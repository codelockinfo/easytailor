<?php
/**
 * Export Orders AJAX Endpoint
 * Tailoring Management System
 */

require_once '../../config/config.php';
require_once '../models/Order.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!has_role('admin')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

try {
    $orderModel = new Order();
    $orders = $orderModel->getOrdersWithDetails();

    $filename = 'orders_export_' . date('Y-m-d_H-i-s') . '.xls';

    $excelContent = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $excelContent .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
    $excelContent .= '<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">' . "\n";
    $excelContent .= '<Title>Orders Export</Title>' . "\n";
    $excelContent .= '<Author>Tailoring Management System</Author>' . "\n";
    $excelContent .= '<Created>' . date('c') . '</Created>' . "\n";
    $excelContent .= '</DocumentProperties>' . "\n";
    $excelContent .= '<Worksheet ss:Name="Orders">' . "\n";
    $excelContent .= '<Table>' . "\n";

    $excelContent .= '<Row>' . "\n";
    $headers = [
        'Order Number', 'Order Date', 'Due Date', 'Delivery Date',
        'Customer Name', 'Customer Code', 'Customer Phone', 'Customer Email',
        'Cloth Type', 'Assigned Tailor', 'Status',
        'Total Amount', 'Advance Amount', 'Balance Amount',
        'Special Instructions', 'Created By', 'Created Date'
    ];
    foreach ($headers as $header) {
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($header) . '</Data></Cell>' . "\n";
    }
    $excelContent .= '</Row>' . "\n";

    foreach ($orders as $order) {
        $customerName = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
        $excelContent .= '<Row>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($order['order_number']) . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($order['order_date']) . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($order['due_date']) . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($order['delivery_date'] ?? '') . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($customerName) . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($order['customer_code'] ?? '') . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($order['customer_phone'] ?? '') . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($order['customer_email'] ?? '') . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($order['cloth_type_name'] ?? '') . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($order['tailor_name'] ?? 'Unassigned') . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars(ucfirst(str_replace('_', ' ', $order['status']))) . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="Number">' . $order['total_amount'] . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="Number">' . ($order['advance_amount'] ?? 0) . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="Number">' . $order['balance_amount'] . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($order['special_instructions'] ?? '') . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars($order['created_by_name'] ?? '') . '</Data></Cell>' . "\n";
        $excelContent .= '<Cell><Data ss:Type="String">' . htmlspecialchars(date('Y-m-d H:i:s', strtotime($order['created_at']))) . '</Data></Cell>' . "\n";
        $excelContent .= '</Row>' . "\n";
    }

    $excelContent .= '</Table>' . "\n";
    $excelContent .= '</Worksheet>' . "\n";
    $excelContent .= '</Workbook>';

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    echo $excelContent;
    exit;
} catch (Exception $e) {
    error_log('Order export failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to export orders']);
    exit;
}

