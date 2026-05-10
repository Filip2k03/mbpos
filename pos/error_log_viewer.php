<?php
// pos/error_log_viewer.php - A dedicated page for viewing the system error log.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authorization ---
if (!is_logged_in() || !is_developer()) {
    flash_message('error', 'You are not authorized to access this page.');
    redirect('index.php?page=dashboard');
}

// --- Read recent error log entries ---
$log_content = 'Log file not found or is empty.';
$log_file = __DIR__ . '/error_log'; 
if (file_exists($log_file) && filesize($log_file) > 0) {
    // Read the last 500 lines for performance
    $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $log_content = implode("\n", array_slice($lines, -500));
}

include_template('header', ['page' => 'error_log_viewer']);
?>

<div class="container mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">System Error Log</h1>
        <a href="index.php?page=clear_log" class="btn-danger" onclick="return confirm('Are you sure you want to clear the error log? This action cannot be undone.')">
            Clear Log File
        </a>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-md">
        <div class="bg-gray-800 text-white font-mono text-sm p-4 rounded-lg overflow-auto h-[60vh]">
            <pre><code><?= htmlspecialchars($log_content) ?></code></pre>
        </div>
    </div>
</div>

<?php include_template('footer'); ?>

