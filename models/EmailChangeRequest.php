<?php
/**
 * Email Change Request Model
 * Tailoring Management System
 */

require_once __DIR__ . '/BaseModel.php';

class EmailChangeRequest extends BaseModel {
    protected $table = 'email_change_requests';

    /**
     * Create email change request
     */
    public function createRequest($data) {
        return $this->create($data);
    }

    /**
     * Get pending requests for a company
     */
    public function getPendingRequests($company_id) {
        return $this->findAll([
            'company_id' => $company_id,
            'status' => 'pending'
        ], 'created_at DESC');
    }

    /**
     * Get all requests for a company
     */
    public function getCompanyRequests($company_id) {
        return $this->findAll([
            'company_id' => $company_id
        ], 'created_at DESC');
    }

    /**
     * Check if there's a pending request for a company
     */
    public function hasPendingRequest($company_id) {
        try {
            $query = "SELECT id FROM " . $this->table . " 
                      WHERE company_id = :company_id AND status = 'pending' 
                      LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch() ? true : false;
        } catch (PDOException $e) {
            // Gracefully handle missing email_change_requests table
            if ($e->getCode() === '42S02') {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Approve email change request
     */
    public function approveRequest($id, $reviewed_by, $review_notes = null) {
        return $this->update($id, [
            'status' => 'approved',
            'reviewed_by' => $reviewed_by,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'review_notes' => $review_notes
        ]);
    }

    /**
     * Reject email change request
     */
    public function rejectRequest($id, $reviewed_by, $review_notes = null) {
        return $this->update($id, [
            'status' => 'rejected',
            'reviewed_by' => $reviewed_by,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'review_notes' => $review_notes
        ]);
    }

    /**
     * Get all email change requests with company details
     */
    public function getAllRequestsWithDetails($status = null) {
        try {
            $query = "SELECT 
                        ecr.*,
                        c.company_name,
                        c.owner_name,
                        u1.username as requested_by_username,
                        u2.username as reviewed_by_username
                      FROM " . $this->table . " ecr
                      LEFT JOIN companies c ON ecr.company_id = c.id
                      LEFT JOIN users u1 ON ecr.requested_by = u1.id
                      LEFT JOIN users u2 ON ecr.reviewed_by = u2.id";
            
            $params = [];
            if ($status) {
                $query .= " WHERE ecr.status = :status";
                $params['status'] = $status;
            }
            
            $query .= " ORDER BY ecr.created_at DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching email change requests: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get request by ID with company details
     */
    public function getRequestWithDetails($id) {
        try {
            $query = "SELECT 
                        ecr.*,
                        c.company_name,
                        c.owner_name,
                        c.business_phone,
                        c.business_address,
                        u1.username as requested_by_username,
                        u2.username as reviewed_by_username
                      FROM " . $this->table . " ecr
                      LEFT JOIN companies c ON ecr.company_id = c.id
                      LEFT JOIN users u1 ON ecr.requested_by = u1.id
                      LEFT JOIN users u2 ON ecr.reviewed_by = u2.id
                      WHERE ecr.id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching email change request: " . $e->getMessage());
            return null;
        }
    }
}
?>

