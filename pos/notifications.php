<?php
// pos/notifications.php - Displays a history of all system notifications.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authentication ---
if (!is_logged_in()) {
    flash_message('error', 'Please log in to view notifications.');
    redirect('index.php?page=login');
}

global $connection;
$user_id = $_SESSION['user_id'];

// --- Mark all as read ---
if (isset($_GET['action']) && $_GET['action'] === 'mark_all_read') {
    $stmt = mysqli_prepare($connection, "UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    redirect('index.php?page=notifications');
}

// --- Pagination ---
$limit = 20; // Notifications per page
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($page - 1) * $limit;

// Get total number of notifications for the user
$total_stmt = mysqli_prepare($connection, "SELECT COUNT(id) FROM notifications WHERE user_id = ?");
mysqli_stmt_bind_param($total_stmt, 'i', $user_id);
mysqli_stmt_execute($total_stmt);
$total_result = mysqli_stmt_get_result($total_stmt);
$total_notifications = mysqli_fetch_row($total_result)[0];
$total_pages = ceil($total_notifications / $limit);
mysqli_stmt_close($total_stmt);

// --- Fetch Notifications for the current page ---
$notifications = [];
$query = "SELECT message, created_at, is_read FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($connection, $query);
mysqli_stmt_bind_param($stmt, 'iii', $user_id, $limit, $offset);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $notifications[] = $row;
    }
}
mysqli_stmt_close($stmt);


include_template('header', ['page' => 'notifications']);
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white p-8 rounded-2xl shadow-xl border border-gray-100">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-3xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                Notification History
            </h2>
            <a href="index.php?page=notifications&action=mark_all_read" class="btn-secondary">Mark All as Read</a>
        </div>

        <div class="space-y-4">
            <?php if (empty($notifications)): ?>
                <p class="text-center text-gray-500 py-8">You have no notifications yet.</p>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="flex items-start p-4 rounded-lg <?= $notification['is_read'] ? 'bg-gray-50' : 'bg-blue-50 border border-blue-200' ?>">
                        <div class="icon-box <?= $notification['is_read'] ? 'bg-gray-200' : 'bg-blue-100' ?> mr-4">
                             <svg class="icon <?= $notification['is_read'] ? 'text-gray-500' : 'text-blue-600' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        </div>
                        <div>
                            <p class="font-medium <?= $notification['is_read'] ? 'text-gray-700' : 'text-gray-900' ?>"><?= htmlspecialchars($notification['message']) ?></p>
                            <p class="text-sm text-gray-500"><?= date('F j, Y, g:i a', strtotime($notification['created_at'])) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="mt-6 flex justify-between items-center">
            <div>
                <?php if ($page > 1): ?>
                    <a href="index.php?page=notifications&p=<?= $page - 1 ?>" class="btn-secondary">&larr; Previous</a>
                <?php endif; ?>
            </div>
            <div class="text-gray-600">
                Page <?= $page ?> of <?= $total_pages ?>
            </div>
            <div>
                <?php if ($page < $total_pages): ?>
                    <a href="index.php?page=notifications&p=<?= $page + 1 ?>" class="btn-secondary">Next &rarr;</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
include_template('footer');
?>