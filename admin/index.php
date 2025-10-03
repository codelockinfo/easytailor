<?php
/**
 * Admin Index Page
 * Redirects to dashboard
 */

require_once '../config/config.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect(APP_URL . '/login.php');
} else {
    // Redirect to dashboard
    redirect(APP_URL . '/admin/dashboard.php');
}
?>
