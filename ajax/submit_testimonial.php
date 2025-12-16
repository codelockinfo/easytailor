<?php
/**
 * Submit Testimonial AJAX Endpoint
 * Handles testimonial form submission
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';

try {
    // Get POST data
    $user_name = isset($_POST['user_name']) ? trim($_POST['user_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $company_id = isset($_POST['company_id']) ? (int)$_POST['company_id'] : 0;
    $user_id = isset($_POST['user_id']) && !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
    
    // Get star value - ensure it's properly captured
    $star = 5; // default
    if (isset($_POST['star'])) {
        $star = (int)$_POST['star'];
        // Ensure star is between 1 and 5
        if ($star < 1 || $star > 5) {
            $star = 5;
        }
    }
    
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
    
    // Log the received star value for debugging
    error_log("Testimonial submission - Star value received: " . $star . " (POST value: " . (isset($_POST['star']) ? $_POST['star'] : 'not set') . ")");
    
    // Validation
    if (empty($user_name)) {
        throw new Exception('Name is required');
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Valid email is required');
    }
    
    if (empty($company_id) || $company_id <= 0) {
        throw new Exception('Please select a shop');
    }
    
    if ($star < 1 || $star > 5) {
        throw new Exception('Rating must be between 1 and 5');
    }
    
    if (empty($comment)) {
        throw new Exception('Comment is required');
    }
    
    // Get database connection
    $database = new Database();
    $db = $database->getConnection();
    
    // Validate user_id if provided - check if it exists in users table
    if ($user_id !== null && $user_id > 0) {
        $userCheckQuery = "SELECT id FROM users WHERE id = :user_id AND status = 'active' LIMIT 1";
        $userCheckStmt = $db->prepare($userCheckQuery);
        $userCheckStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $userCheckStmt->execute();
        if (!$userCheckStmt->fetch()) {
            // User doesn't exist or is inactive, set to null
            $user_id = null;
        }
    } else {
        // Ensure null is used instead of 0 or empty string
        $user_id = null;
    }
    
    // Get owner name from company
    $owner_name = null;
    $companyQuery = "SELECT owner_name FROM companies WHERE id = :company_id LIMIT 1";
    $companyStmt = $db->prepare($companyQuery);
    $companyStmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
    $companyStmt->execute();
    $company = $companyStmt->fetch(PDO::FETCH_ASSOC);
    if ($company) {
        $owner_name = $company['owner_name'];
    }
    
    // Insert testimonial
    $query = "INSERT INTO testimonials 
              (user_name, email, company_id, owner_name, user_id, star, comment, status, created_at) 
              VALUES 
              (:user_name, :email, :company_id, :owner_name, :user_id, :star, :comment, 'pending', NOW())";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_name', $user_name, PDO::PARAM_STR);
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
    $stmt->bindParam(':owner_name', $owner_name, PDO::PARAM_STR);
    // Bind user_id as NULL if it's null, otherwise as INT
    if ($user_id === null) {
        $stmt->bindValue(':user_id', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    }
    $stmt->bindParam(':star', $star, PDO::PARAM_INT);
    $stmt->bindParam(':comment', $comment, PDO::PARAM_STR);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Thank you for your feedback! Your review has been submitted and will be reviewed.'
        ]);
    } else {
        throw new Exception('Failed to save testimonial');
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

