<?php
// pos/export_vouchers.php - Exports filtered voucher data to a valid CSV file for Excel.

require_once 'config.php';
require_once 'includes/functions.php';

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

// --- Define search columns ---
$allowed_search_columns = ['voucher_code', 'sender_name', 'receiver_name', 'receiver_phone'];

// Get filter parameters from GET request
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$filter_origin_region_id = $_GET['origin_region_id'] ?? 'All';
$filter_destination_region_id = $_GET['destination_region_id'] ?? 'All';
$filter_status = $_GET['status'] ?? '';
$search_term = trim($_GET['search'] ?? '');
$search_column = $_GET['search_column'] ?? 'voucher_code';

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

$where_clauses = [];
$bind_params = '';
$bind_values = [];

// Apply Branch and Role-Based Security Filter
if (is_staff() && $user_branch_id) {
    $where_clauses[] = "(v.origin_branch_id = ? OR (v.destination_branch_id = ? AND v.status != 'Pending'))";
    $bind_params .= 'ii';
    $bind_values[] = $user_branch_id;
    $bind_values[] = $user_branch_id;
}

// Apply User-Selected Filters
if (!empty($start_date)) { $where_clauses[] = "DATE(v.created_at) >= ?"; $bind_params .= 's'; $bind_values[] = $start_date; }
if (!empty($end_date)) { $where_clauses[] = "DATE(v.created_at) <= ?"; $bind_params .= 's'; $bind_values[] = $end_date; }
if ($filter_origin_region_id !== 'All' && is_numeric($filter_origin_region_id)) { $where_clauses[] = "v.region_id = ?"; $bind_params .= 'i'; $bind_values[] = intval($filter_origin_region_id); }
if ($filter_destination_region_id !== 'All' && is_numeric($filter_destination_region_id)) { $where_clauses[] = "v.destination_region_id = ?"; $bind_params .= 'i'; $bind_values[] = intval($filter_destination_region_id); }
if (!empty($filter_status)) { $where_clauses[] = "v.status = ?"; $bind_params .= 's'; $bind_values[] = $filter_status; }
if (!empty($search_term) && in_array($search_column, $allowed_search_columns)) { $where_clauses[] = "v.$search_column LIKE ?"; $bind_params .= 's'; $bind_values[] = '%' . $search_term . '%'; }

if(!empty($where_clauses)){ $query .= " WHERE " . implode(' AND ', $where_clauses); }
$query .= " ORDER BY v.created_at DESC";

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
    fputcsv($output, ['Voucher Code', 'Sender', 'Sender_Phone', 'Receiver', 'Receiver Phone', 'Origin', 'Destination', 'total_amount', 'weight_kg', 'Date', 'Created By']);

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
            $row['currency'] . ' ' . number_format($row['total_amount'], 2),
            $row['weight_kg'],
            // $row['status'],
            $row['created_at'],
            $row['created_by_username'] . ' (' . $row['creator_branch_name'] . ')'
        ]);
    }

    fclose($output);
    mysqli_stmt_close($stmt);
    exit();

} else {
    flash_message('error', 'Error preparing data for export: ' . mysqli_error($connection));
    redirect('index.php?page=voucher_bulk_update');
}
?>

