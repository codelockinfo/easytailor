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
    
    require_once $rootDir . '/../config/config.php';

    // Check if user is logged in
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    require_once $rootDir . '/../models/Expense.php';

    // Get filter parameters
    $category = $_GET['category'] ?? '';
    $search = $_GET['search'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? RECORDS_PER_PAGE);
    
    // Validate and fix limit parameter
    if ($limit <= 0) {
        $limit = RECORDS_PER_PAGE; // Default to records per page if limit is 0 or negative
    }
    
    // Validate page parameter
    if ($page <= 0) {
        $page = 1; // Default to page 1 if page is 0 or negative
    }

    $expenseModel = new Expense();
    
    // Build conditions - if all filters are empty, $conditions will be empty array and all expenses will be shown
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
    
    // Prepare search parameter - if empty, null will be passed and no search filter will be applied
    $searchParam = !empty($search) ? trim($search) : null;
    
    // Get expenses with search handled in SQL
    // When $conditions is empty and $searchParam is null, all expenses (filtered by company_id) will be returned
    $offset = ($page - 1) * $limit;
    
    try {
        if ($limit == 0) {
            // Limit 0 means we only need filter options, not expense data
            $expenses = [];
            $totalExpenses = 0;
        } else {
            // Get total count (without limit) - shows all when no filters applied
            $allExpenses = $expenseModel->getExpensesWithDetails($conditions, null, 0, $searchParam);
            $totalExpenses = count($allExpenses);
            
            // Get paginated results - shows all when no filters applied
            $expenses = $expenseModel->getExpensesWithDetails($conditions, $limit, $offset, $searchParam);
        }
    } catch (Exception $e) {
        throw new Exception("Failed to fetch expenses: " . $e->getMessage());
    }
    
    $totalPages = ($limit > 0) ? ceil($totalExpenses / $limit) : 1;
    
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
