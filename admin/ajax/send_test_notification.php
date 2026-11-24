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

try {
    $notifService = new NotificationService();
    $title = "Test Notification 🎉";
    $message = "Notifications are active and working perfectly on your device!";
    $icon = "fas fa-check-circle text-success";
    $link = "dashboard";

    $notifId = $notifService->createNotification(
        $userId,
        $companyId,
        null,
        'test_alert',
        $title,
        $message,
        $link,
        $icon
    );

    if ($notifId) {
        echo json_encode([
            'success' => true,
            'message' => 'Test notification sent successfully',
            'notification_id' => $notifId
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Could not save notification (a test notification may have been sent in the last 60 seconds)'
        ]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
