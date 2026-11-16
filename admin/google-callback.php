<?php
/**
 * Google OAuth Callback Handler
 * Tailoring Management System
 */

require_once '../config/config.php';
require_once '../helpers/GoogleAuthHelper.php';
require_once '../controllers/AuthController.php';

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    if (get_user_role() === 'tailor') {
        smart_redirect('orders.php');
    } else {
        smart_redirect('dashboard.php');
    }
    exit;
}

// 1. Check for errors returned by Google
if (isset($_GET['error'])) {
    $error = $_GET['error'];
    $errorDescription = $_GET['error_description'] ?? 'Google sign-in was cancelled or declined.';
    $_SESSION['login_error'] = 'Google Sign-In failed: ' . $errorDescription;
    smart_redirect('login.php');
    exit;
}

// 2. Validate state token to prevent CSRF attacks
$state = $_GET['state'] ?? '';
$sessionState = $_SESSION['google_oauth_state'] ?? '';
unset($_SESSION['google_oauth_state']);

if (empty($state) || empty($sessionState) || !hash_equals($sessionState, $state)) {
    $_SESSION['login_error'] = 'Invalid Google authentication session. Please try logging in again.';
    smart_redirect('login.php');
    exit;
}

// 3. Check for authorization code
$code = $_GET['code'] ?? '';
if (empty($code)) {
    $_SESSION['login_error'] = 'No authorization code received from Google.';
    smart_redirect('login.php');
    exit;
}

// 4. Exchange code for access token
$tokenResult = GoogleAuthHelper::getAccessToken($code);
if (!$tokenResult['success']) {
    $_SESSION['login_error'] = $tokenResult['message'];
    smart_redirect('login.php');
    exit;
}

// 5. Fetch user profile from Google
$userResult = GoogleAuthHelper::getUserInfo($tokenResult['access_token']);
if (!$userResult['success']) {
    $_SESSION['login_error'] = $userResult['message'];
    smart_redirect('login.php');
    exit;
}

$googleUser = $userResult['data'];

// 6. Log in or auto-register the user using AuthController
$authController = new AuthController();
$result = $authController->loginWithGoogleUser($googleUser);

if ($result['success']) {
    $_SESSION['login_success'] = 'Welcome, ' . htmlspecialchars($googleUser['name'] ?: 'back') . '! Logged in with Google.';
    smart_redirect($result['redirect'] ?? 'dashboard.php');
    exit;
} else {
    $_SESSION['login_error'] = $result['message'];
    smart_redirect('login.php');
    exit;
}
