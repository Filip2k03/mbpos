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

// --- Canonical V5 Status Badge Classes ---
$status_badge_class = match(strtolower($voucher['status'] ?? '')) {
    'delivered' => 'v5-badge-success',
    'received' => 'v5-badge-teal',
    'in transit' => 'v5-badge-info',
    'pending' => 'v5-badge-warning',
    'cancelled', 'returned' => 'v5-badge-danger',
    default => 'v5-badge-neutral'
};

$normalized_status = strtolower($voucher['status'] ?? 'pending');
$milestone_progress = match($normalized_status) {
    'pending' => 25,
    'in transit' => 50,
    'received' => 75,
    'delivered' => 100,
    default => 25
};
$is_cancelled_or_returned = in_array($normalized_status, ['cancelled', 'returned'], true);
$public_tracking_url = 'index.php?page=customer_voucher_view&id=' . (int)$voucher['id'];

include_template('header', ['page' => 'voucher_view']);
?>

<div class="v5-page">
    <!-- Header Section -->
    <div class="v5-page-head">
        <div class="v5-page-head__copy">
            <div class="v5-kicker" data-i18n="Logistics Records">Logistics Records</div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="font-mono text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                    #<?= e($voucher['voucher_code']) ?>
                </h1>
                <button type="button" class="btn-ghost btn-sm p-1.5 rounded-lg text-slate-400 hover:text-blue-600" title="Copy voucher code" data-copy="<?= e($voucher['voucher_code']) ?>" aria-label="Copy voucher code">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2" stroke-width="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" stroke-width="2"/></svg>
                </button>
                <span class="v5-badge <?= $status_badge_class ?>">
                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                    <span><?= e($voucher['status']) ?></span>
                </span>
                <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-800">
                    <?= e($voucher['delivery_type'] ?? 'Standard') ?>
                </span>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 font-medium flex flex-wrap items-center gap-2 mt-1.5">
                <span><span data-i18n="Issued by">Issued by</span> <strong class="text-slate-800"><?= e($voucher['created_by_username'] ?? 'System') ?></strong> (<?= e($voucher['origin_branch_name'] ?? 'N/A') ?>)</span>
                <span class="text-slate-300">•</span>
                <span class="font-mono text-slate-700 bg-slate-100 px-2 py-0.5 rounded text-xs"><?= format_datetime_myanmar($voucher['created_at'], 'full') ?></span>
                <span class="text-xs font-semibold text-slate-500">(<?= format_datetime_myanmar($voucher['created_at'], 'relative') ?>)</span>
            </p>
        </div>

        <!-- Action Buttons -->
        <div class="v5-page-actions flex flex-wrap items-center gap-2">
            <a href="index.php?page=voucher_list" class="btn-ghost btn-sm inline-flex items-center gap-1.5" title="Return to Ledger (Esc)" data-i18n-title="Back to Ledger">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span data-i18n="Back to Ledger">Back to Ledger</span>
            </a>
            <button type="button" class="btn-secondary btn-sm inline-flex items-center gap-1.5" data-copy="<?= e($public_tracking_url) ?>" title="Copy Public Tracking Link" data-i18n-title="Copy Tracking Link">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                <span data-i18n="Copy Tracking Link">Copy Tracking Link</span>
            </button>
            <button type="button" id="btn-share-summary" class="btn-secondary btn-sm inline-flex items-center gap-1.5" title="Copy formatted shipment summary to clipboard" data-i18n-title="Share Summary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                <span data-i18n="Share Summary">Share Summary</span>
            </button>
            <a href="index.php?page=voucher_create&duplicate_id=<?= (int)$voucher['id'] ?>" class="btn-secondary btn-sm inline-flex items-center gap-1.5" title="Duplicate this consignment" data-i18n-title="Duplicate as New">
                <?= mbpos_icon('voucher_create', 'w-4 h-4') ?>
                <span data-i18n="Duplicate as New">Duplicate as New</span>
            </a>
            <a href="voucher_print.php?id=<?= (int)$voucher['id'] ?>" target="_blank" rel="noopener noreferrer" class="btn-primary btn-sm inline-flex items-center gap-1.5" title="Print Waybill (Ctrl+P)" data-i18n-title="Print Waybill">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span data-i18n="Print Waybill">Print Waybill</span>
            </a>
        </div>
    </div>

    <!-- Visual Milestone Logistics Pipeline -->
    <section class="v5-panel">
        <div class="v5-panel__head">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-blue-600 animate-pulse"></span>
                <h2 data-i18n="Logistics Status Pipeline">Logistics Status Pipeline</h2>
            </div>
            <span class="v5-badge <?= $status_badge_class ?>">
                <?= e($voucher['status']) ?>
            </span>
        </div>
        <div class="v5-panel__body">
            <?php if ($is_cancelled_or_returned): ?>
                <div class="p-4 rounded-xl border border-red-200 bg-red-50/70 text-red-800 flex items-center gap-3">
                    <svg class="w-6 h-6 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <div>
                        <strong class="block font-bold text-sm" data-i18n="Consignment Status Alert">Consignment Status Alert</strong>
                        <p class="text-xs text-red-700 mt-0.5">This shipment has been marked as <strong><?= e($voucher['status']) ?></strong>. Please review the operational timeline below for details.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="v5-pipeline-track py-3 px-2 overflow-x-auto">
                    <div class="relative min-w-[560px] flex items-center justify-between">
                        <!-- Progress Background Bar -->
                        <div class="absolute top-1/2 left-8 right-8 h-1.5 -translate-y-1/2 bg-slate-200 rounded-full z-0">
                            <div class="h-full bg-gradient-to-r from-blue-600 via-indigo-600 to-emerald-600 rounded-full transition-all duration-500" style="width: <?= $milestone_progress ?>%;"></div>
                        </div>

                        <!-- Milestone 1: Registered -->
                        <div class="relative z-10 flex flex-col items-center text-center">
                            <div class="w-11 h-11 rounded-full flex items-center justify-center font-bold text-sm shadow-sm transition-all <?= $milestone_progress >= 25 ? 'bg-blue-600 text-white ring-4 ring-blue-100' : 'bg-white text-slate-400 border-2 border-slate-300' ?>">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            </div>
                            <span class="text-xs font-bold text-slate-800 mt-2" data-i18n="Registered">Registered</span>
                            <small class="text-[11px] text-slate-500 font-medium"><?= e($voucher['origin_branch_name'] ?? 'Origin') ?></small>
                        </div>

                        <!-- Milestone 2: In Transit -->
                        <div class="relative z-10 flex flex-col items-center text-center">
                            <div class="w-11 h-11 rounded-full flex items-center justify-center font-bold text-sm shadow-sm transition-all <?= $milestone_progress >= 50 ? 'bg-indigo-600 text-white ring-4 ring-indigo-100' : 'bg-white text-slate-400 border-2 border-slate-300' ?>">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
                            </div>
                            <span class="text-xs font-bold text-slate-800 mt-2" data-i18n="In Transit">In Transit</span>
                            <small class="text-[11px] text-slate-500 font-medium"><?= e($voucher['origin_region_name'] ?? '') ?> → <?= e($voucher['destination_region_name'] ?? '') ?></small>
                        </div>

                        <!-- Milestone 3: Received at Hub -->
                        <div class="relative z-10 flex flex-col items-center text-center">
                            <div class="w-11 h-11 rounded-full flex items-center justify-center font-bold text-sm shadow-sm transition-all <?= $milestone_progress >= 75 ? 'bg-teal-600 text-white ring-4 ring-teal-100' : 'bg-white text-slate-400 border-2 border-slate-300' ?>">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            </div>
                            <span class="text-xs font-bold text-slate-800 mt-2" data-i18n="Received at Hub">Received at Hub</span>
                            <small class="text-[11px] text-slate-500 font-medium"><?= e($voucher['destination_branch_name'] ?? 'Dest Hub') ?></small>
                        </div>

                        <!-- Milestone 4: Delivered -->
                        <div class="relative z-10 flex flex-col items-center text-center">
                            <div class="w-11 h-11 rounded-full flex items-center justify-center font-bold text-sm shadow-sm transition-all <?= $milestone_progress >= 100 ? 'bg-emerald-600 text-white ring-4 ring-emerald-100' : 'bg-white text-slate-400 border-2 border-slate-300' ?>">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <span class="text-xs font-bold text-slate-800 mt-2" data-i18n="Delivered">Delivered</span>
                            <small class="text-[11px] text-slate-500 font-medium"><?= e($voucher['receiver_name']) ?></small>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Quick Status Switcher for Operators -->
            <div class="mt-4 pt-3 border-t border-slate-100 flex flex-wrap items-center gap-2">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider mr-1" data-i18n="Quick Status:">Quick Status:</span>
                <?php foreach(['Pending', 'In Transit', 'Received', 'Delivered', 'Returned', 'Cancelled'] as $qs_opt):
                    if ($voucher['status'] === $qs_opt) continue;
                ?>
                    <button type="button" class="quick-status-chip text-xs px-2.5 py-1 rounded-lg font-semibold border border-slate-200 bg-white hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700 transition-all shadow-sm" data-status="<?= e($qs_opt) ?>">
                        <?= e($qs_opt) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Parties & Specifications Grid (3 Columns) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <!-- Sender Information Card -->
        <div class="v5-panel">
            <div class="v5-panel__head">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center font-bold">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-blue-600 uppercase tracking-wider block" data-i18n="Origin Hub">Origin Hub</span>
                        <h3 class="text-sm font-bold text-slate-900" data-i18n="Sender Details">Sender Details</h3>
                    </div>
                </div>
            </div>
            <div class="v5-panel__body space-y-3 text-sm">
                <div>
                    <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block" data-i18n="Full Name">Full Name</label>
                    <strong class="text-slate-900 text-base block font-bold"><?= e($voucher['sender_name']) ?></strong>
                </div>
                <div>
                    <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block" data-i18n="Phone Number">Phone Number</label>
                    <div class="flex items-center gap-2 mt-0.5">
                        <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $voucher['sender_phone'])) ?>" class="font-mono font-bold text-blue-600 hover:text-blue-800 hover:underline inline-flex items-center gap-1.5" title="Call Sender" data-i18n-title="Call Sender">
                            <span><?= e($voucher['sender_phone']) ?></span>
                            <svg class="w-3.5 h-3.5 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        </a>
                        <button type="button" class="text-slate-400 hover:text-blue-600 p-1 rounded" title="Copy Phone Number" data-copy="<?= e($voucher['sender_phone']) ?>" aria-label="Copy phone number">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2" stroke-width="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" stroke-width="2"/></svg>
                        </button>
                    </div>
                </div>
                <div class="pt-2 border-t border-slate-100">
                    <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block" data-i18n="Origin Point">Origin Point</label>
                    <div class="text-xs font-semibold text-slate-700 mt-0.5">
                        <span><?= e($voucher['origin_region_name'] ?? 'N/A') ?></span> · <span><?= e($voucher['origin_branch_name'] ?? 'N/A') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Receiver Information Card -->
        <div class="v5-panel">
            <div class="v5-panel__head">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center font-bold">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider block" data-i18n="Destination Node">Destination Node</span>
                        <h3 class="text-sm font-bold text-slate-900" data-i18n="Receiver Details">Receiver Details</h3>
                    </div>
                </div>
            </div>
            <div class="v5-panel__body space-y-3 text-sm">
                <div>
                    <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block" data-i18n="Full Name">Full Name</label>
                    <strong class="text-slate-900 text-base block font-bold"><?= e($voucher['receiver_name']) ?></strong>
                </div>
                <div>
                    <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block" data-i18n="Phone Number">Phone Number</label>
                    <div class="flex items-center gap-2 mt-0.5">
                        <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $voucher['receiver_phone'])) ?>" class="font-mono font-bold text-emerald-600 hover:text-emerald-800 hover:underline inline-flex items-center gap-1.5" title="Call Receiver" data-i18n-title="Call Receiver">
                            <span><?= e($voucher['receiver_phone']) ?></span>
                            <svg class="w-3.5 h-3.5 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        </a>
                        <button type="button" class="text-slate-400 hover:text-emerald-600 p-1 rounded" title="Copy Phone Number" data-copy="<?= e($voucher['receiver_phone']) ?>" aria-label="Copy phone number">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2" stroke-width="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" stroke-width="2"/></svg>
                        </button>
                    </div>
                </div>
                <div class="pt-2 border-t border-slate-100">
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block" data-i18n="Delivery Address">Delivery Address</label>
                        <button type="button" class="text-slate-400 hover:text-emerald-600 p-0.5 rounded" title="Copy Address" data-copy="<?= e($voucher['receiver_address']) ?>" aria-label="Copy address">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2" stroke-width="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" stroke-width="2"/></svg>
                        </button>
                    </div>
                    <p class="text-xs text-slate-700 font-medium leading-relaxed break-words bg-slate-50/70 p-2.5 rounded-lg border border-slate-100"><?= nl2br(e($voucher['receiver_address'])) ?></p>
                </div>
            </div>
        </div>

        <!-- Consignment Specifications & Barcode Card -->
        <div class="v5-panel md:col-span-2 lg:col-span-1">
            <div class="v5-panel__head">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-indigo-600 uppercase tracking-wider block" data-i18n="Consignment Specs">Consignment Specs</span>
                        <h3 class="text-sm font-bold text-slate-900" data-i18n="Pricing & Weight">Pricing & Weight</h3>
                    </div>
                </div>
            </div>
            <div class="v5-panel__body space-y-3 text-sm">
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100 text-center">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block" data-i18n="Total Weight">Total Weight</span>
                        <strong class="text-base font-black text-indigo-600 font-mono mt-0.5 block">
                            <?= number_format((float)$voucher['weight_kg'], 2) ?> <span class="text-xs font-sans text-slate-500">kg</span>
                        </strong>
                    </div>
                    <div class="bg-indigo-50/50 p-2.5 rounded-xl border border-indigo-100 text-center">
                        <span class="text-[10px] font-bold text-indigo-500 uppercase tracking-wider block" data-i18n="Total Due">Total Due</span>
                        <strong class="text-base font-black text-indigo-700 font-mono mt-0.5 block">
                            <span class="text-xs"><?= e($voucher['currency']) ?></span> <?= number_format((float)$voucher['total_amount'], 2) ?>
                        </strong>
                    </div>
                </div>

                <!-- Visual Barcode Representation -->
                <div class="p-3 bg-white border border-slate-200 rounded-xl flex flex-col items-center justify-center">
                    <svg class="w-full max-w-[220px] h-9 text-slate-800" viewBox="0 0 160 36" fill="currentColor" aria-hidden="true">
                        <rect x="5" y="2" width="3" height="32"/>
                        <rect x="11" y="2" width="1.5" height="32"/>
                        <rect x="15" y="2" width="4" height="32"/>
                        <rect x="22" y="2" width="2" height="32"/>
                        <rect x="27" y="2" width="1" height="32"/>
                        <rect x="31" y="2" width="3.5" height="32"/>
                        <rect x="37" y="2" width="2" height="32"/>
                        <rect x="42" y="2" width="4" height="32"/>
                        <rect x="49" y="2" width="1.5" height="32"/>
                        <rect x="53" y="2" width="3" height="32"/>
                        <rect x="59" y="2" width="2" height="32"/>
                        <rect x="64" y="2" width="4.5" height="32"/>
                        <rect x="71" y="2" width="1.5" height="32"/>
                        <rect x="75" y="2" width="3" height="32"/>
                        <rect x="81" y="2" width="2" height="32"/>
                        <rect x="86" y="2" width="4" height="32"/>
                        <rect x="93" y="2" width="1" height="32"/>
                        <rect x="97" y="2" width="3.5" height="32"/>
                        <rect x="103" y="2" width="2" height="32"/>
                        <rect x="108" y="2" width="4" height="32"/>
                        <rect x="115" y="2" width="1.5" height="32"/>
                        <rect x="119" y="2" width="3" height="32"/>
                        <rect x="125" y="2" width="2" height="32"/>
                        <rect x="130" y="2" width="4.5" height="32"/>
                        <rect x="137" y="2" width="1.5" height="32"/>
                        <rect x="141" y="2" width="3" height="32"/>
                        <rect x="147" y="2" width="2" height="32"/>
                        <rect x="152" y="2" width="3.5" height="32"/>
                    </svg>
                    <span class="font-mono text-xs font-bold text-slate-600 mt-1 tracking-widest"><?= e($voucher['voucher_code']) ?></span>
                </div>

                <div class="text-[11px] text-slate-400 text-center">
                    <span>Voucher Protocol ID: #<?= (int)$voucher['id'] ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Cargo Breakdown & Financial Ledger -->
    <section class="v5-panel">
        <div class="v5-panel__head">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                <h2 data-i18n="Cargo Breakdown & Pricing">Cargo Breakdown & Pricing</h2>
            </div>
            <span class="v5-count">
                <?= count($breakdown_items) ?> <span data-i18n="item(s)">item(s)</span>
            </span>
        </div>

        <div class="v5-panel__body p-0">
            <?php if (!empty($breakdown_items)): ?>
                <div class="overflow-x-auto">
                    <table class="v5-table w-full">
                        <thead>
                            <tr>
                                <th class="w-12 text-center">#</th>
                                <th data-i18n="Cargo / Item Type">Cargo / Item Type</th>
                                <th class="text-right" data-i18n="Weight (kg)">Weight (kg)</th>
                                <th class="text-right" data-i18n="Rate / Price">Rate / Price</th>
                                <th class="text-right" data-i18n="Line Total">Line Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $items_subtotal = 0;
                            $row_idx = 1;
                            foreach ($breakdown_items as $item):
                                $line_total = (float)$item['kg'] * (float)$item['price_per_kg'];
                                $items_subtotal += $line_total;
                            ?>
                                <tr>
                                    <td class="text-center font-mono text-xs text-slate-400"><?= $row_idx++ ?></td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                            <strong class="text-slate-800"><?= e($item['item_type']) ?></strong>
                                        </div>
                                    </td>
                                    <td class="text-right font-mono font-semibold text-slate-700">
                                        <?= number_format((float)$item['kg'], 2) ?> <span class="text-xs text-slate-400 font-sans">kg</span>
                                    </td>
                                    <td class="text-right font-mono text-slate-600">
                                        <span class="text-xs text-slate-400"><?= e($voucher['currency']) ?></span> <?= number_format((float)$item['price_per_kg'], 2) ?>
                                    </td>
                                    <td class="text-right font-mono font-bold text-slate-900">
                                        <span class="text-xs text-slate-400"><?= e($voucher['currency']) ?></span> <?= number_format($line_total, 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-slate-50 border-t border-slate-200">
                            <tr>
                                <td colspan="4" class="py-2 px-4 text-right font-bold text-slate-500 uppercase tracking-wider text-xs" data-i18n="Items Subtotal">Items Subtotal</td>
                                <td class="py-2 px-4 text-right font-bold text-slate-800 font-mono text-sm">
                                    <span class="text-xs text-slate-400"><?= e($voucher['currency']) ?></span> <?= number_format($items_subtotal, 2) ?>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="4" class="py-2 px-4 text-right font-bold text-slate-500 uppercase tracking-wider text-xs" data-i18n="Delivery Charge">Delivery Charge</td>
                                <td class="py-2 px-4 text-right font-semibold text-slate-700 font-mono text-sm">
                                    <span class="text-xs text-slate-400"><?= e($voucher['currency']) ?></span> <?= number_format((float)$voucher['delivery_charge'], 2) ?>
                                </td>
                            </tr>
                            <tr class="bg-indigo-50/70 border-t border-indigo-100">
                                <td colspan="4" class="py-3 px-4 text-right font-black text-indigo-800 uppercase tracking-wider text-xs" data-i18n="Grand Total Due">Grand Total Due</td>
                                <td class="py-3 px-4 text-right font-black text-indigo-700 font-mono text-base">
                                    <span><?= e($voucher['currency']) ?></span> <?= number_format((float)$voucher['total_amount'], 2) ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-6 text-center">
                    <p class="text-sm font-semibold text-slate-700" data-i18n="Consignment Summary">Consignment Summary</p>
                    <p class="text-xs text-slate-500 mt-1">
                        <span data-i18n="Total Weight:">Total Weight:</span> <strong><?= number_format((float)$voucher['weight_kg'], 2) ?> kg</strong> |
                        <span data-i18n="Delivery Type:">Delivery Type:</span> <strong><?= e($voucher['delivery_type'] ?? 'Standard') ?></strong>
                    </p>
                    <div class="mt-3 inline-flex items-center gap-2 px-4 py-2 bg-indigo-50 text-indigo-700 rounded-xl font-bold text-sm">
                        <span data-i18n="Total Amount:">Total Amount:</span> <?= e($voucher['currency']) ?> <?= number_format((float)$voucher['total_amount'], 2) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Operational Conversation & Update Timeline -->
    <section class="v5-panel">
        <div class="v5-panel__head">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                <h2 data-i18n="Operational Timeline">Operational Timeline</h2>
            </div>
            <span class="v5-count"><?= count($chat_bubbles) ?> <span data-i18n="notes">notes</span></span>
        </div>

        <div class="v5-panel__body">
            <!-- Timeline Stream -->
            <div class="space-y-4 mb-6 max-h-[380px] overflow-y-auto pr-2 custom-scrollbar" id="chat-timeline">
                <?php if (empty($chat_bubbles)): ?>
                    <div class="v5-empty py-8">
                        <p class="text-sm font-medium text-slate-400" data-i18n="No operational notes recorded yet.">No operational notes recorded yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($chat_bubbles as $bubble): ?>
                        <div class="flex gap-3">
                            <div class="shrink-0">
                                <?php if ($bubble['is_legacy']): ?>
                                    <div class="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 font-bold text-xs border border-slate-200">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/></svg>
                                    </div>
                                <?php else: ?>
                                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center text-white font-bold text-xs shadow-sm">
                                        <?= strtoupper(substr($bubble['author'], 0, 2)) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1 bg-slate-50 rounded-2xl p-3.5 border border-slate-100 shadow-sm">
                                <div class="flex items-center justify-between mb-1.5">
                                    <strong class="font-bold text-xs <?= $bubble['is_legacy'] ? 'text-slate-600' : 'text-indigo-600' ?>"><?= e($bubble['author']) ?></strong>
                                    <div class="flex items-center gap-1.5 text-[11px] text-slate-400 font-mono">
                                        <span><?= $bubble['time'] ?></span>
                                        <span class="text-[10px] px-1 py-0.2 bg-white rounded border border-slate-200 text-slate-500 font-sans"><?= $bubble['relative'] ?></span>
                                    </div>
                                </div>
                                <p class="text-slate-700 text-xs sm:text-sm leading-relaxed whitespace-pre-wrap"><?= e($bubble['text']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Ledger Action Form -->
            <form action="index.php?page=voucher_view&id=<?= $voucher_id ?>" method="POST" accept-charset="UTF-8" id="form-update-ledger" class="bg-slate-50/80 p-4 sm:p-5 rounded-2xl border border-slate-200">
                <?= csrf_input() ?>
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-4 space-y-1.5">
                        <label for="status" class="v5-field-label" data-i18n="Current Status">Current Status</label>
                        <select id="status" name="status" class="v5-input w-full font-bold" required>
                            <?php foreach($possible_statuses as $status): ?>
                                <option value="<?= e($status) ?>" <?= ($voucher['status'] === $status) ? 'selected' : '' ?>>
                                    <?= e($status) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="md:col-span-8 space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label for="new_note" class="v5-field-label" data-i18n="Add to Conversation">Add to Conversation</label>
                            <span class="text-[11px] text-slate-400 font-medium hidden sm:inline" data-i18n="Press Ctrl+Enter to submit">Press Ctrl+Enter to submit</span>
                        </div>
                        <textarea id="new_note" name="new_note" rows="2" class="v5-input w-full" placeholder="Write an operational note... (Supports English & Myanmar)" data-i18n-placeholder="Write an operational note... (Supports English & Myanmar)"></textarea>

                        <!-- Quick Note Templates -->
                        <div class="quick-chips flex flex-wrap items-center gap-1.5 pt-1" aria-label="Quick note templates">
                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mr-1" data-i18n="Quick Note:">Quick Note:</span>
                            <button type="button" class="quick-note-chip text-xs bg-white hover:bg-blue-50 text-blue-700 font-semibold px-2.5 py-1 rounded-lg border border-slate-200 hover:border-blue-200 transition-all shadow-sm" data-text="Customer contacted" data-i18n="Customer contacted">Customer contacted</button>
                            <button type="button" class="quick-note-chip text-xs bg-white hover:bg-blue-50 text-blue-700 font-semibold px-2.5 py-1 rounded-lg border border-slate-200 hover:border-blue-200 transition-all shadow-sm" data-text="Out for delivery" data-i18n="Out for delivery">Out for delivery</button>
                            <button type="button" class="quick-note-chip text-xs bg-white hover:bg-blue-50 text-blue-700 font-semibold px-2.5 py-1 rounded-lg border border-slate-200 hover:border-blue-200 transition-all shadow-sm" data-text="Address confirmed" data-i18n="Address confirmed">Address confirmed</button>
                            <button type="button" class="quick-note-chip text-xs bg-white hover:bg-blue-50 text-blue-700 font-semibold px-2.5 py-1 rounded-lg border border-slate-200 hover:border-blue-200 transition-all shadow-sm" data-text="Delayed by weather" data-i18n="Delayed by weather">Delayed by weather</button>
                            <button type="button" class="quick-note-chip text-xs bg-white hover:bg-blue-50 text-blue-700 font-semibold px-2.5 py-1 rounded-lg border border-slate-200 hover:border-blue-200 transition-all shadow-sm" data-text="Package inspected" data-i18n="Package inspected">Package inspected</button>
                            <button type="button" class="quick-note-chip text-xs bg-white hover:bg-blue-50 text-blue-700 font-semibold px-2.5 py-1 rounded-lg border border-slate-200 hover:border-blue-200 transition-all shadow-sm" data-text="Payment collected" data-i18n="Payment collected">Payment collected</button>
                            <button type="button" class="quick-note-chip text-xs bg-white hover:bg-blue-50 text-blue-700 font-semibold px-2.5 py-1 rounded-lg border border-slate-200 hover:border-blue-200 transition-all shadow-sm" data-text="Delivered & signed" data-i18n="Delivered & signed">Delivered & signed</button>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <button type="submit" class="btn-primary inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        <span data-i18n="Add Entry & Update">Add Entry & Update</span>
                    </button>
                </div>
            </form>
        </div>
    </section>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Auto-scroll conversation to bottom
    const chatContainer = document.getElementById('chat-timeline');
    if (chatContainer) {
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }

    // 2. Quick Note Template Chips
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

    // 3. Quick Status Action Chips
    document.querySelectorAll('.quick-status-chip').forEach(function(chip) {
        chip.addEventListener('click', function(e) {
            e.preventDefault();
            const statusSelect = document.getElementById('status');
            const noteInput = document.getElementById('new_note');
            const newStatus = this.getAttribute('data-status');
            if (statusSelect) {
                statusSelect.value = newStatus;
            }
            if (noteInput && noteInput.value.trim().length === 0) {
                noteInput.value = 'Status changed to ' + newStatus;
            }
            noteInput?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => noteInput?.focus(), 250);
        });
    });

    // 4. Share Summary Clipboard Action
    const shareBtn = document.getElementById('btn-share-summary');
    if (shareBtn) {
        shareBtn.addEventListener('click', function() {
            const summaryText = [
                "MBLOGISTICS Waybill #<?= addslashes($voucher['voucher_code']) ?>",
                "Sender: <?= addslashes($voucher['sender_name']) ?> (<?= addslashes($voucher['sender_phone']) ?>)",
                "Receiver: <?= addslashes($voucher['receiver_name']) ?> (<?= addslashes($voucher['receiver_phone']) ?>)",
                "Route: <?= addslashes($voucher['origin_region_name'] ?? '') ?> -> <?= addslashes($voucher['destination_region_name'] ?? '') ?>",
                "Destination Address: <?= addslashes(preg_replace('/\s+/', ' ', trim($voucher['receiver_address']))) ?>",
                "Weight: <?= number_format((float)$voucher['weight_kg'], 2) ?> kg",
                "Total Due: <?= addslashes($voucher['currency']) ?> <?= number_format((float)$voucher['total_amount'], 2) ?>",
                "Status: <?= addslashes($voucher['status']) ?>"
            ].join("\n");

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(summaryText).then(function() {
                    if (window.Toastify) {
                        Toastify({
                            text: "Voucher summary copied to clipboard.",
                            duration: 2500,
                            gravity: "bottom",
                            position: "right",
                            style: { background: "#0b6ff5", borderRadius: "10px" }
                        }).showToast();
                    } else {
                        alert("Voucher summary copied to clipboard.");
                    }
                });
            } else {
                const ta = document.createElement("textarea");
                ta.value = summaryText;
                ta.style.position = "fixed";
                ta.style.left = "-9999px";
                document.body.appendChild(ta);
                ta.select();
                document.execCommand("copy");
                document.body.removeChild(ta);
                if (window.Toastify) {
                    Toastify({
                        text: "Voucher summary copied to clipboard.",
                        duration: 2500,
                        gravity: "bottom",
                        position: "right",
                        style: { background: "#0b6ff5", borderRadius: "10px" }
                    }).showToast();
                }
            }
        });
    }

    // 5. Global Keyboard Shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+P or Cmd+P to open Waybill Print
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'p') {
            e.preventDefault();
            window.open('voucher_print.php?id=<?= (int)$voucher['id'] ?>', '_blank');
        }
        // Esc to go back to ledger
        if (e.key === 'Escape') {
            window.location.href = 'index.php?page=voucher_list';
        }
        // Ctrl+Enter or Cmd+Enter to submit ledger note form
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            const updateForm = document.getElementById('form-update-ledger');
            if (updateForm) {
                e.preventDefault();
                updateForm.requestSubmit();
            }
        }
    });
});
</script>

<?php
include_template('footer');
?>