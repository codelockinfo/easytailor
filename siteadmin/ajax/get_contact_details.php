<?php
/**
 * Get contact details by ID
 */

require_once '../../config/config.php';

// Check if site admin is logged in
if (!isset($_SESSION['site_admin_logged_in']) || $_SESSION['site_admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$contactId = filter_input(INPUT_GET, 'contact_id', FILTER_VALIDATE_INT);
if (!$contactId) {
    echo json_encode(['success' => false, 'message' => 'Contact ID is required']);
    exit;
}

require_once '../../config/database.php';
$database = new Database();
$db = $database->getConnection();

try {
    // Check if contacts table exists
    $stmt = $db->query("SHOW TABLES LIKE 'contacts'");
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Contacts table not found']);
        exit;
    }

    // Get contact details
    $query = "SELECT * FROM contacts WHERE id = :contact_id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':contact_id', $contactId, PDO::PARAM_INT);
    $stmt->execute();
    
    $contact = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$contact) {
        echo json_encode(['success' => false, 'message' => 'Contact not found']);
        exit;
    }

    // Format contact data
    $contactData = [
        'id' => $contact['id'],
        'name' => $contact['name'] ?? 'N/A',
        'company' => $contact['company'] ?? '-',
        'email' => $contact['email'] ?? null,
        'phone' => $contact['phone'] ?? null,
        'category' => $contact['category'] ?? 'General',
        'status' => $contact['status'] ?? 'active',
        'address' => $contact['address'] ?? null,
        'notes' => $contact['notes'] ?? null,
        'created_at' => $contact['created_at'] ?? null,
        'updated_at' => $contact['updated_at'] ?? null
    ];

    echo json_encode([
        'success' => true,
        'contact' => $contactData
    ]);

} catch (Exception $e) {
    error_log("Error fetching contact details: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching contact details: ' . $e->getMessage()
    ]);
}
?>

