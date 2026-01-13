<?php
/**
 * PrivacyOfficerRepository.php - Repository for Privacy Officer Dashboard Data
 * 
 * Provides data access methods for the privacy officer dashboard including
 * HIPAA compliance KPIs, PHI access logs, consent status, regulatory updates,
 * and training compliance tracking.
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
 * Privacy Officer Repository
 * 
 * Handles database operations for the privacy officer dashboard vertical slice.
 * Provides HIPAA compliance metrics, PHI access audit logs, consent management,
 * regulatory updates, and staff training compliance data.
 */
class PrivacyOfficerRepository
{
    private PDO $pdo;
    
    /** @var string Audit log table name */
    private string $auditLogTable = 'audit_log';
    
    /** @var string Consents table name */
    private string $consentsTable = 'consents';
    
    /** @var string Compliance KPIs table name */
    private string $complianceKpisTable = 'compliance_kpis';
    
    /** @var string Compliance KPI values table name */
    private string $complianceKpiValuesTable = 'compliance_kpi_values';
    
    /** @var string Compliance alerts table name */
    private string $complianceAlertsTable = 'compliance_alerts';
    
    /** @var string Training requirements table name */
    private string $trainingRequirementsTable = 'training_requirements';
    
    /** @var string Staff training records table name */
    private string $staffTrainingRecordsTable = 'staff_training_records';
    
    /** @var string Regulatory updates table name */
    private string $regulatoryUpdatesTable = 'regulatory_updates';
    
    /** @var string Patients table name */
    private string $patientsTable = 'patients';
    
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

    /**
     * Get compliance KPIs for the dashboard
     * 
     * Returns key compliance metrics:
     * - trainingCompletion: percentage of staff with current HIPAA training
     * - consentCompliance: percentage of patients with valid consents
     * - breachCount: number of reported breaches (current year)
     * - overallScore: calculated overall compliance score
     * 
     * @return array{trainingCompletion: float, consentCompliance: float, breachCount: int, overallScore: float}
     */
    public function getComplianceKPIs(): array
    {
        try {
            // Calculate training completion rate
            $trainingCompletion = $this->calculateTrainingCompletionRate();
            
            // Calculate consent compliance rate
            $consentCompliance = $this->calculateConsentComplianceRate();
            
            // Get breach count (using compliance alerts with type 'breach')
            $breachCount = $this->getBreachCount();
            
            // Calculate overall compliance score (weighted average)
            $overallScore = ($trainingCompletion * 0.4) + ($consentCompliance * 0.5) + 
                           (($breachCount === 0 ? 100 : max(0, 100 - ($breachCount * 10))) * 0.1);
            
            return [
                'trainingCompletion' => round($trainingCompletion, 1),
                'consentCompliance' => round($consentCompliance, 1),
                'breachCount' => $breachCount,
                'overallScore' => round($overallScore, 1)
            ];
        } catch (PDOException $e) {
            error_log('PrivacyOfficerRepository::getComplianceKPIs error: ' . $e->getMessage());
            return [
                'trainingCompletion' => 0.0,
                'consentCompliance' => 0.0,
                'breachCount' => 0,
                'overallScore' => 0.0
            ];
        }
    }

    /**
     * Get PHI access logs
     * 
     * Returns recent PHI access events from the audit log.
     * PHI-related tables are filtered to show only relevant access.
     * 
     * @param int $limit Maximum number of results (default: 20)
     * @return array<int, array{
     *   id: string,
     *   accessorName: string,
     *   accessorRole: string,
     *   patientId: string|null,
     *   accessType: string,
     *   reason: string,
     *   timestamp: string
     * }>
     */
    public function getPHIAccessLogs(int $limit = 20): array
    {
        try {
            // PHI-related tables to track
            $phiTables = ['patients', 'encounters', 'encounter_observations', 
                         'patient_allergies', 'patient_medications', 'patient_conditions',
                         'patient_immunizations', 'consents'];
            
            $placeholders = implode(',', array_fill(0, count($phiTables), '?'));
            
            $sql = "SELECT 
                        al.log_id,
                        al.table_name,
                        al.record_id,
                        al.action,
                        al.user_id,
                        al.user_role,
                        al.logged_at,
                        al.context,
                        u.username
                    FROM {$this->auditLogTable} al
                    LEFT JOIN {$this->userTable} u ON al.user_id = u.user_id
                    WHERE al.table_name IN ({$placeholders})
                    ORDER BY al.logged_at DESC
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            
            // Bind PHI table names
            foreach ($phiTables as $index => $table) {
                $stmt->bindValue($index + 1, $table, PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = $this->formatAccessLog($row);
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log('PrivacyOfficerRepository::getPHIAccessLogs error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get consent status overview
     * 
     * Returns patient consent records with their current status.
     * 
     * @param int $limit Maximum number of results (default: 20)
     * @return array<int, array{
     *   patientId: string,
     *   patientName: string,
     *   consentType: string,
     *   status: string,
     *   lastUpdated: string
     * }>
     */
    public function getConsentStatus(int $limit = 20): array
    {
        try {
            $sql = "SELECT 
                        c.consent_id,
                        c.patient_id,
                        c.consent_type,
                        c.status,
                        c.consented_at,
                        c.expires_at,
                        c.updated_at,
                        p.legal_first_name,
                        p.legal_last_name
                    FROM {$this->consentsTable} c
                    JOIN {$this->patientsTable} p ON c.patient_id = p.patient_id
                    WHERE p.deleted_at IS NULL
                    ORDER BY c.updated_at DESC, c.consented_at DESC
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = $this->formatConsentStatus($row);
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log('PrivacyOfficerRepository::getConsentStatus error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get regulatory updates
     * 
     * Returns recent HIPAA/regulatory updates that need attention.
     * 
     * @param int $limit Maximum number of results (default: 10)
     * @return array<int, array{
     *   id: string,
     *   title: string,
     *   summary: string,
     *   effectiveDate: string,
     *   priority: string,
     *   source: string,
     *   status: string
     * }>
     */
    public function getRegulatoryUpdates(int $limit = 10): array
    {
        try {
            $sql = "SELECT 
                        update_id,
                        title,
                        summary,
                        source,
                        effective_date,
                        priority,
                        status,
                        created_at
                    FROM {$this->regulatoryUpdatesTable}
                    WHERE status != 'archived'
                    ORDER BY 
                        CASE priority 
                            WHEN 'critical' THEN 1
                            WHEN 'high' THEN 2
                            WHEN 'medium' THEN 3
                            WHEN 'low' THEN 4
                            ELSE 5
                        END,
                        effective_date ASC,
                        created_at DESC
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = $this->formatRegulatoryUpdate($row);
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log('PrivacyOfficerRepository::getRegulatoryUpdates error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get breach incidents
     * 
     * Returns security breach incidents from compliance alerts.
     * 
     * @param int $limit Maximum number of results (default: 10)
     * @return array<int, array{
     *   id: string,
     *   title: string,
     *   severity: string,
     *   status: string,
     *   reportedAt: string,
     *   description: string
     * }>
     */
    public function getBreachIncidents(int $limit = 10): array
    {
        try {
            $sql = "SELECT 
                        alert_id,
                        title,
                        message,
                        severity,
                        status,
                        created_at,
                        acknowledged_at
                    FROM {$this->complianceAlertsTable}
                    WHERE alert_type = 'breach'
                    ORDER BY created_at DESC
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = $this->formatBreachIncident($row);
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log('PrivacyOfficerRepository::getBreachIncidents error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get training compliance data
     * 
     * Returns staff HIPAA training compliance status.
     * 
     * @param int $limit Maximum number of results (default: 20)
     * @return array{
     *   stats: array{total: int, compliant: int, expiringSoon: int, overdue: int},
     *   records: array
     * }
     */
    public function getTrainingCompliance(int $limit = 20): array
    {
        try {
            // Get HIPAA-related training requirements
            $requirementsSql = "SELECT requirement_id, title, frequency_days 
                               FROM {$this->trainingRequirementsTable}
                               WHERE is_active = 1 
                               AND (category = 'hipaa' OR category = 'compliance' OR category = 'privacy')";
            
            $reqStmt = $this->pdo->prepare($requirementsSql);
            $reqStmt->execute();
            $requirements = $reqStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($requirements)) {
                return [
                    'stats' => ['total' => 0, 'compliant' => 0, 'expiringSoon' => 0, 'overdue' => 0],
                    'records' => []
                ];
            }
            
            $requirementIds = array_column($requirements, 'requirement_id');
            $placeholders = implode(',', array_fill(0, count($requirementIds), '?'));
            
            // Get training records with user info
            $sql = "SELECT 
                        str.record_id,
                        str.user_id,
                        str.requirement_id,
                        str.completed_at,
                        str.expires_at,
                        str.status,
                        u.username,
                        tr.title as training_title,
                        tr.frequency_days
                    FROM {$this->staffTrainingRecordsTable} str
                    JOIN {$this->userTable} u ON str.user_id = u.user_id
                    JOIN {$this->trainingRequirementsTable} tr ON str.requirement_id = tr.requirement_id
                    WHERE str.requirement_id IN ({$placeholders})
                    AND u.is_active = 1
                    ORDER BY str.expires_at ASC
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            foreach ($requirementIds as $index => $id) {
                $stmt->bindValue($index + 1, $id, PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $records = [];
            $stats = ['total' => 0, 'compliant' => 0, 'expiringSoon' => 0, 'overdue' => 0];
            $now = new DateTime();
            $thirtyDaysFromNow = (new DateTime())->modify('+30 days');
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $stats['total']++;
                
                $expiresAt = $row['expires_at'] ? new DateTime($row['expires_at']) : null;
                
                if ($row['status'] === 'completed' && $expiresAt && $expiresAt > $now) {
                    $stats['compliant']++;
                    if ($expiresAt <= $thirtyDaysFromNow) {
                        $stats['expiringSoon']++;
                    }
                } elseif (!$expiresAt || $expiresAt <= $now) {
                    $stats['overdue']++;
                }
                
                $records[] = $this->formatTrainingRecord($row);
            }
            
            return [
                'stats' => $stats,
                'records' => $records
            ];
        } catch (PDOException $e) {
            error_log('PrivacyOfficerRepository::getTrainingCompliance error: ' . $e->getMessage());
            return [
                'stats' => ['total' => 0, 'compliant' => 0, 'expiringSoon' => 0, 'overdue' => 0],
                'records' => []
            ];
        }
    }

    /**
     * Calculate training completion rate
     * 
     * @return float Percentage of staff with current HIPAA training (0-100)
     */
    private function calculateTrainingCompletionRate(): float
    {
        try {
            // Count active users
            $totalUsersSql = "SELECT COUNT(*) FROM {$this->userTable} WHERE is_active = 1";
            $totalStmt = $this->pdo->prepare($totalUsersSql);
            $totalStmt->execute();
            $totalUsers = (int) $totalStmt->fetchColumn();
            
            if ($totalUsers === 0) {
                return 100.0; // No users means 100% compliant
            }
            
            // Count users with current HIPAA training
            $compliantSql = "SELECT COUNT(DISTINCT str.user_id) 
                            FROM {$this->staffTrainingRecordsTable} str
                            JOIN {$this->trainingRequirementsTable} tr ON str.requirement_id = tr.requirement_id
                            JOIN {$this->userTable} u ON str.user_id = u.user_id
                            WHERE u.is_active = 1
                            AND tr.is_active = 1
                            AND (tr.category = 'hipaa' OR tr.category = 'compliance' OR tr.category = 'privacy')
                            AND str.status = 'completed'
                            AND (str.expires_at IS NULL OR str.expires_at > NOW())";
            
            $compliantStmt = $this->pdo->prepare($compliantSql);
            $compliantStmt->execute();
            $compliantUsers = (int) $compliantStmt->fetchColumn();
            
            return ($compliantUsers / $totalUsers) * 100;
        } catch (PDOException $e) {
            error_log('calculateTrainingCompletionRate error: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Calculate consent compliance rate
     * 
     * @return float Percentage of patients with valid consents (0-100)
     */
    private function calculateConsentComplianceRate(): float
    {
        try {
            // Count active patients
            $totalPatientsSql = "SELECT COUNT(*) FROM {$this->patientsTable} WHERE deleted_at IS NULL";
            $totalStmt = $this->pdo->prepare($totalPatientsSql);
            $totalStmt->execute();
            $totalPatients = (int) $totalStmt->fetchColumn();
            
            if ($totalPatients === 0) {
                return 100.0; // No patients means 100% compliant
            }
            
            // Count patients with valid treatment consent
            $compliantSql = "SELECT COUNT(DISTINCT c.patient_id) 
                            FROM {$this->consentsTable} c
                            JOIN {$this->patientsTable} p ON c.patient_id = p.patient_id
                            WHERE p.deleted_at IS NULL
                            AND c.consent_type = 'treatment'
                            AND c.status = 'granted'
                            AND (c.expires_at IS NULL OR c.expires_at > NOW())";
            
            $compliantStmt = $this->pdo->prepare($compliantSql);
            $compliantStmt->execute();
            $compliantPatients = (int) $compliantStmt->fetchColumn();
            
            return ($compliantPatients / $totalPatients) * 100;
        } catch (PDOException $e) {
            error_log('calculateConsentComplianceRate error: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Get breach count for current year
     * 
     * @return int Number of breach incidents
     */
    private function getBreachCount(): int
    {
        try {
            $currentYear = (new DateTime())->format('Y');
            
            $sql = "SELECT COUNT(*) 
                    FROM {$this->complianceAlertsTable}
                    WHERE alert_type = 'breach'
                    AND YEAR(created_at) = :year";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['year' => $currentYear]);
            
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('getBreachCount error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Format access log row for API response
     * 
     * @param array $row Database row
     * @return array Formatted access log data
     */
    private function formatAccessLog(array $row): array
    {
        return [
            'id' => (string) $row['log_id'],
            'accessorName' => $row['username'] ?? 'Unknown User',
            'accessorRole' => $row['user_role'] ?? 'unknown',
            'patientId' => $row['table_name'] === 'patients' ? $row['record_id'] : null,
            'accessType' => $row['action'],
            'reason' => $row['context'] ?? 'treatment',
            'tableName' => $row['table_name'],
            'timestamp' => $this->formatIsoDateTime($row['logged_at'])
        ];
    }

    /**
     * Format consent status row for API response
     * 
     * @param array $row Database row
     * @return array Formatted consent data
     */
    private function formatConsentStatus(array $row): array
    {
        $status = $row['status'];
        
        // Check if consent has expired
        if ($status === 'granted' && !empty($row['expires_at'])) {
            $expiresAt = new DateTime($row['expires_at']);
            if ($expiresAt <= new DateTime()) {
                $status = 'expired';
            }
        }
        
        return [
            'consentId' => (string) $row['consent_id'],
            'patientId' => $row['patient_id'],
            'patientName' => trim($row['legal_first_name'] . ' ' . $row['legal_last_name']),
            'consentType' => $row['consent_type'],
            'status' => $status,
            'lastUpdated' => $this->formatIsoDateTime($row['updated_at'] ?? $row['consented_at']),
            'expiresAt' => $row['expires_at'] ? $this->formatIsoDateTime($row['expires_at']) : null
        ];
    }

    /**
     * Format regulatory update row for API response
     * 
     * @param array $row Database row
     * @return array Formatted regulatory update data
     */
    private function formatRegulatoryUpdate(array $row): array
    {
        return [
            'id' => $row['update_id'],
            'title' => $row['title'],
            'summary' => $row['summary'] ?? '',
            'source' => $row['source'] ?? 'HIPAA',
            'effectiveDate' => $row['effective_date'],
            'priority' => $row['priority'] ?? 'medium',
            'status' => $row['status'] ?? 'pending',
            'createdAt' => $this->formatIsoDateTime($row['created_at'])
        ];
    }

    /**
     * Format breach incident row for API response
     * 
     * @param array $row Database row
     * @return array Formatted breach incident data
     */
    private function formatBreachIncident(array $row): array
    {
        return [
            'id' => $row['alert_id'],
            'title' => $row['title'],
            'description' => $row['message'] ?? '',
            'severity' => $row['severity'] ?? 'medium',
            'status' => $row['status'] ?? 'open',
            'reportedAt' => $this->formatIsoDateTime($row['created_at']),
            'acknowledgedAt' => $row['acknowledged_at'] ? $this->formatIsoDateTime($row['acknowledged_at']) : null
        ];
    }

    /**
     * Format training record row for API response
     * 
     * @param array $row Database row
     * @return array Formatted training record data
     */
    private function formatTrainingRecord(array $row): array
    {
        $now = new DateTime();
        $expiresAt = $row['expires_at'] ? new DateTime($row['expires_at']) : null;
        
        $complianceStatus = 'compliant';
        if (!$expiresAt || $expiresAt <= $now) {
            $complianceStatus = 'overdue';
        } elseif ($expiresAt <= (new DateTime())->modify('+30 days')) {
            $complianceStatus = 'expiring_soon';
        }
        
        return [
            'recordId' => $row['record_id'],
            'userId' => $row['user_id'],
            'username' => $row['username'],
            'trainingTitle' => $row['training_title'],
            'completedAt' => $row['completed_at'] ? $this->formatIsoDateTime($row['completed_at']) : null,
            'expiresAt' => $expiresAt ? $this->formatIsoDateTime($row['expires_at']) : null,
            'status' => $row['status'],
            'complianceStatus' => $complianceStatus
        ];
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
}
