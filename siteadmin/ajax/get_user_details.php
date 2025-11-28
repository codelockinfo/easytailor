<?php
/**
 * Get User Details
 * Fetch detailed information for a specific user
 */

require_once '../../config/config.php';

// Check if site admin is logged in
if (!isset($_SESSION['site_admin_logged_in']) || $_SESSION['site_admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit;
}

require_once '../../config/database.php';
require_once '../../models/User.php';
require_once '../../models/Company.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $userModel = new User();
    $user = $userModel->find($userId);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Get company details if user has a company
    $companyName = null;
    if (!empty($user['company_id'])) {
        $companyModel = new Company();
        $company = $companyModel->find($user['company_id']);
        if ($company) {
            $companyName = $company['company_name'] ?? null;
        }
    }
    
    // Get creator name if available
    $createdByName = '';
    if (!empty($user['created_by'])) {
        $creatorQuery = "SELECT COALESCE(full_name, username, '') as creator_name FROM users WHERE id = :created_by";
        $stmt = $db->prepare($creatorQuery);
        $stmt->bindParam(':created_by', $user['created_by'], PDO::PARAM_INT);
        $stmt->execute();
        $creator = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($creator) {
            $createdByName = $creator['creator_name'] ?: 'N/A';
        }
    }
    
    // Format user data
    $userData = [
        'id' => $user['id'],
        'username' => $user['username'] ?? '-',
        'email' => $user['email'] ?? '-',
        'full_name' => $user['full_name'] ?? ($user['name'] ?? '-'),
        'name' => $user['full_name'] ?? ($user['name'] ?? $user['username'] ?? '-'),
        'role' => $user['role'] ?? 'staff',
        'phone' => $user['phone'] ?? '-',
        'address' => $user['address'] ?? '-',
        'status' => $user['status'] ?? 'active',
        'company_id' => $user['company_id'] ?? null,
        'company_name' => $companyName,
        'last_login' => $user['last_login'] ?? null,
        'created_at' => $user['created_at'] ?? null,
        'updated_at' => $user['updated_at'] ?? null,
        'created_by' => $user['created_by'] ?? null,
        'created_by_name' => $createdByName
    ];
    
    echo json_encode([
        'success' => true,
        'user' => $userData
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching user details: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching user details: ' . $e->getMessage()
    ]);
}
?>

