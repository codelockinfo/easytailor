<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="../favicon(2).png">

<?php
/**
 * Dashboard Page
 * Tailoring Management System
 */

// Set page title before including header
$page_title = 'Dashboard'; // Will be translated in header
require_once 'includes/header.php';

// Include models
require_once 'models/Customer.php';
require_once 'models/Order.php';
require_once 'models/Invoice.php';
require_once 'models/User.php';

// Initialize models
$customerModel = new Customer();
$orderModel = new Order();
$invoiceModel = new Invoice();
$userModel = new User();

// Get statistics
$customerStats = $customerModel->getCustomerStats();
$orderStats = $orderModel->getOrderStats();
$invoiceStats = $invoiceModel->getInvoiceStats();
$userStats = $userModel->getUserStats();

// Get recent orders
$recentOrders = $orderModel->getOrdersWithDetails([], 5);

// Get overdue orders
$overdueOrders = $orderModel->getOverdueOrders();

// Get orders due today
$ordersDueToday = $orderModel->getOrdersDueToday();

// Get monthly revenue for chart
$monthlyRevenue = [];
for ($i = 11; $i >= 0; $i--) {
    $date = date('Y-m', strtotime("-$i months"));
    $year = date('Y', strtotime($date));
    $month = date('m', strtotime($date));
    $revenue = $orderModel->getMonthlyRevenue($year, $month);
    $monthlyRevenue[] = [
        'month' => date('M Y', strtotime($date)),
        'revenue' => $revenue
    ];
}
?>


<!-- Statistics Cards -->
<div class="row">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($customerStats['total']); ?></div>
                    <div class="stat-label">Total Customers</div>
                    <small class="opacity-75">+<?php echo $customerStats['this_month']; ?> this month</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($orderStats['total']); ?></div>
                    <div class="stat-label">Total Orders</div>
                    <small class="opacity-75">+<?php echo $orderStats['this_month']; ?> this month</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo format_currency($invoiceStats['due_amount']); ?></div>
                    <div class="stat-label">Pending Revenue</div>
                    <small class="opacity-75"><?php echo $invoiceStats['partial'] + $invoiceStats['due']; ?> invoices</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card" style="background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo count($overdueOrders); ?></div>
                    <div class="stat-label">Overdue Orders</div>
                    <small class="opacity-75">Need attention</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Revenue Chart -->
    <div class="col-xl-8 col-lg-7 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Monthly Revenue
                </h5>
            </div>
            <div class="card-body">
                <canvas id="revenueChart" height="100"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Order Status Chart -->
    <div class="col-xl-4 col-lg-5 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Order Status
                </h5>
            </div>
            <div class="card-body">
                <canvas id="orderStatusChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Orders -->
    <div class="col-xl-8 col-lg-7 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-clock me-2"></i>
                    Recent Orders
                </h5>
                <a href="orders.php" class="btn btn-sm btn-light">View All</a>
            </div>
            <div class="card-body">
                <?php if (!empty($recentOrders)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Cloth Type</th>
                                    <th>Status</th>
                                    <th>Amount</th>
                                    <th>Due Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td>
                                        <a href="orders.php?view=<?php echo $order['id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($order['order_number']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($order['customer_code']); ?></small>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($order['cloth_type_name']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo match($order['status']) {
                                                'pending' => 'warning',
                                                'in_progress' => 'info',
                                                'completed' => 'success',
                                                'delivered' => 'primary',
                                                'cancelled' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo format_currency($order['total_amount']); ?></td>
                                    <td>
                                        <span class="<?php echo $order['due_date'] < date('Y-m-d') ? 'text-danger' : ''; ?>">
                                            <?php echo format_date($order['due_date']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No orders found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions & Alerts -->
    <div class="col-xl-4 col-lg-5 mb-4">
        <!-- Quick Actions -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Quick Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="orders.php?action=create" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>
                        New Order
                    </a>
                    <a href="customers.php?action=create" class="btn btn-outline-primary">
                        <i class="fas fa-user-plus me-2"></i>
                        Add Customer
                    </a>
                    <a href="invoices.php?action=create" class="btn btn-outline-primary">
                        <i class="fas fa-file-invoice me-2"></i>
                        Create Invoice
                    </a>
                    <a href="expenses.php?action=create" class="btn btn-outline-primary">
                        <i class="fas fa-receipt me-2"></i>
                        Record Expense
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Alerts -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-bell me-2"></i>
                    Alerts & Reminders
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($overdueOrders)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong><?php echo count($overdueOrders); ?> overdue orders</strong>
                        <br>
                        <small>Orders past their due date</small>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($ordersDueToday)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-clock me-2"></i>
                        <strong><?php echo count($ordersDueToday); ?> orders due today</strong>
                        <br>
                        <small>Orders scheduled for delivery</small>
                    </div>
                <?php endif; ?>
                
                <?php if ($invoiceStats['due'] > 0): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-file-invoice me-2"></i>
                        <strong><?php echo $invoiceStats['due']; ?> unpaid invoices</strong>
                        <br>
                        <small>Total: <?php echo format_currency($invoiceStats['due_amount']); ?></small>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($overdueOrders) && empty($ordersDueToday) && $invoiceStats['due'] == 0): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <p class="text-muted mb-0">All caught up! No urgent alerts.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Revenue Chart
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
const revenueChart = new Chart(revenueCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($monthlyRevenue, 'month')); ?>,
        datasets: [{
            label: 'Revenue',
            data: <?php echo json_encode(array_column($monthlyRevenue, 'revenue')); ?>,
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#667eea',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 5
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₹' + value.toLocaleString();
                    }
                }
            }
        },
        elements: {
            point: {
                hoverRadius: 8
            }
        }
    }
});

// Order Status Chart
const orderStatusCtx = document.getElementById('orderStatusChart').getContext('2d');
const orderStatusChart = new Chart(orderStatusCtx, {
    type: 'doughnut',
    data: {
        labels: ['Pending', 'In Progress', 'Completed', 'Delivered', 'Cancelled'],
        datasets: [{
            data: [
                <?php echo $orderStats['pending']; ?>,
                <?php echo $orderStats['in_progress']; ?>,
                <?php echo $orderStats['completed']; ?>,
                <?php echo $orderStats['delivered']; ?>,
                <?php echo $orderStats['cancelled']; ?>
            ],
            backgroundColor: [
                '#ffc107',
                '#17a2b8',
                '#28a745',
                '#007bff',
                '#dc3545'
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true
                }
            }
        }
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>

<style>
    .btn-outline-primary:hover {
        color: white;
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%) !important;
        border: none !important;
    }
    .btn-outline-primary {
        color: #667eea;
        border: 1px solid #667eea !important;
    }
</style>