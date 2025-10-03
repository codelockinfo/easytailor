<?php
/**
 * Generate Invoice PDF
 * Tailoring Management System
 */

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

    // Get invoice ID
    $invoiceId = (int)($_GET['id'] ?? 0);
    
    if (!$invoiceId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invoice ID is required']);
        exit;
    }

    require_once $rootDir . '/models/Invoice.php';
    require_once $rootDir . '/models/Order.php';
    require_once $rootDir . '/models/Payment.php';
    require_once $rootDir . '/models/Company.php';
    require_once $rootDir . '/models/Customer.php';

    $invoiceModel = new Invoice();
    $orderModel = new Order();
    $paymentModel = new Payment();
    $companyModel = new Company();
    $customerModel = new Customer();

    // Get invoice with details
    $invoices = $invoiceModel->getInvoicesWithDetails(['i.id' => $invoiceId], 1);
    
    if (empty($invoices)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Invoice not found']);
        exit;
    }

    $invoice = $invoices[0];

    // Get order details
    $order = $orderModel->find($invoice['order_id']);

    // Get customer details
    $customer = $customerModel->find($order['customer_id']);

    // Get payments for this invoice
    $payments = $paymentModel->getInvoicePayments($invoiceId);

    // Get company details
    $companyId = get_company_id();
    $company = $companyId ? $companyModel->find($companyId) : null;

    // Generate HTML for PDF
    $html = generateInvoiceHTML($invoice, $order, $customer, $payments, $company);
    
    // For now, we'll return the HTML and let the frontend handle PDF generation
    // In a production environment, you might want to use a library like TCPDF or mPDF
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'invoice_number' => $invoice['invoice_number']
    ]);
    
} catch (Exception $e) {
    // Clear any output
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'PDF generation failed: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    // Clear any output
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'PDF generation failed: ' . $e->getMessage()
    ]);
}

/**
 * Generate HTML for invoice PDF
 */
function generateInvoiceHTML($invoice, $order, $customer, $payments, $company) {
    $invoiceDate = date('d/m/Y', strtotime($invoice['created_at']));
    $dueDate = date('d/m/Y', strtotime($invoice['due_date']));
    
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice ' . htmlspecialchars($invoice['invoice_number']) . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f8f9fa;
            font-size: 14px;
            line-height: 1.4;
        }
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        .company-info h1 {
            color: #667eea;
            margin: 0;
            font-size: 28px;
            font-weight: bold;
        }
        .company-info p {
            margin: 5px 0;
            color: #6c757d;
        }
        .invoice-info {
            text-align: right;
        }
        .invoice-info h2 {
            color: #495057;
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        .invoice-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .bill-to, .invoice-meta {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
        }
        .bill-to h3, .invoice-meta h3 {
            margin: 0 0 15px 0;
            color: #495057;
            font-size: 16px;
            font-weight: bold;
        }
        .bill-to p, .invoice-meta p {
            margin: 5px 0;
            color: #6c757d;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            background: white;
        }
        .items-table th, .items-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        .items-table th {
            background: #667eea;
            color: white;
            font-weight: bold;
        }
        .items-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        .text-right {
            text-align: right;
        }
        .totals {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 20px;
            margin-bottom: 30px;
        }
        .totals-table {
            width: 300px;
            margin-left: auto;
        }
        .totals-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #dee2e6;
        }
        .totals-table .label {
            font-weight: bold;
            color: #495057;
        }
        .totals-table .amount {
            text-align: right;
            font-weight: bold;
        }
        .totals-table .total-row {
            background: #667eea;
            color: white;
            font-weight: bold;
            font-size: 16px;
        }
        .payments-section {
            margin-top: 30px;
        }
        .payments-section h3 {
            color: #495057;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .payments-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        .payments-table th, .payments-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        .payments-table th {
            background: #28a745;
            color: white;
            font-weight: bold;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            color: #6c757d;
            font-size: 12px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-paid { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-overdue { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="header">
            <div class="company-info">';
    
    if ($company) {
        $html .= '<h1>' . htmlspecialchars($company['company_name'] ?? 'Company') . '</h1>';
        if (!empty($company['address'])) {
            $html .= '<p>' . nl2br(htmlspecialchars($company['address'])) . '</p>';
        }
        if (!empty($company['phone'])) {
            $html .= '<p><strong>Phone:</strong> ' . htmlspecialchars($company['phone']) . '</p>';
        }
        if (!empty($company['email'])) {
            $html .= '<p><strong>Email:</strong> ' . htmlspecialchars($company['email']) . '</p>';
        }
    } else {
        $html .= '<h1>' . APP_NAME . '</h1>';
    }
    
    $html .= '</div>
            <div class="invoice-info">
                <h2>INVOICE</h2>
                <p><strong>Invoice #:</strong> ' . htmlspecialchars($invoice['invoice_number']) . '</p>
                <p><strong>Date:</strong> ' . $invoiceDate . '</p>
                <p><strong>Due Date:</strong> ' . $dueDate . '</p>
                <p><strong>Status:</strong> ';
    
    // Status badge
    $statusClass = 'status-pending';
    $statusText = 'Pending';
    if ($invoice['balance_amount'] <= 0) {
        $statusClass = 'status-paid';
        $statusText = 'Paid';
    } elseif (strtotime($invoice['due_date']) < time()) {
        $statusClass = 'status-overdue';
        $statusText = 'Overdue';
    }
    
    $html .= '<span class="status-badge ' . $statusClass . '">' . $statusText . '</span></p>
            </div>
        </div>
        
        <div class="invoice-details">
            <div class="bill-to">
                <h3>Bill To:</h3>
                <p><strong>' . htmlspecialchars(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) . '</strong></p>';
    
    if (!empty($customer['customer_code'])) {
        $html .= '<p><strong>Customer Code:</strong> ' . htmlspecialchars($customer['customer_code']) . '</p>';
    }
    
    if (!empty($customer['address'])) {
        $html .= '<p>' . nl2br(htmlspecialchars($customer['address'])) . '</p>';
    }
    
    if (!empty($customer['phone'])) {
        $html .= '<p><strong>Phone:</strong> ' . htmlspecialchars($customer['phone']) . '</p>';
    }
    
    if (!empty($customer['email'])) {
        $html .= '<p><strong>Email:</strong> ' . htmlspecialchars($customer['email']) . '</p>';
    }
    
    $html .= '</div>
            <div class="invoice-meta">
                <h3>Order Details:</h3>
                <p><strong>Order #:</strong> ' . htmlspecialchars($order['order_number'] ?? 'N/A') . '</p>
                <p><strong>Order Date:</strong> ' . date('d/m/Y', strtotime($order['created_at'] ?? 'now')) . '</p>';
    
    if (!empty($order['delivery_date'])) {
        $html .= '<p><strong>Delivery Date:</strong> ' . date('d/m/Y', strtotime($order['delivery_date'])) . '</p>';
    }
    
    if (!empty($order['notes'])) {
        $html .= '<p><strong>Notes:</strong> ' . htmlspecialchars($order['notes']) . '</p>';
    }
    
    $html .= '</div>
        </div>
        
        <table class="items-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Quantity</th>
                    <th class="text-right">Unit Price</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Tailoring Services</td>
                    <td class="text-right">1</td>
                    <td class="text-right">₹' . number_format($invoice['total_amount'], 2) . '</td>
                    <td class="text-right">₹' . number_format($invoice['total_amount'], 2) . '</td>
                </tr>
            </tbody>
        </table>
        
        <div class="totals">
            <div></div>
            <table class="totals-table">
                <tr>
                    <td class="label">Subtotal:</td>
                    <td class="amount">₹' . number_format($invoice['total_amount'], 2) . '</td>
                </tr>
                <tr>
                    <td class="label">Tax:</td>
                    <td class="amount">₹0.00</td>
                </tr>
                <tr>
                    <td class="label">Total Amount:</td>
                    <td class="amount">₹' . number_format($invoice['total_amount'], 2) . '</td>
                </tr>
                <tr>
                    <td class="label">Paid Amount:</td>
                    <td class="amount">₹' . number_format($invoice['paid_amount'], 2) . '</td>
                </tr>
                <tr class="total-row">
                    <td class="label">Balance Due:</td>
                    <td class="amount">₹' . number_format($invoice['balance_amount'], 2) . '</td>
                </tr>
            </table>
        </div>';
    
    // Payments section
    if (!empty($payments)) {
        $html .= '<div class="payments-section">
            <h3>Payment History</h3>
            <table class="payments-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($payments as $payment) {
            $html .= '<tr>
                <td>' . date('d/m/Y', strtotime($payment['payment_date'])) . '</td>
                <td class="text-right">₹' . number_format($payment['amount'], 2) . '</td>
                <td>' . ucfirst(str_replace('_', ' ', $payment['payment_method'])) . '</td>
                <td>' . htmlspecialchars($payment['notes']) . '</td>
            </tr>';
        }
        
        $html .= '</tbody>
            </table>
        </div>';
    }
    
    $html .= '<div class="footer">
            <p>Thank you for your business!</p>';
    
    if ($company && !empty($company['company_name'])) {
        $html .= '<p>' . htmlspecialchars($company['company_name']) . ' - ' . date('Y') . '</p>';
    } else {
        $html .= '<p>' . APP_NAME . ' - ' . date('Y') . '</p>';
    }
    
    $html .= '</div>
    </div>
</body>
</html>';
    
    return $html;
}

// End output buffering
ob_end_flush();
