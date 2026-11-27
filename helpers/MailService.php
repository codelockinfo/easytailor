<?php

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

class MailService
{
    private bool $canSend = false;
    private string $lastError = '';

    public function __construct()
    {
        $this->initialise();
    }

    private function initialise(): void
    {
        if (empty(SMTP_HOST) || empty(SMTP_USERNAME) || empty(SMTP_PASSWORD) || empty(SMTP_FROM_EMAIL)) {
            $this->lastError = 'SMTP credentials are not configured.';
            return;
        }

        $autoload = APP_PATH . 'vendor/autoload.php';
        if (!file_exists($autoload)) {
            $this->lastError = 'PHPMailer is not installed. Run "composer require phpmailer/phpmailer".';
            return;
        }

        require_once $autoload;
        $this->canSend = true;
    }

    public function isEnabled(): bool
    {
        return $this->canSend;
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    private function isLocalEnvironment(): bool
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
        return in_array($remoteAddr, ['127.0.0.1', '::1', ''], true)
            || stripos($host, 'localhost') !== false
            || stripos($host, '127.0.0.1') !== false
            || stripos($host, '.local') !== false
            || php_sapi_name() === 'cli';
    }

    private function createMailer(): ?PHPMailer
    {
        if (!$this->canSend) {
            return null;
        }

        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = SMTP_HOST;
        $port = (int)(SMTP_PORT ?: 587);
        $mailer->Port = $port;
        $mailer->SMTPAuth = true;
        $mailer->Username = SMTP_USERNAME;
        $mailer->Password = SMTP_PASSWORD;
        $mailer->CharSet = 'UTF-8';
        $mailer->Timeout = 15;

        if ($this->isLocalEnvironment()) {
            // Allow local development environments (e.g. WAMP without ini_cafile or with antivirus SSL proxy) to reach SMTP.
            $mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];
        }

        $encryption = strtolower(trim((string)(defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : '')));
        if ($port === 465 && ($encryption === 'tls' || empty($encryption))) {
            $encryption = 'ssl';
        } elseif ($port === 587 && ($encryption === 'ssl' || empty($encryption))) {
            $encryption = 'tls';
        }

        if (!empty($encryption)) {
            $mailer->SMTPSecure = $encryption;
        }

        $fromName = SMTP_FROM_NAME ?: APP_NAME;
        $mailer->setFrom(SMTP_FROM_EMAIL, $fromName);
        $mailer->isHTML(true);

        return $mailer;
    }

    private function getBaseUrl(): string
    {
        $baseUrl = rtrim(APP_URL, '/');
        $baseUrl = preg_replace('#/(admin|siteadmin)(/ajax)?$#', '', $baseUrl);

        return $baseUrl;
    }

    private function resolveLogoFilePath(): ?string
    {
        $candidates = [];
        if (function_exists('get_site_setting')) {
            foreach (['footer_logo', 'admin_logo', 'website_logo'] as $key) {
                $val = get_site_setting($key, '');
                if (!empty($val)) {
                    $candidates[] = ltrim(str_replace(['../', './'], '', (string)$val), '/');
                }
            }
        }
        $candidates[] = 'assets/images/tailor_logo.png';
        $candidates[] = 'uploads/logos/footer-logo.png';
        $candidates[] = 'uploads/logos/brand-logo.png';

        foreach ($candidates as $relPath) {
            $fullPath = APP_PATH . $relPath;
            if (is_file($fullPath) && filesize($fullPath) > 0) {
                return $fullPath;
            }
        }

        return null;
    }

    private function getLogoUrl(): string
    {
        $logoPath = get_logo_path('footer-logo.png');
        if (!$logoPath) {
            $logoPath = get_logo_path('brand-logo.png');
        }
        if (!$logoPath) {
            $logoPath = 'assets/images/tailor_logo.png';
        }

        $cleanPath = ltrim(str_replace(['../', './'], '', $logoPath), '/');
        return $this->getBaseUrl() . '/' . $cleanPath;
    }

    private function attachBrandLogo(PHPMailer $mailer): string
    {
        $filePath = $this->resolveLogoFilePath();
        if ($filePath !== null) {
            try {
                $mailer->addEmbeddedImage($filePath, 'tailorpro_brand_logo', basename($filePath));
                return 'cid:tailorpro_brand_logo';
            } catch (PHPMailerException $e) {
                error_log('MailService logo embed failed: ' . $e->getMessage());
            }
        }

        return $this->getLogoUrl();
    }

    private function renderTemplate(string $template, array $data = []): string
    {
        $templatePath = APP_PATH . 'resources/emails/' . $template . '.php';
        if (!file_exists($templatePath)) {
            throw new RuntimeException("Email template '{$template}' not found.");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        include $templatePath;
        return (string)ob_get_clean();
    }

    public function sendWelcomeEmail(array $payload): bool
    {
        if (!$this->canSend) {
            return false;
        }

        $email = $payload['email'] ?? '';
        $name = $payload['name'] ?? '';

        if (empty($email)) {
            $this->lastError = 'Missing recipient email.';
            return false;
        }

        try {
            $mailer = $this->createMailer();
            if (!$mailer) {
                return false;
            }

            $manageUrl = $payload['manageUrl'] ?? ($this->getBaseUrl() . '/admin/login.php');
            $username = $payload['username'] ?? '';
            $companyName = $payload['companyName'] ?? '';

            $mailer->clearAllRecipients();
            $mailer->addAddress($email, $name);
            $mailer->Subject = 'Welcome to ' . APP_NAME;

            $mailer->Body = $this->renderTemplate('welcome', [
                'appName' => APP_NAME,
                'logoUrl' => $this->attachBrandLogo($mailer),
                'ownerName' => $name,
                'companyName' => $companyName,
                'manageUrl' => $manageUrl,
                'supportEmail' => SMTP_FROM_EMAIL,
                'username' => $username,
            ]);

            $mailer->AltBody = "Hi {$name},\n\n"
                . "Your tailor shop {$companyName} is ready to manage.\n"
                . (!empty($username) ? "Username: {$username}\n" : '')
                . "Manage your tailor shop: {$manageUrl}\n\n"
                . "If you did not register, please ignore this email.";

            $mailer->send();
            return true;
        } catch (PHPMailerException $e) {
            $this->lastError = $e->getMessage();
            return false;
        } catch (RuntimeException $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function sendPasswordResetEmail(array $payload): bool
    {
        if (!$this->canSend) {
            return false;
        }

        $email = $payload['email'] ?? '';
        $name = $payload['name'] ?? '';
        $code = $payload['code'] ?? '';
        $token = $payload['token'] ?? '';

        if (empty($email) || empty($code) || empty($token)) {
            $this->lastError = 'Missing password reset details.';
            return false;
        }

        try {
            $mailer = $this->createMailer();
            if (!$mailer) {
                return false;
            }

            $resetUrl = $payload['resetUrl'] ?? ($this->getBaseUrl() . '/admin/reset-password.php?token=' . urlencode($token));

            $mailer->clearAllRecipients();
            $mailer->addAddress($email, $name);
            $mailer->Subject = APP_NAME . ' Password Reset';

            $mailer->Body = $this->renderTemplate('password_reset', [
                'appName' => APP_NAME,
                'logoUrl' => $this->attachBrandLogo($mailer),
                'ownerName' => $name,
                'code' => $code,
                'resetUrl' => $resetUrl,
                'supportEmail' => SMTP_FROM_EMAIL,
            ]);

            $mailer->AltBody = "Hello {$name},\n\n"
                . "We received a request to reset your password.\n"
                . "Verification code: {$code}\n"
                . "Reset your password: {$resetUrl}\n\n"
                . "If you did not request this, please ignore this email.";

            $mailer->send();
            return true;
        } catch (PHPMailerException $e) {
            $this->lastError = $e->getMessage();
            return false;
        } catch (RuntimeException $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }
    public function sendLoginOtpEmail(array $payload): bool
    {
        if (!$this->canSend) {
            return false;
        }

        $email = $payload['email'] ?? '';
        $name = $payload['name'] ?? 'User';
        $code = $payload['code'] ?? '';

        if (empty($email) || empty($code)) {
            $this->lastError = 'Missing email or OTP code.';
            return false;
        }

        try {
            $mailer = $this->createMailer();
            if (!$mailer) {
                return false;
            }

            $mailer->clearAllRecipients();
            $mailer->addAddress($email, $name);
            $mailer->Subject = !empty($payload['subject']) ? $payload['subject'] : ('Your Login Verification Code - ' . APP_NAME);

            $mailer->Body = $this->renderTemplate('login_otp', [
                'appName' => APP_NAME,
                'logoUrl' => $this->attachBrandLogo($mailer),
                'userName' => $name,
                'code' => $code,
                'title' => $payload['title'] ?? 'Login Verification Code',
                'customMessage' => $payload['message'] ?? null,
                'supportEmail' => SMTP_FROM_EMAIL,
            ]);

            $customAlt = !empty($payload['message']) ? ($payload['message'] . "\n\n") : '';
            $mailer->AltBody = "Hello {$name},\n\n"
                . $customAlt
                . "Your verification code for " . APP_NAME . " is: {$code}\n\n"
                . "This code is valid for 5 minutes. Never share this code with anyone.\n"
                . "If you did not request this code, you can safely ignore this email.";

            $mailer->send();
            return true;
        } catch (PHPMailerException $e) {
            $this->lastError = $e->getMessage();
            return false;
        } catch (RuntimeException $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }
    public function sendEmailChangeRequestNotification(array $payload): bool
    {
        if (!$this->canSend) {
            return false;
        }

        $email = $payload['email'] ?? '';
        if (empty($email)) {
            $this->lastError = 'Missing recipient email.';
            return false;
        }

        try {
            $mailer = $this->createMailer();
            if (!$mailer) {
                return false;
            }

            $adminName = $payload['name'] ?? 'Site Admin';
            $companyName = $payload['companyName'] ?? 'Unknown Company';
            $ownerName = $payload['ownerName'] ?? 'Unknown Owner';
            $currentEmail = $payload['currentEmail'] ?? 'N/A';
            $newEmail = $payload['newEmail'] ?? 'N/A';
            $reason = $payload['reason'] ?? 'Not provided';
            $dashboardUrl = $payload['dashboardUrl'] ?? ($this->getBaseUrl() . '/siteadmin/index.php');

            $safeReason = nl2br(htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'));

            $mailer->clearAllRecipients();
            $mailer->addAddress($email, $adminName);
            $mailer->Subject = 'New Email Change Request Received';

            $mailer->Body = "
                <p>Hello {$adminName},</p>
                <p><strong>{$companyName}</strong> has requested to update their business email.</p>
                <ul>
                    <li><strong>Owner:</strong> {$ownerName}</li>
                    <li><strong>Current Email:</strong> {$currentEmail}</li>
                    <li><strong>Requested Email:</strong> {$newEmail}</li>
                </ul>
                <p><strong>Reason Provided:</strong><br>{$safeReason}</p>
                <p>
                    <a href=\"{$dashboardUrl}\" style=\"display:inline-block;padding:10px 18px;background:#667eea;color:#ffffff !important;
                    border-radius:6px;text-decoration:none;\"><span style=\"color:#ffffff !important;\">View Request</span></a>
                </p>
                <p>This is an automated notification from " . APP_NAME . ".</p>
            ";

            $mailer->AltBody =
                "Hello {$adminName},\n\n" .
                "{$companyName} requested an email change.\n" .
                "Owner: {$ownerName}\n" .
                "Current Email: {$currentEmail}\n" .
                "Requested Email: {$newEmail}\n" .
                "Reason: {$reason}\n\n" .
                "Review the request: {$dashboardUrl}\n";

            $mailer->send();
            return true;
        } catch (PHPMailerException $e) {
            $this->lastError = $e->getMessage();
            return false;
        } catch (RuntimeException $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function sendEmailChangeStatusNotification(array $payload): bool
    {
        if (!$this->canSend) {
            return false;
        }

        $email = trim((string)($payload['email'] ?? ''));
        if (empty($email)) {
            $this->lastError = 'Missing recipient email.';
            return false;
        }

        try {
            $mailer = $this->createMailer();
            if (!$mailer) {
                return false;
            }

            $status = ($payload['status'] ?? 'approved') === 'approved' ? 'approved' : 'rejected';
            $ownerName = htmlspecialchars((string)($payload['ownerName'] ?? 'Business Owner'), ENT_QUOTES, 'UTF-8');
            $companyName = htmlspecialchars((string)($payload['companyName'] ?? 'Your Company'), ENT_QUOTES, 'UTF-8');
            $currentEmail = htmlspecialchars((string)($payload['currentEmail'] ?? ''), ENT_QUOTES, 'UTF-8');
            $newEmail = htmlspecialchars((string)($payload['newEmail'] ?? ''), ENT_QUOTES, 'UTF-8');
            $reviewNotes = trim((string)($payload['reviewNotes'] ?? ''));
            $safeNotes = nl2br(htmlspecialchars($reviewNotes, ENT_QUOTES, 'UTF-8'));
            $settingsUrl = htmlspecialchars((string)($payload['settingsUrl'] ?? ($this->getBaseUrl() . '/admin/company-settings.php')), ENT_QUOTES, 'UTF-8');

            $isApproved = ($status === 'approved');
            $subject = $isApproved
                ? 'Business Email Change Approved - ' . APP_NAME
                : 'Business Email Change Request Rejected - ' . APP_NAME;
            $heading = $isApproved ? 'Email Change Approved' : 'Email Change Request Rejected';
            $accentColor = $isApproved ? '#10b981' : '#ef4444';
            $summaryText = $isApproved
                ? "Good news! Your request to update the business email for <strong>{$companyName}</strong> has been approved. Your business email is now <strong>{$newEmail}</strong>."
                : "Your request to change the business email for <strong>{$companyName}</strong> to <strong>{$newEmail}</strong> was not approved.";
            $notesHtml = !empty($reviewNotes)
                ? "<p style=\"background:#f8fafc;border-left:3px solid {$accentColor};padding:10px 12px;border-radius:6px;\"><strong>Reviewer Note:</strong><br>{$safeNotes}</p>"
                : '';

            $mailer->clearAllRecipients();
            $mailer->addAddress($email, (string)($payload['ownerName'] ?? $companyName));
            $mailer->Subject = $subject;

            $mailer->Body = "
                <div style=\"font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;color:#1e293b;\">
                    <h2 style=\"margin-top:0;color:{$accentColor};\">{$heading}</h2>
                    <p>Hello {$ownerName},</p>
                    <p>{$summaryText}</p>
                    <ul>
                        <li><strong>Company:</strong> {$companyName}</li>
                        " . (!empty($currentEmail) ? "<li><strong>Previous Email:</strong> {$currentEmail}</li>" : "") . "
                        <li><strong>Requested Email:</strong> {$newEmail}</li>
                    </ul>
                    {$notesHtml}
                    <p style=\"margin:20px 0;\">
                        <a href=\"{$settingsUrl}\" style=\"display:inline-block;padding:10px 18px;background:#4f46e5;color:#ffffff !important;border-radius:6px;text-decoration:none;font-weight:600;\">View Company Settings</a>
                    </p>
                    <p style=\"font-size:12px;color:#64748b;margin-bottom:0;\">This is an automated notification from " . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . ".</p>
                </div>
            ";

            $mailer->AltBody =
                "Hello " . ($payload['ownerName'] ?? 'Business Owner') . ",\n\n" .
                ($isApproved
                    ? "Your business email change request for " . ($payload['companyName'] ?? 'your company') . " to " . ($payload['newEmail'] ?? '') . " has been approved.\n"
                    : "Your business email change request for " . ($payload['companyName'] ?? 'your company') . " to " . ($payload['newEmail'] ?? '') . " was rejected.\n") .
                (!empty($reviewNotes) ? "Reviewer Note: {$reviewNotes}\n" : "") .
                "\nView Company Settings: " . ($payload['settingsUrl'] ?? ($this->getBaseUrl() . '/admin/company-settings.php')) . "\n";

            $mailer->send();
            return true;
        } catch (PHPMailerException $e) {
            $this->lastError = $e->getMessage();
            return false;
        } catch (RuntimeException $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }
}



