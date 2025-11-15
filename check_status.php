<?php
/**
 * Quick Status Check - Companies Listing Feature
 * Shows what's working and what needs setup
 */

require_once 'config/database.php';

echo '<!DOCTYPE html>
<html>
<head>
    <title>Companies Feature Status</title>
    <style>
        body { font-family: Arial; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .card { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin: 20px 0; }
        h1 { color: #667eea; border-bottom: 3px solid #667eea; padding-bottom: 15px; }
        .success { color: #28a745; background: #d4edda; padding: 15px; border-radius: 8px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 15px; border-radius: 8px; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 15px; border-radius: 8px; margin: 10px 0; }
        .button { display: inline-block; background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 10px 5px; }
        .status { font-size: 2rem; margin-right: 10px; }
        ul { line-height: 2; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🔍 Companies Listing - Status Check</h1>';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo '<div class="success"><span class="status">✅</span> Database connection: OK</div>';
    
    // Check if companies table exists
    $tableCheck = $db->query("SHOW TABLES LIKE 'companies'");
    $companiesTableExists = ($tableCheck->rowCount() > 0);
    
    if (!$companiesTableExists) {
        echo '<div class="error"><span class="status">❌</span> Companies table: NOT FOUND</div>';
        echo '<div class="warning">
            <h3>Action Required:</h3>
            <p>The companies table needs to be created.</p>
            <a href="IMPORT_COMPANIES_NOW.html" class="button">📖 Open Setup Guide</a>
        </div>';
    } else {
        echo '<div class="success"><span class="status">✅</span> Companies table: EXISTS</div>';
        
        // Check for required columns
        $columnsCheck = $db->query("SHOW COLUMNS FROM companies LIKE 'show_on_listing'");
        $hasNewColumns = ($columnsCheck->rowCount() > 0);
        
        if (!$hasNewColumns) {
            echo '<div class="error"><span class="status">❌</span> New columns: MISSING</div>';
            echo '<div class="warning">
                <h3>Action Required:</h3>
                <p>The companies table exists but needs new columns for listing feature.</p>
                <p><strong>Import:</strong> <code>database/setup_companies_for_listing.sql</code></p>
                <a href="IMPORT_COMPANIES_NOW.html" class="button">📖 Open Setup Guide</a>
            </div>';
        } else {
            echo '<div class="success"><span class="status">✅</span> New columns: EXISTS</div>';
            
            // Check for sample data
            $countQuery = $db->query("SELECT COUNT(*) as total FROM companies WHERE show_on_listing = 1");
            $count = $countQuery->fetch();
            
            if ($count['total'] == 0) {
                echo '<div class="warning"><span class="status">⚠️</span> Sample companies: NONE</div>';
                echo '<div class="warning">
                    <p>Table is ready but no companies set to show on listing.</p>
                    <p>Either import the sample data or add <code>show_on_listing = 1</code> to existing companies.</p>
                </div>';
            } else {
                echo '<div class="success"><span class="status">✅</span> Sample companies: ' . $count['total'] . ' found</div>';
                
                // Get statistics
                $statsQuery = $db->query("SELECT 
                    SUM(is_featured) as featured,
                    SUM(is_verified) as verified,
                    AVG(rating) as avg_rating
                    FROM companies WHERE show_on_listing = 1");
                $stats = $statsQuery->fetch();
                
                echo '<div class="card" style="background: #e7f3ff;">
                    <h2>📊 Statistics:</h2>
                    <ul>
                        <li><strong>Total visible companies:</strong> ' . $count['total'] . '</li>
                        <li><strong>Featured companies:</strong> ' . $stats['featured'] . '</li>
                        <li><strong>Verified companies:</strong> ' . $stats['verified'] . '</li>
                        <li><strong>Average rating:</strong> ' . number_format($stats['avg_rating'], 1) . '★</li>
                    </ul>
                </div>';
                
                echo '<div class="success">
                    <h2>🎉 Everything is Ready!</h2>
                    <p>The companies listing feature is fully configured!</p>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="index.php" class="button">🏠 View Landing Page</a>
                        <a href="tailors.php" class="button">📋 View All Shops</a>
                    </div>
                </div>';
            }
        }
    }
    
    // Check required files
    echo '<div class="card">
        <h2>📁 File Status:</h2>';
    
    $files = [
        'models/Company.php' => 'Company Model',
        'ajax/filter_tailors.php' => 'Filter AJAX',
        'tailors.php' => 'Listing Page',
        'database/setup_companies_for_listing.sql' => 'SQL Migration'
    ];
    
    foreach ($files as $file => $name) {
        if (file_exists($file)) {
            echo '<div class="success">✅ ' . $name . ': <code>' . $file . '</code></div>';
        } else {
            echo '<div class="error">❌ ' . $name . ': Missing</div>';
        }
    }
    
    echo '</div>';
    
} catch (Exception $e) {
    echo '<div class="error"><span class="status">❌</span> Error: ' . $e->getMessage() . '</div>';
}

echo '</div>

<div class="card" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; text-align: center;">
    <h2 style="color: white; margin-top: 0;">Need to Setup?</h2>
    <p style="font-size: 1.1rem;">Import the SQL file to get started!</p>
    <a href="IMPORT_COMPANIES_NOW.html" class="button" style="background: white; color: #667eea;">
        📖 Open Visual Setup Guide
    </a>
    <a href="http://localhost/phpmyadmin" target="_blank" class="button">
        🔗 Open phpMyAdmin
    </a>
</div>

</body>
</html>';
?>








