<?php
/**
 * SEO Helper Class
 * Provides comprehensive SEO meta tags and structured data
 */

class SEOHelper {
    
    /**
     * Generate SEO meta tags
     * 
     * @param array $options SEO options
     * @return string HTML meta tags
     */
    public static function generateMetaTags($options = []) {
        $defaults = [
            'title' => 'Tailoring Management System | Smart Solution for Tailor Shops',
            'description' => 'All-in-one tailoring management system to manage customers, orders, invoices, employees, and payments. Digitalize your tailor shop with smart tools.',
            'keywords' => 'tailoring management system, tailor shop software, tailor business software, tailor invoicing system, order management for tailors, tailor ERP, tailor business management app',
            'canonical' => '',
            'og_image' => '',
            'og_type' => 'website',
            'robots' => 'index, follow',
            'author' => 'Tailoring Management System',
            'noindex' => false,
            'structured_data' => null
        ];
        
        $options = array_merge($defaults, $options);
        
        // Get base URL
        $baseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        
        // Build canonical URL
        if (empty($options['canonical'])) {
            $options['canonical'] = $baseUrl . $_SERVER['REQUEST_URI'];
        }
        
        // Build OG image
        if (empty($options['og_image'])) {
            $options['og_image'] = $baseUrl . '/assets/images/og-image.jpg';
        } elseif (!filter_var($options['og_image'], FILTER_VALIDATE_URL)) {
            $options['og_image'] = $baseUrl . '/' . ltrim($options['og_image'], '/');
        }
        
        // Set robots
        if ($options['noindex']) {
            $options['robots'] = 'noindex, nofollow';
        }
        
        $html = "\n    <!-- SEO Meta Tags -->\n";
        $html .= "    <title>" . htmlspecialchars($options['title'], ENT_QUOTES, 'UTF-8') . "</title>\n";
        $html .= "    <meta name=\"description\" content=\"" . htmlspecialchars($options['description'], ENT_QUOTES, 'UTF-8') . "\">\n";
        $html .= "    <meta name=\"keywords\" content=\"" . htmlspecialchars($options['keywords'], ENT_QUOTES, 'UTF-8') . "\">\n";
        $html .= "    <meta name=\"author\" content=\"" . htmlspecialchars($options['author'], ENT_QUOTES, 'UTF-8') . "\">\n";
        $html .= "    <meta name=\"robots\" content=\"" . htmlspecialchars($options['robots'], ENT_QUOTES, 'UTF-8') . "\">\n";
        $html .= "    <meta name=\"googlebot\" content=\"" . htmlspecialchars($options['robots'], ENT_QUOTES, 'UTF-8') . "\">\n";
        $html .= "    <meta name=\"language\" content=\"English\">\n";
        $html .= "    <meta name=\"revisit-after\" content=\"7 days\">\n";
        $html .= "    <meta name=\"distribution\" content=\"global\">\n";
        $html .= "    <meta name=\"rating\" content=\"general\">\n";
        
        // Canonical URL
        $html .= "\n    <!-- Canonical URL -->\n";
        $html .= "    <link rel=\"canonical\" href=\"" . htmlspecialchars($options['canonical'], ENT_QUOTES, 'UTF-8') . "\">\n";
        
        // Open Graph Meta Tags
        $html .= "\n    <!-- Open Graph Meta Tags -->\n";
        $html .= "    <meta property=\"og:title\" content=\"" . htmlspecialchars($options['title'], ENT_QUOTES, 'UTF-8') . "\">\n";
        $html .= "    <meta property=\"og:description\" content=\"" . htmlspecialchars($options['description'], ENT_QUOTES, 'UTF-8') . "\">\n";
        $html .= "    <meta property=\"og:type\" content=\"" . htmlspecialchars($options['og_type'], ENT_QUOTES, 'UTF-8') . "\">\n";
        $html .= "    <meta property=\"og:url\" content=\"" . htmlspecialchars($options['canonical'], ENT_QUOTES, 'UTF-8') . "\">\n";
        $html .= "    <meta property=\"og:image\" content=\"" . htmlspecialchars($options['og_image'], ENT_QUOTES, 'UTF-8') . "\">\n";
        $html .= "    <meta property=\"og:image:width\" content=\"1200\">\n";
        $html .= "    <meta property=\"og:image:height\" content=\"630\">\n";
        $html .= "    <meta property=\"og:image:alt\" content=\"" . htmlspecialchars($options['title'], ENT_QUOTES, 'UTF-8') . "\">\n";
        $html .= "    <meta property=\"og:site_name\" content=\"" . (defined('APP_NAME') ? htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') : 'Tailoring Management System') . "\">\n";
        $html .= "    <meta property=\"og:locale\" content=\"en_US\">\n";
        $html .= "    <meta property=\"og:locale:alternate\" content=\"en_IN\">\n";
        
        // Twitter Card Meta Tags
        $html .= "\n    <!-- Twitter Card Meta Tags -->\n";
        $html .= "    <meta name=\"twitter:card\" content=\"summary_large_image\">\n";
        $html .= "    <meta name=\"twitter:title\" content=\"" . htmlspecialchars($options['title'], ENT_QUOTES, 'UTF-8') . "\">\n";
        $html .= "    <meta name=\"twitter:description\" content=\"" . htmlspecialchars($options['description'], ENT_QUOTES, 'UTF-8') . "\">\n";
        $html .= "    <meta name=\"twitter:image\" content=\"" . htmlspecialchars($options['og_image'], ENT_QUOTES, 'UTF-8') . "\">\n";
        $html .= "    <meta name=\"twitter:image:alt\" content=\"" . htmlspecialchars($options['title'], ENT_QUOTES, 'UTF-8') . "\">\n";
        
        // Structured Data
        if ($options['structured_data']) {
            $html .= "\n    <!-- Structured Data -->\n";
            $html .= "    <script type=\"application/ld+json\">\n";
            $html .= "    " . json_encode($options['structured_data'], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            $html .= "    </script>\n";
        }
        
        return $html;
    }
    
    /**
     * Generate LocalBusiness structured data
     */
    public static function generateLocalBusinessSchema($company) {
        $baseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        
        $schema = [
            "@context" => "https://schema.org",
            "@type" => "LocalBusiness",
            "name" => $company['company_name'] ?? '',
            "description" => $company['description'] ?? '',
            "image" => !empty($company['logo']) ? $baseUrl . '/' . ltrim($company['logo'], '/') : '',
            "address" => [
                "@type" => "PostalAddress",
                "streetAddress" => $company['business_address'] ?? '',
                "addressLocality" => $company['city'] ?? '',
                "addressRegion" => $company['state'] ?? '',
                "postalCode" => $company['postal_code'] ?? '',
                "addressCountry" => $company['country'] ?? 'India'
            ],
            "telephone" => $company['business_phone'] ?? '',
            "email" => $company['business_email'] ?? '',
            "url" => $baseUrl . '/tailor.php?id=' . ($company['id'] ?? '')
        ];
        
        if (!empty($company['website'])) {
            $schema['sameAs'] = [$company['website']];
        }
        
        if (!empty($company['rating']) && $company['rating'] > 0) {
            $schema['aggregateRating'] = [
                "@type" => "AggregateRating",
                "ratingValue" => (float)$company['rating'],
                "reviewCount" => (int)($company['total_reviews'] ?? 0)
            ];
        }
        
        return $schema;
    }
    
    /**
     * Generate BreadcrumbList structured data
     */
    public static function generateBreadcrumbSchema($items) {
        $schema = [
            "@context" => "https://schema.org",
            "@type" => "BreadcrumbList",
            "itemListElement" => []
        ];
        
        $position = 1;
        foreach ($items as $item) {
            $schema['itemListElement'][] = [
                "@type" => "ListItem",
                "position" => $position++,
                "name" => $item['name'],
                "item" => $item['url']
            ];
        }
        
        return $schema;
    }
}

