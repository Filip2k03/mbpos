<?php
// pos/customer_voucher_view.php - Public-facing page for customers to track their voucher status.

require_once 'config.php';

global $connection;

$voucher_id = intval($_GET['id'] ?? 0);
$voucher_data = null;
$error_message = '';

if ($voucher_id <= 0) {
    $error_message = 'Invalid voucher ID provided.';
} else {
    // Fetch only the necessary, non-sensitive data for a public view
    $query = "SELECT 
                v.voucher_code, v.status, v.created_at,
                r_origin.region_name AS origin_region,
                r_dest.region_name AS destination_region
              FROM vouchers v
              LEFT JOIN regions r_origin ON v.region_id = r_origin.id
              LEFT JOIN regions r_dest ON v.destination_region_id = r_dest.id
              WHERE v.id = ?";

    $stmt = mysqli_prepare($connection, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $voucher_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $voucher_data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$voucher_data) {
            $error_message = 'The voucher you are looking for could not be found.';
        }
    } else {
        $error_message = 'A database error occurred. Please try again later.';
    }
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
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Track Your Voucher - MBLOGISTICS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }
        .status-icon {
            width: 64px;
            height: 64px;
            background-color: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s ease;
        }
        .status-icon svg {
            width: 32px;
            height: 32px;
            stroke-width: 2.5;
        }
        .status-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 10px 15px -3px rgba(102, 126, 234, 0.5),
                0 4px 6px -2px rgba(118, 75, 162, 0.4);
        }
        .hover\:status-icon:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-6">
    <main class="w-full max-w-3xl mx-auto bg-white rounded-3xl shadow-lg overflow-hidden">
        <!-- Header -->
        <header class="bg-gradient-to-r from-indigo-600 to-indigo-700 text-white text-center p-10">
            <h1 class="text-4xl font-extrabold tracking-tight mb-2">Shipment Tracking</h1>
            <p class="text-indigo-200 text-lg max-w-xl mx-auto">Check your voucher shipment details in real-time</p>
        </header>

        <section class="p-8">
            <?php if ($error_message): ?>
                <div class="bg-red-50 border border-red-300 text-red-700 p-6 rounded-xl shadow-md flex items-center space-x-4">
                    <svg class="w-8 h-8 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-12.728 12.728M5.636 5.636l12.728 12.728" />
                    </svg>
                    <p class="font-semibold text-lg"><?= htmlspecialchars($error_message) ?></p>
                </div>
            <?php elseif ($voucher_data): ?>
                <!-- Status Card -->
                <article class="status-card rounded-2xl p-8 mb-10 text-center text-white">
                    <div class="flex justify-center mb-6">
                        <div class="status-icon hover:status-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" role="img" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" d="<?= htmlspecialchars($status_icon_path) ?>" />
                            </svg>
                        </div>
                    </div>
                    <p class="text-lg opacity-90 mb-1">Current Status</p>
                    <h2 class="text-5xl font-extrabold tracking-tight"><?= htmlspecialchars($voucher_data['status']) ?></h2>
                </article>

                <!-- Shipment Details -->
                <section class="mb-10">
                    <h3 class="text-2xl font-semibold text-gray-800 mb-6 border-b border-gray-200 pb-2">Shipment Details</h3>
                    <dl class="grid grid-cols-1 md:grid-cols-3 gap-x-8 gap-y-6 text-gray-700">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Voucher Code</dt>
                            <dd class="mt-1 font-mono text-lg text-gray-900"><?= htmlspecialchars($voucher_data['voucher_code']) ?></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Current Location</dt>
                            <dd class="mt-1 font-semibold text-indigo-700 text-lg"><?= htmlspecialchars($current_location) ?></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Origin</dt>
                            <dd class="mt-1 text-lg"><?= htmlspecialchars($voucher_data['origin_region'] ?? 'N/A') ?></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Destination</dt>
                            <dd class="mt-1 text-lg"><?= htmlspecialchars($voucher_data['destination_region'] ?? 'N/A') ?></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created On</dt>
                            <dd class="mt-1 text-lg"><?= date('F j, Y', strtotime($voucher_data['created_at'])) ?></dd>
                        </div>
                    </dl>
                </section>

                <?php if (!empty($breakdown_items)): ?>
                <!-- Cost Breakdown -->
                <section class="mb-10">
                    <h3 class="text-2xl font-semibold text-gray-800 mb-6 border-b border-gray-200 pb-2">Items & Cost</h3>
                    <div class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
                        <table class="min-w-full text-gray-700 text-sm md:text-base">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left font-medium">Item</th>
                                    <th class="px-6 py-3 text-right font-medium">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <?php foreach ($breakdown_items as $item): ?>
                                <tr class="hover:bg-indigo-50 transition-colors duration-150">
                                    <td class="px-6 py-3"><?= htmlspecialchars($item['item_type']) ?> (<?= htmlspecialchars($item['kg']) ?> kg)</td>
                                    <td class="px-6 py-3 text-right"><?= htmlspecialchars($voucher_data['currency']) ?> <?= number_format($item['kg'] * $item['price_per_kg'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="hover:bg-indigo-50 transition-colors duration-150">
                                    <td class="px-6 py-3">Delivery Charge</td>
                                    <td class="px-6 py-3 text-right"><?= htmlspecialchars($voucher_data['currency']) ?> <?= number_format($voucher_data['delivery_charge'], 2) ?></td>
                                </tr>
                                <tr class="bg-indigo-100 font-semibold">
                                    <td class="px-6 py-3">Total Amount</td>
                                    <td class="px-6 py-3 text-right"><?= htmlspecialchars($voucher_data['currency']) ?> <?= number_format($voucher_data['total_amount'], 2) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <?php endif; ?>

                <!-- Go to Public Website Button -->
                <div class="text-center">
                    <a href="https://mblogistics.express" target="_blank" rel="noopener noreferrer"
                       class="inline-block px-8 py-3 rounded-lg bg-indigo-600 text-white font-semibold shadow-lg hover:bg-indigo-700 transition focus:outline-none focus:ring-4 focus:ring-indigo-300">
                         Go to Public Website
                    </a>
                </div>

            <?php endif; ?>
        </section>

        <!-- Footer -->
        <footer class="bg-gray-50 text-center py-6 text-gray-400 text-xs select-none">
            <p>&copy; <?= date('Y') ?> MBLOGISTICS. All rights reserved.</p>
        </footer>
    </main>
</body>
</html>

