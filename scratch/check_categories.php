<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

$database = new Database();
$db = $database->getConnection();

$query = "SELECT name, company_id, COUNT(*) as count FROM categories GROUP BY name, company_id HAVING count > 1";
$stmt = $db->query($query);
$duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Duplicates within same company:\n";
print_r($duplicates);

$query = "SELECT name, COUNT(DISTINCT company_id) as company_count, COUNT(*) as total_count FROM categories GROUP BY name HAVING total_count > 1";
$stmt = $db->query($query);
$across_companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\nDuplicates across companies (or same name appearing multiple times total):\n";
print_r($across_companies);

$query = "SELECT * FROM categories WHERE status = 'active' ORDER BY name ASC";
$stmt = $db->query($query);
$all_active = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\nTotal active categories: " . count($all_active) . "\n";
foreach ($all_active as $cat) {
    echo "ID: {$cat['id']}, Name: {$cat['name']}, Company ID: {$cat['company_id']}\n";
}
?>
