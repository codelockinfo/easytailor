<?php
/**
 * Database Configuration Template
 * Tailoring Management System
 * 
 * Copy this file to database.php and update the values according to your environment
 */

class Database {
    // Database connection settings
    private $host = 'localhost';                    // Database host (usually localhost)
    private $db_name = 'tailoring_management';      // Database name
    private $username = 'root';                     // Database username
    private $password = '';                         // Database password
    private $charset = 'utf8mb4';                   // Character set
    private $port = 3306;                          // Database port (default: 3306)
    
    public $conn;

    /**
     * Get database connection
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
            die();
        }

        return $this->conn;
    }
    
    /**
     * Test database connection
     */
    public function testConnection() {
        try {
            $this->getConnection();
            return true;
        } catch(Exception $e) {
            return false;
        }
    }
}
?>
