<?php
// templates/header.php - Premium V3 Liquid Glass UI Header

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
$page_title = $page_title ?? (APP_NAME ?? 'MBLOGISTICS POS');
$current_page = $_GET['page'] ?? 'dashboard';

// Fetch unread notification count for the logged-in user
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
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="icon" type="image/png" href="https://img.icons8.com/ios-filled/50/000000/shipping-container.png">
    
    <!-- Tailwind + Custom Assets -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
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
<body class="bg-gray-50/50 text-gray-800 antialiased font-sans">

<!-- V3 Logistics Loader -->
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

<!-- Desktop Glass Header -->
<header class="glass-nav sticky top-0 z-40 transition-all duration-300">
    <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-20">
            <!-- Brand -->
            <a href="index.php?page=dashboard" class="flex items-center gap-2 group">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 flex items-center justify-center shadow-md shadow-indigo-500/30 transform group-hover:rotate-12 transition-all">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <span class="text-2xl font-extrabold bg-gradient-to-r from-gray-900 to-gray-700 bg-clip-text text-transparent tracking-tight">MBLOGISTICS</span>
            </a>

            <!-- Desktop Links -->
            <div class="hidden md:flex items-center space-x-2">
                <?php if (is_logged_in()): ?>
                    <a href="index.php?page=dashboard" class="nav-link px-4 py-2 text-gray-600 hover:text-indigo-600 font-bold text-sm rounded-lg <?= $current_page == 'dashboard' ? 'active text-indigo-600' : '' ?>">Dashboard</a>
                    <a href="index.php?page=voucher_list" class="nav-link px-4 py-2 text-gray-600 hover:text-indigo-600 font-bold text-sm rounded-lg <?= $current_page == 'voucher_list' ? 'active text-indigo-600' : '' ?>">Ledger</a>
                    <a href="index.php?page=profit_loss" class="nav-link px-4 py-2 text-gray-600 hover:text-indigo-600 font-bold text-sm rounded-lg <?= $current_page == 'profit_loss' ? 'active text-indigo-600' : '' ?>">Financials</a>
                    
                    <?php if ($is_user_admin || $is_user_developer): ?>
                        <div class="relative group">
                            <button class="nav-link px-4 py-2 text-gray-600 hover:text-indigo-600 font-bold text-sm rounded-lg flex items-center gap-1 <?= in_array($current_page, ['admin_dashboard', 'developer_dashboard', 'branches', 'register']) ? 'active text-indigo-600' : '' ?>">
                                Admin Tools
                                <svg class="w-4 h-4 transition-transform group-hover:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </button>
                            <!-- Dropdown -->
                            <div class="glass-dropdown absolute left-0 mt-2 w-56 bg-white/95 backdrop-blur-xl rounded-2xl shadow-[0_10px_40px_rgb(0,0,0,0.1)] border border-gray-100 py-2 z-50">
                                <a href="index.php?page=admin_dashboard" class="flex items-center gap-3 px-4 py-2.5 text-sm font-bold text-gray-600 hover:text-indigo-600 hover:bg-indigo-50/50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                    Admin Dashboard
                                </a>
                                <a href="index.php?page=register" class="flex items-center gap-3 px-4 py-2.5 text-sm font-bold text-gray-600 hover:text-indigo-600 hover:bg-indigo-50/50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                    User Management
                                </a>
                                <?php if ($is_user_developer): ?>
                                <a href="index.php?page=developer_dashboard" class="flex items-center gap-3 px-4 py-2.5 text-sm font-bold text-gray-600 hover:text-indigo-600 hover:bg-indigo-50/50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                                    Dev Center
                                </a>
                                <?php endif; ?>
                                <a href="index.php?page=branches" class="flex items-center gap-3 px-4 py-2.5 text-sm font-bold text-gray-600 hover:text-indigo-600 hover:bg-indigo-50/50 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    Branches
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="flex items-center gap-5 ml-2 border-l border-gray-200 pl-6">
                        <!-- Notifications -->
                        <a href="index.php?page=notifications" class="relative p-2 text-gray-400 hover:text-indigo-600 transition-colors bg-gray-50 hover:bg-indigo-50 rounded-full">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            <span id="notification-badge" class="v3-badge" style="<?= $unread_notifications > 0 ? '' : 'display: none;' ?>"><?= $unread_notifications ?></span>
                        </a>

                        <!-- User Profile Pill -->
                        <div class="flex items-center gap-3 bg-white border border-gray-100 shadow-sm py-1.5 px-2 pr-4 rounded-full">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-indigo-100 to-blue-50 text-indigo-600 flex items-center justify-center font-bold text-sm border border-indigo-100">
                                <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-gray-800 font-extrabold text-sm leading-tight"><?= htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider leading-tight"><?= htmlspecialchars(get_user_branch_name() ?? 'Global'); ?></span>
                            </div>
                        </div>

                        <!-- Logout -->
                        <a href="index.php?page=logout" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-xl transition-colors" title="Logout">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 11-6 0v-1m6-10v1" /></svg>
                        </a>
                    </div>
                <?php else: ?>
                    <a href="index.php?page=login" class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl font-bold hover:shadow-lg hover:shadow-indigo-500/30 transition-all transform hover:-translate-y-0.5">Login Securely</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
</header>

<!-- Floating Glass Mobile Nav (Premium V3) -->
<?php if (is_logged_in()): ?>
<div class="md:hidden fixed bottom-5 left-4 right-4 z-50">
    <nav class="glass-mobile-nav rounded-3xl px-2 py-3 flex justify-between items-center relative" aria-label="Mobile Navigation">
        
        <a href="index.php?page=dashboard" class="mobile-nav-item flex-1 flex flex-col items-center gap-1 text-gray-400 transition-colors <?= $current_page === 'dashboard' ? 'active' : '' ?>">
            <div class="<?= $current_page === 'dashboard' ? 'bg-indigo-50 p-1.5 rounded-xl' : 'p-1.5' ?> transition-colors">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            </div>
            <span class="text-[10px] font-bold tracking-wide">Home</span>
        </a>

        <a href="index.php?page=notifications" class="mobile-nav-item flex-1 flex flex-col items-center gap-1 text-gray-400 transition-colors relative <?= $current_page === 'notifications' ? 'active' : '' ?>">
            <div class="<?= $current_page === 'notifications' ? 'bg-indigo-50 p-1.5 rounded-xl' : 'p-1.5' ?> transition-colors relative">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                <span id="mobile-notification-badge" class="v3-badge right-0 top-0" style="<?= $unread_notifications > 0 ? '' : 'display: none;' ?>"><?= $unread_notifications ?></span>
            </div>
            <span class="text-[10px] font-bold tracking-wide">Alerts</span>
        </a>
        
        <!-- Center Floating Action Button (FAB) -->
        <?php if ($is_user_staff || $is_user_admin || $is_user_developer): ?>
        <div class="flex-1 flex justify-center">
            <a href="index.php?page=voucher_create" class="mobile-fab flex items-center justify-center text-white rounded-full w-14 h-14 -mt-10 border-4 border-white z-20">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="M12 4v16m8-8H4" />
                </svg>
            </a>
        </div>
        <?php else: ?>
        <div class="flex-1"></div> <!-- Spacer if not authorized to create -->
        <?php endif; ?>

        <a href="index.php?page=voucher_list" class="mobile-nav-item flex-1 flex flex-col items-center gap-1 text-gray-400 transition-colors <?= $current_page === 'voucher_list' ? 'active' : '' ?>">
            <div class="<?= $current_page === 'voucher_list' ? 'bg-indigo-50 p-1.5 rounded-xl' : 'p-1.5' ?> transition-colors">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
            </div>
            <span class="text-[10px] font-bold tracking-wide">Ledger</span>
        </a>

        <a href="index.php?page=logout" class="mobile-nav-item flex-1 flex flex-col items-center gap-1 text-gray-400 hover:text-red-500 transition-colors">
            <div class="p-1.5 transition-colors">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 11-6 0v-1m6-10v1" /></svg>
            </div>
            <span class="text-[10px] font-bold tracking-wide">Logout</span>
        </a>
    </nav>
</div>
<?php endif; ?>

<main class="container max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-12 relative z-10">
    <?php display_flash_messages(); ?>
<!-- Note: Main tag remains open to wrap content, gets closed in footer.php -->

<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let lastId = 0; 
    
    // Notification Fetcher Logic
    function fetchNotifications() {
        <?php if (is_logged_in()): ?>
        fetch(`fetch_notifications.php?last_id=${lastId}`)
            .then(response => response.json())
            .then(data => {
                if (data.notifications && data.notifications.length > 0) {
                    data.notifications.forEach(notif => {
                        // Premium V3 Toast Notification
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
                        if (parseInt(notif.id) > lastId) {
                            lastId = parseInt(notif.id);
                        }
                    });
                }
                
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