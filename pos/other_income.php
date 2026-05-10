<?php
// pos/other_income.php - Page for managing other sources of income.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authentication ---
if (!is_logged_in()) {
    flash_message('error', 'Please log in to manage income.');
    redirect('index.php?page=login');
}

global $connection;
$user_id = $_SESSION['user_id'];
$edit_income = null;

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
    $income_id = intval($_POST['income_id'] ?? 0);
    $description = trim($_POST['description']);
    $amount = floatval($_POST['amount']);
    $currency = trim($_POST['currency']);
    $income_date = $_POST['income_date'];

    if (!empty($description) && $amount > 0 && !empty($income_date) && !empty($currency)) {
        if ($income_id > 0) { // Update
            $stmt = mysqli_prepare($connection, "UPDATE other_income SET description = ?, amount = ?, currency = ?, income_date = ? WHERE id = ? AND created_by_user_id = ?");
            mysqli_stmt_bind_param($stmt, 'sdssii', $description, $amount, $currency, $income_date, $income_id, $user_id);
        } else { // Insert
            $stmt = mysqli_prepare($connection, "INSERT INTO other_income (description, amount, currency, income_date, created_by_user_id) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'sdssi', $description, $amount, $currency, $income_date, $user_id);
        }
        
        if(mysqli_stmt_execute($stmt)){
            flash_message('success', 'Income record saved successfully.');
        } else {
             flash_message('error', 'Failed to save income record: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    } else {
        flash_message('error', 'Please fill in all required fields.');
    }
    redirect('index.php?page=other_income');
}

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = intval($_GET['id'] ?? 0);

    if ($action === 'edit' && $id > 0) {
        $stmt = mysqli_prepare($connection, "SELECT * FROM other_income WHERE id = ? AND created_by_user_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $edit_income = mysqli_fetch_assoc($result);
    }
    
    if ($action === 'delete' && $id > 0) {
        $stmt = mysqli_prepare($connection, "DELETE FROM other_income WHERE id = ? AND created_by_user_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $user_id);
         if(mysqli_stmt_execute($stmt)){
            flash_message('success', 'Income record deleted successfully.');
        } else {
            flash_message('error', 'Failed to delete income record.');
        }
        redirect('index.php?page=other_income');
    }
}


// --- Fetch Income for Display ---
$incomes = [];
$query = "SELECT * FROM other_income ORDER BY income_date DESC";
$result = mysqli_query($connection, $query);
if($result){
    while($row = mysqli_fetch_assoc($result)){
        $incomes[] = $row;
    }
}

include_template('header', ['page' => 'other_income']);
?>
<div class="container mx-auto p-6">
    <h1 class="text-3xl font-bold mb-6">Manage Other Income</h1>

     <!-- Add/Edit Form -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h2 class="text-2xl font-semibold mb-4"><?= $edit_income ? 'Edit Income Record' : 'Add New Income Record' ?></h2>
        <form action="index.php?page=other_income" method="POST">
            <input type="hidden" name="income_id" value="<?= $edit_income['id'] ?? '' ?>">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="md:col-span-2">
                    <label for="description" class="form-label">Description</label>
                    <input type="text" id="description" name="description" class="form-input" value="<?= htmlspecialchars($edit_income['description'] ?? '') ?>" required>
                </div>
                <div>
                    <label for="amount" class="form-label">Amount</label>
                    <input type="number" step="0.01" id="amount" name="amount" class="form-input" value="<?= htmlspecialchars($edit_income['amount'] ?? '') ?>" required>
                </div>
                 <div>
                    <label for="currency" class="form-label">Currency</label>
                     <select id="currency" name="currency" class="form-select" required>
                        <?php foreach($currencies as $currency_code): ?>
                            <option value="<?= htmlspecialchars($currency_code) ?>" <?= (isset($edit_income) && $edit_income['currency'] === $currency_code) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($currency_code) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="income_date" class="form-label">Date</label>
                    <input type="date" id="income_date" name="income_date" class="form-input" value="<?= htmlspecialchars($edit_income['income_date'] ?? date('Y-m-d')) ?>" required>
                </div>
                <div class="md:col-span-4 text-right">
                    <button type="submit" class="btn"><?= $edit_income ? 'Update Record' : 'Add Record' ?></button>
                </div>
            </div>
        </form>
    </div>

    <!-- Income List -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-2xl font-semibold mb-4">Income History</h2>
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
                     <?php foreach($incomes as $income): ?>
                    <tr>
                        <td class="table-cell"><?= htmlspecialchars($income['income_date']) ?></td>
                        <td class="table-cell"><?= htmlspecialchars($income['description']) ?></td>
                        <td class="table-cell"><?= htmlspecialchars($income['currency']) ?> <?= number_format($income['amount'], 2) ?></td>
                        <td class="table-cell">
                            <!--<a href="index.php?page=other_income&action=edit&id=<?= $income['id'] ?>" class="text-indigo-600 hover:text-indigo-900">Edit</a>-->
                            <a href="index.php?page=other_income&action=delete&id=<?= $income['id'] ?>" class="text-red-600 hover:text-red-900 ml-4" onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include_template('footer'); ?>
