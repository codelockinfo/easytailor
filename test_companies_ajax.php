<?php
/**
 * Test Companies AJAX - Diagnostic Tool
 * Shows exactly what's wrong with the AJAX endpoint
 */

require_once 'config/database.php';
require_once 'models/Company.php';

echo '<h1>🔍 Companies AJAX Diagnostic</h1>';
echo '<style>
    body { font-family: Arial; padding: 20px; max-width: 1000px; margin: 0 auto; }
    .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
    code { background: #f4f4f4; padding: 3px 8px; border-radius: 3px; }
    pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
</style>';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo '<div class="success">✅ Step 1: Database connection OK</div>';
    
    // Check if companies table exists
    $tableCheck = $db->query("SHOW TABLES LIKE 'companies'");
    if ($tableCheck->rowCount() === 0) {
        echo '<div class="error">❌ Step 2: Companies table does NOT exist!</div>';
        echo '<div class="info">
            <strong>Solution:</strong> Import the SQL file in phpMyAdmin:<br>
            <code>database/setup_companies_for_listing.sql</code><br><br>
            <a href="IMPORT_COMPANIES_NOW.html">Click here for setup guide</a>
        </div>';
        exit;
    }
    
    echo '<div class="success">✅ Step 2: Companies table exists</div>';
    
    // Check for required columns
    $columns = $db->query("SHOW COLUMNS FROM companies")->fetchAll(PDO::FETCH_COLUMN);
    
    $requiredColumns = ['show_on_listing', 'is_featured', 'rating', 'total_reviews', 'description', 'specialties', 'working_hours', 'whatsapp'];
    $missingColumns = [];
    
    foreach ($requiredColumns as $col) {
        if (!in_array($col, $columns)) {
            $missingColumns[] = $col;
        }
    }
    
    if (!empty($missingColumns)) {
        echo '<div class="error">❌ Step 3: Missing columns in companies table:</div>';
        echo '<pre>' . implode("\n", $missingColumns) . '</pre>';
        echo '<div class="info">
            <strong>Solution:</strong> The companies table exists but needs new columns.<br>
            Import this SQL file in phpMyAdmin:<br>
            <code>database/setup_companies_for_listing.sql</code><br><br>
            <a href="IMPORT_COMPANIES_NOW.html">Click here for setup guide</a>
        </div>';
        exit;
    }
    
    echo '<div class="success">✅ Step 3: All required columns exist</div>';
    
    // Test the Company model
    $company = new Company($db);
    
    echo '<div class="success">✅ Step 4: Company model loaded successfully</div>';
    
    // Test searchCompanies method
    try {
        $testFilters = [
            'keyword' => 'roy',
            'limit' => 12,
            'offset' => 0,
            'sort' => 'rating'
        ];
        
        $results = $company->searchCompanies($testFilters);
        
        echo '<div class="success">✅ Step 5: Search query executed successfully</div>';
        echo '<div class="info">
            <strong>Test Search Results (keyword: "roy"):</strong><br>
            Found: ' . count($results) . ' companies<br><br>
        </div>';
        
        if (count($results) > 0) {
            echo '<pre>';
            foreach ($results as $comp) {
                echo 'Company: ' . $comp['company_name'] . ' (' . $comp['city'] . ')' . "\n";
            }
            echo '</pre>';
        }
        
    } catch (Exception $e) {
        echo '<div class="error">❌ Step 5: Search query failed!</div>';
        echo '<pre>Error: ' . $e->getMessage() . '</pre>';
        echo '<pre>File: ' . $e->getFile() . ' (Line ' . $e->getLine() . ')</pre>';
        exit;
    }
    
    // Test countCompanies method
    try {
        $count = $company->countCompanies($testFilters);
        echo '<div class="success">✅ Step 6: Count query executed successfully (Total: ' . $count . ')</div>';
    } catch (Exception $e) {
        echo '<div class="error">❌ Step 6: Count query failed!</div>';
        echo '<pre>' . $e->getMessage() . '</pre>';
    }
    
    // Test the full AJAX endpoint
    echo '<h2>Test AJAX Endpoint Directly:</h2>';
    echo '<div class="info">
        <a href="ajax/filter_tailors.php?keyword=roy&limit=12&offset=0&sort=rating" target="_blank">
            Click here to test AJAX endpoint
        </a>
    </div>';
    
    echo '<div class="success">
        <h2>🎉 All Tests Passed!</h2>
        <p>The AJAX endpoint should work now. Try the tailors page:</p>
        <a href="tailors.php">Go to Tailors Page</a>
    </div>';
    
} catch (Exception $e) {
    echo '<div class="error">❌ Fatal Error!</div>';
    echo '<pre>' . $e->getMessage() . '</pre>';
    echo '<pre>' . $e->getTraceAsString() . '</pre>';
    
    echo '<div class="info">
        <strong>Most likely cause:</strong> Companies table needs to be set up.<br><br>
        <strong>Solution:</strong> Import the SQL file:<br>
        <code>database/setup_companies_for_listing.sql</code><br><br>
        <a href="IMPORT_COMPANIES_NOW.html">Click here for setup guide</a>
    </div>';
}
?>





