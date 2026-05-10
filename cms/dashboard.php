<?php
// public_website/cms/dashboard.php - The main dashboard for the CMS.

require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!is_cms_admin()) {
    redirect('index.php?page=login');
}

include_template('header', ['title' => 'CMS Dashboard']);
?>
<div class="bg-white p-8 rounded-2xl shadow-xl border border-gray-100">
    <h1 class="text-3xl font-bold mb-4">Welcome to the Website CMS</h1>
    <p class="text-gray-600 mb-6">From here, you can manage the content of your public-facing website.</p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <a href="index.php?page=manage_routes" class="action-card bg-gradient-to-br from-blue-100 to-blue-50 hover:from-blue-200">
            <div class="icon-box bg-blue-100">
                <svg class="icon text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.447 2.724A1 1 0 0021 16.382V5.618a1 1 0 00-1.447-.894L15 7m-6 3l6-3"/></svg>
            </div>
            <span class="text-gradient bg-gradient-to-r from-blue-600 to-sky-600">Manage Rates & Routes</span>
        </a>
        <!-- Add more management cards here as your CMS grows -->
    </div>
</div>
<?php
include_template('footer');
?>
