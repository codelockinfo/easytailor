<?php
/**
 * Site Admin - Global & Site Settings
 * Tailoring Management System
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../models/SiteSetting.php';
require_once __DIR__ . '/../models/EmailChangeRequest.php';

// Check if site admin is logged in
if (!isset($_SESSION['site_admin_logged_in']) || $_SESSION['site_admin_logged_in'] !== true) {
    header('Location: ../admin/login.php');
    exit;
}

$emailChangeRequestModel = new EmailChangeRequest();
$pendingRequestsCount = count($emailChangeRequestModel->getAllRequestsWithDetails('pending'));

$message = '';
$messageType = '';

// Check session flash message
if (isset($_SESSION['settings_msg'])) {
    $message = $_SESSION['settings_msg'];
    $messageType = $_SESSION['settings_msg_type'] ?? 'success';
    unset($_SESSION['settings_msg'], $_SESSION['settings_msg_type']);
}

// Active tab
$activeTab = $_GET['tab'] ?? 'payment';
$validTabs = ['payment', 'seo_identity', 'social', 'contact', 'security', 'scripts'];
if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'payment';
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_payment') {
        $rzpMode = isset($_POST['razorpay_mode']) ? 'live' : 'test';
        $testKeyId = trim($_POST['razorpay_test_key_id'] ?? '');
        $testKeySecret = trim($_POST['razorpay_test_key_secret'] ?? '');
        $liveKeyId = trim($_POST['razorpay_live_key_id'] ?? '');
        $liveKeySecret = trim($_POST['razorpay_live_key_secret'] ?? '');

        SiteSetting::set('razorpay_mode', $rzpMode, 'payment');
        SiteSetting::set('razorpay_test_key_id', $testKeyId, 'payment');
        if (!empty($testKeySecret)) {
            SiteSetting::set('razorpay_test_key_secret', $testKeySecret, 'payment');
        }
        SiteSetting::set('razorpay_live_key_id', $liveKeyId, 'payment');
        if (!empty($liveKeySecret)) {
            SiteSetting::set('razorpay_live_key_secret', $liveKeySecret, 'payment');
        }

        $_SESSION['settings_msg'] = 'Payment gateway settings updated successfully!';
        $_SESSION['settings_msg_type'] = 'success';
        header('Location: settings?tab=payment');
        exit;
    }

    if ($action === 'save_social') {
        $fb = trim($_POST['social_facebook'] ?? ($_POST['facebook_url'] ?? ''));
        $tw = trim($_POST['social_twitter'] ?? ($_POST['twitter_url'] ?? ''));
        $ig = trim($_POST['social_instagram'] ?? ($_POST['instagram_url'] ?? ''));
        $li = trim($_POST['social_linkedin'] ?? ($_POST['linkedin_url'] ?? ''));
        $yt = trim($_POST['social_youtube'] ?? ($_POST['youtube_link'] ?? ($_POST['youtube_url'] ?? '')));
        $wa = trim($_POST['social_whatsapp'] ?? ($_POST['whatsapp_url'] ?? ''));

        $socials = [
            'social_facebook' => $fb,
            'facebook_url' => $fb,
            'social_twitter' => $tw,
            'twitter_url' => $tw,
            'social_instagram' => $ig,
            'instagram_url' => $ig,
            'social_linkedin' => $li,
            'linkedin_url' => $li,
            'social_youtube' => $yt,
            'youtube_link' => $yt,
            'youtube_url' => $yt,
            'social_whatsapp' => $wa,
            'whatsapp_url' => $wa
        ];
        SiteSetting::setMultiple($socials, 'social');

        $_SESSION['settings_msg'] = 'Social media links updated successfully!';
        $_SESSION['settings_msg_type'] = 'success';
        header('Location: settings?tab=social');
        exit;
    }

    if ($action === 'save_contact') {
        $contacts = [
            'contact_email' => trim($_POST['contact_email'] ?? ''),
            'contact_phone' => trim($_POST['contact_phone'] ?? ''),
            'contact_whatsapp' => trim($_POST['contact_whatsapp'] ?? ''),
            'contact_address' => trim($_POST['contact_address'] ?? ''),
            'contact_hours' => trim($_POST['contact_hours'] ?? ''),
            'contact_map_url' => trim($_POST['contact_map_url'] ?? '')
        ];
        SiteSetting::setMultiple($contacts, 'contact');

        $_SESSION['settings_msg'] = 'Contact information updated successfully!';
        $_SESSION['settings_msg_type'] = 'success';
        header('Location: settings?tab=contact');
        exit;
    }

    if ($action === 'save_seo') {
        $seoData = [
            'seo_title' => trim($_POST['seo_title'] ?? ''),
            'seo_description' => trim($_POST['seo_description'] ?? ''),
            'seo_keywords' => trim($_POST['seo_keywords'] ?? '')
        ];
        SiteSetting::setMultiple($seoData, 'seo_identity');

        // Handle Logo uploads
        $logoFields = ['admin_logo', 'website_logo', 'footer_logo', 'favicon_icon', 'payment_logo'];
        foreach ($logoFields as $lf) {
            if (!empty($_FILES[$lf]['name']) && is_uploaded_file($_FILES[$lf]['tmp_name'])) {
                SiteSetting::uploadFile($_FILES[$lf], $lf, 'seo_identity');
            }
        }

        $_SESSION['settings_msg'] = 'SEO & Brand Identity updated successfully!';
        $_SESSION['settings_msg_type'] = 'success';
        header('Location: settings?tab=seo_identity');
        exit;
    }


    if ($action === 'save_security') {
        SiteSetting::set('maintenance_mode', isset($_POST['maintenance_mode']) ? '1' : '0', 'security');
        SiteSetting::set('allow_registration', isset($_POST['allow_registration']) ? '1' : '0', 'security');
        SiteSetting::set('session_lifetime_minutes', (int)($_POST['session_lifetime_minutes'] ?? 120), 'security');

        $_SESSION['settings_msg'] = 'Security settings updated successfully!';
        $_SESSION['settings_msg_type'] = 'success';
        header('Location: settings?tab=security');
        exit;
    }

    if ($action === 'save_scripts') {
        SiteSetting::set('google_analytics_id', trim($_POST['google_analytics_id'] ?? ''), 'scripts');
        SiteSetting::set('custom_head_scripts', trim($_POST['custom_head_scripts'] ?? ''), 'scripts');
        SiteSetting::set('custom_footer_scripts', trim($_POST['custom_footer_scripts'] ?? ''), 'scripts');

        $_SESSION['settings_msg'] = 'Custom scripts and analytics updated successfully!';
        $_SESSION['settings_msg_type'] = 'success';
        header('Location: settings?tab=scripts');
        exit;
    }
}

// Fetch all settings
$settings = SiteSetting::getAll();

$rzpMode = $settings['razorpay_mode'] ?? 'test';
$testKeyConfigured = !empty($settings['razorpay_test_key_id']);
$liveKeyConfigured = !empty($settings['razorpay_live_key_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Settings - Site Admin</title>
    <?php echo render_favicon_links(); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #4338ca;
            --sidebar-width: 236px;
            --header-height: 62px;
            --theme-dark: #4f46e5;
            --theme-dark-hover: #4338ca;
        }
        body {
            background-color: #f6f8fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
            margin: 0;
            padding: 0;
        }
        .app-shell {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }
        
        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            min-width: var(--sidebar-width);
            background: linear-gradient(180deg, #4f46e5 0%, #4338ca 100%);
            color: white;
            padding: 0 0 1.5rem 0;
            display: flex;
            flex-direction: column;
            box-shadow: 10px 0 25px rgba(79, 70, 229, 0.15);
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }
        .brand {
            height: var(--header-height);
            min-height: var(--header-height);
            max-height: var(--header-height);
            padding: 0 1.1rem;
            margin: 0 0 0.75rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            box-sizing: border-box;
        }
        .brand-logo {
            height: 52px;
            width: auto;
            max-width: 185px;
            object-fit: contain;
            filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.25));
            transition: transform 0.2s ease;
        }
        .sidebar .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.15rem;
            border-radius: 10px;
            color: rgba(255,255,255,0.85);
            margin: 0.15rem 0.75rem;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.88rem;
            transition: all 0.2s ease;
        }
        .sidebar .nav-link.active, .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.18);
            color: white;
            font-weight: 600;
        }

        /* Main Content Container */
        .main-content {
            flex: 1;
            padding: 0;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
            width: calc(100% - var(--sidebar-width));
            background-color: #f8f9fa;
        }
        
        /* Unified Top Header */
        .top-header {
            background: #ffffff;
            height: var(--header-height);
            min-height: var(--header-height);
            max-height: var(--header-height);
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            margin: 0 !important;
            border: none;
            border-bottom: 1px solid #edf2f7;
            z-index: 998;
            box-sizing: border-box;
            border-radius: 0 !important;
        }
        .page-main-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
            letter-spacing: -0.3px;
            line-height: 1.2;
            margin: 0;
        }
        .notif-bell-btn {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #64748b;
            width: 38px;
            height: 38px;
            min-width: 38px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            flex-shrink: 0;
            padding: 0;
            font-size: 0.95rem;
        }
        .notif-bell-btn:hover {
            background: #f1f5f9;
            color: #4f46e5;
            transform: translateY(-1px);
        }
        .notif-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            min-width: 16px;
            height: 16px;
            padding: 0 4px;
            background: #ef4444;
            color: #ffffff;
            font-size: 0.62rem;
            font-weight: 700;
            line-height: 16px;
            text-align: center;
            border-radius: 999px;
            border: 1.5px solid #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .notif-dropdown-menu {
            border-radius: 14px !important;
            width: 340px !important;
            max-width: calc(100vw - 24px) !important;
            min-width: 300px !important;
            z-index: 1060;
            padding: 10px 12px !important;
            box-shadow: 0 12px 30px -4px rgba(16, 24, 40, 0.12), 0 4px 10px -2px rgba(16, 24, 40, 0.05) !important;
        }
        .user-pill-btn {
            background: transparent;
            border: none;
            padding: 2px 6px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            border-radius: 38px;
            transition: background 0.2s ease;
            flex-shrink: 0;
            height: 38px;
        }
        .user-pill-btn:hover, .user-pill-btn:focus {
            background: #f8fafc;
        }
        .user-avatar-circle {
            width: 38px;
            height: 38px;
            min-width: 38px;
            max-width: 38px;
            border-radius: 50%;
            background: #2563eb;
            color: #ffffff;
            font-weight: 700;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
            flex-shrink: 0;
            box-sizing: border-box;
        }
        .user-pill-name {
            font-size: 0.88rem;
            font-weight: 600;
            color: #1e293b;
        }
        .user-pill-chevron {
            font-size: 0.68rem;
            color: #3b82f6;
        }
        .dropdown-toggle::after {
            display: none !important;
        }

        .content-scroll {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 1.75rem 2.25rem 3rem;
        }

        /* Settings Pills Sidebar Card */
        .settings-nav-card {
            background: #ffffff;
            border: 1px solid #eef2f7;
            border-radius: 16px;
            padding: 0.85rem;
            box-shadow: 0 2px 12px rgba(15, 23, 42, 0.03);
        }
        .settings-tab-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 16px;
            border-radius: 12px;
            color: #64748b;
            font-size: 0.88rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            margin-bottom: 0.35rem;
            border: 1px solid transparent;
        }
        .settings-tab-btn i {
            width: 20px;
            font-size: 1rem;
            text-align: center;
            color: #64748b;
            transition: color 0.2s;
        }
        .settings-tab-btn:hover {
            background-color: #f8fafc;
            color: #0f172a;
        }
        .settings-tab-btn:hover i {
            color: #0f172a;
        }
        .settings-tab-btn.active {
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.28);
        }
        .settings-tab-btn.active i {
            color: #ffffff;
        }

        /* Settings Content Card */
        .settings-content-card {
            background: #ffffff;
            border-radius: 18px;
            border: 1px solid #eef2f7;
            box-shadow: 0 4px 18px rgba(15, 23, 42, 0.04);
            padding: 2rem 2.25rem;
        }
        .settings-header-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.25rem;
            letter-spacing: -0.02em;
        }
        .settings-header-sub {
            color: #64748b;
            font-size: 0.88rem;
            margin-bottom: 1.75rem;
        }

        /* Payment Mode Card */
        .payment-env-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
        }
        .env-tag {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 700;
            color: #94a3b8;
            margin-bottom: 0.35rem;
        }
        .env-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.2rem;
        }
        .env-desc {
            font-size: 0.84rem;
            color: #64748b;
        }
        .mode-banner-test {
            background: #eef2ff;
            border: 1px solid #c7d2fe;
            color: #3730a3;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 1rem;
            width: 100%;
        }
        .mode-banner-live {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 1rem;
            width: 100%;
        }

        /* Sub-Environment Cards */
        .gateway-card {
            border: 1px solid #4f46e5;
            border-radius: 14px;
            padding: 1.35rem;
            background: #ffffff;
            height: 100%;
            transition: all 0.2s ease;
            box-shadow: 0 4px 16px rgba(79, 70, 229, 0.06);
        }
        .gateway-card.active-env {
            border-color: #4f46e5;
            box-shadow: 0 4px 16px rgba(79, 70, 229, 0.1);
        }
        .badge-configured {
            background: #dcfce7;
            color: #15803d;
            font-size: 0.72rem;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
        }
        .badge-not-configured {
            background: #fef3c7;
            color: #b45309;
            font-size: 0.72rem;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
        }

        /* Form elements */
        .form-label {
            font-size: 0.82rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.35rem;
        }
        .form-control, .form-select {
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 0.55rem 0.85rem;
            font-size: 0.86rem;
            color: #0f172a;
            transition: all 0.2s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
        }
        
        /* Custom Theme Switch */
        .form-switch .form-check-input {
            width: 3rem;
            height: 1.6rem;
            cursor: pointer;
            background-color: #cbd5e1;
            border-color: #cbd5e1;
            transition: all 0.2s ease;
        }
        .form-switch .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        /* Logo Upload Card */
        .logo-upload-card {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 14px;
            padding: 1.25rem;
            text-align: center;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            transition: border-color 0.2s;
        }
        .logo-upload-card:hover {
            border-color: var(--primary-color);
        }
        .logo-preview-box {
            width: 100%;
            height: 90px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            margin-bottom: 0.85rem;
            padding: 8px;
            overflow: hidden;
        }
        .logo-preview-box.dark-bg {
            background: transparent;
        }
        .logo-preview-box img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .logo-title {
            font-size: 0.88rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.2rem;
        }
        .logo-hint {
            font-size: 0.72rem;
            color: #64748b;
            margin-bottom: 0.75rem;
        }

        /* Save Button */
        .btn-save-settings {
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
            color: #ffffff;
            border: none;
            padding: 0.65rem 1.6rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.88rem;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
        }
        .btn-save-settings:hover {
            background: linear-gradient(135deg, #4338ca 0%, #3730a3 100%);
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.32);
        }

        /* Responsive Mobile Drawer */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1040;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .sidebar-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        @media (max-width: 992px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: -280px;
                width: 280px;
                height: 100vh;
                z-index: 1045;
                transition: left 0.3s ease;
                padding: 0 0 1.5rem 0;
            }
            .sidebar.show {
                left: 0;
            }
            .main-content {
                width: 100%;
                padding: 0;
            }
            .top-header {
                padding: 0 1rem;
            }
            .content-scroll {
                padding: 1.25rem 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="app-shell">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="brand">
                <?php 
                $adminLogo = get_site_logo('admin');
                if (!empty($adminLogo)): ?>
                    <img src="<?php echo htmlspecialchars($adminLogo); ?>" alt="Tailor Pro" class="brand-logo">
                <?php else: ?>
                    <i class="fas fa-crown"></i>
                <?php endif; ?>
            </div>
            <a href="index.php?section=requests" class="nav-link">
                <i class="fas fa-envelope-open-text"></i>
                Email Requests
            </a>
            <a href="index.php?section=company" class="nav-link">
                <i class="fas fa-building"></i>
                Company Snapshot
            </a>
            <a href="index.php?section=contact" class="nav-link">
                <i class="fas fa-comments"></i>
                Contact Messages
            </a>
            <a href="index.php?section=testimonials" class="nav-link">
                <i class="fas fa-star"></i>
                Testimonials
            </a>
            <a href="settings.php" class="nav-link active">
                <i class="fas fa-sliders-h"></i>
                Global Settings
            </a>
            <div class="mt-auto">
                <a href="../admin/logout.php" class="nav-link" onclick="return confirm('Are you sure you want to logout?');">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </aside>

        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Main Content View -->
        <main class="main-content">
            <!-- Top Header Bar -->
            <header class="top-header">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-link p-1 text-primary d-lg-none" id="mobileMenuBtn" aria-label="Toggle Navigation" style="font-size: 1.25rem; text-decoration: none; color: #4f46e5 !important; line-height: 1;">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h4 class="mb-0 page-main-title fw-bold text-dark">Global Settings</h4>
                </div>
                
                <div class="d-flex align-items-center gap-2">
                    <!-- Notifications Bell -->
                    <div class="dropdown">
                        <button class="notif-bell-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                            <i class="fas fa-bell"></i>
                            <span class="notif-badge" id="headerNotifBadge" style="<?php echo ($pendingRequestsCount > 0) ? '' : 'display: none;'; ?>"><?php echo $pendingRequestsCount; ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 notif-dropdown-menu">
                            <li class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                <h6 class="mb-0 fw-bold" style="font-size: 0.9rem; color: #1e293b;">Notifications</h6>
                                <span class="badge bg-danger rounded-pill" style="<?php echo ($pendingRequestsCount > 0) ? 'display: inline-block;' : 'display: none;'; ?>; font-size: 0.68rem; padding: 2px 7px;"><?php echo $pendingRequestsCount; ?> new</span>
                            </li>
                            <div style="max-height: 320px; overflow-y: auto;">
                                <?php if ($pendingRequestsCount > 0): ?>
                                    <li>
                                        <a class="dropdown-item d-flex align-items-start gap-2 p-2 rounded bg-light-subtle" href="index.php?section=requests" style="white-space: normal;">
                                            <i class="fas fa-envelope-open-text text-primary mt-1" style="font-size: 1rem;"></i>
                                            <div class="flex-grow-1" style="min-width: 0;">
                                                <div class="fw-semibold text-dark text-truncate" style="font-size: 0.82rem;">Email Change Requests</div>
                                                <div class="text-muted text-break" style="font-size: 0.74rem; line-height: 1.35;"><?php echo $pendingRequestsCount; ?> pending request(s) awaiting approval</div>
                                            </div>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="text-center py-4 text-muted" style="font-size: 0.85rem;">
                                        <i class="fas fa-check-circle text-success mb-2 d-block" style="font-size: 1.5rem;"></i>
                                        All caught up! No notifications.
                                    </li>
                                <?php endif; ?>
                            </div>
                        </ul>
                    </div>

                    <!-- Admin Profile Pill Dropdown -->
                    <div class="dropdown">
                        <button class="user-pill-btn dropdown-toggle p-0 p-sm-1" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-avatar-circle">
                                <span>AD</span>
                            </div>
                            <span class="user-pill-name d-none d-sm-inline">Admin</span>
                            <i class="fas fa-chevron-down user-pill-chevron ms-1 d-none d-sm-inline"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="border-radius: 12px; min-width: 200px; z-index: 1060;">
                            <li class="px-3 py-2">
                                <div class="fw-bold text-dark" style="font-size: 0.9rem;">Site Administrator</div>
                                <small class="text-muted text-capitalize" style="font-size: 0.75rem;">Super Admin</small>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li>
                                <a class="dropdown-item text-danger py-2" href="../admin/logout.php" onclick="return confirm('Are you sure you want to logout?');">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Scrollable Content Area -->
            <div class="content-scroll">
                <!-- Page Title Header -->
                <div class="mb-4">
                    <h2 class="fw-bold text-dark mb-1" style="font-size: 1.6rem; letter-spacing: -0.02em;">Global Settings</h2>
                    <p class="text-muted mb-0" style="font-size: 0.92rem;">Configure and customize system branding, SEO rules, scripts, and platform controls.</p>
                </div>

                <!-- Flash Alert -->
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Two Column Settings Layout -->
                <div class="row g-4">
                    <!-- Left Tab Navigation Pills -->
                    <div class="col-xl-3 col-lg-4">
                        <div class="settings-nav-card">
                            <a href="settings?tab=payment" class="settings-tab-btn <?php echo $activeTab === 'payment' ? 'active' : ''; ?>">
                                <i class="far fa-credit-card"></i>
                                <span>Payment Gateway</span>
                            </a>
                            <a href="settings?tab=seo_identity" class="settings-tab-btn <?php echo $activeTab === 'seo_identity' ? 'active' : ''; ?>">
                                <i class="fas fa-globe"></i>
                                <span>SEO & Identity</span>
                            </a>
                            <a href="settings?tab=social" class="settings-tab-btn <?php echo $activeTab === 'social' ? 'active' : ''; ?>">
                                <i class="fas fa-share-alt"></i>
                                <span>Social Media</span>
                            </a>
                            <a href="settings?tab=contact" class="settings-tab-btn <?php echo $activeTab === 'contact' ? 'active' : ''; ?>">
                                <i class="far fa-envelope"></i>
                                <span>Contact Information</span>
                            </a>

                            <a href="settings?tab=security" class="settings-tab-btn <?php echo $activeTab === 'security' ? 'active' : ''; ?>">
                                <i class="far fa-shield-alt"></i>
                                <span>Security & Access</span>
                            </a>
                            <a href="settings?tab=scripts" class="settings-tab-btn <?php echo $activeTab === 'scripts' ? 'active' : ''; ?>">
                                <i class="fas fa-code"></i>
                                <span>Scripts & Advanced</span>
                            </a>
                        </div>
                    </div>

                    <!-- Right Tab Content Column -->
                    <div class="col-xl-9 col-lg-8">
                        <div class="settings-content-card">
                            
                            <!-- TAB 1: PAYMENT GATEWAY (Matches User Screenshot) -->
                            <?php if ($activeTab === 'payment'): ?>
                                <form method="POST" action="?tab=payment">
                                    <input type="hidden" name="action" value="save_payment">

                                    <h4 class="settings-header-title">Payment Gateway Settings</h4>
                                    <p class="settings-header-sub">Configure Razorpay test and live credentials. Secret values are encrypted and never returned by the API.</p>

                                    <!-- Gateway Operation Mode Card -->
                                    <div class="payment-env-card">
                                        <div class="env-tag">PAYMENT ENVIRONMENT</div>
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <div class="env-title">Gateway Operation Mode</div>
                                                <div class="env-desc" id="modeNoticeText">
                                                    <?php echo $rzpMode === 'live' ? 'Live mode is enabled. Real customer payments will be charged.' : 'Test mode is enabled. No real money will be charged.'; ?>
                                                </div>
                                            </div>
                                            <div class="form-check form-switch m-0">
                                                <input class="form-check-input" type="checkbox" id="gatewayModeToggle" name="razorpay_mode" value="live" <?php echo $rzpMode === 'live' ? 'checked' : ''; ?> onchange="updateModeUI(this.checked)">
                                            </div>
                                        </div>

                                        <div id="modeBannerBox" class="<?php echo $rzpMode === 'live' ? 'mode-banner-live' : 'mode-banner-test'; ?>">
                                            <i class="fas fa-info-circle me-1"></i>
                                            <span id="modeBannerText">Active mode: <strong><?php echo $rzpMode === 'live' ? 'LIVE — production payments' : 'TEST — sandbox payments'; ?></strong></span>
                                        </div>
                                    </div>

                                    <!-- Two Column Razorpay Environments -->
                                    <div class="row g-4 mb-4">
                                        <!-- Sandbox Environment -->
                                        <div class="col-md-6">
                                            <div class="gateway-card <?php echo $rzpMode === 'test' ? 'active-env' : ''; ?>">
                                                <div class="d-flex align-items-center justify-content-between mb-3">
                                                    <div>
                                                        <div class="env-tag">SANDBOX ENVIRONMENT</div>
                                                        <div class="fw-bold fs-5 text-dark">Razorpay Test</div>
                                                    </div>
                                                    <span class="<?php echo $testKeyConfigured ? 'badge-configured' : 'badge-not-configured'; ?>">
                                                        <?php echo $testKeyConfigured ? 'Configured' : 'Not configured'; ?>
                                                    </span>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Test Key ID</label>
                                                    <input type="text" class="form-control font-monospace" name="razorpay_test_key_id" value="<?php echo htmlspecialchars($settings['razorpay_test_key_id'] ?? ''); ?>" placeholder="rzp_test_...">
                                                </div>

                                                <div class="mb-2">
                                                    <label class="form-label">Test Key Secret</label>
                                                    <div class="input-group">
                                                        <input type="password" class="form-control font-monospace" id="testSecretInput" name="razorpay_test_key_secret" placeholder="Leave blank to keep existing secret">
                                                        <button class="btn btn-outline-secondary" type="button" onclick="toggleSecretVisibility('testSecretInput', this)">
                                                            <i class="far fa-eye"></i>
                                                        </button>
                                                    </div>
                                                    <small class="text-muted" style="font-size: 0.75rem;">Leave empty if you don't wish to change the existing secret key.</small>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Production Environment -->
                                        <div class="col-md-6">
                                            <div class="gateway-card <?php echo $rzpMode === 'live' ? 'active-env' : ''; ?>">
                                                <div class="d-flex align-items-center justify-content-between mb-3">
                                                    <div>
                                                        <div class="env-tag">PRODUCTION ENVIRONMENT</div>
                                                        <div class="fw-bold fs-5 text-dark">Razorpay Live</div>
                                                    </div>
                                                    <span class="<?php echo $liveKeyConfigured ? 'badge-configured' : 'badge-not-configured'; ?>">
                                                        <?php echo $liveKeyConfigured ? 'Configured' : 'Not configured'; ?>
                                                    </span>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Live Key ID</label>
                                                    <input type="text" class="form-control font-monospace" name="razorpay_live_key_id" value="<?php echo htmlspecialchars($settings['razorpay_live_key_id'] ?? ''); ?>" placeholder="rzp_live_...">
                                                </div>

                                                <div class="mb-2">
                                                    <label class="form-label">Live Key Secret</label>
                                                    <div class="input-group">
                                                        <input type="password" class="form-control font-monospace" id="liveSecretInput" name="razorpay_live_key_secret" placeholder="Enter live key secret">
                                                        <button class="btn btn-outline-secondary" type="button" onclick="toggleSecretVisibility('liveSecretInput', this)">
                                                            <i class="far fa-eye"></i>
                                                        </button>
                                                    </div>
                                                    <small class="text-muted" style="font-size: 0.75rem;">Keep confidential. Encrypted when stored.</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-save-settings">
                                            <i class="fas fa-check me-2"></i>Save Settings
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>

                            <!-- TAB 2: SEO & BRAND IDENTITY (Logos & Meta) -->
                            <?php if ($activeTab === 'seo_identity'): ?>
                                <form method="POST" action="?tab=seo_identity" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="save_seo">

                                    <h4 class="settings-header-title">SEO & Brand Identity</h4>
                                    <p class="settings-header-sub">Manage system branding logos, public website logos, browser favicon, and SEO meta tags.</p>

                                    <!-- 5 Logo Upload Cards -->
                                    <h6 class="fw-bold text-dark mb-3"><i class="fas fa-images text-primary me-2"></i>Brand Logos & Badges</h6>
                                    <div class="row g-3 mb-4">
                                        <!-- 1. Admin Logo -->
                                        <div class="col-md-4 col-sm-6">
                                            <div class="logo-upload-card">
                                                <div class="w-100">
                                                    <div class="logo-title">Admin Logo</div>
                                                    <div class="logo-hint">Sidebar & login (185x52 px)</div>
                                                    <div class="logo-preview-box dark-bg">
                                                        <?php $adminLogo = $settings['admin_logo'] ?? 'assets/images/tailor_logo.png'; ?>
                                                        <img src="../<?php echo htmlspecialchars($adminLogo); ?>?v=<?php echo time(); ?>" id="preview_admin_logo" alt="Admin Logo">
                                                    </div>
                                                </div>
                                                <div class="w-100">
                                                    <input type="file" class="form-control form-control-sm" name="admin_logo" accept="image/*" onchange="previewImageFile(this, 'preview_admin_logo')">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 2. Website Logo -->
                                        <div class="col-md-4 col-sm-6">
                                            <div class="logo-upload-card">
                                                <div class="w-100">
                                                    <div class="logo-title">Website Logo</div>
                                                    <div class="logo-hint">Main landing header logo</div>
                                                    <div class="logo-preview-box dark-bg">
                                                        <?php $webLogo = $settings['website_logo'] ?? 'assets/images/tailor_logo.png'; ?>
                                                        <img src="../<?php echo htmlspecialchars($webLogo); ?>?v=<?php echo time(); ?>" id="preview_website_logo" alt="Website Logo">
                                                    </div>
                                                </div>
                                                <div class="w-100">
                                                    <input type="file" class="form-control form-control-sm" name="website_logo" accept="image/*" onchange="previewImageFile(this, 'preview_website_logo')">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 3. Footer Logo -->
                                        <div class="col-md-4 col-sm-6">
                                            <div class="logo-upload-card">
                                                <div class="w-100">
                                                    <div class="logo-title">Footer Logo</div>
                                                    <div class="logo-hint">Website dark footer (White text)</div>
                                                    <div class="logo-preview-box dark-bg">
                                                        <?php $footerLogo = $settings['footer_logo'] ?? 'assets/images/tailor_logo.png'; ?>
                                                        <img src="../<?php echo htmlspecialchars($footerLogo); ?>?v=<?php echo time(); ?>" id="preview_footer_logo" alt="Footer Logo">
                                                    </div>
                                                </div>
                                                <div class="w-100">
                                                    <input type="file" class="form-control form-control-sm" name="footer_logo" accept="image/*" onchange="previewImageFile(this, 'preview_footer_logo')">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 4. Favicon Icon -->
                                        <div class="col-md-4 col-sm-6">
                                            <div class="logo-upload-card">
                                                <div class="w-100">
                                                    <div class="logo-title">Favicon Icon</div>
                                                    <div class="logo-hint">Browser tab icon (32x32 / 48x48)</div>
                                                    <div class="logo-preview-box">
                                                        <?php $favIcon = $settings['favicon_icon'] ?? 'favicon.ico'; ?>
                                                        <img src="../<?php echo htmlspecialchars($favIcon); ?>?v=<?php echo time(); ?>" id="preview_favicon_icon" alt="Favicon" style="max-height: 48px;">
                                                    </div>
                                                </div>
                                                <div class="w-100">
                                                    <input type="file" class="form-control form-control-sm" name="favicon_icon" accept="image/x-icon,image/png,image/svg+xml" onchange="previewImageFile(this, 'preview_favicon_icon')">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 5. Payment Logo / Badges -->
                                        <div class="col-md-4 col-sm-6">
                                            <div class="logo-upload-card">
                                                <div class="w-100">
                                                    <div class="logo-title">Payment Logo</div>
                                                    <div class="logo-hint">Accepted payment methods badge</div>
                                                    <div class="logo-preview-box">
                                                        <?php $payLogo = $settings['payment_logo'] ?? ''; ?>
                                                        <?php if (!empty($payLogo)): ?>
                                                            <img src="../<?php echo htmlspecialchars($payLogo); ?>?v=<?php echo time(); ?>" id="preview_payment_logo" alt="Payment Badge">
                                                        <?php else: ?>
                                                            <div id="preview_payment_logo_placeholder" class="text-muted small"><i class="fas fa-credit-card fa-2x mb-1 d-block text-secondary"></i>No image uploaded</div>
                                                            <img src="" id="preview_payment_logo" alt="Payment Badge" style="display: none;">
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="w-100">
                                                    <input type="file" class="form-control form-control-sm" name="payment_logo" accept="image/*" onchange="previewImageFile(this, 'preview_payment_logo', 'preview_payment_logo_placeholder')">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- SEO Metadata Fields -->
                                    <h6 class="fw-bold text-dark mb-3"><i class="fas fa-search text-success me-2"></i>Search Engine Optimization (SEO)</h6>
                                    <div class="row g-3 mb-4">
                                        <div class="col-12">
                                            <label class="form-label">Site Title</label>
                                            <input type="text" class="form-control" name="seo_title" value="<?php echo htmlspecialchars($settings['seo_title'] ?? ''); ?>" placeholder="TailorPro - Smart Tailoring Software">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Meta Description</label>
                                            <textarea class="form-control" name="seo_description" rows="3" placeholder="Brief summary of your business shown in search engine results..."><?php echo htmlspecialchars($settings['seo_description'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Meta Keywords</label>
                                            <input type="text" class="form-control" name="seo_keywords" value="<?php echo htmlspecialchars($settings['seo_keywords'] ?? ''); ?>" placeholder="tailor software, measurement app, boutique management, orders">
                                            <small class="text-muted">Separate multiple keywords with commas.</small>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-save-settings">
                                            <i class="fas fa-check me-2"></i>Save SEO & Identity
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>

                            <!-- TAB 3: SOCIAL MEDIA (Social Links) -->
                            <?php if ($activeTab === 'social'): ?>
                                <form method="POST" action="?tab=social">
                                    <input type="hidden" name="action" value="save_social">

                                    <h4 class="settings-header-title">Social Media Links</h4>
                                    <p class="settings-header-sub">Configure official social media profile URLs displayed across the website and footer.</p>

                                    <div class="row g-3 mb-4">
                                        <div class="col-md-6">
                                            <label class="form-label"><i class="fab fa-facebook text-primary me-1"></i>Facebook URL</label>
                                            <input type="url" class="form-control" name="social_facebook" value="<?php echo htmlspecialchars($settings['social_facebook'] ?? ''); ?>" placeholder="https://facebook.com/yourpage">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label"><svg viewBox="0 0 512 512" width="13" height="13" fill="currentColor" class="text-dark me-1" style="vertical-align:-0.125em;" aria-hidden="true"><path d="M389.2 48h70.6L305.6 224.2 487 464H345L233.7 318.6 106.5 464H35.8L200.7 275.5 26.8 48H172.4L272.9 180.9 389.2 48zM364.4 421.8h39.1L151.1 88h-42L364.4 421.8z"/></svg>Twitter / X URL</label>
                                            <input type="url" class="form-control" name="social_twitter" value="<?php echo htmlspecialchars($settings['social_twitter'] ?? ''); ?>" placeholder="https://x.com/yourhandle">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label"><i class="fab fa-instagram text-danger me-1"></i>Instagram URL</label>
                                            <input type="url" class="form-control" name="social_instagram" value="<?php echo htmlspecialchars($settings['social_instagram'] ?? ''); ?>" placeholder="https://instagram.com/yourhandle">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label"><i class="fab fa-linkedin text-info me-1"></i>LinkedIn URL</label>
                                            <input type="url" class="form-control" name="social_linkedin" value="<?php echo htmlspecialchars($settings['social_linkedin'] ?? ''); ?>" placeholder="https://linkedin.com/company/yourprofile">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label"><i class="fab fa-youtube text-danger me-1"></i>YouTube Channel</label>
                                            <input type="url" class="form-control" name="social_youtube" value="<?php echo htmlspecialchars($settings['social_youtube'] ?? ''); ?>" placeholder="https://youtube.com/@yourchannel">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label"><i class="fab fa-whatsapp text-success me-1"></i>WhatsApp Contact Link / Number</label>
                                            <input type="text" class="form-control" name="social_whatsapp" value="<?php echo htmlspecialchars($settings['social_whatsapp'] ?? ''); ?>" placeholder="+919876543210 or https://wa.me/919876543210">
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-save-settings">
                                            <i class="fas fa-check me-2"></i>Save Social Media
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>

                            <!-- TAB 4: CONTACT INFORMATION -->
                            <?php if ($activeTab === 'contact'): ?>
                                <form method="POST" action="?tab=contact">
                                    <input type="hidden" name="action" value="save_contact">

                                    <h4 class="settings-header-title">Contact Information</h4>
                                    <p class="settings-header-sub">Public customer support and office contact details shown on the website, header, and footer.</p>

                                    <div class="row g-3 mb-4">
                                        <div class="col-md-6">
                                            <label class="form-label"><i class="fas fa-envelope text-primary me-1"></i>Contact Email Address</label>
                                            <input type="email" class="form-control" name="contact_email" value="<?php echo htmlspecialchars($settings['contact_email'] ?? ''); ?>" placeholder="contact@tailorpro.in" required>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label"><i class="fas fa-phone text-primary me-1"></i>Contact Phone Number</label>
                                            <input type="text" class="form-control" name="contact_phone" value="<?php echo htmlspecialchars($settings['contact_phone'] ?? ''); ?>" placeholder="+91 98765 43210" required>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label"><i class="fab fa-whatsapp text-success me-1"></i>WhatsApp Support Number</label>
                                            <input type="text" class="form-control" name="contact_whatsapp" value="<?php echo htmlspecialchars($settings['contact_whatsapp'] ?? ''); ?>" placeholder="+91 98765 43210">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label"><i class="far fa-clock text-warning me-1"></i>Support Working Hours</label>
                                            <input type="text" class="form-control" name="contact_hours" value="<?php echo htmlspecialchars($settings['contact_hours'] ?? ''); ?>" placeholder="Mon - Sat: 9:00 AM - 7:00 PM">
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label"><i class="fas fa-map-marker-alt text-danger me-1"></i>Physical Office / Store Address</label>
                                            <textarea class="form-control" name="contact_address" rows="3" placeholder="123 Ring Road, Surat, Gujarat 395002, India"><?php echo htmlspecialchars($settings['contact_address'] ?? ''); ?></textarea>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label"><i class="fas fa-map text-info me-1"></i>Google Maps Embed or Link</label>
                                            <input type="text" class="form-control" name="contact_map_url" value="<?php echo htmlspecialchars($settings['contact_map_url'] ?? ''); ?>" placeholder="https://maps.google.com/...">
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-save-settings">
                                            <i class="fas fa-check me-2"></i>Save Contact Info
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>


                            <!-- TAB 6: SECURITY & ACCESS -->
                            <?php if ($activeTab === 'security'): ?>
                                <form method="POST" action="?tab=security">
                                    <input type="hidden" name="action" value="save_security">

                                    <h4 class="settings-header-title">Security & Access Control</h4>
                                    <p class="settings-header-sub">Control user registrations, session management, and system maintenance mode.</p>

                                    <div class="payment-env-card mb-3">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <div class="env-title">Maintenance Mode</div>
                                                <div class="env-desc">Temporarily disable access for non-admin users during updates.</div>
                                            </div>
                                            <div class="form-check form-switch m-0">
                                                <input class="form-check-input" type="checkbox" name="maintenance_mode" value="1" <?php echo ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="payment-env-card mb-4">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <div class="env-title">Allow New Shop Registrations</div>
                                                <div class="env-desc">Allow new tailor shops to sign up and create accounts online.</div>
                                            </div>
                                            <div class="form-check form-switch m-0">
                                                <input class="form-check-input" type="checkbox" name="allow_registration" value="1" <?php echo ($settings['allow_registration'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label">Session Lifetime (Minutes)</label>
                                        <input type="number" class="form-control" style="max-width: 250px;" name="session_lifetime_minutes" value="<?php echo htmlspecialchars($settings['session_lifetime_minutes'] ?? '120'); ?>" min="15" max="1440">
                                        <small class="text-muted">Inactivity duration before a user is automatically logged out.</small>
                                    </div>

                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-save-settings">
                                            <i class="fas fa-check me-2"></i>Save Security Settings
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>

                            <!-- TAB 7: SCRIPTS & ADVANCED -->
                            <?php if ($activeTab === 'scripts'): ?>
                                <form method="POST" action="?tab=scripts">
                                    <input type="hidden" name="action" value="save_scripts">

                                    <h4 class="settings-header-title">Scripts & Advanced Platform Controls</h4>
                                    <p class="settings-header-sub">Inject custom tracking scripts, Google Analytics 4 tags, and third-party widgets.</p>

                                    <div class="mb-3">
                                        <label class="form-label"><i class="fab fa-google text-danger me-1"></i>Google Analytics 4 Measurement ID</label>
                                        <input type="text" class="form-control font-monospace" name="google_analytics_id" value="<?php echo htmlspecialchars($settings['google_analytics_id'] ?? 'G-27LLB9QMEV'); ?>" placeholder="G-XXXXXXXXXX">
                                        <small class="text-muted">Example: G-27LLB9QMEV</small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label"><i class="fas fa-code text-primary me-1"></i>Custom Header Code (&lt;head&gt;)</label>
                                        <textarea class="form-control font-monospace" name="custom_head_scripts" rows="4" placeholder="<script>...</script> or <meta ...>"><?php echo htmlspecialchars($settings['custom_head_scripts'] ?? ''); ?></textarea>
                                        <small class="text-muted">Scripts inserted right before the closing &lt;/head&gt; tag.</small>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label"><i class="fas fa-terminal text-secondary me-1"></i>Custom Footer Code (&lt;/body&gt;)</label>
                                        <textarea class="form-control font-monospace" name="custom_footer_scripts" rows="4" placeholder="<script>/* Chat widget or analytics */</script>"><?php echo htmlspecialchars($settings['custom_footer_scripts'] ?? ''); ?></textarea>
                                        <small class="text-muted">Scripts inserted right before the closing &lt;/body&gt; tag.</small>
                                    </div>

                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-save-settings">
                                            <i class="fas fa-check me-2"></i>Save Custom Scripts
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle live mode UI in Payment Settings
        function updateModeUI(isLive) {
            const noticeText = document.getElementById('modeNoticeText');
            const bannerBox = document.getElementById('modeBannerBox');
            const bannerText = document.getElementById('modeBannerText');

            if (isLive) {
                if (noticeText) noticeText.textContent = 'Live mode is enabled. Real customer payments will be charged.';
                if (bannerBox) {
                    bannerBox.className = 'mode-banner-live';
                }
                if (bannerText) {
                    bannerText.innerHTML = 'Active mode: <strong>LIVE — production payments</strong>';
                }
            } else {
                if (noticeText) noticeText.textContent = 'Test mode is enabled. No real money will be charged.';
                if (bannerBox) {
                    bannerBox.className = 'mode-banner-test';
                }
                if (bannerText) {
                    bannerText.innerHTML = 'Active mode: <strong>TEST — sandbox payments</strong>';
                }
            }
        }

        // Toggle secret key visibility
        function toggleSecretVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            if (!input) return;
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                if (icon) {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                }
            } else {
                input.type = 'password';
                if (icon) {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            }
        }

        // Image file preview helper
        function previewImageFile(input, previewImgId, placeholderId = null) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.getElementById(previewImgId);
                    if (img) {
                        img.src = e.target.result;
                        img.style.display = 'block';
                    }
                    if (placeholderId) {
                        const ph = document.getElementById(placeholderId);
                        if (ph) ph.style.display = 'none';
                    }
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Mobile Sidebar Drawer
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            function toggleSidebar() {
                if (sidebar) sidebar.classList.toggle('show');
                if (overlay) overlay.classList.toggle('show');
            }
            
            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', toggleSidebar);
            }
            if (overlay) {
                overlay.addEventListener('click', toggleSidebar);
            }
        });
    </script>
</body>
</html>
