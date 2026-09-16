<?php
// Compatibility tombstone: the customer directory was retired from V5.
// Existing database rows and historic voucher references remain untouched.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!is_logged_in()) {
    flash_message('error', 'Please log in to continue.');
    redirect('index.php?page=login');
}

flash_message('info', 'Customer management is retired in POS V5. Use manual New Sender and New Receiver fields when creating vouchers.');
redirect('index.php?page=dashboard');
