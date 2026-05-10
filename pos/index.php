<?php
// index.php - Main router/controller for the application.

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once 'config.php';
require_once 'includes/functions.php';

// --- Maintenance Mode Check ---
$maintenance_mode = 'off';
// Check if settings table exists to avoid errors on fresh install
$table_check = mysqli_query($connection, "SHOW TABLES LIKE 'settings'");
if ($table_check && mysqli_num_rows($table_check) > 0) {
    $result = mysqli_query($connection, "SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode' LIMIT 1");
    if ($result && mysqli_num_rows($result) > 0) {
        $maintenance_mode = mysqli_fetch_assoc($result)['setting_value'];
    }
}

$page = $_GET['page'] ?? 'dashboard'; // Default page

if ($maintenance_mode === 'on' && !is_admin() && !is_developer()) {
    // Allow access to login, logout, and the maintenance page itself
    if ($page !== 'login' && $page !== 'logout' && $page !== 'maintenance') {
        // Redirect all other requests to the maintenance page
        require_once 'maintenance.php';
        exit();
    }
}


// Determine which page to load based on the 'page' query parameter
switch ($page) {
    case 'login':
        include 'login.php';
        break;
    case 'logout':
        include 'logout.php';
        break;
    case 'dashboard':
        include 'dashboard.php';
        break;
    case 'dashboard_actions':
        include 'dashboard_actions.php';
        break;
    case 'ajax_db_status':
        include 'ajax_db_status.php';
        break;
    case 'clear_log':
        include 'clear_log.php';
        break;
    case 'error_log_viewer':
        include 'error_log_viewer.php';
        break;
    case 'admin_dashboard':
        include 'admin_dashboard.php';
        break;
     case 'developer_dashboard':
        include 'developer_dashboard.php';
        break;
    case 'voucher_list':
        include 'voucher_list.php';
        break;
    case 'voucher_bulk_update':
        include 'voucher_bulk_update.php';
        break;
    case 'voucher_create':
        include 'voucher_create.php';
        break;
    case 'voucher_view':
        include 'voucher_view.php';
        break;
    case 'export_vouchers':
        include 'export_vouchers.php';
        break;
    case 'stock_list':
        include 'stock_list.php';
        break;
        case 'status_bulk_update':
        include 'status_bulk_update.php';
        break;
    case 'expenses':
        include 'expenses.php';
        break;
    case 'other_income':
        include 'other_income.php';
        break;
    case 'profit_loss':
        include 'profit_loss.php';
        break;
    case 'register':
        include 'register.php';
        break;
    case 'branches':
        include 'branches.php';
        break;
    case 'currencies':
        include 'currencies.php';
        break;
    case 'delivery_types':
        include 'delivery_types.php';
        break;
    case 'item_types':
        include 'item_types.php';
        break;
   case 'notifications':
        include 'notifications.php';
        break;
    case 'customer_voucher_view':
        include 'customer_voucher_view.php';
        break;
    case 'customer_register':
        include 'customer_register.php';
        break;
    case 'customer_list':
        include 'customer_list.php';
        break;
    case 'fetch_notifications':
        include 'fetch_notifications.php';
        break;
    case 'maintenance':
        include 'maintenance.php';
        break;
    case 'ajax_search_customers':
        include 'ajax_search_customers.php';
        break;
    default:
        // Optional: A 404 page
        // header("HTTP/1.0 404 Not Found");
        // include '404.php';
        // Or just redirect to the dashboard
        include 'dashboard.php';
        break;
}