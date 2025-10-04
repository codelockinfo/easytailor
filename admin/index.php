<?php
/**
 * Admin Index Page
 * Redirects to dashboard
 */

require_once '../config/config.php';

// Check if user is logged in
if (!is_logged_in()) {
    smart_redirect('login.php');
} else {
    // Redirect to dashboard
    smart_redirect('dashboard.php');
}
?>

