<?php
// pos/login.php - Handles user authentication.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// If user is already logged in, redirect to their dashboard
if (is_logged_in()) {
    redirect('index.php?page=dashboard');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        flash_message('error', 'Username and password are required.');
        redirect('index.php?page=login');
    }

    // Fetch user and their branch name from the database
    $stmt = mysqli_prepare($connection, "SELECT u.id, u.username, u.password, u.user_type, u.branch_id, b.branch_name 
                                        FROM users u 
                                        LEFT JOIN branches b ON u.branch_id = b.id
                                        WHERE u.username = ?");
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($user && password_verify($password, $user['password'])) {
        // Password is correct, set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['branch_id'] = $user['branch_id']; 
        $_SESSION['branch_name'] = $user['branch_name']; // Store branch name

        redirect('index.php?page=dashboard');
    } else {
        // Invalid credentials
        flash_message('error', 'Invalid username or password.');
        redirect('index.php?page=login');
    }
}

include_template('header', ['page' => 'login']);
?>

<form action="index.php?page=login" method="POST" id="loginForm" 
      class="max-w-md mx-auto bg-white shadow-lg rounded-2xl p-8 space-y-6">
    
    <!-- Title -->
    <h2 class="text-2xl font-bold text-center text-gray-800">Login</h2>
    
    <!-- Username -->
    <div>
        <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
        <input type="text" id="username" name="username" required
               class="w-full rounded-xl border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-4 py-2">
    </div>
    
    <!-- Password -->
    <div class="relative">
        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
        <input type="password" id="password" name="password" required
               class="w-full rounded-xl border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-4 py-2 pr-12">
        <button type="button" id="togglePassword"
                class="absolute inset-y-0 right-3 flex items-center text-gray-500 hover:text-gray-700 text-sm">
            Show
        </button>
    </div>
    
    <!-- Remember Me -->
    <div class="flex items-center space-x-2">
        <input type="checkbox" id="remember" name="remember"
               class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
        <label for="remember" class="text-sm text-gray-600">Remember Me</label>
    </div>
    
    <!-- Submit Button -->
    <div>
        <button type="submit"
                class="w-full bg-blue-600 text-white py-2 rounded-xl font-semibold hover:bg-blue-700 shadow-md transition">
            Login
        </button>
    </div>
</form>

<script>
    // Toggle Password Visibility
    const togglePassword = document.getElementById("togglePassword");
    const password = document.getElementById("password");

    togglePassword.addEventListener("click", () => {
        const type = password.getAttribute("type") === "password" ? "text" : "password";
        password.setAttribute("type", type);
        togglePassword.textContent = type === "password" ? "Show" : "Hide";
    });
</script>


<?php
include_template('footer');
?>