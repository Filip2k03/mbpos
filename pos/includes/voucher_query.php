<?php
// Shared, side-effect-free filter normalization for voucher ledger reads and exports.

if (!function_exists('mbpos_voucher_valid_date')) {
    function mbpos_voucher_valid_date($value): string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return '';
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return '';
        }

        return $date->format('Y-m-d') === $value ? $value : '';
    }
}

if (!function_exists('mbpos_normalize_voucher_filters')) {
    function mbpos_normalize_voucher_filters(array $input, array $allowedStatuses): array
    {
        $allowedSearchColumns = ['voucher_code', 'sender_name', 'receiver_name', 'receiver_phone'];
        $searchColumn = $input['search_column'] ?? 'voucher_code';
        if (!is_string($searchColumn) || !in_array($searchColumn, $allowedSearchColumns, true)) {
            $searchColumn = 'voucher_code';
        }

        $searchTerm = is_string($input['search'] ?? null) ? trim($input['search']) : '';
        $searchTerm = function_exists('mb_substr')
            ? mb_substr($searchTerm, 0, 120, 'UTF-8')
            : substr($searchTerm, 0, 120);

        $status = is_string($input['status'] ?? null) ? trim($input['status']) : '';
        if (!in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        $normalizeId = static function ($value) {
            if ($value === 'All' || $value === '' || $value === null) {
                return 'All';
            }
            $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            return $validated === false ? 'All' : (int)$validated;
        };

        return [
            'start_date' => mbpos_voucher_valid_date($input['start_date'] ?? ''),
            'end_date' => mbpos_voucher_valid_date($input['end_date'] ?? ''),
            'origin_region_id' => $normalizeId($input['origin_region_id'] ?? 'All'),
            'destination_region_id' => $normalizeId($input['destination_region_id'] ?? 'All'),
            'status' => $status,
            'search' => $searchTerm,
            'search_column' => $searchColumn,
        ];
    }
}

if (!function_exists('mbpos_build_voucher_filter_sql')) {
    function mbpos_build_voucher_filter_sql(array $filters, int $userBranchId, bool $restrictToBranch): array
    {
        $where = [];
        $types = '';
        $values = [];

        if ($restrictToBranch && $userBranchId > 0) {
            $where[] = "(v.origin_branch_id = ? OR (v.destination_branch_id = ? AND v.status != 'Pending'))";
            $types .= 'ii';
            $values[] = $userBranchId;
            $values[] = $userBranchId;
        }

        if ($filters['start_date'] !== '') {
            $where[] = 'v.created_at >= ?';
            $types .= 's';
            $values[] = $filters['start_date'] . ' 00:00:00';
        }

        if ($filters['end_date'] !== '') {
            $endExclusive = (new DateTimeImmutable($filters['end_date']))->modify('+1 day');
            $where[] = 'v.created_at < ?';
            $types .= 's';
            $values[] = $endExclusive->format('Y-m-d') . ' 00:00:00';
        }

        if ($filters['origin_region_id'] !== 'All') {
            $where[] = 'v.region_id = ?';
            $types .= 'i';
            $values[] = $filters['origin_region_id'];
        }

        if ($filters['destination_region_id'] !== 'All') {
            $where[] = 'v.destination_region_id = ?';
            $types .= 'i';
            $values[] = $filters['destination_region_id'];
        }

        if ($filters['status'] !== '') {
            $where[] = 'v.status = ?';
            $types .= 's';
            $values[] = $filters['status'];
        }

        if ($filters['search'] !== '') {
            $column = $filters['search_column'];
            $escapedTerm = strtr($filters['search'], ['=' => '==', '%' => '=%', '_' => '=_']);
            $prefixSearch = in_array($column, ['voucher_code', 'receiver_phone'], true);
            $where[] = "v.$column LIKE ? ESCAPE '='";
            $types .= 's';
            $values[] = $prefixSearch ? $escapedTerm . '%' : '%' . $escapedTerm . '%';
        }

        return [
            'where_sql' => $where ? ' WHERE ' . implode(' AND ', $where) : '',
            'types' => $types,
            'values' => $values,
        ];
    }
}
