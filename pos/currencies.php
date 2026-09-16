<?php
// pos/currencies.php - CRUD management for currencies (V5 Modern Enterprise).
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

$edit_currency = null;

// --- Handle POST Request (Add or Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    require_csrf_request();
    $currency_id = intval($_POST['delete_id']);
    $stmt = mysqli_prepare($connection, "DELETE FROM currencies WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $currency_id);
    if (mysqli_stmt_execute($stmt)) {
        mbpos_cache_forget_namespace('lookup-currencies');
        mbpos_cache_del(mbpos_cache_key('lookup-currencies', 'codes'));
        flash_message('success', 'Currency deleted successfully.');
    } else {
        flash_message('error', 'Cannot delete this currency because active vouchers may reference it.');
    }
    mysqli_stmt_close($stmt);
    redirect('index.php?page=currencies');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_request();
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $currency_id = intval($_POST['currency_id'] ?? 0);

    if (empty($code) || empty($name)) {
        flash_message('error', 'Currency code and name cannot be empty.');
    } else {
        if ($currency_id > 0) { // Update
            $stmt = mysqli_prepare($connection, "UPDATE currencies SET code = ?, name = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'ssi', $code, $name, $currency_id);
            if(mysqli_stmt_execute($stmt)) {
                mbpos_cache_forget_namespace('lookup-currencies');
                mbpos_cache_del(mbpos_cache_key('lookup-currencies', 'codes'));
                flash_message('success', 'Currency updated successfully.');
            } else {
                flash_message('error', 'Failed to update currency.');
            }
            mysqli_stmt_close($stmt);
        } else { // Add
            $stmt = mysqli_prepare($connection, "INSERT INTO currencies (code, name) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, 'ss', $code, $name);
            if(mysqli_stmt_execute($stmt)) {
                mbpos_cache_forget_namespace('lookup-currencies');
                mbpos_cache_del(mbpos_cache_key('lookup-currencies', 'codes'));
                flash_message('success', 'Currency added successfully.');
            } else {
                flash_message('error', 'Failed to add currency.');
            }
            mysqli_stmt_close($stmt);
        }
    }
    redirect('index.php?page=currencies');
}

// --- Handle GET Request (Edit) ---
if (isset($_GET['action'])) {
    $id = intval($_GET['id'] ?? 0);
    if ($_GET['action'] === 'edit' && $id > 0) {
        $stmt = mysqli_prepare($connection, "SELECT * FROM currencies WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $edit_currency = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }
}

// --- Fetch all currencies with cache ---
$currencies = mbpos_cache_remember('lookup-currencies', 'all-rows', 120, function () use ($connection) {
    $rows = [];
    $result = mysqli_query($connection, "SELECT * FROM currencies ORDER BY code ASC");
    if($result) {
        while($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    }
    return $rows;
});

include_template('header', ['page' => 'currencies']);
?>

<div class="v5-page">
    <div class="v5-page-head">
        <div class="v5-page-head__copy">
            <span class="v5-kicker" data-i18n="Configuration">Configuration</span>
            <h1 data-i18n="Currencies">System Currencies</h1>
            <p data-i18n="Configure accepted tender, currency codes, and transaction formatting.">Configure accepted tender, currency codes, and transaction formatting.</p>
        </div>
        <div class="v5-page-actions">
            <span class="v5-count"><?= count($currencies) ?> <span data-i18n="Currencies">Currencies</span></span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <!-- Form Column -->
        <div class="lg:col-span-4">
            <section class="v5-panel">
                <div class="v5-panel__head">
                    <h2 data-i18n="<?= $edit_currency ? 'Edit Currency' : 'Add Currency' ?>"><?= $edit_currency ? 'Edit Currency' : 'Add Currency' ?></h2>
                    <?php if($edit_currency): ?>
                        <a href="index.php?page=currencies" class="btn-ghost btn-sm" data-i18n="Cancel">Cancel</a>
                    <?php endif; ?>
                </div>
                <div class="v5-panel__body">
                    <form action="index.php?page=currencies" method="POST" accept-charset="UTF-8" class="space-y-4">
                        <?= csrf_input() ?>
                        <input type="hidden" name="currency_id" value="<?= (int)($edit_currency['id'] ?? 0) ?>">

                        <div class="v5-field">
                            <label for="code" class="v5-field-label" data-i18n="Currency Code">Currency Code</label>
                            <input type="text" id="code" name="code" class="v5-input font-mono font-bold uppercase" placeholder="Currency code" data-i18n-placeholder="Currency code" value="<?= e($edit_currency['code'] ?? '') ?>" required maxlength="10" autofocus>
                            <p class="v5-field-hint" data-i18n="Short identifier used in ledgers (e.g. USD, MMK, MYR, SGD)">Short identifier used in ledgers (e.g. USD, MMK, MYR, SGD)</p>
                        </div>

                        <div class="v5-field">
                            <label for="name" class="v5-field-label" data-i18n="Currency Name">Currency Name</label>
                            <input type="text" id="name" name="name" class="v5-input" placeholder="Currency name" data-i18n-placeholder="Currency name" value="<?= e($edit_currency['name'] ?? '') ?>" required>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="btn-primary w-full" data-i18n="<?= $edit_currency ? 'Update Configuration' : 'Register Currency' ?>">
                                <?= $edit_currency ? 'Update Configuration' : 'Register Currency' ?>
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
                    <h2 data-i18n="Configured Currencies">Configured Currencies</h2>
                    <span class="v5-badge v5-badge-info"><?= count($currencies) ?> <span data-i18n="active">active</span></span>
                </div>
                <div class="v5-panel__body p-0">
                    <div class="overflow-x-auto">
                        <table class="v5-table w-full">
                            <thead>
                                <tr>
                                    <th style="width:70px;">ID</th>
                                    <th data-i18n="Currency Code">Code</th>
                                    <th data-i18n="Currency Name">Name</th>
                                    <th class="text-right" data-i18n="Actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($currencies)): ?>
                                    <tr>
                                        <td colspan="4">
                                            <div class="v5-empty">
                                                <span class="v5-empty__icon"><?= mbpos_icon('currencies', 'w-8 h-8 text-slate-400') ?></span>
                                                <strong data-i18n="No currencies configured.">No currencies configured.</strong>
                                                <p data-i18n="Use the form on the left to add a supported currency.">Use the form on the left to add a supported currency.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($currencies as $curr): ?>
                                        <tr>
                                            <td><span class="font-mono text-muted text-xs">#<?= (int)$curr['id'] ?></span></td>
                                            <td>
                                                <span class="v5-badge v5-badge-success font-mono font-bold"><?= e($curr['code']) ?></span>
                                            </td>
                                            <td>
                                                <strong class="font-semibold text-main"><?= e($curr['name']) ?></strong>
                                            </td>
                                            <td class="text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <a href="index.php?page=currencies&action=edit&id=<?= (int)$curr['id'] ?>" class="btn-ghost btn-sm" title="Edit" data-i18n="Edit">
                                                        Edit
                                                    </a>
                                                    <form method="POST" action="index.php?page=currencies" class="inline" onsubmit="return confirm('Delete currency ' + <?= json_encode($curr['code']) ?> + '?');">
                                                        <?= csrf_input() ?>
                                                        <input type="hidden" name="delete_id" value="<?= (int)$curr['id'] ?>">
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
