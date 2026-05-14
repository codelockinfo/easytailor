<?php
/**
 * AJAX Delete Profile Image
 * Tailoring Management System
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Database connection
require_once __DIR__ . '/../../config/database.php';
$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $userId = get_user_id();

    // Get current image path to delete file
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

    // Update database
    $query = "UPDATE users SET profile_image = NULL WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        // Get first letter of name for the placeholder
        $query = "SELECT full_name FROM users WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        $initial = strtoupper(substr($userData['full_name'] ?? 'U', 0, 1));
        
        echo json_encode(['success' => true, 'initial' => $initial]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update database']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
