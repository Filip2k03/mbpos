<?php
// pos/ajax_db_status.php - Fetches real-time database status.
header('Content-Type: application/json');

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authorization ---
if (!is_logged_in() || !is_developer()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

global $connection, $database;

$status = [
    'size' => 'N/A',
    'tables' => 'N/A',
    'version' => 'N/A'
];

// Get DB Size
$size_query = "SELECT table_schema AS 'db_name', ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'db_size_mb' FROM information_schema.tables WHERE table_schema = '$database' GROUP BY table_schema;";
$size_result = mysqli_query($connection, $size_query);
if($size_result && $row = mysqli_fetch_assoc($size_result)) {
    $status['size'] = $row['db_size_mb'] . ' MB';
}

// Get Table Count
$tables_query = "SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = '$database';";
$tables_result = mysqli_query($connection, $tables_query);
if($tables_result && $row = mysqli_fetch_assoc($tables_result)) {
    $status['tables'] = $row['table_count'];
}

// Get MySQL Version
$version_query = "SELECT VERSION() as version;";
$version_result = mysqli_query($connection, $version_query);
if($version_result && $row = mysqli_fetch_assoc($version_result)) {
    $status['version'] = $row['version'];
}

echo json_encode($status);
?>