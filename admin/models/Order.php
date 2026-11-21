<?php

class Order extends BaseModel {
    protected $table = 'orders';

    public function __construct() {
        parent::__construct();
        $this->ensureColumns();
    }

    private function ensureColumns() {
        if (!$this->conn) {
            return;
        }
        try {
            $colCheck = $this->conn->query("SHOW COLUMNS FROM " . $this->table . " LIKE 'payment_method'");
            if ($colCheck && $colCheck->rowCount() === 0) {
                $this->conn->exec("ALTER TABLE " . $this->table . " ADD COLUMN payment_method VARCHAR(50) NOT NULL DEFAULT 'cash' AFTER balance_amount");
            }
            $phoneCheck = $this->conn->query("SHOW COLUMNS FROM " . $this->table . " LIKE 'customer_phone'");
            if ($phoneCheck && $phoneCheck->rowCount() === 0) {
                $this->conn->exec("ALTER TABLE " . $this->table . " ADD COLUMN customer_phone VARCHAR(25) DEFAULT NULL AFTER special_instructions");
            }
        } catch (\Throwable $e) {
            // Silently ignore if column already exists or table locked
        }
    }

    private function getCompanyId() {
        require_once __DIR__ . '/../../config/config.php';
        return get_company_id();
    }
    public function createOrder($data) {
        if (!isset($data['company_id'])) {
            $data['company_id'] = $this->getCompanyId();
        }
        
        $maxRetries = 20;
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            try {
                $data['order_number'] = $this->generateOrderNumber();
                                $result = $this->create($data);
                
                if ($result !== false) {
                    return $result;
                }
                                $attempt++;
                
            } catch (PDOException $e) {
                if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $attempt++;
                    if ($attempt >= $maxRetries) {
                        $data['order_number'] = $this->generateGuaranteedUniqueOrderNumber($data['company_id']);
                        try {
                            $result = $this->create($data);
                            if ($result !== false) {
                                return $result;
                            }
                        } catch (PDOException $e2) {
                            throw new Exception('Unable to create order. Please try again or contact support.');
                        }
                    }
                    usleep(100000);
                    continue;
                } else {
                    throw $e;
                }
            }
        }
        
        throw new Exception('Unable to create order after multiple attempts. Please try again.');
    }
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
    private function generateGuaranteedUniqueOrderNumber($companyId = null) {
        $prefix = 'ORD';
        $timestamp = time();
        $random = mt_rand(100, 999);
        return $prefix . str_pad($timestamp . $random, 12, '0', STR_PAD_LEFT);
    }
    public function getOrdersWithDetails($conditions = [], $limit = null, $offset = 0) {
        $companyId = $this->getCompanyId();
        $query = "SELECT o.*, 
                         c.first_name, c.last_name, c.customer_code, COALESCE(NULLIF(o.customer_phone, ''), NULLIF(c.phone, ''), NULLIF(ms.phone_number, '')) as customer_phone,
                         ct.name as cloth_type_name,
                         u.full_name as tailor_name,
                         creator.full_name as created_by_name,
                         COALESCE(NULLIF(ms.name, ''), NULLIF(o.customer_name, '')) as measurement_customer_name
                  FROM " . $this->table . " o
                  LEFT JOIN customers c ON o.customer_id = c.id
                  LEFT JOIN cloth_types ct ON o.cloth_type_id = ct.id
                  LEFT JOIN users u ON o.assigned_tailor_id = u.id
                  LEFT JOIN users creator ON o.created_by = creator.id
                  LEFT JOIN measurements ms ON o.measurement_id = ms.id";
        
        $params = [];
        $where_clauses = [];
        
        if ($companyId) {
            $where_clauses[] = "o.company_id = :company_id";
            $params['company_id'] = $companyId;
        }

        if (!empty($conditions['start_date'])) {
            $where_clauses[] = "DATE(o.order_date) >= :start_date";
            $params['start_date'] = $conditions['start_date'];
            unset($conditions['start_date']);
        }
        if (!empty($conditions['end_date'])) {
            $where_clauses[] = "DATE(o.order_date) <= :end_date";
            $params['end_date'] = $conditions['end_date'];
            unset($conditions['end_date']);
        }
        if (!empty($conditions['date_from'])) {
            $where_clauses[] = "DATE(o.order_date) >= :date_from";
            $params['date_from'] = $conditions['date_from'];
            unset($conditions['date_from']);
        }
        if (!empty($conditions['date_to'])) {
            $where_clauses[] = "DATE(o.order_date) <= :date_to";
            $params['date_to'] = $conditions['date_to'];
            unset($conditions['date_to']);
        }

        if (!empty($conditions['customer_id'])) {
            $custId = (int)$conditions['customer_id'];
            require_once dirname(dirname(__DIR__)) . '/models/Customer.php';
            $cModel = new Customer();
            $cData = $cModel->find($custId);
            $fullName = '';
            $phone = '';
            if ($cData) {
                $fullName = trim(($cData['first_name'] ?? '') . ' ' . ($cData['last_name'] ?? ''));
                $phone = !empty($cData['phone']) ? preg_replace('/\D/', '', $cData['phone']) : '';
            }
            $custClauses = ["o.customer_id = :cust_filter_id", "o.measurement_id = :cust_filter_m_id"];
            $params['cust_filter_id'] = $custId;
            $params['cust_filter_m_id'] = $custId;
            if (!empty($fullName)) {
                $custClauses[] = "LOWER(TRIM(o.customer_name)) = :cust_filter_name";
                $custClauses[] = "LOWER(TRIM(ms.name)) = :cust_filter_ms_name";
                $params['cust_filter_name'] = strtolower($fullName);
                $params['cust_filter_ms_name'] = strtolower($fullName);
            }
            if (!empty($phone)) {
                $custClauses[] = "REPLACE(REPLACE(o.customer_phone, '+', ''), ' ', '') = :cust_filter_phone";
                $params['cust_filter_phone'] = $phone;
            }
            $where_clauses[] = "(" . implode(" OR ", $custClauses) . ")";
            unset($conditions['customer_id']);
        }
        
        if (!empty($conditions)) {
            foreach ($conditions as $column => $value) {
                if (strpos($column, '.') !== false) {
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
    public function getOrderStats() {
        $companyId = $this->getCompanyId();
        $stats = [];
        $isTailor = has_role('tailor');
        $tailorId = $isTailor ? get_user_id() : null;
        
        $conditions = [];
        if ($companyId) {
            $conditions['company_id'] = $companyId;
        }
        if ($isTailor) {
            $conditions['assigned_tailor_id'] = $tailorId;
        }
        $stats['total'] = $this->count($conditions);
        
        $statuses = ['pending', 'in_progress', 'completed', 'delivered', 'cancelled'];
        foreach ($statuses as $status) {
            $conditions['status'] = $status;
            $stats[$status] = $this->count($conditions);
            unset($conditions['status']);
        }
        $this_month = date('Y-m-01');
        $query = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE created_at >= :this_month";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        if ($isTailor) {
            $query .= " AND assigned_tailor_id = :tailor_id";
        }
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':this_month', $this_month);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        if ($isTailor) {
            $stmt->bindParam(':tailor_id', $tailorId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['this_month'] = $result['count'];
        
        $query = "SELECT SUM(total_amount) as total FROM " . $this->table . " WHERE status IN ('completed', 'delivered')";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        if ($isTailor) {
            $query .= " AND assigned_tailor_id = :tailor_id";
        }
        $stmt = $this->conn->prepare($query);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        if ($isTailor) {
            $stmt->bindParam(':tailor_id', $tailorId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['total_revenue'] = $result['total'] ?? 0;
        
        $query = "SELECT SUM(balance_amount) as total FROM " . $this->table . " WHERE status IN ('pending', 'in_progress', 'completed')";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        if ($isTailor) {
            $query .= " AND assigned_tailor_id = :tailor_id";
        }
        $stmt = $this->conn->prepare($query);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        if ($isTailor) {
            $stmt->bindParam(':tailor_id', $tailorId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['pending_revenue'] = $result['total'] ?? 0;
        
        return $stats;
    }
    public function getOrdersByStatus($status) {
        return $this->getOrdersWithDetails(['status' => $status]);
    }
    public function getCustomerOrders($customer_id, $customer = null) {
        $companyId = $this->getCompanyId();
        $query = "SELECT o.*, 
                         c.first_name, c.last_name, c.customer_code, c.phone as customer_phone,
                         ct.name as cloth_type_name,
                         u.full_name as tailor_name,
                         creator.full_name as created_by_name,
                         COALESCE(NULLIF(ms.name, ''), NULLIF(o.customer_name, '')) as measurement_customer_name
                  FROM " . $this->table . " o
                  LEFT JOIN customers c ON o.customer_id = c.id
                  LEFT JOIN cloth_types ct ON o.cloth_type_id = ct.id
                  LEFT JOIN users u ON o.assigned_tailor_id = u.id
                  LEFT JOIN users creator ON o.created_by = creator.id
                  LEFT JOIN measurements ms ON o.measurement_id = ms.id
                  WHERE (o.customer_id = :customer_id OR o.measurement_id = :cust_m_id";
        
        $params = [
            ':customer_id' => $customer_id,
            ':cust_m_id' => $customer_id
        ];
        
        if (!empty($customer)) {
            $fullName = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
            if (!empty($fullName)) {
                $query .= " OR LOWER(TRIM(o.customer_name)) = :full_name";
                $params[':full_name'] = strtolower($fullName);
            }
            if (!empty($customer['phone'])) {
                $cleanPhone = preg_replace('/\D/', '', $customer['phone']);
                $query .= " OR REPLACE(REPLACE(c.phone, '+', ''), ' ', '') = :clean_phone";
                $params[':clean_phone'] = $cleanPhone;
            }
        }
        
        $query .= ")";
        
        if ($companyId) {
            $query .= " AND o.company_id = :company_id";
            $params[':company_id'] = $companyId;
        }
        
        $query .= " ORDER BY o.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $param => $val) {
            $stmt->bindValue($param, $val);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }
    public function getTailorOrders($tailor_id) {
        return $this->getOrdersWithDetails(['assigned_tailor_id' => $tailor_id]);
    }
    public function updateOrderStatus($id, $status) {
        $data = ['status' => $status];
        
        if ($status === 'delivered') {
            $data['delivery_date'] = date('Y-m-d');
        }
        
        return $this->update($id, $data);
    }
    public function getOverdueOrders() {
        $companyId = $this->getCompanyId();
        $today = date('Y-m-d');
        $isTailor = has_role('tailor');
        $tailorId = $isTailor ? get_user_id() : null;
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
        if ($isTailor) {
            $query .= " AND o.assigned_tailor_id = :tailor_id";
        }
        $query .= " ORDER BY o.due_date ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':today', $today);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        if ($isTailor) {
            $stmt->bindParam(':tailor_id', $tailorId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    public function getOrdersDueToday() {
        $today = date('Y-m-d');
        return $this->getOrdersWithDetails(['due_date' => $today, 'status' => 'pending'], 10);
    }
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
    public function getClothTypeById($id) {
        $query = "SELECT * FROM cloth_types WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    public function getOrderStatsByDateRange($date_from, $date_to) {
        $companyId = $this->getCompanyId();
        $stats = [];
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
    public function update($id, $data) {
        $companyId = $this->getCompanyId();
        if ($companyId) {
            $existing = $this->getOrderById($id);
            if (!$existing || $existing['company_id'] != $companyId) {
                return false; 
            }
        }
        return parent::update($id, $data);
    }
    public function delete($id) {
        $companyId = $this->getCompanyId();
        if ($companyId) {
            $existing = $this->getOrderById($id);
            if (!$existing || $existing['company_id'] != $companyId) {
                return false; 
            }
        }
        return parent::delete($id);
    }
    public function create($data) {
        if (!isset($data['company_id'])) {
            $data['company_id'] = $this->getCompanyId();
        }
        return parent::create($data);
    }
    public function count($conditions = []) {
        $companyId = $this->getCompanyId();
        if ($companyId && !isset($conditions['company_id'])) {
            $conditions['company_id'] = $companyId;
        }
        return parent::count($conditions);
    }
    public function findAll($conditions = [], $order_by = null, $limit = null) {
        $companyId = $this->getCompanyId();
        if ($companyId && !isset($conditions['company_id'])) {
            $conditions['company_id'] = $companyId;
        }
        return parent::findAll($conditions, $order_by, $limit);
    }
    public function findOne($conditions = []) {
        $companyId = $this->getCompanyId();
        if ($companyId && !isset($conditions['company_id'])) {
            $conditions['company_id'] = $companyId;
        }
        return parent::findOne($conditions);
    }
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