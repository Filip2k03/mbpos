<?php
// public_website/cms/login.php - Login page for the CMS.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (is_cms_admin()) {
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
        // Only allow Admin or Developer to log into the CMS
        if (in_array($user['user_type'], ['ADMIN', 'Developer'])) {
            $_SESSION['cms_user_id'] = $user['id'];
            $_SESSION['cms_username'] = $username;
            $_SESSION['cms_user_type'] = $user['user_type'];
            redirect('index.php?page=dashboard');
        } else {
            flash_cms_message('error', 'You are not authorized to access this system.');
        }
    } else {
        flash_cms_message('error', 'Invalid username or password.');
    }
    redirect('index.php?page=login');
}

include_template('header', ['title' => 'CMS Login']);
?>
<div class="flex items-center justify-center min-h-[60vh] bg-gray-100">
    <div class="w-full max-w-md">
        <form action="index.php?page=login" method="POST" 
              class="bg-white shadow-xl rounded-2xl px-8 pt-8 pb-10">
            
            <!-- Title -->
            <h2 class="text-3xl font-bold text-center text-gray-800 mb-8">
                Website CMS Login
            </h2>

            <!-- Username -->
            <div class="mb-5">
                <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                <input id="username" name="username" type="text" placeholder="Enter your username" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <!-- Password -->
            <div class="mb-6 relative">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                <input id="password" name="password" type="password" placeholder="••••••••••" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 pr-12">
                <!-- Toggle Eye Button -->
                <button type="button" id="togglePassword"
                        class="absolute right-3 top-9 text-gray-500 hover:text-gray-700">
                </button>
            </div>

            <!-- Submit Button -->
            <div>
                <button type="submit"
                        class="w-full bg-blue-600 text-white py-2 rounded-lg font-semibold shadow-md hover:bg-blue-700 transition">
                    Sign In
                </button>
            </div>
        </form>
    </div>
</div>

<?php
include_template('footer');
?>
