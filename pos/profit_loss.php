<?php
// pos/profit_loss.php
// Admin & Developer page for calculating profits and expenses, broken down by currency, daily, and monthly

require_once 'config.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- V3 Authorization Update: Admins AND Developers can access ---
if (!is_logged_in() || (!is_admin() && !is_developer())) {
    flash_message('error', 'Access denied. You must be an Administrator or Developer to view financial reports.');
    redirect('index.php?page=dashboard');
}

global $connection;

// --- CRITICAL FIX FOR MYANMAR FONTS ---
mysqli_set_charset($connection, "utf8mb4");

// Initialize arrays to store income and expenses by currency
$voucher_income_by_currency = [];
$other_income_by_currency = [];
$expenses_by_currency = [];
$all_currencies = []; // To keep track of all unique currencies found

// Initialize arrays for daily and monthly data
$daily_net_worth = [];
$monthly_net_worth = [];
$all_dates = []; // To keep track of all unique dates for daily
$all_months = []; // To keep track of all unique months for monthly

// --- Helper function to fetch and process data ---
function fetch_financial_data($connection, $table, $amount_column, $date_column = null) {
    $data = [];
    $group_by_clause = $date_column ? "GROUP BY currency, DATE($date_column)" : "GROUP BY currency";
    if ($table === 'vouchers') { // Special case for vouchers as it uses total_amount
        $query = "SELECT SUM(total_amount) AS total_amount, currency" . ($date_column ? ", DATE(created_at) AS report_date" : "") . " FROM $table $group_by_clause";
    } else {
        $query = "SELECT SUM($amount_column) AS total_amount, currency" . ($date_column ? ", DATE($date_column) AS report_date" : "") . " FROM $table $group_by_clause";
    }

    $result = mysqli_query($connection, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $currency = htmlspecialchars($row['currency'], ENT_QUOTES, 'UTF-8');
            $total_amount = (float)$row['total_amount'];
            if ($date_column) {
                $report_date = $row['report_date'];
                $data[$currency][$report_date] = $total_amount;
            } else {
                $data[$currency] = $total_amount;
            }
        }
        mysqli_free_result($result);
    } else {
        flash_message('error', 'Error fetching data from ' . $table . ': ' . mysqli_error($connection));
    }
    return $data;
}

// --- Fetch overall data by currency ---
$voucher_income_by_currency = fetch_financial_data($connection, 'vouchers', 'total_amount');
$other_income_by_currency = fetch_financial_data($connection, 'other_income', 'amount');
$expenses_by_currency = fetch_financial_data($connection, 'expenses', 'amount');

// Populate all_currencies
foreach ($voucher_income_by_currency as $currency => $amount) {
    $all_currencies[$currency] = true;
}
foreach ($other_income_by_currency as $currency => $amount) {
    $all_currencies[$currency] = true;
}
foreach ($expenses_by_currency as $currency => $amount) {
    $all_currencies[$currency] = true;
}

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
// Gather all unique dates and currencies
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
ksort($all_dates);

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
// For monthly, we'll extract the year-month from the created_at column
$query_monthly_voucher_income = "SELECT SUM(total_amount) AS total_amount, currency, DATE_FORMAT(created_at, '%Y-%m') AS report_month FROM vouchers GROUP BY currency, report_month";
$query_monthly_other_income = "SELECT SUM(amount) AS total_amount, currency, DATE_FORMAT(created_at, '%Y-%m') AS report_month FROM other_income GROUP BY currency, report_month";
$query_monthly_expenses = "SELECT SUM(amount) AS total_amount, currency, DATE_FORMAT(created_at, '%Y-%m') AS report_month FROM expenses GROUP BY currency, report_month";

$monthly_voucher_income = [];
$monthly_other_income = [];
$monthly_expenses = [];

function process_monthly_query($connection, $query, &$target_array) {
    $result = mysqli_query($connection, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $currency = htmlspecialchars($row['currency'], ENT_QUOTES, 'UTF-8');
            $report_month = $row['report_month'];
            $total_amount = (float)$row['total_amount'];
            $target_array[$currency][$report_month] = $total_amount;
            global $all_months;
            $all_months[$report_month] = true;
            global $all_currencies;
            $all_currencies[$currency] = true;
        }
        mysqli_free_result($result);
    } else {
        flash_message('error', 'Error fetching monthly data: ' . mysqli_error($connection));
    }
}

process_monthly_query($connection, $query_monthly_voucher_income, $monthly_voucher_income);
process_monthly_query($connection, $query_monthly_other_income, $monthly_other_income);
process_monthly_query($connection, $query_monthly_expenses, $monthly_expenses);

$monthly_financial_summary = [];
ksort($all_months);

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

// Prepare data for Chart.js
$chart_labels_daily = array_keys($daily_financial_summary);
$chart_net_worth_daily = [];
$chart_total_revenue_daily = [];
$chart_expenses_daily = [];

foreach ($chart_labels_daily as $date) {
    $daily_total_net_worth = 0;
    $daily_total_revenue = 0;
    $daily_total_expenses = 0;
    foreach ($daily_financial_summary[$date] as $currency_data) {
        $daily_total_net_worth += $currency_data['net_worth'];
        $daily_total_revenue += $currency_data['total_revenue'];
        $daily_total_expenses += $currency_data['expenses'];
    }
    $chart_net_worth_daily[] = $daily_total_net_worth;
    $chart_total_revenue_daily[] = $daily_total_revenue;
    $chart_expenses_daily[] = $daily_total_expenses;
}

$chart_labels_monthly = array_keys($monthly_financial_summary);
$chart_net_worth_monthly = [];
$chart_total_revenue_monthly = [];
$chart_expenses_monthly = [];

foreach ($chart_labels_monthly as $month) {
    $monthly_total_net_worth = 0;
    $monthly_total_revenue = 0;
    $monthly_total_expenses = 0;
    foreach ($monthly_financial_summary[$month] as $currency_data) {
        $monthly_total_net_worth += $currency_data['net_worth'];
        $monthly_total_revenue += $currency_data['total_revenue'];
        $monthly_total_expenses += $currency_data['expenses'];
    }
    $chart_net_worth_monthly[] = $monthly_total_net_worth;
    $chart_total_revenue_monthly[] = $monthly_total_revenue;
    $chart_expenses_monthly[] = $monthly_total_expenses;
}

include_template('header', ['page' => 'profit_loss']);
?>

<!-- V3 Liquid UI Wrapper -->
<div class="relative min-h-[85vh] bg-slate-50/30 p-4 sm:p-8 overflow-hidden font-sans">
    
    <!-- Ambient Background Glows -->
    <div class="absolute top-[0%] left-[20%] w-[600px] h-[600px] bg-emerald-500/10 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-[10%] right-[10%] w-[500px] h-[500px] bg-indigo-500/10 rounded-full blur-[120px] pointer-events-none"></div>

    <div class="max-w-7xl mx-auto relative z-10">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-5 animate-fadeInDown">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl flex items-center justify-center shadow-lg shadow-emerald-500/30 text-white transform -rotate-3 hover:rotate-0 transition-transform duration-300 border border-emerald-400/20">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent tracking-tight">Profit & Loss Ledger</h1>
                    <p class="text-sm font-medium text-slate-500 mt-1">Comprehensive financial analytics and revenue tracking.</p>
                </div>
            </div>
        </div>

        <div class="bg-white/80 backdrop-blur-2xl rounded-[2.5rem] shadow-[0_8px_40px_rgb(0,0,0,0.04)] border border-white/60 p-6 sm:p-10 mb-8 animate-fadeInDown" style="animation-delay: 0.1s;">
            
            <!-- V3 Glassmorphism Tabs -->
            <div class="flex flex-wrap gap-2 mb-8 bg-slate-100/50 p-1.5 rounded-2xl border border-slate-200/60 inline-flex">
                <button class="tab-button py-2.5 px-6 text-sm font-bold text-slate-500 rounded-xl focus:outline-none transition-all duration-300 active-tab bg-white text-indigo-600 shadow-sm border border-slate-100" data-tab="currency">By Currency</button>
                <button class="tab-button py-2.5 px-6 text-sm font-bold text-slate-500 rounded-xl focus:outline-none transition-all duration-300 hover:text-indigo-500 hover:bg-white/50" data-tab="daily">Daily Summary</button>
                <button class="tab-button py-2.5 px-6 text-sm font-bold text-slate-500 rounded-xl focus:outline-none transition-all duration-300 hover:text-indigo-500 hover:bg-white/50" data-tab="monthly">Monthly Summary</button>
                <button class="tab-button py-2.5 px-6 text-sm font-bold text-slate-500 rounded-xl focus:outline-none transition-all duration-300 hover:text-indigo-500 hover:bg-white/50" data-tab="charts">Visual Charts</button>
            </div>

            <!-- TAB 1: CURRENCY -->
            <div id="currency" class="tab-content block">
                <h3 class="text-xl font-bold text-slate-800 mb-6 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Summary by Currency
                </h3>
                <?php if (empty($financial_summary_by_currency)): ?>
                    <div class="text-center py-12 bg-slate-50/50 rounded-2xl border border-slate-100 text-slate-400 font-medium">No financial data available to display by currency.</div>
                <?php else: ?>
                    <div class="overflow-x-auto w-full custom-scrollbar rounded-2xl border border-slate-100 shadow-sm bg-white">
                        <table class="min-w-full text-left border-collapse whitespace-nowrap">
                            <thead class="bg-slate-50/80 border-b border-slate-100">
                                <tr>
                                    <th class="py-4 px-6 text-xs font-extrabold text-slate-400 uppercase tracking-widest">Currency</th>
                                    <th class="py-4 px-6 text-xs font-extrabold text-slate-400 uppercase tracking-widest text-right">Voucher Income</th>
                                    <th class="py-4 px-6 text-xs font-extrabold text-slate-400 uppercase tracking-widest text-right">Other Income</th>
                                    <th class="py-4 px-6 text-xs font-extrabold text-slate-400 uppercase tracking-widest text-right">Total Revenue</th>
                                    <th class="py-4 px-6 text-xs font-extrabold text-slate-400 uppercase tracking-widest text-right">Total Expenses</th>
                                    <th class="py-4 px-6 text-xs font-extrabold text-slate-400 uppercase tracking-widest text-right">Net Worth</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100/60">
                                <?php foreach ($financial_summary_by_currency as $currency => $data): ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors duration-200">
                                        <td class="py-4 px-6">
                                            <span class="inline-flex items-center gap-1.5 font-mono text-sm font-bold text-slate-700 bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200">
                                                <?= $currency; ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-6 text-right font-medium text-slate-600"><?= number_format($data['voucher_income'], 2); ?></td>
                                        <td class="py-4 px-6 text-right font-medium text-slate-600"><?= number_format($data['other_income'], 2); ?></td>
                                        <td class="py-4 px-6 text-right font-extrabold text-indigo-600 bg-indigo-50/30"><?= number_format($data['total_revenue'], 2); ?></td>
                                        <td class="py-4 px-6 text-right font-extrabold text-rose-500 bg-rose-50/30"><?= number_format($data['expenses'], 2); ?></td>
                                        <td class="py-4 px-6 text-right font-extrabold text-lg <?= ($data['net_worth'] >= 0) ? 'text-emerald-600 bg-emerald-50/30' : 'text-red-600 bg-red-50/30'; ?>">
                                            <?= number_format($data['net_worth'], 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- TAB 2: DAILY -->
            <div id="daily" class="tab-content hidden">
                <h3 class="text-xl font-bold text-slate-800 mb-6 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span> Daily Financial Summary
                </h3>
                <?php if (empty($daily_financial_summary)): ?>
                    <div class="text-center py-12 bg-slate-50/50 rounded-2xl border border-slate-100 text-slate-400 font-medium">No daily financial data available to display.</div>
                <?php else: ?>
                    <div class="overflow-x-auto w-full custom-scrollbar rounded-2xl border border-slate-100 shadow-sm bg-white">
                        <table class="min-w-full text-left border-collapse whitespace-nowrap">
                            <thead class="bg-slate-50/80 border-b border-slate-100">
                                <tr>
                                    <th class="py-4 px-6 text-xs font-extrabold text-slate-400 uppercase tracking-widest">Date</th>
                                    <?php foreach (array_keys($all_currencies) as $curr_code): ?>
                                        <th class="py-4 px-6 text-xs font-extrabold text-slate-400 uppercase tracking-widest text-right"><?= $curr_code; ?> (Net)</th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100/60">
                                <?php foreach ($daily_financial_summary as $date => $currencies_data): ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors duration-200">
                                        <td class="py-4 px-6 font-bold text-slate-800"><?= htmlspecialchars($date); ?></td>
                                        <?php foreach (array_keys($all_currencies) as $curr_code): 
                                            $net = $currencies_data[$curr_code]['net_worth'] ?? 0;
                                        ?>
                                            <td class="py-4 px-6 text-right font-extrabold <?= $net >= 0 ? 'text-emerald-600' : 'text-red-500'; ?>">
                                                <?= number_format($net, 2); ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- TAB 3: MONTHLY -->
            <div id="monthly" class="tab-content hidden">
                <h3 class="text-xl font-bold text-slate-800 mb-6 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-purple-500"></span> Monthly Financial Summary
                </h3>
                <?php if (empty($monthly_financial_summary)): ?>
                    <div class="text-center py-12 bg-slate-50/50 rounded-2xl border border-slate-100 text-slate-400 font-medium">No monthly financial data available to display.</div>
                <?php else: ?>
                    <div class="overflow-x-auto w-full custom-scrollbar rounded-2xl border border-slate-100 shadow-sm bg-white">
                        <table class="min-w-full text-left border-collapse whitespace-nowrap">
                            <thead class="bg-slate-50/80 border-b border-slate-100">
                                <tr>
                                    <th class="py-4 px-6 text-xs font-extrabold text-slate-400 uppercase tracking-widest">Month</th>
                                    <?php foreach (array_keys($all_currencies) as $curr_code): ?>
                                        <th class="py-4 px-6 text-xs font-extrabold text-slate-400 uppercase tracking-widest text-right"><?= $curr_code; ?> (Net)</th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100/60">
                                <?php foreach ($monthly_financial_summary as $month => $currencies_data): ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors duration-200">
                                        <td class="py-4 px-6 font-bold text-slate-800"><?= htmlspecialchars($month); ?></td>
                                        <?php foreach (array_keys($all_currencies) as $curr_code): 
                                            $net = $currencies_data[$curr_code]['net_worth'] ?? 0;
                                        ?>
                                            <td class="py-4 px-6 text-right font-extrabold <?= $net >= 0 ? 'text-emerald-600' : 'text-red-500'; ?>">
                                                <?= number_format($net, 2); ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- TAB 4: CHARTS -->
            <div id="charts" class="tab-content hidden">
                <h3 class="text-xl font-bold text-slate-800 mb-6 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-cyan-500"></span> Visual Financial Charts
                </h3>
                
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                    <!-- Daily Net Worth -->
                    <div class="bg-white border border-slate-100 rounded-3xl p-6 shadow-sm">
                        <h4 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-4">Daily Net Worth (Combined)</h4>
                        <div class="relative h-64 w-full">
                            <canvas id="dailyNetWorthChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Monthly Net Worth -->
                    <div class="bg-white border border-slate-100 rounded-3xl p-6 shadow-sm">
                        <h4 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-4">Monthly Net Worth (Combined)</h4>
                        <div class="relative h-64 w-full">
                            <canvas id="monthlyNetWorthChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Daily Rev vs Exp -->
                    <div class="bg-white border border-slate-100 rounded-3xl p-6 shadow-sm">
                        <h4 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-4">Daily Revenue vs. Expenses</h4>
                        <div class="relative h-64 w-full">
                            <canvas id="dailyRevenueExpensesChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Monthly Rev vs Exp -->
                    <div class="bg-white border border-slate-100 rounded-3xl p-6 shadow-sm">
                        <h4 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-4">Monthly Revenue vs. Expenses</h4>
                        <div class="relative h-64 w-full">
                            <canvas id="monthlyRevenueExpensesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- External Quick Actions -->
        <div class="flex flex-wrap justify-center gap-4 animate-fadeInDown" style="animation-delay: 0.2s;">
            <a href="index.php?page=other_income" class="bg-gradient-to-r from-emerald-500 to-teal-600 text-white py-3 px-8 rounded-2xl font-bold hover:shadow-[0_0_20px_rgba(16,185,129,0.3)] transition-all transform hover:-translate-y-1">
                Manage Other Income
            </a>
            <a href="index.php?page=expenses" class="bg-gradient-to-r from-rose-500 to-red-600 text-white py-3 px-8 rounded-2xl font-bold hover:shadow-[0_0_20px_rgba(244,63,94,0.3)] transition-all transform hover:-translate-y-1">
                Manage Expenses
            </a>
            <?php if (is_admin()): ?>
            <a href="index.php?page=admin_dashboard" class="bg-white border border-slate-200 text-slate-700 py-3 px-8 rounded-2xl font-bold hover:bg-slate-50 hover:shadow-md transition-all transform hover:-translate-y-1">
                Back to Admin Dashboard
            </a>
            <?php endif; ?>
        </div>

    </div>
</div>

<style>
    /* Custom Scrollbar */
    .custom-scrollbar::-webkit-scrollbar { height: 8px; width: 8px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(203, 213, 225, 0.6); border-radius: 999px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(148, 163, 184, 0.8); }

    /* Animations */
    @keyframes fadeInDown {
        from { opacity: 0; transform: translateY(-15px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fadeInDown {
        animation: fadeInDown 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        opacity: 0;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab switching logic (V3 Styled)
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.tab-content');

        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                // Reset all buttons
                tabButtons.forEach(btn => {
                    btn.classList.remove('active-tab', 'bg-white', 'text-indigo-600', 'shadow-sm', 'border-slate-100');
                    btn.classList.add('hover:text-indigo-500', 'hover:bg-white/50');
                    btn.style.border = '1px solid transparent';
                });

                // Hide all content
                tabContents.forEach(content => {
                    content.classList.remove('block');
                    content.classList.add('hidden');
                });

                // Activate clicked button
                button.classList.add('active-tab', 'bg-white', 'text-indigo-600', 'shadow-sm');
                button.classList.remove('hover:text-indigo-500', 'hover:bg-white/50');
                button.style.border = '1px solid #f1f5f9';

                // Show targeted content
                const targetTab = button.dataset.tab;
                document.getElementById(targetTab).classList.remove('hidden');
                document.getElementById(targetTab).classList.add('block');

                // Re-render charts to fix canvas sizing bugs inside hidden divs
                if (targetTab === 'charts') {
                    renderCharts();
                }
            });
        });

        // Chart Data from PHP
        const dailyLabels = <?php echo json_encode($chart_labels_daily); ?>;
        const dailyNetWorthData = <?php echo json_encode($chart_net_worth_daily); ?>;
        const dailyRevenueData = <?php echo json_encode($chart_total_revenue_daily); ?>;
        const dailyExpensesData = <?php echo json_encode($chart_expenses_daily); ?>;

        const monthlyLabels = <?php echo json_encode($chart_labels_monthly); ?>;
        const monthlyNetWorthData = <?php echo json_encode($chart_net_worth_monthly); ?>;
        const monthlyRevenueData = <?php echo json_encode($chart_total_revenue_monthly); ?>;
        const monthlyExpensesData = <?php echo json_encode($chart_expenses_monthly); ?>;

        let chartsRendered = false;
        const chartInstances = [];

        // Function to render charts with V3 Aesthetics
        function renderCharts() {
            if (chartsRendered) return; // Prevent creating multiple instances
            chartsRendered = true;

            // Global Chart config for sleeker look
            Chart.defaults.font.family = "'Inter', 'Helvetica Neue', 'Helvetica', 'Arial', sans-serif";
            Chart.defaults.color = '#94a3b8'; // slate-400
            
            const gridOptions = {
                color: '#f1f5f9', // slate-100
                drawBorder: false,
            };

            // Daily Net Worth Chart (Line)
            chartInstances.push(new Chart(document.getElementById('dailyNetWorthChart'), {
                type: 'line',
                data: {
                    labels: dailyLabels,
                    datasets: [{
                        label: 'Net Worth',
                        data: dailyNetWorthData,
                        borderColor: '#10b981', // emerald-500
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 3,
                        tension: 0.4, // Smooth curves
                        fill: true,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#10b981',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { x: { grid: { display: false } }, y: { grid: gridOptions } }
                }
            }));

            // Monthly Net Worth Chart (Line)
            chartInstances.push(new Chart(document.getElementById('monthlyNetWorthChart'), {
                type: 'line',
                data: {
                    labels: monthlyLabels,
                    datasets: [{
                        label: 'Net Worth',
                        data: monthlyNetWorthData,
                        borderColor: '#8b5cf6', // violet-500
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#8b5cf6',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { x: { grid: { display: false } }, y: { grid: gridOptions } }
                }
            }));

            // Daily Revenue vs Expenses Chart (Bar)
            chartInstances.push(new Chart(document.getElementById('dailyRevenueExpensesChart'), {
                type: 'bar',
                data: {
                    labels: dailyLabels,
                    datasets: [
                        {
                            label: 'Revenue',
                            data: dailyRevenueData,
                            backgroundColor: '#4f46e5', // indigo-600
                            borderRadius: 6,
                        },
                        {
                            label: 'Expenses',
                            data: dailyExpensesData,
                            backgroundColor: '#f43f5e', // rose-500
                            borderRadius: 6,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 8 } }
                    },
                    scales: { x: { grid: { display: false } }, y: { grid: gridOptions } }
                }
            }));

            // Monthly Revenue vs Expenses Chart (Bar)
            chartInstances.push(new Chart(document.getElementById('monthlyRevenueExpensesChart'), {
                type: 'bar',
                data: {
                    labels: monthlyLabels,
                    datasets: [
                        {
                            label: 'Revenue',
                            data: monthlyRevenueData,
                            backgroundColor: '#0ea5e9', // sky-500
                            borderRadius: 6,
                        },
                        {
                            label: 'Expenses',
                            data: monthlyExpensesData,
                            backgroundColor: '#f59e0b', // amber-500
                            borderRadius: 6,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 8 } }
                    },
                    scales: { x: { grid: { display: false } }, y: { grid: gridOptions } }
                }
            }));
        }
    });
</script>

<?php include_template('footer'); ?>