<?php
/**
 * Customer Model
 * Tailoring Management System
 */

class Customer extends BaseModel {
    protected $table = 'customers';

    /**
     * Get company ID from session
     */
    private function getCompanyId() {
        require_once __DIR__ . '/../config/config.php';
        return get_company_id();
    }

    /**
     * Check if current user is admin
     */
    private function isAdmin() {
        require_once __DIR__ . '/../config/config.php';
        return get_user_role() === 'admin';
    }

    /**
     * Create new customer with auto-generated customer code
     */
    public function createCustomer($data) {
        // Ensure company_id is set
        if (!isset($data['company_id'])) {
            $data['company_id'] = $this->getCompanyId();
        }
        // Generate customer code
        $data['customer_code'] = $this->generateCustomerCode();
        return $this->create($data);
    }

    /**
     * Generate unique customer code
     */
    private function generateCustomerCode() {
        $companyId = $this->getCompanyId();
        $prefix = 'CUST';
        $query = "SELECT customer_code FROM " . $this->table . " WHERE customer_code LIKE :pattern";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        $query .= " ORDER BY customer_code DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $pattern = $prefix . '%';
        $stmt->bindParam(':pattern', $pattern);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        $last_customer = $stmt->fetch();
        
        if ($last_customer) {
            $last_number = (int) substr($last_customer['customer_code'], 4);
            $new_number = $last_number + 1;
        } else {
            $new_number = 1;
        }
        
        return $prefix . str_pad($new_number, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Search customers - handles both single word and full name searches
     */
    public function searchCustomers($search_term, $limit = 20) {
        $companyId = $this->getCompanyId();
        
        // Trim search term and handle URL encoding
        $search_term = trim($search_term);
        // Replace %20 with space if still present (shouldn't be, but just in case)
        $search_term = str_replace('%20', ' ', $search_term);
        $search_term = trim($search_term);
        $search_lower = strtolower($search_term);
        
        // Debug logging
        error_log("Customer search - Search term received: " . var_export($search_term, true));
        error_log("Customer search - Search term length: " . strlen($search_term));
        error_log("Customer search - Search term bytes: " . bin2hex($search_term));
        
        // Check if search term contains a space (full name search)
        $has_space = strpos($search_term, ' ') !== false;
        error_log("Customer search - Has space: " . ($has_space ? 'yes' : 'no'));
        
        // Build WHERE conditions
        $where_conditions = [];
        $params = [];
        
        if ($has_space) {
            // Full name search - split into parts
            $name_parts = preg_split('/\s+/', $search_term);
            $first_part = trim($name_parts[0]);
            $last_part = count($name_parts) > 1 ? trim(end($name_parts)) : '';
            
            error_log("Customer search - Name parts: " . json_encode($name_parts));
            error_log("Customer search - First part: '$first_part', Last part: '$last_part'");
            
            if (!empty($first_part) && !empty($last_part)) {
                // Match: first part in first_name AND last part in last_name
                $where_conditions[] = "(LOWER(COALESCE(first_name, '')) LIKE :fn1 AND LOWER(COALESCE(last_name, '')) LIKE :ln1)";
                $params[':fn1'] = '%' . strtolower($first_part) . '%';
                $params[':ln1'] = '%' . strtolower($last_part) . '%';
                
                // Match: last part in first_name AND first part in last_name (reverse)
                $where_conditions[] = "(LOWER(COALESCE(first_name, '')) LIKE :fn2 AND LOWER(COALESCE(last_name, '')) LIKE :ln2)";
                $params[':fn2'] = '%' . strtolower($last_part) . '%';
                $params[':ln2'] = '%' . strtolower($first_part) . '%';
                
                // Match: full concatenated name
                $where_conditions[] = "LOWER(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))) LIKE :full_name";
                $params[':full_name'] = '%' . $search_lower . '%';
            }
        } else {
            // Single word search - search in individual fields
            $where_conditions[] = "LOWER(COALESCE(first_name, '')) LIKE :search1";
            $where_conditions[] = "LOWER(COALESCE(last_name, '')) LIKE :search2";
            $params[':search1'] = '%' . $search_lower . '%';
            $params[':search2'] = '%' . $search_lower . '%';
        }
        
        // Always search in these fields
        $where_conditions[] = "COALESCE(customer_code, '') LIKE :code";
        $where_conditions[] = "LOWER(COALESCE(email, '')) LIKE :email";
        $where_conditions[] = "COALESCE(phone, '') LIKE :phone";
        $params[':code'] = '%' . $search_term . '%';
        $params[':email'] = '%' . $search_lower . '%';
        $params[':phone'] = '%' . $search_term . '%';
        
        // Build query
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE (" . implode(' OR ', $where_conditions) . ")
                  AND status = 'active'";
        
        if ($companyId) {
            $query .= " AND company_id = :company_id";
            $params[':company_id'] = $companyId;
        }
        
        $query .= " ORDER BY first_name, last_name LIMIT :limit";
        $params[':limit'] = $limit;
        
        // Log the query and parameters
        error_log("Customer search - SQL Query: " . $query);
        error_log("Customer search - Parameters: " . json_encode($params));
        
        $stmt = $this->conn->prepare($query);
        
        // Bind all parameters
        foreach ($params as $key => $value) {
            if ($key === ':limit' || $key === ':company_id') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        
        try {
            $stmt->execute();
            $results = $stmt->fetchAll();
            
            error_log("Customer search - Results count: " . count($results));
            if (count($results) > 0) {
                error_log("Customer search - First result name: " . ($results[0]['first_name'] ?? '') . ' ' . ($results[0]['last_name'] ?? ''));
            } else {
                error_log("Customer search - No results found. Query executed successfully but no matches.");
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log("Customer search - PDO Exception: " . $e->getMessage());
            error_log("Customer search - Query: " . $query);
            error_log("Customer search - Params: " . json_encode($params));
            return [];
        }
    }

    /**
     * Get customers with order count
     */
    public function getCustomersWithOrderCount($limit = null) {
        $companyId = $this->getCompanyId();
        $query = "SELECT c.*, COUNT(o.id) as order_count 
                  FROM " . $this->table . " c 
                  LEFT JOIN orders o ON c.id = o.customer_id";
        
        $where_clauses = ["c.status = 'active'"];
        $params = [];
        
        if ($companyId) {
            $where_clauses[] = "c.company_id = :customer_company_id";
            $params['customer_company_id'] = $companyId;
            // Also filter orders by company_id if they exist
            $where_clauses[] = "(o.company_id = :order_company_id OR o.company_id IS NULL)";
            $params['order_company_id'] = $companyId;
        }
        
        $query .= " WHERE " . implode(" AND ", $where_clauses);
        $query .= " GROUP BY c.id ORDER BY c.first_name, c.last_name";
        
        if ($limit) {
            $query .= " LIMIT " . (int)$limit;
        }
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $param => $value) {
            $stmt->bindValue(':' . $param, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get customer statistics
     */
    public function getCustomerStats() {
        $companyId = $this->getCompanyId();
        $stats = [];
        
        // Total customers
        $conditions = [];
        if ($companyId) {
            $conditions['company_id'] = $companyId;
        }
        $stats['total'] = $this->count($conditions);
        
        // Active customers
        $conditions['status'] = 'active';
        $stats['active'] = $this->count($conditions);
        
        // New customers this month
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
        
        return $stats;
    }

    /**
     * Get customer by customer code
     */
    public function findByCustomerCode($customer_code) {
        $companyId = $this->getCompanyId();
        $conditions = ['customer_code' => $customer_code];
        if ($companyId) {
            $conditions['company_id'] = $companyId;
        }
        return $this->findOne($conditions);
    }

    /**
     * Check if email exists for customer
     */
    public function emailExists($email, $exclude_id = null) {
        $companyId = $this->getCompanyId();
        $query = "SELECT id FROM " . $this->table . " WHERE email = :email";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        if ($exclude_id) {
            $query .= " AND id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
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
     * Get customer's recent orders
     */
    public function getCustomerOrders($customer_id, $limit = 10) {
        $companyId = $this->getCompanyId();
        $query = "SELECT o.*, ct.name as cloth_type_name, u.full_name as tailor_name
                  FROM orders o
                  LEFT JOIN cloth_types ct ON o.cloth_type_id = ct.id
                  LEFT JOIN users u ON o.assigned_tailor_id = u.id
                  WHERE o.customer_id = :customer_id";
        if ($companyId) {
            $query .= " AND o.company_id = :company_id";
        }
        $query .= " ORDER BY o.created_at DESC LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Override find to include company_id filter for non-admin users
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
     * Override findAll to include company_id filter for non-admin users
     */
    public function findAll($conditions = [], $order_by = null, $limit = null) {
        $companyId = $this->getCompanyId();
        if ($companyId && !isset($conditions['company_id'])) {
            $conditions['company_id'] = $companyId;
        }
        return parent::findAll($conditions, $order_by, $limit);
    }

    /**
     * Override findOne to include company_id filter for non-admin users
     */
    public function findOne($conditions = []) {
        $companyId = $this->getCompanyId();
        if ($companyId && !isset($conditions['company_id'])) {
            $conditions['company_id'] = $companyId;
        }
        return parent::findOne($conditions);
    }

    /**
     * Override count to include company_id filter for non-admin users
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
        // First verify the record belongs to this company
        if ($companyId) {
            $existing = $this->find($id);
            if (!$existing || $existing['company_id'] != $companyId) {
                return false; // Record doesn't exist or doesn't belong to this company
            }
        }
        return parent::update($id, $data);
    }

    /**
     * Override delete to ensure company_id is checked
     */
    public function delete($id) {
        $companyId = $this->getCompanyId();
        // First verify the record belongs to this company
        if ($companyId) {
            $existing = $this->find($id);
            if (!$existing || $existing['company_id'] != $companyId) {
                return false; // Record doesn't exist or doesn't belong to this company
            }
        }
        return parent::delete($id);
    }
}
?>
