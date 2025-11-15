<?php
/**
 * Setup Email Change Requests Table
 * Run this file once to create the email_change_requests table
 */

require_once '../config/config.php';
require_login();
require_role('admin');

$message = '';
$messageType = '';

// Get database connection
require_once '../config/database.php';
$database = new Database();
$conn = $database->getConnection();

// SQL to create the table
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
    // Check if table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'email_change_requests'");
    
    if ($checkTable->rowCount() > 0) {
        $message = 'Table "email_change_requests" already exists.';
        $messageType = 'info';
    } else {
        // Create the table
        $conn->exec($sql);
        
        // Add foreign keys if tables exist
        try {
            // Check if companies table exists
            $checkCompanies = $conn->query("SHOW TABLES LIKE 'companies'");
            if ($checkCompanies->rowCount() > 0) {
                // Check if foreign key already exists
                $checkFK = $conn->query("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'email_change_requests' 
                    AND CONSTRAINT_NAME = 'email_change_requests_ibfk_1'
                ");
                
                if ($checkFK->rowCount() == 0) {
                    $conn->exec("ALTER TABLE `email_change_requests` 
                                 ADD CONSTRAINT `email_change_requests_ibfk_1` 
                                 FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE");
                }
            }
            
            // Check if users table exists
            $checkUsers = $conn->query("SHOW TABLES LIKE 'users'");
            if ($checkUsers->rowCount() > 0) {
                // Check if foreign key already exists
                $checkFK2 = $conn->query("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'email_change_requests' 
                    AND CONSTRAINT_NAME = 'email_change_requests_ibfk_2'
                ");
                
                if ($checkFK2->rowCount() == 0) {
                    $conn->exec("ALTER TABLE `email_change_requests` 
                                 ADD CONSTRAINT `email_change_requests_ibfk_2` 
                                 FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE");
                }
            }
        } catch (PDOException $e) {
            // Foreign key constraints are optional, continue if they fail
            $message = 'Table created successfully. Note: Some foreign key constraints could not be added: ' . $e->getMessage();
            $messageType = 'warning';
        }
        
        if (empty($message)) {
            $message = 'Table "email_change_requests" created successfully!';
            $messageType = 'success';
        }
    }
} catch (PDOException $e) {
    $message = 'Error creating table: ' . $e->getMessage();
    $messageType = 'error';
}

$page_title = 'Setup Email Change Requests';
require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-database me-2"></i>Setup Email Change Requests Table
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : ($messageType === 'error' ? 'danger' : 'info'); ?> alert-dismissible fade show">
                            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'error' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <p>This script will create the <code>email_change_requests</code> table in your database.</p>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> If the table already exists, this script will not modify it.
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-play me-2"></i>Create Table
                        </button>
                        <a href="company-settings.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Company Settings
                        </a>
                    </form>
                    
                    <?php if ($messageType === 'success'): ?>
                        <div class="mt-4">
                            <h6>Next Steps:</h6>
                            <ol>
                                <li>The table has been created successfully.</li>
                                <li>You can now use the email change request feature on the Company Settings page.</li>
                                <li>You can delete this setup file after confirming everything works.</li>
                            </ol>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

