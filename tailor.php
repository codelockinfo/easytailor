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
    return htmlspecialchars(htmlspecialchars_decode($value ?? '', ENT_QUOTES), ENT_QUOTES, 'UTF-8');
}

function getSpecialtyIcon($name) {
    $lower = strtolower($name);
    if (strpos($lower, 'suit') !== false) return 'fa-user-tie';
    if (strpos($lower, 'shirt') !== false) return 'fa-tshirt';
    if (strpos($lower, 'kurta') !== false || strpos($lower, 'pajama') !== false) return 'fa-shopping-bag';
    if (strpos($lower, 'alter') !== false) return 'fa-cut';
    if (strpos($lower, 'blouse') !== false || strpos($lower, 'gown') !== false || strpos($lower, 'lehenga') !== false || strpos($lower, 'saree') !== false) return 'fa-female';
    return 'fa-check';
}

function formatTailorProfileAddress($comp) {
    if (!is_array($comp)) return '';
    $cleanAddr = trim(preg_replace('/[\r\n]+/', ', ', (string)($comp['business_address'] ?? '')));
    $parts = [];
    if (!empty($cleanAddr)) $parts[] = $cleanAddr;
    
    $city = trim((string)($comp['city'] ?? ''));
    if (!empty($city) && stripos($cleanAddr, $city) === false) {
        $parts[] = $city;
    }
    
    $state = trim((string)($comp['state'] ?? ''));
    if (!empty($state) && stripos($cleanAddr, $state) === false) {
        $parts[] = $state;
    }
    
    $postal_code = trim((string)($comp['postal_code'] ?? ''));
    if (!empty($postal_code) && strpos($cleanAddr, $postal_code) === false) {
        $parts[] = $postal_code;
    }
    
    $full = implode(', ', $parts);
    $full = preg_replace('/\s*,\s*,+/', ', ', $full);
    $full = preg_replace('/\s+/', ' ', $full);
    return trim($full, ", \t\n\r\0\x0B");
}
?>
<?php
require_once 'helpers/SEOHelper.php';
$baseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$companyName = escape($company['company_name']);
$companyDesc = !empty($company['description']) ? escape($company['description']) : ($companyName . ' - Professional tailor shop offering quality tailoring services. ' . (!empty($company['city']) ? 'Located in ' . escape($company['city']) : ''));
$companyImage = !empty($company['logo']) ? $baseUrl . '/' . ltrim(str_replace(['../', './'], '', $company['logo']), '/') : $baseUrl . '/assets/images/og-image.jpg';
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
    <?php
    require_once 'helpers/GA4Helper.php';
    echo GA4Helper::generateBaseCode();
    ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="favicon.ico" sizes="16x16 32x32 48x48">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon(2).png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon(2).png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/favicon(2).png">
    <link href="assets/css/style16.css" rel="stylesheet">
    <style>
        html, body {
            overflow-x: clip;
            scroll-padding-top: 115px;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
            color: #334155;
        }

        /* ==========================================================================
           DESKTOP HERO SECTION (MATCHES REFERENCE DESIGN EXACTLY)
           ========================================================================== */
        .profile-hero {
            position: relative;
            background-color: #5b21b6;
            background-image: url('assets/images/tailor-hero-bg.png') !important;
            background-size: cover !important;
            background-position: center center !important;
            background-repeat: no-repeat !important;
            color: #ffffff;
            padding: 55px 0 65px;
            overflow: hidden;
        }

        .profile-shop-img-box {
            position: relative;
            display: inline-block;
            padding: 6px;
            background: rgba(255, 255, 255, 0.22);
            backdrop-filter: blur(12px);
            border: 3px solid rgba(255, 255, 255, 0.55);
            border-radius: 24px;
            box-shadow: 0 18px 42px rgba(0, 0, 0, 0.35);
            transition: transform 0.3s ease;
            max-width: 100%;
        }
        .profile-shop-img-box:hover {
            transform: translateY(-3px);
        }
        .profile-shop-img {
            width: 410px;
            height: 285px;
            max-width: 100%;
            object-fit: contain;
            border-radius: 18px;
            display: block;
        }

        .profile-shop-title {
            font-size: 38px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
            line-height: 1.2;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .profile-shop-desc {
            font-size: 15px;
            color: rgba(255, 255, 255, 0.92);
            margin-bottom: 16px;
            line-height: 1.55;
            max-width: 780px;
        }

        .profile-badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(15, 23, 42, 0.35);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50px;
            padding: 5px 14px;
            font-size: 13px;
            font-weight: 600;
            color: #ffffff;
        }

        .profile-badge-verified {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #16a34a;
            border-radius: 50px;
            padding: 5px 14px;
            font-size: 13px;
            font-weight: 600;
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(22, 163, 74, 0.3);
        }

        .profile-address-line {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.9);
            margin: 14px 0 20px;
            display: flex;
            align-items: center;
        }

        .profile-actions-row {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-profile-call,
        .btn-profile-email {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.16);
            border: 1.5px solid rgba(255, 255, 255, 0.4);
            color: #ffffff !important;
            border-radius: 50px;
            padding: 8px 24px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            backdrop-filter: blur(6px);
            transition: all 0.2s ease;
        }
        .btn-profile-call:hover,
        .btn-profile-email:hover {
            background: rgba(255, 255, 255, 0.28);
            border-color: #ffffff;
            transform: translateY(-1px);
        }

        .btn-profile-whatsapp {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #22c55e;
            border: 1.5px solid #22c55e;
            color: #ffffff !important;
            border-radius: 50px;
            padding: 8px 26px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 4px 14px rgba(34, 197, 94, 0.35);
            transition: all 0.2s ease;
        }
        .btn-profile-whatsapp:hover {
            background: #16a34a;
            border-color: #16a34a;
            box-shadow: 0 6px 20px rgba(34, 197, 94, 0.45);
            transform: translateY(-1px);
        }

        /* ==========================================================================
           MAIN BODY SECTION & CARDS
           ========================================================================== */
        .profile-body-wrap {
            padding: 42px 0 70px;
            position: relative;
        }

        .profile-card {
            background: #ffffff;
            border: 1px solid #ede9fe;
            border-radius: 20px;
            box-shadow: 0 8px 28px rgba(124, 58, 237, 0.05);
            padding: 28px;
            transition: box-shadow 0.25s ease;
            position: relative;
        }
        .profile-card:hover {
            box-shadow: 0 12px 34px rgba(124, 58, 237, 0.09);
        }

        .profile-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .profile-card-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #6366f1;
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
            box-shadow: 0 3px 10px rgba(99, 102, 241, 0.3);
        }

        .profile-card-title {
            font-size: 20px;
            font-weight: 700;
            color: #1e1b4b;
            margin: 0;
            letter-spacing: -0.3px;
        }

        .profile-about-address {
            font-size: 14.5px;
            color: #475569;
            display: flex;
            align-items: flex-start;
        }

        .profile-about-text {
            font-size: 14.5px;
            color: #475569;
            line-height: 1.65;
        }

        .profile-subheading {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            font-weight: 700;
            color: #1e1b4b;
            margin-bottom: 12px;
        }

        /* Specialties Pills */
        .specialty-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f5f3ff;
            border: 1.5px solid #ddd6fe;
            color: #4f46e5;
            border-radius: 12px;
            padding: 7px 16px;
            font-size: 13.5px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .specialty-pill:hover {
            background: #ede9fe;
            color: #4338ca;
            transform: translateY(-1px);
        }
        .specialty-pill i {
            color: #6366f1;
            font-size: 13px;
        }

        /* Working Hours Card Layout */
        .wh-card-layout {
            display: flex;
            align-items: center;
            gap: 22px;
            background: #faf8ff;
            border: 1.5px solid #f1edff;
            border-radius: 16px;
            padding: 20px 24px;
            margin-top: 14px;
        }

        .wh-icon-box {
            width: 72px;
            height: 72px;
            background: #ede9fe;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: #6366f1;
            flex-shrink: 0;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.18);
            animation: floatCalendarBox 3.8s ease-in-out infinite;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        @keyframes floatCalendarBox {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-5px);
            }
        }

        .profile-card:hover .wh-icon-box {
            transform: translateY(-6px) scale(1.04);
            box-shadow: 0 8px 22px rgba(99, 102, 241, 0.28);
        }

        .wh-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 32px;
            flex: 1;
        }

        .wh-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13.5px;
            gap: 10px;
        }
        .wh-day {
            color: #475569;
            font-weight: 500;
            min-width: 82px;
        }
        .wh-time {
            color: #1e1b4b;
            font-weight: 600;
        }
        .wh-time.closed {
            color: #ef4444;
            font-weight: 600;
        }

        /* Rating Breakdown Bars with Entry Growth & Shimmer Animation */
        .rating-bar-row {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 12px;
        }
        .rating-star-label {
            width: 38px;
            font-size: 14px;
            font-weight: 600;
            color: #1e1b4b;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .rating-bar-track {
            flex: 1;
            height: 12px;
            background: #eef2ff;
            border-radius: 20px;
            overflow: hidden;
            position: relative;
        }
        .rating-bar-fill {
            height: 100%;
            width: var(--target-width, 0%);
            background: linear-gradient(90deg, #6366f1 0%, #8b5cf6 35%, #c084fc 65%, #6366f1 100%);
            background-size: 200% 100%;
            border-radius: 20px;
            position: relative;
            overflow: hidden;
            animation: growRatingBar 1.2s cubic-bezier(0.16, 1, 0.3, 1) forwards, shimmerRatingBar 4s linear infinite;
            box-shadow: 0 2px 8px rgba(124, 58, 237, 0.25);
        }
        .rating-bar-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 60%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.55), transparent);
            animation: barShine 2.8s ease-in-out infinite;
        }

        @keyframes growRatingBar {
            0% {
                width: 0%;
                opacity: 0.3;
            }
            100% {
                width: var(--target-width);
                opacity: 1;
            }
        }

        @keyframes shimmerRatingBar {
            0% {
                background-position: 200% 0%;
            }
            100% {
                background-position: -200% 0%;
            }
        }

        @keyframes barShine {
            0% {
                left: -100%;
            }
            60%, 100% {
                left: 180%;
            }
        }

        .rating-count-label {
            width: 24px;
            text-align: right;
            font-size: 13.5px;
            font-weight: 500;
            color: #64748b;
        }

        /* Contact Card with 3D Floating Envelope */
        .contact-card-relative {
            position: relative;
            overflow: hidden;
        }
        .contact-info-item {
            display: flex;
            align-items: center;
            font-size: 14.5px;
            color: #334155;
            transition: transform 0.2s ease;
        }
        .contact-info-item:hover {
            transform: translateX(4px);
        }
        .contact-info-item i {
            color: #6366f1 !important;
            font-size: 16px;
            width: 22px;
        }
        .contact-link {
            color: #1e1b4b;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        .contact-link:hover {
            color: #4f46e5;
        }
        .contact-card-envelope-decor {
            position: absolute;
            right: 18px;
            bottom: 14px;
            opacity: 0.95;
            pointer-events: none;
            transform-origin: center center;
            animation: floatEnvelope 3.2s ease-in-out infinite;
            transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1), filter 0.35s ease;
        }

        @keyframes floatEnvelope {
            0%, 100% {
                transform: rotate(-6deg) translateY(0px);
            }
            50% {
                transform: rotate(-2deg) translateY(-8px);
            }
        }

        .contact-card-relative:hover .contact-card-envelope-decor {
            transform: rotate(0deg) translateY(-10px) scale(1.1);
            filter: drop-shadow(0 10px 20px rgba(124, 58, 237, 0.35));
        }

        /* Customer Reviews Decorative Laurel Wreath */
        .reviews-laurel-decor {
            opacity: 0.95;
            flex-shrink: 0;
        }

        /* Review Item Card (when reviews exist) */
        .review-item-card {
            background: #faf8ff;
            border: 1px solid #ede9fe;
            border-radius: 14px;
            padding: 16px 20px;
        }
        .review-author {
            color: #1e1b4b;
            font-size: 15px;
            font-weight: 600;
        }
        .review-item-text {
            color: #475569;
            font-size: 14px;
            line-height: 1.5;
        }
        .review-item-img {
            max-height: 120px;
            border-radius: 8px;
            object-fit: cover;
        }

        /* Share Your Experience Form */
        .profile-experience-card {
            border: 1.5px solid #d8b4fe !important;
            border-radius: 22px !important;
            padding: 18px 26px 20px !important;
            background: #ffffff;
            box-shadow: 0 10px 30px -5px rgba(124, 58, 237, 0.08), 0 0 0 1px rgba(196, 181, 253, 0.25) !important;
            transition: all 0.3s ease;
        }
        .profile-experience-card:hover {
            box-shadow: 0 14px 38px -5px rgba(124, 58, 237, 0.13), 0 0 0 1px rgba(167, 139, 250, 0.45) !important;
        }
        .profile-experience-card .profile-card-header {
            margin-bottom: 8px;
            padding-bottom: 0;
        }
        .profile-experience-card .profile-card-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35);
        }

        .clipboard-illustration-wrap {
            width: 100%;
            max-width: 270px;
            min-height: 235px;
            background: radial-gradient(circle at 50% 50%, rgba(238, 242, 255, 0.95) 0%, rgba(245, 243, 255, 0.5) 55%, transparent 75%);
            border-radius: 24px;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            box-shadow: inset 0 0 24px rgba(196, 181, 253, 0.2);
            overflow: visible;
        }

        .clipboard-3d-stage {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
        }

        .clipboard-floating-img {
            transform-origin: 50% 60%;
            animation: floatClipboardTilt 3.6s ease-in-out infinite;
            filter: drop-shadow(0 14px 26px rgba(99, 102, 241, 0.32));
            transition: transform 0.3s ease, filter 0.3s ease;
            cursor: pointer;
            display: block;
        }

        .clipboard-floating-img:hover {
            transform: rotate(-6deg) translateY(-8px) scale(1.05);
            filter: drop-shadow(0 18px 32px rgba(99, 102, 241, 0.42));
        }

        .clipboard-ground-shadow {
            width: 145px;
            height: 14px;
            background: radial-gradient(ellipse at center, rgba(124, 58, 237, 0.32) 0%, rgba(124, 58, 237, 0.08) 60%, transparent 80%);
            border-radius: 50%;
            margin-top: -6px;
            animation: floatShadowPulse 3.6s ease-in-out infinite;
        }
            margin-top: -10px;
            animation: floatShadowPulse 3.6s ease-in-out infinite;
        }

        @keyframes floatClipboardTilt {
            0%, 100% {
                transform: rotate(-10deg) translateY(0px);
            }
            50% {
                transform: rotate(-6deg) translateY(-10px);
            }
        }

        @keyframes floatShadowPulse {
            0%, 100% {
                transform: scale(1);
                opacity: 0.9;
            }
            50% {
                transform: scale(0.82);
                opacity: 0.5;
            }
        }

        .form-label-styled {
            font-size: 13px;
            font-weight: 600;
            color: #1e1b4b;
            margin-bottom: 4px;
            display: block;
        }
        .input-with-icon-wrap {
            position: relative;
        }
        .input-with-icon-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 13.5px;
            transition: color 0.2s ease;
        }
        .form-control-styled {
            width: 100%;
            padding: 8px 14px 8px 36px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 13.5px;
            color: #1e1b4b;
            background: #fbfaff;
            transition: border-color 0.2s, box-shadow 0.2s, background-color 0.2s;
        }
        .form-control-styled::placeholder {
            color: #94a3b8;
            font-size: 13px;
        }
        .form-control-styled:focus {
            outline: none;
            background: #ffffff;
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.12);
        }
        .input-with-icon-wrap:focus-within i {
            color: #7c3aed;
        }
        textarea.form-control-styled {
            padding: 8px 14px;
            min-height: 64px;
            height: 64px;
            resize: vertical;
        }

        /* Star Rating Custom */
        .star-rating-custom {
            display: inline-flex;
            flex-direction: row-reverse;
            gap: 6px;
            align-items: center;
        }
        .star-rating-custom input {
            display: none;
        }
        .star-rating-custom label {
            font-size: 20px;
            color: #cbd5e1;
            cursor: pointer;
            transition: color 0.15s ease, transform 0.15s ease;
            line-height: 1;
        }
        .star-rating-custom label:hover,
        .star-rating-custom label:hover ~ label,
        .star-rating-custom input:checked ~ label {
            color: #f59e0b;
            text-shadow: 0 2px 8px rgba(245, 158, 11, 0.35);
        }
        .star-rating-custom label:hover {
            transform: scale(1.18);
        }

        /* File Upload & Submit Button */
        .review-file-bar {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #fbfaff;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 4px 14px 4px 5px;
            transition: all 0.2s ease;
        }
        .review-file-bar:hover {
            border-color: #c4b5fd;
            background: #ffffff;
        }
        .file-upload-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #ede9fe;
            border: none;
            border-radius: 7px;
            padding: 5px 14px;
            font-size: 12.5px;
            font-weight: 600;
            color: #6366f1;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .file-upload-btn:hover {
            background: #ddd6fe;
            color: #4f46e5;
        }
        .file-bar-name {
            font-size: 12.5px;
            color: #64748b;
        }
        .file-bar-limit {
            font-size: 12px;
            color: #94a3b8;
            margin-left: 8px;
        }

        .btn-submit-review {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #6366f1 0%, #7c3aed 100%);
            color: #ffffff;
            border: none;
            border-radius: 50px;
            padding: 9px 28px;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 4px 14px rgba(124, 58, 237, 0.35);
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .btn-submit-review:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 22px rgba(124, 58, 237, 0.45);
            color: #ffffff;
        }

        /* Responsive Breakpoints */
        @media (max-width: 991px) {
            .profile-hero {
                padding: 40px 0 45px;
            }
            .profile-shop-img {
                width: 100%;
                max-width: 280px;
                height: 180px;
                margin: 0 auto;
            }
            .profile-shop-title {
                font-size: 28px;
            }
            .wh-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            .wh-card-layout {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        /* Mobile View Optimizations (< 768px) */
        @media (max-width: 767px) {
            body {
                padding-bottom: 80px !important;
            }
            .navbar,
            nav.navbar,
            .announcement-bar,
            .footer-section,
            footer.footer-section {
                display: none !important;
            }

            /* Profile Hero */
            .profile-hero {
                padding: 18px 0 24px !important;
                background-position: center top !important;
            }
            .profile-shop-img-box {
                display: block !important;
                margin: 0 auto 12px !important;
                max-width: 210px !important;
                padding: 4px !important;
                border-radius: 16px !important;
                border-width: 2px !important;
                box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2) !important;
            }
            .profile-shop-img {
                width: 100% !important;
                max-width: 202px !important;
                height: 125px !important;
                border-radius: 12px !important;
                margin: 0 auto !important;
                object-fit: contain !important;
            }
            .profile-shop-title {
                font-size: 21px !important;
                line-height: 1.25 !important;
                text-align: center !important;
                margin-bottom: 6px !important;
            }
            .profile-shop-desc {
                font-size: 12.5px !important;
                line-height: 1.45 !important;
                text-align: center !important;
                margin-bottom: 10px !important;
                padding: 0 4px !important;
                opacity: 0.92;
            }
            .profile-hero .d-flex.flex-wrap.align-items-center {
                justify-content: center !important;
                gap: 6px !important;
                margin-bottom: 8px !important;
            }
            .profile-badge-pill,
            .profile-badge-verified {
                font-size: 11px !important;
                padding: 3.5px 9px !important;
            }
            .profile-address-line {
                font-size: 11.5px !important;
                line-height: 1.4 !important;
                text-align: center !important;
                justify-content: center !important;
                margin-bottom: 14px !important;
                padding: 0 6px !important;
            }
            .profile-actions-row {
                display: flex !important;
                flex-wrap: nowrap !important;
                gap: 8px !important;
                justify-content: center !important;
                width: 100% !important;
                padding: 0 4px !important;
            }
            .profile-actions-row a {
                flex: 1 !important;
                min-width: 0 !important;
                padding: 8px 6px !important;
                font-size: 12px !important;
                font-weight: 600 !important;
                justify-content: center !important;
                text-align: center !important;
                border-radius: 50px !important;
                white-space: nowrap !important;
            }
            .profile-actions-row a i {
                margin-right: 4px !important;
                font-size: 11px !important;
            }

            /* Cards & Body */
            .profile-body-wrap {
                padding: 16px 0 24px !important;
            }
            .profile-card {
                padding: 16px 14px !important;
                border-radius: 16px !important;
            }
            .profile-card-header {
                margin-bottom: 12px !important;
            }
            .profile-card-title {
                font-size: 16px !important;
            }
            .profile-card-icon {
                width: 32px !important;
                height: 32px !important;
                font-size: 13px !important;
            }
            .profile-about-address {
                font-size: 12.5px !important;
                line-height: 1.4 !important;
            }
            .profile-about-text {
                font-size: 13px !important;
                line-height: 1.5 !important;
            }
            .specialty-pill {
                font-size: 12px !important;
                padding: 4px 9px !important;
            }
            .wh-card-layout {
                padding: 12px 10px !important;
            }
            .wh-item {
                font-size: 12px !important;
                padding: 5px 8px !important;
            }

            /* Contact Card */
            .contact-card-envelope-decor {
                right: 6px !important;
                bottom: 6px !important;
                max-width: 50px !important;
                opacity: 0.18 !important;
            }
            .contact-link {
                font-size: 12.5px !important;
            }

            /* Rating Breakdown */
            .rating-bar-row {
                gap: 8px !important;
                margin-bottom: 6px !important;
            }
            .rating-star-label {
                font-size: 11.5px !important;
                width: 28px !important;
            }
            .rating-count-label {
                font-size: 11.5px !important;
                width: 20px !important;
            }
            .rating-bar-track {
                height: 8px !important;
            }

            /* Reviews */
            .review-item-card {
                padding: 12px 12px !important;
            }
            .review-author {
                font-size: 13px !important;
            }
            .review-item-text {
                font-size: 12.5px !important;
            }

            /* Experience Form */
            .profile-experience-card {
                padding: 16px 12px !important;
            }
            .form-label-custom {
                font-size: 12px !important;
            }
            .input-icon-wrap .form-control {
                font-size: 12.5px !important;
                padding: 8px 12px 8px 34px !important;
            }
            .input-icon-wrap i {
                font-size: 12px !important;
                left: 11px !important;
            }
            .textarea-counter-wrap textarea {
                font-size: 12.5px !important;
                padding: 8px 10px !important;
                min-height: 80px !important;
            }
            .file-upload-bar {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 6px !important;
                padding: 8px 10px !important;
            }
            .btn-submit-review {
                width: 100% !important;
                padding: 10px 18px !important;
                font-size: 13.5px !important;
                justify-content: center !important;
            }
        }
    </style>
</head>
<body>
    <?php require_once 'includes/nav.php'; ?>

    <!-- HERO SECTION -->
    <section class="profile-hero">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-xl-4 col-lg-5 col-md-5 text-center text-md-start">
                    <div class="profile-shop-img-box">
                        <img src="<?php echo escape($company['logo'] ? ltrim(str_replace(['../', './'], '', $company['logo']), '/') : 'uploads/logos/default-shop.jpg'); ?>" 
                             alt="<?php echo $companyName; ?>" 
                             class="profile-shop-img"
                             onerror="this.onerror=null; this.src='uploads/logos/default-shop.jpg';">
                    </div>
                </div>
                <div class="col-xl-8 col-lg-7 col-md-7">
                    <h1 class="profile-shop-title"><?php echo $companyName; ?></h1>
                    <?php if (!empty($company['description'])): ?>
                        <p class="profile-shop-desc"><?php echo escape($company['description']); ?></p>
                    <?php endif; ?>
                    
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="profile-badge-pill">
                            <i class="fas fa-star text-warning"></i>
                            <strong><?php echo number_format($stats['average_rating'], 1); ?></strong>
                            <span class="text-white-50 ms-1"><?php echo (int)$stats['review_count']; ?> reviews</span>
                        </div>
                        <?php if (!empty($company['years_experience'])): ?>
                            <div class="profile-badge-pill">
                                <span><?php echo (int)$company['years_experience']; ?>+ yrs experience</span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($company['is_verified'])): ?>
                            <div class="profile-badge-verified">
                                <i class="fas fa-check-circle me-1"></i>Verified
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php 
                    $profileFullAddress = formatTailorProfileAddress($company);
                    if (!empty($profileFullAddress)): ?>
                        <div class="profile-address-line">
                            <i class="fas fa-map-marker-alt text-warning me-2"></i><?php echo escape($profileFullAddress); ?>
                        </div>
                    <?php endif; ?>

                    <div class="profile-actions-row">
                        <a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $company['business_phone']); ?>" class="btn-profile-call">
                            <i class="fas fa-phone me-2"></i>Call
                        </a>
                        <?php 
                        $waNumber = !empty($company['whatsapp']) ? $company['whatsapp'] : $company['business_phone'];
                        if (!empty($waNumber)): ?>
                            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $waNumber); ?>" target="_blank" class="btn-profile-whatsapp">
                                <i class="fab fa-whatsapp me-2"></i>WhatsApp
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($company['business_email'])): ?>
                            <a href="mailto:<?php echo escape($company['business_email']); ?>" class="btn-profile-email">
                                <i class="fas fa-envelope me-2"></i>Email
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- MAIN BODY SECTION -->
    <section class="profile-body-wrap">
        <div class="container">
            <div class="row g-4">
                <!-- Left Column: About Card -->
                <div class="col-lg-7">
                    <div class="profile-card h-100 mb-0">
                        <div class="profile-card-header">
                            <span class="profile-card-icon"><i class="fas fa-info"></i></span>
                            <h3 class="profile-card-title">About</h3>
                        </div>
                        <div class="profile-card-body">
                            <?php 
                            $profileAboutAddress = formatTailorProfileAddress($company);
                            if (!empty($profileAboutAddress)): ?>
                                <div class="profile-about-address mb-3">
                                    <i class="fas fa-map-marker-alt text-primary me-2 mt-1"></i>
                                    <span><?php echo escape($profileAboutAddress); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($company['description'])): ?>
                                <p class="profile-about-text mb-4"><?php echo escape($company['description']); ?></p>
                            <?php endif; ?>

                            <?php if (!empty($specialties)): ?>
                                <div class="profile-subheading">
                                    <i class="fas fa-tag text-primary"></i>
                                    <span>Specialties</span>
                                </div>
                                <div class="d-flex flex-wrap gap-2 mb-4">
                                    <?php foreach ($specialties as $specialty): ?>
                                        <span class="specialty-pill">
                                            <i class="fas <?php echo getSpecialtyIcon($specialty); ?>"></i>
                                            <?php echo escape($specialty); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($workingHours)): ?>
                                <div class="profile-subheading">
                                    <i class="fas fa-calendar-alt text-primary"></i>
                                    <span>Working Hours</span>
                                </div>
                                <div class="wh-card-layout">
                                    <div class="wh-icon-box">
                                        <i class="fas fa-calendar-check"></i>
                                    </div>
                                    <div class="wh-grid">
                                        <?php 
                                        $whKeys = array_keys($workingHours);
                                        $halfCount = (int)ceil(count($whKeys) / 2);
                                        $col1Keys = array_slice($whKeys, 0, $halfCount);
                                        $col2Keys = array_slice($whKeys, $halfCount);
                                        ?>
                                        <div class="wh-col">
                                            <?php foreach ($col1Keys as $day): 
                                                $hrs = $workingHours[$day] ?? '';
                                                $isClosed = (stripos($hrs, 'closed') !== false);
                                            ?>
                                                <div class="wh-item mb-2">
                                                    <span class="wh-day"><?php echo escape(ucfirst($day)); ?></span>
                                                    <span class="wh-time <?php echo $isClosed ? 'closed' : ''; ?>"><?php echo escape($hrs); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="wh-col">
                                            <?php foreach ($col2Keys as $day): 
                                                $hrs = $workingHours[$day] ?? '';
                                                $isClosed = (stripos($hrs, 'closed') !== false);
                                            ?>
                                                <div class="wh-item mb-2">
                                                    <span class="wh-day"><?php echo escape(ucfirst($day)); ?></span>
                                                    <span class="wh-time <?php echo $isClosed ? 'closed' : ''; ?>"><?php echo escape($hrs); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Rating Breakdown & Contact Cards -->
                <div class="col-lg-5 d-flex flex-column gap-4">
                    <!-- Rating Breakdown Card -->
                    <div class="profile-card mb-0">
                        <div class="profile-card-header">
                            <span class="profile-card-icon"><i class="fas fa-star"></i></span>
                            <h3 class="profile-card-title">Rating Breakdown</h3>
                        </div>
                        <div class="profile-card-body">
                            <?php for ($i = 5; $i >= 1; $i--) :
                                $count = $breakdown[$i] ?? 0;
                                $percent = $stats['review_count'] ? round(($count / $stats['review_count']) * 100) : 0;
                            ?>
                                <div class="rating-bar-row">
                                    <span class="rating-star-label"><?php echo $i; ?> <i class="fas fa-star" style="font-size:11px; color:#fbbf24;"></i></span>
                                    <div class="rating-bar-track">
                                        <div class="rating-bar-fill" style="--target-width: <?php echo $percent; ?>%; width: <?php echo $percent; ?>%;"></div>
                                    </div>
                                    <span class="rating-count-label"><?php echo $count; ?></span>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Contact Card -->
                    <div class="profile-card contact-card-relative mb-0 flex-grow-1">
                        <div class="profile-card-header">
                            <span class="profile-card-icon" title="Contact">
                                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
                                    <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
                                </svg>
                            </span>
                            <h3 class="profile-card-title">Contact</h3>
                        </div>
                        <div class="profile-card-body">
                            <div class="contact-info-item mb-3">
                                <i class="fas fa-phone text-primary me-3"></i>
                                <a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $company['business_phone']); ?>" class="contact-link">
                                    <?php echo escape($company['business_phone']); ?>
                                </a>
                            </div>
                            <?php if (!empty($company['business_email'])): ?>
                                <div class="contact-info-item mb-2">
                                    <i class="fas fa-envelope text-primary me-3"></i>
                                    <a href="mailto:<?php echo escape($company['business_email']); ?>" class="contact-link">
                                        <?php echo escape($company['business_email']); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <!-- 3D Decorative Envelope Icon on Bottom Right -->
                        <div class="contact-card-envelope-decor" aria-hidden="true">
                            <svg width="78" height="62" viewBox="0 0 78 62" fill="none">
                                <g filter="drop-shadow(0 6px 14px rgba(124, 58, 237, 0.2))">
                                    <rect x="5" y="6" width="68" height="48" rx="14" fill="#ede9fe"/>
                                    <path d="M8 11L39 34L70 11" stroke="#8b5cf6" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M8 50L29 30" stroke="#a78bfa" stroke-width="2.5" stroke-linecap="round"/>
                                    <path d="M70 50L49 30" stroke="#a78bfa" stroke-width="2.5" stroke-linecap="round"/>
                                </g>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Customer Reviews Card (Full Width) -->
                <div class="col-12">
                    <div class="profile-card mb-0">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="profile-card-icon"><i class="fas fa-comment-dots"></i></span>
                                    <h3 class="profile-card-title mb-0">Customer Reviews</h3>
                                </div>
                                <?php if (empty($reviews)): ?>
                                    <p class="text-muted mb-0 ms-0 ms-md-5 ps-0 ps-md-1">No reviews yet. Be the first to share your experience!</p>
                                <?php else: ?>
                                    <p class="text-muted mb-0 ms-0 ms-md-5 ps-0 ps-md-1">Showing <?php echo count($reviews); ?> approved customer reviews.</p>
                                <?php endif; ?>
                            </div>
                           
                        </div>

                        <?php if (!empty($reviews)): ?>
                            <div class="row g-3 mt-3">
                                <?php foreach ($reviews as $review): ?>
                                    <div class="col-md-6">
                                        <div class="review-item-card">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div>
                                                    <strong class="review-author"><?php echo escape($review['reviewer_name']); ?></strong>
                                                    <div class="small text-muted"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></div>
                                                </div>
                                                <div class="text-warning">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="<?php echo $i <= (int)$review['rating'] ? 'fas' : 'far'; ?> fa-star"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            <?php if (!empty($review['review_text'])): ?>
                                                <p class="review-item-text mb-0"><?php echo escape($review['review_text']); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($review['review_image'])): ?>
                                                <img src="<?php echo escape($review['review_image']); ?>" alt="Review photo" class="review-item-img mt-2">
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Share Your Experience Card (Full Width) -->
                <div class="col-12">
                    <div class="profile-card profile-experience-card mb-0">
                        <div class="profile-card-header">
                            <span class="profile-card-icon"><i class="fas fa-edit"></i></span>
                            <h3 class="profile-card-title">Share Your Experience</h3>
                        </div>

                        <div class="row align-items-start g-4">
                            <!-- Left 3D Vector Illustration (Desktop) -->
                            <div class="col-lg-3 d-none d-lg-flex align-items-center justify-content-center">
                                <div class="clipboard-illustration-wrap">
                                    <div class="clipboard-3d-stage">
                                        <svg class="clipboard-floating-img" width="235" height="260" viewBox="15 8 152 192" fill="none">
                                            <rect x="25" y="24" width="130" height="170" rx="20" fill="url(#clipboardGrad)" filter="drop-shadow(0 10px 20px rgba(99, 102, 241, 0.28))"/>
                                            <rect x="36" y="38" width="108" height="144" rx="12" fill="#ffffff"/>
                                            <rect x="62" y="14" width="56" height="22" rx="8" fill="#e0e7ff"/>
                                            <rect x="74" y="18" width="32" height="8" rx="4" fill="#6366f1"/>
                                            <path d="M54 58L55.5 61.5L59 62L56.5 64.5L57.2 68L54 66.2L50.8 68L51.5 64.5L49 62L52.5 61.5L54 58Z" fill="#fbbf24"/>
                                            <path d="M68 58L69.5 61.5L73 62L70.5 64.5L71.2 68L68 66.2L64.8 68L65.5 64.5L63 62L66.5 61.5L68 58Z" fill="#fbbf24"/>
                                            <path d="M82 58L83.5 61.5L87 62L84.5 64.5L85.2 68L82 66.2L78.8 68L79.5 64.5L77 62L80.5 61.5L82 58Z" fill="#fbbf24"/>
                                            <rect x="48" y="80" width="16" height="16" rx="4" fill="#ede9fe"/>
                                            <path d="M52 88L55 91L60 84" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <line x1="70" y1="88" x2="124" y2="88" stroke="#cbd5e1" stroke-width="3" stroke-linecap="round"/>
                                            <rect x="48" y="106" width="16" height="16" rx="4" fill="#ede9fe"/>
                                            <path d="M52 114L55 117L60 110" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <line x1="70" y1="114" x2="116" y2="114" stroke="#cbd5e1" stroke-width="3" stroke-linecap="round"/>
                                            <rect x="48" y="132" width="16" height="16" rx="4" fill="#ede9fe"/>
                                            <path d="M52 140L55 143L60 136" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            <line x1="70" y1="140" x2="120" y2="140" stroke="#cbd5e1" stroke-width="3" stroke-linecap="round"/>
                                            <g transform="rotate(32 145 125)">
                                                <rect x="135" y="60" width="10" height="90" rx="5" fill="#4f46e5"/>
                                                <path d="M135 150L140 162L145 150Z" fill="#f59e0b"/>
                                                <rect x="136" y="62" width="8" height="12" rx="2" fill="#c7d2fe"/>
                                            </g>
                                            <defs>
                                                <linearGradient id="clipboardGrad" x1="25" y1="24" x2="155" y2="194" gradientUnits="userSpaceOnUse">
                                                    <stop stop-color="#818cf8"/>
                                                    <stop offset="1" stop-color="#6366f1"/>
                                                </linearGradient>
                                            </defs>
                                        </svg>
                                        <div class="clipboard-ground-shadow"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Form -->
                            <div class="col-lg-9">
                                <form method="POST" action="ajax/submit_tailor_review.php" id="profileReviewForm" enctype="multipart/form-data">
                                    <input type="hidden" name="company_id" value="<?php echo (int)$companyId; ?>">
                                    
                                    <div class="row g-3 mb-2">
                                        <div class="col-md-6">
                                            <label class="form-label-styled">Your Name *</label>
                                            <div class="input-with-icon-wrap">
                                                <i class="fas fa-user"></i>
                                                <input type="text" name="reviewer_name" class="form-control-styled" placeholder="Enter your name" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label-styled">Email (optional)</label>
                                            <div class="input-with-icon-wrap">
                                                <i class="fas fa-envelope"></i>
                                                <input type="email" name="reviewer_email" class="form-control-styled" placeholder="Enter your email">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label-styled">Rating *</label>
                                        <div class="star-rating-custom">
                                            <input type="radio" id="profileRating5" name="rating" value="5" required>
                                            <label for="profileRating5" title="5 stars"><i class="fas fa-star"></i></label>
                                            <input type="radio" id="profileRating4" name="rating" value="4">
                                            <label for="profileRating4" title="4 stars"><i class="fas fa-star"></i></label>
                                            <input type="radio" id="profileRating3" name="rating" value="3">
                                            <label for="profileRating3" title="3 stars"><i class="fas fa-star"></i></label>
                                            <input type="radio" id="profileRating2" name="rating" value="2">
                                            <label for="profileRating2" title="2 stars"><i class="fas fa-star"></i></label>
                                            <input type="radio" id="profileRating1" name="rating" value="1">
                                            <label for="profileRating1" title="1 star"><i class="fas fa-star"></i></label>
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label-styled">Review</label>
                                        <textarea name="review_text" class="form-control-styled" rows="2" placeholder="Share details about your experience (optional)"></textarea>
                                    </div>

                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-2 pt-1">
                                        <div>
                                            <label class="form-label-styled mb-1">Upload Image (optional)</label>
                                            <div class="review-file-bar">
                                                <label class="file-upload-btn mb-0" for="reviewImageInput">
                                                    <i class="fas fa-paperclip"></i>
                                                    <span>Choose File</span>
                                                </label>
                                                <input type="file" name="review_image" id="reviewImageInput" accept="image/*" style="display:none;">
                                                <span id="fileNameDisplay" class="file-bar-name">No file chosen</span>
                                                <span class="file-bar-limit">Maximum size 3MB.</span>
                                            </div>
                                        </div>
                                        <div class="pt-2 pt-sm-0">
                                            <button type="submit" class="btn-submit-review">
                                                <i class="fas fa-paper-plane me-2"></i> Submit Review
                                            </button>
                                        </div>
                                    </div>

                                    <p id="profileReviewMessage" class="small mt-2 mb-0"></p>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php require_once 'includes/footer.php'; ?>

    <script>
    // File input label update
    const fileInput = document.getElementById('reviewImageInput');
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const fileName = e.target.files && e.target.files[0] ? e.target.files[0].name : 'No file chosen';
            document.getElementById('fileNameDisplay').textContent = fileName;
        });
    }

    // Submit review form via AJAX
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
                document.getElementById('fileNameDisplay').textContent = 'No file chosen';
                setTimeout(() => window.location.reload(), 1200);
            })
            .catch(error => {
                messageEl.textContent = error.message;
                messageEl.className = 'small text-danger';
            });
    });

    <?php
    require_once 'helpers/GA4Helper.php';
    $pageTitle = $companyName . ' - Tailor Profile';
    $pageLocation = $canonicalUrl;
    $pageViewCode = GA4Helper::trackPageView($pageTitle, $pageLocation);
    ?>
    (function() {
        var attempts = 0;
        var maxAttempts = 50;
        
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
        firePageView();
    })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php require_once 'includes/whatsapp-button.php'; ?>
    <script src="assets/js/script2.js"></script>
    <?php require_once 'includes/go-to-top-button.php'; ?>
</body>
</html>