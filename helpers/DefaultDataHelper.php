<?php
/**
 * Default Data Helper
 * Tailoring Management System
 * Automatically populates default records (like 11 Cloth Types with Farmas) for new companies.
 */

class DefaultDataHelper {
    /**
     * Create 11 default cloth types with measurement chart images (farmas) for a company
     * Ensures exactly 11 default cloth types exist without creating duplicates.
     *
     * @param int $companyId
     * @return bool
     */
    public static function createDefaultClothTypes($companyId) {
        if (!$companyId) {
            return false;
        }

        require_once __DIR__ . '/../config/database.php';
        $db = new Database();
        $conn = $db->getConnection();

        // 11 Default Cloth Types with Farmas (Measurement Chart Images)
        $defaultClothTypes = [
            [
                'name' => 'Shirt',
                'description' => 'Men\'s formal and casual shirts',
                'standard_rate' => 350.00,
                'category' => 'Men\'s Wear',
                'measurement_chart_image' => 'uploads/measurement-charts/shirt_guide.jpg',
                'status' => 'active'
            ],
            [
                'name' => 'Pants',
                'description' => 'Men\'s formal and casual pants',
                'standard_rate' => 450.00,
                'category' => 'Men\'s Wear',
                'measurement_chart_image' => 'uploads/measurement-charts/pants_guide.jpg',
                'status' => 'active'
            ],
            [
                'name' => 'Suit',
                'description' => 'Men\'s formal two-piece suit or blazer',
                'standard_rate' => 2500.00,
                'category' => 'Men\'s Wear',
                'measurement_chart_image' => 'uploads/measurement-charts/suit_guide.png',
                'status' => 'active'
            ],
            [
                'name' => 'Kurta',
                'description' => 'Traditional men\'s kurta shirt',
                'standard_rate' => 400.00,
                'category' => 'Men\'s Wear',
                'measurement_chart_image' => 'uploads/measurement-charts/kurta_guide.png',
                'status' => 'active'
            ],
            [
                'name' => 'Kurta Pajama',
                'description' => 'Traditional men\'s kurta and pajama suit',
                'standard_rate' => 600.00,
                'category' => 'Men\'s Wear',
                'measurement_chart_image' => 'uploads/measurement-charts/kurta_pajama_guide.png',
                'status' => 'active'
            ],
            [
                'name' => 'Sherwani',
                'description' => 'Men\'s traditional festive sherwani',
                'standard_rate' => 3500.00,
                'category' => 'Men\'s Wear',
                'measurement_chart_image' => 'uploads/measurement-charts/sherwani_guide.png',
                'status' => 'active'
            ],
            [
                'name' => 'Saree',
                'description' => 'Saree fall finishing and pico work',
                'standard_rate' => 150.00,
                'category' => 'Women\'s Wear',
                'measurement_chart_image' => 'uploads/measurement-charts/saree_guide.png',
                'status' => 'active'
            ],
            [
                'name' => 'Designer Blouse',
                'description' => 'Custom stitched designer blouse',
                'standard_rate' => 500.00,
                'category' => 'Women\'s Wear',
                'measurement_chart_image' => 'uploads/measurement-charts/blouse_guide.png',
                'status' => 'active'
            ],
            [
                'name' => 'Salwar Kameez',
                'description' => 'Women\'s traditional salwar suit',
                'standard_rate' => 750.00,
                'category' => 'Women\'s Wear',
                'measurement_chart_image' => 'uploads/measurement-charts/salwar_guide.png',
                'status' => 'active'
            ],
            [
                'name' => 'Lehenga Choli',
                'description' => 'Festive and bridal lehenga choli',
                'standard_rate' => 3000.00,
                'category' => 'Women\'s Wear',
                'measurement_chart_image' => 'uploads/measurement-charts/lehenga_guide.png',
                'status' => 'active'
            ],
            [
                'name' => 'Evening Gown / Dress',
                'description' => 'Western evening gown or one-piece dress',
                'standard_rate' => 1200.00,
                'category' => 'Women\'s Wear',
                'measurement_chart_image' => 'uploads/measurement-charts/dress_guide.png',
                'status' => 'active'
            ]
        ];

        try {
            $conn->beginTransaction();

            // Check existing cloth types for this company
            $stmt = $conn->prepare("SELECT name FROM cloth_types WHERE company_id = :company_id");
            $stmt->execute([':company_id' => $companyId]);
            $existingNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $existingNamesLower = array_map('strtolower', $existingNames);

            $sql = "INSERT INTO cloth_types (company_id, name, description, standard_rate, category, measurement_chart_image, status, created_at) 
                    VALUES (:company_id, :name, :description, :standard_rate, :category, :measurement_chart_image, :status, NOW())";
            $insertStmt = $conn->prepare($sql);

            foreach ($defaultClothTypes as $item) {
                if (!in_array(strtolower($item['name']), $existingNamesLower)) {
                    $insertStmt->execute([
                        ':company_id' => $companyId,
                        ':name' => $item['name'],
                        ':description' => $item['description'],
                        ':standard_rate' => $item['standard_rate'],
                        ':category' => $item['category'],
                        ':measurement_chart_image' => $item['measurement_chart_image'],
                        ':status' => $item['status']
                    ]);
                }
            }

            $conn->commit();
            return true;
        } catch (Exception $e) {
            $conn->rollBack();
            error_log("Error creating default cloth types for company $companyId: " . $e->getMessage());
            return false;
        }
    }
}
?>
