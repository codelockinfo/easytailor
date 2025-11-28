<?php
/**
 * Get Invoice Details
 * Fetch detailed information for a specific invoice
 */

require_once '../../config/config.php';

// Check if site admin is logged in
if (!isset($_SESSION['site_admin_logged_in']) || $_SESSION['site_admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$invoiceId = filter_input(INPUT_GET, 'invoice_id', FILTER_VALIDATE_INT);
if (!$invoiceId) {
    echo json_encode(['success' => false, 'message' => 'Invoice ID is required']);
    exit;
}

require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if invoices table exists
    $stmt = $db->query("SHOW TABLES LIKE 'invoices'");
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Invoices table not found']);
        exit;
    }
    
    // Get invoice details with related data
    $query = "SELECT i.*, 
                     o.order_number, o.order_date, o.due_date as order_due_date, o.delivery_date, o.status as order_status,
                     o.total_amount as order_total_amount, o.advance_amount as order_advance_amount,
                     c.first_name, c.last_name, c.customer_code, c.phone as customer_phone, 
                     c.email as customer_email, c.address as customer_address, c.city as customer_city,
                     c.state as customer_state, c.postal_code as customer_postal_code,
                     COALESCE(creator.full_name, creator.username, '') as created_by_name
              FROM invoices i
              LEFT JOIN orders o ON i.order_id = o.id
              LEFT JOIN customers c ON o.customer_id = c.id
              LEFT JOIN users creator ON i.created_by = creator.id
              WHERE i.id = :invoice_id 
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':invoice_id', $invoiceId, PDO::PARAM_INT);
    $stmt->execute();
    
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        echo json_encode(['success' => false, 'message' => 'Invoice not found']);
        exit;
    }
    
    // Format customer name
    $customerName = trim(($invoice['first_name'] ?? '') . ' ' . ($invoice['last_name'] ?? ''));
    if (empty($customerName)) {
        $customerName = 'N/A';
    }
    
    // Format invoice data
    $invoiceData = [
        'id' => $invoice['id'],
        'invoice_number' => $invoice['invoice_number'] ?? 'INV-' . str_pad($invoice['id'], 6, '0', STR_PAD_LEFT),
        'order_id' => $invoice['order_id'] ?? null,
        'order_number' => $invoice['order_number'] ?? '-',
        'order_date' => $invoice['order_date'] ?? null,
        'order_due_date' => $invoice['order_due_date'] ?? null,
        'order_delivery_date' => $invoice['delivery_date'] ?? null,
        'order_status' => $invoice['order_status'] ?? '-',
        'order_total_amount' => $invoice['order_total_amount'] ?? 0,
        'order_advance_amount' => $invoice['order_advance_amount'] ?? 0,
        'customer_name' => $customerName,
        'customer_code' => $invoice['customer_code'] ?? '-',
        'customer_phone' => $invoice['customer_phone'] ?? '-',
        'customer_email' => $invoice['customer_email'] ?? '-',
        'customer_address' => $invoice['customer_address'] ?? '-',
        'customer_city' => $invoice['customer_city'] ?? '-',
        'customer_state' => $invoice['customer_state'] ?? '-',
        'customer_postal_code' => $invoice['customer_postal_code'] ?? '-',
        'invoice_date' => $invoice['invoice_date'] ?? null,
        'due_date' => $invoice['due_date'] ?? null,
        'subtotal' => floatval($invoice['subtotal'] ?? 0),
        'tax_rate' => floatval($invoice['tax_rate'] ?? 0),
        'tax_amount' => floatval($invoice['tax_amount'] ?? 0),
        'discount_amount' => floatval($invoice['discount_amount'] ?? 0),
        'total_amount' => floatval($invoice['total_amount'] ?? 0),
        'paid_amount' => floatval($invoice['paid_amount'] ?? 0),
        'balance_amount' => floatval($invoice['balance_amount'] ?? ($invoice['total_amount'] - $invoice['paid_amount'])),
        'payment_status' => $invoice['payment_status'] ?? 'due',
        'payment_method' => $invoice['payment_method'] ?? null,
        'payment_date' => $invoice['payment_date'] ?? null,
        'notes' => $invoice['notes'] ?? '',
        'created_at' => $invoice['created_at'] ?? null,
        'updated_at' => $invoice['updated_at'] ?? null,
        'created_by' => $invoice['created_by'] ?? null,
        'created_by_name' => $invoice['created_by_name'] ?? 'N/A'
    ];
    
    echo json_encode([
        'success' => true,
        'invoice' => $invoiceData
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching invoice details: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching invoice details: ' . $e->getMessage()
    ]);
}
?>

