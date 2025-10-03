<?php
/**
 * AJAX: Get Customer Measurements
 * Tailoring Management System
 */

require_once __DIR__ . '/../../config/config.php';

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check if customer_id is provided
if (!isset($_GET['customer_id']) || empty($_GET['customer_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Customer ID is required']);
    exit;
}

$customerId = (int)$_GET['customer_id'];

try {
    require_once __DIR__ . '/../models/Measurement.php';
    $measurementModel = new Measurement();
    
    $measurements = $measurementModel->getCustomerMeasurements($customerId);
    
    // Format the data for the dropdown
    $formattedMeasurements = [];
    foreach ($measurements as $measurement) {
        $formattedMeasurements[] = [
            'id' => $measurement['id'],
            'cloth_type_name' => $measurement['cloth_type_name'],
            'created_at' => format_date($measurement['created_at'])
        ];
    }
    
    echo json_encode($formattedMeasurements);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load measurements']);
}
?>

