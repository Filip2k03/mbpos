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

        // --- V3 Email Notification Logic ---
        // Trigger email only if the user is NOT a developer
        if (strtolower($user['user_type']) !== 'developer') {
            $to = 'stephanfilip7@gmail.com, raincloud.157@gmail.com';
            $subject = 'MBPOS V3 Alert: Staff Login Detected';
            
            // Clean HTML Email Template
            $message = "
            <html>
            <head>
                <title>System Access Notification</title>
                <style>
                    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f3f4f6; color: #1f2937; padding: 20px; }
                    .container { background-color: #ffffff; padding: 25px; border-radius: 8px; border-top: 4px solid #2563eb; box-shadow: 0 4px 6px rgba(0,0,0,0.05); max-width: 600px; margin: 0 auto; }
                    h2 { color: #1e40af; margin-top: 0; }
                    ul { list-style-type: none; padding: 0; }
                    li { margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb; }
                    li:last-child { border-bottom: none; }
                    strong { color: #4b5563; display: inline-block; width: 120px; }
                    .footer { margin-top: 20px; font-size: 12px; color: #6b7280; text-align: center; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h2>System Access Notification</h2>
                    <p>A staff member has successfully authenticated into the MBPOS system.</p>
                    <ul>
                        <li><strong>Username:</strong> " . htmlspecialchars($user['username']) . "</li>
                        <li><strong>Access Role:</strong> " . strtoupper(htmlspecialchars($user['user_type'])) . "</li>
                        <li><strong>Branch:</strong> " . htmlspecialchars($user['branch_name'] ?? 'N/A') . "</li>
                        <li><strong>Timestamp:</strong> " . date('Y-m-d H:i:s T') . "</li>
                        <li><strong>IP Address:</strong> " . htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "</li>
                    </ul>
                    <div class='footer'>This is an automated security message from your MBPOS System.</div>
                </div>
            </body>
            </html>
            ";

            // Headers required for sending HTML emails
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            // UPDATED: Using production domain to prevent Gmail silent rejections
            $headers .= "From: security@mbpos.online" . "\r\n"; 

            // Dispatch Email and log if the server rejects it
            $mailSent = mail($to, $subject, $message, $headers);
            if (!$mailSent) {
                error_log("MBPOS Error: Failed to send login alert email to $to. Check server MTA configuration.");
            }
        }
        // -----------------------------------

        redirect('index.php?page=dashboard');
    } else {
        // Invalid credentials
        flash_message('error', 'Invalid username or password.');
        redirect('index.php?page=login');
    }
}

include_template('header', ['page' => 'login']);
?>

<!-- V3 Polished UI Wrapper -->
<div class="min-h-[80vh] flex items-center justify-center px-4 py-12">
    <form action="index.php?page=login" method="POST" id="loginForm" 
          class="w-full max-w-md bg-white/95 backdrop-blur-sm shadow-2xl rounded-2xl p-8 space-y-6 border border-gray-100 transition-all hover:shadow-blue-500/10">
        
        <!-- Title -->
        <div class="text-center space-y-2 mb-8">
            <h2 class="text-3xl font-extrabold text-gray-900 tracking-tight">Welcome Back</h2>
            <p class="text-sm text-gray-500">Sign in to your MBPOS account</p>
        </div>
        
        <!-- Username -->
        <div class="space-y-1">
            <label for="username" class="block text-sm font-semibold text-gray-700">Username</label>
            <input type="text" id="username" name="username" required autocomplete="username"
                   class="w-full rounded-xl border border-gray-300 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 px-4 py-3 bg-gray-50 transition-colors hover:bg-white text-gray-900 placeholder-gray-400"
                   placeholder="Enter your username">
        </div>
        
        <!-- Password -->
        <div class="space-y-1 relative">
            <label for="password" class="block text-sm font-semibold text-gray-700">Password</label>
            <div class="relative">
                <input type="password" id="password" name="password" required autocomplete="current-password"
                       class="w-full rounded-xl border border-gray-300 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 px-4 py-3 bg-gray-50 transition-colors hover:bg-white text-gray-900 placeholder-gray-400 pr-16"
                       placeholder="••••••••">
                <button type="button" id="togglePassword"
                        class="absolute inset-y-0 right-1 my-1 px-3 flex items-center rounded-lg text-gray-500 hover:text-blue-600 hover:bg-blue-50 font-medium text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-blue-200">
                    Show
                </button>
            </div>
        </div>
        
        <!-- Remember Me -->
        <div class="flex items-center justify-between pt-2">
            <div class="flex items-center space-x-2">
                <input type="checkbox" id="remember" name="remember"
                       class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 transition-all cursor-pointer">
                <label for="remember" class="text-sm font-medium text-gray-600 cursor-pointer select-none">Remember Me</label>
            </div>
            <!-- Optional: Forgot password link placeholder for future V3 updates -->
            <!-- <a href="#" class="text-sm font-medium text-blue-600 hover:text-blue-500 transition-colors">Forgot password?</a> -->
        </div>
        
        <!-- Submit Button -->
        <div class="pt-2">
            <button type="submit"
                    class="w-full bg-blue-600 text-white py-3 px-4 rounded-xl font-bold hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-500/50 shadow-lg shadow-blue-500/30 transition-all transform hover:-translate-y-0.5 active:translate-y-0">
                Sign In
            </button>
        </div>
    </form>
</div>

<script>
    // Toggle Password Visibility Logic
    document.addEventListener('DOMContentLoaded', () => {
        const togglePassword = document.getElementById("togglePassword");
        const passwordInput = document.getElementById("password");

        togglePassword.addEventListener("click", () => {
            const isPassword = passwordInput.getAttribute("type") === "password";
            passwordInput.setAttribute("type", isPassword ? "text" : "password");
            togglePassword.textContent = isPassword ? "Hide" : "Show";
        });
    });
</script>

<?php
include_template('footer');
?>