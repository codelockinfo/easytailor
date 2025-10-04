-- =====================================================
-- Tailoring Management System - Complete Database Setup
-- =====================================================
-- This file contains the complete database setup for the Tailoring Management System
-- including all tables, indexes, foreign keys, and initial data.
-- 
-- Usage: Import this file into your MySQL/MariaDB database to set up the complete system
-- 
-- Requirements:
-- - MySQL 5.7+ or MariaDB 10.3+
-- - UTF8MB4 support
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Database: tailoring_management
-- --------------------------------------------------------

CREATE DATABASE IF NOT EXISTS `tailoring_management` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `tailoring_management`;

-- --------------------------------------------------------
-- Table structure for table `companies` (Multi-tenant support)
-- --------------------------------------------------------

CREATE TABLE `companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(200) NOT NULL,
  `owner_name` varchar(100) NOT NULL,
  `business_email` varchar(100) NOT NULL,
  `business_phone` varchar(20) NOT NULL,
  `business_address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `tax_number` varchar(50) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'USD',
  `timezone` varchar(50) DEFAULT 'UTC',
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `subscription_plan` enum('free','basic','premium','enterprise') DEFAULT 'free',
  `subscription_expiry` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `business_email` (`business_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','staff','tailor','cashier') NOT NULL DEFAULT 'staff',
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `company_id` (`company_id`),
  KEY `idx_last_login` (`last_login`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `languages`
-- --------------------------------------------------------

CREATE TABLE `languages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(5) NOT NULL,
  `name` varchar(50) NOT NULL,
  `flag` varchar(10) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `subscription_packages`
-- --------------------------------------------------------

CREATE TABLE `subscription_packages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `duration_days` int(11) NOT NULL DEFAULT 30,
  `max_customers` int(11) DEFAULT NULL,
  `max_orders` int(11) DEFAULT NULL,
  `features` json DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `user_subscriptions`
-- --------------------------------------------------------

CREATE TABLE `user_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `package_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','expired','cancelled') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `package_id` (`package_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`package_id`) REFERENCES `subscription_packages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `coupons`
-- --------------------------------------------------------

CREATE TABLE `coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `type` enum('percentage','fixed') NOT NULL,
  `value` decimal(10,2) NOT NULL,
  `min_amount` decimal(10,2) DEFAULT NULL,
  `max_discount` decimal(10,2) DEFAULT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `used_count` int(11) NOT NULL DEFAULT 0,
  `valid_from` date NOT NULL,
  `valid_until` date NOT NULL,
  `status` enum('active','inactive','expired') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `coupon_history`
-- --------------------------------------------------------

CREATE TABLE `coupon_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `coupon_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subscription_id` int(11) DEFAULT NULL,
  `discount_amount` decimal(10,2) NOT NULL,
  `used_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `coupon_id` (`coupon_id`),
  KEY `user_id` (`user_id`),
  KEY `subscription_id` (`subscription_id`),
  FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`subscription_id`) REFERENCES `user_subscriptions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `customers`
-- --------------------------------------------------------

CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `customer_code` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_customer_code_per_company` (`company_id`, `customer_code`),
  KEY `company_id` (`company_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `cloth_types`
-- --------------------------------------------------------

CREATE TABLE `cloth_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `standard_rate` decimal(10,2) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `measurement_chart_image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `measurements`
-- --------------------------------------------------------

CREATE TABLE `measurements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `cloth_type_id` int(11) NOT NULL,
  `measurement_data` json NOT NULL,
  `notes` text DEFAULT NULL,
  `images` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `customer_id` (`customer_id`),
  KEY `cloth_type_id` (`cloth_type_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`cloth_type_id`) REFERENCES `cloth_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `orders`
-- --------------------------------------------------------

CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `cloth_type_id` int(11) NOT NULL,
  `measurement_id` int(11) DEFAULT NULL,
  `assigned_tailor_id` int(11) DEFAULT NULL,
  `order_date` date NOT NULL,
  `due_date` date NOT NULL,
  `delivery_date` date DEFAULT NULL,
  `status` enum('pending','in_progress','completed','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `total_amount` decimal(10,2) NOT NULL,
  `advance_amount` decimal(10,2) DEFAULT 0.00,
  `balance_amount` decimal(10,2) NOT NULL,
  `special_instructions` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_order_number_per_company` (`company_id`, `order_number`),
  KEY `company_id` (`company_id`),
  KEY `customer_id` (`customer_id`),
  KEY `cloth_type_id` (`cloth_type_id`),
  KEY `measurement_id` (`measurement_id`),
  KEY `assigned_tailor_id` (`assigned_tailor_id`),
  KEY `created_by` (`created_by`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`cloth_type_id`) REFERENCES `cloth_types` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`measurement_id`) REFERENCES `measurements` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`assigned_tailor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `invoices`
-- --------------------------------------------------------

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `order_id` int(11) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `paid_amount` decimal(10,2) DEFAULT 0.00,
  `balance_amount` decimal(10,2) NOT NULL,
  `payment_status` enum('paid','partial','due') NOT NULL DEFAULT 'due',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `company_id` (`company_id`),
  KEY `order_id` (`order_id`),
  KEY `created_by` (`created_by`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `payments`
-- --------------------------------------------------------

CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','bank_transfer','card','cheque') NOT NULL,
  `payment_date` date NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `created_by` (`created_by`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `expenses`
-- --------------------------------------------------------

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `expense_date` date NOT NULL,
  `payment_method` enum('cash','bank_transfer','card','cheque') NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `receipt_image` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `created_by` (`created_by`),
  KEY `expense_date` (`expense_date`),
  KEY `category` (`category`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `contacts`
-- --------------------------------------------------------

CREATE TABLE `contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `company` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `company_settings`
-- --------------------------------------------------------

CREATE TABLE `company_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `business_name` varchar(200) NOT NULL,
  `business_logo` varchar(255) DEFAULT NULL,
  `business_address` text DEFAULT NULL,
  `business_phone` varchar(20) DEFAULT NULL,
  `business_email` varchar(100) DEFAULT NULL,
  `business_website` varchar(200) DEFAULT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `default_language` varchar(5) DEFAULT 'en',
  `timezone` varchar(50) DEFAULT 'UTC',
  `date_format` varchar(20) DEFAULT 'Y-m-d',
  `invoice_prefix` varchar(10) DEFAULT 'INV',
  `order_prefix` varchar(10) DEFAULT 'ORD',
  `customer_prefix` varchar(10) DEFAULT 'CUST',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `sessions`
-- --------------------------------------------------------

CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `last_activity` (`last_activity`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Insert default data
-- --------------------------------------------------------

-- Insert default company
INSERT INTO `companies` (`company_name`, `owner_name`, `business_email`, `business_phone`, `business_address`, `city`, `state`, `country`, `currency`, `timezone`, `status`, `subscription_plan`) VALUES
('Tailoring Management System', 'System Administrator', 'admin@tailoring.com', '+1 (555) 123-4567', '123 Business Street, City, State 12345', 'City', 'State', 'Country', 'USD', 'UTC', 'active', 'free');

-- Get the company ID for foreign key references
SET @company_id = LAST_INSERT_ID();

-- Insert default admin user (password: admin123)
INSERT INTO `users` (`company_id`, `username`, `email`, `password`, `full_name`, `role`, `status`) VALUES
(@company_id, 'admin', 'admin@tailoring.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', 'active');

-- Insert default languages
INSERT INTO `languages` (`code`, `name`, `flag`, `is_default`, `status`) VALUES
('en', 'English', '🇺🇸', 1, 'active'),
('es', 'Spanish', '🇪🇸', 0, 'active'),
('fr', 'French', '🇫🇷', 0, 'active'),
('hi', 'Hindi', '🇮🇳', 0, 'active');

-- Insert default subscription packages
INSERT INTO `subscription_packages` (`name`, `description`, `price`, `duration_days`, `max_customers`, `max_orders`, `features`) VALUES
('Free', 'Basic features for small businesses', 0.00, 30, 50, 100, '["basic_customers", "basic_orders", "basic_reports"]'),
('Basic', 'Standard features for growing businesses', 29.99, 30, 200, 500, '["advanced_customers", "advanced_orders", "advanced_reports", "email_support"]'),
('Premium', 'Advanced features for established businesses', 59.99, 30, 500, 1000, '["premium_customers", "premium_orders", "premium_reports", "priority_support", "custom_branding"]'),
('Enterprise', 'Full features for large businesses', 99.99, 30, -1, -1, '["unlimited_customers", "unlimited_orders", "unlimited_reports", "dedicated_support", "custom_branding", "api_access"]');

-- Insert default cloth types with measurement chart images
INSERT INTO `cloth_types` (`company_id`, `name`, `description`, `standard_rate`, `category`, `measurement_chart_image`, `status`) VALUES
(@company_id, 'Shirt', 'Men\'s formal and casual shirts', 25.00, 'Men\'s Wear', 'uploads/measurement-charts/shirt.svg', 'active'),
(@company_id, 'Pants', 'Men\'s formal and casual pants', 30.00, 'Men\'s Wear', 'uploads/measurement-charts/pants.svg', 'active'),
(@company_id, 'Suit', 'Men\'s formal suit', 150.00, 'Men\'s Wear', 'uploads/measurement-charts/suit.svg', 'active'),
(@company_id, 'Lehenga', 'Traditional women\'s lehenga', 200.00, 'Women\'s Wear', 'uploads/measurement-charts/lehenga.svg', 'active'),
(@company_id, 'Saree', 'Traditional women\'s saree', 80.00, 'Women\'s Wear', 'uploads/measurement-charts/saree.svg', 'active'),
(@company_id, 'Kurta', 'Traditional men\'s kurta', 40.00, 'Men\'s Wear', 'uploads/measurement-charts/kurta.svg', 'active'),
(@company_id, 'Dress', 'Women\'s dresses', 60.00, 'Women\'s Wear', 'uploads/measurement-charts/dress.svg', 'active'),
(@company_id, 'Blouse', 'Women\'s blouse', 35.00, 'Women\'s Wear', 'uploads/measurement-charts/blouse.svg', 'active');

-- Insert default company settings
INSERT INTO `company_settings` (`business_name`, `business_address`, `business_phone`, `business_email`, `currency`, `tax_rate`, `default_language`) VALUES
('Tailoring Management System', '123 Business Street, City, State 12345', '+1 (555) 123-4567', 'info@tailoring.com', 'USD', 8.50, 'en');

-- --------------------------------------------------------
-- Create indexes for better performance
-- --------------------------------------------------------

-- Additional indexes for better query performance
CREATE INDEX `idx_customers_phone` ON `customers` (`phone`);
CREATE INDEX `idx_customers_email` ON `customers` (`email`);
CREATE INDEX `idx_orders_status` ON `orders` (`status`);
CREATE INDEX `idx_orders_due_date` ON `orders` (`due_date`);
CREATE INDEX `idx_invoices_payment_status` ON `invoices` (`payment_status`);
CREATE INDEX `idx_payments_payment_date` ON `payments` (`payment_date`);
CREATE INDEX `idx_expenses_expense_date` ON `expenses` (`expense_date`);

-- --------------------------------------------------------
-- Create views for common queries
-- --------------------------------------------------------

-- View for customer summary
CREATE VIEW `customer_summary` AS
SELECT 
    c.id,
    c.company_id,
    c.customer_code,
    CONCAT(c.first_name, ' ', c.last_name) as full_name,
    c.email,
    c.phone,
    c.status,
    COUNT(o.id) as total_orders,
    SUM(CASE WHEN o.status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
    SUM(CASE WHEN o.status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
    c.created_at
FROM customers c
LEFT JOIN orders o ON c.id = o.customer_id
GROUP BY c.id;

-- View for order summary
CREATE VIEW `order_summary` AS
SELECT 
    o.id,
    o.company_id,
    o.order_number,
    o.customer_id,
    CONCAT(c.first_name, ' ', c.last_name) as customer_name,
    c.phone as customer_phone,
    ct.name as cloth_type,
    o.order_date,
    o.due_date,
    o.delivery_date,
    o.status,
    o.total_amount,
    o.advance_amount,
    o.balance_amount,
    u.full_name as assigned_tailor,
    o.created_at
FROM orders o
JOIN customers c ON o.customer_id = c.id
JOIN cloth_types ct ON o.cloth_type_id = ct.id
LEFT JOIN users u ON o.assigned_tailor_id = u.id;

-- View for invoice summary
CREATE VIEW `invoice_summary` AS
SELECT 
    i.id,
    i.company_id,
    i.invoice_number,
    i.order_id,
    o.order_number,
    CONCAT(c.first_name, ' ', c.last_name) as customer_name,
    i.invoice_date,
    i.due_date,
    i.total_amount,
    i.paid_amount,
    i.balance_amount,
    i.payment_status,
    i.created_at
FROM invoices i
JOIN orders o ON i.order_id = o.id
JOIN customers c ON o.customer_id = c.id;

COMMIT;

-- =====================================================
-- Setup Complete!
-- =====================================================
-- 
-- Default Login Credentials:
-- Username: admin
-- Password: admin123
-- 
-- IMPORTANT: Change the default password immediately after first login!
-- 
-- Next Steps:
-- 1. Update database configuration in config/database.php
-- 2. Update application URL in config/config.php
-- 3. Set proper file permissions for uploads directory
-- 4. Configure your web server (Apache/Nginx)
-- 5. Access the application and change default password
-- 
-- =====================================================
