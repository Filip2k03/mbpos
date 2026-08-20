-- =========================================================================
-- MBPOS Database Update Script: Maintenance & Diagnostics Schema Update
-- Safe to run on MySQL / MariaDB (Supports existing & new databases)
-- =========================================================================

-- 1. Ensure `maintenance` table exists with proper structure
CREATE TABLE IF NOT EXISTS `maintenance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `is_active` BOOLEAN NOT NULL DEFAULT FALSE
);

-- Add description column if missing from older maintenance table
SET @dbname = DATABASE();
SET @tablename = "maintenance";
SET @columnname = "description";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE `maintenance` ADD COLUMN `description` TEXT NULL AFTER `name`;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 2. Ensure `settings` table exists for global maintenance override
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default maintenance_mode setting if not present
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES ('maintenance_mode', 'off');

-- 3. Starter categories for developer maintenance if empty
INSERT IGNORE INTO `maintenance` (`id`, `name`, `description`, `is_active`) VALUES
(1, 'Database Indexing & Ledger Sync', 'Optimizing SQL query speed and multi-currency ledger caches.', 0),
(2, 'Regional Route Sync & Live Tracking', 'Updating international shipment routes and sequence counters.', 0),
(3, 'Multi-Currency Exchange Rate Sync', 'Synchronizing real-time currency conversions across regions.', 0);
