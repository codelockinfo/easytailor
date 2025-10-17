<?php
/**
 * Invoice Details Page
 * Tailoring Management System
 */

// Include config first (before any output)
require_once '../config/config.php';

// Check if user is logged in
require_login();

require_once 'models/Invoice.php';
require_once 'models/Order.php';
require_once 'models/Payment.php';
require_once 'models/Company.php';

$invoiceId = (int)($_GET['id'] ?? 0);

if (!$invoiceId) {
    header('Location: invoices.php');
    exit;
}

$invoiceModel = new Invoice();
$orderModel = new Order();
$paymentModel = new Payment();
$companyModel = new Company();

// Get invoice with details
$invoices = $invoiceModel->getInvoicesWithDetails(['i.id' => $invoiceId], 1);

if (empty($invoices)) {
    header('Location: invoices.php');
    exit;
}

$invoice = $invoices[0];

// Get order details
$order = $orderModel->find($invoice['order_id']);

// Get payments for this invoice
$payments = $paymentModel->getInvoicePayments($invoiceId);

// Get company details
$companyId = get_company_id();
$company = $companyId ? $companyModel->find($companyId) : null;

// NOW include header
$page_title = 'Invoice Details';
require_once 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <a href="invoices.php" class="text-decoration-none text-muted mb-2 d-inline-block">
                    <i class="fas fa-arrow-left me-2"></i>Back to Invoices
                </a>
                <h1 class="h3 mb-0">Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?></h1>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-primary" onclick="printInvoice()">
                    <i class="fas fa-print me-2"></i>Print
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="emailInvoice()">
                    <i class="fas fa-envelope me-2"></i>Email
                </button>
                <?php if ($invoice['balance_amount'] > 0): ?>
                <button type="button" class="btn btn-success" onclick="addPayment()">
                    <i class="fas fa-plus me-2"></i>Add Payment
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Invoice Status Card -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-3 text-center border-end">
                <h6 class="text-muted mb-1">Invoice Status</h6>
                <span class="badge bg-<?php 
                    echo match($invoice['payment_status']) {
                        'paid' => 'success',
                        'partial' => 'warning',
                        'due' => 'danger',
                        default => 'secondary'
                    };
                ?> fs-5">
                    <?php echo ucfirst($invoice['payment_status']); ?>
                </span>
            </div>
            <div class="col-md-3 text-center border-end">
                <h6 class="text-muted mb-1">Total Amount</h6>
                <h4 class="mb-0"><?php echo format_currency($invoice['total_amount']); ?></h4>
            </div>
            <div class="col-md-3 text-center border-end">
                <h6 class="text-muted mb-1">Paid Amount</h6>
                <h4 class="mb-0 text-success"><?php echo format_currency($invoice['paid_amount']); ?></h4>
            </div>
            <div class="col-md-3 text-center">
                <h6 class="text-muted mb-1">Balance Due</h6>
                <h4 class="mb-0 text-danger"><?php echo format_currency($invoice['balance_amount']); ?></h4>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Invoice Details -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-file-invoice me-2"></i>Invoice Information
                </h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <small class="text-muted d-block">Customer</small>
                        <h5 class="mb-0"><?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?></h5>
                        <small class="text-muted"><?php echo htmlspecialchars($invoice['customer_code']); ?></small>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Order Number</small>
                        <h5 class="mb-0"><?php echo htmlspecialchars($invoice['order_number']); ?></h5>
                        <small class="text-muted">Date: <?php echo format_date($invoice['order_date'], 'M j, Y'); ?></small>
                    </div>
                </div>
                
                <hr>
                
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <tr>
                            <td width="50%"><strong>Subtotal:</strong></td>
                            <td class="text-end"><?php echo format_currency($invoice['subtotal']); ?></td>
                        </tr>
                        <?php if ($invoice['tax_amount'] > 0): ?>
                        <tr>
                            <td>Tax (<?php echo number_format($invoice['tax_rate'], 2); ?>%):</td>
                            <td class="text-end"><?php echo format_currency($invoice['tax_amount']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($invoice['discount_amount'] > 0): ?>
                        <tr>
                            <td>Discount:</td>
                            <td class="text-end text-danger">-<?php echo format_currency($invoice['discount_amount']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="table-light">
                            <td><strong>Total Amount:</strong></td>
                            <td class="text-end"><h5 class="mb-0"><?php echo format_currency($invoice['total_amount']); ?></h5></td>
                        </tr>
                        <?php if ($invoice['paid_amount'] > 0): ?>
                        <tr>
                            <td><strong>Advance Paid:</strong></td>
                            <td class="text-end text-success"><strong><?php echo format_currency($invoice['paid_amount']); ?></strong></td>
                        </tr>
                        <tr class="table-warning">
                            <td><strong>Balance Due:</strong></td>
                            <td class="text-end"><h5 class="mb-0 text-danger"><?php echo format_currency($invoice['balance_amount']); ?></h5></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                
                <?php if (!empty($invoice['notes'])): ?>
                <div class="mt-3 p-3 bg-light rounded">
                    <strong>Notes:</strong><br>
                    <?php echo nl2br(htmlspecialchars($invoice['notes'])); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Payment History -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-money-bill-wave me-2"></i>Payment History
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($payments)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Reference</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo format_date($payment['payment_date'], 'M j, Y'); ?></td>
                                    <td><strong class="text-success"><?php echo format_currency($payment['amount']); ?></strong></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['reference_number'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($payment['notes'] ?? '-'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td><strong>Total Paid:</strong></td>
                                    <td colspan="4">
                                        <strong class="text-success fs-5"><?php echo format_currency($invoice['paid_amount']); ?></strong>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No payments yet</h5>
                        <p class="text-muted">This invoice hasn't received any payments.</p>
                        <?php if ($invoice['balance_amount'] > 0): ?>
                        <button type="button" class="btn btn-success" onclick="addPayment()">
                            <i class="fas fa-plus me-2"></i>Add First Payment
                        </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Quick Actions -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-primary" onclick="printInvoice()">
                        <i class="fas fa-print me-2"></i>Print Invoice
                    </button>
                    <button class="btn btn-outline-primary" onclick="emailInvoice()">
                        <i class="fas fa-envelope me-2"></i>Email to Customer
                    </button>
                    <button class="btn btn-outline-secondary" onclick="downloadPDF()">
                        <i class="fas fa-file-pdf me-2"></i>Download PDF
                    </button>
                    <?php if ($invoice['balance_amount'] > 0): ?>
                    <button class="btn btn-success" onclick="addPayment()">
                        <i class="fas fa-plus me-2"></i>Record Payment
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Invoice Metadata -->
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">Invoice Details</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <small class="text-muted d-block">Invoice Number</small>
                    <strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong>
                </div>
                <div class="mb-3">
                    <small class="text-muted d-block">Invoice Date</small>
                    <strong><?php echo format_date($invoice['invoice_date'], 'M j, Y'); ?></strong>
                </div>
                <div class="mb-3">
                    <small class="text-muted d-block">Due Date</small>
                    <strong class="<?php echo $invoice['due_date'] < date('Y-m-d') && $invoice['payment_status'] !== 'paid' ? 'text-danger' : ''; ?>">
                        <?php echo format_date($invoice['due_date'], 'M j, Y'); ?>
                        <?php if ($invoice['due_date'] < date('Y-m-d') && $invoice['payment_status'] !== 'paid'): ?>
                            <br><small class="text-danger">Overdue!</small>
                        <?php endif; ?>
                    </strong>
                </div>
                <div class="mb-3">
                    <small class="text-muted d-block">Created By</small>
                    <strong><?php echo htmlspecialchars($invoice['created_by_name'] ?? 'N/A'); ?></strong>
                </div>
                <div>
                    <small class="text-muted d-block">Created On</small>
                    <strong><?php echo format_date($invoice['created_at'], 'M j, Y H:i'); ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function printInvoice() {
    window.open('print-invoice.php?id=<?php echo $invoiceId; ?>', '_blank');
}

function emailInvoice() {
    alert('Email functionality will be implemented.\n\nIn production, this would send the invoice to:\n<?php echo htmlspecialchars($invoice['customer_email'] ?? 'customer email'); ?>');
}

function downloadPDF() {
    // Show loading state
    const downloadBtn = document.querySelector('button[onclick="downloadPDF()"]');
    const originalText = downloadBtn.innerHTML;
    downloadBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating PDF...';
    downloadBtn.disabled = true;
    
    // Open print-friendly invoice page in new window
    const printWindow = window.open(`ajax/generate_invoice_pdf_print.php?id=<?php echo $invoiceId; ?>`, '_blank');
    
    // Show success message
    showPDFToast('Success', 'Invoice opened for printing. Use Ctrl+P to save as PDF.', 'success');
    
    // Restore button state after a short delay
    setTimeout(() => {
        downloadBtn.innerHTML = originalText;
        downloadBtn.disabled = false;
    }, 1000);
}

// Toast notification function for PDF
function showPDFToast(title, message, type = 'info') {
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
        delay: 5000
    });
    toast.show();
    
    // Remove toast element after it's hidden
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

function addPayment() {
    // Reset form
    document.getElementById('paymentForm').reset();
    document.getElementById('payment_date').value = '<?php echo date('Y-m-d'); ?>';
    
    // Show modal
    new bootstrap.Modal(document.getElementById('paymentModal')).show();
}

// Handle payment form submission
function submitPaymentForm(event) {
    event.preventDefault();
    
    const form = document.getElementById('paymentForm');
    const formData = new FormData(form);
    
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
            showPaymentToast('Success', data.message, 'success');
            
            // Reload page to show updated data
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            // Show error message
            showPaymentToast('Error', data.error || 'Failed to add payment', 'error');
        }
    })
    .catch(error => {
        console.error('Error adding payment:', error);
        showPaymentToast('Error', 'Network error occurred', 'error');
    })
    .finally(() => {
        // Restore button state
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

// Toast notification function for payments
function showPaymentToast(title, message, type = 'info') {
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
</script>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="paymentForm" onsubmit="submitPaymentForm(event)">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-credit-card me-2"></i>Add Payment
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="invoice_id" value="<?php echo $invoiceId; ?>">
                    
                    <div class="mb-3">
                        <label for="amount" class="form-label">Payment Amount *</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" 
                                   class="form-control" 
                                   id="amount" 
                                   name="amount" 
                                   step="0.01" 
                                   min="0.01" 
                                   max="<?php echo $invoice['balance_amount']; ?>"
                                   required>
                        </div>
                        <div class="form-text">
                            Maximum: ₹<?php echo number_format($invoice['balance_amount'], 2); ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Payment Method *</label>
                        <select class="form-select" id="payment_method" name="payment_method" required>
                            <option value="">Select Payment Method</option>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="check">Check</option>
                            <option value="upi">UPI</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_date" class="form-label">Payment Date *</label>
                        <input type="date" 
                               class="form-control" 
                               id="payment_date" 
                               name="payment_date" 
                               value="<?php echo date('Y-m-d'); ?>"
                               required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" 
                                  id="notes" 
                                  name="notes" 
                                  rows="3" 
                                  placeholder="Optional payment notes..."></textarea>
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

<?php require_once 'includes/footer.php'; ?>

