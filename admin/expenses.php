<?php

require_once __DIR__ . '/../config/config.php';
if (!is_logged_in()) {
    smart_redirect('login.php');
}
require_once 'models/Expense.php';
require_once 'models/Category.php';

$expenseModel = new Expense();
$categoryModel = new Category();
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                $data = [
                    'description' => sanitize_input($_POST['description']),
                    'amount' => (float)$_POST['amount'],
                    'expense_date' => $_POST['expense_date'],
                    'payment_method' => $_POST['payment_method'],
                    'created_by' => get_user_id()
                ];
                
                $expenseId = $expenseModel->create($data);
                if ($expenseId) {
                    $message = 'Expense recorded successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to record expense';
                    $messageType = 'error';
                }
                break;
                
            case 'update':
                $expenseId = (int)$_POST['expense_id'];
                $data = [
                    'description' => sanitize_input($_POST['description']),
                    'amount' => (float)$_POST['amount'],
                    'expense_date' => $_POST['expense_date'],
                    'payment_method' => $_POST['payment_method'],
                    'reference_number' => sanitize_input($_POST['reference_number']),
                    'receipt_image' => $_POST['receipt_image'] ?: null
                ];
                
                if ($expenseModel->update($expenseId, $data)) {
                    $message = 'Expense updated successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update expense';
                    $messageType = 'error';
                }
                break;
                
            case 'delete':
                $expenseId = (int)$_POST['expense_id'];
                if ($expenseModel->delete($expenseId)) {
                    $message = 'Expense deleted successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to delete expense';
                    $messageType = 'error';
                }
                break;
        }
    } else {
        $message = 'Invalid request';
        $messageType = 'error';
    }
    if ($messageType === 'success') {
        $msgType = match($action) {
            'create' => 'created',
            'update' => 'updated',
            'delete' => 'deleted',
            default => 'success'
        };
        header('Location: expenses.php?msg=' . $msgType);
        exit;
    }
}
if (empty($message) && isset($_GET['msg'])) {
    $msgMap = [
        'created' => ['Expense recorded successfully!', 'success'],
        'updated' => ['Expense updated successfully!', 'success'],
        'deleted' => ['Expense deleted successfully!', 'success'],
    ];
    if (isset($msgMap[$_GET['msg']])) {
        [$message, $messageType] = $msgMap[$_GET['msg']];
    }
}
$page_title = 'Expense Management';
require_once 'includes/header.php';
$category_filter = $_GET['category'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = RECORDS_PER_PAGE;
$offset = ($page - 1) * $limit;
$conditions = [];
if (!empty($category_filter)) {
    $conditions['category'] = $category_filter;
}

$expenses = $expenseModel->getExpensesWithDetails($conditions, $limit, $offset);
$totalExpenses = $expenseModel->count($conditions);
$totalPages = ceil($totalExpenses / $limit);

$editExpense = null;
if (isset($_GET['edit'])) {
    $editExpense = $expenseModel->find((int)$_GET['edit']);
}
$expenseStats = $expenseModel->getExpenseStats();

$totalCashIn = 0;
$totalCashOut = 0;
$profit = 0; 
foreach ($expenseStats['by_payment_method'] as $pm) {
    if ($pm['payment_method'] === 'cash_in') {
        $totalCashIn = $pm['total'];
    } elseif ($pm['payment_method'] === 'cash_out') {
        $totalCashOut = $pm['total'];
    }
}
$profit = $totalCashIn - $totalCashOut;
$current_company_id = get_company_id();
$dbCategories = $categoryModel->findAll(['status' => 'active', 'company_id' => $current_company_id], 'name ASC');
$categories = array_unique(array_column($dbCategories, 'name'));
if (empty($categories)) {
    $categories = ['Rent', 'Utilities', 'Salaries', 'Materials', 'Equipment', 'Marketing', 'Transportation', 'Other'];
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
.cat-action-del {
    background: #fef2f2;
    border-color: #fecaca;
    color: #dc2626;
}
.cat-action-del:hover {
    background: #fee2e2;
    color: #b91c1c;
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
.cat-badge-cash-in {
    background-color: #f0fdf4 !important;
    color: #15803d !important;
    border: 1px solid #bbf7d0 !important;
}
.cat-badge-cash-out {
    background-color: #fef2f2 !important;
    color: #b91c1c !important;
    border: 1px solid #fecaca !important;
}
#searchInput, #dateFromFilter, #dateToFilter {
    height: 42px !important;
    font-size: 0.85rem !important;
    border-radius: 14px !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02) !important;
}
@media (max-width: 768px) {
    .cat-stat-box .fs-4 {
        font-size: 1.15rem !important;
    }
}
</style>
<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" style="border-radius: 12px;">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php 
$pageHeaderTitle = 'Expenses';
$pageHeaderSubtitle = 'Track operational spendings, cash flows, and tailor shop outgoing costs.';
include 'includes/page-header.php'; 
?>
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="cat-stat-box h-100 d-flex align-items-center justify-content-between" style="background: #eff6ff; border: 1px solid #dbeafe;">
            <div>
                <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.5px;">TOTAL EXPENSES</span>
                <div class="fs-4 fw-bold text-dark mt-1" id="statTotalExpenses"><?php echo number_format($expenseStats['total']); ?></div>
            </div>
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #dbeafe; color: #2563eb; font-size: 0.95rem;">
                <i class="fas fa-receipt"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="cat-stat-box h-100 d-flex align-items-center justify-content-between" style="background: #f0fdf4; border: 1px solid #dcfce7;">
            <div>
                <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.5px;">TOTAL CASH IN</span>
                <div class="fs-4 fw-bold text-success mt-1" id="statTotalCashIn"><?php echo format_currency($totalCashIn); ?></div>
            </div>
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #dcfce7; color: #16a34a; font-size: 0.95rem;">
                <i class="fas fa-arrow-down"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="cat-stat-box h-100 d-flex align-items-center justify-content-between" style="background: #fef2f2; border: 1px solid #fee2e2;">
            <div>
                <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.5px;">TOTAL CASH OUT</span>
                <div class="fs-4 fw-bold text-danger mt-1" id="statTotalCashOut"><?php echo format_currency($totalCashOut); ?></div>
            </div>
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #fee2e2; color: #dc2626; font-size: 0.95rem;">
                <i class="fas fa-arrow-up"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="cat-stat-box h-100 d-flex align-items-center justify-content-between" style="background: #faf5ff; border: 1px solid #ede9fe;">
            <div>
                <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.5px;">NET PROFIT / FLOW</span>
                <div class="fs-4 fw-bold <?php echo $profit >= 0 ? 'text-primary' : 'text-danger'; ?> mt-1" id="statProfit"><?php echo format_currency($profit); ?></div>
            </div>
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #ede9fe; color: #9333ea; font-size: 0.95rem;">
                <i class="fas fa-chart-line"></i>
            </div>
        </div>
    </div>
</div>

<!-- 3. Clean 2-Row Search, Filter & Action Bar -->
<div class="row g-2 align-items-center mb-2">
    <!-- Row 1: Search on left -->
    <div class="col-12 col-md-5">
        <div class="position-relative">
            <i class="fas fa-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.85rem;"></i>
            <input type="text" 
                   id="searchInput"
                   class="form-control ps-5" 
                   placeholder="Search description, payment method..."
                   autocomplete="off"
                   style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.85rem; height: 42px; padding-left: 2.4rem;">
        </div>
    </div>
    <!-- Row 1: Actions on right -->
    <div class="col-12 col-md-7 d-flex justify-content-md-end align-items-center gap-2">
        <?php 
        $exportCheck = SubscriptionHelper::canExportData($companyId ?? get_company_id());
        if ($exportCheck['allowed']): 
        ?>
            <button type="button" class="btn text-secondary fw-semibold d-inline-flex align-items-center justify-content-center gap-1 text-nowrap px-3 shadow-none" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.84rem; height: 42px;" onclick="exportExpenses()">
                <i class="fas fa-file-excel text-success me-1"></i>
                <span>Export</span>
            </button>
        <?php else: ?>
            <a href="subscriptions.php" class="btn text-secondary fw-semibold d-inline-flex align-items-center justify-content-center gap-1 text-nowrap px-3 shadow-none" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.84rem; height: 42px;" title="Upgrade to Premium to Export">
                <i class="fas fa-lock text-warning me-1"></i>
                <span>Export</span>
            </a>
        <?php endif; ?>
        <button type="button" class="btn text-white fw-semibold d-inline-flex align-items-center justify-content-center gap-1 text-nowrap px-4 shadow-sm" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 14px; font-size: 0.84rem; height: 42px; border: none; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.28);" data-bs-toggle="modal" data-bs-target="#expenseModal">
            <i class="fas fa-plus"></i>
            <span>Record Expense</span>
        </button>
    </div>
</div>

<!-- Row 2: Date Filters & Clear -->
<div class="row g-2 align-items-center mb-3">
    <div class="col-6 col-md-5">
        <input type="date" class="form-control" id="dateFromFilter" title="From Date" placeholder="From Date">
    </div>
    <div class="col-6 col-md-5">
        <input type="date" class="form-control" id="dateToFilter" title="To Date" placeholder="To Date">
    </div>
    <div class="col-12 col-md-2">
        <button type="button" id="clearFilters" class="btn btn-outline-danger align-items-center justify-content-center text-nowrap w-100" style="border-radius: 14px; height: 42px; font-size: 0.82rem; padding: 0 16px; display: inline-flex;" title="Clear Filters">
            <i class="fas fa-times me-1"></i> Clear
        </button>
    </div>
</div>

<div id="filterResults" class="mb-3" style="display: none;">
    <div class="alert alert-info py-2 px-3 d-flex align-items-center" style="border-radius: 12px; font-size: 0.85rem;">
        <i class="fas fa-info-circle me-2"></i>
        <span><strong id="filterCount">0</strong> expenses found</span>
    </div>
</div>

<!-- 4. Expenses Table -->
<div class="card shadow-sm border-0 mb-3" style="border-radius: 14px; overflow: hidden; border: 1px solid #f1f5f9 !important;">
    <div class="card-body p-0">
        <?php if (!empty($expenses)): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle category-table">
                    <thead class="cat-table-head">
                        <tr>
                            <th class="ps-3 py-3">DATE</th>
                            <th class="py-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span>DESCRIPTION</span>
                                    <button type="button" class="btn btn-link p-0 text-muted border-0 shadow-none" id="sortExpensesBtn" title="Sort A-Z" style="font-size: 0.75rem; line-height: 1; text-decoration: none;">
                                        <i class="fas fa-sort" id="sortIcon"></i>
                                    </button>
                                </div>
                            </th>
                            <th class="py-3">AMOUNT</th>
                            <th class="py-3">PAYMENT METHOD</th>
                            <th class="py-3">CREATED BY</th>
                            <th class="pe-3 py-3 text-end">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody id="expensesTableBody">
                        <?php foreach ($expenses as $expense): 
                            $isCashIn = $expense['payment_method'] === 'cash_in';
                            $pmBadgeClass = $isCashIn ? 'cat-badge-cash-in' : 'cat-badge-cash-out';
                            $pmText = ucfirst(str_replace('_', ' ', $expense['payment_method']));
                            $pmIcon = $isCashIn ? 'fa-arrow-down' : 'fa-arrow-up';
                            $amountClass = $isCashIn ? 'text-success' : 'text-danger';
                        ?>
                        <tr class="expense-row" data-description="<?php echo htmlspecialchars(strtolower($expense['description'])); ?>">
                            <td class="ps-3 py-3">
                                <div>
                                    <span class="fw-semibold text-dark" style="font-size: 0.85rem;"><?php echo format_date($expense['expense_date']); ?></span>
                                    <div class="text-muted" style="font-size: 0.72rem;"><?php echo format_date($expense['created_at'], 'H:i'); ?></div>
                                </div>
                            </td>
                            <td class="py-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; border-radius: 8px; background: <?php echo $isCashIn ? '#f0fdf4' : '#fef2f2'; ?>; color: <?php echo $isCashIn ? '#16a34a' : '#dc2626'; ?>; font-size: 0.82rem;">
                                        <i class="fas <?php echo $pmIcon; ?>"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="fw-semibold text-dark text-truncate" style="font-size: 0.88rem; max-width: 260px;">
                                            <?php echo display_val($expense['description']); ?>
                                        </div>
                                        <?php if (!empty($expense['receipt_image'])): ?>
                                            <div class="text-muted" style="font-size: 0.72rem;">
                                                <i class="fas fa-paperclip text-primary me-1"></i>Receipt attached
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3">
                                <strong class="<?php echo $amountClass; ?>" style="font-size: 0.9rem;">
                                    <?php echo ($isCashIn ? '+ ' : '- ') . format_currency($expense['amount']); ?>
                                </strong>
                            </td>
                            <td class="py-3">
                                <span class="badge rounded-pill fw-semibold px-2 py-1 <?php echo $pmBadgeClass; ?>" style="font-size: 0.75rem;">
                                    <?php echo $pmText; ?>
                                </span>
                            </td>
                            <td class="py-3">
                                <span class="text-secondary" style="font-size: 0.82rem;">
                                    <i class="fas fa-user-circle me-1 text-muted"></i><?php echo display_val($expense['created_by_name']); ?>
                                </span>
                            </td>
                            <td class="pe-3 py-3 text-end">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <button type="button" 
                                            class="cat-action-btn cat-action-edit" 
                                            onclick="editExpense(<?php echo htmlspecialchars(json_encode($expense)); ?>)"
                                            title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if (!empty($expense['receipt_image'])): ?>
                                        <button type="button" 
                                                class="cat-action-btn cat-action-view" 
                                                onclick="viewReceipt('<?php echo htmlspecialchars($expense['receipt_image']); ?>')"
                                                title="View Receipt">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" 
                                            class="cat-action-btn cat-action-del" 
                                            onclick="deleteExpense(<?php echo $expense['id']; ?>, '<?php echo htmlspecialchars($expense['description']); ?>')"
                                            title="Delete">
                                        <i class="fas fa-trash-alt"></i>
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
                <nav aria-label="Expense pagination">
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $category_filter ? '&category=' . urlencode($category_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $date_from ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo $date_to ? '&date_to=' . urlencode($date_to) : ''; ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $category_filter ? '&category=' . urlencode($category_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $date_from ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo $date_to ? '&date_to=' . urlencode($date_to) : ''; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $category_filter ? '&category=' . urlencode($category_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $date_from ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo $date_to ? '&date_to=' . urlencode($date_to) : ''; ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px; border-radius: 50%; background: #f8fafc; color: #94a3b8;">
                    <i class="fas fa-receipt fa-2x"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">No expenses found</h5>
                <p class="text-muted small mb-3">
                    <?php if (!empty($search) || !empty($category_filter) || !empty($date_from) || !empty($date_to)): ?>
                        No expenses match your search criteria.
                    <?php else: ?>
                        Get started by recording your first expense.
                    <?php endif; ?>
                </p>
                <?php if (empty($search) && empty($category_filter) && empty($date_from) && empty($date_to)): ?>
                    <button type="button" class="btn text-white fw-semibold px-4 py-2" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 12px; font-size: 0.85rem;" data-bs-toggle="modal" data-bs-target="#expenseModal">
                        <i class="fas fa-plus me-1"></i>Record Expense
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="expenseForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="expenseModalTitle">Record Expense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" id="expenseAction" value="create">
                    <input type="hidden" name="expense_id" id="expenseId">
                    
                    <div class="row">
                         <div class="mb-3">
                        <label for="description" class="form-label">Description *</label>
                        <textarea class="form-control" id="description" name="description" rows="2" required></textarea>
                    </div>
                        <div class="col-md-6 mb-3">
                            <label for="amount" class="form-label">Amount *</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                            </div>
                        </div>
                         <div class="col-md-6 mb-3">
                            <label for="expense_date" class="form-label">Expense Date *</label>
                            <input type="date" class="form-control" id="expense_date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div> 
                    <div class="row">
                       
                        <div class="col-md-12 mb-3">
                            <label for="payment_method" class="form-label">Payment Method *</label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="cash_in">Cash In</option>
                                <option value="cash_out">Cash Out</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Expense
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
          
            <div class="modal-header bg-danger text-white border-0">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="deleteModalLabel">
                            <strong>Confirm Deletion</strong>
                        </h5>
                        <small class="opacity-75">This action cannot be undone</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <div class="mb-3">
                        <i class="fas fa-trash-alt text-danger" style="font-size: 3rem; opacity: 0.7;"></i>
                    </div>
                    <h6 class="text-dark mb-3">Are you sure you want to delete this expense?</h6>
                </div>
                
                <div class="alert alert-warning border-0 shadow-sm">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-info-circle text-warning me-3 mt-1"></i>
                        <div>
                            <strong class="text-dark">Expense Details:</strong>
                            <p class="mb-0 mt-2" id="deleteExpenseDescription"></p>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-danger border-0 shadow-sm">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-exclamation-circle text-danger me-3 mt-1"></i>
                        <div>
                            <strong>Warning:</strong>
                            <p class="mb-0 mt-1">This action will permanently delete the expense record and cannot be undone.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 bg-light">
                <div class="w-100 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="expense_id" id="deleteExpenseId">
                        <button type="submit" class="btn btn-danger px-4">
                            <i class="fas fa-trash me-2"></i>Yes, Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="receiptImage" src="" alt="Receipt" class="img-fluid">
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="quickCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="categoryMsg" class="alert d-none"></div>
                <div class="mb-3">
                    <label for="new_category_name" class="form-label">Category Name *</label>
                    <input type="text" class="form-control" id="new_category_name" placeholder="Enter category name">
                </div>
                <div class="mb-3">
                    <label for="new_category_desc" class="form-label">Description</label>
                    <textarea class="form-control" id="new_category_desc" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveQuickCategory()">Save Category</button>
            </div>
        </div>
    </div>
</div>

<script>
function saveQuickCategory() {
    const name = document.getElementById('new_category_name').value.trim();
    const desc = document.getElementById('new_category_desc').value.trim();
    const msgDiv = document.getElementById('categoryMsg');
    
    if (!name) {
        msgDiv.className = 'alert alert-danger';
        msgDiv.textContent = 'Please enter a category name';
        msgDiv.classList.remove('d-none');
        return;
    }
    
    const formData = new FormData();
    formData.append('csrf_token', '<?php echo generate_csrf_token(); ?>');
    formData.append('name', name);
    formData.append('description', desc);
    
    fetch('ajax/add_category.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Add to dropdown
            const categorySelect = document.getElementById('category');
            const option = new Option(name, name);
            categorySelect.add(option, categorySelect.options[1]);
            categorySelect.value = name;
            
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('quickCategoryModal')).hide();
            
            // Reset form
            document.getElementById('new_category_name').value = '';
            document.getElementById('new_category_desc').value = '';
            msgDiv.classList.add('d-none');
            
            showToast('✅ Category added successfully', 'success');
        } else {
            msgDiv.className = 'alert alert-danger';
            msgDiv.textContent = data.error || 'Failed to add category';
            msgDiv.classList.remove('d-none');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        msgDiv.className = 'alert alert-danger';
        msgDiv.textContent = 'Server error. Please try again.';
        msgDiv.classList.remove('d-none');
    });
}
function editExpense(expense) {
    document.getElementById('expenseModalTitle').textContent = 'Edit Expense';
    document.getElementById('expenseAction').value = 'update';
    document.getElementById('expenseId').value = expense.id;
    
    // Populate form fields
    const catEl = document.getElementById('category');
    if (catEl) catEl.value = expense.category || '';
    document.getElementById('description').value = expense.description || '';
    document.getElementById('amount').value = expense.amount || '';
    document.getElementById('expense_date').value = expense.expense_date || '';
    document.getElementById('payment_method').value = expense.payment_method || 'cash';
    const refEl = document.getElementById('reference_number');
    if (refEl) refEl.value = expense.reference_number || '';
    
    // Show modal
    new bootstrap.Modal(document.getElementById('expenseModal')).show();
}

function deleteExpense(expenseId, description) {
    document.getElementById('deleteExpenseId').value = expenseId;
    document.getElementById('deleteExpenseDescription').textContent = description;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function viewReceipt(imagePath) {
    document.getElementById('receiptImage').src = '<?php echo APP_URL; ?>/uploads/receipts/' + imagePath;
    new bootstrap.Modal(document.getElementById('receiptModal')).show();
}

function exportExpenses() {
    // Show initial progress message
    showToast('🔄 Preparing your expense export... Please wait.', 'info');
    
    // Disable the export button temporarily
    const exportBtn = document.querySelector('button[onclick="exportExpenses()"]');
    const originalText = exportBtn.innerHTML;
    exportBtn.disabled = true;
    exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Exporting...';
    
    // Create FormData for AJAX request
    const formData = new FormData();
    formData.append('csrf_token', '<?php echo generate_csrf_token(); ?>');
    
    // Show progress message
    setTimeout(() => {
        showToast('📊 Generating Excel file with all expense details...', 'info');
    }, 500);
    
    // Make AJAX request
    fetch('ajax/export_expenses.php', {
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
        // Create download link
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = 'expenses_export_' + new Date().toISOString().slice(0, 19).replace(/:/g, '-') + '.xls';
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
document.getElementById('expenseModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('expenseModalTitle').textContent = 'Record Expense';
    document.getElementById('expenseAction').value = 'create';
    document.getElementById('expenseId').value = '';
    document.getElementById('expenseForm').reset();
    document.getElementById('expense_date').value = '<?php echo date('Y-m-d'); ?>';
});
document.addEventListener('DOMContentLoaded', function() {
    const formatCurrency = (amount) => '₹' + parseFloat(amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 });
    let filterTimeout;
    const searchInput = document.getElementById('searchInput');
    const dateFromFilter = document.getElementById('dateFromFilter');
    const dateToFilter = document.getElementById('dateToFilter');
    const clearFilters = document.getElementById('clearFilters');
    const filterResults = document.getElementById('filterResults');
    const filterCount = document.getElementById('filterCount');
    const expensesTable = document.getElementById('expensesTableBody') || document.querySelector('.table tbody');

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    [searchInput, dateFromFilter, dateToFilter].forEach(element => {
        if (element) {
            element.addEventListener('change', performFilter);
            if (element === searchInput) {
                element.addEventListener('input', performFilter);
            }
        }
    });

    if (clearFilters) {
        clearFilters.addEventListener('click', function() {
            if (searchInput) searchInput.value = '';
            if (dateFromFilter) dateFromFilter.value = '';
            if (dateToFilter) dateToFilter.value = '';
            
            window.location.href = 'expenses.php';
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
        const dateFrom = dateFromFilter ? dateFromFilter.value : '';
        const dateTo = dateToFilter ? dateToFilter.value : '';
        
        if (filterResults) filterResults.style.display = 'block';
        if (filterCount) filterCount.textContent = 'Filtering...';
        
        const params = new URLSearchParams();
        if (search) params.append('search', search);
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);
        params.append('page', '1');
        params.append('limit', '<?php echo RECORDS_PER_PAGE; ?>');
        
        const url = `ajax/filter_expenses.php?${params.toString()}`;
        
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(text => {
                if (text.trim().startsWith('<')) {
                    console.error('Server returned HTML instead of JSON. Full response:', text);
                    throw new Error('Server returned HTML instead of JSON. Check for PHP errors.');
                }
                
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        const expenses = Array.isArray(data.expenses) ? data.expenses : [];
                        
                        displayFilterResults(expenses);
                        if (data.pagination && data.pagination.total_expenses !== undefined) {
                            if (filterCount) filterCount.textContent = data.pagination.total_expenses;
                            const paginationContainer = document.getElementById('paginationContainer');
                            if (paginationContainer) {
                                if (data.pagination.total_pages <= 1) {
                                    paginationContainer.style.display = 'none';
                                } else {
                                    paginationContainer.style.display = 'block';
                                }
                            }
                        } else {
                            if (filterCount) filterCount.textContent = expenses.length;
                        }
                                                if (data.stats) {
                            const statTotalExpenses = document.getElementById('statTotalExpenses');
                            const statTotalCashIn = document.getElementById('statTotalCashIn');
                            const statTotalCashOut = document.getElementById('statTotalCashOut');
                            const statProfit = document.getElementById('statProfit');
                            
                            if (statTotalExpenses) statTotalExpenses.textContent = data.stats.total_expenses.toLocaleString('en-IN');
                            if (statTotalCashIn) statTotalCashIn.textContent = formatCurrency(data.stats.total_cash_in);
                            if (statTotalCashOut) statTotalCashOut.textContent = formatCurrency(data.stats.total_cash_out);
                            if (statProfit) statProfit.textContent = formatCurrency(data.stats.profit);
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
                console.error('Filter fetch error:', error);
                if (filterCount) filterCount.textContent = 'Filter failed: ' + error.message;
            });
    }

    function displayFilterResults(expenses) {
        const tableBody = document.getElementById('expensesTableBody') || document.querySelector('.table tbody');
        if (!tableBody) return;

        if (!expenses || expenses.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <i class="fas fa-search fa-2x text-muted mb-2"></i>
                        <h5 class="text-muted">No expenses found</h5>
                        <p class="text-muted small">Try adjusting your filter criteria</p>
                    </td>
                </tr>
            `;
            return;
        }
        
        let tableHTML = '';
        expenses.forEach(expense => {
            const expenseDateObj = new Date(expense.expense_date);
            const expenseDate = isNaN(expenseDateObj.getTime()) ? (expense.expense_date || 'N/A') :
                expenseDateObj.getFullYear() + '-' + 
                String(expenseDateObj.getMonth() + 1).padStart(2, '0') + '-' + 
                String(expenseDateObj.getDate()).padStart(2, '0');
                        let createdTime = '';
            if (expense.created_at) {
                const createdTimeObj = new Date(expense.created_at);
                if (!isNaN(createdTimeObj.getTime())) {
                    createdTime = String(createdTimeObj.getHours()).padStart(2, '0') + ':' + 
                        String(createdTimeObj.getMinutes()).padStart(2, '0');
                }
            }
            
            const isCashIn = expense.payment_method === 'cash_in';
            const badgeClass = isCashIn ? 'cat-badge-cash-in' : 'cat-badge-cash-out';
            const pmLabel = (expense.payment_method || '').replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
            const pmIcon = isCashIn ? 'fa-arrow-down' : 'fa-arrow-up';
            const amountClass = isCashIn ? 'text-success' : 'text-danger';
            const amountPrefix = isCashIn ? '+ ' : '- ';
            const cleanDesc = escapeHtml(expense.description || '');
            const expenseJson = JSON.stringify(expense).replace(/"/g, '&quot;');
            
            tableHTML += `
                <tr class="expense-row" data-description="${cleanDesc.toLowerCase()}">
                    <td class="ps-3 py-3">
                        <div>
                            <span class="fw-semibold text-dark" style="font-size: 0.85rem;">${expenseDate}</span>
                            ${createdTime ? `<div class="text-muted" style="font-size: 0.72rem;">${createdTime}</div>` : ''}
                        </div>
                    </td>
                    <td class="py-3">
                        <div class="d-flex align-items-center gap-2">
                            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; border-radius: 8px; background: ${isCashIn ? '#f0fdf4' : '#fef2f2'}; color: ${isCashIn ? '#16a34a' : '#dc2626'}; font-size: 0.82rem;">
                                <i class="fas ${pmIcon}"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="fw-semibold text-dark text-truncate" style="font-size: 0.88rem; max-width: 260px;">
                                    ${cleanDesc}
                                </div>
                                ${expense.receipt_image ? `
                                    <div class="text-muted" style="font-size: 0.72rem;">
                                        <i class="fas fa-paperclip text-primary me-1"></i>Receipt attached
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </td>
                    <td class="py-3">
                        <strong class="${amountClass}" style="font-size: 0.9rem;">
                            ${amountPrefix}${formatCurrency(expense.amount)}
                        </strong>
                    </td>
                    <td class="py-3">
                        <span class="badge rounded-pill fw-semibold px-2 py-1 ${badgeClass}" style="font-size: 0.75rem;">
                            ${pmLabel}
                        </span>
                    </td>
                    <td class="py-3">
                        <span class="text-secondary" style="font-size: 0.82rem;">
                            <i class="fas fa-user-circle me-1 text-muted"></i>${escapeHtml(expense.created_by_name || '-')}
                        </span>
                    </td>
                    <td class="pe-3 py-3 text-end">
                        <div class="d-inline-flex align-items-center gap-1">
                            <button type="button" 
                                    class="cat-action-btn cat-action-edit" 
                                    onclick="editExpense(${expenseJson})"
                                    title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            ${expense.receipt_image ? `
                                <button type="button" 
                                        class="cat-action-btn cat-action-view" 
                                        onclick="viewReceipt('${escapeHtml(expense.receipt_image)}')"
                                        title="View Receipt">
                                    <i class="fas fa-eye"></i>
                                </button>
                            ` : ''}
                            <button type="button" 
                                    class="cat-action-btn cat-action-del" 
                                    onclick="deleteExpense(${expense.id}, '${cleanDesc.replace(/'/g, "\\'")}')"
                                    title="Delete">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
        tableBody.innerHTML = tableHTML;
    }
    let sortAscending = true;
    const sortBtn = document.getElementById('sortExpensesBtn');
    if (sortBtn) {
        sortBtn.addEventListener('click', function() {
            sortAscending = !sortAscending;
            const sortIcon = document.getElementById('sortIcon');
            if (sortIcon) {
                sortIcon.className = sortAscending ? 'fas fa-sort-alpha-down' : 'fas fa-sort-alpha-up';
            }
            const tableBody = document.getElementById('expensesTableBody') || document.querySelector('.table tbody');
            if (tableBody) {
                const rows = Array.from(tableBody.querySelectorAll('tr.expense-row'));
                rows.sort((a, b) => {
                    const nameA = a.getAttribute('data-description') || '';
                    const nameB = b.getAttribute('data-description') || '';
                    return sortAscending ? nameA.localeCompare(nameB) : nameB.localeCompare(nameA);
                });
                rows.forEach(r => tableBody.appendChild(r));
            }
        });
    }
});
</script>
<?php require_once 'includes/footer.php'; ?>
<style>
    @media (max-width: 768px) {
        .card-header {
            display: flex !important;
            align-items: flex-start !important;
            flex-direction: column;
            gap: 15px;
        }
        .card-header .d-flex {
            flex-direction: column;
            width: 100% !important;
        }
    }
</style>