<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    if (!$conn) {
        die("Failed to connect to database\n");
    }

    $sql = file_get_contents(__DIR__ . '/create_push_subscriptions.sql');
    $queries = array_filter(array_map('trim', explode(';', $sql)));
    $checkIndex = $conn->query("SHOW INDEX FROM notifications WHERE Key_name = 'idx_notif_user_read'");
    if ($checkIndex->rowCount() === 0) {
        $conn->exec("CREATE INDEX idx_notif_user_read ON notifications (user_id, is_read)");
        echo "Created index idx_notif_user_read.\n";
    }
    $checkIndex2 = $conn->query("SHOW INDEX FROM notifications WHERE Key_name = 'idx_notif_created'");
    if ($checkIndex2->rowCount() === 0) {
        $conn->exec("CREATE INDEX idx_notif_created ON notifications (created_at)");
        echo "Created index idx_notif_created.\n";
    }
    echo "Migration completed.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
