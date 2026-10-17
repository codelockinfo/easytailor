<?php

class User extends BaseModel {
    protected $table = 'users';

    private function getCompanyId() {
        require_once __DIR__ . '/../config/config.php';
        return get_company_id();
    }
    private function isAdmin() {
        require_once __DIR__ . '/../config/config.php';
        return get_user_role() === 'admin';
    }
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
    public function getActiveUserByEmail($email) {
        $query = "SELECT * FROM " . $this->table . " WHERE email = :email AND status = 'active' LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->fetch();
    }
    public function createUser($data) {
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        return $this->create($data);
    }
    public function updatePassword($id, $new_password) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        return $this->update($id, ['password' => $hashed_password]);
    }
    public function getUsersByRole($role) {
        return $this->findAll(['role' => $role, 'status' => 'active']);
    }
    public function getActiveUsers() {
        return $this->findAll(['status' => 'active'], 'full_name ASC');
    }
    public function usernameExists($username, $exclude_id = null) {
        $companyId = $this->getCompanyId();
        $isAdmin = $this->isAdmin();
        $query = "SELECT id FROM " . $this->table . " WHERE username = :username";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        if ($exclude_id) {
            $query .= " AND id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        if ($exclude_id) {
            $stmt->bindParam(':exclude_id', $exclude_id, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return $stmt->fetch() ? true : false;
    }
    public function emailExists($email, $exclude_id = null) {
        $companyId = $this->getCompanyId();
        $isAdmin = $this->isAdmin();
        $query = "SELECT id FROM " . $this->table . " WHERE email = :email";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        if ($exclude_id) {
            $query .= " AND id != :exclude_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        if ($exclude_id) {
            $stmt->bindParam(':exclude_id', $exclude_id, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return $stmt->fetch() ? true : false;
    }
    public function getUserStats() {
        $companyId = $this->getCompanyId();
        $isAdmin = $this->isAdmin();
        $stats = [];
        $conditions = [];
        if ($companyId) {
            $conditions['company_id'] = $companyId;
        }
        $stats['total'] = $this->count($conditions);
        
        $conditions['status'] = 'active';
        $stats['active'] = $this->count($conditions);
        
        $roles = ['admin', 'staff', 'tailor', 'cashier'];
        foreach ($roles as $role) {
            $conditions['role'] = $role;
            $stats[$role] = $this->count($conditions);
            unset($conditions['role']);
        }
        
        return $stats;
    }
    public function find($id) {
        $companyId = $this->getCompanyId();
        $isAdmin = $this->isAdmin();
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
    public function findAll($conditions = [], $order_by = null, $limit = null) {
        $companyId = $this->getCompanyId();
        $isAdmin = $this->isAdmin();
        if ($companyId && !isset($conditions['company_id'])) {
            $conditions['company_id'] = $companyId;
        }
        return parent::findAll($conditions, $order_by, $limit);
    }
    public function findOne($conditions = []) {
        $companyId = $this->getCompanyId();
        $isAdmin = $this->isAdmin();
        if ($companyId && !isset($conditions['company_id'])) {
            $conditions['company_id'] = $companyId;
        }
        return parent::findOne($conditions);
    }
    public function count($conditions = []) {
        $companyId = $this->getCompanyId();
        $isAdmin = $this->isAdmin();
        if ($companyId && !isset($conditions['company_id'])) {
            $conditions['company_id'] = $companyId;
        }
        return parent::count($conditions);
    }
    public function update($id, $data) {
        $companyId = $this->getCompanyId();
        $isAdmin = $this->isAdmin();
        if ($companyId) {
            $existing = $this->find($id);
            if (!$existing || $existing['company_id'] != $companyId) {
                return false;
            }
        }
        return parent::update($id, $data);
    }
    public function delete($id) {
        $companyId = $this->getCompanyId();
        $isAdmin = $this->isAdmin();
        if ($companyId) {
            $existing = $this->find($id);
            if (!$existing || $existing['company_id'] != $companyId) {
                return false;
            }
        }
        return parent::delete($id);
    }
}
?>

