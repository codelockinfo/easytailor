<?php
/**
 * Database Configuration Template
 * Tailoring Management System
 * 
 * This template shows the auto-detection pattern for local vs live environments
 * The actual database.php file uses this pattern to automatically switch credentials
 */

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset = 'utf8mb4';
    public $conn;

    /**
     * Constructor - Automatically detect environment and set credentials
     */
    public function __construct() {
        // Detect if running on local or live server
        $isLocal = $this->isLocalEnvironment();
        
        if ($isLocal) {
            // Local/Development Environment
            $this->host = 'localhost';
            $this->db_name = 'tailoring_management';
            $this->username = 'root';
            $this->password = '';
        } else {
            // Live/Production Environment
            // UPDATE THESE WITH YOUR LIVE SERVER CREDENTIALS
            $this->host = 'localhost';
            $this->db_name = 'your_live_database_name';
            $this->username = 'your_live_database_user';
            $this->password = 'your_live_database_password';
        }
    }

    /**
     * Detect if running on local environment
     * @return bool
     */
    private function isLocalEnvironment() {
        // Check multiple indicators for local environment
        $serverName = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // List of local indicators
        $localIndicators = [
            'localhost',
            '127.0.0.1',
            '::1',
            'local.test',
            '.local',
            'wamp',
            'xampp'
        ];
        
        // Check if server name contains any local indicator
        foreach ($localIndicators as $indicator) {
            if (stripos($serverName, $indicator) !== false) {
                return true;
            }
        }
        
        // Check if IP is in local range
        if (isset($_SERVER['SERVER_ADDR'])) {
            $serverIp = $_SERVER['SERVER_ADDR'];
            if (strpos($serverIp, '192.168.') === 0 || 
                strpos($serverIp, '10.') === 0 || 
                $serverIp === '127.0.0.1' || 
                $serverIp === '::1') {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get database connection
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }
    
    /**
     * Get current environment name (for debugging)
     * @return string
     */
    public function getEnvironment() {
        return $this->isLocalEnvironment() ? 'local' : 'live';
    }
}
?>
