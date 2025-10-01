<?php
/**
 * AJAX Measurement Filter Endpoint
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
    
    require_once $rootDir . '/config/config.php';

    // Check if user is logged in
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    require_once $rootDir . '/models/Measurement.php';
    require_once $rootDir . '/models/Customer.php';
    require_once $rootDir . '/models/ClothType.php';

    // Get filter parameters
    $search = $_GET['search'] ?? '';
    $customer_id = $_GET['customer_id'] ?? '';
    $cloth_type_id = $_GET['cloth_type_id'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? RECORDS_PER_PAGE);

    $measurementModel = new Measurement();
    $customerModel = new Customer();
    $clothTypeModel = new ClothType();
    
    // Build conditions
    $conditions = [];
    if (!empty($customer_id)) {
        $conditions['customer_id'] = $customer_id;
    }
    if (!empty($cloth_type_id)) {
        $conditions['cloth_type_id'] = $cloth_type_id;
    }
    
    // Get measurements
    $offset = ($page - 1) * $limit;
    
    if (!empty($search)) {
        $measurements = $measurementModel->searchMeasurements($search, $limit);
        $totalMeasurements = count($measurements);
        $totalPages = 1;
    } else {
        $measurements = $measurementModel->getMeasurementsWithDetails($conditions, $limit, $offset);
        $totalMeasurements = $measurementModel->count($conditions);
        $totalPages = ceil($totalMeasurements / $limit);
    }
    
    // Get filter options
    $customers = $customerModel->findAll(['status' => 'active'], 'first_name, last_name');
    $clothTypes = $clothTypeModel->findAll(['status' => 'active'], 'name');
    
    // Format measurements for display - match original table structure exactly
    $formattedMeasurements = [];
    foreach ($measurements as $measurement) {
        $formattedMeasurements[] = [
            'id' => $measurement['id'],
            'customer_name' => htmlspecialchars($measurement['customer_name'] ?? ''),
            'customer_phone' => htmlspecialchars($measurement['customer_phone'] ?? ''),
            'cloth_type_name' => htmlspecialchars($measurement['cloth_type_name'] ?? ''),
            'measurement_data' => $measurement['measurement_data'],
            'notes' => htmlspecialchars($measurement['notes'] ?? ''),
            'created_at' => $measurement['created_at']
        ];
    }
    
    // Format filter options
    $filterOptions = [
        'customers' => array_map(function($customer) {
            return [
                'id' => $customer['id'],
                'name' => htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']),
                'phone' => htmlspecialchars($customer['phone'] ?? '')
            ];
        }, $customers),
        'cloth_types' => array_map(function($clothType) {
            return [
                'id' => $clothType['id'],
                'name' => htmlspecialchars($clothType['name']),
                'category' => htmlspecialchars($clothType['category'] ?? '')
            ];
        }, $clothTypes)
    ];
    
    echo json_encode([
        'success' => true,
        'measurements' => $formattedMeasurements,
        'filter_options' => $filterOptions,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_measurements' => $totalMeasurements,
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
