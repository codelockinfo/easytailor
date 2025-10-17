<?php
/**
 * Password Reset Model
 * Tailoring Management System
 */

class PasswordReset extends BaseModel {
    protected $table = 'password_resets';

    /**
     * Create password reset request
     */
    public function createResetRequest($email, $code, $token, $expires_at) {
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
        return $this->update($id, ['used' => 1]);
    }

    /**
     * Delete expired reset requests
     */
    public function deleteExpired() {
        $query = "DELETE FROM " . $this->table . " WHERE expires_at < NOW()";
        return $this->query($query);
    }

    /**
     * Get user by email
     */
    public function getUserByEmail($email) {
        $query = "SELECT id, full_name, email FROM users WHERE email = :email AND status = 'active' LIMIT 1";
        $stmt = $this->query($query, ['email' => $email]);
        return $stmt->fetch();
    }
}
?>

