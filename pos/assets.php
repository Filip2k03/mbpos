<?php
// assets.php
// Manages dynamic loading of CSS and JavaScript assets.

function load_assets($page_name) {
    // Shared CSS/Tailwind are loaded once by templates/header.php.
    // Keep this helper page-agnostic so legacy screens do not duplicate assets.
    $asset_version = defined('APP_VERSION') ? rawurlencode(APP_VERSION) : '5';
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">';
    echo '<script src="assets/js/main.js?v=' . $asset_version . '" defer></script>';

    // Page-specific scripts (example)
    // if ($page_name === 'voucher_create') {
    //     echo '<script src="assets/js/voucher_create_specific.js"></script>';
    // }
}
?>
