<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== Orders Table Schema ===\n";
$stmt = $conn->query('DESCRIBE orders');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ' - ' . $row['Type'] . ' - ' . ($row['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
}

echo "\n=== Measurement Names ===\n";
require_once 'models/BaseModel.php';
require_once 'models/Measurement.php';
require_once 'config/config.php';

// Set a dummy company_id for testing
$measurementModel = new Measurement();
$names = $measurementModel->getDistinctCustomerNames();
foreach ($names as $name) {
    echo "Name: " . $name['customer_name'] . "\n";
}
echo "Total: " . count($names) . " distinct names\n";
