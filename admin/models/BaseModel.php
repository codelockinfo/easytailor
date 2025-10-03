<?php
/**
 * Base Model Class
 * Tailoring Management System
 */

require_once __DIR__ . '/../../config/database.php';

class BaseModel {
    protected $conn;
    protected $table;
    protected $primary_key = 'id';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * Find record by ID
     */
    public function find($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE " . $this->primary_key . " = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch();
    }

    /**
     * Find all records with optional conditions
     */
    public function findAll($conditions = [], $order_by = null, $limit = null) {
        $query = "SELECT * FROM " . $this->table;
        $params = [];

        if (!empty($conditions)) {
            $where_clauses = [];
            foreach ($conditions as $column => $value) {
                $where_clauses[] = $column . " = :" . $column;
                $params[':' . $column] = $value;
            }
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }

        if ($order_by) {
            $query .= " ORDER BY " . $order_by;
        }

        if ($limit) {
            $query .= " LIMIT " . $limit;
        }

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    /**
     * Find one record with conditions
     */
    public function findOne($conditions = []) {
        $query = "SELECT * FROM " . $this->table;
        $params = [];

        if (!empty($conditions)) {
            $where_clauses = [];
            foreach ($conditions as $column => $value) {
                $where_clauses[] = $column . " = :" . $column;
                $params[':' . $column] = $value;
            }
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }

        $query .= " LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetch();
    }

    /**
     * Create new record
     */
    public function create($data) {
        $columns = array_keys($data);
        $placeholders = array_map(function($col) { return ':' . $col; }, $columns);
        
        $query = "INSERT INTO " . $this->table . " (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($data as $column => $value) {
            $stmt->bindValue(':' . $column, $value);
        }
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        
        return false;
    }

    /**
     * Update record
     */
    public function update($id, $data) {
        $set_clauses = [];
        foreach ($data as $column => $value) {
            $set_clauses[] = $column . " = :" . $column;
        }
        
        $query = "UPDATE " . $this->table . " SET " . implode(', ', $set_clauses) . " WHERE " . $this->primary_key . " = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', $id);
        
        foreach ($data as $column => $value) {
            $stmt->bindValue(':' . $column, $value);
        }
        
        return $stmt->execute();
    }

    /**
     * Delete record
     */
    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE " . $this->primary_key . " = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }

    /**
     * Count records with optional conditions
     */
    public function count($conditions = []) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table;
        $params = [];

        if (!empty($conditions)) {
            $where_clauses = [];
            foreach ($conditions as $column => $value) {
                $where_clauses[] = $column . " = :" . $column;
                $params[':' . $column] = $value;
            }
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        
        $result = $stmt->fetch();
        return $result['count'];
    }

    /**
     * Execute custom query
     */
    public function query($sql, $params = []) {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit() {
        return $this->conn->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->conn->rollback();
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }
}
?>

