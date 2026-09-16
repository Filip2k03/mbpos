<?php
// pos/branches.php - Admin page for CRUD operations on branches (V5 Modern Enterprise).
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/cache.php';

if (session_status() == PHP_SESSION_NONE) session_start();
if (!is_logged_in() || (!is_admin() && !is_developer())) {
    flash_message('error', 'You are not authorized to access this page.');
    redirect('index.php?page=dashboard');
}

global $connection;
mysqli_set_charset($connection, "utf8mb4");

$edit_branch = null;

// --- Handle DELETE Request (POST only) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    require_csrf_request();
    $branch_id = intval($_POST['delete_id']);
    $stmt = mysqli_prepare($connection, "DELETE FROM branches WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $branch_id);
    if (mysqli_stmt_execute($stmt)) {
        mbpos_cache_forget_namespace('lookup-branches');
        mbpos_cache_del(mbpos_cache_key('lookup-branches', 'all'));
        flash_message('success', 'Branch deleted successfully.');
    } else {
        flash_message('error', 'Cannot delete this branch because users or shipments are assigned to it.');
    }
    mysqli_stmt_close($stmt);
    redirect('index.php?page=branches');
}

// --- Handle POST Request (Add or Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_request();
    $branch_name = trim($_POST['branch_name'] ?? '');
    $region_id = intval($_POST['region_id'] ?? 0);
    $branch_id = intval($_POST['branch_id'] ?? 0);

    if (empty($branch_name) || $region_id <= 0) {
        flash_message('error', 'Branch name and parent region are required.');
    } else {
        if ($branch_id > 0) { // Update existing branch
            $stmt = mysqli_prepare($connection, "UPDATE branches SET branch_name = ?, region_id = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'sii', $branch_name, $region_id, $branch_id);
            if (mysqli_stmt_execute($stmt)) {
                mbpos_cache_forget_namespace('lookup-branches');
                mbpos_cache_del(mbpos_cache_key('lookup-branches', 'all'));
                flash_message('success', 'Branch updated successfully.');
            } else {
                flash_message('error', 'Failed to update branch.');
            }
            mysqli_stmt_close($stmt);
        } else { // Add new branch
            $stmt = mysqli_prepare($connection, "INSERT INTO branches (branch_name, region_id) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, 'si', $branch_name, $region_id);
            if (mysqli_stmt_execute($stmt)) {
                mbpos_cache_forget_namespace('lookup-branches');
                mbpos_cache_del(mbpos_cache_key('lookup-branches', 'all'));
                flash_message('success', 'Branch registered successfully.');
            } else {
                flash_message('error', 'Failed to add branch.');
            }
            mysqli_stmt_close($stmt);
        }
    }
    redirect('index.php?page=branches');
}

// --- Handle EDIT Request ---
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $branch_id = intval($_GET['id']);
    $stmt = mysqli_prepare($connection, "SELECT id, branch_name, region_id FROM branches WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $branch_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $edit_branch = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// --- Fetch Data for Display with caching ---
$branches = mbpos_cache_remember('lookup-branches', 'directory', 120, function () use ($connection) {
    $rows = [];
    $result = mysqli_query($connection, "SELECT b.id, b.branch_name, r.region_name FROM branches b JOIN regions r ON b.region_id = r.id ORDER BY r.region_name, b.branch_name");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    }
    return $rows;
});

$regions = mbpos_cache_remember('lookup-regions', 'all', 300, function () use ($connection) {
    $rows = [];
    $region_result = mysqli_query($connection, "SELECT id, region_name FROM regions ORDER BY region_name");
    if ($region_result) {
        while ($row = mysqli_fetch_assoc($region_result)) $rows[] = $row;
    }
    return $rows;
});

include_template('header', ['page' => 'branches']);
?>

<div class="v5-page">
    <div class="v5-page-head">
        <div class="v5-page-head__copy">
            <span class="v5-kicker" data-i18n="Configuration">Configuration</span>
            <h1 data-i18n="Operating Branches">Operating Branches</h1>
            <p data-i18n="Configure regional logistics hubs and branch network nodes.">Configure regional logistics hubs and branch network nodes.</p>
        </div>
        <div class="v5-page-actions">
            <span class="v5-count"><?= count($branches) ?> <span data-i18n="Branches">Branches</span></span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <!-- Form Column -->
        <div class="lg:col-span-4">
            <section class="v5-panel">
                <div class="v5-panel__head">
                    <h2 data-i18n="<?= $edit_branch ? 'Edit Branch' : 'Register Branch' ?>"><?= $edit_branch ? 'Edit Branch' : 'Register Branch' ?></h2>
                    <?php if($edit_branch): ?>
                        <a href="index.php?page=branches" class="btn-ghost btn-sm" data-i18n="Cancel">Cancel</a>
                    <?php endif; ?>
                </div>
                <div class="v5-panel__body">
                    <form action="index.php?page=branches" method="POST" accept-charset="UTF-8" class="space-y-4">
                        <?= csrf_input() ?>
                        <input type="hidden" name="branch_id" value="<?= (int)($edit_branch['id'] ?? 0) ?>">

                        <div class="v5-field">
                            <label for="branch_name" class="v5-field-label" data-i18n="Branch Name">Branch Name</label>
                            <input type="text" id="branch_name" name="branch_name" class="v5-input" placeholder="Branch name" data-i18n-placeholder="Branch name" value="<?= e($edit_branch['branch_name'] ?? '') ?>" required autofocus>
                        </div>

                        <div class="v5-field">
                            <label for="region_id" class="v5-field-label" data-i18n="Parent Region">Parent Region</label>
                            <select id="region_id" name="region_id" class="v5-input" required>
                                <option value="" data-i18n="Select a Region...">Select a Region...</option>
                                <?php foreach ($regions as $region): ?>
                                    <option value="<?= (int)$region['id'] ?>" <?= (isset($edit_branch) && $edit_branch['region_id'] == $region['id']) ? 'selected' : '' ?>>
                                        <?= e($region['region_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="btn-primary w-full" data-i18n="<?= $edit_branch ? 'Update Branch' : 'Register Branch' ?>">
                                <?= $edit_branch ? 'Update Branch' : 'Register Branch' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <!-- Table Column -->
        <div class="lg:col-span-8">
            <section class="v5-panel">
                <div class="v5-panel__head">
                    <h2 data-i18n="Network Directory">Network Directory</h2>
                    <span class="v5-badge v5-badge-info"><?= count($branches) ?> <span data-i18n="nodes">nodes</span></span>
                </div>
                <div class="v5-panel__body p-0">
                    <div class="overflow-x-auto">
                        <table class="v5-table w-full">
                            <thead>
                                <tr>
                                    <th style="width:70px;">ID</th>
                                    <th data-i18n="Branch Name">Branch Name</th>
                                    <th data-i18n="Parent Region">Region</th>
                                    <th class="text-right" data-i18n="Actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($branches)): ?>
                                    <tr>
                                        <td colspan="4">
                                            <div class="v5-empty">
                                                <span class="v5-empty__icon"><?= mbpos_icon('branches', 'w-8 h-8 text-slate-400') ?></span>
                                                <strong data-i18n="No branches configured.">No branches configured.</strong>
                                                <p data-i18n="Use the form on the left to add a branch node.">Use the form on the left to add a branch node.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($branches as $branch): ?>
                                        <tr>
                                            <td><span class="font-mono text-muted text-xs">#<?= (int)$branch['id'] ?></span></td>
                                            <td>
                                                <strong class="font-semibold text-main"><?= e($branch['branch_name']) ?></strong>
                                            </td>
                                            <td>
                                                <span class="v5-badge v5-badge-neutral">
                                                    <?= e($branch['region_name']) ?>
                                                </span>
                                            </td>
                                            <td class="text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <a href="index.php?page=branches&action=edit&id=<?= (int)$branch['id'] ?>" class="btn-ghost btn-sm" title="Edit" data-i18n="Edit">
                                                        Edit
                                                    </a>
                                                    <form method="POST" action="index.php?page=branches" class="inline" onsubmit="return confirm('Delete branch ' + <?= json_encode($branch['branch_name']) ?> + '?');">
                                                        <?= csrf_input() ?>
                                                        <input type="hidden" name="delete_id" value="<?= (int)$branch['id'] ?>">
                                                        <button type="submit" class="btn-ghost btn-sm text-danger hover:bg-red-50" title="Delete" data-i18n="Delete">
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<?php include_template('footer'); ?>
