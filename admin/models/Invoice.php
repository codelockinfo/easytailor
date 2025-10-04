<?php
/**
 * Invoice Model
 * Tailoring Management System
 */

class Invoice extends BaseModel {
    protected $table = 'invoices';

    /**
     * Create new invoice with auto-generated invoice number
     */
    public function createInvoice($data) {
        // Generate invoice number
        $data['invoice_number'] = $this->generateInvoiceNumber();
        return $this->create($data);
    }

    /**
     * Generate unique invoice number
     */
    private function generateInvoiceNumber() {
        $prefix = 'INV';
        $query = "SELECT invoice_number FROM " . $this->table . " WHERE invoice_number LIKE :pattern ORDER BY invoice_number DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $pattern = $prefix . '%';
        $stmt->bindParam(':pattern', $pattern);
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
     * Get invoices with order and customer details
     */
    public function getInvoicesWithDetails($conditions = [], $limit = null, $offset = 0) {
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
        
        if (!empty($conditions)) {
            $where_clauses = [];
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
        $stats = [];
        
        // Total invoices
        $stats['total'] = $this->count();
        
        // Invoices by payment status
        $statuses = ['paid', 'partial', 'due'];
        foreach ($statuses as $status) {
            $stats[$status] = $this->count(['payment_status' => $status]);
        }
        
        // Total invoice amount
        $query = "SELECT SUM(total_amount) as total FROM " . $this->table;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['total_amount'] = $result['total'] ?? 0;
        
        // Paid amount
        $query = "SELECT SUM(paid_amount) as total FROM " . $this->table;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['paid_amount'] = $result['total'] ?? 0;
        
        // Due amount
        $query = "SELECT SUM(balance_amount) as total FROM " . $this->table . " WHERE payment_status IN ('partial', 'due')";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['due_amount'] = $result['total'] ?? 0;
        
        return $stats;
    }

    /**
     * Get overdue invoices
     */
    public function getOverdueInvoices() {
        $today = date('Y-m-d');
        $query = "SELECT i.*, 
                         o.order_number,
                         c.first_name, c.last_name, c.customer_code, c.phone as customer_phone
                  FROM " . $this->table . " i
                  LEFT JOIN orders o ON i.order_id = o.id
                  LEFT JOIN customers c ON o.customer_id = c.id
                  WHERE i.due_date < :today 
                  AND i.payment_status IN ('partial', 'due')
                  ORDER BY i.due_date ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':today', $today);
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
        return $this->findOne(['invoice_number' => $invoice_number]);
    }

    /**
     * Get invoices for a specific customer
     */
    public function getCustomerInvoices($customer_id) {
        $query = "SELECT i.*, o.order_number, ct.name as cloth_type_name
                  FROM " . $this->table . " i
                  LEFT JOIN orders o ON i.order_id = o.id
                  LEFT JOIN cloth_types ct ON o.cloth_type_id = ct.id
                  WHERE o.customer_id = :customer_id
                  ORDER BY i.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get monthly invoice statistics
     */
    public function getMonthlyInvoiceStats($year = null, $month = null) {
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
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt->bindParam(':month', $month, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch();
    }

    /**
     * Update invoice paid and balance amounts
     */
    public function updateInvoiceAmounts($invoiceId, $paidAmount, $balanceAmount) {
        $query = "UPDATE " . $this->table . " 
                  SET paid_amount = :paid_amount, 
                      balance_amount = :balance_amount,
                      updated_at = NOW()
                  WHERE id = :invoice_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':paid_amount', $paidAmount);
        $stmt->bindParam(':balance_amount', $balanceAmount);
        $stmt->bindParam(':invoice_id', $invoiceId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }
    
    /**
     * Get all invoices for export
     */
    public function getAllInvoices() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY invoice_date DESC, created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get invoice statistics by date range
     */
    public function getInvoiceStatsByDateRange($date_from, $date_to) {
        $stats = [];
        
        // Total invoices in date range
        $query = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE DATE(created_at) BETWEEN :date_from AND :date_to";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->bindParam(':date_to', $date_to);
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['total'] = $result['count'];
        
        // Invoices by payment status in date range
        $statuses = ['paid', 'partial', 'due'];
        foreach ($statuses as $status) {
            $query = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE payment_status = :status AND DATE(created_at) BETWEEN :date_from AND :date_to";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':date_from', $date_from);
            $stmt->bindParam(':date_to', $date_to);
            $stmt->execute();
            $result = $stmt->fetch();
            $stats[$status] = $result['count'];
        }
        
        // Total invoice amount in date range
        $query = "SELECT SUM(total_amount) as total FROM " . $this->table . " WHERE DATE(created_at) BETWEEN :date_from AND :date_to";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->bindParam(':date_to', $date_to);
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['total_amount'] = $result['total'] ?? 0;
        
        // Paid amount in date range
        $query = "SELECT SUM(paid_amount) as total FROM " . $this->table . " WHERE DATE(created_at) BETWEEN :date_from AND :date_to";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->bindParam(':date_to', $date_to);
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['paid_amount'] = $result['total'] ?? 0;
        
        // Due amount in date range
        $query = "SELECT SUM(balance_amount) as total FROM " . $this->table . " WHERE payment_status IN ('partial', 'due') AND DATE(created_at) BETWEEN :date_from AND :date_to";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->bindParam(':date_to', $date_to);
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['due_amount'] = $result['total'] ?? 0;
        
        return $stats;
    }
}
?>

