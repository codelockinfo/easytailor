<?php
/**
 * Get Measurement Details via AJAX
 * Returns measurement data with customer and cloth type information
 */

require_once __DIR__ . '/../../config/config.php';
require_login();

header('Content-Type: application/json');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Measurement ID is required']);
    exit;
}

require_once __DIR__ . '/../models/Measurement.php';
require_once __DIR__ . '/../models/Customer.php';
require_once __DIR__ . '/../models/ClothType.php';

$measurementId = (int)$_GET['id'];
$measurementModel = new Measurement();
$customerModel = new Customer();
$clothTypeModel = new ClothType();

// Get measurement with details
$measurements = $measurementModel->getMeasurementsWithDetails(['m.id' => $measurementId], 1);

if (empty($measurements)) {
    echo json_encode(['success' => false, 'message' => 'Measurement not found']);
    exit;
}

$measurement = $measurements[0];

// Decode JSON data
$measurement['measurement_data'] = json_decode($measurement['measurement_data'], true);
$measurement['images'] = json_decode($measurement['images'], true);

// Get cloth type to get the measurement chart
$clothType = $clothTypeModel->find($measurement['cloth_type_id']);
$measurement['measurement_chart'] = $clothType['measurement_chart_image'] ? '../' . ltrim($clothType['measurement_chart_image'], './') : '';

echo json_encode([
    'success' => true,
    'measurement' => $measurement
]);
?>

