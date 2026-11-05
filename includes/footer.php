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


<style>
@media (min-width: 768px) {
    .mob-bottom-nav,
    .mob-header,
    .mob-drawer,
    .mob-drawer-overlay {
        display: none !important;
    }
    .footer-toggle-icon {
        display: none !important;
        visibility: hidden !important;
    }
}
@media (max-width: 767px) {
    .footer-section,
    footer.footer-section {
        display: none !important;
    }
}
.footer-social-icons {
    display: flex !important;
    gap: 8px !important;
    align-items: center !important;
    margin-top: 10px !important;
    flex-wrap: wrap !important;
}
.footer-social-btn {
    width: 32px !important;
    height: 32px !important;
    border-radius: 50% !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 13px !important;
    color: #ffffff !important;
    text-decoration: none !important;
    transition: transform 0.2s ease, box-shadow 0.2s ease !important;
    border: none !important;
    outline: none !important;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1) !important;
}

.footer-social-btn i {
    color: #ffffff !important;
    font-size: 13px !important;
    line-height: 1 !important;
}

.footer-social-btn:hover {
    transform: translateY(-2px) !important;
    color: #ffffff !important;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.25) !important;
}

.footer-social-btn.fb { background: #1877f2 !important; }
.footer-social-btn.yt { background: #ff0000 !important; }
.footer-social-btn.li { background: #0a66c2 !important; }
.footer-social-btn.ig { background: radial-gradient(circle at 30% 107%, #fdf497 0%, #fdf497 5%, #fd5949 45%, #d6249f 60%, #285aeb 90%) !important; }
.footer-social-btn.wa { background: #25d366 !important; }
</style>
<footer id="contact" class="footer-section py-3 d-none d-md-block">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="footer-brand">
                     <a class="navbar-brand fw-bold" href="<?php echo $basePath; ?>./" style="text-decoration: none;">
            <span class="desktop-nav-brand"><i class="fas fa-cut me-1" style="color:#6366f1;"></i> Tai<span style="color:#6366f1;">lor</span></span>
        </a>
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
                        <div class="mt-3">
                            <div style="font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 8px;">Follow Us:</div>
                            <div class="footer-social-icons">
                                <a href="https://www.facebook.com/" target="_blank" class="footer-social-btn fb" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                                <a href="https://www.youtube.com/" target="_blank" class="footer-social-btn yt" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                                <a href="https://www.linkedin.com/" target="_blank" class="footer-social-btn li" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                                <a href="https://www.instagram.com/" target="_blank" class="footer-social-btn ig" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                                <a href="https://wa.me/917600464414" target="_blank" class="footer-social-btn wa" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                            </div>
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

