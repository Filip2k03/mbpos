<?php
// pos/login.php - Handles user authentication (Premium V3 UI & Email).

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

        // --- V3 Email Notification Logic ---
        // Trigger email only if the user is NOT a developer
        if (strcasecmp($user['user_type'], USER_TYPE_DEVELOPER) !== 0) {
            
            $to1 = 'stephanfilip7@gmail.com';
            $to2 = 'raincloud.157@gmail.com';
            $to3 = 'zw50673@gmail.com'; // Added new recipient
            $subject = 'MBPOS Alert: System Access Detected';
            
            // V3 Premium Liquid Glass HTML Email Template (Using Inline CSS for email clients)
            $timestamp = date('F j, Y - H:i:s T');
            $ip_address = htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'Unknown');
            $safe_username = htmlspecialchars($user['username']);
            $safe_role = strtoupper(htmlspecialchars($user['user_type']));
            $safe_branch = htmlspecialchars($user['branch_name'] ?? 'Global / N/A');

            $message = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <title>System Access Notification</title>
            </head>
            <body style='margin: 0; padding: 40px 20px; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; background: #0f172a; background-image: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); color: #f8fafc; -webkit-font-smoothing: antialiased;'>
                
                <table width='100%' cellpadding='0' cellspacing='0' border='0' style='max-width: 600px; margin: 0 auto;'>
                    <tr>
                        <td align='center' style='padding-bottom: 30px;'>
                            <div style='background: linear-gradient(135deg, #4f46e5, #7c3aed); padding: 12px 24px; border-radius: 12px; display: inline-block; font-weight: 900; letter-spacing: 2px; box-shadow: 0 10px 25px rgba(79,70,229,0.4);'>
                                MBLOGISTICS
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style='background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 24px; padding: 40px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);'>
                            
                            <h2 style='margin-top: 0; margin-bottom: 20px; font-size: 24px; font-weight: 800; color: #ffffff; text-align: center;'>Security Alert</h2>
                            <p style='color: #94a3b8; font-size: 15px; line-height: 1.6; margin-bottom: 30px; text-align: center;'>
                                A staff member has successfully authenticated into the MBPOS secure system.
                            </p>
                            
                            <!-- Glass Data Card -->
                            <div style='background-color: rgba(0, 0, 0, 0.2); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 16px; padding: 20px; margin-bottom: 30px;'>
                                <table width='100%' cellpadding='12' cellspacing='0' border='0' style='font-size: 14px;'>
                                    <tr>
                                        <td width='35%' style='color: #64748b; font-weight: 700; text-transform: uppercase; font-size: 11px; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,0.05);'>Username</td>
                                        <td style='color: #ffffff; font-weight: 600; border-bottom: 1px solid rgba(255,255,255,0.05);'>{$safe_username}</td>
                                    </tr>
                                    <tr>
                                        <td style='color: #64748b; font-weight: 700; text-transform: uppercase; font-size: 11px; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,0.05);'>Access Role</td>
                                        <td style='border-bottom: 1px solid rgba(255,255,255,0.05);'><span style='background-color: rgba(79, 70, 229, 0.2); color: #818cf8; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 12px;'>{$safe_role}</span></td>
                                    </tr>
                                    <tr>
                                        <td style='color: #64748b; font-weight: 700; text-transform: uppercase; font-size: 11px; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,0.05);'>Assigned Node</td>
                                        <td style='color: #e2e8f0; font-weight: 500; border-bottom: 1px solid rgba(255,255,255,0.05);'>{$safe_branch}</td>
                                    </tr>
                                    <tr>
                                        <td style='color: #64748b; font-weight: 700; text-transform: uppercase; font-size: 11px; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,0.05);'>IP Address</td>
                                        <td style='color: #e2e8f0; font-family: monospace; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.05);'>{$ip_address}</td>
                                    </tr>
                                    <tr>
                                        <td style='color: #64748b; font-weight: 700; text-transform: uppercase; font-size: 11px; letter-spacing: 1px;'>Timestamp</td>
                                        <td style='color: #94a3b8; font-size: 13px;'>{$timestamp}</td>
                                    </tr>
                                </table>
                            </div>
                            
                            <p style='color: #64748b; font-size: 12px; text-align: center; margin: 0; line-height: 1.5;'>
                                This is an automated security message generated by your MBPOS System architecture.<br>
                                Powered by <a href='https://techyyfilip.vercel.app' style='color: #818cf8; text-decoration: none; font-weight: 600;'>TechyyFilip</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
            ";

            $emailSent = false;

            // Strategy 1: PHPMailer via aaPanel SMTP (Confirmed Active)
            if (file_exists('vendor/autoload.php')) {
                require_once 'vendor/autoload.php';
                $mail = new PHPMailer(true);

                try {
                    $mail->isSMTP();
                    $mail->Host       = 'mail.mbpos.online'; 
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'noreplay@mbpos.online'; 
                    $mail->Password   = 'mbposV32026'; 
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
                    $mail->Port       = 465; 

                    // Options to bypass self-signed certificate issues 
                    $mail->SMTPOptions = array(
                        'ssl' => array(
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        )
                    );

                    $mail->setFrom('noreplay@mbpos.online', 'MBPOS Security');
                    $mail->addAddress($to1); 
                    $mail->addAddress($to2); 
                    $mail->addAddress($to3); // Inserted requested email here

                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body    = $message;

                    $mail->send();
                    $emailSent = true;
                } catch (Exception $e) {
                    error_log("MBPOS PHPMailer Error (SMTP 465): {$mail->ErrorInfo}");
                }
            } else {
                 error_log("MBPOS Alert: PHPMailer is NOT installed. vendor/autoload.php is missing. Please run 'composer require phpmailer/phpmailer'.");
            }

            // Strategy 2: Fallback to native PHP mail() if Composer isn't ready
            if (!$emailSent) {
                $headers  = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
                $headers .= "From: MBPOS Security <noreplay@mbpos.online>" . "\r\n"; 
                $headers .= "Reply-To: noreplay@mbpos.online" . "\r\n";
                $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

                mail("$to1, $to2, $to3", $subject, $message, $headers);
                error_log("MBPOS Alert: PHPMailer failed or is missing. Defaulted to native PHP mail() function.");
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

<!-- V3 Liquid UI Wrapper -->
<div class="min-h-[85vh] flex items-center justify-center px-4 py-12 bg-slate-50/50 relative overflow-hidden font-sans">
    
    <!-- Ambient Background Glows -->
    <div class="absolute top-[0%] left-[-10%] w-[600px] h-[600px] bg-indigo-500/10 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-[0%] right-[-10%] w-[600px] h-[600px] bg-cyan-500/10 rounded-full blur-[120px] pointer-events-none"></div>

    <form action="index.php?page=login" method="POST" id="loginForm" 
          class="w-full max-w-md bg-white/70 backdrop-blur-2xl shadow-[0_8px_40px_rgb(0,0,0,0.06)] rounded-[2.5rem] p-8 sm:p-10 border border-white/80 transition-all relative z-10 animate-fadeInDown">
        
        <!-- Title & Icon -->
        <div class="text-center space-y-3 mb-10">
            <div class="mx-auto w-16 h-16 bg-gradient-to-tr from-indigo-600 to-blue-600 rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-500/30 mb-5 transform -rotate-3 hover:rotate-0 transition-transform duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4" />
                </svg>
            </div>
            <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight">MBPOS Portal</h2>
            <p class="text-sm font-medium text-slate-500">Secure access to your operational dashboard</p>
        </div>
        
        <!-- Username -->
        <div class="space-y-1.5 group mb-6">
            <label for="username" class="block text-xs font-bold text-slate-500 uppercase tracking-wider ml-1">System Identity</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-slate-400 group-focus-within:text-indigo-600 transition-colors" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                    </svg>
                </div>
                <input type="text" id="username" name="username" required autocomplete="username"
                       class="w-full rounded-2xl border-slate-200 shadow-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 pl-11 pr-4 py-3.5 bg-slate-50/50 transition-all focus:bg-white text-slate-800 placeholder-slate-400 font-medium"
                       placeholder="Enter your username">
            </div>
        </div>
        
        <!-- Password -->
        <div class="space-y-1.5 group relative mb-6">
            <label for="password" class="block text-xs font-bold text-slate-500 uppercase tracking-wider ml-1">Access Key</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-slate-400 group-focus-within:text-indigo-600 transition-colors" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                    </svg>
                </div>
                <input type="password" id="password" name="password" required autocomplete="current-password"
                       class="w-full rounded-2xl border-slate-200 shadow-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 pl-11 pr-16 py-3.5 bg-slate-50/50 transition-all focus:bg-white text-slate-800 placeholder-slate-400 font-medium tracking-wide"
                       placeholder="••••••••">
                <button type="button" id="togglePassword"
                        class="absolute inset-y-0 right-1.5 my-1.5 px-3 flex items-center rounded-xl text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 font-bold text-[10px] uppercase tracking-widest transition-all focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    Reveal
                </button>
            </div>
        </div>
        
        <!-- Remember Me -->
        <div class="flex items-center justify-between mb-8">
            <label class="flex items-center space-x-3 cursor-pointer group">
                <div class="relative flex items-center justify-center">
                    <input type="checkbox" id="remember" name="remember"
                           class="peer h-5 w-5 text-indigo-600 border-slate-300 rounded-[6px] focus:ring-indigo-500 focus:ring-offset-0 transition-all cursor-pointer shadow-sm">
                </div>
                <span class="text-sm font-bold text-slate-500 group-hover:text-slate-800 transition-colors select-none">Keep me signed in</span>
            </label>
        </div>
        
        <!-- Submit Button -->
        <div>
            <button type="submit"
                    class="w-full bg-gradient-to-r from-indigo-600 to-blue-600 text-white py-3.5 px-4 rounded-2xl font-bold text-lg hover:from-indigo-700 hover:to-blue-700 focus:outline-none focus:ring-4 focus:ring-indigo-500/30 shadow-[0_8px_20px_rgb(79,70,229,0.3)] transition-all transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2">
                <span>Authorize Access</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd" />
                </svg>
            </button>
        </div>
    </form>
</div>

<style>
    /* V3 Fade In Animation */
    @keyframes fadeInDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fadeInDown {
        animation: fadeInDown 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
    }
</style>

<script>
    // Toggle Password Visibility Logic
    document.addEventListener('DOMContentLoaded', () => {
        const togglePassword = document.getElementById("togglePassword");
        const passwordInput = document.getElementById("password");

        togglePassword.addEventListener("click", () => {
            const isPassword = passwordInput.getAttribute("type") === "password";
            passwordInput.setAttribute("type", isPassword ? "text" : "password");
            togglePassword.textContent = isPassword ? "Hide" : "Reveal";
            
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