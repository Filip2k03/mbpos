<?php
// templates/maintenance_banner.php - Sticky Liquid Glass Maintenance & High-Load Advisory Banner

// Ensure diagnostics data is available
if (!isset($pos_diagnostics)) {
    require_once __DIR__ . '/../includes/maintenance_analytics.php';
    global $connection;
    $pos_diagnostics = get_pos_system_diagnostics($connection ?? null);
}

$is_active_alert = $pos_diagnostics['maintenance_active'] || $pos_diagnostics['high_load_detected'];
$load_pct = $pos_diagnostics['server_load_percent'] ?? 75;
$is_critical = $pos_diagnostics['maintenance_active'] || $load_pct > 80;
?>

<div id="pos-maintenance-banner" class="sticky top-0 z-50 transition-all duration-300 <?= $is_critical ? 'bg-gradient-to-r from-amber-500/90 via-rose-500/90 to-indigo-600/90' : 'bg-gradient-to-r from-blue-600/90 via-indigo-600/90 to-purple-600/90' ?> text-white backdrop-blur-xl border-b border-white/20 shadow-[0_4px_25px_rgba(0,0,0,0.15)] px-3 py-2.5 sm:px-6">
    <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-3 text-xs sm:text-sm">
        
        <!-- Left: Status & Warning Message -->
        <div class="flex items-center gap-3 w-full md:w-auto justify-between md:justify-start">
            <div class="flex items-center gap-2.5">
                <span class="relative flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full <?= $is_critical ? 'bg-amber-300' : 'bg-cyan-300' ?> opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 <?= $is_critical ? 'bg-amber-400' : 'bg-cyan-400' ?>"></span>
                </span>
                
                <div class="flex items-center gap-2">
                    <span class="font-extrabold uppercase tracking-wider text-[11px] bg-black/25 px-2.5 py-0.5 rounded-full border border-white/20 shadow-inner inline-flex items-center gap-1.5">
                        <?php if ($is_critical): ?>
                            <svg class="w-3 h-3 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <span data-i18n="High Load Alert">High Load Alert</span>
                        <?php else: ?>
                            <svg class="w-3 h-3 text-cyan-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            <span data-i18n="System Advisory">System Advisory</span>
                        <?php endif; ?>
                    </span>
                    <span class="font-semibold text-white/95 truncate max-w-[220px] sm:max-w-none">
                        <?= $pos_diagnostics['maintenance_active'] ? 'Routine Maintenance & Updates in Progress' : 'High Operational Traffic Running on POS Node' ?>
                    </span>
                </div>
            </div>

            <!-- Mobile Quick Toggle Button -->
            <button type="button" onclick="openMaintenanceModal()" class="md:hidden text-[11px] font-bold underline text-amber-200 hover:text-white transition-colors ml-auto">
                Details
            </button>
        </div>

        <!-- Center: Quick Metric Badges (Desktop / Tablet) -->
        <div class="hidden lg:flex items-center gap-3 bg-black/20 px-3 py-1 rounded-xl border border-white/10">
            <div class="flex items-center gap-1.5 font-medium text-white/90">
                <span class="text-white/60">Load:</span>
                <span class="font-mono font-bold <?= $load_pct > 80 ? 'text-amber-300' : 'text-emerald-300' ?>"><?= $load_pct ?>%</span>
            </div>
            <span class="text-white/30">|</span>
            <div class="flex items-center gap-1.5 font-medium text-white/90">
                <span class="text-white/60">Total Vouchers:</span>
                <span class="font-mono font-bold text-white"><?= number_format($pos_diagnostics['total_vouchers']) ?></span>
            </div>
            <span class="text-white/30">|</span>
            <div class="flex items-center gap-1.5 font-medium text-white/90">
                <span class="text-white/60">Weight:</span>
                <span class="font-mono font-bold text-white"><?= number_format($pos_diagnostics['total_weight_kg'], 1) ?> kg</span>
            </div>
        </div>

        <!-- Right: Action Buttons -->
        <div class="flex items-center gap-2 w-full md:w-auto justify-end">
            <button type="button" onclick="openMaintenanceModal()" class="flex-1 md:flex-initial flex items-center justify-center gap-1.5 bg-white/20 hover:bg-white text-white hover:text-gray-900 px-3.5 py-1.5 rounded-xl font-bold text-xs transition-all shadow-sm hover:shadow-md border border-white/30 backdrop-blur-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2m0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                <span>View Full Breakdown</span>
            </button>

            <button type="button" onclick="dismissMaintenanceBanner()" class="p-1.5 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition-colors" title="Dismiss Banner">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

    </div>
</div>
