<?php
// pos/admin_dashboard.php - Admin Control Center (V5)
require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authentication & Authorization ---
if (!is_logged_in() || (!is_admin() && !is_developer())) {
    flash_message('error', 'You are not authorized to access the admin dashboard.');
    redirect('index.php?page=dashboard');
}

global $connection;

// --- Fetch Dashboard Key Metrics ---
$total_vouchers_result = mysqli_query($connection, "SELECT COUNT(id) AS total FROM vouchers");
$total_vouchers = mysqli_fetch_assoc($total_vouchers_result)['total'] ?? 0;

$total_users_result = mysqli_query($connection, "SELECT COUNT(id) AS total FROM users");
$total_users = mysqli_fetch_assoc($total_users_result)['total'] ?? 0;

$revenue_by_currency = [];
$rev_result = mysqli_query($connection, "SELECT currency, SUM(total_amount) AS total FROM vouchers WHERE status != 'Cancelled' GROUP BY currency ORDER BY total DESC");
if ($rev_result) {
    while ($row = mysqli_fetch_assoc($rev_result)) {
        $c = trim($row['currency'] ?? '');
        if ($c === '') {
            $c = 'MMK';
        }
        $revenue_by_currency[$c] = (float)($row['total'] ?? 0);
    }
}

$today_vouchers_result = mysqli_query($connection, "SELECT COUNT(id) AS today FROM vouchers WHERE DATE(created_at) = CURDATE()");
$today_vouchers = mysqli_fetch_assoc($today_vouchers_result)['today'] ?? 0;

$active_branches_result = mysqli_query($connection, "SELECT COUNT(id) AS active_branches FROM branches");
$active_branches = mysqli_fetch_assoc($active_branches_result)['active_branches'] ?? 0;

include_template('header', ['page' => 'admin_dashboard']);
?>

<div class="v5-page">

    <!-- Page Header -->
    <div class="v5-page-head">
        <div class="v5-page-head__copy">
            <span class="v5-kicker" data-i18n="Administration">Administration</span>
            <h1 data-i18n="Admin Control Center">Admin Control Center</h1>
            <p data-i18n="High-level operational metrics and systemic oversight.">High-level operational metrics and systemic oversight.</p>
        </div>
        <div class="v5-count" aria-label="<?php echo e(__('Administrator Access')); ?>">
            <span style="width:.5rem;height:.5rem;border-radius:50%;background:#10b981;display:inline-block;box-shadow:0 0 0 4px rgba(16,185,129,.15);flex-shrink:0;" aria-hidden="true"></span>
            <span data-i18n="Administrator Access">Administrator Access</span>
        </div>
    </div>

    <!-- Metrics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">

        <!-- Total Vouchers -->
        <div class="v5-glass-card p-6 md:col-span-1">
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;">
                <span style="width:2.5rem;height:2.5rem;border-radius:.85rem;background:#eff6ff;display:grid;place-items:center;flex-shrink:0;" aria-hidden="true">
                    <svg width="18" height="18" fill="none" stroke="#2563eb" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </span>
                <span class="v5-status-dot v5-status-dot--off" style="background:#eff6ff;color:#2563eb;" data-i18n="Total Vouchers">Total Vouchers</span>
            </div>
            <div style="font-size:2.25rem;font-weight:900;color:var(--v5-text);line-height:1;margin-bottom:.35rem;"><?php echo number_format($total_vouchers); ?></div>
            <div style="font-size:.72rem;font-weight:700;color:var(--v5-muted);text-transform:uppercase;letter-spacing:.06em;" data-i18n="Global Vouchers Processed">Global Vouchers Processed</div>
        </div>

        <!-- Registered Personnel -->
        <div class="v5-glass-card p-6 md:col-span-1">
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;">
                <span style="width:2.5rem;height:2.5rem;border-radius:.85rem;background:#f0fdf4;display:grid;place-items:center;flex-shrink:0;" aria-hidden="true">
                    <svg width="18" height="18" fill="none" stroke="#16a34a" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </span>
                <span class="v5-status-dot" style="background:#f0fdf4;color:#16a34a;" data-i18n="Registered Personnel">Registered Personnel</span>
            </div>
            <div style="font-size:2.25rem;font-weight:900;color:var(--v5-text);line-height:1;margin-bottom:.35rem;"><?php echo number_format($total_users); ?></div>
            <div style="font-size:.72rem;font-weight:700;color:var(--v5-muted);text-transform:uppercase;letter-spacing:.06em;" data-i18n="Active System Users">Active System Users</div>
        </div>

        <!-- Gross Revenue -->
        <div class="v5-glass-card p-6 md:col-span-1">
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;">
                <span style="width:2.5rem;height:2.5rem;border-radius:.85rem;background:#fffbeb;display:grid;place-items:center;flex-shrink:0;" aria-hidden="true">
                    <svg width="18" height="18" fill="none" stroke="#d97706" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <span class="v5-status-dot" style="background:#fffbeb;color:#d97706;" data-i18n="Gross Revenue">Gross Revenue</span>
            </div>
            <div style="margin-bottom:.35rem;">
                <?php if (empty($revenue_by_currency)): ?>
                    <div style="font-size:2.25rem;font-weight:900;color:var(--v5-text);line-height:1;">0.00</div>
                <?php elseif (count($revenue_by_currency) === 1): ?>
                    <?php foreach ($revenue_by_currency as $curr => $amt): ?>
                        <div style="font-size:1.65rem;font-weight:900;color:var(--v5-text);line-height:1.1;word-break:break-all;">
                            <span style="font-size:.85rem;font-weight:700;color:var(--v5-muted);margin-right:.25rem;"><?= e($curr) ?></span><?= number_format($amt, 2) ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="display:flex;flex-direction:column;gap:.35rem;max-height:6rem;overflow-y:auto;">
                        <?php foreach ($revenue_by_currency as $curr => $amt): ?>
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;">
                                <span class="v5-badge" style="font-size:.65rem;padding:0.1rem 0.35rem;font-weight:800;"><?= e($curr) ?></span>
                                <span style="font-size:1.05rem;font-weight:900;color:var(--v5-text);font-variant-numeric:tabular-nums;"><?= number_format($amt, 2) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div style="font-size:.72rem;font-weight:700;color:var(--v5-muted);text-transform:uppercase;letter-spacing:.06em;" data-i18n="Gross Ledger Revenue">Gross Ledger Revenue</div>
        </div>

        <!-- Today's Vouchers -->
        <div class="v5-glass-card p-6 md:col-span-1">
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;">
                <span style="width:2.5rem;height:2.5rem;border-radius:.85rem;background:#f0f9ff;display:grid;place-items:center;flex-shrink:0;" aria-hidden="true">
                    <svg width="18" height="18" fill="none" stroke="#0284c7" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </span>
                <span class="v5-status-dot" style="background:#f0f9ff;color:#0284c7;" data-i18n="Today">Today</span>
            </div>
            <div style="font-size:2.25rem;font-weight:900;color:var(--v5-text);line-height:1;margin-bottom:.35rem;"><?php echo number_format($today_vouchers); ?></div>
            <div style="font-size:.72rem;font-weight:700;color:var(--v5-muted);text-transform:uppercase;letter-spacing:.06em;" data-i18n="Vouchers Today">Vouchers Today</div>
        </div>

        <!-- Active Branches -->
        <div class="v5-glass-card p-6 md:col-span-1">
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;">
                <span style="width:2.5rem;height:2.5rem;border-radius:.85rem;background:#f0fdfa;display:grid;place-items:center;flex-shrink:0;" aria-hidden="true">
                    <svg width="18" height="18" fill="none" stroke="#0d9488" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </span>
                <span class="v5-status-dot" style="background:#f0fdfa;color:#0d9488;" data-i18n="Branches">Branches</span>
            </div>
            <div style="font-size:2.25rem;font-weight:900;color:var(--v5-text);line-height:1;margin-bottom:.35rem;"><?php echo number_format($active_branches); ?></div>
            <div style="font-size:.72rem;font-weight:700;color:var(--v5-muted);text-transform:uppercase;letter-spacing:.06em;" data-i18n="Operating Branches">Operating Branches</div>
        </div>

    </div><!-- /metrics grid -->

    <!-- Administrative Quick Actions -->
    <div class="v5-panel">
        <div class="v5-panel__head">
            <h2 data-i18n="Administrative Actions">Administrative Actions</h2>
        </div>
        <div class="v5-panel__body">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                <!-- User Management -->
                <a href="index.php?page=register" class="v5-record-row" style="flex-direction:column;align-items:flex-start;gap:.75rem;text-decoration:none;min-height:8rem;">
                    <span style="width:2.75rem;height:2.75rem;border-radius:.9rem;background:#eef2ff;display:grid;place-items:center;flex-shrink:0;" aria-hidden="true">
                        <svg width="20" height="20" fill="none" stroke="#4f46e5" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    </span>
                    <div>
                        <strong style="display:block;font-size:.95rem;font-weight:900;color:var(--v5-text);margin-bottom:.2rem;" data-i18n="User Management">User Management</strong>
                        <span style="font-size:.78rem;color:var(--v5-muted);font-weight:600;" data-i18n="Register and manage staff access.">Register and manage staff access.</span>
                    </div>
                </a>

                <!-- Global Ledger -->
                <a href="index.php?page=voucher_list" class="v5-record-row" style="flex-direction:column;align-items:flex-start;gap:.75rem;text-decoration:none;min-height:8rem;">
                    <span style="width:2.75rem;height:2.75rem;border-radius:.9rem;background:#eff6ff;display:grid;place-items:center;flex-shrink:0;" aria-hidden="true">
                        <svg width="20" height="20" fill="none" stroke="#2563eb" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    </span>
                    <div>
                        <strong style="display:block;font-size:.95rem;font-weight:900;color:var(--v5-text);margin-bottom:.2rem;" data-i18n="Global Ledger">Global Ledger</strong>
                        <span style="font-size:.78rem;color:var(--v5-muted);font-weight:600;" data-i18n="View and audit all system vouchers.">View and audit all system vouchers.</span>
                    </div>
                </a>

                <!-- Branch Architecture -->
                <a href="index.php?page=branches" class="v5-record-row" style="flex-direction:column;align-items:flex-start;gap:.75rem;text-decoration:none;min-height:8rem;">
                    <span style="width:2.75rem;height:2.75rem;border-radius:.9rem;background:#ecfeff;display:grid;place-items:center;flex-shrink:0;" aria-hidden="true">
                        <svg width="20" height="20" fill="none" stroke="#0891b2" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </span>
                    <div>
                        <strong style="display:block;font-size:.95rem;font-weight:900;color:var(--v5-text);margin-bottom:.2rem;" data-i18n="Branch Architecture">Branch Architecture</strong>
                        <span style="font-size:.78rem;color:var(--v5-muted);font-weight:600;" data-i18n="Configure regions and operating nodes.">Configure regions and operating nodes.</span>
                    </div>
                </a>

                <!-- Profit / Loss -->
                <a href="index.php?page=profit_loss" class="v5-record-row" style="flex-direction:column;align-items:flex-start;gap:.75rem;text-decoration:none;min-height:8rem;">
                    <span style="width:2.75rem;height:2.75rem;border-radius:.9rem;background:#f0fdf4;display:grid;place-items:center;flex-shrink:0;" aria-hidden="true">
                        <svg width="20" height="20" fill="none" stroke="#16a34a" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </span>
                    <div>
                        <strong style="display:block;font-size:.95rem;font-weight:900;color:var(--v5-text);margin-bottom:.2rem;" data-i18n="Profit / Loss">Profit / Loss</strong>
                        <span style="font-size:.78rem;color:var(--v5-muted);font-weight:600;" data-i18n="Analyze overall system financials.">Analyze overall system financials.</span>
                    </div>
                </a>

            </div>
        </div>
    </div><!-- /quick actions panel -->

</div><!-- /v5-page -->

<?php include_template('footer'); ?>