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
        $query = "SELECT id FROM " . $this->table . " 
                  WHERE company_id = :company_id AND status = 'pending' 
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch() ? true : false;
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
}
?>

