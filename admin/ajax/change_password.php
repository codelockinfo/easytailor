<?php
ob_start();
require_once __DIR__ . '/../../config/config.php';

if (!is_logged_in()) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'redirect' => 'login.php', 'message' => 'Session expired. Please sign in again.']);
    exit;
}

require_once __DIR__ . '/../models/User.php';

$errorField = null;
$errorMsg = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $errorMsg = 'Invalid request method';
} elseif (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $errorMsg = 'Invalid security token. Please refresh the page.';
} else {
    $userModel = new User();
    $userId = get_user_id();
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $user = $userModel->find($userId);

    if ($currentPassword === '') {
        $errorField = 'current_password';
        $errorMsg = 'Please enter current password.';
    } elseif (!$user || !password_verify($currentPassword, $user['password'])) {
        $errorField = 'current_password';
        $errorMsg = 'Current password is incorrect.';
    } elseif (function_exists('validate_password_strength') && !($pwdCheck = validate_password_strength($newPassword))['valid']) {
        $errorField = 'new_password';
        $errorMsg = $pwdCheck['message'];
    } elseif (strlen($newPassword) < 6) {
        $errorField = 'new_password';
        $errorMsg = 'New password must be at least 6 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $errorField = 'confirm_password';
        $errorMsg = 'New passwords do not match!';
    } else {
        if ($userModel->updatePassword($userId, $newPassword)) {
            $success = true;
            $errorMsg = 'Password changed successfully. Redirecting to login...';

            require_once __DIR__ . '/../../controllers/AuthController.php';
            $authController = new AuthController();
            $authController->logout();
        } else {
            $errorMsg = 'Failed to change password';
        }
    }
}

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/json');
$response = [
    'success' => $success,
    'field' => $errorField,
    'message' => $errorMsg
];
if ($success) {
    $response['redirect'] = 'login.php?password_changed=1';
}
echo json_encode($response);
exit;
