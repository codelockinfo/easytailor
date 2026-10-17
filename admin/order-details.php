<?php
require_once '../config/config.php';
require_login();
require_once 'models/Order.php';
require_once '../models/Customer.php';
require_once 'models/Invoice.php';
require_once 'models/Measurement.php';
require_once 'models/ClothType.php';
require_once 'models/User.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST request received. Action: " . ($_POST['action'] ?? 'none') . ", Order ID: " . ($_POST['order_id'] ?? 'none') . ", Status: " . ($_POST['status'] ?? 'none'));
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        error_log("CSRF token verified. Action: $action");
        if ($action === 'update_status') {
            error_log("Processing update_status. Order ID: " . ($_POST['order_id'] ?? 'none') . ", Status: " . ($_POST['status'] ?? 'none'));
            $orderId = (int)$_POST['order_id'];
            $status = sanitize_input($_POST['status'] ?? '');
            $validStatuses = ['pending', 'in_progress', 'completed', 'delivered', 'cancelled'];
            if (!in_array($status, $validStatuses)) {
                $_SESSION['message'] = 'Invalid status';
                $_SESSION['messageType'] = 'error';
                header('Location: order-details.php?id=' . $orderId);
                exit;
            }
            $orderModel = new Order();
            $companyId = get_company_id();
            $order = $orderModel->find($orderId);
            if (!$order) {
                $_SESSION['message'] = 'Order not found. Order ID: ' . $orderId;
                $_SESSION['messageType'] = 'error';
                error_log("Order not found for update. Order ID: $orderId, Company ID: " . ($companyId ?? 'null'));
                header('Location: order-details.php?id=' . $orderId);
                exit;
            }
              if ($companyId && isset($order['company_id']) && $order['company_id'] != $companyId) {
                $_SESSION['message'] = 'Unauthorized access to this order';
                $_SESSION['messageType'] = 'error';
                error_log("Unauthorized access attempt. Order ID: $orderId, Order Company ID: " . ($order['company_id'] ?? 'null') . ", User Company ID: $companyId");
                header('Location: orders.php');
                exit;
            }
                        if (has_role('tailor') && (!isset($order['assigned_tailor_id']) || $order['assigned_tailor_id'] != get_user_id())) {
                $_SESSION['message'] = 'You can only update orders assigned to you';
                $_SESSION['messageType'] = 'error';
                header('Location: orders.php');
                exit;
            }
            error_log("Order found and authorized. Order ID: $orderId, Current Status: " . ($order['status'] ?? 'null') . ", New Status: $status");
            try {
                error_log("About to call updateOrderStatus. Order ID: $orderId, Status: $status");
                $updateResult = $orderModel->updateOrderStatus($orderId, $status);
                error_log("updateOrderStatus returned: " . ($updateResult ? 'true' : 'false'));
                if ($updateResult) {
                    usleep(100000); 
                   $updatedOrder = $orderModel->find($orderId);
                    if ($updatedOrder && $updatedOrder['status'] === $status) {
                        $_SESSION['message'] = 'Order status updated successfully';
                        $_SESSION['messageType'] = 'success';
                        error_log("Order status updated successfully. Order ID: $orderId, Status: $status");
                    } else {
                        $_SESSION['message'] = 'Status update may not have been saved correctly';
                        $_SESSION['messageType'] = 'warning';
                        error_log("Warning: Order status update returned true but verification failed. Order ID: $orderId, Expected Status: $status, Actual Status: " . ($updatedOrder['status'] ?? 'null'));
                    }
                } else {
                    $_SESSION['message'] = 'Failed to update order status. Please try again.';
                    $_SESSION['messageType'] = 'error';
                    error_log("Failed to update order status. Order ID: $orderId, Status: $status. Update method returned false.");
                }
            } catch (Exception $e) {
                $_SESSION['message'] = 'Error updating order status: ' . $e->getMessage();
                $_SESSION['messageType'] = 'error';
                error_log("Exception updating order status: " . $e->getMessage() . " | Stack trace: " . $e->getTraceAsString());
            } catch (PDOException $e) {
                $_SESSION['message'] = 'Database error: ' . $e->getMessage();
                $_SESSION['messageType'] = 'error';
                error_log("PDO Exception updating order status: " . $e->getMessage() . " | Code: " . $e->getCode());
            }
            while (ob_get_level()) {
                ob_end_clean();
            }
            $redirectUrl = 'order-details.php?id=' . $orderId . '&_t=' . time();
            error_log("Redirecting to: $redirectUrl");
            header('Location: ' . $redirectUrl);
            exit;
        } elseif ($action === 'update_notes') {
            $orderId = (int)$_POST['order_id'];
            $notes = sanitize_input($_POST['notes'] ?? '');
            $orderModel = new Order();
            $orderModel->update($orderId, ['special_instructions' => $notes]);
            $_SESSION['message'] = 'Notes updated successfully';
            $_SESSION['messageType'] = 'success';
            header('Location: order-details.php?id=' . $orderId);
            exit;
        }
    } else {
        $_SESSION['message'] = 'Invalid request';
        $_SESSION['messageType'] = 'error';
        $orderId = (int)($_GET['id'] ?? $_POST['order_id'] ?? 0);
        if ($orderId) {
            header('Location: order-details.php?id=' . $orderId);
        } else {
            header('Location: orders.php');
        }
        exit;
    }
}
$orderId = (int)($_GET['id'] ?? 0);
if (!$orderId) {
    header('Location: orders.php');
    exit;
}
$orderModel = new Order();
$customerModel = new Customer();
$invoiceModel = new Invoice();
$measurementModel = new Measurement();
$clothTypeModel = new ClothType();
$userModel = new User();
$orders = $orderModel->getOrdersWithDetails(['o.id' => $orderId], 1);
if (empty($orders)) {
    $order = $orderModel->find($orderId);
    if (!$order) {
        $_SESSION['message'] = 'Order not found';
        $_SESSION['messageType'] = 'error';
        header('Location: orders.php');
        exit;
    }
} else {
    $order = $orders[0];
}
if (has_role('tailor') && (!isset($order['assigned_tailor_id']) || $order['assigned_tailor_id'] != get_user_id())) {
    $_SESSION['message'] = 'Unauthorized access to this order';
    $_SESSION['messageType'] = 'error';
    header('Location: orders.php');
    exit;
}

if ($orderId > 0 && is_logged_in()) {
    require_once __DIR__ . '/../helpers/NotificationService.php';
    $notifService = new NotificationService();
    $notifService->markOrderNotificationsAsRead($orderId, get_user_id());
}
$customer = !empty($order['customer_id']) ? $customerModel->find($order['customer_id']) : false;
$measurement = null;
if (!empty($order['measurement_id'])) {
    $measurements = $measurementModel->getMeasurementsWithDetails(['m.id' => $order['measurement_id']], 1);
    if (!empty($measurements)) {
        $measurement = $measurements[0];
    } else {
        $measurement = $measurementModel->find($order['measurement_id']);
    }
    if ($measurement && !empty($measurement['measurement_data']) && is_string($measurement['measurement_data'])) {
        $measurement['measurement_data'] = json_decode($measurement['measurement_data'], true);
    }
}
$clothType = !empty($order['cloth_type_id']) ? $clothTypeModel->find($order['cloth_type_id']) : false;

$tailor = null;
if ($order['assigned_tailor_id']) {
    $tailor = $userModel->find($order['assigned_tailor_id']);
}
$invoices = $invoiceModel->findAll(['order_id' => $orderId]);
$displayName = 'Walk-in Customer';
$displayPhone = '';
$displayEmail = '';
$customerInitial = 'W';
if ($customer) {
    $displayName = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
    $displayPhone = $customer['phone'] ?? '';
    $displayEmail = $customer['email'] ?? '';
} elseif (!empty($order['measurement_customer_name'])) {
    $displayName = $order['measurement_customer_name'];
} elseif (!empty($order['customer_name'])) {
    $displayName = $order['customer_name'];
}
if (empty($displayPhone) && !empty($measurement['phone_number'])) {
    $displayPhone = $measurement['phone_number'];
}
if (empty($displayEmail) && !empty($measurement['email'])) {
    $displayEmail = $measurement['email'];
}
if (!empty($displayName)) {
    $customerInitial = strtoupper(substr($displayName, 0, 1));
}
$dueDate = strtotime($order['due_date']);
$today = strtotime(date('Y-m-d'));
$daysDiff = (int) round(($dueDate - $today) / 86400);
$isOverdue = $dueDate < $today && !in_array($order['status'], ['completed', 'delivered', 'cancelled']);
if ($isOverdue) {
    $dueText = abs($daysDiff) . ' days overdue';
} elseif ($daysDiff == 0) {
    $dueText = 'Due today';
} else {
    $dueText = $daysDiff . ' days left';
}
$statusColors = [
    'pending' => 'background-color: #f59e0b;',
    'in_progress' => 'background-color: #06b6d4;',
    'completed' => 'background-color: #10b981;',
    'delivered' => 'background-color: #3b82f6;',
    'cancelled' => 'background-color: #ef4444;'
];
$statusBadgeBg = $statusColors[$order['status']] ?? 'background-color: #6b7280;';
$message = '';
$messageType = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['messageType']);
}
$page_title = 'Order Details';
require_once 'includes/header.php';
?>
<div class="container-fluid px-0">
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show rounded-3 mb-3">
            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <div class="mb-2">
        <span class="text-muted small">
            <a href="orders.php" class="text-decoration-none text-muted"><i class="fas fa-home me-1"></i>Orders</a>
            <i class="fas fa-chevron-right mx-2" style="font-size: 0.65rem;"></i>
            <span>Order Details</span>
        </span>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <a href="orders.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 bg-white text-dark shadow-xs" style="border-color: #d1d5db; font-size: 0.85rem; font-weight: 500;">
                <i class="fas fa-arrow-left me-1"></i> Back to Orders
            </a>
            <h2 class="h4 mb-0 fw-bold text-dark">Order Details</h2>
        </div>
        <div class="d-none d-md-flex gap-2">
            <button type="button" class="btn btn-outline-primary rounded-pill px-3 py-1 bg-white" onclick="printOrder()" style="border-color: #667eea; color: #4338ca; font-size: 0.88rem; font-weight: 500;">
                <i class="fas fa-print me-2"></i>Print
            </button>
            <?php if (!has_role('tailor')): ?>
            <a href="invoices.php" class="btn btn-success rounded-pill px-3 py-1 text-white shadow-xs" style="background-color: #107548; border-color: #107548; font-size: 0.88rem; font-weight: 500;">
                <i class="fas fa-file-invoice me-2"></i>Generate Invoice
            </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white d-md-none">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <div class="text-muted" style="font-size: 0.75rem;">Order Number</div>
                    <div class="fw-bold fs-5 text-dark"><?php echo htmlspecialchars($order['order_number']); ?></div>
                </div>
                <div>
                    <span class="badge rounded-pill text-white px-3 py-2" style="<?php echo $statusBadgeBg; ?> font-size: 0.8rem; font-weight: 500;">
                        <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                    </span>
                </div>
            </div>
            <div class="row g-2 mb-3 pb-3 border-bottom">
                <div class="col-6">
                    <div class="d-flex align-items-start gap-2">
                        <div class="d-flex align-items-center justify-content-center rounded-3 flex-shrink-0 mt-1" style="width: 32px; height: 32px; background: #eff6ff; color: #3b82f6;">
                            <i class="fas fa-calendar-alt" style="font-size: 0.85rem;"></i>
                        </div>
                        <div>
                            <div class="text-muted" style="font-size: 0.72rem;">Order Date</div>
                            <div class="fw-bold text-dark" style="font-size: 0.82rem;"><?php echo format_date($order['order_date'], 'M j, Y'); ?></div>
                            <div class="text-muted" style="font-size: 0.68rem;"><?php echo date('h:i A', strtotime($order['created_at'])); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="d-flex align-items-start gap-2">
                        <div class="d-flex align-items-center justify-content-center rounded-3 flex-shrink-0 mt-1" style="width: 32px; height: 32px; background: #eff6ff; color: #3b82f6;">
                            <i class="fas fa-calendar-check" style="font-size: 0.85rem;"></i>
                        </div>
                        <div>
                            <div class="text-muted" style="font-size: 0.72rem;">Due Date</div>
                            <div class="fw-bold text-dark" style="font-size: 0.82rem;"><?php echo format_date($order['due_date'], 'M j, Y'); ?></div>
                            <div class="text-muted" style="font-size: 0.68rem;"><?php echo $dueText; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row text-center">
                <div class="col-4 border-end">
                    <div class="text-muted" style="font-size: 0.72rem;">Total Amount</div>
                    <div class="fw-bold text-dark" style="font-size: 0.95rem;"><?php echo format_currency($order['total_amount']); ?></div>
                </div>
                <div class="col-4 border-end">
                    <div class="text-muted" style="font-size: 0.72rem;">Paid Amount</div>
                    <div class="fw-bold text-success" style="font-size: 0.95rem;"><?php echo format_currency($order['advance_amount']); ?></div>
                </div>
                <div class="col-4">
                    <div class="text-muted" style="font-size: 0.72rem;">Balance</div>
                    <div class="fw-bold text-danger" style="font-size: 0.95rem;"><?php echo format_currency($order['balance_amount']); ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white d-none d-md-block">
        <div class="card-body p-3">
            <div class="row g-3 align-items-center">
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="width: 44px; height: 44px; background: #eff6ff; color: #3b82f6;">
                            <i class="fas fa-file-alt fs-5"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-medium" style="font-size: 0.78rem;">Order Number</div>
                            <div class="fw-bold fs-6 text-primary"><?php echo htmlspecialchars($order['order_number']); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="width: 44px; height: 44px; background: #fffbeb; color: #f59e0b;">
                            <i class="fas fa-hourglass-half fs-5"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-medium" style="font-size: 0.78rem;">Status</div>
                            <div>
                                <span class="badge rounded-pill text-white" style="<?php echo $statusBadgeBg; ?> padding: 5px 14px; font-weight: 500; font-size: 0.78rem;">
                                    <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="width: 44px; height: 44px; background: #f5f3ff; color: #8b5cf6;">
                            <i class="fas fa-shopping-bag fs-5"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-medium" style="font-size: 0.78rem;">Total Amount</div>
                            <div class="fw-bold fs-6 text-dark"><?php echo format_currency($order['total_amount']); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="width: 44px; height: 44px; background: #ecfdf5; color: #10b981;">
                            <i class="fas fa-check-circle fs-5"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-medium" style="font-size: 0.78rem;">Paid Amount</div>
                            <div class="fw-bold fs-6 text-success"><?php echo format_currency($order['advance_amount']); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="width: 44px; height: 44px; background: #fef2f2; color: #ef4444;">
                            <i class="fas fa-exclamation-circle fs-5"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-medium" style="font-size: 0.78rem;">Balance Amount</div>
                            <div class="fw-bold fs-6 text-danger"><?php echo format_currency($order['balance_amount']); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="width: 44px; height: 44px; background: #eff6ff; color: #3b82f6;">
                            <i class="fas fa-calendar-alt fs-5"></i>
                        </div>
                        <div>
                            <div class="text-muted small fw-medium" style="font-size: 0.78rem;">Due Date</div>
                            <div class="fw-bold fs-6 text-dark"><?php echo format_date($order['due_date'], 'M j, Y'); ?></div>
                            <div class="text-muted" style="font-size: 0.72rem;"><?php echo $dueText; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden bg-white">
                <div class="card-header d-flex justify-content-between align-items-center px-4 py-3" style="background: #eef2ff; border: none;">
                    <span class="fw-bold" style="color: #2563eb; font-size: 0.95rem;">
                        <i class="fas fa-user me-2"></i>Customer Information
                    </span>
                    <?php if (!has_role('tailor') && $customer): ?>
                    <a href="customer-details.php?id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-0 bg-white" style="font-size: 0.75rem; border-color: #cbd5e1; color: #2563eb;">
                        <i class="fas fa-edit me-1"></i>Edit
                    </a>
                    <?php endif; ?>
                </div>
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="d-flex align-items-center gap-3 w-100">
                        <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle" style="width: 58px; height: 58px; background: #dbeafe; color: #1d4ed8; font-size: 1.5rem; font-weight: 700;">
                            <?php echo $customerInitial; ?>
                        </div>
                        <div>
                            <div class="text-muted small" style="font-size: 0.75rem;">Name</div>
                            <div class="fw-bold fs-5 text-dark mb-1"><?php echo htmlspecialchars($displayName); ?></div>
                            <div class="text-dark small d-flex align-items-center gap-2 mb-1">
                                <i class="fas fa-phone text-muted" style="font-size: 0.85rem;"></i>
                                <span><?php echo !empty($displayPhone) ? htmlspecialchars($displayPhone) : '-'; ?></span>
                            </div>
                            <div class="text-dark small d-flex align-items-center gap-2">
                                <i class="fas fa-envelope text-muted" style="font-size: 0.85rem;"></i>
                                <span><?php echo !empty($displayEmail) ? htmlspecialchars($displayEmail) : '-'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden bg-white">
                <div class="card-header d-flex justify-content-between align-items-center px-4 py-3" style="background: #f5f0ff; border: none;">
                    <span class="fw-bold" style="color: #6d28d9; font-size: 0.95rem;">
                        <i class="fas fa-file-invoice me-2"></i>Order Information
                    </span>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-0 bg-white" style="font-size: 0.75rem; border-color: #cbd5e1; color: #6d28d9;" data-bs-toggle="modal" data-bs-target="#statusModal">
                        <i class="fas fa-edit me-1"></i>Edit
                    </button>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small">Cloth Type</span>
                            <span class="fw-bold text-dark">
                                <?php echo htmlspecialchars($order['cloth_type_name'] ?? 'N/A'); ?>
                                <?php if ($clothType && !empty($clothType['category'])): ?>
                                    <span class="badge rounded-pill bg-primary ms-1" style="font-size: 0.72rem; font-weight: 500; background-color: #2563eb !important;"><?php echo htmlspecialchars($clothType['category']); ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small">Standard Rate</span>
                            <span class="fw-bold text-dark"><?php echo $clothType && $clothType['standard_rate'] ? format_currency($clothType['standard_rate']) : format_currency($order['total_amount']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small">Total Amount</span>
                            <span class="fw-bold text-dark"><?php echo format_currency($order['total_amount']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small">Status</span>
                            <span class="badge rounded-pill text-white" style="<?php echo $statusBadgeBg; ?> padding: 4px 12px; font-weight: 500;">
                                <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                            </span>
                        </div>
                        <?php if ($tailor): ?>
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <span class="text-muted small">Assigned Tailor</span>
                            <span class="fw-semibold text-primary"><i class="fas fa-cut me-1"></i><?php echo htmlspecialchars($tailor['full_name']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden bg-white">
                <div class="card-header d-flex justify-content-between align-items-center px-4 py-3" style="background: #f0fdf4; border: none;">
                    <span class="fw-bold" style="color: #15803d; font-size: 0.95rem;">
                        <i class="fas fa-ruler-combined me-2"></i>Measurements
                    </span>
                    <?php if (!has_role('tailor') && !empty($order['measurement_id'])): ?>
                    <a href="measurements.php?edit=<?php echo $order['measurement_id']; ?>" class="btn btn-sm btn-outline-success rounded-pill px-3 py-0 bg-white" style="font-size: 0.75rem; border-color: #cbd5e1; color: #15803d;">
                        <i class="fas fa-edit me-1"></i>Edit
                    </a>
                    <?php endif; ?>
                </div>
                <div class="card-body p-3" style="max-height: 350px; overflow-y: auto;">
                    <?php if ($measurement && !empty($measurement['measurement_data']) && is_array($measurement['measurement_data'])): ?>
                        <div class="row g-2 justify-content-center text-center">
                            <?php 
                            foreach ($measurement['measurement_data'] as $mKey => $mVal): 
                                if ($mVal === null || $mVal === '' || !is_scalar($mVal)) continue;
                                $keyLower = strtolower(str_replace([' ', '_', '-'], '', $mKey));
                                $hasUnit = preg_match('/\d/', (string)$mVal);
                            ?>
                                <div class="col-4 mb-2">
                                    <div class="p-1 h-100 d-flex flex-column align-items-center justify-content-center">
                                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-1" style="width: 42px; height: 42px; background: #eff6ff; flex-shrink: 0;">
                                            <?php if (str_contains($keyLower, 'collar') || str_contains($keyLower, 'neck')): ?>
                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4c-3 0-5 2-6 5l6 2 6-2c-1-3-3-5-6-5z"></path><path d="M6 9l6 11 6-11"></path></svg>
                                            <?php elseif (str_contains($keyLower, 'shoulder')): ?>
                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 18l3-8h10l3 8"></path><circle cx="12" cy="7" r="3"></circle><line x1="4" y1="18" x2="20" y2="18" stroke-dasharray="2 2"></line></svg>
                                            <?php elseif (str_contains($keyLower, 'chest') || str_contains($keyLower, 'bust')): ?>
                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle><line x1="8" y1="14" x2="16" y2="14" stroke-dasharray="2 2"></line></svg>
                                            <?php elseif (str_contains($keyLower, 'waist')): ?>
                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 4c0 0 2 5 5 5s5-5 5-5"></path><path d="M7 4v16h10V4"></path><line x1="7" y1="12" x2="17" y2="12" stroke-dasharray="2 2"></line></svg>
                                            <?php elseif (str_contains($keyLower, 'sleeve') || str_contains($keyLower, 'arm') || str_contains($keyLower, 'hand')): ?>
                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4l-4 14h6l2-10 2 10h6l-4-14"></path><line x1="6" y1="12" x2="18" y2="12" stroke-dasharray="2 2"></line></svg>
                                            <?php elseif (str_contains($keyLower, 'length') || str_contains($keyLower, 'height')): ?>
                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="20" rx="2"></rect><line x1="8" y1="6" x2="12" y2="6"></line><line x1="8" y1="10" x2="12" y2="10"></line><line x1="8" y1="14" x2="12" y2="14"></line><line x1="8" y1="18" x2="12" y2="18"></line></svg>
                                            <?php elseif (str_contains($keyLower, 'pocket')): ?>
                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 5v8a5 5 0 0 0 5 5h2a5 5 0 0 0 5-5V5H6z"></path><line x1="6" y1="9" x2="18" y2="9"></line></svg>
                                            <?php elseif (str_contains($keyLower, 'hip')): ?>
                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 7v6c0 4 3 7 6 7s6-3 6-7V7"></path><line x1="6" y1="13" x2="18" y2="13" stroke-dasharray="2 2"></line></svg>
                                            <?php else: ?>
                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"></rect><line x1="8" y1="4" x2="8" y2="8"></line><line x1="12" y1="4" x2="12" y2="10"></line><line x1="16" y1="4" x2="16" y2="8"></line></svg>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted fw-bold text-uppercase text-truncate w-100" style="font-size: 0.65rem; letter-spacing: 0.5px;" title="<?php echo htmlspecialchars(str_replace('_', ' ', $mKey)); ?>">
                                            <?php echo strtoupper(str_replace('_', ' ', $mKey)); ?>
                                        </div>
                                        <div class="fw-bold text-primary my-1 text-truncate w-100" style="font-size: 1.25rem; line-height: 1.2;">
                                            <?php echo htmlspecialchars($mVal); ?>
                                        </div>
                                        <?php if ($hasUnit): ?>
                                            <div class="text-muted" style="font-size: 0.68rem;">inch</div>
                                        <?php else: ?>
                                            <div style="height: 0.68rem;"></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-ruler fa-2x text-muted mb-2"></i>
                            <p class="text-muted small mb-0">No measurements attached</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden bg-white">
                <div class="card-header px-4 py-3" style="background: #faf5ff; border: none;">
                    <span class="fw-bold" style="color: #6d28d9; font-size: 0.95rem;">
                        <i class="fas fa-clock me-2"></i>Timeline
                    </span>
                </div>
                <div class="card-body p-4">
                    <div class="timeline-container position-relative ps-4" style="border-left: 2px solid #e2e8f0; margin-left: 10px;">
                        <div class="timeline-item position-relative mb-4">
                            <div class="position-absolute rounded-circle" style="width: 12px; height: 12px; background: #3b82f6; left: -23px; top: 4px; box-shadow: 0 0 0 3px #dbeafe;"></div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fw-bold text-dark small"><i class="fas fa-calendar-plus text-muted me-2"></i>Order Created</span>
                                    <div class="text-muted" style="font-size: 0.8rem;"><?php echo format_date($order['created_at'], 'M j, Y H:i'); ?></div>
                                </div>
                                <?php if (!empty($order['created_by_name'])): ?>
                                    <span class="text-muted small">by <?php echo htmlspecialchars($order['created_by_name']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="timeline-item position-relative mb-4">
                            <div class="position-absolute rounded-circle" style="width: 12px; height: 12px; background: #10b981; left: -23px; top: 4px; box-shadow: 0 0 0 3px #d1fae5;"></div>
                            <div>
                                <span class="fw-bold text-dark small"><i class="fas fa-calendar-check text-muted me-2"></i>Order Date</span>
                                <div class="text-muted" style="font-size: 0.8rem;"><?php echo format_date($order['order_date'], 'M j, Y'); ?></div>
                            </div>
                        </div>
                        <div class="timeline-item position-relative mb-4">
                            <div class="position-absolute rounded-circle" style="width: 12px; height: 12px; background: #f59e0b; left: -23px; top: 4px; box-shadow: 0 0 0 3px #fef3c7;"></div>
                            <div>
                                <span class="fw-bold text-dark small"><i class="fas fa-calendar-alt text-muted me-2"></i>Due Date</span>
                                <div class="<?php echo $isOverdue ? 'text-danger fw-bold' : 'text-muted'; ?>" style="font-size: 0.8rem;">
                                    <?php echo format_date($order['due_date'], 'M j, Y'); ?>
                                </div>
                            </div>
                        </div>
                        <div class="timeline-item position-relative">
                            <div class="position-absolute rounded-circle" style="width: 12px; height: 12px; background: #64748b; left: -23px; top: 4px; box-shadow: 0 0 0 3px #f1f5f9;"></div>
                            <div>
                                <span class="fw-bold text-dark small"><i class="fas fa-clock text-muted me-2"></i>Last Updated</span>
                                <div class="text-muted" style="font-size: 0.8rem;"><?php echo format_date($order['updated_at'] ?? $order['created_at'], 'M j, Y H:i'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden bg-white">
                <div class="card-header px-4 py-3" style="background: #fff7ed; border: none;">
                    <span class="fw-bold" style="color: #c2410c; font-size: 0.95rem;">
                        <i class="fas fa-file-invoice text-warning me-2"></i>Invoice (<?php echo count($invoices); ?>)
                    </span>
                </div>
                <div class="card-body p-4 text-center d-flex flex-column align-items-center justify-content-center">
                    <?php if (empty($invoices)): ?>
                        <div class="mb-3 position-relative d-inline-block">
                            <div class="d-inline-flex align-items-center justify-content-center rounded-3" style="width: 64px; height: 64px; background: #dbeafe; color: #3b82f6;">
                                <i class="fas fa-file-alt" style="font-size: 2.2rem;"></i>
                            </div>
                            <div class="position-absolute bottom-0 end-0 rounded-circle d-flex align-items-center justify-content-center" style="width: 22px; height: 22px; background: #ef4444; color: white; font-size: 0.65rem; border: 2px solid white; transform: translate(25%, 25%);">
                                <i class="fas fa-times"></i>
                            </div>
                        </div>
                        <div class="fw-bold text-dark fs-6 mb-1">No invoice generated yet</div>
                        <div class="text-muted small mb-3">Generate an invoice to share with the customer.</div>
                        <?php if (!has_role('tailor')): ?>
                        <a href="invoices.php" class="btn btn-success rounded-pill px-4" style="background-color: #107548; border-color: #107548;">
                            <i class="fas fa-file-invoice me-2"></i>Generate Invoice
                        </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="table-responsive w-100 text-start">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoices as $inv): ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo htmlspecialchars($inv['invoice_number']); ?></td>
                                        <td><?php echo format_date($inv['invoice_date'], 'M j, Y'); ?></td>
                                        <td class="fw-bold"><?php echo format_currency($inv['total_amount']); ?></td>
                                        <td><span class="badge bg-success rounded-pill"><?php echo ucfirst($inv['payment_status']); ?></span></td>
                                        <td>
                                            <a href="invoice-details.php?id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 mb-4">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
            <div class="card-header d-flex justify-content-between align-items-center px-4 py-3" style="background: #fefce8; border: none;">
                <span class="fw-bold" style="color: #a16207; font-size: 0.95rem;">
                    <i class="fas fa-sticky-note me-2" style="color: #ca8a04;"></i>Notes
                </span>
                <?php if (!empty($order['special_instructions'])): ?>
                <button type="button" class="btn btn-sm btn-outline-warning rounded-pill px-3 py-0 bg-white" style="font-size: 0.75rem; border-color: #fde047; color: #a16207;" data-bs-toggle="modal" data-bs-target="#editNotesModal">
                    <i class="fas fa-edit me-1"></i>Edit
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-4 text-center">
                <?php if (!empty($order['special_instructions'])): ?>
                    <p class="mb-0 text-dark text-start"><?php echo nl2br(htmlspecialchars($order['special_instructions'])); ?></p>
                <?php else: ?>
                    <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-2" style="width: 48px; height: 48px; background: #fef08a; color: #ca8a04;">
                        <i class="fas fa-sticky-note fs-4"></i>
                    </div>
                    <div class="fw-bold text-dark fs-6 mb-1">No notes added yet.</div>
                    <div class="text-muted small mb-3">Add internal notes for this order.</div>
                    <button type="button" class="btn btn-outline-primary rounded-3 px-4 py-2 w-100" style="max-width: 380px; border-color: #3b82f6; color: #2563eb; font-weight: 500;" data-bs-toggle="modal" data-bs-target="#editNotesModal">
                        <i class="fas fa-plus-circle me-2"></i>Add Note
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="d-md-none mb-4 d-flex flex-column gap-2">
        <button type="button" class="btn btn-outline-primary rounded-3 py-2 w-100 fw-medium bg-white shadow-xs" onclick="printOrder()" style="border-color: #3b82f6; color: #2563eb; font-size: 0.95rem;">
            <i class="fas fa-print me-2"></i>Print Order
        </button>
        <?php if (!has_role('tailor')): ?>
        <a href="invoices.php" class="btn btn-success rounded-3 py-2 w-100 fw-medium text-white shadow-xs" style="background-color: #107548; border-color: #107548; font-size: 0.95rem;">
            <i class="fas fa-file-invoice me-2"></i>Generate Invoice
        </a>
        <?php endif; ?>
    </div>
</div>
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <form id="updateStatusForm">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Update Order Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <label class="form-label fw-medium text-dark">Order Status</label>
                    <select class="form-select rounded-3 mb-3 py-2" id="status" name="status" required>
                        <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="in_progress" <?php echo $order['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo $order['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                    <div class="mb-3" id="cancellationReasonGroup" style="display: <?php echo $order['status'] === 'cancelled' ? 'block' : 'none'; ?>;">
                        <label class="form-label fw-medium text-danger">Cancellation Reason</label>
                        <textarea class="form-control rounded-3" id="cancellation_reason" name="cancellation_reason" rows="2" placeholder="Explain reason for cancellation..."></textarea>
                    </div>
                    <small class="text-muted d-block">
                        <i class="fas fa-info-circle me-1"></i> Progression: Pending → In Progress → Completed → Delivered
                    </small>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4" id="updateStatusBtn">
                        <i class="fas fa-save me-2"></i>Save Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="editNotesModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="update_notes">
                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Edit Notes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <label class="form-label fw-medium text-dark">Notes / Special Instructions</label>
                    <textarea class="form-control rounded-3 p-3" name="notes" rows="4" placeholder="Enter special instructions or notes..."><?php echo htmlspecialchars($order['special_instructions'] ?? ''); ?></textarea>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">
                        <i class="fas fa-save me-2"></i>Save Notes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function printOrder() {
    window.print();
}
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('updateStatusForm');
    const updateBtn = document.getElementById('updateStatusBtn');
    const statusSelect = document.getElementById('status');
    const cancelReasonGroup = document.getElementById('cancellationReasonGroup');
    const cancelReasonInput = document.getElementById('cancellation_reason');

    if (statusSelect && cancelReasonGroup) {
        statusSelect.addEventListener('change', function() {
            cancelReasonGroup.style.display = this.value === 'cancelled' ? 'block' : 'none';
        });
    }

    if (form && updateBtn) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const orderId = document.querySelector('[name="order_id"]').value;
            const status = statusSelect.value;
            const csrfToken = document.querySelector('[name="csrf_token"]').value;
            const originalText = updateBtn.innerHTML;
            updateBtn.disabled = true;
            updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('order_id', orderId);
            formData.append('status', status);
            if (status === 'cancelled' && cancelReasonInput) {
                formData.append('cancellation_reason', cancelReasonInput.value.trim());
            }   
            fetch('ajax/update_order_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.error || 'Failed to update order status');
                    updateBtn.disabled = false;
                    updateBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error updating status:', error);
                alert('Network error occurred');
                updateBtn.disabled = false;
                updateBtn.innerHTML = originalText;
            });
        });
    }
});
</script>
<style>
@media print {
    .no-print, .btn, .card-header button, .card-header a {
        display: none !important;
    }
}
</style>
<?php require_once 'includes/footer.php'; ?>