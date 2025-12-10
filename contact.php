<?php
/**
 * Contact Us Page
 * Tailoring Management System - Standalone Page
 */

// Load config for session and helper functions
if (file_exists(__DIR__ . '/config/config.php')) {
    require_once __DIR__ . '/config/config.php';
} else {
    session_start();
}

// Set page title
$page_title = 'Contact Us';

// Check if user is logged in
$user_logged = is_logged_in() ? 1 : 0;
$user_id = get_user_id();
?>
<?php
// Load SEO Helper if available
if (file_exists(__DIR__ . '/helpers/SEOHelper.php')) {
    require_once 'helpers/SEOHelper.php';
}

$baseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$canonicalUrl = $baseUrl . '/contact.php';

$seoOptions = [
    'title' => 'Contact Us - ' . (defined('APP_NAME') ? APP_NAME : 'Tailoring Management System'),
    'description' => 'Get in touch with us. Have questions about our tailoring management system? Contact our support team for assistance.',
    'keywords' => 'contact us, support, help, tailoring management, customer service',
    'canonical' => $canonicalUrl,
    'og_type' => 'article',
    'structured_data' => [
        "@context" => "https://schema.org",
        "@type" => "ContactPage",
        "name" => "Contact Us",
        "description" => "Get in touch with our support team",
        "url" => $canonicalUrl
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    if (file_exists(__DIR__ . '/helpers/SEOHelper.php') && class_exists('SEOHelper')) {
        try {
            echo SEOHelper::generateMetaTags($seoOptions); 
        } catch (Exception $e) {
            error_log('SEOHelper error: ' . $e->getMessage());
            echo '<title>' . htmlspecialchars($seoOptions['title']) . '</title>';
            echo '<meta name="description" content="' . htmlspecialchars($seoOptions['description']) . '">';
            echo '<meta name="keywords" content="' . htmlspecialchars($seoOptions['keywords']) . '">';
            echo '<link rel="canonical" href="' . htmlspecialchars($seoOptions['canonical']) . '">';
        }
    } else {
        echo '<title>' . htmlspecialchars($seoOptions['title']) . '</title>';
        echo '<meta name="description" content="' . htmlspecialchars($seoOptions['description']) . '">';
        echo '<meta name="keywords" content="' . htmlspecialchars($seoOptions['keywords']) . '">';
        echo '<link rel="canonical" href="' . htmlspecialchars($seoOptions['canonical']) . '">';
    }
    ?>
    
    <!-- Google Analytics 4 (GA4) -->
    <?php
    if (file_exists(__DIR__ . '/helpers/GA4Helper.php')) {
        require_once 'helpers/GA4Helper.php';
        if (class_exists('GA4Helper')) {
            try {
                echo GA4Helper::generateBaseCode();
            } catch (Exception $e) {
                error_log('GA4Helper error: ' . $e->getMessage());
            }
        }
    }
    ?>
    
    <link href="assets/css/style3.css" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon(2).png">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }

        .btn {
            border-radius: var(--border-radius);
            font-weight: 600;
            padding: 0.75rem 2rem;
            transition: var(--transition);
            border: none;
            position: relative;
            overflow: hidden;
        }

        .login-header-btn {
            border: 2px solid var(--primary-color) !important;
            color: var(--primary-color) !important;
            background: transparent !important;
            border-radius: 50px !important;
        }

        .navbar.fixed-top {
            top: 0 !important;
        }

        .navbar-brand {
            font-weight: 700;
            color: #667eea !important;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px 15px 0 0 !important;
        }

        .card-header h1 {
            font-size: 24px;
        }
        
        .contact-form-section {
            padding: 2rem 0;
        }
        
        .form-label {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .contact-info-box {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-left: 4px solid #667eea;
            padding: 2rem;
            border-radius: 10px;
            height: 100%;
        }
        
        .contact-info-box h3 {
            color: #667eea;
            margin-bottom: 1.5rem;
        }
        
        .contact-info-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .contact-info-item i {
            font-size: 1.5rem;
            color: #667eea;
            margin-top: 0.25rem;
            flex-shrink: 0;
        }
        
        .contact-info-item-content h5 {
            color: #2d3748;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .contact-info-item-content p {
            color: #4a5568;
            margin-bottom: 0;
        }
        
        .contact-info-item-content a {
            color: #667eea;
            text-decoration: none;
        }
        
        .contact-info-item-content a:hover {
            text-decoration: underline;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .navbar {
            background: white !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .alert {
            border-radius: 10px;
        }
        
        .is-invalid {
            border-color: #dc3545;
        }
        
        .invalid-feedback {
            display: block;
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <img src="uploads/logos/main-logo.png" alt="TailorPro" class="navbar-logo me-2" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                <i class="fas fa-cut text-primary me-2" style="display: none;"></i>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#benefits">Benefits</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#pricing">Pricing</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#contact">Contact</a>
                    </li>
                    <li class="nav-item ms-3">
                        <a href="admin/login.php" class="btn  btn-sm login-header-btn">Login</a>
                    </li>
                    <li class="nav-item ms-2">
                        <a href="admin/register.php" class="btn  btn-sm register-header-btn">Register</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

<div class="container py-5" style="padding-top: 8rem!important;">
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-envelope me-2"></i>Contact Us
                    </h1>
                    <p class="mb-0 mt-2">We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <!-- Contact Form -->
                        <div class="col-lg-7">
                            <form id="contactForm" novalidate>
                                <input type="hidden" name="user_logged" value="<?php echo $user_logged; ?>">
                                <?php if ($user_id): ?>
                                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label for="name" class="form-label">Your Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" required maxlength="100">
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="emailId" class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="emailId" name="emailId" required maxlength="150">
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="message" name="message" rows="6" required></textarea>
                                    <div class="invalid-feedback"></div>
                                </div>
                                
                                <div id="formMessage" class="alert d-none" role="alert"></div>
                                
                                <button type="submit" class="btn btn-primary btn-lg w-100" id="submitBtn">
                                    <i class="fas fa-paper-plane me-2"></i>Send Message
                                </button>
                            </form>
                        </div>
                        
                        <!-- Contact Information -->
                        <div class="col-lg-5">
                            <div class="contact-info-box">
                                <h3><i class="fas fa-info-circle me-2"></i>Get in Touch</h3>
                                
                                <div class="contact-info-item">
                                    <i class="fas fa-envelope"></i>
                                    <div class="contact-info-item-content">
                                        <h5>Email Us</h5>
                                        <p><a href="mailto:codelockinfo@gmail.com">codelockinfo@gmail.com</a></p>
                                    </div>
                                </div>
                                
                                <div class="contact-info-item">
                                    <i class="fas fa-phone"></i>
                                    <div class="contact-info-item-content">
                                        <h5>Call Us</h5>
                                        <p><a href="tel:+917600464414">+91 7600464414</a></p>
                                    </div>
                                </div>
                                
                                <div class="contact-info-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <div class="contact-info-item-content">
                                        <h5>Visit Us</h5>
                                        <p>Silver business point, near vip circle, utran, Surat - 394105</p>
                                    </div>
                                </div>
                                
                                <div class="contact-info-item">
                                    <i class="fas fa-clock"></i>
                                    <div class="contact-info-item-content">
                                        <h5>Business Hours</h5>
                                        <p>Monday - Friday: 9:00 AM - 6:00 PM IST</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<?php require_once 'includes/footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Form validation and submission
    document.getElementById('contactForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = this;
        const submitBtn = document.getElementById('submitBtn');
        const formMessage = document.getElementById('formMessage');
        const originalBtnText = submitBtn.innerHTML;
        
        // Reset previous states
        formMessage.classList.add('d-none');
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
        
        // Validate form
        let isValid = true;
        const name = document.getElementById('name').value.trim();
        const emailId = document.getElementById('emailId').value.trim();
        const message = document.getElementById('message').value.trim();
        
        if (!name) {
            showFieldError('name', 'Please enter your name');
            isValid = false;
        }
        
        if (!emailId) {
            showFieldError('emailId', 'Please enter your email address');
            isValid = false;
        } else if (!isValidEmail(emailId)) {
            showFieldError('emailId', 'Please enter a valid email address');
            isValid = false;
        }
        
        if (!message) {
            showFieldError('message', 'Please enter your message');
            isValid = false;
        }
        
        if (!isValid) {
            return;
        }
        
        // Disable submit button
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
        
        // Prepare form data
        const formData = new FormData(form);
        
        // Submit form
        fetch('ajax/submit_contact.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                formMessage.classList.remove('d-none', 'alert-danger');
                formMessage.classList.add('alert-success');
                formMessage.innerHTML = '<i class="fas fa-check-circle me-2"></i>' + (data.message || 'Thank you! Your message has been sent successfully. We will get back to you soon.');
                form.reset();
                
                // Track contact form submission
                <?php
                if (file_exists(__DIR__ . '/helpers/GA4Helper.php') && class_exists('GA4Helper')) {
                    echo "if (typeof gtag !== 'undefined') { gtag('event', 'contact_form_submit', { 'event_category': 'engagement' }); }";
                }
                ?>
            } else {
                formMessage.classList.remove('d-none', 'alert-success');
                formMessage.classList.add('alert-danger');
                formMessage.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>' + (data.message || 'Sorry, there was an error sending your message. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            formMessage.classList.remove('d-none', 'alert-success');
            formMessage.classList.add('alert-danger');
            formMessage.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>Network error. Please check your connection and try again.';
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
            
            // Scroll to message
            formMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    });
    
    function showFieldError(fieldId, message) {
        const field = document.getElementById(fieldId);
        field.classList.add('is-invalid');
        const feedback = field.nextElementSibling;
        if (feedback && feedback.classList.contains('invalid-feedback')) {
            feedback.textContent = message;
        }
    }
    
    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
</script>

<script>
    // Track page view (wait for gtag to be available)
    <?php
    if (file_exists(__DIR__ . '/helpers/GA4Helper.php')) {
        require_once 'helpers/GA4Helper.php';
        if (class_exists('GA4Helper')) {
            try {
                $pageTitle = 'Contact Us - ' . (defined('APP_NAME') ? APP_NAME : 'Tailoring Management System');
                $pageLocation = $canonicalUrl;
                $pageViewCode = GA4Helper::trackPageView($pageTitle, $pageLocation);
            } catch (Exception $e) {
                error_log('GA4Helper trackPageView error: ' . $e->getMessage());
                $pageViewCode = '// GA4Helper error';
            }
        } else {
            $pageViewCode = '// GA4Helper not available';
        }
    } else {
        $pageViewCode = '// GA4Helper not available';
    }
    ?>
    (function() {
        var attempts = 0;
        var maxAttempts = 50;
        
        function firePageView() {
            if (typeof gtag !== 'undefined' && typeof window.dataLayer !== 'undefined') {
                try {
                    <?php echo $pageViewCode; ?>
                } catch (e) {
                    console.error('GA4 page_view tracking error:', e);
                }
            } else {
                attempts++;
                if (attempts < maxAttempts) {
                    setTimeout(firePageView, 100);
                } else {
                    console.warn('GA4 not loaded after 5 seconds, page_view event may be lost');
                }
            }
        }
        
        firePageView();
    })();
</script>

<!-- WhatsApp Button -->
<?php require_once 'includes/whatsapp-button.php'; ?>
</body>
</html>

