<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/NotificationService.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    $data = $_POST;
}

$endpoint = $data['endpoint'] ?? '';
$keys = $data['keys'] ?? [];
$p256dh = $keys['p256dh'] ?? ($data['p256dh'] ?? '');
$auth = $keys['auth'] ?? ($data['auth'] ?? '');

if (empty($endpoint) || empty($p256dh) || empty($auth)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid subscription payload']);
    exit;
}

$userId = get_user_id();
$companyId = get_company_id();
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

$notifService = new NotificationService();
$result = $notifService->savePushSubscription($userId, $companyId, $endpoint, $p256dh, $auth, $userAgent);

if ($result) {
    echo json_encode([
        'success' => true,
        'message' => 'Push subscription saved successfully'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to store push subscription'
    ]);
}
