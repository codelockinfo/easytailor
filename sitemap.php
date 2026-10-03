<?php
ob_start();
header('Content-Type: application/xml; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);
$baseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$currentDate = date('Y-m-d');
$staticPages = [
    [
        'loc' => $baseUrl . '/',
        'lastmod' => $currentDate,
        'changefreq' => 'daily',
        'priority' => '1.0'
    ],
    [
        'loc' => $baseUrl . '/tailors',
        'lastmod' => $currentDate,
        'changefreq' => 'daily',
        'priority' => '0.9'
    ],
    [
        'loc' => $baseUrl . '/privacy-policy',
        'lastmod' => $currentDate,
        'changefreq' => 'monthly',
        'priority' => '0.3'
    ],
    [
        'loc' => $baseUrl . '/terms-of-service',
        'lastmod' => $currentDate,
        'changefreq' => 'monthly',
        'priority' => '0.3'
    ],
    [
        'loc' => $baseUrl . '/admin/register',
        'lastmod' => $currentDate,
        'changefreq' => 'weekly',
        'priority' => '0.8'
    ]
];
$tailorPages = [];
try {
   
    ob_start();
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/models/Company.php';
    ob_end_clean(); 
    
    $companyModel = new Company();
    $companies = $companyModel->getActiveCompanies();
    
    foreach ($companies as $company) {
        if (!empty($company['show_on_listing']) && $company['status'] === 'active' && Company::isProfileComplete($company)) {
            $tailorPages[] = [
                'loc' => $baseUrl . '/tailor.php?id=' . $company['id'],
                'lastmod' => !empty($company['updated_at']) ? date('Y-m-d', strtotime($company['updated_at'])) : $currentDate,
                'changefreq' => 'weekly',
                'priority' => '0.7'
            ];
        }
    }
} catch (Exception $e) {
    // If database is not available, just use static pages
}

$allPages = array_merge($staticPages, $tailorPages);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($allPages as $page) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($page['loc'], ENT_XML1, 'UTF-8') . "</loc>\n";
    echo "    <lastmod>" . htmlspecialchars($page['lastmod'], ENT_XML1, 'UTF-8') . "</lastmod>\n";
    echo "    <changefreq>" . htmlspecialchars($page['changefreq'], ENT_XML1, 'UTF-8') . "</changefreq>\n";
    echo "    <priority>" . htmlspecialchars($page['priority'], ENT_XML1, 'UTF-8') . "</priority>\n";
    echo "  </url>\n";
}
echo '</urlset>';
ob_end_flush();
exit;