<?php
/**
 * Process Email Change Request (Approve/Reject)
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

require_once '../../models/EmailChangeRequest.php';
require_once '../../models/Company.php';

$emailChangeRequestModel = new EmailChangeRequest();
$companyModel = new Company();

$action = $_POST['action'] ?? '';
$requestId = (int)($_POST['request_id'] ?? 0);
$reviewNotes = sanitize_input($_POST['review_notes'] ?? '');

if (!$requestId) {
    echo json_encode(['success' => false, 'message' => 'Request ID is required']);
    exit;
}

// Get the request details
$request = $emailChangeRequestModel->getRequestWithDetails($requestId);

if (!$request) {
    echo json_encode(['success' => false, 'message' => 'Request not found']);
    exit;
}

// Check if request is already processed
if ($request['status'] !== 'pending') {
    echo json_encode(['success' => false, 'message' => 'This request has already been processed']);
    exit;
}

// Site admin ID (using a fixed ID or session)
$reviewedBy = 0; // Site admin doesn't have a user ID, use 0 or create a site_admin_users table

if ($action === 'approve') {
    // Check if new email already exists for another company
    if ($companyModel->emailExists($request['new_email'], $request['company_id'])) {
        echo json_encode(['success' => false, 'message' => 'This email address is already in use by another company']);
        exit;
    }

    // Update company email
    $updateResult = $companyModel->update($request['company_id'], [
        'business_email' => $request['new_email']
    ]);

    if (!$updateResult) {
        echo json_encode(['success' => false, 'message' => 'Failed to update company email']);
        exit;
    }

    // Approve the request
    $approveResult = $emailChangeRequestModel->approveRequest($requestId, $reviewedBy, $reviewNotes);

    if ($approveResult) {
        echo json_encode([
            'success' => true,
            'message' => 'Email change request approved successfully. Company email has been updated.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to approve request']);
    }

} elseif ($action === 'reject') {
    // Reject the request
    $rejectResult = $emailChangeRequestModel->rejectRequest($requestId, $reviewedBy, $reviewNotes);

    if ($rejectResult) {
        echo json_encode([
            'success' => true,
            'message' => 'Email change request rejected successfully.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reject request']);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>

