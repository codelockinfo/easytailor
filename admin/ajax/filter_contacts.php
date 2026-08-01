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
    $offset = ($page - 1) * $limit;
    $contacts = $contactModel->findAll($conditions, 'name ASC', $limit, $offset);
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