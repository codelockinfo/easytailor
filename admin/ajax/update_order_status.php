<?php
header('Content-Type: application/json');
ob_start();

try {
    $scriptDir = dirname(__FILE__);
    $rootDir = dirname($scriptDir);
    require_once $rootDir . '/../config/config.php';
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
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    require_once $rootDir . '/../models/Order.php';
    $orderId = (int)($_POST['order_id'] ?? 0);
    $status = sanitize_input($_POST['status'] ?? '');
    if ($orderId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
        exit;
    }
    $validStatuses = ['pending', 'in_progress', 'completed', 'delivered', 'cancelled'];
    if (!in_array($status, $validStatuses)) {
        echo json_encode(['success' => false, 'error' => 'Invalid status']);
        exit;
    }
    $reason = sanitize_input($_POST['cancellation_reason'] ?? $_POST['reason'] ?? '');
    $orderModel = new Order();
    if (has_role('tailor')) {
        $existingOrder = $orderModel->find($orderId);
        if (!$existingOrder || $existingOrder['assigned_tailor_id'] != get_user_id()) {
            echo json_encode(['success' => false, 'error' => 'You can only update orders assigned to you']);
            exit;
        }
    }
    if ($orderModel->updateOrderStatus($orderId, $status)) {
        try {
            require_once $rootDir . '/../helpers/NotificationService.php';
            $notifService = new NotificationService();
            if (has_role('tailor')) {
                $tailorId = get_user_id();
                $tailorName = get_user_name();
                if ($status === 'in_progress') {
                    $notifService->onTailorStartedWork($orderId, $tailorId, $tailorName);
                } elseif ($status === 'completed') {
                    $notifService->onTailorCompletedOrder($orderId, $tailorId, $tailorName);
                } elseif ($status === 'cancelled') {
                    $notifService->onTailorCancelledOrder($orderId, $tailorId, $tailorName, $reason);
                }
            } else {
                $notifService->onAdminUpdatedOrderStatus($orderId, $status, get_user_id(), $reason);
            }
        } catch (Throwable $notifEx) {
            error_log("Status change notification error: " . $notifEx->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => 'Order status updated successfully',
            'order_id' => $orderId,
            'new_status' => $status,
            'status_display' => ucfirst(str_replace('_', ' ', $status))
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update order status']);
    }
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Update failed: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Update failed: ' . $e->getMessage()
    ]);
}
ob_end_flush();