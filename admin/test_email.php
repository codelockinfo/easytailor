<?php
/**
 * Email Testing Tool
 * Test if email functionality is working on your server
 */

require_once '../config/config.php';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_email = sanitize_input($_POST['test_email'] ?? '');
    
    if (!empty($test_email) && filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $subject = "Test Email - " . APP_NAME;
        
        $email_body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .success-badge { background: #28a745; color: white; padding: 10px 20px; border-radius: 5px; display: inline-block; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>✓ Email Test Successful</h1>
                </div>
                <div class='content'>
                    <p>Congratulations!</p>
                    <p>If you're reading this, it means your email configuration is working correctly.</p>
                    <div class='success-badge'>
                        Email System Working!
                    </div>
                    <p><strong>Server Information:</strong></p>
                    <ul>
                        <li>Server Time: " . date('Y-m-d H:i:s') . "</li>
                        <li>PHP Version: " . phpversion() . "</li>
                        <li>Application: " . APP_NAME . "</li>
                    </ul>
                    <p>You can now use the forgot password feature with confidence.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . APP_NAME . " <noreply@tailoring.com>" . "\r\n";

        if (mail($test_email, $subject, $email_body, $headers)) {
            $success = true;
            $message = "✓ Test email sent successfully to " . htmlspecialchars($test_email) . ". Please check your inbox (and spam folder).";
        } else {
            $message = "✗ Failed to send email. Please check your server's email configuration.";
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
                            <h6><i class="fas fa-server me-2"></i>Server Information</h6>
                            <small>
                                <strong>PHP Version:</strong> <?php echo phpversion(); ?><br>
                                <strong>Mail Function:</strong> <?php echo function_exists('mail') ? '✓ Available' : '✗ Not Available'; ?><br>
                                <strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?><br>
                                <strong>Timezone:</strong> <?php echo date_default_timezone_get(); ?>
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

