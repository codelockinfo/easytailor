<?php
/**
 * Categories Page
 * Tailoring Management System
 */

$page_title = 'Category Management';
require_once 'includes/header.php';

require_once 'models/Category.php';

$categoryModel = new Category();
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                $data = [
                    'name' => sanitize_input($_POST['name']),
                    'description' => sanitize_input($_POST['description']),
                    'status' => $_POST['status']
                ];
                
                if ($categoryModel->nameExists($data['name'])) {
                    $message = 'Category name already exists';
                    $messageType = 'error';
                } else {
                    if ($categoryModel->create($data)) {
                        $message = 'Category created successfully';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to create category';
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'update':
                $categoryId = (int)$_POST['category_id'];
                $data = [
                    'name' => sanitize_input($_POST['name']),
                    'description' => sanitize_input($_POST['description']),
                    'status' => $_POST['status']
                ];
                
                if ($categoryModel->nameExists($data['name'], $categoryId)) {
                    $message = 'Category name already exists';
                    $messageType = 'error';
                } else {
                    if ($categoryModel->update($categoryId, $data)) {
                        $message = 'Category updated successfully';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to update category';
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'delete':
                $categoryId = (int)$_POST['category_id'];
                if ($categoryModel->delete($categoryId)) {
                    $message = 'Category deleted successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to delete category';
                    $messageType = 'error';
                }
                break;
        }
    }
}

$categories = $categoryModel->getCategoriesWithStats();
$catStats = $categoryModel->getCategoryStats();
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Category Statistics -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($catStats['total']); ?></div>
                    <div class="stat-label">Total Categories</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-tags"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($catStats['active']); ?></div>
                    <div class="stat-label">Active Categories</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #17a2b8 0%, #007bff 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($catStats['expense_count']); ?></div>
                    <div class="stat-label">Total Expenses</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-receipt"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo format_currency($catStats['total_amount']); ?></div>
                    <div class="stat-label">Total Amount</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-coins"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-tags me-2"></i>Expense Categories
        </h5>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#categoryModal">
            <i class="fas fa-plus me-1"></i>Add Category
        </button>
    </div>
    <div class="card-body">
        <!-- Search and Filter -->
        <div class="row mb-3">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" id="categorySearch" class="form-control" placeholder="Search categories..." autocomplete="off">
                </div>
            </div>
        </div>

        <?php if (!empty($categories)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="categoryTableBody">
                        <?php foreach ($categories as $cat): ?>
                        <tr class="category-row">
                            <td><strong class="cat-name"><?php echo ucwords(htmlspecialchars($cat['name'])); ?></strong></td>
                            <td class="cat-desc"><?php echo htmlspecialchars($cat['description']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $cat['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($cat['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary" onclick="editCategory(<?php echo htmlspecialchars(json_encode($cat)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" onclick="deleteCategory(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['name']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                <p>No categories found. Start by adding one!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="categoryForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="categoryModalTitle">Add Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" id="categoryAction" value="create">
                    <input type="hidden" name="category_id" id="categoryId">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Category Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <p>Are you sure you want to delete category: <strong id="deleteCategoryName"></strong>?</p>
                <p class="text-muted small">Note: Expenses assigned to this category will remain, but the category itself will be removed.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="category_id" id="deleteCategoryId">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function editCategory(cat) {
    document.getElementById('categoryModalTitle').textContent = 'Edit Category';
    document.getElementById('categoryAction').value = 'update';
    document.getElementById('categoryId').value = cat.id;
    document.getElementById('name').value = cat.name;
    document.getElementById('description').value = cat.description || '';
    document.getElementById('status').value = cat.status;
    new bootstrap.Modal(document.getElementById('categoryModal')).show();
}

function deleteCategory(id, name) {
    document.getElementById('deleteCategoryId').value = id;
    document.getElementById('deleteCategoryName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Search Functionality
document.getElementById('categorySearch').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase().trim();
    const rows = document.querySelectorAll('.category-row');
    
    rows.forEach(row => {
        const name = row.querySelector('.cat-name').textContent.toLowerCase();
        const desc = row.querySelector('.cat-desc').textContent.toLowerCase();
        
        if (name.includes(searchTerm) || desc.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

document.getElementById('categoryModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('categoryModalTitle').textContent = 'Add Category';
    document.getElementById('categoryAction').value = 'create';
    document.getElementById('categoryForm').reset();
});
</script>

<?php require_once 'includes/footer.php'; ?>
