<?php
// Optional native Redis cache. The POS remains fully functional when Redis
// is not installed or configured; database behavior is never replaced by a
// cache miss or a cache outage.

function mbpos_redis_connection() {
    static $redis = null;
    static $attempted = false;

    if ($attempted) {
        return $redis;
    }
    $attempted = true;

    if (!class_exists('Redis')) {
        return null;
    }

    $host = getenv('MBPOS_REDIS_HOST');
    if (!$host) {
        return null;
    }

    try {
        $redis = new Redis();
        $port = (int)(getenv('MBPOS_REDIS_PORT') ?: 6379);
        $timeout = max(0.05, min(2.0, (float)(getenv('MBPOS_REDIS_CONNECT_TIMEOUT') ?: 0.2)));
        if (!$redis->connect($host, $port, $timeout)) {
            $redis = null;
            return null;
        }
        $password = getenv('MBPOS_REDIS_PASSWORD');
        if ($password !== false && $password !== '') {
            $redis->auth($password);
        }
        $database = getenv('MBPOS_REDIS_DATABASE');
        if ($database !== false && $database !== '') {
            $redis->select((int)$database);
        }
        if (defined('Redis::OPT_READ_TIMEOUT')) {
            $read_timeout = max(0.05, min(2.0, (float)(getenv('MBPOS_REDIS_READ_TIMEOUT') ?: 0.5)));
            $redis->setOption(Redis::OPT_READ_TIMEOUT, $read_timeout);
        }
    } catch (Throwable $exception) {
        error_log('MBPOS Redis unavailable: ' . $exception->getMessage());
        $redis = null;
    }

    return $redis;
}
function mbpos_cache_key($namespace, $value) {
    $prefix = getenv('MBPOS_CACHE_PREFIX') ?: 'mbpos:v5';
    $safe_prefix = preg_replace('/[^a-z0-9:_-]/i', '_', $prefix);
    return $safe_prefix . ':' . preg_replace('/[^a-z0-9:_-]/i', '_', $namespace) . ':' . hash('sha256', (string)$value);
}

function mbpos_cache_get($key) {
    $redis = mbpos_redis_connection();
    if (!$redis) return null;
    try {
        $value = $redis->get($key);
        if ($value === false) return null;
        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    } catch (Throwable $exception) {
        error_log('MBPOS Redis read failed: ' . $exception->getMessage());
        return null;
    }
}

function mbpos_cache_set($key, $value, $ttl = 60) {
    $redis = mbpos_redis_connection();
    if (!$redis) return false;
    try {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) return false;
        return (bool)$redis->setex($key, max(1, (int)$ttl), $encoded);
    } catch (Throwable $exception) {
        error_log('MBPOS Redis write failed: ' . $exception->getMessage());
        return false;
    }
}

function mbpos_cache_del($key) {
    $redis = mbpos_redis_connection();
    if (!$redis) return false;
    try {
        return (bool)$redis->del($key);
    } catch (Throwable $exception) {
        error_log('MBPOS Redis del failed: ' . $exception->getMessage());
        return false;
    }
}

function mbpos_cache_remember($namespace, $key_value, $ttl, callable $callback) {
    $key = mbpos_cache_key($namespace, $key_value);
    $cached = mbpos_cache_get($key);
    if ($cached !== null) {
        return $cached;
    }

    // A short lock limits database stampedes when a popular key expires. If
    // another request owns the lock, wait at most 75 ms before falling back to
    // the database so Redis can never make a POS request hang.
    $redis = mbpos_redis_connection();
    $lock_key = $key . ':lock';
    $lock_token = bin2hex(random_bytes(8));
    $owns_lock = false;
    if ($redis) {
        try {
            $owns_lock = (bool)$redis->set($lock_key, $lock_token, ['nx', 'ex' => 5]);
            if (!$owns_lock) {
                for ($attempt = 0; $attempt < 3; $attempt++) {
                    usleep(25000);
                    $cached = mbpos_cache_get($key);
                    if ($cached !== null) return $cached;
                }
            }
        } catch (Throwable $exception) {
            $owns_lock = false;
        }
    }

    $fresh = $callback();
    if ($fresh !== null) {
        mbpos_cache_set($key, $fresh, $ttl);
    }
    if ($redis && $owns_lock) {
        try {
            $redis->eval(
                'if redis.call("get", KEYS[1]) == ARGV[1] then return redis.call("del", KEYS[1]) else return 0 end',
                [$lock_key, $lock_token],
                1
            );
        } catch (Throwable $exception) {
            // Lock expiry is the safe fallback.
        }
    }
    return $fresh;
}

function mbpos_cache_forget($namespace, $value) {
    return mbpos_cache_del(mbpos_cache_key($namespace, $value));
}

function mbpos_cache_forget_namespace($namespace) {
    $redis = mbpos_redis_connection();
    if (!$redis) return false;
    try {
        $prefix = getenv('MBPOS_CACHE_PREFIX') ?: 'mbpos:v5';
        $safe_prefix = preg_replace('/[^a-z0-9:_-]/i', '_', $prefix);
        $pattern = $safe_prefix . ':' . preg_replace('/[^a-z0-9:_-]/i', '_', $namespace) . ':*';
        $keys = $redis->keys($pattern);
        if (!empty($keys)) {
            $redis->del($keys);
        }
        return true;
    } catch (Throwable $exception) {
        error_log('MBPOS Redis forget namespace failed: ' . $exception->getMessage());
        return false;
    }
}

function mbpos_cache_status() {
    $redis = mbpos_redis_connection();
    if (!$redis) return ['enabled' => false, 'latency_ms' => null];
    $started = microtime(true);
    try {
        $online = (bool)$redis->ping();
        return [
            'enabled' => $online,
            'latency_ms' => $online ? round((microtime(true) - $started) * 1000, 2) : null,
        ];
    } catch (Throwable $exception) {
        return ['enabled' => false, 'latency_ms' => null];
    }
}
