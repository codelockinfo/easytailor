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
    
    require_once $rootDir . '/../config/config.php';

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
    
    // Validate and fix limit parameter
    if ($limit <= 0) {
        $limit = RECORDS_PER_PAGE; // Default to records per page if limit is 0 or negative
    }
    
    // Validate page parameter
    if ($page <= 0) {
        $page = 1; // Default to page 1 if page is 0 or negative
    }

    $measurementModel = new Measurement();
    $customerModel = new Customer();
    $clothTypeModel = new ClothType();
    
    // Build conditions
    $conditions = [];
    if (!empty($customer_id)) {
        $conditions['customer_id'] = (int)$customer_id;
    }
    if (!empty($cloth_type_id)) {
        $conditions['cloth_type_id'] = (int)$cloth_type_id;
    }
    
    // Debug: Log filter parameters
    error_log('Filter parameters - search: ' . $search . ', customer_id: ' . $customer_id . ', cloth_type_id: ' . $cloth_type_id);
    
    // Get measurements
    $offset = ($page - 1) * $limit;
    
    // If limit is 0, we only need filter options, not measurement data
    if ($limit == 0) {
        $measurements = [];
        $totalMeasurements = 0;
        $totalPages = 1;
    } else {
        // Get all measurements first (with filters if any)
        // Use empty array if no conditions to get all measurements
        try {
            $allMeasurements = $measurementModel->getMeasurementsWithDetails($conditions, 10000, 0);
        } catch (Exception $e) {
            error_log('Error getting measurements: ' . $e->getMessage());
            $allMeasurements = [];
        }
        
        // Debug: Log the count of measurements retrieved
        error_log('Filter measurements - Conditions: ' . json_encode($conditions));
        error_log('Filter measurements - All measurements count: ' . (is_array($allMeasurements) ? count($allMeasurements) : 'not an array - type: ' . gettype($allMeasurements)));
        
        // Ensure $allMeasurements is an array
        if (!is_array($allMeasurements)) {
            error_log('Warning: getMeasurementsWithDetails did not return an array. Type: ' . gettype($allMeasurements));
            $allMeasurements = [];
        }
        
        // Apply search filter if provided
        if (!empty($search)) {
            $searchLower = strtolower(trim($search));
            $allMeasurements = array_filter($allMeasurements, function($m) use ($searchLower) {
                $customerName = strtolower(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')));
                $clothType = strtolower($m['cloth_type_name'] ?? '');
                $notes = strtolower($m['notes'] ?? '');
                $customerCode = strtolower($m['customer_code'] ?? '');
                
                return strpos($customerName, $searchLower) !== false ||
                       strpos($clothType, $searchLower) !== false ||
                       strpos($notes, $searchLower) !== false ||
                       strpos($customerCode, $searchLower) !== false;
            });
            // Re-index array after filtering
            $allMeasurements = array_values($allMeasurements);
            error_log('Filter measurements - After search filter count: ' . count($allMeasurements));
        }
        
        // Apply pagination
        $totalMeasurements = count($allMeasurements);
        $measurements = array_slice($allMeasurements, $offset, $limit);
        $totalPages = $limit > 0 ? ceil($totalMeasurements / $limit) : 1;
        
        error_log('Filter measurements - Final measurements count: ' . count($measurements));
        error_log('Filter measurements - Total measurements: ' . $totalMeasurements);
    }
    
    // Ensure measurements is always an array
    if (!is_array($measurements)) {
        $measurements = [];
    }
    
    // Get filter options
    $customers = $customerModel->findAll(['status' => 'active'], 'first_name, last_name');
    $clothTypes = $clothTypeModel->findAll(['status' => 'active'], 'name');
    
    // Format measurements for display - match original table structure exactly
    $formattedMeasurements = [];
    foreach ($measurements as $measurement) {
        // Combine first_name and last_name to create customer_name
        $customerName = trim(($measurement['first_name'] ?? '') . ' ' . ($measurement['last_name'] ?? ''));
        
        $formattedMeasurements[] = [
            'id' => $measurement['id'],
            'customer_id' => $measurement['customer_id'] ?? null,
            'cloth_type_id' => $measurement['cloth_type_id'] ?? null,
            'customer_name' => htmlspecialchars($customerName),
            'customer_code' => htmlspecialchars($measurement['customer_code'] ?? ''),
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
    
    // Ensure measurements is always an array in the response
    if (!isset($formattedMeasurements) || !is_array($formattedMeasurements)) {
        $formattedMeasurements = [];
    }
    
    $response = [
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
    ];
    
    error_log('Filter measurements - Response: ' . json_encode([
        'success' => $response['success'],
        'measurements_count' => count($response['measurements']),
        'total_measurements' => $response['pagination']['total_measurements']
    ]));
    
    echo json_encode($response);
    
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
