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

<div class="v5-page">
    <!-- Header Section -->
    <div class="v5-page-head">
        <div class="v5-page-head__copy">
            <span class="v5-kicker" data-i18n="Developer Tools">Developer Tools</span>
            <h1 data-i18n="Error Logs">System Diagnostic Logs</h1>
            <p data-i18n="System diagnostics, database access, and core configuration">Live server runtime diagnostic stream and telemetry.</p>
        </div>
        <div class="v5-page-actions flex flex-wrap items-center gap-2.5">
            <a href="index.php?page=developer_dashboard" class="btn-ghost btn-sm inline-flex items-center gap-1.5" data-i18n="Dev Center">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span data-i18n="Dev Center">Dev Center</span>
            </a>
            <button type="button" onclick="window.location.reload();" class="btn-secondary btn-sm inline-flex items-center gap-1.5" title="Refresh Log Stream">
                <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span>Refresh</span>
            </button>
            <form method="POST" action="index.php?page=clear_log" class="inline" onsubmit="return confirm('Clear the error log? This action cannot be undone.');">
                <?= csrf_input() ?>
                <button type="submit" class="v5-btn-danger btn-sm inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    <span>Clear Log Buffer</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Terminal Console Panel -->
    <section class="v5-panel">
        <div class="v5-panel__head">
            <div class="flex items-center gap-2.5">
                <div class="flex items-center gap-1.5 mr-2">
                    <span class="w-3 h-3 rounded-full bg-rose-400"></span>
                    <span class="w-3 h-3 rounded-full bg-amber-400"></span>
                    <span class="w-3 h-3 rounded-full bg-emerald-400"></span>
                </div>
                <span class="font-mono text-xs font-bold text-slate-700">/pos/error_log</span>
                <span class="text-slate-300">•</span>
                <span class="text-xs text-slate-500 font-mono">Tail 500 lines</span>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-1.5 text-xs font-mono text-slate-500">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Live Stream</span>
                </div>
                <input type="search" id="log-search-input" class="v5-input text-xs py-1 px-2.5 rounded-lg border-slate-200" placeholder="Filter log entries..." style="min-height: 2rem; max-width: 180px;">
            </div>
        </div>

        <div class="v5-panel__body p-0">
            <div class="bg-slate-950 text-emerald-400 font-mono text-xs p-4 sm:p-5 rounded-b-2xl overflow-auto h-[65vh] shadow-inner leading-relaxed border-t border-slate-900 selection:bg-emerald-900 selection:text-white" id="log-terminal">
                <pre class="whitespace-pre-wrap break-all m-0" id="log-pre"><code><?= htmlspecialchars($log_content, ENT_QUOTES, 'UTF-8') ?></code></pre>
            </div>
        </div>
    </section>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const term = document.getElementById('log-terminal');
    if (term) {
        term.scrollTop = term.scrollHeight;
    }

    const searchInput = document.getElementById('log-search-input');
    const logPre = document.getElementById('log-pre');
    if (searchInput && logPre) {
        const originalText = logPre.textContent;
        const allLines = originalText.split('\n');

        searchInput.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            if (!query) {
                logPre.textContent = originalText;
                return;
            }
            const filtered = allLines.filter(line => line.toLowerCase().includes(query));
            logPre.textContent = filtered.length > 0 ? filtered.join('\n') : '-- No log lines matching "' + query + '" --';
        });
    }
});
</script>

<?php include_template('footer'); ?>
