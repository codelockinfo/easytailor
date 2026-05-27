<?php
/**
 * Customer Model
 * Tailoring Management System
 */

class Customer extends BaseModel {
    protected $table = 'measurements';

    private function getCompanyId() {
        require_once __DIR__ . '/../config/config.php';
        return get_company_id();
    }

    private function isAdmin() {
        require_once __DIR__ . '/../config/config.php';
        return get_user_role() === 'admin';
    }

    public function createCustomer($data) {
        if (!isset($data['company_id'])) {
            $data['company_id'] = $this->getCompanyId();
        }
        
        $insertData = [
            'company_id' => $data['company_id'],
            'cloth_type_id' => null,
            'name' => trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')),
            'email' => $data['email'] ?? null,
            'phone_number' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'measurement_data' => '{}',
            'notes' => $data['notes'] ?? null
        ];
        
        return $this->create($insertData);
    }

    public function searchCustomers($search_term, $limit = 20) {
        $companyId = $this->getCompanyId();
        $search_term = trim(str_replace('%20', ' ', $search_term));
        $search_lower = strtolower($search_term);
        
        $where_conditions = [];
        $params = [];
        
        $where_conditions[] = "LOWER(c.name) LIKE :name";
        $params[':name'] = '%' . $search_lower . '%';
        
        $where_conditions[] = "LOWER(c.email) LIKE :email";
        $params[':email'] = '%' . $search_lower . '%';
        
        $where_conditions[] = "c.phone_number LIKE :phone";
        $params[':phone'] = '%' . $search_term . '%';
        
        $query = "SELECT c.*, COUNT(o.id) as order_count 
                  FROM " . $this->table . " c
                  LEFT JOIN orders o ON c.id = o.measurement_id
                  WHERE (" . implode(' OR ', $where_conditions) . ")";
        
        if ($companyId) {
            $query .= " AND c.company_id = :company_id";
            $params[':company_id'] = $companyId;
        }
        
        $query .= " GROUP BY c.phone_number, c.name ORDER BY c.name LIMIT :limit";
        $params[':limit'] = $limit;
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            if ($key === ':limit' || $key === ':company_id') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return array_map([$this, 'formatCustomerRow'], $rows);
    }

    public function getCustomersWithOrderCount($limit = null) {
        $companyId = $this->getCompanyId();
        $query = "SELECT c.*, COUNT(o.id) as order_count 
                  FROM " . $this->table . " c 
                  LEFT JOIN orders o ON c.id = o.measurement_id";
        
        $where_clauses = ["1=1"];
        $params = [];
        
        if ($companyId) {
            $where_clauses[] = "c.company_id = :customer_company_id";
            $params['customer_company_id'] = $companyId;
            $where_clauses[] = "(o.company_id = :order_company_id OR o.company_id IS NULL)";
            $params['order_company_id'] = $companyId;
        }
        
        $query .= " WHERE " . implode(" AND ", $where_clauses);
        $query .= " GROUP BY c.phone_number, c.name ORDER BY c.name";
        
        if ($limit) {
            $query .= " LIMIT " . (int)$limit;
        }
        
        $stmt = $this->conn->prepare($query);
        foreach ($params as $param => $value) {
            $stmt->bindValue(':' . $param, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        
        $rows = $stmt->fetchAll();
        return array_map([$this, 'formatCustomerRow'], $rows);
    }

    public function getAllCustomers() {
        return $this->findAll();
    }

    public function getCustomerStats() {
        $companyId = $this->getCompanyId();
        $stats = [];
        
        $conditions = [];
        if ($companyId) {
            $conditions['company_id'] = $companyId;
        }
        $stats['total'] = $this->count($conditions);
        $stats['active'] = $stats['total'];
        
        $this_month = date('Y-m-01');
        $query = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE created_at >= :this_month";
        if ($companyId) {
            $query .= " AND company_id = :company_id";
        }
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':this_month', $this_month);
        if ($companyId) {
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['this_month'] = $result['count'];
        
        return $stats;
    }

    public function findByCustomerCode($customer_code) {
        if (preg_match('/^CUST0*(\d+)$/i', $customer_code, $matches)) {
            return $this->find((int)$matches[1]);
        }
        return false;
    }

    public function emailExists($email, $exclude_id = null) {
        $companyId = $this->getCompanyId();
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

    public function getCustomerOrders($customer_id, $limit = 10) {
        $companyId = $this->getCompanyId();
        
        $stmt = $this->conn->prepare("SELECT name FROM measurements WHERE id = :id");
        $stmt->execute([':id' => $customer_id]);
        $measurement = $stmt->fetch();
        $customer_name = $measurement ? $measurement['name'] : '';

        $query = "SELECT o.*, ct.name as cloth_type_name, u.full_name as tailor_name
                  FROM orders o
                  LEFT JOIN cloth_types ct ON o.cloth_type_id = ct.id
                  LEFT JOIN users u ON o.assigned_tailor_id = u.id
                  WHERE (o.measurement_id = :customer_id" . (!empty($customer_name) ? " OR o.customer_name = :customer_name" : "") . ")";
        if ($companyId) {
            $query .= " AND o.company_id = :company_id";
        }
        $query .= " ORDER BY o.created_at DESC LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':customer_id', $customer_id, PDO::PARAM_INT);
        if (!empty($customer_name)) {
            $stmt->bindValue(':customer_name', $customer_name);
        }
        if ($companyId) {
            $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

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
        $row = $stmt->fetch();
        return $row ? $this->formatCustomerRow($row) : false;
    }

    public function findAll($conditions = [], $order_by = null, $limit = null) {
        if ($order_by === 'first_name, last_name') {
            $order_by = 'name';
        }
        $companyId = $this->getCompanyId();
        if ($companyId && !isset($conditions['company_id'])) {
            $conditions['company_id'] = $companyId;
        }
        if (isset($conditions['customer_code'])) {
            $code = $conditions['customer_code'];
            if (preg_match('/^CUST0*(\d+)$/i', $code, $matches)) {
                $conditions['id'] = (int)$matches[1];
            }
            unset($conditions['customer_code']);
        }
        if (isset($conditions['phone'])) {
            $conditions['phone_number'] = $conditions['phone'];
            unset($conditions['phone']);
        }
        if (isset($conditions['status'])) {
            unset($conditions['status']);
        }
        $rows = parent::findAll($conditions, $order_by, $limit);
        if ($rows) {
            return array_map([$this, 'formatCustomerRow'], $rows);
        }
        return $rows;
    }

    public function findOne($conditions = []) {
        $companyId = $this->getCompanyId();
        if ($companyId && !isset($conditions['company_id'])) {
            $conditions['company_id'] = $companyId;
        }
        if (isset($conditions['customer_code'])) {
            $code = $conditions['customer_code'];
            if (preg_match('/^CUST0*(\d+)$/i', $code, $matches)) {
                $conditions['id'] = (int)$matches[1];
            }
            unset($conditions['customer_code']);
        }
        if (isset($conditions['phone'])) {
            $conditions['phone_number'] = $conditions['phone'];
            unset($conditions['phone']);
        }
        if (isset($conditions['status'])) {
            unset($conditions['status']);
        }
        $row = parent::findOne($conditions);
        return $row ? $this->formatCustomerRow($row) : false;
    }

    public function count($conditions = []) {
        $companyId = $this->getCompanyId();
        if ($companyId && !isset($conditions['company_id'])) {
            $conditions['company_id'] = $companyId;
        }
        if (isset($conditions['customer_code'])) {
            $code = $conditions['customer_code'];
            if (preg_match('/^CUST0*(\d+)$/i', $code, $matches)) {
                $conditions['id'] = (int)$matches[1];
            }
            unset($conditions['customer_code']);
        }
        if (isset($conditions['phone'])) {
            $conditions['phone_number'] = $conditions['phone'];
            unset($conditions['phone']);
        }
        if (isset($conditions['status'])) {
            unset($conditions['status']);
        }
        return parent::count($conditions);
    }

    public function update($id, $data) {
        $companyId = $this->getCompanyId();
        $existing = $this->find($id);
        if (!$existing) {
            return false;
        }
        if ($companyId && $existing['company_id'] != $companyId) {
            return false;
        }
        
        $updateData = [];
        if (isset($data['first_name']) || isset($data['last_name'])) {
            $first = $data['first_name'] ?? ($existing['first_name'] ?? '');
            $last = $data['last_name'] ?? ($existing['last_name'] ?? '');
            $updateData['name'] = trim($first . ' ' . $last);
        }
        if (isset($data['email'])) {
            $updateData['email'] = $data['email'];
        }
        if (isset($data['phone'])) {
            $updateData['phone_number'] = $data['phone'];
        }
        if (isset($data['address'])) {
            $updateData['address'] = $data['address'];
        }
        if (isset($data['date_of_birth'])) {
            $updateData['date_of_birth'] = $data['date_of_birth'];
        }
        if (isset($data['notes'])) {
            $updateData['notes'] = $data['notes'];
        }
        
        if (empty($updateData)) {
            return true;
        }
        
        return parent::update($id, $updateData);
    }

    public function delete($id) {
        $companyId = $this->getCompanyId();
        $existing = $this->find($id);
        if (!$existing) {
            return false;
        }
        if ($companyId && $existing['company_id'] != $companyId) {
            return false;
        }
        
        return parent::delete($id);
    }

    public function formatCustomerRow($row) {
        if (!$row) return $row;
        
        $name = trim($row['name'] ?? '');
        $parts = explode(' ', $name, 2);
        $row['first_name'] = $parts[0] ?? '';
        $row['last_name'] = $parts[1] ?? '';
        
        $row['phone'] = $row['phone_number'] ?? '';
        $row['customer_code'] = 'CUST' . str_pad($row['id'], 6, '0', STR_PAD_LEFT);
        $row['status'] = 'active';
        
        return $row;
    }
}
?>
