<?php
// pos/dashboard.php - Main operations workstation dashboard (V5 Modern Enterprise).

require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/cache.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authentication ---
if (!is_logged_in()) {
    redirect('index.php?page=login');
}

global $connection;
mysqli_set_charset($connection, "utf8mb4");

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Staff Operator';

// --- Fetch recent vouchers created by the current user ---
$recent_vouchers = [];
$query = "SELECT id, voucher_code, receiver_name, total_amount, currency, status, created_at\n" .
         "FROM vouchers\n" .
         "WHERE created_by_user_id = ?\n" .
         "ORDER BY created_at DESC\n" .
         "LIMIT 8";
$stmt = mysqli_prepare($connection, $query);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $recent_vouchers[] = $row;
    }
}
mysqli_stmt_close($stmt);

// --- Fetch Regional Sequence Trackers with cache ---
$region_sequences = mbpos_cache_remember('dashboard-regions', 'sequence', 30, function () use ($connection) {
    $rows = [];
    $seq_result = mysqli_query($connection, "SELECT region_name, prefix, current_sequence FROM regions ORDER BY current_sequence DESC");
    if ($seq_result) {
        while ($row = mysqli_fetch_assoc($seq_result)) {
            $rows[] = $row;
        }
    }
    return $rows;
});

include_template('header', ['page' => 'dashboard']);
?>

<div class="v5-page">
    <!-- Welcome Header -->
    <div class="v5-page-head">
        <div class="v5-page-head__copy">
            <span class="v5-kicker" data-i18n="Operational Station">Operational Station</span>
            <h1><span data-i18n="Welcome back,">Welcome back,</span> <?= e($username) ?></h1>
            <p data-i18n="Active regional sequences, live queue oversight, and operational dispatch.">Active regional sequences, live queue oversight, and operational dispatch.</p>
        </div>
        <div class="v5-page-actions flex items-center gap-3">
            <div class="bg-slate-900 border border-slate-700 px-3.5 py-1.5 rounded-xl shadow-inner flex items-center gap-2">
                <svg class="w-3.5 h-3.5 text-cyan-400 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span id="digital-clock" class="font-mono text-xs font-bold text-cyan-300 tracking-widest">00:00:00 AM</span>
            </div>
            <a href="index.php?page=voucher_create" class="btn-primary" data-i18n="Create Voucher">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Create Voucher
            </a>
        </div>
    </div>

    <!-- Quick Actions Module -->
    <section class="mb-6">
        <?php include_template('dashboard_actions'); ?>
    </section>

    <!-- Global Regional Sequence Trackers (Digital Show) -->
    <section class="v5-panel mb-8">
        <div class="v5-panel__head">
            <div class="flex items-center gap-3">
                <span style="width:2rem;height:2rem;border-radius:.6rem;background:#ecfeff;display:grid;place-items:center;flex-shrink:0;">
                    <svg width="15" height="15" fill="none" stroke="#0891b2" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2m0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </span>
                <h2 data-i18n="Live Regional Sequence Sync">Live Regional Sequence Sync</h2>
            </div>
            <div class="flex items-center gap-2">
                <span class="v5-badge v5-badge-success text-xs font-mono">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping inline-block mr-1"></span>
                    SYNC GMT+6:30
                </span>
            </div>
        </div>

        <div class="v5-panel__body">
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3 sm:gap-4">
                <?php foreach ($region_sequences as $reg): ?>
                    <div style="background:#0f172a;border:1px solid #1e293b;border-radius:14px;padding:0.9rem 0.6rem;text-align:center;box-shadow:inset 0 2px 10px rgba(0,0,0,0.5);">
                        <p style="font-size:0.65rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.5rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <?= e($reg['region_name']) ?>
                        </p>
                        <div class="flex flex-col items-center gap-1">
                            <span style="font-size:0.68rem;font-weight:900;color:#38bdf8;background:rgba(56,189,248,0.12);padding:0.15rem 0.5rem;border-radius:6px;border:1px solid rgba(56,189,248,0.25);letter-spacing:0.08em;">
                                <?= e($reg['prefix']) ?>
                            </span>
                            <div style="font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:1.35rem;font-weight:800;color:#34d399;letter-spacing:0.1em;line-height:1.2;margin-top:0.2rem;text-shadow:0 0 10px rgba(52,211,153,0.5);">
                                <?= sprintf('%06d', $reg['current_sequence']) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Main Content: Recent Vouchers + Cloud Architecture Hub -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <!-- Recent Vouchers Table (8 cols) -->
        <div class="lg:col-span-8">
            <section class="v5-panel">
                <div class="v5-panel__head">
                    <div class="flex items-center gap-2">
                        <h2 data-i18n="Your Recent Vouchers">Your Recent Vouchers</h2>
                    </div>
                    <a href="index.php?page=voucher_list" class="btn-ghost btn-sm" data-i18n="View All">View All</a>
                </div>

                <div class="v5-panel__body p-0">
                    <div class="overflow-x-auto">
                        <table class="v5-table w-full">
                            <thead>
                                <tr>
                                    <th data-i18n="Tracking Code">Tracking Code</th>
                                    <th data-i18n="Receiver">Receiver</th>
                                    <th data-i18n="Amount">Amount</th>
                                    <th data-i18n="Status">Status</th>
                                    <th class="text-right" data-i18n="Action">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_vouchers)): ?>
                                    <tr>
                                        <td colspan="5">
                                            <div class="v5-empty">
                                                <span class="v5-empty__icon"><?= mbpos_icon('dashboard', 'w-8 h-8 text-slate-400') ?></span>
                                                <strong data-i18n="No vouchers issued yet.">No vouchers issued yet.</strong>
                                                <p><a href="index.php?page=voucher_create" class="text-primary font-bold hover:underline" data-i18n="Create your first entry">Create your first entry</a></p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_vouchers as $voucher):
                                        $status_class = match(strtolower($voucher['status'] ?? '')) {
                                            'delivered' => 'v5-badge-success',
                                            'in transit' => 'v5-badge-info',
                                            'pending' => 'v5-badge-warning',
                                            'cancelled', 'returned' => 'v5-badge-danger',
                                            default => 'v5-badge-neutral'
                                        };
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="flex items-center gap-2">
                                                    <a class="font-mono font-bold text-primary hover:underline tracking-tight" href="index.php?page=voucher_view&id=<?= (int)$voucher['id'] ?>">
                                                        <?= e($voucher['voucher_code']) ?>
                                                    </a>
                                                    <button type="button" class="text-slate-400 hover:text-blue-600 transition-colors p-1 rounded-md hover:bg-slate-100" title="Copy voucher code" data-copy="<?= e($voucher['voucher_code']) ?>" aria-label="Copy voucher code">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="9" y="9" width="13" height="13" rx="2" ry="2" stroke-width="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" stroke-width="2"/></svg>
                                                    </button>
                                                </div>
                                            </td>
                                            <td>
                                                <strong class="text-main block"><?= e($voucher['receiver_name']) ?></strong>
                                                <span class="text-[11px] text-slate-400 font-mono"><?= format_datetime_myanmar($voucher['created_at'], 'compact') ?> (<?= format_datetime_myanmar($voucher['created_at'], 'relative') ?>)</span>
                                            </td>
                                            <td class="font-mono font-bold text-main">
                                                <span class="text-xs text-muted"><?= e($voucher['currency']) ?></span> <?= number_format($voucher['total_amount'], 2) ?>
                                            </td>
                                            <td>
                                                <span class="v5-badge <?= $status_class ?>"><?= e($voucher['status']) ?></span>
                                            </td>
                                            <td class="text-right">
                                                <div class="flex items-center justify-end gap-1.5">
                                                    <a href="index.php?page=voucher_view&id=<?= (int)$voucher['id'] ?>" class="btn-ghost btn-sm" data-i18n="Details">
                                                        Details
                                                    </a>
                                                    <a href="voucher_print.php?id=<?= (int)$voucher['id'] ?>" target="_blank" rel="noopener noreferrer" class="btn-ghost btn-sm text-slate-600 hover:text-blue-600 p-1.5" title="Print Waybill" aria-label="Print Waybill">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                                    </a>
                                                    <a href="index.php?page=voucher_create&duplicate_id=<?= (int)$voucher['id'] ?>" class="btn-ghost btn-sm text-slate-600 hover:text-blue-600 p-1.5" title="Duplicate as New" aria-label="Duplicate as New">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect width="13" height="13" x="9" y="9" rx="2" ry="2" stroke-width="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" stroke-width="2"/></svg>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <!-- Tech & Cloud Hub (4 cols) -->
        <div class="lg:col-span-4">
            <div style="background:linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);border:1px solid #334155;border-radius:18px;padding:2rem 1.75rem;color:#f8fafc;box-shadow:0 15px 40px rgba(15,23,42,0.3);position:relative;overflow:hidden;">
                <div class="flex items-center gap-3 mb-4">
                    <span style="width:2.5rem;height:2.5rem;border-radius:.75rem;background:rgba(56,189,248,0.15);border:1px solid rgba(56,189,248,0.3);display:grid;place-items:center;">
                        <svg width="18" height="18" fill="none" stroke="#38bdf8" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </span>
                    <div>
                        <strong style="display:block;font-size:1.1rem;font-weight:900;" data-i18n="Tech & Cloud Hub">Tech & Cloud Hub</strong>
                        <span style="font-size:0.75rem;color:#94a3b8;" data-i18n="Architecture & Diagnostics">Architecture & Diagnostics</span>
                    </div>
                </div>

                <p style="font-size:0.85rem;color:#cbd5e1;line-height:1.6;margin-bottom:1.5rem;" data-i18n="High-availability logistics cloud infrastructure, fail-open Redis caching, and real-time ledger sync powered by Thuya Kyaw.">
                    High-availability logistics cloud infrastructure, fail-open Redis caching, and real-time ledger sync powered by Thuya Kyaw.
                </p>

                <button type="button" id="contactDeveloperBtn" class="btn-primary w-full justify-center" data-i18n="Access Tech Hub">
                    Access Tech Hub
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Developer Support Modal -->
<div id="contactModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-md flex items-center justify-center hidden z-[100] transition-opacity duration-300 opacity-0">
    <div class="bg-white/95 backdrop-blur-2xl rounded-3xl shadow-2xl p-6 sm:p-8 w-full max-w-lg border border-white/60 transform scale-95 transition-transform duration-300 relative overflow-hidden" id="contactModalInner">

        <button id="closeModalBtn" class="absolute top-4 right-4 w-9 h-9 bg-slate-100 text-slate-500 hover:text-slate-800 hover:bg-slate-200 rounded-full flex items-center justify-center transition-colors z-20">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>

        <div class="relative z-10">
            <div class="w-12 h-12 bg-primary text-white rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/30 mx-auto mb-4">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
            </div>

            <h2 class="text-2xl font-black text-slate-900 mb-1 text-center tracking-tight" data-i18n="Developer Support Hub">Developer Support Hub</h2>
            <p class="text-slate-500 text-center text-xs font-medium mb-6" data-i18n="Direct assistance and technical engineering contacts.">Direct assistance and technical engineering contacts.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <a href="tel:+959954480806" class="v5-record-row text-center flex-col items-center p-3 rounded-xl border border-slate-100 hover:border-blue-200">
                    <span class="font-bold text-slate-800 text-sm" data-i18n="Direct Call">Direct Call</span>
                    <span class="text-xs text-primary font-mono mt-0.5">+95 9954480806</span>
                </a>

                <a href="https://t.me/Stephanfilip" target="_blank" class="v5-record-row text-center flex-col items-center p-3 rounded-xl border border-slate-100 hover:border-blue-200">
                    <span class="font-bold text-slate-800 text-sm" data-i18n="Telegram Support">Telegram Support</span>
                    <span class="text-xs text-primary font-mono mt-0.5">@stephanfilip2k03</span>
                </a>

                <a href="https://payvia.asia" target="_blank" rel="noopener noreferrer" class="v5-record-row text-center flex-col items-center p-3 rounded-xl border border-slate-100 hover:border-blue-200">
                    <span class="font-bold text-slate-800 text-sm">FinTech Core</span>
                    <span class="text-xs text-muted mt-0.5">payvia.asia</span>
                </a>

                <a href="https://payvia.cloud" target="_blank" rel="noopener noreferrer" class="v5-record-row text-center flex-col items-center p-3 rounded-xl border border-slate-100 hover:border-blue-200">
                    <span class="font-bold text-slate-800 text-sm">Cloud Operations</span>
                    <span class="text-xs text-muted mt-0.5">payvia.cloud</span>
                </a>
            </div>

            <div class="mt-5 text-center">
                 <p class="text-xs font-medium text-slate-400">Freelance Project Developed by <a href="https://thuyakyaw.com" target="_blank" rel="noopener noreferrer" class="text-primary font-bold hover:underline" title="Thuya Kyaw — Lead Software Engineer & Architect">thuyakyaw.com</a></p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Digital Clock
    function updateClock() {
        const now = new Date();
        let h = now.getHours();
        const m = String(now.getMinutes()).padStart(2, '0');
        const s = String(now.getSeconds()).padStart(2, '0');
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        const clock = document.getElementById('digital-clock');
        if (clock) clock.textContent = `${String(h).padStart(2, '0')}:${m}:${s} ${ampm}`;
    }
    updateClock();
    setInterval(updateClock, 1000);

    // Modal
    const btn = document.getElementById('contactDeveloperBtn');
    const modal = document.getElementById('contactModal');
    const inner = document.getElementById('contactModalInner');
    const close = document.getElementById('closeModalBtn');

    function openModal() {
        if (!modal) return;
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            inner.classList.remove('scale-95');
            inner.classList.add('scale-100');
        }, 10);
    }
    function closeModal() {
        if (!modal) return;
        modal.classList.add('opacity-0');
        inner.classList.remove('scale-100');
        inner.classList.add('scale-95');
        setTimeout(() => modal.classList.add('hidden'), 250);
    }

    if (btn) btn.addEventListener('click', openModal);
    if (close) close.addEventListener('click', closeModal);
    if (modal) modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) closeModal(); });
});
</script>

<?php include_template('footer'); ?>
