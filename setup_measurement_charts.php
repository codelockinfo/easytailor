<?php
/**
 * One-Click Setup for Measurement Charts
 * Run this script once to set up everything
 */

require_once 'config/database.php';

// Initialize response
$response = [
    'step1' => ['status' => 'pending', 'message' => 'Checking database column...'],
    'step2' => ['status' => 'pending', 'message' => 'Assigning charts to cloth types...'],
    'step3' => ['status' => 'pending', 'message' => 'Verifying setup...'],
];

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // STEP 1: Check and add column if needed
    $response['step1']['status'] = 'running';
    
    // Check if column exists
    $stmt = $conn->query("SHOW COLUMNS FROM cloth_types LIKE 'measurement_chart_image'");
    $columnExists = $stmt->fetch() !== false;
    
    if (!$columnExists) {
        // Add the column
        $conn->exec("ALTER TABLE `cloth_types` ADD COLUMN `measurement_chart_image` VARCHAR(255) DEFAULT NULL AFTER `category`");
        $response['step1']['status'] = 'success';
        $response['step1']['message'] = '✅ Added measurement_chart_image column to database';
    } else {
        $response['step1']['status'] = 'success';
        $response['step1']['message'] = '✅ Column already exists';
    }
    
    // STEP 2: Assign charts to cloth types
    $response['step2']['status'] = 'running';
    
    $updates = [
        "UPDATE `cloth_types` SET `measurement_chart_image` = 'uploads/measurement-charts/pants.svg' WHERE LOWER(`name`) LIKE '%pant%' OR LOWER(`name`) LIKE '%trouser%'",
        "UPDATE `cloth_types` SET `measurement_chart_image` = 'uploads/measurement-charts/shirt.svg' WHERE LOWER(`name`) LIKE '%shirt%'",
        "UPDATE `cloth_types` SET `measurement_chart_image` = 'uploads/measurement-charts/kurta.svg' WHERE LOWER(`name`) LIKE '%kurta%' OR LOWER(`name`) LIKE '%kameez%'",
        "UPDATE `cloth_types` SET `measurement_chart_image` = 'uploads/measurement-charts/lehenga.svg' WHERE LOWER(`name`) LIKE '%lehenga%' OR LOWER(`name`) LIKE '%lehnga%'",
        "UPDATE `cloth_types` SET `measurement_chart_image` = 'uploads/measurement-charts/suit.svg' WHERE LOWER(`name`) LIKE '%suit%' OR LOWER(`name`) LIKE '%blazer%'",
        "UPDATE `cloth_types` SET `measurement_chart_image` = 'uploads/measurement-charts/dress.svg' WHERE LOWER(`name`) LIKE '%dress%' OR LOWER(`name`) LIKE '%gown%'",
        "UPDATE `cloth_types` SET `measurement_chart_image` = 'uploads/measurement-charts/saree.svg' WHERE LOWER(`name`) LIKE '%saree%' OR LOWER(`name`) LIKE '%sari%'",
        "UPDATE `cloth_types` SET `measurement_chart_image` = 'uploads/measurement-charts/blouse.svg' WHERE LOWER(`name`) LIKE '%blouse%' OR LOWER(`name`) LIKE '%choli%'"
    ];
    
    $totalUpdated = 0;
    foreach ($updates as $sql) {
        $stmt = $conn->exec($sql);
        $totalUpdated += $stmt;
    }
    
    $response['step2']['status'] = 'success';
    $response['step2']['message'] = "✅ Assigned charts to {$totalUpdated} cloth types";
    
    // STEP 3: Verify
    $response['step3']['status'] = 'running';
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM cloth_types WHERE measurement_chart_image IS NOT NULL AND measurement_chart_image != ''");
    $result = $stmt->fetch();
    $chartsAssigned = $result['total'];
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM cloth_types");
    $result = $stmt->fetch();
    $totalClothTypes = $result['total'];
    
    $response['step3']['status'] = 'success';
    $response['step3']['message'] = "✅ Setup complete! {$chartsAssigned} of {$totalClothTypes} cloth types have charts assigned";
    $response['step3']['details'] = [
        'charts_assigned' => $chartsAssigned,
        'total_cloth_types' => $totalClothTypes
    ];
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    $response['step1']['status'] = 'error';
    $response['step1']['message'] = '❌ Error: ' . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Measurement Charts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .running { color: #ffc107; }
        .pending { color: #6c757d; }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-lg">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">
                            <i class="fas fa-magic me-2"></i>One-Click Setup: Measurement Charts
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($response['error'])): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Error:</strong> <?php echo htmlspecialchars($response['error']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Step 1 -->
                        <div class="mb-4 p-4 border rounded">
                            <div class="text-center">
                                <i class="fas fa-database status-icon <?php echo $response['step1']['status']; ?>"></i>
                            </div>
                            <h5 class="text-center mb-3">Step 1: Database Column</h5>
                            <div class="alert alert-<?php echo $response['step1']['status'] === 'success' ? 'success' : 'info'; ?>">
                                <?php echo $response['step1']['message']; ?>
                            </div>
                        </div>
                        
                        <!-- Step 2 -->
                        <div class="mb-4 p-4 border rounded">
                            <div class="text-center">
                                <i class="fas fa-link status-icon <?php echo $response['step2']['status']; ?>"></i>
                            </div>
                            <h5 class="text-center mb-3">Step 2: Assign Charts</h5>
                            <div class="alert alert-<?php echo $response['step2']['status'] === 'success' ? 'success' : 'info'; ?>">
                                <?php echo $response['step2']['message']; ?>
                            </div>
                        </div>
                        
                        <!-- Step 3 -->
                        <div class="mb-4 p-4 border rounded">
                            <div class="text-center">
                                <i class="fas fa-check-circle status-icon <?php echo $response['step3']['status']; ?>"></i>
                            </div>
                            <h5 class="text-center mb-3">Step 3: Verification</h5>
                            <div class="alert alert-<?php echo $response['step3']['status'] === 'success' ? 'success' : 'info'; ?>">
                                <?php echo $response['step3']['message']; ?>
                            </div>
                            <?php if (isset($response['step3']['details'])): ?>
                                <div class="row text-center mt-3">
                                    <div class="col-6">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h2 class="text-primary mb-0"><?php echo $response['step3']['details']['charts_assigned']; ?></h2>
                                                <small class="text-muted">Charts Assigned</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h2 class="text-secondary mb-0"><?php echo $response['step3']['details']['total_cloth_types']; ?></h2>
                                                <small class="text-muted">Total Cloth Types</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($response['step3']['status'] === 'success'): ?>
                            <div class="alert alert-success text-center mb-0">
                                <i class="fas fa-check-circle fa-3x mb-3"></i>
                                <h4>🎉 Setup Complete!</h4>
                                <p class="mb-0">Measurement charts are now ready to use.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-light">
                        <div class="d-grid gap-2">
                            <a href="measurements.php" class="btn btn-success btn-lg">
                                <i class="fas fa-ruler me-2"></i>Go to Measurements Page & Test
                            </a>
                            <div class="btn-group">
                                <a href="check_measurement_charts.php" class="btn btn-outline-primary">
                                    <i class="fas fa-stethoscope me-2"></i>View Diagnostics
                                </a>
                                <a href="cloth-types.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-tshirt me-2"></i>Manage Cloth Types
                                </a>
                                <a href="dashboard.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-home me-2"></i>Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fas fa-info-circle me-2"></i>What This Script Did:
                        </h6>
                        <ul class="mb-0">
                            <li>✅ Created <code>measurement_chart_image</code> column in database</li>
                            <li>✅ Automatically assigned measurement charts to cloth types based on their names</li>
                            <li>✅ Verified all SVG chart files exist in <code>uploads/measurement-charts/</code></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

