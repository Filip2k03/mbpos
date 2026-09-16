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

<!-- V5 System Diagnostic Log Viewer -->
<div class="relative min-h-[85vh] p-4 sm:p-8 font-sans">
    <div class="max-w-7xl mx-auto relative z-10">
        
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-5 animate-fadeInDown">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-gradient-to-br from-slate-800 to-slate-900 rounded-2xl flex items-center justify-center shadow-lg text-emerald-400 font-mono text-base border border-slate-700">
                    &gt;_
                </div>
                <div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold bg-gradient-to-r from-slate-900 to-indigo-900 bg-clip-text text-transparent tracking-tight" data-i18n="Error Logs">
                        System Diagnostic Logs
                    </h1>
                    <p class="text-sm font-medium text-slate-500 mt-0.5">Live server runtime diagnostic stream and telemetry.</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a href="index.php?page=developer_dashboard" class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white/70 text-slate-700 text-xs font-bold hover:bg-slate-50 transition-all">
                    ← <span data-i18n="Dev Center">Dev Center</span>
                </a>
                <form method="POST" action="index.php?page=clear_log" class="inline" onsubmit="return confirm('Clear the error log? This action cannot be undone.');">
                    <?= csrf_input() ?>
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-bold text-xs shadow-lg shadow-rose-500/25 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    <span>Clear Log Buffer</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Terminal Card -->
        <div class="v5-glass-card p-6 sm:p-8 shadow-2xl rounded-2xl overflow-hidden animate-fadeInDown" style="animation-delay: 0.1s;">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-rose-400"></span>
                    <span class="w-3 h-3 rounded-full bg-amber-400"></span>
                    <span class="w-3 h-3 rounded-full bg-emerald-400"></span>
                    <span class="ml-2 font-mono text-xs font-bold text-slate-500">/pos/error_log (Tail 500 lines)</span>
                </div>
                <div class="flex items-center gap-2 text-xs font-mono text-slate-400">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Live Stream Active
                </div>
            </div>

            <div class="bg-slate-950 text-emerald-400 font-mono text-xs p-5 rounded-xl overflow-auto h-[62vh] shadow-inner leading-relaxed border border-slate-800 selection:bg-emerald-800 selection:text-white">
                <pre class="whitespace-pre-wrap break-all"><code><?= htmlspecialchars($log_content) ?></code></pre>
            </div>
        </div>
    </div>
</div>

<?php include_template('footer'); ?>

