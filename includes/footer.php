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
<footer id="contact" class="footer-section py-5">
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
                        <a href="#" class="social-link" target="_blank" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link" target="_blank" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
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
                        <li><a href="<?php echo $basePath; ?>index.php#home">Home</a></li>
                        <li><a href="<?php echo $basePath; ?>index.php#features">Features</a></li>
                        <li><a href="<?php echo $basePath; ?>index.php#benefits">Benefits</a></li>
                        <li><a href="<?php echo $basePath; ?>index.php#pricing">Pricing</a></li>
                        <li><a href="<?php echo $basePath; ?>contact.php">Contact Us</a></li>
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
                        <li><a href="<?php echo $basePath; ?>admin/login.php">Login</a></li>
                        <li><a href="<?php echo $basePath; ?>admin/register.php">Register</a></li>
                        <li><a href="<?php echo $basePath; ?>index.php#how-it-works">How It Works</a></li>
                        <li><a href="<?php echo $basePath; ?>index.php#testimonials">Testimonials</a></li>
                        <li><a href="<?php echo $basePath; ?>index.php#screenshots">Screenshots</a></li>
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
                    <a href="<?php echo $basePath; ?>about.php" class="legal-link me-3">About Us</a>
                    <a href="<?php echo $basePath; ?>contact.php" class="legal-link me-3">Contact Us</a>
                    <a href="<?php echo $basePath; ?>privacy-policy.php" class="legal-link me-3">Privacy Policy</a>
                    <a href="<?php echo $basePath; ?>terms-of-service.php" class="legal-link">Terms of Service</a>
                </div>
            </div>
        </div>
    </div>
</footer>

<script>
// Footer Mobile Toggle Functionality
(function() {
    // Only run on mobile devices (max-width 767px)
    if (window.innerWidth <= 767) {
        const toggleTitles = document.querySelectorAll('.footer-toggle-title');
        
        toggleTitles.forEach(function(title) {
            title.addEventListener('click', function() {
                const content = this.nextElementSibling;
                const isActive = this.classList.contains('active');
                
                // Close all other sections
                toggleTitles.forEach(function(otherTitle) {
                    if (otherTitle !== title) {
                        otherTitle.classList.remove('active');
                        const otherContent = otherTitle.nextElementSibling;
                        if (otherContent && otherContent.classList.contains('footer-toggle-content')) {
                            otherContent.classList.remove('active');
                        }
                    }
                });
                
                // Toggle current section
                if (isActive) {
                    this.classList.remove('active');
                    if (content && content.classList.contains('footer-toggle-content')) {
                        content.classList.remove('active');
                    }
                } else {
                    this.classList.add('active');
                    if (content && content.classList.contains('footer-toggle-content')) {
                        content.classList.add('active');
                    }
                }
            });
        });
    }
    
    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            const isMobile = window.innerWidth <= 767;
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
