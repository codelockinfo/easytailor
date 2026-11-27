<?php
require_once '../config/config.php';
if (is_logged_in()) {
    if (get_user_role() === 'tailor') {
        smart_redirect('orders.php');
    } else {
        smart_redirect('dashboard.php');
    }
}
$error_message = '';
$success_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $authMethod = sanitize_input($_POST['auth_method'] ?? 'password');
    if ($authMethod === 'otp_verify') {
        $email = trim(sanitize_input($_POST['email'] ?? ''));
        $code = trim(sanitize_input($_POST['code'] ?? ''));
        if (!empty($email) && !empty($code)) {
            require_once '../controllers/AuthController.php';
            $authController = new AuthController();
            $result = $authController->verifyLoginOtp($email, $code);
            if ($result['success']) {
                smart_redirect($result['redirect'] ?? 'dashboard.php');
                exit;
            } else {
                $_SESSION['login_error'] = $result['message'];
                smart_redirect('login.php?method=otp&email=' . urlencode($email));
                exit;
            }
        } else {
            $_SESSION['login_error'] = 'Please enter both your email and the 6-digit verification code.';
            smart_redirect('login.php?method=otp');
            exit;
        }
    }
    $username = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === 'codelock2021@gmail.com' && $password === 'Codelock@63') {
        $_SESSION['site_admin_logged_in'] = true;
        $_SESSION['site_admin_email'] = $username;
        smart_redirect('../siteadmin/index.php');
        exit;
    }
    if (!empty($username) && !empty($password)) {
        require_once '../controllers/AuthController.php';
        $authController = new AuthController();
        $result = $authController->loginWithPasswordOtp($username, $password, $_SERVER['REMOTE_ADDR'] ?? '');
        if ($result['success']) {
            if (!empty($result['requires_otp'])) {
                $_SESSION['login_success'] = $result['message'];
                smart_redirect('login.php?step=verify&email=' . urlencode($result['email']));
                exit;
            } else {
                smart_redirect($result['redirect'] ?? 'dashboard.php');
                exit;
            }
        } else {
            $_SESSION['login_error'] = $result['message'];
            smart_redirect('login.php');
            exit;
        }
    } else {
        $_SESSION['login_error'] = 'Please enter both username and password';
        smart_redirect('login.php');
        exit;
    }
}
if (isset($_SESSION['login_error'])) {
    $error_message = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'account_deleted') {
        $error_message = 'Your account or company record no longer exists in the system. You have been logged out.';
    } elseif ($_GET['error'] === 'account_inactive') {
        $error_message = 'Your account is currently inactive or disabled. Please contact administrator.';
    }
}
if (isset($_GET['password_changed'])) {
    $success_message = 'Password changed successfully. Please sign in with your new password.';
} elseif (isset($_GET['logout'])) {
    $success_message = 'You have been logged out successfully';
}
if (isset($_SESSION['reg_success'])) {
    $success_message = $_SESSION['reg_success'];
    unset($_SESSION['reg_success']);
}
if (isset($_SESSION['login_success'])) {
    $success_message = $_SESSION['login_success'];
    unset($_SESSION['login_success']);
}
$isVerifyStep = (isset($_GET['step']) && $_GET['step'] === 'verify' && !empty($_GET['email']));
$verifyEmail = $isVerifyStep ? sanitize_input($_GET['email']) : '';
?>
<?php
require_once '../helpers/SEOHelper.php';
$baseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$canonicalUrl = $baseUrl . '/admin/login.php';
$seoOptions = [
    'title' => 'Login - ' . (defined('APP_NAME') ? APP_NAME : 'Tailoring Management System'),
    'description' => 'Login to your tailoring management system account to manage your tailor shop, customers, orders, and invoices.',
    'keywords' => 'login, tailor login, tailor shop login, tailoring system login',
    'canonical' => $canonicalUrl,
    'noindex' => true
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo SEOHelper::generateMetaTags($seoOptions); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php echo render_favicon_links(); ?>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            margin-top: 15px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
            text-align: center;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .input-group-text {
            background: transparent;
            border: 2px solid #e9ecef;
            border-right: none;
            border-radius: 10px 0 0 10px;
        }
        .form-control.with-icon {
            border-radius: 0 10px 10px 0;
        }

        .code-inputs {
            display: flex;
            justify-content: center;
            gap: 8px;
        }
        .code-digit {
            width: 44px;
            height: 52px;
            text-align: center;
            font-size: 1.4rem;
            font-weight: 700;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            background: #ffffff;
            transition: all 0.2s ease;
        }
        .code-digit:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            outline: none;
        }

        .mt-4 {
            margin-top: 0.5rem !important;
        }
        .btn-google {
            background: #ffffff;
            color: #3c4043;
            border: 1px solid #dadce0;
            border-radius: 10px;
            padding: 11px 16px;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.25s ease;
            text-decoration: none;
            box-shadow: 0 1px 2px rgba(60,64,67,0.08);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btn-google:hover {
            background: #f8f9fa;
            color: #202124;
            border-color: #c6c9cc;
            box-shadow: 0 2px 6px rgba(60,64,67,0.15);
            transform: translateY(-1px);
        }
        .btn-google:active {
            background: #eeeeee;
            transform: translateY(0);
            box-shadow: none;
        }
        .login-divider {
            position: relative;
            text-align: center;
        }
        .login-divider .divider-line {
            flex-grow: 1;
            height: 1px;
            background: #e2e8f0;
        }
        .login-divider .divider-text {
            padding: 0 14px;
            font-size: 0.78rem;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            color: #94a3b8;
        }
        @media (max-width: 768px) {
            .login-header p {
                font-size: 14px;
            }
            .text-center.mt-4 a {
                font-size: 15px;
            }
            .code-digit {
                width: 38px;
                height: 48px;
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card login-card border-0">
                    <div class="login-header">
                        <a href="../" class="text-decoration-none">
                        <?php
                        $brandLogo = get_site_logo('admin');
                        if (!empty($brandLogo)):
                        ?>
                            <img src="<?php echo htmlspecialchars($brandLogo); ?>" alt="<?php echo APP_NAME; ?>" class="" style="max-height: 80px; max-width: 200px;">
                        <?php else: ?>
                            <i class="fas fa-cut fa-3x mb-3"></i>
                        <?php endif; ?>
                        </a>
                        <p class="mb-0 opacity-75"><?php echo $isVerifyStep ? 'Email Verification' : __t('sign_in_to_account', 'Sign in to your account'); ?></p>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($error_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        <div id="otpAlertBox" class="alert d-none mb-3" role="alert"></div>

                        <div id="stepPassword" style="<?php echo $isVerifyStep ? 'display:none;' : ''; ?>">
                     
                            <div class="mb-3">
                                <a href="google-login.php" class="btn btn-google w-100" id="btnGoogleLogin">
                                    <svg class="me-2 flex-shrink-0" width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg">
                                        <path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.616z"/>
                                        <path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.258c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332C2.438 15.983 5.482 18 9 18z"/>
                                        <path fill="#FBBC05" d="M3.964 10.707c-.18-.54-.282-1.117-.282-1.707s.102-1.167.282-1.707V4.961H.957C.347 6.173 0 7.547 0 9s.347 2.827.957 4.039l3.007-2.332z"/>
                                        <path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0 5.482 0 2.438 2.017.957 4.961L3.964 7.293C4.672 5.166 6.656 3.58 9 3.58z"/>
                                    </svg>
                                    <span><?php echo __t('continue_with_google', 'Continue with Google'); ?></span>
                                </a>
                            </div>
                            <div class="login-divider my-3 d-flex align-items-center">
                                <span class="divider-line"></span>
                                <span class="divider-text"><?php echo __t('or', 'or'); ?></span>
                                <span class="divider-line"></span>
                            </div>
                            <form method="POST" action="" id="passwordLoginForm" onsubmit="handlePasswordLogin(event)">
                                <input type="hidden" name="csrf_token" id="loginCsrfToken" value="<?php echo generate_csrf_token(); ?>">
                                <input type="hidden" name="auth_method" value="password">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username or Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-user"></i>
                                        </span>
                                        <input type="text"
                                               class="form-control with-icon"
                                               id="username"
                                               name="username"
                                               placeholder="Enter username or email"
                                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                               autocomplete="username"
                                               required>
                                    </div>
                                    <div class="text-danger small mt-1 d-none fw-semibold" id="usernameFeedback">
                                        <i class="fas fa-times-circle me-1"></i><span></span>
                                    </div>
                                </div>
                                <div class="mb-3" id="passwordFieldBlock">
                                    <label for="password" class="form-label">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password"
                                               class="form-control with-icon"
                                               id="password"
                                               name="password"
                                               placeholder="Enter password"
                                               autocomplete="current-password"
                                               required>
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="text-danger small mt-1 d-none fw-semibold" id="passwordFeedback">
                                        <i class="fas fa-times-circle me-1"></i><span></span>
                                    </div>
                                </div>
                                <div class="d-grid mb-3">
                                    <button type="submit" id="btnPasswordSignIn" class="btn btn-primary btn-login">
                                        <span class="btn-text"><i class="fas fa-sign-in-alt me-2"></i>Sign In</span>
                                        <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                                    </button>
                                </div>
                                <div class="text-center mt-3 mb-1">
                                    <a href="forgot-password.php" class="text-decoration-none small text-muted">
                                        <i class="fas fa-key me-1"></i>Forgot Password?
                                    </a>
                                </div>
                            </form>
                        </div>
                        <div id="stepOtpVerify" style="<?php echo $isVerifyStep ? 'display:block;' : 'display:none;'; ?>">
                            <div class="p-2 mb-3 rounded-3 text-center" style="background:#f1f5f9;font-size:0.82rem;">
                                <span>Code sent to <strong id="otpTargetEmailText" class="text-dark"><?php echo htmlspecialchars($verifyEmail); ?></strong></span>
                                <button type="button" class="btn btn-link btn-sm text-primary p-0 ms-1" style="font-size:0.8rem;text-decoration:none;" onclick="backToPassword()">Change</button>
                            </div>
                            <form id="formVerifyOtp" onsubmit="handleVerifyOtp(event)">
                                <input type="hidden" id="otpCsrfToken" value="<?php echo generate_csrf_token(); ?>">
                                <input type="hidden" id="postOtpEmail" value="<?php echo htmlspecialchars($verifyEmail); ?>">
                                <input type="hidden" id="postOtpCode">
                                <div class="mb-3">
                                    <label class="form-label text-center w-100 mb-2" style="font-size:0.88rem;font-weight:600;">Verification Code</label>
                                    <div class="code-inputs" id="codeInputsContainer">
                                        <input type="text" class="code-digit" maxlength="1" pattern="\d" inputmode="numeric" autocomplete="one-time-code" required>
                                        <input type="text" class="code-digit" maxlength="1" pattern="\d" inputmode="numeric" required>
                                        <input type="text" class="code-digit" maxlength="1" pattern="\d" inputmode="numeric" required>
                                        <input type="text" class="code-digit" maxlength="1" pattern="\d" inputmode="numeric" required>
                                        <input type="text" class="code-digit" maxlength="1" pattern="\d" inputmode="numeric" required>
                                        <input type="text" class="code-digit" maxlength="1" pattern="\d" inputmode="numeric" required>
                                    </div>
                                </div>
                                <div class="text-center mb-3">
                                    <button type="button" id="btnResendOtp" class="btn btn-link text-decoration-none p-0" style="font-size:0.82rem;" onclick="handleResendOtp()" disabled>
                                        Resend code in <span id="otpCountdown" class="fw-bold">60</span>s
                                    </button>
                                </div>
                                <div class="d-grid mb-3">
                                    <button type="submit" id="btnVerifyOtp" class="btn btn-primary btn-login">
                                        <span class="btn-text"><i class="fas fa-check-circle me-2"></i>Verify &amp; Sign In</span>
                                        <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                                    </button>
                                </div>
                                <div class="text-center">
                                    <?php if (isset($_GET['registered']) && $_GET['registered'] === '1'): ?>
                                        <a href="register.php" class="btn btn-link p-0 text-secondary small text-decoration-none">
                                            <i class="fas fa-arrow-left me-1"></i>Back to Registration
                                        </a>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-link p-0 text-secondary small text-decoration-none" onclick="backToPassword()">
                                            <i class="fas fa-arrow-left me-1"></i>Back to Password Login
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>

                        <div class="text-center mt-4">
                            <p class="text-muted mb-2">
                                Don't have an account? 
                            </p>
                                
                            <a href="register.php" class="text-decoration-none fw-semibold">
                                <i class="fas fa-user-plus me-1"></i>Register Your Tailor Shop
                            </a><br><br>
                            
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Free Plan • No credit card required
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <p class="text-white opacity-75">
                        &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let resendTimer = null;
        let cooldownSeconds = 60;
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert.alert-dismissible:not(#otpAlertBox)');
            alerts.forEach(function(alert) {
                try {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                } catch(e) {}
            });
        }, 5000);
        <?php if (isset($_SESSION['ga4_event']) && !empty($_SESSION['ga4_event'])): ?>
        (function() {
            var attempts = 0;
            var maxAttempts = 50;
            function fireGA4Event() {
                if (typeof gtag !== 'undefined' && typeof window.dataLayer !== 'undefined') {
                    try {
                        <?php echo $_SESSION['ga4_event']; ?>
                        console.log('GA4 event fired successfully');
                    } catch (e) {
                        console.error('GA4 event tracking error:', e);
                    }
                } else {
                    attempts++;
                    if (attempts < maxAttempts) {
                        setTimeout(fireGA4Event, 100);
                    } else {
                        console.warn('GA4 not loaded after 5 seconds, event may be lost');
                    }
                }
            }
            fireGA4Event();
        })();
        <?php 
        unset($_SESSION['ga4_event']);
        endif; 
        ?>
        function showOtpAlert(message, type = 'danger') {
            let box = document.getElementById('otpAlertBox');
            if (!box) {
                const container = document.getElementById('stepPassword') || document.querySelector('.card-body');
                if (container) {
                    box = document.createElement('div');
                    box.id = 'otpAlertBox';
                    container.parentNode.insertBefore(box, container);
                }
            }
            if (!box) return;
            box.className = `alert alert-${type} d-flex align-items-center justify-content-between mb-3 shadow-sm`;
            box.style.display = 'flex';
            box.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-${type === 'success' ? 'check-circle text-success' : 'exclamation-circle text-danger'} fs-5 flex-shrink-0"></i>
                    <span class="fw-semibold" style="font-size:0.88rem;">${message}</span>
                </div>
                <button type="button" class="btn-close ms-2" onclick="hideOtpAlert()" aria-label="Close"></button>
            `;
            box.classList.remove('d-none');
            box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        function hideOtpAlert() {
            const box = document.getElementById('otpAlertBox');
            if (box) {
                box.classList.add('d-none');
                box.style.display = 'none';
            }
        }
        function clearFieldErrors() {
            const u = document.getElementById('username');
            const p = document.getElementById('password');
            const uf = document.getElementById('usernameFeedback');
            const pf = document.getElementById('passwordFeedback');
            if (u) u.classList.remove('is-invalid');
            if (p) p.classList.remove('is-invalid');
            if (uf) {
                uf.classList.add('d-none');
                uf.querySelector('span').textContent = '';
            }
            if (pf) {
                pf.classList.add('d-none');
                pf.querySelector('span').textContent = '';
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username')?.addEventListener('input', function() {
                this.classList.remove('is-invalid');
                const uf = document.getElementById('usernameFeedback');
                if (uf) uf.classList.add('d-none');
                hideOtpAlert();
            });
            document.getElementById('password')?.addEventListener('input', function() {
                this.classList.remove('is-invalid');
                const pf = document.getElementById('passwordFeedback');
                if (pf) pf.classList.add('d-none');
                hideOtpAlert();
            });
        });
        async function handlePasswordLogin(e) {
            if (e) e.preventDefault();
            hideOtpAlert();
            clearFieldErrors();
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            const username = (usernameInput?.value || '').trim();
            const password = passwordInput?.value || '';
            const csrf = document.getElementById('loginCsrfToken')?.value || '';
            const btn = document.getElementById('btnPasswordSignIn');
            if (!username) {
                if (usernameInput) usernameInput.classList.add('is-invalid');
                const uf = document.getElementById('usernameFeedback');
                if (uf) {
                    uf.querySelector('span').textContent = 'Please enter your username or email.';
                    uf.classList.remove('d-none');
                }
                showOtpAlert('Please enter your username or email address.');
                usernameInput?.focus();
                return;
            }
            if (!password) {
                if (passwordInput) passwordInput.classList.add('is-invalid');
                const pf = document.getElementById('passwordFeedback');
                if (pf) {
                    pf.querySelector('span').textContent = 'Please enter your password.';
                    pf.classList.remove('d-none');
                }
                showOtpAlert('Please enter your password.');
                passwordInput?.focus();
                return;
            }
            setButtonLoading(btn, true);
            try {
                const res = await fetch('ajax/auth_otp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'login_password',
                        username: username,
                        password: password,
                        csrf_token: csrf
                    })
                });
                const text = await res.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (jsonErr) {
                    console.error("JSON parse error:", text);
                    showOtpAlert('Server error occurred during login. Please try again.');
                    return;
                }
                if (data.success) {
                    if (data.redirect && !data.requires_otp) {
                        window.location.href = data.redirect;
                        return;
                    }
                    showOtpAlert(data.message || 'Password verified! Verification code has been sent to your email.', 'success');
                    document.getElementById('otpTargetEmailText').textContent = data.email || username;
                    document.getElementById('postOtpEmail').value = data.email || username;
                    document.getElementById('stepPassword').style.display = 'none';
                    document.getElementById('stepOtpVerify').style.display = 'block';
                    startOtpCountdown(data.cooldown || 60);
                    setTimeout(() => {
                        const firstInput = document.querySelector('.code-digit');
                        if (firstInput) firstInput.focus();
                    }, 100);
                } else {
                    if (data.error_type === 'user_not_found') {
                        if (usernameInput) {
                            usernameInput.classList.add('is-invalid');
                            usernameInput.focus();
                        }
                        const uf = document.getElementById('usernameFeedback');
                        if (uf) {
                            uf.querySelector('span').textContent = 'Wrong email or username.';
                            uf.classList.remove('d-none');
                        }
                    } else if (data.error_type === 'wrong_password') {
                        if (passwordInput) {
                            passwordInput.classList.add('is-invalid');
                            passwordInput.value = '';
                            passwordInput.focus();
                        }
                        const pf = document.getElementById('passwordFeedback');
                        if (pf) {
                            pf.querySelector('span').textContent = 'Wrong password.';
                            pf.classList.remove('d-none');
                        }
                    } else {
                        if (usernameInput) usernameInput.classList.add('is-invalid');
                        if (passwordInput) passwordInput.classList.add('is-invalid');
                    }
                    showOtpAlert(data.message || 'Invalid username or password.');
                }
            } catch (err) {
                console.error("Login fetch error:", err);
                showOtpAlert('An unexpected error occurred. Please try again.');
            } finally {
                setButtonLoading(btn, false);
            }
        }
        (function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('step') === 'verify') {
                const email = urlParams.get('email') || '';
                if (email) {
                    document.getElementById('otpTargetEmailText').textContent = email;
                    document.getElementById('postOtpEmail').value = email;
                    document.getElementById('stepPassword').style.display = 'none';
                    document.getElementById('stepOtpVerify').style.display = 'block';
                    startOtpCountdown(60);
                }
            }
        })();
        function backToPassword() {
            hideOtpAlert();
            if (resendTimer) { clearInterval(resendTimer); resendTimer = null; }
            document.getElementById('stepPassword').style.display = 'block';
            document.getElementById('stepOtpVerify').style.display = 'none';
            document.getElementById('password').value = '';
            document.getElementById('password')?.focus();
        }
        function startOtpCountdown(seconds = 60) {
            cooldownSeconds = seconds;
            const resendBtn = document.getElementById('btnResendOtp');
            if (!resendBtn) return;
            resendBtn.disabled = true;
            resendBtn.innerHTML = `Resend code in <span id="otpCountdown" class="fw-bold">${cooldownSeconds}</span>s`;
            if (resendTimer) clearInterval(resendTimer);
            resendTimer = setInterval(() => {
                cooldownSeconds--;
                const span = document.getElementById('otpCountdown');
                if (span) span.textContent = cooldownSeconds;
                if (cooldownSeconds <= 0) {
                    clearInterval(resendTimer);
                    resendTimer = null;
                    resendBtn.disabled = false;
                    resendBtn.innerHTML = '<i class="fas fa-redo me-1"></i> Resend Verification Code';
                }
            }, 1000);
        }
        function setButtonLoading(btn, isLoading) {
            if (!btn) return;
            const textEl = btn.querySelector('.btn-text');
            const spinEl = btn.querySelector('.spinner-border');
            btn.disabled = isLoading;
            if (textEl && spinEl) {
                textEl.classList.toggle('d-none', isLoading);
                spinEl.classList.toggle('d-none', !isLoading);
            }
        }
        async function handleResendOtp() {
            hideOtpAlert();
            const email = document.getElementById('postOtpEmail')?.value || document.getElementById('otpEmail')?.value || '';
            const csrf = document.getElementById('otpCsrfToken')?.value || '';
            const resendBtn = document.getElementById('btnResendOtp');
            if (!email) return;
            resendBtn.disabled = true;
            resendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending...';
            try {
                const res = await fetch('ajax/auth_otp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'resend_otp', email, csrf_token: csrf })
                });
                const data = await res.json();
                if (data.success) {
                    showOtpAlert('A new verification code has been sent to your email.', 'success');
                    startOtpCountdown(data.cooldown || 60);
                } else {
                    showOtpAlert(data.message || 'Failed to resend code.');
                    resendBtn.disabled = false;
                    resendBtn.innerHTML = '<i class="fas fa-redo me-1"></i> Resend Verification Code';
                }
            } catch (err) {
                showOtpAlert('Error resending OTP. Please try again.');
                resendBtn.disabled = false;
                resendBtn.innerHTML = '<i class="fas fa-redo me-1"></i> Resend Verification Code';
            }
        }
        async function handleVerifyOtp(e) {
            if (e) e.preventDefault();
            hideOtpAlert();
            const email = document.getElementById('postOtpEmail')?.value || '';
            const csrf = document.getElementById('otpCsrfToken')?.value || '';
            const btn = document.getElementById('btnVerifyOtp');
            const digits = Array.from(document.querySelectorAll('.code-digit')).map(i => i.value.trim());
            const code = digits.join('');
            if (code.length !== 6 || !/^\d{6}$/.test(code)) {
                showOtpAlert('Please enter the complete 6-digit verification code.');
                return;
            }
            setButtonLoading(btn, true);
            try {
                const res = await fetch('ajax/auth_otp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'verify_otp', email, code, csrf_token: csrf })
                });
                const data = await res.json();
                if (data.success) {
                    showOtpAlert('Login successful! Redirecting...', 'success');
                    setTimeout(() => { window.location.href = data.redirect || 'dashboard.php'; }, 400);
                } else {
                    showOtpAlert(data.message || 'Invalid verification code.');
                    document.querySelectorAll('.code-digit').forEach(i => i.value = '');
                    document.querySelector('.code-digit')?.focus();
                    setButtonLoading(btn, false);
                }
            } catch (err) {
                showOtpAlert('An unexpected network error occurred. Please try again.');
                setButtonLoading(btn, false);
            }
        }
        (function() {
            const container = document.getElementById('codeInputsContainer');
            if (!container) return;
            const inputs = container.querySelectorAll('.code-digit');
            inputs.forEach((input, index) => {
                input.addEventListener('input', function() {
                    const val = this.value.replace(/\D/g, '');
                    this.value = val ? val[0] : '';
                    if (this.value && index < inputs.length - 1) inputs[index + 1].focus();
                    const allFilled = Array.from(inputs).every(i => i.value.trim().length === 1);
                    if (allFilled && index === inputs.length - 1) handleVerifyOtp();
                });
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !this.value && index > 0) inputs[index - 1].focus();
                });
                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const clean = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
                    if (clean.length > 0) {
                        clean.split('').forEach((d, i) => { if (inputs[i]) inputs[i].value = d; });
                        inputs[Math.min(clean.length, inputs.length - 1)].focus();
                        if (clean.length === 6) handleVerifyOtp();
                    }
                });
            });
        })();
        <?php if ($isVerifyStep): ?>
        window.addEventListener('DOMContentLoaded', function() {
            startOtpCountdown(60);
            const firstDigit = document.querySelector('.code-digit');
            if (firstDigit) {
                setTimeout(() => firstDigit.focus(), 150);
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>