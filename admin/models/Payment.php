<?php
/**
 * Payment Model
 * Tailoring Management System
 */

class Payment extends BaseModel {
    protected $table = 'payments';

    /**
     * Get company ID from session
     */
    private function getCompanyId() {
        require_once __DIR__ . '/../../config/config.php';
        return get_company_id();
    }

    /**
     * Get payments for a specific invoice
     */
    public function getInvoicePayments($invoice_id) {
        return $this->findAll(['invoice_id' => $invoice_id], 'payment_date DESC');
    }

    /**
     * Get payments with invoice and customer details
     */
    public function getPaymentsWithDetails($conditions = [], $limit = null, $offset = 0) {
        $companyId = $this->getCompanyId();
        $query = "SELECT p.*, 
                         i.invoice_number,
                         o.order_number,
                         c.first_name, c.last_name, c.customer_code,
                         creator.full_name as created_by_name
                  FROM " . $this->table . " p
                  LEFT JOIN invoices i ON p.invoice_id = i.id
                  LEFT JOIN orders o ON i.order_id = o.id
                  LEFT JOIN customers c ON o.customer_id = c.id
                  LEFT JOIN users creator ON p.created_by = creator.id";
        
        $params = [];
        $where_clauses = [];
        
        // Add company_id filter
        if ($companyId) {
            $where_clauses[] = "p.company_id = :company_id";
            $params['company_id'] = $companyId;
        }
        
        if (!empty($conditions)) {
            foreach ($conditions as $column => $value) {
                if (strpos($column, '.') !== false) {
                    // Handle table.column format
                    $where_clauses[] = $column . " = :" . str_replace('.', '_', $column);
                    $params[str_replace('.', '_', $column)] = $value;
                } else {
                    $where_clauses[] = "p." . $column . " = :" . $column;
                    $params[$column] = $value;
                }
            }
        }
        
        if (!empty($where_clauses)) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }
        
        $query .= " ORDER BY p.payment_date DESC";
        
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
     * Create payment and update invoice
     */
    public function createPayment($data) {
        // Ensure company_id is set
        if (!isset($data['company_id'])) {
            $data['company_id'] = $this->getCompanyId();
        }
        
        try {
            $this->beginTransaction();
            
            // Create payment
            $paymentId = $this->create($data);
            
            if ($paymentId) {
                // Update invoice payment status
                require_once __DIR__ . '/Invoice.php';
                $invoiceModel = new Invoice();
                $invoiceModel->updatePaymentStatus($data['invoice_id']);
                
                $this->commit();
                return $paymentId;
            } else {
                $this->rollback();
                return false;
            }
            
        } catch (Exception $e) {
            $this->rollback();
            return false;
        }
    }

    /**
     * Get payment statistics
     */
    public function getPaymentStats() {
        $companyId = $this->getCompanyId();
        $stats = [];
        
        // Total payments
        $stats['total'] = $this->count();
        
        // Total payment amount
        $query = "SELECT SUM(amount) as total FROM " . $this->table;
        $where_clauses = [];
        $params = [];
        if ($companyId) {
            $where_clauses[] = "company_id = :company_id";
            $params['company_id'] = $companyId;
        }
        if (!empty($where_clauses)) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }
        $stmt = $this->conn->prepare($query);
        foreach ($params as $param => $value) {
            $stmt->bindValue(':' . $param, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['total_amount'] = $result['total'] ?? 0;
        
        // Payments this month
        $this_month = date('Y-m-01');
        $query = "SELECT COUNT(*) as count, SUM(amount) as total 
                  FROM " . $this->table . " 
                  WHERE payment_date >= :this_month";
        $params = [':this_month' => $this_month];
        if ($companyId) {
            $query .= " AND company_id = :company_id";
            $params[':company_id'] = $companyId;
        }
        $stmt = $this->conn->prepare($query);
        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['this_month_count'] = $result['count'];
        $stats['this_month_amount'] = $result['total'] ?? 0;
        
        // Payments by method
        $query = "SELECT payment_method, COUNT(*) as count, SUM(amount) as total 
                  FROM " . $this->table;
        $where_clauses = [];
        $params = [];
        if ($companyId) {
            $where_clauses[] = "company_id = :company_id";
            $params['company_id'] = $companyId;
        }
        if (!empty($where_clauses)) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }
        $query .= " GROUP BY payment_method 
                  ORDER BY total DESC";
        $stmt = $this->conn->prepare($query);
        foreach ($params as $param => $value) {
            $stmt->bindValue(':' . $param, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $stats['by_method'] = $stmt->fetchAll();
        
        return $stats;
    }

    /**
     * Get monthly payment statistics
     */
    public function getMonthlyPaymentStats($year = null, $month = null) {
        $companyId = $this->getCompanyId();
        if (!$year) $year = date('Y');
        if (!$month) $month = date('m');
        
        $query = "SELECT 
                    COUNT(*) as payment_count,
                    SUM(amount) as total_amount
                  FROM " . $this->table . " 
                  WHERE YEAR(payment_date) = :year 
                  AND MONTH(payment_date) = :month";
        
        $params = [':year' => $year, ':month' => $month];
        if ($companyId) {
            $query .= " AND company_id = :company_id";
            $params[':company_id'] = $companyId;
        }
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value, ($param === ':year' || $param === ':month' || $param === ':company_id') ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        
        return $stmt->fetch();
    }

    /**
     * Delete payment and update invoice
     */
    public function deletePayment($id) {
        $payment = $this->find($id);
        if (!$payment) return false;
        
        try {
            $this->beginTransaction();
            
            // Delete payment
            $result = $this->delete($id);
            
            if ($result) {
                // Update invoice payment status
                require_once __DIR__ . '/Invoice.php';
                $invoiceModel = new Invoice();
                $invoiceModel->updatePaymentStatus($payment['invoice_id']);
                
                $this->commit();
                return true;
            } else {
                $this->rollback();
                return false;
            }
            
        } catch (Exception $e) {
            $this->rollback();
            return false;
        }
    }

    /**
     * Get recent payments
     */
    public function getRecentPayments($limit = 10) {
        return $this->getPaymentsWithDetails([], $limit);
    }

    /**
     * Search payments
     */
    public function searchPayments($search_term, $limit = 20) {
        $companyId = $this->getCompanyId();
        $query = "SELECT p.*, 
                         i.invoice_number,
                         o.order_number,
                         c.first_name, c.last_name, c.customer_code
                  FROM " . $this->table . " p
                  LEFT JOIN invoices i ON p.invoice_id = i.id
                  LEFT JOIN orders o ON i.order_id = o.id
                  LEFT JOIN customers c ON o.customer_id = c.id
                  WHERE (i.invoice_number LIKE :search 
                      OR o.order_number LIKE :search 
                      OR c.first_name LIKE :search 
                      OR c.last_name LIKE :search 
                      OR c.customer_code LIKE :search
                      OR p.reference_number LIKE :search)";
        
        $params = [':search' => '%' . $search_term . '%'];
        if ($companyId) {
            $query .= " AND p.company_id = :company_id";
            $params[':company_id'] = $companyId;
        }
        
        $query .= " ORDER BY p.payment_date DESC
                  LIMIT :limit";
        $params[':limit'] = $limit;
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $param => $value) {
            $stmt->bindValue($param, $value, ($param === ':limit' || $param === ':company_id') ? PDO::PARAM_INT : PDO::PARAM_STR);
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

