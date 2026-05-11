<?php
// pos/currencies.php - CRUD management for currencies (V3 Premium UI).
require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) session_start();
if (!is_logged_in() || (!is_admin() && !is_developer())) {
    flash_message('error', 'You are not authorized to access this page.');
    redirect('index.php?page=dashboard');
}

global $connection;
$edit_currency = null;

// --- Handle POST Request (Add or Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim($_POST['code']));
    $name = trim($_POST['name']);
    $currency_id = intval($_POST['currency_id'] ?? 0);

    if (empty($code) || empty($name)) {
        flash_message('error', 'Currency code and name cannot be empty.');
    } else {
        if ($currency_id > 0) { // Update
            $stmt = mysqli_prepare($connection, "UPDATE currencies SET code = ?, name = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'ssi', $code, $name, $currency_id);
            if(mysqli_stmt_execute($stmt)) flash_message('success', 'Currency updated successfully.');
        } else { // Add
            $stmt = mysqli_prepare($connection, "INSERT INTO currencies (code, name) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, 'ss', $code, $name);
            if(mysqli_stmt_execute($stmt)) flash_message('success', 'Currency added successfully.');
        }
    }
    redirect('index.php?page=currencies');
}

// --- Handle GET Request (Delete or Edit) ---
if (isset($_GET['action'])) {
    $id = intval($_GET['id'] ?? 0);
    if ($_GET['action'] === 'delete' && $id > 0) {
        $stmt = mysqli_prepare($connection, "DELETE FROM currencies WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        if(mysqli_stmt_execute($stmt)) flash_message('success', 'Currency deleted.');
        redirect('index.php?page=currencies');
    }
    if ($_GET['action'] === 'edit' && $id > 0) {
        $stmt = mysqli_prepare($connection, "SELECT * FROM currencies WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $edit_currency = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    }
}

// --- Fetch all currencies ---
$currencies = [];
$result = mysqli_query($connection, "SELECT * FROM currencies ORDER BY code ASC");
if($result) while($row = mysqli_fetch_assoc($result)) $currencies[] = $row;


include_template('header', ['page' => 'currencies']);
?>

<!-- V3 Liquid UI Wrapper -->
<div class="relative min-h-[85vh] bg-gray-50/30 p-4 sm:p-8 overflow-hidden font-sans">
    
    <!-- Ambient Background Glows -->
    <div class="absolute top-[10%] left-[-10%] w-[500px] h-[500px] bg-emerald-400/10 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-[500px] h-[500px] bg-teal-400/10 rounded-full blur-[120px] pointer-events-none"></div>

    <div class="max-w-7xl mx-auto relative z-10">
        
        <!-- Header -->
        <div class="flex items-center gap-4 mb-8">
            <div class="w-14 h-14 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl flex items-center justify-center shadow-lg shadow-teal-500/30 text-white transform -rotate-3 hover:rotate-0 transition-transform duration-300">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <h1 class="text-3xl font-extrabold bg-gradient-to-r from-gray-900 to-gray-600 bg-clip-text text-transparent tracking-tight">System Currencies</h1>
                <p class="text-sm font-medium text-gray-500">Configure accepted tender and transaction formatting</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- LEFT COLUMN: CRUD FORM -->
            <div class="lg:col-span-4">
                <div class="bg-white/80 backdrop-blur-2xl p-6 sm:p-8 rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-white/60 sticky top-28">
                    
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                            <svg class="w-5 h-5 <?= $edit_currency ? 'text-amber-500' : 'text-teal-500' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <?php if($edit_currency): ?>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                <?php else: ?>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                <?php endif; ?>
                            </svg>
                            <?= $edit_currency ? 'Edit Currency' : 'Add Currency' ?>
                        </h2>
                        <?php if($edit_currency): ?>
                            <a href="index.php?page=currencies" class="text-xs font-bold text-gray-400 hover:text-red-500 transition-colors uppercase tracking-wider">Cancel</a>
                        <?php endif; ?>
                    </div>
                    
                    <form action="index.php?page=currencies" method="POST" class="space-y-5">
                        <input type="hidden" name="currency_id" value="<?= $edit_currency['id'] ?? '' ?>">
                        
                        <div class="space-y-1.5 group">
                            <label for="code" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">Currency Code</label>
                            <input type="text" id="code" name="code" class="w-full rounded-2xl border-gray-200 bg-gray-50/50 focus:bg-white focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition-all py-3 px-4 font-mono font-bold text-gray-800 shadow-sm uppercase placeholder-gray-300" placeholder="e.g. USD" value="<?= htmlspecialchars($edit_currency['code'] ?? '') ?>" required maxlength="10">
                            <p class="text-[10px] font-medium text-gray-400 ml-1 mt-1">Short identifier used in ledgers (e.g. USD, MMK)</p>
                        </div>
                        
                        <div class="space-y-1.5 group">
                            <label for="name" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">Currency Name</label>
                            <input type="text" id="name" name="name" class="w-full rounded-2xl border-gray-200 bg-gray-50/50 focus:bg-white focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition-all py-3 px-4 font-medium text-gray-800 shadow-sm placeholder-gray-300" placeholder="e.g. US Dollar" value="<?= htmlspecialchars($edit_currency['name'] ?? '') ?>" required>
                        </div>

                        <div class="pt-4">
                            <?php if ($edit_currency): ?>
                                <button type="submit" class="w-full bg-gradient-to-r from-amber-500 to-orange-500 text-white py-3.5 px-4 rounded-2xl font-bold text-md hover:from-amber-600 hover:to-orange-600 focus:outline-none focus:ring-4 focus:ring-amber-500/30 shadow-[0_8px_20px_rgb(245,158,11,0.3)] transition-all transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M7 7l13 13M20 20v-5h-5"/></svg>
                                    Update Configuration
                                </button>
                            <?php else: ?>
                                <button type="submit" class="w-full bg-gradient-to-r from-teal-500 to-emerald-600 text-white py-3.5 px-4 rounded-2xl font-bold text-md hover:from-teal-600 hover:to-emerald-700 focus:outline-none focus:ring-4 focus:ring-teal-500/30 shadow-[0_8px_20px_rgb(20,184,166,0.3)] transition-all transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                    Register Currency
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- RIGHT COLUMN: DATA TABLE -->
            <div class="lg:col-span-8">
                <div class="bg-white/70 backdrop-blur-2xl rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-white/60 overflow-hidden h-full flex flex-col">
                    
                    <div class="p-6 sm:p-8 border-b border-gray-100 flex items-center justify-between bg-white/50">
                        <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                            Active Currencies
                        </h2>
                        <span class="bg-teal-50 text-teal-600 py-1 px-3 rounded-full text-xs font-bold border border-teal-100 shadow-sm">
                            <?= count($currencies) ?> Currencies
                        </span>
                    </div>

                    <div class="overflow-x-auto w-full custom-scrollbar flex-1 p-2">
                        <table class="w-full text-left border-collapse whitespace-nowrap">
                            <thead>
                                <tr>
                                    <th class="py-4 px-6 text-xs font-extrabold text-gray-400 uppercase tracking-widest border-b border-gray-100">Code</th>
                                    <th class="py-4 px-6 text-xs font-extrabold text-gray-400 uppercase tracking-widest border-b border-gray-100">Full Name</th>
                                    <th class="py-4 px-6 text-xs font-extrabold text-gray-400 uppercase tracking-widest border-b border-gray-100 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100/60">
                                <?php if (empty($currencies)): ?>
                                    <tr>
                                        <td colspan="3" class="py-12 text-center text-gray-400 font-medium">No currencies configured.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($currencies as $currency): ?>
                                        <tr class="hover:bg-white/90 transition-colors duration-200 group">
                                            <td class="py-4 px-6">
                                                <span class="inline-flex items-center gap-1.5 font-mono text-sm font-bold text-teal-700 bg-teal-50/50 px-3 py-1.5 rounded-lg border border-teal-100 shadow-sm">
                                                    <?= htmlspecialchars($currency['code']) ?>
                                                </span>
                                            </td>
                                            <td class="py-4 px-6">
                                                <span class="font-bold text-gray-800"><?= htmlspecialchars($currency['name']) ?></span>
                                            </td>
                                            <td class="py-4 px-6 text-right space-x-2">
                                                <a href="index.php?page=currencies&action=edit&id=<?= $currency['id'] ?>" class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-gray-50 text-gray-500 hover:text-amber-500 hover:bg-amber-50 hover:shadow-sm border border-transparent hover:border-amber-100 transition-all" title="Edit">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                </a>
                                                <a href="index.php?page=currencies&action=delete&id=<?= $currency['id'] ?>" onclick="return confirm('Are you sure you want to delete the currency <?= htmlspecialchars($currency['code']) ?>? This action cannot be undone.');" class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-gray-50 text-gray-500 hover:text-red-500 hover:bg-red-50 hover:shadow-sm border border-transparent hover:border-red-100 transition-all" title="Delete">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </a>
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
    </div>
</div>

<style>
    /* Custom scrollbar to keep horizontal scrolling elegant on desktop */
    .custom-scrollbar::-webkit-scrollbar {
        height: 6px;
        width: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: rgba(243, 244, 246, 0.5); 
        border-radius: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(156, 163, 175, 0.5); 
        border-radius: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: rgba(107, 114, 128, 0.8); 
    }
</style>

<?php include_template('footer'); ?>