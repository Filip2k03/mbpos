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
    $new_status = trim($_POST['new_status'] ?? '');
    $batch_note = trim($_POST['batch_note'] ?? '');

    if (empty($voucher_ids)) {
        flash_message('error', 'No vouchers were selected for update.');
    } elseif (count($voucher_ids) > 200) {
        flash_message('error', 'A maximum of 200 vouchers can be updated per request.');
    } elseif (!in_array($new_status, $possible_statuses, true)) {
        flash_message('error', 'An invalid status was selected.');
    } else {
        $ids_placeholder = implode(',', array_fill(0, count($voucher_ids), '?'));

        if (!empty($batch_note)) {
            $username = $_SESSION['username'] ?? 'Staff';
            $user_type = $_SESSION['user_type'] ?? 'Operator';
            $note_entry = "[" . date('Y-m-d H:i:s') . "] (" . $username . " - " . $user_type . "): " . $batch_note;
            $update_query = "UPDATE vouchers "
                          . "SET status = ?, "
                          . "    notes = CASE "
                          . "        WHEN notes IS NULL OR TRIM(notes) = '' THEN ? "
                          . "        ELSE CONCAT(notes, '\n\n===SPLIT===\n\n', ?) "
                          . "    END "
                          . "WHERE id IN ($ids_placeholder)";
            $types = 'sss' . str_repeat('i', count($voucher_ids));
            $update_values = array_merge([$new_status, $note_entry, $note_entry], $voucher_ids);
        } else {
            $update_query = "UPDATE vouchers SET status = ? WHERE id IN ($ids_placeholder)";
            $types = 's' . str_repeat('i', count($voucher_ids));
            $update_values = array_merge([$new_status], $voucher_ids);
        }

        if (is_staff() && $user_branch_id) {
            $update_query .= " AND (origin_branch_id = ? OR destination_branch_id = ?)";
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
    // Redirect back to the same page with filters preserved
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

// Status Statistics Counts across the voucher system
$status_counts = array_fill_keys($possible_statuses, 0);
$stats_query = "SELECT status, COUNT(*) as cnt FROM vouchers v";
$stats_bind_types = '';
$stats_bind_vals = [];
if (is_staff() && $user_branch_id > 0) {
    $stats_query .= " WHERE (v.origin_branch_id = ? OR v.destination_branch_id = ?)";
    $stats_bind_types = 'ii';
    $stats_bind_vals = [$user_branch_id, $user_branch_id];
}
$stats_query .= " GROUP BY status";

$stats_stmt = mysqli_prepare($connection, $stats_query);
if ($stats_stmt) {
    if ($stats_bind_types !== '') {
        mysqli_stmt_bind_param($stats_stmt, $stats_bind_types, ...$stats_bind_vals);
    }
    if (mysqli_stmt_execute($stats_stmt)) {
        $stats_res = mysqli_stmt_get_result($stats_stmt);
        while ($r = mysqli_fetch_assoc($stats_res)) {
            $raw_st = trim((string)($r['status'] ?? ''));
            $matched = false;
            foreach ($possible_statuses as $ps) {
                if (strcasecmp($raw_st, $ps) === 0) {
                    $status_counts[$ps] += (int)$r['cnt'];
                    $matched = true;
                    break;
                }
            }
            if (!$matched && $raw_st !== '') {
                $status_counts[$raw_st] = ($status_counts[$raw_st] ?? 0) + (int)$r['cnt'];
            }
        }
    }
    mysqli_stmt_close($stats_stmt);
}
$all_vouchers_count = array_sum($status_counts);

// Get filter parameters from GET request
$filters = mbpos_normalize_voucher_filters($_GET, $possible_statuses);
$start_date = $filters['start_date'];
$end_date = $filters['end_date'];
$filter_origin_region_id = $filters['origin_region_id'];
$filter_destination_region_id = $filters['destination_region_id'];
$filter_status = $filters['status'];
$search_term = $filters['search'];
$search_column = $filters['search_column'];
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

// Limit & Pagination handling: support 25, 50, 100, 200, and 'all'
$raw_limit = strtolower(trim((string)($_GET['limit'] ?? '50')));
$is_all_limit = ($raw_limit === 'all');
if ($is_all_limit) {
    // Show all matching vouchers, capped safely at 500 records per batch view
    $limit = max(1, min($total_vouchers > 0 ? $total_vouchers : 500, 500));
} elseif (in_array((int)$raw_limit, [25, 50, 100, 200], true)) {
    $limit = (int)$raw_limit;
} else {
    $limit = 50;
    $raw_limit = '50';
}

$total_pages = max(1, (int)ceil($total_vouchers / $limit));
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$page = min($page, $total_pages);
$offset = ($page - 1) * $limit;

// Paginate voucher IDs first; lookup joins enrich only the requested rows.
$query = "SELECT v.id, v.voucher_code, v.sender_name, v.receiver_name, v.total_amount, v.currency, v.status, v.created_at,
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

// --- Prepare Export and Pagination Links ---
$export_params = $_GET;
unset($export_params['page'], $export_params['p']);
$export_query_string = http_build_query($export_params);
$export_url = 'index.php?page=export_vouchers' . ($export_query_string !== '' ? '&' . $export_query_string : '');
$pagination_query_string = $export_query_string;
$pagination_suffix = $pagination_query_string !== '' ? '&' . $pagination_query_string : '';

// Quick status filter ribbon definitions (including Maintenance)
$quick_statuses = ['All', 'Pending', 'In Transit', 'Received', 'Delivered', 'Cancelled', 'Returned', 'Maintenance'];
$status_url_base = $_GET;
unset($status_url_base['p']);

include_template('header', ['page' => 'voucher_bulk_update']);
?>

<div class="v5-page">
    <!-- Page Header -->
    <div class="v5-page-head">
        <div class="v5-page-head__copy">
            <span class="v5-kicker" data-i18n="Operations">Operations</span>
            <h1 data-i18n="Bulk Voucher Update">Bulk Voucher Update</h1>
            <p data-i18n="Filter ledger records, review shipment routes, and batch update operational statuses.">Filter ledger records, review shipment routes, and batch update operational statuses.</p>
        </div>
        <div class="v5-page-actions flex items-center gap-3">
            <a href="index.php?page=voucher_list" class="btn-secondary btn-sm flex items-center gap-1.5">
                <?= mbpos_icon('voucher_list', 'w-4 h-4') ?>
                <span data-i18n="Voucher Ledger">Voucher Ledger</span>
            </a>
            <a href="<?= e($export_url) ?>" class="btn-secondary btn-sm flex items-center gap-1.5" data-i18n="Export CSV">
                <?= mbpos_icon('download', 'w-4 h-4') ?>
                <span>Export CSV</span>
            </a>
            <span class="v5-count"><?= number_format($total_vouchers) ?> <span data-i18n="total entries">total entries</span></span>
        </div>
    </div>

    <!-- Status Overview Statistics Cards -->
    <section class="v5-kpi-grid" aria-label="Status Overview">
        <a href="index.php?page=voucher_bulk_update" class="v5-kpi-card v5-kpi-card--all <?= empty($filter_status) ? 'is-active' : '' ?>">
            <div class="v5-kpi-card__title">
                <span class="w-2 h-2 rounded-full bg-slate-900 inline-block"></span>
                <span data-i18n="All Vouchers">All Vouchers</span>
            </div>
            <div class="v5-kpi-card__count"><?= number_format($all_vouchers_count) ?></div>
        </a>

        <a href="index.php?page=voucher_bulk_update&amp;status=Pending" class="v5-kpi-card v5-kpi-card--pending <?= $filter_status === 'Pending' ? 'is-active' : '' ?>">
            <div class="v5-kpi-card__title">
                <span class="w-2 h-2 rounded-full bg-amber-500 inline-block"></span>
                <span data-i18n="Pending">Pending</span>
            </div>
            <div class="v5-kpi-card__count"><?= number_format($status_counts['Pending'] ?? 0) ?></div>
        </a>

        <a href="index.php?page=voucher_bulk_update&amp;status=In Transit" class="v5-kpi-card v5-kpi-card--in-transit <?= $filter_status === 'In Transit' ? 'is-active' : '' ?>">
            <div class="v5-kpi-card__title">
                <span class="w-2 h-2 rounded-full bg-sky-500 inline-block"></span>
                <span data-i18n="In Transit">In Transit</span>
            </div>
            <div class="v5-kpi-card__count"><?= number_format($status_counts['In Transit'] ?? 0) ?></div>
        </a>

        <a href="index.php?page=voucher_bulk_update&amp;status=Received" class="v5-kpi-card v5-kpi-card--received <?= $filter_status === 'Received' ? 'is-active' : '' ?>">
            <div class="v5-kpi-card__title">
                <span class="w-2 h-2 rounded-full bg-teal-500 inline-block"></span>
                <span data-i18n="Received">Received</span>
            </div>
            <div class="v5-kpi-card__count"><?= number_format($status_counts['Received'] ?? 0) ?></div>
        </a>

        <a href="index.php?page=voucher_bulk_update&amp;status=Delivered" class="v5-kpi-card v5-kpi-card--delivered <?= $filter_status === 'Delivered' ? 'is-active' : '' ?>">
            <div class="v5-kpi-card__title">
                <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>
                <span data-i18n="Delivered">Delivered</span>
            </div>
            <div class="v5-kpi-card__count"><?= number_format($status_counts['Delivered'] ?? 0) ?></div>
        </a>

        <a href="index.php?page=voucher_bulk_update&amp;status=Returned" class="v5-kpi-card v5-kpi-card--returned <?= $filter_status === 'Returned' ? 'is-active' : '' ?>">
            <div class="v5-kpi-card__title">
                <span class="w-2 h-2 rounded-full bg-rose-500 inline-block"></span>
                <span data-i18n="Returned">Returned</span>
            </div>
            <div class="v5-kpi-card__count"><?= number_format($status_counts['Returned'] ?? 0) ?></div>
        </a>

        <a href="index.php?page=voucher_bulk_update&amp;status=Cancelled" class="v5-kpi-card v5-kpi-card--cancelled <?= $filter_status === 'Cancelled' ? 'is-active' : '' ?>">
            <div class="v5-kpi-card__title">
                <span class="w-2 h-2 rounded-full bg-slate-500 inline-block"></span>
                <span data-i18n="Cancelled">Cancelled</span>
            </div>
            <div class="v5-kpi-card__count"><?= number_format($status_counts['Cancelled'] ?? 0) ?></div>
        </a>

        <a href="index.php?page=voucher_bulk_update&amp;status=Maintenance" class="v5-kpi-card v5-kpi-card--maintenance <?= $filter_status === 'Maintenance' ? 'is-active' : '' ?>">
            <div class="v5-kpi-card__title">
                <span class="w-2 h-2 rounded-full bg-purple-500 inline-block"></span>
                <span data-i18n="Maintenance">Maintenance</span>
            </div>
            <div class="v5-kpi-card__count"><?= number_format($status_counts['Maintenance'] ?? 0) ?></div>
        </a>
    </section>

    <!-- Filters Panel -->
    <section class="v5-panel mb-6" aria-label="Filters">
        <div class="v5-panel__head">
            <h2 data-i18n="Filter Ledger Records">Filter Ledger Records</h2>
            <div class="v5-toolbar__group">
                <a class="btn-ghost btn-sm" href="index.php?page=voucher_bulk_update" data-i18n="Reset Filters">Reset Filters</a>
            </div>
        </div>
        <div class="v5-panel__body">
            <!-- Quick Date Filter Shortcuts -->
            <div class="v5-date-chips" aria-label="Date range shortcuts">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider mr-1" data-i18n="Date Range:">Date Range:</span>
                <button type="button" class="v5-date-chip" data-range="today" data-i18n="Today">Today</button>
                <button type="button" class="v5-date-chip" data-range="yesterday" data-i18n="Yesterday">Yesterday</button>
                <button type="button" class="v5-date-chip" data-range="last7" data-i18n="Last 7 Days">Last 7 Days</button>
                <button type="button" class="v5-date-chip" data-range="month" data-i18n="This Month">This Month</button>
                <button type="button" class="v5-date-chip" data-range="clear" data-i18n="Clear Dates">Clear Dates</button>
            </div>

            <form action="index.php" method="GET" class="v5-filter-grid" id="filter-form">
                <input type="hidden" name="page" value="voucher_bulk_update">
                <?php if ($raw_limit !== '50'): ?>
                    <input type="hidden" name="limit" value="<?= e($raw_limit) ?>">
                <?php endif; ?>

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
                        <option value="All" <?= (empty($filter_status) || $filter_status === 'All') ? 'selected' : '' ?> data-i18n="All Statuses">All Statuses (<?= number_format($all_vouchers_count) ?>)</option>
                        <?php foreach ($possible_statuses as $s): ?>
                            <option value="<?= e($s) ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= e($s) ?> (<?= number_format($status_counts[$s] ?? 0) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="v5-field v5-field--wide">
                    <label for="search_term" class="v5-field-label" data-i18n="Search">Search</label>
                    <div class="v5-search-group">
                        <select name="search_column" class="v5-input" style="max-width:160px;">
                            <option value="id" <?= $search_column === 'id' ? 'selected' : '' ?> data-i18n="Voucher ID">Voucher ID (#ID)</option>
                            <option value="voucher_code" <?= $search_column === 'voucher_code' ? 'selected' : '' ?> data-i18n="Voucher Code">Voucher Code</option>
                            <option value="sender_name" <?= $search_column === 'sender_name' ? 'selected' : '' ?> data-i18n="Sender">Sender</option>
                            <option value="receiver_name" <?= $search_column === 'receiver_name' ? 'selected' : '' ?> data-i18n="Receiver">Receiver</option>
                            <option value="receiver_phone" <?= $search_column === 'receiver_phone' ? 'selected' : '' ?> data-i18n="Receiver Phone">Receiver Phone</option>
                        </select>
                        <input id="search_term" type="search" name="search" class="v5-input flex-1" placeholder="Search by ID, voucher code, sender, receiver..." data-i18n-placeholder="Search by ID, voucher code, sender, receiver..." value="<?= e($search_term) ?>">
                    </div>
                </div>

                <div class="v5-field flex items-end">
                    <button type="submit" class="btn-primary w-full" data-i18n="Apply Filters">Apply Filters</button>
                </div>
            </form>
        </div>
    </section>

    <!-- Main Bulk Update Form & Ledger View -->
    <form action="index.php?page=voucher_bulk_update<?= e($pagination_suffix) ?><?= $page > 1 ? '&amp;p=' . $page : '' ?>" method="POST" id="bulk-update-form" class="v5-panel">
        <?= csrf_input() ?>

        <!-- Panel Header with Quick Status Ribbon & Batch Action Controls -->
        <div class="v5-panel__head flex-col gap-4 items-stretch border-b border-slate-100">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 data-i18n="Filtered Voucher Ledger">Filtered Voucher Ledger</h2>
                    <div class="flex flex-wrap items-center gap-2 mt-0.5">
                        <span class="v5-count" data-i18n="Maximum 200 updates per batch">Maximum 200 updates per batch</span>
                        <span class="text-slate-300">•</span>
                        <span class="text-xs font-semibold text-slate-500 font-mono">Showing <?= count($vouchers) ?> of <?= number_format($total_vouchers) ?></span>
                        <?php if ($total_vouchers > count($vouchers)): ?>
                            <span class="text-slate-300">•</span>
                            <?php
                                $show_all_params = $_GET;
                                $show_all_params['limit'] = 'all';
                                unset($show_all_params['p']);
                                $show_all_url = 'index.php?' . http_build_query($show_all_params);
                            ?>
                            <a href="<?= e($show_all_url) ?>" class="text-xs text-blue-600 hover:text-blue-800 font-bold hover:underline" data-i18n="Show All Vouchers">Show All Vouchers</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Page Size / Limit Controls -->
                    <div class="flex items-center gap-1 bg-slate-100 p-0.5 rounded-lg border border-slate-200 text-xs font-semibold">
                        <span class="text-slate-500 px-1.5" data-i18n="Show:">Show:</span>
                        <?php foreach ([50, 100, 200, 'all'] as $optLimit):
                            $is_limit_active = ($optLimit === 'all' && $raw_limit === 'all') || ($raw_limit !== 'all' && (int)$raw_limit === $optLimit);
                            $lim_params = $_GET;
                            $lim_params['limit'] = $optLimit;
                            unset($lim_params['p']);
                            $lim_url = 'index.php?' . http_build_query($lim_params);
                        ?>
                            <a href="<?= e($lim_url) ?>" class="px-2 py-0.5 rounded-md transition-colors <?= $is_limit_active ? 'bg-white text-blue-700 shadow-xs font-bold' : 'text-slate-600 hover:text-slate-900' ?>">
                                <?= $optLimit === 'all' ? 'All' : $optLimit ?>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <span id="selected-counter" class="v5-badge v5-badge-info hidden">0 selected</span>
                    <button type="button" id="btn-select-all-toggle" class="btn-secondary btn-sm" data-i18n="Select All">Select All</button>
                    <button type="button" id="btn-clear-selection" class="btn-ghost btn-sm hidden" data-i18n="Clear Selection">Clear</button>
                </div>
            </div>

            <!-- Selected Voucher IDs Bar -->
            <div id="selected-ids-bar" class="hidden p-2.5 bg-blue-50/90 rounded-xl border border-blue-200/80 flex items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-2 overflow-x-auto py-0.5">
                    <span class="font-bold text-blue-900 whitespace-nowrap flex items-center gap-1">
                        <?= mbpos_icon('check', 'w-3.5 h-3.5 text-blue-600') ?>
                        <span data-i18n="Selected Voucher IDs:">Selected Voucher IDs:</span>
                    </span>
                    <span id="selected-ids-list" class="font-mono text-blue-800 font-semibold break-all"></span>
                </div>
                <button type="button" id="btn-copy-selected-ids" class="btn-ghost btn-xs text-blue-700 hover:bg-blue-100 flex items-center gap-1 whitespace-nowrap" title="Copy comma-separated IDs">
                    <?= mbpos_icon('copy', 'w-3 h-3') ?>
                    <span data-i18n="Copy IDs">Copy IDs</span>
                </button>
            </div>

            <!-- Quick Status Filter Pills Ribbon -->
            <div class="v5-status-ribbon flex flex-wrap items-center gap-2" aria-label="Quick Status Filters">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider mr-1" data-i18n="Quick Filter:">Quick Filter:</span>
                <?php foreach ($quick_statuses as $qs):
                    $is_qs_active = ($qs === 'All' && empty($filter_status)) || ($filter_status === $qs);
                    $qs_count = ($qs === 'All') ? $all_vouchers_count : ($status_counts[$qs] ?? 0);
                    if ($qs === 'All') {
                        $qs_url = 'index.php?page=voucher_bulk_update';
                        if ($raw_limit !== '50') $qs_url .= '&limit=' . urlencode($raw_limit);
                    } else {
                        $qs_params = $status_url_base;
                        $qs_params['status'] = $qs;
                        $qs_url = 'index.php?' . http_build_query($qs_params);
                    }
                ?>
                    <a href="<?= e($qs_url) ?>" class="v5-status-pill <?= $is_qs_active ? 'is-active' : '' ?>">
                        <span data-i18n="<?= e($qs) ?>"><?= e($qs) ?></span>
                        <span class="v5-pill-count"><?= number_format($qs_count) ?></span>
                    </a>
                <?php endforeach; ?>
                <?php if (!empty($start_date) || !empty($end_date) || $filter_origin_region_id !== 'All' || $filter_destination_region_id !== 'All' || !empty($filter_status) || !empty($search_term)): ?>
                    <a href="index.php?page=voucher_bulk_update<?= $raw_limit !== '50' ? '&amp;limit=' . urlencode($raw_limit) : '' ?>" class="btn-ghost btn-xs text-rose-600 hover:bg-rose-50 flex items-center gap-1 ml-auto" data-i18n="Clear Filters">
                        <?= mbpos_icon('x', 'w-3.5 h-3.5') ?>
                        <span>Clear Filters</span>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Batch Action Controls Ribbon -->
            <div class="p-3.5 bg-slate-50/80 rounded-xl border border-slate-200/80 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2 flex-1 min-w-[280px]">
                    <label for="new_status" class="text-xs font-bold text-slate-600 uppercase tracking-wider whitespace-nowrap" data-i18n="Set Selected To:">Set Selected To:</label>
                    <select id="new_status" name="new_status" class="v5-input" style="padding: 0.45rem 0.85rem; font-size: 0.85rem; min-width: 150px;" required>
                        <option value="" data-i18n="Choose status">Choose status</option>
                        <?php foreach ($possible_statuses as $status): ?>
                            <option value="<?= e($status) ?>"><?= e($status) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Quick Status Apply Preset Chips -->
                    <div class="flex flex-wrap items-center gap-1.5 ml-1">
                        <button type="button" class="status-quick-btn text-xs px-2 py-0.5 rounded-md bg-white border border-slate-200 text-slate-700 font-semibold hover:bg-amber-50 hover:text-amber-700 hover:border-amber-200 transition-all" data-status="Pending" data-i18n="Pending">Pending</button>
                        <button type="button" class="status-quick-btn text-xs px-2 py-0.5 rounded-md bg-white border border-slate-200 text-slate-700 font-semibold hover:bg-blue-50 hover:text-blue-700 hover:border-blue-200 transition-all" data-status="In Transit" data-i18n="In Transit">In Transit</button>
                        <button type="button" class="status-quick-btn text-xs px-2 py-0.5 rounded-md bg-white border border-slate-200 text-slate-700 font-semibold hover:bg-teal-50 hover:text-teal-700 hover:border-teal-200 transition-all" data-status="Received" data-i18n="Received">Received</button>
                        <button type="button" class="status-quick-btn text-xs px-2 py-0.5 rounded-md bg-white border border-slate-200 text-slate-700 font-semibold hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-200 transition-all" data-status="Delivered" data-i18n="Delivered">Delivered</button>
                        <button type="button" class="status-quick-btn text-xs px-2 py-0.5 rounded-md bg-white border border-slate-200 text-slate-700 font-semibold hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 transition-all" data-status="Returned" data-i18n="Returned">Returned</button>
                        <button type="button" class="status-quick-btn text-xs px-2 py-0.5 rounded-md bg-white border border-slate-200 text-slate-700 font-semibold hover:bg-slate-100 hover:text-slate-800 hover:border-slate-300 transition-all" data-status="Cancelled" data-i18n="Cancelled">Cancelled</button>
                        <button type="button" class="status-quick-btn text-xs px-2 py-0.5 rounded-md bg-white border border-slate-200 text-slate-700 font-semibold hover:bg-purple-50 hover:text-purple-700 hover:border-purple-200 transition-all" data-status="Maintenance" data-i18n="Maintenance">Maintenance</button>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 flex-1 min-w-[280px]">
                    <label for="batch_note" class="sr-only" data-i18n="Batch Operational Note">Batch Operational Note</label>
                    <input type="text" id="batch_note" name="batch_note" class="v5-input flex-1" style="padding: 0.45rem 0.85rem; font-size: 0.85rem;" placeholder="Optional memo (e.g. Dispatched via Cargo Van-01)..." data-i18n-placeholder="Optional memo (e.g. Dispatched via Cargo Van-01)..." maxlength="255">
                    <button type="submit" class="btn-primary btn-sm flex items-center gap-1.5" id="btn-submit-batch">
                        <?= mbpos_icon('check', 'w-4 h-4') ?>
                        <span data-i18n="Update Selected">Update Selected</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Ledger Table -->
        <div class="v5-panel__body p-0">
            <div class="overflow-x-auto">
                <table class="v5-table w-full" id="bulk-vouchers-table">
                    <thead>
                        <tr>
                            <th style="width:44px;" class="text-center">
                                <input type="checkbox" id="select-all-vouchers" aria-label="Select all vouchers on this page" title="Select all vouchers">
                            </th>
                            <th style="width:76px;" data-i18n="ID">ID</th>
                            <th data-i18n="Voucher Code">Voucher</th>
                            <th data-i18n="Sender → Receiver">Sender → Receiver</th>
                            <th data-i18n="Route">Route</th>
                            <th data-i18n="Amount">Amount</th>
                            <th data-i18n="Status">Status</th>
                            <th data-i18n="Created">Created</th>
                            <th data-i18n="Action" class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($vouchers)): ?>
                            <tr>
                                <td colspan="9">
                                    <div class="v5-empty">
                                        <span class="v5-empty__icon"><?= mbpos_icon('voucher_list', 'w-8 h-8 text-slate-400') ?></span>
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
                                'received' => 'v5-badge-success',
                                'cancelled', 'returned' => 'v5-badge-danger',
                                'maintenance' => 'v5-badge-purple',
                                default => 'v5-badge-neutral'
                            };
                            $currency = $voucher['currency'] ?? 'MMK';
                        ?>
                            <tr data-voucher-id="<?= (int)$voucher['id'] ?>" class="v5-table-row">
                                <td class="text-center" data-label="Select">
                                    <input type="checkbox" name="voucher_ids[]" value="<?= (int)$voucher['id'] ?>" class="voucher-checkbox" aria-label="Select Voucher #<?= (int)$voucher['id'] ?> (<?= e($voucher['voucher_code']) ?>)">
                                </td>
                                <td data-label="ID" class="whitespace-nowrap font-mono">
                                    <span class="v5-id-pill">#<?= (int)$voucher['id'] ?></span>
                                </td>
                                <td data-label="Voucher">
                                    <div class="flex items-center gap-1.5">
                                        <a class="font-mono font-bold text-primary hover:underline flex items-center gap-1" href="index.php?page=voucher_view&id=<?= (int)$voucher['id'] ?>" title="View voucher details">
                                            <?= e($voucher['voucher_code']) ?>
                                        </a>
                                        <button type="button" class="text-slate-400 hover:text-blue-600 transition-colors p-1 rounded-md hover:bg-slate-100" title="Copy voucher code" data-copy="<?= e($voucher['voucher_code']) ?>" aria-label="Copy voucher code">
                                            <?= mbpos_icon('copy', 'w-3.5 h-3.5') ?>
                                        </button>
                                    </div>
                                    <div class="text-[11px] text-slate-400 font-mono mt-0.5">ID: #<?= (int)$voucher['id'] ?></div>
                                </td>
                                <td data-label="Sender / Receiver">
                                    <strong><?= e($voucher['sender_name']) ?></strong>
                                    <div class="text-xs text-muted mt-0.5">→ <?= e($voucher['receiver_name']) ?></div>
                                </td>
                                <td data-label="Route">
                                    <div class="text-xs font-semibold text-slate-700"><?= e($voucher['origin_region'] ?? 'N/A') ?> <span class="text-slate-400">/</span> <?= e($voucher['origin_branch'] ?? 'N/A') ?></div>
                                    <div class="text-xs text-muted mt-0.5">→ <?= e($voucher['destination_region'] ?? 'N/A') ?> <span class="text-slate-400">/</span> <?= e($voucher['destination_branch'] ?? 'N/A') ?></div>
                                </td>
                                <td data-label="Amount" class="text-xs font-bold font-mono text-slate-800 whitespace-nowrap">
                                    <?= format_currency($voucher['total_amount'] ?? 0, $currency) ?>
                                </td>
                                <td data-label="Status">
                                    <span class="v5-badge <?= $status_class ?>"><?= e($voucher['status']) ?></span>
                                </td>
                                <td data-label="Created" class="text-xs whitespace-nowrap">
                                    <strong class="text-slate-800 font-mono block"><?= format_datetime_myanmar($voucher['created_at'], 'date') ?></strong>
                                    <div class="flex items-center gap-1.5 text-slate-500 font-mono mt-0.5">
                                        <span><?= format_datetime_myanmar($voucher['created_at'], 'time') ?></span>
                                        <span class="text-[10px] px-1.5 py-0.2 bg-slate-100 rounded text-slate-600 font-sans"><?= format_datetime_myanmar($voucher['created_at'], 'relative') ?></span>
                                    </div>
                                </td>
                                <td data-label="Action" class="text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="index.php?page=status_edit&id=<?= (int)$voucher['id'] ?>" class="btn-secondary btn-xs inline-flex items-center gap-1 text-slate-700 hover:text-blue-600" title="Edit individual status">
                                            <?= mbpos_icon('edit', 'w-3.5 h-3.5') ?>
                                            <span data-i18n="Update">Update</span>
                                        </a>
                                        <a href="index.php?page=voucher_view&id=<?= (int)$voucher['id'] ?>" class="btn-ghost btn-xs inline-flex items-center p-1 text-slate-500 hover:text-blue-600" title="View voucher">
                                            <?= mbpos_icon('external_link', 'w-3.5 h-3.5') ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination Controls -->
        <?php if ($total_pages > 1 || $raw_limit === 'all'): ?>
            <div class="v5-panel__head border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <?php if ($page > 1): ?>
                        <a href="index.php?page=voucher_bulk_update&amp;p=<?= $page - 1 ?><?= e($pagination_suffix) ?>" class="btn-secondary btn-sm" data-i18n="Previous">Previous</a>
                    <?php endif; ?>
                </div>
                <div class="text-xs font-bold text-muted flex items-center gap-2">
                    <?php if ($raw_limit === 'all'): ?>
                        <span data-i18n="Showing all vouchers">Showing all <?= number_format(count($vouchers)) ?> matching vouchers</span>
                        <span class="text-slate-300">•</span>
                        <?php
                            $paged_params = $_GET;
                            unset($paged_params['limit'], $paged_params['p']);
                            $paged_url = 'index.php?' . http_build_query($paged_params);
                        ?>
                        <a href="<?= e($paged_url) ?>" class="text-blue-600 hover:underline" data-i18n="Switch to 50 per page">Switch to 50/page</a>
                    <?php else: ?>
                        <span data-i18n="Page">Page</span> <?= $page ?> <span data-i18n="of">of</span> <?= $total_pages ?>
                        <span class="text-slate-400 font-normal">(<?= number_format($total_vouchers) ?> total)</span>
                        <?php if ($total_pages > 1): ?>
                            <span class="text-slate-300">•</span>
                            <?php
                                $all_view_params = $_GET;
                                $all_view_params['limit'] = 'all';
                                unset($all_view_params['p']);
                                $all_view_url = 'index.php?' . http_build_query($all_view_params);
                            ?>
                            <a href="<?= e($all_view_url) ?>" class="text-blue-600 hover:underline font-bold" data-i18n="Show All">Show All (<?= number_format($total_vouchers) ?>)</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if ($page < $total_pages && $raw_limit !== 'all'): ?>
                        <a href="index.php?page=voucher_bulk_update&amp;p=<?= $page + 1 ?><?= e($pagination_suffix) ?>" class="btn-secondary btn-sm" data-i18n="Next">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Floating Sticky Batch Action Dock (Anchors to Bottom on Selection) -->
        <div class="v5-bulk-floating-bar" id="bulk-floating-dock" aria-live="polite">
            <div class="flex items-center gap-3">
                <span class="v5-badge v5-badge-info font-bold text-sm" id="dock-counter">0 selected</span>
                <button type="button" id="dock-clear-btn" class="text-xs text-slate-300 hover:text-white underline" data-i18n="Clear Selection">Clear</button>
                <button type="button" id="dock-copy-btn" class="text-xs text-slate-300 hover:text-white flex items-center gap-1 bg-slate-800/80 px-2 py-1 rounded hover:bg-slate-700 transition-colors" title="Copy selected IDs">
                    <?= mbpos_icon('copy', 'w-3 h-3') ?>
                    <span data-i18n="Copy IDs">Copy IDs</span>
                </button>
            </div>
            <div class="flex items-center gap-2 v5-bulk-actions-group">
                <select id="dock-status-select" class="text-xs" aria-label="Selected status action">
                    <option value="" data-i18n="Choose status">Choose status</option>
                    <?php foreach ($possible_statuses as $status): ?>
                        <option value="<?= e($status) ?>"><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-primary btn-sm whitespace-nowrap" id="dock-submit-btn" data-i18n="Apply Bulk Update">Apply Bulk Update</button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById('bulk-vouchers-table');
    const selectAllCheckbox = document.getElementById('select-all-vouchers');
    const selectAllToggleBtn = document.getElementById('btn-select-all-toggle');
    const clearSelectionBtn = document.getElementById('btn-clear-selection');
    const voucherCheckboxes = Array.from(document.querySelectorAll('.voucher-checkbox'));
    const counter = document.getElementById('selected-counter');
    const floatingDock = document.getElementById('bulk-floating-dock');
    const dockCounter = document.getElementById('dock-counter');
    const dockClearBtn = document.getElementById('dock-clear-btn');
    const dockStatusSelect = document.getElementById('dock-status-select');
    const newStatusSelect = document.getElementById('new_status');
    const form = document.getElementById('bulk-update-form');
    const selectedIdsBar = document.getElementById('selected-ids-bar');
    const selectedIdsList = document.getElementById('selected-ids-list');
    const btnCopySelectedIds = document.getElementById('btn-copy-selected-ids');
    const dockCopyBtn = document.getElementById('dock-copy-btn');

    function t(key, fallback) {
        if (typeof window.mbposT === 'function') {
            const translated = window.mbposT(key);
            if (translated && translated !== key) return translated;
        }
        return fallback || key;
    }

    function getSelectedVoucherIds() {
        return voucherCheckboxes.filter(cb => cb.checked).map(cb => cb.value);
    }

    function copySelectedVoucherIds() {
        const ids = getSelectedVoucherIds();
        if (ids.length === 0) return;
        const text = ids.join(', ');
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => {
                if (typeof window.showToast === 'function') {
                    window.showToast(t('Copied ' + ids.length + ' Voucher ID(s) to clipboard', 'Copied ' + ids.length + ' Voucher ID(s) to clipboard'), 'info');
                } else {
                    alert('Copied ' + ids.length + ' Voucher ID(s): ' + text);
                }
            }).catch(() => {
                prompt(t('Selected Voucher IDs:', 'Selected Voucher IDs:'), text);
            });
        } else {
            prompt(t('Selected Voucher IDs:', 'Selected Voucher IDs:'), text);
        }
    }

    if (btnCopySelectedIds) btnCopySelectedIds.addEventListener('click', copySelectedVoucherIds);
    if (dockCopyBtn) dockCopyBtn.addEventListener('click', copySelectedVoucherIds);

    function updateSelectionUI() {
        const checkedBoxes = voucherCheckboxes.filter(cb => cb.checked);
        const count = checkedBoxes.length;
        const total = voucherCheckboxes.length;
        const selectedIds = checkedBoxes.map(cb => cb.value);

        // Sync indeterminate and checked state of master checkbox
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = (total > 0 && count === total);
            selectAllCheckbox.indeterminate = (count > 0 && count < total);
        }

        // Toggle row highlight class (.is-selected)
        voucherCheckboxes.forEach(cb => {
            const tr = cb.closest('tr');
            if (tr) {
                tr.classList.toggle('is-selected', cb.checked);
            }
        });

        // Top badges, counter, and selected IDs preview bar
        if (counter) {
            if (count > 0) {
                const label = t('vouchers selected', 'vouchers selected');
                counter.textContent = count + ' ' + label;
                counter.classList.remove('hidden');
                if (clearSelectionBtn) clearSelectionBtn.classList.remove('hidden');
            } else {
                counter.classList.add('hidden');
                if (clearSelectionBtn) clearSelectionBtn.classList.add('hidden');
            }
        }

        if (selectedIdsBar && selectedIdsList) {
            if (count > 0) {
                selectedIdsBar.classList.remove('hidden');
                const displayIds = selectedIds.slice(0, 20).map(id => '#' + id).join(', ');
                const extra = selectedIds.length > 20 ? ' (+' + (selectedIds.length - 20) + ' more)' : '';
                selectedIdsList.textContent = displayIds + extra;
            } else {
                selectedIdsBar.classList.add('hidden');
                selectedIdsList.textContent = '';
            }
        }

        // Floating bottom dock
        if (floatingDock) {
            if (count > 0) {
                floatingDock.classList.add('is-visible');
                if (dockCounter) {
                    const label = t('vouchers selected', 'vouchers selected');
                    dockCounter.textContent = count + ' ' + label;
                }
            } else {
                floatingDock.classList.remove('is-visible');
            }
        }
    }

    // Toggle master checkbox
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            const isChecked = this.checked;
            voucherCheckboxes.forEach(cb => { cb.checked = isChecked; });
            updateSelectionUI();
        });
    }

    // "Select All" / "Deselect All" button in panel head
    if (selectAllToggleBtn) {
        selectAllToggleBtn.addEventListener('click', function () {
            const allChecked = voucherCheckboxes.length > 0 && voucherCheckboxes.every(cb => cb.checked);
            const newState = !allChecked;
            voucherCheckboxes.forEach(cb => { cb.checked = newState; });
            this.textContent = newState ? t('Deselect All', 'Deselect All') : t('Select All', 'Select All');
            updateSelectionUI();
        });
    }

    // Clear selection buttons
    function clearSelection() {
        voucherCheckboxes.forEach(cb => { cb.checked = false; });
        if (selectAllToggleBtn) selectAllToggleBtn.textContent = t('Select All', 'Select All');
        updateSelectionUI();
    }
    if (clearSelectionBtn) clearSelectionBtn.addEventListener('click', clearSelection);
    if (dockClearBtn) dockClearBtn.addEventListener('click', clearSelection);

    // Checkbox change handlers + Shift-click range selection
    let lastCheckedIndex = -1;
    voucherCheckboxes.forEach((cb, idx) => {
        cb.addEventListener('click', function (e) {
            if (e.shiftKey && lastCheckedIndex !== -1 && lastCheckedIndex !== idx) {
                const start = Math.min(lastCheckedIndex, idx);
                const end = Math.max(lastCheckedIndex, idx);
                const shouldCheck = this.checked;
                for (let i = start; i <= end; i++) {
                    voucherCheckboxes[i].checked = shouldCheck;
                }
            }
            lastCheckedIndex = idx;
            updateSelectionUI();
        });
    });

    // Click anywhere on table row to toggle checkbox (excluding direct links and inputs)
    if (table) {
        table.addEventListener('click', function (e) {
            if (e.target.closest('a, button, input, select, label')) return;
            const tr = e.target.closest('tr.v5-table-row');
            if (tr) {
                const cb = tr.querySelector('.voucher-checkbox');
                if (cb) {
                    cb.checked = !cb.checked;
                    updateSelectionUI();
                }
            }
        });
    }

    // Fast status preset buttons
    document.querySelectorAll('.status-quick-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const status = this.getAttribute('data-status');
            if (newStatusSelect) {
                newStatusSelect.value = status;
                newStatusSelect.focus();
            }
            if (dockStatusSelect) dockStatusSelect.value = status;
        });
    });

    // Synchronize status selects between panel and floating dock
    if (dockStatusSelect && newStatusSelect) {
        dockStatusSelect.addEventListener('change', function () {
            newStatusSelect.value = this.value;
        });
        newStatusSelect.addEventListener('change', function () {
            dockStatusSelect.value = this.value;
        });
    }

    // Quick Date Range Shortcuts
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const filterForm = document.getElementById('filter-form');

    function formatDate(d) {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    document.querySelectorAll('.v5-date-chip').forEach(chip => {
        chip.addEventListener('click', function () {
            const range = this.getAttribute('data-range');
            const today = new Date();

            document.querySelectorAll('.v5-date-chip').forEach(c => c.classList.remove('is-active'));
            this.classList.add('is-active');

            if (range === 'today') {
                const ds = formatDate(today);
                if (startDateInput) startDateInput.value = ds;
                if (endDateInput) endDateInput.value = ds;
            } else if (range === 'yesterday') {
                const y = new Date();
                y.setDate(today.getDate() - 1);
                const ds = formatDate(y);
                if (startDateInput) startDateInput.value = ds;
                if (endDateInput) endDateInput.value = ds;
            } else if (range === 'last7') {
                const past = new Date();
                past.setDate(today.getDate() - 7);
                if (startDateInput) startDateInput.value = formatDate(past);
                if (endDateInput) endDateInput.value = formatDate(today);
            } else if (range === 'month') {
                const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                if (startDateInput) startDateInput.value = formatDate(firstDay);
                if (endDateInput) endDateInput.value = formatDate(today);
            } else if (range === 'clear') {
                if (startDateInput) startDateInput.value = '';
                if (endDateInput) endDateInput.value = '';
                this.classList.remove('is-active');
            }

            if (filterForm) filterForm.submit();
        });
    });

    // Form submission confirmation guard
    if (form) {
        form.addEventListener('submit', function (e) {
            const checkedCount = voucherCheckboxes.filter(cb => cb.checked).length;
            if (checkedCount === 0) {
                e.preventDefault();
                alert(t('Please select at least one voucher to update.', 'Please select at least one voucher to update.'));
                return false;
            }
            if (checkedCount > 200) {
                e.preventDefault();
                alert(t('You can select a maximum of 200 vouchers per batch update.', 'You can select a maximum of 200 vouchers per batch update.'));
                return false;
            }
            const chosenStatus = newStatusSelect ? newStatusSelect.value : '';
            if (!chosenStatus) {
                e.preventDefault();
                alert(t('Please choose a status to apply.', 'Please choose a status to apply.'));
                if (newStatusSelect) newStatusSelect.focus();
                return false;
            }
            const confirmMsg = `Are you sure you want to update ${checkedCount} voucher(s) to "${chosenStatus}"?`;
            if (!confirm(confirmMsg)) {
                e.preventDefault();
                return false;
            }
        });
    }

    // Initial check
    updateSelectionUI();
});
</script>

<?php include_template('footer'); ?>
