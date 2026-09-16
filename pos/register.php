<?php
// pos/register.php - Handles new staff and admin registration + Displays User Directory

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!is_logged_in()) {
    redirect('index.php?page=login');
}

// --- Authorization ---
// Only Admins and Developers can register new users or view the directory.
if (!is_logged_in() || (!is_admin() && !is_developer())) {
    flash_message('error', 'You are not authorized to access the User Management hub.');
    redirect('index.php?page=dashboard');
}

global $connection;

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_request();
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
        redirect('index.php?page=register');
    } else {
        flash_message('error', 'Failed to register user.');
        redirect('index.php?page=register');
    }
}

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

// --- Fetch All Users for Directory ---
$users_list = [];
$users_query = "SELECT u.id, u.username, u.user_type, r.region_name, b.branch_name 
                FROM users u 
                LEFT JOIN regions r ON u.region_id = r.id 
                LEFT JOIN branches b ON u.branch_id = b.id 
                ORDER BY u.id DESC";
$users_result = mysqli_query($connection, $users_query);
if ($users_result) {
    while ($row = mysqli_fetch_assoc($users_result)) {
        $users_list[] = $row;
    }
}

include_template('header', ['page' => 'register']);
?>

<div class="v5-page">
    <div class="v5-page-head">
        <div class="v5-page-head__copy">
            <span class="v5-kicker" data-i18n="Administration">Administration</span>
            <h1 data-i18n="User Management">User Management</h1>
            <p data-i18n="Register new personnel and manage access roles">Register new personnel and manage access roles</p>
        </div>
        <span class="v5-count"><?= count($users_list) ?> <span data-i18n="users">users</span></span>
    </div>

    <div class="v5-panel">
        <div class="v5-panel__head">
            <h2 data-i18n="Register Account">Register Account</h2>
        </div>
        <div class="v5-panel__body">
            <form action="index.php?page=register" method="POST" class="v5-filter-grid" accept-charset="UTF-8">
                <?= csrf_input() ?>

                <div class="v5-field v5-field--wide">
                    <label for="username" data-i18n="Username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Username" data-i18n-placeholder="Username" required>
                </div>

                <div class="v5-field v5-field--wide">
                    <label for="user_type" data-i18n="Access Role">Access Role</label>
                    <select id="user_type" name="user_type" required>
                        <option value="" data-i18n="Select Role...">Select Role&hellip;</option>
                        <option value="ADMIN" data-i18n="Administrator">Administrator</option>
                        <option value="Developer" data-i18n="Developer">Developer</option>
                        <option value="Staff" data-i18n="Standard Staff">Standard Staff</option>
                        <option value="General" data-i18n="General (No Region/Branch)">General (No Region/Branch)</option>
                    </select>
                </div>

                <div id="location-fields" class="v5-field--wide" style="display:none;grid-column:span 12">
                    <div class="v5-filter-grid" style="padding:0">
                        <div class="v5-field v5-field--wide">
                            <label for="region_id" data-i18n="Assigned Region">Assigned Region</label>
                            <select id="region_id" name="region_id">
                                <option value="" data-i18n="Select Region First">Select Region First</option>
                                <?php foreach ($regions as $region): ?>
                                    <option value="<?= htmlspecialchars($region['id']) ?>"><?= htmlspecialchars($region['region_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="v5-field v5-field--wide">
                            <label for="branch_id" data-i18n="Assigned Branch">Assigned Branch</label>
                            <select id="branch_id" name="branch_id">
                                <option value="" data-i18n="Select Region First">Select Region First</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="v5-field v5-field--wide">
                    <label for="password" data-i18n="Password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Password" data-i18n-placeholder="Password" required>
                </div>

                <div class="v5-field v5-field--wide">
                    <label for="confirm_password" data-i18n="Confirm Password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" data-i18n-placeholder="Confirm Password" required>
                </div>

                <div class="v5-field">
                    <button type="submit" class="btn w-full" data-i18n="Create Account">Create Account</button>
                </div>
            </form>
        </div>
    </div>

    <section class="v5-panel">
        <div class="v5-panel__head">
            <h2 data-i18n="Active User Directory">Active User Directory</h2>
            <span class="v5-count"><?= count($users_list) ?> <span data-i18n="users">users</span></span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr>
                        <th data-i18n="Username">Username</th>
                        <th data-i18n="Access Role">Access Role</th>
                        <th data-i18n="Assignment (Region/Branch)">Assignment (Region/Branch)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users_list)): ?>
                        <tr>
                            <td colspan="3">
                                <div class="v5-empty">
                                    <span class="v5-empty__icon"><?= mbpos_icon('register', 'w-8 h-8 text-slate-400') ?></span>
                                    <strong data-i18n="No users found.">No users found.</strong>
                                    <p data-i18n="Register the first account using the form above.">Register the first account using the form above.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users_list as $user):
                            // Role Badge Colors
                            $roleClass = match(strtolower($user['user_type'])) {
                                'admin'     => 'v5-badge-danger',
                                'developer' => 'v5-badge-info',
                                'staff'     => 'v5-badge-primary',
                                default     => 'v5-badge-neutral',
                            };
                        ?>
                            <tr>
                                <td>
                                    <div class="v5-toolbar__group">
                                        <span style="display:inline-flex;align-items:center;justify-content:center;width:2rem;height:2rem;border-radius:50%;background:#edf6ff;color:#1675e8;font-weight:900;font-size:.75rem;flex:0 0 auto">
                                            <?= htmlspecialchars(strtoupper(substr($user['username'], 0, 1))) ?>
                                        </span>
                                        <strong><?= htmlspecialchars($user['username']) ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <span class="v5-status-dot <?= $roleClass ?>" style="text-transform:uppercase">
                                        <?= htmlspecialchars(strtoupper($user['user_type'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['user_type'] === 'General' || (empty($user['region_name']) && empty($user['branch_name']))): ?>
                                        <em style="color:var(--v5-muted);font-size:.82rem" data-i18n="Global Access">Global Access</em>
                                    <?php else: ?>
                                        <div>
                                            <strong style="display:block;font-size:.88rem"><?= htmlspecialchars($user['region_name']) ?></strong>
                                            <span style="color:var(--v5-muted);font-size:.78rem"><?= htmlspecialchars($user['branch_name']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
    // Pass the branch data from PHP to JavaScript
    window.branchesData = <?= json_encode($all_branches) ?>;
    
    document.addEventListener('DOMContentLoaded', function() {
        const userTypeSelect = document.getElementById('user_type');
        const locationFields = document.getElementById('location-fields');
        const regionSelect = document.getElementById('region_id');
        const branchSelect = document.getElementById('branch_id');

        userTypeSelect.addEventListener('change', function() {
            // Show location fields for all types except "General" or empty
            if (this.value && this.value !== 'General') {
                locationFields.style.display = '';
                regionSelect.required = true;
                branchSelect.required = true;
            } else {
                locationFields.style.display = 'none';
                regionSelect.required = false;
                branchSelect.required = false;
                // Clear selections
                regionSelect.value = '';
                branchSelect.innerHTML = '<option value="">Select Region First</option>';
            }
        });

        regionSelect.addEventListener('change', function() {
            const regionId = this.value;
            branchSelect.innerHTML = '<option value="">Select a region to see branches</option>'; 
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