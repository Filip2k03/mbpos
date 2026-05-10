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
?>

<div class="max-w-6xl mx-auto">
    <div class="bg-white p-8 rounded-2xl shadow-xl border border-gray-100">
        <!-- Header -->
        <div class="flex items-start justify-between mb-6">
            <div>
                <h2 class="text-3xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                    Voucher Details
                </h2>
                <p class="text-gray-500">Review and update shipment information.</p>
            </div>
            <div class="text-right">
                <p class="font-mono text-lg text-indigo-600">#<?= htmlspecialchars($voucher['voucher_code']) ?></p>
                <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $voucher['status'])) ?> mt-1">
                    <?= htmlspecialchars($voucher['status']) ?>
                </span>
            </div>
        </div>

        <!-- Details Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column: Key Info -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Sender -->
                <div class="info-card">
                    <div class="icon-box bg-blue-100">
                        <svg class="icon text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-500 text-sm">SENDER</p>
                        <p class="font-bold text-lg"><?= htmlspecialchars($voucher['sender_name']) ?></p>
                        <p class="text-gray-600"><?= htmlspecialchars($voucher['sender_phone']) ?></p>
                    </div>
                </div>
                 <!-- Receiver -->
                <div class="info-card">
                     <div class="icon-box bg-green-100">
                        <svg class="icon text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-500 text-sm">RECEIVER</p>
                        <p class="font-bold text-lg"><?= htmlspecialchars($voucher['receiver_name']) ?></p>
                        <p class="text-gray-600"><?= htmlspecialchars($voucher['receiver_phone']) ?></p>
                    </div>
                </div>
                <!-- Address -->
                 <div class="info-card">
                    <div class="icon-box bg-purple-100">
                       <svg class="icon text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-500 text-sm">DELIVER TO</p>
                        <p class="text-gray-700"><?= nl2br(htmlspecialchars($voucher['receiver_address'])) ?></p>
                    </div>
                </div>
            </div>

            <!-- Right Column: Form and Details -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Shipment Details -->
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-center">
                    <div class="detail-box border border-gray-300 rounded-lg p-4 bg-gray-50 hover:shadow-lg transition-shadow duration-300">
                        <p class="label font-bold text-gray-800">Origin</p>
                        <p class="value"><?= htmlspecialchars($voucher['origin_region_name'] ?? 'N/A') ?></p>
                        <p class="sub-value"><?= htmlspecialchars($voucher['origin_branch_name'] ?? 'N/A') ?></p>
                    </div>
                    <div class="detail-box border border-gray-300 rounded-lg p-4 bg-gray-50 hover:shadow-lg transition-shadow duration-300">
                        <p class="label font-bold text-gray-800">Destination</p>
                        <p class="value"><?= htmlspecialchars($voucher['destination_region_name'] ?? 'N/A') ?></p>
                        <p class="sub-value"><?= htmlspecialchars($voucher['destination_branch_name'] ?? 'N/A') ?></p>
                    </div>
                     <div class="detail-box border border-gray-300 rounded-lg p-4 bg-gray-50 hover:shadow-lg transition-shadow duration-300">
                        <p class="label font-bold text-gray-800">Weight</p>
                        <p class="value"><?= number_format($voucher['weight_kg'], 2) ?> kg</p>
                    </div>
                    <div class="detail-box border border-gray-300 rounded-lg p-4 bg-gray-50 hover:shadow-lg transition-shadow duration-300">
                        <p class="label font-bold text-gray-800">Delivery Charge</p>
                        <p class="value"><?= htmlspecialchars($voucher['currency']) ?> <?= number_format($voucher['delivery_charge'], 2) ?></p>
                    </div>
                    <div class="detail-box col-span-2 md:col-span-1 border border-gray-300 rounded-lg p-4 bg-gray-50 hover:shadow-lg transition-shadow duration-300">
                        <p class="label font-bold text-gray-800">Total Amount</p>
                        <p class="value text-green-600 font-bold"><?= htmlspecialchars($voucher['currency']) ?> <?= number_format($voucher['total_amount'], 2) ?></p>
                    </div>
                </div>

                <!-- Notes Display -->
                 <div>
                    <h3 class="section-title mb-2">Notes</h3>
                    <div class="w-full p-4 bg-gray-50 border rounded-lg min-h-[100px] text-gray-700">
                        <?= nl2br(htmlspecialchars($voucher['notes'] ?: 'No notes have been added yet.')) ?>
                    </div>
                </div>


                <!-- Update Form -->
                <div>
                    <h3 class="section-title mb-2">Update Voucher</h3>
                    <form action="index.php?page=voucher_view&id=<?= $voucher_id ?>" method="POST" class="bg-blue-50 p-4 rounded-lg border border-blue-100">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="status" class="form-label">Change Status</label>
                                <select id="status" name="status" class="form-select" required>
                                    <?php foreach($possible_statuses as $status): ?>
                                        <option value="<?= $status ?>" <?= ($voucher['status'] === $status) ? 'selected' : '' ?>><?= $status ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="notes" class="form-label">Update Notes</label>
                                <textarea id="notes" name="notes" rows="3" class="form-input" placeholder="Add or update notes here..."><?= htmlspecialchars($voucher['notes'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div class="mt-4 text-right">
                            <button type="submit" class="btn px-5 py-2 bg-green-400 hover:bg-green-600 text-gray-700 font-medium rounded-lg shadow-sm transition">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

         <!-- Action Buttons -->
       <div class="button-container">
            <a href="index.php?page=voucher_list" class="btn bg-gray-600 hover:bg-gray-700 text-white">Back to Voucher List</a>
            <a href="voucher_print.php?id=<?= $voucher['id'] ?>" target="_blank" class="btn bg-green-600 hover:bg-green-700 text-white">Print Voucher</a>
        </div>
    </div>
</div>

<?php
include_template('footer');
?>

