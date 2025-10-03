<?php
/**
 * Company Model
 * Tailoring Management System - Multi-Tenant
 */

class Company extends BaseModel {
    protected $table = 'companies';

    /**
     * Create new company (for registration)
     */
    public function createCompany($data) {
        return $this->create($data);
    }

    /**
     * Check if business email exists
     */
    public function emailExists($email, $exclude_id = null) {
        $query = "SELECT id FROM " . $this->table . " WHERE business_email = :email";
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
     * Get company statistics
     */
    public function getCompanyStats($company_id) {
        $stats = [];
        
        // Get counts from related tables
        require_once __DIR__ . '/Customer.php';
        require_once __DIR__ . '/Order.php';
        require_once __DIR__ . '/User.php';
        
        $customerModel = new Customer();
        $orderModel = new Order();
        $userModel = new User();
        
        $stats['total_customers'] = $customerModel->count(['company_id' => $company_id]);
        $stats['total_orders'] = $orderModel->count(['company_id' => $company_id]);
        $stats['total_users'] = $userModel->count(['company_id' => $company_id]);
        
        return $stats;
    }

    /**
     * Get active companies
     */
    public function getActiveCompanies() {
        return $this->findAll(['status' => 'active'], 'company_name ASC');
    }

    /**
     * Update company logo
     */
    public function updateLogo($company_id, $logo_path) {
        return $this->update($company_id, ['logo' => $logo_path]);
    }
}
?>

