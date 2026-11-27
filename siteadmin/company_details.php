<?php
require_once '../config/config.php'; 
if (!function_exists('escape')) {
    function escape($value) {
        return htmlspecialchars(htmlspecialchars_decode($value ?? '', ENT_QUOTES), ENT_QUOTES, 'UTF-8');
    }
}
if (!isset($_SESSION['site_admin_logged_in']) || $_SESSION['site_admin_logged_in'] !== true) {
    header('Location: ../admin/login.php');
    exit;
}
require_once '../models/Company.php';
require_once '../models/EmailChangeRequest.php';
$companyModel = new Company();
$emailChangeRequestModel = new EmailChangeRequest();
$pendingRequestsCount = count($emailChangeRequestModel->getAllRequestsWithDetails('pending'));
$companyId = $_GET['id'] ?? null;
if (!$companyId) {
    header('Location: index.php?section=company');
    exit;
}
$company = $companyModel->find($companyId);
if (!$company) {
    header('Location: index.php?section=company');
    exit;
}
$planStyles = [
    'free' => ['label' => 'Free Plan', 'color' => '#6c757d', 'badge' => 'secondary'],
    'basic' => ['label' => 'Basic Plan', 'color' => '#3b82f6', 'badge' => 'primary'],
    'premium' => ['label' => 'Premium Plan', 'color' => '#c026d3', 'badge' => 'pink'],
    'enterprise' => ['label' => 'Enterprise Plan', 'color' => '#fbbf24', 'badge' => 'warning']
];
$planKey = strtolower($company['subscription_plan'] ?? 'free');
$planMeta = $planStyles[$planKey] ?? $planStyles['free'];
$stats = $companyModel->getCompanyStats($companyId);
$daysRemaining = null;
if (!empty($company['subscription_expiry']) && $planKey !== 'free') {
    $expiryTs = strtotime($company['subscription_expiry']);
    $todayTs = strtotime(date('Y-m-d'));
    $daysRemaining = max(0, (int) floor(($expiryTs - $todayTs) / (60 * 60 * 24)));
}
$cameFromOffer = false;
if ($planKey === 'premium' && !empty($company['subscription_expiry'])) {
    $expiryDate = strtotime($company['subscription_expiry']);
    $createdDate = strtotime($company['created_at'] ?? 'now');
    $oneYearFromCreation = strtotime('+1 year', $createdDate);
    $daysSinceCreation = ($createdDate - strtotime('now')) / (60 * 60 * 24);
    if (abs($expiryDate - $oneYearFromCreation) < (30 * 24 * 60 * 60) && $daysSinceCreation > -30) {
        $cameFromOffer = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape($company['company_name']); ?> - Company Details</title>
    <?php echo render_favicon_links(); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f6f8fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        .app-shell {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }
        :root {
            --primary-color: #4f46e5;
            --secondary-color: #4338ca;
            --sidebar-width: 236px;
            --header-height: 62px;
        }
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
        .brand-logo:hover {
            transform: scale(1.03);
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
        }
        .btn-primary:hover, .btn-primary:focus {
            background: linear-gradient(135deg, #4338ca 0%, #3730a3 100%);
        }
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .btn-outline-primary:hover, .btn-outline-primary:focus, .btn-outline-primary.show {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: #ffffff;
        }
        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.9rem 1rem;
            border-radius: 12px;
            color: rgba(255,255,255,0.85);
            margin-bottom: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .nav-link.active, .nav-link:hover {
            background: rgba(255,255,255,0.18);
            color: white;
        }
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
        .content-scroll {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 1.5rem 2rem;
        }
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
        .user-avatar-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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
        
        /* Mobile and Tablet Responsiveness (Drawer) */
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
            transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out;
            backdrop-filter: blur(2px);
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
                transition: left 0.3s ease-in-out;
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
        /* Unified Clean White Company Details Card */
        .cs-company-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #eef2f7;
            box-shadow: 0 2px 12px rgba(15, 23, 42, 0.04);
            padding: 1.5rem 1.75rem;
            margin-bottom: 1.25rem;
            display: grid;
            grid-template-columns: minmax(250px, 1.25fr) auto minmax(285px, 1.15fr);
            gap: 1.5rem;
            align-items: center;
        }
        .cs-company-primary {
            min-width: 0;
        }
        .cs-plan-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.36rem 0.9rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            margin-bottom: 0.7rem;
        }
        .cs-plan-badge.plan-free {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: #ffffff;
            border: none;
            box-shadow: 0 2px 6px rgba(108, 117, 125, 0.25);
        }
        .cs-plan-badge.plan-basic {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: #ffffff;
            border: none;
            box-shadow: 0 2px 6px rgba(59, 130, 246, 0.28);
        }
        .cs-plan-badge.plan-premium {
            background: linear-gradient(135deg, #c026d3 0%, #7c3aed 100%);
            color: #ffffff;
            border: none;
            box-shadow: 0 2px 6px rgba(192, 38, 211, 0.28);
        }
        .cs-plan-badge.plan-enterprise {
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
            color: #ffffff;
            border: none;
            text-shadow: 0 1px 1px rgba(0, 0, 0, 0.14);
            box-shadow: 0 2px 6px rgba(245, 158, 11, 0.3);
        }
        .cs-company-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 0.35rem 0;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }
        .cs-company-owner {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            font-size: 0.9rem;
            color: #475569;
            font-weight: 500;
            margin: 0 0 0.8rem 0;
        }
        .cs-company-owner i {
            color: #4f46e5;
            font-size: 0.84rem;
        }
        .cs-company-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem 1.2rem;
            font-size: 0.86rem;
            color: #334155;
        }
        .cs-meta-item {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            font-weight: 500;
            min-width: 0;
            overflow-wrap: anywhere;
            text-decoration: none;
            color: #334155;
        }
        a.cs-meta-item:hover {
            color: #4f46e5;
        }
        .cs-meta-item i {
            color: #4f46e5;
            font-size: 0.84rem;
            flex-shrink: 0;
        }
        .cs-meta-item.is-expiry {
            color: #4f46e5;
            font-weight: 600;
        }
        .cs-company-kpis {
            border-left: 1px solid #eef2f7;
            border-right: 1px solid #eef2f7;
            padding: 0.25rem 1.35rem;
            display: grid;
            grid-template-columns: repeat(3, minmax(78px, 1fr));
            gap: 0.5rem;
            align-items: center;
            text-align: center;
        }
        .cs-kpi-item {
            padding: 0.25rem 0.5rem;
            position: relative;
        }
        .cs-kpi-item + .cs-kpi-item::before {
            content: '';
            position: absolute;
            left: -0.25rem;
            top: 15%;
            height: 70%;
            width: 1px;
            background: #f1f5f9;
        }
        .cs-kpi-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin: 0 auto 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
        }
        .cs-kpi-icon.is-customers { background: #eef2ff; color: #4f46e5; }
        .cs-kpi-icon.is-orders { background: #e0f2fe; color: #0284c7; }
        .cs-kpi-icon.is-users { background: #dcfce7; color: #16a34a; }
        .cs-kpi-value {
            font-size: 1.35rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.15;
        }
        .cs-kpi-label {
            font-size: 0.78rem;
            font-weight: 500;
            color: #64748b;
            margin-top: 0.15rem;
        }
        .cs-company-info {
            min-width: 0;
        }
        .cs-info-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 0.75rem 0;
            display: flex;
            align-items: center;
            gap: 0.45rem;
        }
        .cs-info-title i {
            color: #4f46e5;
            font-size: 0.95rem;
        }
        .cs-info-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.5rem 1rem;
        }
        .cs-info-list li {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            color: #475569;
            font-size: 0.84rem;
            line-height: 1.35;
            min-width: 0;
        }
        .cs-info-list li.is-full {
            grid-column: 1 / -1;
        }
        .cs-info-list li i {
            color: #4f46e5;
            width: 14px;
            text-align: center;
            font-size: 0.8rem;
            margin-top: 0.2rem;
            flex-shrink: 0;
        }
        .cs-info-list li span,
        .cs-info-list li a {
            overflow-wrap: anywhere;
        }
        .cs-info-list li a {
            color: #4f46e5;
            text-decoration: none;
        }
        .cs-info-list li a:hover {
            text-decoration: underline;
        }
        @media (max-width: 1180px) {
            .cs-company-card {
                grid-template-columns: 1.2fr auto;
                gap: 1.25rem;
            }
            .cs-company-kpis {
                border-right: none;
                padding-right: 0;
            }
            .cs-company-info {
                grid-column: 1 / -1;
                border-top: 1px solid #eef2f7;
                padding-top: 1rem;
            }
        }
        @media (max-width: 768px) {
            .cs-company-card {
                grid-template-columns: 1fr;
                padding: 1.2rem;
                gap: 1rem;
            }
            .cs-company-kpis {
                border-left: none;
                border-right: none;
                border-top: 1px solid #eef2f7;
                border-bottom: 1px solid #eef2f7;
                padding: 0.85rem 0;
            }
            .cs-company-info {
                border-top: none;
                padding-top: 0;
            }
        }
        @media (max-width: 576px) {
            .cs-info-list {
                grid-template-columns: 1fr;
            }
        }
        .company-info-card {
            background: white;
            border-radius: 18px;
            padding: 1.5rem;
            box-shadow: 0 15px 35px rgba(15,23,42,0.1);
            margin-bottom: 1.5rem;
        }
        .company-info-card h5 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .company-info-card h5 i {
            color: #3b82f6;
        }
        .company-info-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }
        .company-info-list li {
            padding: 0.6rem 0;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            color: #475569;
            font-size: 0.95rem;
        }
        .company-info-list li.full-width {
            grid-column: span 2;
        }
        .company-info-list li i {
            color: #3b82f6;
            margin-top: 0.2rem;
            flex-shrink: 0;
        }
        .company-info-list li span {
            word-break: break-word;
        }
        @media (max-width: 768px) {
            .company-info-list {
                grid-template-columns: 1fr;
            }
            .company-info-list li.full-width {
                grid-column: span 1;
            }
        }
        .tab-nav {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #eef2f7;
            padding: 0 1.25rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
            display: flex;
            align-items: center;
            gap: 1.5rem;
            overflow-x: auto;
            scrollbar-width: none;
        }
        .tab-nav::-webkit-scrollbar {
            display: none;
        }
        .tab-button {
            padding: 0.95rem 0.25rem;
            border: none;
            border-bottom: 3px solid transparent;
            background: transparent;
            color: #475569;
            font-weight: 500;
            font-size: 0.92rem;
            border-radius: 0;
            cursor: pointer;
            transition: color 0.2s ease, border-color 0.2s ease;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
        }
        .tab-button:hover {
            color: #4f46e5;
        }
        .tab-button.active {
            background: transparent;
            color: #4f46e5;
            font-weight: 600;
            border-bottom-color: #4f46e5;
        }
        .data-table-container {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #eef2f7;
            box-shadow: 0 2px 12px rgba(15, 23, 42, 0.04);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        .table {
            margin-bottom: 0;
            font-size: 0.94rem;
        }
        .table thead th {
            background: #f8fafc;
            border-bottom: 1px solid #eef2f7;
            border-top: none;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            padding: 0.95rem 1.25rem;
            white-space: nowrap;
        }
        .table tbody td {
            padding: 0.95rem 1.25rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            background: #ffffff !important;
        }
        .table tbody tr:hover td {
            background: #f8fafc !important;
        }
        .cd-view-btn {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #eef2ff;
            color: #4f46e5;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        .cd-view-btn:hover {
            background: #4f46e5;
            color: #ffffff;
            transform: translateY(-1px);
        }
        .cd-empty-state {
            padding: 3rem 1.5rem;
            text-align: center;
        }
        .cd-empty-icon {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            background: #eef2ff;
            color: #4f46e5;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 0.85rem;
        }
        .back-btn a {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.55rem 1.05rem;
            background: #ffffff;
            color: #334155;
            border: 1px solid #e2e8f0;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
            transition: all 0.2s ease;
        }
        .back-btn a:hover {
            background: #f8faff;
            border-color: #c7d2fe;
            color: #4f46e5;
        }
        .cd-update-sub-btn {
            border-radius: 10px;
            padding: 0.58rem 1.15rem;
            font-weight: 600;
            font-size: 0.9rem;
            box-shadow: 0 3px 10px rgba(79, 70, 229, 0.22);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.82rem;
        }
        .btn-sm {
            padding: 0.4rem 0.6rem;
            border-radius: 20px;
            border: none;
        }
        .btn-sm i {
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="app-shell">
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
            <nav class="d-flex flex-column gap-1">
                <a href="index.php?section=requests" class="nav-link">
                    <i class="fas fa-envelope-open-text"></i>
                    Email Requests
                </a>
                <a href="index.php?section=company" class="nav-link active">
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
                <a href="settings.php" class="nav-link">
                    <i class="fas fa-sliders-h"></i>
                    Global Settings
                </a>
            </nav>
            <div class="mt-auto">
                <a href="../admin/logout.php" class="nav-link" onclick="return confirm('Are you sure you want to logout?');">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </aside>
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <main class="main-content">
            <header class="top-header">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-link p-1 text-primary d-lg-none" id="mobileMenuBtn" aria-label="Toggle Navigation" style="font-size: 1.25rem; text-decoration: none; color: #4f46e5 !important; line-height: 1;">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h4 class="mb-0 page-main-title fw-bold text-dark" style="text-transform: capitalize; font-size: 1.25rem; letter-spacing: -0.3px;">Company Details</h4>
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

            <div class="content-scroll">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div class="back-btn mb-0">
                        <a href="index.php?section=company">
                            <i class="fas fa-arrow-left"></i>
                            Back to Companies
                        </a>
                    </div>
                    <div>
                        <button type="button" class="btn btn-primary cd-update-sub-btn" onclick="openSubscriptionModal()">
                            <i class="fas fa-crown"></i>
                            Update Subscription
                        </button>
                    </div>
                </div>
                <?php
                $locationParts = array_filter([trim($company['city'] ?? ''), trim($company['state'] ?? '')], fn($v) => $v !== '' && $v !== '-');
                $locationDisplay = !empty($locationParts) ? implode(', ', $locationParts) : 'Not provided';
                ?>
                <div class="cs-company-card">
                    <div class="cs-company-primary">
                        <span class="cs-plan-badge plan-<?php echo htmlspecialchars($planKey); ?>">
                            <i class="fas fa-crown"></i>
                            <span><?php echo htmlspecialchars($planMeta['label']); ?></span>
                        </span>
                        <h3 class="cs-company-title"><?php echo escape($company['company_name']); ?></h3>
                        <p class="cs-company-owner">
                            <i class="fas fa-user"></i>
                            <span>Owner: <?php echo escape($company['owner_name'] ?? 'N/A'); ?></span>
                        </p>
                        <div class="cs-company-meta">
                            <?php if (!empty($company['business_email'])): ?>
                                <a href="mailto:<?php echo htmlspecialchars($company['business_email']); ?>" class="cs-meta-item">
                                    <i class="fas fa-envelope"></i>
                                    <span><?php echo htmlspecialchars($company['business_email']); ?></span>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($company['business_phone'])): ?>
                                <a href="tel:<?php echo htmlspecialchars($company['business_phone']); ?>" class="cs-meta-item">
                                    <i class="fas fa-phone"></i>
                                    <span><?php echo htmlspecialchars($company['business_phone']); ?></span>
                                </a>
                            <?php endif; ?>
                            <?php if ($daysRemaining !== null): ?>
                                <span class="cs-meta-item is-expiry">
                                    <i class="far fa-clock"></i>
                                    <span><?php echo $daysRemaining; ?> days left</span>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="cs-company-kpis">
                        <div class="cs-kpi-item">
                            <div class="cs-kpi-icon is-customers"><i class="fas fa-users"></i></div>
                            <div class="cs-kpi-value"><?php echo $stats['total_customers'] ?? 0; ?></div>
                            <div class="cs-kpi-label">Customers</div>
                        </div>
                        <div class="cs-kpi-item">
                            <div class="cs-kpi-icon is-orders"><i class="fas fa-shopping-cart"></i></div>
                            <div class="cs-kpi-value"><?php echo $stats['total_orders'] ?? 0; ?></div>
                            <div class="cs-kpi-label">Orders</div>
                        </div>
                        <div class="cs-kpi-item">
                            <div class="cs-kpi-icon is-users"><i class="fas fa-user"></i></div>
                            <div class="cs-kpi-value"><?php echo $stats['total_users'] ?? 0; ?></div>
                            <div class="cs-kpi-label">Users</div>
                        </div>
                    </div>

                    <div class="cs-company-info">
                        <h6 class="cs-info-title"><i class="fas fa-info-circle"></i>Company Info</h6>
                        <ul class="cs-info-list">
                            <li>
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($locationDisplay); ?></span>
                            </li>
                            <li>
                                <i class="fas fa-globe"></i>
                                <span>
                                    <?php if (!empty($company['website']) && $company['website'] !== 'Not provided'): ?>
                                        <a href="<?php echo htmlspecialchars($company['website']); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($company['website']); ?></a>
                                    <?php else: ?>
                                        Not provided
                                    <?php endif; ?>
                                </span>
                            </li>
                            <li>
                                <i class="fas fa-file-invoice"></i>
                                <span>GST: <?php echo !empty($company['tax_number']) ? htmlspecialchars($company['tax_number']) : 'Not provided'; ?></span>
                            </li>
                            <li>
                                <i class="far fa-calendar-alt"></i>
                                <span>Joined: <?php echo !empty($company['created_at']) ? date('M d, Y', strtotime($company['created_at'])) : '—'; ?></span>
                            </li>
                            <?php if (!empty($company['subscription_expiry']) && $planKey !== 'free'): ?>
                                <li>
                                    <i class="far fa-calendar-check"></i>
                                    <span>Expiry: <?php echo date('M d, Y', strtotime($company['subscription_expiry'])); ?></span>
                                </li>
                            <?php elseif ($planKey === 'free'): ?>
                                <li>
                                    <i class="far fa-calendar-check"></i>
                                    <span>Duration: Lifetime</span>
                                </li>
                            <?php endif; ?>
                            <li>
                                <i class="fas fa-map-pin"></i>
                                <span><?php echo !empty($company['business_address']) ? htmlspecialchars($company['business_address']) : 'No address provided'; ?></span>
                            </li>
                            <?php if ($cameFromOffer): ?>
                                <li class="is-full">
                                    <span class="badge" style="background: #eef2ff; color: #4f46e5; border: 1px solid #c7d2fe; padding: 0.42rem 0.85rem;">
                                        <i class="fas fa-gift me-1"></i>Registered via Promotional Offer (1 Year Free Professional Plan)
                                    </span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                <div class="tab-nav">
                    <button class="tab-button active" data-tab="customers">
                        <i class="fas fa-users me-2"></i>Customers
                    </button>
                    <button class="tab-button" data-tab="orders">
                        <i class="fas fa-shopping-cart me-2"></i>Orders
                    </button>
                    <button class="tab-button" data-tab="users">
                        <i class="fas fa-user-friends me-2"></i>Users
                    </button>
                    <button class="tab-button" data-tab="expenses">
                        <i class="fas fa-money-bill-wave me-2"></i>Expenses
                    </button>
                    <button class="tab-button" data-tab="reports">
                        <i class="fas fa-chart-bar me-2"></i>Reports
                    </button>
                    <button class="tab-button" data-tab="contact">
                        <i class="fas fa-address-book me-2"></i>Contact
                    </button>
                </div>
                <div class="data-table-container">
                    <div id="tab-content">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3 text-muted">Loading data...</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const companyId = <?php echo $companyId; ?>;
        let currentTab = 'customers';
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentTab = this.getAttribute('data-tab');
                loadTabData(currentTab);
            });
        });
        function loadTabData(tab) {
            const contentDiv = document.getElementById('tab-content');
            contentDiv.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-3 text-muted">Loading data...</p></div>';

            fetch(`ajax/get_company_data.php?company_id=${companyId}&type=${tab}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Data received:', data);
                    if (data.success) {
                        if (data.data && data.data.length > 0) {
                            contentDiv.innerHTML = renderTable(tab, data.data);
                        } else {
                            contentDiv.innerHTML = '<div class="cd-empty-state"><div class="cd-empty-icon"><i class="fas fa-inbox"></i></div><h5 class="fw-bold mb-1" style="color: #0f172a;">No data available</h5><p class="text-muted mb-0 small">There are no records for this tab yet.</p></div>';
                        }
                    } else {
                        contentDiv.innerHTML = `<div class="cd-empty-state"><div class="cd-empty-icon"><i class="fas fa-inbox"></i></div><h5 class="fw-bold mb-1" style="color: #0f172a;">No data available</h5><p class="text-muted mb-0 small">${data.message || 'No records found.'}</p></div>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    contentDiv.innerHTML = '<div class="text-center py-5 text-danger">Error loading data. Please check console for details.</div>';
                });
        }
        function renderTable(type, data) {
            if (!data || data.length === 0) {
                return '<div class="cd-empty-state"><div class="cd-empty-icon"><i class="fas fa-inbox"></i></div><h5 class="fw-bold mb-1" style="color: #0f172a;">No data available</h5><p class="text-muted mb-0 small">There are no records for this tab yet.</p></div>';
            }
            const headers = {
                customers: ['ID', 'Name', 'Code', 'Email', 'Actions'],
                orders: ['ID', 'Order Number', 'Customer', 'Status', 'Total', 'Date', 'Actions'],
                users: ['ID', 'Name', 'Email', 'Role', 'Status', 'Created At', 'Actions'],
                invoices: ['Invoice #', 'Customer', 'Order #', 'Invoice Date', 'Due Date', 'Amount', 'Paid', 'Status', 'Actions'],
                expenses: ['ID', 'Category', 'Description', 'Amount', 'Date', 'Status', 'Actions'],
                reports: ['ID', 'Report Type', 'Period', 'Generated At', 'Actions'],
                contact: ['Name', 'Company', 'Contact Info', 'Category', 'Status', 'Actions']
            };
            const fields = {
                customers: ['id', 'name', 'customer_code', 'email', 'actions'],
                orders: ['id', 'order_number', 'customer_name', 'status', 'total', 'created_at', 'actions'],
                users: ['id', 'name', 'email', 'role', 'status', 'created_at', 'actions'],
                invoices: ['invoice_number', 'customer', 'order_number', 'invoice_date', 'due_date', 'total_amount', 'paid_amount', 'payment_status', 'actions'],
                expenses: ['id', 'category', 'description', 'amount', 'date', 'status', 'actions'],
                reports: ['id', 'report_type', 'period', 'generated_at', 'actions'],
                contact: ['name', 'company', 'contact_info', 'category', 'status', 'actions']
            };
            const headerRow = headers[type].map(h => `<th>${h}</th>`).join('');
            const rows = data.map(row => {
                const cells = fields[type].map(field => {
                    let value = row[field] || '-';
                    if (type === 'reports') {
                        if (field === 'generated_at') {
                            if (value && value !== '-') {
                                const date = new Date(value);
                                value = date.toLocaleDateString('en-IN', { year: 'numeric', month: 'short', day: 'numeric' });
                            }
                        } else if (field === 'actions') {
                            value = `<button class="cd-view-btn" onclick="viewCompanyReports(${companyId})" title="View Reports">
                                        <i class="fas fa-eye"></i>
                                     </button>`;
                        }
                    }
                    else if (type === 'orders') {
                        if (field === 'order_number') {
                            value = `<span class="badge bg-primary">${row.order_number}</span>`;
                        } else if (field === 'customer_name') {
                            value = row.customer_name || 'N/A';
                        } else if (field === 'status') {
                            const statusClass = row.status === 'completed' ? 'success' : 
                                              row.status === 'in_progress' ? 'danger' : 
                                              row.status === 'pending' ? 'warning' : 'secondary';
                            value = `<span class="badge bg-${statusClass}">${(row.status || 'pending').replace('_', ' ')}</span>`;
                        } else if (field === 'total') {
                            value = `₹${parseFloat(row.total_amount || 0).toFixed(2)}`;
                        } else if (field === 'created_at') {
                            if (value && value !== '-') {
                                const date = new Date(value);
                                value = date.toLocaleDateString('en-IN', { year: 'numeric', month: 'short', day: 'numeric' });
                            }
                        } else if (field === 'actions') {
                            value = `<button class="cd-view-btn" onclick="viewOrderDetails(${row.id})" title="View Details">
                                        <i class="fas fa-eye"></i>
                                     </button>`;
                        }
                    }
                    else if (type === 'reports') {
                        if (field === 'actions') {
                            value = `<button class="cd-view-btn" onclick="viewCompanyReports(${companyId})" title="View Reports">
                                        <i class="fas fa-eye"></i>
                                     </button>`;
                        } else if (field === 'generated_at') {
                            if (value && value !== '-') {
                                const date = new Date(value);
                                value = date.toLocaleDateString('en-IN', { year: 'numeric', month: 'short', day: 'numeric' });
                            }
                        }
                    }
                    else if (type === 'customers') {
                        if (field === 'actions') {
                            const customerId = row.id || row.customer_id || 0;
                            value = `<button class="cd-view-btn" onclick="viewCustomerDetails(${customerId})" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>`;
                        } else if (field === 'name') {
                            value = `<strong>${row.name || '-'}</strong>`;
                        } else if (field === 'customer_code') {
                            value = row.customer_code ? `<span class="badge bg-secondary">${row.customer_code}</span>` : '-';
                        } else if (field === 'email') {
                            value = row.email ? `<span style="color: #475569;">${row.email}</span>` : '-';
                        }
                    }
                    else if (type === 'invoices') {
                        if (field === 'invoice_number') {
                            value = `<span class="badge bg-primary">${row.invoice_number}</span>`;
                        } else if (field === 'customer') {
                            value = `<div><strong>${row.customer_name || '-'}</strong>${row.customer_code ? '<br><small class="text-muted">' + row.customer_code + '</small>' : ''}</div>`;
                        } else if (field === 'order_number') {
                            value = row.order_number ? `<span class="badge bg-info">${row.order_number}</span>` : '-';
                        } else if (field === 'invoice_date') {
                            if (row.invoice_date) {
                                const date = new Date(row.invoice_date);
                                value = date.toLocaleDateString('en-IN', { year: 'numeric', month: 'short', day: 'numeric' });
                            }
                        } else if (field === 'due_date') {
                            if (row.due_date) {
                                const date = new Date(row.due_date);
                                const isOverdue = date < new Date() && row.payment_status !== 'paid';
                                value = `<span class="${isOverdue ? 'text-danger fw-bold' : ''}">${date.toLocaleDateString('en-IN', { year: 'numeric', month: 'short', day: 'numeric' })}</span>`;
                            }
                        } else if (field === 'total_amount') {
                            value = `<strong>₹${parseFloat(row.total_amount || 0).toFixed(2)}</strong>`;
                            if (row.tax_amount > 0) {
                                value += `<br><small class="text-muted">Tax: ₹${parseFloat(row.tax_amount || 0).toFixed(2)}</small>`;
                            }
                        } else if (field === 'paid_amount') {
                            value = `₹${parseFloat(row.paid_amount || 0).toFixed(2)}`;
                        } else if (field === 'payment_status') {
                            const statusClass = row.payment_status === 'paid' ? 'success' : 
                                              row.payment_status === 'partial' ? 'warning' : 'danger';
                            value = `<span class="badge bg-${statusClass}">${(row.payment_status || 'due').charAt(0).toUpperCase() + (row.payment_status || 'due').slice(1)}</span>`;
                        } else if (field === 'actions') {
                            value = `
                                <button class="btn btn-sm btn-info" onclick="viewInvoiceDetails(${row.id})" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                            `;
                        }
                    }
                    else if (type === 'expenses') {
                        if (field === 'category') {
                            value = `<span class="badge bg-info">${row.category || 'Other'}</span>`;
                        } else if (field === 'amount') {
                            value = `₹${parseFloat(row.amount || 0).toFixed(2)}`;
                        } else if (field === 'date') {
                            if (value && value !== '-') {
                                const date = new Date(value);
                                value = date.toLocaleDateString('en-IN', { year: 'numeric', month: 'short', day: 'numeric' });
                            }
                        } else if (field === 'status') {
                            const statusClass = row.status === 'active' ? 'success' : 'secondary';
                            value = `<span class="badge bg-${statusClass}">${(row.status || 'active').charAt(0).toUpperCase() + (row.status || 'active').slice(1)}</span>`;
                        } else if (field === 'actions') {
                            value = `
                                <button class="cd-view-btn" onclick="viewExpenseDetails(${row.id})" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                            `;
                        }
                    }
                    else if (type === 'contact') {
                        if (field === 'name') {
                            value = `<strong>${row.name || '-'}</strong>`;
                        } else if (field === 'company') {
                            value = row.company || '-';
                        } else if (field === 'contact_info') {
                            const phone = row.phone ? `<div><i class="fas fa-phone me-2"></i>${row.phone}</div>` : '';
                            const email = row.email ? `<div><i class="fas fa-envelope me-2"></i>${row.email}</div>` : '';
                            value = phone || email ? `<div>${phone}${email}</div>` : '-';
                        } else if (field === 'category') {
                            value = `<span class="badge bg-info">${row.category || 'General'}</span>`;
                        } else if (field === 'status') {
                            const statusClass = row.status === 'active' ? 'success' : 'secondary';
                            value = `<span class="badge bg-${statusClass}">${row.status || 'active'}</span>`;
                        } else if (field === 'actions') {
                            value = `
                                <button class="cd-view-btn" onclick="viewContactDetails(${row.id})" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                            `;
                        }
                    }
                    else if (type === 'users') {
                        if (field === 'name') {
                            value = `<strong>${row.name || '-'}</strong>`;
                        } else if (field === 'email') {
                            value = row.email ? `<span style="color: #475569;">${row.email}</span>` : '-';
                        } else if (field === 'role') {
                            const roleClass = row.role === 'admin' ? 'danger' : 
                                            row.role === 'cashier' ? 'info' : 
                                            row.role === 'tailor' ? 'warning' : 'secondary';
                            value = `<span class="badge bg-${roleClass}">${(row.role || 'staff').charAt(0).toUpperCase() + (row.role || 'staff').slice(1)}</span>`;
                        } else if (field === 'status') {
                            const statusClass = row.status === 'active' ? 'success' : 'secondary';
                            value = `<span class="badge bg-${statusClass}">${(row.status || 'active').charAt(0).toUpperCase() + (row.status || 'active').slice(1)}</span>`;
                        } else if (field === 'created_at') {
                            if (value && value !== '-') {
                                const date = new Date(value);
                                value = date.toLocaleDateString('en-IN', { year: 'numeric', month: 'short', day: 'numeric' });
                            }
                        } else if (field === 'actions') {
                            value = `
                                <button class="cd-view-btn" onclick="viewUserDetails(${row.id})" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                            `;
                        }
                    } else {
                        if (field === 'status') {
                            const statusClass = value === 'active' || value === 'completed' || value === 'paid' ? 'success' : 
                                              value === 'pending' ? 'warning' : 'danger';
                            value = `<span class="badge bg-${statusClass}">${value}</span>`;
                        }
                        if (field === 'amount' || field === 'total') {
                            value = `₹${parseFloat(value || 0).toFixed(2)}`;
                        }
                        if (field === 'created_at' || field === 'date' || field === 'generated_at') {
                            if (value && value !== '-') {
                                const date = new Date(value);
                                value = date.toLocaleDateString('en-IN', { year: 'numeric', month: 'short', day: 'numeric' });
                            }
                        }
                    }
                    return `<td>${value}</td>`;
                }).join('');
                return `<tr>${cells}</tr>`;
            }).join('');
            return `
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>${headerRow}</tr>
                        </thead>
                        <tbody>
                            ${rows}
                        </tbody>
                    </table>
                </div>
            `;
        }
        function viewExpenseDetails(expenseId) {
            const modalTitle = document.getElementById('expenseDetailsModalLabel');
            const modalBody = document.getElementById('expenseDetailsModalContent');
            const expenseModal = new bootstrap.Modal(document.getElementById('expenseDetailsModal'));
            modalTitle.innerHTML = `<i class="fas fa-money-bill-wave me-2"></i>Expense Details`;
            modalBody.innerHTML = `<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-3 text-muted">Loading expense details...</p></div>`;
            expenseModal.show();
            fetch(`ajax/get_expense_details.php?expense_id=${expenseId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        modalBody.innerHTML = renderExpenseDetails(data.expense);
                    } else {
                        modalBody.innerHTML = `<div class="alert alert-danger">${data.message || 'Failed to load expense details'}</div>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalBody.innerHTML = '<div class="alert alert-danger">Error loading expense details. Please try again.</div>';
                });
        }

        function renderExpenseDetails(expense) {
            return `
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Expense ID</label>
                        <p class="mb-0">#${expense.id || 'N/A'}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Category</label>
                        <p class="mb-0"><span class="badge bg-info">${expense.category || 'Other'}</span></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Amount</label>
                        <p class="mb-0 h5 text-danger">₹${parseFloat(expense.amount || 0).toFixed(2)}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Expense Date</label>
                        <p class="mb-0">${expense.expense_date ? new Date(expense.expense_date).toLocaleDateString('en-IN', { year: 'numeric', month: 'long', day: 'numeric' }) : '-'}</p>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold text-muted">Description</label>
                        <p class="mb-0">${expense.description || 'No description provided'}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Status</label>
                        <p class="mb-0">
                            <span class="badge bg-${expense.status === 'active' ? 'success' : 'secondary'}">${expense.status || 'active'}</span>
                        </p>
                    </div>
                    ${expense.payment_method ? `
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Payment Method</label>
                        <p class="mb-0">${expense.payment_method}</p>
                    </div>
                    ` : ''}
                    ${expense.receipt_number ? `
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Receipt Number</label>
                        <p class="mb-0">${expense.receipt_number}</p>
                    </div>
                    ` : ''}
                    ${expense.vendor ? `
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Vendor/Supplier</label>
                        <p class="mb-0">${expense.vendor}</p>
                    </div>
                    ` : ''}
                    <div class="col-12 mb-3">
                        <hr>
                        <h6 class="fw-bold text-muted mb-3"><i class="fas fa-user-circle me-2"></i>Created By</h6>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Created By</label>
                                <p class="mb-0">${expense.created_by_name || 'N/A'}</p>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Created At</label>
                                <p class="mb-0">${expense.created_at ? new Date(expense.created_at).toLocaleDateString('en-IN', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-'}</p>
                            </div>
                            ${expense.created_from ? `
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Created From</label>
                                <p class="mb-0"><i class="fas fa-laptop me-2"></i>${expense.created_from}</p>
                            </div>
                            ` : ''}
                            ${expense.ip_address ? `
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">IP Address</label>
                                <p class="mb-0"><i class="fas fa-network-wired me-2"></i>${expense.ip_address}</p>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    ${expense.updated_at ? `
                    <div class="col-12 mb-3">
                        <hr>
                        <h6 class="fw-bold text-muted mb-3"><i class="fas fa-edit me-2"></i>Last Updated</h6>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Updated At</label>
                                <p class="mb-0">${new Date(expense.updated_at).toLocaleDateString('en-IN', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                            </div>
                        </div>
                    </div>
                    ` : ''}
                    ${expense.notes ? `
                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold text-muted">Notes</label>
                        <p class="mb-0">${expense.notes}</p>
                    </div>
                    ` : ''}
                </div>
            `;
        }
        function viewContactDetails(contactId) {
            const modalTitle = document.getElementById('contactDetailsModalLabel');
            const modalBody = document.getElementById('contactDetailsModalContent');
            const contactModal = new bootstrap.Modal(document.getElementById('contactDetailsModal'));

            modalTitle.innerHTML = `<i class="fas fa-user me-2"></i>Contact Details`;
            modalBody.innerHTML = `<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-3 text-muted">Loading contact details...</p></div>`;
            contactModal.show();
            fetch(`ajax/get_contact_details.php?contact_id=${contactId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        modalBody.innerHTML = renderContactDetails(data.contact);
                    } else {
                        modalBody.innerHTML = `<div class="alert alert-danger">${data.message || 'Failed to load contact details'}</div>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalBody.innerHTML = '<div class="alert alert-danger">Error loading contact details. Please try again.</div>';
                });
        }
        function renderContactDetails(contact) {
            return `
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Name</label>
                        <p class="mb-0">${contact.name || 'N/A'}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Company</label>
                        <p class="mb-0">${contact.company || '-'}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Email</label>
                        <p class="mb-0">
                            ${contact.email ? `<a href="mailto:${contact.email}"><i class="fas fa-envelope me-2"></i>${contact.email}</a>` : '-'}
                        </p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Phone</label>
                        <p class="mb-0">
                            ${contact.phone ? `<a href="tel:${contact.phone}"><i class="fas fa-phone me-2"></i>${contact.phone}</a>` : '-'}
                        </p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Category</label>
                        <p class="mb-0">
                            <span class="badge bg-info">${contact.category || 'General'}</span>
                        </p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Status</label>
                        <p class="mb-0">
                            <span class="badge bg-${contact.status === 'active' ? 'success' : 'secondary'}">${contact.status || 'active'}</span>
                        </p>
                    </div>
                    ${contact.address ? `
                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold text-muted">Address</label>
                        <p class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>${contact.address}</p>
                    </div>
                    ` : ''}
                    ${contact.notes ? `
                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold text-muted">Notes</label>
                        <p class="mb-0">${contact.notes}</p>
                    </div>
                    ` : ''}
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Created At</label>
                        <p class="mb-0">${contact.created_at ? new Date(contact.created_at).toLocaleDateString('en-IN', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-'}</p>
                    </div>
                    ${contact.updated_at ? `
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Updated At</label>
                        <p class="mb-0">${new Date(contact.updated_at).toLocaleDateString('en-IN', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                    </div>
                    ` : ''}
                </div>
            `;
        }
        function viewUserDetails(userId) {
            const modalTitle = document.getElementById('userDetailsModalLabel');
            const modalBody = document.getElementById('userDetailsModalContent');
            const userModal = new bootstrap.Modal(document.getElementById('userDetailsModal'));

            modalTitle.innerHTML = `<i class="fas fa-user me-2"></i>User Details`;
            modalBody.innerHTML = `<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-3 text-muted">Loading user details...</p></div>`;
            userModal.show();

            fetch(`ajax/get_user_details.php?user_id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        modalBody.innerHTML = renderUserDetails(data.user);
                    } else {
                        modalBody.innerHTML = `<div class="alert alert-danger">${data.message || 'Failed to load user details'}</div>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalBody.innerHTML = '<div class="alert alert-danger">Error loading user details. Please try again.</div>';
                });
        }
        function renderUserDetails(user) {
            const roleClass = user.role === 'admin' ? 'danger' : 
                            user.role === 'cashier' ? 'info' : 
                            user.role === 'tailor' ? 'warning' : 'secondary';
            
            return `
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">User ID</label>
                        <p class="mb-0">#${user.id || 'N/A'}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Status</label>
                        <p class="mb-0">
                            <span class="badge bg-${user.status === 'active' ? 'success' : 'secondary'}">${(user.status || 'active').charAt(0).toUpperCase() + (user.status || 'active').slice(1)}</span>
                        </p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Full Name</label>
                        <p class="mb-0">${user.full_name || user.name || 'N/A'}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Username</label>
                        <p class="mb-0">${user.username || '-'}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Email</label>
                        <p class="mb-0">
                            ${user.email ? `<a href="mailto:${user.email}"><i class="fas fa-envelope me-2"></i>${user.email}</a>` : '-'}
                        </p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Phone</label>
                        <p class="mb-0">
                            ${user.phone && user.phone !== '-' ? `<a href="tel:${user.phone}"><i class="fas fa-phone me-2"></i>${user.phone}</a>` : '-'}
                        </p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Role</label>
                        <p class="mb-0">
                            <span class="badge bg-${roleClass}">${(user.role || 'staff').charAt(0).toUpperCase() + (user.role || 'staff').slice(1)}</span>
                        </p>
                    </div>
                    ${user.company_name ? `
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Company</label>
                        <p class="mb-0"><i class="fas fa-building me-2"></i>${user.company_name}</p>
                    </div>
                    ` : ''}
                    ${user.address && user.address !== '-' ? `
                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold text-muted">Address</label>
                        <p class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>${user.address}</p>
                    </div>
                    ` : ''}
                    <div class="col-12 mb-3">
                        <hr>
                        <h6 class="fw-bold text-muted mb-3"><i class="fas fa-clock me-2"></i>Timeline</h6>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Created At</label>
                                <p class="mb-0">${user.created_at ? new Date(user.created_at).toLocaleDateString('en-IN', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-'}</p>
                            </div>
                            ${user.updated_at ? `
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Updated At</label>
                                <p class="mb-0">${new Date(user.updated_at).toLocaleDateString('en-IN', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                            </div>
                            ` : ''}
                            ${user.last_login ? `
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Last Login</label>
                                <p class="mb-0"><i class="fas fa-sign-in-alt me-2"></i>${new Date(user.last_login).toLocaleDateString('en-IN', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                            </div>
                            ` : `
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Last Login</label>
                                <p class="mb-0 text-muted"><i class="fas fa-sign-in-alt me-2"></i>Never logged in</p>
                            </div>
                            `}
                        </div>
                    </div>
                    ${user.created_by_name ? `
                    <div class="col-12 mb-3">
                        <hr>
                        <h6 class="fw-bold text-muted mb-3"><i class="fas fa-user-plus me-2"></i>Account Creation</h6>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Created By</label>
                                <p class="mb-0"><i class="fas fa-user-circle me-2"></i>${user.created_by_name}</p>
                            </div>
                        </div>
                    </div>
                    ` : ''}
                </div>
            `;
        }
        function viewCustomerDetails(customerId) {
            const modalTitle = document.getElementById('customerDetailsModalLabel');
            const modalBody = document.getElementById('customerDetailsModalContent');
            const customerModal = new bootstrap.Modal(document.getElementById('customerDetailsModal'));

            modalTitle.innerHTML = `<i class="fas fa-user me-2"></i>Customer Details`;
            modalBody.innerHTML = `<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-3 text-muted">Loading customer details...</p></div>`;
            customerModal.show();

            fetch(`ajax/get_customer_details.php?customer_id=${customerId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        modalBody.innerHTML = renderCustomerDetails(data.customer);
                    } else {
                        modalBody.innerHTML = `<div class="alert alert-danger">${data.message || 'Failed to load customer details'}</div>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalBody.innerHTML = '<div class="alert alert-danger">Error loading customer details. Please try again.</div>';
                });
        }
        function renderCustomerDetails(customer) {
            const statusClass = customer.status === 'active' ? 'success' : 'secondary';
            
            return `
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Customer ID</label>
                        <p class="mb-0">#${customer.id || 'N/A'}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Customer Code</label>
                        <p class="mb-0">${customer.customer_code ? `<span class="badge bg-secondary">${customer.customer_code}</span>` : '-'}</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Status</label>
                        <p class="mb-0">
                            <span class="badge bg-${statusClass}">${(customer.status || 'active').charAt(0).toUpperCase() + (customer.status || 'active').slice(1)}</span>
                        </p>
                    </div>
                    <div class="col-12 mb-2">
                        <hr>
                        <h6 class="fw-bold text-muted mb-2"><i class="fas fa-user me-2"></i>Personal Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-1">
                                <label class="form-label fw-bold text-muted small mb-0">Full Name</label>
                                <p class="mb-0 small">${customer.name || 'N/A'}</p>
                            </div>
                            ${customer.email && customer.email !== '-' ? `
                            <div class="col-md-6 mb-1">
                                <label class="form-label fw-bold text-muted small mb-0">Email</label>
                                <p class="mb-0 small"><a href="mailto:${customer.email}"><i class="fas fa-envelope me-1"></i>${customer.email}</a></p>
                            </div>
                            ` : ''}
                            ${customer.phone && customer.phone !== '-' ? `
                            <div class="col-md-6 mb-1">
                                <label class="form-label fw-bold text-muted small mb-0">Phone</label>
                                <p class="mb-0 small"><a href="tel:${customer.phone}"><i class="fas fa-phone me-1"></i>${customer.phone}</a></p>
                            </div>
                            ` : ''}
                            ${customer.date_of_birth ? `
                            <div class="col-md-6 mb-1">
                                <label class="form-label fw-bold text-muted small mb-0">Date of Birth</label>
                                <p class="mb-0 small">${new Date(customer.date_of_birth).toLocaleDateString('en-IN', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    <div class="col-12 mb-3">
                        <hr>
                        <h6 class="fw-bold text-muted mb-3"><i class="fas fa-map-marker-alt me-2"></i>Address Information</h6>
                        <div class="row">
                            <div class="col-12 mb-2">
                                <label class="form-label fw-bold text-muted small">Full Address</label>
                                <p class="mb-0"><i class="fas fa-map-pin me-2"></i>${customer.full_address || 'No address provided'}</p>
                            </div>
                            ${customer.address && customer.address !== '-' ? `
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Street Address</label>
                                <p class="mb-0">${customer.address}</p>
                            </div>
                            ` : ''}
                            ${customer.city && customer.city !== '-' ? `
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">City</label>
                                <p class="mb-0">${customer.city}</p>
                            </div>
                            ` : ''}
                            ${customer.state && customer.state !== '-' ? `
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">State</label>
                                <p class="mb-0">${customer.state}</p>
                            </div>
                            ` : ''}
                            ${customer.postal_code && customer.postal_code !== '-' ? `
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Postal Code</label>
                                <p class="mb-0">${customer.postal_code}</p>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    ${customer.notes ? `
                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold text-muted">Notes</label>
                        <p class="mb-0">${customer.notes}</p>
                    </div>
                    ` : ''}
                    <div class="col-12 mb-3">
                        <hr>
                        <h6 class="fw-bold text-muted mb-3"><i class="fas fa-clock me-2"></i>Timeline</h6>
                        <div class="row">
                            ${customer.created_at ? `
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Created At</label>
                                <p class="mb-0">${new Date(customer.created_at).toLocaleDateString('en-IN', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                            </div>
                            ` : ''}
                            ${customer.updated_at ? `
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Updated At</label>
                                <p class="mb-0">${new Date(customer.updated_at).toLocaleDateString('en-IN', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        }
        function viewInvoiceDetails(invoiceId) {
            const modalTitle = document.getElementById('invoiceDetailsModalLabel');
            const modalBody = document.getElementById('invoiceDetailsModalContent');
            const invoiceModal = new bootstrap.Modal(document.getElementById('invoiceDetailsModal'));

            modalTitle.innerHTML = `<i class="fas fa-file-invoice me-2"></i>Invoice Details`;
            modalBody.innerHTML = `<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-3 text-muted">Loading invoice details...</p></div>`;
            invoiceModal.show();
            fetch(`ajax/get_invoice_details.php?invoice_id=${invoiceId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        modalBody.innerHTML = renderInvoiceDetails(data.invoice);
                    } else {
                        modalBody.innerHTML = `<div class="alert alert-danger">${data.message || 'Failed to load invoice details'}</div>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalBody.innerHTML = '<div class="alert alert-danger">Error loading invoice details. Please try again.</div>';
                });
        }
        function renderInvoiceDetails(invoice) {
            const statusClass = invoice.payment_status === 'paid' ? 'success' : 
                              invoice.payment_status === 'partial' ? 'warning' : 'danger';
            const isOverdue = invoice.due_date && new Date(invoice.due_date) < new Date() && invoice.payment_status !== 'paid';
            const addressParts = [];
            if (invoice.customer_address && invoice.customer_address !== '-') addressParts.push(invoice.customer_address);
            if (invoice.customer_city && invoice.customer_city !== '-') addressParts.push(invoice.customer_city);
            if (invoice.customer_state && invoice.customer_state !== '-') addressParts.push(invoice.customer_state);
            if (invoice.customer_postal_code && invoice.customer_postal_code !== '-') addressParts.push(invoice.customer_postal_code);
            const fullAddress = addressParts.length > 0 ? addressParts.join(', ') : 'No address provided';
            return `
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Invoice Number</label>
                        <p class="mb-0"><span class="badge bg-primary fs-6">${invoice.invoice_number}</span></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted">Payment Status</label>
                        <p class="mb-0">
                            <span class="badge bg-${statusClass} fs-6">${(invoice.payment_status || 'due').charAt(0).toUpperCase() + (invoice.payment_status || 'due').slice(1)}</span>
                        </p>
                    </div>
                    <div class="col-12 mb-3">
                        <hr>
                        <h6 class="fw-bold text-muted mb-3"><i class="fas fa-user me-2"></i>Customer Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Customer Name</label>
                                <p class="mb-0">${invoice.customer_name || 'N/A'}</p>
                            </div>
                            ${invoice.customer_code && invoice.customer_code !== '-' ? `
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Customer Code</label>
                                <p class="mb-0"><span class="badge bg-secondary">${invoice.customer_code}</span></p>
                            </div>
                            ` : ''}
                            ${invoice.customer_phone && invoice.customer_phone !== '-' ? `
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Phone</label>
                                <p class="mb-0"><a href="tel:${invoice.customer_phone}"><i class="fas fa-phone me-2"></i>${invoice.customer_phone}</a></p>
                            </div>
                            ` : ''}
                            ${invoice.customer_email && invoice.customer_email !== '-' ? `
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Email</label>
                                <p class="mb-0"><a href="mailto:${invoice.customer_email}"><i class="fas fa-envelope me-2"></i>${invoice.customer_email}</a></p>
                            </div>
                            ` : ''}
                            <div class="col-12 mb-2">
                                <label class="form-label fw-bold text-muted small">Address</label>
                                <p class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>${fullAddress}</p>
                            </div>
                        </div>
                    </div>
                    ${invoice.order_number && invoice.order_number !== '-' ? `
                    <div class="col-12 mb-3">
                        <hr>
                        <h6 class="fw-bold text-muted mb-3"><i class="fas fa-shopping-cart me-2"></i>Order Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Order Number</label>
                                <p class="mb-0"><span class="badge bg-info">${invoice.order_number}</span></p>
                            </div>
                            ${invoice.order_date ? `
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Order Date</label>
                                <p class="mb-0">${new Date(invoice.order_date).toLocaleDateString('en-IN', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                            </div>
                            ` : ''}
                            ${invoice.order_status && invoice.order_status !== '-' ? `
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Order Status</label>
                                <p class="mb-0"><span class="badge bg-secondary">${invoice.order_status}</span></p>
                            </div>
                            ` : ''}
                            ${invoice.order_total_amount > 0 ? `
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Order Total</label>
                                <p class="mb-0">₹${parseFloat(invoice.order_total_amount).toFixed(2)}</p>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    ` : ''}
                    <div class="col-12 mb-3">
                        <hr>
                        <h6 class="fw-bold text-muted mb-3"><i class="fas fa-calendar me-2"></i>Invoice Dates</h6>
                        <div class="row">
                            ${invoice.invoice_date ? `
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Invoice Date</label>
                                <p class="mb-0">${new Date(invoice.invoice_date).toLocaleDateString('en-IN', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                            </div>
                            ` : ''}
                            ${invoice.due_date ? `
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Due Date</label>
                                <p class="mb-0 ${isOverdue ? 'text-danger fw-bold' : ''}">
                                    ${new Date(invoice.due_date).toLocaleDateString('en-IN', { year: 'numeric', month: 'long', day: 'numeric' })}
                                    ${isOverdue ? ' <span class="badge bg-danger">Overdue</span>' : ''}
                                </p>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    <div class="col-12 mb-3">
                        <hr>
                        <h6 class="fw-bold text-muted mb-3"><i class="fas fa-money-bill-wave me-2"></i>Financial Details</h6>
                        <div class="row">
                            ${invoice.subtotal > 0 ? `
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Subtotal</label>
                                <p class="mb-0">₹${parseFloat(invoice.subtotal).toFixed(2)}</p>
                            </div>
                            ` : ''}
                            ${invoice.tax_amount > 0 ? `
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Tax (${invoice.tax_rate || 0}%)</label>
                                <p class="mb-0">₹${parseFloat(invoice.tax_amount).toFixed(2)}</p>
                            </div>
                            ` : ''}
                            ${invoice.discount_amount > 0 ? `
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Discount</label>
                                <p class="mb-0 text-success">-₹${parseFloat(invoice.discount_amount).toFixed(2)}</p>
                            </div>
                            ` : ''}
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Total Amount</label>
                                <p class="mb-0 h5 text-primary">₹${parseFloat(invoice.total_amount || 0).toFixed(2)}</p>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Paid Amount</label>
                                <p class="mb-0 h5 text-success">₹${parseFloat(invoice.paid_amount || 0).toFixed(2)}</p>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Balance Amount</label>
                                <p class="mb-0 h5 text-danger">₹${parseFloat(invoice.balance_amount || 0).toFixed(2)}</p>
                            </div>
                            ${invoice.payment_method ? `
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Payment Method</label>
                                <p class="mb-0">${invoice.payment_method}</p>
                            </div>
                            ` : ''}
                            ${invoice.payment_date ? `
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Payment Date</label>
                                <p class="mb-0">${new Date(invoice.payment_date).toLocaleDateString('en-IN', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    ${invoice.notes ? `
                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold text-muted">Notes</label>
                        <p class="mb-0">${invoice.notes}</p>
                    </div>
                    ` : ''}
                    <div class="col-12 mb-3">
                        <hr>
                        <h6 class="fw-bold text-muted mb-3"><i class="fas fa-clock me-2"></i>Timeline</h6>
                        <div class="row">
                            ${invoice.created_at ? `
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Created At</label>
                                <p class="mb-0">${new Date(invoice.created_at).toLocaleDateString('en-IN', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                            </div>
                            ` : ''}
                            ${invoice.updated_at ? `
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Updated At</label>
                                <p class="mb-0">${new Date(invoice.updated_at).toLocaleDateString('en-IN', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                            </div>
                            ` : ''}
                            ${invoice.created_by_name ? `
                            <div class="col-md-6 mb-2">
                                <label class="form-label fw-bold text-muted small">Created By</label>
                                <p class="mb-0"><i class="fas fa-user-circle me-2"></i>${invoice.created_by_name}</p>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        }
        function viewOrderDetails(orderId) {
            const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
            const modalBody = document.getElementById('orderDetailsContent');
            
            modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-3">Loading order details...</p></div>';
            modal.show();
            
            fetch(`ajax/get_order_details.php?order_id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.order) {
                        const order = data.order;
                        const statusClass = order.status === 'completed' ? 'success' : 
                                          order.status === 'in_progress' ? 'danger' : 
                                          order.status === 'pending' ? 'warning' : 'secondary';
                        
                        modalBody.innerHTML = `
                            <div class="order-details-popup">
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 p-3 bg-light rounded">
                                            <div>
                                                <strong>Order Number:</strong> <span class="text-primary">${order.order_number}</span>
                                            </div>
                                            <div>
                                                <span class="badge bg-${statusClass}">${order.status.replace('_', ' ')}</span>
                                            </div>
                                            <div>
                                                <strong>Total Amount:</strong> <span class="text-success">₹${order.total_amount.toFixed(2)}</span>
                                            </div>
                                            <div>
                                                <strong>Paid:</strong> <span class="text-success">₹${order.paid_amount.toFixed(2)}</span>
                                            </div>
                                            <div>
                                                <strong>Balance:</strong> <span class="text-danger">₹${order.balance_amount.toFixed(2)}</span>
                                            </div>
                                            <div>
                                                <strong>Due Date:</strong> ${order.due_date}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <div class="card h-100">
                                            <div class="card-header bg-primary text-white">
                                                <i class="fas fa-user me-2"></i>Customer Information
                                            </div>
                                            <div class="card-body">
                                                <p><strong>Name:</strong> ${order.customer.name}</p>
                                                <p><strong>Customer ID:</strong> ${order.customer.code || 'N/A'}</p>
                                                ${order.customer.phone ? `<p><strong>Phone:</strong> <a href="tel:${order.customer.phone}" class="text-primary"><i class="fas fa-phone me-1"></i>${order.customer.phone}</a></p>` : ''}
                                                ${order.customer.email ? `<p><strong>Email:</strong> <a href="mailto:${order.customer.email}" class="text-primary"><i class="fas fa-envelope me-1"></i>${order.customer.email}</a></p>` : ''}
                                                ${order.customer.address ? `<p><strong>Address:</strong> ${order.customer.address}</p>` : ''}
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-4">
                                        <div class="card h-100">
                                            <div class="card-header bg-primary text-white">
                                                <i class="fas fa-clock me-2"></i>Timeline
                                            </div>
                                            <div class="card-body">
                                                <p><strong>Order Date:</strong> ${order.timeline.order_date}</p>
                                                <p><strong>Due Date:</strong> ${order.timeline.due_date}</p>
                                                ${order.timeline.delivery_date && order.timeline.delivery_date !== '-' ? `<p><strong>Delivery Date:</strong> <span class="text-success">${order.timeline.delivery_date}</span></p>` : ''}
                                                <p><strong>Created On:</strong> ${order.timeline.created_at}</p>
                                                ${order.timeline.created_by ? `<p><strong>by:</strong> ${order.timeline.created_by}</p>` : ''}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="card">
                                            <div class="card-header bg-primary text-white">
                                                <i class="fas fa-shopping-bag me-2"></i>Order Information
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <p><strong>Cloth Type:</strong> ${order.order_info.cloth_type || 'N/A'} ${order.order_info.cloth_category ? `<span class="badge bg-info">${order.order_info.cloth_category}</span>` : ''}</p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <p><strong>Standard Rate:</strong> ₹${order.order_info.standard_rate.toFixed(2)}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        modalBody.innerHTML = `<div class="alert alert-danger">${data.message || 'Failed to load order details'}</div>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalBody.innerHTML = '<div class="alert alert-danger">Error loading order details. Please try again.</div>';
                });
        }
        function viewCompanyReports(companyId) {
            const modal = new bootstrap.Modal(document.getElementById('companyReportsModal'));
            const modalBody = document.getElementById('companyReportsContent');
            
            modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-3">Loading reports...</p></div>';
            modal.show();
            
            fetch(`ajax/get_company_reports.php?company_id=${companyId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.reports) {
                        const reports = data.reports;
                        modalBody.innerHTML = `
                            <div class="company-reports-popup">
                               
                                <div class="row mb-4">
                                    <div class="col-md-3 mb-3">
                                        <div class="card text-center" style="background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%); color: white;">
                                            <div class="card-body">
                                                <div class="h3 mb-1">${reports.customers.total}</div>
                                                <div class="small">Total Customers</div>
                                                <div class="small mt-1">${reports.customers.active} active</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="card text-center" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">
                                            <div class="card-body">
                                                <div class="h3 mb-1">₹${reports.financial.total_revenue.toFixed(2)}</div>
                                                <div class="small">Total Revenue</div>
                                                <div class="small mt-1">${reports.invoices.paid} paid invoices</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="card text-center" style="background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%); color: white;">
                                            <div class="card-body">
                                                <div class="h3 mb-1">₹${reports.expenses.total_amount.toFixed(2)}</div>
                                                <div class="small">Total Expenses</div>
                                                <div class="small mt-1">${reports.expenses.total} transactions</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="card text-center" style="background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%); color: white;">
                                            <div class="card-body">
                                                <div class="h3 mb-1">₹${reports.financial.net_profit.toFixed(2)}</div>
                                                <div class="small">Net Profit</div>
                                                <div class="small mt-1">Revenue - Expenses</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <div class="card">
                                            <div class="card-header bg-primary text-white">
                                                <i class="fas fa-shopping-cart me-2"></i>Orders Statistics
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-6 mb-3">
                                                        <div class="text-center p-3 bg-light rounded">
                                                            <div class="h4 text-primary">${reports.orders.total}</div>
                                                            <small class="text-muted">Total Orders</small>
                                                        </div>
                                                    </div>
                                                    <div class="col-6 mb-3">
                                                        <div class="text-center p-3 bg-light rounded">
                                                            <div class="h4 text-success">${reports.orders.completed}</div>
                                                            <small class="text-muted">Completed</small>
                                                        </div>
                                                    </div>
                                                    <div class="col-6 mb-3">
                                                        <div class="text-center p-3 bg-light rounded">
                                                            <div class="h4 text-warning">${reports.orders.pending}</div>
                                                            <small class="text-muted">Pending</small>
                                                        </div>
                                                    </div>
                                                    <div class="col-6 mb-3">
                                                        <div class="text-center p-3 bg-light rounded">
                                                            <div class="h4 text-danger">${reports.orders.in_progress}</div>
                                                            <small class="text-muted">In Progress</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <hr>
                                                <div class="row">
                                                    <div class="col-12 mb-2">
                                                        <strong>Total Revenue:</strong> <span class="text-success">₹${reports.orders.total_revenue.toFixed(2)}</span>
                                                    </div>
                                                    <div class="col-12 mb-2">
                                                        <strong>Total Paid:</strong> <span class="text-info">₹${reports.orders.total_paid.toFixed(2)}</span>
                                                    </div>
                                                    <div class="col-12">
                                                        <strong>Total Balance:</strong> <span class="text-danger">₹${reports.orders.total_balance.toFixed(2)}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-4">
                                        <div class="card">
                                            <div class="card-header bg-success text-white">
                                                <i class="fas fa-file-invoice me-2"></i>Invoices Statistics
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-6 mb-3">
                                                        <div class="text-center p-3 bg-light rounded">
                                                            <div class="h4 text-primary">${reports.invoices.total}</div>
                                                            <small class="text-muted">Total Invoices</small>
                                                        </div>
                                                    </div>
                                                    <div class="col-6 mb-3">
                                                        <div class="text-center p-3 bg-light rounded">
                                                            <div class="h4 text-success">${reports.invoices.paid}</div>
                                                            <small class="text-muted">Paid</small>
                                                        </div>
                                                    </div>
                                                    <div class="col-6 mb-3">
                                                        <div class="text-center p-3 bg-light rounded">
                                                            <div class="h4 text-warning">${reports.invoices.partial}</div>
                                                            <small class="text-muted">Partial</small>
                                                        </div>
                                                    </div>
                                                    <div class="col-6 mb-3">
                                                        <div class="text-center p-3 bg-light rounded">
                                                            <div class="h4 text-danger">${reports.invoices.due}</div>
                                                            <small class="text-muted">Due</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <hr>
                                                <div class="row">
                                                    <div class="col-12 mb-2">
                                                        <strong>Total Amount:</strong> <span class="text-success">₹${reports.invoices.total_amount.toFixed(2)}</span>
                                                    </div>
                                                    <div class="col-12 mb-2">
                                                        <strong>Paid Amount:</strong> <span class="text-info">₹${reports.invoices.paid_amount.toFixed(2)}</span>
                                                    </div>
                                                    <div class="col-12">
                                                        <strong>Due Amount:</strong> <span class="text-danger">₹${reports.invoices.due_amount.toFixed(2)}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <div class="card">
                                            <div class="card-header bg-info text-white">
                                                <i class="fas fa-users me-2"></i>Users Statistics
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-6">
                                                        <div class="text-center p-3 bg-light rounded">
                                                            <div class="h4 text-primary">${reports.users.total}</div>
                                                            <small class="text-muted">Total Users</small>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="text-center p-3 bg-light rounded">
                                                            <div class="h4 text-success">${reports.users.active}</div>
                                                            <small class="text-muted">Active Users</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-4">
                                        <div class="card">
                                            <div class="card-header bg-warning text-white">
                                                <i class="fas fa-money-bill-wave me-2"></i>Expenses Statistics
                                            </div>
                                            <div class="card-body">
                                                <div class="text-center p-3 bg-light rounded">
                                                    <div class="h4 text-danger">₹${reports.expenses.total_amount.toFixed(2)}</div>
                                                    <small class="text-muted">Total Expenses</small>
                                                    <div class="mt-2">
                                                        <small class="text-muted">${reports.expenses.total} transactions</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        modalBody.innerHTML = `<div class="alert alert-danger">${data.message || 'Failed to load reports'}</div>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalBody.innerHTML = '<div class="alert alert-danger">Error loading reports. Please try again.</div>';
                });
        }
        loadTabData(currentTab);
    </script>
    <div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="orderDetailsModalLabel">Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="orderDetailsContent">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="companyReportsModal" tabindex="-1" aria-labelledby="companyReportsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="companyReportsModalLabel">Company Reports</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="companyReportsContent">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="contactDetailsModal" tabindex="-1" aria-labelledby="contactDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="contactDetailsModalLabel"><i class="fas fa-user me-2"></i>Contact Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="contactDetailsModalContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">Loading contact details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="expenseDetailsModal" tabindex="-1" aria-labelledby="expenseDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="expenseDetailsModalLabel"><i class="fas fa-money-bill-wave me-2"></i>Expense Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="expenseDetailsModalContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">Loading expense details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="userDetailsModal" tabindex="-1" aria-labelledby="userDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="userDetailsModalLabel"><i class="fas fa-user me-2"></i>User Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="userDetailsModalContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">Loading user details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="invoiceDetailsModal" tabindex="-1" aria-labelledby="invoiceDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="invoiceDetailsModalLabel"><i class="fas fa-file-invoice me-2"></i>Invoice Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="invoiceDetailsModalContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">Loading invoice details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="customerDetailsModal" tabindex="-1" aria-labelledby="customerDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="customerDetailsModalLabel"><i class="fas fa-user me-2"></i>Customer Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="customerDetailsModalContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">Loading customer details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="subscriptionModal" tabindex="-1" aria-labelledby="subscriptionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="subscriptionModalLabel">
                        <i class="fas fa-crown me-2"></i>Update Subscription Plan
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Current Plan:</label>
                        <p class="mb-0">
                            <span class="badge bg-<?php echo $planMeta['badge']; ?> badge-custom">
                                <i class="fas fa-crown me-1"></i><?php echo $planMeta['label']; ?>
                            </span>
                        </p>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select New Plan:</label>
                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <div class="card subscription-option subscription-free" data-plan="free" style="cursor: pointer; transition: all 0.3s;">
                                    <div class="card-body text-center">
                                        <h6 class="card-title mb-2">
                                            <i class="fas fa-gift text-secondary"></i> Free Plan
                                        </h6>
                                        <p class="text-muted small mb-0">Basic features for small businesses</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card subscription-option subscription-basic" data-plan="basic" style="cursor: pointer; transition: all 0.3s;">
                                    <div class="card-body text-center">
                                        <h6 class="card-title mb-2" style="color: white;">
                                            <i class="fas fa-arrow-up me-1"></i> Switch to Basic Plan
                                        </h6>
                                        <p class="small mb-0" style="color: rgba(255,255,255,0.9);">Standard features for growing businesses</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card subscription-option subscription-premium" data-plan="premium" style="cursor: pointer; transition: all 0.3s;">
                                    <div class="card-body text-center">
                                        <h6 class="card-title mb-2" style="color: white;">
                                            <i class="fas fa-arrow-up me-1"></i> Upgrade to Premium Plan
                                        </h6>
                                        <p class="small mb-0" style="color: rgba(255,255,255,0.9);">Advanced features for established businesses</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card subscription-option subscription-enterprise" data-plan="enterprise" style="cursor: pointer; transition: all 0.3s;">
                                    <div class="card-body text-center">
                                        <h6 class="card-title mb-2" style="color: #000;">
                                            <i class="fas fa-check me-1"></i> Enterprise Plan
                                        </h6>
                                        <p class="small mb-0" style="color: rgba(0,0,0,0.7);">Full features for large businesses</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" id="selectedPlan" value="">
                        <div class="mt-3" id="durationSelection" style="display: none;">
                            <label class="form-label fw-bold">Select Duration:</label>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="card duration-option" data-duration="monthly" style="cursor: pointer; border: 2px solid #dee2e6; transition: all 0.3s;">
                                        <div class="card-body text-center py-3">
                                            <h6 class="card-title mb-1">
                                                <i class="fas fa-calendar-alt text-primary me-2"></i>Monthly
                                            </h6>
                                            <p class="text-muted small mb-0">1 Month</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card duration-option" data-duration="yearly" style="cursor: pointer; border: 2px solid #dee2e6; transition: all 0.3s;">
                                        <div class="card-body text-center py-3">
                                            <h6 class="card-title mb-1">
                                                <i class="fas fa-calendar-check text-success me-2"></i>Yearly
                                            </h6>
                                            <p class="text-muted small mb-0">12 Months</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" id="selectedDuration" value="">
                        </div>
                    </div>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> Changing the subscription plan will update immediately. The subscription expiry date will be automatically updated based on the selected duration.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="updateSubscriptionBtn" onclick="updateSubscription()" disabled>
                        <i class="fas fa-save me-1"></i>Update Subscription
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="confirmSubscriptionModal" tabindex="-1" aria-labelledby="confirmSubscriptionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="confirmSubscriptionModalLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirm Subscription Change
                    </h5>
                    <button type="button" class="btn-close" onclick="cancelConfirmation()" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Are you sure you want to change the subscription plan?</p>
                    <div class="alert alert-light border">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold">Current Plan:</span>
                            <span class="badge bg-secondary" id="confirmCurrentPlan">-</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold">New Plan:</span>
                            <span class="badge bg-primary" id="confirmNewPlan">-</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center" id="confirmDurationRow" style="display: none;">
                            <span class="fw-bold">Duration:</span>
                            <span class="badge bg-info" id="confirmDuration">-</span>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        This change will take effect immediately. The subscription expiry date will be automatically updated.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="cancelConfirmation()">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="confirmUpdateBtn" onclick="proceedWithUpdate()">
                        <i class="fas fa-check me-1"></i>Confirm Update
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" id="messageModalHeader">
                    <h5 class="modal-title" id="messageModalLabel">
                        <i class="fas fa-info-circle me-2"></i>Message
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="messageModalBody">
                    <p id="messageModalText"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" id="messageModalOkBtn">
                        <i class="fas fa-check me-1"></i>OK
                    </button>
                </div>
            </div>
        </div>
    </div>
    <style>
            .subscription-free {
            background: white !important;
            border: 2px solid #dee2e6 !important;
        }
        .subscription-basic {
            background: #3b82f6 !important;
            border: 2px solid #3b82f6 !important;
        }
        .subscription-premium {
            background: linear-gradient(135deg, #c026d3 0%, #3b82f6 100%) !important;
            border: 2px solid transparent !important;
        }
        .subscription-enterprise {
            background: #ffc107 !important;
            border: 2px solid #ffc107 !important;
        }
        .subscription-option {
            transition: all 0.3s ease;
        }
        .subscription-option:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .subscription-option.selected {
            border-color: #4f46e5 !important;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.5) !important;
            transform: translateY(-3px);
        }
        .subscription-free.selected {
            background: #f0f4ff !important;
            border-color: #4f46e5 !important;
        }
        .subscription-basic.selected {
            background: #2563eb !important;
            border-color: #1e40af !important;
        }
        .subscription-premium.selected {
            background: linear-gradient(135deg, #a21caf 0%, #2563eb 100%) !important;
            border-color: #7c3aed !important;
        }
        .subscription-enterprise.selected {
            background: #f59e0b !important;
            border-color: #d97706 !important;
        }
        .subscription-option.current-plan {
            opacity: 0.9;
        }
        .text-purple {
            color: #c026d3 !important;
        }
        .duration-option:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .duration-option.selected {
            border-color: #4f46e5 !important;
            background: #f0f4ff !important;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
    </style>
    <script>
        function openSubscriptionModal() {
            const modal = new bootstrap.Modal(document.getElementById('subscriptionModal'));
            modal.show();
            document.querySelectorAll('.subscription-option').forEach(option => {
                option.classList.remove('selected');
            });
            document.querySelectorAll('.duration-option').forEach(option => {
                option.classList.remove('selected');
            });
            document.getElementById('selectedPlan').value = '';
            document.getElementById('selectedDuration').value = '';
            document.getElementById('durationSelection').style.display = 'none';
            document.getElementById('updateSubscriptionBtn').disabled = true;
        }
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.subscription-option').forEach(option => {
                option.addEventListener('click', function() {
                    document.querySelectorAll('.subscription-option').forEach(opt => {
                        opt.classList.remove('selected');
                    });
                    this.classList.add('selected');
                    const plan = this.getAttribute('data-plan');
                    document.getElementById('selectedPlan').value = plan;
                    const durationSelection = document.getElementById('durationSelection');
                    if (plan === 'free') {
                        durationSelection.style.display = 'none';
                        document.getElementById('selectedDuration').value = '';
                        document.getElementById('updateSubscriptionBtn').disabled = false;
                    } else {
                        durationSelection.style.display = 'block';
                        document.querySelectorAll('.duration-option').forEach(opt => {
                            opt.classList.remove('selected');
                        });
                        document.getElementById('selectedDuration').value = '';
                        document.getElementById('updateSubscriptionBtn').disabled = true;
                    }
                });
            });
            document.querySelectorAll('.duration-option').forEach(option => {
                option.addEventListener('click', function() {
                    document.querySelectorAll('.duration-option').forEach(opt => {
                        opt.classList.remove('selected');
                    });
                    this.classList.add('selected');
                    const duration = this.getAttribute('data-duration');
                    document.getElementById('selectedDuration').value = duration;
                    document.getElementById('updateSubscriptionBtn').disabled = false;
                });
            });
        });
        function showMessageModal(type, message, onClose = null) {
            const modal = new bootstrap.Modal(document.getElementById('messageModal'));
            const header = document.getElementById('messageModalHeader');
            const label = document.getElementById('messageModalLabel');
            const text = document.getElementById('messageModalText');
            const okBtn = document.getElementById('messageModalOkBtn');
            if (type === 'success') {
                header.className = 'modal-header bg-success text-white';
                label.innerHTML = '<i class="fas fa-check-circle me-2"></i>Success';
                okBtn.className = 'btn btn-success';
            } else if (type === 'error') {
                header.className = 'modal-header bg-danger text-white';
                label.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>Error';
                okBtn.className = 'btn btn-danger';
            } else if (type === 'warning') {
                header.className = 'modal-header bg-warning text-dark';
                label.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Warning';
                okBtn.className = 'btn btn-warning';
            } else {
                header.className = 'modal-header bg-info text-white';
                label.innerHTML = '<i class="fas fa-info-circle me-2"></i>Information';
                okBtn.className = 'btn btn-info';
            }
            text.textContent = message;
            modal.show();
            const messageModal = document.getElementById('messageModal');
            messageModal.addEventListener('hidden.bs.modal', function handler() {
                messageModal.removeEventListener('hidden.bs.modal', handler);
                if (onClose) onClose();
            }, { once: true });
        }
        function updateSubscription() {
            const selectedPlan = document.getElementById('selectedPlan').value;
            const selectedDuration = document.getElementById('selectedDuration').value;
            const currentPlan = '<?php echo $planKey; ?>';
            
            if (!selectedPlan) {
                showMessageModal('warning', 'Please select a subscription plan');
                return;
            }
            if (selectedPlan === currentPlan) {
                showMessageModal('info', 'This is already the current subscription plan');
                return;
            }
            if (selectedPlan !== 'free' && !selectedDuration) {
                showMessageModal('warning', 'Please select a duration (Monthly or Yearly)');
                return;
            }
            const planLabels = {
                'free': 'Free Plan',
                'basic': 'Basic Plan',
                'premium': 'Premium Plan',
                'enterprise': 'Enterprise Plan'
            };
            
            document.getElementById('confirmCurrentPlan').textContent = planLabels[currentPlan] || currentPlan.toUpperCase();
            document.getElementById('confirmNewPlan').textContent = planLabels[selectedPlan] || selectedPlan.toUpperCase();
            const durationRow = document.getElementById('confirmDurationRow');
            if (selectedPlan !== 'free') {
                durationRow.style.display = 'flex';
                const durationText = selectedDuration === 'yearly' ? 'Yearly (12 Months)' : 'Monthly (1 Month)';
                document.getElementById('confirmDuration').textContent = durationText;
            } else {
                durationRow.style.display = 'none';
            }
            window.pendingSubscriptionUpdate = {
                plan: selectedPlan,
                duration: selectedDuration || 'monthly'
            };
            const subscriptionModal = bootstrap.Modal.getInstance(document.getElementById('subscriptionModal'));
            if (subscriptionModal) {
                subscriptionModal.hide();
            }
            const subscriptionModalEl = document.getElementById('subscriptionModal');
            subscriptionModalEl.addEventListener('hidden.bs.modal', function handler() {
                subscriptionModalEl.removeEventListener('hidden.bs.modal', handler);
                const confirmModal = new bootstrap.Modal(document.getElementById('confirmSubscriptionModal'));
                confirmModal.show();
            }, { once: true });
        }
        function cancelConfirmation() {
            const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmSubscriptionModal'));
            if (confirmModal) {
                confirmModal.hide();
            }
            const confirmModalEl = document.getElementById('confirmSubscriptionModal');
            confirmModalEl.addEventListener('hidden.bs.modal', function handler() {
                confirmModalEl.removeEventListener('hidden.bs.modal', handler);
                const pendingUpdate = window.pendingSubscriptionUpdate;
                if (pendingUpdate) {
                    const selectedPlan = typeof pendingUpdate === 'object' ? pendingUpdate.plan : pendingUpdate;
                    const selectedDuration = typeof pendingUpdate === 'object' ? pendingUpdate.duration : '';
                    const subscriptionModal = new bootstrap.Modal(document.getElementById('subscriptionModal'));
                    subscriptionModal.show();
                    setTimeout(() => {
                        document.querySelectorAll('.subscription-option').forEach(option => {
                            if (option.getAttribute('data-plan') === selectedPlan) {
                                option.classList.add('selected');
                                document.getElementById('selectedPlan').value = selectedPlan;
                                const durationSelection = document.getElementById('durationSelection');
                                if (selectedPlan === 'free') {
                                    durationSelection.style.display = 'none';
                                    document.getElementById('updateSubscriptionBtn').disabled = false;
                                } else {
                                    durationSelection.style.display = 'block';
                                    if (selectedDuration) {
                                        document.querySelectorAll('.duration-option').forEach(opt => {
                                            if (opt.getAttribute('data-duration') === selectedDuration) {
                                                opt.classList.add('selected');
                                                document.getElementById('selectedDuration').value = selectedDuration;
                                                document.getElementById('updateSubscriptionBtn').disabled = false;
                                            }
                                        });
                                    }
                                }
                            }
                        });
                    }, 300);
                }
            }, { once: true });
        }
        function proceedWithUpdate() {
            const pendingUpdate = window.pendingSubscriptionUpdate;
            if (!pendingUpdate) return;
            const selectedPlan = typeof pendingUpdate === 'object' ? pendingUpdate.plan : pendingUpdate;
            const selectedDuration = typeof pendingUpdate === 'object' ? pendingUpdate.duration : 'monthly';
            const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmSubscriptionModal'));
            if (confirmModal) {
                confirmModal.hide();
            }
            const subscriptionModal = bootstrap.Modal.getInstance(document.getElementById('subscriptionModal'));
            if (subscriptionModal) {
                subscriptionModal.hide();
            }
            const updateBtn = document.getElementById('updateSubscriptionBtn');
            const originalText = updateBtn.innerHTML;
            updateBtn.disabled = true;
            updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Updating...';
            
            fetch('ajax/update_subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    company_id: companyId,
                    subscription_plan: selectedPlan,
                    duration: selectedDuration || 'monthly'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessageModal('success', 'Subscription plan updated successfully!', function() {
                        window.location.reload();
                    });
                } else {
                    showMessageModal('error', data.message || 'Failed to update subscription plan');
                    updateBtn.disabled = false;
                    updateBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessageModal('error', 'An error occurred while updating the subscription plan');
                updateBtn.disabled = false;
                updateBtn.innerHTML = originalText;
            });
        }

        // Mobile Sidebar Toggle
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