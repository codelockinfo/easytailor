<?php
/**
 * Blog Page
 * Tailoring Management System - Standalone Page
 */

// Set page title
$page_title = 'Blog';

// Load SEO Helper if available
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

// Load articles from JSON
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
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <!-- Favicon - PNG fallback -->
    <link rel="icon" type="image/png" href="favicon(2).png">
    <!-- Apple Touch Icon -->
    <link rel="apple-touch-icon" href="favicon(2).png">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="assets/css/style7.css" rel="stylesheet">
    
    <style>
        .blog-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 120px 0 80px;
            margin-top: 70px;
        }
        
        .blog-hero h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .blog-hero p {
            font-size: 1.25rem;
            opacity: 0.9;
        }
        
        .article-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .article-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
        }
        
        .article-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .article-card-body {
            padding: 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .article-category {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 1rem;
        }
        
        .article-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #2d3748;
            line-height: 1.3;
        }
        
        .article-excerpt {
            color: #4a5568;
            margin-bottom: 1.5rem;
            flex-grow: 1;
        }
        
        .article-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.875rem;
            color: #718096;
            margin-top: auto;
        }
        
        .article-meta i {
            margin-right: 0.25rem;
        }
        
        .read-more-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: opacity 0.3s ease;
        }
        
        .read-more-btn:hover {
            opacity: 0.9;
            color: white;
        }
        
        .no-articles {
            text-align: center;
            padding: 4rem 2rem;
            color: #718096;
        }
        
        @media (max-width: 768px) {
            .blog-hero {
                margin-top: 0 !important;
            }
            
            .blog-hero h1 {
                font-size: 2rem;
            }
            
            .blog-hero p {
                font-size: 1rem;
            }
            
            .article-image {
                height: 200px;
            }
        }
        
       
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php require_once 'includes/nav.php'; ?>

    <!-- Blog Hero Section -->
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

    <!-- Blog Articles Section -->
    <section class="py-5">
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
                        <div class="col-lg-6">
                            <div class="article-card">
                                <img src="<?php echo htmlspecialchars($article['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($article['title']); ?>" 
                                     class="article-image"
                                     onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='block';">
                                
                                <div class="article-card-body">
                                    <span class="article-category"><?php echo htmlspecialchars($article['category']); ?></span>
                                    <h3 class="article-title">
                                        <a href="article?slug=<?php echo htmlspecialchars($article['slug']); ?>" 
                                           style="color: inherit; text-decoration: none;">
                                            <?php echo htmlspecialchars($article['title']); ?>
                                        </a>
                                    </h3>
                                    <p class="article-excerpt"><?php echo htmlspecialchars($article['excerpt']); ?></p>
                                    <div class="article-meta">
                                        <span><i class="fas fa-user"></i><?php echo htmlspecialchars($article['author']); ?></span>
                                        <span><i class="fas fa-calendar"></i><?php echo date('M d, Y', strtotime($article['published_date'])); ?></span>
                                        <span><i class="fas fa-clock"></i><?php echo htmlspecialchars($article['read_time']); ?></span>
                                    </div>
                                    <a href="article?slug=<?php echo htmlspecialchars($article['slug']); ?>" class="read-more-btn mt-3">
                                        Read More <i class="fas fa-arrow-right ms-2"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <?php require_once 'includes/footer.php'; ?>

    <!-- WhatsApp Button -->
    <?php require_once 'includes/whatsapp-button.php'; ?>
    <!-- Go to Top Button -->
<script src="assets/js/script1.js"></script> 
<?php require_once 'includes/go-to-top-button.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- GA4 Page View Tracking -->
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

