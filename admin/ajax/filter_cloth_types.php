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
    // Get the directory of this script
    $scriptDir = dirname(__FILE__);
    $rootDir = dirname($scriptDir);
    
    require_once $rootDir . '/../config/config.php';

    // Check if user is logged in
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    require_once $rootDir . '/models/ClothType.php';

    // Get filter parameters
    $category = $_GET['category'] ?? '';
    $search = $_GET['search'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? RECORDS_PER_PAGE);
    
    // Validate and fix limit parameter
    if ($limit <= 0) {
        $limit = RECORDS_PER_PAGE; // Default to records per page if limit is 0 or negative
    }
    
    // Validate page parameter
    if ($page <= 0) {
        $page = 1; // Default to page 1 if page is 0 or negative
    }

    $clothTypeModel = new ClothType();
    
    // Build conditions
    $conditions = [];
    if (!empty($category)) {
        $conditions['category'] = $category;
    }
    
    // Get cloth types
    $offset = ($page - 1) * $limit;
    
    try {
        $clothTypes = $clothTypeModel->getClothTypesWithOrderCount();
    } catch (Exception $e) {
        error_log('Error getting cloth types: ' . $e->getMessage());
        $clothTypes = [];
    }
    
    // Ensure $clothTypes is an array
    if (!is_array($clothTypes)) {
        $clothTypes = [];
    }
    
    // Filter by category if specified
    if (!empty($category)) {
        $clothTypes = array_filter($clothTypes, function($clothType) use ($category) {
            return isset($clothType['category']) && $clothType['category'] === $category;
        });
        // Re-index array after filtering
        $clothTypes = array_values($clothTypes);
    }
    
    // If search is provided, filter results manually
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
        // Re-index array after filtering
        $clothTypes = array_values($clothTypes);
    }
    
    // Apply pagination
    $totalClothTypes = count($clothTypes);
    $clothTypes = array_slice($clothTypes, $offset, $limit);
    $totalPages = $limit > 0 ? ceil($totalClothTypes / $limit) : 1;
    
    // Get filter options - get unique categories from database
    $allClothTypes = $clothTypeModel->findAll([], 'category');
    $categories = array_unique(array_column($allClothTypes, 'category'));
    $categories = array_filter($categories); // Remove empty values
    $categories = array_values($categories); // Re-index array
    
    // Format cloth types for display - match original table structure exactly
    $formattedClothTypes = [];
    foreach ($clothTypes as $clothType) {
        $formattedClothTypes[] = [
            'id' => $clothType['id'] ?? null,
            'name' => htmlspecialchars($clothType['name'] ?? ''),
            'category' => htmlspecialchars($clothType['category'] ?? ''),
            'description' => htmlspecialchars($clothType['description'] ?? ''),
            'standard_rate' => $clothType['standard_rate'] ?? null,
            'order_count' => $clothType['order_count'] ?? 0,
            'status' => $clothType['status'] ?? 'active',
            'created_at' => $clothType['created_at'] ?? '',
            'measurement_chart_image' => $clothType['measurement_chart_image'] ?? ''
        ];
    }
    
    // Ensure formattedClothTypes is always an array
    if (!is_array($formattedClothTypes)) {
        $formattedClothTypes = [];
    }
    
    // Format filter options - ensure categories is an array
    $filterOptions = [
        'categories' => array_values(array_map(function($category) {
            return htmlspecialchars($category);
        }, $categories))
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
    // Clear any output
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Filter failed: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    // Clear any output
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Filter failed: ' . $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();
