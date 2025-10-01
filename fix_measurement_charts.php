<?php
/**
 * Auto-assign measurement charts to cloth types
 */

require_once 'config/config.php';
require_login();

// Check if user is admin
if (!has_role('admin')) {
    die('Access denied. Admin only.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'auto_assign') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    require_once 'models/ClothType.php';
    $clothTypeModel = new ClothType();
    
    // Get all cloth types
    $clothTypes = $clothTypeModel->findAll();
    
    $updated = 0;
    $skipped = 0;
    
    foreach ($clothTypes as $clothType) {
        $name = strtolower($clothType['name']);
        $chartPath = '';
        
        // Auto-detect chart based on name
        if (strpos($name, 'pant') !== false || strpos($name, 'trouser') !== false) {
            $chartPath = 'uploads/measurement-charts/pants.svg';
        } elseif (strpos($name, 'shirt') !== false) {
            $chartPath = 'uploads/measurement-charts/shirt.svg';
        } elseif (strpos($name, 'kurta') !== false || strpos($name, 'kameez') !== false) {
            $chartPath = 'uploads/measurement-charts/kurta.svg';
        } elseif (strpos($name, 'lehenga') !== false || strpos($name, 'lehnga') !== false) {
            $chartPath = 'uploads/measurement-charts/lehenga.svg';
        } elseif (strpos($name, 'suit') !== false || strpos($name, 'blazer') !== false) {
            $chartPath = 'uploads/measurement-charts/suit.svg';
        } elseif (strpos($name, 'dress') !== false || strpos($name, 'gown') !== false) {
            $chartPath = 'uploads/measurement-charts/dress.svg';
        } elseif (strpos($name, 'saree') !== false || strpos($name, 'sari') !== false) {
            $chartPath = 'uploads/measurement-charts/saree.svg';
        } elseif (strpos($name, 'blouse') !== false || strpos($name, 'choli') !== false) {
            $chartPath = 'uploads/measurement-charts/blouse.svg';
        }
        
        if (!empty($chartPath) && file_exists($chartPath)) {
            $clothTypeModel->update($clothType['id'], ['measurement_chart_image' => $chartPath]);
            $updated++;
        } else {
            $skipped++;
        }
    }
    
    $_SESSION['success_message'] = "Auto-assignment complete! Updated: $updated, Skipped: $skipped";
    header('Location: check_measurement_charts.php');
    exit;
}

header('Location: check_measurement_charts.php');
exit;
?>

