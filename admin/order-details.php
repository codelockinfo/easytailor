<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="favicon(2).png">

<?php
/**
 * Order Details Page
 * Tailoring Management System
 */

// Include config first (before any output)
require_once '../config/config.php';

// Check if user is logged in
require_login();

require_once 'models/Order.php';
require_once 'models/Customer.php';
require_once 'models/Invoice.php';
require_once 'models/Measurement.php';
require_once 'models/ClothType.php';
require_once 'models/User.php';

// Get order ID
$orderId = (int)($_GET['id'] ?? 0);

if (!$orderId) {
    header('Location: orders.php');
    exit;
}

// Initialize models
$orderModel = new Order();
$customerModel = new Customer();
$invoiceModel = new Invoice();
$measurementModel = new Measurement();
$clothTypeModel = new ClothType();
$userModel = new User();

// Get order details
$orders = $orderModel->getOrdersWithDetails(['o.id' => $orderId], 1);

if (empty($orders)) {
    header('Location: orders.php');
    exit;
}

$order = $orders[0];

// Get customer details
$customer = $customerModel->find($order['customer_id']);

// Get measurement details if available
$measurement = null;
if ($order['measurement_id']) {
    $measurements = $measurementModel->getMeasurementsWithDetails(['m.id' => $order['measurement_id']], 1);
    if (!empty($measurements)) {
        $measurement = $measurements[0];
        $measurement['measurement_data'] = json_decode($measurement['measurement_data'], true);
    }
}

// Get cloth type details
$clothType = $clothTypeModel->find($order['cloth_type_id']);

// Get assigned tailor details
$tailor = null;
if ($order['assigned_tailor_id']) {
    $tailor = $userModel->find($order['assigned_tailor_id']);
}

// Get invoices for this order
$invoices = $invoiceModel->findAll(['order_id' => $orderId]);

// NOW include header
$page_title = 'Order Details';
require_once 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <a href="orders.php" class="text-decoration-none text-muted mb-2 d-inline-block">
                    <i class="fas fa-arrow-left me-2"></i>Back to Orders
                </a>
                <h1 class="h3 mb-0">Order Details</h1>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-primary" onclick="printOrder()">
                    <i class="fas fa-print me-2"></i>Print
                </button>
                <?php if (empty($invoices)): ?>
                <a href="invoices.php" class="btn btn-success">
                    <i class="fas fa-file-invoice me-2"></i>Generate Invoice
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Order Status Card -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-2 text-center border-end">
                <h6 class="text-muted mb-1">Order Number</h6>
                <h4 class="mb-0 text-primary"><?php echo htmlspecialchars($order['order_number']); ?></h4>
            </div>
            <div class="col-md-2 text-center border-end">
                <h6 class="text-muted mb-1">Status</h6>
                <?php
                $statusColors = [
                    'pending' => 'secondary',
                    'in_progress' => 'info',
                    'completed' => 'success',
                    'delivered' => 'primary',
                    'cancelled' => 'danger'
                ];
                $color = $statusColors[$order['status']] ?? 'secondary';
                ?>
                <h5 class="mb-0">
                    <span class="badge bg-<?php echo $color; ?> fs-6">
                        <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                    </span>
                </h5>
            </div>
            <div class="col-md-2 text-center border-end">
                <h6 class="text-muted mb-1">Total Amount</h6>
                <h4 class="mb-0"><?php echo format_currency($order['total_amount']); ?></h4>
            </div>
            <div class="col-md-2 text-center border-end">
                <h6 class="text-muted mb-1">Paid</h6>
                <h4 class="mb-0 text-success"><?php echo format_currency($order['advance_amount']); ?></h4>
            </div>
            <div class="col-md-2 text-center border-end">
                <h6 class="text-muted mb-1">Balance</h6>
                <h4 class="mb-0 text-danger"><?php echo format_currency($order['balance_amount']); ?></h4>
            </div>
            <div class="col-md-2 text-center">
                <h6 class="text-muted mb-1">Due Date</h6>
                <?php
                $dueDate = strtotime($order['due_date']);
                $isOverdue = $dueDate < time() && !in_array($order['status'], ['completed', 'delivered', 'cancelled']);
                ?>
                <strong class="<?php echo $isOverdue ? 'text-danger' : ''; ?>">
                    <?php echo format_date($order['due_date'], 'M j, Y'); ?>
                    <?php if ($isOverdue): ?>
                        <br><small class="text-danger">Overdue!</small>
                    <?php endif; ?>
                </strong>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Left Column -->
    <div class="col-lg-4 mb-4">
        <!-- Customer Information -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h6 class="card-title mb-0">
                    <i class="fas fa-user me-2"></i>Customer Information
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <small class="text-muted d-block">Name</small>
                    <h5 class="mb-0">
                        <a href="customer-details.php?id=<?php echo $customer['id']; ?>" class="text-decoration-none">
                            <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                        </a>
                    </h5>
                    <small class="text-muted"><?php echo htmlspecialchars($customer['customer_code']); ?></small>
                </div>
                <div class="mb-3">
                    <small class="text-muted d-block">Phone</small>
                    <strong>
                        <a href="tel:<?php echo htmlspecialchars($customer['phone']); ?>" class="text-decoration-none">
                            <i class="fas fa-phone me-1"></i>
                            <?php echo htmlspecialchars($customer['phone']); ?>
                        </a>
                    </strong>
                </div>
                <?php if (!empty($customer['email'])): ?>
                <div class="mb-3">
                    <small class="text-muted d-block">Email</small>
                    <strong>
                        <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>" class="text-decoration-none">
                            <i class="fas fa-envelope me-1"></i>
                            <?php echo htmlspecialchars($customer['email']); ?>
                        </a>
                    </strong>
                </div>
                <?php endif; ?>
                <?php if (!empty($customer['address'])): ?>
                <div>
                    <small class="text-muted d-block">Address</small>
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($customer['address'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Assigned Tailor -->
        <?php if ($tailor): ?>
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h6 class="card-title mb-0">
                    <i class="fas fa-cut me-2"></i>Assigned Tailor
                </h6>
            </div>
            <div class="card-body">
                <h5 class="mb-1"><?php echo htmlspecialchars($tailor['full_name']); ?></h5>
                <?php if (!empty($tailor['phone'])): ?>
                    <p class="mb-0">
                        <i class="fas fa-phone me-1"></i>
                        <?php echo htmlspecialchars($tailor['phone']); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Timeline -->
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h6 class="card-title mb-0">
                    <i class="fas fa-clock me-2"></i>Timeline
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <small class="text-muted d-block">Order Date</small>
                    <strong><?php echo format_date($order['order_date'], 'M j, Y'); ?></strong>
                </div>
                <div class="mb-3">
                    <small class="text-muted d-block">Due Date</small>
                    <strong class="<?php echo $isOverdue ? 'text-danger' : ''; ?>">
                        <?php echo format_date($order['due_date'], 'M j, Y'); ?>
                    </strong>
                </div>
                <?php if ($order['delivery_date']): ?>
                <div class="mb-3">
                    <small class="text-muted d-block">Delivery Date</small>
                    <strong class="text-success"><?php echo format_date($order['delivery_date'], 'M j, Y'); ?></strong>
                </div>
                <?php endif; ?>
                <div>
                    <small class="text-muted d-block">Created On</small>
                    <strong><?php echo format_date($order['created_at'], 'M j, Y H:i'); ?></strong>
                    <?php if (!empty($order['created_by_name'])): ?>
                        <br><small class="text-muted">by <?php echo htmlspecialchars($order['created_by_name']); ?></small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Right Column -->
    <div class="col-lg-8">
        <!-- Order Details -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-shopping-bag me-2"></i>Order Information
                </h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <small class="text-muted d-block">Cloth Type</small>
                        <h5 class="mb-0">
                            <?php echo htmlspecialchars($order['cloth_type_name'] ?? 'N/A'); ?>
                            <?php if ($clothType && !empty($clothType['category'])): ?>
                                <span class="badge bg-info ms-2"><?php echo htmlspecialchars($clothType['category']); ?></span>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Standard Rate</small>
                        <h5 class="mb-0">
                            <?php echo $clothType && $clothType['standard_rate'] ? format_currency($clothType['standard_rate']) : 'N/A'; ?>
                        </h5>
                    </div>
                </div>
                
                <?php if (!empty($order['special_instructions'])): ?>
                <div class="alert alert-warning">
                    <h6 class="alert-heading">
                        <i class="fas fa-exclamation-circle me-2"></i>Special Instructions
                    </h6>
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['special_instructions'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Measurement Details -->
        <?php if ($measurement): ?>
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h6 class="card-title mb-0">
                    <i class="fas fa-ruler me-2"></i>Measurements
                </h6>
            </div>
            <div class="card-body">
                <?php if ($measurement['measurement_data'] && is_array($measurement['measurement_data'])): ?>
                    <div class="row">
                        <?php foreach ($measurement['measurement_data'] as $key => $value): ?>
                            <?php if (!empty($value)): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h6 class="text-muted mb-1 text-uppercase" style="font-size: 0.75rem;">
                                            <?php echo ucfirst(str_replace('_', ' ', $key)); ?>
                                        </h6>
                                        <h4 class="mb-0 text-primary">
                                            <strong><?php echo htmlspecialchars($value); ?></strong>
                                        </h4>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No measurement data available</p>
                <?php endif; ?>
                
                <?php if (!empty($measurement['notes'])): ?>
                    <div class="alert alert-info mt-3 mb-0">
                        <strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($measurement['notes'])); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="card mb-4">
            <div class="card-body text-center">
                <i class="fas fa-ruler fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No Measurement Attached</h5>
                <p class="text-muted">This order doesn't have measurements linked to it.</p>
                <a href="measurements.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-plus me-2"></i>Add Measurement
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Invoices -->
        <div class="card mb-4">
            <div class="card-header bg-warning">
                <h6 class="card-title mb-0">
                    <i class="fas fa-file-invoice me-2"></i>Invoices (<?php echo count($invoices); ?>)
                </h6>
            </div>
            <div class="card-body">
                <?php if (!empty($invoices)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Date</th>
                                    <th>Total</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong></td>
                                    <td><?php echo format_date($invoice['invoice_date'], 'M j, Y'); ?></td>
                                    <td><?php echo format_currency($invoice['total_amount']); ?></td>
                                    <td class="text-success"><?php echo format_currency($invoice['paid_amount']); ?></td>
                                    <td class="text-danger"><?php echo format_currency($invoice['balance_amount']); ?></td>
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
                                        <a href="invoice-details.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-3">
                        <i class="fas fa-file-invoice fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-2">No invoice generated yet</p>
                        <a href="invoices.php" class="btn btn-sm btn-success">
                            <i class="fas fa-plus me-2"></i>Generate Invoice
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Update Status -->
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-tasks me-2"></i>Update Order Status
                </h6>
            </div>
            <div class="card-body">
                <form method="POST" action="orders.php">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                    
                    <div class="row align-items-end">
                        <div class="col-md-8">
                            <label for="status" class="form-label">Change Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_progress" <?php echo $order['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo $order['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save me-2"></i>Update
                            </button>
                        </div>
                    </div>
                </form>
                
                <div class="mt-3">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Status progression: Pending → In Progress → Completed → Delivered
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function printOrder() {
    window.print();
}

@media print {
    .no-print, .btn, .card-header {
        display: none !important;
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>

