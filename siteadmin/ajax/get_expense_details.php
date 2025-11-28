<?php
/**
 * Get expense details by ID
 */

require_once '../../config/config.php';

// Check if site admin is logged in
if (!isset($_SESSION['site_admin_logged_in']) || $_SESSION['site_admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$expenseId = filter_input(INPUT_GET, 'expense_id', FILTER_VALIDATE_INT);
if (!$expenseId) {
    echo json_encode(['success' => false, 'message' => 'Expense ID is required']);
    exit;
}

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

try {
    // Check if expenses table exists
    $stmt = $db->query("SHOW TABLES LIKE 'expenses'");
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Expenses table not found']);
        exit;
    }

    // Get expense details with user information
    $query = "SELECT e.*, 
                     COALESCE(u.full_name, u.username, u.email, 'Unknown') as created_by_name,
                     u.email as created_by_email
              FROM expenses e
              LEFT JOIN users u ON e.created_by = u.id
              WHERE e.id = :expense_id 
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':expense_id', $expenseId, PDO::PARAM_INT);
    $stmt->execute();
    
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$expense) {
        echo json_encode(['success' => false, 'message' => 'Expense not found']);
        exit;
    }

    // Determine created from (check if there's any way to identify the source)
    $createdFrom = 'Web Application';
    if (isset($expense['created_from']) && !empty($expense['created_from'])) {
        $createdFrom = $expense['created_from'];
    } else {
        // Try to determine from other fields
        if (isset($expense['ip_address']) && !empty($expense['ip_address'])) {
            $createdFrom = 'Web Application (IP: ' . $expense['ip_address'] . ')';
        }
    }

    // Format expense data
    $expenseData = [
        'id' => $expense['id'],
        'category' => $expense['category'] ?? 'Other',
        'description' => $expense['description'] ?? '-',
        'amount' => $expense['amount'] ?? 0,
        'expense_date' => $expense['expense_date'] ?? $expense['date'] ?? $expense['created_at'] ?? null,
        'status' => $expense['status'] ?? 'active',
        'payment_method' => $expense['payment_method'] ?? null,
        'receipt_number' => $expense['receipt_number'] ?? $expense['receipt_no'] ?? null,
        'vendor' => $expense['vendor'] ?? $expense['supplier'] ?? null,
        'notes' => $expense['notes'] ?? null,
        'created_by' => $expense['created_by'] ?? null,
        'created_by_name' => $expense['created_by_name'] ?? 'Unknown',
        'created_by_email' => $expense['created_by_email'] ?? null,
        'created_at' => $expense['created_at'] ?? null,
        'updated_at' => $expense['updated_at'] ?? null,
        'created_from' => $createdFrom,
        'ip_address' => $expense['ip_address'] ?? null
    ];

    echo json_encode([
        'success' => true,
        'expense' => $expenseData
    ]);

} catch (Exception $e) {
    error_log("Error fetching expense details: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching expense details: ' . $e->getMessage()
    ]);
}
?>

