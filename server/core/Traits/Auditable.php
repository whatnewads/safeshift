<?php
/**
 * Auditable Trait
 * 
 * Provides audit logging functionality for CRUD operations.
 * Use this trait in ViewModels, Services, or Repositories that need audit logging.
 * 
 * Features:
 * - Automatic user context capture (user_id, user_name, user_role, ip_address, session_id)
 * - CRUD operation logging (Create, Read, Update, Delete)
 * - Modified fields tracking for updates
 * - Old/new value comparison
 * - Patient ID tracking for PHI access
 * - Success/failure logging
 * 
 * @package SafeShift\Core\Traits
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Core\Traits;

use Core\Services\AuditService;
use Exception;

/**
 * Trait Auditable
 * 
 * Provides comprehensive audit logging for HIPAA compliance.
 */
trait Auditable
{
    /**
     * @var AuditService|null Cached audit service instance
     */
    private ?AuditService $auditServiceInstance = null;
    
    /**
     * Get the AuditService instance
     * 
     * @return AuditService
     */
    protected function getAuditService(): AuditService
    {
        if ($this->auditServiceInstance === null) {
            $this->auditServiceInstance = new AuditService();
        }
        return $this->auditServiceInstance;
    }
    
    /**
     * Get current user context from session
     * 
     * @return array{user_id: ?string, user_name: ?string, user_role: ?string, ip_address: ?string, session_id: ?string}
     */
    protected function getUserContext(): array
    {
        $userId = $_SESSION['user']['user_id'] ?? $_SESSION['id'] ?? $_SESSION['user_id'] ?? null;
        
        // Build user name from available session data
        $userName = null;
        if (isset($_SESSION['user']['first_name'], $_SESSION['user']['last_name'])) {
            $userName = trim($_SESSION['user']['first_name'] . ' ' . $_SESSION['user']['last_name']);
        } elseif (isset($_SESSION['username'])) {
            $userName = $_SESSION['username'];
        } elseif (isset($_SESSION['user']['username'])) {
            $userName = $_SESSION['user']['username'];
        }
        
        // Get user role
        $userRole = $_SESSION['user']['role'] ?? $_SESSION['role'] ?? null;
        
        // Get IP address
        $ipAddress = $this->captureClientIp();
        
        // Get session ID
        $sessionId = session_id() ?: null;
        
        return [
            'user_id' => $userId,
            'user_name' => $userName,
            'user_role' => $userRole,
            'ip_address' => $ipAddress,
            'session_id' => $sessionId,
        ];
    }
    
    /**
     * Capture client IP address
     * 
     * @return string|null
     */
    private function captureClientIp(): ?string
    {
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }
    
    /**
     * Log a CREATE operation
     * 
     * @param string $resourceType The type of resource (e.g., 'patient', 'encounter')
     * @param string|int $resourceId The ID of the created resource
     * @param array $newValues The new values that were created
     * @param int|string|null $patientId Optional patient ID for PHI tracking
     * @param string $description Optional description of the operation
     * @return bool Success status
     */
    protected function logCreate(
        string $resourceType,
        string|int $resourceId,
        array $newValues = [],
        int|string|null $patientId = null,
        string $description = ''
    ): bool {
        return $this->logAuditEvent(
            action: 'create',
            resourceType: $resourceType,
            resourceId: (string) $resourceId,
            oldValues: null,
            newValues: $newValues,
            modifiedFields: array_keys($newValues),
            patientId: $patientId,
            success: true,
            errorMessage: null,
            description: $description ?: "Created $resourceType record"
        );
    }
    
    /**
     * Log a READ operation
     * 
     * @param string $resourceType The type of resource being read
     * @param string|int $resourceId The ID of the resource being read
     * @param int|string|null $patientId Optional patient ID for PHI tracking
     * @param string $description Optional description of the operation
     * @return bool Success status
     */
    protected function logRead(
        string $resourceType,
        string|int $resourceId,
        int|string|null $patientId = null,
        string $description = ''
    ): bool {
        return $this->logAuditEvent(
            action: 'read',
            resourceType: $resourceType,
            resourceId: (string) $resourceId,
            oldValues: null,
            newValues: null,
            modifiedFields: null,
            patientId: $patientId,
            success: true,
            errorMessage: null,
            description: $description ?: "Accessed $resourceType record"
        );
    }
    
    /**
     * Log an UPDATE operation
     * 
     * @param string $resourceType The type of resource being updated
     * @param string|int $resourceId The ID of the resource being updated
     * @param array $oldValues The values before the update
     * @param array $newValues The values after the update
     * @param array|null $modifiedFields Fields that were modified (auto-calculated if null)
     * @param int|string|null $patientId Optional patient ID for PHI tracking
     * @param string $description Optional description of the operation
     * @return bool Success status
     */
    protected function logUpdate(
        string $resourceType,
        string|int $resourceId,
        array $oldValues,
        array $newValues,
        ?array $modifiedFields = null,
        int|string|null $patientId = null,
        string $description = ''
    ): bool {
        // Auto-calculate modified fields if not provided
        if ($modifiedFields === null) {
            $modifiedFields = $this->calculateModifiedFields($oldValues, $newValues);
        }
        
        // Filter to only include changed values
        $filteredOldValues = [];
        $filteredNewValues = [];
        
        foreach ($modifiedFields as $field) {
            if (array_key_exists($field, $oldValues)) {
                $filteredOldValues[$field] = $oldValues[$field];
            }
            if (array_key_exists($field, $newValues)) {
                $filteredNewValues[$field] = $newValues[$field];
            }
        }
        
        return $this->logAuditEvent(
            action: 'update',
            resourceType: $resourceType,
            resourceId: (string) $resourceId,
            oldValues: $filteredOldValues,
            newValues: $filteredNewValues,
            modifiedFields: $modifiedFields,
            patientId: $patientId,
            success: true,
            errorMessage: null,
            description: $description ?: "Updated $resourceType record"
        );
    }
    
    /**
     * Log a DELETE operation
     * 
     * @param string $resourceType The type of resource being deleted
     * @param string|int $resourceId The ID of the resource being deleted
     * @param array $oldValues The values of the deleted record (for preservation)
     * @param int|string|null $patientId Optional patient ID for PHI tracking
     * @param string $description Optional description of the operation
     * @return bool Success status
     */
    protected function logDelete(
        string $resourceType,
        string|int $resourceId,
        array $oldValues = [],
        int|string|null $patientId = null,
        string $description = ''
    ): bool {
        return $this->logAuditEvent(
            action: 'delete',
            resourceType: $resourceType,
            resourceId: (string) $resourceId,
            oldValues: $oldValues,
            newValues: null,
            modifiedFields: array_keys($oldValues),
            patientId: $patientId,
            success: true,
            errorMessage: null,
            description: $description ?: "Deleted $resourceType record"
        );
    }
    
    /**
     * Log a failed operation
     * 
     * @param string $action The action that failed (create, read, update, delete)
     * @param string $resourceType The type of resource
     * @param string|int|null $resourceId The ID of the resource (if known)
     * @param string $errorMessage The error message
     * @param int|string|null $patientId Optional patient ID
     * @param array $metadata Additional context
     * @return bool Success status of the log operation itself
     */
    protected function logFailure(
        string $action,
        string $resourceType,
        string|int|null $resourceId = null,
        string $errorMessage = '',
        int|string|null $patientId = null,
        array $metadata = []
    ): bool {
        return $this->logAuditEvent(
            action: $action,
            resourceType: $resourceType,
            resourceId: $resourceId !== null ? (string) $resourceId : '',
            oldValues: null,
            newValues: $metadata,
            modifiedFields: null,
            patientId: $patientId,
            success: false,
            errorMessage: $errorMessage,
            description: "Failed to $action $resourceType: $errorMessage"
        );
    }
    
    /**
     * Log access denied
     * 
     * @param string $resourceType The type of resource
     * @param string|int|null $resourceId The ID of the resource
     * @param string $reason The reason for denial
     * @param int|string|null $patientId Optional patient ID
     * @return bool Success status
     */
    protected function logAccessDenied(
        string $resourceType,
        string|int|null $resourceId = null,
        string $reason = '',
        int|string|null $patientId = null
    ): bool {
        return $this->logAuditEvent(
            action: 'access_denied',
            resourceType: $resourceType,
            resourceId: $resourceId !== null ? (string) $resourceId : '',
            oldValues: null,
            newValues: ['reason' => $reason],
            modifiedFields: null,
            patientId: $patientId,
            success: false,
            errorMessage: $reason,
            description: "Access denied to $resourceType: $reason"
        );
    }
    
    /**
     * Calculate which fields have been modified between old and new values
     * 
     * @param array $oldValues Old values
     * @param array $newValues New values
     * @return array List of modified field names
     */
    protected function calculateModifiedFields(array $oldValues, array $newValues): array
    {
        $modifiedFields = [];
        
        // Check all keys from new values
        foreach ($newValues as $key => $newValue) {
            // Field is modified if:
            // 1. It didn't exist before
            // 2. The value has changed
            if (!array_key_exists($key, $oldValues)) {
                $modifiedFields[] = $key;
            } elseif ($this->valuesAreDifferent($oldValues[$key], $newValue)) {
                $modifiedFields[] = $key;
            }
        }
        
        // Check for removed fields (in old but not in new)
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
    private function valuesAreDifferent(mixed $oldValue, mixed $newValue): bool
    {
        // Handle null comparisons
        if ($oldValue === null && $newValue === null) {
            return false;
        }
        if ($oldValue === null || $newValue === null) {
            return true;
        }
        
        // Handle array comparison
        if (is_array($oldValue) && is_array($newValue)) {
            return json_encode($oldValue) !== json_encode($newValue);
        }
        
        // Handle different types
        if (gettype($oldValue) !== gettype($newValue)) {
            // Try string comparison for type coercion
            return (string) $oldValue !== (string) $newValue;
        }
        
        // Standard comparison
        return $oldValue !== $newValue;
    }
    
    /**
     * Core audit event logging method
     * 
     * @param string $action The action type
     * @param string $resourceType The resource type
     * @param string $resourceId The resource ID
     * @param array|null $oldValues Old values
     * @param array|null $newValues New values
     * @param array|null $modifiedFields Modified fields
     * @param int|string|null $patientId Patient ID
     * @param bool $success Whether operation succeeded
     * @param string|null $errorMessage Error message if failed
     * @param string $description Human-readable description
     * @return bool Success status
     */
    private function logAuditEvent(
        string $action,
        string $resourceType,
        string $resourceId,
        ?array $oldValues,
        ?array $newValues,
        ?array $modifiedFields,
        int|string|null $patientId,
        bool $success,
        ?string $errorMessage,
        string $description
    ): bool {
        try {
            $userContext = $this->getUserContext();
            $auditService = $this->getAuditService();
            
            // Sanitize PHI from values before logging
            $sanitizedOldValues = $oldValues !== null ? $this->sanitizeForAudit($oldValues) : null;
            $sanitizedNewValues = $newValues !== null ? $this->sanitizeForAudit($newValues) : null;
            
            // Build metadata with all the enhanced fields
            $metadata = [
                'user_name' => $userContext['user_name'],
                'user_role' => $userContext['user_role'],
                'patient_id' => $patientId,
                'modified_fields' => $modifiedFields,
                'old_values' => $sanitizedOldValues,
                'new_values' => $sanitizedNewValues,
                'success' => $success,
                'error_message' => $errorMessage,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
            ];
            
            // Use the existing AuditService to log
            return $auditService->audit(
                $action,
                $resourceType,
                $resourceId,
                $description,
                $metadata
            );
            
        } catch (Exception $e) {
            // Log to error log but don't throw - audit failures shouldn't break operations
            error_log("[AuditError] Failed to log audit event: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sanitize data for audit logging (remove/mask sensitive PHI)
     * 
     * @param array $data Data to sanitize
     * @return array Sanitized data
     */
    protected function sanitizeForAudit(array $data): array
    {
        $sensitiveFields = [
            'ssn', 'social_security', 'social_security_number',
            'password', 'password_hash', 'api_key', 'secret',
            'credit_card', 'card_number', 'cvv', 'pin',
            'bank_account', 'routing_number',
        ];
        
        $maskableFields = [
            'dob', 'date_of_birth', 'birth_date',
            'phone', 'phone_number', 'mobile', 'home_phone', 'work_phone',
            'email', 'email_address',
            'address', 'street_address', 'address_line_1', 'address_line_2',
        ];
        
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);
            
            // Completely redact highly sensitive fields
            if ($this->fieldMatchesList($lowerKey, $sensitiveFields)) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }
            
            // Mask partially sensitive fields (show field was changed but not full value)
            if ($this->fieldMatchesList($lowerKey, $maskableFields)) {
                $sanitized[$key] = $this->maskValue($value);
                continue;
            }
            
            // Recursively handle nested arrays
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeForAudit($value);
                continue;
            }
            
            // Keep other values as-is
            $sanitized[$key] = $value;
        }
        
        return $sanitized;
    }
    
    /**
     * Check if a field name matches any in a list
     * 
     * @param string $fieldName
     * @param array $list
     * @return bool
     */
    private function fieldMatchesList(string $fieldName, array $list): bool
    {
        foreach ($list as $pattern) {
            if (str_contains($fieldName, $pattern)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Mask a value for logging
     * 
     * @param mixed $value
     * @return string
     */
    private function maskValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '[EMPTY]';
        }
        
        $strValue = (string) $value;
        $length = strlen($strValue);
        
        if ($length <= 4) {
            return str_repeat('*', $length);
        }
        
        // Show last 4 characters for longer strings
        return str_repeat('*', $length - 4) . substr($strValue, -4);
    }
    
    /**
     * Log a search/list operation
     * 
     * @param string $resourceType The type of resource being searched
     * @param array $searchCriteria The search parameters used
     * @param int $resultCount Number of results returned
     * @param string $description Optional description
     * @return bool Success status
     */
    protected function logSearch(
        string $resourceType,
        array $searchCriteria = [],
        int $resultCount = 0,
        string $description = ''
    ): bool {
        return $this->logAuditEvent(
            action: 'search',
            resourceType: $resourceType,
            resourceId: '',
            oldValues: null,
            newValues: [
                'criteria' => $this->sanitizeForAudit($searchCriteria),
                'result_count' => $resultCount
            ],
            modifiedFields: null,
            patientId: null,
            success: true,
            errorMessage: null,
            description: $description ?: "Searched $resourceType records"
        );
    }
    
    /**
     * Log an export operation
     * 
     * @param string $resourceType The type of resource being exported
     * @param string $exportFormat The format (csv, pdf, json, etc.)
     * @param array $exportCriteria The export parameters
     * @param int $recordCount Number of records exported
     * @param int|string|null $patientId Optional patient ID
     * @return bool Success status
     */
    protected function logExport(
        string $resourceType,
        string $exportFormat,
        array $exportCriteria = [],
        int $recordCount = 0,
        int|string|null $patientId = null
    ): bool {
        return $this->logAuditEvent(
            action: 'export',
            resourceType: $resourceType,
            resourceId: '',
            oldValues: null,
            newValues: [
                'format' => $exportFormat,
                'criteria' => $this->sanitizeForAudit($exportCriteria),
                'record_count' => $recordCount
            ],
            modifiedFields: null,
            patientId: $patientId,
            success: true,
            errorMessage: null,
            description: "Exported $recordCount $resourceType records as $exportFormat"
        );
    }
    
    /**
     * Log a print operation
     * 
     * @param string $resourceType The type of resource being printed
     * @param string|int $resourceId The resource ID
     * @param int|string|null $patientId Optional patient ID
     * @return bool Success status
     */
    protected function logPrint(
        string $resourceType,
        string|int $resourceId,
        int|string|null $patientId = null
    ): bool {
        return $this->logAuditEvent(
            action: 'print',
            resourceType: $resourceType,
            resourceId: (string) $resourceId,
            oldValues: null,
            newValues: null,
            modifiedFields: null,
            patientId: $patientId,
            success: true,
            errorMessage: null,
            description: "Printed $resourceType record"
        );
    }
}
