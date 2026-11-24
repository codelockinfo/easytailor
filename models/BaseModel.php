<?php
/**
 * Base Model Class
 * Tailoring Management System
 */

require_once __DIR__ . '/../config/database.php';

class BaseModel {
    public $conn;
    protected $table;
    protected $primary_key = 'id';

    public function __construct($db = null) {
        if ($db) {
            $this->conn = $db;
        } else {
            $database = new Database();
            $this->conn = $database->getConnection();
        }
    }

    /**
     * Get database connection with auto-reconnect fallback
     */
    public function getConn() {
        if (!$this->conn) {
            $database = new Database();
            $this->conn = $database->getConnection();
        }
        return $this->conn;
    }

    /**
     * Find record by ID
     */
    public function find($id) {
        $conn = $this->getConn();
        if (!$conn) {
            error_log("BaseModel::find - database connection unavailable for table: {$this->table}");
            return false;
        }
        $query = "SELECT * FROM " . $this->table . " WHERE " . $this->primary_key . " = :id LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch();
    }

    /**
     * Find all records with optional conditions
     */
    public function findAll($conditions = [], $order_by = null, $limit = null) {
        $conn = $this->getConn();
        if (!$conn) {
            error_log("BaseModel::findAll - database connection unavailable for table: {$this->table}");
            return [];
        }
        $query = "SELECT * FROM " . $this->table;
        $params = [];

        if (!empty($conditions)) {
            $where_clauses = [];
            foreach ($conditions as $column => $value) {
                $where_clauses[] = $column . " = :" . $column;
                $params[$column] = $value;
            }
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }

        if ($order_by) {
            $query .= " ORDER BY " . $order_by;
        }

        if ($limit) {
            $query .= " LIMIT " . $limit;
        }

        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    /**
     * Find one record with conditions
     */
    public function findOne($conditions = []) {
        $conn = $this->getConn();
        if (!$conn) {
            error_log("BaseModel::findOne - database connection unavailable for table: {$this->table}");
            return false;
        }
        $query = "SELECT * FROM " . $this->table;
        $params = [];

        if (!empty($conditions)) {
            $where_clauses = [];
            foreach ($conditions as $column => $value) {
                $where_clauses[] = $column . " = :" . $column;
                $params[$column] = $value;
            }
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }

        $query .= " LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetch();
    }

    /**
     * Create new record
     */
    public function create($data) {
        $conn = $this->getConn();
        if (!$conn) {
            error_log("BaseModel::create - database connection unavailable for table: {$this->table}");
            return false;
        }
        $columns = array_keys($data);
        $placeholders = array_map(function($col) { return ':' . $col; }, $columns);
        
        $query = "INSERT INTO " . $this->table . " (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        try {
            $stmt = $conn->prepare($query);
            
            foreach ($data as $column => $value) {
                $stmt->bindValue(':' . $column, $value);
            }
            
            if ($stmt->execute()) {
                return $conn->lastInsertId();
            }
            
            return false;
        } catch (PDOException $e) {
            throw $e;
        }
    }

    /**
     * Update record
     */
    public function update($id, $data) {
        $conn = $this->getConn();
        if (!$conn) {
            error_log("BaseModel::update - database connection unavailable for table: {$this->table}");
            return false;
        }
        if (empty($data)) {
            error_log("BaseModel::update called with empty data array for table: {$this->table}, id: $id");
            return false;
        }
        
        $set_clauses = [];
        foreach ($data as $column => $value) {
            $set_clauses[] = $column . " = :" . $column;
        }
        
        $query = "UPDATE " . $this->table . " SET " . implode(', ', $set_clauses) . " WHERE " . $this->primary_key . " = :id";
        
        try {
            $stmt = $conn->prepare($query);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            
            foreach ($data as $column => $value) {
                // Handle null values
                if ($value === null) {
                    $stmt->bindValue(':' . $column, null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':' . $column, $value);
                }
            }
            
            $result = $stmt->execute();
            
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                error_log("BaseModel::update failed for table: {$this->table}, id: $id. Error: " . print_r($errorInfo, true));
            } else {
                $rowCount = $stmt->rowCount();
                if ($rowCount === 0) {
                    error_log("BaseModel::update executed but no rows affected for table: {$this->table}, id: $id. This might mean the record doesn't exist.");
                } else {
                    error_log("BaseModel::update successful for table: {$this->table}, id: $id, rows affected: $rowCount");
                }
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("BaseModel::update PDO Exception for table: {$this->table}, id: $id. Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete record
     */
    public function delete($id) {
        $conn = $this->getConn();
        if (!$conn) {
            error_log("BaseModel::delete - database connection unavailable for table: {$this->table}");
            return false;
        }
        $query = "DELETE FROM " . $this->table . " WHERE " . $this->primary_key . " = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }

    /**
     * Count records with optional conditions
     */
    public function count($conditions = []) {
        $conn = $this->getConn();
        if (!$conn) {
            error_log("BaseModel::count - database connection unavailable for table: {$this->table}");
            return 0;
        }
        $query = "SELECT COUNT(*) as count FROM " . $this->table;
        $params = [];

        if (!empty($conditions)) {
            $where_clauses = [];
            foreach ($conditions as $column => $value) {
                $where_clauses[] = $column . " = :" . $column;
                $params[$column] = $value;
            }
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }

        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    }

    /**
     * Execute custom query
     */
    public function query($sql, $params = []) {
        $conn = $this->getConn();
        if (!$conn) {
            error_log("BaseModel::query - database connection unavailable for table: {$this->table}");
            return false;
        }
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Begin transaction
     */
    public function beginTransaction() {
        $conn = $this->getConn();
        return $conn ? $conn->beginTransaction() : false;
    }

    /**
     * Commit transaction
     */
    public function commit() {
        $conn = $this->getConn();
        return $conn ? $conn->commit() : false;
    }

    /**
     * Rollback transaction
     */
    public function rollback() {
        $conn = $this->getConn();
        return $conn ? $conn->rollback() : false;
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId() {
        $conn = $this->getConn();
        return $conn ? $conn->lastInsertId() : false;
    }
}
?>

