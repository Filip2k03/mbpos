<?php
// pos/branches.php - Admin page for CRUD operations on branches (Premium V3 UI).

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authentication & Authorization ---
if (!is_logged_in() || (!is_admin() && !is_developer())) {
    flash_message('error', 'You are not authorized to access this page.');
    redirect('index.php?page=dashboard');
}

global $connection;

// --- CRITICAL FIX FOR MYANMAR FONTS ---
// Forces the database connection to use full UTF-8, preventing mojibake/garbled text
mysqli_set_charset($connection, "utf8mb4");

$edit_branch = null; // Variable to hold branch data for editing

// --- Handle DELETE Request ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $branch_id = intval($_GET['id']);
    $stmt = mysqli_prepare($connection, "DELETE FROM branches WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $branch_id);
    if (mysqli_stmt_execute($stmt)) {
        flash_message('success', 'Branch deleted successfully.');
    } else {
        flash_message('error', 'Failed to delete branch: ' . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
    redirect('index.php?page=branches');
}

// --- Handle POST Request (Add or Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_name = trim($_POST['branch_name']);
    $region_id = intval($_POST['region_id']);
    $branch_id = intval($_POST['branch_id'] ?? 0); // For updates

    if (empty($branch_name) || $region_id <= 0) {
        flash_message('error', 'Branch name and region are required.');
    } else {
        if ($branch_id > 0) { // Update existing branch
            $stmt = mysqli_prepare($connection, "UPDATE branches SET branch_name = ?, region_id = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'sii', $branch_name, $region_id, $branch_id);
            if (mysqli_stmt_execute($stmt)) {
                flash_message('success', 'Branch updated successfully.');
            } else {
                flash_message('error', 'Failed to update branch: ' . mysqli_stmt_error($stmt));
            }
        } else { // Add new branch
            $stmt = mysqli_prepare($connection, "INSERT INTO branches (branch_name, region_id) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, 'si', $branch_name, $region_id);
            if (mysqli_stmt_execute($stmt)) {
                flash_message('success', 'Branch added successfully.');
            } else {
                flash_message('error', 'Failed to add branch: ' . mysqli_stmt_error($stmt));
            }
        }
        mysqli_stmt_close($stmt);
        redirect('index.php?page=branches');
    }
}

// --- Handle EDIT Request (Fetch data for the form) ---
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $branch_id = intval($_GET['id']);
    $stmt = mysqli_prepare($connection, "SELECT id, branch_name, region_id FROM branches WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $branch_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $edit_branch = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}


// --- Fetch Data for Display ---
$branches = [];
$result = mysqli_query($connection, "SELECT b.id, b.branch_name, r.region_name FROM branches b JOIN regions r ON b.region_id = r.id ORDER BY r.region_name, b.branch_name");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $branches[] = $row;
    }
}

$regions = [];
$region_result = mysqli_query($connection, "SELECT id, region_name FROM regions ORDER BY region_name");
if ($region_result) {
    while ($row = mysqli_fetch_assoc($region_result)) {
        $regions[] = $row;
    }
}

include_template('header', ['page' => 'branches']);
?>

<!-- V3 Liquid UI Wrapper -->
<div class="relative min-h-[85vh] bg-slate-50/30 p-4 sm:p-8 overflow-hidden font-sans">
    
    <!-- Ambient Background Glows -->
    <div class="absolute top-[10%] left-[-10%] w-[500px] h-[500px] bg-indigo-500/10 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-[500px] h-[500px] bg-emerald-500/10 rounded-full blur-[120px] pointer-events-none"></div>

    <div class="max-w-7xl mx-auto relative z-10">
        
        <!-- Header -->
        <div class="flex items-center gap-4 mb-8">
            <div class="w-14 h-14 bg-gradient-to-br from-indigo-500 to-blue-600 rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-500/30 text-white transform -rotate-3 hover:rotate-0 transition-transform duration-300">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
            <div>
                <h1 class="text-3xl font-extrabold bg-gradient-to-r from-slate-900 to-slate-600 bg-clip-text text-transparent tracking-tight">Operating Branches</h1>
                <p class="text-sm font-medium text-slate-500">Configure regional hubs and branch architecture</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- LEFT COLUMN: CRUD FORM -->
            <div class="lg:col-span-4">
                <div class="bg-white/80 backdrop-blur-2xl p-6 sm:p-8 rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-white/60 sticky top-28">
                    
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                            <svg class="w-5 h-5 <?= $edit_branch ? 'text-amber-500' : 'text-indigo-500' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <?php if($edit_branch): ?>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                <?php else: ?>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                <?php endif; ?>
                            </svg>
                            <?= $edit_branch ? 'Edit Branch' : 'Register Branch' ?>
                        </h2>
                        <?php if($edit_branch): ?>
                            <a href="index.php?page=branches" class="text-xs font-bold text-slate-400 hover:text-red-500 transition-colors uppercase tracking-wider">Cancel</a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Enforce UTF-8 form submission for Myanmar language -->
                    <form action="index.php?page=branches" method="POST" accept-charset="UTF-8" class="space-y-5">
                        <input type="hidden" name="branch_id" value="<?= $edit_branch['id'] ?? '' ?>">
                        
                        <div class="space-y-1.5 group">
                            <label for="branch_name" class="block text-xs font-bold text-slate-500 uppercase tracking-wider ml-1">Branch Name</label>
                            <input type="text" id="branch_name" name="branch_name" class="w-full rounded-2xl border-slate-200 bg-slate-50/50 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all py-3 px-4 font-medium text-slate-800 shadow-sm placeholder-slate-300" placeholder="e.g. Downtown Hub" value="<?= htmlspecialchars($edit_branch['branch_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        
                        <div class="space-y-1.5 group">
                            <label for="region_id" class="block text-xs font-bold text-slate-500 uppercase tracking-wider ml-1">Parent Region</label>
                            <select id="region_id" name="region_id" class="w-full rounded-2xl border-slate-200 bg-slate-50/50 focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all py-3 px-4 font-medium text-slate-800 shadow-sm appearance-none" required>
                                <option value="">Select a Region...</option>
                                <?php foreach ($regions as $region): ?>
                                    <option value="<?= $region['id'] ?>" <?= (isset($edit_branch) && $edit_branch['region_id'] == $region['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($region['region_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="pt-4">
                            <?php if ($edit_branch): ?>
                                <button type="submit" class="w-full bg-gradient-to-r from-amber-500 to-orange-500 text-white py-3.5 px-4 rounded-2xl font-bold text-md hover:from-amber-600 hover:to-orange-600 focus:outline-none focus:ring-4 focus:ring-amber-500/30 shadow-[0_8px_20px_rgb(245,158,11,0.3)] transition-all transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M7 7l13 13M20 20v-5h-5"/></svg>
                                    Update Branch
                                </button>
                            <?php else: ?>
                                <button type="submit" class="w-full bg-gradient-to-r from-indigo-500 to-blue-600 text-white py-3.5 px-4 rounded-2xl font-bold text-md hover:from-indigo-600 hover:to-blue-700 focus:outline-none focus:ring-4 focus:ring-indigo-500/30 shadow-[0_8px_20px_rgb(99,102,241,0.3)] transition-all transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                    Register Branch
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- RIGHT COLUMN: DATA TABLE -->
            <div class="lg:col-span-8">
                <div class="bg-white/70 backdrop-blur-2xl rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-white/60 overflow-hidden h-full flex flex-col">
                    
                    <div class="p-6 sm:p-8 border-b border-slate-100 flex items-center justify-between bg-white/50">
                        <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                            Network Directory
                        </h2>
                        <span class="bg-indigo-50 text-indigo-600 py-1 px-3 rounded-full text-xs font-bold border border-indigo-100 shadow-sm">
                            <?= count($branches) ?> Branches
                        </span>
                    </div>

                    <div class="overflow-x-auto w-full custom-scrollbar flex-1 p-2">
                        <table class="w-full text-left border-collapse whitespace-nowrap">
                            <thead>
                                <tr>
                                    <th class="py-4 px-6 text-xs font-extrabold text-slate-400 uppercase tracking-widest border-b border-slate-100">Branch Name</th>
                                    <th class="py-4 px-6 text-xs font-extrabold text-slate-400 uppercase tracking-widest border-b border-slate-100">Parent Region</th>
                                    <th class="py-4 px-6 text-xs font-extrabold text-slate-400 uppercase tracking-widest border-b border-slate-100 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100/60">
                                <?php if (empty($branches)): ?>
                                    <tr>
                                        <td colspan="3" class="py-12 text-center text-slate-400 font-medium">No branches configured.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($branches as $branch): ?>
                                        <tr class="hover:bg-white/90 transition-colors duration-200 group">
                                            <td class="py-4 px-6">
                                                <!-- ENT_QUOTES | UTF-8 prevents corruption of Myanmar encoding -->
                                                <span class="font-bold text-slate-800"><?= htmlspecialchars($branch['branch_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                            </td>
                                            <td class="py-4 px-6">
                                                <span class="inline-flex items-center gap-1.5 font-medium text-sm text-indigo-700 bg-indigo-50/50 px-3 py-1.5 rounded-lg border border-indigo-100 shadow-sm">
                                                    <div class="w-1.5 h-1.5 rounded-full bg-indigo-500"></div>
                                                    <?= htmlspecialchars($branch['region_name'], ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </td>
                                            <td class="py-4 px-6 text-right space-x-2">
                                                <a href="index.php?page=branches&action=edit&id=<?= $branch['id'] ?>" class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-slate-50 text-slate-500 hover:text-amber-500 hover:bg-amber-50 hover:shadow-sm border border-transparent hover:border-amber-100 transition-all" title="Edit">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                </a>
                                                <a href="index.php?page=branches&action=delete&id=<?= $branch['id'] ?>" onclick="return confirm('Are you sure you want to delete the branch <?= htmlspecialchars($branch['branch_name'], ENT_QUOTES, 'UTF-8') ?>? This action cannot be undone.');" class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-slate-50 text-slate-500 hover:text-red-500 hover:bg-red-50 hover:shadow-sm border border-transparent hover:border-red-100 transition-all" title="Delete">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
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

        </div>
    </div>
</div>

<style>
    /* Custom scrollbar to keep horizontal scrolling elegant on desktop */
    .custom-scrollbar::-webkit-scrollbar {
        height: 6px;
        width: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: rgba(243, 244, 246, 0.5); 
        border-radius: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(156, 163, 175, 0.5); 
        border-radius: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: rgba(107, 114, 128, 0.8); 
    }
</style>

<?php include_template('footer'); ?>