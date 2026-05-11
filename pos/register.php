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
        // V3 Update: Redirect back to register page to see the updated list immediately
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

<!-- V3 Liquid UI Wrapper -->
<div class="relative min-h-[85vh] bg-gray-50/30 p-4 sm:p-8 overflow-hidden font-sans">
    
    <!-- Ambient Background Glows -->
    <div class="absolute top-[10%] left-[-10%] w-[500px] h-[500px] bg-blue-400/10 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-[500px] h-[500px] bg-indigo-400/10 rounded-full blur-[120px] pointer-events-none"></div>

    <div class="max-w-7xl mx-auto relative z-10">
        
        <!-- Header -->
        <div class="flex items-center gap-4 mb-8">
            <div class="w-14 h-14 bg-gradient-to-br from-indigo-600 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg shadow-purple-500/30 text-white transform -rotate-3 hover:rotate-0 transition-transform duration-300">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
            <div>
                <h1 class="text-3xl font-extrabold bg-gradient-to-r from-gray-900 to-gray-600 bg-clip-text text-transparent tracking-tight">User Management</h1>
                <p class="text-sm font-medium text-gray-500">Register new personnel and manage access roles</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- LEFT COLUMN: REGISTRATION FORM -->
            <div class="lg:col-span-4">
                <div class="bg-white/80 backdrop-blur-2xl p-6 sm:p-8 rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-white/60 sticky top-8">
                    <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        Register Account
                    </h2>
                    
                    <form action="index.php?page=register" method="POST" class="space-y-5">
                        
                        <div class="space-y-1.5">
                            <label for="username" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">Username</label>
                            <input type="text" id="username" name="username" class="w-full rounded-2xl border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all py-3 px-4 font-medium text-gray-800 shadow-sm" placeholder="e.g. jdoe_staff" required>
                        </div>
                        
                        <div class="space-y-1.5">
                            <label for="user_type" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">Access Role</label>
                            <select id="user_type" name="user_type" class="w-full rounded-2xl border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all py-3 px-4 font-bold text-gray-700 shadow-sm appearance-none" required>
                                <option value="">Select Role...</option>
                                <option value="ADMIN">Administrator</option>
                                <option value="Developer">Developer</option>
                                <option value="Staff">Standard Staff</option>
                                <option value="General">General (No Region/Branch)</option>
                            </select>
                        </div>

                        <div id="location-fields" class="space-y-5 hidden bg-indigo-50/50 p-4 rounded-2xl border border-indigo-100/50">
                            <div class="space-y-1.5">
                                <label for="region_id" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">Assigned Region</label>
                                <select id="region_id" name="region_id" class="w-full rounded-xl border-gray-200 bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all py-2.5 px-4 font-medium text-gray-700 shadow-sm appearance-none">
                                    <option value="">Select Region First</option>
                                    <?php foreach ($regions as $region): ?>
                                        <option value="<?= htmlspecialchars($region['id']) ?>"><?= htmlspecialchars($region['region_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="space-y-1.5">
                                <label for="branch_id" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">Assigned Branch</label>
                                <select id="branch_id" name="branch_id" class="w-full rounded-xl border-gray-200 bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all py-2.5 px-4 font-medium text-gray-700 shadow-sm appearance-none">
                                    <option value="">Select Region First</option>
                                </select>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label for="password" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">Password</label>
                            <input type="password" id="password" name="password" class="w-full rounded-2xl border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all py-3 px-4 font-medium text-gray-800 shadow-sm" placeholder="••••••••" required>
                        </div>
                        
                        <div class="space-y-1.5">
                            <label for="confirm_password" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="w-full rounded-2xl border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all py-3 px-4 font-medium text-gray-800 shadow-sm" placeholder="••••••••" required>
                        </div>

                        <div class="pt-4">
                            <button type="submit" class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white py-3.5 px-4 rounded-2xl font-bold text-md hover:from-indigo-700 hover:to-purple-700 focus:outline-none focus:ring-4 focus:ring-indigo-500/30 shadow-[0_8px_20px_rgb(99,102,241,0.3)] transition-all transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Create Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- RIGHT COLUMN: USER DIRECTORY TABLE -->
            <div class="lg:col-span-8">
                <div class="bg-white/70 backdrop-blur-2xl rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-white/60 overflow-hidden h-full flex flex-col">
                    
                    <div class="p-6 sm:p-8 border-b border-gray-100 flex items-center justify-between bg-white/50">
                        <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                            <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            Active User Directory
                        </h2>
                        <span class="bg-purple-100 text-purple-700 py-1 px-3 rounded-full text-xs font-bold border border-purple-200">
                            <?= count($users_list) ?> Users
                        </span>
                    </div>

                    <div class="overflow-x-auto w-full custom-scrollbar flex-1">
                        <table class="w-full text-left border-collapse whitespace-nowrap">
                            <thead>
                                <tr class="bg-gray-50/50 border-b border-gray-100">
                                    <th class="py-4 px-6 text-xs font-extrabold text-gray-400 uppercase tracking-widest">Username</th>
                                    <th class="py-4 px-6 text-xs font-extrabold text-gray-400 uppercase tracking-widest">Access Role</th>
                                    <th class="py-4 px-6 text-xs font-extrabold text-gray-400 uppercase tracking-widest">Assignment (Region/Branch)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100/60">
                                <?php if (empty($users_list)): ?>
                                    <tr>
                                        <td colspan="3" class="py-12 text-center text-gray-400 font-medium">No users found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users_list as $user): 
                                        // Role Badge Colors
                                        $roleClass = match(strtolower($user['user_type'])) {
                                            'admin' => 'bg-rose-100 text-rose-700 border-rose-200',
                                            'developer' => 'bg-purple-100 text-purple-700 border-purple-200',
                                            'staff' => 'bg-blue-100 text-blue-700 border-blue-200',
                                            default => 'bg-gray-100 text-gray-600 border-gray-200',
                                        };
                                    ?>
                                        <tr class="hover:bg-white/80 transition-colors duration-200">
                                            <td class="py-4 px-6">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-gray-200 to-gray-100 flex items-center justify-center text-gray-500 font-bold text-xs border border-gray-300 shadow-sm">
                                                        <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                                    </div>
                                                    <span class="font-bold text-gray-800"><?= htmlspecialchars($user['username']) ?></span>
                                                </div>
                                            </td>
                                            <td class="py-4 px-6">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold border <?= $roleClass ?>">
                                                    <?= htmlspecialchars(strtoupper($user['user_type'])) ?>
                                                </span>
                                            </td>
                                            <td class="py-4 px-6">
                                                <?php if ($user['user_type'] === 'General' || (empty($user['region_name']) && empty($user['branch_name']))): ?>
                                                    <span class="text-sm font-medium text-gray-400 italic">Global Access</span>
                                                <?php else: ?>
                                                    <div class="flex flex-col">
                                                        <span class="text-sm font-bold text-gray-700"><?= htmlspecialchars($user['region_name']) ?></span>
                                                        <span class="text-xs text-gray-500"><?= htmlspecialchars($user['branch_name']) ?></span>
                                                    </div>
                                                <?php endif; ?>
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
                locationFields.classList.remove('hidden');
                regionSelect.required = true;
                branchSelect.required = true;
            } else {
                locationFields.classList.add('hidden');
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