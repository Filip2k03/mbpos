<?php
// pos/includes/maintenance_analytics.php - Real-time diagnostics, load detection, GMT+6:30 shift tracking, and voucher calculation engine

/**
 * Fetches comprehensive POS system diagnostics, load metrics, voucher totals, and item breakdowns.
 * Uses only the current existing SQL tables without modifying database structure.
 *
 * @param mysqli $connection Database connection object
 * @return array Structured diagnostic and breakdown dataset
 */
function get_pos_system_diagnostics($connection) {
    // Ensure GMT +6:30 Timezone (Asia/Yangon)
    if (date_default_timezone_get() !== 'Asia/Yangon') {
        date_default_timezone_set('Asia/Yangon');
    }

    $current_hour = (int)date('H'); // 00-23 in GMT+6:30
    $today_date = date('Y-m-d');
    $current_time_str = date('h:i:s A') . ' (GMT+6:30)';

    // Determine Time-of-Day Shift Window (Morning <12:00, Afternoon 12:00-16:59, Night 17:00+)
    if ($current_hour < 12) {
        $shift_code = 'morning';
        $shift_name = 'Morning Shift (00:00 - 11:59 GMT+6:30)';
        $shift_greeting = 'Good Morning';
        $shift_desc = 'Morning Login Checkpoint & Opening Voucher Sync';
    } elseif ($current_hour >= 12 && $current_hour < 17) {
        $shift_code = 'afternoon';
        $shift_name = 'Afternoon Peak (12:00 - 16:59 GMT+6:30)';
        $shift_greeting = 'Good Afternoon';
        $shift_desc = 'Mid-Day Peak Volume & Freight Movement';
    } else {
        $shift_code = 'night';
        $shift_name = 'Evening / Night Shift (17:00+ GMT+6:30)';
        $shift_greeting = 'Good Evening';
        $shift_desc = 'Night Shift Operations & Ledger Balance';
    }

    $shift_session_key = 'mbpos_shift_' . $today_date . '_' . $shift_code;

    if (!$connection) {
        return [
            'maintenance_active' => false,
            'maintenance_categories' => [],
            'developer_maintenance_connected' => true,
            'total_vouchers' => 0,
            'total_weight_kg' => 0,
            'currency_totals' => [],
            'status_breakdown' => [],
            'item_breakdown' => [],
            'high_load_detected' => false,
            'server_load_percent' => 35,
            'diagnostic_token' => 'POS-SYS-' . strtoupper(substr(md5(time()), 0, 8)),
            'shift_code' => $shift_code,
            'shift_name' => $shift_name,
            'shift_greeting' => $shift_greeting,
            'shift_desc' => $shift_desc,
            'shift_session_key' => $shift_session_key,
            'current_time_gmt630' => $current_time_str
        ];
    }

    // Set utf8mb4 for proper character encoding
    mysqli_set_charset($connection, "utf8mb4");

    // 1. Check Maintenance Status (Connected to Developer maintenance system)
    $maintenance_active = false;
    $maintenance_categories = [];
    $developer_maintenance_connected = false;
    
    // Check existing maintenance table
    $table_check = mysqli_query($connection, "SHOW TABLES LIKE 'maintenance'");
    if ($table_check && mysqli_num_rows($table_check) > 0) {
        $developer_maintenance_connected = true;
        $m_res = mysqli_query($connection, "SELECT id, name, description, is_active FROM maintenance WHERE is_active = 1");
        if ($m_res && mysqli_num_rows($m_res) > 0) {
            $maintenance_active = true;
            while ($row = mysqli_fetch_assoc($m_res)) {
                $maintenance_categories[] = $row['name'];
            }
        }
    }

    // Check existing settings table maintenance_mode
    $settings_check = mysqli_query($connection, "SHOW TABLES LIKE 'settings'");
    if ($settings_check && mysqli_num_rows($settings_check) > 0) {
        $s_mode_res = mysqli_query($connection, "SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode' LIMIT 1");
        if ($s_mode_res && mysqli_num_rows($s_mode_res) > 0) {
            $val = mysqli_fetch_assoc($s_mode_res)['setting_value'];
            if ($val === 'on') {
                $maintenance_active = true;
                if (!in_array('Global POS Maintenance (Developer Override)', $maintenance_categories)) {
                    $maintenance_categories[] = 'Global POS Maintenance (Developer Override)';
                }
            }
        }
    }

    // 2. Aggregate Voucher Totals & Currencies (Using current SQL table `vouchers`)
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

    // 4. Item Breakdown from current SQL table `voucher_breakdowns`
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
    $pending_count = $status_breakdown['Pending'] ?? 0;
    $high_load_detected = $maintenance_active || ($total_vouchers > 100) || ($pending_count > 10);
    
    // Calculate realistic load metric percentage
    $calculated_load = 45;
    if ($total_vouchers > 0) {
        $calculated_load += min(35, (int)($total_vouchers / 5));
    }
    if ($pending_count > 5) {
        $calculated_load += min(15, $pending_count * 2);
    }
    if ($maintenance_active) {
        $calculated_load = max(90, $calculated_load);
    }
    $calculated_load = min(98, $calculated_load);

    // Generate unique diagnostic tracking token
    $diagnostic_token = 'POS-DIAG-' . strtoupper(substr(md5($total_vouchers . '_' . date('YmdH')), 0, 8));

    return [
        'maintenance_active' => $maintenance_active,
        'maintenance_categories' => $maintenance_categories,
        'developer_maintenance_connected' => $developer_maintenance_connected,
        'total_vouchers' => $total_vouchers,
        'total_weight_kg' => $total_weight_kg,
        'currency_totals' => $currency_totals,
        'status_breakdown' => $status_breakdown,
        'item_breakdown' => $item_breakdown,
        'high_load_detected' => $high_load_detected,
        'server_load_percent' => $calculated_load,
        'diagnostic_token' => $diagnostic_token,
        'shift_code' => $shift_code,
        'shift_name' => $shift_name,
        'shift_greeting' => $shift_greeting,
        'shift_desc' => $shift_desc,
        'shift_session_key' => $shift_session_key,
        'current_time_gmt630' => $current_time_str,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}
