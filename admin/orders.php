<?php
require_once '../config/config.php';
require_login();
require_once 'models/Order.php';
require_once '../models/Customer.php';
require_once '../models/Measurement.php';
require_once 'models/ClothType.php';
require_once 'models/User.php';
require_once '../helpers/SubscriptionHelper.php';
$orderModel = new Order();
$customerModel = new Customer();
$measurementModel = new Measurement();
$clothTypeModel = new ClothType();
$userModel = new User();

$message = '';
$messageType = '';
$companyId = get_company_id();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                if (has_role('tailor')) {
                    $_SESSION['message'] = 'Tailors cannot create orders.';
                    $_SESSION['messageType'] = 'error';
                    header('Location: orders.php');
                    exit;
                }
                $limitCheck = SubscriptionHelper::canAddOrder($companyId);
                if (!$limitCheck['allowed']) {
                    $_SESSION['message'] = $limitCheck['message'] . ' ' . SubscriptionHelper::getUpgradeMessage('orders', SubscriptionHelper::getCurrentPlan($companyId));
                    $_SESSION['messageType'] = 'error';
                    header('Location: orders.php');
                    exit;
                }
                
                $userId = get_user_id();
                if (!$userId) {
                    $_SESSION['message'] = 'Error: User session invalid. Please logout and login again.';
                    $_SESSION['messageType'] = 'error';
                    header('Location: orders.php');
                    exit;
                }
                
                $user = $userModel->find($userId);
                if (!$user) {
                    $_SESSION['message'] = 'Error: User account not found. Please contact administrator.';
                    $_SESSION['messageType'] = 'error';
                    header('Location: orders.php');
                    exit;
                }
                
                $data = [
                    'customer_name' => sanitize_input($_POST['customer_id']),
                    'customer_id' => null,
                    'cloth_type_id' => (int)$_POST['cloth_type_id'],
                    'measurement_id' => !empty($_POST['measurement_id']) ? (int)$_POST['measurement_id'] : null,
                    'assigned_tailor_id' => !empty($_POST['assigned_tailor_id']) ? (int)$_POST['assigned_tailor_id'] : null,
                    'order_date' => $_POST['order_date'],
                    'due_date' => $_POST['due_date'],
                    'total_amount' => (float)$_POST['total_amount'],
                    'advance_amount' => (float)$_POST['advance_amount'],
                    'balance_amount' => (float)$_POST['total_amount'] - (float)$_POST['advance_amount'],
                    'special_instructions' => sanitize_input($_POST['special_instructions']),
                    'created_by' => $userId
                ];
                if ($companyId && !isset($data['company_id'])) {
                    $data['company_id'] = $companyId;
                }
                
                try {
                    $orderId = $orderModel->createOrder($data);
                    if ($orderId) {
                        $order = $orderModel->find($orderId);
                        require_once '../helpers/GA4Helper.php';
                        $_SESSION['ga4_event'] = GA4Helper::trackCreateOrder(
                            $orderId,
                            $order['order_number'] ?? null,
                            $data['total_amount'] ?? null
                        );
                        $_SESSION['message'] = 'Order created successfully';
                        $_SESSION['messageType'] = 'success';
                    } else {
                        $_SESSION['message'] = 'Failed to create order';
                        $_SESSION['messageType'] = 'error';
                    }
                } catch (Exception $e) {
                    $_SESSION['message'] = 'Error creating order: ' . $e->getMessage();
                    $_SESSION['messageType'] = 'error';
                }
                header('Location: orders.php');
                exit;
                break;
                
            case 'update':
                if (has_role('tailor')) {
                    $_SESSION['message'] = 'Tailors cannot edit orders.';
                    $_SESSION['messageType'] = 'error';
                    header('Location: orders.php');
                    exit;
                }
                $orderId = (int)$_POST['order_id'];
                $data = [
                    'customer_name' => sanitize_input($_POST['customer_id']),
                    'customer_id' => null,
                    'cloth_type_id' => (int)$_POST['cloth_type_id'],
                    'measurement_id' => !empty($_POST['measurement_id']) ? (int)$_POST['measurement_id'] : null,
                    'assigned_tailor_id' => !empty($_POST['assigned_tailor_id']) ? (int)$_POST['assigned_tailor_id'] : null,
                    'order_date' => $_POST['order_date'],
                    'due_date' => $_POST['due_date'],
                    'total_amount' => (float)$_POST['total_amount'],
                    'advance_amount' => (float)$_POST['advance_amount'],
                    'balance_amount' => (float)$_POST['total_amount'] - (float)$_POST['advance_amount'],
                    'special_instructions' => sanitize_input($_POST['special_instructions'])
                ];
                
                if ($orderModel->update($orderId, $data)) {
                    $_SESSION['message'] = 'Order updated successfully';
                    $_SESSION['messageType'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to update order';
                    $_SESSION['messageType'] = 'error';
                }
                header('Location: orders.php');
                exit;
                break;
                
            case 'update_status':
                $orderId = (int)$_POST['order_id'];
                $status = $_POST['status'];
                if (has_role('tailor')) {
                    $existingOrder = $orderModel->find($orderId);
                    if (!$existingOrder || $existingOrder['assigned_tailor_id'] != get_user_id()) {
                        $_SESSION['message'] = 'You can only update orders assigned to you.';
                        $_SESSION['messageType'] = 'error';
                        header('Location: orders.php');
                        exit;
                    }
                }
                
                if ($orderModel->updateOrderStatus($orderId, $status)) {
                    $_SESSION['message'] = 'Order status updated successfully';
                    $_SESSION['messageType'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to update order status';
                    $_SESSION['messageType'] = 'error';
                }
                header('Location: orders.php');
                exit;
                break;
                
            case 'delete':
                if (has_role('tailor')) {
                    $_SESSION['message'] = 'Tailors cannot delete orders.';
                    $_SESSION['messageType'] = 'error';
                    header('Location: orders.php');
                    exit;
                }
                $orderId = (int)$_POST['order_id'];
                if ($orderModel->delete($orderId)) {
                    $_SESSION['message'] = 'Order deleted successfully';
                    $_SESSION['messageType'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to delete order';
                    $_SESSION['messageType'] = 'error';
                }
                header('Location: orders.php');
                exit;
                break;
        }
    } else {
        $_SESSION['message'] = 'Invalid request';
        $_SESSION['messageType'] = 'error';
        header('Location: orders.php');
        exit;
    }
}
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['messageType']);
}
$page_title = 'Order Management';
require_once 'includes/header.php';

// Get orders
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = RECORDS_PER_PAGE;
$offset = ($page - 1) * $limit;

$conditions = [];
if (!empty($status_filter)) {
    $conditions['status'] = $status_filter;
}
if (has_role('tailor')) {
    $conditions['assigned_tailor_id'] = get_user_id();
}

// Get all orders for the company first to get accurate count
$allOrders = $orderModel->getOrdersWithDetails($conditions, null, 0);
$totalOrders = count($allOrders);
// Then slice for pagination
$orders = array_slice($allOrders, $offset, $limit);
$totalPages = ceil($totalOrders / $limit);

// Get data for dropdowns
$customers = $customerModel->findAll(['status' => 'active'], 'first_name, last_name');
$measurementCustomers = $measurementModel->getDistinctCustomerNames();
$clothTypes = $clothTypeModel->findAll(['status' => 'active'], 'name');
$tailors = $userModel->findAll(['role' => 'tailor', 'status' => 'active'], 'full_name');

// Get order for editing
$editOrder = null;
if (isset($_GET['edit'])) {
    $editOrder = $orderModel->find((int)$_GET['edit']);
}

// Check subscription limits
$currentPlan = SubscriptionHelper::getCurrentPlan($companyId);
$orderLimitCheck = SubscriptionHelper::canAddOrder($companyId);

// Get order statistics
$orderStats = $orderModel->getOrderStats();

// Function for customer avatar design
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
/* Exact Orders Styling Matching Category / Customer Page */
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
.cat-action-status {
    background: #f8fafc;
    border-color: #e2e8f0;
    color: #475569;
}
.cat-action-status:hover {
    background: #e2e8f0;
    color: #1e293b;
}
.cat-action-status::after {
    display: none !important;
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
.cat-badge-pending {
    background-color: #fffbeb !important;
    color: #b45309 !important;
    border: 1px solid #fde68a !important;
}
.cat-badge-in_progress {
    background-color: #eff6ff !important;
    color: #1d4ed8 !important;
    border: 1px solid #bfdbfe !important;
}
.cat-badge-completed {
    background-color: #f0fdf4 !important;
    color: #15803d !important;
    border: 1px solid #bbf7d0 !important;
}
.cat-badge-delivered {
    background-color: #faf5ff !important;
    color: #7e22ce !important;
    border: 1px solid #e9d5ff !important;
}
.cat-badge-cancelled {
    background-color: #fef2f2 !important;
    color: #b91c1c !important;
    border: 1px solid #fecaca !important;
}
#searchInput, #startDateFilter, #endDateFilter {
    height: 42px !important;
    font-size: 0.85rem !important;
    border-radius: 14px !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02) !important;
}
#statusFilter, #clothTypeFilter, #tailorFilter {
    height: 42px !important;
    font-size: 0.85rem !important;
    border-radius: 14px !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02) !important;
    padding: 0.45rem 2.2rem 0.45rem 0.8rem !important;
    line-height: 1.4 !important;
    background-color: #ffffff !important;
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

<?php if (!has_role('tailor')): ?>
<?php if (isset($orderLimitCheck['remaining']) && $orderLimitCheck['remaining'] <= 10 && $orderLimitCheck['remaining'] > 0): ?>
    <div class="alert alert-warning alert-dismissible fade show mb-3" style="border-radius: 12px;">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Order Limit Warning:</strong> You have <?php echo $orderLimitCheck['remaining']; ?> order slot(s) remaining in your <?php echo ucfirst($currentPlan); ?> plan. 
        <a href="subscriptions.php" class="alert-link">Upgrade your plan</a> to add more orders.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif (!$orderLimitCheck['allowed']): ?>
    <div class="alert alert-danger alert-dismissible fade show mb-3" style="border-radius: 12px;">
        <i class="fas fa-ban me-2"></i>
        <strong>Order Limit Reached:</strong> <?php echo $orderLimitCheck['message']; ?> 
        <a href="subscriptions.php" class="alert-link">Upgrade your plan</a> to add more orders.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php endif; ?>

<!-- 1. Orders Hero Banner (Using Shared Component) -->
<?php
$pageHeaderTitle = 'Orders';
$pageHeaderSubtitle = 'Track, manage and update customer tailoring orders.';
include 'includes/page-header.php';
?>

<!-- 2. Stat Cards (2x2 on Mobile, 4 cols on Desktop) -->
<div class="row g-2 mb-3">
    <!-- Pending -->
    <div class="col-6 col-md-3">
        <div class="cat-stat-box d-flex align-items-center justify-content-between" style="background: #fffbeb; border: 1px solid #fef3c7;">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #f59e0b; color: #ffffff;">
                    <i class="fas fa-clock" style="font-size: 0.95rem;"></i>
                </div>
                <div class="min-w-0">
                    <div class="fw-bold text-dark lh-1" style="font-size: 1.35rem;" id="stat-pending"><?php echo number_format($orderStats['pending']); ?></div>
                    <div class="text-muted text-truncate" style="font-size: 0.72rem; margin-top: 3px;">Pending</div>
                </div>
            </div>
            <div class="d-flex align-items-end gap-1 ms-1 flex-shrink-0" style="height: 24px; color: #fbbf24;">
                <span style="width: 3px; height: 40%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 75%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 50%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 100%; background: currentColor; border-radius: 2px;"></span>
            </div>
        </div>
    </div>

    <!-- In Progress -->
    <div class="col-6 col-md-3">
        <div class="cat-stat-box d-flex align-items-center justify-content-between" style="background: #eff6ff; border: 1px solid #dbeafe;">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #3b82f6; color: #ffffff;">
                    <i class="fas fa-cogs" style="font-size: 0.95rem;"></i>
                </div>
                <div class="min-w-0">
                    <div class="fw-bold text-dark lh-1" style="font-size: 1.35rem;" id="stat-in_progress"><?php echo number_format($orderStats['in_progress']); ?></div>
                    <div class="text-muted text-truncate" style="font-size: 0.72rem; margin-top: 3px;">In Progress</div>
                </div>
            </div>
            <div class="d-flex align-items-end gap-1 ms-1 flex-shrink-0" style="height: 24px; color: #60a5fa;">
                <span style="width: 3px; height: 35%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 60%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 45%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 95%; background: currentColor; border-radius: 2px;"></span>
            </div>
        </div>
    </div>

    <!-- Completed -->
    <div class="col-6 col-md-3">
        <div class="cat-stat-box d-flex align-items-center justify-content-between" style="background: #f0fdf4; border: 1px solid #dcfce7;">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #22c55e; color: #ffffff;">
                    <i class="fas fa-check-circle" style="font-size: 0.95rem;"></i>
                </div>
                <div class="min-w-0">
                    <div class="fw-bold text-dark lh-1" style="font-size: 1.35rem;" id="stat-completed"><?php echo number_format($orderStats['completed']); ?></div>
                    <div class="text-muted text-truncate" style="font-size: 0.72rem; margin-top: 3px;">Completed</div>
                </div>
            </div>
            <div class="d-flex align-items-end gap-1 ms-1 flex-shrink-0" style="height: 24px; color: #4ade80;">
                <span style="width: 3px; height: 30%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 55%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 40%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 90%; background: currentColor; border-radius: 2px;"></span>
            </div>
        </div>
    </div>

    <!-- Delivered -->
    <div class="col-6 col-md-3">
        <div class="cat-stat-box d-flex align-items-center justify-content-between" style="background: #faf5ff; border: 1px solid #ede9fe;">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #a855f7; color: #ffffff;">
                    <i class="fas fa-truck" style="font-size: 0.95rem;"></i>
                </div>
                <div class="min-w-0">
                    <div class="fw-bold text-dark lh-1" style="font-size: 1.35rem;" id="stat-delivered"><?php echo number_format($orderStats['delivered']); ?></div>
                    <div class="text-muted text-truncate" style="font-size: 0.72rem; margin-top: 3px;">Delivered</div>
                </div>
            </div>
            <div class="d-flex align-items-end gap-1 ms-1 flex-shrink-0" style="height: 24px; color: #c084fc;">
                <span style="width: 3px; height: 45%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 70%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 55%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 100%; background: currentColor; border-radius: 2px;"></span>
            </div>
        </div>
    </div>
</div>

<!-- 3. Search and Primary Action Row -->
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <div class="position-relative flex-grow-1 min-w-0" style="min-width: 220px;">
        <i class="fas fa-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.9rem;"></i>
        <input type="text" id="searchInput" class="form-control" placeholder="Search orders..." autocomplete="off" value="<?php echo htmlspecialchars($search); ?>" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding-left: 2.4rem; padding-right: 0.8rem; height: 42px; font-size: 0.85rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
    </div>
    <?php if (!has_role('tailor')): ?>
    <button type="button" class="btn text-white fw-semibold d-inline-flex align-items-center justify-content-center gap-1 text-nowrap px-4 shadow-sm flex-shrink-0" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 14px; font-size: 0.84rem; height: 42px; border: none; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.28);" data-bs-toggle="modal" data-bs-target="#orderModal">
        <i class="fas fa-plus"></i>
        <span>New Order</span>
    </button>
    <?php endif; ?>
</div>

<!-- Detailed Filters Row -->
<div class="row g-2 align-items-center mb-3">
    <div class="col-6 col-md">
        <select class="form-select" id="statusFilter" title="Filter by Status" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.85rem; height: 42px; padding: 0.45rem 2.2rem 0.45rem 0.8rem; line-height: 1.4; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <option value="">All Statuses</option>
            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
            <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
        </select>
    </div>
    <div class="col-6 col-md">
        <select class="form-select" id="clothTypeFilter" title="Filter by Cloth Type" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.85rem; height: 42px; padding: 0.45rem 2.2rem 0.45rem 0.8rem; line-height: 1.4; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <option value="">All Cloth Types</option>
            <?php foreach ($clothTypes as $ct): ?>
                <option value="<?php echo $ct['id']; ?>"><?php echo htmlspecialchars($ct['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if (!has_role('tailor')): ?>
    <div class="col-6 col-md">
        <select class="form-select" id="tailorFilter" title="Filter by Tailor" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.85rem; height: 42px; padding: 0.45rem 2.2rem 0.45rem 0.8rem; line-height: 1.4; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <option value="">All Tailors</option>
            <?php foreach ($tailors as $t): ?>
                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['full_name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="col-6 col-md">
        <input type="date" class="form-control" id="startDateFilter" title="Start Date" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.85rem; height: 42px; padding: 0.45rem 0.8rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
    </div>
    <div class="col-6 col-md">
        <input type="date" class="form-control" id="endDateFilter" title="End Date" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.85rem; height: 42px; padding: 0.45rem 0.8rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
    </div>
    <div class="col-6 col-md-auto">
        <button type="button" id="clearFilters" class="btn btn-outline-danger align-items-center justify-content-center text-nowrap w-100" style="border-radius: 14px; height: 42px; font-size: 0.82rem; padding: 0 16px; display: inline-flex;" title="Clear Filters">
            <i class="fas fa-times me-1"></i> Clear
        </button>
    </div>
</div>

<div id="filterResults" class="mb-3" style="display: none;">
    <div class="alert alert-info py-2 px-3 d-flex align-items-center" style="border-radius: 12px; font-size: 0.85rem;">
        <i class="fas fa-info-circle me-2"></i>
        <span><strong id="filterCount">0</strong> orders found</span>
    </div>
</div>

<!-- 4. Orders Table -->
<div class="card shadow-sm border-0 mb-3" style="border-radius: 14px; overflow: hidden; border: 1px solid #f1f5f9 !important;">
    <div class="card-body p-0">
        <?php if (!empty($orders)): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle category-table">
                    <thead class="cat-table-head">
                        <tr>
                            <th class="ps-3 py-3">ORDER #</th>
                            <th class="py-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span>CUSTOMER</span>
                                    <button type="button" class="btn btn-link p-0 text-muted border-0 shadow-none" id="sortOrdersBtn" title="Sort A-Z" style="font-size: 0.75rem; line-height: 1; text-decoration: none;">
                                        <i class="fas fa-sort" id="sortIcon"></i>
                                    </button>
                                </div>
                            </th>
                            <th class="py-3">CLOTH TYPE</th>
                            <th class="py-3">TAILOR</th>
                            <th class="py-3">ORDER DATE</th>
                            <th class="py-3">DUE DATE</th>
                            <th class="py-3">STATUS</th>
                            <th class="py-3">AMOUNT</th>
                            <th class="pe-3 py-3 text-end">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTableBody">
                        <?php foreach ($orders as $index => $order): 
                            $custName = display_val($order['measurement_customer_name'] ?? ($order['first_name'] . ' ' . $order['last_name']));
                            $initial = strtoupper(mb_substr(trim($custName), 0, 1) ?: 'C');
                            $avColor = getCustomerAvatarDesign($custName, $index);
                            $isOverdue = $order['due_date'] < date('Y-m-d') && !in_array($order['status'], ['completed', 'delivered', 'cancelled']);
                        ?>
                        <tr class="order-row" data-status="<?php echo htmlspecialchars($order['status']); ?>" data-customer="<?php echo htmlspecialchars($custName); ?>">
                            <td class="ps-3 py-3">
                                <span class="badge fw-semibold" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; font-size: 0.82rem; padding: 5px 9px; border-radius: 8px;">
                                    <?php echo htmlspecialchars($order['order_number']); ?>
                                </span>
                            </td>
                            <td class="py-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="d-flex align-items-center justify-content-center flex-shrink-0 fw-bold" style="width: 34px; height: 34px; border-radius: 10px; background: <?php echo $avColor['bg']; ?>; border: 1px solid <?php echo $avColor['border']; ?>; color: <?php echo $avColor['color']; ?>; font-size: 0.85rem;">
                                        <?php echo $initial; ?>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="fw-bold text-dark text-truncate" style="font-size: 0.88rem; max-width: 170px;">
                                            <?php echo htmlspecialchars($custName); ?>
                                        </div>
                                        <?php if (!empty($order['customer_phone'])): ?>
                                            <div class="text-muted" style="font-size: 0.72rem;"><?php echo htmlspecialchars($order['customer_phone']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3">
                                <span class="badge fw-medium" style="background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; font-size: 0.78rem; border-radius: 6px; padding: 4px 8px;">
                                    <?php echo display_val($order['cloth_type_name']); ?>
                                </span>
                            </td>
                            <td class="py-3">
                                <?php if (!empty($order['tailor_name'])): ?>
                                    <div class="d-flex align-items-center gap-1 text-dark" style="font-size: 0.84rem;">
                                        <i class="fas fa-user-tie text-muted" style="font-size: 0.75rem;"></i>
                                        <span><?php echo display_val($order['tailor_name']); ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 0.8rem; font-style: italic;">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 text-secondary" style="font-size: 0.82rem;"><?php echo format_date($order['order_date']); ?></td>
                            <td class="py-3" style="font-size: 0.82rem;">
                                <span class="<?php echo $isOverdue ? 'text-danger fw-bold' : 'text-dark'; ?>">
                                    <?php if ($isOverdue): ?><i class="fas fa-exclamation-circle text-danger me-1"></i><?php endif; ?>
                                    <?php echo format_date($order['due_date']); ?>
                                </span>
                            </td>
                            <td class="py-3">
                                <span class="badge rounded-pill fw-semibold px-2 py-1 cat-badge-<?php echo htmlspecialchars($order['status']); ?>" style="font-size: 0.75rem;">
                                    <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                </span>
                            </td>
                            <td class="py-3">
                                <div>
                                    <strong class="text-dark" style="font-size: 0.88rem;"><?php echo format_currency($order['total_amount']); ?></strong>
                                    <?php if ($order['advance_amount'] > 0): ?>
                                        <div class="text-muted" style="font-size: 0.72rem;">
                                            Adv: <span class="text-success"><?php echo format_currency($order['advance_amount']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="pe-3 py-3 text-end">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <?php if (!has_role('tailor')): ?>
                                    <button type="button" 
                                            class="cat-action-btn cat-action-edit" 
                                            onclick="editOrder(<?php echo htmlspecialchars(json_encode([
                                                'id' => $order['id'],
                                                'customer_id' => $order['customer_id'],
                                                'customer_name' => $order['measurement_customer_name'] ?? '',
                                                'cloth_type_id' => $order['cloth_type_id'],
                                                'measurement_id' => $order['measurement_id'],
                                                'assigned_tailor_id' => $order['assigned_tailor_id'],
                                                'order_date' => $order['order_date'],
                                                'due_date' => $order['due_date'],
                                                'total_amount' => $order['total_amount'],
                                                'advance_amount' => $order['advance_amount'],
                                                'special_instructions' => $order['special_instructions']
                                            ])); ?>)"
                                            title="Edit Order">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php endif; ?>
                                    <a href="order-details.php?id=<?php echo $order['id']; ?>" 
                                       class="cat-action-btn cat-action-view" 
                                       title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <div class="dropdown d-inline-block" style="position: static;">
                                        <button class="cat-action-btn cat-action-status dropdown-toggle" 
                                                type="button" 
                                                data-bs-toggle="dropdown"
                                                aria-expanded="false"
                                                title="Change Status">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" onclick="event.stopPropagation();" style="border-radius: 12px; border: 1px solid #e2e8f0; font-size: 0.85rem;">
                                            <li><h6 class="dropdown-header text-uppercase text-muted" style="font-size: 0.68rem; letter-spacing: 0.5px;">Update Status</h6></li>
                                            <li><a class="dropdown-item py-1" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(<?php echo $order['id']; ?>, 'pending', this); return false;"><i class="fas fa-clock text-warning me-2"></i>Pending</a></li>
                                            <li><a class="dropdown-item py-1" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(<?php echo $order['id']; ?>, 'in_progress', this); return false;"><i class="fas fa-cogs text-primary me-2"></i>In Progress</a></li>
                                            <li><a class="dropdown-item py-1" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(<?php echo $order['id']; ?>, 'completed', this); return false;"><i class="fas fa-check-circle text-success me-2"></i>Completed</a></li>
                                            <li><a class="dropdown-item py-1" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(<?php echo $order['id']; ?>, 'delivered', this); return false;"><i class="fas fa-truck text-purple me-2" style="color: #8b5cf6;"></i>Delivered</a></li>
                                            <li><hr class="dropdown-divider my-1"></li>
                                            <li><a class="dropdown-item py-1 text-danger" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(<?php echo $order['id']; ?>, 'cancelled', this); return false;"><i class="fas fa-times-circle me-2"></i>Cancelled</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div id="paginationContainer" class="p-3">
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Order pagination">
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px; border-radius: 50%; background: #f8fafc; color: #94a3b8;">
                    <i class="fas fa-clipboard-list fa-2x"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">No orders found</h5>
                <p class="text-muted small mb-3">
                    <?php if (!empty($search) || !empty($status_filter)): ?>
                        No orders match your search criteria.
                    <?php else: ?>
                        Get started by creating your first order.
                    <?php endif; ?>
                </p>
                <?php if (!has_role('tailor') && empty($search) && empty($status_filter)): ?>
                    <button type="button" class="btn text-white fw-semibold px-4 py-2" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 12px; font-size: 0.85rem;" data-bs-toggle="modal" data-bs-target="#orderModal">
                        <i class="fas fa-plus me-1"></i>Create Order
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php if (!has_role('tailor')): ?>
<div class="modal fade" id="orderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="orderForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="orderModalTitle">Create Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" id="orderAction" value="create">
                    <input type="hidden" name="order_id" id="orderId">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="customer_id" class="form-label">Customer *</label>
                            <select class="form-select" id="customer_id" name="customer_id" required>
                                <option value="">Select Customer</option>
                                <?php foreach ($measurementCustomers as $mc): ?>
                                    <option value="<?php echo htmlspecialchars($mc['customer_name']); ?>">
                                        <?php echo htmlspecialchars($mc['customer_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="measurement_id" class="form-label">Measurement</label>
                            <select class="form-select" id="measurement_id" name="measurement_id">
                                <option value="">No Measurement</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Measurement Details Preview Card -->
                    <div id="measurementDetailsPreview" class="mb-3" style="display: none;">
                        <div class="card shadow-sm" style="border-radius: 12px; overflow: hidden; border: 1px solid #e8ecf1;">
                            <div class="card-header d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); border: none; padding: 10px 16px;">
                                <span class="text-white fw-bold" style="font-size: 0.9rem;">
                                    <i class="fas fa-ruler-combined me-2"></i>Measurements <span id="previewClothType" class="ms-1" style="opacity: 0.85;"></span>
                                </span>
                                <span class="badge bg-white text-success" id="previewDateBadge" style="font-size: 0.72rem; display: none;"></span>
                            </div>
                            <div class="card-body py-3 px-3" style="background: #fafcff;">
                                <div id="previewMeasurementValues" class="row g-2 justify-content-center">
                                    <!-- Populated dynamically via JS -->
                                </div>
                                <div id="previewMeasurementNotes" class="mt-2 pt-2 border-top text-muted small" style="display: none;">
                                    <i class="fas fa-sticky-note text-warning me-1"></i><strong>Notes:</strong> <span id="previewNotesText"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="assigned_tailor_id" class="form-label">Assigned Tailor</label>
                            <select class="form-select" id="assigned_tailor_id" name="assigned_tailor_id">
                                <option value="">Select Tailor</option>
                                <?php foreach ($tailors as $tailor): ?>
                                    <option value="<?php echo $tailor['id']; ?>">
                                        <?php echo htmlspecialchars($tailor['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="cloth_type_id" class="form-label">Cloth Type *</label>
                            <select class="form-select" id="cloth_type_id" name="cloth_type_id" required>
                                <option value="">Select Cloth Type</option>
                                <?php foreach ($clothTypes as $clothType): ?>
                                    <option value="<?php echo $clothType['id']; ?>" data-rate="<?php echo $clothType['standard_rate']; ?>">
                                        <?php echo htmlspecialchars($clothType['name']); ?>
                                        <?php if ($clothType['standard_rate']): ?>
                                            - <?php echo format_currency($clothType['standard_rate']); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="order_date" class="form-label">Order Date *</label>
                            <input type="date" class="form-control" id="order_date" name="order_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="due_date" class="form-label">Due Date *</label>
                            <input type="date" class="form-control" id="due_date" name="due_date" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="total_amount" class="form-label">Total Amount *</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" class="form-control" id="total_amount" name="total_amount" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="advance_amount" class="form-label">Advance Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" class="form-control" id="advance_amount" name="advance_amount" step="0.01" min="0" value="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="special_instructions" class="form-label">Special Instructions</label>
                        <textarea class="form-control" id="special_instructions" name="special_instructions" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Order
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="statusForm">
                <div class="modal-header">
                    <h5 class="modal-title">Update Order Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="order_id" id="statusOrderId">
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">New Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>


function editOrder(order) {
    console.log('Editing order:', order); // Debug log
    
    document.getElementById('orderModalTitle').textContent = 'Edit Order';
    document.getElementById('orderAction').value = 'update';
    document.getElementById('orderId').value = order.id || '';
    
    // Populate form fields - convert to string and handle null/undefined
    const customerName = order.customer_name || '';
    const clothTypeId = order.cloth_type_id ? String(order.cloth_type_id) : '';
    const assignedTailorId = order.assigned_tailor_id ? String(order.assigned_tailor_id) : '';
    
    document.getElementById('customer_id').value = customerName;
    document.getElementById('cloth_type_id').value = clothTypeId;
    document.getElementById('assigned_tailor_id').value = assignedTailorId;
    document.getElementById('order_date').value = order.order_date || '';
    document.getElementById('due_date').value = order.due_date || '';
    document.getElementById('total_amount').value = order.total_amount || '';
    document.getElementById('advance_amount').value = order.advance_amount || '';
    document.getElementById('special_instructions').value = order.special_instructions || '';
    
    console.log('Set values:', { customerName, clothTypeId, assignedTailorId }); // Debug log
    
    // Load measurements for the selected customer name
    const measurementSelect = document.getElementById('measurement_id');
    
    if (customerName) {
        // Show loading state
        measurementSelect.innerHTML = '<option value="">Loading measurements...</option>';
        
        // Load measurements via AJAX by name
        fetch(`ajax/get_measurements_by_name.php?name=${encodeURIComponent(customerName)}`)
            .then(response => response.json())
            .then(data => {
                measurementSelect.innerHTML = '<option value="">No Measurement</option>';
                window.currentCustomerMeasurements = data || [];
                
                if (data && data.length > 0) {
                    data.forEach(measurement => {
                        const option = document.createElement('option');
                        option.value = measurement.id;
                        option.setAttribute('data-cloth-type-id', measurement.cloth_type_id);
                        option.textContent = measurement.cloth_type_name + ' - ' + measurement.created_at;
                        measurementSelect.appendChild(option);
                    });
                } else {
                    const noMeasurementsOption = document.createElement('option');
                    noMeasurementsOption.value = '';
                    noMeasurementsOption.textContent = 'No measurements available for this customer';
                    noMeasurementsOption.disabled = true;
                    measurementSelect.appendChild(noMeasurementsOption);
                }
                
                // Set the existing measurement if available
                if (order.measurement_id) {
                    measurementSelect.value = String(order.measurement_id);
                }
                displaySelectedMeasurement();
            })
            .catch(error => {
                console.error('Error loading measurements:', error);
                measurementSelect.innerHTML = '<option value="">Error loading measurements</option>';
                if (order.measurement_id) {
                    measurementSelect.value = String(order.measurement_id);
                }
                displaySelectedMeasurement();
            });
    } else {
        measurementSelect.innerHTML = '<option value="">No Measurement</option>';
        window.currentCustomerMeasurements = [];
        if (order.measurement_id) {
            measurementSelect.value = String(order.measurement_id);
        }
        displaySelectedMeasurement();
    }
    
    // Show modal
    new bootstrap.Modal(document.getElementById('orderModal')).show();
}

function updateOrderStatusAjax(orderId, status, clickedElement) {
    // Prevent default link behavior
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    // Add loading state to the clicked element
    const originalText = clickedElement.innerHTML;
    clickedElement.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
    clickedElement.style.pointerEvents = 'none';
    
    // Find the status badge in the same row (7th column, 6th index)
    const row = clickedElement.closest('tr');
    const statusCell = row.children[6]; // Status column (7th column)
    const statusBadge = statusCell.querySelector('.badge');
    const originalBadgeHtml = statusBadge.innerHTML;
    const originalStatus = row.dataset.status || getCurrentStatusFromBadge(statusBadge);
    
    // Add loading state to status badge
    statusBadge.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Updating';
    statusBadge.className = 'badge rounded-pill fw-semibold px-2 py-1 bg-secondary text-white';
    
    // Prepare form data
    const formData = new FormData();
    formData.append('csrf_token', '<?php echo generate_csrf_token(); ?>');
    formData.append('order_id', orderId);
    formData.append('status', status);
    
    // Make AJAX request
    fetch('ajax/update_order_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update status badge with new status
            const statusClasses = {
                'pending': 'cat-badge-pending',
                'in_progress': 'cat-badge-in_progress',
                'completed': 'cat-badge-completed',
                'delivered': 'cat-badge-delivered',
                'cancelled': 'cat-badge-cancelled'
            };
            
            statusBadge.className = `badge rounded-pill fw-semibold px-2 py-1 ${statusClasses[status] || 'bg-secondary'}`;
            statusBadge.innerHTML = data.status_display;
            row.dataset.status = status;
            
            // Update statistics at the top
            updateOrderStatistics(originalStatus, status);
            
            // Show success message
            showToast('Success', data.message, 'success');
            
            // Close the dropdown
            const dropdownMenu = clickedElement.closest('.dropdown-menu');
            if (dropdownMenu) {
                const dropdown = dropdownMenu.closest('.dropdown');
                if (dropdown) {
                    const dropdownToggle = dropdown.querySelector('.dropdown-toggle');
                    if (dropdownToggle) {
                        const dropdownInstance = bootstrap.Dropdown.getInstance(dropdownToggle);
                        if (dropdownInstance) {
                            dropdownInstance.hide();
                        }
                    }
                }
            }
        } else {
            // Restore original badge
            statusBadge.innerHTML = originalBadgeHtml;
            
            // Show error message
            showToast('Error', data.error || 'Failed to update status', 'error');
        }
    })
    .catch(error => {
        console.error('Error updating status:', error);
        
        // Restore original badge
        statusBadge.innerHTML = originalBadgeHtml;
        
        // Show error message
        showToast('Error', 'Network error occurred', 'error');
    })
    .finally(() => {
        // Restore original element state
        clickedElement.innerHTML = originalText;
        clickedElement.style.pointerEvents = 'auto';
    });
}

function getCurrentStatusFromBadge(badge) {
    const text = badge.textContent.toLowerCase().trim();
    if (text.includes('pending')) return 'pending';
    if (text.includes('progress')) return 'in_progress';
    if (text.includes('completed')) return 'completed';
    if (text.includes('delivered')) return 'delivered';
    if (text.includes('cancelled')) return 'cancelled';
    return null;
}

function updateOrderStatistics(oldStatus, newStatus) {
    // Only update if status actually changed
    if (oldStatus === newStatus) return;
    
    // Get current stat values
    const statElements = {
        'pending': document.getElementById('stat-pending'),
        'in_progress': document.getElementById('stat-in_progress'),
        'completed': document.getElementById('stat-completed'),
        'delivered': document.getElementById('stat-delivered')
    };
    
    // Decrease count for old status (if not cancelled and exists in stats)
    if (oldStatus && statElements[oldStatus]) {
        const oldCount = parseInt(statElements[oldStatus].textContent.replace(/,/g, ''));
        if (oldCount > 0) {
            statElements[oldStatus].textContent = formatNumber(oldCount - 1);
        }
    }
    
    // Increase count for new status (if not cancelled and exists in stats)
    if (newStatus && statElements[newStatus]) {
        const newCount = parseInt(statElements[newStatus].textContent.replace(/,/g, ''));
        statElements[newStatus].textContent = formatNumber(newCount + 1);
    }
}

function formatNumber(num) {
    return num.toLocaleString('en-US');
}

// Toast notification function
function showToast(title, message, type = 'info') {
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
    
    const iconClass = type === 'error' ? 'fas fa-exclamation-circle' : 
                     type === 'success' ? 'fas fa-check-circle' : 
                     'fas fa-info-circle';
    
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

function viewOrder(orderId) {
    window.location.href = 'order-details.php?id=' + orderId;
}

// Auto-fill total amount when cloth type is selected
const clothTypeSelectEl = document.getElementById('cloth_type_id');
if (clothTypeSelectEl) {
    clothTypeSelectEl.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const rate = selectedOption ? selectedOption.getAttribute('data-rate') : null;
        if (rate && rate > 0) {
            const totalAmountEl = document.getElementById('total_amount');
            if (totalAmountEl) totalAmountEl.value = rate;
        }
    });
}

// Current customer measurements cache
window.currentCustomerMeasurements = [];

// Load measurements when customer name is selected
const customerSelectEl = document.getElementById('customer_id');
if (customerSelectEl) {
    customerSelectEl.addEventListener('change', function() {
        const customerName = this.value;
        const measurementSelect = document.getElementById('measurement_id');
        if (!measurementSelect) return;
        
        // Hide measurement preview when customer changes
        hideMeasurementPreview();
        
        if (customerName) {
            // Show loading state
            measurementSelect.innerHTML = '<option value="">Loading measurements...</option>';
            
            // Load measurements via AJAX by name
            fetch(`ajax/get_measurements_by_name.php?name=${encodeURIComponent(customerName)}`)
                .then(response => response.json())
                .then(data => {
                    measurementSelect.innerHTML = '<option value="">No Measurement</option>';
                    window.currentCustomerMeasurements = data || [];
                    
                    if (data && data.length > 0) {
                        data.forEach(measurement => {
                            const option = document.createElement('option');
                            option.value = measurement.id;
                            option.setAttribute('data-cloth-type-id', measurement.cloth_type_id);
                            option.textContent = measurement.cloth_type_name + ' - ' + measurement.created_at;
                            measurementSelect.appendChild(option);
                        });
                    } else {
                        const noMeasurementsOption = document.createElement('option');
                        noMeasurementsOption.value = '';
                        noMeasurementsOption.textContent = 'No measurements available for this customer';
                        noMeasurementsOption.disabled = true;
                        measurementSelect.appendChild(noMeasurementsOption);
                    }
                })
                .catch(error => {
                    console.error('Error loading measurements:', error);
                    measurementSelect.innerHTML = '<option value="">Error loading measurements</option>';
                    window.currentCustomerMeasurements = [];
                });
        } else {
            measurementSelect.innerHTML = '<option value="">No Measurement</option>';
            window.currentCustomerMeasurements = [];
        }
    });
}

// Auto-fill cloth type and show measurements preview when measurement is selected
const measurementSelectEl = document.getElementById('measurement_id');
if (measurementSelectEl) {
    measurementSelectEl.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption) {
            const clothTypeId = selectedOption.getAttribute('data-cloth-type-id');
            if (clothTypeId) {
                const clothTypeSelect = document.getElementById('cloth_type_id');
                if (clothTypeSelect) {
                    clothTypeSelect.value = clothTypeId;
                    clothTypeSelect.dispatchEvent(new Event('change'));
                }
            }
        }
        
        displaySelectedMeasurement();
    });
}

// Display measurement preview based on currently selected measurement ID
function displaySelectedMeasurement() {
    const measurementSelect = document.getElementById('measurement_id');
    const previewContainer = document.getElementById('measurementDetailsPreview');
    if (!measurementSelect || !previewContainer) return;
    
    const measurementId = measurementSelect.value;
    if (!measurementId) {
        hideMeasurementPreview();
        return;
    }
    
    // Check in cached customer measurements first
    let measurement = (window.currentCustomerMeasurements || []).find(m => String(m.id) === String(measurementId));
    
    if (measurement && measurement.measurement_data) {
        renderMeasurementPreview(measurement);
    } else {
        // Fetch via AJAX if not in memory
        fetch(`ajax/get_measurement_details.php?id=${encodeURIComponent(measurementId)}`)
            .then(res => res.json())
            .then(resData => {
                if (resData.success && resData.measurement) {
                    renderMeasurementPreview(resData.measurement);
                } else {
                    hideMeasurementPreview();
                }
            })
            .catch(err => {
                console.error('Error fetching measurement details:', err);
                hideMeasurementPreview();
            });
    }
}

function renderMeasurementPreview(measurement) {
    const previewContainer = document.getElementById('measurementDetailsPreview');
    const valuesContainer = document.getElementById('previewMeasurementValues');
    const clothTypeSpan = document.getElementById('previewClothType');
    const dateBadge = document.getElementById('previewDateBadge');
    const notesContainer = document.getElementById('previewMeasurementNotes');
    const notesText = document.getElementById('previewNotesText');
    
    if (!previewContainer || !valuesContainer) return;
    
    if (clothTypeSpan) {
        clothTypeSpan.textContent = measurement.cloth_type_name ? '(' + measurement.cloth_type_name + ')' : '';
    }
    
    if (dateBadge) {
        if (measurement.created_at) {
            dateBadge.textContent = measurement.created_at;
            dateBadge.style.display = 'inline-block';
        } else {
            dateBadge.style.display = 'none';
        }
    }
    
    valuesContainer.innerHTML = '';
    const mData = measurement.measurement_data;
    let hasValues = false;
    
    // Icon mapping for common measurement keys
    const measurementIcons = {
        'chest': `<svg width="36" height="36" viewBox="0 0 48 48" fill="none"><path d="M24 8c-4 0-8 2-10 5l-4 8v12c0 2 1 3 3 3h22c2 0 3-1 3-3V21l-4-8c-2-3-6-5-10-5z" stroke="#4a7cff" stroke-width="2" fill="none"/><path d="M14 21h20" stroke="#4a7cff" stroke-width="1.5" stroke-dasharray="3 2"/><circle cx="24" cy="16" r="2" fill="#4a7cff"/></svg>`,
        'waist': `<svg width="36" height="36" viewBox="0 0 48 48" fill="none"><path d="M14 12c0 0 2 8 10 8s10-8 10-8" stroke="#4a7cff" stroke-width="2" fill="none"/><path d="M14 12v24h20V12" stroke="#4a7cff" stroke-width="2" fill="none"/><path d="M14 26h20" stroke="#4a7cff" stroke-width="1.5" stroke-dasharray="3 2"/></svg>`,
        'length': `<svg width="36" height="36" viewBox="0 0 48 48" fill="none"><rect x="20" y="6" width="8" height="36" rx="2" stroke="#4a7cff" stroke-width="2" fill="none"/><line x1="22" y1="12" x2="26" y2="12" stroke="#4a7cff" stroke-width="1.5"/><line x1="22" y1="18" x2="26" y2="18" stroke="#4a7cff" stroke-width="1.5"/><line x1="22" y1="24" x2="26" y2="24" stroke="#4a7cff" stroke-width="1.5"/><line x1="22" y1="30" x2="26" y2="30" stroke="#4a7cff" stroke-width="1.5"/><line x1="22" y1="36" x2="26" y2="36" stroke="#4a7cff" stroke-width="1.5"/></svg>`,
        'shoulder': `<svg width="36" height="36" viewBox="0 0 48 48" fill="none"><path d="M10 24c4-6 8-8 14-8s10 2 14 8" stroke="#4a7cff" stroke-width="2" fill="none"/><circle cx="24" cy="12" r="4" stroke="#4a7cff" stroke-width="2" fill="none"/><path d="M10 24h28" stroke="#4a7cff" stroke-width="1.5" stroke-dasharray="3 2"/></svg>`,
        'hip': `<svg width="36" height="36" viewBox="0 0 48 48" fill="none"><path d="M14 14v6c0 6 4 10 10 10s10-4 10-10v-6" stroke="#4a7cff" stroke-width="2" fill="none"/><path d="M14 22h20" stroke="#4a7cff" stroke-width="1.5" stroke-dasharray="3 2"/></svg>`,
        'sleeve': `<svg width="36" height="36" viewBox="0 0 48 48" fill="none"><path d="M18 10l-6 26h8l4-18 4 18h8l-6-26" stroke="#4a7cff" stroke-width="2" fill="none"/><path d="M16 24h16" stroke="#4a7cff" stroke-width="1.5" stroke-dasharray="3 2"/></svg>`,
        'neck': `<svg width="36" height="36" viewBox="0 0 48 48" fill="none"><circle cx="24" cy="18" r="8" stroke="#4a7cff" stroke-width="2" fill="none"/><path d="M16 18c0-4 4-8 8-8s8 4 8 8" stroke="#4a7cff" stroke-width="2" fill="none"/><path d="M20 30h8v6h-8z" stroke="#4a7cff" stroke-width="2" fill="none"/></svg>`,
        'inseam': `<svg width="36" height="36" viewBox="0 0 48 48" fill="none"><path d="M18 8v14l-4 18h8l2-14 2 14h8l-4-18V8" stroke="#4a7cff" stroke-width="2" fill="none"/><path d="M18 22h12" stroke="#4a7cff" stroke-width="1.5" stroke-dasharray="3 2"/></svg>`,
        'arm': `<svg width="36" height="36" viewBox="0 0 48 48" fill="none"><path d="M20 10c0 0-2 12-2 20s2 8 6 8 6-2 6-8-2-20-2-20" stroke="#4a7cff" stroke-width="2" fill="none"/><path d="M18 24h12" stroke="#4a7cff" stroke-width="1.5" stroke-dasharray="3 2"/></svg>`,
        'width': `<svg width="36" height="36" viewBox="0 0 48 48" fill="none"><path d="M8 24h32M8 20v8M40 20v8" stroke="#4a7cff" stroke-width="2"/><path d="M12 24l4-3v6l-4-3zM36 24l-4-3v6l4-3z" fill="#4a7cff"/></svg>`,
    };
    
    // Default icon for unknown keys
    const defaultIcon = `<svg width="36" height="36" viewBox="0 0 48 48" fill="none"><rect x="10" y="6" width="28" height="36" rx="3" stroke="#4a7cff" stroke-width="2" fill="none"/><line x1="16" y1="14" x2="32" y2="14" stroke="#4a7cff" stroke-width="1.5"/><line x1="16" y1="20" x2="28" y2="20" stroke="#4a7cff" stroke-width="1.5"/><line x1="16" y1="26" x2="30" y2="26" stroke="#4a7cff" stroke-width="1.5"/><line x1="16" y1="32" x2="24" y2="32" stroke="#4a7cff" stroke-width="1.5"/></svg>`;
    
    if (mData && typeof mData === 'object') {
        const entries = Object.entries(mData).filter(([k, v]) => v !== null && v !== undefined && String(v).trim() !== '');
        const colClass = entries.length <= 3 ? 'col-4' : (entries.length === 4 ? 'col-3' : 'col-4 col-sm-3');
        
        entries.forEach(([key, val]) => {
            hasValues = true;
            const keyLower = key.toLowerCase().replace(/[_\s-]/g, '');
            const icon = measurementIcons[keyLower] || defaultIcon;
            const label = key.replace(/_/g, ' ').toUpperCase();
            
            const col = document.createElement('div');
            col.className = colClass;
            col.innerHTML = `
                <div class="text-center py-2">
                    <div class="mb-1" style="opacity: 0.8;">${icon}</div>
                    <div class="text-muted text-uppercase fw-bold" style="font-size: 0.65rem; letter-spacing: 1px; margin-bottom: 2px;">${escapeHtml(label)}</div>
                    <div class="fw-bold text-primary" style="font-size: 1.4rem; line-height: 1.2;">${escapeHtml(String(val))}</div>
                    <div class="text-muted" style="font-size: 0.7rem;">inch</div>
                </div>
            `;
            valuesContainer.appendChild(col);
        });
    }
    
    if (!hasValues) {
        valuesContainer.innerHTML = `
            <div class="col-12 text-center text-muted py-3">
                <i class="fas fa-info-circle me-1"></i>No measurement values recorded.
            </div>
        `;
    }
    
    if (measurement.notes && String(measurement.notes).trim() !== '') {
        if (notesText) notesText.textContent = measurement.notes;
        if (notesContainer) notesContainer.style.display = 'block';
    } else {
        if (notesContainer) notesContainer.style.display = 'none';
    }
    
    previewContainer.style.display = 'block';
}

function hideMeasurementPreview() {
    const previewContainer = document.getElementById('measurementDetailsPreview');
    if (previewContainer) {
        previewContainer.style.display = 'none';
    }
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Reset modal when closed
const orderModalEl = document.getElementById('orderModal');
if (orderModalEl) {
    orderModalEl.addEventListener('hidden.bs.modal', function() {
        const titleEl = document.getElementById('orderModalTitle');
        if (titleEl) titleEl.textContent = 'Create Order';
        const actionEl = document.getElementById('orderAction');
        if (actionEl) actionEl.value = 'create';
        const idEl = document.getElementById('orderId');
        if (idEl) idEl.value = '';
        const formEl = document.getElementById('orderForm');
        if (formEl) formEl.reset();
        const dateEl = document.getElementById('order_date');
        if (dateEl) dateEl.value = '<?php echo date('Y-m-d'); ?>';
        hideMeasurementPreview();
        window.currentCustomerMeasurements = [];
    });
}

// AJAX Order Filtering
let filterTimeout;
const searchInput = document.getElementById('searchInput');
const statusFilter = document.getElementById('statusFilter');
const clothTypeFilter = document.getElementById('clothTypeFilter');
const tailorFilter = document.getElementById('tailorFilter');
const startDateFilter = document.getElementById('startDateFilter');
const endDateFilter = document.getElementById('endDateFilter');
const clearFilters = document.getElementById('clearFilters');
const filterResults = document.getElementById('filterResults');
const filterCount = document.getElementById('filterCount');
const ordersTable = document.getElementById('ordersTableBody') || document.querySelector('.table tbody');

// Store original table content (only if table exists)
const originalTableContent = ordersTable ? ordersTable.innerHTML : '';

// Load filter options on page load
loadFilterOptions();

function loadFilterOptions() {
    fetch('ajax/filter_orders.php?page=1&limit=1')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(text => {
            if (text.trim().startsWith('<')) {
                throw new Error('Server returned HTML instead of JSON');
            }
            
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    populateFilterOptions(data.filter_options);
                } else {
                    console.error('Filter options error:', data.error);
                    // Continue without filter options
                }
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response text:', text);
                // Continue without filter options
            }
        })
        .catch(error => {
            console.error('Error loading filter options:', error);
            // Continue without filter options - the page will still work
        });
}

function populateFilterOptions(options) {

    // Populate cloth types
    const clothTypeSelect = document.getElementById('clothTypeFilter');
    clothTypeSelect.innerHTML = '<option value="">All Cloth Types</option>';
    options.cloth_types.forEach(clothType => {
        clothTypeSelect.innerHTML += `<option value="${clothType.id}">${clothType.name}</option>`;
    });
    
    // Populate tailors
    const tailorSelect = document.getElementById('tailorFilter');
    if (tailorSelect && options.tailors) {
        tailorSelect.innerHTML = '<option value="">All Tailors</option>';
        options.tailors.forEach(tailor => {
            tailorSelect.innerHTML += `<option value="${tailor.id}">${tailor.name}</option>`;
        });
    }
}

// Add event listeners for all filters
[searchInput, statusFilter, clothTypeFilter, tailorFilter, startDateFilter, endDateFilter].forEach(element => {
    if (!element) return;
    element.addEventListener('change', performFilter);
    if (element === searchInput) {
        element.addEventListener('input', performFilter);
    }
});

if (clearFilters) {
    clearFilters.addEventListener('click', function() {
        // Reload the page to reset all filters and show all orders
        window.location.href = 'orders.php';
    });
}

function performFilter() {
    // Clear previous timeout
    clearTimeout(filterTimeout);
    
    // Debounce search input
    if (this === searchInput) {
        filterTimeout = setTimeout(() => {
            executeFilter();
        }, 300);
    } else {
        executeFilter();
    }
}

function executeFilter() {
    const search = searchInput.value.trim();
    const status = statusFilter.value;
    const clothType = clothTypeFilter.value;
    const tailor = tailorFilter ? tailorFilter.value : '';
    const startDate = startDateFilter ? startDateFilter.value : '';
    const endDate = endDateFilter ? endDateFilter.value : '';
    
    // Show loading state
    filterResults.style.display = 'block';
    filterCount.textContent = 'Filtering...';
    
    // Build query string
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (status) params.append('status', status);
    if (clothType) params.append('cloth_type_id', clothType);
    if (tailor) params.append('assigned_tailor_id', tailor);
    if (startDate) params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);
    params.append('page', '1');
    params.append('limit', '<?php echo RECORDS_PER_PAGE; ?>');
    
    fetch(`ajax/filter_orders.php?${params.toString()}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text(); // Get as text first to check for HTML
        })
        .then(text => {
            // Check if response is HTML (error page)
            if (text.trim().startsWith('<')) {
                throw new Error('Server returned HTML instead of JSON. Check for PHP errors.');
            }
            
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    displayFilterResults(data.orders);
                    filterCount.textContent = data.pagination.total_orders;
                    
                    const paginationContainer = document.getElementById('paginationContainer');
                    if (paginationContainer) {
                        if (data.pagination.total_pages <= 1) {
                            paginationContainer.style.display = 'none';
                        } else {
                            paginationContainer.style.display = 'block';
                        }
                    }
                } else {
                    console.error('Filter error:', data.error);
                    filterCount.textContent = 'Filter failed: ' + (data.error || 'Unknown error');
                }
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response text:', text);
                filterCount.textContent = 'Invalid response from server';
            }
        })
        .catch(error => {
            console.error('Filter error:', error);
            filterCount.textContent = 'Filter failed: ' + error.message;
        });
}

function displayFilterResults(orders) {
    if (!ordersTable) {
        console.error('Orders table not found');
        return;
    }
    
    if (orders.length === 0) {
        ordersTable.innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-5">
                    <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; border-radius: 50%; background: #f8fafc; color: #94a3b8;">
                        <i class="fas fa-search fa-2x"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">No orders found</h5>
                    <p class="text-muted small">Try adjusting your filter criteria</p>
                </td>
            </tr>
        `;
        return;
    }
    
    const isTailor = <?php echo has_role('tailor') ? 'true' : 'false'; ?>;
    const palettes = [
        { bg: '#eff6ff', border: '#dbeafe', color: '#3b82f6' },
        { bg: '#f0fdf4', border: '#dcfce7', color: '#10b981' },
        { bg: '#faf5ff', border: '#ede9fe', color: '#8b5cf6' },
        { bg: '#fffbeb', border: '#fef3c7', color: '#f59e0b' },
        { bg: '#fdf2f8', border: '#fce7f3', color: '#ec4899' },
        { bg: '#f0fdfa', border: '#ccfbf1', color: '#0d9488' }
    ];
    
    const statusClasses = {
        'pending': 'cat-badge-pending',
        'in_progress': 'cat-badge-in_progress',
        'completed': 'cat-badge-completed',
        'delivered': 'cat-badge-delivered',
        'cancelled': 'cat-badge-cancelled'
    };
    
    const formatCurrency = (amount) => '₹' + parseFloat(amount).toLocaleString('en-IN', { minimumFractionDigits: 2 });
    
    const formatDate = (dateString) => {
        if (!dateString) return '-';
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return dateString;
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };
    
    let tableHTML = '';
    orders.forEach((order, index) => {
        const orderDate = formatDate(order.order_date);
        const dueDate = formatDate(order.due_date);
        const isOverdue = new Date(order.due_date) < new Date() && !['completed', 'delivered', 'cancelled'].includes(order.status);
        const customerName = order.display_customer_name || 'No Name';
        const initial = customerName.charAt(0).toUpperCase() || 'C';
        const pal = palettes[index % palettes.length];
        const statusBadgeClass = statusClasses[order.status] || 'bg-secondary';
        const statusDisplay = order.status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());

        tableHTML += `
            <tr class="order-row" data-status="${escapeHtml(order.status)}" data-customer="${escapeHtml(customerName)}">
                <td class="ps-3 py-3">
                    <span class="badge fw-semibold" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; font-size: 0.82rem; padding: 5px 9px; border-radius: 8px;">
                        ${escapeHtml(order.order_number)}
                    </span>
                </td>
                <td class="py-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="d-flex align-items-center justify-content-center flex-shrink-0 fw-bold" style="width: 34px; height: 34px; border-radius: 10px; background: ${pal.bg}; border: 1px solid ${pal.border}; color: ${pal.color}; font-size: 0.85rem;">
                            ${escapeHtml(initial)}
                        </div>
                        <div class="min-w-0">
                            <div class="fw-bold text-dark text-truncate" style="font-size: 0.88rem; max-width: 170px;">
                                ${escapeHtml(customerName)}
                            </div>
                            ${order.customer_phone ? `<div class="text-muted" style="font-size: 0.72rem;">${escapeHtml(order.customer_phone)}</div>` : ''}
                        </div>
                    </div>
                </td>
                <td class="py-3">
                    <span class="badge fw-medium" style="background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; font-size: 0.78rem; border-radius: 6px; padding: 4px 8px;">
                        ${escapeHtml(order.cloth_type_name || '-')}
                    </span>
                </td>
                <td class="py-3">
                    ${order.tailor_name ? `
                        <div class="d-flex align-items-center gap-1 text-dark" style="font-size: 0.84rem;">
                            <i class="fas fa-user-tie text-muted" style="font-size: 0.75rem;"></i>
                            <span>${escapeHtml(order.tailor_name)}</span>
                        </div>
                    ` : '<span class="text-muted" style="font-size: 0.8rem; font-style: italic;">Unassigned</span>'}
                </td>
                <td class="py-3 text-secondary" style="font-size: 0.82rem;">${orderDate}</td>
                <td class="py-3" style="font-size: 0.82rem;">
                    <span class="${isOverdue ? 'text-danger fw-bold' : 'text-dark'}">
                        ${isOverdue ? '<i class="fas fa-exclamation-circle text-danger me-1"></i>' : ''}${dueDate}
                    </span>
                </td>
                <td class="py-3">
                    <span class="badge rounded-pill fw-semibold px-2 py-1 ${statusBadgeClass}" style="font-size: 0.75rem;">
                        ${escapeHtml(statusDisplay)}
                    </span>
                </td>
                <td class="py-3">
                    <div>
                        <strong class="text-dark" style="font-size: 0.88rem;">${formatCurrency(order.total_amount)}</strong>
                        ${order.advance_amount > 0 ? `
                            <div class="text-muted" style="font-size: 0.72rem;">
                                Adv: <span class="text-success">${formatCurrency(order.advance_amount)}</span>
                            </div>
                        ` : ''}
                    </div>
                </td>
                <td class="pe-3 py-3 text-end">
                    <div class="d-inline-flex align-items-center gap-1">
                        ${!isTailor ? `
                        <button type="button" 
                                class="cat-action-btn cat-action-edit edit-order-btn" 
                                data-order='${JSON.stringify({
                                    id: order.id,
                                    customer_id: order.customer_id,
                                    customer_name: order.customer_name || '',
                                    cloth_type_id: order.cloth_type_id,
                                    measurement_id: order.measurement_id,
                                    assigned_tailor_id: order.assigned_tailor_id,
                                    order_date: order.order_date,
                                    due_date: order.due_date,
                                    total_amount: order.total_amount,
                                    advance_amount: order.advance_amount,
                                    special_instructions: order.special_instructions || ''
                                }).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;')}'
                                title="Edit Order">
                            <i class="fas fa-edit"></i>
                        </button>
                        ` : ''}
                        <a href="order-details.php?id=${order.id}" 
                           class="cat-action-btn cat-action-view" 
                           title="View Details">
                            <i class="fas fa-eye"></i>
                        </a>
                        <div class="dropdown d-inline-block" style="position: static;">
                            <button class="cat-action-btn cat-action-status dropdown-toggle" 
                                    type="button" 
                                    data-bs-toggle="dropdown"
                                    aria-expanded="false"
                                    title="Change Status">
                                <i class="fas fa-cog"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" onclick="event.stopPropagation();" style="border-radius: 12px; border: 1px solid #e2e8f0; font-size: 0.85rem;">
                                <li><h6 class="dropdown-header text-uppercase text-muted" style="font-size: 0.68rem; letter-spacing: 0.5px;">Update Status</h6></li>
                                <li><a class="dropdown-item py-1" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(${order.id}, 'pending', this); return false;"><i class="fas fa-clock text-warning me-2"></i>Pending</a></li>
                                <li><a class="dropdown-item py-1" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(${order.id}, 'in_progress', this); return false;"><i class="fas fa-cogs text-primary me-2"></i>In Progress</a></li>
                                <li><a class="dropdown-item py-1" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(${order.id}, 'completed', this); return false;"><i class="fas fa-check-circle text-success me-2"></i>Completed</a></li>
                                <li><a class="dropdown-item py-1" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(${order.id}, 'delivered', this); return false;"><i class="fas fa-truck text-purple me-2" style="color: #8b5cf6;"></i>Delivered</a></li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li><a class="dropdown-item py-1 text-danger" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(${order.id}, 'cancelled', this); return false;"><i class="fas fa-times-circle me-2"></i>Cancelled</a></li>
                            </ul>
                        </div>
                    </div>
                </td>
            </tr>
        `;
    });
    
    ordersTable.innerHTML = tableHTML;
    
    // Attach event listeners to edit buttons
    ordersTable.querySelectorAll('.edit-order-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            try {
                // Decode HTML entities back to JSON string, then parse
                const orderJson = this.getAttribute('data-order')
                    .replace(/&quot;/g, '"')
                    .replace(/&#39;/g, "'")
                    .replace(/&lt;/g, '<')
                    .replace(/&gt;/g, '>')
                    .replace(/&amp;/g, '&');
                const order = JSON.parse(orderJson);
                editOrder(order);
            } catch (e) {
                console.error('Error parsing order data:', e);
                alert('Error loading order data');
            }
        });
    });
}

// Interactive Sort (A-Z / Z-A)
let sortAscending = true;
document.getElementById('sortOrdersBtn')?.addEventListener('click', function(e) {
    e.preventDefault();
    sortAscending = !sortAscending;
    const sortIcon = document.getElementById('sortIcon');
    if (sortIcon) {
        sortIcon.className = sortAscending ? 'fas fa-sort-alpha-down' : 'fas fa-sort-alpha-up';
    }
    
    const tableBody = document.getElementById('ordersTableBody') || document.querySelector('.table tbody');
    if (tableBody) {
        const rows = Array.from(tableBody.querySelectorAll('tr.order-row'));
        rows.sort((a, b) => {
            const nameA = a.getAttribute('data-customer') || '';
            const nameB = b.getAttribute('data-customer') || '';
            return sortAscending ? nameA.localeCompare(nameB) : nameB.localeCompare(nameA);
        });
        rows.forEach(r => tableBody.appendChild(r));
    }
});

// Fix dropdown positioning in table to prevent clipping
document.addEventListener('shown.bs.dropdown', function(e) {
    const toggle = e.target;
    const dropdown = toggle.closest('.dropdown');
    
    if (!dropdown || !dropdown.closest('.table td')) return;
    
    const menu = dropdown.querySelector('.dropdown-menu');
    if (!menu) return;
    
    // Wait for Bootstrap to position it first
    setTimeout(function() {
        const toggleRect = toggle.getBoundingClientRect();
        const menuRect = menu.getBoundingClientRect();
        const viewportHeight = window.innerHeight;
        const viewportWidth = window.innerWidth;
        
        // Use fixed positioning to escape table container
        menu.style.position = 'fixed';
        menu.style.zIndex = '9999';
        menu.style.pointerEvents = 'auto';
        menu.style.minWidth = '150px';
        menu.style.visibility = 'visible';
        menu.style.opacity = '1';
        
        // Calculate position
        let top = toggleRect.bottom;
        let left = toggleRect.left;
        
        // Check if dropdown would go off bottom of viewport
        if (top + menuRect.height > viewportHeight - 10) {
            top = toggleRect.top - menuRect.height;
            if (top < 10) {
                top = viewportHeight - menuRect.height - 10;
            }
        }
        
        // Check horizontal boundaries
        if (left + menuRect.width > viewportWidth - 10) {
            left = viewportWidth - menuRect.width - 10;
        }
        if (left < 10) {
            left = 10;
        }
        
        menu.style.top = top + 'px';
        menu.style.left = left + 'px';
        menu.style.transform = 'none';
        menu.style.inset = 'auto';
    }, 10);
});
</script>

<?php require_once 'includes/footer.php'; ?>

<style>
    .btn-outline-info:hover {
        color: #ffffff;
    }
    
    /* Fix dropdown clipping in table */
    .table-responsive {
        overflow-x: auto !important;
        overflow-y: visible !important;
        position: relative;
    }
    
    .table td .dropdown {
        position: static;
    }
    
    .table td .dropdown-toggle {
        position: relative;
        z-index: 1;
    }
    
    .table td .dropdown-menu {
        z-index: 9999 !important;
        min-width: 150px;
        pointer-events: auto !important;
        position: fixed !important;
        right: 100px !important;
    }
    
    .table td .dropdown-menu.show {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
    }
    
    /* Ensure dropdown can overflow table boundaries */
    .card-body {
        overflow: visible !important;
        position: relative;
    }
    
    .card {
        overflow: visible !important;
        position: relative;
    }
    
    .table {
        margin-bottom: 0;
        position: relative;
    }
    
    /* Prevent any parent from clipping */
    .content-area {
        overflow: visible !important;
    }
    
    /* Ensure table cells don't block dropdown */
    .table td {
        position: static;
    }

    @media (max-width: 768px) {
        .table td .dropdown-menu {
            right: 0 !important;
        }
        .card-header.d-flex {
        display: flex !important;
        align-items: flex-start !important;
        flex-direction: column;
        gap: 15px;
    }
    .card-header .btn-light {
        width: 100% !important;
    }
    }
</style>