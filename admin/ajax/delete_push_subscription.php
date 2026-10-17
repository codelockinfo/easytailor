<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/NotificationService.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: $_POST;
$endpoint = $data['endpoint'] ?? '';

if (!empty($endpoint)) {
    $notifService = new NotificationService();
    $notifService->deletePushSubscription($endpoint);
}

echo json_encode(['success' => true]);
