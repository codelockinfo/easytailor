<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="favicon(2).png">

<?php
/**
 * Invoices Page
 * Tailoring Management System
 */

// Include config first (before any output)
require_once '../config/config.php';

// Check if user is logged in
require_login();

require_once 'models/Invoice.php';
require_once 'models/Order.php';
require_once 'models/Payment.php';
require_once '../helpers/SubscriptionHelper.php';

$invoiceModel = new Invoice();
$orderModel = new Order();
$paymentModel = new Payment();

$message = '';
$messageType = '';
$companyId = get_company_id();

// Handle fixing existing invoices with advance payments
if (isset($_GET['fix_advance']) && $_GET['fix_advance'] === '1') {
    // Get all invoices that need fixing
    $invoices = $invoiceModel->getInvoicesWithDetails();
    $fixed = 0;
    
    foreach ($invoices as $invoice) {
        $order = $orderModel->find($invoice['order_id']);
        if ($order && $order['advance_amount'] > 0) {
            $totalAmount = $invoice['total_amount'];
            $advanceAmount = (float)$order['advance_amount'];
            $balanceAmount = $totalAmount - $advanceAmount;
            
            // Only update if the invoice doesn't already have the advance payment
            if ($invoice['paid_amount'] == 0 && $invoice['balance_amount'] == $totalAmount) {
                $invoiceModel->update($invoice['id'], [
                    'paid_amount' => $advanceAmount,
                    'balance_amount' => $balanceAmount
                ]);
                
                // Update payment status
                $invoiceModel->updatePaymentStatus($invoice['id']);
                $fixed++;
            }
        }
    }
    
    $_SESSION['message'] = "Fixed {$fixed} invoices with advance payments";
    $_SESSION['messageType'] = 'success';
    header('Location: invoices.php');
    exit;
}

// Handle form submissions BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                // Check if invoice generation is allowed for this plan
                $invoiceCheck = SubscriptionHelper::canGenerateInvoice($companyId);
                if (!$invoiceCheck['allowed']) {
                    $_SESSION['message'] = $invoiceCheck['message'] . ' ' . SubscriptionHelper::getUpgradeMessage('invoices', SubscriptionHelper::getCurrentPlan($companyId));
                    $_SESSION['messageType'] = 'error';
                    header('Location: invoices.php');
                    exit;
                }
                
                // Get order details to include advance payment
                $order = $orderModel->find((int)$_POST['order_id']);
                $advanceAmount = $order ? (float)$order['advance_amount'] : 0;
                $totalAmount = (float)$_POST['total_amount'];
                $balanceAmount = $totalAmount - $advanceAmount;
                
                $data = [
                    'order_id' => (int)$_POST['order_id'],
                    'invoice_date' => $_POST['invoice_date'],
                    'due_date' => $_POST['due_date'],
                    'subtotal' => (float)$_POST['subtotal'],
                    'tax_rate' => (float)$_POST['tax_rate'],
                    'tax_amount' => (float)$_POST['tax_amount'],
                    'discount_amount' => (float)$_POST['discount_amount'],
                    'total_amount' => $totalAmount,
                    'paid_amount' => $advanceAmount, // Include advance payment from order
                    'balance_amount' => $balanceAmount, // Calculate balance after advance
                    'notes' => sanitize_input($_POST['notes']),
                    'created_by' => get_user_id()
                ];
                
                $invoiceId = $invoiceModel->createInvoice($data);
                if ($invoiceId) {
                    // Update payment status based on advance payment
                    $invoiceModel->updatePaymentStatus($invoiceId);
                    $_SESSION['message'] = 'Invoice created successfully';
                    $_SESSION['messageType'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to create invoice';
                    $_SESSION['messageType'] = 'error';
                }
                header('Location: invoices.php');
                exit;
                break;
                
            case 'add_payment':
                $data = [
                    'invoice_id' => (int)$_POST['invoice_id'],
                    'amount' => (float)$_POST['amount'],
                    'payment_method' => $_POST['payment_method'],
                    'payment_date' => $_POST['payment_date'],
                    'reference_number' => sanitize_input($_POST['reference_number']),
                    'notes' => sanitize_input($_POST['notes']),
                    'created_by' => get_user_id()
                ];
                
                $paymentId = $paymentModel->createPayment($data);
                if ($paymentId) {
                    $_SESSION['message'] = 'Payment added successfully';
                    $_SESSION['messageType'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to add payment';
                    $_SESSION['messageType'] = 'error';
                }
                header('Location: invoices.php');
                exit;
                break;
                
            case 'delete_payment':
                $paymentId = (int)$_POST['payment_id'];
                if ($paymentModel->deletePayment($paymentId)) {
                    $_SESSION['message'] = 'Payment deleted successfully';
                    $_SESSION['messageType'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to delete payment';
                    $_SESSION['messageType'] = 'error';
                }
                header('Location: invoices.php');
                exit;
                break;
        }
    } else {
        $_SESSION['message'] = 'Invalid request';
        $_SESSION['messageType'] = 'error';
        header('Location: invoices.php');
        exit;
    }
}

// Get messages from session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['messageType']);
}

// NOW include header (after all redirects are done)
$page_title = 'Invoice Management';
require_once 'includes/header.php';

// Get invoices
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = RECORDS_PER_PAGE;
$offset = ($page - 1) * $limit;

$conditions = [];
if (!empty($status_filter)) {
    $conditions['payment_status'] = $status_filter;
}

$searchParam = !empty($search) ? trim($search) : null;
$allInvoices = $invoiceModel->getInvoicesWithDetails($conditions, null, 0, $searchParam);
$totalInvoices = count($allInvoices);

if ($limit > 0) {
    $invoices = array_slice($allInvoices, $offset, $limit);
    $totalPages = max(1, ceil($totalInvoices / $limit));
} else {
    $invoices = $allInvoices;
    $totalPages = 1;
}

// Get orders that can have invoices (not cancelled and not fully invoiced)
// Show orders with status: pending, in_progress, completed, or delivered
$allOrders = $orderModel->getOrdersWithDetails([], 100);

// Filter orders that:
// 1. Are not cancelled
// 2. Don't have an invoice yet (or have balance remaining)
$unpaidOrders = [];
foreach ($allOrders as $order) {
    if ($order['status'] !== 'cancelled') {
        // Check if this order already has an invoice
        $existingInvoices = $invoiceModel->findAll(['order_id' => $order['id']]);
        
        if (empty($existingInvoices)) {
            // No invoice exists, show this order
            $unpaidOrders[] = $order;
        }
    }
}

// Get invoice statistics
$invoiceStats = $invoiceModel->getInvoiceStats();

// Check subscription for invoice generation
$currentPlan = SubscriptionHelper::getCurrentPlan($companyId);
$invoiceCheck = SubscriptionHelper::canGenerateInvoice($companyId);
?>


<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!$invoiceCheck['allowed']): ?>
    <div class="alert alert-warning alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Invoice Generation Not Available:</strong> Invoice generation is not available in the Free plan. 
        <a href="subscriptions.php" class="alert-link">Upgrade to Basic, Premium, or Enterprise plan</a> to use this feature.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Invoice Statistics -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($invoiceStats['total']); ?></div>
                    <div class="stat-label">Total Invoices</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-file-invoice"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($invoiceStats['paid']); ?></div>
                    <div class="stat-label">Paid</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($invoiceStats['partial']); ?></div>
                    <div class="stat-label">Partial</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($invoiceStats['due']); ?></div>
                    <div class="stat-label">Due</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Revenue Summary -->
<div class="row mb-4">
    <div class="col-xl-4 col-md-6 mb-3">
        <div class="card">
            <div class="card-body text-center">
                <h5 class="card-title text-success">Total Revenue</h5>
                <h3 class="text-success"><?php echo format_currency($invoiceStats['total_amount']); ?></h3>
            </div>
        </div>
    </div>
    
    <div class="col-xl-4 col-md-6 mb-3">
        <div class="card">
            <div class="card-body text-center">
                <h5 class="card-title text-info">Paid Amount</h5>
                <h3 class="text-info"><?php echo format_currency($invoiceStats['paid_amount']); ?></h3>
            </div>
        </div>
    </div>
    
    <div class="col-xl-4 col-md-6 mb-3">
        <div class="card">
            <div class="card-body text-center">
                <h5 class="card-title text-warning">Pending Amount</h5>
                <h3 class="text-warning"><?php echo format_currency($invoiceStats['due_amount']); ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" 
                           id="searchInput"
                           class="form-control" 
                           placeholder="Search invoices..."
                           autocomplete="off">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="statusFilter">
                    <option value="">All Statuses</option>
                    <option value="paid">Paid</option>
                    <option value="partial">Partial</option>
                    <option value="due">Due</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="customerFilter">
                    <option value="">All Customers</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="button" id="clearFilters" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-times"></i> Clear
                </button>
            </div>
        </div>
        <div id="filterResults" class="mt-3" style="display: none;">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <span id="filterCount">0</span> invoices found
            </div>
        </div>
    </div>
</div>

<!-- Invoices Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-file-invoice me-2"></i>
            Invoices (<?php echo number_format($totalInvoices); ?>)
        </h5>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#invoiceModal" <?php echo !$invoiceCheck['allowed'] ? 'disabled title="Upgrade required to create invoices"' : ''; ?>>
                <i class="fas fa-plus me-1"></i>Create Invoice
                <?php if (!$invoiceCheck['allowed']): ?>
                    <span class="badge bg-warning ms-2">Upgrade Required</span>
                <?php endif; ?>
            </button>
            <button type="button" class="btn btn-sm btn-outline-light" onclick="exportInvoices()">
                <i class="fas fa-download me-1"></i>Export
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php if (!empty($invoices)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Customer</th>
                            <th>Order #</th>
                            <th>Invoice Date</th>
                            <th>Due Date</th>
                            <th>Amount</th>
                            <th>Paid</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td>
                                <span class="badge bg-primary"><?php echo htmlspecialchars($invoice['invoice_number']); ?></span>
                            </td>
                            <td>
                                <div>
                                    <strong><?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($invoice['customer_code']); ?></small>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo htmlspecialchars($invoice['order_number']); ?></span>
                            </td>
                            <td><?php echo format_date($invoice['invoice_date']); ?></td>
                            <td>
                                <span class="<?php echo $invoice['due_date'] < date('Y-m-d') && $invoice['payment_status'] !== 'paid' ? 'text-danger fw-bold' : ''; ?>">
                                    <?php echo format_date($invoice['due_date']); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo format_currency($invoice['total_amount']); ?></strong>
                                <?php if ($invoice['tax_amount'] > 0): ?>
                                    <br>
                                    <small class="text-muted">
                                        Tax: <?php echo format_currency($invoice['tax_amount']); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo format_currency($invoice['paid_amount']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo match($invoice['payment_status']) {
                                        'paid' => 'success',
                                        'partial' => 'warning',
                                        'due' => 'danger',
                                        default => 'secondary'
                                    };
                                ?>">
                                    <?php echo ucfirst($invoice['payment_status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" 
                                            class="btn btn-outline-info" 
                                            onclick="viewInvoice(<?php echo $invoice['id']; ?>)"
                                            title="View Details" style="border: 1px solid #667eea;">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" 
                                            class="btn btn-outline-success" 
                                            onclick="addPayment(<?php echo $invoice['id']; ?>, '<?php echo htmlspecialchars($invoice['invoice_number']); ?>', <?php echo $invoice['balance_amount']; ?>)"
                                            title="Add Payment" style="border: 1px solid #667eea;">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button type="button" 
                                            class="btn btn-outline-primary" 
                                            onclick="printInvoice(<?php echo $invoice['id']; ?>)"
                                            title="Print Invoice" style="border: 1px solid #667eea;">
                                        <i class="fas fa-print"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Invoice pagination">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No invoices found</h5>
                <p class="text-muted">
                    <?php if (!empty($search) || !empty($status_filter)): ?>
                        No invoices match your search criteria.
                    <?php else: ?>
                        Get started by creating your first invoice.
                    <?php endif; ?>
                </p>
                <?php if (empty($search) && empty($status_filter)): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#invoiceModal" <?php echo !$invoiceCheck['allowed'] ? 'disabled title="Upgrade required to create invoices"' : ''; ?>>
                        <i class="fas fa-plus me-2"></i>Create Invoice
                        <?php if (!$invoiceCheck['allowed']): ?>
                            <span class="badge bg-warning ms-2">Upgrade Required</span>
                        <?php endif; ?>
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Invoice Modal -->
<div class="modal fade" id="invoiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="invoiceForm">
                <div class="modal-header">
                    <h5 class="modal-title">Create Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (!$invoiceCheck['allowed']): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Invoice generation is not available in the Free plan.</strong> 
                            Please <a href="subscriptions.php" class="alert-link">upgrade your subscription</a> to use this feature.
                        </div>
                    <?php endif; ?>
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="order_id" class="form-label">Order *</label>
                            <select class="form-select" id="order_id" name="order_id" required>
                                <option value="">Select Order</option>
                                <?php if (empty($unpaidOrders)): ?>
                                    <option value="" disabled>No orders available for invoicing</option>
                                <?php else: ?>
                                    <?php foreach ($unpaidOrders as $order): ?>
                                        <option value="<?php echo $order['id']; ?>" 
                                                data-amount="<?php echo $order['total_amount']; ?>"
                                                data-status="<?php echo $order['status']; ?>">
                                            <?php echo htmlspecialchars($order['order_number'] . ' - ' . $order['first_name'] . ' ' . $order['last_name'] . ' - ' . ucfirst($order['status']) . ' (' . format_currency($order['total_amount']) . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <?php if (empty($unpaidOrders)): ?>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Create an order first, then you can generate an invoice for it.
                                    <a href="orders.php" class="text-decoration-none">Go to Orders</a>
                                </small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="invoice_date" class="form-label">Invoice Date *</label>
                            <input type="date" class="form-control" id="invoice_date" name="invoice_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="due_date" class="form-label">Due Date *</label>
                            <input type="date" class="form-control" id="due_date" name="due_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="subtotal" class="form-label">Subtotal *</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" class="form-control" id="subtotal" name="subtotal" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="tax_rate" class="form-label">GST Rate (%)</label>
                            <select class="form-select" id="tax_rate" name="tax_rate">
                                <option value="0">No GST</option>
                                <option value="5">5% GST</option>
                                <option value="12">12% GST</option>
                                <option value="18" selected>18% GST</option>
                                <option value="28">28% GST</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="tax_amount" class="form-label">GST Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" class="form-control" id="tax_amount" name="tax_amount" step="0.01" min="0" value="0" readonly>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="discount_amount" class="form-label">Discount Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" class="form-control" id="discount_amount" name="discount_amount" step="0.01" min="0" value="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="total_amount" class="form-label">Total Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" class="form-control" id="total_amount" name="total_amount" step="0.01" min="0" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Create Invoice
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="paymentForm" onsubmit="submitPaymentForm(event)">
                <div class="modal-header">
                    <h5 class="modal-title">Add Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="add_payment">
                    <input type="hidden" name="invoice_id" id="paymentInvoiceId">
                    
                    <div class="mb-3">
                        <label class="form-label">Invoice Number</label>
                        <p class="form-control-plaintext" id="paymentInvoiceNumber"></p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="paymentAmount" class="form-label">Amount *</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" class="form-control" id="paymentAmount" name="amount" step="0.01" min="0" required>
                        </div>
                        <small class="text-muted">Maximum: ₹<span id="maxPaymentAmount">0.00</span></small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="paymentMethod" class="form-label">Payment Method *</label>
                        <select class="form-select" id="paymentMethod" name="payment_method" required>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="card">Card</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="paymentDate" class="form-label">Payment Date *</label>
                        <input type="date" class="form-control" id="paymentDate" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="referenceNumber" class="form-label">Reference Number</label>
                        <input type="text" class="form-control" id="referenceNumber" name="reference_number">
                    </div>
                    
                    <div class="mb-3">
                        <label for="paymentNotes" class="form-label">Notes</label>
                        <textarea class="form-control" id="paymentNotes" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus me-2"></i>Add Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function addPayment(invoiceId, invoiceNumber, balanceAmount) {
    document.getElementById('paymentInvoiceId').value = invoiceId;
    document.getElementById('paymentInvoiceNumber').textContent = invoiceNumber;
    document.getElementById('paymentAmount').value = balanceAmount;
    document.getElementById('maxPaymentAmount').textContent = balanceAmount.toFixed(2);
    new bootstrap.Modal(document.getElementById('paymentModal')).show();
}

// Handle payment form submission via AJAX
function submitPaymentForm(event) {
    event.preventDefault();
    
    console.log('Payment form submission started...');
    
    const form = document.getElementById('paymentForm');
    const formData = new FormData(form);
    
    // Debug: Log form data
    console.log('Form data:');
    for (let [key, value] of formData.entries()) {
        console.log(key + ': ' + value);
    }
    
    // Add loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
    submitBtn.disabled = true;
    
    // Make AJAX request
    fetch('ajax/add_payment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();
            
            // Show success message
            showToast('Success', data.message || 'Payment added successfully!', 'success');
            
            // Reload page to show updated data
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            // Show error message
            showToast('Error', data.error || 'Failed to add payment', 'error');
        }
    })
    .catch(error => {
        console.error('Error adding payment:', error);
        showToast('Error', 'Network error occurred. Please try again.', 'error');
    })
    .finally(() => {
        // Restore button state
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

function viewInvoice(invoiceId) {
    window.location.href = 'invoice-details.php?id=' + invoiceId;
}

function printInvoice(invoiceId) {
    window.open('print-invoice.php?id=' + invoiceId, '_blank');
}

function fixAdvancePayments() {
    if (confirm('This will fix all existing invoices to include advance payments from their orders. Continue?')) {
        window.location.href = 'invoices.php?fix_advance=1';
    }
}

// Toast notification function
function showToast(title, message, type = 'info') {
    // Create toast container if it doesn't exist
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }
    
    // Create toast element
    const toastId = 'toast-' + Date.now();
    const toastElement = document.createElement('div');
    toastElement.id = toastId;
    toastElement.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;
    toastElement.setAttribute('role', 'alert');
    toastElement.setAttribute('aria-live', 'assertive');
    toastElement.setAttribute('aria-atomic', 'true');
    
    toastElement.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <strong>${title}</strong><br>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toastElement);
    
    // Show toast
    const toast = new bootstrap.Toast(toastElement, {
        autohide: true,
        delay: 3000
    });
    toast.show();
    
    // Remove toast element after it's hidden
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

function exportInvoices() {
    // Show initial progress message
    showToast('Export', '🔄 Preparing your invoice export... Please wait.', 'info');
    
    // Disable the export button temporarily
    const exportBtn = document.querySelector('button[onclick="exportInvoices()"]');
    const originalText = exportBtn.innerHTML;
    exportBtn.disabled = true;
    exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Exporting...';
    
    // Create FormData for AJAX request
    const formData = new FormData();
    formData.append('csrf_token', '<?php echo generate_csrf_token(); ?>');
    
    // Show progress message
    setTimeout(() => {
        showToast('📊 Generating Excel file with all invoice details...', 'info');
    }, 500);
    
    // Make AJAX request
    fetch('ajax/export_invoices.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Export failed');
        }
        return response.blob();
    })
    .then(blob => {
        // Create download link
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = 'invoices_export_' + new Date().toISOString().slice(0, 19).replace(/:/g, '-') + '.xlsx';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        // Show success message
        showToast('✅ Export completed! Your Excel file has been downloaded.', 'success');
        
        // Re-enable the export button
        exportBtn.disabled = false;
        exportBtn.innerHTML = originalText;
    })
    .catch(error => {
        console.error('Export error:', error);
        showToast('❌ Export failed. Please try again.', 'error');
        
        // Re-enable the export button
        exportBtn.disabled = false;
        exportBtn.innerHTML = originalText;
    });
}

// Calculate totals when values change
document.getElementById('order_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const amount = selectedOption.getAttribute('data-amount');
    if (amount) {
        document.getElementById('subtotal').value = amount;
        calculateTotal();
    }
});

document.getElementById('subtotal').addEventListener('input', calculateTotal);
document.getElementById('tax_rate').addEventListener('input', calculateTotal);
document.getElementById('discount_amount').addEventListener('input', calculateTotal);

function calculateTotal() {
    const subtotal = parseFloat(document.getElementById('subtotal').value) || 0;
    const taxRate = parseFloat(document.getElementById('tax_rate').value) || 0;
    const discountAmount = parseFloat(document.getElementById('discount_amount').value) || 0;
    
    const taxAmount = (subtotal * taxRate) / 100;
    const totalAmount = subtotal + taxAmount - discountAmount;
    
    document.getElementById('tax_amount').value = taxAmount.toFixed(2);
    document.getElementById('total_amount').value = totalAmount.toFixed(2);
}

// Reset modal when closed
document.getElementById('invoiceModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('invoiceForm').reset();
    document.getElementById('invoice_date').value = '<?php echo date('Y-m-d'); ?>';
});

document.getElementById('paymentModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('paymentForm').reset();
    document.getElementById('paymentDate').value = '<?php echo date('Y-m-d'); ?>';
});

// AJAX Invoice Filtering
let filterTimeout;
const searchInput = document.getElementById('searchInput');
const statusFilter = document.getElementById('statusFilter');
const customerFilter = document.getElementById('customerFilter');
const clearFilters = document.getElementById('clearFilters');
const filterResults = document.getElementById('filterResults');
const filterCount = document.getElementById('filterCount');
const invoicesTable = document.querySelector('.table tbody');

// Store original table content
const originalTableContent = invoicesTable.innerHTML;

// Load filter options on page load
loadFilterOptions();

function loadFilterOptions() {
    fetch('ajax/filter_invoices.php?page=1&limit=0')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(text => {
            if (text.trim().startsWith('<')) {
                throw new Error('Server returned HTML instead of JSON');
            }
            
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    populateFilterOptions(data.filter_options);
                } else {
                    console.error('Filter options error:', data.error);
                }
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response text:', text);
            }
        })
        .catch(error => {
            console.error('Error loading filter options:', error);
        });
}

function populateFilterOptions(options) {
    // Populate customers
    const customerSelect = document.getElementById('customerFilter');
    customerSelect.innerHTML = '<option value="">All Customers</option>';
    options.customers.forEach(customer => {
        customerSelect.innerHTML += `<option value="${customer.id}">${customer.name}</option>`;
    });
}

// Add event listeners for all filters
[searchInput, statusFilter, customerFilter].forEach(element => {
    element.addEventListener('change', performFilter);
    if (element === searchInput) {
        element.addEventListener('input', performFilter);
    }
});

clearFilters.addEventListener('click', function() {
    searchInput.value = '';
    statusFilter.value = '';
    customerFilter.value = '';
    invoicesTable.innerHTML = originalTableContent;
    filterResults.style.display = 'none';
});

function performFilter() {
    // Clear previous timeout
    clearTimeout(filterTimeout);
    
    // Debounce search input
    if (this === searchInput) {
        filterTimeout = setTimeout(() => {
            executeFilter();
        }, 300);
    } else {
        executeFilter();
    }
}

function executeFilter() {
    const search = searchInput.value.trim();
    const status = statusFilter.value;
    const customer = customerFilter.value;
    
    // Show loading state
    filterResults.style.display = 'block';
    filterCount.textContent = 'Filtering...';
    
    // Build query string
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (status) params.append('status', status);
    if (customer) params.append('customer_id', customer);
    params.append('page', '1');
    params.append('limit', '<?php echo RECORDS_PER_PAGE; ?>');
    
    fetch(`ajax/filter_invoices.php?${params.toString()}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(text => {
            if (text.trim().startsWith('<')) {
                throw new Error('Server returned HTML instead of JSON. Check for PHP errors.');
            }
            
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    displayFilterResults(data.invoices);
                    filterCount.textContent = data.pagination.total_invoices;
                } else {
                    console.error('Filter error:', data.error);
                    filterCount.textContent = 'Filter failed: ' + (data.error || 'Unknown error');
                }
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response text:', text);
                filterCount.textContent = 'Invalid response from server';
            }
        })
        .catch(error => {
            console.error('Filter error:', error);
            filterCount.textContent = 'Filter failed: ' + error.message;
        });
}

function displayFilterResults(invoices) {
    if (invoices.length === 0) {
        invoicesTable.innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-4">
                    <i class="fas fa-search fa-2x text-muted mb-2"></i>
                    <h5 class="text-muted">No invoices found</h5>
                    <p class="text-muted">Try adjusting your filter criteria</p>
                </td>
            </tr>
        `;
        return;
    }
    
    let tableHTML = '';
    invoices.forEach(invoice => {
        // Format dates
        const invoiceDate = new Date(invoice.invoice_date).toLocaleDateString('en-US', { 
            year: 'numeric', month: 'short', day: 'numeric' 
        });
        const dueDate = new Date(invoice.due_date).toLocaleDateString('en-US', { 
            year: 'numeric', month: 'short', day: 'numeric' 
        });
        
        // Check if overdue
        const isOverdue = new Date(invoice.due_date) < new Date() && invoice.payment_status !== 'paid';
        
        // Helpers
        const statusColors = {
            'paid': 'success',
            'partial': 'warning',
            'due': 'danger'
        };
        const statusColor = statusColors[invoice.payment_status] || 'secondary';
        const statusLabel = invoice.payment_status ? invoice.payment_status.charAt(0).toUpperCase() + invoice.payment_status.slice(1) : 'Status';
        const formatCurrency = (amount) => '₹' + parseFloat(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 });
        const formatTax = (amount) => amount && parseFloat(amount) > 0 ? `<br><small class="text-muted">Tax: ${formatCurrency(amount)}</small>` : '';
        const customerCode = invoice.customer_code ? `<br><small class="text-muted">${invoice.customer_code}</small>` : '';
        
        const orderNumber = invoice.order_number && invoice.order_number !== 'N/A' ? invoice.order_number : 'N/A';
        
        tableHTML += `
            <tr>
                <td>
                    <span class="badge bg-primary">${invoice.invoice_number}</span>
                </td>
                <td>
                    <div>
                        <strong>${invoice.customer_name}</strong>
                        ${customerCode}
                    </div>
                </td>
                <td>
                    <span class="badge bg-info">${orderNumber}</span>
                </td>
                <td>${invoiceDate}</td>
                <td>
                    <span class="${isOverdue ? 'text-danger fw-bold' : ''}">${dueDate}</span>
                </td>
                <td>
                    <strong>${formatCurrency(invoice.total_amount)}</strong>
                    ${formatTax(invoice.tax_amount)}
                </td>
                <td>
                    ${formatCurrency(invoice.paid_amount)}
                </td>
                <td>
                    <span class="badge bg-${statusColor}">${statusLabel}</span>
                </td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" 
                                class="btn btn-outline-info" 
                                onclick="viewInvoice(${invoice.id})"
                                title="View Details" style="border: 1px solid #667eea;">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button type="button" 
                                class="btn btn-outline-success" 
                                onclick="addPayment(${invoice.id}, '${invoice.invoice_number}', ${invoice.balance_amount})"
                                title="Add Payment" style="border: 1px solid #667eea;">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button type="button" 
                                class="btn btn-outline-primary" 
                                onclick="printInvoice(${invoice.id})"
                                title="Print Invoice" style="border: 1px solid #667eea;">
                            <i class="fas fa-print"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    invoicesTable.innerHTML = tableHTML;
}
</script>

<?php require_once 'includes/footer.php'; ?>


<style>
    .btn-outline-info:hover {
        color: #ffffff;
    }
</style>