<?php
/**
 * Google OAuth Helper
 * Tailoring Management System
 */

class GoogleAuthHelper {
    const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    const USERINFO_ENDPOINT = 'https://www.googleapis.com/oauth2/v3/userinfo';
    const AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

    /**
     * Check if running on local environment
     */
    public static function isLocal() {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return ($host === 'localhost' || strpos($host, '127.0.0.1') !== false || strpos($host, '::1') !== false);
    }

    /**
     * Get Client ID
     */
    public static function getClientId() {
        return defined('GOOGLE_CLIENT_ID') ? trim(GOOGLE_CLIENT_ID) : '';
    }

    /**
     * Get Client Secret
     */
    public static function getClientSecret() {
        return defined('GOOGLE_CLIENT_SECRET') ? trim(GOOGLE_CLIENT_SECRET) : '';
    }

    /**
     * Check if Google Auth is properly configured
     */
    public static function isConfigured() {
        $clientId = self::getClientId();
        $clientSecret = self::getClientSecret();

        if (empty($clientId) || empty($clientSecret)) {
            return false;
        }

        // Check if placeholder is still present
        if (strpos($clientId, 'YOUR_GOOGLE_CLIENT_ID') !== false || strpos($clientSecret, 'YOUR_GOOGLE_CLIENT_SECRET') !== false) {
            return false;
        }

        return true;
    }

    /**
     * Dynamically compute the Google OAuth Redirect URI
     */
    public static function getRedirectUri() {
        if (defined('GOOGLE_REDIRECT_URI') && !empty(GOOGLE_REDIRECT_URI)) {
            return GOOGLE_REDIRECT_URI;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
        
        $protocol = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = dirname($scriptName);
        $dir = str_replace('\\', '/', $dir);

        if ($dir === '/' || $dir === '.' || empty($dir)) {
            $adminDir = '/admin';
        } elseif (substr($dir, -6) === '/admin') {
            $adminDir = $dir;
        } else {
            $adminDir = rtrim($dir, '/') . '/admin';
        }

        return $protocol . '://' . $host . $adminDir . '/google-callback.php';
    }

    /**
     * Build Google OAuth Authorization URL
     */
    public static function getAuthUrl($state) {
        $params = [
            'client_id' => self::getClientId(),
            'redirect_uri' => self::getRedirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account'
        ];

        return self::AUTH_ENDPOINT . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     */
    public static function getAccessToken($code) {
        $postData = [
            'code' => $code,
            'client_id' => self::getClientId(),
            'client_secret' => self::getClientSecret(),
            'redirect_uri' => self::getRedirectUri(),
            'grant_type' => 'authorization_code'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::TOKEN_ENDPOINT);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ]);

        // In local development on Windows, CA certificates might be missing in php.ini
        if (self::isLocal()) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return [
                'success' => false,
                'message' => 'Connection to Google failed: ' . $curlError
            ];
        }

        $data = json_decode($response, true);
        if ($httpCode !== 200 || empty($data['access_token'])) {
            $errorDesc = $data['error_description'] ?? ($data['error'] ?? 'Unknown token exchange error');
            return [
                'success' => false,
                'message' => 'Google authorization failed: ' . $errorDesc,
                'raw' => $data
            ];
        }

        return [
            'success' => true,
            'access_token' => $data['access_token'],
            'id_token' => $data['id_token'] ?? null,
            'expires_in' => $data['expires_in'] ?? null,
            'raw' => $data
        ];
    }

    /**
     * Fetch user profile from Google using access token
     */
    public static function getUserInfo($accessToken) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::USERINFO_ENDPOINT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json'
        ]);

        if (self::isLocal()) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return [
                'success' => false,
                'message' => 'Could not fetch Google user profile: ' . $curlError
            ];
        }

        $data = json_decode($response, true);
        if ($httpCode !== 200 || empty($data['sub'])) {
            return [
                'success' => false,
                'message' => 'Invalid profile data returned by Google.',
                'raw' => $data
            ];
        }

        return [
            'success' => true,
            'data' => [
                'sub' => $data['sub'],
                'email' => $data['email'] ?? '',
                'email_verified' => $data['email_verified'] ?? false,
                'name' => $data['name'] ?? '',
                'given_name' => $data['given_name'] ?? '',
                'family_name' => $data['family_name'] ?? '',
                'picture' => $data['picture'] ?? null
            ]
        ];
    }

    /**
     * Ensure google_id and avatar columns exist in users table
     */
    public static function ensureDatabaseColumns($db = null) {
        try {
            if (!$db) {
                require_once __DIR__ . '/../config/database.php';
                $database = new Database();
                $db = $database->getConnection();
            }
            if (!$db) return false;

            // Check google_id
            $checkCol = $db->query("SHOW COLUMNS FROM users LIKE 'google_id'");
            if ($checkCol && $checkCol->rowCount() === 0) {
                $db->exec("ALTER TABLE users ADD COLUMN google_id VARCHAR(100) DEFAULT NULL AFTER email");
                $db->exec("ALTER TABLE users ADD INDEX idx_google_id (google_id)");
            }

            // Check avatar
            $checkAvatar = $db->query("SHOW COLUMNS FROM users LIKE 'avatar'");
            if ($checkAvatar && $checkAvatar->rowCount() === 0) {
                $db->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL AFTER address");
            }

            return true;
        } catch (Exception $e) {
            error_log("Error in ensureDatabaseColumns: " . $e->getMessage());
            return false;
        }
    }
}
