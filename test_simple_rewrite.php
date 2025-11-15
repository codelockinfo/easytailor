<?php
/**
 * Simple Rewrite Test
 * Access this via: http://localhost/tailoring/test_simple_rewrite
 * (without .php) to test if rewrite is working
 */
echo "<h1>Simple Rewrite Test</h1>";
echo "<p style='color:green;font-size:18px;'>SUCCESS! If you can see this page accessed without .php extension, the rewrite IS working!</p>";
echo "<p>Accessed via: <code>" . ($_SERVER['REQUEST_URI'] ?? 'Unknown') . "</code></p>";
echo "<p>Script name: <code>" . ($_SERVER['SCRIPT_NAME'] ?? 'Unknown') . "</code></p>";
?>

