<?php
/**
 * Order Model
 * Tailoring Management System
 */

class Order extends BaseModel {
    protected $table = 'orders';

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
     * Create new order with auto-generated order number
     */
    public function createOrder($data) {
        // Ensure company_id is set
        if (!isset($data['company_id'])) {
            $data['company_id'] = $this->getCompanyId();
        }
        
        $maxRetries = 20;
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            try {
                // Generate order number
                $data['order_number'] = $this->generateOrderNumber();
                
                // Attempt to create the order
                $result = $this->create($data);
                
                if ($result !== false) {
                    return $result;
                }
                
                // If create returned false, try again with new number
                $attempt++;
                
            } catch (PDOException $e) {
                // Check if it's a duplicate key error
                if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $attempt++;
                    if ($attempt >= $maxRetries) {
                        // Use guaranteed unique fallback number
                        $data['order_number'] = $this->generateGuaranteedUniqueOrderNumber($data['company_id']);
                        try {
                            $result = $this->create($data);
                            if ($result !== false) {
                                return $result;
                            }
                        } catch (PDOException $e2) {
                            // Even fallback failed, throw user-friendly error
                            throw new Exception('Unable to create order. Please try again or contact support.');
                        }
                    }
                    // Wait a bit before retrying (helps with race conditions)
                    usleep(100000); // 100ms delay
                    continue;
                } else {
                    // If it's a different error, re-throw it
                    throw $e;
                }
            }
        }
        
        throw new Exception('Unable to create order after multiple attempts. Please try again.');
    }

    /**
     * Generate unique order number
     */
    private function generateOrderNumber() {
        $companyId = $this->getCompanyId();
        $prefix = 'ORD';
        $query = "SELECT order_number FROM " . $this->table . " WHERE order_number LIKE :pattern";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        $query .= " ORDER BY order_number DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $pattern = $prefix . '%';
        $stmt->bindParam(':pattern', $pattern);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        $last_order = $stmt->fetch();
        
        if ($last_order) {
            $last_number = (int) substr($last_order['order_number'], 3);
            $new_number = $last_number + 1;
        } else {
            $new_number = 1;
        }
        
        return $prefix . str_pad($new_number, 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Generate guaranteed unique order number using timestamp
     * Used as fallback when normal generation fails due to race conditions
     */
    private function generateGuaranteedUniqueOrderNumber($companyId = null) {
        $prefix = 'ORD';
        $timestamp = time();
        $random = mt_rand(100, 999);
        return $prefix . str_pad($timestamp . $random, 12, '0', STR_PAD_LEFT);
    }

    /**
     * Get orders with customer and cloth type details
     */
    public function getOrdersWithDetails($conditions = [], $limit = null, $offset = 0) {
        $companyId = $this->getCompanyId();
        $query = "SELECT o.*, 
                         c.first_name, c.last_name, c.customer_code, c.phone as customer_phone, c.email as customer_email,
                         ct.name as cloth_type_name,
                         u.full_name as tailor_name,
                         creator.full_name as created_by_name
                  FROM " . $this->table . " o
                  LEFT JOIN customers c ON o.customer_id = c.id
                  LEFT JOIN cloth_types ct ON o.cloth_type_id = ct.id
                  LEFT JOIN users u ON o.assigned_tailor_id = u.id
                  LEFT JOIN users creator ON o.created_by = creator.id";
        
        $params = [];
        $where_clauses = [];
        
        // Add company_id filter
        if ($companyId) {
            $where_clauses[] = "o.company_id = :company_id";
            $params['company_id'] = $companyId;
        }
        
        if (!empty($conditions)) {
            foreach ($conditions as $column => $value) {
                if (strpos($column, '.') !== false) {
                    // Handle table.column format
                    $where_clauses[] = $column . " = :" . str_replace('.', '_', $column);
                    $params[str_replace('.', '_', $column)] = $value;
                } else {
                    $where_clauses[] = "o." . $column . " = :" . $column;
                    $params[$column] = $value;
                }
            }
        }
        
        if (!empty($where_clauses)) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }
        
        $query .= " ORDER BY o.created_at DESC";
        
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
     * Get order statistics
     */
    public function getOrderStats() {
        $companyId = $this->getCompanyId();
        $stats = [];
        
        // Total orders
        $conditions = [];
        if ($companyId) {
            $conditions['company_id'] = $companyId;
        }
        $stats['total'] = $this->count($conditions);
        
        // Orders by status
        $statuses = ['pending', 'in_progress', 'completed', 'delivered', 'cancelled'];
        foreach ($statuses as $status) {
            $conditions['status'] = $status;
            $stats[$status] = $this->count($conditions);
            unset($conditions['status']);
        }
        
        // Orders this month
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
        
        // Total revenue
        $query = "SELECT SUM(total_amount) as total FROM " . $this->table . " WHERE status IN ('completed', 'delivered')";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        $stmt = $this->conn->prepare($query);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['total_revenue'] = $result['total'] ?? 0;
        
        // Pending revenue
        $query = "SELECT SUM(balance_amount) as total FROM " . $this->table . " WHERE status IN ('pending', 'in_progress', 'completed')";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        $stmt = $this->conn->prepare($query);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['pending_revenue'] = $result['total'] ?? 0;
        
        return $stats;
    }

    /**
     * Get orders by status
     */
    public function getOrdersByStatus($status) {
        return $this->getOrdersWithDetails(['status' => $status]);
    }

    /**
     * Get orders for a specific customer
     */
    public function getCustomerOrders($customer_id) {
        return $this->getOrdersWithDetails(['customer_id' => $customer_id]);
    }

    /**
     * Get orders assigned to a specific tailor
     */
    public function getTailorOrders($tailor_id) {
        return $this->getOrdersWithDetails(['assigned_tailor_id' => $tailor_id]);
    }

    /**
     * Update order status
     */
    public function updateOrderStatus($id, $status) {
        $data = ['status' => $status];
        
        if ($status === 'delivered') {
            $data['delivery_date'] = date('Y-m-d');
        }
        
        return $this->update($id, $data);
    }

    /**
     * Get overdue orders
     */
    public function getOverdueOrders() {
        $companyId = $this->getCompanyId();
        $today = date('Y-m-d');
        $query = "SELECT o.*, 
                         c.first_name, c.last_name, c.customer_code, c.phone as customer_phone,
                         ct.name as cloth_type_name,
                         u.full_name as tailor_name
                  FROM " . $this->table . " o
                  LEFT JOIN customers c ON o.customer_id = c.id
                  LEFT JOIN cloth_types ct ON o.cloth_type_id = ct.id
                  LEFT JOIN users u ON o.assigned_tailor_id = u.id
                  WHERE o.due_date < :today 
                  AND o.status IN ('pending', 'in_progress')";
        if ($companyId) {
            $query .= " AND o.company_id = :company_id";
        }
        $query .= " ORDER BY o.due_date ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':today', $today);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get orders due today
     */
    public function getOrdersDueToday() {
        $today = date('Y-m-d');
        return $this->getOrdersWithDetails(['due_date' => $today, 'status' => 'pending'], 10);
    }

    /**
     * Get monthly revenue
     */
    public function getMonthlyRevenue($year = null, $month = null) {
        $companyId = $this->getCompanyId();
        if (!$year) $year = date('Y');
        if (!$month) $month = date('m');
        
        $query = "SELECT SUM(total_amount) as total 
                  FROM " . $this->table . " 
                  WHERE YEAR(created_at) = :year 
                  AND MONTH(created_at) = :month 
                  AND status IN ('completed', 'delivered')";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt->bindParam(':month', $month, PDO::PARAM_INT);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
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
        // Verify the record belongs to this company (find() already filters by company_id)
        if ($companyId) {
            $existing = $this->find($id);
            if (!$existing) {
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

