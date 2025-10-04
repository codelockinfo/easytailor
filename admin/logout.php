<?php
/**
 * Logout Page
 * Tailoring Management System
 */

require_once '../config/config.php';
require_once '../controllers/AuthController.php';

$authController = new AuthController();
$authController->logout();

// Redirect to login page with logout message
smart_redirect('login.php?logout=1');
?>

