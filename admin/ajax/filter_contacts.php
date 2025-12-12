<?php
/**
 * AJAX Contact Filter Endpoint
 * Tailoring Management System
 */

// Set content type to JSON
header('Content-Type: application/json');

// Start output buffering to catch any errors
ob_start();

try {
    // Get the directory of this script
    $scriptDir = dirname(__FILE__);
    $rootDir = dirname($scriptDir);
    
    require_once $rootDir . '/../config/config.php';

    // Check if user is logged in
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    require_once $rootDir . '/../models/Contact';

    // Get filter parameters
    $category = $_GET['category'] ?? '';
    $search = $_GET['search'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? RECORDS_PER_PAGE);
    
    // Validate and fix limit parameter
    if ($limit <= 0) {
        $limit = RECORDS_PER_PAGE; // Default to records per page if limit is 0 or negative
    }
    
    // Validate page parameter
    if ($page <= 0) {
        $page = 1; // Default to page 1 if page is 0 or negative
    }

    $contactModel = new Contact();
    
    // Build conditions
    $conditions = [];
    if (!empty($category)) {
        $conditions['category'] = $category;
    }
    
    // Get contacts
    $offset = ($page - 1) * $limit;
    $contacts = $contactModel->findAll($conditions, 'name ASC', $limit, $offset);
    
    // If search is provided, filter results manually
    if (!empty($search)) {
        $searchLower = strtolower($search);
        $contacts = array_filter($contacts, function($contact) use ($searchLower) {
            return strpos(strtolower($contact['name']), $searchLower) !== false ||
                   strpos(strtolower($contact['company'] ?? ''), $searchLower) !== false ||
                   strpos(strtolower($contact['email'] ?? ''), $searchLower) !== false ||
                   strpos(strtolower($contact['phone'] ?? ''), $searchLower) !== false;
        });
    }
    
    $totalContacts = count($contacts);
    $totalPages = $limit > 0 ? ceil($totalContacts / $limit) : 1;
    
    // Get categories
    $categories = ['Supplier', 'Partner', 'Vendor', 'Service Provider', 'Other'];
    
    // Format contacts for display - match original table structure exactly
    $formattedContacts = [];
    foreach ($contacts as $contact) {
        $formattedContacts[] = [
            'id' => $contact['id'],
            'name' => htmlspecialchars($contact['name']),
            'company' => htmlspecialchars($contact['company'] ?? ''),
            'email' => htmlspecialchars($contact['email'] ?? ''),
            'phone' => htmlspecialchars($contact['phone'] ?? ''),
            'address' => htmlspecialchars($contact['address'] ?? ''),
            'category' => htmlspecialchars($contact['category']),
            'notes' => htmlspecialchars($contact['notes'] ?? ''),
            'status' => $contact['status'],
            'created_at' => $contact['created_at']
        ];
    }
    
    // Format filter options
    $filterOptions = [
        'categories' => array_map(function($category) {
            return [
                'value' => $category,
                'label' => htmlspecialchars($category)
            ];
        }, $categories)
    ];
    
    echo json_encode([
        'success' => true,
        'contacts' => $formattedContacts,
        'filter_options' => $filterOptions,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_contacts' => $totalContacts,
            'has_previous' => $page > 1,
            'has_next' => $page < $totalPages
        ]
    ]);
    
} catch (Exception $e) {
    // Clear any output
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Filter failed: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    // Clear any output
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Filter failed: ' . $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();
