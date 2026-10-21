<?php
/**
 * Email Testing Tool
 * Test if email functionality is working on your server
 */

require_once '../config/config.php';
require_once '../helpers/MailService.php';

$message = '';
$success = false;
$smtp_debug_log = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_email = sanitize_input($_POST['test_email'] ?? '');
    
    if (!empty($test_email) && filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $subject = "Test Email - " . APP_NAME;
        
        // Try PHPMailer via MailService first
        $autoload = APP_PATH . 'vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mailer->isSMTP();
                $mailer->Host = SMTP_HOST;
                $port = (int)(SMTP_PORT ?: 587);
                $mailer->Port = $port;
                $mailer->SMTPAuth = true;
                $mailer->Username = SMTP_USERNAME;
                $mailer->Password = SMTP_PASSWORD;
                $mailer->CharSet = 'UTF-8';

                $encryption = strtolower(trim((string)(defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : '')));
                if ($port === 465 && ($encryption === 'tls' || empty($encryption))) {
                    $encryption = 'ssl';
                } elseif ($port === 587 && ($encryption === 'ssl' || empty($encryption))) {
                    $encryption = 'tls';
                }

                if (!empty($encryption)) {
                    $mailer->SMTPSecure = $encryption;
                }

                $mailer->SMTPDebug = 2;
                $mailer->Debugoutput = function($str, $level) use (&$smtp_debug_log) {
                    $smtp_debug_log .= $str . "\n";
                };

                $fromName = SMTP_FROM_NAME ?: APP_NAME;
                $fromEmail = SMTP_FROM_EMAIL ?: SMTP_USERNAME;
                $mailer->setFrom($fromEmail, $fromName);
                $mailer->addAddress($test_email);
                $mailer->isHTML(true);
                $mailer->Subject = $subject;
                $mailer->Body = "<h2>Email Test Successful</h2><p>Your SMTP configuration is working properly on " . htmlspecialchars($_SERVER['HTTP_HOST'] ?? '') . ".</p>";
                $mailer->AltBody = "Email Test Successful. Your SMTP configuration is working properly.";

                $mailer->send();
                $success = true;
                $message = "✓ Test email sent successfully via SMTP to " . htmlspecialchars($test_email) . ".";
            } catch (\Exception $e) {
                $success = false;
                $message = "✗ SMTP Failed: " . htmlspecialchars($e->getMessage());
            }
        } else {
            // Fallback to mail()
            $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: " . (SMTP_FROM_EMAIL ?: 'noreply@' . $_SERVER['HTTP_HOST']) . "\r\n";
            if (@mail($test_email, $subject, "<h2>Email Test Successful</h2><p>Sent via PHP mail()</p>", $headers)) {
                $success = true;
                $message = "✓ Test email sent successfully via mail() to " . htmlspecialchars($test_email) . ".";
            } else {
                $message = "✗ Failed to send email via mail(). Please configure SMTP.";
            }
        }
    } else {
        $message = "Please enter a valid email address.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Test - <?php echo APP_NAME; ?></title>
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
        .test-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        .test-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
            text-align: center;
        }
        .btn-test {
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
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .server-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card test-card border-0">
                    <div class="test-header">
                        <i class="fas fa-envelope-open-text fa-3x mb-3"></i>
                        <h3 class="mb-0">Email Configuration Test</h3>
                        <p class="mb-0 opacity-75">Verify your email setup is working</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($smtp_debug_log)): ?>
                            <div class="card mb-3">
                                <div class="card-header bg-dark text-white py-1">
                                    <small><i class="fas fa-terminal me-1"></i>SMTP Diagnostic Log</small>
                                </div>
                                <div class="card-body bg-light p-2">
                                    <pre style="max-height: 200px; overflow-y: auto; font-size: 11px; margin-bottom: 0;"><?php echo htmlspecialchars($smtp_debug_log); ?></pre>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="info-box">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Why test email?</strong>
                            <p class="mb-0 mt-2">The forgot password feature requires email to send verification codes. Use this tool to verify your server can send emails.</p>
                        </div>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="test_email" class="form-label">Enter Your Email Address</label>
                                <input type="email" 
                                       class="form-control" 
                                       id="test_email" 
                                       name="test_email" 
                                       placeholder="your-email@example.com"
                                       required>
                                <small class="text-muted">We'll send a test email to this address</small>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-test">
                                    <i class="fas fa-paper-plane me-2"></i>
                                    Send Test Email
                                </button>
                                
                                <a href="login.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    Back to Login
                                </a>
                            </div>
                        </form>
                        
                        <div class="warning-box mt-4">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Email Not Working?</strong>
                            <ul class="mb-0 mt-2">
                                <li>Check your spam/junk folder</li>
                                <li>Verify PHP mail configuration</li>
                                <li>Check sendmail/postfix is installed</li>
                                <li>Review server error logs</li>
                                <li>Consider using SMTP for production</li>
                            </ul>
                        </div>
                        
                        <div class="server-info">
                            <h6><i class="fas fa-server me-2"></i>Email Configuration Status</h6>
                            <small>
                                <strong>PHP Version:</strong> <?php echo phpversion(); ?><br>
                                <strong>SMTP Host:</strong> <?php echo defined('SMTP_HOST') && SMTP_HOST ? htmlspecialchars(SMTP_HOST) : '<span class="text-danger">Not set</span>'; ?><br>
                                <strong>SMTP Port:</strong> <?php echo defined('SMTP_PORT') ? htmlspecialchars((string)SMTP_PORT) : '587'; ?><br>
                                <strong>SMTP Encryption:</strong> <?php echo defined('SMTP_ENCRYPTION') ? htmlspecialchars(SMTP_ENCRYPTION) : 'None'; ?><br>
                                <strong>SMTP Username:</strong> <?php echo defined('SMTP_USERNAME') && SMTP_USERNAME ? htmlspecialchars(SMTP_USERNAME) : '<span class="text-danger">Not set</span>'; ?><br>
                                <strong>From Email:</strong> <?php echo defined('SMTP_FROM_EMAIL') && SMTP_FROM_EMAIL ? htmlspecialchars(SMTP_FROM_EMAIL) : '<span class="text-danger">Not set</span>'; ?><br>
                                <strong>Config File:</strong> <?php echo file_exists(__DIR__ . '/../config/mail.php') ? '<span class="text-success">config/mail.php loaded</span>' : '<span class="text-warning">config/mail.php missing</span>'; ?>
                            </small>
                        </div>
                        
                        <div class="text-center mt-4">
                            <small class="text-muted">
                                <i class="fas fa-book me-1"></i>
                                For setup instructions, see <code>FORGOT_PASSWORD_SETUP.md</code>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

