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
define('APP_URL', 'http://localhost/tailoring');
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
        redirect(APP_URL . '/login.php');
    }
}

function has_role($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

function require_role($role) {
    require_login();
    if (!has_role($role) && !has_role('admin')) {
        redirect(APP_URL . '/unauthorized.php');
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

// Create upload directory if it doesn't exist
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}

// Create subdirectories for different file types
$upload_subdirs = ['customers', 'orders', 'measurements', 'receipts', 'logos'];
foreach ($upload_subdirs as $subdir) {
    $dir = UPLOAD_PATH . $subdir . '/';
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}
?>

