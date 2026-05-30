<!-- Favicon - Primary ICO format for Google Search -->
<link rel="icon" type="image/x-icon" href="../favicon.ico" sizes="16x16 32x32 48x48">
<!-- Favicon - PNG fallback -->
<link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon(2).png">
<link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon(2).png">
<!-- Apple Touch Icon -->
<link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon(2).png">

<?php
/**
 * Dashboard Page
 * Tailoring Management System
 */

// Set page title before including header
$page_title = 'Dashboard'; // Will be translated in header
require_once 'includes/header.php';

// Include models
require_once '../models/Customer.php';
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

// Check if any orders exist for chart
$totalOrderCount = ($orderStats['pending'] ?? 0) + ($orderStats['in_progress'] ?? 0) + ($orderStats['completed'] ?? 0) + ($orderStats['delivered'] ?? 0) + ($orderStats['cancelled'] ?? 0);

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
    
    <!-- Quick Actions -->
    <div class="col-xl-4 col-lg-5 mb-4">
        <div class="card h-100">
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
                    <a href="measurements.php?action=create" class="btn btn-outline-primary">
                        <i class="fas fa-ruler-combined me-2"></i>
                        Add Measurement
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
                                            <strong><?php echo htmlspecialchars($order['customer_name'] ?? (($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''))); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($order['customer_code'] ?? ''); ?></small>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($order['cloth_type_name'] ?? ''); ?></td>
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
                    <div class="no-orders-empty">
                        <div class="no-orders-gradient-top"></div>
                        <div class="no-orders-body">
                            <div class="no-orders-scissors-wrap">
                                <span class="scissors-icon">&#9988;</span>
                                <span class="thread-line"></span>
                            </div>
                            <div class="no-orders-texts">
                                <span class="no-orders-tag">Nothing here yet</span>
                                <h5 class="no-orders-heading">No Recent Orders</h5>
                                <p class="no-orders-desc">Your workspace is ready — start stitching orders for your customers!</p>
                            </div>
                            <a href="orders.php?action=create" class="no-orders-cta">
                                <i class="fas fa-plus"></i> Create First Order
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions & Alerts -->
    <div class="col-xl-4 col-lg-5 mb-4">
        <!-- Order Status Breakdown -->
        <?php
            $statusItems = [
                ['label'=>'Pending',     'key'=>'pending',     'color'=>'#f59e0b', 'count'=>(int)($orderStats['pending'] ?? 0)],
                ['label'=>'In Progress', 'key'=>'in_progress', 'color'=>'#06b6d4', 'count'=>(int)($orderStats['in_progress'] ?? 0)],
                ['label'=>'Completed',   'key'=>'completed',   'color'=>'#10b981', 'count'=>(int)($orderStats['completed'] ?? 0)],
                ['label'=>'Delivered',   'key'=>'delivered',   'color'=>'#6366f1', 'count'=>(int)($orderStats['delivered'] ?? 0)],
                ['label'=>'Cancelled',   'key'=>'cancelled',   'color'=>'#ef4444', 'count'=>(int)($orderStats['cancelled'] ?? 0)],
            ];
        ?>
        <div class="card mb-4 order-status-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-layer-group me-2"></i>
                    Order Status
                </h5>
                <span class="osc-total-badge"><?php echo $totalOrderCount; ?> Total</span>
            </div>
            <div class="card-body osc-body">
                <?php foreach ($statusItems as $item): ?>
                <?php $pct = $totalOrderCount > 0 ? round(($item['count'] / $totalOrderCount) * 100) : 0; ?>
                <div class="osc-row">
                    <div class="osc-label-wrap">
                        <span class="osc-dot" style="background:<?php echo $item['color']; ?>"></span>
                        <span class="osc-label"><?php echo $item['label']; ?></span>
                    </div>
                    <div class="osc-bar-track">
                        <div class="osc-bar-fill"
                             style="background:<?php echo $item['color']; ?>;"
                             data-width="<?php echo $pct; ?>"></div>
                    </div>
                    <div class="osc-meta">
                        <span class="osc-count"><?php echo $item['count']; ?></span>
                        <span class="osc-pct"><?php echo $pct; ?>%</span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if ($totalOrderCount === 0): ?>
                <div class="osc-empty-note">
                    <a href="orders.php?action=create" class="sbe-cta">
                        <i class="fas fa-plus-circle me-1"></i> Add your first order
                    </a>
                </div>
                <?php endif; ?>
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

// Animate Order Status bars on load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.osc-bar-fill').forEach(function(bar) {
        var target = bar.getAttribute('data-width');
        setTimeout(function() {
            bar.style.width = target + '%';
        }, 200);
    });
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

    /* ════════════════════════════════════════
       RECENT ORDERS — Empty State
    ════════════════════════════════════════ */
    .no-orders-empty {
        border-radius: 0 0 12px 12px;
        overflow: hidden;
        background: #fff;
    }
    .no-orders-gradient-top {
        height: 6px;
        background: linear-gradient(90deg, #667eea 0%, #a78bfa 40%, #f472b6 70%, #fb923c 100%);
        border-radius: 0;
    }
    .no-orders-body {
        padding: 2rem 1.5rem 1.8rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1rem;
    }
    .no-orders-scissors-wrap {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .scissors-icon {
        font-size: 2.4rem;
        filter: drop-shadow(0 2px 8px rgba(102,126,234,0.18));
        animation: scissors-bounce 2.5s ease-in-out infinite;
    }
    .thread-line {
        display: inline-block;
        width: 60px;
        height: 2px;
        background: repeating-linear-gradient(90deg, #a78bfa 0px, #a78bfa 6px, transparent 6px, transparent 12px);
        border-radius: 2px;
        opacity: 0.5;
    }
    @keyframes scissors-bounce {
        0%,100% { transform: translateY(0) rotate(-5deg); }
        50%      { transform: translateY(-6px) rotate(5deg); }
    }
    .no-orders-texts { text-align: center; }
    .no-orders-tag {
        display: inline-block;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #a78bfa;
        background: rgba(167,139,250,0.10);
        padding: 2px 10px;
        border-radius: 20px;
        margin-bottom: 6px;
    }
    .no-orders-heading {
        font-size: 1.05rem;
        font-weight: 700;
        color: #1e1b4b;
        margin-bottom: 0.3rem;
    }
    .no-orders-desc {
        font-size: 0.80rem;
        color: #94a3b8;
        margin-bottom: 0;
        line-height: 1.6;
    }
    .no-orders-cta {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 22px;
        border-radius: 30px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: #fff;
        font-size: 0.82rem;
        font-weight: 600;
        text-decoration: none;
        box-shadow: 0 4px 15px rgba(102,126,234,0.35);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .no-orders-cta:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102,126,234,0.45);
        color: #fff;
    }
    .order-status-card .osc-total-badge {
        font-size: 0.80rem;
        font-weight: 700;
        background: linear-gradient(135deg, rgba(102,126,234,0.12), rgba(118,75,162,0.12));
        color: #ffffff;
        padding: 3px 12px;
        border-radius: 20px;
        letter-spacing: 0.03em;
    }
    .osc-body {
        padding: 0.9rem 1.1rem 0.8rem;
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
    }
    .osc-row {
        display: flex;
        align-items: center;
        gap: 9px;
    }
    .osc-label-wrap {
        display: flex;
        align-items: center;
        gap: 6px;
        min-width: 90px;
    }
    .osc-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .osc-label {
        font-size: 0.88rem;
        color: #475569;
        font-weight: 500;
        white-space: nowrap;
    }
    .osc-bar-track {
        flex: 1;
        height: 8px;
        background: #f1f5f9;
        border-radius: 20px;
        overflow: hidden;
    }
    .osc-bar-fill {
        height: 100%;
        width: 0%;
        border-radius: 20px;
        transition: width 0.7s cubic-bezier(0.4, 0, 0.2, 1);
        opacity: 0.85;
    }
    .osc-meta {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        min-width: 36px;
    }
    .osc-count {
        font-size: 0.92rem;
        font-weight: 700;
        color: #1e293b;
        line-height: 1.1;
    }
    .osc-pct {
        font-size: 0.75rem;
        color: #94a3b8;
        line-height: 1.1;
    }
    .osc-empty-note {
        margin-top: 0.5rem;
        padding-top: 0.8rem;
        border-top: 1px dashed #e2e8f0;
        text-align: center;
    }
    .sbe-cta {
        font-size: 0.88rem;
        font-weight: 600;
        color: #667eea;
        text-decoration: none;
        transition: color 0.2s;
    }
    .sbe-cta:hover { color: #764ba2; }

    /* legacy cleanup */
    .sbe-bar-wrap {
        flex: 1;
        height: 7px;
        background: #f1f5f9;
        border-radius: 20px;
        overflow: hidden;
    }
    .sbe-bar {
        height: 100%;
        border-radius: 20px;
        min-width: 0;
        transition: width 0.6s cubic-bezier(0.4,0,0.2,1);
        opacity: 0.35;
    }
    .sbe-count {
        font-size: 0.75rem;
        font-weight: 700;
        color: #94a3b8;
        min-width: 18px;
        text-align: right;
    }
    .sbe-footer {
        margin-top: 1.1rem;
        padding-top: 0.9rem;
        border-top: 1px solid #f1f5f9;
        text-align: center;
    }
    .sbe-cta {
        font-size: 0.78rem;
        font-weight: 600;
        color: #667eea;
        text-decoration: none;
        transition: color 0.2s;
    }
    .sbe-cta:hover { color: #764ba2; }
</style>