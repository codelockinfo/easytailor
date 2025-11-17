<?php
/**
 * Reports Page
 * Tailoring Management System
 */

$page_title = 'Reports & Analytics';
require_once 'includes/header.php';

require_once 'models/Order.php';
require_once 'models/Invoice.php';
require_once 'models/Expense.php';
require_once 'models/Customer.php';

$orderModel = new Order();
$invoiceModel = new Invoice();
$expenseModel = new Expense();
$customerModel = new Customer();

// Get date range
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Get statistics
$orderStats = $orderModel->getOrderStats();
$invoiceStats = $invoiceModel->getInvoiceStats();
$expenseStats = $expenseModel->getExpenseStats();
$customerStats = $customerModel->getCustomerStats();

// Get monthly data for charts
$monthlyRevenue = [];
$monthlyExpenses = [];
for ($i = 11; $i >= 0; $i--) {
    $date = date('Y-m', strtotime("-$i months"));
    $year = date('Y', strtotime($date));
    $month = date('m', strtotime($date));
    
    $revenue = $orderModel->getMonthlyRevenue($year, $month);
    $expense = $expenseModel->getMonthlyExpenseStats($year, $month);
    
    $monthlyRevenue[] = [
        'month' => date('M Y', strtotime($date)),
        'revenue' => $revenue
    ];
    
    $monthlyExpenses[] = [
        'month' => date('M Y', strtotime($date)),
        'expenses' => $expense['total_amount'] ?? 0
    ];
}
?>


<!-- Date Range Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form id="filterForm" class="row g-3">
            <div class="col-md-3">
                <label for="date_from" class="form-label">From Date</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
            </div>
            <div class="col-md-3">
                <label for="date_to" class="form-label">To Date</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2" id="filterBtn">
                    <i class="fas fa-filter me-1"></i>Filter
                </button>
                <button type="button" class="btn btn-outline-secondary" id="resetBtn">
                    <i class="fas fa-times me-1"></i>Reset
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Loading Indicator -->
<div id="loadingIndicator" class="text-center py-4" style="display: none;">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <p class="mt-2 text-muted">Loading reports data...</p>
</div>

<!-- Key Metrics -->
<div id="keyMetrics" class="row mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number" id="totalCustomers"><?php echo number_format($customerStats['total']); ?></div>
                    <div class="stat-label">Total Customers</div>
                    <small class="opacity-75" id="customersThisMonth">+<?php echo $customerStats['this_month']; ?> this month</small>
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
                    <div class="stat-number" id="totalRevenue"><?php echo format_currency($invoiceStats['total_amount']); ?></div>
                    <div class="stat-label">Total Revenue</div>
                    <small class="opacity-75" id="paidInvoices"><?php echo $invoiceStats['paid']; ?> paid invoices</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number" id="totalExpenses"><?php echo format_currency($expenseStats['total_amount']); ?></div>
                    <div class="stat-label">Total Expenses</div>
                    <small class="opacity-75" id="expenseTransactions"><?php echo $expenseStats['total']; ?> transactions</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-receipt"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number" id="netProfit"><?php echo format_currency($invoiceStats['total_amount'] - $expenseStats['total_amount']); ?></div>
                    <div class="stat-label">Net Profit</div>
                    <small class="opacity-75">Revenue - Expenses</small>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts -->
<div class="row mb-4">
    <div class="col-xl-8 col-lg-7 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Revenue vs Expenses (12 Months)
                </h5>
            </div>
            <div class="card-body">
                <canvas id="revenueExpenseChart" height="100"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-xl-4 col-lg-5 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Order Status Distribution
                </h5>
            </div>
            <div class="card-body">
                <canvas id="orderStatusChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Reports -->
<div class="row">
    <div class="col-xl-8 col-lg-7 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Expense Categories
                </h5>
            </div>
            <div class="card-body">
                <canvas id="expenseCategoryChart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-xl-4 col-lg-5 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-pie me-2"></i>
                    Payment Methods
                </h5>
            </div>  
            <div class="card-body">
                <canvas id="paymentMethodChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Summary Tables -->
<div class="row">
    <div class="col-xl-6 col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list me-2"></i>
                    Recent Orders
                </h5>
            </div>
            <div class="card-body" id="recentOrdersTable">
                <?php 
                $recentOrders = $orderModel->getOrdersWithDetails([], 5);
                if (!empty($recentOrders)): 
                ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                    <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                    <td><?php echo format_currency($order['total_amount']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo match($order['status']) {
                                                'pending' => 'warning',
                                                'in_progress' => 'info',
                                                'completed' => 'success',
                                                'delivered' => 'primary',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">No recent orders</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-xl-6 col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list me-2"></i>
                    Top Expense Categories
                </h5>
            </div>
            <div class="card-body" id="expenseCategoriesTable">
                <?php if (!empty($expenseStats['by_category'])): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Count</th>
                                    <th>Total Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($expenseStats['by_category'], 0, 5) as $category): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['category']); ?></td>
                                    <td><?php echo $category['count']; ?></td>
                                    <td><?php echo format_currency($category['total']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center">No expense data</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Export Options -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-download me-2"></i>
            Export Reports
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3 mb-2">
                <button class="btn btn-outline-primary w-100" onclick="exportReport('orders')">
                    <i class="fas fa-clipboard-list me-2"></i>Export Orders
                </button>
            </div>
            <div class="col-md-3 mb-2">
                <button class="btn btn-outline-success w-100" onclick="exportReport('invoices')">
                    <i class="fas fa-file-invoice me-2"></i>Export Invoices
                </button>
            </div>
            <div class="col-md-3 mb-2">
                <button class="btn btn-outline-danger w-100" onclick="exportReport('expenses')">
                    <i class="fas fa-receipt me-2"></i>Export Expenses
                </button>
            </div>
            <div class="col-md-3 mb-2">
                <button class="btn btn-outline-info w-100" onclick="exportReport('customers')">
                    <i class="fas fa-users me-2"></i>Export Customers
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Global chart variables
let revenueExpenseChart, orderStatusChart, expenseCategoryChart, paymentMethodChart;

// Initialize charts with initial data
function initializeCharts() {
    // Revenue vs Expenses Chart
    const revenueExpenseCtx = document.getElementById('revenueExpenseChart').getContext('2d');
    revenueExpenseChart = new Chart(revenueExpenseCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($monthlyRevenue, 'month')); ?>,
            datasets: [{
                label: 'Revenue',
                data: <?php echo json_encode(array_column($monthlyRevenue, 'revenue')); ?>,
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                borderWidth: 3,
                fill: false,
                tension: 0.4
            }, {
                label: 'Expenses',
                data: <?php echo json_encode(array_column($monthlyExpenses, 'expenses')); ?>,
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                borderWidth: 3,
                fill: false,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
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
            }
        }
    });

    // Order Status Chart
    const orderStatusCtx = document.getElementById('orderStatusChart').getContext('2d');
    orderStatusChart = new Chart(orderStatusCtx, {
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
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Expense Category Chart
    const expenseCategoryCtx = document.getElementById('expenseCategoryChart').getContext('2d');
    expenseCategoryChart = new Chart(expenseCategoryCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($expenseStats['by_category'], 'category')); ?>,
            datasets: [{
                label: 'Amount',
                data: <?php echo json_encode(array_column($expenseStats['by_category'], 'total')); ?>,
                backgroundColor: '#dc3545',
                borderColor: '#dc3545',
                borderWidth: 1
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
            }
        }
    });

    // Payment Method Chart
    const paymentMethodCtx = document.getElementById('paymentMethodChart').getContext('2d');
    paymentMethodChart = new Chart(paymentMethodCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode(array_column($expenseStats['by_payment_method'], 'payment_method')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($expenseStats['by_payment_method'], 'total')); ?>,
                backgroundColor: [
                    '#28a745',
                    '#007bff',
                    '#17a2b8',
                    '#ffc107'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// Update charts with new data
function updateCharts(data) {
    // Update Revenue vs Expenses Chart
    revenueExpenseChart.data.labels = data.charts.revenue_expense.labels;
    revenueExpenseChart.data.datasets[0].data = data.charts.revenue_expense.revenue_data;
    revenueExpenseChart.data.datasets[1].data = data.charts.revenue_expense.expense_data;
    revenueExpenseChart.update();

    // Update Order Status Chart
    orderStatusChart.data.datasets[0].data = [
        data.charts.order_status.pending,
        data.charts.order_status.in_progress,
        data.charts.order_status.completed,
        data.charts.order_status.delivered,
        data.charts.order_status.cancelled
    ];
    orderStatusChart.update();

    // Update Expense Category Chart
    expenseCategoryChart.data.labels = data.charts.expense_categories.labels;
    expenseCategoryChart.data.datasets[0].data = data.charts.expense_categories.data;
    expenseCategoryChart.update();

    // Update Payment Method Chart
    paymentMethodChart.data.labels = data.charts.payment_methods.labels;
    paymentMethodChart.data.datasets[0].data = data.charts.payment_methods.data;
    paymentMethodChart.update();
}

// Update key metrics
function updateKeyMetrics(data) {
    document.getElementById('totalCustomers').textContent = data.stats.customers.total;
    document.getElementById('customersThisMonth').textContent = '+' + data.stats.customers.this_month + ' this month';
    document.getElementById('totalRevenue').textContent = data.stats.revenue.total_amount;
    document.getElementById('paidInvoices').textContent = data.stats.revenue.paid + ' paid invoices';
    document.getElementById('totalExpenses').textContent = data.stats.expenses.total_amount;
    document.getElementById('expenseTransactions').textContent = data.stats.expenses.total + ' transactions';
    document.getElementById('netProfit').textContent = data.stats.profit.net_profit;
}

// Update summary tables
function updateSummaryTables(data) {
    // Update Recent Orders Table
    const recentOrdersTable = document.getElementById('recentOrdersTable');
    if (data.tables.recent_orders.length > 0) {
        let tableHTML = `
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        data.tables.recent_orders.forEach(order => {
            tableHTML += `
                <tr>
                    <td>${order.order_number}</td>
                    <td>${order.customer_name}</td>
                    <td>${order.total_amount}</td>
                    <td>
                        <span class="badge bg-${order.status_badge}">${order.status}</span>
                    </td>
                </tr>
            `;
        });
        
        tableHTML += `
                    </tbody>
                </table>
            </div>
        `;
        recentOrdersTable.innerHTML = tableHTML;
    } else {
        recentOrdersTable.innerHTML = '<p class="text-muted text-center">No recent orders</p>';
    }

    // Update Expense Categories Table
    const expenseCategoriesTable = document.getElementById('expenseCategoriesTable');
    if (data.tables.expense_categories.length > 0) {
        let tableHTML = `
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Count</th>
                            <th>Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        data.tables.expense_categories.forEach(category => {
            tableHTML += `
                <tr>
                    <td>${category.category}</td>
                    <td>${category.count}</td>
                    <td>${category.total}</td>
                </tr>
            `;
        });
        
        tableHTML += `
                    </tbody>
                </table>
            </div>
        `;
        expenseCategoriesTable.innerHTML = tableHTML;
    } else {
        expenseCategoriesTable.innerHTML = '<p class="text-muted text-center">No expense data</p>';
    }
}

// Filter form submission
document.getElementById('filterForm').addEventListener('submit', function(e) {
    e.preventDefault();
    performFilter();
});

// Reset button
document.getElementById('resetBtn').addEventListener('click', function() {
    document.getElementById('date_from').value = '<?php echo date('Y-m-01'); ?>';
    document.getElementById('date_to').value = '<?php echo date('Y-m-d'); ?>';
    performFilter();
});

// Perform filter via AJAX
function performFilter() {
    const dateFrom = document.getElementById('date_from').value;
    const dateTo = document.getElementById('date_to').value;
    
    if (!dateFrom || !dateTo) {
        showToast('Please select both from and to dates', 'error');
        return;
    }
    
    if (new Date(dateFrom) > new Date(dateTo)) {
        showToast('From date cannot be after to date', 'error');
        return;
    }
    
    // Show loading indicator
    document.getElementById('loadingIndicator').style.display = 'block';
    document.getElementById('keyMetrics').style.opacity = '0.5';
    
    // Prepare form data
    const formData = new FormData();
    formData.append('date_from', dateFrom);
    formData.append('date_to', dateTo);
    
    // Make AJAX request
    fetch('ajax/filter_reports.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('loadingIndicator').style.display = 'none';
        document.getElementById('keyMetrics').style.opacity = '1';
        
        if (data.success) {
            // Update all components
            updateKeyMetrics(data.data);
            updateCharts(data.data);
            updateSummaryTables(data.data);
            
            showToast('Reports updated successfully', 'success');
        } else {
            showToast(data.error || 'Failed to load reports data', 'error');
        }
    })
    .catch(error => {
        document.getElementById('loadingIndicator').style.display = 'none';
        document.getElementById('keyMetrics').style.opacity = '1';
        console.error('Error:', error);
        showToast('An error occurred while loading reports', 'error');
    });
}

function exportReport(type) {
    showToast('Export functionality will be implemented', 'info');
}

// Initialize charts when page loads
document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
});
</script>

<?php require_once 'includes/footer.php'; ?>

<style>
    .btn-outline-info:hover {
        color: #ffffff;
    }
</style>