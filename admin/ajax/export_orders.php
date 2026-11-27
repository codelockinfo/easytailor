<?php
ob_start();
require_once '../../config/config.php';
require_once '../models/Order.php';
require_once '../../helpers/ExcelExportHelper.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
if (!has_role('admin')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

require_once '../../helpers/SubscriptionHelper.php';
$exportCheck = SubscriptionHelper::canExportData(get_company_id());
if (!$exportCheck['allowed']) {
    http_response_code(403);
    echo json_encode(['error' => $exportCheck['message']]);
    exit;
}

try {
    $orderModel = new Order();
    $orders = $orderModel->getOrdersWithDetails();
    $filename = 'orders_export_' . date('Y-m-d_H-i-s') . '.xls';

    $columns = [
        ['label' => 'Order Number', 'width' => 135, 'type' => 'String', 'style' => 'CenterText'],
        ['label' => 'Order Date', 'width' => 95, 'type' => 'String', 'style' => 'CenterText'],
        ['label' => 'Due Date', 'width' => 95, 'type' => 'String', 'style' => 'CenterText'],
        ['label' => 'Delivery Date', 'width' => 95, 'type' => 'String', 'style' => 'CenterText'],
        ['label' => 'Customer Name', 'width' => 150, 'type' => 'String', 'style' => 'Text'],
        ['label' => 'Customer Phone', 'width' => 110, 'type' => 'String', 'style' => 'CenterText'],
        ['label' => 'Garments / Cloth Type', 'width' => 165, 'type' => 'String', 'style' => 'Text'],
        ['label' => 'Total Qty', 'width' => 70, 'type' => 'Number', 'style' => 'Integer'],
        ['label' => 'Assigned Tailor', 'width' => 150, 'type' => 'String', 'style' => 'Text'],
        ['label' => 'Status', 'width' => 100, 'type' => 'String', 'style' => 'CenterText'],
        ['label' => 'Payment Method', 'width' => 105, 'type' => 'String', 'style' => 'CenterText'],
        ['label' => 'Total Amount', 'width' => 100, 'type' => 'Number', 'style' => 'Currency'],
        ['label' => 'Paid / Advance', 'width' => 100, 'type' => 'Number', 'style' => 'Currency'],
        ['label' => 'Balance Amount', 'width' => 100, 'type' => 'Number', 'style' => 'Currency'],
        ['label' => 'Special Instructions', 'width' => 180, 'type' => 'String', 'style' => 'Text'],
        ['label' => 'Created By', 'width' => 125, 'type' => 'String', 'style' => 'Text'],
        ['label' => 'Created At', 'width' => 125, 'type' => 'String', 'style' => 'CenterText'],
    ];

    $rows = [];
    $sumQty = 0;
    $sumTotal = 0.0;
    $sumAdvance = 0.0;
    $sumBalance = 0.0;

    foreach ($orders as $order) {
        $customerName = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''))
            ?: ($order['measurement_customer_name'] ?? ($order['customer_name'] ?? 'Unknown'));

        $clothTypes = trim((string)($order['all_cloth_type_names'] ?? ''))
            ?: (trim((string)($order['cloth_type_name'] ?? '')) ?: '-');

        $tailors = trim((string)($order['all_tailor_names'] ?? ''))
            ?: (trim((string)($order['tailor_name'] ?? '')) ?: 'Unassigned');

        $qty = (int)($order['total_quantity'] ?? 0);
        if ($qty <= 0) {
            $qty = max(1, (int)($order['item_count'] ?? 0));
        }

        $totalAmt = (float)($order['total_amount'] ?? 0);
        $advanceAmt = (float)($order['advance_amount'] ?? 0);
        $balanceAmt = isset($order['balance_amount'])
            ? (float)$order['balance_amount']
            : max(0.0, $totalAmt - $advanceAmt);

        $sumQty += $qty;
        $sumTotal += $totalAmt;
        $sumAdvance += $advanceAmt;
        $sumBalance += $balanceAmt;

        $paymentMethod = !empty($order['payment_method'])
            ? ucwords(str_replace('_', ' ', (string)$order['payment_method']))
            : 'Cash';

        $createdAt = !empty($order['created_at'])
            ? date('Y-m-d H:i', strtotime($order['created_at']))
            : '';

        $rows[] = [
            $order['order_number'] ?? '',
            $order['order_date'] ?? '',
            $order['due_date'] ?? '',
            !empty($order['delivery_date']) ? $order['delivery_date'] : '-',
            $customerName,
            $order['customer_phone'] ?? '',
            $clothTypes,
            $qty,
            $tailors,
            ucwords(str_replace('_', ' ', (string)($order['status'] ?? ''))),
            $paymentMethod,
            $totalAmt,
            $advanceAmt,
            $balanceAmt,
            trim((string)($order['special_instructions'] ?? '')),
            trim((string)($order['created_by_name'] ?? '')) ?: '-',
            $createdAt,
        ];
    }

    $summaryRows = [];
    if (!empty($rows)) {
        $summaryRows[] = [
            0 => ['value' => 'TOTAL (' . count($rows) . ' Orders)', 'type' => 'String', 'style' => 'SummaryText'],
            7 => ['value' => $sumQty, 'type' => 'Number', 'style' => 'SummaryInteger'],
            11 => ['value' => $sumTotal, 'type' => 'Number', 'style' => 'SummaryCurrency'],
            12 => ['value' => $sumAdvance, 'type' => 'Number', 'style' => 'SummaryCurrency'],
            13 => ['value' => $sumBalance, 'type' => 'Number', 'style' => 'SummaryCurrency'],
        ];
    }

    $xml = ExcelExportHelper::buildWorkbook('Orders', 'Orders Report', $columns, $rows, $summaryRows);
    ExcelExportHelper::sendDownload($filename, $xml);
} catch (Exception $e) {
    error_log('Order export failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to export orders']);
    exit;
}
