<?php
// pos/voucher_bulk_update.php - Page for filtering and bulk updating voucher statuses with branch permissions.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authentication & Authorization ---
// Allows Admin, Developer, and Staff users.
if (!is_logged_in() || (!is_admin() && !is_developer() && !is_staff())) {
    flash_message('error', 'You are not authorized to access this page.');
    redirect('index.php?page=dashboard');
}

global $connection;
$user_branch_id = get_user_branch_id();

// --- Define possible statuses and search columns ---
$possible_statuses = ['Pending', 'In Transit', 'Delivered', 'Received', 'Cancelled', 'Returned', 'Maintenance'];
$allowed_search_columns = ['voucher_code', 'sender_name', 'receiver_name', 'receiver_phone'];


// --- Handle POST request for bulk status update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $voucher_ids = $_POST['voucher_ids'] ?? [];
    $new_status = $_POST['new_status'] ?? '';

    if (empty($voucher_ids)) {
        flash_message('error', 'No vouchers were selected for update.');
    } elseif (!in_array($new_status, $possible_statuses)) {
        flash_message('error', 'An invalid status was selected.');
    } else {
        $ids_placeholder = implode(',', array_fill(0, count($voucher_ids), '?'));
        $stmt = mysqli_prepare($connection, "UPDATE vouchers SET status = ? WHERE id IN ($ids_placeholder)");

        $types = 's' . str_repeat('i', count($voucher_ids));
        mysqli_stmt_bind_param($stmt, $types, $new_status, ...$voucher_ids);

        if (mysqli_stmt_execute($stmt)) {
            $count = mysqli_stmt_affected_rows($stmt);
            flash_message('success', "$count vouchers were successfully updated to '" . htmlspecialchars($new_status) . "'.");
        } else {
            flash_message('error', 'Failed to update vouchers: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    }
    // Redirect back to the same page with filters preserved to see the result
    redirect('index.php?page=voucher_bulk_update'. http_build_query($_GET));
}


// --- Fetch Data for Filters and Display ---
$regions = [];
$vouchers = [];
$region_result = mysqli_query($connection, "SELECT id, region_name FROM regions ORDER BY region_name");
if ($region_result) {
    while ($row = mysqli_fetch_assoc($region_result)) {
        $regions[] = $row;
    }
}

// Get filter parameters from GET request
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$filter_origin_region_id = $_GET['origin_region_id'] ?? 'All';
$filter_destination_region_id = $_GET['destination_region_id'] ?? 'All';
$filter_status = $_GET['status'] ?? '';
$search_term = trim($_GET['search'] ?? '');
$search_column = $_GET['search_column'] ?? 'voucher_code';

// Build the main query
$query = "SELECT v.id, v.voucher_code, v.sender_name, v.receiver_name, v.status, v.created_at,
                 r_origin.region_name AS origin_region,
                 b_origin.branch_name AS origin_branch,
                 r_dest.region_name AS destination_region,
                 b_dest.branch_name AS destination_branch
          FROM vouchers v
          LEFT JOIN regions r_origin ON v.region_id = r_origin.id
          LEFT JOIN branches b_origin ON v.origin_branch_id = b_origin.id
          LEFT JOIN regions r_dest ON v.destination_region_id = r_dest.id
          LEFT JOIN branches b_dest ON v.destination_branch_id = b_dest.id";

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
if (!empty($start_date)) {
    $where_clauses[] = "DATE(v.created_at) >= ?";
    $bind_params .= 's';
    $bind_values[] = $start_date;
}
if (!empty($end_date)) {
    $where_clauses[] = "DATE(v.created_at) <= ?";
    $bind_params .= 's';
    $bind_values[] = $end_date;
}
if ($filter_origin_region_id !== 'All' && is_numeric($filter_origin_region_id)) {
    $where_clauses[] = "v.region_id = ?";
    $bind_params .= 'i';
    $bind_values[] = intval($filter_origin_region_id);
}
if ($filter_destination_region_id !== 'All' && is_numeric($filter_destination_region_id)) {
    $where_clauses[] = "v.destination_region_id = ?";
    $bind_params .= 'i';
    $bind_values[] = intval($filter_destination_region_id);
}
if (!empty($filter_status)) {
    $where_clauses[] = "v.status = ?";
    $bind_params .= 's';
    $bind_values[] = $filter_status;
}
if (!empty($search_term) && in_array($search_column, $allowed_search_columns)) {
    $where_clauses[] = "v.$search_column LIKE ?";
    $bind_params .= 's';
    $bind_values[] = '%' . $search_term . '%';
}

if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(' AND ', $where_clauses);
}
$query .= " ORDER BY v.created_at DESC";

$stmt = mysqli_prepare($connection, $query);
if ($stmt) {
    if (!empty($bind_params)) {
        mysqli_stmt_bind_param($stmt, $bind_params, ...$bind_values);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $vouchers[] = $row;
    }
    mysqli_stmt_close($stmt);
} else {
    flash_message('error', 'Error fetching vouchers: ' . mysqli_error($connection));
}

// --- Prepare Export Link ---
$export_params = $_GET;
unset($export_params['page']);
$export_query_string = http_build_query($export_params);
$export_url = 'index.php?page=export_vouchers&' . $export_query_string;

include_template('header', ['page' => 'voucher_bulk_update']);
?>

<div class="container mx-auto p-6">
    <h1 class="text-3xl font-bold mb-6">Voucher Bulk Status Update</h1>

<form action="index.php" method="GET" class="mb-6 bg-white p-6 rounded-2xl shadow-md border border-gray-100 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

    <input type="hidden" name="page" value="voucher_bulk_update">

    <!-- Start Date -->
    <div class="relative">
        <label for="start_date" class="form-label block mb-1 font-semibold text-gray-700">Start Date:</label>
        <div class="relative">
            <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-blue-500 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2v-7H3v7a2 2 0 002 2z" />
            </svg>
            <input type="date" id="start_date" name="start_date" class="form-input pl-10" value="<?= htmlspecialchars($start_date) ?>">
        </div>
    </div>

    <!-- End Date -->
    <div class="relative">
        <label for="end_date" class="form-label block mb-1 font-semibold text-gray-700">End Date:</label>
        <div class="relative">
            <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-blue-500 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2v-7H3v7a2 2 0 002 2z" />
            </svg>
            <input type="date" id="end_date" name="end_date" class="form-input pl-10" value="<?= htmlspecialchars($end_date) ?>">
        </div>
    </div>

    <!-- Origin Region -->
    <div>
        <label for="filter_origin_region_id" class="form-label block mb-1 font-semibold text-gray-700 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 1.104-.896 2-2 2s-2-.896-2-2 .896-2 2-2 2 .896 2 2zM19 11c0 1.104-.896 2-2 2s-2-.896-2-2 .896-2 2-2 2 .896 2 2z" />
            </svg>
            Origin Region:
        </label>
        <select id="filter_origin_region_id" name="origin_region_id" class="form-select">
            <option value="All">All</option>
            <?php foreach ($regions as $r): ?>
                <option value="<?= $r['id'] ?>" <?= ($filter_origin_region_id == $r['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($r['region_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Destination Region -->
    <div>
        <label for="filter_destination_region_id" class="form-label block mb-1 font-semibold text-gray-700 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 1.104-.896 2-2 2s-2-.896-2-2 .896-2 2-2 2 .896 2 2zM19 11c0 1.104-.896 2-2 2s-2-.896-2-2 .896-2 2-2 2 .896 2 2z" />
            </svg>
            Destination Region:
        </label>
        <select id="filter_destination_region_id" name="destination_region_id" class="form-select">
            <option value="All">All</option>
            <?php foreach ($regions as $r): ?>
                <option value="<?= $r['id'] ?>" <?= ($filter_destination_region_id == $r['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($r['region_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Status -->
    <div>
        <label for="filter_status" class="form-label block mb-1 font-semibold text-gray-700 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            Status:
        </label>
        <select id="filter_status" name="status" class="form-select">
            <option value="">All</option>
            <?php foreach ($possible_statuses as $s): ?>
                <option value="<?= $s ?>" <?= ($filter_status === $s) ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Search -->
    <div class="lg:col-span-2">
        <label for="search_term" class="form-label block mb-1 font-semibold text-gray-700 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            Search:
        </label>
        <div class="flex">
            <select name="search_column" class="form-select rounded-r-none border-r-0">
                <option value="voucher_code" <?= ($search_column === 'voucher_code') ? 'selected' : '' ?>>Voucher Code</option>
                <option value="sender_name" <?= ($search_column === 'sender_name') ? 'selected' : '' ?>>Sender</option>
                <option value="receiver_name" <?= ($search_column === 'receiver_name') ? 'selected' : '' ?>>Receiver</option>
            </select>
            <input type="text" name="search" placeholder="Enter search term..." class="form-input rounded-l-none pl-4" value="<?= htmlspecialchars($search_term) ?>">
        </div>
    </div>

    <!-- Buttons -->
    <div class="self-end flex items-center gap-2">
        <button type="submit" class="px-4 py-2 bg-cyan-600 text-white rounded-md hover:bg-cyan-700 font-medium">Filter</button>
        <a href="<?= htmlspecialchars($export_url) ?>" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 font-medium">Export to Excel</a>
    </div>

</form>



    <form action="index.php?page=voucher_bulk_update&<?= http_build_query($_GET) ?>" method="POST" id="bulk-update-form">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="mb-4 flex flex-wrap items-center gap-4 p-4 bg-yellow-50 rounded-lg shadow-inner">

  <label for="new_status" class="font-semibold flex items-center gap-2">
    Set selected to:
    <!--<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">-->
    <!--  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4" />-->
    <!--</svg>-->
  </label>

  <div class="relative w-auto">
    <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-yellow-600 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4" />
    </svg>
    <select id="new_status" name="new_status" class="form-select pl-10 pr-4 w-auto" required>
      <option value="">Select New Status</option>
      <?php foreach ($possible_statuses as $status): ?>
        <option value="<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <button type="submit" class="px-4 py-2 bg-yellow-500 text-white rounded-md hover:bg-yellow-700 font-medium">Update Selected</button>
</div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="table-header w-4"><input type="checkbox" id="select-all-vouchers"></th>
                            <th class="table-header">Voucher Code</th>
                            <th class="table-header">Sender/Receiver</th>
                            <th class="table-header">Origin/Destination</th>
                            <th class="table-header">Status</th>
                            <th class="table-header">Date</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($vouchers)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">No vouchers found matching your criteria.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($vouchers as $voucher): ?>
                                <tr>
                                    <td data-label="Select" class="table-cell"><input type="checkbox" name="voucher_ids[]" value="<?= $voucher['id'] ?>" class="voucher-checkbox"></td>
                                    <td data-label="Voucher Code" class="table-cell font-mono text-indigo-600"><?= htmlspecialchars($voucher['voucher_code']) ?></td>
                                    <td data-label="Sender/" class="table-cell">
                                        <?= htmlspecialchars($voucher['sender_name']) ?><br>
                                        <span class="text-sm text-gray-500">&rarr; <?= htmlspecialchars($voucher['receiver_name']) ?></span>
                                    </td>
                                    <td data-label="Origin/" class="table-cell">
                                        <?= htmlspecialchars($voucher['origin_region'] ?? 'N/A') ?> / <?= htmlspecialchars($voucher['origin_branch'] ?? 'N/A') ?><br>
                                        <span class="text-sm text-gray-500">&rarr; <?= htmlspecialchars($voucher['destination_region'] ?? 'N/A') ?> / <?= htmlspecialchars($voucher['destination_branch'] ?? 'N/A') ?></span>
                                    </td>
                                    <td data-label="Status" class="table-cell">
                                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $voucher['status'])) ?>"><?= htmlspecialchars($voucher['status']) ?></span>
                                    </td>
                                    <td data-label="Date" class="table-cell"><?= date('Y-m-d', strtotime($voucher['created_at'])) ?></td>
                                </tr>

                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAllCheckbox = document.getElementById('select-all-vouchers');
        const voucherCheckboxes = document.querySelectorAll('.voucher-checkbox');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                voucherCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });
        }
    });
</script>

<?php include_template('footer'); ?>