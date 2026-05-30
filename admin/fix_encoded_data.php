<?php
/**
 * Fix Double-Encoded HTML Entities in Database
 * Run this ONCE to clean up data stored with htmlspecialchars() applied before DB insert
 */

require_once '../config/config.php';
require_once '../config/database.php';

// Only allow logged-in admin access
if (!is_logged_in()) {
    die('<h2 style="color:red">Unauthorized - Please login first</h2>');
}

$db = Database::getInstance()->getConnection();
$fixed = 0;
$skipped = 0;
$log = [];

// Tables and columns to fix
$targets = [
    'categories'   => ['name', 'description'],
    'cloth_types'  => ['name', 'description', 'category'],
    'customers'    => ['first_name', 'last_name', 'address', 'city', 'state', 'notes'],
    'orders'       => ['notes', 'special_instructions'],
    'contacts'     => ['name', 'company', 'address', 'notes', 'category'],
    'expenses'     => ['description', 'category'],
    'companies'    => ['company_name', 'owner_name', 'business_address', 'city', 'state'],
    'measurements' => ['customer_name', 'notes'],
];

echo "<!DOCTYPE html><html><head><title>Fix Encoded Data</title>
<style>body{font-family:Arial,sans-serif;max-width:900px;margin:30px auto;padding:20px;}
.ok{color:#28a745} .skip{color:#6c757d} h2{color:#333} pre{background:#f8f9fa;padding:10px;border-radius:4px;}</style></head><body>";
echo "<h2>🔧 Fixing Double-Encoded HTML Entities in Database</h2>";
echo "<hr>";

foreach ($targets as $table => $columns) {
    // Check if table exists
    try {
        $db->query("SELECT 1 FROM `$table` LIMIT 1");
    } catch (Exception $e) {
        echo "<p style='color:#aaa'>⏭ Table <b>$table</b> not found - skipped</p>";
        continue;
    }

    foreach ($columns as $col) {
        // Check column exists
        try {
            $check = $db->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
            if ($check->rowCount() === 0) {
                $skipped++;
                continue;
            }
        } catch (Exception $e) {
            continue;
        }

        // Fetch rows where the column contains HTML entities
        try {
            $stmt = $db->prepare(
                "SELECT id, `$col` FROM `$table` 
                 WHERE `$col` LIKE '%&#%' 
                    OR `$col` LIKE '%&amp;%' 
                    OR `$col` LIKE '%&lt;%' 
                    OR `$col` LIKE '%&gt;%' 
                    OR `$col` LIKE '%&quot;%'
                    OR `$col` LIKE '%&#039;%'"
            );
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            echo "<p style='color:red'>Error reading $table.$col: " . htmlspecialchars($e->getMessage()) . "</p>";
            continue;
        }

        foreach ($rows as $row) {
            $decoded = html_entity_decode($row[$col], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded !== $row[$col]) {
                try {
                    $update = $db->prepare("UPDATE `$table` SET `$col` = :val WHERE id = :id");
                    $update->execute([':val' => $decoded, ':id' => $row['id']]);
                    $fixed++;
                    echo "<div class='ok'>✅ Fixed <b>$table.$col</b> [id={$row['id']}]: <code>" 
                         . htmlspecialchars($row[$col]) . "</code> → <code>" 
                         . htmlspecialchars($decoded) . "</code></div>";
                } catch (Exception $e) {
                    echo "<p style='color:red'>Error updating $table.$col id={$row['id']}: " . htmlspecialchars($e->getMessage()) . "</p>";
                }
            }
        }
    }
}

echo "<hr>";
if ($fixed > 0) {
    echo "<h3 class='ok'>✅ Done! Fixed $fixed records.</h3>";
} else {
    echo "<h3 style='color:#17a2b8'>ℹ️ No encoded data found. Database is already clean!</h3>";
}
echo "<p><a href='categories.php'>→ Go to Categories</a> &nbsp;|&nbsp; 
      <a href='cloth-types.php'>→ Go to Cloth Types</a> &nbsp;|&nbsp;
      <a href='customers.php'>→ Go to Customers</a></p>";
echo "</body></html>";
?>
