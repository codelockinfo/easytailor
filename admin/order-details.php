<?php
require_once '../config/config.php';
require_login();
require_once 'models/Order.php';
require_once '../models/Customer.php';
require_once 'models/Invoice.php';
require_once 'models/Measurement.php';
require_once 'models/ClothType.php';
require_once 'models/User.php';
require_once '../helpers/SubscriptionHelper.php';
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
            if (has_role('tailor')) {
                $curUserId = (int)get_user_id();
                $isAuthorizedTailor = (!empty($order['assigned_tailor_id']) && (int)$order['assigned_tailor_id'] === $curUserId);
                if (!$isAuthorizedTailor) {
                    $orderItems = $orderModel->getOrderItems($orderId);
                    foreach ($orderItems as $oi) {
                        if (!empty($oi['assigned_tailor_id']) && (int)$oi['assigned_tailor_id'] === $curUserId) {
                            $isAuthorizedTailor = true;
                            break;
                        }
                    }
                }
                if (!$isAuthorizedTailor) {
                    $_SESSION['message'] = 'You can only update orders assigned to you';
                    $_SESSION['messageType'] = 'error';
                    header('Location: orders.php');
                    exit;
                }
                try {
                    $stmt = $orderModel->conn->prepare("UPDATE order_items SET status = :status WHERE order_id = :order_id AND assigned_tailor_id = :tailor_id");
                    $stmt->execute([':status' => $status, ':order_id' => $orderId, ':tailor_id' => $curUserId]);
                } catch (\Throwable $e) {}
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
require_once 'models/Company.php';
$companyModel = new Company();
$companyId = get_company_id();
$companyInfo = $companyId ? $companyModel->find($companyId) : null;
$companyName = !empty($companyInfo['company_name']) ? $companyInfo['company_name'] : '';
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
$orderItems = $orderModel->getOrderItems($orderId);
if (has_role('tailor')) {
    $curUserId = (int)get_user_id();
    $isAuthorizedTailor = (!empty($order['assigned_tailor_id']) && (int)$order['assigned_tailor_id'] === $curUserId);
    if (!$isAuthorizedTailor && !empty($orderItems)) {
        foreach ($orderItems as $oi) {
            if (!empty($oi['assigned_tailor_id']) && (int)$oi['assigned_tailor_id'] === $curUserId) {
                $isAuthorizedTailor = true;
                break;
            }
        }
    }
    if (!$isAuthorizedTailor) {
        $_SESSION['message'] = 'Unauthorized access to this order';
        $_SESSION['messageType'] = 'error';
        header('Location: orders.php');
        exit;
    }
    if (!empty($orderItems)) {
        $myItems = array_values(array_filter($orderItems, function($oi) use ($curUserId) {
            return !empty($oi['assigned_tailor_id']) && (int)$oi['assigned_tailor_id'] === $curUserId;
        }));
        if (!empty($myItems)) {
            $orderItems = $myItems;
            $myPrimaryItem = $myItems[0];
            if (!empty($myPrimaryItem['cloth_type_name'])) {
                $order['cloth_type_name'] = $myPrimaryItem['cloth_type_name'];
            }
            if (!empty($myPrimaryItem['cloth_type_id'])) {
                $order['cloth_type_id'] = $myPrimaryItem['cloth_type_id'];
            }
            if (!empty($myPrimaryItem['status'])) {
                $order['status'] = $myPrimaryItem['status'];
            }
            $order['assigned_tailor_id'] = $curUserId;
            if (!empty($myPrimaryItem['measurement_id'])) {
                $order['measurement_id'] = $myPrimaryItem['measurement_id'];
            }
        }
    }
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

// Prepare garments list for measurement modal
$orderGarmentsForModal = [];
if (!empty($orderItems)) {
    foreach ($orderItems as $idx => $oi) {
        $mData = $oi['measurement_data'] ?? [];
        if (is_string($mData)) {
            $mData = json_decode($mData, true) ?: [];
        }
        if (empty($mData) && !empty($oi['measurement_id'])) {
            $mRow = $measurementModel->find($oi['measurement_id']);
            if ($mRow && !empty($mRow['measurement_data'])) {
                $mData = is_string($mRow['measurement_data']) ? json_decode($mRow['measurement_data'], true) : $mRow['measurement_data'];
            }
        }
        $chartUrl = $oi['measurement_chart'] ?? null;
        if (empty($chartUrl) && !empty($oi['cloth_type_id'])) {
            $ctRow = $clothTypeModel->find($oi['cloth_type_id']);
            if (!empty($ctRow['measurement_chart_image'])) {
                $chartUrl = '../' . ltrim($ctRow['measurement_chart_image'], './');
            }
        }
        $orderGarmentsForModal[] = [
            'measurement_id' => $oi['measurement_id'] ?? null,
            'cloth_type_id' => $oi['cloth_type_id'] ?? null,
            'cloth_type_name' => $oi['cloth_type_name'] ?? 'Garment',
            'cloth_category' => $oi['category'] ?? '',
            'measurement_data' => $mData ?: [],
            'measurement_chart' => $chartUrl,
            'tailor_name' => $oi['tailor_name'] ?? ''
        ];
    }
}
if (empty($orderGarmentsForModal) && !empty($measurement)) {
    $fallbackChart = !empty($clothType['measurement_chart_image']) ? ('../' . ltrim($clothType['measurement_chart_image'], './')) : ($measurement['measurement_chart'] ?? null);
    $orderGarmentsForModal[] = [
        'measurement_id' => $order['measurement_id'] ?? null,
        'cloth_type_id' => $order['cloth_type_id'] ?? null,
        'cloth_type_name' => $order['cloth_type_name'] ?? ($clothType['name'] ?? 'Garment'),
        'cloth_category' => $clothType['category'] ?? '',
        'measurement_data' => $measurement['measurement_data'] ?? [],
        'measurement_chart' => $fallbackChart,
        'tailor_name' => $tailor['full_name'] ?? ''
    ];
}

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
if (empty($displayPhone)) {
    if (!empty($order['customer_phone'])) {
        $displayPhone = $order['customer_phone'];
    } elseif (!empty($measurement['phone_number'])) {
        $displayPhone = $measurement['phone_number'];
    }
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
                <i class="fas fa-print me-2"></i>Print Order
            </button>
            <?php 
                $canGenerateInvoice = !has_role('tailor') && (SubscriptionHelper::canGenerateInvoice($order['company_id'] ?? get_company_id())['allowed'] ?? false);
                $canSendInvoice = !has_role('tailor') && (SubscriptionHelper::canSendInvoice($order['company_id'] ?? get_company_id())['allowed'] ?? false);
            ?>
            <?php if ($canGenerateInvoice): ?>
            <a href="print-invoice.php?order_id=<?php echo $order['id']; ?>" target="_blank" class="btn btn-outline-secondary rounded-pill px-3 py-1 bg-white shadow-xs" style="border-color: #8b5cf6; color: #7c3aed; font-size: 0.88rem; font-weight: 500;">
                <i class="fas fa-file-invoice me-2"></i>Print Invoice
            </a>
            <?php endif; ?>
            <?php 
                if ($canSendInvoice):
                    $cleanPhone = preg_replace('/\D/', '', $displayPhone);
                    if (strlen($cleanPhone) === 10) $cleanPhone = '91' . $cleanPhone;
                    $waMsg = "";
                    if (!empty($companyName)) {
                        $cleanComp = strtoupper(trim($companyName));
                        $padComp = max(0, (int)floor((55 - (strlen($cleanComp) + 2)) / 2));
                        $waMsg .= str_repeat(" ", $padComp) . "*" . $cleanComp . "*\n";
                    }
                    $waMsg .= "*Order Receipt*\n";
                    $waMsg .= "--------------------------------\n📋 Order #: " . ($order['order_number'] ?? '') . "\n👤 Customer: " . $displayName . "\n👗 Item: " . ($clothType['name'] ?? '') . "\n📅 Due Date: " . ($order['due_date'] ?? '') . "\n--------------------------------\n💰 Total: ₹" . number_format($order['total_amount'] ?? 0, 2) . "\n✅ Advance: ₹" . number_format($order['advance_amount'] ?? 0, 2) . "\n⏳ Balance: ₹" . number_format($order['balance_amount'] ?? 0, 2) . "\n--------------------------------\nThank you for choosing us! 🙏";
            ?>
            <a href="https://wa.me/<?php echo $cleanPhone; ?>?text=<?php echo urlencode($waMsg); ?>" target="_blank" class="btn btn-success rounded-pill px-3 py-1 text-white shadow-xs" style="background-color: #16a34a; border-color: #16a34a; font-size: 0.88rem; font-weight: 500;">
                <i class="fab fa-whatsapp me-2"></i>Send Invoice
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
                    <?php if (!empty($order['payment_method'])): ?>
                        <span class="badge bg-light text-dark border text-uppercase" style="font-size: 0.62rem;"><?php echo htmlspecialchars($order['payment_method']); ?></span>
                    <?php endif; ?>
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
                            <div class="fw-bold fs-6 text-success d-flex align-items-center gap-1">
                                <?php echo format_currency($order['advance_amount']); ?>
                                <?php if (!empty($order['payment_method'])): ?>
                                    <span class="badge bg-light text-dark border text-uppercase" style="font-size: 0.65rem;"><?php echo htmlspecialchars($order['payment_method']); ?></span>
                                <?php endif; ?>
                            </div>
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
        <div class="col-lg-12   ">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden bg-white">
                <div class="card-header d-flex justify-content-between align-items-center px-4 py-3" style="background: #eef2ff; border: none;">
                    <span class="fw-bold" style="color: #2563eb; font-size: 0.95rem;">
                        <i class="fas fa-user me-2"></i>Customer Information
                    </span>
                    <div class="d-flex align-items-center gap-1">
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-0 bg-white" onclick="openOrderMeasurementsModal()" style="font-size: 0.75rem; border-color: #cbd5e1; color: #2563eb;" title="View Measurements">
                            <i class="fas fa-ruler-combined me-1"></i>View Measurements
                        </button>
                        <?php if (!has_role('tailor') && $customer): ?>
                        <a href="customer-details.php?id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-0 bg-white" style="font-size: 0.75rem; border-color: #cbd5e1; color: #2563eb;">
                            <i class="fas fa-edit me-1"></i>Edit
                        </a>
                        <?php endif; ?>
                    </div>
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
        
        
    </div>
    <?php if (!empty($orderItems)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 bg-white garments-main-card" style="overflow: visible;">
                <div class="card-header d-flex justify-content-between align-items-center px-4 py-3" style="background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%); border: none; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold text-primary" style="font-size: 0.95rem;">
                            <i class="fas fa-tshirt me-2"></i>Garments & Assigned Tailors 
                        </span>
                        <span class="badge rounded-pill bg-primary" style="font-size: 0.72rem;"><?php echo count($orderItems); ?> <?php echo count($orderItems) === 1 ? 'Garment' : 'Garments'; ?></span>
                    </div>
                </div>
                <div class="table-responsive d-none d-md-block" style="overflow-x: auto; overflow-y: visible; min-height: 260px; padding-bottom: 75px;">
                    <table class="table table-hover align-middle mb-0">
                        <thead style="background: #f8fafc; font-size: 0.78rem; font-weight: 700; color: #475569;">
                            <tr>
                                <th class="ps-4 py-3">#</th>
                                <th class="py-3">GARMENT / CLOTH TYPE</th>
                                <th class="py-3">MEASUREMENT</th>
                                <th class="py-3">ASSIGNED TAILOR</th>
                                <th class="py-3 text-end">RATE / PRICE</th>
                                <th class="pe-4 py-3 text-center">STATUS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $itemStatusStyles = [
                                'pending' => ['bg' => '#fef3c7', 'color' => '#b45309', 'border' => '#fde68a', 'icon' => 'fa-clock'],
                                'in_progress' => ['bg' => '#e0f2fe', 'color' => '#0284c7', 'border' => '#bae6fd', 'icon' => 'fa-cogs'],
                                'completed' => ['bg' => '#dcfce7', 'color' => '#15803d', 'border' => '#bbf7d0', 'icon' => 'fa-check-circle'],
                                'delivered' => ['bg' => '#eff6ff', 'color' => '#2563eb', 'border' => '#bfdbfe', 'icon' => 'fa-truck'],
                                'cancelled' => ['bg' => '#fee2e2', 'color' => '#dc2626', 'border' => '#fecaca', 'icon' => 'fa-times-circle']
                            ];
                            foreach ($orderItems as $idx => $oi): 
                                $isMyItem = (has_role('tailor') && !empty($oi['assigned_tailor_id']) && (int)$oi['assigned_tailor_id'] === (int)get_user_id());
                                $canEditItem = (!has_role('tailor') || $isMyItem);
                                $oiStatus = !empty($oi['status']) ? $oi['status'] : 'pending';
                                $stStyle = $itemStatusStyles[$oiStatus] ?? $itemStatusStyles['pending'];
                            ?>
                            <tr style="<?php echo $isMyItem ? 'background: #f0fdf4;' : ''; ?>">
                                <td class="ps-4 py-3 fw-bold text-muted" style="font-size: 0.85rem;">
                                    <?php echo $idx + 1; ?>
                                </td>
                                <td class="py-3">
                                    <span class="fw-bold text-dark" style="font-size: 0.88rem;">
                                        <?php echo htmlspecialchars($oi['cloth_type_name'] ?? 'Garment'); ?>
                                    </span>
                                    <?php if (!empty($oi['category'])): ?>
                                        <span class="badge rounded-pill bg-light text-primary border ms-1" style="font-size: 0.68rem;"><?php echo htmlspecialchars($oi['category']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3">
                                    <?php if (!empty($oi['measurement_id']) || !empty($oi['measurement_data'])): ?>
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <button type="button" class="btn btn-sm rounded-pill px-3 py-1 d-inline-flex align-items-center gap-1 shadow-sm border-0" 
                                                    style="background: #ecfdf5; color: #047857; font-size: 0.78rem; font-weight: 600; border: 1px solid #a7f3d0 !important; cursor: pointer;" 
                                                    onclick="openOrderMeasurementsModal(<?php echo (int)($oi['measurement_id'] ?? 0); ?>, '<?php echo htmlspecialchars(addslashes($oi['cloth_type_name'] ?? '')); ?>')"
                                                    title="View <?php echo htmlspecialchars($oi['cloth_type_name'] ?? ''); ?> measurement">
                                                <i class="fas fa-ruler-combined text-success"></i>
                                                <span><?php echo htmlspecialchars($oi['cloth_type_name'] ?? 'Measurement'); ?></span>
                                                <i class="fas fa-chevron-down ms-1 text-muted" style="font-size: 0.65rem;"></i>
                                            </button>
                                            <?php if (!has_role('tailor') && !empty($oi['measurement_id'])): ?>
                                                <a href="measurements.php?edit=<?php echo $oi['measurement_id']; ?>" class="btn btn-sm btn-link text-decoration-none p-0 text-muted ms-1" title="Edit Measurement" style="font-size: 0.75rem;">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">No Measurement Attached</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3">
                                    <?php if (!empty($oi['tailor_name'])): ?>
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <span class="fw-semibold text-primary" style="font-size: 0.84rem;">
                                                <i class="fas fa-user-tie me-1"></i><?php echo htmlspecialchars($oi['tailor_name']); ?>
                                            </span>
                                            <?php if ($isMyItem): ?>
                                                <span class="badge rounded-pill bg-success text-white ms-1" style="font-size: 0.65rem;">Assigned to You</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small" style="font-style: italic;">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 text-end fw-bold text-dark" style="font-size: 0.88rem;">
                                    <?php echo format_currency($oi['price']); ?>
                                </td>
                                <td class="pe-4 py-3 text-center">
                                    <?php if ($canEditItem): ?>
                                        <div class="dropdown d-inline-block">
                                            <button type="button" 
                                                    class="btn btn-sm rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1 shadow-sm item-status-badge-btn item-status-btn-<?php echo $oi['id']; ?>" 
                                                    id="itemStatusBtn_<?php echo $oi['id']; ?>"
                                                    data-bs-toggle="dropdown" 
                                                    data-bs-popper-config='{"strategy":"fixed"}'
                                                    aria-expanded="false"
                                                    style="background: <?php echo $stStyle['bg']; ?>; color: <?php echo $stStyle['color']; ?>; border: 1px solid <?php echo $stStyle['border']; ?>; font-size: 0.76rem;"
                                                    title="Click to update garment status">
                                                <i class="fas <?php echo $stStyle['icon']; ?>" id="itemStatusIcon_<?php echo $oi['id']; ?>"></i>
                                                <span id="itemStatusText_<?php echo $oi['id']; ?>"><?php echo ucfirst(str_replace('_', ' ', $oiStatus)); ?></span>
                                                <i class="fas fa-chevron-down ms-1" style="font-size: 0.6rem; opacity: 0.7;"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 py-2" style="border-radius: 12px; font-size: 0.82rem; min-width: 165px; z-index: 1060; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2), 0 8px 10px -6px rgba(0,0,0,0.1) !important;">
                                                <li><h6 class="dropdown-header text-uppercase text-muted py-1" style="font-size: 0.65rem; letter-spacing: 0.5px;">Update Status</h6></li>
                                                <li><a class="dropdown-item py-1 px-3 d-flex align-items-center justify-content-between <?php echo $oiStatus === 'pending' ? 'active fw-bold' : ''; ?>" href="#" onclick="event.preventDefault(); updateOrderItemStatus(<?php echo $oi['id']; ?>, 'pending', <?php echo $order['id']; ?>);"><span class="text-warning"><i class="fas fa-clock me-2"></i>Pending</span> <?php echo $oiStatus === 'pending' ? '<i class="fas fa-check small"></i>' : ''; ?></a></li>
                                                <li><a class="dropdown-item py-1 px-3 d-flex align-items-center justify-content-between <?php echo $oiStatus === 'in_progress' ? 'active fw-bold' : ''; ?>" href="#" onclick="event.preventDefault(); updateOrderItemStatus(<?php echo $oi['id']; ?>, 'in_progress', <?php echo $order['id']; ?>);"><span class="text-info"><i class="fas fa-cogs me-2"></i>In Progress</span> <?php echo $oiStatus === 'in_progress' ? '<i class="fas fa-check small"></i>' : ''; ?></a></li>
                                                <li><a class="dropdown-item py-1 px-3 d-flex align-items-center justify-content-between <?php echo $oiStatus === 'completed' ? 'active fw-bold' : ''; ?>" href="#" onclick="event.preventDefault(); updateOrderItemStatus(<?php echo $oi['id']; ?>, 'completed', <?php echo $order['id']; ?>);"><span class="text-success"><i class="fas fa-check-circle me-2"></i>Completed</span> <?php echo $oiStatus === 'completed' ? '<i class="fas fa-check small"></i>' : ''; ?></a></li>
                                                <li><a class="dropdown-item py-1 px-3 d-flex align-items-center justify-content-between <?php echo $oiStatus === 'delivered' ? 'active fw-bold' : ''; ?>" href="#" onclick="event.preventDefault(); updateOrderItemStatus(<?php echo $oi['id']; ?>, 'delivered', <?php echo $order['id']; ?>);"><span class="text-primary"><i class="fas fa-truck me-2"></i>Delivered</span> <?php echo $oiStatus === 'delivered' ? '<i class="fas fa-check small"></i>' : ''; ?></a></li>
                                                <li><hr class="dropdown-divider my-1"></li>
                                                <li><a class="dropdown-item py-1 px-3 d-flex align-items-center justify-content-between text-danger <?php echo $oiStatus === 'cancelled' ? 'active fw-bold' : ''; ?>" href="#" onclick="event.preventDefault(); updateOrderItemStatus(<?php echo $oi['id']; ?>, 'cancelled', <?php echo $order['id']; ?>);"><span><i class="fas fa-times-circle me-2"></i>Cancelled</span> <?php echo $oiStatus === 'cancelled' ? '<i class="fas fa-check small"></i>' : ''; ?></a></li>
                                            </ul>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1 shadow-sm" 
                                              style="background: <?php echo $stStyle['bg']; ?>; color: <?php echo $stStyle['color']; ?>; border: 1px solid <?php echo $stStyle['border']; ?>; font-size: 0.76rem;"
                                              title="Assigned to <?php echo htmlspecialchars($oi['tailor_name'] ?? 'another tailor'); ?>">
                                            <i class="fas <?php echo $stStyle['icon']; ?>"></i>
                                            <span><?php echo ucfirst(str_replace('_', ' ', $oiStatus)); ?></span>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot style="background: #f8fafc; font-size: 0.84rem; font-weight: 700;">
                            <tr>
                                <td colspan="4" class="ps-4 py-2 text-muted text-uppercase">Total Items Combined:</td>
                                <td class="py-2 text-end text-primary"><?php echo format_currency(has_role('tailor') && !empty($orderItems) ? array_sum(array_column($orderItems, 'price')) : $order['total_amount']); ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- MOBILE CARDS VIEW -->
                <div class="d-md-none d-flex flex-column gap-3 mb-3" id="garmentsMobileCardsList">
                    <?php 
                    foreach ($orderItems as $idx => $oi): 
                        $isMyItem = (has_role('tailor') && !empty($oi['assigned_tailor_id']) && (int)$oi['assigned_tailor_id'] === (int)get_user_id());
                        $canEditItem = (!has_role('tailor') || $isMyItem);
                        $oiStatus = !empty($oi['status']) ? $oi['status'] : 'pending';
                        $stStyle = $itemStatusStyles[$oiStatus] ?? $itemStatusStyles['pending'];
                    ?>
                    <div class="garment-mobile-card <?php echo $isMyItem ? 'my-assigned-item' : ''; ?>">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge rounded-pill bg-light text-muted border px-2 py-1 fw-bold" style="font-size: 0.72rem;">#<?php echo $idx + 1; ?></span>
                                <span class="fw-bold text-dark" style="font-size: 0.92rem;"><?php echo htmlspecialchars($oi['cloth_type_name'] ?? 'Garment'); ?></span>
                                <?php if (!empty($oi['category'])): ?>
                                    <span class="badge rounded-pill bg-light text-primary border" style="font-size: 0.68rem;"><?php echo htmlspecialchars($oi['category']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <?php if ($canEditItem): ?>
                                    <div class="dropdown d-inline-block">
                                        <button type="button" 
                                                class="btn btn-sm rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1 shadow-sm item-status-badge-btn item-status-btn-<?php echo $oi['id']; ?>" 
                                                id="itemStatusBtn_mob_<?php echo $oi['id']; ?>"
                                                data-bs-toggle="dropdown" 
                                                data-bs-popper-config='{"strategy":"fixed"}'
                                                aria-expanded="false"
                                                style="background: <?php echo $stStyle['bg']; ?>; color: <?php echo $stStyle['color']; ?>; border: 1px solid <?php echo $stStyle['border']; ?>; font-size: 0.75rem;"
                                                title="Click to update garment status">
                                            <i class="fas <?php echo $stStyle['icon']; ?>" id="itemStatusIcon_mob_<?php echo $oi['id']; ?>"></i>
                                            <span id="itemStatusText_mob_<?php echo $oi['id']; ?>"><?php echo ucfirst(str_replace('_', ' ', $oiStatus)); ?></span>
                                            <i class="fas fa-chevron-down ms-1" style="font-size: 0.58rem; opacity: 0.7;"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 py-2" style="border-radius: 12px; font-size: 0.82rem; min-width: 165px; z-index: 1060; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2), 0 8px 10px -6px rgba(0,0,0,0.1) !important;">
                                            <li><h6 class="dropdown-header text-uppercase text-muted py-1" style="font-size: 0.65rem; letter-spacing: 0.5px;">Update Status</h6></li>
                                            <li><a class="dropdown-item py-1 px-3 d-flex align-items-center justify-content-between <?php echo $oiStatus === 'pending' ? 'active fw-bold' : ''; ?>" href="#" onclick="event.preventDefault(); updateOrderItemStatus(<?php echo $oi['id']; ?>, 'pending', <?php echo $order['id']; ?>);"><span class="text-warning"><i class="fas fa-clock me-2"></i>Pending</span> <?php echo $oiStatus === 'pending' ? '<i class="fas fa-check small"></i>' : ''; ?></a></li>
                                            <li><a class="dropdown-item py-1 px-3 d-flex align-items-center justify-content-between <?php echo $oiStatus === 'in_progress' ? 'active fw-bold' : ''; ?>" href="#" onclick="event.preventDefault(); updateOrderItemStatus(<?php echo $oi['id']; ?>, 'in_progress', <?php echo $order['id']; ?>);"><span class="text-info"><i class="fas fa-cogs me-2"></i>In Progress</span> <?php echo $oiStatus === 'in_progress' ? '<i class="fas fa-check small"></i>' : ''; ?></a></li>
                                            <li><a class="dropdown-item py-1 px-3 d-flex align-items-center justify-content-between <?php echo $oiStatus === 'completed' ? 'active fw-bold' : ''; ?>" href="#" onclick="event.preventDefault(); updateOrderItemStatus(<?php echo $oi['id']; ?>, 'completed', <?php echo $order['id']; ?>);"><span class="text-success"><i class="fas fa-check-circle me-2"></i>Completed</span> <?php echo $oiStatus === 'completed' ? '<i class="fas fa-check small"></i>' : ''; ?></a></li>
                                            <li><a class="dropdown-item py-1 px-3 d-flex align-items-center justify-content-between <?php echo $oiStatus === 'delivered' ? 'active fw-bold' : ''; ?>" href="#" onclick="event.preventDefault(); updateOrderItemStatus(<?php echo $oi['id']; ?>, 'delivered', <?php echo $order['id']; ?>);"><span class="text-primary"><i class="fas fa-truck me-2"></i>Delivered</span> <?php echo $oiStatus === 'delivered' ? '<i class="fas fa-check small"></i>' : ''; ?></a></li>
                                            <li><hr class="dropdown-divider my-1"></li>
                                            <li><a class="dropdown-item py-1 px-3 d-flex align-items-center justify-content-between text-danger <?php echo $oiStatus === 'cancelled' ? 'active fw-bold' : ''; ?>" href="#" onclick="event.preventDefault(); updateOrderItemStatus(<?php echo $oi['id']; ?>, 'cancelled', <?php echo $order['id']; ?>);"><span><i class="fas fa-times-circle me-2"></i>Cancelled</span> <?php echo $oiStatus === 'cancelled' ? '<i class="fas fa-check small"></i>' : ''; ?></a></li>
                                        </ul>
                                    </div>
                                <?php else: ?>
                                    <span class="badge rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1 shadow-sm" 
                                          style="background: <?php echo $stStyle['bg']; ?>; color: <?php echo $stStyle['color']; ?>; border: 1px solid <?php echo $stStyle['border']; ?>; font-size: 0.75rem;"
                                          title="Assigned to <?php echo htmlspecialchars($oi['tailor_name'] ?? 'another tailor'); ?>">
                                        <i class="fas <?php echo $stStyle['icon']; ?>"></i>
                                        <span><?php echo ucfirst(str_replace('_', ' ', $oiStatus)); ?></span>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="px-3 py-2 my-2 rounded-3" style="background: #f8fafc; border: 1px solid #eef2f6;">
                            <div class="row g-2 align-items-center">
                                <div class="col-6">
                                    <span class="text-muted d-block text-uppercase" style="font-size: 0.68rem; font-weight: 600; letter-spacing: 0.5px;">Measurement</span>
                                    <div class="mt-1">
                                        <?php if (!empty($oi['measurement_id']) || !empty($oi['measurement_data'])): ?>
                                            <div class="d-inline-flex align-items-center gap-1">
                                                <button type="button" class="btn btn-sm rounded-pill px-2 py-0.5 d-inline-flex align-items-center gap-1 shadow-sm border-0" 
                                                        style="background: #ecfdf5; color: #047857; font-size: 0.72rem; font-weight: 600; border: 1px solid #a7f3d0 !important; cursor: pointer; line-height: 1.5;" 
                                                        onclick="openOrderMeasurementsModal(<?php echo (int)($oi['measurement_id'] ?? 0); ?>, '<?php echo htmlspecialchars(addslashes($oi['cloth_type_name'] ?? '')); ?>')"
                                                        title="View <?php echo htmlspecialchars($oi['cloth_type_name'] ?? ''); ?> measurement">
                                                    <i class="fas fa-ruler-combined text-success"></i>
                                                    <span class="text-truncate" style="max-width: 80px;"><?php echo htmlspecialchars($oi['cloth_type_name'] ?? 'Measurement'); ?></span>
                                                    <i class="fas fa-chevron-down ms-1 text-muted" style="font-size: 0.58rem;"></i>
                                                </button>
                                                <?php if (!has_role('tailor') && !empty($oi['measurement_id'])): ?>
                                                    <a href="measurements.php?edit=<?php echo $oi['measurement_id']; ?>" class="btn btn-sm btn-link text-decoration-none p-0 text-muted ms-1" title="Edit Measurement" style="font-size: 0.72rem;">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size: 0.75rem;">None</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted d-block text-uppercase" style="font-size: 0.68rem; font-weight: 600; letter-spacing: 0.5px;">Assigned Tailor</span>
                                    <div class="mt-1">
                                        <?php if (!empty($oi['tailor_name'])): ?>
                                            <div class="d-inline-flex align-items-center gap-1 flex-wrap">
                                                <span class="fw-semibold text-primary" style="font-size: 0.78rem;">
                                                    <i class="fas fa-user-tie me-1"></i><?php echo htmlspecialchars($oi['tailor_name']); ?>
                                                </span>
                                                <?php if ($isMyItem): ?>
                                                    <span class="badge rounded-pill bg-success text-white" style="font-size: 0.6rem; padding: 2px 6px;">You</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-style: italic; font-size: 0.75rem;">Unassigned</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-1">
                            <span class="text-muted fw-semibold" style="font-size: 0.78rem;">Rate / Price:</span>
                            <span class="fw-bold text-dark fs-6"><?php echo format_currency($oi['price']); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <!-- Mobile Summary Card -->
                    <div class="p-3 bg-white rounded-3 border d-flex justify-content-between align-items-center shadow-sm" style="border-radius: 14px; border-color: #e2e8f0 !important;">
                        <span class="text-muted fw-bold text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                            <i class="fas fa-calculator me-1 text-primary"></i>Total Items Combined:
                        </span>
                        <span class="fw-bold text-primary fs-6">
                            <?php echo format_currency(has_role('tailor') && !empty($orderItems) ? array_sum(array_column($orderItems, 'price')) : $order['total_amount']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
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
                <div class="card-body p-4 text-center d-flex flex-column align-items-center justify-content-center">
                    <?php if (!empty($order['special_instructions'])): ?>
                        <div class="w-100 text-start">
                            <p class="mb-0 text-dark"><?php echo nl2br(htmlspecialchars($order['special_instructions'])); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-2" style="width: 48px; height: 48px; background: #fef08a; color: #ca8a04;">
                            <i class="fas fa-sticky-note fs-4"></i>
                        </div>
                        <div class="fw-bold text-dark fs-6 mb-1">No notes added yet.</div>
                        <div class="text-muted small mb-3">Add internal notes for this order.</div>
                        <button type="button" class="btn btn-outline-primary rounded-3 px-4 py-2 w-100" style="max-width: 320px; border-color: #3b82f6; color: #2563eb; font-weight: 500;" data-bs-toggle="modal" data-bs-target="#editNotesModal">
                            <i class="fas fa-plus-circle me-2"></i>Add Note
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="d-md-none mb-4 d-flex flex-column gap-2">
        <button type="button" class="btn btn-outline-primary rounded-3 py-2 w-100 fw-medium bg-white shadow-xs" onclick="printOrder()" style="border-color: #3b82f6; color: #2563eb; font-size: 0.95rem;">
            <i class="fas fa-print me-2"></i>Print Order
        </button>
        <?php if ($canGenerateInvoice && !has_role('tailor')): ?>
        <a href="print-invoice.php?order_id=<?php echo $order['id']; ?>" target="_blank" class="btn btn-outline-secondary rounded-3 py-2 w-100 fw-medium bg-white shadow-xs" style="border-color: #8b5cf6; color: #7c3aed; font-size: 0.95rem;">
            <i class="fas fa-print me-2"></i>Print Invoice
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

<!-- Order Customer Measurements Modal -->
<div class="modal fade" id="orderMeasurementModal" tabindex="-1" aria-labelledby="orderMeasurementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-primary text-white border-0 py-3 px-4">
                <h5 class="modal-title fw-bold" id="orderMeasurementModalLabel">
                    <i class="fas fa-ruler-combined me-2"></i>Measurement Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="orderMeasurementModalContent">
                <!-- Dynamically loaded -->
            </div>
            <div class="modal-footer border-0 bg-light py-2 px-4">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Close
                </button>
                <button type="button" class="btn btn-primary rounded-pill px-4" onclick="printOrderMeasurementsModal()">
                    <i class="fas fa-print me-1"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function printOrder() {
    window.print();
}

const orderGarmentsData = <?php echo json_encode($orderGarmentsForModal, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const orderCustomerDetails = {
    name: <?php echo json_encode($displayName); ?>,
    phone: <?php echo json_encode($displayPhone); ?>,
    email: <?php echo json_encode($displayEmail); ?>,
    orderNumber: <?php echo json_encode($order['order_number'] ?? ''); ?>,
    orderDate: <?php echo json_encode($order['order_date'] ? date('M d, Y', strtotime($order['order_date'])) : ''); ?>
};

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function openOrderMeasurementsModal(targetMeasurementId = 0, targetClothName = '') {
    const modalEl = document.getElementById('orderMeasurementModal');
    const contentEl = document.getElementById('orderMeasurementModalContent');
    if (!modalEl || !contentEl) return;

    let garmentsAccordionHtml = '';
    if (!orderGarmentsData || orderGarmentsData.length === 0) {
        garmentsAccordionHtml = '<div class="alert alert-info text-center py-3">No measurement data attached to this order.</div>';
    } else {
        garmentsAccordionHtml = '<div class="accordion d-flex flex-column gap-2 mb-3" id="orderGarmentsAccordion">';
        orderGarmentsData.forEach((garment, gIdx) => {
            const mData = garment.measurement_data || {};
            const keys = Object.keys(mData);
            let fieldsHtml = '';
            if (keys.length > 0) {
                keys.forEach(k => {
                    const label = String(k).replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    const val = mData[k];
                    fieldsHtml += `
                        <div class="col-sm-4 col-6 mb-2">
                            <div class="p-2 border rounded bg-light text-center h-100">
                                <small class="text-muted d-block text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.3px;">${escapeHtml(label)}</small>
                                <strong class="text-primary fs-6">${escapeHtml(val)}</strong>
                            </div>
                        </div>
                    `;
                });
            } else {
                fieldsHtml = '<div class="col-12 text-muted small p-2">No measurement fields recorded</div>';
            }

            let chartHtml = '';
            if (garment.measurement_chart) {
                chartHtml = `
                    <div class="col-md-5 text-center mt-3 mt-md-0">
                        <div class="border rounded p-2 bg-light">
                            <small class="text-muted d-block mb-1 fw-bold" style="font-size: 0.74rem;">Measurement Guide</small>
                            <img src="${escapeHtml(garment.measurement_chart)}" class="img-fluid rounded" style="max-height: 220px;" alt="Guide">
                        </div>
                    </div>
                `;
            }

            // Determine if this item should be open
            let isItemOpen = false;
            if (targetClothName) {
                isItemOpen = (String(garment.cloth_type_name || '').toLowerCase().trim() === String(targetClothName).toLowerCase().trim());
            } else if (targetMeasurementId && Number(targetMeasurementId) > 0) {
                isItemOpen = (Number(garment.measurement_id) === Number(targetMeasurementId));
            } else {
                isItemOpen = (gIdx === 0);
            }

            garmentsAccordionHtml += `
                <div class="accordion-item border shadow-sm" style="border-radius: 12px; overflow: hidden; border-color: #e2e8f0 !important;">
                    <h2 class="accordion-header" id="heading-order-garment-${gIdx}">
                        <button class="accordion-button py-2 px-3 bg-light text-dark fw-bold d-flex align-items-center gap-2 ${isItemOpen ? '' : 'collapsed'}" 
                                type="button" 
                                data-bs-toggle="collapse" 
                                data-bs-target="#collapse-order-garment-${gIdx}" 
                                aria-expanded="${isItemOpen ? 'true' : 'false'}" 
                                aria-controls="collapse-order-garment-${gIdx}"
                                style="font-size: 0.92rem; box-shadow: none;">
                            <span class="badge bg-primary rounded-pill me-1">#${gIdx + 1}</span>
                            <span class="text-dark fw-bold">${escapeHtml(garment.cloth_type_name || 'Cloth')}</span>
                            ${garment.cloth_category ? `<span class="badge bg-light text-primary border small ms-1" style="font-size: 0.70rem;">${escapeHtml(garment.cloth_category)}</span>` : ''}
                            ${garment.tailor_name ? `<span class="badge bg-secondary bg-opacity-10 text-secondary border small ms-1" style="font-size: 0.70rem;"><i class="fas fa-cut me-1"></i>${escapeHtml(garment.tailor_name)}</span>` : ''}
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 small ms-auto me-2">
                                ${keys.length} ${keys.length === 1 ? 'Field' : 'Fields'}
                            </span>
                        </button>
                    </h2>
                    <div id="collapse-order-garment-${gIdx}" class="accordion-collapse collapse ${isItemOpen ? 'show' : ''}" aria-labelledby="heading-order-garment-${gIdx}" data-bs-parent="#orderGarmentsAccordion">
                        <div class="accordion-body p-3 bg-white">
                            <div class="row align-items-start">
                                <div class="${garment.measurement_chart ? 'col-md-7' : 'col-12'}">
                                    <div class="row g-2">
                                        ${fieldsHtml}
                                    </div>
                                </div>
                                ${chartHtml}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        garmentsAccordionHtml += '</div>';
    }

    contentEl.innerHTML = `
        <div class="card mb-3 border shadow-none" style="border-radius: 12px; border-color: #e2e8f0 !important; overflow: hidden;">
            <div class="py-2 px-3" style="background: #f8fafc !important; border-bottom: 1px solid #e2e8f0 !important;">
                <h6 class="mb-0 fw-bold" style="color: #1e293b !important; font-size: 0.92rem;"><i class="fas fa-user me-2 text-primary"></i>Customer Information</h6>
            </div>
            <div class="card-body p-3">
                <div class="row g-3">
                    <div class="col-sm-4 col-6">
                        <small class="text-muted d-block">Customer Name</small>
                        <strong class="text-dark">${escapeHtml(orderCustomerDetails.name || 'Customer')}</strong>
                    </div>
                    <div class="col-sm-4 col-6">
                        <small class="text-muted d-block">Phone Number</small>
                        <span>${escapeHtml(orderCustomerDetails.phone || '—')}</span>
                    </div>
                    <div class="col-sm-4 col-6">
                        <small class="text-muted d-block">Email</small>
                        <span>${escapeHtml(orderCustomerDetails.email || '—')}</span>
                    </div>
                    <div class="col-sm-4 col-6">
                        <small class="text-muted d-block">Order #</small>
                        <span class="badge bg-light text-dark border">${escapeHtml(orderCustomerDetails.orderNumber || '—')}</span>
                    </div>
                    <div class="col-sm-4 col-6">
                        <small class="text-muted d-block">Total Garments</small>
                        <span class="badge bg-primary rounded-pill">${orderGarmentsData.length} ${orderGarmentsData.length === 1 ? 'Garment' : 'Garments'}</span>
                    </div>
                </div>
            </div>
        </div>

        <h6 class="fw-bold text-dark mb-2 ms-1"><i class="fas fa-tshirt me-2 text-primary"></i>Garment Measurements (${orderGarmentsData.length})</h6>
        ${garmentsAccordionHtml}
    `;

    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    bsModal.show();
}

function printOrderMeasurementsModal() {
    const modalBody = document.getElementById('orderMeasurementModalContent');
    if (!modalBody) return;
    const printWindow = window.open('', '', 'height=600,width=800');
    printWindow.document.write('<html><head><title>Customer Measurements</title>');
    printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">');
    printWindow.document.write('<style>body { padding: 20px; font-family: sans-serif; } .collapse:not(.show) { display: block !important; } .accordion-button::after { display: none; }</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write('<h3 class="text-center mb-3">Measurement Details</h3>');
    printWindow.document.write(modalBody.innerHTML);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
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

function showToast(title, message, type = 'info') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }
    const toastId = 'toast-' + Date.now();
    const toastElement = document.createElement('div');
    toastElement.id = toastId;
    toastElement.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0 shadow-lg`;
    toastElement.setAttribute('role', 'alert');
    toastElement.setAttribute('aria-live', 'assertive');
    toastElement.setAttribute('aria-atomic', 'true');
    
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
    const toast = new bootstrap.Toast(toastElement, { delay: 4000 });
    toast.show();
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

function updateOrderItemStatus(itemId, status, orderId) {
    const btns = document.querySelectorAll('#itemStatusBtn_' + itemId + ', #itemStatusBtn_mob_' + itemId + ', .item-status-btn-' + itemId);
    const originalStates = [];
    btns.forEach(btn => {
        originalStates.push({ btn: btn, html: btn.innerHTML });
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        btn.style.pointerEvents = 'none';
    });

    const formData = new FormData();
    formData.append('item_id', itemId);
    formData.append('order_id', orderId);
    formData.append('status', status);
    formData.append('csrf_token', '<?php echo generate_csrf_token(); ?>');

    fetch('ajax/update_order_item_status.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btns.forEach(btn => {
                if (data.item_style) {
                    btn.style.background = data.item_style.bg;
                    btn.style.color = data.item_style.color;
                    btn.style.borderColor = data.item_style.border;
                    const isMob = btn.id.includes('mob');
                    const iconId = isMob ? `itemStatusIcon_mob_${itemId}` : `itemStatusIcon_${itemId}`;
                    const textId = isMob ? `itemStatusText_mob_${itemId}` : `itemStatusText_${itemId}`;
                    btn.innerHTML = `
                        <i class="fas ${data.item_style.icon}" id="${iconId}"></i>
                        <span id="${textId}">${data.item_status_display}</span>
                        <i class="fas fa-chevron-down ms-1" style="font-size: ${isMob ? '0.58rem' : '0.6rem'}; opacity: 0.7;"></i>
                    `;
                }
                btn.style.pointerEvents = 'auto';

                // Update active checkmarks in this button's dropdown
                const parentDropdown = btn.closest('.dropdown');
                if (parentDropdown) {
                    const items = parentDropdown.querySelectorAll('.dropdown-item');
                    items.forEach(it => {
                        const isNowActive = it.textContent.trim().toLowerCase().replace(/\s+/g, '_').includes(status);
                        if (isNowActive) {
                            it.classList.add('active', 'fw-bold');
                            if (!it.querySelector('.fa-check')) {
                                it.insertAdjacentHTML('beforeend', ' <i class="fas fa-check small"></i>');
                            }
                        } else {
                            it.classList.remove('active', 'fw-bold');
                            const chk = it.querySelector('.fa-check');
                            if (chk) chk.remove();
                        }
                    });
                }
            });

            // Update overall order status badge if returned
            const overallBadge = document.getElementById('overallOrderStatusBadge');
            if (overallBadge && data.overall_status_display) {
                overallBadge.textContent = data.overall_status_display;
                if (data.overall_status_bg) {
                    overallBadge.setAttribute('style', data.overall_status_bg + ' padding: 4px 12px; font-weight: 500;');
                }
            }
            const topBadge = document.getElementById('topOrderStatusBadge');
            if (topBadge && data.overall_status_display) {
                topBadge.textContent = data.overall_status_display;
                if (data.overall_status_bg) {
                    topBadge.setAttribute('style', data.overall_status_bg + ' padding: 5px 14px; font-weight: 500; font-size: 0.78rem;');
                }
            }
            showToast('Success', data.message || 'Garment status updated successfully', 'success');
        } else {
            originalStates.forEach(os => {
                os.btn.innerHTML = os.html;
                os.btn.style.pointerEvents = 'auto';
            });
            showToast('Error', data.error || 'Failed to update garment status', 'error');
        }
    })
    .catch(err => {
        console.error('Error updating garment status:', err);
        originalStates.forEach(os => {
            os.btn.innerHTML = os.html;
            os.btn.style.pointerEvents = 'auto';
        });
        showToast('Error', 'Network error occurred while updating status', 'error');
    });
}
</script>
<style>
@media (max-width: 767.98px) {
    .garments-main-card {
        background: transparent !important;
        background-color: transparent !important;
        border: none !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        margin-bottom: 0.75rem !important;
    }
    .garments-main-card > .card-header {
        background: transparent !important;
        background-color: transparent !important;
        border: none !important;
        padding-left: 4px !important;
        padding-right: 4px !important;
        padding-top: 0 !important;
        padding-bottom: 12px !important;
    }
    #garmentsMobileCardsList {
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
}
.garment-mobile-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 14px 15px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    transition: all 0.2s ease;
    position: relative;
}
.garment-mobile-card.my-assigned-item {
    background: #f0fdf4;
    border-color: #bbf7d0;
}
.garment-mobile-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}
@media (max-width: 575.98px) {
    .garment-mobile-card {
        padding: 12px;
    }
}
#orderGarmentsAccordion .accordion-button:not(.collapsed) {
    background-color: #eff6ff !important;
    color: #1d4ed8 !important;
    box-shadow: none !important;
}
#orderGarmentsAccordion .accordion-button:focus {
    box-shadow: none !important;
    border-color: #e2e8f0 !important;
}
@media print {
    .no-print, .btn, .card-header button, .card-header a {
        display: none !important;
    }
}
</style>
<?php require_once 'includes/footer.php'; ?>