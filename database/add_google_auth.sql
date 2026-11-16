-- Add Google OAuth Support to users table
-- Tailoring Management System

-- Add google_id and avatar columns to users table if they don't already exist
ALTER TABLE `users`
  ADD COLUMN `google_id` VARCHAR(100) DEFAULT NULL AFTER `email`,
  ADD COLUMN `avatar` VARCHAR(255) DEFAULT NULL AFTER `address`,
  ADD INDEX `idx_google_id` (`google_id`);
