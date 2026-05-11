<?php
// pos/expenses.php - Page for managing expenses (Premium V3 UI).

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authentication ---
if (!is_logged_in()) {
    flash_message('error', 'Please log in to manage expenses.');
    redirect('index.php?page=login');
}

global $connection;

// --- CRITICAL FIX FOR MYANMAR FONTS ---
mysqli_set_charset($connection, "utf8mb4");

$user_id = $_SESSION['user_id'];
$edit_expense = null;

// --- Fetch Currencies for Dropdown ---
$currencies = [];
$currency_result = mysqli_query($connection, "SELECT code FROM currencies ORDER BY code ASC");
if ($currency_result) {
    while ($row = mysqli_fetch_assoc($currency_result)) {
        $currencies[] = $row['code'];
    }
}

// --- Handle Add/Update/Delete ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expense_id = intval($_POST['expense_id'] ?? 0);
    $description = trim($_POST['description']);
    $amount = floatval($_POST['amount']);
    $currency = trim($_POST['currency']);
    $expense_date = $_POST['expense_date'];

    if (!empty($description) && $amount > 0 && !empty($expense_date) && !empty($currency)) {
        if ($expense_id > 0) { // Update logic
            $stmt = mysqli_prepare($connection, "UPDATE expenses SET description = ?, amount = ?, currency = ?, expense_date = ? WHERE id = ? AND created_by_user_id = ?");
            mysqli_stmt_bind_param($stmt, 'sdssii', $description, $amount, $currency, $expense_date, $expense_id, $user_id);
        } else { // Insert logic
            $stmt = mysqli_prepare($connection, "INSERT INTO expenses (description, amount, currency, expense_date, created_by_user_id) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'sdssi', $description, $amount, $currency, $expense_date, $user_id);
        }
        
        if(mysqli_stmt_execute($stmt)){
            flash_message('success', 'Expense saved successfully.');
        } else {
            flash_message('error', 'Failed to save expense: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    } else {
        flash_message('error', 'Please fill in all required fields.');
    }
    redirect('index.php?page=expenses');
}

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = intval($_GET['id'] ?? 0);

    if ($action === 'edit' && $id > 0) {
        $stmt = mysqli_prepare($connection, "SELECT * FROM expenses WHERE id = ? AND created_by_user_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $edit_expense = mysqli_fetch_assoc($result);
    }
    
    if ($action === 'delete' && $id > 0) {
        $stmt = mysqli_prepare($connection, "DELETE FROM expenses WHERE id = ? AND created_by_user_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $user_id);
        if(mysqli_stmt_execute($stmt)){
            flash_message('success', 'Expense deleted successfully.');
        } else {
            flash_message('error', 'Failed to delete expense.');
        }
        redirect('index.php?page=expenses');
    }
}

// --- Fetch Expenses for Display ---
$expenses = [];
$query = "SELECT * FROM expenses ORDER BY expense_date DESC";
$result = mysqli_query($connection, $query);
if($result){
    while($row = mysqli_fetch_assoc($result)){
        $expenses[] = $row;
    }
}

include_template('header', ['page' => 'expenses']);
?>

<!-- V3 Liquid UI Wrapper -->
<div class="relative min-h-[85vh] bg-slate-50/30 p-4 sm:p-8 overflow-hidden font-sans">
    
    <!-- Ambient Background Glows -->
    <div class="absolute top-[10%] left-[-10%] w-[500px] h-[500px] bg-rose-500/10 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-[500px] h-[500px] bg-orange-500/10 rounded-full blur-[120px] pointer-events-none"></div>

    <div class="max-w-7xl mx-auto relative z-10">
        
        <!-- Header -->
        <div class="flex items-center gap-4 mb-8">
            <div class="w-14 h-14 bg-gradient-to-br from-rose-500 to-red-600 rounded-2xl flex items-center justify-center shadow-lg shadow-red-500/30 text-white transform -rotate-3 hover:rotate-0 transition-transform duration-300">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5-2.5a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0zM19.5 16.5a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/></svg>
            </div>
            <div>
                <h1 class="text-3xl font-extrabold bg-gradient-to-r from-slate-900 to-slate-600 bg-clip-text text-transparent tracking-tight">System Expenses</h1>
                <p class="text-sm font-medium text-slate-500">Record and monitor operational outgoings</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- LEFT COLUMN: CRUD FORM -->
            <div class="lg:col-span-4">
                <div class="bg-white/80 backdrop-blur-2xl p-6 sm:p-8 rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-white/60 sticky top-28">
                    
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                            <svg class="w-5 h-5 <?= $edit_expense ? 'text-amber-500' : 'text-rose-500' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <?php if($edit_expense): ?>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                <?php else: ?>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                <?php endif; ?>
                            </svg>
                            <?= $edit_expense ? 'Edit Expense' : 'Log New Expense' ?>
                        </h2>
                        <?php if($edit_expense): ?>
                            <a href="index.php?page=expenses" class="text-xs font-bold text-slate-400 hover:text-red-500 transition-colors uppercase tracking-wider">Cancel</a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Enforce UTF-8 form submission for Myanmar language -->
                    <form action="index.php?page=expenses" method="POST" accept-charset="UTF-8" class="space-y-5">
                        <input type="hidden" name="expense_id" value="<?= $edit_expense['id'] ?? '' ?>">
                        
                        <div class="space-y-1.5 group">
                            <label for="expense_date" class="block text-xs font-bold text-slate-500 uppercase tracking-wider ml-1">Expense Date</label>
                            <input type="date" id="expense_date" name="expense_date" class="w-full rounded-2xl border-slate-200 bg-slate-50/50 focus:bg-white focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition-all py-3 px-4 font-medium text-slate-800 shadow-sm" value="<?= htmlspecialchars($edit_expense['expense_date'] ?? date('Y-m-d')) ?>" required>
                        </div>
                        
                        <div class="space-y-1.5 group">
                            <label for="description" class="block text-xs font-bold text-slate-500 uppercase tracking-wider ml-1">Description</label>
                            <textarea id="description" name="description" rows="2" class="w-full rounded-2xl border-slate-200 bg-slate-50/50 focus:bg-white focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition-all py-3 px-4 font-medium text-slate-800 shadow-sm placeholder-slate-300" placeholder="e.g. Office Supplies / ရုံးသုံးပစ္စည်း" required><?= htmlspecialchars($edit_expense['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5 group">
                                <label for="amount" class="block text-xs font-bold text-slate-500 uppercase tracking-wider ml-1">Amount</label>
                                <input type="number" step="0.01" id="amount" name="amount" class="w-full rounded-2xl border-slate-200 bg-slate-50/50 focus:bg-white focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition-all py-3 px-4 font-bold text-rose-600 shadow-sm placeholder-slate-300" placeholder="0.00" value="<?= htmlspecialchars($edit_expense['amount'] ?? '') ?>" required>
                            </div>
                            
                            <div class="space-y-1.5 group">
                                <label for="currency" class="block text-xs font-bold text-slate-500 uppercase tracking-wider ml-1">Currency</label>
                                <select id="currency" name="currency" class="w-full rounded-2xl border-slate-200 bg-slate-50/50 focus:bg-white focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition-all py-3 px-4 font-bold text-slate-700 shadow-sm appearance-none" required>
                                    <?php foreach($currencies as $currency_code): ?>
                                        <option value="<?= htmlspecialchars($currency_code, ENT_QUOTES, 'UTF-8') ?>" 
                                            <?= (isset($edit_expense) && $edit_expense['currency'] === $currency_code) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($currency_code, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="pt-4">
                            <?php if ($edit_expense): ?>
                                <button type="submit" class="w-full bg-gradient-to-r from-amber-500 to-orange-500 text-white py-3.5 px-4 rounded-2xl font-bold text-md hover:from-amber-600 hover:to-orange-600 focus:outline-none focus:ring-4 focus:ring-amber-500/30 shadow-[0_8px_20px_rgb(245,158,11,0.3)] transition-all transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M7 7l13 13M20 20v-5h-5"/></svg>
                                    Update Expense
                                </button>
                            <?php else: ?>
                                <button type="submit" class="w-full bg-gradient-to-r from-rose-500 to-red-600 text-white py-3.5 px-4 rounded-2xl font-bold text-md hover:from-rose-600 hover:to-red-700 focus:outline-none focus:ring-4 focus:ring-red-500/30 shadow-[0_8px_20px_rgb(225,29,72,0.3)] transition-all transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                    Record Expense
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- RIGHT COLUMN: DATA TABLE -->
            <div class="lg:col-span-8">
                <div class="bg-white/70 backdrop-blur-2xl rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-white/60 overflow-hidden h-full flex flex-col">
                    
                    <div class="p-6 sm:p-8 border-b border-slate-100 flex items-center justify-between bg-white/50">
                        <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                            Expense Ledger
                        </h2>
                        <span class="bg-rose-50 text-rose-600 py-1 px-3 rounded-full text-xs font-bold border border-rose-100 shadow-sm">
                            <?= count($expenses) ?> Entries
                        </span>
                    </div>

                    <div class="overflow-x-auto w-full custom-scrollbar flex-1 p-2">
                        <table class="w-full text-left border-collapse whitespace-nowrap">
                            <thead>
                                <tr>
                                    <th class="py-4 px-6 text-xs font-extrabold text-slate-400 uppercase tracking-widest border-b border-slate-100">Date</th>
                                    <th class="py-4 px-6 text-xs font-extrabold text-slate-400 uppercase tracking-widest border-b border-slate-100">Description</th>
                                    <th class="py-4 px-6 text-xs font-extrabold text-slate-400 uppercase tracking-widest border-b border-slate-100">Amount</th>
                                    <th class="py-4 px-6 text-xs font-extrabold text-slate-400 uppercase tracking-widest border-b border-slate-100 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100/60">
                                <?php if (empty($expenses)): ?>
                                    <tr>
                                        <td colspan="4" class="py-12 text-center text-slate-400 font-medium">No expenses recorded yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($expenses as $expense): ?>
                                        <tr class="hover:bg-white/90 transition-colors duration-200 group">
                                            <td class="py-4 px-6">
                                                <span class="text-sm font-bold text-slate-600">
                                                    <?= htmlspecialchars($expense['expense_date']) ?>
                                                </span>
                                            </td>
                                            <td class="py-4 px-6">
                                                <!-- ENT_QUOTES | UTF-8 prevents corruption of Myanmar encoding -->
                                                <span class="font-medium text-slate-800"><?= htmlspecialchars($expense['description'], ENT_QUOTES, 'UTF-8') ?></span>
                                            </td>
                                            <td class="py-4 px-6">
                                                <span class="inline-flex items-center gap-1.5 font-bold text-rose-600 bg-rose-50/80 px-2.5 py-1 rounded-md border border-rose-100">
                                                    <?= htmlspecialchars($expense['currency']) ?> <?= number_format($expense['amount'], 2) ?>
                                                </span>
                                            </td>
                                            <td class="py-4 px-6 text-right space-x-2">
                                                <a href="index.php?page=expenses&action=edit&id=<?= $expense['id'] ?>" class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-slate-50 text-slate-500 hover:text-amber-500 hover:bg-amber-50 hover:shadow-sm border border-transparent hover:border-amber-100 transition-all" title="Edit">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                </a>
                                                <a href="index.php?page=expenses&action=delete&id=<?= $expense['id'] ?>" onclick="return confirm('Are you sure you want to delete this expense? This action cannot be undone.');" class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-slate-50 text-slate-500 hover:text-red-500 hover:bg-red-50 hover:shadow-sm border border-transparent hover:border-red-100 transition-all" title="Delete">
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