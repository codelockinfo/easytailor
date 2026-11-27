<?php
require_once '../config/config.php';
require_once '../helpers/MailService.php';
if (is_logged_in()) {
    smart_redirect('dashboard.php');
}

$error_message = '';
$success_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $ownerName = sanitize_input($_POST['owner_name'] ?? '');
        $businessEmail = sanitize_input($_POST['business_email'] ?? '');
        $rawBusinessPhone = sanitize_input($_POST['business_phone'] ?? '');
        $phoneDigits = preg_replace('/\D/', '', $rawBusinessPhone);
        if (strlen($phoneDigits) > 10 && strpos($phoneDigits, '91') === 0) {
            $phoneDigits = substr($phoneDigits, 2);
        }
        $businessPhone = strlen($phoneDigits) === 10 ? ('+91' . $phoneDigits) : $rawBusinessPhone;
        $companyName = $ownerName . "'s Tailor Shop";
        $username = $businessEmail;
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        if (empty($ownerName) || empty($businessEmail) || empty($businessPhone) || empty($password)) {
            $_SESSION['reg_error'] = 'All required fields must be filled';
            header('Location: register.php');
            exit;
        }
        
        if ($password !== $confirmPassword) {
            $_SESSION['reg_error'] = 'Passwords do not match';
            header('Location: register.php');
            exit;
        }
        
        if (function_exists('validate_password_strength')) {
            $pwdCheck = validate_password_strength($password);
            if (!$pwdCheck['valid']) {
                $_SESSION['reg_error'] = $pwdCheck['message'];
                $_SESSION['reg_form_data'] = $_POST;
                header('Location: register.php');
                exit;
            }
        } elseif (strlen($password) < 6) {
            $_SESSION['reg_error'] = 'Password must be at least 6 characters';
            $_SESSION['reg_form_data'] = $_POST;
            header('Location: register.php');
            exit;
        }
        
        require_once '../models/Company.php';
        require_once '../models/User.php';
        
        $companyModel = new Company();
        $userModel = new User();
        $trimmedOwnerName = trim($ownerName);
        if ($trimmedOwnerName === '' || !preg_match('/^[\p{L} ]+$/u', $trimmedOwnerName)) {
            $_SESSION['reg_error'] = 'Owner name must contain only alphabets and spaces';
            $_SESSION['reg_form_data'] = $_POST;
            header('Location: register.php');
            exit;
        }
        $strictEmailPattern = '/^[A-Za-z0-9](?:[A-Za-z0-9._+-]*[A-Za-z0-9])?@[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?)*\.[A-Za-z]{2,}$/';
        if (!filter_var($businessEmail, FILTER_VALIDATE_EMAIL) || strpos($businessEmail, '..') !== false || !preg_match($strictEmailPattern, $businessEmail)) {
            $_SESSION['reg_error'] = 'Please enter a valid email address';
            $_SESSION['reg_form_data'] = $_POST;
            header('Location: register.php');
            exit;
        }
        if ($companyModel->emailExists($businessEmail)) {
            $_SESSION['reg_error'] = 'This email is already registered. Please use a different email or <a href="login.php">login here</a>.';
            $_SESSION['reg_form_data'] = $_POST;
            header('Location: register.php');
            exit;
        }
        if ($userModel->usernameExists($username)) {
            $_SESSION['reg_error'] = 'This email is already registered as a user. Please use a different email or <a href="login.php">login here</a>.';
            $_SESSION['reg_form_data'] = $_POST;
            header('Location: register.php');
            exit;
        }
        
        try {
            $database = new Database();
            $db = $database->getConnection();
            $db->beginTransaction();
            $cameFromOffer = false;
            $subscriptionPlan = 'free';
            $subscriptionExpiry = null;
            
            if (isset($_COOKIE['promo_offer_source']) && $_COOKIE['promo_offer_source'] === 'promo_popup') {
                $cameFromOffer = true;
                $subscriptionPlan = 'premium';
                $subscriptionExpiry = date('Y-m-d', strtotime('+1 year'));
            }
            $companyData = [
                'company_name' => $companyName,
                'owner_name' => $ownerName,
                'business_email' => $businessEmail,
                'business_phone' => $businessPhone,
                'business_address' => sanitize_input($_POST['business_address'] ?? ''),
                'city' => sanitize_input($_POST['city'] ?? ''),
                'state' => sanitize_input($_POST['state'] ?? ''),
                'country' => 'India',
                'postal_code' => sanitize_input($_POST['postal_code'] ?? ''),
                'website' => sanitize_input($_POST['website'] ?? ''),
                'currency' => 'INR',
                'timezone' => 'Asia/Kolkata',
                'status' => 'active',
                'subscription_plan' => $subscriptionPlan,
                'subscription_expiry' => $subscriptionExpiry,
                'show_on_listing' => 0
            ];
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/logos/';
                $fileName = time() . '_' . basename($_FILES['logo']['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath)) {
                    $companyData['logo'] = $targetPath;
                }
            }
            $companyData['show_on_listing'] = Company::isProfileComplete($companyData) ? 1 : 0;
            
            $companyId = $companyModel->createCompany($companyData);
            
            if (!$companyId) {
                throw new Exception('Failed to create company');
            }
            
            // Populate default cloth types with measurement chart images (farmas)
            require_once '../helpers/DefaultDataHelper.php';
            DefaultDataHelper::createDefaultClothTypes($companyId);
            
            // Create owner user account
            $userData = [
                'company_id' => $companyId,
                'username' => $username,
                'email' => $businessEmail,
                'password' => $password,
                'full_name' => $ownerName,
                'role' => 'admin',
                'phone' => $businessPhone,
                'address' => sanitize_input($_POST['business_address'] ?? ''),
                'status' => 'active'
            ];
            
            $userId = $userModel->createUser($userData);
            
            if (!$userId) {
                throw new Exception('Failed to create user account');
            }
            
            // Update company with user_id (owner user ID)
            $updateResult = $companyModel->update($companyId, ['user_id' => $userId]);
            
            if (!$updateResult) {
                throw new Exception('Failed to link user to company');
            }
            
            // Commit transaction
            if (isset($db) && $db->inTransaction()) {
                $db->commit();
            }

            // Clear promo offer source cookie if applicable
            if ($cameFromOffer) {
                setcookie('promo_offer_source', '', time() - 3600, '/');
            }

            // Generate and dispatch Email OTP for immediate verification & login
            require_once '../controllers/AuthController.php';
            $authController = new AuthController();
            $otpCustomSubject = 'Your Registration Verification Code - ' . APP_NAME;
            $otpCustomMessage = 'Thank you for registering with ' . APP_NAME . '! Please use the following 6-digit verification code to verify your email and sign in directly to your dashboard:';
            $otpResult = $authController->requestLoginOtp(
                $businessEmail,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $otpCustomSubject,
                $otpCustomMessage
            );

            // Send Slack notification (non-blocking)
            try {
                require_once '../helpers/SlackService.php';
                $slackService = new SlackService();
                if ($slackService->isEnabled()) {
                    $slackService->sendNewRegistrationNotification([
                        'company_name' => $companyName,
                        'owner_name'   => $ownerName,
                        'email'        => $businessEmail,
                        'phone'        => $businessPhone,
                        'username'     => $username,
                        'city'         => sanitize_input($_POST['city'] ?? ''),
                        'state'        => sanitize_input($_POST['state'] ?? ''),
                        'plan'         => $subscriptionPlan,
                        'method'       => $cameFromOffer ? 'Special Offer Registration' : 'Web Registration'
                    ]);
                }
            } catch (Throwable $e) {
                error_log('Slack notification error on registration: ' . $e->getMessage());
            }

            // Track signup event for GA4
            require_once '../helpers/GA4Helper.php';
            $_SESSION['ga4_event'] = GA4Helper::trackSignup($userId, $companyId, $companyName, $ownerName);
            
            // Set success notice and redirect directly to OTP verification & direct login step
            $regSuccessNotice = 'Registration successful! A 6-digit verification code has been sent to ' . htmlspecialchars($businessEmail) . '. Please enter it below to access your dashboard.';
            if (!empty($otpResult['dev_otp'])) {
                $regSuccessNotice .= ' (Verification Code: ' . $otpResult['dev_otp'] . ')';
            }
            $_SESSION['login_success'] = $regSuccessNotice;
            smart_redirect('login.php?step=verify&email=' . urlencode($businessEmail) . '&registered=1');
            exit;
            smart_redirect('login.php');
            exit;
            
        } catch (Exception $e) {
            // Rollback on error
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            
            // Convert technical errors to user-friendly messages
            $errorMsg = $e->getMessage();
            
            if (strpos($errorMsg, 'Duplicate entry') !== false) {
                if (strpos($errorMsg, 'username') !== false) {
                    $_SESSION['reg_error'] = 'This username is already taken. Please choose a different username.';
                } elseif (strpos($errorMsg, 'business_email') !== false) {
                    $_SESSION['reg_error'] = 'This business email is already registered. <a href="login.php">Login here</a> if you already have an account.';
                } elseif (strpos($errorMsg, 'email') !== false) {
                    $_SESSION['reg_error'] = 'This email address is already in use. Please use a different email.';
                } else {
                    $_SESSION['reg_error'] = 'An account with these details already exists. Please check your information or <a href="login.php">login here</a>.';
                }
            } elseif (strpos($errorMsg, 'foreign key constraint') !== false) {
                $_SESSION['reg_error'] = 'Database configuration error. Please contact support.';
            } else {
                // Generic friendly error
                $_SESSION['reg_error'] = 'Registration failed. Please check your information and try again. If the problem persists, please contact support.';
            }
            
            $_SESSION['reg_form_data'] = $_POST;
            header('Location: register.php');
            exit;
        }
        
    } else {
        $_SESSION['reg_error'] = 'Invalid request';
        header('Location: register.php');
        exit;
    }
}

// Get messages from session
if (isset($_SESSION['reg_error'])) {
    $error_message = $_SESSION['reg_error'];
    unset($_SESSION['reg_error']);
}

if (isset($_SESSION['reg_success'])) {
    $success_message = $_SESSION['reg_success'];
    unset($_SESSION['reg_success']);
}

// Get form data from session (to repopulate form on error)
$formData = [];
if (isset($_SESSION['reg_form_data'])) {
    $formData = $_SESSION['reg_form_data'];
    unset($_SESSION['reg_form_data']);
}

require_once '../helpers/SEOHelper.php';

$baseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$canonicalUrl = $baseUrl . '/admin/register.php';

$seoOptions = [
    'title' => 'Register Your Tailor Shop - ' . (defined('APP_NAME') ? APP_NAME : 'Tailoring Management System'),
    'description' => 'Register your tailor shop and start managing your business digitally. Free registration with comprehensive features for managing customers, orders, invoices, and payments.',
    'keywords' => 'register tailor shop, tailor shop registration, tailor business registration, free tailor software, tailor shop management',
    'canonical' => $canonicalUrl
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
            justify-content: center;
            padding: 1rem 0;
        }
        .register-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: 100%;
        }
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px 16px 0 0;
            padding: 1rem 1.5rem;
            text-align: center;
        }
        .register-header h2 {
            font-size: 1.5rem;
            margin-bottom: 2px !important;
        }
        .register-header p {
            font-size: 0.85rem;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 8px 12px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .form-label {
            margin-bottom: 4px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            padding: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        .btn-register:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .section-title {
            color: #667eea;
            border-bottom: 2px solid #667eea;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .alert-info-compact {
            padding: 8px 12px;
            font-size: 0.8rem;
            border-radius: 8px;
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
        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 5px;
            background: #e9ecef;
            transition: all 0.3s ease;
        }
        .password-strength.weak { background: #dc3545; width: 33%; }
        .password-strength.medium { background: #ffc107; width: 66%; }
        .password-strength.strong { background: #28a745; width: 100%; }
        .password-requirements {
            font-size: 12px;
            color: #6c757d;
        }
        .requirement {
            padding: 2px 0;
            transition: color 0.2s ease;
        }
        .requirement.met {
            color: #28a745;
        }
        .requirement.met i {
            color: #28a745;
        }
        @media (max-width: 768px) {
            .register-header h2 {
                font-size: 1.25rem;
            }
            .register-header p {
                font-size: 0.8rem;
            }
            .btn-register {
                font-size: 0.9rem;
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card register-card border-0">
                    <div class="register-header">
                        <a href="../" class="text-decoration-none">
                        <?php
                        // Get brand logo using smart path detection
                        $brandLogo = get_logo_path('footer-logo.png');
                        if ($brandLogo):
                        ?>
                            <img src="<?php echo $brandLogo; ?>" alt="<?php echo APP_NAME; ?>" class="" style="max-height: 48px; max-width: 150px;">
                        <?php else: ?>
                            <i class="fas fa-cut fa-2x mb-2"></i>
                        <?php endif; ?>
                        </a>
                        <h2 class="mb-1">Register Your Tailor Shop</h2>
                        <p class="mb-0 opacity-75">Start managing your tailoring business today!</p>
                    </div>
                    <div class="card-body p-3 py-3">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo $error_message; // Already sanitized or contains safe HTML links ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo htmlspecialchars($success_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Sign up with Google -->
                        <div class="mb-3">
                            <a href="google-login.php" class="btn btn-google w-100" id="btnGoogleRegister">
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

                        <form method="POST" enctype="multipart/form-data" id="registerForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            
                            <div class="row g-2">
                                <div class="col-md-6 mb-2">
                                    <label for="owner_name" class="form-label">
                                        Owner Name *
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="owner_name" 
                                           name="owner_name" 
                                           placeholder="e.g., John Smith"
                                           value="<?php echo htmlspecialchars($formData['owner_name'] ?? ''); ?>"
                                           required>
                                    <div class="invalid-feedback">Owner name must contain only alphabets and spaces.</div>
                                </div>
                                
                                <div class="col-md-6 mb-2">
                                    <label for="business_email" class="form-label">
                                        Email *
                                    </label>
                                    <input type="email" 
                                           class="form-control" 
                                           id="business_email" 
                                           name="business_email" 
                                           placeholder="contact@yourtailor.com"
                                           value="<?php echo htmlspecialchars($formData['business_email'] ?? ''); ?>"
                                           required>
                                    <div class="invalid-feedback">Please enter a valid email address.</div>
                                </div>
                            </div>
                            
                            <div class="mb-2">
                                <label for="business_phone" class="form-label">
                                    Phone Number *
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">+91</span>
                                    <input type="tel" 
                                           class="form-control" 
                                           id="business_phone" 
                                           name="business_phone" 
                                           placeholder="10-digit mobile number"
                                           pattern="[0-9]{10}"
                                           maxlength="10"
                                           value="<?php 
                                               $phone = $formData['business_phone'] ?? '';
                                               // Remove +91 prefix if present for display
                                               if (strpos($phone, '+91') === 0) {
                                                   $phone = substr($phone, 3);
                                               }
                                               echo htmlspecialchars($phone); 
                                           ?>"
                                           required>
                                </div>
                                <small class="text-muted">Enter 10-digit mobile number (digits only)</small>
                            </div>
                            
                            <div class="row g-2">
                                <div class="col-md-6 mb-2">
                                    <label for="password" class="form-label">
                                        Password *
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" 
                                               class="form-control" 
                                               id="password" 
                                               name="password" 
                                               minlength="6"
                                               placeholder="At least 6 characters"
                                               required>
                                        <button class="btn btn-outline-secondary" 
                                                type="button" 
                                                onclick="togglePasswordField('password')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="password-strength" id="regStrengthBar"></div>
                                    <div class="password-requirements mt-2">
                                        <div class="requirement" id="req-length">
                                            <i class="fas fa-circle"></i> At least 6 characters
                                        </div>
                                        <div class="requirement" id="req-uppercase">
                                            <i class="fas fa-circle"></i> Contains uppercase letter
                                        </div>
                                        <div class="requirement" id="req-lowercase">
                                            <i class="fas fa-circle"></i> Contains lowercase letter
                                        </div>
                                        <div class="requirement" id="req-number">
                                            <i class="fas fa-circle"></i> Contains number
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label for="confirm_password" class="form-label">
                                        Confirm Password *
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" 
                                               class="form-control" 
                                               id="confirm_password" 
                                               name="confirm_password" 
                                               minlength="6"
                                               placeholder="Re-enter password"
                                               required>
                                        <button class="btn btn-outline-secondary" 
                                                type="button" 
                                                onclick="togglePasswordField('confirm_password')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <small class="d-block mt-2" id="regMatchMessage" style="font-size: 12px;"></small>
                                </div>
                            </div>
                            
                            <div class="alert alert-info alert-info-compact mb-2">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>What you get:</strong>
                                <ul class="mb-0 mt-1">
                                    <li>Free plan available</li>
                                    <li>Manage unlimited customers</li>
                                    <li>Track orders and measurements</li>
                                    <li>Generate invoices and reports</li>
                                    <li>Add staff and tailors</li>
                                </ul>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="agree_terms" required>
                                <label class="form-check-label" for="agree_terms" style="font-size: 0.85rem;">
                                    I agree to the <a href="../terms-of-service">Terms of Service</a> and <a href="../privacy-policy">Privacy Policy</a>
                                </label>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-register btn-lg py-2">
                                    <i class="fas fa-user-plus me-2"></i>
                                    Register My Tailor Shop
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-3">
                            <p class="text-muted mb-0" style="font-size: 0.85rem;">
                                Already have an account? 
                                <a href="login.php" class="text-decoration-none fw-semibold">
                                    Sign In
                                </a>
                            </p>
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

    <!-- Validation Alert Modal -->
    <div class="modal fade" id="registerAlertModal" tabindex="-1" aria-labelledby="registerAlertTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
            <div class="modal-content border-0 shadow-lg text-center p-3 p-sm-4" style="border-radius: 20px;">
                <div class="modal-body p-0">
                    <div id="registerAlertIconContainer" class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 58px; height: 58px; background: #fee2e2; color: #dc2626;">
                        <i id="registerAlertIcon" class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                    <h5 id="registerAlertTitle" class="fw-bold mb-2 text-dark" style="font-size: 1.12rem;">Notice</h5>
                    <p id="registerAlertMessage" class="text-secondary small mb-4 px-2" style="line-height: 1.6; font-size: 0.9rem;"></p>
                    <div class="d-flex justify-content-center">
                        <button type="button" class="btn btn-primary btn-register rounded-pill px-5 text-white" data-bs-dismiss="modal">OK</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview logo upload
        function previewLogo(input) {
            const preview = document.getElementById('logoPreview');
            const previewImg = document.getElementById('logoPreviewImg');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                };
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }
        
        // Toggle password visibility
        function togglePasswordField(fieldId) {
            const field = document.getElementById(fieldId);
            const button = event.currentTarget;
            const icon = button.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Normalize phone input to 10 local digits without duplicating the +91 prefix
        function normalizePhoneDigitsInline(raw, countryPrefix = '+91') {
            let val = (raw || '').toString().trim();
            const prefixDigits = countryPrefix.replace(/[^0-9]/g, '');
            while (val.startsWith(countryPrefix)) {
                val = val.slice(countryPrefix.length).trim();
            }
            let digits = val.replace(/[^0-9]/g, '');
            if (prefixDigits && digits.length > 10 && digits.startsWith(prefixDigits)) {
                digits = digits.slice(prefixDigits.length);
            }
            return digits.slice(0, 10);
        }

        // Phone Number Validation (Inline version for register page)
        function setupPhoneValidationInline(phoneInputId, countryPrefix = '+91') {
            const phoneInput = document.getElementById(phoneInputId);
            if (!phoneInput) return;

            const syncDigits = (raw) => {
                const cleaned = normalizePhoneDigitsInline(raw, countryPrefix);
                if (phoneInput.value !== cleaned) {
                    phoneInput.value = cleaned;
                }
                return cleaned;
            };

            syncDigits(phoneInput.value);
            phoneInput.maxLength = 10;
            phoneInput.setAttribute('pattern', '[0-9]{10}');

            phoneInput.addEventListener('input', function() {
                syncDigits(this.value);
            });

            phoneInput.addEventListener('change', function() {
                syncDigits(this.value);
            });

            phoneInput.addEventListener('keypress', function(e) {
                const char = String.fromCharCode(e.which);
                if (!/[0-9]/.test(char)) {
                    e.preventDefault();
                }
            });

            phoneInput.addEventListener('paste', function(e) {
                e.preventDefault();
                const pastedText = (e.clipboardData || window.clipboardData).getData('text');
                syncDigits(pastedText);
            });

            phoneInput.addEventListener('blur', function() {
                const value = syncDigits(this.value);
                if (value.length === 10) {
                    this.setAttribute('data-phone-full', countryPrefix + value);
                }
            });

            return phoneInput;
        }

        function validatePhoneNumberInline(phoneInputId) {
            const phoneInput = document.getElementById(phoneInputId);
            if (!phoneInput) return false;
            const value = normalizePhoneDigitsInline(phoneInput.value, '+91');
            phoneInput.value = value;
            if (value.length !== 10) {
                phoneInput.classList.add('is-invalid');
                return false;
            }
            phoneInput.classList.remove('is-invalid');
            return true;
        }

        const STRICT_EMAIL_REGEX = /^[A-Za-z0-9](?:[A-Za-z0-9._+-]*[A-Za-z0-9])?@[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?)*\.[A-Za-z]{2,}$/;

        function isValidEmailInline(value) {
            const email = (value || '').trim();
            return email.length > 0 && !email.includes('..') && STRICT_EMAIL_REGEX.test(email);
        }

        function validateEmailInline(emailInputId) {
            const emailInput = document.getElementById(emailInputId);
            if (!emailInput) return true;
            const trimmed = emailInput.value.trim();
            emailInput.value = trimmed;
            if (!isValidEmailInline(trimmed)) {
                emailInput.classList.add('is-invalid');
                return false;
            }
            emailInput.classList.remove('is-invalid');
            return true;
        }

        function setupEmailValidationInline(emailInputId) {
            const emailInput = document.getElementById(emailInputId);
            if (!emailInput) return;

            emailInput.addEventListener('blur', function() {
                if (this.value.trim() !== '') {
                    validateEmailInline(emailInputId);
                }
            });

            emailInput.addEventListener('input', function() {
                if (this.classList.contains('is-invalid') && isValidEmailInline(this.value)) {
                    this.classList.remove('is-invalid');
                }
            });
        }

        function sanitizeOwnerNameInline(value) {
            return (value || '').replace(/[^\p{L} ]/gu, '');
        }

        function isValidOwnerNameInline(value) {
            const name = (value || '').trim();
            return name.length > 0 && /^[\p{L} ]+$/u.test(name);
        }

        function validateOwnerNameInline(nameInputId) {
            const nameInput = document.getElementById(nameInputId);
            if (!nameInput) return true;
            const cleaned = sanitizeOwnerNameInline(nameInput.value).trim();
            nameInput.value = cleaned;
            if (!isValidOwnerNameInline(cleaned)) {
                nameInput.classList.add('is-invalid');
                return false;
            }
            nameInput.classList.remove('is-invalid');
            return true;
        }

        function setupOwnerNameValidationInline(nameInputId) {
            const nameInput = document.getElementById(nameInputId);
            if (!nameInput) return;

            const syncAlphaOnly = () => {
                const cleaned = sanitizeOwnerNameInline(nameInput.value);
                if (nameInput.value !== cleaned) {
                    nameInput.value = cleaned;
                }
            };

            syncAlphaOnly();

            nameInput.addEventListener('keypress', function(e) {
                if (e.key && e.key.length === 1 && !/^[\p{L} ]$/u.test(e.key)) {
                    e.preventDefault();
                }
            });

            nameInput.addEventListener('input', function() {
                syncAlphaOnly();
                if (this.classList.contains('is-invalid') && isValidOwnerNameInline(this.value)) {
                    this.classList.remove('is-invalid');
                }
            });

            nameInput.addEventListener('paste', function(e) {
                e.preventDefault();
                const pastedText = (e.clipboardData || window.clipboardData).getData('text');
                const cleaned = sanitizeOwnerNameInline(pastedText);
                const start = this.selectionStart ?? this.value.length;
                const end = this.selectionEnd ?? this.value.length;
                this.value = sanitizeOwnerNameInline(this.value.slice(0, start) + cleaned + this.value.slice(end));
            });

            nameInput.addEventListener('blur', function() {
                if (this.value.trim() !== '') {
                    validateOwnerNameInline(nameInputId);
                }
            });
        }

        // Initialize owner name, email & phone validation on page load
        document.addEventListener('DOMContentLoaded', function() {
            setupOwnerNameValidationInline('owner_name');
            setupEmailValidationInline('business_email');
            setupPhoneValidationInline('business_phone', '+91');
        });
        // Real-time password requirements validation
        const regPasswordInput = document.getElementById('password');
        const regConfirmPasswordInput = document.getElementById('confirm_password');
        const regStrengthBar = document.getElementById('regStrengthBar');
        const regMatchMsg = document.getElementById('regMatchMessage');

        function updateRegRequirement(id, met) {
            const el = document.getElementById(id);
            if (!el) return;
            const icon = el.querySelector('i');
            if (met) {
                el.classList.add('met');
                if (icon) {
                    icon.classList.remove('fa-circle');
                    icon.classList.add('fa-check-circle');
                }
            } else {
                el.classList.remove('met');
                if (icon) {
                    icon.classList.remove('fa-check-circle');
                    icon.classList.add('fa-circle');
                }
            }
        }

        function checkRegPasswordMatch() {
            if (!regPasswordInput || !regConfirmPasswordInput || !regMatchMsg) return;
            const p1 = regPasswordInput.value;
            const p2 = regConfirmPasswordInput.value;
            if (p2.length > 0) {
                if (p1 === p2) {
                    regMatchMsg.textContent = '✓ Passwords match';
                    regMatchMsg.className = 'd-block mt-2 text-success';
                    regConfirmPasswordInput.classList.remove('is-invalid');
                    regConfirmPasswordInput.classList.add('is-valid');
                } else {
                    regMatchMsg.textContent = '✗ Passwords do not match';
                    regMatchMsg.className = 'd-block mt-2 text-danger';
                    regConfirmPasswordInput.classList.remove('is-valid');
                    regConfirmPasswordInput.classList.add('is-invalid');
                }
            } else {
                regMatchMsg.textContent = '';
                regConfirmPasswordInput.classList.remove('is-valid', 'is-invalid');
            }
        }

        if (regPasswordInput) {
            regPasswordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;

                const hasLength = password.length >= 6;
                const hasUppercase = /[A-Z]/.test(password);
                const hasLowercase = /[a-z]/.test(password);
                const hasNumber = /[0-9]/.test(password);

                updateRegRequirement('req-length', hasLength);
                updateRegRequirement('req-uppercase', hasUppercase);
                updateRegRequirement('req-lowercase', hasLowercase);
                updateRegRequirement('req-number', hasNumber);

                if (hasLength) strength++;
                if (hasUppercase) strength++;
                if (hasLowercase) strength++;
                if (hasNumber) strength++;

                if (regStrengthBar) {
                    regStrengthBar.className = 'password-strength';
                    if (password.length > 0) {
                        if (strength <= 1) {
                            regStrengthBar.classList.add('weak');
                        } else if (strength <= 3) {
                            regStrengthBar.classList.add('medium');
                        } else {
                            regStrengthBar.classList.add('strong');
                        }
                    }
                }

                checkRegPasswordMatch();
            });
        }

        if (regConfirmPasswordInput) {
            regConfirmPasswordInput.addEventListener('input', checkRegPasswordMatch);
        }

        const REGISTER_ALERT_STYLES = {
            danger:  { bg: '#fee2e2', color: '#dc2626', icon: 'fas fa-times-circle fa-2x' },
            warning: { bg: '#fef3c7', color: '#d97706', icon: 'fas fa-exclamation-triangle fa-2x' },
            success: { bg: '#dcfce7', color: '#16a34a', icon: 'fas fa-check-circle fa-2x' },
            info:    { bg: '#e0e7ff', color: '#4338ca', icon: 'fas fa-info-circle fa-2x' }
        };

        function showRegisterAlert(message, title = 'Notice', type = 'warning', focusEl = null) {
            const modalEl = document.getElementById('registerAlertModal');
            if (!modalEl || !window.bootstrap || !bootstrap.Modal) {
                alert(message);
                return;
            }
            const iconContainer = document.getElementById('registerAlertIconContainer');
            const iconEl = document.getElementById('registerAlertIcon');
            const titleEl = document.getElementById('registerAlertTitle');
            const msgEl = document.getElementById('registerAlertMessage');

            if (titleEl) titleEl.textContent = title;
            if (msgEl) msgEl.textContent = message;

            const style = REGISTER_ALERT_STYLES[type] || REGISTER_ALERT_STYLES.warning;
            if (iconContainer && iconEl) {
                iconContainer.style.background = style.bg;
                iconContainer.style.color = style.color;
                iconEl.className = style.icon;
            }

            if (focusEl && typeof focusEl.focus === 'function') {
                modalEl.addEventListener('hidden.bs.modal', function() {
                    focusEl.focus();
                }, { once: true });
            }

            const bsModal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            bsModal.show();
        }

        // Owner name, email, phone, and password match validation (keep visible phone input free of +91 prefix)
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const ownerName = document.getElementById('owner_name');
            if (ownerName && !validateOwnerNameInline('owner_name')) {
                e.preventDefault();
                showRegisterAlert('Owner name must contain only alphabets and spaces. Numbers and special characters are not allowed.', 'Invalid Owner Name', 'warning', ownerName);
                return false;
            }

            const businessEmail = document.getElementById('business_email');
            if (businessEmail && !validateEmailInline('business_email')) {
                e.preventDefault();
                showRegisterAlert('Please enter a valid email address (e.g., name@example.com). Special characters like # or $ are not allowed.', 'Invalid Email Address', 'warning', businessEmail);
                return false;
            }

            const businessPhone = document.getElementById('business_phone');
            if (businessPhone && businessPhone.value.trim()) {
                if (!validatePhoneNumberInline('business_phone')) {
                    e.preventDefault();
                    showRegisterAlert('Please enter a valid 10-digit phone number.', 'Invalid Phone Number', 'warning', businessPhone);
                    return false;
                }
            }

            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            if (passwordInput.value !== confirmPasswordInput.value) {
                e.preventDefault();
                showRegisterAlert('Passwords do not match!', 'Password Mismatch', 'danger', confirmPasswordInput);
                return false;
            }

            const passwordVal = passwordInput.value;
            const hasLength = passwordVal.length >= 6;
            const hasUppercase = /[A-Z]/.test(passwordVal);
            const hasLowercase = /[a-z]/.test(passwordVal);
            const hasNumber = /[0-9]/.test(passwordVal);

            if (!hasLength || !hasUppercase || !hasLowercase || !hasNumber) {
                e.preventDefault();
                showRegisterAlert('Password must meet all 4 conditions:\n• At least 6 characters\n• Contains uppercase letter\n• Contains lowercase letter\n• Contains number', 'Password Requirements', 'warning', passwordInput);
                return false;
            }
        });
    </script>
</body>
</html>

