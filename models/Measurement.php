<?php
/**
 * Measurement Model
 * Tailoring Management System
 */

class Measurement extends BaseModel {
    protected $table = 'measurements';

    /**
     * Get company ID from session
     */
    private function getCompanyId() {
        require_once __DIR__ . '/../config/config.php';
        return get_company_id();
    }

    /**
     * Get measurements for a specific customer
     */
    public function getCustomerMeasurements($customer_id) {
        $companyId = $this->getCompanyId();
        $query = "SELECT m.*, ct.name as cloth_type_name
                  FROM " . $this->table . " m
                  LEFT JOIN cloth_types ct ON m.cloth_type_id = ct.id
                  WHERE m.customer_id = :customer_id";
        if ($companyId) {
            $query .= " AND m.company_id = :company_id";
        }
        $query .= " ORDER BY m.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get measurements with customer and cloth type details
     */
    public function getMeasurementsWithDetails($conditions = [], $limit = null, $offset = 0, $search = null) {
        $companyId = $this->getCompanyId();
        $query = "SELECT m.*, 
                         c.first_name, c.last_name, c.customer_code, c.phone as customer_phone,
                         ct.name as cloth_type_name, ct.category as cloth_category
                  FROM " . $this->table . " m
                  LEFT JOIN customers c ON m.customer_id = c.id
                  LEFT JOIN cloth_types ct ON m.cloth_type_id = ct.id";
        
        $params = [];
        $where_clauses = [];
        
        // Add company_id filter
        if ($companyId) {
            $where_clauses[] = "m.company_id = :company_id";
            $params['company_id'] = $companyId;
        }
        
        if (!empty($conditions)) {
            foreach ($conditions as $column => $value) {
                if (strpos($column, '.') !== false) {
                    // Handle table.column format
                    $where_clauses[] = $column . " = :" . str_replace('.', '_', $column);
                    $params[str_replace('.', '_', $column)] = $value;
                } else {
                    $where_clauses[] = "m." . $column . " = :" . $column;
                    $params[$column] = $value;
                }
            }
        }
        
        // Add search filter if provided
        if (!empty($search)) {
            $searchPattern = '%' . $search . '%';
            $where_clauses[] = "(c.first_name LIKE :search_fname 
                                OR c.last_name LIKE :search_lname 
                                OR c.customer_code LIKE :search_code 
                                OR CONCAT(c.first_name, ' ', c.last_name) LIKE :search_fullname
                                OR ct.name LIKE :search_cloth
                                OR m.notes LIKE :search_notes)";
            $params['search_fname'] = $searchPattern;
            $params['search_lname'] = $searchPattern;
            $params['search_code'] = $searchPattern;
            $params['search_fullname'] = $searchPattern;
            $params['search_cloth'] = $searchPattern;
            $params['search_notes'] = $searchPattern;
        }
        
        if (!empty($where_clauses)) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }
        
        $query .= " ORDER BY m.created_at DESC";
        
        if ($limit) {
            $query .= " LIMIT :limit OFFSET :offset";
        }
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $param => $value) {
            $stmt->bindValue(':' . $param, $value);
        }
        
        if ($limit) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Create measurement with JSON data
     */
    public function createMeasurement($data) {
        // Ensure company_id is set
        if (!isset($data['company_id'])) {
            $data['company_id'] = $this->getCompanyId();
        }
        // Ensure measurement_data is JSON
        if (is_array($data['measurement_data'])) {
            $data['measurement_data'] = json_encode($data['measurement_data']);
        }
        
        // Ensure images is JSON
        if (isset($data['images']) && is_array($data['images'])) {
            $data['images'] = json_encode($data['images']);
        }
        
        return $this->create($data);
    }

    /**
     * Update measurement with JSON data
     */
    public function updateMeasurement($id, $data) {
        // Ensure measurement_data is JSON
        if (isset($data['measurement_data']) && is_array($data['measurement_data'])) {
            $data['measurement_data'] = json_encode($data['measurement_data']);
        }
        
        // Ensure images is JSON
        if (isset($data['images']) && is_array($data['images'])) {
            $data['images'] = json_encode($data['images']);
        }
        
        return $this->update($id, $data);
    }

    /**
     * Get measurement statistics
     */
    public function getMeasurementStats() {
        $companyId = $this->getCompanyId();
        $stats = [];
        
        // Total measurements
        $conditions = [];
        if ($companyId) {
            $conditions['company_id'] = $companyId;
        }
        $stats['total'] = $this->count($conditions);
        
        // Measurements this month
        $this_month = date('Y-m-01');
        $query = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE created_at >= :this_month";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':this_month', $this_month);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['this_month'] = $result['count'];
        
        // Measurements by cloth type
        $query = "SELECT ct.name, COUNT(m.id) as count 
                  FROM " . $this->table . " m
                  LEFT JOIN cloth_types ct ON m.cloth_type_id = ct.id
                  WHERE 1=1";
        if ($companyId) {
            $query .= " AND m.company_id = :company_id";
        }
        $query .= " GROUP BY ct.id, ct.name ORDER BY count DESC";
        
        $stmt = $this->conn->prepare($query);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $stats['by_cloth_type'] = $stmt->fetchAll();
        
        return $stats;
    }

    /**
     * Get measurement by customer and cloth type
     */
    public function getMeasurementByCustomerAndClothType($customer_id, $cloth_type_id) {
        $companyId = $this->getCompanyId();
        $conditions = [
            'customer_id' => $customer_id,
            'cloth_type_id' => $cloth_type_id
        ];
        if ($companyId) {
            $conditions['company_id'] = $companyId;
        }
        return $this->findOne($conditions);
    }

    /**
     * Get recent measurements
     */
    public function getRecentMeasurements($limit = 10) {
        return $this->getMeasurementsWithDetails([], $limit);
    }

    /**
     * Search measurements (deprecated - use getMeasurementsWithDetails with search parameter)
     */
    public function searchMeasurements($search_term, $limit = 20) {
        return $this->getMeasurementsWithDetails([], $limit, 0, $search_term);
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
