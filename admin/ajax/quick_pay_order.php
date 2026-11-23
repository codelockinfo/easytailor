<?php
/**
 * Quick Pay Order AJAX Endpoint
 * Marks an order as fully paid, updates advance_amount and balance_amount,
 * and updates any corresponding invoice and payment records.
 * Tailoring Management System
 */

header('Content-Type: application/json; charset=utf-8');
ob_start();

try {
    $scriptDir = dirname(__FILE__);
    $rootDir = dirname($scriptDir);
    require_once $rootDir . '/../config/config.php';

    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid security token (CSRF)']);
        exit;
    }

    if (has_role('tailor')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Tailors are not authorized to update order payments']);
        exit;
    }

    require_once $rootDir . '/../models/Order.php';

    $orderId = (int)($_POST['order_id'] ?? 0);
    if ($orderId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
        exit;
    }

    $paymentMethod = sanitize_input($_POST['payment_method'] ?? 'cash');
    if (empty($paymentMethod)) {
        $paymentMethod = 'cash';
    }

    $companyId = get_company_id();
    $orderModel = new Order();
    $order = $orderModel->find($orderId);

    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }

    // Multi-tenant check
    if ($companyId && !empty($order['company_id']) && (int)$order['company_id'] !== (int)$companyId) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized access to this company order']);
        exit;
    }

    $totalAmount = (float)($order['total_amount'] ?? 0.0);
    $currentAdvance = (float)($order['advance_amount'] ?? 0.0);
    $pendingAmount = max(0.0, $totalAmount - $currentAdvance);

    // Update order amounts in orders table
    $updateData = [
        'advance_amount' => $totalAmount,
        'balance_amount' => 0.00,
        'payment_method' => $paymentMethod
    ];
    $updated = $orderModel->update($orderId, $updateData);

    if (!$updated) {
        echo json_encode(['success' => false, 'error' => 'Failed to update order payment in database']);
        exit;
    }

    // If an invoice exists for this order, update it and insert into payments table
    try {
        $invSql = "SELECT id, total_amount, paid_amount, balance_amount 
                   FROM invoices 
                   WHERE order_id = :order_id ";
        if ($companyId) {
            $invSql .= " AND company_id = :company_id ";
        }
        $invSql .= " LIMIT 1";

        $invStmt = $orderModel->conn->prepare($invSql);
        $invParams = [':order_id' => $orderId];
        if ($companyId) {
            $invParams[':company_id'] = $companyId;
        }
        $invStmt->execute($invParams);
        $invoice = $invStmt->fetch(PDO::FETCH_ASSOC);

        if ($invoice) {
            $invUpdate = $orderModel->conn->prepare("UPDATE invoices 
                                                     SET paid_amount = total_amount, 
                                                         balance_amount = 0.00, 
                                                         payment_status = 'paid' 
                                                     WHERE id = :id");
            $invUpdate->execute([':id' => $invoice['id']]);

            // Map payment method to payments table enum ('cash','bank_transfer','card','cheque')
            $enumMethod = match(strtolower($paymentMethod)) {
                'card' => 'card',
                'bank_transfer', 'online', 'upi' => 'bank_transfer',
                'cheque' => 'cheque',
                default => 'cash'
            };

            // Add payment record if pending amount was collected
            if ($pendingAmount > 0) {
                $paySql = "INSERT INTO payments (company_id, invoice_id, amount, payment_method, payment_date, notes, created_by, created_at) 
                           VALUES (:company_id, :invoice_id, :amount, :payment_method, :payment_date, :notes, :created_by, NOW())";
                $payStmt = $orderModel->conn->prepare($paySql);
                $payStmt->execute([
                    ':company_id' => $companyId ?: ($order['company_id'] ?? null),
                    ':invoice_id' => $invoice['id'],
                    ':amount' => $pendingAmount,
                    ':payment_method' => $enumMethod,
                    ':payment_date' => date('Y-m-d'),
                    ':notes' => 'Quick Pay: Full balance settled for Order #' . ($order['order_number'] ?? $orderId),
                    ':created_by' => get_user_id()
                ]);
            }
        }
    } catch (\Throwable $invEx) {
        error_log("Quick pay invoice/payment sync notice for order {$orderId}: " . $invEx->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Payment fully settled successfully',
        'order' => [
            'id' => $orderId,
            'order_number' => $order['order_number'] ?? ('ORD' . $orderId),
            'total_amount' => $totalAmount,
            'advance_amount' => $totalAmount,
            'balance_amount' => 0.00,
            'payment_method' => $paymentMethod,
            'formatted_total' => function_exists('format_currency') ? format_currency($totalAmount) : ('₹' . number_format($totalAmount, 2)),
            'formatted_advance' => function_exists('format_currency') ? format_currency($totalAmount) : ('₹' . number_format($totalAmount, 2)),
            'formatted_balance' => function_exists('format_currency') ? format_currency(0.00) : '₹0.00',
            'collected_amount' => $pendingAmount
        ]
    ]);
    exit;

} catch (\Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
    exit;
}
