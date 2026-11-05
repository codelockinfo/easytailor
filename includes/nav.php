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
<style>

.announcement-bar {
    background: linear-gradient(90deg, #6d28d9 0%, #7c3aed 50%, #8b5cf6 100%) !important;
    color: #ffffff !important;
    padding: 8px 15px !important;
    font-size: 13px !important;
    font-weight: 500 !important;
    letter-spacing: 0.2px;
    box-shadow: 0 2px 10px rgba(109, 40, 217, 0.2) !important;
    position: relative !important;
    width: 100% !important;
    z-index: 1060 !important;
    display: block !important;
}
.announcement-bar .announcement-content {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 12px !important;
    text-align: center !important;
    padding: 0 !important;
    position: relative !important;
}
.announcement-bar .announcement-text {
    font-size: 13px !important;
    font-weight: 500 !important;
    color: #ffffff !important;
    margin: 0 !important;
}
.announcement-click-hint {
    color: #ffffff !important;
    text-decoration: underline !important;
    font-weight: 700 !important;
    font-size: 13px !important;
    cursor: pointer !important;
    white-space: nowrap !important;
}
.announcement-bar .announcement-close {
    background: transparent !important;
    border: none !important;
    color: rgba(255, 255, 255, 0.8) !important;
    cursor: pointer !important;
    font-size: 14px !important;
    padding: 2px 6px !important;
    position: absolute !important;
    right: 15px !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
}
.announcement-bar .announcement-close:hover {
    color: #ffffff !important;
}

/* Sticky Main Navbar (Desktop Only) */
@media (min-width: 768px) {
    .navbar.sticky-top,
    .navbar.fixed-top,
    .navbar {
        position: sticky !important;
        top: 0 !important;
        background: #ffffff !important;
        border-bottom: 1px solid #f1f5f9 !important;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04) !important;
        padding: 14px 0 !important;
        transition: all 0.3s ease;
        z-index: 1050 !important;
        display: block !important;
        width: 100% !important;
    }
}

@media (max-width: 767px) {
    .announcement-bar,
    .navbar,
    .navbar.sticky-top,
    .navbar.fixed-top,
    nav.navbar {
        display: none !important;
    }
}

.desktop-nav-brand {
    display: inline-flex !important;
    align-items: center !important;
    font-size: 34px !important;
    font-weight: 800 !important;
    color: #1e1b4b !important;
    letter-spacing: -0.5px !important;
    text-decoration: none !important;
}
.desktop-nav-brand i {
    color: #6366f1 !important;
    font-size: 32px !important;
    margin-right: 6px !important;
}

.navbar-nav .nav-link {
    font-size: 14.5px !important;
    font-weight: 500 !important;
    color: #475569 !important;
    padding: 8px 16px !important;
    transition: all 0.2s ease !important;
}
.navbar-nav .nav-link:hover,
.navbar-nav .nav-link.active {
    color: #4f46e5 !important;
}

/* Action Buttons */
.desktop-btn-login,
.login-header-btn {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    background: #f5f3ff !important;
    color: #4f46e5 !important;
    border: 1.5px solid #ddd6fe !important;
    border-radius: 50px !important;
    padding: 7px 22px !important;
    font-size: 13.5px !important;
    font-weight: 600 !important;
    text-decoration: none !important;
    box-shadow: none !important;
    transition: all 0.2s ease !important;
}
.desktop-btn-login:hover,
.login-header-btn:hover {
    background: #ede9fe !important;
    border-color: #c4b5fd !important;
    color: #4338ca !important;
    transform: translateY(-1px);
}

.desktop-btn-register,
.register-header-btn {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    background: #4f46e5 !important;
    color: #ffffff !important;
    border: 1.5px solid #4f46e5 !important;
    border-radius: 50px !important;
    padding: 7px 24px !important;
    font-size: 13.5px !important;
    font-weight: 600 !important;
    text-decoration: none !important;
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35) !important;
    transition: all 0.2s ease !important;
}
.desktop-btn-register:hover,
.register-header-btn:hover {
    background: #4338ca !important;
    border-color: #4338ca !important;
    box-shadow: 0 6px 20px rgba(79, 70, 229, 0.45) !important;
    transform: translateY(-1px);
    color: #ffffff !important;
}

/* Mobile Offcanvas Drawer */
.site-drawer-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    z-index: 1080;
    display: none;
}
.site-drawer {
    position: fixed;
    top: 0;
    right: -320px;
    width: 300px;
    height: 100%;
    background: #ffffff;
    z-index: 1090;
    box-shadow: -4px 0 25px rgba(0, 0, 0, 0.15);
    transition: right 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    display: flex;
    flex-direction: column;
}
.site-drawer.open {
    right: 0 !important;
}
.site-drawer-header {
    padding: 20px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.site-drawer-close {
    background: transparent;
    border: none;
    font-size: 20px;
    color: #64748b;
    cursor: pointer;
    padding: 4px 8px;
}
.site-drawer-links {
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    overflow-y: auto;
}
.site-drawer-link {
    font-size: 15px;
    font-weight: 600;
    color: #334155;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 12px;
    border-radius: 8px;
    transition: all 0.2s;
}
.site-drawer-link:hover {
    background: #f5f3ff;
    color: #6366f1;
}
.site-drawer-link i {
    width: 20px;
    color: #6366f1;
    font-size: 16px;
}
.site-drawer-actions {
    margin-top: auto;
    padding: 20px;
    border-top: 1px solid #f1f5f9;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

/* Container Width Override - Matches Homepage 1700px */
.container,
.container-fluid,
.container-xl,
.container-xxl {
    max-width: 1700px !important;
    padding-left: 40px !important;
    padding-right: 40px !important;
}
@media (max-width: 768px) {
    .container,
    .container-fluid,
    .container-xl,
    .container-xxl {
        padding-left: 16px !important;
        padding-right: 16px !important;
    }
}
@media (max-width: 767px) {
    .announcement-bar,
    .navbar {
        display: none !important;
    }
}
</style>

<?php require_once __DIR__ . '/announcement-bar.php'; ?>

<?php if ($currentPage !== 'index.php'): ?>
<!-- Mobile Header & Navigation Drawer (Exact Homepage Mobile App Experience) -->
<div class="mob-drawer-overlay" id="mobDrawerOverlay" onclick="closeMobDrawer()"></div>
<div class="mob-drawer" id="mobDrawer">
    <div class="mob-drawer-header">
        <div class="mob-drawer-avatar"><i class="fas fa-user"></i></div>
        <div class="mob-drawer-user">
            <div class="mob-drawer-username">Welcome!</div>
            <div class="mob-drawer-action">Login or Register</div>
        </div>
        <button class="mob-drawer-close" onclick="closeMobDrawer()"><i class="fas fa-times"></i></button>
    </div>
    <div class="mob-drawer-links">
        <a href="<?php echo $basePath; ?>./" class="mob-drawer-link"><i class="fas fa-home"></i> Home</a>
        <a href="<?php echo $basePath; ?>tailors.php" class="mob-drawer-link"><i class="fas fa-map-marker-alt"></i> Tailors Near You</a>
        <a href="<?php echo $basePath; ?>tailors.php?cat=men" class="mob-drawer-link"><i class="fas fa-tshirt"></i> Categories</a>
        <a href="<?php echo $basePath; ?>./#how-it-works" class="mob-drawer-link"><i class="fas fa-question-circle"></i> How It Works</a>
        <a href="<?php echo $basePath; ?>admin/login.php" class="mob-drawer-link"><i class="fas fa-sign-in-alt"></i> Login</a>
        <a href="<?php echo $basePath; ?>admin/register.php" class="mob-drawer-link"><i class="fas fa-user-plus"></i> Register Business</a>
        <a href="<?php echo $basePath; ?>contact.php" class="mob-drawer-link"><i class="fas fa-envelope"></i> Contact Us</a>
        <a href="<?php echo $basePath; ?>about.php" class="mob-drawer-link"><i class="fas fa-info-circle"></i> About</a>
        <a href="<?php echo $basePath; ?>blog.php" class="mob-drawer-link"><i class="fas fa-blog"></i> Blog</a>
    </div>
</div>
<header class="mob-header">
    <div class="mob-header-left">
        <button class="mob-hamburger" onclick="openMobDrawer()" aria-label="Menu">
            <i class="fas fa-bars"></i>
        </button>
        <a href="<?php echo $basePath; ?>./" style="text-decoration:none;">
            <img src="<?php echo $basePath; ?>uploads/logos/main-logo.png" alt="Tailor" class="mob-logo"
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
<?php endif; ?>

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
<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm d-none d-md-block">
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
function openMobDrawer() {
    var drawer = document.getElementById('mobDrawer');
    var overlay = document.getElementById('mobDrawerOverlay');
    if (drawer) drawer.classList.add('open');
    if (overlay) overlay.style.display = 'block';
    document.body.style.overflow = 'hidden';
}
function closeMobDrawer() {
    var drawer = document.getElementById('mobDrawer');
    var overlay = document.getElementById('mobDrawerOverlay');
    if (drawer) drawer.classList.remove('open');
    if (overlay) overlay.style.display = 'none';
    document.body.style.overflow = '';
}
function closeSiteDrawer() {
    var drawer = document.getElementById('siteDrawer');
    var overlay = document.getElementById('siteDrawerOverlay');
    if (drawer) drawer.classList.remove('open');
    if (overlay) overlay.style.display = 'none';
    document.body.style.overflow = '';
}
</script>