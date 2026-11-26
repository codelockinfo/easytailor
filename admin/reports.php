<?php

$page_title = 'Reports & Analytics';
require_once 'includes/header.php';

require_once 'models/Order.php';
require_once 'models/Invoice.php';
require_once 'models/Expense.php';
require_once '../models/Customer.php';
require_once '../helpers/SubscriptionHelper.php';

$orderModel = new Order();
$invoiceModel = new Invoice();
$expenseModel = new Expense();
$customerModel = new Customer();
$companyId = get_company_id();

// Get date range
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Get statistics
$orderStats = $orderModel->getOrderStats();
$invoiceStats = $invoiceModel->getInvoiceStats();
$expenseStats = $expenseModel->getExpenseStats();
$customerStats = $customerModel->getCustomerStats();

$invoiceRevenue = (float)($invoiceStats['total_amount'] ?? 0);
$cashInRevenue = (float)($expenseStats['total_cash_in'] ?? 0);
$totalInflow = $invoiceRevenue + $cashInRevenue;
$totalExpenses = (float)($expenseStats['total_amount'] ?? 0);
$netProfitVal = $totalInflow - $totalExpenses;

// Get monthly data for charts
$monthlyRevenue = [];
$monthlyExpenses = [];
for ($i = 11; $i >= 0; $i--) {
    $date = date('Y-m', strtotime("-$i months"));
    $year = date('Y', strtotime($date));
    $month = date('m', strtotime($date));
    
    $orderRev = (float)$orderModel->getMonthlyRevenue($year, $month);
    $expense = $expenseModel->getMonthlyExpenseStats($year, $month);
    $cashInMonth = (float)($expense['total_cash_in'] ?? 0);
    $expMonth = (float)($expense['total_expenses'] ?? ($expense['total_amount'] ?? 0));
    
    $monthlyRevenue[] = [
        'month' => date('M Y', strtotime($date)),
        'revenue' => $orderRev + $cashInMonth
    ];
    
    $monthlyExpenses[] = [
        'month' => date('M Y', strtotime($date)),
        'expenses' => $expMonth
    ];
}
?>

<style>
/* Exact Reports & Analytics Styling Matching Category / Dashboard / Invoices */
.cat-stat-box {
    border-radius: 16px;
    padding: 12px 14px;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.cat-stat-box:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
}
.cat-report-card {
    border-radius: 16px;
    border: 1px solid #f1f5f9;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    overflow: hidden;
    background: #ffffff;
}
.cat-report-card .card-header {
    background: #ffffff;
    border-bottom: 1px solid #f1f5f9;
    padding: 14px 18px;
}
.cat-table-head th {
    background-color: #f8fafc !important;
    color: #334155 !important;
    font-size: 0.78rem !important;
    font-weight: 700 !important;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e2e8f0 !important;
    padding: 12px 14px !important;
    text-transform: uppercase;
}
.category-table tbody tr {
    transition: background-color 0.12s ease;
}
.category-table tbody tr:hover {
    background-color: #f8fafc;
}
.cat-export-btn {
    border-radius: 14px;
    height: 44px;
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
    border: 1px solid;
    text-decoration: none;
}
.cat-export-btn:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}
#date_from, #date_to {
    height: 42px !important;
    font-size: 0.85rem !important;
    border-radius: 14px !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02) !important;
}
.reports-main-card {
    border-radius: 16px;
    border: 1px solid #f1f5f9;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    overflow: hidden;
    background: #ffffff;
}
@media (max-width: 767.98px) {
    .reports-main-card {
        background: transparent !important;
        background-color: transparent !important;
        border: none !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        margin-bottom: 0 !important;
    }
    .reports-main-card > .card-header {
        background: transparent !important;
        background-color: transparent !important;
        border-bottom: none !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
    .reports-main-card > .card-body {
        background: transparent !important;
        background-color: transparent !important;
        padding: 0 !important;
    }
    #recentOrdersMobileCardsList {
        padding: 0 !important;
    }
}
.report-mobile-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 14px 15px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    transition: all 0.2s ease;
}
@media (max-width: 768px) {
    .cat-stat-box .fs-4 {
        font-size: 1.15rem !important;
    }
}
</style>

<!-- 1. Shared Modern Header Banner -->
<?php 
$pageHeaderTitle = 'Reports & Analytics';
$pageHeaderSubtitle = 'Comprehensive business analytics, financial insights and operational performance.';
include 'includes/page-header.php'; 
?>

<!-- 2. Date Filter Bar -->
<div class="card shadow-sm border-0 mb-3" style="border-radius: 14px; border: 1px solid #f1f5f9 !important;">
    <div class="card-body p-3">
        <form id="filterForm" class="row g-2 align-items-center">
            <div class="col-12 col-sm-4 col-md-4">
                <label for="date_from" class="form-label text-muted fw-semibold mb-1" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">From Date</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
            </div>
            <div class="col-12 col-sm-4 col-md-4">
                <label for="date_to" class="form-label text-muted fw-semibold mb-1" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">To Date</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
            </div>
            <div class="col-12 col-sm-4 col-md-4 d-flex align-items-end gap-2 pt-sm-4">
                <button type="submit" class="btn text-white fw-semibold d-inline-flex align-items-center justify-content-center gap-1 flex-grow-1" id="filterBtn" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 14px; font-size: 0.85rem; height: 42px; border: none; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.28);">
                    <i class="fas fa-filter"></i>
                    <span>Apply Filter</span>
                </button>
                <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center" id="resetBtn" style="border-radius: 14px; font-size: 0.85rem; height: 42px; width: 44px; flex-shrink: 0;" title="Reset Dates">
                    <i class="fas fa-redo-alt"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<div id="loadingIndicator" class="text-center py-4" style="display: none;">
    <div class="spinner-border text-primary" role="status" style="width: 2.2rem; height: 2.2rem;">
        <span class="visually-hidden">Loading...</span>
    </div>
    <p class="mt-2 text-muted small">Updating analytics data...</p>
</div>

<!-- 3. Key Metrics Stat Cards -->
<div id="keyMetrics" class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="cat-stat-box h-100 d-flex align-items-center justify-content-between" style="background: #eff6ff; border: 1px solid #dbeafe;">
            <div>
                <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.5px;">TOTAL CUSTOMERS</span>
                <div class="fs-4 fw-bold text-dark mt-1" id="totalCustomers"><?php echo number_format($customerStats['total']); ?></div>
                <div class="text-muted" style="font-size: 0.72rem;" id="customersThisMonth">+<?php echo $customerStats['this_month']; ?> this month</div>
            </div>
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #dbeafe; color: #2563eb; font-size: 0.95rem;">
                <i class="fas fa-users"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="cat-stat-box h-100 d-flex align-items-center justify-content-between" style="background: #f0fdf4; border: 1px solid #dcfce7;">
            <div>
                <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.5px;">TOTAL REVENUE</span>
                <div class="fs-4 fw-bold text-success mt-1" id="totalRevenue"><?php echo format_currency($totalInflow); ?></div>
                <div class="text-muted" style="font-size: 0.72rem;" id="paidInvoices">
                    <?php echo $invoiceStats['paid']; ?> paid invoices<?php if ($cashInRevenue > 0): ?> <span class="text-success">(+<?php echo format_currency($cashInRevenue); ?> in)</span><?php endif; ?>
                </div>
            </div>
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #dcfce7; color: #16a34a; font-size: 0.95rem;">
                <i class="fas fa-wallet"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="cat-stat-box h-100 d-flex align-items-center justify-content-between" style="background: #fef2f2; border: 1px solid #fee2e2;">
            <div>
                <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.5px;">TOTAL EXPENSES</span>
                <div class="fs-4 fw-bold text-danger mt-1" id="totalExpenses"><?php echo format_currency($totalExpenses); ?></div>
                <div class="text-muted" style="font-size: 0.72rem;" id="expenseTransactions">
                    <?php echo ($expenseStats['total_cash_out_count'] ?? $expenseStats['total']); ?> expenses
                </div>
            </div>
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #fee2e2; color: #dc2626; font-size: 0.95rem;">
                <i class="fas fa-receipt"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="cat-stat-box h-100 d-flex align-items-center justify-content-between" style="background: #faf5ff; border: 1px solid #ede9fe;">
            <div>
                <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.5px;">NET PROFIT</span>
                <div class="fs-4 fw-bold <?php echo $netProfitVal >= 0 ? 'text-primary' : 'text-danger'; ?> mt-1" id="netProfit"><?php echo format_currency($netProfitVal); ?></div>
                <div class="text-muted" style="font-size: 0.72rem;">Total Inflow - Expenses</div>
            </div>
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #ede9fe; color: #9333ea; font-size: 0.95rem;">
                <i class="fas fa-chart-line"></i>
            </div>
        </div>
    </div>
</div>

<!-- 4. Interactive Charts Grid -->
<div class="row g-3 mb-3">
    <div class="col-xl-8 col-lg-7">
        <div class="card cat-report-card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div class="d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; border-radius: 8px; background: #eff6ff; color: #2563eb; font-size: 0.85rem;">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.92rem;">Revenue vs Expenses (12 Months)</h6>
                </div>
            </div>
            <div class="card-body p-3">
                <div style="height: 300px; position: relative;">
                    <canvas id="revenueExpenseChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-4 col-lg-5">
        <div class="card cat-report-card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div class="d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; border-radius: 8px; background: #fffbeb; color: #d97706; font-size: 0.85rem;">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.92rem;">Order Status Distribution</h6>
                </div>
            </div>
            <div class="card-body p-3">
                <div style="height: 300px; position: relative;">
                    <canvas id="orderStatusChart"></canvas>
                    <div id="orderStatusEmptyState" class="position-absolute top-0 start-0 w-100 h-100 flex-column align-items-center justify-content-between text-center p-3 p-sm-4" style="display: none; background: linear-gradient(180deg, #fffdf7 0%, #fffbeb 100%); border: 1.5px dashed #fde68a; border-radius: 14px;">
                        <div class="my-auto d-flex flex-column align-items-center w-100">
                            <div class="position-relative d-inline-flex align-items-center justify-content-center mb-3" style="width: 104px; height: 104px; border-radius: 50%; background: conic-gradient(#fef3c7 0deg 110deg, #e0f2fe 110deg 200deg, #dcfce7 200deg 290deg, #ede9fe 290deg 360deg); padding: 14px;">
                                <div class="w-100 h-100 rounded-circle bg-white d-flex flex-column align-items-center justify-content-center shadow-xs" style="border: 1px solid #fef3c7;">
                                    <i class="fas fa-chart-pie mb-1" style="font-size: 1.25rem; color: #d97706;"></i>
                                    <span class="fw-bold text-dark lh-1" style="font-size: 0.85rem;">0 Orders</span>
                                </div>
                            </div>
                            <div class="fw-bold text-dark mb-1" style="font-size: 1.02rem;">No Orders Yet</div>
                            <p class="text-muted mb-3" style="font-size: 0.82rem; max-width: 270px; line-height: 1.4;">Order status breakdown will appear here once tailoring orders are created.</p>
                            <a href="orders.php?action=create" class="btn btn-sm btn-primary rounded-pill px-4 py-2 fw-semibold shadow-xs" style="font-size: 0.82rem; background: #4f46e5; border-color: #4f46e5;">
                                <i class="fas fa-plus me-1"></i>Create First Order
                            </a>
                        </div>
                        <div class="d-flex flex-wrap justify-content-center gap-2 pt-2 w-100 opacity-75" style="font-size: 0.7rem;">
                            <span class="d-inline-flex align-items-center gap-1 text-secondary"><span class="rounded-circle" style="width:7px;height:7px;background:#ffc107;"></span>Pending</span>
                            <span class="d-inline-flex align-items-center gap-1 text-secondary"><span class="rounded-circle" style="width:7px;height:7px;background:#17a2b8;"></span>In Progress</span>
                            <span class="d-inline-flex align-items-center gap-1 text-secondary"><span class="rounded-circle" style="width:7px;height:7px;background:#28a745;"></span>Completed</span>
                            <span class="d-inline-flex align-items-center gap-1 text-secondary"><span class="rounded-circle" style="width:7px;height:7px;background:#007bff;"></span>Delivered</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-8 col-lg-7">
        <div class="card cat-report-card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div class="d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; border-radius: 8px; background: #fef2f2; color: #dc2626; font-size: 0.85rem;">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.92rem;">Expense Categories</h6>
                </div>
            </div>
            <div class="card-body p-3">
                <div style="height: 300px; position: relative;">
                    <canvas id="expenseCategoryChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-4 col-lg-5">
        <div class="card cat-report-card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div class="d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; border-radius: 8px; background: #f0fdfa; color: #0d9488; font-size: 0.85rem;">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.92rem;">Payment Methods</h6>
                </div>
            </div>
            <div class="card-body p-3">
                <div style="height: 300px; position: relative;">
                    <canvas id="paymentMethodChart"></canvas>
                    <div id="paymentMethodEmptyState" class="position-absolute top-0 start-0 w-100 h-100 flex-column align-items-center justify-content-between text-center p-3 p-sm-4" style="display: none; background: linear-gradient(180deg, #f8fffd 0%, #f0fdfa 100%); border: 1.5px dashed #99f6e4; border-radius: 14px;">
                        <div class="my-auto d-flex flex-column align-items-center w-100">
                            <div class="position-relative d-inline-flex align-items-center justify-content-center mb-3" style="width: 104px; height: 104px; border-radius: 50%; background: conic-gradient(#ccfbf1 0deg 130deg, #dbeafe 130deg 240deg, #ede9fe 240deg 360deg); padding: 14px;">
                                <div class="w-100 h-100 rounded-circle bg-white d-flex flex-column align-items-center justify-content-center shadow-xs" style="border: 1px solid #ccfbf1;">
                                    <i class="fas fa-credit-card mb-1" style="font-size: 1.25rem; color: #0d9488;"></i>
                                    <span class="fw-bold text-dark lh-1" style="font-size: 0.85rem;">₹0.00</span>
                                </div>
                            </div>
                            <div class="fw-bold text-dark mb-1" style="font-size: 1.02rem;">No Payment Data</div>
                            <p class="text-muted mb-3" style="font-size: 0.82rem; max-width: 270px; line-height: 1.4;">Payment method breakdown will appear here once payments or expenses are recorded.</p>
                            <a href="expenses.php?action=create" class="btn btn-sm btn-outline-secondary rounded-pill px-4 py-2 fw-semibold bg-white shadow-xs" style="font-size: 0.82rem; border-color: #99f6e4; color: #0f766e;">
                                <i class="fas fa-plus me-1"></i>Record Expense
                            </a>
                        </div>
                        <div class="d-flex flex-wrap justify-content-center gap-2 pt-2 w-100 opacity-75" style="font-size: 0.7rem;">
                            <span class="d-inline-flex align-items-center gap-1 text-secondary"><span class="rounded-circle" style="width:7px;height:7px;background:#10b981;"></span>Cash</span>
                            <span class="d-inline-flex align-items-center gap-1 text-secondary"><span class="rounded-circle" style="width:7px;height:7px;background:#3b82f6;"></span>UPI</span>
                            <span class="d-inline-flex align-items-center gap-1 text-secondary"><span class="rounded-circle" style="width:7px;height:7px;background:#8b5cf6;"></span>Card</span>
                            <span class="d-inline-flex align-items-center gap-1 text-secondary"><span class="rounded-circle" style="width:7px;height:7px;background:#f59e0b;"></span>Bank</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 5. Recent Orders Table -->
<div class="card cat-report-card mb-3 reports-main-card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <div class="d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; border-radius: 8px; background: #faf5ff; color: #8b5cf6; font-size: 0.85rem;">
                <i class="fas fa-list"></i>
            </div>
            <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.92rem;">Recent Orders</h6>
        </div>
        <a href="orders.php" class="text-decoration-none fw-semibold" style="font-size: 0.8rem; color: #4f46e5;">
            View All <i class="fas fa-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="card-body p-0" id="recentOrdersTable">
        <?php 
        $recentOrders = $orderModel->getOrdersWithDetails([], 5);
        if (!empty($recentOrders)): 
        ?>
            <!-- Desktop Table View -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover mb-0 align-middle category-table">
                    <thead class="cat-table-head">
                        <tr>
                            <th class="ps-3 py-3">ORDER #</th>
                            <th class="py-3">CUSTOMER</th>
                            <th class="py-3">AMOUNT</th>
                            <th class="pe-3 py-3 text-end">STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): 
                            $statusMap = [
                                'pending' => ['bg' => '#fffbeb', 'color' => '#b45309', 'border' => '#fde68a'],
                                'in_progress' => ['bg' => '#eff6ff', 'color' => '#1d4ed8', 'border' => '#bfdbfe'],
                                'completed' => ['bg' => '#f0fdf4', 'color' => '#15803d', 'border' => '#bbf7d0'],
                                'delivered' => ['bg' => '#f0fdfa', 'color' => '#0f766e', 'border' => '#99f6e4'],
                                'cancelled' => ['bg' => '#fef2f2', 'color' => '#b91c1c', 'border' => '#fecaca'],
                            ];
                            $st = $statusMap[$order['status']] ?? ['bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#e2e8f0'];
                            $custName = trim($order['first_name'] . ' ' . $order['last_name']) ?: ($order['measurement_customer_name'] ?? 'Walk-in Customer');
                        ?>
                        <tr>
                            <td class="ps-3 py-3">
                                <span class="badge fw-semibold" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; font-size: 0.8rem; padding: 4px 8px; border-radius: 6px;">
                                    <?php echo htmlspecialchars($order['order_number']); ?>
                                </span>
                            </td>
                            <td class="py-3">
                                <span class="fw-semibold text-dark" style="font-size: 0.88rem;">
                                    <?php echo htmlspecialchars($custName); ?>
                                </span>
                            </td>
                            <td class="py-3">
                                <strong class="text-dark" style="font-size: 0.88rem;"><?php echo format_currency($order['total_amount']); ?></strong>
                            </td>
                            <td class="pe-3 py-3 text-end">
                                <span class="badge rounded-pill fw-semibold px-2 py-1" style="background: <?php echo $st['bg']; ?>; color: <?php echo $st['color']; ?>; border: 1px solid <?php echo $st['border']; ?>; font-size: 0.75rem;">
                                    <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Cards View -->
            <div class="d-md-none d-flex flex-column gap-3 mb-3" id="recentOrdersMobileCardsList">
                <?php foreach ($recentOrders as $order): 
                    $statusMap = [
                        'pending' => ['bg' => '#fffbeb', 'color' => '#b45309', 'border' => '#fde68a'],
                        'in_progress' => ['bg' => '#eff6ff', 'color' => '#1d4ed8', 'border' => '#bfdbfe'],
                        'completed' => ['bg' => '#f0fdf4', 'color' => '#15803d', 'border' => '#bbf7d0'],
                        'delivered' => ['bg' => '#f0fdfa', 'color' => '#0f766e', 'border' => '#99f6e4'],
                        'cancelled' => ['bg' => '#fef2f2', 'color' => '#b91c1c', 'border' => '#fecaca'],
                    ];
                    $st = $statusMap[$order['status']] ?? ['bg' => '#f1f5f9', 'color' => '#475569', 'border' => '#e2e8f0'];
                    $custName = trim($order['first_name'] . ' ' . $order['last_name']) ?: ($order['measurement_customer_name'] ?? 'Walk-in Customer');
                ?>
                <div class="report-mobile-card">
                    <div class="d-flex align-items-center justify-content-between pb-2 mb-2 border-bottom">
                        <span class="badge fw-semibold" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; font-size: 0.8rem; padding: 4px 8px; border-radius: 6px;">
                            <?php echo htmlspecialchars($order['order_number']); ?>
                        </span>
                        <span class="badge rounded-pill fw-semibold px-2 py-1" style="background: <?php echo $st['bg']; ?>; color: <?php echo $st['color']; ?>; border: 1px solid <?php echo $st['border']; ?>; font-size: 0.75rem;">
                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                        </span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small d-block" style="font-size: 0.72rem; text-transform: uppercase;">Customer</span>
                            <span class="fw-bold text-dark" style="font-size: 0.92rem;"><?php echo htmlspecialchars($custName); ?></span>
                        </div>
                        <div class="text-end">
                            <span class="text-muted small d-block" style="font-size: 0.72rem; text-transform: uppercase;">Total</span>
                            <strong class="text-dark" style="font-size: 1.05rem;"><?php echo format_currency($order['total_amount']); ?></strong>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-4 text-muted small">No recent orders found.</div>
        <?php endif; ?>
    </div>
</div>

<!-- 6. Export Reports Card -->
<div class="card cat-report-card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <div class="d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; border-radius: 8px; background: #f0fdf4; color: #16a34a; font-size: 0.85rem;">
                <i class="fas fa-file-excel"></i>
            </div>
            <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.92rem;">Export Reports</h6>
        </div>
        <span class="text-muted small">Download spreadsheets (.xls)</span>
    </div>
    <div class="card-body p-3">
        <?php 
        $exportCheck = SubscriptionHelper::canExportData($companyId);
        if ($exportCheck['allowed']): 
        ?>
        <div class="row g-2">
            <div class="col-6 col-md-3">
                <button class="cat-export-btn w-100" style="background: #eff6ff; border-color: #bfdbfe; color: #2563eb;" data-export-btn="orders" onclick="exportReport('orders')">
                    <i class="fas fa-clipboard-list me-2"></i>Orders
                </button>
            </div>
            <div class="col-6 col-md-3">
                <button class="cat-export-btn w-100" style="background: #f0fdf4; border-color: #bbf7d0; color: #16a34a;" data-export-btn="invoices" onclick="exportReport('invoices')">
                    <i class="fas fa-file-invoice me-2"></i>Invoices
                </button>
            </div>
            <div class="col-6 col-md-3">
                <button class="cat-export-btn w-100" style="background: #fef2f2; border-color: #fecaca; color: #dc2626;" data-export-btn="expenses" onclick="exportReport('expenses')">
                    <i class="fas fa-receipt me-2"></i>Expenses
                </button>
            </div>
            <div class="col-6 col-md-3">
                <button class="cat-export-btn w-100" style="background: #faf5ff; border-color: #ede9fe; color: #8b5cf6;" data-export-btn="customers" onclick="exportReport('customers')">
                    <i class="fas fa-users me-2"></i>Customers
                </button>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-warning mb-0 d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 gap-sm-3" style="border-radius: 12px;">
            <div>
                <i class="fas fa-lock me-2"></i>
                <strong>Data Export Locked:</strong> <?php echo $exportCheck['message']; ?>
            </div>
            <a href="subscriptions.php" class="btn btn-warning btn-sm flex-shrink-0 align-self-center px-3" style="border-radius: 8px; white-space: nowrap;">Upgrade Plan</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Global chart variables
let revenueExpenseChart, orderStatusChart, expenseCategoryChart, paymentMethodChart;

function toggleChartEmptyState(canvasId, emptyId, hasData) {
    const canvas = document.getElementById(canvasId);
    const emptyEl = document.getElementById(emptyId);
    if (canvas) canvas.style.display = hasData ? 'block' : 'none';
    if (emptyEl) emptyEl.style.display = hasData ? 'none' : 'flex';
}

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
    const initOrderStatusData = [
        <?php echo (int)$orderStats['pending']; ?>,
        <?php echo (int)$orderStats['in_progress']; ?>,
        <?php echo (int)$orderStats['completed']; ?>,
        <?php echo (int)$orderStats['delivered']; ?>,
        <?php echo (int)$orderStats['cancelled']; ?>
    ];
    const hasInitOrders = initOrderStatusData.reduce((sum, val) => sum + val, 0) > 0;
    toggleChartEmptyState('orderStatusChart', 'orderStatusEmptyState', hasInitOrders);
    const orderStatusCtx = document.getElementById('orderStatusChart').getContext('2d');
    orderStatusChart = new Chart(orderStatusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Pending', 'In Progress', 'Completed', 'Delivered', 'Cancelled'],
            datasets: [{
                data: initOrderStatusData,
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
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    const reportColorPalette = ['#ef4444', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#06b6d4', '#ec4899', '#64748b'];

    // Expense Category Chart
    const expenseCategoryCtx = document.getElementById('expenseCategoryChart').getContext('2d');
    const initCatLabels = <?php echo json_encode(array_column($expenseStats['by_category'], 'category')); ?>;
    const initCatData = <?php echo json_encode(array_column($expenseStats['by_category'], 'total')); ?>;
    expenseCategoryChart = new Chart(expenseCategoryCtx, {
        type: 'bar',
        data: {
            labels: initCatLabels.length ? initCatLabels : ['No Expenses'],
            datasets: [{
                label: 'Amount',
                data: initCatData.length ? initCatData : [0],
                backgroundColor: initCatLabels.length ? reportColorPalette.slice(0, initCatLabels.length) : ['#e2e8f0'],
                borderRadius: 8,
                maxBarThickness: 45
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
    const initPmLabels = <?php echo json_encode(array_map(function($pm) {
        return ucwords(str_replace('_', ' ', $pm['payment_method']));
    }, $expenseStats['by_payment_method'])); ?>;
    const initPmData = <?php echo json_encode(array_column($expenseStats['by_payment_method'], 'total')); ?>;
    const hasInitPm = initPmLabels.length > 0 && initPmData.reduce((sum, val) => sum + (parseFloat(val) || 0), 0) > 0;
    toggleChartEmptyState('paymentMethodChart', 'paymentMethodEmptyState', hasInitPm);
    paymentMethodChart = new Chart(paymentMethodCtx, {
        type: 'pie',
        data: {
            labels: initPmLabels.length ? initPmLabels : ['None'],
            datasets: [{
                data: initPmData.length ? initPmData : [0],
                backgroundColor: [
                    '#10b981',
                    '#ef4444',
                    '#3b82f6',
                    '#f59e0b',
                    '#8b5cf6'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
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
    if (!orderStatusChart) {
        console.error('Order status chart not initialized');
        return;
    }
    
    const orderStatusData = [
        parseInt(data.charts.order_status.pending) || 0,
        parseInt(data.charts.order_status.in_progress) || 0,
        parseInt(data.charts.order_status.completed) || 0,
        parseInt(data.charts.order_status.delivered) || 0,
        parseInt(data.charts.order_status.cancelled) || 0
    ];
    
    console.log('Updating order status chart with data:', orderStatusData);
    
    // Ensure the chart has the correct structure
    if (!orderStatusChart.data || !orderStatusChart.data.datasets || !orderStatusChart.data.datasets[0]) {
        console.error('Order status chart structure is invalid');
        return;
    }
    
    // Update the data
    orderStatusChart.data.datasets[0].data = orderStatusData;
    
    const totalOrders = orderStatusData.reduce((sum, val) => sum + val, 0);
    toggleChartEmptyState('orderStatusChart', 'orderStatusEmptyState', totalOrders > 0);
    
    // Update the chart
    try {
        // Force a resize and update to ensure the chart renders properly
        orderStatusChart.resize();
        orderStatusChart.update('none'); // Use 'none' animation mode for smoother updates
        console.log('Order status chart updated successfully');
    } catch (error) {
        console.error('Error updating order status chart:', error);
        // Try to reinitialize the chart if update fails
        console.log('Attempting to reinitialize order status chart...');
        try {
            const orderStatusCtx = document.getElementById('orderStatusChart').getContext('2d');
            orderStatusChart.destroy();
            orderStatusChart = new Chart(orderStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'In Progress', 'Completed', 'Delivered', 'Cancelled'],
                    datasets: [{
                        data: orderStatusData,
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
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            console.log('Order status chart reinitialized successfully');
        } catch (reinitError) {
            console.error('Error reinitializing order status chart:', reinitError);
        }
    }

    // Update Expense Category Chart
    const updCatLabels = data.charts.expense_categories.labels || [];
    const updCatData = data.charts.expense_categories.data || [];
    expenseCategoryChart.data.labels = updCatLabels.length ? updCatLabels : ['No Expenses'];
    expenseCategoryChart.data.datasets[0].data = updCatData.length ? updCatData : [0];
    expenseCategoryChart.data.datasets[0].backgroundColor = updCatLabels.length ? reportColorPalette.slice(0, updCatLabels.length) : ['#e2e8f0'];
    expenseCategoryChart.update();

    // Update Payment Method Chart
    const updPmLabels = (data.charts.payment_methods.labels || []).map(l => l.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()));
    const updPmData = data.charts.payment_methods.data || [];
    const hasUpdPm = updPmLabels.length > 0 && updPmData.reduce((sum, val) => sum + (parseFloat(val) || 0), 0) > 0;
    toggleChartEmptyState('paymentMethodChart', 'paymentMethodEmptyState', hasUpdPm);
    paymentMethodChart.data.labels = updPmLabels.length ? updPmLabels : ['None'];
    paymentMethodChart.data.datasets[0].data = updPmData.length ? updPmData : [0];
    if (hasUpdPm) {
        paymentMethodChart.resize();
    }
    paymentMethodChart.update();
}

// Update key metrics
function updateKeyMetrics(data) {
    document.getElementById('totalCustomers').textContent = data.stats.customers.total;
    document.getElementById('customersThisMonth').textContent = '+' + data.stats.customers.this_month + ' this month';
    document.getElementById('totalRevenue').textContent = data.stats.revenue.total_amount;
    document.getElementById('paidInvoices').innerHTML = data.stats.revenue.paid + ' paid invoices' + (data.stats.revenue.cash_in ? ` <span class="text-success">(+${data.stats.revenue.cash_in} in)</span>` : '');
    document.getElementById('totalExpenses').textContent = data.stats.expenses.total_amount;
    document.getElementById('expenseTransactions').textContent = data.stats.expenses.total + ' expenses';
    const netEl = document.getElementById('netProfit');
    netEl.textContent = data.stats.profit.net_profit;
    if (data.stats.profit.net_profit && data.stats.profit.net_profit.includes('-')) {
        netEl.className = 'fs-4 fw-bold text-danger mt-1';
    } else {
        netEl.className = 'fs-4 fw-bold text-primary mt-1';
    }
}

// Update summary tables
function updateSummaryTables(data) {
    // Update Recent Orders Table
    const recentOrdersTable = document.getElementById('recentOrdersTable');
    if (recentOrdersTable) {
        if (data.tables.recent_orders && data.tables.recent_orders.length > 0) {
            let tableHTML = `
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover mb-0 align-middle category-table">
                        <thead class="cat-table-head">
                            <tr>
                                <th class="ps-3 py-3">ORDER #</th>
                                <th class="py-3">CUSTOMER</th>
                                <th class="py-3">AMOUNT</th>
                                <th class="pe-3 py-3 text-end">STATUS</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            let cardsHTML = `
                <div class="d-md-none d-flex flex-column gap-3 mb-3" id="recentOrdersMobileCardsList">
            `;

            const statusBadgeMap = {
                'pending': 'background: #fffbeb; color: #b45309; border: 1px solid #fde68a;',
                'in_progress': 'background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;',
                'completed': 'background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0;',
                'delivered': 'background: #f0fdfa; color: #0f766e; border: 1px solid #99f6e4;',
                'cancelled': 'background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca;'
            };

            data.tables.recent_orders.forEach(order => {
                const badgeStyle = statusBadgeMap[order.status] || 'background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;';
                tableHTML += `
                    <tr>
                        <td class="ps-3 py-3">
                            <span class="badge fw-semibold" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; font-size: 0.8rem; padding: 4px 8px; border-radius: 6px;">
                                ${order.order_number}
                            </span>
                        </td>
                        <td class="py-3">
                            <span class="fw-semibold text-dark" style="font-size: 0.88rem;">${order.customer_name}</span>
                        </td>
                        <td class="py-3">
                            <strong class="text-dark" style="font-size: 0.88rem;">${order.total_amount}</strong>
                        </td>
                        <td class="pe-3 py-3 text-end">
                            <span class="badge rounded-pill fw-semibold px-2 py-1" style="${badgeStyle} font-size: 0.75rem;">${order.status}</span>
                        </td>
                    </tr>
                `;

                cardsHTML += `
                    <div class="report-mobile-card">
                        <div class="d-flex align-items-center justify-content-between pb-2 mb-2 border-bottom">
                            <span class="badge fw-semibold" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; font-size: 0.8rem; padding: 4px 8px; border-radius: 6px;">
                                ${order.order_number}
                            </span>
                            <span class="badge rounded-pill fw-semibold px-2 py-1" style="${badgeStyle} font-size: 0.75rem;">
                                ${order.status}
                            </span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small d-block" style="font-size: 0.72rem; text-transform: uppercase;">Customer</span>
                                <span class="fw-bold text-dark" style="font-size: 0.92rem;">${order.customer_name}</span>
                            </div>
                            <div class="text-end">
                                <span class="text-muted small d-block" style="font-size: 0.72rem; text-transform: uppercase;">Total</span>
                                <strong class="text-dark" style="font-size: 1.05rem;">${order.total_amount}</strong>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            tableHTML += `
                        </tbody>
                    </table>
                </div>
            `;
            cardsHTML += `</div>`;
            recentOrdersTable.innerHTML = tableHTML + cardsHTML;
        } else {
            recentOrdersTable.innerHTML = '<div class="text-center py-4 text-muted small">No recent orders found.</div>';
        }
    }

    // Update Expense Categories Table
    const expenseCategoriesTable = document.getElementById('expenseCategoriesTable');
    if (expenseCategoriesTable) {
        if (data.tables.expense_categories && data.tables.expense_categories.length > 0) {
            let tableHTML = `
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle category-table">
                        <thead class="cat-table-head">
                            <tr>
                                <th class="ps-3 py-3">CATEGORY</th>
                                <th class="py-3">COUNT</th>
                                <th class="pe-3 py-3 text-end">TOTAL AMOUNT</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            data.tables.expense_categories.forEach(category => {
                tableHTML += `
                    <tr>
                        <td class="ps-3 py-3 fw-semibold text-dark">${category.category}</td>
                        <td class="py-3"><span class="badge bg-light text-dark border">${category.count}</span></td>
                        <td class="pe-3 py-3 text-end fw-bold text-dark">${category.total}</td>
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
            expenseCategoriesTable.innerHTML = '<div class="text-center py-4 text-muted small">No expense data found.</div>';
        }
    }
}

// Filter form submission
document.getElementById('filterForm').addEventListener('submit', function(e) {
    e.preventDefault();
    performFilter();
});

// Reset button - reloads page to restore original data/state
document.getElementById('resetBtn').addEventListener('click', function() {
    window.location.href = 'reports.php';
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
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text();
    })
    .then(text => {
        console.log('Raw response text (first 500 chars):', text.substring(0, 500));
        if (text.trim().startsWith('<')) {
            console.error('Server returned HTML instead of JSON. Full response:', text);
            throw new Error('Server returned HTML instead of JSON. Check for PHP errors.');
        }
        
        try {
            const data = JSON.parse(text);
            console.log('Parsed JSON data:', data);
            
            document.getElementById('loadingIndicator').style.display = 'none';
            document.getElementById('keyMetrics').style.opacity = '1';
            
            if (data.success) {
                console.log('Updating charts with data:', data.data);
                console.log('Order status data:', data.data.charts.order_status);
                
                // Update all components
                updateKeyMetrics(data.data);
                updateCharts(data.data);
                updateSummaryTables(data.data);
                
                showToast('Reports updated successfully', 'success');
            } else {
                console.error('Filter error:', data.error);
                showToast(data.error || 'Failed to load reports data', 'error');
            }
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Response text:', text);
            document.getElementById('loadingIndicator').style.display = 'none';
            document.getElementById('keyMetrics').style.opacity = '1';
            showToast('Invalid response from server. Please check the console for details.', 'error');
        }
    })
    .catch(error => {
        document.getElementById('loadingIndicator').style.display = 'none';
        document.getElementById('keyMetrics').style.opacity = '1';
        console.error('Filter fetch error:', error);
        showToast('An error occurred while loading reports: ' + error.message, 'error');
    });
}

const exportEndpoints = {
    orders: 'ajax/export_orders.php',
    invoices: 'ajax/export_invoices.php',
    expenses: 'ajax/export_expenses.php',
    customers: 'ajax/export_customers.php'
};

function exportReport(type) {
    const endpoint = exportEndpoints[type];
    if (!endpoint) {
        showToast('Invalid export type selected.', 'error');
        return;
    }

    const exportBtn = document.querySelector(`[data-export-btn="${type}"]`);
    const originalText = exportBtn ? exportBtn.innerHTML : '';

    if (exportBtn) {
        exportBtn.disabled = true;
        exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Exporting...';
    }

    showToast('Preparing your export... Please wait.', 'info');

    const formData = new FormData();
    formData.append('csrf_token', '<?php echo generate_csrf_token(); ?>');

    fetch(endpoint, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Export failed with status ' + response.status);
        }
        return response.blob();
    })
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = `${type}_export_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.xls`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        showToast('Export completed! Your file has been downloaded.', 'success');
    })
    .catch(error => {
        console.error('Export error:', error);
        showToast('Export failed. Please try again.', 'error');
    })
    .finally(() => {
        if (exportBtn) {
            exportBtn.disabled = false;
            exportBtn.innerHTML = originalText;
        }
    });
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
    @media (max-width: 768px) {
        .stat-card .stat-number {
            font-size: 24px !important;
        }
    }
</style>