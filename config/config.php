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

$googleConfigFile = __DIR__ . '/google.php';
if (file_exists($googleConfigFile)) {
    @include_once $googleConfigFile;
}

$slackConfigFile = __DIR__ . '/slack.php';
if (file_exists($slackConfigFile)) {
    @include_once $slackConfigFile;
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

function validate_password_strength($password) {
    if (strlen($password) < 6) {
        return ['valid' => false, 'message' => 'Password must be at least 6 characters long.'];
    }
    return ['valid' => true];
}

function validate_user_session($pdo = null, $force_check = false) {
    static $validationCache = null;
    
    if ($force_check) {
        $validationCache = null;
    }
    
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return false;
    }
    
    // Site admin session bypass
    if (!empty($_SESSION['site_admin_logged_in'])) {
        return true;
    }

    if ($validationCache !== null) {
        return $validationCache;
    }

    try {
        if (!$pdo) {
            if (!class_exists('Database')) {
                require_once __DIR__ . '/database.php';
            }
            $database = new Database();
            $pdo = $database->getConnection();
        }

        if ($pdo) {
            $userId = (int)$_SESSION['user_id'];
            $stmt = $pdo->prepare("SELECT id, status, company_id FROM users WHERE id = :id LIMIT 1");
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || (isset($user['status']) && $user['status'] !== 'active')) {
                // User no longer exists in DB or is inactive -> destroy session
                $_SESSION = [];
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_destroy();
                }
                if (!headers_sent() && ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000,
                        $params["path"], $params["domain"],
                        $params["secure"], $params["httponly"]
                    );
                }
                $validationCache = false;
                return false;
            }

            if (!empty($user['company_id'])) {
                $compStmt = $pdo->prepare("SELECT id FROM companies WHERE id = :cid LIMIT 1");
                $cid = (int)$user['company_id'];
                $compStmt->bindParam(':cid', $cid, PDO::PARAM_INT);
                $compStmt->execute();
                if (!$compStmt->fetch()) {
                    // Associated company was deleted -> destroy session
                    $_SESSION = [];
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_destroy();
                    }
                    if (!headers_sent() && ini_get("session.use_cookies")) {
                        $params = session_get_cookie_params();
                        setcookie(session_name(), '', time() - 42000,
                            $params["path"], $params["domain"],
                            $params["secure"], $params["httponly"]
                        );
                    }
                    $validationCache = false;
                    return false;
                }
            }

            $validationCache = true;
            return true;
        }
    } catch (Throwable $e) {
        // Fallback safely if database is temporarily unreachable
        return true;
    }

    return true;
}

function is_logged_in() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return false;
    }
    return validate_user_session();
}

function require_login() {
    if (!is_logged_in()) {
        $current_script = $_SERVER['SCRIPT_NAME'] ?? '';
        $redirectUrl = (strpos($current_script, '/admin/') !== false) ? 'login.php' : 'admin/login.php';
        if (isset($_GET['error'])) {
            $redirectUrl .= '?error=' . urlencode($_GET['error']);
        }
        smart_redirect($redirectUrl);
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
// Dynamic Razorpay Configuration from Site Settings
try {
    if (class_exists('Database')) {
        $dbConn = (new Database())->getConnection();
        if ($dbConn) {
            $stmt = $dbConn->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_group = 'payment'");
            $paySettings = [];
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $paySettings[$r['setting_key']] = $r['setting_value'];
            }
            $activeRzpMode = $paySettings['razorpay_mode'] ?? 'test';
            $activeRzpKey = ($activeRzpMode === 'live') ? ($paySettings['razorpay_live_key_id'] ?? '') : ($paySettings['razorpay_test_key_id'] ?? '');
            $activeRzpSecret = ($activeRzpMode === 'live') ? ($paySettings['razorpay_live_key_secret'] ?? '') : ($paySettings['razorpay_test_key_secret'] ?? '');

            if (!defined('RAZORPAY_MODE')) define('RAZORPAY_MODE', $activeRzpMode ?: (getenv('RAZORPAY_MODE') ?: 'test'));
            if (!defined('RAZORPAY_KEY_ID')) define('RAZORPAY_KEY_ID', $activeRzpKey ?: (getenv('RAZORPAY_KEY_ID') ?: 'rzp_test_RfbZw5apB4THcH'));
            if (!defined('RAZORPAY_KEY_SECRET')) define('RAZORPAY_KEY_SECRET', $activeRzpSecret ?: (getenv('RAZORPAY_KEY_SECRET') ?: 'CEpjpNKALClK7tuKFf20D9VM'));
        }
    }
} catch (Exception $e) {
    // Fallback below
}

if (!defined('RAZORPAY_KEY_ID')) define('RAZORPAY_KEY_ID', getenv('RAZORPAY_KEY_ID') ?: 'rzp_test_RfbZw5apB4THcH');
if (!defined('RAZORPAY_KEY_SECRET')) define('RAZORPAY_KEY_SECRET', getenv('RAZORPAY_KEY_SECRET') ?: 'CEpjpNKALClK7tuKFf20D9VM');
if (!defined('RAZORPAY_MODE')) define('RAZORPAY_MODE', getenv('RAZORPAY_MODE') ?: 'test'); 

/**
 * Global Site Settings & Brand Identity Helpers
 */
function get_site_setting($key, $default = null) {
    if (class_exists('SiteSetting')) {
        return SiteSetting::get($key, $default);
    }
    return $default;
}

function get_site_logo($type = 'website', $customPrefix = null) {
    $keyMap = [
        'website' => 'website_logo',
        'website_logo' => 'website_logo',
        'admin' => 'admin_logo',
        'admin_logo' => 'admin_logo',
        'footer' => 'footer_logo',
        'footer_logo' => 'footer_logo',
        'favicon' => 'favicon_icon',
        'favicon_icon' => 'favicon_icon',
        'fevicon_logo' => 'favicon_icon',
        'favicon_logo' => 'favicon_icon',
        'payment' => 'payment_logo',
        'payment_logo' => 'payment_logo',
    ];

    $settingKey = $keyMap[$type] ?? $type;
    $defaultFile = ($settingKey === 'favicon_icon') ? 'favicon.ico' : (($settingKey === 'payment_logo') ? '' : 'assets/images/tailor_logo.png');
    
    $val = get_site_setting($settingKey, $defaultFile);
    if (empty($val)) {
        return '';
    }

    $val = ltrim($val, '/');

    // Determine path prefix
    if ($customPrefix !== null) {
        $prefix = $customPrefix;
    } else {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $prefix = (strpos($script, '/admin/') !== false || strpos($script, '/siteadmin/') !== false) ? '../' : '';
    }

    $absPath = APP_PATH . $val;
    $version = file_exists($absPath) ? filemtime($absPath) : time();

    return $prefix . $val . '?v=' . $version;
}

function render_favicon_links($customPrefix = null) {
    $favPath = get_site_setting('favicon_icon', 'favicon.ico');
    if (empty($favPath)) {
        $favPath = 'favicon.ico';
    }
    $favPath = ltrim($favPath, '/');

    if ($customPrefix !== null) {
        $prefix = $customPrefix;
    } else {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $prefix = (strpos($script, '/admin/') !== false || strpos($script, '/siteadmin/') !== false) ? '../' : '';
    }

    $absPath = APP_PATH . $favPath;
    $version = file_exists($absPath) ? filemtime($absPath) : time();
    $fullUrl = $prefix . $favPath . '?v=' . $version;

    $ext = strtolower(pathinfo($favPath, PATHINFO_EXTENSION));
    $mime = 'image/x-icon';
    if ($ext === 'png') {
        $mime = 'image/png';
    } elseif ($ext === 'svg') {
        $mime = 'image/svg+xml';
    } elseif ($ext === 'jpg' || $ext === 'jpeg') {
        $mime = 'image/jpeg';
    } elseif ($ext === 'webp') {
        $mime = 'image/webp';
    }

    $html = '<link rel="icon" type="' . $mime . '" href="' . htmlspecialchars($fullUrl) . '" sizes="any">' . "\n";
    $html .= '    <link rel="shortcut icon" type="' . $mime . '" href="' . htmlspecialchars($fullUrl) . '">' . "\n";
    $html .= '    <link rel="apple-touch-icon" href="' . htmlspecialchars($fullUrl) . '">' . "\n";
    return $html;
}

function render_custom_head_scripts() {
    $html = '';
    $gaId = get_site_setting('google_analytics_id', 'G-27LLB9QMEV');
    if (!empty($gaId)) {
        $html .= '<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=' . htmlspecialchars($gaId) . '"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag("js", new Date());
  gtag("config", "' . htmlspecialchars($gaId) . '");
</script>' . "\n";
    }
    $customHead = get_site_setting('custom_head_scripts', '');
    if (!empty($customHead)) {
        $html .= $customHead . "\n";
    }
    return $html;
}

function render_custom_footer_scripts() {
    $customFooter = get_site_setting('custom_footer_scripts', '');
    return !empty($customFooter) ? $customFooter . "\n" : '';
}

function render_ad_banner($location = 'header') {
    if (get_site_setting('advertising_enabled', '0') !== '1') {
        return '';
    }
    if ($location === 'header') {
        return get_site_setting('advertising_header_banner', '');
    }
    if ($location === 'sidebar') {
        return get_site_setting('advertising_sidebar_code', '');
    }
    return '';
}

?>