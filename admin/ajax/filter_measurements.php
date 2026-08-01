<?php
header('Content-Type: application/json');
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
    require_once $rootDir . '/../models/Measurement.php';
    require_once $rootDir . '/../models/Customer.php';
    require_once $rootDir . '/../models/ClothType.php';
    $search = $_GET['search'] ?? '';
    $customer_id = $_GET['customer_id'] ?? '';
    $cloth_type_id = $_GET['cloth_type_id'] ?? '';
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? RECORDS_PER_PAGE);
    if ($limit <= 0) {
        $limit = RECORDS_PER_PAGE;
    }
    if ($page <= 0) {
        $page = 1;
    }
    $measurementModel = new Measurement();
    $customerModel = new Customer();
    $clothTypeModel = new ClothType();
    $conditions = [];
    if (!empty($customer_id)) {
        $conditions['customer_id'] = (int)$customer_id;
    }
    if (!empty($cloth_type_id)) {
        $conditions['cloth_type_id'] = (int)$cloth_type_id;
    }
    if (!empty($start_date)) {
        $conditions['start_date'] = $start_date;
    }
    if (!empty($end_date)) {
        $conditions['end_date'] = $end_date;
    }
    error_log('Filter parameters - search: ' . $search . ', customer_id: ' . $customer_id . ', cloth_type_id: ' . $cloth_type_id);
    $offset = ($page - 1) * $limit;
    if ($limit == 0) {
        $measurements = [];
        $totalMeasurements = 0;
        $totalPages = 1;
    } else {
        try {
            $allMeasurements = $measurementModel->getMeasurementsWithDetails($conditions, 10000, 0);
        } catch (Exception $e) {
            error_log('Error getting measurements: ' . $e->getMessage());
            $allMeasurements = [];
        }
        error_log('Filter measurements - Conditions: ' . json_encode($conditions));
        error_log('Filter measurements - All measurements count: ' . (is_array($allMeasurements) ? count($allMeasurements) : 'not an array - type: ' . gettype($allMeasurements)));
        
        if (!is_array($allMeasurements)) {
            error_log('Warning: getMeasurementsWithDetails did not return an array. Type: ' . gettype($allMeasurements));
            $allMeasurements = [];
        }
        if (!empty($search)) {
            $searchLower = strtolower(trim($search));
            $allMeasurements = array_filter($allMeasurements, function($m) use ($searchLower) {
                $customerName = strtolower(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')));
                $clothType = strtolower($m['cloth_type_name'] ?? '');
                $notes = strtolower($m['notes'] ?? '');
                $customerCode = strtolower($m['customer_code'] ?? '');
                $mName = strtolower($m['name'] ?? '');
                $mEmail = strtolower($m['email'] ?? '');
                $mPhone = strtolower($m['phone_number'] ?? '');
                $mAddress = strtolower($m['address'] ?? '');
                return strpos($customerName, $searchLower) !== false ||
                       strpos($clothType, $searchLower) !== false ||
                       strpos($notes, $searchLower) !== false ||
                       strpos($customerCode, $searchLower) !== false ||
                       strpos($mName, $searchLower) !== false ||
                       strpos($mEmail, $searchLower) !== false ||
                       strpos($mPhone, $searchLower) !== false ||
                       strpos($mAddress, $searchLower) !== false;
            });
            $allMeasurements = array_values($allMeasurements);
            error_log('Filter measurements - After search filter count: ' . count($allMeasurements));
        }
        $totalMeasurements = count($allMeasurements);
        $measurements = array_slice($allMeasurements, $offset, $limit);
        $totalPages = $limit > 0 ? ceil($totalMeasurements / $limit) : 1;
        
        error_log('Filter measurements - Final measurements count: ' . count($measurements));
        error_log('Filter measurements - Total measurements: ' . $totalMeasurements);
    }
    if (!is_array($measurements)) {
        $measurements = [];
    }
    $customers = $customerModel->findAll(['status' => 'active'], 'first_name, last_name');
    $clothTypes = $clothTypeModel->findAll(['status' => 'active'], 'name');
    $formattedMeasurements = [];
    foreach ($measurements as $measurement) {
        $customerName = !empty($measurement['name']) ? $measurement['name'] : trim(($measurement['first_name'] ?? '') . ' ' . ($measurement['last_name'] ?? ''));
        $customerPhone = !empty($measurement['phone_number']) ? $measurement['phone_number'] : ($measurement['customer_phone'] ?? ($measurement['customer_code'] ?? ''));
        $formattedMeasurements[] = [
            'id' => $measurement['id'],
            'customer_id' => $measurement['customer_id'] ?? null,
            'cloth_type_id' => $measurement['cloth_type_id'] ?? null,
            'customer_name' => $customerName,
            'customer_code' => $measurement['customer_code'] ?? '',
            'customer_phone' => $customerPhone,
            'cloth_type_name' => $measurement['cloth_type_name'] ?? '',
            'measurement_data' => $measurement['measurement_data'],
            'notes' => $measurement['notes'] ?? '',
            'created_at' => $measurement['created_at']
        ];
    }
    $filterOptions = [
        'customers' => array_map(function($customer) {
            return [
                'id' => $customer['id'],
                'name' => $customer['first_name'] . ' ' . $customer['last_name'],
                'phone' => $customer['phone'] ?? ''
            ];
        }, $customers),
        'cloth_types' => array_map(function($clothType) {
            return [
                'id' => $clothType['id'],
                'name' => $clothType['name'],
                'category' => $clothType['category'] ?? ''
            ];
        }, $clothTypes)
    ];
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