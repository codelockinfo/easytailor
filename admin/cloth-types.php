<!-- Favicon - Primary ICO format for Google Search -->
<link rel="icon" type="image/x-icon" href="../favicon.ico" sizes="16x16 32x32 48x48">
<!-- Favicon - PNG fallback -->
<link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon(2).png">
<link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon(2).png">
<!-- Apple Touch Icon -->
<link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon(2).png">

<?php
/**
 * Cloth Types Page
 * Tailoring Management System
 */

$page_title = 'Cloth Type Management';
require_once 'includes/header.php';

require_once 'models/ClothType.php';

$clothTypeModel = new ClothType();
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
                    'standard_rate' => !empty($_POST['standard_rate']) ? (float)$_POST['standard_rate'] : null,
                    'category' => trim($_POST['category']),
                    'measurement_chart_image' => sanitize_input($_POST['measurement_chart_image'] ?? ''),
                    'status' => $_POST['status']
                ];
                
                // Handle file upload for measurement chart
                if (isset($_FILES['chart_file']) && $_FILES['chart_file']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = '../uploads/measurement-charts/';
                    $fileName = time() . '_' . basename($_FILES['chart_file']['name']);
                    $targetPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['chart_file']['tmp_name'], $targetPath)) {
                        $data['measurement_chart_image'] = $targetPath;
                    }
                }
                
                // Validate name uniqueness
                if ($clothTypeModel->nameExists($data['name'])) {
                    $message = 'Cloth type name already exists';
                    $messageType = 'error';
                } else {
                    $clothTypeId = $clothTypeModel->create($data);
                    if ($clothTypeId) {
                        $message = 'Cloth type created successfully';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to create cloth type';
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'update':
                $clothTypeId = (int)$_POST['cloth_type_id'];
                $data = [
                    'name' => sanitize_input($_POST['name']),
                    'description' => sanitize_input($_POST['description']),
                    'standard_rate' => !empty($_POST['standard_rate']) ? (float)$_POST['standard_rate'] : null,
                    'category' => trim($_POST['category']),
                    'status' => $_POST['status']
                ];
                
                // Handle file upload for measurement chart if provided
                if (isset($_FILES['chart_file']) && $_FILES['chart_file']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = '../uploads/measurement-charts/';
                    $fileName = time() . '_' . basename($_FILES['chart_file']['name']);
                    $targetPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['chart_file']['tmp_name'], $targetPath)) {
                        $data['measurement_chart_image'] = $targetPath;
                    }
                } elseif (!empty($_POST['measurement_chart_image'])) {
                    // Keep existing chart if not uploading new one
                    $data['measurement_chart_image'] = sanitize_input($_POST['measurement_chart_image']);
                }
                
                // Validate name uniqueness
                if ($clothTypeModel->nameExists($data['name'], $clothTypeId)) {
                    $message = 'Cloth type name already exists';
                    $messageType = 'error';
                } else {
                    if ($clothTypeModel->update($clothTypeId, $data)) {
                        $message = 'Cloth type updated successfully';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to update cloth type';
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'delete':
                $clothTypeId = (int)$_POST['cloth_type_id'];
                if ($clothTypeModel->delete($clothTypeId)) {
                    $message = 'Cloth type deleted successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to delete cloth type';
                    $messageType = 'error';
                }
                break;
        }
    } else {
        $message = 'Invalid request';
        $messageType = 'error';
    }
}

// Get cloth types
$category_filter = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = RECORDS_PER_PAGE;
$offset = ($page - 1) * $limit;

$conditions = [];
if (!empty($category_filter)) {
    $conditions['category'] = $category_filter;
}

$clothTypes = $clothTypeModel->getClothTypesWithOrderCount();

// Filter by category if specified
if (!empty($category_filter)) {
    $clothTypes = array_filter($clothTypes, function($clothType) use ($category_filter) {
        return $clothType['category'] === $category_filter;
    });
}

// Apply search filter if provided
if (!empty($search)) {
    $searchLower = mb_strtolower($search);
    $clothTypes = array_filter($clothTypes, function($clothType) use ($searchLower) {
        $nameMatch = mb_stripos($clothType['name'], $searchLower) !== false;
        $categoryMatch = !empty($clothType['category']) && mb_stripos($clothType['category'], $searchLower) !== false;
        $descriptionMatch = !empty($clothType['description']) && mb_stripos($clothType['description'], $searchLower) !== false;
        return $nameMatch || $categoryMatch || $descriptionMatch;
    });
}

$clothTypes = array_values($clothTypes);
$totalClothTypes = count($clothTypes);

if ($limit > 0) {
    $clothTypes = array_slice($clothTypes, $offset, $limit);
    $totalPages = max(1, ceil($totalClothTypes / $limit));
} else {
    $totalPages = 1;
}

// Get cloth type for editing
$editClothType = null;
if (isset($_GET['edit'])) {
    $editClothType = $clothTypeModel->find((int)$_GET['edit']);
}

// Get statistics
$clothTypeStats = $clothTypeModel->getClothTypeStats();
?>


<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Cloth Type Statistics -->
<div class="row mb-2">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($clothTypeStats['total']); ?></div>
                    <div class="stat-label">Total Types</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-tshirt"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo number_format($clothTypeStats['active']); ?></div>
                    <div class="stat-label">Active Types</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo count($clothTypeStats['categories']); ?></div>
                    <div class="stat-label">Categories</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-tags"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-number"><?php echo array_sum(array_column($clothTypes, 'order_count')); ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" 
                           id="searchInput"
                           class="form-control" 
                           placeholder="Search cloth types..."
                           autocomplete="off">
                </div>
            </div>
            <div class="col-md-4">
                <select class="form-select" id="categoryFilter">
                    <option value="">All Categories</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="button" id="clearFilters" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-times"></i> Clear
                </button>
            </div>
        </div>
        <div id="filterResults" class="mt-3" style="display: none;">
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <span id="filterCount">0</span> cloth types found
            </div>
        </div>
    </div>
</div>

<!-- Cloth Types Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-tshirt me-2"></i>
            Cloth Types (<?php echo number_format($totalClothTypes); ?>)
        </h5>
        <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#clothTypeModal">
            <i class="fas fa-plus me-1"></i>Add Cloth Type
        </button>
    </div>  
    <div class="card-body">
        <?php if (!empty($clothTypes)): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Standard Rate</th>
                            <th>Orders</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clothTypes as $clothType): ?>
                        <tr>
                            <td>
                                <div>
                                    <strong><?php echo htmlspecialchars($clothType['name']); ?></strong>
                                    <?php if (!empty($clothType['description'])): ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars(substr($clothType['description'], 0, 50)); ?><?php echo strlen($clothType['description']) > 50 ? '...' : ''; ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($clothType['category'])): ?>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($clothType['category']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($clothType['standard_rate'])): ?>
                                    <strong><?php echo format_currency($clothType['standard_rate']); ?></strong>
                                <?php else: ?>
                                    <span class="text-muted">Not set</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-primary"><?php echo $clothType['order_count']; ?></span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $clothType['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($clothType['status']); ?>
                                </span>
                            </td>
                            <td><?php echo format_date($clothType['created_at']); ?></td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" 
                                            class="btn btn-outline-primary" 
                                            onclick="editClothType(<?php echo htmlspecialchars(json_encode($clothType)); ?>)"
                                            title="Edit" style="border: 1px solid #667eea;">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" 
                                            class="btn btn-outline-danger" 
                                            onclick="deleteClothType(<?php echo $clothType['id']; ?>, '<?php echo htmlspecialchars($clothType['name']); ?>')"
                                            title="Delete" style="border: 1px solid #667eea;">
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
                <nav aria-label="Cloth type pagination">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $category_filter ? '&category=' . urlencode($category_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $category_filter ? '&category=' . urlencode($category_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $category_filter ? '&category=' . urlencode($category_filter) : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-tshirt fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No cloth types found</h5>
                <p class="text-muted">
                    <?php if (!empty($search) || !empty($category_filter)): ?>
                        No cloth types match your search criteria.
                    <?php else: ?>
                        Get started by adding your first cloth type.
                    <?php endif; ?>
                </p>
                <?php if (empty($search) && empty($category_filter)): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#clothTypeModal">
                        <i class="fas fa-plus me-2"></i>Add Cloth Type
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Cloth Type Modal -->
<div class="modal fade" id="clothTypeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="clothTypeForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="clothTypeModalTitle">Add Cloth Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" id="clothTypeAction" value="create">
                    <input type="hidden" name="cloth_type_id" id="clothTypeId">
                    <input type="hidden" name="measurement_chart_image" id="existingChartImage">
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="name" class="form-label">Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="category" class="form-label">Category</label>
                            <input type="text" class="form-control" id="category" name="category" placeholder="e.g., Men's Wear, Women's Wear">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="standard_rate" class="form-label">Standard Rate</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" class="form-control" id="standard_rate" name="standard_rate" step="0.01" min="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="chart_file" class="form-label">Measurement Chart (Image/SVG)</label>
                        <input type="file" class="form-control" id="chart_file" name="chart_file" accept="image/*,.svg" onchange="previewChartFile(this)">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Upload a measurement diagram (PNG with transparent background recommended, or SVG)
                        </small>
                        <div class="alert alert-info mt-2" style="font-size: 0.875rem;">
                            <strong>Tip:</strong> For best results, use PNG with transparent background. 
                            Remove background at: <a href="https://www.remove.bg" target="_blank">remove.bg</a>
                        </div>
                        <div id="currentChartPreview" class="mt-2" style="display:none;">
                            <p class="mb-1"><strong>Current Chart:</strong></p>
                            <img id="chartPreviewImg" src="" alt="Measurement Chart" class="img-thumbnail" style="max-height: 200px; background: #f8f9fa;">
                        </div>
                        <div id="newChartPreview" class="mt-2" style="display:none;">
                            <p class="mb-1"><strong>New Chart Preview:</strong></p>
                            <img id="newChartPreviewImg" src="" alt="New Chart Preview" class="img-thumbnail" style="max-height: 200px; background: #f8f9fa;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Cloth Type
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <!-- Modal Header -->
            <div class="modal-header bg-danger text-white border-0">
                <div class="d-flex align-items-center w-100">
                    <div class="me-3">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 text-center">
                        <h5 class="modal-title mb-0" id="deleteModalLabel">
                            <strong>Confirm Deletion</strong>
                        </h5>
                        <small class="opacity-75">This action cannot be undone</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            
            <!-- Modal Body -->
            <div class="modal-body text-center py-4">
                <div class="mb-4">
                    <i class="fas fa-cut fa-4x text-danger mb-3"></i>
                </div>
                <h6 class="mb-3">Are you sure you want to delete this cloth type?</h6>
                <div class="alert alert-light border">
                    <strong id="deleteClothTypeName" class="text-primary"></strong>
                </div>
                <p class="text-muted mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    All associated measurements and orders will be affected.
                </p>
            </div>
            
            <!-- Modal Footer -->
            <div class="modal-footer border-0 justify-content-center pb-4">
                <button type="button" class="btn btn-outline-secondary me-3" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="cloth_type_id" id="deleteClothTypeId">
                    <button type="submit" class="btn btn-danger px-4">
                        <i class="fas fa-trash me-2"></i>Delete Cloth Type
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function editClothType(clothType) {
    document.getElementById('clothTypeModalTitle').textContent = 'Edit Cloth Type';
    document.getElementById('clothTypeAction').value = 'update';
    document.getElementById('clothTypeId').value = clothType.id;
    
    // Populate form fields
    document.getElementById('name').value = clothType.name || '';
    document.getElementById('description').value = clothType.description || '';
    document.getElementById('category').value = clothType.category || '';
    document.getElementById('standard_rate').value = clothType.standard_rate || '';
    document.getElementById('status').value = clothType.status || 'active';
    
    // Show existing measurement chart if available
    if (clothType.measurement_chart_image) {
        document.getElementById('existingChartImage').value = clothType.measurement_chart_image;
        document.getElementById('chartPreviewImg').src = '../' + clothType.measurement_chart_image.replace(/^\.\//, '');
        document.getElementById('currentChartPreview').style.display = 'block';
    } else {
        document.getElementById('currentChartPreview').style.display = 'none';
    }
    
    // Show modal
    new bootstrap.Modal(document.getElementById('clothTypeModal')).show();
}

function deleteClothType(clothTypeId, clothTypeName) {
    document.getElementById('deleteClothTypeId').value = clothTypeId;
    document.getElementById('deleteClothTypeName').textContent = clothTypeName;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Preview uploaded chart file
function previewChartFile(input) {
    const preview = document.getElementById('newChartPreview');
    const previewImg = document.getElementById('newChartPreviewImg');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            preview.style.display = 'block';
        };
        
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.style.display = 'none';
    }
}

// Reset modal when closed
document.getElementById('clothTypeModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('clothTypeModalTitle').textContent = 'Add Cloth Type';
    document.getElementById('clothTypeAction').value = 'create';
    document.getElementById('clothTypeId').value = '';
    document.getElementById('existingChartImage').value = '';
    document.getElementById('currentChartPreview').style.display = 'none';
    document.getElementById('newChartPreview').style.display = 'none';
    document.getElementById('clothTypeForm').reset();
});

// AJAX Cloth Type Filtering
let filterTimeout;
const searchInput = document.getElementById('searchInput');
const categoryFilter = document.getElementById('categoryFilter');
const clearFilters = document.getElementById('clearFilters');
const filterResults = document.getElementById('filterResults');
const filterCount = document.getElementById('filterCount');
const clothTypesTable = document.querySelector('.table tbody');

// Store original table content
const originalTableContent = clothTypesTable.innerHTML;

// Load filter options on page load
loadFilterOptions();

function loadFilterOptions() {
    fetch('ajax/filter_cloth_types.php?page=1&limit=1')
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
    
    // Ensure categories is an array
    const categories = Array.isArray(options.categories) ? options.categories : Object.values(options.categories);
    categories.forEach(category => {
        categorySelect.innerHTML += `<option value="${category}">${category}</option>`;
    });
}

// Add event listeners for all filters
[searchInput, categoryFilter].forEach(element => {
    element.addEventListener('change', performFilter);
    if (element === searchInput) {
        element.addEventListener('input', performFilter);
    }
});

clearFilters.addEventListener('click', function() {
    searchInput.value = '';
    categoryFilter.value = '';
    
    // Reload the page to show all cloth types
    window.location.href = 'cloth-types.php';
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
    
    // Show loading state
    filterResults.style.display = 'block';
    filterCount.textContent = 'Filtering...';
    
    // Build query string
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (category) params.append('category', category);
    params.append('page', '1');
    params.append('limit', '<?php echo RECORDS_PER_PAGE; ?>');
    
    fetch(`ajax/filter_cloth_types.php?${params.toString()}`)
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
                console.log('Parsed JSON data:', data);
                if (data.success) {
                    // Ensure cloth_types is an array
                    const clothTypes = Array.isArray(data.cloth_types) ? data.cloth_types : [];
                    console.log('Cloth types count:', clothTypes.length);
                    displayFilterResults(clothTypes);
                    if (data.pagination && data.pagination.total_cloth_types !== undefined) {
                        filterCount.textContent = data.pagination.total_cloth_types;
                    } else {
                        filterCount.textContent = clothTypes.length;
                    }
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

function displayFilterResults(clothTypes) {
    console.log('Displaying filter results, count:', clothTypes ? clothTypes.length : 0);
    
    if (!clothTypes || clothTypes.length === 0) {
        if (clothTypesTable) {
            clothTypesTable.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-4">
                    <i class="fas fa-search fa-2x text-muted mb-2"></i>
                    <h5 class="text-muted">No cloth types found</h5>
                    <p class="text-muted">Try adjusting your filter criteria</p>
                </td>
            </tr>
        `;
        }
        return;
    }
    
    let tableHTML = '';
    clothTypes.forEach(clothType => {
        // Format currency
        const formatCurrency = (amount) => '₹' + parseFloat(amount).toLocaleString('en-IN', { minimumFractionDigits: 2 });
        
        tableHTML += `
            <tr>
                <td>
                    <div>
                        <strong>${clothType.name}</strong>
                        ${clothType.description ? `<br><small class="text-muted">${clothType.description}</small>` : ''}
                    </div>
                </td>
                <td>
                    <span class="badge bg-info">${clothType.category}</span>
                </td>
                <td>
                    ${clothType.standard_rate ? `<div class="fw-bold">${formatCurrency(clothType.standard_rate)}</div>` : '<span class="text-muted">Not set</span>'}
                </td>
                <td>
                    <span class="badge bg-primary">${clothType.order_count}</span>
                </td>
                <td>
                    <span class="badge bg-${clothType.status === 'active' ? 'success' : 'secondary'}">${clothType.status.charAt(0).toUpperCase() + clothType.status.slice(1)}</span>
                </td>
                <td>
                    ${clothType.created_at ? (() => {
                        const date = new Date(clothType.created_at);
                        const year = date.getFullYear();
                        const month = String(date.getMonth() + 1).padStart(2, '0');
                        const day = String(date.getDate()).padStart(2, '0');
                        return `${year}-${month}-${day}`;
                    })() : '-'}
                </td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" 
                                class="btn btn-outline-primary" 
                                onclick="editClothType(${JSON.stringify(clothType).replace(/"/g, '&quot;')})"
                                title="Edit" style="border: 1px solid #667eea;">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" 
                                class="btn btn-outline-danger" 
                                onclick="deleteClothType(${clothType.id}, '${clothType.name.replace(/'/g, "\\'")}')"
                                title="Delete" style="border: 1px solid #667eea;">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    clothTypesTable.innerHTML = tableHTML;
}
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
        .card-header .btn-light {
            width: 100% !important;
        }
    }
</style>