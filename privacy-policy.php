<?php
/**
 * Privacy Policy Page
 * Tailoring Management System - Standalone Page
 */

// Set page title
$page_title = 'Privacy Policy';
?>
<?php
require_once 'helpers/SEOHelper.php';

$baseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$canonicalUrl = $baseUrl . '/privacy-policy';

$seoOptions = [
    'title' => 'Privacy Policy - ' . (defined('APP_NAME') ? APP_NAME : 'Tailoring Management System'),
    'description' => 'Read our privacy policy to understand how we collect, use, and protect your personal information when using our tailoring management system.',
    'keywords' => 'privacy policy, data protection, privacy statement, user privacy, data security',
    'canonical' => $canonicalUrl,
    'og_type' => 'article'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php echo SEOHelper::generateMetaTags($seoOptions); ?>
    
    <!-- Google Analytics 4 (GA4) -->
    <?php
    require_once 'helpers/GA4Helper.php';
    echo GA4Helper::generateBaseCode();
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

        .navbar.fixed-top {
            top: 0 !important;
        }
        
        .navbar-brand {
            font-weight: 700;
            color: #667eea !important;
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
        
        .content ul {
            padding-left: 1.5rem;
        }
        
        .content li {
            margin-bottom: 0.5rem;
        }
        
        .contact-info {
            border-left: 4px solid #667eea;
        }
        
        .contact-info h4 {
            color: #667eea;
            font-weight: 600;
            font-size: 20px;
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
    <?php require_once 'includes/nav.php'; ?>

<div class="container" style="padding-top: 3rem !important;">
    <div class="row">
        <div class="col-lg-8 mx-auto w-100">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-shield-alt me-2"></i>Privacy Policy
                    </h1>
                    <p class="mb-0 mt-2">Last updated: <?php echo date('F j, Y'); ?></p>
                </div>
                <div class="card-body">
                    <div class="content">
                        <h2>1. Introduction</h2>
                        <p>
                            Welcome to Tailoring Management System ("we," "our," or "us"). We are committed to protecting your privacy and ensuring the security of your personal information. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our tailoring management platform.
                        </p>

                        <h2>2. Information We Collect</h2>
                        <h3>2.1 Personal Information</h3>
                        <p>We may collect the following personal information:</p>
                        <ul>
                            <li>Business name and contact details</li>
                            <li>Owner/manager name and email address</li>
                            <li>Phone numbers and business addresses</li>
                            <li>Payment and billing information</li>
                            <li>Customer data you input into the system</li>
                            <li>Employee information and user accounts</li>
                        </ul>

                        <h3>2.2 Usage Information</h3>
                        <p>We automatically collect certain information when you use our platform:</p>
                        <ul>
                            <li>IP address and browser type</li>
                            <li>Device information and operating system</li>
                            <li>Pages visited and features used</li>
                            <li>Time and date of access</li>
                            <li>Error logs and performance data</li>
                        </ul>

                        <h2>3. How We Use Your Information</h2>
                        <p>We use the collected information for the following purposes:</p>
                        <ul>
                            <li>To provide and maintain our tailoring management services</li>
                            <li>To process transactions and manage subscriptions</li>
                            <li>To communicate with you about your account and services</li>
                            <li>To improve our platform and develop new features</li>
                            <li>To provide customer support and technical assistance</li>
                            <li>To comply with legal obligations and prevent fraud</li>
                            <li>To send important updates and notifications</li>
                        </ul>

                        <h2>4. Information Sharing and Disclosure</h2>
                        <p>We do not sell, trade, or rent your personal information to third parties. We may share your information in the following circumstances:</p>
                        <ul>
                            <li><strong>Service Providers:</strong> With trusted third-party service providers who assist in operating our platform</li>
                            <li><strong>Legal Requirements:</strong> When required by law or to protect our rights and safety</li>
                            <li><strong>Business Transfers:</strong> In connection with a merger, acquisition, or sale of assets</li>
                            <li><strong>Consent:</strong> With your explicit consent for specific purposes</li>
                        </ul>

                        <h2>5. Data Security</h2>
                        <p>We implement appropriate security measures to protect your personal information:</p>
                        <ul>
                            <li>Encryption of data in transit and at rest</li>
                            <li>Regular security audits and updates</li>
                            <li>Access controls and authentication measures</li>
                            <li>Secure data centers with physical security</li>
                            <li>Regular backups and disaster recovery procedures</li>
                        </ul>

                        <h2>6. Data Retention</h2>
                        <p>We retain your personal information for as long as necessary to:</p>
                        <ul>
                            <li>Provide our services to you</li>
                            <li>Comply with legal obligations</li>
                            <li>Resolve disputes and enforce agreements</li>
                            <li>Maintain business records as required by law</li>
                        </ul>

                        <h2>7. Your Rights and Choices</h2>
                        <p>You have the following rights regarding your personal information:</p>
                        <ul>
                            <li><strong>Access:</strong> Request access to your personal data</li>
                            <li><strong>Correction:</strong> Request correction of inaccurate information</li>
                            <li><strong>Deletion:</strong> Request deletion of your personal data</li>
                            <li><strong>Portability:</strong> Request a copy of your data in a portable format</li>
                            <li><strong>Opt-out:</strong> Unsubscribe from marketing communications</li>
                        </ul>

                        <h2>8. Cookies and Tracking Technologies</h2>
                        <p>We use cookies and similar technologies to:</p>
                        <ul>
                            <li>Remember your preferences and settings</li>
                            <li>Analyze website traffic and usage patterns</li>
                            <li>Improve user experience and functionality</li>
                            <li>Provide personalized content and recommendations</li>
                        </ul>
                        <p>You can control cookie settings through your browser preferences.</p>

                        <h2>9. Third-Party Links</h2>
                        <p>Our platform may contain links to third-party websites. We are not responsible for the privacy practices of these external sites. We encourage you to review their privacy policies before providing any personal information.</p>

                        <h2>10. Children's Privacy</h2>
                        <p>Our services are not intended for children under 13 years of age. We do not knowingly collect personal information from children under 13. If we become aware that we have collected such information, we will take steps to delete it promptly.</p>

                        <h2>11. International Data Transfers</h2>
                        <p>Your information may be transferred to and processed in countries other than your own. We ensure appropriate safeguards are in place to protect your personal information during such transfers.</p>

                        <h2>12. Changes to This Privacy Policy</h2>
                        <p>We may update this Privacy Policy from time to time. We will notify you of any material changes by:</p>
                        <ul>
                            <li>Posting the updated policy on our website</li>
                            <li>Sending you an email notification</li>
                            <li>Displaying a notice in your account dashboard</li>
                        </ul>

                        <h2>13. Contact Information</h2>
                        <p>If you have any questions, concerns, or requests regarding this Privacy Policy or our data practices, please contact us:</p>
                        
                        <div class="contact-info bg-light p-4 rounded mt-3">
                            <h4><i class="fas fa-envelope me-2"></i>Email</h4>
                            <p class="mb-2">
                                <a href="mailto:codelockinfo@gmail.com" class="text-decoration-none">
                                    codelockinfo@gmail.com
                                </a>
                            </p>
                            
                            <h4><i class="fas fa-phone me-2"></i>Phone</h4>
                            <p class="mb-2">
                                <a href="tel:+917600464414" class="text-decoration-none">
                                    +91 7600464414
                                </a>
                            </p>
                            
                            <h4><i class="fas fa-clock me-2"></i>Business Hours</h4>
                            <p class="mb-0">Monday - Friday: 9:00 AM - 6:00 PM IST</p>
                        </div>

                        <div class="alert alert-info mt-4">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> This Privacy Policy is effective as of the date listed above and applies to all users of the Tailoring Management System platform.
                        </div>
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

<!-- WhatsApp Button -->
<?php require_once 'includes/whatsapp-button.php'; ?>

<!-- Go to Top Button -->
<script src="assets/js/script2.js"></script>
<?php require_once 'includes/go-to-top-button.php'; ?>

</body>
</html>
