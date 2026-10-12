<?php

$page_title = 'Cloth Type Management';
require_once 'includes/header.php';

require_once 'models/ClothType.php';
require_once 'models/Category.php';

$clothTypeModel = new ClothType();
$categoryModel = new Category();
$message = '';
$messageType = '';

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
                
                if (isset($_FILES['chart_file']) && $_FILES['chart_file']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = '../uploads/measurement-charts/';
                    $fileName = time() . '_' . basename($_FILES['chart_file']['name']);
                    $targetPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['chart_file']['tmp_name'], $targetPath)) {
                        $data['measurement_chart_image'] = $targetPath;
                    }
                }
                
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
                
                if (isset($_FILES['chart_file']) && $_FILES['chart_file']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = '../uploads/measurement-charts/';
                    $fileName = time() . '_' . basename($_FILES['chart_file']['name']);
                    $targetPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['chart_file']['tmp_name'], $targetPath)) {
                        $data['measurement_chart_image'] = $targetPath;
                    }
                } elseif (!empty($_POST['measurement_chart_image'])) {
                    $data['measurement_chart_image'] = sanitize_input($_POST['measurement_chart_image']);
                }
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

if (!empty($category_filter)) {
    $clothTypes = array_filter($clothTypes, function($clothType) use ($category_filter) {
        return $clothType['category'] === $category_filter;
    });
}

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

$editClothType = null;
if (isset($_GET['edit'])) {
    $editClothType = $clothTypeModel->find((int)$_GET['edit']);
}

$clothTypeStats = $clothTypeModel->getClothTypeStats();

$current_company_id = get_company_id();
$dbCategories = $categoryModel->findAll(['status' => 'active', 'company_id' => $current_company_id], 'name ASC');
$categoriesList = array_unique(array_column($dbCategories, 'name'));
if (!function_exists('getCategoryDesign')) {
    function getCategoryDesign($name, $index = 0) {
        $n = mb_strtolower(trim($name));
                if (strpos($n, 'kurti') !== false || strpos($n, 'frock') !== false || strpos($n, 'dress') !== false || strpos($n, 'lehenga') !== false || strpos($n, 'blouse') !== false || strpos($n, 'gown') !== false) {
            return [
                'bg' => '#fdf2f8',
                'border' => '#fce7f3',
                'color' => '#ec4899',
                'svg' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="#ec4899"><path d="M12 2a2 2 0 0 0-2 2v1H8.5a1.5 1.5 0 0 0-1.5 1.5v1.2a1 1 0 0 0 .5.9L9 9.5 7.2 21.2A1 1 0 0 0 8.2 22h7.6a1 1 0 0 0 1-1.2L15 9.5l1.5-.9a1 1 0 0 0 .5-.9V6.5A1.5 1.5 0 0 0 15.5 5H14V4a2 2 0 0 0-2-2z"/></svg>'
            ];
        }
                if (strpos($n, 'party') !== false || strpos($n, 'shirt') !== false || strpos($n, 't-shirt') !== false || strpos($n, 'top') !== false) {
            return [
                'bg' => '#eff6ff',
                'border' => '#dbeafe',
                'color' => '#3b82f6',
                'svg' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="#3b82f6"><path d="M21.7 6.3l-3.2-3.2c-.4-.4-1-.5-1.5-.2L14.5 4.2C13.8 3.5 12.9 3 12 3s-1.8.5-2.5 1.2L7 2.9c-.5-.3-1.1-.2-1.5.2L2.3 6.3c-.4.4-.4 1.1 0 1.5l2.7 2.7c.4.4 1.1.4 1.5 0l1.5-1.5V20c0 .6.4 1 1 1h6c.6 0 1-.4 1-1V9l1.5 1.5c.4.4 1.1.4 1.5 0l2.7-2.7c.4-.4.4-1.1 0-1.5z"/></svg>'
            ];
        }
        if (strpos($n, 'pent') !== false || strpos($n, 'pant') !== false || strpos($n, 'trouser') !== false || strpos($n, 'jeans') !== false || strpos($n, 'pajama') !== false) {
            return [
                'bg' => '#f0fdf4',
                'border' => '#dcfce7',
                'color' => '#10b981',
                'svg' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="#10b981"><path d="M18 2H6c-.6 0-1 .4-1 1v4h14V3c0-.6-.4-1-1-1zm1 6H5l1.2 13.2c.1.5.5.8 1 .8h3.3c.5 0 .9-.4 1-.9L12 13l.5 8.1c.1.5.5.9 1 .9h3.3c.5 0 .9-.3 1-.8L19 8z"/></svg>'
            ];
        }
                if (strpos($n, 'suit') !== false || strpos($n, 'salwar') !== false || strpos($n, 'blazer') !== false || strpos($n, 'coat') !== false || strpos($n, 'sherwani') !== false) {
            return [
                'bg' => '#faf5ff',
                'border' => '#ede9fe',
                'color' => '#8b5cf6',
                'svg' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="#8b5cf6"><path d="M12 2c-.6 0-1.1.3-1.5.7L6 7.5V21c0 .6.4 1 1 1h10c.6 0 1-.4 1-1V7.5l-4.5-4.8c-.4-.4-.9-.7-1.5-.7zm-1 3.5l1 2 1-2 2.5 2.5L14 13h-4l-1.5-5.5L11 5.5z"/></svg>'
            ];
        }
                if (strpos($n, 'uniform') !== false || strpos($n, 'school') !== false) {
            return [
                'bg' => '#fefce8',
                'border' => '#fef08a',
                'color' => '#ca8a04',
                'svg' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="#ca8a04"><path d="M16 2.5l-2.5 3h-3L8 2.5 3.5 5.2c-.4.2-.6.7-.4 1.1l1.8 4c.2.4.7.6 1.1.4L7 10v10.5c0 .8.7 1.5 1.5 1.5h7c.8 0 1.5-.7 1.5-1.5V10l1 .7c.4.2.9 0 1.1-.4l1.8-4c.2-.4 0-.9-.4-1.1L16 2.5z"/></svg>'
            ];
        }
        $palettes = [
            ['bg' => '#fdf2f8', 'border' => '#fce7f3', 'color' => '#ec4899', 'svg' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="#ec4899"><path d="M12 2a2 2 0 0 0-2 2v1H8.5a1.5 1.5 0 0 0-1.5 1.5v1.2a1 1 0 0 0 .5.9L9 9.5 7.2 21.2A1 1 0 0 0 8.2 22h7.6a1 1 0 0 0 1-1.2L15 9.5l1.5-.9a1 1 0 0 0 .5-.9V6.5A1.5 1.5 0 0 0 15.5 5H14V4a2 2 0 0 0-2-2z"/></svg>'],
            ['bg' => '#eff6ff', 'border' => '#dbeafe', 'color' => '#3b82f6', 'svg' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="#3b82f6"><path d="M21.7 6.3l-3.2-3.2c-.4-.4-1-.5-1.5-.2L14.5 4.2C13.8 3.5 12.9 3 12 3s-1.8.5-2.5 1.2L7 2.9c-.5-.3-1.1-.2-1.5.2L2.3 6.3c-.4.4-.4 1.1 0 1.5l2.7 2.7c.4.4 1.1.4 1.5 0l1.5-1.5V20c0 .6.4 1 1 1h6c.6 0 1-.4 1-1V9l1.5 1.5c.4.4 1.1.4 1.5 0l2.7-2.7c.4-.4.4-1.1 0-1.5z"/></svg>'],
            ['bg' => '#f0fdf4', 'border' => '#dcfce7', 'color' => '#10b981', 'svg' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="#10b981"><path d="M18 2H6c-.6 0-1 .4-1 1v4h14V3c0-.6-.4-1-1-1zm1 6H5l1.2 13.2c.1.5.5.8 1 .8h3.3c.5 0 .9-.4 1-.9L12 13l.5 8.1c.1.5.5.9 1 .9h3.3c.5 0 .9-.3 1-.8L19 8z"/></svg>'],
            ['bg' => '#faf5ff', 'border' => '#ede9fe', 'color' => '#8b5cf6', 'svg' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="#8b5cf6"><path d="M12 2c-.6 0-1.1.3-1.5.7L6 7.5V21c0 .6.4 1 1 1h10c.6 0 1-.4 1-1V7.5l-4.5-4.8c-.4-.4-.9-.7-1.5-.7zm-1 3.5l1 2 1-2 2.5 2.5L14 13h-4l-1.5-5.5L11 5.5z"/></svg>'],
            ['bg' => '#fefce8', 'border' => '#fef08a', 'color' => '#ca8a04', 'svg' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="#ca8a04"><path d="M16 2.5l-2.5 3h-3L8 2.5 3.5 5.2c-.4.2-.6.7-.4 1.1l1.8 4c.2.4.7.6 1.1.4L7 10v10.5c0 .8.7 1.5 1.5 1.5h7c.8 0 1.5-.7 1.5-1.5V10l1 .7c.4.2.9 0 1.1-.4l1.8-4c.2-.4 0-.9-.4-1.1L16 2.5z"/></svg>'],
            ['bg' => '#fdf4ff', 'border' => '#f5d0fe', 'color' => '#c026d3', 'svg' => '<i class="fas fa-tshirt" style="font-size: 1.1rem;"></i>'],
        ];
        return $palettes[$index % count($palettes)];
    }
}
?>

<style>
#categoryFilter {
    height: 42px !important;
    padding: 0.45rem 2.2rem 0.45rem 1rem !important;
    line-height: 1.4 !important;
    font-size: 0.85rem !important;
    border-radius: 14px !important;
    border: 1px solid #e2e8f0 !important;
    background-color: #ffffff !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02) !important;
}
#searchInput {
    height: 42px !important;
    font-size: 0.85rem !important;
    border-radius: 14px !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02) !important;
}
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
    .category-table .cloth-name {
        font-size: 0.82rem !important;
    }
    .category-table .cloth-desc {
        font-size: 0.75rem !important;
        max-width: 140px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
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
<?php
$pageHeaderTitle = 'Cloth Type';
$pageHeaderSubtitle = 'Manage your shop cloth types<br>organize and track your tailoring garments.';
include 'includes/page-header.php';
?>
<div class="row g-2 mb-3">
    <div class="col-6 col-md-4">
        <div class="cat-stat-box d-flex align-items-center justify-content-between" style="background: #faf5ff; border: 1px solid #ede9fe;">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #c084fc; color: #ffffff;">
                    <i class="fas fa-tshirt" style="font-size: 0.95rem;"></i>
                </div>
                <div class="min-w-0">
                    <div class="fw-bold text-dark lh-1" style="font-size: 1.35rem;"><?php echo number_format($clothTypeStats['total']); ?></div>
                    <div class="text-muted text-truncate" style="font-size: 0.72rem; margin-top: 3px;">Total Types</div>
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
    <div class="col-6 col-md-4">
        <div class="cat-stat-box d-flex align-items-center justify-content-between" style="background: #f0fdf4; border: 1px solid #dcfce7;">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #22c55e; color: #ffffff;">
                    <i class="fas fa-check-circle" style="font-size: 0.95rem;"></i>
                </div>
                <div class="min-w-0">
                    <div class="fw-bold text-dark lh-1" style="font-size: 1.35rem;"><?php echo number_format($clothTypeStats['active']); ?></div>
                    <div class="text-muted text-truncate" style="font-size: 0.72rem; margin-top: 3px;">Active Types</div>
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
        <div class="col-12 col-md-4">
        <div class="cat-stat-box d-flex align-items-center justify-content-between" style="background: #f0f9ff; border: 1px solid #e0f2fe;">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #38bdf8; color: #ffffff;">
                    <i class="fas fa-ban" style="font-size: 0.95rem;"></i>
                </div>
                <div class="min-w-0">
                    <div class="fw-bold text-dark lh-1" style="font-size: 1.35rem;"><?php echo number_format($clothTypeStats['inactive']); ?></div>
                    <div class="text-muted text-truncate" style="font-size: 0.72rem; margin-top: 3px;">Inactive Types</div>
                </div>
            </div>
            <div class="d-flex align-items-end gap-1 ms-1 flex-shrink-0" style="height: 24px; color: #7dd3fc;">
                <span style="width: 3px; height: 30%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 55%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 40%; background: currentColor; border-radius: 2px;"></span>
                <span style="width: 3px; height: 90%; background: currentColor; border-radius: 2px;"></span>
            </div>
        </div>
    </div>
</div>
<div class="row g-2 align-items-center mb-3">
    <div class="col-12 col-md-5">
        <div class="position-relative">
            <i class="fas fa-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.9rem;"></i>
            <input type="text" id="searchInput" class="form-control" placeholder="Search cloth types..." autocomplete="off" value="<?php echo htmlspecialchars($search); ?>" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding-left: 2.4rem; padding-right: 1rem; height: 42px; font-size: 0.85rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        </div>
    </div>
    <div class="col-7 col-md-4">
        <select class="form-select" id="categoryFilter" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.85rem; height: 42px !important; padding: 0.45rem 2.2rem 0.45rem 1rem !important; line-height: 1.4 !important; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <option value="">All Categories</option>
            <?php foreach ($categoriesList as $catName): ?>
                <option value="<?php echo display_val($catName); ?>" <?php echo $category_filter === $catName ? 'selected' : ''; ?>>
                    <?php echo ucwords(display_val($catName)); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-5 col-md-3 d-flex gap-2">
        <button type="button" id="clearFilters" class="btn btn-outline-danger align-items-center justify-content-center" style="border-radius: 14px; height: 42px; font-size: 0.82rem; padding: 0 14px; <?php echo (empty($search) && empty($category_filter)) ? 'display: none !important;' : 'display: inline-flex !important;'; ?>" title="Clear Filters">
            <i class="fas fa-times me-1"></i> Clear
        </button>
        <button type="button" class="btn text-white fw-semibold d-inline-flex align-items-center justify-content-center gap-1 flex-grow-1 text-nowrap px-3 shadow-sm" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 14px; font-size: 0.84rem; height: 42px; border: none; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.28);" data-bs-toggle="modal" data-bs-target="#clothTypeModal">
            <i class="fas fa-plus"></i>
            <span>Add Cloth Type</span>
        </button>
    </div>
</div>
<div id="filterResults" class="mb-3" style="display: none;">
    <div class="alert alert-info py-2 px-3 d-flex align-items-center" style="border-radius: 12px; font-size: 0.85rem;">
        <i class="fas fa-info-circle me-2"></i>
        <span><strong id="filterCount">0</strong> cloth types found</span>
    </div>
</div>
<div class="card shadow-sm border-0 mb-3" style="border-radius: 14px; overflow: hidden; border: 1px solid #f1f5f9 !important;">
    <div class="card-body p-0">
        <?php if (!empty($clothTypes)): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle category-table">
                    <thead class="cat-table-head">
                        <tr>
                            <th class="ps-3 py-3" style="background-color: #f8fafc !important; color: #334155 !important; font-size: 0.78rem !important; font-weight: 700 !important; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0 !important;">
                                <div class="d-flex align-items-center gap-2">
                                    <span>CLOTH NAME</span>
                                    <button type="button" class="btn btn-link p-0 text-muted border-0 shadow-none" id="sortClothTypesBtn" title="Sort A-Z" style="font-size: 0.75rem; line-height: 1; text-decoration: none;">
                                        <i class="fas fa-sort" id="sortIcon"></i>
                                    </button>
                                </div>
                            </th>
                            <th class="py-3" style="background-color: #f8fafc !important; color: #334155 !important; font-size: 0.78rem !important; font-weight: 700 !important; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0 !important;">CATEGORY</th>
                            <th class="py-3" style="background-color: #f8fafc !important; color: #334155 !important; font-size: 0.78rem !important; font-weight: 700 !important; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0 !important;">STANDARD RATE</th>
                            <th class="py-3" style="background-color: #f8fafc !important; color: #334155 !important; font-size: 0.78rem !important; font-weight: 700 !important; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0 !important;">ORDERS</th>
                            <th class="py-3" style="background-color: #f8fafc !important; color: #334155 !important; font-size: 0.78rem !important; font-weight: 700 !important; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0 !important;">STATUS</th>
                            <th class="text-end pe-3 py-3" style="background-color: #f8fafc !important; color: #334155 !important; font-size: 0.78rem !important; font-weight: 700 !important; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0 !important;">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody id="clothTypeTableBody">
                        <?php foreach ($clothTypes as $idx => $clothType): 
                            $design = getCategoryDesign($clothType['name'], $idx);
                            $cleanName = display_val($clothType['name']);
                            $cleanDesc = display_val($clothType['description'] ?? '');
                        ?>
                        <tr class="cloth-type-row" data-name="<?php echo strtolower($cleanName); ?>">
                            <td class="ps-3 py-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; border-radius: 10px; background: <?php echo $design['bg']; ?>; border: 1px solid <?php echo $design['border']; ?>; color: <?php echo $design['color']; ?>;">
                                        <?php echo $design['svg']; ?>
                                    </div>
                                    <div>
                                        <strong class="cloth-name text-dark" style="font-size: 0.9rem;"><?php echo ucwords($cleanName); ?></strong>
                                        <?php if (!empty($cleanDesc)): ?>
                                            <div class="text-muted small cloth-desc" style="font-size: 0.74rem; line-height: 1.25;"><?php echo substr($cleanDesc, 0, 45) . (strlen($cleanDesc) > 45 ? '...' : ''); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3">
                                <?php if (!empty($clothType['category'])): ?>
                                    <span class="badge rounded-pill px-2 py-1 fw-medium" style="background: #e0f2fe; color: #0284c7; font-size: 0.72rem;">
                                        <?php echo ucwords(display_val($clothType['category'])); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3">
                                <?php if (!empty($clothType['standard_rate'])): ?>
                                    <span class="fw-bold text-dark" style="font-size: 0.88rem;"><?php echo format_currency($clothType['standard_rate']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">Not set</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3">
                                <span class="badge rounded-pill px-2 py-1 fw-semibold" style="background: #f1f5f9; color: #475569; font-size: 0.72rem;"><?php echo $clothType['order_count']; ?></span>
                            </td>
                            <td class="py-3">
                                <span class="badge rounded-pill fw-semibold px-2 py-1" style="font-size: 0.72rem; background-color: <?php echo $clothType['status'] === 'active' ? '#10b981' : '#94a3b8'; ?>; color: #ffffff;">
                                    <?php echo ucfirst($clothType['status']); ?>
                                </span>
                            </td>
                            <td class="text-end pe-3 py-3">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <button type="button" 
                                            class="cat-action-btn cat-action-edit" 
                                            onclick="editClothType(<?php echo htmlspecialchars(json_encode($clothType)); ?>)"
                                            title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" 
                                            class="cat-action-btn cat-action-del" 
                                            onclick="deleteClothType(<?php echo $clothType['id']; ?>, '<?php echo addslashes($cleanName); ?>')"
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
            <div id="paginationContainer">
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
            </div>
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
                            <label for="name" class="form-label"> Cloth Name *</label>
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
                            <select class="form-select" id="category" name="category">
                                <option value="">Select Category</option>
                                <?php foreach ($categoriesList as $catName): ?>
                                    <option value="<?php echo display_val($catName); ?>">
                                        <?php echo ucwords(display_val($catName)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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
    document.getElementById('name').value = clothType.name || '';
    document.getElementById('description').value = clothType.description || '';
    document.getElementById('category').value = clothType.category || '';
    document.getElementById('standard_rate').value = clothType.standard_rate || '';
    document.getElementById('status').value = clothType.status || 'active';
    if (clothType.measurement_chart_image) {
        document.getElementById('existingChartImage').value = clothType.measurement_chart_image;
        document.getElementById('chartPreviewImg').src = '../' + clothType.measurement_chart_image.replace(/^\.\//, '');
        document.getElementById('currentChartPreview').style.display = 'block';
    } else {
        document.getElementById('currentChartPreview').style.display = 'none';
    }
    new bootstrap.Modal(document.getElementById('clothTypeModal')).show();
}

function deleteClothType(clothTypeId, clothTypeName) {
    document.getElementById('deleteClothTypeId').value = clothTypeId;
    document.getElementById('deleteClothTypeName').textContent = clothTypeName;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

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

document.getElementById('clothTypeModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('clothTypeModalTitle').textContent = 'Add Cloth Type';
    document.getElementById('clothTypeAction').value = 'create';
    document.getElementById('clothTypeId').value = '';
    document.getElementById('existingChartImage').value = '';
    document.getElementById('currentChartPreview').style.display = 'none';
    document.getElementById('newChartPreview').style.display = 'none';
    document.getElementById('clothTypeForm').reset();
});
let sortAscending = true;
document.getElementById('sortClothTypesBtn')?.addEventListener('click', function(e) {
    e.preventDefault();
    sortAscending = !sortAscending;
    const sortIcon = document.getElementById('sortIcon');
    if (sortIcon) {
        sortIcon.className = sortAscending ? 'fas fa-sort-alpha-down' : 'fas fa-sort-alpha-up';
    }
    
    const tableBody = document.getElementById('clothTypeTableBody');
    if (tableBody) {
        const rows = Array.from(tableBody.querySelectorAll('tr.cloth-type-row'));
        rows.sort((a, b) => {
            const nameA = a.getAttribute('data-name') || '';
            const nameB = b.getAttribute('data-name') || '';
            return sortAscending ? nameA.localeCompare(nameB) : nameB.localeCompare(nameA);
        });
        rows.forEach(r => tableBody.appendChild(r));
    }
});
const garmentIcons = [
    { bg: '#fdf2f8', border: '#fce7f3', color: '#ec4899', svg: '<svg width="22" height="22" viewBox="0 0 24 24" fill="#ec4899"><path d="M12 2a2 2 0 0 0-2 2v1H8.5a1.5 1.5 0 0 0-1.5 1.5v1.2a1 1 0 0 0 .5.9L9 9.5 7.2 21.2A1 1 0 0 0 8.2 22h7.6a1 1 0 0 0 1-1.2L15 9.5l1.5-.9a1 1 0 0 0 .5-.9V6.5A1.5 1.5 0 0 0 15.5 5H14V4a2 2 0 0 0-2-2z"/></svg>' },
    { bg: '#eff6ff', border: '#dbeafe', color: '#3b82f6', svg: '<svg width="22" height="22" viewBox="0 0 24 24" fill="#3b82f6"><path d="M21.7 6.3l-3.2-3.2c-.4-.4-1-.5-1.5-.2L14.5 4.2C13.8 3.5 12.9 3 12 3s-1.8.5-2.5 1.2L7 2.9c-.5-.3-1.1-.2-1.5.2L2.3 6.3c-.4.4-.4 1.1 0 1.5l2.7 2.7c.4.4 1.1.4 1.5 0l1.5-1.5V20c0 .6.4 1 1 1h6c.6 0 1-.4 1-1V9l1.5 1.5c.4.4 1.1.4 1.5 0l2.7-2.7c.4-.4.4-1.1 0-1.5z"/></svg>' },
    { bg: '#f0fdf4', border: '#dcfce7', color: '#10b981', svg: '<svg width="22" height="22" viewBox="0 0 24 24" fill="#10b981"><path d="M18 2H6c-.6 0-1 .4-1 1v4h14V3c0-.6-.4-1-1-1zm1 6H5l1.2 13.2c.1.5.5.8 1 .8h3.3c.5 0 .9-.4 1-.9L12 13l.5 8.1c.1.5.5.9 1 .9h3.3c.5 0 .9-.3 1-.8L19 8z"/></svg>' },
    { bg: '#faf5ff', border: '#ede9fe', color: '#8b5cf6', svg: '<svg width="22" height="22" viewBox="0 0 24 24" fill="#8b5cf6"><path d="M12 2c-.6 0-1.1.3-1.5.7L6 7.5V21c0 .6.4 1 1 1h10c.6 0 1-.4 1-1V7.5l-4.5-4.8c-.4-.4-.9-.7-1.5-.7zm-1 3.5l1 2 1-2 2.5 2.5L14 13h-4l-1.5-5.5L11 5.5z"/></svg>' },
    { bg: '#fefce8', border: '#fef08a', color: '#ca8a04', svg: '<svg width="22" height="22" viewBox="0 0 24 24" fill="#ca8a04"><path d="M16 2.5l-2.5 3h-3L8 2.5 3.5 5.2c-.4.2-.6.7-.4 1.1l1.8 4c.2.4.7.6 1.1.4L7 10v10.5c0 .8.7 1.5 1.5 1.5h7c.8 0 1.5-.7 1.5-1.5V10l1 .7c.4.2.9 0 1.1-.4l1.8-4c.2-.4 0-.9-.4-1.1L16 2.5z"/></svg>' }
];
function getClothIcon(name, idx) {
    const n = (name || '').toLowerCase();
    if (n.includes('kurti') || n.includes('frock') || n.includes('gown') || n.includes('blouse') || n.includes('top')) return garmentIcons[0];
    if (n.includes('shirt') || n.includes('t-shirt') || n.includes('tshirt') || n.includes('kurta')) return garmentIcons[1];
    if (n.includes('pant') || n.includes('pent') || n.includes('trouser') || n.includes('jeans') || n.includes('pyjama')) return garmentIcons[2];
    if (n.includes('suit') || n.includes('salwar') || n.includes('blazer') || n.includes('coat') || n.includes('sherwani')) return garmentIcons[3];
    if (n.includes('uniform') || n.includes('school')) return garmentIcons[4];
    return garmentIcons[idx % garmentIcons.length];
}
let filterTimeout;
const searchInput = document.getElementById('searchInput');
const categoryFilter = document.getElementById('categoryFilter');
const clearFilters = document.getElementById('clearFilters');
const filterResults = document.getElementById('filterResults');
const filterCount = document.getElementById('filterCount');
const clothTypesTable = document.getElementById('clothTypeTableBody');
[searchInput, categoryFilter].forEach(element => {
    if (!element) return;
    element.addEventListener('change', performFilter);
    if (element === searchInput) {
        element.addEventListener('input', performFilter);
    }
});
if (searchInput) {
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(filterTimeout);
            executeFilter();
        }
    });
}
if (clearFilters) {
    clearFilters.addEventListener('click', function() {
        searchInput.value = '';
        categoryFilter.value = '';
        window.location.href = 'cloth-types.php';
    });
}

function performFilter() {
    clearTimeout(filterTimeout);
    const searchVal = searchInput ? searchInput.value.trim() : '';
    const catVal = categoryFilter ? categoryFilter.value : '';
    
    if (clearFilters) {
        clearFilters.style.setProperty('display', (searchVal !== '' || catVal !== '') ? 'inline-flex' : 'none', 'important');
    }
    
    if (this === searchInput) {
        filterTimeout = setTimeout(() => {
            executeFilter();
        }, 300);
    } else {
        executeFilter();
    }
}

function executeFilter() {
    if (!searchInput || !categoryFilter) return;
    const search = searchInput.value.trim();
    const category = categoryFilter.value;
    
    if (search || category) {
        if (filterResults) filterResults.style.display = 'block';
        if (filterCount) filterCount.textContent = 'Filtering...';
    } else {
        if (filterResults) filterResults.style.display = 'none';
    }
    
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
                if (data.success) {
                    const clothTypes = Array.isArray(data.cloth_types) ? data.cloth_types : [];
                    displayFilterResults(clothTypes);
                    if (data.pagination && data.pagination.total_cloth_types !== undefined) {
                        if (filterCount) filterCount.textContent = data.pagination.total_cloth_types;
                        updatePagination(data.pagination, search, category);
                    } else {
                        if (filterCount) filterCount.textContent = clothTypes.length;
                        const pagCont = document.getElementById('paginationContainer');
                        if (pagCont) pagCont.innerHTML = '';
                    }
                } else {
                    console.error('Filter error:', data.error);
                    if (filterCount) filterCount.textContent = 'Filter failed: ' + (data.error || 'Unknown error');
                }
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                if (filterCount) filterCount.textContent = 'Invalid response from server';
            }
        })
        .catch(error => {
            console.error('Filter error:', error);
            if (filterCount) filterCount.textContent = 'Filter failed: ' + error.message;
        });
}

function displayFilterResults(clothTypes) {
    if (!clothTypesTable) return;
    
    if (!clothTypes || clothTypes.length === 0) {
        clothTypesTable.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-5">
                    <i class="fas fa-search fa-2x text-muted mb-2"></i>
                    <h5 class="text-muted" style="font-size: 0.95rem;">No cloth types found</h5>
                    <p class="text-muted small mb-0">Try adjusting your filter criteria</p>
                </td>
            </tr>
        `;
        return;
    }
    
    let tableHTML = '';
    clothTypes.forEach((clothType, idx) => {
        const formatCurrency = (amount) => '₹' + parseFloat(amount).toLocaleString('en-IN', { minimumFractionDigits: 2 });
        const iconInfo = getClothIcon(clothType.name, idx);
        const cleanName = (clothType.name || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const cleanDesc = (clothType.description || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const descShort = cleanDesc.length > 45 ? cleanDesc.substring(0, 45) + '...' : cleanDesc;
        const statusVal = clothType.status || 'active';
        const isStatusActive = statusVal === 'active';
        
        tableHTML += `
            <tr class="cloth-type-row" data-name="${cleanName.toLowerCase()}">
                <td class="ps-3 py-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; border-radius: 10px; background: ${iconInfo.bg}; border: 1px solid ${iconInfo.border}; color: ${iconInfo.color};">
                            ${iconInfo.svg}
                        </div>
                        <div>
                            <strong class="cloth-name text-dark" style="font-size: 0.9rem;">${cleanName}</strong>
                            ${cleanDesc ? `<div class="text-muted small cloth-desc" style="font-size: 0.74rem; line-height: 1.25;">${descShort}</div>` : ''}
                        </div>
                    </div>
                </td>
                <td class="py-3">
                    ${clothType.category ? `<span class="badge rounded-pill px-2 py-1 fw-medium" style="background: #e0f2fe; color: #0284c7; font-size: 0.72rem;">${clothType.category}</span>` : '<span class="text-muted small">—</span>'}
                </td>
                <td class="py-3">
                    ${clothType.standard_rate ? `<span class="fw-bold text-dark" style="font-size: 0.88rem;">${formatCurrency(clothType.standard_rate)}</span>` : '<span class="text-muted small">Not set</span>'}
                </td>
                <td class="py-3">
                    <span class="badge rounded-pill px-2 py-1 fw-semibold" style="background: #f1f5f9; color: #475569; font-size: 0.72rem;">${clothType.order_count || 0}</span>
                </td>
                <td class="py-3">
                    <span class="badge rounded-pill fw-semibold px-2 py-1" style="font-size: 0.72rem; background-color: ${isStatusActive ? '#10b981' : '#94a3b8'}; color: #ffffff;">
                        ${statusVal.charAt(0).toUpperCase() + statusVal.slice(1)}
                    </span>
                </td>
                <td class="text-end pe-3 py-3">
                    <div class="d-inline-flex align-items-center gap-1">
                        <button type="button" 
                                class="cat-action-btn cat-action-edit" 
                                onclick="editClothType(${JSON.stringify(clothType).replace(/"/g, '&quot;')})"
                                title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" 
                                class="cat-action-btn cat-action-del" 
                                onclick="deleteClothType(${clothType.id}, '${cleanName.replace(/'/g, "\\'")}')"
                                title="Delete">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    clothTypesTable.innerHTML = tableHTML;
}

function updatePagination(pagination, search, category) {
    const container = document.getElementById('paginationContainer');
    if (!container) return;
    
    if (pagination.total_pages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    const searchParam = search ? `&search=${encodeURIComponent(search)}` : '';
    const categoryParam = category ? `&category=${encodeURIComponent(category)}` : '';
    const page = pagination.current_page;
    const totalPages = pagination.total_pages;
    
    let html = `
        <nav aria-label="Cloth type pagination">
            <ul class="pagination justify-content-center">
    `;
    
    if (page > 1) {
        html += `
            <li class="page-item">
                <a class="page-link" href="?page=${page - 1}${categoryParam}${searchParam}">Previous</a>
            </li>
        `;
    }
    
    const startPage = Math.max(1, page - 2);
    const endPage = Math.min(totalPages, page + 2);
    
    for (let i = startPage; i <= endPage; i++) {
        html += `
            <li class="page-item ${i === page ? 'active' : ''}">
                <a class="page-link" href="?page=${i}${categoryParam}${searchParam}">${i}</a>
            </li>
        `;
    }
    
    if (page < totalPages) {
        html += `
            <li class="page-item">
                <a class="page-link" href="?page=${page + 1}${categoryParam}${searchParam}">Next</a>
            </li>
        `;
    }
    html += `
            </ul>
        </nav>
    `;
    container.innerHTML = html;
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