<?php
// templates/header.php

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

// Fetch unread notification count for the logged-in user
$unread_notifications = 0;
if (is_logged_in()) {
    global $connection;
    $user_id = $_SESSION['user_id']; // Define user_id for the query
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="icon" type="image/png" href="https://img.icons8.com/ios-filled/50/000000/shipping-container.png">
    <!-- Tailwind + custom assets -->
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
        }
        .action-card {
            padding: 1.25rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
        }
        .action-card:hover {
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            transform: translateY(-0.25rem);
            cursor: pointer;
        }
        .icon-box {
            padding: 0.75rem;
            border-radius: 0.5rem;
            flex-shrink: 0;
            transition: background-color 0.2s ease;
        }
        .icon {
            width: 1.5rem;
            height: 1.5rem;
            stroke-width: 1.5;
        }
        .text-gradient {
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            font-weight: 600;
            font-size: 1.125rem;
        }
        .notification-badge {
            position: absolute; top: -5px; right: -5px; padding: 2px 6px;
            border-radius: 9999px; background-color: #ef4444; color: white;
            font-size: 0.75rem; font-weight: bold; line-height: 1;
        }
        /* Add padding to the bottom of the main content on mobile to avoid overlap with the nav bar */
        body { padding-bottom: 80px; }
        @media (min-width: 768px) {
            body { padding-bottom: 0; }
        }

        /* Loader */
        .ship-loader {
            position: fixed;
            inset: 0;
            background: var(--color-bg);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 1;
            visibility: visible;
            transform: scale(1);
            transition: opacity 0.7s ease, visibility 0.7s ease, transform 0.7s ease;
        }
        .ship-loader.hidden {
            opacity: 0;
            visibility: hidden;
            transform: scale(1.05);
            pointer-events: none;
        }

        .mb-logo {
            font-size: 26px;
            font-weight: bold;
            color: var(--color-burgundy);
            margin-top: 15px;
            letter-spacing: 1px;
        }

        .progress-bar {
            width: 220px;
            height: 6px;
            background: #f0f0f0;
            border-radius: 3px;
            margin-top: 20px;
            overflow: hidden;
            box-shadow: inset 0 0 4px rgba(0,0,0,0.1);
        }
        .progress {
            height: 100%;
            background: linear-gradient(90deg, var(--color-burgundy), #a8324a, var(--color-burgundy));
            background-size: 200% 100%;
            animation: shimmer 2s linear infinite;
            width: 0%;
            transition: width 0.4s ease-out;
        }
        /* Button container */
  .button-container {
    margin-top: 2rem;
    text-align: center;
    border-top: 1px solid #ddd;
    padding-top: 1.5rem;
    display: flex;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
  }

  .button-container a.btn {
    display: inline-block;
    font-weight: 600;
    padding: 0.75rem 1.5rem;
    border-radius: 1rem;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    transition: background-color 0.2s ease;
    min-width: 140px;
    text-align: center;
    white-space: nowrap;
  }

  /* Responsive buttons for small screens */
  @media (max-width: 480px) {
    .button-container {
      flex-direction: column;
      gap: 0.75rem;
    }
    .button-container a.btn {
      width: 100%;
      min-width: auto;
    }
  }
        @keyframes shimmer {
            from { background-position: 200% 0; }
            to   { background-position: -200% 0; }
        }

        .ship-svg {
            animation: float 3s ease-in-out infinite;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.15));
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(-10px); }
        }
         /* Mobile Menu Animations */
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fadeInDown {
            animation: fadeInDown 0.3s ease-out forwards;
        }
        .rotate-90 {
            transform: rotate(90deg);
        }
        #menu-icon {
            transition: transform 0.3s ease;
        }
    </style>
</head>
<body class="bg-gray-100">

<!-- Loader -->
<div class="ship-loader" id="shipLoader">
    <svg class="ship-svg" width="200" height="100" viewBox="0 0 200 100" xmlns="http://www.w3.org/2000/svg">
        <rect x="20" y="70" width="160" height="20" rx="5" fill="#2c3e50" />
        <rect x="35" y="50" width="30" height="20" rx="3" fill="#3498db" stroke="#2980b9" stroke-width="2"/>
        <rect x="70" y="50" width="30" height="20" rx="3" fill="#e74c3c" stroke="#c0392b" stroke-width="2"/>
        <rect x="105" y="50" width="30" height="20" rx="3" fill="#2ecc71" stroke="#27ae60" stroke-width="2"/>
        <rect x="140" y="50" width="30" height="20" rx="3" fill="#f1c40f" stroke="#f39c12" stroke-width="2"/>
        <rect x="110" y="35" width="20" height="15" rx="2" fill="#bdc3c7" />
        <ellipse cx="100" cy="92" rx="80" ry="6" fill="#b3e0fc" opacity="0.5"/>
    </svg>
    <div class="mb-logo">MBLOGISTICS</div>
    <div class="progress-bar"><div class="progress" id="loaderProgress"></div></div>
</div>

<!-- Header -->

<!-- Header -->
<header class="backdrop-blur bg-white/90 shadow-md sticky top-0 z-50">
    <nav class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <a href="index.php?page=dashboard" class="text-2xl font-bold text-gray-800">MBLOGISTICS</a>
            <div class="hidden md:flex items-center space-x-4">
                <?php if (is_logged_in()): ?>
                    <div class="flex items-center space-x-4">
                         <a href="index.php?page=dashboard" class="px-3 py-2 text-gray-600 hover:text-gray-900 font-medium">Dashboard</a>
                        <?php if ($is_user_admin || $is_user_developer): ?>
                            <div class="relative group">
                                <button class="px-3 py-2 text-gray-600 hover:text-gray-900 font-medium flex items-center">
                                    Admin Tools
                                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </button>
                                <div class="absolute hidden group-hover:block bg-white shadow-lg rounded-lg py-2 mt-1 min-w-[200px] z-20">
                                    <a href="index.php?page=admin_dashboard" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Admin Dashboard</a>
                                    <?php if ($is_user_developer): ?>
                                    <a href="index.php?page=developer_dashboard" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Developer Dashboard</a>
                                    <?php endif; ?>
                                    <a href="index.php?page=branches" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Manage Branches</a>
                                </div>
                            </div>
                        <?php endif; ?>
                         <a href="index.php?page=voucher_list" class="px-3 py-2 text-gray-600 hover:text-gray-900 font-medium">Vouchers</a>
                         <a href="index.php?page=profit_loss" class="px-3 py-2 text-gray-600 hover:text-gray-900 font-medium">Profit/Loss</a>
                    </div>
                    <div class="flex items-center space-x-4 ml-4 border-l pl-4">
                        <a href="index.php?page=notifications" class="relative p-2 text-gray-600 hover:text-gray-900">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            <span id="notification-badge" class="notification-badge" style="<?= $unread_notifications > 0 ? '' : 'display: none;' ?>"><?= $unread_notifications ?></span>
                        </a>
                        <div>
                            <span class="text-gray-800 font-semibold"><?= htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
                            <span class="text-xs text-gray-500 block"><?= htmlspecialchars(get_user_branch_name() ?? 'No Branch'); ?></span>
                        </div>
                        <a href="index.php?page=logout" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 font-medium">Logout</a>
                    </div>
                <?php else: ?>
                    <a href="index.php?page=login" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-medium">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
</header>

<!-- Floating Mobile Nav -->
<?php if (is_logged_in()): ?>
<div class="md:hidden fixed bottom-0 left-0 right-0 bg-white shadow-[0_-2px_10px_rgba(0,0,0,0.1)] z-50 rounded-t-2xl">
    <nav class="flex justify-around items-center h-16 relative" aria-label="Mobile Navigation">
        <a href="index.php?page=dashboard" class="flex flex-col items-center text-gray-600 hover:text-blue-600 transition-colors" aria-current="<?= ($_GET['page'] ?? '') === 'dashboard' ? 'page' : 'false' ?>">
            <svg class="h-6 w-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            <span class="text-xs">Home</span>
        </a>

        

        <a href="index.php?page=notifications" class="relative flex flex-col items-center text-gray-600 hover:text-blue-600 transition-colors" aria-current="<?= ($_GET['page'] ?? '') === 'notifications' ? 'page' : 'false' ?>">
            <svg class="h-6 w-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <?php if ($unread_notifications > 0): ?>
            <span id="mobile-notification-badge" class="notification-badge absolute top-1 right-3 bg-red-600 text-white text-xs rounded-full px-1.5" aria-label="<?= $unread_notifications ?> unread notifications"><?= $unread_notifications ?></span>
            <?php endif; ?>
            <span class="text-xs">Alerts</span>
        </a>
        
        <?php if ($is_user_staff || $is_user_admin || $is_user_developer): ?>
        <a href="index.php?page=voucher_create"
           class="flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white rounded-full w-14 h-14 -mt-8 shadow-lg transition-colors"
           aria-current="<?= ($_GET['page'] ?? '') === 'voucher_create' ? 'page' : 'false' ?>"
           title="New Voucher">
            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M12 9v6m3-3H9" />
            </svg>
        </a>
        <?php endif; ?>

        <a href="index.php?page=voucher_list" class="flex flex-col items-center text-gray-600 hover:text-blue-600 transition-colors" aria-current="<?= ($_GET['page'] ?? '') === 'voucher_list' ? 'page' : 'false' ?>">
            <svg class="h-6 w-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
            </svg>
            <span class="text-xs">Vouchers</span>
        </a>

        <a href="index.php?page=logout" class="flex flex-col items-center text-gray-600 hover:text-blue-600 transition-colors" role="button" tabindex="0">
            <svg class="h-6 w-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 11-6 0v-1m6-10v1" />
            </svg>
            <span class="text-xs">Logout</span>
        </a>
    </nav>
</div>
<?php endif; ?>



<main class="container mx-auto px-6 py-8">
    <?php display_flash_messages(); ?>
    
</main>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let lastId = 0; 
    function fetchNotifications() {
        <?php if (is_logged_in()): ?>
        fetch(`fetch_notifications.php?last_id=${lastId}`)
            .then(response => response.json())
            .then(data => {
                if (data.notifications && data.notifications.length > 0) {
                    data.notifications.forEach(notif => {
                        Toastify({
                            text: notif.message,
                            duration: 10000,
                            close: true,
                            gravity: "top",
                            position: "right",
                            backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)",
                        }).showToast();
                        if (parseInt(notif.id) > lastId) {
                            lastId = parseInt(notif.id);
                        }
                    });
                }
                
                // Update both desktop and mobile badges
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
    setInterval(fetchNotifications, 10000);
    
    const loader = document.getElementById("shipLoader");
  const progress = document.getElementById("loaderProgress");

  let load = 0;
  const interval = setInterval(() => {
    load += 10;
    progress.style.width = load + "%";

    if (load >= 100) {
      clearInterval(interval);
      setTimeout(() => {
        loader.style.opacity = "0";
        setTimeout(() => loader.style.display = "none", 500);
      }, 300);
    }
  }, 300);
});
</script>
</body>
</html>
