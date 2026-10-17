<?php
if (!defined('APP_PATH')) {
    define('APP_PATH', dirname(__DIR__) . '/');
}

if (file_exists(APP_PATH . 'vendor/autoload.php')) {
    require_once APP_PATH . 'vendor/autoload.php';
}

$vapidJsonFile = __DIR__ . '/vapid.json';

if (!file_exists($vapidJsonFile)) {
    if (class_exists('Minishlink\WebPush\VAPID')) {
        $keys = Minishlink\WebPush\VAPID::createVapidKeys();
        $vapidConfig = [
            'publicKey' => $keys['publicKey'],
            'privateKey' => $keys['privateKey'],
            'subject' => 'mailto:support@tailorpro.local'
        ];
        file_put_contents($vapidJsonFile, json_encode($vapidConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

if (file_exists($vapidJsonFile)) {
    $vapidData = json_decode(file_get_contents($vapidJsonFile), true);
    if (!defined('VAPID_PUBLIC_KEY')) {
        define('VAPID_PUBLIC_KEY', $vapidData['publicKey'] ?? '');
    }
    if (!defined('VAPID_PRIVATE_KEY')) {
        define('VAPID_PRIVATE_KEY', $vapidData['privateKey'] ?? '');
    }
    if (!defined('VAPID_SUBJECT')) {
        define('VAPID_SUBJECT', $vapidData['subject'] ?? 'mailto:support@tailorpro.local');
    }
}
function get_vapid_public_key() {
    return defined('VAPID_PUBLIC_KEY') ? VAPID_PUBLIC_KEY : '';
}
