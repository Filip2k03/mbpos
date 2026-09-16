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
    <link rel="stylesheet" href="assets/css/style.css">
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

        /* V3 Loader - Logistics Edition */
        .ship-loader {
            position: fixed;
            inset: 0;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 99999;
            opacity: 1;
            visibility: visible;
            transition: opacity 0.6s ease, visibility 0.6s ease;
        }
        .ship-loader.hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }
        
        .truck-svg {
            width: 140px;
            height: auto;
            animation: drive 3s ease-in-out infinite;
            filter: drop-shadow(0 10px 15px rgba(99, 102, 241, 0.3));
        }

        /* Truck Animations */
        .wheel {
            animation: spin 1s linear infinite;
            transform-origin: center;
            transform-box: fill-box;
        }
        .exhaust {
            animation: puff 1s ease-out infinite;
            opacity: 0;
        }
        .exhaust-2 { animation-delay: 0.3s; }
        .exhaust-3 { animation-delay: 0.6s; }

        @keyframes drive {
            0%   { transform: translateY(0) rotate(0deg); }
            25%  { transform: translateY(-4px) rotate(-1deg); }
            50%  { transform: translateY(0) rotate(0deg); }
            75%  { transform: translateY(-2px) rotate(1deg); }
            100% { transform: translateY(0) rotate(0deg); }
        }
        @keyframes spin {
            100% { transform: rotate(360deg); }
        }
        @keyframes puff {
            0% { opacity: 0; transform: translate(0, 0) scale(0.5); }
            50% { opacity: 0.6; transform: translate(-10px, -5px) scale(1); }
            100% { opacity: 0; transform: translate(-20px, -10px) scale(1.5); }
        }

        .mb-logo-loader {
            font-size: 28px;
            font-weight: 900;
            background: linear-gradient(to right, #2563eb, #7c3aed);
            -webkit-background-clip: text;
            color: transparent;
            margin-top: 20px;
            letter-spacing: 3px;
        }
        .progress-bar-v3 {
            width: 240px;
            height: 6px;
            background: #f1f5f9;
            border-radius: 999px;
            margin-top: 24px;
            overflow: hidden;
            position: relative;
        }
        .progress-v3 {
            position: absolute;
            top: 0; left: 0; bottom: 0;
            background: linear-gradient(90deg, #2563eb, #7c3aed, #2563eb);
            background-size: 200% 100%;
            animation: shimmer 1.5s linear infinite;
            width: 0%;
            transition: width 0.3s ease-out;
            border-radius: 999px;
        }
        @keyframes shimmer {
            from { background-position: 200% 0; }
            to   { background-position: -200% 0; }
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
<!-- V5 Logistics Loader -->
<div class="ship-loader" id="shipLoader">
    <!-- Custom Animated Logistics Truck SVG -->
    <svg class="truck-svg" viewBox="0 0 120 70" xmlns="http://www.w3.org/2000/svg">
        <!-- Exhaust puffs -->
        <circle class="exhaust exhaust-1" cx="15" cy="45" r="3" fill="#cbd5e1"/>
        <circle class="exhaust exhaust-2" cx="10" cy="40" r="4" fill="#cbd5e1"/>
        <circle class="exhaust exhaust-3" cx="5" cy="35" r="5" fill="#cbd5e1"/>
        
        <!-- Main Body / Cargo Area (Gradient) -->
        <defs>
            <linearGradient id="cargoGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#4f46e5"/>
                <stop offset="100%" stop-color="#7c3aed"/>
            </linearGradient>
            <linearGradient id="cabGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#3b82f6"/>
                <stop offset="100%" stop-color="#2563eb"/>
            </linearGradient>
        </defs>
        
        <path d="M20 20 h 55 v 30 h -55 z" fill="url(#cargoGradient)" rx="4" />
        
        <!-- MBPOS text on cargo -->
        <text x="35" y="40" fill="white" font-family="Arial" font-weight="900" font-size="12" letter-spacing="1">MBPOS</text>
        
        <!-- Truck Cabin -->
        <path d="M 75 25 h 15 l 10 10 v 15 h -25 z" fill="url(#cabGradient)" />
        
        <!-- Window -->
        <path d="M 78 28 h 10 l 6 6 v 5 h -16 z" fill="#e0f2fe" />
        
        <!-- Wheels -->
        <g class="wheel">
            <circle cx="35" cy="55" r="8" fill="#1e293b"/>
            <circle cx="35" cy="55" r="4" fill="#94a3b8"/>
            <circle cx="35" cy="55" r="2" fill="#ffffff"/>
        </g>
        <g class="wheel">
            <circle cx="85" cy="55" r="8" fill="#1e293b"/>
            <circle cx="85" cy="55" r="4" fill="#94a3b8"/>
            <circle cx="85" cy="55" r="2" fill="#ffffff"/>
        </g>
        
        <!-- Headlight -->
        <path d="M 98 42 h 4 v 4 h -4 z" fill="#fbbf24" />
    </svg>

    <div class="mb-logo-loader">MBLOGISTICS</div>
    <div class="progress-bar-v3"><div class="progress-v3" id="loaderProgress"></div></div>
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
<main class="mbpos-global-main container max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-12 relative z-10">
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
    
    // V3 Loader Logic
    const loader = document.getElementById("shipLoader");
    const progress = document.getElementById("loaderProgress");

    let load = 0;
    const interval = setInterval(() => {
        // Variable speed loader effect
        load += Math.floor(Math.random() * 15) + 5; 
        if (load > 100) load = 100;
        progress.style.width = load + "%";

        if (load >= 100) {
            clearInterval(interval);
            setTimeout(() => {
                loader.classList.add("hidden");
                setTimeout(() => loader.style.display = "none", 600); // Fully remove from DOM flow
            }, 200);

        }
    }, 150);
});
</script>
