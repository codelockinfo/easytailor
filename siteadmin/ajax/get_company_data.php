<?php
/**
 * Get company-specific data for different tabs
 */

require_once '../../config/config.php';

// Check if site admin is logged in
if (!isset($_SESSION['site_admin_logged_in']) || $_SESSION['site_admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$companyId = $_GET['company_id'] ?? null;
$type = $_GET['type'] ?? 'customers';

if (!$companyId) {
    echo json_encode(['success' => false, 'message' => 'Company ID is required']);
    exit;
}

require_once '../../models/Company.php';
$companyModel = new Company();

// Verify company exists
$company = $companyModel->find($companyId);
if (!$company) {
    echo json_encode(['success' => false, 'message' => 'Company not found']);
    exit;
}

$data = [];

try {
    switch ($type) {
        case 'customers':
            require_once '../../models/Customer.php';
            $customerModel = new Customer();
            $customers = $customerModel->findAll(['company_id' => $companyId], 'created_at DESC');
            $data = array_map(function($c) {
                // Combine first_name and last_name to create full name
                $firstName = $c['first_name'] ?? '';
                $lastName = $c['last_name'] ?? '';
                $fullName = trim($firstName . ' ' . $lastName);
                if (empty($fullName)) {
                    $fullName = 'N/A';
                }
                
                // Build full address
                $addressParts = [];
                if (!empty($c['address'])) $addressParts[] = $c['address'];
                if (!empty($c['city'])) $addressParts[] = $c['city'];
                if (!empty($c['state'])) $addressParts[] = $c['state'];
                if (!empty($c['postal_code'])) $addressParts[] = $c['postal_code'];
                $fullAddress = !empty($addressParts) ? implode(', ', $addressParts) : '-';
                
                return [
                    'id' => $c['id'],
                    'name' => $fullName,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'customer_code' => $c['customer_code'] ?? '',
                    'email' => $c['email'] ?? '-',
                    'phone' => $c['phone'] ?? '-',
                    'address' => $fullAddress,
                    'city' => $c['city'] ?? '',
                    'state' => $c['state'] ?? '',
                    'postal_code' => $c['postal_code'] ?? '',
                    'status' => $c['status'] ?? 'active',
                    'created_at' => $c['created_at'] ?? null
                ];
            }, $customers);
            break;

        case 'orders':
            require_once '../../config/database.php';
            $database = new Database();
            $db = $database->getConnection();
            try {
                // Check if orders table exists
                $stmt = $db->query("SHOW TABLES LIKE 'orders'");
                if ($stmt->rowCount() > 0) {
                    $query = "SELECT o.*, 
                                     c.first_name, c.last_name, c.customer_code, 
                                     c.phone as customer_phone, c.email as customer_email,
                                     c.address as customer_address, c.city, c.state, c.postal_code,
                                     ct.name as cloth_type_name, ct.category as cloth_category,
                                     COALESCE(creator.full_name, creator.username, '') as created_by_name
                              FROM orders o
                              LEFT JOIN customers c ON o.customer_id = c.id
                              LEFT JOIN cloth_types ct ON o.cloth_type_id = ct.id
                              LEFT JOIN users creator ON o.created_by = creator.id
                              WHERE o.company_id = :company_id 
                              ORDER BY o.created_at DESC";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
                    $stmt->execute();
                    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $data = array_map(function($o) {
                        $customerName = trim(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? ''));
                        if (empty($customerName)) {
                            $customerName = 'N/A';
                        }
                        
                        // Calculate paid amount from advance_amount
                        $paidAmount = floatval($o['advance_amount'] ?? 0);
                        $totalAmount = floatval($o['total_amount'] ?? 0);
                        $balanceAmount = floatval($o['balance_amount'] ?? ($totalAmount - $paidAmount));
                        
                        return [
                            'id' => $o['id'],
                            'order_number' => $o['order_number'] ?? 'ORD-' . str_pad($o['id'], 6, '0', STR_PAD_LEFT),
                            'customer_name' => $customerName,
                            'customer_code' => $o['customer_code'] ?? '',
                            'customer_phone' => $o['customer_phone'] ?? '',
                            'customer_email' => $o['customer_email'] ?? '',
                            'customer_address' => $o['customer_address'] ?? '',
                            'customer_city' => $o['city'] ?? '',
                            'customer_state' => $o['state'] ?? '',
                            'customer_postal_code' => $o['postal_code'] ?? '',
                            'cloth_type_name' => $o['cloth_type_name'] ?? '',
                            'cloth_category' => $o['cloth_category'] ?? '',
                            'status' => $o['status'] ?? 'pending',
                            'order_date' => $o['order_date'] ?? null,
                            'due_date' => $o['due_date'] ?? null,
                            'delivery_date' => $o['delivery_date'] ?? null,
                            'total_amount' => $totalAmount,
                            'advance_amount' => $paidAmount,
                            'paid_amount' => $paidAmount, // For compatibility
                            'balance_amount' => $balanceAmount,
                            'special_instructions' => $o['special_instructions'] ?? '',
                            'created_at' => $o['created_at'] ?? null,
                            'created_by_name' => $o['created_by_name'] ?? ''
                        ];
                    }, $orders);
                } else {
                    $data = [];
                }
            } catch (Exception $e) {
                error_log("Error fetching orders: " . $e->getMessage());
                $data = [];
            }
            break;

        case 'users':
            require_once '../../models/User.php';
            $userModel = new User();
            $users = $userModel->findAll(['company_id' => $companyId], 'created_at DESC');
            $data = array_map(function($u) {
                return [
                    'id' => $u['id'],
                    'name' => $u['name'] ?? $u['username'] ?? 'N/A',
                    'email' => $u['email'] ?? '-',
                    'role' => $u['role'] ?? 'user',
                    'status' => $u['status'] ?? 'active',
                    'created_at' => $u['created_at'] ?? null
                ];
            }, $users);
            break;

        case 'invoices':
            // Use Invoice model to get invoices with details (same as admin side)
            require_once '../../config/database.php';
            $database = new Database();
            $db = $database->getConnection();
            try {
                $stmt = $db->query("SHOW TABLES LIKE 'invoices'");
                if ($stmt->rowCount() > 0) {
                    $query = "SELECT i.*, 
                                     o.order_number, o.order_date,
                                     c.first_name, c.last_name, c.customer_code, 
                                     c.phone as customer_phone, c.email as customer_email
                              FROM invoices i
                              LEFT JOIN orders o ON i.order_id = o.id
                              LEFT JOIN customers c ON o.customer_id = c.id
                              WHERE i.company_id = :company_id 
                              ORDER BY i.created_at DESC";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':company_id', $companyId);
                    $stmt->execute();
                    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $data = array_map(function($i) {
                        return [
                            'id' => $i['id'],
                            'invoice_number' => $i['invoice_number'] ?? 'INV-' . str_pad($i['id'], 6, '0', STR_PAD_LEFT),
                            'customer_name' => trim(($i['first_name'] ?? '') . ' ' . ($i['last_name'] ?? '')),
                            'customer_code' => $i['customer_code'] ?? '',
                            'order_number' => $i['order_number'] ?? '',
                            'invoice_date' => $i['invoice_date'] ?? null,
                            'due_date' => $i['due_date'] ?? null,
                            'subtotal' => $i['subtotal'] ?? 0,
                            'tax_rate' => $i['tax_rate'] ?? 0,
                            'tax_amount' => $i['tax_amount'] ?? 0,
                            'discount_amount' => $i['discount_amount'] ?? 0,
                            'total_amount' => $i['total_amount'] ?? 0,
                            'paid_amount' => $i['paid_amount'] ?? 0,
                            'balance_amount' => $i['balance_amount'] ?? 0,
                            'payment_status' => $i['payment_status'] ?? 'due',
                            'notes' => $i['notes'] ?? '',
                            'created_at' => $i['created_at'] ?? null
                        ];
                    }, $invoices);
                } else {
                    $data = [];
                }
            } catch (Exception $e) {
                $data = [];
            }
            break;

        case 'expenses':
            // Check if expenses table exists
            require_once '../../config/database.php';
            $database = new Database();
            $db = $database->getConnection();
            try {
                $stmt = $db->query("SHOW TABLES LIKE 'expenses'");
                if ($stmt->rowCount() > 0) {
                    // Check if expenses table has company_id column
                    $stmt = $db->query("SHOW COLUMNS FROM expenses LIKE 'company_id'");
                    if ($stmt->rowCount() > 0) {
                        $query = "SELECT * FROM expenses 
                                 WHERE company_id = :company_id 
                                 ORDER BY expense_date DESC, created_at DESC";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':company_id', $companyId);
                    } else {
                        // If no company_id, get expenses through users
                        $query = "SELECT e.* FROM expenses e 
                                 INNER JOIN users u ON e.created_by = u.id 
                                 WHERE u.company_id = :company_id 
                                 ORDER BY e.expense_date DESC, e.created_at DESC";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':company_id', $companyId);
                    }
                    $stmt->execute();
                    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $data = array_map(function($e) {
                        return [
                            'id' => $e['id'],
                            'category' => $e['category'] ?? 'Other',
                            'description' => $e['description'] ?? '-',
                            'amount' => $e['amount'] ?? 0,
                            'date' => $e['expense_date'] ?? $e['date'] ?? $e['created_at'] ?? null,
                            'status' => $e['status'] ?? 'active'
                        ];
                    }, $expenses);
                } else {
                    $data = [];
                }
            } catch (Exception $e) {
                $data = [];
            }
            break;

        case 'reports':
            // Generate company reports summary
            require_once '../../config/database.php';
            $database = new Database();
            $db = $database->getConnection();
            try {
                // Get current month stats
                $currentMonth = date('Y-m');
                $currentYear = date('Y');
                
                // Total Orders
                $stmt = $db->prepare("SELECT COUNT(*) as total FROM orders WHERE company_id = :company_id");
                $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
                $stmt->execute();
                $totalOrders = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                
                // Total Revenue
                $stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE company_id = :company_id");
                $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
                $stmt->execute();
                $totalRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                
                // Total Customers
                $stmt = $db->prepare("SELECT COUNT(*) as total FROM customers WHERE company_id = :company_id");
                $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
                $stmt->execute();
                $totalCustomers = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                
                // Total Invoices
                $stmt = $db->query("SHOW TABLES LIKE 'invoices'");
                $totalInvoices = 0;
                if ($stmt->rowCount() > 0) {
                    $stmt = $db->prepare("SELECT COUNT(*) as total FROM invoices WHERE company_id = :company_id");
                    $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
                    $stmt->execute();
                    $totalInvoices = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                }
                
                // Total Expenses
                $stmt = $db->query("SHOW TABLES LIKE 'expenses'");
                $totalExpenses = 0;
                if ($stmt->rowCount() > 0) {
                    $stmt = $db->query("SHOW COLUMNS FROM expenses LIKE 'company_id'");
                    if ($stmt->rowCount() > 0) {
                        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE company_id = :company_id");
                        $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
                        $stmt->execute();
                        $totalExpenses = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                    } else {
                        // Get through users
                        $stmt = $db->prepare("SELECT COALESCE(SUM(e.amount), 0) as total FROM expenses e INNER JOIN users u ON e.created_by = u.id WHERE u.company_id = :company_id");
                        $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
                        $stmt->execute();
                        $totalExpenses = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                    }
                }
                
                $data = [
                    [
                        'id' => 1,
                        'report_type' => 'Company Summary Report',
                        'period' => date('M Y'),
                        'generated_at' => date('Y-m-d H:i:s'),
                        'total_orders' => $totalOrders,
                        'total_revenue' => $totalRevenue,
                        'total_customers' => $totalCustomers,
                        'total_invoices' => $totalInvoices,
                        'total_expenses' => $totalExpenses
                    ]
                ];
            } catch (Exception $e) {
                error_log("Error generating reports: " . $e->getMessage());
                $data = [];
            }
            break;

        case 'contact':
            // Get contacts from contacts table
            require_once '../../config/database.php';
            $database = new Database();
            $db = $database->getConnection();
            try {
                $stmt = $db->query("SHOW TABLES LIKE 'contacts'");
                if ($stmt->rowCount() > 0) {
                    // Check if contacts table has company_id column
                    $stmt = $db->query("SHOW COLUMNS FROM contacts LIKE 'company_id'");
                    if ($stmt->rowCount() > 0) {
                        $query = "SELECT * FROM contacts 
                                 WHERE company_id = :company_id 
                                 ORDER BY created_at DESC";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':company_id', $companyId);
                    } else {
                        // If no company_id, get all contacts (legacy support)
                        $query = "SELECT * FROM contacts ORDER BY created_at DESC";
                        $stmt = $db->prepare($query);
                    }
                    $stmt->execute();
                    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $data = array_map(function($c) {
                        return [
                            'id' => $c['id'],
                            'name' => $c['name'] ?? 'N/A',
                            'company' => $c['company'] ?? '-',
                            'email' => $c['email'] ?? '-',
                            'phone' => $c['phone'] ?? '-',
                            'category' => $c['category'] ?? 'General',
                            'status' => $c['status'] ?? 'active',
                            'created_at' => $c['created_at'] ?? null
                        ];
                    }, $contacts);
                } else {
                    // Fallback to company contact info if contacts table doesn't exist
                    $data = [
                        [
                            'id' => 1,
                            'name' => $company['owner_name'] ?? 'N/A',
                            'company' => $company['company_name'] ?? '-',
                            'email' => $company['business_email'] ?? '-',
                            'phone' => $company['business_phone'] ?? '-',
                            'category' => 'Primary Contact',
                            'status' => 'active',
                            'created_at' => $company['created_at'] ?? null
                        ]
                    ];
                }
            } catch (Exception $e) {
                $data = [];
            }
            break;

        default:
            $data = [];
    }

    echo json_encode([
        'success' => true,
        'data' => $data,
        'count' => count($data)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching data: ' . $e->getMessage()
    ]);
}

