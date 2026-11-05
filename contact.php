<?php
if (file_exists(__DIR__ . '/config/config.php')) {
    require_once __DIR__ . '/config/config.php';
} else {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

$page_title = 'Contact Us';

$user_logged = function_exists('is_logged_in') && is_logged_in() ? 1 : 0;
$user_id = function_exists('get_user_id') ? get_user_id() : null;

if (file_exists(__DIR__ . '/helpers/SEOHelper.php')) {
    require_once 'helpers/SEOHelper.php';
}

$baseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$canonicalUrl = $baseUrl . '/contact';
$brandName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : (defined('APP_NAME') ? APP_NAME : 'TailorPro');

$seoOptions = [
    'title' => 'Contact Us - ' . $brandName . ' | Support & Enquiries',
    'description' => 'Get in touch with ' . $brandName . ' by Codelock Solutions. Contact our support team for demo requests, tailoring software assistance, or billing inquiries in Surat, Gujarat.',
    'keywords' => 'contact us, TailorPro support, tailoring software customer service, Codelock Solutions Surat, tailor software helpline',
    'canonical' => $canonicalUrl,
    'og_type' => 'article',
    'structured_data' => [
        "@context" => "https://schema.org",
        "@type" => "ContactPage",
        "name" => "Contact Us - " . $brandName,
        "description" => "Get in touch with our customer support and sales team",
        "url" => $canonicalUrl,
        "mainEntity" => [
            "@type" => "Organization",
            "name" => $brandName,
            "legalName" => "Codelock Solutions",
            "url" => $baseUrl,
            "contactPoint" => [
                "@type" => "ContactPoint",
                "telephone" => "+917600464414",
                "contactType" => "Customer Support",
                "email" => "codelockinfo@gmail.com",
                "areaServed" => "IN",
                "availableLanguage" => ["Hindi", "Gujarati", "English"]
            ]
        ]
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
            echo '<title>' . htmlspecialchars($seoOptions['title']) . '</title>';
            echo '<meta name="description" content="' . htmlspecialchars($seoOptions['description']) . '">';
            echo '<link rel="canonical" href="' . htmlspecialchars($seoOptions['canonical']) . '">';
        }
    } else {
        echo '<title>' . htmlspecialchars($seoOptions['title']) . '</title>';
        echo '<meta name="description" content="' . htmlspecialchars($seoOptions['description']) . '">';
        echo '<link rel="canonical" href="' . htmlspecialchars($seoOptions['canonical']) . '">';
    }
    ?>
    
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
    
    <link href="assets/css/style16.css" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="icon" type="image/x-icon" href="favicon.ico" sizes="16x16 32x32 48x48">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon(2).png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon(2).png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/favicon(2).png">
    
    <style>
        :root {
            --cnt-primary: #4f46e5;
            --cnt-primary-hover: #4338ca;
            --cnt-secondary: #764ba2;
            --cnt-accent: #8b5cf6;
            --cnt-dark: #0f172a;
            --cnt-heading: #1e1b4b;
            --cnt-body: #475569;
            --cnt-light-bg: #f8fafc;
            --cnt-card-border: #e2e8f0;
            --cnt-radius: 18px;
            --cnt-gradient: linear-gradient(135deg, #4f46e5 0%, #667eea 50%, #764ba2 100%);
        }

        html, body {
            overflow-x: clip;
            scroll-padding-top: 85px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8fafc;
            color: var(--cnt-body);
            line-height: 1.7;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--cnt-heading);
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        /* Scroll Reveal Animations */
        .cnt-reveal {
            opacity: 0;
            transform: translateY(28px);
            transition: opacity 0.7s cubic-bezier(0.16, 1, 0.3, 1), transform 0.7s cubic-bezier(0.16, 1, 0.3, 1);
            will-change: opacity, transform;
        }
        .cnt-reveal.is-revealed {
            opacity: 1;
            transform: translateY(0);
        }
        .cnt-delay-1 { transition-delay: 0.12s; }
        .cnt-delay-2 { transition-delay: 0.24s; }

        /* Hero Header */
        .cnt-hero {
            background: var(--cnt-gradient);
            color: #ffffff;
            padding: 60px 0 75px;
            position: relative;
            overflow: hidden;
        }
        .cnt-hero::before {
            content: '';
            position: absolute;
            top: -45%;
            right: -10%;
            width: 480px;
            height: 480px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.16) 0%, rgba(255, 255, 255, 0) 70%);
            pointer-events: none;
        }
        .cnt-hero::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -5%;
            width: 380px;
            height: 380px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.12) 0%, rgba(255, 255, 255, 0) 70%);
            pointer-events: none;
        }

        .cnt-hero .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 1.25rem;
        }
        .cnt-hero .breadcrumb-item a {
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .cnt-hero .breadcrumb-item a:hover {
            color: #ffffff;
            text-decoration: underline;
        }
        .cnt-hero .breadcrumb-item.active {
            color: rgba(255, 255, 255, 0.95);
            font-size: 0.9rem;
        }
        .cnt-hero .breadcrumb-item + .breadcrumb-item::before {
            color: rgba(255, 255, 255, 0.6);
        }

        .cnt-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 6px 18px;
            border-radius: 50px;
            font-size: 0.88rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            border: 1px solid rgba(255, 255, 255, 0.28);
            animation: cntBadgeGlow 3.5s ease-in-out infinite;
        }

        @keyframes cntBadgeGlow {
            0%, 100% { box-shadow: 0 0 12px rgba(255, 255, 255, 0.2); }
            50% { box-shadow: 0 0 25px rgba(255, 255, 255, 0.45); }
        }

        .cnt-hero h1 {
            color: #ffffff;
            font-size: 2.75rem;
            font-weight: 800;
            margin-bottom: 0.85rem;
        }
        .cnt-hero p.lead {
            color: rgba(255, 255, 255, 0.92);
            font-size: 1.15rem;
            max-width: 760px;
            margin-bottom: 1.75rem;
        }

        .cnt-meta-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        .cnt-meta-pill {
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: #ffffff;
            font-size: 0.84rem;
            padding: 6px 14px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        /* Top 3 Quick Action Cards */
        .cnt-quick-cards {
            margin-top: -35px;
            position: relative;
            z-index: 10;
            margin-bottom: 45px;
        }
        .cnt-quick-card {
            background: #ffffff;
            border: 1px solid var(--cnt-card-border);
            border-radius: var(--cnt-radius);
            padding: 24px 22px;
            height: 100%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }
        .cnt-quick-card:hover {
            transform: translateY(-5px);
            border-color: #cbd5e1;
            box-shadow: 0 18px 35px rgba(79, 70, 229, 0.12);
        }
        .cnt-quick-icon {
            width: 50px;
            height: 50px;
            min-width: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: var(--cnt-primary);
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.12) 0%, rgba(118, 75, 162, 0.12) 100%);
            transition: transform 0.3s ease;
        }
        .cnt-quick-card:hover .cnt-quick-icon {
            transform: scale(1.1) rotate(5deg);
        }
        .cnt-quick-card h4 {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 4px;
            color: var(--cnt-heading);
        }
        .cnt-quick-card p {
            font-size: 0.88rem;
            margin-bottom: 6px;
            color: #64748b;
        }
        .cnt-quick-link {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--cnt-primary);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        .cnt-quick-link:hover {
            color: var(--cnt-primary-hover);
            transform: translateX(3px);
            text-decoration: underline;
        }

        /* Form Card */
        .cnt-form-card {
            background: #ffffff;
            border: 1px solid var(--cnt-card-border);
            border-radius: var(--cnt-radius);
            padding: 38px 34px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            height: 100%;
        }
        .cnt-form-header {
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f1f5f9;
        }
        .cnt-form-header h2 {
            font-size: 1.45rem;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .cnt-form-header p {
            font-size: 0.92rem;
            color: #64748b;
            margin-bottom: 0;
        }

        .cnt-label {
            font-weight: 600;
            color: var(--cnt-heading);
            font-size: 0.92rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .cnt-label i {
            color: var(--cnt-primary);
            font-size: 0.88rem;
        }

        .cnt-input {
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.95rem;
            color: #1e293b;
            background-color: #f8fafc;
            transition: all 0.25s ease;
            width: 100%;
        }
        .cnt-input:focus {
            background-color: #ffffff;
            border-color: var(--cnt-primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12);
            outline: none;
        }
        .cnt-input::placeholder {
            color: #94a3b8;
            font-size: 0.9rem;
        }

        .btn-cnt-submit {
            background: var(--cnt-gradient);
            color: #ffffff !important;
            border: none;
            border-radius: 12px;
            padding: 14px 28px;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 0.2px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.25s ease;
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.25);
            width: 100%;
            cursor: pointer;
        }
        .btn-cnt-submit:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(79, 70, 229, 0.35);
        }
        .btn-cnt-submit:disabled {
            opacity: 0.75;
            cursor: not-allowed;
        }

        /* Sidebar Info Card */
        .cnt-info-card {
            background: #ffffff;
            border: 1px solid var(--cnt-card-border);
            border-radius: var(--cnt-radius);
            padding: 34px 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            margin-bottom: 24px;
        }
        .cnt-info-card h3 {
            font-size: 1.35rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .cnt-detail-item {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 22px;
            padding-bottom: 18px;
            border-bottom: 1px solid #f1f5f9;
        }
        .cnt-detail-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        .cnt-detail-icon {
            width: 44px;
            height: 44px;
            min-width: 44px;
            border-radius: 12px;
            background: rgba(79, 70, 229, 0.08);
            color: var(--cnt-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        .cnt-detail-content h5 {
            font-size: 0.98rem;
            margin-bottom: 4px;
            color: var(--cnt-heading);
        }
        .cnt-detail-content p {
            font-size: 0.9rem;
            color: #64748b;
            margin: 0;
            line-height: 1.5;
        }
        .cnt-detail-content a {
            color: var(--cnt-primary);
            text-decoration: none;
            font-weight: 600;
        }
        .cnt-detail-content a:hover {
            text-decoration: underline;
        }

        /* WhatsApp Direct Connect Card */
        .cnt-wa-box {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border: 1px solid #bbf7d0;
            border-radius: var(--cnt-radius);
            padding: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.08);
        }
        .cnt-wa-box h4 {
            font-size: 1.05rem;
            margin-bottom: 4px;
            color: #166534;
        }
        .cnt-wa-box p {
            font-size: 0.85rem;
            color: #15803d;
            margin: 0;
        }
        .btn-wa-direct {
            background: #25d366;
            color: #ffffff !important;
            border: none;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.88rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);
            transition: all 0.25s ease;
            white-space: nowrap;
        }
        .btn-wa-direct:hover {
            background: #1eb956;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(37, 211, 102, 0.4);
            color: #ffffff;
        }

        /* Map Embed Card */
        .cnt-map-card {
            background: #ffffff;
            border: 1px solid var(--cnt-card-border);
            border-radius: var(--cnt-radius);
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            margin-top: 35px;
        }
        .cnt-map-header {
            padding: 18px 24px;
            background: #ffffff;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .cnt-map-header h4 {
            font-size: 1rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--cnt-heading);
        }
        .cnt-map-iframe {
            width: 100%;
            height: 320px;
            border: none;
            display: block;
        }

        .is-invalid {
            border-color: #dc3545 !important;
            background-color: #fff8f8 !important;
        }
        .invalid-feedback {
            display: block;
            color: #dc3545;
            font-size: 0.85rem;
            margin-top: 5px;
            font-weight: 500;
        }

        @media (max-width: 991px) {
            .cnt-hero {
                padding: 40px 0 50px;
            }
            .cnt-hero h1 {
                font-size: 2rem;
            }
            .cnt-quick-cards {
                margin-top: -20px;
            }
            .cnt-form-card, .cnt-info-card {
                padding: 26px 20px;
            }
            .cnt-wa-box {
                flex-direction: column;
                align-items: flex-start;
            }
            .btn-wa-direct {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

    <?php 
    if (file_exists(__DIR__ . '/includes/nav.php')) {
        require_once 'includes/nav.php'; 
    }
    ?>

    <!-- Hero Header -->
    <header class="cnt-hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-10 mx-auto text-center text-lg-start">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center justify-content-lg-start">
                            <li class="breadcrumb-item"><a href="./"><i class="fas fa-home me-1"></i>Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Contact Us</li>
                        </ol>
                    </nav>

                    <div class="cnt-badge">
                        <i class="fas fa-headset"></i> Dedicated Customer Support & Inquiries
                    </div>

                    <h1>Get in Touch with Our Team</h1>
                    <p class="lead">
                        Have questions about <strong><?php echo htmlspecialchars($brandName); ?></strong>, want a personalized live demo for your tailor shop, or need custom onboarding help? We're here to assist you every step of the way.
                    </p>

                    <div class="cnt-meta-tags justify-content-center justify-content-lg-start">
                        <span class="cnt-meta-pill">
                            <i class="fas fa-bolt"></i> Average Response: Under 2 Hours
                        </span>
                        <span class="cnt-meta-pill">
                            <i class="fas fa-map-marker-alt"></i> Surat, Gujarat (India)
                        </span>
                        <span class="cnt-meta-pill">
                            <i class="fas fa-comments"></i> Support in Hindi, Gujarati & English
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Top 3 Quick Info Cards -->
    <div class="container cnt-quick-cards">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="cnt-quick-card cnt-reveal">
                    <div class="cnt-quick-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div>
                        <h4>Call Our Hotline</h4>
                        <p>Mon - Sat: 9:30 AM to 6:30 PM IST</p>
                        <a href="tel:+917600464414" class="cnt-quick-link">
                            +91 76004 64414 <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="cnt-quick-card cnt-reveal cnt-delay-1">
                    <div class="cnt-quick-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div>
                        <h4>Email Inquiries</h4>
                        <p>For support, sales & partnerships</p>
                        <a href="https://mail.google.com/mail/?view=cm&fs=1&to=codelockinfo@gmail.com" target="_blank" class="cnt-quick-link">
                            codelockinfo@gmail.com <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="cnt-quick-card cnt-reveal cnt-delay-2">
                    <div class="cnt-quick-icon">
                        <i class="fab fa-whatsapp text-success"></i>
                    </div>
                    <div>
                        <h4>Instant WhatsApp</h4>
                        <p>Chat directly with our support team</p>
                        <a href="https://wa.me/917600464414" target="_blank" class="cnt-quick-link text-success">
                            Chat Now <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Area: Form & Information -->
    <main class="container mb-5">
        <div class="row g-4 align-items-stretch">
            
            <!-- Left Column: Form -->
            <div class="col-lg-7">
                <div class="cnt-form-card cnt-reveal">
                    <div class="cnt-form-header">
                        <h2><i class="fas fa-paper-plane text-primary"></i> Send Us a Message</h2>
                        <p>Fill out the form below and our team will get back to you promptly.</p>
                    </div>

                    <form id="contactForm" novalidate>
                        <input type="hidden" name="user_logged" value="<?php echo $user_logged; ?>">
                        <?php if ($user_id): ?>
                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="name" class="cnt-label">
                                <i class="fas fa-user"></i> Your Full Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="cnt-input form-control" id="name" name="name" placeholder="e.g. Ramesh Patel / Masterji" required maxlength="100">
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="emailId" class="cnt-label">
                                <i class="fas fa-envelope"></i> Email Address <span class="text-danger">*</span>
                            </label>
                            <input type="email" class="cnt-input form-control" id="emailId" name="emailId" placeholder="e.g. yourname@example.com" required maxlength="150">
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="message" class="cnt-label">
                                <i class="fas fa-comment-dots"></i> How can we help you? <span class="text-danger">*</span>
                            </label>
                            <textarea class="cnt-input form-control" id="message" name="message" rows="5" placeholder="Tell us about your tailor shop or what questions you have..." required></textarea>
                            <div class="invalid-feedback"></div>
                        </div>
                        
                        <div id="formMessage" class="alert d-none" role="alert"></div>
                        
                        <button type="submit" class="btn-cnt-submit" id="submitBtn">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                    </form>
                </div>
            </div>

            <!-- Right Column: Contact Details & Direct WhatsApp Card -->
            <div class="col-lg-5 d-flex flex-column justify-content-between">
                <div class="cnt-info-card cnt-reveal cnt-delay-1 flex-grow-1">
                    <h3><i class="fas fa-building text-primary"></i> Company Information</h3>
                    
                    <div class="cnt-detail-item">
                        <div class="cnt-detail-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="cnt-detail-content">
                            <h5>Head Office</h5>
                            <p>Silver Business Point, Near VIP Circle, Utran, Surat, Gujarat - 394105, India</p>
                        </div>
                    </div>

                    <div class="cnt-detail-item">
                        <div class="cnt-detail-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="cnt-detail-content">
                            <h5>Telephone / Helpline</h5>
                            <p><a href="tel:+917600464414">+91 76004 64414</a></p>
                        </div>
                    </div>

                    <div class="cnt-detail-item">
                        <div class="cnt-detail-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="cnt-detail-content">
                            <h5>Official Email</h5>
                            <p><a href="https://mail.google.com/mail/?view=cm&fs=1&to=codelockinfo@gmail.com" target="_blank">codelockinfo@gmail.com</a></p>
                            <small class="text-muted">Support: info@tailorpro.in</small>
                        </div>
                    </div>

                    <div class="cnt-detail-item">
                        <div class="cnt-detail-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="cnt-detail-content">
                            <h5>Support Working Hours</h5>
                            <p>Monday - Saturday: 9:30 AM to 6:30 PM IST</p>
                            <small class="text-muted">Sunday: Emergency On-Call Support</small>
                        </div>
                    </div>
                </div>

                <!-- Instant WhatsApp Card -->
                <div class="cnt-wa-box cnt-reveal cnt-delay-2">
                    <div>
                        <h4><i class="fab fa-whatsapp me-1"></i> Quick WhatsApp Connect</h4>
                        <p>Need instant onboarding or pricing assistance?</p>
                    </div>
                    <a href="https://wa.me/917600464414" target="_blank" class="btn-wa-direct">
                        <i class="fab fa-whatsapp"></i> Chat Now
                    </a>
                </div>
            </div>

        </div>

        <!-- Google Map Section -->
        <div class="cnt-map-card cnt-reveal">
            <div class="cnt-map-header">
                <h4><i class="fas fa-map-marked-alt text-primary"></i> Visit Our Office in Surat</h4>
                <a href="https://maps.google.com/?q=Silver+Business+Point,+VIP+Circle,+Utran,+Surat" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                    <i class="fas fa-directions me-1"></i> Open Google Maps
                </a>
            </div>
            <iframe 
                class="cnt-map-iframe"
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3719.349176378415!2d72.85966607519965!3d21.218001680486043!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3be04f0525d88691%3A0xe5323a63ec5156fa!2sSilver%20Business%20Point!5e0!3m2!1sen!2sin!4v1700000000000!5m2!1sen!2sin" 
                allowfullscreen="" 
                loading="lazy" 
                referrerpolicy="no-referrer-when-downgrade"
                title="Office Location Map">
            </iframe>
        </div>
    </main>

    <?php 
    if (file_exists(__DIR__ . '/includes/footer.php')) {
        require_once 'includes/footer.php'; 
    }
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php 
    if (file_exists(__DIR__ . '/includes/whatsapp-button.php')) {
        require_once 'includes/whatsapp-button.php'; 
    }
    if (file_exists(__DIR__ . '/includes/go-to-top-button.php')) {
        require_once 'includes/go-to-top-button.php'; 
    }
    ?>

    <script src="assets/js/script2.js"></script>

    <!-- AJAX Form Handling & Scroll Reveal Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // Scroll Reveal Animation
        const reveals = document.querySelectorAll('.cnt-reveal');
        if (reveals.length > 0) {
            if ('IntersectionObserver' in window) {
                const revealObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-revealed');
                            revealObserver.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.08, rootMargin: '0px 0px -30px 0px' });
                reveals.forEach(el => revealObserver.observe(el));
            } else {
                reveals.forEach(el => el.classList.add('is-revealed'));
            }
        }

        // Contact Form AJAX Submission
        const contactForm = document.getElementById('contactForm');
        if (contactForm) {
            contactForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const form = this;
                const submitBtn = document.getElementById('submitBtn');
                const formMessage = document.getElementById('formMessage');
                const originalBtnText = submitBtn.innerHTML;
                
                formMessage.classList.add('d-none');
                form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                form.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
                
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
                
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
                
                const formData = new FormData(form);
                
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
                    formMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                });
            });
        }

        function showFieldError(fieldId, message) {
            const field = document.getElementById(fieldId);
            if (field) {
                field.classList.add('is-invalid');
                const feedback = field.nextElementSibling;
                if (feedback && feedback.classList.contains('invalid-feedback')) {
                    feedback.textContent = message;
                }
            }
        }
        
        function isValidEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
    });
    </script>

    <!-- GA4 Tracking -->
    <script>
    <?php
    if (file_exists(__DIR__ . '/helpers/GA4Helper.php')) {
        require_once 'helpers/GA4Helper.php';
        if (class_exists('GA4Helper')) {
            try {
                $pageTitle = 'Contact Us - ' . $brandName;
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

</body>
</html>
