<?php
/**
 * Real-time Order Synchronization Endpoint
 * Automatically sends status changes, newly assigned orders, and count updates
 * without requiring full page reloads.
 */
header('Content-Type: application/json');
ob_start();

try {
    $scriptDir = dirname(__FILE__);
    $rootDir = dirname($scriptDir);
    require_once $rootDir . '/../config/config.php';
    require_once $rootDir . '/../config/database.php';
    require_once $rootDir . '/../helpers/SubscriptionHelper.php';

    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $companyId = get_company_id();
    if (!$companyId) {
        echo json_encode(['success' => false, 'error' => 'No active company']);
        exit;
    }

    $userId = (int)get_user_id();
    $isTailor = has_role('tailor');

    $dbInstance = new Database();
    $db = $dbInstance->getConnection();
    if (!$db) {
        throw new Exception("Database connection unavailable");
    }

    $serverTime = date('Y-m-d H:i:s');
    $lastSync = trim($_POST['last_sync'] ?? $_GET['last_sync'] ?? '');
    if (empty($lastSync) || !strtotime($lastSync)) {
        // Fallback: default to 15 seconds ago
        $lastSync = date('Y-m-d H:i:s', time() - 15);
    }

    $visibleIdsRaw = trim($_POST['visible_ids'] ?? $_GET['visible_ids'] ?? '');
    $visibleIds = [];
    if (!empty($visibleIdsRaw)) {
        $parts = explode(',', $visibleIdsRaw);
        foreach ($parts as $p) {
            $val = (int)trim($p);
            if ($val > 0) $visibleIds[] = $val;
        }
    }

    $singleOrderId = (int)($_POST['single_order_id'] ?? $_GET['single_order_id'] ?? 0);

    // 1. Single Order Details (for order-details.php)
    $singleOrderData = null;
    if ($singleOrderId > 0) {
        $singleStmt = $db->prepare("
            SELECT o.*,
                   c.first_name, c.last_name, c.phone as customer_phone,
                   ct.name as cloth_type_name,
                   u.full_name as tailor_name,
                   ms.name as measurement_customer_name
            FROM orders o
            LEFT JOIN customers c ON o.customer_id = c.id
            LEFT JOIN cloth_types ct ON o.cloth_type_id = ct.id
            LEFT JOIN users u ON o.assigned_tailor_id = u.id
            LEFT JOIN measurements ms ON o.measurement_id = ms.id
            WHERE o.id = :order_id AND o.company_id = :company_id
            LIMIT 1
        ");
        $singleStmt->execute([':order_id' => $singleOrderId, ':company_id' => $companyId]);
        $sOrder = $singleStmt->fetch(PDO::FETCH_ASSOC);

        if ($sOrder) {
            $itemsStmt = $db->prepare("
                SELECT oi.id, oi.status, oi.assigned_tailor_id, ct.name as cloth_type_name, u.full_name as tailor_name
                FROM order_items oi
                LEFT JOIN cloth_types ct ON oi.cloth_type_id = ct.id
                LEFT JOIN users u ON oi.assigned_tailor_id = u.id
                WHERE oi.order_id = :order_id
            ");
            $itemsStmt->execute([':order_id' => $singleOrderId]);
            $sItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            $statusStyles = [
                'pending' => ['bg' => '#fef3c7', 'color' => '#d97706', 'border' => '#fde68a', 'icon' => 'fa-clock'],
                'in_progress' => ['bg' => '#e0f2fe', 'color' => '#0284c7', 'border' => '#bae6fd', 'icon' => 'fa-cogs'],
                'completed' => ['bg' => '#dcfce7', 'color' => '#16a34a', 'border' => '#bbf7d0', 'icon' => 'fa-check-circle'],
                'delivered' => ['bg' => '#eff6ff', 'color' => '#2563eb', 'border' => '#bfdbfe', 'icon' => 'fa-truck'],
                'cancelled' => ['bg' => '#fee2e2', 'color' => '#dc2626', 'border' => '#fca5a5', 'icon' => 'fa-times-circle']
            ];

            $formattedItems = [];
            foreach ($sItems as $it) {
                $st = $it['status'] ?: 'pending';
                $style = $statusStyles[$st] ?? $statusStyles['pending'];
                $formattedItems[] = [
                    'id' => (int)$it['id'],
                    'status' => $st,
                    'status_display' => ucfirst(str_replace('_', ' ', $st)),
                    'assigned_tailor_id' => $it['assigned_tailor_id'] ? (int)$it['assigned_tailor_id'] : null,
                    'tailor_name' => $it['tailor_name'] ?? '',
                    'cloth_type_name' => $it['cloth_type_name'] ?? '',
                    'style' => $style
                ];
            }

            $orderStatus = $sOrder['status'] ?: 'pending';
            $singleOrderData = [
                'id' => (int)$sOrder['id'],
                'order_number' => $sOrder['order_number'],
                'status' => $orderStatus,
                'status_display' => ucfirst(str_replace('_', ' ', $orderStatus)),
                'assigned_tailor_id' => $sOrder['assigned_tailor_id'] ? (int)$sOrder['assigned_tailor_id'] : null,
                'tailor_name' => $sOrder['tailor_name'] ?? '',
                'updated_at' => $sOrder['updated_at'] ?? $sOrder['created_at'],
                'items' => $formattedItems
            ];
        }
    }

    // 2. Fetch Orders Created or Updated Since last_sync
    $orderQuery = "
        SELECT o.*,
               c.first_name, c.last_name, c.customer_code, c.phone as customer_phone,
               ct.name as cloth_type_name,
               u.full_name as tailor_name,
               ms.name as measurement_customer_name,
               (SELECT GROUP_CONCAT(DISTINCT ct2.name SEPARATOR ', ')
                FROM order_items oi2
                JOIN cloth_types ct2 ON oi2.cloth_type_id = ct2.id
                WHERE oi2.order_id = o.id) as all_cloth_type_names,
               (SELECT GROUP_CONCAT(DISTINCT u2.full_name SEPARATOR ', ')
                FROM order_items oi4
                JOIN users u2 ON oi4.assigned_tailor_id = u2.id
                WHERE oi4.order_id = o.id) as all_tailor_names,
               (SELECT COUNT(*) FROM order_items oi3 WHERE oi3.order_id = o.id) as item_count
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        LEFT JOIN cloth_types ct ON o.cloth_type_id = ct.id
        LEFT JOIN users u ON o.assigned_tailor_id = u.id
        LEFT JOIN measurements ms ON o.measurement_id = ms.id
        WHERE o.company_id = :company_id
    ";

    $params = [
        ':company_id' => $companyId,
        ':last_sync_upd' => $lastSync,
        ':last_sync_cre' => $lastSync
    ];

    if ($isTailor) {
        $orderQuery .= " AND (o.assigned_tailor_id = :tailor_user_id1 OR EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.id AND oi.assigned_tailor_id = :tailor_user_id2)) ";
        $params[':tailor_user_id1'] = $userId;
        $params[':tailor_user_id2'] = $userId;
    }

    $orderQuery .= " AND (o.updated_at > :last_sync_upd OR o.created_at > :last_sync_cre) ORDER BY o.updated_at DESC, o.id DESC LIMIT 50";

    $stmt = $db->prepare($orderQuery);
    $stmt->execute($params);
    $changedRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $statusUpdates = [];
    $newOrders = [];

    foreach ($changedRows as $order) {
        $cName = !empty($order['measurement_customer_name'])
            ? $order['measurement_customer_name']
            : (!empty($order['customer_name']) ? $order['customer_name'] : trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')));
        if (empty($cName)) $cName = 'Customer';

        $st = $order['status'] ?: 'pending';
        $stDisplay = ucfirst(str_replace('_', ' ', $st));
        $stBadgeClass = 'cat-badge-' . $st;

        $orderId = (int)$order['id'];
        $isExisting = in_array($orderId, $visibleIds, true);

        // Always construct status update info
        $updatePayload = [
            'id' => $orderId,
            'order_number' => $order['order_number'],
            'customer_name' => $cName,
            'status' => $st,
            'status_display' => $stDisplay,
            'status_class' => $stBadgeClass,
            'assigned_tailor_id' => $order['assigned_tailor_id'] ? (int)$order['assigned_tailor_id'] : null,
            'tailor_name' => $order['tailor_name'] ?? '',
            'all_tailor_names' => $order['all_tailor_names'] ?? '',
            'updated_at' => $order['updated_at'] ?? $order['created_at']
        ];

        if ($isExisting) {
            $statusUpdates[] = $updatePayload;
        } else {
            // New order for this user's view (e.g. assigned to tailor or created by admin)
            $formattedOrder = [
                'id' => $orderId,
                'order_number' => $order['order_number'],
                'customer_id' => $order['customer_id'] ?? null,
                'customer_name' => $cName,
                'display_customer_name' => $cName,
                'cloth_type_id' => $order['cloth_type_id'] ?? null,
                'measurement_id' => $order['measurement_id'] ?? null,
                'assigned_tailor_id' => $order['assigned_tailor_id'] ?? null,
                'item_count' => (int)($order['item_count'] ?? 1),
                'all_cloth_type_names' => $order['all_cloth_type_names'] ?? '',
                'all_tailor_names' => $order['all_tailor_names'] ?? '',
                'first_name' => $order['first_name'] ?? '',
                'last_name' => $order['last_name'] ?? '',
                'customer_code' => $order['customer_code'] ?? '',
                'customer_phone' => $order['customer_phone'] ?? '',
                'cloth_type_name' => $order['cloth_type_name'] ?? '',
                'tailor_name' => $order['tailor_name'] ?? '',
                'order_date' => $order['order_date'],
                'due_date' => $order['due_date'],
                'delivery_date' => $order['delivery_date'] ?? null,
                'status' => $st,
                'total_amount' => (float)$order['total_amount'],
                'advance_amount' => (float)($order['advance_amount'] ?? 0),
                'balance_amount' => (float)($order['balance_amount'] ?? max(0, (float)$order['total_amount'] - (float)($order['advance_amount'] ?? 0))),
                'payment_method' => $order['payment_method'] ?? 'cash',
                'special_instructions' => $order['special_instructions'] ?? '',
                'created_at' => $order['created_at']
            ];
            $newOrders[] = $formattedOrder;
        }
    }

    // 3. Check for Removed Orders (e.g. if Admin re-assigned order away from Tailor)
    $removedOrderIds = [];
    if ($isTailor && !empty($visibleIds)) {
        $inPlaceholders = implode(',', array_fill(0, count($visibleIds), '?'));
        $remStmt = $db->prepare("
            SELECT id FROM orders
            WHERE id IN ($inPlaceholders)
              AND company_id = ?
              AND (assigned_tailor_id IS NULL OR assigned_tailor_id != ?)
              AND NOT EXISTS (
                  SELECT 1 FROM order_items
                  WHERE order_id = orders.id AND assigned_tailor_id = ?
              )
        ");
        $remParams = array_merge($visibleIds, [$companyId, $userId, $userId]);
        $remStmt->execute($remParams);
        $removedOrderIds = $remStmt->fetchAll(PDO::FETCH_COLUMN);
        $removedOrderIds = array_map('intval', $removedOrderIds);
    }

    // 4. Calculate Current Status Counts
    $countQuery = "SELECT status, COUNT(*) as cnt FROM orders WHERE company_id = :company_id ";
    $cParams = [':company_id' => $companyId];
    if ($isTailor) {
        $countQuery .= " AND (assigned_tailor_id = :count_user_id1 OR EXISTS (SELECT 1 FROM order_items WHERE order_id = orders.id AND assigned_tailor_id = :count_user_id2)) ";
        $cParams[':count_user_id1'] = $userId;
        $cParams[':count_user_id2'] = $userId;
    }
    $countQuery .= " GROUP BY status";

    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($cParams);
    $countsRaw = $countStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $stats = [
        'total' => array_sum($countsRaw),
        'pending' => (int)($countsRaw['pending'] ?? 0),
        'in_progress' => (int)($countsRaw['in_progress'] ?? 0),
        'completed' => (int)($countsRaw['completed'] ?? 0),
        'delivered' => (int)($countsRaw['delivered'] ?? 0),
        'cancelled' => (int)($countsRaw['cancelled'] ?? 0),
    ];

    ob_clean();
    echo json_encode([
        'success' => true,
        'server_time' => $serverTime,
        'has_changes' => (!empty($statusUpdates) || !empty($newOrders) || !empty($removedOrderIds)),
        'status_updates' => $statusUpdates,
        'new_orders' => $newOrders,
        'removed_order_ids' => $removedOrderIds,
        'stats' => $stats,
        'single_order' => $singleOrderData
    ]);

} catch (\Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
ob_end_flush();
