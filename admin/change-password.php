<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="../favicon(2).png">

<?php
/**
 * Change Password Page
 * Tailoring Management System
 */

// Include config first (before any output)
require_once '../config/config.php';

// Check if user is logged in
require_login();

require_once 'models/User.php';

$userModel = new User();
$message = '';
$messageType = '';

// Handle form submissions BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $userId = get_user_id();
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Get current user
        $user = $userModel->find($userId);
        
        // Verify current password
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

// Get messages from session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['messageType']);
}

// NOW include header (after all redirects are done)
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
                        <small class="text-muted">Minimum 6 characters</small>
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

// Validate passwords match
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('New passwords do not match!');
        return false;
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>

