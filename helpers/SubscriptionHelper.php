<?php
/**
 * Subscription Helper
 * Tailoring Management System
 * Handles subscription plan limits and restrictions
 */

class SubscriptionHelper {
    
    /**
     * Get plan limits
     */
    public static function getPlanLimits($planKey) {
        $plans = [
            'free' => [
                'customers' => 30,
                'orders' => 50,
                'users' => 3,
                'invoice_generation' => false,
                'export_data' => false,
                'sms_notifications' => false
            ],
            'basic' => [
                'customers' => 100,
                'orders' => 150,
                'users' => 10,
                'invoice_generation' => true,
                'export_data' => false,
                'sms_notifications' => true
            ],
            'premium' => [
                'customers' => 500,
                'orders' => 1000,
                'users' => 20,
                'invoice_generation' => true,
                'export_data' => true,
                'sms_notifications' => true
            ],
            'enterprise' => [
                'customers' => -1, // Unlimited
                'orders' => -1, // Unlimited
                'users' => -1, // Unlimited
                'invoice_generation' => true,
                'export_data' => true,
                'sms_notifications' => true
            ]
        ];
        
        return $plans[$planKey] ?? $plans['free'];
    }
    
    /**
     * Get current company subscription plan
     */
    public static function getCurrentPlan($companyId) {
        require_once __DIR__ . '/../admin/models/Company.php';
        $companyModel = new Company();
        $company = $companyModel->find($companyId);
        
        if (!$company) {
            return 'free';
        }
        
        // Check if subscription is expired
        if ($company['subscription_expiry']) {
            $expiry = strtotime($company['subscription_expiry']);
            $today = strtotime(date('Y-m-d'));
            if ($expiry < $today && $company['subscription_plan'] !== 'free') {
                // Subscription expired, downgrade to free
                return 'free';
            }
        }
        
        return $company['subscription_plan'] ?? 'free';
    }
    
    /**
     * Check if company can add more customers
     */
    public static function canAddCustomer($companyId) {
        $plan = self::getCurrentPlan($companyId);
        $limits = self::getPlanLimits($plan);
        
        // Unlimited
        if ($limits['customers'] === -1) {
            return ['allowed' => true];
        }
        
        // Get current count
        require_once __DIR__ . '/../models/Customer.php';
        $customerModel = new Customer();
        $currentCount = $customerModel->count(['company_id' => $companyId]);
        
        if ($currentCount >= $limits['customers']) {
            return [
                'allowed' => false,
                'message' => "You have reached the maximum limit of {$limits['customers']} customers for your {$plan} plan. Please upgrade your subscription to add more customers.",
                'current' => $currentCount,
                'limit' => $limits['customers']
            ];
        }
        
        return [
            'allowed' => true,
            'current' => $currentCount,
            'limit' => $limits['customers'],
            'remaining' => $limits['customers'] - $currentCount
        ];
    }
    
    /**
     * Check if company can add more orders
     */
    public static function canAddOrder($companyId) {
        $plan = self::getCurrentPlan($companyId);
        $limits = self::getPlanLimits($plan);
        
        // Unlimited
        if ($limits['orders'] === -1) {
            return ['allowed' => true];
        }
        
        // Get current count
        require_once __DIR__ . '/../admin/models/Order.php';
        $orderModel = new Order();
        $currentCount = $orderModel->count(['company_id' => $companyId]);
        
        if ($currentCount >= $limits['orders']) {
            return [
                'allowed' => false,
                'message' => "You have reached the maximum limit of {$limits['orders']} orders for your {$plan} plan. Please upgrade your subscription to add more orders.",
                'current' => $currentCount,
                'limit' => $limits['orders']
            ];
        }
        
        return [
            'allowed' => true,
            'current' => $currentCount,
            'limit' => $limits['orders'],
            'remaining' => $limits['orders'] - $currentCount
        ];
    }
    
    /**
     * Check if company can add more users
     */
    public static function canAddUser($companyId) {
        $plan = self::getCurrentPlan($companyId);
        $limits = self::getPlanLimits($plan);
        
        // Unlimited
        if ($limits['users'] === -1) {
            return ['allowed' => true];
        }
        
        // Get current count excluding admins
        require_once __DIR__ . '/../admin/models/User.php';
        $userModel = new User();
        $stmt = $userModel->query("SELECT COUNT(*) as count FROM users WHERE company_id = :company_id AND role != 'admin'", [':company_id' => $companyId]);
        $result = $stmt->fetch();
        $currentCount = (int)($result['count'] ?? 0);
        
        if ($currentCount >= $limits['users']) {
            return [
                'allowed' => false,
                'message' => "You have reached the maximum limit of {$limits['users']} users for your {$plan} plan. Please upgrade your subscription to add more users.",
                'current' => $currentCount,
                'limit' => $limits['users']
            ];
        }
        
        return [
            'allowed' => true,
            'current' => $currentCount,
            'limit' => $limits['users'],
            'remaining' => $limits['users'] - $currentCount
        ];
    }
    
    /**
     * Check if company can generate invoices
     */
    public static function canGenerateInvoice($companyId) {
        $plan = self::getCurrentPlan($companyId);
        $limits = self::getPlanLimits($plan);
        
        if (!$limits['invoice_generation']) {
            return [
                'allowed' => false,
                'message' => "Invoice generation is not available in the Free plan. Please upgrade to Basic, Premium, or Enterprise plan to use this feature.",
                'plan' => $plan
            ];
        }
        
        return ['allowed' => true];
    }
    
    /**
     * Check if company can export data
     */
    public static function canExportData($companyId) {
        $plan = self::getCurrentPlan($companyId);
        $limits = self::getPlanLimits($plan);
        
        if (!$limits['export_data']) {
            return [
                'allowed' => false,
                'message' => "Data export is only available in Premium and Enterprise plans. Please upgrade to use this feature.",
                'plan' => $plan
            ];
        }
        
        return ['allowed' => true];
    }
    
    /**
     * Check if company can send SMS notifications
     */
    public static function canSendSms($companyId) {
        $plan = self::getCurrentPlan($companyId);
        $limits = self::getPlanLimits($plan);
        
        if (!$limits['sms_notifications']) {
            return [
                'allowed' => false,
                'message' => "SMS notifications are not available in the Free plan. Please upgrade to use this feature.",
                'plan' => $plan
            ];
        }
        
        return ['allowed' => true];
    }
    /**
     * Get upgrade message for limit reached
     */
    public static function getUpgradeMessage($feature, $plan) {
        return "";
    }
}
?>

