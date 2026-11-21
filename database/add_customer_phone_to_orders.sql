-- Migration: Add customer_phone to orders table
-- Tailoring Management System

ALTER TABLE `orders` 
ADD COLUMN `customer_phone` varchar(25) DEFAULT NULL AFTER `customer_name`;
