<link rel="icon" type="image/x-icon" href="../favicon.ico" sizes="16x16 32x32 48x48">
<link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon(2).png">
<link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon(2).png">
<link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon(2).png">
<?php
require_once '../config/config.php';
require_login();
require_once 'models/User.php';
$userModel = new User();
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $userId = get_user_id();
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        $user = $userModel->find($userId);
        if (!password_verify($currentPassword, $user['password'])) {
            $_SESSION['message'] = 'Current password is incorrect';
            $_SESSION['messageType'] = 'error';
        } elseif (function_exists('validate_password_strength') && !($pwdCheck = validate_password_strength($newPassword))['valid']) {
            $_SESSION['message'] = $pwdCheck['message'];
            $_SESSION['messageType'] = 'error';
        } elseif (strlen($newPassword) < 6) {
            $_SESSION['message'] = 'New password must be at least 6 characters';
            $_SESSION['messageType'] = 'error';
        } elseif ($newPassword !== $confirmPassword) {
            $_SESSION['message'] = 'New passwords do not match';
            $_SESSION['messageType'] = 'error';
        } else {
            if ($userModel->updatePassword($userId, $newPassword)) {
                $_SESSION['message'] = 'Password changed successfully';
                $_SESSION['messageType'] = 'success';
            } else {
                $_SESSION['message'] = 'Failed to change password';
                $_SESSION['messageType'] = 'error';
            }
        }
        
        header('Location: change-password.php');
        exit;
    } else {
        $_SESSION['message'] = 'Invalid request';
        $_SESSION['messageType'] = 'error';
        header('Location: change-password.php');
        exit;
    }
}
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['messageType']);
}
$page_title = 'Change Password';
require_once 'includes/header.php';
?>
<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="card-title mb-0">
                    <i class="fas fa-lock me-2"></i>Change Your Password
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="passwordForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password *</label>
                        <div class="input-group">
                            <input type="password" 
                                   class="form-control" 
                                   id="current_password" 
                                   name="current_password" 
                                   required>
                            <button class="btn btn-outline-secondary" 
                                    type="button" 
                                    onclick="togglePassword('current_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password *</label>
                        <div class="input-group">
                            <input type="password" 
                                   class="form-control" 
                                   id="new_password" 
                                   name="new_password" 
                                   minlength="6"
                                   required>
                            <button class="btn btn-outline-secondary" 
                                    type="button" 
                                    onclick="togglePassword('new_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength" id="changeStrengthBar"></div>
                        <div class="password-requirements mt-2">
                            <div class="requirement" id="cp-req-length">
                                <i class="fas fa-circle"></i> At least 6 characters
                            </div>
                            <div class="requirement" id="cp-req-uppercase">
                                <i class="fas fa-circle"></i> Contains uppercase letter
                            </div>
                            <div class="requirement" id="cp-req-lowercase">
                                <i class="fas fa-circle"></i> Contains lowercase letter
                            </div>
                            <div class="requirement" id="cp-req-number">
                                <i class="fas fa-circle"></i> Contains number
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password *</label>
                        <div class="input-group">
                            <input type="password" 
                                   class="form-control" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   minlength="6"
                                   required>
                            <button class="btn btn-outline-secondary" 
                                    type="button" 
                                    onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small class="d-block mt-2" id="cpMatchMessage" style="font-size: 12px;"></small>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-warning btn-lg">
                            <i class="fas fa-key me-2"></i>Change Password
                        </button>
                        <a href="profile.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Profile
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = event.currentTarget.querySelector('i');
    
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

const cpNewPass = document.getElementById('new_password');
const cpConfirmPass = document.getElementById('confirm_password');
const cpStrengthBar = document.getElementById('changeStrengthBar');
const cpMatchMsg = document.getElementById('cpMatchMessage');

function updateCpRequirement(id, met) {
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

function checkCpMatch() {
    if (!cpNewPass || !cpConfirmPass || !cpMatchMsg) return;
    const p1 = cpNewPass.value;
    const p2 = cpConfirmPass.value;
    if (p2.length > 0) {
        if (p1 === p2) {
            cpMatchMsg.textContent = '✓ Passwords match';
            cpMatchMsg.className = 'd-block mt-2 text-success';
            cpConfirmPass.classList.remove('is-invalid');
            cpConfirmPass.classList.add('is-valid');
        } else {
            cpMatchMsg.textContent = '✗ Passwords do not match';
            cpMatchMsg.className = 'd-block mt-2 text-danger';
            cpConfirmPass.classList.remove('is-valid');
            cpConfirmPass.classList.add('is-invalid');
        }
    } else {
        cpMatchMsg.textContent = '';
        cpConfirmPass.classList.remove('is-valid', 'is-invalid');
    }
}

if (cpNewPass) {
    cpNewPass.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;

        const hasLength = password.length >= 6;
        const hasUppercase = /[A-Z]/.test(password);
        const hasLowercase = /[a-z]/.test(password);
        const hasNumber = /[0-9]/.test(password);

        updateCpRequirement('cp-req-length', hasLength);
        updateCpRequirement('cp-req-uppercase', hasUppercase);
        updateCpRequirement('cp-req-lowercase', hasLowercase);
        updateCpRequirement('cp-req-number', hasNumber);

        if (hasLength) strength++;
        if (hasUppercase) strength++;
        if (hasLowercase) strength++;
        if (hasNumber) strength++;

        if (cpStrengthBar) {
            cpStrengthBar.className = 'password-strength';
            if (password.length > 0) {
                if (strength <= 1) {
                    cpStrengthBar.classList.add('weak');
                } else if (strength <= 3) {
                    cpStrengthBar.classList.add('medium');
                } else {
                    cpStrengthBar.classList.add('strong');
                }
            }
        }

        checkCpMatch();
    });
}

if (cpConfirmPass) {
    cpConfirmPass.addEventListener('input', checkCpMatch);
}

document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('New passwords do not match!');
        return false;
    }

    const hasLength = newPassword.length >= 6;
    const hasUppercase = /[A-Z]/.test(newPassword);
    const hasLowercase = /[a-z]/.test(newPassword);
    const hasNumber = /[0-9]/.test(newPassword);

    if (!hasLength || !hasUppercase || !hasLowercase || !hasNumber) {
        e.preventDefault();
        alert('Password must meet all 4 conditions:\n• At least 6 characters\n• Contains uppercase letter\n• Contains lowercase letter\n• Contains number');
        return false;
    }
});
</script>
<?php require_once 'includes/footer.php'; ?>
<style>
    .card-header {
        color: white !important;
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
</style>