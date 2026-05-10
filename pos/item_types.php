<?php
// pos/item_types.php - CRUD management for item types.
require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) session_start();
if (!is_logged_in() || (!is_admin() && !is_developer())) {
    flash_message('error', 'You are not authorized to access this page.');
    redirect('index.php?page=dashboard');
}

global $connection;

// Handle Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_item_type'])) {
        $name = trim($_POST['name']);
        $stmt = mysqli_prepare($connection, "INSERT INTO item_types (name) VALUES (?)");
        mysqli_stmt_bind_param($stmt, "s", $name);
        mysqli_stmt_execute($stmt);
        flash_message('success', 'Item type added successfully.');
    } elseif (isset($_POST['edit_item_type'])) {
        $id   = intval($_POST['id']);
        $name = trim($_POST['name']);
        $stmt = mysqli_prepare($connection, "UPDATE item_types SET name=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "si", $name, $id);
        mysqli_stmt_execute($stmt);
        flash_message('success', 'Item type updated successfully.');
    } elseif (isset($_POST['delete_item_type'])) {
        $id = intval($_POST['id']);
        $stmt = mysqli_prepare($connection, "DELETE FROM item_types WHERE id=?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        flash_message('success', 'Item type deleted successfully.');
    }
    redirect('item_types.php');
}

// Fetch item types
$result = mysqli_query($connection, "SELECT * FROM item_types ORDER BY name ASC");
$item_types = mysqli_fetch_all($result, MYSQLI_ASSOC);

include_template('header', ['page' => 'item_types']);
?>
<div class="container mx-auto p-6">
    <h1 class="text-3xl font-bold mb-6">Manage Item Types</h1>

    <!-- Add Item Type Form -->
    <form method="POST" class="mb-6 bg-white p-4 shadow rounded">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="text" name="name" placeholder="Item Type Name (e.g. Beverage, Snack)" class="border p-2 rounded" required>
            <button type="submit" name="add_item_type" class="bg-blue-600 text-white px-4 py-2 rounded">Add</button>
        </div>
    </form>

    <!-- Item Types Table -->
    <div class="bg-white shadow rounded">
        <table class="w-full table-auto border-collapse">
            <thead class="bg-gray-100">
                <tr>
                    <th class="border px-4 py-2">Name</th>
                    <th class="border px-4 py-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($item_types as $type): ?>
                <tr>
                    <td class="border px-4 py-2"><?= htmlspecialchars($type['name']) ?></td>
                    <td class="border px-4 py-2 space-x-2">
                        <!-- Edit Form -->
                        <form method="POST" class="inline-block">
                            <input type="hidden" name="id" value="<?= $type['id'] ?>">
                            <input type="text" name="name" value="<?= htmlspecialchars($type['name']) ?>" class="border p-1 rounded w-40">
                            <button type="submit" name="edit_item_type" class="bg-green-500 text-white px-2 py-1 rounded">Save</button>
                        </form>

                        <!-- Delete -->
                        <form method="POST" class="inline-block" onsubmit="return confirm('Delete this item type?');">
                            <input type="hidden" name="id" value="<?= $type['id'] ?>">
                            <button type="submit" name="delete_item_type" class="bg-red-500 text-white px-2 py-1 rounded">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include_template('footer'); ?>
