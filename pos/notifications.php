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

<div class="relative min-h-[85vh] p-4 sm:p-8 font-sans">
    <div class="max-w-4xl mx-auto relative z-10 animate-fadeInDown">
        <div class="v5-glass-card p-6 sm:p-10 shadow-2xl overflow-hidden">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8 pb-5 border-b border-slate-100 gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white flex items-center justify-center shadow-lg shadow-blue-500/25">
                        <span class="text-xl">🔔</span>
                    </div>
                    <div>
                        <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight" data-i18n="Alerts">
                            Notification History
                        </h2>
                        <p class="text-xs font-semibold text-slate-400 mt-0.5">Operational dispatch alerts and status changes</p>
                    </div>
                </div>
                <a href="index.php?page=notifications&action=mark_all_read" class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white/80 hover:bg-white text-slate-700 text-xs font-bold transition-all shadow-sm">
                    ✓ Mark All as Read
                </a>
            </div>

            <div class="space-y-3">
                <?php if (empty($notifications)): ?>
                    <div class="text-center py-12 text-slate-400 font-medium">
                        <span class="text-3xl block mb-2">📭</span>
                        You have no notifications yet.
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="flex items-start gap-4 p-4 rounded-2xl transition-all <?= $notification['is_read'] ? 'bg-slate-50/60 border border-slate-100' : 'bg-blue-50/70 border border-blue-200/80 shadow-sm' ?>">
                            <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 <?= $notification['is_read'] ? 'bg-slate-200/70 text-slate-500' : 'bg-blue-600 text-white shadow-md shadow-blue-500/30' ?>">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold <?= $notification['is_read'] ? 'text-slate-700' : 'text-slate-900 font-bold' ?>"><?= htmlspecialchars($notification['message']) ?></p>
                                <p class="text-[11px] font-medium text-slate-400 mt-1"><?= date('F j, Y · g:i A', strtotime($notification['created_at'])) ?> (GMT+6:30)</p>
                            </div>
                            <?php if (!$notification['is_read']): ?>
                                <span class="w-2 h-2 rounded-full bg-blue-500 mt-2 shrink-0"></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="mt-8 pt-5 border-t border-slate-100 flex justify-between items-center text-xs font-bold text-slate-500">
                <div>
                    <?php if ($page > 1): ?>
                        <a href="index.php?page=notifications&p=<?= $page - 1 ?>" class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 transition-all">&larr; Previous</a>
                    <?php endif; ?>
                </div>
                <div>
                    Page <?= $page ?> of <?= max(1, $total_pages) ?>
                </div>
                <div>
                    <?php if ($page < $total_pages): ?>
                        <a href="index.php?page=notifications&p=<?= $page + 1 ?>" class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 transition-all">Next &rarr;</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_template('footer'); ?>