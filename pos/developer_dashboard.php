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

<div class="v5-page-content">
    <div class="v5-page-header">
        <div>
            <span class="v5-kicker" data-i18n="dev.kicker">Developer Tools</span>
            <h1 class="v5-page-title" data-i18n="dev.title">Dev Center</h1>
            <p class="v5-page-subtitle" data-i18n="dev.subtitle">System diagnostics, database access, and core configuration</p>
        </div>
        <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
            <span class="v5-badge v5-badge-danger" style="font-size:.7rem;letter-spacing:.08em;" data-i18n="dev.restricted_badge">
                &#128274; Developer-Only Area
            </span>
        </div>
    </div>

    <!-- TOP STATS: PHP & AJAX Injected -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:1rem;margin-bottom:1.5rem;">

        <!-- Maintenance Count -->
        <div class="v5-glass-card" style="padding:1.1rem 1.25rem;display:flex;flex-direction:column;gap:.25rem;">
            <span style="font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:<?= $active_maintenance_modes > 0 ? '#ef4444' : '#94a3b8' ?>;display:flex;align-items:center;gap:.4rem;">
                <span style="width:8px;height:8px;border-radius:50%;background:<?= $active_maintenance_modes > 0 ? '#ef4444' : '#cbd5e1' ?>;<?= $active_maintenance_modes > 0 ? 'animation:v5-pulse 1.5s infinite;' : '' ?>"></span>
                <span data-i18n="dev.stat_maintenance">Maintenance</span>
            </span>
            <span class="v5-stat-value" style="color:<?= $active_maintenance_modes > 0 ? '#dc2626' : 'var(--v5-text)' ?>"><?= (int)$active_maintenance_modes ?></span>
        </div>

        <!-- Total Users -->
        <div class="v5-glass-card" style="padding:1.1rem 1.25rem;display:flex;flex-direction:column;gap:.25rem;">
            <span style="font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:#3b82f6;display:flex;align-items:center;gap:.4rem;">
                <span style="width:8px;height:8px;border-radius:50%;background:#3b82f6;"></span>
                <span data-i18n="dev.stat_users">Total Users</span>
            </span>
            <span class="v5-stat-value" style="color:#2563eb;"><?= (int)$total_users ?></span>
        </div>

        <!-- AJAX-injected DB stats -->
        <div id="db-status-cards" style="display:contents;">
            <!-- Loader placeholder -->
            <div class="v5-glass-card" style="padding:1.1rem 1.25rem;grid-column:span 4;display:flex;align-items:center;gap:.6rem;color:#94a3b8;font-size:.82rem;font-weight:700;">
                <svg style="width:1rem;height:1rem;animation:spin 1s linear infinite;flex:0 0 auto;" fill="none" viewBox="0 0 24 24">
                    <circle style="opacity:.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path style="opacity:.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span data-i18n="dev.loading_db">Querying Database Metrics&hellip;</span>
            </div>
        </div>

    </div>

    <!-- Two-column: SQL Console + Error Log -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">

        <!-- LEFT: SQL Console (dark terminal) -->
        <div style="background:#0f172a;border-radius:14px;border:1px solid #1e293b;overflow:hidden;display:flex;flex-direction:column;">
            <div style="padding:.85rem 1.2rem;border-bottom:1px solid #1e293b;display:flex;align-items:center;justify-content:space-between;background:rgba(15,23,42,.6);">
                <h2 style="margin:0;font-size:1rem;font-weight:700;color:#fff;display:flex;align-items:center;gap:.5rem;">
                    <svg style="width:1.1rem;height:1.1rem;color:#818cf8;flex:0 0 auto;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                    </svg>
                    <span data-i18n="dev.console_title">Database Console</span>
                </h2>
                <span class="v5-badge" style="background:#1e293b;color:#64748b;font-size:.65rem;letter-spacing:.1em;text-transform:uppercase;" data-i18n="dev.read_only">Read-Only</span>
            </div>

            <div style="padding:1.2rem;flex:1;display:flex;flex-direction:column;">
                <form action="index.php?page=developer_dashboard" method="POST" style="display:flex;flex-direction:column;height:100%;gap:1rem;">
                    <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                    <div style="position:relative;">
                        <label for="sql_query" class="sr-only" data-i18n="dev.sql_label">SQL Query</label>
                        <textarea
                            id="sql_query"
                            name="sql_query"
                            rows="8"
                            required
                            placeholder="mysql> SELECT * FROM users LIMIT 10;"
                            style="width:100%;background:#020617;color:#34d399;font-family:ui-monospace,monospace;font-size:.82rem;padding:1rem;border-radius:10px;border:1px solid #1e293b;resize:vertical;outline:none;box-sizing:border-box;min-height:160px;scrollbar-width:thin;scrollbar-color:#334155 transparent;"
                        ></textarea>
                        <!-- Traffic lights deco -->
                        <div style="position:absolute;top:.65rem;right:.85rem;display:flex;gap:.35rem;pointer-events:none;">
                            <span style="width:10px;height:10px;border-radius:50%;background:rgba(239,68,68,.45);"></span>
                            <span style="width:10px;height:10px;border-radius:50%;background:rgba(234,179,8,.45);"></span>
                            <span style="width:10px;height:10px;border-radius:50%;background:rgba(34,197,94,.45);"></span>
                        </div>
                    </div>
                    <div style="display:flex;justify-content:flex-end;">
                        <button type="submit" class="btn-primary" style="display:flex;align-items:center;gap:.45rem;">
                            <svg style="width:1rem;height:1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span data-i18n="dev.execute_btn">Execute Query</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- RIGHT: System Error Log (dark terminal) -->
        <div style="background:#0f172a;border-radius:14px;border:1px solid #1e293b;overflow:hidden;display:flex;flex-direction:column;">
            <div style="padding:.85rem 1.2rem;border-bottom:1px solid #1e293b;display:flex;align-items:center;justify-content:space-between;background:rgba(15,23,42,.6);">
                <h2 style="margin:0;font-size:1rem;font-weight:700;color:#fff;display:flex;align-items:center;gap:.5rem;">
                    <svg style="width:1.1rem;height:1.1rem;color:#fb7185;flex:0 0 auto;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span data-i18n="dev.log_title">System error_log</span>
                </h2>
                <a href="index.php?page=error_log_viewer" class="v5-badge v5-badge-info" style="font-size:.65rem;letter-spacing:.08em;text-decoration:none;" data-i18n="dev.view_full_log">View Full Log</a>
            </div>

            <div style="padding:1.2rem;flex:1;display:flex;flex-direction:column;">
                <div style="background:#020617;border-radius:10px;border:1px solid #1e293b;overflow:hidden;position:relative;flex:1;min-height:200px;display:flex;flex-direction:column;">
                    <!-- Fake terminal bar -->
                    <div style="background:#1e293b;padding:.35rem .9rem;font-family:ui-monospace,monospace;font-size:.72rem;color:#64748b;flex:0 0 auto;">
                        user@mbpos:~$ tail -n 200 error_log
                    </div>
                    <pre style="margin:0;flex:1;padding:.85rem;overflow-y:auto;color:#cbd5e1;font-size:.75rem;font-family:ui-monospace,monospace;white-space:pre-wrap;word-break:break-all;scrollbar-width:thin;scrollbar-color:#334155 transparent;" id="log-pre"><code><?= htmlspecialchars($log_content) ?></code></pre>
                </div>
            </div>
        </div>

    </div>

    <!-- Query Results Panel (conditional) -->
    <?php if ($query_result !== null || $query_error): ?>
    <div class="v5-glass-card" style="padding:1.5rem;margin-bottom:1.5rem;">
        <h3 style="margin:0 0 1rem;font-size:1.05rem;font-weight:700;color:var(--v5-text);display:flex;align-items:center;gap:.5rem;">
            <svg style="width:1.2rem;height:1.2rem;color:<?= $query_error ? '#ef4444' : '#22c55e' ?>;flex:0 0 auto;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span data-i18n="dev.query_output">Query Output</span>
        </h3>

        <?php if ($query_error): ?>
            <div style="background:#fef2f2;border:1px solid #fca5a5;color:#b91c1c;padding:1rem 1.1rem;border-radius:10px;display:flex;align-items:flex-start;gap:.75rem;">
                <svg style="width:1.2rem;height:1.2rem;color:#ef4444;flex:0 0 auto;margin-top:.1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div style="font-family:ui-monospace,monospace;font-size:.82rem;line-height:1.5;"><?= htmlspecialchars($query_error) ?></div>
            </div>
        <?php elseif (empty($query_result)): ?>
            <div style="background:#f0fdf4;border:1px solid #86efac;color:#15803d;padding:1rem 1.1rem;border-radius:10px;display:flex;align-items:center;gap:.75rem;">
                <svg style="width:1.2rem;height:1.2rem;color:#22c55e;flex:0 0 auto;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <div style="font-size:.88rem;font-weight:600;">
                    <span data-i18n="dev.query_success">Query executed successfully.</span>
                    <strong><?= (int)($affected_rows ?? 0) ?></strong>
                    <span data-i18n="dev.rows_affected">rows affected.</span>
                </div>
            </div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="v5-table" style="white-space:nowrap;">
                    <thead>
                        <tr>
                            <?php foreach (array_keys($query_result[0]) as $header): ?>
                                <th><?= htmlspecialchars($header) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($query_result as $row): ?>
                            <tr>
                                <?php foreach ($row as $cell): ?>
                                    <td style="font-family:ui-monospace,monospace;"><?= htmlspecialchars($cell ?? 'NULL') ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Quick Actions Grid -->
    <div style="padding-top:1.5rem;border-top:1px solid var(--v5-border);">
        <h3 style="margin:0 0 1rem;font-size:1rem;font-weight:700;color:var(--v5-text);display:flex;align-items:center;gap:.5rem;">
            <span style="width:8px;height:8px;border-radius:50%;background:#6366f1;display:inline-block;"></span>
            <span data-i18n="dev.shortcuts_title">Core Configuration Shortcuts</span>
        </h3>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem;">

            <a href="index.php?page=maintenance" class="v5-glass-card" style="display:flex;flex-direction:column;gap:.5rem;padding:1.1rem 1.2rem;text-decoration:none;transition:box-shadow .18s,transform .18s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
                <div style="width:40px;height:40px;background:#fef2f2;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                    <svg style="width:1.2rem;height:1.2rem;color:#ef4444;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <span style="font-weight:700;color:var(--v5-text);font-size:.95rem;" data-i18n="dev.action_maintenance">Maintenance</span>
                <span style="font-size:.75rem;color:var(--v5-muted);font-weight:600;" data-i18n="dev.action_maintenance_sub">Toggle site offline mode</span>
            </a>

            <a href="index.php?page=branches" class="v5-glass-card" style="display:flex;flex-direction:column;gap:.5rem;padding:1.1rem 1.2rem;text-decoration:none;transition:box-shadow .18s,transform .18s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
                <div style="width:40px;height:40px;background:#eef2ff;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                    <svg style="width:1.2rem;height:1.2rem;color:#6366f1;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <span style="font-weight:700;color:var(--v5-text);font-size:.95rem;" data-i18n="dev.action_branches">Branch Logic</span>
                <span style="font-size:.75rem;color:var(--v5-muted);font-weight:600;" data-i18n="dev.action_branches_sub">Manage operational nodes</span>
            </a>

            <a href="index.php?page=register" class="v5-glass-card" style="display:flex;flex-direction:column;gap:.5rem;padding:1.1rem 1.2rem;text-decoration:none;transition:box-shadow .18s,transform .18s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
                <div style="width:40px;height:40px;background:#f0fdf4;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                    <svg style="width:1.2rem;height:1.2rem;color:#22c55e;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                    </svg>
                </div>
                <span style="font-weight:700;color:var(--v5-text);font-size:.95rem;" data-i18n="dev.action_identity">Identity Hub</span>
                <span style="font-size:.75rem;color:var(--v5-muted);font-weight:600;" data-i18n="dev.action_identity_sub">Provision new accounts</span>
            </a>

            <a href="index.php?page=error_log_viewer" class="v5-glass-card" style="display:flex;flex-direction:column;gap:.5rem;padding:1.1rem 1.2rem;text-decoration:none;transition:box-shadow .18s,transform .18s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
                <div style="width:40px;height:40px;background:#f8fafc;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                    <svg style="width:1.2rem;height:1.2rem;color:#64748b;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <span style="font-weight:700;color:var(--v5-text);font-size:.95rem;" data-i18n="dev.action_debuglog">Full Debug Log</span>
                <span style="font-size:.75rem;color:var(--v5-muted);font-weight:600;" data-i18n="dev.action_debuglog_sub">Trace system exceptions</span>
            </a>

        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // Auto-scroll the terminal log to the bottom
    const logPre = document.getElementById('log-pre');
    if (logPre) {
        logPre.scrollTop = logPre.scrollHeight;
    }

    // Fetch and display database status via AJAX
    const statusRequest = window.mbposFetch
        ? window.mbposFetch('index.php?page=ajax_db_status', { timeout: 6000 })
        : fetch('index.php?page=ajax_db_status', { cache: 'no-store', credentials: 'same-origin' });

    statusRequest
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('db-status-cards');
            container.style.display = 'contents';
            container.innerHTML = `
                <div class="v5-glass-card" style="padding:1.1rem 1.25rem;display:flex;flex-direction:column;gap:.25rem;">
                    <span style="font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:#a855f7;display:flex;align-items:center;gap:.4rem;">
                        <span style="width:8px;height:8px;border-radius:50%;background:#a855f7;"></span>
                        DB Size
                    </span>
                    <span class="v5-stat-value" style="color:#9333ea;">${data.size}</span>
                </div>
                <div class="v5-glass-card" style="padding:1.1rem 1.25rem;display:flex;flex-direction:column;gap:.25rem;">
                    <span style="font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:#06b6d4;display:flex;align-items:center;gap:.4rem;">
                        <span style="width:8px;height:8px;border-radius:50%;background:#06b6d4;"></span>
                        Tables
                    </span>
                    <span class="v5-stat-value" style="color:#0891b2;">${data.tables}</span>
                </div>
                <div class="v5-glass-card" style="padding:1.1rem 1.25rem;display:flex;flex-direction:column;gap:.25rem;">
                    <span style="font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:#10b981;display:flex;align-items:center;gap:.4rem;">
                        <span style="width:8px;height:8px;border-radius:50%;background:#10b981;"></span>
                        Version
                    </span>
                    <span class="v5-stat-value" style="color:#059669;font-size:1rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${data.version}</span>
                </div>
                <div class="v5-glass-card" style="padding:1.1rem 1.25rem;display:flex;flex-direction:column;gap:.25rem;">
                    <span style="font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;color:#22c55e;display:flex;align-items:center;gap:.4rem;">
                        <span style="width:8px;height:8px;border-radius:50%;background:#22c55e;animation:v5-pulse 1.5s infinite;"></span>
                        Cache Layer
                    </span>
                    <span class="v5-stat-value" style="color:#16a34a;">${data.cache}</span>
                    <span style="font-size:.7rem;font-weight:600;color:#94a3b8;">${data.cache_latency_ms === null ? 'Fail-open active' : data.cache_latency_ms + ' ms'}</span>
                </div>
            `;
        })
        .catch(() => {
            const container = document.getElementById('db-status-cards');
            container.style.display = 'contents';
            container.innerHTML = `
                <div class="v5-glass-card" style="padding:1rem 1.2rem;display:flex;align-items:center;gap:.6rem;color:#b91c1c;font-size:.82rem;font-weight:700;border-color:#fca5a5;background:#fef2f2;">
                    <svg style="width:1rem;height:1rem;flex:0 0 auto;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Failed to fetch live database metrics.
                </div>
            `;
        });
});
</script>

<?php include_template('footer'); ?>
