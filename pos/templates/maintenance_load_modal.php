<?php
// templates/maintenance_load_modal.php - Interactive Maintenance, High-Load Advisory & Voucher Breakdown Modal

if (!isset($pos_diagnostics)) {
    require_once __DIR__ . '/../includes/maintenance_analytics.php';
    global $connection;
    $pos_diagnostics = get_pos_system_diagnostics($connection ?? null);
}

$load_pct = $pos_diagnostics['server_load_percent'] ?? 75;
$currency_totals = $pos_diagnostics['currency_totals'] ?? [];
$status_breakdown = $pos_diagnostics['status_breakdown'] ?? [];
$item_breakdown = $pos_diagnostics['item_breakdown'] ?? [];
$maintenance_categories = $pos_diagnostics['maintenance_categories'] ?? [];
$total_vouchers = $pos_diagnostics['total_vouchers'] ?? 0;
$total_weight_kg = $pos_diagnostics['total_weight_kg'] ?? 0;
$token = $pos_diagnostics['diagnostic_token'] ?? 'POS-DIAG-000';
$shift_name = $pos_diagnostics['shift_name'] ?? 'GMT+6:30 Operations';
$shift_greeting = $pos_diagnostics['shift_greeting'] ?? 'Welcome';
$shift_desc = $pos_diagnostics['shift_desc'] ?? 'Live Operational Status';
$shift_session_key = $pos_diagnostics['shift_session_key'] ?? 'mbpos_shift_default';
$gmt_time = $pos_diagnostics['current_time_gmt630'] ?? date('h:i A');

// Strictly allow ONLY Developer role to see Dev Mode / Dev Center controls
$is_developer_user = function_exists('is_developer') && is_developer();
?>


<!-- Liquid Glass Diagnostic & Maintenance Modal Wrapper -->
<div id="pos-maintenance-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true" data-shift-key="<?= htmlspecialchars($shift_session_key) ?>">
    
    <!-- Backdrop Blur with ambient glow -->
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-md transition-opacity duration-300" onclick="closeMaintenanceModal(true)"></div>

    <!-- Modal Dialog Container (Centered on Desktop, Ergonomic Bottom-Sheet on Mobile) -->
    <div class="flex min-h-full items-end sm:items-center justify-center p-0 sm:p-4 text-center">
        
        <div class="relative w-full max-w-4xl transform overflow-hidden rounded-t-[2.5rem] sm:rounded-[2.5rem] bg-white/95 backdrop-blur-2xl text-left shadow-[0_25px_70px_rgba(0,0,0,0.25)] border border-white/80 transition-all duration-300 max-h-[92vh] sm:max-h-[90vh] flex flex-col my-0 sm:my-8 animate-fadeInDown">
            
            <!-- Decorative Ambient Glows inside Modal -->
            <div class="absolute -top-24 -left-24 w-72 h-72 bg-indigo-500/15 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-24 -right-24 w-72 h-72 bg-cyan-500/15 rounded-full blur-3xl pointer-events-none"></div>

            <!-- Modal Header -->
            <div class="px-6 sm:px-8 pt-6 sm:pt-8 pb-5 border-b border-gray-100 flex items-center justify-between relative z-10 bg-white/60">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl bg-gradient-to-br from-amber-500 via-rose-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-rose-500/20 text-white shrink-0">
                        <svg class="w-6 h-6 sm:w-7 sm:h-7 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-lg sm:text-2xl font-extrabold bg-gradient-to-r from-gray-900 via-slate-800 to-indigo-900 bg-clip-text text-transparent tracking-tight">
                                <?= htmlspecialchars($shift_greeting) ?>! <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
                            </h3>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold <?= $pos_diagnostics['maintenance_active'] ? 'bg-rose-100 text-rose-800 border border-rose-200' : 'bg-amber-100 text-amber-800 border border-amber-200' ?>">
                                <?= $pos_diagnostics['maintenance_active'] ? 'Maintenance Active' : 'High Operational Load' ?>
                            </span>
                        </div>
                        <p class="text-xs sm:text-sm font-semibold text-slate-500 mt-0.5 flex items-center gap-2 flex-wrap">
                            <span class="text-indigo-600 font-bold"><?= htmlspecialchars($shift_name) ?></span>
                            <span class="text-slate-300">•</span>
                            <span class="font-mono text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md text-[11px] font-bold"><?= htmlspecialchars($gmt_time) ?></span>
                        </p>
                    </div>
                </div>

                <!-- Close Button -->
                <button type="button" onclick="closeMaintenanceModal(true)" class="text-slate-400 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 p-2 rounded-2xl transition-colors shadow-sm" title="Close Modal">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Modal Body (Scrollable with custom scrollbar) -->
            <div class="px-6 sm:px-8 py-6 overflow-y-auto space-y-6 flex-1 custom-scrollbar relative z-10">
                
                <!-- Section 1: Server Load & Shift Diagnostic Gauge -->
                <div class="bg-gradient-to-br from-slate-900 to-indigo-950 rounded-2xl sm:rounded-3xl p-5 sm:p-6 text-white shadow-lg border border-slate-800 relative overflow-hidden">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
                                <span class="text-xs font-extrabold uppercase tracking-widest text-emerald-400">GMT +6:30 Live Operation Node</span>
                            </div>
                            <h4 class="text-base sm:text-lg font-bold text-white mt-1"><?= htmlspecialchars($shift_desc) ?></h4>
                        </div>
                        <div class="flex items-center gap-2 bg-white/10 px-3 py-1.5 rounded-xl border border-white/10 text-xs font-mono">
                            <span class="text-slate-400">Diag Token:</span>
                            <span class="text-cyan-300 font-bold"><?= $token ?></span>
                        </div>
                    </div>

                    <!-- Load Bar -->
                    <div class="w-full bg-slate-800 rounded-full h-3.5 mb-3 p-0.5 overflow-hidden border border-slate-700">
                        <div class="bg-gradient-to-r from-cyan-500 via-indigo-500 to-rose-500 h-2.5 rounded-full transition-all duration-1000 shadow-[0_0_12px_rgba(99,102,241,0.8)]" style="width: <?= $load_pct ?>%;"></div>
                    </div>
                    
                    <div class="flex justify-between items-center text-xs text-slate-300 font-medium">
                        <span>Active System Load: <strong class="text-white"><?= $load_pct ?>% Processing Workload</strong></span>
                        <span class="text-emerald-300 flex items-center gap-1 font-bold">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            POS Online & Ready for Operations
                        </span>
                    </div>

                    <!-- Developer Maintenance Connection Status -->
                    <?php if (!empty($maintenance_categories)): ?>
                    <div class="mt-4 pt-3 border-t border-slate-800 flex items-center justify-between gap-2 flex-wrap text-xs">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-slate-400 font-bold uppercase text-[10px] tracking-wider">Developer Maintenance Toggled:</span>
                            <?php foreach ($maintenance_categories as $cat): ?>
                                <span class="bg-rose-500/20 text-rose-300 border border-rose-500/30 px-2.5 py-0.5 rounded-lg font-semibold flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-400 animate-pulse"></span>
                                    <?= htmlspecialchars($cat) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($is_developer_user): ?>
                            <a href="index.php?page=maintenance" class="text-cyan-400 hover:text-cyan-300 underline font-bold text-[11px] ml-auto inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span data-i18n="Modify in Dev Center">Modify in Dev Center</span>
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                </div>

                <!-- Section 2: Global Voucher Totals & Multi-Currency Financial Breakdown -->
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-sm font-extrabold text-slate-700 uppercase tracking-wider flex items-center gap-2">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            Total Voucher Calculations & Shift Revenue
                        </h4>
                        <span class="text-xs font-bold text-slate-400">Current Database Sync</span>
                    </div>

                    <!-- Macro KPI Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mb-4">
                        <div class="bg-gradient-to-br from-blue-50 to-indigo-50/50 p-4 rounded-2xl border border-blue-100 shadow-sm">
                            <span class="text-[11px] font-bold text-blue-600 uppercase tracking-wider block mb-1">Total Vouchers</span>
                            <div class="text-2xl sm:text-3xl font-black text-slate-800"><?= number_format($total_vouchers) ?></div>
                            <span class="text-[11px] font-medium text-slate-500">Live system count</span>
                        </div>

                        <div class="bg-gradient-to-br from-emerald-50 to-teal-50/50 p-4 rounded-2xl border border-emerald-100 shadow-sm">
                            <span class="text-[11px] font-bold text-emerald-600 uppercase tracking-wider block mb-1">Total Cargo Weight</span>
                            <div class="text-2xl sm:text-3xl font-black text-slate-800"><?= number_format($total_weight_kg, 2) ?> <span class="text-sm font-bold text-emerald-600">kg</span></div>
                            <span class="text-[11px] font-medium text-slate-500">Gross freight volume</span>
                        </div>

                        <div class="bg-gradient-to-br from-amber-50 to-yellow-50/50 p-4 rounded-2xl border border-amber-100 shadow-sm">
                            <span class="text-[11px] font-bold text-amber-700 uppercase tracking-wider block mb-1">Pending Queue</span>
                            <div class="text-2xl sm:text-3xl font-black text-amber-800"><?= number_format($status_breakdown['Pending'] ?? 0) ?></div>
                            <span class="text-[11px] font-medium text-slate-500">Ready for dispatch</span>
                        </div>

                        <div class="bg-gradient-to-br from-purple-50 to-indigo-50/50 p-4 rounded-2xl border border-purple-100 shadow-sm">
                            <span class="text-[11px] font-bold text-purple-700 uppercase tracking-wider block mb-1">Delivered Rate</span>
                            <?php 
                                $deliv_count = $status_breakdown['Delivered'] ?? 0;
                                $deliv_pct = ($total_vouchers > 0) ? round(($deliv_count / $total_vouchers) * 100, 1) : 0;
                            ?>
                            <div class="text-2xl sm:text-3xl font-black text-purple-800"><?= $deliv_pct ?>%</div>
                            <span class="text-[11px] font-medium text-slate-500"><?= number_format($deliv_count) ?> delivered</span>
                        </div>
                    </div>

                    <!-- Currency Revenue Cards -->
                    <?php if (!empty($currency_totals)): ?>
                    <div class="bg-slate-50/80 rounded-2xl p-4 border border-slate-100">
                        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Multi-Currency Financial Totals:</span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <?php foreach ($currency_totals as $curr => $cdata): ?>
                                <div class="bg-white p-3 rounded-xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                                    <div>
                                        <span class="text-xs font-black px-2 py-0.5 bg-indigo-50 text-indigo-700 rounded border border-indigo-100"><?= $curr ?></span>
                                        <p class="text-xs text-slate-500 mt-1"><?= number_format($cdata['count']) ?> vouchers • <?= number_format($cdata['weight'], 1) ?> kg</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-base font-extrabold text-slate-900"><?= number_format($cdata['revenue'], 2) ?></p>
                                        <span class="text-[10px] font-bold text-emerald-600 uppercase">Gross Sum</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Section 3: Status Distribution & Item Breakdown (Using existing tables) -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                    
                    <!-- Status Distribution (Spans 6 cols) -->
                    <div class="lg:col-span-6 bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
                        <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4 flex items-center justify-between">
                            <span>Voucher Status Breakdown</span>
                            <span class="text-slate-400">Total: <?= number_format($total_vouchers) ?></span>
                        </h4>
                        
                        <div class="space-y-3">
                            <?php 
                            $status_colors = [
                                'Pending' => 'bg-amber-400 text-amber-900',
                                'In Transit' => 'bg-blue-500 text-white',
                                'Delivered' => 'bg-emerald-500 text-white',
                                'Received' => 'bg-teal-500 text-white',
                                'Cancelled' => 'bg-rose-500 text-white',
                                'Returned' => 'bg-orange-500 text-white',
                                'Maintenance' => 'bg-slate-500 text-white'
                            ];
                            foreach ($status_breakdown as $st_name => $count): 
                                $pct = ($total_vouchers > 0) ? round(($count / $total_vouchers) * 100, 1) : 0;
                            ?>
                                <div>
                                    <div class="flex justify-between items-center text-xs mb-1">
                                        <span class="font-bold text-slate-700"><?= htmlspecialchars($st_name) ?></span>
                                        <span class="font-mono text-slate-500"><?= number_format($count) ?> (<?= $pct ?>%)</span>
                                    </div>
                                    <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                        <div class="<?= $status_colors[$st_name] ?? 'bg-indigo-500' ?> h-2 rounded-full transition-all duration-700" style="width: <?= $pct ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Item Breakdown from voucher_breakdowns (Spans 6 cols) -->
                    <div class="lg:col-span-6 bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex flex-col">
                        <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4 flex items-center justify-between">
                            <span>Goods & Cargo Breakdown</span>
                            <span class="text-indigo-600 font-bold">Top Categories</span>
                        </h4>
                        
                        <?php if (empty($item_breakdown)): ?>
                            <div class="flex-1 flex flex-col items-center justify-center py-6 text-center text-slate-400 text-xs">
                                <svg class="w-8 h-8 mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                                General cargo distribution active
                            </div>
                        <?php else: ?>
                            <div class="space-y-2.5 flex-1 overflow-y-auto max-h-[220px] custom-scrollbar pr-1">
                                <?php foreach ($item_breakdown as $item): ?>
                                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50/70 border border-slate-100 hover:bg-slate-100/70 transition-colors">
                                        <div class="flex items-center gap-2.5 truncate">
                                            <div class="w-7 h-7 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs font-bold shrink-0">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                            </div>
                                            <span class="text-xs font-bold text-slate-800 truncate"><?= $item['item_type'] ?></span>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <span class="text-xs font-extrabold text-indigo-600"><?= number_format($item['kg'], 2) ?> kg</span>
                                            <span class="text-[10px] text-slate-400 block"><?= number_format($item['count']) ?> items</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- Section 4: Expandable Tech Support Contact Card -->
                <div id="tech-support-card" class="hidden bg-gradient-to-br from-indigo-900 via-slate-900 to-cyan-950 p-6 rounded-3xl text-white shadow-xl border border-indigo-500/30 transition-all animate-fadeInDown">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-cyan-400/20 border border-cyan-400/30 flex items-center justify-center text-cyan-300">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            </div>
                            <div>
                                <h4 class="font-extrabold text-base text-white">Direct Technical Support & Engineering</h4>
                                <p class="text-xs text-cyan-300">Payvia & MBPOS Infrastructure Team</p>
                            </div>
                        </div>
                        <button type="button" onclick="toggleTechSupportCard()" class="text-slate-400 hover:text-white p-1">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                        <!-- Lead Architect / Developer Portfolio -->
                        <a href="https://thuyakyaw.com" target="_blank" rel="noopener noreferrer" class="bg-white/10 hover:bg-white/20 p-3 rounded-xl border border-white/10 transition-all flex items-center gap-3" title="Thuya Kyaw — Lead Software Engineer & Architect">
                            <div class="w-8 h-8 rounded-lg bg-cyan-500/20 text-cyan-300 flex items-center justify-center font-bold text-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                            </div>
                            <div>
                                <span class="text-xs font-bold text-white block">Lead Architect</span>
                                <span class="text-[10px] text-slate-300">thuyakyaw.com</span>
                            </div>
                        </a>

                        <!-- Ecosystem Hub -->
                        <a href="https://payvia.cloud" target="_blank" rel="noopener noreferrer" class="bg-white/10 hover:bg-white/20 p-3 rounded-xl border border-white/10 transition-all flex items-center gap-3" title="Payvia Cloud Operations">
                            <div class="w-8 h-8 rounded-lg bg-indigo-500/20 text-indigo-300 flex items-center justify-center font-bold text-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                            </div>
                            <div>
                                <span class="text-xs font-bold text-white block">Payvia Cloud</span>
                                <span class="text-[10px] text-slate-300">payvia.cloud</span>
                            </div>
                        </a>

                        <!-- Email Support -->
                        <a href="mailto:support@payvia.cloud?subject=MBPOS%20High%20Load%20Support%20Request%20Token%20<?= $token ?>" class="bg-white/10 hover:bg-white/20 p-3 rounded-xl border border-white/10 transition-all flex items-center gap-3" title="Direct Email Support">
                            <div class="w-8 h-8 rounded-lg bg-rose-500/20 text-rose-300 flex items-center justify-center font-bold text-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            </div>
                            <div>
                                <span class="text-xs font-bold text-white block">Support Desk</span>
                                <span class="text-[10px] text-slate-300">support@payvia.cloud</span>
                            </div>
                        </a>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-black/30 p-3 rounded-2xl border border-white/10 text-xs">
                        <span class="text-slate-300">Share Diagnostic Token with Support: <strong class="font-mono text-cyan-300"><?= $token ?></strong></span>
                        <button type="button" onclick="copyDiagnosticToken('<?= $token ?>')" class="w-full sm:w-auto px-4 py-1.5 bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-extrabold rounded-xl transition-all shadow-md flex items-center justify-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                            <span id="copy-token-btn-text">Copy Token & Stats</span>
                        </button>
                    </div>
                </div>

            </div>

            <!-- Modal Action Footer (Fixed bottom on desktop & mobile) -->
            <div class="px-6 sm:px-8 py-4 sm:py-5 border-t border-gray-100 bg-slate-50/90 backdrop-blur-md flex flex-col-reverse sm:flex-row items-center justify-between gap-3 relative z-10 pb-6 sm:pb-5">
                
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <!-- Secondary: Contact Tech Support Trigger -->
                    <button type="button" onclick="toggleTechSupportCard()" class="flex-1 sm:flex-initial flex items-center justify-center gap-2 px-5 py-3 rounded-2xl bg-white hover:bg-slate-100 text-slate-700 font-bold text-sm border border-slate-200 shadow-sm transition-all hover:shadow-md hover:-translate-y-0.5 active:translate-y-0">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        <span>Contact Tech Company</span>
                    </button>

                    <!-- Developer Quick Access Button strictly for Developer Role only -->
                    <?php if ($is_developer_user): ?>
                        <a href="index.php?page=maintenance" class="p-3 rounded-2xl bg-slate-200/80 hover:bg-slate-300 text-slate-700 font-bold text-xs transition-colors flex items-center gap-1.5" title="Dev Center Maintenance Mode">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                            <span class="hidden sm:inline">Dev Mode</span>
                        </a>
                    <?php endif; ?>
                </div>


                <!-- Primary: Continue to Working Action -->
                <button type="button" onclick="closeMaintenanceModal(true)" class="w-full sm:w-auto flex items-center justify-center gap-2 px-8 py-3 rounded-2xl bg-gradient-to-r from-emerald-500 via-teal-600 to-indigo-600 text-white font-extrabold text-sm shadow-[0_8px_25px_rgba(16,185,129,0.35)] hover:shadow-[0_12px_30px_rgba(16,185,129,0.5)] transition-all transform hover:-translate-y-0.5 active:translate-y-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    <span>Continue to Working</span>
                </button>

            </div>

        </div>
    </div>
</div>
