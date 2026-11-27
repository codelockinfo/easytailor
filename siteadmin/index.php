<?php
/**
 * Site Admin - Email Change Requests Approval
 * Tailoring Management System
 */

require_once '../config/config.php';

if (!function_exists('escape')) {
    function escape($value) {
        return htmlspecialchars(htmlspecialchars_decode($value ?? '', ENT_QUOTES), ENT_QUOTES, 'UTF-8');
    }
}

// Check if site admin is logged in (via session)
if (!isset($_SESSION['site_admin_logged_in']) || $_SESSION['site_admin_logged_in'] !== true) {
    header('Location: ../admin/login.php');
    exit;
}

require_once '../models/EmailChangeRequest.php';
require_once '../models/Company.php';
require_once '../config/database.php';

$emailChangeRequestModel = new EmailChangeRequest();
$companyModel = new Company();
$database = new Database();
$db = $database->getConnection();

$message = '';
$messageType = '';

// Get section parameter
$current_section = $_GET['section'] ?? 'requests';

// Get contact messages (always load for stats, but only render if section is active)
$contactMessages = [];
$contactStats = ['total' => 0, 'logged_in' => 0, 'guests' => 0];
try {
    $query = "SELECT * FROM user_contact ORDER BY created_date DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $contactMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $contactStats['total'] = count($contactMessages);
    $contactStats['logged_in'] = count(array_filter($contactMessages, function($m) { return isset($m['user_logged']) && $m['user_logged'] == 1; }));
    $contactStats['guests'] = $contactStats['total'] - $contactStats['logged_in'];
} catch (Exception $e) {
    // If table doesn't exist or error occurs, keep empty arrays
    error_log('Error loading contact messages: ' . $e->getMessage());
}

// Get testimonials data (always load for stats, but only render if section is active)
$testimonials = [];
$testimonialStats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
try {
    $testimonialStatusFilter = ($current_section === 'testimonials') ? ($_GET['status'] ?? 'pending') : null;
    $testimonialStatusFilter = ($testimonialStatusFilter === 'all' || $testimonialStatusFilter === '') ? null : $testimonialStatusFilter;
    
    $testimonialQuery = "SELECT 
                            t.*,
                            c.company_name,
                            u.full_name as tailor_name
                          FROM testimonials t 
                          LEFT JOIN companies c ON t.company_id = c.id 
                          LEFT JOIN users u ON t.user_id = u.id";
    
    if ($testimonialStatusFilter) {
        $testimonialQuery .= " WHERE t.status = :status";
    }
    
    $testimonialQuery .= " ORDER BY t.created_at DESC";
    
    $testimonialStmt = $db->prepare($testimonialQuery);
    if ($testimonialStatusFilter) {
        $testimonialStmt->bindParam(':status', $testimonialStatusFilter);
    }
    $testimonialStmt->execute();
    $testimonials = $testimonialStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all testimonials for stats
    $allTestimonialsQuery = "SELECT status FROM testimonials";
    $allTestimonialsStmt = $db->prepare($allTestimonialsQuery);
    $allTestimonialsStmt->execute();
    $allTestimonials = $allTestimonialsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $testimonialStats['total'] = count($allTestimonials);
    $testimonialStats['pending'] = count(array_filter($allTestimonials, fn($t) => $t['status'] === 'pending'));
    $testimonialStats['approved'] = count(array_filter($allTestimonials, fn($t) => $t['status'] === 'approved'));
    $testimonialStats['rejected'] = count(array_filter($allTestimonials, fn($t) => $t['status'] === 'rejected'));
} catch (Exception $e) {
    error_log('Error loading testimonials: ' . $e->getMessage());
}

// Get filter status
$status_filter = $_GET['status'] ?? 'pending';
$requestStatus = ($status_filter === 'all' || $status_filter === '') ? null : $status_filter;

// Get all requests with details
$requests = $emailChangeRequestModel->getAllRequestsWithDetails($requestStatus);

// Get statistics
$allRequests = $emailChangeRequestModel->getAllRequestsWithDetails(null);
$stats = [
    'total' => count($allRequests),
    'pending' => count(array_filter($allRequests, fn($r) => $r['status'] === 'pending')),
    'approved' => count(array_filter($allRequests, fn($r) => $r['status'] === 'approved')),
    'rejected' => count(array_filter($allRequests, fn($r) => $r['status'] === 'rejected'))
];

// Get highlight company
// Prefer the company from most recent email request, fallback to latest created company
$highlightCompany = null;
if (!empty($allRequests)) {
    $latestRequest = $allRequests[0];
    if (!empty($latestRequest['company_id'])) {
        $highlightCompany = $companyModel->find($latestRequest['company_id']);
    }
}
if (!$highlightCompany) {
    $latestCompany = $companyModel->findAll([], 'created_at DESC', 1);
    $highlightCompany = $latestCompany[0] ?? null;
}
$highlightStats = null;
$daysRemaining = null;

if ($highlightCompany) {
    $highlightStats = $companyModel->getCompanyStats($highlightCompany['id']);
    if (!empty($highlightCompany['subscription_expiry']) && ($highlightCompany['subscription_plan'] ?? '') !== 'free') {
        $expiry = strtotime($highlightCompany['subscription_expiry']);
        $today = strtotime(date('Y-m-d'));
        $daysRemaining = max(0, floor(($expiry - $today) / (60 * 60 * 24)));
    }
}

$planStyles = [
    'free' => ['label' => 'Free Plan', 'color' => '#6c757d', 'badge' => 'secondary'],
    'basic' => ['label' => 'Basic Plan', 'color' => '#3b82f6', 'badge' => 'primary'],
    'premium' => ['label' => 'Premium Plan', 'color' => '#c026d3', 'badge' => 'pink'],
    'enterprise' => ['label' => 'Enterprise Plan', 'color' => '#fbbf24', 'badge' => 'warning']
];
$planKey = 'free';
if ($highlightCompany && !empty($highlightCompany['subscription_plan'])) {
    $planKey = strtolower($highlightCompany['subscription_plan']);
}
$planMeta = $planStyles[$planKey] ?? $planStyles['free'];

// Get messages from session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['messageType']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Admin Panel</title>
    <?php echo render_favicon_links(); ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f6f8fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
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
        .header-logo {
            height: 100px;
            width: auto;
            object-fit: contain;
            margin-right: 1rem;
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
        #pageTitle {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
            letter-spacing: -0.3px;
            line-height: 1.2;
            margin: 0;
        }
        #pageSubtitle {
            display: none;
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
        /* Email Requests Redesign */
        .er-stat-card {
            border-radius: 14px;
            padding: 1.15rem 1.35rem;
            display: flex;
            align-items: center;
            gap: 1.05rem;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.85);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 100%;
        }
        .er-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
        }
        .er-stat-total {
            background: linear-gradient(135deg, #f3f1ff 0%, #ede9fe 100%);
            border-bottom: 3px solid #4f46e5;
        }
        .er-stat-pending {
            background: linear-gradient(135deg, #fff8ec 0%, #ffedd5 100%);
            border-bottom: 3px solid #f59e0b;
        }
        .er-stat-approved {
            background: linear-gradient(135deg, #edfdf4 0%, #dcfce7 100%);
            border-bottom: 3px solid #22c55e;
        }
        .er-stat-rejected {
            background: linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%);
            border-bottom: 3px solid #fb5664;
        }
        .er-stat-guests {
            background: linear-gradient(135deg, #f8fafc 0%, #eef2f6 100%);
            border-bottom: 3px solid #64748b;
        }
        .er-stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 1.25rem;
            flex-shrink: 0;
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.12);
        }
        .er-stat-total .er-stat-icon { background: #4f46e5; }
        .er-stat-pending .er-stat-icon { background: #f59e0b; }
        .er-stat-approved .er-stat-icon { background: #22c55e; }
        .er-stat-rejected .er-stat-icon { background: #fb5664; }
        .er-stat-guests .er-stat-icon { background: #64748b; }
        .er-stat-info {
            min-width: 0;
        }
        .er-stat-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.15rem;
            line-height: 1.25;
        }
        .er-stat-value {
            font-size: 1.85rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.1;
        }
        .er-filter-bar {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #eef2f7;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
            padding: 0 1.25rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .er-filter-tabs {
            display: flex;
            align-items: center;
            gap: 1.75rem;
            overflow-x: auto;
            scrollbar-width: none;
            -webkit-overflow-scrolling: touch;
        }
        .er-filter-tabs::-webkit-scrollbar {
            display: none;
        }
        .er-filter-tab {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.95rem 0.25rem;
            text-decoration: none;
            color: #475569;
            font-weight: 500;
            font-size: 0.92rem;
            border-bottom: 3px solid transparent;
            white-space: nowrap;
            transition: color 0.2s ease, border-color 0.2s ease;
        }
        .er-filter-tab:hover {
            color: #4f46e5;
        }
        .er-filter-tab.active {
            color: #4f46e5;
            font-weight: 600;
            border-bottom-color: #4f46e5;
        }
        .er-tab-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 20px;
            padding: 0 0.45rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            background: #f1f5f9;
            color: #475569;
        }
        .er-filter-tab.active .er-tab-count {
            background: #eef2ff;
            color: #4f46e5;
        }
        .er-tabs-divider {
            width: 1px;
            height: 22px;
            background: #e2e8f0;
            flex-shrink: 0;
        }
        .er-all-dropdown-btn {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.42rem 0.9rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #334155;
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            white-space: nowrap;
            transition: all 0.2s ease;
        }
        .er-all-dropdown-btn:hover, .er-all-dropdown-btn:focus, .er-all-dropdown-btn.is-all {
            border-color: #c7d2fe;
            color: #4f46e5;
            background: #f8faff;
        }
        .er-filter-bar .dropdown-item.active,
        .er-filter-bar .dropdown-item:active {
            background-color: #4f46e5;
            color: #ffffff;
        }
        .er-filter-bar .dropdown-item.active .badge {
            background-color: rgba(255, 255, 255, 0.2) !important;
            color: #ffffff !important;
        }
        .er-requests-card {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #eef2f7;
            box-shadow: 0 2px 12px rgba(15, 23, 42, 0.04);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        .er-requests-card-header {
            padding: 1.35rem 1.5rem 0.5rem;
            background: #ffffff;
        }
        .er-requests-card-title {
            font-size: 1.08rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }
        .er-empty-state {
            padding: 2.5rem 1.5rem 3.25rem;
            text-align: center;
        }
        .er-empty-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            margin-top: 0.65rem;
            margin-bottom: 0.35rem;
        }
        .er-empty-subtitle {
            color: #64748b;
            font-size: 0.92rem;
            max-width: 380px;
            margin: 0 auto;
        }
        @media (max-width: 576px) {
            .er-stat-card {
                padding: 0.9rem 1rem;
                gap: 0.75rem;
            }
            .er-stat-icon {
                width: 42px;
                height: 42px;
                font-size: 1rem;
            }
            .er-stat-value {
                font-size: 1.45rem;
            }
            .er-filter-bar {
                padding: 0 0.85rem;
                gap: 0.5rem;
            }
            .er-filter-tabs {
                gap: 1rem;
            }
        }
        .mb-4 {
    margin-bottom: 1rem !important;
}
        .sticky-controls {
            position: sticky;
            top: 0;
            z-index: 5;
        }
        /* Company Snapshot Redesign */
        .cs-toolbar {
            background: #f6f8fc;
            padding: 0.1rem 0 1rem 0;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.85rem;
            flex-wrap: wrap;
        }
        .cs-search-wrap {
            flex: 1 1 280px;
            height: 46px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0 1rem;
            display: flex;
            align-items: center;
            gap: 0.65rem;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .cs-search-wrap:focus-within {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        .cs-search-icon {
            color: #64748b;
            font-size: 0.92rem;
            flex-shrink: 0;
        }
        .cs-search-input {
            border: 0;
            outline: none;
            box-shadow: none;
            width: 100%;
            background: transparent;
            font-size: 0.92rem;
            color: #0f172a;
        }
        .cs-search-input::placeholder {
            color: #94a3b8;
        }
        .cs-plan-wrap {
            flex: 0 0 auto;
            min-width: 185px;
            height: 46px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .cs-plan-wrap:focus-within {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        .cs-plan-select {
            border: 0 !important;
            box-shadow: none !important;
            background-color: transparent !important;
            padding: 0 1.75rem 0 0.15rem !important;
            font-size: 0.9rem;
            font-weight: 500;
            color: #1e293b;
            cursor: pointer;
            height: 100%;
        }
        .cs-company-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #eef2f7;
            box-shadow: 0 2px 12px rgba(15, 23, 42, 0.04);
            padding: 1.4rem 1.6rem;
            display: grid;
            grid-template-columns: minmax(250px, 1.25fr) auto minmax(285px, 1.15fr);
            gap: 1.5rem;
            align-items: center;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }
        .cs-company-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(79, 70, 229, 0.08);
            border-color: #c7d2fe;
        }
        .cs-company-primary {
            min-width: 0;
        }
        .cs-plan-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.34rem 0.85rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            margin-bottom: 0.65rem;
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
            font-size: 1.25rem;
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
            font-size: 0.88rem;
            color: #475569;
            font-weight: 500;
            margin: 0 0 0.75rem 0;
        }
        .cs-company-owner i {
            color: #4f46e5;
            font-size: 0.82rem;
        }
        .cs-company-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem 1.15rem;
            font-size: 0.85rem;
            color: #334155;
        }
        .cs-meta-item {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            font-weight: 500;
            min-width: 0;
            overflow-wrap: anywhere;
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
            grid-template-columns: repeat(3, minmax(76px, 1fr));
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
        .cs-info-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }
        .cs-info-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.45rem;
        }
        .cs-info-title i {
            color: #4f46e5;
            font-size: 0.95rem;
        }
        .cs-info-menu-btn {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            border: none;
            background: transparent;
            color: #64748b;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        .cs-info-menu-btn:hover, .cs-info-menu-btn:focus {
            background: #f1f5f9;
            color: #4f46e5;
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
            font-size: 0.83rem;
            line-height: 1.35;
            min-width: 0;
        }
        .cs-info-list li i {
            color: #4f46e5;
            width: 14px;
            text-align: center;
            font-size: 0.8rem;
            margin-top: 0.2rem;
            flex-shrink: 0;
        }
        .cs-info-list li span {
            overflow-wrap: anywhere;
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
                padding: 1.15rem;
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
        /* Contact Messages Redesign */
        .cm-filter-bar {
            flex-wrap: wrap;
            padding-top: 0.2rem;
            padding-bottom: 0.2rem;
        }
        .cm-search-wrap {
            flex: 0 1 260px;
            height: 38px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 0 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
        }
        .cm-search-wrap:focus-within {
            background: #ffffff;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        .cm-search-input {
            border: 0;
            outline: none;
            width: 100%;
            background: transparent;
            font-size: 0.86rem;
            color: #0f172a;
        }
        .cm-search-input::placeholder {
            color: #94a3b8;
        }
        .cm-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
            color: #ffffff;
            font-weight: 700;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 3px 8px rgba(79, 70, 229, 0.2);
        }
        .cm-email-link {
            color: #4f46e5;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .cm-email-link:hover {
            color: #4338ca;
            text-decoration: underline;
        }
        .cm-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.34rem 0.8rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
        }
        .cm-type-badge.is-logged {
            background: #dcfce7;
            color: #15803d;
        }
        .cm-type-badge.is-guest {
            background: #f1f5f9;
            color: #475569;
        }
        .cm-view-btn {
            border-radius: 10px !important;
            padding: 0.44rem 0.95rem !important;
            font-size: 0.82rem !important;
            font-weight: 600 !important;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            box-shadow: 0 2px 6px rgba(79, 70, 229, 0.22);
        }
        @media (max-width: 768px) {
            .cm-search-wrap {
                flex: 1 1 100%;
                margin-bottom: 0.65rem;
            }
        }
        @media (max-width: 576px) {
            .cs-plan-wrap {
                width: 100%;
            }
            .cs-info-list {
                grid-template-columns: 1fr;
            }
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
        @media (max-width: 768px) {
            .stat-card {
                margin-bottom: 1rem;
            }
            .nav-pills {
                flex-direction: column;
            }
            .company-main-card {
                flex-direction: column;
                gap: 1.5rem;
            }
            .company-stats {
                width: 100%;
                justify-content: space-between;
                gap: 0.5rem;
            }
            #mobileMenuBtn {
                margin-top: 0;
                padding: 0.375rem 0.6rem;
            }
            #pageTitle {
                font-size: 1.15rem;
                margin-bottom: 0 !important;
            }
            #pageSubtitle {
                display: none;
            }
            .top-header {
                padding: 0 0.85rem;
            }
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #4f46e5;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .request-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #4f46e5;
        }
        .request-card.pending {
            border-left-color: #ffc107;
        }
        .request-card.approved {
            border-left-color: #28a745;
        }
        .request-card.rejected {
            border-left-color: #dc3545;
        }
        .badge-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
        }
        .btn-action {
            margin: 0.25rem;
        }
        .company-grid {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        .company-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }
        .company-main-card {
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(15,23,42,0.1);
            padding: 1.75rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            height: 100%;
        }
        .company-main-card.plan-free {
            background: #6c757d !important;
        }
        .company-main-card.plan-basic {
            background: #3b82f6 !important;
        }
        .company-main-card.plan-premium {
            background: linear-gradient(135deg, #9333ea, #7c3aed) !important;
        }
        .company-main-card.plan-enterprise {
            /* background: linear-gradient(135deg, rgba(255, 193, 7, 1) 0%, rgba(255, 235, 59, 1) 25%, rgba(255, 220, 88, 1) 50%, rgba(255, 235, 59, 1) 75%, rgba(255, 193, 7, 1) 100%) !important;
            box-shadow: 0 4px 25px rgba(255, 193, 7, 0.5), 0 2px 15px rgba(255, 152, 0, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.2) !important;
             */
            background: #fdb71a;
            position: relative;
            overflow: hidden;
        }
        .company-main-card.plan-enterprise::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.4) 50%, transparent 70%);
            animation: shine 3s infinite;
            z-index: 0;
        }
        @keyframes shine {
            0% {
                transform: translateX(-100%) translateY(-100%) rotate(45deg);
            }
            100% {
                transform: translateX(100%) translateY(100%) rotate(45deg);
            }
        }
        .company-main-card .company-details {
            flex: 1;
        }
        .company-main-card .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            margin-bottom: 1rem;
            display: inline-block;
            font-size: 0.85rem;
            border: none;
        }
        .company-main-card.plan-free .badge {
            background: rgba(255, 255, 255, 0.2) !important;
            color: white;
            border: none;
        }
        .company-main-card.plan-basic .badge,
        .company-main-card.plan-premium .badge {
            background: rgba(255, 255, 255, 0.2) !important;
            color: white;
        }
        .company-main-card.plan-enterprise .badge {
            background: rgba(255, 255, 255, 0.2) !important;
            color: white;
        }
        .company-main-card h4 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            line-height: 1.2;
        }
        .company-main-card.plan-free h4 {
            color: white;
        }
        .company-main-card.plan-basic h4,
        .company-main-card.plan-premium h4 {
            color: white;
        }
        .company-main-card.plan-enterprise h4 {
            color: white;
            position: relative;
            z-index: 1;
        }
        .company-main-card .owner-name {
            margin-bottom: 1.5rem;
            font-size: 1rem;
        }
        .company-main-card.plan-free .owner-name {
            color: rgba(255, 255, 255, 0.9);
        }
        .company-main-card.plan-basic .owner-name,
        .company-main-card.plan-premium .owner-name {
            color: rgba(255, 255, 255, 0.9);
        }
        .company-main-card.plan-enterprise .owner-name {
            color: white;
            position: relative;
            z-index: 1;
        }
        .company-contact {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-top: 1rem;
        }
        .company-contact span {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
            font-weight: 500;
        }
        .company-main-card.plan-free .company-contact span {
            color: white;
        }
        .company-main-card.plan-basic .company-contact span,
        .company-main-card.plan-premium .company-contact span {
            color: white;
        }
        .company-main-card.plan-enterprise .company-contact span {
            color: white;
            position: relative;
            z-index: 1;
        }
        .company-contact span i {
            font-size: 1rem;
        }
        .company-main-card.plan-free .company-contact span i {
            color: rgba(255, 255, 255, 0.9);
        }
        .company-main-card.plan-basic .company-contact span i,
        .company-main-card.plan-premium .company-contact span i {
            color: rgba(255, 255, 255, 0.9);
        }
        .company-main-card.plan-enterprise .company-contact span i {
            color: white;
        }
        .company-stats {
            display: flex;
            flex-direction: row;
            gap: 1.5rem;
            text-align: center;
            position: relative;
            z-index: 1;
        }
        .company-stats > div {
            display: flex;
            flex-direction: column;
        }
        .company-stats .stat-number {
            font-size: 1.75rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .company-main-card.plan-free .company-stats .stat-number {
            color: white;
        }
        .company-main-card.plan-basic .company-stats .stat-number,
        .company-main-card.plan-premium .company-stats .stat-number {
            color: white;
        }
        .company-main-card.plan-enterprise .company-stats .stat-number {
            color: white;
            position: relative;
            z-index: 1;
        }
        .company-stats small {
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }
        .company-main-card.plan-free .company-stats small {
            color: rgba(255, 255, 255, 0.8);
        }
        .company-main-card.plan-basic .company-stats small,
        .company-main-card.plan-premium .company-stats small {
            color: rgba(255, 255, 255, 0.8);
        }
        .company-main-card.plan-enterprise .company-stats small {
            color: white;
            position: relative;
            z-index: 1;
        }
        .company-info-card {
            background: white;
            border-radius: 18px;
            padding: 1.5rem;
            box-shadow: 0 15px 35px rgba(15,23,42,0.1);
            height: 100%;
            display: flex;
            flex-direction: column;
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
        .company-info-list li:last-child {
            border-bottom: none;
        }
        @media (max-width: 992px) {
            .company-row {
                grid-template-columns: 1fr;
            }
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
            <a href="#" class="nav-link <?php echo $current_section === 'requests' ? 'active' : ''; ?>" data-section-toggle="requests">
                <i class="fas fa-envelope-open-text"></i>
                Email Requests
            </a>
            <a href="#" class="nav-link <?php echo $current_section === 'company' ? 'active' : ''; ?>" data-section-toggle="company">
                <i class="fas fa-building"></i>
                Company Snapshot
            </a>
            <a href="#" class="nav-link <?php echo $current_section === 'contact' ? 'active' : ''; ?>" data-section-toggle="contact">
                <i class="fas fa-comments"></i>
                Contact Messages
            </a>
            <a href="#" class="nav-link <?php echo $current_section === 'testimonials' ? 'active' : ''; ?>" data-section-toggle="testimonials">
                <i class="fas fa-star"></i>
                Testimonials
            </a>
            <a href="settings.php" class="nav-link">
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
        
        <main class="main-content">
        <header class="top-header">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-link p-1 text-primary d-lg-none" id="mobileMenuBtn" aria-label="Toggle Navigation" style="font-size: 1.25rem; text-decoration: none; color: #4f46e5 !important; line-height: 1;">
                    <i class="fas fa-bars"></i>
                </button>
                <h4 class="mb-0 page-main-title fw-bold text-dark" style="text-transform: capitalize; font-size: 1.25rem; letter-spacing: -0.3px;" id="pageTitle"><?php 
                    echo $current_section === 'company' ? 'Companies' : 
                        ($current_section === 'contact' ? 'Contact Messages' : 
                        ($current_section === 'testimonials' ? 'Testimonials' : 'Email Requests')); 
                ?></h4>
                <p class="d-none" id="pageSubtitle"><?php 
                    echo $current_section === 'company' ? 'Overview of all companies onboarded' : 
                        ($current_section === 'contact' ? 'View all contact form submissions from users' : 
                        ($current_section === 'testimonials' ? 'Review and manage customer testimonials' : 'Review pending email change requests')); 
                ?></p>
            </div>
            
            <div class="d-flex align-items-center gap-2">
                <!-- Notifications Bell -->
                <div class="dropdown">
                    <button class="notif-bell-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                        <i class="fas fa-bell"></i>
                        <?php $unreadNotifCount = $stats['pending'] ?? 0; ?>
                        <span class="notif-badge" id="headerNotifBadge" style="<?php echo ($unreadNotifCount > 0) ? '' : 'display: none;'; ?>"><?php echo $unreadNotifCount; ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 notif-dropdown-menu">
                        <li class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <h6 class="mb-0 fw-bold" style="font-size: 0.9rem; color: #1e293b;">Notifications</h6>
                            <span class="badge bg-danger rounded-pill" style="<?php echo ($unreadNotifCount > 0) ? 'display: inline-block;' : 'display: none;'; ?>; font-size: 0.68rem; padding: 2px 7px;"><?php echo $unreadNotifCount; ?> new</span>
                        </li>
                        <div style="max-height: 320px; overflow-y: auto;">
                            <?php if ($unreadNotifCount > 0): ?>
                                <li>
                                    <a class="dropdown-item d-flex align-items-start gap-2 p-2 rounded bg-light-subtle" href="index.php?section=requests" style="white-space: normal;">
                                        <i class="fas fa-envelope-open-text text-primary mt-1" style="font-size: 1rem;"></i>
                                        <div class="flex-grow-1" style="min-width: 0;">
                                            <div class="fw-semibold text-dark text-truncate" style="font-size: 0.82rem;">Email Change Requests</div>
                                            <div class="text-muted text-break" style="font-size: 0.74rem; line-height: 1.35;"><?php echo $unreadNotifCount; ?> pending request(s) awaiting approval</div>
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
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : ($messageType === 'error' ? 'danger' : 'info'); ?> alert-dismissible fade show">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'error' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <section id="section-requests" class="content-section" style="display: <?php echo $current_section === 'requests' ? 'block' : 'none'; ?>;">
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="er-stat-card er-stat-total">
                    <div class="er-stat-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="er-stat-info">
                        <div class="er-stat-label">Total Requests</div>
                        <div class="er-stat-value"><?php echo $stats['total']; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="er-stat-card er-stat-pending">
                    <div class="er-stat-icon">
                        <i class="far fa-clock"></i>
                    </div>
                    <div class="er-stat-info">
                        <div class="er-stat-label">Pending</div>
                        <div class="er-stat-value"><?php echo $stats['pending']; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="er-stat-card er-stat-approved">
                    <div class="er-stat-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="er-stat-info">
                        <div class="er-stat-label">Approved</div>
                        <div class="er-stat-value"><?php echo $stats['approved']; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="er-stat-card er-stat-rejected">
                    <div class="er-stat-icon">
                        <i class="fas fa-times"></i>
                    </div>
                    <div class="er-stat-info">
                        <div class="er-stat-label">Rejected</div>
                        <div class="er-stat-value"><?php echo $stats['rejected']; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="er-filter-bar">
            <div class="er-filter-tabs" role="tablist">
                <a href="?status=pending" class="er-filter-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                    <i class="fas fa-clock" style="color: <?php echo $status_filter === 'pending' ? '#4f46e5' : '#f59e0b'; ?>;"></i>
                    <span>Pending</span>
                    <span class="er-tab-count"><?php echo $stats['pending']; ?></span>
                </a>
                <a href="?status=approved" class="er-filter-tab <?php echo $status_filter === 'approved' ? 'active' : ''; ?>">
                    <i class="fas fa-check" style="color: #22c55e;"></i>
                    <span>Approved</span>
                    <span class="er-tab-count"><?php echo $stats['approved']; ?></span>
                </a>
                <a href="?status=rejected" class="er-filter-tab <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>">
                    <i class="fas fa-times" style="color: #fb5664;"></i>
                    <span>Rejected</span>
                    <span class="er-tab-count"><?php echo $stats['rejected']; ?></span>
                </a>
                <span class="er-tabs-divider d-none d-sm-inline-block"></span>
            </div>
            <div class="dropdown py-2">
                <button class="er-all-dropdown-btn dropdown-toggle <?php echo $status_filter === 'all' ? 'is-all' : ''; ?>" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-filter" style="font-size: 0.78rem;"></i>
                    <span><?php echo ($status_filter === 'all' || $status_filter === 'pending') ? 'All' : ucfirst($status_filter); ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                    <li><a class="dropdown-item d-flex justify-content-between align-items-center <?php echo $status_filter === 'all' ? 'active' : ''; ?>" href="?status=all"><span>All</span><span class="badge bg-light text-dark ms-2"><?php echo $stats['total']; ?></span></a></li>
                    <li><a class="dropdown-item d-flex justify-content-between align-items-center <?php echo $status_filter === 'pending' ? 'active' : ''; ?>" href="?status=pending"><span>Pending</span><span class="badge bg-light text-dark ms-2"><?php echo $stats['pending']; ?></span></a></li>
                    <li><a class="dropdown-item d-flex justify-content-between align-items-center <?php echo $status_filter === 'approved' ? 'active' : ''; ?>" href="?status=approved"><span>Approved</span><span class="badge bg-light text-dark ms-2"><?php echo $stats['approved']; ?></span></a></li>
                    <li><a class="dropdown-item d-flex justify-content-between align-items-center <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>" href="?status=rejected"><span>Rejected</span><span class="badge bg-light text-dark ms-2"><?php echo $stats['rejected']; ?></span></a></li>
                </ul>
            </div>
        </div>

        <div class="er-requests-card">
            <div class="er-requests-card-header d-flex justify-content-between align-items-center">
                <h5 class="er-requests-card-title">Email Change Requests</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($requests)): ?>
                    <div class="er-empty-state">
                        <div class="mb-2 d-inline-block">
                            <svg width="210" height="126" viewBox="0 0 210 126" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <defs>
                                    <linearGradient id="erEnvBody" x1="58" y1="22" x2="148" y2="100" gradientUnits="userSpaceOnUse">
                                        <stop offset="0%" stop-color="#D7DEFF"/>
                                        <stop offset="100%" stop-color="#B9C5FC"/>
                                    </linearGradient>
                                    <linearGradient id="erEnvFlap" x1="58" y1="22" x2="148" y2="68" gradientUnits="userSpaceOnUse">
                                        <stop offset="0%" stop-color="#E5EAFF"/>
                                        <stop offset="100%" stop-color="#C8D2FE"/>
                                    </linearGradient>
                                    <filter id="erBadgeShadow" x="98" y="48" width="60" height="60" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
                                        <feDropShadow dx="0" dy="4" stdDeviation="6" flood-color="#4F46E5" flood-opacity="0.16"/>
                                    </filter>
                                </defs>
                                <path d="M18 86C14 68 38 42 74 44C102 45 124 22 158 28C186 33 198 58 188 78C178 98 136 108 98 106C58 104 22 104 18 86Z" fill="#EFF2FC"/>
                                <circle cx="40" cy="30" r="2.2" fill="#1E1B4B"/>
                                <circle cx="163" cy="18" r="3.6" fill="#C7D2FE"/>
                                <rect x="57" y="23" width="90" height="68" rx="10" fill="url(#erEnvBody)" stroke="#9AA8EC" stroke-width="1"/>
                                <path d="M58 29L102 62L146 29" stroke="#8C9CE8" stroke-width="1.2" fill="url(#erEnvFlap)"/>
                                <path d="M59 87L89 58M145 87L115 58" stroke="#A6B4F4" stroke-width="1.1"/>
                                <g filter="url(#erBadgeShadow)">
                                    <circle cx="128" cy="78" r="22" fill="#FFFFFF"/>
                                </g>
                                <circle cx="128" cy="78" r="14" stroke="#4F46E5" stroke-width="2.6"/>
                                <path d="M128 70.5V78.5L133.5 81.5" stroke="#4F46E5" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <h4 class="er-empty-title">No requests found</h4>
                        <p class="er-empty-subtitle">
                            <?php if ($stats['total'] > 0 && $status_filter && $status_filter !== 'all'): ?>
                                You don't have any <?php echo $status_filter; ?> requests at the moment. Check back later.
                            <?php else: ?>
                                No email change requests have been submitted yet.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.95rem;">
                            <thead class="bg-light text-muted" style="font-size: 0.85rem; letter-spacing: 0.5px;">
                                <tr>
                                    <th class="border-0 px-4 py-3 fw-semibold text-uppercase">Company</th>
                                    <th class="border-0 px-4 py-3 fw-semibold text-uppercase">Owner</th>
                                    <th class="border-0 px-4 py-3 fw-semibold text-uppercase">Email Details</th>
                                    <th class="border-0 px-4 py-3 fw-semibold text-uppercase">Status</th>
                                    <th class="border-0 px-4 py-3 fw-semibold text-uppercase text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $request): ?>
                                    <tr style="transition: all 0.2s; border-bottom: 1px solid #f8f9fa;">
                                        <td class="px-4 py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="d-flex align-items-center justify-content-center rounded-circle me-3 text-white fw-bold shadow-sm" style="width: 42px; height: 42px; background: linear-gradient(135deg, #4f46e5, #4338ca);">
                                                    <?php echo strtoupper(substr($request['company_name'] ?? 'C', 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold" style="color: #334155;"><?php echo escape($request['company_name'] ?? 'N/A'); ?></div>
                                                    <div class="text-muted small">ID: #<?php echo $request['company_id'] ?? 'N/A'; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div style="color: #475569; font-weight: 500;"><?php echo escape($request['owner_name'] ?? 'N/A'); ?></div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="d-flex flex-column gap-1">
                                                <div class="d-flex align-items-center" style="font-size: 0.85rem;">
                                                    <span class="text-muted me-2" style="width: 35px; font-weight: 500;">From:</span>
                                                    <span class="text-secondary text-decoration-line-through"><?php echo htmlspecialchars($request['current_email']); ?></span>
                                                </div>
                                                <div class="d-flex align-items-center" style="font-size: 0.85rem;">
                                                    <span class="text-muted me-2" style="width: 35px; font-weight: 500;">To:</span>
                                                    <span class="fw-bold" style="color: #4f46e5;"><?php echo htmlspecialchars($request['new_email']); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="badge rounded-pill px-3 py-2 fw-semibold <?php 
                                                if ($request['status'] === 'pending') echo 'bg-warning bg-opacity-10 border border-warning border-opacity-25';
                                                elseif ($request['status'] === 'approved') echo 'bg-success bg-opacity-10 text-success border border-success border-opacity-25';
                                                elseif ($request['status'] === 'rejected') echo 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25';
                                                else echo 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25';
                                            ?> " <?php if ($request['status'] === 'pending') echo 'style="color: #b45309;"'; ?>>
                                                <i class="fas fa-circle me-1" style="font-size: 0.4rem; vertical-align: middle;"></i>
                                                <?php echo ucfirst($request['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-end">
                                            <div class="d-flex justify-content-end gap-2">
                                                <button type="button" 
                                                        class="btn btn-sm btn-light rounded-circle shadow-sm" 
                                                        style="width: 35px; height: 35px; display: inline-flex; align-items: center; justify-content: center; color: #64748b; transition: all 0.2s;"
                                                        onmouseover="this.style.color='#3b82f6'; this.style.backgroundColor='#eff6ff';"
                                                        onmouseout="this.style.color='#64748b'; this.style.backgroundColor='#f8f9fa';"
                                                        onclick="viewEmailRequestDetails(<?php echo $request['id']; ?>)"
                                                        title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($request['status'] === 'pending'): ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-light rounded-circle shadow-sm"
                                                            style="width: 35px; height: 35px; display: inline-flex; align-items: center; justify-content: center; color: #10b981; transition: all 0.2s;"
                                                            onmouseover="this.style.backgroundColor='#ecfdf5';"
                                                            onmouseout="this.style.backgroundColor='#f8f9fa';"
                                                            onclick="openApproveModal(<?php echo $request['id']; ?>)"
                                                            title="Approve">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-light rounded-circle shadow-sm"
                                                            style="width: 35px; height: 35px; display: inline-flex; align-items: center; justify-content: center; color: #ef4444; transition: all 0.2s;"
                                                            onmouseover="this.style.backgroundColor='#fef2f2';"
                                                            onmouseout="this.style.backgroundColor='#f8f9fa';"
                                                            onclick="rejectRequest(<?php echo $request['id']; ?>)"
                                                            title="Reject">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        </section>

        <section id="section-company" class="content-section" style="display: <?php echo $current_section === 'company' ? 'block' : 'none'; ?>;">
            <div class="cs-toolbar sticky-controls">
                <div class="cs-search-wrap">
                    <i class="fas fa-search cs-search-icon"></i>
                    <input type="text" class="cs-search-input" id="companySearch" placeholder="Search company by name, owner, or city...">
                </div>
                <div class="cs-plan-wrap">
                    <i class="fas fa-filter" style="color: #4f46e5; font-size: 0.82rem;"></i>
                    <select class="form-select cs-plan-select" id="planFilter" data-plan-filter>
                        <option value="">All Plans</option>
                        <option value="free">Free</option>
                        <option value="basic">Basic</option>
                        <option value="premium">Premium</option>
                        <option value="enterprise">Enterprise</option>
                    </select>
                </div>
            </div>
            <div id="companiesGrid" class="company-grid">
                <div class="text-center text-muted py-5 w-100">Loading companies...</div>
            </div>
        </section>

        <section id="section-contact" class="content-section" style="display: <?php echo $current_section === 'contact' ? 'block' : 'none'; ?>;">
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-4">
                    <div class="er-stat-card er-stat-total">
                        <div class="er-stat-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <div class="er-stat-info">
                            <div class="er-stat-label">Total Messages</div>
                            <div class="er-stat-value" id="contactStatTotal"><?php echo $contactStats['total']; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="er-stat-card er-stat-approved">
                        <div class="er-stat-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="er-stat-info">
                            <div class="er-stat-label">From Logged In Users</div>
                            <div class="er-stat-value" id="contactStatLoggedIn"><?php echo $contactStats['logged_in']; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="er-stat-card er-stat-guests">
                        <div class="er-stat-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="er-stat-info">
                            <div class="er-stat-label">From Guests</div>
                            <div class="er-stat-value" id="contactStatGuests"><?php echo $contactStats['guests']; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="er-filter-bar cm-filter-bar">
                <div class="er-filter-tabs" role="tablist">
                    <a href="javascript:void(0)" class="er-filter-tab active" data-contact-filter="all">
                        <i class="fas fa-inbox" style="color: #4f46e5;"></i>
                        <span>All</span>
                        <span class="er-tab-count" id="contactTabCountAll"><?php echo $contactStats['total']; ?></span>
                    </a>
                    <a href="javascript:void(0)" class="er-filter-tab" data-contact-filter="logged_in">
                        <i class="fas fa-user-check" style="color: #22c55e;"></i>
                        <span>Logged In</span>
                        <span class="er-tab-count" id="contactTabCountLoggedIn"><?php echo $contactStats['logged_in']; ?></span>
                    </a>
                    <a href="javascript:void(0)" class="er-filter-tab" data-contact-filter="guests">
                        <i class="fas fa-user" style="color: #64748b;"></i>
                        <span>Guests</span>
                        <span class="er-tab-count" id="contactTabCountGuests"><?php echo $contactStats['guests']; ?></span>
                    </a>
                </div>
                <div class="cm-search-wrap">
                    <i class="fas fa-search text-muted" style="font-size: 0.82rem;"></i>
                    <input type="text" class="cm-search-input" id="contactSearchInput" placeholder="Search name or email...">
                </div>
            </div>

            <div class="er-requests-card">
                <div class="er-requests-card-header d-flex justify-content-between align-items-center">
                    <h5 class="er-requests-card-title">Contact Messages</h5>
                    <span class="er-tab-count" id="contactVisibleCount"><?php echo $contactStats['total']; ?></span>
                </div>
                <div class="card-body p-0" id="contactMessagesBody">
                    <?php if (empty($contactMessages)): ?>
                        <div class="er-empty-state">
                            <div class="mb-2 d-inline-block">
                                <svg width="210" height="126" viewBox="0 0 210 126" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <defs>
                                        <linearGradient id="cmEnvBody" x1="58" y1="22" x2="148" y2="100" gradientUnits="userSpaceOnUse">
                                            <stop offset="0%" stop-color="#D7DEFF"/>
                                            <stop offset="100%" stop-color="#B9C5FC"/>
                                        </linearGradient>
                                        <filter id="cmBadgeShadow" x="98" y="48" width="60" height="60" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
                                            <feDropShadow dx="0" dy="4" stdDeviation="6" flood-color="#4F46E5" flood-opacity="0.16"/>
                                        </filter>
                                    </defs>
                                    <path d="M18 86C14 68 38 42 74 44C102 45 124 22 158 28C186 33 198 58 188 78C178 98 136 108 98 106C58 104 22 104 18 86Z" fill="#EFF2FC"/>
                                    <circle cx="40" cy="30" r="2.2" fill="#1E1B4B"/>
                                    <circle cx="163" cy="18" r="3.6" fill="#C7D2FE"/>
                                    <rect x="57" y="25" width="90" height="62" rx="14" fill="url(#cmEnvBody)" stroke="#9AA8EC" stroke-width="1"/>
                                    <circle cx="84" cy="56" r="4" fill="#FFFFFF"/>
                                    <circle cx="102" cy="56" r="4" fill="#FFFFFF"/>
                                    <circle cx="120" cy="56" r="4" fill="#FFFFFF"/>
                                    <g filter="url(#cmBadgeShadow)">
                                        <circle cx="132" cy="78" r="21" fill="#FFFFFF"/>
                                    </g>
                                    <path d="M124 73H140M124 79H135" stroke="#4F46E5" stroke-width="2.6" stroke-linecap="round"/>
                                </svg>
                            </div>
                            <h4 class="er-empty-title">No contact messages found</h4>
                            <p class="er-empty-subtitle">No contact form submissions have been received yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 0.94rem;">
                                <thead class="bg-light text-muted" style="font-size: 0.8rem; letter-spacing: 0.5px;">
                                    <tr>
                                        <th class="border-0 px-4 py-3 fw-semibold text-uppercase">Sender</th>
                                        <th class="border-0 px-4 py-3 fw-semibold text-uppercase">Email</th>
                                        <th class="border-0 px-4 py-3 fw-semibold text-uppercase">User Type</th>
                                        <th class="border-0 px-4 py-3 fw-semibold text-uppercase">Received</th>
                                        <th class="border-0 px-4 py-3 fw-semibold text-uppercase text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contactMessages as $msg):
                                        $isLogged = isset($msg['user_logged']) && $msg['user_logged'] == 1;
                                        $formattedDate = !empty($msg['created_date']) ? date('M d, Y', strtotime($msg['created_date'])) : '—';
                                    ?>
                                        <tr style="transition: all 0.2s; border-bottom: 1px solid #f1f5f9;">
                                            <td class="px-4 py-3">
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="cm-avatar">
                                                        <?php echo strtoupper(substr(trim($msg['name'] ?? 'U'), 0, 1)) ?: 'U'; ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold" style="color: #0f172a;"><?php echo htmlspecialchars($msg['name']); ?></div>
                                                        <div class="text-muted small">ID: #<?php echo htmlspecialchars($msg['id']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <a href="mailto:<?php echo htmlspecialchars($msg['emailId']); ?>" class="cm-email-link">
                                                    <?php echo htmlspecialchars($msg['emailId']); ?>
                                                </a>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="cm-type-badge <?php echo $isLogged ? 'is-logged' : 'is-guest'; ?>">
                                                    <i class="fas <?php echo $isLogged ? 'fa-user-check' : 'fa-user'; ?>" style="font-size: 0.72rem;"></i>
                                                    <?php echo $isLogged ? 'Logged In' : 'Guest'; ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-muted" style="font-size: 0.88rem; white-space: nowrap;">
                                                <?php echo htmlspecialchars($formattedDate); ?>
                                            </td>
                                            <td class="px-4 py-3 text-end">
                                                <button class="btn btn-sm btn-primary cm-view-btn view-contact-btn" data-message='<?php echo htmlspecialchars(json_encode($msg), ENT_QUOTES, 'UTF-8'); ?>'>
                                                    <i class="fas fa-eye"></i>
                                                    <span>View</span>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section id="section-testimonials" class="content-section" style="display: <?php echo $current_section === 'testimonials' ? 'block' : 'none'; ?>;">
        <?php 
        $testimonial_status_filter = $_GET['status'] ?? 'pending';
        ?>
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="er-stat-card er-stat-total">
                    <div class="er-stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="er-stat-info">
                        <div class="er-stat-label">Total Testimonials</div>
                        <div class="er-stat-value"><?php echo $testimonialStats['total']; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="er-stat-card er-stat-pending">
                    <div class="er-stat-icon">
                        <i class="far fa-clock"></i>
                    </div>
                    <div class="er-stat-info">
                        <div class="er-stat-label">Pending</div>
                        <div class="er-stat-value"><?php echo $testimonialStats['pending']; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="er-stat-card er-stat-approved">
                    <div class="er-stat-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="er-stat-info">
                        <div class="er-stat-label">Approved</div>
                        <div class="er-stat-value"><?php echo $testimonialStats['approved']; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="er-stat-card er-stat-rejected">
                    <div class="er-stat-icon">
                        <i class="fas fa-times"></i>
                    </div>
                    <div class="er-stat-info">
                        <div class="er-stat-label">Rejected</div>
                        <div class="er-stat-value"><?php echo $testimonialStats['rejected']; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="er-filter-bar">
            <div class="er-filter-tabs" role="tablist">
                <a href="?section=testimonials&status=pending" class="er-filter-tab <?php echo $testimonial_status_filter === 'pending' ? 'active' : ''; ?>">
                    <i class="fas fa-clock" style="color: <?php echo $testimonial_status_filter === 'pending' ? '#4f46e5' : '#f59e0b'; ?>;"></i>
                    <span>Pending</span>
                    <span class="er-tab-count"><?php echo $testimonialStats['pending']; ?></span>
                </a>
                <a href="?section=testimonials&status=approved" class="er-filter-tab <?php echo $testimonial_status_filter === 'approved' ? 'active' : ''; ?>">
                    <i class="fas fa-check" style="color: #22c55e;"></i>
                    <span>Approved</span>
                    <span class="er-tab-count"><?php echo $testimonialStats['approved']; ?></span>
                </a>
                <a href="?section=testimonials&status=rejected" class="er-filter-tab <?php echo $testimonial_status_filter === 'rejected' ? 'active' : ''; ?>">
                    <i class="fas fa-times" style="color: #fb5664;"></i>
                    <span>Rejected</span>
                    <span class="er-tab-count"><?php echo $testimonialStats['rejected']; ?></span>
                </a>
                <span class="er-tabs-divider d-none d-sm-inline-block"></span>
            </div>
            <div class="dropdown py-2">
                <button class="er-all-dropdown-btn dropdown-toggle <?php echo $testimonial_status_filter === 'all' ? 'is-all' : ''; ?>" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-filter" style="font-size: 0.78rem;"></i>
                    <span><?php echo ($testimonial_status_filter === 'all' || $testimonial_status_filter === 'pending') ? 'All' : ucfirst($testimonial_status_filter); ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                    <li><a class="dropdown-item d-flex justify-content-between align-items-center <?php echo $testimonial_status_filter === 'all' ? 'active' : ''; ?>" href="?section=testimonials&status=all"><span>All</span><span class="badge bg-light text-dark ms-2"><?php echo $testimonialStats['total']; ?></span></a></li>
                    <li><a class="dropdown-item d-flex justify-content-between align-items-center <?php echo $testimonial_status_filter === 'pending' ? 'active' : ''; ?>" href="?section=testimonials&status=pending"><span>Pending</span><span class="badge bg-light text-dark ms-2"><?php echo $testimonialStats['pending']; ?></span></a></li>
                    <li><a class="dropdown-item d-flex justify-content-between align-items-center <?php echo $testimonial_status_filter === 'approved' ? 'active' : ''; ?>" href="?section=testimonials&status=approved"><span>Approved</span><span class="badge bg-light text-dark ms-2"><?php echo $testimonialStats['approved']; ?></span></a></li>
                    <li><a class="dropdown-item d-flex justify-content-between align-items-center <?php echo $testimonial_status_filter === 'rejected' ? 'active' : ''; ?>" href="?section=testimonials&status=rejected"><span>Rejected</span><span class="badge bg-light text-dark ms-2"><?php echo $testimonialStats['rejected']; ?></span></a></li>
                </ul>
            </div>
        </div>

        <div class="er-requests-card">
            <div class="er-requests-card-header d-flex justify-content-between align-items-center">
                <h5 class="er-requests-card-title">Customer Testimonials</h5>
                <span class="er-tab-count"><?php echo count($testimonials); ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($testimonials)): ?>
                    <div class="er-empty-state">
                        <div class="mb-2 d-inline-block">
                            <svg width="210" height="126" viewBox="0 0 210 126" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <defs>
                                    <linearGradient id="tmStarBody" x1="66" y1="18" x2="138" y2="96" gradientUnits="userSpaceOnUse">
                                        <stop offset="0%" stop-color="#D7DEFF"/>
                                        <stop offset="100%" stop-color="#B9C5FC"/>
                                    </linearGradient>
                                    <filter id="tmBadgeShadow" x="98" y="48" width="60" height="60" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
                                        <feDropShadow dx="0" dy="4" stdDeviation="6" flood-color="#4F46E5" flood-opacity="0.16"/>
                                    </filter>
                                </defs>
                                <path d="M18 86C14 68 38 42 74 44C102 45 124 22 158 28C186 33 198 58 188 78C178 98 136 108 98 106C58 104 22 104 18 86Z" fill="#EFF2FC"/>
                                <circle cx="40" cy="30" r="2.2" fill="#1E1B4B"/>
                                <circle cx="163" cy="18" r="3.6" fill="#C7D2FE"/>
                                <path d="M102 22L112.5 43.2L136 46.6L119 63.1L123 86.5L102 75.5L81 86.5L85 63.1L68 46.6L91.5 43.2L102 22Z" fill="url(#tmStarBody)" stroke="#9AA8EC" stroke-width="1.2" stroke-linejoin="round"/>
                                <g filter="url(#tmBadgeShadow)">
                                    <circle cx="132" cy="78" r="21" fill="#FFFFFF"/>
                                </g>
                                <path d="M132 69L134.8 74.6L141 75.5L136.5 79.9L137.6 86L132 83.1L126.4 86L127.5 79.9L123 75.5L129.2 74.6L132 69Z" stroke="#4F46E5" stroke-width="2" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <h4 class="er-empty-title">No testimonials found</h4>
                        <p class="er-empty-subtitle">
                            <?php if ($testimonialStats['total'] > 0 && $testimonial_status_filter && $testimonial_status_filter !== 'all'): ?>
                                You don't have any <?php echo $testimonial_status_filter; ?> testimonials at the moment.
                            <?php else: ?>
                                No testimonials have been submitted yet.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.94rem;">
                            <thead class="bg-light text-muted" style="font-size: 0.8rem; letter-spacing: 0.5px;">
                                <tr>
                                    <th class="border-0 px-4 py-3 fw-semibold text-uppercase">Customer</th>
                                    <th class="border-0 px-4 py-3 fw-semibold text-uppercase">Email</th>
                                    <th class="border-0 px-4 py-3 fw-semibold text-uppercase">Shop Details</th>
                                    <th class="border-0 px-4 py-3 fw-semibold text-uppercase">Rating</th>
                                    <th class="border-0 px-4 py-3 fw-semibold text-uppercase">Status</th>
                                    <th class="border-0 px-4 py-3 fw-semibold text-uppercase text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($testimonials as $testimonial):
                                    $tName = $testimonial['user_name'] ?? 'N/A';
                                    $tInitial = strtoupper(substr(trim($tName), 0, 1)) ?: 'T';
                                    $tShop = $testimonial['company_name'] ?? '';
                                    $tPerson = !empty($testimonial['owner_name']) ? $testimonial['owner_name'] : ($testimonial['tailor_name'] ?? '');
                                    $tStars = max(1, min(5, (int)($testimonial['star'] ?? 5)));
                                    $tStatus = $testimonial['status'] ?? 'pending';
                                ?>
                                    <tr style="transition: all 0.2s; border-bottom: 1px solid #f1f5f9;">
                                        <td class="px-4 py-3">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="cm-avatar"><?php echo escape($tInitial); ?></div>
                                                <div>
                                                    <div class="fw-bold" style="color: #0f172a;"><?php echo escape($tName); ?></div>
                                                    <div class="text-muted small">ID: #<?php echo htmlspecialchars($testimonial['id']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <a href="mailto:<?php echo htmlspecialchars($testimonial['email']); ?>" class="cm-email-link">
                                                <?php echo htmlspecialchars($testimonial['email']); ?>
                                            </a>
                                        </td>
                                        <td class="px-4 py-3">
                                            <?php if (!empty($tShop)): ?>
                                                <div class="fw-semibold" style="color: #1e293b;"><?php echo escape($tShop); ?></div>
                                                <?php if (!empty($tPerson)): ?>
                                                    <div class="small text-muted"><?php echo escape($tPerson); ?></div>
                                                <?php endif; ?>
                                            <?php elseif (!empty($tPerson)): ?>
                                                <div class="fw-medium" style="color: #334155;"><?php echo escape($tPerson); ?></div>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3" style="white-space: nowrap;">
                                            <div class="d-inline-flex align-items-center gap-1" title="<?php echo $tStars; ?>/5">
                                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                                    <i class="fas fa-star" style="font-size: 0.78rem; color: <?php echo $s <= $tStars ? '#f59e0b' : '#e2e8f0'; ?>;"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <?php if ($tStatus === 'approved'): ?>
                                                <span class="cm-type-badge" style="background: #dcfce7; color: #15803d;">
                                                    <i class="fas fa-check-circle" style="font-size: 0.75rem;"></i>
                                                    Approved
                                                </span>
                                            <?php elseif ($tStatus === 'rejected'): ?>
                                                <span class="cm-type-badge" style="background: #ffe4e6; color: #be123c;">
                                                    <i class="fas fa-times-circle" style="font-size: 0.75rem;"></i>
                                                    Rejected
                                                </span>
                                            <?php else: ?>
                                                <span class="cm-type-badge" style="background: #ffedd5; color: #b45309;">
                                                    <i class="fas fa-clock" style="font-size: 0.75rem;"></i>
                                                    Pending
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-end">
                                            <button type="button"
                                                    class="btn btn-sm btn-primary cm-view-btn"
                                                    onclick="viewTestimonialDetails(<?php echo $testimonial['id']; ?>)"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                                <span>View</span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        </section>
        </div>
        </main>
    </div>

    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Approve Email Change Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="approveForm">
                    <div class="modal-body">
                        <input type="hidden" name="request_id" id="approveRequestId">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <div class="text-center py-2">
                            <i class="fas fa-question-circle fa-3x text-success mb-3"></i>
                            <p class="mb-0">
                                Are you sure you want to approve this email change request?
                                This will immediately update the company's login email.
                            </p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-2"></i>Confirm Approval
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="approveTestimonialModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Approve Testimonial</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="approveTestimonialForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="testimonial_id" id="approveTestimonialId">
                        <div class="text-center py-2">
                            <i class="fas fa-question-circle fa-3x text-success mb-3"></i>
                            <p class="mb-0">
                                Are you sure you want to approve this testimonial? It will be displayed on the frontend.
                            </p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-2"></i>Confirm Approval
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rejectTestimonialModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Testimonial</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="rejectTestimonialForm">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="testimonial_id" id="rejectTestimonialId">
                        <div class="text-center py-2 mb-3">
                            <i class="fas fa-question-circle fa-3x text-danger mb-3"></i>
                            <p class="mb-0">
                                Are you sure you want to reject this testimonial? It will not be displayed on the frontend.
                            </p>
                        </div>
                        <div class="mb-3">
                            <label for="rejectTestimonialNotes" class="form-label">Rejection Reason (Optional)</label>
                            <textarea class="form-control" id="rejectTestimonialNotes" name="review_notes" rows="3" placeholder="Enter reason for rejection..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times me-2"></i>Confirm Rejection
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewTestimonialModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Testimonial Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="testimonialDetailsContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Email Change Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="rejectForm">
                    <div class="modal-body">
                        <input type="hidden" name="request_id" id="rejectRequestId">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <div class="mb-3">
                            <label for="rejectNotes" class="form-label">Rejection Reason (Optional)</label>
                            <textarea class="form-control" id="rejectNotes" name="review_notes" rows="3" placeholder="Enter reason for rejection..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times me-2"></i>Reject Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="contactMessageModal" tabindex="-1" aria-labelledby="contactMessageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="contactMessageModalLabel"><i class="fas fa-comments me-2"></i>Contact Message Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="contactMessageDetails">
                    </div>
                    
                    <div class="response-section pt-3 mt-3 border-top">
                        <h6 class="mb-3"><i class="fas fa-reply me-2"></i>Send Response</h6>
                        <div id="contactResponseAlert" class="alert d-none" role="alert"></div>
                        <form id="contactResponseForm">
                            <input type="hidden" id="contactResponseMessageId" name="message_id">
                            <input type="hidden" id="contactResponseEmail" name="email">
                            <input type="hidden" id="contactResponseName" name="name">
                            <input type="hidden" id="contactResponseOriginalMessage" name="original_message">
                            
                            <div class="mb-3">
                                <label for="contactResponseSubject" class="form-label">Subject</label>
                                <input type="text" class="form-control" id="contactResponseSubject" name="subject" value="" placeholder="Enter subject here..." required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="contactResponseMessage" class="form-label">Response Message</label>
                                <textarea class="form-control" id="contactResponseMessage" name="message" rows="5" required placeholder="Type your response here..."></textarea>
                            </div>
                            
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-primary" id="sendContactResponseBtn">
                                    <i class="fas fa-paper-plane me-2"></i>Send Response
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="viewEmailRequestModal" tabindex="-1" aria-labelledby="viewEmailRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="viewEmailRequestModalLabel"><i class="fas fa-envelope me-2"></i>Email Request Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewEmailRequestModalContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3 text-muted">Loading request details...</p>
                    </div>
                </div>
                <div class="modal-footer" id="viewEmailRequestModalFooter">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Attach event listeners to PHP-rendered view buttons on page load
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.view-contact-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    try {
                        const messageData = JSON.parse(this.getAttribute('data-message'));
                        if (typeof viewContactMessage === 'function') {
                            viewContactMessage(messageData);
                        } else {
                            console.error('viewContactMessage function not found');
                        }
                    } catch (e) {
                        console.error('Error parsing message data:', e);
                        alert('Error loading message details. Please try again.');
                    }
                });
            });
        });
        
        const approveModalEl = document.getElementById('approveModal');
        const approveForm = document.getElementById('approveForm');
        const rejectModalEl = document.getElementById('rejectModal');
        const rejectForm = document.getElementById('rejectForm');

        function openApproveModal(requestId) {
            document.getElementById('approveRequestId').value = requestId;
            const modal = bootstrap.Modal.getOrCreateInstance(approveModalEl);
            modal.show();
        }

        function rejectRequest(requestId) {
            document.getElementById('rejectRequestId').value = requestId;
            const modal = bootstrap.Modal.getOrCreateInstance(rejectModalEl);
            modal.show();
        }

        function viewEmailRequestDetails(requestId) {
            const modalTitle = document.getElementById('viewEmailRequestModalLabel');
            const modalBody = document.getElementById('viewEmailRequestModalContent');
            const modalFooter = document.getElementById('viewEmailRequestModalFooter');
            const viewModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('viewEmailRequestModal'));

            modalTitle.innerHTML = `<i class="fas fa-envelope me-2"></i>Email Request Details`;
            modalBody.innerHTML = `<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-3 text-muted">Loading request details...</p></div>`;
            modalFooter.style.display = 'none';
            viewModal.show();

            fetch(`ajax/get_email_request_details.php?request_id=${requestId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        modalBody.innerHTML = renderEmailRequestDetails(data.request);
                    } else {
                        modalBody.innerHTML = `<div class="alert alert-danger">${data.message || 'Failed to load request details'}</div>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalBody.innerHTML = '<div class="alert alert-danger">Error loading request details. Please try again.</div>';
                });
        }

        function approveFromViewModal(requestId) {
            const viewModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('viewEmailRequestModal'));
            viewModal.hide();
            setTimeout(() => {
                openApproveModal(requestId);
            }, 400);
        }

        function rejectFromViewModal(requestId) {
            const viewModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('viewEmailRequestModal'));
            viewModal.hide();
            setTimeout(() => {
                rejectRequest(requestId);
            }, 400);
        }

        function renderEmailRequestDetails(request) {
            const statusClass = request.status === 'pending' ? 'warning' : 
                              request.status === 'approved' ? 'success' : 'danger';
            
            return `
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label class="form-label fw-bold text-muted">Request ID</label>
                        <p class="mb-0">#${request.id || 'N/A'}</p>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label fw-bold text-muted">Status</label>
                        <p class="mb-0">
                            <span class="badge bg-${statusClass}">${(request.status || 'pending').toUpperCase()}</span>
                        </p>
                    </div>
                    <div class="col-12 mb-2">
                        <hr>
                        <h6 class="fw-bold text-muted mb-2"><i class="fas fa-building me-2"></i>Company Information</h6>
                        <div class="row">
                            <div class="col-md-6 mb-1">
                                <label class="form-label fw-bold text-muted small mb-0">Company Name</label>
                                <p class="mb-0 small">${request.company_name || 'N/A'}</p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="form-label fw-bold text-muted small mb-0">Owner</label>
                                <p class="mb-0 small">${request.owner_name || 'N/A'}</p>
                            </div>
                            ${request.company_email ? `
                            <div class="col-md-6 mb-1">
                                <label class="form-label fw-bold text-muted small mb-0">Company Email</label>
                                <p class="mb-0 small"><a href="mailto:${request.company_email}"><i class="fas fa-envelope me-1"></i>${request.company_email}</a></p>
                            </div>
                            ` : ''}
                            ${request.company_phone ? `
                            <div class="col-md-6 mb-1">
                                <label class="form-label fw-bold text-muted small mb-0">Company Phone</label>
                                <p class="mb-0 small"><a href="tel:${request.company_phone}"><i class="fas fa-phone me-1"></i>${request.company_phone}</a></p>
                            </div>
                            ` : ''}
                            ${request.company_address ? `
                            <div class="col-12 mb-1">
                                <label class="form-label fw-bold text-muted small mb-0">Company Address</label>
                                <p class="mb-0 small"><i class="fas fa-map-pin me-1"></i>${request.company_address}</p>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    <div class="col-12 mb-2">
                        <hr>
                        <h6 class="fw-bold text-muted mb-2"><i class="fas fa-envelope-open me-2"></i>Email Change Details</h6>
                        <div class="row">
                            <div class="col-md-6 mb-1">
                                <label class="form-label fw-bold text-muted small mb-0">Current Email</label>
                                <p class="mb-0 small"><a href="mailto:${request.current_email}"><i class="fas fa-envelope me-1"></i>${request.current_email}</a></p>
                            </div>
                            <div class="col-md-6 mb-1">
                                <label class="form-label fw-bold text-muted small mb-0">Requested Email</label>
                                <p class="mb-0 small"><a href="mailto:${request.new_email}"><i class="fas fa-envelope me-1"></i>${request.new_email}</a></p>
                            </div>
                            ${request.reason ? `
                            <div class="col-12 mb-1">
                                <label class="form-label fw-bold text-muted small mb-0">Reason for Change</label>
                                <p class="mb-0 small">${request.reason}</p>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    <div class="col-12 mb-2">
                        <hr>
                        <h6 class="fw-bold text-muted mb-2"><i class="fas fa-clock me-2"></i>Timeline</h6>
                        <div class="row">
                            ${request.created_at ? `
                            <div class="col-md-6 mb-1">
                                <label class="form-label fw-bold text-muted small mb-0">Requested At</label>
                                <p class="mb-0 small"><i class="fas fa-calendar me-1"></i>${new Date(request.created_at).toLocaleString('en-IN', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                            </div>
                            ` : ''}
                            ${request.requested_by_username ? `
                            <div class="col-md-6 mb-1">
                                <label class="form-label fw-bold text-muted small mb-0">Requested By</label>
                                <p class="mb-0 small">${request.requested_by_username}</p>
                            </div>
                            ` : ''}
                            ${request.reviewed_at ? `
                            <div class="col-md-6 mb-1">
                                <label class="form-label fw-bold text-muted small mb-0">Reviewed At</label>
                                <p class="mb-0 small"><i class="fas fa-check-circle me-1"></i>${new Date(request.reviewed_at).toLocaleString('en-IN', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                            </div>
                            ` : ''}
                            ${request.reviewed_by_username ? `
                            <div class="col-md-6 mb-1">
                                <label class="form-label fw-bold text-muted small mb-0">Reviewed By</label>
                                <p class="mb-0 small">${request.reviewed_by_username}</p>
                            </div>
                            ` : ''}
                            ${request.review_notes ? `
                            <div class="col-12 mb-1">
                                <label class="form-label fw-bold text-muted small mb-0">Review Notes</label>
                                <p class="mb-0 small">${request.review_notes}</p>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        }

        if (approveForm) {
            approveForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(approveForm);
                formData.append('action', 'approve');

                fetch('ajax/process_request.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        bootstrap.Modal.getInstance(approveModalEl).hide();
                        showToast('success', 'Request approved successfully!');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        showToast('error', data.message || 'Failed to approve request');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('error', 'An error occurred. Please try again.');
                });
            });
        }

        if (rejectForm) {
            rejectForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(rejectForm);
                formData.append('action', 'reject');

                fetch('ajax/process_request.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        bootstrap.Modal.getInstance(rejectModalEl).hide();
                        showToast('success', 'Request rejected successfully!');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        showToast('error', data.message || 'Failed to reject request');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('error', 'An error occurred. Please try again.');
                });
            });
        }

        function showToast(type, message) {
            const alertDiv = document.createElement('div');
            const alertType = type === 'success' ? 'success' : (type === 'error' ? 'danger' : 'info');
            alertDiv.className = `alert alert-${alertType} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
            alertDiv.style.zIndex = '9999';
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'error' ? 'exclamation-triangle' : 'info-circle')} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);

            setTimeout(() => {
                const alertInstance = bootstrap.Alert.getOrCreateInstance(alertDiv);
                alertInstance.close();
            }, 3000);
        }

        // Testimonial handlers
        const approveTestimonialModalEl = document.getElementById('approveTestimonialModal');
        const approveTestimonialForm = document.getElementById('approveTestimonialForm');
        const rejectTestimonialModalEl = document.getElementById('rejectTestimonialModal');
        const rejectTestimonialForm = document.getElementById('rejectTestimonialForm');
        const viewTestimonialModalEl = document.getElementById('viewTestimonialModal');

        function openApproveTestimonialModal(testimonialId) {
            if (document.getElementById('approveTestimonialId')) {
                document.getElementById('approveTestimonialId').value = testimonialId;
                const modal = bootstrap.Modal.getOrCreateInstance(approveTestimonialModalEl);
                modal.show();
            }
        }

        function rejectTestimonial(testimonialId) {
            if (document.getElementById('rejectTestimonialId')) {
                document.getElementById('rejectTestimonialId').value = testimonialId;
                const modal = bootstrap.Modal.getOrCreateInstance(rejectTestimonialModalEl);
                modal.show();
            }
        }

        function viewTestimonialDetails(testimonialId) {
            if (!viewTestimonialModalEl) return;
            const modal = bootstrap.Modal.getOrCreateInstance(viewTestimonialModalEl);
            const contentDiv = document.getElementById('testimonialDetailsContent');
            
            if (!contentDiv) return;
            
            contentDiv.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            modal.show();

            fetch(`ajax/get_testimonial_details.php?id=${testimonialId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.testimonial) {
                        const t = data.testimonial;
                        const stars = parseInt(t.star) || 5;
                        let starsHtml = '';
                        for (let i = 1; i <= 5; i++) {
                            starsHtml += `<i class="fas fa-star ${i <= stars ? 'text-warning' : 'text-muted'}"></i>`;
                        }
                        
                        const statusClass = t.status === 'pending' ? 'warning' : 
                                          t.status === 'approved' ? 'success' : 'danger';

                        contentDiv.innerHTML = `
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold text-muted small mb-1">Status</label>
                                    <div>
                                        <span class="badge bg-${statusClass} fs-6">${(t.status || 'pending').toUpperCase()}</span>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold text-muted small mb-1">Rating</label>
                                    <div>
                                        ${starsHtml}
                                        <span class="ms-2">${stars}/5</span>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold text-muted small mb-1">User Name</label>
                                    <p class="mb-0">${t.user_name || 'N/A'}</p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold text-muted small mb-1">Email</label>
                                    <p class="mb-0"><a href="mailto:${t.email}">${t.email || 'N/A'}</a></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold text-muted small mb-1">Shop Name</label>
                                    <p class="mb-0">${t.company_name || 'N/A'}</p>
                                </div>
                                ${t.owner_name ? `
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold text-muted small mb-1">Owner Name</label>
                                    <p class="mb-0">${t.owner_name}</p>
                                </div>
                                ` : ''}
                                ${t.tailor_name ? `
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold text-muted small mb-1">Tailor</label>
                                    <p class="mb-0">${t.tailor_name}</p>
                                </div>
                                ` : ''}
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold text-muted small mb-1">Submitted Date</label>
                                    <p class="mb-0">${new Date(t.created_at).toLocaleString()}</p>
                                </div>
                                ${t.updated_at && t.updated_at !== t.created_at ? `
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold text-muted small mb-1">Last Updated</label>
                                    <p class="mb-0">${new Date(t.updated_at).toLocaleString()}</p>
                                </div>
                                ` : ''}
                                <div class="col-12 mb-3">
                                    <label class="form-label fw-bold text-muted small mb-1">Comment</label>
                                    <div class="border rounded p-3 bg-light" style="min-height: 80px;">
                                        <p class="mb-0" style="white-space: pre-wrap;">${t.comment || 'N/A'}</p>
                                    </div>
                                </div>
                                <div class="col-12 mt-3 pt-3 border-top">
                                    <div class="d-flex gap-2 justify-content-end">
                                        ${t.status !== 'approved' ? `
                                        <button type="button" class="btn btn-success" onclick="approveFromViewModal(${t.id})">
                                            <i class="fas fa-check me-1"></i>Approve
                                        </button>
                                        ` : ''}
                                        ${t.status !== 'rejected' ? `
                                        <button type="button" class="btn btn-danger" onclick="rejectFromViewModal(${t.id})">
                                            <i class="fas fa-times me-1"></i>Reject
                                        </button>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        contentDiv.innerHTML = '<div class="alert alert-danger">Failed to load testimonial details.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    contentDiv.innerHTML = '<div class="alert alert-danger">An error occurred while loading testimonial details.</div>';
                });
        }

        function approveFromViewModal(testimonialId) {
            if (viewTestimonialModalEl) {
                bootstrap.Modal.getInstance(viewTestimonialModalEl).hide();
            }
            setTimeout(() => {
                openApproveTestimonialModal(testimonialId);
            }, 300);
        }

        function rejectFromViewModal(testimonialId) {
            if (viewTestimonialModalEl) {
                bootstrap.Modal.getInstance(viewTestimonialModalEl).hide();
            }
            setTimeout(() => {
                rejectTestimonial(testimonialId);
            }, 300);
        }

        if (approveTestimonialForm) {
            approveTestimonialForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(approveTestimonialForm);
                formData.append('action', 'approve');

                fetch('ajax/process_testimonial.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        bootstrap.Modal.getInstance(approveTestimonialModalEl).hide();
                        showToast('success', 'Testimonial approved successfully!');
                        // Reload while preserving the testimonials section
                        const url = new URL(window.location.href);
                        url.searchParams.set('section', 'testimonials');
                        setTimeout(() => {
                            window.location.href = url.toString();
                        }, 1000);
                    } else {
                        showToast('error', data.message || 'Failed to approve testimonial');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('error', 'An error occurred. Please try again.');
                });
            });
        }

        if (rejectTestimonialForm) {
            rejectTestimonialForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(rejectTestimonialForm);
                formData.append('action', 'reject');

                fetch('ajax/process_testimonial.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        bootstrap.Modal.getInstance(rejectTestimonialModalEl).hide();
                        showToast('success', 'Testimonial rejected successfully!');
                        // Reload while preserving the testimonials section
                        const url = new URL(window.location.href);
                        url.searchParams.set('section', 'testimonials');
                        setTimeout(() => {
                            window.location.href = url.toString();
                        }, 1000);
                    } else {
                        showToast('error', data.message || 'Failed to reject testimonial');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('error', 'An error occurred. Please try again.');
                });
            });
        }

        // Section toggle
        const navLinks = document.querySelectorAll('[data-section-toggle]');
        const sections = document.querySelectorAll('.content-section');
        const pageTitleEl = document.getElementById('pageTitle');
        const pageSubtitleEl = document.getElementById('pageSubtitle');

        // Function to switch sections
        function switchSection(target, skipUrlUpdate = false) {
            // Save current section's scroll position before switching
            const contentScroll = document.querySelector('.content-scroll');
            if (contentScroll && !skipUrlUpdate) {
                const currentSection = localStorage.getItem('selectedSection') || 'requests';
                if (currentSection && currentSection !== target) {
                    const currentScroll = contentScroll.scrollTop;
                    sessionStorage.setItem(`${currentSection}ScrollPosition`, currentScroll);
                    console.log(`Saved scroll position for ${currentSection}:`, currentScroll);
                    // Clear company details restore flags when switching sections normally
                    if (currentSection !== 'company' || target !== 'company') {
                        sessionStorage.removeItem('shouldRestoreScroll');
                        sessionStorage.removeItem('companyListScrollPosition');
                    }
                }
            }
            
            // Update URL without reload (unless skipping for initial load)
            if (!skipUrlUpdate) {
                const url = new URL(window.location.href);
                // Preserve other parameters like 'status'
                if (target === 'company') {
                    url.searchParams.set('section', 'company');
                } else if (target === 'requests') {
                    // Remove section parameter but keep others
                    url.searchParams.delete('section');
                } else if (target === 'contact') {
                    // Set contact section in URL
                    url.searchParams.set('section', 'contact');
                } else if (target === 'testimonials') {
                    // Set testimonials section in URL
                    url.searchParams.set('section', 'testimonials');
                } else {
                    // For any other section, set it in URL
                    url.searchParams.set('section', target);
                }
                // Get the clean URL string (ensure .php extension is preserved)
                let pathname = url.pathname;
                // Ensure index.php is in the pathname if it's missing
                if (!pathname.includes('index.php') && !pathname.endsWith('/')) {
                    // If pathname ends with just 'index', add .php
                    if (pathname.endsWith('index')) {
                        pathname = pathname + '.php';
                    } else if (pathname.endsWith('/siteadmin')) {
                        pathname = pathname + '/index.php';
                    }
                }
                const newUrl = pathname + (url.search ? url.search : '') + (url.hash ? url.hash : '');
                // Update URL without page reload
                window.history.pushState({section: target}, '', newUrl);
            }
            
            // Save to localStorage
            localStorage.setItem('selectedSection', target);
            
            // Update UI
            navLinks.forEach(l => l.classList.remove('active'));
            const activeLink = document.querySelector(`[data-section-toggle="${target}"]`);
            if (activeLink) {
                activeLink.classList.add('active');
            }
            
            sections.forEach(section => {
                section.style.display = section.id === `section-${target}` ? 'block' : 'none';
            });
            
            // Section-specific handling
            if (target === 'requests') {
                if (pageTitleEl) pageTitleEl.textContent = 'Email Requests';
                if (pageSubtitleEl) pageSubtitleEl.textContent = 'Review pending email change requests';
                // Restore scroll position for requests section
                setTimeout(function() {
                    restoreSectionScrollPosition('requests');
                }, 100);
            } else if (target === 'company') {
                if (pageTitleEl) pageTitleEl.textContent = 'Companies';
                if (pageSubtitleEl) pageSubtitleEl.textContent = 'Overview of all companies onboarded';
                // Always load companies when switching to company section
                setTimeout(function() {
                    loadCompanies();
                    // Apply URL parameters to filters after loading
                    const urlPlan = getURLParam('plan');
                    const urlSearch = getURLParam('search');
                    const planSelect = document.getElementById('planFilter');
                    const searchInput = document.getElementById('companySearch');
                    if (urlPlan && planSelect) {
                        planSelect.value = urlPlan;
                        activePlanFilter = urlPlan;
                    }
                    if (urlSearch && searchInput) {
                        searchInput.value = urlSearch;
                    }
                    // Restore scroll position for company section after rendering
                    setTimeout(function() {
                        restoreSectionScrollPosition('company');
                    }, 300);
                }, 100);
            } else if (target === 'contact') {
                if (pageTitleEl) pageTitleEl.textContent = 'Contact Messages';
                if (pageSubtitleEl) pageSubtitleEl.textContent = 'View all contact form submissions from users';
                // Load contact messages via AJAX
                loadContactMessages();
                // Restore scroll position for contact section
                setTimeout(function() {
                    restoreSectionScrollPosition('contact');
                }, 100);
            } else if (target === 'testimonials') {
                if (pageTitleEl) pageTitleEl.textContent = 'Testimonials';
                if (pageSubtitleEl) pageSubtitleEl.textContent = 'Review and manage customer testimonials';
                // Restore scroll position for testimonials section
                setTimeout(function() {
                    restoreSectionScrollPosition('testimonials');
                }, 100);
            } else {
                // Generic handling for any other section
                // Restore scroll position for the section
                setTimeout(function() {
                    restoreSectionScrollPosition(target);
                }, 100);
            }
        }
        
        // Generic function to restore scroll position for any section
        function restoreSectionScrollPosition(section) {
            const savedScrollPosition = sessionStorage.getItem(`${section}ScrollPosition`);
            if (savedScrollPosition !== null) {
                const contentScroll = document.querySelector('.content-scroll');
                if (contentScroll) {
                    const targetScroll = parseInt(savedScrollPosition, 10);
                    
                    const attemptScroll = function(attempts = 0) {
                        if (attempts > 20) {
                            return;
                        }
                        
                        requestAnimationFrame(function() {
                            const currentScroll = contentScroll.scrollTop;
                            const scrollHeight = contentScroll.scrollHeight;
                            
                            if (scrollHeight > targetScroll) {
                                contentScroll.scrollTop = targetScroll;
                                
                                if (Math.abs(contentScroll.scrollTop - targetScroll) > 5 && attempts < 20) {
                                    setTimeout(() => attemptScroll(attempts + 1), 100);
                                } else {
                                    console.log(`Scroll restored for ${section} to:`, contentScroll.scrollTop);
                                }
                            } else if (attempts < 20) {
                                setTimeout(() => attemptScroll(attempts + 1), 100);
                            }
                        });
                    };
                    
                    setTimeout(() => attemptScroll(), 100);
                }
            }
        }

        // Contact Messages listing
        function loadContactMessages() {
            fetch('ajax/get_contact_messages.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderContactMessages(data.messages, data.stats);
                    } else {
                        const section = document.getElementById('section-contact');
                        if (section) {
                            const cardBody = section.querySelector('.card-body');
                            if (cardBody) {
                                cardBody.innerHTML = `<div class="text-center text-danger py-5 w-100">${data.message || 'Failed to load contact messages'}</div>`;
                            }
                        }
                    }
                })
                .catch(() => {
                    const section = document.getElementById('section-contact');
                    if (section) {
                        const cardBody = section.querySelector('.card-body');
                        if (cardBody) {
                            cardBody.innerHTML = '<div class="text-center text-danger py-5 w-100">Error loading contact messages.</div>';
                        }
                    }
                });
        }
        
        let contactMessagesCache = <?php echo json_encode($contactMessages, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        let activeContactFilter = 'all';

        function formatContactShortDate(dateStr) {
            if (!dateStr) return '—';
            const d = new Date(dateStr);
            if (isNaN(d.getTime())) return dateStr;
            return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
        }

        function attachContactViewListeners(container) {
            if (!container) return;
            container.querySelectorAll('.view-contact-btn').forEach(btn => {
                const newBtn = btn.cloneNode(true);
                btn.parentNode.replaceChild(newBtn, btn);
                newBtn.addEventListener('click', function() {
                    try {
                        let messageDataStr = this.getAttribute('data-message');
                        messageDataStr = messageDataStr.replace(/&apos;/g, "'").replace(/&quot;/g, '"');
                        const messageData = JSON.parse(messageDataStr);
                        if (typeof viewContactMessage === 'function') {
                            viewContactMessage(messageData);
                        } else {
                            console.error('viewContactMessage function not found');
                        }
                    } catch (e) {
                        console.error('Error parsing message data:', e, this.getAttribute('data-message'));
                        alert('Error loading message details. Please try again.');
                    }
                });
            });
        }

        function applyContactFilterAndRender() {
            const searchQuery = (document.getElementById('contactSearchInput')?.value || '').trim().toLowerCase();
            const filtered = (contactMessagesCache || []).filter(msg => {
                const isLogged = Number(msg.user_logged) === 1;
                if (activeContactFilter === 'logged_in' && !isLogged) return false;
                if (activeContactFilter === 'guests' && isLogged) return false;
                if (!searchQuery) return true;
                const name = (msg.name || '').toLowerCase();
                const email = (msg.emailId || '').toLowerCase();
                const text = (msg.message || '').toLowerCase();
                return name.includes(searchQuery) || email.includes(searchQuery) || text.includes(searchQuery);
            });

            const visibleBadge = document.getElementById('contactVisibleCount');
            if (visibleBadge) visibleBadge.textContent = filtered.length;

            const cardBody = document.getElementById('contactMessagesBody');
            if (!cardBody) return;

            if (filtered.length === 0) {
                cardBody.innerHTML = `
                    <div class="er-empty-state">
                        <div class="mb-2 d-inline-block">
                            <svg width="210" height="126" viewBox="0 0 210 126" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M18 86C14 68 38 42 74 44C102 45 124 22 158 28C186 33 198 58 188 78C178 98 136 108 98 106C58 104 22 104 18 86Z" fill="#EFF2FC"/>
                                <circle cx="40" cy="30" r="2.2" fill="#1E1B4B"/>
                                <circle cx="163" cy="18" r="3.6" fill="#C7D2FE"/>
                                <rect x="57" y="25" width="90" height="62" rx="14" fill="#CBD5FE" stroke="#9AA8EC" stroke-width="1"/>
                                <circle cx="84" cy="56" r="4" fill="#FFFFFF"/>
                                <circle cx="102" cy="56" r="4" fill="#FFFFFF"/>
                                <circle cx="120" cy="56" r="4" fill="#FFFFFF"/>
                                <circle cx="132" cy="78" r="21" fill="#FFFFFF"/>
                                <path d="M124 73H140M124 79H135" stroke="#4F46E5" stroke-width="2.6" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <h4 class="er-empty-title">No contact messages found</h4>
                        <p class="er-empty-subtitle">${(contactMessagesCache || []).length ? 'Try adjusting your filter tab or search query.' : 'No contact form submissions have been received yet.'}</p>
                    </div>
                `;
                return;
            }

            let rowsHtml = '';
            filtered.forEach(msg => {
                const isLogged = Number(msg.user_logged) === 1;
                const initial = escapeHtml((msg.name || 'U').trim().charAt(0).toUpperCase() || 'U');
                const formattedDate = escapeHtml(formatContactShortDate(msg.created_date));
                rowsHtml += `
                    <tr style="transition: all 0.2s; border-bottom: 1px solid #f1f5f9;">
                        <td class="px-4 py-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="cm-avatar">${initial}</div>
                                <div>
                                    <div class="fw-bold" style="color: #0f172a;">${escapeHtml(msg.name)}</div>
                                    <div class="text-muted small">ID: #${escapeHtml(String(msg.id))}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <a href="mailto:${escapeHtml(msg.emailId)}" class="cm-email-link">
                                ${escapeHtml(msg.emailId)}
                            </a>
                        </td>
                        <td class="px-4 py-3">
                            <span class="cm-type-badge ${isLogged ? 'is-logged' : 'is-guest'}">
                                <i class="fas ${isLogged ? 'fa-user-check' : 'fa-user'}" style="font-size: 0.72rem;"></i>
                                ${isLogged ? 'Logged In' : 'Guest'}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-muted" style="font-size: 0.88rem; white-space: nowrap;">
                            ${formattedDate}
                        </td>
                        <td class="px-4 py-3 text-end">
                            <button class="btn btn-sm btn-primary cm-view-btn view-contact-btn" data-message='${JSON.stringify(msg).replace(/'/g, "&apos;").replace(/"/g, "&quot;")}'>
                                <i class="fas fa-eye"></i>
                                <span>View</span>
                            </button>
                        </td>
                    </tr>
                `;
            });

            cardBody.innerHTML = `
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.94rem;">
                        <thead class="bg-light text-muted" style="font-size: 0.8rem; letter-spacing: 0.5px;">
                            <tr>
                                <th class="border-0 px-4 py-3 fw-semibold text-uppercase">Sender</th>
                                <th class="border-0 px-4 py-3 fw-semibold text-uppercase">Email</th>
                                <th class="border-0 px-4 py-3 fw-semibold text-uppercase">User Type</th>
                                <th class="border-0 px-4 py-3 fw-semibold text-uppercase">Received</th>
                                <th class="border-0 px-4 py-3 fw-semibold text-uppercase text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rowsHtml}
                        </tbody>
                    </table>
                </div>
            `;

            attachContactViewListeners(cardBody);
        }

        document.querySelectorAll('[data-contact-filter]').forEach(tab => {
            tab.addEventListener('click', function() {
                activeContactFilter = this.getAttribute('data-contact-filter') || 'all';
                document.querySelectorAll('[data-contact-filter]').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                applyContactFilterAndRender();
            });
        });

        const contactSearchInput = document.getElementById('contactSearchInput');
        if (contactSearchInput) {
            contactSearchInput.addEventListener('input', applyContactFilterAndRender);
        }

        function renderContactMessages(messages, stats) {
            const section = document.getElementById('section-contact');
            if (!section) return;

            contactMessagesCache = Array.isArray(messages) ? messages : [];

            const totalEl = document.getElementById('contactStatTotal');
            const loggedEl = document.getElementById('contactStatLoggedIn');
            const guestsEl = document.getElementById('contactStatGuests');
            const tabAll = document.getElementById('contactTabCountAll');
            const tabLogged = document.getElementById('contactTabCountLoggedIn');
            const tabGuests = document.getElementById('contactTabCountGuests');

            if (totalEl) totalEl.textContent = stats.total;
            if (loggedEl) loggedEl.textContent = stats.logged_in;
            if (guestsEl) guestsEl.textContent = stats.guests;
            if (tabAll) tabAll.textContent = stats.total;
            if (tabLogged) tabLogged.textContent = stats.logged_in;
            if (tabGuests) tabGuests.textContent = stats.guests;

            applyContactFilterAndRender();
        }

        // Company listing
        let companiesData = [];
        function loadCompanies() {
            if (companiesData.length) {
                renderCompaniesCards();
                return;
            }
            const grid = document.getElementById('companiesGrid');
            grid.innerHTML = '<div class="text-center text-muted py-5 w-100">Loading companies...</div>';
            fetch('ajax/list_companies.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        companiesData = data.companies;
                        renderCompaniesCards();
                    } else {
                        grid.innerHTML = `<div class="text-center text-muted py-5 w-100">${data.message || 'Failed to load companies'}</div>`;
                    }
                })
                .catch(() => {
                    grid.innerHTML = '<div class="text-center text-danger py-5 w-100">Error loading companies.</div>';
                });
        }

        // Function to update URL parameters
        function updateURLParams(params) {
            const url = new URL(window.location.href);
            Object.keys(params).forEach(key => {
                if (params[key] === null || params[key] === '' || params[key] === undefined) {
                    url.searchParams.delete(key);
                } else {
                    url.searchParams.set(key, params[key]);
                }
            });
            const newUrl = url.pathname + (url.search ? url.search : '') + (url.hash ? url.hash : '');
            window.history.pushState({}, '', newUrl);
        }

        // Function to get URL parameter
        function getURLParam(name) {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get(name) || '';
        }

        // Initialize filters from URL
        let activePlanFilter = getURLParam('plan') || '';
        const planFilterSelect = document.getElementById('planFilter');
        if (planFilterSelect) {
            // Set initial value from URL
            if (activePlanFilter) {
                planFilterSelect.value = activePlanFilter;
            }
            planFilterSelect.addEventListener('change', function() {
                activePlanFilter = this.value || '';
                updateURLParams({ plan: activePlanFilter });
                renderCompaniesCards();
            });
        }

        // Initialize search from URL
        const companySearchEl = document.getElementById('companySearch');
        if (companySearchEl) {
            const initialSearch = getURLParam('search') || '';
            if (initialSearch) {
                companySearchEl.value = initialSearch;
            }
            // Debounce search input to avoid too many URL updates
            let searchTimeout;
            companySearchEl.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    const searchValue = companySearchEl.value || '';
                    updateURLParams({ search: searchValue });
                    renderCompaniesCards();
                }, 300);
            });
        }

        function renderCompaniesCards() {
            const search = (document.getElementById('companySearch')?.value || '').toLowerCase();
            const plan = activePlanFilter;
            const grid = document.getElementById('companiesGrid');
            const filtered = companiesData.filter(company => {
                const matchesPlan = !plan || company.subscription_plan === plan;
                const matchesSearch = !search ||
                    company.company_name.toLowerCase().includes(search) ||
                    (company.owner_name && company.owner_name.toLowerCase().includes(search)) ||
                    (company.city && company.city.toLowerCase().includes(search));
                return matchesPlan && matchesSearch;
            });
            if (!filtered.length) {
                grid.innerHTML = `
                    <div class="er-requests-card">
                        <div class="er-empty-state">
                            <div class="mb-2 d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 76px; height: 76px; background: #eef2ff; color: #4f46e5;">
                                <i class="fas fa-building fa-2x"></i>
                            </div>
                            <h4 class="er-empty-title">No companies found</h4>
                            <p class="er-empty-subtitle">Try adjusting your search query or subscription plan filter.</p>
                        </div>
                    </div>`;
                return;
            }

            function createInfoItem(icon, text) {
                return `<li><i class="${icon}"></i><span>${escapeHtml(text)}</span></li>`;
            }

            grid.innerHTML = filtered.map(company => {
                const planKey = escapeHtml(company.subscription_plan || 'free');
                const locationText = [company.city, company.state].filter(Boolean).join(', ') || 'Not provided';
                const expiryLine = company.subscription_plan === 'free'
                    ? createInfoItem('far fa-calendar-check', 'Duration: Lifetime')
                    : (company.subscription_expiry ? createInfoItem('far fa-calendar-check', `Expiry: ${company.subscription_expiry}`) : '');

                return `
                <div class="cs-company-card" onclick="viewCompanyDetails(${Number(company.id)})">
                    <div class="cs-company-primary">
                        <span class="cs-plan-badge plan-${planKey}">
                            <i class="fas fa-crown"></i>
                            <span>${escapeHtml(company.plan_label || 'Free Plan')}</span>
                        </span>
                        <h4 class="cs-company-title">${escapeHtml(company.company_name || 'Unnamed Shop')}</h4>
                        <p class="cs-company-owner">
                            <i class="fas fa-user"></i>
                            <span>Owner: ${escapeHtml(company.owner_name || 'N/A')}</span>
                        </p>
                        <div class="cs-company-meta">
                            <span class="cs-meta-item"><i class="fas fa-envelope"></i><span>${escapeHtml(company.business_email || '-')}</span></span>
                            <span class="cs-meta-item"><i class="fas fa-phone"></i><span>${escapeHtml(company.business_phone || '-')}</span></span>
                            ${company.remaining_days !== null && company.remaining_days !== undefined ? `<span class="cs-meta-item is-expiry"><i class="far fa-clock"></i><span>${Number(company.remaining_days)} days left</span></span>` : ''}
                        </div>
                    </div>

                    <div class="cs-company-kpis">
                        <div class="cs-kpi-item">
                            <div class="cs-kpi-icon is-customers"><i class="fas fa-users"></i></div>
                            <div class="cs-kpi-value">${Number(company.stats?.total_customers ?? 0)}</div>
                            <div class="cs-kpi-label">Customers</div>
                        </div>
                        <div class="cs-kpi-item">
                            <div class="cs-kpi-icon is-orders"><i class="fas fa-shopping-cart"></i></div>
                            <div class="cs-kpi-value">${Number(company.stats?.total_orders ?? 0)}</div>
                            <div class="cs-kpi-label">Orders</div>
                        </div>
                        <div class="cs-kpi-item">
                            <div class="cs-kpi-icon is-users"><i class="fas fa-user"></i></div>
                            <div class="cs-kpi-value">${Number(company.stats?.total_users ?? 0)}</div>
                            <div class="cs-kpi-label">Users</div>
                        </div>
                    </div>

                    <div class="cs-company-info">
                        <div class="cs-info-header">
                            <h6 class="cs-info-title"><i class="fas fa-info-circle"></i>Company Info</h6>
                            <div class="dropdown" onclick="event.stopPropagation();">
                                <button type="button" class="cs-info-menu-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Company options">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="viewCompanyDetails(${Number(company.id)})"><i class="fas fa-eye me-2 text-primary"></i>View Details</a></li>
                                </ul>
                            </div>
                        </div>
                        <ul class="cs-info-list">
                            ${createInfoItem('fas fa-map-marker-alt', locationText)}
                            ${createInfoItem('fas fa-globe', company.website || 'Not provided')}
                            ${createInfoItem('fas fa-file-invoice', `GST: ${company.tax_number || 'Not provided'}`)}
                            ${createInfoItem('far fa-calendar-alt', `Joined: ${company.created_at || '—'}`)}
                            ${expiryLine}
                            ${createInfoItem('fas fa-map-pin', company.business_address || 'No address provided')}
                        </ul>
                    </div>
                </div>`;
            }).join('');
            
            // Restore scroll position after rendering (only if coming from company details)
            const currentSection = localStorage.getItem('selectedSection') || 'requests';
            if (currentSection === 'company') {
                restoreScrollPosition();
            }
        }
        
        // Function to restore scroll position (only for company details page navigation)
        function restoreScrollPosition() {
            // Only restore if we're on company section AND coming from company details
            const currentSection = localStorage.getItem('selectedSection') || 'requests';
            if (currentSection !== 'company') {
                // Clear flags if not on company section
                sessionStorage.removeItem('shouldRestoreScroll');
                sessionStorage.removeItem('companyListScrollPosition');
                return;
            }
            
            const shouldRestore = sessionStorage.getItem('shouldRestoreScroll');
            if (shouldRestore === 'true') {
                const savedScrollPosition = sessionStorage.getItem('companyListScrollPosition');
                if (savedScrollPosition !== null) {
                    const contentScroll = document.querySelector('.content-scroll');
                    if (contentScroll) {
                        const targetScroll = parseInt(savedScrollPosition, 10);
                        
                        // Use multiple attempts to ensure scroll happens after DOM is ready
                        const attemptScroll = function(attempts = 0) {
                            if (attempts > 20) {
                                // Max 20 attempts (2 seconds), then clear
                                sessionStorage.removeItem('companyListScrollPosition');
                                sessionStorage.removeItem('shouldRestoreScroll');
                                return;
                            }
                            
                            requestAnimationFrame(function() {
                                const currentScroll = contentScroll.scrollTop;
                                const scrollHeight = contentScroll.scrollHeight;
                                const clientHeight = contentScroll.clientHeight;
                                
                                // Only scroll if content is tall enough
                                if (scrollHeight > targetScroll) {
                                    contentScroll.scrollTop = targetScroll;
                                    
                                    // Verify it worked, if not try again
                                    if (Math.abs(contentScroll.scrollTop - targetScroll) > 5 && attempts < 20) {
                                        setTimeout(() => attemptScroll(attempts + 1), 100);
                                    } else {
                                        console.log('Scroll restored to:', contentScroll.scrollTop);
                                        // Clear the flags after restoring
                                        sessionStorage.removeItem('companyListScrollPosition');
                                        sessionStorage.removeItem('shouldRestoreScroll');
                                    }
                                } else if (attempts < 20) {
                                    // Content not tall enough yet, try again
                                    setTimeout(() => attemptScroll(attempts + 1), 100);
                                } else {
                                    // Give up and clear
                                    sessionStorage.removeItem('companyListScrollPosition');
                                    sessionStorage.removeItem('shouldRestoreScroll');
                                }
                            });
                        };
                        
                        // Start attempting after a short delay
                        setTimeout(() => attemptScroll(), 200);
                    }
                }
            }
        }


        // Get initial section from URL or localStorage
        function getInitialSection() {
            const urlParams = new URLSearchParams(window.location.search);
            const urlSection = urlParams.get('section');
            // Prioritize URL parameter over localStorage, but validate it
            if (urlSection && (urlSection === 'company' || urlSection === 'requests' || urlSection === 'contact' || urlSection === 'testimonials')) {
                return urlSection;
            }
            // If no URL parameter or invalid, check localStorage
            const storedSection = localStorage.getItem('selectedSection');
            if (storedSection && (storedSection === 'company' || storedSection === 'requests' || storedSection === 'contact' || storedSection === 'testimonials')) {
                return storedSection;
            }
            return 'requests'; // default
        }

        // Add click handlers
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const target = this.getAttribute('data-section-toggle');
                switchSection(target);
            });
        });

        // Handle browser back/forward buttons
        window.addEventListener('popstate', function(e) {
            const urlParams = new URLSearchParams(window.location.search);
            const urlSection = urlParams.get('section');
            // Get section from URL or default to requests, validate it
            let section = 'requests';
            if (urlSection && (urlSection === 'company' || urlSection === 'requests' || urlSection === 'contact' || urlSection === 'testimonials')) {
                section = urlSection;
            } else {
                const storedSection = localStorage.getItem('selectedSection');
                if (storedSection && (storedSection === 'company' || storedSection === 'requests' || storedSection === 'contact' || storedSection === 'testimonials')) {
                    section = storedSection;
                }
            }
            switchSection(section, true);
        });

        // Function to navigate to company details
        function viewCompanyDetails(companyId) {
            // Save scroll position before navigating
            const contentScroll = document.querySelector('.content-scroll');
            if (contentScroll) {
                const scrollPosition = contentScroll.scrollTop;
                sessionStorage.setItem('companyListScrollPosition', scrollPosition);
                sessionStorage.setItem('shouldRestoreScroll', 'true');
                console.log('Saved scroll position:', scrollPosition);
            }
            window.location.href = `company_details.php?id=${companyId}`;
        }

        // Initialize section on page load
        (function() {
            // Wait for DOM to be fully ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initializePage);
            } else {
                initializePage();
            }
            
            function initializePage() {
                const initialSection = getInitialSection();
                console.log('Initializing with section:', initialSection);
                
                // Always call switchSection to ensure UI is in sync and data loads
                // Use skipUrlUpdate=true on initial load to avoid double URL update
                switchSection(initialSection, true);
                
                // If company section is active, ensure filters are applied after load
                if (initialSection === 'company') {
                    setTimeout(function() {
                        const urlPlan = getURLParam('plan');
                        const urlSearch = getURLParam('search');
                        if (urlPlan && planFilterSelect) {
                            planFilterSelect.value = urlPlan;
                            activePlanFilter = urlPlan;
                        }
                        if (urlSearch && companySearchEl) {
                            companySearchEl.value = urlSearch;
                        }
                        // Re-render with filters applied
                        if (companiesData.length > 0) {
                            renderCompaniesCards();
                        } else {
                            // If no data, still try to restore scroll
                            restoreScrollPosition();
                        }
                    }, 200);
                } else if (initialSection === 'contact') {
                    // Load contact messages if section is contact
                    setTimeout(function() {
                        loadContactMessages();
                        restoreSectionScrollPosition('contact');
                    }, 100);
                } else if (initialSection === 'testimonials') {
                    // Restore scroll position for testimonials section
                    setTimeout(function() {
                        restoreSectionScrollPosition('testimonials');
                    }, 100);
                }
            }
        })();

        // Contact Messages Functions
        let contactMessageModal;
        
        function viewContactMessage(msg) {
            if (!contactMessageModal) {
                contactMessageModal = new bootstrap.Modal(document.getElementById('contactMessageModal'));
            }
            
            const userTypeClass = msg.user_logged == 1 ? 'success' : 'secondary';
            const userTypeText = msg.user_logged == 1 ? 'Logged In' : 'Guest';
            
            // Populate details with theme matching structure
            const detailsHtml = `
                <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <label class="form-label fw-bold text-muted small mb-0">Message ID</label>
                        <p class="mb-0 small" id="detailMessageId">#${msg.id}</p>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label fw-bold text-muted small mb-0">User Type</label>
                        <p class="mb-0 small" id="detailUserType">
                            <span class="badge bg-${userTypeClass}">${userTypeText}</span>
                        </p>
                    </div>
                </div>
                <div class="col-12 mb-2">
                    <hr>
                    <h6 class="fw-bold text-muted mb-2"><i class="fas fa-user me-2"></i>Contact Information</h6>
                    <div class="row">
                        <div class="col-md-6 mb-1">
                            <label class="form-label fw-bold text-muted small mb-0">Full Name</label>
                            <p class="mb-0 small" id="detailName">${escapeHtml(msg.name)}</p>
                        </div>
                        <div class="col-md-6 mb-1">
                            <label class="form-label fw-bold text-muted small mb-0">Email</label>
                            <p class="mb-0 small" id="detailEmail">
                                <a href="mailto:${escapeHtml(msg.emailId)}">${escapeHtml(msg.emailId)}</a>
                            </p>
                        </div>
                        ${msg.user_id ? `
                        <div class="col-md-6 mb-1" id="detailUserIdContainer">
                            <label class="form-label fw-bold text-muted small mb-0">User ID</label>
                            <p class="mb-0 small" id="detailUserId">#${msg.user_id}</p>
                        </div>
                        ` : ''}
                    </div>
                </div>
                <div class="col-12 mb-2">
                    <hr>
                    <h6 class="fw-bold text-muted mb-2"><i class="fas fa-comment-dots me-2"></i>Message</h6>
                    <div class="row">
                        <div class="col-12 mb-1">
                            <p class="mb-0" style="white-space: pre-wrap; word-wrap: break-word;" id="detailMessage">${escapeHtml(msg.message)}</p>
                        </div>
                    </div>
                </div>
                <div class="col-12 mb-3">
                    <hr>
                    <h6 class="fw-bold text-muted mb-3"><i class="fas fa-clock me-2"></i>Timeline</h6>
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label class="form-label fw-bold text-muted small">Created At</label>
                            <p class="mb-0" id="detailDate">${formatDate(msg.created_date)}</p>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('contactMessageDetails').innerHTML = detailsHtml;
            
            // Set form values
            document.getElementById('contactResponseMessageId').value = msg.id;
            document.getElementById('contactResponseEmail').value = msg.emailId;
            document.getElementById('contactResponseName').value = msg.name;
            document.getElementById('contactResponseOriginalMessage').value = msg.message || '';
            document.getElementById('contactResponseMessage').value = '';
            document.getElementById('contactResponseSubject').value = '';
            
            // Reset alert
            const alert = document.getElementById('contactResponseAlert');
            if (alert) {
                alert.classList.add('d-none');
                alert.classList.remove('alert-success', 'alert-danger');
            }
            
            // Show modal
            contactMessageModal.show();
        }
        
        // Handle contact response form submission
        const contactResponseForm = document.getElementById('contactResponseForm');
        if (contactResponseForm) {
            contactResponseForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const sendBtn = document.getElementById('sendContactResponseBtn');
                const originalBtnText = sendBtn.innerHTML;
                const alert = document.getElementById('contactResponseAlert');
                
                // Disable button
                sendBtn.disabled = true;
                sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
                
                // Hide previous alerts
                if (alert) {
                    alert.classList.add('d-none');
                }
                
                fetch('ajax/send_contact_response.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (alert) {
                            alert.classList.remove('d-none', 'alert-danger');
                            alert.classList.add('alert-success');
                            alert.innerHTML = '<i class="fas fa-check-circle me-2"></i>' + (data.message || 'Response sent successfully!');
                        }
                        
                        // Clear form
                        document.getElementById('contactResponseMessage').value = '';
                        
                        // Optionally close modal after 2 seconds
                        setTimeout(() => {
                            if (contactMessageModal) {
                                contactMessageModal.hide();
                            }
                        }, 2000);
                    } else {
                        if (alert) {
                            alert.classList.remove('d-none', 'alert-success');
                            alert.classList.add('alert-danger');
                            alert.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>' + (data.message || 'Failed to send response. Please try again.');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (alert) {
                        alert.classList.remove('d-none', 'alert-success');
                        alert.classList.add('alert-danger');
                        alert.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>Network error. Please check your connection and try again.';
                    }
                })
                .finally(() => {
                    sendBtn.disabled = false;
                    sendBtn.innerHTML = originalBtnText;
                });
            });
        }
        
        function decodeHtml(html) {
            const txt = document.createElement("textarea");
            txt.innerHTML = html;
            return txt.value;
        }

        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            const decoded = decodeHtml(text);
            const div = document.createElement('div');
            div.textContent = decoded;
            return div.innerHTML;
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            const day = date.getDate();
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            const month = monthNames[date.getMonth()];
            const year = date.getFullYear();
            const hours = date.getHours();
            const minutes = date.getMinutes();
            const ampm = hours >= 12 ? 'pm' : 'am';
            const displayHours = hours % 12 || 12;
            const displayMinutes = minutes < 10 ? '0' + minutes : minutes;
            
            return `${day} ${month} ${year} at ${displayHours}:${displayMinutes} ${ampm}`;
        }

        // Mobile Sidebar Toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            function toggleSidebar() {
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
            }
            
            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', toggleSidebar);
            }
            if (overlay) {
                overlay.addEventListener('click', toggleSidebar);
            }
            
            // Close sidebar when clicking a nav link on mobile
            document.querySelectorAll('.sidebar .nav-link').forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 992) {
                        sidebar.classList.remove('show');
                        overlay.classList.remove('show');
                    }
                });
            });
        });
    </script>
</body>
</html>

