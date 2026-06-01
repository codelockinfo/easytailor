<?php
/**
 * AJAX Cloth Type Filter Endpoint
 * Tailoring Management System
 */

// Set content type to JSON
header('Content-Type: application/json');

// Start output buffering to catch any errors
ob_start();

try {
    $scriptDir = dirname(__FILE__);
    $rootDir = dirname($scriptDir);
    
    require_once $rootDir . '/../config/config.php';
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    require_once $rootDir . '/models/ClothType.php';
    require_once $rootDir . '/models/Category.php';
    $categoryModel = new Category();
    $category = $_GET['category'] ?? '';
    $search = $_GET['search'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? RECORDS_PER_PAGE);
    if ($limit <= 0) {
        $limit = RECORDS_PER_PAGE; 
    }
    if ($page <= 0) {
        $page = 1;
    }

    $clothTypeModel = new ClothType();
    $conditions = [];
    if (!empty($category)) {
        $conditions['category'] = $category;
    }
    $offset = ($page - 1) * $limit;
    
    try {
        $clothTypes = $clothTypeModel->getClothTypesWithOrderCount();
    } catch (Exception $e) {
        error_log('Error getting cloth types: ' . $e->getMessage());
        $clothTypes = [];
    }
    
    if (!is_array($clothTypes)) {
        $clothTypes = [];
    }
    
    if (!empty($category)) {
        $clothTypes = array_filter($clothTypes, function($clothType) use ($category) {
            return isset($clothType['category']) && $clothType['category'] === $category;
        });
        $clothTypes = array_values($clothTypes);
    }
    if (!empty($search)) {
        $searchLower = strtolower(trim($search));
        $clothTypes = array_filter($clothTypes, function($clothType) use ($searchLower) {
            $name = strtolower($clothType['name'] ?? '');
            $cat = strtolower($clothType['category'] ?? '');
            $desc = strtolower($clothType['description'] ?? '');
            
            return strpos($name, $searchLower) !== false ||
                   strpos($cat, $searchLower) !== false ||
                   strpos($desc, $searchLower) !== false;
        });
        $clothTypes = array_values($clothTypes);
    }
    $totalClothTypes = count($clothTypes);
    $clothTypes = array_slice($clothTypes, $offset, $limit);
    $totalPages = $limit > 0 ? ceil($totalClothTypes / $limit) : 1;
    $current_company_id = get_company_id();
    $dbCategories = $categoryModel->findAll(['status' => 'active', 'company_id' => $current_company_id], 'name ASC');
    $categories = array_unique(array_column($dbCategories, 'name'));
    $categories = array_filter($categories); 
    $categories = array_values($categories); 
    
    $formattedClothTypes = [];
    foreach ($clothTypes as $clothType) {
        $formattedClothTypes[] = [
            'id' => $clothType['id'] ?? null,
            'name' => $clothType['name'] ?? '',
            'category' => $clothType['category'] ?? '',
            'description' => $clothType['description'] ?? '',
            'standard_rate' => $clothType['standard_rate'] ?? null,
            'order_count' => $clothType['order_count'] ?? 0,
            'status' => $clothType['status'] ?? 'active',
            'created_at' => $clothType['created_at'] ?? '',
            'measurement_chart_image' => $clothType['measurement_chart_image'] ?? ''
        ];
    }
    if (!is_array($formattedClothTypes)) {
        $formattedClothTypes = [];
    }
    $filterOptions = [
        'categories' => array_values($categories)
    ];
    
    echo json_encode([
        'success' => true,
        'cloth_types' => $formattedClothTypes,
        'filter_options' => $filterOptions,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_cloth_types' => $totalClothTypes,
            'has_previous' => $page > 1,
            'has_next' => $page < $totalPages
        ]
    ]);
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Filter failed: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Filter failed: ' . $e->getMessage()
    ]);
}
ob_end_flush();
