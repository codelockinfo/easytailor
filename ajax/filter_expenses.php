<?php
/**
 * AJAX Expense Filter Endpoint
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

    require_once $rootDir . '/models/Expense.php';

    // Get filter parameters
    $category = $_GET['category'] ?? '';
    $search = $_GET['search'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? RECORDS_PER_PAGE);

    $expenseModel = new Expense();
    
    // Build conditions
    $conditions = [];
    if (!empty($category)) {
        $conditions['category'] = $category;
    }
    if (!empty($date_from)) {
        $conditions['date_from'] = $date_from;
    }
    if (!empty($date_to)) {
        $conditions['date_to'] = $date_to;
    }
    
    // Get expenses
    $offset = ($page - 1) * $limit;
    $expenses = $expenseModel->getExpensesWithDetails($conditions, $limit, $offset);
    
    // If search is provided, filter results manually
    if (!empty($search)) {
        $searchLower = strtolower($search);
        $expenses = array_filter($expenses, function($expense) use ($searchLower) {
            return strpos(strtolower($expense['category']), $searchLower) !== false ||
                   strpos(strtolower($expense['description']), $searchLower) !== false ||
                   strpos(strtolower($expense['payment_method']), $searchLower) !== false ||
                   strpos(strtolower($expense['reference_number'] ?? ''), $searchLower) !== false;
        });
    }
    
    $totalExpenses = count($expenses);
    $totalPages = ceil($totalExpenses / $limit);
    
    // Get filter options - get unique categories from database
    $allExpenses = $expenseModel->findAll([], 'category');
    $categories = array_unique(array_column($allExpenses, 'category'));
    $categories = array_filter($categories); // Remove empty values
    $categories = array_values($categories); // Re-index array
    
    // Format expenses for display - match original table structure exactly
    $formattedExpenses = [];
    foreach ($expenses as $expense) {
        $formattedExpenses[] = [
            'id' => $expense['id'],
            'category' => htmlspecialchars($expense['category']),
            'description' => htmlspecialchars($expense['description']),
            'amount' => $expense['amount'],
            'expense_date' => $expense['expense_date'],
            'payment_method' => htmlspecialchars($expense['payment_method']),
            'reference_number' => htmlspecialchars($expense['reference_number'] ?? ''),
            'receipt_image' => $expense['receipt_image'],
            'created_by_name' => htmlspecialchars($expense['created_by_name'] ?? ''),
            'created_at' => $expense['created_at']
        ];
    }
    
    // Format filter options - ensure categories is an array
    $filterOptions = [
        'categories' => array_values(array_map(function($category) {
            return htmlspecialchars($category);
        }, $categories))
    ];
    
    echo json_encode([
        'success' => true,
        'expenses' => $formattedExpenses,
        'filter_options' => $filterOptions,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_expenses' => $totalExpenses,
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
