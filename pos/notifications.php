<?php
// pos/notifications.php - Displays a history of all system notifications.

require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/cache.php';

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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
    require_csrf_request();
    $stmt = mysqli_prepare($connection, "UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    mbpos_cache_del(mbpos_cache_key('notifications-unread', $user_id));
    flash_message('success', 'All notifications marked as read.');
    redirect('index.php?page=notifications');
}

// --- Pagination ---
$limit = 20; // Notifications per page
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($page - 1) * $limit;

// Get total number of notifications for the user
$total_stmt = mysqli_prepare($connection, "SELECT COUNT(id) FROM notifications WHERE user_id = ?");
mysqli_stmt_bind_param($total_stmt, 'i', $user_id);
mysqli_stmt_execute($total_stmt);
$total_result = mysqli_stmt_get_result($total_stmt);
$total_notifications = mysqli_fetch_row($total_result)[0] ?? 0;
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

<div class="v5-page">
    <div class="v5-page-head">
        <div class="v5-page-head__copy">
            <span class="v5-kicker" data-i18n="System Alerts">System Alerts</span>
            <h1 data-i18n="Notification History">Notification History</h1>
            <p data-i18n="Operational dispatch alerts and status changes">Operational dispatch alerts and status changes</p>
        </div>
        <div class="v5-page-actions flex items-center gap-3">
            <span class="v5-count"><?= number_format((int)$total_notifications) ?> <span data-i18n="total notifications">total notifications</span></span>
            <form method="POST" action="index.php?page=notifications" style="display:inline;">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn-secondary btn-sm inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span data-i18n="Mark All as Read">Mark All as Read</span>
                </button>
            </form>
        </div>
    </div>

    <section class="v5-panel">
        <div class="v5-panel__head">
            <h2 data-i18n="Recent Alerts">Recent Alerts</h2>
            <span class="v5-count" data-i18n="Page">Page <?= $page ?> <span data-i18n="of">of</span> <?= max(1, $total_pages) ?></span>
        </div>
        <div class="v5-panel__body">
            <?php if (empty($notifications)): ?>
                <div class="v5-empty py-12">
                    <span class="v5-empty__icon"><?= mbpos_icon('bell', 'w-8 h-8 text-slate-400') ?></span>
                    <strong data-i18n="You have no notifications yet.">You have no notifications yet.</strong>
                    <p data-i18n="Operational alerts and status updates will appear here.">Operational alerts and status updates will appear here.</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="flex items-start gap-3.5 p-4 rounded-xl transition-all border <?= $notification['is_read'] ? 'bg-white border-slate-200/80 shadow-sm' : 'bg-blue-50/60 border-blue-200 shadow-sm' ?>">
                            <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 <?= $notification['is_read'] ? 'bg-slate-100 text-slate-500' : 'bg-blue-600 text-white shadow-sm' ?>">
                                <?= mbpos_icon('bell', 'w-4 h-4') ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm leading-relaxed <?= $notification['is_read'] ? 'text-slate-700 font-medium' : 'text-slate-900 font-bold' ?>"><?= htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8') ?></p>
                                <div class="text-[11px] text-slate-400 mt-1 flex flex-wrap items-center gap-2 font-mono">
                                    <span><?= format_datetime_myanmar($notification['created_at'], 'date') ?> · <?= format_datetime_myanmar($notification['created_at'], 'time') ?></span>
                                    <span class="text-[10px] px-1.5 py-0.2 bg-slate-100 rounded text-slate-600 font-sans"><?= format_datetime_myanmar($notification['created_at'], 'relative') ?></span>
                                </div>
                            </div>
                            <?php if (!$notification['is_read']): ?>
                                <span class="w-2.5 h-2.5 rounded-full bg-blue-600 mt-1.5 shrink-0" title="Unread"></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="v5-panel__head border-t border-slate-100 flex items-center justify-between">
                <div>
                    <?php if ($page > 1): ?>
                        <a href="index.php?page=notifications&p=<?= $page - 1 ?>" class="btn-secondary btn-sm inline-flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            <span data-i18n="Previous">Previous</span>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="text-xs font-bold text-slate-500">
                    <span data-i18n="Page">Page</span> <?= $page ?> <span data-i18n="of">of</span> <?= max(1, $total_pages) ?>
                </div>

                <div>
                    <?php if ($page < $total_pages): ?>
                        <a href="index.php?page=notifications&p=<?= $page + 1 ?>" class="btn-secondary btn-sm inline-flex items-center gap-1.5">
                            <span data-i18n="Next">Next</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php include_template('footer'); ?>
