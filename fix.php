<?php
$filepath = 'c:/wamp64/www/easytailor/admin/measurements.php';
$content = file_get_contents($filepath);
$pattern = '/\\\ = getDefaultCustomerId\([^)]+\);\s*\\\ = \[\s*\'customer_id\' => \\\,/';
$replacement = '\ = [';
$new_content = preg_replace($pattern, $replacement, $content);
if ($new_content !== null && $new_content !== $content) {
    file_put_contents($filepath, $new_content);
    echo \"Updated measurements.php\";
} else {
    echo \"No changes made\";
}
?>
