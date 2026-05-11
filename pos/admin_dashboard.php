<?php
// pos/admin_dashboard.php - Admin-specific dashboard view (Premium V3).
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
$total_vouchers = mysqli_fetch_assoc($total_vouchers_result)['total'];

$total_users_result = mysqli_query($connection, "SELECT COUNT(id) AS total FROM users");
$total_users = mysqli_fetch_assoc($total_users_result)['total'];

// Assuming a default base currency for the aggregate view, otherwise it's just a numeric total
$total_revenue_result = mysqli_query($connection, "SELECT SUM(total_amount) AS total FROM vouchers");
$total_revenue = mysqli_fetch_assoc($total_revenue_result)['total'] ?? 0;

include_template('header', ['page' => 'admin_dashboard']);
?>

<!-- V3 Liquid UI Wrapper -->
<div class="relative min-h-[85vh] bg-slate-50/50 p-4 sm:p-8 overflow-hidden font-sans">
    
    <!-- Ambient Background Glows -->
    <div class="absolute top-[0%] left-[-5%] w-[600px] h-[600px] bg-blue-500/10 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-[10%] right-[-5%] w-[500px] h-[500px] bg-amber-500/10 rounded-full blur-[120px] pointer-events-none"></div>

    <div class="max-w-7xl mx-auto relative z-10">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-5 animate-fadeInDown">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 bg-gradient-to-br from-blue-600 to-indigo-800 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/30 text-white transform -rotate-3 hover:rotate-0 transition-transform duration-300 border border-blue-400/20">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </div>
                <div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent tracking-tight">Admin Control Center</h1>
                    <p class="text-sm font-medium text-slate-500 mt-1">High-level operational metrics and systemic oversight.</p>
                </div>
            </div>
            
            <div class="flex items-center gap-3 bg-white/70 backdrop-blur-md border border-white/60 px-5 py-2.5 rounded-2xl shadow-sm">
                <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                <span class="text-sm font-bold text-slate-700">Administrator Access</span>
            </div>
        </div>

        <!-- Metrics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8 mb-10 animate-fadeInDown" style="animation-delay: 0.1s;">
            
            <!-- Total Vouchers Metric -->
            <div class="bg-white/80 backdrop-blur-2xl rounded-[2rem] p-8 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-white/60 relative overflow-hidden group hover:-translate-y-1 transition-transform duration-300">
                <div class="absolute -right-6 -top-6 w-32 h-32 bg-blue-500/10 rounded-full blur-2xl group-hover:bg-blue-500/20 transition-colors"></div>
                <div class="relative z-10">
                    <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center mb-6 shadow-inner">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <p class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-1">Global Vouchers Processed</p>
                    <h3 class="text-4xl font-extrabold text-slate-800"><?php echo number_format($total_vouchers); ?></h3>
                </div>
            </div>

            <!-- Total Users Metric -->
            <div class="bg-white/80 backdrop-blur-2xl rounded-[2rem] p-8 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-white/60 relative overflow-hidden group hover:-translate-y-1 transition-transform duration-300">
                <div class="absolute -right-6 -top-6 w-32 h-32 bg-emerald-500/10 rounded-full blur-2xl group-hover:bg-emerald-500/20 transition-colors"></div>
                <div class="relative z-10">
                    <div class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-xl flex items-center justify-center mb-6 shadow-inner">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    </div>
                    <p class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-1">Registered Personnel</p>
                    <h3 class="text-4xl font-extrabold text-slate-800"><?php echo number_format($total_users); ?></h3>
                </div>
            </div>

            <!-- Total Revenue Metric -->
            <div class="bg-white/80 backdrop-blur-2xl rounded-[2rem] p-8 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-white/60 relative overflow-hidden group hover:-translate-y-1 transition-transform duration-300">
                <div class="absolute -right-6 -top-6 w-32 h-32 bg-amber-500/10 rounded-full blur-2xl group-hover:bg-amber-500/20 transition-colors"></div>
                <div class="relative z-10">
                    <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-xl flex items-center justify-center mb-6 shadow-inner">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <p class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-1">Gross Ledger Revenue</p>
                    <h3 class="text-4xl font-extrabold text-slate-800"><span class="text-2xl text-slate-400 font-bold">$</span><?php echo number_format($total_revenue, 2); ?></h3>
                </div>
            </div>

        </div>

        <!-- Administrative Quick Actions -->
        <div class="bg-white/70 backdrop-blur-2xl rounded-[2.5rem] shadow-[0_8px_40px_rgb(0,0,0,0.04)] border border-white/60 p-8 sm:p-10 animate-fadeInDown" style="animation-delay: 0.2s;">
            <h2 class="text-xl font-bold text-slate-800 mb-8 flex items-center gap-3">
                <span class="w-2 h-2 rounded-full bg-indigo-500"></span> 
                Administrative Actions
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                
                <!-- Action: User Management -->
                <a href="index.php?page=register" class="group flex flex-col bg-slate-50/80 hover:bg-white border border-slate-100 hover:border-indigo-200 rounded-3xl p-6 transition-all duration-300 hover:shadow-[0_8px_30px_-5px_rgba(99,102,241,0.2)] hover:-translate-y-1">
                    <div class="w-14 h-14 bg-indigo-100 text-indigo-600 rounded-2xl flex items-center justify-center mb-5 group-hover:scale-110 transition-transform shadow-inner">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    </div>
                    <span class="font-extrabold text-slate-800 text-lg mb-1 group-hover:text-indigo-700 transition-colors">User Management</span>
                    <span class="text-sm font-medium text-slate-500">Register and manage staff access.</span>
                </a>

                <!-- Action: System Ledger -->
                <a href="index.php?page=voucher_list" class="group flex flex-col bg-slate-50/80 hover:bg-white border border-slate-100 hover:border-blue-200 rounded-3xl p-6 transition-all duration-300 hover:shadow-[0_8px_30px_-5px_rgba(59,130,246,0.2)] hover:-translate-y-1">
                    <div class="w-14 h-14 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center mb-5 group-hover:scale-110 transition-transform shadow-inner">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    </div>
                    <span class="font-extrabold text-slate-800 text-lg mb-1 group-hover:text-blue-700 transition-colors">Global Ledger</span>
                    <span class="text-sm font-medium text-slate-500">View and audit all system vouchers.</span>
                </a>

                <!-- Action: Branches -->
                <a href="index.php?page=branches" class="group flex flex-col bg-slate-50/80 hover:bg-white border border-slate-100 hover:border-cyan-200 rounded-3xl p-6 transition-all duration-300 hover:shadow-[0_8px_30px_-5px_rgba(6,182,212,0.2)] hover:-translate-y-1">
                    <div class="w-14 h-14 bg-cyan-100 text-cyan-600 rounded-2xl flex items-center justify-center mb-5 group-hover:scale-110 transition-transform shadow-inner">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </div>
                    <span class="font-extrabold text-slate-800 text-lg mb-1 group-hover:text-cyan-700 transition-colors">Branch Architecture</span>
                    <span class="text-sm font-medium text-slate-500">Configure regions and operating nodes.</span>
                </a>

                <!-- Action: Financials -->
                <a href="index.php?page=profit_loss" class="group flex flex-col bg-slate-50/80 hover:bg-white border border-slate-100 hover:border-emerald-200 rounded-3xl p-6 transition-all duration-300 hover:shadow-[0_8px_30px_-5px_rgba(16,185,129,0.2)] hover:-translate-y-1">
                    <div class="w-14 h-14 bg-emerald-100 text-emerald-600 rounded-2xl flex items-center justify-center mb-5 group-hover:scale-110 transition-transform shadow-inner">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <span class="font-extrabold text-slate-800 text-lg mb-1 group-hover:text-emerald-700 transition-colors">Profit / Loss</span>
                    <span class="text-sm font-medium text-slate-500">Analyze overall system financials.</span>
                </a>

            </div>
        </div>
        
    </div>
</div>

<style>
    /* V3 Fade In Animation */
    @keyframes fadeInDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fadeInDown {
        animation: fadeInDown 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        opacity: 0; /* Starts hidden until animation */
    }
</style>

<?php include_template('footer'); ?>