<?php
/**
 * Get Tailor Assigned Orders & Items AJAX Endpoint
 * Tailoring Management System
 */

header('Content-Type: application/json');
ob_start();

try {
    $scriptDir = dirname(__FILE__);
    $rootDir = dirname($scriptDir);
    
    require_once $rootDir . '/config/config.php';

    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $companyId = get_company_id();
    if (!$companyId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Company not found']);
        exit;
    }

    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    if ($userId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
        exit;
    }

    require_once $rootDir . '/models/User.php';
    $userModel = new User();
    $user = $userModel->find($userId);
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User not found or access denied']);
        exit;
    }

    $db = (new Database())->getConnection();

    // 1. Fetch items assigned directly in order_items
    $sql1 = "SELECT 
                oi.id as item_id,
                o.id as order_id,
                o.order_number,
                o.order_date,
                o.due_date,
                COALESCE(NULLIF(ms.name, ''), NULLIF(o.customer_name, '')) as customer_name,
                COALESCE(NULLIF(o.customer_phone, ''), NULLIF(ms.phone_number, '')) as customer_phone,
                ct.name as cloth_type_name,
                oi.status as garment_status,
                o.status as order_status,
                oi.price as price,
                oi.quantity as quantity,
                oi.notes as notes
             FROM order_items oi
             JOIN orders o ON oi.order_id = o.id
             LEFT JOIN cloth_types ct ON oi.cloth_type_id = ct.id
             LEFT JOIN measurements ms ON oi.measurement_id = ms.id
             WHERE oi.assigned_tailor_id = :tailor_id_1
               AND o.company_id = :company_id_1";

    // 2. Fetch primary garments from orders where no order_items exist, but o.assigned_tailor_id = tailor_id
    $sql2 = "SELECT 
                NULL as item_id,
                o.id as order_id,
                o.order_number,
                o.order_date,
                o.due_date,
                COALESCE(NULLIF(ms.name, ''), NULLIF(o.customer_name, '')) as customer_name,
                COALESCE(NULLIF(o.customer_phone, ''), NULLIF(ms.phone_number, '')) as customer_phone,
                ct.name as cloth_type_name,
                o.status as garment_status,
                o.status as order_status,
                o.total_amount as price,
                1 as quantity,
                o.special_instructions as notes
             FROM orders o
             LEFT JOIN cloth_types ct ON o.cloth_type_id = ct.id
             LEFT JOIN measurements ms ON o.measurement_id = ms.id
             WHERE o.assigned_tailor_id = :tailor_id_2
               AND o.company_id = :company_id_2
               AND NOT EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.id)";

    $unionSql = "($sql1) UNION ALL ($sql2) ORDER BY order_id DESC, item_id DESC";
    $stmt = $db->prepare($unionSql);
    $stmt->execute([
        ':tailor_id_1' => $userId,
        ':company_id_1' => $companyId,
        ':tailor_id_2' => $userId,
        ':company_id_2' => $companyId
    ]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stats = [
        'total' => count($items),
        'pending' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'delivered' => 0,
        'cancelled' => 0
    ];

    foreach ($items as &$it) {
        $st = strtolower(trim($it['garment_status'] ?: 'pending'));
        if (isset($stats[$st])) {
            $stats[$st]++;
        } else {
            $stats['pending']++;
        }
        $it['customer_name'] = !empty($it['customer_name']) ? $it['customer_name'] : 'Walk-in Customer';
        $it['cloth_type_name'] = !empty($it['cloth_type_name']) ? $it['cloth_type_name'] : 'Standard Garment';
    }
    unset($it);

    ob_clean();
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => (int)$user['id'],
            'full_name' => $user['full_name'],
            'username' => $user['username'],
            'email' => $user['email'],
            'phone' => $user['phone'] ?? '',
            'role' => $user['role'],
            'status' => $user['status'],
            'created_at' => $user['created_at']
        ],
        'stats' => $stats,
        'items' => $items
    ]);
    exit;

} catch (Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}
