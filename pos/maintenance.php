<?php
// maintenance.php - Developer page for managing maintenance settings.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authorization: Only Developers can access ---
if (!is_logged_in() || !is_developer()) {
    flash_message('error', 'You are not authorized to access this page.');
    redirect('index.php?page=dashboard');
}

global $connection;
$edit_category = null;

// --- Handle POST Requests (Add/Update/Toggle) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Toggle Status
    if (isset($_POST['toggle_id'])) {
        $id = intval($_POST['toggle_id']);
        $current_status = intval($_POST['current_status']);
        $new_status = $current_status ? 0 : 1;
        $stmt = mysqli_prepare($connection, "UPDATE maintenance SET is_active = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $new_status, $id);
        mysqli_stmt_execute($stmt);
    }
    // Add/Update Category
    else {
        $name = trim($_POST['name']);
        $id = intval($_POST['id'] ?? 0);
        if (!empty($name)) {
            if ($id > 0) { // Update
                $stmt = mysqli_prepare($connection, "UPDATE maintenance SET name = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'si', $name, $id);
            } else { // Insert
                $stmt = mysqli_prepare($connection, "INSERT INTO maintenance (name) VALUES (?)");
                mysqli_stmt_bind_param($stmt, 's', $name);
            }
            mysqli_stmt_execute($stmt);
        }
    }
    redirect('index.php?page=maintenance');
}

// --- Handle GET Requests (Delete/Edit) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = intval($_GET['id'] ?? 0);
    if ($action === 'delete' && $id > 0) {
        $stmt = mysqli_prepare($connection, "DELETE FROM maintenance WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        redirect('index.php?page=maintenance');
    }
    if ($action === 'edit' && $id > 0) {
        $stmt = mysqli_prepare($connection, "SELECT * FROM maintenance WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $edit_category = mysqli_fetch_assoc($result);
    }
}

// --- Fetch all maintenance categories ---
$categories = [];
$result = mysqli_query($connection, "SELECT * FROM maintenance ORDER BY name");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }
}

include_template('header', ['page' => 'maintenance']);
?>

<div class="container mx-auto p-6">
    <h1 class="text-3xl font-bold mb-6">Maintenance Settings</h1>

    <!-- Add/Edit Form -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h2 class="text-2xl font-semibold mb-4"><?= $edit_category ? 'Edit Category' : 'Add New Category' ?></h2>
        <form action="index.php?page=maintenance" method="POST">
            <?php if ($edit_category): ?>
                <input type="hidden" name="id" value="<?= $edit_category['id'] ?>">
            <?php endif; ?>
            <div class="flex items-center space-x-4">
                <input type="text" name="name" placeholder="Category Name" class="form-input flex-grow" value="<?= htmlspecialchars($edit_category['name'] ?? '') ?>" required>
                <button type="submit" class="btn"><?= $edit_category ? 'Update' : 'Add' ?></button>
            </div>
        </form>
    </div>

    <!-- Categories List -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-2xl font-semibold mb-4">Maintenance Categories</h2>
        <div class="space-y-4">
            <?php foreach ($categories as $cat): ?>
                <div class="flex items-center justify-between p-4 border rounded-lg">
                    <span class="font-semibold text-lg"><?= htmlspecialchars($cat['name']) ?></span>
                    <div class="flex items-center space-x-4">
                        <span class="font-bold <?= $cat['is_active'] ? 'text-red-600' : 'text-green-600' ?>">
                            <?= $cat['is_active'] ? 'ACTIVE' : 'INACTIVE' ?>
                        </span>
                        <form action="index.php?page=maintenance" method="POST" class="inline">
                            <input type="hidden" name="toggle_id" value="<?= $cat['id'] ?>">
                            <input type="hidden" name="current_status" value="<?= $cat['is_active'] ?>">
                            <button type="submit" class="text-white font-bold py-2 px-4 rounded <?= $cat['is_active'] ? 'bg-green-500 hover:bg-green-600' : 'bg-red-500 hover:bg-red-600' ?>">
                                Toggle
                            </button>
                        </form>
                        <a href="index.php?page=maintenance&action=edit&id=<?= $cat['id'] ?>" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                        <a href="index.php?page=maintenance&action=delete&id=<?= $cat['id'] ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure?')">Delete</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include_template('footer'); ?>
