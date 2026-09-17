<?php
// pos/stock_list.php - Displays a filterable list of stock items and handles bulk status updates (V5).

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
mysqli_set_charset($connection, "utf8mb4");

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
    require_csrf_request();
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
        <div class="v5-page-head__copy">
            <span class="v5-kicker" data-i18n="Shipment Operations">Shipment Operations</span>
            <h1 data-i18n="Shipments">Shipment Stock</h1>
            <p data-i18n="Search, monitor, and update the active logistics queue.">Search, monitor, and update the active logistics queue.</p>
        </div>
        <div class="v5-page-actions">
            <span class="v5-count"><?= count($stock_items) ?> <span data-i18n="records loaded · maximum 500">records loaded · maximum 500</span></span>
        </div>
    </div>

    <!-- Filters Section -->
    <section class="v5-panel mb-6">
        <div class="v5-panel__head">
            <h2 data-i18n="Shipment Filters">Shipment Filters</h2>
            <a class="btn-ghost btn-sm" href="index.php?page=stock_list" data-i18n="Reset filters">Reset filters</a>
        </div>
        <div class="v5-panel__body">
            <form action="index.php" method="GET" class="v5-filter-grid">
                <input type="hidden" name="page" value="stock_list">

                <div class="v5-field">
                    <label for="start_date" class="v5-field-label" data-i18n="Start Date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="v5-input" value="<?= e($start_date) ?>">
                </div>

                <div class="v5-field">
                    <label for="end_date" class="v5-field-label" data-i18n="End Date">End Date</label>
                    <input type="date" id="end_date" name="end_date" class="v5-input" value="<?= e($end_date) ?>">
                </div>

                <div class="v5-field">
                    <label for="filter_region_id" class="v5-field-label" data-i18n="Region">Region</label>
                    <select id="filter_region_id" name="region_id" class="v5-input">
                        <option value="All" data-i18n="All Regions">All Regions</option>
                        <?php foreach ($regions as $region): ?>
                            <option value="<?= (int)$region['id'] ?>" <?= strval($filter_region_id) === strval($region['id']) ? 'selected' : '' ?>><?= e($region['region_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="v5-field">
                    <label for="filter_status" class="v5-field-label" data-i18n="Status">Status</label>
                    <select id="filter_status" name="status" class="v5-input">
                        <option value="" data-i18n="All Statuses">All Statuses</option>
                        <?php foreach ($possible_statuses as $status): ?>
                            <option value="<?= e($status) ?>" <?= $filter_status === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="v5-field v5-field--wide">
                    <label for="search_term" class="v5-field-label" data-i18n="Search">Search</label>
                    <div class="v5-search-group">
                        <select name="search_column" class="v5-input" style="max-width:160px;">
                            <option value="voucher_code" <?= $search_column === 'voucher_code' ? 'selected' : '' ?> data-i18n="Voucher Code">Voucher Code</option>
                            <option value="sender_name" <?= $search_column === 'sender_name' ? 'selected' : '' ?> data-i18n="Sender">Sender</option>
                            <option value="receiver_name" <?= $search_column === 'receiver_name' ? 'selected' : '' ?> data-i18n="Receiver">Receiver</option>
                            <option value="receiver_phone" <?= $search_column === 'receiver_phone' ? 'selected' : '' ?> data-i18n="Receiver Phone">Receiver Phone</option>
                        </select>
                        <input id="search_term" type="search" name="search" class="v5-input flex-1" placeholder="Search shipment records..." data-i18n-placeholder="Search shipment records..." value="<?= e($search_term) ?>">
                    </div>
                </div>

                <div class="v5-field flex items-end">
                    <button type="submit" class="btn-primary w-full" data-i18n="Apply Filters">Apply Filters</button>
                </div>
            </form>
        </div>
    </section>

    <!-- Active Shipment Queue Table -->
    <form action="index.php?page=stock_list&<?= e(http_build_query($_GET)) ?>" method="POST" id="stock-list-form" class="v5-panel">
        <?= csrf_input() ?>

        <div class="v5-panel__head flex-wrap gap-4">
            <div>
                <h2 data-i18n="Active Shipment Queue">Active Shipment Queue</h2>
                <span class="v5-count" data-i18n="Live database records">Live database records</span>
            </div>

            <?php if ($can_bulk_update): ?>
                <div class="v5-toolbar flex items-center gap-3">
                    <div class="v5-toolbar__group flex items-center gap-2">
                        <label for="new_status" class="text-xs font-bold text-muted uppercase" data-i18n="Selected Status:">Selected Status:</label>
                        <select id="new_status" name="new_status" class="v5-input" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;" required>
                            <option value="" data-i18n="Choose status">Choose status</option>
                            <?php foreach ($possible_statuses as $status): ?>
                                <option value="<?= e($status) ?>"><?= e($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary btn-sm" id="btn-submit-stock-batch" data-i18n="Update Selected">Update Selected</button>
                    <span id="stock-selected-counter" class="v5-badge v5-badge-info hidden">0 selected</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="v5-panel__body p-0">
            <div class="overflow-x-auto">
                <table class="v5-table w-full">
                    <thead>
                        <tr>
                            <?php if ($can_bulk_update): ?>
                                <th style="width:40px;"><input type="checkbox" id="select-all-stocks" aria-label="Select all shipments"></th>
                            <?php endif; ?>
                            <th data-i18n="Voucher">Voucher</th>
                            <th data-i18n="Sender → Receiver">Sender → Receiver</th>
                            <th data-i18n="Origin">Origin</th>
                            <th data-i18n="Status">Status</th>
                            <th data-i18n="Last Updated">Last Updated</th>
                            <th class="text-right" data-i18n="Action">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($stock_items)): ?>
                            <tr>
                                <td colspan="<?= $can_bulk_update ? 7 : 6 ?>">
                                    <div class="v5-empty">
                                        <span class="v5-empty__icon"><?= mbpos_icon('shipments', 'w-8 h-8 text-slate-400') ?></span>
                                        <strong data-i18n="No shipments match these filters">No shipments match these filters</strong>
                                        <p data-i18n="Adjust the date, region, status, or search query.">Adjust the date, region, status, or search query.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: foreach ($stock_items as $item):
                            $status_class = match(strtolower($item['status'] ?? '')) {
                                'delivered' => 'v5-badge-success',
                                'in transit' => 'v5-badge-info',
                                'pending' => 'v5-badge-warning',
                                'received' => 'v5-badge-neutral',
                                'maintenance' => 'v5-badge-danger',
                                default => 'v5-badge-neutral'
                            };
                        ?>
                            <tr>
                                <?php if ($can_bulk_update): ?>
                                    <td>
                                        <input type="checkbox" name="stock_ids[]" value="<?= (int)$item['id'] ?>" class="stock-checkbox" aria-label="Select <?= e($item['voucher_code']) ?>">
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <a class="font-mono font-bold text-primary hover:underline tracking-tight" href="index.php?page=voucher_view&id=<?= (int)($item['voucher_id'] ?? 0) ?>">
                                            <?= e($item['voucher_code']) ?>
                                        </a>
                                        <button type="button" class="text-slate-400 hover:text-blue-600 transition-colors p-1 rounded-md hover:bg-slate-100" title="Copy voucher code" data-copy="<?= e($item['voucher_code']) ?>" aria-label="Copy voucher code">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2" stroke-width="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" stroke-width="2"/></svg>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <strong><?= e($item['sender_name']) ?></strong>
                                    <div class="text-xs text-muted">→ <?= e($item['receiver_name']) ?> · <?= e($item['receiver_phone']) ?></div>
                                </td>
                                <td>
                                    <span class="v5-badge v5-badge-neutral"><?= e($item['origin_region'] ?? 'N/A') ?></span>
                                </td>
                                <td>
                                    <span class="v5-badge <?= $status_class ?>"><?= e($item['status']) ?></span>
                                </td>
                                <td class="text-xs text-muted font-mono whitespace-nowrap">
                                    <strong class="text-slate-800 font-mono block"><?= format_datetime_myanmar($item['updated_at'], 'date') ?></strong>
                                    <div class="flex items-center gap-1.5 text-slate-500 font-mono mt-0.5">
                                        <span><?= format_datetime_myanmar($item['updated_at'], 'time') ?></span>
                                        <span class="text-[10px] px-1.5 py-0.2 bg-slate-100 rounded text-slate-600 font-sans"><?= format_datetime_myanmar($item['updated_at'], 'relative') ?></span>
                                    </div>
                                </td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="index.php?page=voucher_view&id=<?= (int)($item['voucher_id'] ?? 0) ?>" class="btn-ghost btn-sm" data-i18n="Details">
                                            Details
                                        </a>
                                        <a href="voucher_print.php?id=<?= (int)($item['voucher_id'] ?? 0) ?>" target="_blank" rel="noopener noreferrer" class="btn-ghost btn-sm text-slate-600 hover:text-blue-600 p-2" title="Print Waybill" aria-label="Print Waybill">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                        </a>
                                        <a href="index.php?page=voucher_create&duplicate_id=<?= (int)($item['voucher_id'] ?? 0) ?>" class="btn-ghost btn-sm text-slate-600 hover:text-blue-600 p-2" title="Duplicate as New" aria-label="Duplicate as New">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect width="13" height="13" x="9" y="9" rx="2" ry="2" stroke-width="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" stroke-width="2"/></svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
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
    const counter = document.getElementById('stock-selected-counter');

    function updateStockCounter() {
        const checkedCount = document.querySelectorAll('.stock-checkbox:checked').length;
        if (counter) {
            if (checkedCount > 0) {
                const label = typeof window.mbposT === 'function' ? window.mbposT('selected') : 'selected';
                counter.textContent = checkedCount + ' ' + label;
                counter.classList.remove('hidden');
            } else {
                counter.classList.add('hidden');
            }
        }
    }

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            stockCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateStockCounter();
        });
    }

    stockCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateStockCounter);
    });

    const form = document.getElementById('stock-list-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const checkedCount = document.querySelectorAll('.stock-checkbox:checked').length;
            if (checkedCount === 0) {
                e.preventDefault();
                alert('Please select at least one stock item to update.');
                return false;
            }
            if (checkedCount > 200) {
                e.preventDefault();
                alert('You can select a maximum of 200 items per batch update.');
                return false;
            }
            const statusSelect = document.getElementById('new_status');
            if (!confirm('Are you sure you want to update ' + checkedCount + ' stock items to "' + statusSelect.value + '"?')) {
                e.preventDefault();
                return false;
            }
        });
    }
});
</script>

<?php include_template('footer'); ?>
