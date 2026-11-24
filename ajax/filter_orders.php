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
    
    require_once $rootDir . '/config/config.php';

    // Check if user is logged in
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    require_once $rootDir . '/models/Order.php';
    require_once $rootDir . '/models/Customer.php';
    require_once $rootDir . '/models/ClothType.php';
    require_once $rootDir . '/models/User.php';

    // Get filter parameters
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    $customer_id = $_GET['customer_id'] ?? '';
    $cloth_type_id = $_GET['cloth_type_id'] ?? '';
    $assigned_tailor_id = $_GET['assigned_tailor_id'] ?? '';
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? RECORDS_PER_PAGE);

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
    if (!empty($start_date)) {
        $conditions['start_date'] = $start_date;
    }
    if (!empty($end_date)) {
        $conditions['end_date'] = $end_date;
    }
    
    // Get orders
    $offset = ($page - 1) * $limit;
    $orders = $orderModel->getOrdersWithDetails($conditions, $limit, $offset);
    
    // If search is provided, filter results manually
    if (!empty($search)) {
        $searchLower = strtolower(trim($search));
        $allOrders = $orderModel->getOrdersWithDetails($conditions, null, 0);
        $orders = array_filter($allOrders, function($order) use ($searchLower) {
            $customerName = !empty($order['measurement_customer_name']) 
                ? $order['measurement_customer_name'] 
                : (!empty($order['customer_name']) ? $order['customer_name'] : trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')));
            $clothNames = ($order['all_cloth_type_names'] ?? '') . ' ' . ($order['cloth_type_name'] ?? '');
            return strpos(strtolower($order['order_number'] ?? ''), $searchLower) !== false ||
                   strpos(strtolower($customerName), $searchLower) !== false ||
                   strpos(strtolower($clothNames), $searchLower) !== false ||
                   strpos(strtolower($order['customer_phone'] ?? ''), $searchLower) !== false;
        });
        $orders = array_values($orders);
        $totalOrders = count($orders);
        $orders = array_slice($orders, $offset, $limit);
    } else {
        $allOrders = $orderModel->getOrdersWithDetails($conditions, null, 0);
        $totalOrders = count($allOrders);
        $orders = array_slice($allOrders, $offset, $limit);
    }
    
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
        $cName = !empty($order['measurement_customer_name']) 
            ? $order['measurement_customer_name'] 
            : (!empty($order['customer_name']) ? $order['customer_name'] : trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')));
        $formattedOrders[] = [
            'id' => $order['id'],
            'order_number' => htmlspecialchars($order['order_number']),
            'customer_name' => htmlspecialchars($cName),
            'display_customer_name' => htmlspecialchars($cName),
            'item_count' => (int)($order['item_count'] ?? 0),
            'all_cloth_type_names' => htmlspecialchars($order['all_cloth_type_names'] ?? ''),
            'all_tailor_names' => htmlspecialchars($order['all_tailor_names'] ?? ''),
            'first_name' => htmlspecialchars($order['first_name'] ?? ''),
            'last_name' => htmlspecialchars($order['last_name'] ?? ''),
            'customer_code' => htmlspecialchars($order['customer_code'] ?? ''),
            'customer_phone' => htmlspecialchars($order['customer_phone'] ?? ''),
            'cloth_type_name' => htmlspecialchars($order['cloth_type_name'] ?? ''),
            'tailor_name' => htmlspecialchars($order['tailor_name'] ?? ''),
            'order_date' => $order['order_date'],
            'due_date' => $order['due_date'],
            'delivery_date' => $order['delivery_date'],
            'status' => $order['status'],
            'total_amount' => $order['total_amount'],
            'advance_amount' => $order['advance_amount'],
            'balance_amount' => $order['balance_amount'],
            'payment_method' => $order['payment_method'] ?? 'cash',
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
    
    ob_clean();
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
