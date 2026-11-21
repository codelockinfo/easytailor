<?php
$page_title = 'Customer Details';
require_once 'includes/header.php';

require_once '../models/Customer.php';
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

// Get customer orders (passing $customer object to match by ID, measurement_id, or name)
$orders = $orderModel->getCustomerOrders($customer_id, $customer);

// Get customer invoices
$invoices = $invoiceModel->getCustomerInvoices($customer_id, $customer);

// Get customer measurements
$measurements = $measurementModel->getCustomerMeasurements($customer_id);

// Calculate customer statistics
$totalOrders = count($orders);
$totalSpent = 0;
$totalPending = 0;
$totalPaid = 0;

if (!empty($invoices)) {
    foreach ($invoices as $invoice) {
        $totalSpent += (float)($invoice['total_amount'] ?? 0);
        $totalPaid += (float)($invoice['paid_amount'] ?? 0);
        $totalPending += (float)($invoice['balance_amount'] ?? 0);
    }
} else {
    // If no separate invoices exist yet, calculate directly from customer orders
    foreach ($orders as $order) {
        $totalSpent += (float)($order['total_amount'] ?? 0);
        $totalPaid += (float)($order['advance_amount'] ?? 0);
        $totalPending += (float)($order['balance_amount'] ?? 0);
    }
}

// Get recent payments for this customer
$customerPayments = [];
foreach ($invoices as $invoice) {
    $payments = $paymentModel->getInvoicePayments($invoice['id']);
    foreach ($payments as &$payment) {
        if (!isset($payment['invoice_id'])) {
            $payment['invoice_id'] = $invoice['id'];
        }
    }
    unset($payment);
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

$initials = strtoupper(substr($customer['first_name'] ?? '', 0, 1) . substr($customer['last_name'] ?? '', 0, 1));
if (empty($initials)) {
    $initials = 'CU';
}
?>

<!-- Scoped Custom Styling to guarantee clean, modern design without unwanted purple headers -->
<style>
.cust-detail-page .card {
    background: #ffffff !important;
    border: 1px solid #f1f5f9 !important;
    border-radius: 16px !important;
    box-shadow: 0 2px 12px rgba(15, 23, 42, 0.03) !important;
    overflow: hidden;
}

.cust-detail-page .card-header {
    background: #ffffff !important;
    color: #1e293b !important;
    border-bottom: 1px solid #f1f5f9 !important;
    padding: 0.95rem 1.25rem !important;
    border-radius: 16px 16px 0 0 !important;
}

/* Stat Cards */
.cust-stat-card {
    background: #ffffff;
    border: 1px solid #f1f5f9;
    border-radius: 14px;
    padding: 1.1rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.02);
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.cust-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
}
.cust-stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

/* Status Chips */
.cust-status-chip {
    padding: 12px 8px;
    border-radius: 12px;
    text-align: center;
    transition: transform 0.15s ease;
}
.cust-status-chip:hover {
    transform: translateY(-2px);
}

/* Segmented Tabs */
.cust-segmented-tabs-wrapper {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 4px;
    border-radius: 12px;
    display: inline-flex;
    flex-wrap: wrap;
    gap: 4px;
}
.cust-segmented-tabs-wrapper .nav-link {
    color: #64748b !important;
    font-weight: 600 !important;
    font-size: 0.83rem !important;
    padding: 7px 16px !important;
    border-radius: 9px !important;
    border: none !important;
    background: transparent !important;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}
.cust-segmented-tabs-wrapper .nav-link:hover {
    color: #1e293b !important;
    background: #edf2f7 !important;
}
.cust-segmented-tabs-wrapper .nav-link.active {
    background: #ffffff !important;
    color: #4f46e5 !important;
    font-weight: 700 !important;
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.12) !important;
}
.cust-tab-badge {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 20px;
    font-size: 0.72rem;
    font-weight: 700;
    background: #e2e8f0;
    color: #64748b;
}
.cust-segmented-tabs-wrapper .nav-link.active .cust-tab-badge {
    background: #ede9fe;
    color: #4f46e5;
}

/* Info Tiles in Profile Card */
.cust-info-tile {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px dashed #f1f5f9;
}
.cust-info-tile:last-child {
    border-bottom: none;
}
.cust-info-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: #f8fafc;
    color: #6366f1;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 0.88rem;
    border: 1px solid #f1f5f9;
}

/* Modern Tables */
.cust-table thead th {
    background: #f8fafc !important;
    color: #64748b !important;
    font-size: 0.74rem !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    border-bottom: 1px solid #e2e8f0 !important;
    padding: 10px 14px !important;
}
.cust-table tbody td {
    padding: 12px 14px !important;
    font-size: 0.85rem !important;
    color: #334155 !important;
    vertical-align: middle !important;
    border-bottom: 1px solid #f1f5f9 !important;
}

@media print {
    .no-print, .btn, .cust-segmented-tabs-wrapper {
        display: none !important;
    }
}
</style>

<div class="cust-detail-page mb-5">
    <!-- Top Action Bar -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <a href="customers.php" class="btn btn-sm btn-light border text-secondary fw-semibold px-3 py-1.5 mb-2 d-inline-flex align-items-center gap-2" style="border-radius: 10px; font-size: 0.82rem;">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Customers</span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <h4 class="fw-bold text-dark mb-0" style="font-size: 1.25rem;">Customer Profile</h4>
                <span class="badge rounded-pill fw-semibold px-2 py-1" style="background: #f1f5f9; color: #475569; font-size: 0.74rem; border: 1px solid #e2e8f0;">
                    <?php echo htmlspecialchars($customer['customer_code'] ?? 'CUST'); ?>
                </span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 no-print">
            <a href="orders.php?customer_id=<?php echo $customer['id']; ?>" class="btn text-white fw-semibold px-3 py-2 shadow-sm d-inline-flex align-items-center gap-2" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 12px; font-size: 0.84rem; border: none; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.28);">
                <i class="fas fa-plus"></i>
                <span>New Order</span>
            </a>
            <button type="button" class="btn btn-outline-secondary fw-medium px-3 py-2 d-inline-flex align-items-center gap-2" onclick="window.print()" style="border-radius: 12px; font-size: 0.84rem;">
                <i class="fas fa-print"></i>
                <span>Print</span>
            </button>
        </div>
    </div>

    <!-- Main Content Layout -->
    <div class="row g-3">
        <!-- Left: Customer Information Column -->
        <div class="col-lg-4">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="fw-bold text-dark" style="font-size: 0.92rem;">
                        <i class="fas fa-user-circle text-primary me-2"></i>Customer Information
                    </span>
                    <?php if (($customer['status'] ?? 'active') === 'active'): ?>
                        <span class="badge rounded-pill fw-semibold px-2 py-1" style="background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; font-size: 0.72rem;">Active</span>
                    <?php else: ?>
                        <span class="badge rounded-pill fw-semibold px-2 py-1" style="background: #f1f5f9; color: #64748b; font-size: 0.72rem;">Inactive</span>
                    <?php endif; ?>
                </div>
                <div class="card-body p-4">
                    <!-- Avatar & Name -->
                    <div class="text-center pb-3 mb-3 border-bottom">
                        <div class="mx-auto mb-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 76px; height: 76px; border-radius: 50%; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: #ffffff; font-size: 1.7rem; font-weight: 700; box-shadow: 0 8px 20px rgba(79, 70, 229, 0.22);">
                            <?php echo $initials; ?>
                        </div>
                        <h5 class="fw-bold text-dark mb-1" style="font-size: 1.05rem;">
                            <?php echo htmlspecialchars(trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''))); ?>
                        </h5>
                        <small class="text-muted fw-medium" style="font-size: 0.78rem;">
                            <i class="fas fa-id-badge text-muted me-1"></i><?php echo htmlspecialchars($customer['customer_code'] ?? '—'); ?>
                        </small>
                    </div>

                    <!-- Contact Details List -->
                    <div class="cust-info-list">
                        <?php if (!empty($customer['phone'])): ?>
                        <div class="cust-info-tile">
                            <div class="cust-info-icon"><i class="fas fa-phone"></i></div>
                            <div>
                                <small class="text-muted d-block" style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase;">Phone Number</small>
                                <a href="tel:<?php echo htmlspecialchars($customer['phone']); ?>" class="fw-semibold text-dark text-decoration-none" style="font-size: 0.86rem;">
                                    <?php echo htmlspecialchars($customer['phone']); ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($customer['email'])): ?>
                        <div class="cust-info-tile">
                            <div class="cust-info-icon"><i class="fas fa-envelope"></i></div>
                            <div>
                                <small class="text-muted d-block" style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase;">Email Address</small>
                                <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>" class="fw-semibold text-dark text-decoration-none text-break" style="font-size: 0.86rem;">
                                    <?php echo htmlspecialchars($customer['email']); ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($customer['address']) || !empty($customer['city'])): ?>
                        <div class="cust-info-tile">
                            <div class="cust-info-icon"><i class="fas fa-map-marker-alt"></i></div>
                            <div>
                                <small class="text-muted d-block" style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase;">Address</small>
                                <div class="fw-semibold text-dark" style="font-size: 0.85rem; line-height: 1.35;">
                                    <?php echo nl2br(htmlspecialchars($customer['address'] ?? '')); ?>
                                    <?php if (!empty($customer['city']) || !empty($customer['state'])): ?>
                                        <div class="text-muted small mt-0.5" style="font-size: 0.78rem;">
                                            <?php echo htmlspecialchars($customer['city'] ?? ''); ?><?php if (!empty($customer['city']) && !empty($customer['state'])): ?>, <?php endif; ?><?php echo htmlspecialchars($customer['state'] ?? ''); ?>
                                            <?php if (!empty($customer['postal_code'])): ?> - <?php echo htmlspecialchars($customer['postal_code']); ?><?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($customer['date_of_birth'])): ?>
                        <div class="cust-info-tile">
                            <div class="cust-info-icon"><i class="fas fa-birthday-cake"></i></div>
                            <div>
                                <small class="text-muted d-block" style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase;">Date of Birth</small>
                                <span class="fw-semibold text-dark" style="font-size: 0.86rem;">
                                    <?php echo format_date($customer['date_of_birth']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="cust-info-tile">
                            <div class="cust-info-icon"><i class="fas fa-calendar-alt"></i></div>
                            <div>
                                <small class="text-muted d-block" style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase;">Customer Since</small>
                                <span class="fw-semibold text-dark" style="font-size: 0.86rem;">
                                    <?php echo format_date($customer['created_at']); ?>
                                </span>
                            </div>
                        </div>

                        <?php if (!empty($customer['notes'])): ?>
                        <div class="cust-info-tile">
                            <div class="cust-info-icon"><i class="fas fa-sticky-note"></i></div>
                            <div>
                                <small class="text-muted d-block" style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase;">Notes</small>
                                <span class="text-secondary small d-block" style="font-size: 0.82rem;">
                                    <?php echo nl2br(htmlspecialchars($customer['notes'])); ?>
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Stats, Distribution & Tabbed Details Column -->
        <div class="col-lg-8">
            <!-- 1. Top 4 Stats Row -->
            <div class="row g-2 mb-3">
                <div class="col-6 col-md-3">
                    <div class="cust-stat-card">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <small class="text-muted fw-semibold text-uppercase" style="font-size: 0.7rem;">TOTAL ORDERS</small>
                            <div class="cust-stat-icon" style="background: #eff6ff; color: #3b82f6;">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                        </div>
                        <div class="fs-4 fw-bold text-dark"><?php echo $totalOrders; ?></div>
                        <small class="text-muted mt-1" style="font-size: 0.7rem;">All time orders</small>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="cust-stat-card">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <small class="text-muted fw-semibold text-uppercase" style="font-size: 0.7rem;">TOTAL SPENT</small>
                            <div class="cust-stat-icon" style="background: #f0fdf4; color: #10b981;">
                                <i class="fas fa-coins"></i>
                            </div>
                        </div>
                        <div class="fs-4 fw-bold text-dark"><?php echo format_currency($totalSpent); ?></div>
                        <small class="text-muted mt-1" style="font-size: 0.7rem;">Lifetime billing</small>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="cust-stat-card">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <small class="text-muted fw-semibold text-uppercase" style="font-size: 0.7rem;">TOTAL PAID</small>
                            <div class="cust-stat-icon" style="background: #faf5ff; color: #8b5cf6;">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                        <div class="fs-4 fw-bold text-dark"><?php echo format_currency($totalPaid); ?></div>
                        <small class="text-muted mt-1" style="font-size: 0.7rem;">Completed payments</small>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="cust-stat-card">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <small class="text-muted fw-semibold text-uppercase" style="font-size: 0.7rem;">PENDING</small>
                            <div class="cust-stat-icon" style="background: #fffbeb; color: #f59e0b;">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                        <div class="fs-4 fw-bold text-dark"><?php echo format_currency($totalPending); ?></div>
                        <small class="text-muted mt-1" style="font-size: 0.7rem;">Outstanding balance</small>
                    </div>
                </div>
            </div>

            <!-- 2. Order Status Distribution (Clean & Balanced, No giant empty box) -->
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="fw-bold text-dark" style="font-size: 0.9rem;">
                        <i class="fas fa-chart-pie text-primary me-2"></i>Order Status Distribution
                    </span>
                    <span class="badge rounded-pill fw-semibold px-2 py-1" style="background: #f1f5f9; color: #475569; font-size: 0.72rem;">
                        <?php echo $totalOrders; ?> <?php echo $totalOrders === 1 ? 'Order' : 'Orders'; ?>
                    </span>
                </div>
                <div class="card-body p-3">
                    <div class="row g-2 text-center">
                        <div class="col">
                            <div class="cust-status-chip" style="background: #fffbeb; border: 1px solid #fef3c7;">
                                <div class="fs-5 fw-bold" style="color: #b45309;"><?php echo $orderStatusCounts['pending']; ?></div>
                                <small class="fw-semibold d-block mt-0.5" style="font-size: 0.72rem; color: #92400e;">Pending</small>
                            </div>
                        </div>
                        <div class="col">
                            <div class="cust-status-chip" style="background: #f0f9ff; border: 1px solid #e0f2fe;">
                                <div class="fs-5 fw-bold" style="color: #0284c7;"><?php echo $orderStatusCounts['in_progress']; ?></div>
                                <small class="fw-semibold d-block mt-0.5" style="font-size: 0.72rem; color: #0369a1;">In Progress</small>
                            </div>
                        </div>
                        <div class="col">
                            <div class="cust-status-chip" style="background: #f0fdf4; border: 1px solid #dcfce7;">
                                <div class="fs-5 fw-bold" style="color: #15803d;"><?php echo $orderStatusCounts['completed']; ?></div>
                                <small class="fw-semibold d-block mt-0.5" style="font-size: 0.72rem; color: #166534;">Completed</small>
                            </div>
                        </div>
                        <div class="col">
                            <div class="cust-status-chip" style="background: #faf5ff; border: 1px solid #ede9fe;">
                                <div class="fs-5 fw-bold" style="color: #6d28d9;"><?php echo $orderStatusCounts['delivered']; ?></div>
                                <small class="fw-semibold d-block mt-0.5" style="font-size: 0.72rem; color: #5b21b6;">Delivered</small>
                            </div>
                        </div>
                        <div class="col">
                            <div class="cust-status-chip" style="background: #fef2f2; border: 1px solid #fee2e2;">
                                <div class="fs-5 fw-bold" style="color: #b91c1c;"><?php echo $orderStatusCounts['cancelled']; ?></div>
                                <small class="fw-semibold d-block mt-0.5" style="font-size: 0.72rem; color: #991b1b;">Cancelled</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Tabbed Sections (Orders, Invoices, Payments, Measurements) -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom p-3">
                    <ul class="nav nav-pills cust-segmented-tabs-wrapper" id="customerTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders" type="button" role="tab">
                                <i class="fas fa-shopping-bag"></i>
                                <span>Orders</span>
                                <span class="cust-tab-badge"><?php echo count($orders); ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="invoices-tab" data-bs-toggle="tab" data-bs-target="#invoices" type="button" role="tab">
                                <i class="fas fa-file-invoice"></i>
                                <span>Invoices</span>
                                <span class="cust-tab-badge"><?php echo count($invoices); ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab">
                                <i class="fas fa-money-bill-wave"></i>
                                <span>Payments</span>
                                <span class="cust-tab-badge"><?php echo count($customerPayments); ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="measurements-tab" data-bs-toggle="tab" data-bs-target="#measurements" type="button" role="tab">
                                <i class="fas fa-ruler"></i>
                                <span>Measurements</span>
                                <span class="cust-tab-badge"><?php echo count($measurements); ?></span>
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="card-body p-0">
                    <div class="tab-content" id="customerTabsContent">
                        <!-- TAB 1: ORDERS -->
                        <div class="tab-pane fade show active" id="orders" role="tabpanel">
                            <?php if (!empty($orders)): ?>
                                <div class="table-responsive">
                                    <table class="table cust-table table-hover mb-0 align-middle">
                                        <thead>
                                            <tr>
                                                <th class="ps-3">ORDER #</th>
                                                <th>DATE</th>
                                                <th>CLOTH TYPE</th>
                                                <th>DUE DATE</th>
                                                <th>AMOUNT</th>
                                                <th>STATUS</th>
                                                <th class="text-end pe-3">ACTIONS</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($orders as $order): 
                                                $due_date = strtotime($order['due_date']);
                                                $is_overdue = $due_date < time() && !in_array($order['status'], ['completed', 'delivered', 'cancelled']);
                                                $st = $order['status'] ?? 'pending';
                                                
                                                $statusBadges = [
                                                    'pending' => ['bg' => '#fffbeb', 'color' => '#b45309', 'border' => '#fef3c7'],
                                                    'in_progress' => ['bg' => '#f0f9ff', 'color' => '#0284c7', 'border' => '#e0f2fe'],
                                                    'completed' => ['bg' => '#f0fdf4', 'color' => '#15803d', 'border' => '#dcfce7'],
                                                    'delivered' => ['bg' => '#faf5ff', 'color' => '#6d28d9', 'border' => '#ede9fe'],
                                                    'cancelled' => ['bg' => '#fef2f2', 'color' => '#b91c1c', 'border' => '#fee2e2']
                                                ];
                                                $badgeStyle = $statusBadges[$st] ?? ['bg' => '#f1f5f9', 'color' => '#64748b', 'border' => '#e2e8f0'];
                                            ?>
                                            <tr>
                                                <td class="ps-3">
                                                    <span class="fw-bold text-dark font-monospace" style="font-size: 0.86rem;">
                                                        <?php echo htmlspecialchars($order['order_number']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="text-muted small"><?php echo format_date($order['order_date']); ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge rounded-pill fw-medium px-2 py-1" style="background: #f1f5f9; color: #475569; font-size: 0.74rem;">
                                                        <?php echo htmlspecialchars($order['cloth_type_name'] ?? 'Custom Tailoring'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="small <?php echo $is_overdue ? 'text-danger fw-bold' : 'text-muted'; ?>">
                                                        <?php echo format_date($order['due_date']); ?>
                                                        <?php if ($is_overdue): ?>
                                                            <i class="fas fa-exclamation-triangle ms-1 text-danger" title="Overdue"></i>
                                                        <?php endif; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="fw-bold text-dark"><?php echo format_currency($order['total_amount']); ?></span>
                                                    <?php if ((float)($order['balance_amount'] ?? 0) > 0): ?>
                                                        <small class="text-danger d-block" style="font-size: 0.7rem;">Bal: <?php echo format_currency($order['balance_amount']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge rounded-pill fw-semibold px-2 py-1" style="background: <?php echo $badgeStyle['bg']; ?>; color: <?php echo $badgeStyle['color']; ?>; border: 1px solid <?php echo $badgeStyle['border']; ?>; font-size: 0.72rem;">
                                                        <?php echo ucfirst(str_replace('_', ' ', $st)); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end pe-3">
                                                    <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-light border text-primary px-2.5 py-1" style="border-radius: 8px;" title="View Order">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5 px-3">
                                    <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; border-radius: 50%; background: #f8fafc; color: #94a3b8; border: 1px dashed #cbd5e1;">
                                        <i class="fas fa-shopping-bag fa-lg"></i>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1">No orders found</h6>
                                    <p class="text-muted small mb-3">This customer hasn't placed any orders yet.</p>
                                    <a href="orders.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-primary fw-semibold px-3 py-1.5" style="border-radius: 10px;">
                                        <i class="fas fa-plus me-1"></i>Create First Order
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- TAB 2: INVOICES -->
                        <div class="tab-pane fade" id="invoices" role="tabpanel">
                            <?php if (!empty($invoices)): ?>
                                <div class="table-responsive">
                                    <table class="table cust-table table-hover mb-0 align-middle">
                                        <thead>
                                            <tr>
                                                <th class="ps-3">INVOICE #</th>
                                                <th>ORDER #</th>
                                                <th>DATE</th>
                                                <th>TOTAL</th>
                                                <th>PAID</th>
                                                <th>BALANCE</th>
                                                <th>STATUS</th>
                                                <th class="text-end pe-3">ACTIONS</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($invoices as $invoice): 
                                                $ps = $invoice['payment_status'] ?? 'due';
                                                $invBadges = [
                                                    'paid' => ['bg' => '#f0fdf4', 'color' => '#15803d', 'border' => '#dcfce7'],
                                                    'partial' => ['bg' => '#fffbeb', 'color' => '#b45309', 'border' => '#fef3c7'],
                                                    'due' => ['bg' => '#fef2f2', 'color' => '#b91c1c', 'border' => '#fee2e2']
                                                ];
                                                $invStyle = $invBadges[$ps] ?? ['bg' => '#f1f5f9', 'color' => '#64748b', 'border' => '#e2e8f0'];
                                            ?>
                                            <tr>
                                                <td class="ps-3">
                                                    <span class="fw-bold text-dark font-monospace" style="font-size: 0.86rem;">
                                                        <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                                                    </span>
                                                </td>
                                                <td><span class="text-muted small"><?php echo htmlspecialchars($invoice['order_number'] ?? '—'); ?></span></td>
                                                <td><span class="text-muted small"><?php echo format_date($invoice['invoice_date']); ?></span></td>
                                                <td><strong><?php echo format_currency($invoice['total_amount']); ?></strong></td>
                                                <td class="text-success fw-semibold"><?php echo format_currency($invoice['paid_amount']); ?></td>
                                                <td class="text-danger fw-semibold"><?php echo format_currency($invoice['balance_amount']); ?></td>
                                                <td>
                                                    <span class="badge rounded-pill fw-semibold px-2 py-1" style="background: <?php echo $invStyle['bg']; ?>; color: <?php echo $invStyle['color']; ?>; border: 1px solid <?php echo $invStyle['border']; ?>; font-size: 0.72rem;">
                                                        <?php echo ucfirst($ps); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end pe-3">
                                                    <a href="invoice-details.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-light border text-primary px-2.5 py-1" style="border-radius: 8px;" title="View Invoice">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5 px-3">
                                    <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; border-radius: 50%; background: #f8fafc; color: #94a3b8; border: 1px dashed #cbd5e1;">
                                        <i class="fas fa-file-invoice fa-lg"></i>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1">No invoices found</h6>
                                    <p class="text-muted small mb-0">No invoices have been generated for this customer.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- TAB 3: PAYMENTS -->
                        <div class="tab-pane fade" id="payments" role="tabpanel">
                            <?php if (!empty($customerPayments)): ?>
                                <div class="table-responsive">
                                    <table class="table cust-table table-hover mb-0 align-middle">
                                        <thead>
                                            <tr>
                                                <th class="ps-3">DATE</th>
                                                <th>AMOUNT</th>
                                                <th>PAYMENT METHOD</th>
                                                <th>REFERENCE</th>
                                                <th>NOTES</th>
                                                <th class="text-end pe-3">ACTIONS</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($customerPayments as $payment): ?>
                                            <tr>
                                                <td class="ps-3"><span class="text-muted small"><?php echo format_date($payment['payment_date']); ?></span></td>
                                                <td><strong class="text-success"><?php echo format_currency($payment['amount']); ?></strong></td>
                                                <td>
                                                    <span class="badge rounded-pill fw-medium px-2 py-1" style="background: #f0f9ff; color: #0284c7; border: 1px solid #e0f2fe; font-size: 0.72rem;">
                                                        <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                                    </span>
                                                </td>
                                                <td><span class="text-muted small"><?php echo htmlspecialchars($payment['reference_number'] ?? '—'); ?></span></td>
                                                <td><span class="text-muted small"><?php echo htmlspecialchars($payment['notes'] ?? '—'); ?></span></td>
                                                <td class="text-end pe-3">
                                                    <?php if (!empty($payment['invoice_id'])): ?>
                                                    <a href="invoice-details.php?id=<?php echo $payment['invoice_id']; ?>" class="btn btn-sm btn-light border text-primary px-2.5 py-1" style="border-radius: 8px;" title="View Invoice">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr style="background: #f8fafc;">
                                                <td class="ps-3 fw-bold text-dark">Total Paid</td>
                                                <td colspan="5"><strong class="text-success fs-6"><?php echo format_currency($totalPaid); ?></strong></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5 px-3">
                                    <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; border-radius: 50%; background: #f8fafc; color: #94a3b8; border: 1px dashed #cbd5e1;">
                                        <i class="fas fa-money-bill-wave fa-lg"></i>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1">No payments yet</h6>
                                    <p class="text-muted small mb-0">No payment records found for this customer.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- TAB 4: MEASUREMENTS -->
                        <div class="tab-pane fade" id="measurements" role="tabpanel">
                            <?php if (!empty($measurements)): ?>
                                <div class="p-3">
                                    <div class="row g-3">
                                        <?php foreach ($measurements as $measurement): ?>
                                        <div class="col-md-6">
                                            <div class="card h-100 shadow-sm border" style="border-radius: 14px; overflow: hidden; border-color: #f1f5f9 !important;">
                                                <div class="card-header bg-white d-flex align-items-center justify-content-between p-3 border-bottom">
                                                    <div>
                                                        <h6 class="fw-bold text-dark mb-0" style="font-size: 0.9rem;">
                                                            <i class="fas fa-ruler-combined text-primary me-2"></i>
                                                            <?php echo htmlspecialchars($measurement['cloth_type_name'] ?? 'Measurement'); ?>
                                                        </h6>
                                                        <small class="text-muted" style="font-size: 0.72rem;">
                                                            Recorded on <?php echo format_date($measurement['created_at']); ?>
                                                        </small>
                                                    </div>
                                                    <span class="badge rounded-pill fw-semibold px-2 py-1" style="background: #ede9fe; color: #6366f1; font-size: 0.72rem;">
                                                        #<?php echo $measurement['id']; ?>
                                                    </span>
                                                </div>
                                                <div class="card-body p-3">
                                                    <?php
                                                    $measurementData = json_decode($measurement['measurement_data'], true);
                                                    if ($measurementData && is_array($measurementData)):
                                                    ?>
                                                        <div class="row g-2">
                                                            <?php foreach ($measurementData as $key => $value): ?>
                                                                <?php if (!empty($value)): ?>
                                                                <div class="col-6">
                                                                    <div class="p-2 rounded-3" style="background: #f8fafc; border: 1px solid #f1f5f9;">
                                                                        <small class="text-muted d-block text-truncate" style="font-size: 0.68rem; font-weight: 600; text-transform: uppercase;">
                                                                            <?php echo ucfirst(str_replace('_', ' ', $key)); ?>
                                                                        </small>
                                                                        <strong class="text-dark" style="font-size: 0.88rem;">
                                                                            <?php echo htmlspecialchars($value); ?>
                                                                        </strong>
                                                                    </div>
                                                                </div>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <p class="text-muted small mb-0">No measurement parameters recorded.</p>
                                                    <?php endif; ?>

                                                    <?php if (!empty($measurement['notes'])): ?>
                                                        <div class="mt-3 p-2 rounded-3 alert alert-light border mb-0" style="font-size: 0.78rem;">
                                                            <strong class="text-dark d-block mb-1">Notes:</strong>
                                                            <span class="text-secondary"><?php echo nl2br(htmlspecialchars($measurement['notes'])); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="card-footer bg-white border-top p-2.5 d-flex justify-content-end">
                                                    <a href="measurements.php?id=<?php echo $measurement['id']; ?>" class="btn btn-sm btn-outline-primary fw-medium px-3 py-1" style="border-radius: 8px; font-size: 0.78rem;">
                                                        <i class="fas fa-eye me-1"></i>View Details
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5 px-3">
                                    <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; border-radius: 50%; background: #f8fafc; color: #94a3b8; border: 1px dashed #cbd5e1;">
                                        <i class="fas fa-ruler fa-lg"></i>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1">No measurements yet</h6>
                                    <p class="text-muted small mb-3">No measurements have been recorded for this customer.</p>
                                    <a href="measurements.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-primary fw-semibold px-3 py-1.5" style="border-radius: 10px;">
                                        <i class="fas fa-plus me-1"></i>Add Measurement
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
