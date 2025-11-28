<?php

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * MailService
 *
 * Wrapper around PHPMailer with graceful fallbacks
 * for when SMTP is not configured or dependencies
 * are missing.
 */
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
        // Ensure required credentials exist
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

    private function createMailer(): ?PHPMailer
    {
        if (!$this->canSend) {
            return null;
        }

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

        return $mailer;
    }

    private function getBaseUrl(): string
    {
        $baseUrl = rtrim(APP_URL, '/');
        if (substr($baseUrl, -6) === '/admin') {
            $baseUrl = substr($baseUrl, 0, -6);
        }

        return $baseUrl;
    }

    private function getLogoUrl(): string
    {
        $logoPath = get_logo_path('footer-logo.png');
        if (!$logoPath) {
            $logoPath = get_logo_path('brand-logo.png');
        }
        if (!$logoPath) {
            $logoPath = 'assets/images/logo.png';
        }

        $cleanPath = ltrim(str_replace(['../', './'], '', $logoPath), '/');
        return $this->getBaseUrl() . '/' . $cleanPath;
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
                'logoUrl' => $this->getLogoUrl(),
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
                'logoUrl' => $this->getLogoUrl(),
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

    /**
     * Notify site admin about a new email change request
     */
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
                    <a href=\"{$dashboardUrl}\" style=\"display:inline-block;padding:10px 18px;background:#667eea;color:#fff;
                    border-radius:6px;text-decoration:none;\">View Request</a>
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
}



