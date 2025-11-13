<?php
/**
 * AJAX User Filter Endpoint
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

    $companyId = get_company_id();
    if (!$companyId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Company context missing']);
        exit;
    }

    require_once $rootDir . '/../models/User.php';

    // Get parameters
    $search = $_GET['search'] ?? '';
    $role = $_GET['role'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    
    // Validate and fix limit parameter
    if ($limit <= 0) {
        $limit = 20; // Default to 20 if limit is 0 or negative
    }
    
    // Validate page parameter
    if ($page <= 0) {
        $page = 1; // Default to page 1 if page is 0 or negative
    }

    $userModel = new User();
    
    // Build conditions
    $conditions = [];
    if (!empty($role)) {
        $conditions['role'] = $role;
    }
    
    // Fetch users for the company
    $allUsers = $userModel->getCompanyUsers($companyId, $conditions, 'full_name ASC');
    
    $offset = ($page - 1) * $limit;
    
    // If search is provided, filter results manually
    $filteredUsers = $allUsers;
    if (!empty($search)) {
        $searchLower = strtolower($search);
        $filteredUsers = array_filter($filteredUsers, function($user) use ($searchLower) {
            return strpos(strtolower($user['full_name']), $searchLower) !== false ||
                   strpos(strtolower($user['username']), $searchLower) !== false ||
                   strpos(strtolower($user['email']), $searchLower) !== false;
        });
    }

    $filteredUsers = array_values($filteredUsers);
    $totalUsers = count($filteredUsers);
    $users = array_slice($filteredUsers, $offset, $limit);
    
    $totalPages = $limit > 0 ? ceil($totalUsers / $limit) : 1;
    
    // Get filter options
    $allRolesUsers = $userModel->getCompanyUsers($companyId);
    
    // Format users for display
    $formattedUsers = [];
    foreach ($users as $user) {
        $formattedUsers[] = [
            'id' => $user['id'],
            'username' => htmlspecialchars($user['username']),
            'email' => htmlspecialchars($user['email']),
            'full_name' => htmlspecialchars($user['full_name']),
            'role' => $user['role'],
            'phone' => htmlspecialchars($user['phone'] ?? ''),
            'address' => htmlspecialchars($user['address'] ?? ''),
            'status' => $user['status'],
            'created_at' => $user['created_at']
        ];
    }
    
    // Format filter options
    $filterOptions = [
        'roles' => array_unique(array_column($allRolesUsers, 'role'))
    ];
    
    echo json_encode([
        'success' => true,
        'users' => $formattedUsers,
        'filter_options' => $filterOptions,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_users' => $totalUsers,
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
        'error' => 'Filter failed: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
} catch (Error $e) {
    // Clear any output
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Filter failed: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}

// End output buffering
ob_end_flush();

