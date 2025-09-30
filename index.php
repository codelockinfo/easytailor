<?php
/**
 * Index/Home Page
 * Tailoring Management System
 */

require_once 'config/config.php';

// Redirect to login if not logged in
if (!is_logged_in()) {
    redirect(APP_URL . '/login.php');
} else {
    // Redirect to dashboard if logged in
    redirect(APP_URL . '/dashboard.php');
}
?>

