<?php
/**
 * Process Testimonial (Approve/Reject)
 * Site Admin
 */

require_once '../../config/config.php';

// Check if site admin is logged in
if (!isset($_SESSION['site_admin_logged_in']) || $_SESSION['site_admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$action = $_POST['action'] ?? '';
$testimonialId = (int)($_POST['testimonial_id'] ?? 0);
$reviewNotes = sanitize_input($_POST['review_notes'] ?? '');

if (!$testimonialId) {
    echo json_encode(['success' => false, 'message' => 'Testimonial ID is required']);
    exit;
}

try {
    // Get the testimonial details
    $query = "SELECT * FROM testimonials WHERE id = :id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $testimonialId, PDO::PARAM_INT);
    $stmt->execute();
    $testimonial = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$testimonial) {
        echo json_encode(['success' => false, 'message' => 'Testimonial not found']);
        exit;
    }

    // Allow status changes even if already processed (admin can change status)
    // No need to check if already processed

    // Site admin ID (using a fixed ID or session)
    $reviewedBy = 0; // Site admin doesn't have a user ID, use 0

    if ($action === 'approve') {
        // Update testimonial status to approved
        $updateQuery = "UPDATE testimonials SET status = 'approved', updated_at = NOW() WHERE id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':id', $testimonialId, PDO::PARAM_INT);
        
        if ($updateStmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Testimonial approved successfully. It will now be displayed on the frontend.'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to approve testimonial']);
        }

    } elseif ($action === 'reject') {
        // Update testimonial status to rejected
        $updateQuery = "UPDATE testimonials SET status = 'rejected', updated_at = NOW() WHERE id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':id', $testimonialId, PDO::PARAM_INT);
        
        if ($updateStmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Testimonial rejected successfully.'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to reject testimonial']);
        }

    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (PDOException $e) {
    error_log('Testimonial process error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log('Testimonial process error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>

