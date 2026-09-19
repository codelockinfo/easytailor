<?php

$isAdmin = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false);
$basePath = $isAdmin ? '../' : '';

$currentPage = basename($_SERVER['PHP_SELF']);

$faviconLinks = '
    <!-- Favicon - Primary ICO format for Google Search -->
    <link rel="icon" type="image/x-icon" href="' . $basePath . 'favicon.ico" sizes="16x16 32x32 48x48">
    <!-- Favicon - PNG fallback -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon(2).png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon(2).png">
    <!-- Apple Touch Icon -->
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/favicon(2).png">
    <!-- Google AdSense -->
    <meta name="google-adsense-account" content="ca-pub-2821959013351742">
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2821959013351742" crossorigin="anonymous"></script>';

$googleAdSenseScript = '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2821959013351742" crossorigin="anonymous"></script>';
?>
<div class="mob-drawer-overlay" id="mobDrawerOverlay" onclick="closeMobDrawer()"></div>

<div class="mob-drawer" id="mobDrawer">
    <div class="mob-drawer-header">
        <div class="mob-drawer-avatar"><i class="fas fa-user"></i></div>
        <div class="mob-drawer-user">
            <div class="mob-drawer-username">Welcome!</div>
            <div class="mob-drawer-action">Tailoring Management</div>
        </div>
        <button class="mob-drawer-close" onclick="closeMobDrawer()" aria-label="Close menu"><i class="fas fa-times"></i></button>
    </div>
    <div class="mob-drawer-links">
        <a href="<?php echo $basePath; ?>./" class="mob-drawer-link"><i class="fas fa-home"></i> Home</a>
        <a href="<?php echo $basePath; ?>tailors.php" class="mob-drawer-link"><i class="fas fa-map-marker-alt"></i> Tailors Near You</a>
        <a href="<?php echo $basePath; ?>tailors.php?cat=men" class="mob-drawer-link"><i class="fas fa-tshirt"></i> Categories</a>
        <a href="<?php echo ($currentPage === 'index.php') ? '#how-it-works' : $basePath . './#how-it-works'; ?>" class="mob-drawer-link"><i class="fas fa-question-circle"></i> How It Works</a>
        <a href="<?php echo $basePath; ?>admin/login.php" class="mob-drawer-link"><i class="fas fa-sign-in-alt"></i> Login</a>
        <a href="<?php echo $basePath; ?>admin/register.php" class="mob-drawer-link"><i class="fas fa-user-plus"></i> Register Business</a>
        <a href="<?php echo $basePath; ?>contact.php" class="mob-drawer-link"><i class="fas fa-envelope"></i> Contact Us</a>
        <a href="<?php echo $basePath; ?>about.php" class="mob-drawer-link"><i class="fas fa-info-circle"></i> About Us</a>
        <a href="<?php echo $basePath; ?>blog.php" class="mob-drawer-link"><i class="fas fa-newspaper"></i> Blog</a>
    </div>
</div>

<header class="mob-header">
    <div class="mob-header-left">
        <button class="mob-hamburger" onclick="openMobDrawer()" aria-label="Open menu">
            <i class="fas fa-bars"></i>
        </button>
        <a href="<?php echo $basePath; ?>./" style="text-decoration:none;">
            <img src="<?php echo $basePath; ?>uploads/logos/main-logo.png" alt="TailorPro" class="mob-logo"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
            <span class="mob-logo-text" style="display:none;"><i class="fas fa-cut" style="font-size:18px;"></i> Tai<span>lo</span>r</span>
        </a>
    </div>
    <div class="mob-header-right">
        <a href="<?php echo $basePath; ?>tailors.php?city=Surat" class="mob-location-chip" style="text-decoration:none;">
            <i class="fas fa-map-marker-alt"></i> Surat, Gujarat <i class="fas fa-chevron-down"></i>
        </a>
        <a href="<?php echo $basePath; ?>admin/login.php" class="mob-avatar" aria-label="Profile">
            <i class="fas fa-user"></i>
        </a>
    </div>
</header>
<nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm d-none d-md-block">
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
                    <a class="nav-link active" href="<?php echo $basePath; ?>blog.php">Blog</a>
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

