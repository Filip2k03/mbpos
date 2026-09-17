<?php
// pos/status_edit.php - Handles updating the status and operational ledger of a specific voucher in POS V5.

global $connection;

if (!is_logged_in()) {
    flash_message('error', 'Please log in to edit voucher status.');
    redirect('index.php?page=login');
}

// Force UTF-8 mb4 for full Burmese Unicode support
mysqli_set_charset($connection, "utf8mb4");

$user_id = $_SESSION['user_id'];
$is_elevated = is_admin() || is_developer();

$voucher_id = intval($_GET['id'] ?? 0);

if ($voucher_id <= 0) {
    flash_message('error', 'Invalid voucher ID.');
    redirect('index.php?page=voucher_list');
}

// Canonical POS V5 Voucher Statuses
$possible_statuses = ['Pending', 'In Transit', 'Received', 'Delivered', 'Cancelled', 'Returned', 'Maintenance'];

// Fetch voucher details for display and update
$sql = "SELECT v.id, v.voucher_code, v.sender_name, v.sender_phone, v.receiver_name, v.receiver_phone,
               v.status, v.notes, v.created_at, v.created_by_user_id,
               r_origin.region_name AS origin_region_name,
               r_dest.region_name AS destination_region_name
        FROM vouchers v
        LEFT JOIN regions r_origin ON v.region_id = r_origin.id
        LEFT JOIN regions r_dest ON v.destination_region_id = r_dest.id
        WHERE v.id = ?";

if (!$is_elevated) {
    $sql .= " AND v.created_by_user_id = ?";
}

$stmt = mysqli_prepare($connection, $sql);
$voucher = null;

if ($stmt) {
    if (!$is_elevated) {
        mysqli_stmt_bind_param($stmt, 'ii', $voucher_id, $user_id);
    } else {
        mysqli_stmt_bind_param($stmt, 'i', $voucher_id);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $voucher = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    mysqli_stmt_close($stmt);
} else {
    error_log('MBPOS status query failed: ' . mysqli_error($connection));
    flash_message('error', 'Unable to load shipment status right now.');
    redirect('index.php?page=voucher_list');
}

if (!$voucher) {
    flash_message('error', 'Voucher not found or you do not have permission to edit it.');
    redirect('index.php?page=voucher_list');
}

// --- Handle POST request (Status & Notes Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_request();
    $new_status = trim($_POST['status'] ?? '');
    $new_remark = trim($_POST['new_remark'] ?? '');
    $full_notes_override = isset($_POST['notes_override']) ? trim($_POST['notes_override']) : null;

    $errors = [];
    if (!in_array($new_status, $possible_statuses, true)) {
        $errors[] = 'Invalid status selected.';
    }

    // Require reason note if cancelling or returning
    if (in_array($new_status, ['Cancelled', 'Returned'], true) && empty($new_remark) && empty($full_notes_override)) {
        $errors[] = 'Please provide an operational remark explaining why this shipment is being cancelled or returned.';
    }

    if (!empty($errors)) {
        flash_message('error', implode('<br>', $errors));
    } else {
        $final_notes = $voucher['notes'] ?? '';

        if ($full_notes_override !== null && $is_elevated) {
            $final_notes = $full_notes_override;
        } elseif (!empty($new_remark)) {
            $author = $_SESSION['username'] ?? 'Staff';
            $date = date('Y-m-d H:i:s');
            $entry = "[[{$author} @ {$date}]]\n{$new_remark}";

            if (empty(trim($final_notes))) {
                $final_notes = $entry;
            } else {
                $final_notes = $final_notes . "\n\n===SPLIT===\n\n" . $entry;
            }
        }

        $update_sql = "UPDATE vouchers SET status = ?, notes = ? WHERE id = ?";
        if (!$is_elevated) {
            $update_sql .= " AND created_by_user_id = ?";
        }

        $stmt_update = mysqli_prepare($connection, $update_sql);

        if ($stmt_update) {
            if (!$is_elevated) {
                mysqli_stmt_bind_param($stmt_update, 'ssii', $new_status, $final_notes, $voucher_id, $user_id);
            } else {
                mysqli_stmt_bind_param($stmt_update, 'ssi', $new_status, $final_notes, $voucher_id);
            }

            if (mysqli_stmt_execute($stmt_update)) {
                flash_message('success', 'Voucher status and operational ledger updated successfully.');
                redirect('index.php?page=voucher_view&id=' . $voucher_id);
            } else {
                flash_message('error', 'Failed to update voucher status: ' . mysqli_stmt_error($stmt_update));
            }
            mysqli_stmt_close($stmt_update);
        } else {
            error_log('MBPOS status update prepare failed: ' . mysqli_error($connection));
            flash_message('error', 'Unable to update shipment status right now.');
        }
    }
}

// Parse existing notes history for reference
$raw_notes = $voucher['notes'] ?? '';
$history_entries = [];
if (!empty(trim($raw_notes))) {
    $blocks = explode("===SPLIT===", $raw_notes);
    foreach ($blocks as $block) {
        $block = trim($block);
        if (empty($block)) continue;
        if (preg_match('/^\[\[(.*?) @ (.*?)\]\]\s*(.*)$/s', $block, $matches)) {
            $history_entries[] = [
                'author' => $matches[1],
                'time'   => $matches[2],
                'text'   => $matches[3]
            ];
        } else {
            $history_entries[] = [
                'author' => 'System Note',
                'time'   => '',
                'text'   => $block
            ];
        }
    }
}

include_template('header', ['page' => 'status_edit']);
?>

<div class="v5-page">
    <!-- Page Header -->
    <div class="v5-page-head">
        <div class="v5-page-head__copy">
            <span class="v5-kicker" data-i18n="Logistics Operations">Logistics Operations</span>
            <h1 data-i18n="Update Voucher Status">Update Voucher Status</h1>
            <p data-i18n="Update tracking milestone, dispatch status, and operational notes">Transition tracking milestones and record timestamped operational ledger remarks.</p>
        </div>
        <div class="v5-page-actions flex flex-wrap items-center gap-2.5">
            <a href="index.php?page=voucher_view&id=<?= $voucher_id ?>" class="btn-ghost btn-sm inline-flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span data-i18n="Back to Voucher">Back to Voucher</span>
            </a>
            <a href="index.php?page=voucher_list" class="btn-secondary btn-sm inline-flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                <span data-i18n="Voucher Ledger">Voucher Ledger</span>
            </a>
        </div>
    </div>

    <!-- Main Content Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- Left: Voucher Snapshot Dossier (5 cols) -->
        <div class="lg:col-span-5 space-y-6">
            <section class="v5-panel">
                <div class="v5-panel__head">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <h2 class="font-bold text-sm text-slate-800" data-i18n="Shipment Dossier">Shipment Dossier</h2>
                    </div>
                    <span class="font-mono text-xs font-bold text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-200"><?= e($voucher['voucher_code']) ?></span>
                </div>

                <div class="v5-panel__body space-y-4 text-sm">
                    <!-- Origin -> Destination -->
                    <div class="bg-slate-50 rounded-xl p-3.5 border border-slate-200/80">
                        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1" data-i18n="Transit Corridor">Transit Corridor</div>
                        <div class="flex items-center justify-between font-semibold text-slate-800">
                            <span><?= e($voucher['origin_region_name'] ?? 'N/A') ?></span>
                            <svg class="w-4 h-4 text-blue-500 mx-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            <span><?= e($voucher['destination_region_name'] ?? 'N/A') ?></span>
                        </div>
                    </div>

                    <!-- Current Status Callout -->
                    <div class="flex items-center justify-between py-2 border-b border-slate-100">
                        <span class="text-slate-500 text-xs font-semibold" data-i18n="Current Status">Current Status</span>
                        <?php
                            $curr_st = strtolower($voucher['status']);
                            $badge_cls = match($curr_st) {
                                'pending' => 'bg-amber-50 text-amber-800 border-amber-200',
                                'in transit' => 'bg-blue-50 text-blue-800 border-blue-200',
                                'received' => 'bg-indigo-50 text-indigo-800 border-indigo-200',
                                'delivered' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                'cancelled' => 'bg-rose-50 text-rose-800 border-rose-200',
                                'returned' => 'bg-purple-50 text-purple-800 border-purple-200',
                                default => 'bg-slate-100 text-slate-800 border-slate-200'
                            };
                        ?>
                        <span class="v5-badge <?= $badge_cls ?> font-bold text-xs" id="badge-current-status">
                            <?= e($voucher['status']) ?>
                        </span>
                    </div>

                    <!-- Parties Details -->
                    <div class="space-y-3 pt-1">
                        <div class="p-3 bg-slate-50/70 rounded-xl border border-slate-200/60">
                            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1" data-i18n="Sender">Sender</div>
                            <div class="font-bold text-slate-800"><?= e($voucher['sender_name']) ?></div>
                            <?php if (!empty($voucher['sender_phone'])): ?>
                                <a href="tel:<?= e($voucher['sender_phone']) ?>" class="text-xs text-blue-600 font-mono hover:underline inline-flex items-center gap-1 mt-0.5">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                    <?= e($voucher['sender_phone']) ?>
                                </a>
                            <?php endif; ?>
                        </div>

                        <div class="p-3 bg-slate-50/70 rounded-xl border border-slate-200/60">
                            <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1" data-i18n="Receiver">Receiver</div>
                            <div class="font-bold text-slate-800"><?= e($voucher['receiver_name']) ?></div>
                            <?php if (!empty($voucher['receiver_phone'])): ?>
                                <a href="tel:<?= e($voucher['receiver_phone']) ?>" class="text-xs text-blue-600 font-mono hover:underline inline-flex items-center gap-1 mt-0.5">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                    <?= e($voucher['receiver_phone']) ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($voucher['created_at'])): ?>
                        <div class="text-xs text-slate-400 font-mono text-right pt-2">
                            <span data-i18n="Created">Created</span>: <?= format_datetime_myanmar($voucher['created_at']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Past Notes History (if any) -->
            <?php if (!empty($history_entries)): ?>
                <section class="v5-panel">
                    <div class="v5-panel__head">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            <h2 class="font-bold text-sm text-slate-800" data-i18n="Operational Timeline">Operational Timeline</h2>
                        </div>
                        <span class="text-xs text-slate-400 font-mono"><?= count($history_entries) ?> entries</span>
                    </div>
                    <div class="v5-panel__body max-h-60 overflow-y-auto space-y-3 text-xs pr-1">
                        <?php foreach ($history_entries as $entry): ?>
                            <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200/70">
                                <div class="flex items-center justify-between mb-1 font-semibold">
                                    <span class="text-blue-700"><?= e($entry['author']) ?></span>
                                    <span class="text-slate-400 font-mono text-[11px]"><?= e($entry['time']) ?></span>
                                </div>
                                <div class="text-slate-700 leading-relaxed whitespace-pre-wrap"><?= e($entry['text']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>

        <!-- Right: Status Transition Form (7 cols) -->
        <div class="lg:col-span-7">
            <section class="v5-panel">
                <div class="v5-panel__head">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                        <h2 class="font-bold text-sm text-slate-800" data-i18n="Update Status &amp; Ledger">Update Status &amp; Ledger</h2>
                    </div>
                    <span class="text-xs text-slate-400 hidden sm:inline" data-i18n="Shortcut: Ctrl+Enter to save">Shortcut: Ctrl+Enter to save</span>
                </div>

                <div class="v5-panel__body">
                    <form action="index.php?page=status_edit&id=<?= $voucher_id ?>" method="POST" accept-charset="UTF-8" id="status-edit-form" class="space-y-6">
                        <?= csrf_input() ?>

                        <!-- Interactive Status Transition Preview -->
                        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                            <div class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2.5" data-i18n="Milestone Transition">Milestone Transition</div>
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-slate-400 font-semibold" data-i18n="From">From:</span>
                                    <span class="v5-badge <?= $badge_cls ?> font-bold text-xs"><?= e($voucher['status']) ?></span>
                                </div>
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-slate-400 font-semibold" data-i18n="To">To:</span>
                                    <span id="preview-target-badge" class="v5-badge <?= $badge_cls ?> font-bold text-xs"><?= e($voucher['status']) ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- One-Tap Status Selector Buttons -->
                        <div class="space-y-2">
                            <label class="v5-field-label" data-i18n="Target Status">Target Status</label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5" id="status-button-grid">
                                <?php foreach ($possible_statuses as $st_opt):
                                    $is_current = ($voucher['status'] === $st_opt);
                                ?>
                                    <button type="button"
                                            class="status-btn px-3 py-2.5 rounded-xl border text-xs font-bold transition-all text-left flex items-center justify-between <?= $is_current ? 'border-blue-600 bg-blue-50/80 text-blue-800 shadow-sm ring-2 ring-blue-500/20' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50' ?>"
                                            data-status="<?= e($st_opt) ?>"
                                            onclick="selectStatus('<?= e($st_opt) ?>')">
                                        <span><?= e($st_opt) ?></span>
                                        <span class="check-icon <?= $is_current ? 'inline-block' : 'hidden' ?>">
                                            <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        </span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <!-- Hidden Actual Select Input for Form Compatibility -->
                            <select id="status" name="status" class="hidden" required>
                                <?php foreach ($possible_statuses as $status_option): ?>
                                    <option value="<?= e($status_option) ?>" <?= ($voucher['status'] === $status_option) ? 'selected' : '' ?>>
                                        <?= e($status_option) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Caution Warning Banner (shows if Cancelled or Returned is chosen) -->
                        <div id="status-warning-banner" class="hidden p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs flex items-start gap-2.5">
                            <svg class="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <div>
                                <strong class="font-bold" data-i18n="Reason Required">Operational Reason Required:</strong>
                                <span class="ml-1" data-i18n="Please provide an operational remark explaining the cancellation or return">Please provide an operational remark below explaining why this shipment is being cancelled or returned.</span>
                            </div>
                        </div>

                        <!-- Operational Remark Input & Quick Presets -->
                        <div class="space-y-2.5">
                            <div class="flex items-center justify-between">
                                <label for="new_remark" class="v5-field-label" data-i18n="Operational Remark">Operational Remark</label>
                                <span class="text-[11px] text-slate-400 font-medium" data-i18n="Appends with timestamp &amp; author">Appends with timestamp &amp; author</span>
                            </div>

                            <!-- Fast Remark Presets -->
                            <div class="flex flex-wrap gap-1.5" id="remark-presets">
                                <button type="button" onclick="appendRemark('Received at sorting hub, scan verified.')" class="btn-ghost btn-xs text-[11px] py-1 px-2 border border-slate-200 rounded-lg hover:border-blue-300 hover:text-blue-700" data-i18n="Hub Received">
                                    + Hub Received
                                </button>
                                <button type="button" onclick="appendRemark('Dispatched with courier on final delivery route.')" class="btn-ghost btn-xs text-[11px] py-1 px-2 border border-slate-200 rounded-lg hover:border-blue-300 hover:text-blue-700" data-i18n="Out for Delivery">
                                    + Out for Delivery
                                </button>
                                <button type="button" onclick="appendRemark('Delivered successfully to receiver, signed &amp; verified.')" class="btn-ghost btn-xs text-[11px] py-1 px-2 border border-slate-200 rounded-lg hover:border-blue-300 hover:text-blue-700" data-i18n="Delivered &amp; Signed">
                                    + Delivered &amp; Signed
                                </button>
                                <button type="button" onclick="appendRemark('Receiver unavailable at destination address; delivery rescheduled.')" class="btn-ghost btn-xs text-[11px] py-1 px-2 border border-slate-200 rounded-lg hover:border-blue-300 hover:text-blue-700" data-i18n="Rescheduled">
                                    + Rescheduled
                                </button>
                                <button type="button" onclick="appendRemark('Address clarification required; contacted customer by phone.')" class="btn-ghost btn-xs text-[11px] py-1 px-2 border border-slate-200 rounded-lg hover:border-blue-300 hover:text-blue-700" data-i18n="Address Clarification">
                                    + Address Clarification
                                </button>
                            </div>

                            <textarea id="new_remark" name="new_remark" rows="3" class="v5-input w-full" placeholder="Enter status update details, inspection findings, or delivery instructions..." data-i18n-placeholder="Enter status update details, inspection findings, or delivery instructions..."></textarea>
                        </div>

                        <!-- Advanced: Full Notes History Override (Admins Only) -->
                        <?php if ($is_elevated && !empty($raw_notes)): ?>
                            <details class="group text-xs border border-slate-200 rounded-xl overflow-hidden">
                                <summary class="cursor-pointer bg-slate-50 px-4 py-2.5 font-bold text-slate-600 flex items-center justify-between hover:bg-slate-100 select-none">
                                    <span data-i18n="Edit Full Raw Notes History">Edit Full Raw Notes History (Advanced)</span>
                                    <svg class="w-3.5 h-3.5 text-slate-400 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </summary>
                                <div class="p-3.5 bg-white space-y-1.5">
                                    <p class="text-[11px] text-slate-400" data-i18n="Caution: Editing this directly modifies past conversation entries">Caution: Editing this directly modifies all past conversation entries.</p>
                                    <textarea name="notes_override" rows="4" class="v5-input w-full font-mono text-xs"><?= e($raw_notes) ?></textarea>
                                </div>
                            </details>
                        <?php endif; ?>

                        <!-- Form Action Buttons -->
                        <div class="pt-3 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100">
                            <a href="index.php?page=voucher_view&id=<?= $voucher_id ?>" class="btn-secondary px-5 py-2.5 rounded-xl font-bold text-sm" data-i18n="Cancel">Cancel</a>
                            <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl font-bold text-sm inline-flex items-center gap-2 shadow-lg shadow-blue-600/20" id="btn-submit-status">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span data-i18n="Save Status Update">Save Status Update</span>
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
</div>

<script>
// Status Selection UI & Preview Logic
const badgeClasses = {
    'pending': 'bg-amber-50 text-amber-800 border-amber-200',
    'in transit': 'bg-blue-50 text-blue-800 border-blue-200',
    'received': 'bg-indigo-50 text-indigo-800 border-indigo-200',
    'delivered': 'bg-emerald-50 text-emerald-800 border-emerald-200',
    'cancelled': 'bg-rose-50 text-rose-800 border-rose-200',
    'returned': 'bg-purple-50 text-purple-800 border-purple-200',
    'maintenance': 'bg-slate-100 text-slate-800 border-slate-200'
};

function selectStatus(statusVal) {
    const select = document.getElementById('status');
    if (select) {
        select.value = statusVal;
    }

    // Update active button state
    document.querySelectorAll('.status-btn').forEach(btn => {
        const isSelected = btn.getAttribute('data-status') === statusVal;
        const checkIcon = btn.querySelector('.check-icon');
        if (isSelected) {
            btn.className = 'status-btn px-3 py-2.5 rounded-xl border text-xs font-bold transition-all text-left flex items-center justify-between border-blue-600 bg-blue-50/80 text-blue-800 shadow-sm ring-2 ring-blue-500/20';
            if (checkIcon) checkIcon.classList.remove('hidden');
        } else {
            btn.className = 'status-btn px-3 py-2.5 rounded-xl border text-xs font-bold transition-all text-left flex items-center justify-between border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50';
            if (checkIcon) checkIcon.classList.add('hidden');
        }
    });

    // Update target preview badge
    const previewBadge = document.getElementById('preview-target-badge');
    if (previewBadge) {
        previewBadge.textContent = statusVal;
        const normalized = statusVal.toLowerCase();
        previewBadge.className = 'v5-badge font-bold text-xs ' + (badgeClasses[normalized] || 'bg-slate-100 text-slate-800 border-slate-200');
    }

    // Warning banner for Cancelled / Returned
    const warningBanner = document.getElementById('status-warning-banner');
    if (warningBanner) {
        if (statusVal === 'Cancelled' || statusVal === 'Returned') {
            warningBanner.classList.remove('hidden');
        } else {
            warningBanner.classList.add('hidden');
        }
    }
}

function appendRemark(presetText) {
    const textarea = document.getElementById('new_remark');
    if (textarea) {
        const current = textarea.value.trim();
        textarea.value = current ? current + '\n' + presetText : presetText;
        textarea.focus();
    }
}

// Keyboard shortcut: Ctrl+Enter / Cmd+Enter to submit
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        const form = document.getElementById('status-edit-form');
        if (form) {
            e.preventDefault();
            form.submit();
        }
    }
});
</script>

<?php include_template('footer'); ?>
