<?php

$isAdmin = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false);
$basePath = $isAdmin ? '../' : '';
$currentPage = basename($_SERVER['PHP_SELF']);

$currentYear = date('Y');
?>
<nav class="mob-bottom-nav">
    <a href="<?php echo $basePath; ?>./" class="mob-nav-item <?php echo ($currentPage === 'index.php' || empty($currentPage)) ? 'active' : ''; ?>">
        <span class="mob-nav-icon"><i class="fas fa-home"></i></span>
        <span class="mob-nav-label">Home</span>
    </a>
    <a href="<?php echo $basePath; ?>tailors.php" class="mob-nav-item <?php echo ($currentPage === 'tailors.php' || $currentPage === 'tailor.php') ? 'active' : ''; ?>">
        <span class="mob-nav-icon"><i class="fas fa-th-large"></i></span>
        <span class="mob-nav-label">Categories</span>
    </a>
    <a href="<?php echo $basePath; ?>admin/register.php" class="mob-nav-register" aria-label="Register">
        <i class="fas fa-plus"></i>
    </a>
    <a href="<?php echo $basePath; ?>blog.php" class="mob-nav-item <?php echo ($currentPage === 'blog.php' || $currentPage === 'article.php') ? 'active' : ''; ?>">
        <span class="mob-nav-icon"><i class="fas fa-newspaper"></i></span>
        <span class="mob-nav-label">Blog</span>
    </a>
    <a href="<?php echo $basePath; ?>admin/login.php" class="mob-nav-item <?php echo ($currentPage === 'login.php' || $currentPage === 'register.php' || $currentPage === 'profile.php' || $currentPage === 'dashboard.php') ? 'active' : ''; ?>">
        <span class="mob-nav-icon"><i class="fas fa-user"></i></span>
        <span class="mob-nav-label">Profile</span>
    </a>
</nav>


<footer id="contact" class="footer-section py-3">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="footer-brand">
                    <h3 class="brand-name">
                        <img src="<?php echo $basePath; ?>uploads/logos/main-logo.png" alt="TailorPro" class="footer-logo me-2" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                        <i class="fas fa-cut text-primary me-2" style="display: none;"></i>
                    </h3>
                    <p class="brand-description">
                        The complete tailoring management system for modern businesses. 
                        Digitalize your workflow and grow your business.
                    </p>
                    <div class="social-links">
                       
                    </div>
                </div>
            </div>
            <div class="col-lg-2">
                <div class="footer-links">
                    <h5 class="link-title footer-toggle-title">
                        Quick Links
                        <span class="footer-toggle-icon">+</span>
                    </h5>
                    <ul class="link-list footer-toggle-content">
                        <li><a href="<?php echo $basePath; ?>./">Home</a></li>
                        <li><a href="<?php echo $basePath; ?>./#features">Features</a></li>
                        <li><a href="<?php echo $basePath; ?>./#benefits">Benefits</a></li>
                        <li style="display: none;"><a href="<?php echo $basePath; ?>./#pricing">Pricing</a></li>
                        <li><a href="<?php echo $basePath; ?>contact">Contact Us</a></li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-2">
                <div class="footer-links">
                    <h5 class="link-title footer-toggle-title">
                        Platform
                        <span class="footer-toggle-icon">+</span>
                    </h5>
                    <ul class="link-list footer-toggle-content">
                        <li><a href="<?php echo $basePath; ?>admin/login">Login</a></li>
                        <li><a href="<?php echo $basePath; ?>admin/register">Register</a></li>
                        <li><a href="<?php echo $basePath; ?>./#how-it-works">How It Works</a></li>
                        <li><a href="<?php echo $basePath; ?>./#testimonials">Testimonials</a></li>
                        <li><a href="<?php echo $basePath; ?>./#screenshots">Screenshots</a></li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="footer-contact">
                    <h5 class="link-title footer-toggle-title">
                        Contact Info
                        <span class="footer-toggle-icon">+</span>
                    </h5>
                    <div class="footer-toggle-content">
                        <div class="contact-item">
                            <i class="fas fa-envelope me-2"></i>
                            <span><a href="https://mail.google.com/mail/?view=cm&fs=1&to=codelockinfo@gmail.com" target="_blank">codelockinfo@gmail.com</a></span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-phone me-2"></i>
                            <span><a href="tel:+917600464414">+917600464414</a></span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt me-2"></i>
                            <span>Silver business point, near vip circle, utran, Surat - 394105</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <hr class="my-0 my-lg-4" style="border-color: rgba(0, 0, 0, 0.1);">
        <div class="row align-items-center">
            <div class="col-md-6">
                <p class="copyright mb-0">
                    &copy; <?php echo $currentYear; ?> TailorPro. All rights reserved by <a href="https://codelocksolutions.com/" target="_blank" class="legal-link">Codelock Solutions</a>
                </p>
            </div>
            <div class="col-md-6 text-md-end">
                <div class="footer-legal">
                    <a href="<?php echo $basePath; ?>about" class="legal-link me-3">About Us</a>
                    <a href="<?php echo $basePath; ?>privacy-policy" class="legal-link me-3">Privacy Policy</a>
                    <a href="<?php echo $basePath; ?>terms-of-service" class="legal-link">Terms of Service</a>
                    <a href="<?php echo $basePath; ?>blog" class="legal-link">Blog</a>
                </div>
            </div>
        </div>
    </div>
</footer>
<meta name="google-adsense-account" content="ca-pub-2821959013351742">
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2821959013351742" crossorigin="anonymous"></script>

<script>
(function() {
    'use strict';
    function isMobileView() {
        return window.innerWidth <= 767;
    }
    function initFooterToggle() {
        const footer = document.querySelector('.footer-section');
        if (!footer) {
            return false;
        }
        if (footer.dataset.toggleInitialized === 'true') {
            return true;
        }
        footer.dataset.toggleInitialized = 'true';
        footer.addEventListener('click', function(e) {
            if (!isMobileView()) {
                return;
            }
            
            const clickedElement = e.target;
            let toggleTitle = null;
            if (clickedElement.classList.contains('footer-toggle-title')) {
                toggleTitle = clickedElement;
            } 
            else if (clickedElement.classList.contains('footer-toggle-icon')) {
                toggleTitle = clickedElement.closest('.footer-toggle-title');
            }
            else {
                toggleTitle = clickedElement.closest('.footer-toggle-title');
            }
            
            if (!toggleTitle) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            const content = toggleTitle.nextElementSibling;
            if (!content || !content.classList.contains('footer-toggle-content')) {
                return;
            }
            const isActive = toggleTitle.classList.contains('active');
            
            const allTitles = footer.querySelectorAll('.footer-toggle-title');
            const allContents = footer.querySelectorAll('.footer-toggle-content');
            
            allTitles.forEach(function(title) {
                if (title !== toggleTitle) {
                    title.classList.remove('active');
                    const icon = title.querySelector('.footer-toggle-icon');
                    if (icon) icon.textContent = '+';
                }
            });
            
            allContents.forEach(function(cont) {
                if (cont !== content) {
                    cont.classList.remove('active');
                }
            });
            if (isActive) {
                toggleTitle.classList.remove('active');
                content.classList.remove('active');
                const icon = toggleTitle.querySelector('.footer-toggle-icon');
                if (icon) icon.textContent = '+';
            } else {
                toggleTitle.classList.add('active');
                content.classList.add('active');
                const icon = toggleTitle.querySelector('.footer-toggle-icon');
                if (icon) icon.textContent = '-';
            }
        });
        
        return true;
    }
    
    function tryInit() {
        if (!initFooterToggle()) {
            setTimeout(tryInit, 100);
        }
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tryInit);
    } else {
        tryInit();
    }
    
    window.addEventListener('load', function() {
        setTimeout(initFooterToggle, 50);
        });

    let resizeTimer;
        window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            const isMobile = isMobileView();
            const toggleTitles = document.querySelectorAll('.footer-toggle-title');
            const toggleContents = document.querySelectorAll('.footer-toggle-content');
            
            if (!isMobile) {
                toggleTitles.forEach(function(title) {
                    title.classList.remove('active');
                });
                toggleContents.forEach(function(content) {
                    content.classList.remove('active');
                });
            }
        }, 250);
    });
})();
function openMobDrawer() {
    var drawer = document.getElementById('mobDrawer');
    var overlay = document.getElementById('mobDrawerOverlay');
    if (drawer) drawer.classList.add('open');
    if (overlay) overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeMobDrawer() {
    var drawer = document.getElementById('mobDrawer');
    var overlay = document.getElementById('mobDrawerOverlay');
    if (drawer) drawer.classList.remove('open');
    if (overlay) overlay.classList.remove('open');
    document.body.style.overflow = '';
}
</script>

<?php require_once __DIR__ . '/whatsapp-button.php'; ?>

