<?php
require_once '../config/config.php';
require_login();
require_once 'models/Order.php';
require_once '../models/Customer.php';
require_once '../models/Measurement.php';
require_once 'models/ClothType.php';
require_once 'models/User.php';
require_once '../helpers/SubscriptionHelper.php';
require_once '../helpers/NotificationService.php';
$orderModel = new Order();
$customerModel = new Customer();
$measurementModel = new Measurement();
$clothTypeModel = new ClothType();
$userModel = new User();
$notifService = new NotificationService();
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
                
                if (empty($_POST['assigned_tailor_id'])) {
                    $_SESSION['message'] = 'Please select an assigned tailor.';
                    $_SESSION['messageType'] = 'error';
                    header('Location: orders.php');
                    exit;
                }

                $totalAmount = (float)$_POST['total_amount'];
                $advanceAmount = isset($_POST['advance_amount']) ? (float)$_POST['advance_amount'] : 0.0;
                $balanceAmount = max(0.0, $totalAmount - $advanceAmount);
                $paymentMethod = !empty($_POST['payment_method']) ? sanitize_input($_POST['payment_method']) : 'cash';
                $customerPhone = !empty($_POST['customer_phone']) ? sanitize_input($_POST['customer_phone']) : null;

                $data = [
                    'customer_name' => sanitize_input($_POST['customer_id']),
                    'customer_phone' => $customerPhone,
                    'customer_id' => null,
                    'cloth_type_id' => (int)$_POST['cloth_type_id'],
                    'measurement_id' => !empty($_POST['measurement_id']) ? (int)$_POST['measurement_id'] : null,
                    'assigned_tailor_id' => (int)$_POST['assigned_tailor_id'],
                    'order_date' => $_POST['order_date'],
                    'due_date' => $_POST['due_date'],
                    'total_amount' => $totalAmount,
                    'advance_amount' => $advanceAmount,
                    'balance_amount' => $balanceAmount,
                    'payment_method' => $paymentMethod,
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
                        try {
                            $assignedTailorId = !empty($data['assigned_tailor_id']) ? (int)$data['assigned_tailor_id'] : null;
                            if ($assignedTailorId) {
                                $notifService->onOrderAssigned($orderId, $assignedTailorId, null);
                            }
                        } catch (Throwable $notifEx) {
                            error_log("Order create notification error: " . $notifEx->getMessage());
                        }
                        $_SESSION['message'] = 'Order created successfully';
                        $_SESSION['messageType'] = 'success';
                        $canSendInvoiceCheck = SubscriptionHelper::canSendInvoice($companyId)['allowed'];
                        if (!empty($_POST['send_whatsapp_invoice']) && $canSendInvoiceCheck) {
                            $cleanPhone = preg_replace('/\D/', '', $customerPhone ?? '');
                            if (strlen($cleanPhone) === 10) {
                                $cleanPhone = '91' . $cleanPhone;
                            }
                            $orderNum = $order['order_number'] ?? ('ORD' . $orderId);
                            $clothTypeName = '';
                            if (!empty($data['cloth_type_id'])) {
                                $ct = $clothTypeModel->find($data['cloth_type_id']);
                                $clothTypeName = $ct['name'] ?? '';
                            }
                            $waMsg = "*ORDER INVOICE / RECEIPT*\n";
                            $waMsg .= "--------------------------------\n";
                            $waMsg .= "📋 *Order #:* " . $orderNum . "\n";
                            $waMsg .= "👤 *Customer:* " . ($data['customer_name'] ?? 'Customer') . "\n";
                            if ($clothTypeName) $waMsg .= "👗 *Cloth Type:* " . $clothTypeName . "\n";
                            if (!empty($data['due_date'])) $waMsg .= "📅 *Due Date:* " . $data['due_date'] . "\n";
                            $waMsg .= "--------------------------------\n";
                            $waMsg .= "💰 *Total Amount:* ₹" . number_format($totalAmount, 2) . "\n";
                            $waMsg .= "✅ *Advance Paid:* ₹" . number_format($advanceAmount, 2) . " (" . strtoupper($paymentMethod) . ")\n";
                            $waMsg .= "⏳ *Balance Due:* ₹" . number_format($balanceAmount, 2) . "\n";
                            $waMsg .= "--------------------------------\n";
                            $waMsg .= "Thank you for choosing us! 🙏";

                            $_SESSION['auto_whatsapp'] = [
                                'phone' => $cleanPhone,
                                'customer_name' => $data['customer_name'] ?? 'Customer',
                                'order_number' => $orderNum,
                                'message' => $waMsg,
                                'wa_url' => 'https://wa.me/' . $cleanPhone . '?text=' . urlencode($waMsg)
                            ];
                        }
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
                $prevOrder = $orderModel->find($orderId);
                $prevTailorId = $prevOrder['assigned_tailor_id'] ?? null;

                if (empty($_POST['assigned_tailor_id'])) {
                    $_SESSION['message'] = 'Please select an assigned tailor.';
                    $_SESSION['messageType'] = 'error';
                    header('Location: orders.php');
                    exit;
                }

                $totalAmount = (float)$_POST['total_amount'];
                $advanceAmount = isset($_POST['advance_amount']) ? (float)$_POST['advance_amount'] : 0.0;
                $balanceAmount = max(0.0, $totalAmount - $advanceAmount);
                $paymentMethod = !empty($_POST['payment_method']) ? sanitize_input($_POST['payment_method']) : 'cash';
                $customerPhone = !empty($_POST['customer_phone']) ? sanitize_input($_POST['customer_phone']) : null;

                $data = [
                    'customer_name' => sanitize_input($_POST['customer_id']),
                    'customer_phone' => $customerPhone,
                    'customer_id' => null,
                    'cloth_type_id' => (int)$_POST['cloth_type_id'],
                    'measurement_id' => !empty($_POST['measurement_id']) ? (int)$_POST['measurement_id'] : null,
                    'assigned_tailor_id' => (int)$_POST['assigned_tailor_id'],
                    'order_date' => $_POST['order_date'],
                    'due_date' => $_POST['due_date'],
                    'total_amount' => $totalAmount,
                    'advance_amount' => $advanceAmount,
                    'balance_amount' => $balanceAmount,
                    'payment_method' => $paymentMethod,
                    'special_instructions' => sanitize_input($_POST['special_instructions'])
                ];
                
                if ($orderModel->update($orderId, $data)) {
                    try {
                        $newTailorId = $data['assigned_tailor_id'] ?? null;
                        if ($newTailorId && (int)$newTailorId !== (int)$prevTailorId) {
                            $notifService->onOrderAssigned($orderId, $newTailorId, $prevTailorId);
                        }
                    } catch (Throwable $notifEx) {
                        error_log("Order assignment notification error: " . $notifEx->getMessage());
                    }
                    $_SESSION['message'] = 'Order updated successfully';
                    $_SESSION['messageType'] = 'success';
                    $canSendInvoiceCheck = SubscriptionHelper::canSendInvoice($companyId)['allowed'];
                    if (!empty($_POST['send_whatsapp_invoice']) && $canSendInvoiceCheck) {
                        $cleanPhone = preg_replace('/\D/', '', $customerPhone ?? '');
                        if (strlen($cleanPhone) === 10) {
                            $cleanPhone = '91' . $cleanPhone;
                        }
                        $order = $orderModel->find($orderId);
                        $orderNum = $order['order_number'] ?? ('ORD' . $orderId);
                        $clothTypeName = '';
                        if (!empty($data['cloth_type_id'])) {
                            $ct = $clothTypeModel->find($data['cloth_type_id']);
                            $clothTypeName = $ct['name'] ?? '';
                        }
                        $waMsg = "*ORDER INVOICE / RECEIPT*\n";
                        $waMsg .= "--------------------------------\n";
                        $waMsg .= "📋 *Order #:* " . $orderNum . "\n";
                        $waMsg .= "👤 *Customer:* " . ($data['customer_name'] ?? 'Customer') . "\n";
                        if ($clothTypeName) $waMsg .= "👗 *Cloth Type:* " . $clothTypeName . "\n";
                        if (!empty($data['due_date'])) $waMsg .= "📅 *Due Date:* " . $data['due_date'] . "\n";
                        $waMsg .= "--------------------------------\n";
                        $waMsg .= "💰 *Total Amount:* ₹" . number_format($totalAmount, 2) . "\n";
                        $waMsg .= "✅ *Advance Paid:* ₹" . number_format($advanceAmount, 2) . " (" . strtoupper($paymentMethod) . ")\n";
                        $waMsg .= "⏳ *Balance Due:* ₹" . number_format($balanceAmount, 2) . "\n";
                        $waMsg .= "--------------------------------\n";
                        $waMsg .= "Thank you for choosing us! 🙏";

                        $_SESSION['auto_whatsapp'] = [
                            'phone' => $cleanPhone,
                            'customer_name' => $data['customer_name'] ?? 'Customer',
                            'order_number' => $orderNum,
                            'message' => $waMsg,
                            'wa_url' => 'https://wa.me/' . $cleanPhone . '?text=' . urlencode($waMsg)
                        ];
                    }
                } else {
                    $_SESSION['message'] = 'Failed to update order';
                    $_SESSION['messageType'] = 'error';
                }
                header('Location: orders.php');
                exit;
                break;
                
            case 'update_status':
                $orderId = (int)$_POST['order_id'];
                $status = sanitize_input($_POST['status'] ?? '');
                $reason = sanitize_input($_POST['cancellation_reason'] ?? $_POST['reason'] ?? '');

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
                    try {
                        if (has_role('tailor')) {
                            $tailorId = get_user_id();
                            $tailorName = get_user_name();
                            if ($status === 'in_progress') {
                                $notifService->onTailorStartedWork($orderId, $tailorId, $tailorName);
                            } elseif ($status === 'completed') {
                                $notifService->onTailorCompletedOrder($orderId, $tailorId, $tailorName);
                            } elseif ($status === 'cancelled') {
                                $notifService->onTailorCancelledOrder($orderId, $tailorId, $tailorName, $reason);
                            }
                        } else {
                            $notifService->onAdminUpdatedOrderStatus($orderId, $status, get_user_id(), $reason);
                        }
                    } catch (Throwable $notifEx) {
                        error_log("Status change notification error: " . $notifEx->getMessage());
                    }

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
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$cloth_type_id_filter = $_GET['cloth_type_id'] ?? '';
$customer_id_filter = $_GET['customer_id'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = RECORDS_PER_PAGE;
$offset = ($page - 1) * $limit;

$conditions = [];
if (!empty($status_filter)) {
    $conditions['status'] = $status_filter;
}
if (!empty($cloth_type_id_filter)) {
    $conditions['cloth_type_id'] = (int)$cloth_type_id_filter;
}
if (!empty($customer_id_filter)) {
    $conditions['customer_id'] = (int)$customer_id_filter;
}
if (has_role('tailor')) {
    $conditions['assigned_tailor_id'] = get_user_id();
}
$allOrders = $orderModel->getOrdersWithDetails($conditions, null, 0);

$filteredCustomerName = '';
if (!empty($customer_id_filter)) {
    $custObj = $customerModel->find((int)$customer_id_filter);
    if ($custObj) {
        $filteredCustomerName = trim(($custObj['first_name'] ?? '') . ' ' . ($custObj['last_name'] ?? ''));
    }
    if (empty($allOrders)) {
        $custOrders = $orderModel->getCustomerOrders((int)$customer_id_filter, $custObj);
        if (!empty($custOrders)) {
            $allOrders = $custOrders;
            if (!empty($status_filter)) {
                $allOrders = array_filter($allOrders, fn($o) => ($o['status'] ?? '') === $status_filter);
            }
            if (!empty($cloth_type_id_filter)) {
                $allOrders = array_filter($allOrders, fn($o) => (int)($o['cloth_type_id'] ?? 0) === (int)$cloth_type_id_filter);
            }
            $allOrders = array_values($allOrders);
        }
    }
}

if (!empty($search)) {
    $searchLower = strtolower(trim($search));
    $allOrders = array_filter($allOrders, function($order) use ($searchLower) {
        $displayCustName = !empty($order['measurement_customer_name']) 
            ? $order['measurement_customer_name'] 
            : trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
        return strpos(strtolower($order['order_number'] ?? ''), $searchLower) !== false ||
               strpos(strtolower($displayCustName), $searchLower) !== false ||
               strpos(strtolower($order['customer_phone'] ?? ''), $searchLower) !== false;
    });
    $allOrders = array_values($allOrders);
}

$totalOrders = count($allOrders);
$orders = array_slice($allOrders, $offset, $limit);
$totalPages = ceil($totalOrders / $limit);
$customers = $customerModel->findAll(['status' => 'active'], 'first_name, last_name');
$measurementCustomers = $measurementModel->getDistinctCustomerNames();
$clothTypes = $clothTypeModel->findAll(['status' => 'active'], 'name');
$tailors = $userModel->findAll(['role' => 'tailor', 'status' => 'active'], 'full_name');

$editOrder = null;
if (isset($_GET['edit'])) {
    $editOrder = $orderModel->find((int)$_GET['edit']);
}
$currentPlan = SubscriptionHelper::getCurrentPlan($companyId);
$orderLimitCheck = SubscriptionHelper::canAddOrder($companyId);
$canSendInvoice = SubscriptionHelper::canSendInvoice($companyId)['allowed'];
$autoWhatsApp = $_SESSION['auto_whatsapp'] ?? null;
unset($_SESSION['auto_whatsapp']);

$orderStats = $orderModel->getOrderStats();
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
.cat-action-print {
    background: #faf5ff;
    border-color: #ede9fe;
    color: #8b5cf6;
}
.cat-action-print:hover {
    background: #f3e8ff;
    color: #7c3aed;
}
.cat-action-whatsapp {
    background: #f0fdf4;
    border-color: #dcfce7;
    color: #16a34a;
}
.cat-action-whatsapp:hover {
    background: #dcfce7;
    color: #15803d;
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
<?php
$pageHeaderTitle = 'Orders';
$pageHeaderSubtitle = 'Track, manage and update customer tailoring orders.';
include 'includes/page-header.php';
?>
<div class="row g-2 mb-3">
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
                <option value="<?php echo $ct['id']; ?>" <?php echo ((string)$cloth_type_id_filter === (string)$ct['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($ct['name']); ?></option>
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

<?php 
$hasActiveFilter = (!empty($cloth_type_id_filter) || !empty($status_filter) || !empty($search) || !empty($customer_id_filter));
?>
<div id="filterResults" class="mb-3" style="<?php echo $hasActiveFilter ? 'display: block;' : 'display: none;'; ?>">
    <div id="filterAlertBox" class="alert <?php echo $totalOrders > 0 ? 'alert-info' : 'alert-warning'; ?> py-2 px-3 d-flex align-items-center justify-content-between" style="border-radius: 12px; font-size: 0.85rem;">
        <div class="d-flex align-items-center">
            <i id="filterAlertIcon" class="fas <?php echo $totalOrders > 0 ? 'fa-info-circle text-primary' : 'fa-search text-warning'; ?> me-2"></i>
            <span id="filterAlertMessage">
                <?php if ($totalOrders > 0): ?>
                    <strong id="filterCount"><?php echo $totalOrders; ?></strong> <?php echo $totalOrders === 1 ? 'order' : 'orders'; ?> found
                    <?php if (!empty($filteredCustomerName)): ?>
                        for customer <strong><?php echo htmlspecialchars($filteredCustomerName); ?></strong>
                    <?php else: ?>
                        matching your filter criteria
                    <?php endif; ?>
                <?php else: ?>
                    <strong>0 orders found<?php echo !empty($filteredCustomerName) ? ' for customer ' . htmlspecialchars($filteredCustomerName) : ''; ?>.</strong> Try adjusting your filter criteria.
                <?php endif; ?>
            </span>
        </div>
        <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 ms-3 text-secondary" id="btnDismissFilterBanner" title="Dismiss" onclick="document.getElementById('filterResults').style.display='none';">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>
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
                                    <div class="d-flex flex-column gap-1 mt-1" style="font-size: 0.72rem;">
                                        <div class="text-muted">
                                            Adv: <span class="text-success fw-semibold"><?php echo format_currency($order['advance_amount']); ?></span>
                                            <?php 
                                                $pm = strtolower($order['payment_method'] ?? 'cash');
                                                $pmBadgeClass = $pm === 'online' ? 'bg-primary text-white' : 'bg-light text-dark border';
                                                $pmLabel = $pm === 'online' ? 'Online' : ucfirst($pm);
                                            ?>
                                            <span class="badge <?php echo $pmBadgeClass; ?> ms-1" style="font-size: 0.65rem;"><?php echo htmlspecialchars($pmLabel); ?></span>
                                        </div>
                                        <?php 
                                            $pending = isset($order['balance_amount']) ? (float)$order['balance_amount'] : max(0.0, ((float)$order['total_amount'] - (float)$order['advance_amount']));
                                        ?>
                                        <?php if ($pending > 0): ?>
                                            <div class="text-danger fw-semibold">
                                                Pending: <?php echo format_currency($pending); ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-success fw-semibold">
                                                <i class="fas fa-check-circle me-1"></i>Fully Paid
                                            </div>
                                        <?php endif; ?>
                                    </div>
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
                                                'customer_phone' => $order['customer_phone'] ?? '',
                                                'cloth_type_id' => $order['cloth_type_id'],
                                                'measurement_id' => $order['measurement_id'],
                                                'assigned_tailor_id' => $order['assigned_tailor_id'],
                                                'order_date' => $order['order_date'],
                                                'due_date' => $order['due_date'],
                                                'total_amount' => $order['total_amount'],
                                                'advance_amount' => $order['advance_amount'],
                                                'balance_amount' => $order['balance_amount'] ?? 0,
                                                'payment_method' => $order['payment_method'] ?? 'cash',
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
                                    <button type="button" 
                                            class="cat-action-btn cat-action-print" 
                                            onclick="printOrderInvoice(<?php echo $order['id']; ?>)" 
                                            title="Print Invoice">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <?php if ($canSendInvoice): ?>
                                    <button type="button" 
                                            class="cat-action-btn cat-action-whatsapp" 
                                            onclick='openSendInvoiceModal(<?php echo htmlspecialchars(json_encode([
                                                "id" => $order["id"],
                                                "order_number" => $order["order_number"] ?? ("ORD" . $order["id"]),
                                                "customer_name" => $order["measurement_customer_name"] ?? $order["customer_name"] ?? "Customer",
                                                "customer_phone" => $order["customer_phone"] ?? "",
                                                "cloth_type_name" => $order["cloth_type_name"] ?? "",
                                                "total_amount" => (float)$order["total_amount"],
                                                "advance_amount" => (float)($order["advance_amount"] ?? 0),
                                                "balance_amount" => isset($order["balance_amount"]) ? (float)$order["balance_amount"] : max(0.0, (float)$order["total_amount"] - (float)($order["advance_amount"] ?? 0)),
                                                "payment_method" => $order["payment_method"] ?? "cash",
                                                "order_date" => $order["order_date"] ?? "",
                                                "due_date" => $order["due_date"] ?? ""
                                            ])); ?>)' 
                                            title="Send Invoice">
                                        <i class="fab fa-whatsapp"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div id="paginationContainer" class="p-3">
            <?php if ($totalPages > 1): ?>
                <?php 
                $filterQuery = ($status_filter ? '&status=' . urlencode($status_filter) : '') . 
                               ($cloth_type_id_filter ? '&cloth_type_id=' . urlencode($cloth_type_id_filter) : '') . 
                               ($customer_id_filter ? '&customer_id=' . urlencode($customer_id_filter) : '') . 
                               ($search ? '&search=' . urlencode($search) : '');
                ?>
                <nav aria-label="Order pagination">
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $filterQuery; ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $filterQuery; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $filterQuery; ?>">Next</a>
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
                    <?php if (!empty($search) || !empty($status_filter) || !empty($cloth_type_id_filter) || !empty($customer_id_filter)): ?>
                        No orders match your search criteria.
                    <?php else: ?>
                        Get started by creating your first order.
                    <?php endif; ?>
                </p>
                <?php if (!has_role('tailor') && empty($search) && empty($status_filter) && empty($cloth_type_id_filter) && empty($customer_id_filter)): ?>
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
                        <div class="col-md-4 mb-3">
                            <label for="customer_id" class="form-label">Customer *</label>
                            <select class="form-select" id="customer_id" name="customer_id" required>
                                <option value="">Select Customer</option>
                                <?php foreach ($measurementCustomers as $mc): ?>
                                    <option value="<?php echo htmlspecialchars($mc['customer_name']); ?>" data-phone="<?php echo htmlspecialchars($mc['phone_number'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($mc['customer_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="customer_phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="customer_phone" name="customer_phone" placeholder="Enter phone number" maxlength="15">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="measurement_id" class="form-label">Measurement</label>
                            <select class="form-select" id="measurement_id" name="measurement_id">
                                <option value="">No Measurement</option>
                            </select>
                        </div>
                    </div>
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
                                </div>
                                <div id="previewMeasurementNotes" class="mt-2 pt-2 border-top text-muted small" style="display: none;">
                                    <i class="fas fa-sticky-note text-warning me-1"></i><strong>Notes:</strong> <span id="previewNotesText"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="assigned_tailor_id" class="form-label">Assigned Tailor *</label>
                            <select class="form-select" id="assigned_tailor_id" name="assigned_tailor_id" required>
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
                        <div class="col-md-4 mb-3">
                            <label for="total_amount" class="form-label">Total Amount *</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" class="form-control" id="total_amount" name="total_amount" step="0.01" min="0" placeholder="0.00" required>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="advance_amount" class="form-label">Advance Amount *</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" class="form-control" id="advance_amount" name="advance_amount" step="0.01" min="0" value="0" placeholder="0.00" required>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="pending_amount" class="form-label">Pending Amount</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-danger fw-bold" id="pending_currency_symbol">₹</span>
                                <input type="number" class="form-control bg-light fw-bold text-danger" id="pending_amount" name="pending_amount" step="0.01" value="0.00" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div id="advance_warning" class="alert alert-warning py-1 px-2 mb-3 small" style="display: none; font-size: 0.8rem;">
                        <i class="fas fa-exclamation-triangle me-1"></i>Advance amount cannot exceed Total Amount.
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="payment_method" class="form-label">Payment Method *</label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="cash" selected>Cash</option>
                                <option value="online">Online / UPI</option>
                                <option value="card">Card</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Payment Summary</label>
                            <div class="p-2 border rounded bg-light d-flex align-items-center justify-content-between" style="min-height: 38px; font-size: 0.82rem;">
                                <span><span class="text-muted">Status:</span> <strong id="payment_status_text" class="text-secondary">Enter amounts</strong></span>
                                <span id="payment_summary_badge" class="badge bg-secondary">Draft</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="special_instructions" class="form-label">Special Instructions</label>
                        <textarea class="form-control" id="special_instructions" name="special_instructions" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between align-items-center">
                    <div>
                        <?php if ($canSendInvoice): ?>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="send_whatsapp_invoice" name="send_whatsapp_invoice" value="1" checked style="cursor: pointer;">
                            <label class="form-check-label fw-semibold text-success small" for="send_whatsapp_invoice" style="cursor: pointer;">
                                <i class="fab fa-whatsapp me-1"></i>Send Invoice on WhatsApp
                            </label>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="saveOrderBtn">
                            <i class="fas fa-save me-2"></i>Save Order
                        </button>
                    </div>
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
                    <div class="mb-3" id="orderStatusCancellationReasonGroup" style="display: none;">
                        <label for="order_status_cancellation_reason" class="form-label text-danger">Cancellation Reason</label>
                        <textarea class="form-control" id="order_status_cancellation_reason" name="cancellation_reason" rows="2" placeholder="Explain reason for cancellation..."></textarea>
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

<?php if ($autoWhatsApp): ?>
<div class="modal fade" id="autoWhatsAppModal" tabindex="-1" aria-labelledby="autoWhatsAppModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content border-0 shadow-lg text-center" style="border-radius: 20px; overflow: hidden;">
            <div class="p-4" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;">
                <div class="rounded-circle bg-white text-success d-inline-flex align-items-center justify-content-center mb-3 shadow" style="width: 70px; height: 70px; font-size: 2.2rem;">
                    <i class="fab fa-whatsapp"></i>
                </div>
                <h4 class="fw-bold mb-1">Order Saved!</h4>
                <p class="mb-0 opacity-90" style="font-size: 0.88rem;">Invoice receipt is ready to send</p>
            </div>
            <div class="p-4 bg-white">
                <div class="p-3 mb-3 rounded-3 text-start" style="background: #f8fafc; border: 1px solid #e2e8f0; font-size: 0.85rem;">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Order #:</span>
                        <strong class="text-primary"><?php echo htmlspecialchars($autoWhatsApp['order_number']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Customer:</span>
                        <strong><?php echo htmlspecialchars($autoWhatsApp['customer_name']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">WhatsApp:</span>
                        <strong class="<?php echo !empty($autoWhatsApp['phone']) ? 'text-success' : 'text-danger'; ?>">
                            <?php echo !empty($autoWhatsApp['phone']) ? ('+' . htmlspecialchars($autoWhatsApp['phone'])) : 'No phone entered'; ?>
                        </strong>
                    </div>
                </div>

                <?php if (!empty($autoWhatsApp['phone'])): ?>
                <a id="btnTriggerAutoWhatsApp" href="<?php echo htmlspecialchars($autoWhatsApp['wa_url']); ?>" target="_blank" class="btn btn-success btn-lg w-100 rounded-pill py-2 fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2 mb-2" style="background-color: #16a34a; border-color: #16a34a;" onclick="const m = bootstrap.Modal.getInstance(document.getElementById('autoWhatsAppModal')); if(m) m.hide();">
                    <i class="fab fa-whatsapp fs-5"></i>
                    <span>Send on WhatsApp</span>
                </a>
                <div class="text-muted small" style="font-size: 0.78rem;">
                    <i class="fas fa-magic me-1 text-success"></i>Opening WhatsApp... Tap button above if not opened
                </div>
                <?php else: ?>
                <div class="alert alert-warning py-2 px-3 small text-start mb-3" style="font-size: 0.82rem;">
                    <i class="fas fa-exclamation-triangle me-1"></i>Phone number was not provided for this order. You can use the "Send Invoice" button in the order list after adding their number.
                </div>
                <?php endif; ?>

                <div class="mt-3 pt-2 border-top">
                    <button type="button" class="btn btn-link text-muted text-decoration-none small" data-bs-dismiss="modal">
                        Close / Done
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="sendInvoiceModal" tabindex="-1" aria-labelledby="sendInvoiceModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 44px; height: 44px; background: #dcfce7; color: #16a34a; font-size: 1.25rem;">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0">Send Invoice Receipt</h5>
                        <small class="text-muted">Order <span id="sendInvoiceOrderNum" class="fw-semibold text-primary"></span></small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <!-- Customer Details -->
                <div class="row g-2 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold text-muted mb-1">Customer Name</label>
                        <input type="text" class="form-control form-control-sm bg-light" id="sendInvoiceCustomerName" readonly>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold text-muted mb-1">WhatsApp / Phone *</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted">+91</span>
                            <input type="text" class="form-control form-control-sm" id="sendInvoicePhone" placeholder="10-digit number" maxlength="15" oninput="generateInvoiceMessagePreview()">
                        </div>
                    </div>
                </div>

                <!-- Order Summary Card -->
                <div class="p-3 mb-3 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0; font-size: 0.85rem;">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Cloth Type:</span>
                        <strong id="sendInvoiceClothType" class="text-dark"></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Due Date:</span>
                        <strong id="sendInvoiceDueDate" class="text-dark"></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Total Amount:</span>
                        <strong id="sendInvoiceTotal" class="text-dark"></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Advance Paid:</span>
                        <strong id="sendInvoiceAdvance" class="text-success"></strong>
                    </div>
                    <div class="d-flex justify-content-between pt-1 border-top">
                        <span class="fw-bold text-dark">Pending Balance:</span>
                        <strong id="sendInvoiceBalance" class="text-danger fw-bold"></strong>
                    </div>
                </div>

                <!-- Message Preview -->
                <div class="mb-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold text-muted mb-0">Message Preview</label>
                        <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" id="copyInvoiceMsgBtn" onclick="copyInvoiceMessage()" style="font-size: 0.78rem;">
                            <i class="far fa-copy me-1"></i>Copy Text
                        </button>
                    </div>
                    <textarea class="form-control font-monospace" id="sendInvoiceMessage" rows="6" style="font-size: 0.8rem; resize: vertical; background: #fff;"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 pb-4 px-4 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-3 py-2 btn-sm" onclick="printOrderInvoiceFromModal()">
                    <i class="fas fa-print me-1"></i>Print Invoice
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light rounded-pill px-3 py-2 btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success rounded-pill px-4 py-2 btn-sm fw-semibold shadow-sm" onclick="sendInvoiceViaWhatsApp()" style="background-color: #16a34a; border-color: #16a34a;">
                        <i class="fab fa-whatsapp me-2"></i>Send on WhatsApp
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
function calculatePendingAmount() {
    const totalEl = document.getElementById('total_amount');
    const advanceEl = document.getElementById('advance_amount');
    const pendingEl = document.getElementById('pending_amount');
    const symbolEl = document.getElementById('pending_currency_symbol');
    const warningEl = document.getElementById('advance_warning');
    const statusTextEl = document.getElementById('payment_status_text');
    const summaryBadgeEl = document.getElementById('payment_summary_badge');
    
    if (!totalEl || !advanceEl || !pendingEl) return;
    
    const total = parseFloat(totalEl.value) || 0;
    const advance = parseFloat(advanceEl.value) || 0;
    
    if (advance > total && total > 0) {
        if (warningEl) {
            warningEl.style.display = 'block';
            warningEl.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Advance amount (₹' + advance.toFixed(2) + ') cannot exceed Total amount (₹' + total.toFixed(2) + ').';
        }
    } else {
        if (warningEl) {
            warningEl.style.display = 'none';
        }
    }
    
    const pending = Math.max(0, total - advance);
    pendingEl.value = pending.toFixed(2);
    
    if (pending > 0) {
        pendingEl.classList.remove('text-success');
        pendingEl.classList.add('text-danger');
        if (symbolEl) {
            symbolEl.classList.remove('text-success');
            symbolEl.classList.add('text-danger');
        }
    } else if (total > 0 && advance >= total) {
        pendingEl.classList.remove('text-danger');
        pendingEl.classList.add('text-success');
        if (symbolEl) {
            symbolEl.classList.remove('text-danger');
            symbolEl.classList.add('text-success');
        }
    } else {
        pendingEl.classList.remove('text-success', 'text-danger');
        if (symbolEl) {
            symbolEl.classList.remove('text-success', 'text-danger');
        }
    }
    
    if (statusTextEl && summaryBadgeEl) {
        if (total <= 0) {
            statusTextEl.textContent = 'Enter total amount';
            statusTextEl.className = 'text-muted';
            summaryBadgeEl.className = 'badge bg-secondary';
            summaryBadgeEl.textContent = 'Draft';
        } else if (advance <= 0) {
            statusTextEl.textContent = 'Full pending: ₹' + pending.toFixed(2);
            statusTextEl.className = 'text-danger fw-semibold';
            summaryBadgeEl.className = 'badge bg-danger';
            summaryBadgeEl.textContent = 'Unpaid';
        } else if (advance >= total) {
            statusTextEl.textContent = 'Fully paid (₹' + total.toFixed(2) + ')';
            statusTextEl.className = 'text-success fw-semibold';
            summaryBadgeEl.className = 'badge bg-success';
            summaryBadgeEl.textContent = 'Fully Paid';
        } else {
            statusTextEl.textContent = 'Adv: ₹' + advance.toFixed(2) + ' | Pending: ₹' + pending.toFixed(2);
            statusTextEl.className = 'text-warning text-dark fw-semibold';
            summaryBadgeEl.className = 'badge bg-warning text-dark';
            summaryBadgeEl.textContent = 'Partial';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($autoWhatsApp): ?>
    const autoModalEl = document.getElementById('autoWhatsAppModal');
    if (autoModalEl) {
        const autoModal = new bootstrap.Modal(autoModalEl);
        autoModal.show();
        <?php if (!empty($autoWhatsApp['phone'])): ?>
        try {
            const waUrl = <?php echo json_encode($autoWhatsApp['wa_url']); ?>;
            window.open(waUrl, '_blank');
        } catch(e) {}
        <?php endif; ?>
    }
    <?php endif; ?>
    const orderModalStatusSelect = document.querySelector('#statusModal select[name="status"]');
    if (orderModalStatusSelect) {
        orderModalStatusSelect.addEventListener('change', function() {
            const group = document.getElementById('orderStatusCancellationReasonGroup');
            if (group) {
                group.style.display = this.value === 'cancelled' ? 'block' : 'none';
            }
        });
    }

    const totalAmountInput = document.getElementById('total_amount');
    if (totalAmountInput) {
        totalAmountInput.addEventListener('input', calculatePendingAmount);
        totalAmountInput.addEventListener('change', calculatePendingAmount);
    }
    const advanceAmountInput = document.getElementById('advance_amount');
    if (advanceAmountInput) {
        advanceAmountInput.addEventListener('input', calculatePendingAmount);
        advanceAmountInput.addEventListener('change', calculatePendingAmount);
    }

    const orderFormEl = document.getElementById('orderForm');
    if (orderFormEl) {
        orderFormEl.addEventListener('submit', function(e) {
            const total = parseFloat(document.getElementById('total_amount')?.value || 0);
            const advance = parseFloat(document.getElementById('advance_amount')?.value || 0);
            if (advance < 0) {
                e.preventDefault();
                alert('Advance amount cannot be negative.');
                return false;
            }
            if (advance > total && total > 0) {
                e.preventDefault();
                alert('Advance amount (₹' + advance.toFixed(2) + ') cannot be greater than Total amount (₹' + total.toFixed(2) + ').');
                return false;
            }
        });
    }
});

function editOrder(order) {
    console.log('Editing order:', order);
    const waSwitch = document.getElementById('send_whatsapp_invoice');
    if (waSwitch) waSwitch.checked = false; 
    
    document.getElementById('orderModalTitle').textContent = 'Edit Order';
    document.getElementById('orderAction').value = 'update';
    document.getElementById('orderId').value = order.id || '';
    const customerName = order.customer_name || '';
    const clothTypeId = order.cloth_type_id ? String(order.cloth_type_id) : '';
    const assignedTailorId = order.assigned_tailor_id ? String(order.assigned_tailor_id) : '';
    document.getElementById('customer_id').value = customerName;
    document.getElementById('cloth_type_id').value = clothTypeId;
    document.getElementById('assigned_tailor_id').value = assignedTailorId;
    document.getElementById('order_date').value = order.order_date || '';
    document.getElementById('due_date').value = order.due_date || '';
    if (document.getElementById('customer_phone')) {
        document.getElementById('customer_phone').value = order.customer_phone || '';
    }
    document.getElementById('total_amount').value = order.total_amount || '';
    document.getElementById('advance_amount').value = (order.advance_amount !== undefined && order.advance_amount !== null) ? order.advance_amount : '0';
    if (document.getElementById('payment_method')) {
        document.getElementById('payment_method').value = order.payment_method || 'cash';
    }
    document.getElementById('special_instructions').value = order.special_instructions || '';
    calculatePendingAmount();
    
    console.log('Set values:', { customerName, clothTypeId, assignedTailorId }); // Debug log
        const measurementSelect = document.getElementById('measurement_id');
    
    if (customerName) {
        measurementSelect.innerHTML = '<option value="">Loading measurements...</option>';
        
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
    new bootstrap.Modal(document.getElementById('orderModal')).show();
}

function updateOrderStatusAjax(orderId, status, clickedElement) {
    
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    const originalText = clickedElement.innerHTML;
    clickedElement.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
    clickedElement.style.pointerEvents = 'none';
        const row = clickedElement.closest('tr');
    const statusCell = row.children[6];
    const statusBadge = statusCell.querySelector('.badge');
    const originalBadgeHtml = statusBadge.innerHTML;
    const originalStatus = row.dataset.status || getCurrentStatusFromBadge(statusBadge);
    
    statusBadge.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Updating';
    statusBadge.className = 'badge rounded-pill fw-semibold px-2 py-1 bg-secondary text-white';
    
    const formData = new FormData();
    formData.append('csrf_token', '<?php echo generate_csrf_token(); ?>');
    formData.append('order_id', orderId);
    formData.append('status', status);
    
    fetch('ajax/update_order_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
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
            updateOrderStatistics(originalStatus, status);
            
            showToast('Success', data.message, 'success');
            
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
            statusBadge.innerHTML = originalBadgeHtml;
            
            showToast('Error', data.error || 'Failed to update status', 'error');
        }
    })
    .catch(error => {
        console.error('Error updating status:', error);
        statusBadge.innerHTML = originalBadgeHtml;
        showToast('Error', 'Network error occurred', 'error');
    })
    .finally(() => {
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
    if (oldStatus === newStatus) return;
    const statElements = {
        'pending': document.getElementById('stat-pending'),
        'in_progress': document.getElementById('stat-in_progress'),
        'completed': document.getElementById('stat-completed'),
        'delivered': document.getElementById('stat-delivered')
    };
        if (oldStatus && statElements[oldStatus]) {
        const oldCount = parseInt(statElements[oldStatus].textContent.replace(/,/g, ''));
        if (oldCount > 0) {
            statElements[oldStatus].textContent = formatNumber(oldCount - 1);
        }
    }
        if (newStatus && statElements[newStatus]) {
        const newCount = parseInt(statElements[newStatus].textContent.replace(/,/g, ''));
        statElements[newStatus].textContent = formatNumber(newCount + 1);
    }
}

function formatNumber(num) {
    return num.toLocaleString('en-US');
}

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
    const toast = new bootstrap.Toast(toastElement, {
        autohide: true,
        delay: 3000
    });
    toast.show();
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

function viewOrder(orderId) {
    window.location.href = 'order-details.php?id=' + orderId;
}
const clothTypeSelectEl = document.getElementById('cloth_type_id');
if (clothTypeSelectEl) {
    clothTypeSelectEl.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const rate = selectedOption ? selectedOption.getAttribute('data-rate') : null;
        if (rate && rate > 0) {
            const totalAmountEl = document.getElementById('total_amount');
            if (totalAmountEl) {
                totalAmountEl.value = rate;
                calculatePendingAmount();
            }
        }
    });
}
window.currentCustomerMeasurements = [];
const customerSelectEl = document.getElementById('customer_id');
if (customerSelectEl) {
    customerSelectEl.addEventListener('change', function() {
        const customerName = this.value;
        const measurementSelect = document.getElementById('measurement_id');
        if (!measurementSelect) return;
        hideMeasurementPreview();
        
        // Auto-fill customer phone number
        const selectedOpt = this.options[this.selectedIndex];
        const optPhone = selectedOpt ? selectedOpt.getAttribute('data-phone') : '';
        const phoneInput = document.getElementById('customer_phone');
        if (phoneInput && optPhone) {
            phoneInput.value = optPhone;
        }
        
        if (customerName) {
            measurementSelect.innerHTML = '<option value="">Loading measurements...</option>';
            
            fetch(`ajax/get_measurements_by_name.php?name=${encodeURIComponent(customerName)}`)
                .then(response => response.json())
                .then(data => {
                    if (phoneInput && !phoneInput.value && data && data.length > 0 && data[0].phone_number) {
                        phoneInput.value = data[0].phone_number;
                    }
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
function displaySelectedMeasurement() {
    const measurementSelect = document.getElementById('measurement_id');
    const previewContainer = document.getElementById('measurementDetailsPreview');
    if (!measurementSelect || !previewContainer) return;
    
    const measurementId = measurementSelect.value;
    if (!measurementId) {
        hideMeasurementPreview();
        return;
    }
    let measurement = (window.currentCustomerMeasurements || []).find(m => String(m.id) === String(measurementId));
    
    if (measurement && measurement.measurement_data) {
        renderMeasurementPreview(measurement);
    } else {
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
        const phoneEl = document.getElementById('customer_phone');
        if (phoneEl) phoneEl.value = '';
        const waSwitch = document.getElementById('send_whatsapp_invoice');
        if (waSwitch) waSwitch.checked = true;
        const advanceEl = document.getElementById('advance_amount');
        if (advanceEl) advanceEl.value = '0';
        const paymentMethodEl = document.getElementById('payment_method');
        if (paymentMethodEl) paymentMethodEl.value = 'cash';
        hideMeasurementPreview();
        window.currentCustomerMeasurements = [];
        calculatePendingAmount();
    });
}
let filterTimeout;
const searchInput = document.getElementById('searchInput');
const statusFilter = document.getElementById('statusFilter');
const clothTypeFilter = document.getElementById('clothTypeFilter');
const tailorFilter = document.getElementById('tailorFilter');
const startDateFilter = document.getElementById('startDateFilter');
const endDateFilter = document.getElementById('endDateFilter');
const clearFilters = document.getElementById('clearFilters');
const filterResults = document.getElementById('filterResults');
const filterAlertBox = document.getElementById('filterAlertBox');
const filterAlertIcon = document.getElementById('filterAlertIcon');
const filterAlertMessage = document.getElementById('filterAlertMessage');
const filterCount = document.getElementById('filterCount');
const ordersTable = document.getElementById('ordersTableBody') || document.querySelector('.table tbody');
const originalTableContent = ordersTable ? ordersTable.innerHTML : '';
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
                }
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response text:', text);
               
            }
        })
        .catch(error => {
            console.error('Error loading filter options:', error);
        });
}

function populateFilterOptions(options) {
    const clothTypeSelect = document.getElementById('clothTypeFilter');
    if (clothTypeSelect && options.cloth_types) {
        const currentClothType = clothTypeSelect.value;
        clothTypeSelect.innerHTML = '<option value="">All Cloth Types</option>';
        options.cloth_types.forEach(clothType => {
            const isSel = String(clothType.id) === String(currentClothType);
            clothTypeSelect.innerHTML += `<option value="${clothType.id}" ${isSel ? 'selected' : ''}>${clothType.name}</option>`;
        });
        if (currentClothType) {
            clothTypeSelect.value = currentClothType;
        }
    }
    const tailorSelect = document.getElementById('tailorFilter');
    if (tailorSelect && options.tailors) {
        const currentTailor = tailorSelect.value;
        tailorSelect.innerHTML = '<option value="">All Tailors</option>';
        options.tailors.forEach(tailor => {
            const isSel = String(tailor.id) === String(currentTailor);
            tailorSelect.innerHTML += `<option value="${tailor.id}" ${isSel ? 'selected' : ''}>${tailor.name}</option>`;
        });
        if (currentTailor) {
            tailorSelect.value = currentTailor;
        }
    }
}
[searchInput, statusFilter, clothTypeFilter, tailorFilter, startDateFilter, endDateFilter].forEach(element => {
    if (!element) return;
    element.addEventListener('change', performFilter);
    if (element === searchInput) {
        element.addEventListener('input', performFilter);
    }
});

if (clearFilters) {
    clearFilters.addEventListener('click', function() {
        window.location.href = 'orders.php';
    });
}

function performFilter() {
    clearTimeout(filterTimeout);
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
    const urlParams = new URLSearchParams(window.location.search);
    const customerIdParam = urlParams.get('customer_id');
    const hasActiveFilters = Boolean(search || status || clothType || tailor || startDate || endDate || customerIdParam);
    
    if (hasActiveFilters) {
        filterResults.style.display = 'block';
        if (filterAlertBox) {
            filterAlertBox.className = 'alert alert-info py-2 px-3 d-flex align-items-center justify-content-between';
        }
        if (filterAlertIcon) {
            filterAlertIcon.className = 'fas fa-spinner fa-spin me-2 text-primary';
        }
        if (filterAlertMessage) {
            filterAlertMessage.innerHTML = 'Filtering orders...';
        } else if (filterCount) {
            filterCount.textContent = 'Filtering...';
        }
    } else {
        filterResults.style.display = 'none';
    }
    
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (status) params.append('status', status);
    if (clothType) params.append('cloth_type_id', clothType);
    if (tailor) params.append('assigned_tailor_id', tailor);
    if (startDate) params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);
    if (customerIdParam) params.append('customer_id', customerIdParam);
    params.append('page', '1');
    params.append('limit', '<?php echo RECORDS_PER_PAGE; ?>');
    
    fetch(`ajax/filter_orders.php?${params.toString()}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text(); 
        })
        .then(text => {
            if (text.trim().startsWith('<')) {
                throw new Error('Server returned HTML instead of JSON. Check for PHP errors.');
            }
            
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    displayFilterResults(data.orders);
                    const totalOrders = data.pagination.total_orders;
                    if (filterCount) filterCount.textContent = totalOrders;
                    
                    if (hasActiveFilters) {
                        filterResults.style.display = 'block';
                        if (totalOrders > 0) {
                            if (filterAlertBox) {
                                filterAlertBox.className = 'alert alert-info py-2 px-3 d-flex align-items-center justify-content-between';
                            }
                            if (filterAlertIcon) {
                                filterAlertIcon.className = 'fas fa-info-circle me-2 text-primary';
                            }
                            if (filterAlertMessage) {
                                const orderLabel = totalOrders === 1 ? 'order' : 'orders';
                                filterAlertMessage.innerHTML = `<strong id="filterCount">${totalOrders}</strong> ${orderLabel} found matching your filter criteria`;
                            }
                        } else {
                            if (filterAlertBox) {
                                filterAlertBox.className = 'alert alert-warning py-2 px-3 d-flex align-items-center justify-content-between';
                            }
                            if (filterAlertIcon) {
                                filterAlertIcon.className = 'fas fa-search me-2 text-warning';
                            }
                            if (filterAlertMessage) {
                                filterAlertMessage.innerHTML = `<strong>0 orders found.</strong> Try adjusting your filter criteria.`;
                            }
                        }
                    } else {
                        filterResults.style.display = 'none';
                    }
                    
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
                    showFilterError(data.error || 'Unable to filter orders');
                }
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response text:', text);
                showFilterError('Invalid response from server');
            }
        })
        .catch(error => {
            console.error('Filter error:', error);
            showFilterError(error.message);
        });
}

function showFilterError(errorMessage) {
    if (filterResults) filterResults.style.display = 'block';
    if (filterAlertBox) {
        filterAlertBox.className = 'alert alert-danger py-2 px-3 d-flex align-items-center justify-content-between';
    }
    if (filterAlertIcon) {
        filterAlertIcon.className = 'fas fa-exclamation-triangle me-2 text-danger';
    }
    if (filterAlertMessage) {
        filterAlertMessage.innerHTML = `<strong>Filter error:</strong> ${errorMessage || 'Unable to load orders'}. Please try again or clear filters.`;
    } else if (filterCount) {
        filterCount.textContent = 'Error';
    }
    if (ordersTable) {
        ordersTable.innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-5">
                    <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; border-radius: 50%; background: #fef2f2; color: #ef4444;">
                        <i class="fas fa-exclamation-circle fa-2x"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">Error filtering orders</h5>
                    <p class="text-muted small mb-0">Please clear your filters and try again.</p>
                </td>
            </tr>
        `;
    }
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
                        <div class="d-flex flex-column gap-1 mt-1" style="font-size: 0.72rem;">
                            <div class="text-muted">
                                Adv: <span class="text-success fw-semibold">${formatCurrency(order.advance_amount || 0)}</span>
                                <span class="badge ${((order.payment_method || 'cash').toLowerCase() === 'online') ? 'bg-primary text-white' : 'bg-light text-dark border'} ms-1" style="font-size: 0.65rem;">
                                    ${escapeHtml((order.payment_method || 'cash').toLowerCase() === 'online' ? 'Online' : ((order.payment_method || 'cash').charAt(0).toUpperCase() + (order.payment_method || 'cash').slice(1)))}
                                </span>
                            </div>
                            ${(order.balance_amount !== undefined ? parseFloat(order.balance_amount) : Math.max(0, (parseFloat(order.total_amount) || 0) - (parseFloat(order.advance_amount) || 0))) > 0 ? `
                                <div class="text-danger fw-semibold">
                                    Pending: ${formatCurrency(order.balance_amount !== undefined ? parseFloat(order.balance_amount) : Math.max(0, (parseFloat(order.total_amount) || 0) - (parseFloat(order.advance_amount) || 0)))}
                                </div>
                            ` : `
                                <div class="text-success fw-semibold">
                                    <i class="fas fa-check-circle me-1"></i>Fully Paid
                                </div>
                            `}
                        </div>
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
                                    customer_phone: order.customer_phone || '',
                                    cloth_type_id: order.cloth_type_id,
                                    measurement_id: order.measurement_id,
                                    assigned_tailor_id: order.assigned_tailor_id,
                                    order_date: order.order_date,
                                    due_date: order.due_date,
                                    total_amount: order.total_amount,
                                    advance_amount: order.advance_amount,
                                    balance_amount: order.balance_amount,
                                    payment_method: order.payment_method || 'cash',
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
                        <button type="button" 
                                class="cat-action-btn cat-action-print" 
                                onclick="printOrderInvoice(${order.id})" 
                                title="Print Invoice">
                            <i class="fas fa-print"></i>
                        </button>
                        ${canSendInvoice ? `
                        <button type="button" 
                                class="cat-action-btn cat-action-whatsapp send-order-invoice-btn" 
                                data-order='${JSON.stringify({
                                    id: order.id,
                                    order_number: order.order_number || ('ORD' + order.id),
                                    customer_name: order.display_customer_name || order.customer_name || 'Customer',
                                    customer_phone: order.customer_phone || '',
                                    cloth_type_name: order.cloth_type_name || '',
                                    total_amount: order.total_amount,
                                    advance_amount: order.advance_amount,
                                    balance_amount: order.balance_amount,
                                    payment_method: order.payment_method || 'cash',
                                    order_date: order.order_date,
                                    due_date: order.due_date
                                }).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;')}'
                                title="Send Invoice">
                            <i class="fab fa-whatsapp"></i>
                        </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    });
    
    ordersTable.innerHTML = tableHTML;
    ordersTable.querySelectorAll('.edit-order-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            try {
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
    ordersTable.querySelectorAll('.send-order-invoice-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            try {
                const orderJson = this.getAttribute('data-order')
                    .replace(/&quot;/g, '"')
                    .replace(/&#39;/g, "'")
                    .replace(/&lt;/g, '<')
                    .replace(/&gt;/g, '>')
                    .replace(/&amp;/g, '&');
                const order = JSON.parse(orderJson);
                openSendInvoiceModal(order);
            } catch (e) {
                console.error('Error parsing order data:', e);
            }
        });
    });
}
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
document.addEventListener('shown.bs.dropdown', function(e) {
    const toggle = e.target;
    const dropdown = toggle.closest('.dropdown');
    
    if (!dropdown || !dropdown.closest('.table td')) return;
    
    const menu = dropdown.querySelector('.dropdown-menu');
    if (!menu) return;
    setTimeout(function() {
        const toggleRect = toggle.getBoundingClientRect();
        const menuRect = menu.getBoundingClientRect();
        const viewportHeight = window.innerHeight;
        const viewportWidth = window.innerWidth;
        menu.style.position = 'fixed';
        menu.style.zIndex = '9999';
        menu.style.pointerEvents = 'auto';
        menu.style.minWidth = '150px';
        menu.style.visibility = 'visible';
        menu.style.opacity = '1';
        let top = toggleRect.bottom;
        let left = toggleRect.left;
        if (top + menuRect.height > viewportHeight - 10) {
            top = toggleRect.top - menuRect.height;
            if (top < 10) {
                top = viewportHeight - menuRect.height - 10;
            }
        }
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

const canSendInvoice = <?php echo $canSendInvoice ? 'true' : 'false'; ?>;

// Print Invoice directly
function printOrderInvoice(orderId) {
    if (!orderId) return;
    window.open('print-invoice.php?order_id=' + orderId, '_blank');
}

// Global order variable for Send Invoice modal
window.currentSendInvoiceOrder = null;

function openSendInvoiceModal(order) {
    if (!order) return;
    if (!canSendInvoice) {
        alert('Send invoice feature is not available in the Free plan. Please upgrade your subscription to Basic or Premium plan.');
        window.location.href = 'subscriptions.php';
        return;
    }
    window.currentSendInvoiceOrder = order;
    
    const modalEl = document.getElementById('sendInvoiceModal');
    if (!modalEl) return;
    
    const orderNum = order.order_number || ('ORD' + order.id);
    document.getElementById('sendInvoiceOrderNum').textContent = '#' + orderNum;
    document.getElementById('sendInvoiceCustomerName').value = order.customer_name || 'Customer';
    
    const phoneInput = document.getElementById('sendInvoicePhone');
    if (phoneInput) {
        let rawPhone = String(order.customer_phone || '').replace(/\D/g, '');
        if (rawPhone.length > 10 && rawPhone.startsWith('91')) {
            rawPhone = rawPhone.substring(2);
        }
        phoneInput.value = rawPhone;
    }
    
    document.getElementById('sendInvoiceClothType').textContent = order.cloth_type_name || 'Tailoring Item';
    document.getElementById('sendInvoiceDueDate').textContent = order.due_date || 'N/A';
    document.getElementById('sendInvoiceTotal').textContent = '₹' + parseFloat(order.total_amount || 0).toFixed(2);
    document.getElementById('sendInvoiceAdvance').textContent = '₹' + parseFloat(order.advance_amount || 0).toFixed(2);
    
    const balance = order.balance_amount !== undefined ? parseFloat(order.balance_amount) : Math.max(0, parseFloat(order.total_amount || 0) - parseFloat(order.advance_amount || 0));
    document.getElementById('sendInvoiceBalance').textContent = '₹' + balance.toFixed(2);
    
    generateInvoiceMessagePreview();
    
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
}

function generateInvoiceMessagePreview() {
    const order = window.currentSendInvoiceOrder;
    if (!order) return;
    
    const custName = document.getElementById('sendInvoiceCustomerName')?.value || order.customer_name || 'Customer';
    const orderNum = order.order_number || ('ORD' + order.id);
    const clothType = order.cloth_type_name || 'Tailoring Item';
    const total = parseFloat(order.total_amount || 0).toFixed(2);
    const advance = parseFloat(order.advance_amount || 0).toFixed(2);
    const balance = (order.balance_amount !== undefined ? parseFloat(order.balance_amount) : Math.max(0, parseFloat(order.total_amount || 0) - parseFloat(order.advance_amount || 0))).toFixed(2);
    const dueDate = order.due_date || '';
    const paymentMethod = (order.payment_method || 'Cash').toUpperCase();
    
    let msg = `*ORDER INVOICE / RECEIPT*\n`;
    msg += `--------------------------------\n`;
    msg += `📋 *Order #:* ${orderNum}\n`;
    msg += `👤 *Customer:* ${custName}\n`;
    msg += `👗 *Cloth Type:* ${clothType}\n`;
    if (dueDate) msg += `📅 *Due Date:* ${dueDate}\n`;
    msg += `--------------------------------\n`;
    msg += `💰 *Total Amount:* ₹${total}\n`;
    msg += `✅ *Advance Paid:* ₹${advance} (${paymentMethod})\n`;
    msg += `⏳ *Balance Due:* ₹${balance}\n`;
    msg += `--------------------------------\n`;
    msg += `Thank you for choosing us! 🙏`;
    
    const msgPreviewEl = document.getElementById('sendInvoiceMessage');
    if (msgPreviewEl) {
        msgPreviewEl.value = msg;
    }
}

function sendInvoiceViaWhatsApp() {
    const phoneInput = document.getElementById('sendInvoicePhone');
    let phone = phoneInput ? phoneInput.value.trim() : '';
    phone = phone.replace(/\D/g, '');
    
    if (!phone) {
        alert('Please enter a valid 10-digit WhatsApp phone number.');
        phoneInput?.focus();
        return;
    }
    
    if (phone.length === 10) {
        phone = '91' + phone;
    }
    
    const msg = document.getElementById('sendInvoiceMessage')?.value || '';
    const url = `https://wa.me/${phone}?text=${encodeURIComponent(msg)}`;
    window.open(url, '_blank');
}

function copyInvoiceMessage() {
    const msgEl = document.getElementById('sendInvoiceMessage');
    if (!msgEl) return;
    
    navigator.clipboard.writeText(msgEl.value).then(() => {
        const btn = document.getElementById('copyInvoiceMsgBtn');
        if (btn) {
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
            setTimeout(() => { btn.innerHTML = orig; }, 2000);
        }
    }).catch(err => {
        msgEl.select();
        document.execCommand('copy');
        alert('Message copied to clipboard!');
    });
}

function printOrderInvoiceFromModal() {
    if (window.currentSendInvoiceOrder && window.currentSendInvoiceOrder.id) {
        printOrderInvoice(window.currentSendInvoiceOrder.id);
    }
}

</script>
<?php require_once 'includes/footer.php'; ?>
<style>
    .btn-outline-info:hover {
        color: #ffffff;
    }
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
    .content-area {
        overflow: visible !important;
    }
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