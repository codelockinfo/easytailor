<?php
require_once 'models/Company.php';
require_once 'models/CompanyReview.php';

$companyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$companyModel = new Company();
$reviewModel = new CompanyReview();

$company = $companyModel->getPublicCompanyById($companyId);

if (!$company) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>Tailor Not Found</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"></head><body class="bg-light"><div class="container py-5 text-center"><h1 class="display-5">Tailor Not Found</h1><p class="lead text-muted">The tailor profile you are looking for is no longer available.</p><a class="btn btn-primary" href="tailors.php">Browse Tailors</a></div></body></html>';
    exit;
}

$reviews = $reviewModel->getApprovedReviews($companyId, 50);
$stats = $reviewModel->getRatingStats($companyId);
$breakdown = $reviewModel->getRatingBreakdown($companyId);

$specialties = [];
if (!empty($company['specialties'])) {
    $decoded = json_decode($company['specialties'], true);
    if (is_array($decoded)) {
        $specialties = $decoded;
    }
}

$workingHours = [];
if (!empty($company['working_hours'])) {
    $decoded = json_decode($company['working_hours'], true);
    if (is_array($decoded)) {
        $workingHours = $decoded;
    }
}

function escape($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<?php
require_once 'helpers/SEOHelper.php';

$baseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$companyName = escape($company['company_name']);
$companyDesc = !empty($company['description']) ? escape($company['description']) : ($companyName . ' - Professional tailor shop offering quality tailoring services. ' . (!empty($company['city']) ? 'Located in ' . escape($company['city']) : ''));
$companyImage = !empty($company['logo']) ? $baseUrl . '/' . ltrim($company['logo'], '/') : $baseUrl . '/assets/images/og-image.jpg';
$canonicalUrl = $baseUrl . '/tailor.php?id=' . $company['id'];

$seoOptions = [
    'title' => $companyName . ' - Tailor Profile | ' . (defined('APP_NAME') ? APP_NAME : 'Tailoring Management System'),
    'description' => $companyDesc,
    'keywords' => 'tailor shop, tailoring services, ' . $companyName . ', ' . (!empty($company['city']) ? escape($company['city']) . ' tailor' : '') . ', custom tailoring, professional tailor',
    'canonical' => $canonicalUrl,
    'og_image' => $companyImage,
    'og_type' => 'profile',
    'structured_data' => SEOHelper::generateLocalBusinessSchema($company)
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
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon(2).png">
    <!-- Custom CSS -->
    <link href="assets/css/style2.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
        }
        .navbar-logo {
            width: 120px;
            height: 70px;
            transition: var(--transition);
        }
        .btn {
            font-weight: 600;
            padding: 0.75rem 2rem;
            transition: var(--transition);
        }
        .header-browse-tailors-btn {
            border: 2px solid  #667eea !important;
            color: #667eea !important;
            background: transparent !important;
            border-radius: 50px !important;
        }
        .login-header-btn {
            border: 2px solid #667eea !important;
            color: white !important;
            background: #667eea !important;
            border-radius: 50px !important;
        }
        .ms-auto {
            display: flex;
            gap: 10px;
        }
        .btn-outline-light:hover {
            color: #667eea;
        }
        .profile-hero {
            background: linear-gradient(135deg, #4c51bf 0%, #667eea 100%);
            color: #fff;
            padding: 30px 0;
        }
        .profile-rating {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 16px;
            border-radius: 999px;
        }
        .review-card {
            border: 1px solid #edf2f7;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
            background: #fff;
        }
        .review-card img {
            max-height: 160px;
            object-fit: cover;
            border-radius: 10px;
        }
        .star-rating-input {
            display: inline-flex;
            flex-direction: row-reverse;
            gap: 6px;
        }
        .star-rating-input input {
            display: none;
        }
        .star-rating-input label {
            font-size: 2rem;
            color: #e2e8f0;
            cursor: pointer;
            transition: color 0.2s ease;
        }
        .star-rating-input input:checked ~ label,
        .star-rating-input label:hover,
        .star-rating-input label:hover ~ label {
            color: #f59e0b;
        }
        .container.py-5 {
            padding-top: 2rem !important;
            padding-bottom: 2rem !important;
        }

        @media (max-width: 767px) {
            .navbar .container {
                flex-direction: column;
                max-width: 100%;
            }
            .navbar .ms-auto {
                flex-direction: column;
                width: 100%;
            }
            .display-5.mb-2 {
                font-size: 24px;
            }
            .lead.mb-3 {
                font-size: 16px;
            }
            .card-title {
                font-size: 18px;
            }
        }
        @media (max-width: 576px) {
            .display-5.mb-2 {
                font-size: 24px;
            }
            .lead.mb-3 {
                font-size: 16px;
            }
            .card-title {
                font-size: 18px;
            }
            .contact-buttons {
                flex-direction: column;
            }
        }
        @media (max-width: 422px) {
            .review-stats {
                flex-direction: column;
                align-items: flex-start !important;
            }
            .profile-rating {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <img src="uploads/logos/main-logo.png" alt="TailorPro" class="navbar-logo me-2" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                <i class="fas fa-cut text-primary me-2" style="display: none;"></i>
            </a>
            <div class="ms-auto">
                <a href="tailors.php" class="btn  btn-sm header-browse-tailors-btn">Browse Tailors</a>
                <a href="admin/login.php" class="btn  btn-sm login-header-btn">Login</a>
            </div>
        </div>
    </nav>

    <div class="profile-hero">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-md-4 text-center">
                    <img src="<?php echo escape($company['logo'] ?: 'uploads/logos/default-shop.jpg'); ?>" alt="<?php echo escape($company['company_name']); ?>" class="img-fluid rounded shadow" style="max-height:370px; object-fit:cover;">
                </div>
                <div class="col-md-8">
                    <h1 class="display-5 mb-2"><?php echo escape($company['company_name']); ?></h1>
                    <p class="lead mb-3"><?php echo escape($company['description'] ?? ''); ?></p>
                    <div class="d-flex flex-wrap align-items-center gap-3 review-stats">
                        <div class="profile-rating">
                            <i class="fas fa-star text-warning"></i>
                            <strong><?php echo number_format($stats['average_rating'], 1); ?></strong>
                            <span class="text-white-50"><?php echo (int)$stats['review_count']; ?> reviews</span>
                        </div>
                        <?php if (!empty($company['years_experience'])): ?>
                            <span><?php echo (int)$company['years_experience']; ?>+ yrs experience</span>
                        <?php endif; ?>
                        <?php if (!empty($company['is_verified'])): ?>
                            <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Verified</span>
                        <?php endif; ?>
                    </div>
                    <div class="mt-3 d-flex flex-wrap gap-2 contact-buttons">
                        <a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $company['business_phone']); ?>" class="btn btn-outline-light">
                            <i class="fas fa-phone me-2"></i>Call
                        </a>
                        <?php if (!empty($company['whatsapp'])): ?>
                            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $company['whatsapp']); ?>" target="_blank" class="btn btn-success">
                                <i class="fab fa-whatsapp me-2"></i>WhatsApp
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($company['business_email'])): ?>
                            <a href="mailto:<?php echo escape($company['business_email']); ?>" class="btn btn-outline-light">
                                <i class="fas fa-envelope me-2"></i>Email
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h4 class="card-title mb-3">About</h4>
                        <?php if (!empty($company['business_address'])): ?>
                            <p><i class="fas fa-map-marker-alt text-primary me-2"></i><?php echo escape($company['business_address']); ?><?php echo $company['city'] ? ', ' . escape($company['city']) : ''; ?><?php echo $company['state'] ? ', ' . escape($company['state']) : ''; ?></p>
                        <?php endif; ?>
                        <?php if ($specialties): ?>
                            <h6>Specialties</h6>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <?php foreach ($specialties as $specialty): ?>
                                    <span class="badge bg-light text-dark"><?php echo escape($specialty); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($workingHours): ?>
                            <h6>Working Hours</h6>
                            <?php foreach ($workingHours as $day => $hours): ?>
                                <div class="d-flex justify-content-between small">
                                    <span class="text-muted"><?php echo escape($day); ?></span>
                                    <span><?php echo escape($hours); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <h4 class="card-title mb-3">Customer Reviews</h4>
                        <?php if (empty($reviews)): ?>
                            <p class="text-muted">No reviews yet. Be the first to share your experience!</p>
                        <?php else: ?>
                            <?php foreach ($reviews as $review): ?>
                                <div class="review-card">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo escape($review['reviewer_name']); ?></strong>
                                            <div class="small text-muted"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></div>
                                        </div>
                                        <div class="text-warning">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="<?php echo $i <= (int)$review['rating'] ? 'fas' : 'far'; ?> fa-star"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($review['review_text'])): ?>
                                        <p class="mt-2 mb-0"><?php echo escape($review['review_text']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($review['review_image'])): ?>
                                        <img src="<?php echo escape($review['review_image']); ?>" alt="Review" class="mt-3">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card shadow-sm mt-4">
                    <div class="card-body">
                        <h4 class="card-title mb-3">Share Your Experience</h4>
                        <form method="POST" action="ajax/submit_tailor_review.php" id="profileReviewForm" enctype="multipart/form-data">
                            <input type="hidden" name="company_id" value="<?php echo (int)$companyId; ?>">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Your Name *</label>
                                    <input type="text" name="reviewer_name" class="form-control" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email (optional)</label>
                                    <input type="email" name="reviewer_email" class="form-control">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Rating *</label>
                                <div class="star-rating-input">
                                    <input type="radio" id="profileRating5" name="rating" value="5">
                                    <label for="profileRating5"><i class="fas fa-star"></i></label>
                                    <input type="radio" id="profileRating4" name="rating" value="4">
                                    <label for="profileRating4"><i class="fas fa-star"></i></label>
                                    <input type="radio" id="profileRating3" name="rating" value="3">
                                    <label for="profileRating3"><i class="fas fa-star"></i></label>
                                    <input type="radio" id="profileRating2" name="rating" value="2">
                                    <label for="profileRating2"><i class="fas fa-star"></i></label>
                                    <input type="radio" id="profileRating1" name="rating" value="1">
                                    <label for="profileRating1"><i class="fas fa-star"></i></label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Review</label>
                                <textarea name="review_text" class="form-control" rows="3" placeholder="Share details about your experience (optional)"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Upload Image (optional)</label>
                                <input type="file" name="review_image" class="form-control" accept="image/*">
                                <div class="form-text">Maximum size 3MB.</div>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width: 100%;">Submit Review</button>
                            <p id="profileReviewMessage" class="small mt-3"></p>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Rating Breakdown</h5>
                        <?php for ($i = 5; $i >= 1; $i--) :
                            $count = $breakdown[$i] ?? 0;
                            $percent = $stats['review_count'] ? round(($count / $stats['review_count']) * 100) : 0;
                        ?>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span style="width:40px;"><?php echo $i; ?> ★</span>
                                <div class="progress flex-grow-1">
                                    <div class="progress-bar bg-warning" style="width: <?php echo $percent; ?>%;"></div>
                                </div>
                                <span style="width:30px;" class="text-muted text-end"><?php echo $count; ?></span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Contact</h5>
                        <p><i class="fas fa-phone me-2 text-primary"></i><?php echo escape($company['business_phone']); ?></p>
                        <?php if (!empty($company['business_email'])): ?>
                            <p><i class="fas fa-envelope me-2 text-primary"></i><?php echo escape($company['business_email']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($company['website'])): ?>
                            <p><i class="fas fa-globe me-2 text-primary"></i><a href="<?php echo escape($company['website']); ?>" target="_blank">Website</a></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-white border-top py-4">
        <div class="container text-center small text-muted">
            &copy; <?php echo date('Y'); ?> TailorPro. All rights reserved.
        </div>
    </footer>

    <script>
    document.getElementById('profileReviewForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = e.target;
        const messageEl = document.getElementById('profileReviewMessage');
        messageEl.textContent = 'Submitting your review...';
        messageEl.className = 'small text-muted';

        const formData = new FormData(form);

        fetch('ajax/submit_tailor_review.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Unable to submit review.');
                }
                messageEl.textContent = data.message;
                messageEl.className = 'small text-success';
                form.reset();
                setTimeout(() => window.location.reload(), 1200);
            })
            .catch(error => {
                messageEl.textContent = error.message;
                messageEl.className = 'small text-danger';
            });
    });
    
    // Track page view (wait for gtag to be available)
    <?php
    require_once 'helpers/GA4Helper.php';
    $pageTitle = $companyName . ' - Tailor Profile';
    $pageLocation = $canonicalUrl;
    $pageViewCode = GA4Helper::trackPageView($pageTitle, $pageLocation);
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- WhatsApp Button -->
    <?php require_once 'includes/whatsapp-button.php'; ?>
</body>
</html>

