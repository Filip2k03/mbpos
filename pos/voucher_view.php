<?php
// pos/voucher_view.php - Displays details of a specific voucher and allows for status/notes updates.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authentication Check ---
if (!is_logged_in()) {
    flash_message('error', 'Please log in to view vouchers.');
    redirect('index.php?page=login');
}

global $connection;
$voucher_id = intval($_GET['id'] ?? 0);

if ($voucher_id <= 0) {
    flash_message('error', 'Invalid voucher ID.');
    redirect('index.php?page=voucher_list');
}

// --- Define possible statuses ---
$possible_statuses = ['Pending', 'In Transit', 'Delivered', 'Received', 'Cancelled', 'Returned', 'Maintenance'];

// --- Handle POST request for status/notes update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_status = trim($_POST['status'] ?? '');
    $new_notes = trim($_POST['notes'] ?? '');

    if (in_array($new_status, $possible_statuses)) {
        $stmt_update = mysqli_prepare($connection, "UPDATE vouchers SET status = ?, notes = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_update, 'ssi', $new_status, $new_notes, $voucher_id);
        if (mysqli_stmt_execute($stmt_update)) {
            flash_message('success', 'Voucher updated successfully.');
        } else {
            flash_message('error', 'Failed to update voucher: ' . mysqli_stmt_error($stmt_update));
        }
        mysqli_stmt_close($stmt_update);
    } else {
        flash_message('error', 'Invalid status selected.');
    }
    redirect('index.php?page=voucher_view&id=' . $voucher_id);
}


// --- Fetch Full Voucher Data for Display ---
$query = "SELECT 
            v.*,
            r_origin.region_name AS origin_region_name,
            r_dest.region_name AS destination_region_name,
            b_origin.branch_name AS origin_branch_name,
            b_dest.branch_name AS destination_branch_name,
            u.username AS created_by_username
          FROM vouchers v
          LEFT JOIN regions r_origin ON v.region_id = r_origin.id
          LEFT JOIN regions r_dest ON v.destination_region_id = r_dest.id
          LEFT JOIN branches b_origin ON v.origin_branch_id = b_origin.id
          LEFT JOIN branches b_dest ON v.destination_branch_id = b_dest.id
          LEFT JOIN users u ON v.created_by_user_id = u.id
          WHERE v.id = ?";

$stmt = mysqli_prepare($connection, $query);
mysqli_stmt_bind_param($stmt, 'i', $voucher_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$voucher = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$voucher) {
    flash_message('error', 'Voucher not found.');
    redirect('index.php?page=voucher_list');
}

include_template('header', ['page' => 'voucher_view']);

// Dynamic Status Colors for the badge
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

<!-- V3 Liquid UI Wrapper -->
<div class="relative min-h-[85vh] bg-gray-50/30 p-4 sm:p-8 overflow-hidden font-sans">
    
    <!-- Ambient Background Glows -->
    <div class="absolute top-[0%] left-[-10%] w-[600px] h-[600px] bg-blue-400/10 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-[0%] right-[-10%] w-[600px] h-[600px] bg-purple-400/10 rounded-full blur-[120px] pointer-events-none"></div>

    <div class="max-w-6xl mx-auto relative z-10">
        
        <!-- Header Actions -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-5">
            <a href="index.php?page=voucher_list" class="group flex items-center gap-2 text-gray-500 hover:text-indigo-600 transition-colors font-medium text-sm bg-white/50 px-4 py-2 rounded-xl backdrop-blur-sm border border-white">
                <svg class="w-4 h-4 transform group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to Ledger
            </a>
            <a href="voucher_print.php?id=<?= $voucher['id'] ?>" target="_blank" class="flex items-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-600 text-white px-5 py-2.5 rounded-xl font-bold shadow-[0_8px_20px_rgb(16,185,129,0.25)] hover:shadow-[0_12px_25px_rgb(16,185,129,0.4)] transition-all transform hover:-translate-y-0.5">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Print Waybill
            </a>
        </div>

        <div class="bg-white/70 backdrop-blur-2xl rounded-[2.5rem] shadow-[0_8px_40px_rgb(0,0,0,0.06)] border border-white/80 p-8 sm:p-10">
            
            <!-- Tracking Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 pb-8 border-b border-gray-100 gap-6">
                <div class="flex items-center gap-5">
                    <div class="w-16 h-16 rounded-2xl flex items-center justify-center bg-gradient-to-br from-indigo-500 to-purple-600 shadow-lg shadow-indigo-500/30 text-white">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-3xl font-extrabold text-gray-900 tracking-tight">Shipment Details</h2>
                        <p class="text-gray-500 font-medium mt-1">Created by <?= htmlspecialchars($voucher['created_by_username'] ?? 'System', ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
                <div class="text-left md:text-right flex flex-col md:items-end">
                    <p class="font-mono text-2xl font-bold text-indigo-600 bg-indigo-50 px-4 py-1.5 rounded-xl border border-indigo-100 inline-block mb-2 tracking-wider">
                        #<?= htmlspecialchars($voucher['voucher_code'], ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold border <?= $statusClass ?> shadow-sm">
                        <?= htmlspecialchars($voucher['status'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>
            </div>

            <!-- Details Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Left Column: Key Info Cards -->
                <div class="lg:col-span-1 space-y-5">
                    
                    <!-- Sender Card -->
                    <div class="bg-white/50 backdrop-blur-sm border border-gray-100 rounded-3xl p-6 shadow-sm hover:shadow-md transition-shadow group relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-24 h-24 bg-blue-100/50 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
                        <div class="flex gap-4 relative z-10">
                            <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center text-blue-600 shrink-0">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-blue-500 uppercase tracking-wider mb-1">Sender</p>
                                <p class="font-extrabold text-gray-900 text-lg"><?= htmlspecialchars($voucher['sender_name'], ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="text-gray-500 font-medium text-sm mt-0.5"><?= htmlspecialchars($voucher['sender_phone'], ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Receiver Card -->
                    <div class="bg-white/50 backdrop-blur-sm border border-gray-100 rounded-3xl p-6 shadow-sm hover:shadow-md transition-shadow group relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-24 h-24 bg-emerald-100/50 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
                        <div class="flex gap-4 relative z-10">
                            <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center text-emerald-600 shrink-0">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-emerald-500 uppercase tracking-wider mb-1">Receiver</p>
                                <p class="font-extrabold text-gray-900 text-lg"><?= htmlspecialchars($voucher['receiver_name'], ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="text-gray-500 font-medium text-sm mt-0.5"><?= htmlspecialchars($voucher['receiver_phone'], ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Destination Card -->
                    <div class="bg-white/50 backdrop-blur-sm border border-gray-100 rounded-3xl p-6 shadow-sm hover:shadow-md transition-shadow group relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-24 h-24 bg-purple-100/50 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
                        <div class="flex gap-4 relative z-10">
                            <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center text-purple-600 shrink-0">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-purple-500 uppercase tracking-wider mb-1">Deliver To</p>
                                <p class="text-gray-700 font-medium text-sm leading-relaxed"><?= nl2br(htmlspecialchars($voucher['receiver_address'], ENT_QUOTES, 'UTF-8')) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Shipment Details & Forms -->
                <div class="lg:col-span-2 space-y-8">
                    
                    <!-- Metrics Grid -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-gray-50/80 rounded-2xl p-4 border border-gray-100 text-center hover:bg-white hover:shadow-md transition-all">
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Origin</p>
                            <p class="font-bold text-gray-800"><?= htmlspecialchars($voucher['origin_region_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="text-xs font-medium text-gray-500 mt-1 truncate"><?= htmlspecialchars($voucher['origin_branch_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <div class="bg-gray-50/80 rounded-2xl p-4 border border-gray-100 text-center hover:bg-white hover:shadow-md transition-all">
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Destination</p>
                            <p class="font-bold text-gray-800"><?= htmlspecialchars($voucher['destination_region_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="text-xs font-medium text-gray-500 mt-1 truncate"><?= htmlspecialchars($voucher['destination_branch_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <div class="bg-gray-50/80 rounded-2xl p-4 border border-gray-100 text-center hover:bg-white hover:shadow-md transition-all">
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Weight</p>
                            <p class="font-extrabold text-indigo-600 text-lg"><?= number_format($voucher['weight_kg'], 2) ?> <span class="text-sm">kg</span></p>
                        </div>
                        <div class="bg-indigo-50/50 rounded-2xl p-4 border border-indigo-100 text-center hover:bg-indigo-50 hover:shadow-md transition-all">
                            <p class="text-xs font-bold text-indigo-400 uppercase tracking-wider mb-1">Total Due</p>
                            <p class="font-extrabold text-indigo-700 text-lg"><?= htmlspecialchars($voucher['currency'], ENT_QUOTES, 'UTF-8') ?> <?= number_format($voucher['total_amount'], 2) ?></p>
                        </div>
                    </div>

                    <!-- Notes Display (With UTF-8 Enforcement for Myanmar Fonts) -->
                    <div>
                        <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider mb-3 flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            Current Notes
                        </h3>
                        <div class="w-full p-5 bg-yellow-50/50 border border-yellow-100 rounded-2xl min-h-[100px] text-gray-800 font-medium leading-relaxed shadow-inner">
                            <!-- ENT_QUOTES | UTF-8 prevents corruption of Myanmar encoding -->
                            <?= nl2br(htmlspecialchars($voucher['notes'] ?: 'No notes have been added yet.', ENT_QUOTES, 'UTF-8')) ?>
                        </div>
                    </div>

                    <!-- Operational Update Form -->
                    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
                        <h3 class="text-lg font-bold text-gray-900 mb-5 flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M7 7l13 13M20 20v-5h-5"/></svg>
                            </div>
                            Update Ledger
                        </h3>
                        
                        <form action="index.php?page=voucher_view&id=<?= $voucher_id ?>" method="POST" accept-charset="UTF-8">
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                                
                                <div class="md:col-span-4 space-y-2">
                                    <label for="status" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">Change Status</label>
                                    <select id="status" name="status" class="w-full rounded-xl border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all text-sm font-bold text-gray-700 py-3 shadow-sm" required>
                                        <?php foreach($possible_statuses as $status): ?>
                                            <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>" <?= ($voucher['status'] === $status) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="md:col-span-8 space-y-2">
                                    <label for="notes" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">Append Notes</label>
                                    <textarea id="notes" name="notes" rows="3" class="w-full rounded-xl border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all text-sm font-medium text-gray-800 py-3 shadow-sm" placeholder="Type Myanmar or English notes here..."><?= htmlspecialchars($voucher['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                </div>
                                
                            </div>
                            
                            <div class="mt-6 flex justify-end">
                                <button type="submit" class="bg-gradient-to-r from-indigo-600 to-blue-600 text-white py-3 px-8 rounded-xl font-bold hover:from-indigo-700 hover:to-blue-700 focus:outline-none focus:ring-4 focus:ring-indigo-500/30 shadow-[0_8px_20px_rgb(79,70,229,0.2)] transition-all transform hover:-translate-y-0.5 active:translate-y-0 flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Commit Changes
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php
include_template('footer');
?>