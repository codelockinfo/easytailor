<?php
/**
 * User Profile Page
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
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_profile') {
            $userId = get_user_id();
            // Get existing user data to preserve email (email cannot be changed)
            $existingUser = $userModel->find($userId);
            $data = [
                'full_name' => sanitize_input($_POST['full_name']),
                'email' => $existingUser['email'], // Keep existing email - cannot be changed
                'phone' => sanitize_input($_POST['phone']),
                'address' => sanitize_input($_POST['address'])
            ];
            
            if ($userModel->update($userId, $data)) {
                // Update session
                $_SESSION['user_name'] = $data['full_name'];
                
                $_SESSION['message'] = 'Profile updated successfully';
                $_SESSION['messageType'] = 'success';
            } else {
                $_SESSION['message'] = 'Failed to update profile';
                $_SESSION['messageType'] = 'error';
            }
            header('Location: profile.php');
            exit;
        }
    } else {
        $_SESSION['message'] = 'Invalid request';
        $_SESSION['messageType'] = 'error';
        header('Location: profile.php');
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
$page_title = 'My Profile';
require_once 'includes/header.php';

// Get current user details
$currentUser = $userModel->find(get_user_id());

if (!$currentUser) {
    echo '<div class="alert alert-danger">User not found</div>';
    exit;
}
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-4 mb-4">
        <!-- Profile Card -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-user me-2"></i>Profile Information
                </h5>
            </div>
            <div class="card-body text-center">
                <div class="avatar-circle bg-primary text-white mx-auto mb-3" style="width: 120px; height: 120px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3rem;">
                    <?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?>
                </div>
                <h4 class="mb-1"><?php echo htmlspecialchars($currentUser['full_name']); ?></h4>
                <p class="text-muted mb-2">@<?php echo htmlspecialchars($currentUser['username']); ?></p>
                <span class="badge bg-primary fs-6 mb-3"><?php echo ucfirst($currentUser['role']); ?></span>
                
                <hr>
                
                <div class="text-start">
                    <div class="mb-2">
                        <small class="text-muted">Status</small><br>
                        <span class="badge bg-<?php echo $currentUser['status'] === 'active' ? 'success' : 'secondary'; ?>">
                            <?php echo ucfirst($currentUser['status']); ?>
                        </span>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted">Member Since</small><br>
                        <strong><?php echo format_date($currentUser['created_at'], 'M j, Y'); ?></strong>
                    </div>
                    <div>
                        <small class="text-muted">Last Updated</small><br>
                        <strong><?php echo format_date($currentUser['updated_at'], 'M j, Y'); ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <!-- Edit Profile Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-edit me-2"></i>Edit Profile
                </h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label">Full Name *</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="full_name" 
                                   name="full_name" 
                                   value="<?php echo htmlspecialchars($currentUser['full_name']); ?>" 
                                   required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="username" 
                                   value="<?php echo htmlspecialchars($currentUser['username']); ?>" 
                                   readonly
                                   disabled>
                            <small class="text-muted">Username cannot be changed</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   value="<?php echo htmlspecialchars($currentUser['email']); ?>" 
                                   readonly
                                   disabled>
                            <small class="text-muted">Email cannot be changed</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <div class="input-group">
                                <span class="input-group-text">+91</span>
                                <input type="tel" 
                                       class="form-control" 
                                       id="phone" 
                                       name="phone" 
                                       value="<?php 
                                           $phone = $currentUser['phone'] ?? '';
                                           // Remove +91 prefix if present for display
                                           if (strpos($phone, '+91') === 0) {
                                               $phone = substr($phone, 3);
                                           }
                                           echo htmlspecialchars($phone); 
                                       ?>"
                                       placeholder="10-digit mobile number"
                                       pattern="[0-9]{10}"
                                       maxlength="10">
                            </div>
                            <small class="text-muted">Enter 10-digit mobile number (digits only)</small>
                            <div class="invalid-feedback">Please enter a valid 10-digit phone number.</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" 
                                  id="address" 
                                  name="address" 
                                  rows="3"><?php echo htmlspecialchars($currentUser['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role</label>
                            <input type="text" 
                                   class="form-control" 
                                   value="<?php echo ucfirst($currentUser['role']); ?>" 
                                   readonly
                                   disabled>
                            <small class="text-muted">Role cannot be changed</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <input type="text" 
                                   class="form-control" 
                                   value="<?php echo ucfirst($currentUser['status']); ?>" 
                                   readonly
                                   disabled>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Change Password Card -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-key me-2"></i>Change Password
                </h5>
            </div>
            <div class="card-body">
                <p class="text-muted">To change your password, please visit the Change Password page.</p>
                <a href="change-password.php" class="btn btn-warning">
                    <i class="fas fa-key me-2"></i>Change Password
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Setup phone validation
    setupPhoneValidation('phone', '+91');
    
    // Update phone value with prefix before form submission
    const profileForm = document.querySelector('form');
    if (profileForm && document.getElementById('phone')) {
        profileForm.addEventListener('submit', function(e) {
            const phoneInput = document.getElementById('phone');
            if (phoneInput.value.trim()) {
                const phoneValue = getPhoneWithPrefix('phone', '+91');
                if (!validatePhoneNumber('phone', '+91')) {
                    e.preventDefault();
                    phoneInput.focus();
                    alert('Please enter a valid 10-digit phone number.');
                    return false;
                }
                // Set the phone value with prefix for submission
                phoneInput.value = phoneValue;
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>

