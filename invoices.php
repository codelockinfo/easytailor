<?php
/**
 * Invoices Page
 * Tailoring Management System
 */

$page_title = 'Invoice Management';
require_once 'includes/header.php';

require_once 'models/Invoice.php';
require_once 'models/Order.php';
require_once 'models/Payment.php';

$invoiceModel = new Invoice();
$orderModel = new Order();
$paymentModel = new Payment();

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                $data = [
                    'order_id' => (int)$_POST['order_id'],
                    'invoice_date' => $_POST['invoice_date'],
                    'due_date' => $_POST['due_date'],
                    'subtotal' => (float)$_POST['subtotal'],
                    'tax_rate' => (float)$_POST['tax_rate'],
                    'tax_amount' => (float)$_POST['tax_amount'],
                    'discount_amount' => (float)$_POST['discount_amount'],
                    'total_amount' => (float)$_POST['total_amount'],
                    'paid_amount' => 0,
                    'balance_amount' => (float)$_POST['total_amount'],
                    'notes' => sanitize_input($_POST['notes']),
                    'created_by' => get_user_id()
                ];
                
                $invoiceId = $invoiceModel->createInvoice($data);
                if ($invoiceId) {
                    $message = 'Invoice created successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to create invoice';
                    $messageType = 'error';
                }
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
                    $message = 'Payment added successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to add payment';
                    $messageType = 'error';
                }
                break;
                
            case 'delete_payment':
                $paymentId = (int)$_POST['payment_id'];
                if ($paymentModel->deletePayment($paymentId)) {
                    $message = 'Payment deleted successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to delete payment';
                    $messageType = 'error';
                }
                break;
        }
    } else {
        $message = 'Invalid request';
        $messageType = 'error';
    }
}

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

$invoices = $invoiceModel->getInvoicesWithDetails($conditions, $limit, $offset);
$totalInvoices = $invoiceModel->count($conditions);
$totalPages = ceil($totalInvoices / $limit);

// Get unpaid orders for creating invoices
$unpaidOrders = $orderModel->getOrdersWithDetails(['status' => 'completed'], 50);
$unpaidOrders = array_filter($unpaidOrders, function($order) {
    return $order['balance_amount'] > 0;
});

// Get invoice statistics
$invoiceStats = $invoiceModel->getInvoiceStats();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="h3 mb-0">Invoice Management</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#invoiceModal">
                <i class="fas fa-plus me-2"></i>Create Invoice
            </button>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Invoice Statistics -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
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
    
    <div class="col-xl-3 col-md-6 mb-3">
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
    
    <div class="col-xl-3 col-md-6 mb-3">
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
    
    <div class="col-xl-3 col-md-6 mb-3">
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
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" 
                           class="form-control" 
                           name="search" 
                           placeholder="Search invoices..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select class="form-select" name="status">
                    <option value="">All Statuses</option>
                    <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="partial" <?php echo $status_filter === 'partial' ? 'selected' : ''; ?>>Partial</option>
                    <option value="due" <?php echo $status_filter === 'due' ? 'selected' : ''; ?>>Due</option>
                </select>
            </div>
            <div class="col-md-2">
                <div class="d-grid gap-2 d-md-flex">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Filter
                    </button>
                    <?php if (!empty($search) || !empty($status_filter)): ?>
                        <a href="invoices.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>Clear
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
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
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportInvoices()">
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
                                            title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" 
                                            class="btn btn-outline-success" 
                                            onclick="addPayment(<?php echo $invoice['id']; ?>, '<?php echo htmlspecialchars($invoice['invoice_number']); ?>', <?php echo $invoice['balance_amount']; ?>)"
                                            title="Add Payment">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button type="button" 
                                            class="btn btn-outline-primary" 
                                            onclick="printInvoice(<?php echo $invoice['id']; ?>)"
                                            title="Print Invoice">
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
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#invoiceModal">
                        <i class="fas fa-plus me-2"></i>Create Invoice
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
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="order_id" class="form-label">Order *</label>
                            <select class="form-select" id="order_id" name="order_id" required>
                                <option value="">Select Order</option>
                                <?php foreach ($unpaidOrders as $order): ?>
                                    <option value="<?php echo $order['id']; ?>" data-amount="<?php echo $order['balance_amount']; ?>">
                                        <?php echo htmlspecialchars($order['order_number'] . ' - ' . $order['first_name'] . ' ' . $order['last_name'] . ' (' . format_currency($order['balance_amount']) . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="subtotal" name="subtotal" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="tax_rate" class="form-label">Tax Rate (%)</label>
                            <input type="number" class="form-control" id="tax_rate" name="tax_rate" step="0.01" min="0" max="100" value="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="tax_amount" class="form-label">Tax Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="tax_amount" name="tax_amount" step="0.01" min="0" value="0" readonly>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="discount_amount" class="form-label">Discount Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="discount_amount" name="discount_amount" step="0.01" min="0" value="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="total_amount" class="form-label">Total Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
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
            <form method="POST" id="paymentForm">
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
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="paymentAmount" name="amount" step="0.01" min="0" required>
                        </div>
                        <small class="text-muted">Maximum: $<span id="maxPaymentAmount">0.00</span></small>
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

function viewInvoice(invoiceId) {
    window.location.href = 'invoice-details.php?id=' + invoiceId;
}

function printInvoice(invoiceId) {
    window.open('print-invoice.php?id=' + invoiceId, '_blank');
}

function exportInvoices() {
    showToast('Export functionality will be implemented', 'info');
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
</script>

<?php require_once 'includes/footer.php'; ?>

