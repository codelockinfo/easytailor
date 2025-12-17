<?php
/**
 * Navigation Component
 * Reusable navigation for all user-facing pages
 */

// Determine base path for links based on current directory
$isAdmin = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false);
$basePath = $isAdmin ? '../' : '';

// Get current page to highlight active link
$currentPage = basename($_SERVER['PHP_SELF']);

// Favicon links for use in <head> section (multiple formats for maximum compatibility)
$faviconLinks = '
    <!-- Favicon - Primary ICO format for Google Search -->
    <link rel="icon" type="image/x-icon" href="' . $basePath . 'favicon.ico">
    <!-- Favicon - PNG fallback -->
    <link rel="icon" type="image/png" href="/assets/images/favicon(2).png">
    <!-- Apple Touch Icon -->
    <link rel="apple-touch-icon" href="/assets/images/favicon(2).png">
    <!-- Google AdSense -->
    <meta name="google-adsense-account" content="ca-pub-2821959013351742">
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2821959013351742" crossorigin="anonymous"></script>';

// Google AdSense script for footer/body section
$googleAdSenseScript = '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2821959013351742" crossorigin="anonymous"></script>';
?>
<!-- Sticky Header -->
<nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?php echo $basePath; ?>./">
            <img src="<?php echo $basePath; ?>uploads/logos/main-logo.png" alt="TailorPro" class="navbar-logo me-2" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
            <i class="fas fa-cut text-primary me-2" style="display: none;"></i>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link active" href="<?php echo ($currentPage === 'index.php') ? '#features' : $basePath . './#features'; ?>">Features</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="<?php echo ($currentPage === 'index.php') ? '#benefits' : $basePath . './#benefits'; ?>">Benefits</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" style="display: none;" href="<?php echo ($currentPage === 'index.php') ? '#pricing' : $basePath . './#pricing'; ?>">Pricing</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="<?php echo ($currentPage === 'index.php') ? '#contact' : $basePath . './#contact'; ?>">Contact</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="<?php echo ($currentPage === 'index.php') ? '#blog' : $basePath . './#blog'; ?>">Blog</a>
                </li>
                <li class="nav-item ms-3">
                    <a href="<?php echo $basePath; ?>admin/login.php" class="btn btn-sm login-header-btn">Login</a>
                </li>
                <li class="nav-item ms-2">
                    <a href="<?php echo $basePath; ?>admin/register.php" class="btn btn-sm register-header-btn">Register</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

