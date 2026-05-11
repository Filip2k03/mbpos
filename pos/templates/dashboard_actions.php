<?php
// pos/templates/dashboard_actions.php - A shared template for dashboard action buttons.

// Make sure we have access to our role-checking functions
$is_admin = is_admin();
$is_developer = is_developer();
$is_staff = is_staff();
?>

<!-- V3 Dashboard UI Wrapper -->
<div class="relative bg-white/70 backdrop-blur-2xl p-8 sm:p-10 rounded-[2.5rem] shadow-[0_8px_40px_rgb(0,0,0,0.04)] border border-white/60 overflow-hidden">
    
    <!-- Subtle Background Elements -->
    <div class="absolute top-0 right-0 -mt-20 -mr-20 w-80 h-80 bg-blue-400/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute bottom-0 left-0 -mb-20 -ml-20 w-80 h-80 bg-purple-400/10 rounded-full blur-3xl pointer-events-none"></div>

    <!-- Header Section -->
    <div class="flex items-center justify-between mb-8 relative z-10">
        <div class="flex items-center gap-4">
            <div class="p-3 bg-gradient-to-br from-blue-600 to-purple-600 rounded-2xl shadow-lg shadow-blue-500/30">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <h2 class="text-3xl font-extrabold bg-gradient-to-r from-gray-900 to-gray-600 bg-clip-text text-transparent tracking-tight">
                Quick Actions
            </h2>
        </div>
        <div class="hidden sm:block h-1 flex-1 mx-8 bg-gradient-to-r from-blue-500/10 via-purple-500/10 to-transparent rounded-full"></div>
    </div>

    <!-- Quick Actions Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 relative z-10">
        
        <!-- ================= COMMON ACTIONS ================= -->
        <div class="space-y-6">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider pl-2">Operations</h3>
            
            <?php if ($is_admin || $is_developer || $is_staff): ?>
                <a href="index.php?page=voucher_create" class="group block relative bg-white/50 hover:bg-white border border-gray-100 hover:border-green-200 rounded-3xl p-5 transition-all duration-300 hover:-translate-y-1.5 hover:shadow-[0_12px_30px_-10px_rgba(16,185,129,0.3)]">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-gradient-to-br from-green-400 to-emerald-600 shadow-lg shadow-green-500/30 text-white transform group-hover:scale-110 group-hover:rotate-3 transition-all duration-300">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </div>
                        <div class="flex flex-col">
                            <span class="font-bold text-gray-800 group-hover:text-emerald-600 transition-colors">Create Voucher</span>
                            <span class="text-xs text-gray-500 font-medium">Generate new entry</span>
                        </div>
                    </div>
                </a>
            <?php endif; ?>

            <a href="index.php?page=voucher_list" class="group block relative bg-white/50 hover:bg-white border border-gray-100 hover:border-blue-200 rounded-3xl p-5 transition-all duration-300 hover:-translate-y-1.5 hover:shadow-[0_12px_30px_-10px_rgba(59,130,246,0.3)]">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-gradient-to-br from-blue-400 to-blue-600 shadow-lg shadow-blue-500/30 text-white transform group-hover:scale-110 group-hover:rotate-3 transition-all duration-300">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div class="flex flex-col">
                        <span class="font-bold text-gray-800 group-hover:text-blue-600 transition-colors">View Vouchers</span>
                        <span class="text-xs text-gray-500 font-medium">Browse active records</span>
                    </div>
                </div>
            </a>
        </div>

        <!-- ================= FINANCIAL SECTION ================= -->
        <div class="space-y-6">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider pl-2">Financial</h3>
            
            <a href="index.php?page=voucher_bulk_update" class="group block relative bg-white/50 hover:bg-white border border-gray-100 hover:border-purple-200 rounded-3xl p-5 transition-all duration-300 hover:-translate-y-1.5 hover:shadow-[0_12px_30px_-10px_rgba(147,51,234,0.3)]">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-gradient-to-br from-purple-400 to-purple-600 shadow-lg shadow-purple-500/30 text-white transform group-hover:scale-110 group-hover:rotate-3 transition-all duration-300">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M7 7l13 13M20 20v-5h-5"/></svg>
                    </div>
                    <div class="flex flex-col">
                        <span class="font-bold text-gray-800 group-hover:text-purple-600 transition-colors">Voucher Update</span>
                        <span class="text-xs text-gray-500 font-medium">Bulk modify records</span>
                    </div>
                </div>
            </a>

            <a href="index.php?page=expenses" class="group block relative bg-white/50 hover:bg-white border border-gray-100 hover:border-orange-200 rounded-3xl p-5 transition-all duration-300 hover:-translate-y-1.5 hover:shadow-[0_12px_30px_-10px_rgba(249,115,22,0.3)]">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-gradient-to-br from-orange-400 to-orange-600 shadow-lg shadow-orange-500/30 text-white transform group-hover:scale-110 group-hover:rotate-3 transition-all duration-300">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5-2.5a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0zM19.5 16.5a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/></svg>
                    </div>
                    <div class="flex flex-col">
                        <span class="font-bold text-gray-800 group-hover:text-orange-600 transition-colors">Manage Expenses</span>
                        <span class="text-xs text-gray-500 font-medium">Track outgoings</span>
                    </div>
                </div>
            </a>
        </div>

        <?php if ($is_admin || $is_developer): ?>
        <!-- ================= MANAGEMENT SECTION ================= -->
        <div class="space-y-6">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider pl-2">Management</h3>
            
            <a href="index.php?page=profit_loss" class="group block relative bg-white/50 hover:bg-white border border-gray-100 hover:border-yellow-200 rounded-3xl p-5 transition-all duration-300 hover:-translate-y-1.5 hover:shadow-[0_12px_30px_-10px_rgba(234,179,8,0.3)]">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-gradient-to-br from-yellow-400 to-amber-600 shadow-lg shadow-yellow-500/30 text-white transform group-hover:scale-110 group-hover:rotate-3 transition-all duration-300">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="flex flex-col">
                        <span class="font-bold text-gray-800 group-hover:text-amber-600 transition-colors">Profit / Loss</span>
                        <span class="text-xs text-gray-500 font-medium">Financial overview</span>
                    </div>
                </div>
            </a>
            
            <a href="index.php?page=other_income" class="group block relative bg-white/50 hover:bg-white border border-gray-100 hover:border-pink-200 rounded-3xl p-5 transition-all duration-300 hover:-translate-y-1.5 hover:shadow-[0_12px_30px_-10px_rgba(236,72,153,0.3)]">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-gradient-to-br from-pink-400 to-rose-600 shadow-lg shadow-pink-500/30 text-white transform group-hover:scale-110 group-hover:rotate-3 transition-all duration-300">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                    </div>
                    <div class="flex flex-col">
                        <span class="font-bold text-gray-800 group-hover:text-rose-600 transition-colors">Other Income</span>
                        <span class="text-xs text-gray-500 font-medium">Extra revenue streams</span>
                    </div>
                </div>
            </a>
        </div>

        <!-- ================= ADMIN TOOLS ================= -->
        <div class="space-y-6">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider pl-2">Admin Tools</h3>
            
            <a href="index.php?page=customer_list" class="group block relative bg-white/50 hover:bg-white border border-gray-100 hover:border-cyan-200 rounded-3xl p-5 transition-all duration-300 hover:-translate-y-1.5 hover:shadow-[0_12px_30px_-10px_rgba(6,182,212,0.3)]">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-gradient-to-br from-cyan-400 to-sky-600 shadow-lg shadow-cyan-500/30 text-white transform group-hover:scale-110 group-hover:rotate-3 transition-all duration-300">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div class="flex flex-col">
                        <span class="font-bold text-gray-800 group-hover:text-cyan-600 transition-colors">Customer List</span>
                        <span class="text-xs text-gray-500 font-medium">Manage clientele</span>
                    </div>
                </div>
            </a>

            <a href="index.php?page=register" class="group block relative bg-white/50 hover:bg-white border border-gray-100 hover:border-teal-200 rounded-3xl p-5 transition-all duration-300 hover:-translate-y-1.5 hover:shadow-[0_12px_30px_-10px_rgba(20,184,166,0.3)]">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-gradient-to-br from-teal-400 to-emerald-600 shadow-lg shadow-teal-500/30 text-white transform group-hover:scale-110 group-hover:rotate-3 transition-all duration-300">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    </div>
                    <div class="flex flex-col">
                        <span class="font-bold text-gray-800 group-hover:text-teal-600 transition-colors">Register Staff</span>
                        <span class="text-xs text-gray-500 font-medium">Add new accounts</span>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- ================= EXTERNAL PORTALS ================= -->
    <?php if ($is_staff): ?>
    <div class="mt-12 pt-8 border-t border-gray-100 relative z-10">
        <h3 class="text-lg font-bold text-gray-800 mb-6 flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-gray-400"></span> External Links
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
            <a href="http://old.pos.mblogistics.express" target="_blank" class="group flex items-center gap-4 bg-gray-50 hover:bg-white border border-gray-200 hover:border-gray-300 rounded-2xl p-4 transition-all hover:shadow-md">
                <div class="p-3 bg-gray-200/50 rounded-xl text-gray-500 group-hover:text-gray-800 group-hover:scale-110 transition-all"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg></div>
                <span class="font-semibold text-gray-600 group-hover:text-gray-900">Old POS</span>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================= SYSTEM & SITE MANAGEMENT ================= -->
    <?php if ($is_admin || $is_developer): ?>
    <div class="mt-12 pt-8 border-t border-gray-100 relative z-10">
        <h3 class="text-lg font-bold text-gray-800 mb-6 flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-indigo-500"></span> System & Site Management
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
            
            <a href="http://mblogistics.express" target="_blank" class="group flex items-center gap-4 bg-indigo-50/50 hover:bg-indigo-50 border border-indigo-100 hover:border-indigo-300 rounded-2xl p-4 transition-all hover:shadow-md">
                <div class="p-3 bg-indigo-100 rounded-xl text-indigo-500 group-hover:text-indigo-700 group-hover:scale-110 transition-all"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg></div>
                <span class="font-semibold text-indigo-900 group-hover:text-indigo-700">Website Preview</span>
            </a>
            
            <a href="http://customer.mbpos.online" target="_blank" class="group flex items-center gap-4 bg-indigo-50/50 hover:bg-indigo-50 border border-indigo-100 hover:border-indigo-300 rounded-2xl p-4 transition-all hover:shadow-md">
                <div class="p-3 bg-indigo-100 rounded-xl text-indigo-500 group-hover:text-indigo-700 group-hover:scale-110 transition-all"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg></div>
                <span class="font-semibold text-indigo-900 group-hover:text-indigo-700">Customer Portal</span>
            </a>
            
            <a href="http://cms.mbpos.online" target="_blank" class="group flex items-center gap-4 bg-indigo-50/50 hover:bg-indigo-50 border border-indigo-100 hover:border-indigo-300 rounded-2xl p-4 transition-all hover:shadow-md">
                <div class="p-3 bg-indigo-100 rounded-xl text-indigo-500 group-hover:text-indigo-700 group-hover:scale-110 transition-all"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg></div>
                <span class="font-semibold text-indigo-900 group-hover:text-indigo-700">CMS Portal</span>
            </a>
            
            <a href="http://old.mbpos.online" target="_blank" class="group flex items-center gap-4 bg-indigo-50/50 hover:bg-indigo-50 border border-indigo-100 hover:border-indigo-300 rounded-2xl p-4 transition-all hover:shadow-md">
                <div class="p-3 bg-indigo-100 rounded-xl text-indigo-500 group-hover:text-indigo-700 group-hover:scale-110 transition-all"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg></div>
                <span class="font-semibold text-indigo-900 group-hover:text-indigo-700">Old POS System</span>
            </a>
        </div>
    </div>

    <!-- ================= SYSTEM DATA ================= -->
    <div class="mt-12 pt-8 border-t border-gray-100 relative z-10">
        <h3 class="text-lg font-bold text-gray-800 mb-6 flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-slate-800"></span> System Data Configuration
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
            
            <a href="index.php?page=currencies" class="group flex items-center gap-4 bg-slate-50 hover:bg-white border border-slate-200 hover:border-slate-300 rounded-2xl p-4 transition-all hover:shadow-md">
                <div class="p-3 bg-slate-200 rounded-xl text-slate-600 group-hover:text-slate-800 group-hover:scale-110 transition-all"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                <span class="font-semibold text-slate-700 group-hover:text-slate-900">Currencies</span>
            </a>
            
            <a href="index.php?page=item_types" class="group flex items-center gap-4 bg-slate-50 hover:bg-white border border-slate-200 hover:border-slate-300 rounded-2xl p-4 transition-all hover:shadow-md">
                <div class="p-3 bg-slate-200 rounded-xl text-slate-600 group-hover:text-slate-800 group-hover:scale-110 transition-all"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg></div>
                <span class="font-semibold text-slate-700 group-hover:text-slate-900">Item Types</span>
            </a>
            
            <a href="index.php?page=delivery_types" class="group flex items-center gap-4 bg-slate-50 hover:bg-white border border-slate-200 hover:border-slate-300 rounded-2xl p-4 transition-all hover:shadow-md">
                <div class="p-3 bg-slate-200 rounded-xl text-slate-600 group-hover:text-slate-800 group-hover:scale-110 transition-all"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg></div>
                <span class="font-semibold text-slate-700 group-hover:text-slate-900">Delivery Types</span>
            </a>
            
             <?php if ($is_developer): ?>
                <a href="index.php?page=maintenance" class="group flex items-center gap-4 bg-red-50/50 hover:bg-red-50 border border-red-100 hover:border-red-300 rounded-2xl p-4 transition-all hover:shadow-md">
                     <div class="p-3 bg-red-100 rounded-xl text-red-600 group-hover:text-red-700 group-hover:scale-110 transition-all"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg></div>
                    <span class="font-semibold text-red-900 group-hover:text-red-700">Maintenance</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
   
</div>