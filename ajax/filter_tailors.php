<?php
/**
 * Filter Tailors AJAX Endpoint
 * Returns filtered tailor profiles based on search criteria
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Company.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if table exists
    $tableCheck = $db->query("SHOW TABLES LIKE 'companies'");
    if ($tableCheck->rowCount() === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Companies table not yet created. Please run the SQL migration.',
            'data' => []
        ]);
        exit;
    }
    
    $company = new Company($db);

    // Get filter parameters
    $filters = [
        'keyword' => $_GET['keyword'] ?? '',
        'city' => $_GET['city'] ?? '',
        'state' => $_GET['state'] ?? '',
        'min_rating' => $_GET['min_rating'] ?? '',
        'specialty' => $_GET['specialty'] ?? '',
        'sort' => $_GET['sort'] ?? 'newest',
        'order' => $_GET['order'] ?? 'DESC',
        'limit' => isset($_GET['limit']) ? (int)$_GET['limit'] : 12,
        'offset' => isset($_GET['offset']) ? (int)$_GET['offset'] : 0
    ];

    // Remove empty filters
    $filters = array_filter($filters, function($value) {
        return $value !== '' && $value !== null;
    });

    // Get companies
    $companies = $company->searchCompanies($filters);
    
    // Get total count for pagination
    $total = $company->countCompanies($filters);

    // Format companies data
    $formattedCompanies = [];
    foreach ($companies as $comp) {
        $formattedCompanies[] = [
            'id' => $comp['id'],
            'shop_name' => $comp['company_name'],
            'owner_name' => $comp['owner_name'],
            'email' => $comp['business_email'],
            'phone' => $comp['business_phone'],
            'whatsapp' => $comp['whatsapp'] ?? $comp['business_phone'],
            'address' => $comp['business_address'],
            'city' => $comp['city'] ?? '',
            'state' => $comp['state'] ?? '',
            'postal_code' => $comp['postal_code'] ?? '',
            'shop_image' => $comp['logo'] ? $comp['logo'] : 'uploads/logos/default-shop.jpg',
            'logo_image' => $comp['logo'] ? $comp['logo'] : 'uploads/logos/default-shop.jpg',
            'description' => $comp['description'] ?? '',
            'specialties' => isset($comp['specialties']) ? json_decode($comp['specialties'], true) : [],
            'working_hours' => isset($comp['working_hours']) ? json_decode($comp['working_hours'], true) : [],
            'rating' => isset($comp['rating']) ? (float)$comp['rating'] : 0.0,
            'total_reviews' => isset($comp['total_reviews']) ? (int)$comp['total_reviews'] : 0,
            'years_experience' => isset($comp['years_experience']) ? (int)$comp['years_experience'] : 0,
            'is_verified' => isset($comp['is_verified']) ? (bool)$comp['is_verified'] : false,
            'is_featured' => isset($comp['is_featured']) ? (bool)$comp['is_featured'] : false
        ];
    }

    // Calculate pagination info
    $limit = $filters['limit'] ?? 12;
    $offset = $filters['offset'] ?? 0;
    $current_page = floor($offset / $limit) + 1;
    $total_pages = ceil($total / $limit);

    echo json_encode([
        'success' => true,
        'data' => $formattedCompanies,
        'pagination' => [
            'total' => $total,
            'current_page' => $current_page,
            'total_pages' => $total_pages,
            'per_page' => $limit,
            'offset' => $offset
        ]
    ]);

} catch (Exception $e) {
    http_response_code(200); // Return 200 instead of 500 to prevent console errors
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'data' => [],
        'error_details' => $e->getFile() . ' (Line ' . $e->getLine() . ')'
    ]);
}
?>

