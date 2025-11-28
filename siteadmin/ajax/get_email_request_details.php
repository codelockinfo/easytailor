<?php
/**
 * Get Email Request Details
 * Fetch detailed information for a specific email change request with company details
 */

require_once '../../config/config.php';

// Check if site admin is logged in
if (!isset($_SESSION['site_admin_logged_in']) || $_SESSION['site_admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$requestId = filter_input(INPUT_GET, 'request_id', FILTER_VALIDATE_INT);
if (!$requestId) {
    echo json_encode(['success' => false, 'message' => 'Request ID is required']);
    exit;
}

require_once '../../config/database.php';
require_once '../../models/EmailChangeRequest.php';
require_once '../../models/Company.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get email request with company and user details
    $query = "SELECT ecr.*, 
                     c.company_name, c.owner_name, c.business_email as company_email, 
                     c.business_phone as company_phone, c.business_address as company_address,
                     c.city as company_city, c.state as company_state, c.postal_code as company_postal_code,
                     COALESCE(requester.full_name, requester.username, '') as requested_by_username,
                     COALESCE(reviewer.full_name, reviewer.username, '') as reviewed_by_username
              FROM email_change_requests ecr
              LEFT JOIN companies c ON ecr.company_id = c.id
              LEFT JOIN users requester ON ecr.requested_by = requester.id
              LEFT JOIN users reviewer ON ecr.reviewed_by = reviewer.id
              WHERE ecr.id = :request_id
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':request_id', $requestId, PDO::PARAM_INT);
    $stmt->execute();
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'Email request not found']);
        exit;
    }
    
    // Build full company address
    $addressParts = [];
    if (!empty($request['company_address'])) $addressParts[] = $request['company_address'];
    if (!empty($request['company_city'])) $addressParts[] = $request['company_city'];
    if (!empty($request['company_state'])) $addressParts[] = $request['company_state'];
    if (!empty($request['company_postal_code'])) $addressParts[] = $request['company_postal_code'];
    $fullAddress = !empty($addressParts) ? implode(', ', $addressParts) : null;
    
    // Format request data
    $requestData = [
        'id' => $request['id'],
        'company_id' => $request['company_id'],
        'company_name' => $request['company_name'] ?? 'N/A',
        'owner_name' => $request['owner_name'] ?? 'N/A',
        'company_email' => $request['company_email'] ?? null,
        'company_phone' => $request['company_phone'] ?? null,
        'company_address' => $fullAddress,
        'current_email' => $request['current_email'] ?? '',
        'new_email' => $request['new_email'] ?? '',
        'reason' => $request['reason'] ?? '',
        'status' => $request['status'] ?? 'pending',
        'requested_by_username' => $request['requested_by_username'] ?? null,
        'reviewed_by_username' => $request['reviewed_by_username'] ?? null,
        'review_notes' => $request['review_notes'] ?? null,
        'created_at' => $request['created_at'] ?? null,
        'reviewed_at' => $request['reviewed_at'] ?? null
    ];
    
    echo json_encode([
        'success' => true,
        'request' => $requestData
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching email request details: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching email request details: ' . $e->getMessage()
    ]);
}
?>

