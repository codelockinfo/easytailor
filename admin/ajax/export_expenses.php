<?php
ob_start();
require_once '../../config/config.php';
require_once '../models/Expense.php';
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
    $expenseModel = new Expense();
    $expenses = $expenseModel->getExpensesWithDetails();
    $filename = 'expenses_export_' . date('Y-m-d_H-i-s') . '.xls';

    $columns = [
        ['label' => 'Expense ID', 'width' => 95, 'type' => 'String', 'style' => 'CenterText'],
        ['label' => 'Expense Date', 'width' => 95, 'type' => 'String', 'style' => 'CenterText'],
        ['label' => 'Type', 'width' => 85, 'type' => 'String', 'style' => 'CenterText'],
        ['label' => 'Category', 'width' => 140, 'type' => 'String', 'style' => 'Text'],
        ['label' => 'Description', 'width' => 200, 'type' => 'String', 'style' => 'Text'],
        ['label' => 'Cash In', 'width' => 100, 'type' => 'Number', 'style' => 'Currency'],
        ['label' => 'Cash Out', 'width' => 100, 'type' => 'Number', 'style' => 'Currency'],
        ['label' => 'Amount', 'width' => 100, 'type' => 'Number', 'style' => 'Currency'],
        ['label' => 'Reference No.', 'width' => 110, 'type' => 'String', 'style' => 'CenterText'],
        ['label' => 'Created By', 'width' => 130, 'type' => 'String', 'style' => 'Text'],
        ['label' => 'Created At', 'width' => 125, 'type' => 'String', 'style' => 'CenterText'],
    ];

    $rows = [];
    $totalCashIn = 0.0;
    $totalCashOut = 0.0;

    foreach ($expenses as $expense) {
        $rawType = strtolower(trim((string)($expense['payment_method'] ?? 'cash_out')));
        $isCashIn = ($rawType === 'cash_in');
        $typeLabel = $isCashIn ? 'Cash In' : ($rawType === 'cash_out' ? 'Cash Out' : ucwords(str_replace('_', ' ', $rawType)));

        $amount = (float)($expense['amount'] ?? 0);
        $cashInAmt = $isCashIn ? $amount : 0.0;
        $cashOutAmt = $isCashIn ? 0.0 : $amount;

        $totalCashIn += $cashInAmt;
        $totalCashOut += $cashOutAmt;

        $cat = trim((string)($expense['category'] ?? ''))
            ?: (!empty($expense['description']) ? ucfirst(trim((string)$expense['description'])) : 'General');

        $createdAt = !empty($expense['created_at'])
            ? date('Y-m-d H:i', strtotime($expense['created_at']))
            : '';

        $rows[] = [
            'EXP-' . str_pad((string)$expense['id'], 6, '0', STR_PAD_LEFT),
            $expense['expense_date'] ?? '',
            $typeLabel,
            $cat,
            trim((string)($expense['description'] ?? '')),
            $cashInAmt,
            $cashOutAmt,
            $amount,
            trim((string)($expense['reference_number'] ?? '')) ?: '-',
            trim((string)($expense['created_by_name'] ?? '')) ?: 'Unknown',
            $createdAt,
        ];
    }

    $summaryRows = [];
    if (!empty($rows)) {
        $summaryRows[] = [
            0 => ['value' => 'TOTAL (' . count($rows) . ' Entries)', 'type' => 'String', 'style' => 'SummaryText'],
            5 => ['value' => $totalCashIn, 'type' => 'Number', 'style' => 'SummaryCurrency'],
            6 => ['value' => $totalCashOut, 'type' => 'Number', 'style' => 'SummaryCurrency'],
            7 => ['value' => ($totalCashIn - $totalCashOut), 'type' => 'Number', 'style' => 'SummaryCurrency'],
        ];
    }

    $xml = ExcelExportHelper::buildWorkbook('Expenses', 'Expenses & Cash Flow Report', $columns, $rows, $summaryRows);
    ExcelExportHelper::sendDownload($filename, $xml);
} catch (Exception $e) {
    error_log('Expense export failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to export expenses']);
    exit;
}
