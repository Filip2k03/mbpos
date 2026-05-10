<?php
// pos/customer_voucher_view.php - Public-facing page for customers to track their voucher status.

require_once 'config.php';
require_once 'includes/functions.php'; // Using for consistency, though no session functions are needed.

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Your Voucher</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        .status-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .status-icon {
            width: 50px;
            height: 50px;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <div class="w-full max-w-md mx-auto">
        <div class="text-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Shipment Status</h1>
        </div>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-md" role="alert">
                <p class="font-bold">Error</p>
                <p><?= htmlspecialchars($error_message) ?></p>
            </div>
        <?php elseif ($voucher_data): ?>
            <div class="bg-white rounded-2xl shadow-xl p-8">
                <div class="status-card text-white p-6 rounded-xl text-center mb-6">
                    <div class="status-icon mx-auto mb-3">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <p class="text-lg font-semibold">Current Status:</p>
                    <h2 class="text-4xl font-bold"><?= htmlspecialchars($voucher_data['status']) ?></h2>
                </div>
                
                <div>
                    <h3 class="font-semibold text-lg text-gray-700 mb-2">Voucher Details</h3>
                    <div class="border-t border-gray-200">
                        <dl>
                            <div class="bg-gray-50 px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium text-gray-500">Voucher Code</dt>
                                <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 font-mono"><?= htmlspecialchars($voucher_data['voucher_code']) ?></dd>
                            </div>
                            <div class="bg-white px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium text-gray-500">Origin</dt>
                                <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?= htmlspecialchars($voucher_data['origin_region'] ?? 'N/A') ?></dd>
                            </div>
                             <div class="bg-gray-50 px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium text-gray-500">Destination</dt>
                                <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?= htmlspecialchars($voucher_data['destination_region'] ?? 'N/A') ?></dd>
                            </div>
                             <div class="bg-white px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium text-gray-500">Creation Date</dt>
                                <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2"><?= date('F j, Y', strtotime($voucher_data['created_at'])) ?></dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        <?php endif; ?>

         <div class="text-center mt-6 text-sm text-gray-500">
            <p>&copy; <?= date('Y') ?> MBLOGISTICS. All rights reserved.</p>
        </div>
    </div>
</body>
</html>