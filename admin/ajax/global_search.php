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

/**
 * Execute a prepared statement with positional parameters cleanly when
 * PDO::ATTR_EMULATE_PREPARES is disabled.
 */
function runSearchQuery(PDO $conn, string $sql, array $params): array
{
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$searchTerm = '%' . $query . '%';

// 1. Search Orders
$orders = [];
try {
    $orderSql = "SELECT o.id, o.order_number, o.status, o.total_amount, o.order_date, o.due_date,
                        COALESCE(NULLIF(ms.name, ''), NULLIF(o.customer_name, ''), NULLIF(TRIM(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, ''))), '')) as customer_name,
                        COALESCE(NULLIF(o.customer_phone, ''), NULLIF(c.phone, ''), NULLIF(ms.phone_number, '')) as customer_phone,
                        ct.name as cloth_type_name
                 FROM orders o
                 LEFT JOIN customers c ON o.customer_id = c.id
                 LEFT JOIN cloth_types ct ON o.cloth_type_id = ct.id
                 LEFT JOIN measurements ms ON o.measurement_id = ms.id
                 WHERE 1=1 ";
    $orderParams = [];
    if ($companyId) {
        $orderSql .= " AND o.company_id = ? ";
        $orderParams[] = (int)$companyId;
    }
    $orderSql .= " AND (
        o.order_number LIKE ?
        OR o.customer_name LIKE ?
        OR o.customer_phone LIKE ?
        OR ms.name LIKE ?
        OR ms.phone_number LIKE ?
        OR c.first_name LIKE ?
        OR c.last_name LIKE ?
        OR CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,'')) LIKE ?
        OR ct.name LIKE ?
    )
    ORDER BY o.id DESC
    LIMIT 5";
    array_push($orderParams, ...array_fill(0, 9, $searchTerm));

    foreach (runSearchQuery($conn, $orderSql, $orderParams) as $row) {
        $cName = trim($row['customer_name'] ?? '');
        if ($cName === '') {
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

// 2. Search Customers (customers table first so customer-details.php?id= matches customers.id)
$customers = [];
$seenKeys = [];
try {
    $cSql = "SELECT c.id, TRIM(CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,''))) as name,
                    c.phone, c.email, c.customer_code,
                    (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id OR o.measurement_id = c.id) as order_count
             FROM customers c
             WHERE 1=1 ";
    $cParams = [];
    if ($companyId) {
        $cSql .= " AND c.company_id = ? ";
        $cParams[] = (int)$companyId;
    }
    $cSql .= " AND (
        c.first_name LIKE ?
        OR c.last_name LIKE ?
        OR CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,'')) LIKE ?
        OR c.phone LIKE ?
        OR c.email LIKE ?
        OR c.customer_code LIKE ?
    )
    ORDER BY c.id DESC
    LIMIT 5";
    array_push($cParams, ...array_fill(0, 6, $searchTerm));

    foreach (runSearchQuery($conn, $cSql, $cParams) as $row) {
        $cleanPhone = preg_replace('/[^0-9]/', '', $row['phone'] ?? '');
        $nameKey = strtolower(trim($row['name'] ?? ''));
        if ($cleanPhone !== '') {
            $seenKeys['p:' . $cleanPhone] = true;
        }
        if ($nameKey !== '') {
            $seenKeys['n:' . $nameKey] = true;
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
    error_log("Global search customers error: " . $e->getMessage());
}

// Fallback to measurements table if fewer than 5 customers found (avoiding ONLY_FULL_GROUP_BY)
if (count($customers) < 5) {
    try {
        $custSql = "SELECT m.id, m.name, m.phone_number, m.email,
                           (SELECT COUNT(*) FROM orders o WHERE o.measurement_id = m.id) as order_count
                    FROM measurements m
                    WHERE m.name IS NOT NULL AND TRIM(m.name) != '' ";
        $mParams = [];
        if ($companyId) {
            $custSql .= " AND m.company_id = ? ";
            $mParams[] = (int)$companyId;
        }
        $custSql .= " AND (
            m.name LIKE ?
            OR m.phone_number LIKE ?
            OR m.email LIKE ?
            OR CONCAT('CUST', LPAD(m.id, 6, '0')) LIKE ?
        )
        ORDER BY m.id DESC
        LIMIT 25";
        array_push($mParams, ...array_fill(0, 4, $searchTerm));

        foreach (runSearchQuery($conn, $custSql, $mParams) as $row) {
            if (count($customers) >= 5) {
                break;
            }
            $cleanPhone = preg_replace('/[^0-9]/', '', $row['phone_number'] ?? '');
            $nameKey = strtolower(trim($row['name'] ?? ''));
            if (($cleanPhone !== '' && isset($seenKeys['p:' . $cleanPhone])) || ($nameKey !== '' && isset($seenKeys['n:' . $nameKey]))) {
                continue;
            }
            if ($cleanPhone !== '') {
                $seenKeys['p:' . $cleanPhone] = true;
            }
            if ($nameKey !== '') {
                $seenKeys['n:' . $nameKey] = true;
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
}

// 3. Search Categories
$categories = [];
try {
    $catSql = "SELECT c.id, c.name, c.description,
                      (SELECT COUNT(*) FROM cloth_types ct WHERE ct.category = c.name AND (ct.company_id = c.company_id OR ct.company_id IS NULL)) as cloth_count
               FROM categories c
               WHERE 1=1 ";
    $catParams = [];
    if ($companyId) {
        $catSql .= " AND c.company_id = ? ";
        $catParams[] = (int)$companyId;
    }
    $catSql .= " AND (c.name LIKE ? OR c.description LIKE ?)
               ORDER BY c.name ASC
               LIMIT 5";
    array_push($catParams, $searchTerm, $searchTerm);

    foreach (runSearchQuery($conn, $catSql, $catParams) as $row) {
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
    $clothParams = [];
    if ($companyId) {
        $clothSql .= " AND ct.company_id = ? ";
        $clothParams[] = (int)$companyId;
    }
    $clothSql .= " AND (ct.name LIKE ? OR ct.category LIKE ? OR ct.description LIKE ?)
                 ORDER BY ct.name ASC
                 LIMIT 5";
    array_push($clothParams, $searchTerm, $searchTerm, $searchTerm);

    foreach (runSearchQuery($conn, $clothSql, $clothParams) as $row) {
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
