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
    $voucher_ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['voucher_ids'] ?? [])), function ($id) {
        return $id > 0;
    })));
    $new_status = $_POST['new_status'] ?? '';

    if (empty($voucher_ids)) {
        flash_message('error', 'No vouchers were selected for update.');
    } elseif (count($voucher_ids) > 200) {
        flash_message('error', 'A maximum of 200 vouchers can be updated per request.');
    } elseif (!in_array($new_status, $possible_statuses)) {
        flash_message('error', 'An invalid status was selected.');
    } else {
        $ids_placeholder = implode(',', array_fill(0, count($voucher_ids), '?'));
        $update_query = "UPDATE vouchers SET status = ? WHERE id IN ($ids_placeholder)";
        $types = 's' . str_repeat('i', count($voucher_ids));
        $update_values = array_merge([$new_status], $voucher_ids);
        if (is_staff() && $user_branch_id) {
            $update_query .= " AND (origin_branch_id = ? OR (destination_branch_id = ? AND status != 'Pending'))";
            $types .= 'ii';
            $update_values[] = $user_branch_id;
            $update_values[] = $user_branch_id;
        }
        $stmt = mysqli_prepare($connection, $update_query);
        mysqli_stmt_bind_param($stmt, $types, ...$update_values);

        if (mysqli_stmt_execute($stmt)) {
            $count = mysqli_stmt_affected_rows($stmt);
            flash_message('success', "$count vouchers were successfully updated to '" . htmlspecialchars($new_status) . "'.");
        } else {
            flash_message('error', 'Failed to update vouchers: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    }
    // Redirect back to the same page with filters preserved to see the result
    redirect('index.php?page=voucher_bulk_update&' . http_build_query($_GET));
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
$query .= " ORDER BY v.created_at DESC LIMIT 500";

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
    error_log('MBPOS bulk voucher fetch failed: ' . mysqli_error($connection));
    flash_message('error', 'Unable to load vouchers right now. Please try again.');
}

// --- Prepare Export Link ---
$export_params = $_GET;
unset($export_params['page']);
$export_query_string = http_build_query($export_params);
$export_url = 'index.php?page=export_vouchers&' . $export_query_string;

include_template('header', ['page' => 'voucher_bulk_update']);
?>

<div class="v5-page">
    <div class="v5-page-head"><div class="v5-page-head__copy"><span class="v5-kicker">Ledger operations</span><h1 data-i18n="Bulk Update">Bulk Voucher Update</h1><p>Filter up to 500 ledger records and apply controlled shipment-status changes.</p></div><span class="v5-count"><?= count($vouchers) ?> records loaded</span></div>

    <section class="v5-panel">
        <div class="v5-panel__head"><h2>Voucher Filters</h2><div class="v5-toolbar__group"><a class="v5-btn-ghost" href="index.php?page=voucher_bulk_update">Reset</a><a class="v5-btn-success" href="<?= htmlspecialchars($export_url, ENT_QUOTES, 'UTF-8') ?>">Export Excel</a></div></div>
        <div class="v5-panel__body"><form action="index.php" method="GET" class="v5-filter-grid"><input type="hidden" name="page" value="voucher_bulk_update">
            <div class="v5-field"><label for="start_date">Start date</label><input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date, ENT_QUOTES, 'UTF-8') ?>"></div>
            <div class="v5-field"><label for="end_date">End date</label><input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date, ENT_QUOTES, 'UTF-8') ?>"></div>
            <div class="v5-field"><label for="filter_origin_region_id">Origin region</label><select id="filter_origin_region_id" name="origin_region_id"><option value="All">All origins</option><?php foreach ($regions as $r): ?><option value="<?= (int)$r['id'] ?>" <?= strval($filter_origin_region_id) === strval($r['id']) ? 'selected' : '' ?>><?= htmlspecialchars($r['region_name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
            <div class="v5-field"><label for="filter_destination_region_id">Destination</label><select id="filter_destination_region_id" name="destination_region_id"><option value="All">All destinations</option><?php foreach ($regions as $r): ?><option value="<?= (int)$r['id'] ?>" <?= strval($filter_destination_region_id) === strval($r['id']) ? 'selected' : '' ?>><?= htmlspecialchars($r['region_name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
            <div class="v5-field"><label for="filter_status">Status</label><select id="filter_status" name="status"><option value="">All statuses</option><?php foreach ($possible_statuses as $s): ?><option value="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option><?php endforeach; ?></select></div>
            <div class="v5-field v5-field--wide"><label for="search_term">Search</label><div class="v5-search-group"><select name="search_column"><option value="voucher_code" <?= $search_column === 'voucher_code' ? 'selected' : '' ?>>Voucher Code</option><option value="sender_name" <?= $search_column === 'sender_name' ? 'selected' : '' ?>>Sender</option><option value="receiver_name" <?= $search_column === 'receiver_name' ? 'selected' : '' ?>>Receiver</option><option value="receiver_phone" <?= $search_column === 'receiver_phone' ? 'selected' : '' ?>>Receiver Phone</option></select><input id="search_term" type="search" name="search" placeholder="Search ledger records" value="<?= htmlspecialchars($search_term, ENT_QUOTES, 'UTF-8') ?>"></div></div>
            <div class="v5-field"><button type="submit" class="btn w-full">Apply Filters</button></div>
        </form></div>
    </section>

    <form action="index.php?page=voucher_bulk_update&<?= htmlspecialchars(http_build_query($_GET), ENT_QUOTES, 'UTF-8') ?>" method="POST" id="bulk-update-form" class="v5-panel">
        <?= csrf_input() ?>
        <div class="v5-panel__head"><h2>Filtered Voucher Ledger</h2><span class="v5-count">Maximum 200 updates per request</span></div>
        <div class="v5-toolbar"><div class="v5-toolbar__group"><label for="new_status">Set selected to</label><select id="new_status" name="new_status" required><option value="">Choose status</option><?php foreach ($possible_statuses as $status): ?><option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($status) ?></option><?php endforeach; ?></select></div><button type="submit" class="btn-secondary">Update Selected</button></div>
        <div class="overflow-x-auto"><table class="min-w-full"><thead><tr><th><input type="checkbox" id="select-all-vouchers" aria-label="Select all vouchers"></th><th>Voucher</th><th>Sender → Receiver</th><th>Route</th><th>Status</th><th>Created</th></tr></thead><tbody>
            <?php if (empty($vouchers)): ?><tr><td colspan="6"><div class="v5-empty"><span class="v5-empty__icon">▤</span><strong>No vouchers match these filters</strong><p>Adjust the search or reset the filter set.</p></div></td></tr>
            <?php else: foreach ($vouchers as $voucher): ?><tr><td><input type="checkbox" name="voucher_ids[]" value="<?= (int)$voucher['id'] ?>" class="voucher-checkbox" aria-label="Select <?= htmlspecialchars($voucher['voucher_code'], ENT_QUOTES, 'UTF-8') ?>"></td><td><a class="font-mono font-bold text-blue-600" href="index.php?page=voucher_view&id=<?= (int)$voucher['id'] ?>"><?= htmlspecialchars($voucher['voucher_code'], ENT_QUOTES, 'UTF-8') ?></a></td><td><strong><?= htmlspecialchars($voucher['sender_name'], ENT_QUOTES, 'UTF-8') ?></strong><br><span class="text-xs text-slate-500">→ <?= htmlspecialchars($voucher['receiver_name'], ENT_QUOTES, 'UTF-8') ?></span></td><td><?= htmlspecialchars($voucher['origin_region'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?> / <?= htmlspecialchars($voucher['origin_branch'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?><br><span class="text-xs text-slate-500">→ <?= htmlspecialchars($voucher['destination_region'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?> / <?= htmlspecialchars($voucher['destination_branch'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></span></td><td><span class="status-badge status-<?= strtolower(str_replace(' ', '-', $voucher['status'])) ?>"><?= htmlspecialchars($voucher['status'], ENT_QUOTES, 'UTF-8') ?></span></td><td><?= date('M j, Y', strtotime($voucher['created_at'])) ?></td></tr><?php endforeach; endif; ?>
        </tbody></table></div>
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
