<?php
// pos/voucher_print.php - A polished, print-friendly version of the voucher.

session_start();

require_once 'config.php';
require_once 'includes/functions.php';

global $connection;

if (!is_logged_in()) {
    flash_message('error', 'Please log in to view and print vouchers.');
    redirect('index.php?page=login');
}

$voucher_id = $_GET['id'] ?? null;
if (!$voucher_id || !is_numeric($voucher_id)) {
    flash_message('error', 'Invalid voucher ID for printing.');
    redirect('index.php?page=voucher_list');
}

$voucher_data = null;
$breakdown_items = [];
$error_message = '';

try {
    // Fetch voucher details with all necessary joins
    $query_voucher = "SELECT v.*, 
                             r_origin.region_name AS origin_region, 
                             r_dest.region_name AS destination_region,
                             b_origin.branch_name AS origin_branch,
                             b_dest.branch_name AS destination_branch,
                             u.username AS created_by_username
                     FROM vouchers v
                     LEFT JOIN regions r_origin ON v.region_id = r_origin.id
                     LEFT JOIN regions r_dest ON v.destination_region_id = r_dest.id
                     LEFT JOIN branches b_origin ON v.origin_branch_id = b_origin.id
                     LEFT JOIN branches b_dest ON v.destination_branch_id = b_dest.id
                     LEFT JOIN users u ON v.created_by_user_id = u.id
                     WHERE v.id = ?";
    
    $stmt_voucher = mysqli_prepare($connection, $query_voucher);
    mysqli_stmt_bind_param($stmt_voucher, 'i', $voucher_id);
    mysqli_stmt_execute($stmt_voucher);
    $result_voucher = mysqli_stmt_get_result($stmt_voucher);
    $voucher_data = mysqli_fetch_assoc($result_voucher);
    mysqli_stmt_close($stmt_voucher);

    if (!$voucher_data) {
        $error_message = 'Voucher not found or you do not have permission to print it.';
    } else {
        // Fetch breakdown items
        $stmt_breakdowns = mysqli_prepare($connection, "SELECT * FROM voucher_breakdowns WHERE voucher_id = ?");
        mysqli_stmt_bind_param($stmt_breakdowns, 'i', $voucher_id);
        mysqli_stmt_execute($stmt_breakdowns);
        $result_breakdowns = mysqli_stmt_get_result($stmt_breakdowns);
        while ($row = mysqli_fetch_assoc($result_breakdowns)) {
            $breakdown_items[] = $row;
        }
        mysqli_stmt_close($stmt_breakdowns);
    }
} catch (Exception $e) {
    $error_message = 'An unexpected error occurred: ' . $e->getMessage();
}

// Set up the base URL for the QR code
$base_url = "https://qr.mblogistics.express/index.php?page=customer_voucher_view&id=";
$qr_data = $base_url . urlencode($voucher_data['id'] ?? '');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Voucher - <?php echo htmlspecialchars($voucher_data['voucher_code'] ?? 'N/A'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Libre+Barcode+39+Text&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bs-primary-rgb: 78, 115, 223;
            --bs-secondary-rgb: 133, 135, 150;
            --font-family-sans-serif: 'Inter', sans-serif;
            --font-family-barcode: 'Libre Barcode 39 Text', cursive;
        }
        body {
            font-family: var(--font-family-sans-serif);
            background-color: #e9ecef;
        }
        .print-container {
            max-width: 800px;
            margin: 2rem auto;
            background: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.1);
            padding: 2.5rem;
        }
        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .print-header img {
            height: 70px;
            width: 70px;
            border-radius: 50%;
        }
        .barcode {
            font-family: var(--font-family-barcode);
            font-size: 3rem;
            line-height: 1;
            color: #212529;
            margin: 0;
        }
        .qr-code img {
            width: 100px;
            height: 100px;
        }
        .section-title {
            font-weight: 600;
            color: #4e73df;
            margin-bottom: 1rem;
            border-bottom: 1px solid #e3e6f0;
            padding-bottom: 0.5rem;
        }
        .info-card {
            background-color: #f8f9fc;
            border: 1px solid #e3e6f0;
            border-radius: 0.35rem;
            padding: 1.25rem;
            height: 100%;
        }
        .info-card h6 {
            font-weight: 700;
            color: var(--bs-secondary);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .info-card p {
            margin-bottom: 0.25rem;
        }
        .total-amount-box {
            background-color: #4e73df;
            color: #fff;
            padding: 1rem;
            border-radius: 0.35rem;
        }
        .signature-section {
            margin-top: 3rem;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            width: 30%;
            border-top: 1px solid #858796;
            padding-top: 0.5rem;
            text-align: center;
            color: #858796;
            font-size: 0.9rem;
        }
        .print-footer {
            text-align: center;
            margin-top: 2rem;
            border-top: 1px dashed #d1d3e2;
            padding-top: 1rem;
            font-size: 0.8rem;
            color: #858796;
        }
        .voucher-notes-container {
    background-color: #fad390; /* Tailwind CSS yellow-500 hex */
    padding: 12px 16px;
    border-radius: 8px;
    margin-top: 16px;
    color: #1a202c; /* Dark text for contrast */
    font-family: Arial, sans-serif;
    font-size: 14px;
  }

  /* Flex layout for label and value */
  .voucher-note-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 6px;
  }

  .voucher-note-label {
    font-weight: 600;
  }

  .voucher-note-value {
    text-align: right;
    white-space: pre-wrap; /* To respect line breaks in notes */
  }
        @media print {
            body { background-color: #fff; }
            .print-container {
                box-shadow: none;
                margin: 0;
                padding: 0;
                border: none;
            }
            .no-print { display: none !important; }
            
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($error_message): ?>
            <div class="alert alert-danger no-print mt-4" role="alert"><?= $error_message ?></div>
        <?php elseif ($voucher_data): ?>
            <div class="print-container">
                <header class="print-header">
                    <img src="bg.jpg" alt="MB Logistics Logo">
                    <div>
                        <h2 class="fw-bold mb-0">MBLOGISTICS</h2>
                        <p class="text-secondary mb-0">Shipment Voucher</p>
                    </div>
                    <div>
                        <p class="mb-0"><strong>Date:</strong> <?= date('Y-m-d H:i', strtotime($voucher_data['created_at'])) ?></p>
                    </div>
                </header>

                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="info-card">
                            <h6>From (Sender)</h6>
                            <p class="fw-bold fs-5"><?= htmlspecialchars($voucher_data['sender_name']) ?></p>
                            <p><?= htmlspecialchars($voucher_data['sender_phone']) ?></p>
                            <p class="text-secondary small">Origin: <?= htmlspecialchars($voucher_data['origin_region'] ?? 'N/A') ?> / <?= htmlspecialchars($voucher_data['origin_branch'] ?? 'N/A') ?></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-card">
                            <h6>To (Receiver)</h6>
                            <p class="fw-bold fs-5"><?= htmlspecialchars($voucher_data['receiver_name']) ?></p>
                            <p><?= htmlspecialchars($voucher_data['receiver_phone']) ?></p>
                            <p class="text-secondary small">Address: <?= nl2br(htmlspecialchars($voucher_data['receiver_address'])) ?></p>
                        </div>
                    </div>
                </div>

                <h5 class="section-title">Item Breakdown</h5>
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Item Type</th>
                            <th class="text-end">Weight (kg)</th>
                            <th class="text-end">Price/Kg</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($breakdown_items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['item_type']) ?></td>
                            <td class="text-end"><?= number_format($item['kg'], 2) ?></td>
                            <td class="text-end"><?= htmlspecialchars($voucher_data['currency']) ?> <?= number_format($item['price_per_kg'], 2) ?></td>
                            <td class="text-end"><?= htmlspecialchars($voucher_data['currency']) ?> <?= number_format($item['kg'] * $item['price_per_kg'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <!--<tr class="table-standard">-->
                        <!--    <td colspan="3" class="text-end fw-bold">Notes</td>-->
                            <!--<td class="text-end fw-bold"></td>-->
                        <!--</tr>-->
                        <tr class="table-standard">
                            <td colspan="3" class="text-end fw-bold">Delivery Type</td>
                            <td class="text-end fw-bold"><?= htmlspecialchars($voucher_data['delivery_type'] ?? 'N/A') ?></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-end fw-bold">Total Weight</td>
                            <td class="text-end fw-bold"><?= number_format($voucher_data['weight_kg'], 2) ?> kg</td>
                        </tr>
                         <tr>
                            <td colspan="3" class="text-end">Delivery Charge</td>
                            <td class="text-end"><?= htmlspecialchars($voucher_data['currency']) ?> <?= number_format($voucher_data['delivery_charge'], 2) ?></td>
                        </tr>
                         <tr class="table-primary">
                            <td colspan="3" class="text-end fw-bold fs-5">Total Amount</td>
                            <td class="text-end fw-bold fs-.5"><?= htmlspecialchars($voucher_data['currency']) ?> <?= number_format($voucher_data['total_amount'], 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
                <div class="voucher-notes-container">
                    <div class="voucher-note-row">
                    <div class="voucher-note-label">Notes:</div>
                    <div class="voucher-note-value"><?= nl2br(htmlspecialchars($voucher_data['notes'])) ?></div>
                </div>
  
</div>
                
                <!-- Important Notes -->
                <div class="notes">
                    <p style="color: #dc3545; font-weight: bold;"><strong>Important Notes:</strong></p>
                    <ol style="background: #fff5f5; border-radius: 6px; padding: 18px 24px; border: 1px solid #ffeaea;">
                        <li>ဥပဒေနှင့်မလွတ်ကင်းသောပစ္စည်းများ လုံးဝ(လုံးဝ) လက်မခံပါ။</li>
                        <li>ပါဝင်ပစ္စည်းများအား မှန်ကန်စွာပြောပါ။ ကြိုတင်ကြေငြာထားခြင်းမရှိပဲ ခိုးထည့်သောပစ္စည်းများအတွက် တာဝန်မယူပါ။ ယင်းပစ္စည်းများနှင့်ပတ်သက်ပြီ ပြဿနာတစ်စုံတရာဖြစ်ပေါ်ပါက ပိုဆောင်သူဘက်မှတာဝန်ယူဖြေရှင်းရမည်။</li>
                        <li>ပစ္စည်းပိုဆောင်စဉ် လုံခြုံရေးအရ ဖွင့်ဖေါက်စစ်ဆေးမှုအား လက်ခံပေးရပါမည်။</li>
                        <li>အစားအသောက်နှင့် ကြိုးကျေလွယ်သောပစ္စည်းများ အပျက်အစီး တာဝန်မယူပါ။</li>
                        <li>သက်မှတ်KG နှုန်းထားများသည် ရုံးထုတ်ဈေးသာဖြစ်ပြီး တစ်ဖက်နိုင်ငံတွင် အရောက်ပိုလျှင် အရောက်ပိုခ ထပ်ပေးရပါမည်။</li>
                    </ol>
                </div>
                <div class="signature-section">
                    <div class="signature-box">Sender's Signature</div>
                    <div class="signature-box">Staff Signature</div>
                </div>
                
                <footer class="print-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="qr-code">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?= urlencode($qr_data) ?>" alt="QR Code for Tracking">
                        </div>
                        <div class="text-center">
                            <p class="barcode">*<?= htmlspecialchars($voucher_data['voucher_code']) ?>*</p>
                            <p><?= htmlspecialchars($voucher_data['voucher_code']) ?></p>
                        </div>
                    </div>
                    <p class="mt-3">Thank you for choosing MBLOGISTICS. Scan the QR code to track your shipment.</p>
                </footer>
            </div>

            <div class="text-center mt-4 no-print">
                <button class="btn btn-primary btn-lg bg-green-400 text-white py-2 rounded-xl font-semibold hover:bg-green-500 shadow-md transition" onclick="window.print()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-printer-fill me-2" viewBox="0 0 16 16">
                      <path d="M5 1a2 2 0 0 0-2 2v1h10V3a2 2 0 0 0-2-2H5zm4 8a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1h-1a1 1 0 0 1-1-1V9z"/>
                      <path d="M4 0a2 2 0 0 0-2 2v3h12V2a2 2 0 0 0-2-2H4zm0 11h8a1 1 0 0 1 0 2H4a1 1 0 0 1 0-2zm0 3h8a1 1 0 0 1 0 2H4a1 1 0 0 1 0-2zM2 7a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7z"/>
                    </svg>
                    Print Voucher
                </button>
                <a href="index.php?page=voucher_view&id=<?= htmlspecialchars($voucher_id) ?>" class="btn btn-outline-secondary btn-lg bg-gray-600 text-black py-2 rounded-xl font-semibold hover:bg-gray-700 shadow-md transition">Back to Details</a>
            </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
