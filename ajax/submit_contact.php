<?php
/**
 * Contact Form Submission Handler
 * Handles contact form submissions from contact.php
 */

header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Start session to check user login status
session_start();

// Get form data
$name = trim($_POST['name'] ?? '');
$emailId = trim($_POST['emailId'] ?? '');
$message = trim($_POST['message'] ?? '');
$user_logged = isset($_POST['user_logged']) ? (int)$_POST['user_logged'] : 0;
$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;

// Validate required fields
$errors = [];

if (empty($name)) {
    $errors[] = 'Name is required';
} elseif (strlen($name) > 100) {
    $errors[] = 'Name must be 100 characters or less';
}

if (empty($emailId)) {
    $errors[] = 'Email is required';
} elseif (!filter_var($emailId, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email address';
} elseif (strlen($emailId) > 150) {
    $errors[] = 'Email must be 150 characters or less';
}

if (empty($message)) {
    $errors[] = 'Message is required';
}

// If there are validation errors, return them
if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
    exit;
}

// Sanitize inputs
$name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$emailId = filter_var($emailId, FILTER_SANITIZE_EMAIL);
$message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

// Check if user is actually logged in (verify session)
if ($user_logged && isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    $user_logged = 1;
} else {
    $user_id = null;
    $user_logged = 0;
}

try {
    // Connect to database
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Check if table exists
    $tableName = 'user_contact';
    $tableCheck = $db->query("SHOW TABLES LIKE '$tableName'");
    if ($tableCheck->rowCount() === 0) {
        throw new Exception('Contact messages table (user_contact) does not exist. Please create the table first.');
    }
    
    // Insert contact message
    $query = "INSERT INTO `$tableName` (user_logged, user_id, name, emailId, message, created_date) 
              VALUES (:user_logged, :user_id, :name, :emailId, :message, NOW())";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':user_logged', $user_logged, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $user_id, $user_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':name', $name, PDO::PARAM_STR);
    $stmt->bindValue(':emailId', $emailId, PDO::PARAM_STR);
    $stmt->bindValue(':message', $message, PDO::PARAM_STR);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Thank you for contacting us! We have received your message and will get back to you soon.'
        ]);
    } else {
        throw new Exception('Failed to save contact message');
    }
    
} catch (PDOException $e) {
    error_log('Contact form submission error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
} catch (Exception $e) {
    error_log('Contact form submission error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

