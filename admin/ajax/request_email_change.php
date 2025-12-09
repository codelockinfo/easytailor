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
require_once '../../helpers/MailService.php';

$dashboardLink = rtrim(APP_URL, '/') . '/siteadmin/index.php';

/**
 * Fallback email notification using PHP mail()
 */
if (!function_exists('sendEmailChangeNotificationFallback')) {
function sendEmailChangeNotificationFallback($to, $payload) {
    $companyName = htmlspecialchars($payload['companyName'], ENT_QUOTES, 'UTF-8');
    $ownerName = htmlspecialchars($payload['ownerName'], ENT_QUOTES, 'UTF-8');
    $currentEmail = htmlspecialchars($payload['currentEmail'], ENT_QUOTES, 'UTF-8');
    $newEmail = htmlspecialchars($payload['newEmail'], ENT_QUOTES, 'UTF-8');
    $reason = nl2br(htmlspecialchars($payload['reason'], ENT_QUOTES, 'UTF-8'));
    $dashboardUrl = htmlspecialchars($payload['dashboardUrl'], ENT_QUOTES, 'UTF-8');

    $subject = 'New Email Change Request - ' . APP_NAME;
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background: #f7f7f7; }
            .card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 24px; }
            .title { font-size: 20px; margin-bottom: 16px; color: #333333; }
            .info-list { list-style: none; padding: 0; margin: 0 0 16px 0; }
            .info-list li { margin-bottom: 6px; }
            .reason { background: #f1f3f5; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
            .button { display: inline-block; padding: 12px 24px; background: #667eea; color: #ffffff; border-radius: 6px; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class='card'>
            <h2 class='title'>New Email Change Request</h2>
            <p><strong>{$companyName}</strong> submitted a new email change request.</p>
            <ul class='info-list'>
                <li><strong>Owner:</strong> {$ownerName}</li>
                <li><strong>Current Email:</strong> {$currentEmail}</li>
                <li><strong>Requested Email:</strong> {$newEmail}</li>
            </ul>
            <div class='reason'>
                <strong>Reason Provided:</strong>
                <p>{$reason}</p>
            </div>
            <p style='text-align:center;'>
                <a href='{$dashboardUrl}' class='button'>Review Request</a>
            </p>
            <p style='font-size:12px; color:#6c757d; text-align:center;'>This is an automated notification from " . APP_NAME . ".</p>
        </div>
    </body>
    </html>";

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8\r\n";
    $headers .= "From: " . APP_NAME . " <noreply@tailoring.com>\r\n";

    return @mail($to, $subject, $message, $headers);
}
}

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

$companyName = $company['company_name'] ?? 'Unknown Company';
$ownerName = $company['owner_name'] ?? 'Unknown Owner';
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
    // Notify site admins via email (multiple recipients supported)
    $adminEmails = [
        'codelock2021@gmail.com',
        'dipak.codelock99@gmail.com'
    ];

    $mailService = new MailService();

    foreach ($adminEmails as $adminEmail) {
        $adminEmail = trim($adminEmail);
        if (empty($adminEmail)) {
            continue;
        }

        $notificationSent = false;
        $mailError = '';

        if ($mailService->isEnabled()) {
            $notificationSent = $mailService->sendEmailChangeRequestNotification([
                'email' => $adminEmail,
                'name' => 'Site Admin',
                'companyName' => $companyName,
                'ownerName' => $ownerName,
                'currentEmail' => $currentEmail,
                'newEmail' => $newEmail,
                'reason' => $reason,
                'dashboardUrl' => $dashboardLink
            ]);
            $mailError = $mailService->getLastError();
        }

        if (!$notificationSent) {
            $fallbackPayload = [
                'companyName' => $companyName,
                'ownerName' => $ownerName,
                'currentEmail' => $currentEmail,
                'newEmail' => $newEmail,
                'reason' => $reason,
                'dashboardUrl' => $dashboardLink
            ];

            $mailResult = sendEmailChangeNotificationFallback($adminEmail, $fallbackPayload);
            if (!$mailResult) {
                error_log('Email change request notification failed for ' . $adminEmail . ': ' . ($mailError ?: 'mail() returned false'));
            }
        }
    }

    // Track request email change event
    require_once '../../helpers/GA4Helper.php';
    $ga4Event = GA4Helper::trackRequestEmailChange(get_user_id());
    
    echo json_encode([
        'success' => true, 
        'message' => 'Email change request submitted successfully. You will be notified once it\'s reviewed.',
        'ga4_event' => $ga4Event
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to submit email change request. Please try again.']);
}
?>

