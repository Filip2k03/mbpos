<?php
// customer/customer_dashboard.php - Displays voucher history for the logged-in customer.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!is_customer_logged_in()) {
    redirect('index.php?page=login');
}

global $connection;
$user_id = $_SESSION['customer_id'];
$vouchers = [];

// Fetch user's phone number
$stmt_phone = mysqli_prepare($connection, "SELECT phone FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt_phone, 'i', $user_id);
mysqli_stmt_execute($stmt_phone);
$result_phone = mysqli_stmt_get_result($stmt_phone);
$user = mysqli_fetch_assoc($result_phone);
$phone = $user['phone'] ?? null;
mysqli_stmt_close($stmt_phone);

if ($phone) {
    // FIX: Added total_amount and currency to the SELECT statement
    $stmt_vouchers = mysqli_prepare($connection, "SELECT id, voucher_code, sender_name, receiver_name, status, created_at, total_amount, currency FROM vouchers WHERE sender_phone = ? OR receiver_phone = ? ORDER BY created_at DESC");
    mysqli_stmt_bind_param($stmt_vouchers, 'ss', $phone, $phone);
    mysqli_stmt_execute($stmt_vouchers);
    $result_vouchers = mysqli_stmt_get_result($stmt_vouchers);
    while ($row = mysqli_fetch_assoc($result_vouchers)) {
        $vouchers[] = $row;
    }
    mysqli_stmt_close($stmt_vouchers);
}

include_template('header', ['title' => 'My Dashboard']);
?>
<div class="container mx-auto p-6">
    <!-- Title -->
    <h1 class="text-3xl font-extrabold text-gray-800 mb-6 text-center md:text-left">
         My Shipment History
    </h1>

    <div class="bg-white p-6 rounded-2xl shadow-lg overflow-hidden">
        <!-- Table (desktop) -->
        <div class="hidden md:block overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gradient-to-r from-indigo-50 to-indigo-100 text-indigo-700">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Date</th>
                        <th class="px-4 py-3 text-left font-semibold">Voucher Code</th>
                        <th class="px-4 py-3 text-left font-semibold">Sender</th>
                        <th class="px-4 py-3 text-left font-semibold">Receiver</th>
                        <th class="px-4 py-3 text-right font-semibold">Amount</th>
                        <th class="px-4 py-3 text-center font-semibold">Status</th>
                        <th class="px-4 py-3 text-center font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($vouchers)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-6 text-gray-500">
                                No shipments found for your account.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($vouchers as $voucher): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3"><?= date('Y-m-d', strtotime($voucher['created_at'])) ?></td>
                                <td class="px-4 py-3 font-mono text-indigo-600 font-medium"><?= htmlspecialchars($voucher['voucher_code']) ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($voucher['sender_name']) ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($voucher['receiver_name']) ?></td>
                                <td class="px-4 py-3 text-right"><?= htmlspecialchars($voucher['currency']) ?> <?= number_format($voucher['total_amount'], 2) ?></td>
                                <td class="px-4 py-3 text-center">
                                    <?php 
                                        $status = strtolower(str_replace(' ', '-', $voucher['status']));
                                        $statusColors = [
                                            'delivered' => 'bg-green-100 text-green-700',
                                            'in-transit' => 'bg-blue-100 text-blue-700',
                                            'pending' => 'bg-yellow-100 text-yellow-700',
                                            'cancelled' => 'bg-red-100 text-red-700',
                                        ];
                                        $color = $statusColors[$status] ?? 'bg-gray-100 text-gray-600';
                                    ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $color ?>">
                                        <?= htmlspecialchars($voucher['status']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <a href="index.php?page=customer_voucher_view&id=<?= $voucher['id'] ?>" 
                                       class="inline-block text-indigo-600 hover:text-indigo-900 font-medium">
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Card layout (mobile) -->
        <div class="md:hidden space-y-4">
            <?php if (empty($vouchers)): ?>
                <div class="text-center py-6 text-gray-500">
                    No shipments found for your account.
                </div>
            <?php else: ?>
                <?php foreach ($vouchers as $voucher): ?>
                    <div class="p-4 rounded-xl border border-gray-200 shadow-sm bg-gradient-to-br from-gray-50 to-white hover:shadow-md transition">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm text-gray-500"><?= date('M d, Y', strtotime($voucher['created_at'])) ?></span>
                            <?php 
                                $status = strtolower(str_replace(' ', '-', $voucher['status']));
                                $statusColors = [
                                    'delivered' => 'bg-green-100 text-green-700',
                                    'in-transit' => 'bg-blue-100 text-blue-700',
                                    'pending' => 'bg-yellow-100 text-yellow-700',
                                    'cancelled' => 'bg-red-100 text-red-700',
                                ];
                                $color = $statusColors[$status] ?? 'bg-gray-100 text-gray-600';
                            ?>
                            <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $color ?>">
                                <?= htmlspecialchars($voucher['status']) ?>
                            </span>
                        </div>
                        <p class="text-indigo-600 font-mono font-medium text-lg">
                            <?= htmlspecialchars($voucher['voucher_code']) ?>
                        </p>
                        <p class="text-sm text-gray-600 mt-1">
                            From <span class="font-medium"><?= htmlspecialchars($voucher['sender_name']) ?></span> 
                            → To <span class="font-medium"><?= htmlspecialchars($voucher['receiver_name']) ?></span>
                        </p>
                        <div class="flex justify-between items-center mt-3">
                            <span class="font-semibold text-gray-800">
                                <?= htmlspecialchars($voucher['currency']) ?> <?= number_format($voucher['total_amount'], 2) ?>
                            </span>
                            <a href="index.php?page=customer_voucher_view&id=<?= $voucher['id'] ?>" 
                               class="text-sm text-indigo-600 hover:text-indigo-900 font-medium">
                               View Details →
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
include_template('footer');
?>

