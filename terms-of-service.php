<?php
/**
 * Terms of Service Page
 * Tailoring Management System - Standalone Page
 */

// Set page title
$page_title = 'Terms of Service';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Tailoring Management System</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon(2).png">
    
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
    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <img src="uploads/logos/main-logo.png" alt="TailorPro" class="navbar-logo me-2" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                <i class="fas fa-cut text-primary me-2" style="display: none;"></i>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#benefits">Benefits</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#pricing">Pricing</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#contact">Contact</a>
                    </li>
                    <li class="nav-item ms-3">
                        <a href="admin/login.php" class="btn  btn-sm login-header-btn">Login</a>
                    </li>
                    <li class="nav-item ms-2">
                        <a href="admin/register.php" class="btn  btn-sm register-header-btn">Register</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

<div class="container py-5"  style="padding-top: 8rem!important;">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-file-contract me-2"></i>Terms of Service
                    </h1>
                    <p class="mb-0 mt-2">Last updated: <?php echo date('F j, Y'); ?></p>
                </div>
                <div class="card-body">
                    <div class="content">
                        <h2>1. Acceptance of Terms</h2>
                        <p>
                            By accessing and using the Tailoring Management System ("Service," "Platform," or "System"), you accept and agree to be bound by the terms and provision of this agreement. If you do not agree to abide by the above, please do not use this service.
                        </p>

                        <h2>2. Description of Service</h2>
                        <p>
                            The Tailoring Management System is a comprehensive business management platform designed specifically for tailoring businesses. Our service includes:
                        </p>
                        <ul>
                            <li>Customer management and relationship tracking</li>
                            <li>Order processing and delivery management</li>
                            <li>Invoice generation and payment tracking</li>
                            <li>Employee and staff management</li>
                            <li>Measurement and cloth type management</li>
                            <li>Financial reporting and analytics</li>
                            <li>Multi-language support</li>
                            <li>Mobile-responsive web interface</li>
                        </ul>

                        <h2>3. User Accounts and Registration</h2>
                        <h3>3.1 Account Creation</h3>
                        <p>To use our service, you must:</p>
                        <ul>
                            <li>Provide accurate and complete registration information</li>
                            <li>Maintain the security of your account credentials</li>
                            <li>Be at least 18 years old or have parental consent</li>
                            <li>Use the service for legitimate business purposes only</li>
                        </ul>

                        <h3>3.2 Account Responsibilities</h3>
                        <p>You are responsible for:</p>
                        <ul>
                            <li>All activities that occur under your account</li>
                            <li>Maintaining the confidentiality of your password</li>
                            <li>Notifying us immediately of any unauthorized use</li>
                            <li>Ensuring your contact information remains current</li>
                        </ul>

                        <h2>4. Subscription Plans and Billing</h2>
                        <h3>4.1 Available Plans</h3>
                        <ul>
                            <li><strong>Free Trial:</strong> 30-day trial with limited features</li>
                            <li><strong>Basic Plan:</strong> ₹99/month - Up to 100 customers, 150 orders</li>
                            <li><strong>Premium Plan:</strong> ₹199/month - Up to 500 customers, 1,000 orders</li>
                            <li><strong>Enterprise Plan:</strong> ₹999/month - Unlimited customers and orders</li>
                        </ul>

                        <h3>4.2 Billing Terms</h3>
                        <ul>
                            <li>Subscriptions are billed monthly or annually</li>
                            <li>Annual subscriptions receive a 10% discount</li>
                            <li>Payment is due in advance for each billing period</li>
                            <li>Failed payments may result in service suspension</li>
                            <li>Refunds are provided according to our refund policy</li>
                        </ul>

                        <h3>4.3 Plan Limitations</h3>
                        <p>Each plan has specific limitations on:</p>
                        <ul>
                            <li>Number of customers and orders</li>
                            <li>Number of user accounts</li>
                            <li>Available features and support levels</li>
                            <li>Data storage and backup retention</li>
                        </ul>

                        <h2>5. Acceptable Use Policy</h2>
                        <h3>5.1 Permitted Uses</h3>
                        <p>You may use our service to:</p>
                        <ul>
                            <li>Manage your tailoring business operations</li>
                            <li>Store and process customer information</li>
                            <li>Generate invoices and track payments</li>
                            <li>Manage employee and staff information</li>
                            <li>Generate reports and analytics for your business</li>
                        </ul>

                        <h3>5.2 Prohibited Uses</h3>
                        <p>You may not use our service to:</p>
                        <ul>
                            <li>Violate any applicable laws or regulations</li>
                            <li>Infringe on intellectual property rights</li>
                            <li>Transmit harmful, offensive, or illegal content</li>
                            <li>Attempt to gain unauthorized access to our systems</li>
                            <li>Interfere with or disrupt the service</li>
                            <li>Use the service for any non-tailoring business purposes</li>
                            <li>Share your account credentials with unauthorized users</li>
                        </ul>

                        <h2>6. Data and Privacy</h2>
                        <h3>6.1 Data Ownership</h3>
                        <p>You retain ownership of all data you input into our system. We act as a data processor and will not use your data for purposes other than providing our service.</p>

                        <h3>6.2 Data Security</h3>
                        <p>We implement industry-standard security measures to protect your data, including encryption, secure data centers, and regular security audits.</p>

                        <h3>6.3 Data Backup and Recovery</h3>
                        <p>We maintain regular backups of your data. However, you are responsible for maintaining your own backups of critical business information.</p>

                        <h2>7. Intellectual Property</h2>
                        <h3>7.1 Our Rights</h3>
                        <p>We retain all rights to the Tailoring Management System platform, including:</p>
                        <ul>
                            <li>Software code and algorithms</li>
                            <li>User interface and design elements</li>
                            <li>Trademarks and branding</li>
                            <li>Documentation and training materials</li>
                        </ul>

                        <h3>7.2 Your Rights</h3>
                        <p>You retain rights to your business data and any customizations you create within the platform.</p>

                        <h2>8. Service Availability and Support</h2>
                        <h3>8.1 Uptime Commitment</h3>
                        <p>We strive to maintain 99.9% uptime for our service. Planned maintenance will be announced in advance.</p>

                        <h3>8.2 Support Levels</h3>
                        <ul>
                            <li><strong>Free Trial:</strong> Email support</li>
                            <li><strong>Basic Plan:</strong> Priority email support</li>
                            <li><strong>Premium Plan:</strong> 24/7 priority support</li>
                            <li><strong>Enterprise Plan:</strong> Dedicated account manager</li>
                        </ul>

                        <h2>9. Termination</h2>
                        <h3>9.1 Termination by You</h3>
                        <p>You may cancel your subscription at any time. Your account will remain active until the end of your current billing period.</p>

                        <h3>9.2 Termination by Us</h3>
                        <p>We may terminate your account if you:</p>
                        <ul>
                            <li>Violate these terms of service</li>
                            <li>Fail to pay subscription fees</li>
                            <li>Engage in fraudulent or illegal activities</li>
                            <li>Misuse the service in any way</li>
                        </ul>

                        <h3>9.3 Data Export</h3>
                        <p>Upon termination, you may export your data for up to 30 days. After this period, your data may be permanently deleted.</p>

                        <h2>10. Limitation of Liability</h2>
                        <p>
                            To the maximum extent permitted by law, we shall not be liable for any indirect, incidental, special, consequential, or punitive damages, including but not limited to loss of profits, data, or business opportunities.
                        </p>

                        <h2>11. Indemnification</h2>
                        <p>
                            You agree to indemnify and hold us harmless from any claims, damages, or expenses arising from your use of the service or violation of these terms.
                        </p>

                        <h2>12. Governing Law</h2>
                        <p>
                            These terms shall be governed by and construed in accordance with the laws of India. Any disputes shall be resolved through binding arbitration.
                        </p>

                        <h2>13. Changes to Terms</h2>
                        <p>
                            We reserve the right to modify these terms at any time. We will notify users of material changes via email or through the platform. Continued use of the service constitutes acceptance of the modified terms.
                        </p>

                        <h2>14. Contact Information</h2>
                        <p>For questions about these Terms of Service, please contact us:</p>
                        
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
                            <strong>Note:</strong> These Terms of Service are effective as of the date listed above and apply to all users of the Tailoring Management System platform.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="footer py-4 mt-5">
    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <p class="mb-0">&copy; 2024 Tailoring Management System. All rights reserved.</p>
            </div>
            <div class="col-md-6 text-md-end">
                <div class="footer-links">
                    <a href="privacy-policy.php" class="me-3">Privacy Policy</a>
                    <a href="terms-of-service.php">Terms of Service</a>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
