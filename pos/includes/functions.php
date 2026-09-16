<?php
// pos/includes/functions.php - Contains helper functions for the application.

/**
 * Redirects to a specified URL.
 * @param string $url The URL to redirect to.
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/** Escape untrusted values for safe HTML output. */
function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Return the current request's CSRF cookie token for forms/fetch clients. */
function csrf_token() {
    if (!empty($_COOKIE['mbpos_csrf'])) {
        return (string)$_COOKIE['mbpos_csrf'];
    }
    return '';
}

/** Render an optional hidden CSRF field for new forms. */
function csrf_input() {
    $token = csrf_token();
    return $token === '' ? '' : '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Reject cross-site state-changing requests.
 * SameSite=Strict is the primary defense; Origin/Referer checks provide a
 * second layer and the hidden token supports progressively enhanced forms.
 */
function require_csrf_request() {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    $app_origin = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
    $request_origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $request_referer = $_SERVER['HTTP_REFERER'] ?? '';
    $origin_ok = false;

    if ($request_origin !== '') {
        $origin_ok = hash_equals($app_origin, rtrim($request_origin, '/'));
    } elseif ($request_referer !== '') {
        $referer_origin = parse_url($request_referer, PHP_URL_SCHEME) . '://' . parse_url($request_referer, PHP_URL_HOST);
        if (parse_url($request_referer, PHP_URL_PORT)) {
            $referer_origin .= ':' . parse_url($request_referer, PHP_URL_PORT);
        }
        $origin_ok = hash_equals($app_origin, rtrim($referer_origin, '/'));
    }

    $cookie_token = csrf_token();
    $posted_token = (string)($_POST['csrf_token'] ?? '');
    $token_ok = $cookie_token !== '' && $posted_token !== '' && hash_equals($cookie_token, $posted_token);

    if (!$origin_ok && !$token_ok) {
        http_response_code(403);
        exit('Request rejected by security policy. Refresh the page and try again.');
    }
}

/**
 * Sets a flash message in the session.
 * @param string $type Type of message (e.g., 'success', 'error', 'info', 'warning').
 * @param string $message The message content.
 */
function flash_message($type, $message) {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = ['type' => $type, 'message' => $message];
}

/**
 * Displays and clears all flash messages.
 */
function display_flash_messages() {
    if (isset($_SESSION['flash_messages']) && !empty($_SESSION['flash_messages'])) {
        foreach ($_SESSION['flash_messages'] as $key => $msg) {
            $class = '';
            switch ($msg['type']) {
                case 'success':
                    $class = 'bg-green-100 border-green-400 text-green-700';
                    break;
                case 'error':
                    $class = 'bg-red-100 border-red-400 text-red-700';
                    break;
                case 'info':
                    $class = 'bg-blue-100 border-blue-400 text-blue-700';
                    break;
                case 'warning':
                    $class = 'bg-yellow-100 border-yellow-400 text-yellow-700';
                    break;
            }
            echo "<div class='flash-message {$class} p-4 mb-4 text-sm rounded-lg' id='flash-message-" . e($key) . "'>" . e($msg['message']) . "</div>";
        }
        unset($_SESSION['flash_messages']); // Clear messages after displaying
    }
}


/**
 * Checks if a user is authenticated.
 * @return bool True if logged in, false otherwise.
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Checks if the logged-in user is an admin.
 * @return bool True if admin, false otherwise.
 */
function is_admin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'ADMIN';
}

/**
 * Checks if the logged-in user is a developer.
 * @return bool True if developer, false otherwise.
 */
function is_developer() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Developer';
}

/**
 * Checks if the logged-in user is a staff member.
 * @return bool True if staff, false otherwise.
 */
function is_staff() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Staff';
}

/**
 * Checks if the logged-in user belongs to the Myanmar operations team.
 */
function is_myanmar_user() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Myanmar';
}

/**
 * Checks if the logged-in user belongs to the Malay operations team.
 */
function is_malay_user() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'Malay';
}

/**
 * Gets the branch ID of the currently logged-in user.
 * @return int|null The branch ID or null if not set.
 */
function get_user_branch_id() {
    return $_SESSION['branch_id'] ?? null;
}

/**
 * Gets the branch name of the currently logged-in user.
 * @return string|null The branch name or null if not set.
 */
function get_user_branch_name() {
    return $_SESSION['branch_name'] ?? null;
}


/**
 * Generates a unique voucher code based on region prefix and sequence.
 * @param string $prefix The region prefix (e.g., 'MM', 'MY').
 * @param int $sequence The current sequence number.
 * @return string The formatted voucher code.
 */
function generate_voucher_code($prefix, $sequence) {
    // Pad the sequence number with leading zeros to the defined length
    $padded_sequence = str_pad($sequence, 6, '0', STR_PAD_LEFT);
    return $prefix . $padded_sequence;
}

/**
 * Includes a template file, passing data to it.
 * @param string $template_name The name of the template file (e.g., 'header', 'footer').
 * @param array $data An associative array of variables to extract into the template scope.
 */
function include_template($template_name, $data = []) {
    extract($data); 
    
    // Check for the template in the main directory first, then in the templates directory
    if (file_exists(__DIR__ . "/../{$template_name}.php")) {
        require_once __DIR__ . "/../{$template_name}.php";
    } elseif (file_exists(__DIR__ . "/../templates/{$template_name}.php")) {
        require_once __DIR__ . "/../templates/{$template_name}.php";
    }
}
?>

