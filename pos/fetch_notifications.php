<?php
// pos/fetch_notifications.php - API endpoint to get new notifications.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Only allow logged-in users to fetch notifications
if (!is_logged_in()) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

global $connection;

// Get the ID of the last notification the user has seen
$last_id = intval($_GET['last_id'] ?? 0);

$notifications = [];

// Prepare a query to fetch notifications newer than the last one seen
$query = "SELECT id, message, created_at FROM notifications WHERE id > ? ORDER BY id ASC";
$stmt = mysqli_prepare($connection, $query);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $last_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $notifications[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
}

// Set the content type header to JSON and output the data
header('Content-Type: application/json');
echo json_encode($notifications);
