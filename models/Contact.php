<?php
/**
 * Contact Model
 * Tailoring Management System
 */

class Contact extends BaseModel {
    protected $table = 'contacts';

    /**
     * Get active contacts
     */
    public function getActiveContacts() {
        return $this->findAll(['status' => 'active'], 'name ASC');
    }

    /**
     * Get contacts by category
     */
    public function getContactsByCategory($category) {
        return $this->findAll(['category' => $category, 'status' => 'active'], 'name ASC');
    }

    /**
     * Search contacts
     */
    public function searchContacts($search_term, $limit = 20) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE (name LIKE :search 
                      OR company LIKE :search 
                      OR email LIKE :search 
                      OR phone LIKE :search
                      OR notes LIKE :search)
                  AND status = 'active'
                  ORDER BY name
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $search_pattern = '%' . $search_term . '%';
        $stmt->bindParam(':search', $search_pattern);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get contact statistics
     */
    public function getContactStats() {
        $stats = [];
        
        // Total contacts
        $stats['total'] = $this->count();
        
        // Active contacts
        $stats['active'] = $this->count(['status' => 'active']);
        
        // Contacts by category
        $query = "SELECT category, COUNT(*) as count 
                  FROM " . $this->table . " 
                  WHERE category IS NOT NULL AND status = 'active'
                  GROUP BY category 
                  ORDER BY count DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['by_category'] = $stmt->fetchAll();
        
        return $stats;
    }

    /**
     * Get contact categories
     */
    public function getContactCategories() {
        $query = "SELECT DISTINCT category FROM " . $this->table . " WHERE category IS NOT NULL ORDER BY category";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
?>

