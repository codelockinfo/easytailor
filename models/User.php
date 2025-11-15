<?php
/**
 * User Model
 * Tailoring Management System
 */

class User extends BaseModel {
    protected $table = 'users';

    /**
     * Get company ID from session
     */
    private function getCompanyId() {
        require_once __DIR__ . '/../config/config.php';
        return get_company_id();
    }

    /**
     * Check if current user is admin
     */
    private function isAdmin() {
        require_once __DIR__ . '/../config/config.php';
        return get_user_role() === 'admin';
    }

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
        $companyId = $this->getCompanyId();
        $isAdmin = $this->isAdmin();
        $query = "SELECT id FROM " . $this->table . " WHERE username = :username";
        if (!$isAdmin && $companyId) {
            $query .= " AND company_id = :company_id";
        }
        if ($exclude_id) {
            $query .= " AND id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        if (!$isAdmin && $companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        if ($exclude_id) {
            $stmt->bindParam(':exclude_id', $exclude_id, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return $stmt->fetch() ? true : false;
    }

    /**
     * Check if email exists
     */
    public function emailExists($email, $exclude_id = null) {
        $companyId = $this->getCompanyId();
        $isAdmin = $this->isAdmin();
        $query = "SELECT id FROM " . $this->table . " WHERE email = :email";
        if (!$isAdmin && $companyId) {
            $query .= " AND company_id = :company_id";
        }
        if ($exclude_id) {
            $query .= " AND id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        if (!$isAdmin && $companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        if ($exclude_id) {
            $stmt->bindParam(':exclude_id', $exclude_id, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return $stmt->fetch() ? true : false;
    }

    /**
     * Get user statistics
     */
    public function getUserStats() {
        $companyId = $this->getCompanyId();
        $isAdmin = $this->isAdmin();
        $stats = [];
        
        // Total users
        $conditions = [];
        if (!$isAdmin && $companyId) {
            $conditions['company_id'] = $companyId;
        }
        $stats['total'] = $this->count($conditions);
        
        // Active users
        $conditions['status'] = 'active';
        $stats['active'] = $this->count($conditions);
        
        // Users by role
        $roles = ['admin', 'staff', 'tailor', 'cashier'];
        foreach ($roles as $role) {
            $conditions['role'] = $role;
            $stats[$role] = $this->count($conditions);
            unset($conditions['role']);
        }
        
        return $stats;
    }

    /**
     * Override find to include company_id filter for non-admin users
     */
    public function find($id) {
        $companyId = $this->getCompanyId();
        $isAdmin = $this->isAdmin();
        $query = "SELECT * FROM " . $this->table . " WHERE " . $this->primary_key . " = :id";
        if (!$isAdmin && $companyId) {
            $query .= " AND company_id = :company_id";
        }
        $query .= " LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        if (!$isAdmin && $companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return $stmt->fetch();
    }

    /**
     * Override findAll to include company_id filter for non-admin users
     */
    public function findAll($conditions = [], $order_by = null, $limit = null) {
        $companyId = $this->getCompanyId();
        $isAdmin = $this->isAdmin();
        if (!$isAdmin && $companyId && !isset($conditions['company_id'])) {
            $conditions['company_id'] = $companyId;
        }
        return parent::findAll($conditions, $order_by, $limit);
    }

    /**
     * Override findOne to include company_id filter for non-admin users
     */
    public function findOne($conditions = []) {
        $companyId = $this->getCompanyId();
        $isAdmin = $this->isAdmin();
        if (!$isAdmin && $companyId && !isset($conditions['company_id'])) {
            $conditions['company_id'] = $companyId;
        }
        return parent::findOne($conditions);
    }

    /**
     * Override count to include company_id filter for non-admin users
     */
    public function count($conditions = []) {
        $companyId = $this->getCompanyId();
        $isAdmin = $this->isAdmin();
        if (!$isAdmin && $companyId && !isset($conditions['company_id'])) {
            $conditions['company_id'] = $companyId;
        }
        return parent::count($conditions);
    }

    /**
     * Override update to ensure company_id is checked for non-admin users
     */
    public function update($id, $data) {
        $companyId = $this->getCompanyId();
        $isAdmin = $this->isAdmin();
        if (!$isAdmin && $companyId) {
            $existing = $this->find($id);
            if (!$existing || $existing['company_id'] != $companyId) {
                return false;
            }
        }
        return parent::update($id, $data);
    }

    /**
     * Override delete to ensure company_id is checked for non-admin users
     */
    public function delete($id) {
        $companyId = $this->getCompanyId();
        $isAdmin = $this->isAdmin();
        if (!$isAdmin && $companyId) {
            $existing = $this->find($id);
            if (!$existing || $existing['company_id'] != $companyId) {
                return false;
            }
        }
        return parent::delete($id);
    }
}
?>

