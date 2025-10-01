<?php
/**
 * Expenses Page
 * Tailoring Management System
 */

$page_title = 'Expense Management';
require_once 'includes/header.php';

require_once 'models/Expense.php';

$expenseModel = new Expense();
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                $data = [
                    'category' => sanitize_input($_POST['category']),
                    'description' => sanitize_input($_POST['description']),
                    'amount' => (float)$_POST['amount'],
                    'expense_date' => $_POST['expense_date'],
                    'payment_method' => $_POST['payment_method'],
                    'reference_number' => sanitize_input($_POST['reference_number']),
                    'receipt_image' => $_POST['receipt_image'] ?: null,
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
                    'category' => sanitize_input($_POST['category']),
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
}

// Get expenses
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

// Get expense for editing
$editExpense = null;
if (isset($_GET['edit'])) {
    $editExpense = $expenseModel->find((int)$_GET['edit']);
}

// Get statistics
$expenseStats = $expenseModel->getExpenseStats();
$categories = $expenseModel->getExpenseCategories();

// Add common categories if none exist
if (empty($categories)) {
    $categories = ['Rent', 'Utilities', 'Salaries', 'Materials', 'Equipment', 'Marketing', 'Transportation', 'Other'];
}
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="h3 mb-0">Expense Management</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#expenseModal">
                <i class="fas fa-plus me-2"></i>Record Expense
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

<!-- Expense Statistics -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($expenseStats['total']); ?></div>
                    <div class="stat-label">Total Expenses</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-receipt"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo format_currency($expenseStats['total_amount']); ?></div>
                    <div class="stat-label">Total Amount</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-dollar-sign"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($expenseStats['this_month_count']); ?></div>
                    <div class="stat-label">This Month</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo format_currency($expenseStats['this_month_amount']); ?></div>
                    <div class="stat-label">Monthly Total</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
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
                           placeholder="Search expenses..."
                           autocomplete="off">
                </div>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="categoryFilter">
                    <option value="">All Categories</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" id="dateFromFilter" placeholder="From Date">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" id="dateToFilter" placeholder="To Date">
            </div>
            <div class="col-md-3">
                <button type="button" id="clearFilters" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-times"></i> Clear
                </button>
            </div>
        </div>
        <div id="filterResults" class="mt-3" style="display: none;">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <span id="filterCount">0</span> expenses found
            </div>
        </div>
    </div>
</div>

<!-- Expenses Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-receipt me-2"></i>
            Expenses (<?php echo number_format($totalExpenses); ?>)
        </h5>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="exportExpenses()">
                <i class="fas fa-download me-1"></i>Export
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php if (!empty($expenses)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Reference</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $expense): ?>
                        <tr>
                            <td>
                                <div>
                                    <?php echo format_date($expense['expense_date']); ?>
                                    <br>
                                    <small class="text-muted"><?php echo format_date($expense['created_at'], 'H:i'); ?></small>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo htmlspecialchars($expense['category']); ?></span>
                            </td>
                            <td>
                                <div>
                                    <?php echo htmlspecialchars($expense['description']); ?>
                                    <?php if (!empty($expense['receipt_image'])): ?>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-paperclip"></i> Receipt attached
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <strong class="text-danger"><?php echo format_currency($expense['amount']); ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo match($expense['payment_method']) {
                                        'cash' => 'success',
                                        'bank_transfer' => 'primary',
                                        'card' => 'info',
                                        'cheque' => 'warning',
                                        default => 'secondary'
                                    };
                                ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $expense['payment_method'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($expense['reference_number'])): ?>
                                    <?php echo htmlspecialchars($expense['reference_number']); ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($expense['created_by_name']); ?></td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" 
                                            class="btn btn-outline-primary" 
                                            onclick="editExpense(<?php echo htmlspecialchars(json_encode($expense)); ?>)"
                                            title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if (!empty($expense['receipt_image'])): ?>
                                        <button type="button" 
                                                class="btn btn-outline-info" 
                                                onclick="viewReceipt('<?php echo htmlspecialchars($expense['receipt_image']); ?>')"
                                                title="View Receipt">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" 
                                            class="btn btn-outline-danger" 
                                            onclick="deleteExpense(<?php echo $expense['id']; ?>, '<?php echo htmlspecialchars($expense['description']); ?>')"
                                            title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Expense pagination">
                    <ul class="pagination justify-content-center">
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
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No expenses found</h5>
                <p class="text-muted">
                    <?php if (!empty($search) || !empty($category_filter) || !empty($date_from) || !empty($date_to)): ?>
                        No expenses match your search criteria.
                    <?php else: ?>
                        Get started by recording your first expense.
                    <?php endif; ?>
                </p>
                <?php if (empty($search) && empty($category_filter) && empty($date_from) && empty($date_to)): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#expenseModal">
                        <i class="fas fa-plus me-2"></i>Record Expense
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Expense Modal -->
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
                        <div class="col-md-6 mb-3">
                            <label for="category" class="form-label">Category *</label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>">
                                        <?php echo htmlspecialchars($category); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="amount" class="form-label">Amount *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description *</label>
                        <textarea class="form-control" id="description" name="description" rows="2" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="expense_date" class="form-label">Expense Date *</label>
                            <input type="date" class="form-control" id="expense_date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="payment_method" class="form-label">Payment Method *</label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="card">Card</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="reference_number" class="form-label">Reference Number</label>
                            <input type="text" class="form-control" id="reference_number" name="reference_number" placeholder="Transaction ID, Check number, etc.">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="receipt_image" class="form-label">Receipt Image</label>
                            <input type="file" class="form-control" id="receipt_image" name="receipt_image" accept="image/*">
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

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this expense?</p>
                <p><strong id="deleteExpenseDescription"></strong></p>
                <p class="text-danger"><small>This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="expense_id" id="deleteExpenseId">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
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

<script>
function editExpense(expense) {
    document.getElementById('expenseModalTitle').textContent = 'Edit Expense';
    document.getElementById('expenseAction').value = 'update';
    document.getElementById('expenseId').value = expense.id;
    
    // Populate form fields
    document.getElementById('category').value = expense.category || '';
    document.getElementById('description').value = expense.description || '';
    document.getElementById('amount').value = expense.amount || '';
    document.getElementById('expense_date').value = expense.expense_date || '';
    document.getElementById('payment_method').value = expense.payment_method || 'cash';
    document.getElementById('reference_number').value = expense.reference_number || '';
    
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
    showToast('Export functionality will be implemented', 'info');
}

// Reset modal when closed
document.getElementById('expenseModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('expenseModalTitle').textContent = 'Record Expense';
    document.getElementById('expenseAction').value = 'create';
    document.getElementById('expenseId').value = '';
    document.getElementById('expenseForm').reset();
    document.getElementById('expense_date').value = '<?php echo date('Y-m-d'); ?>';
});

// AJAX Expense Filtering
let filterTimeout;
const searchInput = document.getElementById('searchInput');
const categoryFilter = document.getElementById('categoryFilter');
const dateFromFilter = document.getElementById('dateFromFilter');
const dateToFilter = document.getElementById('dateToFilter');
const clearFilters = document.getElementById('clearFilters');
const filterResults = document.getElementById('filterResults');
const filterCount = document.getElementById('filterCount');
const expensesTable = document.querySelector('.table tbody');

// Store original table content
const originalTableContent = expensesTable.innerHTML;

// Load filter options on page load
loadFilterOptions();

function loadFilterOptions() {
    fetch('ajax/filter_expenses.php?page=1&limit=1')
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
    // Populate categories
    const categorySelect = document.getElementById('categoryFilter');
    categorySelect.innerHTML = '<option value="">All Categories</option>';
    
    // Ensure categories is an array and decode HTML entities
    const categories = Array.isArray(options.categories) ? options.categories : Object.values(options.categories);
    categories.forEach(category => {
        // Decode HTML entities
        const decodedCategory = category.replace(/&#039;/g, "'").replace(/&amp;/g, "&");
        categorySelect.innerHTML += `<option value="${decodedCategory}">${decodedCategory}</option>`;
    });
}

// Add event listeners for all filters
[searchInput, categoryFilter, dateFromFilter, dateToFilter].forEach(element => {
    element.addEventListener('change', performFilter);
    if (element === searchInput) {
        element.addEventListener('input', performFilter);
    }
});

clearFilters.addEventListener('click', function() {
    searchInput.value = '';
    categoryFilter.value = '';
    dateFromFilter.value = '';
    dateToFilter.value = '';
    expensesTable.innerHTML = originalTableContent;
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
    const category = categoryFilter.value;
    const dateFrom = dateFromFilter.value;
    const dateTo = dateToFilter.value;
    
    // Show loading state
    filterResults.style.display = 'block';
    filterCount.textContent = 'Filtering...';
    
    // Build query string
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (category) params.append('category', category);
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);
    params.append('page', '1');
    params.append('limit', '<?php echo RECORDS_PER_PAGE; ?>');
    
    fetch(`ajax/filter_expenses.php?${params.toString()}`)
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
                    displayFilterResults(data.expenses);
                    filterCount.textContent = data.pagination.total_expenses;
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

function displayFilterResults(expenses) {
    if (expenses.length === 0) {
        expensesTable.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-4">
                    <i class="fas fa-search fa-2x text-muted mb-2"></i>
                    <h5 class="text-muted">No expenses found</h5>
                    <p class="text-muted">Try adjusting your filter criteria</p>
                </td>
            </tr>
        `;
        return;
    }
    
    let tableHTML = '';
    expenses.forEach(expense => {
        // Format date
        const expenseDate = new Date(expense.expense_date).toLocaleDateString('en-US', { 
            year: 'numeric', month: 'short', day: 'numeric' 
        });
        
        // Format currency
        const formatCurrency = (amount) => '₹' + parseFloat(amount).toLocaleString('en-IN', { minimumFractionDigits: 2 });
        
        tableHTML += `
            <tr>
                <td>
                    <span class="badge bg-primary">${expense.category}</span>
                </td>
                <td>
                    <div>
                        <strong>${expense.description}</strong>
                        ${expense.reference_number ? `<br><small class="text-muted">Ref: ${expense.reference_number}</small>` : ''}
                    </div>
                </td>
                <td>
                    <div class="text-end">
                        <div class="fw-bold text-danger">${formatCurrency(expense.amount)}</div>
                    </div>
                </td>
                <td>${expenseDate}</td>
                <td>
                    <span class="badge bg-info">${expense.payment_method}</span>
                </td>
                <td>
                    ${expense.receipt_image ? `
                        <a href="${expense.receipt_image}" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-image"></i> View
                        </a>
                    ` : '<span class="text-muted">No receipt</span>'}
                </td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" 
                                class="btn btn-outline-primary" 
                                onclick="editExpense(${JSON.stringify(expense).replace(/"/g, '&quot;')})"
                                title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" 
                                class="btn btn-outline-danger" 
                                onclick="deleteExpense(${expense.id}, '${expense.description.replace(/'/g, "\\'")}')"
                                title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    expensesTable.innerHTML = tableHTML;
}
</script>

<?php require_once 'includes/footer.php'; ?>

