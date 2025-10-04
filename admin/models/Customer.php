<?php
/**
 * Customer Model
 * Tailoring Management System
 */

class Customer extends BaseModel {
    protected $table = 'customers';

    /**
     * Create new customer with auto-generated customer code
     */
    public function createCustomer($data) {
        // Generate customer code
        $data['customer_code'] = $this->generateCustomerCode();
        
        // Set company_id from session if not provided
        if (!isset($data['company_id']) && isset($_SESSION['company_id'])) {
            $data['company_id'] = $_SESSION['company_id'];
        }
        
        return $this->create($data);
    }

    /**
     * Generate unique customer code
     */
    private function generateCustomerCode() {
        $prefix = 'CUST';
        $query = "SELECT customer_code FROM " . $this->table . " WHERE customer_code LIKE :pattern ORDER BY customer_code DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $pattern = $prefix . '%';
        $stmt->bindParam(':pattern', $pattern);
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
     * Search customers
     */
    public function searchCustomers($search_term, $limit = 20) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE (first_name LIKE :search1 
                      OR last_name LIKE :search2 
                      OR customer_code LIKE :search3 
                      OR email LIKE :search4 
                      OR phone LIKE :search5)
                  AND status = 'active'
                  ORDER BY first_name, last_name
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $search_pattern = '%' . $search_term . '%';
        $stmt->bindParam(':search1', $search_pattern);
        $stmt->bindParam(':search2', $search_pattern);
        $stmt->bindParam(':search3', $search_pattern);
        $stmt->bindParam(':search4', $search_pattern);
        $stmt->bindParam(':search5', $search_pattern);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get customers with order count
     */
    public function getCustomersWithOrderCount($limit = null) {
        $query = "SELECT c.*, COUNT(o.id) as order_count 
                  FROM " . $this->table . " c 
                  LEFT JOIN orders o ON c.id = o.customer_id 
                  WHERE c.status = 'active'
                  GROUP BY c.id 
                  ORDER BY c.first_name, c.last_name";
        
        if ($limit) {
            $query .= " LIMIT " . $limit;
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get customer statistics
     */
    public function getCustomerStats() {
        $stats = [];
        
        // Total customers
        $stats['total'] = $this->count();
        
        // Active customers
        $stats['active'] = $this->count(['status' => 'active']);
        
        // New customers this month
        $this_month = date('Y-m-01');
        $query = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE created_at >= :this_month";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':this_month', $this_month);
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['this_month'] = $result['count'];
        
        return $stats;
    }

    /**
     * Get customer by customer code
     */
    public function findByCustomerCode($customer_code) {
        return $this->findOne(['customer_code' => $customer_code]);
    }

    /**
     * Check if email exists for customer
     */
    public function emailExists($email, $exclude_id = null) {
        $query = "SELECT id FROM " . $this->table . " WHERE email = :email";
        if ($exclude_id) {
            $query .= " AND id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        if ($exclude_id) {
            $stmt->bindParam(':exclude_id', $exclude_id);
        }
        $stmt->execute();
        
        return $stmt->fetch() ? true : false;
    }

    /**
     * Get customer's recent orders
     */
    public function getCustomerOrders($customer_id, $limit = 10) {
        $query = "SELECT o.*, ct.name as cloth_type_name, u.full_name as tailor_name
                  FROM orders o
                  LEFT JOIN cloth_types ct ON o.cloth_type_id = ct.id
                  LEFT JOIN users u ON o.assigned_tailor_id = u.id
                  WHERE o.customer_id = :customer_id
                  ORDER BY o.created_at DESC
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get all customers for export
     */
    public function getAllCustomers() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY first_name, last_name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get customer by ID
     */
    public function getCustomerById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch();
    }

    /**
     * Get customer statistics by date range
     */
    public function getCustomerStatsByDateRange($date_from, $date_to) {
        $stats = [];
        
        // Total customers in date range
        $query = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE DATE(created_at) BETWEEN :date_from AND :date_to";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->bindParam(':date_to', $date_to);
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['total'] = $result['count'];
        
        // Active customers in date range
        $query = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE status = 'active' AND DATE(created_at) BETWEEN :date_from AND :date_to";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->bindParam(':date_to', $date_to);
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['active'] = $result['count'];
        
        // New customers this month (for comparison)
        $this_month = date('Y-m-01');
        $query = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE created_at >= :this_month";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':this_month', $this_month);
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['this_month'] = $result['count'];
        
        return $stats;
    }
}
?>

