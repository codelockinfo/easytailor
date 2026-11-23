-- Add status field to measurements table and update customers view
-- Tailoring Management System

ALTER TABLE `measurements` 
ADD COLUMN `status` varchar(20) NOT NULL DEFAULT 'active' AFTER `date_of_birth`;

-- Update customers view to expose measurement status
CREATE OR REPLACE VIEW `customers` AS 
select 
  `measurements`.`id` AS `id`,
  `measurements`.`company_id` AS `company_id`,
  substring_index(`measurements`.`name`,' ',1) AS `first_name`,
  (case when (locate(' ',`measurements`.`name`) > 0) then substr(`measurements`.`name`,(locate(' ',`measurements`.`name`) + 1)) else '' end) AS `last_name`,
  `measurements`.`email` AS `email`,
  `measurements`.`phone_number` AS `phone`,
  concat('CUST',lpad(`measurements`.`id`,6,'0')) AS `customer_code`,
  COALESCE(`measurements`.`status`, 'active') AS `status`,
  `measurements`.`created_at` AS `created_at`,
  `measurements`.`updated_at` AS `updated_at` 
from `measurements`;
