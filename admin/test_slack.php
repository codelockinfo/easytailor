<?php
/**
 * Slack Notification Testing Tool
 * TailorPro Management System
 */

require_once '../config/config.php';
require_once '../helpers/SlackService.php';

$slackService = new SlackService();
$result = null;
$actionTaken = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actionTaken = $_POST['action'] ?? 'test_connection';

    if ($actionTaken === 'test_connection') {
        $result = $slackService->sendTestNotification();
    } elseif ($actionTaken === 'test_registration') {
        $success = $slackService->sendNewRegistrationNotification([
            'company_name' => 'Royal Stitch Studio (Demo)',
            'owner_name'   => 'Ramesh Sharma',
            'email'        => 'demo.ramesh@gmail.com',
            'phone'        => '+91 98250 12345',
            'username'     => 'ramesh_tailor',
            'city'         => 'Surat',
            'state'        => 'Gujarat',
            'plan'         => 'FREE (30-day trial)',
            'method'       => 'Test Simulation'
        ]);
        $result = [
            'success' => $success,
            'error'   => $success ? '' : $slackService->getLastError()
        ];
    }
}

// Current config values (masked for security)
$isEnabled = defined('SLACK_NOTIFICATIONS_ENABLED') && SLACK_NOTIFICATIONS_ENABLED;
$botToken = defined('SLACK_BOT_TOKEN') ? (string)SLACK_BOT_TOKEN : '';
$channelId = defined('SLACK_CHANNEL_ID') ? (string)SLACK_CHANNEL_ID : '';
$webhookUrl = defined('SLACK_WEBHOOK_URL') ? (string)SLACK_WEBHOOK_URL : '';
$source = 'config/slack.php';

$companyId = function_exists('get_company_id') ? get_company_id() : null;
if ($companyId) {
    try {
        require_once '../config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        if ($db) {
            $stmt = $db->prepare("SELECT slack_bot_token, slack_channel_id, slack_webhook_url FROM companies WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $companyId]);
            $comp = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($comp) {
                if (!empty(trim((string)($comp['slack_bot_token'] ?? '')))) {
                    $botToken = trim((string)$comp['slack_bot_token']);
                    $source = 'Database (Company Settings)';
                }
                if (!empty(trim((string)($comp['slack_channel_id'] ?? '')))) {
                    $channelId = trim((string)$comp['slack_channel_id']);
                    $source = 'Database (Company Settings)';
                }
                if (!empty(trim((string)($comp['slack_webhook_url'] ?? '')))) {
                    $webhookUrl = trim((string)$comp['slack_webhook_url']);
                    $source = 'Database (Company Settings)';
                }
            }
        }
    } catch (Throwable $e) {}
}

function maskSecret($str, $keepStart = 6, $keepEnd = 4) {
    if (empty($str)) return '<span class="text-muted fst-italic">Not set</span>';
    $len = strlen($str);
    if ($len <= $keepStart + $keepEnd) return str_repeat('•', $len);
    return substr($str, 0, $keepStart) . str_repeat('•', max(4, $len - $keepStart - $keepEnd)) . substr($str, -$keepEnd);
}

// Helper to provide friendly error guidance
function getTroubleshootingTip($errorMsg) {
    if (stripos($errorMsg, 'invalid_auth') !== false) {
        return '<strong>Cause:</strong> The Slack Bot Token is invalid.<br><strong>Solution:</strong> Open <code>config/slack.php</code> and verify that <code>SLACK_BOT_TOKEN</code> is valid and copied completely from <a href="https://api.slack.com/apps" target="_blank">Slack Apps</a>.';
    }
    if (stripos($errorMsg, 'not_in_channel') !== false) {
        return '<strong>Cause:</strong> The bot is not a member of your Slack channel.<br><strong>Solution:</strong> Open Slack, go to channel <code>' . htmlspecialchars((string)SLACK_CHANNEL_ID) . '</code>, and type <code>/invite @YourBotName</code> and press Enter.';
    }
    if (stripos($errorMsg, 'channel_not_found') !== false) {
        return '<strong>Cause:</strong> The Channel ID was not found or is private.<br><strong>Solution:</strong> Check <code>SLACK_CHANNEL_ID</code> in <code>config/slack.php</code>. In Slack, right-click your channel &rarr; "View channel details" &rarr; copy Channel ID at the bottom.';
    }
    if (stripos($errorMsg, 'missing_scope') !== false) {
        return '<strong>Cause:</strong> Missing required permissions.<br><strong>Solution:</strong> In Slack App settings &rarr; OAuth & Permissions &rarr; add the <code>chat:write</code> Bot Token Scope, then reinstall the app.';
    }
    if (stripos($errorMsg, 'dummy-api-key') !== false || stripos($errorMsg, 'dummy') !== false) {
        return '<strong>Dummy Credentials Detected:</strong> You are currently using placeholder dummy credentials.<br>To receive real notifications, open <code>config/slack.php</code> and put your real <code>SLACK_BOT_TOKEN</code> &amp; <code>SLACK_CHANNEL_ID</code> (or <code>SLACK_WEBHOOK_URL</code>).';
    }
    return 'Check your internet connection and verify credentials in <code>config/slack.php</code>.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slack Notification Tester - TailorPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            padding: 30px 15px;
        }
        .tester-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.08);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            max-width: 820px;
            margin: 0 auto;
        }
        .tester-header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: #ffffff;
            padding: 24px 30px;
        }
        .config-badge {
            background: rgba(255, 255, 255, 0.15);
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.8rem;
        }
        .action-card {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 20px;
            background: #ffffff;
            transition: all 0.2s ease;
        }
        .action-card:hover {
            border-color: #6366f1;
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.08);
            transform: translateY(-2px);
        }
        .code-pill {
            background: #f1f5f9;
            padding: 3px 8px;
            border-radius: 6px;
            font-family: Consolas, monospace;
            font-size: 0.85rem;
            color: #334155;
        }
    </style>
</head>
<body>

<div class="tester-card">
    <div class="tester-header d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 fw-bold"><i class="fab fa-slack me-2"></i> Slack Notification Tester</h4>
            <small class="opacity-75">Verify and test real-time Slack alerts for new tailor shop registrations</small>
        </div>
        <div>
            <a href="dashboard" class="btn btn-sm btn-light rounded-pill px-3 fw-semibold">
                <i class="fas fa-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <div class="p-4">
        <!-- Results Section -->
        <?php if ($result !== null): ?>
            <?php if ($result['success']): ?>
                <div class="alert alert-success border-0 shadow-sm rounded-4 p-4 mb-4" role="alert">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px; font-size: 1.3rem;">
                            <i class="fas fa-check"></i>
                        </div>
                        <div>
                            <h5 class="alert-heading fw-bold mb-1">Success! Notification Delivered to Slack</h5>
                            <p class="mb-0 text-muted" style="font-size: 0.92rem;">
                                <?php if ($actionTaken === 'test_registration'): ?>
                                    The sample <strong>New Registration Alert</strong> was sent to your Slack channel. Go check your Slack app now!
                                <?php else: ?>
                                    The <strong>Connection Test</strong> message was successfully delivered to your Slack channel!
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-danger border-0 shadow-sm rounded-4 p-4 mb-4" role="alert">
                    <div class="d-flex align-items-start gap-3">
                        <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center flex-shrink-0 mt-1" style="width: 44px; height: 44px; font-size: 1.3rem;">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="alert-heading fw-bold mb-1">Delivery Failed</h5>
                            <p class="mb-2 text-danger fw-semibold" style="font-size: 0.92rem;">
                                <?php echo htmlspecialchars($result['error'] ?: 'Unknown error'); ?>
                            </p>
                            <div class="p-3 bg-white rounded-3 border border-danger-subtle text-dark" style="font-size: 0.88rem; line-height: 1.5;">
                                <?php echo getTroubleshootingTip($result['error']); ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Current Configuration Status -->
        <div class="card border-0 bg-light rounded-4 p-3 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <span class="fw-bold text-dark" style="font-size: 0.95rem;">
                    <i class="fas fa-sliders-h text-primary me-2"></i> Current Configuration
                </span>
                <span class="badge <?php echo $isEnabled ? 'bg-success' : 'bg-secondary'; ?> rounded-pill px-3 py-1">
                    <?php echo $isEnabled ? 'Enabled' : 'Disabled'; ?>
                </span>
            </div>

            <div class="row g-3" style="font-size: 0.88rem;">
                <div class="col-md-6">
                    <div class="text-muted small">Config Source:</div>
                    <div class="code-pill text-truncate"><?php echo htmlspecialchars($source); ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Channel ID:</div>
                    <div class="code-pill text-truncate"><?php echo htmlspecialchars($channelId ?: 'Not set'); ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Bot Token:</div>
                    <div><?php echo maskSecret($botToken, 8, 4); ?></div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Incoming Webhook (Optional):</div>
                    <div><?php echo maskSecret($webhookUrl, 12, 6); ?></div>
                </div>
            </div>
        </div>

        <!-- Testing Actions -->
        <h6 class="fw-bold text-dark mb-3">Choose Test Action</h6>
        <div class="row g-3 mb-4">
            <!-- Test 1: Simple Connection Test -->
            <div class="col-md-6">
                <div class="action-card h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-primary-subtle text-primary rounded-pill p-2"><i class="fas fa-plug"></i></span>
                            <h6 class="fw-bold text-dark mb-0">1. Simple Ping Test</h6>
                        </div>
                        <p class="text-muted small mb-3">
                            Sends a simple "Connection Successful" test message to verify Slack API connection.
                        </p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="test_connection">
                        <button type="submit" class="btn btn-outline-primary w-100 rounded-pill fw-semibold py-2">
                            <i class="fas fa-paper-plane me-2"></i> Send Ping Test
                        </button>
                    </form>
                </div>
            </div>

            <!-- Test 2: Full Registration Simulation -->
            <div class="col-md-6">
                <div class="action-card h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-purple-subtle text-purple rounded-pill p-2" style="background: #ede9fe; color: #7c3aed;"><i class="fas fa-user-plus"></i></span>
                            <h6 class="fw-bold text-dark mb-0">2. Simulate New Registration</h6>
                        </div>
                        <p class="text-muted small mb-3">
                            Simulates an actual new tailor shop registration with shop name, owner, plan, and phone.
                        </p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="test_registration">
                        <button type="submit" class="btn btn-primary w-100 rounded-pill fw-semibold py-2" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); border: none;">
                            <i class="fas fa-bell me-2"></i> Simulate Registration Alert
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Setup Instructions Quick Guide -->
        <div class="card border-0 rounded-4 p-3" style="background: #f8fafc; border: 1px dashed #cbd5e1 !important;">
            <div class="fw-bold text-dark mb-2" style="font-size: 0.9rem;">
                <i class="fas fa-lightbulb text-warning me-1"></i> How to add your real credentials:
            </div>
            <ol class="mb-0 text-muted ps-3" style="font-size: 0.82rem; line-height: 1.6;">
                <li>Open <code>config/slack.php</code> in your code editor.</li>
                <li>Set your <code>SLACK_BOT_TOKEN</code> (from <a href="https://api.slack.com/apps" target="_blank">api.slack.com/apps</a> with <code>chat:write</code> permission).</li>
                <li>Set your <code>SLACK_CHANNEL_ID</code> (Right-click channel in Slack &rarr; View channel details &rarr; Copy ID).</li>
                <li>In your Slack channel, invite the bot: <code>/invite @YourBotName</code></li>
                <li>Or simply use <code>SLACK_WEBHOOK_URL</code> if using Incoming Webhook.</li>
                <li>Click <strong>Send Ping Test</strong> above to confirm!</li>
            </ol>
        </div>
    </div>
</div>

</body>
</html>
