<?php
// pos/voucher_list.php - Displays a paginated list of all vouchers with filtering and branch-based permissions.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authentication Check ---
if (!is_logged_in()) {
    flash_message('error', 'Please log in to view the voucher list.');
    redirect('index.php?page=login');
}

global $connection;
$user_branch_id = get_user_branch_id();

// --- Fetch Data for Filters ---
$regions = [];
$region_result = mysqli_query($connection, "SELECT id, region_name FROM regions ORDER BY region_name");
if ($region_result) while ($row = mysqli_fetch_assoc($region_result)) $regions[] = $row;
$possible_statuses = ['Pending', 'In Transit', 'Delivered', 'Received', 'Cancelled', 'Returned', 'Maintenance'];
$allowed_search_columns = ['voucher_code', 'sender_name', 'receiver_name', 'receiver_phone'];

// --- Get Filter Parameters from GET request ---
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$filter_origin_region_id = $_GET['origin_region_id'] ?? 'All';
$filter_destination_region_id = $_GET['destination_region_id'] ?? 'All';
$filter_status = $_GET['status'] ?? '';
$search_term = trim($_GET['search'] ?? '');
$search_column = $_GET['search_column'] ?? 'voucher_code';


// --- Pagination Setup ---
$limit = 30; // Vouchers per page
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($page - 1) * $limit;

// --- Build Query with Filters ---
$base_query = "FROM vouchers v
               LEFT JOIN regions r_origin ON v.region_id = r_origin.id
               LEFT JOIN regions r_dest ON v.destination_region_id = r_dest.id
               LEFT JOIN branches b_origin ON v.origin_branch_id = b_origin.id
               LEFT JOIN branches b_dest ON v.destination_branch_id = b_dest.id
               LEFT JOIN users u ON v.created_by_user_id = u.id
               LEFT JOIN branches b_user ON u.branch_id = b_user.id";

$where_clauses = [];
$bind_params = '';
$bind_values = [];

// --- Apply Branch and Role-Based Security Filter ---
if (is_staff() && $user_branch_id) {
    $where_clauses[] = "(v.origin_branch_id = ? OR (v.destination_branch_id = ? AND v.status != 'Pending'))";
    $bind_params .= 'ii';
    $bind_values[] = $user_branch_id;
    $bind_values[] = $user_branch_id;
}

// --- Apply User-Selected Filters ---
if (!empty($start_date)) { $where_clauses[] = "DATE(v.created_at) >= ?"; $bind_params .= 's'; $bind_values[] = $start_date; }
if (!empty($end_date)) { $where_clauses[] = "DATE(v.created_at) <= ?"; $bind_params .= 's'; $bind_values[] = $end_date; }
if ($filter_origin_region_id !== 'All' && is_numeric($filter_origin_region_id)) { $where_clauses[] = "v.region_id = ?"; $bind_params .= 'i'; $bind_values[] = intval($filter_origin_region_id); }
if ($filter_destination_region_id !== 'All' && is_numeric($filter_destination_region_id)) { $where_clauses[] = "v.destination_region_id = ?"; $bind_params .= 'i'; $bind_values[] = intval($filter_destination_region_id); }
if (!empty($filter_status)) { $where_clauses[] = "v.status = ?"; $bind_params .= 's'; $bind_values[] = $filter_status; }
if (!empty($search_term) && in_array($search_column, $allowed_search_columns)) { $where_clauses[] = "v.$search_column LIKE ?"; $bind_params .= 's'; $bind_values[] = '%' . $search_term . '%'; }

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(' AND ', $where_clauses);
}

// --- Get Total Count for Pagination ---
$total_vouchers = 0;
$count_query = "SELECT COUNT(v.id) " . $base_query . $where_sql;
$stmt_count = mysqli_prepare($connection, $count_query);
if ($stmt_count) {
    if (!empty($bind_params)) {
        mysqli_stmt_bind_param($stmt_count, $bind_params, ...$bind_values);
    }
    mysqli_stmt_execute($stmt_count);
    $result_count = mysqli_stmt_get_result($stmt_count);
    $total_vouchers = mysqli_fetch_row($result_count)[0];
    mysqli_stmt_close($stmt_count);
}
$total_pages = ceil($total_vouchers / $limit);


// --- Fetch Vouchers for the Current Page ---
$vouchers = [];
$select_fields = "SELECT 
                    v.id, v.voucher_code, v.sender_name, v.receiver_name, 
                    v.total_amount, v.status, v.created_at, v.currency,
                    r_origin.region_name AS origin_region,
                    r_dest.region_name AS destination_region,
                    b_origin.branch_name as origin_branch,
                    b_dest.branch_name as destination_branch,
                    u.username as created_by_username,
                    b_user.branch_name as creator_branch_name ";

$query = $select_fields . $base_query . $where_sql . " ORDER BY v.created_at DESC LIMIT ? OFFSET ?";
$page_bind_params = $bind_params . 'ii';
$page_bind_values = array_merge($bind_values, [$limit, $offset]);

$stmt = mysqli_prepare($connection, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, $page_bind_params, ...$page_bind_values);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $vouchers[] = $row;
        }
    } else {
        flash_message('error', 'Failed to fetch vouchers: ' . mysqli_error($connection));
    }
    mysqli_stmt_close($stmt);
} else {
    flash_message('error', 'Failed to prepare statement for vouchers: ' . mysqli_error($connection));
}

// Prepare pagination links with existing filters
$pagination_params = $_GET;
unset($pagination_params['p']); // Remove old page number
$pagination_query_string = http_build_query($pagination_params);


include_template('header', ['page' => 'voucher_list']);
?>

<div class="container mx-auto p-6">
    <div class="flex flex-col md:flex-row justify-between md:items-center mb-6 gap-4">
        <h1 class="text-3xl font-bold">Voucher List</h1>
        <?php if (is_staff() || is_admin() || is_developer()): ?>
            <a href="index.php?page=voucher_create" class="btn">Create New Voucher</a>
        <?php endif; ?>
    </div>

    <!-- Advanced Filter Form -->
    <form action="index.php" method="GET" class="mb-6 bg-white p-6 rounded-2xl shadow-md border border-gray-100 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <input type="hidden" name="page" value="voucher_list">
        <div><label for="start_date" class="form-label">Start Date:</label><input type="date" id="start_date" name="start_date" class="form-input" value="<?= htmlspecialchars($start_date) ?>"></div>
        <div><label for="end_date" class="form-label">End Date:</label><input type="date" id="end_date" name="end_date" class="form-input" value="<?= htmlspecialchars($end_date) ?>"></div>
        <div><label for="filter_origin_region_id" class="form-label">Origin Region:</label><select id="filter_origin_region_id" name="origin_region_id" class="form-select"><option value="All">All</option><?php foreach ($regions as $r): ?><option value="<?= $r['id'] ?>" <?= ($filter_origin_region_id == $r['id']) ? 'selected' : '' ?>><?= htmlspecialchars($r['region_name']) ?></option><?php endforeach; ?></select></div>
        <div><label for="filter_destination_region_id" class="form-label">Destination Region:</label><select id="filter_destination_region_id" name="destination_region_id" class="form-select"><option value="All">All</option><?php foreach ($regions as $r): ?><option value="<?= $r['id'] ?>" <?= ($filter_destination_region_id == $r['id']) ? 'selected' : '' ?>><?= htmlspecialchars($r['region_name']) ?></option><?php endforeach; ?></select></div>
        <div><label for="filter_status" class="form-label">Status:</label><select id="filter_status" name="status" class="form-select"><option value="">All</option><?php foreach ($possible_statuses as $s): ?><option value="<?= $s ?>" <?= ($filter_status === $s) ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
        <div class="lg:col-span-2"><label for="search_term" class="form-label">Search:</label><div class="flex"><select name="search_column" class="form-select rounded-r-none border-r-0"><option value="voucher_code" <?= ($search_column === 'voucher_code') ? 'selected' : '' ?>>Voucher Code</option><option value="sender_name" <?= ($search_column === 'sender_name') ? 'selected' : '' ?>>Sender</option><option value="receiver_name" <?= ($search_column === 'receiver_name') ? 'selected' : '' ?>>Receiver</option></select><input type="text" name="search" placeholder="Enter search term..." class="form-input rounded-l-none" value="<?= htmlspecialchars($search_term) ?>"></div></div>
        <div class="self-end"><button type="submit" class="btn w-full hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">Filter</button></div>
    </form>


    <div class="bg-white p-6 rounded-lg shadow-md overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="table-header">Voucher Code</th>
                    <th class="table-header">Sender</th>
                    <th class="table-header">Receiver</th>
                    <th class="table-header">Origin</th>
                    <th class="table-header">Destination</th>
                    <th class="table-header">Amount</th>
                    <th class="table-header">Status</th>
                    <th class="table-header">Created By</th>
                    <th class="table-header">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($vouchers)): ?>
                    <tr><td colspan="9" class="text-center py-4">No vouchers found matching your criteria.</td></tr>
                <?php else: ?>
                    <?php foreach ($vouchers as $voucher): ?>
                        <tr>
                            <td data-label="Voucher Code::" class="table-cell font-mono text-indigo-600"><?= htmlspecialchars($voucher['voucher_code']) ?></td>
                            <td data-label="Sender::" class="table-cell"><?= htmlspecialchars($voucher['sender_name']) ?></td>
                            <td data-label="Receiver::" class="table-cell"><?= htmlspecialchars($voucher['receiver_name']) ?></td>
                            <td data-label="Origin::" class="table-cell"><?= htmlspecialchars($voucher['origin_region'] ?? 'N/A') ?> / <?= htmlspecialchars($voucher['origin_branch'] ?? 'N/A') ?></td>
                            <td data-label="Destination::" class="table-cell"><?= htmlspecialchars($voucher['destination_region'] ?? 'N/A') ?> / <?= htmlspecialchars($voucher['destination_branch'] ?? 'N/A') ?></td>
                            <td data-label="Amount::" class="table-cell"><?= htmlspecialchars($voucher['currency']) ?> <?= number_format($voucher['total_amount'], 2) ?></td>
                            <td data-label="Status::" class="table-cell"><span class="status-badge status-<?= strtolower(str_replace(' ', '-', $voucher['status'])) ?>"><?= htmlspecialchars($voucher['status']) ?></span></td>
                            <td data-label="Created By::" class="table-cell"><?= htmlspecialchars($voucher['created_by_username'] ?? 'N/A') ?><br><span class="text-sm text-gray-500"><?= htmlspecialchars($voucher['creator_branch_name'] ?? 'No Branch') ?></span></td>
                            <td data-label="Actions::" class="table-cell"><a href="index.php?page=voucher_view&id=<?= $voucher['id'] ?>" class="text-indigo-600 hover:text-indigo-900">View</a></td>
                        </tr>

                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Links -->
    <div class="mt-6 flex justify-between items-center">
        <div>
            <?php if ($page > 1): ?>
                <a href="index.php?page=voucher_list&p=<?= $page - 1 ?>&<?= $pagination_query_string ?>" class="btn-secondary">&larr; Previous</a>
            <?php endif; ?>
        </div>
        <div class="text-gray-600">
            Page <?= $page ?> of <?= $total_pages ?>
        </div>
        <div>
            <?php if ($page < $total_pages): ?>
                <a href="index.php?page=voucher_list&p=<?= $page + 1 ?>&<?= $pagination_query_string ?>" class="btn-secondary">Next &rarr;</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_template('footer'); ?>

