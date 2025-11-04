<?php
/**
 * Get Tailor Locations AJAX Endpoint
 * Returns unique cities and states for filter dropdowns
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Company.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $company = new Company($db);

    // Get unique cities and states
    $cities = $company->getUniqueCities();
    $states = $company->getUniqueStates();

    echo json_encode([
        'success' => true,
        'data' => [
            'cities' => $cities,
            'states' => $states
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching locations: ' . $e->getMessage()
    ]);
}
?>

