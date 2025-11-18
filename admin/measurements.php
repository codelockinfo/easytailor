<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="favicon(2).png">

<?php
/**
 * Measurements Management Page
 * Tailoring Management System
 */

require_once '../config/config.php';
require_login();

require_once 'models/Measurement.php';
require_once 'models/Customer.php';
require_once 'models/ClothType.php';

$measurementModel = new Measurement();
$customerModel = new Customer();
$clothTypeModel = new ClothType();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Debug: Log the POST data
                error_log('POST data: ' . print_r($_POST, true));
                
                // Process measurement data from form
                $measurement_data = [];
                if (isset($_POST['measurement_keys']) && isset($_POST['measurement_values'])) {
                    for ($i = 0; $i < count($_POST['measurement_keys']); $i++) {
                        $key = sanitize_input($_POST['measurement_keys'][$i]);
                        $value = sanitize_input($_POST['measurement_values'][$i]);
                        if (!empty($key) && !empty($value)) {
                            $measurement_data[$key] = $value;
                        }
                    }
                }
                
                // Debug: Log processed measurement data
                error_log('Processed measurement data: ' . print_r($measurement_data, true));
                
                $data = [
                    'customer_id' => (int)$_POST['customer_id'],
                    'cloth_type_id' => (int)$_POST['cloth_type_id'],
                    'measurement_data' => $measurement_data,
                    'notes' => sanitize_input($_POST['notes'] ?? ''),
                    'images' => $_POST['images'] ?? []
                ];
                
                // Debug: Log final data
                error_log('Final data for database: ' . print_r($data, true));
                
                if ($measurementModel->createMeasurement($data)) {
                    $_SESSION['success_message'] = 'Measurement added successfully! Data: ' . json_encode($measurement_data);
                } else {
                    $_SESSION['error_message'] = 'Failed to add measurement. Please check the logs for details.';
                }
                break;
                
            case 'update':
                $id = (int)$_POST['measurement_id'];
                
                // Process measurement data from form
                $measurement_data = [];
                if (isset($_POST['measurement_keys']) && isset($_POST['measurement_values'])) {
                    for ($i = 0; $i < count($_POST['measurement_keys']); $i++) {
                        $key = sanitize_input($_POST['measurement_keys'][$i]);
                        $value = sanitize_input($_POST['measurement_values'][$i]);
                        if (!empty($key) && !empty($value)) {
                            $measurement_data[$key] = $value;
                        }
                    }
                }
                
                $data = [
                    'measurement_data' => $measurement_data,
                    'notes' => sanitize_input($_POST['notes'] ?? ''),
                    'images' => $_POST['images'] ?? []
                ];
                
                if ($measurementModel->updateMeasurement($id, $data)) {
                    $_SESSION['success_message'] = 'Measurement updated successfully!';
                } else {
                    $_SESSION['error_message'] = 'Failed to update measurement.';
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['measurement_id'];
                if ($measurementModel->delete($id)) {
                    $_SESSION['success_message'] = 'Measurement deleted successfully!';
                } else {
                    $_SESSION['error_message'] = 'Failed to delete measurement.';
                }
                break;
        }
        
        // Redirect to prevent form resubmission
        smart_redirect('measurements.php');
    }
}

// Get search parameters
$search = $_GET['search'] ?? '';
$customer_id = $_GET['customer_id'] ?? '';
$cloth_type_id = $_GET['cloth_type_id'] ?? '';

// Build conditions for filtering
$conditions = [];
if (!empty($search)) {
    // We'll handle search separately using the search method
}
if (!empty($customer_id)) {
    $conditions['customer_id'] = $customer_id;
}
if (!empty($cloth_type_id)) {
    $conditions['cloth_type_id'] = $cloth_type_id;
}

// Pagination
$page = (int)($_GET['page'] ?? 1);
$limit = RECORDS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Get measurements with company-aware totals
$searchParam = !empty($search) ? trim($search) : null;
$allMeasurements = $measurementModel->getMeasurementsWithDetails($conditions, null, 0, $searchParam);
$totalMeasurements = count($allMeasurements);

if ($limit > 0) {
    $measurements = array_slice($allMeasurements, $offset, $limit);
    $totalPages = max(1, ceil($totalMeasurements / $limit));
} else {
    $measurements = $allMeasurements;
    $totalPages = 1;
}

// Get data for dropdowns
$customers = $customerModel->findAll(['status' => 'active'], 'first_name, last_name');
$clothTypes = $clothTypeModel->findAll(['status' => 'active']);

// Get accurate count of active customers for the company
$totalActiveCustomers = $customerModel->count(['status' => 'active']);

// Get measurement for editing
$editMeasurement = null;
if (isset($_GET['edit'])) {
    $editMeasurement = $measurementModel->find((int)$_GET['edit']);
    if ($editMeasurement) {
        $editMeasurement['measurement_data'] = json_decode($editMeasurement['measurement_data'], true);
        $editMeasurement['images'] = json_decode($editMeasurement['images'], true);
    }
}

// Get measurement statistics
$stats = $measurementModel->getMeasurementStats();

// Set page title for header
$page_title = 'Measurements';
require_once 'includes/header.php';
?>

<div class="container-fluid">

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title"><?php echo number_format($stats['total']); ?></h4>
                            <p class="card-text">Total Measurements</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-ruler fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title"><?php echo number_format($stats['this_month']); ?></h4>
                            <p class="card-text">This Month</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-calendar fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title"><?php echo count($stats['by_cloth_type']); ?></h4>
                            <p class="card-text">Cloth Types</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-tshirt fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title"><?php echo number_format($totalActiveCustomers); ?></h4>
                            <p class="card-text">Active Customers</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="searchInput" class="form-label">Search</label>
                            <input type="text" class="form-control" id="searchInput" 
                                   placeholder="Search by customer, cloth type, or notes"
                                   autocomplete="off">
                        </div>
                        <div class="col-md-3">
                            <label for="customerFilter" class="form-label">Customer</label>
                            <select class="form-select" id="customerFilter">
                                <option value="">All Customers</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="clothTypeFilter" class="form-label">Cloth Type</label>
                            <select class="form-select" id="clothTypeFilter">
                                <option value="">All Cloth Types</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="button" id="clearFilters" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </button>
                            </div>
                        </div>
                    </div>
                    <div id="filterResults" class="mt-3" style="display: none;">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <span id="filterCount">0</span> measurements found
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Measurements Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Measurements (<?php echo number_format($totalMeasurements); ?>)</h5>
                    <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addMeasurementModal">
                        <i class="fas fa-plus me-1"></i>Add Measurement
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($measurements)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-ruler fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No measurements found</h5>
                            <p class="text-muted">Start by adding your first measurement.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMeasurementModal">
                                <i class="fas fa-plus me-1"></i>Add Measurement
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Cloth Type</th>
                                        <th>Measurements</th>
                                        <th>Notes</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($measurements as $measurement): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($measurement['first_name'] . ' ' . $measurement['last_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($measurement['customer_code']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo htmlspecialchars($measurement['cloth_type_name']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                $measurementData = json_decode($measurement['measurement_data'], true);
                                                if (is_array($measurementData) && !empty($measurementData)): 
                                                ?>
                                                    <div class="measurement-summary">
                                                        <?php foreach (array_slice($measurementData, 0, 3) as $key => $value): ?>
                                                            <small class="d-block">
                                                                <strong><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $key))); ?>:</strong> 
                                                                <?php echo htmlspecialchars($value); ?>
                                                            </small>
                                                        <?php endforeach; ?>
                                                        <?php if (count($measurementData) > 3): ?>
                                                            <small class="text-muted">+<?php echo count($measurementData) - 3; ?> more...</small>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">No measurements</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($measurement['notes'])): ?>
                                                    <span class="text-truncate d-inline-block" style="max-width: 150px;" 
                                                          title="<?php echo htmlspecialchars($measurement['notes']); ?>">
                                                        <?php echo htmlspecialchars($measurement['notes']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">No notes</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?php echo format_date($measurement['created_at'], 'M j, Y'); ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary" onclick="editMeasurement(<?php echo htmlspecialchars(json_encode($measurement)); ?>)" title="Edit" style="border: 1px solid #667eea;">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-outline-info" onclick="viewMeasurement(<?php echo $measurement['id']; ?>)" title="View Details" style="border: 1px solid #667eea;">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" onclick="deleteMeasurement(<?php echo $measurement['id']; ?>)" title="Delete" style="border: 1px solid #667eea;">
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
                            <nav aria-label="Measurements pagination">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&customer_id=<?php echo urlencode($customer_id); ?>&cloth_type_id=<?php echo urlencode($cloth_type_id); ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&customer_id=<?php echo urlencode($customer_id); ?>&cloth_type_id=<?php echo urlencode($cloth_type_id); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&customer_id=<?php echo urlencode($customer_id); ?>&cloth_type_id=<?php echo urlencode($cloth_type_id); ?>">Next</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Measurement Modal -->
<div class="modal fade" id="addMeasurementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="measurementForm" onsubmit="debugFormSubmission(event)">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <?php echo $editMeasurement ? 'Edit Measurement' : 'Add New Measurement'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?php echo $editMeasurement ? 'update' : 'add'; ?>">
                    <?php if ($editMeasurement): ?>
                        <input type="hidden" name="measurement_id" value="<?php echo $editMeasurement['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="customer_id" class="form-label">Customer *</label>
                                <select class="form-select" id="customer_id" name="customer_id" required <?php echo $editMeasurement ? 'readonly' : ''; ?>>
                                    <option value="">Select Customer</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?php echo $customer['id']; ?>" 
                                                <?php echo ($editMeasurement && $editMeasurement['customer_id'] == $customer['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="cloth_type_id" class="form-label">Cloth Type *</label>
                                <select class="form-select" id="cloth_type_id" name="cloth_type_id" required <?php echo $editMeasurement ? 'readonly' : ''; ?> onchange="loadMeasurementChart(this.value); loadFieldsForClothType(this.value)">
                                    <option value="">Select Cloth Type</option>
                                    <?php foreach ($clothTypes as $clothType): ?>
                                        <option value="<?php echo $clothType['id']; ?>" 
                                                data-chart="<?php echo htmlspecialchars($clothType['measurement_chart_image'] ? '../' . ltrim($clothType['measurement_chart_image'], './') : ''); ?>"
                                                <?php echo ($editMeasurement && $editMeasurement['cloth_type_id'] == $clothType['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($clothType['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Measurement Chart Display -->
                    <div id="measurementChartContainer" class="mb-4" style="display:none;">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-ruler-combined me-2"></i>Measurement Guide
                                </h6>
                            </div>
                            <div class="card-body text-center">
                                <img id="measurementChartImage" src="" alt="Measurement Guide" class="img-fluid" style="max-height: 500px;">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Measurements</label>
                        <div id="measurementFieldsContainer">
                            <!-- Dynamic measurement fields will be added here -->
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addCustomMeasurementField()">
                            <i class="fas fa-plus me-1"></i>Add Custom Field
                        </button>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="Additional notes about the measurements..."><?php echo htmlspecialchars($editMeasurement['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $editMeasurement ? 'Update Measurement' : 'Add Measurement'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Measurement Modal -->
<div class="modal fade" id="viewMeasurementModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-ruler me-2"></i>Measurement Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewMeasurementContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading measurement details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Close
                </button>
                <button type="button" class="btn btn-primary" onclick="printMeasurement()">
                    <i class="fas fa-print me-2"></i>Print
                </button>
            </div>
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
                    <i class="fas fa-ruler-combined fa-4x text-danger mb-3"></i>
                </div>
                <h6 class="mb-3">Are you sure you want to delete this measurement?</h6>
                <div class="alert alert-light border">
                    <strong id="deleteMeasurementInfo" class="text-primary"></strong>
                </div>
                <p class="text-muted mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    All measurement data will be permanently removed from the system.
                </p>
            </div>
            
            <!-- Modal Footer -->
            <div class="modal-footer border-0 justify-content-center pb-4">
                <button type="button" class="btn btn-outline-secondary me-3" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <form method="POST" class="d-inline" id="deleteMeasurementForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="measurement_id" id="deleteMeasurementId">
                    <button type="submit" class="btn btn-danger px-4">
                        <i class="fas fa-trash me-2"></i>Delete Measurement
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Store cloth type chart data
const clothTypeCharts = {};
<?php foreach ($clothTypes as $clothType): ?>
clothTypeCharts[<?php echo $clothType['id']; ?>] = '<?php echo htmlspecialchars($clothType['measurement_chart_image'] ? '../' . ltrim($clothType['measurement_chart_image'], './') : ''); ?>';
<?php endforeach; ?>

// Store cloth type names for field detection
const clothTypeNames = {};
<?php foreach ($clothTypes as $clothType): ?>
clothTypeNames[<?php echo $clothType['id']; ?>] = '<?php echo htmlspecialchars($clothType['name']); ?>';
<?php endforeach; ?>

// Load measurement chart based on selected cloth type
function loadMeasurementChart(clothTypeId) {
    const chartContainer = document.getElementById('measurementChartContainer');
    const chartImage = document.getElementById('measurementChartImage');
    
    console.log('Loading chart for cloth type ID:', clothTypeId);
    console.log('Available charts:', clothTypeCharts);
    
    if (clothTypeId && clothTypeCharts[clothTypeId] && clothTypeCharts[clothTypeId].trim() !== '') {
        const chartPath = clothTypeCharts[clothTypeId];
        console.log('Chart path:', chartPath);
        
        chartImage.src = chartPath;
        chartImage.onerror = function() {
            console.error('Failed to load chart image:', chartPath);
        };
        chartImage.onload = function() {
            console.log('Chart loaded successfully:', chartPath);
        };
        chartContainer.style.display = 'block';
    } else {
        console.log('No chart available for this cloth type');
        chartContainer.style.display = 'none';
    }
    
    // Load default fields for specific cloth types
    if (clothTypeId && clothTypeNames[clothTypeId]) {
        const clothTypeName = clothTypeNames[clothTypeId].toLowerCase();
        if (clothTypeName.includes('shirt')) {
            loadShirtFields();
        } else if (clothTypeName.includes('pent')) {
            loadPentFields();
        }
    }
}

// Load chart on page load if editing
<?php if ($editMeasurement && !empty($editMeasurement['cloth_type_id'])): ?>
window.addEventListener('DOMContentLoaded', function() {
    loadMeasurementChart(<?php echo $editMeasurement['cloth_type_id']; ?>);
});
<?php endif; ?>

// Debug function for form submission
function debugFormSubmission(event) {
    console.log('Form submission started...');
    
    // Get all form data
    const formData = new FormData(event.target);
    console.log('Form data:');
    for (let [key, value] of formData.entries()) {
        console.log(key + ': ' + value);
    }
    
    // Check measurement fields specifically
    const measurementKeys = document.querySelectorAll('input[name="measurement_keys[]"]');
    const measurementValues = document.querySelectorAll('input[name="measurement_values[]"], select[name="measurement_values[]"]');
    
    console.log('Measurement fields found:');
    console.log('Keys:', measurementKeys.length);
    console.log('Values:', measurementValues.length);
    
    measurementKeys.forEach((input, index) => {
        console.log(`Key ${index}: "${input.value}"`);
    });
    
    measurementValues.forEach((input, index) => {
        console.log(`Value ${index}: "${input.value}"`);
    });
    
    // Basic validation
    const customerId = document.getElementById('customer_id').value;
    const clothTypeId = document.getElementById('cloth_type_id').value;
    
    if (!customerId) {
        alert('Please select a customer.');
        event.preventDefault();
        return false;
    }
    
    if (!clothTypeId) {
        alert('Please select a cloth type.');
        event.preventDefault();
        return false;
    }
    
    // Check if at least one measurement field has both key and value
    let hasValidMeasurement = false;
    for (let i = 0; i < measurementKeys.length; i++) {
        const key = measurementKeys[i].value.trim();
        const value = measurementValues[i].value.trim();
        if (key && value) {
            hasValidMeasurement = true;
            break;
        }
    }
    
    if (!hasValidMeasurement) {
        alert('Please add at least one measurement with both name and value.');
        event.preventDefault();
        return false;
    }
    
    console.log('Form validation passed, submitting...');
    return true;
}

// Measurement field management
let measurementFieldCount = 0;

function addMeasurementField(key = '', value = '', fieldType = 'text') {
    measurementFieldCount++;
    const container = document.getElementById('measurementFieldsContainer');
    const fieldDiv = document.createElement('div');
    fieldDiv.className = 'row mb-2';
    
    let inputHtml = '';
    if (fieldType === 'color') {
        inputHtml = `<input type="color" class="form-control form-control-color" name="measurement_values[]" value="${value}" style="width: 60px; height: 38px;">`;
    } else if (fieldType === 'select') {
        const options = key === 'sleeve_type' ? 
            '<option value="half">Half Sleeve</option><option value="full">Full Sleeve</option>' :
            '<option value="yes">Yes</option><option value="no">No</option>';
        inputHtml = `<select class="form-select" name="measurement_values[]">
            <option value="">Select...</option>
            ${options}
        </select>`;
    } else {
        inputHtml = `<input type="text" class="form-control" name="measurement_values[]" 
                   placeholder="Value (e.g., 42 inches)" value="${value}">`;
    }
    
    fieldDiv.innerHTML = `
        <div class="col-md-5">
            <input type="text" class="form-control" name="measurement_keys[]" 
                   placeholder="Measurement name (e.g., chest, waist)" value="${key}" readonly>
        </div>
        <div class="col-md-5">
            ${inputHtml}
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeMeasurementField(this)" style="padding: 17px 25px;">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    container.appendChild(fieldDiv);
}

function loadShirtFields() {
    // Clear existing fields
    const container = document.getElementById('measurementFieldsContainer');
    container.innerHTML = '';
    measurementFieldCount = 0;
    
    // Add shirt-specific fields
    addMeasurementField('collar', '', 'text');
    addMeasurementField('shoulder', '', 'text');
    addMeasurementField('chest', '', 'text');
    addMeasurementField('waist', '', 'text');
    addMeasurementField('sleeve_type', '', 'select');
    addMeasurementField('sleeve_length', '', 'text');
    addMeasurementField('length', '', 'text');
    addMeasurementField('pocket', '', 'select');
    addMeasurementField('button_color', '#000000', 'color');
}

function loadPentFields() {
    // Clear existing fields
    const container = document.getElementById('measurementFieldsContainer');
    container.innerHTML = '';
    measurementFieldCount = 0;
    
    // Add pent-specific fields
    addMeasurementField('waist', '', 'text');
    addMeasurementField('hip', '', 'text');
    addMeasurementField('seat', '', 'text');
    addMeasurementField('rise', '', 'text');
    addMeasurementField('thigh', '', 'text');
    addMeasurementField('knee', '', 'text');
    addMeasurementField('calf', '', 'text');
    addMeasurementField('ankle_bottom_opening', '', 'text');
    addMeasurementField('outseam_full_length', '', 'text');
    addMeasurementField('inseam', '', 'text');
    addMeasurementField('crotch_depth', '', 'text');
}

function loadFieldsForClothType(clothTypeId) {
    // Only load fields if not editing (to preserve existing data when editing)
    <?php if (!$editMeasurement): ?>
    if (clothTypeId && clothTypeNames[clothTypeId]) {
        const clothTypeName = clothTypeNames[clothTypeId].toLowerCase();
        if (clothTypeName.includes('shirt')) {
            loadShirtFields();
        } else if (clothTypeName.includes('pent')) {
            loadPentFields();
        }
    }
    <?php endif; ?>
}

function addCustomMeasurementField() {
    measurementFieldCount++;
    const container = document.getElementById('measurementFieldsContainer');
    const fieldDiv = document.createElement('div');
    fieldDiv.className = 'row mb-2';
    
    fieldDiv.innerHTML = `
        <div class="col-md-5">
            <input type="text" class="form-control" name="measurement_keys[]" 
                   placeholder="Measurement name (e.g., chest, waist)" value="">
        </div>
        <div class="col-md-5">
            <input type="text" class="form-control" name="measurement_values[]" 
                   placeholder="Value (e.g., 42 inches)" value="">
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeMeasurementField(this)" style="padding: 17px 25px;">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    container.appendChild(fieldDiv);
}

function removeMeasurementField(button) {
    button.closest('.row').remove();
}

// Initialize measurement fields if editing
<?php if ($editMeasurement && !empty($editMeasurement['measurement_data'])): ?>
    <?php foreach ($editMeasurement['measurement_data'] as $key => $value): ?>
        <?php 
        $fieldType = 'text';
        if ($key === 'button_color') $fieldType = 'color';
        if (in_array($key, ['sleeve_type', 'pocket'])) $fieldType = 'select';
        ?>
        addMeasurementField('<?php echo htmlspecialchars($key); ?>', '<?php echo htmlspecialchars($value); ?>', '<?php echo $fieldType; ?>');
    <?php endforeach; ?>
<?php else: ?>
    // Add default measurement fields
    addMeasurementField('chest', '');
    addMeasurementField('waist', '');
    addMeasurementField('length', '');
<?php endif; ?>

// Edit measurement from filtered results
function editMeasurementFromFilter(id) {
    // Fetch full measurement data
    fetch('ajax/get_measurement_details.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.measurement) {
                editMeasurement(data.measurement);
            } else {
                alert('Failed to load measurement data');
            }
        })
        .catch(error => {
            console.error('Error loading measurement:', error);
            alert('Error loading measurement data');
        });
}

// View measurement details
function editMeasurement(measurement) {
    // Set modal title
    document.querySelector('#addMeasurementModal .modal-title').textContent = 'Edit Measurement';
    
    // Set form action
    document.querySelector('#addMeasurementModal form input[name="action"]').value = 'update';
    
    // Add hidden measurement ID field if not exists
    let measurementIdField = document.querySelector('input[name="measurement_id"]');
    if (!measurementIdField) {
        measurementIdField = document.createElement('input');
        measurementIdField.type = 'hidden';
        measurementIdField.name = 'measurement_id';
        document.querySelector('#addMeasurementModal form').appendChild(measurementIdField);
    }
    measurementIdField.value = measurement.id;
    
    // Populate form fields
    document.getElementById('customer_id').value = measurement.customer_id || '';
    document.getElementById('cloth_type_id').value = measurement.cloth_type_id || '';
    document.getElementById('notes').value = measurement.notes || '';
    
    // Load cloth type chart if available
    if (measurement.cloth_type_id) {
        loadMeasurementChart(measurement.cloth_type_id);
    }
    
    // Clear existing measurement fields
    const container = document.getElementById('measurementFieldsContainer');
    if (container) {
        container.innerHTML = '';
    }
    
    // Add existing measurement data
    if (measurement.measurement_data) {
        // Parse measurement_data if it's a string
        let measurementData = measurement.measurement_data;
        if (typeof measurementData === 'string') {
            try {
                measurementData = JSON.parse(measurementData);
            } catch (e) {
                console.error('Error parsing measurement data:', e);
                measurementData = {};
            }
        }
        
        if (measurementData && Object.keys(measurementData).length > 0) {
            Object.entries(measurementData).forEach(([key, value]) => {
                addMeasurementField(key, value);
            });
        }
    }
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('addMeasurementModal'));
    modal.show();
}


// Reset modal when closed
document.getElementById('addMeasurementModal').addEventListener('hidden.bs.modal', function() {
    // Reset modal title
    document.querySelector('#addMeasurementModal .modal-title').textContent = 'Add New Measurement';
    
    // Reset form action
    document.querySelector('#addMeasurementModal form input[name="action"]').value = 'add';
    
    // Remove measurement ID field if exists
    const measurementIdField = document.querySelector('input[name="measurement_id"]');
    if (measurementIdField) {
        measurementIdField.remove();
    }
    
    // Reset form
    document.getElementById('addMeasurementModal').querySelector('form').reset();
    
    // Clear measurement fields
    document.getElementById('measurementFieldsContainer').innerHTML = '';
    
    // Clear chart
    document.getElementById('measurementChart').style.display = 'none';
});

function viewMeasurement(id) {
    const modal = new bootstrap.Modal(document.getElementById('viewMeasurementModal'));
    const contentDiv = document.getElementById('viewMeasurementContent');
    
    // Show loading state
    contentDiv.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 text-muted">Loading measurement details...</p>
        </div>
    `;
    
    // Show modal
    modal.show();
    
    // Fetch measurement details via AJAX
    fetch('ajax/get_measurement_details.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayMeasurementDetails(data.measurement);
            } else {
                contentDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        ${data.message || 'Failed to load measurement details'}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            contentDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    An error occurred while loading measurement details.
                </div>
            `;
        });
}

function displayMeasurementDetails(measurement) {
    const contentDiv = document.getElementById('viewMeasurementContent');
    
    // Build measurement data HTML
    let measurementDataHtml = '';
    if (measurement.measurement_data && typeof measurement.measurement_data === 'object') {
        const data = measurement.measurement_data;
        measurementDataHtml = '<div class="row">';
        for (const [key, value] of Object.entries(data)) {
            if (value) {
                measurementDataHtml += `
                    <div class="col-md-4 mb-3">
                        <div class="card h-100" style="box-shadow: none;">
                            <div class="card-body">
                                <h6 class="text-muted mb-1 text-uppercase" style="font-size: 0.75rem;">
                                    ${key.replace(/_/g, ' ')}
                                </h6>
                                <h4 class="mb-0 text-primary">
                                    <strong>${value}</strong>
                                </h4>
                            </div>
                        </div>
                    </div>
                `;
            }
        }
        measurementDataHtml += '</div>';
    } else {
        measurementDataHtml = '<p class="text-muted">No measurement data available</p>';
    }
    
    // Build measurement chart HTML
    let chartHtml = '';
    if (measurement.measurement_chart) {
        chartHtml = `
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-ruler-combined me-2"></i>Measurement Guide
                    </h6>
                </div>
                <div class="card-body text-center">
                    <img src="${measurement.measurement_chart}" 
                         alt="Measurement Chart" 
                         class="img-fluid" 
                         style="max-height: 400px;">
                </div>
            </div>
        `;
    }
    
    // Build complete HTML
    contentDiv.innerHTML = `
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="fas fa-user me-2"></i>Customer Information
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted d-block">Customer Name</small>
                            <h5 class="mb-0">${measurement.first_name} ${measurement.last_name}</h5>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Customer Code</small>
                            <span class="badge bg-primary fs-6">${measurement.customer_code}</span>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Cloth Type</small>
                            <span class="badge bg-secondary fs-6">${measurement.cloth_type_name || 'N/A'}</span>
                            ${measurement.cloth_category ? '<span class="badge bg-info fs-6 ms-1">' + measurement.cloth_category + '</span>' : ''}
                        </div>
                        <div>
                            <small class="text-muted d-block">Recorded On</small>
                            <span><i class="fas fa-calendar me-1"></i>${new Date(measurement.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                ${chartHtml || '<div class="card h-100"><div class="card-body text-center text-muted" style="display: flex; flex-direction: column; justify-content: center;"><i class="fas fa-ruler fa-3x mb-3"></i><p>No measurement chart available</p></div></div>'}
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0">
                    <i class="fas fa-tape me-2"></i>Measurements
                </h6>
            </div>
            <div class="card-body">
                ${measurementDataHtml}
            </div>
        </div>
        
        ${measurement.notes ? `
        <div class="card">
            <div class="card-header bg-warning">
                <h6 class="mb-0">
                    <i class="fas fa-sticky-note me-2"></i>Notes
                </h6>
            </div>
            <div class="card-body">
                <p class="mb-0">${measurement.notes.replace(/\n/g, '<br>')}</p>
            </div>
        </div>
        ` : ''}
    `;
}

function printMeasurement() {
    const modalBody = document.getElementById('viewMeasurementContent');
    const printWindow = window.open('', '', 'height=600,width=800');
    
    printWindow.document.write('<html><head><title>Measurement Details</title>');
    printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">');
    printWindow.document.write('<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">');
    printWindow.document.write('<style>body { padding: 20px; } @media print { .no-print { display: none; } }</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write('<h2 class="text-center mb-4">Measurement Details</h2>');
    printWindow.document.write(modalBody.innerHTML);
    printWindow.document.write('</body></html>');
    
    printWindow.document.close();
    printWindow.focus();
    
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
}

// Delete measurement
function deleteMeasurement(id) {
    // Find the measurement data to display in the modal
    const measurementRow = document.querySelector(`button[onclick="deleteMeasurement(${id})"]`).closest('tr');
    const customerName = measurementRow.querySelector('td:nth-child(2)').textContent.trim();
    const clothType = measurementRow.querySelector('td:nth-child(3)').textContent.trim();
    
    // Set the measurement info in the modal
    document.getElementById('deleteMeasurementInfo').textContent = `${customerName} - ${clothType}`;
    document.getElementById('deleteMeasurementId').value = id;
    
    // Show the modal
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Auto-dismiss alerts
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);

// AJAX Measurement Filtering
document.addEventListener('DOMContentLoaded', function() {
    // Wait a bit more to ensure all content is rendered
    setTimeout(function() {
    let filterTimeout;
    const searchInput = document.getElementById('searchInput');
    const customerFilter = document.getElementById('customerFilter');
    const clothTypeFilter = document.getElementById('clothTypeFilter');
    const clearFilters = document.getElementById('clearFilters');
    const filterResults = document.getElementById('filterResults');
    const filterCount = document.getElementById('filterCount');
    const measurementsTable = document.querySelector('.table tbody');

    // Check if all required elements exist
    if (!searchInput || !customerFilter || !clothTypeFilter) {
        console.error('Required DOM elements not found for measurement filtering');
        console.error('searchInput:', !!searchInput);
        console.error('customerFilter:', !!customerFilter);
        console.error('clothTypeFilter:', !!clothTypeFilter);
        console.error('measurementsTable:', !!measurementsTable);
        return;
    }
    
    // Store original table content if table exists
    const originalTableContent = measurementsTable ? measurementsTable.innerHTML : '';

// Load filter options on page load
loadFilterOptions();

function loadFilterOptions() {
    fetch('ajax/filter_measurements.php?page=1&limit=0')
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
    // Populate customers
    const customerSelect = document.getElementById('customerFilter');
    customerSelect.innerHTML = '<option value="">All Customers</option>';
    options.customers.forEach(customer => {
        customerSelect.innerHTML += `<option value="${customer.id}">${customer.name}</option>`;
    });
    
    // Populate cloth types
    const clothTypeSelect = document.getElementById('clothTypeFilter');
    clothTypeSelect.innerHTML = '<option value="">All Cloth Types</option>';
    options.cloth_types.forEach(clothType => {
        clothTypeSelect.innerHTML += `<option value="${clothType.id}">${clothType.name}</option>`;
    });
}

// Add event listeners for all filters
[searchInput, customerFilter, clothTypeFilter].forEach(element => {
    element.addEventListener('change', performFilter);
    if (element === searchInput) {
        element.addEventListener('input', performFilter);
    }
});

clearFilters.addEventListener('click', function() {
    searchInput.value = '';
    customerFilter.value = '';
    clothTypeFilter.value = '';
    
    // Reload the page to show all measurements
    window.location.href = 'measurements.php';
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
    const customer = customerFilter.value;
    const clothType = clothTypeFilter.value;
    
    // Show loading state
    filterResults.style.display = 'block';
    filterCount.textContent = 'Filtering...';
    
    // Build query string
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (customer) params.append('customer_id', customer);
    if (clothType) params.append('cloth_type_id', clothType);
    params.append('page', '1');
    params.append('limit', '<?php echo RECORDS_PER_PAGE; ?>');
    
    const url = `ajax/filter_measurements.php?${params.toString()}`;
    console.log('Fetching from URL:', url);
    
    fetch(url)
        .then(response => {
            console.log('Response status:', response.status, response.statusText);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(text => {
            console.log('Raw response text (first 500 chars):', text.substring(0, 500));
            if (text.trim().startsWith('<')) {
                console.error('Server returned HTML instead of JSON. Full response:', text);
                throw new Error('Server returned HTML instead of JSON. Check for PHP errors.');
            }
            
            try {
                const data = JSON.parse(text);
                console.log('Parsed JSON data:', data);
                console.log('Success:', data.success);
                console.log('Measurements array:', data.measurements);
                console.log('Measurements length:', data.measurements ? data.measurements.length : 'undefined');
                
                if (data.success) {
                    // Ensure measurements is an array
                    const measurements = Array.isArray(data.measurements) ? data.measurements : [];
                    console.log('Final measurements count:', measurements.length);
                    
                    if (measurements.length > 0) {
                        console.log('First measurement:', measurements[0]);
                    }
                    
                    displayFilterResults(measurements);
                    if (data.pagination) {
                        filterCount.textContent = data.pagination.total_measurements || measurements.length;
                    } else {
                        filterCount.textContent = measurements.length;
                    }
                } else {
                    console.error('Filter error:', data.error);
                    filterCount.textContent = 'Filter failed: ' + (data.error || 'Unknown error');
                    if (measurementsTable) {
                        measurementsTable.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    ${data.error || 'Unknown error'}
                                </div>
                            </td>
                        </tr>
                    `;
                    }
                }
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Response text:', text);
                filterCount.textContent = 'Invalid response from server';
                if (measurementsTable) {
                    measurementsTable.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Invalid response from server. Please check the console for details.
                            </div>
                        </td>
                    </tr>
                `;
                }
            }
        })
        .catch(error => {
            console.error('Filter fetch error:', error);
            filterCount.textContent = 'Filter failed: ' + error.message;
        });
}

function displayFilterResults(measurements) {
    console.log('Displaying filter results, count:', measurements ? measurements.length : 0);
    
    if (!measurements || measurements.length === 0) {
        if (measurementsTable) {
            measurementsTable.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-4">
                    <i class="fas fa-search fa-2x text-muted mb-2"></i>
                    <h5 class="text-muted">No measurements found</h5>
                    <p class="text-muted">Try adjusting your filter criteria</p>
                </td>
            </tr>
        `;
        }
        return;
    }
    
    let tableHTML = '';
    measurements.forEach(measurement => {
        // Parse measurement data safely
        let measurementData = {};
        if (measurement.measurement_data) {
            try {
                if (typeof measurement.measurement_data === 'string') {
                    measurementData = JSON.parse(measurement.measurement_data);
                } else if (typeof measurement.measurement_data === 'object') {
                    measurementData = measurement.measurement_data;
                }
            } catch (e) {
                console.error('Error parsing measurement_data:', e, measurement.measurement_data);
                measurementData = {};
            }
        }
        const measurementCount = Object.keys(measurementData).length;
        
        // Build measurement summary (matching original structure)
        let measurementSummary = '';
        if (measurementCount > 0) {
            const measurementEntries = Object.entries(measurementData);
            measurementSummary = '<div class="measurement-summary">';
            
            // Show first 3 measurements
            measurementEntries.slice(0, 3).forEach(([key, value]) => {
                const formattedKey = key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ');
                measurementSummary += `<small class="d-block"><strong>${formattedKey}:</strong> ${value}</small>`;
            });
            
            // Show "+X more..." if there are more than 3
            if (measurementCount > 3) {
                measurementSummary += `<small class="text-muted">+${measurementCount - 3} more...</small>`;
            }
            
            measurementSummary += '</div>';
        } else {
            measurementSummary = '<span class="text-muted">No measurements</span>';
        }
        
        // Format date (matching original format)
        const date = new Date(measurement.created_at);
        const formattedDate = date.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric' 
        });
        
        // Build notes display (matching original structure)
        let notesDisplay = '';
        if (measurement.notes && measurement.notes.trim()) {
            notesDisplay = `<span class="text-truncate d-inline-block" style="max-width: 150px;" title="${measurement.notes}">${measurement.notes}</span>`;
        } else {
            notesDisplay = '<span class="text-muted">No notes</span>';
        }
        
        tableHTML += `
            <tr>
                <td>
                    <div>
                        <strong>${measurement.customer_name}</strong>
                        ${measurement.customer_code ? `<br><small class="text-muted">${measurement.customer_code}</small>` : ''}
                    </div>
                </td>
                <td>
                    <span class="badge bg-secondary">${measurement.cloth_type_name}</span>
                </td>
                <td>
                    ${measurementSummary}
                </td>
                <td>
                    ${notesDisplay}
                </td>
                <td>
                    <small>${formattedDate}</small>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary" onclick="editMeasurementFromFilter(${measurement.id})" title="Edit" style="border: 1px solid #667eea;">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-info" onclick="viewMeasurement(${measurement.id})" title="View Details" style="border: 1px solid #667eea;">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="deleteMeasurement(${measurement.id})" title="Delete" style="border: 1px solid #667eea;">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    if (measurementsTable) {
        measurementsTable.innerHTML = tableHTML;
    }
}
    }, 100); // Close setTimeout
}); // Close the DOMContentLoaded event listener
</script>

<?php include 'includes/footer.php'; ?>

<style>
    @media (max-width: 768px) {
    .col-md-3 .card {
        margin-bottom: 16px;
    }
    .col-md-2 label {
        display: none;
    }
}
.col-md-3 .card-body {
    padding: 24px;
}
.btn-outline-info:hover {
    color: #ffffff;
}
.badge {
    margin-top: 5px;
    font-size: 14px !important;
}
.modal-header.bg-primary {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%) !important;
}
</style>