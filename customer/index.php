<?php
// customer/index.php - Main router for the customer portal.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/functions.php';

// Basic routing logic
$page = $_GET['page'] ?? (is_customer_logged_in() ? 'dashboard' : 'login');

switch ($page) {
    case 'login':
        include 'customer_login.php';
        break;
    case 'register':
        include 'register.php';
        break;
    case 'dashboard':
        include 'customer_dashboard.php';
        break;
    case 'customer_voucher_view':
        include 'customer_voucher_view.php';
        break;
    case 'logout':
        session_destroy();
        redirect('index.php?page=login');
        break;
    default:
        include 'customer_login.php';
        break;
}
?>
