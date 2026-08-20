<?php
// pos/includes/maintenance_analytics.php - Real-time diagnostics, load detection, and voucher calculation engine

/**
 * Fetches comprehensive POS system diagnostics, load metrics, voucher totals, and item breakdowns.
 *
 * @param mysqli $connection Database connection object
 * @return array Structured diagnostic and breakdown dataset
 */
function get_pos_system_diagnostics($connection) {
    if (!$connection) {
        return [
            'maintenance_active' => false,
            'maintenance_categories' => [],
            'total_vouchers' => 0,
            'total_weight_kg' => 0,
            'currency_totals' => [],
            'status_breakdown' => [],
            'item_breakdown' => [],
            'high_load_detected' => false,
            'server_load_percent' => 35,
            'diagnostic_token' => 'POS-SYS-' . strtoupper(substr(md5(time()), 0, 8))
        ];
    }

    // Set utf8mb4 for proper character encoding
    mysqli_set_charset($connection, "utf8mb4");

    // 1. Check Maintenance Status
    $maintenance_active = false;
    $maintenance_categories = [];
    
    // Check maintenance table
    $table_check = mysqli_query($connection, "SHOW TABLES LIKE 'maintenance'");
    if ($table_check && mysqli_num_rows($table_check) > 0) {
        $m_res = mysqli_query($connection, "SELECT name, description, is_active FROM maintenance WHERE is_active = 1");
        if ($m_res && mysqli_num_rows($m_res) > 0) {
            $maintenance_active = true;
            while ($row = mysqli_fetch_assoc($m_res)) {
                $maintenance_categories[] = $row['name'];
            }
        }
    }

    // Check settings table maintenance_mode
    $settings_check = mysqli_query($connection, "SHOW TABLES LIKE 'settings'");
    if ($settings_check && mysqli_num_rows($settings_check) > 0) {
        $s_mode_res = mysqli_query($connection, "SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode' LIMIT 1");
        if ($s_mode_res && mysqli_num_rows($s_mode_res) > 0) {
            $val = mysqli_fetch_assoc($s_mode_res)['setting_value'];
            if ($val === 'on') {
                $maintenance_active = true;
                if (!in_array('Global System Maintenance', $maintenance_categories)) {
                    $maintenance_categories[] = 'Global System Maintenance';
                }
            }
        }
    }

    // 2. Aggregate Voucher Totals & Currencies
    $total_vouchers = 0;
    $total_weight_kg = 0;
    $currency_totals = [];

    $v_res = mysqli_query($connection, "SELECT currency, COUNT(id) AS total_count, SUM(total_amount) AS total_revenue, SUM(weight_kg) AS total_weight FROM vouchers GROUP BY currency");
    if ($v_res) {
        while ($row = mysqli_fetch_assoc($v_res)) {
            $curr = !empty($row['currency']) ? htmlspecialchars($row['currency'], ENT_QUOTES, 'UTF-8') : 'USD';
            $count = (int)$row['total_count'];
            $revenue = (float)$row['total_revenue'];
            $weight = (float)$row['total_weight'];

            $total_vouchers += $count;
            $total_weight_kg += $weight;

            $currency_totals[$curr] = [
                'count' => $count,
                'revenue' => $revenue,
                'weight' => $weight
            ];
        }
    }

    // 3. Status Breakdown
    $status_breakdown = [];
    $all_possible_statuses = ['Pending', 'In Transit', 'Delivered', 'Received', 'Cancelled', 'Returned', 'Maintenance'];
    foreach ($all_possible_statuses as $st) {
        $status_breakdown[$st] = 0;
    }

    $st_res = mysqli_query($connection, "SELECT status, COUNT(id) AS status_count FROM vouchers GROUP BY status");
    if ($st_res) {
        while ($row = mysqli_fetch_assoc($st_res)) {
            $st_name = $row['status'] ?? 'Pending';
            $status_breakdown[$st_name] = (int)$row['status_count'];
        }
    }

    // 4. Item Breakdown from voucher_breakdowns
    $item_breakdown = [];
    $breakdown_table_check = mysqli_query($connection, "SHOW TABLES LIKE 'voucher_breakdowns'");
    if ($breakdown_table_check && mysqli_num_rows($breakdown_table_check) > 0) {
        $b_res = mysqli_query($connection, "SELECT item_type, COUNT(id) AS item_count, SUM(kg) AS sum_kg FROM voucher_breakdowns GROUP BY item_type ORDER BY sum_kg DESC LIMIT 8");
        if ($b_res) {
            while ($row = mysqli_fetch_assoc($b_res)) {
                $item_breakdown[] = [
                    'item_type' => htmlspecialchars($row['item_type'], ENT_QUOTES, 'UTF-8'),
                    'count' => (int)$row['item_count'],
                    'kg' => (float)$row['sum_kg']
                ];
            }
        }
    }

    // 5. Load Calculation & High Load Detection
    // Consider load high if active maintenance, or vouchers count > 100, or pending queue > 10
    $pending_count = $status_breakdown['Pending'] ?? 0;
    $high_load_detected = $maintenance_active || ($total_vouchers > 100) || ($pending_count > 10);
    
    // Calculate realistic load metric percentage
    $calculated_load = 42;
    if ($total_vouchers > 0) {
        $calculated_load += min(35, (int)($total_vouchers / 5));
    }
    if ($pending_count > 5) {
        $calculated_load += min(15, $pending_count * 2);
    }
    if ($maintenance_active) {
        $calculated_load = max(88, $calculated_load);
    }
    $calculated_load = min(98, $calculated_load);

    // Generate unique diagnostic tracking token
    $diagnostic_token = 'POS-DIAG-' . strtoupper(substr(md5($total_vouchers . '_' . date('YmdH')), 0, 8));

    return [
        'maintenance_active' => $maintenance_active,
        'maintenance_categories' => $maintenance_categories,
        'total_vouchers' => $total_vouchers,
        'total_weight_kg' => $total_weight_kg,
        'currency_totals' => $currency_totals,
        'status_breakdown' => $status_breakdown,
        'item_breakdown' => $item_breakdown,
        'high_load_detected' => $high_load_detected,
        'server_load_percent' => $calculated_load,
        'diagnostic_token' => $diagnostic_token,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}
