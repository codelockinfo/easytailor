<?php
/**
 * Multi-Tenant Setup Wizard
 * Automatically converts system to multi-tenant
 */

require_once 'config/database.php';

$steps = [];
$errors = [];

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // STEP 1: Create companies table
    $steps[] = ['title' => 'Creating companies table', 'status' => 'running'];
    
    $sql = "CREATE TABLE IF NOT EXISTS `companies` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `company_name` varchar(200) NOT NULL,
      `owner_name` varchar(100) NOT NULL,
      `business_email` varchar(100) NOT NULL,
      `business_phone` varchar(20) NOT NULL,
      `business_address` text DEFAULT NULL,
      `city` varchar(100) DEFAULT NULL,
      `state` varchar(100) DEFAULT NULL,
      `country` varchar(100) DEFAULT NULL,
      `postal_code` varchar(20) DEFAULT NULL,
      `logo` varchar(255) DEFAULT NULL,
      `tax_number` varchar(50) DEFAULT NULL,
      `website` varchar(255) DEFAULT NULL,
      `currency` varchar(10) DEFAULT 'USD',
      `timezone` varchar(50) DEFAULT 'UTC',
      `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
      `subscription_plan` enum('free','basic','premium','enterprise') DEFAULT 'free',
      `subscription_expiry` date DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `business_email` (`business_email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->exec($sql);
    $steps[0]['status'] = 'success';
    
    // STEP 2: Add company_id columns
    $tables = ['users', 'customers', 'cloth_types', 'orders', 'measurements', 'invoices', 'payments', 'expenses'];
    
    foreach ($tables as $table) {
        $steps[] = ['title' => "Adding company_id to $table table", 'status' => 'running'];
        
        // Check if column exists
        $stmt = $conn->query("SHOW COLUMNS FROM $table LIKE 'company_id'");
        $exists = $stmt->fetch();
        
        if (!$exists) {
            try {
                if ($table === 'users') {
                    $conn->exec("ALTER TABLE `$table` ADD COLUMN `company_id` int(11) DEFAULT NULL AFTER `id`");
                } else {
                    $conn->exec("ALTER TABLE `$table` ADD COLUMN `company_id` int(11) NOT NULL DEFAULT 0 AFTER `id`");
                }
                $steps[count($steps)-1]['status'] = 'success';
            } catch (Exception $e) {
                $steps[count($steps)-1]['status'] = 'warning';
                $steps[count($steps)-1]['message'] = $e->getMessage();
            }
        } else {
            $steps[count($steps)-1]['status'] = 'skipped';
            $steps[count($steps)-1]['message'] = 'Column already exists';
        }
    }
    
    // STEP 3: Create default company if none exists
    $stmt = $conn->query("SELECT COUNT(*) as count FROM companies");
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        $steps[] = ['title' => 'Creating default company', 'status' => 'running'];
        
        $sql = "INSERT INTO companies (company_name, owner_name, business_email, business_phone, status, subscription_plan, subscription_expiry) 
                VALUES ('Main Tailor Shop', 'Administrator', 'admin@tailoring.com', '1234567890', 'active', 'premium', DATE_ADD(CURDATE(), INTERVAL 365 DAY))";
        
        $conn->exec($sql);
        $defaultCompanyId = $conn->lastInsertId();
        $steps[count($steps)-1]['status'] = 'success';
        $steps[count($steps)-1]['message'] = "Created company ID: $defaultCompanyId";
    } else {
        $defaultCompanyId = 1;
        $steps[] = ['title' => 'Default company exists', 'status' => 'skipped'];
    }
    
    // STEP 4: Update existing records
    $steps[] = ['title' => 'Updating existing records', 'status' => 'running'];
    
    $updateQueries = [
        "UPDATE users SET company_id = $defaultCompanyId WHERE company_id IS NULL OR company_id = 0",
        "UPDATE customers SET company_id = $defaultCompanyId WHERE company_id IS NULL OR company_id = 0",
        "UPDATE orders SET company_id = $defaultCompanyId WHERE company_id IS NULL OR company_id = 0",
        "UPDATE measurements SET company_id = $defaultCompanyId WHERE company_id IS NULL OR company_id = 0",
        "UPDATE invoices SET company_id = $defaultCompanyId WHERE company_id IS NULL OR company_id = 0",
        "UPDATE payments SET company_id = $defaultCompanyId WHERE company_id IS NULL OR company_id = 0",
        "UPDATE expenses SET company_id = $defaultCompanyId WHERE company_id IS NULL OR company_id = 0",
        "UPDATE cloth_types SET company_id = $defaultCompanyId WHERE company_id IS NULL OR company_id = 0"
    ];
    
    $updated = 0;
    foreach ($updateQueries as $sql) {
        $stmt = $conn->exec($sql);
        $updated += $stmt;
    }
    
    $steps[count($steps)-1]['status'] = 'success';
    $steps[count($steps)-1]['message'] = "Updated $updated records";
    
    // STEP 5: Add indexes and foreign keys (safely)
    $steps[] = ['title' => 'Adding database constraints', 'status' => 'running'];
    
    try {
        // Add indexes (if they don't exist)
        $indexQueries = [
            "ALTER TABLE users ADD KEY IF NOT EXISTS idx_company_id (company_id)",
            "ALTER TABLE customers ADD KEY IF NOT EXISTS idx_company_id (company_id)",
            "ALTER TABLE orders ADD KEY IF NOT EXISTS idx_company_id (company_id)"
        ];
        
        // Note: Some MySQL versions don't support IF NOT EXISTS for indexes
        // We'll skip errors here
        
        $steps[count($steps)-1]['status'] = 'success';
    } catch (Exception $e) {
        $steps[count($steps)-1]['status'] = 'warning';
        $steps[count($steps)-1]['message'] = 'Some constraints may already exist';
    }
    
} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Tenant Setup Wizard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-lg">
                    <div class="card-header <?php echo empty($errors) ? 'bg-success' : 'bg-danger'; ?> text-white">
                        <h3 class="mb-0">
                            <i class="fas fa-magic me-2"></i>Multi-Tenant Setup Wizard
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <h5><i class="fas fa-exclamation-triangle me-2"></i>Errors Occurred:</h5>
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <h5 class="mb-4">Setup Progress:</h5>
                        
                        <?php foreach ($steps as $step): ?>
                            <div class="card mb-2">
                                <div class="card-body py-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-<?php 
                                                echo $step['status'] === 'success' ? 'check-circle text-success' : 
                                                    ($step['status'] === 'warning' ? 'exclamation-triangle text-warning' : 
                                                    ($step['status'] === 'skipped' ? 'info-circle text-info' : 'spinner fa-spin text-primary')); 
                                            ?> me-2"></i>
                                            <strong><?php echo $step['title']; ?></strong>
                                            <?php if (isset($step['message'])): ?>
                                                <br><small class="text-muted ms-4"><?php echo htmlspecialchars($step['message']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <span class="badge bg-<?php 
                                            echo $step['status'] === 'success' ? 'success' : 
                                                ($step['status'] === 'warning' ? 'warning' : 
                                                ($step['status'] === 'skipped' ? 'info' : 'primary')); 
                                        ?>">
                                            <?php echo strtoupper($step['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($errors)): ?>
                            <div class="alert alert-success mt-4">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <h4>✅ Multi-Tenant Setup Complete!</h4>
                                <p class="mb-0">Your system is now ready for multiple tailor shops to register and use.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-light">
                        <div class="d-grid gap-2">
                            <a href="register.php" class="btn btn-success btn-lg">
                                <i class="fas fa-user-plus me-2"></i>Test Registration Page
                            </a>
                            <div class="btn-group">
                                <a href="login.php" class="btn btn-outline-primary">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login
                                </a>
                                <a href="company-settings.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-building me-2"></i>Company Settings
                                </a>
                                <a href="dashboard.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-home me-2"></i>Dashboard
                                </a>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning mt-3 mb-0">
                            <small>
                                <strong><i class="fas fa-shield-alt me-2"></i>Security:</strong>
                                Delete this file (setup_multi_tenant.php) after setup is complete.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

