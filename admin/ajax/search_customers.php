<?php
/**
 * AJAX Customer Search Endpoint
 * Tailoring Management System
 */

require_once '../../config/config.php';

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../models/Customer.php';
require_once '../../config/database.php';

// Get search term - PHP automatically decodes %20 to space, but ensure it's clean
$raw_search = $_GET['search'] ?? '';
$search = trim($raw_search);

// Debug logging
error_log("AJAX search_customers.php - Raw search from GET: " . var_export($raw_search, true));
error_log("AJAX search_customers.php - After trim: " . var_export($search, true));
error_log("AJAX search_customers.php - Search length: " . strlen($search));
error_log("AJAX search_customers.php - Has space: " . (strpos($search, ' ') !== false ? 'yes' : 'no'));
error_log("AJAX search_customers.php - Contains %20: " . (strpos($search, '%20') !== false ? 'yes' : 'no'));

$limit = (int)($_GET['limit'] ?? 20);

if (empty($search)) {
    echo json_encode(['success' => true, 'customers' => [], 'count' => 0]);
    exit;
}

try {
    error_log("AJAX search_customers.php - Creating Customer model");
    $customerModel = new Customer();
    error_log("AJAX search_customers.php - Calling searchCustomers with: '$search'");
    $customers = $customerModel->searchCustomers($search, $limit);
    error_log("AJAX search_customers.php - searchCustomers returned " . count($customers) . " customers");
    
    // Get customer IDs to fetch order counts
    $customerIds = array_column($customers, 'id');
    $orderCounts = [];
    
    if (!empty($customerIds)) {
        $companyId = get_company_id();
        $database = new Database();
        $conn = $database->getConnection();
        
        $placeholders = implode(',', array_fill(0, count($customerIds), '?'));
        $query = "SELECT customer_id, COUNT(*) as order_count 
                  FROM orders 
                  WHERE customer_id IN ($placeholders)";
        
        if ($companyId) {
            $query .= " AND company_id = ?";
        }
        $query .= " GROUP BY customer_id";
        
        $stmt = $conn->prepare($query);
        $params = $customerIds;
        if ($companyId) {
            $params[] = $companyId;
        }
        $stmt->execute($params);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $orderCounts[$row['customer_id']] = (int)$row['order_count'];
        }
    }
    
    // Format customers for display
    $formattedCustomers = [];
    foreach ($customers as $customer) {
        $customerId = $customer['id'];
        $orderCount = $orderCounts[$customerId] ?? 0;
        
        $formattedCustomers[] = [
            'id' => $customerId,
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
            'total_orders' => $orderCount,
            'order_count' => $orderCount,
            'total_spent' => format_currency($customer['total_spent'] ?? 0)
        ];
    }
    
    echo json_encode([
        'success' => true,
        'customers' => $formattedCustomers,
        'count' => count($formattedCustomers)
    ]);
    
} catch (Exception $e) {
    error_log("AJAX search_customers.php - Exception: " . $e->getMessage());
    error_log("AJAX search_customers.php - Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Search failed: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    error_log("AJAX search_customers.php - Fatal Error: " . $e->getMessage());
    error_log("AJAX search_customers.php - Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Search failed: ' . $e->getMessage()
    ]);
}
