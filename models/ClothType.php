<?php
/**
 * Cloth Type Model
 * Tailoring Management System
 */

class ClothType extends BaseModel {
    protected $table = 'cloth_types';

    /**
     * Get active cloth types
     */
    public function getActiveClothTypes() {
        return $this->findAll(['status' => 'active'], 'name ASC');
    }

    /**
     * Get cloth types by category
     */
    public function getClothTypesByCategory($category) {
        return $this->findAll(['category' => $category, 'status' => 'active'], 'name ASC');
    }

    /**
     * Get cloth type statistics
     */
    public function getClothTypeStats() {
        $stats = [];
        
        // Total cloth types
        $stats['total'] = $this->count();
        
        // Active cloth types
        $stats['active'] = $this->count(['status' => 'active']);
        
        // Get categories
        $query = "SELECT DISTINCT category FROM " . $this->table . " WHERE category IS NOT NULL AND status = 'active' ORDER BY category";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['categories'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return $stats;
    }

    /**
     * Check if cloth type name exists
     */
    public function nameExists($name, $exclude_id = null) {
        $query = "SELECT id FROM " . $this->table . " WHERE name = :name";
        if ($exclude_id) {
            $query .= " AND id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        if ($exclude_id) {
            $stmt->bindParam(':exclude_id', $exclude_id);
        }
        $stmt->execute();
        
        return $stmt->fetch() ? true : false;
    }

    /**
     * Get cloth types with order count
     */
    public function getClothTypesWithOrderCount() {
        $query = "SELECT ct.*, COUNT(o.id) as order_count 
                  FROM " . $this->table . " ct 
                  LEFT JOIN orders o ON ct.id = o.cloth_type_id 
                  WHERE ct.status = 'active'
                  GROUP BY ct.id 
                  ORDER BY ct.name";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}
?>

