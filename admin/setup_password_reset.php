<?php
/**
 * Password Reset Setup Script
 * Run this once to create the password_resets table
 */

require_once '../config/config.php';
require_once '../config/database.php';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Create password_resets table
        $sql = "CREATE TABLE IF NOT EXISTS `password_resets` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `email` varchar(100) NOT NULL,
          `code` varchar(6) NOT NULL,
          `token` varchar(64) NOT NULL,
          `expires_at` timestamp NOT NULL,
          `used` tinyint(1) NOT NULL DEFAULT 0,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `email` (`email`),
          KEY `token` (`token`),
          KEY `expires_at` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $conn->exec($sql);
        
        // Create index
        $sql = "CREATE INDEX IF NOT EXISTS idx_email_code ON password_resets(email, code, used)";
        $conn->exec($sql);
        
        $success = true;
        $message = "✓ Password reset table created successfully! You can now use the forgot password feature.";
        
    } catch (Exception $e) {
        $message = "✗ Error: " . $e->getMessage();
    }
}

// Check if table already exists
try {
    $database = new Database();
    $conn = $database->getConnection();
    $result = $conn->query("SHOW TABLES LIKE 'password_resets'");
    $tableExists = $result->rowCount() > 0;
} catch (Exception $e) {
    $tableExists = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Password Reset - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
        }
        .setup-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        .setup-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
            text-align: center;
        }
        .btn-setup {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .success-box {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .code-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            font-family: monospace;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-7">
                <div class="card setup-card border-0">
                    <div class="setup-header">
                        <i class="fas fa-cog fa-3x mb-3"></i>
                        <h3 class="mb-0">Password Reset Setup</h3>
                        <p class="mb-0 opacity-75">Configure forgot password functionality</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($tableExists): ?>
                            <div class="success-box">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Setup Complete!</strong>
                                <p class="mb-0 mt-2">The password reset table already exists. The forgot password feature is ready to use.</p>
                            </div>
                            
                            <div class="info-box">
                                <strong><i class="fas fa-info-circle me-2"></i>What's Next?</strong>
                                <ul class="mb-0 mt-2">
                                    <li><a href="test_email.php">Test email configuration</a></li>
                                    <li><a href="login.php">Go to login page</a></li>
                                    <li><a href="forgot-password.php">Try forgot password</a></li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <div class="info-box">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Setup Required</strong>
                                <p class="mb-0 mt-2">Click the button below to create the password reset table in your database. This is a one-time setup.</p>
                            </div>
                            
                            <div class="warning-box">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Before proceeding:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Ensure database connection is configured</li>
                                    <li>Verify you have database write permissions</li>
                                    <li>Backup your database (recommended)</li>
                                </ul>
                            </div>
                            
                            <form method="POST" action="" onsubmit="return confirm('Are you sure you want to create the password reset table?');">
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-setup">
                                        <i class="fas fa-database me-2"></i>
                                        Create Password Reset Table
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                        
                        <hr class="my-4">
                        
                        <div class="accordion" id="detailsAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                                        <i class="fas fa-table me-2"></i> Table Structure
                                    </button>
                                </h2>
                                <div id="collapseOne" class="accordion-collapse collapse" data-bs-parent="#detailsAccordion">
                                    <div class="accordion-body">
                                        <p>The following table will be created:</p>
                                        <div class="code-box">
                                            <strong>Table:</strong> password_resets<br>
                                            <strong>Columns:</strong><br>
                                            - id (INT, PRIMARY KEY)<br>
                                            - email (VARCHAR 100)<br>
                                            - code (VARCHAR 6)<br>
                                            - token (VARCHAR 64)<br>
                                            - expires_at (TIMESTAMP)<br>
                                            - used (TINYINT)<br>
                                            - created_at (TIMESTAMP)<br>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                                        <i class="fas fa-shield-alt me-2"></i> Security Features
                                    </button>
                                </h2>
                                <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#detailsAccordion">
                                    <div class="accordion-body">
                                        <ul>
                                            <li><strong>6-digit codes:</strong> Randomly generated verification codes</li>
                                            <li><strong>Secure tokens:</strong> 64-character cryptographic tokens</li>
                                            <li><strong>Time expiration:</strong> Codes expire after 15 minutes</li>
                                            <li><strong>One-time use:</strong> Codes can only be used once</li>
                                            <li><strong>Password hashing:</strong> All passwords are securely hashed</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree">
                                        <i class="fas fa-book me-2"></i> Documentation
                                    </button>
                                </h2>
                                <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#detailsAccordion">
                                    <div class="accordion-body">
                                        <p>For complete setup instructions and troubleshooting:</p>
                                        <ul>
                                            <li>Read <code>FORGOT_PASSWORD_SETUP.md</code> in the project root</li>
                                            <li>Configure email settings in <code>php.ini</code></li>
                                            <li>Test email functionality using <a href="test_email.php">Email Test Tool</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="login.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>
                                Back to Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

