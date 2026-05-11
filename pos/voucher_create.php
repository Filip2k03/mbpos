<?php
// pos/voucher_create.php - A polished and debugged form for creating new vouchers.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
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

$region_result = mysqli_query($connection, "SELECT id, region_name FROM regions ORDER BY region_name");
if ($region_result) while ($row = mysqli_fetch_assoc($region_result)) $all_regions[] = $row;
$branch_result = mysqli_query($connection, "SELECT id, branch_name, region_id FROM branches ORDER BY branch_name");
if ($branch_result) while ($row = mysqli_fetch_assoc($branch_result)) $all_branches[] = $row;
$currency_result = mysqli_query($connection, "SELECT code FROM currencies ORDER BY code");
if ($currency_result) while ($row = mysqli_fetch_assoc($currency_result)) $currencies[] = $row['code'];
$item_type_result = mysqli_query($connection, "SELECT name FROM item_types ORDER BY name");
if ($item_type_result) while ($row = mysqli_fetch_assoc($item_type_result)) $item_types_list[] = $row['name'];
$delivery_type_result = mysqli_query($connection, "SELECT name FROM delivery_types ORDER BY name");
if ($delivery_type_result) while ($row = mysqli_fetch_assoc($delivery_type_result)) $delivery_types[] = $row['name'];


// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and Sanitize Data
    $sender_type = $_POST['sender_type'] ?? 'new';
    $sender_customer_id = ($sender_type === 'existing') ? intval($_POST['sender_customer_id'] ?? 0) : null;
    $sender_name = trim($_POST['sender_name']);
    $sender_phone = trim($_POST['sender_phone']);
    
    $receiver_type = $_POST['receiver_type'] ?? 'new';
    $receiver_customer_id = ($receiver_type === 'existing') ? intval($_POST['receiver_customer_id'] ?? 0) : null;
    $receiver_name = trim($_POST['receiver_name']);
    $receiver_phone = trim($_POST['receiver_phone']);
    $receiver_address = trim($_POST['receiver_address']);
    
    $origin_region_id = $user_info['region_id'];
    $origin_branch_id = $user_info['branch_id'];
    $destination_region_id = intval($_POST['destination_region_id']);
    $destination_branch_id = intval($_POST['destination_branch_id']);
    $delivery_charge = floatval($_POST['delivery_charge']);
    $currency = trim($_POST['currency']);
    $notes = trim($_POST['notes']);
    $delivery_type = trim($_POST['delivery_type']);

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
    if (empty($sender_name) || empty($receiver_name) || empty($validated_breakdowns) || empty($origin_branch_id) || empty($destination_branch_id)) {
        flash_message('error', 'Please fill all required fields and add at least one valid item.');
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
        $new_sequence = $region_data['current_sequence'] + 1;
        $voucher_code = generate_voucher_code($region_data['prefix'], $new_sequence);
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
        
        // 4. Update the sequence number for the DESTINATION region
        $stmt_update_seq = mysqli_prepare($connection, "UPDATE regions SET current_sequence = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_update_seq, 'ii', $new_sequence, $destination_region_id);
        if(!mysqli_stmt_execute($stmt_update_seq)) throw new Exception("Database Error [Sequence]: " . mysqli_stmt_error($stmt_update_seq));
        mysqli_stmt_close($stmt_update_seq);

        // 5. Create user-specific notifications for all other users
        $notification_message = "New voucher #{$voucher_code} created by " . htmlspecialchars($_SESSION['username']) . ".";
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
        flash_message('error', "An unexpected error occurred. Please check the details and try again.");
        redirect('index.php?page=voucher_create');
    }
}

include_template('header', ['page' => 'voucher_create']);
?>
<!-- Include Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* V3 Glassmorphism Select2 Overrides */
    .select2-container .select2-selection--single {
        height: 48px !important;
        border: 1px solid #e5e7eb !important;
        border-radius: 1rem !important;
        background-color: rgba(249, 250, 251, 0.5) !important;
        display: flex;
        align-items: center;
        transition: all 0.3s ease;
    }
    .select2-container--default .select2-selection--single:focus,
    .select2-container--default.select2-container--focus .select2-selection--single {
        border-color: #6366f1 !important;
        box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2) !important;
        background-color: #ffffff !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #374151 !important;
        font-weight: 500;
        padding-left: 1rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 46px !important;
        right: 8px !important;
    }
    .select2-dropdown {
        border: 1px solid #e5e7eb !important;
        border-radius: 1rem !important;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
        overflow: hidden;
        z-index: 1050;
    }
    .select2-search__field {
        border-radius: 0.5rem !important;
        padding: 8px 12px !important;
    }
    
    /* Smooth toggle transitions */
    .toggle-radio:checked + div {
        background-color: #4f46e5;
        border-color: #4f46e5;
    }
    .toggle-radio:checked + div .toggle-dot {
        transform: translateX(100%);
        background-color: white;
    }
</style>

<!-- V3 Liquid UI Wrapper -->
<div class="relative min-h-[85vh] bg-gray-50/30 p-4 sm:p-8 overflow-hidden font-sans">
    
    <!-- Ambient Background Glows -->
    <div class="absolute top-[10%] left-[-10%] w-[500px] h-[500px] bg-blue-400/10 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-[500px] h-[500px] bg-indigo-400/10 rounded-full blur-[120px] pointer-events-none"></div>

    <div class="max-w-6xl mx-auto relative z-10">
        
        <div class="flex items-center gap-4 mb-8">
            <div class="w-14 h-14 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-500/30 text-white transform -rotate-3 hover:rotate-0 transition-transform duration-300">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </div>
            <div>
                <h1 class="text-3xl font-extrabold bg-gradient-to-r from-gray-900 to-gray-600 bg-clip-text text-transparent tracking-tight">Issue New Voucher</h1>
                <p class="text-sm font-medium text-gray-500">Record a new shipment into the ledger</p>
            </div>
        </div>

        <form action="index.php?page=voucher_create" method="POST" id="voucher-form" accept-charset="UTF-8">
            
            <div class="bg-white/70 backdrop-blur-2xl p-6 sm:p-10 rounded-[2.5rem] shadow-[0_8px_40px_rgb(0,0,0,0.04)] border border-white/80 space-y-10">
                
                <!-- SENDER & RECEIVER SECTION -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    
                    <!-- Sender Details -->
                    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm relative overflow-hidden group">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-blue-50 rounded-bl-full -mr-8 -mt-8 transition-transform group-hover:scale-110 pointer-events-none"></div>
                        <div class="relative z-10">
                            <h3 class="text-lg font-bold text-gray-800 mb-6 flex items-center gap-2">
                                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                Sender Details
                            </h3>
                            
                            <!-- Custom Toggle -->
                            <div class="flex items-center gap-6 mb-6 p-1.5 bg-gray-100 rounded-xl inline-flex">
                                <label class="cursor-pointer relative flex-1 text-center">
                                    <input type="radio" name="sender_type" value="existing" checked class="peer sr-only">
                                    <div class="px-4 py-2 rounded-lg text-sm font-bold text-gray-500 peer-checked:bg-white peer-checked:text-blue-600 peer-checked:shadow-sm transition-all">Existing</div>
                                </label>
                                <label class="cursor-pointer relative flex-1 text-center">
                                    <input type="radio" name="sender_type" value="new" class="peer sr-only">
                                    <div class="px-4 py-2 rounded-lg text-sm font-bold text-gray-500 peer-checked:bg-white peer-checked:text-blue-600 peer-checked:shadow-sm transition-all">New</div>
                                </label>
                            </div>

                            <div id="existing_sender_fields" class="space-y-4">
                                <label for="sender_customer_id" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">Search Database</label>
                                <select id="sender_customer_id" name="sender_customer_id" class="customer-search w-full"></select>
                            </div>
                            
                            <div id="new_sender_fields" class="space-y-4 hidden">
                                <div>
                                    <label for="sender_name" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1 mb-1">Full Name</label>
                                    <input type="text" id="sender_name" name="sender_name" class="w-full rounded-2xl border-gray-200 bg-gray-50/50 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all py-3 font-medium text-gray-800" placeholder="e.g. Aung Aung">
                                </div>
                                <div>
                                    <label for="sender_phone" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1 mb-1">Phone Number</label>
                                    <input type="text" id="sender_phone" name="sender_phone" class="w-full rounded-2xl border-gray-200 bg-gray-50/50 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all py-3 font-medium text-gray-800" placeholder="09xxxxxxxxx">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Receiver Details -->
                    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm relative overflow-hidden group">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-50 rounded-bl-full -mr-8 -mt-8 transition-transform group-hover:scale-110 pointer-events-none"></div>
                        <div class="relative z-10">
                            <h3 class="text-lg font-bold text-gray-800 mb-6 flex items-center gap-2">
                                <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                Receiver Details
                            </h3>
                            
                            <!-- Custom Toggle -->
                            <div class="flex items-center gap-6 mb-6 p-1.5 bg-gray-100 rounded-xl inline-flex">
                                <label class="cursor-pointer relative flex-1 text-center">
                                    <input type="radio" name="receiver_type" value="existing" checked class="peer sr-only">
                                    <div class="px-4 py-2 rounded-lg text-sm font-bold text-gray-500 peer-checked:bg-white peer-checked:text-emerald-600 peer-checked:shadow-sm transition-all">Existing</div>
                                </label>
                                <label class="cursor-pointer relative flex-1 text-center">
                                    <input type="radio" name="receiver_type" value="new" class="peer sr-only">
                                    <div class="px-4 py-2 rounded-lg text-sm font-bold text-gray-500 peer-checked:bg-white peer-checked:text-emerald-600 peer-checked:shadow-sm transition-all">New</div>
                                </label>
                            </div>

                            <div id="existing_receiver_fields" class="space-y-4">
                                <label for="receiver_customer_id" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">Search Database</label>
                                <select id="receiver_customer_id" name="receiver_customer_id" class="customer-search w-full"></select>
                            </div>

                            <div id="new_receiver_fields" class="space-y-4 hidden">
                                <div>
                                    <label for="receiver_name" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1 mb-1">Full Name</label>
                                    <input type="text" id="receiver_name" name="receiver_name" class="w-full rounded-2xl border-gray-200 bg-gray-50/50 focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all py-3 font-medium text-gray-800" placeholder="e.g. Maung Maung">
                                </div>
                                <div>
                                    <label for="receiver_phone" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1 mb-1">Phone Number</label>
                                    <input type="text" id="receiver_phone" name="receiver_phone" class="w-full rounded-2xl border-gray-200 bg-gray-50/50 focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all py-3 font-medium text-gray-800" placeholder="09xxxxxxxxx">
                                </div>
                                <div>
                                    <label for="receiver_address" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1 mb-1">Delivery Address</label>
                                    <textarea id="receiver_address" name="receiver_address" rows="2" class="w-full rounded-2xl border-gray-200 bg-gray-50/50 focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all py-3 font-medium text-gray-800" placeholder="Full address details..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ROUTING & LOGISTICS SECTION -->
                <div class="bg-gray-50/50 rounded-3xl p-6 border border-gray-100">
                    <h3 class="text-lg font-bold text-gray-800 mb-6 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Routing & Logistics
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                        <!-- Origin Read-Only -->
                        <div class="md:col-span-4">
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1 mb-1">Origin Point</label>
                            <div class="px-4 py-3 rounded-2xl border border-gray-200 bg-white text-gray-800 font-bold shadow-sm flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                <?= htmlspecialchars($user_info['region_name'] ?? 'N/A') ?> &rarr; <?= htmlspecialchars($user_info['branch_name'] ?? 'N/A') ?>
                            </div>
                            <input type="hidden" name="origin_region_id" value="<?= $user_info['region_id'] ?>">
                            <input type="hidden" name="origin_branch_id" value="<?= $user_info['branch_id'] ?>">
                        </div>

                        <!-- Destination Region -->
                        <div class="md:col-span-4 space-y-1">
                            <label for="destination_region_id" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1 mb-1">Dest. Region</label>
                            <select id="destination_region_id" name="destination_region_id" class="w-full rounded-2xl border-gray-200 bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all py-3 font-medium text-gray-800 shadow-sm appearance-none" required>
                                <option value="">Select Destination</option>
                                <?php foreach ($all_regions as $region): ?>
                                    <?php if ($region['id'] != $user_info['region_id']): ?>
                                        <option value="<?= $region['id'] ?>"><?= htmlspecialchars($region['region_name']) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Destination Branch -->
                        <div class="md:col-span-4 space-y-1">
                            <label for="destination_branch_id" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1 mb-1">Dest. Branch</label>
                            <select id="destination_branch_id" name="destination_branch_id" class="w-full rounded-2xl border-gray-200 bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all py-3 font-medium text-gray-800 shadow-sm appearance-none" required>
                                <option value="">Select Region First</option>
                            </select>
                        </div>
                        
                        <div class="md:col-span-3 space-y-1">
                            <label for="delivery_type" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1 mb-1">Delivery Type</label>
                            <select id="delivery_type" name="delivery_type" class="w-full rounded-2xl border-gray-200 bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all py-3 font-medium text-gray-800 shadow-sm appearance-none">
                                <?php foreach ($delivery_types as $dt): ?>
                                    <option value="<?= $dt ?>"><?= $dt ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="md:col-span-3 space-y-1">
                            <label for="currency" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1 mb-1">Currency</label>
                            <select id="currency" name="currency" class="w-full rounded-2xl border-gray-200 bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all py-3 font-medium text-gray-800 shadow-sm appearance-none">
                                <?php foreach ($currencies as $c): ?>
                                    <option value="<?= $c ?>"><?= $c ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="md:col-span-6 space-y-1">
                            <label for="delivery_charge" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1 mb-1">Additional Delivery Charge</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <span class="text-gray-400 font-bold">$</span>
                                </div>
                                <input type="number" step="0.01" id="delivery_charge" name="delivery_charge" class="w-full rounded-2xl border-gray-200 bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all py-3 pl-8 font-medium text-gray-800 shadow-sm" value="0">
                            </div>
                        </div>

                        <div class="md:col-span-12 space-y-1">
                            <label for="notes" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1 mb-1">Operational Notes</label>
                            <textarea id="notes" name="notes" rows="2" class="w-full rounded-2xl border-gray-200 bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all py-3 font-medium text-gray-800 shadow-sm" placeholder="Any special instructions or Myanmar language notes..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- ITEM BREAKDOWN SECTION -->
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                            <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            Item Breakdown
                        </h3>
                        <button type="button" id="add-item-btn" class="text-sm font-bold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-4 py-2 rounded-xl transition-colors flex items-center gap-1 border border-indigo-100">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Add Row
                        </button>
                    </div>
                    
                    <div id="item-breakdown-container" class="space-y-3">
                        <!-- Rows injected via JS -->
                    </div>
                </div>
                
                <!-- TOTALS & SUBMIT -->
                <div class="pt-8 border-t border-gray-200 flex flex-col md:flex-row justify-between items-center gap-6">
                    <div class="flex items-center gap-8 bg-blue-50/50 p-4 rounded-2xl border border-blue-100/50 w-full md:w-auto">
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-0.5">Total Weight</p>
                            <p id="total-weight-display" class="text-2xl font-extrabold text-gray-700">0.00 <span class="text-sm">kg</span></p>
                        </div>
                        <div class="w-px h-10 bg-gray-200"></div>
                        <div>
                            <p class="text-xs font-bold text-indigo-400 uppercase tracking-wider mb-0.5">Grand Total</p>
                            <p id="total-amount-display" class="text-3xl font-extrabold text-indigo-600">0.00</p>
                        </div>
                    </div>
                    
                    <button type="button" id="confirm-voucher-btn" class="w-full md:w-auto bg-gradient-to-r from-blue-600 to-indigo-600 text-white py-4 px-10 rounded-2xl font-bold text-lg hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-500/30 shadow-[0_8px_20px_rgb(79,70,229,0.3)] transition-all transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2">
                        Create Ledger Entry
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>

            </div>
        </form>
    </div>
</div>

<!-- Glassmorphism Confirmation Modal -->
<div id="confirmationModal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm flex items-center justify-center hidden z-50 transition-opacity">
    <div class="bg-white p-8 rounded-[2rem] shadow-2xl w-full max-w-lg border border-white/20 transform scale-100 transition-transform">
        
        <div class="w-16 h-16 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-inner">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        
        <h2 class="text-2xl font-extrabold text-gray-900 text-center mb-6">Confirm Voucher Details</h2>
        
        <div id="confirmation-details" class="text-left space-y-4 bg-gray-50 p-5 rounded-2xl border border-gray-100">
            <!-- Details will be injected here by JavaScript -->
        </div>
        
        <div class="mt-8 flex justify-center gap-4">
            <button type="button" id="cancel-btn" class="px-6 py-3 rounded-xl font-bold text-gray-500 bg-white border border-gray-200 hover:bg-gray-50 hover:text-gray-700 transition-colors w-full">Go Back</button>
            <button type="button" id="submit-btn" class="px-6 py-3 rounded-xl font-bold text-white bg-blue-600 hover:bg-blue-700 shadow-lg shadow-blue-500/30 transition-colors w-full">Confirm & Issue</button>
        </div>
    </div>
</div>

<!-- Include jQuery and Select2 JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    // Pass PHP data to JavaScript
    window.branchesData = <?= json_encode($all_branches) ?>;
    window.itemTypes = <?= json_encode($item_types_list) ?>;

    $(document).ready(function() {
        // --- Initialize Select2 ---
        $('.customer-search').select2({
            ajax: {
                url: 'index.php?page=ajax_search_customers',
                dataType: 'json',
                delay: 250,
                data: params => ({
                    q: params.term
                }),
                processResults: data => ({
                    results: data.results
                })
            },
            placeholder: 'Search by name or phone...',
            minimumInputLength: 2,
        });

        // --- Radio button toggle logic ---
        function setupToggle(type) {
            $(`input[name="${type}_type"]`).change(function() {
                const isExisting = this.value === 'existing';
                $(`#existing_${type}_fields`).toggleClass('hidden', !isExisting);
                $(`#new_${type}_fields`).toggleClass('hidden', isExisting);
                $(`#new_${type}_fields :input`).prop('required', !isExisting);
                $(`#${type}_customer_id`).prop('required', isExisting);
                if (!isExisting) {
                    $(`#${type}_customer_id`).val(null).trigger('change');
                    $(`#new_${type}_fields input, #new_${type}_fields textarea`).val('');
                }
            }).change();
        }
        setupToggle('sender');
        setupToggle('receiver');

        // --- Auto-fill logic ---
        $('#sender_customer_id').on('select2:select', function(e) {
            const data = e.params.data;
            $('#sender_name').val(data.text.split(' (')[0]);
            $('#sender_phone').val(data.phone);
        });
        $('#receiver_customer_id').on('select2:select', function(e) {
            const data = e.params.data;
            $('#receiver_name').val(data.text.split(' (')[0]);
            $('#receiver_phone').val(data.phone);
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        const destRegionSelect = document.getElementById('destination_region_id');
        const destBranchSelect = document.getElementById('destination_branch_id');
        const itemContainer = document.getElementById('item-breakdown-container');
        const addItemBtn = document.getElementById('add-item-btn');

        // --- Branch Population ---
        function populateBranches(regionSelect, branchSelect) {
            const regionId = regionSelect.value;
            branchSelect.innerHTML = '<option value="">Select Region First</option>';
            if (regionId) {
                window.branchesData
                    .filter(branch => branch.region_id == regionId)
                    .forEach(branch => {
                        branchSelect.add(new Option(branch.branch_name, branch.id));
                    });
            }
        }

        // --- Polished Item Breakdown Row ---
        function addItemRow() {
            const row = document.createElement('div');
            row.className = 'grid grid-cols-1 md:grid-cols-12 gap-4 items-end bg-white p-4 sm:p-5 rounded-2xl border border-gray-100 shadow-sm item-row transition-all hover:shadow-md relative group';
            let typeOptions = window.itemTypes.map(type => `<option value="${type}">${type}</option>`).join('');
            row.innerHTML = `
            <div class="md:col-span-5 space-y-1">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">Item Category</label>
                <select name="item_type[]" class="w-full rounded-xl border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 py-3 font-medium text-gray-800 appearance-none"><option value="">Select Category...</option>${typeOptions}</select>
            </div>
            <div class="md:col-span-3 space-y-1 relative">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">Weight</label>
                <div class="relative">
                    <input type="number" name="item_kg[]" class="w-full rounded-xl border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 py-3 pr-8 font-bold text-gray-800" value="0">
                    <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400 font-medium text-sm">kg</span>
                </div>
            </div>
             <div class="md:col-span-3 space-y-1">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">Price / Kg</label>
                <input type="number" name="item_price_per_kg[]" class="w-full rounded-xl border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 py-3 font-bold text-gray-800" step="0.01" value="0">
            </div>
            <div class="md:col-span-1 flex justify-end md:block">
                <button type="button" class="remove-item-btn w-full h-[46px] bg-red-50 hover:bg-red-500 text-red-500 hover:text-white rounded-xl transition duration-300 flex items-center justify-center" title="Remove Item">
                    <svg class="w-5 h-5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            </div>
        `;
            itemContainer.appendChild(row);
        }

        // --- Total Calculation ---
        function calculateTotals() {
            let subtotal = 0,
                totalWeight = 0;
            document.querySelectorAll('.item-row').forEach(row => {
                const kg = parseFloat(row.querySelector('[name="item_kg[]"]').value) || 0;
                const price = parseFloat(row.querySelector('[name="item_price_per_kg[]"]').value) || 0;
                subtotal += kg * price;
                totalWeight += kg;
            });
            const delivery = parseFloat(document.getElementById('delivery_charge').value) || 0;
            const currency = document.getElementById('currency').value;
            const grandTotal = subtotal + delivery;
            document.getElementById('total-amount-display').textContent = `${grandTotal.toFixed(2)} ${currency}`;
            document.getElementById('total-weight-display').textContent = `${totalWeight.toFixed(2)}`;
        }

        // --- Event Listeners ---
        destRegionSelect.addEventListener('change', () => populateBranches(destRegionSelect, destBranchSelect));
        addItemBtn.addEventListener('click', addItemRow);
        itemContainer.addEventListener('click', (e) => {
            // Target the button or the SVG inside it
            if (e.target.closest('.remove-item-btn')) {
                e.target.closest('.item-row').remove();
                calculateTotals();
            }
        });
        document.getElementById('voucher-form').addEventListener('input', calculateTotals);

        // --- Initial State ---
        addItemRow();
        calculateTotals();
        
        
    const confirmBtn = document.getElementById('confirm-voucher-btn');
    const modal = document.getElementById('confirmationModal');
    const detailsContainer = document.getElementById('confirmation-details');
    const cancelBtn = document.getElementById('cancel-btn');
    const submitBtn = document.getElementById('submit-btn');
    const form = document.getElementById('voucher-form');

    confirmBtn.addEventListener('click', function() {
        // --- Gather data from the form ---
        const senderName = document.getElementById('sender_name').value || "Existing (Database)";
        const senderPhone = document.getElementById('sender_phone').value || "---";
        const receiverName = document.getElementById('receiver_name').value || "Existing (Database)";
        const receiverPhone = document.getElementById('receiver_phone').value || "---";
        const totalAmount = document.getElementById('total-amount-display').textContent;
        const totalWeight = document.getElementById('total-weight-display').textContent;
        
        let itemsHtml = `
            <table class="w-full text-sm mt-3">
                <thead class="border-b border-gray-200">
                    <tr>
                        <th class="text-left py-2 font-bold text-gray-500">Item</th>
                        <th class="text-center py-2 font-bold text-gray-500">KG</th>
                        <th class="text-right py-2 font-bold text-gray-500">Price/Kg</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">`;
                
        document.querySelectorAll('.item-row').forEach(row => {
            const type = row.querySelector('[name="item_type[]"]').value;
            const kg = row.querySelector('[name="item_kg[]"]').value;
            const price = row.querySelector('[name="item_price_per_kg[]"]').value;
            if(type && kg > 0) {
                itemsHtml += `
                    <tr>
                        <td class="py-2 font-medium text-gray-800">${type}</td>
                        <td class="py-2 text-center text-gray-600">${kg}</td>
                        <td class="py-2 text-right text-gray-600">${price}</td>
                    </tr>`;
            }
        });
        itemsHtml += '</tbody></table>';

        // --- Populate the modal ---
        detailsContainer.innerHTML = `
            <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                <div>
                    <span class="block text-xs font-bold text-gray-400 uppercase">Sender</span>
                    <span class="font-bold text-gray-800">${senderName}</span><br>
                    <span class="text-gray-500">${senderPhone}</span>
                </div>
                <div>
                    <span class="block text-xs font-bold text-gray-400 uppercase">Receiver</span>
                    <span class="font-bold text-gray-800">${receiverName}</span><br>
                    <span class="text-gray-500">${receiverPhone}</span>
                </div>
            </div>
            <div class="border-t border-b border-gray-100 py-3 my-3">
                ${itemsHtml}
            </div>
            <div class="flex justify-between items-center px-2">
                <span class="text-sm font-bold text-gray-500">Total Weight: <span class="text-gray-900">${totalWeight} kg</span></span>
                <span class="text-xl font-extrabold text-indigo-600">${totalAmount}</span>
            </div>
        `;

        // --- Show the modal ---
        modal.classList.remove('hidden');
    });

    cancelBtn.addEventListener('click', () => modal.classList.add('hidden'));
    submitBtn.addEventListener('click', () => form.submit());
    });
</script>

<?php
include_template('footer');
?>