<?php
/**
 * Global Search AJAX Endpoint
 * Searches Orders, Customers, Categories, and Cloth Types
 * Tailoring Management System
 */

require_once '../../config/config.php';
header('Content-Type: application/json; charset=utf-8');

// Check authentication
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once '../../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$companyId = get_company_id();
$query = trim($_GET['q'] ?? $_GET['search'] ?? '');

if ($query === '') {
    echo json_encode([
        'success' => true,
        'query' => '',
        'total_matches' => 0,
        'results' => [
            'orders' => [],
            'customers' => [],
            'categories' => [],
            'cloth_types' => []
        ]
    ]);
    exit;
}

$searchTerm = '%' . $query . '%';

// 1. Search Orders
$orders = [];
try {
    $orderSql = "SELECT o.id, o.order_number, o.status, o.total_amount, o.order_date, o.due_date,
                        COALESCE(NULLIF(ms.name, ''), NULLIF(o.customer_name, ''), CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, ''))) as customer_name,
                        COALESCE(NULLIF(o.customer_phone, ''), NULLIF(c.phone, ''), NULLIF(ms.phone_number, '')) as customer_phone,
                        ct.name as cloth_type_name
                 FROM orders o
                 LEFT JOIN customers c ON o.customer_id = c.id
                 LEFT JOIN cloth_types ct ON o.cloth_type_id = ct.id
                 LEFT JOIN measurements ms ON o.measurement_id = ms.id
                 WHERE 1=1 ";
    if ($companyId) {
        $orderSql .= " AND o.company_id = :company_id ";
    }
    $orderSql .= " AND (
        o.order_number LIKE :q
        OR o.customer_name LIKE :q
        OR o.customer_phone LIKE :q
        OR ms.name LIKE :q
        OR ms.phone_number LIKE :q
        OR c.first_name LIKE :q
        OR c.last_name LIKE :q
        OR CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,'')) LIKE :q
        OR ct.name LIKE :q
    )
    ORDER BY o.id DESC
    LIMIT 5";

    $stmt = $conn->prepare($orderSql);
    $stmt->bindValue(':q', $searchTerm);
    if ($companyId) {
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
    }
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cName = trim($row['customer_name'] ?? '');
        if (empty($cName)) {
            $cName = 'Customer';
        }
        $orders[] = [
            'id' => (int)$row['id'],
            'order_number' => $row['order_number'],
            'customer_name' => $cName,
            'customer_phone' => $row['customer_phone'] ?? '',
            'cloth_type_name' => $row['cloth_type_name'] ?? '',
            'status' => $row['status'] ?? 'pending',
            'total_amount' => (float)($row['total_amount'] ?? 0),
            'formatted_amount' => function_exists('format_currency') ? format_currency((float)($row['total_amount'] ?? 0)) : ('₹' . number_format((float)($row['total_amount'] ?? 0), 2)),
            'order_date' => !empty($row['order_date']) ? date('d M Y', strtotime($row['order_date'])) : '',
            'due_date' => !empty($row['due_date']) ? date('d M Y', strtotime($row['due_date'])) : '',
            'url' => 'order-details.php?id=' . (int)$row['id']
        ];
    }
} catch (Exception $e) {
    error_log("Global search orders error: " . $e->getMessage());
}

// 2. Search Customers
$customers = [];
$seenPhones = [];
try {
    $custSql = "SELECT m.id, m.name, m.phone_number, m.email,
                       (SELECT COUNT(*) FROM orders o WHERE o.measurement_id = m.id) as order_count
                FROM measurements m
                WHERE m.name IS NOT NULL AND TRIM(m.name) != '' ";
    if ($companyId) {
        $custSql .= " AND m.company_id = :company_id ";
    }
    $custSql .= " AND (
        m.name LIKE :q
        OR m.phone_number LIKE :q
        OR m.email LIKE :q
        OR CONCAT('CUST', LPAD(m.id, 6, '0')) LIKE :q
    )
    GROUP BY m.phone_number, m.name
    ORDER BY m.id DESC
    LIMIT 5";

    $stmt = $conn->prepare($custSql);
    $stmt->bindValue(':q', $searchTerm);
    if ($companyId) {
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
    }
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cleanPhone = preg_replace('/[^0-9]/', '', $row['phone_number'] ?? '');
        if ($cleanPhone) {
            $seenPhones[$cleanPhone] = true;
        }
        $customers[] = [
            'id' => (int)$row['id'],
            'name' => trim($row['name']),
            'phone' => $row['phone_number'] ?? '',
            'email' => $row['email'] ?? '',
            'customer_code' => 'CUST' . str_pad($row['id'], 6, '0', STR_PAD_LEFT),
            'order_count' => (int)($row['order_count'] ?? 0),
            'url' => 'customer-details.php?id=' . (int)$row['id']
        ];
    }
} catch (Exception $e) {
    error_log("Global search measurements error: " . $e->getMessage());
}

// Also check customers table if fewer than 5 customers found
if (count($customers) < 5) {
    try {
        $cSql = "SELECT c.id, CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,'')) as name,
                        c.phone, c.email, c.customer_code,
                        (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) as order_count
                 FROM customers c
                 WHERE 1=1 ";
        if ($companyId) {
            $cSql .= " AND c.company_id = :company_id ";
        }
        $cSql .= " AND (
            c.first_name LIKE :q
            OR c.last_name LIKE :q
            OR CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,'')) LIKE :q
            OR c.phone LIKE :q
            OR c.email LIKE :q
            OR c.customer_code LIKE :q
        )
        ORDER BY c.id DESC
        LIMIT " . (5 - count($customers));

        $stmt = $conn->prepare($cSql);
        $stmt->bindValue(':q', $searchTerm);
        if ($companyId) {
            $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $row['phone'] ?? '');
            if ($cleanPhone && isset($seenPhones[$cleanPhone])) {
                continue;
            }
            $customers[] = [
                'id' => (int)$row['id'],
                'name' => trim($row['name']),
                'phone' => $row['phone'] ?? '',
                'email' => $row['email'] ?? '',
                'customer_code' => !empty($row['customer_code']) ? $row['customer_code'] : ('CUST' . str_pad($row['id'], 6, '0', STR_PAD_LEFT)),
                'order_count' => (int)($row['order_count'] ?? 0),
                'url' => 'customer-details.php?id=' . (int)$row['id']
            ];
        }
    } catch (Exception $e) {
        // customers table may not be used or accessible, safe to ignore
    }
}

// 3. Search Categories
$categories = [];
try {
    $catSql = "SELECT c.id, c.name, c.description,
                      (SELECT COUNT(*) FROM cloth_types ct WHERE ct.category = c.name AND (ct.company_id = c.company_id OR ct.company_id IS NULL)) as cloth_count
               FROM categories c
               WHERE 1=1 ";
    if ($companyId) {
        $catSql .= " AND c.company_id = :company_id ";
    }
    $catSql .= " AND (c.name LIKE :q OR c.description LIKE :q)
               ORDER BY c.name ASC
               LIMIT 5";

    $stmt = $conn->prepare($catSql);
    $stmt->bindValue(':q', $searchTerm);
    if ($companyId) {
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
    }
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $categories[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'description' => $row['description'] ?? '',
            'cloth_count' => (int)($row['cloth_count'] ?? 0),
            'url' => 'categories.php?search=' . urlencode($row['name'])
        ];
    }
} catch (Exception $e) {
    error_log("Global search categories error: " . $e->getMessage());
}

// 4. Search Cloth Types
$clothTypes = [];
try {
    $clothSql = "SELECT ct.id, ct.name, ct.category, ct.description, ct.standard_rate
                 FROM cloth_types ct
                 WHERE 1=1 ";
    if ($companyId) {
        $clothSql .= " AND ct.company_id = :company_id ";
    }
    $clothSql .= " AND (ct.name LIKE :q OR ct.category LIKE :q OR ct.description LIKE :q)
                 ORDER BY ct.name ASC
                 LIMIT 5";

    $stmt = $conn->prepare($clothSql);
    $stmt->bindValue(':q', $searchTerm);
    if ($companyId) {
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
    }
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rateFormatted = !empty($row['standard_rate']) 
            ? (function_exists('format_currency') ? format_currency((float)$row['standard_rate']) : ('₹' . number_format((float)$row['standard_rate'], 2)))
            : '';
        $clothTypes[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'category' => $row['category'] ?? '',
            'rate' => $rateFormatted,
            'url' => 'cloth-types.php?search=' . urlencode($row['name'])
        ];
    }
} catch (Exception $e) {
    error_log("Global search cloth types error: " . $e->getMessage());
}

$totalMatches = count($orders) + count($customers) + count($categories) + count($clothTypes);

echo json_encode([
    'success' => true,
    'query' => $query,
    'total_matches' => $totalMatches,
    'results' => [
        'orders' => $orders,
        'customers' => $customers,
        'categories' => $categories,
        'cloth_types' => $clothTypes
    ]
]);
exit;
