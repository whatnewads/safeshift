<?php
/**
 * Main Logging Service Interface
 *
 * Coordinates between FileLogger, SecureLogger, and AuditService
 * to provide a unified logging interface for the application.
 *
 * IMPORTANT: This service does NOT extend BaseService because logging
 * must work independently of database connectivity. Database-backed
 * audit logging is optional and will gracefully degrade to file-only
 * logging if the database is unavailable.
 */

namespace Core\Services;

use Core\Infrastructure\Logging\FileLogger;
use Core\Infrastructure\Logging\SecureLogger;
use Core\Services\AuditService;
use Exception;

class LogService
{
    private $fileLogger;
    private $secureLogger;
    private $auditService = null;
    private $auditServiceInitialized = false;
    
    /**
     * Critical security events that require special handling
     */
    private $criticalSecurityEvents = [
        'BRUTE_FORCE_ATTEMPT',
        'SQL_INJECTION_ATTEMPT',
        'XSS_ATTEMPT',
        'CSRF_ATTEMPT',
        'UNAUTHORIZED_FILE_ACCESS',
        'SUSPICIOUS_PATTERN',
        'SESSION_HIJACK_ATTEMPT',
        'PERMISSION_DENIED',
        'DATA_EXPORT',
        'BULK_DOWNLOAD'
    ];
    
    /**
     * Events that should be flagged for review
     */
    private $flaggedEvents = [
        'UNAUTHORIZED_ACCESS',
        'LOGIN_FAILED',
        'SESSION_HIJACK_ATTEMPT',
        'PERMISSION_DENIED',
        'DATA_EXPORT',
        'BULK_DOWNLOAD',
        'SUSPICIOUS_ACTIVITY'
    ];
    
    public function __construct()
    {
        // DO NOT call parent::__construct() - LogService must work without database
        // Initialize file-based logging components only (no database dependency)
        $this->fileLogger = new FileLogger();
        $this->secureLogger = SecureLogger::getInstance();
        // AuditService is lazily initialized when needed and database is available
    }
    
    /**
     * Get the AuditService instance (lazy initialization)
     * Returns null if database is not available
     *
     * @return AuditService|null
     */
    private function getAuditService()
    {
        if (!$this->auditServiceInitialized) {
            $this->auditServiceInitialized = true;
            try {
                // Only try to create AuditService if database config is loaded
                if (defined('DB_HOST') && defined('DB_NAME')) {
                    $this->auditService = new AuditService();
                }
            } catch (Exception $e) {
                // Database not available - log to file and continue without audit service
                $this->fileLogger->warning(
                    'AuditService unavailable - database connection failed: ' . $e->getMessage(),
                    ['exception' => get_class($e)],
                    'system'
                );
                $this->auditService = null;
            }
        }
        return $this->auditService;
    }
    
    /**
     * Log an error
     * 
     * @param string $message Error message
     * @param array $context Additional context
     * @param Exception|null $exception Exception if available
     * @return bool Success status
     */
    public function error($message, array $context = [], Exception $exception = null)
    {
        if ($exception) {
            $context['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }
        
        // Log to file
        $this->fileLogger->error($message, $context, 'error');
        
        // Log with SecureLogger (strips PHI)
        $this->secureLogger->log(
            SecureLogger::LEVEL_ERROR,
            SecureLogger::CAT_ERROR,
            $message,
            $context
        );
        
        return true;
    }
    
    /**
     * Log a security event
     * 
     * @param string $eventType Type of security event
     * @param string $message Event message
     * @param array $details Event details
     * @return bool Success status
     */
    public function security($eventType, $message, array $details = [])
    {
        $context = [
            'event_type' => $eventType,
            'details' => $details,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'uri' => $_SERVER['REQUEST_URI'] ?? null
        ];
        
        // Always log security events to file (works without database)
        $this->fileLogger->security($message, $context, 'security');
        
        // Log with SecureLogger
        $this->secureLogger->log(
            SecureLogger::LEVEL_CRITICAL,
            SecureLogger::CAT_AUTH,
            $message,
            $context
        );
        
        // Critical events also go to database (if available)
        if (in_array($eventType, $this->criticalSecurityEvents)) {
            $auditService = $this->getAuditService();
            if ($auditService) {
                try {
                    $auditService->logSecurityEvent($eventType, $details);
                } catch (Exception $e) {
                    // Log failure to file but don't throw
                    $this->fileLogger->error(
                        'Failed to log security event to database: ' . $e->getMessage(),
                        ['event_type' => $eventType],
                        'system'
                    );
                }
            }
            
            // TODO: Implement alert mechanism
            // $this->alertSecurityTeam($eventType, $message, $details);
        }
        
        return true;
    }
    
    /**
     * Log an audit event (HIPAA compliance)
     * 
     * @param string $action Action performed
     * @param string $subjectType Type of subject (User, Patient, Encounter, etc.)
     * @param string|null $subjectId ID of the subject
     * @param array $details Additional details
     * @return bool Success status
     */
    public function audit($action, $subjectType, $subjectId = null, array $details = [])
    {
        // Determine if this should be flagged
        $flagged = in_array($action, $this->flaggedEvents);
        
        // Log to file for redundancy (always works, no database needed)
        $this->fileLogger->audit("Audit: $action on $subjectType/$subjectId", [
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'details' => $details,
            'flagged' => $flagged
        ], 'audit');
        
        // Log to database (if available)
        $auditService = $this->getAuditService();
        if ($auditService) {
            try {
                $description = isset($details['description']) ? $details['description'] : $action;
                $auditService->audit($action, $subjectType, $subjectId, $description, $details);
            } catch (Exception $e) {
                // Log failure to file but don't throw - audit should not break the app
                $this->fileLogger->error(
                    'Failed to log audit to database: ' . $e->getMessage(),
                    ['action' => $action, 'subject_type' => $subjectType],
                    'system'
                );
            }
        }
        
        // Also log with SecureLogger for tamper-proof records
        $this->secureLogger->log(
            SecureLogger::LEVEL_AUDIT,
            SecureLogger::CAT_HIPAA,
            "Audit: $action",
            [
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'details' => $details
            ]
        );
        
        return true;
    }
    
    /**
     * Log general information
     * 
     * @param string $message Information message
     * @param array $context Additional context
     * @param string $channel Log channel
     * @return bool Success status
     */
    public function info($message, array $context = [], $channel = 'general')
    {
        return $this->fileLogger->info($message, $context, $channel);
    }
    
    /**
     * Log debug information
     * 
     * @param string $message Debug message
     * @param array $context Additional context
     * @param string $channel Log channel
     * @return bool Success status
     */
    public function debug($message, array $context = [], $channel = 'general')
    {
        return $this->fileLogger->debug($message, $context, $channel);
    }
    
    /**
     * Log a warning
     * 
     * @param string $message Warning message
     * @param array $context Additional context
     * @param string $channel Log channel
     * @return bool Success status
     */
    public function warning($message, array $context = [], $channel = 'general')
    {
        return $this->fileLogger->warning($message, $context, $channel);
    }
    
    /**
     * Log a critical error
     * 
     * @param string $message Critical error message
     * @param array $context Additional context
     * @param Exception|null $exception Exception if available
     * @return bool Success status
     */
    public function critical($message, array $context = [], Exception $exception = null)
    {
        if ($exception) {
            $context['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }
        
        // Log to multiple destinations for critical errors (file-based, no DB needed)
        $this->fileLogger->critical($message, $context, 'error');
        
        $this->secureLogger->log(
            SecureLogger::LEVEL_CRITICAL,
            SecureLogger::CAT_ERROR,
            $message,
            $context
        );
        
        // Also create an audit record (if database available)
        $auditService = $this->getAuditService();
        if ($auditService) {
            try {
                $auditService->audit(
                    'CRITICAL_ERROR',
                    'System',
                    null,
                    $message,
                    $context
                );
            } catch (Exception $e) {
                // Don't throw - critical logging should not break the app
                $this->fileLogger->error(
                    'Failed to log critical error to database: ' . $e->getMessage(),
                    [],
                    'system'
                );
            }
        }
        
        return true;
    }
    
    /**
     * Log PHI access (Protected Health Information)
     * 
     * @param string $patientId Patient ID
     * @param string $action Action performed
     * @param array $fields Fields accessed
     * @return bool Success status
     */
    public function phiAccess($patientId, $action, array $fields = [])
    {
        $purpose = $_SESSION['access_purpose'] ?? 'treatment';
        
        $details = [
            'action' => $action,
            'fields' => $fields,
            'purpose' => $purpose
        ];
        
        // Use secure logger to strip any PHI from logs (file-based, no DB needed)
        $this->secureLogger->log(
            SecureLogger::LEVEL_INFO,
            SecureLogger::CAT_HIPAA,
            "PHI Access: $action",
            [
                'patient_id' => $patientId,
                'fields_count' => count($fields),
                'purpose' => $purpose
            ]
        );
        
        // Audit trail in database (gracefully handles DB unavailability)
        return $this->audit('PHI_ACCESS', 'Patient', $patientId, $details);
    }
    
    /**
     * Log authentication events
     * 
     * @param string $event Event type (login, logout, failed_login, etc.)
     * @param string|null $userId User ID
     * @param array $details Additional details
     * @return bool Success status
     */
    public function auth($event, $userId = null, array $details = [])
    {
        $message = "Authentication: $event";
        if ($userId) {
            $message .= " for user $userId";
        }
        
        // File log (always works, no DB needed)
        $this->fileLogger->info($message, array_merge($details, [
            'event' => $event,
            'user_id' => $userId
        ]), 'auth');
        
        // Secure log
        $this->secureLogger->log(
            SecureLogger::LEVEL_INFO,
            SecureLogger::CAT_AUTH,
            $message,
            $details
        );
        
        // Database audit (if available)
        $auditService = $this->getAuditService();
        if ($auditService) {
            try {
                switch ($event) {
                    case 'login':
                        $auditService->logLogin($userId, true);
                        break;
                        
                    case 'logout':
                        $auditService->logLogout($userId);
                        break;
                        
                    case 'failed_login':
                        $username = $details['username'] ?? 'unknown';
                        $reason = $details['reason'] ?? '';
                        $auditService->logFailedLogin($username, $reason, $userId);
                        break;
                        
                    case 'access_denied':
                        $resourceType = $details['resource_type'] ?? 'unknown';
                        $resourceId = $details['resource_id'] ?? null;
                        $reason = $details['reason'] ?? '';
                        $auditService->logAccessDenied($resourceType, $resourceId, $reason);
                        break;
                }
            } catch (Exception $e) {
                // Log failure to file but don't throw
                $this->fileLogger->error(
                    'Failed to log auth event to database: ' . $e->getMessage(),
                    ['event' => $event],
                    'system'
                );
            }
        }
        
        // Check for brute force attempts (works without DB)
        if ($event === 'failed_login') {
            $username = $details['username'] ?? 'unknown';
            $this->checkBruteForce($username, $details);
        }
        
        return true;
    }
    
    /**
     * Check for brute force attempts
     * 
     * @param string $username Username
     * @param array $details Login attempt details
     */
    private function checkBruteForce($username, array $details)
    {
        // Simple brute force detection - in production, use a more sophisticated approach
        $cacheKey = 'login_attempts_' . $username . '_' . ($_SERVER['REMOTE_ADDR'] ?? '');
        $attempts = $_SESSION[$cacheKey] ?? 0;
        $attempts++;
        $_SESSION[$cacheKey] = $attempts;
        
        if ($attempts >= 5) {
            $this->security('BRUTE_FORCE_ATTEMPT', "Multiple failed login attempts for $username", [
                'username' => $username,
                'attempts' => $attempts,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        }
    }
    
    /**
     * Log access to a resource
     * 
     * @param string $resourceType Resource type
     * @param string $resourceId Resource ID
     * @param string $action Action performed
     * @param array $context Additional context
     * @return bool Success status
     */
    public function access($resourceType, $resourceId, $action = 'view', array $context = [])
    {
        $message = "Accessed $resourceType/$resourceId - Action: $action";
        
        // File log (always works, no DB needed)
        $this->fileLogger->info($message, $context, 'access');
        
        // Secure log
        $this->secureLogger->log(
            SecureLogger::LEVEL_INFO,
            SecureLogger::CAT_ACCESS,
            $message,
            $context
        );
        
        // Database audit (if available)
        $auditService = $this->getAuditService();
        if ($auditService) {
            try {
                $auditService->audit($action, $resourceType, $resourceId, $message, $context);
            } catch (Exception $e) {
                // Log failure to file but don't throw
                $this->fileLogger->error(
                    'Failed to log access to database: ' . $e->getMessage(),
                    ['resource_type' => $resourceType, 'action' => $action],
                    'system'
                );
            }
        }
        
        return true;
    }
    
    /**
     * Log form submission (for compliance)
     * 
     * @param string $formName Form name
     * @param array $metadata Form metadata (not the actual data)
     * @return bool Success status
     */
    public function formSubmission($formName, array $metadata = [])
    {
        $message = "Form submitted: $formName";
        
        // Never log actual form data - only metadata
        $safeMetadata = [
            'form_name' => $formName,
            'fields_count' => $metadata['fields_count'] ?? 0,
            'validation_errors' => $metadata['validation_errors'] ?? 0,
            'processing_time' => $metadata['processing_time'] ?? null
        ];
        
        // Secure logger to ensure no PHI
        $this->secureLogger->log(
            SecureLogger::LEVEL_INFO,
            SecureLogger::CAT_FORM,
            $message,
            $safeMetadata
        );
        
        return true;
    }
    
    /**
     * Get log statistics
     * 
     * @return array Combined statistics from all loggers
     */
    public function getStatistics()
    {
        $stats = [
            'file_stats' => $this->fileLogger->getStatistics(),
            'audit_stats' => null
        ];
        
        // Only get audit stats if database is available
        $auditService = $this->getAuditService();
        if ($auditService) {
            try {
                $stats['audit_stats'] = $auditService->getStatistics();
            } catch (Exception $e) {
                $stats['audit_stats'] = ['error' => 'Database unavailable'];
            }
        }
        
        return $stats;
    }
    
    /**
     * Search logs
     * 
     * @param string $pattern Search pattern
     * @param array $options Search options
     * @return array Search results
     */
    public function searchLogs($pattern, array $options = [])
    {
        // Search file logs (always available)
        $channels = $options['channels'] ?? [];
        $startDate = $options['start_date'] ?? null;
        $endDate = $options['end_date'] ?? null;
        
        $fileResults = $this->fileLogger->search($pattern, $channels, $startDate, $endDate);
        
        $results = [
            'file_logs' => $fileResults,
            'audit_logs' => [],
            'total_results' => count($fileResults)
        ];
        
        // Search audit logs (if database available)
        $auditService = $this->getAuditService();
        if ($auditService) {
            try {
                $auditFilters = [
                    'search' => $pattern,
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ];
                
                if (isset($options['user_id'])) {
                    $auditFilters['user_id'] = $options['user_id'];
                }
                
                $auditResults = $auditService->searchLogs($auditFilters);
                $results['audit_logs'] = $auditResults['logs'];
                $results['total_results'] += count($auditResults['logs']);
            } catch (Exception $e) {
                $results['audit_error'] = 'Database unavailable';
            }
        }
        
        return $results;
    }
    
    /**
     * Export logs
     * 
     * @param array $filters Export filters
     * @param string $format Export format (csv, json, pdf)
     * @return mixed Exported data
     */
    public function exportLogs(array $filters = [], $format = 'csv')
    {
        // Try to export from audit service if available
        $auditService = $this->getAuditService();
        if ($auditService) {
            try {
                return $auditService->exportLogs($filters, $format);
            } catch (Exception $e) {
                // Fall through to file-based export
                $this->fileLogger->error(
                    'Failed to export from database, falling back to file logs: ' . $e->getMessage(),
                    [],
                    'system'
                );
            }
        }
        
        // Fallback: export from file logs
        // In the future, implement file-based log export
        return [
            'error' => 'Database unavailable - file log export not yet implemented',
            'format' => $format,
            'filters' => $filters
        ];
    }
    
    /**
     * Verify log integrity
     * 
     * @param string $logType Log type to verify
     * @param string $filename Filename to verify
     * @return bool Integrity status
     */
    public function verifyIntegrity($logType, $filename)
    {
        if ($logType === 'secure') {
            return $this->secureLogger->verifyLogIntegrity($filename);
        }
        
        // For audit logs, verify individual entries
        // This would need implementation in the audit service
        
        return false;
    }
}