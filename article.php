<?php
/**
 * Article Detail Page
 * Tailoring Management System - Standalone Page
 */

// Get article slug from URL
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (empty($slug)) {
    header('Location: blog.php');
    exit;
}

// Load articles from JSON
$articlesData = null;
$article = null;
$dataDir = __DIR__ . '/data';
$articlesFile = $dataDir . '/articles.json';

if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}

if (file_exists($articlesFile)) {
    $jsonContent = file_get_contents($articlesFile);
    $articlesData = json_decode($jsonContent, true);
    
    if ($articlesData && isset($articlesData['articles']) && is_array($articlesData['articles'])) {
        // Find article by slug
        foreach ($articlesData['articles'] as $art) {
            if (isset($art['slug']) && $art['slug'] === $slug) {
                $article = $art;
                break;
            }
        }
    }
}

// If article not found, redirect to blog
if (!$article) {
    header('Location: blog.php');
    exit;
}

// Determine back link based on referrer or URL parameter
$backLink = 'blog.php'; // Default to blog page
$backText = 'Back to Blog';

// Check for URL parameter first (more reliable)
if (isset($_GET['from']) && $_GET['from'] === 'index') {
    $backLink = './#blog';
    $backText = 'Back to Home';
} else {
    // Fallback to HTTP_REFERER
    $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    
    if (!empty($referrer)) {
        $referrerPath = parse_url($referrer, PHP_URL_PATH);
        // Check if referrer contains index.php or is the root/homepage
        if (strpos($referrerPath, 'index.php') !== false || 
            $referrerPath === '/' || 
            empty(parse_url($referrer, PHP_URL_PATH)) ||
            basename($referrerPath) === 'index.php') {
            $backLink = './#blog';
            $backText = 'Back to Home';
        }
    }
}

// Set page title
$page_title = $article['title'];

// Load SEO Helper if available
if (file_exists(__DIR__ . '/helpers/SEOHelper.php')) {
    require_once 'helpers/SEOHelper.php';
}

$baseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$canonicalUrl = $baseUrl . '/article?slug=' . urlencode($slug);

$seoOptions = [
    'title' => htmlspecialchars($article['title']) . ' - ' . (defined('APP_NAME') ? APP_NAME : 'Tailoring Management System'),
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
    
    <!-- Google Analytics 4 (GA4) -->
    <?php
    if (file_exists(__DIR__ . '/helpers/GA4Helper.php')) {
        require_once 'helpers/GA4Helper.php';
        if (class_exists('GA4Helper')) {
            echo GA4Helper::generateBaseCode();
        }
    }
    ?>
    
    <!-- Favicon - Primary ICO format for Google Search -->
    <link rel="icon" type="image/x-icon" href="favicon.ico" sizes="16x16 32x32 48x48">
    <!-- Favicon - PNG fallback -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon(2).png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon(2).png">
    <!-- Apple Touch Icon -->
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/favicon(2).png">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="assets/css/style8.css" rel="stylesheet">
    
    <style>
        .article-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 120px 0 60px;
            margin-top: 76px;
        }
        
        .article-header-content {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .article-category-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 1rem;
        }
        
        .article-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }
        
        .article-meta {
            display: flex;
            align-items: center;
            gap: 2rem;
            font-size: 0.95rem;
            opacity: 0.9;
            flex-wrap: wrap;
        }
        
        .article-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .article-image-wrapper {
            margin: 3rem 0;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .article-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .article-content {
            max-width: 1100px;
            margin: 0 auto;
            padding: 2rem 0;
        }
        
        .article-content p {
            font-size: 1.125rem;
            line-height: 1.8;
            color: #2d3748;
            margin-bottom: 1.5rem;
        }
        
        .article-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 2px solid #e2e8f0;
        }
        
        .article-tag {
            background: #e2e8f0;
            color: #4a5568;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            text-decoration: none;
            transition: background 0.3s ease;
        }
        
        .article-tag:hover {
            background: #cbd5e0;
            color: #2d3748;
        }
        
        .back-to-blog {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .back-to-blog:hover {
            color: #764ba2;
        }
        
        @media (max-width: 768px) {
            .article-title {
                font-size: 1.75rem;
            }
            
            .article-image {
                height: 250px;
            }
            
            .article-content p {
                font-size: 1rem;
            }
            
            .article-meta {
                gap: 1rem;
            }
            .article-header{
                margin-top: 0 !important;
                padding: 60px 0 60px !important;
            }
            .back-to-blog{
                margin-bottom: 0 !important;
            }
            .article-image-wrapper {
                margin: 1.5rem 0 !important;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php require_once 'includes/nav.php'; ?>

    <!-- Article Header -->
    <section class="article-header">
        <div class="container">
            <div class="article-header-content">
                <span class="article-category-badge"><?php echo htmlspecialchars($article['category']); ?></span>
                <h1 class="article-title"><?php echo htmlspecialchars($article['title']); ?></h1>
                <div class="article-meta">
                    <span><i class="fas fa-user"></i><?php echo htmlspecialchars($article['author']); ?></span>
                    <span><i class="fas fa-calendar"></i><?php echo date('F d, Y', strtotime($article['published_date'])); ?></span>
                    <span><i class="fas fa-clock"></i><?php echo htmlspecialchars($article['read_time']); ?></span>
                </div>
            </div>
        </div>
    </section>

    <!-- Article Content -->
    <section class="py-4">
        <div class="container">
            <a href="<?php echo htmlspecialchars($backLink); ?>" class="back-to-blog">
                <i class="fas fa-arrow-left"></i> <?php echo htmlspecialchars($backText); ?>
            </a>
            
            <div class="article-image-wrapper">
                <img src="<?php echo htmlspecialchars($article['image']); ?>" 
                     alt="<?php echo htmlspecialchars($article['title']); ?>" 
                     class="article-image"
                     onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
            </div>
            
            <div class="article-content">
                <?php if (isset($article['content']) && is_array($article['content'])): ?>
                    <?php foreach ($article['content'] as $paragraph): ?>
                        <p><?php echo nl2br(htmlspecialchars($paragraph)); ?></p>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if (isset($article['tags']) && is_array($article['tags']) && !empty($article['tags'])): ?>
                    <div class="article-tags">
                        <strong style="margin-right: 0.5rem;">Tags:</strong>
                        <?php foreach ($article['tags'] as $tag): ?>
                            <a href="blog.php" class="article-tag"><?php echo htmlspecialchars($tag); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php require_once 'includes/footer.php'; ?>

    <!-- WhatsApp Button -->
    <?php require_once 'includes/whatsapp-button.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Go to Top Button -->
<script src="assets/js/script2.js"></script> 
<?php require_once 'includes/go-to-top-button.php'; ?>
    
    <!-- GA4 Page View Tracking -->
    <?php
    if (file_exists(__DIR__ . '/helpers/GA4Helper.php')) {
        require_once 'helpers/GA4Helper.php';
        if (class_exists('GA4Helper')) {
            $pageTitle = htmlspecialchars($article['title']) . ' - ' . (defined('APP_NAME') ? APP_NAME : 'Tailoring Management System');
            $pageLocation = $canonicalUrl;
            $pageViewCode = GA4Helper::trackPageView($pageTitle, $pageLocation);
            echo '<script>' . $pageViewCode . '</script>';
        }
    }
    ?>
</body>
</html>

