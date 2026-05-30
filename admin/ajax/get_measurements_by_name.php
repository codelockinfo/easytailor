<?php
/**
 * AJAX: Get Measurements by Customer Name
 * Returns measurements for a specific customer name from the measurements table
 */

require_once __DIR__ . '/../../config/config.php';

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check if name is provided
if (!isset($_GET['name']) || empty($_GET['name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Customer name is required']);
    exit;
}

$name = sanitize_input($_GET['name']);

try {
    require_once __DIR__ . '/../../models/Measurement.php';
    $measurementModel = new Measurement();
    
    $measurements = $measurementModel->getMeasurementsByName($name);
    
    // Format the data for the dropdown
    $formattedMeasurements = [];
    foreach ($measurements as $measurement) {
        $formattedMeasurements[] = [
            'id' => $measurement['id'],
            'cloth_type_id' => $measurement['cloth_type_id'],
            'cloth_type_name' => $measurement['cloth_type_name'],
            'name' => $measurement['name'],
            'created_at' => format_date($measurement['created_at'])
        ];
    }
    
    echo json_encode($formattedMeasurements);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load measurements']);
}
?>
