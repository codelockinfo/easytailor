<?php
/**
 * Get company reports data for popup
 */

require_once '../../config/config.php';

// Check if site admin is logged in
if (!isset($_SESSION['site_admin_logged_in']) || $_SESSION['site_admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$companyId = $_GET['company_id'] ?? null;

if (!$companyId) {
    echo json_encode(['success' => false, 'message' => 'Company ID is required']);
    exit;
}

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

try {
    // Get order statistics
    $orderQuery = "SELECT 
                        COUNT(*) as total_orders,
                        SUM(total_amount) as total_revenue,
                        SUM(advance_amount) as total_paid,
                        SUM(balance_amount) as total_balance,
                        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
                        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
                        COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_orders
                   FROM orders 
                   WHERE company_id = :company_id";
    $stmt = $db->prepare($orderQuery);
    $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
    $stmt->execute();
    $orderStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get invoice statistics
    $invoiceQuery = "SELECT 
                        COUNT(*) as total_invoices,
                        SUM(total_amount) as total_invoice_amount,
                        SUM(paid_amount) as total_paid_amount,
                        SUM(balance_amount) as total_due_amount,
                        COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_invoices,
                        COUNT(CASE WHEN payment_status = 'partial' THEN 1 END) as partial_invoices,
                        COUNT(CASE WHEN payment_status = 'due' THEN 1 END) as due_invoices
                     FROM invoices 
                     WHERE company_id = :company_id";
    $stmt = $db->prepare($invoiceQuery);
    $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
    $stmt->execute();
    $invoiceStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get expense statistics - check if expenses table has company_id
    $stmt = $db->query("SHOW COLUMNS FROM expenses LIKE 'company_id'");
    if ($stmt->rowCount() > 0) {
        $expenseQuery = "SELECT 
                            COUNT(*) as total_expenses,
                            SUM(amount) as total_expense_amount
                         FROM expenses 
                         WHERE company_id = :company_id";
    } else {
        $expenseQuery = "SELECT 
                            COUNT(*) as total_expenses,
                            SUM(e.amount) as total_expense_amount
                         FROM expenses e
                         INNER JOIN users u ON e.created_by = u.id
                         WHERE u.company_id = :company_id";
    }
    $stmt = $db->prepare($expenseQuery);
    $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
    $stmt->execute();
    $expenseStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get customer statistics
    $customerQuery = "SELECT 
                         COUNT(*) as total_customers,
                         COUNT(CASE WHEN status = 'active' THEN 1 END) as active_customers
                      FROM customers 
                      WHERE company_id = :company_id";
    $stmt = $db->prepare($customerQuery);
    $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
    $stmt->execute();
    $customerStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get user statistics
    $userQuery = "SELECT 
                     COUNT(*) as total_users,
                     COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users
                  FROM users 
                  WHERE company_id = :company_id";
    $stmt = $db->prepare($userQuery);
    $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
    $stmt->execute();
    $userStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate net profit
    $totalRevenue = floatval($invoiceStats['total_invoice_amount'] ?? $orderStats['total_revenue'] ?? 0);
    $totalExpenses = floatval($expenseStats['total_expense_amount'] ?? 0);
    $netProfit = $totalRevenue - $totalExpenses;
    
    $result = [
        'orders' => [
            'total' => intval($orderStats['total_orders'] ?? 0),
            'completed' => intval($orderStats['completed_orders'] ?? 0),
            'pending' => intval($orderStats['pending_orders'] ?? 0),
            'in_progress' => intval($orderStats['in_progress_orders'] ?? 0),
            'total_revenue' => floatval($orderStats['total_revenue'] ?? 0),
            'total_paid' => floatval($orderStats['total_paid'] ?? 0),
            'total_balance' => floatval($orderStats['total_balance'] ?? 0)
        ],
        'invoices' => [
            'total' => intval($invoiceStats['total_invoices'] ?? 0),
            'paid' => intval($invoiceStats['paid_invoices'] ?? 0),
            'partial' => intval($invoiceStats['partial_invoices'] ?? 0),
            'due' => intval($invoiceStats['due_invoices'] ?? 0),
            'total_amount' => floatval($invoiceStats['total_invoice_amount'] ?? 0),
            'paid_amount' => floatval($invoiceStats['total_paid_amount'] ?? 0),
            'due_amount' => floatval($invoiceStats['total_due_amount'] ?? 0)
        ],
        'expenses' => [
            'total' => intval($expenseStats['total_expenses'] ?? 0),
            'total_amount' => floatval($expenseStats['total_expense_amount'] ?? 0)
        ],
        'customers' => [
            'total' => intval($customerStats['total_customers'] ?? 0),
            'active' => intval($customerStats['active_customers'] ?? 0)
        ],
        'users' => [
            'total' => intval($userStats['total_users'] ?? 0),
            'active' => intval($userStats['active_users'] ?? 0)
        ],
        'financial' => [
            'total_revenue' => $totalRevenue,
            'total_expenses' => $totalExpenses,
            'net_profit' => $netProfit
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'reports' => $result
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching reports: ' . $e->getMessage()
    ]);
}

