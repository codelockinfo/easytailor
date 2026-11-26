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
            // Auto-ensure order_items table exists
            $this->conn->exec("
                CREATE TABLE IF NOT EXISTS order_items (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_id INT NOT NULL,
                    cloth_type_id INT NOT NULL,
                    measurement_id INT NULL DEFAULT NULL,
                    assigned_tailor_id INT NULL DEFAULT NULL,
                    quantity INT DEFAULT 1,
                    price DECIMAL(10,2) DEFAULT 0.00,
                    status VARCHAR(50) NOT NULL DEFAULT 'pending',
                    notes TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_order_id (order_id),
                    INDEX idx_cloth_type_id (cloth_type_id),
                    INDEX idx_measurement_id (measurement_id),
                    INDEX idx_assigned_tailor_id (assigned_tailor_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Auto-ensure order_items columns exist
            $itemTableCheck = $this->conn->query("SHOW TABLES LIKE 'order_items'");
            if ($itemTableCheck && $itemTableCheck->rowCount() > 0) {
                $tailorCol = $this->conn->query("SHOW COLUMNS FROM order_items LIKE 'assigned_tailor_id'");
                if ($tailorCol && $tailorCol->rowCount() === 0) {
                    $this->conn->exec("ALTER TABLE order_items ADD COLUMN assigned_tailor_id INT NULL DEFAULT NULL AFTER measurement_id");
                }
                $statusCol = $this->conn->query("SHOW COLUMNS FROM order_items LIKE 'status'");
                if ($statusCol && $statusCol->rowCount() === 0) {
                    $this->conn->exec("ALTER TABLE order_items ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'pending' AFTER price");
                }
                $notesCol = $this->conn->query("SHOW COLUMNS FROM order_items LIKE 'notes'");
                if ($notesCol && $notesCol->rowCount() === 0) {
                    $this->conn->exec("ALTER TABLE order_items ADD COLUMN notes TEXT NULL AFTER status");
                }
            }
        } catch (\Throwable $e) {
            // Silently ignore if column already exists or table locked
        }
    }

    private function getCompanyId() {
        require_once __DIR__ . '/../config/config.php';
        return get_company_id();
    }
    private function isAdmin() {
        require_once __DIR__ . '/../config/config.php';
        return get_user_role() === 'admin';
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
        $isTailor = has_role('tailor');
        $tailorId = $isTailor ? (int)get_user_id() : (!empty($conditions['assigned_tailor_id']) ? (int)$conditions['assigned_tailor_id'] : null);

        $params = [];
        $where_clauses = [];

        $itemCountSub = "(SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id" . ($isTailor ? " AND oi.assigned_tailor_id = :tailor_ctx_1" : "") . ") as item_count";
        $clothNamesSub = "(SELECT GROUP_CONCAT(DISTINCT ct2.name SEPARATOR ', ') FROM order_items oi JOIN cloth_types ct2 ON oi.cloth_type_id = ct2.id WHERE oi.order_id = o.id" . ($isTailor ? " AND oi.assigned_tailor_id = :tailor_ctx_2" : "") . ") as all_cloth_type_names";
        $tailorNamesSub = "(SELECT GROUP_CONCAT(DISTINCT u2.full_name SEPARATOR ', ') FROM order_items oi JOIN users u2 ON oi.assigned_tailor_id = u2.id WHERE oi.order_id = o.id" . ($isTailor ? " AND oi.assigned_tailor_id = :tailor_ctx_3" : "") . ") as all_tailor_names";

        if ($isTailor) {
            $params['tailor_ctx_1'] = $tailorId;
            $params['tailor_ctx_2'] = $tailorId;
            $params['tailor_ctx_3'] = $tailorId;
        }

        $query = "SELECT o.*, 
                         c.first_name, c.last_name, c.customer_code, COALESCE(NULLIF(o.customer_phone, ''), NULLIF(c.phone, ''), NULLIF(ms.phone_number, '')) as customer_phone, c.email as customer_email,
                         ct.name as cloth_type_name,
                         u.full_name as tailor_name,
                         creator.full_name as created_by_name,
                         COALESCE(NULLIF(ms.name, ''), NULLIF(o.customer_name, '')) as measurement_customer_name,
                         $itemCountSub,
                         $clothNamesSub,
                         $tailorNamesSub
                  FROM " . $this->table . " o
                  LEFT JOIN customers c ON o.customer_id = c.id
                  LEFT JOIN cloth_types ct ON o.cloth_type_id = ct.id
                  LEFT JOIN users u ON o.assigned_tailor_id = u.id
                  LEFT JOIN users creator ON o.created_by = creator.id
                  LEFT JOIN measurements ms ON o.measurement_id = ms.id";
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
            require_once __DIR__ . '/Customer.php';
            $cModel = new Customer();
            $cData = $cModel->find($custId);
            $fullName = '';
            $phone = '';
            if ($cData) {
                $fullName = trim(($cData['first_name'] ?? '') . ' ' . ($cData['last_name'] ?? ''));
                if (empty($fullName) && !empty($cData['name'])) {
                    $fullName = trim($cData['name']);
                }
                $phone = !empty($cData['phone']) ? preg_replace('/\D/', '', $cData['phone']) : (!empty($cData['phone_number']) ? preg_replace('/\D/', '', $cData['phone_number']) : '');
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
            if (!empty($phone) && !empty($fullName)) {
                $custClauses[] = "(REPLACE(REPLACE(o.customer_phone, '+', ''), ' ', '') = :cust_filter_phone AND (o.customer_name IS NULL OR TRIM(o.customer_name) = '' OR LOWER(TRIM(o.customer_name)) = :cust_filter_phone_name))";
                $params['cust_filter_phone'] = $phone;
                $params['cust_filter_phone_name'] = strtolower($fullName);
            }
            $where_clauses[] = "(" . implode(" OR ", $custClauses) . ")";
            unset($conditions['customer_id']);
        }

        if (!empty($conditions['search'])) {
            $searchTerm = '%' . strtolower(trim($conditions['search'])) . '%';
            $cleanSearchPhone = '%' . preg_replace('/\D/', '', $conditions['search']) . '%';
            $where_clauses[] = "(LOWER(o.order_number) LIKE :search_term 
                                OR LOWER(o.customer_name) LIKE :search_term 
                                OR LOWER(ms.name) LIKE :search_term 
                                OR LOWER(CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, ''))) LIKE :search_term 
                                OR ct.name LIKE :search_term
                                OR EXISTS (SELECT 1 FROM order_items oi2 JOIN cloth_types ct3 ON oi2.cloth_type_id = ct3.id WHERE oi2.order_id = o.id AND LOWER(ct3.name) LIKE :search_term_item)
                                OR REPLACE(REPLACE(o.customer_phone, '+', ''), ' ', '') LIKE :search_term_phone)";
            $params['search_term'] = $searchTerm;
            $params['search_term_item'] = $searchTerm;
            $params['search_term_phone'] = $cleanSearchPhone;
            unset($conditions['search']);
        }

        if (!empty($conditions['assigned_tailor_id'])) {
            $where_clauses[] = "(o.assigned_tailor_id = :assigned_tailor_id OR EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.id AND oi.assigned_tailor_id = :assigned_tailor_id_item))";
            $params['assigned_tailor_id'] = $conditions['assigned_tailor_id'];
            $params['assigned_tailor_id_item'] = $conditions['assigned_tailor_id'];
            unset($conditions['assigned_tailor_id']);
        }

        if (!empty($conditions['cloth_type_id'])) {
            $where_clauses[] = "(o.cloth_type_id = :cloth_type_id OR EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.id AND oi.cloth_type_id = :cloth_type_id_item))";
            $params['cloth_type_id'] = $conditions['cloth_type_id'];
            $params['cloth_type_id_item'] = $conditions['cloth_type_id'];
            unset($conditions['cloth_type_id']);
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
            $query .= " AND (assigned_tailor_id = :tailor_id OR EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = " . $this->table . ".id AND oi.assigned_tailor_id = :tailor_id_item))";
        }
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':this_month', $this_month);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        if ($isTailor) {
            $stmt->bindParam(':tailor_id', $tailorId, PDO::PARAM_INT);
            $stmt->bindParam(':tailor_id_item', $tailorId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['this_month'] = $result['count'];
        
        $query = "SELECT SUM(total_amount) as total FROM " . $this->table . " WHERE status IN ('completed', 'delivered')";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        if ($isTailor) {
            $query .= " AND (assigned_tailor_id = :tailor_id OR EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = " . $this->table . ".id AND oi.assigned_tailor_id = :tailor_id_item))";
        }
        $stmt = $this->conn->prepare($query);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        if ($isTailor) {
            $stmt->bindParam(':tailor_id', $tailorId, PDO::PARAM_INT);
            $stmt->bindParam(':tailor_id_item', $tailorId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['total_revenue'] = $result['total'] ?? 0;
        
        $query = "SELECT SUM(balance_amount) as total FROM " . $this->table . " WHERE status IN ('pending', 'in_progress', 'completed')";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        if ($isTailor) {
            $query .= " AND (assigned_tailor_id = :tailor_id OR EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = " . $this->table . ".id AND oi.assigned_tailor_id = :tailor_id_item))";
        }
        $stmt = $this->conn->prepare($query);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        if ($isTailor) {
            $stmt->bindParam(':tailor_id', $tailorId, PDO::PARAM_INT);
            $stmt->bindParam(':tailor_id_item', $tailorId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['pending_revenue'] = $result['total'] ?? 0;

        // Financial totals for dashboard (Advance, Pending, and Total amount)
        $finQuery = "SELECT 
                        COALESCE(SUM(CASE WHEN status != 'cancelled' THEN advance_amount ELSE 0 END), 0) as total_advance,
                        COALESCE(SUM(CASE WHEN status != 'cancelled' THEN (CASE WHEN balance_amount IS NOT NULL THEN balance_amount ELSE GREATEST(0, total_amount - advance_amount) END) ELSE 0 END), 0) as total_pending
                     FROM " . $this->table . " WHERE 1=1";
        if ($companyId) {
            $finQuery .= " AND company_id = :company_id";
        }
        if ($isTailor) {
            $finQuery .= " AND (assigned_tailor_id = :tailor_id OR EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = " . $this->table . ".id AND oi.assigned_tailor_id = :tailor_id_item))";
        }
        $finStmt = $this->conn->prepare($finQuery);
        if ($companyId) {
            $finStmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        if ($isTailor) {
            $finStmt->bindParam(':tailor_id', $tailorId, PDO::PARAM_INT);
            $finStmt->bindParam(':tailor_id_item', $tailorId, PDO::PARAM_INT);
        }
        $finStmt->execute();
        $finResult = $finStmt->fetch();

        $stats['total_advance'] = (float)($finResult['total_advance'] ?? 0);
        $stats['total_pending'] = (float)($finResult['total_pending'] ?? 0);
        $stats['total_amount'] = $stats['total_advance'] + $stats['total_pending'];
        
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
            if (empty($fullName) && !empty($customer['name'])) {
                $fullName = trim($customer['name']);
            }
            if (!empty($fullName)) {
                $query .= " OR LOWER(TRIM(o.customer_name)) = :full_name OR LOWER(TRIM(ms.name)) = :ms_full_name";
                $params[':full_name'] = strtolower($fullName);
                $params[':ms_full_name'] = strtolower($fullName);
            }
            if (!empty($customer['phone']) && !empty($fullName)) {
                $cleanPhone = preg_replace('/\D/', '', $customer['phone']);
                $query .= " OR (REPLACE(REPLACE(o.customer_phone, '+', ''), ' ', '') = :clean_phone AND (o.customer_name IS NULL OR TRIM(o.customer_name) = '' OR LOWER(TRIM(o.customer_name)) = :clean_phone_name))";
                $params[':clean_phone'] = $cleanPhone;
                $params[':clean_phone_name'] = strtolower($fullName);
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
    public function count($conditions = []) {
        $companyId = $this->getCompanyId();
        if ($companyId && !isset($conditions['company_id'])) {
            $conditions['company_id'] = $companyId;
        }
        if (isset($conditions['assigned_tailor_id'])) {
            $tailorId = (int)$conditions['assigned_tailor_id'];
            unset($conditions['assigned_tailor_id']);
            $query = "SELECT COUNT(*) as count FROM " . $this->table . " o WHERE (o.assigned_tailor_id = :tailor_id OR EXISTS (SELECT 1 FROM order_items oi WHERE oi.order_id = o.id AND oi.assigned_tailor_id = :tailor_id_item))";
            $params = [':tailor_id' => $tailorId, ':tailor_id_item' => $tailorId];
            foreach ($conditions as $k => $v) {
                $query .= " AND o.$k = :$k";
                $params[":$k"] = $v;
            }
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            $row = $stmt->fetch();
            return (int)($row['count'] ?? 0);
        }
        return parent::count($conditions);
    }
    public function update($id, $data) {
        $companyId = $this->getCompanyId();
        if ($companyId) {
            $existing = $this->find($id);
            if (!$existing) {
                return false; 
            }
        }
        if (!isset($data['updated_at'])) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        return parent::update($id, $data);
    }
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

    public function getOrderItems($orderId, $tailorId = null) {
        if (!$this->conn || !$orderId) return [];
        $query = "SELECT oi.*, 
                         ct.name as cloth_type_name, ct.standard_rate, ct.category,
                         u.full_name as tailor_name,
                         ms.name as measurement_customer_name, ms.measurement_data, ms.created_at as measurement_created_at
                  FROM order_items oi
                  LEFT JOIN cloth_types ct ON oi.cloth_type_id = ct.id
                  LEFT JOIN users u ON oi.assigned_tailor_id = u.id
                  LEFT JOIN measurements ms ON oi.measurement_id = ms.id
                  WHERE oi.order_id = :order_id";
        if ($tailorId !== null) {
            $query .= " AND oi.assigned_tailor_id = :tailor_id";
        }
        $query .= " ORDER BY oi.id ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
        if ($tailorId !== null) {
            $stmt->bindParam(':tailor_id', $tailorId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as &$item) {
            if (!empty($item['measurement_data']) && is_string($item['measurement_data'])) {
                $item['measurement_data'] = json_decode($item['measurement_data'], true);
            }
        }
        return $items;
    }

    public function saveOrderItems($orderId, array $items) {
        if (!$this->conn || !$orderId || empty($items)) {
            return false;
        }
        try {
            $delStmt = $this->conn->prepare("DELETE FROM order_items WHERE order_id = :order_id");
            $delStmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
            $delStmt->execute();

            $insertStmt = $this->conn->prepare("
                INSERT INTO order_items (order_id, cloth_type_id, measurement_id, assigned_tailor_id, quantity, price, status, notes)
                VALUES (:order_id, :cloth_type_id, :measurement_id, :assigned_tailor_id, :quantity, :price, :status, :notes)
            ");

            foreach ($items as $item) {
                $clothTypeId = !empty($item['cloth_type_id']) ? (int)$item['cloth_type_id'] : null;
                if (!$clothTypeId) continue;
                $measId = !empty($item['measurement_id']) ? (int)$item['measurement_id'] : null;
                $tailorId = !empty($item['assigned_tailor_id']) ? (int)$item['assigned_tailor_id'] : null;
                $qty = !empty($item['quantity']) ? (int)$item['quantity'] : 1;
                $price = isset($item['price']) ? (float)$item['price'] : 0.00;
                $status = !empty($item['status']) ? $item['status'] : 'pending';
                $notes = !empty($item['notes']) ? $item['notes'] : null;

                $insertStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
                $insertStmt->bindValue(':cloth_type_id', $clothTypeId, PDO::PARAM_INT);
                $insertStmt->bindValue(':measurement_id', $measId, $measId ? PDO::PARAM_INT : PDO::PARAM_NULL);
                $insertStmt->bindValue(':assigned_tailor_id', $tailorId, $tailorId ? PDO::PARAM_INT : PDO::PARAM_NULL);
                $insertStmt->bindValue(':quantity', $qty, PDO::PARAM_INT);
                $insertStmt->bindValue(':price', $price);
                $insertStmt->bindValue(':status', $status);
                $insertStmt->bindValue(':notes', $notes);
                $insertStmt->execute();
            }
            try {
                $touchStmt = $this->conn->prepare("UPDATE " . $this->table . " SET updated_at = NOW() WHERE id = :order_id");
                $touchStmt->execute([':order_id' => $orderId]);
            } catch (\Throwable $t) {}
            return true;
        } catch (\Throwable $e) {
            error_log("saveOrderItems error: " . $e->getMessage());
            return false;
        }
    }

    public function updateOrderItemStatus($itemId, $status) {
        if (!$this->conn || !$itemId) return false;
        $stmt = $this->conn->prepare("UPDATE order_items SET status = :status WHERE id = :id");
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $itemId, PDO::PARAM_INT);
        $res = $stmt->execute();
        if ($res) {
            try {
                $touchStmt = $this->conn->prepare("UPDATE " . $this->table . " SET updated_at = NOW() WHERE id = (SELECT order_id FROM order_items WHERE id = :id LIMIT 1)");
                $touchStmt->execute([':id' => $itemId]);
            } catch (\Throwable $t) {}
        }
        return $res;
    }
    public function syncOrderStatusFromItems($orderId) {
        if (!$this->conn || !$orderId) return null;
        try {
            $stmt = $this->conn->prepare("SELECT status FROM order_items WHERE order_id = :order_id");
            $stmt->execute([':order_id' => $orderId]);
            $items = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (empty($items)) return null;

            $totalItems = count($items);
            $completedCount = 0;
            $deliveredCount = 0;
            $inProgressCount = 0;
            $cancelledCount = 0;
            $pendingCount = 0;

            foreach ($items as $s) {
                if ($s === 'completed') $completedCount++;
                elseif ($s === 'delivered') $deliveredCount++;
                elseif ($s === 'in_progress') $inProgressCount++;
                elseif ($s === 'cancelled') $cancelledCount++;
                else $pendingCount++;
            }

            $nonCancelledTotal = $totalItems - $cancelledCount;
            $newStatus = null;

            if ($nonCancelledTotal > 0 && ($completedCount + $deliveredCount) >= $nonCancelledTotal) {
                if ($deliveredCount >= $nonCancelledTotal) {
                    $newStatus = 'delivered';
                } else {
                    $newStatus = 'completed';
                }
            } elseif ($inProgressCount > 0 || $completedCount > 0) {
                $newStatus = 'in_progress';
            } elseif ($cancelledCount === $totalItems) {
                $newStatus = 'cancelled';
            } elseif ($pendingCount === $nonCancelledTotal) {
                $newStatus = 'pending';
            }

            if ($newStatus) {
                $currOrder = $this->find($orderId);
                if ($currOrder && $currOrder['status'] !== $newStatus) {
                    $this->updateOrderStatus($orderId, $newStatus);
                }
            }
            return $newStatus;
        } catch (\Throwable $e) {
            error_log("syncOrderStatusFromItems error: " . $e->getMessage());
            return null;
        }
    }
}
?>