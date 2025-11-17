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

    require_once $rootDir . '/models/Expense.php';

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
    
    // Build conditions (only for category, date filters handled separately)
    $conditions = [];
    if (!empty($category)) {
        $conditions['category'] = $category;
    }
    
    // Get expenses
    $offset = ($page - 1) * $limit;
    
    try {
        // Get all expenses first (with category filter if any)
        $allExpenses = $expenseModel->getExpensesWithDetails($conditions, 10000, 0);
    } catch (Exception $e) {
        error_log('Error getting expenses: ' . $e->getMessage());
        $allExpenses = [];
    }
    
    // Ensure $allExpenses is an array
    if (!is_array($allExpenses)) {
        $allExpenses = [];
    }
    
    // Apply date filters if provided
    if (!empty($date_from) || !empty($date_to)) {
        $allExpenses = array_filter($allExpenses, function($expense) use ($date_from, $date_to) {
            $expenseDate = $expense['expense_date'] ?? '';
            if (empty($expenseDate)) {
                return false;
            }
            
            $expenseTimestamp = strtotime($expenseDate);
            
            if (!empty($date_from)) {
                $fromTimestamp = strtotime($date_from);
                if ($expenseTimestamp < $fromTimestamp) {
                    return false;
                }
            }
            
            if (!empty($date_to)) {
                $toTimestamp = strtotime($date_to);
                if ($expenseTimestamp > $toTimestamp) {
                    return false;
                }
            }
            
            return true;
        });
        // Re-index array after filtering
        $allExpenses = array_values($allExpenses);
    }
    
    // Apply search filter if provided
    if (!empty($search)) {
        $searchLower = strtolower(trim($search));
        $allExpenses = array_filter($allExpenses, function($expense) use ($searchLower) {
            $category = strtolower($expense['category'] ?? '');
            $description = strtolower($expense['description'] ?? '');
            $paymentMethod = strtolower($expense['payment_method'] ?? '');
            $referenceNumber = strtolower($expense['reference_number'] ?? '');
            
            return strpos($category, $searchLower) !== false ||
                   strpos($description, $searchLower) !== false ||
                   strpos($paymentMethod, $searchLower) !== false ||
                   strpos($referenceNumber, $searchLower) !== false;
        });
        // Re-index array after filtering
        $allExpenses = array_values($allExpenses);
    }
    
    // Apply pagination
    $totalExpenses = count($allExpenses);
    $expenses = array_slice($allExpenses, $offset, $limit);
    $totalPages = $limit > 0 ? ceil($totalExpenses / $limit) : 1;
    
    // Get filter options - get unique categories from database
    $allExpenses = $expenseModel->findAll([], 'category');
    $categories = array_unique(array_column($allExpenses, 'category'));
    $categories = array_filter($categories); // Remove empty values
    $categories = array_values($categories); // Re-index array
    
    // Format expenses for display - match original table structure exactly
    $formattedExpenses = [];
    foreach ($expenses as $expense) {
        $formattedExpenses[] = [
            'id' => $expense['id'] ?? null,
            'category' => htmlspecialchars($expense['category'] ?? ''),
            'description' => htmlspecialchars($expense['description'] ?? ''),
            'amount' => $expense['amount'] ?? 0,
            'expense_date' => $expense['expense_date'] ?? '',
            'payment_method' => htmlspecialchars($expense['payment_method'] ?? 'cash'),
            'reference_number' => htmlspecialchars($expense['reference_number'] ?? ''),
            'receipt_image' => $expense['receipt_image'] ?? null,
            'created_by_name' => htmlspecialchars($expense['created_by_name'] ?? ''),
            'created_at' => $expense['created_at'] ?? ''
        ];
    }
    
    // Ensure formattedExpenses is always an array
    if (!is_array($formattedExpenses)) {
        $formattedExpenses = [];
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
