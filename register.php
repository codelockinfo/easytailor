<?php
/**
 * Registration Page - Multi-Tenant
 * Tailoring Management System
 * Allows tailor shop owners to register their business
 */

require_once 'config/config.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect(APP_URL . '/admin/dashboard.php');
}

$error_message = '';
$success_message = '';

// Handle registration form submission BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        
        // Validate input
        $companyName = sanitize_input($_POST['company_name'] ?? '');
        $ownerName = sanitize_input($_POST['owner_name'] ?? '');
        $businessEmail = sanitize_input($_POST['business_email'] ?? '');
        $businessPhone = sanitize_input($_POST['business_phone'] ?? '');
        $username = sanitize_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Basic validation
        if (empty($companyName) || empty($ownerName) || empty($businessEmail) || empty($businessPhone) || empty($username) || empty($password)) {
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
        
        require_once 'models/Company.php';
        require_once 'models/User.php';
        
        $companyModel = new Company();
        $userModel = new User();
        
        // Validate business email
        if (!filter_var($businessEmail, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['reg_error'] = 'Please enter a valid business email address';
            $_SESSION['reg_form_data'] = $_POST;
            header('Location: register.php');
            exit;
        }
        
        // Check if business email already exists
        if ($companyModel->emailExists($businessEmail)) {
            $_SESSION['reg_error'] = 'This business email is already registered. Please use a different email or <a href="login.php">login here</a>';
            $_SESSION['reg_form_data'] = $_POST;
            header('Location: register.php');
            exit;
        }
        
        // Check if username already exists
        if ($userModel->usernameExists($username)) {
            $_SESSION['reg_error'] = 'This username is already taken. Please choose a different username';
            $_SESSION['reg_form_data'] = $_POST;
            header('Location: register.php');
            exit;
        }
        
        try {
            // Start transaction
            $database = new Database();
            $db = $database->getConnection();
            $db->beginTransaction();
            
            // Create company
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
                'subscription_plan' => 'free',
                'subscription_expiry' => date('Y-m-d', strtotime('+30 days'))
            ];
            
            // Handle logo upload
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/logos/';
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
            
            // Commit transaction
            $db->commit();
            
            // Success - redirect to login
            $_SESSION['reg_success'] = 'Registration successful! Please login with your credentials.';
            header('Location: login.php');
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Your Tailor Shop - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        .register-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
            text-align: center;
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .section-title {
            color: #667eea;
            border-bottom: 2px solid #667eea;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card register-card border-0">
                    <div class="register-header">
                        <?php
                        // Check for brand logo
                        $brandLogo = 'uploads/logos/brand-logo.png';
                        if (file_exists($brandLogo)):
                        ?>
                            <img src="<?php echo $brandLogo; ?>" alt="<?php echo APP_NAME; ?>" class="mb-3" style="max-height: 80px; max-width: 200px;">
                        <?php else: ?>
                            <i class="fas fa-cut fa-3x mb-3"></i>
                        <?php endif; ?>
                        <h2 class="mb-2">Register Your Tailor Shop</h2>
                        <p class="mb-0 opacity-75">Start managing your tailoring business today!</p>
                    </div>
                    <div class="card-body p-4">
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
                            
                            <!-- Business Information -->
                            <h5 class="section-title">
                                <i class="fas fa-store me-2"></i>Business Information
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="company_name" class="form-label">
                                        Tailor Shop Name *
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="company_name" 
                                           name="company_name" 
                                           placeholder="e.g., Royal Tailors"
                                           value="<?php echo htmlspecialchars($formData['company_name'] ?? ''); ?>"
                                           required>
                                </div>
                                <div class="col-md-6 mb-3">
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
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="business_email" class="form-label">
                                        Business Email *
                                    </label>
                                    <input type="email" 
                                           class="form-control" 
                                           id="business_email" 
                                           name="business_email" 
                                           placeholder="contact@yourtailor.com"
                                           value="<?php echo htmlspecialchars($formData['business_email'] ?? ''); ?>"
                                           required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="business_phone" class="form-label">
                                        Business Phone *
                                    </label>
                                    <input type="tel" 
                                           class="form-control" 
                                           id="business_phone" 
                                           name="business_phone" 
                                           placeholder="+1 234 567 8900"
                                           value="<?php echo htmlspecialchars($formData['business_phone'] ?? ''); ?>"
                                           required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="business_address" class="form-label">
                                    Business Address
                                </label>
                                <textarea class="form-control" 
                                          id="business_address" 
                                          name="business_address" 
                                          rows="2"
                                          placeholder="123 Main Street, Suite 100"></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="city" name="city" placeholder="e.g., Mumbai, Delhi, Bangalore">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="state" class="form-label">State</label>
                                    <select class="form-select" id="state" name="state">
                                        <option value="">Select State</option>
                                        <option value="Andhra Pradesh">Andhra Pradesh</option>
                                        <option value="Arunachal Pradesh">Arunachal Pradesh</option>
                                        <option value="Assam">Assam</option>
                                        <option value="Bihar">Bihar</option>
                                        <option value="Chhattisgarh">Chhattisgarh</option>
                                        <option value="Goa">Goa</option>
                                        <option value="Gujarat">Gujarat</option>
                                        <option value="Haryana">Haryana</option>
                                        <option value="Himachal Pradesh">Himachal Pradesh</option>
                                        <option value="Jharkhand">Jharkhand</option>
                                        <option value="Karnataka">Karnataka</option>
                                        <option value="Kerala">Kerala</option>
                                        <option value="Madhya Pradesh">Madhya Pradesh</option>
                                        <option value="Maharashtra">Maharashtra</option>
                                        <option value="Manipur">Manipur</option>
                                        <option value="Meghalaya">Meghalaya</option>
                                        <option value="Mizoram">Mizoram</option>
                                        <option value="Nagaland">Nagaland</option>
                                        <option value="Odisha">Odisha</option>
                                        <option value="Punjab">Punjab</option>
                                        <option value="Rajasthan">Rajasthan</option>
                                        <option value="Sikkim">Sikkim</option>
                                        <option value="Tamil Nadu">Tamil Nadu</option>
                                        <option value="Telangana">Telangana</option>
                                        <option value="Tripura">Tripura</option>
                                        <option value="Uttar Pradesh">Uttar Pradesh</option>
                                        <option value="Uttarakhand">Uttarakhand</option>
                                        <option value="West Bengal">West Bengal</option>
                                        <option value="Delhi">Delhi</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="postal_code" class="form-label">PIN Code</label>
                                    <input type="text" class="form-control" id="postal_code" name="postal_code" placeholder="e.g., 400001" maxlength="6" pattern="[0-9]{6}">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="website" class="form-label">Website (Optional)</label>
                                <input type="url" class="form-control" id="website" name="website" placeholder="https://yourtailor.com">
                            </div>
                            
                            <div class="mb-3">
                                <label for="logo" class="form-label">
                                    <i class="fas fa-image me-2"></i>Business Logo (Optional)
                                </label>
                                <input type="file" 
                                       class="form-control" 
                                       id="logo" 
                                       name="logo" 
                                       accept="image/*"
                                       onchange="previewLogo(this)">
                                <small class="text-muted">PNG, JPG, or GIF - Max 2MB</small>
                                <div id="logoPreview" class="mt-2" style="display:none;">
                                    <img id="logoPreviewImg" src="" alt="Logo Preview" class="img-thumbnail" style="max-height: 100px;">
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <!-- Account Information -->
                            <h5 class="section-title">
                                <i class="fas fa-user-lock me-2"></i>Account Information
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">
                                        Username *
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-user"></i>
                                        </span>
                                        <input type="text" 
                                               class="form-control" 
                                               id="username" 
                                               name="username" 
                                               placeholder="Choose a username"
                                               value="<?php echo htmlspecialchars($formData['username'] ?? ''); ?>"
                                               required>
                                    </div>
                                    <small class="text-muted">This will be your login username</small>
                                </div>
                                <div class="col-md-6 mb-3">
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
                            </div>
                            
                            <div class="mb-3">
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
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>What you get:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>30-day free trial</li>
                                    <li>Manage unlimited customers</li>
                                    <li>Track orders and measurements</li>
                                    <li>Generate invoices and reports</li>
                                    <li>Add staff and tailors</li>
                                </ul>
                            </div>
                            
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="agree_terms" required>
                                <label class="form-check-label" for="agree_terms">
                                    I agree to the <a href="#" target="_blank">Terms of Service</a> and <a href="#" target="_blank">Privacy Policy</a>
                                </label>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-register btn-lg">
                                    <i class="fas fa-user-plus me-2"></i>
                                    Register My Tailor Shop
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p class="text-muted">
                                Already have an account? 
                                <a href="login.php" class="text-decoration-none fw-bold">
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
        
        // Password match validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
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

