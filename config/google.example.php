<?php
/**
 * Google OAuth Configuration Template
 * Tailoring Management System
 * 
 * Instructions:
 * 1. Go to Google Cloud Console: https://console.cloud.google.com/apis/credentials
 * 2. Create an OAuth 2.0 Client ID (Web Application)
 * 3. Add Authorized Redirect URI:
 *    - For Localhost: http://localhost/easytailor/admin/google-callback.php
 *    - For Production: https://yourdomain.com/admin/google-callback.php
 * 4. Copy Client ID and Client Secret below.
 */

if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com');
}

if (!defined('GOOGLE_CLIENT_SECRET')) {
    define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
}

if (!defined('GOOGLE_AUTH_ENABLED')) {
    define('GOOGLE_AUTH_ENABLED', true);
}
