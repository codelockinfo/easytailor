<!-- Favicon - Primary ICO format for Google Search -->
<link rel="icon" type="image/x-icon" href="../favicon.ico" sizes="16x16 32x32 48x48">
<!-- Favicon - PNG fallback -->
<link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon(2).png">
<link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon(2).png">
<!-- Apple Touch Icon -->
<link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon(2).png">

<?php
/**
 * Orders Page
 * Tailoring Management System
 */

// Include config first (before any output)
require_once '../config/config.php';

// Check if user is logged in
require_login();

require_once 'models/Order.php';
require_once '../models/Customer.php';
require_once 'models/ClothType.php';
require_once 'models/User.php';
require_once '../helpers/SubscriptionHelper.php';

$orderModel = new Order();
$customerModel = new Customer();
$clothTypeModel = new ClothType();
$userModel = new User();

$message = '';
$messageType = '';
$companyId = get_company_id();

// Handle form submissions BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                // Check subscription limit for orders
                $limitCheck = SubscriptionHelper::canAddOrder($companyId);
                if (!$limitCheck['allowed']) {
                    $_SESSION['message'] = $limitCheck['message'] . ' ' . SubscriptionHelper::getUpgradeMessage('orders', SubscriptionHelper::getCurrentPlan($companyId));
                    $_SESSION['messageType'] = 'error';
                    header('Location: orders.php');
                    exit;
                }
                
                // Validate user ID exists
                $userId = get_user_id();
                if (!$userId) {
                    $_SESSION['message'] = 'Error: User session invalid. Please logout and login again.';
                    $_SESSION['messageType'] = 'error';
                    header('Location: orders.php');
                    exit;
                }
                
                // Verify user exists in database
                $user = $userModel->find($userId);
                if (!$user) {
                    $_SESSION['message'] = 'Error: User account not found. Please contact administrator.';
                    $_SESSION['messageType'] = 'error';
                    header('Location: orders.php');
                    exit;
                }
                
                $data = [
                    'customer_id' => (int)$_POST['customer_id'],
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
                
                // Add company_id if not present
                if ($companyId && !isset($data['company_id'])) {
                    $data['company_id'] = $companyId;
                }
                
                try {
                    $orderId = $orderModel->createOrder($data);
                    if ($orderId) {
                        // Get order details for tracking
                        $order = $orderModel->find($orderId);
                        // Track create order event
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
                $orderId = (int)$_POST['order_id'];
                $data = [
                    'customer_id' => (int)$_POST['customer_id'],
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

// Get messages from session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['messageType']);
}

// NOW include header (after all redirects are done)
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

// Get all orders for the company first to get accurate count
$allOrders = $orderModel->getOrdersWithDetails($conditions, null, 0);
$totalOrders = count($allOrders);
// Then slice for pagination
$orders = array_slice($allOrders, $offset, $limit);
$totalPages = ceil($totalOrders / $limit);

// Get data for dropdowns
$customers = $customerModel->findAll(['status' => 'active'], 'first_name, last_name');
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
?>


<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($orderLimitCheck['remaining']) && $orderLimitCheck['remaining'] <= 10 && $orderLimitCheck['remaining'] > 0): ?>
    <div class="alert alert-warning alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Order Limit Warning:</strong> You have <?php echo $orderLimitCheck['remaining']; ?> order slot(s) remaining in your <?php echo ucfirst($currentPlan); ?> plan. 
        <a href="subscriptions.php" class="alert-link">Upgrade your plan</a> to add more orders.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php elseif (!$orderLimitCheck['allowed']): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-ban me-2"></i>
        <strong>Order Limit Reached:</strong> <?php echo $orderLimitCheck['message']; ?> 
        <a href="subscriptions.php" class="alert-link">Upgrade your plan</a> to add more orders.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Order Statistics -->
<div class="row">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number" id="stat-pending"><?php echo number_format($orderStats['pending']); ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number" id="stat-in_progress"><?php echo number_format($orderStats['in_progress']); ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-cogs"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number" id="stat-completed"><?php echo number_format($orderStats['completed']); ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #007bff 0%, #6f42c1 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number" id="stat-delivered"><?php echo number_format($orderStats['delivered']); ?></div>
                    <div class="stat-label">Delivered</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-truck"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" 
                           id="searchInput"
                           class="form-control" 
                           placeholder="Search orders..."
                           autocomplete="off">
                </div>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="statusFilter">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="customerFilter">
                    <option value="">All Customers</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="clothTypeFilter">
                    <option value="">All Cloth Types</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="tailorFilter">
                    <option value="">All Tailors</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="button" id="clearFilters" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-times"></i> Clear
                </button>
            </div>
        </div>
        <div id="filterResults" class="mt-3" style="display: none;">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <span id="filterCount">0</span> orders found
            </div>
        </div>
    </div>
</div>

<!-- Orders Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-clipboard-list me-2"></i>
            Orders (<?php echo number_format($totalOrders); ?>)
        </h5>
        <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#orderModal">
            <i class="fas fa-plus me-1"></i>New Order
        </button>
    </div>
    <div class="card-body">
        <?php if (!empty($orders)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Cloth Type</th>
                            <th>Tailor</th>
                            <th>Order Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr data-status="<?php echo htmlspecialchars($order['status']); ?>">
                            <td>
                                <span class="badge bg-primary"><?php echo htmlspecialchars($order['order_number']); ?></span>
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
                                <?php if (!empty($order['tailor_name'])): ?>
                                    <?php echo htmlspecialchars($order['tailor_name']); ?>
                                <?php else: ?>
                                    <span class="text-muted">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo format_date($order['order_date']); ?></td>
                            <td>
                                <span class="<?php echo $order['due_date'] < date('Y-m-d') && !in_array($order['status'], ['completed', 'delivered']) ? 'text-danger fw-bold' : ''; ?>">
                                    <?php echo format_date($order['due_date']); ?>
                                </span>
                            </td>
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
                            <td>
                                <div>
                                    <strong><?php echo format_currency($order['total_amount']); ?></strong>
                                    <?php if ($order['advance_amount'] > 0): ?>
                                        <br>
                                        <small class="text-muted">
                                            Advance: <?php echo format_currency($order['advance_amount']); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" 
                                            class="btn btn-outline-primary" 
                                            onclick="editOrder(<?php echo htmlspecialchars(json_encode([
                                                'id' => $order['id'],
                                                'customer_id' => $order['customer_id'],
                                                'cloth_type_id' => $order['cloth_type_id'],
                                                'measurement_id' => $order['measurement_id'],
                                                'assigned_tailor_id' => $order['assigned_tailor_id'],
                                                'order_date' => $order['order_date'],
                                                'due_date' => $order['due_date'],
                                                'total_amount' => $order['total_amount'],
                                                'advance_amount' => $order['advance_amount'],
                                                'special_instructions' => $order['special_instructions']
                                            ])); ?>)"
                                            title="Edit" style="border: 1px solid #667eea;">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="order-details.php?id=<?php echo $order['id']; ?>" 
                                       class="btn btn-outline-info" 
                                       title="View Details" style="border: 1px solid #667eea; padding-top: 17px;">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <div class="dropdown" style="position: static;">
                                        <button class="btn btn-outline-secondary dropdown-toggle" 
                                                type="button" 
                                                style="border-top-left-radius: 0; border-bottom-left-radius: 0; padding-bottom: 13px; border-left: none !important; border: 1px solid #667eea;"
                                                data-bs-toggle="dropdown"
                                                title="Change Status">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <ul class="dropdown-menu" onclick="event.stopPropagation();">
                                            <li><a class="dropdown-item" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(<?php echo $order['id']; ?>, 'pending', this); return false;">Pending</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(<?php echo $order['id']; ?>, 'in_progress', this); return false;">In Progress</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(<?php echo $order['id']; ?>, 'completed', this); return false;">Completed</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(<?php echo $order['id']; ?>, 'delivered', this); return false;">Delivered</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(<?php echo $order['id']; ?>, 'cancelled', this); return false;">Cancelled</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Order pagination">
                    <ul class="pagination justify-content-center">
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
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No orders found</h5>
                <p class="text-muted">
                    <?php if (!empty($search) || !empty($status_filter)): ?>
                        No orders match your search criteria.
                    <?php else: ?>
                        Get started by creating your first order.
                    <?php endif; ?>
                </p>
                <?php if (empty($search) && empty($status_filter)): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#orderModal">
                        <i class="fas fa-plus me-2"></i>Create Order
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Order Modal -->
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
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['id']; ?>">
                                        <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name'] . ' (' . $customer['customer_code'] . ')'); ?>
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
                            <label for="measurement_id" class="form-label">Measurement</label>
                            <select class="form-select" id="measurement_id" name="measurement_id">
                                <option value="">No Measurement</option>
                                <!-- Measurements will be loaded via AJAX -->
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

<!-- Status Update Modal -->
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
    const customerId = order.customer_id ? String(order.customer_id) : '';
    const clothTypeId = order.cloth_type_id ? String(order.cloth_type_id) : '';
    const assignedTailorId = order.assigned_tailor_id ? String(order.assigned_tailor_id) : '';
    
    document.getElementById('customer_id').value = customerId;
    document.getElementById('cloth_type_id').value = clothTypeId;
    document.getElementById('assigned_tailor_id').value = assignedTailorId;
    document.getElementById('order_date').value = order.order_date || '';
    document.getElementById('due_date').value = order.due_date || '';
    document.getElementById('total_amount').value = order.total_amount || '';
    document.getElementById('advance_amount').value = order.advance_amount || '';
    document.getElementById('special_instructions').value = order.special_instructions || '';
    
    console.log('Set values:', { customerId, clothTypeId, assignedTailorId }); // Debug log
    
    // Load measurements for the selected customer
    const measurementSelect = document.getElementById('measurement_id');
    
    if (customerId) {
        // Show loading state
        measurementSelect.innerHTML = '<option value="">Loading measurements...</option>';
        
        // Load measurements via AJAX
        fetch(`ajax/get_customer_measurements.php?customer_id=${customerId}`)
            .then(response => response.json())
            .then(data => {
                measurementSelect.innerHTML = '<option value="">No Measurement</option>';
                
                if (data && data.length > 0) {
                    data.forEach(measurement => {
                        const option = document.createElement('option');
                        option.value = measurement.id;
                        option.textContent = measurement.cloth_type_name + ' - ' + measurement.created_at;
                        measurementSelect.appendChild(option);
                    });
                } else {
                    // Add option to show no measurements available
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
            })
            .catch(error => {
                console.error('Error loading measurements:', error);
                measurementSelect.innerHTML = '<option value="">Error loading measurements</option>';
                if (order.measurement_id) {
                    measurementSelect.value = String(order.measurement_id);
                }
            });
    } else {
        measurementSelect.innerHTML = '<option value="">No Measurement</option>';
        if (order.measurement_id) {
            measurementSelect.value = String(order.measurement_id);
        }
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
    statusBadge.className = 'badge bg-secondary';
    
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
            const statusColors = {
                'pending': 'bg-warning',
                'in_progress': 'bg-info',
                'completed': 'bg-success',
                'delivered': 'bg-primary',
                'cancelled': 'bg-danger'
            };
            
            statusBadge.className = `badge ${statusColors[status] || 'bg-secondary'}`;
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
document.getElementById('cloth_type_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const rate = selectedOption.getAttribute('data-rate');
    if (rate && rate > 0) {
        document.getElementById('total_amount').value = rate;
    }
});

// Load measurements when customer is selected
document.getElementById('customer_id').addEventListener('change', function() {
    const customerId = this.value;
    const measurementSelect = document.getElementById('measurement_id');
    
    if (customerId) {
        // Show loading state
        measurementSelect.innerHTML = '<option value="">Loading measurements...</option>';
        
        // Load measurements via AJAX
        fetch(`ajax/get_customer_measurements.php?customer_id=${customerId}`)
            .then(response => response.json())
            .then(data => {
                measurementSelect.innerHTML = '<option value="">No Measurement</option>';
                
                if (data && data.length > 0) {
                    data.forEach(measurement => {
                        const option = document.createElement('option');
                        option.value = measurement.id;
                        option.textContent = measurement.cloth_type_name + ' - ' + measurement.created_at;
                        measurementSelect.appendChild(option);
                    });
                } else {
                    // Add option to show no measurements available
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
            });
    } else {
        measurementSelect.innerHTML = '<option value="">No Measurement</option>';
    }
});

// Reset modal when closed
document.getElementById('orderModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('orderModalTitle').textContent = 'Create Order';
    document.getElementById('orderAction').value = 'create';
    document.getElementById('orderId').value = '';
    document.getElementById('orderForm').reset();
    document.getElementById('order_date').value = '<?php echo date('Y-m-d'); ?>';
});

// AJAX Order Filtering
let filterTimeout;
const searchInput = document.getElementById('searchInput');
const statusFilter = document.getElementById('statusFilter');
const customerFilter = document.getElementById('customerFilter');
const clothTypeFilter = document.getElementById('clothTypeFilter');
const tailorFilter = document.getElementById('tailorFilter');
const clearFilters = document.getElementById('clearFilters');
const filterResults = document.getElementById('filterResults');
const filterCount = document.getElementById('filterCount');
const ordersTable = document.querySelector('.table tbody');

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
    // Populate customers
    const customerSelect = document.getElementById('customerFilter');
    customerSelect.innerHTML = '<option value="">All Customers</option>';
    options.customers.forEach(customer => {
        customerSelect.innerHTML += `<option value="${customer.id}">${customer.name}</option>`;
    });
    
    // Populate cloth types
    const clothTypeSelect = document.getElementById('clothTypeFilter');
    clothTypeSelect.innerHTML = '<option value="">All Cloth Types</option>';
    options.cloth_types.forEach(clothType => {
        clothTypeSelect.innerHTML += `<option value="${clothType.id}">${clothType.name}</option>`;
    });
    
    // Populate tailors
    const tailorSelect = document.getElementById('tailorFilter');
    tailorSelect.innerHTML = '<option value="">All Tailors</option>';
    options.tailors.forEach(tailor => {
        tailorSelect.innerHTML += `<option value="${tailor.id}">${tailor.name}</option>`;
    });
}

// Add event listeners for all filters
[searchInput, statusFilter, customerFilter, clothTypeFilter, tailorFilter].forEach(element => {
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
    const customer = customerFilter.value;
    const clothType = clothTypeFilter.value;
    const tailor = tailorFilter.value;
    
    // Show loading state
    filterResults.style.display = 'block';
    filterCount.textContent = 'Filtering...';
    
    // Build query string
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (status) params.append('status', status);
    if (customer) params.append('customer_id', customer);
    if (clothType) params.append('cloth_type_id', clothType);
    if (tailor) params.append('assigned_tailor_id', tailor);
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
                <td colspan="9" class="text-center py-4">
                    <i class="fas fa-search fa-2x text-muted mb-2"></i>
                    <h5 class="text-muted">No orders found</h5>
                    <p class="text-muted">Try adjusting your filter criteria</p>
                </td>
            </tr>
        `;
        return;
    }
    
    let tableHTML = '';
    orders.forEach(order => {
        // Format dates to match PHP format_date() output (Y-m-d format: 2025-11-16)
        const formatDate = (dateString) => {
            const date = new Date(dateString);
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };
        
        const orderDate = formatDate(order.order_date);
        const dueDate = formatDate(order.due_date);
        
        // Check if overdue
        const isOverdue = new Date(order.due_date) < new Date() && !['completed', 'delivered', 'cancelled'].includes(order.status);
        
        // Status badge colors
        const statusColors = {
            'pending': 'warning',
            'in_progress': 'info', 
            'completed': 'success',
            'delivered': 'primary',
            'cancelled': 'danger'
        };
        const statusColor = statusColors[order.status] || 'secondary';
        
        // Format currency
        const formatCurrency = (amount) => '₹' + parseFloat(amount).toLocaleString('en-IN', { minimumFractionDigits: 2 });
        
        tableHTML += `
            <tr data-status="${order.status}">
                <td>
                    <span class="badge bg-primary">${order.order_number}</span>
                </td>
                <td>
                    <div>
                        <strong>${order.first_name} ${order.last_name}</strong>
                        <br>
                        <small class="text-muted">${order.customer_code}</small>
                    </div>
                </td>
                <td>${order.cloth_type_name}</td>
                <td>
                    ${order.tailor_name ? order.tailor_name : '<span class="text-muted">Unassigned</span>'}
                </td>
                <td>${orderDate}</td>
                <td>
                    <span class="${isOverdue ? 'text-danger fw-bold' : ''}">${dueDate}</span>
                </td>
                <td>
                    <span class="badge bg-${statusColor}">${order.status.replace('_', ' ').charAt(0).toUpperCase() + order.status.replace('_', ' ').slice(1)}</span>
                </td>
                <td>
                    <div>
                        <strong>${formatCurrency(order.total_amount)}</strong>
                        ${order.advance_amount > 0 ? `
                            <br>
                            <small class="text-muted">
                                Advance: ${formatCurrency(order.advance_amount)}
                            </small>
                        ` : ''}
                    </div>
                </td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" 
                                class="btn btn-outline-primary edit-order-btn" 
                                data-order='${JSON.stringify({
                                    id: order.id,
                                    customer_id: order.customer_id,
                                    cloth_type_id: order.cloth_type_id,
                                    measurement_id: order.measurement_id,
                                    assigned_tailor_id: order.assigned_tailor_id,
                                    order_date: order.order_date,
                                    due_date: order.due_date,
                                    total_amount: order.total_amount,
                                    advance_amount: order.advance_amount,
                                    special_instructions: order.special_instructions || ''
                                }).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;')}'
                                title="Edit" style="border: 1px solid #667eea;">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="order-details.php?id=${order.id}" 
                           class="btn btn-outline-info" 
                           title="View Details" style="border: 1px solid #667eea; padding-top: 17px;">
                            <i class="fas fa-eye"></i>
                        </a>
                        <div class="dropdown" style="position: static;">
                            <button class="btn btn-outline-secondary dropdown-toggle" 
                                    type="button" 
                                    style="border-top-left-radius: 0; border-bottom-left-radius: 0; padding-bottom: 13px; border-left: none !important; border: 1px solid #667eea;"
                                    data-bs-toggle="dropdown"
                                    title="Change Status">
                                <i class="fas fa-cog"></i>
                            </button>
                            <ul class="dropdown-menu" onclick="event.stopPropagation();">
                                <li><a class="dropdown-item" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(${order.id}, 'pending', this); return false;">Pending</a></li>
                                <li><a class="dropdown-item" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(${order.id}, 'in_progress', this); return false;">In Progress</a></li>
                                <li><a class="dropdown-item" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(${order.id}, 'completed', this); return false;">Completed</a></li>
                                <li><a class="dropdown-item" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(${order.id}, 'delivered', this); return false;">Delivered</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(${order.id}, 'cancelled', this); return false;">Cancelled</a></li>
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
