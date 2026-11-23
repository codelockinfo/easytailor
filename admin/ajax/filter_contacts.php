<?php
header('Content-Type: application/json');
ob_start();
try {
    $scriptDir = dirname(__FILE__);
    $rootDir = dirname($scriptDir);
    
    require_once $rootDir . '/../config/config.php';
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    require_once $rootDir . '/../models/Contact.php';
    
    $category = $_GET['category'] ?? '';
    $search = $_GET['search'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? RECORDS_PER_PAGE);
    if ($limit <= 0) {
        $limit = RECORDS_PER_PAGE;
    }
    if ($page <= 0) {
        $page = 1; 
    }
    $contactModel = new Contact();
    $conditions = [];
    if (!empty($category)) {
        $conditions['category'] = $category;
    }
    $companyId = get_company_id();
    if ($companyId) {
        $conditions['company_id'] = $companyId;
    }

    $allContacts = $contactModel->findAll($conditions, 'name ASC');
    if (!empty($search)) {
        $searchLower = mb_strtolower(trim($search));
        $filteredContacts = array_values(array_filter($allContacts, function($contact) use ($searchLower) {
            return mb_stripos($contact['name'] ?? '', $searchLower) !== false ||
                   mb_stripos($contact['company'] ?? '', $searchLower) !== false ||
                   mb_stripos($contact['email'] ?? '', $searchLower) !== false ||
                   mb_stripos($contact['phone'] ?? '', $searchLower) !== false ||
                   mb_stripos($contact['notes'] ?? '', $searchLower) !== false;
        }));
    } else {
        $filteredContacts = $allContacts;
    }
    $totalContacts = count($filteredContacts);
    $offset = ($page - 1) * $limit;
    $contacts = array_slice($filteredContacts, $offset, $limit);
    $totalPages = $limit > 0 ? max(1, ceil($totalContacts / $limit)) : 1;
    $categories = ['Supplier', 'Partner', 'Vendor', 'Service Provider', 'Other'];
    $formattedContacts = [];
    foreach ($contacts as $contact) {
        $formattedContacts[] = [
            'id' => $contact['id'],
            'name' => $contact['name'],
            'company' => $contact['company'] ?? '',
            'email' => $contact['email'] ?? '',
            'phone' => $contact['phone'] ?? '',
            'address' => $contact['address'] ?? '',
            'category' => $contact['category'],
            'notes' => $contact['notes'] ?? '',
            'status' => $contact['status'],
            'created_at' => $contact['created_at']
        ];
    }
    $filterOptions = [
        'categories' => array_map(function($category) {
            return [
                'value' => $category,
                'label' => $category
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
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Filter failed: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Filter failed: ' . $e->getMessage()
    ]);
}
ob_end_flush();