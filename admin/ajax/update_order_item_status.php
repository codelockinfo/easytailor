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
    $itemId = (int)($_POST['item_id'] ?? 0);
    $orderId = (int)($_POST['order_id'] ?? 0);
    $status = sanitize_input($_POST['status'] ?? '');

    if ($itemId <= 0 || $orderId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid item or order ID']);
        exit;
    }

    $validStatuses = ['pending', 'in_progress', 'completed', 'delivered', 'cancelled'];
    if (!in_array($status, $validStatuses)) {
        echo json_encode(['success' => false, 'error' => 'Invalid status']);
        exit;
    }

    $orderModel = new Order();
    $order = $orderModel->find($orderId);
    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }

    // Verify item belongs to this order
    $db = $orderModel->getConn();
    $itemStmt = $db->prepare("SELECT oi.*, ct.name as cloth_type_name FROM order_items oi LEFT JOIN cloth_types ct ON oi.cloth_type_id = ct.id WHERE oi.id = :id AND oi.order_id = :order_id");
    $itemStmt->execute([':id' => $itemId, ':order_id' => $orderId]);
    $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        echo json_encode(['success' => false, 'error' => 'Garment item not found in this order']);
        exit;
    }

    // Check authorization: Admin can update all; Tailor can only update items assigned to them
    if (has_role('tailor')) {
        $curUserId = (int)get_user_id();
        $isMyItem = (!empty($item['assigned_tailor_id']) && (int)$item['assigned_tailor_id'] === $curUserId);
        $isOrderTailor = (!empty($order['assigned_tailor_id']) && (int)$order['assigned_tailor_id'] === $curUserId);
        if (!$isMyItem && !$isOrderTailor) {
            echo json_encode(['success' => false, 'error' => 'You can only update garments assigned to you']);
            exit;
        }
    }

    // Update item status
    if ($orderModel->updateOrderItemStatus($itemId, $status)) {
        // Sync overall order status
        $newOverallStatus = $orderModel->syncOrderStatusFromItems($orderId);
        $updatedOrder = $orderModel->find($orderId);
        $overallStatus = $updatedOrder['status'] ?? $newOverallStatus ?? $order['status'];

        // Send notifications if applicable
        try {
            require_once $rootDir . '/../helpers/NotificationService.php';
            $notifService = new NotificationService();
            $clothName = $item['cloth_type_name'] ?? 'Garment';
            if (has_role('tailor')) {
                $tailorId = get_user_id();
                $tailorName = get_user_name();
                if ($status === 'in_progress') {
                    $notifService->onTailorStartedWork($orderId, $tailorId, $tailorName);
                } elseif ($status === 'completed') {
                    $notifService->onTailorCompletedOrder($orderId, $tailorId, $tailorName);
                }
            }
        } catch (\Throwable $notifEx) {
            error_log("Item status notification error: " . $notifEx->getMessage());
        }

        // Status styles for badge
        $statusStyles = [
            'pending' => ['bg' => '#fef3c7', 'color' => '#d97706', 'border' => '#fde68a', 'icon' => 'fa-clock'],
            'in_progress' => ['bg' => '#e0f2fe', 'color' => '#0284c7', 'border' => '#bae6fd', 'icon' => 'fa-cogs'],
            'completed' => ['bg' => '#dcfce7', 'color' => '#16a34a', 'border' => '#bbf7d0', 'icon' => 'fa-check-circle'],
            'delivered' => ['bg' => '#eff6ff', 'color' => '#2563eb', 'border' => '#bfdbfe', 'icon' => 'fa-truck'],
            'cancelled' => ['bg' => '#fee2e2', 'color' => '#dc2626', 'border' => '#fecaca', 'icon' => 'fa-times-circle']
        ];

        $overallStatusColors = [
            'pending' => 'background-color: #f59e0b;',
            'in_progress' => 'background-color: #06b6d4;',
            'completed' => 'background-color: #10b981;',
            'delivered' => 'background-color: #3b82f6;',
            'cancelled' => 'background-color: #ef4444;'
        ];

        echo json_encode([
            'success' => true,
            'message' => ($item['cloth_type_name'] ?? 'Garment') . ' status updated to ' . ucfirst(str_replace('_', ' ', $status)),
            'item_id' => $itemId,
            'item_status' => $status,
            'item_status_display' => ucfirst(str_replace('_', ' ', $status)),
            'item_style' => $statusStyles[$status] ?? $statusStyles['pending'],
            'overall_status' => $overallStatus,
            'overall_status_display' => ucfirst(str_replace('_', ' ', $overallStatus)),
            'overall_status_bg' => $overallStatusColors[$overallStatus] ?? 'background-color: #6b7280;'
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update garment status']);
    }

} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Error $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
ob_end_flush();
