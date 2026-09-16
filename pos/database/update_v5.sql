-- MBLOGISTICS POS V5 schema upgrade
-- Safe, additive migration. No portal-specific columns exist in the supplied
-- schema, so this migration intentionally does not drop any columns.

CREATE TABLE IF NOT EXISTS currencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS item_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS delivery_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS shipping_routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    origin_country VARCHAR(100) NOT NULL,
    destination_country VARCHAR(100) NOT NULL,
    schedule VARCHAR(255) DEFAULT NULL,
    working_days VARCHAR(255) DEFAULT NULL,
    rates LONGTEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_shipping_routes_active (is_active),
    INDEX idx_shipping_routes_lane (origin_country, destination_country)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voucher_id INT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_stock_voucher (voucher_id),
    CONSTRAINT fk_stock_voucher FOREIGN KEY (voucher_id) REFERENCES vouchers(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @has_sender_customer_id := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vouchers' AND COLUMN_NAME = 'sender_customer_id'
);
SET @add_sender_customer_id := IF(@has_sender_customer_id = 0,
    'ALTER TABLE vouchers ADD COLUMN sender_customer_id INT NULL AFTER receiver_phone',
    'SELECT 1');
PREPARE stmt FROM @add_sender_customer_id;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_receiver_customer_id := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vouchers' AND COLUMN_NAME = 'receiver_customer_id'
);
SET @add_receiver_customer_id := IF(@has_receiver_customer_id = 0,
    'ALTER TABLE vouchers ADD COLUMN receiver_customer_id INT NULL AFTER sender_customer_id',
    'SELECT 1');
PREPARE stmt FROM @add_receiver_customer_id;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- These references are intentionally nullable so existing vouchers remain valid.
-- Add foreign keys only after production data has been audited for orphan IDs.
