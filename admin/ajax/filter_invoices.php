<?php
try {
    $scriptDir = dirname(__FILE__);
    $rootDir = dirname($scriptDir);
    require_once $rootDir . '/../config/config.php';
    header('Content-Type: application/json');
    ob_start();
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    require_once $rootDir . '/../models/Invoice.php';
    require_once $rootDir . '/../models/Order.php';
    require_once $rootDir . '/../models/Customer.php';
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    $customer_id = $_GET['customer_id'] ?? '';
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? RECORDS_PER_PAGE);
    $invoiceModel = new Invoice();
    $orderModel = new Order();
    $customerModel = new Customer();
    $conditions = [];
    if (!empty($status)) {
        $conditions['i.payment_status'] = $status;
    }
    if (!empty($customer_id)) {
        $conditions['c.id'] = $customer_id;
    }
    if (!empty($start_date)) {
        $conditions['start_date'] = $start_date;
    }
    if (!empty($end_date)) {
        $conditions['end_date'] = $end_date;
    }
    $offset = ($page - 1) * $limit;
    
    try {
        if ($limit == 0) {
            $invoices = [];
            $totalInvoices = 0;
        } else {
            $searchParam = !empty($search) ? trim($search) : null;
            $allInvoices = $invoiceModel->getInvoicesWithDetails($conditions, null, 0, $searchParam);
            $totalInvoices = count($allInvoices);
            $invoices = $invoiceModel->getInvoicesWithDetails($conditions, $limit, $offset, $searchParam);
        }
    } catch (Exception $e) {
        throw new Exception("Failed to fetch invoices: " . $e->getMessage());
    }
    
    $totalPages = ($limit > 0) ? ceil($totalInvoices / $limit) : 1;
    $customers = $customerModel->findAll(['status' => 'active'], 'first_name, last_name');
    $formattedInvoices = [];
    foreach ($invoices as $invoice) {
        $standardName = trim(($invoice['first_name'] ?? '') . ' ' . ($invoice['last_name'] ?? ''));
        $customerName = !empty($invoice['measurement_customer_name']) ? $invoice['measurement_customer_name'] : $standardName;
        $formattedInvoices[] = [
            'id' => $invoice['id'],
            'invoice_number' => $invoice['invoice_number'],
            'customer_name' => $customerName ?: 'N/A',
            'customer_name_raw' => $customerName ?: 'N/A',
            'customer_code' => $invoice['customer_code'] ?? '',
            'customer_phone' => $invoice['customer_phone'] ?? 'N/A',
            'order_id' => $invoice['order_id'],
            'order_number' => $invoice['order_number'] ?? 'N/A',
            'invoice_date' => $invoice['invoice_date'],
            'due_date' => $invoice['due_date'],
            'payment_status' => $invoice['payment_status'],
            'subtotal' => $invoice['subtotal'],
            'tax_rate' => $invoice['tax_rate'],
            'tax_amount' => $invoice['tax_amount'],
            'discount_amount' => $invoice['discount_amount'],
            'total_amount' => $invoice['total_amount'],
            'paid_amount' => $invoice['paid_amount'],
            'balance_amount' => $invoice['balance_amount'],
            'notes' => $invoice['notes'] ?? '',
            'created_at' => $invoice['created_at']
        ];
    }
    $filterOptions = [
        'customers' => array_map(function($customer) {
            return [
                'id' => $customer['id'],
                'name' => $customer['first_name'] . ' ' . $customer['last_name'],
                'phone' => $customer['phone'] ?? ''
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
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Filter failed: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
} catch (Error $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Filter failed: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
ob_end_flush();