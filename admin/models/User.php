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
        require_once __DIR__ . '/../../config/config.php';
        return get_company_id();
    }

    /**
     * Authenticate user (no company filter - needed for login)
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
        // Ensure company_id is set
        if (!isset($data['company_id'])) {
            $data['company_id'] = $this->getCompanyId();
        }

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
        $companyId = $this->getCompanyId();
        $conditions = ['role' => $role, 'status' => 'active'];
        if ($companyId) {
            $conditions['company_id'] = $companyId;
        }
        return $this->findAll($conditions);
    }

    /**
     * Get active users
     */
    public function getActiveUsers() {
        $companyId = $this->getCompanyId();
        $conditions = ['status' => 'active'];
        if ($companyId) {
            $conditions['company_id'] = $companyId;
        }
        return $this->findAll($conditions, 'full_name ASC');
    }

    /**
     * Check if username exists (globally - username must be unique across all companies)
     */
    public function usernameExists($username, $exclude_id = null) {
        // Username must be unique globally, not per company (database constraint)
        $query = "SELECT id FROM " . $this->table . " WHERE username = :username";
        if ($exclude_id) {
            $query .= " AND id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        if ($exclude_id) {
            $stmt->bindParam(':exclude_id', $exclude_id, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return $stmt->fetch() ? true : false;
    }

    /**
     * Check if email exists (globally - email must be unique across all companies)
     */
    public function emailExists($email, $exclude_id = null) {
        // Email must be unique globally, not per company (database constraint)
        $query = "SELECT id FROM " . $this->table . " WHERE email = :email";
        if ($exclude_id) {
            $query .= " AND id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
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
        $stats = [];
        
        // Total users
        $conditions = [];
        if ($companyId) {
            $conditions['company_id'] = $companyId;
        }
        $stats['total'] = $this->count($conditions);
        
        // Active users
        $conditions['status'] = 'active';
        $stats['active'] = $this->count($conditions);
        unset($conditions['status']);
        
        // Users by role
        $roles = ['admin', 'staff', 'tailor', 'cashier'];
        foreach ($roles as $role) {
            $conditions['role'] = $role;
            $conditions['status'] = 'active';
            $stats[$role] = $this->count($conditions);
            unset($conditions['role'], $conditions['status']);
        }
        
        return $stats;
    }
    
    /**
     * Get user by ID
     */
    public function getUserById($id) {
        $companyId = $this->getCompanyId();
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        $query .= " LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return $stmt->fetch();
    }

    /**
     * Get all users for a specific company
     */
    public function getCompanyUsers($company_id, $conditions = [], $order_by = null) {
        if (!$company_id) {
            return [];
        }

        $conditions['company_id'] = $company_id;
        return $this->findAll($conditions, $order_by);
    }

    /**
     * Find a user by ID within a company
     */
    public function findByIdAndCompany($id, $company_id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id AND company_id = :company_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Count users for a specific company with optional conditions
     */
    public function countByCompany($company_id, $conditions = []) {
        if (!$company_id) {
            return 0;
        }

        $conditions['company_id'] = $company_id;
        return $this->count($conditions);
    }

    /**
     * Override findAll to include company_id filter
     */
    public function findAll($conditions = [], $order_by = null, $limit = null) {
        $companyId = $this->getCompanyId();
        if ($companyId && !isset($conditions['company_id'])) {
            $conditions['company_id'] = $companyId;
        }
        return parent::findAll($conditions, $order_by, $limit);
    }

    /**
     * Override findOne to include company_id filter
     */
    public function findOne($conditions = []) {
        $companyId = $this->getCompanyId();
        if ($companyId && !isset($conditions['company_id'])) {
            $conditions['company_id'] = $companyId;
        }
        return parent::findOne($conditions);
    }

    /**
     * Override count to include company_id filter
     */
    public function count($conditions = []) {
        $companyId = $this->getCompanyId();
        if ($companyId && !isset($conditions['company_id'])) {
            $conditions['company_id'] = $companyId;
        }
        return parent::count($conditions);
    }

    /**
     * Override find to include company_id filter
     */
    public function find($id) {
        $companyId = $this->getCompanyId();
        $query = "SELECT * FROM " . $this->table . " WHERE " . $this->primary_key . " = :id";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        $query .= " LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return $stmt->fetch();
    }
}
?>

