<?php
// pos/profit_loss.php - Comprehensive financial analytics and currency-safe profit/loss ledger (V5).

require_once 'config.php';
require_once 'includes/functions.php';
require_once 'includes/cache.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authorization: Admins and Developers ---
if (!is_logged_in() || (!is_admin() && !is_developer())) {
    flash_message('error', 'Access denied. You must be an Administrator or Developer to view financial reports.');
    redirect('index.php?page=dashboard');
}

global $connection;
mysqli_set_charset($connection, "utf8mb4");

// Initialize arrays to store income and expenses by currency
$all_currencies = [];
$all_dates = [];
$all_months = [];

// --- Helper function to fetch and process data ---
function fetch_financial_data($connection, $table, $amount_column, $date_column = null) {
    $data = [];
    $group_by_clause = $date_column ? "GROUP BY currency, DATE($date_column)" : "GROUP BY currency";
    if ($table === 'vouchers') {
        $query = "SELECT SUM(total_amount) AS total_amount, currency" . ($date_column ? ", DATE(created_at) AS report_date" : "") . " FROM $table $group_by_clause";
    } else {
        $query = "SELECT SUM($amount_column) AS total_amount, currency" . ($date_column ? ", DATE($date_column) AS report_date" : "") . " FROM $table $group_by_clause";
    }

    $result = mysqli_query($connection, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $currency = htmlspecialchars($row['currency'] ?? '', ENT_QUOTES, 'UTF-8');
            if (empty($currency)) continue;
            $total_amount = (float)($row['total_amount'] ?? 0);
            if ($date_column) {
                $report_date = $row['report_date'];
                $data[$currency][$report_date] = $total_amount;
            } else {
                $data[$currency] = $total_amount;
            }
        }
        mysqli_free_result($result);
    } else {
        error_log('MBPOS financial report fetch failed for ' . $table . ': ' . mysqli_error($connection));
    }
    return $data;
}

// --- Fetch overall data by currency with cache ---
$voucher_income_by_currency = fetch_financial_data($connection, 'vouchers', 'total_amount');
$other_income_by_currency = fetch_financial_data($connection, 'other_income', 'amount');
$expenses_by_currency = fetch_financial_data($connection, 'expenses', 'amount');

// Populate all_currencies
foreach ($voucher_income_by_currency as $currency => $amount) $all_currencies[$currency] = true;
foreach ($other_income_by_currency as $currency => $amount) $all_currencies[$currency] = true;
foreach ($expenses_by_currency as $currency => $amount) $all_currencies[$currency] = true;

// --- Consolidate overall data for display ---
$financial_summary_by_currency = [];
foreach ($all_currencies as $currency => $dummy) {
    $voucher_income = $voucher_income_by_currency[$currency] ?? 0;
    $other_income = $other_income_by_currency[$currency] ?? 0;
    $expenses = $expenses_by_currency[$currency] ?? 0;

    $total_revenue = $voucher_income + $other_income;
    $net_worth = $total_revenue - $expenses;

    $financial_summary_by_currency[$currency] = [
        'voucher_income' => $voucher_income,
        'other_income' => $other_income,
        'expenses' => $expenses,
        'total_revenue' => $total_revenue,
        'net_worth' => $net_worth
    ];
}
ksort($financial_summary_by_currency);

// --- Fetch Daily Data ---
$daily_voucher_income = fetch_financial_data($connection, 'vouchers', 'total_amount', 'created_at');
$daily_other_income = fetch_financial_data($connection, 'other_income', 'amount', 'created_at');
$daily_expenses = fetch_financial_data($connection, 'expenses', 'amount', 'created_at');

$daily_financial_summary = [];
foreach ($daily_voucher_income as $currency => $dates) {
    foreach ($dates as $date => $amount) {
        $all_dates[$date] = true;
        $all_currencies[$currency] = true;
    }
}
foreach ($daily_other_income as $currency => $dates) {
    foreach ($dates as $date => $amount) {
        $all_dates[$date] = true;
        $all_currencies[$currency] = true;
    }
}
foreach ($daily_expenses as $currency => $dates) {
    foreach ($dates as $date => $amount) {
        $all_dates[$date] = true;
        $all_currencies[$currency] = true;
    }
}
krsort($all_dates);

foreach ($all_dates as $date => $dummy_date) {
    foreach ($all_currencies as $currency => $dummy_currency) {
        $voucher_income = $daily_voucher_income[$currency][$date] ?? 0;
        $other_income = $daily_other_income[$currency][$date] ?? 0;
        $expenses = $daily_expenses[$currency][$date] ?? 0;

        $total_revenue = $voucher_income + $other_income;
        $net_worth = $total_revenue - $expenses;

        if (!isset($daily_financial_summary[$date])) {
            $daily_financial_summary[$date] = [];
        }
        $daily_financial_summary[$date][$currency] = [
            'voucher_income' => $voucher_income,
            'other_income' => $other_income,
            'expenses' => $expenses,
            'total_revenue' => $total_revenue,
            'net_worth' => $net_worth
        ];
    }
}

// --- Fetch Monthly Data ---
$query_monthly_voucher_income = "SELECT SUM(total_amount) AS total_amount, currency, DATE_FORMAT(created_at, '%Y-%m') AS report_month FROM vouchers GROUP BY currency, report_month";
$query_monthly_other_income = "SELECT SUM(amount) AS total_amount, currency, DATE_FORMAT(created_at, '%Y-%m') AS report_month FROM other_income GROUP BY currency, report_month";
$query_monthly_expenses = "SELECT SUM(amount) AS total_amount, currency, DATE_FORMAT(created_at, '%Y-%m') AS report_month FROM expenses GROUP BY currency, report_month";

$monthly_voucher_income = [];
$monthly_other_income = [];
$monthly_expenses = [];

function process_monthly_query($connection, $query, &$target_array, &$all_months, &$all_currencies) {
    $result = mysqli_query($connection, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $currency = htmlspecialchars($row['currency'] ?? '', ENT_QUOTES, 'UTF-8');
            if (empty($currency)) continue;
            $report_month = $row['report_month'];
            $total_amount = (float)$row['total_amount'];
            $target_array[$currency][$report_month] = $total_amount;
            $all_months[$report_month] = true;
            $all_currencies[$currency] = true;
        }
        mysqli_free_result($result);
    }
}

process_monthly_query($connection, $query_monthly_voucher_income, $monthly_voucher_income, $all_months, $all_currencies);
process_monthly_query($connection, $query_monthly_other_income, $monthly_other_income, $all_months, $all_currencies);
process_monthly_query($connection, $query_monthly_expenses, $monthly_expenses, $all_months, $all_currencies);

krsort($all_months);
$monthly_financial_summary = [];

foreach ($all_months as $month => $dummy_month) {
    foreach ($all_currencies as $currency => $dummy_currency) {
        $voucher_income = $monthly_voucher_income[$currency][$month] ?? 0;
        $other_income = $monthly_other_income[$currency][$month] ?? 0;
        $expenses = $monthly_expenses[$currency][$month] ?? 0;

        $total_revenue = $voucher_income + $other_income;
        $net_worth = $total_revenue - $expenses;

        if (!isset($monthly_financial_summary[$month])) {
            $monthly_financial_summary[$month] = [];
        }
        $monthly_financial_summary[$month][$currency] = [
            'voucher_income' => $voucher_income,
            'other_income' => $other_income,
            'expenses' => $expenses,
            'total_revenue' => $total_revenue,
            'net_worth' => $net_worth
        ];
    }
}

include_template('header', ['page' => 'profit_loss']);
?>

<div class="v5-page">
    <div class="v5-page-head">
        <div class="v5-page-head__copy">
            <span class="v5-kicker" data-i18n="Financial Oversight">Financial Oversight</span>
            <h1 data-i18n="Profit & Loss Ledger">Profit & Loss Ledger</h1>
            <p data-i18n="Currency-isolated financial accounting, net balances, and transaction summaries.">Currency-isolated financial accounting, net balances, and transaction summaries.</p>
        </div>
        <div class="v5-page-actions flex items-center gap-3">
            <a href="index.php?page=expenses" class="btn-secondary btn-sm" data-i18n="Manage Expenses">Manage Expenses</a>
            <a href="index.php?page=other_income" class="btn-primary btn-sm" data-i18n="Manage Other Income">Manage Other Income</a>
        </div>
    </div>

    <!-- Currency-Isolated Metrics Strip (NEVER combined into one misleading sum) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <?php foreach ($financial_summary_by_currency as $curr => $data):
            $is_positive = $data['net_worth'] >= 0;
        ?>
            <div class="v5-glass-card p-5 relative overflow-hidden">
                <div class="flex items-center justify-between mb-3">
                    <span class="v5-badge v5-badge-info font-mono font-bold text-xs"><?= e($curr) ?></span>
                    <span class="text-xs font-semibold inline-flex items-center gap-1 <?= $is_positive ? 'text-success' : 'text-danger' ?>">
                        <?php if ($is_positive): ?>
                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                            <span data-i18n="Net Positive">Net Positive</span>
                        <?php else: ?>
                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/></svg>
                            <span data-i18n="Net Deficit">Net Deficit</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="text-2xl font-black text-main leading-tight mb-1 font-mono">
                    <?= number_format($data['net_worth'], 2) ?>
                </div>
                <div class="text-xs text-muted uppercase font-bold tracking-wider mb-3">
                    <span data-i18n="Net Worth">Net Worth</span> (<?= e($curr) ?>)
                </div>
                <div class="border-t border-slate-100 pt-2 flex items-center justify-between text-xs text-muted">
                    <span><strong class="text-primary"><?= number_format($data['total_revenue'], 2) ?></strong> Rev</span>
                    <span><strong class="text-danger"><?= number_format($data['expenses'], 2) ?></strong> Exp</span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Financial Breakdown Panel with Tabs -->
    <section class="v5-panel">
        <div class="v5-panel__head flex-wrap gap-4">
            <div class="flex items-center gap-2">
                <button type="button" class="btn-primary btn-sm pl-tab-btn active" data-tab="tab-currency" data-i18n="By Currency">By Currency</button>
                <button type="button" class="btn-ghost btn-sm pl-tab-btn" data-tab="tab-daily" data-i18n="Daily Ledger">Daily Ledger</button>
                <button type="button" class="btn-ghost btn-sm pl-tab-btn" data-tab="tab-monthly" data-i18n="Monthly Ledger">Monthly Ledger</button>
            </div>
            <span class="v5-count" data-i18n="Strict currency separation enforced">Strict currency separation enforced</span>
        </div>

        <div class="v5-panel__body p-0">
            <!-- TAB 1: By Currency -->
            <div id="tab-currency" class="pl-tab-pane">
                <div class="overflow-x-auto">
                    <table class="v5-table w-full">
                        <thead>
                            <tr>
                                <th data-i18n="Currency">Currency</th>
                                <th class="text-right" data-i18n="Voucher Income">Voucher Income</th>
                                <th class="text-right" data-i18n="Other Income">Other Income</th>
                                <th class="text-right" data-i18n="Total Revenue">Total Revenue</th>
                                <th class="text-right" data-i18n="Total Expenses">Total Expenses</th>
                                <th class="text-right" data-i18n="Net Profit">Net Profit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($financial_summary_by_currency)): ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="v5-empty">
                                            <span class="v5-empty__icon"><?= mbpos_icon('profit_loss', 'w-8 h-8 text-slate-400') ?></span>
                                            <strong data-i18n="No financial records available.">No financial records available.</strong>
                                            <p data-i18n="Create vouchers or log expenses to see financial reports.">Create vouchers or log expenses to see financial reports.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($financial_summary_by_currency as $currency => $data):
                                    $is_profit = $data['net_worth'] >= 0;
                                ?>
                                    <tr>
                                        <td>
                                            <span class="v5-badge v5-badge-info font-mono font-bold"><?= e($currency) ?></span>
                                        </td>
                                        <td class="text-right font-mono font-semibold text-main">
                                            <?= number_format($data['voucher_income'], 2) ?>
                                        </td>
                                        <td class="text-right font-mono font-semibold text-main">
                                            <?= number_format($data['other_income'], 2) ?>
                                        </td>
                                        <td class="text-right font-mono font-bold text-primary">
                                            <?= number_format($data['total_revenue'], 2) ?>
                                        </td>
                                        <td class="text-right font-mono font-bold text-danger">
                                            <?= number_format($data['expenses'], 2) ?>
                                        </td>
                                        <td class="text-right font-mono font-bold text-base <?= $is_profit ? 'text-success' : 'text-danger' ?>">
                                            <?= number_format($data['net_worth'], 2) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 2: Daily Ledger -->
            <div id="tab-daily" class="pl-tab-pane hidden">
                <div class="overflow-x-auto">
                    <table class="v5-table w-full">
                        <thead>
                            <tr>
                                <th data-i18n="Date">Date</th>
                                <?php foreach (array_keys($all_currencies) as $curr_code): ?>
                                    <th class="text-right font-mono"><?= e($curr_code) ?> (Net)</th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($daily_financial_summary)): ?>
                                <tr>
                                    <td colspan="<?= count($all_currencies) + 1 ?>">
                                        <div class="v5-empty">
                                            <strong data-i18n="No daily financial activity recorded.">No daily financial activity recorded.</strong>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach (array_slice($daily_financial_summary, 0, 60, true) as $date => $currencies_data): ?>
                                    <tr>
                                        <td class="font-bold text-main font-mono whitespace-nowrap">
                                            <span><?= format_datetime_myanmar($date, 'date') ?></span>
                                            <span class="text-[10px] text-slate-400 font-sans ml-1">(<?= date('D', strtotime($date)) ?>)</span>
                                        </td>
                                        <?php foreach (array_keys($all_currencies) as $curr_code): 
                                            $net = $currencies_data[$curr_code]['net_worth'] ?? 0;
                                        ?>
                                            <td class="text-right font-mono font-semibold <?= $net > 0 ? 'text-success' : ($net < 0 ? 'text-danger' : 'text-muted') ?>">
                                                <?= number_format($net, 2) ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 3: Monthly Ledger -->
            <div id="tab-monthly" class="pl-tab-pane hidden">
                <div class="overflow-x-auto">
                    <table class="v5-table w-full">
                        <thead>
                            <tr>
                                <th data-i18n="Month">Month</th>
                                <?php foreach (array_keys($all_currencies) as $curr_code): ?>
                                    <th class="text-right font-mono"><?= e($curr_code) ?> (Net)</th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($monthly_financial_summary)): ?>
                                <tr>
                                    <td colspan="<?= count($all_currencies) + 1 ?>">
                                        <div class="v5-empty">
                                            <strong data-i18n="No monthly financial history recorded.">No monthly financial history recorded.</strong>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($monthly_financial_summary as $month => $currencies_data): ?>
                                    <tr>
                                        <td class="font-bold text-main font-mono"><?= e($month) ?></td>
                                        <?php foreach (array_keys($all_currencies) as $curr_code): 
                                            $net = $currencies_data[$curr_code]['net_worth'] ?? 0;
                                        ?>
                                            <td class="text-right font-mono font-semibold <?= $net > 0 ? 'text-success' : ($net < 0 ? 'text-danger' : 'text-muted') ?>">
                                                <?= number_format($net, 2) ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabButtons = document.querySelectorAll('.pl-tab-btn');
    const tabPanes = document.querySelectorAll('.pl-tab-pane');

    tabButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            tabButtons.forEach(b => {
                b.classList.remove('btn-primary', 'active');
                b.classList.add('btn-ghost');
            });
            this.classList.remove('btn-ghost');
            this.classList.add('btn-primary', 'active');

            const targetId = this.getAttribute('data-tab');
            tabPanes.forEach(pane => {
                if (pane.id === targetId) {
                    pane.classList.remove('hidden');
                } else {
                    pane.classList.add('hidden');
                }
            });
        });
    });
});
</script>

<?php include_template('footer'); ?>
