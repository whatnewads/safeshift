<?php
namespace Core\Services;

use Core\Database;
use Core\Services\BaseService;
use Core\Services\AuditService;
use Core\Services\AuthService;
use Core\Services\EmailService;
use PDO;
use Exception;

class QualityReviewService extends BaseService
{
    private $auditService;
    private $authService;
    private $emailService;
    
    public function __construct()
    {
        parent::__construct();
        $this->auditService = new AuditService();
        $this->authService = new AuthService();
        $this->emailService = new EmailService();
    }
    
    /**
     * Get pending QA review queue
     */
    public function getReviewQueue($reviewer_id = null, $filters = [], $limit = 20, $offset = 0)
    {
        try {
            $query = "
                SELECT 
                    e.encounter_id,
                    e.encounter_date,
                    e.chief_complaint,
                    e.qa_status,
                    e.qa_priority,
                    e.created_at,
                    e.updated_at,
                    p.patient_uuid,
                    p.legal_first_name,
                    p.legal_last_name,
                    p.mrn,
                    p.dob,
                    emp.name AS employer_name,
                    provider.id AS provider_id,
                    provider.first_name AS provider_first_name,
                    provider.last_name AS provider_last_name,
                    CASE 
                        WHEN e.qa_priority = 'critical' THEN 1
                        WHEN e.qa_priority = 'high' THEN 2
                        WHEN e.qa_priority = 'medium' THEN 3
                        ELSE 4
                    END AS priority_order,
                    DATEDIFF(CURRENT_DATE, e.created_at) AS days_pending
                FROM encounters e
                JOIN patient p ON e.patient_uuid = p.patient_uuid
                LEFT JOIN employer emp ON p.employer_uuid = emp.employer_uuid
                LEFT JOIN user provider ON e.npi_provider = provider.npi
                WHERE e.qa_status = 'pending'
            ";
            
            $params = [];
            
            // Apply filters
            if ($reviewer_id) {
                $query .= " AND e.qa_assigned_to = :reviewer_id";
                $params['reviewer_id'] = $reviewer_id;
            }
            
            if (!empty($filters['provider_id'])) {
                $query .= " AND e.npi_provider = :provider_id";
                $params['provider_id'] = $filters['provider_id'];
            }
            
            if (!empty($filters['priority'])) {
                $query .= " AND e.qa_priority = :priority";
                $params['priority'] = $filters['priority'];
            }
            
            if (!empty($filters['date_from'])) {
                $query .= " AND e.encounter_date >= :date_from";
                $params['date_from'] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $query .= " AND e.encounter_date <= :date_to";
                $params['date_to'] = $filters['date_to'];
            }
            
            // Order by priority and date
            $query .= " ORDER BY priority_order ASC, e.created_at ASC";
            
            // Add pagination
            $query .= " LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            $encounters = $stmt->fetchAll();
            
            // Get total count for pagination
            $countQuery = "
                SELECT COUNT(*) as total
                FROM encounters e
                WHERE e.qa_status = 'pending'
            ";
            
            if ($reviewer_id) {
                $countQuery .= " AND e.qa_assigned_to = :reviewer_id";
            }
            
            $countStmt = $this->db->prepare($countQuery);
            if ($reviewer_id) {
                $countStmt->bindValue(':reviewer_id', $reviewer_id);
            }
            $countStmt->execute();
            $total = $countStmt->fetch()['total'];
            
            // Format encounters for mobile display
            $formattedEncounters = array_map(function($encounter) {
                return [
                    'encounter_id' => $encounter['encounter_id'],
                    'patient' => [
                        'uuid' => $encounter['patient_uuid'],
                        'name' => $encounter['legal_first_name'] . ' ' . $encounter['legal_last_name'],
                        'mrn' => $encounter['mrn'],
                        'age' => $this->calculateAge($encounter['dob'])
                    ],
                    'encounter_date' => $encounter['encounter_date'],
                    'chief_complaint' => $encounter['chief_complaint'],
                    'provider' => [
                        'id' => $encounter['provider_id'],
                        'name' => $encounter['provider_first_name'] . ' ' . $encounter['provider_last_name']
                    ],
                    'employer' => $encounter['employer_name'],
                    'qa_priority' => $encounter['qa_priority'],
                    'days_pending' => $encounter['days_pending'],
                    'created_at' => $encounter['created_at']
                ];
            }, $encounters);
            
            return [
                'encounters' => $formattedEncounters,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ];
            
        } catch (Exception $e) {
            $this->logError("Failed to get review queue: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Approve encounter QA review
     */
    public function approveEncounter($encounter_id, $reviewer_id, $notes = '')
    {
        try {
            $this->db->beginTransaction();
            
            // Update encounter QA status
            $stmt = $this->db->prepare("
                UPDATE encounters 
                SET qa_status = 'approved',
                    qa_reviewed_by = :reviewer_id,
                    qa_reviewed_at = NOW(),
                    qa_notes = :notes,
                    updated_at = NOW()
                WHERE encounter_id = :encounter_id
                AND qa_status = 'pending'
            ");
            
            $stmt->execute([
                'encounter_id' => $encounter_id,
                'reviewer_id' => $reviewer_id,
                'notes' => $notes
            ]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("Encounter not found or already reviewed");
            }
            
            // Log the approval
            $this->auditService->log('qa_approve', 'encounter', $encounter_id, 
                "QA approved encounter with notes: " . $notes);
            
            // Notify provider of approval
            $this->notifyProviderOfQADecision($encounter_id, 'approved', $notes);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Encounter approved successfully'
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError("Failed to approve encounter: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Reject encounter QA review
     */
    public function rejectEncounter($encounter_id, $reviewer_id, $rejection_reasons, $notes = '')
    {
        try {
            $this->db->beginTransaction();
            
            // Update encounter QA status
            $stmt = $this->db->prepare("
                UPDATE encounters 
                SET qa_status = 'rejected',
                    qa_reviewed_by = :reviewer_id,
                    qa_reviewed_at = NOW(),
                    qa_rejection_reasons = :rejection_reasons,
                    qa_notes = :notes,
                    updated_at = NOW()
                WHERE encounter_id = :encounter_id
                AND qa_status = 'pending'
            ");
            
            $stmt->execute([
                'encounter_id' => $encounter_id,
                'reviewer_id' => $reviewer_id,
                'rejection_reasons' => json_encode($rejection_reasons),
                'notes' => $notes
            ]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("Encounter not found or already reviewed");
            }
            
            // Log the rejection
            $this->auditService->log('qa_reject', 'encounter', $encounter_id, 
                "QA rejected encounter with reasons: " . json_encode($rejection_reasons));
            
            // Notify provider of rejection
            $this->notifyProviderOfQADecision($encounter_id, 'rejected', $notes, $rejection_reasons);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Encounter rejected and sent back to provider'
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError("Failed to reject encounter: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Flag encounter for escalation
     */
    public function flagEncounter($encounter_id, $reviewer_id, $flag_reason, $escalate_to = null)
    {
        try {
            $this->db->beginTransaction();
            
            // Update encounter QA status
            $stmt = $this->db->prepare("
                UPDATE encounters 
                SET qa_status = 'flagged',
                    qa_reviewed_by = :reviewer_id,
                    qa_reviewed_at = NOW(),
                    qa_flag_reason = :flag_reason,
                    qa_escalated_to = :escalate_to,
                    updated_at = NOW()
                WHERE encounter_id = :encounter_id
                AND qa_status = 'pending'
            ");
            
            $stmt->execute([
                'encounter_id' => $encounter_id,
                'reviewer_id' => $reviewer_id,
                'flag_reason' => $flag_reason,
                'escalate_to' => $escalate_to
            ]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("Encounter not found or already reviewed");
            }
            
            // Create flag record
            $stmt = $this->db->prepare("
                INSERT INTO encounter_flags (
                    flag_id, encounter_id, flag_type, severity, flag_reason,
                    flagged_by, assigned_to, created_at
                ) VALUES (
                    UUID(), :encounter_id, 'qa_escalation', 'high', :flag_reason,
                    :flagged_by, :assigned_to, NOW()
                )
            ");
            
            $stmt->execute([
                'encounter_id' => $encounter_id,
                'flag_reason' => $flag_reason,
                'flagged_by' => $reviewer_id,
                'assigned_to' => $escalate_to
            ]);
            
            // Log the flag
            $this->auditService->log('qa_flag', 'encounter', $encounter_id, 
                "QA flagged encounter for escalation: " . $flag_reason);
            
            // Notify escalation recipient
            if ($escalate_to) {
                $this->notifyEscalationRecipient($encounter_id, $escalate_to, $flag_reason);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Encounter flagged for escalation'
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError("Failed to flag encounter: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Bulk approve multiple encounters
     */
    public function bulkApprove($encounter_ids, $reviewer_id, $notes = '')
    {
        try {
            $this->db->beginTransaction();
            
            $approved = 0;
            $errors = [];
            
            foreach ($encounter_ids as $encounter_id) {
                try {
                    $this->approveEncounter($encounter_id, $reviewer_id, $notes);
                    $approved++;
                } catch (Exception $e) {
                    $errors[] = [
                        'encounter_id' => $encounter_id,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'approved' => $approved,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError("Failed bulk approval: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get QA statistics for dashboard
     */
    public function getQAStatistics($date_range = '30')
    {
        try {
            $stats = [];
            
            // Get pending count by priority
            $stmt = $this->db->prepare("
                SELECT 
                    qa_priority,
                    COUNT(*) as count
                FROM encounters
                WHERE qa_status = 'pending'
                GROUP BY qa_priority
            ");
            $stmt->execute();
            $pendingByPriority = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Get review statistics
            $stmt = $this->db->prepare("
                SELECT 
                    qa_status,
                    COUNT(*) as count
                FROM encounters
                WHERE qa_reviewed_at >= DATE_SUB(CURRENT_DATE, INTERVAL :days DAY)
                GROUP BY qa_status
            ");
            $stmt->execute(['days' => $date_range]);
            $reviewStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Get average review time
            $stmt = $this->db->prepare("
                SELECT 
                    AVG(TIMESTAMPDIFF(HOUR, created_at, qa_reviewed_at)) as avg_hours
                FROM encounters
                WHERE qa_status IN ('approved', 'rejected')
                AND qa_reviewed_at >= DATE_SUB(CURRENT_DATE, INTERVAL :days DAY)
            ");
            $stmt->execute(['days' => $date_range]);
            $avgReviewTime = $stmt->fetch()['avg_hours'];
            
            // Get reviewer performance
            $stmt = $this->db->prepare("
                SELECT 
                    u.id,
                    u.first_name,
                    u.last_name,
                    COUNT(e.encounter_id) as reviews_completed,
                    SUM(CASE WHEN e.qa_status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN e.qa_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    AVG(TIMESTAMPDIFF(HOUR, e.created_at, e.qa_reviewed_at)) as avg_review_hours
                FROM encounters e
                JOIN user u ON e.qa_reviewed_by = u.id
                WHERE e.qa_reviewed_at >= DATE_SUB(CURRENT_DATE, INTERVAL :days DAY)
                GROUP BY u.id
                ORDER BY reviews_completed DESC
                LIMIT 10
            ");
            $stmt->execute(['days' => $date_range]);
            $topReviewers = $stmt->fetchAll();
            
            return [
                'pending_by_priority' => $pendingByPriority,
                'review_stats' => $reviewStats,
                'avg_review_hours' => round($avgReviewTime, 1),
                'top_reviewers' => $topReviewers,
                'date_range_days' => $date_range
            ];
            
        } catch (Exception $e) {
            $this->logError("Failed to get QA statistics: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Download encounters for offline review
     */
    public function downloadForOfflineReview($reviewer_id, $limit = 50)
    {
        try {
            $queue = $this->getReviewQueue($reviewer_id, [], $limit);
            
            // Get full encounter details for each
            $offlineData = [];
            
            foreach ($queue['encounters'] as $encounter) {
                // Get vital signs
                $stmt = $this->db->prepare("
                    SELECT * FROM vital_signs
                    WHERE encounter_id = :encounter_id
                    ORDER BY created_at DESC
                    LIMIT 1
                ");
                $stmt->execute(['encounter_id' => $encounter['encounter_id']]);
                $vitals = $stmt->fetch();
                
                // Get clinical notes
                $stmt = $this->db->prepare("
                    SELECT * FROM clinical_notes
                    WHERE encounter_id = :encounter_id
                    ORDER BY created_at DESC
                ");
                $stmt->execute(['encounter_id' => $encounter['encounter_id']]);
                $notes = $stmt->fetchAll();
                
                $offlineData[] = [
                    'encounter' => $encounter,
                    'vitals' => $vitals,
                    'notes' => $notes,
                    'downloaded_at' => date('Y-m-d H:i:s')
                ];
            }
            
            return [
                'success' => true,
                'data' => $offlineData,
                'count' => count($offlineData),
                'sync_token' => uniqid('sync_', true)
            ];
            
        } catch (Exception $e) {
            $this->logError("Failed to download for offline review: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Sync offline review decisions
     */
    public function syncOfflineDecisions($sync_data)
    {
        try {
            $this->db->beginTransaction();
            
            $synced = 0;
            $conflicts = [];
            
            foreach ($sync_data as $decision) {
                // Check if encounter was already reviewed
                $stmt = $this->db->prepare("
                    SELECT qa_status, qa_reviewed_at
                    FROM encounters
                    WHERE encounter_id = :encounter_id
                ");
                $stmt->execute(['encounter_id' => $decision['encounter_id']]);
                $current = $stmt->fetch();
                
                if ($current['qa_status'] !== 'pending') {
                    $conflicts[] = [
                        'encounter_id' => $decision['encounter_id'],
                        'offline_decision' => $decision['action'],
                        'current_status' => $current['qa_status'],
                        'reviewed_at' => $current['qa_reviewed_at']
                    ];
                    continue;
                }
                
                // Apply the decision
                switch ($decision['action']) {
                    case 'approve':
                        $this->approveEncounter(
                            $decision['encounter_id'], 
                            $decision['reviewer_id'], 
                            $decision['notes'] ?? ''
                        );
                        break;
                    case 'reject':
                        $this->rejectEncounter(
                            $decision['encounter_id'], 
                            $decision['reviewer_id'], 
                            $decision['rejection_reasons'] ?? [], 
                            $decision['notes'] ?? ''
                        );
                        break;
                    case 'flag':
                        $this->flagEncounter(
                            $decision['encounter_id'], 
                            $decision['reviewer_id'], 
                            $decision['flag_reason'] ?? ''
                        );
                        break;
                }
                
                $synced++;
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'synced' => $synced,
                'conflicts' => $conflicts
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError("Failed to sync offline decisions: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Calculate age from date of birth
     */
    private function calculateAge($dob)
    {
        if (!$dob) return 'N/A';
        
        $birthDate = new \DateTime($dob);
        $today = new \DateTime();
        $age = $today->diff($birthDate);
        
        return $age->y . ' years';
    }
    
    /**
     * Notify provider of QA decision
     */
    private function notifyProviderOfQADecision($encounter_id, $decision, $notes, $rejection_reasons = [])
    {
        try {
            // Get provider and encounter details
            $stmt = $this->db->prepare("
                SELECT 
                    e.encounter_id,
                    e.encounter_date,
                    e.chief_complaint,
                    u.email,
                    u.first_name,
                    u.last_name,
                    p.legal_first_name AS patient_first_name,
                    p.legal_last_name AS patient_last_name
                FROM encounters e
                JOIN user u ON e.npi_provider = u.npi
                JOIN patient p ON e.patient_uuid = p.patient_uuid
                WHERE e.encounter_id = :encounter_id
            ");
            $stmt->execute(['encounter_id' => $encounter_id]);
            $data = $stmt->fetch();
            
            if (!$data || !$data['email']) return;
            
            // Prepare email content
            $subject = "QA Review " . ucfirst($decision) . ": " . $data['patient_first_name'] . " " . $data['patient_last_name'];
            $body = $this->prepareQANotificationEmail($data, $decision, $notes, $rejection_reasons);
            
            // Send email
            $this->emailService->send($data['email'], $subject, $body);
            
        } catch (Exception $e) {
            $this->logError("Failed to notify provider: " . $e->getMessage());
        }
    }
    
    /**
     * Notify escalation recipient
     */
    private function notifyEscalationRecipient($encounter_id, $recipient_id, $flag_reason)
    {
        try {
            // Get recipient details
            $stmt = $this->db->prepare("SELECT email, first_name FROM user WHERE id = :id");
            $stmt->execute(['id' => $recipient_id]);
            $recipient = $stmt->fetch();
            
            if (!$recipient || !$recipient['email']) return;
            
            // Get encounter details
            $stmt = $this->db->prepare("
                SELECT 
                    e.encounter_id,
                    e.encounter_date,
                    p.legal_first_name,
                    p.legal_last_name
                FROM encounters e
                JOIN patient p ON e.patient_uuid = p.patient_uuid
                WHERE e.encounter_id = :encounter_id
            ");
            $stmt->execute(['encounter_id' => $encounter_id]);
            $encounter = $stmt->fetch();
            
            $subject = "QA Escalation Required: " . $encounter['legal_first_name'] . " " . $encounter['legal_last_name'];
            $body = "
                <p>Hello {$recipient['first_name']},</p>
                <p>A QA review has been escalated to you for the following encounter:</p>
                <ul>
                    <li>Patient: {$encounter['legal_first_name']} {$encounter['legal_last_name']}</li>
                    <li>Encounter Date: {$encounter['encounter_date']}</li>
                    <li>Escalation Reason: {$flag_reason}</li>
                </ul>
                <p>Please review this encounter at your earliest convenience.</p>
            ";
            
            $this->emailService->send($recipient['email'], $subject, $body);
            
        } catch (Exception $e) {
            $this->logError("Failed to notify escalation recipient: " . $e->getMessage());
        }
    }
    
    /**
     * Prepare QA notification email
     */
    private function prepareQANotificationEmail($data, $decision, $notes, $rejection_reasons)
    {
        $body = "<p>Hello Dr. {$data['last_name']},</p>";
        
        if ($decision === 'approved') {
            $body .= "
                <p>Your encounter documentation has been <strong>approved</strong> by Quality Assurance.</p>
                <ul>
                    <li>Patient: {$data['patient_first_name']} {$data['patient_last_name']}</li>
                    <li>Encounter Date: {$data['encounter_date']}</li>
                    <li>Chief Complaint: {$data['chief_complaint']}</li>
                </ul>
            ";
            
            if ($notes) {
                $body .= "<p>QA Notes: {$notes}</p>";
            }
        } else {
            $body .= "
                <p>Your encounter documentation has been <strong>rejected</strong> and requires corrections.</p>
                <ul>
                    <li>Patient: {$data['patient_first_name']} {$data['patient_last_name']}</li>
                    <li>Encounter Date: {$data['encounter_date']}</li>
                    <li>Chief Complaint: {$data['chief_complaint']}</li>
                </ul>
                <p><strong>Rejection Reasons:</strong></p>
                <ul>
            ";
            
            foreach ($rejection_reasons as $reason) {
                $body .= "<li>{$reason}</li>";
            }
            
            $body .= "</ul>";
            
            if ($notes) {
                $body .= "<p>Additional Notes: {$notes}</p>";
            }
            
            $body .= "<p>Please log in to make the necessary corrections.</p>";
        }
        
        return $body;
    }
}