<?php
/**
 * Expense Model
 * Tailoring Management System
 */

class Expense extends BaseModel {
    protected $table = 'expenses';

    /**
     * Get expenses with creator details
     */
    public function getExpensesWithDetails($conditions = [], $limit = null, $offset = 0) {
        $query = "SELECT e.*, u.full_name as created_by_name
                  FROM " . $this->table . " e
                  LEFT JOIN users u ON e.created_by = u.id";
        
        $params = [];
        
        if (!empty($conditions)) {
            $where_clauses = [];
            foreach ($conditions as $column => $value) {
                if (strpos($column, '.') !== false) {
                    // Handle table.column format
                    $where_clauses[] = $column . " = :" . str_replace('.', '_', $column);
                    $params[str_replace('.', '_', $column)] = $value;
                } else {
                    $where_clauses[] = "e." . $column . " = :" . $column;
                    $params[$column] = $value;
                }
            }
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
        $stats = [];
        
        // Total expenses
        $stats['total'] = $this->count();
        
        // Total expense amount
        $query = "SELECT SUM(amount) as total FROM " . $this->table;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['total_amount'] = $result['total'] ?? 0;
        
        // Expenses this month
        $this_month = date('Y-m-01');
        $query = "SELECT COUNT(*) as count, SUM(amount) as total 
                  FROM " . $this->table . " 
                  WHERE expense_date >= :this_month";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':this_month', $this_month);
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['this_month_count'] = $result['count'];
        $stats['this_month_amount'] = $result['total'] ?? 0;
        
        // Expenses by category
        $query = "SELECT category, COUNT(*) as count, SUM(amount) as total 
                  FROM " . $this->table . " 
                  GROUP BY category 
                  ORDER BY total DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $stats['by_category'] = $stmt->fetchAll();
        
        // Expenses by payment method
        $query = "SELECT payment_method, COUNT(*) as count, SUM(amount) as total 
                  FROM " . $this->table . " 
                  GROUP BY payment_method 
                  ORDER BY total DESC";
        $stmt = $this->conn->prepare($query);
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
}
?>

