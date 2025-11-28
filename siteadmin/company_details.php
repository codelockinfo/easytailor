<?php
/**
 * Company Details Page
 * Shows detailed information about a company with tabs for different data types
 */

require_once '../config/config.php';

// Check if site admin is logged in
if (!isset($_SESSION['site_admin_logged_in']) || $_SESSION['site_admin_logged_in'] !== true) {
    header('Location: ../admin/login.php');
    exit;
}

require_once '../models/Company.php';

$companyModel = new Company();
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
    'free' => ['label' => 'Free Trial', 'color' => '#6c757d', 'badge' => 'secondary'],
    'basic' => ['label' => 'Basic Plan', 'color' => '#3b82f6', 'badge' => 'primary'],
    'premium' => ['label' => 'Premium Plan', 'color' => '#c026d3', 'badge' => 'pink'],
    'enterprise' => ['label' => 'Enterprise Plan', 'color' => '#fbbf24', 'badge' => 'warning']
];
$planKey = strtolower($company['subscription_plan'] ?? 'free');
$planMeta = $planStyles[$planKey] ?? $planStyles['free'];
$stats = $companyModel->getCompanyStats($companyId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($company['company_name']); ?> - Company Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        .app-shell {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, #4f46e5 0%, #7c3aed 100%);
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
        .top-header {
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 10px;
        }
        .company-header {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }
        @keyframes shine {
            0% {
                transform: translateX(-100%) translateY(-100%) rotate(45deg);
            }
            100% {
                transform: translateX(100%) translateY(100%) rotate(45deg);
            }
        }
        .company-header.plan-free {
            background: white !important;
            border: 2px solid #dee2e6 !important;
        }
        .company-header.plan-basic {
            background: #3b82f6 !important;
        }
        .company-header.plan-premium {
            background: linear-gradient(135deg, #c026d3 0%, #3b82f6 100%) !important;
            position: relative;
            overflow: hidden;
        }
        .company-header.plan-enterprise {
            background: #ffc107 !important;
            position: relative;
            overflow: hidden;
        }
        .company-header.plan-free h3,
        .company-header.plan-free .company-header-title,
        .company-header.plan-free .company-header-text,
        .company-header.plan-free .company-header-contact,
        .company-header.plan-free .company-header-stat,
        .company-header.plan-free .company-header-stat-label {
            color: #1e293b !important;
        }
        .company-header.plan-basic h3,
        .company-header.plan-basic .company-header-title,
        .company-header.plan-basic .company-header-text,
        .company-header.plan-basic .company-header-contact,
        .company-header.plan-basic .company-header-stat,
        .company-header.plan-basic .company-header-stat-label {
            color: white;
        }
        .company-header.plan-premium h3,
        .company-header.plan-premium .company-header-title,
        .company-header.plan-premium .company-header-text,
        .company-header.plan-premium .company-header-contact,
        .company-header.plan-premium .company-header-stat,
        .company-header.plan-premium .company-header-stat-label {
            color: white;
        }
        .company-header.plan-enterprise h3,
        .company-header.plan-enterprise .company-header-title,
        .company-header.plan-enterprise .company-header-text,
        .company-header.plan-enterprise .company-header-contact,
        .company-header.plan-enterprise .company-header-stat,
        .company-header.plan-enterprise .company-header-stat-label {
            color: white;
        }
        
        /* Company header links and text colors */
        .company-header-link {
            color: inherit;
        }
        .company-header.plan-free .company-header-link,
        .company-header.plan-free .company-header-contact-text,
        .company-header.plan-free .company-info-text,
        .company-header.plan-free .company-info-title,
        .company-header.plan-free .company-info-icon {
            color: #1e293b !important;
        }
        .company-header.plan-basic .company-header-link,
        .company-header.plan-basic .company-header-contact-text,
        .company-header.plan-basic .company-info-text,
        .company-header.plan-basic .company-info-title,
        .company-header.plan-basic .company-info-icon,
        .company-header.plan-premium .company-header-link,
        .company-header.plan-premium .company-header-contact-text,
        .company-header.plan-premium .company-info-text,
        .company-header.plan-premium .company-info-title,
        .company-header.plan-premium .company-info-icon,
        .company-header.plan-enterprise .company-header-link,
        .company-header.plan-enterprise .company-header-contact-text,
        .company-header.plan-enterprise .company-info-text,
        .company-header.plan-enterprise .company-info-title,
        .company-header.plan-enterprise .company-info-icon {
            color: white !important;
        }
        
        .company-header .badge {
            background: rgba(30, 41, 59, 0.1) !important;
            color: #1e293b !important;
        }
        .company-header.plan-free .badge {
            background: rgba(30, 41, 59, 0.1) !important;
            color: #1e293b !important;
        }
        .company-header.plan-basic .badge,
        .company-header.plan-premium .badge,
        .company-header.plan-enterprise .badge {
            background: rgba(255, 255, 255, 0.2) !important;
            color: white !important;
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
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .tab-button {
            padding: 0.75rem 1.5rem;
            border: none;
            background: transparent;
            color: #6c757d;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            margin-right: 0.5rem;
        }
        .tab-button:hover {
            background: #f8f9fa;
            color: #4f46e5;
        }
        .tab-button.active {
            background: #4f46e5;
            color: white;
        }
        .data-table-container {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .table {
            margin-bottom: 0;
        }
        .table thead th {
            background: #4f46e5;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: white;
            padding: 1rem;
        }
        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
        }
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        .badge-custom {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.85rem;
        }
        .back-btn {
            margin-bottom: 10px;
        }
        .back-btn a {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: white;
            color: #4f46e5;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.2s;
        }
        .back-btn a:hover {
            background: #4f46e5;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
        }
        .badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.85rem;
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
                $logoPath = '../uploads/logos/footer-logo.png';
                if (file_exists($logoPath)): ?>
                    <img src="<?php echo $logoPath; ?>" alt="Tailor Logo" class="brand-logo">
                <?php else: ?>
                    <i class="fas fa-crown"></i>
                <?php endif; ?>
            </div>
            <a href="index.php?section=requests" class="nav-link">
                <i class="fas fa-envelope-open-text"></i>
                Email Requests
            </a>
            <a href="index.php?section=company" class="nav-link active">
                <i class="fas fa-building"></i>
                Company Snapshot
            </a>
            <div class="mt-auto">
                <a href="../admin/logout.php" class="nav-link" onclick="return confirm('Are you sure you want to logout?');">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </aside>

        <main class="main-content">
            <div class="top-header card">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h2 class="mb-1">Company Details</h2>
                        <p class="text-muted mb-0">View detailed information and data</p>
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
                <!-- Back Button and Subscription Update -->
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2" style="margin-bottom: 10px;">
                    <div class="back-btn">
                        <a href="index.php?section=company">
                            <i class="fas fa-arrow-left"></i>
                            Back to Companies
                        </a>
                    </div>
                    <div>
                        <button type="button" class="btn btn-primary" onclick="openSubscriptionModal()" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-crown"></i>
                            Update Subscription
                        </button>
                    </div>
                </div>

                <!-- Company Header -->
                <div class="company-header plan-<?php echo $planKey; ?>">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3" style="position: relative; z-index: 1;">
                        <div class="flex-grow-1">
                            <span class="badge badge-custom bg-<?php echo $planMeta['badge']; ?> mb-2">
                                <i class="fas fa-crown me-1"></i><?php echo $planMeta['label']; ?>
                            </span>
                            <h3 class="mb-2 company-header-title"><?php echo htmlspecialchars($company['company_name']); ?></h3>
                            <p class="mb-2 company-header-text"><strong>Owner:</strong> <?php echo htmlspecialchars($company['owner_name'] ?? 'N/A'); ?></p>
                            <div class="d-flex gap-3 flex-wrap company-header-contact">
                                <?php if (!empty($company['business_email'])): ?>
                                    <span><i class="fas fa-envelope me-2"></i><a href="mailto:<?php echo htmlspecialchars($company['business_email']); ?>" class="company-header-link" style="text-decoration: none;"><?php echo htmlspecialchars($company['business_email']); ?></a></span>
                                <?php endif; ?>
                                <?php if (!empty($company['business_phone'])): ?>
                                    <span><i class="fas fa-phone me-2"></i><a href="tel:<?php echo htmlspecialchars($company['business_phone']); ?>" class="company-header-link" style="text-decoration: none;"><?php echo htmlspecialchars($company['business_phone']); ?></a></span>
                                <?php endif; ?>
                                <?php if ($company['city']): ?>
                                    <span class="company-header-contact-text"><i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($company['city']); ?>, <?php echo htmlspecialchars($company['state'] ?? ''); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="position: relative; z-index: 1; display: flex; flex-direction: column; gap: 1.5rem; align-items: flex-end;">
                            <div class="text-end">
                                <div class="d-flex gap-4" style="justify-content: center;">
                                    <div style="text-align: center;">
                                        <div class="h4 mb-0 company-header-stat"><?php echo $stats['total_customers'] ?? 0; ?></div>
                                        <small class="company-header-stat-label">Customers</small>
                                    </div>
                                    <div style="text-align: center;">
                                        <div class="h4 mb-0 company-header-stat"><?php echo $stats['total_orders'] ?? 0; ?></div>
                                        <small class="company-header-stat-label">Orders</small>
                                    </div>
                                    <div style="text-align: center;">
                                        <div class="h4 mb-0 company-header-stat"><?php echo $stats['total_users'] ?? 0; ?></div>
                                        <small class="company-header-stat-label">Users</small>
                                    </div>
                                </div>
                            </div>
                            <div class="company-info-inline" style="text-align: left; position: relative; z-index: 1;">
                                <h5 class="mb-2 company-info-title" style="font-size: 0.95rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
                                    <i class="fas fa-info-circle company-info-icon"></i>Company Info
                                </h5>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem 1.5rem; max-width: 500px;">
                                    <div class="company-info-item" style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.85rem; line-height: 1.4;">
                                        <i class="fas fa-map-marker-alt company-info-icon" style="margin-top: 0.2rem; flex-shrink: 0; font-size: 0.8rem;"></i>
                                        <span class="company-info-text"><?php echo htmlspecialchars($company['city'] ?? '-'); ?>, <?php echo htmlspecialchars($company['state'] ?? '-'); ?></span>
                                    </div>
                                    <div class="company-info-item" style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.85rem; line-height: 1.4;">
                                        <i class="fas fa-globe company-info-icon" style="margin-top: 0.2rem; flex-shrink: 0; font-size: 0.8rem;"></i>
                                        <span class="company-info-text" style="word-break: break-word;">
                                            <?php if (!empty($company['website']) && $company['website'] !== 'Not provided'): ?>
                                                <a href="<?php echo htmlspecialchars($company['website']); ?>" target="_blank" rel="noopener noreferrer" class="company-header-link" style="text-decoration: underline;"><?php echo htmlspecialchars($company['website']); ?></a>
                                            <?php else: ?>
                                                Not provided
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="company-info-item" style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.85rem; line-height: 1.4;">
                                        <i class="fas fa-file-invoice company-info-icon" style="margin-top: 0.2rem; flex-shrink: 0; font-size: 0.8rem;"></i>
                                        <span class="company-info-text">GST: <?php echo !empty($company['tax_number']) ? htmlspecialchars($company['tax_number']) : 'Not provided'; ?></span>
                                    </div>
                                    <div class="company-info-item" style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.85rem; line-height: 1.4;">
                                        <i class="fas fa-calendar company-info-icon" style="margin-top: 0.2rem; flex-shrink: 0; font-size: 0.8rem;"></i>
                                        <span class="company-info-text">Joined: <?php echo !empty($company['created_at']) ? date('M d, Y', strtotime($company['created_at'])) : '—'; ?></span>
                                    </div>
                                    <?php if (!empty($company['subscription_expiry'])): ?>
                                    <div class="company-info-item" style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.85rem; line-height: 1.4;">
                                        <i class="fas fa-flag-checkered company-info-icon" style="margin-top: 0.2rem; flex-shrink: 0; font-size: 0.8rem;"></i>
                                        <span class="company-info-text">Expiry: <?php echo date('M d, Y', strtotime($company['subscription_expiry'])); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="company-info-item" style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.85rem; grid-column: span 2; line-height: 1.4;">
                                        <i class="fas fa-map-pin company-info-icon" style="margin-top: 0.2rem; flex-shrink: 0; font-size: 0.8rem;"></i>
                                        <span class="company-info-text" style="word-break: break-word;"><?php echo !empty($company['business_address']) ? htmlspecialchars($company['business_address']) : 'No address provided'; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Navigation -->
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
                    <button class="tab-button" data-tab="invoices">
                        <i class="fas fa-file-invoice me-2"></i>Invoices
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

                <!-- Tab Content -->
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

        // Tab switching
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentTab = this.getAttribute('data-tab');
                loadTabData(currentTab);
            });
        });

        // Load tab data
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
                            contentDiv.innerHTML = '<div class="text-center py-5 text-muted">No data available</div>';
                        }
                    } else {
                        contentDiv.innerHTML = `<div class="text-center py-5 text-muted">${data.message || 'No data available'}</div>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    contentDiv.innerHTML = '<div class="text-center py-5 text-danger">Error loading data. Please check console for details.</div>';
                });
        }

        // Render table based on tab type
        function renderTable(type, data) {
            if (!data || data.length === 0) {
                return '<div class="text-center py-5 text-muted">No data available</div>';
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
                    
                    // Special handling for reports tab
                    if (type === 'reports') {
                        if (field === 'generated_at') {
                            if (value && value !== '-') {
                                const date = new Date(value);
                                value = date.toLocaleDateString('en-IN', { year: 'numeric', month: 'short', day: 'numeric' });
                            }
                        } else if (field === 'actions') {
                            value = `<button class="btn btn-sm btn-primary" onclick="viewCompanyReports(${companyId})" title="View Reports">
                                        <i class="fas fa-eye"></i> View
                                     </button>`;
                        }
                    }
                    // Special handling for orders tab
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
                            value = `<button class="btn btn-sm btn-info" onclick="viewOrderDetails(${row.id})" title="View Details">
                                        <i class="fas fa-eye"></i>
                                     </button>`;
                        }
                    }
                    // Special handling for reports tab
                    else if (type === 'reports') {
                        if (field === 'actions') {
                            value = `<button class="btn btn-sm btn-primary" onclick="viewCompanyReports(${companyId})" title="View Reports">
                                        <i class="fas fa-eye"></i> View
                                     </button>`;
                        } else if (field === 'generated_at') {
                            if (value && value !== '-') {
                                const date = new Date(value);
                                value = date.toLocaleDateString('en-IN', { year: 'numeric', month: 'short', day: 'numeric' });
                            }
                        }
                    }
                    // Special handling for customers tab
                    else if (type === 'customers') {
                        if (field === 'actions') {
                            const customerId = row.id || row.customer_id || 0;
                            value = `<button class="btn btn-sm btn-info" onclick="viewCustomerDetails(${customerId})" title="View Details">
                                        <i class="fas fa-eye"></i> View
                                    </button>`;
                        } else if (field === 'name') {
                            value = `<strong>${row.name || '-'}</strong>`;
                        } else if (field === 'customer_code') {
                            value = row.customer_code ? `<span class="badge bg-secondary">${row.customer_code}</span>` : '-';
                        } else if (field === 'email') {
                            value = row.email ? `<a href="mailto:${row.email}">${row.email}</a>` : '-';
                        }
                    }
                    // Special handling for invoices tab
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
                    // Special handling for expenses tab
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
                                <button class="btn btn-sm btn-info" onclick="viewExpenseDetails(${row.id})" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                            `;
                        }
                    }
                    // Special handling for contact tab
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
                                <button class="btn btn-sm btn-info" onclick="viewContactDetails(${row.id})" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                            `;
                        }
                    }
                    // Special handling for users tab
                    else if (type === 'users') {
                        if (field === 'name') {
                            value = `<strong>${row.name || '-'}</strong>`;
                        } else if (field === 'email') {
                            value = row.email ? `<a href="mailto:${row.email}">${row.email}</a>` : '-';
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
                                <button class="btn btn-sm btn-info" onclick="viewUserDetails(${row.id})" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                            `;
                        }
                    } else {
                        // Default handling for other tabs
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

        // Expense actions
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

        // Contact actions
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

        // User actions
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

        // Customer actions
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

        // Invoice actions
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
            
            // Build customer address
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

        // View order details
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
                                <!-- Order Summary -->
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
                                    <!-- Customer Information -->
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
                                    
                                    <!-- Timeline -->
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
                                
                                <!-- Order Information -->
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

        // View company reports
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
                                <!-- Key Metrics -->
                                <div class="row mb-4">
                                    <div class="col-md-3 mb-3">
                                        <div class="card text-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
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
                                
                                <!-- Detailed Statistics -->
                                <div class="row">
                                    <!-- Orders Statistics -->
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
                                    
                                    <!-- Invoices Statistics -->
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
                                
                                <!-- Additional Statistics -->
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

        // Load initial tab
        loadTabData(currentTab);
    </script>

    <!-- Order Details Modal -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="orderDetailsModalLabel">Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="orderDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Company Reports Modal -->
    <div class="modal fade" id="companyReportsModal" tabindex="-1" aria-labelledby="companyReportsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="companyReportsModalLabel">Company Reports</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="companyReportsContent">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact Details Modal -->
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

    <!-- Expense Details Modal -->
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

    <!-- User Details Modal -->
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

    <!-- Invoice Details Modal -->
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

    <!-- Customer Details Modal -->
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

    <!-- Subscription Update Modal -->
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
                                            <i class="fas fa-gift text-secondary"></i> Free Trial
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
                        
                        <!-- Duration Selection (only show for paid plans) -->
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

    <!-- Confirmation Modal for Subscription Update -->
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

    <!-- Success/Error Message Modal -->
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
        /* Free Trial - White background with light grey border */
        .subscription-free {
            background: white !important;
            border: 2px solid #dee2e6 !important;
        }
        /* Basic Plan - Bright blue background */
        .subscription-basic {
            background: #3b82f6 !important;
            border: 2px solid #3b82f6 !important;
        }
        
        /* Premium Plan - Purple-blue gradient */
        .subscription-premium {
            background: linear-gradient(135deg, #c026d3 0%, #3b82f6 100%) !important;
            border: 2px solid transparent !important;
        }
        
        /* Enterprise Plan - Yellow background */
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
        
        /* Current plan styling */
        .subscription-option.current-plan {
            opacity: 0.9;
        }
        
        .text-purple {
            color: #c026d3 !important;
        }
        
        /* Duration option styling */
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
        // Subscription modal functions
        function openSubscriptionModal() {
            const modal = new bootstrap.Modal(document.getElementById('subscriptionModal'));
            modal.show();
            
            // Reset selection
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

        // Handle subscription option selection
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.subscription-option').forEach(option => {
                option.addEventListener('click', function() {
                    // Remove previous selection
                    document.querySelectorAll('.subscription-option').forEach(opt => {
                        opt.classList.remove('selected');
                    });
                    
                    // Add selection to clicked option
                    this.classList.add('selected');
                    const plan = this.getAttribute('data-plan');
                    document.getElementById('selectedPlan').value = plan;
                    
                    // Show/hide duration selection based on plan
                    const durationSelection = document.getElementById('durationSelection');
                    if (plan === 'free') {
                        durationSelection.style.display = 'none';
                        document.getElementById('selectedDuration').value = '';
                        document.getElementById('updateSubscriptionBtn').disabled = false;
                    } else {
                        durationSelection.style.display = 'block';
                        // Reset duration selection
                        document.querySelectorAll('.duration-option').forEach(opt => {
                            opt.classList.remove('selected');
                        });
                        document.getElementById('selectedDuration').value = '';
                        document.getElementById('updateSubscriptionBtn').disabled = true;
                    }
                });
            });
            
            // Handle duration option selection
            document.querySelectorAll('.duration-option').forEach(option => {
                option.addEventListener('click', function() {
                    // Remove previous selection
                    document.querySelectorAll('.duration-option').forEach(opt => {
                        opt.classList.remove('selected');
                    });
                    
                    // Add selection to clicked option
                    this.classList.add('selected');
                    const duration = this.getAttribute('data-duration');
                    document.getElementById('selectedDuration').value = duration;
                    document.getElementById('updateSubscriptionBtn').disabled = false;
                });
            });
        });

        // Show message modal
        function showMessageModal(type, message, onClose = null) {
            const modal = new bootstrap.Modal(document.getElementById('messageModal'));
            const header = document.getElementById('messageModalHeader');
            const label = document.getElementById('messageModalLabel');
            const text = document.getElementById('messageModalText');
            const okBtn = document.getElementById('messageModalOkBtn');
            
            // Set modal style based on type
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
            
            // Handle close event
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
            
            // For paid plans, duration is required
            if (selectedPlan !== 'free' && !selectedDuration) {
                showMessageModal('warning', 'Please select a duration (Monthly or Yearly)');
                return;
            }
            
            // Show confirmation modal
            const planLabels = {
                'free': 'Free Trial',
                'basic': 'Basic Plan',
                'premium': 'Premium Plan',
                'enterprise': 'Enterprise Plan'
            };
            
            document.getElementById('confirmCurrentPlan').textContent = planLabels[currentPlan] || currentPlan.toUpperCase();
            document.getElementById('confirmNewPlan').textContent = planLabels[selectedPlan] || selectedPlan.toUpperCase();
            
            // Show duration if it's a paid plan
            const durationRow = document.getElementById('confirmDurationRow');
            if (selectedPlan !== 'free') {
                durationRow.style.display = 'flex';
                const durationText = selectedDuration === 'yearly' ? 'Yearly (12 Months)' : 'Monthly (1 Month)';
                document.getElementById('confirmDuration').textContent = durationText;
            } else {
                durationRow.style.display = 'none';
            }
            
            // Store selected plan and duration for confirmation
            window.pendingSubscriptionUpdate = {
                plan: selectedPlan,
                duration: selectedDuration || 'monthly'
            };
            
            // Close subscription modal first
            const subscriptionModal = bootstrap.Modal.getInstance(document.getElementById('subscriptionModal'));
            if (subscriptionModal) {
                subscriptionModal.hide();
            }
            
            // Wait for subscription modal to close, then show confirmation modal
            const subscriptionModalEl = document.getElementById('subscriptionModal');
            subscriptionModalEl.addEventListener('hidden.bs.modal', function handler() {
                subscriptionModalEl.removeEventListener('hidden.bs.modal', handler);
                const confirmModal = new bootstrap.Modal(document.getElementById('confirmSubscriptionModal'));
                confirmModal.show();
            }, { once: true });
        }

        function cancelConfirmation() {
            // Close confirmation modal
            const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmSubscriptionModal'));
            if (confirmModal) {
                confirmModal.hide();
            }
            
            // Wait for confirmation modal to close, then reopen subscription modal
            const confirmModalEl = document.getElementById('confirmSubscriptionModal');
            confirmModalEl.addEventListener('hidden.bs.modal', function handler() {
                confirmModalEl.removeEventListener('hidden.bs.modal', handler);
                // Restore the selected plan if it was set
                const pendingUpdate = window.pendingSubscriptionUpdate;
                if (pendingUpdate) {
                    const selectedPlan = typeof pendingUpdate === 'object' ? pendingUpdate.plan : pendingUpdate;
                    const selectedDuration = typeof pendingUpdate === 'object' ? pendingUpdate.duration : '';
                    
                    // Reopen subscription modal
                    const subscriptionModal = new bootstrap.Modal(document.getElementById('subscriptionModal'));
                    subscriptionModal.show();
                    
                    // Restore the selection
                    setTimeout(() => {
                        document.querySelectorAll('.subscription-option').forEach(option => {
                            if (option.getAttribute('data-plan') === selectedPlan) {
                                option.classList.add('selected');
                                document.getElementById('selectedPlan').value = selectedPlan;
                                
                                // Show/hide duration selection
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
            
            // Close confirmation modal
            const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmSubscriptionModal'));
            if (confirmModal) {
                confirmModal.hide();
            }
            
            // Close subscription selection modal
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
    </script>
</body>
</html>