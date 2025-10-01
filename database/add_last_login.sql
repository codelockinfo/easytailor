-- Add last_login column to users table (optional enhancement)

ALTER TABLE `users` 
ADD COLUMN `last_login` TIMESTAMP NULL DEFAULT NULL 
AFTER `status`;

-- Add index for better performance
ALTER TABLE `users` 
ADD KEY `idx_last_login` (`last_login`);

