<?php
/**
 * AJAX Add Payment Endpoint
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

    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }

    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    require_once $rootDir . '/models/Payment.php';
    require_once $rootDir . '/models/Invoice.php';

    // Get parameters
    $invoiceId = (int)($_POST['invoice_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $paymentMethod = sanitize_input($_POST['payment_method'] ?? '');
    $paymentDate = sanitize_input($_POST['payment_date'] ?? '');
    $notes = sanitize_input($_POST['notes'] ?? '');

    // Validate inputs
    if ($invoiceId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid invoice ID']);
        exit;
    }

    if ($amount <= 0) {
        echo json_encode(['success' => false, 'error' => 'Payment amount must be greater than 0']);
        exit;
    }

    if (empty($paymentMethod)) {
        echo json_encode(['success' => false, 'error' => 'Payment method is required']);
        exit;
    }

    if (empty($paymentDate)) {
        echo json_encode(['success' => false, 'error' => 'Payment date is required']);
        exit;
    }

    // Validate payment method
    $validMethods = ['cash', 'card', 'bank_transfer', 'check', 'upi', 'other'];
    if (!in_array($paymentMethod, $validMethods)) {
        echo json_encode(['success' => false, 'error' => 'Invalid payment method']);
        exit;
    }

    // Check if invoice exists and get balance
    $invoiceModel = new Invoice();
    $invoices = $invoiceModel->getInvoicesWithDetails(['i.id' => $invoiceId], 1);
    
    if (empty($invoices)) {
        echo json_encode(['success' => false, 'error' => 'Invoice not found']);
        exit;
    }

    $invoice = $invoices[0];

    // Check if payment amount doesn't exceed balance
    if ($amount > $invoice['balance_amount']) {
        echo json_encode(['success' => false, 'error' => 'Payment amount cannot exceed balance amount']);
        exit;
    }

    $paymentModel = new Payment();
    
    // Prepare payment data
    $paymentData = [
        'invoice_id' => $invoiceId,
        'amount' => $amount,
        'payment_method' => $paymentMethod,
        'payment_date' => $paymentDate,
        'notes' => $notes,
        'created_by' => get_user_id()
    ];

    // Add the payment
    $paymentId = $paymentModel->create($paymentData);
    
    if ($paymentId) {
        // Update invoice paid amount and balance
        $newPaidAmount = $invoice['paid_amount'] + $amount;
        $newBalanceAmount = $invoice['total_amount'] - $newPaidAmount;
        
        $invoiceModel->updateInvoiceAmounts($invoiceId, $newPaidAmount, $newBalanceAmount);
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment added successfully',
            'payment_id' => $paymentId,
            'new_paid_amount' => $newPaidAmount,
            'new_balance_amount' => $newBalanceAmount,
            'payment' => [
                'id' => $paymentId,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'payment_date' => $paymentDate,
                'notes' => $notes,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to add payment']);
    }
    
} catch (Exception $e) {
    // Clear any output
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Payment failed: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    // Clear any output
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Payment failed: ' . $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();
