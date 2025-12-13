<?php
/**
 * Invoice Model
 * Tailoring Management System
 */

class Invoice extends BaseModel {
    protected $table = 'invoices';

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
     * Create new invoice with auto-generated invoice number
     */
    public function createInvoice($data) {
        // Ensure company_id is set
        if (!isset($data['company_id'])) {
            $data['company_id'] = $this->getCompanyId();
        }
        
        $maxRetries = 20;
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            try {
                // Generate invoice number
                $data['invoice_number'] = $this->generateInvoiceNumber();
                
                // Attempt to create the invoice
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
                        $data['invoice_number'] = $this->generateGuaranteedUniqueInvoiceNumber($data['company_id']);
                        try {
                            $result = $this->create($data);
                            if ($result !== false) {
                                return $result;
                            }
                        } catch (PDOException $e2) {
                            // Even fallback failed, throw user-friendly error
                            throw new Exception('Unable to create invoice. Please try again or contact support.');
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
        
        throw new Exception('Unable to create invoice after multiple attempts. Please try again.');
    }

    /**
     * Generate unique invoice number
     */
    private function generateInvoiceNumber() {
        $companyId = $this->getCompanyId();
        $prefix = 'INV';
        $query = "SELECT invoice_number FROM " . $this->table . " WHERE invoice_number LIKE :pattern";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        $query .= " ORDER BY invoice_number DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $pattern = $prefix . '%';
        $stmt->bindParam(':pattern', $pattern);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        $last_invoice = $stmt->fetch();
        
        if ($last_invoice) {
            $last_number = (int) substr($last_invoice['invoice_number'], 3);
            $new_number = $last_number + 1;
        } else {
            $new_number = 1;
        }
        
        return $prefix . str_pad($new_number, 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Generate guaranteed unique invoice number using timestamp
     * Used as fallback when normal generation fails due to race conditions
     */
    private function generateGuaranteedUniqueInvoiceNumber($companyId = null) {
        $prefix = 'INV';
        $timestamp = time();
        $random = mt_rand(100, 999);
        return $prefix . str_pad($timestamp . $random, 12, '0', STR_PAD_LEFT);
    }

    /**
     * Get invoices with order and customer details
     */
    public function getInvoicesWithDetails($conditions = [], $limit = null, $offset = 0, $search = null) {
        $companyId = $this->getCompanyId();
        $query = "SELECT i.*, 
                         o.order_number, o.order_date, o.due_date as order_due_date,
                         c.first_name, c.last_name, c.customer_code, c.phone as customer_phone, c.email as customer_email,
                         ct.name as cloth_type_name,
                         creator.full_name as created_by_name
                  FROM " . $this->table . " i
                  LEFT JOIN orders o ON i.order_id = o.id
                  LEFT JOIN customers c ON o.customer_id = c.id
                  LEFT JOIN cloth_types ct ON o.cloth_type_id = ct.id
                  LEFT JOIN users creator ON i.created_by = creator.id";
        
        $params = [];
        $where_clauses = [];
        
        // Add company_id filter
        if ($companyId) {
            $where_clauses[] = "i.company_id = :company_id";
            $params['company_id'] = $companyId;
        }
        
        if (!empty($conditions)) {
            foreach ($conditions as $column => $value) {
                if (strpos($column, '.') !== false) {
                    // Handle table.column format
                    $where_clauses[] = $column . " = :" . str_replace('.', '_', $column);
                    $params[str_replace('.', '_', $column)] = $value;
                } else {
                    $where_clauses[] = "i." . $column . " = :" . $column;
                    $params[$column] = $value;
                }
            }
        }
        
        // Add search filter if provided
        if (!empty($search)) {
            $searchPattern = '%' . $search . '%';
            $where_clauses[] = "(i.invoice_number LIKE :search_inv 
                                OR o.order_number LIKE :search_ord 
                                OR c.first_name LIKE :search_fname 
                                OR c.last_name LIKE :search_lname 
                                OR CONCAT(c.first_name, ' ', c.last_name) LIKE :search_fullname
                                OR c.phone LIKE :search_phone)";
            $params['search_inv'] = $searchPattern;
            $params['search_ord'] = $searchPattern;
            $params['search_fname'] = $searchPattern;
            $params['search_lname'] = $searchPattern;
            $params['search_fullname'] = $searchPattern;
            $params['search_phone'] = $searchPattern;
        }
        
        if (!empty($where_clauses)) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }
        
        $query .= " ORDER BY i.created_at DESC";
        
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
     * Get invoice statistics
     */
    public function getInvoiceStats() {
        $companyId = $this->getCompanyId();
        $stats = [];
        
        // Total invoices
        $conditions = [];
        if ($companyId) {
            $conditions['company_id'] = $companyId;
        }
        $stats['total'] = $this->count($conditions);
        
        // Invoices by payment status
        $statuses = ['paid', 'partial', 'due'];
        foreach ($statuses as $status) {
            $conditions['payment_status'] = $status;
            $stats[$status] = $this->count($conditions);
            unset($conditions['payment_status']);
        }
        
        // Total invoice amount
        $query = "SELECT SUM(total_amount) as total FROM " . $this->table;
        if ($companyId) {
            $query .= " WHERE company_id = :company_id";
        }
        $stmt = $this->conn->prepare($query);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['total_amount'] = $result['total'] ?? 0;
        
        // Paid amount
        $query = "SELECT SUM(paid_amount) as total FROM " . $this->table;
        if ($companyId) {
            $query .= " WHERE company_id = :company_id";
        }
        $stmt = $this->conn->prepare($query);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['paid_amount'] = $result['total'] ?? 0;
        
        // Due amount
        $query = "SELECT SUM(balance_amount) as total FROM " . $this->table . " WHERE payment_status IN ('partial', 'due')";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        $stmt = $this->conn->prepare($query);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['due_amount'] = $result['total'] ?? 0;
        
        return $stats;
    }

    /**
     * Get overdue invoices
     */
    public function getOverdueInvoices() {
        $companyId = $this->getCompanyId();
        $today = date('Y-m-d');
        $query = "SELECT i.*, 
                         o.order_number,
                         c.first_name, c.last_name, c.customer_code, c.phone as customer_phone
                  FROM " . $this->table . " i
                  LEFT JOIN orders o ON i.order_id = o.id
                  LEFT JOIN customers c ON o.customer_id = c.id
                  WHERE i.due_date < :today 
                  AND i.payment_status IN ('partial', 'due')";
        if ($companyId) {
            $query .= " AND i.company_id = :company_id";
        }
        $query .= " ORDER BY i.due_date ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':today', $today);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Update payment status
     */
    public function updatePaymentStatus($id) {
        $invoice = $this->find($id);
        if (!$invoice) return false;
        
        $paid_amount = $invoice['paid_amount'];
        $total_amount = $invoice['total_amount'];
        $balance_amount = $total_amount - $paid_amount;
        
        $payment_status = 'paid';
        if ($balance_amount > 0) {
            $payment_status = ($paid_amount > 0) ? 'partial' : 'due';
        }
        
        return $this->update($id, [
            'balance_amount' => $balance_amount,
            'payment_status' => $payment_status
        ]);
    }

    /**
     * Get invoice by invoice number
     */
    public function findByInvoiceNumber($invoice_number) {
        $companyId = $this->getCompanyId();
        $conditions = ['invoice_number' => $invoice_number];
        if ($companyId) {
            $conditions['company_id'] = $companyId;
        }
        return $this->findOne($conditions);
    }

    /**
     * Get invoices for a specific customer
     */
    public function getCustomerInvoices($customer_id) {
        $companyId = $this->getCompanyId();
        $query = "SELECT i.*, o.order_number, ct.name as cloth_type_name
                  FROM " . $this->table . " i
                  LEFT JOIN orders o ON i.order_id = o.id
                  LEFT JOIN cloth_types ct ON o.cloth_type_id = ct.id
                  WHERE o.customer_id = :customer_id";
        if ($companyId) {
            $query .= " AND i.company_id = :company_id";
        }
        $query .= " ORDER BY i.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get monthly invoice statistics
     */
    public function getMonthlyInvoiceStats($year = null, $month = null) {
        $companyId = $this->getCompanyId();
        if (!$year) $year = date('Y');
        if (!$month) $month = date('m');
        
        $query = "SELECT 
                    COUNT(*) as invoice_count,
                    SUM(total_amount) as total_amount,
                    SUM(paid_amount) as paid_amount,
                    SUM(balance_amount) as balance_amount
                  FROM " . $this->table . " 
                  WHERE YEAR(created_at) = :year 
                  AND MONTH(created_at) = :month";
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
        
        return $stmt->fetch();
    }

    /**
     * Update invoice paid and balance amounts
     */
    public function updateInvoiceAmounts($invoiceId, $paidAmount, $balanceAmount) {
        $companyId = $this->getCompanyId();
        $query = "UPDATE " . $this->table . " 
                  SET paid_amount = :paid_amount, 
                      balance_amount = :balance_amount,
                      updated_at = NOW()
                  WHERE id = :invoice_id";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':paid_amount', $paidAmount);
        $stmt->bindParam(':balance_amount', $balanceAmount);
        $stmt->bindParam(':invoice_id', $invoiceId, PDO::PARAM_INT);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        
        return $stmt->execute();
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
        // Verify the record belongs to this company (find() already filters by company_id)
        if ($companyId) {
            $existing = $this->find($id);
            if (!$existing) {
                return false; // Record doesn't exist or doesn't belong to this company
            }
        }
        return parent::delete($id);
    }
}
?>

