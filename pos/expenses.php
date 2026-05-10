<?php
// pos/expenses.php - Page for managing expenses.

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
<div class="container mx-auto p-6">
    <h1 class="text-3xl font-bold mb-6">Manage Expenses</h1>

    <!-- Add/Edit Form -->
    <div class="bg-white p-6 rounded-xl shadow-lg mb-8">
    <h2 class="text-2xl font-semibold text-gray-800 mb-6">
        <?= $edit_expense ? 'Edit Expense' : 'Add New Expense' ?>
    </h2>

    <form action="index.php?page=expenses" method="POST">
        <input type="hidden" name="expense_id" value="<?= $edit_expense['id'] ?? '' ?>">

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="md:col-span-2">
                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                <input type="text" id="description" name="description" 
                       value="<?= htmlspecialchars($edit_expense['description'] ?? '') ?>" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">Amount</label>
                <input type="number" step="0.01" id="amount" name="amount" 
                       value="<?= htmlspecialchars($edit_expense['amount'] ?? '') ?>" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="currency" class="block text-sm font-medium text-gray-700 mb-2">Currency</label>
                <select id="currency" name="currency" required
                        class="w-full border border-gray-300 rounded-lg px-4 py-2 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <?php foreach($currencies as $currency_code): ?>
                        <option value="<?= htmlspecialchars($currency_code) ?>" 
                            <?= (isset($edit_expense) && $edit_expense['currency'] === $currency_code) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($currency_code) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="expense_date" class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                <input type="date" id="expense_date" name="expense_date" 
                       value="<?= htmlspecialchars($edit_expense['expense_date'] ?? date('Y-m-d')) ?>" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="md:col-span-4 flex justify-end">
                <button type="submit"
                        class="px-6 py-2 bg-blue-600 text-white font-semibold rounded-lg shadow-md hover:bg-blue-700 transition">
                    <?= $edit_expense ? 'Update Expense' : 'Add Expense' ?>
                </button>
            </div>
        </div>
    </form>
</div>


    <!-- Expense List -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-2xl font-semibold mb-4">Expense History</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="table-header">Date</th>
                        <th class="table-header">Description</th>
                        <th class="table-header">Amount</th>
                        <th class="table-header">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach($expenses as $expense): ?>
                    <tr>
                        <td class="table-cell"><?= htmlspecialchars($expense['expense_date']) ?></td>
                        <td class="table-cell"><?= htmlspecialchars($expense['description']) ?></td>
                        <td class="table-cell"><?= htmlspecialchars($expense['currency']) ?> <?= number_format($expense['amount'], 2) ?></td>
                        <td class="table-cell">
                            <!--<a href="index.php?page=expenses&action=edit&id=<?= $expense['id'] ?>" class="text-indigo-600 hover:text-indigo-900">Edit</a>-->
                            <a href="index.php?page=expenses&action=delete&id=<?= $expense['id'] ?>" class="text-red-600 hover:text-red-900 ml-4" onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include_template('footer'); ?>

