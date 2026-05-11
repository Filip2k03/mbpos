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

<!-- V3 Liquid UI Wrapper -->
<div class="relative min-h-[85vh] bg-gray-50/30 p-4 sm:p-8 overflow-hidden font-sans">

    <!-- Ambient Background Glows -->
    <div class="absolute top-[-10%] right-[-5%] w-[500px] h-[500px] bg-indigo-400/10 rounded-full blur-[100px] pointer-events-none"></div>
    <div class="absolute bottom-[-10%] left-[-5%] w-[500px] h-[500px] bg-blue-400/10 rounded-full blur-[100px] pointer-events-none"></div>

    <div class="max-w-7xl mx-auto relative z-10">
        
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-5">
            <div>
                <h1 class="text-4xl font-extrabold bg-gradient-to-r from-gray-900 to-gray-600 bg-clip-text text-transparent tracking-tight">
                    Voucher Ledger
                </h1>
                <p class="text-sm font-medium text-gray-500 mt-1">Manage and track your operational entries</p>
            </div>
            
            <?php if (is_staff() || is_admin() || is_developer()): ?>
                <a href="index.php?page=voucher_create" class="group flex items-center justify-center gap-2 bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-6 py-3 rounded-2xl font-bold shadow-[0_8px_20px_rgb(79,70,229,0.25)] hover:shadow-[0_12px_25px_rgb(79,70,229,0.4)] transition-all transform hover:-translate-y-0.5">
                    <svg class="w-5 h-5 transition-transform group-hover:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Create New Voucher
                </a>
            <?php endif; ?>
        </div>

        <!-- Glassmorphism Filter Form -->
        <form action="index.php" method="GET" class="mb-8 bg-white/70 backdrop-blur-2xl p-6 sm:p-8 rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-white/60">
            <div class="flex items-center gap-3 mb-6 border-b border-gray-100 pb-4">
                <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                <h3 class="font-bold text-gray-700 tracking-wide">Advanced Filters</h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <input type="hidden" name="page" value="voucher_list">
                
                <div class="space-y-1.5">
                    <label for="start_date" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="w-full rounded-xl border-gray-200 bg-white/50 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all text-sm font-medium text-gray-700 py-3" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                
                <div class="space-y-1.5">
                    <label for="end_date" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">End Date</label>
                    <input type="date" id="end_date" name="end_date" class="w-full rounded-xl border-gray-200 bg-white/50 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all text-sm font-medium text-gray-700 py-3" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                
                <div class="space-y-1.5">
                    <label for="filter_origin_region_id" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">Origin Region</label>
                    <select id="filter_origin_region_id" name="origin_region_id" class="w-full rounded-xl border-gray-200 bg-white/50 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all text-sm font-medium text-gray-700 py-3 appearance-none">
                        <option value="All">All Regions</option>
                        <?php foreach ($regions as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= ($filter_origin_region_id == $r['id']) ? 'selected' : '' ?>><?= htmlspecialchars($r['region_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="space-y-1.5">
                    <label for="filter_destination_region_id" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">Dest. Region</label>
                    <select id="filter_destination_region_id" name="destination_region_id" class="w-full rounded-xl border-gray-200 bg-white/50 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all text-sm font-medium text-gray-700 py-3 appearance-none">
                        <option value="All">All Regions</option>
                        <?php foreach ($regions as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= ($filter_destination_region_id == $r['id']) ? 'selected' : '' ?>><?= htmlspecialchars($r['region_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="space-y-1.5">
                    <label for="filter_status" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">Status</label>
                    <select id="filter_status" name="status" class="w-full rounded-xl border-gray-200 bg-white/50 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all text-sm font-medium text-gray-700 py-3 appearance-none">
                        <option value="">Any Status</option>
                        <?php foreach ($possible_statuses as $s): ?>
                            <option value="<?= $s ?>" <?= ($filter_status === $s) ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="space-y-1.5 lg:col-span-2">
                    <label for="search_term" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">Universal Search</label>
                    <div class="flex shadow-sm rounded-xl overflow-hidden border border-gray-200 bg-white/50 focus-within:bg-white focus-within:ring-2 focus-within:ring-indigo-500/20 focus-within:border-indigo-500 transition-all">
                        <select name="search_column" class="border-0 bg-transparent text-sm font-medium text-gray-600 py-3 pl-4 pr-8 border-r border-gray-200 focus:ring-0">
                            <option value="voucher_code" <?= ($search_column === 'voucher_code') ? 'selected' : '' ?>>Voucher Code</option>
                            <option value="sender_name" <?= ($search_column === 'sender_name') ? 'selected' : '' ?>>Sender Name</option>
                            <option value="receiver_name" <?= ($search_column === 'receiver_name') ? 'selected' : '' ?>>Receiver Name</option>
                        </select>
                        <input type="text" name="search" placeholder="Type to search..." class="border-0 bg-transparent flex-1 py-3 px-4 text-sm font-medium text-gray-800 placeholder-gray-400 focus:ring-0" value="<?= htmlspecialchars($search_term) ?>">
                    </div>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-slate-800 text-white py-3 px-4 rounded-xl font-bold hover:bg-slate-900 focus:outline-none focus:ring-4 focus:ring-slate-500/30 transition-all flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Apply Filters
                    </button>
                </div>
            </div>
        </form>

        <!-- Liquid Data Table -->
        <div class="bg-white/70 backdrop-blur-2xl rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-white/60 overflow-hidden">
            <div class="overflow-x-auto w-full custom-scrollbar">
                <table class="w-full text-left border-collapse whitespace-nowrap">
                    <thead>
                        <tr class="bg-gray-50/50 border-b border-gray-100">
                            <th class="py-5 px-6 text-xs font-extrabold text-gray-400 uppercase tracking-widest">Tracking Code</th>
                            <th class="py-5 px-6 text-xs font-extrabold text-gray-400 uppercase tracking-widest">Sender</th>
                            <th class="py-5 px-6 text-xs font-extrabold text-gray-400 uppercase tracking-widest">Receiver</th>
                            <th class="py-5 px-6 text-xs font-extrabold text-gray-400 uppercase tracking-widest">Route (Origin &rarr; Dest)</th>
                            <th class="py-5 px-6 text-xs font-extrabold text-gray-400 uppercase tracking-widest">Value</th>
                            <th class="py-5 px-6 text-xs font-extrabold text-gray-400 uppercase tracking-widest">Status</th>
                            <th class="py-5 px-6 text-xs font-extrabold text-gray-400 uppercase tracking-widest">Issuer</th>
                            <th class="py-5 px-6 text-xs font-extrabold text-gray-400 uppercase tracking-widest text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100/60">
                        <?php if (empty($vouchers)): ?>
                            <tr>
                                <td colspan="8" class="py-16 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-400">
                                        <svg class="w-12 h-12 mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                                        <span class="text-sm font-medium">No ledger records match your criteria.</span>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($vouchers as $voucher): 
                                // Dynamic Status Colors
                                $statusClass = match(strtolower($voucher['status'])) {
                                    'pending' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                                    'in transit' => 'bg-blue-100 text-blue-700 border-blue-200',
                                    'delivered' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                                    'received' => 'bg-teal-100 text-teal-700 border-teal-200',
                                    'cancelled' => 'bg-red-100 text-red-700 border-red-200',
                                    'returned' => 'bg-orange-100 text-orange-700 border-orange-200',
                                    default => 'bg-gray-100 text-gray-700 border-gray-200',
                                };
                            ?>
                                <tr class="hover:bg-white/80 transition-colors duration-200 group">
                                    <td class="py-4 px-6">
                                        <span class="inline-flex items-center gap-1.5 font-mono text-sm font-bold text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded-md border border-indigo-100">
                                            <?= htmlspecialchars($voucher['voucher_code']) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-6 font-medium text-gray-800 text-sm"><?= htmlspecialchars($voucher['sender_name']) ?></td>
                                    <td class="py-4 px-6 font-medium text-gray-800 text-sm"><?= htmlspecialchars($voucher['receiver_name']) ?></td>
                                    
                                    <!-- Route formatting -->
                                    <td class="py-4 px-6 text-sm">
                                        <div class="flex items-center gap-2">
                                            <div class="flex flex-col">
                                                <span class="font-bold text-gray-700"><?= htmlspecialchars($voucher['origin_region'] ?? 'N/A') ?></span>
                                                <span class="text-xs text-gray-400"><?= htmlspecialchars($voucher['origin_branch'] ?? 'N/A') ?></span>
                                            </div>
                                            <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                                            <div class="flex flex-col">
                                                <span class="font-bold text-gray-700"><?= htmlspecialchars($voucher['destination_region'] ?? 'N/A') ?></span>
                                                <span class="text-xs text-gray-400"><?= htmlspecialchars($voucher['destination_branch'] ?? 'N/A') ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="py-4 px-6 font-bold text-gray-900 text-sm">
                                        <?= htmlspecialchars($voucher['currency']) ?> <?= number_format($voucher['total_amount'], 2) ?>
                                    </td>
                                    
                                    <td class="py-4 px-6">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold border <?= $statusClass ?>">
                                            <?= htmlspecialchars($voucher['status']) ?>
                                        </span>
                                    </td>
                                    
                                    <td class="py-4 px-6">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-semibold text-gray-800 flex items-center gap-1.5">
                                                <div class="w-2 h-2 rounded-full bg-slate-300"></div>
                                                <?= htmlspecialchars($voucher['created_by_username'] ?? 'N/A') ?>
                                            </span>
                                            <span class="text-xs text-gray-400 ml-3.5"><?= htmlspecialchars($voucher['creator_branch_name'] ?? 'System') ?></span>
                                        </div>
                                    </td>
                                    
                                    <td class="py-4 px-6 text-center">
                                        <a href="index.php?page=voucher_view&id=<?= $voucher['id'] ?>" class="inline-flex items-center justify-center p-2 rounded-xl text-indigo-500 hover:text-white hover:bg-indigo-500 transition-all duration-200">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Liquid Pagination -->
        <div class="mt-8 flex flex-col sm:flex-row justify-between items-center gap-4">
            <div class="flex-1 w-full sm:w-auto">
                <?php if ($page > 1): ?>
                    <a href="index.php?page=voucher_list&p=<?= $page - 1 ?>&<?= $pagination_query_string ?>" class="inline-flex items-center gap-2 px-5 py-2.5 bg-white/70 backdrop-blur-md border border-white hover:border-gray-200 text-gray-700 text-sm font-bold rounded-xl shadow-sm hover:shadow-md transition-all group">
                        <svg class="w-4 h-4 transform group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Previous
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="flex items-center gap-2 px-4 py-2 bg-white/50 backdrop-blur-md rounded-xl border border-white shadow-sm">
                <span class="text-sm font-medium text-gray-500">Page</span>
                <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-bold text-sm shadow-md">
                    <?= $page ?>
                </span>
                <span class="text-sm font-medium text-gray-500">of <?= max(1, $total_pages) ?></span>
            </div>
            
            <div class="flex-1 w-full sm:w-auto flex justify-end">
                <?php if ($page < $total_pages): ?>
                    <a href="index.php?page=voucher_list&p=<?= $page + 1 ?>&<?= $pagination_query_string ?>" class="inline-flex items-center gap-2 px-5 py-2.5 bg-white/70 backdrop-blur-md border border-white hover:border-gray-200 text-gray-700 text-sm font-bold rounded-xl shadow-sm hover:shadow-md transition-all group">
                        Next
                        <svg class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<style>
    /* Custom scrollbar to keep horizontal scrolling elegant on desktop */
    .custom-scrollbar::-webkit-scrollbar {
        height: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: rgba(243, 244, 246, 0.5); 
        border-radius: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(156, 163, 175, 0.5); 
        border-radius: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: rgba(107, 114, 128, 0.8); 
    }
</style>

<?php include_template('footer'); ?>