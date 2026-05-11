<?php
// pos/dashboard.php - Main dashboard for staff and general users.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authentication & Role-based routing ---
if (!is_logged_in()) {
    redirect('index.php?page=login');
}

// Redirect Admins and Developers to their specific dashboards
// if (is_developer()) {
//     redirect('index.php?page=developer_dashboard');
// } elseif (is_admin()) {
//     redirect('index.php?page=admin_dashboard');
// }

global $connection;
$user_id = $_SESSION['user_id'];

// --- Fetch recent vouchers created by the current user ---
$recent_vouchers = [];
// FIX: Added 'currency' to the SELECT statement to fix the display bug.
$query = "SELECT id, voucher_code, receiver_name, total_amount, currency, status 
          FROM vouchers 
          WHERE created_by_user_id = ? 
          ORDER BY created_at DESC 
          LIMIT 5";
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

include_template('header', ['page' => 'dashboard']);
?>

<!-- V3 Liquid UI Wrapper -->
<div class="relative min-h-[85vh] bg-slate-50/50 p-4 sm:p-8 overflow-hidden font-sans">
    
    <!-- Ambient Background Glows -->
    <div class="absolute top-[0%] left-[-10%] w-[600px] h-[600px] bg-indigo-500/10 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-[10%] right-[-10%] w-[500px] h-[500px] bg-cyan-500/10 rounded-full blur-[120px] pointer-events-none"></div>

    <div class="max-w-7xl mx-auto relative z-10">
        
        <!-- Welcome Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-5 animate-fadeInDown">
            <div>
                <h1 class="text-3xl sm:text-4xl font-extrabold bg-gradient-to-r from-slate-900 to-indigo-800 bg-clip-text text-transparent tracking-tight">
                    Welcome back, <?= htmlspecialchars($_SESSION['username']); ?>
                </h1>
                <p class="text-sm font-medium text-slate-500 mt-1">Here's what's happening with your logistics operations today.</p>
            </div>
            <div class="hidden md:flex items-center gap-3 bg-white/70 backdrop-blur-md border border-white/60 px-5 py-2.5 rounded-2xl shadow-sm">
                <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                <span class="text-sm font-bold text-slate-700">System Online</span>
            </div>
        </div>

        <!-- Quick Actions Module (Injected V3 Template) -->
        <section class="mb-10 animate-fadeInDown" style="animation-delay: 0.1s;">
            <?php include_template('dashboard_actions'); ?>
        </section>

        <!-- Main Dashboard Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 animate-fadeInDown" style="animation-delay: 0.2s;">
            
            <!-- Recent Vouchers Table -->
            <div class="lg:col-span-8">
                <div class="bg-white/80 backdrop-blur-2xl rounded-[2.5rem] shadow-[0_8px_40px_rgb(0,0,0,0.04)] border border-white/60 overflow-hidden h-full flex flex-col">
                    
                    <div class="p-6 sm:p-8 border-b border-gray-100 flex items-center justify-between bg-white/50">
                        <h2 class="text-xl font-bold text-gray-800 flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                            </div>
                            Your Recent Vouchers
                        </h2>
                        <a href="index.php?page=voucher_list" class="text-sm font-bold text-indigo-600 hover:text-indigo-800 transition-colors bg-indigo-50 hover:bg-indigo-100 px-4 py-2 rounded-xl">View All</a>
                    </div>

                    <div class="overflow-x-auto w-full custom-scrollbar flex-1 p-2">
                        <table class="w-full text-left border-collapse whitespace-nowrap">
                            <thead>
                                <tr>
                                    <th class="py-4 px-6 text-xs font-extrabold text-gray-400 uppercase tracking-widest border-b border-gray-100">Tracking Code</th>
                                    <th class="py-4 px-6 text-xs font-extrabold text-gray-400 uppercase tracking-widest border-b border-gray-100">Receiver</th>
                                    <th class="py-4 px-6 text-xs font-extrabold text-gray-400 uppercase tracking-widest border-b border-gray-100">Value</th>
                                    <th class="py-4 px-6 text-xs font-extrabold text-gray-400 uppercase tracking-widest border-b border-gray-100">Status</th>
                                    <th class="py-4 px-6 text-xs font-extrabold text-gray-400 uppercase tracking-widest border-b border-gray-100 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100/60">
                                <?php if (empty($recent_vouchers)): ?>
                                    <tr>
                                        <td colspan="5" class="py-16 text-center">
                                            <div class="flex flex-col items-center justify-center text-gray-400">
                                                <svg class="w-12 h-12 mb-3 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                                                <span class="text-sm font-bold text-gray-500">No vouchers issued yet.</span>
                                                <a href="index.php?page=voucher_create" class="mt-4 text-xs font-bold text-indigo-600 bg-indigo-50 px-4 py-2 rounded-lg hover:bg-indigo-100 transition-colors">Create your first entry</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_vouchers as $voucher): 
                                        $statusClass = match(strtolower($voucher['status'])) {
                                            'pending' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                                            'in transit' => 'bg-blue-100 text-blue-700 border-blue-200',
                                            'delivered' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                                            'received' => 'bg-teal-100 text-teal-700 border-teal-200',
                                            'cancelled' => 'bg-red-100 text-red-700 border-red-200',
                                            'returned' => 'bg-orange-100 text-orange-700 border-orange-200',
                                            default => 'bg-gray-100 text-gray-600 border-gray-200',
                                        };
                                    ?>
                                        <tr class="hover:bg-white/90 transition-colors duration-200 group">
                                            <td class="py-4 px-6">
                                                <span class="inline-flex items-center gap-1.5 font-mono text-sm font-bold text-indigo-700 bg-indigo-50/50 px-2.5 py-1.5 rounded-lg border border-indigo-100 shadow-sm">
                                                    <?= htmlspecialchars($voucher['voucher_code']) ?>
                                                </span>
                                            </td>
                                            <td class="py-4 px-6">
                                                <span class="font-bold text-gray-800"><?= htmlspecialchars($voucher['receiver_name']) ?></span>
                                            </td>
                                            <td class="py-4 px-6">
                                                <span class="font-extrabold text-slate-700"><?= htmlspecialchars($voucher['currency']) ?> <?= number_format($voucher['total_amount'], 2) ?></span>
                                            </td>
                                            <td class="py-4 px-6">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] uppercase tracking-wider font-bold border <?= $statusClass ?> shadow-sm">
                                                    <?= htmlspecialchars($voucher['status']) ?>
                                                </span>
                                            </td>
                                            <td class="py-4 px-6 text-center">
                                                <a href="index.php?page=voucher_view&id=<?= $voucher['id'] ?>" class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-gray-50 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 hover:shadow-sm border border-transparent hover:border-indigo-100 transition-all" title="View Details">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Support / Cloud Services Card -->
            <div class="lg:col-span-4">
                <div class="bg-gradient-to-br from-slate-900 to-indigo-950 rounded-[2.5rem] shadow-2xl border border-slate-700 p-8 sm:p-10 flex flex-col items-center text-center relative overflow-hidden h-full group">
                    
                    <!-- Decorative Elements -->
                    <div class="absolute top-0 right-0 w-48 h-48 bg-indigo-500/20 rounded-bl-full pointer-events-none transition-transform group-hover:scale-110"></div>
                    <div class="absolute bottom-0 left-0 w-32 h-32 bg-cyan-500/20 rounded-tr-full pointer-events-none transition-transform group-hover:scale-110"></div>
                    
                    <div class="relative z-10 flex flex-col items-center h-full">
                        <div class="mb-6 bg-slate-800/80 p-5 rounded-3xl border border-slate-600 shadow-inner backdrop-blur-sm">
                            <svg class="w-10 h-10 text-cyan-400" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                                <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                                <line x1="12" y1="22.08" x2="12" y2="12"></line>
                            </svg>
                        </div>
                        
                        <h2 class="text-2xl font-bold text-white mb-3">Tech & Cloud Solutions</h2>
                        <p class="text-slate-400 text-sm mb-8 leading-relaxed">System architecture, seamless payments, and cloud infrastructure powered by TechyyFilip.</p>
                        
                        <div class="mt-auto w-full">
                            <button id="contactDeveloperBtn" class="w-full bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-white font-bold py-3.5 px-6 rounded-2xl shadow-[0_0_20px_rgba(6,182,212,0.3)] transition-all transform hover:-translate-y-1 flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                Access Tech Hub
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<!-- Premium V3 Glassmorphism Contact Modal -->
<div id="contactModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-md flex items-center justify-center hidden z-[100] transition-opacity duration-300 opacity-0">
    <div class="bg-white/90 backdrop-blur-2xl rounded-[2.5rem] shadow-2xl p-8 sm:p-10 w-full max-w-xl border border-white/60 transform scale-95 transition-transform duration-300 relative overflow-hidden" id="contactModalInner">
        
        <!-- Decorative bg -->
        <div class="absolute -top-24 -right-24 w-48 h-48 bg-indigo-500/10 rounded-full blur-2xl"></div>
        
        <button id="closeModalBtn" class="absolute top-6 right-6 w-10 h-10 bg-slate-100 text-slate-500 hover:text-slate-800 hover:bg-slate-200 rounded-full flex items-center justify-center transition-colors z-20">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
        
        <div class="relative z-10">
            <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-500/30 text-white mx-auto mb-6">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
            </div>
            
            <h2 class="text-3xl font-extrabold text-slate-900 mb-2 text-center tracking-tight">Developer Hub</h2>
            <p class="text-slate-500 text-center text-sm font-medium mb-8">Choose a support channel or explore our ecosystem.</p>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                
                <!-- Direct Contact -->
                <a href="tel:+959954480806" class="group bg-white border border-slate-200 hover:border-emerald-300 hover:bg-emerald-50 rounded-2xl p-4 flex flex-col items-center justify-center text-center transition-all hover:shadow-md hover:-translate-y-1">
                    <div class="w-10 h-10 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    </div>
                    <span class="font-bold text-slate-800 block text-sm">Direct Call</span>
                    <span class="text-xs text-slate-500 font-mono mt-1">+95 9954480806</span>
                </a>
                
                <!-- Telegram -->
                <a href="https://t.me/Stephanfilip" target="_blank" class="group bg-white border border-slate-200 hover:border-sky-300 hover:bg-sky-50 rounded-2xl p-4 flex flex-col items-center justify-center text-center transition-all hover:shadow-md hover:-translate-y-1">
                    <div class="w-10 h-10 bg-sky-100 text-sky-600 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8.287 5.906c-.778.324-2.334.994-4.608 1.976l-.623 2.406.846.065 1.706-.938c.834-.46 1.137-.624 1.288-.661.04-.017.071-.03.092-.04.072-.049.088-.047.172-.004.09.042.138.113.177.26.04.148.026.316-.06.495-.061.168-.117.29-.16.38-.11.234-.2.416-.232.462-.02.036-.026.044-.028.047-.002.003-.005.006-.008.01L7.182 11.89c-.194.165-.42.261-.603.261-.586 0-1.068-.377-1.391-.659-.62-.542-1.2-.956-1.282-1.003-.054-.03-.109-.048-.163-.048-.092 0-.125.016-.166.043l-.062.042-.164.117-.104.07a1 1 0 0 1-.354.129c-.326.076-.41.07-.517-.006l-.06-.05-.167-.145-1.47-1.336c-.44-.41-.75-.6-.916-.628-.06-.01-.105-.015-.147-.015-.093 0-.178.04-.252.115-.12.148-.18.276-.22.465-.036.162-.056.28-.064.307-.005.013-.008.016-.011.018-.002.002-.004.004-.007.006-.003.003-.006.005-.008.008a6.76 6.76 0 0 1-.162.09c-.026.012-.057.028-.087.04-.03.013-.058.022-.088.033-.3.093-.654.097-.7.091C.013 9.728 0 9.693 0 9.636c0-.024.029-.074.103-.178.026-.038.059-.074.097-.108l.056-.051 1.077-.965c1.078-.96 1.45-1.295 1.552-1.356.12-.072.247-.132.379-.18.237-.087.48-.17.714-.242.42-.137.833-.242 1.092-.261.685-.045 1.38-.104 2.052-.168.973-.096 1.254-.127 1.524-.127H16V8a8 8 0 0 0-7.713-7.906z"/></svg>
                    </div>
                    <span class="font-bold text-slate-800 block text-sm">Telegram Chat</span>
                    <span class="text-xs text-slate-500 font-mono mt-1">@stephanfilip2k03</span>
                </a>

                <!-- Payvia Asia -->
                <a href="https://payvia.asia" target="_blank" class="group bg-white border border-slate-200 hover:border-purple-300 hover:bg-purple-50 rounded-2xl p-4 flex flex-col items-center justify-center text-center transition-all hover:shadow-md hover:-translate-y-1">
                    <div class="w-10 h-10 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    </div>
                    <span class="font-bold text-slate-800 block text-sm">FinTech & Payments</span>
                    <span class="text-xs text-purple-600 font-bold mt-1 bg-purple-100/50 px-2 py-0.5 rounded">payvia.asia</span>
                </a>

                <!-- Payvia Space -->
                <a href="https://payvia.space" target="_blank" class="group bg-white border border-slate-200 hover:border-indigo-300 hover:bg-indigo-50 rounded-2xl p-4 flex flex-col items-center justify-center text-center transition-all hover:shadow-md hover:-translate-y-1">
                    <div class="w-10 h-10 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    </div>
                    <span class="font-bold text-slate-800 block text-sm">Cloud Infrastructure</span>
                    <span class="text-xs text-indigo-600 font-bold mt-1 bg-indigo-100/50 px-2 py-0.5 rounded">payvia.space</span>
                </a>
            </div>
            
            <div class="mt-6 text-center">
                 <p class="text-xs font-medium text-slate-400">Developed by <a href="https://techyyfilip.vercel.app" target="_blank" class="text-indigo-500 hover:underline">TechyyFilip</a> (Stephan)</p>
            </div>
        </div>
    </div>
</div>

<style>
    /* Sleek scrollbar for the table */
    .custom-scrollbar::-webkit-scrollbar {
        height: 6px;
        width: 6px;
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
    
    /* Animations */
    @keyframes fadeInDown {
        from { opacity: 0; transform: translateY(-15px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fadeInDown {
        animation: fadeInDown 0.4s ease-out forwards;
        opacity: 0; /* Starts hidden until animation */
    }
</style>

<script>
  const contactDeveloperBtn = document.getElementById('contactDeveloperBtn');
  const contactModal = document.getElementById('contactModal');
  const contactModalInner = document.getElementById('contactModalInner');
  const closeModalBtn = document.getElementById('closeModalBtn');

  function showModal() {
    contactModal.classList.remove('hidden');
    // Tiny delay to allow display:block to apply before animating opacity
    setTimeout(() => {
        contactModal.classList.remove('opacity-0');
        contactModalInner.classList.remove('scale-95');
        contactModalInner.classList.add('scale-100');
    }, 10);
  }
  function hideModal() {
    contactModal.classList.add('opacity-0');
    contactModalInner.classList.remove('scale-100');
    contactModalInner.classList.add('scale-95');
    // Wait for transition to finish before hiding completely
    setTimeout(() => {
        contactModal.classList.add('hidden');
    }, 300);
  }

  if (contactDeveloperBtn) contactDeveloperBtn.addEventListener('click', showModal);
  if (closeModalBtn) closeModalBtn.addEventListener('click', hideModal);
  if (contactModal) contactModal.addEventListener('click', e => { 
      // Close if clicking outside the inner modal box
      if(e.target === contactModal) hideModal(); 
  });
  document.addEventListener('keydown', e => { 
      if (e.key === 'Escape' && !contactModal.classList.contains('hidden')) hideModal(); 
  });
</script>

<?php
include_template('footer');
?>