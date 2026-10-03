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
        $companyId = $this->create($data);
        if ($companyId) {
            require_once __DIR__ . '/../../helpers/DefaultDataHelper.php';
            DefaultDataHelper::createDefaultClothTypes($companyId);
        }
        return $companyId;
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
        
        // Count users excluding admins for subscription limits
        $stmt = $userModel->query("SELECT COUNT(*) as count FROM users WHERE company_id = :company_id AND role != 'admin'", [':company_id' => $company_id]);
        $result = $stmt->fetch();
        $stats['total_users'] = (int)($result['count'] ?? 0);
        
        return $stats;
    }

    /**
     * Get active companies
     */
    public function getActiveCompanies() {
        return $this->findAll(['status' => 'active'], 'company_name ASC');
    }

    /**
     * Calculate profile completion percentage (0-100)
     */
    public static function calculateProfileCompletion($company) {
        if (!$company || !is_array($company)) {
            return 0;
        }

        $progress = 0;
        if (!empty(trim((string)($company['owner_name'] ?? '')))) $progress += 10;
        if (!empty(trim((string)($company['business_email'] ?? '')))) $progress += 10;
        if (!empty(trim((string)($company['business_phone'] ?? '')))) $progress += 10;
        if (!empty(trim((string)($company['company_name'] ?? '')))) $progress += 15;
        if (!empty(trim((string)($company['business_address'] ?? '')))) $progress += 15;
        if (!empty(trim((string)($company['city'] ?? '')))) $progress += 10;
        if (!empty(trim((string)($company['state'] ?? '')))) $progress += 10;
        if (!empty(trim((string)($company['postal_code'] ?? '')))) $progress += 10;
        if (!empty(trim((string)($company['logo'] ?? '')))) $progress += 10;

        return min(100, $progress);
    }

    /**
     * Check if company profile is 100% complete
     */
    public static function isProfileComplete($company) {
        return self::calculateProfileCompletion($company) >= 100;
    }

    /**
     * Get list of missing/incomplete profile fields
     */
    public static function getMissingProfileFields($company) {
        if (!$company || !is_array($company)) {
            return [
                'company_name' => 'Tailor Shop Name',
                'owner_name' => 'Owner Name',
                'business_email' => 'Business Email',
                'business_phone' => 'Business Phone',
                'business_address' => 'Address',
                'city' => 'City',
                'state' => 'State',
                'postal_code' => 'PIN Code',
                'logo' => 'Business Logo'
            ];
        }

        $missing = [];
        if (empty(trim((string)($company['company_name'] ?? '')))) $missing['company_name'] = 'Tailor Shop Name';
        if (empty(trim((string)($company['owner_name'] ?? '')))) $missing['owner_name'] = 'Owner Name';
        if (empty(trim((string)($company['business_email'] ?? '')))) $missing['business_email'] = 'Business Email';
        if (empty(trim((string)($company['business_phone'] ?? '')))) $missing['business_phone'] = 'Business Phone';
        if (empty(trim((string)($company['business_address'] ?? '')))) $missing['business_address'] = 'Address';
        if (empty(trim((string)($company['city'] ?? '')))) $missing['city'] = 'City';
        if (empty(trim((string)($company['state'] ?? '')))) $missing['state'] = 'State';
        if (empty(trim((string)($company['postal_code'] ?? '')))) $missing['postal_code'] = 'PIN Code';
        if (empty(trim((string)($company['logo'] ?? '')))) $missing['logo'] = 'Business Logo';

        return $missing;
    }

    /**
     * Update company logo
     */
    public function updateLogo($company_id, $logo_path) {
        $result = $this->update($company_id, ['logo' => $logo_path]);
        $updatedCompany = $this->find($company_id);
        if ($updatedCompany) {
            $isComplete = self::isProfileComplete($updatedCompany);
            $this->update($company_id, ['show_on_listing' => $isComplete ? 1 : 0]);
        }
        return $result;
    }
}
?>

