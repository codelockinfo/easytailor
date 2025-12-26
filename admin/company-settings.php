<!-- Favicon - Primary ICO format for Google Search -->
<link rel="icon" type="image/x-icon" href="../favicon.ico" sizes="16x16 32x32 48x48">
<!-- Favicon - PNG fallback -->
<link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon(2).png">
<link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon(2).png">
<!-- Apple Touch Icon -->
<link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon(2).png">

<?php
/**
 * Company Settings Page
 * Manage business/tailor shop information
 */

// Include config first (before any output)
require_once '../config/config.php';

// Check if user is logged in
require_login();

// Only admins can edit company settings
require_role('admin');

require_once 'models/Company.php';
require_once '../models/EmailChangeRequest.php';

$companyModel = new Company();
$emailChangeRequestModel = new EmailChangeRequest();
$message = '';
$messageType = '';

// Get company ID from session
$companyId = get_company_id();

if (!$companyId) {
    die('No company associated with your account. Please contact support.');
}

// Handle form submissions BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        
        // Get existing company data to preserve email (email cannot be changed directly)
        $existingCompany = $companyModel->find($companyId);
        
        $data = [
            'company_name' => sanitize_input($_POST['company_name']),
            'owner_name' => sanitize_input($_POST['owner_name']),
            'business_email' => $existingCompany['business_email'], // Keep existing email - cannot be changed directly
            'business_phone' => sanitize_input($_POST['business_phone']),
            'business_address' => sanitize_input($_POST['business_address']),
            'city' => sanitize_input($_POST['city']),
            'state' => sanitize_input($_POST['state']),
            'country' => 'India',
            'postal_code' => sanitize_input($_POST['postal_code']),
            'tax_number' => sanitize_input($_POST['tax_number']),
            'website' => sanitize_input($_POST['website']),
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata'
        ];
        
        // Handle logo upload
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/logos/';
            $fileName = time() . '_' . basename($_FILES['logo']['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath)) {
                $data['logo'] = $targetPath;
            }
        }
        
        if ($companyModel->update($companyId, $data)) {
            $_SESSION['message'] = 'Company settings updated successfully';
            $_SESSION['messageType'] = 'success';
        } else {
            $_SESSION['message'] = 'Failed to update company settings';
            $_SESSION['messageType'] = 'error';
        }
        
        header('Location: company-settings.php');
        exit;
        
    } else {
        $_SESSION['message'] = 'Invalid request';
        $_SESSION['messageType'] = 'error';
        header('Location: company-settings.php');
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
$page_title = 'Company Settings';
require_once 'includes/header.php';

// Get company details
$company = $companyModel->find($companyId);

if (!$company) {
    echo '<div class="alert alert-danger">Company not found</div>';
    exit;
}

// Get company statistics
$companyStats = $companyModel->getCompanyStats($companyId);

// Check if there's a pending email change request
$hasPendingEmailRequest = $emailChangeRequestModel->hasPendingRequest($companyId);
$currentEmail = $company['business_email'] ?? '';
$emailFieldDisabled = !empty($currentEmail);
?>


<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Company Statistics -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h4><?php echo number_format($companyStats['total_customers']); ?></h4>
                <p class="mb-0"><i class="fas fa-users me-2"></i>Total Customers</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h4><?php echo number_format($companyStats['total_orders']); ?></h4>
                <p class="mb-0"><i class="fas fa-shopping-bag me-2"></i>Total Orders</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h4><?php echo number_format($companyStats['total_users']); ?></h4>
                <p class="mb-0"><i class="fas fa-user-tie me-2"></i>Team Members</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h4><?php echo ucfirst($company['subscription_plan']); ?></h4>
                <p class="mb-0"><i class="fas fa-crown me-2"></i>Plan</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-4 mb-4">
        <!-- Company Logo -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">
                    <i class="fas fa-image me-2"></i>Company Logo
                </h5>
            </div>
            <div class="card-body text-center">
                <?php if (!empty($company['logo']) && file_exists($company['logo'])): ?>
                    <img src="<?php echo htmlspecialchars($company['logo']); ?>" 
                         alt="Company Logo" 
                         class="img-fluid mb-3" 
                         style="max-height: 200px;">
                <?php else: ?>
                    <div class="bg-light p-5 mb-3 rounded">
                        <i class="fas fa-building fa-4x text-muted"></i>
                    </div>
                    <p class="text-muted">No logo uploaded</p>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="file" 
                           class="form-control mb-2" 
                           name="logo" 
                           accept="image/*"
                           onchange="this.form.submit()">
                    <small class="text-muted">Upload PNG, JPG (Max 2MB)</small>
                </form>
            </div>
        </div>
        
        <!-- Subscription Info -->
        <div class="card mt-4">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">
                    <i class="fas fa-crown me-2"></i>Subscription
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <small class="text-muted d-block">Current Plan</small>
                    <h5 class="text-primary"><?php echo ucfirst($company['subscription_plan']); ?></h5>
                </div>
                <?php if ($company['subscription_expiry']): ?>
                <div class="mb-3">
                    <small class="text-muted d-block">Expires On</small>
                    <strong><?php echo format_date($company['subscription_expiry'], 'M j, Y'); ?></strong>
                </div>
                <?php endif; ?>
                <a href="subscriptions.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-arrow-up me-2"></i>Upgrade Plan
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <!-- Company Information Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-building me-2"></i>Business Information
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="company_name" class="form-label">Company Name *</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="company_name" 
                                   name="company_name" 
                                   value="<?php echo htmlspecialchars($company['company_name']); ?>" 
                                   required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="owner_name" class="form-label">Owner Name *</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="owner_name" 
                                   name="owner_name" 
                                   value="<?php echo htmlspecialchars($company['owner_name']); ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="business_email" class="form-label">Business Email *</label>
                            <div class="input-group">
                                <input type="email" 
                                       class="form-control" 
                                       id="business_email" 
                                       name="business_email" 
                                       value="<?php echo htmlspecialchars($currentEmail); ?>" 
                                       <?php echo $emailFieldDisabled ? 'readonly disabled' : ''; ?>
                                       required>
                                <?php if ($emailFieldDisabled): ?>
                                    <button type="button" 
                                            class="btn btn-outline-primary" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#emailChangeModal"
                                            id="requestEmailChangeBtn"
                                            <?php echo $hasPendingEmailRequest ? 'disabled' : ''; ?>>
                                        <i class="fas fa-edit me-1"></i>Request Change
                                    </button>
                                <?php endif; ?>
                            </div>
                            <?php if ($emailFieldDisabled): ?>
                                <small class="text-muted">Email cannot be changed directly. Click "Request Change" to submit a change request.</small>
                                <?php if ($hasPendingEmailRequest): ?>
                                    <div class="alert alert-info mt-2 mb-0 py-2">
                                        <i class="fas fa-info-circle me-2"></i>You have a pending email change request. Please wait for approval.
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <small class="text-muted">Enter your business email address.</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="business_phone" class="form-label">Business Phone *</label>
                            <div class="input-group">
                                <span class="input-group-text">+91</span>
                                <input type="tel" 
                                       class="form-control" 
                                       id="business_phone" 
                                       name="business_phone" 
                                       value="<?php 
                                           $phone = $company['business_phone'] ?? '';
                                           // Remove +91 prefix if present for display
                                           if (strpos($phone, '+91') === 0) {
                                               $phone = substr($phone, 3);
                                           }
                                           echo htmlspecialchars($phone); 
                                       ?>" 
                                       placeholder="10-digit mobile number"
                                       pattern="[0-9]{10}"
                                       maxlength="10"
                                       required>
                            </div>
                            <small class="text-muted">Enter 10-digit mobile number (digits only)</small>
                            <div class="invalid-feedback">Please enter a valid 10-digit phone number.</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="business_address" class="form-label">Business Address</label>
                        <textarea class="form-control" 
                                  id="business_address" 
                                  name="business_address" 
                                  rows="2"><?php echo htmlspecialchars($company['business_address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="city" class="form-label">City</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="city" 
                                   name="city" 
                                   placeholder="e.g., Mumbai, Delhi"
                                   value="<?php echo htmlspecialchars($company['city'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="state" class="form-label">State</label>
                            <select class="form-select" id="state" name="state">
                                <option value="">Select State</option>
                                <?php
                                $indianStates = ['Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal', 'Delhi'];
                                foreach ($indianStates as $state) {
                                    $selected = ($company['state'] ?? '') === $state ? 'selected' : '';
                                    echo "<option value=\"$state\" $selected>$state</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="postal_code" class="form-label">PIN Code</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="postal_code" 
                                   name="postal_code" 
                                   placeholder="e.g., 400001"
                                   maxlength="6"
                                   pattern="[0-9]{6}"
                                   value="<?php echo htmlspecialchars($company['postal_code'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="website" class="form-label">Website (Optional)</label>
                        <input type="url" 
                               class="form-control" 
                               id="website" 
                               name="website" 
                               placeholder="https://yourtailor.com"
                               value="<?php echo htmlspecialchars($company['website'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="tax_number" class="form-label">GST Number (Optional)</label>
                        <input type="text" 
                               class="form-control" 
                               id="tax_number" 
                               name="tax_number" 
                               placeholder="e.g., 27AAAAA0000A1Z5"
                               value="<?php echo htmlspecialchars($company['tax_number'] ?? ''); ?>">
                        <small class="text-muted">Enter your GSTIN if registered</small>
                    </div>
                    
                    <hr class="my-4">
                    
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
    </div>
</div>

<!-- Email Change Request Modal -->
<div class="modal fade" id="emailChangeModal" tabindex="-1" aria-labelledby="emailChangeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="emailChangeModalLabel">
                    <i class="fas fa-envelope me-2"></i>Request Email Change
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="emailChangeRequestForm">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Your email change request will be reviewed by an administrator. You will be notified once it's approved or rejected.
                    </div>
                    
                    <div class="mb-3">
                        <label for="current_email_display" class="form-label">Current Email</label>
                        <input type="text" 
                               class="form-control" 
                               id="current_email_display" 
                               value="<?php echo htmlspecialchars($currentEmail); ?>" 
                               readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_email" class="form-label">New Email Address *</label>
                        <input type="email" 
                               class="form-control" 
                               id="new_email" 
                               name="new_email" 
                               placeholder="Enter new email address"
                               required>
                        <small class="text-muted">Enter the new email address you want to use.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="change_reason" class="form-label">Reason for Change *</label>
                        <textarea class="form-control" 
                                  id="change_reason" 
                                  name="change_reason" 
                                  rows="4" 
                                  placeholder="Please provide a reason for changing your email address..."
                                  required></textarea>
                        <small class="text-muted">Explain why you need to change your email address.</small>
                    </div>
                    
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="company_id" value="<?php echo $companyId; ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitEmailChangeBtn">
                        <i class="fas fa-paper-plane me-2"></i>Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Setup phone validation
    setupPhoneValidation('business_phone', '+91');
    
    // Update phone value with prefix before form submission
    const companyForm = document.querySelector('form[method="POST"]');
    if (companyForm && document.getElementById('business_phone')) {
        companyForm.addEventListener('submit', function(e) {
            const phoneInput = document.getElementById('business_phone');
            const phoneValue = getPhoneWithPrefix('business_phone', '+91');
            
            if (!validatePhoneNumber('business_phone', '+91')) {
                e.preventDefault();
                phoneInput.focus();
                alert('Please enter a valid 10-digit phone number.');
                return false;
            }
            
            // Set the phone value with prefix for submission
            phoneInput.value = phoneValue;
        });
    }
    
    const emailChangeForm = document.getElementById('emailChangeRequestForm');
    const submitBtn = document.getElementById('submitEmailChangeBtn');
    
    if (emailChangeForm) {
        emailChangeForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
            
            fetch('ajax/request_email_change.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Track request email change event if provided in response (wait for gtag)
                    if (data.ga4_event) {
                        (function() {
                            var attempts = 0;
                            var maxAttempts = 50; // 5 seconds max wait time
                            var eventCode = data.ga4_event;
                            
                            function fireEmailChangeEvent() {
                                if (typeof gtag !== 'undefined' && typeof window.dataLayer !== 'undefined') {
                                    try {
                                        eval(eventCode);
                                        console.log('GA4 email change event fired successfully');
                                    } catch (e) {
                                        console.error('GA4 email change event tracking error:', e);
                                    }
                                } else {
                                    attempts++;
                                    if (attempts < maxAttempts) {
                                        setTimeout(fireEmailChangeEvent, 100);
                                    } else {
                                        console.warn('GA4 not loaded after 5 seconds, email change event may be lost');
                                    }
                                }
                            }
                            
                            fireEmailChangeEvent();
                        })();
                    }
                    
                    // Show success message
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success alert-dismissible fade show';
                    alertDiv.innerHTML = `
                        <i class="fas fa-check-circle me-2"></i>${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.querySelector('.card-body').insertBefore(alertDiv, document.querySelector('.card-body').firstChild);
                    
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('emailChangeModal'));
                    modal.hide();
                    
                    // Reset form
                    emailChangeForm.reset();
                    
                    // Reload page after 2 seconds to show pending request status
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    // Show error message
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                    alertDiv.innerHTML = `
                        <i class="fas fa-exclamation-triangle me-2"></i>${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.querySelector('#emailChangeModal .modal-body').insertBefore(alertDiv, document.querySelector('#emailChangeModal .modal-body').firstChild);
                    
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = submitBtnText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitBtnText;
            });
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>

