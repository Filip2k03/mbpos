<?php
// pos/currencies.php - CRUD management for currencies.
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
<div class="container mx-auto p-6">
    <h1 class="text-3xl font-bold mb-6">Manage Currencies</h1>

    <!-- Add/Edit Form -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h2 class="text-2xl font-semibold mb-4"><?= $edit_currency ? 'Edit Currency' : 'Add New Currency' ?></h2>
        <form action="index.php?page=currencies" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <input type="hidden" name="currency_id" value="<?= $edit_currency['id'] ?? '' ?>">
            <div>
                <label for="code" class="form-label">Code</label>
                <input type="text" id="code" name="code" class="form-input" placeholder="e.g., USD" value="<?= htmlspecialchars($edit_currency['code'] ?? '') ?>" required>
            </div>
             <div>
                <label for="name" class="form-label">Name</label>
                <input type="text" id="name" name="name" class="form-input" placeholder="e.g., US Dollar" value="<?= htmlspecialchars($edit_currency['name'] ?? '') ?>" required>
            </div>
            <button type="submit" class="btn"><?= $edit_currency ? 'Update Currency' : 'Add Currency' ?></button>
        </form>
    </div>

    <!-- Table of existing currencies -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-2xl font-semibold mb-4">Existing Currencies</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="table-header">Code</th>
                        <th class="table-header">Name</th>
                        <th class="table-header">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach($currencies as $currency): ?>
                    <tr>
                        <td class="table-cell font-mono"><?= htmlspecialchars($currency['code']) ?></td>
                        <td class="table-cell"><?= htmlspecialchars($currency['name']) ?></td>
                        <td class="table-cell">
                            <a href="index.php?page=currencies&action=edit&id=<?= $currency['id'] ?>" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                            <a href="index.php?page=currencies&action=delete&id=<?= $currency['id'] ?>" class="text-red-600 hover:text-red-900 ml-4" onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include_template('footer'); ?>

