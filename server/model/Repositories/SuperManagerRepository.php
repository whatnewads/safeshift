<?php
/**
 * SuperManagerRepository.php - Repository for SuperManager Dashboard Data
 * 
 * Provides data access methods for the SuperManager dashboard including
 * multi-clinic oversight, staff management, operational metrics, and alerts.
 * 
 * The SuperManager role is associated with 'Manager' role type with elevated
 * permissions across multiple establishments/clinics.
 * 
 * @package    SafeShift\Model\Repositories
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace Model\Repositories;

use PDO;
use PDOException;
use DateTime;

/**
 * SuperManager Repository
 * 
 * Handles database operations for the SuperManager dashboard vertical slice.
 * Provides multi-clinic overview, staff management, operational metrics, and alerts.
 */
class SuperManagerRepository
{
    private PDO $pdo;
    
    /** @var string Establishment/clinic table name */
    private string $establishmentTable = 'establishment';
    
    /** @var string User table name */
    private string $userTable = 'user';
    
    /** @var string User role junction table */
    private string $userRoleTable = 'userrole';
    
    /** @var string Role table name */
    private string $roleTable = 'role';
    
    /** @var string Encounters table name */
    private string $encountersTable = 'encounters';
    
    /** @var string Staff training records table */
    private string $trainingRecordsTable = 'staff_training_records';
    
    /** @var string Training requirements table */
    private string $trainingRequirementsTable = 'training_requirements';

    /**
     * Constructor
     * 
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ========================================================================
    // Overview Statistics
    // ========================================================================

    /**
     * Get overview statistics for the SuperManager dashboard
     * 
     * Returns counts for:
     * - clinicsManaged: Total active clinics/establishments
     * - totalStaff: Total active staff members
     * - encountersToday: Total encounters across all clinics today
     * - pendingApprovals: Pending time off requests, schedule changes, etc.
     * 
     * @return array{clinicsManaged: int, totalStaff: int, encountersToday: int, pendingApprovals: int}
     */
    public function getOverviewStats(): array
    {
        try {
            $stats = [
                'clinicsManaged' => 0,
                'totalStaff' => 0,
                'encountersToday' => 0,
                'pendingApprovals' => 0
            ];
            
            // Count active clinics/establishments
            $clinicsSql = "SELECT COUNT(*) FROM {$this->establishmentTable} WHERE is_active = 1";
            $stmt = $this->pdo->query($clinicsSql);
            $stats['clinicsManaged'] = (int) $stmt->fetchColumn();
            
            // Count active staff members
            $staffSql = "SELECT COUNT(*) FROM {$this->userTable} WHERE is_active = 1";
            $stmt = $this->pdo->query($staffSql);
            $stats['totalStaff'] = (int) $stmt->fetchColumn();
            
            // Count encounters today across all clinics
            $today = (new DateTime())->format('Y-m-d');
            $encountersSql = "SELECT COUNT(*) FROM {$this->encountersTable} 
                              WHERE DATE(occurred_on) = :today 
                              AND deleted_at IS NULL";
            $stmt = $this->pdo->prepare($encountersSql);
            $stmt->execute(['today' => $today]);
            $stats['encountersToday'] = (int) $stmt->fetchColumn();
            
            // Count pending approvals (time off requests, etc.)
            $stats['pendingApprovals'] = $this->countPendingApprovals();
            
            return $stats;
        } catch (PDOException $e) {
            error_log('SuperManagerRepository::getOverviewStats error: ' . $e->getMessage());
            return [
                'clinicsManaged' => 0,
                'totalStaff' => 0,
                'encountersToday' => 0,
                'pendingApprovals' => 0
            ];
        }
    }

    // ========================================================================
    // Clinic Performance
    // ========================================================================

    /**
     * Get performance metrics for all managed clinics
     * 
     * Returns clinic-level metrics including encounters, wait times, and staff count.
     * 
     * @param int $limit Maximum number of clinics to return
     * @return array<int, array{
     *   id: string,
     *   name: string,
     *   encountersToday: int,
     *   avgWaitTime: float,
     *   patientSatisfaction: float,
     *   staffCount: int,
     *   status: string
     * }>
     */
    public function getClinicPerformance(int $limit = 10): array
    {
        try {
            $today = (new DateTime())->format('Y-m-d');
            
            $sql = "SELECT 
                        est.Id AS clinic_id,
                        est.establishment_name AS clinic_name,
                        est.is_active,
                        est.city,
                        est.state,
                        COUNT(DISTINCT CASE WHEN DATE(e.occurred_on) = :today THEN e.encounter_id END) AS encounters_today,
                        AVG(TIMESTAMPDIFF(MINUTE, e.arrived_on, 
                            (SELECT MIN(eo.taken_at) FROM encounter_observations eo WHERE eo.encounter_id = e.encounter_id)
                        )) AS avg_wait_time,
                        (SELECT COUNT(DISTINCT u.user_id) 
                         FROM {$this->userTable} u 
                         WHERE u.is_active = 1) AS staff_count
                    FROM {$this->establishmentTable} est
                    LEFT JOIN {$this->encountersTable} e ON e.site_id = est.Id AND e.deleted_at IS NULL
                    WHERE est.is_active = 1
                    GROUP BY est.Id, est.establishment_name, est.is_active, est.city, est.state
                    ORDER BY encounters_today DESC
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':today', $today);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = [
                    'id' => (string) $row['clinic_id'],
                    'name' => $row['clinic_name'] ?? 'Unknown Clinic',
                    'encountersToday' => (int) ($row['encounters_today'] ?? 0),
                    'avgWaitTime' => round((float) ($row['avg_wait_time'] ?? 0), 1),
                    'patientSatisfaction' => $this->getClinicSatisfactionScore((string) $row['clinic_id']),
                    'staffCount' => (int) ($row['staff_count'] ?? 0),
                    'status' => $row['is_active'] ? 'active' : 'inactive',
                    'location' => trim(($row['city'] ?? '') . ', ' . ($row['state'] ?? ''))
                ];
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log('SuperManagerRepository::getClinicPerformance error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get patient satisfaction score for a clinic
     * 
     * @param string $clinicId Clinic/establishment ID
     * @return float Satisfaction score (0-5 scale)
     */
    private function getClinicSatisfactionScore(string $clinicId): float
    {
        // Patient satisfaction would typically come from a surveys table
        // For now, return a calculated average based on wait times or default
        try {
            $sql = "SELECT AVG(
                        CASE 
                            WHEN TIMESTAMPDIFF(MINUTE, e.arrived_on, e.discharged_on) < 30 THEN 5.0
                            WHEN TIMESTAMPDIFF(MINUTE, e.arrived_on, e.discharged_on) < 60 THEN 4.0
                            WHEN TIMESTAMPDIFF(MINUTE, e.arrived_on, e.discharged_on) < 90 THEN 3.5
                            ELSE 3.0
                        END
                    ) as satisfaction
                    FROM {$this->encountersTable} e
                    WHERE e.site_id = :clinic_id
                    AND e.status = 'completed'
                    AND e.discharged_on IS NOT NULL
                    AND e.arrived_on IS NOT NULL
                    AND e.deleted_at IS NULL
                    AND e.occurred_on >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':clinic_id', $clinicId);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return round((float) ($result['satisfaction'] ?? 4.0), 1);
        } catch (PDOException $e) {
            return 4.0; // Default satisfaction score
        }
    }

    // ========================================================================
    // Staff Overview
    // ========================================================================

    /**
     * Get staff overview with clinic, role, and compliance status
     * 
     * @param int $limit Maximum number of staff to return
     * @return array<int, array{
     *   id: string,
     *   name: string,
     *   clinic: string,
     *   role: string,
     *   trainingStatus: string,
     *   credentialStatus: string
     * }>
     */
    public function getStaffOverview(int $limit = 20): array
    {
        try {
            $sql = "SELECT 
                        u.user_id,
                        u.username,
                        u.email,
                        u.status,
                        u.is_active,
                        u.last_login,
                        GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS roles,
                        GROUP_CONCAT(DISTINCT r.slug ORDER BY r.slug SEPARATOR ',') AS role_slugs
                    FROM {$this->userTable} u
                    LEFT JOIN {$this->userRoleTable} ur ON u.user_id = ur.user_id
                    LEFT JOIN {$this->roleTable} r ON ur.role_id = r.role_id
                    WHERE u.is_active = 1
                    GROUP BY u.user_id, u.username, u.email, u.status, u.is_active, u.last_login
                    ORDER BY u.username
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $trainingStatus = $this->getStaffTrainingStatus($row['user_id']);
                $credentialStatus = $this->getStaffCredentialStatus($row['user_id']);
                
                $results[] = [
                    'id' => $row['user_id'],
                    'name' => $row['username'] ?? 'Unknown',
                    'email' => $row['email'] ?? '',
                    'clinic' => 'Main Clinic', // Would need user-to-clinic assignment table
                    'role' => $row['roles'] ?? 'No Role',
                    'trainingStatus' => $trainingStatus,
                    'credentialStatus' => $credentialStatus,
                    'lastLogin' => $row['last_login']
                ];
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log('SuperManagerRepository::getStaffOverview error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get training compliance status for a staff member
     * 
     * @param string $userId User ID
     * @return string Status: 'compliant', 'expiring', 'overdue', 'unknown'
     */
    private function getStaffTrainingStatus(string $userId): string
    {
        try {
            $sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN str.expiration_date < NOW() THEN 1 ELSE 0 END) as overdue,
                        SUM(CASE WHEN str.expiration_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiring
                    FROM {$this->trainingRecordsTable} str
                    WHERE str.user_id = :user_id
                    AND str.completion_date IS NOT NULL";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':user_id', $userId);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ((int) $result['overdue'] > 0) {
                return 'overdue';
            } elseif ((int) $result['expiring'] > 0) {
                return 'expiring';
            } elseif ((int) $result['total'] > 0) {
                return 'compliant';
            }
            
            return 'unknown';
        } catch (PDOException $e) {
            return 'unknown';
        }
    }

    /**
     * Get credential status for a staff member
     * 
     * @param string $userId User ID
     * @return string Status: 'valid', 'expiring', 'expired', 'unknown'
     */
    private function getStaffCredentialStatus(string $userId): string
    {
        // Would typically check user_credentials table
        // For now, use training records as proxy for credentials
        try {
            $sql = "SELECT 
                        MIN(str.expiration_date) as earliest_expiration
                    FROM {$this->trainingRecordsTable} str
                    WHERE str.user_id = :user_id
                    AND str.completion_date IS NOT NULL
                    AND str.expiration_date IS NOT NULL";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':user_id', $userId);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (empty($result['earliest_expiration'])) {
                return 'unknown';
            }
            
            $expDate = new DateTime($result['earliest_expiration']);
            $now = new DateTime();
            $thirtyDaysFromNow = (new DateTime())->modify('+30 days');
            
            if ($expDate < $now) {
                return 'expired';
            } elseif ($expDate <= $thirtyDaysFromNow) {
                return 'expiring';
            }
            
            return 'valid';
        } catch (PDOException $e) {
            return 'unknown';
        }
    }

    // ========================================================================
    // Expiring Credentials
    // ========================================================================

    /**
     * Get credentials expiring within specified days
     * 
     * @param int $daysAhead Days to look ahead for expiring credentials
     * @param int $limit Maximum number of results
     * @return array<int, array{
     *   id: string,
     *   staffName: string,
     *   credential: string,
     *   expiresAt: string,
     *   clinic: string
     * }>
     */
    public function getExpiringCredentials(int $daysAhead = 30, int $limit = 10): array
    {
        try {
            $sql = "SELECT 
                        str.record_id,
                        str.user_id,
                        u.username,
                        tr.training_name AS credential_name,
                        str.expiration_date,
                        DATEDIFF(str.expiration_date, NOW()) AS days_until_expiry
                    FROM {$this->trainingRecordsTable} str
                    JOIN {$this->userTable} u ON str.user_id = u.user_id
                    JOIN {$this->trainingRequirementsTable} tr ON str.requirement_id = tr.requirement_id
                    WHERE str.completion_date IS NOT NULL
                    AND str.expiration_date IS NOT NULL
                    AND str.expiration_date <= DATE_ADD(NOW(), INTERVAL :days DAY)
                    AND str.expiration_date >= NOW()
                    AND u.is_active = 1
                    ORDER BY str.expiration_date ASC
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':days', $daysAhead, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = [
                    'id' => $row['record_id'],
                    'staffId' => $row['user_id'],
                    'staffName' => $row['username'] ?? 'Unknown',
                    'credential' => $row['credential_name'] ?? 'Unknown Credential',
                    'expiresAt' => $row['expiration_date'],
                    'daysUntilExpiry' => (int) $row['days_until_expiry'],
                    'clinic' => 'Main Clinic' // Would need user-to-clinic mapping
                ];
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log('SuperManagerRepository::getExpiringCredentials error: ' . $e->getMessage());
            return [];
        }
    }

    // ========================================================================
    // Training Overdue
    // ========================================================================

    /**
     * Get staff with overdue training
     * 
     * @param int $limit Maximum number of results
     * @return array<int, array{
     *   id: string,
     *   staffName: string,
     *   training: string,
     *   dueDate: string,
     *   clinic: string
     * }>
     */
    public function getTrainingOverdue(int $limit = 10): array
    {
        try {
            // First, try to get overdue training from records with past expiration dates
            $sql = "SELECT 
                        str.record_id,
                        str.user_id,
                        u.username,
                        tr.training_name,
                        str.expiration_date AS due_date,
                        DATEDIFF(NOW(), str.expiration_date) AS days_overdue
                    FROM {$this->trainingRecordsTable} str
                    JOIN {$this->userTable} u ON str.user_id = u.user_id
                    JOIN {$this->trainingRequirementsTable} tr ON str.requirement_id = tr.requirement_id
                    WHERE str.completion_date IS NOT NULL
                    AND str.expiration_date IS NOT NULL
                    AND str.expiration_date < NOW()
                    AND u.is_active = 1
                    ORDER BY str.expiration_date ASC
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = [
                    'id' => $row['record_id'],
                    'staffId' => $row['user_id'],
                    'staffName' => $row['username'] ?? 'Unknown',
                    'training' => $row['training_name'] ?? 'Unknown Training',
                    'dueDate' => $row['due_date'],
                    'daysOverdue' => (int) $row['days_overdue'],
                    'clinic' => 'Main Clinic'
                ];
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log('SuperManagerRepository::getTrainingOverdue error: ' . $e->getMessage());
            return [];
        }
    }

    // ========================================================================
    // Pending Approvals
    // ========================================================================

    /**
     * Count pending approvals
     * 
     * @return int Number of pending approvals
     */
    private function countPendingApprovals(): int
    {
        // Try to count from time_off_requests or similar tables
        // Fallback to 0 if tables don't exist
        try {
            $count = 0;
            
            // Try time_off_requests table
            try {
                $sql = "SELECT COUNT(*) FROM time_off_requests WHERE status = 'pending'";
                $stmt = $this->pdo->query($sql);
                $count += (int) $stmt->fetchColumn();
            } catch (PDOException $e) {
                // Table may not exist
            }
            
            // Try schedule_changes table
            try {
                $sql = "SELECT COUNT(*) FROM schedule_changes WHERE status = 'pending'";
                $stmt = $this->pdo->query($sql);
                $count += (int) $stmt->fetchColumn();
            } catch (PDOException $e) {
                // Table may not exist
            }
            
            // Try qa_review_queue for override approvals
            try {
                $sql = "SELECT COUNT(*) FROM qa_review_queue WHERE review_status = 'pending'";
                $stmt = $this->pdo->query($sql);
                $count += (int) $stmt->fetchColumn();
            } catch (PDOException $e) {
                // Table may not exist
            }
            
            return $count;
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * Get pending approvals list
     * 
     * @param int $limit Maximum number of results
     * @return array<int, array{
     *   id: string,
     *   type: string,
     *   staffName: string,
     *   requestDate: string,
     *   details: string,
     *   status: string
     * }>
     */
    public function getPendingApprovals(int $limit = 10): array
    {
        $results = [];
        
        // Try to get from qa_review_queue (override approvals)
        try {
            $sql = "SELECT 
                        qr.review_id,
                        'chart_review' AS request_type,
                        u.username AS staff_name,
                        qr.created_at AS request_date,
                        CONCAT('Chart review for encounter: ', qr.encounter_id) AS details,
                        qr.review_status AS status
                    FROM qa_review_queue qr
                    LEFT JOIN {$this->encountersTable} e ON qr.encounter_id = e.encounter_id
                    LEFT JOIN {$this->userTable} u ON e.created_by = u.user_id
                    WHERE qr.review_status = 'pending'
                    ORDER BY qr.created_at ASC
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = [
                    'id' => $row['review_id'],
                    'type' => $row['request_type'],
                    'staffName' => $row['staff_name'] ?? 'Unknown',
                    'requestDate' => $row['request_date'],
                    'details' => $row['details'] ?? '',
                    'status' => $row['status']
                ];
            }
        } catch (PDOException $e) {
            // Table may not exist or query error
            error_log('SuperManagerRepository::getPendingApprovals qa_review error: ' . $e->getMessage());
        }
        
        // Try to get time off requests if table exists
        try {
            $sql = "SELECT 
                        tor.request_id,
                        'time_off' AS request_type,
                        u.username AS staff_name,
                        tor.created_at AS request_date,
                        CONCAT('Time Off: ', tor.start_date, ' to ', tor.end_date) AS details,
                        tor.status
                    FROM time_off_requests tor
                    JOIN {$this->userTable} u ON tor.user_id = u.user_id
                    WHERE tor.status = 'pending'
                    ORDER BY tor.created_at ASC
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = [
                    'id' => $row['request_id'],
                    'type' => $row['request_type'],
                    'staffName' => $row['staff_name'] ?? 'Unknown',
                    'requestDate' => $row['request_date'],
                    'details' => $row['details'] ?? '',
                    'status' => $row['status']
                ];
            }
        } catch (PDOException $e) {
            // Table may not exist - this is expected
        }
        
        // Sort by request date and limit
        usort($results, function($a, $b) {
            return strtotime($a['requestDate']) - strtotime($b['requestDate']);
        });
        
        return array_slice($results, 0, $limit);
    }

    // ========================================================================
    // Clinic Comparison
    // ========================================================================

    /**
     * Get comparative metrics across all clinics
     * 
     * @return array<int, array{
     *   clinicId: string,
     *   clinicName: string,
     *   encounters7d: int,
     *   encounters30d: int,
     *   avgWaitTime: float,
     *   avgTreatmentTime: float,
     *   staffCount: int
     * }>
     */
    public function getClinicComparison(): array
    {
        try {
            $sql = "SELECT 
                        est.Id AS clinic_id,
                        est.establishment_name AS clinic_name,
                        COUNT(DISTINCT CASE WHEN e.occurred_on >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN e.encounter_id END) AS encounters_7d,
                        COUNT(DISTINCT CASE WHEN e.occurred_on >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN e.encounter_id END) AS encounters_30d,
                        AVG(TIMESTAMPDIFF(MINUTE, e.arrived_on, 
                            (SELECT MIN(eo.taken_at) FROM encounter_observations eo WHERE eo.encounter_id = e.encounter_id)
                        )) AS avg_wait_time,
                        AVG(TIMESTAMPDIFF(MINUTE, e.arrived_on, e.discharged_on)) AS avg_treatment_time
                    FROM {$this->establishmentTable} est
                    LEFT JOIN {$this->encountersTable} e ON e.site_id = est.Id 
                        AND e.deleted_at IS NULL
                        AND e.occurred_on >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    WHERE est.is_active = 1
                    GROUP BY est.Id, est.establishment_name
                    ORDER BY encounters_30d DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $staffCount = $this->getClinicStaffCount((string) $row['clinic_id']);
                
                $results[] = [
                    'clinicId' => (string) $row['clinic_id'],
                    'clinicName' => $row['clinic_name'] ?? 'Unknown Clinic',
                    'encounters7d' => (int) ($row['encounters_7d'] ?? 0),
                    'encounters30d' => (int) ($row['encounters_30d'] ?? 0),
                    'avgWaitTime' => round((float) ($row['avg_wait_time'] ?? 0), 1),
                    'avgTreatmentTime' => round((float) ($row['avg_treatment_time'] ?? 0), 1),
                    'staffCount' => $staffCount
                ];
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log('SuperManagerRepository::getClinicComparison error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get staff count for a specific clinic
     * 
     * @param string $clinicId Clinic/establishment ID
     * @return int Staff count
     */
    private function getClinicStaffCount(string $clinicId): int
    {
        // Would need user-to-establishment mapping table
        // For now, return total active users divided by clinic count as estimate
        try {
            $totalStaff = 0;
            $totalClinics = 1;
            
            $sql = "SELECT COUNT(*) FROM {$this->userTable} WHERE is_active = 1";
            $stmt = $this->pdo->query($sql);
            $totalStaff = (int) $stmt->fetchColumn();
            
            $sql = "SELECT COUNT(*) FROM {$this->establishmentTable} WHERE is_active = 1";
            $stmt = $this->pdo->query($sql);
            $totalClinics = max(1, (int) $stmt->fetchColumn());
            
            return (int) ceil($totalStaff / $totalClinics);
        } catch (PDOException $e) {
            return 0;
        }
    }

    // ========================================================================
    // Approval Actions
    // ========================================================================

    /**
     * Approve a pending request
     * 
     * @param string $requestId Request ID
     * @param string $type Request type ('time_off', 'schedule_change', 'chart_review')
     * @param string $approvedBy User ID of approver
     * @return bool Success status
     */
    public function approvePending(string $requestId, string $type, string $approvedBy): bool
    {
        try {
            switch ($type) {
                case 'chart_review':
                    $sql = "UPDATE qa_review_queue 
                            SET review_status = 'approved', 
                                reviewer_id = :approved_by,
                                reviewed_at = NOW()
                            WHERE review_id = :request_id 
                            AND review_status = 'pending'";
                    break;
                    
                case 'time_off':
                    $sql = "UPDATE time_off_requests 
                            SET status = 'approved',
                                approved_by = :approved_by,
                                approved_at = NOW()
                            WHERE request_id = :request_id 
                            AND status = 'pending'";
                    break;
                    
                case 'schedule_change':
                    $sql = "UPDATE schedule_changes 
                            SET status = 'approved',
                                approved_by = :approved_by,
                                approved_at = NOW()
                            WHERE change_id = :request_id 
                            AND status = 'pending'";
                    break;
                    
                default:
                    return false;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':request_id', $requestId);
            $stmt->bindValue(':approved_by', $approvedBy);
            
            return $stmt->execute() && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('SuperManagerRepository::approvePending error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Deny a pending request
     * 
     * @param string $requestId Request ID
     * @param string $type Request type ('time_off', 'schedule_change', 'chart_review')
     * @param string $deniedBy User ID of denier
     * @param string $reason Denial reason
     * @return bool Success status
     */
    public function denyPending(string $requestId, string $type, string $deniedBy, string $reason = ''): bool
    {
        try {
            switch ($type) {
                case 'chart_review':
                    $sql = "UPDATE qa_review_queue 
                            SET review_status = 'rejected', 
                                reviewer_id = :denied_by,
                                reviewed_at = NOW(),
                                review_notes = :reason
                            WHERE review_id = :request_id 
                            AND review_status = 'pending'";
                    break;
                    
                case 'time_off':
                    $sql = "UPDATE time_off_requests 
                            SET status = 'denied',
                                approved_by = :denied_by,
                                approved_at = NOW(),
                                denial_reason = :reason
                            WHERE request_id = :request_id 
                            AND status = 'pending'";
                    break;
                    
                case 'schedule_change':
                    $sql = "UPDATE schedule_changes 
                            SET status = 'denied',
                                approved_by = :denied_by,
                                approved_at = NOW(),
                                denial_reason = :reason
                            WHERE change_id = :request_id 
                            AND status = 'pending'";
                    break;
                    
                default:
                    return false;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':request_id', $requestId);
            $stmt->bindValue(':denied_by', $deniedBy);
            $stmt->bindValue(':reason', $reason);
            
            return $stmt->execute() && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('SuperManagerRepository::denyPending error: ' . $e->getMessage());
            return false;
        }
    }

    // ========================================================================
    // Helper Methods
    // ========================================================================

    /**
     * Generate a UUID
     * 
     * @return string UUID string
     */
    private function generateUUID(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
