-- Migration: Add Slack notification columns to company_settings
-- Tailoring Management System (TailorPro)

ALTER TABLE `company_settings`
ADD COLUMN IF NOT EXISTS `slack_bot_token` VARCHAR(255) NULL DEFAULT NULL AFTER `updated_at`,
ADD COLUMN IF NOT EXISTS `slack_channel_id` VARCHAR(100) NULL DEFAULT NULL AFTER `slack_bot_token`,
ADD COLUMN IF NOT EXISTS `slack_webhook_url` VARCHAR(500) NULL DEFAULT NULL AFTER `slack_channel_id`;
