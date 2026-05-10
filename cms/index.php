<?php
// public_website/cms/index.php - Main router for the CMS.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/functions.php';

// Default page is dashboard if logged in, otherwise login
$page = $_GET['page'] ?? (is_cms_admin() ? 'dashboard' : 'login');

// Whitelist of allowed pages
$allowed_pages = ['login', 'dashboard', 'manage_routes', 'logout'];

if (!in_array($page, $allowed_pages)) {
    $page = 'login'; // Default to login if page is not allowed
}

// Protect pages that require login
if (!is_cms_admin() && in_array($page, ['dashboard', 'manage_routes'])) {
    redirect('index.php?page=login');
}

// Include the requested page
include "{$page}.php";
?>
