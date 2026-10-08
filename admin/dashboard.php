<?php
$page_title = 'Dashboard'; 
require_once 'includes/header.php';
require_once '../models/Customer.php';
require_once 'models/Order.php';
require_once 'models/Invoice.php';
require_once 'models/User.php';
require_once '../models/EmailChangeRequest.php';
$customerModel = new Customer();
$orderModel = new Order();
$invoiceModel = new Invoice();
$userModel = new User();
$emailChangeRequestModel = new EmailChangeRequest();
$customerStats = $customerModel->getCustomerStats();
$orderStats = $orderModel->getOrderStats();
$invoiceStats = $invoiceModel->getInvoiceStats();
$userStats = $userModel->getUserStats();
$companyId = get_company_id();
$recentRejectedRequest = $emailChangeRequestModel->hasRecentRejectedRequest($companyId, 7);
$hasPendingEmailRequest = $emailChangeRequestModel->hasPendingRequest($companyId);
$recentOrders = $orderModel->getOrdersWithDetails([], 5);
$totalOrderCount = ($orderStats['pending'] ?? 0) + ($orderStats['in_progress'] ?? 0) + ($orderStats['completed'] ?? 0) + ($orderStats['delivered'] ?? 0) + ($orderStats['cancelled'] ?? 0);
$overdueOrders = $orderModel->getOverdueOrders();
$ordersDueToday = $orderModel->getOrdersDueToday();
$monthlyRevenue = [];
for ($i = 11; $i >= 0; $i--) {
    $date = date('Y-m', strtotime("-$i months"));
    $year = date('Y', strtotime($date));
    $month = date('m', strtotime($date));
    $revenue = (float)$orderModel->getMonthlyRevenue($year, $month);
    $monthlyRevenue[] = [
        'month' => date('M', strtotime($date)),
        'full_month' => date('M Y', strtotime($date)),
        'revenue' => $revenue
    ];
}
$currentMonthRevenue = end($monthlyRevenue)['revenue'] ?? 0;

require_once 'models/Company.php';
$companyModelForProgress = new Company();
$companyForProgress = $companyModelForProgress->find($companyId);

$completionProgress = Company::calculateProfileCompletion($companyForProgress);
$missingProfileFields = Company::getMissingProfileFields($companyForProgress);
$missingFieldsText = '';
if (!empty($missingProfileFields)) {
    $fieldNames = array_values($missingProfileFields);
    if (count($fieldNames) > 1) {
        $lastField = array_pop($fieldNames);
        $missingFieldsText = implode(', ', $fieldNames) . ' and ' . $lastField;
    } else {
        $missingFieldsText = $fieldNames[0];
    }
}
?>

<style>
.dash-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
}
.dash-title {
    font-size: 1.45rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
    letter-spacing: -0.3px;
}
.dash-filter-btn {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 5px 12px;
    font-size: 0.82rem;
    font-weight: 600;
    color: #2563eb !important;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s;
}
.dash-filter-btn:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}
.dash-kpi-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-bottom: 16px;
}
@media (min-width: 992px) {
    .dash-kpi-grid {
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
}

.kpi-card {
    border-radius: 14px;
    padding: 12px 14px;
    color: #ffffff;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 96px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.06);
    position: relative;
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.12);
}

.kpi-purple {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}
.kpi-green {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}
.kpi-orange {
    background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);
}
.kpi-red {
    background: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%);
}

.kpi-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 8px;
}
.kpi-left-group {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
    flex: 1;
}
.kpi-icon-box {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.22);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    color: #ffffff;
    backdrop-filter: blur(4px);
    flex-shrink: 0;
}
.kpi-val-group {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.kpi-main-val {
    font-size: 1.25rem;
    font-weight: 700;
    line-height: 1.1;
    color: #ffffff;
    letter-spacing: -0.3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.kpi-subtitle {
    font-size: 0.70rem;
    color: rgba(255, 255, 255, 0.85);
    font-weight: 500;
    line-height: 1.2;
    margin-top: 1px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.kpi-mini-chart {
    color: rgba(255, 255, 255, 0.75);
    font-size: 0.95rem;
    flex-shrink: 0;
    margin-top: 2px;
}
.kpi-label {
    font-size: 0.78rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.95);
    letter-spacing: 0.01em;
    margin-top: 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.modern-card {
    border-radius: 14px;
    overflow: hidden;
    background: #ffffff;
    border: none;
    box-shadow: 0 4px 16px rgba(0,0,0,0.05);
    margin-bottom: 16px;
}
.modern-card-header {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    padding: 10px 14px;
    border: none;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.modern-card-header h5, .modern-card-header .header-title {
    font-size: 0.95rem;
    font-weight: 600;
    margin: 0;
    color: #ffffff;
    display: flex;
    align-items: center;
}
.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}
.qa-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 12px;
    border-radius: 10px;
    font-size: 0.84rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
    text-align: center;
}
.qa-btn-primary {
    background: linear-gradient(135deg, #5b54ea 0%, #794ce8 100%);
    color: #ffffff !important;
    border: none;
    box-shadow: 0 4px 12px rgba(91, 84, 234, 0.25);
}
.qa-btn-primary:hover {
    color: #ffffff !important;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(91, 84, 234, 0.35);
}
.qa-btn-outline {
    background: #ffffff;
    color: #2563eb !important;
    border: 1.5px solid #3b82f6;
}
.qa-btn-outline:hover {
    background: #eff6ff;
    color: #1d4ed8 !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
}
.no-orders-body {
    padding: 2rem 1.5rem 1.8rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.8rem;
    text-align: center;
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
    0%, 100% { transform: translateY(0) rotate(-5deg); }
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
    background: rgba(167,139,250,0.12);
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
    line-height: 1.5;
    max-width: 320px;
}
.no-orders-cta {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 22px;
    border-radius: 30px;
    background: linear-gradient(135deg, #5b54ea, #794ce8);
    color: #ffffff !important;
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
    box-shadow: 0 4px 15px rgba(91, 84, 234, 0.35);
    transition: transform 0.2s, box-shadow 0.2s;
}
.no-orders-cta:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(91, 84, 234, 0.45);
    color: #ffffff !important;
}
.mobile-order-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px 12px;
    transition: background 0.2s;
}
.mobile-order-card:hover {
    background: #f1f5f9;
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
    font-size: 0.86rem;
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
    opacity: 0.9;
}
.osc-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    min-width: 32px;
}
.osc-count {
    font-size: 0.88rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.1;
}
.osc-pct {
    font-size: 0.72rem;
    color: #94a3b8;
    line-height: 1.1;
}
</style>

<?php if ($completionProgress < 100): ?>
<div class="row mb-3">
    <div class="col-12">
        <div class="card border-0 shadow-sm text-white" style="background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); border-radius: 14px; overflow: hidden; position: relative;">
            <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; border-radius: 50%; background: rgba(255,255,255,0.08); pointer-events: none;"></div>
            
            <div class="card-body p-3 p-md-4 position-relative">
                <div class="row align-items-center">
                    <div class="col-lg-8 col-md-7 mb-3 mb-md-0">
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-white text-primary me-2 px-2 py-1" style="border-radius: 20px; font-weight: 700; font-size: 0.75rem;">ACTION REQUIRED</span>
                            <h5 class="mb-0 fw-bold" style="font-size: 1.1rem;">Complete Your Shop Profile</h5>
                        </div>
                        <p class="mb-3 opacity-90" style="font-size: 0.85rem; max-width: 650px; line-height: 1.4;">
                            Please complete your shop details (<strong><?php echo htmlspecialchars($missingFieldsText); ?></strong>) to access all features.
                        </p>
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1 me-3">
                                <div class="progress" style="height: 8px; border-radius: 10px; background-color: rgba(255,255,255,0.25);">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                         role="progressbar" 
                                         style="width: <?php echo $completionProgress; ?>%; border-radius: 10px; background: linear-gradient(90deg, #10b981 0%, #34d399 100%);" 
                                         aria-valuenow="<?php echo $completionProgress; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                    </div>
                                </div>
                            </div>
                            <span class="fw-bold fs-6 text-white"><?php echo $completionProgress; ?>%</span>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-5 text-md-end">
                        <a href="company-settings.php" class="btn btn-light px-3 py-2 fw-bold text-primary shadow-sm" style="border-radius: 10px; font-size: 0.85rem;">
                            <i class="fas fa-edit me-1"></i> Update Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<div class="dash-header-bar d-flex justify-content-between align-items-center mb-3">
    <h3 class="dash-title d-none d-md-block">Dashboard</h3>
    <div class="dropdown ms-auto">
        <button class="dash-filter-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span>This Month</span>
            <i class="fas fa-chevron-down ms-1" style="font-size: 0.7rem;"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="border-radius: 10px; font-size: 0.85rem;">
            <li><a class="dropdown-item active" href="#">This Month</a></li>
            <li><a class="dropdown-item" href="#">Today</a></li>
            <li><a class="dropdown-item" href="#">This Week</a></li>
            <li><a class="dropdown-item" href="#">This Year</a></li>
        </ul>
    </div>
</div>

<div class="dash-kpi-grid">
    <!-- Card 1: Monthly Revenue (Purple) -->
    <div class="kpi-card kpi-purple">
        <div class="kpi-top">
            <div class="kpi-left-group">
                <div class="kpi-icon-box">
                    <i class="fas fa-camera-retro"></i>
                </div>
                <div class="kpi-val-group">
                    <div class="kpi-main-val">₹<?php echo number_format($currentMonthRevenue); ?></div>
                    <div class="kpi-subtitle">+<?php echo (int)($orderStats['this_month'] ?? 0); ?> this month</div>
                </div>
            </div>
            <div class="kpi-mini-chart">
                <i class="fas fa-chart-simple"></i>
            </div>
        </div>
        <div class="kpi-label">Monthly Revenue</div>
    </div>

    <!-- Card 2: Total Orders (Green) -->
    <div class="kpi-card kpi-green">
        <div class="kpi-top">
            <div class="kpi-left-group">
                <div class="kpi-icon-box">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div class="kpi-val-group">
                    <div class="kpi-main-val"><?php echo number_format($orderStats['total'] ?? 0); ?></div>
                    <div class="kpi-subtitle">+<?php echo (int)($orderStats['this_month'] ?? 0); ?> this month</div>
                </div>
            </div>
            <div class="kpi-mini-chart">
                <i class="fas fa-chart-simple"></i>
            </div>
        </div>
        <div class="kpi-label">Total Orders</div>
    </div>

    <!-- Card 3: Total Invoices (Orange) -->
    <div class="kpi-card kpi-orange">
        <div class="kpi-top">
            <div class="kpi-left-group">
                <div class="kpi-icon-box">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="kpi-val-group">
                    <div class="kpi-main-val"><?php echo number_format($invoiceStats['total'] ?? 0); ?></div>
                    <div class="kpi-subtitle"><?php echo (int)(($invoiceStats['partial'] ?? 0) + ($invoiceStats['due'] ?? 0)); ?> invoices</div>
                </div>
            </div>
            <div class="kpi-mini-chart">
                <i class="fas fa-chart-simple"></i>
            </div>
        </div>
        <div class="kpi-label">Total Invoices</div>
    </div>

    <!-- Card 4: Pending Orders (Red/Pink) -->
    <div class="kpi-card kpi-red">
        <div class="kpi-top">
            <div class="kpi-left-group">
                <div class="kpi-icon-box">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="kpi-val-group">
                    <div class="kpi-main-val"><?php echo (int)($orderStats['pending'] ?? count($overdueOrders)); ?></div>
                    <div class="kpi-subtitle">Need attention</div>
                </div>
            </div>
            <div class="kpi-mini-chart">
                <i class="fas fa-chart-simple"></i>
            </div>
        </div>
        <div class="kpi-label">Pending Orders</div>
    </div>
</div>
<div class="row">
    <div class="col-xl-8 col-lg-7">
        <div class="card modern-card">
            <div class="modern-card-header">
                <div class="d-flex align-items-center">
                    <i class="fas fa-chart-line me-2"></i>
                    <span class="fw-semibold">Monthly Revenue</span>
                </div>
                <div class="dropdown">
                    <button class="btn btn-sm btn-light py-1 px-2 fw-semibold dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 8px; font-size: 0.75rem; background: #ffffff; color: #4f46e5 !important; border: none;">
                        <span>This Year</span>
                        <i class="fas fa-chevron-down ms-1" style="font-size: 0.65rem;"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="border-radius: 8px; font-size: 0.8rem;">
                        <li><a class="dropdown-item active" href="#">This Year</a></li>
                        <li><a class="dropdown-item" href="#">Last Year</a></li>
                    </ul>
                </div>
            </div>
            <div class="card-body p-2 p-md-3">
                <div style="position: relative; height: 200px; width: 100%;">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
        <div class="card modern-card">
            <div class="modern-card-header">
                <div class="d-flex align-items-center">
                    <i class="fas fa-bolt me-2"></i>
                    <span class="fw-semibold">Quick Actions</span>
                </div>
            </div>
            <div class="card-body p-3">
                <div class="quick-actions-grid">
                    <a href="orders.php?action=create" class="qa-btn qa-btn-primary">
                        <i class="fas fa-plus"></i> New Order
                    </a>
                    <a href="measurements.php?action=create" class="qa-btn qa-btn-outline">
                        <i class="fas fa-ruler-combined"></i> Add Measurement
                    </a>
                    <a href="invoices.php?action=create" class="qa-btn qa-btn-outline">
                        <i class="fas fa-file-invoice"></i> Create Invoice
                    </a>
                    <a href="expenses.php?action=create" class="qa-btn qa-btn-outline">
                        <i class="fas fa-receipt"></i> Record Expense
                    </a>
                </div>
            </div>
        </div>
        <div class="card modern-card">
            <div class="modern-card-header">
                <div class="d-flex align-items-center">
                    <i class="fas fa-clock me-2"></i>
                    <span class="fw-semibold">Recent Orders</span>
                </div>
                <a href="orders.php" class="btn btn-sm btn-light py-1 px-2 fw-semibold" style="border-radius: 6px; font-size: 0.75rem; background: #ffffff; color: #1e1b4b; border: none; text-decoration: none;">
                    View All
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($recentOrders)): ?>
                    <div class="d-none d-md-block table-responsive p-3">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
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
                                        <a href="orders.php?view=<?php echo $order['id']; ?>" class="fw-bold text-primary text-decoration-none">
                                            #<?php echo htmlspecialchars($order['order_number']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div>
                                            <span class="fw-semibold text-dark"><?php echo htmlspecialchars($order['customer_name'] ?? (($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''))); ?></span>
                                            <?php if (!empty($order['customer_code'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($order['customer_code']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($order['cloth_type_name'] ?? 'Custom'); ?></td>
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
                                    <td class="fw-bold text-dark"><?php echo format_currency($order['total_amount']); ?></td>
                                    <td>
                                        <span class="<?php echo $order['due_date'] < date('Y-m-d') ? 'text-danger fw-semibold' : 'text-muted'; ?>">
                                            <?php echo format_date($order['due_date']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-md-none p-2 d-flex flex-column gap-2">
                        <?php foreach ($recentOrders as $order): ?>
                        <div class="mobile-order-card">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <a href="orders.php?view=<?php echo $order['id']; ?>" class="fw-bold text-primary text-decoration-none" style="font-size: 0.88rem;">
                                    #<?php echo htmlspecialchars($order['order_number']); ?>
                                </a>
                                <span class="badge bg-<?php 
                                    echo match($order['status']) {
                                        'pending' => 'warning',
                                        'in_progress' => 'info',
                                        'completed' => 'success',
                                        'delivered' => 'primary',
                                        'cancelled' => 'danger',
                                        default => 'secondary'
                                    };
                                ?>" style="font-size: 0.7rem; border-radius: 12px; padding: 3px 8px;">
                                    <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold text-dark" style="font-size: 0.85rem;">
                                        <?php echo htmlspecialchars($order['customer_name'] ?? (($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''))); ?>
                                    </div>
                                    <small class="text-muted" style="font-size: 0.75rem;">
                                        <?php echo htmlspecialchars($order['cloth_type_name'] ?? 'Custom'); ?> &bull; Due: <?php echo format_date($order['due_date']); ?>
                                    </small>
                                </div>
                                <div class="fw-bold text-dark" style="font-size: 0.92rem;">
                                    <?php echo format_currency($order['total_amount']); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-orders-body">
                        <div class="no-orders-scissors-wrap">
                            <span class="scissors-icon">&#9988;</span>
                            <span class="thread-line"></span>
                        </div>
                        <div class="no-orders-texts">
                            <span class="no-orders-tag">NOTHING HERE YET</span>
                            <h5 class="no-orders-heading">No Recent Orders</h5>
                            <p class="no-orders-desc">Your workspace is ready — start stitching orders for your customers!</p>
                        </div>
                        <a href="orders.php?action=create" class="no-orders-cta">
                            <i class="fas fa-plus"></i> Create First Order
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-lg-5">
        <?php
        $statusItems = [
            ['label'=>'Pending',     'key'=>'pending',     'color'=>'#f59e0b', 'count'=>(int)($orderStats['pending'] ?? 0)],
            ['label'=>'In Progress', 'key'=>'in_progress', 'color'=>'#06b6d4', 'count'=>(int)($orderStats['in_progress'] ?? 0)],
            ['label'=>'Completed',   'key'=>'completed',   'color'=>'#10b981', 'count'=>(int)($orderStats['completed'] ?? 0)],
            ['label'=>'Delivered',   'key'=>'delivered',   'color'=>'#6366f1', 'count'=>(int)($orderStats['delivered'] ?? 0)],
            ['label'=>'Cancelled',   'key'=>'cancelled',   'color'=>'#ef4444', 'count'=>(int)($orderStats['cancelled'] ?? 0)],
        ];
        ?>
        <div class="card modern-card">
            <div class="modern-card-header">
                <div class="d-flex align-items-center">
                    <i class="fas fa-layer-group me-2"></i>
                    <span class="fw-semibold">Order Status</span>
                </div>
                <span class="badge bg-white bg-opacity-25 px-2 py-1 fw-semibold" style="border-radius: 20px; font-size: 0.75rem;">
                    <?php echo $totalOrderCount; ?> Total
                </span>
            </div>
            <div class="card-body osc-body">
                <?php foreach ($statusItems as $item): ?>
                <?php $pct = $totalOrderCount > 0 ? round(($item['count'] / $totalOrderCount) * 100) : 0; ?>
                <div class="osc-row">
                    <div class="osc-label-wrap">
                        <span class="osc-dot" style="background:<?php echo $item['color']; ?>;"></span>
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
                <div class="osc-empty-note mt-2 pt-2 text-center" style="border-top: 1px dashed #e2e8f0;">
                    <a href="orders.php?action=create" class="text-primary text-decoration-none fw-semibold" style="font-size: 0.82rem;">
                        <i class="fas fa-plus-circle me-1"></i> Add your first order
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card modern-card">
            <div class="modern-card-header">
                <div class="d-flex align-items-center">
                    <i class="fas fa-bell me-2"></i>
                    <span class="fw-semibold">Alerts & Reminders</span>
                </div>
            </div>
            <div class="card-body p-3">
                <?php if ($recentRejectedRequest): ?>
                    <div class="alert alert-danger py-2 px-3 mb-2" style="font-size: 0.84rem; border-radius: 10px;">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Email Request Rejected</strong>
                        <div class="small opacity-85 mt-1">You can submit a new request after 7 days from the rejection date.</div>
                    </div>
                <?php endif; ?>
                
                <?php if ($hasPendingEmailRequest): ?>
                    <div class="alert alert-info py-2 px-3 mb-2" style="font-size: 0.84rem; border-radius: 10px;">
                        <i class="fas fa-envelope-open-text me-2"></i>
                        <strong>Email Change Pending</strong>
                        <div class="small opacity-85 mt-1">Waiting for Super Admin approval.</div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($overdueOrders)): ?>
                    <div class="alert alert-danger py-2 px-3 mb-2" style="font-size: 0.84rem; border-radius: 10px;">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong><?php echo count($overdueOrders); ?> overdue orders</strong>
                        <div class="small opacity-85 mt-1">Orders past their due date.</div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($ordersDueToday)): ?>
                    <div class="alert alert-warning py-2 px-3 mb-2" style="font-size: 0.84rem; border-radius: 10px;">
                        <i class="fas fa-clock me-2"></i>
                        <strong><?php echo count($ordersDueToday); ?> orders due today</strong>
                        <div class="small opacity-85 mt-1">Scheduled for delivery today.</div>
                    </div>
                <?php endif; ?>
                
                <?php if (($invoiceStats['due'] ?? 0) > 0): ?>
                    <div class="alert alert-info py-2 px-3 mb-2" style="font-size: 0.84rem; border-radius: 10px;">
                        <i class="fas fa-file-invoice-dollar me-2"></i>
                        <strong><?php echo $invoiceStats['due']; ?> unpaid invoices</strong>
                        <div class="small opacity-85 mt-1">Total: <?php echo format_currency($invoiceStats['due_amount']); ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($overdueOrders) && empty($ordersDueToday) && ($invoiceStats['due'] ?? 0) == 0 && !$recentRejectedRequest && !$hasPendingEmailRequest): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <p class="text-muted mb-0" style="font-size: 0.85rem;">All caught up! No urgent alerts.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.osc-bar-fill').forEach(function(bar) {
        const targetWidth = bar.getAttribute('data-width');
        setTimeout(function() {
            bar.style.width = targetWidth + '%';
        }, 150);
    });
    const revenueCtx = document.getElementById('revenueChart')?.getContext('2d');
    if (revenueCtx) {
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthlyRevenue, 'month')); ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode(array_column($monthlyRevenue, 'revenue')); ?>,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.08)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#3b82f6',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return ' ₹' + Number(context.raw || 0).toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f1f5f9',
                            drawBorder: false
                        },
                        ticks: {
                            font: { size: 10 },
                            color: '#64748b',
                            callback: function(value) {
                                if (value >= 100000) return '₹' + (value / 100000).toFixed(1) + 'L';
                                if (value >= 1000) return '₹' + (value / 1000).toFixed(0) + 'k';
                                return '₹' + value;
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            font: { size: 10 },
                            color: '#64748b'
                        }
                    }
                }
            }
        });
    }
});
</script>
<?php require_once 'includes/footer.php'; ?>