<?php
/**
 * AJAX Customer Search Endpoint
 * Tailoring Management System
 */

require_once '../config/config.php';

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../models/Customer.php';

// Get search term
$search = $_GET['search'] ?? '';
$limit = (int)($_GET['limit'] ?? 20);

if (empty($search)) {
    echo json_encode(['customers' => []]);
    exit;
}

try {
    $customerModel = new Customer();
    $customers = $customerModel->searchCustomers($search, $limit);
    
    // Format customers for display
    $formattedCustomers = [];
    foreach ($customers as $customer) {
        $formattedCustomers[] = [
            'id' => $customer['id'],
            'first_name' => htmlspecialchars($customer['first_name']),
            'last_name' => htmlspecialchars($customer['last_name']),
            'name' => htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']),
            'customer_code' => htmlspecialchars($customer['customer_code']),
            'email' => htmlspecialchars($customer['email']),
            'phone' => htmlspecialchars($customer['phone']),
            'city' => htmlspecialchars($customer['city']),
            'state' => htmlspecialchars($customer['state']),
            'date_of_birth' => $customer['date_of_birth'] ? format_date($customer['date_of_birth']) : null,
            'status' => $customer['status'],
            'created_at' => format_date($customer['created_at'], 'M j, Y'),
            'total_orders' => $customer['total_orders'] ?? 0,
            'total_spent' => format_currency($customer['total_spent'] ?? 0)
        ];
    }
    
    echo json_encode([
        'success' => true,
        'customers' => $formattedCustomers,
        'count' => count($formattedCustomers)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Search failed: ' . $e->getMessage()
    ]);
}
