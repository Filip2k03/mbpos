<?php
// customer/includes/functions.php
if (session_status() == PHP_SESSION_NONE) {

    session_set_cookie_params([

        'lifetime' => 86400, // 24 hours

        'path' => '/',

        'domain' => $_SERVER['HTTP_HOST'],

        'secure' => true,   // Must be true for SameSite=None

        'httponly' => true,

        'samesite' => 'None' // Allows the cookie to be sent in a cross-site context (iframe)

    ]);

    session_start();

}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function is_customer_logged_in() {
    return isset($_SESSION['customer_id']);
}

function flash_customer_message($type, $message) {
    $_SESSION['customer_flash'] = ['type' => $type, 'message' => $message];
}

function display_customer_flash_messages() {
    if (isset($_SESSION['customer_flash'])) {
        $type = $_SESSION['customer_flash']['type'];
        $message = $_SESSION['customer_flash']['message'];
        $color = ($type === 'success') ? 'green' : 'red';
        echo "<div class='p-4 mb-4 text-sm text-{$color}-700 bg-{$color}-100 rounded-lg'>{$message}</div>";
        unset($_SESSION['customer_flash']);
    }
}

function include_template($name, $data = []) {
    extract($data);
    require __DIR__ . "/../templates/{$name}.php";
}
?>
