<?php
/**
 * Create Razorpay Order
 * Tailoring Management System
 */

// Set content type to JSON
header('Content-Type: application/json');

require_once '../../config/config.php';
require_once '../../config/database.php';

// Check if user is logged in
require_login();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

// Validate required fields
$required = ['plan_key', 'plan_name', 'amount', 'duration', 'customer_name', 'customer_email', 'customer_phone'];
foreach ($required as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

$planKey = $input['plan_key'];
$planName = $input['plan_name'];
$amount = floatval($input['amount']);
$duration = $input['duration'];
$customerName = sanitize_input($input['customer_name']);
$customerEmail = sanitize_input($input['customer_email']);
$customerPhone = sanitize_input($input['customer_phone']);

// Validate amount
if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid amount']);
    exit;
}

// Convert amount to paise (Razorpay uses smallest currency unit)
$amountInPaise = intval($amount * 100);

// Get company ID
$companyId = get_company_id();
$userId = get_user_id();

if (!$companyId || !$userId) {
    echo json_encode(['success' => false, 'message' => 'User session invalid']);
    exit;
}

try {
    // Initialize Razorpay (using cURL since we don't have Razorpay SDK)
    $razorpayKeyId = RAZORPAY_KEY_ID;
    $razorpayKeySecret = RAZORPAY_KEY_SECRET;
    
    // Create order via Razorpay API
    $orderData = [
        'amount' => $amountInPaise,
        'currency' => 'INR',
        'receipt' => 'sub_' . $companyId . '_' . time(),
        'notes' => [
            'plan_key' => $planKey,
            'plan_name' => $planName,
            'duration' => $duration,
            'company_id' => $companyId,
            'user_id' => $userId,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'customer_phone' => $customerPhone
        ]
    ];
    
    // Make API call to Razorpay
    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($razorpayKeyId . ':' . $razorpayKeySecret)
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['error']['description'] ?? 'Failed to create order';
        echo json_encode(['success' => false, 'message' => $errorMsg]);
        exit;
    }
    
    $orderResponse = json_decode($response, true);
    
    if (!isset($orderResponse['id'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid response from Razorpay']);
        exit;
    }
    
    // Store order details in database (optional but recommended)
    try {
        require_once '../../config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        // Create table if it doesn't exist
        $db->exec("
            CREATE TABLE IF NOT EXISTS razorpay_orders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id VARCHAR(255) NOT NULL UNIQUE,
                company_id INT NOT NULL,
                user_id INT NOT NULL,
                plan_key VARCHAR(50) NOT NULL,
                plan_name VARCHAR(100) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                duration VARCHAR(20) NOT NULL,
                customer_name VARCHAR(255) NOT NULL,
                customer_email VARCHAR(255) NOT NULL,
                customer_phone VARCHAR(20) NOT NULL,
                status VARCHAR(50) DEFAULT 'created',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_company_id (company_id),
                INDEX idx_order_id (order_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        $stmt = $db->prepare("
            INSERT INTO razorpay_orders 
            (order_id, company_id, user_id, plan_key, plan_name, amount, duration, customer_name, customer_email, customer_phone, status, created_at) 
            VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'created', NOW())
        ");
        $stmt->execute([
            $orderResponse['id'],
            $companyId,
            $userId,
            $planKey,
            $planName,
            $amount,
            $duration,
            $customerName,
            $customerEmail,
            $customerPhone
        ]);
    } catch (Exception $e) {
        // Log error but don't fail the request
        error_log("Failed to store Razorpay order in database: " . $e->getMessage());
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'order_id' => $orderResponse['id'],
        'amount' => $amountInPaise,
        'currency' => 'INR',
        'razorpay_key' => $razorpayKeyId
    ]);
    
} catch (Exception $e) {
    error_log("Razorpay order creation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to create payment order. Please try again.']);
}
?>

