<?php

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (empty($slug)) {
    header('Location: blog.php');
    exit;
}

$articlesData = null;
$article = null;
$dataDir = __DIR__ . '/data';
$articlesFile = $dataDir . '/articles.json';

if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}

if (file_exists($articlesFile)) {
    $jsonContent = @file_get_contents($articlesFile);
    if ($jsonContent !== false) {
        $articlesData = json_decode($jsonContent, true);
        if ($articlesData && isset($articlesData['articles']) && is_array($articlesData['articles'])) {
            foreach ($articlesData['articles'] as $art) {
                if (isset($art['slug']) && $art['slug'] === $slug) {
                    $article = $art;
                    break;
                }
            }
        }
    }
}

if (!$article) {
    header('Location: blog.php');
    exit;
}

// Compute Previous, Next, and Related Articles
$allArticles = (isset($articlesData['articles']) && is_array($articlesData['articles'])) ? $articlesData['articles'] : [];
$currentIndex = -1;
foreach ($allArticles as $idx => $item) {
    if (isset($item['slug']) && $item['slug'] === $slug) {
        $currentIndex = $idx;
        break;
    }
}

$prevArticle = ($currentIndex > 0) ? $allArticles[$currentIndex - 1] : null;
$nextArticle = ($currentIndex >= 0 && $currentIndex < count($allArticles) - 1) ? $allArticles[$currentIndex + 1] : null;

// Collect up to 3 related articles (first same category, then others)
$relatedArticles = [];
foreach ($allArticles as $item) {
    if ($item['slug'] !== $slug && isset($item['category']) && $item['category'] === $article['category']) {
        $relatedArticles[] = $item;
    }
    if (count($relatedArticles) >= 3) break;
}
if (count($relatedArticles) < 3) {
    foreach ($allArticles as $item) {
        if ($item['slug'] !== $slug) {
            $alreadyAdded = false;
            foreach ($relatedArticles as $rel) {
                if ($rel['slug'] === $item['slug']) {
                    $alreadyAdded = true;
                    break;
                }
            }
            if (!$alreadyAdded) {
                $relatedArticles[] = $item;
            }
        }
        if (count($relatedArticles) >= 3) break;
    }
}

$backLink = 'blog'; 
$backText = 'Back to All Articles';
if (isset($_GET['from']) && $_GET['from'] === 'index') {
    $backLink = './#blog';
    $backText = 'Back to Home';
} else {
    $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    if (!empty($referrer)) {
        $referrerPath = parse_url($referrer, PHP_URL_PATH);
        if (strpos($referrerPath, 'index.php') !== false || 
            $referrerPath === '/' || 
            empty(parse_url($referrer, PHP_URL_PATH)) ||
            basename($referrerPath) === 'index.php') {
            $backLink = './#blog';
            $backText = 'Back to Home';
        }
    }
}

$brandName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : (defined('APP_NAME') ? APP_NAME : 'TailorPro');
$page_title = $article['title'];

if (file_exists(__DIR__ . '/helpers/SEOHelper.php')) {
    require_once 'helpers/SEOHelper.php';
}

$baseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$canonicalUrl = $baseUrl . '/article?slug=' . urlencode($slug);

$seoOptions = [
    'title' => htmlspecialchars($article['title']) . ' - ' . $brandName,
    'description' => htmlspecialchars($article['excerpt']),
    'keywords' => implode(', ', $article['tags'] ?? []),
    'canonical' => $canonicalUrl,
    'og_type' => 'article',
    'og_image' => $baseUrl . '/' . $article['image']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    if (class_exists('SEOHelper')) {
        echo SEOHelper::generateMetaTags($seoOptions);
    } else {
        echo '<title>' . htmlspecialchars($seoOptions['title']) . '</title>';
        echo '<meta name="description" content="' . htmlspecialchars($seoOptions['description']) . '">';
    }
    ?>
    <?php
    if (file_exists(__DIR__ . '/helpers/GA4Helper.php')) {
        require_once 'helpers/GA4Helper.php';
        if (class_exists('GA4Helper')) {
            echo GA4Helper::generateBaseCode();
        }
    }
    ?>
    <!-- JSON-LD Structured Data for Rich Blog Snippet -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "BlogPosting",
      "headline": <?php echo json_encode($article['title']); ?>,
      "description": <?php echo json_encode($article['excerpt']); ?>,
      "image": <?php echo json_encode($baseUrl . '/' . $article['image']); ?>,
      "datePublished": <?php echo json_encode($article['published_date']); ?>,
      "author": {
        "@type": "Organization",
        "name": <?php echo json_encode($article['author'] ?? $brandName); ?>
      },
      "publisher": {
        "@type": "Organization",
        "name": <?php echo json_encode($brandName); ?>,
        "logo": {
          "@type": "ImageObject",
          "url": <?php echo json_encode($baseUrl . '/assets/images/favicon(2).png'); ?>
        }
      },
      "mainEntityOfPage": {
        "@type": "WebPage",
        "@id": <?php echo json_encode($canonicalUrl); ?>
      }
    }
    </script>

    <link rel="icon" type="image/x-icon" href="favicon.ico" sizes="16x16 32x32 48x48">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon(2).png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon(2).png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/favicon(2).png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link href="assets/css/style16.css" rel="stylesheet">
    <style>
        :root {
            --art-primary: #4f46e5;
            --art-primary-hover: #4338ca;
            --art-secondary: #764ba2;
            --art-accent: #8b5cf6;
            --art-dark: #0f172a;
            --art-heading: #1e1b4b;
            --art-body: #334155;
            --art-gradient: linear-gradient(135deg, #4f46e5 0%, #667eea 50%, #764ba2 100%);
            --art-card-border: #e2e8f0;
            --art-card-radius: 20px;
        }

        html, body {
            overflow-x: clip;
            scroll-padding-top: 115px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8fafc;
            color: var(--art-body);
            line-height: 1.8;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--art-heading);
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        /* Reading Progress Bar (Fixed Top) */
        .art-reading-progress {
            position: fixed;
            top: 0;
            left: 0;
            width: 0%;
            height: 4px;
            background: linear-gradient(90deg, #4f46e5 0%, #764ba2 50%, #ec4899 100%);
            z-index: 99999;
            transition: width 0.1s linear;
            box-shadow: 0 1px 6px rgba(79, 70, 229, 0.4);
        }

        /* Hero Header */
        .art-hero {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 45%, #4338ca 100%);
            color: #ffffff;
            padding: 55px 0 85px;
            position: relative;
            overflow: hidden;
        }
        .art-hero::before {
            content: '';
            position: absolute;
            top: -40%;
            right: -10%;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.25) 0%, rgba(255, 255, 255, 0) 70%);
            pointer-events: none;
        }
        .art-hero::after {
            content: '';
            position: absolute;
            bottom: -35%;
            left: -8%;
            width: 420px;
            height: 420px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(236, 72, 153, 0.18) 0%, rgba(255, 255, 255, 0) 70%);
            pointer-events: none;
        }

        .art-hero .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 1.25rem;
        }
        .art-hero .breadcrumb-item a {
            color: rgba(255, 255, 255, 0.82);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: color 0.2s ease;
        }
        .art-hero .breadcrumb-item a:hover {
            color: #ffffff;
            text-decoration: underline;
        }
        .art-hero .breadcrumb-item.active {
            color: rgba(255, 255, 255, 0.95);
            font-size: 0.9rem;
            max-width: 320px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: inline-block;
            vertical-align: bottom;
        }
        .art-hero .breadcrumb-item + .breadcrumb-item::before {
            color: rgba(255, 255, 255, 0.5);
            content: "›";
            font-size: 1.1rem;
            line-height: 1;
        }

        .art-cat-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(255, 255, 255, 0.16);
            backdrop-filter: blur(10px);
            color: #ffffff;
            padding: 6px 18px;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 1.25rem;
            border: 1px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .art-main-title {
            color: #ffffff;
            font-size: 2.6rem;
            font-weight: 800;
            line-height: 1.26;
            margin-bottom: 1.25rem;
            letter-spacing: -0.025em;
        }

        .art-hero-excerpt {
            color: rgba(255, 255, 255, 0.88);
            font-size: 1.15rem;
            line-height: 1.7;
            max-width: 880px;
            margin-bottom: 1.75rem;
        }

        .art-meta-strip {
            display: flex;
            align-items: center;
            gap: 22px;
            flex-wrap: wrap;
            font-size: 0.92rem;
            color: rgba(255, 255, 255, 0.9);
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
        }
        .art-meta-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .art-meta-author-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.22);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 700;
            border: 1.5px solid rgba(255, 255, 255, 0.4);
        }

        /* Main Container Overlapping Hero */
        .art-main-wrapper {
            margin-top: -45px;
            position: relative;
            z-index: 10;
        }

        /* Article Main Card */
        .art-content-card {
            background: #ffffff;
            border: 1px solid var(--art-card-border);
            border-radius: var(--art-card-radius);
            padding: 44px 48px;
            box-shadow: 0 12px 35px rgba(15, 23, 42, 0.05);
            margin-bottom: 35px;
        }

        .art-top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 22px;
            margin-bottom: 30px;
            border-bottom: 1px solid #f1f5f9;
            flex-wrap: wrap;
            gap: 15px;
        }
        .art-back-btn {
            background: #f8fafc;
            color: var(--art-primary);
            border: 1px solid #e2e8f0;
            padding: 8px 18px;
            border-radius: 50px;
            font-size: 0.88rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.25s ease;
        }
        .art-back-btn:hover {
            background: var(--art-primary);
            color: #ffffff;
            border-color: var(--art-primary);
            transform: translateX(-3px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
        }

        .art-reading-time-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f1f5f9;
            color: #475569;
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.84rem;
            font-weight: 600;
        }

        /* Featured Image */
        .art-featured-img-wrap {
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 36px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            position: relative;
            background: #f1f5f9;
        }
        .art-featured-img-wrap img {
            width: 100%;
            max-height: 480px;
            object-fit: cover;
            display: block;
            transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .art-featured-img-wrap:hover img {
            transform: scale(1.02);
        }
        .art-img-caption-tag {
            position: absolute;
            bottom: 14px;
            right: 14px;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(6px);
            color: #ffffff;
            padding: 5px 14px;
            border-radius: 50px;
            font-size: 0.76rem;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        /* Article Typography */
        .art-body-text {
            color: #334155;
            font-size: 1.08rem;
            line-height: 1.9;
        }
        .art-lead-p {
            font-size: 1.22rem !important;
            font-weight: 500;
            color: #0f172a !important;
            line-height: 1.85 !important;
            margin-bottom: 2rem !important;
            position: relative;
        }
        .art-lead-p::first-letter {
            font-size: 2.8rem;
            line-height: 0.9;
            float: left;
            margin: 0.1em 0.15em 0 0;
            font-weight: 800;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--art-primary);
        }
        .art-body-text p {
            margin-bottom: 1.8rem;
        }

        /* In-Article Pro-Tip Highlight Callout */
        .art-highlight-callout {
            background: linear-gradient(135deg, #f5f3ff 0%, #eef2ff 100%);
            border: 1px solid #c7d2fe;
            border-left: 5px solid var(--art-primary);
            border-radius: 14px;
            padding: 24px 28px;
            margin: 35px 0;
            position: relative;
        }
        .art-callout-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            color: var(--art-primary);
            font-weight: 700;
            font-size: 1rem;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .art-callout-header i {
            font-size: 1.2rem;
            color: #f59e0b;
        }
        .art-callout-body {
            color: #334155;
            font-size: 0.98rem;
            line-height: 1.7;
            margin: 0;
        }

        /* Tags Section */
        .art-tags-row {
            margin-top: 40px;
            padding-top: 24px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 9px;
        }
        .art-tag-label {
            font-weight: 700;
            font-size: 0.9rem;
            color: #0f172a;
            margin-right: 5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .art-tag-pill {
            background: #f1f5f9;
            color: #475569;
            padding: 6px 15px;
            border-radius: 50px;
            font-size: 0.84rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.25s ease;
            border: 1px solid #e2e8f0;
        }
        .art-tag-pill:hover {
            background: var(--art-primary);
            color: #ffffff;
            border-color: var(--art-primary);
            transform: translateY(-2px);
        }

        /* Social Share Box */
        .art-share-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px 28px;
            margin: 35px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }
        .art-share-title {
            font-weight: 700;
            font-size: 1rem;
            color: var(--art-heading);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 9px;
        }
        .art-share-btns {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .art-sbtn {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #ffffff !important;
            text-decoration: none;
            transition: all 0.25s ease;
            font-size: 1rem;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.08);
            border: none;
        }
        .art-sbtn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.15);
        }
        .art-sbtn.wa { background: #25d366; }
        .art-sbtn.fb { background: #1877f2; }
        .art-sbtn.tw { background: #0f1419; }
        .art-sbtn.li { background: #0a66c2; }
        .art-sbtn.cp { background: #64748b; }
        .art-sbtn.cp.copied { background: #10b981; }

        /* Author Bio Card */
        .art-author-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 26px 30px;
            margin: 35px 0;
            display: flex;
            align-items: center;
            gap: 22px;
        }
        .art-author-avatar-lg {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4f46e5 0%, #764ba2 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            flex-shrink: 0;
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.25);
        }
        .art-author-meta h4 {
            font-size: 1.15rem;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .art-author-badge {
            background: #e0e7ff;
            color: var(--art-primary);
            font-size: 0.72rem;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 50px;
            text-transform: uppercase;
        }
        .art-author-meta p {
            color: #64748b;
            font-size: 0.92rem;
            line-height: 1.6;
            margin: 0;
        }

        /* Next / Prev Navigation Cards */
        .art-adjacent-nav {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid #f1f5f9;
        }
        .art-nav-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 20px 24px;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            justify-content: center;
            height: 100%;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
        }
        .art-nav-card:hover {
            border-color: var(--art-primary);
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.08);
        }
        .art-nav-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--art-primary);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .art-nav-card.next .art-nav-label {
            justify-content: flex-end;
        }
        .art-nav-title {
            color: var(--art-heading);
            font-size: 0.98rem;
            font-weight: 700;
            line-height: 1.45;
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .art-nav-card.next .art-nav-title {
            text-align: right;
        }
        .art-nav-card:hover .art-nav-title {
            color: var(--art-primary);
        }

        /* Sticky Sidebar */
        .art-sidebar {
            position: sticky;
            top: 115px;
            align-self: flex-start;
        }

        /* Sidebar Widget 1: TailorPro Software CTA */
        .art-side-cta {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 50%, #764ba2 100%);
            border-radius: 18px;
            padding: 32px 26px;
            color: #ffffff;
            margin-bottom: 26px;
            box-shadow: 0 12px 30px rgba(79, 70, 229, 0.22);
            position: relative;
            overflow: hidden;
        }
        .art-side-cta::before {
            content: '';
            position: absolute;
            top: -30%;
            right: -25%;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.16) 0%, rgba(255, 255, 255, 0) 70%);
            pointer-events: none;
        }
        .art-side-cta-badge {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(6px);
            color: #ffffff;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            display: inline-block;
            margin-bottom: 12px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .art-side-cta h3 {
            color: #ffffff;
            font-size: 1.35rem;
            font-weight: 800;
            line-height: 1.3;
            margin-bottom: 12px;
        }
        .art-side-cta p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 18px;
        }
        .art-side-features {
            list-style: none;
            padding: 0;
            margin: 0 0 22px 0;
        }
        .art-side-features li {
            color: rgba(255, 255, 255, 0.95);
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 9px;
            display: flex;
            align-items: center;
            gap: 9px;
        }
        .art-side-features li i {
            color: #34d399;
            font-size: 0.9rem;
        }
        .btn-art-sidebar {
            background: #ffffff !important;
            color: var(--art-primary) !important;
            border: none !important;
            padding: 13px 24px !important;
            border-radius: 50px !important;
            font-weight: 700 !important;
            font-size: 0.95rem !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            width: 100% !important;
            text-align: center !important;
            text-decoration: none !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12) !important;
            transition: all 0.25s ease !important;
        }
        .btn-art-sidebar:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.18) !important;
            background: #f8fafc !important;
            color: var(--art-primary-hover) !important;
        }
        .art-side-trust {
            text-align: center;
            font-size: 0.74rem;
            color: rgba(255, 255, 255, 0.8);
            margin-top: 10px;
            margin-bottom: 0;
        }

        /* Sidebar Widget 2: Related Articles */
        .art-side-widget {
            background: #ffffff;
            border: 1px solid var(--art-card-border);
            border-radius: 18px;
            padding: 26px 24px;
            margin-bottom: 26px;
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.03);
        }
        .art-widget-header {
            font-size: 1.08rem;
            font-weight: 800;
            color: var(--art-heading);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .art-rel-list {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .art-rel-item {
            display: flex;
            gap: 14px;
            text-decoration: none;
            transition: transform 0.2s ease;
        }
        .art-rel-item:hover {
            transform: translateX(3px);
        }
        .art-rel-thumb {
            width: 80px;
            height: 65px;
            border-radius: 10px;
            overflow: hidden;
            flex-shrink: 0;
            background: #f1f5f9;
        }
        .art-rel-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        .art-rel-item:hover .art-rel-thumb img {
            transform: scale(1.08);
        }
        .art-rel-content {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .art-rel-cat {
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--art-primary);
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 3px;
        }
        .art-rel-title {
            font-size: 0.88rem;
            font-weight: 700;
            line-height: 1.35;
            color: var(--art-heading);
            margin-bottom: 4px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            transition: color 0.2s ease;
        }
        .art-rel-item:hover .art-rel-title {
            color: var(--art-primary);
        }
        .art-rel-date {
            font-size: 0.75rem;
            color: #94a3b8;
        }

        /* Sidebar Widget 3: WhatsApp Support */
        .art-side-support {
            background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
            border: 1px solid #bbf7d0;
            border-radius: 18px;
            padding: 24px;
            text-align: center;
        }
        .art-support-icon {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: #22c55e;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 12px;
            box-shadow: 0 4px 14px rgba(34, 197, 94, 0.35);
        }
        .art-side-support h4 {
            font-size: 1.05rem;
            margin-bottom: 6px;
            color: #14532d;
        }
        .art-side-support p {
            font-size: 0.86rem;
            color: #166534;
            line-height: 1.5;
            margin-bottom: 14px;
        }
        .btn-art-whatsapp {
            background: #25d366 !important;
            color: #ffffff !important;
            border: none !important;
            padding: 10px 20px !important;
            border-radius: 50px !important;
            font-weight: 700 !important;
            font-size: 0.88rem !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 7px !important;
            text-decoration: none !important;
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3) !important;
            transition: all 0.25s ease !important;
        }
        .btn-art-whatsapp:hover {
            background: #1ebc57 !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 16px rgba(37, 211, 102, 0.4) !important;
            color: #ffffff !important;
        }

        /* Bottom Full-Width CTA Banner */
        .art-bottom-cta {
            background: var(--art-gradient);
            border-radius: 24px;
            padding: 50px 45px;
            color: #ffffff;
            margin: 60px 0 30px;
            box-shadow: 0 18px 45px rgba(79, 70, 229, 0.22);
            position: relative;
            overflow: hidden;
        }
        .art-bottom-cta::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.16) 0%, rgba(255, 255, 255, 0) 70%);
            pointer-events: none;
        }
        .art-bottom-cta h3 {
            color: #ffffff;
            font-size: 1.95rem;
            font-weight: 800;
            margin-bottom: 10px;
        }
        .art-bottom-cta p {
            color: rgba(255, 255, 255, 0.92);
            font-size: 1.05rem;
            max-width: 650px;
            margin-bottom: 0;
            line-height: 1.6;
        }
        .btn-art-primary {
            background: var(--art-gradient) !important;
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
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.35) !important;
            transition: all 0.25s ease !important;
        }
        .btn-art-primary:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.5) !important;
            color: #ffffff !important;
        }
        .art-cta-actions {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: 14px;
            flex-wrap: wrap;
        }

        .btn-art-white {
            background: #ffffff !important;
            color: var(--art-primary) !important;
            border: 2px solid #ffffff !important;
            padding: 12px 26px !important;
            border-radius: 50px !important;
            font-weight: 700 !important;
            font-size: 0.95rem !important;
            line-height: 1.4 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            white-space: nowrap !important;
            text-decoration: none !important;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12) !important;
            transition: all 0.25s ease !important;
        }
        .btn-art-white:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18) !important;
            background: #f8fafc !important;
            border-color: #f8fafc !important;
            color: var(--art-primary-hover) !important;
        }

        .btn-art-glass {
            background: rgba(255, 255, 255, 0.18) !important;
            backdrop-filter: blur(10px) !important;
            -webkit-backdrop-filter: blur(10px) !important;
            color: #ffffff !important;
            border: 1.5px solid rgba(255, 255, 255, 0.45) !important;
            padding: 12px 26px !important;
            border-radius: 50px !important;
            font-weight: 700 !important;
            font-size: 0.95rem !important;
            line-height: 1.4 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            white-space: nowrap !important;
            text-decoration: none !important;
            transition: all 0.25s ease !important;
        }
        .btn-art-glass:hover {
            background: #ffffff !important;
            color: var(--art-primary) !important;
            border-color: #ffffff !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15) !important;
        }

        /* Copy Toast */
        .art-copy-toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #0f172a;
            color: #ffffff;
            padding: 12px 24px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            box-shadow: 0 8px 25px rgba(0,0,0,0.25);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            z-index: 99999;
            opacity: 0;
            transform: translateY(20px);
            pointer-events: none;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .art-copy-toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        /* Responsive Breakpoints */
        @media (max-width: 991px) {
            .art-hero {
                padding: 45px 0 70px;
            }
            .art-main-title {
                font-size: 2.1rem;
            }
            .art-content-card {
                padding: 30px 24px;
                border-radius: 18px;
            }
            .art-sidebar {
                position: static;
                margin-top: 30px;
            }
            .art-bottom-cta {
                padding: 36px 28px;
                text-align: center;
            }
            .art-bottom-cta p {
                margin: 0 auto 22px auto;
            }
            .art-cta-actions {
                justify-content: center !important;
                width: 100%;
                margin-top: 15px;
            }
        }

        @media (max-width: 576px) {
            .art-hero {
                padding: 35px 0 60px;
            }
            .art-main-title {
                font-size: 1.65rem;
            }
            .art-hero-excerpt {
                font-size: 1rem;
            }
            .art-content-card {
                padding: 22px 18px;
                border-radius: 16px;
            }
            .art-body-text {
                font-size: 1rem;
            }
            .art-lead-p {
                font-size: 1.1rem !important;
            }
            .art-lead-p::first-letter {
                font-size: 2.2rem;
            }
            .art-author-card {
                flex-direction: column;
                text-align: center;
                gap: 14px;
                padding: 20px;
            }
            .art-author-meta h4 {
                justify-content: center;
            }
            .art-share-card {
                flex-direction: column;
                align-items: flex-start;
            }
            .art-cta-actions {
                flex-direction: column;
                width: 100%;
            }
            .art-cta-actions .btn-art-white,
            .art-cta-actions .btn-art-glass {
                width: 100% !important;
            }
        }
    </style>
</head>
<body>

    <!-- Reading Progress Bar -->
    <div id="readingProgressBar" class="art-reading-progress" aria-hidden="true"></div>

    <?php require_once 'includes/nav.php'; ?>

    <!-- Article Hero Header -->
    <header class="art-hero">
        <div class="container">
            <div class="row">
                <div class="col-lg-11 col-xl-10 mx-auto">
                    
                    <!-- Breadcrumbs -->
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="./"><i class="fas fa-home me-1"></i>Home</a></li>
                            <li class="breadcrumb-item"><a href="blog"><i class="fas fa-newspaper me-1"></i>Blog</a></li>
                            <li class="breadcrumb-item"><a href="blog?category=<?php echo urlencode($article['category']); ?>"><?php echo htmlspecialchars($article['category']); ?></a></li>
                            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($article['title']); ?></li>
                        </ol>
                    </nav>

                    <!-- Category Badge -->
                    <div>
                        <span class="art-cat-pill">
                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($article['category']); ?>
                        </span>
                    </div>

                    <!-- Article Title -->
                    <h1 class="art-main-title"><?php echo htmlspecialchars($article['title']); ?></h1>

                    <!-- Article Excerpt / Subheading -->
                    <?php if (!empty($article['excerpt'])): ?>
                        <p class="art-hero-excerpt"><?php echo htmlspecialchars($article['excerpt']); ?></p>
                    <?php endif; ?>

                    <!-- Meta Strip -->
                    <div class="art-meta-strip">
                        <div class="art-meta-item">
                            <div class="art-meta-author-avatar">
                                <i class="fas fa-user-pen"></i>
                            </div>
                            <span>By <strong><?php echo htmlspecialchars($article['author'] ?? 'TailorPro Team'); ?></strong></span>
                        </div>
                        <div class="art-meta-item">
                            <i class="fas fa-calendar-days text-light"></i>
                            <span><?php echo date('F d, Y', strtotime($article['published_date'])); ?></span>
                        </div>
                        <div class="art-meta-item">
                            <i class="fas fa-clock text-light"></i>
                            <span><?php echo htmlspecialchars($article['read_time']); ?></span>
                        </div>
                        <div class="art-meta-item d-none d-sm-inline-flex">
                            <i class="fas fa-shield-halved text-success"></i>
                            <span>Verified Industry Guide</span>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Layout -->
    <main class="container art-main-wrapper mb-5">
        <div class="row">
            
            <!-- Left Main Column (col-lg-8) -->
            <div class="col-lg-8">
                <article class="art-content-card">
                    
                    <!-- Top Action Bar -->
                    <div class="art-top-bar">
                        <a href="<?php echo htmlspecialchars($backLink); ?>" class="art-back-btn">
                            <i class="fas fa-arrow-left"></i> <?php echo htmlspecialchars($backText); ?>
                        </a>
                        <div class="art-reading-time-pill">
                            <i class="fas fa-book-open text-primary"></i> <?php echo htmlspecialchars($article['read_time']); ?>
                        </div>
                    </div>

                    <!-- Featured Hero Image -->
                    <?php if (!empty($article['image'])): ?>
                        <div class="art-featured-img-wrap">
                            <img src="<?php echo htmlspecialchars($article['image']); ?>" 
                                 alt="<?php echo htmlspecialchars($article['title']); ?>" 
                                 onerror="this.onerror=null; this.src='assets/images/blog/at01.png';">
                            <div class="art-img-caption-tag">
                                <i class="fas fa-scissors me-1 text-warning"></i> TailorPro Knowledge Series
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Article Content Paragraphs -->
                    <div class="art-body-text">
                        <?php if (isset($article['content']) && is_array($article['content'])): ?>
                            <?php 
                            $totalParagraphs = count($article['content']);
                            $midPoint = (int) floor($totalParagraphs / 2);
                            ?>
                            <?php foreach ($article['content'] as $idx => $paragraph): ?>
                                <?php if ($idx === 0): ?>
                                    <p class="art-lead-p"><?php echo nl2br(htmlspecialchars($paragraph)); ?></p>
                                <?php else: ?>
                                    <p><?php echo nl2br(htmlspecialchars($paragraph)); ?></p>
                                <?php endif; ?>

                                <?php // Insert Pro Tip callout card around middle of article ?>
                                <?php if ($idx === $midPoint && $totalParagraphs >= 4): ?>
                                    <div class="art-highlight-callout">
                                        <div class="art-callout-header">
                                            <i class="fas fa-lightbulb"></i>
                                            <span>TailorPro Business Insight</span>
                                        </div>
                                        <p class="art-callout-body">
                                            Digitizing your measurement records and order workflow eliminates up to 95% of measurement disputes and saves 3+ hours daily. TailorPro automatically stores client sizing history securely in the cloud and notifies clients via WhatsApp when trials are ready.
                                        </p>
                                    </div>
                                <?php endif; ?>

                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Interactive Tags Cloud -->
                    <?php if (isset($article['tags']) && is_array($article['tags']) && !empty($article['tags'])): ?>
                        <div class="art-tags-row">
                            <span class="art-tag-label"><i class="fas fa-tags text-primary"></i> Topics:</span>
                            <?php foreach ($article['tags'] as $tag): ?>
                                <a href="blog?category=<?php echo urlencode($article['category']); ?>" class="art-tag-pill">#<?php echo htmlspecialchars($tag); ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Social Share Box -->
                    <div class="art-share-card">
                        <h3 class="art-share-title">
                            <i class="fas fa-share-nodes text-primary"></i> Share this with other tailors:
                        </h3>
                        <div class="art-share-btns">
                            <a href="https://api.whatsapp.com/send?text=<?php echo urlencode($article['title'] . ' - ' . $canonicalUrl); ?>" 
                               target="_blank" 
                               rel="noopener noreferrer"
                               class="art-sbtn wa" 
                               title="Share on WhatsApp">
                                <i class="fab fa-whatsapp"></i>
                            </a>
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($canonicalUrl); ?>" 
                               target="_blank" 
                               rel="noopener noreferrer"
                               class="art-sbtn fb" 
                               title="Share on Facebook">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode($article['title']); ?>&url=<?php echo urlencode($canonicalUrl); ?>" 
                               target="_blank" 
                               rel="noopener noreferrer"
                               class="art-sbtn tw" 
                               title="Share on X (Twitter)">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="#ffffff" style="display:inline-block; vertical-align:middle;">
                                    <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                                </svg>
                            </a>
                            <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode($canonicalUrl); ?>" 
                               target="_blank" 
                               rel="noopener noreferrer"
                               class="art-sbtn li" 
                               title="Share on LinkedIn">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                            <button type="button" 
                                    class="art-sbtn cp" 
                                    id="copyLinkBtn" 
                                    title="Copy Article Link">
                                <i class="fas fa-link"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Author Profile Bio Box -->
                    <div class="art-author-card">
                        <div class="art-author-avatar-lg">
                            <i class="fas fa-scissors"></i>
                        </div>
                        <div class="art-author-meta">
                            <h4>
                                <?php echo htmlspecialchars($article['author'] ?? 'TailorPro Editorial Team'); ?>
                                <span class="art-author-badge"><i class="fas fa-check-circle me-1"></i>Verified Editor</span>
                            </h4>
                            <p>
                                Written by industry specialists at <?php echo htmlspecialchars($brandName); ?>. We empower tailors, boutiques, and fashion designers across the country with smart cloud tools for measurements, order workflows, and automated WhatsApp communication.
                            </p>
                        </div>
                    </div>

                    <!-- Previous & Next Article Navigation -->
                    <?php if ($prevArticle || $nextArticle): ?>
                        <div class="art-adjacent-nav">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <?php if ($prevArticle): ?>
                                        <a href="article?slug=<?php echo urlencode($prevArticle['slug']); ?>" class="art-nav-card prev">
                                            <span class="art-nav-label"><i class="fas fa-arrow-left"></i> Previous Article</span>
                                            <h4 class="art-nav-title"><?php echo htmlspecialchars($prevArticle['title']); ?></h4>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <?php if ($nextArticle): ?>
                                        <a href="article?slug=<?php echo urlencode($nextArticle['slug']); ?>" class="art-nav-card next">
                                            <span class="art-nav-label">Next Article <i class="fas fa-arrow-right"></i></span>
                                            <h4 class="art-nav-title"><?php echo htmlspecialchars($nextArticle['title']); ?></h4>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                </article>
            </div>

            <!-- Right Sticky Sidebar (col-lg-4) -->
            <div class="col-lg-4">
                <aside class="art-sidebar">

                    <!-- Widget 1: TailorPro Cloud Software Promo -->
                    <div class="art-side-cta">
                        <span class="art-side-cta-badge"><i class="fas fa-crown text-warning me-1"></i> TAILOR SOFTWARE</span>
                        <h3>Run Your Tailor Shop 100% Digitally</h3>
                        <p>Say goodbye to lost paper diaries. Save customer measurements securely and run your business like a modern boutique.</p>
                        
                        <ul class="art-side-features">
                            <li><i class="fas fa-check-circle"></i> Digital Measurement Book (Always Safe)</li>
                            <li><i class="fas fa-check-circle"></i> Automatic WhatsApp Trial & Order SMS</li>
                            <li><i class="fas fa-check-circle"></i> Karigar (Worker) Job Cards & Khata</li>
                            <li><i class="fas fa-check-circle"></i> Professional PDF Invoices with QR Code</li>
                        </ul>

                        <a href="admin/register.php" class="btn-art-sidebar">
                            <i class="fas fa-rocket me-1"></i> Start Free Registration
                        </a>
                        <p class="art-side-trust"><i class="fas fa-lock me-1"></i> No Credit Card Required • Instant Setup</p>
                    </div>

                    <!-- Widget 2: Related & Trending Articles -->
                    <?php if (!empty($relatedArticles)): ?>
                        <div class="art-side-widget">
                            <div class="art-widget-header">
                                <span><i class="fas fa-fire text-danger me-2"></i> Related Articles</span>
                                <span class="badge bg-light text-primary"><?php echo count($relatedArticles); ?></span>
                            </div>
                            <div class="art-rel-list">
                                <?php foreach ($relatedArticles as $relArt): ?>
                                    <a href="article?slug=<?php echo urlencode($relArt['slug']); ?>" class="art-rel-item">
                                        <div class="art-rel-thumb">
                                            <img src="<?php echo htmlspecialchars($relArt['image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($relArt['title']); ?>"
                                                 onerror="this.onerror=null; this.src='assets/images/blog/at01.png';">
                                        </div>
                                        <div class="art-rel-content">
                                            <span class="art-rel-cat"><?php echo htmlspecialchars($relArt['category']); ?></span>
                                            <h5 class="art-rel-title"><?php echo htmlspecialchars($relArt['title']); ?></h5>
                                            <span class="art-rel-date"><i class="far fa-clock me-1"></i> <?php echo htmlspecialchars($relArt['read_time']); ?></span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Widget 3: Need Help / WhatsApp Assistance -->
                    <div class="art-side-support">
                        <div class="art-support-icon">
                            <i class="fab fa-whatsapp"></i>
                        </div>
                        <h4>Need TailorPro Assistance?</h4>
                        <p>Have questions about managing your shop or importing customer measurements?</p>
                        <a href="https://api.whatsapp.com/send?phone=919999999999&text=Hi%20TailorPro%20Team%2C%20I%20have%20a%20question%20about%20the%20tailoring%20software." 
                           target="_blank" 
                           rel="noopener noreferrer"
                           class="btn-art-whatsapp">
                            <i class="fab fa-whatsapp"></i> Chat with Expert
                        </a>
                    </div>

                </aside>
            </div>

        </div>

        <!-- Full-Width Bottom CTA Banner -->
        <div class="row">
            <div class="col-12">
                <section class="art-bottom-cta">
                    <div class="row align-items-center">
                        <div class="col-lg-7 col-xl-8 mb-4 mb-lg-0">
                            <h3><i class="fas fa-sparkles text-warning me-2"></i> Ready to Upgrade Your Tailoring Shop?</h3>
                            <p>
                                Join 1,500+ tailors and designer boutiques across India who save 3+ hours every day, prevent sizing errors, and delight customers with automated WhatsApp notifications.
                            </p>
                        </div>
                        <div class="col-lg-5 col-xl-4 text-lg-end">
                            <div class="art-cta-actions">
                                <a href="admin/register.php" class="btn-art-white">
                                    <i class="fas fa-rocket"></i> Get Started Free
                                </a>
                                <a href="blog" class="btn-art-glass">
                                    <i class="fas fa-book-open"></i> All Guides
                                </a>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

    </main>

    <!-- Copy Toast Notification -->
    <div id="copyToast" class="art-copy-toast" role="alert">
        <i class="fas fa-check-circle text-success"></i> Link copied to clipboard!
    </div>

    <?php require_once 'includes/footer.php'; ?>
    <?php require_once 'includes/whatsapp-button.php'; ?>
    <?php require_once 'includes/go-to-top-button.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script2.js"></script> 

    <!-- Reading Progress Bar & Copy Link Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Reading Progress Bar
        var progressBar = document.getElementById('readingProgressBar');
        if (progressBar) {
            window.addEventListener('scroll', function() {
                var docHeight = document.documentElement.scrollHeight - window.innerHeight;
                if (docHeight > 0) {
                    var scrolled = (window.scrollY / docHeight) * 100;
                    progressBar.style.width = Math.min(100, Math.max(0, scrolled)) + '%';
                }
            }, { passive: true });
        }

        // Copy Link Button with Toast Notification
        var copyBtn = document.getElementById('copyLinkBtn');
        var copyToast = document.getElementById('copyToast');
        if (copyBtn) {
            copyBtn.addEventListener('click', function() {
                var currentUrl = window.location.href;
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(currentUrl).then(showToast);
                } else {
                    // Fallback for non-HTTPS or older browsers
                    var tempInput = document.createElement('input');
                    tempInput.value = currentUrl;
                    document.body.appendChild(tempInput);
                    tempInput.select();
                    try {
                        document.execCommand('copy');
                        showToast();
                    } catch (err) {
                        alert('Link: ' + currentUrl);
                    }
                    document.body.removeChild(tempInput);
                }
            });
        }

        function showToast() {
            if (copyBtn) {
                copyBtn.classList.add('copied');
                copyBtn.innerHTML = '<i class="fas fa-check"></i>';
                setTimeout(function() {
                    copyBtn.classList.remove('copied');
                    copyBtn.innerHTML = '<i class="fas fa-link"></i>';
                }, 2200);
            }
            if (copyToast) {
                copyToast.classList.add('show');
                setTimeout(function() {
                    copyToast.classList.remove('show');
                }, 2500);
            }
        }
    });
    </script>

    <?php
    if (file_exists(__DIR__ . '/helpers/GA4Helper.php')) {
        require_once 'helpers/GA4Helper.php';
        if (class_exists('GA4Helper')) {
            $pageTitle = htmlspecialchars($article['title']) . ' - ' . $brandName;
            $pageLocation = $canonicalUrl;
            $pageViewCode = GA4Helper::trackPageView($pageTitle, $pageLocation);
            echo '<script>' . $pageViewCode . '</script>';
        }
    }
    ?>
</body>
</html>