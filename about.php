<?php
if (file_exists(__DIR__ . '/config/config.php')) {
    require_once __DIR__ . '/config/config.php';
} else {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

$page_title = 'About Us';

if (file_exists(__DIR__ . '/helpers/SEOHelper.php')) {
    require_once 'helpers/SEOHelper.php';
}

$baseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$canonicalUrl = $baseUrl . '/about';
$brandName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : (defined('APP_NAME') ? APP_NAME : 'TailorPro');

$seoOptions = [
    'title' => 'About Us - ' . $brandName . ' | Smart Tailoring Management System',
    'description' => 'Discover how ' . $brandName . ' by Codelock Solutions is revolutionizing tailor shops, boutiques, and fashion designers across India with smart digital measurement books, order tracking, and WhatsApp billing.',
    'keywords' => 'about us, TailorPro story, tailoring management software, digital tailor shop, boutique management software, tailor CRM, Codelock Solutions Surat',
    'canonical' => $canonicalUrl,
    'og_type' => 'article',
    'structured_data' => [
        "@context" => "https://schema.org",
        "@type" => "AboutPage",
        "name" => "About Us - " . $brandName,
        "description" => "Empowering tailor shops and boutiques with modern cloud technology",
        "url" => $canonicalUrl,
        "mainEntity" => [
            "@type" => "Organization",
            "name" => $brandName,
            "legalName" => "Codelock Solutions",
            "url" => $baseUrl,
            "logo" => $baseUrl . "/uploads/logos/main-logo.png",
            "foundingDate" => "2020",
            "address" => [
                "@type" => "PostalAddress",
                "streetAddress" => "Silver Business Point, Near VIP Circle, Utran",
                "addressLocality" => "Surat",
                "addressRegion" => "Gujarat",
                "postalCode" => "394105",
                "addressCountry" => "IN"
            ],
            "contactPoint" => [
                "@type" => "ContactPoint",
                "telephone" => "+917600464414",
                "contactType" => "Customer Support",
                "email" => "codelockinfo@gmail.com"
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
            --ab-primary: #4f46e5;
            --ab-primary-hover: #4338ca;
            --ab-secondary: #764ba2;
            --ab-accent: #8b5cf6;
            --ab-dark: #0f172a;
            --ab-heading: #1e1b4b;
            --ab-body: #475569;
            --ab-light: #f8fafc;
            --ab-card-border: #e2e8f0;
            --ab-radius: 18px;
            --ab-gradient: linear-gradient(135deg, #4f46e5 0%, #667eea 50%, #764ba2 100%);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8fafc;
            color: var(--ab-body);
            line-height: 1.7;
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--ab-heading);
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        /* --- Scroll Reveal Animations --- */
        .ab-reveal {
            opacity: 0;
            transform: translateY(32px);
            transition: opacity 0.75s cubic-bezier(0.16, 1, 0.3, 1), transform 0.75s cubic-bezier(0.16, 1, 0.3, 1);
            will-change: opacity, transform;
        }
        .ab-reveal.is-revealed {
            opacity: 1;
            transform: translateY(0);
        }

        /* Stagger Delays */
        .ab-delay-1 { transition-delay: 0.12s; }
        .ab-delay-2 { transition-delay: 0.24s; }
        .ab-delay-3 { transition-delay: 0.36s; }
        .ab-delay-4 { transition-delay: 0.48s; }

        /* Floating 3D Image Animation */
        @keyframes abFloat {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
            }
            50% {
                transform: translateY(-12px) rotate(1deg);
            }
        }
        .ab-floating-img {
            animation: abFloat 4.5s ease-in-out infinite;
        }

        /* Hero Header */
        .ab-hero {
            background: var(--ab-gradient);
            color: #ffffff;
            padding: 65px 0 80px;
            position: relative;
            overflow: hidden;
        }
        .ab-hero::before {
            content: '';
            position: absolute;
            top: -40%;
            right: -10%;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.16) 0%, rgba(255, 255, 255, 0) 70%);
            pointer-events: none;
            animation: abPulseGlow 8s ease-in-out infinite alternate;
        }
        .ab-hero::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -5%;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.12) 0%, rgba(255, 255, 255, 0) 70%);
            pointer-events: none;
            animation: abPulseGlow 10s ease-in-out infinite alternate;
        }

        @keyframes abPulseGlow {
            0% { transform: scale(1); opacity: 0.7; }
            100% { transform: scale(1.15); opacity: 1; }
        }

        .ab-hero .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 1.25rem;
        }
        .ab-hero .breadcrumb-item a {
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .ab-hero .breadcrumb-item a:hover {
            color: #ffffff;
            text-decoration: underline;
        }
        .ab-hero .breadcrumb-item.active {
            color: rgba(255, 255, 255, 0.95);
            font-size: 0.9rem;
        }
        .ab-hero .breadcrumb-item + .breadcrumb-item::before {
            color: rgba(255, 255, 255, 0.6);
        }

        .ab-badge {
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
            animation: abBadgeGlow 3.5s ease-in-out infinite;
        }

        @keyframes abBadgeGlow {
            0%, 100% {
                box-shadow: 0 0 12px rgba(255, 255, 255, 0.2);
            }
            50% {
                box-shadow: 0 0 25px rgba(255, 255, 255, 0.45);
            }
        }

        .ab-hero h1 {
            color: #ffffff;
            font-size: 2.85rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 1rem;
        }
        .ab-hero p.lead {
            color: rgba(255, 255, 255, 0.92);
            font-size: 1.15rem;
            max-width: 820px;
            margin-bottom: 2rem;
        }

        /* Stats Strip Inside Hero */
        .ab-hero-stats {
            background: rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 18px;
            padding: 22px 26px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-top: 25px;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.12);
        }
        .ab-stat-item {
            text-align: center;
            border-right: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
        }
        .ab-stat-item:last-child {
            border-right: none;
        }
        .ab-stat-num {
            font-size: 2.3rem;
            font-weight: 800;
            color: #ffffff;
            display: inline-block;
            line-height: 1.1;
            font-family: 'Plus Jakarta Sans', sans-serif;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            letter-spacing: -0.02em;
        }
        .ab-stat-label {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            margin-top: 5px;
            display: block;
        }

        /* Section Styling */
        .ab-section {
            padding: 70px 0;
            position: relative;
        }
        .ab-section-white {
            background-color: #ffffff;
        }
        .ab-section-light {
            background-color: #f8fafc;
        }

        .ab-section-header {
            text-align: center;
            max-width: 720px;
            margin: 0 auto 50px;
        }
        .ab-section-tag {
            color: var(--ab-primary);
            background: rgba(79, 70, 229, 0.08);
            border: 1px solid rgba(79, 70, 229, 0.2);
            padding: 5px 14px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            margin-bottom: 12px;
        }
        .ab-section-title {
            font-size: 2.15rem;
            font-weight: 800;
            color: var(--ab-heading);
            margin-bottom: 12px;
        }
        .ab-section-subtitle {
            font-size: 1rem;
            color: #64748b;
            margin-bottom: 0;
        }

        /* Story Cards */
        .ab-story-card {
            background: #ffffff;
            border: 1px solid var(--ab-card-border);
            border-radius: var(--ab-radius);
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            height: 100%;
        }
        .ab-story-card p {
            font-size: 1.02rem;
            color: #475569;
            margin-bottom: 1.25rem;
        }

        /* Comparison Box */
        .ab-comparison-box {
            background: #ffffff;
            border: 1px solid var(--ab-card-border);
            border-radius: var(--ab-radius);
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.05);
            height: 100%;
        }
        .ab-comp-header {
            padding: 20px 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.05rem;
        }
        .ab-comp-header.old {
            background: #fff1f2;
            color: #be123c;
            border-bottom: 1px solid #fecdd3;
        }
        .ab-comp-header.new {
            background: #f0fdf4;
            color: #15803d;
            border-bottom: 1px solid #bbf7d0;
        }
        .ab-comp-list {
            padding: 20px 24px;
            list-style: none;
            margin: 0;
        }
        .ab-comp-list li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 14px;
            font-size: 0.94rem;
        }
        .ab-comp-list li:last-child {
            margin-bottom: 0;
        }
        .ab-comp-list.old li i {
            color: #f43f5e;
            margin-top: 4px;
        }
        .ab-comp-list.new li i {
            color: #10b981;
            margin-top: 4px;
        }

        /* Mission & Vision Dual Cards */
        .ab-mv-card {
            background: #ffffff;
            border: 1px solid var(--ab-card-border);
            border-radius: var(--ab-radius);
            padding: 38px 34px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            height: 100%;
            position: relative;
            transition: transform 0.35s ease, box-shadow 0.35s ease;
        }
        .ab-mv-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 40px rgba(79, 70, 229, 0.12);
        }
        .ab-mv-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 30px;
            right: 30px;
            height: 4px;
            border-radius: 0 0 8px 8px;
        }
        .ab-mv-card.mission::before {
            background: linear-gradient(90deg, #4f46e5, #667eea);
        }
        .ab-mv-card.vision::before {
            background: linear-gradient(90deg, #764ba2, #8b5cf6);
        }
        .ab-mv-icon {
            width: 58px;
            height: 58px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 22px;
            transition: transform 0.3s ease;
        }
        .ab-mv-card:hover .ab-mv-icon {
            transform: scale(1.1) rotate(5deg);
        }
        .ab-mv-card.mission .ab-mv-icon {
            background: rgba(79, 70, 229, 0.1);
            color: var(--ab-primary);
        }
        .ab-mv-card.vision .ab-mv-icon {
            background: rgba(118, 75, 162, 0.1);
            color: var(--ab-secondary);
        }
        .ab-mv-card h3 {
            font-size: 1.45rem;
            margin-bottom: 12px;
        }
        .ab-mv-card p {
            font-size: 1rem;
            color: #475569;
            line-height: 1.8;
            margin-bottom: 0;
        }

        /* Pillars Grid */
        .ab-pillar-card {
            background: #ffffff;
            border: 1px solid var(--ab-card-border);
            border-radius: 16px;
            padding: 30px 24px;
            height: 100%;
            transition: all 0.35s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            text-align: left;
        }
        .ab-pillar-card:hover {
            transform: translateY(-6px);
            border-color: #cbd5e1;
            box-shadow: 0 16px 35px rgba(79, 70, 229, 0.1);
        }
        .ab-pillar-icon {
            width: 54px;
            height: 54px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 18px;
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            color: var(--ab-primary);
            transition: transform 0.3s ease;
        }
        .ab-pillar-card:hover .ab-pillar-icon {
            transform: scale(1.12) rotate(6deg);
        }
        .ab-pillar-card h4 {
            font-size: 1.15rem;
            margin-bottom: 10px;
            color: var(--ab-heading);
        }
        .ab-pillar-card p {
            font-size: 0.92rem;
            color: #64748b;
            line-height: 1.6;
            margin: 0;
        }

        /* Core Values */
        .ab-value-card {
            background: #ffffff;
            border: 1px solid var(--ab-card-border);
            border-radius: 14px;
            padding: 28px 22px;
            height: 100%;
            text-align: center;
            transition: transform 0.35s ease, box-shadow 0.35s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        }
        .ab-value-card:hover {
            transform: translateY(-5px);
            border-color: #cbd5e1;
            box-shadow: 0 14px 30px rgba(0, 0, 0, 0.08);
        }
        .ab-value-icon {
            width: 62px;
            height: 62px;
            margin: 0 auto 16px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.12) 0%, rgba(139, 92, 246, 0.12) 100%);
            color: var(--ab-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            transition: transform 0.3s ease;
        }
        .ab-value-card:hover .ab-value-icon {
            transform: scale(1.12);
        }
        .ab-value-card h4 {
            font-size: 1.1rem;
            margin-bottom: 8px;
        }
        .ab-value-card p {
            font-size: 0.88rem;
            color: #64748b;
            margin: 0;
            line-height: 1.6;
        }

        /* Hub / Surat Badge Section */
        .ab-hub-box {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
            color: #ffffff;
            border-radius: var(--ab-radius);
            padding: 48px 42px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 45px rgba(30, 27, 75, 0.18);
        }
        .ab-hub-box h3 {
            color: #ffffff;
            font-size: 1.85rem;
            margin-bottom: 12px;
        }
        .ab-hub-box p {
            color: rgba(255, 255, 255, 0.88);
            font-size: 1.02rem;
            max-width: 650px;
            line-height: 1.7;
        }
        .ab-hub-badge {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 8px;
            padding: 8px 16px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            margin-bottom: 18px;
        }

        /* CTA Section */
        .ab-cta-section {
            background: var(--ab-gradient);
            border-radius: var(--ab-radius);
            padding: 60px 45px;
            color: #ffffff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(79, 70, 229, 0.25);
        }
        .ab-cta-section h2 {
            color: #ffffff;
            font-size: 2.35rem;
            font-weight: 800;
            margin-bottom: 14px;
        }
        .ab-cta-section p {
            color: rgba(255, 255, 255, 0.92);
            font-size: 1.1rem;
            max-width: 620px;
            margin-bottom: 25px;
        }
        .ab-btn-light {
            background: #ffffff;
            color: var(--ab-primary) !important;
            border: none;
            padding: 14px 32px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.25s ease;
            text-decoration: none;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        .ab-btn-light:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.25);
            background: #f8fafc;
            color: var(--ab-primary-hover) !important;
        }
        .ab-btn-transparent {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff !important;
            border: 1.5px solid rgba(255, 255, 255, 0.5);
            padding: 14px 28px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.25s ease;
            text-decoration: none;
        }
        .ab-btn-transparent:hover {
            background: rgba(255, 255, 255, 0.25);
            color: #ffffff !important;
            transform: translateY(-3px);
        }

        @media (max-width: 991px) {
            .ab-hero {
                padding: 45px 0 55px;
            }
            .ab-hero h1 {
                font-size: 2rem;
            }
            .ab-hero-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
            }
            .ab-stat-item {
                border-right: none;
                border-bottom: 1px solid rgba(255, 255, 255, 0.15);
                padding-bottom: 12px;
            }
            .ab-stat-item:nth-last-child(-n+2) {
                border-bottom: none;
                padding-bottom: 0;
            }
            .ab-section {
                padding: 50px 0;
            }
            .ab-section-title {
                font-size: 1.85rem;
            }
            .ab-story-card, .ab-mv-card, .ab-hub-box, .ab-cta-section {
                padding: 30px 24px;
            }
            .ab-cta-section h2 {
                font-size: 1.75rem;
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
    <header class="ab-hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-10 mx-auto text-center text-lg-start">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center justify-content-lg-start">
                            <li class="breadcrumb-item"><a href="./"><i class="fas fa-home me-1"></i>Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">About Us</li>
                        </ol>
                    </nav>

                    <div class="ab-badge">
                        <i class="fas fa-sparkles"></i> Transforming Tailoring Businesses Since 2020
                    </div>

                    <h1>Empowering Tailors & Boutiques Through Modern Technology</h1>
                    <p class="lead">
                        Developed by <strong>Codelock Solutions</strong> in Surat — the textile capital of India — <strong><?php echo htmlspecialchars($brandName); ?></strong> is the complete digital operating system built specifically for tailor shops, fashion boutiques, and bespoke apparel designers.
                    </p>

                    <!-- Animated Stats Strip -->
                    <div class="ab-hero-stats" id="abHeroStats">
                        <div class="ab-stat-item">
                            <span class="ab-stat-num" data-count="1500" data-suffix="+">0</span>
                            <span class="ab-stat-label">Tailor Shops & Boutiques</span>
                        </div>
                        <div class="ab-stat-item">
                            <span class="ab-stat-num" data-count="250000" data-suffix="+">0</span>
                            <span class="ab-stat-label">Customer Measurements</span>
                        </div>
                        <div class="ab-stat-item">
                            <span class="ab-stat-num" data-count="500000" data-suffix="+">0</span>
                            <span class="ab-stat-label">WhatsApp Alerts & Bills</span>
                        </div>
                        <div class="ab-stat-item">
                            <span class="ab-stat-num" data-count="99.9" data-decimals="1" data-suffix="%">0</span>
                            <span class="ab-stat-label">Cloud Uptime & Security</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Section 1: Our Story & Comparison -->
    <section class="ab-section ab-section-white">
        <div class="container">
            <div class="row g-4 align-items-stretch">
                <div class="col-lg-7">
                    <div class="ab-story-card ab-reveal">
                        <span class="ab-section-tag">Our Story</span>
                        <h2 class="ab-section-title">Born from Real Tailor Shop Challenges</h2>
                        <p>
                            For decades, tailoring has been an art of precision, creativity, and deep craftsmanship. Yet behind the scenes, tailors faced immense daily stress: thick paper measurement diaries that got torn or misplaced, handwritten receipts that got lost, endless customer phone calls asking <em>"Masterji, kapda tayar hua kya?"</em>, and confusing manual math with worker advances.
                        </p>
                        <p>
                            In 2020, our founding team in <strong>Surat, Gujarat</strong> spent months sitting inside tailor shops, boutique studios, and stitching workshops to observe their exact workflow. We realized that while enterprise ERPs existed for large clothing factories, the local master tailor had zero tools designed for their unique day-to-day realities.
                        </p>
                        <p class="mb-0">
                            That spark led to the creation of <strong><?php echo htmlspecialchars($brandName); ?></strong> — an intuitive, mobile-friendly platform where any tailor can record measurements in seconds, send instant PDF bills with payment reminders on WhatsApp, and manage their karigars without a single sheet of paper.
                        </p>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="ab-comparison-box ab-reveal ab-delay-1">
                        <div class="ab-comp-header old">
                            <i class="fas fa-times-circle"></i>
                            <span>Traditional Paper Shop</span>
                        </div>
                        <ul class="ab-comp-list old">
                            <li><i class="fas fa-times"></i> <span>Lost or coffee-stained physical diaries</span></li>
                            <li><i class="fas fa-times"></i> <span>Manual search taking 10-15 minutes per customer</span></li>
                            <li><i class="fas fa-times"></i> <span>Missed promised delivery dates & angry clients</span></li>
                            <li><i class="fas fa-times"></i> <span>Disputes over karigar piece-rate work & advances</span></li>
                        </ul>

                        <div class="ab-comp-header new mt-3">
                            <i class="fas fa-check-circle"></i>
                            <span>Smart <?php echo htmlspecialchars($brandName); ?> Shop</span>
                        </div>
                        <ul class="ab-comp-list new">
                            <li><i class="fas fa-check"></i> <span>Lifetime cloud backup of all client measurements</span></li>
                            <li><i class="fas fa-check"></i> <span>1-Second customer lookup by phone number</span></li>
                            <li><i class="fas fa-check"></i> <span>Automated WhatsApp trial & delivery notifications</span></li>
                            <li><i class="fas fa-check"></i> <span>Automated karigar piecework hisab & professional bills</span></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section 2: Mission & Vision -->
    <section class="ab-section ab-section-light">
        <div class="container">
            <div class="ab-section-header ab-reveal">
                <span class="ab-section-tag">Purpose & Horizon</span>
                <h2 class="ab-section-title">Our Mission & Vision</h2>
                <p class="ab-section-subtitle">Guiding every line of code we write and every feature we deliver.</p>
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="ab-mv-card mission ab-reveal ab-delay-1">
                        <div class="ab-mv-icon">
                            <i class="fas fa-bullseye"></i>
                        </div>
                        <h3>Our Mission</h3>
                        <p>
                            To empower tailoring businesses of all sizes — from neighborhood boutique artisans to multi-branch tailoring houses — with professional, user-friendly digital tools that eliminate paperwork, elevate customer trust, and unlock sustainable business growth.
                        </p>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="ab-mv-card vision ab-reveal ab-delay-2">
                        <div class="ab-mv-icon">
                            <i class="fas fa-eye"></i>
                        </div>
                        <h3>Our Vision</h3>
                        <p>
                            To become the world's most trusted digital ecosystem for custom apparel and bespoke tailoring, bridging master craftsmen, fashion designers, fabric retailers, and end-customers through elegant, accessible cloud software.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section 3: Platform Pillars -->
    <section class="ab-section ab-section-white">
        <div class="container">
            <div class="ab-section-header ab-reveal">
                <span class="ab-section-tag">What We Offer</span>
                <h2 class="ab-section-title">The Complete Tailoring OS</h2>
                <p class="ab-section-subtitle">Everything you need to run, scale, and automate your tailor shop in one place.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="ab-pillar-card ab-reveal ab-delay-1">
                        <div class="ab-pillar-icon">
                            <i class="fas fa-ruler-combined"></i>
                        </div>
                        <h4>Digital Measurement Book</h4>
                        <p>Pre-configured sizing charts for Kurta, Blouse, Pant, Shirt, Sherwani, Lehenga, and Suits with design photo uploads.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="ab-pillar-card ab-reveal ab-delay-2">
                        <div class="ab-pillar-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <h4>Order & Trial Tracker</h4>
                        <p>Track every garment through its lifecycle: Booking &rarr; Cutting &rarr; Stitching &rarr; Trial Ready &rarr; Delivered.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="ab-pillar-card ab-reveal ab-delay-3">
                        <div class="ab-pillar-icon">
                            <i class="fab fa-whatsapp"></i>
                        </div>
                        <h4>WhatsApp Invoicing & Alerts</h4>
                        <p>Instantly send professional digital PDF receipts, advance confirmation, and pickup notices directly to customer WhatsApp.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="ab-pillar-card ab-reveal ab-delay-4">
                        <div class="ab-pillar-icon">
                            <i class="fas fa-user-cog"></i>
                        </div>
                        <h4>Karigar & Staff Accounting</h4>
                        <p>Manage daily wages, piece-rate stitching hisab, advances, pending balances, and role permissions for all workshop staff.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section 4: Our Core Values -->
    <section class="ab-section ab-section-light">
        <div class="container">
            <div class="ab-section-header ab-reveal">
                <span class="ab-section-tag">Core Principles</span>
                <h2 class="ab-section-title">The Values That Guide Us</h2>
                <p class="ab-section-subtitle">Building software tailors genuinely love and rely on every morning.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="ab-value-card ab-reveal ab-delay-1">
                        <div class="ab-value-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <h4>Artisan-Centric</h4>
                        <p>We listen to actual tailors. Every button, input box, and feature is shaped by master craftsmen's real feedback.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="ab-value-card ab-reveal ab-delay-2">
                        <div class="ab-value-icon">
                            <i class="fas fa-mobile-screen-button"></i>
                        </div>
                        <h4>Zero Complexity</h4>
                        <p>No technical skills required. If you know how to use WhatsApp, you can master <?php echo htmlspecialchars($brandName); ?> in under 5 minutes.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="ab-value-card ab-reveal ab-delay-3">
                        <div class="ab-value-icon">
                            <i class="fas fa-shield-halved"></i>
                        </div>
                        <h4>Uncompromising Security</h4>
                        <p>256-bit SSL encryption, automated daily cloud backups, and a strict guarantee: we never sell your shop or client data.</p>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="ab-value-card ab-reveal ab-delay-4">
                        <div class="ab-value-icon">
                            <i class="fas fa-handshake-angle"></i>
                        </div>
                        <h4>True Partnership</h4>
                        <p>We are not just a software vendor. We are your dedicated digital partner providing prompt support in Hindi and English.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section 5: Built in Surat & Codelock Solutions -->
    <section class="ab-section ab-section-white">
        <div class="container">
            <div class="ab-hub-box ab-reveal">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <div class="ab-hub-badge">
                            <i class="fas fa-map-marker-alt text-warning"></i>
                            <span>Proudly Built in Surat, Gujarat — India's Textile Hub</span>
                        </div>
                        <h3>Engineered by Codelock Solutions</h3>
                        <p>
                            Surat processes over 40% of India's man-made fabric. Living at the heart of this vibrant textile ecosystem gives us an unparalleled, first-hand understanding of tailoring workflows, fabric types, and business requirements.
                        </p>
                        <p class="mb-4">
                            Our engineering headquarters at <strong>Silver Business Point, Utran, Surat</strong> houses our development, security, and customer success teams dedicated to building reliable digital solutions for entrepreneurs.
                        </p>
                        <div class="d-flex flex-wrap gap-3">
                            <a href="contact" class="btn btn-sm btn-outline-light rounded-pill px-3 py-2">
                                <i class="fas fa-envelope me-1"></i> Contact Head Office
                            </a>
                            <a href="tel:+917600464414" class="btn btn-sm btn-outline-light rounded-pill px-3 py-2">
                                <i class="fas fa-phone me-1"></i> Call +91 76004 64414
                            </a>
                        </div>
                    </div>
                    <div class="col-lg-4 text-center mt-4 mt-lg-0">
                        <div class="p-3 bg-white bg-opacity-10 rounded-4 border border-white border-opacity-25 d-inline-block">
                            <img src="uploads/logos/main-logo.png" alt="TailorPro" class="img-fluid" style="max-height: 70px;" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                            <div style="display: none; font-size: 2rem; font-weight: 800; color: #fff;">
                                <i class="fas fa-cut me-2"></i>TailorPro
                            </div>
                            <div class="mt-2 text-white-50 small">
                                Crafting Tech for Modern Tailors
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section 6: CTA Section -->
    <section class="container mb-5">
        <div class="ab-cta-section text-center text-lg-start ab-reveal">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h2>Ready to Modernize Your Tailor Shop?</h2>
                    <p>
                        Join hundreds of successful tailors and boutique owners who have eliminated paper registers, reduced customer trial delays, and increased repeat orders with <?php echo htmlspecialchars($brandName); ?>.
                    </p>
                    <div class="d-flex flex-wrap gap-3 justify-content-center justify-content-lg-start">
                        <a href="admin/register.php" class="ab-btn-light">
                            <i class="fas fa-rocket"></i> Start Free Registration
                        </a>
                        <a href="contact" class="ab-btn-transparent">
                            <i class="fas fa-calendar-check"></i> Book a Free Demo
                        </a>
                    </div>
                </div>
                <div class="col-lg-4 text-center mt-4 mt-lg-0 d-none d-lg-block">
                    <img src="assets/images/cta-tailor-3d.png" alt="Tailor 3D" class="img-fluid ab-floating-img" style="max-height: 240px;" onerror="this.src='assets/images/person.png';">
                </div>
            </div>
        </div>
    </section>

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

    <!-- Number Counter & Scroll Animations Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // --- 1. Rolling Number Counter Animation (Starts from 0, 1, 2, 3...) ---
        function animateCounters() {
            const counters = document.querySelectorAll('.ab-stat-num[data-count]');
            const duration = 2400; // 2.4s smooth roll up
            
            counters.forEach(counter => {
                const target = parseFloat(counter.getAttribute('data-count'));
                const suffix = counter.getAttribute('data-suffix') || '';
                const decimals = parseInt(counter.getAttribute('data-decimals') || '0', 10);
                const startTime = performance.now();
                
                function step(currentTime) {
                    const elapsed = currentTime - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    
                    // Ease-out Quartic formula for smooth deceleration
                    const easeProgress = 1 - Math.pow(1 - progress, 4);
                    const currentVal = target * easeProgress;
                    
                    if (decimals > 0) {
                        counter.textContent = currentVal.toFixed(decimals) + suffix;
                    } else {
                        counter.textContent = Math.floor(currentVal).toLocaleString('en-US') + suffix;
                    }
                    
                    if (progress < 1) {
                        requestAnimationFrame(step);
                    } else {
                        // Ensure final exact value
                        if (decimals > 0) {
                            counter.textContent = target.toFixed(decimals) + suffix;
                        } else {
                            counter.textContent = target.toLocaleString('en-US') + suffix;
                        }
                    }
                }
                
                requestAnimationFrame(step);
            });
        }

        // Trigger Counter when stats section is in view
        const statsEl = document.getElementById('abHeroStats');
        if (statsEl) {
            let countersAnimated = false;
            if ('IntersectionObserver' in window) {
                const statsObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting && !countersAnimated) {
                            countersAnimated = true;
                            animateCounters();
                            statsObserver.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.15 });
                statsObserver.observe(statsEl);
            } else {
                animateCounters();
            }
        }

        // --- 2. Scroll Reveal Animations for Cards & Sections ---
        const reveals = document.querySelectorAll('.ab-reveal');
        if (reveals.length > 0) {
            if ('IntersectionObserver' in window) {
                const revealObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-revealed');
                            revealObserver.unobserve(entry.target);
                        }
                    });
                }, {
                    threshold: 0.08,
                    rootMargin: '0px 0px -30px 0px'
                });

                reveals.forEach(el => revealObserver.observe(el));
            } else {
                reveals.forEach(el => el.classList.add('is-revealed'));
            }
        }
    });
    </script>

    <!-- GA4 Tracking -->
    <script>
    <?php
    $pageViewCode = '// GA4Helper not available';
    if (file_exists(__DIR__ . '/helpers/GA4Helper.php')) {
        require_once 'helpers/GA4Helper.php';
        if (class_exists('GA4Helper')) {
            try {
                $pageTitle = 'About Us - ' . $brandName;
                $pageLocation = $canonicalUrl;
                $pageViewCode = GA4Helper::trackPageView($pageTitle, $pageLocation);
            } catch (Exception $e) {
                error_log('GA4Helper trackPageView error: ' . $e->getMessage());
                $pageViewCode = '// GA4Helper error';
            }
        }
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
                }
            }
        }
        firePageView();
    })();
    </script>

</body>
</html>
