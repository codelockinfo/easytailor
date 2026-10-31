<?php

$isAdmin = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false);
$basePath = $isAdmin ? '../' : '';

$currentPage = basename($_SERVER['PHP_SELF']);

$faviconLinks = '
    <link rel="icon" type="image/x-icon" href="' . $basePath . 'favicon.ico" sizes="16x16 32x32 48x48">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon(2).png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon(2).png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/favicon(2).png">
    <meta name="google-adsense-account" content="ca-pub-2821959013351742">
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2821959013351742" crossorigin="anonymous"></script>';

$googleAdSenseScript = '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2821959013351742" crossorigin="anonymous"></script>';
?>
<!-- Mobile / Offcanvas Navigation Drawer -->
<div class="site-drawer-overlay" id="siteDrawerOverlay" onclick="closeSiteDrawer()"></div>
<div class="site-drawer" id="siteDrawer">
    <div class="site-drawer-header">
        <a class="desktop-nav-brand" href="<?php echo $basePath; ?>./" style="text-decoration:none;">
            <i class="fas fa-cut me-1" style="color:#6366f1;"></i> Tai<span style="color:#6366f1;">lor</span>
        </a>
        <button class="site-drawer-close" onclick="closeSiteDrawer()" aria-label="Close menu">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="site-drawer-links">
        <a href="<?php echo ($currentPage === 'index.php') ? '#home' : $basePath . './#home'; ?>" class="site-drawer-link" onclick="closeSiteDrawer()"><i class="fas fa-home"></i> Home</a>
        <a href="<?php echo ($currentPage === 'index.php') ? '#about' : $basePath . './#about'; ?>" class="site-drawer-link" onclick="closeSiteDrawer()"><i class="fas fa-info-circle"></i> About</a>
        <a href="<?php echo ($currentPage === 'index.php') ? '#features' : $basePath . './#features'; ?>" class="site-drawer-link" onclick="closeSiteDrawer()"><i class="fas fa-th-large"></i> Features</a>
        <a href="<?php echo ($currentPage === 'index.php') ? '#tailors-near-you' : $basePath . './#tailors-near-you'; ?>" class="site-drawer-link" onclick="closeSiteDrawer()"><i class="fas fa-map-marker-alt"></i> Tailor Shops</a>
        <a href="<?php echo ($currentPage === 'index.php') ? '#benefits' : $basePath . './#benefits'; ?>" class="site-drawer-link" onclick="closeSiteDrawer()"><i class="fas fa-star"></i> Benefits</a>
        <a href="<?php echo ($currentPage === 'index.php') ? '#how-it-works' : $basePath . './#how-it-works'; ?>" class="site-drawer-link" onclick="closeSiteDrawer()"><i class="fas fa-question-circle"></i> How It Works</a>
        <a href="<?php echo ($currentPage === 'index.php') ? '#screenshots' : $basePath . './#screenshots'; ?>" class="site-drawer-link" onclick="closeSiteDrawer()"><i class="fas fa-laptop"></i> See It In Action</a>
        <a href="<?php echo ($currentPage === 'index.php') ? '#testimonials' : $basePath . './#testimonials'; ?>" class="site-drawer-link" onclick="closeSiteDrawer()"><i class="fas fa-comment-dots"></i> Testimonials</a>
        <a href="<?php echo ($currentPage === 'index.php') ? '#contact' : $basePath . './#contact'; ?>" class="site-drawer-link" onclick="closeSiteDrawer()"><i class="fas fa-envelope"></i> Contact</a>
        <a href="<?php echo $basePath; ?>blog.php" class="site-drawer-link" onclick="closeSiteDrawer()"><i class="fas fa-newspaper"></i> Blog</a>
    </div>
    <div class="site-drawer-actions">
        <a href="<?php echo $basePath; ?>admin/login.php" class="desktop-btn-login w-100 text-center">Login</a>
        <a href="<?php echo $basePath; ?>admin/register.php" class="desktop-btn-register w-100 text-center">Register</a>
    </div>
</div>

<!-- Main Top Navbar (Responsive for Desktop, Tablet, and Mobile) -->
<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?php echo $basePath; ?>./" style="text-decoration: none;">
            <span class="desktop-nav-brand"><i class="fas fa-cut me-1" style="color:#6366f1;"></i> Tai<span style="color:#6366f1;">lor</span></span>
        </a>
        
        <div class="d-flex align-items-center d-lg-none gap-2">
            <a href="<?php echo $basePath; ?>admin/login.php" class="btn btn-sm login-header-btn px-2 py-1" style="font-size:12px;">Login</a>
            <button class="navbar-toggler border-0 p-1" type="button" onclick="openSiteDrawer()" aria-label="Toggle navigation" style="box-shadow:none;">
                <i class="fas fa-bars" style="font-size:20px; color:#4f46e5;"></i>
            </button>
        </div>
        
        <div class="collapse navbar-collapse d-none d-lg-flex" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo ($currentPage === 'index.php') ? '#features' : $basePath . './#features'; ?>">Features</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo ($currentPage === 'index.php') ? '#benefits' : $basePath . './#benefits'; ?>">Benefits</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo ($currentPage === 'index.php') ? '#contact' : $basePath . './#contact'; ?>">Contact</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $basePath; ?>blog.php">Blog</a>
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

<script>
function openSiteDrawer() {
    var drawer = document.getElementById('siteDrawer');
    var overlay = document.getElementById('siteDrawerOverlay');
    if (drawer) drawer.classList.add('open');
    if (overlay) overlay.style.display = 'block';
    document.body.style.overflow = 'hidden';
}
function closeSiteDrawer() {
    var drawer = document.getElementById('siteDrawer');
    var overlay = document.getElementById('siteDrawerOverlay');
    if (drawer) drawer.classList.remove('open');
    if (overlay) overlay.style.display = 'none';
    document.body.style.overflow = '';
}
</script>
