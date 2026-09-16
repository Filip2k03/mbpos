<?php
// pos/export_vouchers.php - Exports filtered voucher data to a valid CSV file for Excel.

require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/voucher_query.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authentication & Authorization ---
if (!is_logged_in() || (!is_admin() && !is_developer() && !is_staff())) {
    flash_message('error', 'You are not authorized to export this data.');
    redirect('index.php?page=dashboard');
}

global $connection;
$user_branch_id = get_user_branch_id();

// Get filter parameters from GET request
$possible_statuses = ['Pending', 'In Transit', 'Delivered', 'Received', 'Cancelled', 'Returned', 'Maintenance'];
$filters = mbpos_normalize_voucher_filters($_GET, $possible_statuses);

// Build the main query
$query = "SELECT v.id, v.voucher_code, v.sender_name, v.receiver_name, v.status, v.created_at, v.total_amount, v.currency, v.weight_kg, v.sender_phone, v.receiver_phone,
                 r_origin.region_name AS origin_region,
                 b_origin.branch_name AS origin_branch,
                 r_dest.region_name AS destination_region,
                 b_dest.branch_name AS destination_branch,
                 u.username as created_by_username,
                 b_user.branch_name as creator_branch_name
          FROM vouchers v
          LEFT JOIN regions r_origin ON v.region_id = r_origin.id
          LEFT JOIN branches b_origin ON v.origin_branch_id = b_origin.id
          LEFT JOIN regions r_dest ON v.destination_region_id = r_dest.id
          LEFT JOIN branches b_dest ON v.destination_branch_id = b_dest.id
          LEFT JOIN users u ON v.created_by_user_id = u.id
          LEFT JOIN branches b_user ON u.branch_id = b_user.id";

$queryFilter = mbpos_build_voucher_filter_sql($filters, (int)$user_branch_id, is_staff());
$bind_params = $queryFilter['types'];
$bind_values = $queryFilter['values'];
$query .= $queryFilter['where_sql'] . ' ORDER BY v.created_at DESC, v.id DESC';

$stmt = mysqli_prepare($connection, $query);
if ($stmt) {
    if (!empty($bind_params)) { mysqli_stmt_bind_param($stmt, $bind_params, ...$bind_values); }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    // --- Generate CSV Output ---
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="vouchers_export_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM to ensure Excel properly handles special characters
    fputs($output, "\xEF\xBB\xBF");

    // Add header row
    fputcsv($output, ['Voucher Code', 'Sender', 'Sender Phone', 'Receiver', 'Receiver Phone', 'Origin', 'Destination', 'Status', 'Total Amount', 'Weight (kg)', 'Date', 'Created By']);

    // Add data rows
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, [
            $row['voucher_code'],
            $row['sender_name'],
            $row['sender_phone'],
            $row['receiver_name'],
            $row['receiver_phone'],
            $row['origin_region'] . ' / ' . $row['origin_branch'],
            $row['destination_region'] . ' / ' . $row['destination_branch'],
            $row['status'],
            $row['currency'] . ' ' . number_format($row['total_amount'], 2),
            $row['weight_kg'],
            $row['created_at'],
            $row['created_by_username'] . ' (' . $row['creator_branch_name'] . ')'
        ]);
    }

    fclose($output);
    mysqli_stmt_close($stmt);
    exit();

} else {
    error_log('MBPOS voucher export prepare failed: ' . mysqli_error($connection));
    flash_message('error', 'Unable to prepare the export right now. Please try again.');
    redirect('index.php?page=voucher_list');
}
?>
