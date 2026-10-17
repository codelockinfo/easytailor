<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/NotificationService.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$userId = get_user_id();
$companyId = get_company_id();

$notifService = new NotificationService();
$unreadCount = $notifService->getUnreadCount($userId, $companyId);
$notifications = $notifService->getRecentNotifications($userId, $companyId, 15);

echo json_encode([
    'success' => true,
    'unread_count' => $unreadCount,
    'notifications' => $notifications
]);
