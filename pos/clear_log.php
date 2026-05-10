<?php
// pos/clear_log.php - Clears the system error log.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authorization ---
if (!is_logged_in() || !is_developer()) {
    flash_message('error', 'You are not authorized to perform this action.');
    redirect('index.php?page=dashboard');
}

$log_file = __DIR__ . '/error_log';

if (file_exists($log_file)) {
    // Open the file in write mode to truncate it
    $handle = fopen($log_file, 'w');
    if ($handle) {
        fclose($handle);
        flash_message('success', 'System error log has been cleared.');
    } else {
        flash_message('error', 'Could not open the log file to clear it.');
    }
} else {
    flash_message('info', 'Log file does not exist, nothing to clear.');
}

// Redirect back to the new log viewer page
redirect('index.php?page=error_log_viewer');
?>

