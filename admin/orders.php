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
require_once 'models/Company.php';
$companyModel = new Company();
$message = '';
$messageType = '';
$companyId = get_company_id();
$companyInfo = $companyId ? $companyModel->find($companyId) : null;
$companyName = !empty($companyInfo['company_name']) ? $companyInfo['company_name'] : '';
$clothTypes = $clothTypeModel->findAll(['status' => 'active'], 'name');
$tailors = $userModel->findAll(['role' => 'tailor', 'status' => 'active'], 'full_name');
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
                
                $items = $_POST['items'] ?? [];
                $cleanItems = [];
                $primaryClothTypeId = !empty($_POST['cloth_type_id']) ? (int)$_POST['cloth_type_id'] : null;
                $primaryMeasurementId = !empty($_POST['measurement_id']) ? (int)$_POST['measurement_id'] : null;
                $primaryTailorId = !empty($_POST['assigned_tailor_id']) ? (int)$_POST['assigned_tailor_id'] : null;
                $calculatedTotal = 0.0;

                if (!empty($items) && is_array($items)) {
                    foreach ($items as $it) {
                        $cId = !empty($it['cloth_type_id']) ? (int)$it['cloth_type_id'] : null;
                        if (!$cId) continue;
                        $mId = !empty($it['measurement_id']) ? (int)$it['measurement_id'] : null;
                        $tId = !empty($it['assigned_tailor_id']) ? (int)$it['assigned_tailor_id'] : null;
                        $p = isset($it['price']) ? (float)$it['price'] : 0.0;
                        $q = !empty($it['quantity']) ? (int)$it['quantity'] : 1;
                        if (!$primaryClothTypeId) {
                            $primaryClothTypeId = $cId;
                            $primaryMeasurementId = $mId;
                            $primaryTailorId = $tId;
                        }
                        $calculatedTotal += ($p * $q);
                        $cleanItems[] = [
                            'cloth_type_id' => $cId,
                            'measurement_id' => $mId,
                            'assigned_tailor_id' => $tId,
                            'price' => $p,
                            'quantity' => $q,
                            'status' => 'pending',
                            'notes' => sanitize_input($it['notes'] ?? '')
                        ];
                    }
                }

                if (empty($primaryTailorId)) {
                    $_SESSION['message'] = 'Please select an assigned tailor for all garments.';
                    $_SESSION['messageType'] = 'error';
                    header('Location: orders.php');
                    exit;
                }

                $totalAmount = ($calculatedTotal > 0) ? $calculatedTotal : ((isset($_POST['total_amount']) && (float)$_POST['total_amount'] > 0) ? (float)$_POST['total_amount'] : 0.0);
                $advanceAmount = isset($_POST['advance_amount']) ? (float)$_POST['advance_amount'] : 0.0;
                $balanceAmount = max(0.0, $totalAmount - $advanceAmount);
                $paymentMethod = !empty($_POST['payment_method']) ? sanitize_input($_POST['payment_method']) : 'cash';
                $customerPhone = !empty($_POST['customer_phone']) ? trim(sanitize_input($_POST['customer_phone'])) : '';
                if ($customerPhone === '') {
                    $_SESSION['message'] = 'Please enter a phone number.';
                    $_SESSION['messageType'] = 'error';
                    header('Location: orders.php');
                    exit;
                }

                $data = [
                    'customer_name' => sanitize_input($_POST['customer_id']),
                    'customer_phone' => $customerPhone,
                    'customer_id' => null,
                    'cloth_type_id' => $primaryClothTypeId ?: (int)($_POST['cloth_type_id'] ?? 0),
                    'measurement_id' => $primaryMeasurementId,
                    'assigned_tailor_id' => $primaryTailorId,
                    'order_date' => $_POST['order_date'],
                    'due_date' => $_POST['due_date'],
                    'total_amount' => $totalAmount,
                    'advance_amount' => $advanceAmount,
                    'balance_amount' => $balanceAmount,
                    'payment_method' => $paymentMethod,
                    'special_instructions' => sanitize_input($_POST['special_instructions'] ?? ''),
                    'created_by' => $userId
                ];
                if ($companyId && !isset($data['company_id'])) {
                    $data['company_id'] = $companyId;
                }
                
                try {
                    $orderId = $orderModel->createOrder($data);
                    if ($orderId) {
                        if (!empty($cleanItems)) {
                            $orderModel->saveOrderItems($orderId, $cleanItems);
                        } else {
                            $orderModel->saveOrderItems($orderId, [[
                                'cloth_type_id' => $data['cloth_type_id'],
                                'measurement_id' => $data['measurement_id'],
                                'assigned_tailor_id' => $data['assigned_tailor_id'],
                                'price' => $data['total_amount'],
                                'quantity' => 1,
                                'status' => 'pending'
                            ]]);
                        }

                        $order = $orderModel->find($orderId);
                        require_once '../helpers/GA4Helper.php';
                        $_SESSION['ga4_event'] = GA4Helper::trackCreateOrder(
                            $orderId,
                            $order['order_number'] ?? null,
                            $data['total_amount'] ?? null
                        );
                        try {
                            $notifiedTailors = [];
                            foreach ($cleanItems as $ci) {
                                $tid = $ci['assigned_tailor_id'];
                                if ($tid && !in_array($tid, $notifiedTailors)) {
                                    $notifiedTailors[] = $tid;
                                    $notifService->onOrderAssigned($orderId, $tid, null);
                                }
                            }
                            if (empty($notifiedTailors) && !empty($data['assigned_tailor_id'])) {
                                $notifService->onOrderAssigned($orderId, (int)$data['assigned_tailor_id'], null);
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
                            $waMsg = "";
                            if (!empty($companyName)) {
                                $cleanComp = strtoupper(trim($companyName));
                                $padComp = max(0, (int)floor((55 - (strlen($cleanComp) + 2)) / 2));
                                $waMsg .= str_repeat(" ", $padComp) . "*" . $cleanComp . "*\n";
                            }
                            $waMsg .= "*Order Receipt*\n";
                            $waMsg .= "--------------------------------\n";
                            $waMsg .= "📋 *Order #:* " . $orderNum . "\n";
                            $waMsg .= "👤 *Customer:* " . ($data['customer_name'] ?? 'Customer') . "\n";
                            if (!empty($cleanItems)) {
                                $waMsg .= "👗 *Items :*\n";
                                foreach ($cleanItems as $ci) {
                                    $ctName = '';
                                    if (!empty($clothTypes) && is_array($clothTypes)) {
                                        foreach ($clothTypes as $ct) {
                                            if ($ct['id'] == $ci['cloth_type_id']) {
                                                $ctName = $ct['name'];
                                                break;
                                            }
                                        }
                                    }
                                    if (!$ctName && !empty($ci['cloth_type_id'])) {
                                        $foundCt = $clothTypeModel->find($ci['cloth_type_id']);
                                        if ($foundCt && !empty($foundCt['name'])) {
                                            $ctName = $foundCt['name'];
                                        }
                                    }
                                    $waMsg .= "• " . ($ctName ?: 'Garment') . ": ₹" . number_format($ci['price'], 2) . "\n";
                                }
                            } elseif (!empty($data['cloth_type_id'])) {
                                $ct = $clothTypeModel->find($data['cloth_type_id']);
                                if (!empty($ct['name'])) $waMsg .= "👗 *Cloth Type:* " . $ct['name'] . "\n";
                            }
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
                $items = $_POST['items'] ?? [];
                $cleanItems = [];
                $primaryClothTypeId = !empty($_POST['cloth_type_id']) ? (int)$_POST['cloth_type_id'] : null;
                $primaryMeasurementId = !empty($_POST['measurement_id']) ? (int)$_POST['measurement_id'] : null;
                $primaryTailorId = !empty($_POST['assigned_tailor_id']) ? (int)$_POST['assigned_tailor_id'] : null;
                $calculatedTotal = 0.0;

                if (!empty($items) && is_array($items)) {
                    foreach ($items as $it) {
                        $cId = !empty($it['cloth_type_id']) ? (int)$it['cloth_type_id'] : null;
                        if (!$cId) continue;
                        $mId = !empty($it['measurement_id']) ? (int)$it['measurement_id'] : null;
                        $tId = !empty($it['assigned_tailor_id']) ? (int)$it['assigned_tailor_id'] : null;
                        $p = isset($it['price']) ? (float)$it['price'] : 0.0;
                        $q = !empty($it['quantity']) ? (int)$it['quantity'] : 1;
                        if (!$primaryClothTypeId) {
                            $primaryClothTypeId = $cId;
                            $primaryMeasurementId = $mId;
                            $primaryTailorId = $tId;
                        }
                        $calculatedTotal += ($p * $q);
                        $cleanItems[] = [
                            'cloth_type_id' => $cId,
                            'measurement_id' => $mId,
                            'assigned_tailor_id' => $tId,
                            'price' => $p,
                            'quantity' => $q,
                            'status' => $it['status'] ?? 'pending',
                            'notes' => sanitize_input($it['notes'] ?? '')
                        ];
                    }
                }

                $totalAmount = ($calculatedTotal > 0) ? $calculatedTotal : ((isset($_POST['total_amount']) && (float)$_POST['total_amount'] > 0) ? (float)$_POST['total_amount'] : 0.0);
                $advanceAmount = isset($_POST['advance_amount']) ? (float)$_POST['advance_amount'] : 0.0;
                $balanceAmount = max(0.0, $totalAmount - $advanceAmount);
                $paymentMethod = !empty($_POST['payment_method']) ? sanitize_input($_POST['payment_method']) : 'cash';
                $customerPhone = !empty($_POST['customer_phone']) ? trim(sanitize_input($_POST['customer_phone'])) : '';
                if ($customerPhone === '') {
                    $_SESSION['message'] = 'Please enter a phone number.';
                    $_SESSION['messageType'] = 'error';
                    header('Location: orders.php');
                    exit;
                }

                $data = [
                    'customer_name' => sanitize_input($_POST['customer_id']),
                    'customer_phone' => $customerPhone,
                    'customer_id' => null,
                    'cloth_type_id' => $primaryClothTypeId ?: (int)($_POST['cloth_type_id'] ?? 0),
                    'measurement_id' => $primaryMeasurementId,
                    'assigned_tailor_id' => $primaryTailorId ?: (int)($_POST['assigned_tailor_id'] ?? 0),
                    'order_date' => $_POST['order_date'],
                    'due_date' => $_POST['due_date'],
                    'total_amount' => $totalAmount,
                    'advance_amount' => $advanceAmount,
                    'balance_amount' => $balanceAmount,
                    'payment_method' => $paymentMethod,
                    'special_instructions' => sanitize_input($_POST['special_instructions'] ?? '')
                ];
                
                if ($orderModel->update($orderId, $data)) {
                    if (!empty($cleanItems)) {
                        $orderModel->saveOrderItems($orderId, $cleanItems);
                    }
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
                        $waMsg = "";
                        if (!empty($companyName)) {
                            $cleanComp = strtoupper(trim($companyName));
                            $padComp = max(0, (int)floor((55 - (strlen($cleanComp) + 2)) / 2));
                            $waMsg .= str_repeat(" ", $padComp) . "*" . $cleanComp . "*\n";
                        }
                        $waMsg .= "*Order Receipt*\n";
                        $waMsg .= "--------------------------------\n";
                        $waMsg .= "📋 *Order #:* " . $orderNum . "\n";
                        $waMsg .= "👤 *Customer:* " . ($data['customer_name'] ?? 'Customer') . "\n";
                        if (!empty($cleanItems)) {
                            $waMsg .= "👗 *Items:*\n";
                            foreach ($cleanItems as $ci) {
                                $ctName = '';
                                if (!empty($clothTypes) && is_array($clothTypes)) {
                                    foreach ($clothTypes as $ct) {
                                        if ($ct['id'] == $ci['cloth_type_id']) {
                                            $ctName = $ct['name'];
                                            break;
                                        }
                                    }
                                }
                                if (!$ctName && !empty($ci['cloth_type_id'])) {
                                    $foundCt = $clothTypeModel->find($ci['cloth_type_id']);
                                    if ($foundCt && !empty($foundCt['name'])) {
                                        $ctName = $foundCt['name'];
                                    }
                                }
                                $waMsg .= "• " . ($ctName ?: 'Garment') . ": ₹" . number_format($ci['price'], 2) . "\n";
                            }
                        } elseif (!empty($data['cloth_type_id'])) {
                            $ct = $clothTypeModel->find($data['cloth_type_id']);
                            if (!empty($ct['name'])) $waMsg .= "👗 *Cloth Type:* " . $ct['name'] . "\n";
                        }
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
        if (empty($filteredCustomerName) && !empty($custObj['name'])) {
            $filteredCustomerName = trim($custObj['name']);
        }
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
            : (!empty($order['customer_name']) ? $order['customer_name'] : trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')));
        $clothNames = ($order['all_cloth_type_names'] ?? '') . ' ' . ($order['cloth_type_name'] ?? '');
        return strpos(strtolower($order['order_number'] ?? ''), $searchLower) !== false ||
               strpos(strtolower($displayCustName), $searchLower) !== false ||
               strpos(strtolower($clothNames), $searchLower) !== false ||
               strpos(strtolower($order['customer_phone'] ?? ''), $searchLower) !== false;
    });
    $allOrders = array_values($allOrders);
}

$totalOrders = count($allOrders);
$orders = array_slice($allOrders, $offset, $limit);
$totalPages = max(1, ceil($totalOrders / $limit));
$customers = $customerModel->findAll(['status' => 'active'], 'first_name, last_name');
$measurementCustomers = $measurementModel->getDistinctCustomerNames();
$clothTypes = $clothTypeModel->findAll(['status' => 'active'], 'name');
$tailors = $userModel->findAll(['role' => 'tailor', 'status' => 'active'], 'full_name');

$editOrder = null;
if (isset($_GET['edit'])) {
    $editOrder = $orderModel->find((int)$_GET['edit']);
}
try {
    $currentPlan = SubscriptionHelper::getCurrentPlan($companyId);
    $orderLimitCheck = SubscriptionHelper::canAddOrder($companyId);
    $canGenerateInvoice = !has_role('tailor') && (SubscriptionHelper::canGenerateInvoice($companyId)['allowed'] ?? false);
    $canSendInvoice = !has_role('tailor') && (SubscriptionHelper::canSendInvoice($companyId)['allowed'] ?? false);
} catch (Throwable $planEx) {
    error_log("Subscription check error on orders page: " . $planEx->getMessage());
    $currentPlan = 'free';
    $orderLimitCheck = ['allowed' => true];
    $canGenerateInvoice = !has_role('tailor');
    $canSendInvoice = !has_role('tailor');
}
$autoWhatsApp = !has_role('tailor') ? ($_SESSION['auto_whatsapp'] ?? null) : null;
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
@keyframes orderRowPulse {
    0% { background-color: rgba(59, 130, 246, 0.25); }
    50% { background-color: rgba(59, 130, 246, 0.10); }
    100% { background-color: transparent; }
}
@keyframes orderRowNewPulse {
    0% { background-color: rgba(34, 197, 94, 0.35); }
    50% { background-color: rgba(34, 197, 94, 0.12); }
    100% { background-color: transparent; }
}
.order-row-highlight {
    animation: orderRowPulse 2.5s ease-out;
}
.order-row-new-highlight {
    animation: orderRowNewPulse 3.5s ease-out;
}
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
.cat-action-pay {
    background: #ecfdf5;
    border-color: #a7f3d0;
    color: #059669;
}
.cat-action-pay:hover {
    background: #d1fae5;
    color: #047857;
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(16, 185, 129, 0.25);
}
.cat-action-paid {
    background: #f0fdf4;
    border-color: #dcfce7;
    color: #16a34a;
    cursor: default;
    display: inline-flex;
    align-items: center;
    justify-content: center;
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
        width: 32px;
        height: 32px;
        font-size: 0.78rem;
    }
}
/* Orders Main Listing Card */
.orders-main-card {
    border-radius: 14px;
    border: 1px solid #f1f5f9 !important;
    background-color: #ffffff;
}
@media (max-width: 767.98px) {
    .orders-main-card {
        background: transparent !important;
        background-color: transparent !important;
        border: none !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        margin-bottom: 0 !important;
    }
    .orders-main-card > .card-body {
        background: transparent !important;
        background-color: transparent !important;
        padding: 0 !important;
    }
    #ordersMobileCardsList {
        padding: 0 !important;
    }
    #paginationContainer {
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
}
.order-mobile-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 14px 15px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    transition: all 0.2s ease;
    position: relative;
}
.order-mobile-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}
.order-mobile-card .cat-action-btn {
    width: 34px;
    height: 34px;
    font-size: 0.82rem;
    border-radius: 8px;
}
@media (max-width: 575.98px) {
    .order-mobile-card {
        padding: 12px;
    }
    .order-mobile-card .cat-action-btn {
        width: 32px;
        height: 32px;
        font-size: 0.78rem;
    }
}
#topClothTypeSelect,
.form-select:disabled,
select:disabled {
    background-repeat: no-repeat, no-repeat !important;
    background-position: right 0.875rem center, center !important;
    background-size: 16px 12px, 100% 100% !important;
}
.measurement-search-container {
    position: relative;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    min-height: 42px;
    padding: 4px 38px 4px 10px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    background: #ffffff;
    cursor: text;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.measurement-search-container:focus-within {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}
.measurement-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #4f46e5;
    color: #ffffff;
    font-size: 0.8rem;
    font-weight: 500;
    padding: 3px 8px;
    border-radius: 6px;
    line-height: 1.2;
    user-select: none;
}
.measurement-tag .tag-remove-btn {
    cursor: pointer;
    font-size: 0.85rem;
    line-height: 1;
    opacity: 0.85;
    transition: opacity 0.15s;
}
.measurement-tag .tag-remove-btn:hover {
    opacity: 1;
}
.measurement-search-input {
    border: none !important;
    outline: none !important;
    box-shadow: none !important;
    font-size: 0.85rem;
    flex: 1;
    min-width: 80px;
    padding: 4px 4px;
    background: transparent;
}
.measurement-search-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    font-size: 0.9rem;
    pointer-events: none;
}
.measurement-dropdown-menu {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1055;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    max-height: 220px;
    overflow-y: auto;
    margin-top: 4px;
    display: none;
}
.measurement-dropdown-item {
    padding: 9px 14px;
    cursor: pointer;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #f3f4f6;
    transition: background 0.15s;
}
.measurement-dropdown-item:last-child {
    border-bottom: none;
}
.measurement-dropdown-item:hover {
    background: #f8fafc;
}
.measurement-dropdown-item.is-selected {
    background: #eef2ff;
    color: #4338ca;
}
.customer-select-box {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 42px;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    background: #ffffff;
    cursor: pointer;
    user-select: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.customer-select-box:hover {
    border-color: #9ca3af;
}
.customer-select-box:focus,
.customer-select-box.is-focused {
    outline: none;
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}
.customer-select-box.is-invalid {
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15) !important;
}
.customer-dropdown-menu {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1060;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.12), 0 8px 10px -6px rgba(0, 0, 0, 0.08);
    margin-top: 4px;
    display: none;
    overflow: hidden;
}
.customer-dropdown-item {
    padding: 8px 14px;
    cursor: pointer;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #f3f4f6;
    transition: background 0.12s;
}
.customer-dropdown-item:last-child {
    border-bottom: none;
}
.customer-dropdown-item:hover {
    background: #f1f5f9;
}
.customer-dropdown-item.is-selected {
    background: #eef2ff;
    color: #4338ca;
    font-weight: 600;
}
.customer-clear-btn {
    font-size: 0.82rem;
    color: #9ca3af;
    line-height: 1;
    margin-right: 4px;
    transition: color 0.15s;
}
.customer-clear-btn:hover {
    color: #ef4444;
}
.order-item-card {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    overflow: hidden;
    transition: border-color 0.15s;
}
.order-item-card:hover {
    border-color: #cbd5e1;
}
.order-item-header {
    display: grid;
    grid-template-columns: minmax(0, 1.45fr) minmax(0, 1fr) 28px 28px;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    background: #ffffff;
}
.item-cloth-type-select,
.item-tailor-select {
    width: 100% !important;
    min-width: 0 !important;
    max-width: 100% !important;
}
.item-collapse-btn,
.item-delete-btn {
    width: 28px;
    height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
@media (max-width: 575.98px) {
    .order-item-header {
        display: flex;
        flex-wrap: wrap;
    }
    .item-cloth-type-select {
        flex: 1 1 100%;
    }
    .item-tailor-select {
        flex: 1 1 140px;
    }
    .item-collapse-btn {
        margin-left: auto;
    }
}
.order-item-body {
    padding: 12px 14px;
    border-top: 1px solid #f1f5f9;
    background: #fafbfe;
}
.item-qty-group {
    width: 102px;
    border-radius: 6px;
    overflow: hidden;
}
.item-qty-group .btn {
    border-color: #ced4da;
    background: #ffffff;
    color: #334155;
    font-weight: 700;
    line-height: 1;
    padding: 0.25rem 0.5rem;
}
.item-qty-group .btn:hover {
    background: #eef2ff;
    color: #4f46e5;
    border-color: #c7d2fe;
}
.item-qty-input::-webkit-outer-spin-button,
.item-qty-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.item-qty-input {
    -moz-appearance: textfield;
}
.measurement-pills-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}
@media (max-width: 768px) {
    .measurement-pills-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
.measurement-pill-item {
    background: #f1f3f9;
    border-radius: 10px;
    padding: 8px 12px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.measurement-pill-label {
    font-size: 0.72rem;
    color: #64748b;
    font-weight: 500;
    margin-bottom: 2px;
    text-transform: capitalize;
}
.measurement-pill-value {
    font-size: 0.92rem;
    color: #1e293b;
    font-weight: 600;
    word-break: break-word;
    display: flex;
    align-items: center;
    gap: 6px;
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
<?php if (isset($orderLimitCheck['remaining']) && $orderLimitCheck['remaining'] <= 5 && $orderLimitCheck['remaining'] > 0): ?>
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
    <?php if (!$orderLimitCheck['allowed']): ?>
    <button type="button" class="btn text-white fw-semibold d-inline-flex align-items-center justify-content-center gap-1 text-nowrap px-4 shadow-sm flex-shrink-0" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 14px; font-size: 0.84rem; height: 42px; border: none; opacity: 0.65;" data-bs-toggle="modal" data-bs-target="#orderLimitUpgradeModal" title="Order limit reached. Click to upgrade your plan.">
        <i class="fas fa-plus"></i>
        <span>New Order</span>
    </button>
    <?php else: ?>
    <button type="button" class="btn text-white fw-semibold d-inline-flex align-items-center justify-content-center gap-1 text-nowrap px-4 shadow-sm flex-shrink-0" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 14px; font-size: 0.84rem; height: 42px; border: none; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.28);" data-bs-toggle="modal" data-bs-target="#orderModal">
        <i class="fas fa-plus"></i>
        <span>New Order</span>
    </button>
    <?php endif; ?>
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
<div class="card shadow-sm border-0 mb-3 orders-main-card">
    <div class="card-body p-0">
        <?php if (!empty($orders)): ?>
            <div class="table-responsive d-none d-md-block">
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
                            <?php if (!has_role('tailor')): ?>
                            <th class="py-3">TAILOR</th>
                            <?php endif; ?>
                            <th class="py-3">ORDER DATE</th>
                            <th class="py-3">DUE DATE</th>
                            <th class="py-3">STATUS</th>
                            <th class="py-3">AMOUNT</th>
                            <th class="py-3 text-center">QUANTITY</th>
                            <th class="pe-3 py-3 text-end">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTableBody">
                        <?php foreach ($orders as $index => $order): 
                            $rawCustName = !empty($order['measurement_customer_name']) 
                                ? $order['measurement_customer_name'] 
                                : (!empty($order['customer_name']) ? $order['customer_name'] : trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')));
                            $custName = display_val($rawCustName);
                            $initial = strtoupper(mb_substr(trim($custName), 0, 1) ?: 'C');
                            $avColor = getCustomerAvatarDesign($custName, $index);
                            $isOverdue = $order['due_date'] < date('Y-m-d') && !in_array($order['status'], ['completed', 'delivered', 'cancelled']);
                        ?>
                        <tr class="order-row" id="orderRow_<?php echo $order['id']; ?>" data-status="<?php echo htmlspecialchars($order['status']); ?>" data-customer="<?php echo htmlspecialchars($custName); ?>">
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
                                <?php 
                                    $itemCount = (int)($order['item_count'] ?? 0);
                                    $displayCloth = !empty($order['all_cloth_type_names']) ? $order['all_cloth_type_names'] : display_val($order['cloth_type_name']);
                                ?>
                                <div class="d-inline-flex align-items-center gap-1" style="max-width: 235px;">
                                    <span class="badge fw-medium" title="<?php echo htmlspecialchars($displayCloth); ?>" style="background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; font-size: 0.78rem; border-radius: 6px; padding: 4px 8px; display: inline-block; max-width: 170px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: middle;">
                                        <?php echo htmlspecialchars($displayCloth); ?>
                                    </span>
                                    <?php if ($itemCount > 1): ?>
                                        <span class="badge rounded-pill bg-light text-primary border flex-shrink-0" style="font-size: 0.68rem; white-space: nowrap;"><?php echo $itemCount; ?> Items</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php if (!has_role('tailor')): ?>
                            <td class="py-3">
                                <?php 
                                    $displayTailor = !empty($order['all_tailor_names']) ? $order['all_tailor_names'] : display_val($order['tailor_name']);
                                ?>
                                <?php if (!empty($displayTailor)): ?>
                                    <div class="d-flex align-items-center gap-1 text-dark" style="font-size: 0.84rem;">
                                        <i class="fas fa-user-tie text-muted" style="font-size: 0.75rem;"></i>
                                        <span><?php echo htmlspecialchars($displayTailor); ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 0.8rem; font-style: italic;">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
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
                            <td class="py-3" id="orderAmountCell_<?php echo $order['id']; ?>">
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
                            <?php $orderQty = max(1, (int)(($order['total_quantity'] ?? 0) > 0 ? $order['total_quantity'] : ($order['item_count'] ?? 1))); ?>
                            <td class="py-3 text-center">
                                <span class="badge rounded-pill fw-semibold px-2 py-1" style="background: #eef2ff; color: #4f46e5; border: 1px solid #c7d2fe; font-size: 0.78rem;">
                                    <?php echo $orderQty; ?>
                                </span>
                            </td>
                            <td class="pe-3 py-3 text-end">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <?php if (!has_role('tailor')): ?>
                                        <?php if ($pending > 0): ?>
                                            <button type="button" 
                                                    class="cat-action-btn cat-action-pay quick-pay-btn" 
                                                    id="quickPayBtn_<?php echo $order['id']; ?>"
                                                    onclick="openQuickPayModal(<?php echo htmlspecialchars(json_encode([
                                                        'id' => $order['id'],
                                                        'order_number' => $order['order_number'] ?? ('ORD' . $order['id']),
                                                        'customer_name' => $order['measurement_customer_name'] ?? $order['customer_name'] ?? $custName,
                                                        'total_amount' => (float)$order['total_amount'],
                                                        'advance_amount' => (float)$order['advance_amount'],
                                                        'balance_amount' => (float)$pending,
                                                        'payment_method' => $order['payment_method'] ?? 'cash'
                                                    ])); ?>)" 
                                                    title="Mark as Fully Paid (Pending: <?php echo format_currency($pending); ?>)">
                                                <i class="fas fa-rupee-sign"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="cat-action-btn cat-action-paid" 
                                                  id="quickPayBtn_<?php echo $order['id']; ?>"
                                                  title="Fully Paid (<?php echo format_currency($order['total_amount']); ?>)">
                                                <i class="fas fa-check text-success"></i>
                                            </span>
                                        <?php endif; ?>
                                    <button type="button" 
                                            class="cat-action-btn cat-action-edit" 
                                            id="editOrderBtn_<?php echo $order['id']; ?>"
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
                                    <div class="dropdown d-inline-block">
                                        <button class="cat-action-btn cat-action-status dropdown-toggle" 
                                                type="button" 
                                                data-bs-toggle="dropdown"
                                                data-bs-popper-config='{"strategy":"fixed"}'
                                                aria-expanded="false"
                                                title="Change Status">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" onclick="event.stopPropagation();" style="border-radius: 12px; border: 1px solid #e2e8f0; font-size: 0.85rem; z-index: 1060;">
                                            <li><h6 class="dropdown-header text-uppercase text-muted" style="font-size: 0.68rem; letter-spacing: 0.5px;">Update Status</h6></li>
                                            <li><a class="dropdown-item py-1" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(<?php echo $order['id']; ?>, 'pending', this); return false;"><i class="fas fa-clock text-warning me-2"></i>Pending</a></li>
                                            <li><a class="dropdown-item py-1" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(<?php echo $order['id']; ?>, 'in_progress', this); return false;"><i class="fas fa-cogs text-primary me-2"></i>In Progress</a></li>
                                            <li><a class="dropdown-item py-1" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(<?php echo $order['id']; ?>, 'completed', this); return false;"><i class="fas fa-check-circle text-success me-2"></i>Completed</a></li>
                                            <li><a class="dropdown-item py-1" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(<?php echo $order['id']; ?>, 'delivered', this); return false;"><i class="fas fa-truck text-purple me-2" style="color: #8b5cf6;"></i>Delivered</a></li>
                                            <li><hr class="dropdown-divider my-1"></li>
                                            <li><a class="dropdown-item py-1 text-danger" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(<?php echo $order['id']; ?>, 'cancelled', this); return false;"><i class="fas fa-times-circle me-2"></i>Cancelled</a></li>
                                        </ul>
                                    </div>
                                    <?php if ($canGenerateInvoice): ?>
                                    <button type="button" 
                                            class="cat-action-btn cat-action-print" 
                                            onclick="printOrderInvoice(<?php echo $order['id']; ?>)" 
                                            title="Print Invoice">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <?php endif; ?>
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
            <div class="d-md-none d-flex flex-column gap-3 mb-3" id="ordersMobileCardsList">
                <?php foreach ($orders as $index => $order): 
                    $rawCustName = !empty($order['measurement_customer_name']) 
                        ? $order['measurement_customer_name'] 
                        : (!empty($order['customer_name']) ? $order['customer_name'] : trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')));
                    $custName = display_val($rawCustName);
                    $initial = strtoupper(mb_substr(trim($custName), 0, 1) ?: 'C');
                    $avColor = getCustomerAvatarDesign($custName, $index);
                    $isOverdue = $order['due_date'] < date('Y-m-d') && !in_array($order['status'], ['completed', 'delivered', 'cancelled']);
                    $itemCount = (int)($order['item_count'] ?? 0);
                    $displayCloth = !empty($order['all_cloth_type_names']) ? $order['all_cloth_type_names'] : display_val($order['cloth_type_name']);
                    $displayTailor = !empty($order['all_tailor_names']) ? $order['all_tailor_names'] : display_val($order['tailor_name']);
                    $pending = isset($order['balance_amount']) ? (float)$order['balance_amount'] : max(0.0, ((float)$order['total_amount'] - (float)$order['advance_amount']));
                    $pm = strtolower($order['payment_method'] ?? 'cash');
                    $pmBadgeClass = $pm === 'online' ? 'bg-primary text-white' : 'bg-light text-dark border';
                    $pmLabel = $pm === 'online' ? 'Online' : ucfirst($pm);
                ?>
                <div class="order-mobile-card shadow-sm" id="orderCard_<?php echo $order['id']; ?>" data-status="<?php echo htmlspecialchars($order['status']); ?>" data-customer="<?php echo htmlspecialchars($custName); ?>">
                   
                    <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                        <span class="badge fw-semibold" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; font-size: 0.8rem; padding: 5px 9px; border-radius: 8px;">
                            #<?php echo htmlspecialchars($order['order_number']); ?>
                        </span>
                        <span class="badge rounded-pill fw-semibold px-2 py-1 cat-badge-<?php echo htmlspecialchars($order['status']); ?>" style="font-size: 0.75rem;">
                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                        </span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <div class="d-flex align-items-center justify-content-center flex-shrink-0 fw-bold" style="width: 38px; height: 38px; border-radius: 10px; background: <?php echo $avColor['bg']; ?>; border: 1px solid <?php echo $avColor['border']; ?>; color: <?php echo $avColor['color']; ?>; font-size: 0.9rem;">
                                <?php echo $initial; ?>
                            </div>
                            <div class="min-w-0">
                                <div class="fw-bold text-dark text-truncate" style="font-size: 0.9rem;">
                                    <?php echo htmlspecialchars($custName); ?>
                                </div>
                                <?php if (!empty($order['customer_phone'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars($order['customer_phone']); ?>" class="text-decoration-none text-muted d-inline-flex align-items-center gap-1" style="font-size: 0.78rem;">
                                        <i class="fas fa-phone text-primary" style="font-size: 0.7rem;"></i> <?php echo htmlspecialchars($order['customer_phone']); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-3 p-2 mb-3" style="background: #f8fafc; border: 1px solid #edf2f7; font-size: 0.8rem;">
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="text-muted" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 600;">Cloth Type</div>
                                <div class="d-flex align-items-center flex-wrap gap-1 mt-1">
                                    <span class="badge fw-medium text-dark text-truncate" style="background: #ffffff; border: 1px solid #e2e8f0; font-size: 0.75rem; max-width: 100%;">
                                        <?php echo htmlspecialchars($displayCloth); ?>
                                    </span>
                                    <?php if ($itemCount > 1): ?>
                                        <span class="badge rounded-pill bg-light text-primary border" style="font-size: 0.65rem;"><?php echo $itemCount; ?> Items</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!has_role('tailor')): ?>
                            <div class="col-6">
                                <div class="text-muted" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 600;">Tailor</div>
                                <div class="mt-1">
                                    <?php if (!empty($displayTailor)): ?>
                                        <div class="d-flex align-items-center gap-1 text-dark text-truncate" style="font-size: 0.8rem;">
                                            <i class="fas fa-user-tie text-muted flex-shrink-0" style="font-size: 0.72rem;"></i>
                                            <span class="text-truncate"><?php echo htmlspecialchars($displayTailor); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size: 0.75rem; font-style: italic;">Unassigned</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="col-6">
                                <div class="text-muted" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 600;">Order Date</div>
                                <div class="text-dark fw-medium mt-1"><?php echo format_date($order['order_date']); ?></div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 600;">Due Date</div>
                                <div class="mt-1 <?php echo $isOverdue ? 'text-danger fw-bold' : 'text-dark fw-medium'; ?>">
                                    <?php if ($isOverdue): ?><i class="fas fa-exclamation-circle text-danger me-1"></i><?php endif; ?>
                                    <?php echo format_date($order['due_date']); ?>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 600;">Quantity</div>
                                <div class="mt-1">
                                    <span class="badge rounded-pill fw-semibold px-2 py-1" style="background: #eef2ff; color: #4f46e5; border: 1px solid #c7d2fe; font-size: 0.75rem;">
                                        <?php echo $orderQty; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between p-2 rounded-3 mb-3" style="background: #ffffff; border: 1px solid #e2e8f0;">
                        <div>
                            <div class="text-muted" style="font-size: 0.68rem; text-transform: uppercase; font-weight: 600;">Total Amount</div>
                            <div class="fw-bold text-dark" style="font-size: 0.95rem;"><?php echo format_currency($order['total_amount']); ?></div>
                        </div>
                        <div class="text-center">
                            <div class="text-muted" style="font-size: 0.68rem; text-transform: uppercase; font-weight: 600;">Advance</div>
                            <div class="text-success fw-semibold" style="font-size: 0.85rem;">
                                <?php echo format_currency($order['advance_amount']); ?>
                                <span class="badge <?php echo $pmBadgeClass; ?>" style="font-size: 0.62rem;"><?php echo htmlspecialchars($pmLabel); ?></span>
                            </div>
                        </div>
                        <div class="text-end" id="orderCardAmount_<?php echo $order['id']; ?>">
                            <div class="text-muted" style="font-size: 0.68rem; text-transform: uppercase; font-weight: 600;">Balance</div>
                            <?php if ($pending > 0): ?>
                                <div class="text-danger fw-bold" style="font-size: 0.85rem;">
                                    <?php echo format_currency($pending); ?>
                                </div>
                            <?php else: ?>
                                <div class="text-success fw-bold" style="font-size: 0.8rem;">
                                    <i class="fas fa-check-circle me-1"></i>Fully Paid
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                        <span class="text-muted small" style="font-size: 0.72rem;">Actions</span>
                        <div class="d-inline-flex align-items-center gap-1">
                            <?php if (!has_role('tailor')): ?>
                                <?php if ($pending > 0): ?>
                                    <button type="button" 
                                            class="cat-action-btn cat-action-pay quick-pay-btn" 
                                            id="mobileQuickPayBtn_<?php echo $order['id']; ?>"
                                            onclick="openQuickPayModal(<?php echo htmlspecialchars(json_encode([
                                                'id' => $order['id'],
                                                'order_number' => $order['order_number'] ?? ('ORD' . $order['id']),
                                                'customer_name' => $order['measurement_customer_name'] ?? $order['customer_name'] ?? $custName,
                                                'total_amount' => (float)$order['total_amount'],
                                                'advance_amount' => (float)$order['advance_amount'],
                                                'balance_amount' => (float)$pending,
                                                'payment_method' => $order['payment_method'] ?? 'cash'
                                            ])); ?>)" 
                                            title="Mark as Fully Paid (Pending: <?php echo format_currency($pending); ?>)">
                                        <i class="fas fa-rupee-sign"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="cat-action-btn cat-action-paid" 
                                          id="mobileQuickPayBtn_<?php echo $order['id']; ?>"
                                          title="Fully Paid (<?php echo format_currency($order['total_amount']); ?>)">
                                        <i class="fas fa-check text-success"></i>
                                    </span>
                                <?php endif; ?>
                            <button type="button" 
                                    class="cat-action-btn cat-action-edit" 
                                    id="mobileEditOrderBtn_<?php echo $order['id']; ?>"
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
                            <div class="dropdown d-inline-block">
                                <button class="cat-action-btn cat-action-status dropdown-toggle" 
                                        type="button" 
                                        data-bs-toggle="dropdown"
                                        data-bs-popper-config='{"strategy":"fixed"}'
                                        aria-expanded="false"
                                        title="Change Status">
                                    <i class="fas fa-cog"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" onclick="event.stopPropagation();" style="border-radius: 12px; border: 1px solid #e2e8f0; font-size: 0.85rem; z-index: 1060;">
                                    <li><h6 class="dropdown-header text-uppercase text-muted" style="font-size: 0.68rem; letter-spacing: 0.5px;">Update Status</h6></li>
                                    <li><a class="dropdown-item py-1" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(<?php echo $order['id']; ?>, 'pending', this); return false;"><i class="fas fa-clock text-warning me-2"></i>Pending</a></li>
                                    <li><a class="dropdown-item py-1" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(<?php echo $order['id']; ?>, 'in_progress', this); return false;"><i class="fas fa-cogs text-primary me-2"></i>In Progress</a></li>
                                    <li><a class="dropdown-item py-1" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(<?php echo $order['id']; ?>, 'completed', this); return false;"><i class="fas fa-check-circle text-success me-2"></i>Completed</a></li>
                                    <li><a class="dropdown-item py-1" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(<?php echo $order['id']; ?>, 'delivered', this); return false;"><i class="fas fa-truck text-purple me-2" style="color: #8b5cf6;"></i>Delivered</a></li>
                                    <li><hr class="dropdown-divider my-1"></li>
                                    <li><a class="dropdown-item py-1 text-danger" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(<?php echo $order['id']; ?>, 'cancelled', this); return false;"><i class="fas fa-times-circle me-2"></i>Cancelled</a></li>
                                </ul>
                            </div>
                            <?php if ($canGenerateInvoice): ?>
                            <button type="button" 
                                    class="cat-action-btn cat-action-print" 
                                    onclick="printOrderInvoice(<?php echo $order['id']; ?>)" 
                                    title="Print Invoice">
                                <i class="fas fa-print"></i>
                            </button>
                            <?php endif; ?>
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
                    </div>
                </div>
                <?php endforeach; ?>
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
                    <?php if (!$orderLimitCheck['allowed']): ?>
                    <button type="button" class="btn text-white fw-semibold px-4 py-2" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 12px; font-size: 0.85rem; opacity: 0.65;" data-bs-toggle="modal" data-bs-target="#orderLimitUpgradeModal">
                        <i class="fas fa-plus me-1"></i>Create Order
                    </button>
                    <?php else: ?>
                    <button type="button" class="btn text-white fw-semibold px-4 py-2" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 12px; font-size: 0.85rem;" data-bs-toggle="modal" data-bs-target="#orderModal">
                        <i class="fas fa-plus me-1"></i>Create Order
                    </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php if (!has_role('tailor')): ?>
<div class="modal fade" id="orderLimitUpgradeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-body text-center p-4">
                <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px; border-radius: 50%; background: #fee2e2; color: #ef4444;">
                    <i class="fas fa-crown fa-2x"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Order Limit Reached</h5>
                <p class="text-muted small mb-4">
                    <?php echo htmlspecialchars($orderLimitCheck['message'] ?? 'You have reached the maximum limit of orders for your plan. Please upgrade your subscription to add more orders.'); ?>
                </p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-light px-4 fw-medium" data-bs-dismiss="modal" style="border-radius: 12px; font-size: 0.85rem;">Cancel</button>
                    <a href="subscriptions.php" class="btn text-white px-4 fw-semibold" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 12px; font-size: 0.85rem;">
                        <i class="fas fa-arrow-up me-1"></i> Upgrade Plan
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
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
                            <label for="customer_id" class="form-label fw-semibold">Customer *</label>
                            <input type="hidden" id="customer_id" name="customer_id" value="">
                            <div class="customer-picker-container position-relative">
                                <div class="customer-select-box" id="customerSelectBox" tabindex="0">
                                    <span class="customer-select-text text-muted" id="selectedCustomerDisplay">Select Customer</span>
                                    <div class="d-flex align-items-center">
                                        <button type="button" class="btn btn-link p-0 text-muted customer-clear-btn me-2" id="customerClearBtn" style="display: none;" title="Clear selection">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <i class="fas fa-chevron-down text-muted customer-chevron" style="font-size: 0.8rem;"></i>
                                    </div>
                                </div>
                                <div class="customer-dropdown-menu shadow-lg" id="customerDropdownMenu">
                                    <div class="p-2 border-bottom bg-light">
                                        <div class="position-relative">
                                            <input type="text" class="form-control form-control-sm pe-4" id="customerSearchInput" placeholder="Search customer by name..." autocomplete="off">
                                            <i class="fas fa-search position-absolute top-50 end-0 translate-middle-y me-2 text-muted" style="font-size: 0.78rem; pointer-events: none;"></i>
                                        </div>
                                    </div>
                                    <div class="customer-dropdown-header px-3 py-1 bg-light border-bottom" style="font-size: 0.75rem; color: #64748b;">
                                        <span id="customerListHeaderTitle" class="fw-semibold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.7rem;">Customer</span>
                                    </div>
                                    <div class="customer-dropdown-list" id="customerDropdownList" style="max-height: 220px; overflow-y: auto;">
                                        <!-- Populated via JavaScript -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="customer_phone" class="form-label fw-semibold">Phone Number *</label>
                            <input type="text" class="form-control" id="customer_phone" name="customer_phone" placeholder="Enter phone number" maxlength="15" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="order_date" class="form-label fw-semibold">Order Date *</label>
                            <input type="date" class="form-control" id="order_date" name="order_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="due_date" class="form-label fw-semibold">Due Date *</label>
                            <input type="date" class="form-control" id="due_date" name="due_date" required>
                        </div>
                    </div>
                    <input type="hidden" id="cloth_type_id" name="cloth_type_id" value="">
                    <input type="hidden" id="measurement_id" name="measurement_id" value="">
                    <input type="hidden" id="assigned_tailor_id" name="assigned_tailor_id" value="">
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold text-dark" style="font-size: 1.05rem;">
                                    Order Items & Measurements
                                </span>
                                <span class="badge rounded-pill bg-light text-primary border" id="orderItemsLimitBadge" style="font-size: 0.75rem; font-weight: 600; display: none;"></span>
                            </div>
                            
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <select class="form-select" id="topClothTypeSelect" style=" border-radius: 8px; border-color: #d1d5db; font-size: 0.88rem; font-weight: 500; background-repeat: no-repeat !important;">
                                    <option value="">Cloth Type *</option>
                                    <?php foreach ($clothTypes as $ct): ?>
                                        <option value="<?php echo $ct['id']; ?>" data-rate="<?php echo htmlspecialchars($ct['standard_rate'] ?? 0); ?>" data-name="<?php echo htmlspecialchars($ct['name']); ?>">
                                            <?php echo htmlspecialchars($ct['name']); ?><?php echo !empty($ct['standard_rate']) ? ' - ₹' . number_format($ct['standard_rate'], 2) : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-8 position-relative">
                                <div class="measurement-search-container" id="measurementSearchContainer" tabindex="0">
                                    <div id="measurementTagsList" class="d-flex align-items-center flex-wrap gap-1"></div>
                                    <input type="text" class="measurement-search-input" id="measurementSearchInput" placeholder="Measurement Search" autocomplete="off">
                                    <i class="fas fa-search measurement-search-icon"></i>
                                </div>
                                <div class="measurement-dropdown-menu" id="measurementDropdownMenu">
                                </div>
                            </div>
                        </div>

                        <div id="orderItemsContainer" class="d-flex flex-column gap-3">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="total_amount" class="form-label fw-semibold text-dark">Total Amount *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold text-dark">₹</span>
                                <input type="number" class="form-control bg-light fw-bold text-dark" id="total_amount" name="total_amount" step="0.01" min="0" placeholder="0.00" readonly tabindex="-1" style="cursor: not-allowed; background-color: #f8fafc !important;" required>
                            </div>
                            <div class="form-text text-muted" style="font-size: 0.72rem;"><i class="fas fa-lock me-1"></i>Auto-calculated from items</div>
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
                <div class="p-3 mb-3 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0; font-size: 0.85rem;">
                    <div class="text-center mb-2 pb-2 border-bottom">
                        <?php if (!empty($companyName)): ?>
                        <div class="fw-bold text-dark" style="font-size: 1.15rem;"><?php echo htmlspecialchars($companyName); ?></div>
                        <div class="text-muted text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.8px; font-weight: 600;">Order Receipt</div>
                        <?php else: ?>
                        <div class="fw-bold text-dark" style="font-size: 1.05rem;">Order Receipt</div>
                        <?php endif; ?>
                    </div>
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

<!-- Quick Pay Modal -->
<div class="modal fade" id="quickPayModal" tabindex="-1" aria-labelledby="quickPayModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 44px; height: 44px; background: #dcfce7; color: #16a34a; font-size: 1.25rem;">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0">Settle Order Payment</h5>
                        <small class="text-muted">Order <span id="quickPayOrderNum" class="fw-semibold text-primary"></span></small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <input type="hidden" id="quickPayOrderId" value="">
                <input type="hidden" id="quickPayCsrfToken" value="<?php echo generate_csrf_token(); ?>">

                <div class="p-3 mb-3 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0; font-size: 0.88rem;">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Customer:</span>
                        <strong id="quickPayCustomer" class="text-dark"></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total Amount:</span>
                        <strong id="quickPayTotal" class="text-dark"></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Current Advance:</span>
                        <strong id="quickPayAdvance" class="text-success"></strong>
                    </div>
                    <div class="d-flex justify-content-between pt-2 border-top align-items-center">
                        <span class="fw-bold text-dark">Pending to Collect:</span>
                        <span class="badge bg-danger-subtle text-danger fs-6 fw-bold px-2 py-1" id="quickPayPendingBadge">₹0.00</span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Payment Method</label>
                    <select class="form-select form-select-sm" id="quickPayMethod" style="border-radius: 8px;">
                        <option value="cash" selected>Cash</option>
                        <option value="online">Online / UPI</option>
                        <option value="card">Card</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cheque">Cheque</option>
                    </select>
                </div>

                <div class="alert alert-success d-flex align-items-center py-2 px-3 mb-0" style="font-size: 0.82rem; border-radius: 8px;">
                    <i class="fas fa-info-circle me-2 flex-shrink-0"></i>
                    <span>This will set Advance to the Total Amount (Pending becomes ₹0.00) and mark the order as <strong>Fully Paid</strong>.</span>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 pb-4 px-4 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-light rounded-pill px-3 py-2 btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success rounded-pill px-4 py-2 btn-sm fw-semibold shadow-sm" id="quickPaySubmitBtn" onclick="submitQuickPay()" style="background-color: #16a34a; border-color: #16a34a;">
                    <i class="fas fa-check-circle me-1"></i>Mark Fully Paid
                </button>
            </div>
        </div>
    </div>
</div>


<!-- Custom Order Alert Modal -->
<div class="modal fade" id="orderAlertModal" tabindex="-1" aria-labelledby="orderAlertTitle" aria-hidden="true" style="z-index: 1085;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content border-0 shadow-lg text-center p-3 p-sm-4" style="border-radius: 20px;">
            <div class="modal-body p-0">
                <div id="orderAlertIconContainer" class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 58px; height: 58px; background: #fef3c7; color: #d97706;">
                    <i id="orderAlertIcon" class="fas fa-exclamation-triangle fa-2x"></i>
                </div>
                <h5 id="orderAlertTitle" class="fw-bold mb-2 text-dark" style="font-size: 1.12rem;">Notice</h5>
                <p id="orderAlertMessage" class="text-secondary small mb-4 px-2" style="line-height: 1.6; font-size: 0.88rem;"></p>
                <div id="orderAlertActions" class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const currentCompanyName = <?php echo json_encode($companyName); ?>;
function showOrderAlert(message, title = 'Notice', type = 'warning', action = null) {
    const modalEl = document.getElementById('orderAlertModal');
    if (!modalEl) {
        alert(message);
        return;
    }
    const iconContainer = document.getElementById('orderAlertIconContainer');
    const iconEl = document.getElementById('orderAlertIcon');
    const titleEl = document.getElementById('orderAlertTitle');
    const msgEl = document.getElementById('orderAlertMessage');
    const actionsEl = document.getElementById('orderAlertActions');
    
    if (titleEl) titleEl.textContent = title;
    if (msgEl) msgEl.textContent = message;
    
    if (iconContainer && iconEl) {
        if (type === 'danger' || type === 'error') {
            iconContainer.style.background = '#fee2e2';
            iconContainer.style.color = '#dc2626';
            iconEl.className = 'fas fa-times-circle fa-2x';
        } else if (type === 'success') {
            iconContainer.style.background = '#dcfce7';
            iconContainer.style.color = '#16a34a';
            iconEl.className = 'fas fa-check-circle fa-2x';
        } else if (type === 'info') {
            iconContainer.style.background = '#e0e7ff';
            iconContainer.style.color = '#4338ca';
            iconEl.className = 'fas fa-info-circle fa-2x';
        } else {
            iconContainer.style.background = '#fef3c7';
            iconContainer.style.color = '#d97706';
            iconEl.className = 'fas fa-exclamation-triangle fa-2x';
        }
    }
    
    if (actionsEl) {
        if (action && action.url && action.text) {
            actionsEl.innerHTML = `
                <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Close</button>
                <a href="${action.url}" class="btn btn-primary rounded-pill px-4">${escapeHtml(action.text)}</a>
            `;
        } else {
            actionsEl.innerHTML = `<button type="button" class="btn btn-primary rounded-pill px-4" data-bs-dismiss="modal">OK</button>`;
        }
    }
    
    if (window.bootstrap && bootstrap.Modal) {
        const bsModal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        bsModal.show();
    } else {
        alert(message);
    }
}
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
            calculateTotalFromItems();
            const customerVal = document.getElementById('customer_id')?.value?.trim();
            if (!customerVal) {
                e.preventDefault();
                showOrderAlert('Please select a customer for this order.', 'Customer Required', 'warning');
                const box = document.getElementById('customerSelectBox');
                if (box) {
                    box.classList.add('is-invalid');
                    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return false;
            }
            const phoneInputEl = document.getElementById('customer_phone');
            const phoneVal = phoneInputEl?.value?.trim();
            if (!phoneVal) {
                e.preventDefault();
                showOrderAlert('Please enter a phone number for this order.', 'Phone Number Required', 'warning');
                if (phoneInputEl) {
                    phoneInputEl.classList.add('is-invalid');
                    phoneInputEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    phoneInputEl.focus();
                }
                return false;
            }
            if (phoneInputEl) {
                phoneInputEl.classList.remove('is-invalid');
            }
            const cards = document.querySelectorAll('.order-item-card');
            if (cards.length === 0) {
                e.preventDefault();
                showOrderAlert('Please add at least one garment to the order.', 'Garment Required', 'warning');
                return false;
            }
            let missingField = false;
            cards.forEach((card, idx) => {
                const cloth = card.querySelector('.item-cloth-type-select')?.value;
                const tailor = card.querySelector('.item-tailor-select')?.value;
                const price = card.querySelector('.item-price-input')?.value;
                if (!cloth || !tailor || price === '') {
                    missingField = true;
                }
            });
            if (missingField) {
                e.preventDefault();
                showOrderAlert('Please select Cloth Type, Assigned Tailor, and Rate for all garments.', 'Required Fields', 'warning');
                return false;
            }

            const total = parseFloat(document.getElementById('total_amount')?.value || 0);
            const advance = parseFloat(document.getElementById('advance_amount')?.value || 0);
            if (advance < 0) {
                e.preventDefault();
                showOrderAlert('Advance amount cannot be negative.', 'Invalid Advance Amount', 'warning');
                return false;
            }
            if (advance > total && total > 0) {
                e.preventDefault();
                showOrderAlert('Advance amount (₹' + advance.toFixed(2) + ') cannot be greater than Total amount (₹' + total.toFixed(2) + ').', 'Invalid Advance Amount', 'warning');
                return false;
            }
        });
    }
});

window.availableClothTypes = <?php echo json_encode(array_values($clothTypes)); ?>;
window.availableTailors = <?php echo json_encode(array_values($tailors)); ?>;
window.currentCustomerMeasurements = [];
let orderItemCounter = 0;
let selectedMeasurementIds = new Set();

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
}

function formatMeasurementLabel(key) {
    return key.replace(/_/g, ' ')
              .replace(/\b\w/g, l => l.toUpperCase());
}

function renderMeasurementPills(mData, notes = '') {
    if (!mData || typeof mData !== 'object') {
        return `<div class="text-muted small py-2 text-center" style="font-size: 0.8rem;">
            <i class="fas fa-info-circle me-1"></i>No measurement values recorded for this item.
        </div>`;
    }

    const entries = Object.entries(mData).filter(([k, v]) => v !== null && v !== undefined && String(v).trim() !== '');
    if (entries.length === 0) {
        return `<div class="text-muted small py-2 text-center" style="font-size: 0.8rem;">
            <i class="fas fa-info-circle me-1"></i>No measurement values recorded for this item.
        </div>`;
    }

    let pillsHtml = '<div class="measurement-pills-grid">';
    entries.forEach(([key, val]) => {
        const label = formatMeasurementLabel(key);
        const valStr = String(val).trim();
        const isColor = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(valStr);

        let valueContent;
        if (isColor) {
            valueContent = `
                <span style="width: 12px; height: 12px; border-radius: 50%; background-color: ${escapeHtml(valStr)}; display: inline-block; border: 1px solid rgba(0,0,0,0.15); flex-shrink: 0;"></span>
                <span>${escapeHtml(valStr)}</span>
            `;
        } else {
            valueContent = `<span>${escapeHtml(valStr)}</span>`;
        }

        pillsHtml += `
            <div class="measurement-pill-item">
                <div class="measurement-pill-label">${escapeHtml(label)}</div>
                <div class="measurement-pill-value">${valueContent}</div>
            </div>
        `;
    });
    pillsHtml += '</div>';

    if (notes && String(notes).trim() !== '') {
        pillsHtml += `
            <div class="mt-2 text-muted small" style="font-size: 0.78rem;">
                <i class="fas fa-sticky-note text-warning me-1"></i><strong>Notes:</strong> ${escapeHtml(notes)}
            </div>
        `;
    }

    return pillsHtml;
}

function calculateTotalFromItems() {
    let sum = 0;
    const cards = document.querySelectorAll('.order-item-card');
    cards.forEach(card => {
        const priceVal = parseFloat(card.querySelector('.item-price-input')?.value) || 0;
        const qtyInput = card.querySelector('.item-qty-input');
        const qtyVal = qtyInput ? Math.max(1, parseInt(qtyInput.value, 10) || 1) : 1;
        const lineTotal = priceVal * qtyVal;
        const subtotalEl = card.querySelector('.item-line-total');
        if (subtotalEl) {
            if (qtyVal > 1) {
                subtotalEl.textContent = 'Subtotal: ₹' + lineTotal.toFixed(2);
                subtotalEl.style.display = 'inline-block';
            } else {
                subtotalEl.style.display = 'none';
            }
        }
        sum += lineTotal;
    });

    const totalInput = document.getElementById('total_amount');
    if (totalInput) {
        totalInput.value = sum > 0 ? sum.toFixed(2) : '0.00';
    }

    // Sync primary fallback fields from first item card
    const firstCard = document.querySelector('.order-item-card');
    if (firstCard) {
        const firstCloth = firstCard.querySelector('.item-cloth-type-select')?.value || '';
        const firstMeas = firstCard.querySelector('.item-measurement-id')?.value || '';
        const firstTailor = firstCard.querySelector('.item-tailor-select')?.value || '';
        if (document.getElementById('cloth_type_id')) document.getElementById('cloth_type_id').value = firstCloth;
        if (document.getElementById('measurement_id')) document.getElementById('measurement_id').value = firstMeas;
        if (document.getElementById('assigned_tailor_id')) document.getElementById('assigned_tailor_id').value = firstTailor;
    }

    calculatePendingAmount();
}

function addOrderItemCard(itemData = {}, isExpanded = true) {
    const container = document.getElementById('orderItemsContainer');
    if (!container) return null;

    const index = orderItemCounter++;
    const card = document.createElement('div');
    card.className = 'order-item-card';
    card.setAttribute('data-index', index);

    const measId = itemData.measurement_id ? String(itemData.measurement_id) : '';
    if (measId) {
        card.setAttribute('data-measurement-id', measId);
        selectedMeasurementIds.add(measId);
    }

    // Determine cloth type & standard rate
    let currentClothId = itemData.cloth_type_id ? String(itemData.cloth_type_id) : '';
    
    // If measurement given but no cloth type, try to match from measurement object
    let measObj = null;
    if (measId && window.currentCustomerMeasurements) {
        measObj = window.currentCustomerMeasurements.find(m => String(m.id) === measId);
        if (measObj && !currentClothId && measObj.cloth_type_id) {
            currentClothId = String(measObj.cloth_type_id);
        }
    }

    // Determine standard rate of currentClothId
    let standardRate = 0;
    const clothTypesList = window.availableClothTypes || [];
    const foundCt = clothTypesList.find(ct => String(ct.id) === currentClothId);
    if (foundCt && foundCt.standard_rate) {
        standardRate = parseFloat(foundCt.standard_rate);
    }

    // Initial price
    let initPrice = '';
    if (itemData.price !== undefined && itemData.price !== null && itemData.price !== '') {
        initPrice = parseFloat(itemData.price).toFixed(2);
    } else if (standardRate > 0) {
        initPrice = standardRate.toFixed(2);
    }

    function truncateLabel(str, maxLen) {
        const s = String(str || '').trim();
        return s.length > maxLen ? s.slice(0, maxLen).trimEnd() + '...' : s;
    }

    // Build cloth types options (truncate very long names with ... so full price stays visible)
    let clothOptions = '<option value="">Select Cloth Type *</option>';
    clothTypesList.forEach(ct => {
        const selected = currentClothId && String(ct.id) === currentClothId ? 'selected' : '';
        const rateText = ct.standard_rate ? ' - ₹' + parseFloat(ct.standard_rate).toFixed(2) : '';
        const fullLabel = `${ct.name || ''}${rateText}`;
        const shortLabel = `${truncateLabel(ct.name || '', 28)}${rateText}`;
        clothOptions += `<option value="${ct.id}" data-rate="${ct.standard_rate || 0}" title="${escapeHtml(fullLabel)}" ${selected}>${escapeHtml(shortLabel)}</option>`;
    });

    // Build tailors options
    let tailorOptions = '<option value="">Select Tailor *</option>';
    (window.availableTailors || []).forEach(t => {
        const selected = itemData.assigned_tailor_id && String(itemData.assigned_tailor_id) === String(t.id) ? 'selected' : '';
        tailorOptions += `<option value="${t.id}" ${selected}>${escapeHtml(t.full_name)}</option>`;
    });

    // Measurement pills
    let pillsHtml = '';
    if (measObj) {
        pillsHtml = renderMeasurementPills(measObj.measurement_data, measObj.notes);
    } else if (itemData.measurement_data) {
        pillsHtml = renderMeasurementPills(itemData.measurement_data, itemData.notes);
    } else if (measId) {
        pillsHtml = `<div class="text-muted small py-2 text-center"><i class="fas fa-spinner fa-spin me-1"></i>Loading measurements...</div>`;
        fetch(`ajax/get_measurement_details.php?id=${encodeURIComponent(measId)}`)
            .then(res => res.json())
            .then(mData => {
                const pillsBox = card.querySelector('.item-pills-container');
                if (pillsBox && mData && !mData.error) {
                    pillsBox.innerHTML = renderMeasurementPills(mData.measurement_data, mData.notes);
                }
            })
            .catch(() => {});
    } else {
        pillsHtml = `<div class="text-muted small py-2 text-center" style="font-size: 0.8rem;">
            <i class="fas fa-info-circle me-1"></i>No measurement linked. 
        </div>`;
    }

    const standardRateText = standardRate > 0 ? standardRate.toFixed(2) : '0.00';
    const initQty = Math.max(1, parseInt(itemData.quantity, 10) || 1);

    card.innerHTML = `
        <input type="hidden" class="item-measurement-id" name="items[${index}][measurement_id]" value="${measId}">
        <div class="order-item-header">
            <!-- 1. Cloth Type Select with Rate -->
            <select class="form-select item-cloth-type-select" name="items[${index}][cloth_type_id]" required style="border-radius: 8px; border-color: #d1d5db; font-size: 0.85rem; font-weight: 500;">
                ${clothOptions}
            </select>

            <!-- 2. Assigned Tailor Select -->
            <select class="form-select item-tailor-select" name="items[${index}][assigned_tailor_id]" required style="border-radius: 8px; border-color: #d1d5db; font-size: 0.85rem; font-weight: 500;">
                ${tailorOptions}
            </select>

            <!-- 3. Collapse / Expand toggle button -->
            <button type="button" class="btn btn-link p-1 text-secondary item-collapse-btn" title="Expand / Collapse" style="text-decoration: none; font-size: 0.95rem;">
                <i class="fas ${isExpanded ? 'fa-chevron-up' : 'fa-chevron-down'} toggle-icon"></i>
            </button>

            <!-- 4. Delete button -->
            <button type="button" class="btn btn-link p-1 text-secondary item-delete-btn" title="Remove Item" style="text-decoration: none; font-size: 0.95rem;">
                <i class="far fa-trash-alt"></i>
            </button>
        </div>

        <!-- Collapsible Body with Measurement Pills Grid -->
        <div class="order-item-body" style="display: ${isExpanded ? 'block' : 'none'};">
            <div class="item-pills-container">
                ${pillsHtml}
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top flex-wrap gap-2">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <span class="small text-muted" style="font-size: 0.78rem;">
                        <i class="fas fa-tag me-1 text-primary"></i>Standard Rate: ₹<span class="item-standard-rate">${standardRateText}</span>
                    </span>
                    <span class="small text-primary fw-semibold item-line-total" style="font-size: 0.78rem; display: none;"></span>
                </div>
                <div class="d-flex align-items-center flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <label class="small text-muted fw-semibold mb-0" style="font-size: 0.78rem;">Qty:</label>
                        <div class="input-group input-group-sm item-qty-group">
                            <button type="button" class="btn btn-outline-secondary item-qty-minus" aria-label="Decrease quantity">-</button>
                            <input type="number" class="form-control form-control-sm text-center item-qty-input px-1" name="items[${index}][quantity]" min="1" step="1" value="${initQty}" required style="font-weight: 600;">
                            <button type="button" class="btn btn-outline-secondary item-qty-plus" aria-label="Increase quantity">+</button>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label class="small text-muted fw-semibold mb-0" style="font-size: 0.78rem;">Rate / Price:</label>
                        <div class="input-group input-group-sm" style="width: 125px;">
                            <span class="input-group-text bg-white text-muted fw-bold">₹</span>
                            <input type="number" class="form-control form-control-sm item-price-input" name="items[${index}][price]" step="0.01" min="0" placeholder="0.00" value="${initPrice}" required style="font-weight: 600; border-radius: 0 6px 6px 0;">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    container.appendChild(card);

    // Event listeners inside the card
    const clothSelect = card.querySelector('.item-cloth-type-select');
    const priceInput = card.querySelector('.item-price-input');
    const qtyInput = card.querySelector('.item-qty-input');
    const qtyMinusBtn = card.querySelector('.item-qty-minus');
    const qtyPlusBtn = card.querySelector('.item-qty-plus');
    const collapseBtn = card.querySelector('.item-collapse-btn');
    const toggleIcon = card.querySelector('.toggle-icon');
    const bodyEl = card.querySelector('.order-item-body');
    const deleteBtn = card.querySelector('.item-delete-btn');
    const stdRateSpan = card.querySelector('.item-standard-rate');

    collapseBtn.addEventListener('click', function() {
        if (bodyEl.style.display === 'none') {
            bodyEl.style.display = 'block';
            toggleIcon.className = 'fas fa-chevron-up toggle-icon';
        } else {
            bodyEl.style.display = 'none';
            toggleIcon.className = 'fas fa-chevron-down toggle-icon';
        }
    });

    clothSelect.addEventListener('change', function() {
        const selectedOpt = this.options[this.selectedIndex];
        const rate = selectedOpt ? parseFloat(selectedOpt.getAttribute('data-rate')) || 0 : 0;
        if (stdRateSpan) stdRateSpan.textContent = rate > 0 ? rate.toFixed(2) : '0.00';
        if (priceInput && (!priceInput.value || parseFloat(priceInput.value) === 0 || priceInput.dataset.autoFilled === 'true' || !itemData.price)) {
            priceInput.value = rate > 0 ? rate.toFixed(2) : '0.00';
            priceInput.dataset.autoFilled = 'true';
        }
        calculateTotalFromItems();
    });

    priceInput.addEventListener('input', function() {
        priceInput.dataset.autoFilled = 'false';
        calculateTotalFromItems();
    });
    priceInput.addEventListener('change', function() {
        priceInput.dataset.autoFilled = 'false';
        calculateTotalFromItems();
    });

    if (qtyMinusBtn && qtyInput) {
        qtyMinusBtn.addEventListener('click', function() {
            const cur = parseInt(qtyInput.value, 10) || 1;
            qtyInput.value = Math.max(1, cur - 1);
            calculateTotalFromItems();
        });
    }
    if (qtyPlusBtn && qtyInput) {
        qtyPlusBtn.addEventListener('click', function() {
            const cur = parseInt(qtyInput.value, 10) || 1;
            qtyInput.value = cur + 1;
            calculateTotalFromItems();
        });
    }
    if (qtyInput) {
        qtyInput.addEventListener('input', function() {
            calculateTotalFromItems();
        });
        qtyInput.addEventListener('change', function() {
            const val = parseInt(qtyInput.value, 10);
            qtyInput.value = (isNaN(val) || val < 1) ? 1 : val;
            calculateTotalFromItems();
        });
    }

    deleteBtn.addEventListener('click', function() {
        const cardMeasId = card.getAttribute('data-measurement-id');
        if (cardMeasId) {
            selectedMeasurementIds.delete(cardMeasId);
            updateMeasurementTagsUI();
        }
        card.remove();
        calculateTotalFromItems();
        updateAddItemButtonState();
    });

    calculateTotalFromItems();
    updateAddItemButtonState();
    return card;
}

function updateAddItemButtonState() {
    const addBtn = document.getElementById('addOrderItemBtn');
    const topCloth = document.getElementById('topClothTypeSelect');
    const limitBadge = document.getElementById('orderItemsLimitBadge');
    const customerName = document.getElementById('customer_id')?.value;
    const measurements = window.currentCustomerMeasurements || [];
    const cards = document.querySelectorAll('.order-item-card');

    if (!customerName) {
        if (addBtn) {
            addBtn.disabled = true;
            addBtn.style.opacity = '0.5';
            addBtn.style.cursor = 'not-allowed';
            addBtn.title = 'Please select a customer first';
        }
        if (topCloth) topCloth.disabled = true;
        if (limitBadge) limitBadge.style.display = 'none';
        return;
    }

    if (limitBadge) {
        limitBadge.style.display = 'inline-block';
        limitBadge.textContent = `${cards.length}/${measurements.length} Measurements Added`;
    }

    if (measurements.length === 0) {
        if (addBtn) {
            addBtn.disabled = true;
            addBtn.style.opacity = '0.5';
            addBtn.style.cursor = 'not-allowed';
            addBtn.title = 'No measurements found for this customer. Please add measurements in the Measurement section first.';
        }
        if (topCloth) topCloth.disabled = true;
        return;
    }

    // Limit reached: cannot add more than customer's available measurements
    if (cards.length >= measurements.length) {
        if (addBtn) {
            addBtn.disabled = true;
            addBtn.style.opacity = '0.5';
            addBtn.style.cursor = 'not-allowed';
            addBtn.title = `All (${measurements.length}) measurements for this customer have already been added to the order. To add more garments, please record a new measurement first.`;
        }
        if (topCloth) topCloth.disabled = true;
    } else {
        if (addBtn) {
            addBtn.disabled = false;
            addBtn.style.opacity = '1';
            addBtn.style.cursor = 'pointer';
            addBtn.title = 'Add Remaining Measurement';
        }
        if (topCloth) topCloth.disabled = false;
    }
}

function updateMeasurementTagsUI() {
    const tagsContainer = document.getElementById('measurementTagsList');
    const measSearchInput = document.getElementById('measurementSearchInput');
    if (!tagsContainer) return;

    tagsContainer.innerHTML = '';
    selectedMeasurementIds.forEach(id => {
        const meas = (window.currentCustomerMeasurements || []).find(m => String(m.id) === String(id));
        const tagName = meas ? meas.cloth_type_name : `Meas #${id}`;

        const tag = document.createElement('span');
        tag.className = 'measurement-tag';
        tag.innerHTML = `
            <span>${escapeHtml(tagName)}</span>
            <span class="tag-remove-btn" data-id="${id}">&times;</span>
        `;
        tag.querySelector('.tag-remove-btn').addEventListener('click', function(e) {
            e.stopPropagation();
            removeMeasurement(id);
        });
        tagsContainer.appendChild(tag);
    });

    if (measSearchInput) {
        if (selectedMeasurementIds.size > 0) {
            measSearchInput.placeholder = '';
        } else {
            measSearchInput.placeholder = 'Measurement Search';
        }
    }

    renderMeasurementDropdownOptions();
    updateAddItemButtonState();
}

function removeMeasurement(id) {
    selectedMeasurementIds.delete(String(id));
    updateMeasurementTagsUI();

    const card = document.querySelector(`.order-item-card[data-measurement-id="${id}"]`);
    if (card) {
        card.remove();
        calculateTotalFromItems();
    }
    updateAddItemButtonState();
}

function addMeasurement(id) {
    const idStr = String(id);
    if (selectedMeasurementIds.has(idStr)) return;

    const measurements = window.currentCustomerMeasurements || [];
    const cards = document.querySelectorAll('.order-item-card');
    if (cards.length >= measurements.length) {
        showOrderAlert(`All (${measurements.length}) measurements for this customer have already been added to the order. To add more garments, please record a new measurement first.`, 'Measurement Limit Reached', 'info', { text: 'Add Measurement', url: 'measurements.php' });
        return;
    }

    selectedMeasurementIds.add(idStr);
    updateMeasurementTagsUI();

    const meas = measurements.find(m => String(m.id) === idStr);
    addOrderItemCard({
        measurement_id: idStr,
        cloth_type_id: meas ? meas.cloth_type_id : '',
        price: ''
    }, true);
    updateAddItemButtonState();
}

function renderMeasurementDropdownOptions(filterText = '') {
    const dropdown = document.getElementById('measurementDropdownMenu');
    if (!dropdown) return;

    const measurements = window.currentCustomerMeasurements || [];
    if (!document.getElementById('customer_id')?.value) {
        dropdown.innerHTML = `<div class="p-3 text-muted text-center small"><i class="fas fa-user-clock me-1"></i>Please select a customer first</div>`;
        return;
    }

    if (measurements.length === 0) {
        dropdown.innerHTML = `<div class="p-3 text-muted text-center small"><i class="fas fa-exclamation-circle me-1"></i>No measurements found for this customer</div>`;
        return;
    }

    const filter = filterText.toLowerCase().trim();
    const filtered = measurements.filter(m => {
        if (!filter) return true;
        return (m.cloth_type_name && m.cloth_type_name.toLowerCase().includes(filter)) ||
               (m.created_at && m.created_at.toLowerCase().includes(filter));
    });

    if (filtered.length === 0) {
        dropdown.innerHTML = `<div class="p-2 text-muted text-center small">No matching measurements</div>`;
        return;
    }

    let html = '';
    filtered.forEach(m => {
        const isSelected = selectedMeasurementIds.has(String(m.id));
        html += `
            <div class="measurement-dropdown-item ${isSelected ? 'is-selected' : ''}" data-id="${m.id}">
                <div>
                    <strong>${escapeHtml(m.cloth_type_name)}</strong>
                    <small class="text-muted ms-1">(${escapeHtml(m.created_at)})</small>
                </div>
                <div>
                    ${isSelected 
                        ? '<span class="badge bg-primary" style="font-size: 0.7rem;"><i class="fas fa-check me-1"></i>Added</span>' 
                        : '<i class="fas fa-plus text-primary"></i>'}
                </div>
            </div>
        `;
    });

    dropdown.innerHTML = html;

    dropdown.querySelectorAll('.measurement-dropdown-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.stopPropagation();
            const id = this.getAttribute('data-id');
            if (selectedMeasurementIds.has(String(id))) {
                removeMeasurement(id);
            } else {
                addMeasurement(id);
            }
            const measSearchInput = document.getElementById('measurementSearchInput');
            if (measSearchInput) {
                measSearchInput.value = '';
                measSearchInput.focus();
            }
        });
    });
}

// Search box interactions
const measSearchContainer = document.getElementById('measurementSearchContainer');
const measSearchInput = document.getElementById('measurementSearchInput');
const measDropdownMenu = document.getElementById('measurementDropdownMenu');

if (measSearchContainer && measDropdownMenu) {
    measSearchContainer.addEventListener('click', function() {
        if (measSearchInput) measSearchInput.focus();
        measDropdownMenu.style.display = 'block';
        renderMeasurementDropdownOptions(measSearchInput ? measSearchInput.value : '');
    });

    if (measSearchInput) {
        measSearchInput.addEventListener('input', function() {
            measDropdownMenu.style.display = 'block';
            renderMeasurementDropdownOptions(this.value);
        });

        measSearchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && !this.value && selectedMeasurementIds.size > 0) {
                const ids = Array.from(selectedMeasurementIds);
                const lastId = ids[ids.length - 1];
                removeMeasurement(lastId);
            }
        });
    }

    document.addEventListener('click', function(e) {
        if (!measSearchContainer.contains(e.target) && !measDropdownMenu.contains(e.target)) {
            measDropdownMenu.style.display = 'none';
        }
    });
}

// Top Cloth Type dropdown interaction
const topClothTypeSelect = document.getElementById('topClothTypeSelect');
if (topClothTypeSelect) {
    topClothTypeSelect.addEventListener('change', function() {
        const clothId = this.value;
        if (!clothId) return;

        const measurements = window.currentCustomerMeasurements || [];
        const cards = document.querySelectorAll('.order-item-card');
        const customerName = document.getElementById('customer_id')?.value;

        if (!customerName) {
            showOrderAlert('Please select a customer first.', 'Customer Required', 'warning');
            this.value = '';
            return;
        }

        if (measurements.length === 0) {
            showOrderAlert('No measurements found for this customer. Please add measurements in the Measurement section first.', 'No Measurements Found', 'warning', { text: 'Add Measurement', url: 'measurements.php' });
            this.value = '';
            return;
        }

        if (cards.length >= measurements.length) {
            showOrderAlert(`All (${measurements.length}) measurements for this customer have already been added to the order. To add more garments, please record a new measurement first.`, 'All Measurements Added', 'info', { text: 'Add Measurement', url: 'measurements.php' });
            this.value = '';
            return;
        }

        // Find unused measurement matching this cloth type
        const matchingMeas = measurements.find(m => String(m.cloth_type_id) === String(clothId) && !selectedMeasurementIds.has(String(m.id)));
        if (matchingMeas) {
            addMeasurement(matchingMeas.id);
        } else {
            showOrderAlert('No unused measurement found for this cloth type. Please add measurements for this garment in the Measurement section first.', 'Measurement Not Found', 'warning', { text: 'Add Measurement', url: 'measurements.php' });
        }

        this.value = '';
    });
}

// + Add Item button interaction
const addOrderItemBtn = document.getElementById('addOrderItemBtn');
if (addOrderItemBtn) {
    addOrderItemBtn.addEventListener('click', function() {
        const measurements = window.currentCustomerMeasurements || [];
        const cards = document.querySelectorAll('.order-item-card');
        const customerName = document.getElementById('customer_id')?.value;

        if (!customerName) {
            showOrderAlert('Please select a customer first.', 'Customer Required', 'warning');
            return;
        }

        if (measurements.length === 0) {
            showOrderAlert('No measurements found for this customer. Please add measurements in the Measurement section first.', 'No Measurements Found', 'warning', { text: 'Add Measurement', url: 'measurements.php' });
            return;
        }

        if (cards.length >= measurements.length) {
            showOrderAlert(`All (${measurements.length}) measurements for this customer have already been added to the order. To add more garments, please record a new measurement first.`, 'All Measurements Added', 'info', { text: 'Add Measurement', url: 'measurements.php' });
            return;
        }

        // Find unused measurement and add it
        const unused = measurements.find(m => !selectedMeasurementIds.has(String(m.id)));
        if (unused) {
            addMeasurement(unused.id);
        }
    });
}

// Customer Dropdown Management
const allMeasurementCustomers = <?php echo json_encode(array_values($measurementCustomers)); ?>;
const customerIdInput = document.getElementById('customer_id');
const customerSelectBox = document.getElementById('customerSelectBox');
const customerDropdownMenu = document.getElementById('customerDropdownMenu');
const customerSearchInput = document.getElementById('customerSearchInput');
const customerDropdownList = document.getElementById('customerDropdownList');
const selectedCustomerDisplay = document.getElementById('selectedCustomerDisplay');
const customerClearBtn = document.getElementById('customerClearBtn');
const customerListHeaderTitle = document.getElementById('customerListHeaderTitle');

function renderCustomerOptions(filterText = '') {
    if (!customerDropdownList) return;
    const query = filterText.trim().toLowerCase();
    let displayList = [];
    if (!query) {
        // Show all customers (scrollable, no limit)
        displayList = allMeasurementCustomers;
    } else {
        // Filter strictly by customer name only
        displayList = allMeasurementCustomers.filter(c => {
            return c.customer_name && c.customer_name.toLowerCase().includes(query);
        });
    }

    if (customerListHeaderTitle) {
        customerListHeaderTitle.textContent = 'Customer';
    }

    if (displayList.length === 0) {
        customerDropdownList.innerHTML = `
            <div class="p-3 text-center text-muted" style="font-size: 0.82rem;">
                <i class="fas fa-user-slash d-block mb-1 text-secondary" style="font-size: 1.1rem;"></i>
                No customer found matching "<strong>${escapeHtml(filterText)}</strong>"
            </div>
        `;
        return;
    }

    const currentVal = customerIdInput ? customerIdInput.value : '';
    let html = '';
    displayList.forEach(cust => {
        const isSelected = (currentVal && cust.customer_name === currentVal);
        html += `
            <div class="customer-dropdown-item ${isSelected ? 'is-selected' : ''}" 
                 data-name="${escapeHtml(cust.customer_name)}" 
                 data-phone="${escapeHtml(cust.phone_number || '')}">
                <div class="fw-semibold ${isSelected ? 'text-primary' : 'text-dark'}">${escapeHtml(cust.customer_name)}</div>
                ${isSelected ? '<i class="fas fa-check text-primary" style="font-size: 0.85rem;"></i>' : ''}
            </div>
        `;
    });
    customerDropdownList.innerHTML = html;

    customerDropdownList.querySelectorAll('.customer-dropdown-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.stopPropagation();
            const name = this.getAttribute('data-name');
            const phone = this.getAttribute('data-phone');
            setCustomerValue(name, phone, true);
            closeCustomerDropdown();
        });
    });
}

function setCustomerValue(name, phone = '', triggerChange = true) {
    if (!customerIdInput) return;
    customerIdInput.value = name || '';
    if (phone) {
        customerIdInput.setAttribute('data-phone', phone);
    } else {
        customerIdInput.removeAttribute('data-phone');
    }
    if (selectedCustomerDisplay) {
        if (name) {
            selectedCustomerDisplay.innerHTML = `<span class="fw-semibold text-dark">${escapeHtml(name)}</span>`;
            selectedCustomerDisplay.classList.remove('text-muted');
        } else {
            selectedCustomerDisplay.textContent = 'Select Customer';
            selectedCustomerDisplay.classList.add('text-muted');
        }
    }
    if (customerClearBtn) {
        customerClearBtn.style.display = name ? 'inline-block' : 'none';
    }
    if (customerSelectBox) {
        customerSelectBox.classList.remove('is-invalid');
    }
    const phoneInput = document.getElementById('customer_phone');
    if (phoneInput && phone) {
        phoneInput.value = phone;
    }
    if (triggerChange) {
        customerIdInput.dispatchEvent(new Event('change'));
    }
}

function openCustomerDropdown() {
    if (!customerDropdownMenu) return;
    customerDropdownMenu.style.display = 'block';
    if (customerSelectBox) customerSelectBox.classList.add('is-focused');
    renderCustomerOptions(customerSearchInput ? customerSearchInput.value : '');
    setTimeout(() => {
        if (customerSearchInput) customerSearchInput.focus();
    }, 50);
}

function closeCustomerDropdown() {
    if (!customerDropdownMenu) return;
    customerDropdownMenu.style.display = 'none';
    if (customerSelectBox) customerSelectBox.classList.remove('is-focused');
}

if (customerSelectBox) {
    customerSelectBox.addEventListener('click', function(e) {
        if (e.target.closest('#customerClearBtn')) return;
        if (customerDropdownMenu.style.display === 'block') {
            closeCustomerDropdown();
        } else {
            openCustomerDropdown();
        }
    });
    customerSelectBox.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
            e.preventDefault();
            openCustomerDropdown();
        }
    });
}

if (customerClearBtn) {
    customerClearBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        setCustomerValue('', '', true);
    });
}

if (customerSearchInput) {
    customerSearchInput.addEventListener('input', function() {
        renderCustomerOptions(this.value);
    });
    customerSearchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeCustomerDropdown();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const firstItem = customerDropdownList.querySelector('.customer-dropdown-item');
            if (firstItem) firstItem.click();
        }
    });
}

document.addEventListener('click', function(e) {
    if (customerDropdownMenu && customerDropdownMenu.style.display === 'block') {
        if (!e.target.closest('.customer-picker-container')) {
            closeCustomerDropdown();
        }
    }
});

// Initial render of top 10 customers
renderCustomerOptions('');

// Customer change handler
const customerSelectEl = document.getElementById('customer_id');
if (customerSelectEl) {
    customerSelectEl.addEventListener('change', function() {
        const customerName = this.value;
        
        // Auto-fill customer phone number
        const selectedOpt = (this.tagName === 'SELECT' && this.options) ? this.options[this.selectedIndex] : null;
        const optPhone = selectedOpt ? selectedOpt.getAttribute('data-phone') : (this.getAttribute('data-phone') || '');
        const phoneInput = document.getElementById('customer_phone');
        if (phoneInput) {
            phoneInput.value = optPhone || '';
        }

        // Reset measurement tags & clear existing cards
        selectedMeasurementIds.clear();
        updateMeasurementTagsUI();
        const container = document.getElementById('orderItemsContainer');
        
        if (customerName) {
            if (container) {
                container.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-spinner fa-spin fa-2x mb-2 text-primary"></i>
                        <div>Loading measurements for ${escapeHtml(customerName)}...</div>
                    </div>
                `;
            }

            fetch(`ajax/get_measurements_by_name.php?name=${encodeURIComponent(customerName)}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network error loading measurements');
                    return response.json();
                })
                .then(data => {
                    if (phoneInput && !phoneInput.value && data && data.length > 0 && data[0].phone_number) {
                        phoneInput.value = data[0].phone_number;
                    }
                    window.currentCustomerMeasurements = data || [];
                    if (container) container.innerHTML = '';
                    selectedMeasurementIds.clear();

                    // Load ONLY this customer's measurements as cards and tags
                    if (data && data.length > 0) {
                        data.forEach((m, idx) => {
                            selectedMeasurementIds.add(String(m.id));
                            addOrderItemCard({
                                measurement_id: m.id,
                                cloth_type_id: m.cloth_type_id,
                                price: ''
                            }, idx === 0);
                        });
                        updateMeasurementTagsUI();
                    } else {
                        updateMeasurementTagsUI();
                        if (container) {
                            container.innerHTML = `
                                <div class="alert alert-warning py-3 px-3 d-flex align-items-center justify-content-between rounded-3" style="border-radius: 10px;">
                                    <div>
                                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                        <strong>No measurements found for this customer!</strong> Please add measurements in the Measurement section first to create an order.
                                    </div>
                                    <a href="measurements.php" class="btn btn-sm btn-warning text-dark fw-semibold ms-3" target="_blank">
                                        <i class="fas fa-plus me-1"></i>Add Measurement
                                    </a>
                                </div>
                            `;
                        }
                    }
                    updateAddItemButtonState();
                })
                .catch(error => {
                    console.error('Error loading measurements:', error);
                    window.currentCustomerMeasurements = [];
                    if (container) container.innerHTML = '';
                    selectedMeasurementIds.clear();
                    updateMeasurementTagsUI();
                    updateAddItemButtonState();
                });
        } else {
            window.currentCustomerMeasurements = [];
            if (container) container.innerHTML = '';
            selectedMeasurementIds.clear();
            updateMeasurementTagsUI();
            updateAddItemButtonState();
        }
    });
}

function editOrder(order) {
    console.log('Editing order:', order);
    const waSwitch = document.getElementById('send_whatsapp_invoice');
    if (waSwitch) waSwitch.checked = false; 
    
    document.getElementById('orderModalTitle').textContent = 'Edit Order';
    document.getElementById('orderAction').value = 'update';
    document.getElementById('orderId').value = order.id || '';
    const customerName = order.customer_name || '';
    document.getElementById('customer_id').value = customerName;
    if (typeof setCustomerValue === 'function') {
        setCustomerValue(customerName, order.customer_phone || '', false);
    }
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

    // Clear items and tags
    selectedMeasurementIds.clear();
    const container = document.getElementById('orderItemsContainer');
    if (container) container.innerHTML = '';
    updateMeasurementTagsUI();

    if (customerName) {
        fetch(`ajax/get_measurements_by_name.php?name=${encodeURIComponent(customerName)}`)
            .then(response => response.json())
            .then(measurements => {
                window.currentCustomerMeasurements = measurements || [];
                renderMeasurementDropdownOptions();
                return fetch(`ajax/get_order_items.php?order_id=${encodeURIComponent(order.id)}`);
            })
            .then(res => res.json())
            .then(items => {
                if (container) container.innerHTML = '';
                selectedMeasurementIds.clear();

                if (items && items.length > 0) {
                    items.forEach((it, idx) => {
                        addOrderItemCard({
                            cloth_type_id: it.cloth_type_id,
                            measurement_id: it.measurement_id,
                            assigned_tailor_id: it.assigned_tailor_id,
                            quantity: it.quantity ?? 1,
                            price: it.price,
                            measurement_data: it.measurement_data,
                            cloth_type_name: it.cloth_type_name,
                            notes: it.notes
                        }, idx === 0);
                    });
                } else {
                    // Fallback to legacy single order
                    addOrderItemCard({
                        cloth_type_id: order.cloth_type_id,
                        measurement_id: order.measurement_id,
                        assigned_tailor_id: order.assigned_tailor_id,
                        price: order.total_amount
                    }, true);
                }
                updateMeasurementTagsUI();
                calculateTotalFromItems();
            })
            .catch(err => {
                console.error('Error loading order items:', err);
                if (container) container.innerHTML = '';
                addOrderItemCard({
                    cloth_type_id: order.cloth_type_id,
                    measurement_id: order.measurement_id,
                    assigned_tailor_id: order.assigned_tailor_id,
                    price: order.total_amount
                }, true);
                updateMeasurementTagsUI();
            });
    } else {
        addOrderItemCard({
            cloth_type_id: order.cloth_type_id,
            measurement_id: order.measurement_id,
            assigned_tailor_id: order.assigned_tailor_id,
            price: order.total_amount
        }, true);
        updateMeasurementTagsUI();
    }

    new bootstrap.Modal(document.getElementById('orderModal')).show();
}

function updateOrderStatusAjax(orderId, status, clickedElement) {
    if (window.event) {
        window.event.preventDefault();
        window.event.stopPropagation();
    }
    const originalText = clickedElement.innerHTML;
    clickedElement.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
    clickedElement.style.pointerEvents = 'none';

    fetch('ajax/update_order_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `order_id=${orderId}&status=${status}&csrf_token=<?php echo generate_csrf_token(); ?>`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            clickedElement.innerHTML = originalText;
            clickedElement.style.pointerEvents = 'auto';

            // Close bootstrap dropdown
            const dropdownMenu = clickedElement.closest('.dropdown-menu');
            if (dropdownMenu) {
                const dropdownToggle = dropdownMenu.parentElement.querySelector('[data-bs-toggle="dropdown"]');
                if (dropdownToggle && window.bootstrap && bootstrap.Dropdown) {
                    const bsDropdown = bootstrap.Dropdown.getInstance(dropdownToggle);
                    if (bsDropdown) bsDropdown.hide();
                }
            }

            // Update UI in-place without page reload
            applyOrderStatusUpdateLocally(orderId, status, data.status_display);
            showToast('Success', 'Order status updated to ' + (data.status_display || status), 'success');

            // Quick poll to refresh stat counters
            if (typeof pollOrderSync === 'function') {
                setTimeout(pollOrderSync, 500);
            }
        } else {
            showToast('Error', data.message || 'Failed to update order status', 'error');
            clickedElement.innerHTML = originalText;
            clickedElement.style.pointerEvents = 'auto';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error', 'An error occurred while updating status', 'error');
        clickedElement.innerHTML = originalText;
        clickedElement.style.pointerEvents = 'auto';
    });
}

// Global order variable for Quick Pay modal
window.currentQuickPayOrder = null;

function openQuickPayModal(order) {
    if (!order) return;
    window.currentQuickPayOrder = order;

    const modalEl = document.getElementById('quickPayModal');
    if (!modalEl) return;

    const orderNum = order.order_number || ('ORD' + order.id);
    document.getElementById('quickPayOrderNum').textContent = '#' + orderNum;
    document.getElementById('quickPayOrderId').value = order.id;
    document.getElementById('quickPayCustomer').textContent = order.customer_name || 'Customer';

    const formatCurr = (val) => '₹' + parseFloat(val || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    
    document.getElementById('quickPayTotal').textContent = formatCurr(order.total_amount);
    document.getElementById('quickPayAdvance').textContent = formatCurr(order.advance_amount);
    document.getElementById('quickPayPendingBadge').textContent = formatCurr(order.balance_amount);

    const methodSelect = document.getElementById('quickPayMethod');
    if (methodSelect) {
        methodSelect.value = (order.payment_method || 'cash').toLowerCase();
        if (!methodSelect.value) methodSelect.value = 'cash';
    }

    const bsModal = new bootstrap.Modal(modalEl);
    bsModal.show();
}

function submitQuickPay() {
    const orderId = document.getElementById('quickPayOrderId')?.value;
    const paymentMethod = document.getElementById('quickPayMethod')?.value || 'cash';
    const csrfToken = document.getElementById('quickPayCsrfToken')?.value || '<?php echo generate_csrf_token(); ?>';
    const submitBtn = document.getElementById('quickPaySubmitBtn');

    if (!orderId) {
        showToast('Error', 'Invalid order selected', 'error');
        return;
    }

    const origBtnHtml = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';
        submitBtn.disabled = true;
    }

    fetch('ajax/quick_pay_order.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `order_id=${encodeURIComponent(orderId)}&payment_method=${encodeURIComponent(paymentMethod)}&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(res => res.json())
    .then(data => {
        if (submitBtn) {
            submitBtn.innerHTML = origBtnHtml;
            submitBtn.disabled = false;
        }

        if (data.success) {
            const modalEl = document.getElementById('quickPayModal');
            if (modalEl) {
                const bsModal = bootstrap.Modal.getInstance(modalEl);
                if (bsModal) bsModal.hide();
            }

            showToast('Success', data.message || 'Payment settled successfully! Order marked as Fully Paid.', 'success');

            // 1. Update Amount column UI (Desktop & Mobile)
            const amountCell = document.getElementById('orderAmountCell_' + orderId);
            if (amountCell) {
                const methodLower = (data.order.payment_method || 'cash').toLowerCase();
                const methodDisplay = methodLower === 'online' ? 'Online' : (methodLower.charAt(0).toUpperCase() + methodLower.slice(1));
                const methodBadgeClass = methodLower === 'online' ? 'bg-primary text-white' : 'bg-light text-dark border';
                amountCell.innerHTML = `
                    <div>
                        <strong class="text-dark" style="font-size: 0.88rem;">${data.order.formatted_total}</strong>
                        <div class="d-flex flex-column gap-1 mt-1" style="font-size: 0.72rem;">
                            <div class="text-muted">
                                Adv: <span class="text-success fw-semibold">${data.order.formatted_advance}</span>
                                <span class="badge ${methodBadgeClass} ms-1" style="font-size: 0.65rem;">
                                    ${escapeHtml(methodDisplay)}
                                </span>
                            </div>
                            <div class="text-success fw-semibold">
                                <i class="fas fa-check-circle me-1"></i>Fully Paid
                            </div>
                        </div>
                    </div>
                `;
            }
            const cardAmountBox = document.getElementById('orderCardAmount_' + orderId);
            if (cardAmountBox) {
                cardAmountBox.innerHTML = `
                    <div class="text-muted" style="font-size: 0.68rem; text-transform: uppercase; font-weight: 600;">Balance</div>
                    <div class="text-success fw-bold" style="font-size: 0.8rem;">
                        <i class="fas fa-check-circle me-1"></i>Fully Paid
                    </div>
                `;
            }

            // 2. Replace Quick Pay button with Fully Paid checkmark (Desktop & Mobile)
            const payBtn = document.getElementById('quickPayBtn_' + orderId);
            if (payBtn) {
                const paidSpan = document.createElement('span');
                paidSpan.className = 'cat-action-btn cat-action-paid';
                paidSpan.id = 'quickPayBtn_' + orderId;
                paidSpan.title = `Fully Paid (${data.order.formatted_total})`;
                paidSpan.innerHTML = '<i class="fas fa-check text-success"></i>';
                payBtn.parentNode.replaceChild(paidSpan, payBtn);
            }
            const mobilePayBtn = document.getElementById('mobileQuickPayBtn_' + orderId);
            if (mobilePayBtn) {
                const paidSpan = document.createElement('span');
                paidSpan.className = 'cat-action-btn cat-action-paid';
                paidSpan.id = 'mobileQuickPayBtn_' + orderId;
                paidSpan.title = `Fully Paid (${data.order.formatted_total})`;
                paidSpan.innerHTML = '<i class="fas fa-check text-success"></i>';
                mobilePayBtn.parentNode.replaceChild(paidSpan, mobilePayBtn);
            }

            // 3. Update Edit button's data so opening Edit modal shows 0 pending (Desktop & Mobile)
            const updateEditBtnData = (btnEl) => {
                if (!btnEl) return;
                const currentOnClick = btnEl.getAttribute('onclick');
                if (currentOnClick && currentOnClick.includes('editOrder(')) {
                    try {
                        const match = currentOnClick.match(/editOrder\(([\s\S]+)\)/);
                        if (match && match[1]) {
                            const orderObj = JSON.parse(match[1]);
                            orderObj.advance_amount = data.order.advance_amount;
                            orderObj.balance_amount = 0.00;
                            orderObj.payment_method = data.order.payment_method;
                            btnEl.setAttribute('onclick', `editOrder(${JSON.stringify(orderObj)})`);
                        }
                    } catch (e) {
                        console.error('Error updating edit button onclick:', e);
                    }
                }
                const dataOrderAttr = btnEl.getAttribute('data-order');
                if (dataOrderAttr) {
                    try {
                        const orderObj = JSON.parse(dataOrderAttr.replace(/&quot;/g, '"').replace(/&#39;/g, "'"));
                        orderObj.advance_amount = data.order.advance_amount;
                        orderObj.balance_amount = 0.00;
                        orderObj.payment_method = data.order.payment_method;
                        btnEl.setAttribute('data-order', JSON.stringify(orderObj));
                    } catch (e) {
                        console.error('Error updating edit button data-order:', e);
                    }
                }
            };
            updateEditBtnData(document.getElementById('editOrderBtn_' + orderId));
            updateEditBtnData(document.getElementById('mobileEditOrderBtn_' + orderId));
        } else {
            showToast('Error', data.error || 'Failed to update payment', 'error');
        }
    })
    .catch(err => {
        console.error('Quick pay error:', err);
        if (submitBtn) {
            submitBtn.innerHTML = origBtnHtml;
            submitBtn.disabled = false;
        }
        showToast('Error', 'An error occurred while updating payment', 'error');
    });
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

document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('orderItemsContainer');
    if (container && container.children.length === 0) {
        addOrderItemCard({}, true);
    }
});

const orderModalEl = document.getElementById('orderModal');
if (orderModalEl) {
    orderModalEl.addEventListener('show.bs.modal', function() {
        const action = document.getElementById('orderAction')?.value;
        const container = document.getElementById('orderItemsContainer');
        const custVal = document.getElementById('customer_id')?.value;
        if (action === 'create' && custVal) {
            document.getElementById('customer_id').dispatchEvent(new Event('change'));
        } else if (action === 'create' && container && container.children.length === 0) {
            addOrderItemCard({}, true);
        }
    });

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
        
        window.currentCustomerMeasurements = [];
        selectedMeasurementIds.clear();
        updateMeasurementTagsUI();
        const topCloth = document.getElementById('topClothTypeSelect');
        if (topCloth) topCloth.value = '';
        const measSearchInp = document.getElementById('measurementSearchInput');
        if (measSearchInp) measSearchInp.value = '';
        const dropdown = document.getElementById('measurementDropdownMenu');
        if (dropdown) dropdown.style.display = 'none';
        if (typeof setCustomerValue === 'function') {
            setCustomerValue('', '', false);
        }
        if (typeof closeCustomerDropdown === 'function') {
            closeCustomerDropdown();
        }
        const custSearchInp = document.getElementById('customerSearchInput');
        if (custSearchInp) custSearchInp.value = '';
        if (typeof renderCustomerOptions === 'function') {
            renderCustomerOptions('');
        }

        const container = document.getElementById('orderItemsContainer');
        if (container) {
            container.innerHTML = '';
            addOrderItemCard({}, true);
        }
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
    const mobileErrorList = document.getElementById('ordersMobileCardsList');
    if (mobileErrorList) {
        mobileErrorList.innerHTML = `
            <div class="text-center py-5">
                <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; border-radius: 50%; background: #fef2f2; color: #ef4444;">
                    <i class="fas fa-exclamation-circle fa-2x"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">Error filtering orders</h5>
                <p class="text-muted small mb-0">Please clear your filters and try again.</p>
            </div>
        `;
    }
}

function displayFilterResults(orders) {
    if (!ordersTable) {
        console.error('Orders table not found');
        return;
    }
    
    const isTailor = <?php echo has_role('tailor') ? 'true' : 'false'; ?>;
    const ordersMobileList = document.getElementById('ordersMobileCardsList');
    
    if (orders.length === 0) {
        ordersTable.innerHTML = `
            <tr>
                <td colspan="${isTailor ? '8' : '9'}" class="text-center py-5">
                    <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; border-radius: 50%; background: #f8fafc; color: #94a3b8;">
                        <i class="fas fa-search fa-2x"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">No orders found</h5>
                    <p class="text-muted small">Try adjusting your filter criteria</p>
                </td>
            </tr>
        `;
        if (ordersMobileList) {
            ordersMobileList.innerHTML = `
                <div class="text-center py-5">
                    <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; border-radius: 50%; background: #f8fafc; color: #94a3b8;">
                        <i class="fas fa-search fa-2x"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">No orders found</h5>
                    <p class="text-muted small">Try adjusting your filter criteria</p>
                </div>
            `;
        }
        return;
    }
    
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
    let cardsHTML = '';
    orders.forEach((order, index) => {
        const orderDate = formatDate(order.order_date);
        const dueDate = formatDate(order.due_date);
        const isOverdue = new Date(order.due_date) < new Date() && !['completed', 'delivered', 'cancelled'].includes(order.status);
        const customerName = order.display_customer_name || 'No Name';
        const initial = customerName.charAt(0).toUpperCase() || 'C';
        const pal = palettes[index % palettes.length];
        const statusBadgeClass = statusClasses[order.status] || 'bg-secondary';
        const statusDisplay = order.status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());

        const orderQty = Math.max(1, parseInt((order.total_quantity > 0 ? order.total_quantity : (order.item_count || 1)), 10) || 1);
        const totalAmt = parseFloat(order.total_amount) || 0;
        const advAmt = parseFloat(order.advance_amount) || 0;
        const pendingAmt = (order.balance_amount !== undefined && order.balance_amount !== null) ? parseFloat(order.balance_amount) : Math.max(0, totalAmt - advAmt);

        const pmLower = (order.payment_method || 'cash').toLowerCase();
        const pmDisplay = pmLower === 'online' ? 'Online' : (pmLower.charAt(0).toUpperCase() + pmLower.slice(1));
        const pmBadgeClass = pmLower === 'online' ? 'bg-primary text-white' : 'bg-light text-dark border';

        // 1. Desktop Table Row
        tableHTML += `
            <tr class="order-row" id="orderRow_${order.id}" data-status="${escapeHtml(order.status)}" data-customer="${escapeHtml(customerName)}">
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
                    <div class="d-inline-flex align-items-center gap-1" style="max-width: 235px;">
                        <span class="badge fw-medium" title="${escapeHtml(order.all_cloth_type_names || order.cloth_type_name || '-')}" style="background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; font-size: 0.78rem; border-radius: 6px; padding: 4px 8px; display: inline-block; max-width: 170px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: middle;">
                            ${escapeHtml(order.all_cloth_type_names || order.cloth_type_name || '-')}
                        </span>
                        ${order.item_count && order.item_count > 1 ? `<span class="badge rounded-pill bg-light text-primary border flex-shrink-0" style="font-size: 0.68rem; white-space: nowrap;">${order.item_count} Items</span>` : ''}
                    </div>
                </td>
                ${!isTailor ? `
                <td class="py-3">
                    ${(order.all_tailor_names || order.tailor_name) ? `
                        <div class="d-flex align-items-center gap-1 text-dark" style="font-size: 0.84rem;">
                            <i class="fas fa-user-tie text-muted" style="font-size: 0.75rem;"></i>
                            <span>${escapeHtml(order.all_tailor_names || order.tailor_name)}</span>
                        </div>
                    ` : '<span class="text-muted" style="font-size: 0.8rem; font-style: italic;">Unassigned</span>'}
                </td>
                ` : ''}
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
                <td class="py-3" id="orderAmountCell_${order.id}">
                    <div>
                        <strong class="text-dark" style="font-size: 0.88rem;">${formatCurrency(order.total_amount)}</strong>
                        <div class="d-flex flex-column gap-1 mt-1" style="font-size: 0.72rem;">
                            <div class="text-muted">
                                Adv: <span class="text-success fw-semibold">${formatCurrency(order.advance_amount || 0)}</span>
                                <span class="badge ${pmBadgeClass} ms-1" style="font-size: 0.65rem;">
                                    ${escapeHtml(pmDisplay)}
                                </span>
                            </div>
                            ${pendingAmt > 0 ? `
                                <div class="text-danger fw-semibold">
                                    Pending: ${formatCurrency(pendingAmt)}
                                </div>
                            ` : `
                                <div class="text-success fw-semibold">
                                    <i class="fas fa-check-circle me-1"></i>Fully Paid
                                </div>
                            `}
                        </div>
                    </div>
                </td>
                <td class="py-3 text-center">
                    <span class="badge rounded-pill fw-semibold px-2 py-1" style="background: #eef2ff; color: #4f46e5; border: 1px solid #c7d2fe; font-size: 0.78rem;">
                        ${orderQty}
                    </span>
                </td>
                <td class="pe-3 py-3 text-end">
                    <div class="d-inline-flex align-items-center gap-1">
                        ${!isTailor ? `
                        ${pendingAmt > 0 ? `
                        <button type="button" 
                                class="cat-action-btn cat-action-pay quick-pay-btn" 
                                id="quickPayBtn_${order.id}"
                                data-order='${JSON.stringify({
                                    id: order.id,
                                    order_number: order.order_number || ('ORD' + order.id),
                                    customer_name: order.display_customer_name || order.customer_name || 'Customer',
                                    total_amount: totalAmt,
                                    advance_amount: advAmt,
                                    balance_amount: pendingAmt,
                                    payment_method: order.payment_method || 'cash'
                                }).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;')}'
                                title="Mark as Fully Paid (Pending: ${formatCurrency(pendingAmt)})">
                            <i class="fas fa-rupee-sign"></i>
                        </button>
                        ` : `
                        <span class="cat-action-btn cat-action-paid" 
                              id="quickPayBtn_${order.id}"
                              title="Fully Paid (${formatCurrency(totalAmt)})">
                            <i class="fas fa-check text-success"></i>
                        </span>
                        `}
                        <button type="button" 
                                class="cat-action-btn cat-action-edit edit-order-btn" 
                                id="editOrderBtn_${order.id}"
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
                        <div class="dropdown d-inline-block">
                            <button class="cat-action-btn cat-action-status dropdown-toggle" 
                                    type="button" 
                                    data-bs-toggle="dropdown"
                                    data-bs-popper-config='{"strategy":"fixed"}'
                                    aria-expanded="false"
                                    title="Change Status">
                                <i class="fas fa-cog"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" onclick="event.stopPropagation();" style="border-radius: 12px; border: 1px solid #e2e8f0; font-size: 0.85rem; z-index: 1060;">
                                <li><h6 class="dropdown-header text-uppercase text-muted" style="font-size: 0.68rem; letter-spacing: 0.5px;">Update Status</h6></li>
                                <li><a class="dropdown-item py-1" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(${order.id}, 'pending', this); return false;"><i class="fas fa-clock text-warning me-2"></i>Pending</a></li>
                                <li><a class="dropdown-item py-1" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(${order.id}, 'in_progress', this); return false;"><i class="fas fa-cogs text-primary me-2"></i>In Progress</a></li>
                                <li><a class="dropdown-item py-1" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(${order.id}, 'completed', this); return false;"><i class="fas fa-check-circle text-success me-2"></i>Completed</a></li>
                                <li><a class="dropdown-item py-1" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(${order.id}, 'delivered', this); return false;"><i class="fas fa-truck text-purple me-2" style="color: #8b5cf6;"></i>Delivered</a></li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li><a class="dropdown-item py-1 text-danger" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(${order.id}, 'cancelled', this); return false;"><i class="fas fa-times-circle me-2"></i>Cancelled</a></li>
                            </ul>
                        </div>
                        ${canGenerateInvoice ? `
                        <button type="button" 
                                class="cat-action-btn cat-action-print" 
                                onclick="printOrderInvoice(${order.id})" 
                                title="Print Invoice">
                            <i class="fas fa-print"></i>
                        </button>
                        ` : ''}
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

        // 2. Mobile Card
        cardsHTML += `
            <div class="order-mobile-card shadow-sm" id="orderCard_${order.id}" data-status="${escapeHtml(order.status)}" data-customer="${escapeHtml(customerName)}">
                <!-- Header: Order # & Status -->
                <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                    <span class="badge fw-semibold" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; font-size: 0.8rem; padding: 5px 9px; border-radius: 8px;">
                        #${escapeHtml(order.order_number)}
                    </span>
                    <span class="badge rounded-pill fw-semibold px-2 py-1 ${statusBadgeClass}" style="font-size: 0.75rem;">
                        ${escapeHtml(statusDisplay)}
                    </span>
                </div>

                <!-- Customer Row -->
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-2 min-w-0">
                        <div class="d-flex align-items-center justify-content-center flex-shrink-0 fw-bold" style="width: 38px; height: 38px; border-radius: 10px; background: ${pal.bg}; border: 1px solid ${pal.border}; color: ${pal.color}; font-size: 0.9rem;">
                            ${escapeHtml(initial)}
                        </div>
                        <div class="min-w-0">
                            <div class="fw-bold text-dark text-truncate" style="font-size: 0.9rem;">
                                ${escapeHtml(customerName)}
                            </div>
                            ${order.customer_phone ? `
                                <a href="tel:${escapeHtml(order.customer_phone)}" class="text-decoration-none text-muted d-inline-flex align-items-center gap-1" style="font-size: 0.78rem;">
                                    <i class="fas fa-phone text-primary" style="font-size: 0.7rem;"></i> ${escapeHtml(order.customer_phone)}
                                </a>
                            ` : ''}
                        </div>
                    </div>
                </div>

                <!-- Order Info Grid -->
                <div class="rounded-3 p-2 mb-3" style="background: #f8fafc; border: 1px solid #edf2f7; font-size: 0.8rem;">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="text-muted" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 600;">Cloth Type</div>
                            <div class="d-flex align-items-center flex-wrap gap-1 mt-1">
                                <span class="badge fw-medium text-dark text-truncate" style="background: #ffffff; border: 1px solid #e2e8f0; font-size: 0.75rem; max-width: 100%;">
                                    ${escapeHtml(order.all_cloth_type_names || order.cloth_type_name || '-')}
                                </span>
                                ${order.item_count && order.item_count > 1 ? `<span class="badge rounded-pill bg-light text-primary border" style="font-size: 0.65rem;">${order.item_count} Items</span>` : ''}
                            </div>
                        </div>
                        ${!isTailor ? `
                        <div class="col-6">
                            <div class="text-muted" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 600;">Tailor</div>
                            <div class="mt-1">
                                ${(order.all_tailor_names || order.tailor_name) ? `
                                    <div class="d-flex align-items-center gap-1 text-dark text-truncate" style="font-size: 0.8rem;">
                                        <i class="fas fa-user-tie text-muted flex-shrink-0" style="font-size: 0.72rem;"></i>
                                        <span class="text-truncate">${escapeHtml(order.all_tailor_names || order.tailor_name)}</span>
                                    </div>
                                ` : '<span class="text-muted" style="font-size: 0.75rem; font-style: italic;">Unassigned</span>'}
                            </div>
                        </div>
                        ` : ''}
                        <div class="col-6">
                            <div class="text-muted" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 600;">Order Date</div>
                            <div class="text-dark fw-medium mt-1">${orderDate}</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 600;">Due Date</div>
                            <div class="mt-1 ${isOverdue ? 'text-danger fw-bold' : 'text-dark fw-medium'}">
                                ${isOverdue ? '<i class="fas fa-exclamation-circle text-danger me-1"></i>' : ''}${dueDate}
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 600;">Quantity</div>
                            <div class="mt-1">
                                <span class="badge rounded-pill fw-semibold px-2 py-1" style="background: #eef2ff; color: #4f46e5; border: 1px solid #c7d2fe; font-size: 0.75rem;">
                                    ${orderQty}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Financial Box -->
                <div class="d-flex align-items-center justify-content-between p-2 rounded-3 mb-3" style="background: #ffffff; border: 1px solid #e2e8f0;">
                    <div>
                        <div class="text-muted" style="font-size: 0.68rem; text-transform: uppercase; font-weight: 600;">Total Amount</div>
                        <div class="fw-bold text-dark" style="font-size: 0.95rem;">${formatCurrency(order.total_amount)}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-muted" style="font-size: 0.68rem; text-transform: uppercase; font-weight: 600;">Advance</div>
                        <div class="text-success fw-semibold" style="font-size: 0.85rem;">
                            ${formatCurrency(order.advance_amount || 0)}
                            <span class="badge ${pmBadgeClass}" style="font-size: 0.62rem;">${escapeHtml(pmDisplay)}</span>
                        </div>
                    </div>
                    <div class="text-end" id="orderCardAmount_${order.id}">
                        <div class="text-muted" style="font-size: 0.68rem; text-transform: uppercase; font-weight: 600;">Balance</div>
                        ${pendingAmt > 0 ? `
                            <div class="text-danger fw-bold" style="font-size: 0.85rem;">
                                ${formatCurrency(pendingAmt)}
                            </div>
                        ` : `
                            <div class="text-success fw-bold" style="font-size: 0.8rem;">
                                <i class="fas fa-check-circle me-1"></i>Fully Paid
                            </div>
                        `}
                    </div>
                </div>

                <!-- Action Bar in Card Footer -->
                <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                    <span class="text-muted small" style="font-size: 0.72rem;">Actions</span>
                    <div class="d-inline-flex align-items-center gap-1">
                        ${!isTailor ? `
                        ${pendingAmt > 0 ? `
                        <button type="button" 
                                class="cat-action-btn cat-action-pay quick-pay-btn" 
                                id="mobileQuickPayBtn_${order.id}"
                                data-order='${JSON.stringify({
                                    id: order.id,
                                    order_number: order.order_number || ('ORD' + order.id),
                                    customer_name: order.display_customer_name || order.customer_name || 'Customer',
                                    total_amount: totalAmt,
                                    advance_amount: advAmt,
                                    balance_amount: pendingAmt,
                                    payment_method: order.payment_method || 'cash'
                                }).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;')}'
                                title="Mark as Fully Paid (Pending: ${formatCurrency(pendingAmt)})">
                            <i class="fas fa-rupee-sign"></i>
                        </button>
                        ` : `
                        <span class="cat-action-btn cat-action-paid" 
                              id="mobileQuickPayBtn_${order.id}"
                              title="Fully Paid (${formatCurrency(totalAmt)})">
                            <i class="fas fa-check text-success"></i>
                        </span>
                        `}
                        <button type="button" 
                                class="cat-action-btn cat-action-edit edit-order-btn" 
                                id="mobileEditOrderBtn_${order.id}"
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
                        <div class="dropdown d-inline-block">
                            <button class="cat-action-btn cat-action-status dropdown-toggle" 
                                    type="button" 
                                    data-bs-toggle="dropdown"
                                    data-bs-popper-config='{"strategy":"fixed"}'
                                    aria-expanded="false"
                                    title="Change Status">
                                <i class="fas fa-cog"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" onclick="event.stopPropagation();" style="border-radius: 12px; border: 1px solid #e2e8f0; font-size: 0.85rem; z-index: 1060;">
                                <li><h6 class="dropdown-header text-uppercase text-muted" style="font-size: 0.68rem; letter-spacing: 0.5px;">Update Status</h6></li>
                                <li><a class="dropdown-item py-1" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(${order.id}, 'pending', this); return false;"><i class="fas fa-clock text-warning me-2"></i>Pending</a></li>
                                <li><a class="dropdown-item py-1" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(${order.id}, 'in_progress', this); return false;"><i class="fas fa-cogs text-primary me-2"></i>In Progress</a></li>
                                <li><a class="dropdown-item py-1" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(${order.id}, 'completed', this); return false;"><i class="fas fa-check-circle text-success me-2"></i>Completed</a></li>
                                <li><a class="dropdown-item py-1" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(${order.id}, 'delivered', this); return false;"><i class="fas fa-truck text-purple me-2" style="color: #8b5cf6;"></i>Delivered</a></li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li><a class="dropdown-item py-1 text-danger" href="#" onclick="event.preventDefault(); event.stopPropagation(); updateOrderStatusAjax(${order.id}, 'cancelled', this); return false;"><i class="fas fa-times-circle me-2"></i>Cancelled</a></li>
                            </ul>
                        </div>
                        ${canGenerateInvoice ? `
                        <button type="button" 
                                class="cat-action-btn cat-action-print" 
                                onclick="printOrderInvoice(${order.id})" 
                                title="Print Invoice">
                            <i class="fas fa-print"></i>
                        </button>
                        ` : ''}
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
                </div>
            </div>
        `;
    });
    
    ordersTable.innerHTML = tableHTML;
    if (ordersMobileList) {
        ordersMobileList.innerHTML = cardsHTML;
    }
    
    // Bind click events to both desktop and mobile buttons
    document.querySelectorAll('.quick-pay-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            try {
                const orderJson = this.getAttribute('data-order')
                    .replace(/&quot;/g, '"')
                    .replace(/&#39;/g, "'")
                    .replace(/&lt;/g, '<')
                    .replace(/&gt;/g, '>')
                    .replace(/&amp;/g, '&');
                const order = JSON.parse(orderJson);
                openQuickPayModal(order);
            } catch (e) {
                console.error('Error parsing quick pay order data:', e);
            }
        });
    });
    document.querySelectorAll('.edit-order-btn').forEach(btn => {
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
                showOrderAlert('Failed to load order details. Please try again.', 'Error', 'danger');
            }
        });
    });
    document.querySelectorAll('.send-order-invoice-btn').forEach(btn => {
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
    const mobileCardsList = document.getElementById('ordersMobileCardsList');
    if (mobileCardsList) {
        const cards = Array.from(mobileCardsList.querySelectorAll('.order-mobile-card'));
        cards.sort((a, b) => {
            const nameA = a.getAttribute('data-customer') || '';
            const nameB = b.getAttribute('data-customer') || '';
            return sortAscending ? nameA.localeCompare(nameB) : nameB.localeCompare(nameA);
        });
        cards.forEach(c => mobileCardsList.appendChild(c));
    }
});
(function() {
    let activeFloatingToggle = null;
    let activeFloatingMenu = null;

    function positionFloatingDropdown(toggle, menu) {
        if (!toggle || !menu || !menu.classList.contains('show')) return;
        const toggleRect = toggle.getBoundingClientRect();
        if (toggleRect.width === 0 && toggleRect.height === 0) return;

        menu.style.position = 'fixed';
        menu.style.zIndex = '9999';
        menu.style.pointerEvents = 'auto';
        menu.style.minWidth = '165px';
        menu.style.margin = '0';
        menu.style.right = 'auto';
        menu.style.bottom = 'auto';
        menu.style.transform = 'none';
        menu.style.inset = 'auto';
        menu.style.visibility = 'visible';
        menu.style.opacity = '1';

        const menuWidth = menu.offsetWidth || 165;
        const menuHeight = menu.offsetHeight || 220;
        const viewportHeight = window.innerHeight;
        const viewportWidth = window.innerWidth;
        const gap = 6;

        let top = toggleRect.bottom + gap;
        if (top + menuHeight > viewportHeight - 10) {
            if (toggleRect.top - menuHeight - gap >= 10) {
                top = toggleRect.top - menuHeight - gap;
            } else {
                top = Math.max(10, viewportHeight - menuHeight - 10);
            }
        }

        let left = toggleRect.right - menuWidth;
        if (left + menuWidth > viewportWidth - 10) {
            left = viewportWidth - menuWidth - 10;
        }
        if (left < 10) {
            left = 10;
        }

        menu.style.top = top + 'px';
        menu.style.left = left + 'px';
    }

    document.addEventListener('shown.bs.dropdown', function(e) {
        const toggle = e.target;
        const dropdown = toggle.closest('.dropdown');
        if (!dropdown || (!dropdown.closest('.table td') && !dropdown.closest('.order-mobile-card'))) return;

        const menu = toggle._orderStatusMenu || dropdown.querySelector('.dropdown-menu');
        if (!menu) return;

        toggle._orderStatusMenu = menu;
        if (!menu._origParent) {
            menu._origParent = dropdown;
            menu._origNextSibling = menu.nextSibling;
        }

        // Portal to body so parent overflow/transform (.table-responsive, .card:hover) never clips or traps fixed positioning
        if (menu.parentElement !== document.body) {
            document.body.appendChild(menu);
        }

        activeFloatingToggle = toggle;
        activeFloatingMenu = menu;

        positionFloatingDropdown(toggle, menu);
        requestAnimationFrame(function() {
            positionFloatingDropdown(toggle, menu);
        });
    });

    document.addEventListener('hidden.bs.dropdown', function(e) {
        const toggle = e.target;
        const menu = toggle._orderStatusMenu;
        if (!menu) return;

        if (activeFloatingMenu === menu) {
            activeFloatingToggle = null;
            activeFloatingMenu = null;
        }

        menu.style.top = '';
        menu.style.left = '';
        menu.style.right = '';
        menu.style.bottom = '';
        menu.style.position = '';
        menu.style.transform = '';
        menu.style.inset = '';
        menu.style.margin = '';

        if (menu._origParent && menu._origParent.isConnected) {
            if (menu._origNextSibling && menu._origNextSibling.parentNode === menu._origParent) {
                menu._origParent.insertBefore(menu, menu._origNextSibling);
            } else {
                menu._origParent.appendChild(menu);
            }
        } else if (menu.parentElement === document.body) {
            menu.remove();
        }
    });

    window.addEventListener('scroll', function() {
        if (activeFloatingToggle && activeFloatingMenu) {
            positionFloatingDropdown(activeFloatingToggle, activeFloatingMenu);
        }
    }, true);

    window.addEventListener('resize', function() {
        if (activeFloatingToggle && activeFloatingMenu) {
            positionFloatingDropdown(activeFloatingToggle, activeFloatingMenu);
        }
    });
})();

const canSendInvoice = <?php echo $canSendInvoice ? 'true' : 'false'; ?>;
const canGenerateInvoice = <?php echo $canGenerateInvoice ? 'true' : 'false'; ?>;

// Print Invoice directly
function printOrderInvoice(orderId) {
    if (!orderId) return;
    if (!canGenerateInvoice) {
        showOrderAlert('Print / Invoice generation feature is not available in the Free plan. Please upgrade your subscription to Basic or Premium plan.', 'Subscription Upgrade Required', 'warning', { text: 'Upgrade Plan', url: 'subscriptions.php' });
        return;
    }
    window.open('print-invoice.php?order_id=' + orderId, '_blank');
}

// Global order variable for Send Invoice modal
window.currentSendInvoiceOrder = null;

function openSendInvoiceModal(order) {
    if (!order) return;
    if (!canSendInvoice) {
        showOrderAlert('Send invoice feature is not available in the Free plan. Please upgrade your subscription to Basic or Premium plan.', 'Subscription Upgrade Required', 'warning', { text: 'Upgrade Plan', url: 'subscriptions.php' });
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
    
    let msg = '';
    const centerWidth = 55;
    if (typeof currentCompanyName !== 'undefined' && currentCompanyName && currentCompanyName.trim()) {
        const cleanName = currentCompanyName.trim().toUpperCase();
        const padComp = Math.max(0, Math.floor((centerWidth - (cleanName.length + 2)) / 2));
        msg += ' '.repeat(padComp) + `*${cleanName}*\n`;
    }
    msg += `*Order Receipt*\n`;
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
        showOrderAlert('Please enter a valid 10-digit WhatsApp phone number.', 'Invalid Phone Number', 'warning');
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
        showOrderAlert('Message copied to clipboard!', 'Copied', 'success');
    });
}

function printOrderInvoiceFromModal() {
    if (window.currentSendInvoiceOrder && window.currentSendInvoiceOrder.id) {
        printOrderInvoice(window.currentSendInvoiceOrder.id);
    }
}

// --- Real-time In-Place Updates & Live Polling Synchronization ---
function applyOrderStatusUpdateLocally(orderId, newStatus, statusDisplay) {
    const display = statusDisplay || (newStatus.charAt(0).toUpperCase() + newStatus.slice(1).replace('_', ' '));
    const badgeClass = 'cat-badge-' + newStatus;
    const allBadgeClasses = ['cat-badge-pending', 'cat-badge-in_progress', 'cat-badge-completed', 'cat-badge-delivered', 'cat-badge-cancelled'];

    // Desktop row
    const row = document.getElementById('orderRow_' + orderId);
    if (row) {
        row.setAttribute('data-status', newStatus);
        const badge = row.querySelector('.cat-badge-pending, .cat-badge-in_progress, .cat-badge-completed, .cat-badge-delivered, .cat-badge-cancelled');
        if (badge) {
            allBadgeClasses.forEach(cls => badge.classList.remove(cls));
            badge.classList.add(badgeClass);
            badge.textContent = display;
        }
        row.classList.remove('order-row-highlight');
        void row.offsetWidth; // trigger reflow
        row.classList.add('order-row-highlight');
    }

    // Mobile card
    const card = document.getElementById('orderCard_' + orderId);
    if (card) {
        card.setAttribute('data-status', newStatus);
        const cardBadge = card.querySelector('.cat-badge-pending, .cat-badge-in_progress, .cat-badge-completed, .cat-badge-delivered, .cat-badge-cancelled');
        if (cardBadge) {
            allBadgeClasses.forEach(cls => cardBadge.classList.remove(cls));
            cardBadge.classList.add(badgeClass);
            cardBadge.textContent = display;
        }
        card.classList.remove('order-row-highlight');
        void card.offsetWidth;
        card.classList.add('order-row-highlight');
    }
}

function playSyncNotificationSound() {
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const now = ctx.currentTime;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, now); // D5
        osc.frequency.setValueAtTime(880, now + 0.1); // A5
        gain.gain.setValueAtTime(0.15, now);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(now);
        osc.stop(now + 0.35);
    } catch (e) {
        // Audio might be muted until user gesture
    }
}

let lastOrderSyncTime = '<?php echo date("Y-m-d H:i:s"); ?>';
let isOrderSyncActive = false;
let orderSyncInterval = null;

function getVisibleOrderIds() {
    const ids = [];
    document.querySelectorAll('.order-row[id^="orderRow_"]').forEach(el => {
        const id = parseInt(el.id.replace('orderRow_', ''), 10);
        if (id > 0 && !ids.includes(id)) ids.push(id);
    });
    document.querySelectorAll('.order-mobile-card[id^="orderCard_"]').forEach(el => {
        const id = parseInt(el.id.replace('orderCard_', ''), 10);
        if (id > 0 && !ids.includes(id)) ids.push(id);
    });
    return ids;
}

function pollOrderSync() {
    if (isOrderSyncActive) return;
    isOrderSyncActive = true;

    const visibleIds = getVisibleOrderIds();
    const formData = new FormData();
    formData.append('last_sync', lastOrderSyncTime);
    formData.append('visible_ids', visibleIds.join(','));

    fetch('ajax/sync_orders.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        isOrderSyncActive = false;
        if (!data || !data.success) return;

        if (data.server_time) {
            lastOrderSyncTime = data.server_time;
        }

        // 1. Update status statistics cards
        if (data.stats) {
            ['pending', 'in_progress', 'completed', 'delivered'].forEach(st => {
                const el = document.getElementById('stat-' + st);
                if (el && typeof data.stats[st] !== 'undefined') {
                    el.textContent = Number(data.stats[st]).toLocaleString();
                }
            });
        }

        // 2. Handle removed orders (tailor unassigned from order)
        if (data.removed_order_ids && data.removed_order_ids.length > 0) {
            data.removed_order_ids.forEach(remId => {
                const row = document.getElementById('orderRow_' + remId);
                const card = document.getElementById('orderCard_' + remId);
                if (row) {
                    row.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(20px)';
                    setTimeout(() => row.remove(), 400);
                }
                if (card) {
                    card.style.transition = 'opacity 0.4s ease';
                    card.style.opacity = '0';
                    setTimeout(() => card.remove(), 400);
                }
            });
        }

        // 3. Update existing visible orders with new status
        if (data.status_updates && data.status_updates.length > 0) {
            data.status_updates.forEach(u => {
                applyOrderStatusUpdateLocally(u.id, u.status, u.status_display);
                showToast('Status Updated', `Order #${u.order_number} is now ${u.status_display}`, 'info');
            });
        }

        // 4. Handle new orders assigned or created
        if (data.new_orders && data.new_orders.length > 0) {
            playSyncNotificationSound();
            const orderNums = data.new_orders.map(o => '#' + o.order_number).join(', ');
            showToast('New Order Assigned', `You have new order(s): ${orderNums}`, 'success');

            // Refresh orders table dynamically via existing executeFilter() without page refresh!
            if (typeof executeFilter === 'function') {
                executeFilter();
            }
        }
    })
    .catch(err => {
        isOrderSyncActive = false;
        console.warn('Real-time sync notice:', err);
    });
}

function startOrderSyncTimer() {
    if (orderSyncInterval) clearInterval(orderSyncInterval);
    const intervalMs = document.hidden ? 10000 : 4000;
    orderSyncInterval = setInterval(pollOrderSync, intervalMs);
}

document.addEventListener('visibilitychange', function() {
    startOrderSyncTimer();
    if (!document.hidden) {
        pollOrderSync();
    }
});

// Start real-time sync polling
startOrderSyncTimer();

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
        min-width: 165px;
        pointer-events: auto !important;
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