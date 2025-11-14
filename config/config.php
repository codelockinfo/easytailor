<?php
/**
 * Application Configuration
 * Tailoring Management System
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
session_start();

// Application constants
define('APP_NAME', 'Tailoring Management System');
define('APP_VERSION', '1.0.0');

// Include language loader
require_once __DIR__ . '/../lang/language_loader.php';
// Auto-detect APP_URL based on current request
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_name = $_SERVER['SCRIPT_NAME'] ?? '';
$path = dirname($script_name);
$path = $path === '/' ? '' : $path;
define('APP_URL', $protocol . '://' . $host . $path);
define('APP_PATH', __DIR__ . '/../');

// Security constants
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_TIMEOUT', 3600); // 1 hour

// File upload settings
define('UPLOAD_PATH', APP_PATH . 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

// Pagination settings
define('RECORDS_PER_PAGE', 20);

// Date and time settings
date_default_timezone_set('Asia/Kolkata'); // Indian Standard Time

// Load mail configuration if available, otherwise fall back to environment/defaults
$mailConfigFile = __DIR__ . '/mail.php';
if (file_exists($mailConfigFile)) {
    require_once $mailConfigFile;
}

if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));
}
if (!defined('SMTP_ENCRYPTION')) {
    define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: 'tls');
}
if (!defined('SMTP_USERNAME')) {
    define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
}
if (!defined('SMTP_PASSWORD')) {
    define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
}
if (!defined('SMTP_FROM_EMAIL')) {
    define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: '');
}
if (!defined('SMTP_FROM_NAME')) {
    define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: APP_NAME);
}

// Autoloader
spl_autoload_register(function ($class_name) {
    $directories = [
        APP_PATH . 'models/',
        APP_PATH . 'controllers/',
        APP_PATH . 'helpers/',
        APP_PATH . 'config/'
    ];
    
    foreach ($directories as $directory) {
        $file = $directory . $class_name . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Helper functions
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function generate_csrf_token() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verify_csrf_token($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function format_currency($amount, $currency = 'INR') {
    return '₹' . number_format($amount, 2);
}

function format_date($date, $format = 'Y-m-d') {
    return date($format, strtotime($date));
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        // Check if we're already in admin folder
        $current_script = $_SERVER['SCRIPT_NAME'] ?? '';
        if (strpos($current_script, '/admin/') !== false) {
            smart_redirect('login.php');
        } else {
            smart_redirect('admin/login.php');
        }
    }
}

function has_role($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

function require_role($role) {
    require_login();
    if (!has_role($role) && !has_role('admin')) {
        // Check if we're already in admin folder
        $current_script = $_SERVER['SCRIPT_NAME'] ?? '';
        if (strpos($current_script, '/admin/') !== false) {
            smart_redirect('unauthorized.php');
        } else {
            smart_redirect('admin/unauthorized.php');
        }
    }
}

function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function get_user_role() {
    return $_SESSION['user_role'] ?? null;
}

function get_user_name() {
    return $_SESSION['user_name'] ?? null;
}

function get_company_id() {
    return $_SESSION['company_id'] ?? null;
}

function set_company_id($company_id) {
    $_SESSION['company_id'] = $company_id;
}

/**
 * Get logo path that works on any deployment
 */
function get_logo_path($logo_name = 'brand-logo.png') {
    // Try different possible paths
    $possible_paths = [
        'uploads/logos/' . $logo_name,
        '../uploads/logos/' . $logo_name,
        __DIR__ . '/../uploads/logos/' . $logo_name
    ];
    
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            // Return relative path for web access
            if (strpos($path, '../') === 0) {
                return $path;
            } elseif (strpos($path, 'uploads/') === 0) {
                return $path;
            } else {
                // Convert absolute path to relative
                $relative_path = str_replace(__DIR__ . '/../', '', $path);
                return $relative_path;
            }
        }
    }
    
    return null; // Logo not found
}

/**
 * Smart redirect function that works on any deployment
 */
function smart_redirect($url) {
    // Remove any leading slashes and admin/admin duplicates
    $url = ltrim($url, '/');
    $url = preg_replace('#admin/admin/#', 'admin/', $url);
    
    // If URL doesn't start with http, make it relative to current directory
    if (!preg_match('/^https?:\/\//', $url)) {
        // Get current directory structure
        $current_path = dirname($_SERVER['SCRIPT_NAME']);
        $current_path = $current_path === '/' ? '' : $current_path;
        
        // Build the full URL
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $full_url = $protocol . '://' . $host . $current_path . '/' . $url;
    } else {
        $full_url = $url;
    }
    
    header('Location: ' . $full_url);
    exit;
}

// Create upload directory if it doesn't exist
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}

// Create subdirectories for different file types
$upload_subdirs = ['customers', 'orders', 'measurements', 'receipts', 'logos', 'reviews'];
foreach ($upload_subdirs as $subdir) {
    $dir = UPLOAD_PATH . $subdir . '/';
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Razorpay Configuration (Test Mode)
// IMPORTANT: Replace these with your actual Razorpay test keys from https://dashboard.razorpay.com/app/keys
// For test mode, use keys starting with 'rzp_test_'
// You can also set these via environment variables: RAZORPAY_KEY_ID and RAZORPAY_KEY_SECRET
define('RAZORPAY_KEY_ID', getenv('RAZORPAY_KEY_ID') ?: 'rzp_test_RfbZw5apB4THcH');
define('RAZORPAY_KEY_SECRET', getenv('RAZORPAY_KEY_SECRET') ?: 'CEpjpNKALClK7tuKFf20D9VM');
define('RAZORPAY_MODE', getenv('RAZORPAY_MODE') ?: 'test'); // 'test' or 'live'
?>

