<?php


$page_title = 'Customer Management';
require_once 'includes/header.php';
require_once '../models/Customer.php';
require_once '../helpers/SubscriptionHelper.php';
$customerModel = new Customer();
$message = '';
$messageType = '';
$companyId = get_company_id();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                $limitCheck = SubscriptionHelper::canAddCustomer($companyId);
                if (!$limitCheck['allowed']) {
                    $message = $limitCheck['message'] . ' ' . SubscriptionHelper::getUpgradeMessage('customers', SubscriptionHelper::getCurrentPlan($companyId));
                    $messageType = 'error';
                    break;
                }
                
                $data = [
                    'first_name' => sanitize_input($_POST['first_name']),
                    'last_name' => sanitize_input($_POST['last_name']),
                    'email' => sanitize_input($_POST['email']),
                    'phone' => sanitize_input($_POST['phone']),
                    'address' => sanitize_input($_POST['address']),
                    'city' => sanitize_input($_POST['city']),
                    'state' => sanitize_input($_POST['state']),
                    'postal_code' => sanitize_input($_POST['postal_code']),
                    'date_of_birth' => $_POST['date_of_birth'] ?: null,
                    'status' => $_POST['status'] ?? 'active',
                    'notes' => sanitize_input($_POST['notes'])
                ];
                if ($companyId && !isset($data['company_id'])) {
                    $data['company_id'] = $companyId;
                }
                if (!empty($data['email']) && $customerModel->emailExists($data['email'])) {
                    $message = 'Email already exists';
                    $messageType = 'error';
                } else {
                    $customerId = $customerModel->createCustomer($data);
                    if ($customerId) {
                        $message = 'Customer created successfully';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to create customer';
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'update':
                $customerId = (int)$_POST['customer_id'];
                $data = [
                    'first_name' => sanitize_input($_POST['first_name']),
                    'last_name' => sanitize_input($_POST['last_name']),
                    'email' => sanitize_input($_POST['email']),
                    'phone' => sanitize_input($_POST['phone']),
                    'address' => sanitize_input($_POST['address']),
                    'city' => sanitize_input($_POST['city']),
                    'state' => sanitize_input($_POST['state']),
                    'postal_code' => sanitize_input($_POST['postal_code']),
                    'date_of_birth' => $_POST['date_of_birth'] ?: null,
                    'status' => $_POST['status'] ?? 'active',
                    'notes' => sanitize_input($_POST['notes'])
                ];
                if (!empty($data['email']) && $customerModel->emailExists($data['email'], $customerId)) {
                    $message = 'Email already exists';
                    $messageType = 'error';
                } else {
                    if ($customerModel->update($customerId, $data)) {
                        $message = 'Customer updated successfully';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to update customer';
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'delete':
                $customerId = (int)$_POST['customer_id'];
                if ($customerModel->delete($customerId)) {
                    $message = 'Customer deleted successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to delete customer';
                    $messageType = 'error';
                }
                break;
                
        }
    } else {
        $message = 'Invalid request';
        $messageType = 'error';
    }
}
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = RECORDS_PER_PAGE;
$offset = ($page - 1) * $limit;
$allCustomers = $customerModel->getCustomersWithOrderCount();
$totalCustomers = count($allCustomers);

if (!empty($search)) {
    $customers = $customerModel->searchCustomers($search, $limit);
} else {
    $customers = array_slice($allCustomers, $offset, $limit);
}

$totalPages = ceil($totalCustomers / $limit);
$customerStats = $customerModel->getCustomerStats();
$totalCustomerCount = $totalCustomers;
$activeCustomerCount = 0;
$totalOrdersCount = 0;
foreach ($allCustomers as $c) {
    if (($c['status'] ?? 'active') === 'active') {
        $activeCustomerCount++;
    }
    $totalOrdersCount += (int)($c['order_count'] ?? 0);
}
$thisMonthCustomerCount = $customerStats['this_month'] ?? 0;
$editCustomer = null;
if (isset($_GET['edit'])) {
    $editCustomer = $customerModel->find((int)$_GET['edit']);
}
$currentPlan = SubscriptionHelper::getCurrentPlan($companyId);
$customerLimitCheck = SubscriptionHelper::canAddCustomer($companyId);
if (!function_exists('getCustomerAvatarDesign')) {
    function getCustomerAvatarDesign($name, $index) {
        $palettes = [
            ['bg' => '#eff6ff', 'border' => '#dbeafe', 'color' => '#3b82f6'],
            ['bg' => '#f0fdf4', 'border' => '#dcfce7', 'color' => '#10b981'],
            ['bg' => '#faf5ff', 'border' => '#ede9fe', 'color' => '#8b5cf6'],
            ['bg' => '#fffbeb', 'border' => '#fef3c7', 'color' => '#f59e0b'],
            ['bg' => '#fdf2f8', 'border' => '#fce7f3', 'color' => '#ec4899'],
            ['bg' => '#f0fdfa', 'border' => '#ccfbf1', 'color' => '#0d9488']
        ];
        return $palettes[$index % count($palettes)];
    }
}
?>

<style>
.cat-stat-box {
    border-radius: 16px;
    padding: 10px 12px;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.cat-stat-box:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
}
.cat-action-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid;
    cursor: pointer;
    font-size: 0.8rem;
    transition: all 0.15s ease;
    padding: 0;
    text-decoration: none;
}
.cat-action-edit {
    background: #eff6ff;
    border-color: #bfdbfe;
    color: #2563eb;
}
.cat-action-edit:hover {
    background: #dbeafe;
    color: #1d4ed8;
}
.cat-action-view {
    background: #f0fdfa;
    border-color: #99f6e4;
    color: #0d9488;
}
.cat-action-view:hover {
    background: #ccfbf1;
    color: #0f766e;
}
.cat-action-del {
    background: #fef2f2;
    border-color: #fecaca;
    color: #ef4444;
}
.cat-action-del:hover {
    background: #fee2e2;
    color: #dc2626;
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
#searchInput, #startDateFilter, #endDateFilter {
    height: 42px !important;
    font-size: 0.85rem !important;
    border-radius: 14px !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02) !important;
}
@media (max-width: 767.98px) {
    .category-table th {
        font-size: 0.72rem !important;
        padding: 8px 10px !important;
        white-space: nowrap !important;
    }
    .category-table td {
        font-size: 0.78rem !important;
        padding: 8px 10px !important;
        white-space: nowrap !important;
    }
}
@media (max-width: 575.98px) {
    .cat-action-btn {
        width: 30px;
        height: 30px;
        font-size: 0.74rem;
    }
}
</style>
<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show mb-3" style="border-radius: 12px;">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($customerLimitCheck['remaining']) && $customerLimitCheck['remaining'] <= 5 && $customerLimitCheck['remaining'] > 0): ?>
    <div class="alert alert-warning alert-dismissible fade show mb-3" style="border-radius: 12px;">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Customer Limit Warning:</strong> You have <?php echo $customerLimitCheck['remaining']; ?> customer slot(s) remaining in your <?php echo ucfirst($currentPlan); ?> plan. 
        <a href="subscriptions.php" class="alert-link">Upgrade your plan</a> to add more customers.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif (!$customerLimitCheck['allowed']): ?>
    <div class="alert alert-danger alert-dismissible fade show mb-3" style="border-radius: 12px;">
        <i class="fas fa-ban me-2"></i>
        <strong>Customer Limit Reached:</strong> <?php echo $customerLimitCheck['message']; ?> 
        <a href="subscriptions.php" class="alert-link">Upgrade your plan</a> to add more customers.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php
$pageHeaderTitle = 'Customers';
$pageHeaderSubtitle = 'Manage your clients, track their garment orders and relationships.';
include 'includes/page-header.php';
?>
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="cat-stat-box d-flex align-items-center justify-content-between" style="background: #faf5ff; border: 1px solid #ede9fe;">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #c084fc; color: #ffffff;">
                    <i class="fas fa-users" style="font-size: 0.95rem;"></i>
                </div>
                <div class="min-w-0">
                    <div class="fw-bold text-dark lh-1" style="font-size: 1.35rem;"><?php echo number_format($totalCustomerCount); ?></div>
                    <div class="text-muted text-truncate" style="font-size: 0.72rem; margin-top: 3px;">Total Customers</div>
                </div>
            </div>
            <div class="d-flex align-items-end gap-1 ms-1 flex-shrink-0" style="height: 24px; color: #c084fc;">
                <span style="width: 3px; height: 40%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 75%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 50%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 100%; background: currentColor; border-radius: 2px;"></span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="cat-stat-box d-flex align-items-center justify-content-between" style="background: #f0fdf4; border: 1px solid #dcfce7;">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #22c55e; color: #ffffff;">
                    <i class="fas fa-user-check" style="font-size: 0.95rem;"></i>
                </div>
                <div class="min-w-0">
                    <div class="fw-bold text-dark lh-1" style="font-size: 1.35rem;"><?php echo number_format($activeCustomerCount); ?></div>
                    <div class="text-muted text-truncate" style="font-size: 0.72rem; margin-top: 3px;">Active Clients</div>
                </div>
            </div>
            <div class="d-flex align-items-end gap-1 ms-1 flex-shrink-0" style="height: 24px; color: #4ade80;">
                <span style="width: 3px; height: 35%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 60%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 45%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 95%; background: currentColor; border-radius: 2px;"></span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="cat-stat-box d-flex align-items-center justify-content-between" style="background: #eff6ff; border: 1px solid #dbeafe;">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #3b82f6; color: #ffffff;">
                    <i class="fas fa-shopping-bag" style="font-size: 0.95rem;"></i>
                </div>
                <div class="min-w-0">
                    <div class="fw-bold text-dark lh-1" style="font-size: 1.35rem;"><?php echo number_format($totalOrdersCount); ?></div>
                    <div class="text-muted text-truncate" style="font-size: 0.72rem; margin-top: 3px;">Total Orders</div>
                </div>
            </div>
            <div class="d-flex align-items-end gap-1 ms-1 flex-shrink-0" style="height: 24px; color: #60a5fa;">
                <span style="width: 3px; height: 30%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 55%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 40%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 90%; background: currentColor; border-radius: 2px;"></span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="cat-stat-box d-flex align-items-center justify-content-between" style="background: #fffbeb; border: 1px solid #fef3c7;">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #f59e0b; color: #ffffff;">
                    <i class="fas fa-user-plus" style="font-size: 0.95rem;"></i>
                </div>
                <div class="min-w-0">
                    <div class="fw-bold text-dark lh-1" style="font-size: 1.35rem;"><?php echo number_format($thisMonthCustomerCount); ?></div>
                    <div class="text-muted text-truncate" style="font-size: 0.72rem; margin-top: 3px;">This Month</div>
                </div>
            </div>
            <div class="d-flex align-items-end gap-1 ms-1 flex-shrink-0" style="height: 24px; color: #fbbf24;">
                <span style="width: 3px; height: 45%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 70%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 55%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 100%; background: currentColor; border-radius: 2px;"></span>
            </div>
        </div>
    </div>
</div>
<div class="row g-2 align-items-center mb-3">
    <div class="col-12 col-md-4">
        <div class="position-relative">
            <i class="fas fa-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.9rem;"></i>
            <input type="text" id="searchInput" class="form-control" placeholder="Search customer, code, phone..." autocomplete="off" value="<?php echo htmlspecialchars($search); ?>" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding-left: 2.4rem; padding-right: 0.8rem; height: 42px; font-size: 0.85rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        </div>
    </div>
    <div class="col-6 col-sm-3 col-md-2">
        <input type="date" class="form-control" id="startDateFilter" title="Start Date" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.85rem; height: 42px; padding: 0.45rem 0.8rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
    </div>
    <div class="col-6 col-sm-3 col-md-2">
        <input type="date" class="form-control" id="endDateFilter" title="End Date" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.85rem; height: 42px; padding: 0.45rem 0.8rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
    </div>
    <div class="col-12 col-md-4 d-flex gap-2 justify-content-end">
        <button type="button" id="clearSearch" class="btn btn-outline-danger align-items-center justify-content-center" style="border-radius: 14px; height: 42px; font-size: 0.82rem; padding: 0 12px; <?php echo empty($search) ? 'display: none !important;' : 'display: inline-flex !important;'; ?>" title="Clear Filters">
            <i class="fas fa-times me-1"></i> Clear
        </button>
        <?php 
        $exportCheck = SubscriptionHelper::canExportData($companyId);
        if ($exportCheck['allowed']): 
        ?>
            <button type="button" class="btn btn-outline-secondary align-items-center justify-content-center text-nowrap" style="border-radius: 14px; height: 42px; font-size: 0.82rem; padding: 0 14px; border-color: #cbd5e1;" onclick="exportCustomers()" title="Export Customers">
                <i class="fas fa-download me-1"></i> Export
            </button>
        <?php else: ?>
            <a href="subscriptions.php" class="btn btn-outline-secondary align-items-center justify-content-center text-muted text-nowrap" style="border-radius: 14px; height: 42px; font-size: 0.82rem; padding: 0 14px; border-color: #cbd5e1;" title="Upgrade to Premium to Export">
                <i class="fas fa-lock me-1 text-warning"></i> Export
            </a>
        <?php endif; ?>
        <button type="button" class="btn text-white fw-semibold d-inline-flex align-items-center justify-content-center gap-1 flex-grow-1 text-nowrap px-3 shadow-sm" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 14px; font-size: 0.84rem; height: 42px; border: none; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.28);" data-bs-toggle="modal" data-bs-target="#customerModal">
            <i class="fas fa-plus"></i>
            <span>Add Customer</span>
        </button>
    </div>
</div>

<div id="searchResults" class="mb-3" style="display: none;">
    <div class="alert alert-info py-2 px-3 d-flex align-items-center" style="border-radius: 12px; font-size: 0.85rem;">
        <i class="fas fa-info-circle me-2"></i>
        <span><strong id="searchCount">0</strong> customers found</span>
    </div>
</div>
<div class="card shadow-sm border-0 mb-3" style="border-radius: 14px; overflow: hidden; border: 1px solid #f1f5f9 !important;">
    <div class="card-body p-0">
        <?php if (!empty($customers)): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle category-table">
                    <thead class="cat-table-head">
                        <tr>
                            <th class="ps-3 py-3" style="background-color: #f8fafc !important; color: #334155 !important; font-size: 0.78rem !important; font-weight: 700 !important; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0 !important;">
                                <div class="d-flex align-items-center gap-2">
                                    <span>CUSTOMER</span>
                                    <button type="button" class="btn btn-link p-0 text-muted border-0 shadow-none" id="sortCustomersBtn" title="Sort A-Z" style="font-size: 0.75rem; line-height: 1; text-decoration: none;">
                                        <i class="fas fa-sort" id="sortIcon"></i>
                                    </button>
                                </div>
                            </th>
                            <th class="py-3" style="background-color: #f8fafc !important; color: #334155 !important; font-size: 0.78rem !important; font-weight: 700 !important; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0 !important;">CONTACT</th>
                            <th class="py-3" style="background-color: #f8fafc !important; color: #334155 !important; font-size: 0.78rem !important; font-weight: 700 !important; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0 !important;">LOCATION</th>
                            <th class="py-3" style="background-color: #f8fafc !important; color: #334155 !important; font-size: 0.78rem !important; font-weight: 700 !important; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0 !important;">ORDERS</th>
                            <th class="py-3" style="background-color: #f8fafc !important; color: #334155 !important; font-size: 0.78rem !important; font-weight: 700 !important; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0 !important;">STATUS</th>
                            <th class="text-end pe-3 py-3" style="background-color: #f8fafc !important; color: #334155 !important; font-size: 0.78rem !important; font-weight: 700 !important; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0 !important;">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody id="customersTableBody">
                        <?php foreach ($customers as $idx => $customer): 
                            $cleanCustName = display_val($customer['first_name'] . ' ' . $customer['last_name']);
                            if (empty(trim($cleanCustName))) $cleanCustName = 'Walk-in Customer';
                            $custDesign = getCustomerAvatarDesign($cleanCustName, $idx);
                            $initial = strtoupper(substr(trim($cleanCustName), 0, 1) ?: 'C');
                            $dobDisplay = !empty($customer['date_of_birth']) ? format_date($customer['date_of_birth']) : '';
                        ?>
                        <tr class="customer-row" data-name="<?php echo strtolower($cleanCustName); ?>">
                            <td class="ps-3 py-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="d-flex align-items-center justify-content-center flex-shrink-0 fw-bold" style="width: 36px; height: 36px; border-radius: 10px; background: <?php echo $custDesign['bg']; ?>; border: 1px solid <?php echo $custDesign['border']; ?>; color: <?php echo $custDesign['color']; ?>; font-size: 0.88rem;">
                                        <?php echo $initial; ?>
                                    </div>
                                    <div>
                                        <a href="customer-details.php?id=<?php echo $customer['id']; ?>" class="text-decoration-none">
                                            <strong class="text-dark cust-name" style="font-size: 0.9rem;"><?php echo htmlspecialchars($cleanCustName); ?></strong>
                                        </a>
                                        <div class="d-flex align-items-center gap-2 mt-1">
                                            <span class="badge rounded-pill px-2 py-0 fw-medium" style="background: #f1f5f9; color: #475569; font-size: 0.7rem;">
                                                <?php echo htmlspecialchars($customer['customer_code']); ?>
                                            </span>
                                            <?php if ($dobDisplay): ?>
                                                <span class="text-muted small" style="font-size: 0.72rem;">
                                                    <i class="fas fa-birthday-cake me-1 text-muted"></i><?php echo $dobDisplay; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3">
                                <div>
                                    <?php if (!empty($customer['phone'])): ?>
                                        <div class="text-dark" style="font-size: 0.84rem;">
                                            <i class="fas fa-phone me-1 text-muted" style="font-size: 0.75rem;"></i>
                                            <?php echo display_val($customer['phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($customer['email'])): ?>
                                        <div class="text-muted" style="font-size: 0.78rem;">
                                            <i class="fas fa-envelope me-1 text-muted" style="font-size: 0.72rem;"></i>
                                            <?php echo display_val($customer['email']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (empty($customer['phone']) && empty($customer['email'])): ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="py-3">
                                <?php if (!empty($customer['address']) || !empty($customer['city'])): 
                                    $loc = trim(($customer['address'] ?? '') . (!empty($customer['city']) ? ', ' . $customer['city'] : ''));
                                ?>
                                    <span class="text-muted small text-truncate d-inline-block" style="max-width: 180px; font-size: 0.8rem;" title="<?php echo htmlspecialchars($loc); ?>">
                                        <?php echo htmlspecialchars($loc); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3">
                                <span class="badge rounded-pill px-2 py-1 fw-medium" style="background: #e0f2fe; color: #0284c7; font-size: 0.75rem;">
                                    <?php echo (int)($customer['order_count'] ?? 0); ?> Orders
                                </span>
                            </td>
                            <td class="py-3">
                                <?php if (($customer['status'] ?? 'active') === 'active'): ?>
                                    <span class="badge rounded-pill px-2 py-1 fw-medium" style="background: #dcfce7; color: #15803d; font-size: 0.72rem;">Active</span>
                                <?php else: ?>
                                    <span class="badge rounded-pill px-2 py-1 fw-medium" style="background: #f1f5f9; color: #64748b; font-size: 0.72rem;">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3 py-3">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <a href="customer-details.php?id=<?php echo $customer['id']; ?>" class="cat-action-btn cat-action-view" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button type="button" class="cat-action-btn cat-action-edit" onclick="editCustomer(<?php echo htmlspecialchars(json_encode($customer)); ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="cat-action-btn cat-action-del" onclick="deleteCustomer(<?php echo $customer['id']; ?>, '<?php echo htmlspecialchars($cleanCustName); ?>')" title="Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div id="paginationContainer">
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Customer pagination" class="mt-4 pb-3">
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No customers found</h5>
                <p class="text-muted small">
                    <?php if (!empty($search)): ?>
                        No customers match your search criteria.
                    <?php else: ?>
                        Get started by adding your first customer.
                    <?php endif; ?>
                </p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal" style="border-radius: 12px;">
                    <i class="fas fa-plus me-1"></i>Add Customer
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <form method="POST" id="customerForm">
                <div class="modal-header text-white border-0" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);">
                    <h5 class="modal-title fw-bold" id="customerModalTitle">Add Customer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" id="customerAction" value="create">
                    <input type="hidden" name="customer_id" id="customerId">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="first_name" class="form-label fw-semibold small text-dark">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required placeholder="Enter first name" style="border-radius: 12px; height: 42px; font-size: 0.85rem;">
                        </div>
                        <div class="col-md-6">
                            <label for="last_name" class="form-label fw-semibold small text-dark">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required placeholder="Enter last name" style="border-radius: 12px; height: 42px; font-size: 0.85rem;">
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label fw-semibold small text-dark">Email</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="customer@example.com" style="border-radius: 12px; height: 42px; font-size: 0.85rem;">
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label fw-semibold small text-dark">Phone *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0" style="border-radius: 12px 0 0 12px; font-size: 0.85rem;">+91</span>
                                <input type="tel" 
                                       class="form-control border-start-0" 
                                       id="phone" 
                                       name="phone" 
                                       placeholder="10-digit mobile number"
                                       maxlength="10"
                                       pattern="[0-9]{10}"
                                       required
                                       style="border-radius: 0 12px 12px 0; height: 42px; font-size: 0.85rem;">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label fw-semibold small text-dark">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2" placeholder="Street address or landmark" style="border-radius: 12px; font-size: 0.85rem;"></textarea>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="city" class="form-label fw-semibold small text-dark">City</label>
                            <input type="text" class="form-control" id="city" name="city" placeholder="e.g., Surat" style="border-radius: 12px; height: 42px; font-size: 0.85rem;">
                        </div>
                        <div class="col-md-4">
                            <label for="state" class="form-label fw-semibold small text-dark">State</label>
                            <input type="text" class="form-control" id="state" name="state" placeholder="e.g., Gujarat" style="border-radius: 12px; height: 42px; font-size: 0.85rem;">
                        </div>
                        <div class="col-md-4">
                            <label for="postal_code" class="form-label fw-semibold small text-dark">PIN Code</label>
                            <input type="text" class="form-control" id="postal_code" name="postal_code" placeholder="e.g., 395001" maxlength="6" pattern="[0-9]{6}" style="border-radius: 12px; height: 42px; font-size: 0.85rem;">
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="date_of_birth" class="form-label fw-semibold small text-dark">Date of Birth</label>
                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" style="border-radius: 12px; height: 42px; font-size: 0.85rem;">
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label fw-semibold small text-dark">Status</label>
                            <select class="form-select" id="status" name="status" style="border-radius: 12px; height: 42px; font-size: 0.85rem;">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-2">
                        <label for="notes" class="form-label fw-semibold small text-dark">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Special customer preferences, notes..." style="border-radius: 12px; font-size: 0.85rem;"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3 bg-light">
                    <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal" style="border-radius: 12px;">Cancel</button>
                    <button type="submit" class="btn text-white px-4 fw-semibold shadow-sm" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 12px; border: none;">
                        <i class="fas fa-save me-1"></i>Save Customer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger text-white border-0">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="deleteModalLabel">
                            <strong>Confirm Deletion</strong>
                        </h5>
                        <small class="opacity-75">This action cannot be undone</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <div class="mb-3">
                        <i class="fas fa-user-times text-danger" style="font-size: 3rem; opacity: 0.7;"></i>
                    </div>
                    <h6 class="text-dark mb-3">Are you sure you want to delete this customer?</h6>
                </div>
                
                <div class="alert alert-warning border-0 shadow-sm">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-info-circle text-warning me-3 mt-1"></i>
                        <div>
                            <strong class="text-dark">Customer Details:</strong>
                            <p class="mb-0 mt-2" id="deleteCustomerName"></p>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-danger border-0 shadow-sm">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-exclamation-circle text-danger me-3 mt-1"></i>
                        <div>
                            <strong>Warning:</strong>
                            <p class="mb-0 mt-1">This action will permanently delete the customer record and all associated data cannot be undone.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer border-0 p-4 bg-light">
                <div class="w-100 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="customer_id" id="deleteCustomerId">
                        <button type="submit" class="btn btn-danger px-4">
                            <i class="fas fa-trash me-2"></i>Yes, Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    setupPhoneValidation('phone', '+91');
});

function editCustomer(customer) {
    document.getElementById('customerModalTitle').textContent = 'Edit Customer';
    document.getElementById('customerAction').value = 'update';
    document.getElementById('customerId').value = customer.id;
    
    document.getElementById('first_name').value = customer.first_name || '';
    document.getElementById('last_name').value = customer.last_name || '';
    document.getElementById('email').value = customer.email || '';
        let phoneNumber = customer.phone || '';
    phoneNumber = phoneNumber.replace(/^\+91/, '').replace(/[^0-9]/g, '').slice(0, 10);
    document.getElementById('phone').value = phoneNumber;
    
    document.getElementById('address').value = customer.address || '';
    document.getElementById('city').value = customer.city || '';
    document.getElementById('state').value = customer.state || '';
    document.getElementById('postal_code').value = customer.postal_code || '';
    document.getElementById('date_of_birth').value = customer.date_of_birth || '';
    document.getElementById('status').value = customer.status || 'active';
    document.getElementById('notes').value = customer.notes || '';
    
    new bootstrap.Modal(document.getElementById('customerModal')).show();
}

function deleteCustomer(customerId, customerName) {
    document.getElementById('deleteCustomerId').value = customerId;
    document.getElementById('deleteCustomerName').textContent = customerName;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function exportCustomers() {
    showToast('🔄 Preparing your customer export... Please wait.', 'info');
    
    const exportBtn = document.querySelector('button[onclick="exportCustomers()"]');
    const originalText = exportBtn.innerHTML;
    exportBtn.disabled = true;
    exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Exporting...';
    
    const formData = new FormData();
    formData.append('csrf_token', '<?php echo generate_csrf_token(); ?>');
    
    setTimeout(() => {
        showToast('📊 Generating Excel file with all customer details...', 'info');
    }, 500);
    
    fetch('ajax/export_customers.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Export failed');
        }
        return response.blob();
    })
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = 'customers_export_' + new Date().toISOString().slice(0, 19).replace(/:/g, '-') + '.xls';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        showToast('✅ Export completed! Your Excel file has been downloaded.', 'success');
        
        exportBtn.disabled = false;
        exportBtn.innerHTML = originalText;
    })
    .catch(error => {
        console.error('Export error:', error);
        showToast('❌ Export failed. Please try again.', 'error');
        
        exportBtn.disabled = false;
        exportBtn.innerHTML = originalText;
    });
}
document.addEventListener('DOMContentLoaded', function() {
    const customerForm = document.getElementById('customerForm');
    if (customerForm) {
        customerForm.addEventListener('submit', function(e) {
            const phoneField = document.getElementById('phone');
            if (phoneField && phoneField.value.trim()) {
                const phoneValue = getPhoneWithPrefix('phone', '+91');
                
                if (!validatePhoneNumber('phone', '+91')) {
                    e.preventDefault();
                    phoneField.focus();
                    alert('Please enter a valid 10-digit mobile number.');
                    return false;
                }
                                phoneField.value = phoneValue;
            }
        });
    }
});
const customerModal = document.getElementById('customerModal');
if (customerModal) {
    customerModal.addEventListener('hidden.bs.modal', function() {
        const title = document.getElementById('customerModalTitle');
        if (title) title.textContent = 'Add Customer';
        const form = document.getElementById('customerForm');
        if (form) form.reset();
        const idInput = document.getElementById('customerId');
        if (idInput) idInput.value = '';
        const actionInput = document.getElementById('action');
        if (actionInput) actionInput.value = 'create';
    });
}

let sortAscending = true;
document.getElementById('sortCustomersBtn')?.addEventListener('click', function(e) {
    e.preventDefault();
    sortAscending = !sortAscending;
    const sortIcon = document.getElementById('sortIcon');
    if (sortIcon) {
        sortIcon.className = sortAscending ? 'fas fa-sort-alpha-down' : 'fas fa-sort-alpha-up';
    }
    
    const tableBody = document.getElementById('customersTableBody') || document.querySelector('.table tbody');
    if (tableBody) {
        const rows = Array.from(tableBody.querySelectorAll('tr.customer-row'));
        rows.sort((a, b) => {
            const nameA = a.getAttribute('data-name') || '';
            const nameB = b.getAttribute('data-name') || '';
            return sortAscending ? nameA.localeCompare(nameB) : nameB.localeCompare(nameA);
        });
        rows.forEach(r => tableBody.appendChild(r));
    }
});

let searchTimeout;
const searchInput = document.getElementById('searchInput');
const startDateFilter = document.getElementById('startDateFilter');
const endDateFilter = document.getElementById('endDateFilter');
const searchResults = document.getElementById('searchResults');
const searchCount = document.getElementById('searchCount');
const clearSearch = document.getElementById('clearSearch');
const customersTable = document.getElementById('customersTableBody') || document.querySelector('.table tbody');

let originalTableContent = '';
if (customersTable) {
    originalTableContent = customersTable.innerHTML;
}

function performSearch() {
    const searchTerm = searchInput ? searchInput.value.trim() : '';
    const startDate = startDateFilter ? startDateFilter.value : '';
    const endDate = endDateFilter ? endDateFilter.value : '';
    
    clearTimeout(searchTimeout);
    const hasFilter = Boolean(searchTerm.length > 0 || startDate || endDate);
    if (clearSearch) {
        clearSearch.style.setProperty('display', hasFilter ? 'inline-flex' : 'none', 'important');
    }
    if (!hasFilter) {
        if (customersTable && originalTableContent) {
            customersTable.innerHTML = originalTableContent;
        }
        if (searchResults) {
            searchResults.style.display = 'none';
        }
        const paginationContainer = document.getElementById('paginationContainer');
        if (paginationContainer) {
            paginationContainer.style.display = 'block';
        }
        return;
    }
    if (searchResults) {
        searchResults.style.display = 'block';
    }
    if (searchCount) {
        searchCount.textContent = 'Searching...';
    }
    const params = new URLSearchParams();
    if (searchTerm) params.append('search', searchTerm);
    if (startDate) params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);
    searchTimeout = setTimeout(() => {
        const url = `ajax/search_customers.php?${params.toString()}`;
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displaySearchResults(data.customers);
                    if (searchCount) {
                        searchCount.textContent = data.count;
                    }
                    const paginationContainer = document.getElementById('paginationContainer');
                    if (paginationContainer) {
                        paginationContainer.style.display = 'none';
                    }
                } else {
                    console.error('Search error:', data.error);
                    if (searchCount) {
                        searchCount.textContent = 'Search failed';
                    }
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                if (searchCount) {
                    searchCount.textContent = 'Search failed';
                }
            });
    }, 300);
}

if (searchInput) {
    searchInput.addEventListener('input', performSearch);
}

if (startDateFilter) {
    startDateFilter.addEventListener('change', performSearch);
}

if (endDateFilter) {
    endDateFilter.addEventListener('change', performSearch);
}

if (clearSearch) {
    clearSearch.addEventListener('click', function() {
        if (searchInput) searchInput.value = '';
        if (startDateFilter) startDateFilter.value = '';
        if (endDateFilter) endDateFilter.value = '';
        
        this.style.setProperty('display', 'none', 'important');
        if (customersTable && originalTableContent) {
            customersTable.innerHTML = originalTableContent;
        }
        if (searchResults) {
            searchResults.style.display = 'none';
        }
        
        window.location.href = 'customers.php';
    });
}

const avatarPalettes = [
    { bg: '#eff6ff', border: '#dbeafe', color: '#3b82f6' },
    { bg: '#f0fdf4', border: '#dcfce7', color: '#10b981' },
    { bg: '#faf5ff', border: '#ede9fe', color: '#8b5cf6' },
    { bg: '#fffbeb', border: '#fef3c7', color: '#f59e0b' },
    { bg: '#fdf2f8', border: '#fce7f3', color: '#ec4899' },
    { bg: '#f0fdfa', border: '#ccfbf1', color: '#0d9488' }
];

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
function displaySearchResults(customers) {
    if (!customersTable) {
        console.warn('Customers table not found');
        return;
    }
    if (!customers || customers.length === 0) {
        customersTable.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-5">
                    <i class="fas fa-search fa-2x text-muted mb-2"></i>
                    <h5 class="text-muted">No customers found</h5>
                    <p class="text-muted small">Try adjusting your search terms</p>
                </td>
            </tr>
        `;
        return;
    }
    
    let tableHTML = '';
    customers.forEach((customer, idx) => {
        let cleanCustName = customer.name || ((customer.first_name || '') + ' ' + (customer.last_name || '')).trim();
        if (!cleanCustName) cleanCustName = 'Walk-in Customer';
        
        let initial = (cleanCustName.charAt(0) || 'C').toUpperCase();
        
        let charCodeSum = 0;
        for (let c = 0; c < cleanCustName.length; c++) charCodeSum += cleanCustName.charCodeAt(c);
        const palette = avatarPalettes[(charCodeSum + idx) % avatarPalettes.length];
        
        const dobDisplay = customer.date_of_birth ? 
            `<span class="text-muted small" style="font-size: 0.72rem;"><i class="fas fa-birthday-cake me-1 text-muted"></i>${escapeHtml(customer.date_of_birth)}</span>` : '';
        
        let phoneDisplay = '';
        if (customer.phone) {
            phoneDisplay = `<div class="text-dark" style="font-size: 0.84rem;"><i class="fas fa-phone me-1 text-muted" style="font-size: 0.75rem;"></i>${escapeHtml(customer.phone)}</div>`;
        }
        let emailDisplay = '';
        if (customer.email) {
            emailDisplay = `<div class="text-muted" style="font-size: 0.78rem;"><i class="fas fa-envelope me-1 text-muted" style="font-size: 0.72rem;"></i>${escapeHtml(customer.email)}</div>`;
        }
        const contactHtml = (phoneDisplay || emailDisplay) ? `${phoneDisplay}${emailDisplay}` : '<span class="text-muted small">—</span>';
        const locationDisplay = customer.address ? 
            `<span class="text-muted small text-truncate d-inline-block" style="max-width: 180px; font-size: 0.8rem;" title="${escapeHtml(customer.address)}">${escapeHtml(customer.address)}</span>` : 
            '<span class="text-muted small">—</span>';
        const orderCount = customer.total_orders || customer.order_count || 0;
        const status = (customer.status || 'active').toLowerCase();
        const statusBadge = status === 'active' ?
            '<span class="badge rounded-pill px-2 py-1 fw-medium" style="background: #dcfce7; color: #15803d; font-size: 0.72rem;">Active</span>' :
            '<span class="badge rounded-pill px-2 py-1 fw-medium" style="background: #f1f5f9; color: #64748b; font-size: 0.72rem;">Inactive</span>';
        
        const customerJson = JSON.stringify(customer).replace(/"/g, '&quot;');
        tableHTML += `
            <tr class="customer-row" data-name="${escapeHtml(cleanCustName.toLowerCase())}">
                <td class="ps-3 py-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="d-flex align-items-center justify-content-center flex-shrink-0 fw-bold" style="width: 36px; height: 36px; border-radius: 10px; background: ${palette.bg}; border: 1px solid ${palette.border}; color: ${palette.color}; font-size: 0.88rem;">
                            ${initial}
                        </div>
                        <div>
                            <a href="customer-details.php?id=${customer.id}" class="text-decoration-none">
                                <strong class="text-dark cust-name" style="font-size: 0.9rem;">${escapeHtml(cleanCustName)}</strong>
                            </a>
                            <div class="d-flex align-items-center gap-2 mt-1">
                                <span class="badge rounded-pill px-2 py-0 fw-medium" style="background: #f1f5f9; color: #475569; font-size: 0.7rem;">
                                    ${escapeHtml(customer.customer_code || '')}
                                </span>
                                ${dobDisplay}
                            </div>
                        </div>
                    </div>
                </td>
                <td class="py-3">
                    ${contactHtml}
                </td>
                <td class="py-3">
                    ${locationDisplay}
                </td>
                <td class="py-3">
                    <span class="badge rounded-pill px-2 py-1 fw-medium" style="background: #e0f2fe; color: #0284c7; font-size: 0.75rem;">
                        ${orderCount} Orders
                    </span>
                </td>
                <td class="py-3">
                    ${statusBadge}
                </td>
                <td class="text-end pe-3 py-3">
                    <div class="d-inline-flex align-items-center gap-1">
                        <a href="customer-details.php?id=${customer.id}" class="cat-action-btn cat-action-view" title="View Details">
                            <i class="fas fa-eye"></i>
                        </a>
                        <button type="button" class="cat-action-btn cat-action-edit" onclick="editCustomer(${customerJson})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="cat-action-btn cat-action-del" onclick="deleteCustomer(${customer.id}, '${escapeHtml(cleanCustName)}')" title="Delete">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    customersTable.innerHTML = tableHTML;
}
</script>

<?php require_once 'includes/footer.php'; ?>

<style>
    @media (max-width: 768px) {
        .form-label.d-block {
            display: none !important;
        }
        .card-header {
        display: flex !important;
        align-items: flex-start !important;
        flex-direction: column;
        gap: 15px;
    }
    .card-header .btn-light,
    .card-header .d-flex {
        width: 100% !important;
    }
    }

    .btn-outline-info:hover {
        color: #ffffff;
    }
</style>