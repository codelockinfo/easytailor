<?php
/**
 * Contact Model
 * Tailoring Management System
 */

class Contact extends BaseModel {
    protected $table = 'contacts';

    /**
     * Get company ID from session
     */
    private function getCompanyId() {
        require_once __DIR__ . '/../config/config.php';
        return get_company_id();
    }

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
        $companyId = $this->getCompanyId();
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE (name LIKE :search 
                      OR company LIKE :search 
                      OR email LIKE :search 
                      OR phone LIKE :search
                      OR notes LIKE :search)
                  AND status = 'active'";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        $query .= " ORDER BY name LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $search_pattern = '%' . $search_term . '%';
        $stmt->bindParam(':search', $search_pattern);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get contact statistics
     */
    public function getContactStats() {
        $companyId = $this->getCompanyId();
        $stats = [];
        
        // Total contacts
        $conditions = [];
        if ($companyId) {
            $conditions['company_id'] = $companyId;
        }
        $stats['total'] = $this->count($conditions);
        
        // Active contacts
        $conditions['status'] = 'active';
        $stats['active'] = $this->count($conditions);
        
        // Contacts by category
        $query = "SELECT category, COUNT(*) as count 
                  FROM " . $this->table . " 
                  WHERE category IS NOT NULL AND status = 'active'";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        $query .= " GROUP BY category ORDER BY count DESC";
        $stmt = $this->conn->prepare($query);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $stats['by_category'] = $stmt->fetchAll();
        
        return $stats;
    }

    /**
     * Get contact categories
     */
    public function getContactCategories() {
        $companyId = $this->getCompanyId();
        $query = "SELECT DISTINCT category FROM " . $this->table . " WHERE category IS NOT NULL";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        $query .= " ORDER BY category";
        $stmt = $this->conn->prepare($query);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
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

    /**
     * Override find to include company_id filter
     */
    public function find($id) {
        $companyId = $this->getCompanyId();
        $query = "SELECT * FROM " . $this->table . " WHERE " . $this->primary_key . " = :id";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        $query .= " LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return $stmt->fetch();
    }

    /**
     * Override findAll to include company_id filter
     */
    public function findAll($conditions = [], $order_by = null, $limit = null) {
        $companyId = $this->getCompanyId();
        if ($companyId && !isset($conditions['company_id'])) {
            $conditions['company_id'] = $companyId;
        }
        return parent::findAll($conditions, $order_by, $limit);
    }

    /**
     * Override findOne to include company_id filter
     */
    public function findOne($conditions = []) {
        $companyId = $this->getCompanyId();
        if ($companyId && !isset($conditions['company_id'])) {
            $conditions['company_id'] = $companyId;
        }
        return parent::findOne($conditions);
    }

    /**
     * Override count to include company_id filter
     */
    public function count($conditions = []) {
        $companyId = $this->getCompanyId();
        if ($companyId && !isset($conditions['company_id'])) {
            $conditions['company_id'] = $companyId;
        }
        return parent::count($conditions);
    }

    /**
     * Override update to ensure company_id is checked
     */
    public function update($id, $data) {
        $companyId = $this->getCompanyId();
        if ($companyId) {
            $existing = $this->find($id);
            if (!$existing || $existing['company_id'] != $companyId) {
                return false;
            }
        }
        return parent::update($id, $data);
    }

    /**
     * Override delete to ensure company_id is checked
     */
    public function delete($id) {
        $companyId = $this->getCompanyId();
        if ($companyId) {
            $existing = $this->find($id);
            if (!$existing || $existing['company_id'] != $companyId) {
                return false;
            }
        }
        return parent::delete($id);
    }
}
?>
