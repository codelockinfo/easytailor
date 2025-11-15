<?php
/**
 * Debug Rewrite Rules
 * Shows what's happening with URL rewriting
 */

echo "<h1>Rewrite Debug Information</h1>";

echo "<h2>Server Variables:</h2>";
echo "<pre>";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'Not set') . "\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'Not set') . "\n";
echo "SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'Not set') . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Not set') . "\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'Not set') . "\n";
echo "PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? 'Not set') . "\n";
echo "</pre>";

echo "<h2>Path Analysis:</h2>";
$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

echo "Document Root: $docRoot<br>";
echo "Request URI: $requestUri<br>";
echo "Script Name: $scriptName<br>";

// Test file existence
$testFiles = [
    'admin/dashboard.php',
    'admin/login.php',
    'admin/customers.php',
];

echo "<h2>File Existence Check:</h2>";
foreach ($testFiles as $file) {
    $fullPath = $docRoot . '/' . ltrim($file, '/');
    $exists = file_exists($fullPath);
    $relative = __DIR__ . '/' . $file;
    $relativeExists = file_exists($relative);
    
    echo "<strong>$file:</strong><br>";
    echo "&nbsp;&nbsp;Absolute (DOCUMENT_ROOT): $fullPath - " . ($exists ? '<span style="color:green">EXISTS</span>' : '<span style="color:red">NOT FOUND</span>') . "<br>";
    echo "&nbsp;&nbsp;Relative (from this file): $relative - " . ($relativeExists ? '<span style="color:green">EXISTS</span>' : '<span style="color:red">NOT FOUND</span>') . "<br><br>";
}

// Test what the rewrite should do
echo "<h2>Rewrite Simulation:</h2>";
$testUrl = '/tailoring/admin/dashboard';
echo "Test URL: $testUrl<br>";
$withoutExt = rtrim($testUrl, '/');
$withExt = $withoutExt . '.php';
echo "Should rewrite to: $withExt<br>";

$rewritePath = $docRoot . $withExt;
echo "Full path check: $rewritePath<br>";
echo "File exists: " . (file_exists($rewritePath) ? '<span style="color:green">YES</span>' : '<span style="color:red">NO</span>') . "<br>";

echo "<h2>Current Request:</h2>";
echo "If you accessed this page via: <code>" . htmlspecialchars($requestUri) . "</code><br>";

if (strpos($requestUri, '.php') === false) {
    echo "<p style='color:blue;'>This URL doesn't have .php extension - rewrite might be working!</p>";
    echo "<p>If you can see this page, the rewrite rule is NOT matching (this is debug_rewrite.php, not debug_rewrite)</p>";
} else {
    echo "<p style='color:orange;'>This URL has .php extension</p>";
}

?>

