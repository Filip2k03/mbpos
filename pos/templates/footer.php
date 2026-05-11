<?php
// templates/footer.php - Premium V3 Liquid Glass Footer

// The $page variable is passed to include_template function
$page = $page ?? 'home'; // fallback page name
?>
</main> <!-- Closes the <main> tag opened in header.php -->

<!-- Spacer to ensure mobile nav doesn't overlap footer content on small screens -->
<div class="h-20 md:hidden"></div>

<footer class="relative z-10 bg-white/60 backdrop-blur-xl border-t border-white/80 mt-auto shadow-[0_-8px_30px_rgb(0,0,0,0.02)] overflow-hidden">
    
    <!-- Ambient Background Glows inside Footer -->
    <div class="absolute top-0 right-[10%] w-64 h-64 bg-indigo-500/10 rounded-full blur-[80px] pointer-events-none"></div>
    <div class="absolute bottom-0 left-[10%] w-64 h-64 bg-cyan-500/10 rounded-full blur-[80px] pointer-events-none"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-10 relative z-10">
        <div class="flex flex-col md:flex-row justify-between items-center gap-6">
            
            <!-- Left: Branding -->
            <div class="flex flex-col items-center md:items-start">
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 flex items-center justify-center shadow-md shadow-indigo-500/30 text-white">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <span class="text-xl font-extrabold bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent tracking-tight">
                        <?php echo defined('APP_NAME') ? APP_NAME : 'MBLOGISTICS'; ?>
                    </span>
                </div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">&copy; <?php echo date('Y'); ?> All rights reserved.</p>
            </div>

            <!-- Center: Ecosystem Links -->
            <div class="flex flex-wrap justify-center items-center gap-2 bg-slate-50/80 p-1.5 rounded-2xl border border-slate-100 shadow-sm">
                <a href="https://payvia.asia" target="_blank" class="px-4 py-2 rounded-xl text-[11px] sm:text-xs font-bold text-purple-600 hover:bg-white hover:shadow-sm hover:text-purple-700 transition-all flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    Payvia.asia
                </a>
                <div class="w-px h-6 bg-slate-200 hidden sm:block"></div>
                <a href="https://payvia.space" target="_blank" class="px-4 py-2 rounded-xl text-[11px] sm:text-xs font-bold text-indigo-600 hover:bg-white hover:shadow-sm hover:text-indigo-700 transition-all flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    Payvia.space
                </a>
            </div>

            <!-- Right: Developer Signature -->
            <div class="flex items-center gap-2 mt-2 md:mt-0">
                <span class="text-xs font-medium text-slate-400">Engineered by</span>
                <a href="https://techyyfilip.vercel.app" target="_blank" class="group flex items-center gap-2 bg-slate-900 px-3.5 py-2 rounded-xl hover:shadow-[0_0_15px_rgba(6,182,212,0.3)] transition-all hover:-translate-y-0.5">
                    <div class="w-2 h-2 rounded-full bg-cyan-400 group-hover:animate-ping shadow-[0_0_8px_rgba(34,211,238,0.8)]"></div>
                    <span class="text-xs font-bold text-white tracking-wide">TechyyFilip</span>
                </a>
            </div>
            
        </div>
    </div>
</footer>

</body>
</html>