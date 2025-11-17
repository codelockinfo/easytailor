<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="../favicon(2).png">

<?php
/**
 * Diagnostic Script - Check Measurement Charts Setup
 * This script helps verify that measurement charts are properly configured
 */

require_once '../config/config.php';
require_login();

// Check if user is admin
if (!has_role('admin')) {
    die('Access denied. Admin only.');
}

require_once '../models/ClothType.php';
$clothTypeModel = new ClothType();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Measurement Charts Diagnostic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">
                    <i class="fas fa-diagnostics me-2"></i>Measurement Charts Diagnostic
                </h3>
            </div>
            <div class="card-body">
                <h5>1. Database Column Check</h5>
                <?php
                // Check if measurement_chart_image column exists
                try {
                    $db = Database::getInstance()->getConnection();
                    $query = "SHOW COLUMNS FROM cloth_types LIKE 'measurement_chart_image'";
                    $stmt = $db->query($query);
                    $columnExists = $stmt->fetch() !== false;
                    
                    if ($columnExists) {
                        echo '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>✅ Database column <code>measurement_chart_image</code> exists</div>';
                    } else {
                        echo '<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>❌ Database column <code>measurement_chart_image</code> does NOT exist</div>';
                        echo '<div class="alert alert-warning">';
                        echo '<strong>Action Required:</strong> Run the SQL migration:<br>';
                        echo '<code>database/add_measurement_charts.sql</code>';
                        echo '</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="alert alert-danger">Error checking database: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    $columnExists = false;
                }
                ?>
                
                <hr>
                
                <h5>2. Cloth Types & Charts Status</h5>
                <?php
                if ($columnExists) {
                    $clothTypes = $clothTypeModel->findAll(['status' => 'active']);
                    
                    if (empty($clothTypes)) {
                        echo '<div class="alert alert-warning">No cloth types found in database.</div>';
                    } else {
                        echo '<div class="table-responsive">';
                        echo '<table class="table table-striped">';
                        echo '<thead><tr><th>ID</th><th>Name</th><th>Chart Path</th><th>File Exists</th><th>Preview</th></tr></thead>';
                        echo '<tbody>';
                        
                        foreach ($clothTypes as $clothType) {
                            $chartPath = $clothType['measurement_chart_image'] ?? '';
                            $fileExists = !empty($chartPath) && file_exists($chartPath);
                            
                            echo '<tr>';
                            echo '<td>' . $clothType['id'] . '</td>';
                            echo '<td>' . htmlspecialchars($clothType['name']) . '</td>';
                            echo '<td>';
                            if (empty($chartPath)) {
                                echo '<span class="badge bg-secondary">Not Set</span>';
                            } else {
                                echo '<code>' . htmlspecialchars($chartPath) . '</code>';
                            }
                            echo '</td>';
                            echo '<td>';
                            if (empty($chartPath)) {
                                echo '<span class="badge bg-secondary">N/A</span>';
                            } elseif ($fileExists) {
                                echo '<span class="badge bg-success">✅ Exists</span>';
                            } else {
                                echo '<span class="badge bg-danger">❌ Missing</span>';
                            }
                            echo '</td>';
                            echo '<td>';
                            if ($fileExists) {
                                echo '<img src="' . htmlspecialchars($chartPath) . '" alt="Chart" style="max-height: 60px;">';
                            } else {
                                echo '<span class="text-muted">-</span>';
                            }
                            echo '</td>';
                            echo '</tr>';
                        }
                        
                        echo '</tbody>';
                        echo '</table>';
                        echo '</div>';
                    }
                }
                ?>
                
                <hr>
                
                <h5>3. Chart Files Directory</h5>
                <?php
                $chartDir = 'uploads/measurement-charts';
                if (is_dir($chartDir)) {
                    echo '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>✅ Directory exists: <code>' . $chartDir . '</code></div>';
                    
                    $files = scandir($chartDir);
                    $svgFiles = array_filter($files, function($file) {
                        return pathinfo($file, PATHINFO_EXTENSION) === 'svg';
                    });
                    
                    if (count($svgFiles) > 0) {
                        echo '<div class="alert alert-info">';
                        echo '<strong>Available chart files (' . count($svgFiles) . '):</strong><br>';
                        echo '<ul class="mb-0">';
                        foreach ($svgFiles as $file) {
                            echo '<li><code>' . htmlspecialchars($file) . '</code></li>';
                        }
                        echo '</ul>';
                        echo '</div>';
                    } else {
                        echo '<div class="alert alert-warning">No SVG files found in directory.</div>';
                    }
                } else {
                    echo '<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>❌ Directory does NOT exist: <code>' . $chartDir . '</code></div>';
                }
                ?>
                
                <hr>
                
                <h5>4. Quick Fix Actions</h5>
                <?php if ($columnExists && !empty($clothTypes)): ?>
                <form method="POST" action="fix_measurement_charts.php" class="mb-3">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <button type="submit" name="action" value="auto_assign" class="btn btn-primary">
                        <i class="fas fa-magic me-2"></i>Auto-Assign Charts Based on Names
                    </button>
                    <small class="d-block text-muted mt-1">This will automatically assign chart files to cloth types based on their names.</small>
                </form>
                <?php endif; ?>
                
                <a href="cloth-types.php" class="btn btn-outline-primary">
                    <i class="fas fa-tshirt me-2"></i>Manage Cloth Types
                </a>
                
                <a href="measurements.php" class="btn btn-outline-success">
                    <i class="fas fa-ruler me-2"></i>Go to Measurements
                </a>
                
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-home me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>

