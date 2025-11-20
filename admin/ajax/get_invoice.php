<?php
/**
 * AJAX Get Invoice Endpoint
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

    // Check if it's a GET request
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }

    require_once $rootDir . '/models/Invoice.php';
    require_once $rootDir . '/models/Order.php';

    // Get invoice ID
    $invoiceId = (int)($_GET['id'] ?? 0);

    if ($invoiceId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid invoice ID']);
        exit;
    }

    $invoiceModel = new Invoice();
    $orderModel = new Order();
    
    // Get invoice with details
    $invoices = $invoiceModel->getInvoicesWithDetails(['i.id' => $invoiceId], 1);
    
    if (empty($invoices)) {
        echo json_encode(['success' => false, 'error' => 'Invoice not found']);
        exit;
    }

    $invoice = $invoices[0];
    
    // Get the order details
    $order = $orderModel->find($invoice['order_id']);
    
    echo json_encode([
        'success' => true,
        'invoice' => [
            'id' => $invoice['id'],
            'invoice_number' => $invoice['invoice_number'],
            'order_id' => $invoice['order_id'],
            'invoice_date' => $invoice['invoice_date'],
            'due_date' => $invoice['due_date'],
            'subtotal' => $invoice['subtotal'],
            'tax_rate' => $invoice['tax_rate'],
            'tax_amount' => $invoice['tax_amount'],
            'discount_amount' => $invoice['discount_amount'],
            'total_amount' => $invoice['total_amount'],
            'paid_amount' => $invoice['paid_amount'],
            'balance_amount' => $invoice['balance_amount'],
            'notes' => $invoice['notes'],
            'order' => $order ? [
                'id' => $order['id'],
                'order_number' => $order['order_number'],
                'total_amount' => $order['total_amount']
            ] : null
        ]
    ]);
    
} catch (Exception $e) {
    // Clear any output
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch invoice: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    // Clear any output
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch invoice: ' . $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();

