<?php

require_once '../config/config.php';
require_login();

require_once 'models/Measurement.php';
require_once '../models/Customer.php';
require_once 'models/ClothType.php';

$measurementModel = new Measurement();
$customerModel = new Customer();
$clothTypeModel = new ClothType();

/**
 * Helper to find or create the default Walk-in Customer record for the company
 */
function getDefaultCustomerId($companyId, $customerModel) {
    $conditions = ['customer_code' => 'CUST000000'];
    if ($companyId) {
        $conditions['company_id'] = $companyId;
    }
    $existing = $customerModel->findOne($conditions);
    if ($existing) {
        return (int)$existing['id'];
    }
    
    // Create default customer for this company
    $data = [
        'customer_code' => 'CUST000000',
        'first_name' => 'Walk-in',
        'last_name' => 'Customer',
        'phone' => '0000000000',
        'status' => 'active'
    ];
    if ($companyId) {
        $data['company_id'] = $companyId;
    }
    return (int)$customerModel->createCustomer($data);
}

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
                
                $name = sanitize_input($_POST['name'] ?? '');
                $phone = sanitize_input($_POST['phone_number'] ?? '');
                $email = sanitize_input($_POST['email'] ?? '');
                $address = sanitize_input($_POST['address'] ?? '');
                $dob = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;

                $companyId = get_company_id();
                
                $data = [
                    'cloth_type_id' => (int)$_POST['cloth_type_id'],
                    'measurement_data' => $measurement_data,
                    'name' => $name,
                    'email' => $email,
                    'phone_number' => $phone,
                    'address' => $address,
                    'date_of_birth' => $dob,
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
                
                $name = sanitize_input($_POST['name'] ?? '');
                $phone = sanitize_input($_POST['phone_number'] ?? '');
                $email = sanitize_input($_POST['email'] ?? '');
                $address = sanitize_input($_POST['address'] ?? '');
                $dob = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;

                $companyId = get_company_id();

                $data = [
                    'measurement_data' => $measurement_data,
                    'name' => $name,
                    'email' => $email,
                    'phone_number' => $phone,
                    'address' => $address,
                    'date_of_birth' => $dob,
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

$stats = $measurementModel->getMeasurementStats();

$page_title = 'Measurements';
require_once 'includes/header.php';

$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Function for customer avatar design
if (!function_exists('getCustomerAvatarDesign')) {
    function getCustomerAvatarDesign($name, $index) {
        $palettes = [
            ['bg' => '#eff6ff', 'border' => '#dbeafe', 'color' => '#3b82f6'],
            ['bg' => '#f0fdf4', 'border' => '#dcfce7', 'color' => '#10b981'],
            ['bg' => '#faf5ff', 'border' => '#ede9fe', 'color' => '#8b5cf6'],
            ['bg' => '#fffbeb', 'border' => '#fef3c7', 'color' => '#f59e0b'],
            ['bg' => '#fdf2f8', 'border' => '#fce7f3', 'color' => '#ec4899'],
        ];
        return $palettes[$index % count($palettes)];
    }
}
?>

<style>
/* Exact Measurements Styling Matching Category Page */
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
    color: #ef4444;
}
.cat-action-del:hover {
    background: #fee2e2;
    color: #dc2626;
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
#clothTypeFilter {
    height: 42px !important;
    padding: 0.45rem 2.2rem 0.45rem 1rem !important;
    line-height: 1.4 !important;
    font-size: 0.85rem !important;
    border-radius: 14px !important;
    border: 1px solid #e2e8f0 !important;
    background-color: #ffffff !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02) !important;
}
#searchInput, #startDateFilter, #endDateFilter {
    height: 42px !important;
    font-size: 0.85rem !important;
    border-radius: 14px !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02) !important;
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

<?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show mb-3" style="border-radius: 12px;">
        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show mb-3" style="border-radius: 12px;">
        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- 1. Measurements Hero Banner (Using Shared Component) -->
<?php
$pageHeaderTitle = 'Measurements';
$pageHeaderSubtitle = 'Track and manage your customer garment measurements easily.';
include 'includes/page-header.php';
?>

<!-- 2. Stat Cards (2x2 on Mobile, 4 cols on Desktop) -->
<div class="row g-2 mb-3">
    <!-- Total Measurements -->
    <div class="col-6 col-md-3">
        <div class="cat-stat-box d-flex align-items-center justify-content-between" style="background: #faf5ff; border: 1px solid #ede9fe;">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #c084fc; color: #ffffff;">
                    <i class="fas fa-ruler-combined" style="font-size: 0.95rem;"></i>
                </div>
                <div class="min-w-0">
                    <div class="fw-bold text-dark lh-1" style="font-size: 1.35rem;"><?php echo number_format($stats['total']); ?></div>
                    <div class="text-muted text-truncate" style="font-size: 0.72rem; margin-top: 3px;">Total Measurements</div>
                </div>
            </div>
            <div class="d-flex align-items-end gap-1 ms-1 flex-shrink-0" style="height: 24px; color: #c084fc;">
                <span style="width: 3px; height: 35%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 60%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 45%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 100%; background: currentColor; border-radius: 2px;"></span>
            </div>
        </div>
    </div>
    
    <!-- This Month -->
    <div class="col-6 col-md-3">
        <div class="cat-stat-box d-flex align-items-center justify-content-between" style="background: #f0fdf4; border: 1px solid #dcfce7;">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #22c55e; color: #ffffff;">
                    <i class="fas fa-calendar-alt" style="font-size: 0.95rem;"></i>
                </div>
                <div class="min-w-0">
                    <div class="fw-bold text-dark lh-1" style="font-size: 1.35rem;"><?php echo number_format($stats['this_month']); ?></div>
                    <div class="text-muted text-truncate" style="font-size: 0.72rem; margin-top: 3px;">This Month</div>
                </div>
            </div>
            <div class="d-flex align-items-end gap-1 ms-1 flex-shrink-0" style="height: 24px; color: #4ade80;">
                <span style="width: 3px; height: 40%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 65%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 50%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 100%; background: currentColor; border-radius: 2px;"></span>
            </div>
        </div>
    </div>

    <!-- Cloth Types -->
    <div class="col-6 col-md-3">
        <div class="cat-stat-box d-flex align-items-center justify-content-between" style="background: #eff6ff; border: 1px solid #dbeafe;">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #3b82f6; color: #ffffff;">
                    <i class="fas fa-tshirt" style="font-size: 0.95rem;"></i>
                </div>
                <div class="min-w-0">
                    <div class="fw-bold text-dark lh-1" style="font-size: 1.35rem;"><?php echo count($stats['by_cloth_type']); ?></div>
                    <div class="text-muted text-truncate" style="font-size: 0.72rem; margin-top: 3px;">Cloth Types</div>
                </div>
            </div>
            <div class="d-flex align-items-end gap-1 ms-1 flex-shrink-0" style="height: 24px; color: #60a5fa;">
                <span style="width: 3px; height: 30%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 55%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 40%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 90%; background: currentColor; border-radius: 2px;"></span>
            </div>
        </div>
    </div>

    <!-- Active Customers -->
    <div class="col-6 col-md-3">
        <div class="cat-stat-box d-flex align-items-center justify-content-between" style="background: #fffbeb; border: 1px solid #fef3c7;">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #f59e0b; color: #ffffff;">
                    <i class="fas fa-users" style="font-size: 0.95rem;"></i>
                </div>
                <div class="min-w-0">
                    <div class="fw-bold text-dark lh-1" style="font-size: 1.35rem;"><?php echo number_format($totalActiveCustomers); ?></div>
                    <div class="text-muted text-truncate" style="font-size: 0.72rem; margin-top: 3px;">Active Customers</div>
                </div>
            </div>
            <div class="d-flex align-items-end gap-1 ms-1 flex-shrink-0" style="height: 24px; color: #fbbf24;">
                <span style="width: 3px; height: 45%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 70%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 55%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 100%; background: currentColor; border-radius: 2px;"></span>
            </div>
        </div>
    </div>
</div>

<!-- 3. Search and Action Row -->
<div class="row g-2 align-items-center mb-3">
    <div class="col-12 col-md-3">
        <div class="position-relative">
            <i class="fas fa-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.9rem;"></i>
            <input type="text" id="searchInput" class="form-control" placeholder="Search customer, phone..." autocomplete="off" value="<?php echo htmlspecialchars($search); ?>" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding-left: 2.4rem; padding-right: 0.8rem; height: 42px; font-size: 0.85rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-2">
        <select class="form-select" id="clothTypeFilter" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.85rem; height: 42px !important; padding: 0.45rem 2.2rem 0.45rem 0.8rem !important; line-height: 1.4 !important; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <option value="">All Cloth Types</option>
            <?php foreach ($clothTypes as $ct): ?>
                <option value="<?php echo $ct['id']; ?>" <?php echo $cloth_type_id == $ct['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($ct['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-sm-3 col-md-2">
        <input type="date" class="form-control" id="startDateFilter" title="Start Date" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.85rem; height: 42px; padding: 0.45rem 0.8rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
    </div>
    <div class="col-6 col-sm-3 col-md-2">
        <input type="date" class="form-control" id="endDateFilter" title="End Date" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.85rem; height: 42px; padding: 0.45rem 0.8rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
    </div>
    <div class="col-12 col-md-3 d-flex gap-2 justify-content-end">
        <button type="button" id="clearFilters" class="btn btn-outline-danger align-items-center justify-content-center" style="border-radius: 14px; height: 42px; font-size: 0.82rem; padding: 0 12px; <?php echo (empty($search) && empty($cloth_type_id) && empty($start_date) && empty($end_date)) ? 'display: none !important;' : 'display: inline-flex !important;'; ?>" title="Clear Filters">
            <i class="fas fa-times me-1"></i> Clear
        </button>
        <button type="button" class="btn text-white fw-semibold d-inline-flex align-items-center justify-content-center gap-1 flex-grow-1 text-nowrap px-3 shadow-sm" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 14px; font-size: 0.84rem; height: 42px; border: none; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.28);" data-bs-toggle="modal" data-bs-target="#addMeasurementModal">
            <i class="fas fa-plus"></i>
            <span>Add Measurement</span>
        </button>
    </div>
</div>

<div id="filterResults" class="mb-3" style="display: none;">
    <div class="alert alert-info py-2 px-3 d-flex align-items-center" style="border-radius: 12px; font-size: 0.85rem;">
        <i class="fas fa-info-circle me-2"></i>
        <span><strong id="filterCount">0</strong> measurements found</span>
    </div>
</div>

<!-- 4. Measurements Table (Shown in both Desktop & Mobile View) -->
<div class="card shadow-sm border-0 mb-3" style="border-radius: 14px; overflow: hidden; border: 1px solid #f1f5f9 !important;">
    <div class="card-body p-0">
        <?php if (!empty($measurements)): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle category-table">
                    <thead class="cat-table-head">
                        <tr>
                            <th class="ps-3 py-3" style="background-color: #f8fafc !important; color: #334155 !important; font-size: 0.78rem !important; font-weight: 700 !important; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0 !important;">
                                <div class="d-flex align-items-center gap-2">
                                    <span>CUSTOMER</span>
                                    <button type="button" class="btn btn-link p-0 text-muted border-0 shadow-none" id="sortMeasurementsBtn" title="Sort A-Z" style="font-size: 0.75rem; line-height: 1; text-decoration: none;">
                                        <i class="fas fa-sort" id="sortIcon"></i>
                                    </button>
                                </div>
                            </th>
                            <th class="py-3" style="background-color: #f8fafc !important; color: #334155 !important; font-size: 0.78rem !important; font-weight: 700 !important; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0 !important;">CLOTH TYPE</th>
                            <th class="py-3" style="background-color: #f8fafc !important; color: #334155 !important; font-size: 0.78rem !important; font-weight: 700 !important; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0 !important;">MEASUREMENTS</th>
                            <th class="py-3" style="background-color: #f8fafc !important; color: #334155 !important; font-size: 0.78rem !important; font-weight: 700 !important; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0 !important;">NOTES</th>
                            <th class="py-3" style="background-color: #f8fafc !important; color: #334155 !important; font-size: 0.78rem !important; font-weight: 700 !important; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0 !important;">DATE</th>
                            <th class="text-end pe-3 py-3" style="background-color: #f8fafc !important; color: #334155 !important; font-size: 0.78rem !important; font-weight: 700 !important; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0 !important;">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody id="measurementsTableBody">
                        <?php foreach ($measurements as $idx => $measurement): 
                            $cleanCustName = !empty($measurement['name']) ? $measurement['name'] : trim(($measurement['first_name'] ?? '') . ' ' . ($measurement['last_name'] ?? ''));
                            if (empty($cleanCustName)) $cleanCustName = 'Walk-in Customer';
                            $custDesign = getCustomerAvatarDesign($cleanCustName, $idx);
                            $initial = strtoupper(substr($cleanCustName, 0, 1) ?: 'C');
                        ?>
                        <tr class="measurement-row" data-name="<?php echo strtolower($cleanCustName); ?>">
                            <td class="ps-3 py-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="d-flex align-items-center justify-content-center flex-shrink-0 fw-bold" style="width: 36px; height: 36px; border-radius: 10px; background: <?php echo $custDesign['bg']; ?>; border: 1px solid <?php echo $custDesign['border']; ?>; color: <?php echo $custDesign['color']; ?>; font-size: 0.88rem;">
                                        <?php echo $initial; ?>
                                    </div>
                                    <div>
                                        <strong class="text-dark cust-name" style="font-size: 0.9rem;"><?php echo htmlspecialchars($cleanCustName); ?></strong>
                                        <div class="text-muted small" style="font-size: 0.74rem;"><?php echo htmlspecialchars(!empty($measurement['phone_number']) ? $measurement['phone_number'] : ($measurement['customer_code'] ?? '')); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3">
                                <span class="badge rounded-pill px-2 py-1 fw-medium" style="background: #e0f2fe; color: #0284c7; font-size: 0.75rem;">
                                    <?php echo htmlspecialchars($measurement['cloth_type_name'] ?? 'Custom'); ?>
                                </span>
                            </td>
                            <td class="py-3">
                                <?php 
                                $measurementData = json_decode($measurement['measurement_data'], true);
                                if (is_array($measurementData) && !empty($measurementData)): 
                                ?>
                                    <div class="d-flex flex-wrap gap-1 align-items-center" style="max-width: 280px;">
                                        <?php foreach (array_slice($measurementData, 0, 3) as $key => $value): ?>
                                            <span class="badge fw-medium px-2 py-1" style="background: #f1f5f9; color: #334155; font-size: 0.72rem; border-radius: 6px;">
                                                <strong style="color: #475569;"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $key))); ?>:</strong> <?php echo htmlspecialchars($value); ?>
                                            </span>
                                        <?php endforeach; ?>
                                        <?php if (count($measurementData) > 3): ?>
                                            <span class="text-muted small" style="font-size: 0.72rem;">+<?php echo count($measurementData) - 3; ?> more</span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3">
                                <?php if (!empty($measurement['notes'])): ?>
                                    <span class="text-muted small text-truncate d-inline-block" style="max-width: 140px; font-size: 0.78rem;" title="<?php echo htmlspecialchars($measurement['notes']); ?>">
                                        <?php echo htmlspecialchars($measurement['notes']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3">
                                <span class="text-muted small" style="font-size: 0.8rem;"><?php echo format_date($measurement['created_at'], 'M j, Y'); ?></span>
                            </td>
                            <td class="text-end pe-3 py-3">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <button type="button" class="cat-action-btn cat-action-edit" onclick="editMeasurement(<?php echo htmlspecialchars(json_encode($measurement)); ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="cat-action-btn cat-action-view" onclick="viewMeasurement(<?php echo $measurement['id']; ?>)" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="cat-action-btn cat-action-del" onclick="deleteMeasurement(<?php echo $measurement['id']; ?>)" title="Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div id="paginationContainer">
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
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-ruler-combined fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No measurements found</h5>
                <p class="text-muted small">Start by adding your first measurement.</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMeasurementModal" style="border-radius: 12px;">
                    <i class="fas fa-plus me-1"></i>Add Measurement
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>
<div class="modal fade" id="addMeasurementModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="POST" id="measurementForm" class="modal-content" onsubmit="debugFormSubmission(event)">
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
                                <label for="name" class="form-label">Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required placeholder="Enter customer name" 
                                       value="<?php echo htmlspecialchars($editMeasurement['name'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="Enter email address"
                                       value="<?php echo htmlspecialchars($editMeasurement['email'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone_number" class="form-label">Phone Number *</label>
                                <input type="text" class="form-control" id="phone_number" name="phone_number" required placeholder="Enter phone number"
                                       value="<?php echo htmlspecialchars($editMeasurement['phone_number'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_of_birth" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                                       value="<?php echo htmlspecialchars($editMeasurement['date_of_birth'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2" placeholder="Enter customer address"><?php echo htmlspecialchars($editMeasurement['address'] ?? ''); ?></textarea>
                    </div>

                    <input type="hidden" id="customer_id" name="customer_id" value="<?php echo htmlspecialchars($editMeasurement['customer_id'] ?? ''); ?>">

                    <div class="row">
                        <div class="col-md-12">
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

<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            
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
    const name = document.getElementById('name').value.trim();
    const phone = document.getElementById('phone_number').value.trim();
    const clothTypeId = document.getElementById('cloth_type_id').value;
    
    if (!name) {
        alert('Please enter customer name.');
        event.preventDefault();
        return false;
    }
    
    if (!phone) {
        alert('Please enter phone number.');
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
    addMeasurementField('hip/seat', '', 'text');
    addMeasurementField('thigh', '', 'text');
    addMeasurementField('knee', '', 'text');
    addMeasurementField('bottom', '', 'text');
    addMeasurementField('length', '', 'text');
}

function loadSareeFields() {
    // Clear existing fields
    const container = document.getElementById('measurementFieldsContainer');
    container.innerHTML = '';
    measurementFieldCount = 0;
    
    // Add saree/blouse-specific fields
    addMeasurementField('blouse_length', '', 'text');
    addMeasurementField('chest', '', 'text');
    addMeasurementField('waist', '', 'text');
    addMeasurementField('shoulder', '', 'text');
    addMeasurementField('sleeve_length', '', 'text');
    addMeasurementField('armhole', '', 'text');
    addMeasurementField('front_neck_depth', '', 'text');
    addMeasurementField('back_neck_depth', '', 'text');
}

function loadDressFields() {
    // Clear existing fields
    const container = document.getElementById('measurementFieldsContainer');
    container.innerHTML = '';
    measurementFieldCount = 0;
    
    // Add dress-specific fields
    addMeasurementField('length', '', 'text');
    addMeasurementField('shoulder', '', 'text');
    addMeasurementField('chest', '', 'text');
    addMeasurementField('waist', '', 'text');
    addMeasurementField('hips', '', 'text');
    addMeasurementField('sleeve_length', '', 'text');
    addMeasurementField('armhole', '', 'text');
    addMeasurementField('front_neck_depth', '', 'text');
    addMeasurementField('back_neck_depth', '', 'text');
}

function loadFieldsForClothType(clothTypeId) {
    // Only load fields if not editing (to preserve existing data when editing)
    <?php if (!$editMeasurement): ?>
    // Clear existing fields whenever cloth type changes
    const container = document.getElementById('measurementFieldsContainer');
    container.innerHTML = '';
    measurementFieldCount = 0;

    if (clothTypeId && clothTypeNames[clothTypeId]) {
        const clothTypeName = clothTypeNames[clothTypeId].toLowerCase();
        
        // Match various top garments (including suit, coat, blazer, sherwani)
        if (clothTypeName.includes('shirt') || clothTypeName.includes('kurta') || clothTypeName.includes('kameez') || clothTypeName.includes('top') || clothTypeName.includes('suit') || clothTypeName.includes('coat') || clothTypeName.includes('blazer') || clothTypeName.includes('jacket') || clothTypeName.includes('servani') || clothTypeName.includes('sherwani')) {
            loadShirtFields();
        // Match various bottom garments
        } else if (clothTypeName.includes('pent') || clothTypeName.includes('pant') || clothTypeName.includes('trouser') || clothTypeName.includes('pajama') || clothTypeName.includes('salwar') || clothTypeName.includes('bottom')) {
            loadPentFields();
        // Match saree and blouse
        } else if (clothTypeName.includes('saree') || clothTypeName.includes('sari') || clothTypeName.includes('blouse') || clothTypeName.includes('choli')) {
            loadSareeFields();
        // Match dresses
        } else if (clothTypeName.includes('dress') || clothTypeName.includes('gown') || clothTypeName.includes('frock')) {
            loadDressFields();
        } else {
            // Add default measurement fields for other types
            addMeasurementField('chest', '');
            addMeasurementField('waist', '');
            addMeasurementField('length', '');
        }
    } else {
        // Add default measurement fields if no specific type is selected
        addMeasurementField('chest', '');
        addMeasurementField('waist', '');
        addMeasurementField('length', '');
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
    document.getElementById('name').value = measurement.name || '';
    document.getElementById('email').value = measurement.email || '';
    document.getElementById('phone_number').value = measurement.phone_number || '';
    document.getElementById('address').value = measurement.address || '';
    document.getElementById('date_of_birth').value = measurement.date_of_birth || '';
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
    const chart = document.getElementById('measurementChart');
    if (chart) {
        chart.style.display = 'none';
    }
    const chartContainer = document.getElementById('measurementChartContainer');
    if (chartContainer) {
        chartContainer.style.display = 'none';
    }
});

// Fetch customer details on selection
document.getElementById('customer_id').addEventListener('change', function() {
    const customerId = this.value;
    if (customerId) {
        fetch('ajax/get_customer_details.php?customer_id=' + customerId)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.customer) {
                    document.getElementById('name').value = data.customer.name || '';
                    document.getElementById('email').value = data.customer.email || '';
                    document.getElementById('phone_number').value = data.customer.phone || '';
                    document.getElementById('address').value = data.customer.address || '';
                    document.getElementById('date_of_birth').value = data.customer.date_of_birth || '';
                }
            })
            .catch(error => {
                console.error('Error fetching customer details:', error);
            });
    } else {
        // Clear fields
        document.getElementById('name').value = '';
        document.getElementById('email').value = '';
        document.getElementById('phone_number').value = '';
        document.getElementById('address').value = '';
        document.getElementById('date_of_birth').value = '';
    }
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
                            <small class="text-muted d-block">Linked Account</small>
                            <h5 class="mb-0">${(measurement.first_name + ' ' + (measurement.last_name || '')).trim()} <span class="badge bg-primary fs-7">${measurement.customer_code}</span></h5>
                        </div>
                        <hr class="my-2">
                        <div class="mb-3">
                            <small class="text-muted d-block mb-2">Recorded Measurement Details</small>
                            <div class="row">
                                <div class="col-6 mb-2">
                                    <small class="text-muted d-block">Name</small>
                                    <strong>${measurement.name || 'N/A'}</strong>
                                </div>
                                <div class="col-6 mb-2">
                                    <small class="text-muted d-block">Phone</small>
                                    <strong>${measurement.phone_number || 'N/A'}</strong>
                                </div>
                                <div class="col-6 mb-2">
                                    <small class="text-muted d-block">Email</small>
                                    <strong>${measurement.email || 'N/A'}</strong>
                                </div>
                                <div class="col-6 mb-2">
                                    <small class="text-muted d-block">DOB</small>
                                    <strong>${measurement.date_of_birth ? new Date(measurement.date_of_birth).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'}</strong>
                                </div>
                                <div class="col-12">
                                    <small class="text-muted d-block">Address</small>
                                    <span>${measurement.address || 'N/A'}</span>
                                </div>
                            </div>
                        </div>
                        <hr class="my-2">
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
            <div class="col-md-6" style="margin-top: 10px;">
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
    const btn = document.querySelector(`button[onclick="deleteMeasurement(${id})"]`);
    const measurementRow = btn ? btn.closest('tr') : null;
    let customerName = 'Measurement';
    let clothType = '';
    
    if (measurementRow) {
        const custElem = measurementRow.querySelector('.cust-name');
        customerName = custElem ? custElem.textContent.trim() : (measurementRow.querySelector('td:first-child')?.textContent.trim() || 'Measurement');
        const clothElem = measurementRow.querySelector('td:nth-child(2)');
        clothType = clothElem ? clothElem.textContent.trim() : '';
    }
    
    document.getElementById('deleteMeasurementInfo').textContent = clothType ? `${customerName} - ${clothType}` : customerName;
    document.getElementById('deleteMeasurementId').value = id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Interactive Sort (A-Z / Z-A)
let sortAscending = true;
document.getElementById('sortMeasurementsBtn')?.addEventListener('click', function(e) {
    e.preventDefault();
    sortAscending = !sortAscending;
    const sortIcon = document.getElementById('sortIcon');
    if (sortIcon) {
        sortIcon.className = sortAscending ? 'fas fa-sort-alpha-down' : 'fas fa-sort-alpha-up';
    }
    
    const tableBody = document.getElementById('measurementsTableBody') || document.querySelector('.table tbody');
    if (tableBody) {
        const rows = Array.from(tableBody.querySelectorAll('tr.measurement-row'));
        rows.sort((a, b) => {
            const nameA = a.getAttribute('data-name') || '';
            const nameB = b.getAttribute('data-name') || '';
            return sortAscending ? nameA.localeCompare(nameB) : nameB.localeCompare(nameA);
        });
        rows.forEach(r => tableBody.appendChild(r));
    }
});

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
    setTimeout(function() {
    let filterTimeout;
    const searchInput = document.getElementById('searchInput');
    const clothTypeFilter = document.getElementById('clothTypeFilter');
    const startDateFilter = document.getElementById('startDateFilter');
    const endDateFilter = document.getElementById('endDateFilter');
    const clearFilters = document.getElementById('clearFilters');
    const filterResults = document.getElementById('filterResults');
    const filterCount = document.getElementById('filterCount');
    const measurementsTable = document.getElementById('measurementsTableBody') || document.querySelector('.table tbody');

    // Check if all required elements exist
    if (!searchInput || !clothTypeFilter) {
        console.error('Required DOM elements not found for measurement filtering');
        return;
    }

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
            }
        })
        .catch(error => {
            console.error('Error loading filter options:', error);
        });
}

function populateFilterOptions(options) {
    const clothTypeSelect = document.getElementById('clothTypeFilter');
    const currentVal = clothTypeSelect.value;
    clothTypeSelect.innerHTML = '<option value="">All Cloth Types</option>';
    options.cloth_types.forEach(clothType => {
        const isSelected = String(clothType.id) === String(currentVal) ? 'selected' : '';
        clothTypeSelect.innerHTML += `<option value="${clothType.id}" ${isSelected}>${clothType.name}</option>`;
    });
}

// Add event listeners for all filters
[searchInput, clothTypeFilter, startDateFilter, endDateFilter].forEach(element => {
    if (!element) return;
    element.addEventListener('change', performFilter);
    if (element === searchInput) {
        element.addEventListener('input', performFilter);
    }
});

if (clearFilters) {
    clearFilters.addEventListener('click', function() {
        searchInput.value = '';
        if (clothTypeFilter) clothTypeFilter.value = '';
        if (startDateFilter) startDateFilter.value = '';
        if (endDateFilter) endDateFilter.value = '';
        clearFilters.style.setProperty('display', 'none', 'important');
        window.location.href = 'measurements.php';
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
    const clothType = clothTypeFilter.value;
    const startDate = startDateFilter ? startDateFilter.value : '';
    const endDate = endDateFilter ? endDateFilter.value : '';
    
    const hasFilter = Boolean(search || clothType || startDate || endDate);
    if (clearFilters) {
        clearFilters.style.setProperty('display', hasFilter ? 'inline-flex' : 'none', 'important');
    }
    
    // Show loading state
    if (filterResults) {
        filterResults.style.display = hasFilter ? 'block' : 'none';
    }
    if (filterCount) {
        filterCount.textContent = 'Filtering...';
    }
    
    // Build query string
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (clothType) params.append('cloth_type_id', clothType);
    if (startDate) params.append('start_date', startDate);
    if (endDate) params.append('end_date', endDate);
    params.append('page', '1');
    params.append('limit', '<?php echo RECORDS_PER_PAGE; ?>');
    
    const url = `ajax/filter_measurements.php?${params.toString()}`;
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(text => {
            if (text.trim().startsWith('<')) {
                throw new Error('Server returned HTML instead of JSON.');
            }
            
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    const measurements = Array.isArray(data.measurements) ? data.measurements : [];
                    displayFilterResults(measurements);
                    if (data.pagination) {
                        if (filterCount) filterCount.textContent = data.pagination.total_measurements || measurements.length;
                        const paginationContainer = document.getElementById('paginationContainer');
                        if (paginationContainer) {
                            paginationContainer.style.display = (data.pagination.total_pages <= 1) ? 'none' : 'block';
                        }
                    } else {
                        if (filterCount) filterCount.textContent = measurements.length;
                    }
                } else {
                    if (filterCount) filterCount.textContent = 'Filter failed: ' + (data.error || 'Unknown error');
                    if (measurementsTable) {
                        measurementsTable.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <div class="alert alert-danger mb-0">
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
                if (filterCount) filterCount.textContent = 'Invalid response from server';
                if (measurementsTable) {
                    measurementsTable.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <div class="alert alert-danger mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Invalid response from server.
                            </div>
                        </td>
                    </tr>
                `;
                }
            }
        })
        .catch(error => {
            console.error('Filter fetch error:', error);
            if (filterCount) filterCount.textContent = 'Filter failed: ' + error.message;
        });
}

const avatarPalettes = [
    { bg: '#faf5ff', border: '#f3e8ff', color: '#9333ea' },
    { bg: '#f0fdf4', border: '#dcfce7', color: '#16a34a' },
    { bg: '#eff6ff', border: '#dbeafe', color: '#2563eb' },
    { bg: '#fffbeb', border: '#fef3c7', color: '#d97706' },
    { bg: '#fdf2f8', border: '#fce7f3', color: '#db2777' },
    { bg: '#f0fdfa', border: '#ccfbf1', color: '#0d9488' }
];

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function displayFilterResults(measurements) {
    if (!measurements || measurements.length === 0) {
        if (measurementsTable) {
            measurementsTable.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-5">
                    <i class="fas fa-search fa-2x text-muted mb-2"></i>
                    <h5 class="text-muted">No measurements found</h5>
                    <p class="text-muted small">Try adjusting your filter criteria</p>
                </td>
            </tr>
        `;
        }
        return;
    }
    
    let tableHTML = '';
    measurements.forEach((measurement, idx) => {
        let cleanCustName = measurement.name || ((measurement.first_name || '') + ' ' + (measurement.last_name || '')).trim();
        if (!cleanCustName) cleanCustName = 'Walk-in Customer';
        
        let initial = (cleanCustName.charAt(0) || 'C').toUpperCase();
        let subText = measurement.phone_number || measurement.customer_code || '';
        
        let charCodeSum = 0;
        for (let c = 0; c < cleanCustName.length; c++) charCodeSum += cleanCustName.charCodeAt(c);
        const palette = avatarPalettes[(charCodeSum + idx) % avatarPalettes.length];
        
        const clothTypeName = measurement.cloth_type_name || 'Custom';
        
        let measurementData = {};
        if (measurement.measurement_data) {
            try {
                if (typeof measurement.measurement_data === 'string') {
                    measurementData = JSON.parse(measurement.measurement_data);
                } else if (typeof measurement.measurement_data === 'object') {
                    measurementData = measurement.measurement_data;
                }
            } catch (e) {
                measurementData = {};
            }
        }
        
        const keys = Object.keys(measurementData || {});
        let measurementSummaryHtml = '';
        if (keys.length > 0) {
            measurementSummaryHtml = '<div class="d-flex flex-wrap gap-1 align-items-center" style="max-width: 280px;">';
            keys.slice(0, 3).forEach(key => {
                const formattedKey = key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ');
                const val = measurementData[key];
                measurementSummaryHtml += `<span class="badge fw-medium px-2 py-1" style="background: #f1f5f9; color: #334155; font-size: 0.72rem; border-radius: 6px;"><strong style="color: #475569;">${escapeHtml(formattedKey)}:</strong> ${escapeHtml(val)}</span>`;
            });
            if (keys.length > 3) {
                measurementSummaryHtml += `<span class="text-muted small" style="font-size: 0.72rem;">+${keys.length - 3} more</span>`;
            }
            measurementSummaryHtml += '</div>';
        } else {
            measurementSummaryHtml = '<span class="text-muted small">—</span>';
        }
        
        let formattedDate = '—';
        if (measurement.created_at) {
            const date = new Date(measurement.created_at);
            if (!isNaN(date.getTime())) {
                formattedDate = date.toLocaleDateString('en-US', { 
                    month: 'short', 
                    day: 'numeric', 
                    year: 'numeric' 
                });
            } else {
                formattedDate = escapeHtml(measurement.created_at);
            }
        }
        
        let notesDisplay = '<span class="text-muted small">—</span>';
        if (measurement.notes && measurement.notes.trim()) {
            notesDisplay = `<span class="text-muted small text-truncate d-inline-block" style="max-width: 140px; font-size: 0.78rem;" title="${escapeHtml(measurement.notes)}">${escapeHtml(measurement.notes)}</span>`;
        }
        
        tableHTML += `
            <tr class="measurement-row" data-name="${escapeHtml(cleanCustName.toLowerCase())}">
                <td class="ps-3 py-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="d-flex align-items-center justify-content-center flex-shrink-0 fw-bold" style="width: 36px; height: 36px; border-radius: 10px; background: ${palette.bg}; border: 1px solid ${palette.border}; color: ${palette.color}; font-size: 0.88rem;">
                            ${initial}
                        </div>
                        <div>
                            <strong class="text-dark cust-name" style="font-size: 0.9rem;">${escapeHtml(cleanCustName)}</strong>
                            <div class="text-muted small" style="font-size: 0.74rem;">${escapeHtml(subText)}</div>
                        </div>
                    </div>
                </td>
                <td class="py-3">
                    <span class="badge rounded-pill px-2 py-1 fw-medium" style="background: #e0f2fe; color: #0284c7; font-size: 0.75rem;">
                        ${escapeHtml(clothTypeName)}
                    </span>
                </td>
                <td class="py-3">
                    ${measurementSummaryHtml}
                </td>
                <td class="py-3">
                    ${notesDisplay}
                </td>
                <td class="py-3">
                    <span class="text-muted small" style="font-size: 0.8rem;">${formattedDate}</span>
                </td>
                <td class="text-end pe-3 py-3">
                    <div class="d-inline-flex align-items-center gap-1">
                        <button type="button" class="cat-action-btn cat-action-edit" onclick="editMeasurementFromFilter(${measurement.id})" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="cat-action-btn cat-action-view" onclick="viewMeasurement(${measurement.id})" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button type="button" class="cat-action-btn cat-action-del" onclick="deleteMeasurement(${measurement.id})" title="Delete">
                            <i class="fas fa-trash-alt"></i>
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
    }, 100);
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

/* Measurement input placeholder - light gray so it doesn't look like a real value */
#measurementFieldsContainer .form-control::placeholder,
#measurementFieldsContainer input::placeholder {
    color: #b0b0b1 !important;
    opacity: 1;
  
    font-size: 0.85rem;
}
#addMeasurementModal .form-control::placeholder,
#addMeasurementModal input::placeholder,
#addMeasurementModal textarea::placeholder {
    color: #adb5bd !important;
    opacity: 1;
}
</style>