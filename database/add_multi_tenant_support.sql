-- Add Multi-Tenant Support
-- This adds user_id to customers table so each user only sees their own data

-- Add user_id to customers table
ALTER TABLE `customers` 
ADD COLUMN `user_id` INT(11) NULL AFTER `id`,
ADD INDEX `user_id` (`user_id`),
ADD FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- Add user_id to cloth_types table
ALTER TABLE `cloth_types` 
ADD COLUMN `user_id` INT(11) NULL AFTER `id`,
ADD INDEX `user_id` (`user_id`),
ADD FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- Add user_id to contacts table  
ALTER TABLE `contacts`
ADD COLUMN `user_id` INT(11) NULL AFTER `id`,
ADD INDEX `user_id` (`user_id`),
ADD FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

COMMIT;


