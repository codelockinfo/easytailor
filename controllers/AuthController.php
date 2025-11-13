<?php
/**
 * Authentication Controller
 * Tailoring Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/PasswordReset.php';
require_once __DIR__ . '/../helpers/MailService.php';

class AuthController {
    private $userModel;
    private $passwordResetModel;
    private $mailService;

    public function __construct() {
        $this->userModel = new User();
        $this->passwordResetModel = new PasswordReset();
        $this->mailService = new MailService();
    }

    /**
     * Login user
     */
    public function login($username, $password) {
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Username and password are required'];
        }

        $user = $this->userModel->authenticate($username, $password);
        
        if ($user) {
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['company_id'] = $user['company_id'];
            $_SESSION['login_time'] = time();
            
            // Note: last_login column is optional - skip if doesn't exist
            try {
                $this->userModel->update($user['id'], ['last_login' => date('Y-m-d H:i:s')]);
            } catch (Exception $e) {
                // Column doesn't exist, ignore this error
            }
            
            return ['success' => true, 'message' => 'Login successful', 'user' => $user];
        } else {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }
    }

    /**
     * Logout user
     */
    public function logout() {
        // Destroy session
        session_destroy();
        
        // Clear session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        return ['success' => true, 'message' => 'Logged out successfully'];
    }

    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_time'])) {
            return false;
        }
        
        // Check session timeout
        if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
            $this->logout();
            return false;
        }
        
        // Update last activity time
        $_SESSION['login_time'] = time();
        
        return true;
    }

    /**
     * Change password
     */
    public function changePassword($user_id, $current_password, $new_password) {
        $user = $this->userModel->find($user_id);
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        if (!password_verify($current_password, $user['password'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        if (strlen($new_password) < 6) {
            return ['success' => false, 'message' => 'New password must be at least 6 characters long'];
        }
        
        $result = $this->userModel->updatePassword($user_id, $new_password);
        
        if ($result) {
            return ['success' => true, 'message' => 'Password changed successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to change password'];
        }
    }

    /**
     * Reset password (admin only)
     */
    public function resetPassword($user_id, $new_password) {
        if (strlen($new_password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters long'];
        }
        
        $result = $this->userModel->updatePassword($user_id, $new_password);
        
        if ($result) {
            return ['success' => true, 'message' => 'Password reset successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to reset password'];
        }
    }

    /**
     * Get current user
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['user_role'],
            'name' => $_SESSION['user_name']
        ];
    }

    /**
     * Check user role
     */
    public function hasRole($role) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        return $_SESSION['user_role'] === $role || $_SESSION['user_role'] === 'admin';
    }

    /**
     * Require specific role
     */
    public function requireRole($role) {
        if (!$this->hasRole($role)) {
            http_response_code(403);
            die(json_encode(['success' => false, 'message' => 'Access denied']));
        }
    }

    /**
     * Request password reset - sends 6-digit code to email
     */
    public function requestPasswordReset($email) {
        if (empty($email)) {
            return ['success' => false, 'message' => 'Email is required'];
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }

        // Check if user exists
        $user = $this->passwordResetModel->getUserByEmail($email);

        if (!$user) {
            return ['success' => false, 'message' => 'No account found with this email address'];
        }

        // Generate 6-digit code
        $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Generate unique token
        $token = bin2hex(random_bytes(32));
        
        // Set expiration time (15 minutes from now)
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // Store in database
        $resetId = $this->passwordResetModel->createResetRequest($email, $code, $token, $expires_at);
        
        if (!$resetId) {
            return ['success' => false, 'message' => 'Failed to process password reset request'];
        }

        // Send email with code
        $emailSent = false;
        $mailError = '';

        if ($this->mailService->isEnabled()) {
            $emailSent = $this->mailService->sendPasswordResetEmail([
                'email' => $user['email'],
                'name' => $user['full_name'],
                'code' => $code,
                'token' => $token
            ]);
            $mailError = $this->mailService->getLastError();
        }

        if (!$emailSent) {
            $emailSent = $this->sendPasswordResetEmailFallback($user['email'], $user['full_name'], $code, $token);
            if (!$emailSent && empty($mailError)) {
                $mailError = 'Unable to dispatch email via SMTP or fallback mail().';
            }
        }

        // Development mode: Show code if email fails
        $message = 'Password reset code has been sent to your email';
        if (!$emailSent) {
            $message = "EMAIL NOT CONFIGURED - Your verification code is: <strong>$code</strong> (Valid for 15 minutes)";
            if (!empty($mailError)) {
                error_log('Password reset email failed: ' . $mailError);
            }
        }

        return [
            'success' => true, 
            'message' => $message,
            'token' => $token,
            'email' => $email
        ];
    }

    /**
     * Verify password reset code
     */
    public function verifyResetCode($email, $code) {
        if (empty($email) || empty($code)) {
            return ['success' => false, 'message' => 'Email and code are required'];
        }

        // Find valid reset request
        $reset = $this->passwordResetModel->findByEmailAndCode($email, $code);

        if (!$reset) {
            return ['success' => false, 'message' => 'Invalid verification code'];
        }

        // Check if expired
        if (strtotime($reset['expires_at']) < time()) {
            return ['success' => false, 'message' => 'Verification code has expired. Please request a new one.'];
        }

        return [
            'success' => true,
            'message' => 'Code verified successfully',
            'token' => $reset['token']
        ];
    }

    /**
     * Reset password with token
     */
    public function resetPasswordWithToken($token, $new_password, $confirm_password) {
        if (empty($token) || empty($new_password) || empty($confirm_password)) {
            return ['success' => false, 'message' => 'All fields are required'];
        }

        // Check if passwords match
        if ($new_password !== $confirm_password) {
            return ['success' => false, 'message' => 'Passwords do not match'];
        }

        // Validate password strength
        if (strlen($new_password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters long'];
        }

        // Find valid reset request
        $reset = $this->passwordResetModel->findByToken($token);

        if (!$reset) {
            return ['success' => false, 'message' => 'Invalid or expired reset token'];
        }

        // Check if expired
        if (strtotime($reset['expires_at']) < time()) {
            return ['success' => false, 'message' => 'Reset token has expired. Please request a new one.'];
        }

        // Get user
        $user = $this->passwordResetModel->getUserByEmail($reset['email']);

        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        // Update password
        $result = $this->userModel->updatePassword($user['id'], $new_password);

        if (!$result) {
            return ['success' => false, 'message' => 'Failed to update password'];
        }

        // Mark token as used
        $this->passwordResetModel->markAsUsed($reset['id']);

        return ['success' => true, 'message' => 'Password has been reset successfully'];
    }

    /**
     * Validate reset token without updating password
     */
    public function validateResetToken($token) {
        if (empty($token)) {
            return ['success' => false, 'message' => 'Reset token is required'];
        }

        $reset = $this->passwordResetModel->findByToken($token);

        if (!$reset) {
            return ['success' => false, 'message' => 'Invalid or expired reset token'];
        }

        if (strtotime($reset['expires_at']) < time()) {
            return ['success' => false, 'message' => 'Reset token has expired. Please request a new one.'];
        }

        return [
            'success' => true,
            'message' => 'Token is valid',
            'email' => $reset['email']
        ];
    }

    /**
     * Send password reset email
     */
    private function sendPasswordResetEmailFallback($email, $name, $code, $token) {
        $subject = "Password Reset Code - " . APP_NAME;

        $baseUrl = rtrim(APP_URL, '/');
        if (substr($baseUrl, -6) === '/admin') {
            $baseUrl = substr($baseUrl, 0, -6);
        }
        $resetUrl = $baseUrl . '/admin/reset-password.php?token=' . urlencode($token);
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .code-box { background: white; border: 2px dashed #667eea; padding: 20px; text-align: center; margin: 20px 0; border-radius: 10px; }
                .code { font-size: 32px; font-weight: bold; color: #667eea; letter-spacing: 5px; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
                .button { background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Password Reset Request</h1>
                </div>
                <div class='content'>
                    <p>Hello " . htmlspecialchars($name) . ",</p>
                    <p>We received a request to reset your password for your " . APP_NAME . " account.</p>
                    <p>Your 6-digit verification code is:</p>
                    
                    <div class='code-box'>
                        <div class='code'>" . $code . "</div>
                    </div>
                    
                    <p style='text-align:center;'>
                        <a href='" . $resetUrl . "' class='button'>Reset Password</a>
                    </p>
                    
                    <p><strong>This code will expire in 15 minutes.</strong></p>
                    <p>If you didn't request this password reset, please ignore this email or contact support if you have concerns.</p>
                    
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " " . APP_NAME . ". All rights reserved.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";

        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . APP_NAME . " <noreply@tailoring.com>" . "\r\n";

        return mail($email, $subject, $message, $headers);
    }
}
?>

