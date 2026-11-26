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
                                   placeholder="Enter current password"
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
                                   placeholder="Enter new password (min. 6 characters)"
                                   minlength="6"
                                   required>
                            <button class="btn btn-outline-secondary" 
                                    type="button" 
                                    onclick="togglePassword('new_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password *</label>
                        <div class="input-group">
                            <input type="password" 
                                   class="form-control" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   placeholder="Re-enter new password"
                                   minlength="6"
                                   required>
                            <button class="btn btn-outline-secondary" 
                                    type="button" 
                                    onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
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

<!-- Validation Alert Modal -->
<div class="modal fade" id="passwordAlertModal" tabindex="-1" aria-labelledby="passwordAlertTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content border-0 shadow-lg text-center p-3 p-sm-4" style="border-radius: 20px;">
            <div class="modal-body p-0">
                <div id="passwordAlertIconContainer" class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 58px; height: 58px; background: #fee2e2; color: #dc2626;">
                    <i id="passwordAlertIcon" class="fas fa-times-circle fa-2x"></i>
                </div>
                <h5 id="passwordAlertTitle" class="fw-bold mb-2 text-dark" style="font-size: 1.12rem;">Notice</h5>
                <p id="passwordAlertMessage" class="text-secondary small mb-4 px-2" style="line-height: 1.6; font-size: 0.9rem;"></p>
                <div class="d-flex justify-content-center">
                    <button type="button" class="btn rounded-pill px-5 text-white fw-semibold" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const PASSWORD_ALERT_STYLES = {
    danger:  { bg: '#fee2e2', color: '#dc2626', icon: 'fas fa-times-circle fa-2x' },
    warning: { bg: '#fef3c7', color: '#d97706', icon: 'fas fa-exclamation-triangle fa-2x' },
    success: { bg: '#dcfce7', color: '#16a34a', icon: 'fas fa-check-circle fa-2x' },
    info:    { bg: '#e0e7ff', color: '#4338ca', icon: 'fas fa-info-circle fa-2x' }
};

function showPasswordAlert(message, title = 'Notice', type = 'warning', focusEl = null) {
    const modalEl = document.getElementById('passwordAlertModal');
    if (!modalEl || !window.bootstrap || !bootstrap.Modal) {
        alert(message);
        return;
    }
    const iconContainer = document.getElementById('passwordAlertIconContainer');
    const iconEl = document.getElementById('passwordAlertIcon');
    const titleEl = document.getElementById('passwordAlertTitle');
    const msgEl = document.getElementById('passwordAlertMessage');

    if (titleEl) titleEl.textContent = title;
    if (msgEl) msgEl.textContent = message;

    const style = PASSWORD_ALERT_STYLES[type] || PASSWORD_ALERT_STYLES.warning;
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
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmInput = document.getElementById('confirm_password');
    const confirmPassword = confirmInput.value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        showPasswordAlert('New passwords do not match!', 'Password Mismatch', 'danger', confirmInput);
        return false;
    }
});
</script>
<?php require_once 'includes/footer.php'; ?>
<style>
    .card-header {
        color: white !important;
    }
</style>