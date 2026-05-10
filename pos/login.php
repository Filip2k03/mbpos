<?php
// pos/login.php - Handles user authentication.

// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

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

        // --- V3 Email Notification Logic (PHPMailer via aaPanel SMTP) ---
        // Trigger email only if the user is NOT a developer
        if (strcasecmp($user['user_type'], USER_TYPE_DEVELOPER) !== 0) {
            
            // Failsafe: Only attempt to send if Composer has been run
            if (file_exists('vendor/autoload.php')) {
                require_once 'vendor/autoload.php';

                $mail = new PHPMailer(true);

                try {
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host       = 'mail.mbpos.online'; // aaPanel default mail host
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'noreplay@mbpos.online';
                    $mail->Password   = 'mbposV32026'; // Provided SMTP password
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Enable implicit TLS/SSL encryption
                    $mail->Port       = 465; // SSL port

                    // Recipients
                    $mail->setFrom('noreplay@mbpos.online', 'MBPOS Security');
                    $mail->addAddress('stephanfilip7@gmail.com'); 
                    $mail->addAddress('raincloud.157@gmail.com'); 

                    // Clean HTML Email Template
                    $message = "
                    <html>
                    <head>
                        <title>System Access Notification</title>
                        <style>
                            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f3f4f6; color: #1f2937; padding: 20px; }
                            .container { background-color: #ffffff; padding: 25px; border-radius: 8px; border-top: 4px solid #4f46e5; box-shadow: 0 4px 6px rgba(0,0,0,0.05); max-width: 600px; margin: 0 auto; }
                            h2 { color: #3730a3; margin-top: 0; }
                            ul { list-style-type: none; padding: 0; }
                            li { margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb; }
                            li:last-child { border-bottom: none; }
                            strong { color: #4b5563; display: inline-block; width: 120px; }
                            .footer { margin-top: 20px; font-size: 12px; color: #6b7280; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 15px;}
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

                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = 'MBPOS Alert: Staff Login Detected';
                    $mail->Body    = $message;

                    $mail->send();
                } catch (Exception $e) {
                    // Log error silently so user login isn't interrupted
                    error_log("MBPOS Mailer Error: {$mail->ErrorInfo}");
                }
            } else {
                error_log("MBPOS Security Alert Skipped: PHPMailer vendor/autoload.php is missing. Run composer require phpmailer/phpmailer.");
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

<!-- V3 Premium UI Wrapper -->
<div class="min-h-[85vh] flex items-center justify-center px-4 py-12 bg-gray-50/50 relative overflow-hidden">
    
    <!-- Subtle Background Glow Elements -->
    <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-blue-400/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-96 h-96 bg-indigo-400/20 rounded-full blur-3xl pointer-events-none"></div>

    <form action="index.php?page=login" method="POST" id="loginForm" 
          class="w-full max-w-md bg-white/80 backdrop-blur-xl shadow-[0_8px_30px_rgb(0,0,0,0.08)] rounded-3xl p-8 space-y-7 border border-white/40 transition-all relative z-10">
        
        <!-- Title & Icon -->
        <div class="text-center space-y-3 mb-8">
            <div class="mx-auto w-16 h-16 bg-gradient-to-tr from-blue-600 to-indigo-600 rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-500/30 mb-4 transform -rotate-3 hover:rotate-0 transition-transform duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4" />
                </svg>
            </div>
            <h2 class="text-3xl font-extrabold text-gray-900 tracking-tight">MBPOS Portal</h2>
            <p class="text-sm font-medium text-gray-500">Secure access to your dashboard</p>
        </div>
        
        <!-- Username -->
        <div class="space-y-1.5 group">
            <label for="username" class="block text-sm font-bold text-gray-700 ml-1">Username</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400 group-focus-within:text-blue-600 transition-colors" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                    </svg>
                </div>
                <input type="text" id="username" name="username" required autocomplete="username"
                       class="w-full rounded-2xl border-gray-200 shadow-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 pl-11 pr-4 py-3.5 bg-gray-50/50 transition-all hover:bg-gray-50 text-gray-900 placeholder-gray-400 font-medium"
                       placeholder="Enter your username">
            </div>
        </div>
        
        <!-- Password -->
        <div class="space-y-1.5 group relative">
            <label for="password" class="block text-sm font-bold text-gray-700 ml-1">Password</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400 group-focus-within:text-indigo-600 transition-colors" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                    </svg>
                </div>
                <input type="password" id="password" name="password" required autocomplete="current-password"
                       class="w-full rounded-2xl border-gray-200 shadow-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 pl-11 pr-16 py-3.5 bg-gray-50/50 transition-all hover:bg-gray-50 text-gray-900 placeholder-gray-400 font-medium tracking-wide"
                       placeholder="••••••••">
                <button type="button" id="togglePassword"
                        class="absolute inset-y-0 right-1.5 my-1.5 px-3 flex items-center rounded-xl text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 font-bold text-xs uppercase tracking-wider transition-all focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    Show
                </button>
            </div>
        </div>
        
        <!-- Remember Me -->
        <div class="flex items-center justify-between pt-1">
            <label class="flex items-center space-x-3 cursor-pointer group">
                <div class="relative flex items-center justify-center">
                    <input type="checkbox" id="remember" name="remember"
                           class="peer h-5 w-5 text-indigo-600 border-gray-300 rounded-md focus:ring-indigo-500 focus:ring-offset-0 transition-all cursor-pointer shadow-sm">
                </div>
                <span class="text-sm font-semibold text-gray-600 group-hover:text-gray-900 transition-colors select-none">Keep me signed in</span>
            </label>
        </div>
        
        <!-- Submit Button -->
        <div class="pt-3">
            <button type="submit"
                    class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white py-3.5 px-4 rounded-2xl font-bold text-lg hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-500/30 shadow-[0_8px_20px_rgb(79,70,229,0.3)] transition-all transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2">
                <span>Sign In Securely</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd" />
                </svg>
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
            togglePassword.textContent = isPassword ? "HIDE" : "SHOW";
            
            if (isPassword) {
                passwordInput.classList.remove('tracking-wide');
            } else {
                passwordInput.classList.add('tracking-wide');
            }
        });
    });
</script>

<?php
include_template('footer');
?>