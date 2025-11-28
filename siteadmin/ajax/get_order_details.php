<?php
/**
 * Get order details for popup
 */

require_once '../../config/config.php';

// Check if site admin is logged in
if (!isset($_SESSION['site_admin_logged_in']) || $_SESSION['site_admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$orderId = $_GET['order_id'] ?? null;

if (!$orderId) {
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

try {
    $query = "SELECT o.*, 
                     c.first_name, c.last_name, c.customer_code, 
                     c.phone as customer_phone, c.email as customer_email,
                     c.address as customer_address, c.city, c.state, c.postal_code,
                     ct.name as cloth_type_name, ct.category as cloth_category,
                     COALESCE(creator.full_name, creator.username, '') as created_by_name
              FROM orders o
              LEFT JOIN customers c ON o.customer_id = c.id
              LEFT JOIN cloth_types ct ON o.cloth_type_id = ct.id
              LEFT JOIN users creator ON o.created_by = creator.id
              WHERE o.id = :order_id 
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':order_id', $orderId);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    $customerName = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
    if (empty($customerName)) {
        $customerName = 'N/A';
    }
    
    // Build full address
    $addressParts = [];
    if (!empty($order['customer_address'])) $addressParts[] = $order['customer_address'];
    if (!empty($order['city'])) $addressParts[] = $order['city'];
    if (!empty($order['state'])) $addressParts[] = $order['state'];
    if (!empty($order['postal_code'])) $addressParts[] = $order['postal_code'];
    $fullAddress = !empty($addressParts) ? implode(', ', $addressParts) : '';
    
    // Format dates
    function formatDate($date) {
        if (!$date) return '-';
        $d = new DateTime($date);
        return $d->format('M d, Y');
    }
    
    function formatDateTime($datetime) {
        if (!$datetime) return '-';
        $d = new DateTime($datetime);
        return $d->format('M d, Y H:i');
    }
    
    // Calculate paid amount from advance_amount
    $paidAmount = floatval($order['advance_amount'] ?? 0);
    $totalAmount = floatval($order['total_amount'] ?? 0);
    $balanceAmount = floatval($order['balance_amount'] ?? ($totalAmount - $paidAmount));
    
    $result = [
        'id' => $order['id'],
        'order_number' => $order['order_number'] ?? 'ORD-' . str_pad($order['id'], 6, '0', STR_PAD_LEFT),
        'status' => $order['status'] ?? 'pending',
        'total_amount' => $totalAmount,
        'paid_amount' => $paidAmount,
        'advance_amount' => $paidAmount,
        'balance_amount' => $balanceAmount,
        'due_date' => formatDate($order['due_date']),
        'customer' => [
            'name' => $customerName,
            'code' => $order['customer_code'] ?? '',
            'phone' => $order['customer_phone'] ?? '',
            'email' => $order['customer_email'] ?? '',
            'address' => $fullAddress
        ],
        'timeline' => [
            'order_date' => formatDate($order['order_date']),
            'due_date' => formatDate($order['due_date']),
            'delivery_date' => formatDate($order['delivery_date']),
            'created_at' => formatDateTime($order['created_at']),
            'created_by' => $order['created_by_name'] ?? $order['created_by_username'] ?? ''
        ],
        'order_info' => [
            'cloth_type' => $order['cloth_type_name'] ?? '',
            'cloth_category' => $order['cloth_category'] ?? '',
            'standard_rate' => $totalAmount
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'order' => $result
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching order details: ' . $e->getMessage()
    ]);
}

