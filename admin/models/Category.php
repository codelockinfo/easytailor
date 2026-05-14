<?php
/**
 * Category Model
 * Tailoring Management System
 */

class Category extends BaseModel {
    protected $table = 'categories';

    private $defaultCategories = [
        'Formal Suits',
        'Blazers',
        'Shirts',
        'Pent',
        'Salwar Suits',
        'Kurtis',
        'Gowns',
        'Dresses',
        'School Uniforms',
        'Party Wear'
    ];

    /**
     * Get company ID from session
     */
    private function getCompanyId() {
        require_once __DIR__ . '/../../config/config.php';
        return get_company_id();
    }

    /**
     * Seed default categories for a company
     */
    public function seedDefaults($companyId) {
        foreach ($this->defaultCategories as $name) {
            $this->create([
                'company_id' => $companyId,
                'name' => $name,
                'description' => '',
                'status' => 'active'
            ]);
        }
    }

    /**
     * Get categories with stats
     */
    public function getCategoriesWithStats() {
        $companyId = $this->getCompanyId();
        
        // Auto-seed if company has no categories
        $checkQuery = "SELECT COUNT(*) FROM " . $this->table . " WHERE company_id = :company_id";
        $checkStmt = $this->conn->prepare($checkQuery);
        $checkStmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        $checkStmt->execute();
        if ($checkStmt->fetchColumn() == 0) {
            $this->seedDefaults($companyId);
        }

        $query = "SELECT c.*, 
                  (SELECT COUNT(*) FROM expenses e WHERE e.category = c.name AND e.company_id = c.company_id) as expense_count,
                  (SELECT SUM(amount) FROM expenses e WHERE e.category = c.name AND e.company_id = c.company_id) as total_amount
                  FROM " . $this->table . " c
                  WHERE c.company_id = :company_id
                  ORDER BY c.name ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get category statistics
     */
    public function getCategoryStats() {
        $companyId = $this->getCompanyId();
        $stats = [];
        
        // Total categories
        $query = "SELECT COUNT(*) as total FROM " . $this->table . " WHERE company_id = :company_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->execute();
        $stats['total'] = $stmt->fetch()['total'];
        
        // Active categories
        $query = "SELECT COUNT(*) as active FROM " . $this->table . " WHERE status = 'active' AND company_id = :company_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->execute();
        $stats['active'] = $stmt->fetch()['active'];
        
        // Total expenses in these categories
        $query = "SELECT COUNT(*) as expense_count, SUM(amount) as total_amount 
                  FROM expenses e 
                  JOIN " . $this->table . " c ON e.category = c.name AND e.company_id = c.company_id
                  WHERE c.company_id = :company_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['expense_count'] = $result['expense_count'] ?? 0;
        $stats['total_amount'] = $result['total_amount'] ?? 0;
        
        return $stats;
    }

    /**
     * Check if name exists for the company
     */
    public function nameExists($name, $excludeId = null) {
        $companyId = $this->getCompanyId();
        $query = "SELECT id FROM " . $this->table . " WHERE name = :name AND company_id = :company_id";
        if ($excludeId) {
            $query .= " AND id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        if ($excludeId) {
            $stmt->bindParam(':exclude_id', $excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return $stmt->fetch() ? true : false;
    }

    /**
     * Override create to ensure company_id is set
     */
    public function create($data) {
        if (!isset($data['company_id'])) {
            $data['company_id'] = $this->getCompanyId();
        }
        return parent::create($data);
    }
}
?>
