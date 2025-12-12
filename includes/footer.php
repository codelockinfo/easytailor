<?php
/**
 * Footer Component
 * Reusable footer for all user-facing pages
 */

// Determine base path for links based on current directory
$isAdmin = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false);
$basePath = $isAdmin ? '../' : '';

// Get current year
$currentYear = date('Y');
?>
<!-- Footer -->
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
                        <a href="#" class="social-link" target="_blank" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <!-- <a href="#" class="social-link" target="_blank" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link" target="_blank" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a> -->
                        <a href="#" class="social-link" target="_blank" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
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
                        <li><a href="<?php echo $basePath; ?>./#pricing">Pricing</a></li>
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
                            <span><a href="mailto:codelockinfo@gmail.com">codelockinfo@gmail.com</a></span>
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
        <hr class="my-4" style="border-color: rgba(0, 0, 0, 0.1);">
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

<!-- Google AdSense -->
<meta name="google-adsense-account" content="ca-pub-2821959013351742">
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2821959013351742" crossorigin="anonymous"></script>

<script>
// Footer Mobile Toggle Functionality
(function() {
    'use strict';
    
    // Function to check if mobile view
    function isMobileView() {
        return window.innerWidth <= 767;
    }
    
    // Function to initialize footer toggle
    function initFooterToggle() {
        const footer = document.querySelector('.footer-section');
        if (!footer) {
            return false;
        }
        
        // Check if already initialized
        if (footer.dataset.toggleInitialized === 'true') {
            return true;
        }
        
        // Mark as initialized
        footer.dataset.toggleInitialized = 'true';
        
        // Add click event listener to footer (event delegation)
        footer.addEventListener('click', function(e) {
            // Only work on mobile
            if (!isMobileView()) {
                return;
            }
            
            // Check if clicked element or its parent is a toggle title or icon
            const clickedElement = e.target;
            let toggleTitle = null;
            
            // Check if clicked on the title itself
            if (clickedElement.classList.contains('footer-toggle-title')) {
                toggleTitle = clickedElement;
            } 
            // Check if clicked on the icon
            else if (clickedElement.classList.contains('footer-toggle-icon')) {
                toggleTitle = clickedElement.closest('.footer-toggle-title');
            }
            // Check if clicked inside the title
            else {
                toggleTitle = clickedElement.closest('.footer-toggle-title');
            }
            
            if (!toggleTitle) {
                return;
            }
            
            // Prevent default behavior
            e.preventDefault();
            e.stopPropagation();
            
            // Find the content element
            const content = toggleTitle.nextElementSibling;
            if (!content || !content.classList.contains('footer-toggle-content')) {
                return;
            }
            
            // Check if already active
            const isActive = toggleTitle.classList.contains('active');
            
            // Close all other sections first
            const allTitles = footer.querySelectorAll('.footer-toggle-title');
            const allContents = footer.querySelectorAll('.footer-toggle-content');
            
            allTitles.forEach(function(title) {
                if (title !== toggleTitle) {
                    title.classList.remove('active');
                }
            });
            
            allContents.forEach(function(cont) {
                if (cont !== content) {
                    cont.classList.remove('active');
                }
            });
            
            // Toggle current section
            if (isActive) {
                toggleTitle.classList.remove('active');
                content.classList.remove('active');
            } else {
                toggleTitle.classList.add('active');
                content.classList.add('active');
            }
        });
        
        return true;
    }
    
    // Initialize when DOM is ready
    function tryInit() {
        if (!initFooterToggle()) {
            // Retry after a short delay
            setTimeout(tryInit, 100);
        }
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tryInit);
    } else {
        tryInit();
    }
    
    // Also try on window load as fallback
    window.addEventListener('load', function() {
        setTimeout(initFooterToggle, 50);
        });

        // Handle window resize
    let resizeTimer;
        window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            const isMobile = isMobileView();
            const toggleTitles = document.querySelectorAll('.footer-toggle-title');
            const toggleContents = document.querySelectorAll('.footer-toggle-content');
            
            if (!isMobile) {
                // Desktop: Remove active classes and show all content
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
    </script>
