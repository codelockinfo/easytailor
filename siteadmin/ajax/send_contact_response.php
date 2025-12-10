<?php
/**
 * Send Contact Response Email
 * Handles sending email responses to contact form submissions
 */

// Suppress error display for JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start output buffering to catch any unexpected output
ob_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// Load config first (it starts session)
$oldDisplayErrors = ini_get('display_errors');
ini_set('display_errors', 0);
require_once '../../config/config.php';
ini_set('display_errors', $oldDisplayErrors);

// Check if site admin is logged in
if (!isset($_SESSION['site_admin_logged_in']) || $_SESSION['site_admin_logged_in'] !== true) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    ob_end_flush();
    exit;
}

// Set JSON header early
header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    ob_end_flush();
    exit;
}

// Get form data
$messageId = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
$email = trim($_POST['email'] ?? '');
$name = trim($_POST['name'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

// Validate required fields
if (empty($email) || empty($name) || empty($subject) || empty($message)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    ob_end_flush();
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    ob_end_flush();
    exit;
}

try {
    // Clear any output
    ob_clean();
    
    // Load MailService
    require_once '../../helpers/MailService.php';
    
    $mailService = new MailService();
    
    if (!$mailService->isEnabled()) {
        // Fallback to PHP mail() if MailService is not configured
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : APP_NAME) . " <" . (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'noreply@tailorpro.com') . ">" . "\r\n";
        $headers .= "Reply-To: " . (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'noreply@tailorpro.com') . "\r\n";
        
        $emailBody = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px 10px 0 0; }
                    .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 10px 10px; }
                    .message-box { background: white; padding: 15px; border-left: 4px solid #667eea; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>" . htmlspecialchars(APP_NAME) . "</h2>
                    </div>
                    <div class='content'>
                        <p>Hello " . htmlspecialchars($name) . ",</p>
                        <p>Thank you for contacting us. We have received your message and here is our response:</p>
                        <div class='message-box'>
                            " . nl2br(htmlspecialchars($message)) . "
                        </div>
                        <p>If you have any further questions, please don't hesitate to contact us.</p>
                        <p>Best regards,<br>" . htmlspecialchars(defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : APP_NAME) . " Team</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        if (mail($email, $subject, $emailBody, $headers)) {
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Response sent successfully']);
            ob_end_flush();
            exit;
        } else {
            throw new Exception('Failed to send email using PHP mail()');
        }
    } else {
        // Use PHPMailer directly (MailService uses it internally)
        $autoload = APP_PATH . 'vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
            
            $mailer = new PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = SMTP_HOST;
            $mailer->Port = SMTP_PORT ?: 587;
            $mailer->SMTPAuth = true;
            $mailer->Username = SMTP_USERNAME;
            $mailer->Password = SMTP_PASSWORD;
            $mailer->CharSet = 'UTF-8';
            
            if (!empty(SMTP_ENCRYPTION)) {
                $mailer->SMTPSecure = SMTP_ENCRYPTION;
            }
            
            $fromName = SMTP_FROM_NAME ?: APP_NAME;
            $mailer->setFrom(SMTP_FROM_EMAIL, $fromName);
            $mailer->isHTML(true);
            
            $mailer->clearAllRecipients();
            $mailer->addAddress($email, $name);
            $mailer->Subject = $subject;
            
            // Create email body
            $emailBody = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px 10px 0 0; }
                        .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 10px 10px; }
                        .message-box { background: white; padding: 15px; border-left: 4px solid #667eea; margin: 20px 0; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>" . htmlspecialchars(APP_NAME) . "</h2>
                        </div>
                        <div class='content'>
                            <p>Hello " . htmlspecialchars($name) . ",</p>
                            <p>Thank you for contacting us. We have received your message and here is our response:</p>
                            <div class='message-box'>
                                " . nl2br(htmlspecialchars($message)) . "
                            </div>
                            <p>If you have any further questions, please don't hesitate to contact us.</p>
                            <p>Best regards,<br>" . htmlspecialchars($fromName) . " Team</p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            $mailer->Body = $emailBody;
            $mailer->AltBody = "Hello {$name},\n\nThank you for contacting us. We have received your message and here is our response:\n\n{$message}\n\nIf you have any further questions, please don't hesitate to contact us.\n\nBest regards,\n{$fromName} Team";
            
            $mailer->send();
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Response sent successfully']);
            ob_end_flush();
            exit;
        } else {
            // Fallback to PHP mail() if PHPMailer not available
            throw new Exception('PHPMailer not available');
        }
    }
    
} catch (Exception $e) {
    // Clear any output
    ob_clean();
    
    error_log('Contact response email error: ' . $e->getMessage());
    
    // Ensure JSON header is set
    header('Content-Type: application/json');
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send response. Please try again later.'
    ]);
}

// Clean output buffer and send response
ob_end_flush();
exit;
?>

