<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/PasswordReset.php';
require_once __DIR__ . '/../models/LoginOtp.php';
require_once __DIR__ . '/../helpers/MailService.php';
class AuthController {
    private $userModel;
    private $passwordResetModel;
    private $mailService;
    private $loginOtpModel;
    public function __construct() {
        $this->userModel = new User();
        $this->passwordResetModel = new PasswordReset();
        $this->mailService = new MailService();
        $this->loginOtpModel = new LoginOtp();
    }
    public function completeUserLogin($user) {
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        require_once __DIR__ . '/../admin/models/Company.php';
        $companyModel = new Company();
        $company = $companyModel->find($user['company_id']);
        if ($company && $company['subscription_expiry']) {
            $expiry = strtotime($company['subscription_expiry']);
            $today = strtotime(date('Y-m-d'));
            if ($expiry < $today) {
                return ['success' => false, 'message' => 'Your subscription package has expired. Please contact support to renew.'];
            }
        }
        if (!headers_sent()) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['company_id'] = $user['company_id'];
        $_SESSION['login_time'] = time();
                try {
            $this->userModel->update($user['id'], ['last_login' => date('Y-m-d H:i:s')]);
        } catch (Exception $e) {
        }
        if (file_exists(__DIR__ . '/../helpers/GA4Helper.php')) {
            require_once __DIR__ . '/../helpers/GA4Helper.php';
            $_SESSION['ga4_event'] = GA4Helper::trackLogin($user['id'] ?? null, $user['role'] ?? null);
        }

        $redirect = (($user['role'] ?? '') === 'tailor') ? 'orders.php' : 'dashboard.php';
        
        return [
            'success' => true,
            'message' => 'Login successful',
            'user' => $user,
            'redirect' => $redirect
        ];
    }
    public function login($username, $password) {
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Username and password are required'];
        }

        $user = $this->userModel->authenticate($username, $password);
        
        if ($user) {
            return $this->completeUserLogin($user);
        } else {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }
    }

    public function loginWithPasswordOtp($username, $password, $clientIp = null) {
        $username = trim(sanitize_input($username ?? ''));
        $password = $password ?? '';

        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Username and password are required.'];
        }

        // Special check for site admin
        if ($username === 'codelock2021@gmail.com' && $password === 'Codelock@63') {
            $_SESSION['site_admin_logged_in'] = true;
            $_SESSION['site_admin_email'] = $username;
            return [
                'success' => true,
                'requires_otp' => false,
                'redirect' => '../siteadmin/index.php'
            ];
        }

        $user = $this->userModel->authenticate($username, $password);
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        if (empty($user['email']) || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'No valid email address found for this user to receive OTP.'];
        }

        // Generate and send OTP to the user's verified registered email
        $otpResult = $this->requestLoginOtp($user['email'], $clientIp);
        if (!$otpResult['success']) {
            return $otpResult;
        }

        return [
            'success' => true,
            'requires_otp' => true,
            'email' => $user['email'],
            'message' => $otpResult['message'] ?? 'Password verified! Verification code has been sent to your email.',
            'cooldown' => $otpResult['cooldown'] ?? 60,
            'dev_otp' => $otpResult['dev_otp'] ?? null
        ];
    }

    public function requestLoginOtp($email, $ipAddress = null) {
        $email = trim(sanitize_input($email ?? ''));

        if (empty($email)) {
            return ['success' => false, 'message' => 'Please enter your registered email address.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Please enter a valid email address.'];
        }
        $user = $this->userModel->getActiveUserByEmail($email);
        if (!$user) {
            return ['success' => false, 'message' => 'No active account found with this email address.'];
        }
        $cooldown = $this->loginOtpModel->getRecentRequestCooldown($email, 60);
        if ($cooldown > 0) {
            return [
                'success' => false,
                'message' => "Please wait {$cooldown} seconds before requesting a new OTP.",
                'cooldown' => $cooldown
            ];
        }
        $code = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $saved = $this->loginOtpModel->createOtp($user['id'], $user['email'], $code, $token, $expiresAt, $ipAddress);
        if (!$saved) {
            return ['success' => false, 'message' => 'Failed to generate OTP. Please try again.'];
        }
        $emailSent = false;
        $mailError = '';
        if ($this->mailService->isEnabled()) {
            $emailSent = $this->mailService->sendLoginOtpEmail([
                'email' => $user['email'],
                'name' => $user['full_name'],
                'code' => $code
            ]);
            if (!$emailSent) {
                $mailError = $this->mailService->getLastError();
            }
        } else {
            $mailError = $this->mailService->getLastError();
        }

        if (!$emailSent) {
            $subject = 'Your Login Verification Code - ' . APP_NAME;
            $msg = "Hello {$user['full_name']},\n\nYour login code is: {$code}\nValid for 10 minutes.";
            $headers = 'From: ' . (defined('SMTP_FROM_EMAIL') && SMTP_FROM_EMAIL ? SMTP_FROM_EMAIL : 'no-reply@localhost') . "\r\n";
            @mail($user['email'], $subject, $msg, $headers);
        }

        $isLocalhost = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', '']) || 
                       strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false ||
                       strpos($_SERVER['SERVER_NAME'] ?? '', 'localhost') !== false ||
                       (php_sapi_name() === 'cli');

        if (!$emailSent) {
            if ($isLocalhost) {
                return [
                    'success' => true,
                    'message' => "SMTP is not configured on localhost. Your OTP is: <strong>{$code}</strong>",
                    'email' => $user['email'],
                    'cooldown' => 60,
                    'dev_otp' => $code
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Unable to send email. Please check SMTP configuration.' . (!empty($mailError) ? " ({$mailError})" : '')
                ];
            }
        }

        return [
            'success' => true,
            'message' => 'Verification code has been sent to your email address.',
            'email' => $user['email'],
            'cooldown' => 60
        ];
    }
    public function verifyLoginOtp($email, $code) {
        $email = trim(sanitize_input($email ?? ''));
        $code = trim(sanitize_input($code ?? ''));

        if (empty($email) || empty($code)) {
            return ['success' => false, 'message' => 'Email and 6-digit verification code are required.'];
        }

        if (!preg_match('/^\d{6}$/', $code)) {
            return ['success' => false, 'message' => 'Please enter a valid 6-digit numeric code.'];
        }
        $otp = $this->loginOtpModel->findLatestActiveOtp($email);
        if (!$otp) {
            return ['success' => false, 'message' => 'No active OTP found or code already used. Please request a new code.'];
        }
        if ((int)$otp['attempts'] >= 5) {
            $this->loginOtpModel->invalidateOtp($otp['id']);
            return ['success' => false, 'message' => 'Too many failed attempts. This OTP has been invalidated. Please request a new one.'];
        }
        if (strtotime($otp['expires_at']) < time()) {
            $this->loginOtpModel->invalidateOtp($otp['id']);
            return ['success' => false, 'message' => 'Verification code has expired. Please request a new one.'];
        }
        if (!hash_equals((string)$otp['otp_code'], (string)$code)) {
            $this->loginOtpModel->incrementAttempts($otp['id']);
            $remaining = 5 - ((int)$otp['attempts'] + 1);
            $remainMsg = $remaining > 0 ? " You have {$remaining} attempt(s) remaining." : " Maximum attempts reached.";
            return ['success' => false, 'message' => 'Invalid verification code.' . $remainMsg];
        }
        $this->loginOtpModel->markAsUsed($otp['id']);
        $user = $this->userModel->find($otp['user_id']);
        if (!$user || ($user['status'] ?? '') !== 'active') {
            return ['success' => false, 'message' => 'User account is inactive or not found.'];
        }
        return $this->completeUserLogin($user);
    }
    public function logout() {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        return ['success' => true, 'message' => 'Logged out successfully'];
    }
    public function isLoggedIn() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_time'])) {
            return false;
        }
        if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
            $this->logout();
            return false;
        }
        $_SESSION['login_time'] = time();
        
        return true;
    }
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
    public function hasRole($role) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        return $_SESSION['user_role'] === $role || $_SESSION['user_role'] === 'admin';
    }
    public function requireRole($role) {
        if (!$this->hasRole($role)) {
            http_response_code(403);
            die(json_encode(['success' => false, 'message' => 'Access denied']));
        }
    }

    public function requestPasswordReset($email) {
        if (empty($email)) {
            return ['success' => false, 'message' => 'Email is required'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }
        $user = $this->passwordResetModel->getUserByEmail($email);

        if (!$user) {
            return ['success' => false, 'message' => 'No account found with this email address'];
        }
        $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $resetId = $this->passwordResetModel->createResetRequest($email, $code, $token, $expires_at);
        
        if (!$resetId) {
            return ['success' => false, 'message' => 'Failed to process password reset request'];
        }
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
    public function verifyResetCode($email, $code) {
        if (empty($email) || empty($code)) {
            return ['success' => false, 'message' => 'Email and code are required'];
        }
        $reset = $this->passwordResetModel->findByEmailAndCode($email, $code);

        if (!$reset) {
            return ['success' => false, 'message' => 'Invalid verification code'];
        }
        if (strtotime($reset['expires_at']) < time()) {
            return ['success' => false, 'message' => 'Verification code has expired. Please request a new one.'];
        }

        return [
            'success' => true,
            'message' => 'Code verified successfully',
            'token' => $reset['token']
        ];
    }
    public function resetPasswordWithToken($token, $new_password, $confirm_password) {
        if (empty($token) || empty($new_password) || empty($confirm_password)) {
            return ['success' => false, 'message' => 'All fields are required'];
        }
        if ($new_password !== $confirm_password) {
            return ['success' => false, 'message' => 'Passwords do not match'];
        }
        if (strlen($new_password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters long'];
        }
        $reset = $this->passwordResetModel->findByToken($token);

        if (!$reset) {
            return ['success' => false, 'message' => 'Invalid or expired reset token'];
        }
        if (strtotime($reset['expires_at']) < time()) {
            return ['success' => false, 'message' => 'Reset token has expired. Please request a new one.'];
        }
        $user = $this->passwordResetModel->getUserByEmail($reset['email']);

        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        $result = $this->userModel->updatePassword($user['id'], $new_password);

        if (!$result) {
            return ['success' => false, 'message' => 'Failed to update password'];
        }
        $this->passwordResetModel->markAsUsed($reset['id']);

        return ['success' => true, 'message' => 'Password has been reset successfully'];
    }
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
                .button { background: #667eea; color: #ffffff !important; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
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
                    
                    <p style='text-align:center; color: white;'>
                        <a href='" . $resetUrl . "' class='button' style='color:#ffffff !important;'><span style='color:#ffffff !important;'>Reset Password</span></a>
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

