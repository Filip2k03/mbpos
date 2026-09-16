<?php
// pos/fetch_notifications.php - API endpoint to get new notifications.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Only allow logged-in users to fetch notifications
if (!is_logged_in()) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

global $connection;

// Get the ID of the last notification the user has seen
$last_id = intval($_GET['last_id'] ?? 0);
$user_id = intval($_SESSION['user_id']);

$notifications = [];

// Prepare a query to fetch notifications newer than the last one seen
$query = "SELECT id, message, created_at FROM notifications WHERE user_id = ? AND id > ? ORDER BY id ASC";
$stmt = mysqli_prepare($connection, $query);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ii', $user_id, $last_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $notifications[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

$unread_count = 0;
$count_stmt = mysqli_prepare($connection, "SELECT COUNT(id) FROM notifications WHERE user_id = ? AND is_read = 0");
if ($count_stmt) {
    mysqli_stmt_bind_param($count_stmt, 'i', $user_id);
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
    $unread_count = intval(mysqli_fetch_row($count_result)[0] ?? 0);
    mysqli_stmt_close($count_stmt);
}

// Notifications are user-specific and must never be shared by browser/proxy caches.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
echo json_encode([
    'notifications' => $notifications,
    'unread_count' => $unread_count,
]);
