<?php
/**
 * AJAX Invoice Filter Endpoint
 * Tailoring Management System
 */

try {
    // Get the directory of this script
    $scriptDir = dirname(__FILE__);
    $rootDir = dirname($scriptDir);
    
    require_once $rootDir . '/../config/config.php';
    
    // Enable error reporting after config is loaded (disabled for production)
    // error_reporting(E_ALL);
    // ini_set('display_errors', 1);

    // Set content type to JSON
    header('Content-Type: application/json');

    // Start output buffering to catch any errors
    ob_start();

    // Check if user is logged in
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    require_once $rootDir . '/../models/Invoice.php';
    require_once $rootDir . '/../models/Order.php';
    require_once $rootDir . '/../models/Customer.php';

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
        $conditions['i.payment_status'] = $status;
    }
    if (!empty($customer_id)) {
        $conditions['c.id'] = $customer_id;
    }
    
    // Get invoices with details - this method already includes joins and company_id filtering
    $offset = ($page - 1) * $limit;
    
    try {
        // If limit is 0, we only need filter options, not invoice data
        if ($limit == 0) {
            $invoices = [];
            $totalInvoices = 0;
        } else {
            // Get invoices with search filter applied in SQL
            // Pass empty string if search is empty to avoid issues
            $searchParam = !empty($search) ? trim($search) : null;
            
            // Get total count - fetch all without limit to count
            $allInvoices = $invoiceModel->getInvoicesWithDetails($conditions, null, 0, $searchParam);
            $totalInvoices = count($allInvoices);
            
            // Then get paginated results
            $invoices = $invoiceModel->getInvoicesWithDetails($conditions, $limit, $offset, $searchParam);
        }
    } catch (Exception $e) {
        throw new Exception("Failed to fetch invoices: " . $e->getMessage());
    }
    
    $totalPages = ($limit > 0) ? ceil($totalInvoices / $limit) : 1;
    
    // Get filter options
    $customers = $customerModel->findAll(['status' => 'active'], 'first_name, last_name');
    
    // Format invoices for display - match original table structure exactly
    $formattedInvoices = [];
    foreach ($invoices as $invoice) {
        $customerName = trim(($invoice['first_name'] ?? '') . ' ' . ($invoice['last_name'] ?? ''));
        $formattedInvoices[] = [
            'id' => $invoice['id'],
            'invoice_number' => htmlspecialchars($invoice['invoice_number']),
            'customer_name' => htmlspecialchars($customerName ?: 'N/A'),
            'customer_name_raw' => $customerName ?: 'N/A',
            'customer_code' => htmlspecialchars($invoice['customer_code'] ?? ''),
            'customer_phone' => htmlspecialchars($invoice['customer_phone'] ?? 'N/A'),
            'order_id' => $invoice['order_id'],
            'order_number' => htmlspecialchars($invoice['order_number'] ?? 'N/A'),
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
        'error' => 'Filter failed: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
} catch (Error $e) {
    // Clear any output
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

// End output buffering
ob_end_flush();
