<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); 
session_start();
define('APP_NAME', 'Tailoring Management System');
define('APP_VERSION', '1.0.0');
define('GA4_MEASUREMENT_ID', 'G-27LLB9QMEV');
require_once __DIR__ . '/../lang/language_loader.php';
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_name = $_SERVER['SCRIPT_NAME'] ?? '';
$path = dirname($script_name);
$path = $path === '/' ? '' : $path;
define('APP_URL', $protocol . '://' . $host . $path);
define('APP_PATH', __DIR__ . '/../');
if (file_exists(APP_PATH . 'vendor/autoload.php')) {
    require_once APP_PATH . 'vendor/autoload.php';
}
if (file_exists(__DIR__ . '/vapid.php')) {
    require_once __DIR__ . '/vapid.php';
}
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_TIMEOUT', 3600);
define('UPLOAD_PATH', APP_PATH . 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); 
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

define('RECORDS_PER_PAGE', 10);
date_default_timezone_set('Asia/Kolkata');

define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 465);
define('SMTP_ENCRYPTION', 'ssl');
define('SMTP_USERNAME', 'info@tailorpro.in');
define('SMTP_PASSWORD', '82@Codelock@');
define('SMTP_FROM_EMAIL', 'info@tailorpro.in');
define('SMTP_FROM_NAME', 'TailorPro');

$mailConfigFile = __DIR__ . '/mail.php';
if (file_exists($mailConfigFile)) {
    @include_once $mailConfigFile;
}
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
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    return $data;
}
function display_val($data) {
    if ($data === null) return '';
    return htmlspecialchars(html_entity_decode((string)$data, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
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
function get_logo_path($logo_name = 'brand-logo.png') {

    $possible_paths = [
        'uploads/logos/' . $logo_name,
        '../uploads/logos/' . $logo_name,
        __DIR__ . '/../uploads/logos/' . $logo_name
    ];
    
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            if (strpos($path, '../') === 0) {
                return $path;
            } elseif (strpos($path, 'uploads/') === 0) {
                return $path;
            } else {
                $relative_path = str_replace(__DIR__ . '/../', '', $path);
                return $relative_path;
            }
        }
    }
    
    return null;
}
function smart_redirect($url) {
    $url = ltrim($url, '/');
    $url = preg_replace('#admin/admin/#', 'admin/', $url);
        if (!preg_match('/^https?:\/\//', $url)) {
      
        $current_path = dirname($_SERVER['SCRIPT_NAME']);
        $current_path = $current_path === '/' ? '' : $current_path;
        
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $full_url = $protocol . '://' . $host . $current_path . '/' . $url;
    } else {
        $full_url = $url;
    }
    
    header('Location: ' . $full_url);
    exit;
}
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}
$upload_subdirs = ['customers', 'orders', 'measurements', 'receipts', 'logos', 'reviews'];
foreach ($upload_subdirs as $subdir) {
    $dir = UPLOAD_PATH . $subdir . '/';
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}
define('RAZORPAY_KEY_ID', getenv('RAZORPAY_KEY_ID') ?: 'rzp_test_RfbZw5apB4THcH');
define('RAZORPAY_KEY_SECRET', getenv('RAZORPAY_KEY_SECRET') ?: 'CEpjpNKALClK7tuKFf20D9VM');
define('RAZORPAY_MODE', getenv('RAZORPAY_MODE') ?: 'test'); 
?>