<?php
/**
 * AJAX: Get Customer Details
 * Tailoring Management System
 */

require_once __DIR__ . '/../../config/config.php';

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check if customer_id is provided
if (!isset($_GET['customer_id']) || empty($_GET['customer_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Customer ID is required']);
    exit;
}

$customerId = (int)$_GET['customer_id'];

try {
    require_once __DIR__ . '/../models/Customer.php';
    $customerModel = new Customer();
    
    $customer = $customerModel->find($customerId);
    
    if (!$customer) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Customer not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'customer' => [
            'id' => $customer['id'],
            'first_name' => $customer['first_name'],
            'last_name' => $customer['last_name'],
            'name' => trim($customer['first_name'] . ' ' . $customer['last_name']),
            'email' => $customer['email'],
            'phone' => $customer['phone'],
            'address' => $customer['address'],
            'date_of_birth' => $customer['date_of_birth']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load customer details: ' . $e->getMessage()]);
}
?>
