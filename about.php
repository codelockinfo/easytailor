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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">

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

        html, body {
            overflow-x: clip;
            scroll-padding-top: 85px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8fafc;
            color: var(--ab-body);
            line-height: 1.7;
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
        .ab-hub-img-wrap {
            position: relative;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.35), 0 0 0 1px rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.05);
            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.4s ease;
        }
        .ab-hub-img-wrap:hover {
            transform: translateY(-4px);
            box-shadow: 0 22px 50px rgba(0, 0, 0, 0.45), 0 0 0 1px rgba(255, 255, 255, 0.25);
        }
        .ab-hub-img {
            width: 100%;
            height: 100%;
            max-height: 290px;
            object-fit: cover;
            display: block;
            transition: transform 0.6s ease;
        }
        .ab-hub-img-wrap:hover .ab-hub-img {
            transform: scale(1.04);
        }
        .ab-hub-img-badge {
            position: absolute;
            bottom: 14px;
            left: 14px;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            color: #ffffff;
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.78rem;
            font-weight: 700;
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }

        /* --- Final Call To Action (#final-cta from Home Page) --- */
        .final-cta-section {
            padding: 35px 0 0 0 !important;
            background: #ffffff !important;
            width: 100% !important;
            overflow: visible !important;
        }

        .final-cta-card {
            width: 100% !important;
            min-height: 220px !important;
            background: linear-gradient(105deg, #a192f7 0%, #7672ea 22%, #625fe5 45%, #544ad7 72%, #4a38c2 100%) !important;
            border-radius: 0 !important;
            padding: 0 !important;
            position: relative !important;
            overflow: visible !important;
            box-shadow: none !important;
            margin: 0 !important;
        }

        .final-cta-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 420px;
            height: 100%;
            border-radius: 0 !important;
            background: radial-gradient(circle at 85% 15%, rgba(255, 255, 255, 0.12), transparent 50%),
                        radial-gradient(circle at 90% 90%, rgba(55, 41, 168, 0.45), transparent 60%);
            pointer-events: none;
            z-index: 1;
        }

        .final-cta-inner {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            min-height: 220px !important;
            display: grid !important;
            grid-template-columns: minmax(340px, 460px) 1fr auto !important;
            align-items: stretch !important;
            gap: 20px !important;
            position: relative !important;
            z-index: 2 !important;
            padding: 0 clamp(24px, 3.5vw, 60px) 0 0 !important;
        }

        .final-cta-visual {
            position: relative !important;
            display: flex !important;
            align-items: flex-end !important;
            justify-content: flex-start !important;
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            line-height: 0 !important;
            height: 100% !important;
            overflow: visible !important;
        }

        .final-cta-visual img {
            height: calc(100% + 22px) !important;
            max-height: 260px !important;
            min-height: 220px !important;
            width: auto !important;
            max-width: 100% !important;
            object-fit: contain !important;
            object-position: bottom left !important;
            margin-top: -22px !important;
            margin-bottom: 0 !important;
            display: block !important;
            border-radius: 0 !important;
            user-select: none !important;
            pointer-events: none !important;
            filter: drop-shadow(0 10px 24px rgba(0, 0, 0, 0.14)) !important;
        }

        .final-cta-content {
            padding: 30px 16px !important;
            text-align: center !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .final-cta-eyebrow {
            font-size: 12px !important;
            font-weight: 700 !important;
            letter-spacing: 1.8px !important;
            text-transform: uppercase !important;
            color: rgba(255, 255, 255, 0.92) !important;
            margin-bottom: 8px !important;
            text-align: center !important;
        }

        .final-cta-title {
            font-family: 'Playfair Display', Georgia, serif !important;
            font-size: clamp(23px, 2.3vw, 29px) !important;
            font-weight: 700 !important;
            color: #ffffff !important;
            margin-bottom: 10px !important;
            line-height: 1.28 !important;
            letter-spacing: -0.2px !important;
            text-align: center !important;
        }

        .final-cta-desc {
            font-size: 13.5px !important;
            color: rgba(255, 255, 255, 0.9) !important;
            line-height: 1.55 !important;
            margin: 0 auto !important;
            max-width: 510px !important;
            text-align: center !important;
        }

        .final-cta-actions {
            display: flex !important;
            flex-direction: column !important;
            align-items: flex-start !important;
            justify-content: center !important;
            gap: 14px !important;
            padding: 24px 0 !important;
        }

        .final-cta-btns {
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            flex-wrap: nowrap !important;
        }

        .btn-cta-white {
            background: #ffffff !important;
            color: #4338ca !important;
            border: 1.5px solid #ffffff !important;
            border-radius: 10px !important;
            padding: 10px 20px !important;
            font-size: 13.5px !important;
            font-weight: 700 !important;
            text-decoration: none !important;
            display: inline-flex !important;
            align-items: center !important;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12) !important;
            transition: all 0.2s ease !important;
            white-space: nowrap !important;
        }

        .btn-cta-white:hover {
            background: #f8fafc !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.16) !important;
            color: #3730a3 !important;
        }

        .btn-cta-white i {
            color: #4f46e5 !important;
            font-size: 14px !important;
        }

        .btn-cta-transparent {
            background: rgba(255, 255, 255, 0.1) !important;
            color: #ffffff !important;
            border: 1.5px solid rgba(255, 255, 255, 0.5) !important;
            border-radius: 10px !important;
            padding: 10px 20px !important;
            font-size: 13.5px !important;
            font-weight: 600 !important;
            text-decoration: none !important;
            display: inline-flex !important;
            align-items: center !important;
            transition: all 0.2s ease !important;
            white-space: nowrap !important;
        }

        .btn-cta-transparent:hover {
            background: rgba(255, 255, 255, 0.2) !important;
            border-color: rgba(255, 255, 255, 0.85) !important;
            transform: translateY(-2px) !important;
            color: #ffffff !important;
        }

        .final-cta-checklist {
            display: flex !important;
            flex-direction: column !important;
            gap: 7px !important;
            font-size: 12.5px !important;
            color: rgba(255, 255, 255, 0.92) !important;
            font-weight: 500 !important;
            margin: 0 !important;
            padding: 0 !important;
            list-style: none !important;
        }

        .final-cta-checklist li {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            line-height: 1.3 !important;
        }

        .final-cta-checklist li i {
            font-size: 13px !important;
            color: rgba(255, 255, 255, 0.9) !important;
        }

        @media (max-width: 1199px) {
            .final-cta-inner {
                grid-template-columns: minmax(280px, 360px) 1fr auto !important;
                gap: 16px !important;
                padding: 0 24px 0 0 !important;
                min-height: 205px !important;
            }
            .final-cta-card {
                min-height: 205px !important;
            }
            .final-cta-content {
                padding: 24px 12px !important;
            }
            .final-cta-visual img {
                height: calc(100% + 18px) !important;
                max-height: 235px !important;
                margin-top: -18px !important;
            }
            .final-cta-title {
                font-size: 22px !important;
            }
            .final-cta-desc {
                font-size: 13px !important;
                max-width: 440px !important;
            }
            .btn-cta-white,
            .btn-cta-transparent {
                padding: 9px 16px !important;
                font-size: 13px !important;
            }
        }

        @media (max-width: 991px) {
            .final-cta-section {
                padding: 25px 0 0 0 !important;
            }
            .final-cta-card {
                padding: 0 !important;
                border-radius: 0 !important;
            }
            .final-cta-inner {
                grid-template-columns: 1fr !important;
                text-align: center !important;
                gap: 20px !important;
                padding: 24px 18px 28px 18px !important;
            }
            .final-cta-visual {
                justify-content: center !important;
                margin-top: -15px !important;
                height: auto !important;
                align-items: center !important;
            }
            .final-cta-visual img {
                height: auto !important;
                max-height: 180px !important;
                width: auto !important;
                max-width: 280px !important;
                margin: 0 auto !important;
                object-position: center !important;
                border-radius: 0 !important;
            }
            .final-cta-content {
                padding: 0 !important;
            }
            .final-cta-desc {
                margin: 0 auto !important;
            }
            .final-cta-title {
                font-size: 22px !important;
            }
            .final-cta-actions {
                align-items: center !important;
                padding: 0 !important;
            }
            .final-cta-btns {
                justify-content: center !important;
                flex-wrap: wrap !important;
            }
            .final-cta-checklist {
                align-items: center !important;
            }
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
                    <div class="col-lg-7">
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
                    <div class="col-lg-5 mt-4 mt-lg-0">
                        <div class="ab-hub-img-wrap">
                            <img src="assets/images/about/surat-textile-tech.jpg" 
                                 alt="TailorPro - Built in Surat, Gujarat - India's Textile Hub" 
                                 class="ab-hub-img"
                                 loading="lazy">
                            <div class="ab-hub-img-badge">
                                <i class="fas fa-sparkles text-warning"></i> Textile &amp; SaaS Innovation
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Final CTA Section (From Home Page) -->
    <section id="final-cta" class="final-cta-section ab-reveal">
        <div class="final-cta-card">
            <div class="final-cta-inner">
                <div class="final-cta-visual">
                    <img src="assets/images/cta-tailor-3d.png?v=7" alt="Tailor Management Platform" class="final-cta-img">
                </div>
                <div class="final-cta-content">
                    <div class="final-cta-eyebrow">READY TO GROW YOUR BUSINESS?</div>
                    <h2 class="final-cta-title">Take Your Tailoring Business to the Next Level</h2>
                    <p class="final-cta-desc">
                        Join thousands of successful tailoring businesses that have already digitalized their operations. Start your free trial today and see the difference.
                    </p>
                </div>
                <div class="final-cta-actions">
                    <div class="final-cta-btns">
                        <a href="admin/register.php" class="btn btn-cta-white">
                            <i class="fa-solid fa-shapes me-2"></i>Register Your Business
                        </a>
                        <a href="admin/login.php" class="btn btn-cta-transparent">
                            <i class="fa-solid fa-arrow-right-to-bracket me-2"></i>Login Now
                        </a>
                    </div>
                    <ul class="final-cta-checklist">
                        <li><i class="fa-regular fa-circle-check"></i> Free to use</li>
                        <li><i class="fa-regular fa-circle-check"></i> No credit card required</li>
                        <li><i class="fa-solid fa-circle-info"></i> Start in minutes</li>
                    </ul>
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
