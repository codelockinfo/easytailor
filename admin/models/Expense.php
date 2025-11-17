<?php
/**
 * Expense Model
 * Tailoring Management System
 */

class Expense extends BaseModel {
    protected $table = 'expenses';

    /**
     * Get company ID from session
     */
    private function getCompanyId() {
        require_once __DIR__ . '/../../config/config.php';
        return get_company_id();
    }

    /**
     * Get expenses with creator details
     */
    public function getExpensesWithDetails($conditions = [], $limit = null, $offset = 0) {
        $companyId = $this->getCompanyId();
        $query = "SELECT e.*, u.full_name as created_by_name
                  FROM " . $this->table . " e
                  LEFT JOIN users u ON e.created_by = u.id";
        
        $params = [];
        $where_clauses = [];
        
        // Add company_id filter
        if ($companyId) {
            $where_clauses[] = "e.company_id = :company_id";
            $params['company_id'] = $companyId;
        }
        
        if (!empty($conditions)) {
            foreach ($conditions as $column => $value) {
                if ($column === 'date_from') {
                    $where_clauses[] = "e.expense_date >= :date_from";
                    $params['date_from'] = $value;
                } elseif ($column === 'date_to') {
                    $where_clauses[] = "e.expense_date <= :date_to";
                    $params['date_to'] = $value;
                } else {
                    if (strpos($column, '.') !== false) {
                        // Handle table.column format
                        $where_clauses[] = $column . " = :" . str_replace('.', '_', $column);
                        $params[str_replace('.', '_', $column)] = $value;
                    } else {
                        $where_clauses[] = "e." . $column . " = :" . $column;
                        $params[$column] = $value;
                    }
                }
            }
        }
        
        if (!empty($where_clauses)) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }
        
        $query .= " ORDER BY e.expense_date DESC, e.created_at DESC";
        
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
     * Get expense statistics
     */
    public function getExpenseStats() {
        $companyId = $this->getCompanyId();
        $stats = [];
        
        // Total expenses
        $stats['total'] = $this->count();
        
        // Total expense amount
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
        
        // Expenses this month
        $this_month = date('Y-m-01');
        $query = "SELECT COUNT(*) as count, SUM(amount) as total 
                  FROM " . $this->table . " 
                  WHERE expense_date >= :this_month";
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
        
        // Expenses by category
        $query = "SELECT category, COUNT(*) as count, SUM(amount) as total 
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
        $query .= " GROUP BY category 
                  ORDER BY total DESC";
        $stmt = $this->conn->prepare($query);
        foreach ($params as $param => $value) {
            $stmt->bindValue(':' . $param, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $stats['by_category'] = $stmt->fetchAll();
        
        // Expenses by payment method
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
        $stats['by_payment_method'] = $stmt->fetchAll();
        
        return $stats;
    }

    /**
     * Get monthly expense statistics
     */
    public function getMonthlyExpenseStats($year = null, $month = null) {
        if (!$year) $year = date('Y');
        if (!$month) $month = date('m');
        
        $query = "SELECT 
                    COUNT(*) as expense_count,
                    SUM(amount) as total_amount
                  FROM " . $this->table . " 
                  WHERE YEAR(expense_date) = :year 
                  AND MONTH(expense_date) = :month";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt->bindParam(':month', $month, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch();
    }

    /**
     * Get expenses by date range
     */
    public function getExpensesByDateRange($start_date, $end_date) {
        $query = "SELECT e.*, u.full_name as created_by_name
                  FROM " . $this->table . " e
                  LEFT JOIN users u ON e.created_by = u.id
                  WHERE e.expense_date BETWEEN :start_date AND :end_date
                  ORDER BY e.expense_date DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get expenses by category
     */
    public function getExpensesByCategory($category) {
        return $this->getExpensesWithDetails(['category' => $category]);
    }

    /**
     * Get recent expenses
     */
    public function getRecentExpenses($limit = 10) {
        return $this->getExpensesWithDetails([], $limit);
    }

    /**
     * Search expenses
     */
    public function searchExpenses($search_term, $limit = 20) {
        $query = "SELECT e.*, u.full_name as created_by_name
                  FROM " . $this->table . " e
                  LEFT JOIN users u ON e.created_by = u.id
                  WHERE (e.category LIKE :search 
                      OR e.description LIKE :search
                      OR e.reference_number LIKE :search)
                  ORDER BY e.expense_date DESC
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $search_pattern = '%' . $search_term . '%';
        $stmt->bindParam(':search', $search_pattern);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get expense categories
     */
    public function getExpenseCategories() {
        $query = "SELECT DISTINCT category FROM " . $this->table . " WHERE category IS NOT NULL ORDER BY category";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get daily expenses for a specific month
     */
    public function getDailyExpensesForMonth($year, $month) {
        $query = "SELECT 
                    DAY(expense_date) as day,
                    SUM(amount) as total_amount,
                    COUNT(*) as expense_count
                  FROM " . $this->table . " 
                  WHERE YEAR(expense_date) = :year 
                  AND MONTH(expense_date) = :month
                  GROUP BY DAY(expense_date)
                  ORDER BY day";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt->bindParam(':month', $month, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get all expenses for export
     */
    public function getAllExpenses() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY expense_date DESC, created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get expense statistics by date range
     */
    public function getExpenseStatsByDateRange($date_from, $date_to) {
        $stats = [];
        
        // Total expenses in date range
        $query = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE DATE(expense_date) BETWEEN :date_from AND :date_to";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->bindParam(':date_to', $date_to);
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['total'] = $result['count'];
        
        // Total expense amount in date range
        $query = "SELECT SUM(amount) as total FROM " . $this->table . " WHERE DATE(expense_date) BETWEEN :date_from AND :date_to";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->bindParam(':date_to', $date_to);
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['total_amount'] = $result['total'] ?? 0;
        
        // Expenses by category in date range
        $query = "SELECT category, COUNT(*) as count, SUM(amount) as total 
                  FROM " . $this->table . " 
                  WHERE DATE(expense_date) BETWEEN :date_from AND :date_to
                  GROUP BY category 
                  ORDER BY total DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->bindParam(':date_to', $date_to);
        $stmt->execute();
        $stats['by_category'] = $stmt->fetchAll();
        
        // Expenses by payment method in date range
        $query = "SELECT payment_method, COUNT(*) as count, SUM(amount) as total 
                  FROM " . $this->table . " 
                  WHERE DATE(expense_date) BETWEEN :date_from AND :date_to
                  GROUP BY payment_method 
                  ORDER BY total DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->bindParam(':date_to', $date_to);
        $stmt->execute();
        $stats['by_payment_method'] = $stmt->fetchAll();
        
        return $stats;
    }

    /**
     * Get monthly expense statistics by date range
     */
    public function getMonthlyExpenseStatsByDateRange($year, $month, $date_from, $date_to) {
        $query = "SELECT 
                    COUNT(*) as expense_count,
                    SUM(amount) as total_amount
                  FROM " . $this->table . " 
                  WHERE YEAR(expense_date) = :year 
                  AND MONTH(expense_date) = :month
                  AND DATE(expense_date) BETWEEN :date_from AND :date_to";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt->bindParam(':month', $month, PDO::PARAM_INT);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->bindParam(':date_to', $date_to);
        $stmt->execute();
        
        return $stmt->fetch();
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

