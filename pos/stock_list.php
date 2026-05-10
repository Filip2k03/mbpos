<?php
// pos/stock_list.php - Displays a filterable list of stock items and handles bulk status updates.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authentication & Authorization ---
if (!is_logged_in()) {
    flash_message('error', 'Please log in to access this page.');
    redirect('index.php?page=login');
}

global $connection;

// --- Define possible statuses and search columns ---
$possible_statuses = ['Pending', 'In Transit', 'Delivered', 'Received', 'Maintenance'];
$allowed_search_columns = ['voucher_code', 'sender_name', 'receiver_name', 'receiver_phone'];

// --- Get user role and region for permissions ---
$user_id = $_SESSION['user_id'] ?? null;
$user_type = $_SESSION['user_type'] ?? null;
$user_region_id = null;

if ($user_id && ($user_type === 'Myanmar' || $user_type === 'Malay')) {
    $stmt_user = mysqli_prepare($connection, "SELECT region_id FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt_user, 'i', $user_id);
    mysqli_stmt_execute($stmt_user);
    $result_user = mysqli_stmt_get_result($stmt_user);
    if ($row = mysqli_fetch_assoc($result_user)) {
        $user_region_id = $row['region_id'];
    }
    mysqli_stmt_close($stmt_user);
}

// --- Handle POST request for bulk status update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stock_ids = $_POST['stock_ids'] ?? [];
    $new_status = $_POST['new_status'] ?? '';

    if (empty($stock_ids)) {
        flash_message('error', 'No stock items were selected for update.');
    } elseif (!in_array($new_status, $possible_statuses)) {
        flash_message('error', 'An invalid status was selected for the bulk update.');
    } else {
        // Get the corresponding voucher IDs from the selected stock IDs
        $ids_placeholder = implode(',', array_fill(0, count($stock_ids), '?'));
        $stmt_vouchers = mysqli_prepare($connection, "SELECT id, voucher_id FROM stock WHERE id IN ($ids_placeholder)");
        $types = str_repeat('i', count($stock_ids));
        mysqli_stmt_bind_param($stmt_vouchers, $types, ...$stock_ids);
        mysqli_stmt_execute($stmt_vouchers);
        $result_vouchers = mysqli_stmt_get_result($stmt_vouchers);
        
        $voucher_ids_to_update = [];
        while($row = mysqli_fetch_assoc($result_vouchers)){
            $voucher_ids_to_update[] = $row['voucher_id'];
        }
        mysqli_stmt_close($stmt_vouchers);

        if(!empty($voucher_ids_to_update)) {
            // Now update the vouchers table
            $voucher_ids_placeholder = implode(',', array_fill(0, count($voucher_ids_to_update), '?'));
            $stmt_update = mysqli_prepare($connection, "UPDATE vouchers SET status = ? WHERE id IN ($voucher_ids_placeholder)");
            
            $update_types = 's' . str_repeat('i', count($voucher_ids_to_update));
            mysqli_stmt_bind_param($stmt_update, $update_types, $new_status, ...$voucher_ids_to_update);

            if (mysqli_stmt_execute($stmt_update)) {
                flash_message('success', count($voucher_ids_to_update) . ' items updated successfully to "' . htmlspecialchars($new_status) . '".');
            } else {
                flash_message('error', 'Failed to update voucher statuses: ' . mysqli_stmt_error($stmt_update));
            }
            mysqli_stmt_close($stmt_update);
        }
    }
    // Redirect to the same page to show updated list and messages
    redirect('index.php?page=stock_list&' . http_build_query($_GET));
}


// --- Fetch Data for Display ---
$stock_items = [];
$regions = [];

// Fetch regions for the filter dropdown
$region_result = mysqli_query($connection, "SELECT id, region_name FROM regions ORDER BY region_name");
if ($region_result) {
    while ($row = mysqli_fetch_assoc($region_result)) {
        $regions[] = $row;
    }
}

// Get filter parameters from GET request
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$filter_region_id = $_GET['region_id'] ?? 'All';
$filter_status = $_GET['status'] ?? '';
$search_term = trim($_GET['search'] ?? '');
$search_column = $_GET['search_column'] ?? 'voucher_code';

// Build the main query
$query = "SELECT s.id, v.voucher_code, v.sender_name, v.receiver_name, v.receiver_phone, v.status, s.updated_at, r_origin.region_name AS origin_region
          FROM stock s
          JOIN vouchers v ON s.voucher_id = v.id
          LEFT JOIN regions r_origin ON v.region_id = r_origin.id
          WHERE v.status NOT IN ('Cancelled', 'Returned')";

$bind_params = '';
$bind_values = [];

// --- Apply Filters ---
if ($user_region_id) { // Auto-filter for Myanmar/Malay users
    $query .= " AND v.region_id = ?";
    $bind_params .= 'i';
    $bind_values[] = $user_region_id;
}
if (!empty($start_date)) {
    $query .= " AND DATE(v.created_at) >= ?";
    $bind_params .= 's';
    $bind_values[] = $start_date;
}
if (!empty($end_date)) {
    $query .= " AND DATE(v.created_at) <= ?";
    $bind_params .= 's';
    $bind_values[] = $end_date;
}
if ($filter_region_id !== 'All' && is_numeric($filter_region_id)) {
    $query .= " AND (v.region_id = ? OR v.destination_region_id = ?)";
    $bind_params .= 'ii';
    $bind_values[] = intval($filter_region_id);
    $bind_values[] = intval($filter_region_id);
}
if (!empty($filter_status)) {
    $query .= " AND v.status = ?";
    $bind_params .= 's';
    $bind_values[] = $filter_status;
}
if (!empty($search_term) && in_array($search_column, $allowed_search_columns)) {
    $query .= " AND v.$search_column LIKE ?";
    $bind_params .= 's';
    $bind_values[] = '%' . $search_term . '%';
}

$query .= " ORDER BY s.updated_at DESC";

$stmt = mysqli_prepare($connection, $query);
if ($stmt) {
    if (!empty($bind_params)) {
        mysqli_stmt_bind_param($stmt, $bind_params, ...$bind_values);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $stock_items[] = $row;
    }
    mysqli_stmt_close($stmt);
} else {
    flash_message('error', 'Error fetching stock items: ' . mysqli_error($connection));
}

include_template('header', ['page' => 'stock_list']);
?>

<div class="container mx-auto p-6">
    <h1 class="text-3xl font-bold mb-6">Stock List</h1>

    <!-- Advanced Filter Form -->
    <form action="index.php" method="GET" class="mb-6 bg-blue-50 p-4 rounded-lg shadow-inner flex flex-wrap items-center gap-4">
        <input type="hidden" name="page" value="stock_list">
        <div>
            <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date:</label>
            <input type="date" id="start_date" name="start_date" class="form-input mt-1" value="<?= htmlspecialchars($start_date) ?>">
        </div>
        <div>
            <label for="end_date" class="block text-sm font-medium text-gray-700">End Date:</label>
            <input type="date" id="end_date" name="end_date" class="form-input mt-1" value="<?= htmlspecialchars($end_date) ?>">
        </div>
        <div>
            <label for="filter_region_id" class="block text-sm font-medium text-gray-700">Region:</label>
            <select id="filter_region_id" name="region_id" class="form-select mt-1">
                <option value="All">All Regions</option>
                <?php foreach ($regions as $region): ?>
                    <option value="<?= $region['id'] ?>" <?= (strval($filter_region_id) === strval($region['id'])) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($region['region_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="filter_status" class="block text-sm font-medium text-gray-700">Status:</label>
            <select id="filter_status" name="status" class="form-select mt-1">
                <option value="">All Statuses</option>
                <?php foreach ($possible_statuses as $status): ?>
                    <option value="<?= $status ?>" <?= ($filter_status === $status) ? 'selected' : '' ?>><?= $status ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex-grow">
            <label for="search_term" class="block text-sm font-medium text-gray-700">Search:</label>
            <div class="flex mt-1">
                <select name="search_column" class="form-select rounded-r-none border-r-0">
                    <option value="voucher_code" <?= ($search_column === 'voucher_code') ? 'selected' : '' ?>>Voucher Code</option>
                    <option value="sender_name" <?= ($search_column === 'sender_name') ? 'selected' : '' ?>>Sender</option>
                    <option value="receiver_name" <?= ($search_column === 'receiver_name') ? 'selected' : '' ?>>Receiver</option>
                </select>
                <input type="text" name="search" placeholder="Enter search term..." class="form-input flex-grow rounded-l-none" value="<?= htmlspecialchars($search_term) ?>">
            </div>
        </div>
        <div class="self-end">
            <button type="submit" class="btn">Filter</button>
        </div>
    </form>

    <!-- Main Content and Bulk Actions Form -->
    <form action="index.php?page=stock_list&<?= http_build_query($_GET) ?>" method="POST" id="stock-list-form">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <?php if (is_admin() || is_developer() || is_myanmar_user() || is_malay_user()): ?>
                <div class="mb-4 flex flex-wrap items-center gap-4 p-4 bg-yellow-50 rounded-lg shadow-inner">
                    <label for="new_status" class="font-semibold">Set selected to:</label>
                    <select id="new_status" name="new_status" class="form-select w-auto" required>
                        <option value="">Select New Status</option>
                        <?php foreach ($possible_statuses as $status): ?>
                            <option value="<?= $status ?>"><?= $status ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-secondary">Update Selected</button>
                </div>
            <?php endif; ?>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <?php if (is_admin() || is_developer() || is_myanmar_user() || is_malay_user()): ?>
                                <th class="table-header w-4"><input type="checkbox" id="select-all-stocks"></th>
                            <?php endif; ?>
                            <th class="table-header">Voucher Code</th>
                            <th class="table-header">Origin Region</th>
                            <th class="table-header">Status</th>
                            <th class="table-header">Last Updated</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($stock_items)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4">No stock items found for the selected filter.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($stock_items as $item): ?>
                                <tr>
                                    <?php if (is_admin() || is_developer() || is_myanmar_user() || is_malay_user()): ?>
                                        <td class="table-cell"><input type="checkbox" name="stock_ids[]" value="<?= $item['id'] ?>" class="stock-checkbox"></td>
                                    <?php endif; ?>
                                    <td class="table-cell font-mono text-indigo-600"><?= htmlspecialchars($item['voucher_code']) ?></td>
                                    <td class="table-cell"><?= htmlspecialchars($item['origin_region'] ?? 'N/A') ?></td>
                                    <td class="table-cell">
                                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $item['status'])) ?>">
                                            <?= htmlspecialchars(ucfirst($item['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="table-cell"><?= date('Y-m-d H:i', strtotime($item['updated_at'])) ?></td>
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
document.addEventListener('DOMContentLoaded', function () {
    const selectAllCheckbox = document.getElementById('select-all-stocks');
    const stockCheckboxes = document.querySelectorAll('.stock-checkbox');

    if(selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            stockCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }
});
</script>

<?php
include_template('footer');
?>

