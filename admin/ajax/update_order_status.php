<?php
/**
 * AJAX Order Status Update Endpoint
 * Tailoring Management System
 */

// Set content type to JSON
header('Content-Type: application/json');

// Start output buffering to catch any errors
ob_start();

try {
    // Get the directory of this script
    $scriptDir = dirname(__FILE__);
    $rootDir = dirname($scriptDir);
    
    require_once $rootDir . '/../config/config.php';

    // Check if user is logged in
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }

    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    require_once $rootDir . '/../models/Order.php';

    // Get parameters
    $orderId = (int)($_POST['order_id'] ?? 0);
    $status = sanitize_input($_POST['status'] ?? '');

    // Validate inputs
    if ($orderId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
        exit;
    }

    $validStatuses = ['pending', 'in_progress', 'completed', 'delivered', 'cancelled'];
    if (!in_array($status, $validStatuses)) {
        echo json_encode(['success' => false, 'error' => 'Invalid status']);
        exit;
    }

    $orderModel = new Order();
    
    // Update the order status
    if ($orderModel->updateOrderStatus($orderId, $status)) {
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
    // Clear any output
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Update failed: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    // Clear any output
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Update failed: ' . $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();
