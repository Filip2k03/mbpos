<?php
// pos/delivery_types.php - CRUD management for delivery types (V5 Modern Enterprise).
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

$edit_type = null;

// --- Handle POST Request (Add or Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    require_csrf_request();
    $type_id = intval($_POST['delete_id']);
    $stmt = mysqli_prepare($connection, "DELETE FROM delivery_types WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $type_id);
    if (mysqli_stmt_execute($stmt)) {
        mbpos_cache_forget_namespace('lookup-delivery-types');
        mbpos_cache_del(mbpos_cache_key('lookup-delivery-types', 'all'));
        flash_message('success', 'Delivery service type deleted successfully.');
    } else {
        flash_message('error', 'Cannot delete this delivery type because active vouchers may reference it.');
    }
    mysqli_stmt_close($stmt);
    redirect('index.php?page=delivery_types');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_request();
    $name = trim($_POST['name'] ?? '');
    $type_id = intval($_POST['type_id'] ?? 0);

    if (empty($name)) {
        flash_message('error', 'Delivery type name cannot be empty.');
    } else {
        if ($type_id > 0) { // Update
            $stmt = mysqli_prepare($connection, "UPDATE delivery_types SET name = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $name, $type_id);
            if(mysqli_stmt_execute($stmt)) {
                mbpos_cache_forget_namespace('lookup-delivery-types');
                mbpos_cache_del(mbpos_cache_key('lookup-delivery-types', 'all'));
                flash_message('success', 'Delivery type updated successfully.');
            } else {
                flash_message('error', 'Failed to update delivery type.');
            }
            mysqli_stmt_close($stmt);
        } else { // Add
            $stmt = mysqli_prepare($connection, "INSERT INTO delivery_types (name) VALUES (?)");
            mysqli_stmt_bind_param($stmt, 's', $name);
            if(mysqli_stmt_execute($stmt)) {
                mbpos_cache_forget_namespace('lookup-delivery-types');
                mbpos_cache_del(mbpos_cache_key('lookup-delivery-types', 'all'));
                flash_message('success', 'Delivery type added successfully.');
            } else {
                flash_message('error', 'Failed to add delivery type.');
            }
            mysqli_stmt_close($stmt);
        }
    }
    redirect('index.php?page=delivery_types');
}

// --- Handle GET Request (Edit) ---
if (isset($_GET['action'])) {
    $id = intval($_GET['id'] ?? 0);
    if ($_GET['action'] === 'edit' && $id > 0) {
        $stmt = mysqli_prepare($connection, "SELECT * FROM delivery_types WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $edit_type = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }
}

// --- Fetch all delivery types with cache ---
$delivery_types = mbpos_cache_remember('lookup-delivery-types', 'all', 120, function () use ($connection) {
    $rows = [];
    $result = mysqli_query($connection, "SELECT * FROM delivery_types ORDER BY name ASC");
    if($result) {
        while($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    }
    return $rows;
});

include_template('header', ['page' => 'delivery_types']);
?>

<div class="v5-page">
    <div class="v5-page-head">
        <div class="v5-page-head__copy">
            <span class="v5-kicker" data-i18n="Configuration">Configuration</span>
            <h1 data-i18n="Delivery Types">Delivery Service Types</h1>
            <p data-i18n="Configure logistics service levels and shipping speeds.">Configure logistics service levels and shipping speeds.</p>
        </div>
        <div class="v5-page-actions">
            <span class="v5-count"><?= count($delivery_types) ?> <span data-i18n="Services">Services</span></span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <!-- Form Column -->
        <div class="lg:col-span-4">
            <section class="v5-panel">
                <div class="v5-panel__head">
                    <h2 data-i18n="<?= $edit_type ? 'Edit Service' : 'Add Service' ?>"><?= $edit_type ? 'Edit Service' : 'Add Service' ?></h2>
                    <?php if($edit_type): ?>
                        <a href="index.php?page=delivery_types" class="btn-ghost btn-sm" data-i18n="Cancel">Cancel</a>
                    <?php endif; ?>
                </div>
                <div class="v5-panel__body">
                    <form action="index.php?page=delivery_types" method="POST" accept-charset="UTF-8" class="space-y-4">
                        <?= csrf_input() ?>
                        <input type="hidden" name="type_id" value="<?= (int)($edit_type['id'] ?? 0) ?>">

                        <div class="v5-field">
                            <label for="name" class="v5-field-label" data-i18n="Service Name">Service Name</label>
                            <input type="text" id="name" name="name" class="v5-input" placeholder="e.g. Express / အမြန်" value="<?= e($edit_type['name'] ?? '') ?>" required autofocus>
                            <p class="v5-field-hint" data-i18n="Supports English and Myanmar (Unicode)">Supports English and Myanmar (Unicode)</p>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="btn-primary w-full" data-i18n="<?= $edit_type ? 'Update Service' : 'Register Service' ?>">
                                <?= $edit_type ? 'Update Service' : 'Register Service' ?>
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
                    <h2 data-i18n="Active Delivery Services">Active Delivery Services</h2>
                    <span class="v5-badge v5-badge-info"><?= count($delivery_types) ?> <span data-i18n="configured">configured</span></span>
                </div>
                <div class="v5-panel__body p-0">
                    <div class="overflow-x-auto">
                        <table class="v5-table w-full">
                            <thead>
                                <tr>
                                    <th style="width:70px;">ID</th>
                                    <th data-i18n="Service Name">Service Name</th>
                                    <th class="text-right" data-i18n="Actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($delivery_types)): ?>
                                    <tr>
                                        <td colspan="3">
                                            <div class="v5-empty">
                                                <span class="v5-empty__icon"><?= mbpos_icon('delivery_types', 'w-8 h-8 text-slate-400') ?></span>
                                                <strong data-i18n="No delivery services configured.">No delivery services configured.</strong>
                                                <p data-i18n="Use the form on the left to add a new delivery service.">Use the form on the left to add a new delivery service.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($delivery_types as $type): ?>
                                        <tr>
                                            <td><span class="font-mono text-muted text-xs">#<?= (int)$type['id'] ?></span></td>
                                            <td>
                                                <strong class="font-semibold text-main"><?= e($type['name']) ?></strong>
                                            </td>
                                            <td class="text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <a href="index.php?page=delivery_types&action=edit&id=<?= (int)$type['id'] ?>" class="btn-ghost btn-sm" title="Edit" data-i18n="Edit">
                                                        Edit
                                                    </a>
                                                    <form method="POST" action="index.php?page=delivery_types" class="inline" onsubmit="return confirm('Delete this delivery service?');">
                                                        <?= csrf_input() ?>
                                                        <input type="hidden" name="delete_id" value="<?= (int)$type['id'] ?>">
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
