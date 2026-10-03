<?php
/**
 * Company Model
 * Tailoring Management System - Multi-Tenant
 */

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/CompanyReview.php';

class Company extends BaseModel {
    protected $table = 'companies';

    /**
     * Create new company (for registration)
     */
    public function createCompany($data) {
        $companyId = $this->create($data);
        if ($companyId) {
            require_once __DIR__ . '/../helpers/DefaultDataHelper.php';
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
     * SQL condition to ensure only companies with 100% completed profiles are visible on front side
     */
    public static function getProfileCompleteCondition($tableAlias = '') {
        $p = $tableAlias ? $tableAlias . '.' : '';
        return " AND ({$p}owner_name IS NOT NULL AND TRIM({$p}owner_name) != '')
                 AND ({$p}business_email IS NOT NULL AND TRIM({$p}business_email) != '')
                 AND ({$p}business_phone IS NOT NULL AND TRIM({$p}business_phone) != '')
                 AND ({$p}company_name IS NOT NULL AND TRIM({$p}company_name) != '')
                 AND ({$p}business_address IS NOT NULL AND TRIM({$p}business_address) != '')
                 AND ({$p}city IS NOT NULL AND TRIM({$p}city) != '')
                 AND ({$p}state IS NOT NULL AND TRIM({$p}state) != '')
                 AND ({$p}postal_code IS NOT NULL AND TRIM({$p}postal_code) != '')
                 AND ({$p}logo IS NOT NULL AND TRIM({$p}logo) != '')";
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
        $result = $this->update($company_id, ['logo' => $logo_path]);
        $updatedCompany = $this->find($company_id);
        if ($updatedCompany) {
            $isComplete = self::isProfileComplete($updatedCompany);
            $this->update($company_id, ['show_on_listing' => $isComplete ? 1 : 0]);
        }
        return $result;
    }

    /**
     * Get companies for public listing (landing page)
     */
    public function getPublicCompanies($limit = null, $offset = 0) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE status = 'active' AND show_on_listing = 1" . self::getProfileCompleteCondition() . " 
                  ORDER BY is_featured DESC, created_at DESC";
        
        if ($limit) {
            $query .= " LIMIT :limit OFFSET :offset";
        }
        
        $stmt = $this->conn->prepare($query);
        
        if ($limit) {
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get featured companies for homepage slider
     */
    public function getFeaturedCompanies($limit = 8) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE status = 'active' AND is_featured = 1 AND show_on_listing = 1" . self::getProfileCompleteCondition() . " 
                  ORDER BY created_at DESC 
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Search companies with filters
     */
    public function searchCompanies($filters = []) {
        $query = "SELECT * FROM " . $this->table . " WHERE status = 'active' AND show_on_listing = 1" . self::getProfileCompleteCondition();
        $params = [];

        // Search by keyword
        if (!empty($filters['keyword'])) {
            $keywordValue = '%' . $filters['keyword'] . '%';
            $query .= " AND (company_name LIKE :keyword1 OR owner_name LIKE :keyword2 OR city LIKE :keyword3 OR description LIKE :keyword4)";
            $params['keyword1'] = $keywordValue;
            $params['keyword2'] = $keywordValue;
            $params['keyword3'] = $keywordValue;
            $params['keyword4'] = $keywordValue;
        }

        // Filter by city
        if (!empty($filters['city'])) {
            $query .= " AND city = :city";
            $params['city'] = $filters['city'];
        }

        // Filter by state
        if (!empty($filters['state'])) {
            $query .= " AND state = :state";
            $params['state'] = $filters['state'];
        }

        // Filter by minimum rating
        if (!empty($filters['min_rating'])) {
            $query .= " AND rating >= :min_rating";
            $params['min_rating'] = $filters['min_rating'];
        }

        // Filter by specialty
        if (!empty($filters['specialty'])) {
            $query .= " AND JSON_SEARCH(specialties, 'one', :specialty) IS NOT NULL";
            $params['specialty'] = $filters['specialty'];
        }

        // Sort order
        $sort = $filters['sort'] ?? 'newest';
        $order = $filters['order'] ?? 'DESC';
        
        switch($sort) {
            case 'name':
                $query .= " ORDER BY company_name ASC";
                break;
            case 'newest':
                $query .= " ORDER BY created_at DESC";
                break;
            case 'oldest':
                $query .= " ORDER BY created_at ASC";
                break;
            case 'rating':
                $query .= " ORDER BY rating " . $order . ", total_reviews DESC";
                break;
            case 'reviews':
                $query .= " ORDER BY total_reviews " . $order;
                break;
            case 'experience':
                $query .= " ORDER BY years_experience " . $order;
                break;
            default:
                $query .= " ORDER BY is_featured DESC, created_at DESC";
        }

        // Pagination
        if (isset($filters['limit']) && $filters['limit'] > 0) {
            $query .= " LIMIT :limit";
            if (isset($filters['offset']) && $filters['offset'] >= 0) {
                $query .= " OFFSET :offset";
            }
        }

        $stmt = $this->conn->prepare($query);
        
        // Bind all parameters including pagination
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        
        // Bind pagination parameters separately (they need to be integers)
        if (isset($filters['limit']) && $filters['limit'] > 0) {
            $stmt->bindValue(':limit', (int)$filters['limit'], PDO::PARAM_INT);
            if (isset($filters['offset']) && $filters['offset'] >= 0) {
                $stmt->bindValue(':offset', (int)$filters['offset'], PDO::PARAM_INT);
            }
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Count companies with filters
     */
    public function countCompanies($filters = []) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table . " WHERE status = 'active' AND show_on_listing = 1" . self::getProfileCompleteCondition();
        $params = [];

        if (!empty($filters['keyword'])) {
            $keywordValue = '%' . $filters['keyword'] . '%';
            $query .= " AND (company_name LIKE :keyword1 OR owner_name LIKE :keyword2 OR city LIKE :keyword3 OR description LIKE :keyword4)";
            $params['keyword1'] = $keywordValue;
            $params['keyword2'] = $keywordValue;
            $params['keyword3'] = $keywordValue;
            $params['keyword4'] = $keywordValue;
        }

        if (!empty($filters['city'])) {
            $query .= " AND city = :city";
            $params['city'] = $filters['city'];
        }

        if (!empty($filters['state'])) {
            $query .= " AND state = :state";
            $params['state'] = $filters['state'];
        }

        if (!empty($filters['min_rating'])) {
            $query .= " AND rating >= :min_rating";
            $params['min_rating'] = $filters['min_rating'];
        }

        if (!empty($filters['specialty'])) {
            $query .= " AND JSON_SEARCH(specialties, 'one', :specialty) IS NOT NULL";
            $params['specialty'] = $filters['specialty'];
        }

        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        $stmt->execute();
        $result = $stmt->fetch();
        return $result['total'];
    }

    /**
     * Get unique cities
     */
    public function getUniqueCities() {
        $query = "SELECT DISTINCT city FROM " . $this->table . " 
                  WHERE status = 'active' AND show_on_listing = 1 AND city IS NOT NULL AND TRIM(city) != ''" . self::getProfileCompleteCondition() . " 
                  ORDER BY city ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get unique states
     */
    public function getUniqueStates() {
        $query = "SELECT DISTINCT state FROM " . $this->table . " 
                  WHERE status = 'active' AND show_on_listing = 1 AND state IS NOT NULL AND TRIM(state) != ''" . self::getProfileCompleteCondition() . " 
                  ORDER BY state ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get company listing statistics
     */
    public function getListingStats() {
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) as featured,
                    SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified,
                    AVG(rating) as avg_rating
                  FROM " . $this->table . "
                  WHERE show_on_listing = 1" . self::getProfileCompleteCondition();
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetch();
    }

    /**
     * Get a single company for public display
     */
    public function getPublicCompanyById($companyId) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE id = :id AND status = 'active' AND show_on_listing = 1" . self::getProfileCompleteCondition() . " 
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', $companyId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Refresh rating and review counts from review table
     */
    public function refreshReviewStats($companyId) {
        $reviewModel = new CompanyReview();
        $stats = $reviewModel->getRatingStats($companyId);

        $this->update($companyId, [
            'rating' => $stats['average_rating'],
            'total_reviews' => $stats['review_count']
        ]);

        return $stats;
    }
}
?>

