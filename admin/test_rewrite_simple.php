<?php
/**
 * Simple rewrite test for admin directory
 * Access via: http://localhost/tailoring/admin/test_rewrite_simple (without .php)
 */
echo "<h1>Admin Rewrite Test - SUCCESS!</h1>";
echo "<p style='color:green;font-size:18px;'>If you can see this without .php extension, the rewrite IS working!</p>";
echo "<hr>";
echo "<h2>Server Variables:</h2>";
echo "<pre>";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'Not set') . "\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'Not set') . "\n";
echo "SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'Not set') . "\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'Not set') . "\n";
echo "</pre>";
echo "<p><strong>If REQUEST_URI shows '/tailoring/admin/test_rewrite_simple', the rewrite preserved the subdirectory!</strong></p>";
?>

