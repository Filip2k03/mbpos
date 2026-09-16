<?php
// pos/stock_list.php - Displays a filterable list of stock items and handles bulk status updates.

require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/cache.php';

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
$can_bulk_update = is_admin() || is_developer() || is_myanmar_user() || is_malay_user();

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
    $stock_ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['stock_ids'] ?? [])), function ($id) {
        return $id > 0;
    })));
    $new_status = $_POST['new_status'] ?? '';

    if (!$can_bulk_update) {
        flash_message('error', 'You are not authorized to update shipment statuses.');
    } elseif (empty($stock_ids)) {
        flash_message('error', 'No stock items were selected for update.');
    } elseif (count($stock_ids) > 200) {
        flash_message('error', 'A maximum of 200 stock items can be updated per request.');
    } elseif (!in_array($new_status, $possible_statuses)) {
        flash_message('error', 'An invalid status was selected for the bulk update.');
    } else {
        // Get the corresponding voucher IDs from the selected stock IDs
        $ids_placeholder = implode(',', array_fill(0, count($stock_ids), '?'));
        $voucher_lookup = "SELECT s.id, s.voucher_id FROM stock s JOIN vouchers v ON v.id = s.voucher_id WHERE s.id IN ($ids_placeholder)";
        $types = str_repeat('i', count($stock_ids));
        $lookup_values = $stock_ids;
        if ($user_region_id) {
            $voucher_lookup .= " AND v.region_id = ?";
            $types .= 'i';
            $lookup_values[] = $user_region_id;
        }
        $stmt_vouchers = mysqli_prepare($connection, $voucher_lookup);
        mysqli_stmt_bind_param($stmt_vouchers, $types, ...$lookup_values);
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
$regions = mbpos_cache_remember('lookup-regions', 'all', 300, function () use ($connection) {
    $rows = [];
    $region_result = mysqli_query($connection, "SELECT id, region_name FROM regions ORDER BY region_name");
    if ($region_result) {
        while ($row = mysqli_fetch_assoc($region_result)) $rows[] = $row;
    }
    return $rows;
});

// Get filter parameters from GET request
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$filter_region_id = $_GET['region_id'] ?? 'All';
$filter_status = $_GET['status'] ?? '';
$search_term = trim($_GET['search'] ?? '');
$search_column = $_GET['search_column'] ?? 'voucher_code';

// Build the main query
$query = "SELECT s.id, v.id AS voucher_id, v.voucher_code, v.sender_name, v.receiver_name, v.receiver_phone, v.status, s.updated_at, r_origin.region_name AS origin_region
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

$query .= " ORDER BY s.updated_at DESC LIMIT 500";

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
    error_log('MBPOS stock fetch failed: ' . mysqli_error($connection));
    flash_message('error', 'Unable to load shipment stock right now. Please try again.');
}

include_template('header', ['page' => 'stock_list']);
?>

<div class="v5-page">
    <div class="v5-page-head">
        <div class="v5-page-head__copy"><span class="v5-kicker">Shipment operations</span><h1 data-i18n="Shipments">Shipment Stock</h1><p>Search, monitor, and update the active logistics queue.</p></div>
        <span class="v5-count"><?= count($stock_items) ?> records loaded · maximum 500</span>
    </div>

    <section class="v5-panel">
        <div class="v5-panel__head"><h2>Shipment Filters</h2><a class="v5-btn-ghost" href="index.php?page=stock_list">Reset filters</a></div>
        <div class="v5-panel__body">
            <form action="index.php" method="GET" class="v5-filter-grid">
                <input type="hidden" name="page" value="stock_list">
                <div class="v5-field"><label for="start_date">Start date</label><input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date, ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="v5-field"><label for="end_date">End date</label><input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date, ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="v5-field"><label for="filter_region_id">Region</label><select id="filter_region_id" name="region_id"><option value="All">All Regions</option><?php foreach ($regions as $region): ?><option value="<?= (int)$region['id'] ?>" <?= strval($filter_region_id) === strval($region['id']) ? 'selected' : '' ?>><?= htmlspecialchars($region['region_name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
                <div class="v5-field"><label for="filter_status">Status</label><select id="filter_status" name="status"><option value="">All Statuses</option><?php foreach ($possible_statuses as $status): ?><option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>" <?= $filter_status === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option><?php endforeach; ?></select></div>
                <div class="v5-field v5-field--wide"><label for="search_term">Search</label><div class="v5-search-group"><select name="search_column"><option value="voucher_code" <?= $search_column === 'voucher_code' ? 'selected' : '' ?>>Voucher Code</option><option value="sender_name" <?= $search_column === 'sender_name' ? 'selected' : '' ?>>Sender</option><option value="receiver_name" <?= $search_column === 'receiver_name' ? 'selected' : '' ?>>Receiver</option><option value="receiver_phone" <?= $search_column === 'receiver_phone' ? 'selected' : '' ?>>Receiver Phone</option></select><input id="search_term" type="search" name="search" placeholder="Search shipment records" value="<?= htmlspecialchars($search_term, ENT_QUOTES, 'UTF-8') ?>"></div></div>
                <div class="v5-field"><button type="submit" class="btn w-full">Apply Filters</button></div>
            </form>
        </div>
    </section>

    <form action="index.php?page=stock_list&<?= htmlspecialchars(http_build_query($_GET), ENT_QUOTES, 'UTF-8') ?>" method="POST" id="stock-list-form" class="v5-panel">
        <?= csrf_input() ?>
        <div class="v5-panel__head"><h2>Active Shipment Queue</h2><span class="v5-count">Live database records</span></div>
        <?php if ($can_bulk_update): ?>
            <div class="v5-toolbar"><div class="v5-toolbar__group"><label for="new_status">Selected status</label><select id="new_status" name="new_status" required><option value="">Choose status</option><?php foreach ($possible_statuses as $status): ?><option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($status) ?></option><?php endforeach; ?></select></div><button type="submit" class="btn-secondary">Update Selected</button></div>
        <?php endif; ?>
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead><tr><?php if ($can_bulk_update): ?><th><input type="checkbox" id="select-all-stocks" aria-label="Select all shipments"></th><?php endif; ?><th>Voucher</th><th>Sender → Receiver</th><th>Origin</th><th>Status</th><th>Last Updated</th></tr></thead>
                <tbody>
                    <?php if (empty($stock_items)): ?><tr><td colspan="6"><div class="v5-empty"><span class="v5-empty__icon">◇</span><strong>No shipments match these filters</strong><p>Adjust the date, region, status, or search query.</p></div></td></tr>
                    <?php else: foreach ($stock_items as $item): ?><tr>
                        <?php if ($can_bulk_update): ?><td><input type="checkbox" name="stock_ids[]" value="<?= (int)$item['id'] ?>" class="stock-checkbox" aria-label="Select <?= htmlspecialchars($item['voucher_code'], ENT_QUOTES, 'UTF-8') ?>"></td><?php endif; ?>
                        <td><a class="font-mono font-bold text-blue-600" href="index.php?page=voucher_view&id=<?= (int)($item['voucher_id'] ?? 0) ?>"><?= htmlspecialchars($item['voucher_code'], ENT_QUOTES, 'UTF-8') ?></a></td>
                        <td><strong><?= htmlspecialchars($item['sender_name'], ENT_QUOTES, 'UTF-8') ?></strong><br><span class="text-xs text-slate-500">→ <?= htmlspecialchars($item['receiver_name'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($item['receiver_phone'], ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><?= htmlspecialchars($item['origin_region'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="status-badge status-<?= strtolower(str_replace(' ', '-', $item['status'])) ?>"><?= htmlspecialchars($item['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><?= date('M j, Y · H:i', strtotime($item['updated_at'])) ?></td>
                    </tr><?php endforeach; endif; ?>
                </tbody>
            </table>
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

