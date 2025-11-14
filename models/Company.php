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

    /**
     * Get companies for public listing (landing page)
     */
    public function getPublicCompanies($limit = null, $offset = 0) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE status = 'active' AND show_on_listing = 1 
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
                  WHERE status = 'active' AND is_featured = 1 AND show_on_listing = 1 
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
        $query = "SELECT * FROM " . $this->table . " WHERE status = 'active' AND show_on_listing = 1";
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
        $query = "SELECT COUNT(*) as total FROM " . $this->table . " WHERE status = 'active' AND show_on_listing = 1";
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
                  WHERE status = 'active' AND show_on_listing = 1 AND city IS NOT NULL 
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
                  WHERE status = 'active' AND show_on_listing = 1 AND state IS NOT NULL 
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
                  WHERE show_on_listing = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetch();
    }

    /**
     * Get a single company for public display
     */
    public function getPublicCompanyById($companyId) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE id = :id AND status = 'active' AND show_on_listing = 1 
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

