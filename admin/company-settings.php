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

$companyModel = new Company();
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
        
        $data = [
            'company_name' => sanitize_input($_POST['company_name']),
            'owner_name' => sanitize_input($_POST['owner_name']),
            'business_email' => sanitize_input($_POST['business_email']),
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
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h4><?php echo number_format($companyStats['total_customers']); ?></h4>
                <p class="mb-0"><i class="fas fa-users me-2"></i>Total Customers</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h4><?php echo number_format($companyStats['total_orders']); ?></h4>
                <p class="mb-0"><i class="fas fa-shopping-bag me-2"></i>Total Orders</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h4><?php echo number_format($companyStats['total_users']); ?></h4>
                <p class="mb-0"><i class="fas fa-user-tie me-2"></i>Team Members</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
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
                            <input type="email" 
                                   class="form-control" 
                                   id="business_email" 
                                   name="business_email" 
                                   value="<?php echo htmlspecialchars($company['business_email']); ?>" 
                                   required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="business_phone" class="form-label">Business Phone *</label>
                            <input type="tel" 
                                   class="form-control" 
                                   id="business_phone" 
                                   name="business_phone" 
                                   value="<?php echo htmlspecialchars($company['business_phone']); ?>" 
                                   required>
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

<?php require_once 'includes/footer.php'; ?>

