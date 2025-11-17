<?php
/**
 * AJAX endpoint for filtering reports data
 * Tailoring Management System
 */

// Start output buffering to catch any errors
ob_start();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // Get the directory of this script
    $scriptDir = dirname(__FILE__);
    $rootDir = dirname($scriptDir);
    
    require_once $rootDir . '/../config/config.php';
    
    // Set JSON header after config is loaded
    header('Content-Type: application/json');
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Configuration error: ' . $e->getMessage()]);
    exit;
} catch (Error $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Fatal configuration error: ' . $e->getMessage()]);
    exit;
}

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Get POST data
    $date_from = $_POST['date_from'] ?? date('Y-m-01');
    $date_to = $_POST['date_to'] ?? date('Y-m-d');
    
    // Validate date format
    if (!strtotime($date_from) || !strtotime($date_to)) {
        throw new Exception('Invalid date format');
    }
    
    // Ensure date_from is not after date_to
    if (strtotime($date_from) > strtotime($date_to)) {
        throw new Exception('From date cannot be after to date');
    }
    
    require_once $rootDir . '/models/Order.php';
    require_once $rootDir . '/models/Invoice.php';
    require_once $rootDir . '/models/Expense.php';
    require_once $rootDir . '/models/Customer.php';
    require_once $rootDir . '/../config/database.php';
    
    // Clear any output buffer content
    ob_clean();
    
    try {
        $orderModel = new Order();
        $invoiceModel = new Invoice();
        $expenseModel = new Expense();
        $customerModel = new Customer();
    } catch (Exception $e) {
        throw new Exception('Failed to initialize models: ' . $e->getMessage());
    }
    
    // Get statistics (filtered by date range using conditions)
    // Note: These methods will automatically filter by company_id
    $orderStats = $orderModel->getOrderStats();
    $invoiceStats = $invoiceModel->getInvoiceStats();
    $expenseStats = $expenseModel->getExpenseStats();
    $customerStats = $customerModel->getCustomerStats();
    
    // Get orders within date range for filtering (using direct query)
    $companyId = get_company_id();
    // Create a new database connection for direct queries
    $database = new Database();
    $conn = $database->getConnection();
    
    $ordersQuery = "SELECT o.*, 
                         c.first_name, c.last_name, c.customer_code, c.phone as customer_phone,
                         ct.name as cloth_type_name,
                         u.full_name as tailor_name
                  FROM orders o
                  LEFT JOIN customers c ON o.customer_id = c.id
                  LEFT JOIN cloth_types ct ON o.cloth_type_id = ct.id
                  LEFT JOIN users u ON o.assigned_tailor_id = u.id
                  WHERE o.order_date >= :date_from AND o.order_date <= :date_to";
    if ($companyId) {
        $ordersQuery .= " AND o.company_id = :company_id";
    }
    $ordersQuery .= " ORDER BY o.created_at DESC LIMIT 1000";
    
    $ordersStmt = $conn->prepare($ordersQuery);
    $ordersStmt->bindParam(':date_from', $date_from);
    $ordersStmt->bindParam(':date_to', $date_to);
    if ($companyId) {
        $ordersStmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
    }
    $ordersStmt->execute();
    $ordersInRange = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filter order stats by date range
    $filteredOrderStats = [
        'total' => 0,
        'pending' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'delivered' => 0,
        'cancelled' => 0
    ];
    
    foreach ($ordersInRange as $order) {
        $filteredOrderStats['total']++;
        $status = $order['status'] ?? 'pending';
        if (isset($filteredOrderStats[$status])) {
            $filteredOrderStats[$status]++;
        }
    }
    
    // Get invoices within date range (using direct query)
    $invoicesQuery = "SELECT i.*, 
                         o.order_number, o.order_date,
                         c.first_name, c.last_name, c.customer_code
                  FROM invoices i
                  LEFT JOIN orders o ON i.order_id = o.id
                  LEFT JOIN customers c ON o.customer_id = c.id
                  WHERE DATE(i.created_at) >= :date_from AND DATE(i.created_at) <= :date_to";
    if ($companyId) {
        $invoicesQuery .= " AND i.company_id = :company_id";
    }
    $invoicesQuery .= " ORDER BY i.created_at DESC LIMIT 1000";
    
    $invoicesStmt = $conn->prepare($invoicesQuery);
    $invoicesStmt->bindParam(':date_from', $date_from);
    $invoicesStmt->bindParam(':date_to', $date_to);
    if ($companyId) {
        $invoicesStmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
    }
    $invoicesStmt->execute();
    $invoicesInRange = $invoicesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filter invoice stats by date range
    $filteredInvoiceStats = [
        'total' => count($invoicesInRange),
        'paid' => 0,
        'partial' => 0,
        'due' => 0,
        'total_amount' => 0
    ];
    
    foreach ($invoicesInRange as $invoice) {
        $filteredInvoiceStats['total_amount'] += floatval($invoice['total_amount'] ?? 0);
        $paymentStatus = $invoice['payment_status'] ?? 'due';
        if (isset($filteredInvoiceStats[$paymentStatus])) {
            $filteredInvoiceStats[$paymentStatus]++;
        }
    }
    
    // Get expenses within date range
    $expensesInRange = $expenseModel->getExpensesByDateRange($date_from, $date_to);
    
    // Filter expense stats by date range
    $filteredExpenseStats = [
        'total' => count($expensesInRange),
        'total_amount' => 0,
        'by_category' => [],
        'by_payment_method' => []
    ];
    
    $categoryTotals = [];
    $methodTotals = [];
    
    foreach ($expensesInRange as $expense) {
        $filteredExpenseStats['total_amount'] += floatval($expense['amount'] ?? 0);
        
        $category = $expense['category'] ?? 'Other';
        if (!isset($categoryTotals[$category])) {
            $categoryTotals[$category] = ['count' => 0, 'total' => 0];
        }
        $categoryTotals[$category]['count']++;
        $categoryTotals[$category]['total'] += floatval($expense['amount'] ?? 0);
        
        $method = $expense['payment_method'] ?? 'cash';
        if (!isset($methodTotals[$method])) {
            $methodTotals[$method] = ['count' => 0, 'total' => 0];
        }
        $methodTotals[$method]['count']++;
        $methodTotals[$method]['total'] += floatval($expense['amount'] ?? 0);
    }
    
    foreach ($categoryTotals as $cat => $data) {
        $filteredExpenseStats['by_category'][] = [
            'category' => $cat,
            'count' => $data['count'],
            'total' => $data['total']
        ];
    }
    
    foreach ($methodTotals as $method => $data) {
        $filteredExpenseStats['by_payment_method'][] = [
            'payment_method' => $method,
            'count' => $data['count'],
            'total' => $data['total']
        ];
    }
    
    // Get customers created within date range (using direct query)
    $customersQuery = "SELECT * FROM customers 
                      WHERE DATE(created_at) >= :date_from 
                      AND DATE(created_at) <= :date_to";
    if ($companyId) {
        $customersQuery .= " AND company_id = :company_id";
    }
    
    $customersStmt = $conn->prepare($customersQuery);
    $customersStmt->bindParam(':date_from', $date_from);
    $customersStmt->bindParam(':date_to', $date_to);
    if ($companyId) {
        $customersStmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
    }
    $customersStmt->execute();
    $customersInRange = $customersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $filteredCustomerStats = [
        'total' => count($customersInRange),
        'this_month' => 0
    ];
    
    $thisMonthStart = date('Y-m-01');
    foreach ($customersInRange as $customer) {
        if (strpos($customer['created_at'], $thisMonthStart) === 0) {
            $filteredCustomerStats['this_month']++;
        }
    }
    
    // Get monthly data for charts within the date range
    $monthlyRevenue = [];
    $monthlyExpenses = [];
    
    // Calculate months between date_from and date_to (limit to 12 months)
    $start = new DateTime($date_from);
    $end = new DateTime($date_to);
    $interval = new DateInterval('P1M');
    $period = new DatePeriod($start, $interval, $end->modify('+1 month'));
    
    $monthCount = 0;
    foreach ($period as $date) {
        if ($monthCount >= 12) break; // Limit to 12 months
        
        $year = $date->format('Y');
        $month = $date->format('m');
        
        // Get revenue for this month (filtered by date range)
        $monthStart = $date->format('Y-m-01');
        $monthEnd = $date->format('Y-m-t');
        
        // Get orders for this month using direct query
        $monthStartDate = max($monthStart, $date_from);
        $monthEndDate = min($monthEnd, $date_to);
        
        $monthOrdersQuery = "SELECT o.* FROM orders o
                            WHERE o.order_date >= :month_start 
                            AND o.order_date <= :month_end
                            AND o.status IN ('completed', 'delivered')";
        if ($companyId) {
            $monthOrdersQuery .= " AND o.company_id = :company_id";
        }
        $monthOrdersQuery .= " LIMIT 1000";
        
        $monthOrdersStmt = $conn->prepare($monthOrdersQuery);
        $monthOrdersStmt->bindParam(':month_start', $monthStartDate);
        $monthOrdersStmt->bindParam(':month_end', $monthEndDate);
        if ($companyId) {
            $monthOrdersStmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $monthOrdersStmt->execute();
        $monthOrders = $monthOrdersStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $revenue = 0;
        foreach ($monthOrders as $order) {
            $revenue += floatval($order['total_amount'] ?? 0);
        }
        
        // Get expenses for this month
        $monthExpenses = $expenseModel->getExpensesByDateRange(
            max($monthStart, $date_from),
            min($monthEnd, $date_to)
        );
        
        $expenseTotal = 0;
        foreach ($monthExpenses as $exp) {
            $expenseTotal += floatval($exp['amount'] ?? 0);
        }
        
        $monthlyRevenue[] = [
            'month' => $date->format('M Y'),
            'revenue' => $revenue
        ];
        
        $monthlyExpenses[] = [
            'month' => $date->format('M Y'),
            'expenses' => $expenseTotal
        ];
        
        $monthCount++;
    }
    
    // Get recent orders for the date range (limit to 5)
    $recentOrders = array_slice($ordersInRange, 0, 5);
    
    // Format recent orders for display
    $formattedOrders = [];
    foreach ($recentOrders as $order) {
        $formattedOrders[] = [
            'order_number' => htmlspecialchars($order['order_number']),
            'customer_name' => htmlspecialchars($order['first_name'] . ' ' . $order['last_name']),
            'total_amount' => format_currency($order['total_amount']),
            'status' => ucfirst(str_replace('_', ' ', $order['status'])),
            'status_badge' => match($order['status']) {
                'pending' => 'warning',
                'in_progress' => 'info',
                'completed' => 'success',
                'delivered' => 'primary',
                default => 'secondary'
            }
        ];
    }
    
    // Format expense categories for display (use filtered stats)
    $formattedExpenseCategories = [];
    if (!empty($filteredExpenseStats['by_category'])) {
        foreach (array_slice($filteredExpenseStats['by_category'], 0, 5) as $category) {
            $formattedExpenseCategories[] = [
                'category' => htmlspecialchars($category['category']),
                'count' => $category['count'],
                'total' => format_currency($category['total'])
            ];
        }
    }
    
    // Prepare response data
    $response = [
        'success' => true,
        'data' => [
            'stats' => [
                'customers' => [
                    'total' => number_format($filteredCustomerStats['total']),
                    'this_month' => $filteredCustomerStats['this_month']
                ],
                'revenue' => [
                    'total_amount' => format_currency($filteredInvoiceStats['total_amount']),
                    'paid' => $filteredInvoiceStats['paid']
                ],
                'expenses' => [
                    'total_amount' => format_currency($filteredExpenseStats['total_amount']),
                    'total' => $filteredExpenseStats['total']
                ],
                'profit' => [
                    'net_profit' => format_currency($filteredInvoiceStats['total_amount'] - $filteredExpenseStats['total_amount'])
                ]
            ],
            'charts' => [
                'revenue_expense' => [
                    'labels' => array_column($monthlyRevenue, 'month'),
                    'revenue_data' => array_column($monthlyRevenue, 'revenue'),
                    'expense_data' => array_column($monthlyExpenses, 'expenses')
                ],
                'order_status' => [
                    'pending' => $filteredOrderStats['pending'],
                    'in_progress' => $filteredOrderStats['in_progress'],
                    'completed' => $filteredOrderStats['completed'],
                    'delivered' => $filteredOrderStats['delivered'],
                    'cancelled' => $filteredOrderStats['cancelled']
                ],
                'expense_categories' => [
                    'labels' => !empty($filteredExpenseStats['by_category']) ? array_column($filteredExpenseStats['by_category'], 'category') : [],
                    'data' => !empty($filteredExpenseStats['by_category']) ? array_column($filteredExpenseStats['by_category'], 'total') : []
                ],
                'payment_methods' => [
                    'labels' => !empty($filteredExpenseStats['by_payment_method']) ? array_column($filteredExpenseStats['by_payment_method'], 'payment_method') : [],
                    'data' => !empty($filteredExpenseStats['by_payment_method']) ? array_column($filteredExpenseStats['by_payment_method'], 'total') : []
                ]
            ],
            'tables' => [
                'recent_orders' => $formattedOrders,
                'expense_categories' => $formattedExpenseCategories
            ]
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    error_log('Filter reports error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => 'Filter failed: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    ob_clean();
    http_response_code(500);
    error_log('Filter reports fatal error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();
?>
