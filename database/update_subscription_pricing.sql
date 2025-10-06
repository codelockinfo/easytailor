-- Update subscription packages with new pricing
-- This script updates the subscription_packages table with the new pricing structure

-- Update existing subscription packages with new pricing
UPDATE `subscription_packages` SET 
    `price` = 0.00,
    `description` = 'Basic features for small businesses - 30 day trial'
WHERE `name` = 'Free';

UPDATE `subscription_packages` SET 
    `price` = 99.00,
    `description` = 'Standard features for growing businesses - Monthly billing'
WHERE `name` = 'Basic';

UPDATE `subscription_packages` SET 
    `price` = 199.00,
    `description` = 'Advanced features for established businesses - Monthly billing'
WHERE `name` = 'Premium';

UPDATE `subscription_packages` SET 
    `price` = 999.00,
    `description` = 'Full features for large businesses - Monthly billing'
WHERE `name` = 'Enterprise';

-- Add annual pricing column if it doesn't exist
ALTER TABLE `subscription_packages` 
ADD COLUMN IF NOT EXISTS `price_annual` DECIMAL(10,2) DEFAULT 0.00 AFTER `price`;

-- Update annual pricing (10% discount)
UPDATE `subscription_packages` SET 
    `price_annual` = 0.00
WHERE `name` = 'Free';

UPDATE `subscription_packages` SET 
    `price_annual` = 89.00
WHERE `name` = 'Basic';

UPDATE `subscription_packages` SET 
    `price_annual` = 179.00
WHERE `name` = 'Premium';

UPDATE `subscription_packages` SET 
    `price_annual` = 899.00
WHERE `name` = 'Enterprise';

-- Add billing type column
ALTER TABLE `subscription_packages` 
ADD COLUMN IF NOT EXISTS `billing_type` ENUM('monthly', 'annual') DEFAULT 'monthly' AFTER `price_annual`;

-- Update customer and order limits
UPDATE `subscription_packages` SET 
    `max_customers` = 30,
    `max_orders` = 50,
    `features` = '["basic_customers", "basic_orders", "basic_reports", "email_support"]'
WHERE `name` = 'Free';

UPDATE `subscription_packages` SET 
    `max_customers` = 100,
    `max_orders` = 150,
    `features` = '["advanced_customers", "advanced_orders", "advanced_reports", "priority_email_support", "custom_cloth_types", "invoice_generation", "sms_notifications"]'
WHERE `name` = 'Basic';

UPDATE `subscription_packages` SET 
    `max_customers` = 500,
    `max_orders` = 1000,
    `features` = '["premium_customers", "premium_orders", "premium_reports", "priority_support_24_7", "sms_notifications", "export_data", "custom_integrations", "training_onboarding"]'
WHERE `name` = 'Premium';

UPDATE `subscription_packages` SET 
    `max_customers` = -1,
    `max_orders` = -1,
    `features` = '["unlimited_customers", "unlimited_orders", "unlimited_reports", "dedicated_account_manager", "custom_integrations", "training_onboarding", "sla_guarantee", "data_migration_support", "priority_support_24_7"]'
WHERE `name` = 'Enterprise';
