<?php
// pos/voucher_create.php - MBLOGISTICS POS V5 Interface
// Modern, enterprise-grade delivery voucher management with real-time live voucher preview

require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/cache.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ensure GMT+6:30 Timezone (Asia/Yangon)
if (date_default_timezone_get() !== 'Asia/Yangon') {
    date_default_timezone_set('Asia/Yangon');
}

// --- Authentication & Authorization ---
if (!is_logged_in() || (!is_staff() && !is_admin() && !is_developer())) {
    flash_message('error', 'You are not authorized to create vouchers.');
    redirect('index.php?page=dashboard');
}

global $connection;
$user_id = $_SESSION['user_id'];

// --- Fetch User Info and Data for Dropdowns ---
$user_info = null;
$stmt_user = mysqli_prepare($connection, "SELECT u.region_id, u.branch_id, r.region_name, b.branch_name 
                                        FROM users u 
                                        LEFT JOIN regions r ON u.region_id = r.id
                                        LEFT JOIN branches b ON u.branch_id = b.id
                                        WHERE u.id = ?");
mysqli_stmt_bind_param($stmt_user, 'i', $user_id);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
if ($result_user) $user_info = mysqli_fetch_assoc($result_user);
mysqli_stmt_close($stmt_user);

$all_regions = mbpos_cache_remember('lookup-regions', 'voucher-create', 30, function () use ($connection) {
    $rows = [];
    $region_result = mysqli_query($connection, "SELECT id, region_name, prefix, current_sequence FROM regions ORDER BY region_name");
    if ($region_result) while ($row = mysqli_fetch_assoc($region_result)) $rows[] = $row;
    return $rows;
});
$all_branches = mbpos_cache_remember('lookup-branches', 'all', 300, function () use ($connection) {
    $rows = [];
    $branch_result = mysqli_query($connection, "SELECT id, branch_name, region_id FROM branches ORDER BY branch_name");
    if ($branch_result) while ($row = mysqli_fetch_assoc($branch_result)) $rows[] = $row;
    return $rows;
});
$currencies = mbpos_cache_remember('lookup-currencies', 'codes', 300, function () use ($connection) {
    $rows = [];
    $currency_result = mysqli_query($connection, "SELECT code FROM currencies ORDER BY code");
    if ($currency_result) while ($row = mysqli_fetch_assoc($currency_result)) $rows[] = $row['code'];
    return $rows;
});
if (empty($currencies)) $currencies = ['MMK', 'RM', 'SGD', 'USD'];

$item_types_list = mbpos_cache_remember('lookup-items', 'all', 300, function () use ($connection) {
    $rows = [];
    $item_type_result = mysqli_query($connection, "SELECT name FROM item_types ORDER BY name");
    if ($item_type_result) while ($row = mysqli_fetch_assoc($item_type_result)) $rows[] = $row['name'];
    return $rows;
});
if (empty($item_types_list)) {
    $item_types_list = ['Laptop', 'Bag', 'Book', 'Document', 'Fancy Gold', 'Power Bank', 'Medicine', 'Food / Snacks', 'Phone', 'Electronics', 'Clothing', 'Cosmetics'];
}

$delivery_types = mbpos_cache_remember('lookup-delivery-types', 'all', 300, function () use ($connection) {
    $rows = [];
    $delivery_type_result = mysqli_query($connection, "SELECT name FROM delivery_types ORDER BY name");
    if ($delivery_type_result) while ($row = mysqli_fetch_assoc($delivery_type_result)) $rows[] = $row['name'];
    return $rows;
});
if (empty($delivery_types)) {
    $delivery_types = ['ကားဂိတ်တင်', 'စင်တာမှ လွှဲပို့', 'စာတိုက်တင်', 'မလပးကွား ပို့ဆောင်', 'မော်လမြိုင် ရုံးထုတ်', 'ရန်ကုန် ရုံးထုတ်', 'အထူးဘိုင့်'];
}

// Draft preview values (authoritative codes generated securely upon transaction commit)
$preview_voucher_code = 'Pending Save';
$preview_tracking_no = 'Pending Save';
$preview_date_time = date('Y-m-d H:i');

// --- Voucher Duplication Feature ---
$duplicate_voucher = null;
$duplicate_items = [];
$duplicate_id = intval($_GET['duplicate_id'] ?? 0);
if ($duplicate_id > 0) {
    $stmt_dup = mysqli_prepare($connection, "SELECT * FROM vouchers WHERE id = ?");
    if ($stmt_dup) {
        mysqli_stmt_bind_param($stmt_dup, 'i', $duplicate_id);
        mysqli_stmt_execute($stmt_dup);
        $res_dup = mysqli_stmt_get_result($stmt_dup);
        if ($res_dup) {
            $duplicate_voucher = mysqli_fetch_assoc($res_dup);
        }
        mysqli_stmt_close($stmt_dup);
    }
    if ($duplicate_voucher) {
        $stmt_dup_items = mysqli_prepare($connection, "SELECT * FROM voucher_breakdowns WHERE voucher_id = ?");
        if ($stmt_dup_items) {
            mysqli_stmt_bind_param($stmt_dup_items, 'i', $duplicate_id);
            mysqli_stmt_execute($stmt_dup_items);
            $res_dup_items = mysqli_stmt_get_result($stmt_dup_items);
            if ($res_dup_items) {
                while ($row = mysqli_fetch_assoc($res_dup_items)) {
                    $duplicate_items[] = [
                        'category' => $row['item_type'],
                        'weight' => (float)$row['kg'],
                        'price' => (float)$row['price_per_kg']
                    ];
                }
            }
            mysqli_stmt_close($stmt_dup_items);
        }
    }
}

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_request();
    // V5 vouchers always capture a fresh sender and receiver. Historical
    // customer links remain in the database for old vouchers, but this form
    // never accepts or trusts customer IDs from the browser.
    $sender_customer_id = null;
    $sender_name = trim($_POST['sender_name']);
    $sender_phone = trim($_POST['sender_phone']);
    
    $receiver_customer_id = null;
    $receiver_name = trim($_POST['receiver_name']);
    $receiver_phone = trim($_POST['receiver_phone']);
    $receiver_address = trim($_POST['receiver_address']);
    
    $origin_region_id = $user_info['region_id'] ?? 1;
    $origin_branch_id = $user_info['branch_id'] ?? 1;
    $destination_region_id = intval($_POST['destination_region_id']);
    $destination_branch_id = intval($_POST['destination_branch_id']);
    $delivery_charge = floatval($_POST['delivery_charge'] ?? 0);
    $currency = trim($_POST['currency'] ?? 'MMK');
    $notes = trim($_POST['notes'] ?? '');
    $delivery_type = trim($_POST['delivery_type'] ?? 'Standard');

    // Process and Validate Items
    $item_types = $_POST['item_type'] ?? [];
    $item_kgs = $_POST['item_kg'] ?? [];
    $item_prices = $_POST['item_price_per_kg'] ?? [];
    $total_weight = 0;
    $total_amount = 0;
    $validated_breakdowns = [];
    foreach ($item_types as $key => $type) {
        if (!empty($type) && floatval($item_kgs[$key] ?? 0) > 0) {
            $kg = floatval($item_kgs[$key]);
            $price = floatval($item_prices[$key] ?? 0);
            $total_weight += $kg;
            $total_amount += $kg * $price;
            $validated_breakdowns[] = ['type' => $type, 'kg' => $kg, 'price' => $price];
        }
    }
    $total_amount += $delivery_charge;
    
    // Main Validation
    if (empty($sender_name) || empty($receiver_name) || empty($validated_breakdowns) || empty($destination_region_id) || empty($destination_branch_id)) {
        flash_message('error', 'Please fill all required fields, select destination branch, and add at least one valid item.');
        redirect('index.php?page=voucher_create');
    }

    // Database Transaction
    mysqli_begin_transaction($connection);
    try {
        // 1. Get sequence and prefix from DESTINATION region
        $stmt_seq = mysqli_prepare($connection, "SELECT current_sequence, prefix FROM regions WHERE id = ? FOR UPDATE");
        mysqli_stmt_bind_param($stmt_seq, 'i', $destination_region_id);
        mysqli_stmt_execute($stmt_seq);
        $region_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_seq));
        $new_sequence = ($region_data['current_sequence'] ?? 0) + 1;
        $prefix = !empty($region_data['prefix']) ? $region_data['prefix'] : 'MBV';
        $voucher_code = generate_voucher_code($prefix, $new_sequence);
        mysqli_stmt_close($stmt_seq);
        
        // 2. Insert the main voucher record
        $voucher_sql = "INSERT INTO vouchers (voucher_code, sender_customer_id, receiver_customer_id, sender_name, sender_phone, receiver_name, receiver_phone, receiver_address, region_id, origin_branch_id, destination_region_id, destination_branch_id, weight_kg, price_per_kg_at_voucher, delivery_charge, total_amount, currency, delivery_type, notes, created_by_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt_voucher = mysqli_prepare($connection, $voucher_sql);
        $dummy_price_per_kg = $total_weight > 0 ? ($total_amount - $delivery_charge) / $total_weight : 0;
        
        mysqli_stmt_bind_param($stmt_voucher, 'siisssssiiiiddddsssi', $voucher_code, $sender_customer_id, $receiver_customer_id, $sender_name, $sender_phone, $receiver_name, $receiver_phone, $receiver_address, $origin_region_id, $origin_branch_id, $destination_region_id, $destination_branch_id, $total_weight, $dummy_price_per_kg, $delivery_charge, $total_amount, $currency, $delivery_type, $notes, $user_id);
        
        if(!mysqli_stmt_execute($stmt_voucher)) throw new Exception("Database Error [Voucher]: " . mysqli_stmt_error($stmt_voucher));
        $new_voucher_id = mysqli_insert_id($connection);
        mysqli_stmt_close($stmt_voucher);

        // 3. Insert the itemized breakdown for the voucher
        $stmt_breakdown = mysqli_prepare($connection, "INSERT INTO voucher_breakdowns (voucher_id, item_type, kg, price_per_kg) VALUES (?, ?, ?, ?)");
        foreach ($validated_breakdowns as $item) {
            mysqli_stmt_bind_param($stmt_breakdown, 'isdd', $new_voucher_id, $item['type'], $item['kg'], $item['price']);
            if(!mysqli_stmt_execute($stmt_breakdown)) throw new Exception("Database Error [Breakdown]: " . mysqli_stmt_error($stmt_breakdown));
        }
        mysqli_stmt_close($stmt_breakdown);

        // 4. Put every new voucher into the operational stock queue.
        $stmt_stock = mysqli_prepare($connection, "INSERT INTO stock (voucher_id) VALUES (?)");
        mysqli_stmt_bind_param($stmt_stock, 'i', $new_voucher_id);
        if (!mysqli_stmt_execute($stmt_stock)) throw new Exception("Database Error [Stock]: " . mysqli_stmt_error($stmt_stock));
        mysqli_stmt_close($stmt_stock);

        // 5. Update sequence number
        $stmt_update_seq = mysqli_prepare($connection, "UPDATE regions SET current_sequence = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_update_seq, 'ii', $new_sequence, $destination_region_id);
        if(!mysqli_stmt_execute($stmt_update_seq)) throw new Exception("Database Error [Sequence]: " . mysqli_stmt_error($stmt_update_seq));
        mysqli_stmt_close($stmt_update_seq);
        mbpos_cache_del(mbpos_cache_key('lookup-regions', 'voucher-create'));
        mbpos_cache_del(mbpos_cache_key('dashboard-regions', 'sequence'));

        // 6. Create user notifications
        $notification_message = "New voucher #{$voucher_code} created by " . htmlspecialchars($_SESSION['username'] ?? 'Staff') . ".";
        $users_to_notify = [];
        $user_result = mysqli_query($connection, "SELECT id FROM users WHERE id != $user_id AND user_type != 'Customer'");
        if ($user_result) while ($row = mysqli_fetch_assoc($user_result)) $users_to_notify[] = $row['id'];
        
        if (!empty($users_to_notify)) {
            $stmt_notif = mysqli_prepare($connection, "INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            foreach ($users_to_notify as $notify_user_id) {
                mysqli_stmt_bind_param($stmt_notif, 'is', $notify_user_id, $notification_message);
                mysqli_stmt_execute($stmt_notif);
                mbpos_cache_del(mbpos_cache_key('notifications-unread', $notify_user_id));
            }
            mysqli_stmt_close($stmt_notif);
        }
        
        mysqli_commit($connection);
        
        $_SESSION['show_success_modal'] = true;
        $_SESSION['success_modal_title'] = "Voucher Created!";
        $_SESSION['success_modal_message'] = "Voucher #" . htmlspecialchars($voucher_code) . " has been created successfully.";
        $_SESSION['success_modal_link'] = "index.php?page=voucher_view&id=" . $new_voucher_id;
        $_SESSION['success_modal_link_text'] = "View Voucher";

        redirect('index.php?page=voucher_list');

    } catch (Exception $e) {
        mysqli_rollback($connection);
        error_log($e->getMessage()); 
        flash_message('error', "An unexpected error occurred: " . $e->getMessage());
        redirect('index.php?page=voucher_create');
    }
}

// Render master header
include_template('header', ['page' => 'voucher_create']);
?>

<style>
.voucher-create-page{
  --vc-bg:#f4f7fb;--vc-surface:#fff;--vc-text:#14243a;--vc-muted:#64748b;--vc-line:#dce5f0;
  --vc-primary:#1769e0;--vc-primary-strong:#0b4fb7;--vc-soft:#edf5ff;--vc-success:#087f5b;
  --vc-danger:#c92a2a;--vc-shadow:0 12px 34px rgba(30,64,110,.09);--vc-radius:18px;
  min-height:100vh;display:grid;grid-template-columns:244px minmax(0,1fr);color:var(--vc-text);
  background:linear-gradient(180deg,#f8fbff 0,#f3f7fc 55%,#eef4fa 100%);font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif
}
.voucher-create-page *{box-sizing:border-box}
.voucher-create-page button,.voucher-create-page input,.voucher-create-page select,.voucher-create-page textarea{font:inherit}
.voucher-create-page button{cursor:pointer}
.voucher-create-page svg{width:20px;height:20px;display:block}
.voucher-create-page .sidebar{position:sticky;top:0;height:100vh;padding:20px 14px;background:rgba(255,255,255,.94);border-right:1px solid var(--vc-line);z-index:40}
.voucher-create-page .brand{display:flex;gap:11px;align-items:center;padding:4px 9px 24px}
.voucher-create-page .mark{width:38px;height:32px;position:relative;flex:0 0 auto}
.voucher-create-page .mark:before,.voucher-create-page .mark:after{content:"";position:absolute;transform:skew(-24deg);border-radius:4px}
.voucher-create-page .mark:before{left:3px;top:2px;width:14px;height:28px;background:linear-gradient(160deg,#0967df,#5ed1ff)}
.voucher-create-page .mark:after{left:20px;top:7px;width:14px;height:23px;background:linear-gradient(160deg,#124ead,#b7ebff)}
.voucher-create-page .brand b{font-size:15px;line-height:1.2;color:var(--vc-text);display:block}
.voucher-create-page .brand small{display:block;color:var(--vc-primary);font-size:9px;font-weight:800;letter-spacing:.06em}
.voucher-create-page .nav{display:grid;gap:5px}
.voucher-create-page .nav a{min-height:44px;color:#52657d;padding:10px 12px;border-radius:12px;font-size:12px;font-weight:700;display:flex;gap:11px;align-items:center;text-decoration:none;transition:background-color .18s,color .18s}
.voucher-create-page .nav a:hover{background:var(--vc-soft);color:var(--vc-primary)}
.voucher-create-page .nav a.active{color:#fff;background:linear-gradient(135deg,var(--vc-primary),#169fe9);box-shadow:0 8px 20px rgba(23,105,224,.22)}
.voucher-create-page .side-health{position:absolute;bottom:18px;left:14px;right:14px;padding:13px;border:1px solid var(--vc-line);border-radius:15px;background:#f8fbff}
.voucher-create-page .system-dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:#12a879;box-shadow:0 0 0 4px #dcf8ef;margin-right:7px}
.voucher-create-page .main{min-width:0}
.voucher-create-page .top{min-height:70px;display:flex;align-items:center;gap:12px;padding:12px 24px;position:sticky;top:0;z-index:30;background:rgba(248,251,255,.94);border-bottom:1px solid var(--vc-line);backdrop-filter:blur(16px)}
.voucher-create-page .menu-toggle{display:none;width:44px;height:44px;border:1px solid var(--vc-line);border-radius:12px;background:#fff;color:var(--vc-text);align-items:center;justify-content:center}
.voucher-create-page .search-wrap{position:relative;flex:1;max-width:560px}
.voucher-create-page .search-wrap svg{position:absolute;left:13px;top:50%;transform:translateY(-50%);width:17px;height:17px;color:#8190a4;pointer-events:none}
.voucher-create-page .search{width:100%;height:44px;border:1px solid var(--vc-line);border-radius:12px;background:#fff;padding:0 14px 0 40px;outline:0;font-size:13px;color:var(--vc-text)}
.voucher-create-page .search:focus{border-color:#75aef5;box-shadow:0 0 0 3px rgba(23,105,224,.12)}
.voucher-create-page .operator{margin-left:auto;display:flex;align-items:center;gap:9px;min-width:0}
.voucher-create-page .avatar{width:38px;height:38px;border-radius:50%;display:grid;place-items:center;color:#fff;font-size:11px;font-weight:800;background:linear-gradient(135deg,#173a67,#5386bd);flex:0 0 auto}
.voucher-create-page .content{max-width:1580px;margin:auto;padding:24px;width:100%}
.voucher-create-page .head{display:flex;justify-content:space-between;align-items:end;gap:20px;margin-bottom:16px}
.voucher-create-page .head h1{margin:0;font-size:clamp(22px,2.1vw,30px);font-weight:850;letter-spacing:-.03em}
.voucher-create-page .head p{margin:6px 0 0;color:var(--vc-muted);font-size:13px}
.voucher-create-page .chip{display:inline-flex;padding:3px 8px;border-radius:7px;background:var(--vc-soft);color:var(--vc-primary);font-size:10px;font-weight:800;vertical-align:middle;margin-left:5px}
.voucher-create-page .glass{background:var(--vc-surface);border:1px solid var(--vc-line);border-radius:var(--vc-radius);box-shadow:var(--vc-shadow)}
.voucher-create-page .steps{display:grid;grid-template-columns:repeat(5,1fr);gap:6px;padding:8px;margin-bottom:18px}
.voucher-create-page .step{text-align:center;padding:8px;color:#7d8ca1;font-size:11px;border-radius:11px;user-select:none}
.voucher-create-page .step b{display:grid;place-items:center;width:26px;height:26px;margin:0 auto 5px;border-radius:50%;background:#edf1f6;color:#617187;font-size:11px}
.voucher-create-page .step.active{background:var(--vc-soft);color:var(--vc-primary);font-weight:800}
.voucher-create-page .step.active b{color:#fff;background:var(--vc-primary)}
.voucher-create-page .workspace{display:grid;grid-template-columns:minmax(0,1fr) 380px;gap:18px;align-items:start}
.voucher-create-page .grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.voucher-create-page .card{padding:19px}
.voucher-create-page .card h3,.voucher-create-page .summary h3,.voucher-create-page .tracking h3{margin:0 0 16px;font-size:15px;font-weight:800;display:flex;justify-content:space-between;align-items:center;gap:12px}
.voucher-create-page .card h3 small{font-weight:500;color:var(--vc-muted);font-size:11px;text-align:right}
.voucher-create-page .section-title{display:flex;align-items:center;gap:9px}
.voucher-create-page .section-number{display:grid;place-items:center;width:26px;height:26px;border-radius:8px;background:var(--vc-soft);color:var(--vc-primary);font-size:12px;flex:0 0 auto}
.voucher-create-page .field{margin-bottom:13px}
.voucher-create-page .field label{display:block;margin-bottom:6px;font-size:11px;font-weight:750;color:#4c6078}
.voucher-create-page .field input,.voucher-create-page .field select,.voucher-create-page .field textarea{width:100%;min-height:44px;padding:10px 12px;border:1px solid var(--vc-line);border-radius:11px;background:#fff;color:var(--vc-text);outline:0;font-size:16px;transition:border-color .18s,box-shadow .18s}
.voucher-create-page .field input:focus,.voucher-create-page .field select:focus,.voucher-create-page .field textarea:focus{border-color:#75aef5;box-shadow:0 0 0 3px rgba(23,105,224,.12)}
.voucher-create-page .field textarea{min-height:92px;resize:vertical}
.voucher-create-page .required{color:var(--vc-danger)}
.voucher-create-page .secure-hint{display:flex;gap:7px;align-items:flex-start;color:var(--vc-muted);font-size:10px;line-height:1.45;margin-top:3px}
.voucher-create-page .secure-hint svg{width:14px;height:14px;color:var(--vc-success);flex:0 0 auto}
.voucher-create-page .route{grid-column:1/-1}
.voucher-create-page .routegrid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:13px}
.voucher-create-page .full{grid-column:1/-1}
.voucher-create-page .delivery{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px}
.voucher-create-page .radio{display:flex;gap:8px;align-items:center;min-height:44px;border:1px solid var(--vc-line);padding:9px 10px;border-radius:10px;background:#fff;font-size:12px;cursor:pointer}
.voucher-create-page .radio:has(input:checked){border-color:#75aef5;background:var(--vc-soft);color:var(--vc-primary);font-weight:700}
.voucher-create-page .radio input{accent-color:var(--vc-primary);width:16px;height:16px}
.voucher-create-page .currencies{display:grid;grid-template-columns:repeat(auto-fit,minmax(58px,1fr));gap:6px}
.voucher-create-page .currencies button{min-height:44px;border:1px solid var(--vc-line);background:#fff;border-radius:9px;padding:8px;font-size:12px;font-weight:750;color:#52657d}
.voucher-create-page .currencies button.active{background:var(--vc-soft);border-color:#75aef5;color:var(--vc-primary);box-shadow:inset 0 0 0 1px #75aef5}
.voucher-create-page .items{margin-top:18px}
.voucher-create-page .tablewrap{overflow:auto;border:1px solid var(--vc-line);border-radius:12px}
.voucher-create-page .table{width:100%;border-collapse:collapse;min-width:650px;font-size:12px}
.voucher-create-page .table th{color:#617187;text-align:left;padding:10px;background:#f7f9fc;font-size:10px;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid var(--vc-line)}
.voucher-create-page .table td{padding:8px 10px;background:#fff;border-bottom:1px solid #edf1f6}
.voucher-create-page .table tr:last-child td{border-bottom:0}
.voucher-create-page .table input,.voucher-create-page .table select{width:100%;min-height:40px;border:1px solid transparent;border-radius:8px;padding:8px;background:#f8fafc;font-size:14px;color:var(--vc-text);outline:0}
.voucher-create-page .table input:focus,.voucher-create-page .table select:focus{border-color:#75aef5;background:#fff;box-shadow:0 0 0 3px rgba(23,105,224,.1)}
.voucher-create-page .remove{width:44px;height:44px;display:grid;place-items:center;border:1px solid #ffd3d3;background:#fff7f7;color:var(--vc-danger);border-radius:9px}
.voucher-create-page .remove svg{width:18px;height:18px}
.voucher-create-page .add{min-height:44px;margin-top:12px;border:1px dashed #75aef5;color:var(--vc-primary);background:#f5f9ff;border-radius:10px;padding:9px 14px;font-size:12px;font-weight:800;display:inline-flex;align-items:center;gap:8px}
.voucher-create-page .add svg{width:17px;height:17px}
.voucher-create-page .totals{display:flex;justify-content:flex-end;gap:30px;margin-top:14px;font-size:11px;color:var(--vc-muted);text-align:right}
.voucher-create-page .totals strong{display:block;color:var(--vc-primary-strong);font-size:18px;margin-top:2px}
.voucher-create-page .right{display:grid;gap:18px;align-content:start}
.voucher-create-page .summary,.voucher-create-page .tracking{padding:19px}
.voucher-create-page .summary{position:sticky;top:88px}
.voucher-create-page .status{background:#e3f8f0;color:var(--vc-success);border-radius:999px;padding:5px 9px;font-size:10px;font-weight:800}
.voucher-create-page .sum{display:flex;justify-content:space-between;gap:18px;padding:9px 0;color:#617187;font-size:12px;border-bottom:1px solid #edf1f6}
.voucher-create-page .grand{display:flex;justify-content:space-between;align-items:center;gap:16px;margin:13px 0 8px;padding:14px;border-radius:12px;background:var(--vc-soft);font-weight:800;border:1px solid #d4e7ff}
.voucher-create-page .grand strong{color:var(--vc-primary-strong);font-size:20px;text-align:right}
.voucher-create-page .actions{display:grid;gap:8px;margin-top:14px}
.voucher-create-page .primary,.voucher-create-page .secondary{width:100%;min-height:46px;padding:10px 12px;border-radius:11px;font-size:12px;font-weight:800;display:flex;justify-content:center;align-items:center;gap:8px}
.voucher-create-page .primary svg,.voucher-create-page .secondary svg{width:17px;height:17px}
.voucher-create-page .primary{border:0;color:#fff;background:linear-gradient(135deg,var(--vc-primary),#1598e6);box-shadow:0 9px 20px rgba(23,105,224,.2)}
.voucher-create-page .primary:disabled{opacity:.65;cursor:wait}
.voucher-create-page .secondary{border:1px solid var(--vc-line);background:#fff;color:#465b74}
.voucher-create-page .request-status{margin-top:10px;font-size:10px;color:var(--vc-muted);min-height:15px}
.voucher-create-page .request-status.ok{color:var(--vc-success);font-weight:700}
.voucher-create-page .tracking .line{font-size:10px;color:var(--vc-muted);margin-top:9px}
.voucher-create-page .tracking b{font-size:12px;color:#1e293b;overflow-wrap:anywhere}
.voucher-create-page .barcode{height:48px;margin:12px 0;background:repeating-linear-gradient(90deg,#182a43 0 2px,transparent 2px 4px,#182a43 4px 5px,transparent 5px 8px);border-radius:3px}
.voucher-create-page .track-bottom{display:flex;justify-content:space-between;align-items:center;gap:12px}
.voucher-create-page .status-list{display:grid;gap:6px;margin-top:9px;font-size:10px;color:#8090a4}
.voucher-create-page .status-item{display:flex;align-items:center;gap:7px}
.voucher-create-page .status-item:before{content:"";width:7px;height:7px;border-radius:50%;background:#cbd5e1;flex:0 0 auto}
.voucher-create-page .status-item.current{color:var(--vc-success);font-weight:700}
.voucher-create-page .status-item.current:before{background:#12a879;box-shadow:0 0 0 3px #dcf8ef}
.voucher-create-page .qr{width:64px;height:64px;border:7px solid #fff;box-shadow:0 0 0 1px var(--vc-line);background:repeating-conic-gradient(#15253b 0 8%,#fff 0 16%);flex:0 0 auto}
.voucher-create-page .preview{padding:14px}
.voucher-create-page .previewbar{display:flex;justify-content:space-between;align-items:center;padding:2px 5px 12px}
.voucher-create-page .previewbar .secondary{width:auto;min-height:38px;padding:7px 11px}
.voucher-create-page .paper{background:#fff;border:1px solid #d8e2ed;box-shadow:0 12px 30px rgba(35,65,100,.12);padding:18px;min-height:600px;border-radius:8px}
.voucher-create-page .phead{display:flex;justify-content:space-between;border-bottom:2px solid #1595ef;padding-bottom:10px}
.voucher-create-page .pbrand{display:flex;align-items:center;gap:9px}
.voucher-create-page .pbrand-logo{width:42px;height:42px;border-radius:50%;object-fit:cover;border:1px solid #dce5ef;background:#fff}
.voucher-create-page .phead strong{color:#126bd3;font-size:17px;display:block;font-weight:900}
.voucher-create-page .phead small{font-size:8px;font-weight:700;color:#64748b;text-align:right}
.voucher-create-page .pgrid{display:grid;grid-template-columns:1fr 1fr;gap:7px}
.voucher-create-page .pbox{border:1px solid #dce5ef;border-radius:6px;padding:8px;font-size:8px;min-height:48px;background:#fafcff}
.voucher-create-page .pbox b{display:block;font-size:9px;margin-bottom:4px;color:#2b415e;font-weight:800}
.voucher-create-page .pbox.full{grid-column:1/-1}
.voucher-create-page .paper h4{font-size:9px;color:#126bd3;margin:13px 0 6px;font-weight:800;text-transform:uppercase;letter-spacing:.3px}
.voucher-create-page .paper table{width:100%;border-collapse:collapse;font-size:8px}
.voucher-create-page .paper th,.voucher-create-page .paper td{border:1px solid #dce5ef;padding:5px 6px;text-align:left}
.voucher-create-page .paper th{background:#f1f5f9;color:#475569;font-weight:800}
.voucher-create-page .paper-total{display:flex;justify-content:space-between;padding:10px 0;font-size:9px;font-weight:900}
.voucher-create-page .paper-total strong{font-size:14px;color:#126bd3}
.voucher-create-page .legacy-print-notes{margin-top:10px;padding:9px 11px;border:1px solid #ffe1e1;border-radius:6px;background:#fff8f8;color:#7f1d1d;font-size:7px;line-height:1.45}
.voucher-create-page .legacy-print-notes b{display:block;margin-bottom:4px;color:#dc2626;font-size:8px}
.voucher-create-page .legacy-print-notes ol{margin:0;padding-left:15px}
.voucher-create-page .spinner{display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.45);border-top-color:#fff;border-radius:50%;animation:vc-spin .7s linear infinite}
@keyframes vc-spin{to{transform:rotate(360deg)}}
.voucher-create-page .toast{position:fixed;right:20px;bottom:20px;max-width:min(360px,calc(100vw - 32px));padding:12px 16px;background:#14243a;color:#fff;border-radius:12px;box-shadow:var(--vc-shadow);font-size:12px;font-weight:650;opacity:0;transform:translateY(10px);transition:.2s ease;z-index:100;pointer-events:none}
.voucher-create-page .toast.show{opacity:1;transform:none}
.voucher-create-page .nav-scrim{display:none}
@media(max-width:1260px){
  .voucher-create-page .workspace{grid-template-columns:1fr}
  .voucher-create-page .right{grid-template-columns:1fr 1fr}
  .voucher-create-page .summary{position:static}
  .voucher-create-page .preview{grid-column:1/-1}
}
@media(max-width:900px){
  .voucher-create-page{display:block;padding-bottom:env(safe-area-inset-bottom)}
  .voucher-create-page .sidebar{position:fixed;inset:0 auto 0 0;width:min(290px,86vw);height:100dvh;transform:translateX(-105%);transition:transform .2s ease;box-shadow:20px 0 45px rgba(15,35,65,.18)}
  .voucher-create-page.nav-open .sidebar{transform:translateX(0)}
  .voucher-create-page .nav-scrim{display:block;position:fixed;inset:0;background:rgba(15,35,65,.4);z-index:35;opacity:0;pointer-events:none;transition:opacity .2s}
  .voucher-create-page.nav-open .nav-scrim{opacity:1;pointer-events:auto}
  .voucher-create-page .menu-toggle{display:flex}
  .voucher-create-page .top{padding:10px 14px;padding-top:max(10px,env(safe-area-inset-top))}
  .voucher-create-page .operator-details{display:none}
  .voucher-create-page .content{padding:18px 14px 24px}
  .voucher-create-page .grid,.voucher-create-page .routegrid,.voucher-create-page .right{grid-template-columns:1fr}
  .voucher-create-page .preview{grid-column:auto}
  .voucher-create-page .route,.voucher-create-page .full{grid-column:auto}
  .voucher-create-page .head{align-items:flex-start}
}
@media(max-width:600px){
  .voucher-create-page .top{gap:8px}
  .voucher-create-page .search-wrap{display:none}
  .voucher-create-page .head{display:block}
  .voucher-create-page .voucher-code{margin-top:12px;text-align:left!important;padding:10px 12px;background:#fff;border:1px solid var(--vc-line);border-radius:11px}
  .voucher-create-page .steps{display:flex;overflow-x:auto;scroll-snap-type:x proximity;padding:7px;-webkit-overflow-scrolling:touch}
  .voucher-create-page .step{min-width:98px;scroll-snap-align:start}
  .voucher-create-page .card,.voucher-create-page .summary,.voucher-create-page .tracking{padding:15px}
  .voucher-create-page .card h3{align-items:flex-start}
  .voucher-create-page .delivery{grid-template-columns:1fr}
  .voucher-create-page .tablewrap{overflow:visible;border:0}
  .voucher-create-page .table{display:block;min-width:0}
  .voucher-create-page .table thead{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
  .voucher-create-page .table tbody{display:grid;gap:10px}
  .voucher-create-page .table tr{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:12px;border:1px solid var(--vc-line);border-radius:12px;background:#fff}
  .voucher-create-page .table td{display:block;padding:0;border:0;background:transparent;min-width:0}
  .voucher-create-page .table td:before{content:attr(data-label);display:block;margin-bottom:5px;color:#617187;font-size:10px;font-weight:750;text-transform:uppercase;letter-spacing:.04em}
  .voucher-create-page .table td:first-child{grid-column:1/-1;font-weight:800;color:var(--vc-primary)}
  .voucher-create-page .table td:nth-child(2){grid-column:1/-1}
  .voucher-create-page .table td:nth-child(5){display:flex;flex-direction:column;justify-content:flex-end;font-size:15px}
  .voucher-create-page .table td:last-child{display:flex;justify-content:flex-end;align-items:flex-end}
  .voucher-create-page .totals{justify-content:space-between;gap:12px;text-align:left}
  .voucher-create-page .totals strong{font-size:16px}
  .voucher-create-page .preview{overflow:auto}
  .voucher-create-page .paper{min-width:330px}
  .voucher-create-page .toast{right:16px;bottom:calc(16px + env(safe-area-inset-bottom))}
}
@media(max-width:360px){
  .voucher-create-page .content{padding-left:10px;padding-right:10px}
  .voucher-create-page .card,.voucher-create-page .summary,.voucher-create-page .tracking{padding:13px}
  .voucher-create-page .currencies{grid-template-columns:repeat(2,1fr)}
  .voucher-create-page .totals{display:grid;grid-template-columns:1fr 1fr}
}
@media(prefers-reduced-motion:reduce){.voucher-create-page *{scroll-behavior:auto!important;transition:none!important;animation:none!important}}
@media print{
  *{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}
  body{background:#fff!important;padding:0!important;margin:0!important}
  .voucher-create-page .sidebar,.voucher-create-page .top,.voucher-create-page .head,.voucher-create-page .steps,.voucher-create-page .workspace>section,.voucher-create-page .summary,.voucher-create-page .tracking,.voucher-create-page .previewbar,.voucher-create-page .toast,.voucher-create-page .nav-scrim{display:none!important}
  .voucher-create-page,.voucher-create-page .main,.voucher-create-page .content,.voucher-create-page #voucher-form,.voucher-create-page .workspace,.voucher-create-page .right,.voucher-create-page .preview{display:block!important;padding:0!important;margin:0!important;width:100%!important;max-width:none!important;background:#fff!important}
  .voucher-create-page .paper{border:0!important;box-shadow:none!important;min-height:auto!important;padding:0!important;display:block!important}
}
</style>

<div class="app voucher-create-page" id="voucher-page">
  <!-- Sidebar (Desktop Enterprise Nav) -->
  <aside class="sidebar" id="voucher-sidebar">
    <div class="brand">
      <div class="mark"></div>
      <div>
        <b>MBLOGISTICS</b>
        <small>POS V5 · FAST · SAFE · GLOBAL</small>
      </div>
    </div>
    
    <nav class="nav" aria-label="Primary navigation">
      <a href="index.php?page=dashboard"><?= mbpos_icon('dashboard') ?><span data-i18n="Dashboard">Dashboard</span></a>
      <a href="index.php?page=voucher_create" class="active" aria-current="page"><?= mbpos_icon('voucher_create') ?><span data-i18n="Create Voucher">Create Voucher</span></a>
      <a href="index.php?page=stock_list"><?= mbpos_icon('stock_list') ?><span data-i18n="Shipments">Shipments</span></a>
      <a href="index.php?page=voucher_list"><?= mbpos_icon('voucher_list') ?><span data-i18n="Voucher Ledger">Voucher Ledger</span></a>
      <?php if (is_admin() || is_developer()): ?>
        <a href="index.php?page=profit_loss"><?= mbpos_icon('profit_loss') ?><span data-i18n="Profit & Loss">Profit &amp; Loss</span></a>
        <a href="index.php?page=branches"><?= mbpos_icon('branches') ?><span data-i18n="Branches">Branches</span></a>
        <a href="index.php?page=admin_dashboard"><?= mbpos_icon('admin_dashboard') ?><span data-i18n="Administration">Administration</span></a>
      <?php endif; ?>
    </nav>
    
    <div class="side-health" aria-label="System Online">
      <span class="system-dot"></span>
      <b style="font-size:10px" data-i18n="Online">Online</b>
      <div style="font-size:9px;color:var(--vc-muted);margin-top:5px" data-i18n="Live records available">Live records available</div>
    </div>
  </aside>
  <button type="button" class="nav-scrim" id="nav-scrim" aria-label="Close menu"></button>

  <!-- Main Viewport -->
  <main class="main">
    <!-- Topbar Header -->
    <header class="top">
      <button type="button" class="menu-toggle" id="nav-toggle" aria-controls="voucher-sidebar" aria-expanded="false" aria-label="Open menu"><?= mbpos_icon('menu') ?></button>
      <div class="search-wrap">
        <?= mbpos_icon('search') ?>
        <input id="global-search" class="search" placeholder="Search tracking number or voucher" data-i18n-placeholder="Search tracking number or voucher" aria-label="Global search">
      </div>
      <button type="button" id="language-toggle" class="language-toggle" aria-label="Switch language" title="Switch language">
        <span class="language-option language-option-en">EN</span>
        <span class="language-option language-option-mm">မြန်မာ</span>
      </button>
      <button type="button" id="install-pwa" class="pwa-install-button" hidden data-i18n="Install app">Install app</button>
      <div class="operator">
        <div class="avatar"><?= htmlspecialchars(strtoupper(substr($_SESSION['username'] ?? 'OP', 0, 2)), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="operator-details">
          <b style="font-size:11px"><?= htmlspecialchars($_SESSION['username'] ?? 'Operator', ENT_QUOTES, 'UTF-8') ?></b>
          <small style="display:block;color:var(--vc-muted);font-size:9px"><?= htmlspecialchars(ucfirst($_SESSION['role'] ?? 'Operator'), ENT_QUOTES, 'UTF-8') ?><?php if (!empty($user_info['branch_name'])): ?> · <?= htmlspecialchars($user_info['branch_name'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?></small>
        </div>
      </div>
    </header>

    <div class="content">
      <!-- Page Head -->
      <div class="head">
        <div>
          <h1><span data-i18n="Create Delivery Voucher">Create Delivery Voucher</span> <span class="chip">V5</span></h1>
          <p data-i18n="Enter shipment details and review totals before creating the voucher.">Enter shipment details and review totals before creating the voucher.</p>
        </div>
        <div class="voucher-code" style="font-size:10px;color:var(--vc-muted);text-align:right">
          <span data-i18n="Voucher Code">Voucher Code</span><br>
          <b id="voucherNo" style="font-size:13px;color:var(--vc-text)"><?= htmlspecialchars($preview_voucher_code) ?></b>
        </div>
      </div>

      <!-- Step Wizard -->
      <div class="steps glass">
        <div class="step active" id="st-1"><b>1</b>Sender & Receiver</div>
        <div class="step" id="st-2"><b>2</b>Routing</div>
        <div class="step" id="st-3"><b>3</b>Items</div>
        <div class="step" id="st-4"><b>4</b>Review</div>
        <div class="step" id="st-5"><b>5</b>Create</div>
      </div>

      <!-- V5 Form & Workspace -->
      <form id="voucher-form" method="POST" action="index.php?page=voucher_create" data-protect-unsaved="true">
        <?= csrf_input() ?>
        <div class="workspace">
          
          <!-- LEFT COLUMN: Input Fields -->
          <section>
            <div class="grid">
              
              <!-- Card 1: Sender Details -->
              <div class="card glass">
                <h3><span class="section-title"><span class="section-number">1</span><span data-i18n="New Sender Details">New Sender Details</span></span><small data-i18n="Manual entry">Manual entry</small></h3>

                <div class="field">
                  <label>Full Name <span class="required">*</span></label>
                  <input id="sender" name="sender_name" maxlength="120" autocomplete="name" placeholder="Sender full name" value="<?= htmlspecialchars($duplicate_voucher['sender_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="field">
                  <label>Phone Number <span class="required">*</span></label>
                  <input id="senderPhone" name="sender_phone" maxlength="32" autocomplete="tel" inputmode="tel" placeholder="Phone number" value="<?= htmlspecialchars($duplicate_voucher['sender_phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="secure-hint"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg><span data-i18n="Validated securely when submitted">Validated securely when submitted</span></div>
              </div>

              <!-- Card 2: Receiver Details -->
              <div class="card glass">
                <h3><span class="section-title"><span class="section-number">2</span><span data-i18n="New Receiver Details">New Receiver Details</span></span><small data-i18n="Manual entry">Manual entry</small></h3>

                <div class="field">
                  <label>Full Name <span class="required">*</span></label>
                  <input id="receiver" name="receiver_name" maxlength="120" autocomplete="name" placeholder="Receiver full name" value="<?= htmlspecialchars($duplicate_voucher['receiver_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="field">
                  <label>Phone Number <span class="required">*</span></label>
                  <input id="receiverPhone" name="receiver_phone" maxlength="32" autocomplete="tel" inputmode="tel" placeholder="Phone number" value="<?= htmlspecialchars($duplicate_voucher['receiver_phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="field">
                  <label>Delivery Address <span class="required">*</span></label>
                  <textarea id="address" name="receiver_address" maxlength="500" autocomplete="street-address" placeholder="Delivery address" required><?= htmlspecialchars($duplicate_voucher['receiver_address'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
              </div>

              <!-- Card 3: Routing & Logistics -->
              <div class="card glass route">
                <h3><span class="section-title"><span class="section-number">3</span><span data-i18n="Routing & Logistics">Routing &amp; Logistics</span></span><small data-i18n="Destination and service">Destination and service</small></h3>
                <div class="routegrid">
                  
                  <div class="field">
                    <label>Origin Point</label>
                    <select id="origin" name="origin_point">
                      <option value="">Select origin branch</option>
                      <?php if (!empty($all_branches)): ?>
                        <?php foreach ($all_branches as $b): ?>
                          <option value="<?= htmlspecialchars($b['branch_name']) ?>" <?= ($b['id'] == ($user_info['branch_id'] ?? 0)) ? 'selected' : '' ?>>Myanmar → <?= htmlspecialchars($b['branch_name']) ?></option>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </select>
                  </div>

                  <div class="field">
                    <label>Destination Region <span class="required">*</span></label>
                    <select id="region" name="destination_region_id" required>
                      <option value="">Select destination</option>
                      <?php foreach ($all_regions as $reg): ?>
                        <option value="<?= $reg['id'] ?>" data-prefix="<?= htmlspecialchars($reg['prefix'] ?? 'MBV') ?>" data-seq="<?= $reg['current_sequence'] ?? 0 ?>" data-name="<?= htmlspecialchars($reg['region_name']) ?>" <?= ($duplicate_voucher && ($duplicate_voucher['destination_region_id'] ?? 0) == $reg['id']) ? 'selected' : '' ?>>
                          <?= htmlspecialchars($reg['region_name']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="field">
                    <label>Destination Branch <span class="required">*</span></label>
                    <select id="branch" name="destination_branch_id" required>
                      <option value="">Select region first</option>
                    </select>
                  </div>

                  <div class="field">
                    <label>Delivery Type <span class="required">*</span></label>
                    <div class="delivery">
                      <?php foreach ($delivery_types as $idx => $dt): ?>
                        <label class="radio">
                          <input type="radio" name="delivery_type" value="<?= htmlspecialchars($dt) ?>" <?= ($duplicate_voucher && ($duplicate_voucher['delivery_type'] ?? '') === $dt) ? 'checked' : '' ?> required>
                          <?= htmlspecialchars($dt) ?>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </div>

                  <div class="field">
                    <label>Currency <span class="required">*</span></label>
                    <div class="currencies">
                      <?php foreach ($currencies as $idx => $curr): ?>
                        <button type="button" class="<?= ($duplicate_voucher && ($duplicate_voucher['currency'] ?? '') === $curr) ? 'active' : '' ?>" data-currency="<?= htmlspecialchars($curr) ?>" aria-pressed="<?= ($duplicate_voucher && ($duplicate_voucher['currency'] ?? '') === $curr) ? 'true' : 'false' ?>">
                          <?= htmlspecialchars($curr) ?>
                        </button>
                      <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="currency" id="currency_input" value="<?= htmlspecialchars($duplicate_voucher['currency'] ?? '') ?>">
                  </div>

                  <div class="field">
                    <label>Additional Delivery Charge</label>
                    <input id="extra" name="delivery_charge" type="number" min="0" max="999999999" step=".01" value="<?= isset($duplicate_voucher['delivery_charge']) ? htmlspecialchars($duplicate_voucher['delivery_charge']) : '' ?>" placeholder="0.00" inputmode="decimal">
                  </div>

                  <div class="field full">
                    <label>Operational Notes</label>
                    <textarea id="notes" name="notes" maxlength="500" placeholder="Operational notes (optional)..."><?= htmlspecialchars($duplicate_voucher['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                  </div>

                </div>
              </div>

            </div>

            <!-- Card 4: Item Breakdown -->
            <div class="card glass items">
              <h3><span class="section-title"><span class="section-number">4</span><span data-i18n="Item Breakdown">Item Breakdown</span></span><small data-i18n="Package calculation">Package calculation</small></h3>
              <div class="tablewrap">
                <table class="table">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Item Category</th>
                      <th>Weight (kg)</th>
                      <th>Price / Kg</th>
                      <th>Total</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody id="rows"></tbody>
                </table>
              </div>
              <button type="button" class="add" id="addRow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg><span data-i18n="Add Item">Add Item</span></button>
              <div class="totals">
                <span>Total Weight <strong id="weightTotal">0.00 kg</strong></span>
                <span>Grand Total <strong id="grandTotal">0 MMK</strong></span>
              </div>
            </div>

          </section>

          <!-- RIGHT COLUMN: Order Summary, Live Tracking & Voucher Preview Paper -->
          <aside class="right">
            
            <!-- Summary Card -->
            <div class="summary glass">
              <h3>Order Summary <span class="status">Pending</span></h3>
              <div class="sum"><span>Subtotal</span><b id="subtotal">0 MMK</b></div>
              <div class="sum"><span data-i18n="Delivery Charge">Delivery Charge</span><b id="extraSum">0</b></div>
              <div class="grand">
                <span>Grand Total</span>
                <strong id="grand2">0 MMK</strong>
              </div>
              <div class="actions">
                <button type="submit" class="primary" id="create"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg><span data-i18n="Create Ledger Entry">Create Ledger Entry</span></button>
                <button type="button" class="secondary" id="print"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg><span data-i18n="Preview and Print Voucher">Preview / Print Voucher</span></button>
              </div>
              <div id="requestStatus" class="request-status"></div>
            </div>

            <!-- Tracking Card -->
            <div class="tracking glass">
              <h3>Tracking & Voucher Info</h3>
              <div class="line">Voucher ID</div>
              <b id="pvoucher"><?= htmlspecialchars($preview_voucher_code) ?></b>
              <div class="line">Tracking Number</div>
              <b id="tracking"><?= htmlspecialchars($preview_tracking_no) ?></b>
              <div class="track-bottom">
                <div>
                  <b data-i18n="Shipment Status">Shipment Status</b>
                  <div class="status-list">
                    <div class="status-item current" data-i18n="Not created">Not created</div>
                    <div class="status-item" data-i18n="Tracking begins after creation">Tracking begins after creation</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Live Printable Voucher Paper Preview -->
            <div class="preview glass">
              <div class="previewbar">
                <b style="font-size:12px">Voucher Preview</b>
                <button type="button" class="secondary" id="print2" style="width:auto;padding:6px 12px;font-size:10px">Print</button>
              </div>
              <div class="paper" id="paper">
                <div class="phead">
                  <div class="pbrand">
                    <img class="pbrand-logo" src="bg.jpg" alt="MB Logistics logo">
                    <div>
                      <strong>MBLOGISTICS</strong>
                      <div style="font-size:7px;color:#64748b;font-weight:700">FAST · SAFE · GLOBAL</div>
                    </div>
                  </div>
                  <small>SHIPMENT VOUCHER<br><b style="color:var(--blue)">V5 · ORIGINAL PRINT STYLE</b></small>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:7px;margin-top:9px;color:#64748b">
                  <span>Voucher: <b id="pno" style="color:#0f172a"><?= htmlspecialchars($preview_voucher_code) ?></b></span>
                  <span>Tracking: <b id="ptracking" style="color:#0f172a"><?= htmlspecialchars($preview_tracking_no) ?></b></span>
                </div>
                
                <h4>Sender & Receiver</h4>
                <div class="pgrid">
                  <div class="pbox">
                    <b>Sender</b>
                    <span id="psender">Not entered</span><br>
                    <span id="psphone" style="color:#64748b">Not entered</span>
                  </div>
                  <div class="pbox">
                    <b>Receiver</b>
                    <span id="preceiver">Not entered</span><br>
                    <span id="prphone" style="color:#64748b">Not entered</span>
                  </div>
                  <div class="pbox full">
                    <b>Delivery Address</b>
                    <span id="paddress">Not entered</span>
                  </div>
                </div>

                <h4>Routing & Logistics</h4>
                <div class="pbox full">
                  <span id="porigin">Select origin</span> · <span id="pregion">Select destination</span> · <span id="pbranch">Select branch</span><br>
                  <span id="pdelivery" style="font-weight:700;color:#126bd3">Select delivery type</span> · <span id="pcurrency" style="font-weight:700">Select currency</span>
                </div>

                <h4>Item Breakdown</h4>
                <table>
                  <thead>
                    <tr>
                      <th>Category</th>
                      <th>Weight</th>
                      <th>Price/Kg</th>
                      <th>Total</th>
                    </tr>
                  </thead>
                  <tbody id="prows"></tbody>
                </table>

                <div class="paper-total">
                  <span>Total Weight: <span id="pweight">0.00 kg</span></span>
                  <span>Grand Total: <strong id="ptotal">0 MMK</strong></span>
                </div>

                <div class="legacy-print-notes">
                  <b>Important Notes</b>
                  <ol>
                    <li>ဥပဒေနှင့်မလွတ်ကင်းသောပစ္စည်းများ လုံးဝ လက်မခံပါ။</li>
                    <li>ပစ္စည်းအမျိုးအစားကို မှန်ကန်စွာကြေညာပြီး လုံခြုံရေးစစ်ဆေးမှုကို လက်ခံရပါမည်။</li>
                    <li>အစားအသောက်နှင့် ပျက်စီးလွယ်သောပစ္စည်းများကို သတ်မှတ်ချက်အတိုင်း ထုပ်ပိုးရပါမည်။</li>
                  </ol>
                </div>
                
                <div style="border-top:1px solid #dce5ef;padding-top:8px;font-size:7px;color:#64748b;line-height:1.4">
                  Keep this voucher for tracking and enquiry support. Delivery timing may vary by destination. Operational notes are visible to authorized logistics personnel only.
                </div>
              </div>
            </div>

          </aside>

        </div>
      </form>

    </div>
  </main>
</div>

<!-- Floating Toast Container -->
<div class="toast" id="toast"></div>

<script>
// Data injected from backend database
const categories = <?= json_encode(array_values($item_types_list), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const allBranches = <?= json_encode(array_values($all_branches), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const allRegions = <?= json_encode(array_values($all_regions), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

let currency = <?= json_encode((string) ($duplicate_voucher['currency'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
let rowId = 0;

const byId = id => document.getElementById(id);
const money = n => Number(n || 0).toLocaleString(undefined, {maximumFractionDigits: 2}) + (currency ? " " + currency : "");
const safeText = s => String(s ?? "").replace(/[<>]/g, "");
const escapeHtml = s => String(s ?? "").replace(/[&<>"']/g, character => ({
  "&": "&amp;",
  "<": "&lt;",
  ">": "&gt;",
  '"': "&quot;",
  "'": "&#039;"
})[character]);
const toast = t => {
  const x = byId("toast");
  x.textContent = t;
  x.classList.add("show");
  setTimeout(() => x.classList.remove("show"), 2500);
};

// Add Item Row Function
function addRow(data = {}) {
  const tr = document.createElement("tr");
  tr.dataset.row = ++rowId;
  const selCategory = data.category || "";
  const weightVal = (data.weight !== undefined && data.weight !== null && data.weight !== "") ? data.weight : "";
  const priceVal = (data.price !== undefined && data.price !== null && data.price !== "") ? data.price : "";
  tr.innerHTML = `
    <td class="num" data-label="Item"></td>
    <td data-label="Item Category">
      <select class="category" name="item_type[]" required aria-label="Item category">
        <option value="">Select category</option>
        ${categories.map(c => `<option value="${escapeHtml(c)}" ${c === selCategory ? "selected" : ""}>${escapeHtml(c)}</option>`).join("")}
      </select>
    </td>
    <td data-label="Weight (kg)">
      <input class="weight" name="item_kg[]" type="number" min="0.01" max="99999" step="0.01" value="${weightVal}" placeholder="0.00" required inputmode="decimal" aria-label="Weight in kilograms">
    </td>
    <td data-label="Price / kg">
      <input class="price" name="item_price_per_kg[]" type="number" min="0" max="999999999" step="0.01" value="${priceVal}" placeholder="0.00" required inputmode="decimal" aria-label="Price per kilogram">
    </td>
    <td class="lineTotal" data-label="Total" style="font-weight:700;color:#1e293b;text-align:right">0</td>
    <td data-label="Action"><button type="button" class="remove" title="Remove item" aria-label="Remove item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2m-9 0 1 15h8l1-15M10 10v7m4-7v7"/></svg></button></td>
  `;
  byId("rows").appendChild(tr);
  
  tr.querySelectorAll("input,select").forEach(el => el.addEventListener("input", update));
  tr.querySelector(".remove").onclick = () => {
    if (document.querySelectorAll("#rows tr").length <= 1) {
      toast("Voucher requires at least one item");
      return;
    }
    tr.remove();
    update();
  };
  update();
}

// Real-time calculation and preview synchronization
function update() {
  let subtotal = 0, totalWeight = 0;
  const prows = [];
  
  document.querySelectorAll("#rows tr").forEach((tr, i) => {
    tr.querySelector(".num").textContent = i + 1;
    const w = Math.max(0, +tr.querySelector(".weight").value || 0);
    const p = Math.max(0, +tr.querySelector(".price").value || 0);
    const line = w * p;
    subtotal += line;
    totalWeight += w;
    tr.querySelector(".lineTotal").textContent = Number(line).toLocaleString();
    prows.push(`<tr><td>${escapeHtml(tr.querySelector(".category").value)}</td><td>${w.toFixed(2)} kg</td><td>${Number(p).toLocaleString()}</td><td>${Number(line).toLocaleString()}</td></tr>`);
  });

  const extra = Math.max(0, +byId("extra").value || 0);
  const grand = subtotal + extra;

  // Order summary & form totals
  byId("subtotal").textContent = money(subtotal);
  byId("extraSum").textContent = money(extra);
  byId("grandTotal").textContent = money(grand);
  byId("grand2").textContent = money(grand);
  byId("weightTotal").textContent = totalWeight.toFixed(2) + " kg";

  // Paper preview
  byId("pweight").textContent = totalWeight.toFixed(2) + " kg";
  byId("ptotal").textContent = money(grand);
  byId("prows").innerHTML = prows.join("") || `<tr><td colspan="4" style="text-align:center;color:#94a3b8">No items added</td></tr>`;

  byId("psender").textContent = safeText(byId("sender").value) || "Not entered";
  byId("psphone").textContent = safeText(byId("senderPhone").value) || "Not entered";
  byId("preceiver").textContent = safeText(byId("receiver").value) || "Not entered";
  byId("prphone").textContent = safeText(byId("receiverPhone").value) || "Not entered";
  byId("paddress").textContent = safeText(byId("address").value) || "Not entered";
  byId("porigin").textContent = safeText(byId("origin").value) || "Select origin";

  const regSel = byId("region");
  const regText = regSel.selectedIndex >= 0 && regSel.options[regSel.selectedIndex] ? regSel.options[regSel.selectedIndex].text : "Select destination";
  byId("pregion").textContent = regText;

  const branchSel = byId("branch");
  const branchText = branchSel.selectedIndex >= 0 && branchSel.options[branchSel.selectedIndex] ? branchSel.options[branchSel.selectedIndex].text : "Select branch";
  byId("pbranch").textContent = branchText;

  byId("pcurrency").textContent = currency || "Select currency";
  const deliveryChecked = document.querySelector('input[name="delivery_type"]:checked');
  byId("pdelivery").textContent = deliveryChecked ? deliveryChecked.value : "Select delivery type";

  // Step state tracking
  updateSteps();
}

function updateSteps() {
  const hasCustomer = (byId("sender").value.trim() && byId("receiver").value.trim() && byId("address").value.trim());
  const hasRouting = (byId("region").value && byId("branch").value);
  const hasItems = [...document.querySelectorAll("#rows tr")].some(tr =>
    tr.querySelector(".category").value && Number(tr.querySelector(".weight").value) > 0
  );

  byId("st-1").className = "step " + (hasCustomer ? "active" : "");
  byId("st-2").className = "step " + (hasRouting ? "active" : "");
  byId("st-3").className = "step " + (hasItems ? "active" : "");
  byId("st-4").className = "step " + (hasCustomer && hasRouting && hasItems ? "active" : "");
}

// Load branches dynamically based on region selection
function loadBranches(regionId) {
  const select = byId("branch");
  select.innerHTML = '<option value="">Select branch</option>';
  if (!regionId) return;

  const filtered = allBranches.filter(b => b.region_id == regionId);
  if (filtered.length > 0) {
    filtered.forEach(b => {
      const opt = document.createElement("option");
      opt.value = b.id;
      opt.textContent = b.branch_name;
      select.appendChild(opt);
    });
  } else {
    const opt = document.createElement("option");
    opt.value = "";
    opt.textContent = "No branches available";
    opt.disabled = true;
    select.appendChild(opt);
  }
}

// Currency Button Handlers
function bindCurrencies() {
  document.querySelectorAll("[data-currency]").forEach(b => {
    b.onclick = () => {
      document.querySelectorAll("[data-currency]").forEach(x => x.classList.remove("active"));
      document.querySelectorAll("[data-currency]").forEach(x => x.setAttribute("aria-pressed", "false"));
      b.classList.add("active");
      b.setAttribute("aria-pressed", "true");
      currency = b.dataset.currency;
      byId("currency_input").value = currency;
      update();
    };
  });
}

// Client-side Validation before POST
function validate() {
  const required = [
    ["sender", "Sender full name"],
    ["senderPhone", "Sender phone number"],
    ["receiver", "Receiver full name"],
    ["receiverPhone", "Receiver phone number"],
    ["address", "Delivery address"],
    ["region", "Destination region"],
    ["branch", "Destination branch"]
  ];

  for (const [id, label] of required) {
    const el = byId(id);
    if (!el || !el.value.trim()) {
      toast(label + " is required");
      if (el) el.focus();
      return false;
    }
  }

  if (!currency) {
    toast("Select a currency");
    document.querySelector("[data-currency]")?.focus();
    return false;
  }

  const deliveryType = document.querySelector('input[name="delivery_type"]:checked');
  if (!deliveryType) {
    toast("Select a delivery type");
    document.querySelector('input[name="delivery_type"]')?.focus();
    return false;
  }

  const rows = document.querySelectorAll("#rows tr");
  if (!rows.length) {
    toast("Add at least one item breakdown");
    return false;
  }

  for (const tr of rows) {
    const category = tr.querySelector(".category");
    if (!category.value) {
      toast("Select an item category");
      category.focus();
      return false;
    }
    const w = parseFloat(tr.querySelector(".weight").value) || 0;
    if (w <= 0) {
      toast("Each item requires a weight greater than 0 kg");
      tr.querySelector(".weight").focus();
      return false;
    }
  }

  return true;
}

// Form Submit Handler
document.getElementById("voucher-form").addEventListener("submit", function(e) {
  if (!validate()) {
    e.preventDefault();
    return false;
  }
  const btn = byId("create");
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Creating Ledger Entry…';
  byId("requestStatus").className = "request-status ok";
  byId("requestStatus").textContent = "Submitting secure voucher transaction…";
});

// Print handlers
byId("print").onclick = () => window.print();
byId("print2").onclick = () => window.print();

// Setup Event Listeners
byId("addRow").addEventListener("click", () => addRow());
byId("extra").addEventListener("input", update);
["sender", "senderPhone", "receiver", "receiverPhone", "address", "notes", "origin"].forEach(id => {
  const el = byId(id);
  if (el) el.addEventListener("input", update);
});

byId("region").addEventListener("change", function() {
  loadBranches(this.value);
  update();
});
byId("branch").addEventListener("change", update);
document.addEventListener("change", e => {
  if (e.target.name === "delivery_type") update();
});

const voucherPage = byId("voucher-page");
const navToggle = byId("nav-toggle");
const navScrim = byId("nav-scrim");
const setNavigationOpen = open => {
  voucherPage.classList.toggle("nav-open", open);
  navToggle.setAttribute("aria-expanded", open ? "true" : "false");
  navToggle.setAttribute("aria-label", open ? "Close menu" : "Open menu");
};
navToggle.addEventListener("click", () => setNavigationOpen(!voucherPage.classList.contains("nav-open")));
navScrim.addEventListener("click", () => setNavigationOpen(false));
document.addEventListener("keydown", event => {
  if (event.key === "Escape") setNavigationOpen(false);
});

// Initialize Defaults
bindCurrencies();
const duplicateItems = <?= json_encode($duplicate_items) ?>;
const duplicateRegion = <?= json_encode($duplicate_voucher['destination_region_id'] ?? null) ?>;
const duplicateBranch = <?= json_encode($duplicate_voucher['destination_branch_id'] ?? null) ?>;
const duplicateCurrency = <?= json_encode($duplicate_voucher['currency'] ?? null) ?>;

if (duplicateRegion) {
  byId("region").value = duplicateRegion;
  loadBranches(duplicateRegion);
  if (duplicateBranch) byId("branch").value = duplicateBranch;
}

if (duplicateCurrency) {
  currency = duplicateCurrency;
  byId("currency_input").value = currency;
  document.querySelectorAll("[data-currency]").forEach(b => {
    b.classList.toggle("active", b.dataset.currency === currency);
  });
}

if (duplicateItems && duplicateItems.length > 0) {
  duplicateItems.forEach(item => addRow(item));
  toast("Loaded voucher data for duplication. Review and create new entry.");
} else {
  addRow();
}
update();
</script>

<?php
include_template("footer", ["page" => "voucher_create"]);
?>
