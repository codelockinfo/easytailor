<?php
/**
 * Verify Razorpay Payment
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
$required = ['razorpay_payment_id', 'razorpay_order_id', 'razorpay_signature', 'plan_key', 'amount'];
foreach ($required as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

$paymentId = sanitize_input($input['razorpay_payment_id']);
$orderId = sanitize_input($input['razorpay_order_id']);
$signature = sanitize_input($input['razorpay_signature']);
$planKey = sanitize_input($input['plan_key']);
$amount = floatval($input['amount']);

// Get company ID
$companyId = get_company_id();
$userId = get_user_id();

if (!$companyId || !$userId) {
    echo json_encode(['success' => false, 'message' => 'User session invalid']);
    exit;
}

try {
    $razorpayKeySecret = RAZORPAY_KEY_SECRET;
    
    // Verify signature
    $generatedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, $razorpayKeySecret);
    
    if ($generatedSignature !== $signature) {
        echo json_encode(['success' => false, 'message' => 'Payment verification failed: Invalid signature']);
        exit;
    }
    
    // Verify payment with Razorpay API
    $ch = curl_init('https://api.razorpay.com/v1/payments/' . $paymentId);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode(RAZORPAY_KEY_ID . ':' . $razorpayKeySecret)
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo json_encode(['success' => false, 'message' => 'Failed to verify payment with Razorpay']);
        exit;
    }
    
    $paymentData = json_decode($response, true);
    
    // Check if payment is successful
    if ($paymentData['status'] !== 'captured' && $paymentData['status'] !== 'authorized') {
        echo json_encode(['success' => false, 'message' => 'Payment not successful. Status: ' . $paymentData['status']]);
        exit;
    }
    
    // Verify amount matches
    $paidAmount = floatval($paymentData['amount'] / 100); // Convert from paise
    if (abs($paidAmount - $amount) > 0.01) {
        echo json_encode(['success' => false, 'message' => 'Amount mismatch']);
        exit;
    }
    
    // Update subscription in database
    require_once '../../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    // Calculate expiry date
    $duration = $input['duration'] ?? 'monthly';
    $months = ($duration === 'yearly') ? 12 : 1;
    $expiryDate = date('Y-m-d', strtotime("+$months months"));
    
    // Update company subscription
    require_once '../models/Company.php';
    $companyModel = new Company();
    $updateData = [
        'subscription_plan' => $planKey,
        'subscription_expiry' => $expiryDate
    ];
    
    $updated = $companyModel->update($companyId, $updateData);
    
    if (!$updated) {
        echo json_encode(['success' => false, 'message' => 'Failed to update subscription']);
        exit;
    }
    
    // Store payment record in database
    try {
        // Create razorpay_payments table if it doesn't exist
        $db->exec("
            CREATE TABLE IF NOT EXISTS razorpay_payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                payment_id VARCHAR(255) NOT NULL UNIQUE,
                order_id VARCHAR(255) NOT NULL,
                company_id INT NOT NULL,
                user_id INT NOT NULL,
                plan_key VARCHAR(50) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                currency VARCHAR(10) DEFAULT 'INR',
                status VARCHAR(50) NOT NULL,
                payment_data TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_company_id (company_id),
                INDEX idx_payment_id (payment_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        $stmt = $db->prepare("
            INSERT INTO razorpay_payments 
            (payment_id, order_id, company_id, user_id, plan_key, amount, currency, status, payment_data, created_at) 
            VALUES 
            (?, ?, ?, ?, ?, ?, 'INR', ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            status = VALUES(status),
            payment_data = VALUES(payment_data)
        ");
        $stmt->execute([
            $paymentId,
            $orderId,
            $companyId,
            $userId,
            $planKey,
            $amount,
            $paymentData['status'],
            json_encode($paymentData)
        ]);
    } catch (Exception $e) {
        error_log("Failed to store payment record: " . $e->getMessage());
        // Don't fail the request if payment record storage fails
    }
    
    // Track purchase event
    require_once '../../helpers/GA4Helper.php';
    $ga4Event = GA4Helper::trackSubscriptionPurchase(
        $input['plan_name'] ?? null,
        $planKey,
        $amount,
        $input['duration'] ?? null,
        $paymentId
    );
    
    // Set success message in session
    $_SESSION['message'] = 'Payment successful! Your subscription has been upgraded to ' . ($input['plan_name'] ?? $planKey) . '.';
    $_SESSION['messageType'] = 'success';
    $_SESSION['ga4_event'] = $ga4Event;
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment verified and subscription updated successfully',
        'ga4_event' => $ga4Event
    ]);
    
} catch (Exception $e) {
    error_log("Payment verification error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Payment verification failed. Please contact support.']);
}
?>

