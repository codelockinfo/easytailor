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
require_once '../../helpers/NotificationService.php';
require_once '../../helpers/MailService.php';

function notifyCompanyEmailChangeStatus(array $request, string $status, string $reviewNotes = ''): void {
    // 1. In-app bell notification + browser Web Push to company admins & requester
    try {
        $notificationService = new NotificationService();
        $notificationService->onEmailChangeProcessed($request, $status, $reviewNotes);
    } catch (Throwable $e) {
        error_log('Email change in-app/push notification failed: ' . $e->getMessage());
    }

    // 2. Email notification to the company
    try {
        $baseUrl = preg_replace('#/(admin|siteadmin)(/ajax)?$#', '', rtrim(APP_URL, '/'));
        $settingsUrl = $baseUrl . '/admin/company-settings.php';

        $recipients = [];
        $newEmail = trim((string)($request['new_email'] ?? ''));
        $currentEmail = trim((string)($request['current_email'] ?? ''));

        if ($status === 'approved') {
            if (!empty($newEmail) && filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $recipients[] = $newEmail;
            }
            if (!empty($currentEmail) && filter_var($currentEmail, FILTER_VALIDATE_EMAIL) && strcasecmp($currentEmail, $newEmail) !== 0) {
                $recipients[] = $currentEmail;
            }
        } else {
            if (!empty($currentEmail) && filter_var($currentEmail, FILTER_VALIDATE_EMAIL)) {
                $recipients[] = $currentEmail;
            }
        }

        if (!empty($recipients)) {
            $mailService = new MailService();
            foreach (array_unique($recipients) as $recipientEmail) {
                $sent = false;
                if ($mailService->isEnabled()) {
                    $sent = $mailService->sendEmailChangeStatusNotification([
                        'email' => $recipientEmail,
                        'ownerName' => $request['owner_name'] ?? 'Business Owner',
                        'companyName' => $request['company_name'] ?? 'Your Company',
                        'status' => $status,
                        'currentEmail' => $currentEmail,
                        'newEmail' => $newEmail,
                        'reviewNotes' => $reviewNotes,
                        'settingsUrl' => $settingsUrl
                    ]);
                }

                if (!$sent) {
                    $subject = ($status === 'approved')
                        ? 'Business Email Change Approved - ' . APP_NAME
                        : 'Business Email Change Request Rejected - ' . APP_NAME;
                    $body = ($status === 'approved')
                        ? "Hello " . ($request['owner_name'] ?? 'Business Owner') . ",\n\nYour business email change request for " . ($request['company_name'] ?? 'your company') . " to {$newEmail} has been approved and updated.\n"
                        : "Hello " . ($request['owner_name'] ?? 'Business Owner') . ",\n\nYour business email change request for " . ($request['company_name'] ?? 'your company') . " to {$newEmail} was rejected.\n";
                    if (!empty(trim($reviewNotes))) {
                        $body .= "Note: " . trim($reviewNotes) . "\n";
                    }
                    $body .= "\nView Company Settings: {$settingsUrl}\n";
                    @mail($recipientEmail, $subject, $body, "From: " . APP_NAME . " <noreply@tailorpro.in>\r\n");
                }
            }
        }
    } catch (Throwable $e) {
        error_log('Email change status email failed: ' . $e->getMessage());
    }
}

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
        notifyCompanyEmailChangeStatus($request, 'approved', $reviewNotes);

        echo json_encode([
            'success' => true,
            'message' => 'Email change request approved successfully. Company email has been updated and notification sent.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to approve request']);
    }

} elseif ($action === 'reject') {
    // Reject the request
    $rejectResult = $emailChangeRequestModel->rejectRequest($requestId, $reviewedBy, $reviewNotes);

    if ($rejectResult) {
        notifyCompanyEmailChangeStatus($request, 'rejected', $reviewNotes);

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