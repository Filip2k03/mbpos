<?php
// index.php - Main router/controller for the application.

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once 'config.php';
// require_once 'includes/functions.php';

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

$page = $_GET['page'] ?? ''; // Default page

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
    case 'customer_voucher_view':
        include 'customer_voucher_view.php';
        break;
    default:
        // Optional: A 404 page
        header("HTTP/1.0 404 Not Found");
        include '404.php';
        // Or just redirect to the dashboard
        // include 'dashboard.php';
        break;
}