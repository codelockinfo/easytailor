<?php
/**
 * Generate Invoice PDF (Print Version)
 * Tailoring Management System
 */

// Set content type to HTML for print-friendly output
header('Content-Type: text/html; charset=UTF-8');

try {
    // Get the directory of this script
    $scriptDir = dirname(__FILE__);
    $rootDir = dirname($scriptDir);
    
    require_once $rootDir . '/config/config.php';

    // Check if user is logged in
    if (!is_logged_in()) {
        die('Unauthorized access');
    }

    // Get invoice ID
    $invoiceId = (int)($_GET['id'] ?? 0);
    
    if (!$invoiceId) {
        die('Invoice ID is required');
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
        die('Invoice not found');
    }

    $invoice = $invoices[0];
    $order = $orderModel->find($invoice['order_id']);
    $customer = $customerModel->find($order['customer_id']);
    $payments = $paymentModel->getInvoicePayments($invoiceId);
    $companyId = get_company_id();
    $company = $companyId ? $companyModel->find($companyId) : null;

    // Generate the invoice HTML
    generateInvoicePrintHTML($invoice, $order, $customer, $payments, $company);
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

function generateInvoicePrintHTML($invoice, $order, $customer, $payments, $company) {
    $invoiceDate = date('d/m/Y', strtotime($invoice['created_at']));
    $dueDate = date('d/m/Y', strtotime($invoice['due_date']));
    
    // Status determination
    $statusClass = 'status-pending';
    $statusText = 'Pending';
    if ($invoice['balance_amount'] <= 0) {
        $statusClass = 'status-paid';
        $statusText = 'Paid';
    } elseif (strtotime($invoice['due_date']) < time()) {
        $statusClass = 'status-overdue';
        $statusText = 'Overdue';
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: white;
        }
        
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px;
            background: white;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 3px solid #667eea;
        }
        
        .company-info h1 {
            color: #667eea;
            margin: 0;
            font-size: 32px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .company-info p {
            margin: 8px 0;
            color: #666;
            font-size: 14px;
        }
        
        .invoice-info {
            text-align: right;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 2px solid #e9ecef;
        }
        
        .invoice-info h2 {
            color: #495057;
            margin: 0 0 15px 0;
            font-size: 28px;
            font-weight: bold;
        }
        
        .invoice-info p {
            margin: 5px 0;
            color: #6c757d;
            font-size: 14px;
        }
        
        .invoice-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .bill-to, .invoice-meta {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .bill-to h3, .invoice-meta h3 {
            margin: 0 0 20px 0;
            color: #495057;
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .bill-to p, .invoice-meta p {
            margin: 8px 0;
            color: #666;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .items-table th, .items-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .items-table th {
            background: #667eea;
            color: white;
            font-weight: bold;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .items-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .text-right {
            text-align: right;
        }
        
        .totals-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 40px;
        }
        
        .totals-table {
            width: 350px;
            border-collapse: collapse;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .totals-table td {
            padding: 12px 20px;
            border-bottom: 1px solid #dee2e6;
            font-size: 14px;
        }
        
        .totals-table .label {
            font-weight: bold;
            color: #495057;
            background: #f8f9fa;
        }
        
        .totals-table .amount {
            text-align: right;
            font-weight: bold;
            color: #333;
        }
        
        .totals-table .total-row {
            background: #667eea;
            color: white;
            font-weight: bold;
            font-size: 16px;
        }
        
        .payments-section {
            margin-top: 40px;
            page-break-inside: avoid;
        }
        
        .payments-section h3 {
            color: #495057;
            margin-bottom: 20px;
            font-size: 20px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .payments-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .payments-table th, .payments-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .payments-table th {
            background: #28a745;
            color: white;
            font-weight: bold;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .footer {
            margin-top: 50px;
            padding-top: 30px;
            border-top: 2px solid #e9ecef;
            text-align: center;
            color: #6c757d;
            font-size: 14px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-paid { 
            background: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb;
        }
        .status-pending { 
            background: #fff3cd; 
            color: #856404; 
            border: 1px solid #ffeaa7;
        }
        .status-overdue { 
            background: #f8d7da; 
            color: #721c24; 
            border: 1px solid #f5c6cb;
        }
        
        /* Print styles */
        @media print {
            body {
                font-size: 12px;
            }
            
            .invoice-container {
                padding: 20px;
                max-width: 100%;
            }
            
            .header {
                page-break-inside: avoid;
            }
            
            .items-table, .payments-table {
                page-break-inside: avoid;
            }
            
            .footer {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="header">
            <div class="company-info">
                <?php if ($company): ?>
                    <h1><?php echo htmlspecialchars($company['company_name'] ?? 'Company'); ?></h1>
                    <?php if (!empty($company['address'])): ?>
                        <p><?php echo nl2br(htmlspecialchars($company['address'])); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($company['phone'])): ?>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($company['phone']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($company['email'])): ?>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($company['email']); ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <h1><?php echo APP_NAME; ?></h1>
                <?php endif; ?>
            </div>
            <div class="invoice-info">
                <h2>INVOICE</h2>
                <p><strong>Invoice #:</strong> <?php echo htmlspecialchars($invoice['invoice_number']); ?></p>
                <p><strong>Date:</strong> <?php echo $invoiceDate; ?></p>
                <p><strong>Due Date:</strong> <?php echo $dueDate; ?></p>
                <p><strong>Status:</strong> <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></p>
            </div>
        </div>
        
        <div class="invoice-details">
            <div class="bill-to">
                <h3>Bill To:</h3>
                <p><strong><?php echo htmlspecialchars(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')); ?></strong></p>
                <?php if (!empty($customer['customer_code'])): ?>
                    <p><strong>Customer Code:</strong> <?php echo htmlspecialchars($customer['customer_code']); ?></p>
                <?php endif; ?>
                <?php if (!empty($customer['address'])): ?>
                    <p><?php echo nl2br(htmlspecialchars($customer['address'])); ?></p>
                <?php endif; ?>
                <?php if (!empty($customer['phone'])): ?>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($customer['phone']); ?></p>
                <?php endif; ?>
                <?php if (!empty($customer['email'])): ?>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($customer['email']); ?></p>
                <?php endif; ?>
            </div>
            <div class="invoice-meta">
                <h3>Order Details:</h3>
                <p><strong>Order #:</strong> <?php echo htmlspecialchars($order['order_number'] ?? 'N/A'); ?></p>
                <p><strong>Order Date:</strong> <?php echo date('d/m/Y', strtotime($order['created_at'] ?? 'now')); ?></p>
                <?php if (!empty($order['delivery_date'])): ?>
                    <p><strong>Delivery Date:</strong> <?php echo date('d/m/Y', strtotime($order['delivery_date'])); ?></p>
                <?php endif; ?>
                <?php if (!empty($order['notes'])): ?>
                    <p><strong>Notes:</strong> <?php echo htmlspecialchars($order['notes']); ?></p>
                <?php endif; ?>
            </div>
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
                    <td class="text-right">₹<?php echo number_format($invoice['total_amount'], 2); ?></td>
                    <td class="text-right">₹<?php echo number_format($invoice['total_amount'], 2); ?></td>
                </tr>
            </tbody>
        </table>
        
        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td class="label">Subtotal:</td>
                    <td class="amount">₹<?php echo number_format($invoice['total_amount'], 2); ?></td>
                </tr>
                <tr>
                    <td class="label">Tax:</td>
                    <td class="amount">₹0.00</td>
                </tr>
                <tr>
                    <td class="label">Total Amount:</td>
                    <td class="amount">₹<?php echo number_format($invoice['total_amount'], 2); ?></td>
                </tr>
                <tr>
                    <td class="label">Paid Amount:</td>
                    <td class="amount">₹<?php echo number_format($invoice['paid_amount'], 2); ?></td>
                </tr>
                <tr class="total-row">
                    <td class="label">Balance Due:</td>
                    <td class="amount">₹<?php echo number_format($invoice['balance_amount'], 2); ?></td>
                </tr>
            </table>
        </div>
        
        <?php if (!empty($payments)): ?>
        <div class="payments-section">
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
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></td>
                        <td class="text-right">₹<?php echo number_format($payment['amount'], 2); ?></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                        <td><?php echo htmlspecialchars($payment['notes']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <div class="footer">
            <p><strong>Thank you for your business!</strong></p>
            <?php if ($company && !empty($company['company_name'])): ?>
                <p><?php echo htmlspecialchars($company['company_name']); ?> - <?php echo date('Y'); ?></p>
            <?php else: ?>
                <p><?php echo APP_NAME; ?> - <?php echo date('Y'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Auto-trigger print dialog when page loads
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
    <?php
}
?>
