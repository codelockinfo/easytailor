<?php
/**
 * Get Testimonial Details
 * Site Admin
 */

require_once '../../config/config.php';

// Check if site admin is logged in
if (!isset($_SESSION['site_admin_logged_in']) || $_SESSION['site_admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$testimonialId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$testimonialId) {
    echo json_encode(['success' => false, 'message' => 'Testimonial ID is required']);
    exit;
}

try {
    // Get testimonial with company and user info
    $query = "SELECT 
                t.*,
                c.company_name,
                u.full_name as tailor_name
              FROM testimonials t 
              LEFT JOIN companies c ON t.company_id = c.id 
              LEFT JOIN users u ON t.user_id = u.id 
              WHERE t.id = :id 
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $testimonialId, PDO::PARAM_INT);
    $stmt->execute();
    $testimonial = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$testimonial) {
        echo json_encode(['success' => false, 'message' => 'Testimonial not found']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'testimonial' => $testimonial
    ]);

} catch (PDOException $e) {
    error_log('Get testimonial details error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log('Get testimonial details error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>

