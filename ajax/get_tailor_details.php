<?php
/**
 * Get Tailor Details AJAX Endpoint
 * Returns tailor profile details with reviews
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../models/Company.php';
require_once __DIR__ . '/../models/CompanyReview.php';

function normalize_path($path, $fallback) {
    if (empty($path)) {
        return $fallback;
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $clean = ltrim(str_replace(['../', './'], '', $path), '/');
    if (!file_exists(__DIR__ . '/../' . $clean)) {
        return $fallback;
    }
    return $clean;
}

try {
    $companyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($companyId <= 0) {
        throw new Exception('Invalid tailor reference.');
    }

    $companyModel = new Company();
    $reviewModel = new CompanyReview();

    $company = $companyModel->getPublicCompanyById($companyId);
    if (!$company) {
        throw new Exception('Tailor not found or not available.');
    }

    $shopImage = normalize_path($company['logo'] ?? '', 'uploads/logos/default-shop.jpg');
    $specialties = [];
    if (!empty($company['specialties'])) {
        $decoded = json_decode($company['specialties'], true);
        if (is_array($decoded)) {
            $specialties = $decoded;
        }
    }

    $workingHours = [];
    if (!empty($company['working_hours'])) {
        $decodedHours = json_decode($company['working_hours'], true);
        if (is_array($decodedHours)) {
            $workingHours = $decodedHours;
        }
    }

    $companyData = [
        'id' => (int)$company['id'],
        'shop_name' => $company['company_name'],
        'owner_name' => $company['owner_name'],
        'description' => $company['description'] ?? '',
        'phone' => $company['business_phone'],
        'whatsapp' => $company['whatsapp'] ?? $company['business_phone'],
        'email' => $company['business_email'],
        'address' => $company['business_address'] ?? '',
        'city' => $company['city'] ?? '',
        'state' => $company['state'] ?? '',
        'postal_code' => $company['postal_code'] ?? '',
        'rating' => (float)($company['rating'] ?? 0),
        'total_reviews' => (int)($company['total_reviews'] ?? 0),
        'years_experience' => (int)($company['years_experience'] ?? 0),
        'is_verified' => (bool)($company['is_verified'] ?? false),
        'is_featured' => (bool)($company['is_featured'] ?? false),
        'shop_image' => $shopImage,
        'specialties' => $specialties,
        'working_hours' => $workingHours,
    ];

    $reviewsRaw = $reviewModel->getApprovedReviews($companyId, 50);
    $reviews = [];

    foreach ($reviewsRaw as $review) {
        $imagePath = normalize_path($review['review_image'] ?? '', '');
        $reviews[] = [
            'id' => (int)$review['id'],
            'reviewer_name' => htmlspecialchars($review['reviewer_name']),
            'rating' => (int)$review['rating'],
            'review_text' => isset($review['review_text']) ? htmlspecialchars($review['review_text']) : '',
            'review_image' => $imagePath,
            'created_at' => date('M d, Y', strtotime($review['created_at']))
        ];
    }

    $stats = $reviewModel->getRatingStats($companyId);
    $breakdown = $reviewModel->getRatingBreakdown($companyId);

    echo json_encode([
        'success' => true,
        'company' => $companyData,
        'reviews' => $reviews,
        'stats' => [
            'average_rating' => $stats['average_rating'],
            'total_reviews' => $stats['review_count'],
            'breakdown' => $breakdown
        ]
    ]);
} catch (Exception $e) {
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}


