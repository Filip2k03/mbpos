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
    <title>MBLOGISTICS V5 • Track Voucher <?= htmlspecialchars($voucher_data['voucher_code'] ?? '') ?></title>
    <meta name="theme-color" content="#0b6ff5">
    <link rel="icon" type="image/svg+xml" href="assets/icons/mbpos.svg">
    <link rel="apple-touch-icon" href="assets/icons/mbpos.svg">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --v5-blue: #0b6ff5;
            --v5-cyan: #20b8f5;
            --v5-surface: rgba(255, 255, 255, 0.92);
            --v5-shadow: 0 20px 60px rgba(32, 75, 125, 0.12);
        }
        body {
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: radial-gradient(circle at 80% -5%, #d9f2ff 0, transparent 35%),
                        radial-gradient(circle at -5% 55%, #e7efff 0, transparent 35%),
                        #f3f7fc;
            min-height: 100vh;
        }
        .origami-mark {
            width: 44px;
            height: 36px;
            position: relative;
            display: inline-block;
        }
        .origami-mark:before, .origami-mark:after {
            content: "";
            position: absolute;
            transform: skew(-28deg);
            border-radius: 5px;
        }
        .origami-mark:before {
            left: 3px;
            top: 3px;
            width: 15px;
            height: 30px;
            background: linear-gradient(160deg, #096ff0, #79dcff);
        }
        .origami-mark:after {
            left: 20px;
            top: 8px;
            width: 15px;
            height: 25px;
            background: linear-gradient(160deg, #1167d8, #bdefff);
        }
    </style>
</head>
<body class="flex items-center justify-center p-4 sm:p-6">
    <div class="w-full max-w-lg mx-auto">
        <!-- Brand Header -->
        <div class="text-center mb-6">
            <div class="origami-mark mb-2"></div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">MBLOGISTICS <span class="text-blue-600">POS V5</span></h1>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest" data-i18n="Real-time Tracking">Real-Time Waybill Tracking</p>
        </div>

        <?php if ($error_message): ?>
            <div class="v5-glass-card border border-rose-200 bg-rose-50/90 text-rose-800 p-6 rounded-2xl shadow-lg text-center" role="alert">
                <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </div>
                <h3 class="font-bold text-base mb-1" data-i18n="Error">Error</h3>
                <p class="text-sm"><?= htmlspecialchars($error_message) ?></p>
            </div>
        <?php elseif ($voucher_data): ?>
            <div class="v5-glass-card bg-white/95 rounded-[2rem] shadow-2xl p-6 sm:p-8 border border-white/80">
                <!-- Status Banner -->
                <div class="bg-gradient-to-r from-blue-600 to-cyan-600 text-white p-6 rounded-2xl text-center mb-6 shadow-lg shadow-blue-500/20">
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-3 backdrop-blur-sm">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <p class="text-xs font-extrabold uppercase tracking-widest text-blue-100 mb-1" data-i18n="Current Status">Current Status</p>
                    <h2 class="text-3xl font-black tracking-tight"><?= htmlspecialchars($voucher_data['status']) ?></h2>
                </div>

                <!-- Details List -->
                <div>
                    <h3 class="font-bold text-sm text-slate-700 mb-3 px-1 uppercase tracking-wider text-xs" data-i18n="Order Summary">Shipment Details</h3>
                    <div class="divide-y divide-slate-100 rounded-xl bg-slate-50/80 border border-slate-200/80 overflow-hidden">
                        <div class="p-3.5 flex justify-between items-center text-sm">
                            <span class="text-slate-500 font-semibold" data-i18n="Voucher Code">Voucher Code</span>
                            <span class="font-mono font-bold text-blue-600"><?= htmlspecialchars($voucher_data['voucher_code']) ?></span>
                        </div>
                        <div class="p-3.5 flex justify-between items-center text-sm">
                            <span class="text-slate-500 font-semibold" data-i18n="Origin Point">Origin</span>
                            <span class="font-bold text-slate-800"><?= htmlspecialchars($voucher_data['origin_region'] ?? 'N/A') ?></span>
                        </div>
                        <div class="p-3.5 flex justify-between items-center text-sm">
                            <span class="text-slate-500 font-semibold" data-i18n="Destination">Destination</span>
                            <span class="font-bold text-slate-800"><?= htmlspecialchars($voucher_data['destination_region'] ?? 'N/A') ?></span>
                        </div>
                        <div class="p-3.5 flex justify-between items-center text-sm">
                            <span class="text-slate-500 font-semibold" data-i18n="Date">Creation Date</span>
                            <span class="font-medium text-slate-700"><?= date('F j, Y · H:i', strtotime($voucher_data['created_at'])) ?> (GMT+6:30)</span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-5 border-t border-slate-100 flex items-center justify-between text-xs text-slate-400">
                    <span>Verified via QR Code</span>
                    <span class="font-bold text-blue-500">MBLOGISTICS Global</span>
                </div>
            </div>
        <?php endif; ?>

        <div class="text-center mt-6 text-xs font-semibold text-slate-400">
            <p>&copy; <?= date('Y') ?> MBLOGISTICS POS V5 · All rights reserved.</p>
        </div>
    </div>
</body>
</html>