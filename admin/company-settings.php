<?php
require_once '../config/config.php';
require_login();
require_role('admin');

require_once 'models/Company.php';
require_once '../models/EmailChangeRequest.php';

$companyModel = new Company();
$emailChangeRequestModel = new EmailChangeRequest();
$message = '';
$messageType = '';
$companyId = get_company_id();

if (!$companyId) {
    die('No company associated with your account. Please contact support.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $existingCompany = $companyModel->find($companyId);
        $data = [
            'company_name' => sanitize_input($_POST['company_name'] ?? ($existingCompany['company_name'] ?? '')),
            'owner_name' => sanitize_input($_POST['owner_name'] ?? ($existingCompany['owner_name'] ?? '')),
            'business_email' => $existingCompany['business_email'], // Keep existing email - cannot be changed directly
            'business_phone' => sanitize_input($_POST['business_phone'] ?? ($existingCompany['business_phone'] ?? '')),
            'business_address' => sanitize_input($_POST['business_address'] ?? ($existingCompany['business_address'] ?? '')),
            'city' => sanitize_input($_POST['city'] ?? ($existingCompany['city'] ?? '')),
            'state' => sanitize_input($_POST['state'] ?? ($existingCompany['state'] ?? '')),
            'country' => 'India',
            'postal_code' => sanitize_input($_POST['postal_code'] ?? ($existingCompany['postal_code'] ?? '')),
            'tax_number' => sanitize_input($_POST['tax_number'] ?? ($existingCompany['tax_number'] ?? '')),
            'website' => sanitize_input($_POST['website'] ?? ($existingCompany['website'] ?? '')),
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata'
        ];
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/logos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $fileName = time() . '_' . basename($_FILES['logo']['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath)) {
                $data['logo'] = $targetPath;
            }
        }
        $mergedCompany = array_merge($existingCompany ?: [], $data);
        $data['show_on_listing'] = Company::isProfileComplete($mergedCompany) ? 1 : 0;

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
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['messageType']);
}
$page_title = 'Company Settings';
require_once 'includes/header.php';
$company = $companyModel->find($companyId);
if (!$company) {
    echo '<div class="alert alert-danger">Company not found</div>';
    require_once 'includes/footer.php';
    exit;
}
$companyStats = $companyModel->getCompanyStats($companyId);
$hasPendingEmailRequest = $emailChangeRequestModel->hasPendingRequest($companyId);
$recentRejectedRequest = $emailChangeRequestModel->hasRecentRejectedRequest($companyId, 7);
$currentEmail = $company['business_email'] ?? '';
$emailFieldDisabled = !empty($currentEmail);
$completionProgress = Company::calculateProfileCompletion($company);
$missingProfileFields = Company::getMissingProfileFields($company);
$missingFieldsText = '';
if (!empty($missingProfileFields)) {
    $fieldNames = array_values($missingProfileFields);
    if (count($fieldNames) > 1) {
        $lastField = array_pop($fieldNames);
        $missingFieldsText = implode(', ', $fieldNames) . ' and ' . $lastField;
    } else {
        $missingFieldsText = $fieldNames[0];
    }
}
$profileFields = [
    'business_address' => 'Address',
    'city'             => 'City',
    'state'            => 'State',
    'postal_code'      => 'PIN Code',
    'logo'             => 'Business Logo',
];
$fieldsDone = [];
foreach ($profileFields as $key => $label) {
    $fieldsDone[$key] = !empty(trim((string)($company[$key] ?? '')));
}
$prog = (int)$completionProgress;
$rDesk = 32;
$circDesk = round(2 * M_PI * $rDesk, 2);
$offsetDesk = round($circDesk - ($prog / 100) * $circDesk, 2);

$rMob = 19;
$circMob = round(2 * M_PI * $rMob, 2);
$offsetMob = round($circMob - ($prog / 100) * $circMob, 2);
?>
<style>
.cat-stat-box {
    border-radius: 14px;
    padding: 16px 18px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid transparent;
}
.cat-stat-box:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.06);
}
.welcome-banner {
    position: relative;
    min-height: 168px;
    border-radius: 16px;
    box-shadow: 0 8px 30px rgba(26, 35, 126, 0.35);
    margin-bottom: 1.25rem;
}
.wb-btn-clean {
    background: #ffffff;
    color: #2563eb;
    font-weight: 700;
    font-size: 0.88rem;
    padding: 9px 20px;
    border-radius: 12px;
    text-decoration: none;
    white-space: nowrap;
    box-shadow: 0 4px 14px rgba(0,0,0,0.18);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.wb-btn-clean:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.25);
    color: #1d4ed8;
}
.wb-pill-btn-mob {
    background: #ffffff;
    color: #2563eb;
    font-weight: 700;
    font-size: 0.78rem;
    padding: 6px 18px;
    border-radius: 999px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    box-shadow: 0 3px 10px rgba(0,0,0,0.18);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.wb-pill-btn-mob:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(0,0,0,0.25);
    color: #1d4ed8;
}
@media (max-width: 991.98px) {
    .welcome-banner {
        border-radius: 16px !important;
        min-height: auto !important;
        margin-bottom: 1rem !important;
    }
    .wb-bg-gradient {
        background: linear-gradient(90deg, 
            rgba(15, 23, 115, 0.98) 0%, 
            rgba(20, 30, 135, 0.92) 48%, 
            rgba(30, 40, 150, 0.60) 68%, 
            rgba(40, 50, 160, 0.22) 84%, 
            transparent 100%) !important;
    }
    .wb-content-pad {
        padding: 16px 14px 14px !important;
    }
}
.settings-card {
    border-radius: 16px;
    border: 1px solid #f1f5f9;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    overflow: hidden;
}
.settings-card-header {
    background-color: #fafafa;
    border-bottom: 1px solid #f1f5f9;
    padding: 14px 20px;
}
.settings-input {
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    font-size: 0.86rem;
    height: 42px;
    padding: 0.45rem 0.85rem;
    line-height: 1.4;
    background-color: #ffffff;
}
select.settings-input,
select.form-select.settings-input,
.form-select.settings-input {
    height: 42px !important;
    min-height: 42px !important;
    padding: 0.45rem 2.25rem 0.45rem 0.85rem !important;
    line-height: 1.4 !important;
    font-size: 0.86rem !important;
    background-position: right 0.75rem center !important;
    background-size: 16px 12px !important;
}
.settings-input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}
</style>
<div class="welcome-banner overflow-hidden position-relative">
    <div class="position-absolute top-0 start-0 w-100 h-100 wb-bg-img"
         style="background: url('../assets/images/dashboard/welcome_banner_bg.png') no-repeat right center / cover; z-index: 0;"></div>
    <div class="position-absolute top-0 start-0 w-100 h-100 wb-bg-gradient"
         style="background: linear-gradient(90deg, 
             rgba(15, 23, 115, 0.96) 0%, 
             rgba(24, 35, 145, 0.92) 26%, 
             rgba(35, 48, 175, 0.82) 46%, 
             rgba(58, 48, 175, 0.60) 65%, 
             rgba(70, 45, 168, 0.22) 80%, 
             transparent 92%); z-index: 1;"></div>
    <div class="position-relative text-white p-3 p-md-4 wb-content-pad" style="z-index: 2;">
        <div class="d-none d-lg-flex flex-column justify-content-between" style="min-height: 168px;">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div style="font-size: 0.84rem; color: rgba(255,255,255,0.85); margin-bottom: 2px;">Company Settings</div>
                    <div style="font-size: 1.85rem; font-weight: 800; line-height: 1.15; color: #ffffff; letter-spacing: -0.5px; margin-bottom: 4px;">
                        <?php echo htmlspecialchars($company['company_name'] ?: ($current_user['name'] ?? 'User')); ?> ⚙️
                    </div>
                    <div style="font-size: 0.78rem; color: rgba(255,255,255,0.82); line-height: 1.45; max-width: 380px;">
                        Manage your shop profile, logo, business details and settings to grow your tailoring business.
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <div class="position-relative flex-shrink-0" style="width: 72px; height: 72px;">
                        <svg width="72" height="72" viewBox="0 0 72 72">
                            <circle cx="36" cy="36" r="34" fill="rgba(15, 23, 42, 0.45)" />
                            <circle cx="36" cy="36" r="<?php echo $rDesk; ?>" fill="none" stroke="rgba(255,255,255,0.18)" stroke-width="6.5" />
                            <circle cx="36" cy="36" r="<?php echo $rDesk; ?>" fill="none"
                                    stroke="#00e676" stroke-width="6.5" stroke-linecap="round"
                                    stroke-dasharray="<?php echo $circDesk; ?>"
                                    stroke-dashoffset="<?php echo $offsetDesk; ?>"
                                    transform="rotate(-90 36 36)" />
                        </svg>
                        <div class="position-absolute top-50 start-50 translate-middle fw-bold text-white" style="font-size: 1.05rem;">
                            <?php echo $prog; ?>%
                        </div>
                    </div>
                    <div>
                        <div class="fw-bold text-white mb-1" style="font-size: 1.05rem; line-height: 1.2;">Profile Completed</div>
                        <div style="font-size: 0.74rem; color: rgba(255,255,255,0.82); line-height: 1.4; max-width: 250px;">
                            <?php if ($prog >= 100): ?>
                                Your shop profile is 100% complete and publicly active.
                            <?php else: ?>
                                Complete missing details below to activate your public shop listing.
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="pe-5 me-3">
                    <a href="#businessInfoForm" class="wb-btn-clean">
                        <i class="fas fa-edit me-1"></i>Edit Profile
                    </a>
                </div>
            </div>
            <div class="d-flex align-items-center justify-content-between gap-4 mt-3 pt-2">
                <div class="d-flex align-items-center flex-wrap" style="gap: 16px;">
                    <?php foreach ($profileFields as $key => $label): ?>
                        <?php if ($fieldsDone[$key]): ?>
                            <span class="d-inline-flex align-items-center gap-2" style="font-size: 0.76rem; color: #ffffff; font-weight: 500;">
                                <span style="width: 16px; height: 16px; border-radius: 50%; background: #00e676; display: inline-flex; align-items: center; justify-content: center; color: #ffffff; font-size: 9px; flex-shrink: 0;">
                                    <i class="fas fa-check"></i>
                                </span>
                                <?php echo $label; ?>
                            </span>
                        <?php else: ?>
                            <span class="d-inline-flex align-items-center gap-2" style="font-size: 0.76rem; color: rgba(255,255,255,0.85); font-weight: 500;">
                                <span style="width: 16px; height: 16px; border-radius: 50%; border: 2px solid rgba(255,255,255,0.55); background: rgba(255,255,255,0.18); display: inline-flex; flex-shrink: 0;"></span>
                                <?php echo $label; ?>
                            </span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <div class="d-flex align-items-center gap-3 pe-5 me-3" style="flex: 0 0 46%; max-width: 46%;">
                    <div style="flex: 1; height: 8px; background: rgba(255,255,255,0.22); border-radius: 10px; overflow: hidden;">
                        <div style="height: 100%; width: <?php echo $prog; ?>%; background: #00e676; border-radius: 10px; box-shadow: 0 0 10px rgba(0,230,118,0.7); transition: width 1.2s ease;"></div>
                    </div>
                    <span class="fw-bold text-white flex-shrink-0" style="font-size: 0.92rem; min-width: 34px;">
                        <?php echo $prog; ?>%
                    </span>
                </div>
            </div>
        </div>
        <div class="d-flex d-lg-none flex-column gap-2">
            <div>
                <div style="font-size: 0.76rem; color: rgba(255,255,255,0.85); margin-bottom: 2px;">Company Settings</div>
                <div style="font-size: 1.45rem; font-weight: 800; line-height: 1.15; color: #ffffff; letter-spacing: -0.3px; margin-bottom: 4px;">
                    <?php echo htmlspecialchars($company['company_name'] ?: ($current_user['name'] ?? 'User')); ?> ⚙️
                </div>
            </div>
            
            <div class="d-flex align-items-center gap-2 mt-1">
                <div class="position-relative flex-shrink-0" style="width: 46px; height: 46px;">
                    <svg width="46" height="46" viewBox="0 0 46 46">
                        <circle cx="23" cy="23" r="21" fill="rgba(15, 23, 42, 0.45)" />
                        <circle cx="23" cy="23" r="<?php echo $rMob; ?>" fill="none" stroke="rgba(255,255,255,0.18)" stroke-width="4.5" />
                        <circle cx="23" cy="23" r="<?php echo $rMob; ?>" fill="none"
                                stroke="#00e676" stroke-width="4.5" stroke-linecap="round"
                                stroke-dasharray="<?php echo $circMob; ?>"
                                stroke-dashoffset="<?php echo $offsetMob; ?>"
                                transform="rotate(-90 23 23)" />
                    </svg>
                    <div class="position-absolute top-50 start-50 translate-middle fw-bold text-white" style="font-size: 0.76rem;">
                        <?php echo $prog; ?>%
                    </div>
                </div>
                <div>
                    <div class="fw-bold text-white" style="font-size: 0.90rem; line-height: 1.2;">Profile Completed</div>
                    <div style="font-size: 0.67rem; color: rgba(255,255,255,0.82); line-height: 1.3;">
                        <?php if ($prog >= 100): ?>
                            100% complete and publicly active.
                        <?php else: ?>
                            Complete missing details to activate public listing.
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="mt-1">
                <a href="#businessInfoForm" class="wb-pill-btn-mob">
                    <i class="fas fa-edit me-1"></i> Edit Profile
                </a>
            </div>

            <div class="d-flex flex-column gap-1 mt-1 pt-1">
                <div class="d-flex align-items-center flex-wrap" style="gap: 12px;">
                    <?php 
                    $row1Fields = ['business_address' => 'Address', 'city' => 'City', 'state' => 'State'];
                    foreach ($row1Fields as $key => $label): 
                    ?>
                        <?php if ($fieldsDone[$key]): ?>
                            <span class="d-inline-flex align-items-center gap-1" style="font-size: 0.70rem; color: #ffffff; font-weight: 500;">
                                <span style="width: 13px; height: 13px; border-radius: 50%; background: #00e676; display: inline-flex; align-items: center; justify-content: center; color: #ffffff; font-size: 8px; flex-shrink: 0;">
                                    <i class="fas fa-check"></i>
                                </span>
                                <?php echo $label; ?>
                            </span>
                        <?php else: ?>
                            <span class="d-inline-flex align-items-center gap-1" style="font-size: 0.70rem; color: rgba(255,255,255,0.85); font-weight: 500;">
                                <span style="width: 13px; height: 13px; border-radius: 50%; border: 1.5px solid rgba(255,255,255,0.6); background: rgba(255,255,255,0.18); display: inline-flex; flex-shrink: 0;"></span>
                                <?php echo $label; ?>
                            </span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <div class="d-flex align-items-center flex-wrap" style="gap: 12px;">
                    <?php 
                    $row2Fields = ['postal_code' => 'PIN Code', 'logo' => 'Business Logo'];
                    foreach ($row2Fields as $key => $label): 
                    ?>
                        <?php if ($fieldsDone[$key]): ?>
                            <span class="d-inline-flex align-items-center gap-1" style="font-size: 0.70rem; color: #ffffff; font-weight: 500;">
                                <span style="width: 13px; height: 13px; border-radius: 50%; background: #00e676; display: inline-flex; align-items: center; justify-content: center; color: #ffffff; font-size: 8px; flex-shrink: 0;">
                                    <i class="fas fa-check"></i>
                                </span>
                                <?php echo $label; ?>
                            </span>
                        <?php else: ?>
                            <span class="d-inline-flex align-items-center gap-1" style="font-size: 0.70rem; color: rgba(255,255,255,0.85); font-weight: 500;">
                                <span style="width: 13px; height: 13px; border-radius: 50%; border: 1.5px solid rgba(255,255,255,0.6); background: rgba(255,255,255,0.18); display: inline-flex; flex-shrink: 0;"></span>
                                <?php echo $label; ?>
                            </span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show mb-3" style="border-radius: 12px;">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<div class="row g-2 mb-4">
    <div class="col-6 col-md-3">
        <div class="cat-stat-box h-100 d-flex align-items-center justify-content-between" style="background: #eff6ff; border: 1px solid #dbeafe;">
            <div>
                <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.5px;">TOTAL CUSTOMERS</span>
                <div class="fs-4 fw-bold text-dark mt-1"><?php echo number_format($companyStats['total_customers'] ?? 0); ?></div>
            </div>
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #dbeafe; color: #2563eb; font-size: 0.95rem;">
                <i class="fas fa-users"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="cat-stat-box h-100 d-flex align-items-center justify-content-between" style="background: #f0fdf4; border: 1px solid #dcfce7;">
            <div>
                <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.5px;">TOTAL ORDERS</span>
                <div class="fs-4 fw-bold text-dark mt-1"><?php echo number_format($companyStats['total_orders'] ?? 0); ?></div>
            </div>
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #dcfce7; color: #16a34a; font-size: 0.95rem;">
                <i class="fas fa-shopping-bag"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="cat-stat-box h-100 d-flex align-items-center justify-content-between" style="background: #faf5ff; border: 1px solid #ede9fe;">
            <div>
                <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.5px;">TEAM MEMBERS</span>
                <div class="fs-4 fw-bold text-dark mt-1"><?php echo number_format($companyStats['total_users'] ?? 0); ?></div>
            </div>
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #ede9fe; color: #9333ea; font-size: 0.95rem;">
                <i class="fas fa-user-tie"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="cat-stat-box h-100 d-flex align-items-center justify-content-between" style="background: #fffbeb; border: 1px solid #fef3c7;">
            <div>
                <span class="text-secondary text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.5px;">SUBSCRIPTION PLAN</span>
                <div class="fs-4 fw-bold text-dark mt-1" style="font-size: 1.15rem;"><?php echo ucfirst($company['subscription_plan'] ?? 'Free'); ?></div>
            </div>
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px; border-radius: 10px; background: #fef3c7; color: #d97706; font-size: 0.95rem;">
                <i class="fas fa-crown"></i>
            </div>
        </div>
    </div>
</div>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card settings-card mb-3">
            <div class="settings-card-header d-flex align-items-center justify-content-between">
                <span class="fw-bold text-dark" style="font-size: 0.92rem;">
                    <i class="fas fa-image text-primary me-2"></i>Company Logo
                </span>
                <span class="badge bg-light text-muted border" style="font-size: 0.72rem;">Branding</span>
            </div>
            <div class="card-body p-4 text-center">
                <?php if (!empty($company['logo']) && file_exists($company['logo'])): ?>
                    <div class="p-3 mb-3 bg-white rounded-3 shadow-sm border d-inline-block" style="max-width: 100%;">
                        <img src="<?php echo htmlspecialchars($company['logo']); ?>" 
                             alt="Company Logo" 
                             class="img-fluid" 
                             style="max-height: 150px; max-width: 100%; object-fit: contain;">
                    </div>
                <?php else: ?>
                    <div class="d-inline-flex align-items-center justify-content-center mb-3" style="width: 100px; height: 100px; border-radius: 20px; background: #f8fafc; border: 2px dashed #cbd5e1; color: #94a3b8;">
                        <i class="fas fa-building fa-3x"></i>
                    </div>
                    <p class="text-muted small mb-3">No logo uploaded yet</p>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="mb-2">
                        <label for="logoUploadInput" class="btn btn-outline-primary btn-sm w-100 py-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-2" style="border-radius: 12px; cursor: pointer;">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Choose New Logo</span>
                        </label>
                        <input type="file" 
                               id="logoUploadInput"
                               class="d-none" 
                               name="logo" 
                               accept="image/*"
                               onchange="this.form.submit()">
                    </div>
                    <small class="text-muted d-block" style="font-size: 0.74rem;">PNG, JPG, WEBP (Max 2MB)</small>
                </form>
            </div>
        </div>
        <div class="card settings-card">
            <div class="settings-card-header d-flex align-items-center justify-content-between">
                <span class="fw-bold text-dark" style="font-size: 0.92rem;">
                    <i class="fas fa-crown text-warning me-2"></i>Subscription & Status
                </span>
                <span class="badge rounded-pill fw-semibold px-2 py-1" style="background: #fef3c7; color: #b45309; border: 1px solid #fde68a; font-size: 0.72rem;">
                    <?php echo ucfirst($company['subscription_plan'] ?? 'Free'); ?>
                </span>
            </div>
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom">
                    <div>
                        <small class="text-muted d-block" style="font-size: 0.74rem;">CURRENT PLAN</small>
                        <div class="fw-bold text-dark fs-5 mt-1"><?php echo ucfirst($company['subscription_plan'] ?? 'Free'); ?></div>
                    </div>
                    <div class="d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; border-radius: 12px; background: #fffbeb; color: #d97706; font-size: 1.1rem;">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                </div>

                <?php if ($company['subscription_expiry']): ?>
                <div class="mb-3 pb-3 border-bottom">
                    <small class="text-muted d-block" style="font-size: 0.74rem;">PLAN EXPIRES ON</small>
                    <div class="fw-semibold text-dark mt-1" style="font-size: 0.88rem;">
                        <i class="fas fa-calendar-alt text-muted me-1"></i>
                        <?php echo format_date($company['subscription_expiry'], 'M j, Y'); ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="mb-4">
                    <small class="text-muted d-block mb-1" style="font-size: 0.74rem;">PUBLIC SHOP LISTING</small>
                    <?php if (Company::isProfileComplete($company)): ?>
                        <span class="badge rounded-pill fw-semibold px-3 py-2" style="background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; font-size: 0.78rem;">
                            <i class="fas fa-check-circle me-1"></i>Active on Public Listing
                        </span>
                    <?php else: ?>
                        <span class="badge rounded-pill fw-semibold px-3 py-2" style="background: #fffbeb; color: #b45309; border: 1px solid #fde68a; font-size: 0.78rem;">
                            <i class="fas fa-exclamation-circle me-1"></i>Incomplete Profile
                        </span>
                        <div class="text-muted mt-2" style="font-size: 0.73rem; line-height: 1.4;">
                            Complete missing info to activate listing.
                        </div>
                    <?php endif; ?>
                </div>

                <a href="subscriptions.php" class="btn text-white fw-semibold w-100 py-2 d-inline-flex align-items-center justify-content-center gap-2" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 12px; font-size: 0.85rem; border: none; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);">
                    <i class="fas fa-arrow-up"></i>
                    <span>Upgrade / Manage Plan</span>
                </a>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card settings-card" id="businessInfoForm">
            <div class="settings-card-header d-flex align-items-center justify-content-between">
                <span class="fw-bold text-dark" style="font-size: 0.92rem;">
                    <i class="fas fa-building text-primary me-2"></i>Business Information
                </span>
                <span class="text-muted small">Fields with * are required</span>
            </div>
            <div class="card-body p-4">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="company_name" class="form-label fw-semibold text-muted small mb-1">Company Name *</label>
                            <input type="text" 
                                   class="form-control settings-input" 
                                   id="company_name" 
                                   name="company_name" 
                                   value="<?php echo htmlspecialchars($company['company_name'] ?? ''); ?>" 
                                   required>
                        </div>
                        <div class="col-md-6">
                            <label for="owner_name" class="form-label fw-semibold text-muted small mb-1">Owner Name *</label>
                            <input type="text" 
                                   class="form-control settings-input" 
                                   id="owner_name" 
                                   name="owner_name" 
                                   value="<?php echo htmlspecialchars($company['owner_name'] ?? ''); ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="business_email" class="form-label fw-semibold text-muted small mb-1">Business Email *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0" style="border-radius: 12px 0 0 12px; height: 42px;">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" 
                                       class="form-control" 
                                       id="business_email" 
                                       name="business_email" 
                                       value="<?php echo htmlspecialchars($currentEmail); ?>" 
                                       <?php echo $emailFieldDisabled ? 'readonly disabled' : ''; ?>
                                       style="height: 42px; font-size: 0.86rem; border-color: #e2e8f0; <?php echo !$emailFieldDisabled ? 'border-radius: 0 12px 12px 0;' : ''; ?>"
                                       required>
                                <?php if ($emailFieldDisabled): ?>
                                    <button type="button" 
                                            class="btn btn-outline-primary px-3 fw-medium" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#emailChangeModal"
                                            id="requestEmailChangeBtn"
                                            style="border-radius: 0 12px 12px 0; height: 42px; font-size: 0.82rem;"
                                            <?php echo ($hasPendingEmailRequest || $recentRejectedRequest) ? 'disabled' : ''; ?>>
                                        <i class="fas fa-edit me-1"></i>Request Change
                                    </button>
                                <?php endif; ?>
                            </div>
                            <?php if ($emailFieldDisabled): ?>
                                <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">Email cannot be edited directly. Click "Request Change" to submit a change request.</small>
                                <?php if ($hasPendingEmailRequest): ?>
                                    <div class="alert alert-info mt-2 mb-0 py-2 px-3 d-flex align-items-center" style="border-radius: 10px; font-size: 0.8rem;">
                                        <i class="fas fa-clock me-2"></i>You have a pending email change request. Please wait for approval.
                                    </div>
                                <?php elseif ($recentRejectedRequest): ?>
                                    <div class="alert alert-danger mt-2 mb-0 py-2 px-3 d-flex align-items-center" style="border-radius: 10px; font-size: 0.8rem;">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Your email change request was rejected. You can submit a new request after 7 days.
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">Enter your official business email address.</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="business_phone" class="form-label fw-semibold text-muted small mb-1">Business Phone *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted fw-semibold" style="border-radius: 12px 0 0 12px; height: 42px; font-size: 0.86rem; border-color: #e2e8f0;">+91</span>
                                <input type="tel" 
                                       class="form-control" 
                                       id="business_phone" 
                                       name="business_phone" 
                                       value="<?php 
                                           $phone = $company['business_phone'] ?? '';
                                           if (strpos($phone, '+91') === 0) {
                                               $phone = substr($phone, 3);
                                           }
                                           echo htmlspecialchars($phone); 
                                       ?>" 
                                       placeholder="10-digit mobile number"
                                       pattern="[0-9]{10}"
                                       maxlength="10"
                                       style="border-radius: 0 12px 12px 0; height: 42px; font-size: 0.86rem; border-color: #e2e8f0;"
                                       required>
                            </div>
                            <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">Enter 10-digit mobile number (digits only)</small>
                            <div class="invalid-feedback">Please enter a valid 10-digit phone number.</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="business_address" class="form-label fw-semibold text-muted small mb-1">Business Address</label>
                        <textarea class="form-control" 
                                  id="business_address" 
                                  name="business_address" 
                                  rows="2" 
                                  placeholder="Shop number, street, landmark..."
                                  style="border-radius: 12px; border-color: #e2e8f0; font-size: 0.86rem;"><?php echo htmlspecialchars($company['business_address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="city" class="form-label fw-semibold text-muted small mb-1">City</label>
                            <input type="text" 
                                   class="form-control settings-input" 
                                   id="city" 
                                   name="city" 
                                   placeholder="e.g., Surat, Ahmedabad"
                                   value="<?php echo htmlspecialchars($company['city'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="state" class="form-label fw-semibold text-muted small mb-1">State</label>
                            <select class="form-select settings-input" id="state" name="state">
                                <option value="">Select State</option>
                                <?php
                                $indianStates = ['Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal', 'Delhi'];
                                foreach ($indianStates as $st) {
                                    $selected = ($company['state'] ?? '') === $st ? 'selected' : '';
                                    echo "<option value=\"$st\" $selected>$st</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="postal_code" class="form-label fw-semibold text-muted small mb-1">PIN Code</label>
                            <input type="text" 
                                   class="form-control settings-input" 
                                   id="postal_code" 
                                   name="postal_code" 
                                   placeholder="e.g., 395006"
                                   maxlength="6"
                                   pattern="[0-9]{6}"
                                   value="<?php echo htmlspecialchars($company['postal_code'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="website" class="form-label fw-semibold text-muted small mb-1">Website (Optional)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0" style="border-radius: 12px 0 0 12px; height: 42px;">
                                    <i class="fas fa-globe"></i>
                                </span>
                                <input type="url" 
                                       class="form-control" 
                                       id="website" 
                                       name="website" 
                                       placeholder="https://yourtailorshop.com"
                                       value="<?php echo htmlspecialchars($company['website'] ?? ''); ?>"
                                       style="border-radius: 0 12px 12px 0; height: 42px; font-size: 0.86rem; border-color: #e2e8f0;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="tax_number" class="form-label fw-semibold text-muted small mb-1">GST Number (Optional)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0" style="border-radius: 12px 0 0 12px; height: 42px;">
                                    <i class="fas fa-file-invoice"></i>
                                </span>
                                <input type="text" 
                                       class="form-control" 
                                       id="tax_number" 
                                       name="tax_number" 
                                       placeholder="e.g., 24AAAAA0000A1Z5"
                                       value="<?php echo htmlspecialchars($company['tax_number'] ?? ''); ?>"
                                       style="border-radius: 0 12px 12px 0; height: 42px; font-size: 0.86rem; border-color: #e2e8f0;">
                            </div>
                            <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">Enter your GSTIN if registered</small>
                        </div>
                    </div>
                    
                    <hr class="my-4" style="border-color: #f1f5f9;">
                    
                    <div class="d-flex align-items-center justify-content-end gap-2">
                        <a href="dashboard.php" class="btn btn-light fw-medium px-4" style="border-radius: 12px; height: 42px; font-size: 0.86rem; display: inline-flex; align-items: center;">
                            Cancel
                        </a>
                        <button type="submit" class="btn text-white fw-semibold px-4 shadow-sm" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 12px; height: 42px; font-size: 0.86rem; border: none; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.28); display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fas fa-save"></i>
                            <span>Save Changes</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="emailChangeModal" tabindex="-1" aria-labelledby="emailChangeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header border-bottom py-3" style="background: #fafafa;">
                <h5 class="modal-title fw-bold text-dark" id="emailChangeModalLabel" style="font-size: 1rem;">
                    <i class="fas fa-envelope text-primary me-2"></i>Request Email Change
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="emailChangeRequestForm">
                <div class="modal-body p-4">
                    <div class="alert alert-info py-2 px-3 d-flex align-items-start mb-3" style="border-radius: 12px; font-size: 0.82rem;">
                        <i class="fas fa-info-circle me-2 mt-1"></i>
                        <span>Your email change request will be reviewed by an administrator. You will be notified once approved or rejected.</span>
                    </div>
                    
                    <div class="mb-3">
                        <label for="current_email_display" class="form-label fw-semibold text-muted small mb-1">Current Email</label>
                        <input type="text" 
                               class="form-control" 
                               id="current_email_display" 
                               value="<?php echo htmlspecialchars($currentEmail); ?>" 
                               style="border-radius: 10px; background: #f8fafc; height: 42px; font-size: 0.86rem;"
                               readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_email" class="form-label fw-semibold text-muted small mb-1">New Email Address *</label>
                        <input type="email" 
                               class="form-control" 
                               id="new_email" 
                               name="new_email" 
                               placeholder="Enter new email address"
                               style="border-radius: 10px; height: 42px; font-size: 0.86rem;"
                               required>
                        <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">Enter the new email address you want to use.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="change_reason" class="form-label fw-semibold text-muted small mb-1">Reason for Change *</label>
                        <textarea class="form-control" 
                                  id="change_reason" 
                                  name="change_reason" 
                                  rows="3" 
                                  placeholder="Please provide a reason for changing your email address..."
                                  style="border-radius: 10px; font-size: 0.86rem;"
                                  required></textarea>
                        <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">Explain why you need to change your email address.</small>
                    </div>
                    
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="company_id" value="<?php echo $companyId; ?>">
                </div>
                <div class="modal-footer border-top py-3" style="background: #fafafa;">
                    <button type="button" class="btn btn-light fw-medium px-4" data-bs-dismiss="modal" style="border-radius: 10px; font-size: 0.85rem;">
                        Cancel
                    </button>
                    <button type="submit" class="btn text-white fw-semibold px-4" id="submitEmailChangeBtn" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 10px; font-size: 0.85rem; border: none; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.28);">
                        <i class="fas fa-paper-plane me-1"></i>Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    setupPhoneValidation('business_phone', '+91');
    const phoneInputEl = document.getElementById('business_phone');
    const companyForm = phoneInputEl ? phoneInputEl.closest('form') : null;
    if (companyForm && phoneInputEl) {
        companyForm.addEventListener('submit', function(e) {
            const phoneInput = document.getElementById('business_phone');
            const phoneValue = getPhoneWithPrefix('business_phone', '+91');
            
            if (!validatePhoneNumber('business_phone', '+91')) {
                e.preventDefault();
                phoneInput.focus();
                alert('Please enter a valid 10-digit phone number.');
                return false;
            }
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
                    if (data.ga4_event) {
                        (function() {
                            var attempts = 0;
                            var maxAttempts = 50;
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
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success alert-dismissible fade show';
                    alertDiv.innerHTML = `
                        <i class="fas fa-check-circle me-2"></i>${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    const containerCard = document.querySelector('#businessInfoForm .card-body');
                    if (containerCard) {
                        containerCard.insertBefore(alertDiv, containerCard.firstChild);
                    }
                    const modalEl = document.getElementById('emailChangeModal');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                    emailChangeForm.reset();
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
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