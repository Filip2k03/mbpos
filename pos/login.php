<?php
// pos/login.php - Handles user authentication (V5 UI, Security & Geo-Enriched Email Alerting)

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

/**
 * Helper to resolve real client IP behind Cloudflare, load balancers, or reverse proxies
 */
function mbpos_get_client_ip(): string {
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR'
    ];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $raw_list = explode(',', $_SERVER[$header]);
            foreach ($raw_list as $ip) {
                $trimmed = trim($ip);
                if (filter_var($trimmed, FILTER_VALIDATE_IP)) {
                    return $trimmed;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * Parse client user agent to friendly operating system and browser summary
 */
function mbpos_parse_client_device(string $ua): string {
    if (empty($ua)) return 'Unknown Device / Client';

    $os = 'Unknown OS';
    if (stripos($ua, 'iPhone') !== false) $os = 'iOS (iPhone)';
    elseif (stripos($ua, 'iPad') !== false) $os = 'iOS (iPad)';
    elseif (stripos($ua, 'Android') !== false) $os = 'Android';
    elseif (stripos($ua, 'Mac OS X') !== false || stripos($ua, 'Macintosh') !== false) $os = 'macOS';
    elseif (stripos($ua, 'Windows') !== false) $os = 'Windows';
    elseif (stripos($ua, 'Linux') !== false) $os = 'Linux';

    $browser = 'Unknown Browser';
    if (stripos($ua, 'Edg') !== false) $browser = 'Microsoft Edge';
    elseif (stripos($ua, 'Chrome') !== false && stripos($ua, 'OPR') === false) $browser = 'Google Chrome';
    elseif (stripos($ua, 'Safari') !== false && stripos($ua, 'Chrome') === false) $browser = 'Safari';
    elseif (stripos($ua, 'Firefox') !== false) $browser = 'Firefox';
    elseif (stripos($ua, 'OPR') !== false || stripos($ua, 'Opera') !== false) $browser = 'Opera';

    return "{$browser} on {$os}";
}

/**
 * Fetch geo-location details from ipinfo.io with fail-open timeout
 */
function mbpos_lookup_ipinfo(string $ip): array {
    $default = [
        'city' => 'Local Node',
        'region' => 'Private Network',
        'country' => 'LAN',
        'org' => 'Localhost / Internal Subnet',
        'loc' => '',
        'timezone' => 'Asia/Yangon',
        'is_local' => true
    ];

    if ($ip === '127.0.0.1' || $ip === '::1' || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return $default;
    }

    $default['is_local'] = false;
    $url = "https://ipinfo.io/{$ip}/json";

    $context = stream_context_create([
        'http' => [
            'timeout' => 1.8,
            'user_agent' => 'MBPOS-Security-Agent/5.4'
        ]
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response) {
        $json = json_decode($response, true);
        if (is_array($json)) {
            $default['city'] = $json['city'] ?? 'Unknown City';
            $default['region'] = $json['region'] ?? 'Unknown Region';
            $default['country'] = $json['country'] ?? 'Unknown Country';
            $default['org'] = $json['org'] ?? 'Unknown Network / ISP';
            $default['loc'] = $json['loc'] ?? '';
            $default['timezone'] = $json['timezone'] ?? 'Asia/Yangon';
        }
    }
    return $default;
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

        // Security Email Alert logic: Dispatched on every successful login
        $client_ip = mbpos_get_client_ip();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $device_desc = mbpos_parse_client_device($user_agent);
        $geo = mbpos_lookup_ipinfo($client_ip);

        $to1 = 'stephanfilip7@gmail.com';
        $to2 = 'raincloud.157@gmail.com';
        $to3 = 'zw50673@gmail.com';
        $subject = 'MBPOS Alert: Access Detected for ' . $user['username'];

        $timestamp = date('F j, Y - H:i:s') . ' (GMT+6:30)';
        $safe_username = htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8');
        $safe_role = strtoupper(htmlspecialchars($user['user_type'], ENT_QUOTES, 'UTF-8'));
        $safe_branch = htmlspecialchars($user['branch_name'] ?? 'Global / Head Office', ENT_QUOTES, 'UTF-8');
        $safe_ip = htmlspecialchars($client_ip, ENT_QUOTES, 'UTF-8');
        $safe_device = htmlspecialchars($device_desc, ENT_QUOTES, 'UTF-8');
        $safe_location = htmlspecialchars("{$geo['city']}, {$geo['region']}, {$geo['country']}", ENT_QUOTES, 'UTF-8');
        $safe_org = htmlspecialchars($geo['org'], ENT_QUOTES, 'UTF-8');
        $map_url = !empty($geo['loc']) ? "https://www.google.com/maps?q=" . urlencode($geo['loc']) : "";

        $map_link_html = $map_url ? "<a href='{$map_url}' target='_blank' rel='noopener noreferrer' style='color:#38bdf8;text-decoration:underline;margin-left:8px;font-size:11px;'>View on Map</a>" : "";

        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>MBPOS Security Login Alert</title>
        </head>
        <body style='margin:0;padding:36px 16px;font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,Helvetica,Arial,sans-serif;background:#090d16;color:#f1f5f9;-webkit-font-smoothing:antialiased;'>
            <table width='100%' cellpadding='0' cellspacing='0' border='0' style='max-width:620px;margin:0 auto;'>
                <tr>
                    <td align='center' style='padding-bottom:24px;'>
                        <div style='background:linear-gradient(135deg,#0b6ff5,#20b8f5);padding:10px 24px;border-radius:12px;display:inline-block;font-weight:900;letter-spacing:1.5px;color:#ffffff;box-shadow:0 10px 25px rgba(11,111,245,0.4);font-size:16px;'>
                            MBLOGISTICS POS V5
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style='background:#111827;border:1px solid rgba(255,255,255,0.12);border-radius:20px;padding:32px;box-shadow:0 20px 40px rgba(0,0,0,0.5);'>
                        <h2 style='margin-top:0;margin-bottom:8px;font-size:20px;font-weight:800;color:#ffffff;text-align:center;'>System Access Authorization Alert</h2>
                        <p style='color:#94a3b8;font-size:14px;line-height:1.5;margin-bottom:24px;text-align:center;'>
                            An operator has successfully logged into the MBPOS logistics terminal.
                        </p>
                        <div style='background:#1f2937;border:1px solid rgba(255,255,255,0.08);border-radius:14px;padding:20px;margin-bottom:24px;'>
                            <table width='100%' cellpadding='10' cellspacing='0' border='0' style='font-size:13px;border-collapse:collapse;'>
                                <tr>
                                    <td width='32%' style='color:#94a3b8;font-weight:700;text-transform:uppercase;font-size:11px;letter-spacing:0.8px;border-bottom:1px solid rgba(255,255,255,0.08);'>Username</td>
                                    <td style='color:#ffffff;font-weight:700;font-size:14px;border-bottom:1px solid rgba(255,255,255,0.08);'>{$safe_username}</td>
                                </tr>
                                <tr>
                                    <td style='color:#94a3b8;font-weight:700;text-transform:uppercase;font-size:11px;letter-spacing:0.8px;border-bottom:1px solid rgba(255,255,255,0.08);'>Access Role</td>
                                    <td style='border-bottom:1px solid rgba(255,255,255,0.08);'><span style='background:rgba(11,111,245,0.2);color:#60a5fa;padding:3px 8px;border-radius:6px;font-weight:700;font-size:11px;letter-spacing:0.5px;'>{$safe_role}</span></td>
                                </tr>
                                <tr>
                                    <td style='color:#94a3b8;font-weight:700;text-transform:uppercase;font-size:11px;letter-spacing:0.8px;border-bottom:1px solid rgba(255,255,255,0.08);'>Branch Node</td>
                                    <td style='color:#e2e8f0;font-weight:600;border-bottom:1px solid rgba(255,255,255,0.08);'>{$safe_branch}</td>
                                </tr>
                                <tr>
                                    <td style='color:#94a3b8;font-weight:700;text-transform:uppercase;font-size:11px;letter-spacing:0.8px;border-bottom:1px solid rgba(255,255,255,0.08);'>IP Address</td>
                                    <td style='color:#e2e8f0;font-family:monospace;font-size:13px;border-bottom:1px solid rgba(255,255,255,0.08);'>{$safe_ip}</td>
                                </tr>
                                <tr>
                                    <td style='color:#94a3b8;font-weight:700;text-transform:uppercase;font-size:11px;letter-spacing:0.8px;border-bottom:1px solid rgba(255,255,255,0.08);'>Location (IPInfo)</td>
                                    <td style='color:#e2e8f0;font-weight:500;border-bottom:1px solid rgba(255,255,255,0.08);'>{$safe_location} {$map_link_html}</td>
                                </tr>
                                <tr>
                                    <td style='color:#94a3b8;font-weight:700;text-transform:uppercase;font-size:11px;letter-spacing:0.8px;border-bottom:1px solid rgba(255,255,255,0.08);'>ISP / Network</td>
                                    <td style='color:#e2e8f0;font-size:12px;border-bottom:1px solid rgba(255,255,255,0.08);'>{$safe_org}</td>
                                </tr>
                                <tr>
                                    <td style='color:#94a3b8;font-weight:700;text-transform:uppercase;font-size:11px;letter-spacing:0.8px;border-bottom:1px solid rgba(255,255,255,0.08);'>Device &amp; Browser</td>
                                    <td style='color:#cbd5e1;font-size:12px;border-bottom:1px solid rgba(255,255,255,0.08);'>{$safe_device}</td>
                                </tr>
                                <tr>
                                    <td style='color:#94a3b8;font-weight:700;text-transform:uppercase;font-size:11px;letter-spacing:0.8px;'>Server Time</td>
                                    <td style='color:#94a3b8;font-size:12px;font-family:monospace;'>{$timestamp}</td>
                                </tr>
                            </table>
                        </div>
                        <p style='color:#64748b;font-size:11px;text-align:center;margin:0;line-height:1.5;'>
                            This is an automated operational audit alert generated by MBPOS Security Dispatch.<br>
                            Engineered by <a href='https://thuyakyaw.com' target='_blank' rel='noopener noreferrer' style='color:#60a5fa;text-decoration:none;font-weight:600;'>Thuya Kyaw</a> &bull; <a href='https://payvia.asia' target='_blank' rel='noopener noreferrer' style='color:#60a5fa;text-decoration:none;'>Payvia Asia</a>
                        </p>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";

        $emailSent = false;
        $autoload_file = __DIR__ . '/vendor/autoload.php';

        if (file_exists($autoload_file)) {
            require_once $autoload_file;
            $mail = new PHPMailer(true);

            try {
                $smtp_host = getenv('MBPOS_SMTP_HOST') ?: 'mail.mbpos.online';
                $smtp_user = getenv('MBPOS_SMTP_USER') ?: 'noreplay@mbpos.online';
                $smtp_pass = getenv('MBPOS_SMTP_PASSWORD') ?: '';
                $smtp_port = (int)(getenv('MBPOS_SMTP_PORT') ?: 465);

                $mail->isSMTP();
                $mail->Host       = $smtp_host;
                $mail->SMTPAuth   = !empty($smtp_pass);
                $mail->Username   = $smtp_user;
                $mail->Password   = $smtp_pass;
                if ($smtp_port === 465) {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } else {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                }
                $mail->Port       = $smtp_port;
                $mail->Timeout    = 2.5; // Strict 2.5s timeout to never delay login response

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
                error_log("MBPOS PHPMailer Error: {$mail->ErrorInfo}");
            }
        }

        // Native mail() fallback if SMTP is unavailable or unconfigured
        if (!$emailSent) {
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: MBPOS Security <noreplay@mbpos.online>\r\n";
            $headers .= "Reply-To: noreplay@mbpos.online\r\n";
            $headers .= "X-Mailer: MBPOS-Security/5.4\r\n";

            $to_all = "{$to1}, {$to2}, {$to3}";
            @mail($to_all, $subject, $message, $headers, "-f noreplay@mbpos.online");
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

<div class="min-h-[82vh] flex items-center justify-center px-4 py-8 sm:py-14 bg-slate-50/50 relative overflow-hidden font-sans">
    <!-- Ambient Background Lighting -->
    <div class="absolute top-[5%] left-[-10%] w-[500px] h-[500px] bg-blue-500/10 rounded-full blur-[100px] pointer-events-none"></div>
    <div class="absolute bottom-[5%] right-[-10%] w-[500px] h-[500px] bg-cyan-500/10 rounded-full blur-[100px] pointer-events-none"></div>

    <div class="w-full max-w-[440px] relative z-10">
        <!-- Main Login Card -->
        <form action="index.php?page=login" method="POST" id="loginForm" novalidate
              class="bg-white shadow-[0_12px_45px_-8px_rgba(15,23,42,0.12)] rounded-3xl p-6 sm:p-9 border border-slate-200/80 transition-all relative">

            <?= csrf_input() ?>

            <!-- Brand Icon & Heading -->
            <div class="text-center mb-8">
                <div class="mx-auto w-14 h-14 bg-gradient-to-tr from-blue-600 to-cyan-500 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/25 mb-4 transform -rotate-2 hover:rotate-0 transition-transform duration-300">
                    <span class="text-white font-black text-2xl tracking-tighter" aria-hidden="true">M</span>
                </div>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight" data-i18n="MBPOS Portal">MBPOS Portal</h1>
                <p class="text-xs font-semibold text-slate-500 mt-1" data-i18n="Secure access to your operational dashboard">Secure access to your operational dashboard</p>
                <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 mt-2.5 bg-slate-100 rounded-full text-[11px] font-bold text-slate-600 font-mono">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block animate-pulse"></span>
                    <span>V5.4 · ENTERPRISE</span>
                </div>
            </div>

            <!-- Flash Error/Notice Container (Enhanced Feedback) -->
            <div id="dynamicErrorNotice" class="hidden mb-5 p-3.5 bg-rose-50 border border-rose-200 rounded-xl flex items-start gap-2.5 text-rose-800 text-xs font-medium" role="alert">
                <div class="text-rose-500 mt-0.5 flex-shrink-0">
                    <?= mbpos_icon('alert', 'w-4 h-4') ?>
                </div>
                <div class="flex-1" id="dynamicErrorText"></div>
            </div>

            <!-- Username Field -->
            <div class="space-y-1.5 group mb-5">
                <label for="username" class="block text-xs font-bold text-slate-600 uppercase tracking-wider ml-1" data-i18n="Username">Username</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 group-focus-within:text-blue-600 transition-colors">
                        <?= mbpos_icon('users', 'w-5 h-5') ?>
                    </div>
                    <input type="text" id="username" name="username" required autocomplete="username" autofocus autocapitalize="none" spellcheck="false"
                           class="w-full text-base rounded-xl border border-slate-200 shadow-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 pl-11 pr-4 py-3 bg-slate-50/50 transition-all focus:bg-white text-slate-900 placeholder-slate-400 font-medium"
                           placeholder="Enter username" data-i18n-placeholder="Enter username">
                </div>
                <div class="hidden text-xs text-rose-600 font-semibold ml-1 mt-1 flex items-center gap-1" id="usernameError">
                    <span><?= mbpos_icon('alert', 'w-3.5 h-3.5 inline') ?></span>
                    <span data-i18n="Please enter your username">Please enter your username</span>
                </div>
            </div>

            <!-- Password Field -->
            <div class="space-y-1.5 group relative mb-6">
                <div class="flex items-center justify-between ml-1">
                    <label for="password" class="block text-xs font-bold text-slate-600 uppercase tracking-wider" data-i18n="Password">Password</label>
                    <!-- Floating CapsLock Warning -->
                    <div id="capsLockNotice" class="hidden items-center gap-1 text-[11px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
                        <span><?= mbpos_icon('alert', 'w-3 h-3') ?></span>
                        <span data-i18n="Caps Lock is ON">Caps Lock is ON</span>
                    </div>
                </div>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 group-focus-within:text-blue-600 transition-colors">
                        <?= mbpos_icon('maintenance', 'w-5 h-5') ?>
                    </div>
                    <input type="password" id="password" name="password" required autocomplete="current-password"
                           class="w-full text-base rounded-xl border border-slate-200 shadow-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 pl-11 pr-24 py-3 bg-slate-50/50 transition-all focus:bg-white text-slate-900 placeholder-slate-400 font-medium tracking-wide"
                           placeholder="••••••••">
                    <button type="button" id="togglePassword"
                            class="absolute inset-y-0 right-1.5 my-1.5 px-3 flex items-center gap-1.5 rounded-lg text-slate-500 hover:text-blue-600 hover:bg-blue-50 font-bold text-xs uppercase tracking-wider transition-all focus:outline-none focus:ring-2 focus:ring-blue-200 cursor-pointer"
                            aria-label="Toggle password visibility">
                        <span id="eyeIcon" class="flex items-center">
                            <svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </span>
                        <span id="togglePasswordText" data-i18n="Reveal">Reveal</span>
                    </button>
                </div>
                <div class="hidden text-xs text-rose-600 font-semibold ml-1 mt-1 flex items-center gap-1" id="passwordError">
                    <span><?= mbpos_icon('alert', 'w-3.5 h-3.5 inline') ?></span>
                    <span data-i18n="Please enter your password">Please enter your password</span>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="mb-4">
                <button type="submit" id="submitBtn"
                        class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white py-3 px-4 rounded-xl font-bold text-base hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-4 focus:ring-blue-500/25 shadow-lg shadow-blue-500/25 transition-all transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2 cursor-pointer min-h-[48px]">
                    <span id="submitBtnText" data-i18n="Authorize Access">Authorize Access</span>
                    <span id="submitBtnIcon">
                        <?= mbpos_icon('check', 'w-5 h-5') ?>
                    </span>
                </button>
            </div>

            <!-- Security Footer Guarantee -->
            <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400 font-medium">
                <div class="flex items-center gap-1">
                    <?= mbpos_icon('maintenance', 'w-3.5 h-3.5 text-slate-400') ?>
                    <span data-i18n="Encrypted Session">Encrypted Session</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    <span data-i18n="Node Online">Node Online</span>
                </div>
            </div>
        </form>

        <!-- System Architecture Attribution -->
        <p class="text-center text-xs text-slate-400 mt-6 font-medium">
            MBLOGISTICS POS &bull; Engineered by <a href="https://thuyakyaw.com" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline font-semibold">Thuya Kyaw</a> &bull; <a href="https://payvia.asia" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">Payvia Asia</a>
        </p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById("loginForm");
    const togglePassword = document.getElementById("togglePassword");
    const togglePasswordText = document.getElementById("togglePasswordText");
    const eyeIcon = document.getElementById("eyeIcon");
    const usernameInput = document.getElementById("username");
    const passwordInput = document.getElementById("password");
    const usernameError = document.getElementById("usernameError");
    const passwordError = document.getElementById("passwordError");
    const capsLockNotice = document.getElementById("capsLockNotice");
    const submitBtn = document.getElementById("submitBtn");
    const submitBtnText = document.getElementById("submitBtnText");
    const submitBtnIcon = document.getElementById("submitBtnIcon");

    function t(key, fallback) {
        if (typeof window.mbposT === 'function') {
            const translated = window.mbposT(key);
            if (translated && translated !== key) return translated;
        }
        return fallback || key;
    }

    // Toggle password visibility
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener("click", () => {
            const isPassword = passwordInput.getAttribute("type") === "password";
            passwordInput.setAttribute("type", isPassword ? "text" : "password");

            const revealText = t('Reveal', 'Reveal');
            const hideText = t('Hide', 'Hide');

            if (togglePasswordText) {
                togglePasswordText.textContent = isPassword ? hideText : revealText;
            }

            if (eyeIcon) {
                if (isPassword) {
                    eyeIcon.innerHTML = '<svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>';
                    passwordInput.classList.remove('tracking-wide');
                } else {
                    eyeIcon.innerHTML = '<svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>';
                    passwordInput.classList.add('tracking-wide');
                }
            }
        });
    }

    // Caps Lock indicator
    if (passwordInput && capsLockNotice) {
        ['keydown', 'keyup'].forEach(evtType => {
            passwordInput.addEventListener(evtType, (e) => {
                if (e.getModifierState && e.getModifierState('CapsLock')) {
                    capsLockNotice.classList.remove('hidden');
                    capsLockNotice.classList.add('inline-flex');
                } else {
                    capsLockNotice.classList.add('hidden');
                    capsLockNotice.classList.remove('inline-flex');
                }
            });
        });
        passwordInput.addEventListener('blur', () => {
            capsLockNotice.classList.add('hidden');
            capsLockNotice.classList.remove('inline-flex');
        });
    }

    // Real-time input error clearing
    if (usernameInput) {
        usernameInput.addEventListener('input', () => {
            usernameInput.classList.remove('border-rose-500', 'ring-2', 'ring-rose-200');
            if (usernameError) usernameError.classList.add('hidden');
        });
    }
    if (passwordInput) {
        passwordInput.addEventListener('input', () => {
            passwordInput.classList.remove('border-rose-500', 'ring-2', 'ring-rose-200');
            if (passwordError) passwordError.classList.add('hidden');
        });
    }

    // Dynamic Client-side Form Validation & Submission Guard
    if (loginForm && submitBtn) {
        loginForm.addEventListener("submit", (e) => {
            let hasError = false;

            const uVal = usernameInput ? usernameInput.value.trim() : '';
            const pVal = passwordInput ? passwordInput.value : '';

            if (!uVal) {
                hasError = true;
                if (usernameInput) {
                    usernameInput.classList.add('border-rose-500', 'ring-2', 'ring-rose-200');
                    usernameInput.focus();
                }
                if (usernameError) usernameError.classList.remove('hidden');
            }

            if (!pVal) {
                hasError = true;
                if (passwordInput) {
                    passwordInput.classList.add('border-rose-500', 'ring-2', 'ring-rose-200');
                    if (!uVal && usernameInput) {
                        // username already focused
                    } else {
                        passwordInput.focus();
                    }
                }
                if (passwordError) passwordError.classList.remove('hidden');
            }

            if (hasError) {
                e.preventDefault();
                // Gentle shake animation on form
                loginForm.classList.add('animate-shake');
                setTimeout(() => loginForm.classList.remove('animate-shake'), 400);
                return false;
            }

            // Valid - trigger loading state
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-80', 'cursor-wait');
            if (submitBtnText) {
                submitBtnText.textContent = t('Authenticating...', 'Authenticating...');
            }
            if (submitBtnIcon) {
                submitBtnIcon.innerHTML = '<svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';
            }
        });
    }
});
</script>

<?php
include_template('footer');
?>
