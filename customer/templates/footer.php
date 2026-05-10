<?php
// customer/templates/footer.php
?>
    </main>
    
    <!-- Floating Mobile Nav - Now only shows if the customer is logged in -->
    <?php if(is_customer_logged_in()): ?>
    <div class="md:hidden fixed bottom-0 left-0 right-0 bg-white shadow-[0_-2px_10px_rgba(0,0,0,0.1)] z-50 rounded-t-2xl">
        <div class="flex justify-around items-center h-16">
            <a href="index.php?page=dashboard" class="flex flex-col items-center text-gray-600 hover:text-blue-600 transition-colors">
                <svg class="h-6 w-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span class="text-xs">Dashboard</span>
            </a>
            <a href="index.php?page=logout" class="flex flex-col items-center text-gray-600 hover:text-red-600 transition-colors">
                 <svg class="h-6 w-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H3"></path></svg>
                <span class="text-xs">Logout</span>
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <footer class="text-center mt-8 py-6 text-sm text-gray-500 border-t">
        <p class="mb-2">&copy; <?= date('Y') ?> MBLOGISTICS. All rights reserved.</p>
        <a href="https://payvia.asia" target="_blank" rel="noopener noreferrer" class="inline-flex items-center text-gray-400 hover:text-blue-500 transition-colors">
            <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2H4zm0 6a2 2 0 00-2 2v4a2 2 0 002 2h12a2 2 0 002-2v-4a2 2 0 00-2-2H4z"></path></svg>
            <span>Powered by payvia.asia</span>
        </a>
    </footer>
</body>
</html>

