<?php
// pos/voucher_view.php - Displays details of a specific voucher with a conversational notes timeline.

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

// --- CRITICAL FIX FOR MYANMAR FONTS ---
// Forces the database connection to use full UTF-8, preventing mojibake/garbled text like "á€¡á€‘á€Š"
mysqli_set_charset($connection, "utf8mb4");

$voucher_id = intval($_GET['id'] ?? 0);

if ($voucher_id <= 0) {
    flash_message('error', 'Invalid voucher ID.');
    redirect('index.php?page=voucher_list');
}

// --- Define possible statuses ---
$possible_statuses = ['Pending', 'In Transit', 'Delivered', 'Received', 'Cancelled', 'Returned', 'Maintenance'];

// --- Fetch Full Voucher Data First (Needed for appending notes) ---
$query = "SELECT\n" .
         "v.*,\n" .
         "r_origin.region_name AS origin_region_name,\n" .
         "r_dest.region_name AS destination_region_name,\n" .
         "b_origin.branch_name AS origin_branch_name,\n" .
         "b_dest.branch_name AS destination_branch_name,\n" .
         "u.username AS created_by_username\n" .
         "FROM vouchers v\n" .
         "LEFT JOIN regions r_origin ON v.region_id = r_origin.id\n" .
         "LEFT JOIN regions r_dest ON v.destination_region_id = r_dest.id\n" .
         "LEFT JOIN branches b_origin ON v.origin_branch_id = b_origin.id\n" .
         "LEFT JOIN branches b_dest ON v.destination_branch_id = b_dest.id\n" .
         "LEFT JOIN users u ON v.created_by_user_id = u.id\n" .
         "WHERE v.id = ?";

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

// Fetch Item Breakdown Rows
$breakdown_items = [];
$stmt_items = mysqli_prepare($connection, "SELECT * FROM voucher_breakdowns WHERE voucher_id = ? ORDER BY id ASC");
if ($stmt_items) {
    mysqli_stmt_bind_param($stmt_items, 'i', $voucher_id);
    mysqli_stmt_execute($stmt_items);
    $res_items = mysqli_stmt_get_result($stmt_items);
    if ($res_items) {
        while ($item_row = mysqli_fetch_assoc($res_items)) {
            $breakdown_items[] = $item_row;
        }
    }
    mysqli_stmt_close($stmt_items);
}

// --- Handle POST request for status/notes update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_request();
    $new_status = trim($_POST['status'] ?? '');
    $new_note_text = trim($_POST['new_note'] ?? '');

    $final_notes = $voucher['notes'];

    // V3 Conversation Logic: Append new note with Author and Timestamp
    if (!empty($new_note_text)) {
        $author = $_SESSION['username'] ?? 'Unknown User';
        $date = date('Y-m-d H:i:s');

        // Structured format for parsing later
        $header = "[[{$author} @ {$date}]]";
        $entry = $header . "\n" . $new_note_text;

        if (empty(trim($final_notes))) {
            $final_notes = $entry;
        } else {
            // Append with a unique split marker
            $final_notes = $final_notes . "\n\n===SPLIT===\n\n" . $entry;
        }
    }

    if (in_array($new_status, $possible_statuses)) {
        $stmt_update = mysqli_prepare($connection, "UPDATE vouchers SET status = ?, notes = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_update, 'ssi', $new_status, $final_notes, $voucher_id);
        if (mysqli_stmt_execute($stmt_update)) {
            flash_message('success', 'Ledger updated successfully.');
        } else {
            flash_message('error', 'Failed to update ledger: ' . mysqli_stmt_error($stmt_update));
        }
        mysqli_stmt_close($stmt_update);
    } else {
        flash_message('error', 'Invalid status selected.');
    }

    // Redirect to prevent form resubmission
    redirect('index.php?page=voucher_view&id=' . $voucher_id);
}

// --- Parse Notes into Conversation Bubbles ---
$raw_notes = $voucher['notes'] ?? '';
$chat_bubbles = [];

if (!empty(trim($raw_notes))) {
    $parts = explode("===SPLIT===", $raw_notes);
    foreach ($parts as $part) {
        $part = trim($part);
        if (empty($part)) continue;

        // Try to extract the structured header
        if (preg_match('/^\[\[(.*?) @ (.*?)\]\]\n(.*)/s', $part, $matches)) {
            $raw_time = trim($matches[2]);
            $chat_bubbles[] = [
                'author' => trim($matches[1]),
                'time' => format_datetime_myanmar($raw_time, 'compact'),
                'relative' => format_datetime_myanmar($raw_time, 'relative'),
                'text' => trim($matches[3]),
                'is_legacy' => false
            ];
        } else {
            // Treat as an old/legacy note before the update
            $chat_bubbles[] = [
                'author' => 'System / Initial Note',
                'time' => format_datetime_myanmar($voucher['created_at'], 'compact'),
                'relative' => format_datetime_myanmar($voucher['created_at'], 'relative'),
                'text' => $part,
                'is_legacy' => true
            ];
        }
    }
}

// --- Dynamic Status Colors ---
$statusClass = match(strtolower($voucher['status'])) {
    'pending' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
    'in transit' => 'bg-blue-100 text-blue-700 border-blue-200',
    'delivered' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
    'received' => 'bg-teal-100 text-teal-700 border-teal-200',
    'cancelled' => 'bg-red-100 text-red-700 border-red-200',
    'returned' => 'bg-orange-100 text-orange-700 border-orange-200',
    default => 'bg-gray-100 text-gray-700 border-gray-200',
};

include_template('header', ['page' => 'voucher_view']);
?>

<!-- V3 Liquid UI Wrapper -->
<div class="relative min-h-[85vh] bg-gray-50/30 p-4 sm:p-8 overflow-hidden font-sans">
    <!-- Ambient Background Glows -->
    <div class="absolute top-[0%] left-[-10%] w-[600px] h-[600px] bg-blue-400/10 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-[0%] right-[-10%] w-[600px] h-[600px] bg-purple-400/10 rounded-full blur-[120px] pointer-events-none"></div>

    <div class="max-w-6xl mx-auto relative z-10">
        <!-- Header Actions -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-5">
            <a href="index.php?page=voucher_list" class="group flex items-center gap-2 text-gray-500 hover:text-indigo-600 transition-colors font-medium text-sm bg-white/50 px-4 py-2 rounded-xl backdrop-blur-sm border border-white shadow-sm hover:shadow-md">
                <svg class="w-4 h-4 transform group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span data-i18n="Back to Ledger">Back to Ledger</span>
            </a>
            <div class="flex items-center gap-3">
                <a href="index.php?page=voucher_create&duplicate_id=<?= $voucher['id'] ?>" class="flex items-center gap-2 bg-blue-50 text-blue-700 hover:bg-blue-100 px-4 py-2.5 rounded-xl font-semibold border border-blue-200 transition-all">
                    <?= mbpos_icon('voucher_create', 'w-4 h-4') ?>
                    <span data-i18n="Duplicate as New">Duplicate as New</span>
                </a>
                <a href="voucher_print.php?id=<?= $voucher['id'] ?>" target="_blank" class="flex items-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-600 text-white px-5 py-2.5 rounded-xl font-bold shadow-[0_8px_20px_rgb(16,185,129,0.25)] hover:shadow-[0_12px_25px_rgb(16,185,129,0.4)] transition-all transform hover:-translate-y-0.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span data-i18n="Print Waybill">Print Waybill</span>
                </a>
            </div>
        </div>

        <div class="bg-white/70 backdrop-blur-2xl rounded-[2.5rem] shadow-[0_8px_40px_rgb(0,0,0,0.06)] border border-white/80 p-6 sm:p-10">
            <!-- Tracking Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 pb-8 border-b border-gray-100 gap-6">
                <div class="flex items-center gap-5">
                    <div class="w-16 h-16 rounded-2xl flex items-center justify-center bg-gradient-to-br from-indigo-500 to-purple-600 shadow-lg shadow-indigo-500/30 text-white shrink-0">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <h2 class="text-3xl font-extrabold text-gray-900 tracking-tight" data-i18n="Voucher Details">Voucher Details</h2>
                            <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-800">
                                <?= e($voucher['delivery_type'] ?? 'Standard') ?>
                            </span>
                        </div>
                        <p class="text-gray-500 font-medium text-sm flex flex-wrap items-center gap-2">
                            <span><span data-i18n="Issued by">Issued by</span> <strong class="text-gray-800"><?= htmlspecialchars($voucher['created_by_username'] ?? 'System', ENT_QUOTES, 'UTF-8') ?></strong> (<?= e($voucher['origin_branch_name'] ?? 'N/A') ?>)</span>
                            <span class="text-gray-300">•</span>
                            <span class="font-mono text-slate-700 bg-slate-100 px-2 py-0.5 rounded text-xs"><?= format_datetime_myanmar($voucher['created_at'], 'full') ?></span>
                            <span class="text-xs font-semibold text-slate-500">(<?= format_datetime_myanmar($voucher['created_at'], 'relative') ?>)</span>
                        </p>
                    </div>
                </div>
                <div class="text-left md:text-right flex flex-col md:items-end">
                    <div class="flex items-center gap-2 mb-2">
                        <p class="font-mono text-2xl font-bold text-indigo-600 bg-indigo-50 px-4 py-1.5 rounded-xl border border-indigo-100 tracking-wider">
                            #<?= htmlspecialchars($voucher['voucher_code'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <button type="button" class="p-2.5 bg-slate-100 hover:bg-indigo-50 text-slate-500 hover:text-indigo-600 rounded-xl transition-all border border-slate-200 hover:border-indigo-200" title="Copy voucher code" data-copy="<?= e($voucher['voucher_code']) ?>" aria-label="Copy voucher code">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2" stroke-width="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" stroke-width="2"/></svg>
                        </button>
                    </div>
                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-bold border <?= $statusClass ?> shadow-sm">
                        <span class="w-2 h-2 rounded-full bg-current mr-2"></span>
                        <?= htmlspecialchars($voucher['status'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>
            </div>

            <!-- Details Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- Left Column: Key Info Cards (Spans 4 columns) -->
                <div class="lg:col-span-4 space-y-5">
                    <!-- Sender Card -->
                    <div class="bg-white/50 backdrop-blur-sm border border-gray-100 rounded-3xl p-6 shadow-sm hover:shadow-md transition-shadow group relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-24 h-24 bg-blue-100/50 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
                        <div class="flex gap-4 relative z-10">
                            <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center text-blue-600 shrink-0">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-blue-500 uppercase tracking-wider mb-1" data-i18n="Sender">Sender</p>
                                <p class="font-extrabold text-gray-900 text-lg"><?= htmlspecialchars($voucher['sender_name'], ENT_QUOTES, 'UTF-8') ?></p>
                                <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $voucher['sender_phone'])) ?>" class="text-blue-600 hover:text-blue-800 font-mono font-semibold text-sm mt-0.5 inline-flex items-center gap-1.5 hover:underline" title="Call Sender" data-i18n-title="Call Sender">
                                    <span><?= htmlspecialchars($voucher['sender_phone'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <svg class="w-3.5 h-3.5 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                </a>
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
                                <p class="text-xs font-bold text-emerald-500 uppercase tracking-wider mb-1" data-i18n="Receiver">Receiver</p>
                                <p class="font-extrabold text-gray-900 text-lg"><?= htmlspecialchars($voucher['receiver_name'], ENT_QUOTES, 'UTF-8') ?></p>
                                <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $voucher['receiver_phone'])) ?>" class="text-emerald-600 hover:text-emerald-800 font-mono font-semibold text-sm mt-0.5 inline-flex items-center gap-1.5 hover:underline" title="Call Receiver" data-i18n-title="Call Receiver">
                                    <span><?= htmlspecialchars($voucher['receiver_phone'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <svg class="w-3.5 h-3.5 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                </a>
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
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-bold text-purple-500 uppercase tracking-wider mb-1" data-i18n="Deliver To">Deliver To</p>
                                    <button type="button" class="text-purple-500 hover:text-purple-700 p-1 rounded-md hover:bg-purple-100 transition-colors" title="Copy Address" data-i18n-title="Copy Address" data-copy="<?= e($voucher['receiver_address']) ?>" aria-label="Copy Address">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2" stroke-width="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" stroke-width="2"/></svg>
                                    </button>
                                </div>
                                <p class="text-gray-700 font-medium text-sm leading-relaxed break-words"><?= nl2br(htmlspecialchars($voucher['receiver_address'], ENT_QUOTES, 'UTF-8')) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Shipment Details & Forms (Spans 8 columns) -->
                <div class="lg:col-span-8 space-y-8">
                    <!-- Metrics Grid -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-gray-50/80 rounded-2xl p-4 border border-gray-100 text-center hover:bg-white hover:shadow-md transition-all">
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1" data-i18n="Origin">Origin</p>
                            <p class="font-bold text-gray-800 truncate"><?= htmlspecialchars($voucher['origin_region_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="text-xs font-medium text-gray-500 mt-1 truncate"><?= htmlspecialchars($voucher['origin_branch_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <div class="bg-gray-50/80 rounded-2xl p-4 border border-gray-100 text-center hover:bg-white hover:shadow-md transition-all">
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1" data-i18n="Destination">Destination</p>
                            <p class="font-bold text-gray-800 truncate"><?= htmlspecialchars($voucher['destination_region_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="text-xs font-medium text-gray-500 mt-1 truncate"><?= htmlspecialchars($voucher['destination_branch_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <div class="bg-gray-50/80 rounded-2xl p-4 border border-gray-100 text-center hover:bg-white hover:shadow-md transition-all">
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1" data-i18n="Weight">Weight</p>
                            <p class="font-extrabold text-indigo-600 text-lg"><?= number_format($voucher['weight_kg'], 2) ?> <span class="text-sm">kg</span></p>
                        </div>
                        <div class="bg-indigo-50/50 rounded-2xl p-4 border border-indigo-100 text-center hover:bg-indigo-50 hover:shadow-md transition-all">
                            <p class="text-xs font-bold text-indigo-400 uppercase tracking-wider mb-1" data-i18n="Total Due">Total Due</p>
                            <p class="font-extrabold text-indigo-700 text-lg"><?= htmlspecialchars($voucher['currency'], ENT_QUOTES, 'UTF-8') ?> <?= number_format($voucher['total_amount'], 2) ?></p>
                        </div>
                    </div>

                    <!-- Cargo Items Breakdown & Pricing -->
                    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-4 mb-6">
                            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                </div>
                                <span data-i18n="Cargo Breakdown & Pricing">Cargo Breakdown & Pricing</span>
                            </h3>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-100">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                <?= count($breakdown_items) ?> <span data-i18n="item(s)">item(s)</span>
                            </span>
                        </div>

                        <?php if (!empty($breakdown_items)): ?>
                            <div class="overflow-x-auto rounded-2xl border border-gray-100">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-gray-50/80 text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-100">
                                        <tr>
                                            <th class="py-3.5 px-4" data-i18n="Cargo / Item Type">Cargo / Item Type</th>
                                            <th class="py-3.5 px-4 text-right" data-i18n="Weight (kg)">Weight (kg)</th>
                                            <th class="py-3.5 px-4 text-right" data-i18n="Rate / Price">Rate / Price</th>
                                            <th class="py-3.5 px-4 text-right" data-i18n="Line Total">Line Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 text-gray-700 font-medium">
                                        <?php
                                        $items_subtotal = 0;
                                        foreach ($breakdown_items as $item):
                                            $line_total = (float)$item['kg'] * (float)$item['price_per_kg'];
                                            $items_subtotal += $line_total;
                                        ?>
                                            <tr class="hover:bg-gray-50/50 transition-colors">
                                                <td class="py-3 px-4 font-semibold text-gray-900 flex items-center gap-2">
                                                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                                    <?= htmlspecialchars($item['item_type'], ENT_QUOTES, 'UTF-8') ?>
                                                </td>
                                                <td class="py-3 px-4 text-right tabular-nums">
                                                    <?= number_format((float)$item['kg'], 2) ?> <span class="text-xs text-gray-400">kg</span>
                                                </td>
                                                <td class="py-3 px-4 text-right tabular-nums">
                                                    <?= htmlspecialchars($voucher['currency'], ENT_QUOTES, 'UTF-8') ?> <?= number_format((float)$item['price_per_kg'], 2) ?>
                                                </td>
                                                <td class="py-3 px-4 text-right font-bold text-gray-900 tabular-nums">
                                                    <?= htmlspecialchars($voucher['currency'], ENT_QUOTES, 'UTF-8') ?> <?= number_format($line_total, 2) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="bg-gray-50/90 font-medium text-xs text-gray-600 border-t border-gray-200">
                                        <tr>
                                            <td colspan="3" class="py-2.5 px-4 text-right font-bold text-gray-500 uppercase tracking-wider" data-i18n="Items Subtotal">Items Subtotal</td>
                                            <td class="py-2.5 px-4 text-right font-bold text-gray-900 tabular-nums text-sm">
                                                <?= htmlspecialchars($voucher['currency'], ENT_QUOTES, 'UTF-8') ?> <?= number_format($items_subtotal, 2) ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" class="py-2 px-4 text-right font-bold text-gray-500 uppercase tracking-wider" data-i18n="Delivery Charge">Delivery Charge</td>
                                            <td class="py-2 px-4 text-right font-semibold text-gray-700 tabular-nums text-sm">
                                                <?= htmlspecialchars($voucher['currency'], ENT_QUOTES, 'UTF-8') ?> <?= number_format((float)$voucher['delivery_charge'], 2) ?>
                                            </td>
                                        </tr>
                                        <tr class="bg-indigo-50/60 text-indigo-900 font-extrabold text-sm border-t border-indigo-100">
                                            <td colspan="3" class="py-3.5 px-4 text-right uppercase tracking-wider text-xs text-indigo-700" data-i18n="Grand Total Due">Grand Total Due</td>
                                            <td class="py-3.5 px-4 text-right font-black text-indigo-700 text-base tabular-nums">
                                                <?= htmlspecialchars($voucher['currency'], ENT_QUOTES, 'UTF-8') ?> <?= number_format((float)$voucher['total_amount'], 2) ?>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50/50 p-6 text-center">
                                <p class="text-sm font-semibold text-gray-700" data-i18n="Consignment Summary">Consignment Summary</p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <span data-i18n="Total Weight:">Total Weight:</span> <strong><?= number_format((float)$voucher['weight_kg'], 2) ?> kg</strong> |
                                    <span data-i18n="Delivery Type:">Delivery Type:</span> <strong><?= htmlspecialchars($voucher['delivery_type'] ?? 'Standard', ENT_QUOTES, 'UTF-8') ?></strong>
                                </p>
                                <div class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-indigo-50 text-indigo-700 rounded-xl font-bold text-sm">
                                    <span data-i18n="Total Amount:">Total Amount:</span> <?= htmlspecialchars($voucher['currency'], ENT_QUOTES, 'UTF-8') ?> <?= number_format((float)$voucher['total_amount'], 2) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Conversation / Notes Timeline -->
                    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-[0_8px_30px_rgb(0,0,0,0.04)]">
                        <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2 border-b border-gray-100 pb-4">
                            <div class="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            </div>
                            <span data-i18n="Operational Timeline">Operational Timeline</span>
                        </h3>

                        <div class="space-y-6 mb-8 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                            <?php if (empty($chat_bubbles)): ?>
                                <div class="text-center py-6">
                                    <p class="text-gray-400 font-medium text-sm" data-i18n="No operational notes recorded yet.">No operational notes recorded yet.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($chat_bubbles as $bubble): ?>
                                    <div class="flex gap-4 group">
                                        <div class="shrink-0">
                                            <?php if ($bubble['is_legacy']): ?>
                                                <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 font-bold border-2 border-white shadow-sm">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/></svg>
                                                </div>
                                            <?php else: ?>
                                                <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-indigo-500 to-purple-500 flex items-center justify-center text-white font-bold text-sm border-2 border-white shadow-sm">
                                                    <?= strtoupper(substr($bubble['author'], 0, 2)) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-1 bg-gray-50 rounded-2xl rounded-tl-none px-5 py-4 border border-gray-100 shadow-sm">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="font-bold text-sm <?= $bubble['is_legacy'] ? 'text-gray-600' : 'text-indigo-600' ?>"><?= htmlspecialchars($bubble['author'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <span class="text-xs font-medium text-gray-400"><?= $bubble['time'] ?></span>
                                            </div>
                                            <p class="text-gray-700 text-sm leading-relaxed whitespace-pre-wrap"><?= htmlspecialchars($bubble['text'], ENT_QUOTES, 'UTF-8') ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Operational Update Form -->
                        <form action="index.php?page=voucher_view&id=<?= $voucher_id ?>" method="POST" accept-charset="UTF-8" class="bg-blue-50/50 p-5 sm:p-6 rounded-2xl border border-blue-100">
                            <?= csrf_input() ?>
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                                <div class="md:col-span-4 space-y-2">
                                    <label for="status" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1" data-i18n="Current Status">Current Status</label>
                                    <select id="status" name="status" class="w-full rounded-xl border-gray-200 bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all text-sm font-bold text-gray-700 py-3 shadow-sm appearance-none" required>
                                        <?php foreach($possible_statuses as $status): ?>
                                            <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>" <?= ($voucher['status'] === $status) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="md:col-span-8 space-y-2">
                                    <label for="new_note" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1" data-i18n="Add to Conversation">Add to Conversation</label>
                                    <textarea id="new_note" name="new_note" rows="2" class="w-full rounded-xl border-gray-200 bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all text-sm font-medium text-gray-800 py-3 px-4 shadow-sm" placeholder="Write an operational note... (Supports English & Myanmar)" data-i18n-placeholder="Write an operational note... (Supports English & Myanmar)"></textarea>
                                    <div class="quick-chips flex flex-wrap items-center gap-1.5 mt-2" aria-label="Quick note templates">
                                        <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mr-1" data-i18n="Quick Note:">Quick Note:</span>
                                        <button type="button" class="quick-note-chip text-xs bg-white hover:bg-indigo-50 text-indigo-700 font-semibold px-2.5 py-1 rounded-lg border border-indigo-200 transition-all shadow-sm" data-text="Customer contacted" data-i18n="Customer contacted">Customer contacted</button>
                                        <button type="button" class="quick-note-chip text-xs bg-white hover:bg-indigo-50 text-indigo-700 font-semibold px-2.5 py-1 rounded-lg border border-indigo-200 transition-all shadow-sm" data-text="Out for delivery" data-i18n="Out for delivery">Out for delivery</button>
                                        <button type="button" class="quick-note-chip text-xs bg-white hover:bg-indigo-50 text-indigo-700 font-semibold px-2.5 py-1 rounded-lg border border-indigo-200 transition-all shadow-sm" data-text="Address confirmed" data-i18n="Address confirmed">Address confirmed</button>
                                        <button type="button" class="quick-note-chip text-xs bg-white hover:bg-indigo-50 text-indigo-700 font-semibold px-2.5 py-1 rounded-lg border border-indigo-200 transition-all shadow-sm" data-text="Delayed by weather" data-i18n="Delayed by weather">Delayed by weather</button>
                                        <button type="button" class="quick-note-chip text-xs bg-white hover:bg-indigo-50 text-indigo-700 font-semibold px-2.5 py-1 rounded-lg border border-indigo-200 transition-all shadow-sm" data-text="Package inspected" data-i18n="Package inspected">Package inspected</button>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-5 flex justify-end">
                                <button type="submit" class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white py-2.5 px-6 rounded-xl font-bold text-sm hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-500/30 shadow-[0_8px_20px_rgb(79,70,229,0.2)] transition-all transform hover:-translate-y-0.5 active:translate-y-0 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                    <span data-i18n="Add Entry & Update">Add Entry & Update</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Elegant scrollbar for the conversation timeline */
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: rgba(243, 244, 246, 0.5);
        border-radius: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(199, 210, 254, 0.8);
        border-radius: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: rgba(129, 140, 248, 1);
    }
</style>

<script>
    // Auto-scroll conversation to bottom when page loads
    document.addEventListener("DOMContentLoaded", function() {
        const chatContainer = document.querySelector('.custom-scrollbar');
        if (chatContainer) {
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }

        // Quick Note Template Chips
        document.querySelectorAll('.quick-note-chip').forEach(function(chip) {
            chip.addEventListener('click', function(e) {
                e.preventDefault();
                const noteInput = document.getElementById('new_note');
                if (!noteInput) return;
                const text = this.getAttribute('data-text');
                if (noteInput.value.trim().length > 0) {
                    noteInput.value = noteInput.value.trim() + ' | ' + text;
                } else {
                    noteInput.value = text;
                }
                noteInput.focus();
            });
        });
    });
</script>

<?php
include_template('footer');
?>