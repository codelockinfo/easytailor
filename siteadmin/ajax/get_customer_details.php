<?php
/**
 * Get Customer Details
 * Fetch detailed information for a specific customer
 */

require_once '../../config/config.php';

// Check if site admin is logged in
if (!isset($_SESSION['site_admin_logged_in']) || $_SESSION['site_admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$customerId = filter_input(INPUT_GET, 'customer_id', FILTER_VALIDATE_INT);
if (!$customerId) {
    echo json_encode(['success' => false, 'message' => 'Customer ID is required']);
    exit;
}

require_once '../../config/database.php';
require_once '../../models/Customer.php';

try {
    $customerModel = new Customer();
    $customer = $customerModel->find($customerId);
    
    if (!$customer) {
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
        exit;
    }
    
    // Build full name
    $firstName = $customer['first_name'] ?? '';
    $lastName = $customer['last_name'] ?? '';
    $fullName = trim($firstName . ' ' . $lastName);
    if (empty($fullName)) {
        $fullName = 'N/A';
    }
    
    // Build full address
    $addressParts = [];
    if (!empty($customer['address'])) $addressParts[] = $customer['address'];
    if (!empty($customer['city'])) $addressParts[] = $customer['city'];
    if (!empty($customer['state'])) $addressParts[] = $customer['state'];
    if (!empty($customer['postal_code'])) $addressParts[] = $customer['postal_code'];
    $fullAddress = !empty($addressParts) ? implode(', ', $addressParts) : 'No address provided';
    
    // Format customer data
    $customerData = [
        'id' => $customer['id'],
        'customer_code' => $customer['customer_code'] ?? '-',
        'first_name' => $firstName,
        'last_name' => $lastName,
        'name' => $fullName,
        'email' => $customer['email'] ?? '-',
        'phone' => $customer['phone'] ?? '-',
        'address' => $customer['address'] ?? '-',
        'city' => $customer['city'] ?? '-',
        'state' => $customer['state'] ?? '-',
        'postal_code' => $customer['postal_code'] ?? '-',
        'full_address' => $fullAddress,
        'date_of_birth' => $customer['date_of_birth'] ?? null,
        'notes' => $customer['notes'] ?? '',
        'status' => $customer['status'] ?? 'active',
        'created_at' => $customer['created_at'] ?? null,
        'updated_at' => $customer['updated_at'] ?? null
    ];
    
    echo json_encode([
        'success' => true,
        'customer' => $customerData
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching customer details: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching customer details: ' . $e->getMessage()
    ]);
}
?>


