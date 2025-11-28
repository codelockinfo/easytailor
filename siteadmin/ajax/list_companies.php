<?php
/**
 * List all companies for site admin
 */

require_once '../../config/config.php';

// Check if site admin is logged in
if (!isset($_SESSION['site_admin_logged_in']) || $_SESSION['site_admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../models/Company.php';

$companyModel = new Company();

// Get all companies
$companies = $companyModel->findAll([], 'created_at DESC');

$planStyles = [
    'free' => [
        'label' => 'Free Trial',
        'badge' => 'secondary',
        'gradient' => '#6c757d, #8e9aaf'
    ],
    'basic' => [
        'label' => 'Basic Plan',
        'badge' => 'primary',
        'gradient' => '#3b82f6, #60a5fa'
    ],
    'premium' => [
        'label' => 'Premium Plan',
        'badge' => 'pink',
        'gradient' => '#c026d3, #d946ef'
    ],
    'enterprise' => [
        'label' => 'Enterprise Plan',
        'badge' => 'warning',
        'gradient' => '#fbbf24, #fef3c7'
    ]
];

$result = [];
foreach ($companies as $company) {
    $planKey = strtolower($company['subscription_plan'] ?? 'free');
    $planMeta = $planStyles[$planKey] ?? $planStyles['free'];
    
    // Get company stats
    $stats = $companyModel->getCompanyStats($company['id']);
    
    // Calculate days remaining
    $daysRemaining = null;
    if (!empty($company['subscription_expiry'])) {
        $expiry = strtotime($company['subscription_expiry']);
        $today = strtotime(date('Y-m-d'));
        $daysRemaining = max(0, floor(($expiry - $today) / (60 * 60 * 24)));
    }
    
    $result[] = [
        'id' => $company['id'],
        'company_name' => $company['company_name'],
        'owner_name' => $company['owner_name'] ?? 'N/A',
        'business_email' => $company['business_email'],
        'business_phone' => $company['business_phone'] ?? '-',
        'city' => $company['city'] ?? '',
        'state' => $company['state'] ?? '',
        'website' => $company['website'] ?? '',
        'tax_number' => $company['tax_number'] ?? '',
        'business_address' => $company['business_address'] ?? '',
        'subscription_plan' => $planKey,
        'subscription_expiry' => $company['subscription_expiry'] ? date('M d, Y', strtotime($company['subscription_expiry'])) : null,
        'created_at' => date('M d, Y', strtotime($company['created_at'])),
        'plan_label' => $planMeta['label'],
        'plan_badge' => $planMeta['badge'],
        'plan_gradient' => $planMeta['gradient'],
        'remaining_days' => $daysRemaining,
        'stats' => $stats
    ];
}

echo json_encode([
    'success' => true,
    'companies' => $result
]);

