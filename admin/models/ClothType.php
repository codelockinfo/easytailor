<?php
/**
 * Cloth Type Model
 * Tailoring Management System
 */

class ClothType extends BaseModel {
    protected $table = 'cloth_types';

    /**
     * Get company ID from session
     */
    private function getCompanyId() {
        require_once __DIR__ . '/../../config/config.php';
        return get_company_id();
    }

    /**
     * Get active cloth types
     */
    public function getActiveClothTypes() {
        $companyId = $this->getCompanyId();
        $conditions = ['status' => 'active'];
        if ($companyId) {
            $conditions['company_id'] = $companyId;
        }
        return $this->findAll($conditions, 'name ASC');
    }

    /**
     * Get cloth types by category
     */
    public function getClothTypesByCategory($category) {
        $companyId = $this->getCompanyId();
        $conditions = ['category' => $category, 'status' => 'active'];
        if ($companyId) {
            $conditions['company_id'] = $companyId;
        }
        return $this->findAll($conditions, 'name ASC');
    }

    /**
     * Get cloth type statistics
     */
    public function getClothTypeStats() {
        $companyId = $this->getCompanyId();
        $stats = [];
        
        // Total cloth types
        $conditions = [];
        if ($companyId) {
            $conditions['company_id'] = $companyId;
        }
        $stats['total'] = $this->count($conditions);
        
        // Active cloth types
        $conditions['status'] = 'active';
        $stats['active'] = $this->count($conditions);
        
        // Get categories
        $query = "SELECT DISTINCT category FROM " . $this->table . " WHERE category IS NOT NULL AND status = 'active'";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        $query .= " ORDER BY category";
        $stmt = $this->conn->prepare($query);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $stats['categories'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return $stats;
    }

    /**
     * Check if cloth type name exists
     */
    public function nameExists($name, $exclude_id = null) {
        $companyId = $this->getCompanyId();
        $query = "SELECT id FROM " . $this->table . " WHERE name = :name";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        if ($exclude_id) {
            $query .= " AND id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        if ($exclude_id) {
            $stmt->bindParam(':exclude_id', $exclude_id, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return $stmt->fetch() ? true : false;
    }

    /**
     * Get cloth types with order count
     */
    public function getClothTypesWithOrderCount() {
        $companyId = $this->getCompanyId();
        $query = "SELECT ct.*, COUNT(o.id) as order_count 
                  FROM " . $this->table . " ct 
                  LEFT JOIN orders o ON ct.id = o.cloth_type_id";
        
        $where_clauses = ["ct.status = 'active'"];
        $params = [];
        
        if ($companyId) {
            $where_clauses[] = "ct.company_id = :cloth_company_id";
            $params['cloth_company_id'] = $companyId;
            // Also filter orders by company_id if they exist
            $where_clauses[] = "(o.company_id = :order_company_id OR o.company_id IS NULL)";
            $params['order_company_id'] = $companyId;
        }
        
        $query .= " WHERE " . implode(" AND ", $where_clauses);
        $query .= " GROUP BY ct.id 
                  ORDER BY ct.name";
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $param => $value) {
            $stmt->bindValue(':' . $param, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Override create to include company_id
     */
    public function create($data) {
        if (!isset($data['company_id'])) {
            $data['company_id'] = $this->getCompanyId();
        }
        return parent::create($data);
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
}
?>

