<?php
/**
 * AJAX Profile Image Upload
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

// One-time check for profile_image column
try {
    $db->query("SELECT profile_image FROM users LIMIT 1");
} catch (Exception $e) {
    try {
        $db->exec("ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL AFTER email");
    } catch (Exception $alterError) {
        // Ignore if already exists or other error
    }
}

// Error log function for debugging
function log_upload_error($msg) {
    $logFile = __DIR__ . '/upload_error.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        log_upload_error("CSRF token validation failed");
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
        $error = $_FILES['profile_image']['error'] ?? 'No file';
        log_upload_error("Upload error code: $error");
        echo json_encode(['success' => false, 'error' => 'No image uploaded or upload error (' . $error . ')']);
        exit;
    }

    $file = $_FILES['profile_image'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowedTypes)) {
        log_upload_error("Invalid file type: " . $file['type']);
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, and GIF are allowed.']);
        exit;
    }

    if ($file['size'] > $maxSize) {
        log_upload_error("File too large: " . $file['size']);
        echo json_encode(['success' => false, 'error' => 'File too large. Maximum size is 5MB.']);
        exit;
    }

    $uploadDir = __DIR__ . '/../../assets/images/profiles/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            log_upload_error("Failed to create directory: $uploadDir");
            echo json_encode(['success' => false, 'error' => 'Internal server error: Cannot create upload directory']);
            exit;
        }
    }

    $userId = get_user_id();
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = 'profile_' . $userId . '_' . time() . '.' . $extension;
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $relativePath = 'assets/images/profiles/' . $fileName;
        
        $query = "UPDATE users SET profile_image = :image WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':image', $relativePath);
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'image_url' => '../' . $relativePath]);
        } else {
            log_upload_error("Database update failed for user $userId");
            echo json_encode(['success' => false, 'error' => 'Failed to update database']);
        }
    } else {
        log_upload_error("Failed to move file to $targetPath");
        echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
