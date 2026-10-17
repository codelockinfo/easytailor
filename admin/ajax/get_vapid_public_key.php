<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$publicKey = function_exists('get_vapid_public_key') ? get_vapid_public_key() : (defined('VAPID_PUBLIC_KEY') ? VAPID_PUBLIC_KEY : '');

if (empty($publicKey)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'VAPID public key not configured']);
    exit;
}

echo json_encode([
    'success' => true,
    'publicKey' => $publicKey
]);
