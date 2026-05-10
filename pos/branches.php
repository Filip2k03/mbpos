<?php
// branches.php - Admin page for CRUD operations on branches.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authentication & Authorization ---
if (!is_logged_in() || (!is_admin() && !is_developer())) {
    flash_message('error', 'You are not authorized to access this page.');
    redirect('index.php?page=dashboard');
}

global $connection;
$edit_branch = null; // Variable to hold branch data for editing

// --- Handle DELETE Request ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $branch_id = intval($_GET['id']);
    $stmt = mysqli_prepare($connection, "DELETE FROM branches WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $branch_id);
    if (mysqli_stmt_execute($stmt)) {
        flash_message('success', 'Branch deleted successfully.');
    } else {
        flash_message('error', 'Failed to delete branch: ' . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
    redirect('index.php?page=branches');
}

// --- Handle POST Request (Add or Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_name = trim($_POST['branch_name']);
    $region_id = intval($_POST['region_id']);
    $branch_id = intval($_POST['branch_id'] ?? 0); // For updates

    if (empty($branch_name) || $region_id <= 0) {
        flash_message('error', 'Branch name and region are required.');
    } else {
        if ($branch_id > 0) { // Update existing branch
            $stmt = mysqli_prepare($connection, "UPDATE branches SET branch_name = ?, region_id = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'sii', $branch_name, $region_id, $branch_id);
            if (mysqli_stmt_execute($stmt)) {
                flash_message('success', 'Branch updated successfully.');
            } else {
                flash_message('error', 'Failed to update branch: ' . mysqli_stmt_error($stmt));
            }
        } else { // Add new branch
            $stmt = mysqli_prepare($connection, "INSERT INTO branches (branch_name, region_id) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, 'si', $branch_name, $region_id);
            if (mysqli_stmt_execute($stmt)) {
                flash_message('success', 'Branch added successfully.');
            } else {
                flash_message('error', 'Failed to add branch: ' . mysqli_stmt_error($stmt));
            }
        }
        mysqli_stmt_close($stmt);
        redirect('index.php?page=branches');
    }
}

// --- Handle EDIT Request (Fetch data for the form) ---
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $branch_id = intval($_GET['id']);
    $stmt = mysqli_prepare($connection, "SELECT id, branch_name, region_id FROM branches WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $branch_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $edit_branch = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}


// --- Fetch Data for Display ---
$branches = [];
$result = mysqli_query($connection, "SELECT b.id, b.branch_name, r.region_name FROM branches b JOIN regions r ON b.region_id = r.id ORDER BY r.region_name, b.branch_name");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $branches[] = $row;
    }
}

$regions = [];
$region_result = mysqli_query($connection, "SELECT id, region_name FROM regions ORDER BY region_name");
if ($region_result) {
    while ($row = mysqli_fetch_assoc($region_result)) {
        $regions[] = $row;
    }
}

include_template('header', ['page' => 'branches']);
?>

<div class="container mx-auto p-6">
    <h1 class="text-3xl font-bold mb-6">Manage Branches</h1>

    <!-- Add/Edit Branch Form -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h2 class="text-2xl font-semibold mb-4"><?php echo $edit_branch ? 'Edit Branch' : 'Add New Branch'; ?></h2>
        <form action="index.php?page=branches" method="POST">
            <?php if ($edit_branch): ?>
                <input type="hidden" name="branch_id" value="<?php echo htmlspecialchars($edit_branch['id']); ?>">
            <?php endif; ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="branch_name" class="block text-gray-700 font-semibold mb-2">Branch Name</label>
                    <input type="text" id="branch_name" name="branch_name" class="w-full p-2 border rounded" value="<?php echo htmlspecialchars($edit_branch['branch_name'] ?? ''); ?>" required>
                </div>
                <div>
                    <label for="region_id" class="block text-gray-700 font-semibold mb-2">Region</label>
                    <select id="region_id" name="region_id" class="w-full p-2 border rounded" required>
                        <option value="">Select a Region</option>
                        <?php foreach ($regions as $region): ?>
                            <option value="<?php echo $region['id']; ?>" <?php echo (isset($edit_branch) && $edit_branch['region_id'] == $region['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($region['region_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="self-end">
                    <button type="submit" class="w-full bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700 transition duration-300">
                        <?php echo $edit_branch ? 'Update Branch' : 'Add Branch'; ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Branch List -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-2xl font-semibold mb-4">Existing Branches</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Branch Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Region</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($branches)): ?>
                        <tr>
                            <td colspan="3" class="px-6 py-4 text-center text-gray-500">No branches found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($branches as $branch): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($branch['branch_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($branch['region_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="index.php?page=branches&action=edit&id=<?php echo $branch['id']; ?>" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                    <a href="index.php?page=branches&action=delete&id=<?php echo $branch['id']; ?>" class="text-red-600 hover:text-red-900 ml-4" onclick="return confirm('Are you sure you want to delete this branch?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include_template('footer');
?>
