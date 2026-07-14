-- Wishlist PHP Application Database Schema

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wishlist_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `url` TEXT NOT NULL,
    `image_url` TEXT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `estimated_price` DECIMAL(10,2) DEFAULT NULL,
    `is_bought` TINYINT(1) DEFAULT 0,
    `buyer_name` VARCHAR(100) DEFAULT NULL,
    `buyer_proof` TEXT DEFAULT NULL,
    `buyer_message` TEXT DEFAULT NULL,
    `message_public` TINYINT(1) DEFAULT 0,
    `bought_at` TIMESTAMP NULL DEFAULT NULL,
    `is_archived` TINYINT(1) DEFAULT 0,
    `sort_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
    `key` VARCHAR(50) PRIMARY KEY,
    `value` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default settings keys
INSERT IGNORE INTO `settings` (`key`, `value`) VALUES 
('shipping_address', ''),
('shipping_address_visible', '1'),
('shipping_address_expires_at', ''),
('general_notes', ''),
('currency', 'USD');

CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL,
    `created_at` INT NOT NULL,
    INDEX `idx_key_created` (`key`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL,
    `created_at` INT NOT NULL,
    INDEX `idx_key_created` (`key`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
