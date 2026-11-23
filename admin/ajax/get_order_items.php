<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/Order.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if (!$orderId) {
    http_response_code(400);
    echo json_encode(['error' => 'Order ID is required']);
    exit;
}

try {
    $orderModel = new Order();
    $tailorId = has_role('tailor') ? (int)get_user_id() : null;
    $items = $orderModel->getOrderItems($orderId, $tailorId);
    echo json_encode($items ?: []);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
