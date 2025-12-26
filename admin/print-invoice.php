<!-- Favicon - Primary ICO format for Google Search -->
<link rel="icon" type="image/x-icon" href="../favicon.ico" sizes="16x16 32x32 48x48">
<!-- Favicon - PNG fallback -->
<link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon(2).png">
<link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon(2).png">
<!-- Apple Touch Icon -->
<link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon(2).png">

<?php
/**
 * Print Invoice Page
 * Tailoring Management System
 */

require_once '../config/config.php';
require_login();

require_once 'models/Invoice.php';
require_once 'models/Order.php';
require_once 'models/Payment.php';
require_once 'models/Company.php';

$invoiceId = (int)($_GET['id'] ?? 0);

if (!$invoiceId) {
    die('Invalid invoice ID');
}

$invoiceModel = new Invoice();
$orderModel = new Order();
$paymentModel = new Payment();
$companyModel = new Company();

// Get invoice with details
$invoices = $invoiceModel->getInvoicesWithDetails(['i.id' => $invoiceId], 1);

if (empty($invoices)) {
    die('Invoice not found');
}

$invoice = $invoices[0];

// Get order details
$order = $orderModel->find($invoice['order_id']);

// Get payments for this invoice
$payments = $paymentModel->getInvoicePayments($invoiceId);

// Get company details
$companyId = get_company_id();
$company = $companyId ? $companyModel->find($companyId) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?> - Print</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                margin: 0;
                padding: 15px;
            }
            .print-page {
                page-break-after: always;
            }
        }
        
        body {
            background: #f8f9fa;
            font-family: 'Arial', sans-serif;
        }
        
        .invoice-container {
            background: white;
            max-width: 900px;
            margin: 20px auto;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .invoice-header {
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .company-logo {
            max-height: 80px;
            max-width: 200px;
        }
        
        .invoice-title {
            font-size: 2.5rem;
            color: #667eea;
            font-weight: bold;
        }
        
        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .table-invoice {
            margin-top: 30px;
        }
        
        .table-invoice th {
            background: #667eea;
            color: white;
        }
        
        .total-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .payment-status {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
        }
        
        .status-paid { background: #d4edda; color: #155724; }
        .status-partial { background: #fff3cd; color: #856404; }
        .status-due { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Print Button -->
        <div class="no-print text-end mb-3">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print me-2"></i>Print Invoice
            </button>
            <button onclick="window.close()" class="btn btn-secondary ms-2">
                <i class="fas fa-times me-2"></i>Close
            </button>
        </div>
        
        <!-- Invoice Header -->
        <div class="invoice-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <?php if ($company && !empty($company['logo']) && file_exists($company['logo'])): ?>
                        <img src="<?php echo htmlspecialchars($company['logo']); ?>" alt="Company Logo" class="company-logo mb-2">
                    <?php endif; ?>
                    <h4 class="mb-1"><?php echo htmlspecialchars($company['company_name'] ?? 'Tailoring Management'); ?></h4>
                    <?php if ($company): ?>
                        <p class="mb-0">
                            <?php if (!empty($company['business_address'])): ?>
                                <?php echo nl2br(htmlspecialchars($company['business_address'])); ?><br>
                            <?php endif; ?>
                            <?php if (!empty($company['city'])): ?>
                                <?php echo htmlspecialchars($company['city']); ?>
                                <?php if (!empty($company['state'])): ?>, <?php echo htmlspecialchars($company['state']); ?><?php endif; ?>
                                <?php if (!empty($company['postal_code'])): ?> - <?php echo htmlspecialchars($company['postal_code']); ?><?php endif; ?>
                                <br>
                            <?php endif; ?>
                            <?php if (!empty($company['business_phone'])): ?>
                                Phone: <?php echo htmlspecialchars($company['business_phone']); ?><br>
                            <?php endif; ?>
                            <?php if (!empty($company['business_email'])): ?>
                                Email: <?php echo htmlspecialchars($company['business_email']); ?>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 text-end">
                    <div class="invoice-title">INVOICE</div>
                    <h5 class="mb-1"><?php echo htmlspecialchars($invoice['invoice_number']); ?></h5>
                    <p class="mb-0">
                        Date: <strong><?php echo format_date($invoice['invoice_date'], 'M j, Y'); ?></strong><br>
                        Due Date: <strong><?php echo format_date($invoice['due_date'], 'M j, Y'); ?></strong>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Bill To & Order Info -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="info-box">
                    <h6 class="text-muted mb-2">BILL TO:</h6>
                    <h5 class="mb-1"><?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?></h5>
                    <p class="mb-0">
                        Customer Code: <strong><?php echo htmlspecialchars($invoice['customer_code']); ?></strong><br>
                        <?php if (!empty($invoice['customer_phone'])): ?>
                            Phone: <?php echo htmlspecialchars($invoice['customer_phone']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($invoice['customer_email'])): ?>
                            Email: <?php echo htmlspecialchars($invoice['customer_email']); ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="info-box">
                    <h6 class="text-muted mb-2">ORDER DETAILS:</h6>
                    <p class="mb-0">
                        Order Number: <strong><?php echo htmlspecialchars($invoice['order_number']); ?></strong><br>
                        Order Date: <?php echo format_date($invoice['order_date'], 'M j, Y'); ?><br>
                        <?php if (!empty($invoice['cloth_type_name'])): ?>
                            Cloth Type: <?php echo htmlspecialchars($invoice['cloth_type_name']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($invoice['created_by_name'])): ?>
                            Created By: <?php echo htmlspecialchars($invoice['created_by_name']); ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Invoice Items -->
        <table class="table table-bordered table-invoice">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-end" width="120">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>Tailoring Services</strong><br>
                        <small class="text-muted">
                            Order: <?php echo htmlspecialchars($invoice['order_number']); ?>
                            <?php if (!empty($invoice['cloth_type_name'])): ?>
                                - <?php echo htmlspecialchars($invoice['cloth_type_name']); ?>
                            <?php endif; ?>
                        </small>
                    </td>
                    <td class="text-end"><?php echo format_currency($invoice['subtotal']); ?></td>
                </tr>
            </tbody>
        </table>
        
        <!-- Totals -->
        <div class="row">
            <div class="col-md-6">
                <?php if (!empty($invoice['notes'])): ?>
                    <div class="info-box">
                        <h6 class="text-muted mb-2">NOTES:</h6>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <div class="total-section">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td><strong>Subtotal:</strong></td>
                            <td class="text-end"><?php echo format_currency($invoice['subtotal']); ?></td>
                        </tr>
                        <?php if ($invoice['tax_amount'] > 0): ?>
                        <tr>
                            <td>Tax (<?php echo number_format($invoice['tax_rate'], 2); ?>%):</td>
                            <td class="text-end"><?php echo format_currency($invoice['tax_amount']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($invoice['discount_amount'] > 0): ?>
                        <tr>
                            <td>Discount:</td>
                            <td class="text-end text-danger">-<?php echo format_currency($invoice['discount_amount']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr style="border-top: 2px solid #dee2e6;">
                            <td><strong>Total Amount:</strong></td>
                            <td class="text-end"><h5 class="mb-0"><?php echo format_currency($invoice['total_amount']); ?></h5></td>
                        </tr>
                        <tr class="text-success">
                            <td><strong>Paid Amount:</strong></td>
                            <td class="text-end"><strong><?php echo format_currency($invoice['paid_amount']); ?></strong></td>
                        </tr>
                        <tr class="text-danger">
                            <td><strong>Balance Due:</strong></td>
                            <td class="text-end"><h5 class="mb-0"><?php echo format_currency($invoice['balance_amount']); ?></h5></td>
                        </tr>
                    </table>
                </div>
                
                <div class="text-end mt-3">
                    <span class="payment-status status-<?php echo $invoice['payment_status']; ?>">
                        <?php echo strtoupper($invoice['payment_status']); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Payment History -->
        <?php if (!empty($payments)): ?>
        <div class="mt-4">
            <h5 class="mb-3">Payment History</h5>
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?php echo format_date($payment['payment_date'], 'M j, Y'); ?></td>
                        <td><strong><?php echo format_currency($payment['amount']); ?></strong></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                        <td><?php echo htmlspecialchars($payment['reference_number'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($payment['notes'] ?? '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="mt-5 pt-4" style="border-top: 1px solid #dee2e6;">
            <div class="row">
                <div class="col-md-6">
                    <small class="text-muted">
                        <strong>Terms & Conditions:</strong><br>
                        Payment is due within the specified due date.<br>
                        Late payments may incur additional charges.
                    </small>
                </div>
                <div class="col-md-6 text-end">
                    <small class="text-muted">
                        Thank you for your business!<br>
                        <strong><?php echo htmlspecialchars($company['company_name'] ?? 'Tailoring Management System'); ?></strong>
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-print on load (optional)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>

