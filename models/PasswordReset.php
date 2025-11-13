<?php
/**
 * Password Reset Model
 * Tailoring Management System
 */

class PasswordReset extends BaseModel {
    protected $table = 'password_resets';
    private static $tableChecked = false;

    private function ensureTableExists() {
        if (self::$tableChecked) {
            return;
        }

        try {
            $this->query("SELECT 1 FROM {$this->table} LIMIT 1");
            self::$tableChecked = true;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), '42S02') === false) {
                throw $e;
            }

            $createTableSql = "
                CREATE TABLE IF NOT EXISTS `{$this->table}` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `email` varchar(100) NOT NULL,
                    `code` varchar(6) NOT NULL,
                    `token` varchar(64) NOT NULL,
                    `expires_at` timestamp NOT NULL,
                    `used` tinyint(1) NOT NULL DEFAULT 0,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `email` (`email`),
                    KEY `token` (`token`),
                    KEY `expires_at` (`expires_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";

            $this->query($createTableSql);
            $indexStmt = $this->query("SHOW INDEX FROM {$this->table} WHERE Key_name = 'idx_email_code'");
            if (!$indexStmt->fetch()) {
                $this->query("CREATE INDEX idx_email_code ON {$this->table}(email, code, used)");
            }
            self::$tableChecked = true;
        }
    }

    /**
     * Create password reset request
     */
    public function createResetRequest($email, $code, $token, $expires_at) {
        $this->ensureTableExists();
        return $this->create([
            'email' => $email,
            'code' => $code,
            'token' => $token,
            'expires_at' => $expires_at
        ]);
    }

    /**
     * Find valid reset request by email and code
     */
    public function findByEmailAndCode($email, $code) {
        $this->ensureTableExists();
        $query = "SELECT id, token, expires_at FROM " . $this->table . " 
                  WHERE email = :email AND code = :code AND used = 0 
                  ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->query($query, [
            'email' => $email,
            'code' => $code
        ]);
        return $stmt->fetch();
    }

    /**
     * Find valid reset request by token
     */
    public function findByToken($token) {
        $this->ensureTableExists();
        $query = "SELECT id, email, expires_at FROM " . $this->table . " 
                  WHERE token = :token AND used = 0 
                  ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->query($query, ['token' => $token]);
        return $stmt->fetch();
    }

    /**
     * Mark reset request as used
     */
    public function markAsUsed($id) {
        $this->ensureTableExists();
        return $this->update($id, ['used' => 1]);
    }

    /**
     * Delete expired reset requests
     */
    public function deleteExpired() {
        $this->ensureTableExists();
        $query = "DELETE FROM " . $this->table . " WHERE expires_at < NOW()";
        return $this->query($query);
    }

    /**
     * Get user by email
     */
    public function getUserByEmail($email) {
        $this->ensureTableExists();
        $query = "SELECT id, full_name, email FROM users WHERE email = :email AND status = 'active' LIMIT 1";
        $stmt = $this->query($query, ['email' => $email]);
        return $stmt->fetch();
    }
}
?>

