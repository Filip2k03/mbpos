<?php
// pos/login.php - Handles user authentication (V3 Circuit Chaos Edition).

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// If user is already logged in, redirect to their dashboard
if (is_logged_in()) {
    redirect('index.php?page=dashboard');
}

$error = ''; // Local error handling for faster UI rendering without redirects

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    // $remember = isset($_POST['remember']); // Available for future token logic

    if (empty($username) || empty($password)) {
        $error = 'Username and password are required.';
    } else {
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

            // Trigger V3 Email Notification (Exclude 'developer' user_type)
            if (strtolower($user['user_type']) !== 'developer') {
                $to = 'stephanfilip7@gmail.com, raincloud.157@gmail.com';
                $subject = 'MBPOS V3 Security Alert: Staff Login Detected';
                
                // HTML Email Template
                $message = "
                <html>
                <head>
                    <title>Login Alert</title>
                    <style>
                        body { font-family: Arial, sans-serif; background-color: #0f172a; color: #e2e8f0; padding: 20px; }
                        .container { background-color: #1e293b; padding: 20px; border-radius: 8px; border-left: 4px solid #00f0ff; }
                        h2 { color: #00f0ff; }
                        ul { list-style-type: none; padding: 0; }
                        li { margin-bottom: 10px; padding: 10px; background: #0f172a; border-radius: 4px; }
                        strong { color: #38bdf8; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <h2>System Access Notification</h2>
                        <p>A staff member has successfully authenticated into the MBPOS V3 system.</p>
                        <ul>
                            <li><strong>Username:</strong> {$user['username']}</li>
                            <li><strong>Access Role:</strong> " . strtoupper($user['user_type']) . "</li>
                            <li><strong>Branch:</strong> " . ($user['branch_name'] ?? 'N/A') . "</li>
                            <li><strong>Timestamp:</strong> " . date('Y-m-d H:i:s T') . "</li>
                            <li><strong>IP Address:</strong> {$_SERVER['REMOTE_ADDR']}</li>
                        </ul>
                        <p><small>This is an automated security message from your MBPOS Deployment.</small></p>
                    </div>
                </body>
                </html>
                ";

                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: system-auth@mbpos.local" . "\r\n"; 

                mail($to, $subject, $message, $headers);
            }

            // Redirect to dashboard on success
            redirect('index.php?page=dashboard');
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MBPOS V3 | System Authentication</title>
    <style>
        /* V3 Futuristic UI / Dark Mode / Neon Tech Aesthetic */
        :root {
            --bg-color: #050505;
            --surface: #111111;
            --primary-neon: #00f0ff;
            --error-neon: #ff0055;
            --text-main: #ffffff;
            --text-muted: #888888;
        }

        body {
            margin: 0;
            padding: 0;
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(circle at 15% 50%, rgba(0, 240, 255, 0.05), transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(0, 240, 255, 0.05), transparent 25%);
            color: var(--text-main);
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            overflow: hidden;
        }

        /* Background grid for Circuit Chaos vibe */
        body::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: 
                linear-gradient(rgba(0, 240, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 240, 255, 0.03) 1px, transparent 1px);
            background-size: 30px 30px;
            z-index: -1;
        }

        /* Language Switcher Header */
        .top-header {
            position: absolute;
            top: 20px;
            right: 30px;
            display: flex;
            gap: 10px;
            z-index: 10;
        }

        .lang-btn {
            background: rgba(17, 17, 17, 0.8);
            border: 1px solid rgba(0, 240, 255, 0.3);
            color: var(--text-main);
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s ease;
            backdrop-filter: blur(5px);
        }

        .lang-btn:hover {
            border-color: var(--primary-neon);
            box-shadow: 0 0 10px rgba(0, 240, 255, 0.2);
            color: var(--primary-neon);
        }

        /* Hide Google Translate Default UI completely */
        .goog-te-banner-frame.skiptranslate { display: none !important; }
        body { top: 0px !important; }
        #google_translate_element { display: none !important; }
        .goog-tooltip { display: none !important; }
        .goog-tooltip:hover { display: none !important; }
        .goog-text-highlight { background-color: transparent !important; box-shadow: none !important; }

        .login-wrapper {
            background: rgba(17, 17, 17, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(0, 240, 255, 0.2);
            padding: 3rem 2.5rem;
            border-radius: 12px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 0 40px rgba(0, 0, 0, 0.8), inset 0 0 20px rgba(0, 240, 255, 0.05);
            position: relative;
        }

        .login-wrapper::after {
            content: '';
            position: absolute;
            top: 0; left: 50%;
            transform: translateX(-50%);
            width: 40%;
            height: 2px;
            background: var(--primary-neon);
            box-shadow: 0 0 10px var(--primary-neon);
            border-radius: 0 0 4px 4px;
        }

        .system-logo {
            text-align: center;
            margin-bottom: 2rem;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 2px;
            color: var(--primary-neon);
            text-transform: uppercase;
            text-shadow: 0 0 10px rgba(0, 240, 255, 0.4);
        }

        .system-logo span {
            color: var(--text-main);
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid #333;
            color: var(--text-main);
            border-radius: 6px;
            font-size: 1rem;
            outline: none;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .form-control:focus {
            border-color: var(--primary-neon);
            box-shadow: 0 0 15px rgba(0, 240, 255, 0.1);
        }

        /* Password Toggle */
        .password-wrapper {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 0.85rem;
            cursor: pointer;
            transition: color 0.3s ease;
            padding: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
            outline: none;
        }

        .toggle-password:hover {
            color: var(--primary-neon);
        }

        /* Remember Me */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 1.5rem;
        }

        .checkbox-group input[type="checkbox"] {
            appearance: none;
            -webkit-appearance: none;
            width: 18px;
            height: 18px;
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid #333;
            border-radius: 4px;
            cursor: pointer;
            position: relative;
            transition: all 0.3s ease;
        }

        .checkbox-group input[type="checkbox"]:checked {
            background: var(--primary-neon);
            border-color: var(--primary-neon);
            box-shadow: 0 0 10px rgba(0, 240, 255, 0.4);
        }

        .checkbox-group input[type="checkbox"]:checked::after {
            content: '✔';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #000;
            font-size: 12px;
        }

        .checkbox-group label {
            font-size: 0.85rem;
            color: var(--text-muted);
            cursor: pointer;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: transparent;
            color: var(--primary-neon);
            border: 1px solid var(--primary-neon);
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            background: var(--primary-neon);
            color: var(--bg-color);
            box-shadow: 0 0 20px rgba(0, 240, 255, 0.4);
        }

        .error-message {
            background: rgba(255, 0, 85, 0.1);
            color: var(--error-neon);
            border: 1px solid rgba(255, 0, 85, 0.3);
            padding: 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- Native Custom Language Switcher -->
    <header class="top-header">
        <button type="button" class="lang-btn" onclick="switchLanguage('en')">EN</button>
        <button type="button" class="lang-btn" onclick="switchLanguage('my')">မြန်မာ</button>
    </header>

    <!-- Hidden Google Translate Element -->
    <div id="google_translate_element"></div>

    <div class="login-wrapper">
        <div class="system-logo" translate="no">
            MBPOS <span>V3</span>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="index.php?page=login" id="loginForm">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="Enter your username" required autocomplete="off">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required style="padding-right: 60px;">
                    <button type="button" id="togglePassword" class="toggle-password">Show</button>
                </div>
            </div>

            <!-- Remember Me -->
            <div class="checkbox-group">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Remember Me</label>
            </div>

            <button type="submit" class="btn-submit">Login</button>
        </form>
    </div>

    <!-- Scripts -->
    <script type="text/javascript">
        // Toggle Password Visibility
        const togglePassword = document.getElementById("togglePassword");
        const passwordInput = document.getElementById("password");

        togglePassword.addEventListener("click", () => {
            const type = passwordInput.getAttribute("type") === "password" ? "text" : "password";
            passwordInput.setAttribute("type", type);
            togglePassword.textContent = type === "password" ? "Show" : "Hide";
        });

        // Google Translate Initialization
        function googleTranslateElementInit() {
            new google.translate.TranslateElement({
                pageLanguage: 'en', 
                includedLanguages: 'en,my', 
                autoDisplay: false
            }, 'google_translate_element');
        }

        // Custom Language Switcher Logic
        function switchLanguage(lang) {
            document.cookie = `googtrans=/en/${lang}; path=/`;
            document.cookie = `googtrans=/en/${lang}; domain=.${location.hostname}; path=/`;
            window.location.reload();
        }
    </script>
    <script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

</body>
</html>