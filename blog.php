<?php
$page_title = 'Blog';
if (file_exists(__DIR__ . '/helpers/SEOHelper.php')) {
    require_once 'helpers/SEOHelper.php';
}

$baseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$canonicalUrl = $baseUrl . '/blog.php';

$seoOptions = [
    'title' => 'Blog - ' . (defined('APP_NAME') ? APP_NAME : 'Tailoring Management System'),
    'description' => 'Read our latest articles about tailoring business tips, industry insights, and digital transformation in the tailoring industry.',
    'keywords' => 'tailoring blog, tailoring business tips, tailoring industry news, tailoring management articles',
    'canonical' => $canonicalUrl,
    'og_type' => 'website'
];
$articlesData = null;
$articles = [];
$dataDir = __DIR__ . '/data';
$articlesFile = $dataDir . '/articles.json';

if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}

if (file_exists($articlesFile)) {
    $jsonContent = file_get_contents($articlesFile);
    $articlesData = json_decode($jsonContent, true);
    
    if ($articlesData && isset($articlesData['articles']) && is_array($articlesData['articles'])) {
        $articles = $articlesData['articles'];
    }
} else {
    error_log('Warning: articles.json file not found at ' . $articlesFile);
}
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
    <link rel="icon" type="image/x-icon" href="favicon.ico" sizes="16x16 32x32 48x48">
    
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon(2).png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon(2).png">
   
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/favicon(2).png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link href="assets/css/style13.css" rel="stylesheet">
    <style>
        .blog-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 100px 0 50px;
            text-align: center;
        }
        
        .blog-hero h1 {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
            letter-spacing: -1px;
            font-family: 'Inter', sans-serif;
        }
        .blog-hero p {
            font-size: 1.15rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
            font-family: 'Inter', sans-serif;
        }

        @media (max-width: 768px) {
            .blog-hero {
                padding: 85px 16px 35px;
            }
            .blog-hero h1 {
                font-size: 2rem;
            }
            .blog-hero p {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php require_once 'includes/nav.php'; ?>
    <section class="blog-hero">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h1>Our Blog</h1>
                    <p>Insights, tips, and stories from the tailoring industry</p>
                </div>
            </div>
        </div>
    </section>
    <section class="py-3 py-md-5 blog-section">
        <div class="container">
            <?php if (empty($articles)): ?>
                <div class="no-articles">
                    <i class="fas fa-newspaper fa-3x mb-3"></i>
                    <h3>No Articles Available</h3>
                    <p>Check back soon for new articles and insights.</p>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($articles as $article): ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="article-card">
                                <div class="article-image-wrapper">
                                    <img src="<?php echo htmlspecialchars($article['image']); ?>"
                                        alt="<?php echo htmlspecialchars($article['title']); ?>"
                                        onerror="this.onerror=null; this.style.display='none';">
                                </div>
                                
                                <div class="article-body">
                                    <span class="article-category"><?php echo htmlspecialchars($article['category']); ?></span>
                                    <h3 class="article-title">
                                        <a href="article?slug=<?php echo htmlspecialchars($article['slug']); ?>">
                                            <?php echo htmlspecialchars($article['title']); ?>
                                        </a>
                                    </h3>
                                    <p class="article-excerpt"><?php echo htmlspecialchars($article['excerpt']); ?></p>
                                    <div class="article-meta">
                                        <span><i class="fas fa-user"></i><?php echo htmlspecialchars($article['author']); ?></span>
                                        <span><i class="fas fa-calendar"></i><?php echo date('M d, Y', strtotime($article['published_date'])); ?></span>
                                        <span><i class="fas fa-clock"></i><?php echo htmlspecialchars($article['read_time']); ?></span>
                                    </div>
                                    <a href="article?slug=<?php echo htmlspecialchars($article['slug']); ?>" class="blog-read-more-btn">
                                        Read More <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php require_once 'includes/footer.php'; ?>

    <?php require_once 'includes/whatsapp-button.php'; ?>
<script src="assets/js/script2.js"></script> 
<?php require_once 'includes/go-to-top-button.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php
    if (file_exists(__DIR__ . '/helpers/GA4Helper.php')) {
        require_once 'helpers/GA4Helper.php';
        if (class_exists('GA4Helper')) {
            $pageTitle = 'Blog - ' . (defined('APP_NAME') ? APP_NAME : 'Tailoring Management System');
            $pageLocation = $canonicalUrl;
            $pageViewCode = GA4Helper::trackPageView($pageTitle, $pageLocation);
            echo '<script>' . $pageViewCode . '</script>';
        }
    }
    ?>
</body>
</html>

