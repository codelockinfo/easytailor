<?php
/**
 * Category Model
 * Tailoring Management System
 */

class Category extends BaseModel {
    protected $table = 'categories';

    /**
     * Get company ID from session
     */
    private function getCompanyId() {
        require_once __DIR__ . '/../../config/config.php';
        return get_company_id();
    }

    /**
     * Get categories with stats
     */
    public function getCategoriesWithStats() {
        $query = "SELECT c.*, 
                  (SELECT COUNT(*) FROM expenses e WHERE e.category = c.name) as expense_count,
                  (SELECT SUM(amount) FROM expenses e WHERE e.category = c.name) as total_amount
                  FROM " . $this->table . " c";
        
        $query .= " ORDER BY c.name ASC";
        
        $stmt = $this->conn->prepare($query);
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
        $query = "SELECT COUNT(*) as total FROM " . $this->table;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['total'] = $stmt->fetch()['total'];
        
        // Active categories
        $query = "SELECT COUNT(*) as active FROM " . $this->table . " WHERE status = 'active'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['active'] = $stmt->fetch()['active'];
        
        // Total expenses in these categories
        $query = "SELECT COUNT(*) as expense_count, SUM(amount) as total_amount 
                  FROM expenses e 
                  JOIN " . $this->table . " c ON e.category = c.name";
        $stmt = $this->conn->prepare($query);
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
        $query = "SELECT id FROM " . $this->table . " WHERE name = :name";
        if ($excludeId) {
            $query .= " AND id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
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
