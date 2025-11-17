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
        require_once __DIR__ . '/../../config/config.php';
        return get_company_id();
    }

    /**
     * Create new order with auto-generated order number
     */
    public function createOrder($data) {
        // Ensure company_id is set
        if (!isset($data['company_id'])) {
            $data['company_id'] = $this->getCompanyId();
        }
        // Generate order number
        $data['order_number'] = $this->generateOrderNumber();
        return $this->create($data);
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
     * Get orders with customer and cloth type details
     */
    public function getOrdersWithDetails($conditions = [], $limit = null, $offset = 0) {
        $companyId = $this->getCompanyId();
        $query = "SELECT o.*, 
                         c.first_name, c.last_name, c.customer_code, c.phone as customer_phone,
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
     * Get order by ID
     */
    public function getOrderById($id) {
        $companyId = $this->getCompanyId();
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        $query .= " LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    /**
     * Get cloth type by ID
     */
    public function getClothTypeById($id) {
        $query = "SELECT * FROM cloth_types WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch();
    }

    /**
     * Get order statistics by date range
     */
    public function getOrderStatsByDateRange($date_from, $date_to) {
        $companyId = $this->getCompanyId();
        $stats = [];
        
        // Total orders in date range
        $query = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE DATE(created_at) BETWEEN :date_from AND :date_to";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->bindParam(':date_to', $date_to);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['total'] = $result['count'];
        
        // Orders by status in date range
        $statuses = ['pending', 'in_progress', 'completed', 'delivered', 'cancelled'];
        foreach ($statuses as $status) {
            $query = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE status = :status AND DATE(created_at) BETWEEN :date_from AND :date_to";
            if ($companyId) {
                $query .= " AND company_id = :company_id";
            }
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':date_from', $date_from);
            $stmt->bindParam(':date_to', $date_to);
            if ($companyId) {
                $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
            }
            $stmt->execute();
            $result = $stmt->fetch();
            $stats[$status] = $result['count'];
        }
        
        return $stats;
    }

    /**
     * Get monthly revenue by date range
     */
    public function getMonthlyRevenueByDateRange($year, $month, $date_from, $date_to) {
        $companyId = $this->getCompanyId();
        $query = "SELECT SUM(total_amount) as total 
                  FROM " . $this->table . " 
                  WHERE YEAR(created_at) = :year 
                  AND MONTH(created_at) = :month 
                  AND DATE(created_at) BETWEEN :date_from AND :date_to
                  AND status IN ('completed', 'delivered')";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt->bindParam(':month', $month, PDO::PARAM_INT);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->bindParam(':date_to', $date_to);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    }

    /**
     * Get orders with details by date range
     */
    public function getOrdersWithDetailsByDateRange($date_from, $date_to, $limit = null) {
        $companyId = $this->getCompanyId();
        $query = "SELECT o.*, 
                         c.first_name, c.last_name, c.customer_code, c.phone as customer_phone,
                         ct.name as cloth_type_name,
                         u.full_name as tailor_name,
                         creator.full_name as created_by_name
                  FROM " . $this->table . " o
                  LEFT JOIN customers c ON o.customer_id = c.id
                  LEFT JOIN cloth_types ct ON o.cloth_type_id = ct.id
                  LEFT JOIN users u ON o.assigned_tailor_id = u.id
                  LEFT JOIN users creator ON o.created_by = creator.id
                  WHERE DATE(o.created_at) BETWEEN :date_from AND :date_to";
        if ($companyId) {
            $query .= " AND o.company_id = :company_id";
        }
        $query .= " ORDER BY o.created_at DESC";
        
        if ($limit) {
            $query .= " LIMIT :limit";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->bindParam(':date_to', $date_to);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        
        if ($limit) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Override update to ensure company_id is checked
     */
    public function update($id, $data) {
        $companyId = $this->getCompanyId();
        // First verify the record belongs to this company
        if ($companyId) {
            $existing = $this->getOrderById($id);
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
            $existing = $this->getOrderById($id);
            if (!$existing || $existing['company_id'] != $companyId) {
                return false; // Record doesn't exist or doesn't belong to this company
            }
        }
        return parent::delete($id);
    }
}
?>

