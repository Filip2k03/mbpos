<?php
// public_website/cms/includes/functions.php
// Helper functions for the CMS.

/**
 * Redirects to a specified URL within the CMS.
 * @param string $url The URL to redirect to.
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Checks if a user is logged into the CMS and has the correct role.
 * @return bool True if logged in as Admin or Developer, false otherwise.
 */
function is_cms_admin() {
    return isset($_SESSION['cms_user_id']) && in_array($_SESSION['cms_user_type'], ['ADMIN', 'Developer']);
}

/**
 * Sets a flash message in the session for the CMS.
 * @param string $type Type of message ('success' or 'error').
 * @param string $message The message content.
 */
function flash_cms_message($type, $message) {
    $_SESSION['cms_flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Displays and clears the CMS flash message.
 */
function display_cms_flash_messages() {
    if (isset($_SESSION['cms_flash'])) {
        $type = $_SESSION['cms_flash']['type'];
        $message = $_SESSION['cms_flash']['message'];
        $color = ($type === 'success') ? 'green' : 'red';
        echo "<div class='p-4 mb-4 text-sm text-{$color}-700 bg-{$color}-100 rounded-lg'>{$message}</div>";
        unset($_SESSION['cms_flash']);
    }
}

/**
 * Includes a template file for the CMS.
 * @param string $name The name of the template file.
 * @param array $data Data to be extracted for the template.
 */
function include_template($name, $data = []) {
    extract($data);
    require __DIR__ . "/../templates/{$name}.php";
}
?>
