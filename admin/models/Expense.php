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
        
        $where_clauses = [];
        $params = [];
        if ($companyId) {
            $where_clauses[] = "company_id = :company_id";
            $params['company_id'] = $companyId;
        }
        $where_sql = !empty($where_clauses) ? " WHERE " . implode(" AND ", $where_clauses) : "";
        
        // Total transactions count
        $query = "SELECT COUNT(*) as total_count FROM " . $this->table . $where_sql;
        $stmt = $this->conn->prepare($query);
        foreach ($params as $param => $value) {
            $stmt->bindValue(':' . $param, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $stats['total'] = (int)($stmt->fetch()['total_count'] ?? 0);
        
        // Total actual expense amount (Cash Out only - excluding Cash In)
        $exp_clauses = $where_clauses;
        $exp_clauses[] = "payment_method != 'cash_in'";
        $query = "SELECT COALESCE(SUM(amount), 0) as total, COUNT(*) as count FROM " . $this->table . " WHERE " . implode(" AND ", $exp_clauses);
        $stmt = $this->conn->prepare($query);
        foreach ($params as $param => $value) {
            $stmt->bindValue(':' . $param, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $res = $stmt->fetch();
        $stats['total_amount'] = (float)($res['total'] ?? 0);
        $stats['total_cash_out_count'] = (int)($res['count'] ?? 0);
        $stats['total_cash_out'] = $stats['total_amount'];
        
        // Total cash in
        $cin_clauses = $where_clauses;
        $cin_clauses[] = "payment_method = 'cash_in'";
        $query = "SELECT COALESCE(SUM(amount), 0) as total, COUNT(*) as count FROM " . $this->table . " WHERE " . implode(" AND ", $cin_clauses);
        $stmt = $this->conn->prepare($query);
        foreach ($params as $param => $value) {
            $stmt->bindValue(':' . $param, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $resCin = $stmt->fetch();
        $stats['total_cash_in'] = (float)($resCin['total'] ?? 0);
        $stats['total_cash_in_count'] = (int)($resCin['count'] ?? 0);
        $stats['net_flow'] = $stats['total_cash_in'] - $stats['total_cash_out'];
        
        // Expenses this month (Cash Out only)
        $this_month = date('Y-m-01');
        $m_clauses = $exp_clauses;
        $m_clauses[] = "expense_date >= :this_month";
        $m_params = $params;
        $m_params['this_month'] = $this_month;
        $query = "SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total 
                  FROM " . $this->table . " 
                  WHERE " . implode(" AND ", $m_clauses);
        $stmt = $this->conn->prepare($query);
        foreach ($m_params as $param => $value) {
            $stmt->bindValue(':' . $param, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['this_month_count'] = (int)($result['count'] ?? 0);
        $stats['this_month_amount'] = (float)($result['total'] ?? 0);
        
        // Expenses by category (Cash Out only, with fallback to description if category is empty)
        $query = "SELECT COALESCE(NULLIF(category, ''), COALESCE(NULLIF(description, ''), 'General')) as category, 
                         COUNT(*) as count, 
                         COALESCE(SUM(amount), 0) as total 
                  FROM " . $this->table . " 
                  WHERE " . implode(" AND ", $exp_clauses) . " 
                  GROUP BY COALESCE(NULLIF(category, ''), COALESCE(NULLIF(description, ''), 'General')) 
                  ORDER BY total DESC";
        $stmt = $this->conn->prepare($query);
        foreach ($params as $param => $value) {
            $stmt->bindValue(':' . $param, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $stats['by_category'] = $stmt->fetchAll();
        
        // Expenses by payment method (all records)
        $query = "SELECT payment_method, COUNT(*) as count, COALESCE(SUM(amount), 0) as total 
                  FROM " . $this->table . $where_sql . " 
                  GROUP BY payment_method 
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
     * Get monthly expense statistics (Cash Out only, filtered by company)
     */
    public function getMonthlyExpenseStats($year = null, $month = null) {
        $companyId = $this->getCompanyId();
        if (!$year) $year = date('Y');
        if (!$month) $month = date('m');
        
        $query = "SELECT 
                    COUNT(*) as expense_count,
                    COALESCE(SUM(CASE WHEN payment_method != 'cash_in' THEN amount ELSE 0 END), 0) as total_expenses,
                    COALESCE(SUM(CASE WHEN payment_method = 'cash_in' THEN amount ELSE 0 END), 0) as total_cash_in,
                    COALESCE(SUM(CASE WHEN payment_method != 'cash_in' THEN amount ELSE 0 END), 0) as total_amount
                  FROM " . $this->table . " 
                  WHERE YEAR(expense_date) = :year 
                  AND MONTH(expense_date) = :month";
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
     * Get expenses by date range (filtered by company)
     */
    public function getExpensesByDateRange($start_date, $end_date) {
        $companyId = $this->getCompanyId();
        $query = "SELECT e.*, u.full_name as created_by_name
                  FROM " . $this->table . " e
                  LEFT JOIN users u ON e.created_by = u.id
                  WHERE e.expense_date BETWEEN :start_date AND :end_date";
        if ($companyId) {
            $query .= " AND e.company_id = :company_id";
        }
        $query .= " ORDER BY e.expense_date DESC, e.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
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
     * Search expenses (filtered by company)
     */
    public function searchExpenses($search_term, $limit = 20) {
        $companyId = $this->getCompanyId();
        $query = "SELECT e.*, u.full_name as created_by_name
                  FROM " . $this->table . " e
                  LEFT JOIN users u ON e.created_by = u.id
                  WHERE (e.category LIKE :search_cat 
                      OR e.description LIKE :search_desc
                      OR e.reference_number LIKE :search_ref)";
        if ($companyId) {
            $query .= " AND e.company_id = :company_id";
        }
        $query .= " ORDER BY e.expense_date DESC LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $search_pattern = '%' . $search_term . '%';
        $stmt->bindParam(':search_cat', $search_pattern);
        $stmt->bindParam(':search_desc', $search_pattern);
        $stmt->bindParam(':search_ref', $search_pattern);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get expense categories (filtered by company)
     */
    public function getExpenseCategories() {
        $companyId = $this->getCompanyId();
        $query = "SELECT DISTINCT category FROM " . $this->table . " WHERE category IS NOT NULL AND category != ''";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        $query .= " ORDER BY category";
        $stmt = $this->conn->prepare($query);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get daily expenses for a specific month (filtered by company, Cash Out only)
     */
    public function getDailyExpensesForMonth($year, $month) {
        $companyId = $this->getCompanyId();
        $query = "SELECT 
                    DAY(expense_date) as day,
                    COALESCE(SUM(amount), 0) as total_amount,
                    COUNT(*) as expense_count
                  FROM " . $this->table . " 
                  WHERE YEAR(expense_date) = :year 
                  AND MONTH(expense_date) = :month
                  AND payment_method != 'cash_in'";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        $query .= " GROUP BY DAY(expense_date) ORDER BY day";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt->bindParam(':month', $month, PDO::PARAM_INT);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get all expenses for export (filtered by company and user)
     */
    public function getAllExpenses($created_by = null) {
        $companyId = $this->getCompanyId();
        $query = "SELECT * FROM " . $this->table;
        
        $where_clauses = [];
        $params = [];
        
        if ($companyId) {
            $where_clauses[] = "company_id = :company_id";
            $params['company_id'] = $companyId;
        }
        
        if ($created_by) {
            $where_clauses[] = "created_by = :created_by";
            $params['created_by'] = $created_by;
        }
        
        if (!empty($where_clauses)) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }
        
        $query .= " ORDER BY expense_date DESC, created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $param => $value) {
            $stmt->bindValue(':' . $param, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get expense statistics by date range (filtered by company, Cash Out only for expenses)
     */
    public function getExpenseStatsByDateRange($date_from, $date_to) {
        $companyId = $this->getCompanyId();
        $stats = [];
        
        $where_clauses = ["DATE(expense_date) BETWEEN :date_from AND :date_to"];
        $params = [
            'date_from' => $date_from,
            'date_to' => $date_to
        ];
        if ($companyId) {
            $where_clauses[] = "company_id = :company_id";
            $params['company_id'] = $companyId;
        }
        $where_sql = " WHERE " . implode(" AND ", $where_clauses);
        
        // Total transactions count in date range
        $query = "SELECT COUNT(*) as count FROM " . $this->table . $where_sql;
        $stmt = $this->conn->prepare($query);
        foreach ($params as $param => $value) {
            $stmt->bindValue(':' . $param, $value);
        }
        $stmt->execute();
        $stats['total'] = (int)($stmt->fetch()['count'] ?? 0);
        
        // Total expense amount in date range (Cash Out only)
        $exp_sql = $where_sql . " AND payment_method != 'cash_in'";
        $query = "SELECT COALESCE(SUM(amount), 0) as total, COUNT(*) as count FROM " . $this->table . $exp_sql;
        $stmt = $this->conn->prepare($query);
        foreach ($params as $param => $value) {
            $stmt->bindValue(':' . $param, $value);
        }
        $stmt->execute();
        $res = $stmt->fetch();
        $stats['total_amount'] = (float)($res['total'] ?? 0);
        $stats['total_cash_out_count'] = (int)($res['count'] ?? 0);
        $stats['total_cash_out'] = $stats['total_amount'];

        // Total cash in in date range
        $cin_sql = $where_sql . " AND payment_method = 'cash_in'";
        $query = "SELECT COALESCE(SUM(amount), 0) as total, COUNT(*) as count FROM " . $this->table . $cin_sql;
        $stmt = $this->conn->prepare($query);
        foreach ($params as $param => $value) {
            $stmt->bindValue(':' . $param, $value);
        }
        $stmt->execute();
        $resCin = $stmt->fetch();
        $stats['total_cash_in'] = (float)($resCin['total'] ?? 0);
        $stats['total_cash_in_count'] = (int)($resCin['count'] ?? 0);
        $stats['net_flow'] = $stats['total_cash_in'] - $stats['total_cash_out'];
        
        // Expenses by category in date range (Cash Out only)
        $query = "SELECT COALESCE(NULLIF(category, ''), COALESCE(NULLIF(description, ''), 'General')) as category, 
                         COUNT(*) as count, 
                         COALESCE(SUM(amount), 0) as total 
                  FROM " . $this->table . $exp_sql . " 
                  GROUP BY COALESCE(NULLIF(category, ''), COALESCE(NULLIF(description, ''), 'General')) 
                  ORDER BY total DESC";
        $stmt = $this->conn->prepare($query);
        foreach ($params as $param => $value) {
            $stmt->bindValue(':' . $param, $value);
        }
        $stmt->execute();
        $stats['by_category'] = $stmt->fetchAll();
        
        // Expenses by payment method in date range
        $query = "SELECT payment_method, COUNT(*) as count, COALESCE(SUM(amount), 0) as total 
                  FROM " . $this->table . $where_sql . " 
                  GROUP BY payment_method 
                  ORDER BY total DESC";
        $stmt = $this->conn->prepare($query);
        foreach ($params as $param => $value) {
            $stmt->bindValue(':' . $param, $value);
        }
        $stmt->execute();
        $stats['by_payment_method'] = $stmt->fetchAll();
        
        return $stats;
    }

    /**
     * Get monthly expense statistics by date range (filtered by company, Cash Out only)
     */
    public function getMonthlyExpenseStatsByDateRange($year, $month, $date_from, $date_to) {
        $companyId = $this->getCompanyId();
        $query = "SELECT 
                    COUNT(*) as expense_count,
                    COALESCE(SUM(amount), 0) as total_amount
                  FROM " . $this->table . " 
                  WHERE YEAR(expense_date) = :year 
                  AND MONTH(expense_date) = :month
                  AND DATE(expense_date) BETWEEN :date_from AND :date_to
                  AND payment_method != 'cash_in'";
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

    /**
     * Override update to ensure company_id is checked
     */
    public function update($id, $data) {
        $companyId = $this->getCompanyId();
        if ($companyId) {
            $existing = $this->find($id);
            if (!$existing || $existing['company_id'] != $companyId) {
                return false;
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
