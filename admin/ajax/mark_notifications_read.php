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

$userId = get_user_id();
$companyId = get_company_id();
$notifService = new NotificationService();

$notificationId = (int)($_POST['id'] ?? 0);
$orderId = (int)($_POST['order_id'] ?? 0);
$markAll = !empty($_POST['all']);

if ($notificationId === 0 && $orderId === 0 && !$markAll) {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $json = json_decode($rawInput, true);
        if (is_array($json)) {
            $notificationId = (int)($json['id'] ?? 0);
            $orderId = (int)($json['order_id'] ?? 0);
            $markAll = !empty($json['all']);
        }
    }
}

if ($markAll) {
    $success = $notifService->markAllAsRead($userId, $companyId);
} elseif ($notificationId > 0) {
    $success = $notifService->markAsRead($notificationId, $userId);
} elseif ($orderId > 0) {
    $success = $notifService->markOrderNotificationsAsRead($orderId, $userId);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

$unreadCount = $notifService->getUnreadCount($userId, $companyId);

echo json_encode([
    'success' => true,
    'unread_count' => $unreadCount
]);
