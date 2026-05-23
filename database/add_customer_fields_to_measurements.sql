-- Add customer details fields to measurements table
ALTER TABLE `measurements` 
ADD COLUMN `name` varchar(255) DEFAULT NULL AFTER `cloth_type_id`,
ADD COLUMN `email` varchar(255) DEFAULT NULL AFTER `name`,
ADD COLUMN `phone_number` varchar(50) DEFAULT NULL AFTER `email`,
ADD COLUMN `address` text DEFAULT NULL AFTER `phone_number`,
ADD COLUMN `date_of_birth` date DEFAULT NULL AFTER `address`;
