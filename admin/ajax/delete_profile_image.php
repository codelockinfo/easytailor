<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
require_once __DIR__ . '/../../config/database.php';
$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    $userId = get_user_id();
    $query = "SELECT profile_image FROM users WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && !empty($user['profile_image'])) {
        $filePath = __DIR__ . '/../../' . $user['profile_image'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
    $query = "UPDATE users SET profile_image = NULL WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
    if ($stmt->execute()) {
        $query = "SELECT full_name FROM users WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        $name = $userData['full_name'] ?? 'User';
        $nameParts = explode(' ', trim($name));
        $initials = '';
        if (count($nameParts) >= 2) {
            $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[count($nameParts) - 1], 0, 1));
        } else {
            $initials = strtoupper(substr($name, 0, 2));
        }
        
        echo json_encode(['success' => true, 'initial' => $initials]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update database']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}