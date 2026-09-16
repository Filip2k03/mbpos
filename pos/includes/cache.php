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
        $timeout = 0.2;
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
    } catch (Throwable $exception) {
        error_log('MBPOS Redis unavailable: ' . $exception->getMessage());
        $redis = null;
    }

    return $redis;
}
function mbpos_cache_key($namespace, $value) {
    return 'mbpos:v5:' . preg_replace('/[^a-z0-9:_-]/i', '_', $namespace) . ':' . hash('sha256', (string)$value);
}

function mbpos_cache_get($key) {
    $redis = mbpos_redis_connection();
    if (!$redis) return null;
    try {
        $value = $redis->get($key);
        return $value === false ? null : json_decode($value, true);
    } catch (Throwable $exception) {
        error_log('MBPOS Redis read failed: ' . $exception->getMessage());
        return null;
    }
}

function mbpos_cache_set($key, $value, $ttl = 60) {
    $redis = mbpos_redis_connection();
    if (!$redis) return false;
    try {
        return (bool)$redis->setex($key, max(1, (int)$ttl), json_encode($value, JSON_UNESCAPED_UNICODE));
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
    $fresh = $callback();
    if ($fresh !== null) {
        mbpos_cache_set($key, $fresh, $ttl);
    }
    return $fresh;
}
