<?php
if (file_exists(__DIR__ . '/config/config.php')) {
    require_once __DIR__ . '/config/config.php';
} else {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

$page_title = 'Blog & Industry Insights';

if (file_exists(__DIR__ . '/helpers/SEOHelper.php')) {
    require_once 'helpers/SEOHelper.php';
}

$baseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$canonicalUrl = $baseUrl . '/blog';
$brandName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : (defined('APP_NAME') ? APP_NAME : 'TailorPro');

$seoOptions = [
    'title' => 'Blog & Guides - ' . $brandName . ' | Tailoring Business Tips & Insights',
    'description' => 'Explore the latest articles, tailoring business tips, digital measurement guides, boutique growth strategies, and fashion tech insights from ' . $brandName . '.',
    'keywords' => 'tailoring blog, tailor business tips, boutique management guide, digital measurement software, tailor shop growth, tailor billing tips',
    'canonical' => $canonicalUrl,
    'og_type' => 'website'
];

$articlesData = null;
$articles = [];
$dataDir = __DIR__ . '/data';
$articlesFile = $dataDir . '/articles.json';

if (file_exists($articlesFile)) {
    $jsonContent = @file_get_contents($articlesFile);
    if ($jsonContent !== false) {
        $articlesData = json_decode($jsonContent, true);
        if ($articlesData && isset($articlesData['articles']) && is_array($articlesData['articles'])) {
            $articles = $articlesData['articles'];
        }
    }
}

// Extract distinct categories
$categories = [];
foreach ($articles as $art) {
    if (!empty($art['category']) && !in_array($art['category'], $categories)) {
        $categories[] = $art['category'];
    }
}

// Featured article is the first one if available
$featuredArticle = !empty($articles) ? $articles[0] : null;
$remainingArticles = !empty($articles) ? array_slice($articles, 1) : [];
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
            --blg-primary: #4f46e5;
            --blg-primary-hover: #4338ca;
            --blg-secondary: #764ba2;
            --blg-accent: #8b5cf6;
            --blg-dark: #0f172a;
            --blg-heading: #1e1b4b;
            --blg-body: #475569;
            --blg-card-border: #e2e8f0;
            --blg-radius: 16px;
            --blg-gradient: linear-gradient(135deg, #4f46e5 0%, #667eea 50%, #764ba2 100%);
        }

        html, body {
            overflow-x: clip;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8fafc;
            color: var(--blg-body);
            line-height: 1.7;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--blg-heading);
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        /* Scroll Reveal Animations */
        .blg-reveal {
            opacity: 0;
            transform: translateY(28px);
            transition: opacity 0.7s cubic-bezier(0.16, 1, 0.3, 1), transform 0.7s cubic-bezier(0.16, 1, 0.3, 1);
            will-change: opacity, transform;
        }
        .blg-reveal.is-revealed {
            opacity: 1;
            transform: translateY(0);
        }
        .blg-delay-1 { transition-delay: 0.1s; }
        .blg-delay-2 { transition-delay: 0.2s; }
        .blg-delay-3 { transition-delay: 0.3s; }

        /* Hero Header */
        .blg-hero {
            background: var(--blg-gradient);
            color: #ffffff;
            padding: 65px 0 80px;
            position: relative;
            overflow: hidden;
        }
        .blg-hero::before {
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
        .blg-hero::after {
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

        .blg-hero .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 1.25rem;
        }
        .blg-hero .breadcrumb-item a {
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .blg-hero .breadcrumb-item a:hover {
            color: #ffffff;
            text-decoration: underline;
        }
        .blg-hero .breadcrumb-item.active {
            color: rgba(255, 255, 255, 0.95);
            font-size: 0.9rem;
        }
        .blg-hero .breadcrumb-item + .breadcrumb-item::before {
            color: rgba(255, 255, 255, 0.6);
        }

        .blg-badge {
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
            animation: blgBadgeGlow 3.5s ease-in-out infinite;
        }

        @keyframes blgBadgeGlow {
            0%, 100% { box-shadow: 0 0 12px rgba(255, 255, 255, 0.2); }
            50% { box-shadow: 0 0 25px rgba(255, 255, 255, 0.45); }
        }

        .blg-hero h1 {
            color: #ffffff;
            font-size: 2.85rem;
            font-weight: 800;
            margin-bottom: 0.85rem;
        }
        .blg-hero p.lead {
            color: rgba(255, 255, 255, 0.92);
            font-size: 1.15rem;
            max-width: 760px;
            margin: 0 auto 2rem;
        }

        /* Search Bar Inside Hero */
        .blg-search-box {
            max-width: 620px;
            margin: 0 auto;
            position: relative;
        }
        .blg-search-input {
            width: 100%;
            padding: 16px 24px 16px 52px;
            border-radius: 50px;
            border: 2px solid rgba(255, 255, 255, 0.35);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            font-size: 1rem;
            color: #1e293b;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
        }
        .blg-search-input:focus {
            outline: none;
            background: #ffffff;
            border-color: #ffffff;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
        }
        .blg-search-icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--blg-primary);
            font-size: 18px;
            pointer-events: none;
        }
        .blg-search-clear {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: #94a3b8;
            font-size: 16px;
            cursor: pointer;
            display: none;
        }
        .blg-search-clear:hover {
            color: #1e293b;
        }

        /* Category Filter Bar */
        .blg-filter-bar {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 0;
            position: sticky;
            top: 70px;
            z-index: 1020;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        }
        .blg-pills-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
            overflow-x: auto;
            scrollbar-width: none;
            padding: 4px 0;
        }
        .blg-pills-wrap::-webkit-scrollbar {
            display: none;
        }
        .blg-filter-pill {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid transparent;
            border-radius: 50px;
            padding: 8px 18px;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.25s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .blg-filter-pill:hover {
            background: #e2e8f0;
            color: #1e293b;
        }
        .blg-filter-pill.active {
            background: var(--blg-gradient);
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.3);
        }
        .blg-count-badge {
            font-size: 0.75rem;
            padding: 2px 7px;
            border-radius: 20px;
            background: rgba(0, 0, 0, 0.08);
            margin-left: 4px;
        }
        .blg-filter-pill.active .blg-count-badge {
            background: rgba(255, 255, 255, 0.25);
            color: #ffffff;
        }

        /* Section Container */
        .blg-section {
            padding: 50px 0 70px;
        }

        /* Featured Article Card */
        .blg-featured-card {
            background: #ffffff;
            border: 1px solid var(--blg-card-border);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.06);
            margin-bottom: 50px;
            transition: all 0.35s ease;
        }
        .blg-featured-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 45px rgba(79, 70, 229, 0.12);
            border-color: #cbd5e1;
        }
        .blg-featured-img-wrap {
            height: 100%;
            min-height: 320px;
            overflow: hidden;
            position: relative;
            background: #e2e8f0;
        }
        .blg-featured-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .blg-featured-card:hover .blg-featured-img-wrap img {
            transform: scale(1.06);
        }
        .blg-featured-badge {
            position: absolute;
            top: 20px;
            left: 20px;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: #ffffff;
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .blg-featured-body {
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .blg-category-tag {
            color: var(--blg-primary);
            background: rgba(79, 70, 229, 0.08);
            border: 1px solid rgba(79, 70, 229, 0.2);
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            margin-bottom: 12px;
            align-self: flex-start;
        }
        .blg-featured-title {
            font-size: 1.85rem;
            line-height: 1.3;
            margin-bottom: 14px;
        }
        .blg-featured-title a {
            color: var(--blg-heading);
            text-decoration: none;
            transition: color 0.25s ease;
        }
        .blg-featured-title a:hover {
            color: var(--blg-primary);
        }
        .blg-featured-excerpt {
            color: #64748b;
            font-size: 1.02rem;
            line-height: 1.7;
            margin-bottom: 24px;
        }

        /* Article Meta */
        .blg-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 16px;
            font-size: 0.86rem;
            color: #64748b;
            margin-bottom: 24px;
        }
        .blg-meta span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .blg-meta i {
            color: var(--blg-primary);
            font-size: 0.85rem;
        }

        /* Grid Article Cards */
        .blg-card {
            background: #ffffff;
            border: 1px solid var(--blg-card-border);
            border-radius: var(--blg-radius);
            overflow: hidden;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.04);
            height: 100%;
            display: flex;
            flex-direction: column;
            transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .blg-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 35px rgba(79, 70, 229, 0.12);
            border-color: #cbd5e1;
        }
        .blg-img-wrap {
            height: 220px;
            overflow: hidden;
            position: relative;
            background: #e2e8f0;
        }
        .blg-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .blg-card:hover .blg-img-wrap img {
            transform: scale(1.08);
        }
        .blg-img-category {
            position: absolute;
            top: 14px;
            left: 14px;
            background: rgba(15, 23, 42, 0.82);
            backdrop-filter: blur(8px);
            color: #ffffff;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }
        .blg-card-body {
            padding: 24px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .blg-card-title {
            font-size: 1.2rem;
            line-height: 1.4;
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 52px;
        }
        .blg-card-title a {
            color: var(--blg-heading);
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .blg-card-title a:hover {
            color: var(--blg-primary);
        }
        .blg-card-excerpt {
            color: #64748b;
            font-size: 0.92rem;
            line-height: 1.6;
            margin-bottom: 18px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex-grow: 1;
        }
        .blg-card-footer {
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .blg-read-btn {
            color: var(--blg-primary);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.88rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.25s ease;
        }
        .blg-read-btn i {
            transition: transform 0.25s ease;
        }
        .blg-card:hover .blg-read-btn i {
            transform: translateX(4px);
        }

        /* Empty State */
        .blg-empty-state {
            background: #ffffff;
            border: 2px dashed #e2e8f0;
            border-radius: 20px;
            padding: 60px 30px;
            text-align: center;
            margin: 30px 0;
        }
        .blg-empty-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: #f1f5f9;
            color: #94a3b8;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: 0 auto 18px;
        }

        /* Bottom CTA Banner */
        .blg-cta-banner {
            background: var(--blg-gradient);
            border-radius: 20px;
            padding: 48px 40px;
            color: #ffffff;
            margin-top: 50px;
            box-shadow: 0 16px 40px rgba(79, 70, 229, 0.2);
            position: relative;
            overflow: hidden;
        }
        .blg-cta-banner h3 {
            color: #ffffff;
            font-size: 1.9rem;
            font-weight: 800;
            margin-bottom: 8px;
        }
        .blg-cta-banner p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.05rem;
            max-width: 600px;
            margin-bottom: 0;
        }
        .btn-blg-white {
            background: #ffffff;
            color: var(--blg-primary) !important;
            border: none;
            padding: 12px 28px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.25s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .btn-blg-white:hover {
            transform: translateY(-2px);
        }

        .btn-blg-primary {
            background: var(--blg-gradient) !important;
            color: #ffffff !important;
            border: none !important;
            padding: 12px 28px !important;
            border-radius: 50px !important;
            font-weight: 700 !important;
            font-size: 0.95rem !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            text-decoration: none !important;
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.3) !important;
            transition: all 0.25s ease !important;
        }
        .btn-blg-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.45) !important;
            background: linear-gradient(135deg, #4338ca 0%, #4f46e5 50%, #6d28d9 100%) !important;
            color: #ffffff !important;
        }

        .btn-blg-outline {
            background: transparent !important;
            color: var(--blg-primary) !important;
            border: 1.5px solid var(--blg-primary) !important;
            padding: 10px 24px !important;
            border-radius: 50px !important;
            font-weight: 600 !important;
            font-size: 0.92rem !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            text-decoration: none !important;
            transition: all 0.25s ease !important;
        }
        .btn-blg-outline:hover {
            background: var(--blg-primary) !important;
            color: #ffffff !important;
            transform: translateY(-2px) !important;
        }
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
            background: #f8fafc;
        }

        @media (max-width: 991px) {
            .blg-hero {
                padding: 45px 0 55px;
            }
            .blg-hero h1 {
                font-size: 2.1rem;
            }
            .blg-featured-body {
                padding: 28px 24px;
            }
            .blg-featured-title {
                font-size: 1.4rem;
            }
            .blg-cta-banner {
                padding: 32px 24px;
                text-align: center;
            }
            .blg-cta-banner h3 {
                font-size: 1.5rem;
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
    <header class="blg-hero">
        <div class="container text-center">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb justify-content-center">
                    <li class="breadcrumb-item"><a href="./"><i class="fas fa-home me-1"></i>Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Blog & Guides</li>
                </ol>
            </nav>

            <div class="blg-badge">
                <i class="fas fa-sparkles"></i> Tailoring Guides & Industry Knowledge
            </div>

            <h1>The TailorPro Blog</h1>
            <p class="lead">
                Actionable tips for tailor shop owners, master cutters, boutique designers, and custom clothing artisans looking to grow their business and go digital.
            </p>

            <!-- Search Bar -->
            <div class="blg-search-box">
                <i class="fas fa-search blg-search-icon"></i>
                <input type="text" id="blogSearchInput" class="blg-search-input" placeholder="Search articles by title, topic, or keyword..." aria-label="Search articles">
                <button type="button" id="blogSearchClear" class="blg-search-clear" title="Clear search">
                    <i class="fas fa-times-circle"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Sticky Category Filter Bar -->
    <div class="blg-filter-bar">
        <div class="container">
            <div class="blg-pills-wrap" id="categoryFilterWrap">
                <button type="button" class="blg-filter-pill active" data-category="all">
                    <i class="fas fa-th-large"></i> All Articles 
                    <span class="blg-count-badge" id="allCountBadge"><?php echo count($articles); ?></span>
                </button>
                <?php foreach ($categories as $cat): ?>
                    <?php 
                    $catCount = 0;
                    foreach ($articles as $a) {
                        if (isset($a['category']) && $a['category'] === $cat) $catCount++;
                    }
                    ?>
                    <button type="button" class="blg-filter-pill" data-category="<?php echo htmlspecialchars($cat); ?>">
                        <?php echo htmlspecialchars($cat); ?>
                        <span class="blg-count-badge"><?php echo $catCount; ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Main Content Section -->
    <main class="blg-section">
        <div class="container">

            <?php if (empty($articles)): ?>
                <!-- Empty State if no articles in JSON -->
                <div class="blg-empty-state blg-reveal">
                    <div class="blg-empty-icon">
                        <i class="fas fa-newspaper"></i>
                    </div>
                    <h3>No Articles Found</h3>
                    <p class="text-muted">Check back soon for new guides, insights, and tailoring business tutorials.</p>
                </div>
            <?php else: ?>

                <!-- Featured Article Spotlight (Only shown when "All" is active and search is empty) -->
                <?php if ($featuredArticle): ?>
                    <div class="blg-featured-card blg-reveal" id="featuredArticleCard" data-category="<?php echo htmlspecialchars($featuredArticle['category']); ?>" data-title="<?php echo htmlspecialchars(strtolower($featuredArticle['title'])); ?>" data-excerpt="<?php echo htmlspecialchars(strtolower($featuredArticle['excerpt'])); ?>">
                        <div class="row g-0 align-items-center">
                            <div class="col-lg-6">
                                <div class="blg-featured-img-wrap">
                                    <span class="blg-featured-badge">
                                        <i class="fas fa-star"></i> Featured
                                    </span>
                                    <img src="<?php echo htmlspecialchars($featuredArticle['image']); ?>" 
                                         alt="<?php echo htmlspecialchars($featuredArticle['title']); ?>"
                                         onerror="this.onerror=null; this.src='assets/images/blog/at01.png';">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="blg-featured-body">
                                    <span class="blg-category-tag"><?php echo htmlspecialchars($featuredArticle['category']); ?></span>
                                    <h2 class="blg-featured-title">
                                        <a href="article?slug=<?php echo htmlspecialchars($featuredArticle['slug']); ?>">
                                            <?php echo htmlspecialchars($featuredArticle['title']); ?>
                                        </a>
                                    </h2>
                                    <p class="blg-featured-excerpt"><?php echo htmlspecialchars($featuredArticle['excerpt']); ?></p>
                                    
                                    <div class="blg-meta">
                                        <span><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($featuredArticle['author']); ?></span>
                                        <span><i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($featuredArticle['published_date'])); ?></span>
                                        <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($featuredArticle['read_time']); ?></span>
                                    </div>

                                    <div>
                                        <a href="article?slug=<?php echo htmlspecialchars($featuredArticle['slug']); ?>" class="btn-blg-primary">
                                            Read Full Article <i class="fas fa-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Grid of Articles -->
                <div class="row g-4" id="articlesGrid">
                    <?php 
                    // Render all articles in grid
                    foreach ($articles as $index => $article): 
                    ?>
                        <div class="col-lg-4 col-md-6 article-item blg-reveal" 
                             data-category="<?php echo htmlspecialchars($article['category']); ?>"
                             data-title="<?php echo htmlspecialchars(strtolower($article['title'])); ?>"
                             data-excerpt="<?php echo htmlspecialchars(strtolower($article['excerpt'])); ?>"
                             data-id="<?php echo $article['id']; ?>">
                            <div class="blg-card">
                                <div class="blg-img-wrap">
                                    <span class="blg-img-category"><?php echo htmlspecialchars($article['category']); ?></span>
                                    <img src="<?php echo htmlspecialchars($article['image']); ?>" 
                                         alt="<?php echo htmlspecialchars($article['title']); ?>"
                                         loading="lazy"
                                         onerror="this.onerror=null; this.src='assets/images/blog/at01.png';">
                                </div>
                                
                                <div class="blg-card-body">
                                    <div class="blg-meta mb-2">
                                        <span><i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($article['published_date'])); ?></span>
                                        <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($article['read_time']); ?></span>
                                    </div>

                                    <h3 class="blg-card-title">
                                        <a href="article?slug=<?php echo htmlspecialchars($article['slug']); ?>">
                                            <?php echo htmlspecialchars($article['title']); ?>
                                        </a>
                                    </h3>
                                    
                                    <p class="blg-card-excerpt"><?php echo htmlspecialchars($article['excerpt']); ?></p>
                                    
                                    <div class="blg-card-footer">
                                        <span class="small text-muted">
                                            <i class="fas fa-user-edit me-1 text-primary"></i> <?php echo htmlspecialchars($article['author']); ?>
                                        </span>
                                        <a href="article?slug=<?php echo htmlspecialchars($article['slug']); ?>" class="blg-read-btn">
                                            Read More <i class="fas fa-arrow-right"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- No Matching Search Results Box (Hidden by default) -->
                <div class="blg-empty-state d-none" id="noSearchResultsBox">
                    <div class="blg-empty-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>No Matching Articles Found</h3>
                    <p class="text-muted">We couldn't find any articles matching your search query or selected category.</p>
                    <button type="button" class="btn-blg-outline mt-2" id="resetFilterBtn">
                        <i class="fas fa-redo me-1"></i> Reset Filters & Show All
                    </button>
                </div>

                <!-- Bottom CTA Card -->
                <div class="blg-cta-banner blg-reveal">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h3>Ready to Transform Your Tailoring Business?</h3>
                            <p>
                                Experience the smart digital measurement book, instant WhatsApp receipts, and automated order tracking tailored specifically for boutiques and tailors.
                            </p>
                        </div>
                        <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                            <a href="admin/register.php" class="btn-blg-white">
                                <i class="fas fa-rocket"></i> Get Started Free
                            </a>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

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

    <!-- Client-Side Search, Category Filtering & Scroll Reveal Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // --- 1. Scroll Reveal ---
        const reveals = document.querySelectorAll('.blg-reveal');
        if (reveals.length > 0) {
            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-revealed');
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.08, rootMargin: '0px 0px -30px 0px' });
                reveals.forEach(el => observer.observe(el));
            } else {
                reveals.forEach(el => el.classList.add('is-revealed'));
            }
        }

        // --- 2. Live Search & Category Filtering ---
        const searchInput = document.getElementById('blogSearchInput');
        const clearBtn = document.getElementById('blogSearchClear');
        const filterPills = document.querySelectorAll('.blg-filter-pill');
        const articleItems = document.querySelectorAll('.article-item');
        const featuredCard = document.getElementById('featuredArticleCard');
        const noResultsBox = document.getElementById('noSearchResultsBox');
        const resetBtn = document.getElementById('resetFilterBtn');

        let activeCategory = 'all';
        let searchQuery = '';

        function filterArticles() {
            let visibleCount = 0;

            articleItems.forEach(item => {
                const cat = item.getAttribute('data-category') || '';
                const title = item.getAttribute('data-title') || '';
                const excerpt = item.getAttribute('data-excerpt') || '';

                const matchesCat = (activeCategory === 'all' || cat === activeCategory);
                const matchesSearch = !searchQuery || title.includes(searchQuery) || excerpt.includes(searchQuery) || cat.toLowerCase().includes(searchQuery);

                if (matchesCat && matchesSearch) {
                    item.style.display = '';
                    item.classList.add('is-revealed');
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            // Featured card visibility
            if (featuredCard) {
                if (activeCategory === 'all' && !searchQuery) {
                    featuredCard.style.display = '';
                } else {
                    // Check if featured card matches
                    const featCat = featuredCard.getAttribute('data-category') || '';
                    const featTitle = featuredCard.getAttribute('data-title') || '';
                    const featExcerpt = featuredCard.getAttribute('data-excerpt') || '';
                    const mCat = (activeCategory === 'all' || featCat === activeCategory);
                    const mSearch = !searchQuery || featTitle.includes(searchQuery) || featExcerpt.includes(searchQuery);
                    
                    if (mCat && mSearch) {
                        featuredCard.style.display = '';
                        visibleCount++;
                    } else {
                        featuredCard.style.display = 'none';
                    }
                }
            }

            // Show empty state if no articles found
            if (visibleCount === 0) {
                if (noResultsBox) noResultsBox.classList.remove('d-none');
            } else {
                if (noResultsBox) noResultsBox.classList.add('d-none');
            }
        }

        // Search Input Event
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                searchQuery = this.value.trim().toLowerCase();
                if (clearBtn) {
                    clearBtn.style.display = searchQuery ? 'block' : 'none';
                }
                filterArticles();
            });
        }

        // Clear Search Button
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                if (searchInput) {
                    searchInput.value = '';
                    searchQuery = '';
                    this.style.display = 'none';
                    filterArticles();
                    searchInput.focus();
                }
            });
        }

        // Category Pills Click
        filterPills.forEach(pill => {
            pill.addEventListener('click', function () {
                filterPills.forEach(p => p.classList.remove('active'));
                this.classList.add('active');
                activeCategory = this.getAttribute('data-category');
                filterArticles();
            });
        });

        // Reset Filter Button in Empty State
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                activeCategory = 'all';
                searchQuery = '';
                if (searchInput) searchInput.value = '';
                if (clearBtn) clearBtn.style.display = 'none';
                filterPills.forEach(p => {
                    if (p.getAttribute('data-category') === 'all') p.classList.add('active');
                    else p.classList.remove('active');
                });
                filterArticles();
            });
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
                $pageTitle = 'Blog - ' . $brandName;
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
