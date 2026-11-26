<?php
/**
 * Web Database Migration: Add Slack Columns & Settings
 * Tailoring Management System (TailorPro)
 * 
 * Access via Browser:
 *   http://your-domain.com/database/migrate_slack.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$message = '';
$messageType = '';
$colsAdded = [];

try {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        throw new Exception("Could not connect to database.");
    }

    // Check existing columns
    $stmt = $db->query("SHOW COLUMNS FROM company_settings");
    $existingCols = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('slack_bot_token', $existingCols)) {
        $db->exec("ALTER TABLE company_settings ADD COLUMN slack_bot_token VARCHAR(255) NULL DEFAULT NULL AFTER updated_at");
        $colsAdded[] = 'slack_bot_token';
    }
    if (!in_array('slack_channel_id', $existingCols)) {
        $db->exec("ALTER TABLE company_settings ADD COLUMN slack_channel_id VARCHAR(100) NULL DEFAULT NULL AFTER slack_bot_token");
        $colsAdded[] = 'slack_channel_id';
    }
    if (!in_array('slack_webhook_url', $existingCols)) {
        $db->exec("ALTER TABLE company_settings ADD COLUMN slack_webhook_url VARCHAR(500) NULL DEFAULT NULL AFTER slack_channel_id");
        $colsAdded[] = 'slack_webhook_url';
    }

    // Handle Form Submit to set or update credentials
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $botToken = trim($_POST['slack_bot_token'] ?? '');
        $channelId = trim($_POST['slack_channel_id'] ?? '');
        $webhookUrl = trim($_POST['slack_webhook_url'] ?? '');

        $checkRow = $db->query("SELECT id FROM company_settings ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($checkRow) {
            $updateStmt = $db->prepare("UPDATE company_settings SET slack_bot_token = ?, slack_channel_id = ?, slack_webhook_url = ? WHERE id = ?");
            $updateStmt->execute([$botToken ?: null, $channelId ?: null, $webhookUrl ?: null, $checkRow['id']]);
        } else {
            $insertStmt = $db->prepare("INSERT INTO company_settings (business_name, slack_bot_token, slack_channel_id, slack_webhook_url) VALUES ('TailorPro', ?, ?, ?)");
            $insertStmt->execute([$botToken ?: null, $channelId ?: null, $webhookUrl ?: null]);
        }
        $message = "Slack credentials updated successfully in database!";
        $messageType = "success";
    }

    // Fetch current settings
    $current = $db->query("SELECT slack_bot_token, slack_channel_id, slack_webhook_url FROM company_settings WHERE (slack_bot_token IS NOT NULL AND slack_bot_token != '') OR (slack_webhook_url IS NOT NULL AND slack_webhook_url != '') ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $message = "Error: " . $e->getMessage();
    $messageType = "danger";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slack Migration & Settings - TailorPro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: system-ui, -apple-system, sans-serif; padding: 40px 15px; }
        .card { max-width: 650px; margin: 0 auto; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .card-header { background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color: white; border-radius: 16px 16px 0 0 !important; padding: 20px 24px; }
    </style>
</head>
<body>

<div class="card">
    <div class="card-header">
        <h4 class="mb-0 fw-bold">🚀 Slack Database Migration</h4>
        <small class="opacity-75">TailorPro Server Database Setup</small>
    </div>
    <div class="card-body p-4">
        <?php if (!empty($colsAdded)): ?>
            <div class="alert alert-success">
                ✅ Database columns created: <strong><?php echo implode(', ', $colsAdded); ?></strong>
            </div>
        <?php else: ?>
            <div class="alert alert-info py-2">
                ℹ️ Database columns already exist in <code>company_settings</code>.
            </div>
        <?php endif; ?>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> py-2">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="mt-3">
            <div class="mb-3">
                <label class="form-label fw-semibold">Slack Bot Token (xoxb-...):</label>
                <input type="text" name="slack_bot_token" class="form-control" value="<?php echo htmlspecialchars($current['slack_bot_token'] ?? ''); ?>" placeholder="xoxb-859302...">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Slack Channel ID:</label>
                <input type="text" name="slack_channel_id" class="form-control" value="<?php echo htmlspecialchars($current['slack_channel_id'] ?? ''); ?>" placeholder="C0CLMJC15DJ">
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Slack Webhook URL (Optional):</label>
                <input type="text" name="slack_webhook_url" class="form-control" value="<?php echo htmlspecialchars($current['slack_webhook_url'] ?? ''); ?>" placeholder="https://hooks.slack.com/services/...">
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold" style="background: #4f46e5; border: none;">
                Save Credentials to Database
            </button>
        </form>
    </div>
</div>

</body>
</html>
