<?php
/**
 * Audit Log Repository
 * 
 * Handles all audit log-related database operations
 */

namespace App\Repositories;

use PDO;

class AuditLogRepository extends BaseRepository
{
    protected string $table = 'AuditEvent';
    protected string $primaryKey = 'audit_id';
    
    /**
     * Create audit log entry
     * 
     * @param array $data
     * @return string|false Audit ID or false on failure
     */
    public function createAuditLog(array $data)
    {
        $auditId = $data['audit_id'] ?? $this->generateUuid();
        
        $auditData = [
            'audit_id' => $auditId,
            'user_id' => $data['user_id'] ?? null,
            'subject_type' => $data['subject_type'],
            'subject_id' => $data['subject_id'] ?? null,
            'action' => $data['action'],
            'occurred_at' => $data['occurred_at'] ?? date('Y-m-d H:i:s.u'),
            'source_ip' => $data['source_ip'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'details' => $data['details'] ?? null,
            'flagged' => $data['flagged'] ?? 0
        ];
        
        return $this->insert($auditData) ? $auditId : false;
    }
    
    /**
     * Get audit trail for a specific subject
     * 
     * @param string $subjectType
     * @param string $subjectId
     * @param int $limit
     * @return array
     */
    public function getAuditTrail(string $subjectType, string $subjectId, int $limit = 100): array
    {
        $sql = "SELECT 
                    ae.*,
                    u.username,
                    u.email as user_email
                FROM {$this->table} ae
                LEFT JOIN user u ON ae.user_id = u.user_id
                WHERE ae.subject_type = :subject_type 
                AND ae.subject_id = :subject_id
                ORDER BY ae.occurred_at DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':subject_type', $subjectType);
        $stmt->bindValue(':subject_id', $subjectId);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get audit logs by action
     * 
     * @param string $action
     * @param array $filters
     * @param int $limit
     * @return array
     */
    public function getByAction(string $action, array $filters = [], int $limit = 100): array
    {
        $sql = "SELECT 
                    ae.*,
                    u.username,
                    u.email as user_email
                FROM {$this->table} ae
                LEFT JOIN user u ON ae.user_id = u.user_id
                WHERE ae.action = :action";
        
        $params = ['action' => $action];
        
        // Add date range filter
        if (isset($filters['start_date'])) {
            $sql .= " AND ae.occurred_at >= :start_date";
            $params['start_date'] = $filters['start_date'];
        }
        
        if (isset($filters['end_date'])) {
            $sql .= " AND ae.occurred_at <= :end_date";
            $params['end_date'] = $filters['end_date'];
        }
        
        // Add user filter
        if (isset($filters['user_id'])) {
            $sql .= " AND ae.user_id = :user_id";
            $params['user_id'] = $filters['user_id'];
        }
        
        // Add flagged filter
        if (isset($filters['flagged'])) {
            $sql .= " AND ae.flagged = :flagged";
            $params['flagged'] = $filters['flagged'];
        }
        
        $sql .= " ORDER BY ae.occurred_at DESC LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get flagged audit events
     * 
     * @param int $limit
     * @return array
     */
    public function getFlaggedEvents(int $limit = 100): array
    {
        $sql = "SELECT 
                    ae.*,
                    u.username,
                    u.email as user_email
                FROM {$this->table} ae
                LEFT JOIN user u ON ae.user_id = u.user_id
                WHERE ae.flagged = 1
                ORDER BY ae.occurred_at DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get user activity summary
     * 
     * @param string $userId
     * @param int $days Number of days to look back
     * @return array
     */
    public function getUserActivitySummary(string $userId, int $days = 30): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_actions,
                    COUNT(DISTINCT DATE(occurred_at)) as active_days,
                    COUNT(DISTINCT subject_type) as unique_subjects,
                    MAX(occurred_at) as last_activity,
                    SUM(CASE WHEN flagged = 1 THEN 1 ELSE 0 END) as flagged_actions
                FROM {$this->table}
                WHERE user_id = :user_id
                AND occurred_at >= DATE_SUB(NOW(), INTERVAL :days DAY)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'days' => $days
        ]);
        
        return $stmt->fetch() ?: [
            'total_actions' => 0,
            'active_days' => 0,
            'unique_subjects' => 0,
            'last_activity' => null,
            'flagged_actions' => 0
        ];
    }
    
    /**
     * Get audit statistics by action
     * 
     * @param int $days Number of days to look back
     * @return array
     */
    public function getActionStatistics(int $days = 30): array
    {
        $sql = "SELECT 
                    action,
                    COUNT(*) as count,
                    COUNT(DISTINCT user_id) as unique_users,
                    SUM(CASE WHEN flagged = 1 THEN 1 ELSE 0 END) as flagged_count
                FROM {$this->table}
                WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY action
                ORDER BY count DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['days' => $days]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Search audit logs
     * 
     * @param array $criteria
     * @param int $limit
     * @return array
     */
    public function searchAuditLogs(array $criteria, int $limit = 100): array
    {
        $sql = "SELECT 
                    ae.*,
                    u.username,
                    u.email as user_email
                FROM {$this->table} ae
                LEFT JOIN user u ON ae.user_id = u.user_id
                WHERE 1=1";
        
        $params = [];
        
        // Build dynamic WHERE clause
        if (!empty($criteria['action'])) {
            $sql .= " AND ae.action LIKE :action";
            $params['action'] = '%' . $criteria['action'] . '%';
        }
        
        if (!empty($criteria['subject_type'])) {
            $sql .= " AND ae.subject_type = :subject_type";
            $params['subject_type'] = $criteria['subject_type'];
        }
        
        if (!empty($criteria['user_id'])) {
            $sql .= " AND ae.user_id = :user_id";
            $params['user_id'] = $criteria['user_id'];
        }
        
        if (!empty($criteria['ip_address'])) {
            $sql .= " AND ae.source_ip = :ip_address";
            $params['ip_address'] = $criteria['ip_address'];
        }
        
        if (!empty($criteria['details'])) {
            $sql .= " AND ae.details LIKE :details";
            $params['details'] = '%' . $criteria['details'] . '%';
        }
        
        if (isset($criteria['flagged'])) {
            $sql .= " AND ae.flagged = :flagged";
            $params['flagged'] = $criteria['flagged'] ? 1 : 0;
        }
        
        // Date range
        if (!empty($criteria['date_from'])) {
            $sql .= " AND ae.occurred_at >= :date_from";
            $params['date_from'] = $criteria['date_from'];
        }
        
        if (!empty($criteria['date_to'])) {
            $sql .= " AND ae.occurred_at <= :date_to";
            $params['date_to'] = $criteria['date_to'];
        }
        
        $sql .= " ORDER BY ae.occurred_at DESC LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Clean up old audit logs
     * 
     * @param int $retentionDays Number of days to retain logs
     * @return int Number of deleted records
     */
    public function cleanupOldLogs(int $retentionDays): int
    {
        $sql = "DELETE FROM {$this->table}
                WHERE occurred_at < DATE_SUB(NOW(), INTERVAL :days DAY)
                AND flagged = 0";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['days' => $retentionDays]);
        
        return $stmt->rowCount();
    }
    
    /**
     * Get PHI access logs
     * 
     * @param string $patientId
     * @param int $days
     * @return array
     */
    public function getPhiAccessLogs(string $patientId, int $days = 30): array
    {
        $sql = "SELECT 
                    ae.*,
                    u.username,
                    u.email as user_email,
                    JSON_EXTRACT(ae.details, '$.fields') as fields_accessed,
                    JSON_EXTRACT(ae.details, '$.purpose') as access_purpose
                FROM {$this->table} ae
                LEFT JOIN user u ON ae.user_id = u.user_id
                WHERE ae.action = 'PHI_ACCESS'
                AND ae.subject_type = 'Patient'
                AND ae.subject_id = :patient_id
                AND ae.occurred_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                ORDER BY ae.occurred_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'patient_id' => $patientId,
            'days' => $days
        ]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get security events
     * 
     * @param array $eventTypes
     * @param int $hours
     * @return array
     */
    public function getSecurityEvents(array $eventTypes = [], int $hours = 24): array
    {
        $sql = "SELECT 
                    ae.*,
                    u.username,
                    u.email as user_email
                FROM {$this->table} ae
                LEFT JOIN user u ON ae.user_id = u.user_id
                WHERE ae.occurred_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)";
        
        if (!empty($eventTypes)) {
            $placeholders = array_map(fn($i) => ":type_$i", array_keys($eventTypes));
            $sql .= " AND ae.action IN (" . implode(', ', $placeholders) . ")";
        }
        
        $sql .= " ORDER BY ae.occurred_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':hours', $hours, PDO::PARAM_INT);
        
        foreach ($eventTypes as $i => $type) {
            $stmt->bindValue(":type_$i", $type);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
}