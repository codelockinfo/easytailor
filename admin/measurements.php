<?php

require_once '../config/config.php';
require_login();

require_once 'models/Measurement.php';
require_once '../models/Customer.php';
require_once 'models/ClothType.php';
require_once '../helpers/SubscriptionHelper.php';

$measurementModel = new Measurement();
$customerModel = new Customer();
$clothTypeModel = new ClothType();

$clothTypes = $clothTypeModel->getActiveClothTypes();
if (empty($clothTypes)) {
    $clothTypes = $clothTypeModel->findAll(['status' => 'active'], 'name ASC');
}

if (!function_exists('formatMeasurementShortKey')) {
    function formatMeasurementShortKey($key) {
        $k = strtolower(trim($key));
        $map = [
            'length' => 'L',
            'blouse_length' => 'L',
            'kurta_length' => 'L',
            'shirt_length' => 'L',
            'pant_length' => 'L',
            'choli_length' => 'L',
            'dress_length' => 'L',
            'suit_length' => 'L',
            'chest' => 'C',
            'bust' => 'B',
            'upper_chest' => 'UC',
            'waist' => 'W',
            'hip' => 'H',
            'hips' => 'H',
            'hip_seat' => 'H',
            'hip/seat' => 'H',
            'shoulder' => 'Sh',
            'sleeve_length' => 'Sl',
            'sleeve' => 'Sl',
            'sleeves' => 'Sl',
            'collar' => 'Col',
            'neck' => 'N',
            'front_neck' => 'FN',
            'front_neck_depth' => 'FN',
            'back_neck' => 'BN',
            'back_neck_depth' => 'BN',
            'armhole' => 'Arm',
            'bicep' => 'Bic',
            'wrist' => 'Wr',
            'thigh' => 'Th',
            'inseam' => 'In',
            'outseam' => 'Out',
            'bottom' => 'Bot',
            'knee' => 'Kn',
            'cross_back' => 'CB',
            'apex' => 'Ap',
        ];
        if (isset($map[$k])) {
            return $map[$k];
        }
        $words = preg_split('/[\s_\-]+/', $k);
        if (count($words) > 1) {
            $initials = '';
            foreach ($words as $w) {
                if ($w !== '') $initials .= strtoupper($w[0]);
            }
            return $initials ?: strtoupper(substr($k, 0, 2));
        }
        return ucfirst(substr($k, 0, 1));
    }
}

function getDefaultCustomerId($companyId, $customerModel) {
    $conditions = ['customer_code' => 'CUST000000'];
    if ($companyId) {
        $conditions['company_id'] = $companyId;
    }
    $existing = $customerModel->findOne($conditions);
    if ($existing) {
        return (int)$existing['id'];
    }
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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $checkCompanyId = get_company_id();
                $customerLimitCheck = SubscriptionHelper::canAddCustomer($checkCompanyId);
                if (!$customerLimitCheck['allowed']) {
                    $_SESSION['error_message'] = $customerLimitCheck['message'] . ' ' . SubscriptionHelper::getUpgradeMessage('customers', SubscriptionHelper::getCurrentPlan($checkCompanyId));
                    header('Location: measurements.php');
                    exit;
                }
                error_log('POST data: ' . print_r($_POST, true));
                
                $name = sanitize_input($_POST['name'] ?? '');
                $phone = sanitize_input($_POST['phone_number'] ?? '');
                $email = sanitize_input($_POST['email'] ?? '');
                $address = sanitize_input($_POST['address'] ?? '');
                $dob = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
                $notes = sanitize_input($_POST['notes'] ?? '');
                $companyId = get_company_id();
                
                $addedCount = 0;
                $addedClothNames = [];
                if (!empty($_POST['garments']) && is_array($_POST['garments'])) {
                    foreach ($_POST['garments'] as $garment) {
                        $clothTypeId = (int)($garment['cloth_type_id'] ?? 0);
                        if (!$clothTypeId) {
                            continue;
                        }
                        $measurement_data = [];
                        if (!empty($garment['keys']) && !empty($garment['values']) && is_array($garment['keys'])) {
                            for ($k = 0; $k < count($garment['keys']); $k++) {
                                $kName = sanitize_input($garment['keys'][$k] ?? '');
                                $kVal = sanitize_input($garment['values'][$k] ?? '');
                                if ($kName !== '' && $kVal !== '') {
                                    $measurement_data[$kName] = $kVal;
                                }
                            }
                        }

                        $data = [
                            'cloth_type_id' => $clothTypeId,
                            'measurement_data' => $measurement_data,
                            'name' => $name,
                            'email' => $email,
                            'phone_number' => $phone,
                            'address' => $address,
                            'date_of_birth' => $dob,
                            'notes' => $notes,
                            'images' => []
                        ];

                        if ($measurementModel->createMeasurement($data)) {
                            $addedCount++;
                            $ctFoundName = '';
                            if (!empty($clothTypes) && is_array($clothTypes)) {
                                foreach ($clothTypes as $ct) {
                                    if ((int)$ct['id'] === $clothTypeId) {
                                        $ctFoundName = $ct['name'];
                                        break;
                                    }
                                }
                            }
                            if (!$ctFoundName) {
                                $directCt = $clothTypeModel->find($clothTypeId);
                                if ($directCt && !empty($directCt['name'])) {
                                    $ctFoundName = $directCt['name'];
                                }
                            }
                            if ($ctFoundName) {
                                $addedClothNames[] = $ctFoundName;
                            }
                        }
                    }
                } elseif (!empty($_POST['cloth_type_id'])) {
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
                        'cloth_type_id' => (int)$_POST['cloth_type_id'],
                        'measurement_data' => $measurement_data,
                        'name' => $name,
                        'email' => $email,
                        'phone_number' => $phone,
                        'address' => $address,
                        'date_of_birth' => $dob,
                        'notes' => $notes,
                        'images' => $_POST['images'] ?? []
                    ];

                    if ($measurementModel->createMeasurement($data)) {
                        $addedCount++;
                    }
                }

                if ($addedCount > 0) {
                    $clothList = !empty($addedClothNames) ? ' (' . implode(', ', array_unique($addedClothNames)) . ')' : '';
                    $_SESSION['success_message'] = $addedCount . ' measurement' . ($addedCount > 1 ? 's' : '') . ' added successfully!' . $clothList;
                } else {
                    $_SESSION['error_message'] = 'Failed to add measurement. Please select at least one cloth type and enter measurement values.';
                }
                break;
                
            case 'update':
                $id = (int)($_POST['measurement_id'] ?? 0);
                $existingIds = [];
                if (!empty($_POST['existing_measurement_ids'])) {
                    $rawExisting = $_POST['existing_measurement_ids'];
                    if (is_string($rawExisting)) {
                        $existingIds = json_decode($rawExisting, true) ?: [];
                    } elseif (is_array($rawExisting)) {
                        $existingIds = $rawExisting;
                    }
                }
                if (empty($existingIds) && $id > 0) {
                    $existingIds = [$id];
                }
                
                $name = sanitize_input($_POST['name'] ?? '');
                $phone = sanitize_input($_POST['phone_number'] ?? '');
                $email = sanitize_input($_POST['email'] ?? '');
                $address = sanitize_input($_POST['address'] ?? '');
                $dob = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
                $notes = sanitize_input($_POST['notes'] ?? '');
                
                $keptIds = [];
                $updatedCount = 0;

                if (!empty($_POST['garments']) && is_array($_POST['garments'])) {
                    foreach ($_POST['garments'] as $garment) {
                        $clothTypeId = (int)($garment['cloth_type_id'] ?? 0);
                        if (!$clothTypeId) {
                            continue;
                        }

                        $measurement_data = [];
                        if (!empty($garment['keys']) && !empty($garment['values']) && is_array($garment['keys'])) {
                            for ($k = 0; $k < count($garment['keys']); $k++) {
                                $kName = sanitize_input($garment['keys'][$k] ?? '');
                                $kVal = sanitize_input($garment['values'][$k] ?? '');
                                if ($kName !== '' && $kVal !== '') {
                                    $measurement_data[$kName] = $kVal;
                                }
                            }
                        }

                        $targetId = (int)($garment['measurement_id'] ?? 0);
                        if ($targetId > 0 && in_array($targetId, $existingIds)) {
                            $data = [
                                'cloth_type_id' => $clothTypeId,
                                'measurement_data' => $measurement_data,
                                'name' => $name,
                                'email' => $email,
                                'phone_number' => $phone,
                                'address' => $address,
                                'date_of_birth' => $dob,
                                'notes' => $notes
                            ];
                            if ($measurementModel->updateMeasurement($targetId, $data)) {
                                $keptIds[] = $targetId;
                                $updatedCount++;
                            }
                        } else {
                            // Check for any unused existing ID to reuse
                            $unusedId = null;
                            foreach ($existingIds as $eId) {
                                if (!in_array($eId, $keptIds)) {
                                    $unusedId = $eId;
                                    break;
                                }
                            }

                            if ($unusedId) {
                                $data = [
                                    'cloth_type_id' => $clothTypeId,
                                    'measurement_data' => $measurement_data,
                                    'name' => $name,
                                    'email' => $email,
                                    'phone_number' => $phone,
                                    'address' => $address,
                                    'date_of_birth' => $dob,
                                    'notes' => $notes
                                ];
                                if ($measurementModel->updateMeasurement($unusedId, $data)) {
                                    $keptIds[] = $unusedId;
                                    $updatedCount++;
                                }
                            } else {
                                // Create new garment measurement record
                                $data = [
                                    'cloth_type_id' => $clothTypeId,
                                    'measurement_data' => $measurement_data,
                                    'name' => $name,
                                    'email' => $email,
                                    'phone_number' => $phone,
                                    'address' => $address,
                                    'date_of_birth' => $dob,
                                    'notes' => $notes,
                                    'images' => []
                                ];
                                if ($measurementModel->createMeasurement($data)) {
                                    $updatedCount++;
                                }
                            }
                        }
                    }

                    // Delete existing records that were removed during edit
                    foreach ($existingIds as $eId) {
                        if (!in_array($eId, $keptIds)) {
                            $measurementModel->delete($eId);
                        }
                    }
                } elseif ($id > 0) {
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
                        'name' => $name,
                        'email' => $email,
                        'phone_number' => $phone,
                        'address' => $address,
                        'date_of_birth' => $dob,
                        'notes' => $notes,
                        'images' => $_POST['images'] ?? []
                    ];
                    if (!empty($_POST['cloth_type_id'])) {
                        $data['cloth_type_id'] = (int)$_POST['cloth_type_id'];
                    }

                    if ($measurementModel->updateMeasurement($id, $data)) {
                        $updatedCount++;
                    }
                }
                
                if ($updatedCount > 0) {
                    $_SESSION['success_message'] = 'Measurement(s) updated successfully!';
                } else {
                    $_SESSION['error_message'] = 'Failed to update measurements.';
                }
                break;
                
            case 'delete':
                $ids = [];
                if (!empty($_POST['measurement_ids'])) {
                    $raw = $_POST['measurement_ids'];
                    if (is_string($raw) && ($raw[0] === '[' || $raw[0] === '{')) {
                        $ids = json_decode($raw, true) ?: [];
                    } elseif (is_string($raw)) {
                        $ids = array_filter(array_map('intval', explode(',', $raw)));
                    } elseif (is_array($raw)) {
                        $ids = array_map('intval', $raw);
                    }
                }
                if (empty($ids) && !empty($_POST['measurement_id'])) {
                    $ids = [(int)$_POST['measurement_id']];
                }

                $deletedCount = 0;
                foreach ($ids as $delId) {
                    if ($delId > 0 && $measurementModel->delete($delId)) {
                        $deletedCount++;
                    }
                }

                if ($deletedCount > 0) {
                    $_SESSION['success_message'] = 'Measurement record(s) deleted successfully!';
                } else {
                    $_SESSION['error_message'] = 'Failed to delete measurement.';
                }
                break;
        }
        smart_redirect('measurements.php');
    }
}
$search = $_GET['search'] ?? '';
$customer_id = $_GET['customer_id'] ?? '';
$cloth_type_id = $_GET['cloth_type_id'] ?? '';

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
$page = (int)($_GET['page'] ?? 1);
$limit = RECORDS_PER_PAGE;
$offset = ($page - 1) * $limit;
$searchParam = !empty($search) ? trim($search) : null;
$allMeasurements = $measurementModel->getMeasurementsWithDetails($conditions, null, 0, $searchParam);
$groupedMeasurements = groupMeasurementsByCustomer($allMeasurements);
$totalMeasurements = count($groupedMeasurements);

if ($limit > 0) {
    $measurements = array_slice($groupedMeasurements, $offset, $limit);
    $totalPages = max(1, ceil($totalMeasurements / $limit));
} else {
    $measurements = $groupedMeasurements;
    $totalPages = 1;
}

$customers = $customerModel->findAll(['status' => 'active'], 'first_name, last_name');
$clothTypes = $clothTypeModel->getActiveClothTypes();
if (empty($clothTypes)) {
    $clothTypes = $clothTypeModel->findAll(['status' => 'active'], 'name ASC');
}

$totalActiveCustomers = $customerModel->count(['status' => 'active']);
$editMeasurement = null;
if (isset($_GET['edit'])) {
    $editMeasurement = $measurementModel->find((int)$_GET['edit']);
    if ($editMeasurement) {
        $editMeasurement['measurement_data'] = json_decode($editMeasurement['measurement_data'], true);
        $editMeasurement['images'] = json_decode($editMeasurement['images'], true);
    }
}

$stats = $measurementModel->getMeasurementStats();
$companyId = get_company_id();
$currentPlan = SubscriptionHelper::getCurrentPlan($companyId);
$customerLimitCheck = SubscriptionHelper::canAddCustomer($companyId);

$page_title = 'Measurements';
require_once 'includes/header.php';

$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
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
.remove-garment-btn:hover i,
.remove-garment-btn:focus i {
    color: #ffffff !important;
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
/* Measurements Main Listing Card */
.measurements-main-card {
    border-radius: 14px;
    border: 1px solid #f1f5f9 !important;
    background-color: #ffffff;
}
@media (max-width: 767.98px) {
    .measurements-main-card {
        background: transparent !important;
        background-color: transparent !important;
        border: none !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        margin-bottom: 0 !important;
    }
    .measurements-main-card > .card-body {
        background: transparent !important;
        background-color: transparent !important;
        padding: 0 !important;
    }
    #measurementMobileCardsList {
        padding: 0 !important;
    }
    #paginationContainer {
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
}
.measurement-mobile-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 14px 15px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    transition: all 0.2s ease;
}
.measurement-mobile-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}
@media (max-width: 575.98px) {
    .cat-action-btn {
        width: 32px;
        height: 32px;
        font-size: 0.78rem;
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
<?php if (isset($customerLimitCheck['remaining']) && $customerLimitCheck['remaining'] <= 5 && $customerLimitCheck['remaining'] > 0): ?>
<div class="alert alert-warning alert-dismissible fade show mb-3" style="border-radius: 12px;">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <strong>Customer Limit Warning:</strong> You have <?php echo $customerLimitCheck['remaining']; ?> customer slot(s) remaining in your <?php echo ucfirst($currentPlan); ?> plan.
    <a href="subscriptions.php" class="alert-link fw-semibold">Upgrade your plan</a> to add more customers.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php elseif (!$customerLimitCheck['allowed']): ?>
<div class="alert alert-danger alert-dismissible fade show mb-3" style="border-radius: 12px;">
    <i class="fas fa-ban me-2"></i>
    <strong>Customer Limit Reached:</strong> <?php echo $customerLimitCheck['message']; ?>
    <a href="subscriptions.php" class="alert-link fw-semibold">Upgrade your plan</a> to add more customers.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php
$pageHeaderTitle = 'Measurements';
$pageHeaderSubtitle = 'Track and manage your customer garment measurements easily.';
include 'includes/page-header.php';
?>
<div class="row g-2 mb-3">
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
        <?php if (!$customerLimitCheck['allowed']): ?>
        <button type="button" class="btn text-white fw-semibold d-inline-flex align-items-center justify-content-center gap-1 flex-grow-1 text-nowrap px-3 shadow-sm" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 14px; font-size: 0.84rem; height: 42px; border: none; opacity: 0.65;" data-bs-toggle="modal" data-bs-target="#limitUpgradeModal" title="Customer limit reached. Click to upgrade your plan.">
            <i class="fas fa-plus"></i>
            <span>Add Measurement</span>
        </button>
        <?php else: ?>
        <button type="button" class="btn text-white fw-semibold d-inline-flex align-items-center justify-content-center gap-1 flex-grow-1 text-nowrap px-3 shadow-sm" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 14px; font-size: 0.84rem; height: 42px; border: none; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.28);" data-bs-toggle="modal" data-bs-target="#addMeasurementModal">
            <i class="fas fa-plus"></i>
            <span>Add Measurement</span>
        </button>
        <?php endif; ?>
    </div>
</div>

<div id="filterResults" class="mb-3" style="display: none;">
    <div class="alert alert-info py-2 px-3 d-flex align-items-center" style="border-radius: 12px; font-size: 0.85rem;">
        <i class="fas fa-info-circle me-2"></i>
        <span><strong id="filterCount">0</strong> measurements found</span>
    </div>
</div>
<div class="card shadow-sm border-0 mb-3 measurements-main-card">
    <div class="card-body p-0">
        <?php if (!empty($measurements)): ?>
            <div class="table-responsive d-none d-md-block">
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
                            <th class="py-3" style="background-color: #f8fafc !important; color: #334155 !important; font-size: 0.78rem !important; font-weight: 700 !important; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0 !important;">NOTES</th>
                            <th class="py-3" style="background-color: #f8fafc !important; color: #334155 !important; font-size: 0.78rem !important; font-weight: 700 !important; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0 !important;">DATE</th>
                            <th class="text-end pe-3 py-3" style="background-color: #f8fafc !important; color: #334155 !important; font-size: 0.78rem !important; font-weight: 700 !important; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0 !important;">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody id="measurementsTableBody">
                        <?php foreach ($measurements as $idx => $measurement): 
                            $custKey = 'cust_' . $idx;
                            $cleanCustName = !empty($measurement['name']) ? $measurement['name'] : trim(($measurement['first_name'] ?? '') . ' ' . ($measurement['last_name'] ?? ''));
                            if (empty($cleanCustName)) $cleanCustName = 'Walk-in Customer';
                            $custDesign = getCustomerAvatarDesign($cleanCustName, $idx);
                            $initial = strtoupper(substr($cleanCustName, 0, 1) ?: 'C');
                            $cClothTypes = $measurement['cloth_types'] ?? [];
                            $cGarments = $measurement['garments'] ?? [];
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
                                <div class="d-flex flex-wrap gap-1 align-items-center">
                                    <?php if (!empty($cClothTypes)): ?>
                                        <?php foreach ($cClothTypes as $ctName): ?>
                                            <span class="badge rounded-pill px-2 py-1 fw-medium" style="background: #e0f2fe; color: #0284c7; font-size: 0.75rem;">
                                                <?php echo htmlspecialchars($ctName); ?>
                                            </span>
                                        <?php endforeach; ?>
                                        <?php if (count($cClothTypes) > 1): ?>
                                            <span class="badge rounded-pill px-2 py-1 fw-bold" style="background: #f1f5f9; color: #475569; font-size: 0.72rem;">
                                                <?php echo count($cGarments); ?> Items
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge rounded-pill px-2 py-1 fw-medium" style="background: #e0f2fe; color: #0284c7; font-size: 0.75rem;">
                                            <?php echo htmlspecialchars($measurement['cloth_type_name'] ?? 'Custom'); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
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
                                    <button type="button" class="cat-action-btn cat-action-edit" onclick="editCustomerByKey('<?php echo $custKey; ?>')" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="cat-action-btn cat-action-view" onclick="viewCustomerByKey('<?php echo $custKey; ?>')" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="cat-action-btn cat-action-del" onclick="deleteCustomerByKey('<?php echo $custKey; ?>')" title="Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Cards Listing (Visible only on screens < 768px) -->
            <div class="d-md-none d-flex flex-column gap-3 mb-3" id="measurementMobileCardsList">
                <?php foreach ($measurements as $idx => $measurement): 
                    $custKey = 'cust_' . $idx;
                    $cleanCustName = !empty($measurement['name']) ? $measurement['name'] : trim(($measurement['first_name'] ?? '') . ' ' . ($measurement['last_name'] ?? ''));
                    if (empty($cleanCustName)) $cleanCustName = 'Walk-in Customer';
                    $custDesign = getCustomerAvatarDesign($cleanCustName, $idx);
                    $initial = strtoupper(substr($cleanCustName, 0, 1) ?: 'C');
                    $cClothTypes = $measurement['cloth_types'] ?? [];
                    $cGarments = $measurement['garments'] ?? [];
                ?>
                <div class="measurement-mobile-card shadow-sm measurement-card-row" data-name="<?php echo strtolower($cleanCustName); ?>">
                    <!-- Card Header -->
                    <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <div class="d-flex align-items-center justify-content-center flex-shrink-0 fw-bold" style="width: 38px; height: 38px; border-radius: 10px; background: <?php echo $custDesign['bg']; ?>; border: 1px solid <?php echo $custDesign['border']; ?>; color: <?php echo $custDesign['color']; ?>; font-size: 0.9rem;">
                                <?php echo $initial; ?>
                            </div>
                            <div class="min-w-0">
                                <strong class="text-dark cust-name text-truncate d-block" style="font-size: 0.95rem;"><?php echo htmlspecialchars($cleanCustName); ?></strong>
                                <div class="text-muted small" style="font-size: 0.74rem;"><?php echo htmlspecialchars(!empty($measurement['phone_number']) ? $measurement['phone_number'] : ($measurement['customer_code'] ?? '')); ?></div>
                            </div>
                        </div>
                        <span class="text-muted small" style="font-size: 0.76rem;"><?php echo format_date($measurement['created_at'], 'M j, Y'); ?></span>
                    </div>

                    <!-- Cloth Types & Items -->
                    <div class="rounded-3 p-2 mb-3" style="background: #f8fafc; border: 1px solid #edf2f7; font-size: 0.8rem;">
                        <div class="text-muted mb-1" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 600;">Cloth Types & Garments</div>
                        <div class="d-flex flex-wrap gap-1 align-items-center">
                            <?php if (!empty($cClothTypes)): ?>
                                <?php foreach ($cClothTypes as $ctName): ?>
                                    <span class="badge rounded-pill px-2 py-1 fw-medium" style="background: #e0f2fe; color: #0284c7; font-size: 0.75rem;">
                                        <?php echo htmlspecialchars($ctName); ?>
                                    </span>
                                <?php endforeach; ?>
                                <?php if (count($cClothTypes) > 1): ?>
                                    <span class="badge rounded-pill px-2 py-1 fw-bold" style="background: #f1f5f9; color: #475569; font-size: 0.72rem;">
                                        <?php echo count($cGarments); ?> Items
                                    </span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge rounded-pill px-2 py-1 fw-medium" style="background: #e0f2fe; color: #0284c7; font-size: 0.75rem;">
                                    <?php echo htmlspecialchars($measurement['cloth_type_name'] ?? 'Custom'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($measurement['notes'])): ?>
                            <div class="mt-2 pt-2 border-top">
                                <div class="text-muted" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 600;">Notes</div>
                                <div class="text-dark small mt-1" style="font-size: 0.78rem;"><?php echo htmlspecialchars($measurement['notes']); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                        <span class="text-muted small" style="font-size: 0.72rem;">Actions</span>
                        <div class="d-inline-flex align-items-center gap-1">
                            <button type="button" class="cat-action-btn cat-action-edit" onclick="editCustomerByKey('<?php echo $custKey; ?>')" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="cat-action-btn cat-action-view" onclick="viewCustomerByKey('<?php echo $custKey; ?>')" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button type="button" class="cat-action-btn cat-action-del" onclick="deleteCustomerByKey('<?php echo $custKey; ?>')" title="Delete">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
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
                <?php if (!$customerLimitCheck['allowed']): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#limitUpgradeModal" style="border-radius: 12px; opacity: 0.65;">
                    <i class="fas fa-plus me-1"></i>Add Measurement
                </button>
                <?php else: ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMeasurementModal" style="border-radius: 12px;">
                    <i class="fas fa-plus me-1"></i>Add Measurement
                </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<div class="modal fade" id="limitUpgradeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-body text-center p-4">
                <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px; border-radius: 50%; background: #fee2e2; color: #ef4444;">
                    <i class="fas fa-crown fa-2x"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Customer Limit Reached</h5>
                <p class="text-muted small mb-4">
                    <?php echo htmlspecialchars($customerLimitCheck['message'] ?? 'You have reached the maximum limit of 30 customers for your free plan. Please upgrade your subscription to add more customers.'); ?>
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
                    <input type="hidden" name="existing_measurement_ids" id="existing_measurement_ids" value="">
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

                    <input type="hidden" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($editMeasurement['phone_number'] ?? ''); ?>">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_of_birth" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                                       value="<?php echo htmlspecialchars($editMeasurement['date_of_birth'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="1" placeholder="Enter customer address" style="min-height: 38px; resize: vertical;"><?php echo htmlspecialchars($editMeasurement['address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" id="customer_id" name="customer_id" value="<?php echo htmlspecialchars($editMeasurement['customer_id'] ?? ''); ?>">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-bold mb-0 text-dark" style="font-size: 0.95rem;">
                                <i class="fas fa-tshirt me-2 text-primary"></i>Cloth Types & Measurements 
                            </label>
                        </div>
                        
                        <div id="garmentsContainer" class="accordion d-flex flex-column gap-2 mb-3">
                            <!-- Dynamic Garment Blocks as Accordion Dropdowns will be inserted here -->
                        </div>
                        
                        <div id="addGarmentBtnWrapper" class="mb-3">
                            <button type="button" class="btn w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2" id="addAnotherClothTypeBtn" onclick="addGarmentBlock()">
                                <i class="fas fa-plus-circle text-primary"></i>
                                <span>Add Another Cloth Type</span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2" 
                                  placeholder="Additional notes about the measurements..."><?php echo htmlspecialchars($editMeasurement['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveMeasurementBtn">
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
                    <input type="hidden" name="measurement_ids" id="deleteMeasurementIds">
                    <button type="submit" class="btn btn-danger px-4">
                        <i class="fas fa-trash me-2"></i>Delete Measurement
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Custom Measurement Alert Modal -->
<div class="modal fade" id="measurementAlertModal" tabindex="-1" aria-labelledby="measurementAlertTitle" aria-hidden="true" style="z-index: 1090;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content border-0 shadow-lg text-center p-3 p-sm-4" style="border-radius: 20px;">
            <div class="modal-body p-0">
                <div id="measurementAlertIconContainer" class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 58px; height: 58px; background: #fef3c7; color: #d97706;">
                    <i id="measurementAlertIcon" class="fas fa-exclamation-triangle fa-2x"></i>
                </div>
                <h5 id="measurementAlertTitle" class="fw-bold mb-2 text-dark" style="font-size: 1.12rem;">Notice</h5>
                <p id="measurementAlertMessage" class="text-secondary small mb-4 px-2" style="line-height: 1.6; font-size: 0.88rem;"></p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showMeasurementAlert(message, title = 'Notice', type = 'warning', focusEl = null) {
    const modalEl = document.getElementById('measurementAlertModal');
    if (!modalEl || !window.bootstrap || !bootstrap.Modal) {
        alert(message);
        return;
    }
    const iconContainer = document.getElementById('measurementAlertIconContainer');
    const iconEl = document.getElementById('measurementAlertIcon');
    const titleEl = document.getElementById('measurementAlertTitle');
    const msgEl = document.getElementById('measurementAlertMessage');

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

    modalEl.addEventListener('shown.bs.modal', function() {
        const backdrops = document.querySelectorAll('.modal-backdrop');
        if (backdrops.length > 1) {
            backdrops[backdrops.length - 1].style.zIndex = '1085';
        }
    }, { once: true });

    if (focusEl && typeof focusEl.focus === 'function') {
        modalEl.addEventListener('hidden.bs.modal', function() {
            focusEl.focus();
        }, { once: true });
    }

    const bsModal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    bsModal.show();
}

// Global HTML escaping helper
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Global short measurement key abbreviation helper (e.g. L:32, C:34, W:33)
function formatMeasurementShortKey(key) {
    const k = String(key || '').toLowerCase().trim();
    const map = {
        'length': 'L',
        'blouse_length': 'L',
        'kurta_length': 'L',
        'shirt_length': 'L',
        'pant_length': 'L',
        'choli_length': 'L',
        'dress_length': 'L',
        'suit_length': 'L',
        'chest': 'C',
        'bust': 'B',
        'upper_chest': 'UC',
        'waist': 'W',
        'hip': 'H',
        'hips': 'H',
        'hip_seat': 'H',
        'hip/seat': 'H',
        'shoulder': 'Sh',
        'sleeve_length': 'Sl',
        'sleeve': 'Sl',
        'sleeves': 'Sl',
        'collar': 'Col',
        'neck': 'N',
        'front_neck': 'FN',
        'front_neck_depth': 'FN',
        'back_neck': 'BN',
        'back_neck_depth': 'BN',
        'armhole': 'Arm',
        'bicep': 'Bic',
        'wrist': 'Wr',
        'thigh': 'Th',
        'inseam': 'In',
        'outseam': 'Out',
        'bottom': 'Bot',
        'knee': 'Kn',
        'cross_back': 'CB',
        'apex': 'Ap'
    };
    if (map[k]) return map[k];
    const words = k.split(/[\s_\-]+/);
    if (words.length > 1) {
        return words.map(w => w ? w[0].toUpperCase() : '').join('');
    }
    return k.charAt(0).toUpperCase();
}

// Global map of grouped customer measurements (eliminates JSON in HTML attributes)
const customerMeasurementsMap = <?php 
    $cMap = [];
    if (!empty($measurements)) {
        foreach ($measurements as $i => $m) {
            $cMap['cust_' . $i] = $m;
        }
    }
    echo json_encode($cMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '{}';
?>;

function editCustomerByKey(key) {
    const group = customerMeasurementsMap[key];
    if (group) {
        editCustomerMeasurements(group);
    } else {
        console.error('Customer data not found for key:', key);
    }
}

function viewCustomerByKey(key) {
    const group = customerMeasurementsMap[key];
    if (group) {
        viewCustomerMeasurements(group);
    } else {
        console.error('Customer data not found for key:', key);
    }
}

function deleteCustomerByKey(key) {
    const group = customerMeasurementsMap[key];
    if (group) {
        deleteCustomerMeasurements(group.ids, group.name);
    } else {
        console.error('Customer data not found for key:', key);
    }
}

// Available cloth types data from PHP
const availableClothTypes = <?php echo json_encode(array_values(array_map(function($ct) {
    return [
        'id' => (int)$ct['id'],
        'name' => $ct['name'],
        'category' => $ct['category'] ?? '',
        'chart' => $ct['measurement_chart_image'] ? '../' . ltrim($ct['measurement_chart_image'], './') : ''
    ];
}, $clothTypes))); ?>;

// Default standard measurement fields templates based on cloth type name
function getDefaultFieldsForClothType(clothTypeName) {
    const name = (clothTypeName || '').toLowerCase();
    
    // Suit / Blazer / Coat
    if (name.includes('suit') || name.includes('blazer') || name.includes('coat')) {
        return [
            { key: 'length', label: 'Suit Length', type: 'text' },
            { key: 'chest', label: 'Chest', type: 'text' },
            { key: 'waist', label: 'Waist', type: 'text' },
            { key: 'hip', label: 'Hip', type: 'text' },
            { key: 'shoulder', label: 'Shoulder', type: 'text' },
            { key: 'sleeve_length', label: 'Sleeve Length', type: 'text' },
            { key: 'collar', label: 'Collar / Neck', type: 'text' },
            { key: 'cross_back', label: 'Cross Back', type: 'text' }
        ];
    }
    
    // Pants / Pent / Trousers / Salwar / Pajama
    if (name.includes('pant') || name.includes('pent') || name.includes('trouser') || name.includes('pajama') || name.includes('salwar') || name.includes('bottom')) {
        return [
            { key: 'length', label: 'Length', type: 'text' },
            { key: 'waist', label: 'Waist', type: 'text' },
            { key: 'hip/seat', label: 'Hip / Seat', type: 'text' },
            { key: 'thigh', label: 'Thigh', type: 'text' },
            { key: 'knee', label: 'Knee', type: 'text' },
            { key: 'bottom', label: 'Bottom / Mori', type: 'text' }
        ];
    }
    
    // Shirt
    if (name.includes('shirt')) {
        return [
            { key: 'collar', label: 'Collar', type: 'text' },
            { key: 'shoulder', label: 'Shoulder', type: 'text' },
            { key: 'chest', label: 'Chest', type: 'text' },
            { key: 'waist', label: 'Waist', type: 'text' },
            { key: 'sleeve_type', label: 'Sleeve Type', type: 'select', options: [{val: 'half', text: 'Half Sleeve'}, {val: 'full', text: 'Full Sleeve'}] },
            { key: 'sleeve_length', label: 'Sleeve Length', type: 'text' },
            { key: 'length', label: 'Length', type: 'text' },
            { key: 'pocket', label: 'Pocket', type: 'select', options: [{val: 'yes', text: 'Yes'}, {val: 'no', text: 'No'}] },
            { key: 'button_color', label: 'Button Color', type: 'color' }
        ];
    }
    
    // Kurta / Kameez / Sherwani
    if (name.includes('kurta') || name.includes('kameez') || name.includes('sherwani') || name.includes('servani')) {
        return [
            { key: 'length', label: 'Length', type: 'text' },
            { key: 'chest', label: 'Chest', type: 'text' },
            { key: 'waist', label: 'Waist', type: 'text' },
            { key: 'shoulder', label: 'Shoulder', type: 'text' },
            { key: 'sleeve_length', label: 'Sleeve Length', type: 'text' },
            { key: 'collar', label: 'Collar / Neck', type: 'text' },
            { key: 'hip', label: 'Hip / Seat', type: 'text' }
        ];
    }
    
    // Saree / Blouse / Choli
    if (name.includes('saree') || name.includes('sari') || name.includes('blouse') || name.includes('choli')) {
        return [
            { key: 'blouse_length', label: 'Blouse Length', type: 'text' },
            { key: 'chest', label: 'Chest', type: 'text' },
            { key: 'waist', label: 'Waist', type: 'text' },
            { key: 'shoulder', label: 'Shoulder', type: 'text' },
            { key: 'sleeve_length', label: 'Sleeve Length', type: 'text' },
            { key: 'armhole', label: 'Armhole', type: 'text' },
            { key: 'front_neck_depth', label: 'Front Neck Depth', type: 'text' },
            { key: 'back_neck_depth', label: 'Back Neck Depth', type: 'text' }
        ];
    }
    
    // Dress / Gown / Frock
    if (name.includes('dress') || name.includes('gown') || name.includes('frock')) {
        return [
            { key: 'length', label: 'Length', type: 'text' },
            { key: 'shoulder', label: 'Shoulder', type: 'text' },
            { key: 'chest', label: 'Chest', type: 'text' },
            { key: 'waist', label: 'Waist', type: 'text' },
            { key: 'hips', label: 'Hips', type: 'text' },
            { key: 'sleeve_length', label: 'Sleeve Length', type: 'text' },
            { key: 'armhole', label: 'Armhole', type: 'text' },
            { key: 'front_neck_depth', label: 'Front Neck Depth', type: 'text' },
            { key: 'back_neck_depth', label: 'Back Neck Depth', type: 'text' }
        ];
    }
    
    // Default fallback
    return [
        { key: 'length', label: 'Length', type: 'text' },
        { key: 'chest', label: 'Chest', type: 'text' },
        { key: 'waist', label: 'Waist', type: 'text' }
    ];
}

let garmentBlockCounter = 0;

// Add a garment block as an Accordion Dropdown (default open)
function addGarmentBlock(presetClothTypeId = '', presetData = {}, measurementId = '', isOpen = true) {
    garmentBlockCounter++;
    const currentId = garmentBlockCounter;
    const container = document.getElementById('garmentsContainer');
    if (!container) return;
    
    // Check if this is the first block
    const existingBlocks = container.querySelectorAll('.garment-block');
    const isFirst = (existingBlocks.length === 0);
    
    // If the new block is set to be open, collapse any currently open blocks so the new one has focus
    if (isOpen && !isFirst) {
        existingBlocks.forEach(b => {
            const collapseEl = b.querySelector('.accordion-collapse');
            const btnEl = b.querySelector('.accordion-button');
            if (collapseEl && collapseEl.classList.contains('show')) {
                collapseEl.classList.remove('show');
            }
            if (btnEl && !btnEl.classList.contains('collapsed')) {
                btnEl.classList.add('collapsed');
                btnEl.setAttribute('aria-expanded', 'false');
            }
        });
    }

    const item = document.createElement('div');
    item.className = 'accordion-item garment-block border shadow-sm mb-2';
    item.id = `garment-card-${currentId}`;
    item.dataset.garmentId = currentId;
    item.style.borderRadius = '12px';
    item.style.overflow = 'hidden';
    item.style.borderColor = '#e2e8f0';

    let optionsHtml = '<option value="">Select Cloth Type</option>';
    availableClothTypes.forEach(ct => {
        const isSelected = (presetClothTypeId && String(presetClothTypeId) === String(ct.id)) ? 'selected' : '';
        optionsHtml += `<option value="${ct.id}" data-name="${ct.name}" data-chart="${ct.chart}" ${isSelected}>${ct.name}${ct.category ? ' (' + ct.category + ')' : ''}</option>`;
    });

    item.innerHTML = `
        <h2 class="accordion-header" id="heading-${currentId}">
            <div class="d-flex align-items-center justify-content-between bg-light p-1 pe-2 border-bottom">
                <button class="accordion-button py-2 px-3 bg-transparent border-0 shadow-none text-dark fw-bold d-flex align-items-center gap-2 ${isOpen ? '' : 'collapsed'}" 
                        type="button" 
                        data-bs-toggle="collapse" 
                        data-bs-target="#collapse-${currentId}" 
                        aria-expanded="${isOpen ? 'true' : 'false'}" 
                        aria-controls="collapse-${currentId}"
                        style="flex: 1; text-align: left; font-size: 0.92rem;">
                    <span class="badge bg-primary rounded-pill garment-index-badge">#1</span>
                    <span class="garment-title text-dark">Select Cloth Type</span>
                    <span class="badge bg-white text-muted border garment-summary-badge small ms-2" style="font-size: 0.75rem; font-weight: normal;">
                        No measurements
                    </span>
                </button>
                <div class="d-flex align-items-center">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-garment-btn p-1 px-2 border-0" onclick="removeGarmentBlock(${currentId})" title="Remove this garment" style="border-radius: 8px; font-size: 0.95rem;">
                        <i class="fas fa-trash-alt text-danger"></i>
                    </button>
                </div>
            </div>
        </h2>
        <div id="collapse-${currentId}" class="accordion-collapse collapse ${isOpen ? 'show' : ''}" aria-labelledby="heading-${currentId}" data-bs-parent="#garmentsContainer">
            <div class="accordion-body p-3 bg-white">
                <input type="hidden" class="garment-id-input" name="garments[${currentId}][measurement_id]" value="${measurementId || ''}">
                <div class="row align-items-end g-2 mb-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold text-dark small mb-1">
                            Cloth Type * 
                        </label>
                        <select class="form-select form-select-sm garment-cloth-type-select" name="garments[${currentId}][cloth_type_id]" required onchange="handleGarmentClothTypeChange(${currentId}, this.value)" style="border-radius: 8px; font-size: 0.88rem; ">
                            ${optionsHtml}
                        </select>
                    </div>
                </div>

                <div class="garment-chart-box mb-3" id="garment-chart-box-${currentId}" style="display: none;">
                    <div class="card border-primary bg-light" style="border-radius: 10px; overflow: hidden; border: 1px solid #c7d2fe !important;">
                        <div class="card-header bg-primary bg-opacity-10 py-1 px-3 d-flex justify-content-between align-items-center" style="font-size: 0.78rem;">
                            <span class="fw-semibold text-light"><i class="fas fa-ruler-combined me-1"></i>Measurement Guide Image</span>
                        </div>
                        <div class="card-body text-center p-2">
                            <img src="" alt="Guide" class="img-fluid garment-chart-img" style="max-height: 220px; border-radius: 8px;">
                        </div>
                    </div>
                </div>

                <div class="mb-1">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label fw-semibold text-dark small mb-0">
                            <i class="fas fa-tape me-1 text-primary"></i>Measurements 
                        </label>
                        <button type="button" class="btn btn-xs btn-outline-primary" onclick="addCustomFieldToGarment(${currentId})" style="border-radius: 8px; font-size: 0.76rem; padding: 2px 8px;">
                            <i class="fas fa-plus me-1"></i>Add Custom Field
                        </button>
                    </div>
                    <div class="garment-fields-list row g-2" id="garment-fields-${currentId}">
                        <!-- Inputs loaded here in 2-column grid -->
                    </div>
                </div>
            </div>
        </div>
    `;

    container.appendChild(item);

    if (presetClothTypeId) {
        handleGarmentClothTypeChange(currentId, presetClothTypeId, presetData);
    } else {
        renderGarmentDefaultFields(currentId);
    }

    updateGarmentNumbering();

    if (isOpen) {
        setTimeout(() => {
            item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            const select = item.querySelector('.garment-cloth-type-select');
            if (select) select.focus();
        }, 80);
    }
}

// Update garment measurement count in the accordion badge
function updateGarmentMeasurementCount(garmentId) {
    const card = document.getElementById(`garment-card-${garmentId}`);
    if (!card) return;
    const badge = card.querySelector('.garment-summary-badge');
    if (!badge) return;
    
    const inputs = card.querySelectorAll('input[name*="[values]"], select[name*="[values]"]');
    let filledCount = 0;
    inputs.forEach(inp => {
        if (inp.value && inp.value.trim() !== '') {
            filledCount++;
        }
    });
    
    if (filledCount > 0) {
        badge.textContent = `${filledCount} ${filledCount === 1 ? 'measurement' : 'measurements'}`;
        badge.className = 'badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 small ms-2';
    } else {
        badge.textContent = 'No measurements';
        badge.className = 'badge bg-white text-muted border garment-summary-badge small ms-2';
    }
}

// Update garment index badges and remove button visibility
function updateGarmentNumbering() {
    const blocks = document.querySelectorAll('#garmentsContainer .garment-block');
    const total = blocks.length;
    blocks.forEach((block, index) => {
        const badge = block.querySelector('.garment-index-badge');
        if (badge) {
            badge.textContent = `#${index + 1}`;
        }
        const select = block.querySelector('.garment-cloth-type-select');
        const title = block.querySelector('.garment-title');
        if (title && select) {
            const selectedText = select.options[select.selectedIndex]?.text;
            if (select.value && selectedText && !selectedText.startsWith('Select Cloth Type')) {
                title.textContent = selectedText.split(' (')[0];
            } else {
                title.textContent = `Garment #${index + 1}`;
            }
        }
        const removeBtn = block.querySelector('.remove-garment-btn');
        if (removeBtn) {
            if (total <= 1) {
                removeBtn.style.display = 'none';
            } else {
                removeBtn.style.display = 'inline-block';
            }
        }
    });
}

// Remove a garment block
function removeGarmentBlock(id) {
    const blocks = document.querySelectorAll('#garmentsContainer .garment-block');
    if (blocks.length <= 1) {
        showMeasurementAlert('At least one garment is required.', 'Garment Required', 'warning');
        return;
    }
    const card = document.getElementById(`garment-card-${id}`);
    if (card) {
        card.remove();
        updateGarmentNumbering();
        // Make sure at least one block is expanded
        const remaining = document.querySelectorAll('#garmentsContainer .garment-block');
        const anyOpen = Array.from(remaining).some(b => b.querySelector('.accordion-collapse.show'));
        if (!anyOpen && remaining.length > 0) {
            const firstCollapse = remaining[0].querySelector('.accordion-collapse');
            const firstBtn = remaining[0].querySelector('.accordion-button');
            if (firstCollapse) firstCollapse.classList.add('show');
            if (firstBtn) {
                firstBtn.classList.remove('collapsed');
                firstBtn.setAttribute('aria-expanded', 'true');
            }
        }
    }
}

// Toggle chart preview collapse
function toggleGarmentChart(id) {
    const box = document.getElementById(`garment-chart-box-${id}`);
    const btn = document.getElementById(`guide-btn-${id}`);
    const btnText = btn ? btn.querySelector('.guide-btn-text') : null;
    if (box) {
        if (box.style.display === 'none' || !box.style.display) {
            box.style.display = 'block';
            if (btnText) btnText.textContent = 'Hide Guide';
        } else {
            box.style.display = 'none';
            if (btnText) btnText.textContent = 'View Guide';
        }
    }
}

// Handle cloth type change for a garment
function handleGarmentClothTypeChange(id, clothTypeId, customValues = null) {
    const card = document.getElementById(`garment-card-${id}`);
    if (!card) return;

    const clothType = availableClothTypes.find(ct => String(ct.id) === String(clothTypeId));
    
    // Update title in accordion header
    const title = card.querySelector('.garment-title');
    if (title) {
        title.textContent = clothType ? clothType.name : 'Select Cloth Type';
    }

    // Guide chart image preview (shown by default if available)
    const chartBox = document.getElementById(`garment-chart-box-${id}`);
    const chartImg = chartBox ? chartBox.querySelector('.garment-chart-img') : null;
    
    if (clothType && clothType.chart && clothType.chart.trim() !== '') {
        if (chartImg) chartImg.src = clothType.chart;
        if (chartBox) chartBox.style.display = 'block';
    } else {
        if (chartBox) chartBox.style.display = 'none';
        if (chartImg) chartImg.src = '';
    }

    // Render fields into the row container
    const fieldsContainer = document.getElementById(`garment-fields-${id}`);
    if (!fieldsContainer) return;
    fieldsContainer.innerHTML = '';

    if (!clothType) {
        renderGarmentDefaultFields(id);
        updateGarmentMeasurementCount(id);
        return;
    }

    const fieldTemplates = getDefaultFieldsForClothType(clothType.name);

    if (customValues && typeof customValues === 'object' && Object.keys(customValues).length > 0) {
        const renderedKeys = new Set();
        fieldTemplates.forEach(field => {
            const val = customValues[field.key] !== undefined ? customValues[field.key] : '';
            renderSingleFieldRow(fieldsContainer, id, field.key, val, field.type || 'text', field.options || null, true);
            renderedKeys.add(field.key);
        });
        Object.entries(customValues).forEach(([k, v]) => {
            if (!renderedKeys.has(k)) {
                let fType = 'text';
                if (k === 'button_color') fType = 'color';
                renderSingleFieldRow(fieldsContainer, id, k, v, fType, null, false);
            }
        });
    } else {
        fieldTemplates.forEach(field => {
            renderSingleFieldRow(fieldsContainer, id, field.key, '', field.type || 'text', field.options || null, true);
        });
    }

    updateGarmentNumbering();
    updateGarmentMeasurementCount(id);
}

// Render a single field row inside a garment block (2-column compact input-group)
function renderSingleFieldRow(container, garmentId, key = '', value = '', fieldType = 'text', options = null, isStandard = false) {
    const col = document.createElement('div');
    col.className = 'col-md-6 col-12 measurement-field-col';
    
    let inputHtml = '';
    if (fieldType === 'color') {
        inputHtml = `<input type="color" class="form-control form-control-color form-control-sm" name="garments[${garmentId}][values][]" value="${value || '#000000'}" style="width: 48px; height: 34px; padding: 2px;" onchange="updateGarmentMeasurementCount(${garmentId})">`;
    } else if (fieldType === 'select') {
        const opts = options || (key === 'sleeve_type' ? 
            [{val: 'half', text: 'Half Sleeve'}, {val: 'full', text: 'Full Sleeve'}] :
            [{val: 'yes', text: 'Yes'}, {val: 'no', text: 'No'}]);
        
        let optsHtml = '<option value="">Select...</option>';
        opts.forEach(opt => {
            const sel = (String(value).toLowerCase() === String(opt.val).toLowerCase()) ? 'selected' : '';
            optsHtml += `<option value="${opt.val}" ${sel}>${opt.text}</option>`;
        });
        inputHtml = `<select class="form-select form-select-sm" name="garments[${garmentId}][values][]" style="border-radius: 0;" onchange="updateGarmentMeasurementCount(${garmentId})">
            ${optsHtml}
        </select>`;
    } else {
        inputHtml = `<input type="text" class="form-control form-control-sm" name="garments[${garmentId}][values][]" 
                     placeholder="e.g. 42" value="${value || ''}" style="border-radius: 0;" oninput="updateGarmentMeasurementCount(${garmentId})">`;
    }

    const labelText = key ? key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) : '';

    if (isStandard) {
        col.innerHTML = `
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light text-dark fw-medium text-truncate" style="min-width: 105px; max-width: 130px; font-size: 0.82rem; border-top-left-radius: 8px; border-bottom-left-radius: 8px;" title="${labelText}">
                    ${labelText}
                    <input type="hidden" class="measurement-key-input" name="garments[${garmentId}][keys][]" value="${key}">
                </span>
                ${inputHtml}
                <button type="button" class="btn btn-outline-secondary border px-2" onclick="this.closest('.measurement-field-col').remove(); updateGarmentMeasurementCount(${garmentId});" title="Remove field" style="border-top-right-radius: 8px; border-bottom-right-radius: 8px;">
                    <i class="fas fa-times text-muted"></i>
                </button>
            </div>
        `;
    } else {
        col.innerHTML = `
            <div class="input-group input-group-sm">
                <input type="text" class="form-control form-control-sm measurement-key-input" name="garments[${garmentId}][keys][]" 
                       placeholder="Field Name" value="${key}" style="max-width: 110px; font-size: 0.82rem; border-top-left-radius: 8px; border-bottom-left-radius: 8px;">
                ${inputHtml}
                <button type="button" class="btn btn-outline-danger border px-2" onclick="this.closest('.measurement-field-col').remove(); updateGarmentMeasurementCount(${garmentId});" title="Remove field" style="border-top-right-radius: 8px; border-bottom-right-radius: 8px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
    }
    container.appendChild(col);
}

// Add custom field to a garment
function addCustomFieldToGarment(garmentId) {
    const container = document.getElementById(`garment-fields-${garmentId}`);
    if (container) {
        renderSingleFieldRow(container, garmentId, '', '', 'text', null, false);
    }
}

// Render default 3 fields when no cloth type selected yet
function renderGarmentDefaultFields(garmentId) {
    const container = document.getElementById(`garment-fields-${garmentId}`);
    if (!container) return;
    renderSingleFieldRow(container, garmentId, 'chest', '', 'text', null, true);
    renderSingleFieldRow(container, garmentId, 'waist', '', 'text', null, true);
    renderSingleFieldRow(container, garmentId, 'length', '', 'text', null, true);
}

let isMeasurementSubmitting = false;

// Form validation before submission
function debugFormSubmission(event) {
    if (isMeasurementSubmitting) {
        if (event) event.preventDefault();
        return false;
    }

    const name = document.getElementById('name').value.trim();
    if (!name) {
        showMeasurementAlert('Please enter customer name.', 'Customer Name Required', 'warning', document.getElementById('name'));
        if (event) event.preventDefault();
        return false;
    }

    const garmentBlocks = document.querySelectorAll('#garmentsContainer .garment-block');
    if (garmentBlocks.length === 0) {
        showMeasurementAlert('Please add at least one cloth type.', 'Cloth Type Required', 'warning');
        if (event) event.preventDefault();
        return false;
    }

    for (let i = 0; i < garmentBlocks.length; i++) {
        const block = garmentBlocks[i];
        const select = block.querySelector('.garment-cloth-type-select');
        if (!select || !select.value) {
            // Expand this block so the user sees it immediately
            const collapseEl = block.querySelector('.accordion-collapse');
            const btnEl = block.querySelector('.accordion-button');
            if (collapseEl && !collapseEl.classList.contains('show')) {
                collapseEl.classList.add('show');
            }
            if (btnEl && btnEl.classList.contains('collapsed')) {
                btnEl.classList.remove('collapsed');
                btnEl.setAttribute('aria-expanded', 'true');
            }
            showMeasurementAlert(`Please select a Cloth Type for Garment #${i + 1}.`, 'Cloth Type Required', 'warning', select || null);
            if (event) event.preventDefault();
            return false;
        }

        const keys = block.querySelectorAll('input.measurement-key-input');
        const vals = block.querySelectorAll('input[name*="[values]"], select[name*="[values]"]');
        let blockHasMeas = false;
        for (let j = 0; j < keys.length; j++) {
            if (keys[j].value.trim() && vals[j].value.trim()) {
                blockHasMeas = true;
                break;
            }
        }
        if (!blockHasMeas) {
            // Expand this block so the user sees it immediately
            const collapseEl = block.querySelector('.accordion-collapse');
            const btnEl = block.querySelector('.accordion-button');
            if (collapseEl && !collapseEl.classList.contains('show')) {
                collapseEl.classList.add('show');
            }
            if (btnEl && btnEl.classList.contains('collapsed')) {
                btnEl.classList.remove('collapsed');
                btnEl.setAttribute('aria-expanded', 'true');
            }
            const garmentName = select?.options[select.selectedIndex]?.text?.split(' (')[0] || `Garment #${i + 1}`;
            showMeasurementAlert(`Please enter at least one measurement value for ${garmentName}.`, 'Measurement Required', 'warning', vals[0] || null);
            if (event) event.preventDefault();
            return false;
        }
    }

    isMeasurementSubmitting = true;
    const saveBtn = document.getElementById('saveMeasurementBtn');
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Saving...';
    }

    return true;
}

// Edit customer measurements from table or object
function editCustomerMeasurements(group) {
    if (!group) return;
    
    // Fallback if raw single measurement object is passed
    if (!group.garments) {
        let mData = group.measurement_data;
        if (typeof mData === 'string') {
            try { mData = JSON.parse(mData); } catch (e) { mData = {}; }
        }
        group = {
            primary_id: group.id,
            ids: [group.id],
            customer_id: group.customer_id || '',
            name: group.name || ((group.first_name || '') + ' ' + (group.last_name || '')).trim(),
            email: group.email || '',
            phone_number: group.phone_number || '',
            address: group.address || '',
            date_of_birth: group.date_of_birth || '',
            notes: group.notes || '',
            cloth_types: [group.cloth_type_name || 'Cloth'],
            garments: [{
                measurement_id: group.id,
                cloth_type_id: group.cloth_type_id,
                cloth_type_name: group.cloth_type_name,
                measurement_data: mData
            }]
        };
    }
    
    const cleanName = group.name || 'Customer';
    const modalTitle = document.querySelector('#addMeasurementModal .modal-title');
    if (modalTitle) modalTitle.textContent = 'Edit Measurements - ' + cleanName;
    
    const actionInput = document.querySelector('#addMeasurementModal form input[name="action"]');
    if (actionInput) actionInput.value = 'update';
    
    let measurementIdField = document.querySelector('input[name="measurement_id"]');
    if (!measurementIdField) {
        measurementIdField = document.createElement('input');
        measurementIdField.type = 'hidden';
        measurementIdField.name = 'measurement_id';
        document.querySelector('#addMeasurementModal form').appendChild(measurementIdField);
    }
    measurementIdField.value = group.primary_id || (group.ids && group.ids[0]) || '';
    
    // Store existing measurement IDs
    let existingIdsField = document.getElementById('existing_measurement_ids');
    if (!existingIdsField) {
        existingIdsField = document.createElement('input');
        existingIdsField.type = 'hidden';
        existingIdsField.id = 'existing_measurement_ids';
        existingIdsField.name = 'existing_measurement_ids';
        document.querySelector('#addMeasurementModal form').appendChild(existingIdsField);
    }
    existingIdsField.value = JSON.stringify(group.ids || [group.primary_id]);
    
    if (document.getElementById('customer_id')) document.getElementById('customer_id').value = group.customer_id || '';
    if (document.getElementById('name')) document.getElementById('name').value = group.name || '';
    if (document.getElementById('email')) document.getElementById('email').value = group.email || '';
    if (document.getElementById('phone_number')) document.getElementById('phone_number').value = group.phone_number || '';
    if (document.getElementById('address')) document.getElementById('address').value = group.address || '';
    if (document.getElementById('date_of_birth')) document.getElementById('date_of_birth').value = group.date_of_birth || '';
    if (document.getElementById('notes')) document.getElementById('notes').value = group.notes || '';
    
    const container = document.getElementById('garmentsContainer');
    if (container) container.innerHTML = '';
    
    if (group.garments && group.garments.length > 0) {
        group.garments.forEach((garment, idx) => {
            let mData = garment.measurement_data;
            if (typeof mData === 'string') {
                try { mData = JSON.parse(mData); } catch (e) { mData = {}; }
            }
            addGarmentBlock(garment.cloth_type_id, mData, garment.measurement_id, idx === 0);
        });
    } else {
        addGarmentBlock();
    }
    
    const addBtn = document.getElementById('addGarmentBtnWrapper');
    if (addBtn) addBtn.style.display = 'block';

    const submitBtn = document.getElementById('saveMeasurementBtn');
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Update Measurements';
    }
    isMeasurementSubmitting = false;
    
    const modalEl = document.getElementById('addMeasurementModal');
    if (modalEl) {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else if (typeof $ !== 'undefined' && $.fn.modal) {
            $('#addMeasurementModal').modal('show');
        }
    }
}

// Alias for backwards compatibility
function editMeasurement(measurement) {
    editCustomerMeasurements(measurement);
}

// Edit measurement from filtered results
function editMeasurementFromFilter(id) {
    fetch('ajax/get_measurement_details.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.measurement) {
                editCustomerMeasurements(data.measurement);
            } else {
                showMeasurementAlert('Failed to load measurement data', 'Load Error', 'danger');
            }
        })
        .catch(error => {
            console.error('Error loading measurement:', error);
            showMeasurementAlert('Error loading measurement data', 'Load Error', 'danger');
        });
}

// Reset modal when closed
document.getElementById('addMeasurementModal').addEventListener('hidden.bs.modal', function() {
    const modalTitle = document.querySelector('#addMeasurementModal .modal-title');
    if (modalTitle) modalTitle.textContent = 'Add New Measurement';
    
    const actionInput = document.querySelector('#addMeasurementModal form input[name="action"]');
    if (actionInput) actionInput.value = 'add';
    
    const measurementIdField = document.querySelector('input[name="measurement_id"]');
    if (measurementIdField) {
        measurementIdField.remove();
    }
    const existingIdsField = document.getElementById('existing_measurement_ids');
    if (existingIdsField) {
        existingIdsField.value = '';
    }
    
    document.getElementById('measurementForm').reset();
    
    const container = document.getElementById('garmentsContainer');
    if (container) container.innerHTML = '';
    addGarmentBlock();
    
    const addBtn = document.getElementById('addGarmentBtnWrapper');
    if (addBtn) addBtn.style.display = 'block';

    const submitBtn = document.getElementById('saveMeasurementBtn');
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Add Measurement';
    }
    isMeasurementSubmitting = false;
});

// Initialise garment blocks on page load
window.addEventListener('DOMContentLoaded', function() {
    <?php if ($editMeasurement): ?>
        addGarmentBlock(<?php echo (int)$editMeasurement['cloth_type_id']; ?>, <?php echo json_encode($editMeasurement['measurement_data'] ?? []); ?>);
        const addBtn = document.getElementById('addGarmentBtnWrapper');
        if (addBtn) addBtn.style.display = 'none';
    <?php else: ?>
        const container = document.getElementById('garmentsContainer');
        if (container && container.children.length === 0) {
            addGarmentBlock();
        }
    <?php endif; ?>
});

// Fetch customer details on selection
document.getElementById('customer_id')?.addEventListener('change', function() {
    const customerId = this.value;
    if (customerId) {
        fetch('ajax/get_customer_details.php?customer_id=' + customerId)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.customer) {
                    document.getElementById('name').value = data.customer.name || '';
                    document.getElementById('email').value = data.customer.email || '';
                    if (document.getElementById('phone_number')) document.getElementById('phone_number').value = data.customer.phone || '';
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
        if (document.getElementById('phone_number')) document.getElementById('phone_number').value = '';
        document.getElementById('address').value = '';
        document.getElementById('date_of_birth').value = '';
    }
});

function viewCustomerMeasurements(group, targetClothName = '') {
    if (!group) return;
    
    // Fallback if passed ID or single record without garments
    if (typeof group === 'number' || typeof group === 'string') {
        return viewMeasurement(group);
    }
    if (!group.garments) {
        return displayMeasurementDetails(group);
    }
    
    const modalEl = document.getElementById('viewMeasurementModal');
    const contentDiv = document.getElementById('viewMeasurementContent');
    if (!modalEl || !contentDiv) return;
    
    let cleanCustName = group.name || 'Customer';
    let cleanPhone = group.phone_number || '';
    let cleanEmail = group.email || '';
    let cleanAddress = group.address || '';
    let cleanDob = group.date_of_birth ? new Date(group.date_of_birth).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : '';
    let dateStr = group.created_at ? new Date(group.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : '';

    let garmentsHtml = '';
    const garments = group.garments || [];
    
    if (garments.length === 0) {
        garmentsHtml = '<p class="text-muted text-center py-3">No garment measurements recorded.</p>';
    } else {
        garmentsHtml = '<div class="accordion d-flex flex-column gap-2 mb-3" id="viewGarmentsAccordion">';
        garments.forEach((garment, gIdx) => {
            let measurementData = garment.measurement_data || {};
            if (typeof measurementData === 'string') {
                try { measurementData = JSON.parse(measurementData); } catch(e) { measurementData = {}; }
            }
            if (typeof measurementData !== 'object' || measurementData === null) {
                measurementData = {};
            }
            
            let fieldsHtml = '';
            const keys = Object.keys(measurementData);
            if (keys.length > 0) {
                keys.forEach(k => {
                    const label = String(k).replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    const val = measurementData[k];
                    fieldsHtml += `
                        <div class="col-sm-4 col-6 mb-2">
                            <div class="p-2 border rounded bg-light text-center h-100">
                                <small class="text-muted d-block text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.3px;">${escapeHtml(label)}</small>
                                <strong class="text-primary fs-6">${escapeHtml(val)}</strong>
                            </div>
                        </div>
                    `;
                });
            } else {
                fieldsHtml = '<div class="col-12 text-muted small p-2">No measurement fields recorded</div>';
            }
            
            let chartHtml = '';
            if (garment.measurement_chart) {
                chartHtml = `
                    <div class="col-md-5 text-center mt-3 mt-md-0">
                        <div class="border rounded p-2 bg-light">
                            <small class="text-muted d-block mb-1 fw-bold" style="font-size: 0.74rem;">Measurement Guide</small>
                            <img src="${escapeHtml(garment.measurement_chart)}" class="img-fluid rounded" style="max-height: 220px;" alt="Guide">
                        </div>
                    </div>
                `;
            }
            
            const isItemOpen = targetClothName
                ? (String(garment.cloth_type_name || '').toLowerCase().trim() === String(targetClothName).toLowerCase().trim())
                : (gIdx === 0);
            
            garmentsHtml += `
                <div class="accordion-item border shadow-sm" style="border-radius: 12px; overflow: hidden; border-color: #e2e8f0 !important;">
                    <h2 class="accordion-header" id="view-garment-heading-${gIdx}">
                        <button class="accordion-button py-2 px-3 bg-light text-dark fw-bold d-flex align-items-center gap-2 ${isItemOpen ? '' : 'collapsed'}" 
                                type="button" 
                                data-bs-toggle="collapse" 
                                data-bs-target="#view-garment-collapse-${gIdx}" 
                                aria-expanded="${isItemOpen ? 'true' : 'false'}" 
                                aria-controls="view-garment-collapse-${gIdx}"
                                style="font-size: 0.92rem; box-shadow: none;">
                            <span class="badge bg-primary rounded-pill me-1">#${gIdx + 1}</span>
                            <span class="text-dark fw-bold">${escapeHtml(garment.cloth_type_name || 'Cloth')}</span>
                            ${garment.cloth_category ? `<span class="badge bg-light text-primary border small ms-1" style="font-size: 0.70rem;">${escapeHtml(garment.cloth_category)}</span>` : ''}
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 small ms-auto me-2">
                                ${keys.length} ${keys.length === 1 ? 'Field' : 'Fields'}
                            </span>
                        </button>
                    </h2>
                    <div id="view-garment-collapse-${gIdx}" class="accordion-collapse collapse ${isItemOpen ? 'show' : ''}" aria-labelledby="view-garment-heading-${gIdx}" data-bs-parent="#viewGarmentsAccordion">
                        <div class="accordion-body p-3 bg-white">
                            <div class="row align-items-start">
                                <div class="${garment.measurement_chart ? 'col-md-7' : 'col-12'}">
                                    <div class="row g-2">
                                        ${fieldsHtml}
                                    </div>
                                </div>
                                ${chartHtml}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        garmentsHtml += '</div>';
    }

    contentDiv.innerHTML = `
        <div class="card mb-3 border shadow-none" style="border-radius: 12px; border-color: #e2e8f0 !important; overflow: hidden;">
            <div class="py-2 px-3" style="background: #f8fafc !important; border-bottom: 1px solid #e2e8f0 !important;">
                <h6 class="mb-0 fw-bold" style="color: #1e293b !important; font-size: 0.92rem;"><i class="fas fa-user me-2 text-primary"></i>Customer Information</h6>
            </div>
            <div class="card-body p-3">
                <div class="row g-3">
                    <div class="col-sm-4 col-6">
                        <small class="text-muted d-block">Customer Name</small>
                        <strong class="text-dark">${escapeHtml(cleanCustName)}</strong>
                    </div>
                    ${cleanPhone ? `
                    <div class="col-sm-4 col-6">
                        <small class="text-muted d-block">Phone Number</small>
                        <span>${escapeHtml(cleanPhone)}</span>
                    </div>
                    ` : ''}
                    <div class="col-sm-4 col-6">
                        <small class="text-muted d-block">Email</small>
                        <span>${escapeHtml(cleanEmail) || '—'}</span>
                    </div>
                    ${cleanDob ? `
                    <div class="col-sm-4 col-6">
                        <small class="text-muted d-block">Date of Birth</small>
                        <span>${escapeHtml(cleanDob)}</span>
                    </div>
                    ` : ''}
                    ${cleanAddress ? `
                    <div class="col-sm-8 col-12">
                        <small class="text-muted d-block">Address</small>
                        <span>${escapeHtml(cleanAddress)}</span>
                    </div>
                    ` : ''}
                    <div class="col-sm-4 col-6">
                        <small class="text-muted d-block">Recorded On</small>
                        <span><i class="fas fa-calendar-alt me-1 text-muted"></i>${escapeHtml(dateStr) || '—'}</span>
                    </div>
                    <div class="col-sm-4 col-6">
                        <small class="text-muted d-block">Total Garments</small>
                        <span class="badge bg-primary rounded-pill">${garments.length} ${garments.length === 1 ? 'Garment' : 'Garments'}</span>
                    </div>
                </div>
            </div>
        </div>

        <h6 class="fw-bold text-dark mb-2 ms-1"><i class="fas fa-tshirt me-2 text-primary"></i>Garment Measurements (${garments.length})</h6>
        ${garmentsHtml}

        ${group.notes ? `
        <div class="card border shadow-none mt-3" style="border-radius: 12px; border-color: #fef08a !important; background-color: #fefce8;">
            <div class="card-body p-3">
                <strong class="text-warning-emphasis d-block mb-1" style="font-size: 0.82rem;"><i class="fas fa-sticky-note me-1"></i>Notes:</strong>
                <p class="mb-0 text-dark small">${escapeHtml(group.notes).replace(/\n/g, '<br>')}</p>
            </div>
        </div>
        ` : ''}
    `;

    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    } else if (typeof $ !== 'undefined' && $.fn.modal) {
        $('#viewMeasurementModal').modal('show');
    }
}

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

// Delete customer measurements
function deleteCustomerMeasurements(ids, customerName) {
    if (!Array.isArray(ids)) {
        try { ids = JSON.parse(ids); } catch(e) { ids = [ids]; }
    }
    const count = ids ? ids.length : 0;
    const infoEl = document.getElementById('deleteMeasurementInfo');
    if (infoEl) {
        infoEl.textContent = `${customerName || 'Customer'} (${count} ${count > 1 ? 'garments' : 'garment'})`;
    }
    const idEl = document.getElementById('deleteMeasurementId');
    if (idEl) {
        idEl.value = (ids && ids[0]) ? ids[0] : '';
    }
    
    let idsInput = document.getElementById('deleteMeasurementIds');
    if (!idsInput) {
        idsInput = document.createElement('input');
        idsInput.type = 'hidden';
        idsInput.id = 'deleteMeasurementIds';
        idsInput.name = 'measurement_ids';
        const form = document.getElementById('deleteMeasurementForm');
        if (form) form.appendChild(idsInput);
    }
    if (idsInput) {
        idsInput.value = JSON.stringify(ids || []);
    }
    
    const modalEl = document.getElementById('deleteModal');
    if (modalEl) {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else if (typeof $ !== 'undefined' && $.fn.modal) {
            $('#deleteModal').modal('show');
        }
    }
}

function deleteMeasurement(id) {
    if (typeof id === 'string' && customerMeasurementsMap[id]) {
        return deleteCustomerByKey(id);
    }
    deleteCustomerMeasurements([id], 'Measurement');
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
    const mobileCardsList = document.getElementById('measurementMobileCardsList');
    if (mobileCardsList) {
        const cards = Array.from(mobileCardsList.querySelectorAll('.measurement-card-row'));
        cards.sort((a, b) => {
            const nameA = a.getAttribute('data-name') || '';
            const nameB = b.getAttribute('data-name') || '';
            return sortAscending ? nameA.localeCompare(nameB) : nameB.localeCompare(nameA);
        });
        cards.forEach(c => mobileCardsList.appendChild(c));
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
                            <td colspan="5" class="text-center py-4">
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
                        <td colspan="5" class="text-center py-4">
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
    const mobileCardsList = document.getElementById('measurementMobileCardsList');
    if (!measurements || measurements.length === 0) {
        if (measurementsTable) {
            measurementsTable.innerHTML = `
            <tr>
                <td colspan="5" class="text-center py-5">
                    <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; border-radius: 50%; background: #f8fafc; color: #94a3b8; border: 1px solid #e2e8f0;">
                        <i class="fas fa-search fa-2x"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1" style="font-size: 1rem;">No Data Found</h5>
                    <p class="text-muted small mb-0">Try adjusting your filter criteria</p>
                </td>
            </tr>
        `;
        }
        if (mobileCardsList) {
            mobileCardsList.innerHTML = `
                <div class="text-center py-5" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 24px;">
                    <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; border-radius: 50%; background: #f8fafc; color: #94a3b8; border: 1px solid #e2e8f0;">
                        <i class="fas fa-search fa-2x"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1" style="font-size: 1rem;">No Data Found</h5>
                    <p class="text-muted small mb-0">Try adjusting your filter criteria</p>
                </div>
            `;
        }
        return;
    }
    
    let tableHTML = '';
    let cardsHTML = '';
    measurements.forEach((measurement, idx) => {
        let cleanCustName = measurement.name || ((measurement.first_name || '') + ' ' + (measurement.last_name || '')).trim();
        if (!cleanCustName) cleanCustName = 'Walk-in Customer';
        
        let initial = (cleanCustName.charAt(0) || 'C').toUpperCase();
        let subText = measurement.phone_number || measurement.customer_code || '';
        
        let charCodeSum = 0;
        for (let c = 0; c < cleanCustName.length; c++) charCodeSum += cleanCustName.charCodeAt(c);
        const palette = avatarPalettes[(charCodeSum + idx) % avatarPalettes.length];
        
        // Cloth types badge display
        let clothTypesHtml = '';
        const clothTypes = measurement.cloth_types || (measurement.cloth_type_name ? [measurement.cloth_type_name] : []);
        const garments = measurement.garments || [];
        
        if (clothTypes.length > 0) {
            clothTypesHtml = '<div class="d-flex flex-wrap gap-1 align-items-center">';
            clothTypes.forEach(ct => {
                clothTypesHtml += `<span class="badge rounded-pill px-2 py-1 fw-medium" style="background: #e0f2fe; color: #0284c7; font-size: 0.75rem;">${escapeHtml(ct)}</span>`;
            });
            if (clothTypes.length > 1) {
                clothTypesHtml += `<span class="badge rounded-pill px-2 py-1 fw-bold" style="background: #f1f5f9; color: #475569; font-size: 0.72rem;">${garments.length || clothTypes.length} Items</span>`;
            }
            clothTypesHtml += '</div>';
        } else {
            clothTypesHtml = '<span class="badge rounded-pill px-2 py-1 fw-medium" style="background: #e0f2fe; color: #0284c7; font-size: 0.75rem;">Custom</span>';
        }
        
        // Measurements summary
        let measurementSummaryHtml = '';
        if (garments.length > 0) {
            measurementSummaryHtml = '<div class="d-flex flex-column gap-1" style="max-width: 320px;">';
            garments.forEach(g => {
                let mData = g.measurement_data || {};
                if (typeof mData === 'string') {
                    try { mData = JSON.parse(mData); } catch(e) { mData = {}; }
                }
                const mKeys = Object.keys(mData);
                measurementSummaryHtml += '<div class="d-flex flex-wrap gap-1 align-items-center">';
                measurementSummaryHtml += `<span class="fw-bold text-secondary me-1" style="font-size: 0.73rem;">${escapeHtml(g.cloth_type_name)}:</span>`;
                if (mKeys.length > 0) {
                    mKeys.slice(0, 4).forEach(key => {
                        const shortKey = formatMeasurementShortKey(key);
                        const fullKey = key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ');
                        measurementSummaryHtml += `<span class="badge fw-medium px-2 py-1" style="background: #f1f5f9; color: #334155; font-size: 0.73rem; border-radius: 6px;" title="${escapeHtml(fullKey)}: ${escapeHtml(mData[key])}"><strong style="color: #475569;">${escapeHtml(shortKey)}:</strong> ${escapeHtml(mData[key])}</span>`;
                    });
                    if (mKeys.length > 4) {
                        measurementSummaryHtml += `<span class="text-muted small" style="font-size: 0.70rem;">+${mKeys.length - 4} more</span>`;
                    }
                } else {
                    measurementSummaryHtml += '<span class="text-muted small">—</span>';
                }
                measurementSummaryHtml += '</div>';
            });
            measurementSummaryHtml += '</div>';
        } else {
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
            if (keys.length > 0) {
                measurementSummaryHtml = '<div class="d-flex flex-wrap gap-1 align-items-center" style="max-width: 280px;">';
                keys.slice(0, 4).forEach(key => {
                    const shortKey = formatMeasurementShortKey(key);
                    const fullKey = key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ');
                    const val = measurementData[key];
                    measurementSummaryHtml += `<span class="badge fw-medium px-2 py-1" style="background: #f1f5f9; color: #334155; font-size: 0.73rem; border-radius: 6px;" title="${escapeHtml(fullKey)}: ${escapeHtml(val)}"><strong style="color: #475569;">${escapeHtml(shortKey)}:</strong> ${escapeHtml(val)}</span>`;
                });
                if (keys.length > 4) {
                    measurementSummaryHtml += `<span class="text-muted small" style="font-size: 0.72rem;">+${keys.length - 4} more</span>`;
                }
                measurementSummaryHtml += '</div>';
            } else {
                measurementSummaryHtml = '<span class="text-muted small">—</span>';
            }
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
        
        const custKey = 'filter_cust_' + idx;
        customerMeasurementsMap[custKey] = measurement;
        
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
                    ${clothTypesHtml}
                </td>
                <td class="py-3">
                    ${notesDisplay}
                </td>
                <td class="py-3">
                    <span class="text-muted small" style="font-size: 0.8rem;">${formattedDate}</span>
                </td>
                <td class="text-end pe-3 py-3">
                    <div class="d-inline-flex align-items-center gap-1">
                        <button type="button" class="cat-action-btn cat-action-edit" onclick="editCustomerByKey('${custKey}')" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="cat-action-btn cat-action-view" onclick="viewCustomerByKey('${custKey}')" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button type="button" class="cat-action-btn cat-action-del" onclick="deleteCustomerByKey('${custKey}')" title="Delete">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;

        cardsHTML += `
            <div class="measurement-mobile-card shadow-sm measurement-card-row" data-name="${escapeHtml(cleanCustName.toLowerCase())}">
                <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                    <div class="d-flex align-items-center gap-2 min-w-0">
                        <div class="d-flex align-items-center justify-content-center flex-shrink-0 fw-bold" style="width: 38px; height: 38px; border-radius: 10px; background: ${palette.bg}; border: 1px solid ${palette.border}; color: ${palette.color}; font-size: 0.9rem;">
                            ${initial}
                        </div>
                        <div class="min-w-0">
                            <strong class="text-dark cust-name text-truncate d-block" style="font-size: 0.95rem;">${escapeHtml(cleanCustName)}</strong>
                            <div class="text-muted small" style="font-size: 0.74rem;">${escapeHtml(subText)}</div>
                        </div>
                    </div>
                    <span class="text-muted small" style="font-size: 0.76rem;">${formattedDate}</span>
                </div>
                <div class="rounded-3 p-2 mb-3" style="background: #f8fafc; border: 1px solid #edf2f7; font-size: 0.8rem;">
                    <div class="text-muted mb-1" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 600;">Cloth Types & Garments</div>
                    ${clothTypesHtml}
                    ${measurement.notes ? `
                        <div class="mt-2 pt-2 border-top">
                            <div class="text-muted" style="font-size: 0.7rem; text-transform: uppercase; font-weight: 600;">Notes</div>
                            <div class="text-dark small mt-1" style="font-size: 0.78rem;">${escapeHtml(measurement.notes)}</div>
                        </div>
                    ` : ''}
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                    <span class="text-muted small" style="font-size: 0.72rem;">Actions</span>
                    <div class="d-inline-flex align-items-center gap-1">
                        <button type="button" class="cat-action-btn cat-action-edit" onclick="editCustomerByKey('${custKey}')" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="cat-action-btn cat-action-view" onclick="viewCustomerByKey('${custKey}')" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button type="button" class="cat-action-btn cat-action-del" onclick="deleteCustomerByKey('${custKey}')" title="Delete">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    if (measurementsTable) {
        measurementsTable.innerHTML = tableHTML;
    }
    if (mobileCardsList) {
        mobileCardsList.innerHTML = cardsHTML;
    }
}
    }, 100);

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'create') {
        const addMeasModalEl = document.getElementById('addMeasurementModal');
        if (addMeasModalEl && window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(addMeasModalEl).show();
        }
    }
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

/* Garment Accordion Dropdown Styling */
#garmentsContainer .accordion-item {
    border-radius: 12px !important;
    overflow: hidden;
    transition: all 0.2s ease;
    border: 1px solid #e2e8f0 !important;
}
#garmentsContainer .accordion-button {
    font-size: 0.92rem;
    font-weight: 600;
    padding: 10px 14px;
    border-radius: 10px;
    background-color: #f8fafc;
    color: #1e293b;
    box-shadow: none !important;
}
#garmentsContainer .accordion-button:not(.collapsed) {
    background-color: #eff6ff !important;
    color: #1d4ed8 !important;
    box-shadow: none !important;
}
#garmentsContainer .accordion-button:focus {
    box-shadow: none !important;
    border-color: #e2e8f0 !important;
}
#garmentsContainer .accordion-button::after {
    margin-left: auto;
    font-size: 0.85rem;
}
.measurement-field-col .input-group-text {
    background-color: #f8fafc;
    color: #334155;
    font-size: 0.82rem;
    font-weight: 500;
    border-color: #e2e8f0;
}
.measurement-field-col .form-control {
    border-color: #e2e8f0;
    font-size: 0.85rem;
}
.measurement-field-col .form-control:focus {
    border-color: #6366f1;
    box-shadow: none;
}
.garment-summary-badge {
    transition: all 0.2s ease;
}
#addAnotherClothTypeBtn {
    background-color: #f8fafc;
    border: 1px solid #cbd5e1 !important;
    color: #4f46e5;
    border-radius: 8px;
    font-size: 0.88rem;
    font-weight: 600;
    padding: 9px 16px;
    box-shadow: none !important;
    transition: none !important;
    cursor: pointer;
}
#addAnotherClothTypeBtn:hover,
#addAnotherClothTypeBtn:focus,
#addAnotherClothTypeBtn:active {
    background-color: #f8fafc !important;
    border-color: #cbd5e1 !important;
    color: #4f46e5 !important;
    box-shadow: none !important;
    transform: none !important;
    outline: none !important;
}
</style>