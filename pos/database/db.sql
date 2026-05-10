CREATE TABLE `regions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `region_code` VARCHAR(10) NOT NULL UNIQUE,
    `region_name` VARCHAR(255) NOT NULL,
    `prefix` VARCHAR(10) NOT NULL UNIQUE,
    `current_sequence` INT NOT NULL DEFAULT 0,
    `price_per_kg` DECIMAL(10, 2) NOT NULL DEFAULT 0.00
);

INSERT INTO `regions` (`id`, `region_code`, `region_name`, `prefix`, `current_sequence`, `price_per_kg`) VALUES
(1, 'MM', 'Myanmar', 'MM', 0, 0.00),
(2, 'CA', 'Canada', 'CA', 0, 0.00),
(3, 'TH', 'Thailand', 'TH', 0, 0.00),
(4, 'MY', 'Malaysia', 'MY', 0, 0.00),
(5, 'AU', 'Australia', 'AU', 0, 0.00),
(6, 'NZ', 'New Zealand', 'NZ', 0, 0.00),
(7, 'US', 'United States', 'US', 0, 0.00);

CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(191) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `user_type` ENUM('ADMIN', 'Myanmar', 'Malay', 'General', 'Developer', 'Customer') NOT NULL DEFAULT 'General',
    `phone` VARCHAR(50) UNIQUE,
    `region_id` INT,
    `branch_id` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`region_id`) REFERENCES `regions`(`id`) ON DELETE SET NULL
);

CREATE TABLE `branches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `branch_name` VARCHAR(255) NOT NULL,
    `region_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`region_id`) REFERENCES `regions`(`id`) ON DELETE CASCADE
);

INSERT INTO `branches` (`branch_name`, `region_id`) VALUES
('Yangon', 1),
('Mawlamyine', 1),
('Mandalay', 1),
('Naypyitaw', 1);

CREATE TABLE `vouchers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `voucher_code` VARCHAR(50) NOT NULL UNIQUE,
    `sender_name` VARCHAR(255) NOT NULL,
    `sender_phone` VARCHAR(50) NOT NULL,
    `sender_address` TEXT,
    `use_sender_address_for_checkout` BOOLEAN DEFAULT FALSE,
    `receiver_name` VARCHAR(255) NOT NULL,
    `receiver_phone` VARCHAR(50) NOT NULL,
    `receiver_address` TEXT NOT NULL,
    `payment_method` VARCHAR(100),
    `weight_kg` DECIMAL(10, 2) NOT NULL,
    `price_per_kg_at_voucher` DECIMAL(10, 2) NOT NULL,
    `delivery_charge` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10, 2) NOT NULL,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
    `delivery_type` VARCHAR(100),
    `notes` TEXT,
    `status` ENUM('Pending', 'In Transit', 'Delivered', 'Received', 'Cancelled', 'Returned', 'Maintenance') DEFAULT 'Pending',
    `region_id` INT NOT NULL,
    `destination_region_id` INT,
    `origin_branch_id` INT,
    `destination_branch_id` INT,
    `created_by_user_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`region_id`) REFERENCES `regions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`destination_region_id`) REFERENCES `regions`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`origin_branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`destination_branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE `voucher_breakdowns` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `voucher_id` INT NOT NULL,
    `item_type` VARCHAR(255) NOT NULL,
    `kg` DECIMAL(10, 2) NOT NULL,
    `price_per_kg` DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (`voucher_id`) REFERENCES `vouchers`(`id`) ON DELETE CASCADE
);

CREATE TABLE `expenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `description` TEXT NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
    `expense_date` DATE NOT NULL,
    `created_by_user_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE `other_income` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `description` VARCHAR(255) NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
    `income_date` DATE NOT NULL,
    `created_by_user_id` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

CREATE TABLE `maintenance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `is_active` BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE TABLE `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `message` VARCHAR(255) NOT NULL,
    `is_read` BOOLEAN NOT NULL DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

