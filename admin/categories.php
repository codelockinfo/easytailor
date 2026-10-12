<?php

$page_title = 'Category Management';
require_once 'includes/header.php';

require_once 'models/Category.php';

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
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = RECORDS_PER_PAGE;
$offset = ($page - 1) * $limit;

$allCategories = $categoryModel->getCategoriesWithStats();
if (!empty($search)) {
    $searchLower = mb_strtolower($search);
    $allCategories = array_filter($allCategories, function($cat) use ($searchLower) {
        $nameMatch = mb_stripos($cat['name'], $searchLower) !== false;
        $descriptionMatch = !empty($cat['description']) && mb_stripos($cat['description'], $searchLower) !== false;
        return $nameMatch || $descriptionMatch;
    });
}

$allCategories = array_values($allCategories);
$totalCategories = count($allCategories);

if ($limit > 0) {
    $categories = array_slice($allCategories, $offset, $limit);
    $totalPages = max(1, ceil($totalCategories / $limit));
} else {
    $categories = $allCategories;
    $totalPages = 1;
}

$catStats = $categoryModel->getCategoryStats();
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
            ['bg' => '#fdf4ff', 'border' => '#f5d0fe', 'color' => '#c026d3', 'svg' => '<i class="fas fa-tags" style="font-size: 1.1rem;"></i>'],
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
.cat-action-del {
    background: #fef2f2;
    border-color: #fecaca;
    color: #ef4444;
}
.cat-action-del:hover {
    background: #fee2e2;
    color: #dc2626;
}
.cat-mobile-card {
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.cat-mobile-card:active {
    transform: scale(0.99);
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
    .category-table .cat-name {
        font-size: 0.82rem !important;
    }
    .category-table .cat-desc {
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
$pageHeaderTitle = 'Category';
$pageHeaderSubtitle = 'Manage your shop categories<br>organize and grow your tailoring business.';
include 'includes/page-header.php';
?>
<div class="row g-2 mb-3">
    <div class="col-6 col-md-4">
        <div class="cat-stat-box d-flex align-items-center justify-content-between" style="background: #faf5ff; border: 1px solid #ede9fe;">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #c084fc; color: #ffffff;">
                    <i class="fas fa-tags" style="font-size: 0.95rem;"></i>
                </div>
                <div class="min-w-0">
                    <div class="fw-bold text-dark lh-1" style="font-size: 1.35rem;"><?php echo number_format($catStats['total']); ?></div>
                    <div class="text-muted text-truncate" style="font-size: 0.72rem; margin-top: 3px;">Total Categories</div>
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
                    <div class="fw-bold text-dark lh-1" style="font-size: 1.35rem;"><?php echo number_format($catStats['active']); ?></div>
                    <div class="text-muted text-truncate" style="font-size: 0.72rem; margin-top: 3px;">Active Categories</div>
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
                    <div class="fw-bold text-dark lh-1" style="font-size: 1.35rem;"><?php echo number_format($catStats['inactive']); ?></div>
                    <div class="text-muted text-truncate" style="font-size: 0.72rem; margin-top: 3px;">Inactive Categories</div>
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
<div class="d-flex align-items-center gap-2 mb-3">
    <div class="position-relative flex-grow-1">
        <i class="fas fa-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.9rem;"></i>
        <input type="text" id="categorySearch" class="form-control" placeholder="Search categories..." autocomplete="off" value="<?php echo htmlspecialchars($search); ?>" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding-left: 2.4rem; padding-top: 0.6rem; padding-bottom: 0.6rem; font-size: 0.85rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
    </div>
    <button type="button" class="btn text-white fw-semibold d-inline-flex align-items-center gap-1 text-nowrap px-3" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 14px; font-size: 0.84rem; height: 42px; border: none; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.28);" data-bs-toggle="modal" data-bs-target="#categoryModal">
        <i class="fas fa-plus"></i>
        <span>Add Category</span>
    </button>
</div>
<div class="card shadow-sm border-0 mb-3" style="border-radius: 14px; overflow: hidden; border: 1px solid #f1f5f9 !important;">
    <div class="card-body p-0">
        <?php if (!empty($categories)): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle category-table">
                    <thead class="cat-table-head">
                        <tr>
                            <th class="ps-3 py-3" style="background-color: #f8fafc !important; color: #334155 !important; font-size: 0.78rem !important; font-weight: 700 !important; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0 !important;">
                                <div class="d-flex align-items-center gap-2">
                                    <span>CATEGORY</span>
                                    <button type="button" class="btn btn-link p-0 text-muted border-0 shadow-none" id="sortCategoriesBtn" title="Sort A-Z" style="font-size: 0.75rem; line-height: 1; text-decoration: none;">
                                        <i class="fas fa-sort" id="sortIcon"></i>
                                    </button>
                                </div>
                            </th>
                            <th class="py-3" style="background-color: #f8fafc !important; color: #334155 !important; font-size: 0.78rem !important; font-weight: 700 !important; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0 !important;">DESCRIPTION</th>
                            <th class="py-3" style="background-color: #f8fafc !important; color: #334155 !important; font-size: 0.78rem !important; font-weight: 700 !important; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0 !important;">STATUS</th>
                            <th class="text-end pe-3 py-3" style="background-color: #f8fafc !important; color: #334155 !important; font-size: 0.78rem !important; font-weight: 700 !important; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0 !important;">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody id="categoryTableBody">
                        <?php foreach ($categories as $idx => $cat): 
                            $design = getCategoryDesign($cat['name'], $idx);
                            $cleanName = htmlspecialchars(html_entity_decode($cat['name'], ENT_QUOTES, 'UTF-8'));
                            $cleanDesc = htmlspecialchars(html_entity_decode($cat['description'] ?? '', ENT_QUOTES, 'UTF-8'));
                            $jsonCat = htmlspecialchars(json_encode(array_map(function($v){ return is_string($v) ? html_entity_decode($v, ENT_QUOTES, 'UTF-8') : $v; }, $cat)));
                        ?>
                        <tr class="category-row" data-name="<?php echo strtolower($cleanName); ?>">
                            <td class="ps-3 py-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; border-radius: 10px; background: <?php echo $design['bg']; ?>; border: 1px solid <?php echo $design['border']; ?>; color: <?php echo $design['color']; ?>;">
                                        <?php echo $design['svg']; ?>
                                    </div>
                                    <strong class="cat-name text-dark" style="font-size: 0.9rem;"><?php echo ucwords($cleanName); ?></strong>
                                </div>
                            </td>
                            <td class="cat-desc text-muted py-3" style="font-size: 0.85rem;"><?php echo !empty($cleanDesc) ? $cleanDesc : '—'; ?></td>
                            <td class="py-3">
                                <span class="badge rounded-pill fw-semibold px-2 py-1" style="font-size: 0.72rem; background-color: <?php echo $cat['status'] === 'active' ? '#10b981' : '#94a3b8'; ?>; color: #ffffff;">
                                    <?php echo ucfirst($cat['status']); ?>
                                </span>
                            </td>
                            <td class="text-end pe-3 py-3">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary" style="border-radius: 6px; margin-right: 4px;" onclick="editCategory(<?php echo $jsonCat; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" style="border-radius: 6px;" onclick="deleteCategory(<?php echo $cat['id']; ?>, '<?php echo $cleanName; ?>')">
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
                <p class="text-muted">No categories found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
<div id="paginationContainer">
<?php if ($totalPages > 1): ?>
    <nav aria-label="Category pagination">
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Previous</a>
                </li>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Next</a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
<?php endif; ?>
</div>
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
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
document.getElementById('categorySearch')?.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase().trim();
    const rows = document.querySelectorAll('.category-row');
    let visibleCount = 0;
    rows.forEach(row => {
        const nameEl = row.querySelector('.cat-name');
        const descEl = row.querySelector('.cat-desc');
        const name = nameEl ? nameEl.textContent.toLowerCase() : '';
        const desc = descEl ? descEl.textContent.toLowerCase() : '';
        if (name.includes(searchTerm) || desc.includes(searchTerm)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    const paginationContainer = document.getElementById('paginationContainer');
    if (paginationContainer) {
        if (searchTerm !== '') {
            paginationContainer.style.display = 'none';
        } else {
            paginationContainer.style.display = 'block';
        }
    }
});
let sortAscending = true;
document.getElementById('sortCategoriesBtn')?.addEventListener('click', function(e) {
    e.preventDefault();
    sortAscending = !sortAscending;
    const sortIcon = document.getElementById('sortIcon');
    if (sortIcon) {
        sortIcon.className = sortAscending ? 'fas fa-sort-alpha-down' : 'fas fa-sort-alpha-up';
    }
    
    const tableBody = document.getElementById('categoryTableBody');
    if (tableBody) {
        const rows = Array.from(tableBody.querySelectorAll('tr.category-row'));
        rows.sort((a, b) => {
            const nameA = a.getAttribute('data-name') || '';
            const nameB = b.getAttribute('data-name') || '';
            return sortAscending ? nameA.localeCompare(nameB) : nameB.localeCompare(nameA);
        });
        rows.forEach(r => tableBody.appendChild(r));
    }
});

document.getElementById('categoryModal')?.addEventListener('hidden.bs.modal', function() {
    document.getElementById('categoryModalTitle').textContent = 'Add Category';
    document.getElementById('categoryAction').value = 'create';
    document.getElementById('categoryForm').reset();
});
        </script>
<?php require_once 'includes/footer.php'; ?>