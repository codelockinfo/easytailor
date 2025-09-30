<?php
/**
 * Payment Model
 * Tailoring Management System
 */

class Payment extends BaseModel {
    protected $table = 'payments';

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
        
        if (!empty($conditions)) {
            $where_clauses = [];
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
        $stats = [];
        
        // Total payments
        $stats['total'] = $this->count();
        
        // Total payment amount
        $query = "SELECT SUM(amount) as total FROM " . $this->table;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['total_amount'] = $result['total'] ?? 0;
        
        // Payments this month
        $this_month = date('Y-m-01');
        $query = "SELECT COUNT(*) as count, SUM(amount) as total 
                  FROM " . $this->table . " 
                  WHERE payment_date >= :this_month";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':this_month', $this_month);
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['this_month_count'] = $result['count'];
        $stats['this_month_amount'] = $result['total'] ?? 0;
        
        // Payments by method
        $query = "SELECT payment_method, COUNT(*) as count, SUM(amount) as total 
                  FROM " . $this->table . " 
                  GROUP BY payment_method 
                  ORDER BY total DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['by_method'] = $stmt->fetchAll();
        
        return $stats;
    }

    /**
     * Get monthly payment statistics
     */
    public function getMonthlyPaymentStats($year = null, $month = null) {
        if (!$year) $year = date('Y');
        if (!$month) $month = date('m');
        
        $query = "SELECT 
                    COUNT(*) as payment_count,
                    SUM(amount) as total_amount
                  FROM " . $this->table . " 
                  WHERE YEAR(payment_date) = :year 
                  AND MONTH(payment_date) = :month";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt->bindParam(':month', $month, PDO::PARAM_INT);
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
                      OR p.reference_number LIKE :search)
                  ORDER BY p.payment_date DESC
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $search_pattern = '%' . $search_term . '%';
        $stmt->bindParam(':search', $search_pattern);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}
?>

