<?php
/**
 * Submit Tailor Review AJAX Endpoint
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

require_once __DIR__ . '/../models/Company.php';
require_once __DIR__ . '/../models/CompanyReview.php';

function sanitize_text($value) {
    return trim(strip_tags($value));
}

try {
    $companyId = isset($_POST['company_id']) ? (int)$_POST['company_id'] : 0;
    $name = sanitize_text($_POST['reviewer_name'] ?? '');
    $email = filter_var($_POST['reviewer_email'] ?? '', FILTER_VALIDATE_EMAIL) ?: null;
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $reviewText = sanitize_text($_POST['review_text'] ?? '');

    if ($companyId <= 0) {
        throw new Exception('Invalid tailor reference.');
    }

    if (empty($name)) {
        throw new Exception('Please provide your name.');
    }

    if ($rating < 1 || $rating > 5) {
        throw new Exception('Please provide a rating between 1 and 5 stars.');
    }

    $companyModel = new Company();
    $reviewModel = new CompanyReview();

    $company = $companyModel->getPublicCompanyById($companyId);
    if (!$company) {
        throw new Exception('Tailor not found or not available for reviews.');
    }

    $imagePath = null;
    if (!empty($_FILES['review_image']['name'])) {
        $file = $_FILES['review_image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error uploading image. Please try again.');
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array(mime_content_type($file['tmp_name']), $allowedTypes)) {
            throw new Exception('Please upload a valid image (JPG, PNG, GIF, WEBP).');
        }

        if ($file['size'] > 3 * 1024 * 1024) {
            throw new Exception('Image size should be less than 3MB.');
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'review_' . $companyId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
        $destinationDir = __DIR__ . '/../uploads/reviews/';

        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        $destination = $destinationDir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new Exception('Failed to save the uploaded image.');
        }

        $imagePath = 'uploads/reviews/' . $filename;
    }

    $reviewId = $reviewModel->addReview([
        'company_id' => $companyId,
        'reviewer_name' => $name,
        'reviewer_email' => $email,
        'rating' => $rating,
        'review_text' => $reviewText,
        'review_image' => $imagePath,
        'status' => 'approved'
    ]);

    if (!$reviewId) {
        throw new Exception('Unable to save your review. Please try again.');
    }

    $stats = $companyModel->refreshReviewStats($companyId);

    echo json_encode([
        'success' => true,
        'message' => 'Thank you! Your review has been added.',
        'stats' => $stats
    ]);
} catch (Exception $e) {
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}


