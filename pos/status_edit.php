<?php
// pos/status_edit.php - Handles updating the status of a specific voucher.

global $connection; // Access the global database connection

if (!is_logged_in()) {
    flash_message('error', 'Please log in to edit voucher status.');
    redirect('index.php?page=login');
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

$voucher_id = intval($_GET['id'] ?? 0);

if ($voucher_id <= 0) {
    flash_message('error', 'Invalid voucher ID.');
    redirect('index.php?page=voucher_list'); // Redirect if no valid ID
}

// Define possible voucher statuses for dropdown
$possible_statuses = ['Pending', 'In Transit', 'Delivered', 'Cancelled', 'Returned'];

$voucher = null; // Initialize voucher data

// Fetch voucher details for display and update
$sql = "SELECT v.id, v.voucher_code, v.sender_name, v.receiver_name, v.receiver_phone, v.status, v.notes,
               r_origin.region_name AS origin_region_name,
               r_dest.region_name AS destination_region_name,
               v.created_by_user_id
        FROM vouchers v
        JOIN regions r_origin ON v.region_id = r_origin.id
        JOIN regions r_dest ON v.destination_region_id = r_dest.id
        WHERE v.id = ?";

// Security check: allows ADMIN to edit any, regular user to edit only their own
if ($user_type !== 'ADMIN') {
    $sql .= " AND v.created_by_user_id = ?";
}

$stmt = mysqli_prepare($connection, $sql);

if ($stmt) {
    if ($user_type !== 'ADMIN') {
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
    flash_message('error', 'Unable to load this shipment status right now.');
    redirect('index.php?page=voucher_list'); // Redirect on query preparation failure
}

if (!$voucher) {
    flash_message('error', 'Voucher not found or you do not have permission to edit it.');
    redirect('index.php?page=voucher_list'); // Redirect if voucher not found or no permission
}

// --- Handle POST request (Form Submission for Status Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_request();
    $new_status = trim($_POST['status'] ?? '');
    $notes_update = trim($_POST['notes'] ?? $voucher['notes']); // Allow notes to be updated too

    $errors = [];
    if (!in_array($new_status, $possible_statuses)) {
        $errors[] = 'Invalid status selected.';
    }

    if (!empty($errors)) {
        flash_message('error', implode('<br>', $errors));
    } else {
        $update_sql = "UPDATE vouchers SET status = ?, notes = ? WHERE id = ?";
        if ($user_type !== 'ADMIN') {
            $update_sql .= " AND created_by_user_id = ?";
        }

        $stmt_update = mysqli_prepare($connection, $update_sql);

        if ($stmt_update) {
            if ($user_type !== 'ADMIN') {
                mysqli_stmt_bind_param($stmt_update, 'ssii', $new_status, $notes_update, $voucher_id, $user_id);
            } else {
                mysqli_stmt_bind_param($stmt_update, 'ssi', $new_status, $notes_update, $voucher_id);
            }

            if (mysqli_stmt_execute($stmt_update)) {
                flash_message('success', 'Voucher status updated successfully!');
                redirect('index.php?page=voucher_view&id=' . $voucher_id); // Redirect back to voucher view
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

include_template('header', ['page' => 'status_edit']);
?>

<div class="relative min-h-[85vh] p-4 sm:p-8 flex items-center justify-center font-sans">
    <div class="w-full max-w-2xl v5-glass-card p-8 sm:p-10 shadow-2xl relative z-10 animate-fadeInDown">
        
        <!-- Header -->
        <div class="flex items-center gap-4 mb-8 pb-5 border-b border-slate-100">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-blue-600 to-cyan-500 text-white flex items-center justify-center shadow-lg shadow-blue-500/25">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </div>
            <div>
                <h2 class="text-2xl font-black text-slate-900 tracking-tight" data-i18n="Update Status">Update Voucher Status</h2>
                <p class="text-xs font-semibold text-slate-400 mt-0.5">Modify shipment status and operational remarks</p>
            </div>
        </div>

        <!-- Voucher Summary Card -->
        <div class="mb-6 p-5 bg-slate-50/80 rounded-2xl border border-slate-200/80 space-y-2.5 text-sm">
            <div class="flex justify-between items-center">
                <span class="text-slate-500 font-bold text-xs uppercase tracking-wider" data-i18n="Voucher Code">Voucher Code</span>
                <span class="font-mono font-bold text-blue-600"><?= htmlspecialchars($voucher['voucher_code']); ?></span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-slate-500 font-bold text-xs uppercase tracking-wider" data-i18n="Sender">Sender</span>
                <span class="font-semibold text-slate-800"><?= htmlspecialchars($voucher['sender_name']); ?></span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-slate-500 font-bold text-xs uppercase tracking-wider" data-i18n="Receiver">Receiver</span>
                <span class="font-semibold text-slate-800"><?= htmlspecialchars($voucher['receiver_name']); ?> (<?= htmlspecialchars($voucher['receiver_phone']); ?>)</span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-slate-500 font-bold text-xs uppercase tracking-wider" data-i18n="Destination">Routing</span>
                <span class="font-medium text-slate-700"><?= htmlspecialchars($voucher['origin_region_name']); ?> → <?= htmlspecialchars($voucher['destination_region_name']); ?></span>
            </div>
            <div class="flex justify-between items-center pt-2 border-t border-slate-200/60">
                <span class="text-slate-500 font-bold text-xs uppercase tracking-wider" data-i18n="Status">Current Status</span>
                <span class="v5-badge <?= match(strtolower($voucher['status'])) {
                    'pending' => 'bg-amber-100 text-amber-800 border-amber-200',
                    'in transit' => 'bg-blue-100 text-blue-800 border-blue-200',
                    'delivered' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                    'cancelled' => 'bg-rose-100 text-rose-800 border-rose-200',
                    default => 'bg-slate-100 text-slate-800 border-slate-200'
                } ?>">
                    <?= htmlspecialchars($voucher['status']); ?>
                </span>
            </div>
        </div>

        <!-- Form -->
        <form action="index.php?page=status_edit&id=<?= htmlspecialchars($voucher['id']); ?>" method="POST" class="space-y-5">
            <?= csrf_input() ?>
            <div class="space-y-1.5">
                <label for="status" class="block text-xs font-bold text-slate-500 uppercase tracking-wider ml-1" data-i18n="Status">Update Status</label>
                <select id="status" name="status" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all" required>
                    <?php foreach ($possible_statuses as $status_option): ?>
                        <option value="<?= htmlspecialchars($status_option); ?>" <?= ($voucher['status'] === $status_option) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($status_option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="space-y-1.5">
                <label for="notes" class="block text-xs font-bold text-slate-500 uppercase tracking-wider ml-1" data-i18n="Operational Notes">Operational Notes (Optional)</label>
                <textarea id="notes" name="notes" rows="3" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-800 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all" placeholder="Enter remarks or delivery updates..."><?= htmlspecialchars($voucher['notes'] ?? ''); ?></textarea>
            </div>

            <div class="pt-3 flex items-center justify-between gap-4">
                <a href="index.php?page=voucher_view&id=<?= htmlspecialchars($voucher['id']); ?>" class="px-5 py-3 rounded-xl border border-slate-200 text-sm font-bold text-slate-600 hover:bg-slate-50 transition-all" data-i18n="Cancel">Cancel</a>
                <button type="submit" class="flex-1 btn-primary py-3 px-6 rounded-xl font-bold text-sm text-white shadow-lg shadow-blue-500/25 hover:opacity-95 transition-all" data-i18n="Update">Update Status</button>
            </div>
        </form>
    </div>
</div>

<?php include_template('footer'); ?>
