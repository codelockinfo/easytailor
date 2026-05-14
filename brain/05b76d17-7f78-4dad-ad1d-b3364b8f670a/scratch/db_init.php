<?php
require_once 'C:/wamp64/www/easytailor/config/database.php';
$database = new Database();
$db = $database->getConnection();

// Add profile_image column to users table if it doesn't exist
try {
    $db->exec("ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL AFTER email");
    echo "profile_image column added successfully.\n";
} catch (Exception $e) {
    echo "Column might already exist: " . $e->getMessage() . "\n";
}
?>
