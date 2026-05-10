<?php
// public_website/cms/templates/header.php
$page_title = $title ?? 'Website CMS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../pos/assets/css/style.css"> <!-- Re-using main style for consistency -->
</head>
<body class="bg-gray-100">
    <header class="bg-white shadow-md">
        <nav class="container mx-auto px-6 py-3 flex justify-between items-center">
             <a href="index.php?page=dashboard" class="text-2xl font-bold text-gray-800">Website CMS</a>
             <?php if(is_cms_admin()): ?>
                <div class="flex items-center space-x-4">
                    <a href="index.php?page=dashboard" class="nav-link">Dashboard</a>
                    <a href="index.php?page=manage_routes" class="nav-link">Manage Routes</a>
                    <span class="text-gray-400">|</span>
                    <span class="text-gray-700">Welcome, <?= htmlspecialchars($_SESSION['cms_username']) ?>!</span>
                    <a href="index.php?page=logout" class="btn-secondary">Logout</a>
                </div>
             <?php endif; ?>
        </nav>
    </header>
    <main class="container mx-auto p-6">
        <?php display_cms_flash_messages(); ?>
