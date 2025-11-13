<?php
/**
 * Company Review Model
 * Handles tailor shop reviews and ratings
 */

require_once __DIR__ . '/BaseModel.php';

class CompanyReview extends BaseModel {
    protected $table = 'company_reviews';
    private static $tableChecked = false;

    private function ensureTableExists() {
        if (self::$tableChecked) {
            return;
        }

        try {
            $this->query("SELECT 1 FROM {$this->table} LIMIT 1");
            self::$tableChecked = true;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), '42S02') === false) {
                throw $e;
            }

            $createSql = "
                CREATE TABLE IF NOT EXISTS `{$this->table}` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `company_id` int(11) NOT NULL,
                    `reviewer_name` varchar(100) NOT NULL,
                    `reviewer_email` varchar(150) DEFAULT NULL,
                    `rating` tinyint(1) NOT NULL,
                    `review_text` text DEFAULT NULL,
                    `review_image` varchar(255) DEFAULT NULL,
                    `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'approved',
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `company_id` (`company_id`),
                    KEY `rating` (`rating`),
                    CONSTRAINT `fk_company_reviews_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";

            $this->query($createSql);
            self::$tableChecked = true;
        }
    }

    public function addReview($data) {
        $this->ensureTableExists();
        return $this->create($data);
    }

    public function getApprovedReviews($companyId, $limit = 20) {
        $this->ensureTableExists();
        $query = "SELECT * FROM {$this->table}
                  WHERE company_id = :company_id AND status = 'approved'
                  ORDER BY created_at DESC";
        if ($limit) {
            $query .= " LIMIT :limit";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        if ($limit) {
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getRatingStats($companyId) {
        $this->ensureTableExists();
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(*) as review_count,
                AVG(rating) as average_rating
            FROM {$this->table}
            WHERE company_id = :company_id AND status = 'approved'
        ");
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch();
        return [
            'review_count' => (int)($result['review_count'] ?? 0),
            'average_rating' => $result['average_rating'] ? round($result['average_rating'], 1) : 0
        ];
    }

    public function getRatingBreakdown($companyId) {
        $this->ensureTableExists();
        $stmt = $this->conn->prepare("
            SELECT rating, COUNT(*) as count
            FROM {$this->table}
            WHERE company_id = :company_id AND status = 'approved'
            GROUP BY rating
        ");
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->execute();

        $breakdown = array_fill(1, 5, 0);
        while ($row = $stmt->fetch()) {
            $rating = (int)$row['rating'];
            if ($rating >= 1 && $rating <= 5) {
                $breakdown[$rating] = (int)$row['count'];
            }
        }

        return $breakdown;
    }
}


