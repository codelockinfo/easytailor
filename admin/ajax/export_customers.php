<?php
/**
 * Export Customers AJAX Endpoint
 * Tailoring Management System
 */

require_once '../../config/config.php';
require_once '../models/Customer.php';

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
    // Get all customers
    $customerModel = new Customer();
    $customers = $customerModel->getAllCustomers();
    
    // Set headers for Excel download
    $filename = 'customers_export_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    // Create Excel XML content
    $excelContent = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $excelContent .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
    $excelContent .= '<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">' . "\n";
    $excelContent .= '<Title>Customers Export</Title>' . "\n";
    $excelContent .= '<Author>Tailoring Management System</Author>' . "\n";
    $excelContent .= '<Created>' . date('c') . '</Created>' . "\n";
    $excelContent .= '</DocumentProperties>' . "\n";
    $excelContent .= '<Worksheet ss:Name="Customers">' . "\n";
    $excelContent .= '<Table>' . "\n";
    
    // Add headers
    $excelContent .= '<Row>' . "\n";
    $headers = ['Customer Code', 'First Name', 'Last Name', 'Email', 'Phone', 'Address', 'City', 'State', 'Postal Code', 'Date of Birth', 'Notes', 'Status', 'Created Date'];
    foreach ($headers as $header) {
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($header) . '</Data></Cell>' . "\n";
    }
    $excelContent .= '</Row>' . "\n";
    
    // Add data rows
    foreach ($customers as $customer) {
        $excelContent .= '<Row>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($customer['customer_code']) . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($customer['first_name']) . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($customer['last_name']) . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($customer['email'] ?: '') . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($customer['phone']) . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($customer['address'] ?: '') . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($customer['city'] ?: '') . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($customer['state'] ?: '') . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($customer['postal_code'] ?: '') . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($customer['date_of_birth'] ?: '') . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($customer['notes'] ?: '') . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars(ucfirst($customer['status'])) . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars(date('Y-m-d H:i:s', strtotime($customer['created_at']))) . '</Data></Cell>' . "\n";
        $excelContent .= '</Row>' . "\n";
    }
    
    $excelContent .= '</Table>' . "\n";
    $excelContent .= '</Worksheet>' . "\n";
    $excelContent .= '</Workbook>';
    
    // Set headers for Excel download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
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
