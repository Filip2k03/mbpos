<?php
// pos/developer_dashboard.php - Dashboard for users with the 'Developer' role.

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

global $connection,$database;

$query_result = null;
$query_error = '';
$affected_rows = null;

// --- Fetch some developer-relevant stats (examples) ---
$active_maintenance_modes = 0;
$result = mysqli_query($connection, "SELECT COUNT(*) as count FROM maintenance WHERE is_active = 1");
if ($result) {
    $active_maintenance_modes = mysqli_fetch_assoc($result)['count'];
}

$total_users = 0;
$result_users = mysqli_query($connection, "SELECT COUNT(*) as count FROM users");
if ($result_users) {
    $total_users = mysqli_fetch_assoc($result_users)['count'];
}

// --- Handle SQL Query Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sql_query'])) {
    $sql_query = trim($_POST['sql_query']);

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

<!-- V3 Liquid UI Wrapper -->
<div class="relative min-h-[85vh] bg-slate-50/50 p-4 sm:p-8 overflow-hidden font-sans">
    
    <!-- Ambient Background Glows -->
    <div class="absolute top-[0%] left-[20%] w-[600px] h-[600px] bg-indigo-500/10 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-[10%] right-[10%] w-[500px] h-[500px] bg-purple-500/10 rounded-full blur-[120px] pointer-events-none"></div>

    <div class="max-w-7xl mx-auto relative z-10">
        
        <!-- Header -->
        <div class="flex items-center justify-between mb-8 gap-5">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-gradient-to-br from-indigo-800 to-slate-900 rounded-2xl flex items-center justify-center shadow-lg shadow-slate-900/30 text-white transform -rotate-3 hover:rotate-0 transition-transform duration-300 border border-slate-700">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                </div>
                <div>
                    <h1 class="text-3xl font-extrabold bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent tracking-tight">Dev Center</h1>
                    <p class="text-sm font-medium text-slate-500">System diagnostics, database access, and core configuration</p>
                </div>
            </div>
        </div>

        <!-- TOP STATS: PHP & AJAX Injected -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
            <!-- PHP Driven Stats -->
            <div class="bg-white/80 backdrop-blur-xl rounded-2xl p-5 border border-white/60 shadow-[0_4px_20px_rgb(0,0,0,0.03)] flex flex-col justify-center transition-all hover:shadow-md hover:-translate-y-0.5">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1 flex items-center gap-1.5"><div class="w-2 h-2 rounded-full <?= $active_maintenance_modes > 0 ? 'bg-red-500 animate-pulse' : 'bg-slate-300' ?>"></div> Maintenance</span>
                <span class="text-2xl font-extrabold <?= $active_maintenance_modes > 0 ? 'text-red-600' : 'text-slate-700' ?>"><?= $active_maintenance_modes ?></span>
            </div>
            <div class="bg-white/80 backdrop-blur-xl rounded-2xl p-5 border border-white/60 shadow-[0_4px_20px_rgb(0,0,0,0.03)] flex flex-col justify-center transition-all hover:shadow-md hover:-translate-y-0.5">
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1 flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-blue-500"></div> Total Users</span>
                <span class="text-2xl font-extrabold text-blue-600"><?= $total_users ?></span>
            </div>
            <!-- AJAX Driven Stats (Injected via JS at bottom) -->
            <div id="db-status-cards" class="col-span-2 md:col-span-1 lg:col-span-4 grid grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Loader Placeholder -->
                <div class="col-span-full text-sm font-bold text-slate-400 py-5 flex items-center gap-2 animate-pulse">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Querying Database Metrics...
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            
            <!-- LEFT: SQL Query Runner (Dark Mode Terminal Aesthetic) -->
            <div class="bg-slate-900 rounded-[2rem] shadow-2xl border border-slate-700 overflow-hidden flex flex-col relative group">
                <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-500/10 rounded-bl-full pointer-events-none transition-transform group-hover:scale-110"></div>
                
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-900/50">
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                        Database Console
                    </h2>
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest bg-slate-800 px-2 py-1 rounded">Read-Only</span>
                </div>
                
                <div class="p-6 flex-1 flex flex-col">
                    <form action="index.php?page=developer_dashboard" method="POST" class="flex flex-col h-full">
                        <div class="flex-1 relative">
                            <label for="sql_query" class="sr-only">SQL Query</label>
                            <textarea id="sql_query" name="sql_query" class="w-full h-48 bg-[#0f172a] text-emerald-400 font-mono text-sm p-4 rounded-xl border border-slate-700 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 shadow-inner custom-scrollbar resize-none placeholder-slate-600 transition-colors" placeholder="mysql> SELECT * FROM users LIMIT 10;" required></textarea>
                            <div class="absolute top-3 right-3 flex gap-1.5 pointer-events-none">
                                <div class="w-2.5 h-2.5 rounded-full bg-red-500/50"></div>
                                <div class="w-2.5 h-2.5 rounded-full bg-yellow-500/50"></div>
                                <div class="w-2.5 h-2.5 rounded-full bg-green-500/50"></div>
                            </div>
                        </div>
                        <div class="mt-4 flex justify-end">
                            <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white py-2.5 px-6 rounded-xl font-bold text-sm shadow-[0_0_15px_rgba(79,70,229,0.4)] transition-all flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Execute Query
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- RIGHT: System Log Viewer (Terminal Aesthetic) -->
            <div class="bg-slate-900 rounded-[2rem] shadow-2xl border border-slate-700 overflow-hidden flex flex-col relative group">
                <div class="absolute top-0 right-0 w-32 h-32 bg-rose-500/10 rounded-bl-full pointer-events-none transition-transform group-hover:scale-110"></div>
                
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-900/50">
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        System error_log
                    </h2>
                    <a href="index.php?page=error_log_viewer" class="text-[10px] font-bold text-indigo-400 hover:text-indigo-300 uppercase tracking-widest bg-indigo-900/30 hover:bg-indigo-900/50 px-3 py-1 rounded transition-colors border border-indigo-500/30">View Full Log</a>
                </div>
                
                <div class="p-6 flex-1 flex flex-col">
                    <div class="bg-[#0f172a] rounded-xl border border-slate-700 shadow-inner h-48 sm:h-56 relative">
                        <div class="absolute top-0 left-0 w-full h-8 bg-slate-800/50 rounded-t-xl border-b border-slate-700 flex items-center px-4 gap-2">
                            <span class="text-slate-400 text-xs font-mono">user@mbpos:~$ tail -n 200 error_log</span>
                        </div>
                        <pre class="w-full h-full pt-10 pb-4 px-4 overflow-y-auto custom-scrollbar text-gray-300 text-xs font-mono whitespace-pre-wrap"><code><?= htmlspecialchars($log_content) ?></code></pre>
                    </div>
                </div>
            </div>
            
        </div>

        <!-- Dynamic Query Results -->
        <?php if ($query_result !== null || $query_error): ?>
        <div class="bg-white/80 backdrop-blur-2xl p-6 sm:p-8 rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-white/60 mb-8 animate-fadeInDown">
            <h3 class="text-xl font-bold text-slate-800 mb-6 flex items-center gap-2">
                <svg class="w-6 h-6 <?= $query_error ? 'text-red-500' : 'text-emerald-500' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Query Output
            </h3>
            
            <?php if ($query_error): ?>
                <div class="bg-red-50/50 border border-red-200 text-red-700 p-5 rounded-2xl flex items-start gap-3">
                    <svg class="w-6 h-6 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div class="font-mono text-sm leading-relaxed"><?= htmlspecialchars($query_error) ?></div>
                </div>
            <?php elseif (empty($query_result)): ?>
                <div class="bg-emerald-50/50 border border-emerald-200 text-emerald-700 p-5 rounded-2xl flex items-center gap-3">
                    <svg class="w-6 h-6 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <div class="font-medium text-sm">Query executed successfully. <strong><?= $affected_rows ?? 0 ?></strong> rows affected.</div>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto w-full custom-scrollbar pb-3">
                    <table class="w-full text-left border-collapse whitespace-nowrap text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200">
                                <?php foreach (array_keys($query_result[0]) as $header): ?>
                                    <th class="py-3 px-4 font-bold text-slate-500 uppercase tracking-wider"><?= htmlspecialchars($header) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($query_result as $row): ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <?php foreach ($row as $cell): ?>
                                        <td class="py-3 px-4 text-slate-700 font-mono"><?= htmlspecialchars($cell ?? 'NULL') ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Quick Action / Navigation Cards -->
        <div class="mt-8 pt-8 border-t border-slate-200/60 relative z-10">
            <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-indigo-500"></span> Core Configuration Shortcuts
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 group">
                <a href="index.php?page=maintenance" class="action-card bg-white/70 backdrop-blur-md border border-white/60 hover:border-red-200 hover:bg-white p-5 rounded-2xl shadow-sm transition-all hover:shadow-[0_8px_20px_-5px_rgba(239,68,68,0.2)] hover:-translate-y-1">
                    <div class="w-12 h-12 bg-red-50 text-red-500 rounded-xl flex items-center justify-center shrink-0 mb-4 transition-transform group-hover:scale-110"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg></div>
                    <span class="font-bold text-slate-800 block text-lg">Maintenance</span>
                    <span class="text-xs text-slate-500 font-medium">Toggle site offline mode</span>
                </a>
                
                <a href="index.php?page=branches" class="action-card bg-white/70 backdrop-blur-md border border-white/60 hover:border-indigo-200 hover:bg-white p-5 rounded-2xl shadow-sm transition-all hover:shadow-[0_8px_20px_-5px_rgba(99,102,241,0.2)] hover:-translate-y-1">
                    <div class="w-12 h-12 bg-indigo-50 text-indigo-500 rounded-xl flex items-center justify-center shrink-0 mb-4 transition-transform group-hover:scale-110"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg></div>
                    <span class="font-bold text-slate-800 block text-lg">Branch Logic</span>
                    <span class="text-xs text-slate-500 font-medium">Manage operational nodes</span>
                </a>
                
                <a href="index.php?page=register" class="action-card bg-white/70 backdrop-blur-md border border-white/60 hover:border-emerald-200 hover:bg-white p-5 rounded-2xl shadow-sm transition-all hover:shadow-[0_8px_20px_-5px_rgba(16,185,129,0.2)] hover:-translate-y-1">
                    <div class="w-12 h-12 bg-emerald-50 text-emerald-500 rounded-xl flex items-center justify-center shrink-0 mb-4 transition-transform group-hover:scale-110"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg></div>
                    <span class="font-bold text-slate-800 block text-lg">Identity Hub</span>
                    <span class="text-xs text-slate-500 font-medium">Provision new accounts</span>
                </a>
                
                <a href="index.php?page=error_log_viewer" class="action-card bg-white/70 backdrop-blur-md border border-white/60 hover:border-slate-300 hover:bg-white p-5 rounded-2xl shadow-sm transition-all hover:shadow-[0_8px_20px_-5px_rgba(148,163,184,0.2)] hover:-translate-y-1">
                    <div class="w-12 h-12 bg-slate-100 text-slate-600 rounded-xl flex items-center justify-center shrink-0 mb-4 transition-transform group-hover:scale-110"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></div>
                    <span class="font-bold text-slate-800 block text-lg">Full Debug Log</span>
                    <span class="text-xs text-slate-500 font-medium">Trace system exceptions</span>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    /* Sleek scrollbar for the dark terminal / table */
    .custom-scrollbar::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent; 
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(148, 163, 184, 0.3); 
        border-radius: 999px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: rgba(148, 163, 184, 0.6); 
    }
    
    /* V3 Fade In Animation */
    @keyframes fadeInDown {
        from { opacity: 0; transform: translateY(-15px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fadeInDown {
        animation: fadeInDown 0.4s ease-out forwards;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Auto-scroll the terminal log to the bottom
    const logContainer = document.querySelector('pre.custom-scrollbar');
    if (logContainer) {
        logContainer.scrollTop = logContainer.scrollHeight;
    }

    // Fetch and display database status via AJAX, injecting V3 Premium Cards
    fetch('index.php?page=ajax_db_status')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('db-status-cards');
            container.innerHTML = `
                <div class="bg-white/80 backdrop-blur-xl rounded-2xl p-5 border border-white/60 shadow-[0_4px_20px_rgb(0,0,0,0.03)] flex flex-col justify-center transition-all hover:shadow-md hover:-translate-y-0.5">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1 flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-purple-500"></div> DB Size</span>
                    <span class="text-2xl font-extrabold text-purple-600">${data.size}</span>
                </div>
                <div class="bg-white/80 backdrop-blur-xl rounded-2xl p-5 border border-white/60 shadow-[0_4px_20px_rgb(0,0,0,0.03)] flex flex-col justify-center transition-all hover:shadow-md hover:-translate-y-0.5">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1 flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-cyan-500"></div> Tables</span>
                    <span class="text-2xl font-extrabold text-cyan-600">${data.tables}</span>
                </div>
                <div class="bg-white/80 backdrop-blur-xl rounded-2xl p-5 border border-white/60 shadow-[0_4px_20px_rgb(0,0,0,0.03)] flex flex-col justify-center transition-all hover:shadow-md hover:-translate-y-0.5">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1 flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-emerald-500"></div> Version</span>
                    <span class="text-2xl font-extrabold text-emerald-600 truncate">${data.version}</span>
                </div>
                <div class="bg-white/80 backdrop-blur-xl rounded-2xl p-5 border border-white/60 shadow-[0_4px_20px_rgb(0,0,0,0.03)] flex flex-col justify-center transition-all hover:shadow-md hover:-translate-y-0.5">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1 flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div> Connectivity</span>
                    <span class="text-2xl font-extrabold text-green-500">Online</span>
                </div>
            `;
        })
        .catch(error => {
            const container = document.getElementById('db-status-cards');
            container.innerHTML = `
                <div class="col-span-full bg-red-50/80 border border-red-200 text-red-600 p-4 rounded-xl text-sm font-bold flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Failed to fetch live database metrics.
                </div>
            `;
        });
});
</script>

<?php include_template('footer'); ?>