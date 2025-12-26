<?php
/**
 * Site Admin - Email Change Requests Approval
 * Tailoring Management System
 */

require_once '../config/config.php';

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
    if (!empty($highlightCompany['subscription_expiry'])) {
        $expiry = strtotime($highlightCompany['subscription_expiry']);
        $today = strtotime(date('Y-m-d'));
        $daysRemaining = max(0, floor(($expiry - $today) / (60 * 60 * 24)));
    }
}

$planStyles = [
    'free' => ['label' => 'Free Trial', 'color' => '#6c757d', 'badge' => 'secondary'],
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
    <title>Email Change Requests - Site Admin</title>
    <!-- Favicon - Primary ICO format for Google Search -->
    <link rel="icon" type="image/x-icon" href="../favicon.ico" sizes="16x16 32x32 48x48">
    <!-- Favicon - PNG fallback -->
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon(2).png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon(2).png">
    <!-- Apple Touch Icon -->
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon(2).png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }
        .app-shell {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }
        .sidebar {
            width: 250px;
            /* background: linear-gradient(180deg, #4f46e5 0%, #7c3aed 100%); */
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 1.5rem;
            display: flex;
            flex-direction: column;
            box-shadow: 10px 0 25px rgba(79, 70, 229, 0.2);
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        .brand {
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .brand-logo {
            height: 100px;
            width: auto;
            object-fit: contain;
        }
        .header-logo {
            height: 100px;
            width: auto;
            object-fit: contain;
            margin-right: 1rem;
        }
        .top-header {
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .top-header .header-left {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        .mb-4 {
    margin-bottom: 1rem !important;
}
        .sticky-controls {
            position: sticky;
            top: 0;
            z-index: 5;
        }
        .sticky-controls.card {
            border: none !important;
            border-top-left-radius: 0 !important;
            border-top-right-radius: 0 !important;
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
        .sticky-controls #companySearch,
        .sticky-controls #planFilter {
            height: 45px !important;
            font-size: 1rem;
        }
        .sticky-controls .input-group-text {
            height: 45px !important;
            padding: 0 1rem;
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
            padding: 2rem 2.5rem;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }
        .content-scroll {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 0.5rem;
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
            color: #667eea;
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
            border-left: 4px solid #667eea;
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
        @media (max-width: 992px) {
            .company-row {
                grid-template-columns: 1fr;
            }
        }
            gap: 0.75rem;
            border-bottom: 1px dashed rgba(148,163,184,0.4);
            color: #475569;
        }
        .company-info-list li:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand">
                <?php 
                $logoPath = '../uploads/logos/footer-logo.png';
                if (file_exists($logoPath)): ?>
                    <img src="<?php echo $logoPath; ?>" alt="Tailor Logo" class="brand-logo">
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
            <div class="mt-auto">
                <a href="../admin/logout.php" class="nav-link" onclick="return confirm('Are you sure you want to logout?');">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </aside>

        <main class="main-content">
        <div class="top-header card mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="header-left">
                    <div>
                        <h2 class="mb-1" id="pageTitle"><?php 
                            echo $current_section === 'company' ? 'Companies' : 
                                ($current_section === 'contact' ? 'Contact Messages' : 
                                ($current_section === 'testimonials' ? 'Testimonials' : 'Email Requests')); 
                        ?></h2>
                        <p class="text-muted mb-0" id="pageSubtitle"><?php 
                            echo $current_section === 'company' ? 'Overview of all companies onboarded' : 
                                ($current_section === 'contact' ? 'View all contact form submissions from users' : 
                                ($current_section === 'testimonials' ? 'Review and manage customer testimonials' : 'Review pending email change requests')); 
                        ?></p>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <!-- <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-globe me-1"></i>EN
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#">English</a></li>
                            <li><a class="dropdown-item" href="#">Hindi</a></li>
                        </ul>
                    </div> -->
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>Admin
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text text-muted small">Site Administrator</span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../admin/logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="content-scroll">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : ($messageType === 'error' ? 'danger' : 'info'); ?> alert-dismissible fade show">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'error' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <section id="section-requests" class="content-section" style="display: <?php echo $current_section === 'requests' ? 'block' : 'none'; ?>;">
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Requests</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center" style="border-top: 3px solid #ffc107;">
                    <div class="stat-number text-warning"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center" style="border-top: 3px solid #28a745;">
                    <div class="stat-number text-success"><?php echo $stats['approved']; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center" style="border-top: 3px solid #dc3545;">
                    <div class="stat-number text-danger"><?php echo $stats['rejected']; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>
        </div>
        <div class="card mb-4 sticky-controls">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="btn-group" role="group">
                            <a href="?status=pending" class="btn btn-<?php echo $status_filter === 'pending' ? 'warning' : 'outline-warning'; ?>">
                                <i class="fas fa-clock me-2"></i>Pending (<?php echo $stats['pending']; ?>)
                            </a>
                            <a href="?status=approved" class="btn btn-<?php echo $status_filter === 'approved' ? 'success' : 'outline-success'; ?>">
                                <i class="fas fa-check me-2"></i>Approved (<?php echo $stats['approved']; ?>)
                            </a>
                            <a href="?status=rejected" class="btn btn-<?php echo $status_filter === 'rejected' ? 'danger' : 'outline-danger'; ?>">
                                <i class="fas fa-times me-2"></i>Rejected (<?php echo $stats['rejected']; ?>)
                            </a>
                            <a href="?status=all" class="btn btn-<?php echo $status_filter === 'all' ? 'primary' : 'outline-primary'; ?>">
                                <i class="fas fa-list me-2"></i>All (<?php echo $stats['total']; ?>)
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Email Change Requests 
                    <?php if ($status_filter && $status_filter !== 'all'): ?>
                        <span class="badge bg-secondary"><?php echo ucfirst($status_filter); ?></span>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($requests)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No requests found</h5>
                        <p class="text-muted">
                            <?php if ($status_filter && $status_filter !== 'all'): ?>
                                No <?php echo $status_filter; ?> requests at the moment.
                            <?php else: ?>
                                No email change requests have been submitted yet.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr style="background: #667eea; color: white;">
                                    <th>Company Name</th>
                                    <th>Owner</th>
                                    <th>Old Email</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td>
                                            <i class="fas fa-building me-2"></i>
                                            <strong><?php echo htmlspecialchars($request['company_name'] ?? 'N/A'); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($request['owner_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <a href="mailto:<?php echo htmlspecialchars($request['current_email']); ?>">
                                                <?php echo htmlspecialchars($request['current_email']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                $statusClass = 'secondary';
                                                if ($request['status'] === 'pending') {
                                                    $statusClass = 'warning';
                                                } elseif ($request['status'] === 'approved') {
                                                    $statusClass = 'success';
                                                } elseif ($request['status'] === 'rejected') {
                                                    $statusClass = 'danger';
                                                }
                                                echo $statusClass;
                                            ?>">
                                                <?php echo strtoupper($request['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" 
                                                    class="btn btn-sm btn-info" 
                                                    onclick="viewEmailRequestDetails(<?php echo $request['id']; ?>)"
                                                    title="View Details">
                                                <i class="fas fa-eye me-1"></i>View
                                            </button>
                                            <?php if ($request['status'] === 'pending'): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-success" 
                                                        onclick="openApproveModal(<?php echo $request['id']; ?>)"
                                                        title="Approve">
                                                    <i class="fas fa-check me-1"></i>Approve
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-sm btn-danger" 
                                                        onclick="rejectRequest(<?php echo $request['id']; ?>)"
                                                        title="Reject">
                                                    <i class="fas fa-times me-1"></i>Reject
                                                </button>
                                            <?php endif; ?>
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
            <div class="card mb-4 sticky-controls">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-9">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="companySearch" placeholder="Search company by name, owner, or city...">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="planFilter" data-plan-filter>
                                <option value="">All Plans</option>
                                <option value="free">Free</option>
                                <option value="basic">Basic</option>
                                <option value="premium">Premium</option>
                                <option value="enterprise">Enterprise</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div id="companiesGrid" class="company-grid">
                <div class="text-center text-muted py-5 w-100">Loading companies...</div>
            </div>
        </section>

        <section id="section-contact" class="content-section" style="display: <?php echo $current_section === 'contact' ? 'block' : 'none'; ?>;">
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stat-card text-center">
                        <div class="stat-number"><?php echo $contactStats['total']; ?></div>
                        <div class="stat-label">Total Messages</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card text-center" style="border-top: 3px solid #28a745;">
                        <div class="stat-number text-success"><?php echo $contactStats['logged_in']; ?></div>
                        <div class="stat-label">From Logged In Users</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card text-center" style="border-top: 3px solid #6c757d;">
                        <div class="stat-number text-secondary"><?php echo $contactStats['guests']; ?></div>
                        <div class="stat-label">From Guests</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-comments me-2"></i>
                        Contact Messages
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($contactMessages)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No contact messages found</h5>
                            <p class="text-muted">No contact form submissions have been received yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr style="background: #667eea; color: white;">
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contactMessages as $msg): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($msg['id']); ?></td>
                                            <td><strong><?php echo htmlspecialchars($msg['name']); ?></strong></td>
                                            <td>
                                                <a href="mailto:<?php echo htmlspecialchars($msg['emailId']); ?>">
                                                    <?php echo htmlspecialchars($msg['emailId']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary view-contact-btn" data-message='<?php echo htmlspecialchars(json_encode($msg), ENT_QUOTES, 'UTF-8'); ?>'>
                                                    <i class="fas fa-eye me-1"></i>View
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
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-number"><?php echo $testimonialStats['total']; ?></div>
                    <div class="stat-label">Total Testimonials</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center" style="border-top: 3px solid #ffc107;">
                    <div class="stat-number text-warning"><?php echo $testimonialStats['pending']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center" style="border-top: 3px solid #28a745;">
                    <div class="stat-number text-success"><?php echo $testimonialStats['approved']; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center" style="border-top: 3px solid #dc3545;">
                    <div class="stat-number text-danger"><?php echo $testimonialStats['rejected']; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>
        </div>
        <div class="card mb-4 sticky-controls">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="btn-group" role="group">
                            <a href="?section=testimonials&status=pending" class="btn btn-<?php echo $testimonial_status_filter === 'pending' ? 'warning' : 'outline-warning'; ?>">
                                <i class="fas fa-clock me-2"></i>Pending (<?php echo $testimonialStats['pending']; ?>)
                            </a>
                            <a href="?section=testimonials&status=approved" class="btn btn-<?php echo $testimonial_status_filter === 'approved' ? 'success' : 'outline-success'; ?>">
                                <i class="fas fa-check me-2"></i>Approved (<?php echo $testimonialStats['approved']; ?>)
                            </a>
                            <a href="?section=testimonials&status=rejected" class="btn btn-<?php echo $testimonial_status_filter === 'rejected' ? 'danger' : 'outline-danger'; ?>">
                                <i class="fas fa-times me-2"></i>Rejected (<?php echo $testimonialStats['rejected']; ?>)
                            </a>
                            <a href="?section=testimonials&status=all" class="btn btn-<?php echo $testimonial_status_filter === 'all' ? 'primary' : 'outline-primary'; ?>">
                                <i class="fas fa-list me-2"></i>All (<?php echo $testimonialStats['total']; ?>)
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-star me-2"></i>
                    Testimonials 
                    <?php if ($testimonial_status_filter && $testimonial_status_filter !== 'all'): ?>
                        <span class="badge bg-secondary"><?php echo ucfirst($testimonial_status_filter); ?></span>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($testimonials)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-star fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No testimonials found</h5>
                        <p class="text-muted">
                            <?php if ($testimonial_status_filter && $testimonial_status_filter !== 'all'): ?>
                                No <?php echo $testimonial_status_filter; ?> testimonials at the moment.
                            <?php else: ?>
                                No testimonials have been submitted yet.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr style="background: #667eea; color: white;">
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Shop</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($testimonials as $testimonial): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($testimonial['id']); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($testimonial['user_name'] ?? 'N/A'); ?></strong>
                                        </td>
                                        <td>
                                            <a href="mailto:<?php echo htmlspecialchars($testimonial['email']); ?>">
                                                <?php echo htmlspecialchars($testimonial['email']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($testimonial['company_name'] ?? 'N/A'); ?>
                                            <?php if (!empty($testimonial['owner_name'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($testimonial['owner_name']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" 
                                                    class="btn btn-sm btn-info" 
                                                    onclick="viewTestimonialDetails(<?php echo $testimonial['id']; ?>)"
                                                    title="View Details">
                                                <i class="fas fa-eye me-1"></i>View
                                            </button>
                                            <?php 
                                            // Show status badge in actions column
                                            // Show all statuses in "All" view, or specific status in filtered views
                                            $showStatusBadge = false;
                                            if ($testimonial_status_filter === 'all' || $testimonial_status_filter === '') {
                                                // In "All" view, show all status badges
                                                $showStatusBadge = true;
                                            } elseif ($testimonial_status_filter === $testimonial['status']) {
                                                // In filtered views, show badge if it matches the filter
                                                $showStatusBadge = true;
                                            }
                                            
                                            if ($showStatusBadge): 
                                                $statusBadgeClass = '';
                                                $statusIcon = '';
                                                if ($testimonial['status'] === 'approved') {
                                                    $statusBadgeClass = 'btn-success';
                                                    $statusIcon = 'fa-check';
                                                } elseif ($testimonial['status'] === 'rejected') {
                                                    $statusBadgeClass = 'btn-danger';
                                                    $statusIcon = 'fa-times';
                                                } elseif ($testimonial['status'] === 'pending') {
                                                    $statusBadgeClass = 'btn-warning';
                                                    $statusIcon = 'fa-clock';
                                                }
                                                if ($statusBadgeClass):
                                            ?>
                                                <span class="btn btn-sm <?php echo $statusBadgeClass; ?> ms-1" style="pointer-events: none; cursor: default;">
                                                    <i class="fas <?php echo $statusIcon; ?> me-1"></i><?php echo strtoupper($testimonial['status']); ?>
                                                </span>
                                            <?php 
                                                endif;
                                            endif; 
                                            ?>
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

    <!-- Approve Modal -->
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

    <!-- Approve Testimonial Modal -->
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

    <!-- Reject Testimonial Modal -->
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

    <!-- View Testimonial Modal -->
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

    <!-- Reject Modal -->
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

    <!-- Contact Message Detail Modal -->
    <div class="modal fade" id="contactMessageModal" tabindex="-1" aria-labelledby="contactMessageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="contactMessageModalLabel"><i class="fas fa-comments me-2"></i>Contact Message Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="contactMessageDetails">
                        <!-- Details will be populated by JavaScript -->
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

    <!-- View Email Request Details Modal -->
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
            const viewModal = new bootstrap.Modal(document.getElementById('viewEmailRequestModal'));

            modalTitle.innerHTML = `<i class="fas fa-envelope me-2"></i>Email Request Details`;
            modalBody.innerHTML = `<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-3 text-muted">Loading request details...</p></div>`;
            modalFooter.innerHTML = '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Close</button>';
            viewModal.show();

            fetch(`ajax/get_email_request_details.php?request_id=${requestId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        modalBody.innerHTML = renderEmailRequestDetails(data.request);
                        // Update footer with action buttons if pending
                        if (data.request.status === 'pending') {
                            modalFooter.innerHTML = `
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-1"></i>Close
                                </button>
                                <button type="button" class="btn btn-success" onclick="approveFromViewModal(${data.request.id})">
                                    <i class="fas fa-check me-1"></i>Approve
                                </button>
                                <button type="button" class="btn btn-danger" onclick="rejectFromViewModal(${data.request.id})">
                                    <i class="fas fa-times me-1"></i>Reject
                                </button>
                            `;
                        } else {
                            modalFooter.innerHTML = '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Close</button>';
                        }
                    } else {
                        modalBody.innerHTML = `<div class="alert alert-danger">${data.message || 'Failed to load request details'}</div>`;
                        modalFooter.innerHTML = '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Close</button>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalBody.innerHTML = '<div class="alert alert-danger">Error loading request details. Please try again.</div>';
                    modalFooter.innerHTML = '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Close</button>';
                });
        }

        function approveFromViewModal(requestId) {
            bootstrap.Modal.getInstance(document.getElementById('viewEmailRequestModal')).hide();
            setTimeout(() => {
                openApproveModal(requestId);
            }, 300);
        }

        function rejectFromViewModal(requestId) {
            bootstrap.Modal.getInstance(document.getElementById('viewEmailRequestModal')).hide();
            setTimeout(() => {
                rejectRequest(requestId);
            }, 300);
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
        
        function renderContactMessages(messages, stats) {
            const section = document.getElementById('section-contact');
            if (!section) return;
            
            // Update stats cards
            const statCards = section.querySelectorAll('.stat-card .stat-number');
            if (statCards.length >= 3) {
                statCards[0].textContent = stats.total;
                statCards[1].textContent = stats.logged_in;
                statCards[2].textContent = stats.guests;
            }
            
            // Get or create card body
            let cardBody = section.querySelector('.card-body');
            if (!cardBody) {
                // If card body doesn't exist, create the card structure
                const card = section.querySelector('.card') || document.createElement('div');
                card.className = 'card';
                if (!card.querySelector('.card-header')) {
                    card.innerHTML = `
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-comments me-2"></i>
                                Contact Messages
                            </h5>
                        </div>
                        <div class="card-body"></div>
                    `;
                }
                cardBody = card.querySelector('.card-body');
            }
            
            if (messages.length === 0) {
                cardBody.innerHTML = `
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No contact messages found</h5>
                        <p class="text-muted">No contact form submissions have been received yet.</p>
                    </div>
                `;
            } else {
                // Build table rows HTML
                let rowsHtml = '';
                messages.forEach(msg => {
                    rowsHtml += `
                        <tr>
                            <td>${msg.id}</td>
                            <td><strong>${escapeHtml(msg.name)}</strong></td>
                            <td>
                                <a href="mailto:${escapeHtml(msg.emailId)}">
                                    ${escapeHtml(msg.emailId)}
                                </a>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary view-contact-btn" data-message='${JSON.stringify(msg).replace(/'/g, "&apos;").replace(/"/g, "&quot;")}'>
                                    <i class="fas fa-eye me-1"></i>View
                                </button>
                            </td>
                        </tr>
                    `;
                });
                
                // Replace entire card body content with table
                cardBody.innerHTML = `
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr style="background: #667eea; color: white;">
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rowsHtml}
                            </tbody>
                        </table>
                    </div>
                `;
                
                // Attach event listeners to view buttons after rendering
                setTimeout(() => {
                    cardBody.querySelectorAll('.view-contact-btn').forEach(btn => {
                        // Remove any existing listeners to prevent duplicates
                        const newBtn = btn.cloneNode(true);
                        btn.parentNode.replaceChild(newBtn, btn);
                        
                        newBtn.addEventListener('click', function() {
                            try {
                                let messageDataStr = this.getAttribute('data-message');
                                // Decode HTML entities
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
                }, 100);
            }
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
                grid.innerHTML = '<div class="text-center text-muted py-5 w-100">No companies found</div>';
                return;
            }
            
            function createInfoItem(icon, text) {
                const isLong = text && text.length > 30;
                const fullWidthClass = isLong ? 'full-width' : '';
                return `<li class="${fullWidthClass}"><i class="${icon}"></i><span>${text}</span></li>`;
            }
            grid.innerHTML = filtered.map(company => `
                <div class="company-row">
                    <div class="company-main-card plan-${company.subscription_plan}" style="cursor: pointer;" onclick="viewCompanyDetails(${company.id})">
                        <div class="company-details">
                            <span class="badge"><i class="fas fa-crown me-1"></i>${company.plan_label}</span>
                            <h4>${company.company_name}</h4>
                            <p class="owner-name">Owner: ${company.owner_name || 'N/A'}</p>
                            <div class="company-contact">
                                <span><i class="fas fa-envelope"></i>${company.business_email || '-'}</span>
                                <span><i class="fas fa-phone"></i>${company.business_phone || '-'}</span>
                                ${company.remaining_days !== null ? `<span><i class="fas fa-clock"></i>${company.remaining_days} days left</span>` : ''}
                            </div>
                        </div>
                        <div class="company-stats">
                            <div>
                                <span class="stat-number">${company.stats.total_customers ?? 0}</span>
                                <small>Customers</small>
                            </div>
                            <div>
                                <span class="stat-number">${company.stats.total_orders ?? 0}</span>
                                <small>Orders</small>
                            </div>
                            <div>
                                <span class="stat-number">${company.stats.total_users ?? 0}</span>
                                <small>Users</small>
                            </div>
                        </div>
                    </div>
                    <div class="company-info-card">
                        <h5><i class="fas fa-info-circle"></i>Company Info</h5>
                        <ul class="company-info-list">
                            ${createInfoItem('fas fa-map-marker-alt', `${company.city || '-'}, ${company.state || '-'}`)}
                            ${createInfoItem('fas fa-globe', company.website || 'Not provided')}
                            ${createInfoItem('fas fa-file-invoice', `GST: ${company.tax_number || 'Not provided'}`)}
                            ${createInfoItem('fas fa-calendar', `Joined: ${company.created_at || '—'}`)}
                            ${company.subscription_expiry ? createInfoItem('fas fa-flag-checkered', `Expiry: ${company.subscription_expiry}`) : ''}
                            ${createInfoItem('fas fa-map-pin', company.business_address || 'No address provided')}
                        </ul>
                    </div>
                </div>
            `).join('');
            
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
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
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
    </script>
</body>
</html>

