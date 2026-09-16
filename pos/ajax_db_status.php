<?php
// pos/ajax_db_status.php - Fetches real-time database status.
header('Content-Type: application/json');

require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/cache.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authorization ---
if (!is_logged_in() || !is_developer()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

global $connection;

$status = mbpos_cache_remember('diagnostics', 'database-status', 15, function () use ($connection) {
    $data = ['size' => 'N/A', 'tables' => 'N/A', 'version' => 'N/A'];
    $metrics_result = mysqli_query(
        $connection,
        "SELECT ROUND(COALESCE(SUM(data_length + index_length), 0) / 1024 / 1024, 2) AS db_size_mb,
                COUNT(*) AS table_count
         FROM information_schema.tables
         WHERE table_schema = DATABASE()"
    );
    if ($metrics_result && $row = mysqli_fetch_assoc($metrics_result)) {
        $data['size'] = $row['db_size_mb'] . ' MB';
        $data['tables'] = (int)$row['table_count'];
    }
    $version_result = mysqli_query($connection, "SELECT VERSION() AS version");
    if ($version_result && $row = mysqli_fetch_assoc($version_result)) {
        $data['version'] = $row['version'];
    }
    return $data;
});

$cache_status = mbpos_cache_status();
$status['cache'] = $cache_status['enabled'] ? 'Redis Online' : 'Database Fallback';
$status['cache_latency_ms'] = $cache_status['latency_ms'];

header('Cache-Control: no-store, private');
echo json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
