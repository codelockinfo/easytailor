<!-- Favicon - Primary ICO format for Google Search -->
<link rel="icon" type="image/x-icon" href="../favicon.ico" sizes="16x16 32x32 48x48">
<!-- Favicon - PNG fallback -->
<link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon(2).png">
<link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon(2).png">
<!-- Apple Touch Icon -->
<link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon(2).png">

<?php
/**
 * Verify Reset Code Page
 * Tailoring Management System
 */

require_once '../config/config.php';

// Redirect if already logged in
if (is_logged_in()) {
    smart_redirect('dashboard.php');
}

// Redirect if no reset email in session
if (!isset($_SESSION['reset_email'])) {
    smart_redirect('forgot-password.php');
}

$error_message = '';
$success_message = '';
$email = $_SESSION['reset_email'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = sanitize_input($_POST['code'] ?? '');
    
    if (!empty($code)) {
        // Validate code format (6 digits)
        if (preg_match('/^\d{6}$/', $code)) {
            require_once '../controllers/AuthController.php';
            $authController = new AuthController();
            $result = $authController->verifyResetCode($email, $code);
            
            if ($result['success']) {
                // Store verified token in session
                $_SESSION['verified_reset_token'] = $result['token'];
                smart_redirect('reset-password.php');
            } else {
                $error_message = $result['message'];
            }
        } else {
            $error_message = 'Please enter a valid 6-digit code';
        }
    } else {
        $error_message = 'Please enter the verification code';
    }
}

// Handle resend code
if (isset($_GET['resend'])) {
    require_once '../controllers/AuthController.php';
    $authController = new AuthController();
    $result = $authController->requestPasswordReset($email);
    
    if ($result['success']) {
        $success_message = 'A new verification code has been sent to your email';
    } else {
        $error_message = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Code - <?php echo APP_NAME; ?></title>
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
        }
        .verify-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .verify-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
            text-align: center;
        }
        .code-inputs {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
        }
        .code-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .code-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            outline: none;
        }
        .btn-verify {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-verify:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .timer {
            color: #667eea;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card verify-card border-0">
                    <div class="verify-header">
                        <i class="fas fa-shield-alt fa-3x mb-3"></i>
                        <h3 class="mb-0">Verify Code</h3>
                        <p class="mb-0 opacity-75">Enter the 6-digit code sent to your email</p>
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
                        
                        <div class="info-box">
                            <i class="fas fa-envelope me-2"></i>
                            <small>We've sent a 6-digit code to <strong><?php echo htmlspecialchars($email); ?></strong></small>
                        </div>
                        
                        <form method="POST" action="" id="verifyForm">
                            <div class="mb-3">
                                <label class="form-label text-center w-100">Verification Code</label>
                                <div class="code-inputs">
                                    <input type="text" class="code-input" maxlength="1" pattern="\d" inputmode="numeric" required>
                                    <input type="text" class="code-input" maxlength="1" pattern="\d" inputmode="numeric" required>
                                    <input type="text" class="code-input" maxlength="1" pattern="\d" inputmode="numeric" required>
                                    <input type="text" class="code-input" maxlength="1" pattern="\d" inputmode="numeric" required>
                                    <input type="text" class="code-input" maxlength="1" pattern="\d" inputmode="numeric" required>
                                    <input type="text" class="code-input" maxlength="1" pattern="\d" inputmode="numeric" required>
                                </div>
                                <input type="hidden" name="code" id="fullCode">
                            </div>
                            
                            <div class="text-center mb-3">
                                <small class="text-muted">
                                    Code expires in <span class="timer" id="timer">15:00</span>
                                </small>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-verify">
                                    <i class="fas fa-check-circle me-2"></i>
                                    Verify Code
                                </button>
                                
                                <a href="?resend=1" class="btn btn-outline-secondary">
                                    <i class="fas fa-redo me-2"></i>
                                    Resend Code
                                </a>
                                
                                <a href="forgot-password.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    Back
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle code input boxes
        const codeInputs = document.querySelectorAll('.code-input');
        const fullCodeInput = document.getElementById('fullCode');
        
        codeInputs.forEach((input, index) => {
            // Auto-focus next input on entry
            input.addEventListener('input', (e) => {
                const value = e.target.value;
                if (value.length === 1 && index < codeInputs.length - 1) {
                    codeInputs[index + 1].focus();
                }
                updateFullCode();
            });
            
            // Handle backspace
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && e.target.value === '' && index > 0) {
                    codeInputs[index - 1].focus();
                }
            });
            
            // Prevent non-numeric input
            input.addEventListener('keypress', (e) => {
                if (!/\d/.test(e.key)) {
                    e.preventDefault();
                }
            });
            
            // Handle paste
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pastedData = e.clipboardData.getData('text').replace(/\D/g, '');
                if (pastedData.length === 6) {
                    codeInputs.forEach((inp, i) => {
                        inp.value = pastedData[i] || '';
                    });
                    updateFullCode();
                }
            });
        });
        
        function updateFullCode() {
            const code = Array.from(codeInputs).map(input => input.value).join('');
            fullCodeInput.value = code;
        }
        
        // Focus first input on load
        codeInputs[0].focus();
        
        // Countdown timer (15 minutes)
        let timeLeft = 15 * 60; // 15 minutes in seconds
        const timerElement = document.getElementById('timer');
        
        const countdown = setInterval(() => {
            timeLeft--;
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 0) {
                clearInterval(countdown);
                timerElement.textContent = 'Expired';
                timerElement.classList.add('text-danger');
            }
        }, 1000);
        
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

