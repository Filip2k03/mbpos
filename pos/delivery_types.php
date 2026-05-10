<?php
// pos/delivery_types.php - CRUD management for delivery types.
require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) session_start();
if (!is_logged_in() || (!is_admin() && !is_developer())) {
    flash_message('error', 'You are not authorized to access this page.');
    redirect('index.php?page=dashboard');
}

global $connection;
$edit_type = null;

// --- Handle POST Request (Add or Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $type_id = intval($_POST['type_id'] ?? 0);

    if (empty($name)) {
        flash_message('error', 'Delivery type name cannot be empty.');
    } else {
        if ($type_id > 0) { // Update
            $stmt = mysqli_prepare($connection, "UPDATE delivery_types SET name = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $name, $type_id);
            if(mysqli_stmt_execute($stmt)) flash_message('success', 'Delivery type updated successfully.');
        } else { // Add
            $stmt = mysqli_prepare($connection, "INSERT INTO delivery_types (name) VALUES (?)");
            mysqli_stmt_bind_param($stmt, 's', $name);
            if(mysqli_stmt_execute($stmt)) flash_message('success', 'Delivery type added successfully.');
        }
    }
    redirect('index.php?page=delivery_types');
}

// --- Handle GET Request (Delete or Edit) ---
if (isset($_GET['action'])) {
    $id = intval($_GET['id'] ?? 0);
    if ($_GET['action'] === 'delete' && $id > 0) {
        $stmt = mysqli_prepare($connection, "DELETE FROM delivery_types WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        if(mysqli_stmt_execute($stmt)) flash_message('success', 'Delivery type deleted.');
        redirect('index.php?page=delivery_types');
    }
    if ($_GET['action'] === 'edit' && $id > 0) {
        $stmt = mysqli_prepare($connection, "SELECT * FROM delivery_types WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $edit_type = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    }
}

// --- Fetch all delivery types ---
$delivery_types = [];
$result = mysqli_query($connection, "SELECT * FROM delivery_types ORDER BY name ASC");
if($result) while($row = mysqli_fetch_assoc($result)) $delivery_types[] = $row;


include_template('header', ['page' => 'delivery_types']);
?>
<div class="container mx-auto p-6">
    <h1 class="text-3xl font-bold mb-6">Manage Delivery Types</h1>

    <!-- Add/Edit Form -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h2 class="text-2xl font-semibold mb-4"><?= $edit_type ? 'Edit Delivery Type' : 'Add New Delivery Type' ?></h2>
        <form action="index.php?page=delivery_types" method="POST" class="flex items-center gap-4">
            <input type="hidden" name="type_id" value="<?= $edit_type['id'] ?? '' ?>">
            <div class="flex-grow">
                <label for="name" class="form-label sr-only">Type Name</label>
                <input type="text" id="name" name="name" class="form-input" placeholder="e.g., Express, Standard" value="<?= htmlspecialchars($edit_type['name'] ?? '') ?>" required>
            </div>
            <button type="submit" class="btn"><?= $edit_type ? 'Update Type' : 'Add Type' ?></button>
        </form>
    </div>

    <!-- Table of existing types -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-2xl font-semibold mb-4">Existing Delivery Types</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="table-header">Name</th>
                        <th class="table-header">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach($delivery_types as $type): ?>
                    <tr>
                        <td class="table-cell"><?= htmlspecialchars($type['name']) ?></td>
                        <td class="table-cell">
                            <a href="index.php?page=delivery_types&action=edit&id=<?= $type['id'] ?>" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                            <a href="index.php?page=delivery_types&action=delete&id=<?= $type['id'] ?>" class="text-red-600 hover:text-red-900 ml-4" onclick="return confirm('Are you sure?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include_template('footer'); ?>

