<?php
// pos/voucher_create.php - MBLOGISTICS POS V5 Interface
// Modern, enterprise-grade delivery voucher management with real-time live voucher preview

require_once 'config.php';
require_once 'includes/functions.php';

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

$all_regions = [];
$all_branches = [];
$currencies = [];
$item_types_list = [];
$delivery_types = [];

$region_result = mysqli_query($connection, "SELECT id, region_name, prefix, current_sequence FROM regions ORDER BY region_name");
if ($region_result) while ($row = mysqli_fetch_assoc($region_result)) $all_regions[] = $row;
$branch_result = mysqli_query($connection, "SELECT id, branch_name, region_id FROM branches ORDER BY branch_name");
if ($branch_result) while ($row = mysqli_fetch_assoc($branch_result)) $all_branches[] = $row;
$currency_result = mysqli_query($connection, "SELECT code FROM currencies ORDER BY code");
if ($currency_result) while ($row = mysqli_fetch_assoc($currency_result)) $currencies[] = $row['code'];
if (empty($currencies)) $currencies = ['MMK', 'RM', 'SGD', 'USD'];

$item_type_result = mysqli_query($connection, "SELECT name FROM item_types ORDER BY name");
if ($item_type_result) while ($row = mysqli_fetch_assoc($item_type_result)) $item_types_list[] = $row['name'];
if (empty($item_types_list)) {
    $item_types_list = ['Laptop', 'Bag', 'Book', 'Document', 'Fancy Gold', 'Power Bank', 'Medicine', 'Food / Snacks', 'Phone', 'Electronics', 'Clothing', 'Cosmetics'];
}

$delivery_type_result = mysqli_query($connection, "SELECT name FROM delivery_types ORDER BY name");
if ($delivery_type_result) while ($row = mysqli_fetch_assoc($delivery_type_result)) $delivery_types[] = $row['name'];
if (empty($delivery_types)) {
    $delivery_types = ['ကားဂိတ်တင်', 'စင်တာမှ လွှဲပို့', 'စာတိုက်တင်', 'မလပးကွား ပို့ဆောင်', 'မော်လမြိုင် ရုံးထုတ်', 'ရန်ကုန် ရုံးထုတ်', 'အထူးဘိုင့်'];
}

// Generate preliminary draft voucher code & tracking number for live preview
$default_dest_prefix = !empty($all_regions) ? ($all_regions[0]['prefix'] ?? 'MBV') : 'MBV';
$default_dest_seq = !empty($all_regions) ? (($all_regions[0]['current_sequence'] ?? 841) + 1) : 842;
$preview_voucher_code = 'MBV-' . date('Y') . '-' . str_pad($default_dest_seq, 6, '0', STR_PAD_LEFT);
$preview_tracking_no = 'MBT-' . date('Y') . '-' . rand(100000, 999999);
$preview_date_time = date('Y-m-d H:i');

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sender_type = $_POST['sender_type'] ?? 'new';
    $sender_customer_id = ($sender_type === 'existing') ? intval($_POST['sender_customer_id'] ?? 0) : null;
    $sender_name = trim($_POST['sender_name']);
    $sender_phone = trim($_POST['sender_phone']);
    
    $receiver_type = $_POST['receiver_type'] ?? 'new';
    $receiver_customer_id = ($receiver_type === 'existing') ? intval($_POST['receiver_customer_id'] ?? 0) : null;
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

<!-- Select2 CDN -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
:root{
  --bg:#f3f7fc;--surface:rgba(255,255,255,.88);--white:#fff;--text:#10233f;
  --muted:#718198;--line:#dce7f3;--blue:#1677ff;--cyan:#20b8f5;--green:#12b981;
  --amber:#f59e0b;--red:#ef4444;--shadow:0 18px 55px rgba(32,75,125,.11);--r:20px
}
*{box-sizing:border-box}html{scroll-behavior:smooth}
body{margin:0;color:var(--text);font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:radial-gradient(circle at 78% -5%,#d9f2ff 0,transparent 30%),radial-gradient(circle at -5% 55%,#e7efff 0,transparent 32%),var(--bg)!important;padding-bottom:0!important}
button,input,select,textarea{font:inherit}button{cursor:pointer}
.app{min-height:100vh;display:grid;grid-template-columns:232px 1fr}
.sidebar{position:sticky;top:0;height:100vh;padding:20px 15px;background:rgba(255,255,255,.72);backdrop-filter:blur(25px);border-right:1px solid var(--line);z-index:30}
.brand{display:flex;gap:10px;align-items:center;padding:3px 10px 25px}.mark{width:38px;height:30px;position:relative}
.mark:before,.mark:after{content:"";position:absolute;transform:skew(-28deg);border-radius:4px}
.mark:before{left:2px;top:2px;width:13px;height:27px;background:linear-gradient(160deg,#096ff0,#79dcff)}
.mark:after{left:17px;top:7px;width:13px;height:22px;background:linear-gradient(160deg,#1167d8,#bdefff)}
.brand b{font-size:15px;line-height:1.2;color:#10233f;display:block}.brand small{display:block;color:var(--blue);font-size:9px;font-weight:900;letter-spacing:0.5px}
.nav{display:grid;gap:6px}
.nav a,.nav button{border:0;background:transparent;text-align:left;color:#56677e;padding:11px 13px;border-radius:13px;font-size:12px;font-weight:700;display:flex;align-items:center;text-decoration:none;transition:all .2s ease;cursor:pointer}
.nav a:hover,.nav button:hover{background:rgba(22,119,255,0.06);color:var(--blue)}
.nav a.active,.nav button.active{color:#fff!important;background:linear-gradient(135deg,#126ff4,#1cb8fa)!important;box-shadow:0 11px 25px rgba(22,119,255,.24)!important}
.side-health{position:absolute;bottom:18px;left:15px;right:15px;padding:14px;border:1px solid var(--line);border-radius:17px;background:linear-gradient(145deg,#fff,#edf7ff);cursor:pointer;transition:transform .2s ease}
.side-health:hover{transform:translateY(-2px)}
.pulse{display:inline-block;width:8px;height:8px;border-radius:50%;background:#14c995;box-shadow:0 0 0 5px #e0faf2;margin-right:7px;animation:pulse-ring 2s infinite}
@keyframes pulse-ring{0%{box-shadow:0 0 0 0 rgba(20,201,149,.4)}70%{box-shadow:0 0 0 8px rgba(20,201,149,0)}100%{box-shadow:0 0 0 0 rgba(20,201,149,0)}}
.main{min-width:0}
.top{height:74px;display:flex;align-items:center;gap:16px;padding:13px 27px;position:sticky;top:0;z-index:20;background:rgba(255,255,255,.67);backdrop-filter:blur(20px);border-bottom:1px solid var(--line)}
.search{height:44px;flex:1;max-width:600px;border:1px solid var(--line);border-radius:14px;background:#fff;padding:0 15px;outline:0;box-shadow:0 5px 20px rgba(45,90,140,.05);font-size:12px;color:var(--text)}
.search:focus{border-color:#65b8ff;box-shadow:0 0 0 3px #e7f5ff}
.operator{margin-left:auto;display:flex;align-items:center;gap:9px}
.avatar{width:38px;height:38px;border-radius:50%;display:grid;place-items:center;color:#fff;font-size:11px;font-weight:900;background:linear-gradient(135deg,#132c4d,#6696c9);box-shadow:0 4px 10px rgba(19,44,77,.15)}
.content{max-width:1650px;margin:auto;padding:25px}
.alert{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:12px 18px;margin-bottom:18px;border:1px solid #f6dca4;border-radius:15px;background:linear-gradient(100deg,#fffaf0,#fff);box-shadow:0 8px 25px rgba(120,80,15,.06);transition:all .2s ease}
.alert:hover{box-shadow:0 10px 30px rgba(120,80,15,.1)}
.alert strong{font-size:12px;color:#92400e}.alert span{font-size:11px;color:#8a6a28}
.loadbar{width:170px;height:7px;border-radius:10px;background:#f1eadb;overflow:hidden}.loadbar i{display:block;width:95%;height:100%;background:linear-gradient(90deg,#f6b31a,#ef6d35);border-radius:inherit;animation:load-shimmer 2.5s infinite}
@keyframes load-shimmer{0%,100%{opacity:1}50%{opacity:0.8}}
.head{display:flex;justify-content:space-between;align-items:end;gap:20px;margin-bottom:18px}.head h1{margin:0;font-size:26px;font-weight:900;letter-spacing:-0.5px}.head p{margin:5px 0 0;color:var(--muted);font-size:12px}
.chip{display:inline-flex;padding:3px 9px;border-radius:8px;background:#e7f2ff;color:var(--blue);font-size:11px;font-weight:900;vertical-align:middle;margin-left:6px}
.workspace{display:grid;grid-template-columns:minmax(0,1fr) 410px;gap:18px}
.glass{background:var(--surface);border:1px solid rgba(211,225,240,.95);border-radius:var(--r);box-shadow:var(--shadow);backdrop-filter:blur(18px)}
.steps{display:grid;grid-template-columns:repeat(5,1fr);gap:6px;padding:9px;margin-bottom:18px}
.step{text-align:center;padding:9px;color:#8190a5;font-size:10px;border-radius:11px;transition:all .2s ease;user-select:none}
.step b{display:grid;place-items:center;width:24px;height:24px;margin:0 auto 5px;border-radius:50%;background:#edf2f7;color:#64748b;font-size:10px;transition:all .2s ease}
.step.active{background:#edf7ff;color:var(--blue);font-weight:900}
.step.active b{color:#fff;background:linear-gradient(135deg,#1676ff,#23b8f6);box-shadow:0 4px 10px rgba(22,118,255,.25)}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.card{padding:18px}
.card h3{margin:0 0 15px;font-size:14px;font-weight:800;display:flex;justify-content:space-between;align-items:center}
.card h3 small{font-weight:500;color:var(--muted);font-size:11px}
.field{margin-bottom:12px}
.field label{display:block;margin-bottom:6px;font-size:10px;font-weight:800;color:#596a82;text-transform:uppercase;letter-spacing:0.4px}
.field input,.field select,.field textarea{width:100%;padding:10px 11px;border:1px solid var(--line);border-radius:11px;background:#fff;color:var(--text);outline:0;font-size:12px;transition:border-color .2s,box-shadow .2s}
.field input:focus,.field select:focus,.field textarea:focus{border-color:#65b8ff;box-shadow:0 0 0 3px #e7f5ff}
.field textarea{min-height:80px;resize:vertical}
.required{color:var(--red);font-weight:bold}
.secure-hint{display:flex;gap:7px;align-items:center;color:#68809d;font-size:9px;margin-top:4px}
.lock{color:var(--green);font-size:10px}
.route{grid-column:1/-1}
.routegrid{display:grid;grid-template-columns:1.2fr 1fr 1fr;gap:12px}
.full{grid-column:1/-1}
.delivery{display:grid;grid-template-columns:repeat(2,1fr);gap:7px}
.radio{display:flex;gap:7px;align-items:center;border:1px solid var(--line);padding:8px 10px;border-radius:10px;background:#fff;font-size:10px;cursor:pointer;transition:all .2s ease}
.radio:hover{border-color:#93c5fd;background:#f8faff}
.radio input{accent-color:var(--blue);cursor:pointer}
.currencies{display:flex;gap:6px}
.currencies button{flex:1;border:1px solid var(--line);background:#fff;border-radius:9px;padding:9px 4px;font-size:10px;cursor:pointer;font-weight:700;color:#56677e;transition:all .2s ease}
.currencies button:hover{background:#f8faff;border-color:#93c5fd}
.currencies button.active{background:#eaf5ff;border-color:#7abfff;color:var(--blue);font-weight:900;box-shadow:0 2px 8px rgba(22,119,255,.15)}
.items{margin-top:18px}
.tablewrap{overflow:auto}
.table{width:100%;border-collapse:separate;border-spacing:0 6px;min-width:620px;font-size:10px}
.table th{color:#8491a4;text-align:left;padding:0 8px;font-size:9px;text-transform:uppercase;letter-spacing:0.5px}
.table td{padding:8px;background:#fff;border-top:1px solid var(--line);border-bottom:1px solid var(--line)}
.table td:first-child{border-left:1px solid var(--line);border-radius:9px 0 0 9px;font-weight:700;color:#64748b;width:32px;text-align:center}
.table td:last-child{border-right:1px solid var(--line);border-radius:0 9px 9px 0;width:40px;text-align:center}
.table input,.table select{width:100%;border:0;outline:0;background:transparent;font-size:11px;font-weight:600;color:var(--text)}
.table input:focus,.table select:focus{outline:2px solid #93c5fd;border-radius:6px;background:#f8faff}
.remove{border:1px solid #ffd4d4;background:#fff4f4;color:#d33;padding:4px 8px;border-radius:8px;cursor:pointer;font-weight:bold;transition:all .2s ease}
.remove:hover{background:#ffe2e2;color:#b91c1c}
.add{margin-top:8px;border:1px dashed #86bfff;color:var(--blue);background:#f4faff;border-radius:10px;padding:9px 13px;font-size:10px;font-weight:900;cursor:pointer;transition:all .2s ease}
.add:hover{background:#e8f4ff;border-color:#1677ff}
.totals{display:flex;justify-content:flex-end;gap:30px;margin-top:14px;font-size:10px;color:var(--muted)}
.totals strong{display:block;color:#126bd3;font-size:17px;margin-top:2px;font-weight:900}
.right{display:grid;gap:18px;align-content:start}
.summary,.tracking{padding:18px}
.summary h3,.tracking h3{margin:0 0 13px;font-size:14px;font-weight:800;display:flex;justify-content:space-between;align-items:center}
.status{float:right;background:#ddfaf1;color:#078f6c;border-radius:20px;padding:4px 9px;font-size:9px;font-weight:800}
.sum{display:flex;justify-content:space-between;padding:7px 0;color:#697a91;font-size:11px;border-bottom:1px dashed #f1f5f9}
.grand{display:flex;justify-content:space-between;align-items:center;margin:12px -3px 8px;padding:13px;border-radius:13px;background:linear-gradient(110deg,#eef7ff,#e7fbff);font-weight:900;border:1px solid #d0e8ff}
.grand strong{color:#086cdb;font-size:20px}
.actions{display:grid;gap:8px;margin-top:13px}
.primary,.secondary{width:100%;padding:11px;border-radius:11px;font-size:11px;font-weight:900;cursor:pointer;transition:all .2s ease}
.primary{border:0;color:#fff;background:linear-gradient(135deg,#1477ff,#19b6fa);box-shadow:0 10px 22px rgba(20,119,255,.22)}
.primary:hover{opacity:0.95;transform:translateY(-1px);box-shadow:0 12px 25px rgba(20,119,255,.28)}
.secondary{border:1px solid var(--line);background:#fff;color:#53647c}
.secondary:hover{background:#f8fafc;border-color:#cbd5e1}
.request-status{margin-top:10px;font-size:9px;color:var(--muted);min-height:15px}
.request-status.ok{color:#07936e;font-weight:700}
.request-status.err{color:#d33;font-weight:700}
.tracking .line{font-size:9px;color:var(--muted);margin-top:8px}
.tracking b{font-size:11px;color:#1e293b;letter-spacing:0.5px}
.barcode{height:50px;margin:12px 0;background:repeating-linear-gradient(90deg,#182a43 0 2px,transparent 2px 4px,#182a43 4px 5px,transparent 5px 8px);border-radius:3px}
.track-bottom{display:flex;justify-content:space-between;align-items:center}
.qr{width:64px;height:64px;border:7px solid #fff;box-shadow:0 0 0 1px var(--line);background:repeating-conic-gradient(#15253b 0 8%,#fff 0 16%)}
.preview{padding:14px}
.previewbar{display:flex;justify-content:space-between;align-items:center;padding:2px 5px 12px}
.paper{background:#fff;border:1px solid #d8e2ed;box-shadow:0 12px 30px rgba(35,65,100,.12);padding:18px;min-height:600px;border-radius:8px}
.phead{display:flex;justify-content:space-between;border-bottom:2px solid #1595ef;padding-bottom:10px}
.phead strong{color:#126bd3;font-size:17px;display:block;font-weight:900}
.phead small{font-size:8px;font-weight:700;color:#64748b;text-align:right}
.pgrid{display:grid;grid-template-columns:1fr 1fr;gap:7px}
.pbox{border:1px solid #dce5ef;border-radius:6px;padding:8px;font-size:8px;min-height:48px;background:#fafcff}
.pbox b{display:block;font-size:9px;margin-bottom:4px;color:#2b415e;font-weight:800}
.pbox.full{grid-column:1/-1}
.paper h4{font-size:9px;color:#126bd3;margin:13px 0 6px;font-weight:800;text-transform:uppercase;letter-spacing:0.3px}
.paper table{width:100%;border-collapse:collapse;font-size:8px}
.paper th,.paper td{border:1px solid #dce5ef;padding:5px 6px;text-align:left}
.paper th{background:#f1f5f9;color:#475569;font-weight:800}
.paper-total{display:flex;justify-content:space-between;padding:10px 0;font-size:9px;font-weight:900}
.paper-total strong{font-size:14px;color:#126bd3}
.loading{opacity:.65;pointer-events:none}
.spinner{display:inline-block;width:10px;height:10px;border:2px solid #cfe4fb;border-top-color:var(--blue);border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;margin-right:4px}
@keyframes spin{to{transform:rotate(360deg)}}
.toast{position:fixed;right:20px;bottom:20px;padding:12px 16px;background:#10233f;color:#fff;border-radius:12px;box-shadow:var(--shadow);font-size:11px;font-weight:600;opacity:0;transform:translateY(10px);transition:.25s ease;z-index:9999;pointer-events:none}
.toast.show{opacity:1;transform:none}
.seg-pill{display:inline-flex;background:#edf2f7;border-radius:8px;padding:2px;gap:2px}
.seg-pill button{border:0;background:transparent;padding:3px 9px;border-radius:6px;font-size:9px;font-weight:700;color:#6b7c93;transition:all .2s ease}
.seg-pill button.active{background:#fff;color:var(--blue);box-shadow:0 1px 4px rgba(0,0,0,.08)}
.customer-search-wrap{margin-bottom:10px}
.select2-container--default .select2-selection--single{border:1px solid var(--line)!important;border-radius:11px!important;height:38px!important;padding:4px 6px!important}
.select2-container--default .select2-selection--single .select2-selection__arrow{height:36px!important}
@media(max-width:1200px){.workspace{grid-template-columns:1fr}.right{grid-template-columns:1fr 1fr}.preview{grid-column:1/-1}}
@media(max-width:850px){.app{display:block}.sidebar{display:none}.top{padding:12px 16px}.content{padding:16px}.grid,.routegrid{grid-template-columns:1fr}.route,.full{grid-column:auto}.right{grid-template-columns:1fr}.delivery{grid-template-columns:1fr}.head{align-items:start;flex-direction:column}.steps{overflow-x:auto;-webkit-overflow-scrolling:touch}.step{min-width:100px}.search{max-width:none}}
@media print{
  body{background:#fff!important;padding:0!important;margin:0!important}
  .sidebar,.top,.alert,.head,.steps,.grid,.summary,.tracking,.previewbar,#voucher-form,.seg-pill,.add,.actions,.request-status{display:none!important}
  .content,.workspace,.right,.preview{display:block!important;padding:0!important;margin:0!important;width:100%!important;max-width:none!important}
  .paper{border:0!important;box-shadow:none!important;min-height:auto!important;padding:0!important;display:block!important}
}
</style>

<div class="app">
  <!-- Sidebar (Desktop Enterprise Nav) -->
  <aside class="sidebar">
    <div class="brand">
      <div class="mark"></div>
      <div>
        <b>MBLOGISTICS</b>
        <small>POS V5 · FAST · SAFE · GLOBAL</small>
      </div>
    </div>
    
    <nav class="nav">
      <a href="index.php?page=dashboard">⌂ &nbsp; Dashboard</a>
      <a href="index.php?page=voucher_create" class="active">▣ &nbsp; Create Voucher</a>
      <a href="index.php?page=stock_list">◇ &nbsp; Shipments</a>
      <a href="index.php?page=customer_list">♙ &nbsp; Customers</a>
      <a href="index.php?page=voucher_list">▤ &nbsp; Ledger</a>
      <a href="index.php?page=profit_loss">▥ &nbsp; Reports</a>
      <a href="index.php?page=branches">⌂ &nbsp; Branches</a>
      <a href="index.php?page=admin_dashboard">⚙ &nbsp; Settings</a>
    </nav>
    
    <div class="side-health" aria-label="System Online">
      <span class="pulse"></span>
      <b style="font-size:10px">System Online</b>
      <div style="font-size:9px;color:var(--muted);margin-top:5px">API · DB · Cache health monitored</div>
    </div>
  </aside>

  <!-- Main Viewport -->
  <main class="main">
    <!-- Topbar Header -->
    <header class="top">
      <input id="global-search" class="search" placeholder="⌕  Search customer, tracking number, or voucher..." data-i18n-placeholder="Search customer, tracking number, or voucher..." aria-label="Global search">
      <button type="button" id="language-toggle" class="language-toggle" aria-label="Switch language" title="Switch language">
        <span class="language-option language-option-en">EN</span>
        <span class="language-option language-option-mm">မြန်မာ</span>
      </button>
      <button type="button" id="install-pwa" class="pwa-install-button" hidden data-i18n="Install app">Install app</button>
      <div class="operator">
        <div class="avatar"><?= strtoupper(substr($_SESSION['username'] ?? 'SF', 0, 2)) ?></div>
        <div>
          <b style="font-size:11px"><?= htmlspecialchars($_SESSION['username'] ?? 'Stephan Filip') ?></b>
          <small style="display:block;color:var(--muted);font-size:9px"><?= htmlspecialchars(ucfirst($_SESSION['role'] ?? 'Operator')) ?> · <?= htmlspecialchars($user_info['branch_name'] ?? 'Yangon') ?></small>
        </div>
      </div>
    </header>

    <div class="content">
      <!-- Page Head -->
      <div class="head">
        <div>
          <h1>Create Delivery Voucher <span class="chip">V5</span></h1>
          <p>Create secure logistics shipment records and generate ledger entries in real time.</p>
        </div>
        <div style="font-size:10px;color:var(--muted);text-align:right">
          Voucher Code<br>
          <b id="voucherNo" style="font-size:13px;color:var(--text)"><?= htmlspecialchars($preview_voucher_code) ?></b>
        </div>
      </div>

      <!-- Step Wizard -->
      <div class="steps glass">
        <div class="step active" id="st-1"><b>1</b>Customer</div>
        <div class="step" id="st-2"><b>2</b>Routing</div>
        <div class="step" id="st-3"><b>3</b>Items</div>
        <div class="step" id="st-4"><b>4</b>Review</div>
        <div class="step" id="st-5"><b>5</b>Create</div>
      </div>

      <!-- V5 Form & Workspace -->
      <form id="voucher-form" method="POST" action="index.php?page=voucher_create">
        <div class="workspace">
          
          <!-- LEFT COLUMN: Input Fields -->
          <section>
            <div class="grid">
              
              <!-- Card 1: Sender Details -->
              <div class="card glass">
                <h3>
                  <span>① Sender Details</span>
                  <div class="seg-pill">
                    <button type="button" class="active" data-toggle="sender" data-val="new">+ New</button>
                    <button type="button" data-toggle="sender" data-val="existing">⌕ Existing</button>
                  </div>
                </h3>
                <input type="hidden" name="sender_type" id="sender_type" value="new">
                
                <div id="existing_sender_wrap" class="customer-search-wrap" style="display:none;">
                  <div class="field">
                    <label>Select Existing Customer <span class="required">*</span></label>
                    <select id="sender_customer_id" name="sender_customer_id" class="customer-search" style="width:100%"></select>
                  </div>
                </div>

                <div class="field">
                  <label>Full Name <span class="required">*</span></label>
                  <input id="sender" name="sender_name" maxlength="120" autocomplete="name" placeholder="Enter sender full name" required>
                </div>
                <div class="field">
                  <label>Phone Number <span class="required">*</span></label>
                  <input id="senderPhone" name="sender_phone" maxlength="32" autocomplete="tel" inputmode="tel" placeholder="+95 9 123 456789" required>
                </div>
                <div class="secure-hint"><span class="lock">●</span> Validated before submission · server-side normalization required</div>
              </div>

              <!-- Card 2: Receiver Details -->
              <div class="card glass">
                <h3>
                  <span>② Receiver Details</span>
                  <div class="seg-pill">
                    <button type="button" class="active" data-toggle="receiver" data-val="new">+ New</button>
                    <button type="button" data-toggle="receiver" data-val="existing">⌕ Existing</button>
                  </div>
                </h3>
                <input type="hidden" name="receiver_type" id="receiver_type" value="new">

                <div id="existing_receiver_wrap" class="customer-search-wrap" style="display:none;">
                  <div class="field">
                    <label>Select Existing Customer <span class="required">*</span></label>
                    <select id="receiver_customer_id" name="receiver_customer_id" class="customer-search" style="width:100%"></select>
                  </div>
                </div>

                <div class="field">
                  <label>Full Name <span class="required">*</span></label>
                  <input id="receiver" name="receiver_name" maxlength="120" autocomplete="name" placeholder="Enter receiver full name" required>
                </div>
                <div class="field">
                  <label>Phone Number <span class="required">*</span></label>
                  <input id="receiverPhone" name="receiver_phone" maxlength="32" autocomplete="tel" inputmode="tel" placeholder="+61 412 345 678" required>
                </div>
                <div class="field">
                  <label>Delivery Address <span class="required">*</span></label>
                  <textarea id="address" name="receiver_address" maxlength="500" autocomplete="street-address" placeholder="Enter complete delivery address" required></textarea>
                </div>
              </div>

              <!-- Card 3: Routing & Logistics -->
              <div class="card glass route">
                <h3>③ Routing & Logistics <small>Destination + service rules</small></h3>
                <div class="routegrid">
                  
                  <div class="field">
                    <label>Origin Point</label>
                    <select id="origin" name="origin_point">
                      <?php if (!empty($all_branches)): ?>
                        <?php foreach ($all_branches as $b): ?>
                          <option value="<?= htmlspecialchars($b['branch_name']) ?>" <?= ($b['id'] == ($user_info['branch_id'] ?? 0)) ? 'selected' : '' ?>>Myanmar → <?= htmlspecialchars($b['branch_name']) ?></option>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <option>Myanmar → Yangon</option>
                        <option>Myanmar → Mandalay</option>
                        <option>Myanmar → Mawlamyine</option>
                      <?php endif; ?>
                    </select>
                  </div>

                  <div class="field">
                    <label>Destination Region <span class="required">*</span></label>
                    <select id="region" name="destination_region_id" required>
                      <option value="">Select destination</option>
                      <?php foreach ($all_regions as $reg): ?>
                        <option value="<?= $reg['id'] ?>" data-prefix="<?= htmlspecialchars($reg['prefix'] ?? 'MBV') ?>" data-seq="<?= $reg['current_sequence'] ?? 0 ?>" data-name="<?= htmlspecialchars($reg['region_name']) ?>">
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
                          <input type="radio" name="delivery_type" value="<?= htmlspecialchars($dt) ?>" <?= $idx === 0 ? 'checked' : '' ?>>
                          <?= htmlspecialchars($dt) ?>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </div>

                  <div class="field">
                    <label>Currency <span class="required">*</span></label>
                    <div class="currencies">
                      <?php foreach ($currencies as $idx => $curr): ?>
                        <button type="button" class="<?= $idx === 0 ? 'active' : '' ?>" data-currency="<?= htmlspecialchars($curr) ?>">
                          <?= htmlspecialchars($curr) ?>
                        </button>
                      <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="currency" id="currency_input" value="<?= htmlspecialchars($currencies[0] ?? 'MMK') ?>">
                  </div>

                  <div class="field">
                    <label>Additional Delivery Charge</label>
                    <input id="extra" name="delivery_charge" type="number" min="0" max="999999999" step=".01" value="0" inputmode="decimal">
                  </div>

                  <div class="field full">
                    <label>Operational Notes</label>
                    <textarea id="notes" name="notes" maxlength="500" placeholder="Fragile, special handling, internal operational note..."></textarea>
                  </div>

                </div>
              </div>

            </div>

            <!-- Card 4: Item Breakdown -->
            <div class="card glass items">
              <h3>④ Item Breakdown <small>Dynamic package calculation</small></h3>
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
              <button type="button" class="add" id="addRow">＋ Add Item</button>
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
              <div class="sum"><span>Delivery Charge</span><b id="deliveryCharge">0 MMK</b></div>
              <div class="sum"><span>Additional Charge</span><b id="extraSum">0 MMK</b></div>
              <div class="sum"><span>Discount</span><b>0 MMK</b></div>
              <div class="grand">
                <span>Grand Total</span>
                <strong id="grand2">0 MMK</strong>
              </div>
              <div class="actions">
                <button type="submit" class="primary" id="create">▣ Create Ledger Entry</button>
                <button type="button" class="secondary" id="draft">▢ Save Draft</button>
                <button type="button" class="secondary" id="print">◉ Preview / Print Voucher</button>
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
              <div class="barcode"></div>
              <div class="track-bottom">
                <div style="font-size:10px">
                  <b>Shipment Status</b>
                  <div style="margin-top:8px;color:#159c75">● Created</div>
                  <div style="color:#a7b2c0">│ Picked Up<br>│ In Transit<br>│ Delivered</div>
                </div>
                <div class="qr"></div>
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
                  <div>
                    <strong>MBLOGISTICS</strong>
                    <div style="font-size:7px;color:#64748b;font-weight:700">FAST · SAFE · GLOBAL</div>
                  </div>
                  <small>LOGISTICS DELIVERY VOUCHER<br><b style="color:var(--blue)">V5 ENTERPRISE</b></small>
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
                  <span id="porigin">Myanmar → Yangon</span> · <span id="pregion">Select destination</span> · <span id="pbranch">Select branch</span><br>
                  <span id="pdelivery" style="font-weight:700;color:var(--blue)">ကားဂိတ်တင်</span> · <span id="pcurrency" style="font-weight:700">MMK</span>
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

<!-- jQuery and Select2 JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// Data injected from backend database
const categories = <?= json_encode($item_types_list) ?>;
const allBranches = <?= json_encode($all_branches) ?>;
const allRegions = <?= json_encode($all_regions) ?>;

let currency = "<?= htmlspecialchars($currencies[0] ?? 'MMK') ?>";
let rowId = 0;

const $ = id => document.getElementById(id);
const money = n => Number(n || 0).toLocaleString() + " " + currency;
const safeText = s => String(s ?? "").replace(/[<>]/g, "");
const toast = t => {
  const x = $("toast");
  x.textContent = t;
  x.classList.add("show");
  setTimeout(() => x.classList.remove("show"), 2500);
};

// Add Item Row Function
function addRow(data = {category: (categories[0] || "Document"), weight: 1, price: 5000}) {
  const tr = document.createElement("tr");
  tr.dataset.row = ++rowId;
  tr.innerHTML = `
    <td class="num"></td>
    <td>
      <select class="category" name="item_type[]">
        ${categories.map(c => `<option value="${safeText(c)}" ${c === data.category ? "selected" : ""}>${safeText(c)}</option>`).join("")}
      </select>
    </td>
    <td>
      <input class="weight" name="item_kg[]" type="number" min="0.01" max="99999" step="0.01" value="${data.weight}">
    </td>
    <td>
      <input class="price" name="item_price_per_kg[]" type="number" min="0" max="999999999" step="0.01" value="${data.price}">
    </td>
    <td class="lineTotal" style="font-weight:700;color:#1e293b;text-align:right">0</td>
    <td><button type="button" class="remove" title="Remove Item">×</button></td>
  `;
  $("rows").appendChild(tr);
  
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
    prows.push(`<tr><td>${safeText(tr.querySelector(".category").value)}</td><td>${w.toFixed(2)} kg</td><td>${Number(p).toLocaleString()}</td><td>${Number(line).toLocaleString()}</td></tr>`);
  });

  const extra = Math.max(0, +$("extra").value || 0);
  const grand = subtotal + extra;

  // Order summary & form totals
  $("subtotal").textContent = money(subtotal);
  $("deliveryCharge").textContent = money(0);
  $("extraSum").textContent = money(extra);
  $("grandTotal").textContent = money(grand);
  $("grand2").textContent = money(grand);
  $("weightTotal").textContent = totalWeight.toFixed(2) + " kg";

  // Paper preview
  $("pweight").textContent = totalWeight.toFixed(2) + " kg";
  $("ptotal").textContent = money(grand);
  $("prows").innerHTML = prows.join("") || `<tr><td colspan="4" style="text-align:center;color:#94a3b8">No items added</td></tr>`;

  $("psender").textContent = safeText($("sender").value) || "Not entered";
  $("psphone").textContent = safeText($("senderPhone").value) || "Not entered";
  $("preceiver").textContent = safeText($("receiver").value) || "Not entered";
  $("prphone").textContent = safeText($("receiverPhone").value) || "Not entered";
  $("paddress").textContent = safeText($("address").value) || "Not entered";
  $("porigin").textContent = safeText($("origin").value) || "Myanmar → Yangon";

  const regSel = $("region");
  const regText = regSel.selectedIndex >= 0 && regSel.options[regSel.selectedIndex] ? regSel.options[regSel.selectedIndex].text : "Select destination";
  $("pregion").textContent = regText;

  const branchSel = $("branch");
  const branchText = branchSel.selectedIndex >= 0 && branchSel.options[branchSel.selectedIndex] ? branchSel.options[branchSel.selectedIndex].text : "Select branch";
  $("pbranch").textContent = branchText;

  $("pcurrency").textContent = currency;
  const deliveryChecked = document.querySelector('input[name="delivery_type"]:checked');
  $("pdelivery").textContent = deliveryChecked ? deliveryChecked.value : "ကားဂိတ်တင်";

  // Step state tracking
  updateSteps();
}

function updateSteps() {
  const hasCustomer = ($("sender").value.trim() && $("receiver").value.trim() && $("address").value.trim());
  const hasRouting = ($("region").value && $("branch").value);
  const hasItems = document.querySelectorAll("#rows tr").length > 0;

  $("st-1").className = "step " + (hasCustomer ? "active" : "");
  $("st-2").className = "step " + (hasRouting ? "active" : "");
  $("st-3").className = "step " + (hasItems ? "active" : "");
  $("st-4").className = "step " + (hasCustomer && hasRouting && hasItems ? "active" : "");
}

// Load branches dynamically based on region selection
function loadBranches(regionId) {
  const select = $("branch");
  select.innerHTML = "<option value="">Select branch</option>";
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
    opt.value = "1";
    opt.textContent = "Main Branch";
    select.appendChild(opt);
  }

  // Update dynamic voucher preview number based on region sequence
  const regSel = $("region");
  const opt = regSel.options[regSel.selectedIndex];
  if (opt && opt.dataset.prefix) {
    const prefix = opt.dataset.prefix;
    const seq = (parseInt(opt.dataset.seq, 10) || 0) + 1;
    const code = `${prefix}-${new Date().getFullYear()}-${String(seq).padStart(6, "0")}`;
    $("voucherNo").textContent = code;
    $("pvoucher").textContent = code;
    $("pno").textContent = code;
  }
}

// Currency Button Handlers
function bindCurrencies() {
  document.querySelectorAll("[data-currency]").forEach(b => {
    b.onclick = () => {
      document.querySelectorAll("[data-currency]").forEach(x => x.classList.remove("active"));
      b.classList.add("active");
      currency = b.dataset.currency;
      $("currency_input").value = currency;
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
    const el = $(id);
    if (!el || !el.value.trim()) {
      toast(label + " is required");
      if (el) el.focus();
      return false;
    }
  }

  const rows = document.querySelectorAll("#rows tr");
  if (!rows.length) {
    toast("Add at least one item breakdown");
    return false;
  }

  for (const tr of rows) {
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
  const btn = $("create");
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Creating Ledger Entry…';
  $("requestStatus").className = "request-status ok";
  $("requestStatus").textContent = "Submitting secure voucher transaction…";
});

// Draft Save & Restore
$("draft").onclick = () => {
  const draftData = {
    sender: $("sender").value,
    senderPhone: $("senderPhone").value,
    receiver: $("receiver").value,
    receiverPhone: $("receiverPhone").value,
    address: $("address").value,
    region: $("region").value,
    branch: $("branch").value,
    extra: $("extra").value,
    notes: $("notes").value,
    currency: currency,
    items: [...document.querySelectorAll("#rows tr")].map(tr => ({
      category: tr.querySelector(".category").value,
      weight: tr.querySelector(".weight").value,
      price: tr.querySelector(".price").value
    }))
  };
  try {
    localStorage.setItem("mbpos_v5_draft", JSON.stringify(draftData));
    toast("Draft saved locally! You can resume anytime.");
  } catch(e) {
    toast("Could not save draft locally");
  }
};

// Print handlers
$("print").onclick = () => window.print();
$("print2").onclick = () => window.print();

// Customer Existing / New Toggle Pill
document.querySelectorAll(".seg-pill button").forEach(btn => {
  btn.onclick = function() {
    const toggle = this.dataset.toggle;
    const val = this.dataset.val;
    this.parentElement.querySelectorAll("button").forEach(b => b.classList.remove("active"));
    this.classList.add("active");
    $(toggle + "_type").value = val;

    const wrap = $( "existing_" + toggle + "_wrap" );
    if (val === "existing") {
      wrap.style.display = "block";
    } else {
      wrap.style.display = "none";
      $(`#${toggle}_customer_id`).val(null).trigger("change");
    }
  };
});

// Setup Event Listeners
$("extra").addEventListener("input", update);
["sender", "senderPhone", "receiver", "receiverPhone", "address", "notes", "origin"].forEach(id => {
  const el = $(id);
  if (el) el.addEventListener("input", update);
});

$("region").addEventListener("change", function() {
  loadBranches(this.value);
  update();
});
$("branch").addEventListener("change", update);
document.addEventListener("change", e => {
  if (e.target.name === "delivery_type") update();
});

// jQuery Select2 for Customer Search
$(document).ready(function() {
  $(".customer-search").select2({
    ajax: {
      url: "index.php?page=ajax_search_customers",
      dataType: "json",
      delay: 250,
      data: params => ({ q: params.term }),
      processResults: data => ({ results: data.results })
    },
    placeholder: "Search customer by name or phone...",
    minimumInputLength: 2,
    width: "100%"
  });

  $("#sender_customer_id").on("select2:select", function(e) {
    const data = e.params.data;
    if (data) {
      $("#sender").val(data.text ? data.text.split(" (")[0] : "");
      $("#senderPhone").val(data.phone || "");
      update();
    }
  });

  $("#receiver_customer_id").on("select2:select", function(e) {
    const data = e.params.data;
    if (data) {
      $("#receiver").val(data.text ? data.text.split(" (")[0] : "");
      $("#receiverPhone").val(data.phone || "");
      if (data.address) $("#address").val(data.address);
      update();
    }
  });
});

// Initialize Defaults
bindCurrencies();
if (allRegions.length > 0) {
  $("region").value = allRegions[0].id;
  loadBranches(allRegions[0].id);
}
addRow({category: "Laptop", weight: 2.5, price: 12000});
update();
</script>

<?php
include_template("footer", ["page" => "voucher_create"]);
?>
