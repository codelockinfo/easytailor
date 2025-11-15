<?php
/**
 * Test if mod_rewrite is working
 */
echo "<h1>Mod_Rewrite Test</h1>";
echo "<h2>Server Information:</h2>";
echo "SERVER_SOFTWARE: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "<br>";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'Unknown') . "<br>";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'Unknown') . "<br>";
echo "REQUEST_FILENAME: " . ($_SERVER['REQUEST_FILENAME'] ?? 'Not set') . "<br>";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "<br>";

echo "<h2>Apache Modules:</h2>";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    echo "mod_rewrite: " . (in_array('mod_rewrite', $modules) ? "<strong style='color:green'>ENABLED</strong>" : "<strong style='color:red'>DISABLED</strong>") . "<br>";
    echo "Available modules: " . implode(', ', $modules) . "<br>";
} else {
    echo "Cannot check Apache modules (function not available - may be running on different server)<br>";
}

echo "<h2>Current Request:</h2>";
echo "Full URL: " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "<br>";

echo "<h2>Test URLs:</h2>";
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
echo "<a href='admin/dashboard' target='_blank'>Test: admin/dashboard (should work without .php)</a><br><br>";
echo "<a href='admin/dashboard.php' target='_blank'>Test: admin/dashboard.php (should redirect to admin/dashboard)</a><br><br>";
echo "<a href='admin/login' target='_blank'>Test: admin/login (should work without .php)</a><br><br>";
echo "<a href='admin/customers' target='_blank'>Test: admin/customers (should work without .php)</a><br><br>";

echo "<h2>File Existence Check:</h2>";
$testFiles = [
    'admin/dashboard.php' => __DIR__ . '/admin/dashboard.php',
    'admin/login.php' => __DIR__ . '/admin/login.php',
    'admin/customers.php' => __DIR__ . '/admin/customers.php',
];

foreach ($testFiles as $url => $file) {
    $exists = file_exists($file);
    echo "$url: " . ($exists ? "<span style='color:green'>EXISTS</span>" : "<span style='color:red'>NOT FOUND</span>") . "<br>";
}

echo "<h2>.htaccess File:</h2>";
$htaccessPath = __DIR__ . '/.htaccess';
if (file_exists($htaccessPath)) {
    echo "<span style='color:green'>.htaccess file exists</span><br>";
    echo "File size: " . filesize($htaccessPath) . " bytes<br>";
} else {
    echo "<span style='color:red'>.htaccess file NOT FOUND</span><br>";
}

?>
