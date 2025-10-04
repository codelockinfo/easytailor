<?php
/**
 * Save Language Preference Endpoint
 * Tailoring Management System
 */

header('Content-Type: application/json');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['language'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Language parameter required']);
    exit;
}

$language = $input['language'];

// Validate language code
$supportedLanguages = [
    'en', 'hi', 'gu', 'mr', 'ta', 'te', 'kn', 'ml', 'bn', 'pa', 'ur', 'or', 'as'
];

if (!in_array($language, $supportedLanguages)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unsupported language']);
    exit;
}

// Save to session
$_SESSION['language'] = $language;

// Clear cached translations for this language
if (isset($_SESSION['translations'][$language])) {
    unset($_SESSION['translations'][$language]);
}

// Return success response
echo json_encode([
    'success' => true,
    'language' => $language,
    'message' => 'Language preference saved successfully'
]);
?>
