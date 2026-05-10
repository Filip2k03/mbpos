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
    .select2-container .select2-selection--single {
        height: 42px;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 40px;
        padding-left: 1rem;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 40px;
    }
</style>

<div class="max-w-6xl mx-auto">
    <form action="index.php?page=voucher_create" method="POST" id="voucher-form">
        <div class="bg-white p-8 rounded-2xl shadow-xl border border-gray-200">
            <h2 class="text-3xl font-bold mb-6">Create New Voucher</h2>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Sender Section -->
                <div class="p-6 rounded-xl border border-gray-300 bg-gray-100">
                    <h3 class="text-xl font-semibold mb-4 text-gray-700">Sender Details</h3>
                    <div class="flex items-center space-x-6 mb-6">
                        <label class="inline-flex items-center"><input type="radio" name="sender_type" value="new" class="form-radio text-blue-600"><span class="ml-2 text-gray-600">New</span></label>
                        <label class="inline-flex items-center ml-6"><input type="radio" name="sender_type" value="existing" checked class="form-radio text-blue-600"><span class="ml-2 text-gray-600">Existing Customer</span></label>
                    </div>
                    <div id="existing_sender_fields" class="hidden space-y-4">
                        <label for="sender_customer_id" class="block text-sm font-medium text-gray-600">Search Customer</label>
                        <select id="sender_customer_id" name="sender_customer_id" class="customer-search w-full"></select>
                    </div>
                    <div id="new_sender_fields" class="space-y-5">
                        <div><label for="sender_name" class="block text-sm font-medium text-gray-600">Name</label><input type="text" id="sender_name" name="sender_name" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required></div>
                        <div><label for="sender_phone" class="block text-sm font-medium text-gray-600">Phone</label><input type="text" id="sender_phone" name="sender_phone" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required></div>
                    </div>
                </div>

                <!-- Receiver Section -->
                <div class="p-6 rounded-xl border border-gray-300 bg-gray-100">
                    <h3 class="text-xl font-semibold mb-4 text-gray-700">Receiver Details</h3>
                    <div class="flex items-center space-x-6 mb-6">
                        <label class="inline-flex items-center">
                            <input type="radio" name="receiver_type" value="new" class="form-radio text-blue-600">
                            <span class="ml-2 text-gray-600">New</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="receiver_type" value="existing" checked class="form-radio text-blue-600">
                            <span class="ml-2 text-gray-600">Existing Customer</span>
                        </label>
                    </div>
                    <div id="existing_receiver_fields" class="hidden space-y-4">
                        <label for="receiver_customer_id" class="block text-sm font-medium text-gray-600">Search Customer</label>
                        <select id="receiver_customer_id" name="receiver_customer_id" class="customer-search w-full">
                        </select>
                    </div>

                    <!-- New Customer -->
                    <div id="new_receiver_fields" class="space-y-5">
                        <div>
                            <label for="receiver_name" class="block text-sm font-medium text-gray-600">Name</label>
                            <input type="text" id="receiver_name" name="receiver_name" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        </div>
                        <div>
                            <label for="receiver_phone" class="block text-sm font-medium text-gray-600">Phone</label>
                            <input type="text" id="receiver_phone" name="receiver_phone" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        </div>
                        <div>
                            <label for="receiver_address" class="block text-sm font-medium text-gray-600">Address</label>
                            <textarea id="receiver_address" name="receiver_address" rows="3" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required></textarea>
                        </div>
                    </div>
                </div>

                <div class="p-6 rounded-xl border border-gray-100 bg-gray-50 mt-8">
                    <h3 class="text-xl font-semibold mb-6 text-gray-700">Shipment & Item Details</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Origin -->
                        <div>
                            <label class="block text-sm font-medium text-gray-600">Origin</label>
                            <p class="mt-2 px-3 py-2 rounded-lg border border-gray-300 bg-white text-gray-800 font-medium">
                                <?= htmlspecialchars($user_info['region_name'] ?? 'N/A') ?> /
                                <?= htmlspecialchars($user_info['branch_name'] ?? 'N/A') ?>
                            </p>
                            <input type="hidden" name="origin_region_id" value="<?= $user_info['region_id'] ?>">
                            <input type="hidden" name="origin_branch_id" value="<?= $user_info['branch_id'] ?>">
                        </div>

                        <!-- Destination Region -->
                        <div>
                            <label for="destination_region_id" class="block text-sm font-medium text-gray-600">Destination Region</label>
                            <select id="destination_region_id" name="destination_region_id"
                                class="w-full rounded-lg border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 mt-2 p-2"
                                required>
                                <option value="">Select Destination</option>
                                <?php foreach ($all_regions as $region): ?>
                                    <?php if ($region['id'] != $user_info['region_id']): ?>
                                        <option value="<?= $region['id'] ?>"><?= htmlspecialchars($region['region_name']) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Destination Branch -->
                        <div class="md:col-span-2">
                            <label for="destination_branch_id" class="block text-sm font-medium text-gray-600">Destination Branch</label>
                            <select id="destination_branch_id" name="destination_branch_id"
                                class="w-full rounded-lg border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 mt-2 p-2"
                                required>
                                <option value="">Select a destination region first</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="p-6 rounded-xl border border-gray-100 bg-gray-50 mt-8">
                    <h3 class="text-xl font-semibold mb-6 text-gray-700">Notes & Charges</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="delivery_charge" class="block text-sm font-medium text-gray-600">Delivery Charge</label>
                            <input type="number" step="0.01" id="delivery_charge" name="delivery_charge" class="form-input rounded-lg border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 mt-2 p-2" value="0">
                        </div>
                        <div>
                            <label for="currency" class="block text-sm font-medium text-gray-600">Currency</label>
                            <select id="currency" name="currency" class="form-select rounded-lg border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 mt-2 p-2">
                                <?php foreach ($currencies as $c): ?>
                                    <option value="<?= $c ?>"><?= $c ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="delivery_type" class="block text-sm font-medium text-gray-600">Delivery Type</label>
                            <select id="delivery_type" name="delivery_type" class="form-select rounded-lg border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 mt-2 p-2">
                                <?php foreach ($delivery_types as $dt): ?>
                                    <option value="<?= $dt ?>"><?= $dt ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="md:col-span-3">
                            <label for="notes" class="block text-sm font-medium text-gray-600">Notes</label>
                            <textarea id="notes" name="notes" rows="3" class="block text-sm font-medium w-full rounded-lg border border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 mt-2 p-3"></textarea>
                        </div>
                    </div>
                    
                </div>

                <div class="mt-6 pt-6 border-t">
                    <div id="item-breakdown-container" class="space-y-4"></div>
                    <button type="button" id="add-item-btn" class=" px-5 py-2 bg-green-400 hover:bg-green-600 text-gray-700 font-medium rounded-lg shadow-sm transition">Add Another Item</button>
                </div>
            </div>
            
            <!-- Totals & Submit -->
        <div class="mt-8 pt-6 border-t flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="text-center md:text-left">
                 <p class="text-gray-600">Total Weight: <span id="total-weight-display" class="font-bold">0.00 kg</span></p>
                 <h3 class="text-3xl font-bold text-gray-800">Total: <span id="total-amount-display" class="text-blue-600">0.00</span></h3>
            </div>
            <!-- Changed button type to 'button' to trigger modal first -->
            <button type="button" id="confirm-voucher-btn" class="btn btn-lg w-full md:w-auto px-5 py-2 bg-blue-400 hover:bg-blue-600 text-gray-700 font-medium rounded-lg shadow-sm transition">Create Voucher</button>
        </div>

    </form>
</div>

<!-- Confirmation Modal -->
<div id="confirmationModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center hidden z-50">
    <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-lg">
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Confirm Voucher Details</h2>
        <div id="confirmation-details" class="text-left space-y-4">
            <!-- Details will be injected here by JavaScript -->
        </div>
        <div class="mt-6 flex justify-end gap-4">
            <button type="button" id="cancel-btn" class="btn-secondary">Cancel</button>
            <button type="button" id="submit-btn" class="btn">Confirm & Create</button>
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
            placeholder: 'Search by name or phone',
            minimumInputLength: 2,
        });

        // --- Radio button toggle logic ---
        function setupToggle(type) {
            $(`input[name="${type}_type"]`).change(function() {
                const isExisting = this.value === 'existing';
                $(`#existing_${type}_fields`).toggle(isExisting);
                $(`#new_${type}_fields`).toggle(!isExisting);
                $(`#new_${type}_fields :input`).prop('required', !isExisting);
                $(`#${type}_customer_id`).prop('required', isExisting);
                if (!isExisting) {
                    // Clear search box and fields when switching to new
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
            branchSelect.innerHTML = '<option value="">Select branch</option>';
            if (regionId) {
                window.branchesData
                    .filter(branch => branch.region_id == regionId)
                    .forEach(branch => {
                        branchSelect.add(new Option(branch.branch_name, branch.id));
                    });
            }
        }

        // --- Item Breakdown ---
        function addItemRow() {
            const row = document.createElement('div');
            row.className = 'grid grid-cols-1 md:grid-cols-4 gap-4 item-row';
            let typeOptions = window.itemTypes.map(type => `<option value="${type}">${type}</option>`).join('');
            row.innerHTML = `
            <div class="md:col-span-2">
                <label class="label font-bold text-gray-800">Item Type</label>
                <select name="item_type[]" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-purple-500"><option value="">Select...</option>${typeOptions}</select>
            </div>
            <div>
                <label class="form-label-sm">Weight (kg)</label>
                <input type="number" name="item_kg[]" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-purple-500" value="0">
            </div>
             <div>
                <label class="form-label-sm">Price/Kg</label>
                <input type="number" name="item_price_per_kg[]" class="border border-gray-300 rounded-lg p-2 w-full focus:outline-none focus:ring-2 focus:ring-purple-500" step="0.01" value="0">
            </div>
            <div class="flex items-end">
                <button type="button" class="remove-item-btn bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300 w-full">Remove</button>
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
            document.getElementById('total-weight-display').textContent = `${totalWeight.toFixed(2)} kg`;
        }

        // --- Event Listeners ---
        destRegionSelect.addEventListener('change', () => populateBranches(destRegionSelect, destBranchSelect));
        addItemBtn.addEventListener('click', addItemRow);
        itemContainer.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-item-btn')) {
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
        const senderName = document.getElementById('sender_name').value;
        const senderPhone = document.getElementById('sender_phone').value;
        const receiverName = document.getElementById('receiver_name').value;
        const receiverPhone = document.getElementById('receiver_phone').value;
        const totalAmount = document.getElementById('total-amount-display').textContent;
        
        let itemsHtml = '<table class="min-w-full divide-y divide-gray-200"><thead><tr><th class="table-header">Item</th><th class="table-header">KG</th><th class="table-header">Price/Kg</th></tr></thead><tbody>';
        document.querySelectorAll('.item-row').forEach(row => {
            const type = row.querySelector('[name="item_type[]"]').value;
            const kg = row.querySelector('[name="item_kg[]"]').value;
            const price = row.querySelector('[name="item_price_per_kg[]"]').value;
            if(type && kg > 0) {
                itemsHtml += `<tr><td class="table-cell">${type}</td><td class="table-cell">${kg}</td><td class="table-cell">${price}</td></tr>`;
            }
        });
        itemsHtml += '</tbody></table>';

        // --- Populate the modal ---
        detailsContainer.innerHTML = `
            <p><strong>Sender:</strong> ${senderName} (${senderPhone})</p>
            <p><strong>Receiver:</strong> ${receiverName} (${receiverPhone})</p>
            <hr>
            ${itemsHtml}
            <hr>
            <p class="text-right text-xl font-bold">Total: ${totalAmount}</p>
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