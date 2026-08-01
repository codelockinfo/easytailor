<?php
header('Content-Type: application/json');
ob_start();
try {
    $scriptDir = dirname(__FILE__);
    $rootDir = dirname($scriptDir);
    require_once $rootDir . '/../config/config.php';
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    require_once $rootDir . '/models/Expense.php';
    require_once $rootDir . '/models/Category.php';
    $category = $_GET['category'] ?? '';
    $search = $_GET['search'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? RECORDS_PER_PAGE);
    if ($limit <= 0) {
        $limit = RECORDS_PER_PAGE; 
    }
    if ($page <= 0) {
        $page = 1; 
    }
    $expenseModel = new Expense();
    $conditions = [];
    if (!empty($category)) {
        $conditions['category'] = $category;
    }
    $offset = ($page - 1) * $limit;
    
    try {
        $allExpenses = $expenseModel->getExpensesWithDetails($conditions, 10000, 0);
    } catch (Exception $e) {
        error_log('Error getting expenses: ' . $e->getMessage());
        $allExpenses = [];
    }
    if (!is_array($allExpenses)) {
        $allExpenses = [];
    }
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
        $allExpenses = array_values($allExpenses);
    }
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
        $allExpenses = array_values($allExpenses);
    }
    $totalExpenses = count($allExpenses);
    $expenses = array_slice($allExpenses, $offset, $limit);
    $totalPages = $limit > 0 ? ceil($totalExpenses / $limit) : 1;
    $totalCashIn = 0;
    $totalCashOut = 0;
    foreach ($allExpenses as $expense) {
        $amount = (float)($expense['amount'] ?? 0);
        $method = $expense['payment_method'] ?? '';
        if ($method === 'cash_in') {
            $totalCashIn += $amount;
        } elseif ($method === 'cash_out') {
            $totalCashOut += $amount;
        }
    }
    $profit = $totalCashIn - $totalCashOut;
    $formattedExpenses = [];
    foreach ($expenses as $expense) {
        $formattedExpenses[] = [
            'id' => $expense['id'] ?? null,
            'category' => $expense['category'] ?? '',
            'description' => $expense['description'] ?? '',
            'amount' => $expense['amount'] ?? 0,
            'expense_date' => $expense['expense_date'] ?? '',
            'payment_method' => $expense['payment_method'] ?? 'cash',
            'reference_number' => $expense['reference_number'] ?? '',
            'receipt_image' => $expense['receipt_image'] ?? null,
            'created_by_name' => $expense['created_by_name'] ?? '',
            'created_at' => $expense['created_at'] ?? ''
        ];
    }
    if (!is_array($formattedExpenses)) {
        $formattedExpenses = [];
    }
    echo json_encode([
        'success' => true,
        'expenses' => $formattedExpenses,
        'stats' => [
            'total_expenses' => $totalExpenses,
            'total_cash_in' => $totalCashIn,
            'total_cash_out' => $totalCashOut,
            'profit' => $profit
        ],
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_expenses' => $totalExpenses,
            'has_previous' => $page > 1,
            'has_next' => $page < $totalPages
        ]
    ]);
    
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
        'error' => 'Filter failed: ' . $e->getMessage()
    ]);
}
ob_end_flush();