<?php
if (file_exists(__DIR__ . '/config/config.php')) {
    require_once __DIR__ . '/config/config.php';
} else {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

$page_title = 'Privacy Policy';

if (file_exists(__DIR__ . '/helpers/SEOHelper.php')) {
    require_once 'helpers/SEOHelper.php';
}

$baseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$canonicalUrl = $baseUrl . '/privacy-policy';
$brandName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : (defined('APP_NAME') ? APP_NAME : 'TailorPro');

$seoOptions = [
    'title' => 'Privacy Policy - ' . $brandName . ' | Tailoring Management System',
    'description' => 'Read our comprehensive Privacy Policy to understand how ' . $brandName . ' collects, secures, and handles tailor shop, customer measurement, and billing data in compliance with data privacy regulations.',
    'keywords' => 'privacy policy, TailorPro privacy, tailoring management data protection, customer measurement security, tailor shop privacy, google adsense policy, dpdp act',
    'canonical' => $canonicalUrl,
    'og_type' => 'article'
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
    
    <link href="assets/css/style15.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="icon" type="image/x-icon" href="favicon.ico" sizes="16x16 32x32 48x48">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon(2).png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon(2).png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/favicon(2).png">
    
    <style>
        :root {
            --pp-primary: #667eea;
            --pp-secondary: #764ba2;
            --pp-accent: #8b5cf6;
            --pp-dark: #1e293b;
            --pp-body: #475569;
            --pp-light-bg: #f8fafc;
            --pp-card-border: #e2e8f0;
            --pp-radius: 16px;
        }

        html, body {
            overflow-x: clip;
            scroll-padding-top: 115px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8fafc;
            color: var(--pp-body);
            line-height: 1.7;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--pp-dark);
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        /* Hero Header */
        .pp-hero {
            background: linear-gradient(135deg, #4f46e5 0%, #667eea 50%, #764ba2 100%);
            color: #ffffff;
            padding: 55px 0 65px;
            position: relative;
            overflow: hidden;
        }
        .pp-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 450px;
            height: 450px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, rgba(255, 255, 255, 0) 70%);
            pointer-events: none;
        }
        .pp-hero::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -5%;
            width: 350px;
            height: 350px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 70%);
            pointer-events: none;
        }
        .pp-hero .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 1rem;
        }
        .pp-hero .breadcrumb-item a {
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .pp-hero .breadcrumb-item a:hover {
            color: #ffffff;
            text-decoration: underline;
        }
        .pp-hero .breadcrumb-item.active {
            color: rgba(255, 255, 255, 0.95);
            font-size: 0.9rem;
        }
        .pp-hero .breadcrumb-item + .breadcrumb-item::before {
            color: rgba(255, 255, 255, 0.6);
        }
        .pp-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.25);
        }
        .pp-hero h1 {
            color: #ffffff;
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
        }
        .pp-hero p.lead {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
            max-width: 720px;
            margin-bottom: 1.5rem;
        }
        .pp-meta-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        .pp-meta-pill {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #ffffff;
            font-size: 0.82rem;
            padding: 5px 12px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        /* Highlights Row */
        .pp-highlights {
            margin-top: -35px;
            position: relative;
            z-index: 10;
            margin-bottom: 40px;
        }
        .pp-highlight-card {
            background: #ffffff;
            border: 1px solid var(--pp-card-border);
            border-radius: 14px;
            padding: 22px 20px;
            height: 100%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }
        .pp-highlight-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.12);
        }
        .pp-highlight-icon {
            width: 48px;
            height: 48px;
            min-width: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.12) 0%, rgba(118, 75, 162, 0.12) 100%);
            color: var(--pp-primary);
        }
        .pp-highlight-card h4 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 4px;
            color: var(--pp-dark);
        }
        .pp-highlight-card p {
            font-size: 0.85rem;
            margin: 0;
            color: #64748b;
            line-height: 1.5;
        }

        /* Sticky Sidebar */
        @media (min-width: 992px) {
            aside.col-lg-4,
            aside.col-xl-3 {
                display: block !important;
                position: relative !important;
                align-self: stretch !important;
            }
            .pp-sidebar {
                position: -webkit-sticky !important;
                position: sticky !important;
                top: 115px !important;
                z-index: 100 !important;
                max-height: calc(100vh - 130px) !important;
                overflow-y: auto !important;
                scrollbar-width: thin;
                padding-right: 4px;
            }
            .pp-sidebar::-webkit-scrollbar {
                width: 4px;
            }
            .pp-sidebar::-webkit-scrollbar-thumb {
                background: #cbd5e1;
                border-radius: 4px;
            }
            .pp-toc-list {
                max-height: none !important;
                overflow-y: visible !important;
            }
        }
        @media (max-width: 991px) {
            .pp-sidebar {
                position: relative;
                top: 0;
            }
            .pp-toc-list {
                max-height: 240px;
                overflow-y: auto;
            }
        }
        .pp-toc-card {
            background: #ffffff;
            border: 1px solid var(--pp-card-border);
            border-radius: var(--pp-radius);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            padding: 20px;
            margin-bottom: 24px;
        }
        .pp-toc-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 12px;
            margin-bottom: 12px;
            border-bottom: 1px solid var(--pp-card-border);
        }
        .pp-toc-header h3 {
            font-size: 1rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--pp-dark);
        }
        .pp-toc-list {
            list-style: none;
            padding: 0;
            margin: 0;
            max-height: 420px;
            overflow-y: auto;
            scrollbar-width: thin;
        }
        .pp-toc-list::-webkit-scrollbar {
            width: 4px;
        }
        .pp-toc-list::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .pp-toc-item {
            margin-bottom: 4px;
        }
        .pp-toc-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-radius: 8px;
            color: #475569;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .pp-toc-link:hover {
            color: var(--pp-primary);
            background: rgba(102, 126, 234, 0.08);
            transform: translateX(3px);
        }
        .pp-toc-link.active {
            color: var(--pp-primary);
            background: rgba(102, 126, 234, 0.12);
            font-weight: 700;
            border-left: 3px solid var(--pp-primary);
        }
        .pp-toc-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 6px;
            background: #f1f5f9;
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .pp-toc-link.active .pp-toc-num {
            background: var(--pp-primary);
            color: #ffffff;
        }

        /* Help Box Widget */
        .pp-help-widget {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.08) 0%, rgba(118, 75, 162, 0.08) 100%);
            border: 1px dashed rgba(102, 126, 234, 0.35);
            border-radius: 14px;
            padding: 20px;
            text-align: center;
        }
        .pp-help-widget h4 {
            font-size: 0.95rem;
            margin-bottom: 6px;
        }
        .pp-help-widget p {
            font-size: 0.82rem;
            color: #64748b;
            margin-bottom: 14px;
        }

        /* Main Content Cards */
        .pp-section-card {
            background: #ffffff;
            border: 1px solid var(--pp-card-border);
            border-radius: var(--pp-radius);
            padding: 32px 30px;
            margin-bottom: 28px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            scroll-margin-top: 100px;
            transition: border-color 0.25s ease;
        }
        .pp-section-card:hover {
            border-color: #cbd5e1;
        }
        .pp-section-header {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 2px solid #f1f5f9;
        }
        .pp-section-badge {
            width: 42px;
            height: 42px;
            min-width: 42px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--pp-primary) 0%, var(--pp-secondary) 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            font-weight: 700;
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.25);
        }
        .pp-section-title {
            margin: 0;
            font-size: 1.35rem;
            color: var(--pp-dark);
            line-height: 1.3;
        }
        .pp-section-subtitle {
            font-size: 0.85rem;
            color: #64748b;
            margin: 3px 0 0;
        }

        .pp-section-card h3 {
            font-size: 1.1rem;
            margin-top: 1.5rem;
            margin-bottom: 0.6rem;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .pp-section-card h3 i {
            color: var(--pp-primary);
            font-size: 0.95rem;
        }

        .pp-section-card p {
            margin-bottom: 1rem;
            font-size: 0.95rem;
            color: #475569;
        }

        .pp-section-card ul {
            padding-left: 1.25rem;
            margin-bottom: 1.2rem;
        }
        .pp-section-card li {
            margin-bottom: 0.5rem;
            font-size: 0.93rem;
            color: #475569;
        }
        .pp-section-card li strong {
            color: #1e293b;
        }

        /* Custom Box Alerts */
        .pp-alert {
            border-radius: 12px;
            padding: 16px 18px;
            margin: 18px 0;
            font-size: 0.92rem;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .pp-alert-info {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }
        .pp-alert-success {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }
        .pp-alert-warning {
            background-color: #fffbeb;
            border: 1px solid #fef3c7;
            color: #92400e;
        }
        .pp-alert i {
            font-size: 1.15rem;
            margin-top: 2px;
        }

        /* Key Grid in Sections */
        .pp-grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            margin: 16px 0;
        }
        .pp-subcard {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px 18px;
        }
        .pp-subcard h4 {
            font-size: 0.95rem;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--pp-dark);
        }
        .pp-subcard p {
            font-size: 0.87rem;
            margin-bottom: 0;
            color: #64748b;
        }

        /* Tables */
        .pp-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 18px 0;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            font-size: 0.9rem;
        }
        .pp-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: 700;
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        .pp-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            color: #475569;
        }
        .pp-table tr:last-child td {
            border-bottom: none;
        }
        .pp-table tr:nth-child(even) td {
            background-color: #fafbfd;
        }

        /* Contact Details Card */
        .pp-contact-box {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            padding: 24px;
            margin-top: 20px;
        }
        .pp-contact-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 16px;
        }
        .pp-contact-item:last-child {
            margin-bottom: 0;
        }
        .pp-contact-icon {
            width: 38px;
            height: 38px;
            min-width: 38px;
            border-radius: 10px;
            background: #ffffff;
            color: var(--pp-primary);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        /* Action Buttons */
        .btn-pp-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff !important;
            border: none;
            border-radius: 10px;
            padding: 9px 18px;
            font-weight: 600;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.25s ease;
            text-decoration: none;
        }
        .btn-pp-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(102, 126, 234, 0.35);
            color: #ffffff;
        }

        .btn-pp-outline {
            background: #ffffff;
            color: var(--pp-primary) !important;
            border: 1.5px solid var(--pp-primary);
            border-radius: 10px;
            padding: 8px 16px;
            font-weight: 600;
            font-size: 0.88rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.25s ease;
            text-decoration: none;
        }
        .btn-pp-outline:hover {
            background: var(--pp-primary);
            color: #ffffff !important;
        }

        /* Mobile Adjustments */
        @media (max-width: 991px) {
            .pp-hero {
                padding: 40px 0 50px;
            }
            .pp-hero h1 {
                font-size: 1.85rem;
            }
            .pp-highlights {
                margin-top: -20px;
            }
            .pp-section-card {
                padding: 24px 20px;
            }
            .pp-sidebar {
                position: relative;
                top: 0;
                margin-bottom: 24px;
            }
            .pp-toc-list {
                max-height: 240px;
            }
        }

        /* Print styles */
        @media print {
            .pp-hero, .pp-highlights, .pp-sidebar, nav, footer, .go-to-top-btn, .whatsapp-button {
                display: none !important;
            }
            body {
                background: #ffffff;
                color: #000000;
            }
            .pp-section-card {
                border: none;
                box-shadow: none;
                padding: 10px 0;
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
    <header class="pp-hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-10 mx-auto text-center text-lg-start">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center justify-content-lg-start">
                            <li class="breadcrumb-item"><a href="./"><i class="fas fa-home me-1"></i>Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Privacy Policy</li>
                        </ol>
                    </nav>

                    <div class="pp-badge">
                        <i class="fas fa-shield-halved"></i> Data Protection & Privacy Commitment
                    </div>

                    <h1>Privacy Policy</h1>
                    <p class="lead">
                        Learn how <strong><?php echo htmlspecialchars($brandName); ?></strong> protects, respects, and securely manages your tailoring shop information, employee accounts, and customer measurement records.
                    </p>

                    <div class="pp-meta-tags justify-content-center justify-content-lg-start">
                        <span class="pp-meta-pill">
                            <i class="fas fa-calendar-check"></i> Last Updated: <?php echo date('F j, Y'); ?>
                        </span>
                        <span class="pp-meta-pill">
                            <i class="fas fa-code-branch"></i> Version: 2.2
                        </span>
                        <span class="pp-meta-pill">
                            <i class="fas fa-lock"></i> 256-bit SSL Protected
                        </span>
                        <span class="pp-meta-pill">
                            <i class="fas fa-balance-scale"></i> DPDP Act 2023 & GDPR Compliant
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Key Highlights Grid -->
    <div class="container pp-highlights">
        <div class="row g-3">
            <div class="col-md-6 col-lg-3">
                <div class="pp-highlight-card">
                    <div class="pp-highlight-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div>
                        <h4>Zero Data Selling</h4>
                        <p>We never sell, rent, or trade your tailoring business or customer records to third parties.</p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="pp-highlight-card">
                    <div class="pp-highlight-icon">
                        <i class="fas fa-tape"></i>
                    </div>
                    <div>
                        <h4>Protected Measurements</h4>
                        <p>Customer body measurements, designs, and cloth photos remain your strictly private assets.</p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="pp-highlight-card">
                    <div class="pp-highlight-icon">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <div>
                        <h4>Transactional Messaging</h4>
                        <p>WhatsApp and SMS gateways are strictly used for your order confirmations, bills & trial notices.</p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="pp-highlight-card">
                    <div class="pp-highlight-icon">
                        <i class="fas fa-trash-can"></i>
                    </div>
                    <div>
                        <h4>Right to Deletion</h4>
                        <p>You have full autonomy to export, modify, or permanently delete your account and records anytime.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Area with Sticky TOC -->
    <main class="container mb-5">
        <div class="row g-4">
            
            <!-- Sticky Sidebar: Table of Contents -->
            <aside class="col-lg-4 col-xl-3">
                <div class="pp-sidebar">
                    <div class="pp-toc-card">
                        <div class="pp-toc-header">
                            <h3><i class="fas fa-list-ol text-primary"></i> Contents</h3>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" onclick="window.print()" title="Print Policy">
                                <i class="fas fa-print"></i>
                            </button>
                        </div>
                        <ul class="pp-toc-list" id="ppTocList">
                            <li class="pp-toc-item">
                                <a href="#section-1" class="pp-toc-link">
                                    <span class="pp-toc-num">1</span>
                                    <span>Introduction & Scope</span>
                                </a>
                            </li>
                            <li class="pp-toc-item">
                                <a href="#section-2" class="pp-toc-link">
                                    <span class="pp-toc-num">2</span>
                                    <span>Information We Collect</span>
                                </a>
                            </li>
                            <li class="pp-toc-item">
                                <a href="#section-3" class="pp-toc-link">
                                    <span class="pp-toc-num">3</span>
                                    <span>How We Use Your Data</span>
                                </a>
                            </li>
                            <li class="pp-toc-item">
                                <a href="#section-4" class="pp-toc-link">
                                    <span class="pp-toc-num">4</span>
                                    <span>Google AdSense & Ads</span>
                                </a>
                            </li>
                            <li class="pp-toc-item">
                                <a href="#section-5" class="pp-toc-link">
                                    <span class="pp-toc-num">5</span>
                                    <span>Cookies & Analytics</span>
                                </a>
                            </li>
                            <li class="pp-toc-item">
                                <a href="#section-6" class="pp-toc-link">
                                    <span class="pp-toc-num">6</span>
                                    <span>WhatsApp & Notifications</span>
                                </a>
                            </li>
                            <li class="pp-toc-item">
                                <a href="#section-7" class="pp-toc-link">
                                    <span class="pp-toc-num">7</span>
                                    <span>Payments & Invoicing</span>
                                </a>
                            </li>
                            <li class="pp-toc-item">
                                <a href="#section-8" class="pp-toc-link">
                                    <span class="pp-toc-num">8</span>
                                    <span>Data Storage & Security</span>
                                </a>
                            </li>
                            <li class="pp-toc-item">
                                <a href="#section-9" class="pp-toc-link">
                                    <span class="pp-toc-num">9</span>
                                    <span>Third-Party Sharing</span>
                                </a>
                            </li>
                            <li class="pp-toc-item">
                                <a href="#section-10" class="pp-toc-link">
                                    <span class="pp-toc-num">10</span>
                                    <span>Data Retention Periods</span>
                                </a>
                            </li>
                            <li class="pp-toc-item">
                                <a href="#section-11" class="pp-toc-link">
                                    <span class="pp-toc-num">11</span>
                                    <span>Data & Account Deletion</span>
                                </a>
                            </li>
                            <li class="pp-toc-item">
                                <a href="#section-12" class="pp-toc-link">
                                    <span class="pp-toc-num">12</span>
                                    <span>Indian DPDP & GDPR Rights</span>
                                </a>
                            </li>
                            <li class="pp-toc-item">
                                <a href="#section-13" class="pp-toc-link">
                                    <span class="pp-toc-num">13</span>
                                    <span>Children's Privacy</span>
                                </a>
                            </li>
                            <li class="pp-toc-item">
                                <a href="#section-14" class="pp-toc-link">
                                    <span class="pp-toc-num">14</span>
                                    <span>Grievance & Contact Info</span>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- Quick Support Box -->
                    <div class="pp-help-widget">
                        <h4>Questions regarding your privacy?</h4>
                        <p>Our dedicated privacy officer is here to assist with any questions or deletion requests.</p>
                        <div class="d-grid gap-2">
                            <a href="https://mail.google.com/mail/?view=cm&fs=1&to=codelockinfo@gmail.com" target="_blank" class="btn btn-sm btn-pp-primary">
                                <i class="fas fa-envelope"></i> Email Privacy Team
                            </a>
                            <a href="https://wa.me/917600464414" target="_blank" class="btn btn-sm btn-pp-outline">
                                <i class="fab fa-whatsapp"></i> Chat on WhatsApp
                            </a>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Main Legal Sections -->
            <div class="col-lg-8 col-xl-9">

                <!-- 1. Introduction & Scope -->
                <section class="pp-section-card" id="section-1">
                    <div class="pp-section-header">
                        <div class="pp-section-badge">1</div>
                        <div>
                            <h2 class="pp-section-title">Introduction & Scope</h2>
                            <p class="pp-section-subtitle">Who we are and how this Privacy Policy applies to you</p>
                        </div>
                    </div>
                    <p>
                        Welcome to <strong><?php echo htmlspecialchars($brandName); ?></strong> ("Platform," "Software," "we," "us," or "our"), developed and operated by <strong>Codelock Solutions</strong>, located in Surat, Gujarat, India.
                    </p>
                    <p>
                        <?php echo htmlspecialchars($brandName); ?> provides an integrated, cloud-enabled Tailoring Business Management Platform designed to streamline tailor shop operations, including digital measurement tracking, customer communication, order progress, billing, staff workflow, and catalog management.
                    </p>
                    <p>
                        This Privacy Policy governs how we collect, store, process, transfer, and protect personal and operational information when:
                    </p>
                    <ul>
                        <li>Tailors, boutique owners, and enterprise administrators register and use our platform.</li>
                        <li>Shop staff, masters, and cutting/stitching artisans access assignments.</li>
                        <li>End-customers whose details (name, contact number, body measurements, and order specs) are entered into the platform by tailors.</li>
                        <li>Visitors browse our public websites, marketing pages, blogs, and documentation.</li>
                    </ul>
                    <div class="pp-alert pp-alert-info">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>Core Philosophy:</strong> We recognize that customer measurements and business orders are proprietary trade secrets of each tailor shop. We act primarily as a secure data custodian (data processor), leaving business ownership and rights in your hands.
                        </div>
                    </div>
                </section>

                <!-- 2. Information We Collect -->
                <section class="pp-section-card" id="section-2">
                    <div class="pp-section-header">
                        <div class="pp-section-badge">2</div>
                        <div>
                            <h2 class="pp-section-title">Information We Collect</h2>
                            <p class="pp-section-subtitle">Clear categorization of data collected across our platform</p>
                        </div>
                    </div>
                    <p>
                        Depending on how you interact with our platform, we collect the following categories of information:
                    </p>

                    <h3><i class="fas fa-store"></i> 2.1 Tailor & Business Account Data</h3>
                    <ul>
                        <li><strong>Account Details:</strong> Shop/Boutique Name, Owner/Proprietor Name, Email Address, Contact Phone/Mobile Number, and secure hashed login passwords.</li>
                        <li><strong>Business Profile:</strong> Shop physical address, city, state, postal code, shop logo, website, and GST/Tax ID (if voluntarily provided for custom invoices).</li>
                        <li><strong>Staff Accounts:</strong> Employee names, phone numbers, assigned roles (master, cutter, helper, manager), and permission levels.</li>
                    </ul>

                    <h3><i class="fas fa-ruler-combined"></i> 2.2 Customer & Measurement Records</h3>
                    <p>When shop owners create customer profiles and orders, the system stores:</p>
                    <ul>
                        <li>Customer full name, phone number, gender, delivery address, and notes.</li>
                        <li><strong>Garment Measurement Profiles:</strong> Quantitative measurements (e.g., chest, waist, hips, shoulder, sleeve length, inseam, neck, blouse depth, kurta lengths, pant folds, etc.).</li>
                        <li><strong>Design & Style Specifications:</strong> Pattern selections, neckline styles, collar types, embroidery preferences, lining materials, and sample cloth/reference photos uploaded by the tailor.</li>
                    </ul>

                    <h3><i class="fas fa-file-invoice-dollar"></i> 2.3 Order, Billing & Invoicing Records</h3>
                    <ul>
                        <li>Order numbers, booking date, trial date, promised delivery date, and order status (Pending, Cutting, Stitching, Ready, Delivered, Cancelled).</li>
                        <li>Item pricing, advance deposit paid, outstanding balance, discounts, and payment methods (Cash, UPI, Card, Net Banking).</li>
                        <li><em>Note:</em> We do <strong>not</strong> collect or store credit card numbers, CVVs, or banking PINs. All online payments are handled directly by PCI-DSS compliant third-party payment gateways.</li>
                    </ul>

                    <h3><i class="fas fa-desktop"></i> 2.4 Technical, Device & Log Data</h3>
                    <ul>
                        <li>IP address, browser type and version, device manufacturer, operating system, and language settings.</li>
                        <li>Access times, pages viewed, URL clickstreams, and diagnostic crash/error logs to maintain high platform uptime.</li>
                    </ul>
                </section>

                <!-- 3. How We Use Your Data -->
                <section class="pp-section-card" id="section-3">
                    <div class="pp-section-header">
                        <div class="pp-section-badge">3</div>
                        <div>
                            <h2 class="pp-section-title">How We Use Your Data</h2>
                            <p class="pp-section-subtitle">Legitimate purposes for processing data</p>
                        </div>
                    </div>
                    <p>We process information strictly for legitimate business and operational purposes, including:</p>
                    <div class="pp-grid-2">
                        <div class="pp-subcard">
                            <h4><i class="fas fa-cogs text-primary"></i> Service Operations</h4>
                            <p>To enable account registration, manage tailor orders, maintain digital measurement books, and track workshop workflows.</p>
                        </div>
                        <div class="pp-subcard">
                            <h4><i class="fas fa-receipt text-primary"></i> Invoice & Receipt Generation</h4>
                            <p>To generate branded digital and printable invoices, tax receipts, delivery slips, and measurement job cards.</p>
                        </div>
                        <div class="pp-subcard">
                            <h4><i class="fab fa-whatsapp text-success"></i> Customer Notifications</h4>
                            <p>To deliver automated order confirmations, trial readiness alerts, and delivery pickup reminders via WhatsApp/SMS.</p>
                        </div>
                        <div class="pp-subcard">
                            <h4><i class="fas fa-shield-virus text-danger"></i> Security & Fraud Prevention</h4>
                            <p>To verify accounts, prevent unauthorized logins, perform backups, and detect potential security breaches.</p>
                        </div>
                    </div>
                    <ul>
                        <li><strong>Customer Support:</strong> Assisting you via phone, WhatsApp, or email when you report issues or request setup help.</li>
                        <li><strong>System Improvement:</strong> Analyzing platform speed, usability, and popular features to engineer performance enhancements.</li>
                        <li><strong>Legal Compliance:</strong> Meeting statutory reporting obligations, resolving commercial disputes, and enforcing our Terms of Service.</li>
                    </ul>
                </section>

                <!-- 4. Google AdSense & Advertising -->
                <section class="pp-section-card" id="section-4">
                    <div class="pp-section-header">
                        <div class="pp-section-badge">4</div>
                        <div>
                            <h2 class="pp-section-title">Google AdSense & Third-Party Advertising</h2>
                            <p class="pp-section-subtitle">Transparency regarding public ads and third-party advertising cookies</p>
                        </div>
                    </div>
                    <p>
                        We may display advertisements on public pages of our platform (e.g., informative blog posts, measurement guide articles, and public visitor pages) using <strong>Google AdSense</strong> (Google LLC) and affiliate networks.
                    </p>
                    <div class="pp-alert pp-alert-warning">
                        <i class="fas fa-ad"></i>
                        <div>
                            <strong>AdSense Privacy Notice:</strong> Google is a third-party vendor that uses cookies to serve ads on our site. Google's use of advertising cookies enables it and its partners to serve ads to our users based on their visit to our sites and/or other sites on the Internet.
                        </div>
                    </div>
                    <h3><i class="fas fa-cookie"></i> Google DoubleClick DART Cookie</h3>
                    <p>
                        Google may use the <strong>DART cookie</strong> to serve ads to users based on their visit to this website and other websites across the web. You may opt out of the use of the DART cookie by visiting the 
                        <a href="https://policies.google.com/technologies/ads" target="_blank" rel="noopener">Google Ad and Content Network Privacy Policy</a>.
                    </p>
                    <h3><i class="fas fa-sliders"></i> Your Choices & How to Opt-Out</h3>
                    <ul>
                        <li>Users may opt out of personalized advertising by visiting <a href="https://www.google.com/settings/ads" target="_blank" rel="noopener">Google Ads Settings</a>.</li>
                        <li>Alternatively, you can opt out of a third-party vendor's use of cookies for personalized advertising by visiting <a href="https://www.aboutads.info/choices/" target="_blank" rel="noopener">www.aboutads.info</a> or <a href="https://www.youronlinechoices.com/" target="_blank" rel="noopener">Your Online Choices</a>.</li>
                        <li>Your confidential tailor records, customer body measurements, and invoice databases are <strong>never</strong> shared with Google AdSense or advertising networks. Ads appear only on public educational/marketing sections.</li>
                    </ul>
                </section>

                <!-- 5. Cookies & Analytics -->
                <section class="pp-section-card" id="section-5">
                    <div class="pp-section-header">
                        <div class="pp-section-badge">5</div>
                        <div>
                            <h2 class="pp-section-title">Cookies & Web Analytics</h2>
                            <p class="pp-section-subtitle">How small data files are used to improve your experience</p>
                        </div>
                    </div>
                    <p>
                        Cookies are small text files placed on your device to remember session authentication, user preferences, and performance insights.
                    </p>
                    <table class="pp-table">
                        <thead>
                            <tr>
                                <th>Cookie Category</th>
                                <th>Purpose</th>
                                <th>Lifespan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Essential / Session</strong></td>
                                <td>Maintains secure tailor login state, CSRF security tokens, and language selection.</td>
                                <td>Session / 24 Hours</td>
                            </tr>
                            <tr>
                                <td><strong>Preference Cookies</strong></td>
                                <td>Remembers measurement units (inches vs cm), print layout defaults, and theme preferences.</td>
                                <td>Up to 1 Year</td>
                            </tr>
                            <tr>
                                <td><strong>Analytics (GA4)</strong></td>
                                <td>Google Analytics 4 tracks aggregate visitor flow, popular guides, and device types to optimize performance.</td>
                                <td>Up to 2 Years</td>
                            </tr>
                            <tr>
                                <td><strong>Advertising Cookies</strong></td>
                                <td>Google AdSense cookies for ad frequency capping, fraud prevention, and personalized ad display.</td>
                                <td>Third-Party Policy</td>
                            </tr>
                        </tbody>
                    </table>
                    <p>
                        You can configure your browser to block or alert you about these cookies. Note that disabling essential session cookies may cause parts of the tailoring dashboard to function incorrectly.
                    </p>
                </section>

                <!-- 6. WhatsApp & Communications -->
                <section class="pp-section-card" id="section-6">
                    <div class="pp-section-header">
                        <div class="pp-section-badge">6</div>
                        <div>
                            <h2 class="pp-section-title">WhatsApp & Automated Communications</h2>
                            <p class="pp-section-subtitle">Transactional notifications and customer messaging transparency</p>
                        </div>
                    </div>
                    <p>
                        Our platform integrates with official <strong>WhatsApp Business Cloud APIs</strong> and SMS gateways to provide tailors with automated customer communication tools.
                    </p>
                    <ul>
                        <li><strong>Strictly Transactional:</strong> WhatsApp messages sent through our system are strictly transactional—such as sending a PDF invoice, notifying a customer that their dress is ready for fitting/trial, or sending payment receipt reminders.</li>
                        <li><strong>Customer Consent:</strong> Shop owners are responsible for ensuring that their customers consent to receiving transactional status messages at their provided phone number.</li>
                        <li><strong>No Promotional Spam:</strong> We never broadcast unauthorized marketing, gambling, or unsolicited third-party promotions to your customers' WhatsApp numbers.</li>
                        <li><strong>Opt-Out Mechanism:</strong> If an end-customer replies with "STOP" or requests to stop WhatsApp notifications, the tailor shop can uncheck WhatsApp alerts for that customer profile.</li>
                    </ul>
                </section>

                <!-- 7. Payments & Invoicing -->
                <section class="pp-section-card" id="section-7">
                    <div class="pp-section-header">
                        <div class="pp-section-badge">7</div>
                        <div>
                            <h2 class="pp-section-title">Payments & Financial Information</h2>
                            <p class="pp-section-subtitle">Safe handling of subscription fees and tailor-to-customer billing</p>
                        </div>
                    </div>
                    <p>
                        Our platform manages two types of transactions:
                    </p>
                    <ol>
                        <li>
                            <strong>Software Subscription Payments:</strong> When you purchase a subscription or renew your tailor shop license, payments are processed by authorized payment aggregators (e.g., Razorpay, Cashfree, Stripe, or direct UPI). We do not record or retain your debit/credit card numbers or UPI MPIN on our servers.
                        </li>
                        <li>
                            <strong>Tailor-to-Customer Invoices:</strong> Our software calculates order totals, advances received, and outstanding amounts purely as digital bookkeeping. These records are stored securely in your private shop database.
                        </li>
                    </ol>
                </section>

                <!-- 8. Data Storage & Security -->
                <section class="pp-section-card" id="section-8">
                    <div class="pp-section-header">
                        <div class="pp-section-badge">8</div>
                        <div>
                            <h2 class="pp-section-title">Data Storage, Encryption & Security</h2>
                            <p class="pp-section-subtitle">Technical safeguards engineered to protect your tailoring records</p>
                        </div>
                    </div>
                    <p>
                        We employ industry-standard administrative, physical, and technical safeguards to ensure high data integrity and protection:
                    </p>
                    <div class="pp-grid-2">
                        <div class="pp-subcard">
                            <h4><i class="fas fa-lock text-success"></i> SSL/TLS Encryption</h4>
                            <p>All communication between your browser and our servers is encrypted using modern 256-bit TLS/SSL protocols.</p>
                        </div>
                        <div class="pp-subcard">
                            <h4><i class="fas fa-key text-warning"></i> Password Hashing</h4>
                            <p>User passwords are irreversibly hashed using robust algorithms (Bcrypt/Argon2) before database insertion.</p>
                        </div>
                        <div class="pp-subcard">
                            <h4><i class="fas fa-database text-info"></i> Automated Cloud Backups</h4>
                            <p>Databases are backed up on encrypted cloud storage to prevent data loss in case of hardware or network failure.</p>
                        </div>
                        <div class="pp-subcard">
                            <h4><i class="fas fa-user-lock text-primary"></i> Role-Based Access</h4>
                            <p>Strict access boundaries ensure staff members only see sections allowed by the primary shop administrator.</p>
                        </div>
                    </div>
                    <p class="mb-0">
                        While we use state-of-the-art protections, no method of transmission over the Internet is 100% immune from risks. We encourage you to use strong, unique passwords and avoid sharing login credentials.
                    </p>
                </section>

                <!-- 9. Third-Party Sharing -->
                <section class="pp-section-card" id="section-9">
                    <div class="pp-section-header">
                        <div class="pp-section-badge">9</div>
                        <div>
                            <h2 class="pp-section-title">Third-Party Sharing & Disclosure</h2>
                            <p class="pp-section-subtitle">Circumstances where data may be shared with trusted service providers</p>
                        </div>
                    </div>
                    <p>
                        <strong>We will never sell, lease, or monetize your personal or client data.</strong> We disclose information only under strict, contractual parameters:
                    </p>
                    <ul>
                        <li><strong>Infrastructure & Hosting Providers:</strong> Secure cloud servers, data centers (e.g., Hostinger / Cloudflare), and transactional email delivery networks (e.g., SMTP servers).</li>
                        <li><strong>Telecommunication & Messaging Partners:</strong> WhatsApp Cloud API and authorized Indian telecom SMS gateways solely for transmitting requested customer notifications.</li>
                        <li><strong>Legal & Statutory Requirements:</strong> When compelled by valid court orders, government law enforcement agencies, or under applicable provisions of Indian law (such as the IT Act and DPDP Act).</li>
                        <li><strong>Business Restructuring:</strong> In the event of a merger, acquisition, or sale of assets, user records may be transferred to the successor entity under equal privacy protections.</li>
                    </ul>
                </section>

                <!-- 10. Data Retention Periods -->
                <section class="pp-section-card" id="section-10">
                    <div class="pp-section-header">
                        <div class="pp-section-badge">10</div>
                        <div>
                            <h2 class="pp-section-title">Data Retention Policy</h2>
                            <p class="pp-section-subtitle">How long we preserve operational and customer records</p>
                        </div>
                    </div>
                    <p>
                        We retain personal data only for as long as necessary to fulfill the purposes outlined in this policy or satisfy legal, accounting, and tax retention obligations:
                    </p>
                    <table class="pp-table">
                        <thead>
                            <tr>
                                <th>Data Type</th>
                                <th>Retention Duration</th>
                                <th>Post-Retention Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Active Tailor Account & Orders</strong></td>
                                <td>For the lifetime of your active subscription & account.</td>
                                <td>Maintained securely.</td>
                            </tr>
                            <tr>
                                <td><strong>Customer Measurement Books</strong></td>
                                <td>Preserved until deleted or requested by shop owner.</td>
                                <td>Permanent deletion upon request.</td>
                            </tr>
                            <tr>
                                <td><strong>Tax Invoices & Billing Records</strong></td>
                                <td>Up to 7 years (as mandated under Indian GST & Tax Laws).</td>
                                <td>Secure archiving or deletion.</td>
                            </tr>
                            <tr>
                                <td><strong>Account Deletion Requests</strong></td>
                                <td>Within 30 business days of verified request.</td>
                                <td>Permanent erasure from live database.</td>
                            </tr>
                            <tr>
                                <td><strong>Server Logs & Error Traces</strong></td>
                                <td>30 to 90 days.</td>
                                <td>Automated rolling overwrite.</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <!-- 11. Data & Account Deletion -->
                <section class="pp-section-card" id="section-11">
                    <div class="pp-section-header">
                        <div class="pp-section-badge">11</div>
                        <div>
                            <h2 class="pp-section-title">User Rights & Account / Data Deletion</h2>
                            <p class="pp-section-subtitle">Step-by-step instructions to export, update, or permanently delete your data</p>
                        </div>
                    </div>
                    <p>
                        In full compliance with Google Play Store Developer Policies, Meta Platform Guidelines, and international data protection standards, we provide all users with transparent mechanisms to delete their account and associated data.
                    </p>
                    
                    <h3><i class="fas fa-trash-alt text-danger"></i> How to Request Complete Account & Data Deletion</h3>
                    <p>You can request complete deletion of your tailor account, staff credentials, customer profiles, and measurement archives through either method:</p>
                    
                    <div class="pp-grid-2">
                        <div class="pp-subcard">
                            <h4>Method A: Through Dashboard</h4>
                            <p class="mb-2">1. Log in to your tailor admin panel.</p>
                            <p class="mb-2">2. Go to <strong>Settings &gt; Profile / Account Settings</strong>.</p>
                            <p class="mb-0">3. Scroll to <strong>"Delete My Account"</strong>, confirm your password, and submit.</p>
                        </div>
                        <div class="pp-subcard">
                            <h4>Method B: Direct Email Request</h4>
                            <p class="mb-2">Send an email to: <a href="mailto:codelockinfo@gmail.com" class="fw-bold">codelockinfo@gmail.com</a></p>
                            <p class="mb-2">Subject: <strong>"Account & Data Deletion Request"</strong></p>
                            <p class="mb-0">Include your registered shop phone number and email address. Our security team will process it within <strong>7 to 15 business days</strong>.</p>
                        </div>
                    </div>

                    <div class="pp-alert pp-alert-success">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <strong>What is wiped upon deletion?</strong> Your shop profile, staff accounts, customer measurement sheets, cloth sample images, and order records will be permanently erased from our production databases.
                        </div>
                    </div>
                </section>

                <!-- 12. Indian DPDP & GDPR Rights -->
                <section class="pp-section-card" id="section-12">
                    <div class="pp-section-header">
                        <div class="pp-section-badge">12</div>
                        <div>
                            <h2 class="pp-section-title">Indian DPDP Act (2023) & International Rights</h2>
                            <p class="pp-section-subtitle">Legal rights granted under Indian and global data privacy frameworks</p>
                        </div>
                    </div>
                    <p>
                        Under the <strong>Digital Personal Data Protection (DPDP) Act, 2023 (India)</strong> and international guidelines (such as GDPR where applicable), you are entitled to:
                    </p>
                    <ul>
                        <li><strong>Right to Access & Summary:</strong> Request a summary of personal data processed by us and the processing activities undertaken.</li>
                        <li><strong>Right to Correction & Erasure:</strong> Correct inaccurate or incomplete personal records, or request erasure of obsolete customer details.</li>
                        <li><strong>Right of Grievance Redressal:</strong> Avail easily accessible grievance redressal mechanisms regarding our data obligations.</li>
                        <li><strong>Right to Nominate:</strong> Nominate another individual to exercise data rights on your behalf in the event of incapacity or demise.</li>
                        <li><strong>Data Portability:</strong> Export your customer contact book and measurement list in spreadsheet/CSV formats from your dashboard.</li>
                    </ul>
                </section>

                <!-- 13. Children's Privacy -->
                <section class="pp-section-card" id="section-13">
                    <div class="pp-section-header">
                        <div class="pp-section-badge">13</div>
                        <div>
                            <h2 class="pp-section-title">Children's Privacy Protection</h2>
                            <p class="pp-section-subtitle">Policy regarding minors under 18 years of age</p>
                        </div>
                    </div>
                    <p>
                        Our platform is a business-to-business (B2B) tailoring software intended for commercial enterprises, tailors, and boutique managers aged 18 and older. We do not knowingly solicit subscriptions or collect personal identification from children under 18.
                    </p>
                    <p class="mb-0">
                        While a tailor shop may enter child clothing measurements (e.g., kids' school uniforms or festive attire) provided by parents or guardians, this data is strictly treated as private, anonymized measurement entries under parental consent.
                    </p>
                </section>

                <!-- 14. Grievance & Contact Info -->
                <section class="pp-section-card" id="section-14">
                    <div class="pp-section-header">
                        <div class="pp-section-badge">14</div>
                        <div>
                            <h2 class="pp-section-title">Grievance Redressal & Contact Information</h2>
                            <p class="pp-section-subtitle">Official channels to reach our compliance officer and support team</p>
                        </div>
                    </div>
                    <p>
                        If you have questions, feedback, or grievances regarding this Privacy Policy, or if you wish to exercise your data rights, please contact our designated Grievance Officer:
                    </p>

                    <div class="pp-contact-box">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="pp-contact-item">
                                    <div class="pp-contact-icon">
                                        <i class="fas fa-building"></i>
                                    </div>
                                    <div>
                                        <strong class="d-block text-dark">Company / Organization</strong>
                                        <span class="text-muted">Codelock Solutions</span>
                                        <small class="d-block text-muted">Brand: <?php echo htmlspecialchars($brandName); ?></small>
                                    </div>
                                </div>

                                <div class="pp-contact-item">
                                    <div class="pp-contact-icon">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </div>
                                    <div>
                                        <strong class="d-block text-dark">Office Address</strong>
                                        <span class="text-muted">Silver Business Point, Near VIP Circle, Utran, Surat, Gujarat - 394105, India</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="pp-contact-item">
                                    <div class="pp-contact-icon">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div>
                                        <strong class="d-block text-dark">Official Email</strong>
                                        <a href="https://mail.google.com/mail/?view=cm&fs=1&to=codelockinfo@gmail.com" target="_blank" class="text-decoration-none">
                                            codelockinfo@gmail.com
                                        </a>
                                        <small class="d-block text-muted">Support: info@tailorpro.in</small>
                                    </div>
                                </div>

                                <div class="pp-contact-item">
                                    <div class="pp-contact-icon">
                                        <i class="fas fa-phone-alt"></i>
                                    </div>
                                    <div>
                                        <strong class="d-block text-dark">Support Hotline / WhatsApp</strong>
                                        <a href="tel:+917600464414" class="text-decoration-none">+91 76004 64414</a>
                                        <small class="d-block text-muted">Mon - Sat: 9:30 AM to 6:30 PM IST</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-3" style="border-color: #cbd5e1;">

                        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                            <span class="text-muted small">
                                <i class="fas fa-certificate text-primary me-1"></i> Registered software under Codelock Solutions.
                            </span>
                            <div class="d-flex gap-2">
                                <a href="terms-of-service" class="btn-pp-outline py-1 px-3">
                                    <i class="fas fa-file-contract"></i> Terms of Service
                                </a>
                                <a href="contact" class="btn-pp-primary py-1 px-3">
                                    <i class="fas fa-headset"></i> Contact Us
                                </a>
                            </div>
                        </div>
                    </div>
                </section>

            </div>
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

    <!-- Table of Contents Active Spy & Smooth Scroll Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const tocLinks = document.querySelectorAll('.pp-toc-link');
        const sections = document.querySelectorAll('.pp-section-card');

        // Smooth scroll on TOC click
        tocLinks.forEach(link => {
            link.addEventListener('click', function (e) {
                const targetId = this.getAttribute('href');
                if (targetId && targetId.startsWith('#')) {
                    e.preventDefault();
                    const targetEl = document.querySelector(targetId);
                    if (targetEl) {
                        const yOffset = -90; 
                        const y = targetEl.getBoundingClientRect().top + window.pageYOffset + yOffset;
                        window.scrollTo({ top: y, behavior: 'smooth' });
                    }
                }
            });
        });

        // IntersectionObserver for active TOC highlight
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const id = entry.target.getAttribute('id');
                        tocLinks.forEach(link => {
                            if (link.getAttribute('href') === '#' + id) {
                                link.classList.add('active');
                            } else {
                                link.classList.remove('active');
                            }
                        });
                    }
                });
            }, {
                rootMargin: '-20% 0px -70% 0px',
                threshold: 0
            });

            sections.forEach(sec => observer.observe(sec));
        }
    });
    </script>

</body>
</html>
