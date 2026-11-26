<?php
/**
 * Slack Notification Configuration
 * Tailoring Management System (TailorPro)
 */

if (!defined('SLACK_NOTIFICATIONS_ENABLED')) {
    define('SLACK_NOTIFICATIONS_ENABLED', true);
}

if (!defined('SLACK_BOT_TOKEN')) {
    define('SLACK_BOT_TOKEN', '');
}

if (!defined('SLACK_CHANNEL_ID')) {
    define('SLACK_CHANNEL_ID', '');
}

if (!defined('SLACK_WEBHOOK_URL')) {
    define('SLACK_WEBHOOK_URL', '');
}
