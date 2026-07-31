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
        $businessPhone = sanitize_input($_POST['business_phone'] ?? '');
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
        
        if (strlen($password) < 6) {
            $_SESSION['reg_error'] = 'Password must be at least 6 characters';
            header('Location: register.php');
            exit;
        }
        
        require_once '../models/Company.php';
        require_once '../models/User.php';
        
        $companyModel = new Company();
        $userModel = new User();
        if (!filter_var($businessEmail, FILTER_VALIDATE_EMAIL)) {
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
            $subscriptionExpiry = date('Y-m-d', strtotime('+30 days'));
            
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
                'subscription_expiry' => $subscriptionExpiry
            ];
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../uploads/logos/';
                $fileName = time() . '_' . basename($_FILES['logo']['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath)) {
                    $companyData['logo'] = $targetPath;
                }
            }
            
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
            $db->commit();

            // Attempt to send welcome email (non-blocking)
            $mailService = new MailService();
            if ($cameFromOffer) {
                $welcomeMessage = 'Congratulations! You have successfully registered and your Professional Plan (1 Year Free) has been activated! Please login with your credentials to get started.';
                // Clear the offer source cookie after successful registration
                setcookie('promo_offer_source', '', time() - 3600, '/');
                // Note: Removed offer_claimed cookie to allow users to claim offers multiple times through different profiles
            } else {
                $welcomeMessage = 'Registration successful! Please login with your credentials.';
            }

            $emailSent = false;
            $mailError = '';

            if ($mailService->isEnabled()) {
                $emailSent = $mailService->sendWelcomeEmail([
                    'email' => $businessEmail,
                    'name' => $ownerName,
                    'companyName' => $companyName,
                    'username' => $username
                ]);
                $mailError = $mailService->getLastError();
            }

            if (!$emailSent) {
                // Fallback to native mail() with inline template
                $baseUrl = rtrim(APP_URL, '/');
                if (substr($baseUrl, -6) === '/admin') {
                    $baseUrl = substr($baseUrl, 0, -6);
                }
                $manageUrl = $baseUrl . '/admin/login.php';
                $logoPath = get_logo_path('footer-logo.png') ?: get_logo_path('brand-logo.png') ?: 'assets/images/logo.png';
                $logoUrl = $baseUrl . '/' . ltrim(str_replace(['../', './'], '', $logoPath), '/');

                $subject = 'Welcome to ' . APP_NAME;
                $body = "
                <html>
                    <head>
                        <meta charset='UTF-8'>
                        <style>
                            body { background:#f5f6fa; font-family: Arial, Helvetica, sans-serif; margin:0; padding:0; }
                            .wrapper { padding:32px 0; }
                            .card { max-width:600px; margin:0 auto; background:#ffffff; border-radius:18px; overflow:hidden; box-shadow:0 12px 40px rgba(17,24,39,0.12); }
                            .header { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); padding:40px 40px 24px; text-align:center; }
                            .header img { max-width:180px; height:auto; margin-bottom:20px; }
                            .header h1 { color:#ffffff; margin:0; font-size:28px; }
                            .content { padding:32px 40px 24px; color:#2d3748; }
                            .content p { font-size:16px; line-height:1.6; margin:0 0 16px; }
                            .button { display:inline-block; padding:16px 32px; background:linear-gradient(135deg,#4c51bf 0%,#667eea 100%); color:#ffffff !important; text-decoration:none; border-radius:8px; font-weight:600; }
                            .footer { padding:24px 40px 40px; border-top:1px solid #edf2f7; text-align:center; color:#718096; font-size:13px; }
                        </style>
                    </head>
                    <body>
                        <div class='wrapper'>
                            <div class='card'>
                                <div class='header'>
                                    <img src='{$logoUrl}' alt='" . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . "'>
                                    <h1>Welcome to " . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . "!</h1>
                                </div>
                                <div class='content'>
                                    <p>Hi " . htmlspecialchars($ownerName, ENT_QUOTES, 'UTF-8') . ",</p>
                                    <p>Thanks for registering <strong>" . htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') . "</strong> with " . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . ". Your dashboard is ready to manage orders, customers, tailors, and payments in one place.</p>";

                if (!empty($username)) {
                    $body .= "<p><strong>Username:</strong> " . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . "</p>";
                }

                $supportEmail = SMTP_FROM_EMAIL ?: 'support@' . ($_SERVER['SERVER_NAME'] ?? 'example.com');

                $body .= "
                                    <p>Click the button below to start managing your tailor shop right away.</p>
                                    <p style='text-align:center; margin:32px 0;'>
                                        <a class='button' href='" . htmlspecialchars($manageUrl, ENT_QUOTES, 'UTF-8') . "' style='color:#ffffff !important;'><span style='color:#ffffff !important;'>Manage Your Tailor</span></a>
                                    </p>
                                    <p>If you need help, reply to this email or contact us at <a href='mailto:" . htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') . "</a>.</p>
                                </div>
                                <div class='footer'>
                                    <p>Cheers,<br>" . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . " Team</p>
                                    <p>&copy; " . date('Y') . ' ' . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . ". All rights reserved.</p>
                                </div>
                            </div>
                        </div>
                    </body>
                </html>";

                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                $fromEmail = SMTP_FROM_EMAIL ?: 'no-reply@' . ($_SERVER['SERVER_NAME'] ?? 'example.com');
                $fromName = SMTP_FROM_NAME ?: APP_NAME;
                $headers .= "From: " . $fromName . " <" . $fromEmail . ">\r\n";

                $emailSent = mail($businessEmail, $subject, $body, $headers);

                if (!$emailSent) {
                    $welcomeMessage .= ' (We could not deliver the welcome email. Please verify SMTP settings.)';
                    $fallbackError = $mailError ?: 'mail() fallback failed';
                    error_log('Welcome email failed: ' . $fallbackError);
                }
            }


            // Track signup event for GA4
            require_once '../helpers/GA4Helper.php';
            $_SESSION['ga4_event'] = GA4Helper::trackSignup($userId, $companyId, $companyName, $ownerName);
            
            // Success - redirect to login
            $_SESSION['reg_success'] = $welcomeMessage;
            smart_redirect('login.php');
            exit;
            
        } catch (Exception $e) {
            // Rollback on error
            $db->rollBack();
            
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
    <!-- Favicon - Primary ICO format for Google Search -->
    <link rel="icon" type="image/x-icon" href="../favicon.ico" sizes="16x16 32x32 48x48">
    <!-- Favicon - PNG fallback -->
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon(2).png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon(2).png">
    <!-- Apple Touch Icon -->
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
                        
                        <form method="POST" enctype="multipart/form-data" id="registerForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            
                            <!-- Register Form Fields -->
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
                                    I agree to the <a href="../terms-of-service" target="_blank">Terms of Service</a> and <a href="../privacy-policy" target="_blank">Privacy Policy</a>
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
        
        // Phone Number Validation (Inline version for register page)
        function setupPhoneValidationInline(phoneInputId, countryPrefix = '+91') {
            const phoneInput = document.getElementById(phoneInputId);
            if (!phoneInput) return;
            
            let initialValue = phoneInput.value || '';
            if (initialValue.startsWith(countryPrefix)) {
                initialValue = initialValue.replace(countryPrefix, '').trim();
            }
            initialValue = initialValue.replace(/[^0-9]/g, '').slice(0, 10);
            phoneInput.value = initialValue;
            phoneInput.maxLength = 10;
            phoneInput.setAttribute('pattern', '[0-9]{10}');
            
            phoneInput.addEventListener('input', function(e) {
                let value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
                this.value = value;
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
                let value = pastedText.replace(/[^0-9]/g, '').slice(0, 10);
                this.value = value;
            });
            
            phoneInput.addEventListener('blur', function() {
                let value = this.value.replace(/[^0-9]/g, '');
                if (value.length === 10) {
                    this.setAttribute('data-phone-full', countryPrefix + value);
                }
            });
            
            return phoneInput;
        }
        
        function getPhoneWithPrefixInline(phoneInputId, countryPrefix = '+91') {
            const phoneInput = document.getElementById(phoneInputId);
            if (!phoneInput) return '';
            let value = phoneInput.value.replace(/[^0-9]/g, '');
            if (value.length === 10) {
                return countryPrefix + value;
            }
            return phoneInput.getAttribute('data-phone-full') || value || '';
        }
        
        function validatePhoneNumberInline(phoneInputId) {
            const phoneInput = document.getElementById(phoneInputId);
            if (!phoneInput) return false;
            let value = phoneInput.value.replace(/[^0-9]/g, '');
            if (value.length !== 10) {
                phoneInput.classList.add('is-invalid');
                return false;
            }
            phoneInput.classList.remove('is-invalid');
            return true;
        }
        
        // Initialize phone validation on page load
        document.addEventListener('DOMContentLoaded', function() {
            setupPhoneValidationInline('business_phone', '+91');
        });
        
        // Password match validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            // Validate phone number
            const businessPhone = document.getElementById('business_phone');
            if (businessPhone && businessPhone.value.trim()) {
                if (!validatePhoneNumberInline('business_phone')) {
                    e.preventDefault();
                    businessPhone.focus();
                    alert('Please enter a valid 10-digit phone number.');
                    return false;
                }
                // Set phone value with prefix before submission
                businessPhone.value = getPhoneWithPrefixInline('business_phone', '+91');
            }
            
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
        });
    </script>
</body>
</html>

