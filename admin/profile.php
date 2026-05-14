<!-- Favicon - Primary ICO format for Google Search -->
<link rel="icon" type="image/x-icon" href="../favicon.ico" sizes="16x16 32x32 48x48">
<!-- Favicon - PNG fallback -->
<link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon(2).png">
<link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon(2).png">
<!-- Apple Touch Icon -->
<link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon(2).png">

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

// Get user initials (2 letters)
$name = $currentUser['full_name'];
$nameParts = explode(' ', trim($name));
$userInitials = '';
if (count($nameParts) >= 2) {
    $userInitials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[count($nameParts) - 1], 0, 1));
} else {
    $userInitials = strtoupper(substr($name, 0, 2));
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
                <div class="position-relative d-inline-block mb-3">
                    <div class="avatar-circle-wrapper" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#profilePhotoModal">
                        <div class="avatar-circle bg-primary text-white mx-auto" style="width: 120px; height: 120px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3rem; overflow: hidden; border: 4px solid #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.1); position: relative;">
                            <?php if (!empty($currentUser['profile_image'])): ?>
                                <img id="profilePreviewMain" src="../<?php echo htmlspecialchars($currentUser['profile_image']); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <span id="profileLetterMain"><?php echo htmlspecialchars($userInitials); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="btn btn-primary btn-sm position-absolute bottom-0 end-0 rounded-circle shadow" style="width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center; border: 2px solid #fff;">
                            <i class="fas fa-pencil-alt" style="font-size: 0.8rem;"></i>
                        </div>
                    </div>
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

<!-- Profile Photo Modal -->
<div class="modal fade" id="profilePhotoModal" tabindex="-1" aria-labelledby="profilePhotoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="profilePhotoModalLabel">Profile Photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center pt-4 pb-5">
                <div class="position-relative d-inline-block mb-4">
                    <div id="modalAvatarPreview" class="avatar-circle bg-primary text-white mx-auto shadow" style="width: 180px; height: 180px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 4.5rem; overflow: hidden; border: 6px solid #fff;">
                        <?php if (!empty($currentUser['profile_image'])): ?>
                            <img src="../<?php echo htmlspecialchars($currentUser['profile_image']); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <span><?php echo htmlspecialchars($userInitials); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <p id="uploadStatusText" class="text-muted mb-4 px-4" style="font-size: 0.9rem;">
                    Update your profile picture to help people recognize you across the platform.
                </p>

                <div id="modalActionsDefault">
                    <label for="modal_image_input" class="btn btn-primary px-4 py-2" style="border-radius: 10px;">
                        <i class="fas fa-camera me-2"></i>Change Photo
                    </label>
                    <?php if (!empty($currentUser['profile_image'])): ?>
                        <button type="button" id="btnDeletePhoto" class="btn btn-outline-danger ms-2 px-4 py-2" style="border-radius: 10px;">
                            <i class="fas fa-trash-alt me-2"></i>Delete
                        </button>
                    <?php endif; ?>
                </div>

                <div id="modalActionsPreview" class="d-none">
                    <button type="button" id="btnSavePhoto" class="btn btn-success px-4 py-2" style="border-radius: 10px;">
                        <i class="fas fa-check me-2"></i>Save
                    </button>
                    <button type="button" id="btnCancelPreview" class="btn btn-outline-secondary ms-2 px-4 py-2" style="border-radius: 10px;">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                </div>

                <div id="modalActionsLoading" class="d-none">
                    <button class="btn btn-primary px-4 py-2" type="button" disabled style="border-radius: 10px;">
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        Saving...
                    </button>
                </div>

                <input type="file" id="modal_image_input" class="d-none" accept="image/*">
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
    
    // Profile image management in modal
    const modalImageInput = document.getElementById('modal_image_input');
    const modalAvatarPreview = document.getElementById('modalAvatarPreview');
    const modalActionsDefault = document.getElementById('modalActionsDefault');
    const modalActionsPreview = document.getElementById('modalActionsPreview');
    const modalActionsLoading = document.getElementById('modalActionsLoading');
    const uploadStatusText = document.getElementById('uploadStatusText');
    const btnSavePhoto = document.getElementById('btnSavePhoto');
    const btnCancelPreview = document.getElementById('btnCancelPreview');
    const btnDeletePhoto = document.getElementById('btnDeletePhoto');
    
    let originalAvatarContent = modalAvatarPreview.innerHTML;
    let selectedFile = null;

    if (modalImageInput) {
        modalImageInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                selectedFile = this.files[0];
                const reader = new FileReader();
                reader.onload = function(e) {
                    modalAvatarPreview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">`;
                    uploadStatusText.innerHTML = '<span class="text-primary fw-bold">Previewing new photo...</span>';
                    modalActionsDefault.classList.add('d-none');
                    modalActionsPreview.classList.remove('d-none');
                };
                reader.readAsDataURL(selectedFile);
            }
        });
    }

    if (btnCancelPreview) {
        btnCancelPreview.addEventListener('click', function() {
            modalAvatarPreview.innerHTML = originalAvatarContent;
            uploadStatusText.innerHTML = 'Update your profile picture to help people recognize you across the platform.';
            modalActionsDefault.classList.remove('d-none');
            modalActionsPreview.classList.add('d-none');
            modalImageInput.value = '';
            selectedFile = null;
        });
    }

    if (btnSavePhoto) {
        btnSavePhoto.addEventListener('click', function() {
            if (!selectedFile) return;

            const formData = new FormData();
            formData.append('profile_image', selectedFile);
            formData.append('csrf_token', '<?php echo generate_csrf_token(); ?>');

            modalActionsPreview.classList.add('d-none');
            modalActionsLoading.classList.remove('d-none');
            uploadStatusText.innerHTML = '<span class="text-primary fw-bold">Saving your new photo...</span>';

            fetch('ajax/upload_profile_image.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update main page avatar
                    const mainPreview = document.getElementById('profilePreviewMain');
                    if (mainPreview) {
                        mainPreview.src = data.image_url;
                    } else {
                        const avatarCircle = document.querySelector('.avatar-circle-wrapper .avatar-circle');
                        avatarCircle.innerHTML = `<img id="profilePreviewMain" src="${data.image_url}" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">`;
                    }
                    
                    // Update modal state
                    originalAvatarContent = modalAvatarPreview.innerHTML;
                    uploadStatusText.innerHTML = '<span class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i> Profile photo updated successfully!</span>';
                    modalActionsLoading.classList.add('d-none');
                    modalActionsDefault.classList.remove('d-none');
                    
                    // Add delete button if it wasn't there
                    if (!document.getElementById('btnDeletePhoto')) {
                        const deleteBtn = document.createElement('button');
                        deleteBtn.type = 'button';
                        deleteBtn.id = 'btnDeletePhoto';
                        deleteBtn.className = 'btn btn-outline-danger ms-2 px-4 py-2';
                        deleteBtn.style.borderRadius = '10px';
                        deleteBtn.innerHTML = '<i class="fas fa-trash-alt me-2"></i>Delete';
                        modalActionsDefault.appendChild(deleteBtn);
                        // Re-bind delete event
                        bindDeleteEvent(deleteBtn);
                    }
                    
                    // Optional: update header avatar if exists
                    const headerAvatar = document.querySelector('.user-initials-badge');
                    if (headerAvatar) {
                        // If header uses initials, we might want to keep them or change to small img
                        // For now, let's just refresh the page or update if it's an img
                    }
                    
                    setTimeout(() => {
                        bootstrap.Modal.getInstance(document.getElementById('profilePhotoModal')).hide();
                        location.reload(); // Reload to update everything consistently
                    }, 1500);
                } else {
                    alert(data.error || 'Failed to upload image');
                    btnCancelPreview.click();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred during upload');
                btnCancelPreview.click();
            });
        });
    }

    function bindDeleteEvent(el) {
        if (!el) return;
        el.addEventListener('click', function() {
            const formData = new FormData();
            formData.append('csrf_token', '<?php echo generate_csrf_token(); ?>');

            modalActionsDefault.classList.add('d-none');
            modalActionsLoading.classList.remove('d-none');
            modalActionsLoading.querySelector('button').innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Deleting...';

            fetch('ajax/delete_profile_image.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    modalAvatarPreview.innerHTML = `<span>${data.initial}</span>`;
                    originalAvatarContent = modalAvatarPreview.innerHTML;
                    uploadStatusText.innerHTML = '<span class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i> Profile photo deleted.</span>';
                    
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    alert(data.error || 'Failed to delete photo');
                    modalActionsLoading.classList.add('d-none');
                    modalActionsDefault.classList.remove('d-none');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
                modalActionsLoading.classList.add('d-none');
                modalActionsDefault.classList.remove('d-none');
            });
        });
    }

    bindDeleteEvent(btnDeletePhoto);
});
</script>

<?php require_once 'includes/footer.php'; ?>

<style>
    .avatar-circle-wrapper {
        transition: all 0.3s ease;
    }
    .avatar-circle-wrapper:hover {
        transform: scale(1.05);
    }
    .avatar-circle-wrapper:hover .avatar-circle {
        box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    }
    
    #modalAvatarPreview {
        transition: all 0.3s ease;
    }
    
    .modal-content {
        animation: modalFadeIn 0.3s ease-out;
    }
    
    .spinner-border-sm {
        width: 0.8rem;
        height: 0.8rem;
        border-width: 0.15em;
    }
    
    @keyframes modalFadeIn {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 768px) {
        .avatar-circle {
            width: 90px !important;
            height: 90px !important;
        }
        #modalAvatarPreview {
            width: 140px !important;
            height: 140px !important;
            font-size: 3.5rem !important;
        }
    }
</style>