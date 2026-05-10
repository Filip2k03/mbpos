<?php
// pos/stock_bulk_update.php - Processes bulk status updates for stock items.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authentication & Authorization ---
if (!is_logged_in() || (!is_admin() && !is_developer() && !is_myanmar_user() && !is_malay_user())) {
    flash_message('error', 'You are not authorized to perform this action.');
    redirect('index.php?page=stock_list');
}

global $connection;

// --- FIX: Add check for POST request method ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash_message('error', 'Invalid request method.');
    redirect('index.php?page=stock_list');
}

// --- Handle POST request for bulk status update ---
$stock_ids = $_POST['stock_ids'] ?? [];
$new_status = $_POST['new_status'] ?? '';
$possible_statuses = ['Pending', 'In Transit', 'Delivered', 'Received', 'Maintenance'];


if (empty($stock_ids)) {
    flash_message('warning', 'No stock items were selected for update.');
    redirect('index.php?page=stock_list');
}

if (empty($new_status) || !in_array($new_status, $possible_statuses)) {
    flash_message('error', 'An invalid status was selected.');
    redirect('index.php?page=stock_list');
}

// Get the corresponding voucher IDs from the selected stock IDs
$ids_placeholder = implode(',', array_fill(0, count($stock_ids), '?'));
$stmt_vouchers = mysqli_prepare($connection, "SELECT voucher_id FROM stock WHERE id IN ($ids_placeholder)");

$types = str_repeat('i', count($stock_ids));
mysqli_stmt_bind_param($stmt_vouchers, $types, ...$stock_ids);
mysqli_stmt_execute($stmt_vouchers);
$result_vouchers = mysqli_stmt_get_result($stmt_vouchers);

$voucher_ids_to_update = [];
while ($row = mysqli_fetch_assoc($result_vouchers)) {
    $voucher_ids_to_update[] = $row['voucher_id'];
}
mysqli_stmt_close($stmt_vouchers);

if (!empty($voucher_ids_to_update)) {
    // Now update the vouchers table
    $voucher_ids_placeholder = implode(',', array_fill(0, count($voucher_ids_to_update), '?'));
    $stmt_update = mysqli_prepare($connection, "UPDATE vouchers SET status = ? WHERE id IN ($voucher_ids_placeholder)");

    $update_types = 's' . str_repeat('i', count($voucher_ids_to_update));
    mysqli_stmt_bind_param($stmt_update, $update_types, $new_status, ...$voucher_ids_to_update);

    if (mysqli_stmt_execute($stmt_update)) {
        $count = mysqli_stmt_affected_rows($stmt_update);
        flash_message('success', "$count stock items have been successfully updated to '$new_status'.");
    } else {
        flash_message('error', 'An error occurred while updating stock items: ' . mysqli_stmt_error($stmt_update));
    }
    mysqli_stmt_close($stmt_update);
} else {
    flash_message('warning', 'Could not find corresponding vouchers for the selected stock items.');
}

redirect('index.php?page=stock_list');
?>

