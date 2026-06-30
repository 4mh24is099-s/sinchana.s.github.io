-- Create database if not exists
CREATE DATABASE IF NOT EXISTS `stock_portfolio` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `stock_portfolio`;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Stocks Table
CREATE TABLE IF NOT EXISTS `stocks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `symbol` VARCHAR(10) NOT NULL,
    `company_name` VARCHAR(100) NOT NULL,
    `quantity` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `buy_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `current_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `purchase_date` DATE NOT NULL,
    `logo_path` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_user_stocks` (`user_id`),
    INDEX `idx_symbol` (`symbol`)
) ENGINE=InnoDB;

-- 3. Watchlist Table
CREATE TABLE IF NOT EXISTS `watchlist` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `symbol` VARCHAR(10) NOT NULL,
    `company_name` VARCHAR(100) NOT NULL,
    `target_price` DECIMAL(12,2) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user_watchlist` (`user_id`, `symbol`)
) ENGINE=InnoDB;

-- 4. Transactions Table
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `symbol` VARCHAR(10) NOT NULL,
    `transaction_type` ENUM('BUY', 'SELL') NOT NULL,
    `quantity` DECIMAL(12,4) NOT NULL,
    `price` DECIMAL(12,2) NOT NULL,
    `transaction_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_user_transactions` (`user_id`)
) ENGINE=InnoDB;

-- Seed Data (Password for demo is: demo123)
-- Hash generated using password_hash('demo123', PASSWORD_BCRYPT)
INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `created_at`) 
VALUES (1, 'demo', 'demo@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Seed Stocks for Demo User
INSERT INTO `stocks` (`user_id`, `symbol`, `company_name`, `quantity`, `buy_price`, `current_price`, `purchase_date`, `logo_path`, `notes`) VALUES
(1, 'AAPL', 'Apple Inc.', 15.0000, 150.00, 175.50, '2026-01-10', NULL, 'Tech giant, steady growth.'),
(1, 'MSFT', 'Microsoft Corporation', 10.0000, 280.00, 340.20, '2026-02-15', NULL, 'Cloud and AI growth play.'),
(1, 'TSLA', 'Tesla, Inc.', 8.0000, 220.00, 185.40, '2026-03-05', NULL, 'High volatility EV play.'),
(1, 'GOOGL', 'Alphabet Inc.', 12.0000, 110.00, 135.80, '2026-04-12', NULL, 'Leader in search and online ads.')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Seed Watchlist for Demo User
INSERT INTO `watchlist` (`user_id`, `symbol`, `company_name`, `target_price`, `notes`) VALUES
(1, 'NVDA', 'NVIDIA Corporation', 450.00, 'AI chip boom, wait for pullback.'),
(1, 'AMZN', 'Amazon.com, Inc.', 125.00, 'E-commerce and AWS recovery.')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Seed Transactions for Demo User
INSERT INTO `transactions` (`user_id`, `symbol`, `transaction_type`, `quantity`, `price`, `transaction_date`) VALUES
(1, 'AAPL', 'BUY', 15.0000, 150.00, '2026-01-10 10:00:00'),
(1, 'MSFT', 'BUY', 10.0000, 280.00, '2026-02-15 11:30:00'),
(1, 'TSLA', 'BUY', 8.0000, 220.00, '2026-03-05 14:15:00'),
(1, 'GOOGL', 'BUY', 12.0000, 110.00, '2026-04-12 09:45:00')
ON DUPLICATE KEY UPDATE `id`=`id`;
