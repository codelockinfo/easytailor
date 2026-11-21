-- Migration: Add payment_method column to orders table
-- Tailoring Management System

ALTER TABLE `orders` 
ADD COLUMN `payment_method` varchar(50) NOT NULL DEFAULT 'cash' AFTER `balance_amount`;
