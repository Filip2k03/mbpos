<?php
// pos/other_income.php - Page for managing other sources of income.

require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/cache.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authentication ---
if (!is_logged_in()) {
    flash_message('error', 'Please log in to manage income.');
    redirect('index.php?page=login');
}

global $connection;
$user_id = $_SESSION['user_id'];
$edit_income = null;

// --- Fetch Currencies for Dropdown ---
$currencies = mbpos_cache_remember('lookup-currencies', 'codes', 300, function () use ($connection) {
    $rows = [];
    $currency_result = mysqli_query($connection, "SELECT code FROM currencies ORDER BY code ASC");
    if ($currency_result) {
        while ($row = mysqli_fetch_assoc($currency_result)) $rows[] = $row['code'];
    }
    return $rows;
});

// --- Handle Add/Update/Delete ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    require_csrf_request();
    $income_id = intval($_POST['delete_id']);
    $stmt = mysqli_prepare($connection, "DELETE FROM other_income WHERE id = ? AND created_by_user_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $income_id, $user_id);
    if (mysqli_stmt_execute($stmt)) flash_message('success', 'Income record deleted successfully.');
    else flash_message('error', 'Failed to delete income record.');
    mysqli_stmt_close($stmt);
    redirect('index.php?page=other_income');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_request();
    $income_id = intval($_POST['income_id'] ?? 0);
    $description = trim($_POST['description']);
    $amount = floatval($_POST['amount']);
    $currency = trim($_POST['currency']);
    $income_date = $_POST['income_date'];

    if (!empty($description) && $amount > 0 && !empty($income_date) && !empty($currency)) {
        if ($income_id > 0) { // Update
            $stmt = mysqli_prepare($connection, "UPDATE other_income SET description = ?, amount = ?, currency = ?, income_date = ? WHERE id = ? AND created_by_user_id = ?");
            mysqli_stmt_bind_param($stmt, 'sdssii', $description, $amount, $currency, $income_date, $income_id, $user_id);
        } else { // Insert
            $stmt = mysqli_prepare($connection, "INSERT INTO other_income (description, amount, currency, income_date, created_by_user_id) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'sdssi', $description, $amount, $currency, $income_date, $user_id);
        }

        if (mysqli_stmt_execute($stmt)) {
            flash_message('success', 'Income record saved successfully.');
        } else {
            flash_message('error', 'Failed to save income record: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    } else {
        flash_message('error', 'Please fill in all required fields.');
    }
    redirect('index.php?page=other_income');
}

if (isset($_GET['action'])) {
    $action = (string)$_GET['action'];
    $id = intval($_GET['id'] ?? 0);

    if ($action === 'edit' && $id > 0) {
        $stmt = mysqli_prepare($connection, "SELECT * FROM other_income WHERE id = ? AND created_by_user_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $edit_income = mysqli_fetch_assoc($result);
    }

    if ($action === 'delete' && $id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = mysqli_prepare($connection, "DELETE FROM other_income WHERE id = ? AND created_by_user_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $user_id);
        if (mysqli_stmt_execute($stmt)) {
            flash_message('success', 'Income record deleted successfully.');
        } else {
            flash_message('error', 'Failed to delete income record.');
        }
        redirect('index.php?page=other_income');
    } elseif ($action === 'delete' && $id > 0) {
        flash_message('warning', 'Please confirm deletion using the form button.');
        redirect('index.php?page=other_income');
    }
}


// --- Fetch Income for Display ---
$incomes = [];
$income_totals = [];
$query = "SELECT * FROM other_income ORDER BY income_date DESC";
$result = mysqli_query($connection, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $incomes[] = $row;
        $code = $row['currency'] ?: 'N/A';
        $income_totals[$code] = ($income_totals[$code] ?? 0) + (float)$row['amount'];
    }
}

include_template('header', ['page' => 'other_income']);
?>
<div class="v5-page">

    <div class="v5-page-head">
        <div class="v5-page-head__copy">
            <span class="v5-kicker" data-i18n="Finance">Finance</span>
            <h1 data-i18n="Other Income">Other Income</h1>
            <p data-i18n="other_income.subtitle">Record non-freight revenue with currency-safe totals and an auditable history.</p>
        </div>
        <?php if ($income_totals): ?>
        <div class="v5-toolbar__group">
            <?php foreach ($income_totals as $code => $total): ?>
            <span class="v5-count">
                <strong><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></strong>
                <?= number_format($total, 2) ?>
            </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- LEFT: Add / Edit Form -->
        <div class="lg:col-span-4">
            <section class="v5-panel">
                <div class="v5-panel__head">
                    <h2><?= $edit_income
                        ? '<span data-i18n="other_income.edit_title">Edit Income Record</span>'
                        : '<span data-i18n="other_income.add_title">Add Income Record</span>' ?></h2>
                    <?php if ($edit_income): ?>
                    <a class="v5-btn-ghost" href="index.php?page=other_income" data-i18n="Cancel edit">Cancel edit</a>
                    <?php endif; ?>
                </div>
                <div class="v5-panel__body">
                    <form action="index.php?page=other_income" method="POST" accept-charset="UTF-8" class="v5-filter-grid">
                        <?= csrf_input() ?>
                        <input type="hidden" name="income_id" value="<?= (int)($edit_income['id'] ?? 0) ?>">

                        <div class="v5-field v5-field--wide">
                            <label for="description" data-i18n="Description">Description</label>
                            <input type="text" id="description" name="description" maxlength="255"
                                   placeholder="Describe the income source"
                                   value="<?= htmlspecialchars($edit_income['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   required>
                        </div>

                        <div class="v5-field v5-field--wide">
                            <label for="amount" data-i18n="Amount">Amount</label>
                            <input type="number" min="0.01" step="0.01" id="amount" name="amount"
                                   value="<?= htmlspecialchars((string)($edit_income['amount'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                   required>
                        </div>

                        <div class="v5-field">
                            <label for="currency" data-i18n="Currency">Currency</label>
                            <select id="currency" name="currency" required>
                                <option value="" disabled <?= empty($edit_income['currency']) ? 'selected' : '' ?> data-i18n="Select currency">Select currency</option>
                                <?php foreach ($currencies as $currency_code): ?>
                                <option value="<?= htmlspecialchars($currency_code, ENT_QUOTES, 'UTF-8') ?>"
                                    <?= ($edit_income && $edit_income['currency'] === $currency_code) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($currency_code, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="v5-field v5-field--wide">
                            <label for="income_date" data-i18n="Income date">Income date</label>
                            <input type="date" id="income_date" name="income_date"
                                   value="<?= htmlspecialchars($edit_income['income_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   required>
                        </div>

                        <div class="v5-field v5-field--wide">
                            <button type="submit" class="btn w-full" data-i18n="<?= $edit_income ? 'Save Changes' : 'Add Income' ?>">
                                <?= $edit_income ? 'Save Changes' : 'Add Income' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <!-- RIGHT: Income History Table -->
        <div class="lg:col-span-8">
            <section class="v5-panel">
                <div class="v5-panel__head">
                    <h2 data-i18n="Income History">Income History</h2>
                    <span class="v5-count"><?= count($incomes) ?> <span data-i18n="records">records</span></span>
                </div>
                <div class="overflow-x-auto">
                    <table class="v5-table min-w-full">
                        <thead>
                            <tr>
                                <th data-i18n="Date">Date</th>
                                <th data-i18n="Description">Description</th>
                                <th data-i18n="Currency">Currency</th>
                                <th data-i18n="Amount">Amount</th>
                                <th data-i18n="Actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($incomes)): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="v5-empty">
                                        <span class="v5-empty__icon"><?= mbpos_icon('other_income', 'w-8 h-8 text-slate-400') ?></span>
                                        <strong data-i18n="other_income.empty_title">No additional income recorded</strong>
                                        <p data-i18n="other_income.empty_hint">Add the first record using the form.</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: foreach ($incomes as $income): ?>
                            <tr>
                                <td><?= date('M j, Y', strtotime($income['income_date'])) ?></td>
                                <td><strong><?= htmlspecialchars($income['description'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                <td>
                                    <span class="v5-badge" style="background:#edf6ff;color:#1675e8;">
                                        <?= htmlspecialchars($income['currency'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td><strong><?= number_format((float)$income['amount'], 2) ?></strong></td>
                                <td>
                                    <div class="v5-toolbar__group">
                                        <a href="index.php?page=other_income&action=edit&id=<?= (int)$income['id'] ?>"
                                           class="v5-btn-ghost" data-i18n="Edit">Edit</a>
                                        <form method="POST" action="index.php?page=other_income"
                                              onsubmit="return confirm('Delete this income record?');">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="delete_id" value="<?= (int)$income['id'] ?>">
                                            <button type="submit" class="v5-btn-danger" data-i18n="Delete">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

    </div><!-- /.grid -->
</div><!-- /.v5-page -->
<?php include_template('footer'); ?>
