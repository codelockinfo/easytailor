<?php
/**
 * AJAX Order Filter Endpoint
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

    require_once $rootDir . '/../models/Order.php';
    require_once $rootDir . '/../models/Customer.php';
    require_once $rootDir . '/../models/ClothType.php';
    require_once $rootDir . '/../models/User.php';

    // Get filter parameters
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    $customer_id = $_GET['customer_id'] ?? '';
    $cloth_type_id = $_GET['cloth_type_id'] ?? '';
    $assigned_tailor_id = $_GET['assigned_tailor_id'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? RECORDS_PER_PAGE);
    
    // Validate and fix limit parameter
    if ($limit <= 0) {
        $limit = RECORDS_PER_PAGE; // Default to records per page if limit is 0 or negative
    }
    
    // Validate page parameter
    if ($page <= 0) {
        $page = 1; // Default to page 1 if page is 0 or negative
    }

    $orderModel = new Order();
    $customerModel = new Customer();
    $clothTypeModel = new ClothType();
    $userModel = new User();
    
    // Build conditions
    $conditions = [];
    if (!empty($status)) {
        $conditions['status'] = $status;
    }
    if (!empty($customer_id)) {
        $conditions['customer_id'] = $customer_id;
    }
    if (!empty($cloth_type_id)) {
        $conditions['cloth_type_id'] = $cloth_type_id;
    }
    if (!empty($assigned_tailor_id)) {
        $conditions['assigned_tailor_id'] = $assigned_tailor_id;
    }
    
    // Get orders
    $offset = ($page - 1) * $limit;
    $orders = $orderModel->getOrdersWithDetails($conditions, $limit, $offset);
    
    // If search is provided, filter results manually
    if (!empty($search)) {
        $searchLower = strtolower($search);
        $orders = array_filter($orders, function($order) use ($searchLower) {
            $customerName = trim($order['first_name'] . ' ' . $order['last_name']);
            return strpos(strtolower($order['order_number']), $searchLower) !== false ||
                   strpos(strtolower($customerName), $searchLower) !== false ||
                   strpos(strtolower($order['customer_phone'] ?? ''), $searchLower) !== false;
        });
    }
    
    $totalOrders = count($orders);
    $totalPages = $limit > 0 ? ceil($totalOrders / $limit) : 1;
    
    // Get filter options
    $customers = $customerModel->findAll(['status' => 'active'], 'first_name, last_name');
    $clothTypes = $clothTypeModel->findAll(['status' => 'active'], 'name');
    
    // Get tailors - try different approaches
    try {
        $tailors = $userModel->findAll(['role' => 'tailor', 'status' => 'active'], 'full_name');
    } catch (Exception $e) {
        // Fallback: get all active users
        $tailors = $userModel->findAll(['status' => 'active'], 'full_name');
    }
    
    // Format orders for display - match original table structure exactly
    $formattedOrders = [];
    foreach ($orders as $order) {
        $formattedOrders[] = [
            'id' => $order['id'],
            'order_number' => htmlspecialchars($order['order_number']),
            'customer_id' => $order['customer_id'] ?? null,
            'cloth_type_id' => $order['cloth_type_id'] ?? null,
            'measurement_id' => $order['measurement_id'] ?? null,
            'assigned_tailor_id' => $order['assigned_tailor_id'] ?? null,
            'first_name' => htmlspecialchars($order['first_name'] ?? ''),
            'last_name' => htmlspecialchars($order['last_name'] ?? ''),
            'customer_code' => htmlspecialchars($order['customer_code'] ?? ''),
            'customer_phone' => htmlspecialchars($order['customer_phone'] ?? ''),
            'cloth_type_name' => htmlspecialchars($order['cloth_type_name'] ?? ''),
            'tailor_name' => htmlspecialchars($order['tailor_name'] ?? ''),
            'order_date' => $order['order_date'],
            'due_date' => $order['due_date'],
            'delivery_date' => $order['delivery_date'] ?? null,
            'status' => $order['status'],
            'total_amount' => $order['total_amount'],
            'advance_amount' => $order['advance_amount'],
            'balance_amount' => $order['balance_amount'],
            'special_instructions' => htmlspecialchars($order['special_instructions'] ?? ''),
            'created_by_name' => htmlspecialchars($order['created_by_name'] ?? ''),
            'created_at' => $order['created_at']
        ];
    }
    
    // Format filter options
    $filterOptions = [
        'customers' => array_map(function($customer) {
            return [
                'id' => $customer['id'],
                'name' => htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']),
                'phone' => htmlspecialchars($customer['phone'] ?? '')
            ];
        }, $customers),
        'cloth_types' => array_map(function($clothType) {
            return [
                'id' => $clothType['id'],
                'name' => htmlspecialchars($clothType['name']),
                'category' => htmlspecialchars($clothType['category'] ?? '')
            ];
        }, $clothTypes),
        'tailors' => array_map(function($tailor) {
            return [
                'id' => $tailor['id'],
                'name' => htmlspecialchars($tailor['full_name'] ?? 'Unknown'),
                'phone' => htmlspecialchars($tailor['phone'] ?? '')
            ];
        }, $tailors ?: [])
    ];
    
    echo json_encode([
        'success' => true,
        'orders' => $formattedOrders,
        'filter_options' => $filterOptions,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_orders' => $totalOrders,
            'has_previous' => $page > 1,
            'has_next' => $page < $totalPages
        ]
    ]);
    
} catch (Exception $e) {
    // Clear any output
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Filter failed: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    // Clear any output
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Filter failed: ' . $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();
