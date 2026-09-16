<?php
// pos/voucher_list.php - Displays a paginated list of all vouchers with filtering and branch-based permissions (V5).

require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/cache.php';
require_once 'includes/voucher_query.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authentication Check ---
if (!is_logged_in()) {
    flash_message('error', 'Please log in to view the voucher list.');
    redirect('index.php?page=login');
}

global $connection;
mysqli_set_charset($connection, "utf8mb4");

$user_branch_id = get_user_branch_id();

// --- Fetch Data for Filters ---
$regions = mbpos_cache_remember('lookup-regions', 'all', 300, function () use ($connection) {
    $rows = [];
    $region_result = mysqli_query($connection, "SELECT id, region_name FROM regions ORDER BY region_name");
    if ($region_result) while ($row = mysqli_fetch_assoc($region_result)) $rows[] = $row;
    return $rows;
});
$possible_statuses = ['Pending', 'In Transit', 'Delivered', 'Received', 'Cancelled', 'Returned', 'Maintenance'];

// --- Get Filter Parameters from GET request ---
$filters = mbpos_normalize_voucher_filters($_GET, $possible_statuses);
$start_date = $filters['start_date'];
$end_date = $filters['end_date'];
$filter_origin_region_id = $filters['origin_region_id'];
$filter_destination_region_id = $filters['destination_region_id'];
$filter_status = $filters['status'];
$search_term = $filters['search'];
$search_column = $filters['search_column'];

// --- Pagination Setup ---
$limit = 30; // Vouchers per page
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$queryFilter = mbpos_build_voucher_filter_sql($filters, (int)$user_branch_id, is_staff());
$where_sql = $queryFilter['where_sql'];
$bind_params = $queryFilter['types'];
$bind_values = $queryFilter['values'];

// --- Get Total Count for Pagination ---
$total_vouchers = 0;
$count_query = 'SELECT COUNT(*) FROM vouchers v' . $where_sql;
$stmt_count = mysqli_prepare($connection, $count_query);
if ($stmt_count) {
    if (!empty($bind_params)) {
        mysqli_stmt_bind_param($stmt_count, $bind_params, ...$bind_values);
    }
    mysqli_stmt_execute($stmt_count);
    $result_count = mysqli_stmt_get_result($stmt_count);
    $total_vouchers = (int)mysqli_fetch_row($result_count)[0];
    mysqli_stmt_close($stmt_count);
}
$total_pages = max(1, (int)ceil($total_vouchers / $limit));
$page = min($page, $total_pages);
$offset = ($page - 1) * $limit;

// --- Fetch Vouchers for the Current Page ---
$vouchers = [];
$select_fields = "SELECT\n" .
                "v.id, v.voucher_code, v.sender_name, v.receiver_name,\n" .
                "v.total_amount, v.status, v.created_at, v.currency,\n" .
                "r_origin.region_name AS origin_region,\n" .
                "r_dest.region_name AS destination_region,\n" .
                "b_origin.branch_name as origin_branch,\n" .
                "b_dest.branch_name as destination_branch,\n" .
                "u.username as created_by_username,\n" .
                "b_user.branch_name as creator_branch_name ";

// Filter and paginate voucher IDs before joining lookup tables, so each request
// performs enrichment joins for at most one page of records.
$query = $select_fields .
    "FROM (SELECT v.id FROM vouchers v" . $where_sql .
    " ORDER BY v.created_at DESC, v.id DESC LIMIT ? OFFSET ?) page_rows " .
    "INNER JOIN vouchers v ON v.id = page_rows.id " .
    "LEFT JOIN regions r_origin ON v.region_id = r_origin.id " .
    "LEFT JOIN regions r_dest ON v.destination_region_id = r_dest.id " .
    "LEFT JOIN branches b_origin ON v.origin_branch_id = b_origin.id " .
    "LEFT JOIN branches b_dest ON v.destination_branch_id = b_dest.id " .
    "LEFT JOIN users u ON v.created_by_user_id = u.id " .
    "LEFT JOIN branches b_user ON u.branch_id = b_user.id " .
    "ORDER BY v.created_at DESC, v.id DESC";
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
    }
    mysqli_stmt_close($stmt);
}

// Prepare pagination links
$pagination_params = $_GET;
unset($pagination_params['page'], $pagination_params['p']);
$pagination_query_string = http_build_query($pagination_params);
$pagination_suffix = $pagination_query_string !== '' ? '&' . $pagination_query_string : '';

include_template('header', ['page' => 'voucher_list']);
?>

<div class="v5-page">
    <div class="v5-page-head">
        <div class="v5-page-head__copy">
            <span class="v5-kicker" data-i18n="Logistics Records">Logistics Records</span>
            <h1 data-i18n="Voucher Ledger">Voucher Ledger</h1>
            <p data-i18n="Search, filter, and audit shipment entries across all operating nodes.">Search, filter, and audit shipment entries across all operating nodes.</p>
        </div>
        <div class="v5-page-actions flex items-center gap-3">
            <span class="v5-count"><?= number_format($total_vouchers) ?> <span data-i18n="total entries">total entries</span></span>
            <a href="index.php?page=voucher_create" class="btn-primary" data-i18n="Create Voucher">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Create Voucher
            </a>
        </div>
    </div>

    <!-- Filter Form -->
    <section class="v5-panel mb-6">
        <div class="v5-panel__head">
            <h2 data-i18n="Advanced Ledger Filters">Advanced Ledger Filters</h2>
            <a class="btn-ghost btn-sm" href="index.php?page=voucher_list" data-i18n="Reset Filters">Reset Filters</a>
        </div>
        <div class="v5-panel__body">
            <form action="index.php" method="GET" class="v5-filter-grid">
                <input type="hidden" name="page" value="voucher_list">

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
                        <option value="All" data-i18n="All Regions">All Regions</option>
                        <?php foreach ($regions as $r): ?>
                            <option value="<?= (int)$r['id'] ?>" <?= strval($filter_origin_region_id) === strval($r['id']) ? 'selected' : '' ?>><?= e($r['region_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="v5-field">
                    <label for="filter_destination_region_id" class="v5-field-label" data-i18n="Destination">Destination</label>
                    <select id="filter_destination_region_id" name="destination_region_id" class="v5-input">
                        <option value="All" data-i18n="All Regions">All Regions</option>
                        <?php foreach ($regions as $r): ?>
                            <option value="<?= (int)$r['id'] ?>" <?= strval($filter_destination_region_id) === strval($r['id']) ? 'selected' : '' ?>><?= e($r['region_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="v5-field">
                    <label for="filter_status" class="v5-field-label" data-i18n="Status">Status</label>
                    <select id="filter_status" name="status" class="v5-input">
                        <option value="" data-i18n="Any Status">Any Status</option>
                        <?php foreach ($possible_statuses as $s): ?>
                            <option value="<?= e($s) ?>" <?= ($filter_status === $s) ? 'selected' : '' ?>><?= e($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="v5-field v5-field--wide">
                    <label for="search_term" class="v5-field-label" data-i18n="Universal Search">Universal Search</label>
                    <div class="v5-search-group">
                        <select name="search_column" class="v5-input" style="max-width:160px;">
                            <option value="voucher_code" <?= ($search_column === 'voucher_code') ? 'selected' : '' ?> data-i18n="Voucher Code">Voucher Code</option>
                            <option value="sender_name" <?= ($search_column === 'sender_name') ? 'selected' : '' ?> data-i18n="Sender Name">Sender Name</option>
                            <option value="receiver_name" <?= ($search_column === 'receiver_name') ? 'selected' : '' ?> data-i18n="Receiver Name">Receiver Name</option>
                            <option value="receiver_phone" <?= ($search_column === 'receiver_phone') ? 'selected' : '' ?> data-i18n="Receiver Phone">Receiver Phone</option>
                        </select>
                        <input type="search" id="search_term" name="search" class="v5-input flex-1" placeholder="Type to search..." value="<?= e($search_term) ?>">
                    </div>
                </div>

                <div class="v5-field flex items-end">
                    <button type="submit" class="btn-primary w-full" data-i18n="Apply Filters">Apply Filters</button>
                </div>
            </form>
        </div>
    </section>

    <!-- Ledger Table -->
    <section class="v5-panel">
        <div class="v5-panel__head">
            <h2 data-i18n="Master Voucher Ledger">Master Voucher Ledger</h2>
            <span class="v5-count" data-i18n="Page">Page <?= $page ?> <span data-i18n="of">of</span> <?= $total_pages ?></span>
        </div>

        <div class="v5-panel__body p-0">
            <div class="overflow-x-auto">
                <table class="v5-table w-full">
                    <thead>
                        <tr>
                            <th data-i18n="Tracking Code">Tracking Code</th>
                            <th data-i18n="Sender">Sender</th>
                            <th data-i18n="Receiver">Receiver</th>
                            <th data-i18n="Route">Route</th>
                            <th data-i18n="Value">Value</th>
                            <th data-i18n="Status">Status</th>
                            <th data-i18n="Issuer">Issuer</th>
                            <th class="text-right" data-i18n="Action">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($vouchers)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="v5-empty">
                                        <span class="v5-empty__icon"><?= mbpos_icon('vouchers', 'w-8 h-8 text-slate-400') ?></span>
                                        <strong data-i18n="No ledger records match your criteria.">No ledger records match your criteria.</strong>
                                        <p data-i18n="Try clearing filters or adjusting your date range.">Try clearing filters or adjusting your date range.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($vouchers as $voucher):
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
                                        <a class="font-mono font-bold text-primary hover:underline" href="index.php?page=voucher_view&id=<?= (int)$voucher['id'] ?>">
                                            <?= e($voucher['voucher_code']) ?>
                                        </a>
                                    </td>
                                    <td><strong class="text-main"><?= e($voucher['sender_name']) ?></strong></td>
                                    <td><strong class="text-main"><?= e($voucher['receiver_name']) ?></strong></td>
                                    <td class="text-xs">
                                        <div><strong><?= e($voucher['origin_region'] ?? 'N/A') ?></strong> <span class="text-muted">(<?= e($voucher['origin_branch'] ?? 'N/A') ?>)</span></div>
                                        <div class="text-muted">→ <?= e($voucher['destination_region'] ?? 'N/A') ?> <span class="text-muted">(<?= e($voucher['destination_branch'] ?? 'N/A') ?>)</span></div>
                                    </td>
                                    <td class="font-mono font-bold text-main">
                                        <span class="text-xs text-muted"><?= e($voucher['currency']) ?></span> <?= number_format($voucher['total_amount'], 2) ?>
                                    </td>
                                    <td>
                                        <span class="v5-badge <?= $status_class ?>"><?= e($voucher['status']) ?></span>
                                    </td>
                                    <td class="text-xs text-muted">
                                        <div><?= e($voucher['created_by_username'] ?? 'N/A') ?></div>
                                        <div><?= e($voucher['creator_branch_name'] ?? 'System') ?></div>
                                    </td>
                                    <td class="text-right">
                                        <a href="index.php?page=voucher_view&id=<?= (int)$voucher['id'] ?>" class="btn-ghost btn-sm" data-i18n="Details">
                                            Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination Bar -->
        <?php if ($total_pages > 1): ?>
            <div class="v5-panel__head border-t border-slate-100 flex items-center justify-between">
                <div>
                    <?php if ($page > 1): ?>
                        <a href="index.php?page=voucher_list&p=<?= $page - 1 ?><?= e($pagination_suffix) ?>" class="btn-secondary btn-sm inline-flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            <span data-i18n="Previous">Previous</span>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="text-xs font-bold text-muted">
                    <span data-i18n="Page">Page</span> <?= $page ?> <span data-i18n="of">of</span> <?= $total_pages ?>
                </div>

                <div>
                    <?php if ($page < $total_pages): ?>
                        <a href="index.php?page=voucher_list&p=<?= $page + 1 ?><?= e($pagination_suffix) ?>" class="btn-secondary btn-sm inline-flex items-center gap-1.5">
                            <span data-i18n="Next">Next</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php include_template('footer'); ?>
