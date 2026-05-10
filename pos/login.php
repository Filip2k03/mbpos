<?php
session_start();
// Include your standard configuration file (assuming PDO connection $pdo is established here)
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        // Prepare statement to prevent SQL injection
        // NOTE: Adjust table name 'users' or column names based on your database/db.sql
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Security note: Using password_verify for V3 standard. 
        // If using plain text or md5 in V2, update this line accordingly (e.g., $user['password'] === md5($password))
        if ($user && password_verify($password, $user['password'])) {
            
            // 1. Set Session Variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // 2. Trigger V3 Email Notification (Exclude 'developer')
            if (strtolower($user['role']) !== 'developer') {
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
                            <li><strong>Access Role:</strong> " . strtoupper($user['role']) . "</li>
                            <li><strong>Timestamp:</strong> " . date('Y-m-d H:i:s T') . "</li>
                            <li><strong>IP Address:</strong> {$_SERVER['REMOTE_ADDR']}</li>
                        </ul>
                        <p><small>This is an automated security message from your MBPOS Deployment.</small></p>
                    </div>
                </body>
                </html>
                ";

                // Standard Headers for HTML Email
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: system-auth@mbpos.local" . "\r\n"; // Change to your actual domain

                // Send Email (Ensure SMTP is configured on your server for this to work reliably)
                mail($to, $subject, $message, $headers);
            }

            // 3. Route to corresponding dashboard
            if (strtolower($user['role']) === 'developer') {
                header("Location: developer_dashboard.php");
            } elseif (strtolower($user['role']) === 'admin') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit;

        } else {
            $error = "Authentication failed. Invalid username or password.";
        }
    } else {
        $error = "Please initialize both identification fields.";
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

        /* Top decorative neon bar */
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
            margin-top: 1rem;
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

    <div class="login-wrapper">
        <div class="system-logo">
            MBPOS <span>V3</span>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Identification</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="Enter Username" required autocomplete="off">
            </div>
            
            <div class="form-group">
                <label for="password">Security Key</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Enter Password" required>
            </div>

            <button type="submit" class="btn-submit">Initialize Session</button>
        </form>
    </div>

</body>
</html>