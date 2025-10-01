<?php
/**
 * AJAX Invoice Filter Endpoint
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

    require_once $rootDir . '/models/Invoice.php';
    require_once $rootDir . '/models/Order.php';
    require_once $rootDir . '/models/Customer.php';

    // Get filter parameters
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    $customer_id = $_GET['customer_id'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? RECORDS_PER_PAGE);

    $invoiceModel = new Invoice();
    $orderModel = new Order();
    $customerModel = new Customer();
    
    // Build conditions
    $conditions = [];
    if (!empty($status)) {
        $conditions['payment_status'] = $status;
    }
    if (!empty($customer_id)) {
        $conditions['customer_id'] = $customer_id;
    }
    
    // Get invoices
    $offset = ($page - 1) * $limit;
    $invoices = $invoiceModel->getInvoicesWithDetails($conditions, $limit, $offset);
    
    // If search is provided, filter results manually
    if (!empty($search)) {
        $searchLower = strtolower($search);
        $invoices = array_filter($invoices, function($invoice) use ($searchLower) {
            return strpos(strtolower($invoice['invoice_number']), $searchLower) !== false ||
                   strpos(strtolower($invoice['customer_name'] ?? ''), $searchLower) !== false ||
                   strpos(strtolower($invoice['customer_phone'] ?? ''), $searchLower) !== false;
        });
    }
    
    $totalInvoices = count($invoices);
    $totalPages = ceil($totalInvoices / $limit);
    
    // Get filter options
    $customers = $customerModel->findAll(['status' => 'active'], 'first_name, last_name');
    
    // Format invoices for display - match original table structure exactly
    $formattedInvoices = [];
    foreach ($invoices as $invoice) {
        $formattedInvoices[] = [
            'id' => $invoice['id'],
            'invoice_number' => htmlspecialchars($invoice['invoice_number']),
            'customer_name' => htmlspecialchars($invoice['customer_name'] ?? ''),
            'customer_phone' => htmlspecialchars($invoice['customer_phone'] ?? ''),
            'order_number' => htmlspecialchars($invoice['order_number'] ?? ''),
            'invoice_date' => $invoice['invoice_date'],
            'due_date' => $invoice['due_date'],
            'payment_status' => $invoice['payment_status'],
            'subtotal' => $invoice['subtotal'],
            'tax_amount' => $invoice['tax_amount'],
            'discount_amount' => $invoice['discount_amount'],
            'total_amount' => $invoice['total_amount'],
            'paid_amount' => $invoice['paid_amount'],
            'balance_amount' => $invoice['balance_amount'],
            'created_at' => $invoice['created_at']
        ];
    }
    
    // Format filter options
    $filterOptions = [
        'customers' => array_map(function($customer) {
            return [
                'id' => $customer['id'],
                'name' => htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']),
                'phone' => htmlspecialchars($customer['phone'] ?? '')
            ];
        }, $customers)
    ];
    
    echo json_encode([
        'success' => true,
        'invoices' => $formattedInvoices,
        'filter_options' => $filterOptions,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_invoices' => $totalInvoices,
            'has_previous' => $page > 1,
            'has_next' => $page < $totalPages
        ]
    ]);
    
} catch (Exception $e) {
    // Clear any output
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Filter failed: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    // Clear any output
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Filter failed: ' . $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();
