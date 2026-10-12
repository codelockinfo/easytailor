<?php
require_once '../config/config.php';

require_login();

require_once 'models/Invoice.php';
require_once 'models/Order.php';
require_once 'models/Payment.php';
require_once '../helpers/SubscriptionHelper.php';

$invoiceModel = new Invoice();
$orderModel = new Order();
$paymentModel = new Payment();

$message = '';
$messageType = '';
$companyId = get_company_id();
if (isset($_GET['fix_advance']) && $_GET['fix_advance'] === '1') {
   
    $invoices = $invoiceModel->getInvoicesWithDetails();
    $fixed = 0;
    
    foreach ($invoices as $invoice) {
        $order = $orderModel->find($invoice['order_id']);
        if ($order && $order['advance_amount'] > 0) {
            $totalAmount = $invoice['total_amount'];
            $advanceAmount = (float)$order['advance_amount'];
            $balanceAmount = $totalAmount - $advanceAmount;
            
            if ($invoice['paid_amount'] == 0 && $invoice['balance_amount'] == $totalAmount) {
                $invoiceModel->update($invoice['id'], [
                    'paid_amount' => $advanceAmount,
                    'balance_amount' => $balanceAmount
                ]);
                $invoiceModel->updatePaymentStatus($invoice['id']);
                $fixed++;
            }
        }
    }
    
    $_SESSION['message'] = "Fixed {$fixed} invoices with advance payments";
    $_SESSION['messageType'] = 'success';
    header('Location: invoices.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                $invoiceCheck = SubscriptionHelper::canGenerateInvoice($companyId);
                if (!$invoiceCheck['allowed']) {
                    $_SESSION['message'] = $invoiceCheck['message'] . ' ' . SubscriptionHelper::getUpgradeMessage('invoices', SubscriptionHelper::getCurrentPlan($companyId));
                    $_SESSION['messageType'] = 'error';
                    header('Location: invoices.php');
                    exit;
                }
                
                $order = $orderModel->find((int)$_POST['order_id']);
                $advanceAmount = $order ? (float)$order['advance_amount'] : 0;
                $totalAmount = (float)$_POST['total_amount'];
                $balanceAmount = $totalAmount - $advanceAmount;
                
                $data = [
                    'order_id' => (int)$_POST['order_id'],
                    'invoice_date' => $_POST['invoice_date'],
                    'due_date' => $_POST['due_date'],
                    'subtotal' => (float)$_POST['subtotal'],
                    'tax_rate' => (float)$_POST['tax_rate'],
                    'tax_amount' => (float)$_POST['tax_amount'],
                    'discount_amount' => (float)$_POST['discount_amount'],
                    'total_amount' => $totalAmount,
                    'paid_amount' => $advanceAmount,
                    'balance_amount' => $balanceAmount, 
                    'notes' => sanitize_input($_POST['notes']),
                    'created_by' => get_user_id()
                ];
                
                $invoiceId = $invoiceModel->createInvoice($data);
                if ($invoiceId) {

                    $invoiceModel->updatePaymentStatus($invoiceId);
                    $invoice = $invoiceModel->find($invoiceId);
                    require_once '../helpers/GA4Helper.php';
                    $_SESSION['ga4_event'] = GA4Helper::trackCreateInvoice(
                        $invoiceId,
                        $invoice['invoice_number'] ?? null,
                        $data['total_amount'] ?? null
                    );
                    $_SESSION['message'] = 'Invoice created successfully';
                    $_SESSION['messageType'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to create invoice';
                    $_SESSION['messageType'] = 'error';
                }
                header('Location: invoices.php');
                exit;
                break;
                
            case 'update':
                $invoiceCheck = SubscriptionHelper::canGenerateInvoice($companyId);
                if (!$invoiceCheck['allowed']) {
                    $_SESSION['message'] = $invoiceCheck['message'] . ' ' . SubscriptionHelper::getUpgradeMessage('invoices', SubscriptionHelper::getCurrentPlan($companyId));
                    $_SESSION['messageType'] = 'error';
                    header('Location: invoices.php');
                    exit;
                }
                
                $invoiceId = (int)$_POST['invoice_id'];
                $existingInvoice = $invoiceModel->find($invoiceId);
                
                if (!$existingInvoice) {
                    $_SESSION['message'] = 'Invoice not found';
                    $_SESSION['messageType'] = 'error';
                    header('Location: invoices.php');
                    exit;
                }
                
                $order = $orderModel->find((int)$_POST['order_id']);
                $advanceAmount = $order ? (float)$order['advance_amount'] : 0;
                $totalAmount = (float)$_POST['total_amount'];
                                $currentPaidAmount = (float)$existingInvoice['paid_amount'];
                $newPaidAmount = max($advanceAmount, $currentPaidAmount);
                $balanceAmount = $totalAmount - $newPaidAmount;
                
                $data = [
                    'order_id' => (int)$_POST['order_id'],
                    'invoice_date' => $_POST['invoice_date'],
                    'due_date' => $_POST['due_date'],
                    'subtotal' => (float)$_POST['subtotal'],
                    'tax_rate' => (float)$_POST['tax_rate'],
                    'tax_amount' => (float)$_POST['tax_amount'],
                    'discount_amount' => (float)$_POST['discount_amount'],
                    'total_amount' => $totalAmount,
                    'paid_amount' => $newPaidAmount,
                    'balance_amount' => $balanceAmount,
                    'notes' => sanitize_input($_POST['notes'])
                ];
                
                if ($invoiceModel->update($invoiceId, $data)) {
                    $invoiceModel->updatePaymentStatus($invoiceId);
                    $_SESSION['message'] = 'Invoice updated successfully';
                    $_SESSION['messageType'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to update invoice';
                    $_SESSION['messageType'] = 'error';
                }
                header('Location: invoices.php');
                exit;
                break;
                
            case 'add_payment':
                $data = [
                    'invoice_id' => (int)$_POST['invoice_id'],
                    'amount' => (float)$_POST['amount'],
                    'payment_method' => $_POST['payment_method'],
                    'payment_date' => $_POST['payment_date'],
                    'reference_number' => sanitize_input($_POST['reference_number']),
                    'notes' => sanitize_input($_POST['notes']),
                    'created_by' => get_user_id()
                ];
                
                $paymentId = $paymentModel->createPayment($data);
                if ($paymentId) {
                    $_SESSION['message'] = 'Payment added successfully';
                    $_SESSION['messageType'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to add payment';
                    $_SESSION['messageType'] = 'error';
                }
                header('Location: invoices.php');
                exit;
                break;
                
            case 'delete_payment':
                $paymentId = (int)$_POST['payment_id'];
                if ($paymentModel->deletePayment($paymentId)) {
                    $_SESSION['message'] = 'Payment deleted successfully';
                    $_SESSION['messageType'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to delete payment';
                    $_SESSION['messageType'] = 'error';
                }
                header('Location: invoices.php');
                exit;
                break;

            case 'update':
                $invoiceId = (int)$_POST['invoice_id'];
                $existingInvoice = $invoiceModel->find($invoiceId);
                
                if (!$existingInvoice) {
                    $_SESSION['message'] = 'Invoice not found or access denied';
                    $_SESSION['messageType'] = 'error';
                    header('Location: invoices.php');
                    exit;
                }
                
                $orderId = (int)$_POST['order_id'];
                $order = $orderModel->find($orderId);
                if (!$order) {
                    $_SESSION['message'] = 'Invalid order selected';
                    $_SESSION['messageType'] = 'error';
                    header('Location: invoices.php');
                    exit;
                }
                
                $subtotal = (float)$_POST['subtotal'];
                $taxRate = (float)$_POST['tax_rate'];
                $taxAmount = (float)$_POST['tax_amount'];
                $discountAmount = (float)$_POST['discount_amount'];
                $totalAmount = (float)$_POST['total_amount'];
                $paidAmount = (float)$existingInvoice['paid_amount'];
                $balanceAmount = max($totalAmount - $paidAmount, 0);
                $paymentStatus = $paidAmount >= $totalAmount ? 'paid' : ($paidAmount > 0 ? 'partial' : 'due');
                
                $data = [
                    'order_id' => $orderId,
                    'invoice_date' => $_POST['invoice_date'],
                    'due_date' => $_POST['due_date'],
                    'subtotal' => $subtotal,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'discount_amount' => $discountAmount,
                    'total_amount' => $totalAmount,
                    'balance_amount' => $balanceAmount,
                    'payment_status' => $paymentStatus,
                    'notes' => sanitize_input($_POST['notes'])
                ];
                
                if ($invoiceModel->update($invoiceId, $data)) {
                    $_SESSION['message'] = 'Invoice updated successfully';
                    $_SESSION['messageType'] = 'success';
                } else {
                    $_SESSION['message'] = 'Failed to update invoice';
                    $_SESSION['messageType'] = 'error';
                }
                header('Location: invoices.php');
                exit;
                break;
        }
    } else {
        $_SESSION['message'] = 'Invalid request';
        $_SESSION['messageType'] = 'error';
        header('Location: invoices.php');
        exit;
    }
}

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['messageType']);
}

$page_title = 'Invoice Management';
require_once 'includes/header.php';

$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = RECORDS_PER_PAGE;
$offset = ($page - 1) * $limit;

$conditions = [];
if (!empty($status_filter)) {
    $conditions['payment_status'] = $status_filter;
}

$searchParam = !empty($search) ? trim($search) : null;
$allInvoices = $invoiceModel->getInvoicesWithDetails($conditions, null, 0, $searchParam);
$totalInvoices = count($allInvoices);

if ($limit > 0) {
    $invoices = array_slice($allInvoices, $offset, $limit);
    $totalPages = max(1, ceil($totalInvoices / $limit));
} else {
    $invoices = $allInvoices;
    $totalPages = 1;
}

$allOrders = $orderModel->getOrdersWithDetails([], 100);

$unpaidOrders = [];
foreach ($allOrders as $order) {
    if ($order['status'] !== 'cancelled') {
        $existingInvoices = $invoiceModel->findAll(['order_id' => $order['id']]);
        
        if (empty($existingInvoices)) {
            $unpaidOrders[] = $order;
        }
    }
}
$invoiceStats = $invoiceModel->getInvoiceStats();

$currentPlan = SubscriptionHelper::getCurrentPlan($companyId);
$invoiceCheck = SubscriptionHelper::canGenerateInvoice($companyId);

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
.cat-action-pay {
    background: #f0fdf4;
    border-color: #bbf7d0;
    color: #16a34a;
}
.cat-action-pay:hover {
    background: #dcfce7;
    color: #15803d;
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
.cat-badge-paid {
    background-color: #f0fdf4 !important;
    color: #15803d !important;
    border: 1px solid #bbf7d0 !important;
}
.cat-badge-partial {
    background-color: #fffbeb !important;
    color: #b45309 !important;
    border: 1px solid #fde68a !important;
}
.cat-badge-due {
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
#statusFilter {
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

<?php if (!$invoiceCheck['allowed']): ?>
    <div class="alert alert-warning alert-dismissible fade show mb-3" style="border-radius: 12px;">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Invoice Generation Not Available:</strong> Invoice generation is not available in the Free plan. 
        <a href="subscriptions.php" class="alert-link">Upgrade to Basic, Premium, or Enterprise plan</a> to use this feature.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- 1. Invoices Hero Banner (Using Shared Component) -->
<?php
$pageHeaderTitle = 'Invoices';
$pageHeaderSubtitle = 'Track customer billings, payment balances and financial receipts.';
include 'includes/page-header.php';
?>

<!-- 2. Stat Cards (2x2 on Mobile, 4 cols on Desktop) -->
<div class="row g-2 mb-2">
    <!-- Total Invoices -->
    <div class="col-6 col-md-3">
        <div class="cat-stat-box d-flex align-items-center justify-content-between" style="background: #faf5ff; border: 1px solid #ede9fe;">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #a855f7; color: #ffffff;">
                    <i class="fas fa-file-invoice" style="font-size: 0.95rem;"></i>
                </div>
                <div class="min-w-0">
                    <div class="fw-bold text-dark lh-1" style="font-size: 1.35rem;"><?php echo number_format($invoiceStats['total']); ?></div>
                    <div class="text-muted text-truncate" style="font-size: 0.72rem; margin-top: 3px;">Total Invoices</div>
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

    <!-- Paid Invoices -->
    <div class="col-6 col-md-3">
        <div class="cat-stat-box d-flex align-items-center justify-content-between" style="background: #f0fdf4; border: 1px solid #dcfce7;">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #22c55e; color: #ffffff;">
                    <i class="fas fa-check-circle" style="font-size: 0.95rem;"></i>
                </div>
                <div class="min-w-0">
                    <div class="fw-bold text-dark lh-1" style="font-size: 1.35rem;"><?php echo number_format($invoiceStats['paid']); ?></div>
                    <div class="text-muted text-truncate" style="font-size: 0.72rem; margin-top: 3px;">Paid Invoices</div>
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

    <!-- Partial Invoices -->
    <div class="col-6 col-md-3">
        <div class="cat-stat-box d-flex align-items-center justify-content-between" style="background: #fffbeb; border: 1px solid #fef3c7;">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #f59e0b; color: #ffffff;">
                    <i class="fas fa-clock" style="font-size: 0.95rem;"></i>
                </div>
                <div class="min-w-0">
                    <div class="fw-bold text-dark lh-1" style="font-size: 1.35rem;"><?php echo number_format($invoiceStats['partial']); ?></div>
                    <div class="text-muted text-truncate" style="font-size: 0.72rem; margin-top: 3px;">Partially Paid</div>
                </div>
            </div>
            <div class="d-flex align-items-end gap-1 ms-1 flex-shrink-0" style="height: 24px; color: #fbbf24;">
                <span style="width: 3px; height: 30%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 55%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 40%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 90%; background: currentColor; border-radius: 2px;"></span>
            </div>
        </div>
    </div>

    <!-- Due Invoices -->
    <div class="col-6 col-md-3">
        <div class="cat-stat-box d-flex align-items-center justify-content-between" style="background: #fef2f2; border: 1px solid #fee2e2;">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #ef4444; color: #ffffff;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 0.95rem;"></i>
                </div>
                <div class="min-w-0">
                    <div class="fw-bold text-dark lh-1" style="font-size: 1.35rem;"><?php echo number_format($invoiceStats['due']); ?></div>
                    <div class="text-muted text-truncate" style="font-size: 0.72rem; margin-top: 3px;">Payment Due</div>
                </div>
            </div>
            <div class="d-flex align-items-end gap-1 ms-1 flex-shrink-0" style="height: 24px; color: #f87171;">
                <span style="width: 3px; height: 45%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 70%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 55%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 100%; background: currentColor; border-radius: 2px;"></span>
            </div>
        </div>
    </div>
</div>

<!-- 3. Financial Revenue Strip -->
<div class="row g-2 mb-3">
    <div class="col-12 col-md-4">
        <div class="p-2 px-3 d-flex align-items-center justify-content-between" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; border-radius: 8px; background: #ecfdf5; color: #10b981;">
                    <i class="fas fa-coins" style="font-size: 0.85rem;"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-muted text-uppercase fw-semibold" style="font-size: 0.65rem; letter-spacing: 0.5px;">Total Revenue</div>
                    <div class="fw-bold text-dark lh-1 text-truncate" style="font-size: 1.05rem;"><?php echo format_currency($invoiceStats['total_amount']); ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="p-2 px-3 d-flex align-items-center justify-content-between" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; border-radius: 8px; background: #eff6ff; color: #3b82f6;">
                    <i class="fas fa-hand-holding-usd" style="font-size: 0.85rem;"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-muted text-uppercase fw-semibold" style="font-size: 0.65rem; letter-spacing: 0.5px;">Paid Amount</div>
                    <div class="fw-bold text-success lh-1 text-truncate" style="font-size: 1.05rem;"><?php echo format_currency($invoiceStats['paid_amount']); ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="p-2 px-3 d-flex align-items-center justify-content-between" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; border-radius: 8px; background: #fff7ed; color: #ea580c;">
                    <i class="fas fa-hourglass-half" style="font-size: 0.85rem;"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-muted text-uppercase fw-semibold" style="font-size: 0.65rem; letter-spacing: 0.5px;">Pending Amount</div>
                    <div class="fw-bold text-danger lh-1 text-truncate" style="font-size: 1.05rem;"><?php echo format_currency($invoiceStats['due_amount']); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 4. Search and Action Row -->
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <div class="position-relative flex-grow-1 min-w-0" style="min-width: 220px;">
        <i class="fas fa-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.9rem;"></i>
        <input type="text" id="searchInput" class="form-control" placeholder="Search invoices..." autocomplete="off" value="<?php echo htmlspecialchars($search); ?>" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding-left: 2.4rem; padding-right: 0.8rem; height: 42px; font-size: 0.85rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
    </div>
    <div class="d-flex align-items-center gap-2 flex-shrink-0">
        <?php 
        $exportCheck = SubscriptionHelper::canExportData($companyId);
        if ($exportCheck['allowed']): 
        ?>
            <button type="button" class="btn btn-outline-secondary align-items-center justify-content-center text-nowrap" style="border-radius: 14px; height: 42px; font-size: 0.82rem; padding: 0 14px; border-color: #cbd5e1;" onclick="exportInvoices()" title="Export Invoices">
                <i class="fas fa-download me-1"></i> Export
            </button>
        <?php else: ?>
            <a href="subscriptions.php" class="btn btn-outline-secondary align-items-center justify-content-center text-muted text-nowrap" style="border-radius: 14px; height: 42px; font-size: 0.82rem; padding: 0 14px; border-color: #cbd5e1;" title="Upgrade to Premium to Export">
                <i class="fas fa-lock me-1 text-warning"></i> Export
            </a>
        <?php endif; ?>
        
        <button type="button" class="btn text-white fw-semibold d-inline-flex align-items-center justify-content-center gap-1 text-nowrap px-4 shadow-sm" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 14px; font-size: 0.84rem; height: 42px; border: none; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.28);" data-bs-toggle="modal" data-bs-target="#invoiceModal" <?php echo !$invoiceCheck['allowed'] ? 'disabled title="Upgrade required to create invoices"' : ''; ?>>
            <i class="fas fa-plus"></i>
            <span>Create Invoice</span>
            <?php if (!$invoiceCheck['allowed']): ?>
                <span class="badge bg-warning ms-1" style="font-size: 0.65rem;">Upgrade</span>
            <?php endif; ?>
        </button>
    </div>
</div>

<!-- Detailed Filters Row -->
<div class="row g-2 align-items-center mb-3">
    <div class="col-6 col-md-4">
        <select class="form-select" id="statusFilter" title="Filter by Status" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.85rem; height: 42px; padding: 0.45rem 2.2rem 0.45rem 0.8rem; line-height: 1.4; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <option value="">All Payment Statuses</option>
            <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
            <option value="partial" <?php echo $status_filter === 'partial' ? 'selected' : ''; ?>>Partial</option>
            <option value="due" <?php echo $status_filter === 'due' ? 'selected' : ''; ?>>Due</option>
        </select>
    </div>
    <div class="col-6 col-md-3">
        <input type="date" class="form-control" id="startDateFilter" title="Start Date" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.85rem; height: 42px; padding: 0.45rem 0.8rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
    </div>
    <div class="col-6 col-md-3">
        <input type="date" class="form-control" id="endDateFilter" title="End Date" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.85rem; height: 42px; padding: 0.45rem 0.8rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
    </div>
    <div class="col-6 col-md-2">
        <button type="button" id="clearFilters" class="btn btn-outline-danger align-items-center justify-content-center text-nowrap w-100" style="border-radius: 14px; height: 42px; font-size: 0.82rem; padding: 0 16px; display: inline-flex;" title="Clear Filters">
            <i class="fas fa-times me-1"></i> Clear
        </button>
    </div>
</div>

<div id="filterResults" class="mb-3" style="display: none;">
    <div class="alert alert-info py-2 px-3 d-flex align-items-center" style="border-radius: 12px; font-size: 0.85rem;">
        <i class="fas fa-info-circle me-2"></i>
        <span><strong id="filterCount">0</strong> invoices found</span>
    </div>
</div>

<!-- 5. Invoices Table -->
<div class="card shadow-sm border-0 mb-3" style="border-radius: 14px; overflow: hidden; border: 1px solid #f1f5f9 !important;">
    <div class="card-body p-0">
        <?php if (!empty($invoices)): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle category-table">
                    <thead class="cat-table-head">
                        <tr>
                            <th class="ps-3 py-3">INVOICE #</th>
                            <th class="py-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span>CUSTOMER</span>
                                    <button type="button" class="btn btn-link p-0 text-muted border-0 shadow-none" id="sortInvoicesBtn" title="Sort A-Z" style="font-size: 0.75rem; line-height: 1; text-decoration: none;">
                                        <i class="fas fa-sort" id="sortIcon"></i>
                                    </button>
                                </div>
                            </th>
                            <th class="py-3">ORDER #</th>
                            <th class="py-3">INVOICE DATE</th>
                            <th class="py-3">DUE DATE</th>
                            <th class="py-3">AMOUNT</th>
                            <th class="py-3">PAID</th>
                            <th class="py-3">STATUS</th>
                            <th class="pe-3 py-3 text-end">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody id="invoicesTableBody">
                        <?php foreach ($invoices as $index => $invoice): 
                            $custName = htmlspecialchars($invoice['measurement_customer_name'] ?? (($invoice['first_name'] ?? '') . ' ' . ($invoice['last_name'] ?? '')));
                            $initial = strtoupper(mb_substr(trim($custName), 0, 1) ?: 'C');
                            $avColor = getCustomerAvatarDesign($custName, $index);
                            $isOverdue = $invoice['due_date'] < date('Y-m-d') && $invoice['payment_status'] !== 'paid';
                        ?>
                        <tr class="invoice-row" data-customer="<?php echo htmlspecialchars($custName); ?>">
                            <td class="ps-3 py-3">
                                <span class="badge fw-semibold" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; font-size: 0.82rem; padding: 5px 9px; border-radius: 8px;">
                                    <?php echo htmlspecialchars($invoice['invoice_number'] ?? ''); ?>
                                </span>
                            </td>
                            <td class="py-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="d-flex align-items-center justify-content-center flex-shrink-0 fw-bold" style="width: 34px; height: 34px; border-radius: 10px; background: <?php echo $avColor['bg']; ?>; border: 1px solid <?php echo $avColor['border']; ?>; color: <?php echo $avColor['color']; ?>; font-size: 0.85rem;">
                                        <?php echo $initial; ?>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="fw-bold text-dark text-truncate" style="font-size: 0.88rem; max-width: 170px;">
                                            <?php echo $custName; ?>
                                        </div>
                                        <?php if (!empty($invoice['customer_code'])): ?>
                                            <div class="text-muted" style="font-size: 0.72rem;"><?php echo htmlspecialchars($invoice['customer_code']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3">
                                <span class="badge fw-medium" style="background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; font-size: 0.78rem; border-radius: 6px; padding: 4px 8px;">
                                    <?php echo htmlspecialchars($invoice['order_number'] ?? 'N/A'); ?>
                                </span>
                            </td>
                            <td class="py-3 text-secondary" style="font-size: 0.82rem;"><?php echo format_date($invoice['invoice_date']); ?></td>
                            <td class="py-3" style="font-size: 0.82rem;">
                                <span class="<?php echo $isOverdue ? 'text-danger fw-bold' : 'text-dark'; ?>">
                                    <?php if ($isOverdue): ?><i class="fas fa-exclamation-circle text-danger me-1"></i><?php endif; ?>
                                    <?php echo format_date($invoice['due_date']); ?>
                                </span>
                            </td>
                            <td class="py-3">
                                <div>
                                    <strong class="text-dark" style="font-size: 0.88rem;"><?php echo format_currency($invoice['total_amount']); ?></strong>
                                    <?php if ($invoice['tax_amount'] > 0): ?>
                                        <div class="text-muted" style="font-size: 0.72rem;">
                                            Tax: <?php echo format_currency($invoice['tax_amount']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="py-3">
                                <span class="fw-semibold text-success" style="font-size: 0.85rem;"><?php echo format_currency($invoice['paid_amount']); ?></span>
                            </td>
                            <td class="py-3">
                                <span class="badge rounded-pill fw-semibold px-2 py-1 cat-badge-<?php echo htmlspecialchars($invoice['payment_status']); ?>" style="font-size: 0.75rem;">
                                    <?php echo ucfirst($invoice['payment_status']); ?>
                                </span>
                            </td>
                            <td class="pe-3 py-3 text-end">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <?php
                                        $invoicePayload = [
                                            'id' => $invoice['id'],
                                            'invoice_number' => $invoice['invoice_number'],
                                            'order_id' => $invoice['order_id'],
                                            'order_number' => $invoice['order_number'] ?? '',
                                            'invoice_date' => $invoice['invoice_date'],
                                            'due_date' => $invoice['due_date'],
                                            'subtotal' => $invoice['subtotal'],
                                            'tax_rate' => $invoice['tax_rate'] ?? 0,
                                            'tax_amount' => $invoice['tax_amount'],
                                            'discount_amount' => $invoice['discount_amount'],
                                            'total_amount' => $invoice['total_amount'],
                                            'notes' => $invoice['notes'] ?? '',
                                            'customer_name' => $invoice['measurement_customer_name'] ?? trim(($invoice['first_name'] ?? '') . ' ' . ($invoice['last_name'] ?? ''))
                                        ];
                                        $invoiceJson = htmlspecialchars(json_encode($invoicePayload), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <button type="button" 
                                            class="cat-action-btn cat-action-edit" 
                                            onclick="editInvoice(<?php echo $invoiceJson; ?>)"
                                            title="Edit Invoice">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" 
                                            class="cat-action-btn cat-action-view" 
                                            onclick="viewInvoice(<?php echo $invoice['id']; ?>)"
                                            title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" 
                                            class="cat-action-btn cat-action-pay" 
                                            onclick="addPayment(<?php echo $invoice['id']; ?>, '<?php echo htmlspecialchars($invoice['invoice_number'] ?? ''); ?>', <?php echo $invoice['balance_amount']; ?>)"
                                            title="Add Payment">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button type="button" 
                                            class="cat-action-btn cat-action-print" 
                                            onclick="printInvoice(<?php echo $invoice['id']; ?>)"
                                            title="Print Invoice">
                                        <i class="fas fa-print"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div id="paginationContainer" class="p-3">
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Invoice pagination">
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
                    <i class="fas fa-file-invoice fa-2x"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">No invoices found</h5>
                <p class="text-muted small mb-3">
                    <?php if (!empty($search) || !empty($status_filter)): ?>
                        No invoices match your search criteria.
                    <?php else: ?>
                        Get started by creating your first invoice.
                    <?php endif; ?>
                </p>
                <?php if (empty($search) && empty($status_filter)): ?>
                    <button type="button" class="btn text-white fw-semibold px-4 py-2" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 12px; font-size: 0.85rem;" data-bs-toggle="modal" data-bs-target="#invoiceModal" <?php echo !$invoiceCheck['allowed'] ? 'disabled title="Upgrade required to create invoices"' : ''; ?>>
                        <i class="fas fa-plus me-1"></i>Create Invoice
                        <?php if (!$invoiceCheck['allowed']): ?>
                            <span class="badge bg-warning ms-1" style="font-size: 0.65rem;">Upgrade Required</span>
                        <?php endif; ?>
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<div class="modal fade" id="invoiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="invoiceForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="invoiceModalTitle">Create Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (!$invoiceCheck['allowed']): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Invoice generation is not available in the Free plan.</strong> 
                            Please <a href="subscriptions.php" class="alert-link">upgrade your subscription</a> to use this feature.
                        </div>
                    <?php endif; ?>
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" id="invoiceAction" value="create">
                    <input type="hidden" name="invoice_id" id="invoice_id" value="">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="order_id" class="form-label">Order *</label>
                            <select class="form-select" id="order_id" name="order_id" required>
                                <option value="">Select Order</option>
                                <?php if (empty($unpaidOrders)): ?>
                                    <option value="" disabled>No orders available for invoicing</option>
                                <?php else: ?>
                                    <?php foreach ($unpaidOrders as $order): ?>
                                        <option value="<?php echo $order['id']; ?>" 
                                                data-amount="<?php echo $order['total_amount']; ?>"
                                                data-status="<?php echo $order['status']; ?>"
                                                data-customer-name="<?php echo htmlspecialchars(trim($order['measurement_customer_name'] ?? ($order['first_name'] . ' ' . $order['last_name']))); ?>"
                                                data-customer-code="<?php echo htmlspecialchars($order['customer_code'] ?? ''); ?>"
                                                data-customer-phone="<?php echo htmlspecialchars($order['customer_phone'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($order['order_number'] . ' - ' . ($order['measurement_customer_name'] ?? ($order['first_name'] . ' ' . $order['last_name'])) . ' - ' . ucfirst($order['status']) . ' (' . format_currency($order['total_amount']) . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <?php if (empty($unpaidOrders)): ?>
                                <small class="text-muted d-block mt-1">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Create an order first, then you can generate an invoice for it.
                                    <a href="orders.php" class="text-decoration-none">Go to Orders</a>
                                </small>
                            <?php endif; ?>
                            <div id="customer_details_display" class="mt-2 p-2 bg-light rounded border" style="display: none; font-size: 0.9em;">
                                <strong><i class="fas fa-user text-muted me-1"></i> <span id="display_customer_name"></span></strong><br>
                                <span class="text-muted"><i class="fas fa-id-badge me-1"></i> <span id="display_customer_code"></span></span>
                                <span class="text-muted ms-3"><i class="fas fa-phone me-1"></i> <span id="display_customer_phone"></span></span>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="invoice_date" class="form-label">Invoice Date *</label>
                            <input type="date" class="form-control" id="invoice_date" name="invoice_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="due_date" class="form-label">Due Date *</label>
                            <input type="date" class="form-control" id="due_date" name="due_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="subtotal" class="form-label">Subtotal *</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" class="form-control" id="subtotal" name="subtotal" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="tax_rate" class="form-label">GST Rate (%)</label>
                            <select class="form-select" id="tax_rate" name="tax_rate">
                                <option value="0">No GST</option>
                                <option value="5">5% GST</option>
                                <option value="12" selected>12% GST</option>
                                <option value="18">18% GST</option>
                                <option value="28">28% GST</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="tax_amount" class="form-label">GST Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" class="form-control" id="tax_amount" name="tax_amount" step="0.01" min="0" value="0" readonly>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="discount_amount" class="form-label">Discount Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" class="form-control" id="discount_amount" name="discount_amount" step="0.01" min="0" value="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="total_amount" class="form-label">Total Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" class="form-control" id="total_amount" name="total_amount" step="0.01" min="0" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="createInvoiceBtn">
                        <i class="fas fa-save me-2"></i>Create Invoice
                    </button>
                    <button type="submit" class="btn btn-primary" id="updateInvoiceBtn" style="display: none;">
                        <i class="fas fa-save me-2"></i>Update Invoice
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="paymentForm" onsubmit="submitPaymentForm(event)">
                <div class="modal-header">
                    <h5 class="modal-title">Add Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="add_payment">
                    <input type="hidden" name="invoice_id" id="paymentInvoiceId">
                    
                    <div class="mb-3">
                        <label class="form-label">Invoice Number</label>
                        <p class="form-control-plaintext" id="paymentInvoiceNumber"></p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="paymentAmount" class="form-label">Amount *</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" class="form-control" id="paymentAmount" name="amount" step="0.01" min="0" required>
                        </div>
                        <small class="text-muted">Maximum: ₹<span id="maxPaymentAmount">0.00</span></small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="paymentMethod" class="form-label">Payment Method *</label>
                        <select class="form-select" id="paymentMethod" name="payment_method" required>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="card">Card</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="paymentDate" class="form-label">Payment Date *</label>
                        <input type="date" class="form-control" id="paymentDate" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="referenceNumber" class="form-label">Reference Number</label>
                        <input type="text" class="form-control" id="referenceNumber" name="reference_number">
                    </div>
                    
                    <div class="mb-3">
                        <label for="paymentNotes" class="form-label">Notes</label>
                        <textarea class="form-control" id="paymentNotes" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus me-2"></i>Add Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const invoiceModalElement = document.getElementById('invoiceModal');
const invoiceFormEl = document.getElementById('invoiceForm');
const invoiceModalTitle = document.getElementById('invoiceModalTitle');
const invoiceActionInput = document.getElementById('invoiceAction');
const invoiceIdInput = document.getElementById('invoice_id');
const createInvoiceBtn = document.getElementById('createInvoiceBtn');
const updateInvoiceBtn = document.getElementById('updateInvoiceBtn');
const orderSelect = document.getElementById('order_id');
const invoiceDateInput = document.getElementById('invoice_date');
const dueDateInput = document.getElementById('due_date');
const subtotalInput = document.getElementById('subtotal');
const taxRateSelect = document.getElementById('tax_rate');
const taxAmountInput = document.getElementById('tax_amount');
const discountInput = document.getElementById('discount_amount');
const totalAmountInput = document.getElementById('total_amount');
const notesInput = document.getElementById('notes');

const TEMP_ORDER_OPTION_CLASS = 'temp-order-option';
const TEMP_TAX_OPTION_CLASS = 'temp-tax-rate-option';

function encodeAttribute(str) {
    return (str || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function decodeAttribute(str) {
    return (str || '')
        .replace(/&quot;/g, '"')
        .replace(/&#39;/g, "'")
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&amp;/g, '&');
}

function addPayment(invoiceId, invoiceNumber, balanceAmount) {
    document.getElementById('paymentInvoiceId').value = invoiceId;
    document.getElementById('paymentInvoiceNumber').textContent = invoiceNumber;
    document.getElementById('paymentAmount').value = balanceAmount;
    document.getElementById('maxPaymentAmount').textContent = balanceAmount.toFixed(2);
    new bootstrap.Modal(document.getElementById('paymentModal')).show();
}

function submitPaymentForm(event) {
    event.preventDefault();
    
    console.log('Payment form submission started...');
    
    const form = document.getElementById('paymentForm');
    const formData = new FormData(form);
    
    console.log('Form data:');
    for (let [key, value] of formData.entries()) {
        console.log(key + ': ' + value);
    }
    
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
    submitBtn.disabled = true;
    
    fetch('ajax/add_payment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();
            
            showToast('Success', data.message || 'Payment added successfully!', 'success');
            
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showToast('Error', data.error || 'Failed to add payment', 'error');
        }
    })
    .catch(error => {
        console.error('Error adding payment:', error);
        showToast('Error', 'Network error occurred. Please try again.', 'error');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

function viewInvoice(invoiceId) {
    window.location.href = 'invoice-details.php?id=' + invoiceId;
}

function populateInvoiceForm(invoice) {
    if (!invoiceModalElement || !invoice) return;
    
    if (invoiceActionInput) {
        invoiceActionInput.value = 'update';
    }
    if (invoiceIdInput) {
        invoiceIdInput.value = invoice.id || '';
    }
    if (invoiceModalTitle) {
        const suffix = invoice.invoice_number ? ` (${invoice.invoice_number})` : '';
        invoiceModalTitle.textContent = 'Edit Invoice' + suffix;
    }
    
    if (createInvoiceBtn) {
        createInvoiceBtn.style.display = 'none';
        createInvoiceBtn.disabled = false;
    }
    if (updateInvoiceBtn) {
        updateInvoiceBtn.style.display = 'inline-block';
        updateInvoiceBtn.disabled = false;
    }
    
    if (orderSelect) {
        const orderValue = invoice.order_id ? String(invoice.order_id) : '';
        if (orderValue) {
            const orderAmount = invoice.order?.total_amount ?? invoice.total_amount ?? 0;
            const orderLabel =
                invoice.order_number
                    ? `${invoice.order_number} - ${invoice.customer_name || ''}`
                    : invoice.order?.order_number
                        ? `${invoice.order.order_number} - ${invoice.order.customer_name || ''}`
                        : `Order #${orderValue}`;
            
            let option = Array.from(orderSelect.options).find(opt => opt.value === orderValue);
            if (!option) {
                option = document.createElement('option');
                option.value = orderValue;
                option.textContent = orderLabel;
                option.dataset.amount = orderAmount;
                option.classList.add(TEMP_ORDER_OPTION_CLASS);
                orderSelect.appendChild(option);
            }
            option.dataset.amount = orderAmount;
            orderSelect.value = orderValue;
        }
    }
    
    if (invoiceDateInput) {
        invoiceDateInput.value = invoice.invoice_date || '';
    }
    if (dueDateInput) {
        dueDateInput.value = invoice.due_date || '';
    }
    if (subtotalInput) {
        subtotalInput.value = invoice.subtotal ?? '';
    }
    if (discountInput) {
        discountInput.value = invoice.discount_amount ?? 0;
    }
    if (notesInput) {
        notesInput.value = invoice.notes || '';
    }
    if (taxRateSelect) {
        const rateValue = invoice.tax_rate !== undefined && invoice.tax_rate !== null ? String(invoice.tax_rate) : '12';
        let option = Array.from(taxRateSelect.options).find(opt => opt.value === rateValue);
        if (!option) {
            option = document.createElement('option');
            option.value = rateValue;
            option.textContent = `${rateValue}% GST`;
            option.classList.add(TEMP_TAX_OPTION_CLASS);
            taxRateSelect.appendChild(option);
        }
        taxRateSelect.value = rateValue;
    }
    if (taxAmountInput) {
        taxAmountInput.value = invoice.tax_amount ?? 0;
    }
    if (totalAmountInput) {
        totalAmountInput.value = invoice.total_amount ?? 0;
    }
    
    calculateTotal();
    
    const modalInstance = bootstrap.Modal.getOrCreateInstance(invoiceModalElement);
    modalInstance.show();
}

function editInvoice(invoiceOrId) {
    if (!invoiceModalElement || !invoiceOrId) return;
    
    const showLoadingState = () => {
        if (invoiceModalTitle) {
            invoiceModalTitle.textContent = 'Loading...';
        }
        if (createInvoiceBtn) {
            createInvoiceBtn.disabled = true;
            createInvoiceBtn.style.display = 'none';
        }
        if (updateInvoiceBtn) {
            updateInvoiceBtn.style.display = 'inline-block';
            updateInvoiceBtn.disabled = true;
        }
    };
    
    const clearLoadingState = () => {
        if (createInvoiceBtn) {
            createInvoiceBtn.disabled = false;
            if (!invoiceActionInput || invoiceActionInput.value !== 'update') {
                createInvoiceBtn.style.display = 'inline-block';
            }
        }
        if (updateInvoiceBtn) {
            updateInvoiceBtn.disabled = false;
            if (!invoiceActionInput || invoiceActionInput.value !== 'update') {
                updateInvoiceBtn.style.display = 'none';
            }
        }
    };
    
    if (typeof invoiceOrId === 'object') {
        populateInvoiceForm(invoiceOrId);
        return;
    }
    
    const invoiceId = Number(invoiceOrId);
    if (!Number.isFinite(invoiceId) || invoiceId <= 0) {
        showToast('Error', 'Invalid invoice selected', 'error');
        return;
    }
    
    showLoadingState();
    
    fetch(`ajax/get_invoice.php?id=${invoiceId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.invoice) {
                populateInvoiceForm(data.invoice);
            } else {
                showToast('Error', data.error || 'Failed to load invoice data', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading invoice:', error);
            showToast('Error', 'Network error occurred. Please try again.', 'error');
        })
        .finally(() => {
            clearLoadingState();
        });
}

function printInvoice(invoiceId) {
    window.open('print-invoice.php?id=' + invoiceId, '_blank');
}

function fixAdvancePayments() {
    if (confirm('This will fix all existing invoices to include advance payments from their orders. Continue?')) {
        window.location.href = 'invoices.php?fix_advance=1';
    }
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

function exportInvoices() {
    showToast('Export', '🔄 Preparing your invoice export... Please wait.', 'info');
    
    const exportBtn = document.querySelector('button[onclick="exportInvoices()"]');
    const originalText = exportBtn.innerHTML;
    exportBtn.disabled = true;
    exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Exporting...';
    
    const formData = new FormData();
    formData.append('csrf_token', '<?php echo generate_csrf_token(); ?>');
    
    setTimeout(() => {
        showToast('📊 Generating Excel file with all invoice details...', 'info');
    }, 500);
    
    fetch('ajax/export_invoices.php', {
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
        a.download = 'invoices_export_' + new Date().toISOString().slice(0, 19).replace(/:/g, '-') + '.xls';
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
document.getElementById('order_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const amount = selectedOption.getAttribute('data-amount');
    
    const customerDisplay = document.getElementById('customer_details_display');
    if (selectedOption.value) {
        const customerName = selectedOption.getAttribute('data-customer-name');
        const customerCode = selectedOption.getAttribute('data-customer-code');
        const customerPhone = selectedOption.getAttribute('data-customer-phone');
        
        if (customerName || customerCode || customerPhone) {
            document.getElementById('display_customer_name').textContent = customerName || 'N/A';
            document.getElementById('display_customer_code').textContent = customerCode || 'N/A';
            document.getElementById('display_customer_phone').textContent = customerPhone || 'N/A';
            customerDisplay.style.display = 'block';
        } else {
            customerDisplay.style.display = 'none';
        }
    } else {
        customerDisplay.style.display = 'none';
    }

    if (amount) {
        document.getElementById('subtotal').value = amount;
        calculateTotal();
    }
});

document.getElementById('subtotal').addEventListener('input', calculateTotal);
document.getElementById('tax_rate').addEventListener('input', calculateTotal);
document.getElementById('discount_amount').addEventListener('input', calculateTotal);

function calculateTotal() {
    const subtotal = parseFloat(document.getElementById('subtotal').value) || 0;
    const taxRate = parseFloat(document.getElementById('tax_rate').value) || 0;
    const discountAmount = parseFloat(document.getElementById('discount_amount').value) || 0;
    
    const taxAmount = (subtotal * taxRate) / 100;
    const totalAmount = subtotal + taxAmount - discountAmount;
    
    document.getElementById('tax_amount').value = taxAmount.toFixed(2);
    document.getElementById('total_amount').value = totalAmount.toFixed(2);
}
document.getElementById('invoiceModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('invoiceForm').reset();
    document.getElementById('invoiceAction').value = 'create';
    document.getElementById('invoice_id').value = '';
    document.getElementById('invoiceModalTitle').textContent = 'Create Invoice';
    if (createInvoiceBtn) createInvoiceBtn.style.display = 'inline-block';
    if (updateInvoiceBtn) updateInvoiceBtn.style.display = 'none';
    document.getElementById('invoice_date').value = '<?php echo date('Y-m-d'); ?>';
    document.getElementById('tax_rate').value = '12'; 
    
    const orderSelect = document.getElementById('order_id');
    const options = Array.from(orderSelect.options);
    options.forEach((option, index) => {
        if (index > 0 && option.textContent.includes('(Current Order)')) {
            option.remove();
        }
    });
    document.getElementById('customer_details_display').style.display = 'none';
});
if (invoiceModalElement) {
    invoiceModalElement.addEventListener('hidden.bs.modal', function() {
        if (invoiceFormEl) {
            invoiceFormEl.reset();
        }
        if (invoiceActionInput) {
            invoiceActionInput.value = 'create';
        }
        if (invoiceIdInput) {
            invoiceIdInput.value = '';
        }
        if (invoiceModalTitle) {
            invoiceModalTitle.textContent = 'Create Invoice';
        }
        if (createInvoiceBtn) createInvoiceBtn.style.display = 'inline-block';
        if (updateInvoiceBtn) updateInvoiceBtn.style.display = 'none';
        if (invoiceDateInput) {
            invoiceDateInput.value = '<?php echo date('Y-m-d'); ?>';
        }
        if (orderSelect) {
            orderSelect.querySelectorAll(`.${TEMP_ORDER_OPTION_CLASS}`).forEach(opt => opt.remove());
        }
        if (taxRateSelect) {
            taxRateSelect.querySelectorAll(`.${TEMP_TAX_OPTION_CLASS}`).forEach(opt => opt.remove());
        }
        if (taxAmountInput) {
            taxAmountInput.value = '0.00';
        }
        if (totalAmountInput) {
            totalAmountInput.value = '0.00';
        }
        if (notesInput) {
            notesInput.value = '';
        }
    });
}

document.getElementById('paymentModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('paymentForm').reset();
    document.getElementById('paymentDate').value = '<?php echo date('Y-m-d'); ?>';
});
let filterTimeout;
const searchInput = document.getElementById('searchInput');
const statusFilter = document.getElementById('statusFilter');
const startDateFilter = document.getElementById('startDateFilter');
const endDateFilter = document.getElementById('endDateFilter');
const clearFilters = document.getElementById('clearFilters');
const filterResults = document.getElementById('filterResults');
const filterCount = document.getElementById('filterCount');
const invoicesTable = document.getElementById('invoicesTableBody') || document.querySelector('.table tbody');
const originalTableContent = invoicesTable ? invoicesTable.innerHTML : '';

[searchInput, statusFilter, startDateFilter, endDateFilter].forEach(element => {
    if (!element) return;
    element.addEventListener('change', performFilter);
    if (element === searchInput) {
        element.addEventListener('input', performFilter);
    }
});

if (clearFilters) {
    clearFilters.addEventListener('click', function() {
        if (searchInput) searchInput.value = '';
        if (statusFilter) statusFilter.value = '';
        if (startDateFilter) startDateFilter.value = '';
        if (endDateFilter) endDateFilter.value = '';
                window.location.href = 'invoices.php';
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
    const search = searchInput ? searchInput.value.trim() : '';
    const status = statusFilter ? statusFilter.value : '';
    const startDate = startDateFilter ? startDateFilter.value : '';
    const endDate = endDateFilter ? endDateFilter.value : '';
    
    if (filterResults) filterResults.style.display = 'block';
    if (filterCount) filterCount.textContent = 'Filtering...';
    
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (status) params.append('status', status);
    if (startDate) params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);
    params.append('page', '1');
    params.append('limit', '<?php echo RECORDS_PER_PAGE; ?>');
    
    fetch(`ajax/filter_invoices.php?${params.toString()}`)
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
                    displayFilterResults(data.invoices);
                    if (filterCount) filterCount.textContent = data.pagination.total_invoices;
                    
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
                    if (filterCount) filterCount.textContent = 'Filter failed: ' + (data.error || 'Unknown error');
                }
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response text:', text);
                if (filterCount) filterCount.textContent = 'Invalid response from server';
            }
        })
        .catch(error => {
            console.error('Filter error:', error);
            if (filterCount) filterCount.textContent = 'Filter failed: ' + error.message;
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

function getCustomerAvatarDesign(name, index) {
    let sum = 0;
    const str = name || '';
    for (let i = 0; i < str.length; i++) {
        sum += str.charCodeAt(i);
    }
    return avatarPalettes[(sum + index) % avatarPalettes.length];
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function displayFilterResults(invoices) {
    const tableBody = document.getElementById('invoicesTableBody') || document.querySelector('.table tbody');
    if (!tableBody) return;

    if (!invoices || invoices.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-5">
                    <i class="fas fa-search fa-2x text-muted mb-2"></i>
                    <h5 class="text-muted">No invoices found</h5>
                    <p class="text-muted small">Try adjusting your filter criteria</p>
                </td>
            </tr>
        `;
        return;
    }
    
    let tableHTML = '';
    invoices.forEach((invoice, index) => {
        const formatDate = (dateString) => {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return dateString;
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };
        
        const invoiceDate = formatDate(invoice.invoice_date);
        const dueDate = formatDate(invoice.due_date);
        
        const isOverdue = invoice.due_date && new Date(invoice.due_date) < new Date() && invoice.payment_status !== 'paid';
        
        const statusMap = {
            'paid': 'cat-badge-paid',
            'partial': 'cat-badge-partial',
            'due': 'cat-badge-due'
        };
        const statusClass = statusMap[invoice.payment_status] || 'bg-secondary text-white';
        const statusLabel = invoice.payment_status ? invoice.payment_status.charAt(0).toUpperCase() + invoice.payment_status.slice(1) : 'Status';
        const formatCurrency = (amount) => '₹' + parseFloat(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 });
        const formatTax = (amount) => amount && parseFloat(amount) > 0 ? `<div class="text-muted" style="font-size: 0.72rem;">Tax: ${formatCurrency(amount)}</div>` : '';
        
        const orderNumber = invoice.order_number && invoice.order_number !== 'N/A' ? invoice.order_number : 'N/A';
        const customerNameDisplay = invoice.customer_name || 'N/A';
        const initial = (customerNameDisplay.trim().charAt(0) || 'C').toUpperCase();
        const avColor = getCustomerAvatarDesign(customerNameDisplay, index);
        
        const invoiceEditPayload = {
            id: invoice.id,
            invoice_number: invoice.invoice_number,
            order_id: invoice.order_id,
            order_number: orderNumber,
            invoice_date: invoice.invoice_date,
            due_date: invoice.due_date,
            subtotal: invoice.subtotal,
            tax_rate: invoice.tax_rate,
            tax_amount: invoice.tax_amount,
            discount_amount: invoice.discount_amount,
            total_amount: invoice.total_amount,
            notes: invoice.notes || '',
            customer_name: invoice.customer_name_raw || customerNameDisplay
        };
        const encodedInvoiceData = encodeAttribute(JSON.stringify(invoiceEditPayload));
        
        tableHTML += `
            <tr class="invoice-row" data-customer="${escapeHtml(customerNameDisplay)}">
                <td class="ps-3 py-3">
                    <span class="badge fw-semibold" style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; font-size: 0.82rem; padding: 5px 9px; border-radius: 8px;">
                        ${escapeHtml(invoice.invoice_number || '')}
                    </span>
                </td>
                <td class="py-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="d-flex align-items-center justify-content-center flex-shrink-0 fw-bold" style="width: 34px; height: 34px; border-radius: 10px; background: ${avColor.bg}; border: 1px solid ${avColor.border}; color: ${avColor.color}; font-size: 0.85rem;">
                            ${initial}
                        </div>
                        <div class="min-w-0">
                            <div class="fw-bold text-dark text-truncate" style="font-size: 0.88rem; max-width: 170px;">
                                ${escapeHtml(customerNameDisplay)}
                            </div>
                            ${invoice.customer_code ? `<div class="text-muted" style="font-size: 0.72rem;">${escapeHtml(invoice.customer_code)}</div>` : ''}
                        </div>
                    </div>
                </td>
                <td class="py-3">
                    <span class="badge fw-medium" style="background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; font-size: 0.78rem; border-radius: 6px; padding: 4px 8px;">
                        ${escapeHtml(orderNumber)}
                    </span>
                </td>
                <td class="py-3 text-secondary" style="font-size: 0.82rem;">${invoiceDate}</td>
                <td class="py-3" style="font-size: 0.82rem;">
                    <span class="${isOverdue ? 'text-danger fw-bold' : 'text-dark'}">
                        ${isOverdue ? '<i class="fas fa-exclamation-circle text-danger me-1"></i>' : ''}
                        ${dueDate}
                    </span>
                </td>
                <td class="py-3">
                    <div>
                        <strong class="text-dark" style="font-size: 0.88rem;">${formatCurrency(invoice.total_amount)}</strong>
                        ${formatTax(invoice.tax_amount)}
                    </div>
                </td>
                <td class="py-3">
                    <span class="fw-semibold text-success" style="font-size: 0.85rem;">${formatCurrency(invoice.paid_amount)}</span>
                </td>
                <td class="py-3">
                    <span class="badge rounded-pill fw-semibold px-2 py-1 ${statusClass}" style="font-size: 0.75rem;">
                        ${statusLabel}
                    </span>
                </td>
                <td class="pe-3 py-3 text-end">
                    <div class="d-inline-flex align-items-center gap-1">
                        <button type="button" 
                                class="cat-action-btn cat-action-edit edit-invoice-btn" 
                                data-invoice='${encodedInvoiceData}'
                                title="Edit Invoice">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" 
                                class="cat-action-btn cat-action-view" 
                                onclick="viewInvoice(${invoice.id})"
                                title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button type="button" 
                                class="cat-action-btn cat-action-pay" 
                                onclick="addPayment(${invoice.id}, '${escapeHtml(invoice.invoice_number || '')}', ${invoice.balance_amount || 0})"
                                title="Add Payment">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button type="button" 
                                class="cat-action-btn cat-action-print" 
                                onclick="printInvoice(${invoice.id})"
                                title="Print Invoice">
                            <i class="fas fa-print"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    tableBody.innerHTML = tableHTML;
    
    tableBody.querySelectorAll('.edit-invoice-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            try {
                const encoded = this.getAttribute('data-invoice');
                const invoiceData = JSON.parse(decodeAttribute(encoded));
                editInvoice(invoiceData);
            } catch (error) {
                console.error('Error parsing invoice data:', error);
                showToast('Error loading invoice data', 'error');
            }
        });
    });
}
let sortAscending = true;
const sortBtn = document.getElementById('sortInvoicesBtn');
if (sortBtn) {
    sortBtn.addEventListener('click', function() {
        sortAscending = !sortAscending;
        const sortIcon = document.getElementById('sortIcon');
        if (sortIcon) {
            sortIcon.className = sortAscending ? 'fas fa-sort-alpha-down' : 'fas fa-sort-alpha-up';
        }
        const tableBody = document.getElementById('invoicesTableBody') || document.querySelector('.table tbody');
        if (tableBody) {
            const rows = Array.from(tableBody.querySelectorAll('tr.invoice-row'));
            rows.sort((a, b) => {
                const nameA = a.getAttribute('data-customer') || '';
                const nameB = b.getAttribute('data-customer') || '';
                return sortAscending ? nameA.localeCompare(nameB) : nameB.localeCompare(nameA);
            });
            rows.forEach(r => tableBody.appendChild(r));
        }
    });
}
</script>
<?php require_once 'includes/footer.php'; ?>
<style>
    .btn-outline-info:hover {
        color: #ffffff;
    }
    @media (max-width: 768px) {
        .card-header{
            display: flex !important;
            flex-direction: column;
            gap: 15px;
            align-items: flex-start !important;
        }
        .card-header .d-flex {
            flex-direction: column;
    width: 100%;
        }
    }
</style>