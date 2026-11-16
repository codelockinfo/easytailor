<?php
/**
 * Google Login Handler
 * Tailoring Management System
 */

require_once '../config/config.php';
require_once '../helpers/GoogleAuthHelper.php';

// If already logged in, redirect to dashboard or orders
if (is_logged_in()) {
    if (get_user_role() === 'tailor') {
        smart_redirect('orders.php');
    } else {
        smart_redirect('dashboard.php');
    }
    exit;
}

$redirectUri = GoogleAuthHelper::getRedirectUri();
$isConfigured = GoogleAuthHelper::isConfigured();

// Handle saving credentials from setup screen if submitted
$setupSuccess = '';
$setupError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_google_config'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $setupError = 'Security token expired. Please refresh and try again.';
    } else {
        $clientId = trim($_POST['google_client_id'] ?? '');
        $clientSecret = trim($_POST['google_client_secret'] ?? '');

        if (empty($clientId) || empty($clientSecret)) {
            $setupError = 'Please enter both Google Client ID and Client Secret.';
        } else {
            $configContent = "<?php\n/**\n * Google OAuth Configuration\n * Tailoring Management System\n */\n\n";
            $configContent .= "if (!defined('GOOGLE_CLIENT_ID')) {\n";
            $configContent .= "    define('GOOGLE_CLIENT_ID', " . var_export($clientId, true) . ");\n";
            $configContent .= "}\n\n";
            $configContent .= "if (!defined('GOOGLE_CLIENT_SECRET')) {\n";
            $configContent .= "    define('GOOGLE_CLIENT_SECRET', " . var_export($clientSecret, true) . ");\n";
            $configContent .= "}\n\n";
            $configContent .= "if (!defined('GOOGLE_AUTH_ENABLED')) {\n";
            $configContent .= "    define('GOOGLE_AUTH_ENABLED', true);\n";
            $configContent .= "}\n";

            $configPath = __DIR__ . '/../config/google.php';
            if (file_put_contents($configPath, $configContent)) {
                $setupSuccess = 'Google OAuth credentials saved successfully! Redirecting to Google Login...';
                // Redirect to self to initiate Google OAuth
                header("Refresh: 2; url=google-login.php");
            } else {
                $setupError = 'Could not write to config/google.php. Please check file permissions or edit config/google.php manually.';
            }
        }
    }
}

// If already configured and not an explicit setup request, redirect straight to Google OAuth!
if ($isConfigured && empty($setupError) && empty($setupSuccess)) {
    // Generate secure state parameter for CSRF prevention
    $state = bin2hex(random_bytes(16));
    $_SESSION['google_oauth_state'] = $state;

    $authUrl = GoogleAuthHelper::getAuthUrl($state);
    header('Location: ' . $authUrl);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Sign-In Setup - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .setup-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
            border: none;
            overflow: hidden;
        }
        .setup-header {
            background: linear-gradient(135deg, #4285F4 0%, #34A853 100%);
            color: #ffffff;
            padding: 25px;
            text-align: center;
        }
        .code-box {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 10px 14px;
            font-family: monospace;
            font-size: 0.9rem;
            color: #1e293b;
            word-break: break-all;
        }
        .btn-copy {
            cursor: pointer;
        }
        .step-num {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #4285F4;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: bold;
            margin-right: 8px;
            flex-shrink: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7 col-md-9">
                <div class="card setup-card">
                    <div class="setup-header">
                        <div class="d-inline-flex align-items-center justify-content-center bg-white rounded-circle p-3 mb-2 shadow-sm" style="width: 60px; height: 60px;">
                            <svg width="32" height="32" viewBox="0 0 18 18">
                                <path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.616z"/>
                                <path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.258c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332C2.438 15.983 5.482 18 9 18z"/>
                                <path fill="#FBBC05" d="M3.964 10.707c-.18-.54-.282-1.117-.282-1.707s.102-1.167.282-1.707V4.961H.957C.347 6.173 0 7.547 0 9s.347 2.827.957 4.039l3.007-2.332z"/>
                                <path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0 5.482 0 2.438 2.017.957 4.961L3.964 7.293C4.672 5.166 6.656 3.58 9 3.58z"/>
                            </svg>
                        </div>
                        <h4 class="mb-1 fw-bold">Google Sign-In Setup</h4>
                        <p class="mb-0 small opacity-75">ગૂગલ લૉગિન શરૂ કરવા માટે Client ID અને Client Secret જરૂરી છે</p>
                    </div>

                    <div class="card-body p-4">
                        <?php if ($setupSuccess): ?>
                            <div class="alert alert-success d-flex align-items-center mb-4">
                                <i class="fas fa-check-circle fs-4 me-3"></i>
                                <div><?php echo htmlspecialchars($setupSuccess); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if ($setupError): ?>
                            <div class="alert alert-danger d-flex align-items-center mb-4">
                                <i class="fas fa-exclamation-triangle fs-4 me-3"></i>
                                <div><?php echo htmlspecialchars($setupError); ?></div>
                            </div>
                        <?php endif; ?>

                        <div class="alert alert-info border-0 rounded-3 mb-4" style="background: #eef2ff; color: #3730a3;">
                            <i class="fas fa-info-circle me-1"></i>
                            Google OAuth 2.0 credentials are required to enable <strong>"Continue with Google"</strong>. Follow the quick steps below:
                        </div>

                        <div class="mb-4">
                            <h6 class="fw-bold mb-3 text-secondary">Setup Instructions (ઝડપી સેટઅપ પગલાં):</h6>
                            
                            <div class="d-flex align-items-start mb-3">
                                <span class="step-num">1</span>
                                <div>
                                    Open Google Cloud Console:
                                    <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="fw-semibold text-primary ms-1">
                                        Google Cloud Console Credentials <i class="fas fa-external-link-alt small"></i>
                                    </a>
                                    <br><small class="text-muted">Create a new Project if you haven't already, then configure OAuth Consent Screen (External).</small>
                                </div>
                            </div>

                            <div class="d-flex align-items-start mb-3">
                                <span class="step-num">2</span>
                                <div>
                                    Click <strong>"Create Credentials"</strong> &rarr; <strong>"OAuth client ID"</strong>.
                                    <br><small class="text-muted">Application type: <strong>Web application</strong>.</small>
                                </div>
                            </div>

                            <div class="d-flex align-items-start mb-3">
                                <span class="step-num">3</span>
                                <div class="w-100">
                                    Under <strong>"Authorized redirect URIs"</strong>, add this exact URL:
                                    <div class="input-group mt-1">
                                        <input type="text" id="redirectUriBox" class="form-control form-control-sm code-box" value="<?php echo htmlspecialchars($redirectUri); ?>" readonly>
                                        <button class="btn btn-sm btn-outline-secondary btn-copy" type="button" onclick="copyRedirectUri()">
                                            <i class="fas fa-copy me-1"></i> Copy
                                        </button>
                                    </div>
                                    <small id="copyFeedback" class="text-success d-none"><i class="fas fa-check me-1"></i> Copied to clipboard!</small>
                                </div>
                            </div>

                            <div class="d-flex align-items-start mb-2">
                                <span class="step-num">4</span>
                                <div>
                                    Paste your <strong>Client ID</strong> and <strong>Client Secret</strong> below to activate Google Login immediately:
                                </div>
                            </div>
                        </div>

                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="save_google_config" value="1">

                            <div class="mb-3">
                                <label for="google_client_id" class="form-label fw-semibold">Google Client ID *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="google_client_id" 
                                       name="google_client_id" 
                                       placeholder="xxxxxxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxx.apps.googleusercontent.com" 
                                       value="<?php echo htmlspecialchars(GoogleAuthHelper::getClientId()); ?>"
                                       required>
                            </div>

                            <div class="mb-3">
                                <label for="google_client_secret" class="form-label fw-semibold">Google Client Secret *</label>
                                <input type="password" 
                                       class="form-control" 
                                       id="google_client_secret" 
                                       name="google_client_secret" 
                                       placeholder="GOCSPX-xxxxxxxxxxxxxxxxxxxxxxxx" 
                                       value="<?php echo htmlspecialchars(GoogleAuthHelper::getClientSecret()); ?>"
                                       required>
                            </div>

                            <div class="d-grid gap-2 mb-3">
                                <button type="submit" class="btn btn-primary py-2 fw-semibold">
                                    <i class="fas fa-save me-2"></i> Save &amp; Activate Google Sign-In
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-3">
                            <a href="login.php" class="text-decoration-none text-secondary small">
                                <i class="fas fa-arrow-left me-1"></i> Return to Login Page
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyRedirectUri() {
            const copyBox = document.getElementById('redirectUriBox');
            copyBox.select();
            copyBox.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(copyBox.value).then(function() {
                const feedback = document.getElementById('copyFeedback');
                feedback.classList.remove('d-none');
                setTimeout(() => feedback.classList.add('d-none'), 3000);
            });
        }
    </script>
</body>
</html>
