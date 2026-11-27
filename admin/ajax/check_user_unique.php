<?php
header('Content-Type: application/json');
ob_start();

require_once __DIR__ . '/../../config/config.php';

if (!is_logged_in() || !has_role('admin')) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token']);
    exit;
}

require_once __DIR__ . '/../models/User.php';

$userModel = new User();
$userId = (int)($_POST['user_id'] ?? 0);
$excludeId = $userId > 0 ? $userId : null;
$email = trim(sanitize_input($_POST['email'] ?? ''));
$username = trim(sanitize_input($_POST['username'] ?? ''));

$emailExists = ($email !== '') ? $userModel->emailExists($email, $excludeId) : false;
$usernameExists = ($username !== '') ? $userModel->usernameExists($username, $excludeId) : false;

while (ob_get_level() > 0) {
    ob_end_clean();
}

echo json_encode([
    'success' => true,
    'email_exists' => $emailExists,
    'username_exists' => $usernameExists
]);
exit;
