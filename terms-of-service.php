<?php
if (file_exists(__DIR__ . '/config/config.php')) {
    require_once __DIR__ . '/config/config.php';
} else {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

$page_title = 'Terms of Service';

if (file_exists(__DIR__ . '/helpers/SEOHelper.php')) {
    require_once 'helpers/SEOHelper.php';
}

$baseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$canonicalUrl = $baseUrl . '/terms-of-service';
$brandName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : (defined('APP_NAME') ? APP_NAME : 'TailorPro');

$seoOptions = [
    'title' => 'Terms of Service - ' . $brandName . ' | Tailoring Management System',
    'description' => 'Read our official Terms of Service to understand the user agreement, subscription policies, customer data ownership, and acceptable use terms for ' . $brandName . ' by Codelock Solutions.',
    'keywords' => 'terms of service, terms and conditions, TailorPro terms, tailor software legal terms, user agreement, tailoring subscription terms, Codelock Solutions Surat',
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
            --tos-primary: #4f46e5;
            --tos-primary-hover: #4338ca;
            --tos-secondary: #764ba2;
            --tos-accent: #8b5cf6;
            --tos-dark: #0f172a;
            --tos-heading: #1e1b4b;
            --tos-body: #475569;
            --tos-light-bg: #f8fafc;
            --tos-card-border: #e2e8f0;
            --tos-radius: 16px;
            --tos-gradient: linear-gradient(135deg, #4f46e5 0%, #667eea 50%, #764ba2 100%);
        }

        html, body {
            overflow-x: clip;
            scroll-padding-top: 85px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8fafc;
            color: var(--tos-body);
            line-height: 1.7;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--tos-heading);
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        /* Scroll Reveal Animations */
        .tos-reveal {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.75s cubic-bezier(0.16, 1, 0.3, 1), transform 0.75s cubic-bezier(0.16, 1, 0.3, 1);
            will-change: opacity, transform;
        }
        .tos-reveal.is-revealed {
            opacity: 1;
            transform: translateY(0);
        }

        /* Hero Header */
        .tos-hero {
            background: var(--tos-gradient);
            color: #ffffff;
            padding: 60px 0 75px;
            position: relative;
            overflow: hidden;
        }
        .tos-hero::before {
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
        .tos-hero::after {
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
        .tos-hero .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 1.25rem;
        }
        .tos-hero .breadcrumb-item a {
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .tos-hero .breadcrumb-item a:hover {
            color: #ffffff;
            text-decoration: underline;
        }
        .tos-hero .breadcrumb-item.active {
            color: rgba(255, 255, 255, 0.95);
            font-size: 0.9rem;
        }
        .tos-hero .breadcrumb-item + .breadcrumb-item::before {
            color: rgba(255, 255, 255, 0.6);
        }

        .tos-badge {
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
            animation: tosBadgeGlow 3.5s ease-in-out infinite;
        }

        @keyframes tosBadgeGlow {
            0%, 100% {
                box-shadow: 0 0 12px rgba(255, 255, 255, 0.2);
            }
            50% {
                box-shadow: 0 0 25px rgba(255, 255, 255, 0.45);
            }
        }

        .tos-hero h1 {
            color: #ffffff;
            font-size: 2.65rem;
            font-weight: 800;
            margin-bottom: 0.85rem;
        }
        .tos-hero p.lead {
            color: rgba(255, 255, 255, 0.92);
            font-size: 1.15rem;
            max-width: 760px;
            margin-bottom: 1.75rem;
        }

        .tos-meta-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        .tos-meta-pill {
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

        /* Highlights Row */
        .tos-highlights {
            margin-top: -35px;
            position: relative;
            z-index: 10;
            margin-bottom: 45px;
        }
        .tos-highlight-card {
            background: #ffffff;
            border: 1px solid var(--tos-card-border);
            border-radius: 14px;
            padding: 22px 20px;
            height: 100%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }
        .tos-highlight-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 35px rgba(79, 70, 229, 0.12);
        }
        .tos-highlight-icon {
            width: 48px;
            height: 48px;
            min-width: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.12) 0%, rgba(118, 75, 162, 0.12) 100%);
            color: var(--tos-primary);
        }
        .tos-highlight-card h4 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 4px;
            color: var(--tos-heading);
        }
        .tos-highlight-card p {
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
            .tos-sidebar {
                position: -webkit-sticky !important;
                position: sticky !important;
                top: 85px !important;
                z-index: 100 !important;
                max-height: calc(100vh - 105px) !important;
                overflow-y: auto !important;
                scrollbar-width: thin;
                padding-right: 4px;
            }
            .tos-sidebar::-webkit-scrollbar {
                width: 4px;
            }
            .tos-sidebar::-webkit-scrollbar-thumb {
                background: #cbd5e1;
                border-radius: 4px;
            }
            .tos-toc-list {
                max-height: none !important;
                overflow-y: visible !important;
            }
        }
        @media (max-width: 991px) {
            .tos-sidebar {
                position: relative;
                top: 0;
            }
            .tos-toc-list {
                max-height: 240px;
                overflow-y: auto;
            }
        }
        .tos-toc-card {
            background: #ffffff;
            border: 1px solid var(--tos-card-border);
            border-radius: var(--tos-radius);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            padding: 20px;
            margin-bottom: 24px;
        }
        .tos-toc-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 12px;
            margin-bottom: 12px;
            border-bottom: 1px solid var(--tos-card-border);
        }
        .tos-toc-header h3 {
            font-size: 1rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--tos-heading);
        }
        .tos-toc-list {
            list-style: none;
            padding: 0;
            margin: 0;
            max-height: 440px;
            overflow-y: auto;
            scrollbar-width: thin;
        }
        .tos-toc-list::-webkit-scrollbar {
            width: 4px;
        }
        .tos-toc-list::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .tos-toc-item {
            margin-bottom: 4px;
        }
        .tos-toc-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-radius: 8px;
            color: #475569;
            text-decoration: none;
            font-size: 0.86rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .tos-toc-link:hover {
            color: var(--tos-primary);
            background: rgba(79, 70, 229, 0.08);
            transform: translateX(3px);
        }
        .tos-toc-link.active {
            color: var(--tos-primary);
            background: rgba(79, 70, 229, 0.12);
            font-weight: 700;
            border-left: 3px solid var(--tos-primary);
        }
        .tos-toc-num {
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
        .tos-toc-link.active .tos-toc-num {
            background: var(--tos-primary);
            color: #ffffff;
        }

        /* Help Box Widget */
        .tos-help-widget {
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.08) 0%, rgba(118, 75, 162, 0.08) 100%);
            border: 1px dashed rgba(79, 70, 229, 0.35);
            border-radius: 14px;
            padding: 20px;
            text-align: center;
        }
        .tos-help-widget h4 {
            font-size: 0.95rem;
            margin-bottom: 6px;
        }
        .tos-help-widget p {
            font-size: 0.82rem;
            color: #64748b;
            margin-bottom: 14px;
        }

        /* Main Content Cards */
        .tos-section-card {
            background: #ffffff;
            border: 1px solid var(--tos-card-border);
            border-radius: var(--tos-radius);
            padding: 32px 30px;
            margin-bottom: 28px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            scroll-margin-top: 100px;
            transition: border-color 0.25s ease;
        }
        .tos-section-card:hover {
            border-color: #cbd5e1;
        }
        .tos-section-header {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 2px solid #f1f5f9;
        }
        .tos-section-badge {
            width: 42px;
            height: 42px;
            min-width: 42px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--tos-primary) 0%, var(--tos-secondary) 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            font-weight: 700;
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.25);
        }
        .tos-section-title {
            margin: 0;
            font-size: 1.35rem;
            color: var(--tos-heading);
            line-height: 1.3;
        }
        .tos-section-subtitle {
            font-size: 0.85rem;
            color: #64748b;
            margin: 3px 0 0;
        }

        .tos-section-card h3 {
            font-size: 1.1rem;
            margin-top: 1.5rem;
            margin-bottom: 0.6rem;
            color: #1e1b4b;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tos-section-card h3 i {
            color: var(--tos-primary);
            font-size: 0.95rem;
        }

        .tos-section-card p {
            margin-bottom: 1rem;
            font-size: 0.95rem;
            color: #475569;
        }

        .tos-section-card ul, .tos-section-card ol {
            padding-left: 1.25rem;
            margin-bottom: 1.2rem;
        }
        .tos-section-card li {
            margin-bottom: 0.5rem;
            font-size: 0.93rem;
            color: #475569;
        }
        .tos-section-card li strong {
            color: #1e1b4b;
        }

        /* Custom Box Alerts */
        .tos-alert {
            border-radius: 12px;
            padding: 16px 18px;
            margin: 18px 0;
            font-size: 0.92rem;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .tos-alert-info {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }
        .tos-alert-success {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }
        .tos-alert-warning {
            background-color: #fffbeb;
            border: 1px solid #fef3c7;
            color: #92400e;
        }
        .tos-alert i {
            font-size: 1.15rem;
            margin-top: 2px;
        }

        /* Grid inside sections */
        .tos-grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            margin: 16px 0;
        }
        .tos-subcard {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px 18px;
        }
        .tos-subcard h4 {
            font-size: 0.95rem;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--tos-heading);
        }
        .tos-subcard p {
            font-size: 0.87rem;
            margin-bottom: 0;
            color: #64748b;
        }

        /* Table */
        .tos-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 18px 0;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            font-size: 0.9rem;
        }
        .tos-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: 700;
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        .tos-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            color: #475569;
        }
        .tos-table tr:last-child td {
            border-bottom: none;
        }
        .tos-table tr:nth-child(even) td {
            background-color: #fafbfd;
        }

        /* Contact Details Card */
        .tos-contact-box {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            padding: 24px;
            margin-top: 20px;
        }
        .tos-contact-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 16px;
        }
        .tos-contact-item:last-child {
            margin-bottom: 0;
        }
        .tos-contact-icon {
            width: 38px;
            height: 38px;
            min-width: 38px;
            border-radius: 10px;
            background: #ffffff;
            color: var(--tos-primary);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        /* Action Buttons */
        .btn-tos-primary {
            background: var(--tos-gradient);
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
        .btn-tos-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(79, 70, 229, 0.35);
            color: #ffffff;
        }

        .btn-tos-outline {
            background: #ffffff;
            color: var(--tos-primary) !important;
            border: 1.5px solid var(--tos-primary);
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
        .btn-tos-outline:hover {
            background: var(--tos-primary);
            color: #ffffff !important;
        }

        @media (max-width: 991px) {
            .tos-hero {
                padding: 40px 0 50px;
            }
            .tos-hero h1 {
                font-size: 1.95rem;
            }
            .tos-highlights {
                margin-top: -20px;
            }
            .tos-section-card {
                padding: 24px 20px;
            }
            .tos-sidebar {
                position: relative;
                top: 0;
                margin-bottom: 24px;
            }
            .tos-toc-list {
                max-height: 240px;
            }
        }

        /* Print styles */
        @media print {
            .tos-hero, .tos-highlights, .tos-sidebar, nav, footer, .go-to-top-btn, .whatsapp-button {
                display: none !important;
            }
            body {
                background: #ffffff;
                color: #000000;
            }
            .tos-section-card {
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
    <header class="tos-hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-10 mx-auto text-center text-lg-start">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center justify-content-lg-start">
                            <li class="breadcrumb-item"><a href="./"><i class="fas fa-home me-1"></i>Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Terms of Service</li>
                        </ol>
                    </nav>

                    <div class="tos-badge">
                        <i class="fas fa-file-contract"></i> User Agreement & Service Terms
                    </div>

                    <h1>Terms of Service</h1>
                    <p class="lead">
                        Please review the legal rules, user rights, data guarantees, and acceptable use conditions for <strong><?php echo htmlspecialchars($brandName); ?></strong> operated by <strong>Codelock Solutions</strong>.
                    </p>

                    <div class="tos-meta-tags justify-content-center justify-content-lg-start">
                        <span class="tos-meta-pill">
                            <i class="fas fa-calendar-check"></i> Last Updated: <?php echo date('F j, Y'); ?>
                        </span>
                        <span class="tos-meta-pill">
                            <i class="fas fa-code-branch"></i> Version: 2.2
                        </span>
                        <span class="tos-meta-pill">
                            <i class="fas fa-landmark"></i> Jurisdiction: Surat, Gujarat (India)
                        </span>
                        <span class="tos-meta-pill">
                            <i class="fas fa-handshake"></i> B2B Agreement
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Key Highlights Grid -->
    <div class="container tos-highlights">
        <div class="row g-3">
            <div class="col-md-6 col-lg-3">
                <div class="tos-highlight-card">
                    <div class="tos-highlight-icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <div>
                        <h4>Your Data Ownership</h4>
                        <p>You own 100% of customer measurements, designs, and shop records stored in your system.</p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="tos-highlight-card">
                    <div class="tos-highlight-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <div>
                        <h4>99.9% Uptime Commitment</h4>
                        <p>Reliable cloud infrastructure designed for high availability during your daily store hours.</p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="tos-highlight-card">
                    <div class="tos-highlight-icon">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <div>
                        <h4>Compliant Messaging</h4>
                        <p>Official WhatsApp & SMS integrations strictly used for your order receipts, trial & delivery alerts.</p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="tos-highlight-card">
                    <div class="tos-highlight-icon">
                        <i class="fas fa-door-open"></i>
                    </div>
                    <div>
                        <h4>Freedom to Cancel</h4>
                        <p>No lock-in traps. Cancel anytime with 30-day full customer data export guaranteed.</p>
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
                <div class="tos-sidebar">
                    <div class="tos-toc-card">
                        <div class="tos-toc-header">
                            <h3><i class="fas fa-list-ol text-primary"></i> Contents</h3>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" onclick="window.print()" title="Print Terms">
                                <i class="fas fa-print"></i>
                            </button>
                        </div>
                        <ul class="tos-toc-list" id="tosTocList">
                            <li class="tos-toc-item">
                                <a href="#section-1" class="tos-toc-link">
                                    <span class="tos-toc-num">1</span>
                                    <span>Acceptance of Terms</span>
                                </a>
                            </li>
                            <li class="tos-toc-item">
                                <a href="#section-2" class="tos-toc-link">
                                    <span class="tos-toc-num">2</span>
                                    <span>Description of Service</span>
                                </a>
                            </li>
                            <li class="tos-toc-item">
                                <a href="#section-3" class="tos-toc-link">
                                    <span class="tos-toc-num">3</span>
                                    <span>Account & Registration</span>
                                </a>
                            </li>
                            <li class="tos-toc-item">
                                <a href="#section-4" class="tos-toc-link">
                                    <span class="tos-toc-num">4</span>
                                    <span>Subscriptions & Billing</span>
                                </a>
                            </li>
                            <li class="tos-toc-item">
                                <a href="#section-5" class="tos-toc-link">
                                    <span class="tos-toc-num">5</span>
                                    <span>Cancellation & Refunds</span>
                                </a>
                            </li>
                            <li class="tos-toc-item">
                                <a href="#section-6" class="tos-toc-link">
                                    <span class="tos-toc-num">6</span>
                                    <span>Acceptable Use Policy</span>
                                </a>
                            </li>
                            <li class="tos-toc-item">
                                <a href="#section-7" class="tos-toc-link">
                                    <span class="tos-toc-num">7</span>
                                    <span>Measurement & Data Rights</span>
                                </a>
                            </li>
                            <li class="tos-toc-item">
                                <a href="#section-8" class="tos-toc-link">
                                    <span class="tos-toc-num">8</span>
                                    <span>WhatsApp & SMS Terms</span>
                                </a>
                            </li>
                            <li class="tos-toc-item">
                                <a href="#section-9" class="tos-toc-link">
                                    <span class="tos-toc-num">9</span>
                                    <span>Intellectual Property</span>
                                </a>
                            </li>
                            <li class="tos-toc-item">
                                <a href="#section-10" class="tos-toc-link">
                                    <span class="tos-toc-num">10</span>
                                    <span>Uptime SLA & Support</span>
                                </a>
                            </li>
                            <li class="tos-toc-item">
                                <a href="#section-11" class="tos-toc-link">
                                    <span class="tos-toc-num">11</span>
                                    <span>Termination & Data Export</span>
                                </a>
                            </li>
                            <li class="tos-toc-item">
                                <a href="#section-12" class="tos-toc-link">
                                    <span class="tos-toc-num">12</span>
                                    <span>Liability Limitations</span>
                                </a>
                            </li>
                            <li class="tos-toc-item">
                                <a href="#section-13" class="tos-toc-link">
                                    <span class="tos-toc-num">13</span>
                                    <span>Indemnification</span>
                                </a>
                            </li>
                            <li class="tos-toc-item">
                                <a href="#section-14" class="tos-toc-link">
                                    <span class="tos-toc-num">14</span>
                                    <span>Governing Law & Disputes</span>
                                </a>
                            </li>
                            <li class="tos-toc-item">
                                <a href="#section-15" class="tos-toc-link">
                                    <span class="tos-toc-num">15</span>
                                    <span>Changes to Agreement</span>
                                </a>
                            </li>
                            <li class="tos-toc-item">
                                <a href="#section-16" class="tos-toc-link">
                                    <span class="tos-toc-num">16</span>
                                    <span>Contact & Legal Inquiries</span>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- Quick Support Widget -->
                    <div class="tos-help-widget">
                        <h4>Questions about our Terms?</h4>
                        <p>Our compliance team is available to assist you with contract or billing queries.</p>
                        <div class="d-grid gap-2">
                            <a href="https://mail.google.com/mail/?view=cm&fs=1&to=codelockinfo@gmail.com" target="_blank" class="btn btn-sm btn-tos-primary">
                                <i class="fas fa-envelope"></i> Email Legal Team
                            </a>
                            <a href="https://wa.me/917600464414" target="_blank" class="btn btn-sm btn-tos-outline">
                                <i class="fab fa-whatsapp"></i> Chat on WhatsApp
                            </a>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Main Legal Sections -->
            <div class="col-lg-8 col-xl-9">

                <!-- 1. Acceptance of Terms -->
                <section class="tos-section-card tos-reveal" id="section-1">
                    <div class="tos-section-header">
                        <div class="tos-section-badge">1</div>
                        <div>
                            <h2 class="tos-section-title">Acceptance of Terms</h2>
                            <p class="tos-section-subtitle">Binding legal contract between you and Codelock Solutions</p>
                        </div>
                    </div>
                    <p>
                        These Terms of Service ("Terms," "Agreement") constitute a legally binding agreement between you ("User," "Subscriber," "Tailor," "Boutique Owner," or "You") and <strong>Codelock Solutions</strong> ("Company," "we," "us," or "our"), governing your access to and use of the <strong><?php echo htmlspecialchars($brandName); ?></strong> platform, web applications, APIs, and associated customer service offerings.
                    </p>
                    <p>
                        By clicking "I Agree," registering an account, or accessing our platform, you acknowledge that you have read, understood, and agree to be bound by these Terms and our <a href="privacy-policy" class="text-primary fw-semibold">Privacy Policy</a>. If you are entering into this Agreement on behalf of a company, tailoring shop, or partnership entity, you represent that you possess the legal authority to bind that entity.
                    </p>
                    <div class="tos-alert tos-alert-info">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>Commercial Agreement:</strong> <?php echo htmlspecialchars($brandName); ?> is a specialized business management software intended for commercial use by tailor shops, designers, boutiques, and garment production workshops.
                        </div>
                    </div>
                </section>

                <!-- 2. Description of Service -->
                <section class="tos-section-card tos-reveal" id="section-2">
                    <div class="tos-section-header">
                        <div class="tos-section-badge">2</div>
                        <div>
                            <h2 class="tos-section-title">Description of Service</h2>
                            <p class="tos-section-subtitle">Scope of our tailoring business management software</p>
                        </div>
                    </div>
                    <p>
                        <?php echo htmlspecialchars($brandName); ?> provides a cloud-based software-as-a-service (SaaS) suite engineered specifically for the custom apparel and tailoring industry, including:
                    </p>
                    <div class="tos-grid-2">
                        <div class="tos-subcard">
                            <h4><i class="fas fa-ruler-combined text-primary"></i> Digital Measurement Book</h4>
                            <p>Recording, archiving, and retrieving client sizing charts for Kurta, Blouse, Pant, Shirt, Sherwani, Lehenga, and Suits.</p>
                        </div>
                        <div class="tos-subcard">
                            <h4><i class="fas fa-tasks text-primary"></i> Order & Workshop Lifecycle</h4>
                            <p>Tracking orders from booking date to cutting, stitching, trial ready, delivery, and payment balance collection.</p>
                        </div>
                        <div class="tos-subcard">
                            <h4><i class="fab fa-whatsapp text-success"></i> Automated Alerts & Invoices</h4>
                            <p>Generating PDF receipts and delivering automated trial notifications and balance alerts via WhatsApp Cloud API.</p>
                        </div>
                        <div class="tos-subcard">
                            <h4><i class="fas fa-users-cog text-warning"></i> Staff & Karigar Accounting</h4>
                            <p>Assigning cutting/stitching jobs, tracking piece-rate hisab, calculating daily wages, and recording worker advances.</p>
                        </div>
                    </div>
                    <p class="mb-0">
                        We periodically release platform updates, optimizations, and new features to enhance stability and security without requiring manual installations on your device.
                    </p>
                </section>

                <!-- 3. Account & Registration -->
                <section class="tos-section-card tos-reveal" id="section-3">
                    <div class="tos-section-header">
                        <div class="tos-section-badge">3</div>
                        <div>
                            <h2 class="tos-section-title">Account Registration & Security</h2>
                            <p class="tos-section-subtitle">Requirements for creating and maintaining valid tailor shop accounts</p>
                        </div>
                    </div>
                    <h3><i class="fas fa-user-check"></i> 3.1 Eligibility & Information Accuracy</h3>
                    <ul>
                        <li>You must be at least 18 years of age and legally competent to enter into contracts under Indian law.</li>
                        <li>You agree to provide true, accurate, current, and complete registration information (shop name, owner name, active contact phone, and business email).</li>
                        <li>Each shop account is intended for the designated business entity. Creating multiple trial accounts to evade subscriptions is strictly prohibited.</li>
                    </ul>

                    <h3><i class="fas fa-key"></i> 3.2 Password & Credential Security</h3>
                    <ul>
                        <li>You are solely responsible for maintaining the confidentiality of your login credentials, passwords, and two-factor authentication tokens.</li>
                        <li>You must assign distinct role permissions (Manager, Master, Cutter, Helper) to employees rather than sharing root administrator credentials.</li>
                        <li>You agree to immediately notify our support team at <a href="mailto:codelockinfo@gmail.com" class="fw-bold">codelockinfo@gmail.com</a> if you suspect any unauthorized access or breach of your account.</li>
                    </ul>
                </section>

                <!-- 4. Subscriptions & Billing -->
                <section class="tos-section-card tos-reveal" id="section-4">
                    <div class="tos-section-header">
                        <div class="tos-section-badge">4</div>
                        <div>
                            <h2 class="tos-section-title">Subscriptions, Pricing & Payments</h2>
                            <p class="tos-section-subtitle">Transparent subscription terms, pricing plans, and payment gateways</p>
                        </div>
                    </div>
                    <p>
                        Our platform is offered on a subscription model (Monthly, Quarterly, or Annually) with a free trial period for new tailor shops:
                    </p>
                    <table class="tos-table">
                        <thead>
                            <tr>
                                <th>Plan</th>
                                <th>Target Business</th>
                                <th>Key Inclusions</th>
                                <th>Billing Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Free Trial</strong></td>
                                <td>New Shops & Testing</td>
                                <td>Full feature access, test measurements, sample invoices</td>
                                <td>Free (No credit card needed)</td>
                            </tr>
                            <tr>
                                <td><strong>Starter / Single Shop</strong></td>
                                <td>Individual Tailor Masters</td>
                                <td>Unlimited measurements, WhatsApp bills, basic karigar hisab</td>
                                <td>Monthly / Annual</td>
                            </tr>
                            <tr>
                                <td><strong>Boutique Pro</strong></td>
                                <td>Growing Boutiques & Studios</td>
                                <td>Multi-staff logins, photo attachments, automated reminders</td>
                                <td>Monthly / Annual</td>
                            </tr>
                            <tr>
                                <td><strong>Enterprise Multi-Branch</strong></td>
                                <td>Tailoring Chains & Factories</td>
                                <td>Multi-branch sync, dedicated account manager, API integration</td>
                                <td>Annual Custom</td>
                            </tr>
                        </tbody>
                    </table>
                    <h3><i class="fas fa-credit-card"></i> Payment Processing & Taxes</h3>
                    <ul>
                        <li><strong>Payment Processors:</strong> Online subscription fees are securely processed through RBI-authorized payment aggregators (e.g., Razorpay, UPI, Netbanking). We never store payment card numbers or bank credentials.</li>
                        <li><strong>Advance Invoicing:</strong> Subscriptions are billed in advance on a recurring schedule based on the chosen tenure.</li>
                        <li><strong>Taxes:</strong> All fees are subject to applicable Goods and Services Tax (GST) as mandated by the Government of India. Valid GST invoices are automatically delivered upon renewal.</li>
                    </ul>
                </section>

                <!-- 5. Cancellation & Refunds -->
                <section class="tos-section-card tos-reveal" id="section-5">
                    <div class="tos-section-header">
                        <div class="tos-section-badge">5</div>
                        <div>
                            <h2 class="tos-section-title">Cancellation, Downgrades & Refund Policy</h2>
                            <p class="tos-section-subtitle">Fair terms for ending or modifying your software plan</p>
                        </div>
                    </div>
                    <h3><i class="fas fa-ban"></i> 5.1 Plan Cancellation</h3>
                    <p>
                        You can cancel your subscription at any time directly through your dashboard (Settings &gt; Subscription) or by emailing support. When you cancel, your account remains active until the end of your prepaid billing period without further automatic renewals.
                    </p>
                    <h3><i class="fas fa-undo"></i> 5.2 Refund Guarantee</h3>
                    <ul>
                        <li><strong>7-Day Money-Back Guarantee:</strong> If you are dissatisfied with our software within seven (7) calendar days of your initial paid activation, you may request a 100% refund by contacting support.</li>
                        <li><strong>After 7 Days:</strong> Subscription payments are non-refundable for elapsed periods due to cloud computing and messaging infrastructure costs incurred on your behalf.</li>
                        <li><strong>Free Trial Security:</strong> Free trials do not automatically charge your account without your explicit confirmation.</li>
                    </ul>
                </section>

                <!-- 6. Acceptable Use Policy -->
                <section class="tos-section-card tos-reveal" id="section-6">
                    <div class="tos-section-header">
                        <div class="tos-section-badge">6</div>
                        <div>
                            <h2 class="tos-section-title">Acceptable Use Policy</h2>
                            <p class="tos-section-subtitle">Permitted uses and strictly prohibited actions across our platform</p>
                        </div>
                    </div>
                    <p>You agree to use <?php echo htmlspecialchars($brandName); ?> exclusively for lawful tailoring, fashion, boutique, and garment workshop operations.</p>
                    
                    <div class="tos-alert tos-alert-warning">
                        <i class="fas fa-shield-virus"></i>
                        <div>
                            <strong>Strictly Prohibited Conduct:</strong> Violation of any of the following terms will result in immediate suspension or termination of your account without refund:
                        </div>
                    </div>
                    <ul>
                        <li>Attempting to reverse engineer, decompile, copy, or scrape the source code, layout, or database schemas of <?php echo htmlspecialchars($brandName); ?>.</li>
                        <li>Using our WhatsApp or messaging infrastructure to broadcast unsolicited spam, political messages, betting/lottery schemes, or deceptive promotional blasts.</li>
                        <li>Uploading illegal, offensive, defamatory, or copyright-infringing content (including pirated fashion catalog imagery).</li>
                        <li>Attempting to compromise our network security, launch DDoS attacks, or inject SQL injection / malicious scripts into input fields.</li>
                        <li>Reselling, sublicensing, or leasing your tailor portal login credentials to competing software vendors or unauthorized third parties.</li>
                    </ul>
                </section>

                <!-- 7. Measurement & Data Rights -->
                <section class="tos-section-card tos-reveal" id="section-7">
                    <div class="tos-section-header">
                        <div class="tos-section-badge">7</div>
                        <div>
                            <h2 class="tos-section-title">Customer Measurement & Business Data Ownership</h2>
                            <p class="tos-section-subtitle">Complete clarity: You own your tailoring records, clients, and measurements</p>
                        </div>
                    </div>
                    <p>
                        We respect the sacred relationship between master tailors and their clients. We uphold the following foundational commitments:
                    </p>
                    <ul>
                        <li><strong>100% Data Ownership:</strong> You retain exclusive, unencumbered ownership of all customer profiles, phone numbers, body measurements, fabric sample photos, invoice history, and employee records you enter into the platform.</li>
                        <li><strong>No Data Monetization:</strong> We do not sell, rent, or lease your customer data to clothing manufacturers, fabric distributors, advertisers, or third-party data brokers.</li>
                        <li><strong>Data Processing Custodian:</strong> We act purely as a secure technological custodian (Data Processor) under the Digital Personal Data Protection (DPDP) Act, 2023.</li>
                        <li><strong>Data Export Rights:</strong> You can export your complete customer contact list and measurement entries in standard spreadsheet (CSV/Excel) formats at any time.</li>
                    </ul>
                </section>

                <!-- 8. WhatsApp & SMS Terms -->
                <section class="tos-section-card tos-reveal" id="section-8">
                    <div class="tos-section-header">
                        <div class="tos-section-badge">8</div>
                        <div>
                            <h2 class="tos-section-title">WhatsApp & Communication Gateways</h2>
                            <p class="tos-section-subtitle">Terms regarding Meta / WhatsApp Cloud API integration</p>
                        </div>
                    </div>
                    <p>
                        <?php echo htmlspecialchars($brandName); ?> facilitates automated transactional alerts (e.g., PDF bill links, trial readiness notifications, delivery reminders) through official WhatsApp Business APIs and SMS aggregators:
                    </p>
                    <ul>
                        <li><strong>Customer Consent:</strong> You represent that you have acquired appropriate consent from your customers to send transactional updates to their provided mobile numbers.</li>
                        <li><strong>Transactional Integrity:</strong> Messages sent via our integration must pertain strictly to garment orders placed at your shop. Promotional blasts without opt-in consent violate WhatsApp's Business Policy and may lead to gateway number suspension.</li>
                        <li><strong>Third-Party Delivery:</strong> While our system guarantees prompt message dispatch, actual delivery depends on recipient network availability and Meta platform uptime.</li>
                    </ul>
                </section>

                <!-- 9. Intellectual Property -->
                <section class="tos-section-card tos-reveal" id="section-9">
                    <div class="tos-section-header">
                        <div class="tos-section-badge">9</div>
                        <div>
                            <h2 class="tos-section-title">Intellectual Property Rights</h2>
                            <p class="tos-section-subtitle">Copyright, trademark, and proprietary software ownership</p>
                        </div>
                    </div>
                    <p>
                        The <?php echo htmlspecialchars($brandName); ?> platform, including its software architecture, backend PHP/MySQL databases, frontend user interfaces, algorithms, logos, documentation, and measurement templates, are the exclusive intellectual property of <strong>Codelock Solutions</strong>, protected under Indian and international copyright and trademark laws.
                    </p>
                    <p class="mb-0">
                        You are granted a revocable, non-exclusive, non-transferable license to access and use the software in accordance with your subscription plan. This agreement does not transfer any intellectual property ownership to you.
                    </p>
                </section>

                <!-- 10. Uptime SLA & Support -->
                <section class="tos-section-card tos-reveal" id="section-10">
                    <div class="tos-section-header">
                        <div class="tos-section-badge">10</div>
                        <div>
                            <h2 class="tos-section-title">Service Availability & Customer Support</h2>
                            <p class="tos-section-subtitle">Our 99.9% uptime target and technical support channels</p>
                        </div>
                    </div>
                    <div class="tos-grid-2">
                        <div class="tos-subcard">
                            <h4><i class="fas fa-server text-success"></i> 99.9% Uptime Commitment</h4>
                            <p>We deploy high-availability cloud servers with automated load balancing and continuous monitoring to ensure your shop can bill customers uninterrupted.</p>
                        </div>
                        <div class="tos-subcard">
                            <h4><i class="fas fa-tools text-warning"></i> Scheduled Maintenance</h4>
                            <p>Routine updates and database optimizations are performed during off-peak night hours (IST) with advance dashboard notice whenever possible.</p>
                        </div>
                    </div>
                    <h3><i class="fas fa-headset"></i> Dedicated Support Hours</h3>
                    <p>
                        Our support team is available via Phone and WhatsApp at <strong>+91 76004 64414</strong> and via Email at <strong>codelockinfo@gmail.com</strong> from Monday to Saturday, 9:30 AM to 6:30 PM IST. Critical outage tickets are monitored 24/7.
                    </p>
                </section>

                <!-- 11. Termination & Data Export -->
                <section class="tos-section-card tos-reveal" id="section-11">
                    <div class="tos-section-header">
                        <div class="tos-section-badge">11</div>
                        <div>
                            <h2 class="tos-section-title">Account Termination & Data Retrieval</h2>
                            <p class="tos-section-subtitle">Grace periods and procedures when accounts are closed</p>
                        </div>
                    </div>
                    <p>
                        Upon voluntary cancellation or account termination:
                    </p>
                    <ol>
                        <li><strong>30-Day Grace Period for Data Export:</strong> Your records will remain securely archived for <strong>thirty (30) days</strong> following subscription expiry, during which you can download your customer measurements and invoice records.</li>
                        <li><strong>Permanent Data Deletion:</strong> After 30 days without renewal or upon receiving an explicit Account Deletion Request, all proprietary shop records, client files, and sample photos will be permanently wiped from production databases.</li>
                        <li><strong>Statutory Invoices:</strong> Payment tax receipts issued for software subscriptions will be preserved in accounting archives as required by Indian GST laws.</li>
                    </ol>
                </section>

                <!-- 12. Liability Limitations -->
                <section class="tos-section-card tos-reveal" id="section-12">
                    <div class="tos-section-header">
                        <div class="tos-section-badge">12</div>
                        <div>
                            <h2 class="tos-section-title">Limitation of Liability & Disclaimers</h2>
                            <p class="tos-section-subtitle">Standard legal disclaimers for cloud software services</p>
                        </div>
                    </div>
                    <p>
                        To the maximum extent permitted by applicable Indian law, <?php echo htmlspecialchars($brandName); ?> and Codelock Solutions provide the platform on an <strong>"AS IS"</strong> and <strong>"AS AVAILABLE"</strong> basis without warranties of any kind, whether express or implied.
                    </p>
                    <p>
                        We shall not be liable for any indirect, incidental, punitive, special, or consequential damages, including loss of profits, tailoring production delays, disputes between tailors and their end-customers regarding garment fitting, cloth shrinkage, or third-party telecom SMS/WhatsApp delivery network delays.
                    </p>
                    <div class="tos-alert tos-alert-info">
                        <i class="fas fa-balance-scale"></i>
                        <div>
                            <strong>Liability Cap:</strong> In all circumstances, our maximum aggregate liability arising out of or related to this Agreement shall be limited to the total fees actually paid by you to Codelock Solutions during the three (3) months preceding the incident.
                        </div>
                    </div>
                </section>

                <!-- 13. Indemnification -->
                <section class="tos-section-card tos-reveal" id="section-13">
                    <div class="tos-section-header">
                        <div class="tos-section-badge">13</div>
                        <div>
                            <h2 class="tos-section-title">Indemnification</h2>
                            <p class="tos-section-subtitle">Mutual defense obligations</p>
                        </div>
                    </div>
                    <p>
                        You agree to defend, indemnify, and hold harmless Codelock Solutions, its founders, directors, employees, and technological partners from and against any claims, damages, liabilities, costs, and expenses (including legal fees) arising from:
                    </p>
                    <ul>
                        <li>Your breach of any provision of these Terms of Service.</li>
                        <li>Any dispute or claim between you and your end-customers or employees.</li>
                        <li>Your violation of third-party intellectual property or privacy rights.</li>
                        <li>Any unlawful content uploaded or transmitted through your user account.</li>
                    </ul>
                </section>

                <!-- 14. Governing Law & Disputes -->
                <section class="tos-section-card tos-reveal" id="section-14">
                    <div class="tos-section-header">
                        <div class="tos-section-badge">14</div>
                        <div>
                            <h2 class="tos-section-title">Governing Law & Dispute Resolution</h2>
                            <p class="tos-section-subtitle">Jurisdiction of Indian courts in Surat, Gujarat</p>
                        </div>
                    </div>
                    <p>
                        This Agreement shall be governed by, interpreted, and construed in accordance with the laws of the <strong>Republic of India</strong>, without regard to its conflict of law principles.
                    </p>
                    <p>
                        In the event of any controversy, claim, or dispute arising out of or relating to these Terms, the parties agree to first seek an amicable resolution through good-faith discussions with our management team.
                    </p>
                    <p class="mb-0">
                        If unresolved within thirty (30) days, the dispute shall be subject to the exclusive jurisdiction of the competent civil courts located in <strong>Surat, Gujarat, India</strong>.
                    </p>
                </section>

                <!-- 15. Changes to Agreement -->
                <section class="tos-section-card tos-reveal" id="section-15">
                    <div class="tos-section-header">
                        <div class="tos-section-badge">15</div>
                        <div>
                            <h2 class="tos-section-title">Changes to Terms of Service</h2>
                            <p class="tos-section-subtitle">How updates to this agreement are published</p>
                        </div>
                    </div>
                    <p>
                        We may update these Terms from time to time to reflect product enhancements, statutory changes, or operational requirements. When significant revisions occur, we will notify you by:
                    </p>
                    <ul>
                        <li>Updating the "Last Updated" timestamp at the top of this page.</li>
                        <li>Displaying an announcement notice inside your tailor admin dashboard.</li>
                        <li>Sending an email notification to your registered business address.</li>
                    </ul>
                    <p class="mb-0">
                        Your continued access or use of <?php echo htmlspecialchars($brandName); ?> after the effective date of modified terms signifies your full acceptance of the updated Agreement.
                    </p>
                </section>

                <!-- 16. Contact & Legal Inquiries -->
                <section class="tos-section-card tos-reveal" id="section-16">
                    <div class="tos-section-header">
                        <div class="tos-section-badge">16</div>
                        <div>
                            <h2 class="tos-section-title">Legal Contact & Grievance Redressal</h2>
                            <p class="tos-section-subtitle">Official channels to reach our legal and support team</p>
                        </div>
                    </div>
                    <p>
                        If you have questions, notices, or compliance inquiries regarding these Terms of Service, please reach out to our team:
                    </p>

                    <div class="tos-contact-box">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="tos-contact-item">
                                    <div class="tos-contact-icon">
                                        <i class="fas fa-building"></i>
                                    </div>
                                    <div>
                                        <strong class="d-block text-dark">Operating Entity</strong>
                                        <span class="text-muted">Codelock Solutions</span>
                                        <small class="d-block text-muted">Platform: <?php echo htmlspecialchars($brandName); ?></small>
                                    </div>
                                </div>

                                <div class="tos-contact-item">
                                    <div class="tos-contact-icon">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </div>
                                    <div>
                                        <strong class="d-block text-dark">Office Headquarters</strong>
                                        <span class="text-muted">Silver Business Point, Near VIP Circle, Utran, Surat, Gujarat - 394105, India</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="tos-contact-item">
                                    <div class="tos-contact-icon">
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

                                <div class="tos-contact-item">
                                    <div class="tos-contact-icon">
                                        <i class="fas fa-phone"></i>
                                    </div>
                                    <div>
                                        <strong class="d-block text-dark">Helpline & WhatsApp</strong>
                                        <a href="tel:+917600464414" class="text-decoration-none">+91 76004 64414</a>
                                        <small class="d-block text-muted">Mon - Sat: 9:30 AM - 6:30 PM IST</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-3" style="border-color: #cbd5e1;">

                        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                            <span class="text-muted small">
                                <i class="fas fa-shield-alt text-primary me-1"></i> Transparent & Fair SaaS Terms by Codelock Solutions.
                            </span>
                            <div class="d-flex gap-2">
                                <a href="privacy-policy" class="btn-tos-outline py-1 px-3">
                                    <i class="fas fa-shield-halved"></i> Privacy Policy
                                </a>
                                <a href="contact" class="btn-tos-primary py-1 px-3">
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

    <!-- Table of Contents Active Spy, Smooth Scroll & Scroll Reveal Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const tocLinks = document.querySelectorAll('.tos-toc-link');
        const sections = document.querySelectorAll('.tos-section-card');

        // Smooth scroll on TOC link click
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

            // Scroll Reveal Animation
            const reveals = document.querySelectorAll('.tos-reveal');
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
            // Fallback for older browsers
            document.querySelectorAll('.tos-reveal').forEach(el => el.classList.add('is-revealed'));
        }
    });
    </script>

</body>
</html>
