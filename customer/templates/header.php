<?php
// customer/templates/header.php
$page_title = $title ?? 'Customer Portal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css"> <!-- Re-using main style for consistency -->
    <style>
        /* Add padding to the bottom of the main content on mobile to avoid overlap with the nav bar */
        body { padding-bottom: 80px; }
        @media (min-width: 768px) {
            body { padding-bottom: 0; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <header class="bg-white shadow-md hidden md:block">
        <nav class="container mx-auto px-6 py-3 flex justify-between items-center">
             <a href="index.php?page=dashboard" class="text-2xl font-bold text-gray-800">MBLOGISTICS</a>
             <?php if(is_customer_logged_in()): ?>
                <div>
                    <span class="text-gray-700 mr-4">Welcome, <?= htmlspecialchars($_SESSION['customer_username']) ?>!</span>
                    <a href="index.php?page=logout" class="btn-secondary">Logout</a>
                </div>
             <?php endif; ?>
        </nav>
    </header>
    <main class="container mx-auto p-4 md:p-6">
        <?php display_customer_flash_messages(); ?>

