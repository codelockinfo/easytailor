<?php
/**
 * Header Include
 * Tailoring Management System
 */

require_once __DIR__ . '/../../config/config.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect(APP_URL . '/login.php');
}

$current_user = [
    'id' => get_user_id(),
    'name' => get_user_name() ?? 'User',
    'role' => get_user_role(),
    'username' => $_SESSION['username'] ?? ''
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Dashboard'; ?> - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --sidebar-width: 280px;
            --header-height: 70px;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            z-index: 1000;
            transition: all 0.3s ease;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 0.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-brand {
            color: white;
            text-decoration: none;
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .sidebar-nav {
            padding: 1rem 0;
        }
        
        .nav-item {
            margin: 0.25rem 1rem;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1rem;
            border-radius: 10px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .nav-link:hover, .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }
        
        .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        
        .nav-dropdown {
            margin-left: 1rem;
        }
        
        .nav-dropdown .nav-link {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }
        
        .top-header {
            background: white;
            height: var(--header-height);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            position: sticky;
            top: 0;
            z-index: 999;
        }
        
        .content-area {
            padding: 2rem;
        }
        
        /* Cards */
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            border: none;
            padding: 1.5rem;
        }
        
        /* Stats Cards */
        .stat-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .stat-card .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        
        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin: 0.5rem 0;
        }
        
        .stat-card .stat-label {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        /* Buttons */
        .btn {
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        /* Tables */
        .table {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table thead th {
            background-color: var(--primary-color);
            color: white;
            border: none;
            font-weight: 600;
        }
        
        /* Forms */
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            background-color: #fff;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            background-color: #fff;
        }
        
        /* Premium Dropdown Styling */
        .form-select {
            font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            font-weight: 500;
            font-size: 0.95rem;
            letter-spacing: 0.01em;
            line-height: 1.5;
            
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.875rem 1.25rem;
            padding-right: 3rem;
            
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%234a5568'%3e%3cpath fill-rule='evenodd' d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z' clip-rule='evenodd'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.875rem center;
            background-size: 18px 18px;
            
            cursor: pointer;
            position: relative;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .form-select:hover {
            border-color: #cbd5e0;
            background: linear-gradient(145deg, #ffffff, #f7fafc);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .form-select:focus {
            border-color: #667eea;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1), 0 4px 20px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
            outline: none;
        }
        
        .form-select:active {
            transform: translateY(-1px);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        /* Premium Option Styling */
        .form-select option {
            font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
            font-weight: 500;
            font-size: 0.95rem;
            letter-spacing: 0.01em;
            line-height: 1.6;
            
            padding: 0.875rem 1.25rem;
            margin: 0.125rem 0.25rem;
            background-color: #ffffff;
            color: #2d3748;
            border-radius: 8px;
            border: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        
        .form-select option:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.25);
            font-weight: 600;
        }
        
        .form-select option:checked,
        .form-select option:focus {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        /* Elegant Dropdown Container */
        .dropdown-container {
            position: relative;
        }
        
        .dropdown-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(145deg, transparent, rgba(102, 126, 234, 0.02));
            border-radius: 12px;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
            z-index: 0;
        }
        
        .dropdown-container:hover::before {
            opacity: 1;
        }
        
        .dropdown-container .form-select {
            position: relative;
            z-index: 1;
        }
        
        /* Loading State */
        .form-select.loading {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23667eea' stroke-width='2'%3e%3cpath d='M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83'/%3e%3c/svg%3e");
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        /* Enhanced Typography */
        .form-select {
            color: #2d3748;
        }
        
        .form-select::placeholder {
            color: #a0aec0;
            font-weight: 400;
        }
        
        /* Disabled State */
        .form-select:disabled {
            background: linear-gradient(145deg, #f7fafc, #edf2f7);
            border-color: #e2e8f0;
            color: #a0aec0;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .form-select:disabled:hover {
            transform: none;
            box-shadow: none;
        }
        
        /* Dark Mode Support */
        @media (prefers-color-scheme: dark) {
            .form-select {
                background: linear-gradient(145deg, #2d3748, #4a5568);
                border-color: #4a5568;
                color: #e2e8f0;
                background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%23a0aec0'%3e%3cpath fill-rule='evenodd' d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z' clip-rule='evenodd'/%3e%3c/svg%3e");
            }
            
            .form-select:hover {
                background: linear-gradient(145deg, #4a5568, #2d3748);
                border-color: #667eea;
            }
            
            .form-select option {
                background-color: #2d3748;
                color: #e2e8f0;
            }
            
            .form-select option:hover {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #ffffff;
            }
        }
        
        /* Custom Scrollbar */
        .form-select::-webkit-scrollbar {
            width: 6px;
        }
        
        .form-select::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        
        .form-select::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 10px;
        }
        
        .form-select::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #5a67d8, #6b46c1);
        }
        
        /* Badges */
        .badge {
            padding: 0.5rem 0.75rem;
            border-radius: 20px;
            font-weight: 500;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .top-header {
                padding: 0 1rem;
            }
            
            .content-area {
                padding: 1rem;
            }
        }
        
        /* Loading Spinner */
        .loading {
            display: none;
            text-align: center;
            padding: 2rem;
        }
        
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
        
        /* Custom Scrollbar */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }
        
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-brand d-flex align-items-center">
                <?php
                // Check for brand logo
                $brandLogo = '../uploads/logos/brand-logo.png';
                if (file_exists($brandLogo)):
                ?>
                    <img src="<?php echo $brandLogo; ?>" alt="<?php echo APP_NAME; ?>" style="height: 100px; width: auto; display: block; margin: 0 auto;">
                <?php else: ?>
                    <i class="fas fa-cut me-2"></i>
                    <?php echo APP_NAME; ?>
                <?php endif; ?>
            </a>
        </div>
        
        <div class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="customers.php" class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['customers.php', 'customer-details.php']) ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        Customers
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="orders.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>">
                        <i class="fas fa-clipboard-list"></i>
                        Orders
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="invoices.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'invoices.php' ? 'active' : ''; ?>">
                        <i class="fas fa-file-invoice"></i>
                        Invoices
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="measurements.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'measurements.php' ? 'active' : ''; ?>">
                        <i class="fas fa-ruler"></i>
                        Measurements
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="cloth-types.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'cloth-types.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tshirt"></i>
                        Cloth Types
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="expenses.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'expenses.php' ? 'active' : ''; ?>">
                        <i class="fas fa-receipt"></i>
                        Expenses
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i>
                        Reports
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="contacts.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'contacts.php' ? 'active' : ''; ?>">
                        <i class="fas fa-address-book"></i>
                        Contacts
                    </a>
                </li>
                
                <?php if (has_role('admin')): ?>
                <li class="nav-item">
                    <a href="company-settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'company-settings.php' ? 'active' : ''; ?>">
                        <i class="fas fa-building"></i>
                        Company Settings
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="users.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user-cog"></i>
                        User Management
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="subscriptions.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'subscriptions.php' ? 'active' : ''; ?>">
                        <i class="fas fa-crown"></i>
                        Subscriptions
                    </a>
                </li>
                
                <?php endif; ?>
            </ul>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="d-flex align-items-center">
                <button class="btn btn-link d-md-none me-3" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h4 class="mb-0"><?php echo $page_title ?? 'Dashboard'; ?></h4>
            </div>
            
            <div class="d-flex align-items-center">
                <!-- Language Switcher -->
                <div class="dropdown me-3">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-globe me-1"></i>
                        <span id="currentLanguage">EN</span>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" data-lang="en">🇺🇸 English</a></li>
                        <li><a class="dropdown-item" href="#" data-lang="es">🇪🇸 Español</a></li>
                        <li><a class="dropdown-item" href="#" data-lang="fr">🇫🇷 Français</a></li>
                        <li><a class="dropdown-item" href="#" data-lang="hi">🇮🇳 हिन्दी</a></li>
                    </ul>
                </div>
                
                <!-- User Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i>
                        <?php echo htmlspecialchars($current_user['name']); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header"><?php echo htmlspecialchars($current_user['name']); ?></h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="change-password.php"><i class="fas fa-key me-2"></i>Change Password</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>
        
        <!-- Content Area -->
        <main class="content-area">
        
        <!-- Premium Dropdown JavaScript -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add Google Fonts for better typography
            const fontLink = document.createElement('link');
            fontLink.href = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap';
            fontLink.rel = 'stylesheet';
            document.head.appendChild(fontLink);
            
            // Enhance all form-select elements
            const selects = document.querySelectorAll('.form-select');
            
            selects.forEach(select => {
                // Add sophisticated hover effects
                select.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.1)';
                });
                
                select.addEventListener('mouseleave', function() {
                    if (document.activeElement !== this) {
                        this.style.transform = '';
                        this.style.boxShadow = '0 1px 3px rgba(0, 0, 0, 0.05)';
                    }
                });
                
                // Add focus enhancement
                select.addEventListener('focus', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 0 0 3px rgba(102, 126, 234, 0.1), 0 4px 20px rgba(0, 0, 0, 0.08)';
                    this.parentElement.classList.add('focused');
                });
                
                select.addEventListener('blur', function() {
                    this.style.transform = '';
                    this.style.boxShadow = '0 1px 3px rgba(0, 0, 0, 0.05)';
                    this.parentElement.classList.remove('focused');
                });
                
                // Add elegant click animation
                select.addEventListener('mousedown', function() {
                    this.style.transform = 'translateY(-1px)';
                    this.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.1)';
                });
                
                select.addEventListener('mouseup', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 0 0 3px rgba(102, 126, 234, 0.1), 0 4px 20px rgba(0, 0, 0, 0.08)';
                });
                
                // Add selection feedback
                select.addEventListener('change', function() {
                    // Elegant selection animation
                    this.style.transform = 'scale(0.98)';
                    setTimeout(() => {
                        this.style.transform = 'translateY(-2px)';
                    }, 100);
                    
                    // Add ripple effect
                    const ripple = document.createElement('div');
                    ripple.style.cssText = `
                        position: absolute;
                        top: 50%;
                        left: 50%;
                        width: 20px;
                        height: 20px;
                        background: rgba(102, 126, 234, 0.3);
                        border-radius: 50%;
                        transform: translate(-50%, -50%) scale(0);
                        animation: ripple 0.6s ease-out;
                        pointer-events: none;
                        z-index: 10;
                    `;
                    
                    this.style.position = 'relative';
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });
            
            // Add ripple animation keyframes
            const style = document.createElement('style');
            style.textContent = `
                @keyframes ripple {
                    0% {
                        transform: translate(-50%, -50%) scale(0);
                        opacity: 1;
                    }
                    100% {
                        transform: translate(-50%, -50%) scale(4);
                        opacity: 0;
                    }
                }
                
                /* Enhanced option styling */
                .form-select option {
                    font-family: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif !important;
                    font-weight: 500 !important;
                    font-size: 0.95rem !important;
                    letter-spacing: 0.01em !important;
                    line-height: 1.6 !important;
                    padding: 0.875rem 1.25rem !important;
                    margin: 0.125rem 0.25rem !important;
                    border-radius: 8px !important;
                    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
                }
                
                .form-select option:hover {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
                    color: white !important;
                    transform: translateX(4px) !important;
                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.25) !important;
                    font-weight: 600 !important;
                }
                
                .form-select option:checked,
                .form-select option:focus {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
                    color: white !important;
                    font-weight: 600 !important;
                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3) !important;
                }
                
                /* Enhanced dropdown container */
                .dropdown-container {
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                }
                
                .dropdown-container:hover {
                    transform: translateY(-1px);
                }
                
                /* Focused state enhancement */
                .dropdown-container.focused {
                    transform: translateY(-1px);
                }
                
                .dropdown-container.focused .form-select {
                    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1), 0 4px 20px rgba(0, 0, 0, 0.08) !important;
                }
            `;
            document.head.appendChild(style);
        });
        </script>

