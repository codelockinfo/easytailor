<?php
/**
 * User Model
 * Tailoring Management System
 */

class User extends BaseModel {
    protected $table = 'users';

    /**
     * Authenticate user
     */
    public function authenticate($username, $password) {
        $query = "SELECT * FROM " . $this->table . " WHERE (username = :username OR email = :email) AND status = 'active' LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $username);
        $stmt->execute();
        
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        
        return false;
    }

    /**
     * Create new user
     */
    public function createUser($data) {
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        return $this->create($data);
    }

    /**
     * Update user password
     */
    public function updatePassword($id, $new_password) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        return $this->update($id, ['password' => $hashed_password]);
    }

    /**
     * Get users by role
     */
    public function getUsersByRole($role) {
        return $this->findAll(['role' => $role, 'status' => 'active']);
    }

    /**
     * Get active users
     */
    public function getActiveUsers() {
        return $this->findAll(['status' => 'active'], 'full_name ASC');
    }

    /**
     * Check if username exists
     */
    public function usernameExists($username, $exclude_id = null) {
        $query = "SELECT id FROM " . $this->table . " WHERE username = :username";
        if ($exclude_id) {
            $query .= " AND id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        if ($exclude_id) {
            $stmt->bindParam(':exclude_id', $exclude_id);
        }
        $stmt->execute();
        
        return $stmt->fetch() ? true : false;
    }

    /**
     * Check if email exists
     */
    public function emailExists($email, $exclude_id = null) {
        $query = "SELECT id FROM " . $this->table . " WHERE email = :email";
        if ($exclude_id) {
            $query .= " AND id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        if ($exclude_id) {
            $stmt->bindParam(':exclude_id', $exclude_id);
        }
        $stmt->execute();
        
        return $stmt->fetch() ? true : false;
    }

    /**
     * Get user statistics
     */
    public function getUserStats() {
        $stats = [];
        
        // Total users
        $stats['total'] = $this->count();
        
        // Active users
        $stats['active'] = $this->count(['status' => 'active']);
        
        // Users by role
        $roles = ['admin', 'staff', 'tailor', 'cashier'];
        foreach ($roles as $role) {
            $stats[$role] = $this->count(['role' => $role, 'status' => 'active']);
        }
        
        return $stats;
    }
}
?>

