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
    require_once '../config/config.php';
    
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
    
    require_once '../models/Order.php';
    require_once '../models/Invoice.php';
    require_once '../models/Expense.php';
    require_once '../models/Customer.php';
    
    // Clear any output buffer content
    ob_clean();
    
    $orderModel = new Order();
    $invoiceModel = new Invoice();
    $expenseModel = new Expense();
    $customerModel = new Customer();
    
    // Get statistics for the date range
    $orderStats = $orderModel->getOrderStatsByDateRange($date_from, $date_to);
    $invoiceStats = $invoiceModel->getInvoiceStatsByDateRange($date_from, $date_to);
    $expenseStats = $expenseModel->getExpenseStatsByDateRange($date_from, $date_to);
    $customerStats = $customerModel->getCustomerStatsByDateRange($date_from, $date_to);
    
    // Get monthly data for charts within the date range
    $monthlyRevenue = [];
    $monthlyExpenses = [];
    
    // Calculate months between date_from and date_to
    $start = new DateTime($date_from);
    $end = new DateTime($date_to);
    $interval = new DateInterval('P1M');
    $period = new DatePeriod($start, $interval, $end);
    
    foreach ($period as $date) {
        $year = $date->format('Y');
        $month = $date->format('m');
        
        $revenue = $orderModel->getMonthlyRevenueByDateRange($year, $month, $date_from, $date_to);
        $expense = $expenseModel->getMonthlyExpenseStatsByDateRange($year, $month, $date_from, $date_to);
        
        $monthlyRevenue[] = [
            'month' => $date->format('M Y'),
            'revenue' => $revenue
        ];
        
        $monthlyExpenses[] = [
            'month' => $date->format('M Y'),
            'expenses' => $expense['total_amount'] ?? 0
        ];
    }
    
    // Get recent orders for the date range
    $recentOrders = $orderModel->getOrdersWithDetailsByDateRange($date_from, $date_to, 5);
    
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
    
    // Format expense categories for display
    $formattedExpenseCategories = [];
    if (!empty($expenseStats['by_category'])) {
        foreach (array_slice($expenseStats['by_category'], 0, 5) as $category) {
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
                    'total' => number_format($customerStats['total']),
                    'this_month' => $customerStats['this_month']
                ],
                'revenue' => [
                    'total_amount' => format_currency($invoiceStats['total_amount']),
                    'paid' => $invoiceStats['paid']
                ],
                'expenses' => [
                    'total_amount' => format_currency($expenseStats['total_amount']),
                    'total' => $expenseStats['total']
                ],
                'profit' => [
                    'net_profit' => format_currency($invoiceStats['total_amount'] - $expenseStats['total_amount'])
                ]
            ],
            'charts' => [
                'revenue_expense' => [
                    'labels' => array_column($monthlyRevenue, 'month'),
                    'revenue_data' => array_column($monthlyRevenue, 'revenue'),
                    'expense_data' => array_column($monthlyExpenses, 'expenses')
                ],
                'order_status' => [
                    'pending' => $orderStats['pending'],
                    'in_progress' => $orderStats['in_progress'],
                    'completed' => $orderStats['completed'],
                    'delivered' => $orderStats['delivered'],
                    'cancelled' => $orderStats['cancelled']
                ],
                'expense_categories' => [
                    'labels' => array_column($expenseStats['by_category'], 'category'),
                    'data' => array_column($expenseStats['by_category'], 'total')
                ],
                'payment_methods' => [
                    'labels' => array_column($expenseStats['by_payment_method'], 'payment_method'),
                    'data' => array_column($expenseStats['by_payment_method'], 'total')
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
    echo json_encode([
        'success' => false,
        'error' => 'Filter failed: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();
?>
