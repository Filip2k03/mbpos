
<?php
// config.php - shared production bootstrap for the MBPOS V5 application.
// Keep database values stable for the existing production dataset.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Set System Timezone to GMT +6:30 (Asia/Yangon)
date_default_timezone_set('Asia/Yangon');

$hostname = "localhost";
$username = "mbposV3_usr";
$password = "bCnxZMzSh3zhNSYA";
$database = "mbposv3";


// Establish the database connection
$connection = mysqli_connect($hostname, $username, $password, $database);

// Check connection
if (!$connection) {
    error_log('MBPOS database connection failed: ' . mysqli_connect_error());
    http_response_code(500);
    exit('The POS service is temporarily unavailable.');
}
mysqli_set_charset($connection, 'utf8mb4');

// Baseline browser protections. CSP is intentionally not forced here because
// legacy screens still load trusted CDN assets and inline page scripts.
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

// SameSite CSRF cookie: existing forms continue to work while cross-site POSTs
// are rejected by the router's origin/cookie guard.
if (!isset($_COOKIE['mbpos_csrf']) && !headers_sent()) {
    $csrf_cookie_secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $csrf_cookie_value = bin2hex(random_bytes(32));
    setcookie('mbpos_csrf', $csrf_cookie_value, [
        'expires' => time() + 86400,
        'path' => '/',
        'secure' => $csrf_cookie_secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    // Make the token available during this first response as well.
    $_COOKIE['mbpos_csrf'] = $csrf_cookie_value;
}

// Application Configuration
define('APP_NAME', 'MBLOGISTICS POS');
define('APP_VERSION', '5.4.7');
define('APP_URL', 'https://mbpos.online');

// Voucher Code Configuration
define('VOUCHER_CODE_LENGTH', 7); // e.g., 0000001
define('USER_TYPE_ADMIN', 'ADMIN');
define('USER_TYPE_MYANMAR', 'Myanmar'); // Assuming 'Myanmar' is the user_type string
define('USER_TYPE_MALAY', 'Malay');     // Assuming 'Malay' is the user_type string
define('USER_TYPE_DEVELOPER', 'Developer');
?>
