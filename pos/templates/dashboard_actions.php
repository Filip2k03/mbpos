<?php
// pos/dashboard_actions.php - A shared template for dashboard action buttons.

// Make sure we have access to our role-checking functions
$is_admin = is_admin();
$is_developer = is_developer();
$is_staff = is_staff();
?>

<div class="bg-white p-8 rounded-2xl shadow-xl border border-gray-100">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-3xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
            Quick Actions
        </h2>
        <div class="h-1 flex-1 mx-4 bg-gradient-to-r from-blue-50 to-purple-50 rounded-full"></div>
        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
        </svg>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5 group">
        <!-- Common Actions -->
        <div class="space-y-5">
            <?php if ($is_admin || $is_developer || $is_staff): ?>
                <a href="index.php?page=voucher_create" class="action-card bg-gradient-to-br from-green-100 to-green-50 hover:from-green-200 transition-all">
                    <div class="icon-box bg-green-100">
                        <svg class="icon text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </div>
                    <span class="text-gradient bg-gradient-to-r from-green-600 to-emerald-600">Create Voucher</span>
                </a>
            <?php endif; ?>

            <a href="index.php?page=voucher_list" class="action-card bg-gradient-to-br from-blue-100 to-blue-50 hover:from-blue-200">
                <div class="icon-box bg-blue-100">
                    <svg class="icon text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <span class="text-gradient bg-gradient-to-r from-blue-600 to-sky-600">View Vouchers</span>
            </a>
        </div>

        <!-- Financial Section -->
        <div class="space-y-5">
                        <a href="index.php?page=voucher_bulk_update" class="action-card bg-gradient-to-br from-purple-100 to-purple-50 hover:from-purple-200">
                <div class="icon-box bg-purple-100">
                    <svg class="icon text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M7 7l13 13M20 20v-5h-5"/></svg>
                </div>
                <span class="text-gradient bg-gradient-to-r from-purple-600 to-fuchsia-600">Voucher Update</span>
            </a>

            <a href="index.php?page=expenses" class="action-card bg-gradient-to-br from-orange-100 to-orange-50 hover:from-orange-200">
                <div class="icon-box bg-orange-100">
                   <svg class="icon text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5-2.5a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0zM19.5 16.5a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/></svg>
                </div>
                <span class="text-gradient bg-gradient-to-r from-orange-600 to-red-600">Manage Expenses</span>
            </a>
        </div>

        <?php if ($is_admin || $is_developer): ?>
        <!-- Management Section -->
        <div class="space-y-5">
            <a href="index.php?page=profit_loss" class="action-card bg-gradient-to-br from-yellow-100 to-yellow-50 hover:from-yellow-200">
                <div class="icon-box bg-yellow-100">
                    <svg class="icon text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <span class="text-gradient bg-gradient-to-r from-amber-600 to-orange-600">Profit / Loss</span>
            </a>
            
            <a href="index.php?page=other_income" class="action-card bg-gradient-to-br from-pink-100 to-pink-50 hover:from-pink-200">
                <div class="icon-box bg-pink-100">
                    <svg class="icon text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </div>
                <span class="text-gradient bg-gradient-to-r from-pink-600 to-rose-600">Other Income</span>
            </a>
        </div>

        <!-- Admin Tools -->

        <div class="space-y-5">
            <a href="index.php?page=customer_list" class="action-card bg-gradient-to-br from-cyan-100 to-cyan-50 hover:from-cyan-200">
                <div class="icon-box bg-cyan-100">
                   <svg class="icon text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <span class="text-gradient bg-gradient-to-r from-cyan-600 to-sky-600">Customer List</span>
            </a>
            <a href="index.php?page=register" class="action-card bg-gradient-to-br from-teal-100 to-teal-50 hover:from-teal-200">
                <div class="icon-box bg-teal-100">
                   <svg class="icon text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                </div>
                <span class="text-gradient bg-gradient-to-r from-teal-600 to-emerald-600">Register Staff</span>
            </a>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if ($is_staff): ?>
    <div class="mt-8">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">Old Pos</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5 group">
              <a href="http://old.pos.mblogistics.express" target="_blank" class="action-card bg-gradient-to-br from-gray-100 to-gray-50 hover:from-gray-200">
                <div class="icon-box bg-gray-100"><svg class="icon text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg></div>
                <span class="text-gradient bg-gradient-to-r from-gray-600 to-slate-600">Old POS</span>
            </a>
            </div>
    </div>
            <?php endif; ?>

     <!-- System & Site Management -->
    <?php if ($is_admin || $is_developer): ?>
    <div class="mt-8">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">System & Site Management</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5 group">
            <a href="http://mblogistics.express" target="_blank" class="action-card bg-gradient-to-br from-gray-100 to-gray-50 hover:from-gray-200">
                <div class="icon-box bg-gray-100"><svg class="icon text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg></div>
                <span class="text-gradient bg-gradient-to-r from-gray-600 to-slate-600">Website Preview</span>
            </a>
             <a href="http://customer.mblogistics.express" target="_blank" class="action-card bg-gradient-to-br from-gray-100 to-gray-50 hover:from-gray-200">
                <div class="icon-box bg-gray-100"><svg class="icon text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg></div>
                <span class="text-gradient bg-gradient-to-r from-gray-600 to-slate-600">Customer Portal</span>
            </a>
             <a href="http://cms.pos.mblogistics.express" target="_blank" class="action-card bg-gradient-to-br from-gray-100 to-gray-50 hover:from-gray-200">
                <div class="icon-box bg-gray-100"><svg class="icon text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg></div>
                <span class="text-gradient bg-gradient-to-r from-gray-600 to-slate-600">CMS Portal</span>
            </a>
             <?php if ($is_staff): ?>
              <a href="http://old.pos.mblogistics.express" target="_blank" class="action-card bg-gradient-to-br from-gray-100 to-gray-50 hover:from-gray-200">
                <div class="icon-box bg-gray-100"><svg class="icon text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg></div>
                <span class="text-gradient bg-gradient-to-r from-gray-600 to-slate-600">Old POS</span>
            </a>
            <?php endif; ?>
             <a href="http://old.pos.mblogistics.express" target="_blank" class="action-card bg-gradient-to-br from-gray-100 to-gray-50 hover:from-gray-200">
                <div class="icon-box bg-gray-100"><svg class="icon text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg></div>
                <span class="text-gradient bg-gradient-to-r from-gray-600 to-slate-600">Old POS</span>
            </a>
        </div>
    </div>
    <?php endif; ?>
<?php if ($is_admin || $is_developer): ?>
    <div class="mt-8">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">System Data</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5 group">
            <a href="index.php?page=currencies" class="action-card bg-gradient-to-br from-gray-100 to-gray-50 hover:from-gray-200">
                <div class="icon-box bg-gray-100"><svg class="icon text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                <span class="text-gradient bg-gradient-to-r from-gray-600 to-slate-600">Currencies</span>
            </a>
            <a href="index.php?page=item_types" class="action-card bg-gradient-to-br from-gray-100 to-gray-50 hover:from-gray-200">
                <div class="icon-box bg-gray-100"><svg class="icon text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg></div>
                <span class="text-gradient bg-gradient-to-r from-gray-600 to-slate-600">Item Types</span>
            </a>
            <a href="index.php?page=delivery_types" class="action-card bg-gradient-to-br from-gray-100 to-gray-50 hover:from-gray-200">
                <div class="icon-box bg-gray-100"><svg class="icon text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg></div>
                <span class="text-gradient bg-gradient-to-r from-gray-600 to-slate-600">Delivery Types</span>
            </a>
             <?php if ($is_developer): ?>
                <a href="index.php?page=maintenance" class="action-card bg-gradient-to-br from-red-100 to-red-50 hover:from-red-200">
                     <div class="icon-box bg-red-100"><svg class="icon text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg></div>
                    <span class="text-gradient bg-gradient-to-r from-red-600 to-orange-600">Maintenance</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
   
</div>
<?php endif; ?>

