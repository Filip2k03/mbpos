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
    $posted_token = (string)($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
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

/**
 * Renders an inline SVG icon from the unified V5 design system.
 * @param string $name Icon identifier.
 * @param string $class Additional CSS classes.
 * @return string SVG markup.
 */
function mbpos_icon($name, $class = 'w-5 h-5') {
    $class_attr = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
    $icons = [
        'dashboard' => '<svg class="' . $class_attr . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
        'voucher_create' => '<svg class="' . $class_attr . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>',
        'stock_list' => '<svg class="' . $class_attr . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
        'voucher_list' => '<svg class="' . $class_attr . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
        'expenses' => '<svg class="' . $class_attr . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>',
        'other_income' => '<svg class="' . $class_attr . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
        'profit_loss' => '<svg class="' . $class_attr . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
        'branches' => '<svg class="' . $class_attr . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3v12"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 0 1-9 9"/></svg>',
        'currencies' => '<svg class="' . $class_attr . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><line x1="12" y1="6" x2="12" y2="8"/><line x1="12" y1="16" x2="12" y2="18"/></svg>',
        'delivery_types' => '<svg class="' . $class_attr . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
        'item_types' => '<svg class="' . $class_attr . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
        'register' => '<svg class="' . $class_attr . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'admin_dashboard' => '<svg class="' . $class_attr . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
        'developer_dashboard' => '<svg class="' . $class_attr . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 16 4-4-4-4"/><path d="m6 8-4 4 4 4"/><path d="m14.5 4-5 16"/></svg>',
        'maintenance' => '<svg class="' . $class_attr . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        'bell' => '<svg class="' . $class_attr . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
        'logout' => '<svg class="' . $class_attr . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
        'menu' => '<svg class="' . $class_attr . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>',
        'search' => '<svg class="' . $class_attr . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
    ];
    return $icons[$name] ?? '<span class="' . $class_attr . '">•</span>';
}

/**
 * Standardized date and time formatting for Myanmar operations (GMT+6:30).
 * @param string|null $datetime Database timestamp (e.g. YYYY-MM-DD HH:MM:SS)
 * @param string $format Output format ('full', 'date', 'time', 'relative', 'compact')
 * @return string Formatted string
 */
function format_datetime_myanmar($datetime, $format = 'full') {
    if (empty($datetime)) return 'N/A';
    $ts = strtotime($datetime);
    if (!$ts) return 'N/A';

    return match($format) {
        'date' => date('Y-m-d', $ts),
        'time' => date('h:i A', $ts),
        'compact' => date('d M Y, h:i A', $ts),
        'relative' => (function() use ($ts) {
            $diff = time() - $ts;
            if ($diff < 60) return 'Just now';
            if ($diff < 3600) return floor($diff / 60) . ' mins ago';
            if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
            if ($diff < 172800) return 'Yesterday';
            return date('M d, Y', $ts);
        })(),
        default => date('F j, Y · h:i A', $ts) . ' (GMT+6:30)'
    };
}
?>
