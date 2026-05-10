<?php
// customer/customer_login.php - Login page for customers, admins, and developers.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (is_customer_logged_in()) {
    redirect('index.php?page=dashboard');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = mysqli_prepare($connection, "SELECT id, password, user_type FROM users WHERE username = ?");
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if ($user && password_verify($password, $user['password'])) {
        // Allow login for Customer, Admin, or Developer roles
        if (in_array($user['user_type'], ['Customer', 'ADMIN', 'Developer'])) {
            $_SESSION['customer_id'] = $user['id'];
            $_SESSION['customer_username'] = $username;
            $_SESSION['customer_user_type'] = $user['user_type'];
            redirect('index.php?page=dashboard');
        } else {
            flash_customer_message('error', 'You are not authorized to access this portal.');
        }
    } else {
        flash_customer_message('error', 'Invalid username or password.');
    }
    redirect('index.php?page=login');
}

include_template('header', ['title' => 'Customer Login']);
?>
<div class="flex items-center justify-center min-h-screen bg-gray-100">
    <div class="w-full max-w-md">
        <form action="index.php?page=login" method="POST" 
              class="bg-white shadow-xl rounded-2xl px-8 pt-8 pb-10">
            
            <!-- Title -->
            <h2 class="text-3xl font-bold text-center text-gray-800 mb-6">
                Customer Portal Login
            </h2>

            <!-- Flash messages -->
            <?php display_customer_flash_messages(); ?>

            <!-- Username -->
            <div class="mb-5">
                <label for="username" class="block text-sm font-semibold text-gray-700 mb-2">Username</label>
                <input id="username" name="username" type="text" placeholder="Enter your username" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <!-- Password -->
            <div class="mb-6 relative">
                <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                <input id="password" name="password" type="password" placeholder="••••••••••" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pr-12">
                <!-- Eye toggle -->
                <button type="button" id="togglePassword"
                        class="absolute right-3 top-9 text-gray-500 hover:text-gray-700">
                    <!-- Default Eye Icon -->
                    <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" 
                         class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 
                                 8.268 2.943 9.542 7-1.274 4.057-5.064 
                                 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                </button>
            </div>
            <div class="mb-4">
                <button type="submit"
                        class="w-full bg-blue-600 text-white py-2 rounded-lg font-semibold shadow-md hover:bg-blue-700 transition">
                    Sign In
                </button>
            </div>
        </form>
    </div>
</div>

