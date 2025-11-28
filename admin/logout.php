<?php
/**
 * Logout Page
 * Tailoring Management System
 */

require_once '../config/config.php';

// Check if site admin is logged in
if (isset($_SESSION['site_admin_logged_in']) && $_SESSION['site_admin_logged_in'] === true) {
    // Clear site admin session
    unset($_SESSION['site_admin_logged_in']);
    unset($_SESSION['site_admin_email']);
    session_destroy();
    // Redirect to login page
    smart_redirect('login.php?logout=1');
    exit;
}

// Regular user logout
require_once '../controllers/AuthController.php';
$authController = new AuthController();
$authController->logout();

// Redirect to login page with logout message
smart_redirect('login.php?logout=1');
?>

