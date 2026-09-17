<?php
// pos/voucher_bulk_update.php - Page for filtering and bulk updating voucher statuses with branch permissions (V5).

require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/cache.php';
require_once 'includes/voucher_query.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authentication & Authorization ---
if (!is_logged_in() || (!is_admin() && !is_developer() && !is_staff())) {
    flash_message('error', 'You are not authorized to access this page.');
    redirect('index.php?page=dashboard');
}

global $connection;
$user_branch_id = get_user_branch_id();

// --- Define possible statuses and search columns ---
$possible_statuses = ['Pending', 'In Transit', 'Delivered', 'Received', 'Cancelled', 'Returned', 'Maintenance'];

// --- Handle POST request for bulk status update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_request();
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
        if (!$stmt) {
            error_log('MBPOS bulk voucher update prepare failed: ' . mysqli_error($connection));
            flash_message('error', 'Unable to prepare the voucher update. Please try again.');
        } else {
            mysqli_stmt_bind_param($stmt, $types, ...$update_values);
            if (mysqli_stmt_execute($stmt)) {
                $count = mysqli_stmt_affected_rows($stmt);
                flash_message('success', "$count vouchers were successfully updated to '" . $new_status . "'.");
            } else {
                error_log('MBPOS bulk voucher update execute failed: ' . mysqli_stmt_error($stmt));
                flash_message('error', 'Unable to update the selected vouchers. Please try again.');
            }
            mysqli_stmt_close($stmt);
        }
    }
    // Redirect back to the same page with filters preserved to see the result
    $return_params = $_GET;
    unset($return_params['page']);
    $return_query = http_build_query($return_params);
    redirect('index.php?page=voucher_bulk_update' . ($return_query !== '' ? '&' . $return_query : ''));
}

// --- Fetch Data for Filters and Display ---
$vouchers = [];
$regions = mbpos_cache_remember('lookup-regions', 'all', 300, function () use ($connection) {
    $rows = [];
    $regionResult = mysqli_query($connection, 'SELECT id, region_name FROM regions ORDER BY region_name');
    if ($regionResult) while ($row = mysqli_fetch_assoc($regionResult)) $rows[] = $row;
    return $rows;
});

// Get filter parameters from GET request
$filters = mbpos_normalize_voucher_filters($_GET, $possible_statuses);
$start_date = $filters['start_date'];
$end_date = $filters['end_date'];
$filter_origin_region_id = $filters['origin_region_id'];
$filter_destination_region_id = $filters['destination_region_id'];
$filter_status = $filters['status'];
$search_term = $filters['search'];
$search_column = $filters['search_column'];
$limit = 50;
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$queryFilter = mbpos_build_voucher_filter_sql($filters, (int)$user_branch_id, is_staff());
$where_sql = $queryFilter['where_sql'];
$bind_params = $queryFilter['types'];
$bind_values = $queryFilter['values'];

// Count without lookup joins, then constrain the requested page to a real range.
$total_vouchers = 0;
$count_stmt = mysqli_prepare($connection, 'SELECT COUNT(*) FROM vouchers v' . $where_sql);
if ($count_stmt) {
    if ($bind_params !== '') mysqli_stmt_bind_param($count_stmt, $bind_params, ...$bind_values);
    if (mysqli_stmt_execute($count_stmt)) {
        $count_result = mysqli_stmt_get_result($count_stmt);
        $total_vouchers = (int)(mysqli_fetch_row($count_result)[0] ?? 0);
    } else {
        error_log('MBPOS bulk voucher count execute failed: ' . mysqli_stmt_error($count_stmt));
    }
    mysqli_stmt_close($count_stmt);
} else {
    error_log('MBPOS bulk voucher count prepare failed: ' . mysqli_error($connection));
}
$total_pages = max(1, (int)ceil($total_vouchers / $limit));
$page = min($page, $total_pages);
$offset = ($page - 1) * $limit;

// Paginate voucher IDs first; lookup joins enrich only the current 50 rows.
$query = "SELECT v.id, v.voucher_code, v.sender_name, v.receiver_name, v.status, v.created_at,
                 r_origin.region_name AS origin_region,
                 b_origin.branch_name AS origin_branch,
                 r_dest.region_name AS destination_region,
                 b_dest.branch_name AS destination_branch
          FROM (SELECT v.id FROM vouchers v" . $where_sql . "
                ORDER BY v.created_at DESC, v.id DESC LIMIT ? OFFSET ?) page_rows
          INNER JOIN vouchers v ON v.id = page_rows.id
          LEFT JOIN regions r_origin ON v.region_id = r_origin.id
          LEFT JOIN branches b_origin ON v.origin_branch_id = b_origin.id
          LEFT JOIN regions r_dest ON v.destination_region_id = r_dest.id
          LEFT JOIN branches b_dest ON v.destination_branch_id = b_dest.id
          ORDER BY v.created_at DESC, v.id DESC";
$page_bind_params = $bind_params . 'ii';
$page_bind_values = array_merge($bind_values, [$limit, $offset]);

$stmt = mysqli_prepare($connection, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, $page_bind_params, ...$page_bind_values);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) $vouchers[] = $row;
    } else {
        error_log('MBPOS bulk voucher fetch execute failed: ' . mysqli_stmt_error($stmt));
        flash_message('error', 'Unable to load vouchers right now. Please try again.');
    }
    mysqli_stmt_close($stmt);
} else {
    error_log('MBPOS bulk voucher fetch failed: ' . mysqli_error($connection));
    flash_message('error', 'Unable to load vouchers right now. Please try again.');
}

// --- Prepare Export Link ---
$export_params = $_GET;
unset($export_params['page'], $export_params['p']);
$export_query_string = http_build_query($export_params);
$export_url = 'index.php?page=export_vouchers' . ($export_query_string !== '' ? '&' . $export_query_string : '');
$pagination_query_string = $export_query_string;
$pagination_suffix = $pagination_query_string !== '' ? '&' . $pagination_query_string : '';

include_template('header', ['page' => 'voucher_bulk_update']);
?>

<div class="v5-page">
    <div class="v5-page-head">
        <div class="v5-page-head__copy">
            <span class="v5-kicker" data-i18n="Ledger Operations">Ledger Operations</span>
            <h1 data-i18n="Bulk Voucher Update">Bulk Voucher Update</h1>
            <p data-i18n="Filter ledger records and apply controlled shipment-status changes.">Filter ledger records and apply controlled shipment-status changes.</p>
        </div>
        <div class="v5-page-actions flex items-center gap-3">
            <a href="<?= e($export_url) ?>" class="btn-secondary btn-sm" data-i18n="Export CSV">Export CSV</a>
            <span class="v5-count"><?= number_format($total_vouchers) ?> <span data-i18n="total entries">total entries</span></span>
        </div>
    </div>

    <!-- Filters Panel -->
    <section class="v5-panel mb-6">
        <div class="v5-panel__head">
            <h2 data-i18n="Voucher Filters">Voucher Filters</h2>
            <div class="v5-toolbar__group">
                <a class="btn-ghost btn-sm" href="index.php?page=voucher_bulk_update" data-i18n="Reset Filters">Reset Filters</a>
            </div>
        </div>
        <div class="v5-panel__body">
            <form action="index.php" method="GET" class="v5-filter-grid">
                <input type="hidden" name="page" value="voucher_bulk_update">

                <div class="v5-field">
                    <label for="start_date" class="v5-field-label" data-i18n="Start Date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="v5-input" value="<?= e($start_date) ?>">
                </div>

                <div class="v5-field">
                    <label for="end_date" class="v5-field-label" data-i18n="End Date">End Date</label>
                    <input type="date" id="end_date" name="end_date" class="v5-input" value="<?= e($end_date) ?>">
                </div>

                <div class="v5-field">
                    <label for="filter_origin_region_id" class="v5-field-label" data-i18n="Origin Region">Origin Region</label>
                    <select id="filter_origin_region_id" name="origin_region_id" class="v5-input">
                        <option value="All" data-i18n="All Origins">All Origins</option>
                        <?php foreach ($regions as $r): ?>
                            <option value="<?= (int)$r['id'] ?>" <?= strval($filter_origin_region_id) === strval($r['id']) ? 'selected' : '' ?>><?= e($r['region_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="v5-field">
                    <label for="filter_destination_region_id" class="v5-field-label" data-i18n="Destination">Destination</label>
                    <select id="filter_destination_region_id" name="destination_region_id" class="v5-input">
                        <option value="All" data-i18n="All Destinations">All Destinations</option>
                        <?php foreach ($regions as $r): ?>
                            <option value="<?= (int)$r['id'] ?>" <?= strval($filter_destination_region_id) === strval($r['id']) ? 'selected' : '' ?>><?= e($r['region_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="v5-field">
                    <label for="filter_status" class="v5-field-label" data-i18n="Status">Status</label>
                    <select id="filter_status" name="status" class="v5-input">
                        <option value="" data-i18n="All Statuses">All Statuses</option>
                        <?php foreach ($possible_statuses as $s): ?>
                            <option value="<?= e($s) ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= e($s) ?></option>
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
                        <input id="search_term" type="search" name="search" class="v5-input flex-1" placeholder="Search ledger records..." data-i18n-placeholder="Search ledger records..." value="<?= e($search_term) ?>">
                    </div>
                </div>

                <div class="v5-field flex items-end">
                    <button type="submit" class="btn-primary w-full" data-i18n="Apply Filters">Apply Filters</button>
                </div>
            </form>
        </div>
    </section>

    <!-- Bulk Action Form & Table -->
    <form action="index.php?page=voucher_bulk_update<?= e($pagination_suffix) ?><?= $page > 1 ? '&amp;p=' . $page : '' ?>" method="POST" id="bulk-update-form" class="v5-panel">
        <?= csrf_input() ?>

        <div class="v5-panel__head flex-wrap gap-4">
            <div>
                <h2 data-i18n="Filtered Voucher Ledger">Filtered Voucher Ledger</h2>
                <span class="v5-count" data-i18n="Maximum 200 updates per batch">Maximum 200 updates per batch</span>
            </div>
            <div class="v5-toolbar flex items-center gap-3">
                <div class="v5-toolbar__group flex items-center gap-2">
                    <label for="new_status" class="text-xs font-bold text-muted uppercase" data-i18n="Set Selected To:">Set Selected To:</label>
                    <select id="new_status" name="new_status" class="v5-input" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;" required>
                        <option value="" data-i18n="Choose status">Choose status</option>
                        <?php foreach ($possible_statuses as $status): ?>
                            <option value="<?= e($status) ?>"><?= e($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-primary btn-sm" id="btn-submit-batch" data-i18n="Update Selected">Update Selected</button>
                <span id="selected-counter" class="v5-badge v5-badge-info hidden">0 selected</span>
            </div>
        </div>

        <div class="v5-panel__body p-0">
            <div class="overflow-x-auto">
                <table class="v5-table w-full">
                    <thead>
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="select-all-vouchers" aria-label="Select all vouchers"></th>
                            <th data-i18n="Voucher Code">Voucher</th>
                            <th data-i18n="Sender → Receiver">Sender → Receiver</th>
                            <th data-i18n="Route">Route</th>
                            <th data-i18n="Status">Status</th>
                            <th data-i18n="Created">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($vouchers)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="v5-empty">
                                        <span class="v5-empty__icon"><?= mbpos_icon('vouchers', 'w-8 h-8 text-slate-400') ?></span>
                                        <strong data-i18n="No vouchers match these filters">No vouchers match these filters</strong>
                                        <p data-i18n="Adjust the search or reset the filter set.">Adjust the search or reset the filter set.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: foreach ($vouchers as $voucher):
                            $status_class = match(strtolower($voucher['status'] ?? '')) {
                                'delivered' => 'v5-badge-success',
                                'in transit' => 'v5-badge-info',
                                'pending' => 'v5-badge-warning',
                                'cancelled', 'returned' => 'v5-badge-danger',
                                default => 'v5-badge-neutral'
                            };
                        ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="voucher_ids[]" value="<?= (int)$voucher['id'] ?>" class="voucher-checkbox" aria-label="Select <?= e($voucher['voucher_code']) ?>">
                                </td>
                                <td>
                                    <a class="font-mono font-bold text-primary hover:underline" href="index.php?page=voucher_view&id=<?= (int)$voucher['id'] ?>">
                                        <?= e($voucher['voucher_code']) ?>
                                    </a>
                                </td>
                                <td>
                                    <strong><?= e($voucher['sender_name']) ?></strong>
                                    <div class="text-xs text-muted">→ <?= e($voucher['receiver_name']) ?></div>
                                </td>
                                <td>
                                    <div class="text-xs font-medium text-main"><?= e($voucher['origin_region'] ?? 'N/A') ?> / <?= e($voucher['origin_branch'] ?? 'N/A') ?></div>
                                    <div class="text-xs text-muted">→ <?= e($voucher['destination_region'] ?? 'N/A') ?> / <?= e($voucher['destination_branch'] ?? 'N/A') ?></div>
                                </td>
                                <td>
                                    <span class="v5-badge <?= $status_class ?>"><?= e($voucher['status']) ?></span>
                                </td>
                                <td class="text-xs whitespace-nowrap">
                                    <strong class="text-slate-800 font-mono block"><?= format_datetime_myanmar($voucher['created_at'], 'date') ?></strong>
                                    <div class="flex items-center gap-1.5 text-slate-500 font-mono mt-0.5">
                                        <span><?= format_datetime_myanmar($voucher['created_at'], 'time') ?></span>
                                        <span class="text-[10px] px-1.5 py-0.2 bg-slate-100 rounded text-slate-600 font-sans"><?= format_datetime_myanmar($voucher['created_at'], 'relative') ?></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="v5-panel__head border-t border-slate-100 flex items-center justify-between">
                <div>
                    <?php if ($page > 1): ?>
                        <a href="index.php?page=voucher_bulk_update&amp;p=<?= $page - 1 ?><?= e($pagination_suffix) ?>" class="btn-secondary btn-sm" data-i18n="Previous">Previous</a>
                    <?php endif; ?>
                </div>
                <div class="text-xs font-bold text-muted">
                    <span data-i18n="Page">Page</span> <?= $page ?> <span data-i18n="of">of</span> <?= $total_pages ?>
                </div>
                <div>
                    <?php if ($page < $total_pages): ?>
                        <a href="index.php?page=voucher_bulk_update&amp;p=<?= $page + 1 ?><?= e($pagination_suffix) ?>" class="btn-secondary btn-sm" data-i18n="Next">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select-all-vouchers');
    const voucherCheckboxes = document.querySelectorAll('.voucher-checkbox');
    const counter = document.getElementById('selected-counter');

    function updateCounter() {
        const checkedCount = document.querySelectorAll('.voucher-checkbox:checked').length;
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
        selectAllCheckbox.addEventListener('change', function() {
            voucherCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateCounter();
        });
    }

    voucherCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateCounter);
    });

    const form = document.getElementById('bulk-update-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const checkedCount = document.querySelectorAll('.voucher-checkbox:checked').length;
            if (checkedCount === 0) {
                e.preventDefault();
                alert('Please select at least one voucher to update.');
                return false;
            }
            if (checkedCount > 200) {
                e.preventDefault();
                alert('You can select a maximum of 200 vouchers per batch update.');
                return false;
            }
            const statusSelect = document.getElementById('new_status');
            if (!confirm('Are you sure you want to update ' + checkedCount + ' vouchers to "' + statusSelect.value + '"?')) {
                e.preventDefault();
                return false;
            }
        });
    }
});
</script>

<?php include_template('footer'); ?>
