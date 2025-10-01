-- Multi-Tenant Support for Tailoring Management System
-- Adds company/shop management for multiple tailor businesses

-- Create companies table to store each tailor shop
CREATE TABLE IF NOT EXISTS `companies` (
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

-- Add company_id to users table
ALTER TABLE `users` 
ADD COLUMN `company_id` int(11) DEFAULT NULL AFTER `id`,
ADD KEY `company_id` (`company_id`),
ADD FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

-- Add company_id to customers table
ALTER TABLE `customers` 
ADD COLUMN `company_id` int(11) NOT NULL AFTER `id`,
ADD KEY `company_id` (`company_id`),
ADD FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

-- Add company_id to cloth_types table
ALTER TABLE `cloth_types` 
ADD COLUMN `company_id` int(11) DEFAULT NULL AFTER `id`,
ADD KEY `company_id` (`company_id`),
ADD FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

-- Add company_id to orders table
ALTER TABLE `orders` 
ADD COLUMN `company_id` int(11) NOT NULL AFTER `id`,
ADD KEY `company_id` (`company_id`),
ADD FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

-- Add company_id to measurements table
ALTER TABLE `measurements` 
ADD COLUMN `company_id` int(11) NOT NULL AFTER `id`,
ADD KEY `company_id` (`company_id`),
ADD FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

-- Add company_id to invoices table
ALTER TABLE `invoices` 
ADD COLUMN `company_id` int(11) NOT NULL AFTER `id`,
ADD KEY `company_id` (`company_id`),
ADD FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

-- Add company_id to payments table
ALTER TABLE `payments` 
ADD COLUMN `company_id` int(11) NOT NULL AFTER `id`,
ADD KEY `company_id` (`company_id`),
ADD FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

-- Add company_id to expenses table
ALTER TABLE `expenses` 
ADD COLUMN `company_id` int(11) NOT NULL AFTER `id`,
ADD KEY `company_id` (`company_id`),
ADD FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

-- Add company_id to contacts table (if exists)
ALTER TABLE `contacts` 
ADD COLUMN `company_id` int(11) DEFAULT NULL AFTER `id`,
ADD KEY `company_id` (`company_id`),
ADD FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

-- Update customer_code to include company_id for uniqueness
ALTER TABLE `customers` 
DROP INDEX `customer_code`,
ADD UNIQUE KEY `unique_customer_code_per_company` (`company_id`, `customer_code`);

-- Update order_number to include company_id for uniqueness
ALTER TABLE `orders` 
DROP INDEX `order_number`,
ADD UNIQUE KEY `unique_order_number_per_company` (`company_id`, `order_number`);

