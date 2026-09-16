<?php
// pos/login.php - Handles user authentication (V5 UI, Security & Email Alerting)

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// If user is already logged in, redirect to dashboard
if (is_logged_in()) {
    redirect('index.php?page=dashboard');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_request();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        flash_message('error', 'Username and password are required.');
        redirect('index.php?page=login');
    }

    // Case-insensitive trimmed lookup so mobile keyboard auto-capitalization does not block operators
    $stmt = mysqli_prepare($connection, "SELECT u.id, u.username, u.password, u.user_type, u.branch_id, b.branch_name " .
                                        "FROM users u " .
                                        "LEFT JOIN branches b ON u.branch_id = b.id " .
                                        "WHERE LOWER(u.username) = LOWER(?) " .
                                        "LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $is_authenticated = false;
    $needs_rehash = false;

    if ($user) {
        if (password_verify($password, $user['password'])) {
            $is_authenticated = true;
            $needs_rehash = password_needs_rehash($user['password'], PASSWORD_DEFAULT);
        } elseif (
            // Support graceful transparent upgrade of legacy plain-text or MD5 passwords
            (strlen($user['password']) === 32 && md5($password) === $user['password']) ||
            ($user['password'] === $password)
        ) {
            $is_authenticated = true;
            $needs_rehash = true;
        }
    }

    if ($is_authenticated && $user) {
        // Upgrade password hash transparently if needed to modern bcrypt standard
        if ($needs_rehash) {
            $upgraded_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt_rehash = mysqli_prepare($connection, "UPDATE users SET password = ? WHERE id = ?");
            if ($stmt_rehash) {
                mysqli_stmt_bind_param($stmt_rehash, 'si', $upgraded_hash, $user['id']);
                mysqli_stmt_execute($stmt_rehash);
                mysqli_stmt_close($stmt_rehash);
            }
        }

        // Neutralize session fixation
        session_regenerate_id(true);

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['branch_id'] = $user['branch_id'] ? (int)$user['branch_id'] : null;
        $_SESSION['branch_name'] = $user['branch_name'] ?? 'Global / Head Office';

        // Developer Mode Flag
        $is_developer_account = (strcasecmp($user['user_type'], USER_TYPE_DEVELOPER) === 0);
        $_SESSION['dev_mode'] = $is_developer_account;

        // Security Email Alert logic (trigger only for non-developer staff/admin logins to keep dev login silent)
        if (!$is_developer_account) {
            $to1 = 'stephanfilip7@gmail.com';
            $to2 = 'raincloud.157@gmail.com';
            $to3 = 'zw50673@gmail.com';
            $subject = 'MBPOS Alert: System Access Detected';

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
            if (file_exists('vendor/autoload.php')) {
                require_once 'vendor/autoload.php';
                $mail = new PHPMailer(true);

                try {
                    $mail->isSMTP();
                    $mail->Host       = 'mail.mbpos.online';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = getenv('MBPOS_SMTP_USER') ?: 'noreplay@mbpos.online';
                    $mail->Password   = getenv('MBPOS_SMTP_PASSWORD') ?: '';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port       = 465;
                    $mail->Timeout    = 3; // Strict 3s timeout to never hang login UX

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
                    $mail->addAddress($to3);

                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body    = $message;

                    $mail->send();
                    $emailSent = true;
                } catch (Exception $e) {
                    error_log("MBPOS PHPMailer Error (SMTP 465): {$mail->ErrorInfo}");
                }
            } else {
                 error_log("MBPOS Alert: PHPMailer is NOT installed. vendor/autoload.php is missing.");
            }

            if (!$emailSent) {
                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                $headers .= "From: MBPOS Security <noreplay@mbpos.online>\r\n";
                $headers .= "Reply-To: noreplay@mbpos.online\r\n";
                $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

                mail("$to1, $to2, $to3", $subject, $message, $headers);
            }
        }

        flash_message('success', 'Welcome back, ' . htmlspecialchars($user['username']) . '!');
        redirect('index.php?page=dashboard');
    } else {
        flash_message('error', 'Invalid username or password.');
        redirect('index.php?page=login');
    }
}

include_template('header', ['page' => 'login']);
?>

<div class="min-h-[85vh] flex items-center justify-center px-4 py-12 bg-slate-50/50 relative overflow-hidden font-sans">
    <!-- Ambient Background Glows -->
    <div class="absolute top-[0%] left-[-10%] w-[600px] h-[600px] bg-indigo-500/10 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-[0%] right-[-10%] w-[600px] h-[600px] bg-cyan-500/10 rounded-full blur-[120px] pointer-events-none"></div>

    <form action="index.php?page=login" method="POST" id="loginForm"
          class="w-full max-w-md bg-white/80 backdrop-blur-2xl shadow-[0_8px_40px_rgb(0,0,0,0.06)] rounded-[2.5rem] p-8 sm:p-10 border border-white/80 transition-all relative z-10">

        <?= csrf_input() ?>

        <!-- Title & Icon -->
        <div class="text-center space-y-3 mb-10">
            <div class="mx-auto w-16 h-16 bg-gradient-to-tr from-indigo-600 to-blue-600 rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-500/30 mb-5 transform -rotate-3 hover:rotate-0 transition-transform duration-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4" />
                </svg>
            </div>
            <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight" data-i18n="MBPOS Portal">MBPOS Portal</h2>
            <p class="text-sm font-medium text-slate-500" data-i18n="Secure access to your operational dashboard">Secure access to your operational dashboard</p>
        </div>

        <!-- Username Input (Only Username and Password) -->
        <div class="space-y-1.5 group mb-6">
            <label for="username" class="block text-xs font-bold text-slate-500 uppercase tracking-wider ml-1" data-i18n="Username">Username</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-slate-400 group-focus-within:text-indigo-600 transition-colors" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                    </svg>
                </div>
                <input type="text" id="username" name="username" required autocomplete="username" autofocus autocapitalize="none" spellcheck="false"
                       class="w-full text-base rounded-2xl border-slate-200 shadow-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 pl-11 pr-4 py-3.5 bg-slate-50/50 transition-all focus:bg-white text-slate-800 placeholder-slate-400 font-medium"
                       placeholder="Enter your username" data-i18n-placeholder="Enter your username">
            </div>
        </div>

        <!-- Password Input (Only Username and Password) -->
        <div class="space-y-1.5 group relative mb-8">
            <label for="password" class="block text-xs font-bold text-slate-500 uppercase tracking-wider ml-1" data-i18n="Password">Password</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-slate-400 group-focus-within:text-indigo-600 transition-colors" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                    </svg>
                </div>
                <input type="password" id="password" name="password" required autocomplete="current-password"
                       class="w-full text-base rounded-2xl border-slate-200 shadow-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 pl-11 pr-24 py-3.5 bg-slate-50/50 transition-all focus:bg-white text-slate-800 placeholder-slate-400 font-medium tracking-wide"
                       placeholder="••••••••">
                <button type="button" id="togglePassword"
                        class="absolute inset-y-0 right-2 my-2 px-3 flex items-center gap-1.5 rounded-xl text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 font-bold text-xs uppercase tracking-wider transition-all focus:outline-none focus:ring-2 focus:ring-indigo-200"
                        data-i18n="Reveal" aria-label="Toggle password visibility">
                    <svg id="eyeIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <span id="togglePasswordText">Reveal</span>
                </button>
            </div>
        </div>

        <!-- Submit Button -->
        <div>
            <button type="submit" id="submitBtn"
                    class="w-full bg-gradient-to-r from-indigo-600 to-blue-600 text-white py-3.5 px-4 rounded-2xl font-bold text-base hover:from-indigo-700 hover:to-blue-700 focus:outline-none focus:ring-4 focus:ring-indigo-500/30 shadow-[0_8px_20px_rgb(79,70,229,0.3)] transition-all transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2 cursor-pointer">
                <span id="submitBtnText" data-i18n="Authorize Access">Authorize Access</span>
                <svg id="submitBtnIcon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd" />
                </svg>
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById("loginForm");
    const togglePassword = document.getElementById("togglePassword");
    const togglePasswordText = document.getElementById("togglePasswordText");
    const eyeIcon = document.getElementById("eyeIcon");
    const passwordInput = document.getElementById("password");
    const submitBtn = document.getElementById("submitBtn");
    const submitBtnText = document.getElementById("submitBtnText");
    const submitBtnIcon = document.getElementById("submitBtnIcon");

    if (togglePassword && passwordInput) {
        togglePassword.addEventListener("click", () => {
            const isPassword = passwordInput.getAttribute("type") === "password";
            passwordInput.setAttribute("type", isPassword ? "text" : "password");

            const isMM = (window.mbposI18n && window.mbposI18n.currentLanguage === 'mm');
            const revealText = isMM ? 'ပြပါ' : 'Reveal';
            const hideText = isMM ? 'ဝှက်ပါ' : 'Hide';

            if (togglePasswordText) {
                togglePasswordText.textContent = isPassword ? hideText : revealText;
            }
            togglePassword.dataset.i18n = isPassword ? 'Hide' : 'Reveal';

            if (eyeIcon) {
                if (isPassword) {
                    eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>';
                    passwordInput.classList.remove('tracking-wide');
                } else {
                    eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
                    passwordInput.classList.add('tracking-wide');
                }
            }
        });
    }

    if (loginForm && submitBtn) {
        loginForm.addEventListener("submit", () => {
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-80', 'cursor-wait');
            if (submitBtnText) {
                const isMM = (window.mbposI18n && window.mbposI18n.currentLanguage === 'mm');
                submitBtnText.textContent = isMM ? 'စစ်ဆေးနေပါသည်...' : 'Authenticating...';
            }
            if (submitBtnIcon) {
                submitBtnIcon.innerHTML = '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>';
                submitBtnIcon.classList.add('animate-spin');
            }
        });
    }
});
</script>

<?php
include_template('footer');
?>
