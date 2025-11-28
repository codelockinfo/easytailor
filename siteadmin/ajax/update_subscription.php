<?php
/**
 * Update Company Subscription Plan
 * AJAX endpoint for updating company subscription
 */

require_once '../../config/config.php';

// Check if site admin is logged in
if (!isset($_SESSION['site_admin_logged_in']) || $_SESSION['site_admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['company_id']) || !isset($input['subscription_plan'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$companyId = intval($input['company_id']);
$subscriptionPlan = strtolower(trim($input['subscription_plan']));
$duration = isset($input['duration']) ? strtolower(trim($input['duration'])) : 'monthly';

// Validate subscription plan
$validPlans = ['free', 'basic', 'premium', 'enterprise'];
if (!in_array($subscriptionPlan, $validPlans)) {
    echo json_encode(['success' => false, 'message' => 'Invalid subscription plan']);
    exit;
}

// Validate duration
$validDurations = ['monthly', 'yearly'];
if ($subscriptionPlan !== 'free' && !in_array($duration, $validDurations)) {
    echo json_encode(['success' => false, 'message' => 'Invalid duration']);
    exit;
}

try {
    require_once '../../models/Company.php';
    $companyModel = new Company();
    
    // Check if company exists
    $company = $companyModel->find($companyId);
    if (!$company) {
        echo json_encode(['success' => false, 'message' => 'Company not found']);
        exit;
    }
    
    // Calculate expiry date based on duration
    $expiryDate = null;
    if ($subscriptionPlan !== 'free') {
        $months = ($duration === 'yearly') ? 12 : 1;
        $expiryDate = date('Y-m-d', strtotime("+$months months"));
    }
    
    // Update subscription plan and expiry date
    $updateData = [
        'subscription_plan' => $subscriptionPlan
    ];
    
    // Only update expiry date for paid plans
    if ($expiryDate) {
        $updateData['subscription_expiry'] = $expiryDate;
    }
    
    $updated = $companyModel->update($companyId, $updateData);
    
    if ($updated) {
        echo json_encode([
            'success' => true,
            'message' => 'Subscription plan updated successfully',
            'new_plan' => $subscriptionPlan
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update subscription plan']);
    }
} catch (Exception $e) {
    error_log('Error updating subscription: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while updating the subscription plan']);
}

