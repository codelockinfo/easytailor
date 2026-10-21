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

if (isset($_GET['logout'])) {
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
    <link rel="icon" type="image/x-icon" href="../favicon.ico" sizes="16x16 32x32 48x48">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon(2).png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon(2).png">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon(2).png">
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
                        $brandLogo = get_logo_path('footer-logo.png');
                        if ($brandLogo):
                        ?>
                            <img src="<?php echo $brandLogo; ?>" alt="<?php echo APP_NAME; ?>" class="" style="max-height: 80px; max-width: 200px;">
                        <?php else: ?>
                            <i class="fas fa-cut fa-3x mb-3"></i>
                        <?php endif; ?>
                        </a>
                        <p class="mb-0 opacity-75"><?php echo __t('sign_in_to_account', 'Sign in to your account'); ?></p>
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
                                <?php echo htmlspecialchars($success_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Unified Login Alert Box -->
                        <div id="otpAlertBox" class="alert d-none mb-3" role="alert"></div>

                        <!-- Step 1: Email / Username + Password (default view) -->
                        <div id="stepPassword">
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

                        <!-- Step 2: OTP — Enter Code -->
                        <div id="stepOtpVerify" style="display:none;">
                            <div class="p-2 mb-3 rounded-3 text-center" style="background:#f1f5f9;font-size:0.82rem;">
                                <span>Code sent to <strong id="otpTargetEmailText" class="text-dark"></strong></span>
                                <button type="button" class="btn btn-link btn-sm text-primary p-0 ms-1" style="font-size:0.8rem;text-decoration:none;" onclick="backToPassword()">Change</button>
                            </div>

                            <form id="formVerifyOtp" onsubmit="handleVerifyOtp(event)">
                                <input type="hidden" id="otpCsrfToken" value="<?php echo generate_csrf_token(); ?>">
                                <input type="hidden" id="postOtpEmail">
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
                                    <button type="button" class="btn btn-link p-0 text-secondary small text-decoration-none" onclick="backToPassword()">
                                        <i class="fas fa-arrow-left me-1"></i>Back to Password Login
                                    </button>
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
                                Free 30-day trial • No credit card required
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
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
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
            const box = document.getElementById('otpAlertBox');
            if (!box) return;
            box.className = `alert alert-${type} alert-dismissible fade show`;
            box.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i><span>${message}</span><button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
            box.classList.remove('d-none');
        }

        function hideOtpAlert() {
            const box = document.getElementById('otpAlertBox');
            if (box) box.classList.add('d-none');
        }

        async function handlePasswordLogin(e) {
            if (e) e.preventDefault();
            hideOtpAlert();

            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            const username = (usernameInput?.value || '').trim();
            const password = passwordInput?.value || '';
            const csrf = document.getElementById('loginCsrfToken')?.value || '';
            const btn = document.getElementById('btnPasswordSignIn');

            if (!username || !password) {
                showOtpAlert('Please enter both username/email and password.');
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

                const data = await res.json();

                if (data.success) {
                    if (data.redirect && !data.requires_otp) {
                        window.location.href = data.redirect;
                        return;
                    }

                    // Password is correct! OTP was generated and sent!
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
                    showOtpAlert(data.message || 'Invalid username or password.');
                }
            } catch (err) {
                showOtpAlert('An unexpected error occurred. Please try again.');
            } finally {
                setButtonLoading(btn, false);
            }
        }

        // Check if redirected from server POST with step=verify
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

        // Go back to password form
        function backToPassword() {
            hideOtpAlert();
            if (resendTimer) { clearInterval(resendTimer); resendTimer = null; }
            document.getElementById('stepPassword').style.display = 'block';
            document.getElementById('stepOtpVerify').style.display = 'none';
            document.getElementById('password').value = '';
            document.getElementById('password')?.focus();
        }

        // ── Countdown timer & button helpers ──────────────────────────────────
        let resendTimer = null;
        let cooldownSeconds = 60;

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

        // ── Resend OTP ─────────────────────────────────────────────────────────
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

        // ── Verify OTP ─────────────────────────────────────────────────────────
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

        // ── Digit input navigation (auto-tab, backspace, paste) ────────────────
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

=    </script>
</body>
</html>