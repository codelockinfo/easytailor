<?php
/**
 * Installation Script for Tailoring Management System
 * This script helps set up the system on a new server
 */

// Check if already installed
if (file_exists('config/database.php') && file_exists('config/config.php')) {
    die('System appears to be already installed. If you want to reinstall, please delete config/database.php and config/config.php files first.');
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// Handle form submissions
if ($_POST) {
    switch ($step) {
        case 1:
            // Database configuration
            $db_host = $_POST['db_host'] ?? 'localhost';
            $db_name = $_POST['db_name'] ?? 'tailoring_management';
            $db_user = $_POST['db_user'] ?? 'root';
            $db_pass = $_POST['db_pass'] ?? '';
            $db_port = $_POST['db_port'] ?? '3306';
            
            // Test database connection
            try {
                $dsn = "mysql:host={$db_host};port={$db_port};charset=utf8mb4";
                $pdo = new PDO($dsn, $db_user, $db_pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Create database if it doesn't exist
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `{$db_name}`");
                
                // Store database config in session
                session_start();
                $_SESSION['install_db'] = [
                    'host' => $db_host,
                    'name' => $db_name,
                    'user' => $db_user,
                    'pass' => $db_pass,
                    'port' => $db_port
                ];
                
                header('Location: install.php?step=2');
                exit;
            } catch (Exception $e) {
                $error = 'Database connection failed: ' . $e->getMessage();
            }
            break;
            
        case 2:
            // Application configuration
            session_start();
            if (!isset($_SESSION['install_db'])) {
                header('Location: install.php?step=1');
                exit;
            }
            
            $app_url = $_POST['app_url'] ?? 'http://localhost/tailoring';
            $timezone = $_POST['timezone'] ?? 'Asia/Kolkata';
            $admin_username = $_POST['admin_username'] ?? 'admin';
            $admin_password = $_POST['admin_password'] ?? '';
            $admin_email = $_POST['admin_email'] ?? 'admin@tailoring.com';
            
            if (empty($admin_password)) {
                $error = 'Admin password is required';
            } else {
                // Create configuration files
                $db_config = $_SESSION['install_db'];
                
                // Create database.php
                $db_content = "<?php
class Database {
    private \$host = '{$db_config['host']}';
    private \$db_name = '{$db_config['name']}';
    private \$username = '{$db_config['user']}';
    private \$password = '{$db_config['pass']}';
    private \$charset = 'utf8mb4';
    private \$port = {$db_config['port']};
    public \$conn;

    public function getConnection() {
        \$this->conn = null;
        try {
            \$dsn = \"mysql:host=\" . \$this->host . \";port=\" . \$this->port . \";dbname=\" . \$this->db_name . \";charset=\" . \$this->charset;
            \$options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => \"SET NAMES utf8mb4\"
            ];
            \$this->conn = new PDO(\$dsn, \$this->username, \$this->password, \$options);
        } catch(PDOException \$exception) {
            echo \"Connection error: \" . \$exception->getMessage();
            die();
        }
        return \$this->conn;
    }
}
?>";
                
                // Create config.php
                $config_content = "<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0);
session_start();

define('APP_NAME', 'Tailoring Management System');
define('APP_VERSION', '1.0.0');
define('APP_URL', '{$app_url}');
define('APP_PATH', __DIR__ . '/../');

define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_TIMEOUT', 3600);
define('UPLOAD_PATH', APP_PATH . 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('RECORDS_PER_PAGE', 20);

date_default_timezone_set('{$timezone}');

spl_autoload_register(function (\$class_name) {
    \$directories = [
        APP_PATH . 'models/',
        APP_PATH . 'controllers/',
        APP_PATH . 'helpers/',
        APP_PATH . 'config/'
    ];
    foreach (\$directories as \$directory) {
        \$file = \$directory . \$class_name . '.php';
        if (file_exists(\$file)) {
            require_once \$file;
            return;
        }
    }
});

function sanitize_input(\$data) {
    \$data = trim(\$data);
    \$data = stripslashes(\$data);
    \$data = htmlspecialchars(\$data);
    return \$data;
}

function generate_csrf_token() {
    if (!isset(\$_SESSION[CSRF_TOKEN_NAME])) {
        \$_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return \$_SESSION[CSRF_TOKEN_NAME];
}

function verify_csrf_token(\$token) {
    return isset(\$_SESSION[CSRF_TOKEN_NAME]) && hash_equals(\$_SESSION[CSRF_TOKEN_NAME], \$token);
}

function redirect(\$url) {
    header(\"Location: \" . \$url);
    exit();
}

function format_currency(\$amount, \$currency = 'INR') {
    return '₹' . number_format(\$amount, 2);
}

function format_date(\$date, \$format = 'Y-m-d') {
    return date(\$format, strtotime(\$date));
}

function is_logged_in() {
    return isset(\$_SESSION['user_id']) && !empty(\$_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        redirect(APP_URL . '/login.php');
    }
}

function has_role(\$role) {
    return isset(\$_SESSION['user_role']) && \$_SESSION['user_role'] === \$role;
}

function require_role(\$role) {
    require_login();
    if (!has_role(\$role) && !has_role('admin')) {
        redirect(APP_URL . '/unauthorized.php');
    }
}

function get_user_id() {
    return \$_SESSION['user_id'] ?? null;
}

function get_user_role() {
    return \$_SESSION['user_role'] ?? null;
}

function get_user_name() {
    return \$_SESSION['user_name'] ?? null;
}

function get_company_id() {
    return \$_SESSION['company_id'] ?? null;
}

function set_company_id(\$company_id) {
    \$_SESSION['company_id'] = \$company_id;
}

if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}

\$upload_subdirs = ['customers', 'orders', 'measurements', 'receipts', 'logos', 'measurement-charts'];
foreach (\$upload_subdirs as \$subdir) {
    \$dir = UPLOAD_PATH . \$subdir . '/';
    if (!file_exists(\$dir)) {
        mkdir(\$dir, 0755, true);
    }
}
?>";
                
                // Write configuration files
                file_put_contents('config/database.php', $db_content);
                file_put_contents('config/config.php', $config_content);
                
                // Import database schema
                try {
                    $pdo = new PDO("mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['name']};charset=utf8mb4", 
                                  $db_config['user'], $db_config['pass']);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    $sql = file_get_contents('database/complete_setup.sql');
                    $pdo->exec($sql);
                    
                    // Update admin user with new password
                    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ? WHERE role = 'admin'");
                    $stmt->execute([$admin_username, $admin_email, $hashed_password]);
                    
                    $success = 'Installation completed successfully!';
                    $step = 3;
                } catch (Exception $e) {
                    $error = 'Database setup failed: ' . $e->getMessage();
                }
            }
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - Tailoring Management System</title>
    <!-- Favicon - Primary ICO format for Google Search -->
    <link rel="icon" type="image/x-icon" href="favicon.ico" sizes="16x16 32x32 48x48">
    <!-- Favicon - PNG fallback -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon(2).png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon(2).png">
    <!-- Apple Touch Icon -->
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/favicon(2).png">
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .container { background: #f9f9f9; padding: 30px; border-radius: 8px; }
        .step { margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007cba; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #005a87; }
        .error { color: #d63638; background: #fcf0f1; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .success { color: #00a32a; background: #f0f6fc; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
        .progress { background: #e1e1e1; height: 20px; border-radius: 10px; margin-bottom: 20px; }
        .progress-bar { background: #007cba; height: 100%; border-radius: 10px; transition: width 0.3s; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Tailoring Management System - Installation</h1>
        
        <div class="progress">
            <div class="progress-bar" style="width: <?php echo ($step / 3) * 100; ?>%"></div>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($step == 1): ?>
            <h2>Step 1: Database Configuration</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="db_host">Database Host:</label>
                    <input type="text" id="db_host" name="db_host" value="localhost" required>
                </div>
                
                <div class="form-group">
                    <label for="db_port">Database Port:</label>
                    <input type="number" id="db_port" name="db_port" value="3306" required>
                </div>
                
                <div class="form-group">
                    <label for="db_name">Database Name:</label>
                    <input type="text" id="db_name" name="db_name" value="tailoring_management" required>
                </div>
                
                <div class="form-group">
                    <label for="db_user">Database Username:</label>
                    <input type="text" id="db_user" name="db_user" value="root" required>
                </div>
                
                <div class="form-group">
                    <label for="db_pass">Database Password:</label>
                    <input type="password" id="db_pass" name="db_pass">
                </div>
                
                <button type="submit">Test Connection & Continue</button>
            </form>
            
        <?php elseif ($step == 2): ?>
            <h2>Step 2: Application Configuration</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="app_url">Application URL:</label>
                    <input type="url" id="app_url" name="app_url" value="http://localhost/tailoring" required>
                </div>
                
                <div class="form-group">
                    <label for="timezone">Timezone:</label>
                    <select id="timezone" name="timezone">
                        <option value="Asia/Kolkata">Asia/Kolkata (IST)</option>
                        <option value="UTC">UTC</option>
                        <option value="America/New_York">America/New_York (EST)</option>
                        <option value="Europe/London">Europe/London (GMT)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="admin_username">Admin Username:</label>
                    <input type="text" id="admin_username" name="admin_username" value="admin" required>
                </div>
                
                <div class="form-group">
                    <label for="admin_email">Admin Email:</label>
                    <input type="email" id="admin_email" name="admin_email" value="admin@tailoring.com" required>
                </div>
                
                <div class="form-group">
                    <label for="admin_password">Admin Password:</label>
                    <input type="password" id="admin_password" name="admin_password" required>
                </div>
                
                <button type="submit">Install System</button>
            </form>
            
        <?php elseif ($step == 3): ?>
            <h2>Installation Complete!</h2>
            <p>Your Tailoring Management System has been successfully installed.</p>
            
            <h3>Next Steps:</h3>
            <ol>
                <li>Delete this installation file (install.php) for security</li>
                <li>Set proper file permissions for the uploads directory</li>
                <li>Access your application at: <a href="<?php echo $_SESSION['install_db']['app_url'] ?? 'http://localhost/tailoring'; ?>"><?php echo $_SESSION['install_db']['app_url'] ?? 'http://localhost/tailoring'; ?></a></li>
                <li>Login with your admin credentials</li>
                <li>Configure your company settings</li>
            </ol>
            
            <p><strong>Important:</strong> For security reasons, please delete this installation file after completing the setup.</p>
            
            <a href="login.php"><button>Go to Login Page</button></a>
        <?php endif; ?>
    </div>
</body>
</html>
