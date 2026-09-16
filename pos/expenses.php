<?php
// pos/expenses.php - Page for managing expenses (V5 UI).

require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/cache.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authentication ---
if (!is_logged_in()) {
    flash_message('error', 'Please log in to manage expenses.');
    redirect('index.php?page=login');
}

global $connection;

// --- CRITICAL FIX FOR MYANMAR FONTS ---
mysqli_set_charset($connection, "utf8mb4");

$user_id = $_SESSION['user_id'];
$edit_expense = null;

// --- Fetch Currencies for Dropdown ---
$currencies = mbpos_cache_remember('lookup-currencies', 'codes', 300, function () use ($connection) {
    $rows = [];
    $currency_result = mysqli_query($connection, "SELECT code FROM currencies ORDER BY code ASC");
    if ($currency_result) while ($row = mysqli_fetch_assoc($currency_result)) $rows[] = $row['code'];
    return $rows;
});

// --- Handle Add/Update/Delete ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    require_csrf_request();
    $expense_id = intval($_POST['delete_id']);
    $stmt = mysqli_prepare($connection, "DELETE FROM expenses WHERE id = ? AND created_by_user_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $expense_id, $user_id);
    if (mysqli_stmt_execute($stmt)) flash_message('success', 'Expense deleted successfully.');
    else flash_message('error', 'Failed to delete expense.');
    mysqli_stmt_close($stmt);
    redirect('index.php?page=expenses');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_request();
    $expense_id = intval($_POST['expense_id'] ?? 0);
    $description = trim($_POST['description']);
    $amount = floatval($_POST['amount']);
    $currency = trim($_POST['currency']);
    $expense_date = $_POST['expense_date'];

    if (!empty($description) && $amount > 0 && !empty($expense_date) && !empty($currency)) {
        if ($expense_id > 0) { // Update logic
            $stmt = mysqli_prepare($connection, "UPDATE expenses SET description = ?, amount = ?, currency = ?, expense_date = ? WHERE id = ? AND created_by_user_id = ?");
            mysqli_stmt_bind_param($stmt, 'sdssii', $description, $amount, $currency, $expense_date, $expense_id, $user_id);
        } else { // Insert logic
            $stmt = mysqli_prepare($connection, "INSERT INTO expenses (description, amount, currency, expense_date, created_by_user_id) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'sdssi', $description, $amount, $currency, $expense_date, $user_id);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            flash_message('success', 'Expense saved successfully.');
        } else {
            flash_message('error', 'Failed to save expense: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    } else {
        flash_message('error', 'Please fill in all required fields.');
    }
    redirect('index.php?page=expenses');
}

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = intval($_GET['id'] ?? 0);

    if ($action === 'edit' && $id > 0) {
        $stmt = mysqli_prepare($connection, "SELECT * FROM expenses WHERE id = ? AND created_by_user_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $edit_expense = mysqli_fetch_assoc($result);
    }
    
    if ($action === 'delete' && $id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = mysqli_prepare($connection, "DELETE FROM expenses WHERE id = ? AND created_by_user_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $user_id);
        if (mysqli_stmt_execute($stmt)) {
            flash_message('success', 'Expense deleted successfully.');
        } else {
            flash_message('error', 'Failed to delete expense.');
        }
        redirect('index.php?page=expenses');
    } elseif ($action === 'delete' && $id > 0) {
        flash_message('warning', 'Please confirm deletion using the form button.');
        redirect('index.php?page=expenses');
    }
}

// --- Fetch Expenses for Display ---
$expenses = [];
$query = "SELECT * FROM expenses ORDER BY expense_date DESC";
$result = mysqli_query($connection, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $expenses[] = $row;
    }
}

include_template('header', ['page' => 'expenses']);
?>

<div class="v5-page">
    <div class="v5-page-head">
        <div class="v5-page-head__copy">
            <span class="v5-kicker" data-i18n="Finance">Finance</span>
            <h1 data-i18n="System Expenses">System Expenses</h1>
            <p data-i18n="expenses_subtitle">Record and monitor operational outgoings</p>
        </div>
        <span class="v5-count"><?= count($expenses) ?> <span data-i18n="entries">entries</span></span>
    </div>

    <section class="v5-panel">
        <div class="v5-panel__head">
            <h2 data-i18n="<?= $edit_expense ? 'edit_expense' : 'log_new_expense' ?>"><?= $edit_expense ? 'Edit Expense' : 'Log New Expense' ?></h2>
            <?php if ($edit_expense): ?>
                <a class="v5-btn-ghost" href="index.php?page=expenses" data-i18n="cancel">Cancel</a>
            <?php endif; ?>
        </div>
        <div class="v5-panel__body">
            <!-- Enforce UTF-8 form submission for Myanmar language -->
            <form action="index.php?page=expenses" method="POST" accept-charset="UTF-8" class="v5-filter-grid">
                <?= csrf_input() ?>
                <input type="hidden" name="expense_id" value="<?= (int)($edit_expense['id'] ?? 0) ?>">

                <div class="v5-field v5-field--wide">
                    <label for="description" data-i18n="description">Description</label>
                    <textarea id="description" name="description" rows="2" placeholder="e.g. Office Supplies / ရုံးသုံးပစ္စည်း" required><?= htmlspecialchars($edit_expense['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <div class="v5-field">
                    <label for="amount" data-i18n="amount">Amount</label>
                    <input type="number" step="0.01" id="amount" name="amount" placeholder="0.00" value="<?= htmlspecialchars($edit_expense['amount'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <div class="v5-field">
                    <label for="currency" data-i18n="currency">Currency</label>
                    <select id="currency" name="currency" required>
                        <option value="" disabled <?= empty($edit_expense['currency']) ? 'selected' : '' ?> data-i18n="Select currency">Select currency</option>
                        <?php foreach ($currencies as $currency_code): ?>
                            <option value="<?= htmlspecialchars($currency_code, ENT_QUOTES, 'UTF-8') ?>"
                                <?= (isset($edit_expense) && $edit_expense['currency'] === $currency_code) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($currency_code, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="v5-field">
                    <label for="expense_date" data-i18n="expense_date">Expense Date</label>
                    <input type="date" id="expense_date" name="expense_date" value="<?= htmlspecialchars($edit_expense['expense_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <div class="v5-field">
                    <button type="submit" class="btn w-full" data-i18n="<?= $edit_expense ? 'update_expense' : 'record_expense' ?>"><?= $edit_expense ? 'Update Expense' : 'Record Expense' ?></button>
                </div>
            </form>
        </div>
    </section>

    <section class="v5-panel">
        <div class="v5-panel__head">
            <h2 data-i18n="expense_ledger">Expense Ledger</h2>
            <span class="v5-count"><?= count($expenses) ?> <span data-i18n="entries">entries</span></span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr>
                        <th data-i18n="date">Date</th>
                        <th data-i18n="description">Description</th>
                        <th data-i18n="amount">Amount</th>
                        <th data-i18n="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)): ?>
                        <tr>
                            <td colspan="4">
                                <div class="v5-empty">
                                    <span class="v5-empty__icon"><?= mbpos_icon('expenses', 'w-8 h-8 text-slate-400') ?></span>
                                    <strong data-i18n="no_expenses_recorded">No expenses recorded yet.</strong>
                                    <p data-i18n="add_first_expense">Add the first record using the form above.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $expense): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($expense['expense_date']) ?></strong>
                                </td>
                                <td>
                                    <!-- ENT_QUOTES | UTF-8 prevents corruption of Myanmar encoding -->
                                    <?= htmlspecialchars($expense['description'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <span class="v5-count" style="color:#be123c;border-color:#fecdd3;background:#fff1f2">
                                        <?= htmlspecialchars($expense['currency']) ?> <?= number_format($expense['amount'], 2) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="v5-toolbar__group">
                                        <a href="index.php?page=expenses&action=edit&id=<?= (int)$expense['id'] ?>" class="v5-btn-ghost" data-i18n="edit" title="Edit">
                                            <svg style="width:1rem;height:1rem" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            <span data-i18n="edit">Edit</span>
                                        </a>
                                        <form method="POST" action="index.php?page=expenses" class="inline" onsubmit="return confirm('Delete this expense?');">
                                            <?= csrf_input() ?><input type="hidden" name="delete_id" value="<?= (int)$expense['id'] ?>">
                                            <button type="submit" class="v5-btn-danger" data-i18n="delete" title="Delete">
                                                <svg style="width:1rem;height:1rem" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                <span data-i18n="delete">Delete</span>
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
    </section>
</div>

<?php include_template('footer'); ?>
