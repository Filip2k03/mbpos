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

<!-- =========================================================================
     MBLOGISTICS POS V5 MASTER INTERFACE LAYOUT
     ========================================================================= -->
<div class="v5-layout-wrapper flex min-h-screen bg-[#eef2f6]">
    
    <!-- 1. V5 LEFT SIDEBAR (Desktop) -->
    <aside class="v5-sidebar hidden xl:flex flex-col w-[260px] bg-white border-r border-slate-200 sticky top-0 h-screen z-30 shadow-[2px_0_12px_rgba(0,0,0,0.02)] shrink-0">
        
        <!-- Brand Header -->
        <div class="p-5 pb-3 border-b border-slate-100 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#0b6ff5] to-[#2563eb] flex items-center justify-center text-white shadow-md shadow-blue-500/25 shrink-0">
                <!-- Folding M vector -->
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M4 4h3l5 8 5-8h3v16h-3.5V9.5L12 16.5 7.5 9.5V20H4V4z"/>
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-1.5">
                    <span class="text-lg font-black tracking-tight text-slate-900 leading-none">MBLOGISTICS</span>
                </div>
                <p class="text-[10px] font-bold text-slate-400 tracking-wider mt-1">Fast • Safe • Global</p>
            </div>
        </div>

        <!-- Navigation Menu Items -->
        <div class="py-4 flex-1 overflow-y-auto space-y-1">
            <a href="index.php?page=dashboard" class="v5-nav-item">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span>Dashboard</span>
            </a>

            <a href="index.php?page=voucher_create" class="v5-nav-item active">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Create Voucher</span>
            </a>

            <a href="index.php?page=stock_list" class="v5-nav-item">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                <span>Shipments</span>
            </a>

            <a href="index.php?page=customer_list" class="v5-nav-item">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <span>Customers</span>
            </a>

            <a href="index.php?page=voucher_list" class="v5-nav-item">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                <span>Ledger</span>
            </a>

            <a href="index.php?page=profit_loss" class="v5-nav-item">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                <span>Reports</span>
            </a>

            <a href="index.php?page=branches" class="v5-nav-item">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <span>Branches</span>
            </a>

            <a href="index.php?page=admin_dashboard" class="v5-nav-item">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span>Settings</span>
            </a>
        </div>

        <!-- Bottom Promotional 3D Widget -->
        <div class="p-4 border-t border-slate-100">
            <div class="bg-gradient-to-br from-blue-50 to-indigo-50/60 rounded-2xl p-4 border border-blue-100 text-center relative overflow-hidden">
                <!-- Mini Parcel Illustration -->
                <div class="w-16 h-16 mx-auto mb-2 relative">
                    <svg viewBox="0 0 64 64" fill="none" class="w-full h-full drop-shadow-md">
                        <path d="M32 6L54 18V46L32 58L10 46V18L32 6Z" fill="#3b82f6" fill-opacity="0.15"/>
                        <path d="M32 6L54 18L32 30L10 18L32 6Z" fill="#60a5fa"/>
                        <path d="M10 18L32 30V58L10 46V18Z" fill="#2563eb"/>
                        <path d="M54 18L32 30V58L54 46V18Z" fill="#1d4ed8"/>
                        <!-- Parcel tape -->
                        <path d="M26 9.5L48 21.5L42 24.8L20 12.8L26 9.5Z" fill="#f59e0b"/>
                        <path d="M20 12.8L26 16.1V44.1L20 40.8V12.8Z" fill="#d97706"/>
                    </svg>
                </div>
                <h4 class="text-xs font-extrabold text-slate-800 leading-tight">Global Shipping,<br>Closer to You</h4>
                <p class="text-[10px] text-slate-500 font-semibold mt-1">From Myanmar to the World</p>
                
                <div class="mt-3 pt-2.5 border-t border-blue-200/60 flex items-center justify-between text-[10px] text-slate-600 font-bold">
                    <span class="flex items-center gap-1.5 text-emerald-600">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        System Online
                    </span>
                    <span class="text-slate-400 font-mono">v5.0.0</span>
                </div>
            </div>
        </div>
    </aside>

    <!-- 2. MAIN WORKSPACE COLUMN -->
    <div class="flex-1 flex flex-col min-w-0">
        
        <!-- V5 Top Bar -->
        <header class="v5-topbar sticky top-0 z-20 bg-white/95 backdrop-blur-md border-b border-slate-200 flex items-center justify-between px-4 sm:px-6 h-[72px] shadow-sm">
            
            <!-- Global Search Pill -->
            <div class="flex items-center gap-3 flex-1 max-w-xl">
                <!-- Mobile brand trigger -->
                <a href="index.php?page=dashboard" class="xl:hidden flex items-center gap-2 mr-2">
                    <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center text-white font-bold text-sm">M</div>
                </a>

                <div class="v5-global-search flex items-center gap-2 w-full max-w-md px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl">
                    <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" placeholder="Search customer, tracking number, or voucher..." class="w-full bg-transparent border-none text-xs text-slate-700 font-medium focus:outline-none placeholder-slate-400">
                    <kbd class="hidden sm:inline-block text-[10px] font-mono text-slate-400 bg-white px-1.5 py-0.5 border border-slate-200 rounded">⌘ K</kbd>
                </div>
            </div>

            <!-- Top Right Profile & Controls -->
            <div class="flex items-center gap-3 sm:gap-4 shrink-0">
                
                <!-- Notification Bell -->
                <a href="index.php?page=notifications" class="relative p-2 rounded-xl text-slate-500 hover:text-blue-600 hover:bg-slate-100 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    <span class="absolute top-1.5 right-1.5 w-4 h-4 bg-rose-500 text-white rounded-full text-[9px] font-bold flex items-center justify-center border-2 border-white">3</span>
                </a>

                <!-- Theme Toggle Button -->
                <button type="button" class="p-2 rounded-xl text-slate-500 hover:text-amber-500 hover:bg-slate-100 transition-colors" title="Theme Settings">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </button>

                <!-- User Profile Pill -->
                <div class="flex items-center gap-2.5 px-2.5 py-1 rounded-xl hover:bg-slate-100 transition-colors cursor-pointer border border-transparent hover:border-slate-200">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-600 text-white flex items-center justify-center font-bold text-xs shadow-sm">
                        <?= strtoupper(substr($_SESSION['username'] ?? 'SF', 0, 2)) ?>
                    </div>
                    <div class="hidden md:block text-left">
                        <div class="text-xs font-bold text-slate-800 leading-tight"><?= htmlspecialchars($_SESSION['username'] ?? 'Stephan Filip') ?></div>
                        <div class="text-[10px] text-slate-400 font-medium leading-tight"><?= htmlspecialchars($_SESSION['user_type'] ?? 'Operator') ?></div>
                    </div>
                    <svg class="w-3.5 h-3.5 text-slate-400 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>

                <!-- Live Clock (MMT) -->
                <div class="hidden lg:flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700">
                    <span class="w-2 h-2 rounded-full bg-blue-500 animate-ping"></span>
                    <span id="v5-live-clock"><?= date('M d, Y H:i') ?> (MMT)</span>
                </div>

                <!-- Country Selector Pill -->
                <div class="flex items-center gap-2 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700">
                    <span class="text-base">🇲🇲</span>
                    <span class="hidden sm:inline">Myanmar</span>
                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
            </div>
        </header>

        <!-- 3. PAGE BODY: Create Delivery Voucher Workspace -->
        <main class="p-4 sm:p-6 lg:p-8 max-w-[1720px] w-full mx-auto space-y-6">
            
            <!-- Page Title Bar -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center text-white shadow-lg shadow-amber-500/20 shrink-0">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Create Delivery Voucher</h1>
                            <span class="px-2.5 py-0.5 rounded-full bg-blue-600 text-white text-xs font-black uppercase tracking-wider">V5</span>
                        </div>
                        <p class="text-xs sm:text-sm font-medium text-slate-500 mt-0.5">Fill in the details below to create a new shipment voucher.</p>
                    </div>
                </div>

                <!-- Voucher # & Draft Badge -->
                <div class="flex items-center gap-3 bg-slate-50 px-4 py-2 rounded-xl border border-slate-200">
                    <div class="text-right">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Voucher #</span>
                        <span class="text-sm sm:text-base font-extrabold text-slate-800 font-mono tracking-tight" id="top-voucher-number"><?= $preview_voucher_code ?></span>
                    </div>
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        Draft
                    </span>
                </div>
            </div>

            <!-- Stepper Wizard Progress Bar -->
            <div class="bg-white px-6 py-4 rounded-2xl border border-slate-200 shadow-sm overflow-x-auto">
                <div class="flex items-center justify-between min-w-[620px] relative">
                    <!-- Progress track line -->
                    <div class="absolute top-1/2 left-4 right-4 h-0.5 bg-slate-200 -translate-y-1/2 z-0"></div>
                    <div class="absolute top-1/2 left-4 w-1/6 h-0.5 bg-blue-600 -translate-y-1/2 z-0"></div>

                    <!-- Step 1 -->
                    <div class="flex items-center gap-2 relative z-10 bg-white pr-3">
                        <span class="w-7 h-7 rounded-full bg-blue-600 text-white flex items-center justify-center text-xs font-extrabold shadow-md shadow-blue-500/30">1</span>
                        <span class="text-xs font-bold text-blue-600">Sender</span>
                    </div>
                    <!-- Step 2 -->
                    <div class="flex items-center gap-2 relative z-10 bg-white px-3">
                        <span class="w-7 h-7 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center text-xs font-bold border border-slate-200">2</span>
                        <span class="text-xs font-semibold text-slate-500">Receiver</span>
                    </div>
                    <!-- Step 3 -->
                    <div class="flex items-center gap-2 relative z-10 bg-white px-3">
                        <span class="w-7 h-7 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center text-xs font-bold border border-slate-200">3</span>
                        <span class="text-xs font-semibold text-slate-500">Routing</span>
                    </div>
                    <!-- Step 4 -->
                    <div class="flex items-center gap-2 relative z-10 bg-white px-3">
                        <span class="w-7 h-7 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center text-xs font-bold border border-slate-200">4</span>
                        <span class="text-xs font-semibold text-slate-500">Items & Charges</span>
                    </div>
                    <!-- Step 5 -->
                    <div class="flex items-center gap-2 relative z-10 bg-white px-3">
                        <span class="w-7 h-7 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center text-xs font-bold border border-slate-200">5</span>
                        <span class="text-xs font-semibold text-slate-500">Review</span>
                    </div>
                    <!-- Step 6 -->
                    <div class="flex items-center gap-2 relative z-10 bg-white pl-3">
                        <span class="w-7 h-7 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center text-xs font-bold border border-slate-200">6</span>
                        <span class="text-xs font-semibold text-slate-500">Create Voucher</span>
                    </div>
                </div>
            </div>

            <!-- Main Form & Live Preview Grid -->
            <form action="index.php?page=voucher_create" method="POST" id="voucher-form" accept-charset="UTF-8">
                
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                    
                    <!-- Left & Middle: Form Cards Container (8 Cols on wide screens) -->
                    <div class="lg:col-span-8 space-y-6">
                        
                        <!-- Top Row: Sender (Card 1) & Receiver (Card 2) -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
                            <!-- CARD 1: 1. Sender Details -->
                            <div class="v5-card p-5 sm:p-6 space-y-4">
                                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                    <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        1. Sender Details
                                    </h3>
                                    
                                    <!-- Segmented Switcher -->
                                    <div class="v5-segmented-toggle">
                                        <button type="button" class="v5-segmented-btn" data-toggle="sender" data-val="existing">Existing</button>
                                        <button type="button" class="v5-segmented-btn active" data-toggle="sender" data-val="new">New</button>
                                    </div>
                                    <input type="hidden" name="sender_type" id="sender_type" value="new">
                                </div>

                                <!-- Existing Customer Search -->
                                <div id="existing_sender_fields" class="space-y-1.5 hidden">
                                    <label for="sender_customer_id" class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Search Customer</label>
                                    <select id="sender_customer_id" name="sender_customer_id" class="customer-search w-full"></select>
                                </div>

                                <!-- New Sender Inputs -->
                                <div id="new_sender_fields" class="space-y-3.5">
                                    <div>
                                        <label for="sender_name" class="block text-xs font-bold text-slate-700 mb-1">Full Name <span class="text-rose-500">*</span></label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                            </span>
                                            <input type="text" id="sender_name" name="sender_name" class="w-full rounded-xl border border-slate-200 bg-slate-50/50 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 py-2.5 pl-10 pr-3 text-xs font-semibold text-slate-800 transition-all placeholder-slate-400" placeholder="e.g. Ko Ko Win" required>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="sender_phone" class="block text-xs font-bold text-slate-700 mb-1">Phone Number <span class="text-rose-500">*</span></label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-base">
                                                🇲🇲
                                            </span>
                                            <input type="tel" id="sender_phone" name="sender_phone" class="w-full rounded-xl border border-slate-200 bg-slate-50/50 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 py-2.5 pl-9 pr-3 text-xs font-semibold text-slate-800 transition-all placeholder-slate-400" placeholder="+95 9 123 456789" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- CARD 2: 2. Receiver Details -->
                            <div class="v5-card p-5 sm:p-6 space-y-4">
                                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                    <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                        2. Receiver Details
                                    </h3>

                                    <!-- Segmented Switcher -->
                                    <div class="v5-segmented-toggle">
                                        <button type="button" class="v5-segmented-btn" data-toggle="receiver" data-val="existing">Existing</button>
                                        <button type="button" class="v5-segmented-btn active" data-toggle="receiver" data-val="new">New</button>
                                    </div>
                                    <input type="hidden" name="receiver_type" id="receiver_type" value="new">
                                </div>

                                <!-- Existing Customer Search -->
                                <div id="existing_receiver_fields" class="space-y-1.5 hidden">
                                    <label for="receiver_customer_id" class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Search Customer</label>
                                    <select id="receiver_customer_id" name="receiver_customer_id" class="customer-search w-full"></select>
                                </div>

                                <!-- New Receiver Inputs -->
                                <div id="new_receiver_fields" class="space-y-3">
                                    <div>
                                        <label for="receiver_name" class="block text-xs font-bold text-slate-700 mb-1">Full Name <span class="text-rose-500">*</span></label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                            </span>
                                            <input type="text" id="receiver_name" name="receiver_name" class="w-full rounded-xl border border-slate-200 bg-slate-50/50 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 py-2.5 pl-10 pr-3 text-xs font-semibold text-slate-800 transition-all placeholder-slate-400" placeholder="e.g. Aung San" required>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="receiver_phone" class="block text-xs font-bold text-slate-700 mb-1">Phone Number <span class="text-rose-500">*</span></label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                            </span>
                                            <input type="tel" id="receiver_phone" name="receiver_phone" class="w-full rounded-xl border border-slate-200 bg-slate-50/50 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 py-2.5 pl-10 pr-3 text-xs font-semibold text-slate-800 transition-all placeholder-slate-400" placeholder="+61 412 345 678" required>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="flex justify-between items-center mb-1">
                                            <label for="receiver_address" class="block text-xs font-bold text-slate-700">Delivery Address <span class="text-rose-500">*</span></label>
                                            <span class="text-[10px] font-mono text-slate-400" id="address-counter">0/500</span>
                                        </div>
                                        <div class="relative">
                                            <span class="absolute top-2.5 left-3 text-slate-400 pointer-events-none">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                            </span>
                                            <textarea id="receiver_address" name="receiver_address" rows="2" maxlength="500" class="w-full rounded-xl border border-slate-200 bg-slate-50/50 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 py-2.5 pl-9 pr-3 text-xs font-medium text-slate-800 transition-all placeholder-slate-400 resize-none" placeholder="e.g. 12 Elizabeth St, Melbourne VIC 3000, Australia" required></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CARD 3: 3. Routing & Logistics -->
                        <div class="v5-card p-5 sm:p-6 space-y-4">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    3. Routing & Logistics
                                </h3>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                                <!-- Origin Point -->
                                <div class="md:col-span-12">
                                    <label class="block text-xs font-bold text-slate-700 mb-1">Origin Point</label>
                                    <div class="flex items-center justify-between px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 shadow-sm">
                                        <div class="flex items-center gap-2">
                                            <span class="text-base">🇲🇲</span>
                                            <span><?= htmlspecialchars($user_info['region_name'] ?? 'Myanmar') ?></span>
                                            <span class="text-slate-400">&rarr;</span>
                                            <span><?= htmlspecialchars($user_info['branch_name'] ?? 'Yangon') ?></span>
                                        </div>
                                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </div>
                                    <input type="hidden" name="origin_region_id" value="<?= $user_info['region_id'] ?? 1 ?>">
                                    <input type="hidden" name="origin_branch_id" value="<?= $user_info['branch_id'] ?? 1 ?>">
                                </div>

                                <!-- Dest. Region -->
                                <div class="md:col-span-6">
                                    <label for="destination_region_id" class="block text-xs font-bold text-slate-700 mb-1">Dest. Region <span class="text-rose-500">*</span></label>
                                    <select id="destination_region_id" name="destination_region_id" class="w-full rounded-xl border border-slate-200 bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 py-2.5 px-3 text-xs font-semibold text-slate-800 shadow-sm" required>
                                        <option value="">Select Destination</option>
                                        <?php foreach ($all_regions as $reg): ?>
                                            <?php 
                                            // Flag mappings
                                            $name = $reg['region_name'];
                                            $flag = '🌐';
                                            if (stripos($name, 'Australia') !== false) $flag = '🇦🇺';
                                            elseif (stripos($name, 'Canada') !== false) $flag = '🇨🇦';
                                            elseif (stripos($name, 'Malaysia') !== false) $flag = '🇲🇾';
                                            elseif (stripos($name, 'New Zealand') !== false) $flag = '🇳🇿';
                                            elseif (stripos($name, 'Singapore') !== false) $flag = '🇸🇬';
                                            elseif (stripos($name, 'Thailand') !== false) $flag = '🇹🇭';
                                            elseif (stripos($name, 'United States') !== false || stripos($name, 'US') !== false) $flag = '🇺🇸';
                                            elseif (stripos($name, 'China') !== false) $flag = '🇨🇳';
                                            elseif (stripos($name, 'Japan') !== false) $flag = '🇯🇵';
                                            elseif (stripos($name, 'Korea') !== false) $flag = '🇰🇷';
                                            ?>
                                            <option value="<?= $reg['id'] ?>" data-prefix="<?= htmlspecialchars($reg['prefix'] ?? 'MBV') ?>" data-seq="<?= $reg['current_sequence'] ?? 0 ?>" data-name="<?= htmlspecialchars($name) ?>">
                                                <?= $flag ?> <?= htmlspecialchars($name) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Dest. Branch -->
                                <div class="md:col-span-6">
                                    <label for="destination_branch_id" class="block text-xs font-bold text-slate-700 mb-1">Dest. Branch <span class="text-rose-500">*</span></label>
                                    <select id="destination_branch_id" name="destination_branch_id" class="w-full rounded-xl border border-slate-200 bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 py-2.5 px-3 text-xs font-semibold text-slate-800 shadow-sm" required>
                                        <option value="">Select region first</option>
                                    </select>
                                </div>

                                <!-- Delivery Type (Burmese Options) -->
                                <div class="md:col-span-12">
                                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Delivery Type <span class="text-rose-500">*</span></label>
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                        <?php foreach ($delivery_types as $idx => $dt): ?>
                                            <label class="relative flex items-center gap-2 p-2 rounded-xl border border-slate-200 bg-slate-50/60 hover:bg-blue-50 hover:border-blue-300 cursor-pointer text-xs font-semibold text-slate-700 transition-all">
                                                <input type="radio" name="delivery_type" value="<?= htmlspecialchars($dt) ?>" <?= $idx === 0 ? 'checked' : '' ?> class="delivery-type-radio text-blue-600 focus:ring-blue-500">
                                                <span class="truncate"><?= htmlspecialchars($dt) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Currency -->
                                <div class="md:col-span-6">
                                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Currency <span class="text-rose-500">*</span></label>
                                    <div class="flex items-center gap-2">
                                        <?php foreach (['MMK', 'RM', 'SGD', 'USD'] as $curr): ?>
                                            <button type="button" class="currency-pill-btn flex-1 py-2 rounded-xl text-xs font-extrabold border transition-all <?= $curr === 'MMK' ? 'bg-blue-600 text-white border-blue-600 shadow-sm' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100' ?>" data-curr="<?= $curr ?>">
                                                <?= $curr ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="currency" id="currency" value="MMK">
                                </div>

                                <!-- Additional Delivery Charge -->
                                <div class="md:col-span-6">
                                    <label for="delivery_charge" class="block text-xs font-bold text-slate-700 mb-1.5">Additional Delivery Charge</label>
                                    <div class="flex items-center gap-2">
                                        <input type="number" step="0.01" id="delivery_charge" name="delivery_charge" class="w-full rounded-xl border border-slate-200 bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 py-2 px-3 text-xs font-bold text-slate-800 shadow-sm" value="0">
                                        <span class="px-3 py-2 bg-slate-100 border border-slate-200 rounded-xl text-xs font-bold text-slate-600 selected-currency-label">MMK</span>
                                    </div>
                                </div>

                                <!-- Operational Notes -->
                                <div class="md:col-span-12">
                                    <div class="flex justify-between items-center mb-1">
                                        <label for="notes" class="block text-xs font-bold text-slate-700">Operational Notes</label>
                                        <span class="text-[10px] font-mono text-slate-400" id="notes-counter">0/500</span>
                                    </div>
                                    <textarea id="notes" name="notes" rows="2" maxlength="500" class="w-full rounded-xl border border-slate-200 bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 py-2 px-3 text-xs font-medium text-slate-800 shadow-sm placeholder-slate-400 resize-none" placeholder="e.g. Handle with care, fragile, special request..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- CARD 4: 4. Item Breakdown -->
                        <div class="v5-card p-5 sm:p-6 space-y-4">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                    4. Item Breakdown
                                </h3>
                                <button type="button" id="add-item-btn" class="px-3 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-sm flex items-center gap-1.5 transition-all">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                    Add Row
                                </button>
                            </div>

                            <!-- Item Table -->
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse" id="items-table">
                                    <thead>
                                        <tr class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400 border-b border-slate-100">
                                            <th class="py-2.5 px-2 w-8">#</th>
                                            <th class="py-2.5 px-3">Item Category</th>
                                            <th class="py-2.5 px-3 w-32">Weight (kg)</th>
                                            <th class="py-2.5 px-3 w-32">Price / Kg</th>
                                            <th class="py-2.5 px-3 w-36">Total (<span class="selected-currency-label">MMK</span>)</th>
                                            <th class="py-2.5 px-2 w-16 text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="items-tbody" class="divide-y divide-slate-100 text-xs">
                                        <!-- Row 1 Injected via JS or PHP -->
                                    </tbody>
                                </table>
                            </div>

                            <!-- Quick Category Tag Chips -->
                            <div class="space-y-2 pt-2 border-t border-slate-100">
                                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Quick Category Select</span>
                                
                                <!-- English Row -->
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <button type="button" class="v5-category-chip" data-cat="Bag">👜 Bag</button>
                                    <button type="button" class="v5-category-chip" data-cat="Book">📖 Book</button>
                                    <button type="button" class="v5-category-chip" data-cat="Document">📄 Document</button>
                                    <button type="button" class="v5-category-chip" data-cat="Fancy Gold">🌟 Fancy Gold</button>
                                    <button type="button" class="v5-category-chip active" data-cat="Laptop">💻 Laptop</button>
                                    <button type="button" class="v5-category-chip" data-cat="Power Bank">🔋 Power Bank</button>
                                </div>
                                
                                <!-- Burmese Row -->
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <button type="button" class="v5-category-chip" data-cat="ဆေးဝါး">💊 ဆေးဝါး</button>
                                    <button type="button" class="v5-category-chip" data-cat="မုန့်">🍪 မုန့်</button>
                                    <button type="button" class="v5-category-chip" data-cat="ဖုန်း">📱 ဖုန်း</button>
                                    <button type="button" class="v5-category-chip" data-cat="လျှပ်စစ်ပစ္စည်း">🔌 လျှပ်စစ်ပစ္စည်း</button>
                                    <button type="button" class="v5-category-chip" data-cat="အဝတ်အထည်">👕 အဝတ်အထည်</button>
                                    <button type="button" class="v5-category-chip" data-cat="အလှကုန်">💄 အလှကုန်</button>
                                </div>
                            </div>

                            <!-- Bottom Summary of Card 4 -->
                            <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                                <div>
                                    <span class="text-slate-400 font-bold">Total Weight:</span>
                                    <strong class="text-slate-800 text-sm ml-1" id="card4-total-weight">2.50 kg</strong>
                                </div>
                                <div>
                                    <span class="text-slate-400 font-bold">Grand Total:</span>
                                    <strong class="text-blue-600 text-base ml-1" id="card4-grand-total">30,000 MMK</strong>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Right Column: Order Summary (Card 5), Tracking (Card 6), and Live Voucher Preview Sheet (4 Cols) -->
                    <div class="lg:col-span-4 space-y-6">
                        
                        <!-- CARD 5: Order Summary -->
                        <div class="v5-card p-5 sm:p-6 space-y-4">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    Order Summary
                                </h3>
                            </div>

                            <div class="space-y-2 text-xs">
                                <div class="flex justify-between text-slate-600 font-medium">
                                    <span>Subtotal</span>
                                    <span id="summary-subtotal" class="font-bold text-slate-800">30,000 MMK</span>
                                </div>
                                <div class="flex justify-between text-slate-600 font-medium">
                                    <span>Delivery Charge</span>
                                    <span id="summary-delivery-charge" class="font-bold text-slate-800">0 MMK</span>
                                </div>
                                <div class="flex justify-between text-slate-600 font-medium">
                                    <span>Additional Charge</span>
                                    <span id="summary-additional-charge" class="font-bold text-slate-800">0 MMK</span>
                                </div>
                                <div class="flex justify-between text-slate-600 font-medium">
                                    <span>Discount</span>
                                    <span class="font-bold text-slate-800">0 MMK</span>
                                </div>
                                <div class="flex justify-between text-slate-600 font-medium">
                                    <span>Tax (0%)</span>
                                    <span class="font-bold text-slate-800">0 MMK</span>
                                </div>

                                <div class="pt-3 border-t border-slate-100 flex justify-between items-baseline">
                                    <span class="text-sm font-extrabold text-slate-900">Grand Total</span>
                                    <span id="summary-grand-total" class="text-xl font-black text-slate-900">30,000 MMK</span>
                                </div>

                                <div class="pt-2 flex items-center justify-between">
                                    <span class="text-xs font-bold text-slate-500">Payment Status</span>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        Pending
                                    </span>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="pt-2 space-y-2.5">
                                <button type="button" id="confirm-voucher-btn" class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-extrabold text-xs shadow-md shadow-blue-500/25 transition-all flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <span>Create Ledger Entry</span>
                                </button>

                                <div class="grid grid-cols-2 gap-2">
                                    <button type="button" class="py-2.5 px-3 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-xs transition-colors flex items-center justify-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                                        <span>Save Draft</span>
                                    </button>
                                    <button type="button" onclick="document.getElementById('live-voucher-card').scrollIntoView({behavior: 'smooth'})" class="py-2.5 px-3 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-xs transition-colors flex items-center justify-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        <span>Preview Voucher</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- CARD 6: Tracking & Voucher Info -->
                        <div class="v5-card p-5 sm:p-6 space-y-4">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                    Tracking & Voucher Info
                                </h3>
                            </div>

                            <div class="space-y-3 text-xs">
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-500 font-semibold">Voucher ID</span>
                                    <span class="font-mono font-bold text-slate-800" id="card6-voucher-id"><?= $preview_voucher_code ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-500 font-semibold">Tracking Number</span>
                                    <span class="font-mono font-bold text-slate-800 flex items-center gap-1">
                                        <span id="card6-tracking-no"><?= $preview_tracking_no ?></span>
                                        <svg class="w-3.5 h-3.5 text-slate-400 cursor-pointer hover:text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    </span>
                                </div>

                                <!-- Barcode & QR Code Graphic -->
                                <div class="py-3 px-4 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between">
                                    <div class="text-left">
                                        <!-- Simulated Barcode Bars -->
                                        <div class="flex items-center h-10 space-x-0.5">
                                            <span class="v5-barcode-bar w-1"></span>
                                            <span class="v5-barcode-bar w-0.5"></span>
                                            <span class="v5-barcode-bar w-1.5"></span>
                                            <span class="v5-barcode-bar w-0.5"></span>
                                            <span class="v5-barcode-bar w-1"></span>
                                            <span class="v5-barcode-bar w-2"></span>
                                            <span class="v5-barcode-bar w-0.5"></span>
                                            <span class="v5-barcode-bar w-1"></span>
                                            <span class="v5-barcode-bar w-1.5"></span>
                                            <span class="v5-barcode-bar w-0.5"></span>
                                            <span class="v5-barcode-bar w-1"></span>
                                            <span class="v5-barcode-bar w-2"></span>
                                            <span class="v5-barcode-bar w-0.5"></span>
                                            <span class="v5-barcode-bar w-1"></span>
                                        </div>
                                        <span class="font-mono text-[10px] text-slate-500 font-bold block mt-1" id="card6-barcode-text"><?= $preview_tracking_no ?></span>
                                    </div>
                                    <div class="w-12 h-12 bg-white p-1 rounded-lg border border-slate-200 shrink-0">
                                        <!-- Clean SVG QR Code -->
                                        <svg viewBox="0 0 24 24" class="w-full h-full text-slate-800" fill="currentColor">
                                            <path d="M2 2h8v8H2V2zm2 2v4h4V4H4zm8-2h8v8h-8V2zm2 2v4h4V4h-4zM2 14h8v8H2v-8zm2 2v4h4v-4H4zm14-2h4v2h-4v-2zm-6 0h2v4h-2v-4zm2 4h4v4h-2v-2h-2v-2zm4 0h2v4h-2v-4zm-6 2h2v2h-2v-2z"/>
                                        </svg>
                                    </div>
                                </div>

                                <!-- Shipment Status Vertical Stepper -->
                                <div class="pt-2 space-y-3">
                                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Shipment Status</span>
                                    
                                    <div class="space-y-3 pl-2 border-l-2 border-slate-200 text-xs">
                                        <div class="relative pl-4">
                                            <span class="absolute -left-[17px] top-0.5 w-3 h-3 rounded-full bg-emerald-500 ring-4 ring-white"></span>
                                            <p class="font-bold text-slate-800 leading-none">Created</p>
                                            <p class="text-[10px] text-slate-400 mt-0.5" id="status-created-time"><?= date('Y-m-d H:i') ?></p>
                                        </div>
                                        <div class="relative pl-4">
                                            <span class="absolute -left-[17px] top-0.5 w-3 h-3 rounded-full bg-slate-300 ring-4 ring-white"></span>
                                            <p class="font-semibold text-slate-500 leading-none">Picked Up</p>
                                        </div>
                                        <div class="relative pl-4">
                                            <span class="absolute -left-[17px] top-0.5 w-3 h-3 rounded-full bg-slate-300 ring-4 ring-white"></span>
                                            <p class="font-semibold text-slate-500 leading-none">In Transit</p>
                                        </div>
                                        <div class="relative pl-4">
                                            <span class="absolute -left-[17px] top-0.5 w-3 h-3 rounded-full bg-slate-300 ring-4 ring-white"></span>
                                            <p class="font-semibold text-slate-500 leading-none">Pending</p>
                                        </div>
                                        <div class="relative pl-4">
                                            <span class="absolute -left-[17px] top-0.5 w-3 h-3 rounded-full bg-slate-300 ring-4 ring-white"></span>
                                            <p class="font-semibold text-slate-500 leading-none">Delivered</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- LIVE VOUCHER PREVIEW CARD ("LOGISTICS DELIVERY VOUCHER V5") -->
                        <div id="live-voucher-card" class="v5-voucher-sheet p-5 bg-white space-y-4 border border-slate-300 shadow-xl rounded-2xl">
                            
                            <!-- Voucher Sheet Header -->
                            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-blue-600 text-white flex items-center justify-center font-bold text-xs">M</div>
                                    <div>
                                        <div class="text-xs font-black text-slate-900 leading-tight">MBLOGISTICS</div>
                                        <div class="text-[9px] text-slate-400 font-semibold leading-tight">Fast • Safe • Global</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-[11px] font-black text-blue-600 uppercase tracking-tight">LOGISTICS DELIVERY VOUCHER</div>
                                    <span class="inline-block px-1.5 py-0.2 rounded bg-blue-100 text-blue-800 text-[9px] font-extrabold">V5</span>
                                </div>
                            </div>

                            <!-- Meta info: Voucher No, Tracking No, Date & Time -->
                            <div class="grid grid-cols-3 gap-2 text-[10px] bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                <div>
                                    <span class="text-slate-400 font-bold block">Voucher No.</span>
                                    <strong class="text-slate-800 font-mono" id="preview-voucher-no"><?= $preview_voucher_code ?></strong>
                                </div>
                                <div>
                                    <span class="text-slate-400 font-bold block">Tracking No.</span>
                                    <strong class="text-slate-800 font-mono" id="preview-tracking-no"><?= $preview_tracking_no ?></strong>
                                </div>
                                <div>
                                    <span class="text-slate-400 font-bold block">Date & Time</span>
                                    <strong class="text-slate-800" id="preview-date-time"><?= date('Y-m-d H:i') ?></strong>
                                </div>
                            </div>

                            <!-- Sender & Receiver Two-Column Blue Headers -->
                            <div class="grid grid-cols-2 gap-3 text-[11px]">
                                <!-- Sender Panel -->
                                <div class="border border-slate-200 rounded-xl overflow-hidden">
                                    <div class="v5-voucher-header-bar">Sender Details</div>
                                    <div class="p-2.5 space-y-1 bg-white">
                                        <div class="flex justify-between">
                                            <span class="text-slate-400">Full Name:</span>
                                            <strong class="text-slate-800" id="pv-sender-name">Ko Ko Win</strong>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-slate-400">Phone:</span>
                                            <strong class="text-slate-800 font-mono text-[10px]" id="pv-sender-phone">+95 9 123 456789</strong>
                                        </div>
                                    </div>
                                </div>

                                <!-- Receiver Panel -->
                                <div class="border border-slate-200 rounded-xl overflow-hidden">
                                    <div class="v5-voucher-header-bar">Receiver Details</div>
                                    <div class="p-2.5 space-y-1 bg-white">
                                        <div class="flex justify-between">
                                            <span class="text-slate-400">Full Name:</span>
                                            <strong class="text-slate-800" id="pv-receiver-name">Aung San</strong>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-slate-400">Phone:</span>
                                            <strong class="text-slate-800 font-mono text-[10px]" id="pv-receiver-phone">+61 412 345 678</strong>
                                        </div>
                                        <div class="text-[10px] text-slate-600 mt-1 pt-1 border-t border-slate-100 line-clamp-2" id="pv-receiver-address">
                                            12 Elizabeth St, Melbourne VIC 3000 Australia
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Routing & Logistics + QR & Barcode -->
                            <div class="border border-slate-200 rounded-xl p-2.5 text-[10px] bg-slate-50/70">
                                <div class="v5-voucher-header-bar mb-2">Routing & Logistics</div>
                                <div class="grid grid-cols-12 gap-2 items-center">
                                    <div class="col-span-8 space-y-1">
                                        <div class="flex justify-between"><span class="text-slate-400">Origin Point:</span> <strong id="pv-origin">Myanmar &rarr; Yangon</strong></div>
                                        <div class="flex justify-between"><span class="text-slate-400">Destination Region:</span> <strong id="pv-region">Australia</strong></div>
                                        <div class="flex justify-between"><span class="text-slate-400">Destination Branch:</span> <strong id="pv-branch">Melbourne</strong></div>
                                        <div class="flex justify-between"><span class="text-slate-400">Delivery Type:</span> <strong id="pv-delivery-type">ကားဂိတ်တင်</strong></div>
                                        <div class="flex justify-between"><span class="text-slate-400">Currency:</span> <strong id="pv-currency">MMK</strong></div>
                                        <div class="flex justify-between"><span class="text-slate-400">Additional Charge:</span> <strong id="pv-add-charge">0 MMK</strong></div>
                                        <div class="flex justify-between"><span class="text-slate-400">Notes:</span> <span class="italic text-slate-600" id="pv-notes">Handle with care.</span></div>
                                    </div>
                                    <div class="col-span-4 flex flex-col items-center justify-center pl-2 border-l border-slate-200">
                                        <div class="w-12 h-12 bg-white p-0.5 rounded border border-slate-300">
                                            <svg viewBox="0 0 24 24" class="w-full h-full text-slate-800" fill="currentColor"><path d="M2 2h8v8H2V2zm2 2v4h4V4H4zm8-2h8v8h-8V2zm2 2v4h4V4h-4zM2 14h8v8H2v-8zm2 2v4h4v-4H4zm14-2h4v2h-4v-2zm-6 0h2v4h-2v-4zm2 4h4v4h-2v-2h-2v-2zm4 0h2v4h-2v-4zm-6 2h2v2h-2v-2z"/></svg>
                                        </div>
                                        <span class="font-mono text-[8px] text-slate-600 font-bold mt-1" id="pv-barcode-val"><?= $preview_tracking_no ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Item Breakdown in Voucher Preview -->
                            <div class="border border-slate-200 rounded-xl overflow-hidden text-[10px]">
                                <div class="v5-voucher-header-bar">Item Breakdown</div>
                                <table class="w-full text-left">
                                    <thead class="bg-slate-50 text-slate-500 font-bold border-b border-slate-200">
                                        <tr>
                                            <th class="p-1.5 pl-2">#</th>
                                            <th class="p-1.5">Category</th>
                                            <th class="p-1.5 text-center">Weight (kg)</th>
                                            <th class="p-1.5 text-right">Price / Kg</th>
                                            <th class="p-1.5 text-right pr-2">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="pv-items-tbody" class="divide-y divide-slate-100">
                                        <tr>
                                            <td class="p-1.5 pl-2 font-bold">1</td>
                                            <td class="p-1.5 font-semibold">Laptop</td>
                                            <td class="p-1.5 text-center font-mono">2.50</td>
                                            <td class="p-1.5 text-right font-mono">12,000</td>
                                            <td class="p-1.5 text-right pr-2 font-mono font-bold">30,000</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Total Weight & Grand Total -->
                            <div class="flex justify-between items-center bg-slate-50 p-2.5 rounded-xl border border-slate-200 text-xs">
                                <div>
                                    <span class="text-slate-400 font-bold">Total Weight:</span>
                                    <strong class="text-slate-800 ml-1 font-mono" id="pv-total-weight">2.50 kg</strong>
                                </div>
                                <div>
                                    <span class="text-slate-400 font-bold">Grand Total:</span>
                                    <strong class="text-slate-900 text-sm font-black ml-1 font-mono" id="pv-grand-total">30,000 MMK</strong>
                                </div>
                            </div>

                            <!-- Terms & Conditions -->
                            <div class="pt-2 border-t border-slate-100 text-[9px] text-slate-500 space-y-0.5">
                                <span class="font-extrabold text-slate-700 block mb-0.5">Terms & Conditions</span>
                                <p>• Goods are subject to inspection at destination.</p>
                                <p>• MBLOGISTICS is not responsible for prohibited items.</p>
                                <p>• Please keep this voucher for tracking and enquiry.</p>
                                <p>• Delivery time may vary depending on destination.</p>
                            </div>

                            <!-- Footer Tag -->
                            <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[9px] font-bold text-slate-400">
                                <span>MBLOGISTICS POS V5</span>
                                <span>Powered by <strong class="text-blue-600">MBLOGISTICS</strong></span>
                            </div>

                        </div>

                    </div>

                </div>

            </form>

            <!-- 4. BOTTOM FEATURES BANNER ("Trusted Logistics Across the Globe") -->
            <div class="bg-gradient-to-r from-blue-50 via-indigo-50 to-sky-50 rounded-3xl p-6 sm:p-8 border border-blue-100/80 shadow-sm relative overflow-hidden mt-10">
                
                <div class="relative z-10 flex flex-col xl:flex-row items-start xl:items-center justify-between gap-6">
                    <div>
                        <h3 class="text-base sm:text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                            <span class="text-xl">✈️</span>
                            Trusted Logistics Across the Globe
                        </h3>
                        <p class="text-xs sm:text-sm text-slate-600 font-medium mt-0.5">Your shipment. Our priority.</p>
                    </div>

                    <!-- 5 Feature Badges -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 w-full xl:w-auto">
                        
                        <!-- Feature 1 -->
                        <div class="bg-white/90 backdrop-blur-sm p-3 rounded-2xl border border-white/80 shadow-sm">
                            <div class="w-7 h-7 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center mb-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            </div>
                            <h5 class="text-xs font-bold text-slate-800">Fast & Secure</h5>
                            <p class="text-[10px] text-slate-500 font-medium">Real-time tracking & secure handling</p>
                        </div>

                        <!-- Feature 2 -->
                        <div class="bg-white/90 backdrop-blur-sm p-3 rounded-2xl border border-white/80 shadow-sm">
                            <div class="w-7 h-7 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center mb-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <h5 class="text-xs font-bold text-slate-800">Multi-Country</h5>
                            <p class="text-[10px] text-slate-500 font-medium">Ship to AU, CA, MY, NZ, SG, TH, US</p>
                        </div>

                        <!-- Feature 3 -->
                        <div class="bg-white/90 backdrop-blur-sm p-3 rounded-2xl border border-white/80 shadow-sm">
                            <div class="w-7 h-7 rounded-lg bg-cyan-100 text-cyan-600 flex items-center justify-center mb-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            </div>
                            <h5 class="text-xs font-bold text-slate-800">Multiple Payment</h5>
                            <p class="text-[10px] text-slate-500 font-medium">MMK, RM, SGD, USD + payment methods</p>
                        </div>

                        <!-- Feature 4 -->
                        <div class="bg-white/90 backdrop-blur-sm p-3 rounded-2xl border border-white/80 shadow-sm">
                            <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center mb-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                            </div>
                            <h5 class="text-xs font-bold text-slate-800">Real-time Tracking</h5>
                            <p class="text-[10px] text-slate-500 font-medium">Live updates and cargo status</p>
                        </div>

                        <!-- Feature 5 -->
                        <div class="bg-white/90 backdrop-blur-sm p-3 rounded-2xl border border-white/80 shadow-sm col-span-2 sm:col-span-1">
                            <div class="w-7 h-7 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center mb-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            </div>
                            <h5 class="text-xs font-bold text-slate-800">Professional Support</h5>
                            <p class="text-[10px] text-slate-500 font-medium">24/7 customer support via chat & phone</p>
                        </div>

                    </div>
                </div>

            </div>

        </main>
    </div>
</div>

<!-- Glassmorphism Confirmation Modal -->
<div id="confirmationModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center hidden z-50 transition-opacity p-4">
    <div class="bg-white p-6 sm:p-8 rounded-[2rem] shadow-2xl w-full max-w-lg border border-slate-100 transform scale-100 transition-transform">
        
        <div class="w-14 h-14 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-sm">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        
        <h2 class="text-xl font-extrabold text-slate-900 text-center mb-2">Confirm Voucher Creation</h2>
        <p class="text-xs text-slate-500 text-center mb-6">Review your shipment entries before committing to the live operational ledger.</p>
        
        <div id="confirmation-details" class="text-left space-y-4 bg-slate-50 p-4 rounded-2xl border border-slate-100 text-xs">
            <!-- Injected via JavaScript -->
        </div>
        
        <div class="mt-6 flex justify-center gap-3">
            <button type="button" id="cancel-btn" class="px-5 py-2.5 rounded-xl font-bold text-xs text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors w-full">Go Back</button>
            <button type="button" id="submit-btn" class="px-5 py-2.5 rounded-xl font-bold text-xs text-white bg-blue-600 hover:bg-blue-700 shadow-md shadow-blue-500/25 transition-colors w-full">Confirm & Issue</button>
        </div>
    </div>
</div>

<!-- jQuery and Select2 JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    window.branchesData = <?= json_encode($all_branches) ?>;
    window.itemTypes = <?= json_encode($item_types_list) ?>;
    window.allRegions = <?= json_encode($all_regions) ?>;

    $(document).ready(function() {
        // --- Initialize Customer Select2 Search ---
        $('.customer-search').select2({
            ajax: {
                url: 'index.php?page=ajax_search_customers',
                dataType: 'json',
                delay: 250,
                data: params => ({ q: params.term }),
                processResults: data => ({ results: data.results })
            },
            placeholder: 'Search by name or phone...',
            minimumInputLength: 2,
            width: '100%'
        });

        // --- Segmented Switcher for Sender & Receiver ---
        $('.v5-segmented-btn').click(function() {
            const toggleType = $(this).data('toggle');
            const val = $(this).data('val');
            
            $(`[data-toggle="${toggleType}"]`).removeClass('active');
            $(this).addClass('active');
            $(`#${toggleType}_type`).val(val);

            const isExisting = (val === 'existing');
            $(`#existing_${toggleType}_fields`).toggleClass('hidden', !isExisting);
            $(`#new_${toggleType}_fields`).toggleClass('hidden', isExisting);
            $(`#new_${toggleType}_fields input`).prop('required', !isExisting);
            $(`#${toggleType}_customer_id`).prop('required', isExisting);

            if (!isExisting) {
                $(`#${toggleType}_customer_id`).val(null).trigger('change');
            }
            updateLivePreview();
        });

        // Customer auto-fill
        $('#sender_customer_id').on('select2:select', function(e) {
            const data = e.params.data;
            $('#sender_name').val(data.text.split(' (')[0]);
            $('#sender_phone').val(data.phone);
            updateLivePreview();
        });
        $('#receiver_customer_id').on('select2:select', function(e) {
            const data = e.params.data;
            $('#receiver_name').val(data.text.split(' (')[0]);
            $('#receiver_phone').val(data.phone);
            updateLivePreview();
        });
    });

    // --- Dynamic V5 DOM Handling & Calculations ---
    document.addEventListener('DOMContentLoaded', function() {
        const destRegionSelect = document.getElementById('destination_region_id');
        const destBranchSelect = document.getElementById('destination_branch_id');
        const itemsTbody = document.getElementById('items-tbody');
        const addItemBtn = document.getElementById('add-item-btn');
        let rowCount = 0;

        // Populate Destination Branches based on Region
        function populateBranches() {
            const regionId = destRegionSelect.value;
            destBranchSelect.innerHTML = '<option value="">Select region first</option>';
            if (regionId) {
                const filtered = window.branchesData.filter(b => b.region_id == regionId);
                if (filtered.length > 0) {
                    destBranchSelect.innerHTML = '';
                    filtered.forEach(b => {
                        destBranchSelect.add(new Option(b.branch_name, b.id));
                    });
                } else {
                    destBranchSelect.innerHTML = '<option value="1">Main Branch</option>';
                }

                // Update Voucher Code based on selected region prefix and sequence
                const selectedOpt = destRegionSelect.options[destRegionSelect.selectedIndex];
                if (selectedOpt) {
                    const prefix = selectedOpt.getAttribute('data-prefix') || 'MBV';
                    const seq = parseInt(selectedOpt.getAttribute('data-seq') || '0', 10) + 1;
                    const formattedCode = `${prefix}-${new Date().getFullYear()}-${String(seq).padStart(6, '0')}`;
                    document.getElementById('top-voucher-number').textContent = formattedCode;
                    document.getElementById('card6-voucher-id').textContent = formattedCode;
                    document.getElementById('preview-voucher-no').textContent = formattedCode;
                }
            }
            updateLivePreview();
        }
        destRegionSelect.addEventListener('change', populateBranches);
        destBranchSelect.addEventListener('change', updateLivePreview);

        // Add Item Row Function
        window.addItemRow = function(category = '', weight = 0, price = 0) {
            rowCount++;
            const tr = document.createElement('tr');
            tr.className = 'item-row hover:bg-slate-50/70 transition-colors';
            
            let catOptions = window.itemTypes.map(t => `<option value="${t}" ${t === category ? 'selected' : ''}>${t}</option>`).join('');
            
            tr.innerHTML = `
                <td class="py-2.5 px-2 font-bold text-slate-400 text-xs row-index">${rowCount}</td>
                <td class="py-2.5 px-3">
                    <select name="item_type[]" class="item-cat-select w-full rounded-lg border border-slate-200 bg-white py-1.5 px-2.5 text-xs font-semibold text-slate-800 focus:ring-1 focus:ring-blue-500">
                        <option value="">Select Category...</option>
                        ${catOptions}
                    </select>
                </td>
                <td class="py-2.5 px-3">
                    <div class="relative">
                        <input type="number" step="0.01" name="item_kg[]" value="${weight > 0 ? weight : '0.00'}" class="item-kg-input w-full rounded-lg border border-slate-200 bg-white py-1.5 pl-2.5 pr-7 text-xs font-bold text-slate-800 text-right focus:ring-1 focus:ring-blue-500">
                        <span class="absolute inset-y-0 right-0 pr-2 flex items-center pointer-events-none text-[10px] text-slate-400 font-bold">kg</span>
                    </div>
                </td>
                <td class="py-2.5 px-3">
                    <input type="number" step="0.01" name="item_price_per_kg[]" value="${price > 0 ? price : '0.00'}" class="item-price-input w-full rounded-lg border border-slate-200 bg-white py-1.5 px-2.5 text-xs font-bold text-slate-800 text-right focus:ring-1 focus:ring-blue-500">
                </td>
                <td class="py-2.5 px-3 font-mono font-bold text-slate-800 text-right row-total">0.00</td>
                <td class="py-2.5 px-2 text-center">
                    <button type="button" class="remove-row-btn p-1 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Delete Row">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </td>
            `;
            itemsTbody.appendChild(tr);
            if (typeof window.mbposApplyLanguage === 'function') {
                window.mbposApplyLanguage(document.documentElement.dataset.language || 'en');
            }
            reindexRows();
            calculateAll();
        };

        addItemBtn.addEventListener('click', () => addItemRow('Laptop', 1, 12000));

        // Remove Row
        itemsTbody.addEventListener('click', function(e) {
            const btn = e.target.closest('.remove-row-btn');
            if (btn) {
                const tr = btn.closest('tr');
                if (document.querySelectorAll('.item-row').length > 1) {
                    tr.remove();
                    reindexRows();
                    calculateAll();
                } else {
                    alert("At least one item row is required.");
                }
            }
        });

        function reindexRows() {
            document.querySelectorAll('.item-row').forEach((row, i) => {
                row.querySelector('.row-index').textContent = i + 1;
            });
        }

        // Quick Category Chip Click
        document.querySelectorAll('.v5-category-chip').forEach(chip => {
            chip.addEventListener('click', function() {
                document.querySelectorAll('.v5-category-chip').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                const cat = this.getAttribute('data-cat');
                
                // Set category of last item row or add new
                const lastRow = itemsTbody.lastElementChild;
                if (lastRow) {
                    const select = lastRow.querySelector('.item-cat-select');
                    // check if option exists
                    let exists = false;
                    for (let opt of select.options) {
                        if (opt.value === cat) {
                            exists = true;
                            break;
                        }
                    }
                    if (!exists) {
                        select.add(new Option(cat, cat));
                    }
                    select.value = cat;
                } else {
                    addItemRow(cat, 1, 10000);
                }
                calculateAll();
            });
        });

        // Currency Pill Switcher
        document.querySelectorAll('.currency-pill-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const curr = this.getAttribute('data-curr');
                document.querySelectorAll('.currency-pill-btn').forEach(b => {
                    b.className = 'currency-pill-btn flex-1 py-2 rounded-xl text-xs font-extrabold border bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100 transition-all';
                });
                this.className = 'currency-pill-btn flex-1 py-2 rounded-xl text-xs font-extrabold border bg-blue-600 text-white border-blue-600 shadow-sm transition-all';
                document.getElementById('currency').value = curr;
                document.querySelectorAll('.selected-currency-label').forEach(el => el.textContent = curr);
                calculateAll();
            });
        });

        // Delivery Type Radio Change
        document.querySelectorAll('.delivery-type-radio').forEach(radio => {
            radio.addEventListener('change', updateLivePreview);
        });

        // Calculations Engine
        function calculateAll() {
            let subtotal = 0;
            let totalWeight = 0;
            const currency = document.getElementById('currency').value || 'MMK';
            
            document.querySelectorAll('.item-row').forEach(row => {
                const kg = parseFloat(row.querySelector('.item-kg-input').value) || 0;
                const price = parseFloat(row.querySelector('.item-price-input').value) || 0;
                const rowTotal = kg * price;
                row.querySelector('.row-total').textContent = rowTotal.toLocaleString();
                subtotal += rowTotal;
                totalWeight += kg;
            });

            const deliveryCharge = parseFloat(document.getElementById('delivery_charge').value) || 0;
            const grandTotal = subtotal + deliveryCharge;

            // Form Summaries
            document.getElementById('card4-total-weight').textContent = `${totalWeight.toFixed(2)} kg`;
            document.getElementById('card4-grand-total').textContent = `${grandTotal.toLocaleString()} ${currency}`;
            
            document.getElementById('summary-subtotal').textContent = `${subtotal.toLocaleString()} ${currency}`;
            document.getElementById('summary-delivery-charge').textContent = `${deliveryCharge.toLocaleString()} ${currency}`;
            document.getElementById('summary-additional-charge').textContent = `0 ${currency}`;
            document.getElementById('summary-grand-total').textContent = `${grandTotal.toLocaleString()} ${currency}`;

            // Live Voucher Sheet Update
            updateLivePreview(subtotal, deliveryCharge, grandTotal, totalWeight, currency);
        }

        // Real-Time Reactive Live Voucher Preview Sheet
        function updateLivePreview(subtotal, deliveryCharge, grandTotal, totalWeight, currency) {
            if (subtotal === undefined) {
                let s = 0, w = 0;
                document.querySelectorAll('.item-row').forEach(r => {
                    const k = parseFloat(r.querySelector('.item-kg-input').value) || 0;
                    const p = parseFloat(r.querySelector('.item-price-input').value) || 0;
                    s += (k * p);
                    w += k;
                });
                const d = parseFloat(document.getElementById('delivery_charge').value) || 0;
                currency = document.getElementById('currency').value || 'MMK';
                subtotal = s;
                deliveryCharge = d;
                grandTotal = s + d;
                totalWeight = w;
            }

            // Sender & Receiver
            const sName = document.getElementById('sender_name').value.trim() || 'Ko Ko Win';
            const sPhone = document.getElementById('sender_phone').value.trim() || '+95 9 123 456789';
            const rName = document.getElementById('receiver_name').value.trim() || 'Aung San';
            const rPhone = document.getElementById('receiver_phone').value.trim() || '+61 412 345 678';
            const rAddress = document.getElementById('receiver_address').value.trim() || '12 Elizabeth St, Melbourne VIC 3000 Australia';
            
            document.getElementById('pv-sender-name').textContent = sName;
            document.getElementById('pv-sender-phone').textContent = sPhone;
            document.getElementById('pv-receiver-name').textContent = rName;
            document.getElementById('pv-receiver-phone').textContent = rPhone;
            document.getElementById('pv-receiver-address').textContent = rAddress;

            // Region & Branch
            const regOpt = destRegionSelect.options[destRegionSelect.selectedIndex];
            const regName = regOpt ? regOpt.getAttribute('data-name') : 'Australia';
            const branchOpt = destBranchSelect.options[destBranchSelect.selectedIndex];
            const branchName = branchOpt && branchOpt.value ? branchOpt.text : 'Melbourne';

            document.getElementById('pv-region').textContent = regName || 'Australia';
            document.getElementById('pv-branch').textContent = branchName || 'Melbourne';

            // Delivery Type
            const activeRadio = document.querySelector('.delivery-type-radio:checked');
            document.getElementById('pv-delivery-type').textContent = activeRadio ? activeRadio.value : 'ကားဂိတ်တင်';
            document.getElementById('pv-currency').textContent = currency;
            document.getElementById('pv-add-charge').textContent = `${deliveryCharge.toLocaleString()} ${currency}`;
            
            const notes = document.getElementById('notes').value.trim() || 'Handle with care.';
            document.getElementById('pv-notes').textContent = notes;

            // Items table in preview sheet
            const pvItemsTbody = document.getElementById('pv-items-tbody');
            let rowsHtml = '';
            document.querySelectorAll('.item-row').forEach((row, idx) => {
                const cat = row.querySelector('.item-cat-select').value || 'General';
                const kg = parseFloat(row.querySelector('.item-kg-input').value) || 0;
                const p = parseFloat(row.querySelector('.item-price-input').value) || 0;
                const tot = kg * p;
                rowsHtml += `
                    <tr>
                        <td class="p-1.5 pl-2 font-bold">${idx + 1}</td>
                        <td class="p-1.5 font-semibold">${cat}</td>
                        <td class="p-1.5 text-center font-mono">${kg.toFixed(2)}</td>
                        <td class="p-1.5 text-right font-mono">${p.toLocaleString()}</td>
                        <td class="p-1.5 text-right pr-2 font-mono font-bold">${tot.toLocaleString()}</td>
                    </tr>
                `;
            });
            pvItemsTbody.innerHTML = rowsHtml || '<tr><td colspan="5" class="p-2 text-center text-slate-400">No items added</td></tr>';

            document.getElementById('pv-total-weight').textContent = `${totalWeight.toFixed(2)} kg`;
            document.getElementById('pv-grand-total').textContent = `${grandTotal.toLocaleString()} ${currency}`;
        }

        // Input listener on the entire form for reactive calculations
        document.getElementById('voucher-form').addEventListener('input', function(e) {
            // Character counters
            if (e.target.id === 'receiver_address') {
                document.getElementById('address-counter').textContent = `${e.target.value.length}/500`;
            }
            if (e.target.id === 'notes') {
                document.getElementById('notes-counter').textContent = `${e.target.value.length}/500`;
            }
            calculateAll();
        });

        // Initialize Row 1 exactly like the screenshot: 1 Laptop, 2.50 kg, 12,000 price = 30,000 MMK
        addItemRow('Laptop', 2.50, 12000);

        // Pre-select first region if available
        if (destRegionSelect.options.length > 1) {
            destRegionSelect.selectedIndex = 1;
            populateBranches();
        }

        // Live MMT Clock Update
        function updateMMTClock() {
            const now = new Date();
            const utc = now.getTime() + (now.getTimezoneOffset() * 60000);
            const mmt = new Date(utc + (3600000 * 6.5));
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const m = months[mmt.getMonth()];
            const d = mmt.getDate();
            const y = mmt.getFullYear();
            const hh = String(mmt.getHours()).padStart(2, '0');
            const mm = String(mmt.getMinutes()).padStart(2, '0');
            const clockEl = document.getElementById('v5-live-clock');
            if (clockEl) clockEl.textContent = `${m} ${d}, ${y} ${hh}:${mm} (MMT)`;
        }
        setInterval(updateMMTClock, 1000);
        updateMMTClock();

        // Confirmation Modal Logic
        const confirmBtn = document.getElementById('confirm-voucher-btn');
        const modal = document.getElementById('confirmationModal');
        const detailsContainer = document.getElementById('confirmation-details');
        const cancelBtn = document.getElementById('cancel-btn');
        const submitBtn = document.getElementById('submit-btn');
        const form = document.getElementById('voucher-form');

        confirmBtn.addEventListener('click', function() {
            const sName = document.getElementById('sender_name').value || "Existing";
            const sPhone = document.getElementById('sender_phone').value || "---";
            const rName = document.getElementById('receiver_name').value || "Existing";
            const rPhone = document.getElementById('receiver_phone').value || "---";
            const grandTotal = document.getElementById('summary-grand-total').textContent;
            const totalWeight = document.getElementById('card4-total-weight').textContent;
            
            let itemsHtml = `
                <table class="w-full text-xs mt-2 border-collapse">
                    <thead class="border-b border-slate-200 text-slate-400 font-bold uppercase text-[10px]">
                        <tr>
                            <th class="text-left py-1">Item</th>
                            <th class="text-center py-1">Weight</th>
                            <th class="text-right py-1">Price/Kg</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">`;
                    
            document.querySelectorAll('.item-row').forEach(row => {
                const type = row.querySelector('.item-cat-select').value;
                const kg = row.querySelector('.item-kg-input').value;
                const price = row.querySelector('.item-price-input').value;
                if(type && kg > 0) {
                    itemsHtml += `
                        <tr>
                            <td class="py-1 font-semibold text-slate-800">${type}</td>
                            <td class="py-1 text-center font-mono">${kg} kg</td>
                            <td class="py-1 text-right font-mono">${price}</td>
                        </tr>`;
                }
            });
            itemsHtml += '</tbody></table>';

            detailsContainer.innerHTML = `
                <div class="grid grid-cols-2 gap-3 text-xs mb-3">
                    <div class="p-2.5 bg-white rounded-xl border border-slate-200">
                        <span class="block text-[10px] font-bold text-slate-400 uppercase">Sender</span>
                        <strong class="text-slate-800">${sName}</strong><br>
                        <span class="text-slate-500 font-mono text-[11px]">${sPhone}</span>
                    </div>
                    <div class="p-2.5 bg-white rounded-xl border border-slate-200">
                        <span class="block text-[10px] font-bold text-slate-400 uppercase">Receiver</span>
                        <strong class="text-slate-800">${rName}</strong><br>
                        <span class="text-slate-500 font-mono text-[11px]">${rPhone}</span>
                    </div>
                </div>
                <div class="p-2.5 bg-white rounded-xl border border-slate-200">
                    <span class="block text-[10px] font-bold text-slate-400 uppercase">Items Included</span>
                    ${itemsHtml}
                </div>
                <div class="flex justify-between items-center px-1 pt-2">
                    <span class="text-xs font-bold text-slate-500">Total Weight: <strong class="text-slate-900">${totalWeight}</strong></span>
                    <span class="text-base font-black text-blue-600">${grandTotal}</span>
                </div>
            `;
            modal.classList.remove('hidden');
        });

        cancelBtn.addEventListener('click', () => modal.classList.add('hidden'));
        submitBtn.addEventListener('click', () => form.submit());
    });
</script>

<?php
include_template('footer', ['page' => 'voucher_create']);
?>
