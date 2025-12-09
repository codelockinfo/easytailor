<?php
/**
 * Google Analytics 4 (GA4) Helper Class
 * Provides GA4 tracking and event tracking functions
 */

class GA4Helper {
    
    /**
     * Get GA4 Measurement ID
     */
    public static function getMeasurementId() {
        return defined('GA4_MEASUREMENT_ID') ? GA4_MEASUREMENT_ID : '';
    }
    
    /**
     * Generate GA4 base tracking code
     */
    public static function generateBaseCode() {
        $ga4Id = self::getMeasurementId();
        
        // Use fallback if not defined
        if (empty($ga4Id)) {
            $ga4Id = 'G-27LLB9QMEV'; // Fallback measurement ID
        }
        
        // Define gtag function FIRST before async script loads
        // This ensures gtag is always available even if async script hasn't loaded yet
        $code = "
    <!-- Google tag (gtag.js) -->
    <script>
        // Initialize dataLayer immediately (must be global)
        window.dataLayer = window.dataLayer || [];
        
        // Define gtag function immediately (must be global, not in IIFE)
        // This ensures gtag is always available before any other scripts run
        function gtag() {
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push(arguments);
        }
        
        // Also assign to window for explicit access
        window.gtag = gtag;
        
        // Make gtag available globally
        if (typeof gtag === 'undefined') {
            var gtag = window.gtag;
        }
        
        // Initialize GA4
        gtag('js', new Date());
        gtag('config', '" . htmlspecialchars($ga4Id, ENT_QUOTES, 'UTF-8') . "');
    </script>
    <script async src=\"https://www.googletagmanager.com/gtag/js?id=" . htmlspecialchars($ga4Id, ENT_QUOTES, 'UTF-8') . "\"></script>";
        
        return $code;
    }
    
    /**
     * Generate JavaScript code for tracking events
     * Returns JavaScript function that can be called from PHP or inline JS
     */
    public static function generateEventTrackingCode($eventName, $eventParams = []) {
        $ga4Id = self::getMeasurementId();
        
        if (empty($ga4Id)) {
            return '';
        }
        
        $paramsJson = json_encode($eventParams, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        return "gtag('event', '" . htmlspecialchars($eventName, ENT_QUOTES, 'UTF-8') . "', " . $paramsJson . ");";
    }
    
    /**
     * Track login event
     */
    public static function trackLogin($userId = null, $userRole = null) {
        $params = [
            'event_category' => 'authentication',
            'event_label' => 'user_login',
            'method' => 'email'
        ];
        
        if ($userId) {
            $params['user_id'] = (string)$userId;
        }
        if ($userRole) {
            $params['user_role'] = $userRole;
        }
        
        return self::generateEventTrackingCode('login', $params);
    }
    
    /**
     * Track signup/registration event
     */
    public static function trackSignup($userId = null, $companyId = null, $companyName = null, $ownerName = null) {
        $params = [
            'method' => 'email'
        ];
        
        if ($userId) {
            $params['user_id'] = (string)$userId;
        }
        if ($companyId) {
            $params['company_id'] = (string)$companyId;
        }
        if ($companyName) {
            $params['company_name'] = $companyName;
        }
        if ($ownerName) {
            $params['owner_name'] = $ownerName;
        }
        
        return self::generateEventTrackingCode('sign_up', $params);
    }
    
    /**
     * Track page view/visit
     */
    public static function trackPageView($pageTitle = null, $pageLocation = null) {
        $params = [];
        
        if ($pageTitle) {
            $params['page_title'] = $pageTitle;
        }
        if ($pageLocation) {
            $params['page_location'] = $pageLocation;
        }
        
        return self::generateEventTrackingCode('page_view', $params);
    }
    
    /**
     * Track generate lead event
     */
    public static function trackGenerateLead($leadType = 'contact', $value = null) {
        $params = [
            'event_category' => 'lead_generation',
            'event_label' => $leadType,
            'currency' => 'INR'
        ];
        
        if ($value !== null) {
            $params['value'] = (float)$value;
        }
        
        return self::generateEventTrackingCode('generate_lead', $params);
    }
    
    /**
     * Track create order event
     */
    public static function trackCreateOrder($orderId = null, $orderNumber = null, $value = null, $currency = 'INR') {
        $params = [
            'event_category' => 'orders',
            'event_label' => 'create_order',
            'currency' => $currency
        ];
        
        if ($orderId) {
            $params['order_id'] = (string)$orderId;
        }
        if ($orderNumber) {
            $params['order_number'] = $orderNumber;
        }
        if ($value !== null) {
            $params['value'] = (float)$value;
        }
        
        return self::generateEventTrackingCode('create_order', $params);
    }
    
    /**
     * Track create invoice event
     */
    public static function trackCreateInvoice($invoiceId = null, $invoiceNumber = null, $value = null, $currency = 'INR') {
        $params = [
            'event_category' => 'invoices',
            'event_label' => 'create_invoice',
            'currency' => $currency
        ];
        
        if ($invoiceId) {
            $params['invoice_id'] = (string)$invoiceId;
        }
        if ($invoiceNumber) {
            $params['invoice_number'] = $invoiceNumber;
        }
        if ($value !== null) {
            $params['value'] = (float)$value;
        }
        
        return self::generateEventTrackingCode('create_invoice', $params);
    }
    
    /**
     * Track request email change event
     */
    public static function trackRequestEmailChange($userId = null) {
        $params = [
            'event_category' => 'account',
            'event_label' => 'request_email_change'
        ];
        
        if ($userId) {
            $params['user_id'] = (string)$userId;
        }
        
        return self::generateEventTrackingCode('request_email_change', $params);
    }
    
    /**
     * Track purchase/subscription event
     */
    public static function trackPurchase($transactionId = null, $value = null, $currency = 'INR', $items = []) {
        $params = [
            'event_category' => 'ecommerce',
            'event_label' => 'purchase',
            'currency' => $currency
        ];
        
        if ($transactionId) {
            $params['transaction_id'] = (string)$transactionId;
        }
        if ($value !== null) {
            $params['value'] = (float)$value;
        }
        if (!empty($items)) {
            $params['items'] = $items;
        }
        
        return self::generateEventTrackingCode('purchase', $params);
    }
    
    /**
     * Track subscription purchase
     */
    public static function trackSubscriptionPurchase($planName = null, $planKey = null, $value = null, $duration = null, $transactionId = null) {
        $params = [
            'event_category' => 'subscription',
            'event_label' => 'purchase_subscription',
            'currency' => 'INR'
        ];
        
        if ($planName) {
            $params['plan_name'] = $planName;
        }
        if ($planKey) {
            $params['plan_key'] = $planKey;
        }
        if ($duration) {
            $params['duration'] = $duration;
        }
        if ($value !== null) {
            $params['value'] = (float)$value;
        }
        if ($transactionId) {
            $params['transaction_id'] = (string)$transactionId;
        }
        
        return self::generateEventTrackingCode('purchase', $params);
    }
}

