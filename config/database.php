<?php
/**
 * Database Configuration
 * Tailoring Management System
 * Auto-detects environment (local/live) and uses appropriate credentials
 */

class Database {
    private static $sharedConn = null;
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
            $this->host = 'localhost';
            $this->db_name = 'u402017191_tailorpro';
            $this->username = 'u402017191_tailorpro';
            $this->password = 'Tailorpro@99';
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
            '192.168.',
            '10.',
            '172.',
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
     * Get database connection (Singleton shared connection per request)
     */
    public function getConnection() {
        if (self::$sharedConn !== null) {
            $this->conn = self::$sharedConn;
            return $this->conn;
        }

        $this->conn = null;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            self::$sharedConn = $this->conn;
        } catch(PDOException $exception) {
            // Fallback: If 'localhost' socket connection fails on Linux (e.g. 2002 Operation not permitted),
            // attempt TCP connection via 127.0.0.1
            if ($this->host === 'localhost') {
                try {
                    $dsnFallback = "mysql:host=127.0.0.1;port=3306;dbname=" . $this->db_name . ";charset=" . $this->charset;
                    $this->conn = new PDO($dsnFallback, $this->username, $this->password, $options);
                    self::$sharedConn = $this->conn;
                    return $this->conn;
                } catch(PDOException $fallbackEx) {
                    error_log("Database TCP fallback error: " . $fallbackEx->getMessage());
                }
            }
            error_log("Database Connection error: " . $exception->getMessage());
        }

        return $this->conn;
    }

    /**
     * Reset shared connection (useful if connection was dropped)
     */
    public static function resetConnection() {
        self::$sharedConn = null;
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

