<?php
/**
 * Get Contact Messages AJAX Endpoint
 * Returns all contact messages with statistics
 */

header('Content-Type: application/json');

require_once '../../config/config.php';

// Check if site admin is logged in
if (!isset($_SESSION['site_admin_logged_in']) || $_SESSION['site_admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Connect to database
require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

try {
    // Get all contact messages
    $query = "SELECT * FROM user_contact ORDER BY created_date DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $totalMessages = count($messages);
    $loggedInMessages = count(array_filter($messages, function($m) { return $m['user_logged'] == 1; }));
    $guestMessages = $totalMessages - $loggedInMessages;
    
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'stats' => [
            'total' => $totalMessages,
            'logged_in' => $loggedInMessages,
            'guests' => $guestMessages
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load contact messages: ' . $e->getMessage()
    ]);
}
