<?php
/**
 * Slack Notification Service
 * Tailoring Management System (TailorPro)
 * 
 * Handles sending notifications to Slack via Bot User OAuth Token or Incoming Webhook.
 */

class SlackService
{
    private bool $enabled = false;
    private ?int $companyId = null;
    private string $botToken = '';
    private string $channelId = '';
    private string $webhookUrl = '';
    private string $lastError = '';

    public function __construct($companyId = null)
    {
        $this->companyId = $companyId ? (int)$companyId : (function_exists('get_company_id') ? get_company_id() : null);
        $this->initialize();
    }

    private function initialize(): void
    {
        $this->enabled = defined('SLACK_NOTIFICATIONS_ENABLED') ? (bool)SLACK_NOTIFICATIONS_ENABLED : true;
        $this->botToken = defined('SLACK_BOT_TOKEN') ? trim((string)SLACK_BOT_TOKEN) : '';
        $this->channelId = defined('SLACK_CHANNEL_ID') ? trim((string)SLACK_CHANNEL_ID) : '';
        $this->webhookUrl = defined('SLACK_WEBHOOK_URL') ? trim((string)SLACK_WEBHOOK_URL) : '';

        // Check if Slack settings are stored in company_settings table
        try {
            require_once __DIR__ . '/../config/database.php';
            $database = new Database();
            $db = $database->getConnection();
            if ($db) {
                // Look for configured Slack credentials in company_settings
                $stmt = $db->query("SELECT slack_bot_token, slack_channel_id, slack_webhook_url FROM company_settings WHERE (slack_bot_token IS NOT NULL AND slack_bot_token != '') OR (slack_webhook_url IS NOT NULL AND slack_webhook_url != '') ORDER BY id ASC LIMIT 1");
                $cs = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
                if ($cs) {
                    if (!empty(trim((string)($cs['slack_bot_token'] ?? '')))) {
                        $this->botToken = trim((string)$cs['slack_bot_token']);
                    }
                    if (!empty(trim((string)($cs['slack_channel_id'] ?? '')))) {
                        $this->channelId = trim((string)$cs['slack_channel_id']);
                    }
                    if (!empty(trim((string)($cs['slack_webhook_url'] ?? '')))) {
                        $this->webhookUrl = trim((string)$cs['slack_webhook_url']);
                    }
                }
            }
        } catch (Throwable $e) {
            // Fall back to config defaults
        }

        // Check if at least webhook or (botToken + channelId) is configured
        if (!$this->enabled) {
            $this->lastError = 'Slack notifications are disabled in config/slack.php.';
            return;
        }

        if (empty($this->webhookUrl) && (empty($this->botToken) || empty($this->channelId))) {
            $this->lastError = 'Slack credentials are not configured. Please set SLACK_BOT_TOKEN and SLACK_CHANNEL_ID or SLACK_WEBHOOK_URL.';
            $this->enabled = false;
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * Send a test notification to verify Slack connectivity.
     * 
     * @return array ['success' => bool, 'error' => string]
     */
    public function sendTestNotification(): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'error' => $this->lastError ?: 'Slack notifications are disabled in config/slack.php'];
        }

        $dateTime = date('d M Y, h:i A');
        $fallbackText = "🎉 TailorPro Slack Test: Connection successful at {$dateTime}!";

        $blocks = [
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => '🚀 TailorPro Slack Connection Test',
                    'emoji' => true
                ]
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "✅ *Success!* Your Slack integration with *TailorPro* is working properly.\nWhen a new tailor shop registers, you will automatically receive an alert in this channel."
                ]
            ],
            [
                'type' => 'context',
                'elements' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => "Tested from: `" . ($_SERVER['HTTP_HOST'] ?? 'Localhost') . "` • Time: {$dateTime}"
                    ]
                ]
            ]
        ];

        $attachments = [
            [
                'color' => '#10b981',
                'blocks' => $blocks
            ]
        ];

        return $this->postMessage($fallbackText, [], $attachments);
    }

    /**
     * Send notification for a newly registered tailor shop / user.
     * 
     * @param array $data ['company_name', 'owner_name', 'email', 'phone', 'city', 'state', 'plan', 'method']
     * @return bool
     */
    public function sendNewRegistrationNotification(array $data): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $companyName = trim((string)($data['company_name'] ?? 'New Shop'));
        $ownerName   = trim((string)($data['owner_name'] ?? 'New User'));
        $email       = trim((string)($data['email'] ?? 'Not provided'));
        $phone       = trim((string)($data['phone'] ?? 'Not provided'));
        $username    = trim((string)($data['username'] ?? $email));
        $city        = trim((string)($data['city'] ?? ''));
        $state       = trim((string)($data['state'] ?? ''));
        $location    = trim($city . ($city && $state ? ', ' : '') . $state) ?: 'Not specified';
        $plan        = strtoupper(trim((string)($data['plan'] ?? 'FREE')));
        $method      = trim((string)($data['method'] ?? 'Web Form'));
        $dateTime    = date('d M Y, h:i A');

        $fallbackText = "🎉 New Tailor Shop Registration: {$companyName} ({$ownerName}) - Plan: {$plan}";

        // Build rich Slack Block Kit message
        $blocks = [
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => '🧵 New Tailor Shop Registered!',
                    'emoji' => true
                ]
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*A new tailor shop has just registered on TailorPro!*\nHere are the details:"
                ]
            ],
            [
                'type' => 'divider'
            ],
            [
                'type' => 'section',
                'fields' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => "*🏢 Shop Name:*\n{$companyName}"
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*👤 Owner Name:*\n{$ownerName}"
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*📧 Email:*\n{$email}"
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*📱 Phone:*\n{$phone}"
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*📍 Location:*\n{$location}"
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*💎 Plan:*\n`{$plan}`"
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*🌐 Source:*\n{$method}"
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*🕒 Registered At:*\n{$dateTime}"
                    ]
                ]
            ],
            [
                'type' => 'divider'
            ],
            [
                'type' => 'context',
                'elements' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => "TailorPro System Notification • Username: `{$username}`"
                    ]
                ]
            ]
        ];

        // Accent color attachment (purple/indigo theme)
        $attachments = [
            [
                'color' => '#6366f1',
                'blocks' => $blocks
            ]
        ];

        $result = $this->postMessage($fallbackText, [], $attachments);
        return $result['success'];
    }

    /**
     * Send notification when a company/user purchases or upgrades a subscription plan.
     * 
     * @param array $data ['company_name', 'owner_name', 'email', 'phone', 'plan', 'duration', 'amount', 'payment_id', 'expiry_date']
     * @return bool
     */
    public function sendPlanPurchaseNotification(array $data): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $companyName = trim((string)($data['company_name'] ?? 'Shop'));
        $ownerName   = trim((string)($data['owner_name'] ?? 'Admin'));
        $email       = trim((string)($data['email'] ?? 'Not provided'));
        $phone       = trim((string)($data['phone'] ?? 'Not provided'));
        $plan        = strtoupper(trim((string)($data['plan'] ?? 'BASIC')));
        $duration    = ucfirst(trim((string)($data['duration'] ?? 'Monthly')));
        $amount      = isset($data['amount']) ? number_format((float)$data['amount'], 2) : '0.00';
        $paymentId   = trim((string)($data['payment_id'] ?? 'N/A'));
        $expiryDate  = trim((string)($data['expiry_date'] ?? 'N/A'));
        $dateTime    = date('d M Y, h:i A');

        $fallbackText = "💰 Plan Purchase: {$companyName} bought {$plan} ({$duration}) - ₹{$amount}";

        $blocks = [
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => '💰 New Plan Purchase / Upgrade!',
                    'emoji' => true
                ]
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*A customer just bought/upgraded a subscription plan on TailorPro!*\nHere are the transaction details:"
                ]
            ],
            [
                'type' => 'divider'
            ],
            [
                'type' => 'section',
                'fields' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => "*🏢 Shop Name:*\n{$companyName}"
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*👤 Admin / Owner:*\n{$ownerName}"
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*💎 Plan:*\n*`{$plan}`* ({$duration})"
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*💵 Amount Paid:*\n*₹{$amount}*"
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*📧 Email:*\n{$email}"
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*📱 Phone:*\n{$phone}"
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*🆔 Payment ID:*\n`{$paymentId}`"
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*📅 Valid Until:*\n{$expiryDate}"
                    ]
                ]
            ],
            [
                'type' => 'divider'
            ],
            [
                'type' => 'context',
                'elements' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => "TailorPro Billing Notification • Paid at: {$dateTime}"
                    ]
                ]
            ]
        ];

        // Green theme for successful payment
        $attachments = [
            [
                'color' => '#10b981',
                'blocks' => $blocks
            ]
        ];

        $result = $this->postMessage($fallbackText, [], $attachments);
        return $result['success'];
    }

    /**
     * Send a raw message to Slack.
     * 
     * @param string $fallbackText Main message text or fallback
     * @param array $blocks Optional Slack Block Kit blocks
     * @param array $attachments Optional Slack attachments (with colors, etc.)
     * @return array ['success' => bool, 'error' => string]
     */
    public function postMessage(string $fallbackText, array $blocks = [], array $attachments = []): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'error' => $this->lastError ?: 'Slack notifications disabled'];
        }

        $payload = [
            'text' => $fallbackText
        ];

        if (!empty($blocks)) {
            $payload['blocks'] = $blocks;
        }
        if (!empty($attachments)) {
            $payload['attachments'] = $attachments;
        }

        try {
            // Priority 1: Webhook URL (if configured)
            if (!empty($this->webhookUrl)) {
                return $this->sendHttp($this->webhookUrl, $payload, [
                    'Content-Type: application/json'
                ]);
            }

            // Priority 2: Slack Web API (chat.postMessage with Bot Token + Channel ID)
            $payload['channel'] = $this->channelId;
            $apiUrl = 'https://slack.com/api/chat.postMessage';

            $headers = [
                'Content-Type: application/json; charset=utf-8',
                'Authorization: Bearer ' . $this->botToken
            ];

            $response = $this->sendHttp($apiUrl, $payload, $headers);
            
            // Check if Slack returned ok: false
            if ($response['success'] && !empty($response['raw'])) {
                $decoded = json_decode($response['raw'], true);
                if (isset($decoded['ok']) && $decoded['ok'] === false) {
                    $err = $decoded['error'] ?? 'Unknown Slack API error';
                    $this->lastError = 'Slack API error: ' . $err;
                    error_log('SlackService API Error: ' . $err);
                    return ['success' => false, 'error' => $this->lastError];
                }
            }

            return $response;
        } catch (Throwable $e) {
            $this->lastError = 'Slack exception: ' . $e->getMessage();
            error_log('SlackService Exception: ' . $e->getMessage());
            return ['success' => false, 'error' => $this->lastError];
        }
    }

    /**
     * Internal HTTP POST helper using cURL with short timeout (non-blocking safety).
     */
    private function sendHttp(string $url, array $payload, array $headers): array
    {
        $json = json_encode($payload);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // 3 sec connect timeout
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);        // 5 sec total timeout

            // Handle SSL verification safely on Windows/WAMP
            $caBundle = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');
            if (!empty($caBundle) && file_exists($caBundle)) {
                curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            } else {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                $this->lastError = 'cURL error: ' . $curlError;
                error_log('SlackService cURL error: ' . $curlError);
                return ['success' => false, 'error' => $this->lastError];
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                return ['success' => true, 'raw' => $response];
            }

            $this->lastError = 'HTTP status ' . $httpCode . ': ' . $response;
            error_log('SlackService HTTP error ' . $httpCode . ': ' . $response);
            return ['success' => false, 'error' => $this->lastError];
        }

        // Fallback if cURL is not enabled
        $opts = [
            'http' => [
                'method'  => 'POST',
                'header'  => implode("\r\n", $headers) . "\r\nContent-Length: " . strlen($json) . "\r\n",
                'content' => $json,
                'timeout' => 5
            ]
        ];

        $context = stream_context_create($opts);
        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            $this->lastError = 'Failed to connect using stream context.';
            return ['success' => false, 'error' => $this->lastError];
        }

        return ['success' => true, 'raw' => $result];
    }
}
