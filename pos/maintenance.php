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

// --- Handle POST Requests (Add/Update/Toggle) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
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
$result = mysqli_query($connection, "SELECT * FROM maintenance ORDER BY name");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }
}

include_template('header', ['page' => 'maintenance']);
?>

<div class="v5-page">
    <div class="v5-page-head">
        <div class="v5-page-head__copy">
            <span class="v5-kicker">Developer configuration</span>
            <h1 data-i18n="Maintenance Settings">Maintenance Settings</h1>
            <p>Control operational maintenance categories without interrupting active POS sessions.</p>
        </div>
        <span class="v5-count"><?= count($categories) ?> configured categories</span>
    </div>

    <section class="v5-panel">
        <div class="v5-panel__head">
            <h2><?= $edit_category ? 'Edit Maintenance Category' : 'Add Maintenance Category' ?></h2>
            <?php if ($edit_category): ?><a class="v5-btn-ghost" href="index.php?page=maintenance">Cancel edit</a><?php endif; ?>
        </div>
        <div class="v5-panel__body">
            <form action="index.php?page=maintenance" method="POST">
                <?= csrf_input() ?>
                <?php if ($edit_category): ?><input type="hidden" name="id" value="<?= (int)$edit_category['id'] ?>"><?php endif; ?>
                <div class="v5-filter-grid">
                    <div class="v5-field v5-field--wide">
                        <label for="maintenance-name">Category name</label>
                        <input id="maintenance-name" type="text" name="name" maxlength="120" placeholder="e.g. Exchange rate synchronization" value="<?= htmlspecialchars($edit_category['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="v5-field">
                        <button type="submit" class="btn w-full"><?= $edit_category ? 'Save Changes' : 'Add Category' ?></button>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <section class="v5-panel">
        <div class="v5-panel__head"><h2>Operational Categories</h2><span class="v5-count">Live controls</span></div>
        <div class="v5-panel__body">
            <?php if (empty($categories)): ?>
                <div class="v5-empty"><span class="v5-empty__icon">⚙</span><strong>No maintenance categories configured</strong><p>Add the first category above.</p></div>
            <?php else: ?>
                <div class="v5-record-list">
                    <?php foreach ($categories as $cat): ?>
                        <article class="v5-record-row">
                            <div class="v5-record-row__main">
                                <strong><?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <span>Category ID #<?= (int)$cat['id'] ?></span>
                            </div>
                            <div class="v5-record-row__actions">
                                <span class="v5-status-dot <?= $cat['is_active'] ? 'v5-status-dot--on' : 'v5-status-dot--off' ?>"><?= $cat['is_active'] ? 'Active' : 'Inactive' ?></span>
                                <form action="index.php?page=maintenance" method="POST">
                                    <?= csrf_input() ?><input type="hidden" name="toggle_id" value="<?= (int)$cat['id'] ?>"><input type="hidden" name="current_status" value="<?= (int)$cat['is_active'] ?>">
                                    <button type="submit" class="<?= $cat['is_active'] ? 'v5-btn-success' : 'v5-btn-ghost' ?>"><?= $cat['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                                </form>
                                <a href="index.php?page=maintenance&action=edit&id=<?= (int)$cat['id'] ?>" class="v5-btn-ghost">Edit</a>
                                <form method="POST" action="index.php?page=maintenance" onsubmit="return confirm('Delete this maintenance category?');">
                                    <?= csrf_input() ?><input type="hidden" name="delete_id" value="<?= (int)$cat['id'] ?>">
                                    <button type="submit" class="v5-btn-danger">Delete</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php include_template('footer'); ?>
