<?php
/**
 * Orders Page
 * Tailoring Management System
 */

// Include config first (before any output)
require_once 'config/config.php';

// Check if user is logged in
require_login();

require_once 'models/Order.php';
require_once 'models/Customer.php';
require_once 'models/ClothType.php';
require_once 'models/User.php';

$orderModel = new Order();
$customerModel = new Customer();
$clothTypeModel = new ClothType();
$userModel = new User();

$message = '';
$messageType = '';

// Handle form submissions BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
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
                
                try {
                    $orderId = $orderModel->createOrder($data);
                    if ($orderId) {
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

$orders = $orderModel->getOrdersWithDetails($conditions, $limit, $offset);
$totalOrders = $orderModel->count($conditions);
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

// Get order statistics
$orderStats = $orderModel->getOrderStats();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="h3 mb-0">Order Management</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#orderModal">
                <i class="fas fa-plus me-2"></i>New Order
            </button>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Order Statistics -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($orderStats['pending']); ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($orderStats['in_progress']); ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-cogs"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($orderStats['completed']); ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #007bff 0%, #6f42c1 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($orderStats['delivered']); ?></div>
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
                    <i class="fas fa-times"></i>
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
                        <tr>
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
                                            onclick="editOrder(<?php echo htmlspecialchars(json_encode($order)); ?>)"
                                            title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="order-details.php?id=<?php echo $order['id']; ?>" 
                                       class="btn btn-outline-info" 
                                       title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary dropdown-toggle" 
                                                type="button" 
                                                data-bs-toggle="dropdown"
                                                title="Change Status">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $order['id']; ?>, 'pending')">Pending</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $order['id']; ?>, 'in_progress')">In Progress</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $order['id']; ?>, 'completed')">Completed</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="updateStatus(<?php echo $order['id']; ?>, 'delivered')">Delivered</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="updateStatus(<?php echo $order['id']; ?>, 'cancelled')">Cancelled</a></li>
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
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="total_amount" name="total_amount" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="advance_amount" class="form-label">Advance Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
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
    document.getElementById('orderModalTitle').textContent = 'Edit Order';
    document.getElementById('orderAction').value = 'update';
    document.getElementById('orderId').value = order.id;
    
    // Populate form fields
    document.getElementById('customer_id').value = order.customer_id || '';
    document.getElementById('cloth_type_id').value = order.cloth_type_id || '';
    document.getElementById('assigned_tailor_id').value = order.assigned_tailor_id || '';
    document.getElementById('order_date').value = order.order_date || '';
    document.getElementById('due_date').value = order.due_date || '';
    document.getElementById('total_amount').value = order.total_amount || '';
    document.getElementById('advance_amount').value = order.advance_amount || '';
    document.getElementById('special_instructions').value = order.special_instructions || '';
    
    // Show modal
    new bootstrap.Modal(document.getElementById('orderModal')).show();
}

function updateStatus(orderId, status) {
    document.getElementById('statusOrderId').value = orderId;
    document.getElementById('status').value = status;
    new bootstrap.Modal(document.getElementById('statusModal')).show();
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
        // Load measurements via AJAX
        fetch(`ajax/get_customer_measurements.php?customer_id=${customerId}`)
            .then(response => response.json())
            .then(data => {
                measurementSelect.innerHTML = '<option value="">No Measurement</option>';
                data.forEach(measurement => {
                    const option = document.createElement('option');
                    option.value = measurement.id;
                    option.textContent = measurement.cloth_type_name + ' - ' + measurement.created_at;
                    measurementSelect.appendChild(option);
                });
            })
            .catch(error => console.error('Error loading measurements:', error));
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

// Store original table content
const originalTableContent = ordersTable.innerHTML;

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

clearFilters.addEventListener('click', function() {
    searchInput.value = '';
    statusFilter.value = '';
    customerFilter.value = '';
    clothTypeFilter.value = '';
    tailorFilter.value = '';
    ordersTable.innerHTML = originalTableContent;
    filterResults.style.display = 'none';
});

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
        // Format dates
        const orderDate = new Date(order.order_date).toLocaleDateString('en-US', { 
            year: 'numeric', month: 'short', day: 'numeric' 
        });
        const dueDate = new Date(order.due_date).toLocaleDateString('en-US', { 
            year: 'numeric', month: 'short', day: 'numeric' 
        });
        
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
            <tr>
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
                                class="btn btn-outline-primary" 
                                onclick="editOrder(${JSON.stringify(order).replace(/"/g, '&quot;')})"
                                title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="order-details.php?id=${order.id}" 
                           class="btn btn-outline-info" 
                           title="View Details">
                            <i class="fas fa-eye"></i>
                        </a>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle" 
                                    type="button" 
                                    data-bs-toggle="dropdown"
                                    title="Change Status">
                                <i class="fas fa-cog"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="updateOrderStatus(${order.id}, 'pending')">Pending</a></li>
                                <li><a class="dropdown-item" href="#" onclick="updateOrderStatus(${order.id}, 'in_progress')">In Progress</a></li>
                                <li><a class="dropdown-item" href="#" onclick="updateOrderStatus(${order.id}, 'completed')">Completed</a></li>
                                <li><a class="dropdown-item" href="#" onclick="updateOrderStatus(${order.id}, 'delivered')">Delivered</a></li>
                                <li><a class="dropdown-item" href="#" onclick="updateOrderStatus(${order.id}, 'cancelled')">Cancelled</a></li>
                            </ul>
                        </div>
                    </div>
                </td>
            </tr>
        `;
    });
    
    ordersTable.innerHTML = tableHTML;
}
</script>

<?php require_once 'includes/footer.php'; ?>


