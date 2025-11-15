<?php
/**
 * Quick script to create email_change_requests table
 * Run this file once: http://localhost/tailoring/admin/create_email_table.php
 */

require_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$sql = "CREATE TABLE IF NOT EXISTS `email_change_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `current_email` varchar(100) NOT NULL,
  `new_email` varchar(100) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `requested_by` int(11) NOT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `requested_by` (`requested_by`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $conn->exec($sql);
    
    // Try to add foreign keys (may fail if tables don't exist, that's okay)
    try {
        $conn->exec("ALTER TABLE `email_change_requests` 
                     ADD CONSTRAINT `email_change_requests_ibfk_1` 
                     FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE");
    } catch (PDOException $e) {
        // Foreign key may already exist or table may not exist, continue
    }
    
    try {
        $conn->exec("ALTER TABLE `email_change_requests` 
                     ADD CONSTRAINT `email_change_requests_ibfk_2` 
                     FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE");
    } catch (PDOException $e) {
        // Foreign key may already exist or table may not exist, continue
    }
    
    echo "<h2 style='color: green;'>✓ Table 'email_change_requests' created successfully!</h2>";
    echo "<p>You can now use the email change request feature.</p>";
    echo "<p><a href='company-settings.php'>Go to Company Settings</a></p>";
    
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>✗ Error creating table:</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

