<?php
// pos/developer_dashboard.php - Dashboard for users with the 'Developer' role in POS V5.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authorization Check: Only Developers can access this page ---
if (!is_logged_in() || !is_developer()) {
    flash_message('error', 'You are not authorized to access this page.');
    redirect('index.php?page=dashboard');
    exit();
}

global $connection, $database;

// Ensure UTF-8 mb4 encoding
mysqli_set_charset($connection, "utf8mb4");

$query_result = null;
$query_error = '';
$affected_rows = null;
$executed_sql = '';

// --- Fetch developer stats ---
$active_maintenance_modes = 0;
$result = mysqli_query($connection, "SELECT COUNT(*) as count FROM maintenance WHERE is_active = 1");
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $active_maintenance_modes = (int)($row['count'] ?? 0);
}

$total_users = 0;
$result_users = mysqli_query($connection, "SELECT COUNT(*) as count FROM users");
if ($result_users) {
    $row_users = mysqli_fetch_assoc($result_users);
    $total_users = (int)($row_users['count'] ?? 0);
}

// --- Handle SQL Query Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sql_query'])) {
    require_csrf_request();
    $sql_query = trim($_POST['sql_query']);
    $executed_sql = $sql_query;

    if (!empty($sql_query)) {
        // For security, only allow SELECT, SHOW, DESCRIBE, and EXPLAIN queries
        if (preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)/i', $sql_query)) {
            $result = mysqli_query($connection, $sql_query);

            if ($result) {
                if ($result instanceof mysqli_result) {
                    $query_result = mysqli_fetch_all($result, MYSQLI_ASSOC);
                }
                $affected_rows = mysqli_affected_rows($connection);
                flash_message('success', 'Query executed successfully.');
            } else {
                $query_error = mysqli_error($connection);
            }
        } else {
            $query_error = "For security reasons, only SELECT, SHOW, DESCRIBE, and EXPLAIN queries are allowed.";
        }
    } else {
        $query_error = "SQL query cannot be empty.";
    }
}

// --- Read recent error log entries ---
$log_content = 'Log file not found or is empty.';
$log_file = __DIR__ . '/error_log';
if (file_exists($log_file) && filesize($log_file) > 0) {
    // Read the last 200 lines for performance
    $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $log_content = implode("\n", array_slice($lines, -200));
}

include_template('header', ['page' => 'developer_dashboard']);
?>

<div class="v5-page">
    <!-- Page Header -->
    <div class="v5-page-head">
        <div class="v5-page-head__copy">
            <span class="v5-kicker" data-i18n="Developer Tools">Developer Tools</span>
            <h1 data-i18n="Dev Center">Dev Center</h1>
            <p data-i18n="System diagnostics, database access, and core configuration">System telemetry, database query workbench, and operational configuration.</p>
        </div>
        <div class="v5-page-actions flex flex-wrap items-center gap-2.5">
            <a href="index.php?page=error_log_viewer" class="btn-secondary btn-sm inline-flex items-center gap-1.5">
                <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span data-i18n="Error Logs">Error Logs</span>
            </a>
            <a href="index.php?page=maintenance" class="btn-ghost btn-sm inline-flex items-center gap-1.5">
                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span data-i18n="Maintenance">Maintenance Mode</span>
            </a>
            <span class="v5-badge bg-rose-50 text-rose-700 border-rose-200 text-xs font-bold inline-flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                <span data-i18n="Developer-Only Area">Developer-Only Area</span>
            </span>
        </div>
    </div>

    <!-- Telemetry & Live DB Metrics Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5 mb-6">
        <!-- Maintenance Mode Status -->
        <div class="v5-panel p-4 flex flex-col justify-between">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full <?= $active_maintenance_modes > 0 ? 'bg-rose-500 animate-pulse' : 'bg-slate-300' ?>"></span>
                <span data-i18n="Maintenance">Maintenance</span>
            </span>
            <div class="mt-2 text-2xl font-black <?= $active_maintenance_modes > 0 ? 'text-rose-600' : 'text-slate-800' ?>">
                <?= (int)$active_maintenance_modes ?>
            </div>
            <span class="text-[11px] text-slate-400"><?= $active_maintenance_modes > 0 ? 'Active lock' : 'Online' ?></span>
        </div>

        <!-- Total Accounts -->
        <div class="v5-panel p-4 flex flex-col justify-between">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                <span data-i18n="Total Users">Total Users</span>
            </span>
            <div class="mt-2 text-2xl font-black text-blue-600">
                <?= (int)$total_users ?>
            </div>
            <span class="text-[11px] text-slate-400" data-i18n="Staff &amp; Operators">Staff &amp; Operators</span>
        </div>

        <!-- Live AJAX Injected DB Status Cards -->
        <div id="db-status-cards" class="contents">
            <div class="v5-panel p-4 col-span-2 sm:col-span-4 flex items-center gap-3 text-slate-400 text-xs font-semibold">
                <svg class="w-4 h-4 animate-spin text-blue-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span data-i18n="Querying Database Metrics...">Querying Database Metrics&hellip;</span>
            </div>
        </div>
    </div>

    <!-- Query Results Panel (shown conditionally if query was run) -->
    <?php if ($query_result !== null || $query_error): ?>
        <section class="v5-panel mb-6">
            <div class="v5-panel__head">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 <?= $query_error ? 'text-rose-600' : 'text-emerald-600' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <h2 class="font-bold text-sm text-slate-800" data-i18n="Query Output">Query Output</h2>
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($query_result !== null): ?>
                        <span class="v5-badge bg-emerald-50 text-emerald-800 border-emerald-200 text-xs font-mono">
                            <?= count($query_result) ?> rows
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="v5-panel__body">
                <?php if ($query_error): ?>
                    <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-start gap-3">
                        <svg class="w-4 h-4 text-rose-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div class="font-mono leading-relaxed"><?= e($query_error) ?></div>
                    </div>
                <?php elseif (empty($query_result)): ?>
                    <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center gap-2.5 font-semibold">
                        <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Query executed successfully. <strong><?= (int)($affected_rows ?? 0) ?></strong> rows affected. (Empty result set)</span>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="v5-table text-xs whitespace-nowrap w-full">
                            <thead>
                                <tr>
                                    <?php foreach (array_keys($query_result[0]) as $header): ?>
                                        <th class="font-mono uppercase text-[11px]"><?= e($header) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($query_result as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $cell): ?>
                                            <td class="font-mono text-slate-700"><?= e($cell ?? 'NULL') ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Two-Column Workbench: SQL Terminal + Diagnostic Stream -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6 items-start">

        <!-- Left: SQL Query Console (7 cols) -->
        <div class="lg:col-span-7">
            <section class="v5-panel flex flex-col h-full">
                <div class="v5-panel__head">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                        <h2 class="font-bold text-sm text-slate-800" data-i18n="Database Console">SQL Terminal Workbench</h2>
                    </div>
                    <span class="v5-badge bg-slate-100 text-slate-600 border-slate-200 text-[11px] font-mono uppercase" data-i18n="Read-Only">Read-Only (SELECT/SHOW)</span>
                </div>

                <div class="v5-panel__body space-y-4">
                    <!-- Quick SQL Presets -->
                    <div class="space-y-1.5">
                        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider" data-i18n="Fast Presets">Fast Query Presets</div>
                        <div class="flex flex-wrap gap-1.5" id="sql-presets">
                            <button type="button" onclick="setQuery('SELECT id, voucher_code, status, sender_name, receiver_name, created_at FROM vouchers ORDER BY id DESC LIMIT 10;')" class="btn-ghost btn-xs text-[11px] py-1 px-2 border border-slate-200 rounded-lg hover:border-indigo-300 hover:text-indigo-600 font-mono">
                                Recent Vouchers
                            </button>
                            <button type="button" onclick="setQuery('SELECT status, COUNT(*) AS count FROM vouchers GROUP BY status;')" class="btn-ghost btn-xs text-[11px] py-1 px-2 border border-slate-200 rounded-lg hover:border-indigo-300 hover:text-indigo-600 font-mono">
                                Status Counts
                            </button>
                            <button type="button" onclick="setQuery('SELECT id, username, user_type, email, created_at FROM users ORDER BY id ASC;')" class="btn-ghost btn-xs text-[11px] py-1 px-2 border border-slate-200 rounded-lg hover:border-indigo-300 hover:text-indigo-600 font-mono">
                                Users
                            </button>
                            <button type="button" onclick="setQuery('SHOW TABLE STATUS;')" class="btn-ghost btn-xs text-[11px] py-1 px-2 border border-slate-200 rounded-lg hover:border-indigo-300 hover:text-indigo-600 font-mono">
                                Table Status
                            </button>
                            <button type="button" onclick="setQuery('SELECT * FROM branches ORDER BY id ASC;')" class="btn-ghost btn-xs text-[11px] py-1 px-2 border border-slate-200 rounded-lg hover:border-indigo-300 hover:text-indigo-600 font-mono">
                                Branches
                            </button>
                        </div>
                    </div>

                    <!-- SQL Input Form -->
                    <form action="index.php?page=developer_dashboard" method="POST" id="sql-query-form" class="space-y-3">
                        <?= csrf_input() ?>
                        <div class="relative rounded-xl overflow-hidden border border-slate-800 bg-slate-950 shadow-inner">
                            <div class="bg-slate-900 px-3.5 py-1.5 flex items-center justify-between border-b border-slate-800">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-rose-500/80"></span>
                                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500/80"></span>
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500/80"></span>
                                </div>
                                <span class="font-mono text-[11px] text-slate-400">mysql &gt;</span>
                            </div>
                            <textarea
                                id="sql_query"
                                name="sql_query"
                                rows="7"
                                required
                                placeholder="mysql> SELECT * FROM vouchers LIMIT 10;"
                                class="w-full bg-slate-950 text-emerald-400 font-mono text-xs p-3.5 outline-none border-0 resize-y leading-relaxed selection:bg-emerald-900 selection:text-white"
                            ><?= e($executed_sql) ?></textarea>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                            <span class="text-[11px] text-slate-400 font-medium" data-i18n="Press Ctrl+Enter to execute">Press <kbd class="px-1.5 py-0.5 bg-slate-100 border border-slate-300 rounded text-slate-600 font-mono text-[10px]">Ctrl+Enter</kbd> to execute</span>
                            <button type="submit" class="btn-primary px-5 py-2 rounded-xl text-xs font-bold inline-flex items-center gap-1.5 shadow-md shadow-blue-600/20">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span data-i18n="Execute Query">Execute Query</span>
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <!-- Right: Diagnostic Error Log Stream (5 cols) -->
        <div class="lg:col-span-5">
            <section class="v5-panel flex flex-col h-full">
                <div class="v5-panel__head">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <h2 class="font-bold text-sm text-slate-800" data-i18n="System error_log">Error Stream (Tail 200)</h2>
                    </div>
                    <a href="index.php?page=error_log_viewer" class="btn-ghost btn-xs text-blue-600 inline-flex items-center gap-1 font-semibold" data-i18n="Full Viewer">
                        <span data-i18n="Full Viewer">Full Viewer</span>
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>

                <div class="v5-panel__body p-0">
                    <div class="bg-slate-900 px-3.5 py-1.5 flex items-center justify-between text-[11px] font-mono text-slate-400 border-b border-slate-800">
                        <span>user@mbpos:~$ tail -n 200 error_log</span>
                        <div class="flex items-center gap-1.5 text-xs text-slate-400">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span>Live</span>
                        </div>
                    </div>
                    <div class="bg-slate-950 text-slate-300 font-mono text-[11px] p-3.5 overflow-y-auto h-[260px] leading-relaxed selection:bg-blue-900 selection:text-white" id="log-terminal-mini">
                        <pre class="whitespace-pre-wrap break-all m-0" id="log-pre"><code><?= e($log_content) ?></code></pre>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <!-- Core Configuration Shortcuts Grid -->
    <section class="v5-panel">
        <div class="v5-panel__head">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
                <h2 class="font-bold text-sm text-slate-800" data-i18n="Core Configuration Shortcuts">Core Configuration Shortcuts</h2>
            </div>
            <span class="text-xs text-slate-400" data-i18n="Operational Admin Nodes">Operational Admin Nodes</span>
        </div>

        <div class="v5-panel__body">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <a href="index.php?page=maintenance" class="p-3.5 rounded-xl border border-slate-200 bg-white hover:border-rose-300 hover:shadow-sm transition-all flex flex-col gap-2 group">
                    <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div>
                        <div class="font-bold text-xs text-slate-800" data-i18n="Maintenance">Maintenance</div>
                        <div class="text-[10px] text-slate-400 mt-0.5" data-i18n="Toggle site offline mode">Site offline mode</div>
                    </div>
                </a>

                <a href="index.php?page=branches" class="p-3.5 rounded-xl border border-slate-200 bg-white hover:border-indigo-300 hover:shadow-sm transition-all flex flex-col gap-2 group">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </div>
                    <div>
                        <div class="font-bold text-xs text-slate-800" data-i18n="Branch Logic">Branch Logic</div>
                        <div class="text-[10px] text-slate-400 mt-0.5" data-i18n="Manage operational nodes">Operational nodes</div>
                    </div>
                </a>

                <a href="index.php?page=register" class="p-3.5 rounded-xl border border-slate-200 bg-white hover:border-emerald-300 hover:shadow-sm transition-all flex flex-col gap-2 group">
                    <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    </div>
                    <div>
                        <div class="font-bold text-xs text-slate-800" data-i18n="Identity Hub">Identity Hub</div>
                        <div class="text-[10px] text-slate-400 mt-0.5" data-i18n="Provision new accounts">User accounts</div>
                    </div>
                </a>

                <a href="index.php?page=currencies" class="p-3.5 rounded-xl border border-slate-200 bg-white hover:border-amber-300 hover:shadow-sm transition-all flex flex-col gap-2 group">
                    <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <div class="font-bold text-xs text-slate-800" data-i18n="Currencies">Currencies</div>
                        <div class="text-[10px] text-slate-400 mt-0.5" data-i18n="Exchange &amp; rates">Exchange &amp; rates</div>
                    </div>
                </a>

                <a href="index.php?page=delivery_types" class="p-3.5 rounded-xl border border-slate-200 bg-white hover:border-cyan-300 hover:shadow-sm transition-all flex flex-col gap-2 group">
                    <div class="w-8 h-8 rounded-lg bg-cyan-50 text-cyan-600 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <div>
                        <div class="font-bold text-xs text-slate-800" data-i18n="Delivery Types">Delivery Types</div>
                        <div class="text-[10px] text-slate-400 mt-0.5" data-i18n="Logistics tiers">Logistics tiers</div>
                    </div>
                </a>

                <a href="index.php?page=error_log_viewer" class="p-3.5 rounded-xl border border-slate-200 bg-white hover:border-slate-400 hover:shadow-sm transition-all flex flex-col gap-2 group">
                    <div class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div>
                        <div class="font-bold text-xs text-slate-800" data-i18n="Full Debug Log">Full Debug Log</div>
                        <div class="text-[10px] text-slate-400 mt-0.5" data-i18n="Trace system exceptions">Exception stream</div>
                    </div>
                </a>
            </div>
        </div>
    </section>
</div>

<script>
function setQuery(sql) {
    const textarea = document.getElementById('sql_query');
    if (textarea) {
        textarea.value = sql;
        textarea.focus();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Auto-scroll the terminal log to the bottom
    const logTerminal = document.getElementById('log-terminal-mini');
    if (logTerminal) {
        logTerminal.scrollTop = logTerminal.scrollHeight;
    }

    // Keyboard shortcut: Ctrl+Enter or Cmd+Enter to execute query
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            const sqlForm = document.getElementById('sql-query-form');
            if (sqlForm) {
                e.preventDefault();
                sqlForm.submit();
            }
        }
    });

    // Fetch and display database status via AJAX
    const statusRequest = window.mbposFetch
        ? window.mbposFetch('index.php?page=ajax_db_status', { timeout: 6000 })
        : fetch('index.php?page=ajax_db_status', { cache: 'no-store', credentials: 'same-origin' });

    statusRequest
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('db-status-cards');
            if (!container) return;
            container.innerHTML = `
                <div class="v5-panel p-4 flex flex-col justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                        <span data-i18n="DB Size">DB Size</span>
                    </span>
                    <div class="mt-2 text-2xl font-black text-purple-600">${data.size}</div>
                    <span class="text-[11px] text-slate-400 font-mono">MB Storage</span>
                </div>
                <div class="v5-panel p-4 flex flex-col justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-cyan-500"></span>
                        <span data-i18n="Tables">Tables</span>
                    </span>
                    <div class="mt-2 text-2xl font-black text-cyan-600">${data.tables}</div>
                    <span class="text-[11px] text-slate-400 font-mono">Schema Entities</span>
                </div>
                <div class="v5-panel p-4 flex flex-col justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span data-i18n="Version">MySQL Version</span>
                    </span>
                    <div class="mt-2 text-lg font-black text-emerald-600 font-mono truncate" title="${data.version}">${data.version}</div>
                    <span class="text-[11px] text-slate-400 font-mono">Engine Version</span>
                </div>
                <div class="v5-panel p-4 flex flex-col justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span data-i18n="Cache Layer">Cache Layer</span>
                    </span>
                    <div class="mt-2 text-sm font-black text-emerald-700">${data.cache}</div>
                    <span class="text-[11px] text-slate-400 font-mono">${data.cache_latency_ms === null ? 'Fail-open active' : data.cache_latency_ms + ' ms'}</span>
                </div>
            `;
            if (typeof window.mbposApplyLanguage === 'function') {
                window.mbposApplyLanguage(document.documentElement.dataset.language || 'en');
            }
        })
        .catch(() => {
            const container = document.getElementById('db-status-cards');
            if (!container) return;
            container.innerHTML = `
                <div class="v5-panel p-4 col-span-2 sm:col-span-4 flex items-center gap-2.5 text-rose-700 text-xs font-semibold bg-rose-50 border-rose-200">
                    <svg class="w-4 h-4 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span data-i18n="Failed to fetch live database metrics.">Failed to fetch live database metrics.</span>
                </div>
            `;
            if (typeof window.mbposApplyLanguage === 'function') {
                window.mbposApplyLanguage(document.documentElement.dataset.language || 'en');
            }
        });
});
</script>

<?php include_template('footer'); ?>
