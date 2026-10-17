<?php

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controllers/AuthController.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = $_POST;
if (empty($input)) {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $input = $json;
        }
    }
}
$csrfToken = $input['csrf_token'] ?? '';
if (!verify_csrf_token($csrfToken)) {
    echo json_encode(['success' => false, 'message' => 'Security token expired. Please refresh the page and try again.']);
    exit;
}

$action = sanitize_input($input['action'] ?? '');
$email = trim(sanitize_input($input['email'] ?? ''));
$code = trim(sanitize_input($input['code'] ?? ''));
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';

$authController = new AuthController();

if ($action === 'login_password') {
    $username = trim(sanitize_input($input['username'] ?? ''));
    $password = $input['password'] ?? '';
    $result = $authController->loginWithPasswordOtp($username, $password, $clientIp);
    echo json_encode($result);
    exit;
}

if ($action === 'send_otp' || $action === 'resend_otp') {
    $result = $authController->requestLoginOtp($email, $clientIp);
    echo json_encode($result);
    exit;
}

if ($action === 'verify_otp') {
    $result = $authController->verifyLoginOtp($email, $code);
    echo json_encode($result);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action requested.']);
exit;
