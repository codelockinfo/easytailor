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
    
    // Validate Razorpay keys
    if (empty($razorpayKeyId) || $razorpayKeyId === 'rzp_test_YOUR_KEY_ID_HERE' || strpos($razorpayKeyId, 'YOUR_KEY') !== false) {
        echo json_encode(['success' => false, 'message' => 'Razorpay Key ID is not configured. Please set RAZORPAY_KEY_ID in config.php']);
        exit;
    }
    
    if (empty($razorpayKeySecret) || $razorpayKeySecret === 'YOUR_KEY_SECRET_HERE' || strpos($razorpayKeySecret, 'YOUR_KEY') !== false) {
        echo json_encode(['success' => false, 'message' => 'Razorpay Key Secret is not configured. Please set RAZORPAY_KEY_SECRET in config.php']);
        exit;
    }
    
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
    // For localhost, you might need to disable SSL verification (development only)
    // In production, always keep SSL verification enabled
    $isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1']) || 
                   strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false ||
                   strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false;
    
    if ($isLocalhost && RAZORPAY_MODE === 'test') {
        // Disable SSL verification for localhost test mode only
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    } else {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Check for cURL errors
    if ($response === false || !empty($curlError)) {
        error_log("Razorpay cURL Error: " . $curlError);
        echo json_encode([
            'success' => false, 
            'message' => 'Connection error: ' . ($curlError ?: 'Failed to connect to Razorpay API. Please check your internet connection.')
        ]);
        exit;
    }
    
    // Check HTTP status code
    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg = 'Failed to create order';
        
        if (isset($errorData['error'])) {
            if (isset($errorData['error']['description'])) {
                $errorMsg = $errorData['error']['description'];
            } elseif (isset($errorData['error']['reason'])) {
                $errorMsg = $errorData['error']['reason'];
            } elseif (isset($errorData['error']['code'])) {
                $errorMsg = 'Error Code: ' . $errorData['error']['code'];
            }
        } elseif (isset($errorData['message'])) {
            $errorMsg = $errorData['message'];
        }
        
        // Log full error for debugging
        error_log("Razorpay API Error (HTTP $httpCode): " . $response);
        
        echo json_encode([
            'success' => false, 
            'message' => $errorMsg,
            'debug' => (RAZORPAY_MODE === 'test' ? ['http_code' => $httpCode, 'response' => $response] : null)
        ]);
        exit;
    }
    
    $orderResponse = json_decode($response, true);
    
    if (!isset($orderResponse['id'])) {
        error_log("Invalid Razorpay response: " . $response);
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid response from Razorpay',
            'debug' => (RAZORPAY_MODE === 'test' ? ['response' => $response] : null)
        ]);
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

