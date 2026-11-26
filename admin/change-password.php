<?php
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
                        <small class="text-muted">Minimum 6 characters</small>
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
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Password Requirements:</strong>
                        <ul class="mb-0 mt-2">
                            <li>At least 6 characters long</li>
                            <li>Mix of letters and numbers recommended</li>
                            <li>Avoid common words or patterns</li>
                        </ul>
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

            if (!newInput.value) {
                firstInvalid = firstInvalid || setFieldError('new_password', 'Please enter new password.');
            } else if (newInput.value.length < 6) {
                firstInvalid = firstInvalid || setFieldError('new_password', 'New password must be at least 6 characters.');
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

            try {
                const res = await fetch('change-password.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: new FormData(passwordForm)
                });
                const data = await res.json();

                if (data.success) {
                    passwordForm.reset();
                    Object.keys(FIELD_MAP).forEach(clearFieldError);
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
</style>
