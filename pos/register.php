<?php
// pos/register.php - Handles new staff and admin registration.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!is_logged_in()) {
    redirect('index.php?page=login');
}

// --- Authorization ---
// Only Admins and Developers can register new users.
if (!is_logged_in() || (!is_admin() && !is_developer())) {
    flash_message('error', 'You are not authorized to access this page.');
    redirect('index.php?page=dashboard');
}

global $connection;

// --- Fetch Data for Dropdowns ---
$regions = [];
$all_branches = [];

$region_result = mysqli_query($connection, "SELECT id, region_name FROM regions ORDER BY region_name");
if ($region_result) {
    while ($row = mysqli_fetch_assoc($region_result)) {
        $regions[] = $row;
    }
}

$branch_result = mysqli_query($connection, "SELECT id, branch_name, region_id FROM branches ORDER BY branch_name");
if ($branch_result) {
    while ($row = mysqli_fetch_assoc($branch_result)) {
        $all_branches[] = $row;
    }
}

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_type = $_POST['user_type'] ?? '';
    $region_id = !empty($_POST['region_id']) ? intval($_POST['region_id']) : null;
    $branch_id = !empty($_POST['branch_id']) ? intval($_POST['branch_id']) : null;

    // --- Server-side Validation ---
    if (empty($username) || empty($password) || empty($user_type)) {
        flash_message('error', 'Username, password, and user type are required.');
        redirect('index.php?page=register');
    }
    // Location is required for all roles except 'General'
    if ($user_type !== 'General' && (empty($region_id) || empty($branch_id))) {
         flash_message('error', 'Region and Branch are required for Staff, Admin, and Developer roles.');
         redirect('index.php?page=register');
    }
    if ($password !== $confirm_password) {
        flash_message('error', 'Passwords do not match.');
        redirect('index.php?page=register');
    }
    
    // Check for existing user
    $stmt_check = mysqli_prepare($connection, "SELECT id FROM users WHERE username = ?");
    mysqli_stmt_bind_param($stmt_check, 's', $username);
    mysqli_stmt_execute($stmt_check);
    mysqli_stmt_store_result($stmt_check);
    if (mysqli_stmt_num_rows($stmt_check) > 0) {
        flash_message('error', 'Username already exists.');
        redirect('index.php?page=register');
    }
    mysqli_stmt_close($stmt_check);

    // --- Database Insertion ---
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt_insert = mysqli_prepare($connection, "INSERT INTO users (username, password, user_type, region_id, branch_id) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt_insert, 'sssii', $username, $hashed_password, $user_type, $region_id, $branch_id);

    if (mysqli_stmt_execute($stmt_insert)) {
        flash_message('success', 'User has been registered successfully!');
        redirect('index.php?page=admin_dashboard');
    } else {
        flash_message('error', 'Failed to register user.');
        redirect('index.php?page=register');
    }
}

include_template('header', ['page' => 'register']);
?>

<div class="flex items-center justify-center min-h-screen -mt-20">
    <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-lg border border-gray-100">
        <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Register New User</h2>
        <form action="index.php?page=register" method="POST" class="space-y-6">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="username" class="form-label">Username</label>
                    <input type="text" id="username" name="username" class="form-input" required>
                </div>
                <div>
                    <label for="user_type" class="form-label">User Type</label>
                    <select id="user_type" name="user_type" class="form-select" required>
                        <option value="">Select User Type</option>
                        <option value="ADMIN">ADMIN</option>
                        <option value="Developer">Developer</option>
                        <option value="Staff">Staff</option>
                        <option value="General">General (No Region/Branch)</option>
                    </select>
                </div>
            </div>

            <div id="location-fields" class="hidden grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="region_id" class="form-label">Region</label>
                    <select id="region_id" name="region_id" class="form-select">
                        <option value="">Select Region First</option>
                        <?php foreach ($regions as $region): ?>
                            <option value="<?= htmlspecialchars($region['id']) ?>"><?= htmlspecialchars($region['region_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="branch_id" class="form-label">Branch</label>
                    <select id="branch_id" name="branch_id" class="form-select">
                        <option value="">Select a region to see branches</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                 <div>
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-input" required>
                </div>
                <div>
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input" required>
                </div>
            </div>

            <div class="pt-4">
                <button type="submit" class="btn w-full">Register User</button>
            </div>
        </form>
    </div>
</div>

<script>
    // This passes the branch data from PHP to our global JavaScript file.
    window.branchesData = <?= json_encode($all_branches) ?>;
    
    document.addEventListener('DOMContentLoaded', function() {
        const userTypeSelect = document.getElementById('user_type');
        const locationFields = document.getElementById('location-fields');
        const regionSelect = document.getElementById('region_id');
        const branchSelect = document.getElementById('branch_id');

        userTypeSelect.addEventListener('change', function() {
            // Show location fields for all types except "General" or empty
            if (this.value && this.value !== 'General') {
                locationFields.style.display = 'grid';
                regionSelect.required = true;
                branchSelect.required = true;
            } else {
                locationFields.style.display = 'none';
                regionSelect.required = false;
                branchSelect.required = false;
            }
        });

        regionSelect.addEventListener('change', function() {
            const regionId = this.value;
            branchSelect.innerHTML = '<option value="">Select a region to see branches</option>'; // Clear
            if (regionId) {
                const filteredBranches = window.branchesData.filter(b => b.region_id == regionId);
                filteredBranches.forEach(branch => {
                    const option = new Option(branch.branch_name, branch.id);
                    branchSelect.add(option);
                });
            }
        });
    });
</script>

<?php include_template('footer'); ?>