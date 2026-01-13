<?php
/**
 * DoctorRepository.php - Repository for Doctor (MRO) Dashboard Data
 * 
 * Provides data access methods for the Doctor/MRO dashboard including
 * DOT test verifications, pending orders, and verification history.
 * 
 * The Doctor role is associated with 'pclinician' role type (provider clinician)
 * and serves as the MRO interface for DOT drug testing, result verification,
 * and order signing.
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
 * Doctor Repository
 * 
 * Handles database operations for the Doctor/MRO dashboard vertical slice.
 * Provides MRO statistics, pending verifications, pending orders, and verification history.
 */
class DoctorRepository
{
    private PDO $pdo;
    
    /** @var string Patients table name */
    private string $patientsTable = 'patients';
    
    /** @var string Encounters table name */
    private string $encountersTable = 'encounters';
    
    /** @var string DOT tests table name */
    private string $dotTestsTable = 'dot_tests';
    
    /** @var string Orders table name */
    private string $ordersTable = 'encounter_orders';
    
    /** @var string Chain of custody form table name */
    private string $ccfTable = 'chainofcustodyform';
    
    /** @var string User table name */
    private string $userTable = 'user';

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
    // Doctor/MRO Statistics
    // ========================================================================

    /**
     * Get doctor statistics for the dashboard
     * 
     * Returns counts for:
     * - pendingVerifications: DOT tests awaiting MRO review
     * - ordersToSign: orders awaiting doctor signature
     * - reviewedToday: tests verified by doctor today
     * - avgTurnaroundHours: average time from collection to verification
     * 
     * @param string $doctorId Doctor user UUID
     * @return array{pendingVerifications: int, ordersToSign: int, reviewedToday: int, avgTurnaroundHours: float}
     */
    public function getDoctorStats(string $doctorId): array
    {
        try {
            $today = (new DateTime())->format('Y-m-d');
            
            // Consolidated query: Get all DOT test stats in a single query using conditional aggregation
            // This optimizes the N+1 pattern by combining pending verifications, reviewed today, and avg turnaround
            $dotStatsSql = "SELECT
                                SUM(CASE WHEN mro_review_required = 1
                                         AND mro_reviewed_at IS NULL
                                         AND status IN ('pending', 'positive')
                                    THEN 1 ELSE 0 END) AS pending_verifications,
                                SUM(CASE WHEN DATE(mro_reviewed_at) = :today
                                    THEN 1 ELSE 0 END) AS reviewed_today,
                                AVG(CASE WHEN mro_reviewed_at IS NOT NULL
                                         AND collected_at IS NOT NULL
                                         AND mro_reviewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                                    THEN TIMESTAMPDIFF(HOUR, collected_at, mro_reviewed_at)
                                    ELSE NULL END) AS avg_hours
                            FROM {$this->dotTestsTable}";
            
            $dotStmt = $this->pdo->prepare($dotStatsSql);
            $dotStmt->execute(['today' => $today]);
            $dotStats = $dotStmt->fetch(PDO::FETCH_ASSOC);
            
            $pendingVerifications = (int) ($dotStats['pending_verifications'] ?? 0);
            $reviewedToday = (int) ($dotStats['reviewed_today'] ?? 0);
            $avgTurnaroundHours = round((float) ($dotStats['avg_hours'] ?? 0), 1);
            
            // Get orders to sign count (separate table, cannot be consolidated)
            $ordersToSignSql = "SELECT COUNT(*) FROM {$this->ordersTable}
                                WHERE status = 'pending'
                                AND (signed_by IS NULL OR signed_at IS NULL)";
            $ordersStmt = $this->pdo->prepare($ordersToSignSql);
            $ordersStmt->execute();
            $ordersToSign = (int) $ordersStmt->fetchColumn();
            
            return [
                'pendingVerifications' => $pendingVerifications,
                'ordersToSign' => $ordersToSign,
                'reviewedToday' => $reviewedToday,
                'avgTurnaroundHours' => $avgTurnaroundHours
            ];
        } catch (PDOException $e) {
            error_log('DoctorRepository::getDoctorStats error: ' . $e->getMessage());
            return [
                'pendingVerifications' => 0,
                'ordersToSign' => 0,
                'reviewedToday' => 0,
                'avgTurnaroundHours' => 0.0
            ];
        }
    }

    // ========================================================================
    // Pending Verifications
    // ========================================================================

    /**
     * Get pending DOT test verifications requiring MRO review
     * 
     * Returns DOT tests that need MRO verification, ordered by collection date.
     * 
     * @param string $doctorId Doctor user UUID
     * @param int $limit Maximum number of results (default: 20)
     * @return array<int, array{
     *   id: string,
     *   patientName: string,
     *   testType: string,
     *   collectionDate: string,
     *   status: string,
     *   priority: string,
     *   chainOfCustodyStatus: string
     * }>
     */
    public function getPendingVerifications(string $doctorId, int $limit = 20): array
    {
        try {
            $sql = "SELECT 
                        dt.test_id,
                        dt.modality,
                        dt.test_type,
                        dt.specimen_id,
                        dt.collected_at,
                        dt.status,
                        dt.mro_review_required,
                        dt.results,
                        dt.created_at,
                        dt.encounter_id,
                        p.patient_id,
                        p.legal_first_name,
                        p.legal_last_name,
                        p.dob,
                        ccf.ccf_id,
                        ccf.status AS ccf_status
                    FROM {$this->dotTestsTable} dt
                    JOIN {$this->patientsTable} p ON dt.patient_id = p.patient_id
                    LEFT JOIN {$this->ccfTable} ccf ON dt.test_id = ccf.test_id
                    WHERE dt.mro_review_required = 1
                    AND dt.mro_reviewed_at IS NULL
                    AND dt.status IN ('pending', 'positive')
                    AND p.deleted_at IS NULL
                    ORDER BY 
                        CASE dt.status
                            WHEN 'positive' THEN 1
                            WHEN 'pending' THEN 2
                            ELSE 3
                        END,
                        dt.collected_at ASC,
                        dt.created_at ASC
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = $this->formatPendingVerification($row);
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log('DoctorRepository::getPendingVerifications error: ' . $e->getMessage());
            return [];
        }
    }

    // ========================================================================
    // Pending Orders
    // ========================================================================

    /**
     * Get pending orders requiring doctor signature
     * 
     * Returns orders awaiting signature, ordered by priority and date.
     * 
     * @param string $doctorId Doctor user UUID
     * @param int $limit Maximum number of results (default: 20)
     * @return array<int, array{
     *   id: string,
     *   patientName: string,
     *   orderType: string,
     *   description: string,
     *   requestDate: string,
     *   priority: string,
     *   encounterId: string
     * }>
     */
    public function getPendingOrders(string $doctorId, int $limit = 20): array
    {
        try {
            $sql = "SELECT 
                        o.order_id,
                        o.encounter_id,
                        o.order_type,
                        o.order_details,
                        o.status,
                        o.priority,
                        o.created_at,
                        e.patient_id,
                        p.legal_first_name,
                        p.legal_last_name,
                        p.dob
                    FROM {$this->ordersTable} o
                    JOIN {$this->encountersTable} e ON o.encounter_id = e.encounter_id
                    JOIN {$this->patientsTable} p ON e.patient_id = p.patient_id
                    WHERE o.status = 'pending'
                    AND (o.signed_by IS NULL OR o.signed_at IS NULL)
                    AND e.deleted_at IS NULL
                    AND p.deleted_at IS NULL
                    ORDER BY 
                        CASE o.priority 
                            WHEN 'stat' THEN 1
                            WHEN 'urgent' THEN 2
                            WHEN 'routine' THEN 3
                            ELSE 4
                        END,
                        o.created_at ASC
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = $this->formatPendingOrder($row);
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log('DoctorRepository::getPendingOrders error: ' . $e->getMessage());
            return [];
        }
    }

    // ========================================================================
    // Verification History
    // ========================================================================

    /**
     * Get verification history for recently verified tests
     * 
     * Returns tests that have been verified by MRO.
     * 
     * @param string $doctorId Doctor user UUID
     * @param int $limit Maximum number of results (default: 20)
     * @return array<int, array{
     *   id: string,
     *   patientName: string,
     *   testType: string,
     *   result: string,
     *   verifiedAt: string
     * }>
     */
    public function getVerificationHistory(string $doctorId, int $limit = 20): array
    {
        try {
            $sql = "SELECT 
                        dt.test_id,
                        dt.modality,
                        dt.test_type,
                        dt.status,
                        dt.results,
                        dt.mro_reviewed_at,
                        dt.mro_reviewed_by,
                        p.patient_id,
                        p.legal_first_name,
                        p.legal_last_name
                    FROM {$this->dotTestsTable} dt
                    JOIN {$this->patientsTable} p ON dt.patient_id = p.patient_id
                    WHERE dt.mro_reviewed_at IS NOT NULL
                    AND p.deleted_at IS NULL
                    ORDER BY dt.mro_reviewed_at DESC
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = $this->formatVerificationHistory($row);
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log('DoctorRepository::getVerificationHistory error: ' . $e->getMessage());
            return [];
        }
    }

    // ========================================================================
    // Test Details
    // ========================================================================

    /**
     * Get detailed information about a specific test for review
     * 
     * @param string $testId Test UUID
     * @return array|null Test details or null if not found
     */
    public function getTestDetails(string $testId): ?array
    {
        try {
            $sql = "SELECT 
                        dt.test_id,
                        dt.encounter_id,
                        dt.patient_id,
                        dt.modality,
                        dt.test_type,
                        dt.specimen_id,
                        dt.collected_at,
                        dt.results,
                        dt.mro_review_required,
                        dt.mro_reviewed_by,
                        dt.mro_reviewed_at,
                        dt.status,
                        dt.created_at,
                        p.legal_first_name,
                        p.legal_last_name,
                        p.dob,
                        p.phone,
                        p.email,
                        e.employer_name,
                        e.chief_complaint,
                        e.occurred_on,
                        ccf.ccf_id,
                        ccf.status AS ccf_status,
                        ccf.collector_signature,
                        ccf.donor_signature,
                        ccf.created_at AS ccf_created_at
                    FROM {$this->dotTestsTable} dt
                    JOIN {$this->patientsTable} p ON dt.patient_id = p.patient_id
                    JOIN {$this->encountersTable} e ON dt.encounter_id = e.encounter_id
                    LEFT JOIN {$this->ccfTable} ccf ON dt.test_id = ccf.test_id
                    WHERE dt.test_id = :test_id
                    AND p.deleted_at IS NULL
                    AND e.deleted_at IS NULL";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':test_id', $testId, PDO::PARAM_STR);
            $stmt->execute();
            
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$row) {
                return null;
            }
            
            return $this->formatTestDetails($row);
        } catch (PDOException $e) {
            error_log('DoctorRepository::getTestDetails error: ' . $e->getMessage());
            return null;
        }
    }

    // ========================================================================
    // Verification Actions
    // ========================================================================

    /**
     * Submit MRO verification for a DOT test
     * 
     * @param string $testId Test UUID
     * @param string $doctorId Doctor user UUID
     * @param string $result Verification result (negative, positive, cancelled, etc.)
     * @param string|null $comments MRO comments
     * @return bool Success status
     */
    public function verifyTest(string $testId, string $doctorId, string $result, ?string $comments = null): bool
    {
        try {
            $sql = "UPDATE {$this->dotTestsTable}
                    SET mro_reviewed_by = :doctor_id,
                        mro_reviewed_at = NOW(),
                        status = :result,
                        results = JSON_SET(COALESCE(results, '{}'), '$.mro_comments', :comments, '$.mro_result', :mro_result)
                    WHERE test_id = :test_id
                    AND mro_reviewed_at IS NULL";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':doctor_id', $doctorId, PDO::PARAM_STR);
            $stmt->bindValue(':result', $result, PDO::PARAM_STR);
            $stmt->bindValue(':comments', $comments, PDO::PARAM_STR);
            $stmt->bindValue(':mro_result', $result, PDO::PARAM_STR);
            $stmt->bindValue(':test_id', $testId, PDO::PARAM_STR);
            
            return $stmt->execute() && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('DoctorRepository::verifyTest error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sign an order
     * 
     * @param string $orderId Order ID
     * @param string $doctorId Doctor user UUID
     * @return bool Success status
     */
    public function signOrder(string $orderId, string $doctorId): bool
    {
        try {
            $sql = "UPDATE {$this->ordersTable}
                    SET signed_by = :doctor_id,
                        signed_at = NOW(),
                        status = 'signed'
                    WHERE order_id = :order_id
                    AND (signed_at IS NULL OR signed_by IS NULL)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':doctor_id', $doctorId, PDO::PARAM_STR);
            $stmt->bindValue(':order_id', $orderId, PDO::PARAM_STR);
            
            return $stmt->execute() && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('DoctorRepository::signOrder error: ' . $e->getMessage());
            return false;
        }
    }

    // ========================================================================
    // Monthly Statistics
    // ========================================================================

    /**
     * Get tests reviewed this month
     * 
     * @param string $doctorId Doctor user UUID
     * @return int Count of tests reviewed this month
     */
    public function getTestsReviewedThisMonth(string $doctorId): int
    {
        try {
            $sql = "SELECT COUNT(*) FROM {$this->dotTestsTable}
                    WHERE mro_reviewed_at IS NOT NULL
                    AND MONTH(mro_reviewed_at) = MONTH(NOW())
                    AND YEAR(mro_reviewed_at) = YEAR(NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('DoctorRepository::getTestsReviewedThisMonth error: ' . $e->getMessage());
            return 0;
        }
    }

    // ========================================================================
    // Formatting Methods
    // ========================================================================

    /**
     * Format pending verification row for API response
     * 
     * @param array $row Database row
     * @return array Formatted verification data
     */
    private function formatPendingVerification(array $row): array
    {
        $collectionDate = $row['collected_at'] ?? $row['created_at'];
        $priority = $this->determinePriority($row['status'], $collectionDate);
        $ccfStatus = $this->determineCcfStatus($row['ccf_status'] ?? null, $row['ccf_id'] ?? null);
        
        return [
            'id' => $row['test_id'],
            'patientName' => trim($row['legal_first_name'] . ' ' . $row['legal_last_name']),
            'patientId' => $row['patient_id'],
            'testType' => $this->formatTestType($row['test_type'], $row['modality']),
            'collectionDate' => $this->formatIsoDateTime($collectionDate),
            'status' => $row['status'] ?? 'pending',
            'priority' => $priority,
            'chainOfCustodyStatus' => $ccfStatus,
            'encounterId' => $row['encounter_id'],
            'specimenId' => $row['specimen_id'] ?? null,
            'modality' => $row['modality'] ?? 'drug_test'
        ];
    }

    /**
     * Format pending order row for API response
     * 
     * @param array $row Database row
     * @return array Formatted order data
     */
    private function formatPendingOrder(array $row): array
    {
        $orderDetails = $row['order_details'] ?? '';
        if (is_string($orderDetails) && $this->isJson($orderDetails)) {
            $decoded = json_decode($orderDetails, true);
            $orderDetails = $decoded['description'] ?? $decoded['details'] ?? '';
        }
        
        return [
            'id' => $row['order_id'],
            'patientName' => trim($row['legal_first_name'] . ' ' . $row['legal_last_name']),
            'patientId' => $row['patient_id'] ?? null,
            'orderType' => $this->formatOrderType($row['order_type']),
            'description' => is_string($orderDetails) ? $orderDetails : '',
            'requestDate' => $this->formatIsoDateTime($row['created_at']),
            'priority' => $row['priority'] ?? 'routine',
            'encounterId' => $row['encounter_id']
        ];
    }

    /**
     * Format verification history row for API response
     * 
     * @param array $row Database row
     * @return array Formatted history data
     */
    private function formatVerificationHistory(array $row): array
    {
        $result = $row['status'] ?? 'unknown';
        $results = $row['results'] ?? null;
        
        if (is_string($results) && $this->isJson($results)) {
            $decoded = json_decode($results, true);
            $result = $decoded['mro_result'] ?? $row['status'] ?? 'unknown';
        }
        
        return [
            'id' => $row['test_id'],
            'patientName' => trim($row['legal_first_name'] . ' ' . $row['legal_last_name']),
            'patientId' => $row['patient_id'],
            'testType' => $this->formatTestType($row['test_type'], $row['modality']),
            'result' => $result,
            'verifiedAt' => $this->formatIsoDateTime($row['mro_reviewed_at'])
        ];
    }

    /**
     * Format test details for API response
     * 
     * @param array $row Database row
     * @return array Formatted test details
     */
    private function formatTestDetails(array $row): array
    {
        $results = $row['results'] ?? null;
        $parsedResults = null;
        
        if (is_string($results) && $this->isJson($results)) {
            $parsedResults = json_decode($results, true);
        }
        
        return [
            'id' => $row['test_id'],
            'encounterId' => $row['encounter_id'],
            'patient' => [
                'id' => $row['patient_id'],
                'name' => trim($row['legal_first_name'] . ' ' . $row['legal_last_name']),
                'firstName' => $row['legal_first_name'],
                'lastName' => $row['legal_last_name'],
                'dob' => $row['dob'],
                'phone' => $row['phone'] ?? null,
                'email' => $row['email'] ?? null,
            ],
            'employer' => $row['employer_name'] ?? null,
            'testInfo' => [
                'modality' => $row['modality'],
                'type' => $this->formatTestType($row['test_type'], $row['modality']),
                'specimenId' => $row['specimen_id'],
                'collectedAt' => $this->formatIsoDateTime($row['collected_at']),
                'status' => $row['status'],
            ],
            'results' => $parsedResults,
            'mroReview' => [
                'required' => (bool) ($row['mro_review_required'] ?? false),
                'reviewedBy' => $row['mro_reviewed_by'] ?? null,
                'reviewedAt' => $row['mro_reviewed_at'] ? $this->formatIsoDateTime($row['mro_reviewed_at']) : null,
            ],
            'chainOfCustody' => [
                'id' => $row['ccf_id'] ?? null,
                'status' => $this->determineCcfStatus($row['ccf_status'] ?? null, $row['ccf_id'] ?? null),
                'collectorSigned' => !empty($row['collector_signature']),
                'donorSigned' => !empty($row['donor_signature']),
                'createdAt' => $row['ccf_created_at'] ? $this->formatIsoDateTime($row['ccf_created_at']) : null,
            ],
            'encounter' => [
                'chiefComplaint' => $row['chief_complaint'] ?? null,
                'occurredOn' => $row['occurred_on'] ? $this->formatIsoDateTime($row['occurred_on']) : null,
            ],
            'createdAt' => $this->formatIsoDateTime($row['created_at']),
        ];
    }

    /**
     * Determine priority based on test status and collection time
     * 
     * @param string|null $status Test status
     * @param string|null $collectionDate Collection timestamp
     * @return string Priority level: 'normal', 'high', 'urgent'
     */
    private function determinePriority(?string $status, ?string $collectionDate): string
    {
        // Positive tests are always high priority
        if ($status === 'positive') {
            return 'urgent';
        }
        
        if (empty($collectionDate)) {
            return 'normal';
        }
        
        try {
            $collection = new DateTime($collectionDate);
            $now = new DateTime();
            $diff = $now->diff($collection);
            
            $totalHours = ($diff->days * 24) + $diff->h;
            
            // Priority thresholds for MRO review
            if ($totalHours >= 48) {
                return 'urgent';
            } elseif ($totalHours >= 24) {
                return 'high';
            }
            
            return 'normal';
        } catch (\Exception $e) {
            return 'normal';
        }
    }

    /**
     * Determine chain of custody status
     * 
     * @param string|null $ccfStatus CCF status from database
     * @param string|null $ccfId CCF ID
     * @return string Chain of custody status
     */
    private function determineCcfStatus(?string $ccfStatus, ?string $ccfId): string
    {
        if (empty($ccfId)) {
            return 'not_started';
        }
        
        if (empty($ccfStatus)) {
            return 'pending';
        }
        
        return match (strtolower($ccfStatus)) {
            'complete', 'completed', 'verified' => 'verified',
            'partial', 'in_progress' => 'in_progress',
            'failed', 'invalid' => 'failed',
            default => 'pending'
        };
    }

    /**
     * Format test type for display
     * 
     * @param string|null $testType Test type from database
     * @param string|null $modality Test modality
     * @return string Formatted test type name
     */
    private function formatTestType(?string $testType, ?string $modality): string
    {
        if (!empty($testType)) {
            return $testType;
        }
        
        return match ($modality) {
            'drug_test' => '5-Panel Drug Screen',
            'alcohol_test' => 'Alcohol Test',
            default => 'DOT Test'
        };
    }

    /**
     * Format order type for display
     * 
     * @param string|null $orderType Order type from database
     * @return string Formatted order type name
     */
    private function formatOrderType(?string $orderType): string
    {
        if (empty($orderType)) {
            return 'General Order';
        }
        
        return match (strtolower($orderType)) {
            'lab' => 'Lab Order',
            'rx', 'prescription' => 'Prescription',
            'imaging' => 'Imaging Order',
            'referral' => 'Referral',
            'procedure' => 'Procedure Order',
            default => ucfirst($orderType)
        };
    }

    /**
     * Format datetime as ISO 8601 string
     * 
     * @param string|null $datetime Datetime value
     * @return string ISO 8601 formatted string
     */
    private function formatIsoDateTime(?string $datetime): string
    {
        if (empty($datetime)) {
            return (new DateTime())->format('c');
        }
        
        try {
            $dt = new DateTime($datetime);
            return $dt->format('c');
        } catch (\Exception $e) {
            return (new DateTime())->format('c');
        }
    }

    /**
     * Check if a string is valid JSON
     * 
     * @param string $string String to check
     * @return bool True if valid JSON
     */
    private function isJson(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
