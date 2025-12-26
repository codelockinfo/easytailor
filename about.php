<?php
/**
 * About Us Page
 * Tailoring Management System - Standalone Page
 */

// Set page title
$page_title = 'About Us';
?>
<?php
// Load SEO Helper if available
if (file_exists(__DIR__ . '/helpers/SEOHelper.php')) {
    require_once 'helpers/SEOHelper.php';
}

$baseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$canonicalUrl = $baseUrl . '/about';

$seoOptions = [
    'title' => 'About Us - ' . (defined('APP_NAME') ? APP_NAME : 'Tailoring Management System'),
    'description' => 'Learn about our mission to revolutionize tailoring businesses with innovative management solutions. Discover how we help tailors digitalize their operations and grow their business.',
    'keywords' => 'about us, tailoring management, business software, tailor shop management, digital solutions',
    'canonical' => $canonicalUrl,
    'og_type' => 'article',
    'structured_data' => [
        "@context" => "https://schema.org",
        "@type" => "AboutPage",
        "name" => "About Us",
        "description" => "Learn about our mission to revolutionize tailoring businesses with innovative management solutions",
        "url" => $canonicalUrl,
        "mainEntity" => [
            "@type" => "Organization",
            "name" => defined('APP_NAME') ? APP_NAME : 'Tailoring Management System',
            "description" => "Comprehensive tailoring management system for modern businesses",
            "url" => $baseUrl
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
            // Fallback if SEOHelper fails
            error_log('SEOHelper error: ' . $e->getMessage());
            echo '<title>' . htmlspecialchars($seoOptions['title']) . '</title>';
            echo '<meta name="description" content="' . htmlspecialchars($seoOptions['description']) . '">';
            echo '<meta name="keywords" content="' . htmlspecialchars($seoOptions['keywords']) . '">';
            echo '<link rel="canonical" href="' . htmlspecialchars($seoOptions['canonical']) . '">';
        }
    } else {
        // Fallback meta tags
        echo '<title>' . htmlspecialchars($seoOptions['title']) . '</title>';
        echo '<meta name="description" content="' . htmlspecialchars($seoOptions['description']) . '">';
        echo '<meta name="keywords" content="' . htmlspecialchars($seoOptions['keywords']) . '">';
        echo '<link rel="canonical" href="' . htmlspecialchars($seoOptions['canonical']) . '">';
    }
    ?>
    
    <!-- Google Analytics 4 (GA4) -->
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
    
    <link href="assets/css/style8.css" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Favicon - Primary ICO format for Google Search -->
    <link rel="icon" type="image/x-icon" href="favicon.ico" sizes="16x16 32x32 48x48">
    <!-- Favicon - PNG fallback -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon(2).png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon(2).png">
    <!-- Apple Touch Icon -->
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/favicon(2).png">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }

        .btn {
            border-radius: var(--border-radius);
            font-weight: 600;
            padding: 0.75rem 2rem;
            transition: var(--transition);
            border: none;
            position: relative;
            overflow: hidden;
        }

        .login-header-btn {
            border: 2px solid var(--primary-color) !important;
            color: var(--primary-color) !important;
            background: transparent !important;
            border-radius: 50px !important;
        }

        .navbar.fixed-top {
            top: 0 !important;
        }

        .navbar-brand {
            font-weight: 700;
            color: #667eea !important;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px 15px 0 0 !important;
        }

        .card-header h1 {
            font-size: 24px;
        }
        
        .content h2 {
            color: #2d3748;
            margin-top: 2rem;
            margin-bottom: 1rem;
            font-weight: 600;
            font-size: 24px;
        }
        
        .content h3 {
            color: #4a5568;
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
            font-weight: 500;
            font-size: 20px;
        }
        
        .content p {
            color: #4a5568;
            line-height: 1.8;
            margin-bottom: 1rem;
        }
        
        .content ul {
            padding-left: 1.5rem;
        }
        
        .content li {
            margin-bottom: 0.5rem;
            color: #4a5568;
            line-height: 1.8;
        }
        
        .mission-box {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-left: 4px solid #667eea;
            padding: 1.5rem;
            border-radius: 10px;
            margin: 2rem 0;
        }
        
        .mission-box h3 {
            color: #667eea;
            margin-top: 0;
        }
        
        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        
        .value-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .value-card:hover {
            transform: translateY(-5px);
        }
        
        .value-card i {
            font-size: 2.5rem;
            color: #667eea;
            margin-bottom: 1rem;
        }
        
        .value-card h4 {
            color: #2d3748;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .navbar {
            background: white !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .footer {
            background: #2d3748;
            color: white;
        }
        
        .footer a {
            color: #a0aec0;
            text-decoration: none;
        }
        
        .footer a:hover {
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <!-- Navigation -->
    <?php require_once 'includes/nav.php'; ?>

<?php
// Load content from JSON file
$jsonFile = __DIR__ . '/data/about-content.json';
$aboutData = null;

// Check if data directory exists, if not create it
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}

if (file_exists($jsonFile)) {
    $jsonContent = @file_get_contents($jsonFile);
    if ($jsonContent !== false) {
        $aboutData = json_decode($jsonContent, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Log error but don't break the page
            error_log('JSON decode error in about: ' . json_last_error_msg());
            $aboutData = null;
        }
    }
} else {
    // Log warning if file doesn't exist
    error_log('About content JSON file not found: ' . $jsonFile);
}
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-10 mx-auto w-100">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-info-circle me-2"></i><?php echo isset($aboutData['page']['title']) ? htmlspecialchars($aboutData['page']['title']) : 'About Us'; ?>
                    </h1>
                    <p class="mb-0 mt-2"><?php echo isset($aboutData['page']['subtitle']) ? htmlspecialchars($aboutData['page']['subtitle']) : 'Empowering Tailoring Businesses Through Technology'; ?></p>
                </div>
                <div class="card-body">
                    <div class="content">
                        <?php if ($aboutData && isset($aboutData['sections'])): ?>
                            <?php foreach ($aboutData['sections'] as $section): ?>
                                <?php if (isset($section['type']) && $section['type'] === 'mission'): ?>
                                    <div class="mission-box">
                                        <h3>
                                            <?php if (isset($section['icon'])): ?>
                                                <i class="<?php echo htmlspecialchars($section['icon']); ?> me-2"></i>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($section['title']); ?>
                                        </h3>
                                        <?php if (isset($section['content'])): ?>
                                            <?php foreach ($section['content'] as $paragraph): ?>
                                                <p><?php echo htmlspecialchars($paragraph); ?></p>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif (isset($section['type']) && $section['type'] === 'values' && isset($section['values'])): ?>
                                    <h2><?php echo htmlspecialchars($section['title']); ?></h2>
                                    <div class="values-grid">
                                        <?php foreach ($section['values'] as $value): ?>
                                            <div class="value-card">
                                                <?php if (isset($value['icon'])): ?>
                                                    <i class="<?php echo htmlspecialchars($value['icon']); ?>"></i>
                                                <?php endif; ?>
                                                <?php if (isset($value['title'])): ?>
                                                    <h4><?php echo htmlspecialchars($value['title']); ?></h4>
                                                <?php endif; ?>
                                                <?php if (isset($value['description'])): ?>
                                                    <p><?php echo htmlspecialchars($value['description']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <h2><?php echo htmlspecialchars($section['title']); ?></h2>
                                    <?php if (isset($section['content'])): ?>
                                        <?php foreach ($section['content'] as $paragraph): ?>
                                            <p><?php echo htmlspecialchars($paragraph); ?></p>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            
                            <?php if (isset($aboutData['cta'])): ?>
                                <div class="text-center mt-5">
                                    <a href="<?php echo htmlspecialchars($aboutData['cta']['link']); ?>" class="btn btn-primary btn-lg">
                                        <?php if (isset($aboutData['cta']['icon'])): ?>
                                            <i class="<?php echo htmlspecialchars($aboutData['cta']['icon']); ?> me-2"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($aboutData['cta']['text']); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- Fallback content if JSON file is not available -->
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>Content is currently being loaded. Please refresh the page.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<?php require_once 'includes/footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Track page view (wait for gtag to be available)
    <?php
    $pageViewCode = '// GA4Helper not available';
    if (file_exists(__DIR__ . '/helpers/GA4Helper.php')) {
        require_once 'helpers/GA4Helper.php';
        if (class_exists('GA4Helper')) {
            try {
                $pageTitle = 'About Us - ' . (defined('APP_NAME') ? APP_NAME : 'Tailoring Management System');
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
        var maxAttempts = 50; // 5 seconds max wait time
        
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
        
        // Start trying to fire the page view
        firePageView();
    })();
</script>

<!-- WhatsApp Button -->
<?php require_once 'includes/whatsapp-button.php'; ?>
<!-- Go to Top Button -->
<script src="assets/js/script2.js"></script> 
<?php require_once 'includes/go-to-top-button.php'; ?>
</body>
</html>

