<?php
namespace Core\Services;

use Core\Database;
use Core\Services\BaseService;
use PDO;
use Exception;

/**
 * AuditService - Enhanced HIPAA-Compliant Audit Logging
 *
 * Provides comprehensive audit logging for the SafeShift EHR application.
 * Supports the enhanced audit_log schema with:
 * - user_name: Human-readable username
 * - patient_id: Direct patient reference for PHI tracking
 * - modified_fields: JSON array of changed fields
 * - old_values / new_values: Before/after values for changes
 * - success: Operation success status
 * - error_message: Error details for failed operations
 *
 * @package SafeShift\Core\Services
 * @version 2.0.0
 */
class AuditService extends BaseService
{
    private $salt;
    
    /**
     * @var bool Whether enhanced schema columns are available
     */
    private ?bool $hasEnhancedSchema = null;
    
    public function __construct()
    {
        parent::__construct();
        // Use a secure salt from environment or config
        $this->salt = $this->getConfig('AUDIT_SALT', 'default_audit_salt_change_this');
    }
    
    /**
     * Check if enhanced schema columns exist
     *
     * @return bool
     */
    private function hasEnhancedSchema(): bool
    {
        if ($this->hasEnhancedSchema === null) {
            try {
                $stmt = $this->db->query("SHOW COLUMNS FROM AuditEvent LIKE 'patient_id'");
                $this->hasEnhancedSchema = $stmt->rowCount() > 0;
            } catch (Exception $e) {
                $this->hasEnhancedSchema = false;
            }
        }
        return $this->hasEnhancedSchema;
    }
    
    /**
     * Log an audit event
     *
     * @param string $actionType view, create, update, delete, export, login, logout, access_denied
     * @param string $resourceType patient, encounter, document, user, report
     * @param string $resourceId UUID or ID of the resource
     * @param string $description Human-readable description
     * @param array $metadata Additional context data (may include enhanced fields)
     * @return bool Success status
     */
    public function audit($actionType, $resourceType, $resourceId, $description = '', $metadata = [])
    {
        try {
            $userId = $_SESSION['user']['user_id'] ?? $_SESSION['id'] ?? $_SESSION['user_id'] ?? null;
            $ipAddress = $this->getClientIp();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $sessionId = session_id();
            
            // Extract enhanced fields from metadata
            $userName = $metadata['user_name'] ?? $this->getCurrentUserName();
            $userRole = $metadata['user_role'] ?? $this->getCurrentUserRole();
            $patientId = $metadata['patient_id'] ?? null;
            $modifiedFields = $metadata['modified_fields'] ?? null;
            $oldValues = $metadata['old_values'] ?? null;
            $newValues = $metadata['new_values'] ?? null;
            $success = $metadata['success'] ?? true;
            $errorMessage = $metadata['error_message'] ?? null;
            
            // Remove enhanced fields from metadata to avoid duplication
            $cleanMetadata = array_diff_key($metadata, array_flip([
                'user_name', 'user_role', 'patient_id', 'modified_fields',
                'old_values', 'new_values', 'success', 'error_message'
            ]));
            
            // Prepare audit data for checksum
            $auditData = [
                'user_id' => $userId,
                'action_type' => $actionType,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'description' => $description,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'session_id' => $sessionId,
                'metadata' => json_encode($cleanMetadata)
            ];
            
            // Calculate checksum for tamper detection
            $checksum = $this->calculateChecksum($auditData);
            
            // Build details JSON
            $details = [
                'description' => $description,
                'metadata' => $cleanMetadata
            ];
            
            // Use enhanced schema if available
            if ($this->hasEnhancedSchema()) {
                $stmt = $this->db->prepare("
                    INSERT INTO AuditEvent (
                        audit_id, user_id, user_name, user_role, action, subject_type, subject_id,
                        details, source_ip, user_agent, session_id, patient_id,
                        modified_fields, old_values, new_values, success, error_message,
                        checksum, occurred_at, flagged
                    ) VALUES (
                        UUID(), :user_id, :user_name, :user_role, :action_type, :resource_type, :resource_id,
                        :details, :ip_address, :user_agent, :session_id, :patient_id,
                        :modified_fields, :old_values, :new_values, :success, :error_message,
                        :checksum, NOW(6), 0
                    )
                ");
                
                $stmt->execute([
                    'user_id' => $userId,
                    'user_name' => $userName,
                    'user_role' => $userRole,
                    'action_type' => $actionType,
                    'resource_type' => $resourceType,
                    'resource_id' => $resourceId,
                    'details' => json_encode($details),
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'session_id' => $sessionId,
                    'patient_id' => $patientId,
                    'modified_fields' => $modifiedFields ? json_encode($modifiedFields) : null,
                    'old_values' => $oldValues ? json_encode($oldValues) : null,
                    'new_values' => $newValues ? json_encode($newValues) : null,
                    'success' => $success ? 1 : 0,
                    'error_message' => $errorMessage,
                    'checksum' => $checksum
                ]);
            } else {
                // Fall back to original schema (include enhanced data in details)
                $details['user_name'] = $userName;
                $details['user_role'] = $userRole;
                $details['patient_id'] = $patientId;
                $details['modified_fields'] = $modifiedFields;
                $details['old_values'] = $oldValues;
                $details['new_values'] = $newValues;
                $details['success'] = $success;
                $details['error_message'] = $errorMessage;
                
                $stmt = $this->db->prepare("
                    INSERT INTO AuditEvent (
                        audit_id, user_id, action, subject_type, subject_id,
                        details, source_ip, user_agent, session_id, checksum,
                        occurred_at, flagged
                    ) VALUES (
                        UUID(), :user_id, :action_type, :resource_type, :resource_id,
                        :details, :ip_address, :user_agent, :session_id, :checksum,
                        NOW(6), 0
                    )
                ");
                
                $stmt->execute([
                    'user_id' => $userId,
                    'action_type' => $actionType,
                    'resource_type' => $resourceType,
                    'resource_id' => $resourceId,
                    'details' => json_encode($details),
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'session_id' => $sessionId,
                    'checksum' => $checksum
                ]);
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logError("Failed to create audit log: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get current user's display name from session
     *
     * @return string|null
     */
    private function getCurrentUserName(): ?string
    {
        if (isset($_SESSION['user']['first_name'], $_SESSION['user']['last_name'])) {
            return trim($_SESSION['user']['first_name'] . ' ' . $_SESSION['user']['last_name']);
        }
        if (isset($_SESSION['username'])) {
            return $_SESSION['username'];
        }
        if (isset($_SESSION['user']['username'])) {
            return $_SESSION['user']['username'];
        }
        return null;
    }
    
    /**
     * Log a failed access attempt
     */
    public function logAccessDenied($resourceType, $resourceId, $reason = '', $patientId = null)
    {
        $this->audit('access_denied', $resourceType, $resourceId,
            "Access denied: " . $reason,
            [
                'reason' => $reason,
                'patient_id' => $patientId,
                'success' => false,
                'error_message' => $reason
            ]);
    }
    
    /**
     * Log a CREATE operation with enhanced fields
     *
     * @param string $resourceType Type of resource created
     * @param string $resourceId ID of the created resource
     * @param array $newValues The new values that were created
     * @param string|int|null $patientId Patient ID for PHI tracking
     * @param string $description Optional description
     * @return bool Success status
     */
    public function logCreate($resourceType, $resourceId, array $newValues = [], $patientId = null, $description = '')
    {
        return $this->audit(
            'create',
            $resourceType,
            $resourceId,
            $description ?: "Created $resourceType record",
            [
                'patient_id' => $patientId,
                'modified_fields' => array_keys($newValues),
                'new_values' => $newValues,
                'success' => true
            ]
        );
    }
    
    /**
     * Log a READ operation with enhanced fields
     *
     * @param string $resourceType Type of resource read
     * @param string $resourceId ID of the resource
     * @param string|int|null $patientId Patient ID for PHI tracking
     * @param string $description Optional description
     * @return bool Success status
     */
    public function logRead($resourceType, $resourceId, $patientId = null, $description = '')
    {
        return $this->audit(
            'read',
            $resourceType,
            $resourceId,
            $description ?: "Accessed $resourceType record",
            [
                'patient_id' => $patientId,
                'success' => true
            ]
        );
    }
    
    /**
     * Log an UPDATE operation with enhanced fields
     *
     * @param string $resourceType Type of resource updated
     * @param string $resourceId ID of the resource
     * @param array $oldValues Values before update
     * @param array $newValues Values after update
     * @param array|null $modifiedFields Fields that changed (auto-calculated if null)
     * @param string|int|null $patientId Patient ID for PHI tracking
     * @param string $description Optional description
     * @return bool Success status
     */
    public function logUpdate($resourceType, $resourceId, array $oldValues, array $newValues, ?array $modifiedFields = null, $patientId = null, $description = '')
    {
        // Auto-calculate modified fields if not provided
        if ($modifiedFields === null) {
            $modifiedFields = $this->calculateModifiedFields($oldValues, $newValues);
        }
        
        return $this->audit(
            'update',
            $resourceType,
            $resourceId,
            $description ?: "Updated $resourceType record",
            [
                'patient_id' => $patientId,
                'modified_fields' => $modifiedFields,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'success' => true
            ]
        );
    }
    
    /**
     * Log a DELETE operation with enhanced fields
     *
     * @param string $resourceType Type of resource deleted
     * @param string $resourceId ID of the resource
     * @param array $oldValues The values of the deleted record
     * @param string|int|null $patientId Patient ID for PHI tracking
     * @param string $description Optional description
     * @return bool Success status
     */
    public function logDelete($resourceType, $resourceId, array $oldValues = [], $patientId = null, $description = '')
    {
        return $this->audit(
            'delete',
            $resourceType,
            $resourceId,
            $description ?: "Deleted $resourceType record",
            [
                'patient_id' => $patientId,
                'modified_fields' => array_keys($oldValues),
                'old_values' => $oldValues,
                'success' => true
            ]
        );
    }
    
    /**
     * Log a failed operation
     *
     * @param string $action The action that failed
     * @param string $resourceType Type of resource
     * @param string|null $resourceId ID of the resource (if known)
     * @param string $errorMessage Error message
     * @param string|int|null $patientId Patient ID for PHI tracking
     * @param array $metadata Additional context
     * @return bool Success status
     */
    public function logFailure($action, $resourceType, $resourceId = null, $errorMessage = '', $patientId = null, array $metadata = [])
    {
        return $this->audit(
            $action,
            $resourceType,
            $resourceId ?? '',
            "Failed to $action $resourceType: $errorMessage",
            array_merge($metadata, [
                'patient_id' => $patientId,
                'success' => false,
                'error_message' => $errorMessage
            ])
        );
    }
    
    /**
     * Log a search/list operation
     *
     * @param string $resourceType Type of resource searched
     * @param array $searchCriteria Search parameters used
     * @param int $resultCount Number of results
     * @param string $description Optional description
     * @return bool Success status
     */
    public function logSearch($resourceType, array $searchCriteria = [], $resultCount = 0, $description = '')
    {
        return $this->audit(
            'search',
            $resourceType,
            '',
            $description ?: "Searched $resourceType records",
            [
                'new_values' => [
                    'criteria' => $searchCriteria,
                    'result_count' => $resultCount
                ],
                'success' => true
            ]
        );
    }
    
    /**
     * Calculate which fields have been modified
     *
     * @param array $oldValues Old values
     * @param array $newValues New values
     * @return array Modified field names
     */
    private function calculateModifiedFields(array $oldValues, array $newValues): array
    {
        $modifiedFields = [];
        
        foreach ($newValues as $key => $newValue) {
            if (!array_key_exists($key, $oldValues)) {
                $modifiedFields[] = $key;
            } elseif ($this->valuesAreDifferent($oldValues[$key], $newValue)) {
                $modifiedFields[] = $key;
            }
        }
        
        foreach ($oldValues as $key => $oldValue) {
            if (!array_key_exists($key, $newValues) && !in_array($key, $modifiedFields)) {
                $modifiedFields[] = $key;
            }
        }
        
        return $modifiedFields;
    }
    
    /**
     * Check if two values are different
     *
     * @param mixed $oldValue
     * @param mixed $newValue
     * @return bool
     */
    private function valuesAreDifferent($oldValue, $newValue): bool
    {
        if ($oldValue === null && $newValue === null) return false;
        if ($oldValue === null || $newValue === null) return true;
        
        if (is_array($oldValue) && is_array($newValue)) {
            return json_encode($oldValue) !== json_encode($newValue);
        }
        
        return $oldValue !== $newValue;
    }
    
    /**
     * Log a login event
     */
    public function logLogin($userId, $success = true)
    {
        $this->audit(
            $success ? 'login' : 'login_failed',
            'user',
            $userId,
            $success ? 'User logged in successfully' : 'Failed login attempt'
        );
    }
    
    /**
     * Log a logout event
     */
    public function logLogout($userId)
    {
        $this->audit('logout', 'user', $userId, 'User logged out');
    }
    
    /**
     * Log a failed login attempt
     */
    public function logFailedLogin($username, $reason = '', $userId = null)
    {
        $this->audit(
            'login_failed',
            'user',
            $userId ?? $username,
            "Failed login attempt: $reason",
            ['username' => $username, 'reason' => $reason]
        );
    }
    
    /**
     * Log a security event
     */
    public function logSecurityEvent($eventType, $data = [])
    {
        $this->audit(
            'security_event',
            'system',
            $eventType,
            "Security event: $eventType",
            $data
        );
    }
    
    /**
     * Log dashboard access for HIPAA compliance
     *
     * @param string $dashboardName Name of the dashboard being accessed
     * @param int|string $userId User ID accessing the dashboard
     * @param array $metadata Additional context data
     * @return bool Success status
     */
    public function logDashboardAccess($dashboardName, $userId, array $metadata = [])
    {
        return $this->audit(
            'dashboard_access',
            'dashboard',
            $dashboardName,
            "User accessed dashboard: $dashboardName",
            array_merge($metadata, ['user_id' => $userId])
        );
    }
    
    /**
     * Log unauthorized access attempt for security monitoring
     *
     * @param string $resourceType Type of resource (dashboard, patient, etc.)
     * @param int|string $userId User ID who attempted access
     * @param array $metadata Additional context data
     * @return bool Success status
     */
    public function logUnauthorizedAccess($resourceType, $userId, array $metadata = [])
    {
        return $this->audit(
            'unauthorized_access',
            $resourceType,
            $userId,
            "Unauthorized access attempt to: $resourceType",
            $metadata
        );
    }
    
    /**
     * Log patient registration for HIPAA compliance
     *
     * @param int|string $patientId The newly created patient ID
     * @param string $registrationType Type of registration (quick, full, etc.)
     * @return bool Success status
     */
    public function logPatientRegistration($patientId, $registrationType = 'standard')
    {
        return $this->audit(
            'patient_registration',
            'patient',
            $patientId,
            "New patient registered via $registrationType registration",
            ['registration_type' => $registrationType]
        );
    }
    
    /**
     * Search audit logs
     */
    public function searchLogs($filters = [], $limit = 100, $offset = 0)
    {
        try {
            $query = "
                SELECT
                    ae.audit_id,
                    ae.user_id,
                    ae.action,
                    ae.subject_type,
                    ae.subject_id,
                    ae.details,
                    ae.source_ip,
                    ae.user_agent,
                    ae.session_id,
                    ae.occurred_at,
                    ae.checksum,
                    ae.flagged,
                    u.username,
                    u.first_name,
                    u.last_name
                FROM AuditEvent ae
                LEFT JOIN User u ON ae.user_id = u.user_id
                WHERE 1=1
            ";
            
            $params = [];
            
            // Apply filters
            if (!empty($filters['start_date'])) {
                $query .= " AND ae.occurred_at >= :start_date";
                $params['start_date'] = $filters['start_date'];
            }
            
            if (!empty($filters['end_date'])) {
                $query .= " AND ae.occurred_at <= :end_date";
                $params['end_date'] = $filters['end_date'] . ' 23:59:59';
            }
            
            if (!empty($filters['user_id'])) {
                $query .= " AND ae.user_id = :user_id";
                $params['user_id'] = $filters['user_id'];
            }
            
            if (!empty($filters['action_type'])) {
                $query .= " AND ae.action = :action_type";
                $params['action_type'] = $filters['action_type'];
            }
            
            if (!empty($filters['resource_type'])) {
                $query .= " AND ae.subject_type = :resource_type";
                $params['resource_type'] = $filters['resource_type'];
            }
            
            if (!empty($filters['resource_id'])) {
                $query .= " AND ae.subject_id = :resource_id";
                $params['resource_id'] = $filters['resource_id'];
            }
            
            if (!empty($filters['search'])) {
                $query .= " AND (ae.details LIKE :search OR ae.subject_id LIKE :search2)";
                $params['search'] = '%' . $filters['search'] . '%';
                $params['search2'] = '%' . $filters['search'] . '%';
            }
            
            // Order by timestamp desc
            $query .= " ORDER BY ae.occurred_at DESC";
            
            // Get total count before pagination
            $countQuery = str_replace(
                'SELECT ae.audit_id,',
                'SELECT COUNT(*) as total FROM (SELECT ae.audit_id,',
                $query
            ) . ') as t';
            
            $countStmt = $this->db->prepare($countQuery);
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $totalCount = $countStmt->fetch()['total'];
            
            // Add pagination
            $query .= " LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            $logs = $stmt->fetchAll();
            
            // Verify checksums
            foreach ($logs as &$log) {
                $log['integrity_verified'] = $this->verifyIntegrity($log);
                $log['user_display_name'] = $log['username'] ?? 'Unknown User';
                if ($log['first_name'] && $log['last_name']) {
                    $log['user_display_name'] = $log['first_name'] . ' ' . $log['last_name'];
                }
            }
            
            return [
                'logs' => $logs,
                'total' => $totalCount,
                'limit' => $limit,
                'offset' => $offset
            ];
            
        } catch (Exception $e) {
            $this->logError("Failed to search audit logs: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get audit statistics
     */
    public function getStatistics($dateRange = 30)
    {
        try {
            $stats = [];
            
            // Total events by action type
            $stmt = $this->db->prepare("
                SELECT action, COUNT(*) as count
                FROM AuditEvent
                WHERE occurred_at >= DATE_SUB(CURRENT_DATE, INTERVAL :days DAY)
                GROUP BY action
                ORDER BY count DESC
            ");
            $stmt->execute(['days' => $dateRange]);
            $stats['events_by_action'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Total events by resource type
            $stmt = $this->db->prepare("
                SELECT subject_type, COUNT(*) as count
                FROM AuditEvent
                WHERE occurred_at >= DATE_SUB(CURRENT_DATE, INTERVAL :days DAY)
                GROUP BY subject_type
                ORDER BY count DESC
            ");
            $stmt->execute(['days' => $dateRange]);
            $stats['events_by_resource'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Top users by activity
            $stmt = $this->db->prepare("
                SELECT
                    u.username,
                    COUNT(ae.audit_id) as event_count
                FROM AuditEvent ae
                JOIN User u ON ae.user_id = u.user_id
                WHERE ae.occurred_at >= DATE_SUB(CURRENT_DATE, INTERVAL :days DAY)
                GROUP BY ae.user_id
                ORDER BY event_count DESC
                LIMIT 10
            ");
            $stmt->execute(['days' => $dateRange]);
            $stats['top_users'] = $stmt->fetchAll();
            
            // Failed access attempts
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM AuditEvent
                WHERE action = 'access_denied'
                AND occurred_at >= DATE_SUB(CURRENT_DATE, INTERVAL :days DAY)
            ");
            $stmt->execute(['days' => $dateRange]);
            $stats['failed_access_attempts'] = $stmt->fetch()['count'];
            
            // Events per day
            $stmt = $this->db->prepare("
                SELECT
                    DATE(occurred_at) as date,
                    COUNT(*) as count
                FROM AuditEvent
                WHERE occurred_at >= DATE_SUB(CURRENT_DATE, INTERVAL :days DAY)
                GROUP BY DATE(occurred_at)
                ORDER BY date ASC
            ");
            $stmt->execute(['days' => $dateRange]);
            $stats['events_per_day'] = $stmt->fetchAll();
            
            return $stats;
            
        } catch (Exception $e) {
            $this->logError("Failed to get audit statistics: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Export audit logs
     */
    public function exportLogs($filters = [], $format = 'csv')
    {
        try {
            // Get all logs matching filters (no pagination for export)
            $logs = $this->searchLogs($filters, 10000, 0);
            
            switch ($format) {
                case 'csv':
                    return $this->exportToCsv($logs['logs']);
                    
                case 'pdf':
                    return $this->exportToPdf($logs['logs'], $filters);
                    
                case 'json':
                    return $this->exportToJson($logs['logs']);
                    
                default:
                    throw new Exception("Unsupported export format: $format");
            }
            
        } catch (Exception $e) {
            $this->logError("Failed to export audit logs: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Verify the integrity of a log entry
     */
    public function verifyIntegrity($log)
    {
        $auditData = [
            'user_id' => $log['user_id'],
            'action' => $log['action'],
            'subject_type' => $log['subject_type'],
            'subject_id' => $log['subject_id'],
            'details' => $log['details'],
            'source_ip' => $log['source_ip'],
            'user_agent' => $log['user_agent'],
            'session_id' => $log['session_id'],
            'occurred_at' => $log['occurred_at']
        ];
        
        $computedChecksum = $this->calculateChecksum($auditData);
        return $computedChecksum === $log['checksum'];
    }
    
    /**
     * Archive old audit logs
     */
    public function archiveLogs($olderThanDays = 730) // 2 years
    {
        try {
            $this->db->beginTransaction();
            
            // Select logs to archive
            $stmt = $this->db->prepare("
                SELECT * FROM AuditEvent
                WHERE occurred_at < DATE_SUB(CURRENT_DATE, INTERVAL :days DAY)
            ");
            $stmt->execute(['days' => $olderThanDays]);
            $logsToArchive = $stmt->fetchAll();
            
            if (count($logsToArchive) > 0) {
                // Insert into archive table
                $archiveStmt = $this->db->prepare("
                    INSERT INTO AuditEvent_archive
                    SELECT * FROM AuditEvent
                    WHERE occurred_at < DATE_SUB(CURRENT_DATE, INTERVAL :days DAY)
                ");
                $archiveStmt->execute(['days' => $olderThanDays]);
                
                // Delete from main table
                $deleteStmt = $this->db->prepare("
                    DELETE FROM AuditEvent
                    WHERE occurred_at < DATE_SUB(CURRENT_DATE, INTERVAL :days DAY)
                ");
                $deleteStmt->execute(['days' => $olderThanDays]);
                
                $this->db->commit();
                
                return count($logsToArchive);
            }
            
            $this->db->commit();
            return 0;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError("Failed to archive audit logs: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Calculate checksum for audit data
     */
    private function calculateChecksum($data)
    {
        // Remove any null values and ensure consistent ordering
        $data = array_filter($data, function($value) {
            return $value !== null;
        });
        ksort($data);
        
        // Create string representation
        $dataString = json_encode($data) . $this->salt;
        
        // Return SHA-256 hash
        return hash('sha256', $dataString);
    }
    
    /**
     * Get client IP address
     */
    private function getClientIp()
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Export to CSV
     */
    private function exportToCsv($logs)
    {
        $output = fopen('php://temp', 'r+');
        
        // Headers
        fputcsv($output, [
            'Timestamp',
            'User',
            'Action',
            'Subject Type',
            'Subject ID',
            'Details',
            'IP Address',
            'User Agent',
            'Integrity Verified'
        ]);
        
        // Data
        foreach ($logs as $log) {
            fputcsv($output, [
                $log['occurred_at'],
                $log['user_display_name'],
                $log['action'],
                $log['subject_type'],
                $log['subject_id'],
                $log['details'],
                $log['source_ip'],
                $log['user_agent'],
                $log['integrity_verified'] ? 'Yes' : 'No'
            ]);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    /**
     * Export to JSON
     */
    private function exportToJson($logs)
    {
        // Remove sensitive data
        $exportLogs = array_map(function($log) {
            unset($log['session_id']);
            unset($log['checksum']);
            return $log;
        }, $logs);
        
        return json_encode($exportLogs, JSON_PRETTY_PRINT);
    }
    
    /**
     * Export to PDF
     */
    private function exportToPdf($logs, $filters)
    {
        // This is a placeholder - in production, use TCPDF or similar
        // For now, return HTML that can be converted to PDF
        
        $html = '<html><head><style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            h1 { color: #333; }
            table { width: 100%; border-collapse: collapse; }
            th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .header { margin-bottom: 20px; }
            .footer { margin-top: 20px; font-size: 10px; color: #666; }
        </style></head><body>';
        
        $html .= '<div class="header">';
        $html .= '<h1>HIPAA Audit Trail Report</h1>';
        $html .= '<p>Generated: ' . date('Y-m-d H:i:s') . '</p>';
        
        if (!empty($filters['start_date']) || !empty($filters['end_date'])) {
            $html .= '<p>Date Range: ' . 
                ($filters['start_date'] ?? 'Beginning') . ' to ' . 
                ($filters['end_date'] ?? 'Present') . '</p>';
        }
        
        $html .= '<p>Total Records: ' . count($logs) . '</p>';
        $html .= '</div>';
        
        $html .= '<table>';
        $html .= '<tr>
            <th>Timestamp</th>
            <th>User</th>
            <th>Action</th>
            <th>Resource</th>
            <th>Description</th>
            <th>IP Address</th>
            <th>Verified</th>
        </tr>';
        
        foreach ($logs as $log) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($log['occurred_at']) . '</td>';
            $html .= '<td>' . htmlspecialchars($log['user_display_name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($log['action']) . '</td>';
            $html .= '<td>' . htmlspecialchars($log['subject_type'] . '/' . $log['subject_id']) . '</td>';
            $html .= '<td>' . htmlspecialchars($log['details']) . '</td>';
            $html .= '<td>' . htmlspecialchars($log['source_ip']) . '</td>';
            $html .= '<td>' . ($log['integrity_verified'] ? '✓' : '✗') . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</table>';
        
        $html .= '<div class="footer">';
        $html .= '<p>This is an official HIPAA audit trail report. ';
        $html .= 'Unauthorized disclosure or modification is prohibited by law.</p>';
        $html .= '<p>Report ID: ' . uniqid('AUDIT-') . '</p>';
        $html .= '</div>';
        
        $html .= '</body></html>';
        
        return $html;
    }
}