/**
 * Tailoring Management System Landing Page
 * Interactive JavaScript for modern user experience
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Initialize all components
    initNavbar();
    initScrollAnimations();
    initCounters();
    initSmoothScrolling();
    initParallax();
    initTestimonials();
    initPricingCards();
    initScrollIndicator();
    initFeaturesSlider();
    initBenefitsSlider();
    initStepsSlider();
    initScreenshotsSlider();
    initTestimonialsSlider();
    initPricingSlider();
    
    // Add loading animation
    setTimeout(() => {
        document.body.classList.add('loaded');
    }, 100);
});

/**
 * Navbar functionality
 */
function initNavbar() {
    const navbar = document.querySelector('.navbar');
    const navLinks = document.querySelectorAll('.nav-link');
    
    // Navbar scroll effect
    window.addEventListener('scroll', () => {
        if (window.scrollY > 100) {
            navbar.classList.add('navbar-scrolled');
            navbar.style.background = 'rgba(255, 255, 255, 0.95)';
            navbar.style.backdropFilter = 'blur(10px)';
        } else {
            navbar.classList.remove('navbar-scrolled');
            navbar.style.background = 'white';
            navbar.style.backdropFilter = 'none';
        }
    });
    
    // Navigation links - let browser handle all navigation normally
    // No JavaScript interference - URLs work as expected
    
    // Mobile menu close on link click
    const navbarToggler = document.querySelector('.navbar-toggler');
    const navbarCollapse = document.querySelector('.navbar-collapse');
    
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 992) {
                navbarCollapse.classList.remove('show');
            }
        });
    });
}

/**
 * Scroll animations
 */
function initScrollAnimations() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-fade-in-up');
            }
        });
    }, observerOptions);
    
    // Observe elements for animation
    const animateElements = document.querySelectorAll('.feature-card, .benefit-item, .step-card, .testimonial-card, .pricing-card, .screenshot-card');
    animateElements.forEach(el => {
        observer.observe(el);
    });
}

/**
 * Counter animations
 */
function initCounters() {
    const counters = document.querySelectorAll('.stat-number');
    const counterObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                counterObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });
    
    counters.forEach(counter => {
        counterObserver.observe(counter);
    });
}

function animateCounter(element) {
    const target = parseInt(element.textContent.replace(/[^\d]/g, ''));
    const duration = 2000;
    const step = target / (duration / 16);
    let current = 0;
    
    const timer = setInterval(() => {
        current += step;
        if (current >= target) {
            current = target;
            clearInterval(timer);
        }
        
        const suffix = element.textContent.includes('+') ? '+' : 
                      element.textContent.includes('%') ? '%' : '';
        element.textContent = Math.floor(current) + suffix;
    }, 16);
}

/**
 * Smooth scrolling for anchor links
 * Disabled to allow normal browser navigation
 */
function initSmoothScrolling() {
    // Smooth scrolling disabled - browser handles all navigation normally
    // This prevents JavaScript errors and allows proper URL navigation
}

/**
 * Parallax effect for hero section
 */
function initParallax() {
    const heroSection = document.querySelector('.hero-section');
    const heroImage = document.querySelector('.hero-image');
    
    window.addEventListener('scroll', () => {
        const scrolled = window.pageYOffset;
        const rate = scrolled * -0.5;
        
        // if (heroImage) {
        //     heroImage.style.transform = `translateY(${rate}px)`;
        // }
    });
}

/**
 * Testimonials carousel functionality
 */
function initTestimonials() {
    const testimonialCards = document.querySelectorAll('.testimonial-card');
    
    // Add hover effects
    testimonialCards.forEach(card => {
        card.addEventListener('mouseenter', () => {
            card.style.transform = 'translateY(-10px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', () => {
            card.style.transform = 'translateY(0) scale(1)';
        });
    });
}

/**
 * Pricing cards interactions
 */
function initPricingCards() {
    const pricingCards = document.querySelectorAll('.pricing-card');
    
    pricingCards.forEach(card => {
        // Add click effect
        card.addEventListener('click', () => {
            pricingCards.forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
        });
        
        // Add hover effects
        card.addEventListener('mouseenter', () => {
            if (!card.classList.contains('featured')) {
                card.style.transform = 'translateY(-10px)';
            }
        });
        
        card.addEventListener('mouseleave', () => {
            if (!card.classList.contains('featured')) {
                card.style.transform = 'translateY(0)';
            }
        });
    });
}

/**
 * Features Slider Initialization
 */
function initFeaturesSlider() {
    // Check if jQuery and Slick are loaded
    if (typeof jQuery !== 'undefined' && typeof jQuery.fn.slick !== 'undefined') {
        // Only initialize slider on mobile/tablet devices
        if (window.innerWidth < 1024) {
            jQuery('.features-slider').slick({
                dots: true,
                infinite: true,
                speed: 500,
                slidesToShow: window.innerWidth < 768 ? 1 : 2,
                slidesToScroll: 1,
                autoplay: true,
                autoplaySpeed: 3000,
                pauseOnHover: true,
                arrows: window.innerWidth >= 768,
                responsive: [
                    {
                        breakpoint: 1024,
                        settings: {
                            slidesToShow: 2,
                            slidesToScroll: 1,
                            infinite: true,
                            dots: true,
                            arrows: true
                        }
                    },
                    {
                        breakpoint: 768,
                        settings: {
                            slidesToShow: 1,
                            slidesToScroll: 1,
                            arrows: false
                        }
                    }
                ]
            });
        }
        
        // Handle window resize
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                if (window.innerWidth >= 1024) {
                    // Destroy slider on desktop
                    if (jQuery('.features-slider').hasClass('slick-initialized')) {
                        jQuery('.features-slider').slick('unslick');
                    }
                } else {
                    // Initialize slider on mobile/tablet
                    if (!jQuery('.features-slider').hasClass('slick-initialized')) {
                        jQuery('.features-slider').slick({
                            dots: true,
                            infinite: true,
                            speed: 500,
                            slidesToShow: window.innerWidth < 768 ? 1 : 2,
                            slidesToScroll: 1,
                            autoplay: true,
                            autoplaySpeed: 3000,
                            pauseOnHover: true,
                            arrows: window.innerWidth >= 768,
                            responsive: [
                                {
                                    breakpoint: 1024,
                                    settings: {
                                        slidesToShow: 2,
                                        slidesToScroll: 1,
                                        infinite: true,
                                        dots: true,
                                        arrows: true
                                    }
                                },
                                {
                                    breakpoint: 768,
                                    settings: {
                                        slidesToShow: 1,
                                        slidesToScroll: 1,
                                        arrows: false
                                    }
                                }
                            ]
                        });
                    }
                }
            }, 250);
        });
    } else {
        console.error('jQuery or Slick Carousel not loaded');
    }
}

/**
 * Benefits Slider Initialization
 */
function initBenefitsSlider() {
    // Check if jQuery and Slick are loaded
    if (typeof jQuery !== 'undefined' && typeof jQuery.fn.slick !== 'undefined') {
        // Only initialize slider on mobile/tablet devices
        if (window.innerWidth < 1024) {
            jQuery('.benefits-slider').slick({
                dots: true,
                infinite: true,
                speed: 500,
                slidesToShow: window.innerWidth < 768 ? 1 : 2,
                slidesToScroll: 1,
                autoplay: true,
                autoplaySpeed: 3000,
                pauseOnHover: true,
                arrows: window.innerWidth >= 768,
                responsive: [
                    {
                        breakpoint: 1024,
                        settings: {
                            slidesToShow: 2,
                            slidesToScroll: 1,
                            infinite: true,
                            dots: true,
                            arrows: true
                        }
                    },
                    {
                        breakpoint: 768,
                        settings: {
                            slidesToShow: 1,
                            slidesToScroll: 1,
                            arrows: false
                        }
                    }
                ]
            });
        }
        
        // Handle window resize
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                if (window.innerWidth >= 1024) {
                    // Destroy slider on desktop
                    if (jQuery('.benefits-slider').hasClass('slick-initialized')) {
                        jQuery('.benefits-slider').slick('unslick');
                    }
                } else {
                    // Initialize slider on mobile/tablet
                    if (!jQuery('.benefits-slider').hasClass('slick-initialized')) {
                        jQuery('.benefits-slider').slick({
                            dots: true,
                            infinite: true,
                            speed: 500,
                            slidesToShow: window.innerWidth < 768 ? 1 : 2,
                            slidesToScroll: 1,
                            autoplay: true,
                            autoplaySpeed: 3000,
                            pauseOnHover: true,
                            arrows: window.innerWidth >= 768,
                            responsive: [
                                {
                                    breakpoint: 1024,
                                    settings: {
                                        slidesToShow: 2,
                                        slidesToScroll: 1,
                                        infinite: true,
                                        dots: true,
                                        arrows: true
                                    }
                                },
                                {
                                    breakpoint: 768,
                                    settings: {
                                        slidesToShow: 1,
                                        slidesToScroll: 1,
                                        arrows: false
                                    }
                                }
                            ]
                        });
                    }
                }
            }, 250);
        });
    } else {
        console.error('jQuery or Slick Carousel not loaded');
    }
}

/**
 * Steps Slider Initialization
 */
function initStepsSlider() {
    if (typeof jQuery !== 'undefined' && typeof jQuery.fn.slick !== 'undefined') {
        if (window.innerWidth < 1024) {
            jQuery('.steps-slider').slick({
                dots: true,
                infinite: true,
                speed: 500,
                slidesToShow: window.innerWidth < 768 ? 1 : 2,
                slidesToScroll: 1,
                autoplay: true,
                autoplaySpeed: 3000,
                pauseOnHover: true,
                arrows: window.innerWidth >= 768,
                responsive: [
                    {
                        breakpoint: 1024,
                        settings: {
                            slidesToShow: 2,
                            slidesToScroll: 1,
                            infinite: true,
                            dots: true,
                            arrows: true
                        }
                    },
                    {
                        breakpoint: 768,
                        settings: {
                            slidesToShow: 1,
                            slidesToScroll: 1,
                            arrows: false
                        }
                    }
                ]
            });
        }
        
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                if (window.innerWidth >= 1024) {
                    if (jQuery('.steps-slider').hasClass('slick-initialized')) {
                        jQuery('.steps-slider').slick('unslick');
                    }
                } else {
                    if (!jQuery('.steps-slider').hasClass('slick-initialized')) {
                        jQuery('.steps-slider').slick({
                            dots: true,
                            infinite: true,
                            speed: 500,
                            slidesToShow: window.innerWidth < 768 ? 1 : 2,
                            slidesToScroll: 1,
                            autoplay: true,
                            autoplaySpeed: 3000,
                            pauseOnHover: true,
                            arrows: window.innerWidth >= 768,
                            responsive: [
                                {
                                    breakpoint: 1024,
                                    settings: {
                                        slidesToShow: 2,
                                        slidesToScroll: 1,
                                        infinite: true,
                                        dots: true,
                                        arrows: true
                                    }
                                },
                                {
                                    breakpoint: 768,
                                    settings: {
                                        slidesToShow: 1,
                                        slidesToScroll: 1,
                                        arrows: false
                                    }
                                }
                            ]
                        });
                    }
                }
            }, 250);
        });
    }
}

/**
 * Screenshots Slider Initialization
 */
function initScreenshotsSlider() {
    if (typeof jQuery !== 'undefined' && typeof jQuery.fn.slick !== 'undefined') {
        if (window.innerWidth < 1024) {
            jQuery('.screenshots-slider').slick({
                dots: true,
                infinite: true,
                speed: 500,
                slidesToShow: window.innerWidth < 768 ? 1 : 2,
                slidesToScroll: 1,
                autoplay: true,
                autoplaySpeed: 3000,
                pauseOnHover: true,
                arrows: window.innerWidth >= 768,
                responsive: [
                    {
                        breakpoint: 1024,
                        settings: {
                            slidesToShow: 2,
                            slidesToScroll: 1,
                            infinite: true,
                            dots: true,
                            arrows: true
                        }
                    },
                    {
                        breakpoint: 768,
                        settings: {
                            slidesToShow: 1,
                            slidesToScroll: 1,
                            arrows: false
                        }
                    }
                ]
            });
        }
        
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                if (window.innerWidth >= 1024) {
                    if (jQuery('.screenshots-slider').hasClass('slick-initialized')) {
                        jQuery('.screenshots-slider').slick('unslick');
                    }
                } else {
                    if (!jQuery('.screenshots-slider').hasClass('slick-initialized')) {
                        jQuery('.screenshots-slider').slick({
                            dots: true,
                            infinite: true,
                            speed: 500,
                            slidesToShow: window.innerWidth < 768 ? 1 : 2,
                            slidesToScroll: 1,
                            autoplay: true,
                            autoplaySpeed: 3000,
                            pauseOnHover: true,
                            arrows: window.innerWidth >= 768,
                            responsive: [
                                {
                                    breakpoint: 1024,
                                    settings: {
                                        slidesToShow: 2,
                                        slidesToScroll: 1,
                                        infinite: true,
                                        dots: true,
                                        arrows: true
                                    }
                                },
                                {
                                    breakpoint: 768,
                                    settings: {
                                        slidesToShow: 1,
                                        slidesToScroll: 1,
                                        arrows: false
                                    }
                                }
                            ]
                        });
                    }
                }
            }, 250);
        });
    }
}

/**
 * Testimonials Slider Initialization
 */
function initTestimonialsSlider() {
    if (typeof jQuery !== 'undefined' && typeof jQuery.fn.slick !== 'undefined') {
        if (window.innerWidth < 1024) {
            jQuery('.testimonials-slider').slick({
                dots: true,
                infinite: true,
                speed: 500,
                slidesToShow: window.innerWidth < 768 ? 1 : 2,
                slidesToScroll: 1,
                autoplay: true,
                autoplaySpeed: 3000,
                pauseOnHover: true,
                arrows: window.innerWidth >= 768,
                responsive: [
                    {
                        breakpoint: 1024,
                        settings: {
                            slidesToShow: 2,
                            slidesToScroll: 1,
                            infinite: true,
                            dots: true,
                            arrows: true
                        }
                    },
                    {
                        breakpoint: 768,
                        settings: {
                            slidesToShow: 1,
                            slidesToScroll: 1,
                            arrows: false
                        }
                    }
                ]
            });
        }
        
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                if (window.innerWidth >= 1024) {
                    if (jQuery('.testimonials-slider').hasClass('slick-initialized')) {
                        jQuery('.testimonials-slider').slick('unslick');
                    }
                } else {
                    if (!jQuery('.testimonials-slider').hasClass('slick-initialized')) {
                        jQuery('.testimonials-slider').slick({
                            dots: true,
                            infinite: true,
                            speed: 500,
                            slidesToShow: window.innerWidth < 768 ? 1 : 2,
                            slidesToScroll: 1,
                            autoplay: true,
                            autoplaySpeed: 3000,
                            pauseOnHover: true,
                            arrows: window.innerWidth >= 768,
                            responsive: [
                                {
                                    breakpoint: 1024,
                                    settings: {
                                        slidesToShow: 2,
                                        slidesToScroll: 1,
                                        infinite: true,
                                        dots: true,
                                        arrows: true
                                    }
                                },
                                {
                                    breakpoint: 768,
                                    settings: {
                                        slidesToShow: 1,
                                        slidesToScroll: 1,
                                        arrows: false
                                    }
                                }
                            ]
                        });
                    }
                }
            }, 250);
        });
    }
}

/**
 * Pricing Slider Initialization
 */
function initPricingSlider() {
    if (typeof jQuery !== 'undefined' && typeof jQuery.fn.slick !== 'undefined') {
        if (window.innerWidth < 1024) {
            jQuery('.pricing-slider').slick({
                dots: true,
                infinite: true,
                speed: 500,
                slidesToShow: window.innerWidth < 768 ? 1 : 2,
                slidesToScroll: 1,
                autoplay: true,
                autoplaySpeed: 3000,
                pauseOnHover: true,
                arrows: window.innerWidth >= 768,
                responsive: [
                    {
                        breakpoint: 1024,
                        settings: {
                            slidesToShow: 2,
                            slidesToScroll: 1,
                            infinite: true,
                            dots: true,
                            arrows: true
                        }
                    },
                    {
                        breakpoint: 768,
                        settings: {
                            slidesToShow: 1,
                            slidesToScroll: 1,
                            arrows: false
                        }
                    }
                ]
            });
        }
        
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                if (window.innerWidth >= 1024) {
                    if (jQuery('.pricing-slider').hasClass('slick-initialized')) {
                        jQuery('.pricing-slider').slick('unslick');
                    }
                } else {
                    if (!jQuery('.pricing-slider').hasClass('slick-initialized')) {
                        jQuery('.pricing-slider').slick({
                            dots: true,
                            infinite: true,
                            speed: 500,
                            slidesToShow: window.innerWidth < 768 ? 1 : 2,
                            slidesToScroll: 1,
                            autoplay: true,
                            autoplaySpeed: 3000,
                            pauseOnHover: true,
                            arrows: window.innerWidth >= 768,
                            responsive: [
                                {
                                    breakpoint: 1024,
                                    settings: {
                                        slidesToShow: 2,
                                        slidesToScroll: 1,
                                        infinite: true,
                                        dots: true,
                                        arrows: true
                                    }
                                },
                                {
                                    breakpoint: 768,
                                    settings: {
                                        slidesToShow: 1,
                                        slidesToScroll: 1,
                                        arrows: false
                                    }
                                }
                            ]
                        });
                    }
                }
            }, 250);
        });
    }
}

/**
 * Scroll Progress Indicator
 */
function initScrollIndicator() {
    console.log('Initializing scroll indicator...');
    
    const scrollIndicator = document.getElementById('scrollProgressBar');
    
    if (!scrollIndicator) {
        console.error('Scroll indicator element not found!');
        return;
    }
    
    console.log('Scroll indicator element found:', scrollIndicator);
    
    function updateScrollProgress() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const docHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        
        console.log('Scroll values:', { scrollTop, docHeight });
        
        if (docHeight <= 0) {
            console.log('Document height is 0, skipping update');
            return;
        }
        
        const scrollPercent = (scrollTop / docHeight) * 100;
        const progress = Math.min(Math.max(scrollPercent, 0), 100);
        
        console.log('Setting progress to:', progress + '%');
        scrollIndicator.style.width = progress + '%';
    }
    
    // Use a more reliable scroll event handler
    let ticking = false;
    
    function onScroll() {
        if (!ticking) {
            requestAnimationFrame(() => {
                updateScrollProgress();
                ticking = false;
            });
            ticking = true;
        }
    }
    
    // Add multiple event listeners for better compatibility
    window.addEventListener('scroll', onScroll, { passive: true });
    document.addEventListener('scroll', onScroll, { passive: true });
    
    // Initial call after a short delay to ensure DOM is ready
    setTimeout(() => {
        console.log('Initial scroll progress update');
        updateScrollProgress();
    }, 100);
    
    // Also call on window load
    window.addEventListener('load', () => {
        console.log('Window loaded, updating scroll progress');
        updateScrollProgress();
    });
    
}

/**
 * Form validation and submission
 */
function initForms() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            handleFormSubmission(form);
        });
    });
}

function handleFormSubmission(form) {
    const formData = new FormData(form);
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.textContent;
    
    // Show loading state
    submitButton.textContent = 'Sending...';
    submitButton.disabled = true;
    
    // Simulate form submission (replace with actual API call)
    setTimeout(() => {
        showNotification('Thank you! We\'ll get back to you soon.', 'success');
        form.reset();
        submitButton.textContent = originalText;
        submitButton.disabled = false;
    }, 2000);
}

/**
 * Notification system
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
            <span>${message}</span>
            <button class="notification-close">&times;</button>
        </div>
    `;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#10b981' : '#3b82f6'};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        z-index: 10000;
        transform: translateX(400px);
        transition: transform 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto remove
    setTimeout(() => {
        notification.style.transform = 'translateX(400px)';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 5000);
    
    // Close button
    notification.querySelector('.notification-close').addEventListener('click', () => {
        notification.style.transform = 'translateX(400px)';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    });
}

/**
 * Lazy loading for images
 */
function initLazyLoading() {
    const images = document.querySelectorAll('img[data-src]');
    
    const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                imageObserver.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
}

/**
 * Performance optimization
 */
function optimizePerformance() {
    // Debounce scroll events
    let scrollTimeout;
    window.addEventListener('scroll', () => {
        if (scrollTimeout) {
            clearTimeout(scrollTimeout);
        }
        scrollTimeout = setTimeout(() => {
            // Scroll-based functions here
        }, 16);
    });
    
    // Throttle resize events
    let resizeTimeout;
    window.addEventListener('resize', () => {
        if (resizeTimeout) {
            clearTimeout(resizeTimeout);
        }
        resizeTimeout = setTimeout(() => {
            // Resize-based functions here
        }, 250);
    });
}

/**
 * Accessibility improvements
 */
// function initAccessibility() {
//     // Skip to main content
//     const skipLink = document.createElement('a');
//     skipLink.href = '#main-content';
//     skipLink.textContent = 'Skip to main content';
//     skipLink.className = 'skip-link';
//     skipLink.style.cssText = `
//         position: absolute;
//         top: -40px;
//         left: 6px;
//         background: #000;
//         color: #fff;
//         padding: 8px;
//         text-decoration: none;
//         z-index: 10000;
//         transition: top 0.3s;
//     `;
    
//     skipLink.addEventListener('focus', () => {
//         skipLink.style.top = '6px';
//     });
    
//     skipLink.addEventListener('blur', () => {
//         skipLink.style.top = '-40px';
//     });
    
//     document.body.insertBefore(skipLink, document.body.firstChild);
    
//     // Keyboard navigation
//     document.addEventListener('keydown', (e) => {
//         if (e.key === 'Tab') {
//             document.body.classList.add('keyboard-navigation');
//         }
//     });
    
//     document.addEventListener('mousedown', () => {
//         document.body.classList.remove('keyboard-navigation');
//     });
// }

/**
 * Analytics and tracking
 */
function initAnalytics() {
    // Track CTA clicks
    const ctaButtons = document.querySelectorAll('a[href*="register"], a[href*="login"]');
    ctaButtons.forEach(button => {
        button.addEventListener('click', () => {
            const buttonText = button.textContent.trim();
            const buttonLocation = button.closest('section')?.id || 'unknown';
            
            // Track event (replace with your analytics code)
            if (typeof gtag !== 'undefined') {
                gtag('event', 'cta_click', {
                    'button_text': buttonText,
                    'location': buttonLocation
                });
            }
        });
    });
    
    // Track scroll depth
    let maxScroll = 0;
    window.addEventListener('scroll', () => {
        const scrollPercent = Math.round((window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100);
        if (scrollPercent > maxScroll) {
            maxScroll = scrollPercent;
            
            // Track milestones
            if (maxScroll >= 25 && maxScroll < 50) {
                trackEvent('scroll_25_percent');
            } else if (maxScroll >= 50 && maxScroll < 75) {
                trackEvent('scroll_50_percent');
            } else if (maxScroll >= 75) {
                trackEvent('scroll_75_percent');
            }
        }
    });
}

function trackEvent(eventName, parameters = {}) {
    // Replace with your analytics tracking code
    console.log('Analytics Event:', eventName, parameters);
    
    if (typeof gtag !== 'undefined') {
        gtag('event', eventName, parameters);
    }
}

/**
 * Initialize additional features
 */
document.addEventListener('DOMContentLoaded', function() {
    initForms();
    initLazyLoading();
    optimizePerformance();
    // initAccessibility(); // Not implemented yet
    // initAnalytics(); // Not implemented yet
});

/**
 * Utility functions
 */
const utils = {
    // Debounce function
    debounce: (func, wait) => {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    // Throttle function
    throttle: (func, limit) => {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },
    
    // Check if element is in viewport
    isInViewport: (element) => {
        const rect = element.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    }
};

// Go to Top Button Functionality
function initGoToTopButton() {
    const goToTopBtn = document.getElementById('goToTopBtn');
    
    if (!goToTopBtn) return;
    
    // Check if we're on the home page (index.php or ./)
    function isHomePage() {
        const currentPath = window.location.pathname;
        const currentPage = currentPath.split('/').pop();
        return currentPage === '' || 
               currentPage === 'index.php' || 
               currentPath.endsWith('/') ||
               currentPath === '/easytailor/' ||
               currentPath === '/easytailor/index.php';
    }
    
    // Apply home page class if on home page
    if (isHomePage()) {
        goToTopBtn.classList.add('home-page');
    }
    
    // Handle window resize to maintain correct positioning
    function handleResize() {
        // The CSS media query will handle the positioning automatically
        // This function can be used for any additional resize logic if needed
    }
    
    // Show/hide button based on scroll position
    function toggleGoToTopButton() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const windowHeight = window.innerHeight;
        const documentHeight = document.documentElement.scrollHeight;
        
        // Show button when user has scrolled more than 300px OR when near bottom
        const showButton = scrollTop > 300 || (scrollTop + windowHeight) >= (documentHeight - 100);
        
        if (showButton) {
            goToTopBtn.classList.add('show');
        } else {
            goToTopBtn.classList.remove('show');
        }
    }
    
    // Smooth scroll to top
    function scrollToTop() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
        
        // Track the event
        if (typeof trackEvent === 'function') {
            trackEvent('button_click', 'go_to_top', 'navigation');
        }
    }
    
    // Event listeners
    window.addEventListener('scroll', toggleGoToTopButton, { passive: true });
    window.addEventListener('resize', handleResize, { passive: true });
    goToTopBtn.addEventListener('click', scrollToTop);
    
    // Initial check
    toggleGoToTopButton();
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', initGoToTopButton);

// Export for use in other scripts
window.LandingPage = {
    showNotification,
    trackEvent,
    utils,
    initGoToTopButton
};
