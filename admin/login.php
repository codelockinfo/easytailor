<?php
/**
 * Login Page
 * Tailoring Management System
 */

require_once '../config/config.php';

// Redirect if already logged in
if (is_logged_in()) {
    smart_redirect('dashboard.php');
}

$error_message = '';
$success_message = '';

// Handle login form submission BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        require_once '../controllers/AuthController.php';
        $authController = new AuthController();
        $result = $authController->login($username, $password);
        
        if ($result['success']) {
            smart_redirect('dashboard.php');
        } else {
            // Store error in session and redirect to prevent form resubmission
            $_SESSION['login_error'] = $result['message'];
            smart_redirect('login.php');
            exit;
        }
    } else {
        // Store error in session and redirect to prevent form resubmission
        $_SESSION['login_error'] = 'Please enter both username and password';
        smart_redirect('login.php');
        exit;
    }
}

// Get messages from session
if (isset($_SESSION['login_error'])) {
    $error_message = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

// Handle logout message
if (isset($_GET['logout'])) {
    $success_message = 'You have been logged out successfully';
}

// Handle registration success message
if (isset($_SESSION['reg_success'])) {
    $success_message = $_SESSION['reg_success'];
    unset($_SESSION['reg_success']);
}

// Handle password reset success message
if (isset($_SESSION['login_success'])) {
    $success_message = $_SESSION['login_success'];
    unset($_SESSION['login_success']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon(2).png">
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

        .mt-4 {
            margin-top: 0.5rem !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card login-card border-0">
                    <div class="login-header">
                        <a href="../index.php" class="text-decoration-none">
                        <?php
                        // Get brand logo using smart path detection
                        $brandLogo = get_logo_path('footer-logo.png');
                        if ($brandLogo):
                        ?>
                            <img src="<?php echo $brandLogo; ?>" alt="<?php echo APP_NAME; ?>" class="mb-3" style="max-height: 80px; max-width: 200px;">
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
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            
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
                                           required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
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
                                           required>
                                    <button class="btn btn-outline-secondary" 
                                            type="button" 
                                            id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-login">
                                    <i class="fas fa-sign-in-alt me-2"></i>
                                    Sign In
                                </button>
                            </div>
                            
                            <div class="text-center mt-3">
                                <a href="forgot-password.php" class="text-decoration-none">
                                    <i class="fas fa-key me-1"></i>Forgot Password?
                                </a>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p class="text-muted mb-2">
                                Don't have an account? 
                            </p>
                                
                            <a href="register.php" class="text-decoration-none fw-bold">
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
        // Toggle password visibility
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

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>

