<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    require_once __DIR__ . '/../models/Category.php';
    $categoryModel = new Category();
    $name = sanitize_input($_POST['name'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    if (empty($name)) {
        echo json_encode(['success' => false, 'error' => 'Category name is required']);
        exit;
    }
    if ($categoryModel->nameExists($name)) {
        echo json_encode(['success' => false, 'error' => 'Category already exists']);
        exit;
    }
    $data = [
        'name' => $name,
        'description' => $description,
        'status' => 'active'
    ];
    if ($categoryModel->create($data)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save category']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>