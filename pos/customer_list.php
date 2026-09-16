<?php
// pos/customer_list.php - Displays a list of all registered customers.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authorization ---
if (!is_logged_in() || (!is_admin() && !is_developer())) {
    flash_message('error', 'You are not authorized to access this page.');
    redirect('index.php?page=dashboard');
}

global $connection;

// --- Fetch Customers ---
$customers = [];
$query = "SELECT id, username, phone, created_at FROM users WHERE user_type = 'Customer' ORDER BY created_at DESC";
$result = mysqli_query($connection, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $customers[] = $row;
    }
} else {
    flash_message('error', 'Could not retrieve customer list.');
}

include_template('header', ['page' => 'customer_list']);
?>

<!-- V5 Liquid UI Customer Directory -->
<div class="relative min-h-[85vh] p-4 sm:p-8 font-sans">
    <div class="max-w-7xl mx-auto relative z-10">
        
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-5 animate-fadeInDown">
            <div>
                <h1 class="text-3xl sm:text-4xl font-extrabold bg-gradient-to-r from-slate-900 to-indigo-800 bg-clip-text text-transparent tracking-tight" data-i18n="Customer List">
                    Customer Directory
                </h1>
                <p class="text-sm font-medium text-slate-500 mt-1" data-i18n="Manage and track all registered customer profiles">
                    Manage and track all registered customer profiles in the MBPOS network.
                </p>
            </div>
            <a href="index.php?page=customer_register" class="btn-primary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold text-white shadow-lg shadow-blue-500/25 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span data-i18n="Register Customer">Register Customer</span>
            </a>
        </div>

        <!-- Directory Glass Card -->
        <div class="v5-glass-card p-6 sm:p-8 shadow-xl overflow-hidden animate-fadeInDown" style="animation-delay: 0.1s;">
            <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-black">
                        👥
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-800" data-i18n="Customers">Registered Profiles</h2>
                        <span class="text-xs text-slate-400 font-semibold"><?= count($customers) ?> total records</span>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full v5-table">
                    <thead>
                        <tr>
                            <th class="text-left" data-i18n="Customer Name">Customer</th>
                            <th class="text-left" data-i18n="Phone Number">Phone Number</th>
                            <th class="text-left" data-i18n="Date">Registered Date</th>
                            <th class="text-right" data-i18n="Status">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($customers)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-8 text-slate-400 font-medium">
                                    No customer records found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($customers as $customer): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="font-bold text-slate-800 flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-blue-500 to-indigo-600 text-white font-bold text-xs flex items-center justify-center shadow-sm">
                                            <?= strtoupper(substr($customer['username'] ?? 'C', 0, 1)) ?>
                                        </div>
                                        <span><?= htmlspecialchars($customer['username']) ?></span>
                                    </td>
                                    <td class="font-semibold text-slate-600 font-mono text-xs">
                                        <?= htmlspecialchars($customer['phone']) ?>
                                    </td>
                                    <td class="text-slate-500 text-xs font-medium">
                                        <?= date('M j, Y', strtotime($customer['created_at'])) ?>
                                    </td>
                                    <td class="text-right">
                                        <span class="v5-badge bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            Active
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include_template('footer'); ?>
