<?php
/**
 * Test Admin Rewrite
 * Access this via: http://localhost/tailoring/test_admin_rewrite
 */
echo "<h1>Admin Rewrite Test</h1>";
echo "<p style='color:green;font-size:18px;'>SUCCESS! Rewrite is working!</p>";
echo "<p>If you can see this without .php extension, the rewrite works for admin paths too.</p>";
echo "<hr>";
echo "<h2>Test Admin Pages:</h2>";
echo "<ul>";
echo "<li><a href='admin/dashboard'>admin/dashboard</a> (should work without .php)</li>";
echo "<li><a href='admin/login'>admin/login</a> (should work without .php)</li>";
echo "<li><a href='admin/customers'>admin/customers</a> (should work without .php)</li>";
echo "<li><a href='admin/profile'>admin/profile</a> (should work without .php)</li>";
echo "</ul>";
echo "<p>Accessed via: <code>" . ($_SERVER['REQUEST_URI'] ?? 'Unknown') . "</code></p>";
echo "<p>Script name: <code>" . ($_SERVER['SCRIPT_NAME'] ?? 'Unknown') . "</code></p>";
?>

