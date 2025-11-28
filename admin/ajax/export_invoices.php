<?php
/**
 * Export Invoices AJAX Endpoint
 * Tailoring Management System
 */

require_once '../../config/config.php';
require_once '../models/Invoice.php';
require_once '../models/Order.php';
require_once '../models/Customer.php';
require_once '../models/Payment.php';

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check if user has admin role
if (!has_role('admin')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Verify CSRF token
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

try {
    // Get all invoices with related data
    $invoiceModel = new Invoice();
    $orderModel = new Order();
    $customerModel = new Customer();
    $paymentModel = new Payment();
    
    // Get all invoices
    $invoices = $invoiceModel->getAllInvoices();
    
    // Set headers for Excel download
    $filename = 'invoices_export_' . date('Y-m-d_H-i-s') . '.xls';
    
    // Create Excel XML content
    $excelContent = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $excelContent .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
    $excelContent .= '<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">' . "\n";
    $excelContent .= '<Title>Invoices Export</Title>' . "\n";
    $excelContent .= '<Author>Tailoring Management System</Author>' . "\n";
    $excelContent .= '<Created>' . date('c') . '</Created>' . "\n";
    $excelContent .= '</DocumentProperties>' . "\n";
    $excelContent .= '<Worksheet ss:Name="Invoices">' . "\n";
    $excelContent .= '<Table>' . "\n";
    
    // Add headers
    $excelContent .= '<Row>' . "\n";
    $headers = [
        'Invoice Number', 'Invoice Date', 'Due Date', 'Customer Name', 'Customer Phone', 'Customer Email',
        'Order Number', 'Order Date', 'Cloth Type', 'Order Status', 'Subtotal', 'Tax Rate (%)', 'Tax Amount',
        'Discount Amount', 'Total Amount', 'Paid Amount', 'Balance Amount', 'Payment Status', 'Notes', 'Created Date'
    ];
    foreach ($headers as $header) {
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($header) . '</Data></Cell>' . "\n";
    }
    $excelContent .= '</Row>' . "\n";
    
    // Add data rows
    foreach ($invoices as $invoice) {
        // Get order details
        $order = $orderModel->getOrderById($invoice['order_id']);
        
        // Get customer details
        $customer = $customerModel->getCustomerById($order['customer_id']);
        
        // Get cloth type name
        $clothType = $orderModel->getClothTypeById($order['cloth_type_id']);
        
        $excelContent .= '<Row>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($invoice['invoice_number']) . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($invoice['invoice_date']) . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($invoice['due_date']) . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($customer['phone']) . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($customer['email'] ?: '') . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($order['order_number']) . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($order['order_date']) . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($clothType['name']) . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars(ucfirst(str_replace('_', ' ', $order['status']))) . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="Number">' . $invoice['subtotal'] . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="Number">' . $invoice['tax_rate'] . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="Number">' . $invoice['tax_amount'] . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="Number">' . $invoice['discount_amount'] . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="Number">' . $invoice['total_amount'] . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="Number">' . $invoice['paid_amount'] . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="Number">' . $invoice['balance_amount'] . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars(ucfirst($invoice['payment_status'])) . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($invoice['notes'] ?: '') . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars(date('Y-m-d H:i:s', strtotime($invoice['created_at']))) . '</Data></Cell>' . "\n";
        $excelContent .= '</Row>' . "\n";
    }
    
    $excelContent .= '</Table>' . "\n";
    $excelContent .= '</Worksheet>' . "\n";
    $excelContent .= '</Workbook>';
    
    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    header('Content-Length: ' . strlen($excelContent));
    
    // Output Excel content
    echo $excelContent;
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Export failed: ' . $e->getMessage()]);
}
?>
