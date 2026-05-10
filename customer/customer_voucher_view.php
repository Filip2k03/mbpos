<?php
// customer/customer_voucher_view.php - Polished, secure view for a customer's specific voucher.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authentication & Authorization ---
if (!is_customer_logged_in()) {
    redirect('index.php?page=login');
}

global $connection;
$user_id = $_SESSION['customer_id'];
$voucher_id = intval($_GET['id'] ?? 0);
$voucher_data = null;
$breakdown_items = [];
$error_message = '';

// Get user's phone number for validation
$stmt_user = mysqli_prepare($connection, "SELECT phone FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt_user, 'i', $user_id);
mysqli_stmt_execute($stmt_user);
$user_phone = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user))['phone'] ?? null;
mysqli_stmt_close($stmt_user);

if ($voucher_id > 0 && $user_phone) {
    // Fetch voucher details
    $query = "SELECT 
                v.voucher_code, v.status, v.created_at,
                v.sender_phone, v.receiver_phone, v.total_amount, v.currency, v.delivery_charge,
                r_origin.region_name AS origin_region,
                r_dest.region_name AS destination_region
              FROM vouchers v
              LEFT JOIN regions r_origin ON v.region_id = r_origin.id
              LEFT JOIN regions r_dest ON v.destination_region_id = r_dest.id
              WHERE v.id = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, 'i', $voucher_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $voucher_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    // Security Check: Ensure the logged-in user is either the sender or receiver
    if (!$voucher_data || ($voucher_data['sender_phone'] != $user_phone && $voucher_data['receiver_phone'] != $user_phone)) {
        $error_message = "You do not have permission to view this voucher.";
        $voucher_data = null; // Clear data to prevent it from being displayed
    } else {
        // Fetch breakdown items if the voucher is valid
        $stmt_breakdown = mysqli_prepare($connection, "SELECT item_type, kg, price_per_kg FROM voucher_breakdowns WHERE voucher_id = ?");
        mysqli_stmt_bind_param($stmt_breakdown, 'i', $voucher_id);
        mysqli_stmt_execute($stmt_breakdown);
        $result_breakdown = mysqli_stmt_get_result($stmt_breakdown);
        while ($row = mysqli_fetch_assoc($result_breakdown)) {
            $breakdown_items[] = $row;
        }
        mysqli_stmt_close($stmt_breakdown);
    }
} else {
    $error_message = "Invalid request or user information could not be verified.";
}

// --- Determine Current Location & Icon based on Status ---
$current_location = "In Transit";
$status_icon_path = "M13 10V3L4 14h7v7l9-11h-7z"; // Default: In Transit

if (isset($voucher_data['status'])) {
    if ($voucher_data['status'] === 'Pending') {
        $current_location = $voucher_data['origin_region'];
        $status_icon_path = "M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z";
    } elseif (in_array($voucher_data['status'], ['Delivered', 'Received'])) {
        $current_location = $voucher_data['destination_region'];
        $status_icon_path = "M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z";
    }
}


include_template('header', ['title' => 'Voucher Details']);
?>
<div class="flex items-center justify-center min-h-screen p-4">
    <div class="w-full max-w-2xl mx-auto px-4">
    <!-- Page Title -->
    <div class="text-center mb-8">
        <h1 class="text-3xl font-extrabold text-gray-800">Shipment Tracking</h1>
        <p class="text-gray-500 mt-1">Check your voucher shipment details in real-time</p>
    </div>

    <?php if ($error_message): ?>
        <!-- Error Card -->
        <div class="bg-red-50 border border-red-300 text-red-700 p-5 rounded-xl shadow-md">
            <p class="font-bold">⚠️ Error</p>
            <p class="mt-1"><?= htmlspecialchars($error_message) ?></p>
        </div>
    <?php elseif ($voucher_data): ?>
        <!-- Voucher Card -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            
            <!-- Status Section -->
            <div class="bg-gradient-to-r from-indigo-500 to-indigo-600 text-white p-6 text-center">
                <div class="flex justify-center mb-4">
                    <div class="w-16 h-16 flex items-center justify-center bg-white bg-opacity-20 rounded-full">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <p class="text-lg opacity-90">Current Status</p>
                <h2 class="text-4xl font-bold tracking-wide"><?= htmlspecialchars($voucher_data['status']) ?></h2>
            </div>

            <!-- Shipment Details -->
            <div class="p-6">
                <h3 class="font-semibold text-lg text-gray-700 mb-3">Shipment Details</h3>
                <dl class="divide-y divide-gray-200">
                    <div class="py-3 grid grid-cols-3 gap-4">
                        <dt class="text-sm font-medium text-gray-500">Voucher Code</dt>
                        <dd class="col-span-2 text-sm font-mono text-gray-900"><?= htmlspecialchars($voucher_data['voucher_code']) ?></dd>
                    </div>
                    <div class="py-3 grid grid-cols-3 gap-4">
                        <dt class="text-sm font-medium text-gray-500">Current Location</dt>
                        <dd class="col-span-2 text-sm font-semibold text-indigo-700"><?= htmlspecialchars($current_location) ?></dd>
                    </div>
                    <div class="py-3 grid grid-cols-3 gap-4">
                        <dt class="text-sm font-medium text-gray-500">Origin</dt>
                        <dd class="col-span-2 text-sm text-gray-900"><?= htmlspecialchars($voucher_data['origin_region'] ?? 'N/A') ?></dd>
                    </div>
                    <div class="py-3 grid grid-cols-3 gap-4">
                        <dt class="text-sm font-medium text-gray-500">Destination</dt>
                        <dd class="col-span-2 text-sm text-gray-900"><?= htmlspecialchars($voucher_data['destination_region'] ?? 'N/A') ?></dd>
                    </div>
                    <div class="py-3 grid grid-cols-3 gap-4">
                        <dt class="text-sm font-medium text-gray-500">Created On</dt>
                        <dd class="col-span-2 text-sm text-gray-900"><?= date('F j, Y', strtotime($voucher_data['created_at'])) ?></dd>
                    </div>
                </dl>
            </div>

            <!-- Cost Breakdown -->
            <?php if (!empty($breakdown_items)): ?>
            <div class="px-6 pb-6">
                <h3 class="font-semibold text-lg text-gray-700 mb-3">Items & Cost</h3>
                <div class="overflow-hidden rounded-xl border border-gray-200 shadow-sm">
                    <table class="min-w-full text-sm text-gray-700">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium">Item</th>
                                <th class="px-4 py-2 text-right font-medium">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <?php foreach($breakdown_items as $item): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2"><?= htmlspecialchars($item['item_type']) ?> (<?= htmlspecialchars($item['kg']) ?> kg)</td>
                                <td class="px-4 py-2 text-right"><?= htmlspecialchars($voucher_data['currency']) ?> <?= number_format($item['kg'] * $item['price_per_kg'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2">Delivery Charge</td>
                                <td class="px-4 py-2 text-right"><?= htmlspecialchars($voucher_data['currency']) ?> <?= number_format($voucher_data['delivery_charge'], 2) ?></td>
                            </tr>
                            <tr class="bg-gray-50 font-bold">
                                <td class="px-4 py-2">Total Amount</td>
                                <td class="px-4 py-2 text-right"><?= htmlspecialchars($voucher_data['currency']) ?> <?= number_format($voucher_data['total_amount'], 2) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Back Button -->
            <div class="p-6 text-center border-t border-gray-100">
                <a href="index.php?page=dashboard" 
                   class="inline-block px-6 py-3 rounded-lg bg-indigo-600 text-white font-medium shadow hover:bg-indigo-700 transition">
                   ← Back to Dashboard
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="text-center mt-8 text-xs text-gray-400">
        <p>&copy; <?= date('Y') ?> MBLOGISTICS. All rights reserved.</p>
    </div>
</div>

</div>
<?php
include_template('footer');
?>

