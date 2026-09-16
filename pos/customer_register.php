<?php
// pos/customer_register.php - Admin/Developer page to register new customers.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authorization ---
if (!is_logged_in() || (!is_admin() && !is_developer())) {
    flash_message('error', 'You are not authorized to access this page.');
    redirect('index.php?page=dashboard');
}

global $connection;

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // --- Server-side Validation ---
    if (empty($username) || empty($phone) || empty($password)) {
        flash_message('error', 'Username, phone, and password are required.');
    } elseif ($password !== $confirm_password) {
        flash_message('error', 'Passwords do not match.');
    } else {
        // Check for existing username or phone
        $stmt_check = mysqli_prepare($connection, "SELECT id FROM users WHERE username = ? OR phone = ?");
        mysqli_stmt_bind_param($stmt_check, 'ss', $username, $phone);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);

        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            flash_message('error', 'A user with that username or phone number already exists.');
        } else {
            // Create the new customer user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $user_type = 'Customer'; // Set the user type specifically to Customer
            
            $stmt_insert = mysqli_prepare($connection, "INSERT INTO users (username, password, user_type, phone) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_insert, 'ssss', $username, $hashed_password, $user_type, $phone);
            
            if (mysqli_stmt_execute($stmt_insert)) {
                flash_message('success', 'Customer account registered successfully!');
                redirect('index.php?page=customer_list');
            } else {
                flash_message('error', 'An error occurred during registration. Please try again.');
            }
        }
        mysqli_stmt_close($stmt_check);
    }
    // Redirect back to the form on validation failure
    redirect('index.php?page=customer_register');
}

include_template('header', ['page' => 'customer_register']);
?>

<div class="relative min-h-[85vh] p-4 sm:p-8 flex items-center justify-center font-sans">
    <div class="w-full max-w-xl v5-glass-card p-8 sm:p-10 shadow-2xl relative z-10 animate-fadeInDown">
        <div class="text-center space-y-2 mb-8">
            <div class="mx-auto w-14 h-14 bg-gradient-to-tr from-blue-600 to-cyan-500 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/25 text-white mb-4">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
            </div>
            <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight" data-i18n="Register Customer">Register Customer</h2>
            <p class="text-sm font-medium text-slate-500" data-i18n="Add a new customer profile to the MBPOS ledger">Add a new customer profile to the MBPOS ledger</p>
        </div>

        <form action="index.php?page=customer_register" method="POST" class="space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="space-y-1.5">
                    <label for="username" class="block text-xs font-bold text-slate-500 uppercase tracking-wider ml-1" data-i18n="Username">Username</label>
                    <input type="text" id="username" name="username" class="w-full rounded-xl border border-slate-200 bg-white/70 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 px-4 py-3 text-sm font-medium text-slate-800 transition-all" placeholder="Customer username" required>
                </div>
                <div class="space-y-1.5">
                    <label for="phone" class="block text-xs font-bold text-slate-500 uppercase tracking-wider ml-1" data-i18n="Phone Number">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="w-full rounded-xl border border-slate-200 bg-white/70 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 px-4 py-3 text-sm font-medium text-slate-800 transition-all" placeholder="+95 9 123 456789" required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="space-y-1.5">
                    <label for="password" class="block text-xs font-bold text-slate-500 uppercase tracking-wider ml-1" data-i18n="Password">Password</label>
                    <input type="password" id="password" name="password" class="w-full rounded-xl border border-slate-200 bg-white/70 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 px-4 py-3 text-sm font-medium text-slate-800 transition-all" placeholder="••••••••" required>
                </div>
                <div class="space-y-1.5">
                    <label for="confirm_password" class="block text-xs font-bold text-slate-500 uppercase tracking-wider ml-1" data-i18n="Confirm Password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="w-full rounded-xl border border-slate-200 bg-white/70 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 px-4 py-3 text-sm font-medium text-slate-800 transition-all" placeholder="••••••••" required>
                </div>
            </div>

            <div class="pt-2 flex items-center justify-between gap-4">
                <a href="index.php?page=customer_list" class="px-5 py-3 rounded-xl border border-slate-200 text-sm font-bold text-slate-600 hover:bg-slate-50 transition-all" data-i18n="Go Back">Go Back</a>
                <button type="submit" class="flex-1 btn-primary py-3 px-6 rounded-xl font-bold text-sm text-white shadow-lg shadow-blue-500/30 hover:opacity-95 transition-all" data-i18n="Register Customer">Register Customer</button>
            </div>
        </form>
    </div>
</div>

<?php include_template('footer'); ?>
