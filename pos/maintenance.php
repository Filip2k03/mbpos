<?php
// maintenance.php - Developer page for managing maintenance settings.

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authorization: Only Developers can access ---
if (!is_logged_in() || !is_developer()) {
    flash_message('error', 'You are not authorized to access this page.');
    redirect('index.php?page=dashboard');
}

global $connection;
$edit_category = null;

// --- Handle POST Requests (Add/Update/Toggle/Delete) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    require_csrf_request();
    $id = intval($_POST['delete_id']);
    if ($id > 0) {
        $stmt = mysqli_prepare($connection, "DELETE FROM maintenance WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    redirect('index.php?page=maintenance');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_request();
    // Toggle Status
    if (isset($_POST['toggle_id'])) {
        $id = intval($_POST['toggle_id']);
        $current_status = intval($_POST['current_status']);
        $new_status = $current_status ? 0 : 1;
        $stmt = mysqli_prepare($connection, "UPDATE maintenance SET is_active = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $new_status, $id);
        mysqli_stmt_execute($stmt);
    }
    // Add/Update Category
    else {
        $name = trim($_POST['name']);
        $id = intval($_POST['id'] ?? 0);
        if (!empty($name)) {
            if ($id > 0) { // Update
                $stmt = mysqli_prepare($connection, "UPDATE maintenance SET name = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'si', $name, $id);
            } else { // Insert
                $stmt = mysqli_prepare($connection, "INSERT INTO maintenance (name) VALUES (?)");
                mysqli_stmt_bind_param($stmt, 's', $name);
            }
            mysqli_stmt_execute($stmt);
        }
    }
    redirect('index.php?page=maintenance');
}

// --- Handle GET Requests (Edit only) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = intval($_GET['id'] ?? 0);
    if ($action === 'edit' && $id > 0) {
        $stmt = mysqli_prepare($connection, "SELECT * FROM maintenance WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $edit_category = mysqli_fetch_assoc($result);
    }
}

// --- Fetch all maintenance categories ---
$categories = [];
$active_count = 0;
$result = mysqli_query($connection, "SELECT * FROM maintenance ORDER BY name");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
        if (!empty($row['is_active'])) {
            $active_count++;
        }
    }
}

include_template('header', ['page' => 'maintenance']);
?>

<div class="v5-page">

    <div class="v5-page-head">
        <div class="v5-page-head__copy">
            <span class="v5-kicker" data-i18n="System">System</span>
            <h1 data-i18n="Maintenance Zones">Maintenance Zones</h1>
            <p data-i18n="Control operational maintenance categories without interrupting active POS sessions.">Control operational maintenance categories without interrupting active POS sessions.</p>
        </div>
        <span class="v5-count"><?= count($categories) ?> <span data-i18n="configured">configured</span></span>
    </div>

    <?php if ($active_count > 0): ?>
    <div class="v5-panel" style="border-color:#fecdd3;background:#fff1f2;">
        <div class="v5-panel__body" style="display:flex;align-items:center;gap:.85rem;padding:.9rem 1.15rem;">
            <span class="v5-badge" style="background:#fee2e2;color:#b91c1c;font-size:.78rem;padding:.4rem .85rem;display:inline-flex;align-items:center;gap:.35rem;">
                <svg style="width:14px;height:14px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg> <span data-i18n="MAINTENANCE ACTIVE">MAINTENANCE ACTIVE</span>
            </span>
            <span style="color:#9f1239;font-size:.82rem;font-weight:700;" data-i18n-params='{"count":<?= $active_count ?>}'>
                <?= $active_count === 1
                    ? '1 zone is currently active. The POS may be in a restricted state.'
                    : $active_count . ' zones are currently active. The POS may be in a restricted state.' ?>
            </span>
        </div>
    </div>
    <?php endif; ?>

    <section class="v5-panel">
        <div class="v5-panel__head">
            <h2><?= $edit_category
                ? '<span data-i18n="Edit Maintenance Zone">Edit Maintenance Zone</span>'
                : '<span data-i18n="Add Maintenance Zone">Add Maintenance Zone</span>' ?></h2>
            <?php if ($edit_category): ?>
            <a class="v5-btn-ghost" href="index.php?page=maintenance" data-i18n="Cancel">Cancel</a>
            <?php endif; ?>
        </div>
        <div class="v5-panel__body">
            <form action="index.php?page=maintenance" method="POST">
                <?= csrf_input() ?>
                <?php if ($edit_category): ?>
                <input type="hidden" name="id" value="<?= (int)$edit_category['id'] ?>">
                <?php endif; ?>
                <div class="v5-filter-grid">
                    <div class="v5-field v5-field--wide">
                        <label for="maintenance-name" data-i18n="Zone name">Zone name</label>
                        <input id="maintenance-name" type="text" name="name" maxlength="120"
                               placeholder="e.g. Exchange rate synchronization"
                               value="<?= htmlspecialchars($edit_category['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               required>
                    </div>
                    <div class="v5-field v5-field--action">
                        <button type="submit" class="btn w-full"
                                data-i18n="<?= $edit_category ? 'Save Changes' : 'Add Zone' ?>">
                            <?= $edit_category ? 'Save Changes' : 'Add Zone' ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <section class="v5-panel">
        <div class="v5-panel__head">
            <h2 data-i18n="Maintenance Zones">Maintenance Zones</h2>
            <span class="v5-count" data-i18n="Live controls">Live controls</span>
        </div>
        <div class="v5-panel__body">
            <?php if (empty($categories)): ?>
            <div class="v5-empty">
                <span class="v5-empty__icon"><?= mbpos_icon('maintenance', 'w-8 h-8 text-slate-400') ?></span>
                <strong data-i18n="No maintenance zones configured">No maintenance zones configured</strong>
                <p data-i18n="Add the first zone using the form above.">Add the first zone using the form above.</p>
            </div>
            <?php else: ?>
            <div class="v5-record-list">
                <?php foreach ($categories as $cat): ?>
                <article class="v5-record-row">
                    <div class="v5-record-row__main">
                        <strong><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <span data-i18n="Zone ID">Zone ID</span> #<?= (int)$cat['id'] ?>
                    </div>
                    <div class="v5-record-row__actions">
                        <?php if ($cat['is_active']): ?>
                        <span class="v5-badge" style="background:#fee2e2;color:#b91c1c;display:inline-flex;align-items:center;gap:.3rem;" data-i18n="Active">
                            <svg style="width:12px;height:12px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg> Active
                        </span>
                        <?php else: ?>
                        <span class="v5-badge" style="background:#f1f5f9;color:#64748b;" data-i18n="Inactive">
                            Inactive
                        </span>
                        <?php endif; ?>

                        <form action="index.php?page=maintenance" method="POST">
                            <?= csrf_input() ?>
                            <input type="hidden" name="toggle_id" value="<?= (int)$cat['id'] ?>">
                            <input type="hidden" name="current_status" value="<?= (int)$cat['is_active'] ?>">
                            <button type="submit"
                                    class="<?= $cat['is_active'] ? 'v5-btn-ghost' : 'v5-btn-success' ?>"
                                    data-i18n="<?= $cat['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                <?= $cat['is_active'] ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </form>

                        <a href="index.php?page=maintenance&action=edit&id=<?= (int)$cat['id'] ?>"
                           class="v5-btn-ghost" data-i18n="Edit">Edit</a>

                        <form method="POST" action="index.php?page=maintenance"
                              onsubmit="return confirm('Delete this maintenance zone?');">
                            <?= csrf_input() ?>
                            <input type="hidden" name="delete_id" value="<?= (int)$cat['id'] ?>">
                            <button type="submit" class="v5-btn-danger" data-i18n="Delete">Delete</button>
                        </form>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

</div><!-- /.v5-page -->

<?php include_template('footer'); ?>
