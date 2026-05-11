<?php
// pos/item_types.php - CRUD management for Item Types (V3 Premium UI).
require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) session_start();
if (!is_logged_in() || (!is_admin() && !is_developer())) {
    flash_message('error', 'You are not authorized to access this page.');
    redirect('index.php?page=dashboard');
}

global $connection;

// --- CRITICAL FIX FOR MYANMAR FONTS ---
// Forces the database connection to use full UTF-8, preventing mojibake/garbled text
mysqli_set_charset($connection, "utf8mb4");

$edit_item = null;

// --- Handle POST Request (Add or Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $item_id = intval($_POST['item_id'] ?? 0);

    if (empty($name)) {
        flash_message('error', 'Item category name cannot be empty.');
    } else {
        if ($item_id > 0) { // Update
            $stmt = mysqli_prepare($connection, "UPDATE item_types SET name = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $name, $item_id);
            if(mysqli_stmt_execute($stmt)) flash_message('success', 'Item category updated successfully.');
        } else { // Add
            $stmt = mysqli_prepare($connection, "INSERT INTO item_types (name) VALUES (?)");
            mysqli_stmt_bind_param($stmt, 's', $name);
            if(mysqli_stmt_execute($stmt)) flash_message('success', 'Item category added successfully.');
        }
    }
    redirect('index.php?page=item_types');
}

// --- Handle GET Request (Delete or Edit) ---
if (isset($_GET['action'])) {
    $id = intval($_GET['id'] ?? 0);
    if ($_GET['action'] === 'delete' && $id > 0) {
        $stmt = mysqli_prepare($connection, "DELETE FROM item_types WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        if(mysqli_stmt_execute($stmt)) flash_message('success', 'Item category deleted.');
        redirect('index.php?page=item_types');
    }
    if ($_GET['action'] === 'edit' && $id > 0) {
        $stmt = mysqli_prepare($connection, "SELECT * FROM item_types WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $edit_item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    }
}

// --- Fetch all item types ---
$item_types = [];
$result = mysqli_query($connection, "SELECT * FROM item_types ORDER BY name ASC");
if($result) while($row = mysqli_fetch_assoc($result)) $item_types[] = $row;


include_template('header', ['page' => 'item_types']);
?>

<!-- V3 Liquid UI Wrapper -->
<div class="relative min-h-[85vh] bg-gray-50/30 p-4 sm:p-8 overflow-hidden font-sans">
    
    <!-- Ambient Background Glows -->
    <div class="absolute top-[10%] left-[-10%] w-[500px] h-[500px] bg-rose-400/10 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-[500px] h-[500px] bg-fuchsia-400/10 rounded-full blur-[120px] pointer-events-none"></div>

    <div class="max-w-7xl mx-auto relative z-10">
        
        <!-- Header -->
        <div class="flex items-center gap-4 mb-8">
            <div class="w-14 h-14 bg-gradient-to-br from-rose-500 to-fuchsia-600 rounded-2xl flex items-center justify-center shadow-lg shadow-fuchsia-500/30 text-white transform -rotate-3 hover:rotate-0 transition-transform duration-300">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
            <div>
                <h1 class="text-3xl font-extrabold bg-gradient-to-r from-gray-900 to-gray-600 bg-clip-text text-transparent tracking-tight">Item Categories</h1>
                <p class="text-sm font-medium text-gray-500">Configure logistics parcel and cargo classifications</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- LEFT COLUMN: CRUD FORM -->
            <div class="lg:col-span-4">
                <div class="bg-white/80 backdrop-blur-2xl p-6 sm:p-8 rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-white/60 sticky top-28">
                    
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                            <svg class="w-5 h-5 <?= $edit_item ? 'text-amber-500' : 'text-fuchsia-500' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <?php if($edit_item): ?>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                <?php else: ?>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                <?php endif; ?>
                            </svg>
                            <?= $edit_item ? 'Edit Category' : 'Add Category' ?>
                        </h2>
                        <?php if($edit_item): ?>
                            <a href="index.php?page=item_types" class="text-xs font-bold text-gray-400 hover:text-red-500 transition-colors uppercase tracking-wider">Cancel</a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Enforce UTF-8 form submission for Myanmar language -->
                    <form action="index.php?page=item_types" method="POST" accept-charset="UTF-8" class="space-y-5">
                        <input type="hidden" name="item_id" value="<?= $edit_item['id'] ?? '' ?>">
                        
                        <div class="space-y-1.5 group">
                            <label for="name" class="block text-xs font-bold text-gray-500 uppercase tracking-wider ml-1">Category Name</label>
                            <input type="text" id="name" name="name" class="w-full rounded-2xl border-gray-200 bg-gray-50/50 focus:bg-white focus:ring-2 focus:ring-fuchsia-500/20 focus:border-fuchsia-500 transition-all py-3 px-4 font-medium text-gray-800 shadow-sm placeholder-gray-300" placeholder="e.g. Document / စာရွက်စာတမ်း" value="<?= htmlspecialchars($edit_item['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                            <p class="text-[10px] font-medium text-gray-400 ml-1 mt-1">Supports English and Myanmar (Unicode)</p>
                        </div>

                        <div class="pt-4">
                            <?php if ($edit_item): ?>
                                <button type="submit" class="w-full bg-gradient-to-r from-amber-500 to-orange-500 text-white py-3.5 px-4 rounded-2xl font-bold text-md hover:from-amber-600 hover:to-orange-600 focus:outline-none focus:ring-4 focus:ring-amber-500/30 shadow-[0_8px_20px_rgb(245,158,11,0.3)] transition-all transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M7 7l13 13M20 20v-5h-5"/></svg>
                                    Update Category
                                </button>
                            <?php else: ?>
                                <button type="submit" class="w-full bg-gradient-to-r from-rose-500 to-fuchsia-600 text-white py-3.5 px-4 rounded-2xl font-bold text-md hover:from-rose-600 hover:to-fuchsia-700 focus:outline-none focus:ring-4 focus:ring-fuchsia-500/30 shadow-[0_8px_20px_rgb(217,70,239,0.3)] transition-all transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                    Register Category
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- RIGHT COLUMN: DATA TABLE -->
            <div class="lg:col-span-8">
                <div class="bg-white/70 backdrop-blur-2xl rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-white/60 overflow-hidden h-full flex flex-col">
                    
                    <div class="p-6 sm:p-8 border-b border-gray-100 flex items-center justify-between bg-white/50">
                        <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                            Active Categories
                        </h2>
                        <span class="bg-fuchsia-50 text-fuchsia-600 py-1 px-3 rounded-full text-xs font-bold border border-fuchsia-100 shadow-sm">
                            <?= count($item_types) ?> Types
                        </span>
                    </div>

                    <div class="overflow-x-auto w-full custom-scrollbar flex-1 p-2">
                        <table class="w-full text-left border-collapse whitespace-nowrap">
                            <thead>
                                <tr>
                                    <th class="py-4 px-6 text-xs font-extrabold text-gray-400 uppercase tracking-widest border-b border-gray-100 w-16">ID</th>
                                    <th class="py-4 px-6 text-xs font-extrabold text-gray-400 uppercase tracking-widest border-b border-gray-100">Category Name</th>
                                    <th class="py-4 px-6 text-xs font-extrabold text-gray-400 uppercase tracking-widest border-b border-gray-100 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100/60">
                                <?php if (empty($item_types)): ?>
                                    <tr>
                                        <td colspan="3" class="py-12 text-center text-gray-400 font-medium">No item categories configured.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($item_types as $item): ?>
                                        <tr class="hover:bg-white/90 transition-colors duration-200 group">
                                            <td class="py-4 px-6">
                                                <span class="text-sm font-bold text-gray-400">
                                                    #<?= $item['id'] ?>
                                                </span>
                                            </td>
                                            <td class="py-4 px-6">
                                                <!-- ENT_QUOTES | UTF-8 prevents corruption of Myanmar encoding -->
                                                <span class="font-bold text-gray-800"><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                            </td>
                                            <td class="py-4 px-6 text-right space-x-2">
                                                <a href="index.php?page=item_types&action=edit&id=<?= $item['id'] ?>" class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-gray-50 text-gray-500 hover:text-amber-500 hover:bg-amber-50 hover:shadow-sm border border-transparent hover:border-amber-100 transition-all" title="Edit">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                </a>
                                                <a href="index.php?page=item_types&action=delete&id=<?= $item['id'] ?>" onclick="return confirm('Are you sure you want to delete the category <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>? This action cannot be undone.');" class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-gray-50 text-gray-500 hover:text-red-500 hover:bg-red-50 hover:shadow-sm border border-transparent hover:border-red-100 transition-all" title="Delete">
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