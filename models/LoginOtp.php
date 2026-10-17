<?php

require_once __DIR__ . '/BaseModel.php';

class LoginOtp extends BaseModel {
    protected $table = 'login_otps';
    private static $tableChecked = false;
    public function __construct() {
        parent::__construct();
        $this->ensureTableExists();
    }

    public function ensureTableExists() {
        if (self::$tableChecked) {
            return;
        }

        try {
            $this->conn->query("SELECT 1 FROM {$this->table} LIMIT 1");
            self::$tableChecked = true;
        } catch (PDOException $e) {
            $createTableSql = "
                CREATE TABLE IF NOT EXISTS `{$this->table}` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `user_id` int(11) NOT NULL,
                    `email` varchar(100) NOT NULL,
                    `otp_code` varchar(6) NOT NULL,
                    `token` varchar(64) NOT NULL,
                    `expires_at` timestamp NOT NULL,
                    `attempts` int(11) NOT NULL DEFAULT 0,
                    `used` tinyint(1) NOT NULL DEFAULT 0,
                    `ip_address` varchar(45) DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_email_used` (`email`, `used`),
                    KEY `idx_token` (`token`),
                    KEY `idx_expires` (`expires_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";
            $this->conn->exec($createTableSql);
            self::$tableChecked = true;
        }
    }

    /**
     * Check if a recent OTP was requested within cooldown period (e.g. 60 seconds)
     * @return int Remaining cooldown seconds, or 0 if cooldown passed
     */
    public function getRecentRequestCooldown($email, $cooldownSeconds = 60) {
        $this->ensureTableExists();
        $stmt = $this->conn->prepare("
            SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) AS elapsed
            FROM {$this->table}
            WHERE email = :email AND used = 0
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['elapsed'])) {
            $elapsed = (int)$row['elapsed'];
            if ($elapsed < $cooldownSeconds) {
                return $cooldownSeconds - $elapsed;
            }
        }
        return 0;
    }
    public function invalidatePreviousOtps($email) {
        $this->ensureTableExists();
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET used = 2 WHERE email = :email AND used = 0");
        return $stmt->execute([':email' => $email]);
    }

    public function createOtp($userId, $email, $code, $token, $expiresAt, $ipAddress = null) {
        $this->ensureTableExists();
        $this->invalidatePreviousOtps($email);

        $stmt = $this->conn->prepare("
            INSERT INTO {$this->table} (user_id, email, otp_code, token, expires_at, attempts, used, ip_address, created_at)
            VALUES (:uid, :email, :code, :token, :expires, 0, 0, :ip, NOW())
        ");
        $stmt->execute([
            ':uid' => $userId,
            ':email' => $email,
            ':code' => $code,
            ':token' => $token,
            ':expires' => $expiresAt,
            ':ip' => $ipAddress
        ]);
        return $this->conn->lastInsertId();
    }
    public function findLatestActiveOtp($email) {
        $this->ensureTableExists();
        $stmt = $this->conn->prepare("
            SELECT * FROM {$this->table}
            WHERE email = :email AND used = 0
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function incrementAttempts($id) {
        $this->ensureTableExists();
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET attempts = attempts + 1 WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
    public function markAsUsed($id) {
        $this->ensureTableExists();
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET used = 1 WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
    public function invalidateOtp($id) {
        $this->ensureTableExists();
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET used = 2 WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
