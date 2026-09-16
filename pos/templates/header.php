<?php
// templates/header.php - Canonical V5 Unified POS Shell & Navigation

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../assets.php';

// Detect authenticated roles
$is_user_admin = is_admin();
$is_user_developer = is_developer();
$is_user_staff = is_staff();

// Page and shell variables
$current_page = $_GET['page'] ?? ($page ?? 'dashboard');
$page_title = $page_title ?? ((APP_NAME ?? 'MBLOGISTICS POS') . ' V5');
$asset_version = defined('APP_VERSION') ? rawurlencode(APP_VERSION) : '5.1.0';
$is_voucher_workspace = ($current_page === 'voucher_create');

// Fetch unread notification count
$unread_notifications = 0;
if (is_logged_in()) {
    global $connection;
    $user_id = $_SESSION['user_id'] ?? 0;
    $stmt = mysqli_prepare($connection, "SELECT COUNT(id) FROM notifications WHERE user_id = ? AND is_read = 0");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $unread_notifications = (int)(mysqli_fetch_row($result)[0] ?? 0);
        mysqli_stmt_close($stmt);
    }
}

// Canonical V5 Target Information Architecture (No Customer UI per AGENTS.md)
$nav_groups = [
    'Operations' => [
        [
            'key' => 'dashboard',
            'label' => 'Dashboard',
            'route' => 'index.php?page=dashboard',
            'icon' => '⌂',
            'allowed' => true,
            'aliases' => ['dashboard'],
            'mobile_priority' => 1,
        ],
        [
            'key' => 'voucher_create',
            'label' => 'Create Voucher',
            'route' => 'index.php?page=voucher_create',
            'icon' => '＋',
            'allowed' => ($is_user_staff || $is_user_admin || $is_user_developer),
            'aliases' => ['voucher_create'],
            'mobile_priority' => 2,
        ],
        [
            'key' => 'stock_list',
            'label' => 'Shipments',
            'route' => 'index.php?page=stock_list',
            'icon' => '◇',
            'allowed' => true,
            'aliases' => ['stock_list'],
            'mobile_priority' => 3,
        ],
        [
            'key' => 'voucher_list',
            'label' => 'Voucher Ledger',
            'route' => 'index.php?page=voucher_list',
            'icon' => '▤',
            'allowed' => true,
            'aliases' => ['voucher_list', 'voucher_bulk_update', 'voucher_view'],
            'mobile_priority' => 4,
        ],
    ],
    'Finance' => [
        [
            'key' => 'expenses',
            'label' => 'Expenses',
            'route' => 'index.php?page=expenses',
            'icon' => '💳',
            'allowed' => ($is_user_admin || $is_user_developer),
            'aliases' => ['expenses'],
        ],
        [
            'key' => 'other_income',
            'label' => 'Other Income',
            'route' => 'index.php?page=other_income',
            'icon' => '💰',
            'allowed' => ($is_user_admin || $is_user_developer),
            'aliases' => ['other_income'],
        ],
        [
            'key' => 'profit_loss',
            'label' => 'Profit & Loss',
            'route' => 'index.php?page=profit_loss',
            'icon' => '▥',
            'allowed' => ($is_user_admin || $is_user_developer),
            'aliases' => ['profit_loss'],
        ],
    ],
    'Configuration' => [
        [
            'key' => 'branches',
            'label' => 'Branches',
            'route' => 'index.php?page=branches',
            'icon' => '⌘',
            'allowed' => ($is_user_admin || $is_user_developer),
            'aliases' => ['branches'],
        ],
        [
            'key' => 'currencies',
            'label' => 'Currencies',
            'route' => 'index.php?page=currencies',
            'icon' => '💱',
            'allowed' => ($is_user_admin || $is_user_developer),
            'aliases' => ['currencies'],
        ],
        [
            'key' => 'delivery_types',
            'label' => 'Delivery Types',
            'route' => 'index.php?page=delivery_types',
            'icon' => '🚚',
            'allowed' => ($is_user_admin || $is_user_developer),
            'aliases' => ['delivery_types'],
        ],
        [
            'key' => 'item_types',
            'label' => 'Item Types',
            'route' => 'index.php?page=item_types',
            'icon' => '📦',
            'allowed' => ($is_user_admin || $is_user_developer),
            'aliases' => ['item_types'],
        ],
    ],
    'Administration' => [
        [
            'key' => 'register',
            'label' => 'User Management',
            'route' => 'index.php?page=register',
            'icon' => '👥',
            'allowed' => ($is_user_admin || $is_user_developer),
            'aliases' => ['register'],
        ],
        [
            'key' => 'admin_dashboard',
            'label' => 'Admin Control Center',
            'route' => 'index.php?page=admin_dashboard',
            'icon' => '⚙',
            'allowed' => ($is_user_admin || $is_user_developer),
            'aliases' => ['admin_dashboard'],
        ],
        [
            'key' => 'developer_dashboard',
            'label' => 'Dev Center',
            'route' => 'index.php?page=developer_dashboard',
            'icon' => '🛠',
            'allowed' => $is_user_developer,
            'aliases' => ['developer_dashboard'],
        ],
        [
            'key' => 'maintenance',
            'label' => 'Maintenance Zones',
            'route' => 'index.php?page=maintenance',
            'icon' => '🛡',
            'allowed' => $is_user_developer,
            'aliases' => ['maintenance'],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="en" data-app-version="5.1.0">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" type="image/png" href="https://img.icons8.com/ios-filled/50/000000/shipping-container.png">
    <meta name="theme-color" content="#0b6ff5">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link rel="manifest" href="manifest.webmanifest">
    
    <!-- Shared Assets -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= $asset_version ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php if (function_exists('load_assets')) load_assets($current_page); ?>
</head>
<body class="bg-gray-50/50 text-gray-800 antialiased font-sans mbpos-v5-shell <?= $is_voucher_workspace ? 'is-voucher-create' : '' ?>" data-current-page="<?= htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8') ?>">

<div id="offline-status" class="offline-status" role="status" aria-live="polite" hidden>
    <span class="offline-status-dot" aria-hidden="true"></span>
    <span data-i18n="You are offline. Saved pages remain available; live records require a connection.">You are offline. Saved pages remain available; live records require a connection.</span>
</div>

<?php if (!$is_voucher_workspace): ?>
<div class="mbpos-global-shell">
    <?php if (is_logged_in()): ?>
    <aside class="mbpos-sidebar" aria-label="POS navigation">
        <div class="flex items-center justify-between w-full">
            <a href="index.php?page=dashboard" class="mbpos-sidebar-brand">
                <span class="mbpos-brand-mark" aria-hidden="true">M</span>
                <span><strong>MBLOGISTICS</strong><small>POS V5 · GLOBAL</small></span>
            </a>
            <button type="button" id="mbpos-sidebar-close" class="mbpos-sidebar-close" aria-label="Close navigation" title="Close menu">&times;</button>
        </div>

        <div class="mbpos-sidebar-nav-container">
            <?php foreach ($nav_groups as $group_name => $group_items):
                $visible_items = array_filter($group_items, fn($item) => !empty($item['allowed']));
                if (empty($visible_items)) continue;
            ?>
            <div class="mbpos-nav-group">
                <div class="mbpos-nav-group-title" data-i18n="<?= e($group_name) ?>"><?= e($group_name) ?></div>
                <nav class="mbpos-sidebar-nav">
                    <?php foreach ($visible_items as $item):
                        $is_active = in_array($current_page, $item['aliases'] ?? [$item['key']], true);
                    ?>
                    <a href="<?= e($item['route']) ?>" class="<?= $is_active ? 'is-active' : '' ?>" data-nav-key="<?= e($item['key']) ?>">
                        <span aria-hidden="true"><?= $item['icon'] ?></span>
                        <span data-i18n="<?= e($item['label']) ?>"><?= e($item['label']) ?></span>
                    </a>
                    <?php endforeach; ?>
                </nav>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mbpos-sidebar-status">
            <span class="mbpos-status-dot"></span>
            <span data-i18n="System Online">System Online</span>
            <small>MBPOS V5</small>
        </div>
    </aside>
    <?php endif; ?>

    <div class="mbpos-global-content">
        <header class="mbpos-global-header">
            <?php if (is_logged_in()): ?>
            <button type="button" id="mobile-drawer-toggle" class="mbpos-icon-button md:hidden" aria-label="Open menu" title="Open navigation menu">☰</button>
            <?php endif; ?>

            <a href="index.php?page=dashboard" class="mbpos-header-brand">
                <span class="mbpos-brand-mark" aria-hidden="true">M</span>
                <span><strong>MBLOGISTICS</strong><small>V5 · Logistics POS</small></span>
            </a>

            <?php if (is_logged_in()): ?>
            <div class="v5-global-search" role="search" id="cmd-palette-trigger" title="Command Palette (⌘K)">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="7" stroke-width="2"/><path d="m20 20-4-4" stroke-width="2" stroke-linecap="round"/></svg>
                <input id="global-search" type="search" autocomplete="off" placeholder="Search vouchers, tracking, actions..." data-i18n-placeholder="Search vouchers, actions..." aria-label="Search vouchers or actions">
                <kbd>⌘ K</kbd>
            </div>
            <?php endif; ?>

            <div class="mbpos-header-actions">
                <button type="button" id="language-toggle" class="language-toggle" aria-label="Switch language" title="Switch language">
                    <span class="language-option language-option-en">EN</span>
                    <span class="language-option language-option-mm">မြန်မာ</span>
                </button>

                <button type="button" id="install-pwa" class="pwa-install-button" hidden data-i18n="Install app">Install app</button>

                <?php if (is_logged_in()): ?>
                <a href="index.php?page=notifications" class="mbpos-icon-button" aria-label="Notifications" title="Notifications">
                    🔔<span id="notification-badge" class="v3-badge" style="<?= $unread_notifications > 0 ? '' : 'display: none;' ?>"><?= $unread_notifications ?></span>
                </a>
                <div class="mbpos-user-chip">
                    <span class="mbpos-user-avatar"><?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?></span>
                    <span>
                        <strong><?= htmlspecialchars($_SESSION['username'] ?? 'User', ENT_QUOTES, 'UTF-8') ?></strong>
                        <small><?= htmlspecialchars(get_user_branch_name() ?? 'Global', ENT_QUOTES, 'UTF-8') ?></small>
                    </span>
                </div>
                <a href="index.php?page=logout" class="mbpos-icon-button mbpos-logout" aria-label="Logout" title="Logout">↪</a>
                <?php else: ?>
                <a href="index.php?page=login" class="mbpos-login-link" data-i18n="Login Securely">Login Securely</a>
                <?php endif; ?>
            </div>
        </header>

        <!-- V5 Command Palette Modal (⌘K / Ctrl+K) -->
        <?php if (is_logged_in()): ?>
        <div id="mbpos-cmd-palette" class="mbpos-cmd-modal" hidden role="dialog" aria-modal="true" aria-label="Command Palette">
            <div class="mbpos-cmd-backdrop"></div>
            <div class="mbpos-cmd-dialog">
                <div class="mbpos-cmd-head">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="7" stroke-width="2"/><path d="m20 20-4-4" stroke-width="2" stroke-linecap="round"/></svg>
                    <input type="search" id="mbpos-cmd-input" class="mbpos-cmd-input" placeholder="Search vouchers, jumps, actions..." data-i18n-placeholder="Search vouchers, jumps, actions..." autocomplete="off">
                </div>
                <div class="mbpos-cmd-body">
                    <!-- Dynamic Search Jump -->
                    <a href="index.php?page=voucher_list" id="mbpos-cmd-search-jump" class="mbpos-cmd-item" style="display:none;">
                        <div class="mbpos-cmd-item-left">
                            <span class="mbpos-cmd-item-icon">🔎</span>
                            <span>Search vouchers for "<strong class="mbpos-cmd-query-text"></strong>"</span>
                        </div>
                        <span class="mbpos-cmd-item-badge">Enter ↵</span>
                    </a>

                    <div class="mbpos-cmd-section-title" data-i18n="Quick Actions">Quick Actions</div>
                    <a href="index.php?page=voucher_create" class="mbpos-cmd-item" data-jump="create">
                        <div class="mbpos-cmd-item-left">
                            <span class="mbpos-cmd-item-icon">＋</span>
                            <span data-i18n="Create Voucher">Create Voucher</span>
                        </div>
                        <span class="mbpos-cmd-item-badge">Operations</span>
                    </a>
                    <a href="index.php?page=voucher_list" class="mbpos-cmd-item" data-jump="ledger">
                        <div class="mbpos-cmd-item-left">
                            <span class="mbpos-cmd-item-icon">▤</span>
                            <span data-i18n="Voucher Ledger">Voucher Ledger</span>
                        </div>
                        <span class="mbpos-cmd-item-badge">Operations</span>
                    </a>
                    <a href="index.php?page=stock_list" class="mbpos-cmd-item" data-jump="shipments">
                        <div class="mbpos-cmd-item-left">
                            <span class="mbpos-cmd-item-icon">◇</span>
                            <span data-i18n="Shipments">Shipments</span>
                        </div>
                        <span class="mbpos-cmd-item-badge">Operations</span>
                    </a>
                    <?php if ($is_user_admin || $is_user_developer): ?>
                    <a href="index.php?page=profit_loss" class="mbpos-cmd-item" data-jump="profit">
                        <div class="mbpos-cmd-item-left">
                            <span class="mbpos-cmd-item-icon">▥</span>
                            <span data-i18n="Profit & Loss">Profit & Loss</span>
                        </div>
                        <span class="mbpos-cmd-item-badge">Finance</span>
                    </a>
                    <a href="index.php?page=expenses" class="mbpos-cmd-item" data-jump="expenses">
                        <div class="mbpos-cmd-item-left">
                            <span class="mbpos-cmd-item-icon">💳</span>
                            <span data-i18n="Expenses">Expenses</span>
                        </div>
                        <span class="mbpos-cmd-item-badge">Finance</span>
                    </a>
                    <a href="index.php?page=branches" class="mbpos-cmd-item" data-jump="branches">
                        <div class="mbpos-cmd-item-left">
                            <span class="mbpos-cmd-item-icon">⌘</span>
                            <span data-i18n="Branches">Branches</span>
                        </div>
                        <span class="mbpos-cmd-item-badge">Config</span>
                    </a>
                    <a href="index.php?page=register" class="mbpos-cmd-item" data-jump="users">
                        <div class="mbpos-cmd-item-left">
                            <span class="mbpos-cmd-item-icon">👥</span>
                            <span data-i18n="User Management">User Management</span>
                        </div>
                        <span class="mbpos-cmd-item-badge">Admin</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($is_user_developer): ?>
                    <a href="index.php?page=developer_dashboard" class="mbpos-cmd-item" data-jump="dev">
                        <div class="mbpos-cmd-item-left">
                            <span class="mbpos-cmd-item-icon">🛠</span>
                            <span data-i18n="Dev Center">Dev Center</span>
                        </div>
                        <span class="mbpos-cmd-item-badge">Developer</span>
                    </a>
                    <?php endif; ?>
                </div>
                <div class="mbpos-cmd-footer">
                    <span data-i18n="Press Esc to close">Press Esc to close</span>
                    <span><kbd>⌘K</kbd> / <kbd>Ctrl+K</kbd></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Mobile Bottom Navigation (4 High-Frequency Actions + Off-Canvas Menu) -->
        <?php if (is_logged_in()): ?>
        <nav class="mbpos-mobile-nav" aria-label="Mobile Navigation">
            <a href="index.php?page=dashboard" class="mbpos-mobile-nav-item <?= $current_page === 'dashboard' ? 'is-active' : '' ?>">
                <span aria-hidden="true">⌂</span>
                <span data-i18n="Dashboard">Dashboard</span>
            </a>
            <?php if ($is_user_staff || $is_user_admin || $is_user_developer): ?>
            <a href="index.php?page=voucher_create" class="mbpos-mobile-nav-item <?= $current_page === 'voucher_create' ? 'is-active' : '' ?>">
                <span aria-hidden="true">＋</span>
                <span data-i18n="Create Voucher">Create</span>
            </a>
            <?php endif; ?>
            <a href="index.php?page=stock_list" class="mbpos-mobile-nav-item <?= $current_page === 'stock_list' ? 'is-active' : '' ?>">
                <span aria-hidden="true">◇</span>
                <span data-i18n="Shipments">Shipments</span>
            </a>
            <a href="index.php?page=voucher_list" class="mbpos-mobile-nav-item <?= in_array($current_page, ['voucher_list', 'voucher_bulk_update', 'voucher_view'], true) ? 'is-active' : '' ?>">
                <span aria-hidden="true">▤</span>
                <span data-i18n="Ledger">Ledger</span>
            </a>
            <button type="button" id="mobile-nav-more" class="mbpos-mobile-nav-item" aria-label="Open full menu">
                <span aria-hidden="true">☰</span>
                <span data-i18n="Menu">Menu</span>
            </button>
        </nav>
        <?php endif; ?>

        <main class="mbpos-global-main container max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-12 relative z-10" data-v5-page="<?= htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8') ?>">
            <?php display_flash_messages(); ?>
<?php else: ?>
    <?php display_flash_messages(); ?>
<?php endif; ?>

<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let lastId = 0;
    let notificationPollInitialized = false;
    let notificationPollInFlight = false;
    
    function fetchNotifications() {
        <?php if (is_logged_in()): ?>
        if (notificationPollInFlight || document.hidden) return;
        notificationPollInFlight = true;
        const request = window.mbposFetch ? window.mbposFetch(`index.php?page=fetch_notifications&last_id=${lastId}`, { timeout: 5000 }) : fetch(`index.php?page=fetch_notifications&last_id=${lastId}`);
        request
            .then(response => response.json())
            .then(data => {
                const incomingNotifications = Array.isArray(data.notifications) ? data.notifications : [];
                if (notificationPollInitialized && incomingNotifications.length > 0) {
                    incomingNotifications.forEach(notif => {
                        Toastify({
                            text: notif.message,
                            duration: 7000,
                            close: true,
                            gravity: "top",
                            position: "right",
                            style: {
                                background: "rgba(255, 255, 255, 0.96)",
                                backdropFilter: "blur(12px)",
                                color: "#10233f",
                                borderLeft: "4px solid #0b6ff5",
                                borderRadius: "12px",
                                boxShadow: "0 10px 25px -5px rgba(0, 0, 0, 0.12)",
                                fontWeight: "600",
                                fontSize: "14px",
                                padding: "14px 18px"
                            },
                        }).showToast();
                    });
                }
                if (incomingNotifications.length > 0) {
                    lastId = Math.max(...incomingNotifications.map(notif => parseInt(notif.id, 10) || 0), lastId);
                }
                notificationPollInitialized = true;
                
                const badge = document.getElementById('notification-badge');
                if (badge) {
                    if (data.unread_count > 0) {
                        badge.textContent = data.unread_count;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            })
            .catch(error => console.error('Error fetching notifications:', error))
            .finally(() => { notificationPollInFlight = false; });
        <?php endif; ?>
    }
    
    fetchNotifications();
    setInterval(fetchNotifications, 15000);
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) fetchNotifications();
    });
});
</script>
