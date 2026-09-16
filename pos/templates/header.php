<?php
// templates/header.php - Premium V5 Liquid Glass UI Header

// Ensure session is started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Load shared functions + assets
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../assets.php';

// Detect roles
$is_user_admin = is_admin();
$is_user_developer = is_developer();
$is_user_staff = is_staff();

// Page title
$page_title = $page_title ?? ((APP_NAME ?? 'MBLOGISTICS POS') . ' V5');
$asset_version = defined('APP_VERSION') ? rawurlencode(APP_VERSION) : '5';
$current_page = $_GET['page'] ?? 'dashboard';
$is_voucher_workspace = ($current_page === 'voucher_create');

// Fetch the unread notification count for the logged-in user
$unread_notifications = 0;
if (is_logged_in()) {
    global $connection;
    
    $user_id = $_SESSION['user_id']; 
    $stmt = mysqli_prepare($connection, "SELECT COUNT(id) FROM notifications WHERE user_id = ? AND is_read = 0");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $unread_notifications = mysqli_fetch_row($result)[0];
    mysqli_stmt_close($stmt);
}

?>
<!DOCTYPE html>
<html lang="en" data-app-version="5.0.0">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="icon" type="image/png" href="https://img.icons8.com/ios-filled/50/000000/shipping-container.png">
    <meta name="theme-color" content="#0b6ff5">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link rel="manifest" href="manifest.webmanifest">
    
    <!-- Tailwind + Custom Assets -->
    <!-- Toastify CSS for Notifications -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= $asset_version ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php if (function_exists('load_assets') && isset($page)) load_assets($page); ?>

    <style>
        :root {
            --color-burgundy: #800020;
            --color-bg: #ffffff;
            --color-text: #2c3e50;
            --v3-primary: #4f46e5;
            --v3-secondary: #7c3aed;
        }
        
        /* Global V3 Enhancements */
        body { 
            padding-bottom: 100px; /* Space for floating mobile nav */
            background-color: #f8fafc;
            -webkit-tap-highlight-color: transparent;
        }
        @media (min-width: 768px) {
            body { padding-bottom: 0; }
        }

        /* Glassmorphism Utilities */
        .glass-nav {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.5);
        }
        .glass-mobile-nav {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 10px 40px -10px rgba(0,0,0,0.15);
        }

        /* Nav Link Hover States */
        .nav-link {
            transition: all 0.3s ease;
            position: relative;
        }
        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 3px;
            bottom: -2px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(to right, var(--v3-primary), var(--v3-secondary));
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        .nav-link:hover::after, .nav-link.active::after {
            width: 80%;
        }

        /* Enhanced Dropdown */
        .glass-dropdown {
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .group:hover .glass-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        /* Badges */
        .v3-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background: linear-gradient(135deg, #ff0055, #ff4b2b);
            color: white;
            font-size: 0.7rem;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 999px;
            border: 2px solid white;
            box-shadow: 0 4px 6px rgba(255, 0, 85, 0.3);
            animation: pulse-badge 2s infinite;
        }
        @keyframes pulse-badge {
            0% { box-shadow: 0 0 0 0 rgba(255, 0, 85, 0.4); }
            70% { box-shadow: 0 0 0 6px rgba(255, 0, 85, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 0, 85, 0); }
        }

        /* Mobile Nav Active Item */
        .mobile-nav-item.active {
            color: var(--v3-primary);
        }
        .mobile-nav-item.active svg {
            filter: drop-shadow(0 4px 6px rgba(79, 70, 229, 0.3));
        }
        .mobile-fab {
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            box-shadow: 0 8px 25px -5px rgba(99, 102, 241, 0.5);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .mobile-fab:active {
            transform: scale(0.95);
            box-shadow: 0 4px 15px -5px rgba(99, 102, 241, 0.5);
        }
    </style>
</head>
<body class="bg-gray-50/50 text-gray-800 antialiased font-sans mbpos-v5-shell" data-current-page="<?= htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8') ?>">

<div id="offline-status" class="offline-status" role="status" aria-live="polite" hidden>
    <span class="offline-status-dot" aria-hidden="true"></span>
    <span data-i18n="You are offline. Saved pages remain available; live records require a connection.">You are offline. Saved pages remain available; live records require a connection.</span>
</div>
<?php if (!$is_voucher_workspace): ?>
<div class="mbpos-global-shell">
    <?php if (is_logged_in()): ?>
    <aside class="mbpos-sidebar" aria-label="POS navigation">
        <a href="index.php?page=dashboard" class="mbpos-sidebar-brand">
            <span class="mbpos-brand-mark" aria-hidden="true">M</span>
            <span><strong>MBLOGISTICS</strong><small>POS V5 · FAST · SAFE · GLOBAL</small></span>
        </a>
        <nav class="mbpos-sidebar-nav">
            <a href="index.php?page=dashboard" class="<?= $current_page === 'dashboard' ? 'is-active' : '' ?>"><span aria-hidden="true">⌂</span><span data-i18n="Dashboard">Dashboard</span></a>
            <?php if ($is_user_staff || $is_user_admin || $is_user_developer): ?>
            <a href="index.php?page=voucher_create" class="<?= $current_page === 'voucher_create' ? 'is-active' : '' ?>"><span aria-hidden="true">＋</span><span data-i18n="Create Voucher">Create Voucher</span></a>
            <?php endif; ?>
            <a href="index.php?page=stock_list" class="<?= $current_page === 'stock_list' ? 'is-active' : '' ?>"><span aria-hidden="true">◇</span><span data-i18n="Shipments">Shipments</span></a>
            <a href="index.php?page=customer_list" class="<?= $current_page === 'customer_list' ? 'is-active' : '' ?>"><span aria-hidden="true">♙</span><span data-i18n="Customers">Customers</span></a>
            <a href="index.php?page=voucher_list" class="<?= $current_page === 'voucher_list' ? 'is-active' : '' ?>"><span aria-hidden="true">▤</span><span data-i18n="Ledger">Ledger</span></a>
            <a href="index.php?page=profit_loss" class="<?= $current_page === 'profit_loss' ? 'is-active' : '' ?>"><span aria-hidden="true">▥</span><span data-i18n="Reports">Reports</span></a>
            <?php if ($is_user_admin || $is_user_developer): ?>
            <a href="index.php?page=branches" class="<?= $current_page === 'branches' ? 'is-active' : '' ?>"><span aria-hidden="true">⌘</span><span data-i18n="Branches">Branches</span></a>
            <a href="index.php?page=admin_dashboard" class="<?= $current_page === 'admin_dashboard' ? 'is-active' : '' ?>"><span aria-hidden="true">⚙</span><span data-i18n="Settings">Settings</span></a>
            <?php endif; ?>
        </nav>
        <div class="mbpos-sidebar-status"><span class="mbpos-status-dot"></span><span data-i18n="System Online">System Online</span><small>MBPOS V5</small></div>
    </aside>
    <?php endif; ?>
    <div class="mbpos-global-content">
        <header class="mbpos-global-header">
            <a href="index.php?page=dashboard" class="mbpos-header-brand">
                <span class="mbpos-brand-mark" aria-hidden="true">M</span>
                <span><strong>MBLOGISTICS</strong><small>V5 · Logistics POS</small></span>
            </a>
            <?php if (is_logged_in()): ?>
            <div class="v5-global-search" role="search">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="7" stroke-width="2"/><path d="m20 20-4-4" stroke-width="2" stroke-linecap="round"/></svg>
                <input id="global-search" type="search" autocomplete="off" placeholder="Search customer, tracking number, or voucher..." data-i18n-placeholder="Search customer, tracking number, or voucher..." aria-label="Search customer, tracking number, or voucher...">
                <kbd>⌘ K</kbd>
            </div>
            <?php endif; ?>
            <div class="mbpos-header-actions">
                <button type="button" id="language-toggle" class="language-toggle" aria-label="Switch language" title="Switch language"><span class="language-option language-option-en">EN</span><span class="language-option language-option-mm">မြန်မာ</span></button>
                <button type="button" id="install-pwa" class="pwa-install-button" hidden data-i18n="Install app">Install app</button>
                <?php if (is_logged_in()): ?>
                <a href="index.php?page=notifications" class="mbpos-icon-button" aria-label="Notifications">🔔<span id="notification-badge" class="v3-badge" style="<?= $unread_notifications > 0 ? '' : 'display: none;' ?>"><?= $unread_notifications ?></span></a>
                <div class="mbpos-user-chip"><span class="mbpos-user-avatar"><?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?></span><span><strong><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></strong><small><?= htmlspecialchars(get_user_branch_name() ?? 'Global') ?></small></span></div>
                <a href="index.php?page=logout" class="mbpos-icon-button mbpos-logout" aria-label="Logout">↪</a>
                <?php else: ?>
                <a href="index.php?page=login" class="mbpos-login-link" data-i18n="Login Securely">Login Securely</a>
                <?php endif; ?>
            </div>
        </header>
<?php endif; ?>

<?php if (!$is_voucher_workspace): ?>
<main class="mbpos-global-main container max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-12 relative z-10" data-v5-page="<?= htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8') ?>">
    <?php display_flash_messages(); ?>
<?php else: ?>
    <?php display_flash_messages(); ?>
<?php endif; ?>
<!-- Note: Main tag remains open to wrap content, gets closed in footer.php -->

<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let lastId = 0;
    let notificationPollInitialized = false;
    
    // Notification Fetcher Logic
    function fetchNotifications() {
        <?php if (is_logged_in()): ?>
        const request = window.mbposFetch ? window.mbposFetch(`index.php?page=fetch_notifications&last_id=${lastId}`, { timeout: 5000 }) : fetch(`index.php?page=fetch_notifications&last_id=${lastId}`);
        request
            .then(response => response.json())
            .then(data => {
                const incomingNotifications = Array.isArray(data.notifications) ? data.notifications : [];
                if (notificationPollInitialized && incomingNotifications.length > 0) {
                    incomingNotifications.forEach(notif => {
                        // Premium V5 Toast Notification
                        Toastify({
                            text: notif.message,
                            duration: 8000,
                            close: true,
                            gravity: "top",
                            position: "right",
                            style: {
                                background: "rgba(255, 255, 255, 0.95)",
                                backdropFilter: "blur(10px)",
                                color: "#1e293b",
                                borderLeft: "4px solid #4f46e5",
                                borderRadius: "12px",
                                boxShadow: "0 10px 25px -5px rgba(0, 0, 0, 0.1)",
                                fontWeight: "600",
                                fontSize: "14px",
                                padding: "16px 20px"
                            },
                        }).showToast();
                    });
                }
                if (incomingNotifications.length > 0) {
                    lastId = Math.max(...incomingNotifications.map(notif => parseInt(notif.id, 10) || 0), lastId);
                }
                notificationPollInitialized = true;
                
                // Update both desktop and mobile badges dynamically
                const badges = [document.getElementById('notification-badge'), document.getElementById('mobile-notification-badge')];
                badges.forEach(badge => {
                    if (badge) {
                        if (data.unread_count > 0) {
                            badge.textContent = data.unread_count;
                            badge.style.display = 'inline-block';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                });
            })
            .catch(error => console.error('Error fetching notifications:', error));
        <?php endif; ?>
    }
    
    // Poll every 10 seconds
    setInterval(fetchNotifications, 10000);
    
});
</script>
