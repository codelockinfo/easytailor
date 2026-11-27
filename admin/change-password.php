<?php
ob_start();
require_once '../config/config.php';
require_login();
require_once 'models/User.php';
$userModel = new User();
$message = '';
$messageType = '';
$fieldErrors = [];

$isAjax = (
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
    (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errorField = null;
    $errorMsg = '';
    $success = false;

    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $userId = get_user_id();
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        $user = $userModel->find($userId);
        if ($currentPassword === '') {
            $errorField = 'current_password';
            $errorMsg = 'Please enter current password.';
        } elseif (!$user || !password_verify($currentPassword, $user['password'])) {
            $errorField = 'current_password';
            $errorMsg = 'Current password is incorrect.';
        } elseif (function_exists('validate_password_strength') && !($pwdCheck = validate_password_strength($newPassword))['valid']) {
            $errorField = 'new_password';
            $errorMsg = $pwdCheck['message'];
        } elseif (strlen($newPassword) < 6) {
            $errorField = 'new_password';
            $errorMsg = 'New password must be at least 6 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $errorField = 'confirm_password';
            $errorMsg = 'New passwords do not match!';
        } else {
            if ($userModel->updatePassword($userId, $newPassword)) {
                $success = true;
                $errorMsg = 'Password changed successfully';
            } else {
                $errorMsg = 'Failed to change password';
            }
        }
    } else {
        $errorMsg = 'Invalid request';
    }

    if ($isAjax) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'field' => $errorField,
            'message' => $errorMsg
        ]);
        exit;
    }

    if ($success) {
        $_SESSION['message'] = $errorMsg;
        $_SESSION['messageType'] = 'success';
    } elseif ($errorField) {
        $_SESSION['pw_field_errors'] = [$errorField => $errorMsg];
    } else {
        $_SESSION['message'] = $errorMsg;
        $_SESSION['messageType'] = 'error';
    }
    header('Location: change-password.php');
    exit;
}

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['messageType']);
}
if (isset($_SESSION['pw_field_errors'])) {
    $fieldErrors = $_SESSION['pw_field_errors'];
    unset($_SESSION['pw_field_errors']);
}
$page_title = 'Change Password';
require_once 'includes/header.php';
?>
<link rel="icon" type="image/x-icon" href="../favicon.ico" sizes="16x16 32x32 48x48">
<link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon(2).png">
<link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon(2).png">
<link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon(2).png">
<div id="passwordAlertContainer">
<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="card-title mb-0">
                    <i class="fas fa-lock me-2"></i>Change Your Password
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="passwordForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password *</label>
                        <div class="input-group has-validation">
                            <input type="password" 
                                   class="form-control<?php echo !empty($fieldErrors['current_password']) ? ' is-invalid' : ''; ?>" 
                                   id="current_password" 
                                   name="current_password" 
                                   placeholder="Enter current password"
                                   required>
                            <button class="btn btn-outline-secondary" 
                                    type="button" 
                                    onclick="togglePassword('current_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                            <div class="invalid-feedback" id="currentPasswordFeedback"><?php echo htmlspecialchars($fieldErrors['current_password'] ?? 'Please enter current password.'); ?></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password *</label>
                        <div class="input-group has-validation">
                            <input type="password" 
                                   class="form-control<?php echo !empty($fieldErrors['new_password']) ? ' is-invalid' : ''; ?>" 
                                   id="new_password" 
                                   name="new_password" 
                                   placeholder="Enter new password (min. 6 characters)"
                                   minlength="6"
                                   required>
                            <button class="btn btn-outline-secondary" 
                                    type="button" 
                                    onclick="togglePassword('new_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                            <div class="invalid-feedback" id="newPasswordFeedback"><?php echo htmlspecialchars($fieldErrors['new_password'] ?? 'New password must be at least 6 characters.'); ?></div>
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
                        <div class="input-group has-validation">
                            <input type="password" 
                                   class="form-control<?php echo !empty($fieldErrors['confirm_password']) ? ' is-invalid' : ''; ?>" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   placeholder="Re-enter new password"
                                   minlength="6"
                                   required>
                            <button class="btn btn-outline-secondary" 
                                    type="button" 
                                    onclick="togglePassword('confirm_password', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                            <div class="invalid-feedback" id="confirmPasswordFeedback"><?php echo htmlspecialchars($fieldErrors['confirm_password'] ?? 'New passwords do not match!'); ?></div>
                        </div>
                        <small class="d-block mt-2" id="cpMatchMessage" style="font-size: 12px;"></small>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-warning btn-lg" id="btnChangePassword">
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
function togglePassword(fieldId, btnEl) {
    const field = document.getElementById(fieldId);
    const btn = btnEl || (typeof event !== 'undefined' ? event.currentTarget : null);
    const icon = btn ? btn.querySelector('i') : null;
    if (!field) return;

    if (field.type === 'password') {
        field.type = 'text';
        if (icon) {
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
    } else {
        field.type = 'password';
        if (icon) {
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
}

(function() {
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

    const FIELD_MAP = {
        current_password: { inputId: 'current_password', feedbackId: 'currentPasswordFeedback' },
        new_password:     { inputId: 'new_password',     feedbackId: 'newPasswordFeedback' },
        confirm_password: { inputId: 'confirm_password', feedbackId: 'confirmPasswordFeedback' }
    };

    function setFieldError(fieldName, message) {
        const meta = FIELD_MAP[fieldName];
        if (!meta) return null;
        const input = document.getElementById(meta.inputId);
        const fb = document.getElementById(meta.feedbackId);
        if (fb && message) fb.textContent = message;
        if (input) input.classList.add('is-invalid');
        return input;
    }

    function clearFieldError(fieldName) {
        const meta = FIELD_MAP[fieldName];
        if (!meta) return;
        document.getElementById(meta.inputId)?.classList.remove('is-invalid');
    }

    function showBannerAlert(msg, type) {
        const container = document.getElementById('passwordAlertContainer');
        if (!container) return;
        const cls = type === 'success' ? 'success' : 'danger';
        const icon = type === 'success' ? 'check-circle' : 'exclamation-triangle';
        container.innerHTML = '';
        const div = document.createElement('div');
        div.className = 'alert alert-' + cls + ' alert-dismissible fade show';
        div.innerHTML = '<i class="fas fa-' + icon + ' me-2"></i><span></span><button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        div.querySelector('span').textContent = msg;
        container.appendChild(div);
    }

    Object.keys(FIELD_MAP).forEach(function(fieldName) {
        const input = document.getElementById(FIELD_MAP[fieldName].inputId);
        if (!input) return;
        input.addEventListener('input', function() {
            clearFieldError(fieldName);
            if (fieldName === 'new_password') {
                const confirmEl = document.getElementById('confirm_password');
                if (confirmEl && confirmEl.classList.contains('is-invalid') && confirmEl.value === this.value) {
                    clearFieldError('confirm_password');
                }
            }
        });
    });

    const passwordForm = document.getElementById('passwordForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const currentInput = document.getElementById('current_password');
            const newInput = document.getElementById('new_password');
            const confirmInput = document.getElementById('confirm_password');
            const submitBtn = document.getElementById('btnChangePassword');

            let firstInvalid = null;

            if (!currentInput.value) {
                firstInvalid = firstInvalid || setFieldError('current_password', 'Please enter current password.');
            } else {
                clearFieldError('current_password');
            }

            const pwdVal = newInput.value;
            const hasLength = pwdVal.length >= 6;
            const hasUppercase = /[A-Z]/.test(pwdVal);
            const hasLowercase = /[a-z]/.test(pwdVal);
            const hasNumber = /[0-9]/.test(pwdVal);

            if (!pwdVal) {
                firstInvalid = firstInvalid || setFieldError('new_password', 'Please enter new password.');
            } else if (!hasLength || !hasUppercase || !hasLowercase || !hasNumber) {
                firstInvalid = firstInvalid || setFieldError('new_password', 'Password must be at least 6 characters and contain uppercase, lowercase, and a number.');
            } else {
                clearFieldError('new_password');
            }

            if (!confirmInput.value) {
                firstInvalid = firstInvalid || setFieldError('confirm_password', 'Please confirm new password.');
            } else if (newInput.value !== confirmInput.value) {
                firstInvalid = firstInvalid || setFieldError('confirm_password', 'New passwords do not match!');
            } else {
                clearFieldError('confirm_password');
            }

            if (firstInvalid) {
                firstInvalid.focus();
                return;
            }

            const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
            }

            const alertContainer = document.getElementById('passwordAlertContainer');
            if (alertContainer) alertContainer.innerHTML = '';

            try {
                const res = await fetch('ajax/change_password.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: new FormData(passwordForm)
                });
                if (res.redirected && res.url.includes('login')) {
                    window.location.href = res.url;
                    return;
                }
                const data = await res.json();

                if (data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                if (data.success) {
                    passwordForm.reset();
                    Object.keys(FIELD_MAP).forEach(clearFieldError);
                    if (cpStrengthBar) cpStrengthBar.className = 'password-strength';
                    ['cp-req-length', 'cp-req-uppercase', 'cp-req-lowercase', 'cp-req-number'].forEach(function(id) {
                        updateCpRequirement(id, false);
                    });
                    if (cpMatchMsg) cpMatchMsg.textContent = '';
                    showBannerAlert(data.message || 'Password changed successfully', 'success');
                } else if (data.field) {
                    const target = setFieldError(data.field, data.message);
                    if (target) target.focus();
                } else {
                    showBannerAlert(data.message || 'Failed to change password', 'error');
                }
            } catch (err) {
                showBannerAlert('Unable to verify password right now. Please try again.', 'error');
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnHtml;
                }
            }
        });
    }
})();
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
