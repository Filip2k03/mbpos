-- MBLOGISTICS POS V5: voucher ledger read-path indexes
--
-- IMPORTANT
-- 1. Take and verify a current database backup before running this migration.
-- 2. Run the preflight SHOW INDEX statement and do not add an index whose
--    ordered column list is already covered by an existing index.
-- 3. Apply during a low-write window. This file never deletes or rewrites
--    voucher records, but index creation can briefly consume CPU, I/O, and locks.
-- 4. This migration is intentionally separate from application deployment.

SHOW INDEX FROM vouchers;

-- Default ledger ordering and date-range pagination.
ALTER TABLE vouchers
    ADD INDEX idx_vouchers_created_id (created_at, id);

-- Status, route, and role-scoped filters while retaining ledger order.
ALTER TABLE vouchers
    ADD INDEX idx_vouchers_status_created_id (status, created_at, id),
    ADD INDEX idx_vouchers_origin_region_created_id (region_id, created_at, id),
    ADD INDEX idx_vouchers_destination_region_created_id (destination_region_id, created_at, id),
    ADD INDEX idx_vouchers_origin_branch_created_id (origin_branch_id, created_at, id),
    ADD INDEX idx_vouchers_destination_branch_status_created_id (destination_branch_id, status, created_at, id);

-- Receiver-phone searches are prefix searches in V5. Voucher codes already use
-- the table's unique voucher_code index. Name searches remain substring searches
-- and intentionally do not receive ineffective B-tree indexes.
ALTER TABLE vouchers
    ADD INDEX idx_vouchers_receiver_phone (receiver_phone);

ANALYZE TABLE vouchers;

SHOW INDEX FROM vouchers;

