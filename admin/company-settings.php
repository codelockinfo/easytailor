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

$indianStates = [
    'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh',
    'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand',
    'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur',
    'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab',
    'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura',
    'Uttar Pradesh', 'Uttarakhand', 'West Bengal', 'Delhi'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $existingCompany = $companyModel->find($companyId);
        $errors = [];
        $actionType = $_POST['action_type'] ?? '';

        // 1. Handle Logo Upload
        if ($actionType === 'upload_logo' || (isset($_FILES['logo']) && !isset($_POST['company_name']))) {
            if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Please select a valid image file to upload.';
            } else {
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                
                if (!in_array($ext, $allowedExtensions)) {
                    $errors[] = 'Invalid file format. Allowed formats: PNG, JPG, JPEG, and WEBP.';
                } elseif ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
                    $errors[] = 'Logo file size exceeds the maximum limit of 2MB.';
                } else {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $_FILES['logo']['tmp_name']);
                    finfo_close($finfo);
                    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
                    if (!in_array($mime, $allowedMimes)) {
                        $errors[] = 'Invalid image file content.';
                    }
                }
            }

            if (empty($errors)) {
                $uploadDir = '../uploads/logos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $fileName = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath)) {
                    $data = ['logo' => $targetPath];
                    $merged = array_merge($existingCompany ?: [], $data);
                    $data['show_on_listing'] = Company::isProfileComplete($merged) ? 1 : 0;
                    
                    if ($companyModel->update($companyId, $data)) {
                        $_SESSION['message'] = 'Company logo updated successfully!';
                        $_SESSION['messageType'] = 'success';
                    } else {
                        $_SESSION['message'] = 'Failed to update company logo.';
                        $_SESSION['messageType'] = 'danger';
                    }
                } else {
                    $_SESSION['message'] = 'Failed to save uploaded logo file.';
                    $_SESSION['messageType'] = 'danger';
                }
            } else {
                $_SESSION['message'] = implode('<br>', $errors);
                $_SESSION['messageType'] = 'danger';
            }
            header('Location: company-settings.php');
            exit;
        }

        // 2. Handle Business Information Form
        $companyName = trim(sanitize_input($_POST['company_name'] ?? ''));
        $ownerName = trim(sanitize_input($_POST['owner_name'] ?? ''));
        $rawPhone = trim(sanitize_input($_POST['business_phone'] ?? ''));
        $businessAddress = trim(sanitize_input($_POST['business_address'] ?? ''));
        $city = trim(sanitize_input($_POST['city'] ?? ''));
        $state = trim(sanitize_input($_POST['state'] ?? ''));
        $postalCode = trim(sanitize_input($_POST['postal_code'] ?? ''));
        $taxNumber = strtoupper(trim(sanitize_input($_POST['tax_number'] ?? '')));
        $website = trim(sanitize_input($_POST['website'] ?? ''));

        // Company Name validation
        if (empty($companyName)) {
            $errors[] = 'Company Name is required.';
        } elseif (mb_strlen($companyName) < 2 || mb_strlen($companyName) > 100) {
            $errors[] = 'Company Name must be between 2 and 100 characters.';
        }

        // Owner Name validation
        if (empty($ownerName)) {
            $errors[] = 'Owner Name is required.';
        } elseif (mb_strlen($ownerName) < 2 || mb_strlen($ownerName) > 60) {
            $errors[] = 'Owner Name must be between 2 and 60 characters.';
        } elseif (!preg_match("/^[a-zA-Z\s\.\p{L}]+$/u", $ownerName)) {
            $errors[] = 'Owner Name can only contain letters, spaces, and dots.';
        }

        // Phone validation (10-digit Indian mobile starting with 6,7,8,9)
        $cleanPhone = preg_replace('/\D/', '', $rawPhone);
        if (strpos($cleanPhone, '91') === 0 && strlen($cleanPhone) === 12) {
            $cleanPhone = substr($cleanPhone, 2);
        }
        if (empty($cleanPhone)) {
            $errors[] = 'Business Phone number is required.';
        } elseif (!preg_match('/^[6-9]\d{9}$/', $cleanPhone)) {
            $errors[] = 'Please enter a valid 10-digit Indian mobile number (starting with 6, 7, 8, or 9).';
        }
        $formattedPhone = '+91' . $cleanPhone;

        // Address validation (Required)
        if (empty($businessAddress)) {
            $errors[] = 'Business Address is required.';
        } elseif (mb_strlen($businessAddress) < 5 || mb_strlen($businessAddress) > 250) {
            $errors[] = 'Business Address must be between 5 and 250 characters.';
        }

        // City validation (Required)
        if (empty($city)) {
            $errors[] = 'City is required.';
        } elseif (mb_strlen($city) < 2 || mb_strlen($city) > 50) {
            $errors[] = 'City must be between 2 and 50 characters.';
        } elseif (!preg_match("/^[a-zA-Z\s\-\.\p{L}]+$/u", $city)) {
            $errors[] = 'City can only contain letters and spaces.';
        }

        // State validation (Required)
        if (empty($state)) {
            $errors[] = 'State is required. Please select your State.';
        } elseif (!in_array($state, $indianStates)) {
            $errors[] = 'Please select a valid State from the list.';
        }

        // PIN Code validation (Required)
        if (empty($postalCode)) {
            $errors[] = 'PIN Code is required.';
        } elseif (!preg_match('/^[1-9][0-9]{5}$/', $postalCode)) {
            $errors[] = 'PIN Code must be a valid 6-digit Indian PIN Code (e.g., 395006).';
        }

        $hasExistingLogo = Company::hasCustomLogo($existingCompany);

        if (!empty($website)) {
            if (!preg_match('/^https?:\/\//i', $website)) {
                $website = 'https://' . $website;
            }
            if (!filter_var($website, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}/i', $website)) {
                $errors[] = 'Please enter a valid Website URL (e.g., https://yourtailorshop.com).';
            }
        }

        if (!empty($taxNumber)) {
            if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $taxNumber)) {
                $errors[] = 'GST Number must be a valid 15-character GSTIN (e.g., 24AAAAA0000A1Z5).';
            }
        }

        $daysList = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $whData = [];
        $whClosed = $_POST['wh_closed'] ?? [];
        $whOpen = $_POST['wh_open'] ?? [];
        $whClose = $_POST['wh_close'] ?? [];

        foreach ($daysList as $day) {
            if (!empty($whClosed[$day])) {
                $whData[$day] = 'Closed';
            } else {
                $openTime = trim((string)($whOpen[$day] ?? '10:00'));
                $closeTime = trim((string)($whClose[$day] ?? '20:00'));
                if (!empty($openTime) && !empty($closeTime)) {
                    if (strtotime($openTime) >= strtotime($closeTime)) {
                        $errors[] = ucfirst($day) . ': Opening time must be earlier than closing time.';
                    }
                    $openFormatted = date('g:i A', strtotime($openTime));
                    $closeFormatted = date('g:i A', strtotime($closeTime));
                    $whData[$day] = "$openFormatted - $closeFormatted";
                } elseif (!empty($openTime)) {
                    $whData[$day] = date('g:i A', strtotime($openTime));
                } else {
                    $whData[$day] = '10:00 AM - 8:00 PM';
                }
            }
        }

        if (empty($errors)) {
            $data = [
                'company_name' => $companyName,
                'owner_name' => $ownerName,
                'business_phone' => $formattedPhone,
                'business_address' => $businessAddress,
                'city' => $city,
                'state' => $state,
                'country' => 'India',
                'postal_code' => $postalCode,
                'tax_number' => $taxNumber,
                'website' => $website,
                'currency' => 'INR',
                'timezone' => 'Asia/Kolkata',
                'working_hours' => json_encode($whData, JSON_UNESCAPED_UNICODE)
            ];

            $mergedCompany = array_merge($existingCompany ?: [], $data);
            $data['show_on_listing'] = Company::isProfileComplete($mergedCompany) ? 1 : 0;

            if ($companyModel->update($companyId, $data)) {
                $_SESSION['message'] = 'Company settings updated successfully!';
                $_SESSION['messageType'] = 'success';
            } else {
                $_SESSION['message'] = 'Failed to update company settings. Please try again.';
                $_SESSION['messageType'] = 'danger';
            }
        } else {
            $_SESSION['message'] = implode('<br>', $errors);
            $_SESSION['messageType'] = 'danger';
        }
        header('Location: company-settings.php#businessInfoForm');
        exit;
    } else {
        $_SESSION['message'] = 'Invalid request or session expired. Please try again.';
        $_SESSION['messageType'] = 'danger';
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
    if ($key === 'logo') {
        $fieldsDone[$key] = Company::hasCustomLogo($company);
    } else {
        $fieldsDone[$key] = !empty(trim((string)($company[$key] ?? '')));
    }
}
$prog = (int)$completionProgress;
$rDesk = 32;
$circDesk = round(2 * M_PI * $rDesk, 2);
$offsetDesk = round($circDesk - ($prog / 100) * $circDesk, 2);

$rMob = 19;
$circMob = round(2 * M_PI * $rMob, 2);
$offsetMob = round($circMob - ($prog / 100) * $circMob, 2);
$companyWorkingHours = [];
if (!empty($company['working_hours'])) {
    $decodedWh = json_decode($company['working_hours'], true);
    if (is_array($decodedWh)) {
        foreach ($decodedWh as $k => $v) {
            $companyWorkingHours[strtolower($k)] = $v;
        }
    }
}

$daysOfWeek = [
    'monday'    => 'Monday',
    'tuesday'   => 'Tuesday',
    'wednesday' => 'Wednesday',
    'thursday'  => 'Thursday',
    'friday'    => 'Friday',
    'saturday'  => 'Saturday',
    'sunday'    => 'Sunday',
];
?>
<style>
.cat-stat-box {
    border-radius: 14px;
    padding: 12px 14px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid transparent;
}
.cat-stat-box:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.06);
}
@media (max-width: 767.98px) {
    .cat-stat-box {
        border-radius: 12px !important;
        padding: 9px 10px !important;
    }
    .cat-stat-box .cs-stat-label {
        font-size: 0.62rem !important;
        letter-spacing: 0.2px !important;
        line-height: 1.2 !important;
    }
    .cat-stat-box .cs-stat-value {
        font-size: 1.05rem !important;
        margin-top: 2px !important;
        line-height: 1.2 !important;
    }
    .cat-stat-box .cs-stat-icon {
        width: 30px !important;
        height: 30px !important;
        border-radius: 8px !important;
        font-size: 0.80rem !important;
    }
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
.wh-day-row {
    background: #ffffff;
    border: 1px solid #f1f5f9;
    border-radius: 12px;
    padding: 10px 14px;
    transition: all 0.2s ease;
}
.wh-day-row:hover {
    border-color: #e2e8f0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}
.wh-day-row.is-closed {
    background: #f8fafc;
    border-color: #f1f5f9;
}
.wh-day-badge {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    font-size: 0.76rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.wh-time-input {
    height: 38px;
    border-radius: 8px;
    font-size: 0.82rem;
    border: 1px solid #e2e8f0;
    padding: 0.35rem 0.65rem;
    background-color: #ffffff;
}
.wh-time-input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.12);
}
.wh-day-col {
    min-width: 125px;
}
@media (max-width: 767.98px) {
    .wh-day-row {
        flex-wrap: nowrap !important;
        padding: 8px 10px !important;
        gap: 6px !important;
    }
    .wh-day-col {
        min-width: auto !important;
        flex-shrink: 0;
    }
    .wh-day-badge {
        width: 34px;
        height: 28px;
        border-radius: 8px;
        font-size: 0.68rem;
    }
    .wh-time-controls {
        flex: 1 1 0%;
        min-width: 0;
        width: auto !important;
        gap: 4px !important;
        justify-content: center !important;
    }
    .wh-time-pair {
        flex: 1 1 0%;
        min-width: 0;
    }
    .wh-time-input {
        width: 100% !important;
        min-width: 0 !important;
        height: 32px !important;
        padding: 2px 4px !important;
        font-size: 0.74rem !important;
    }
    .wh-closed-msg {
        font-size: 0.74rem !important;
    }
    .wh-switch-wrap {
        margin-left: 0 !important;
        flex-shrink: 0;
        padding-left: 2.15em !important;
    }
    .wh-switch-wrap .form-check-label {
        font-size: 0.70rem !important;
    }
    .wh-preset-btns {
        width: 100%;
    }
    .wh-preset-btns .btn {
        font-size: 0.69rem !important;
        padding: 5px 8px !important;
        white-space: nowrap;
    }
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
                        <?php echo htmlspecialchars($company['company_name'] ?: ($current_user['name'] ?? 'User')); ?>
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
                            <circle id="profileProgressCircle" cx="36" cy="36" r="<?php echo $rDesk; ?>" fill="none"
                                    stroke="#00e676" stroke-width="6.5" stroke-linecap="round"
                                    stroke-dasharray="<?php echo $circDesk; ?>"
                                    stroke-dashoffset="<?php echo $offsetDesk; ?>"
                                    style="transition: stroke-dashoffset 0.8s ease;"
                                    transform="rotate(-90 36 36)" />
                        </svg>
                        <div class="position-absolute top-50 start-50 translate-middle fw-bold text-white" id="profileProgressText" style="font-size: 1.05rem;">
                            <?php echo $prog; ?>%
                        </div>
                    </div>
                    <div>
                        <div class="fw-bold text-white mb-1" style="font-size: 1.05rem; line-height: 1.2;">Profile Completed</div>
                        <div id="profileProgressSubtext" style="font-size: 0.74rem; color: rgba(255,255,255,0.82); line-height: 1.4; max-width: 250px;">
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
                    <?php 
                    $pillTargets = [
                        'business_address' => '#business_address',
                        'city'             => '#city',
                        'state'            => '#state',
                        'postal_code'      => '#postal_code',
                        'logo'             => '#companyLogoCard'
                    ];
                    foreach ($profileFields as $key => $label): 
                    ?>
                        <span class="d-inline-flex align-items-center gap-2 profile-pill-clickable" 
                              id="pill_<?php echo $key; ?>" 
                              onclick="scrollToProfileField('<?php echo $pillTargets[$key]; ?>')"
                              style="font-size: 0.76rem; color: #ffffff; font-weight: 500; cursor: pointer; transition: transform 0.2s ease, opacity 0.2s;"
                              title="Click to edit <?php echo $label; ?>">
                            <?php if ($fieldsDone[$key]): ?>
                                <span class="pill-icon" style="width: 16px; height: 16px; border-radius: 50%; background: #00e676; display: inline-flex; align-items: center; justify-content: center; color: #ffffff; font-size: 9px; flex-shrink: 0;">
                                    <i class="fas fa-check"></i>
                                </span>
                            <?php else: ?>
                                <span class="pill-icon" style="width: 16px; height: 16px; border-radius: 50%; border: 2px solid rgba(255,255,255,0.55); background: rgba(255,255,255,0.18); display: inline-flex; flex-shrink: 0;"></span>
                            <?php endif; ?>
                            <span class="pill-text" style="<?php echo $fieldsDone[$key] ? '' : 'color: rgba(255,255,255,0.85);'; ?>"><?php echo $label; ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>

                <div class="d-flex align-items-center gap-3 pe-5 me-3" id="profileProgressBarWrapper" style="flex: 0 0 46%; max-width: 46%;">
                    <div style="flex: 1; height: 8px; background: rgba(255,255,255,0.22); border-radius: 10px; overflow: hidden;">
                        <div id="profileProgressBarFill" style="height: 100%; width: <?php echo $prog; ?>%; background: #00e676; border-radius: 10px; box-shadow: 0 0 10px rgba(0,230,118,0.7); transition: width 0.6s ease;"></div>
                    </div>
                    <span id="profileProgressBarText" class="fw-bold text-white flex-shrink-0" style="font-size: 0.92rem; min-width: 38px;">
                        <?php echo $prog; ?>%
                    </span>
                </div>
            </div>
        </div>
        <div class="d-flex d-lg-none flex-column gap-2">
            <div>
                <div style="font-size: 0.76rem; color: rgba(255,255,255,0.85); margin-bottom: 2px;">Company Settings</div>
                <div style="font-size: 1.45rem; font-weight: 800; line-height: 1.15; color: #ffffff; letter-spacing: -0.3px; margin-bottom: 4px;">
                    <?php echo htmlspecialchars($company['company_name'] ?: ($current_user['name'] ?? 'User')); ?>
                </div>
            </div>
            
            <div class="d-flex align-items-center gap-2 mt-1">
                <div class="position-relative flex-shrink-0" style="width: 46px; height: 46px;">
                    <svg width="46" height="46" viewBox="0 0 46 46">
                        <circle cx="23" cy="23" r="21" fill="rgba(15, 23, 42, 0.45)" />
                        <circle cx="23" cy="23" r="<?php echo $rMob; ?>" fill="none" stroke="rgba(255,255,255,0.18)" stroke-width="4.5" />
                        <circle id="profileProgressCircleMob" cx="23" cy="23" r="<?php echo $rMob; ?>" fill="none"
                                stroke="#00e676" stroke-width="4.5" stroke-linecap="round"
                                stroke-dasharray="<?php echo $circMob; ?>"
                                stroke-dashoffset="<?php echo $offsetMob; ?>"
                                style="transition: stroke-dashoffset 0.8s ease;"
                                transform="rotate(-90 23 23)" />
                    </svg>
                    <div class="position-absolute top-50 start-50 translate-middle fw-bold text-white" id="profileProgressTextMob" style="font-size: 0.76rem;">
                        <?php echo $prog; ?>%
                    </div>
                </div>
                <div>
                    <div class="fw-bold text-white" style="font-size: 0.90rem; line-height: 1.2;">Profile Completed</div>
                    <div id="profileProgressSubtextMob" style="font-size: 0.67rem; color: rgba(255,255,255,0.82); line-height: 1.3;">
                        <?php if ($prog >= 100): ?>
                            100% complete and publicly active.
                        <?php else: ?>
                            Complete missing details to activate public listing.
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 mt-1">
                <div style="flex: 1; height: 6px; background: rgba(255,255,255,0.22); border-radius: 10px; overflow: hidden;">
                    <div id="profileProgressBarFillMob" style="height: 100%; width: <?php echo $prog; ?>%; background: #00e676; border-radius: 10px; transition: width 0.6s ease;"></div>
                </div>
                <span id="profileProgressBarTextMob" class="fw-bold text-white" style="font-size: 0.75rem;">
                    <?php echo $prog; ?>%
                </span>
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
                        <span class="d-inline-flex align-items-center gap-1 profile-pill-clickable" 
                              id="pill_mob_<?php echo $key; ?>" 
                              onclick="scrollToProfileField('<?php echo $pillTargets[$key]; ?>')"
                              style="font-size: 0.70rem; color: #ffffff; font-weight: 500; cursor: pointer;">
                            <?php if ($fieldsDone[$key]): ?>
                                <span class="pill-icon" style="width: 13px; height: 13px; border-radius: 50%; background: #00e676; display: inline-flex; align-items: center; justify-content: center; color: #ffffff; font-size: 8px; flex-shrink: 0;">
                                    <i class="fas fa-check"></i>
                                </span>
                            <?php else: ?>
                                <span class="pill-icon" style="width: 13px; height: 13px; border-radius: 50%; border: 1.5px solid rgba(255,255,255,0.6); background: rgba(255,255,255,0.18); display: inline-flex; flex-shrink: 0;"></span>
                            <?php endif; ?>
                            <span class="pill-text" style="<?php echo $fieldsDone[$key] ? '' : 'color: rgba(255,255,255,0.85);'; ?>"><?php echo $label; ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>

                <div class="d-flex align-items-center flex-wrap" style="gap: 12px;">
                    <?php 
                    $row2Fields = ['postal_code' => 'PIN Code', 'logo' => 'Business Logo'];
                    foreach ($row2Fields as $key => $label): 
                    ?>
                        <span class="d-inline-flex align-items-center gap-1 profile-pill-clickable" 
                              id="pill_mob_<?php echo $key; ?>" 
                              onclick="scrollToProfileField('<?php echo $pillTargets[$key]; ?>')"
                              style="font-size: 0.70rem; color: #ffffff; font-weight: 500; cursor: pointer;">
                            <?php if ($fieldsDone[$key]): ?>
                                <span class="pill-icon" style="width: 13px; height: 13px; border-radius: 50%; background: #00e676; display: inline-flex; align-items: center; justify-content: center; color: #ffffff; font-size: 8px; flex-shrink: 0;">
                                    <i class="fas fa-check"></i>
                                </span>
                            <?php else: ?>
                                <span class="pill-icon" style="width: 13px; height: 13px; border-radius: 50%; border: 1.5px solid rgba(255,255,255,0.6); background: rgba(255,255,255,0.18); display: inline-flex; flex-shrink: 0;"></span>
                            <?php endif; ?>
                            <span class="pill-text" style="<?php echo $fieldsDone[$key] ? '' : 'color: rgba(255,255,255,0.85);'; ?>"><?php echo $label; ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show mb-3" style="border-radius: 12px;">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<div class="row g-2 mb-3 mb-md-4">
    <div class="col-6 col-md-3">
        <div class="cat-stat-box h-100 d-flex align-items-center justify-content-between gap-2" style="background: #eff6ff; border: 1px solid #dbeafe;">
            <div class="min-w-0">
                <span class="text-secondary text-uppercase fw-semibold cs-stat-label d-block" style="font-size: 0.70rem; letter-spacing: 0.4px;">TOTAL CUSTOMERS</span>
                <div class="fs-5 fw-bold text-dark mt-1 cs-stat-value"><?php echo number_format($companyStats['total_customers'] ?? 0); ?></div>
            </div>
            <div class="d-flex align-items-center justify-content-center flex-shrink-0 cs-stat-icon" style="width: 34px; height: 34px; border-radius: 9px; background: #dbeafe; color: #2563eb; font-size: 0.9rem;">
                <i class="fas fa-users"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="cat-stat-box h-100 d-flex align-items-center justify-content-between gap-2" style="background: #f0fdf4; border: 1px solid #dcfce7;">
            <div class="min-w-0">
                <span class="text-secondary text-uppercase fw-semibold cs-stat-label d-block" style="font-size: 0.70rem; letter-spacing: 0.4px;">TOTAL ORDERS</span>
                <div class="fs-5 fw-bold text-dark mt-1 cs-stat-value"><?php echo number_format($companyStats['total_orders'] ?? 0); ?></div>
            </div>
            <div class="d-flex align-items-center justify-content-center flex-shrink-0 cs-stat-icon" style="width: 34px; height: 34px; border-radius: 9px; background: #dcfce7; color: #16a34a; font-size: 0.9rem;">
                <i class="fas fa-shopping-bag"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="cat-stat-box h-100 d-flex align-items-center justify-content-between gap-2" style="background: #faf5ff; border: 1px solid #ede9fe;">
            <div class="min-w-0">
                <span class="text-secondary text-uppercase fw-semibold cs-stat-label d-block" style="font-size: 0.70rem; letter-spacing: 0.4px;">TEAM MEMBERS</span>
                <div class="fs-5 fw-bold text-dark mt-1 cs-stat-value"><?php echo number_format($companyStats['total_users'] ?? 0); ?></div>
            </div>
            <div class="d-flex align-items-center justify-content-center flex-shrink-0 cs-stat-icon" style="width: 34px; height: 34px; border-radius: 9px; background: #ede9fe; color: #9333ea; font-size: 0.9rem;">
                <i class="fas fa-user-tie"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="cat-stat-box h-100 d-flex align-items-center justify-content-between gap-2" style="background: #fffbeb; border: 1px solid #fef3c7;">
            <div class="min-w-0">
                <span class="text-secondary text-uppercase fw-semibold cs-stat-label d-block" style="font-size: 0.70rem; letter-spacing: 0.4px;">SUBSCRIPTION PLAN</span>
                <div class="fs-5 fw-bold text-dark mt-1 cs-stat-value"><?php echo ucfirst($company['subscription_plan'] ?? 'Free'); ?></div>
            </div>
            <div class="d-flex align-items-center justify-content-center flex-shrink-0 cs-stat-icon" style="width: 34px; height: 34px; border-radius: 9px; background: #fef3c7; color: #d97706; font-size: 0.9rem;">
                <i class="fas fa-crown"></i>
            </div>
        </div>
    </div>
</div>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card settings-card mb-3" id="companyLogoCard">
            <div class="settings-card-header d-flex align-items-center justify-content-between">
                <span class="fw-bold text-dark" style="font-size: 0.92rem;">
                    <i class="fas fa-image text-primary me-2"></i>Company Logo *
                </span>
                <span class="badge <?php echo Company::hasCustomLogo($company) ? 'bg-light text-muted border' : 'bg-danger-subtle text-danger border border-danger-subtle'; ?>" style="font-size: 0.72rem;">
                    <?php echo Company::hasCustomLogo($company) ? 'Branding' : 'Required *'; ?>
                </span>
            </div>
            <div class="card-body p-4 text-center" id="companyLogoCardBody">
                <?php if (Company::hasCustomLogo($company)): ?>
                    <div class="p-3 mb-3 bg-white rounded-3 shadow-sm border d-inline-block" style="max-width: 100%;">
                        <img src="<?php echo htmlspecialchars(Company::getLogoUrl($company, true)); ?>" 
                             alt="Company Logo" 
                             class="img-fluid" 
                             style="max-height: 150px; max-width: 100%; object-fit: contain;">
                    </div>
                <?php else: ?>
                    <div class="d-inline-flex align-items-center justify-content-center mb-3" id="noLogoBox" style="width: 100px; height: 100px; border-radius: 20px; background: #fff5f5; border: 2px dashed #f87171; color: #ef4444;">
                        <i class="fas fa-building fa-3x"></i>
                    </div>
                    <p class="text-danger small mb-3 fw-semibold" id="noLogoText"><i class="fas fa-exclamation-circle me-1"></i>No logo uploaded yet (Required *)</p>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data" id="logoUploadForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action_type" value="upload_logo">
                    <div class="mb-2">
                        <label for="logoUploadInput" class="btn btn-outline-primary btn-sm w-100 py-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-2" style="border-radius: 12px; cursor: pointer;">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span id="logoBtnLabel"><?php echo Company::hasCustomLogo($company) ? 'Choose New Logo' : 'Upload Logo *'; ?></span>
                        </label>
                        <input type="file" 
                               id="logoUploadInput"
                               class="d-none" 
                               name="logo" 
                               accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
                    </div>
                    <div id="logoValidationMsg" class="alert alert-danger py-2 px-3 small d-none text-start" style="font-size: 0.76rem; border-radius: 8px;"></div>
                    <small class="text-muted d-block" style="font-size: 0.74rem;">PNG, JPG, WEBP (Max 2MB) &bull; <span class="text-danger fw-semibold">Required *</span></small>
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

                <?php if ($company['subscription_expiry'] && ($company['subscription_plan'] ?? '') !== 'free'): ?>
                <div class="mb-3 pb-3 border-bottom">
                    <small class="text-muted d-block" style="font-size: 0.74rem;">PLAN EXPIRES ON</small>
                    <div class="fw-semibold text-dark mt-1" style="font-size: 0.88rem;">
                        <i class="fas fa-calendar-alt text-muted me-1"></i>
                        <?php echo format_date($company['subscription_expiry'], 'M j, Y'); ?>
                    </div>
                </div>
                <?php elseif (($company['subscription_plan'] ?? '') === 'free'): ?>
                <div class="mb-3 pb-3 border-bottom">
                    <small class="text-muted d-block" style="font-size: 0.74rem;">PLAN DURATION</small>
                    <div class="fw-semibold text-dark mt-1" style="font-size: 0.88rem;">
                        <i class="fas fa-infinity text-muted me-1"></i>
                        Lifetime Access
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
            <div class="card-body p-3 p-md-4">
                <form method="POST" enctype="multipart/form-data" id="businessInfoActualForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action_type" value="update_business_info">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="company_name" class="form-label fw-semibold text-muted small mb-1">Company Name *</label>
                            <input type="text" 
                                   class="form-control settings-input" 
                                   id="company_name" 
                                   name="company_name" 
                                   value="<?php echo htmlspecialchars($company['company_name'] ?? ''); ?>" 
                                   placeholder="e.g., Isha's Tailor Shop"
                                   minlength="2"
                                   maxlength="100"
                                   required>
                            <div class="invalid-feedback">Company name is required (2 to 100 characters).</div>
                        </div>
                        <div class="col-md-6">
                            <label for="owner_name" class="form-label fw-semibold text-muted small mb-1">Owner Name *</label>
                            <input type="text" 
                                   class="form-control settings-input" 
                                   id="owner_name" 
                                   name="owner_name" 
                                   value="<?php echo htmlspecialchars($company['owner_name'] ?? ''); ?>" 
                                   placeholder="e.g., Isha Patel"
                                   minlength="2"
                                   maxlength="60"
                                   required>
                            <div class="invalid-feedback">Owner name can only contain letters, spaces, and dots (2 to 60 chars).</div>
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
                            <div class="input-group has-validation">
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
                                       pattern="[6-9][0-9]{9}"
                                       maxlength="10"
                                       inputmode="numeric"
                                       style="border-radius: 0 12px 12px 0; height: 42px; font-size: 0.86rem; border-color: #e2e8f0;"
                                       required>
                                <div class="invalid-feedback" id="phoneInvalidFeedback">Please enter a valid 10-digit mobile number (starts with 6, 7, 8, or 9).</div>
                            </div>
                            <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">Enter 10-digit mobile number (digits only)</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="business_address" class="form-label fw-semibold text-muted small mb-1">Business Address *</label>
                        <textarea class="form-control" 
                                  id="business_address" 
                                  name="business_address" 
                                  rows="2" 
                                  minlength="5"
                                  maxlength="250"
                                  placeholder="Shop number, street, landmark..."
                                  style="border-radius: 12px; border-color: #e2e8f0; font-size: 0.86rem;"
                                  required><?php echo htmlspecialchars($company['business_address'] ?? ''); ?></textarea>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <div class="invalid-feedback mb-0" id="addressInvalidFeedback" style="display:none;">Business Address is required (5 to 250 characters).</div>
                            <small class="text-muted ms-auto" id="addressCharCount" style="font-size: 0.72rem;">0/250</small>
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="city" class="form-label fw-semibold text-muted small mb-1">City *</label>
                            <input type="text" 
                                   class="form-control settings-input" 
                                   id="city" 
                                   name="city" 
                                   minlength="2"
                                   maxlength="50"
                                   placeholder="e.g., Surat, Ahmedabad"
                                   value="<?php echo htmlspecialchars($company['city'] ?? ''); ?>"
                                   required>
                            <div class="invalid-feedback">City is required (2 to 50 characters, letters only).</div>
                        </div>
                        <div class="col-md-4">
                            <label for="state" class="form-label fw-semibold text-muted small mb-1">State *</label>
                            <select class="form-select settings-input" id="state" name="state" required>
                                <option value="">Select State</option>
                                <?php
                                foreach ($indianStates as $st) {
                                    $selected = ($company['state'] ?? '') === $st ? 'selected' : '';
                                    echo "<option value=\"$st\" $selected>$st</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">State is required. Please select your State.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="postal_code" class="form-label fw-semibold text-muted small mb-1">PIN Code *</label>
                            <input type="text" 
                                   class="form-control settings-input" 
                                   id="postal_code" 
                                   name="postal_code" 
                                   placeholder="e.g., 395006"
                                   maxlength="6"
                                   pattern="[1-9][0-9]{5}"
                                   inputmode="numeric"
                                   value="<?php echo htmlspecialchars($company['postal_code'] ?? ''); ?>"
                                   required>
                            <div class="invalid-feedback">PIN code is required (6-digit number, cannot start with 0).</div>
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="website" class="form-label fw-semibold text-muted small mb-1">Website (Optional)</label>
                            <div class="input-group has-validation">
                                <span class="input-group-text bg-light text-muted border-end-0" style="border-radius: 12px 0 0 12px; height: 42px;">
                                    <i class="fas fa-globe"></i>
                                </span>
                                <input type="text" 
                                       class="form-control" 
                                       id="website" 
                                       name="website" 
                                       placeholder="https://yourtailorshop.com"
                                       value="<?php echo htmlspecialchars($company['website'] ?? ''); ?>"
                                       style="border-radius: 0 12px 12px 0; height: 42px; font-size: 0.86rem; border-color: #e2e8f0;">
                                <div class="invalid-feedback">Please enter a valid website URL (e.g., https://yourtailorshop.com).</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="tax_number" class="form-label fw-semibold text-muted small mb-1">GST Number (Optional)</label>
                            <div class="input-group has-validation">
                                <span class="input-group-text bg-light text-muted border-end-0" style="border-radius: 12px 0 0 12px; height: 42px;">
                                    <i class="fas fa-file-invoice"></i>
                                </span>
                                <input type="text" 
                                       class="form-control text-uppercase" 
                                       id="tax_number" 
                                       name="tax_number" 
                                       placeholder="e.g., 24AAAAA0000A1Z5"
                                       maxlength="15"
                                       value="<?php echo htmlspecialchars($company['tax_number'] ?? ''); ?>"
                                       style="border-radius: 0 12px 12px 0; height: 42px; font-size: 0.86rem; border-color: #e2e8f0;">
                                <div class="invalid-feedback">Invalid GSTIN format (15 characters, e.g., 24AAAAA0000A1Z5).</div>
                            </div>
                            <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">Enter your 15-character GSTIN if registered</small>
                        </div>
                    </div>
                    
                    <hr class="my-4" style="border-color: #e2e8f0;">

                    <!-- SECTION: Working Hours -->
                    <div class="mb-4" id="workingHoursSection">
                        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                            <div>
                                <h6 class="fw-bold text-dark mb-1" style="font-size: 0.95rem;">
                                    <i class="fas fa-calendar-alt text-primary me-2"></i>Working Hours 
                                </h6>
                                <small class="text-muted" style="font-size: 0.76rem;">Set shop opening & closing hours for each day of the week.</small>
                            </div>
                            <div class="d-flex align-items-center flex-wrap gap-2 wh-preset-btns">
                                <button type="button" class="btn btn-sm btn-outline-primary fw-medium" id="btnQuickMonSat" style="border-radius: 8px; font-size: 0.75rem; padding: 5px 12px;">
                                    <i class="fas fa-bolt me-1"></i> Mon-Sat 10AM-8PM (Sun Closed)
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary fw-medium" id="btnCopyMonday" style="border-radius: 8px; font-size: 0.75rem; padding: 5px 12px;">
                                    <i class="fas fa-copy me-1"></i> Copy Mon to All
                                </button>
                            </div>
                        </div>
                        <div id="whValidationAlert" class="alert alert-danger py-2 px-3 small d-none mb-3" style="border-radius: 8px;"></div>

                        <!-- Days Schedule Rows -->
                        <div class="d-flex flex-column gap-2">
                            <?php foreach ($daysOfWeek as $dayKey => $dayName): 
                                $dayHoursRaw = $companyWorkingHours[$dayKey] ?? ($dayKey === 'sunday' ? 'Closed' : '10:00 AM - 8:00 PM');
                                $dayClosed = (empty($dayHoursRaw) || stripos($dayHoursRaw, 'closed') !== false);
                                $dayOpenTime = '10:00';
                                $dayCloseTime = '20:00';

                                if (!$dayClosed) {
                                    $timeParts = explode('-', $dayHoursRaw);
                                    $t1 = trim($timeParts[0] ?? '');
                                    $t2 = trim($timeParts[1] ?? '');
                                    if (!empty($t1) && strtotime($t1)) {
                                        $dayOpenTime = date('H:i', strtotime($t1));
                                    }
                                    if (!empty($t2) && strtotime($t2)) {
                                        $dayCloseTime = date('H:i', strtotime($t2));
                                    }
                                }
                            ?>
                                <div class="wh-day-row d-flex align-items-center justify-content-between flex-md-wrap gap-2 <?php echo $dayClosed ? 'is-closed' : ''; ?>" id="row_wh_<?php echo $dayKey; ?>">
                                    <!-- Day Name & Badge -->
                                    <div class="d-flex align-items-center gap-2 wh-day-col">
                                        <div class="wh-day-badge" style="background: <?php echo $dayKey === 'sunday' ? '#fee2e2; color: #dc2626;' : '#ede9fe; color: #6366f1;'; ?>">
                                            <span class="d-md-none"><?php echo strtoupper(substr($dayName, 0, 3)); ?></span>
                                            <span class="d-none d-md-inline"><?php echo strtoupper(substr($dayName, 0, 2)); ?></span>
                                        </div>
                                        <div class="d-none d-md-block">
                                            <span class="fw-semibold text-dark" style="font-size: 0.86rem;"><?php echo $dayName; ?></span>
                                            <?php if ($dayKey === 'sunday'): ?>
                                                <small class="text-muted d-block" style="font-size: 0.68rem;">Weekend</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Time Inputs (Open & Close) -->
                                    <div class="d-flex align-items-center gap-2 flex-grow-1 justify-content-md-center wh-time-controls" id="time_controls_<?php echo $dayKey; ?>" style="<?php echo $dayClosed ? 'display: none !important;' : ''; ?>">
                                        <div class="d-flex align-items-center gap-1 wh-time-pair">
                                            <span class="text-muted small d-none d-md-inline" style="font-size: 0.72rem;">From:</span>
                                            <input type="time" 
                                                   name="wh_open[<?php echo $dayKey; ?>]" 
                                                   class="form-control form-control-sm wh-time-input" 
                                                   value="<?php echo htmlspecialchars($dayOpenTime); ?>" 
                                                   style="width: 125px;"
                                                   <?php echo $dayClosed ? 'disabled' : ''; ?>>
                                        </div>
                                        <span class="text-muted small px-0 px-md-1">-</span>
                                        <div class="d-flex align-items-center gap-1 wh-time-pair">
                                            <span class="text-muted small d-none d-md-inline" style="font-size: 0.72rem;">To:</span>
                                            <input type="time" 
                                                   name="wh_close[<?php echo $dayKey; ?>]" 
                                                   class="form-control form-control-sm wh-time-input" 
                                                   value="<?php echo htmlspecialchars($dayCloseTime); ?>" 
                                                   style="width: 125px;"
                                                   <?php echo $dayClosed ? 'disabled' : ''; ?>>
                                        </div>
                                    </div>

                                    <!-- Shop Closed Notice -->
                                    <div class="wh-closed-msg text-danger fw-semibold small flex-grow-1 text-center" id="closed_msg_<?php echo $dayKey; ?>" style="<?php echo !$dayClosed ? 'display: none !important;' : ''; ?> font-size: 0.82rem;">
                                        <i class="fas fa-ban me-1"></i> Closed
                                    </div>

                                    <!-- Closed Switch Toggle -->
                                    <div class="form-check form-switch m-0 ms-auto wh-switch-wrap">
                                        <input class="form-check-input wh-closed-switch" 
                                               type="checkbox" 
                                               role="switch" 
                                               id="wh_closed_<?php echo $dayKey; ?>" 
                                               name="wh_closed[<?php echo $dayKey; ?>]" 
                                               value="1" 
                                               data-day="<?php echo $dayKey; ?>"
                                               <?php echo $dayClosed ? 'checked' : ''; ?>>
                                        <label class="form-check-label small fw-semibold" for="wh_closed_<?php echo $dayKey; ?>" style="font-size: 0.78rem; color: #64748b; cursor: pointer;">Closed</label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <hr class="my-4" style="border-color: #f1f5f9;">
                    
                    <div class="d-flex align-items-center justify-content-end gap-2">
                        <a href="dashboard.php" class="btn btn-light fw-medium px-4" style="border-radius: 12px; height: 42px; font-size: 0.86rem; display: inline-flex; align-items: center;">
                            Cancel
                        </a>
                        <button type="submit" id="btnSaveBusinessInfo" class="btn text-white fw-semibold px-4 shadow-sm" style="background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 12px; height: 42px; font-size: 0.86rem; border: none; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.28); display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fas fa-save"></i>
                            <span id="btnSaveBusinessInfoText">Save Changes</span>
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
window.scrollToProfileField = function(targetSelector) {
    const el = document.querySelector(targetSelector);
    if (!el) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    if (typeof el.focus === 'function') {
        el.focus();
    }
    const highlightTarget = el.classList.contains('card') ? el : (el.closest('.mb-3') || el);
    highlightTarget.style.transition = 'box-shadow 0.3s ease, border-color 0.3s ease';
    highlightTarget.style.boxShadow = '0 0 0 4px rgba(99, 102, 241, 0.4)';
    highlightTarget.style.borderColor = '#6366f1';
    setTimeout(() => {
        highlightTarget.style.boxShadow = '';
        highlightTarget.style.borderColor = '';
    }, 2000);
};

document.addEventListener('DOMContentLoaded', function() {
    // -------------------------------------------------------------
    // 1. Logo File Upload Validation
    // -------------------------------------------------------------
    const logoInput = document.getElementById('logoUploadInput');
    const logoForm = document.getElementById('logoUploadForm');
    const logoMsg = document.getElementById('logoValidationMsg');
    const logoBtnLabel = document.getElementById('logoBtnLabel');

    if (logoInput && logoForm) {
        logoInput.addEventListener('change', function() {
            if (!this.files || !this.files[0]) return;
            const file = this.files[0];
            const allowedExts = ['png', 'jpg', 'jpeg', 'webp'];
            const fileExt = file.name.split('.').pop().toLowerCase();
            const allowedMimes = ['image/png', 'image/jpeg', 'image/webp'];

            if (!allowedExts.includes(fileExt) || (file.type && !allowedMimes.includes(file.type))) {
                if (logoMsg) {
                    logoMsg.textContent = 'Invalid file format. Allowed formats: PNG, JPG, JPEG, and WEBP.';
                    logoMsg.classList.remove('d-none');
                }
                this.value = '';
                return;
            }

            // Max 2MB
            if (file.size > 2 * 1024 * 1024) {
                if (logoMsg) {
                    const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
                    logoMsg.textContent = `Logo file size exceeds the 2MB limit (Selected: ${sizeMB} MB).`;
                    logoMsg.classList.remove('d-none');
                }
                this.value = '';
                return;
            }

            if (logoMsg) {
                logoMsg.classList.add('d-none');
                logoMsg.textContent = '';
            }

            if (logoBtnLabel) {
                logoBtnLabel.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Uploading...';
            }
            if (typeof updateLiveProfileProgress === 'function') {
                updateLiveProfileProgress();
            }
            logoForm.submit();
        });
    }

    // -------------------------------------------------------------
    // 2. Business Information Form Real-time Validation
    // -------------------------------------------------------------
    const companyNameInput = document.getElementById('company_name');
    const ownerNameInput = document.getElementById('owner_name');
    const phoneInput = document.getElementById('business_phone');
    const addressInput = document.getElementById('business_address');
    const cityInput = document.getElementById('city');
    const stateInput = document.getElementById('state');
    const postalCodeInput = document.getElementById('postal_code');
    const websiteInput = document.getElementById('website');
    const taxNumberInput = document.getElementById('tax_number');
    const addressCharCount = document.getElementById('addressCharCount');
    const addressFeedback = document.getElementById('addressInvalidFeedback');

    function validateCompanyName() {
        if (!companyNameInput) return true;
        const val = companyNameInput.value.trim();
        if (val.length < 2 || val.length > 100) {
            companyNameInput.classList.add('is-invalid');
            companyNameInput.classList.remove('is-valid');
            return false;
        } else {
            companyNameInput.classList.remove('is-invalid');
            companyNameInput.classList.add('is-valid');
            return true;
        }
    }

    function validateOwnerName() {
        if (!ownerNameInput) return true;
        const val = ownerNameInput.value.trim();
        const namePattern = /^[a-zA-Z\s\.]+$/;
        if (val.length < 2 || val.length > 60 || !namePattern.test(val)) {
            ownerNameInput.classList.add('is-invalid');
            ownerNameInput.classList.remove('is-valid');
            return false;
        } else {
            ownerNameInput.classList.remove('is-invalid');
            ownerNameInput.classList.add('is-valid');
            return true;
        }
    }

    function validatePhone() {
        if (!phoneInput) return true;
        let val = phoneInput.value.replace(/[^0-9]/g, '');
        if (val.length === 12 && val.startsWith('91')) {
            val = val.substring(2);
        }
        phoneInput.value = val.slice(0, 10);
        
        const phonePattern = /^[6-9][0-9]{9}$/;
        if (!phonePattern.test(phoneInput.value)) {
            phoneInput.classList.add('is-invalid');
            phoneInput.classList.remove('is-valid');
            return false;
        } else {
            phoneInput.classList.remove('is-invalid');
            phoneInput.classList.add('is-valid');
            return true;
        }
    }

    function validateAddress() {
        if (!addressInput) return true;
        const val = addressInput.value.trim();
        const len = addressInput.value.length;
        if (addressCharCount) {
            addressCharCount.textContent = `${len}/250`;
        }
        if (val.length < 5 || len > 250) {
            addressInput.classList.add('is-invalid');
            addressInput.classList.remove('is-valid');
            if (addressFeedback) {
                addressFeedback.textContent = val.length === 0 ? 'Business Address is required.' : (val.length < 5 ? 'Business Address must be at least 5 characters.' : 'Address cannot exceed 250 characters.');
                addressFeedback.style.display = 'block';
            }
            if (addressCharCount && len > 250) addressCharCount.classList.add('text-danger');
            return false;
        } else {
            addressInput.classList.remove('is-invalid');
            addressInput.classList.add('is-valid');
            if (addressFeedback) addressFeedback.style.display = 'none';
            if (addressCharCount) addressCharCount.classList.remove('text-danger');
            return true;
        }
    }

    function validateCity() {
        if (!cityInput) return true;
        const val = cityInput.value.trim();
        const cityPattern = /^[a-zA-Z\s\-]+$/;
        if (val.length < 2 || val.length > 50 || !cityPattern.test(val)) {
            cityInput.classList.add('is-invalid');
            cityInput.classList.remove('is-valid');
            return false;
        } else {
            cityInput.classList.remove('is-invalid');
            cityInput.classList.add('is-valid');
            return true;
        }
    }

    function validateState() {
        if (!stateInput) return true;
        if (stateInput.value === '') {
            stateInput.classList.add('is-invalid');
            stateInput.classList.remove('is-valid');
            return false;
        } else {
            stateInput.classList.remove('is-invalid');
            stateInput.classList.add('is-valid');
            return true;
        }
    }

    function validatePostalCode() {
        if (!postalCodeInput) return true;
        postalCodeInput.value = postalCodeInput.value.replace(/[^0-9]/g, '').slice(0, 6);
        const val = postalCodeInput.value.trim();
        const pinPattern = /^[1-9][0-9]{5}$/;
        if (!pinPattern.test(val)) {
            postalCodeInput.classList.add('is-invalid');
            postalCodeInput.classList.remove('is-valid');
            return false;
        } else {
            postalCodeInput.classList.remove('is-invalid');
            postalCodeInput.classList.add('is-valid');
            return true;
        }
    }

    function validateCompanyLogo() {
        const hasExisting = <?php echo (!empty($company['logo']) && file_exists($company['logo'])) ? 'true' : 'false'; ?>;
        const logoInput = document.getElementById('logoUploadInput');
        const hasSelected = logoInput && logoInput.files && logoInput.files.length > 0;
        const logoMsg = document.getElementById('logoValidationMsg');
        const noLogoBox = document.getElementById('noLogoBox');

        if (!hasExisting && !hasSelected) {
            if (logoMsg) {
                logoMsg.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i> Company Logo is required. Please upload your logo.';
                logoMsg.classList.remove('d-none');
            }
            if (noLogoBox) {
                noLogoBox.style.borderColor = '#ef4444';
                noLogoBox.style.background = '#fff5f5';
                noLogoBox.style.color = '#ef4444';
            }
            return false;
        } else {
            if (logoMsg && !hasSelected) {
                logoMsg.classList.add('d-none');
            }
            return true;
        }
    }

    function validateWebsite() {
        if (!websiteInput) return true;
        let val = websiteInput.value.trim();
        if (val === '') {
            websiteInput.classList.remove('is-invalid', 'is-valid');
            return true;
        }
        const urlPattern = /^(https?:\/\/)?([a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,})(\/[^\s]*)?$/i;
        if (!urlPattern.test(val)) {
            websiteInput.classList.add('is-invalid');
            websiteInput.classList.remove('is-valid');
            return false;
        } else {
            websiteInput.classList.remove('is-invalid');
            websiteInput.classList.add('is-valid');
            return true;
        }
    }

    function validateTaxNumber() {
        if (!taxNumberInput) return true;
        taxNumberInput.value = taxNumberInput.value.toUpperCase().replace(/[^0-9A-Z]/g, '').slice(0, 15);
        const val = taxNumberInput.value.trim();
        if (val === '') {
            taxNumberInput.classList.remove('is-invalid', 'is-valid');
            return true;
        }
        const gstPattern = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/;
        if (!gstPattern.test(val)) {
            taxNumberInput.classList.add('is-invalid');
            taxNumberInput.classList.remove('is-valid');
            return false;
        } else {
            taxNumberInput.classList.remove('is-invalid');
            taxNumberInput.classList.add('is-valid');
            return true;
        }
    }

    function validateWorkingHours() {
        const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        let isValid = true;
        const invalidDays = [];
        const whAlert = document.getElementById('whValidationAlert');

        days.forEach(day => {
            const sw = document.getElementById('wh_closed_' + day);
            const openInput = document.querySelector('input[name="wh_open[' + day + ']"]');
            const closeInput = document.querySelector('input[name="wh_close[' + day + ']"]');
            
            if (openInput) openInput.classList.remove('is-invalid');
            if (closeInput) closeInput.classList.remove('is-invalid');

            if (sw && !sw.checked && openInput && closeInput) {
                const openVal = openInput.value;
                const closeVal = closeInput.value;
                if (openVal && closeVal && openVal >= closeVal) {
                    isValid = false;
                    openInput.classList.add('is-invalid');
                    closeInput.classList.add('is-invalid');
                    const dayCap = day.charAt(0).toUpperCase() + day.slice(1);
                    invalidDays.push(`${dayCap}: Opening time (${openVal}) must be earlier than closing time (${closeVal}).`);
                }
            }
        });

        if (!isValid && whAlert) {
            whAlert.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i> ' + invalidDays.join('<br>');
            whAlert.classList.remove('d-none');
        } else if (whAlert) {
            whAlert.classList.add('d-none');
            whAlert.innerHTML = '';
        }
        return isValid;
    }

    // Attach real-time input listeners
    if (companyNameInput) {
        companyNameInput.addEventListener('input', function() {
            validateCompanyName();
            updateLiveProfileProgress();
        });
        companyNameInput.addEventListener('blur', function() {
            validateCompanyName();
            updateLiveProfileProgress();
        });
    }
    if (ownerNameInput) {
        ownerNameInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z\s\.]/g, '');
            validateOwnerName();
            updateLiveProfileProgress();
        });
        ownerNameInput.addEventListener('blur', function() {
            validateOwnerName();
            updateLiveProfileProgress();
        });
    }
    if (phoneInput) {
        phoneInput.addEventListener('input', function() {
            validatePhone();
            updateLiveProfileProgress();
        });
        phoneInput.addEventListener('blur', function() {
            validatePhone();
            updateLiveProfileProgress();
        });
        phoneInput.addEventListener('paste', function(e) {
            setTimeout(function() {
                validatePhone();
                updateLiveProfileProgress();
            }, 10);
        });
    }
    if (addressInput) {
        validateAddress();
        addressInput.addEventListener('input', function() {
            validateAddress();
            updateLiveProfileProgress();
        });
        addressInput.addEventListener('blur', function() {
            validateAddress();
            updateLiveProfileProgress();
        });
    }
    if (cityInput) {
        cityInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z\s\-]/g, '');
            validateCity();
            updateLiveProfileProgress();
        });
        cityInput.addEventListener('blur', function() {
            validateCity();
            updateLiveProfileProgress();
        });
    }
    if (stateInput) {
        stateInput.addEventListener('change', function() {
            validateState();
            updateLiveProfileProgress();
        });
    }
    if (postalCodeInput) {
        postalCodeInput.addEventListener('input', function() {
            validatePostalCode();
            updateLiveProfileProgress();
        });
        postalCodeInput.addEventListener('blur', function() {
            validatePostalCode();
            updateLiveProfileProgress();
        });
    }
    if (websiteInput) {
        websiteInput.addEventListener('input', validateWebsite);
        websiteInput.addEventListener('blur', function() {
            let val = this.value.trim();
            if (val && !val.match(/^https?:\/\//i)) {
                this.value = 'https://' + val;
            }
            validateWebsite();
        });
    }
    if (taxNumberInput) {
        taxNumberInput.addEventListener('input', validateTaxNumber);
        taxNumberInput.addEventListener('blur', validateTaxNumber);
    }

    // Working hours change listeners
    document.querySelectorAll('.wh-time-input').forEach(input => {
        input.addEventListener('change', validateWorkingHours);
    });

    // -------------------------------------------------------------
    // Live Profile Progress Calculation
    // -------------------------------------------------------------
    function updateLiveProfileProgress() {
        let progress = 0;
        
        // 1. Company Name (15%)
        const cName = companyNameInput ? companyNameInput.value.trim() : '';
        const isCNameValid = cName.length >= 2 && cName.length <= 100;
        if (isCNameValid) progress += 15;
        
        // 2. Owner Name (10%)
        const oName = ownerNameInput ? ownerNameInput.value.trim() : '';
        const isONameValid = oName.length >= 2 && oName.length <= 60 && /^[a-zA-Z\s\.]+$/.test(oName);
        if (isONameValid) progress += 10;
        
        // 3. Business Email (10%)
        const hasEmail = <?php echo !empty($company['business_email']) ? 'true' : 'false'; ?>;
        if (hasEmail) progress += 10;
        
        // 4. Business Phone (10%)
        const rawPhone = phoneInput ? phoneInput.value.replace(/[^0-9]/g, '') : '';
        const cleanDigits = (rawPhone.length === 12 && rawPhone.startsWith('91')) ? rawPhone.substring(2) : rawPhone;
        const isPhoneValid = /^[6-9][0-9]{9}$/.test(cleanDigits);
        if (isPhoneValid) progress += 10;
        
        // 5. Business Address (15%)
        const addr = addressInput ? addressInput.value.trim() : '';
        const isAddrValid = addr.length >= 5 && addr.length <= 250;
        if (isAddrValid) progress += 15;
        
        // 6. City (10%)
        const city = cityInput ? cityInput.value.trim() : '';
        const isCityValid = city.length >= 2 && city.length <= 50 && /^[a-zA-Z\s\-]+$/.test(city);
        if (isCityValid) progress += 10;
        
        // 7. State (10%)
        const state = stateInput ? stateInput.value.trim() : '';
        const isStateValid = state !== '';
        if (isStateValid) progress += 10;
        
        // 8. Postal Code (10%)
        const pin = postalCodeInput ? postalCodeInput.value.trim() : '';
        const isPinValid = /^[1-9][0-9]{5}$/.test(pin);
        if (isPinValid) progress += 10;
        
        // 9. Logo (10%)
        const hasExistingCustomLogo = <?php echo Company::hasCustomLogo($company) ? 'true' : 'false'; ?>;
        const hasSelectedLogoFile = logoInput && logoInput.files && logoInput.files.length > 0;
        const isLogoValid = hasExistingCustomLogo || hasSelectedLogoFile;
        if (isLogoValid) progress += 10;
        
        progress = Math.min(100, Math.max(0, progress));
        
        // Update SVG circle (desktop)
        const circDesk = <?php echo $circDesk; ?>;
        const offsetDesk = Math.round((circDesk - (progress / 100) * circDesk) * 100) / 100;
        const circleDesk = document.getElementById('profileProgressCircle');
        if (circleDesk) circleDesk.style.strokeDashoffset = offsetDesk;
        
        // Update SVG circle (mobile)
        const circMob = <?php echo $circMob; ?>;
        const offsetMob = Math.round((circMob - (progress / 100) * circMob) * 100) / 100;
        const circleMob = document.getElementById('profileProgressCircleMob');
        if (circleMob) circleMob.style.strokeDashoffset = offsetMob;
        
        // Update text percentages
        const textDesk = document.getElementById('profileProgressText');
        if (textDesk) textDesk.textContent = progress + '%';
        const textMob = document.getElementById('profileProgressTextMob');
        if (textMob) textMob.textContent = progress + '%';
        const textBar = document.getElementById('profileProgressBarText');
        if (textBar) textBar.textContent = progress + '%';
        const textBarMob = document.getElementById('profileProgressBarTextMob');
        if (textBarMob) textBarMob.textContent = progress + '%';
        
        // Update linear progress bar (desktop)
        const barFill = document.getElementById('profileProgressBarFill');
        if (barFill) {
            barFill.style.width = progress + '%';
            if (progress === 100) {
                barFill.style.background = '#00e676';
                barFill.style.boxShadow = '0 0 12px rgba(0, 230, 118, 0.8)';
            } else {
                barFill.style.background = 'linear-gradient(90deg, #38ef7d, #11998e)';
                barFill.style.boxShadow = '0 0 8px rgba(56, 239, 125, 0.5)';
            }
        }
        
        // Update linear progress bar (mobile)
        const barFillMob = document.getElementById('profileProgressBarFillMob');
        if (barFillMob) barFillMob.style.width = progress + '%';
        
        // Update subtext
        const subtextDesk = document.getElementById('profileProgressSubtext');
        if (subtextDesk) {
            subtextDesk.textContent = progress >= 100 
                ? 'Your shop profile is 100% complete and publicly active.' 
                : 'Complete missing details below to activate your public shop listing.';
        }
        const subtextMob = document.getElementById('profileProgressSubtextMob');
        if (subtextMob) {
            subtextMob.textContent = progress >= 100 
                ? '100% complete and publicly active.' 
                : 'Complete missing details to activate public listing.';
        }
        
        // Update pills
        function updatePill(id, isDone) {
            const pill = document.getElementById(id);
            if (!pill) return;
            const iconWrap = pill.querySelector('.pill-icon');
            const textWrap = pill.querySelector('.pill-text');
            if (iconWrap) {
                if (isDone) {
                    iconWrap.style.background = '#00e676';
                    iconWrap.style.border = 'none';
                    iconWrap.innerHTML = '<i class="fas fa-check"></i>';
                } else {
                    iconWrap.style.background = 'rgba(255,255,255,0.18)';
                    iconWrap.style.border = '2px solid rgba(255,255,255,0.55)';
                    iconWrap.innerHTML = '';
                }
            }
            if (textWrap) {
                textWrap.style.color = isDone ? '#ffffff' : 'rgba(255,255,255,0.85)';
            }
        }
        
        updatePill('pill_business_address', isAddrValid);
        updatePill('pill_city', isCityValid);
        updatePill('pill_state', isStateValid);
        updatePill('pill_postal_code', isPinValid);
        updatePill('pill_logo', isLogoValid);
        
        updatePill('pill_mob_business_address', isAddrValid);
        updatePill('pill_mob_city', isCityValid);
        updatePill('pill_mob_state', isStateValid);
        updatePill('pill_mob_postal_code', isPinValid);
        updatePill('pill_mob_logo', isLogoValid);
    }
    
    // Call once on page load to sync initial visual state
    updateLiveProfileProgress();

    // -------------------------------------------------------------
    // 3. Form Submit Interception & Handling
    // -------------------------------------------------------------
    const businessForm = document.getElementById('businessInfoActualForm');
    if (businessForm) {
        businessForm.addEventListener('submit', function(e) {
            const isCompanyValid = validateCompanyName();
            const isOwnerValid = validateOwnerName();
            const isPhoneValid = validatePhone();
            const isAddressValid = validateAddress();
            const isCityValid = validateCity();
            const isStateValid = validateState();
            const isPostalValid = validatePostalCode();
            const isWebValid = validateWebsite();
            const isGstValid = validateTaxNumber();
            const isHoursValid = validateWorkingHours();
            validateCompanyLogo(); // show notice if logo is not uploaded, but don't hard block saving address/phone

            if (!isCompanyValid || !isOwnerValid || !isPhoneValid || !isAddressValid || !isCityValid || !isStateValid || !isPostalValid || !isWebValid || !isGstValid || !isHoursValid) {
                e.preventDefault();
                const firstInvalid = businessForm.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalid.focus();
                } else if (!isHoursValid) {
                    const whSec = document.getElementById('workingHoursSection');
                    if (whSec) whSec.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return false;
            }

            // Ensure website has https://
            if (websiteInput && websiteInput.value.trim() && !websiteInput.value.trim().match(/^https?:\/\//i)) {
                websiteInput.value = 'https://' + websiteInput.value.trim();
            }

            // Clean phone before submission
            if (phoneInput) {
                let cleanPhone = phoneInput.value.replace(/[^0-9]/g, '');
                if (cleanPhone.length === 10) {
                    phoneInput.value = '+91' + cleanPhone;
                }
            }

            const saveBtn = document.getElementById('btnSaveBusinessInfo');
            const saveBtnText = document.getElementById('btnSaveBusinessInfoText');
            if (saveBtn) {
                saveBtn.disabled = true;
                if (saveBtnText) saveBtnText.textContent = 'Saving...';
                const icon = saveBtn.querySelector('i');
                if (icon) icon.className = 'fas fa-spinner fa-spin me-1';
            }
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

    // --- Working Hours Interactivity ---
    document.querySelectorAll('.wh-closed-switch').forEach(function(sw) {
        sw.addEventListener('change', function() {
            const day = this.dataset.day;
            const row = document.getElementById('row_wh_' + day);
            const timeControls = document.getElementById('time_controls_' + day);
            const closedMsg = document.getElementById('closed_msg_' + day);
            const openInput = row ? row.querySelector('input[name="wh_open[' + day + ']"]') : null;
            const closeInput = row ? row.querySelector('input[name="wh_close[' + day + ']"]') : null;

            if (this.checked) {
                if (row) row.classList.add('is-closed');
                if (timeControls) timeControls.style.setProperty('display', 'none', 'important');
                if (closedMsg) closedMsg.style.setProperty('display', 'block', 'important');
                if (openInput) openInput.disabled = true;
                if (closeInput) closeInput.disabled = true;
            } else {
                if (row) row.classList.remove('is-closed');
                if (timeControls) timeControls.style.setProperty('display', 'flex', 'important');
                if (closedMsg) closedMsg.style.setProperty('display', 'none', 'important');
                if (openInput) openInput.disabled = false;
                if (closeInput) closeInput.disabled = false;
            }
        });
    });

    // Quick Preset: Mon-Sat 10AM-8PM, Sun Closed
    const btnQuickMonSat = document.getElementById('btnQuickMonSat');
    if (btnQuickMonSat) {
        btnQuickMonSat.addEventListener('click', function() {
            const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            days.forEach(function(d) {
                const sw = document.getElementById('wh_closed_' + d);
                const row = document.getElementById('row_wh_' + d);
                const timeControls = document.getElementById('time_controls_' + d);
                const closedMsg = document.getElementById('closed_msg_' + d);
                const openInput = row ? row.querySelector('input[name="wh_open[' + d + ']"]') : null;
                const closeInput = row ? row.querySelector('input[name="wh_close[' + d + ']"]') : null;

                if (d === 'sunday') {
                    if (sw) sw.checked = true;
                    if (row) row.classList.add('is-closed');
                    if (timeControls) timeControls.style.setProperty('display', 'none', 'important');
                    if (closedMsg) closedMsg.style.setProperty('display', 'block', 'important');
                    if (openInput) openInput.disabled = true;
                    if (closeInput) closeInput.disabled = true;
                } else {
                    if (sw) sw.checked = false;
                    if (row) row.classList.remove('is-closed');
                    if (timeControls) timeControls.style.setProperty('display', 'flex', 'important');
                    if (closedMsg) closedMsg.style.setProperty('display', 'none', 'important');
                    if (openInput) {
                        openInput.disabled = false;
                        openInput.value = '10:00';
                    }
                    if (closeInput) {
                        closeInput.disabled = false;
                        closeInput.value = '20:00';
                    }
                }
            });
        });
    }

    // Copy Monday to other working days
    const btnCopyMonday = document.getElementById('btnCopyMonday');
    if (btnCopyMonday) {
        btnCopyMonday.addEventListener('click', function() {
            const monOpenInput = document.querySelector('input[name="wh_open[monday]"]');
            const monCloseInput = document.querySelector('input[name="wh_close[monday]"]');
            const monSw = document.getElementById('wh_closed_monday');
            if (!monOpenInput || !monCloseInput || !monSw) return;

            const openVal = monOpenInput.value || '10:00';
            const closeVal = monCloseInput.value || '20:00';
            const isClosed = monSw.checked;

            const otherDays = ['tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            otherDays.forEach(function(d) {
                const sw = document.getElementById('wh_closed_' + d);
                const row = document.getElementById('row_wh_' + d);
                const timeControls = document.getElementById('time_controls_' + d);
                const closedMsg = document.getElementById('closed_msg_' + d);
                const openInput = row ? row.querySelector('input[name="wh_open[' + d + ']"]') : null;
                const closeInput = row ? row.querySelector('input[name="wh_close[' + d + ']"]') : null;

                if (sw) sw.checked = isClosed;
                if (isClosed) {
                    if (row) row.classList.add('is-closed');
                    if (timeControls) timeControls.style.setProperty('display', 'none', 'important');
                    if (closedMsg) closedMsg.style.setProperty('display', 'block', 'important');
                    if (openInput) openInput.disabled = true;
                    if (closeInput) closeInput.disabled = true;
                } else {
                    if (row) row.classList.remove('is-closed');
                    if (timeControls) timeControls.style.setProperty('display', 'flex', 'important');
                    if (closedMsg) closedMsg.style.setProperty('display', 'none', 'important');
                    if (openInput) {
                        openInput.disabled = false;
                        openInput.value = openVal;
                    }
                    if (closeInput) {
                        closeInput.disabled = false;
                        closeInput.value = closeVal;
                    }
                }
            });
        });
    }
});
</script>
<?php require_once 'includes/footer.php'; ?>