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
        $curUserId = (int)get_user_id();
        $existingOrder = $orderModel->find($orderId);
        $isAuthorized = ($existingOrder && (int)($existingOrder['assigned_tailor_id'] ?? 0) === $curUserId);
        if (!$isAuthorized) {
            $items = $orderModel->getOrderItems($orderId);
            foreach ($items as $it) {
                if (!empty($it['assigned_tailor_id']) && (int)$it['assigned_tailor_id'] === $curUserId) {
                    $isAuthorized = true;
                    break;
                }
            }
        }
        if (!$isAuthorized) {
            echo json_encode(['success' => false, 'error' => 'You can only update orders assigned to you']);
            exit;
        }
        try {
            $db = $orderModel->getConn();
            $updateItemStmt = $db->prepare("UPDATE order_items SET status = :status WHERE order_id = :order_id AND assigned_tailor_id = :tailor_id");
            $updateItemStmt->execute([':status' => $status, ':order_id' => $orderId, ':tailor_id' => $curUserId]);
            // Sync overall status based on remaining items
            $orderModel->syncOrderStatusFromItems($orderId);
        } catch (\Throwable $e) {}
    } else {
        // Admin / manager updated overall status -> propagate to order items
        try {
            $db = $orderModel->getConn();
            if ($status === 'completed' || $status === 'delivered') {
                $updateItemStmt = $db->prepare("UPDATE order_items SET status = :status WHERE order_id = :order_id AND status != 'cancelled'");
                $updateItemStmt->execute([':status' => $status, ':order_id' => $orderId]);
            } elseif ($status === 'cancelled') {
                $updateItemStmt = $db->prepare("UPDATE order_items SET status = 'cancelled' WHERE order_id = :order_id");
                $updateItemStmt->execute([':order_id' => $orderId]);
            } elseif ($status === 'pending') {
                $updateItemStmt = $db->prepare("UPDATE order_items SET status = 'pending' WHERE order_id = :order_id AND status != 'cancelled'");
                $updateItemStmt->execute([':order_id' => $orderId]);
            } elseif ($status === 'in_progress') {
                $updateItemStmt = $db->prepare("UPDATE order_items SET status = 'in_progress' WHERE order_id = :order_id AND status = 'pending'");
                $updateItemStmt->execute([':order_id' => $orderId]);
            }
        } catch (\Throwable $e) {}
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