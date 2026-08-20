<?php
// pos/includes/maintenance_analytics.php - Real-time diagnostics, load detection, GMT+6:30 shift tracking, and voucher calculation engine

/**
 * Fetches comprehensive POS system diagnostics, load metrics, voucher totals, and item breakdowns.
 * Completely resilient and safe against missing optional columns/tables.
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

    // Fallback baseline structure
    $result_data = [
        'maintenance_active' => false,
        'maintenance_categories' => [],
        'developer_maintenance_connected' => true,
        'total_vouchers' => 0,
        'total_weight_kg' => 0,
        'currency_totals' => [],
        'status_breakdown' => [
            'Pending' => 0,
            'In Transit' => 0,
            'Delivered' => 0,
            'Received' => 0,
            'Cancelled' => 0,
            'Returned' => 0,
            'Maintenance' => 0
        ],
        'item_breakdown' => [],
        'high_load_detected' => false,
        'server_load_percent' => 35,
        'diagnostic_token' => 'POS-SYS-' . strtoupper(substr(md5(time()), 0, 8)),
        'shift_code' => $shift_code,
        'shift_name' => $shift_name,
        'shift_greeting' => $shift_greeting,
        'shift_desc' => $shift_desc,
        'shift_session_key' => $shift_session_key,
        'current_time_gmt630' => $current_time_str,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    if (!$connection) {
        return $result_data;
    }

    try {
        // Set utf8mb4 for proper character encoding
        @mysqli_set_charset($connection, "utf8mb4");

        // 1. Check Maintenance Status (Connected to Developer maintenance system)
        $table_check = @mysqli_query($connection, "SHOW TABLES LIKE 'maintenance'");
        if ($table_check && mysqli_num_rows($table_check) > 0) {
            $result_data['developer_maintenance_connected'] = true;
            // Use SELECT * or SELECT name to safely handle tables with or without optional columns
            $m_res = @mysqli_query($connection, "SELECT * FROM maintenance WHERE is_active = 1");
            if ($m_res && mysqli_num_rows($m_res) > 0) {
                $result_data['maintenance_active'] = true;
                while ($row = mysqli_fetch_assoc($m_res)) {
                    if (!empty($row['name'])) {
                        $result_data['maintenance_categories'][] = $row['name'];
                    }
                }
            }
        }

        // Check settings table maintenance_mode
        $settings_check = @mysqli_query($connection, "SHOW TABLES LIKE 'settings'");
        if ($settings_check && mysqli_num_rows($settings_check) > 0) {
            $s_mode_res = @mysqli_query($connection, "SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode' LIMIT 1");
            if ($s_mode_res && mysqli_num_rows($s_mode_res) > 0) {
                $val = mysqli_fetch_assoc($s_mode_res)['setting_value'];
                if ($val === 'on') {
                    $result_data['maintenance_active'] = true;
                    if (!in_array('Global POS Maintenance (Developer Override)', $result_data['maintenance_categories'])) {
                        $result_data['maintenance_categories'][] = 'Global POS Maintenance (Developer Override)';
                    }
                }
            }
        }

        // 2. Aggregate Voucher Totals & Currencies
        $v_res = @mysqli_query($connection, "SELECT currency, COUNT(id) AS total_count, SUM(total_amount) AS total_revenue, SUM(weight_kg) AS total_weight FROM vouchers GROUP BY currency");
        if ($v_res) {
            while ($row = mysqli_fetch_assoc($v_res)) {
                $curr = !empty($row['currency']) ? htmlspecialchars($row['currency'], ENT_QUOTES, 'UTF-8') : 'USD';
                $count = (int)$row['total_count'];
                $revenue = (float)$row['total_revenue'];
                $weight = (float)$row['total_weight'];

                $result_data['total_vouchers'] += $count;
                $result_data['total_weight_kg'] += $weight;

                $result_data['currency_totals'][$curr] = [
                    'count' => $count,
                    'revenue' => $revenue,
                    'weight' => $weight
                ];
            }
        }

        // 3. Status Breakdown
        $st_res = @mysqli_query($connection, "SELECT status, COUNT(id) AS status_count FROM vouchers GROUP BY status");
        if ($st_res) {
            while ($row = mysqli_fetch_assoc($st_res)) {
                $st_name = $row['status'] ?? 'Pending';
                $result_data['status_breakdown'][$st_name] = (int)$row['status_count'];
            }
        }

        // 4. Item Breakdown from voucher_breakdowns
        $breakdown_table_check = @mysqli_query($connection, "SHOW TABLES LIKE 'voucher_breakdowns'");
        if ($breakdown_table_check && mysqli_num_rows($breakdown_table_check) > 0) {
            $b_res = @mysqli_query($connection, "SELECT item_type, COUNT(id) AS item_count, SUM(kg) AS sum_kg FROM voucher_breakdowns GROUP BY item_type ORDER BY sum_kg DESC LIMIT 8");
            if ($b_res) {
                while ($row = mysqli_fetch_assoc($b_res)) {
                    $result_data['item_breakdown'][] = [
                        'item_type' => htmlspecialchars($row['item_type'], ENT_QUOTES, 'UTF-8'),
                        'count' => (int)$row['item_count'],
                        'kg' => (float)$row['sum_kg']
                    ];
                }
            }
        }

        // 5. Load Calculation & High Load Detection
        $pending_count = $result_data['status_breakdown']['Pending'] ?? 0;
        $total_v = $result_data['total_vouchers'];
        $result_data['high_load_detected'] = $result_data['maintenance_active'] || ($total_v > 100) || ($pending_count > 10);
        
        $calc_load = 45;
        if ($total_v > 0) {
            $calc_load += min(35, (int)($total_v / 5));
        }
        if ($pending_count > 5) {
            $calc_load += min(15, $pending_count * 2);
        }
        if ($result_data['maintenance_active']) {
            $calc_load = max(90, $calc_load);
        }
        $result_data['server_load_percent'] = min(98, $calc_load);
        $result_data['diagnostic_token'] = 'POS-DIAG-' . strtoupper(substr(md5($total_v . '_' . date('YmdH')), 0, 8));

    } catch (Throwable $e) {
        // Fail-safe: log error quietly and return safe baseline data
        error_log("MBPOS Diagnostics Notice: " . $e->getMessage());
    }

    return $result_data;
}
