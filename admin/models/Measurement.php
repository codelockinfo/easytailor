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
        require_once __DIR__ . '/../../config/config.php';
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
                  WHERE m.id = :customer_id";
        
        $params = [':customer_id' => $customer_id];
        if ($companyId) {
            $query .= " AND m.company_id = :company_id";
            $params[':company_id'] = $companyId;
        }
        
        $query .= " ORDER BY m.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value, ($param === ':company_id' || $param === ':customer_id') ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get measurements with customer and cloth type details
     */
    public function getMeasurementsWithDetails($conditions = [], $limit = null, $offset = 0) {
        $companyId = $this->getCompanyId();
        $query = "SELECT m.*, 
                         c.first_name, c.last_name, c.customer_code, c.phone as customer_phone,
                         ct.name as cloth_type_name, ct.category as cloth_category, ct.measurement_chart_image
                  FROM " . $this->table . " m
                  LEFT JOIN customers c ON m.id = c.id
                  LEFT JOIN cloth_types ct ON m.cloth_type_id = ct.id";
        
        $params = [];
        $where_clauses = [];
        
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
                  LEFT JOIN cloth_types ct ON m.cloth_type_id = ct.id";
        if ($companyId) {
            $query .= " WHERE m.company_id = :company_id";
        }
        $query .= " GROUP BY ct.id, ct.name
                  ORDER BY count DESC";
        
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
        return $this->findOne([
            'customer_id' => $customer_id,
            'cloth_type_id' => $cloth_type_id
        ]);
    }

    /**
     * Get measurements by name (for loading measurements by customer name in order form)
     */
    public function getMeasurementsByName($name) {
        $companyId = $this->getCompanyId();
        $query = "SELECT m.*, ct.name as cloth_type_name
                  FROM " . $this->table . " m
                  LEFT JOIN cloth_types ct ON m.cloth_type_id = ct.id
                  WHERE m.name = :name";
        if ($companyId) {
            $query .= " AND m.company_id = :company_id";
        }
        $query .= " ORDER BY m.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get recent measurements
     */
    public function getRecentMeasurements($limit = 10) {
        return $this->getMeasurementsWithDetails([], $limit);
    }

    /**
     * Search measurements
     */
    public function searchMeasurements($search_term, $limit = 20) {
        $companyId = $this->getCompanyId();
        $query = "SELECT m.*, 
                         c.first_name, c.last_name, c.customer_code,
                         ct.name as cloth_type_name
                  FROM " . $this->table . " m
                  LEFT JOIN customers c ON m.id = c.id
                  LEFT JOIN cloth_types ct ON m.cloth_type_id = ct.id
                  WHERE c.first_name LIKE :search";
        if ($companyId) {
            $query .= " AND m.company_id = :company_id";
        }
        $query .= " ORDER BY m.created_at DESC
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $search_pattern = '%' . $search_term . '%';
        $stmt->bindValue(':search', $search_pattern);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
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

    /**
     * Group an array of raw measurement rows by customer
     * Combines all cloth types / garments belonging to the same customer into a single record.
     */
    public static function groupByCustomer($measurements) {
        $grouped = [];
        foreach ($measurements as $m) {
            $cleanName = !empty($m['name']) ? trim($m['name']) : trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''));
            if (empty($cleanName)) $cleanName = 'Walk-in Customer';
            $phone = trim($m['phone_number'] ?? ($m['customer_phone'] ?? ''));
            
            // Unique key for customer: lowercase name + phone
            $key = strtolower($cleanName);
            if (!empty($phone)) {
                $key .= '_' . $phone;
            }
            
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'primary_id' => (int)$m['id'],
                    'ids' => [],
                    'customer_id' => $m['customer_id'] ?? null,
                    'name' => $cleanName,
                    'customer_code' => $m['customer_code'] ?? '',
                    'phone_number' => $phone,
                    'email' => $m['email'] ?? '',
                    'address' => $m['address'] ?? '',
                    'date_of_birth' => $m['date_of_birth'] ?? '',
                    'notes' => $m['notes'] ?? '',
                    'created_at' => $m['created_at'],
                    'cloth_types' => [],
                    'garments' => []
                ];
            }
            
            $mId = (int)$m['id'];
            $grouped[$key]['ids'][] = $mId;
            
            $ctName = !empty($m['cloth_type_name']) ? $m['cloth_type_name'] : 'Custom';
            if (!in_array($ctName, $grouped[$key]['cloth_types'])) {
                $grouped[$key]['cloth_types'][] = $ctName;
            }
            
            $measData = $m['measurement_data'] ?? [];
            if (is_string($measData)) {
                $decoded = json_decode($measData, true);
                if (is_array($decoded)) $measData = $decoded;
            }
            
            $chartImg = '';
            if (!empty($m['measurement_chart_image'])) {
                $chartImg = '../' . ltrim($m['measurement_chart_image'], './');
            } elseif (!empty($m['measurement_chart'])) {
                $chartImg = $m['measurement_chart'];
            }
            
            $grouped[$key]['garments'][] = [
                'measurement_id' => $mId,
                'cloth_type_id' => (int)($m['cloth_type_id'] ?? 0),
                'cloth_type_name' => $ctName,
                'cloth_category' => $m['cloth_category'] ?? '',
                'measurement_data' => $measData,
                'measurement_chart' => $chartImg,
                'notes' => $m['notes'] ?? ''
            ];
        }
        return array_values($grouped);
    }
}

if (!function_exists('groupMeasurementsByCustomer')) {
    function groupMeasurementsByCustomer($measurements) {
        return Measurement::groupByCustomer($measurements);
    }
}
?>

