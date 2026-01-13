<?php
/**
 * Patient Access Service
 * 
 * Handles logging of patient access for HIPAA compliance and recent patients feature
 * Feature 1.1: Recent Patients / Recently Viewed
 */

namespace Core\Services;

use PDO;
use Exception;

class PatientAccessService extends BaseService
{
    /**
     * Constructor
     * @param PDO $db Database connection
     */
    public function __construct(\PDO $db = null)
    {
        parent::__construct();
        if ($db) {
            $this->db = $db;
        }
    }
    
    /**
     * Log patient access
     *
     * @param string $patient_id
     * @param string $user_id
     * @param string $access_type 'view' or 'edit'
     * @return bool
     */
    public function logAccess(string $patient_id, string $user_id, string $access_type = 'view'): bool
    {
        try {
            $sql = "INSERT INTO patient_access_log 
                    (user_id, patient_id, access_type, ip_address, user_agent, accessed_at)
                    VALUES (:user_id, :patient_id, :access_type, :ip_address, :user_agent, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id' => $user_id,
                'patient_id' => $patient_id,
                'access_type' => $access_type,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            // Also log to audit trail for HIPAA compliance
            $this->auditLog('PATIENT_ACCESS', [
                'patient_id' => $patient_id,
                'access_type' => $access_type
            ]);
            
            return true;
        } catch (Exception $e) {
            $this->logError('Failed to log patient access: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get recent patients for a user
     * 
     * @param string $user_id
     * @param int $limit
     * @return array
     */
    public function getRecentPatients(string $user_id, int $limit = 10): array
    {
        try {
            $sql = "
                SELECT DISTINCT
                    p.patient_id,
                    p.legal_first_name,
                    p.legal_last_name,
                    p.preferred_name,
                    pal.accessed_at,
                    pal.access_type,
                    CONCAT('MRN-', SUBSTRING(p.patient_id, 1, 8)) as mrn,
                    (SELECT MAX(e.created_at) 
                     FROM encounters e 
                     WHERE e.patient_id = p.patient_id
                     AND e.status != 'voided') as last_encounter_date,
                    (SELECT e.employer_name 
                     FROM encounters e 
                     WHERE e.patient_id = p.patient_id
                     AND e.status != 'voided'
                     ORDER BY e.created_at DESC
                     LIMIT 1) as employer_name
                FROM patient_access_log pal
                INNER JOIN patients p ON pal.patient_id = p.patient_id
                WHERE pal.user_id = :user_id
                AND p.deleted_at IS NULL
                ORDER BY pal.accessed_at DESC
                LIMIT :limit
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logError('Failed to get recent patients: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get access history for a specific patient
     * 
     * @param string $patient_id
     * @param int $limit
     * @return array
     */
    public function getPatientAccessHistory(string $patient_id, int $limit = 50): array
    {
        try {
            $sql = "
                SELECT 
                    pal.log_id,
                    pal.accessed_at,
                    pal.access_type,
                    pal.ip_address,
                    u.username,
                    CONCAT(u.first_name, ' ', u.last_name) as user_full_name
                FROM patient_access_log pal
                INNER JOIN user u ON pal.user_id = u.id
                WHERE pal.patient_id = :patient_id
                ORDER BY pal.accessed_at DESC
                LIMIT :limit
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':patient_id', $patient_id, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logError('Failed to get patient access history: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Clean up old access logs (for data retention policy)
     * 
     * @param int $days_to_keep Default 2555 (7 years for HIPAA)
     * @return int Number of records deleted
     */
    public function cleanupOldLogs(int $days_to_keep = 2555): int
    {
        try {
            $sql = "DELETE FROM patient_access_log 
                    WHERE accessed_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['days' => $days_to_keep]);
            
            $deleted = $stmt->rowCount();
            
            if ($deleted > 0) {
                $this->auditLog('PATIENT_ACCESS_LOG_CLEANUP', [
                    'records_deleted' => $deleted,
                    'retention_days' => $days_to_keep
                ]);
            }
            
            return $deleted;
        } catch (Exception $e) {
            $this->logError('Failed to cleanup patient access logs: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Audit log helper method
     */
    protected function auditLog(string $action, array $data = []): void
    {
        // This would integrate with the main audit logging system
        $this->logInfo("Audit: $action - " . json_encode($data));
    }
}