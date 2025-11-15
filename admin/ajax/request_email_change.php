<?php
/**
 * Handle Email Change Request
 * Tailoring Management System
 */

require_once '../../config/config.php';
require_login();
require_role('admin');

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

$companyId = (int)($_POST['company_id'] ?? 0);
$newEmail = sanitize_input($_POST['new_email'] ?? '');
$reason = sanitize_input($_POST['change_reason'] ?? '');

// Validate inputs
if (!$companyId) {
    echo json_encode(['success' => false, 'message' => 'Company ID is required']);
    exit;
}

if (empty($newEmail) || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid email address']);
    exit;
}

if (empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a reason for the email change']);
    exit;
}

// Get company details
$company = $companyModel->find($companyId);
if (!$company) {
    echo json_encode(['success' => false, 'message' => 'Company not found']);
    exit;
}

$currentEmail = $company['business_email'] ?? '';

// Check if new email is different from current
if ($newEmail === $currentEmail) {
    echo json_encode(['success' => false, 'message' => 'New email must be different from current email']);
    exit;
}

// Check if new email already exists
if ($companyModel->emailExists($newEmail, $companyId)) {
    echo json_encode(['success' => false, 'message' => 'This email address is already in use by another company']);
    exit;
}

// Check if there's already a pending request
if ($emailChangeRequestModel->hasPendingRequest($companyId)) {
    echo json_encode(['success' => false, 'message' => 'You already have a pending email change request. Please wait for it to be reviewed.']);
    exit;
}

// Create email change request
$requestData = [
    'company_id' => $companyId,
    'current_email' => $currentEmail,
    'new_email' => $newEmail,
    'reason' => $reason,
    'status' => 'pending',
    'requested_by' => get_user_id()
];

$requestId = $emailChangeRequestModel->createRequest($requestData);

if ($requestId) {
    echo json_encode([
        'success' => true, 
        'message' => 'Email change request submitted successfully. You will be notified once it\'s reviewed.'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to submit email change request. Please try again.']);
}
?>

