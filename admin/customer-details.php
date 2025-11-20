<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="../favicon(2).png">

<?php
/**
 * Customer Details Page
 * Tailoring Management System
 */

$page_title = 'Customer Details';
require_once 'includes/header.php';

require_once 'models/Customer.php';
require_once 'models/Order.php';
require_once 'models/Invoice.php';
require_once 'models/Payment.php';
require_once 'models/Measurement.php';

// Get customer ID
$customer_id = (int)($_GET['id'] ?? 0);

if (!$customer_id) {
    header('Location: customers.php');
    exit;
}

// Initialize models
$customerModel = new Customer();
$orderModel = new Order();
$invoiceModel = new Invoice();
$paymentModel = new Payment();
$measurementModel = new Measurement();

// Get customer details
$customer = $customerModel->find($customer_id);

if (!$customer) {
    header('Location: customers.php');
    exit;
}

// Get customer orders
$orders = $orderModel->getCustomerOrders($customer_id);

// Get customer invoices
$invoices = $invoiceModel->getCustomerInvoices($customer_id);

// Get customer measurements
$measurements = $measurementModel->getCustomerMeasurements($customer_id);

// Calculate customer statistics
$totalOrders = count($orders);
$totalSpent = 0;
$totalPending = 0;
$totalPaid = 0;

foreach ($invoices as $invoice) {
    $totalSpent += $invoice['total_amount'];
    $totalPaid += $invoice['paid_amount'];
    $totalPending += $invoice['balance_amount'];
}

// Get recent payments for this customer
$customerPayments = [];
foreach ($invoices as $invoice) {
    $payments = $paymentModel->getInvoicePayments($invoice['id']);
    // Ensure invoice_id is set for each payment
    foreach ($payments as &$payment) {
        if (!isset($payment['invoice_id'])) {
            $payment['invoice_id'] = $invoice['id'];
        }
    }
    unset($payment); // Unset reference
    $customerPayments = array_merge($customerPayments, $payments);
}

// Sort payments by date
usort($customerPayments, function($a, $b) {
    return strtotime($b['payment_date']) - strtotime($a['payment_date']);
});

// Order status counts
$orderStatusCounts = [
    'pending' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'delivered' => 0,
    'cancelled' => 0
];

foreach ($orders as $order) {
    if (isset($orderStatusCounts[$order['status']])) {
        $orderStatusCounts[$order['status']]++;
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <a href="customers.php" class="text-decoration-none text-muted mb-2 d-inline-block">
                    <i class="fas fa-arrow-left me-2"></i>Back to Customers
                </a>
                <h1 class="h3 mb-0">Customer Profile</h1>
            </div>
            <div class="d-flex gap-2">
                <a href="orders.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>New Order
                </a>
                <button type="button" class="btn btn-outline-primary" onclick="printProfile()">
                    <i class="fas fa-print me-2"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Customer Information Card -->
<div class="row mb-4">
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-user me-2"></i>Customer Information
                </h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <div class="avatar-circle bg-primary text-white mx-auto mb-3" style="width: 100px; height: 100px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem;">
                        <?php echo strtoupper(substr($customer['first_name'], 0, 1) . substr($customer['last_name'], 0, 1)); ?>
                    </div>
                    <h5 class="mb-1"><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></h5>
                    <p class="text-muted mb-2">
                        <span class="badge bg-primary fs-6"><?php echo htmlspecialchars($customer['customer_code']); ?></span>
                    </p>
                    <span class="badge bg-<?php echo $customer['status'] === 'active' ? 'success' : 'secondary'; ?>">
                        <?php echo ucfirst($customer['status']); ?>
                    </span>
                </div>
                
                <hr>
                
                <div class="customer-details">
                    <?php if (!empty($customer['phone'])): ?>
                    <div class="mb-3">
                        <small class="text-muted d-block">Phone</small>
                        <strong>
                            <i class="fas fa-phone text-primary me-2"></i>
                            <a href="tel:<?php echo htmlspecialchars($customer['phone']); ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($customer['phone']); ?>
                            </a>
                        </strong>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($customer['email'])): ?>
                    <div class="mb-3">
                        <small class="text-muted d-block">Email</small>
                        <strong>
                            <i class="fas fa-envelope text-primary me-2"></i>
                            <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($customer['email']); ?>
                            </a>
                        </strong>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($customer['address'])): ?>
                    <div class="mb-3">
                        <small class="text-muted d-block">Address</small>
                        <strong>
                            <i class="fas fa-map-marker-alt text-primary me-2"></i>
                            <?php echo nl2br(htmlspecialchars($customer['address'])); ?>
                            <?php if (!empty($customer['city']) || !empty($customer['state'])): ?>
                                <br>
                                <?php echo htmlspecialchars($customer['city']); ?>
                                <?php if (!empty($customer['city']) && !empty($customer['state'])): ?>, <?php endif; ?>
                                <?php echo htmlspecialchars($customer['state']); ?>
                                <?php if (!empty($customer['postal_code'])): ?>
                                    - <?php echo htmlspecialchars($customer['postal_code']); ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </strong>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($customer['date_of_birth'])): ?>
                    <div class="mb-3">
                        <small class="text-muted d-block">Date of Birth</small>
                        <strong>
                            <i class="fas fa-birthday-cake text-primary me-2"></i>
                            <?php echo format_date($customer['date_of_birth']); ?>
                        </strong>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <small class="text-muted d-block">Customer Since</small>
                        <strong>
                            <i class="fas fa-calendar text-primary me-2"></i>
                            <?php echo format_date($customer['created_at']); ?>
                        </strong>
                    </div>
                    
                    <?php if (!empty($customer['notes'])): ?>
                    <div class="mb-3">
                        <small class="text-muted d-block">Notes</small>
                        <div class="alert alert-info mb-0">
                            <small><?php echo nl2br(htmlspecialchars($customer['notes'])); ?></small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card border-left-primary h-100">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Orders
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $totalOrders; ?>
                        </div>
                        <small class="text-muted">
                            <i class="fas fa-shopping-bag"></i> All time
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card border-left-success h-100">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Total Spent
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo format_currency($totalSpent); ?>
                        </div>
                        <small class="text-muted">
                            <i class="fas fa-coins"></i> Lifetime value
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card border-left-info h-100">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Total Paid
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo format_currency($totalPaid); ?>
                        </div>
                        <small class="text-muted">
                            <i class="fas fa-check-circle"></i> Completed
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card border-left-warning h-100">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Pending
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo format_currency($totalPending); ?>
                        </div>
                        <small class="text-muted">
                            <i class="fas fa-clock"></i> Outstanding
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Order Status Distribution -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="fas fa-chart-pie me-2"></i>Order Status Distribution
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col">
                        <div class="mb-1">
                            <span class="badge bg-secondary fs-4"><?php echo $orderStatusCounts['pending']; ?></span>
                        </div>
                        <small class="text-muted">Pending</small>
                    </div>
                    <div class="col">
                        <div class="mb-1">
                            <span class="badge bg-info fs-4"><?php echo $orderStatusCounts['in_progress']; ?></span>
                        </div>
                        <small class="text-muted">In Progress</small>
                    </div>
                    <div class="col">
                        <div class="mb-1">
                            <span class="badge bg-success fs-4"><?php echo $orderStatusCounts['completed']; ?></span>
                        </div>
                        <small class="text-muted">Completed</small>
                    </div>
                    <div class="col">
                        <div class="mb-1">
                            <span class="badge bg-primary fs-4"><?php echo $orderStatusCounts['delivered']; ?></span>
                        </div>
                        <small class="text-muted">Delivered</small>
                    </div>
                    <div class="col">
                        <div class="mb-1">
                            <span class="badge bg-danger fs-4"><?php echo $orderStatusCounts['cancelled']; ?></span>
                        </div>
                        <small class="text-muted">Cancelled</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs for Orders, Payments, and Measurements -->
<div class="card">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs" id="customerTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders" type="button" role="tab">
                    <i class="fas fa-shopping-bag me-2"></i>Orders (<?php echo count($orders); ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="invoices-tab" data-bs-toggle="tab" data-bs-target="#invoices" type="button" role="tab">
                    <i class="fas fa-file-invoice me-2"></i>Invoices (<?php echo count($invoices); ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab">
                    <i class="fas fa-money-bill-wave me-2"></i>Payments (<?php echo count($customerPayments); ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="measurements-tab" data-bs-toggle="tab" data-bs-target="#measurements" type="button" role="tab">
                    <i class="fas fa-ruler me-2"></i>Measurements (<?php echo count($measurements); ?>)
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content" id="customerTabsContent">
            <!-- Orders Tab -->
            <div class="tab-pane fade show active" id="orders" role="tabpanel">
                <?php if (!empty($orders)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Date</th>
                                    <th>Cloth Type</th>
                                    <th>Due Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                    </td>
                                    <td><?php echo format_date($order['order_date']); ?></td>
                                    <td><?php echo htmlspecialchars($order['cloth_type_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php 
                                        $due_date = strtotime($order['due_date']);
                                        $is_overdue = $due_date < time() && !in_array($order['status'], ['completed', 'delivered', 'cancelled']);
                                        ?>
                                        <span class="<?php echo $is_overdue ? 'text-danger' : ''; ?>">
                                            <?php echo format_date($order['due_date']); ?>
                                            <?php if ($is_overdue): ?>
                                                <i class="fas fa-exclamation-triangle ms-1"></i>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo format_currency($order['total_amount']); ?></strong>
                                    </td>
                                    <td>
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
                                        <span class="badge bg-<?php echo $color; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary" title="View Order">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No orders yet</h5>
                        <p class="text-muted">This customer hasn't placed any orders.</p>
                        <a href="orders.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Create First Order
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Invoices Tab -->
            <div class="tab-pane fade" id="invoices" role="tabpanel">
                <?php if (!empty($invoices)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Order #</th>
                                    <th>Date</th>
                                    <th>Total Amount</th>
                                    <th>Paid Amount</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($invoice['order_number']); ?></td>
                                    <td><?php echo format_date($invoice['invoice_date']); ?></td>
                                    <td><?php echo format_currency($invoice['total_amount']); ?></td>
                                    <td class="text-success">
                                        <strong><?php echo format_currency($invoice['paid_amount']); ?></strong>
                                    </td>
                                    <td class="text-danger">
                                        <strong><?php echo format_currency($invoice['balance_amount']); ?></strong>
                                    </td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'paid' => 'success',
                                            'partial' => 'warning',
                                            'due' => 'danger'
                                        ];
                                        $color = $statusColors[$invoice['payment_status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>">
                                            <?php echo ucfirst($invoice['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="invoice-details.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-outline-primary" title="View Invoice">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No invoices yet</h5>
                        <p class="text-muted">No invoices have been generated for this customer.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Payments Tab -->
            <div class="tab-pane fade" id="payments" role="tabpanel">
                <?php if (!empty($customerPayments)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Reference</th>
                                    <th>Notes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customerPayments as $payment): ?>
                                <tr>
                                    <td><?php echo format_date($payment['payment_date']); ?></td>
                                    <td>
                                        <strong class="text-success"><?php echo format_currency($payment['amount']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($payment['reference_number'] ?? '-'); ?>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($payment['notes'] ?? '-'); ?></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($payment['invoice_id'])): ?>
                                        <a href="invoice-details.php?id=<?php echo $payment['invoice_id']; ?>" class="btn btn-sm btn-outline-primary" title="View Invoice">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-info">
                                    <td><strong>Total Paid</strong></td>
                                    <td colspan="5">
                                        <strong class="text-success fs-5">
                                            <?php echo format_currency($totalPaid); ?>
                                        </strong>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No payments yet</h5>
                        <p class="text-muted">No payments have been recorded for this customer.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Measurements Tab -->
            <div class="tab-pane fade" id="measurements" role="tabpanel">
                <?php if (!empty($measurements)): ?>
                    <div class="row">
                        <?php foreach ($measurements as $measurement): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">
                                        <i class="fas fa-ruler me-2"></i>
                                        <?php echo htmlspecialchars($measurement['cloth_type_name'] ?? 'Measurement'); ?>
                                    </h6>
                                    <small class="text-muted">
                                        Recorded on <?php echo format_date($measurement['created_at']); ?>
                                    </small>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $measurementData = json_decode($measurement['measurement_data'], true);
                                    if ($measurementData && is_array($measurementData)):
                                    ?>
                                        <div class="row">
                                            <?php foreach ($measurementData as $key => $value): ?>
                                                <?php if (!empty($value)): ?>
                                                <div class="col-6 mb-2">
                                                    <small class="text-muted d-block"><?php echo ucfirst(str_replace('_', ' ', $key)); ?></small>
                                                    <strong><?php echo htmlspecialchars($value); ?></strong>
                                                </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted">No measurement data available</p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($measurement['notes'])): ?>
                                        <hr>
                                        <div class="alert alert-info mb-0">
                                            <small><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($measurement['notes'])); ?></small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer bg-light">
                                    <a href="measurements.php?id=<?php echo $measurement['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i>View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-ruler fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No measurements yet</h5>
                        <p class="text-muted">No measurements have been recorded for this customer.</p>
                        <a href="measurements.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add Measurement
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.border-left-primary {
    border-left: 4px solid #4e73df !important;
}

.border-left-success {
    border-left: 4px solid #1cc88a !important;
}

.border-left-info {
    border-left: 4px solid #36b9cc !important;
}

.border-left-warning {
    border-left: 4px solid #f6c23e !important;
}

.text-xs {
    font-size: 0.7rem;
}

@media (max-width: 768px) {
    .nav-item {
        margin: 0.25rem 0;
    }
    .card-body .border-end {
        border-right: none !important;
    }
    .col-12 .d-flex {
        display: block !important;
    }
    .d-flex.gap-2 {
        margin-top: 10px;
        display: flex !important;
        gap: 10px !important;
        flex-direction: column;
    }
    .card-body .avatar-circle {
        width: 80px !important;
        height: 80px !important;
        font-size: 32px !important;
    }
    .badge.bg-primary.fs-6 {
        font-size: 14px !important;
    }
}

@media print {
    .btn, .nav-tabs, .card-header-tabs {
        display: none !important;
    }
}
</style>

<script>
function printProfile() {
    window.print();
}
</script>

<?php require_once 'includes/footer.php'; ?>

