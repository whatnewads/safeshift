<?php
/**
 * EHR Submission Logging Helper
 * 
 * Provides simple wrapper functions for EHR logging operations.
 * Uses the Core\Services\EHRLogger class for structured HIPAA-compliant logging.
 * 
 * Log Format: [TIMESTAMP] [LEVEL] [USER_ID] [ENCOUNTER_ID] [ACTION] [STATUS] [DETAILS]
 * Log File: logs/ehr_submissions_YYYY-MM-DD.log
 * 
 * @package SafeShift\Includes
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/Services/EHRLogger.php';

use Core\Services\EHRLogger;

/**
 * Log an EHR encounter submission
 * 
 * @param string $encounterId Encounter identifier
 * @param int|string|null $userId User identifier
 * @param string $action Action being performed (e.g., 'SUBMIT', 'CREATE', 'UPDATE')
 * @param string $status Status of the action ('SUCCESS', 'FAILED', 'PENDING')
 * @param string|array $details Additional details about the operation
 * @return bool Success status of logging operation
 * 
 * @example
 * ```php
 * logEhrSubmission('enc_123', 1, 'SUBMIT', 'SUCCESS', 'Encounter submitted successfully');
 * logEhrSubmission('enc_456', 1, 'SUBMIT', 'FAILED', ['error' => 'Validation failed', 'fields' => ['patient_id']]);
 * ```
 */
function logEhrSubmission(
    string $encounterId, 
    $userId, 
    string $action, 
    string $status, 
    $details
): bool {
    try {
        $logger = EHRLogger::getInstance();
        
        // Also write to the dedicated ehr_submissions log file
        $logEntry = buildEhrLogEntry($encounterId, $userId, $action, $status, $details);
        writeEhrSubmissionLog($logEntry);
        
        // Map action to EHRLogger operation
        $operation = mapActionToOperation($action);
        
        $context = [
            'encounter_id' => $encounterId,
            'details' => is_array($details) ? $details : ['message' => $details],
            'result' => strtolower($status) === 'success' ? 'success' : 'failure',
        ];
        
        return $logger->logOperation($operation, $context, EHRLogger::CHANNEL_ENCOUNTER);
    } catch (\Exception $e) {
        error_log("EHR Logging failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Log an EHR error
 * 
 * @param string $encounterId Encounter identifier
 * @param int|string|null $userId User identifier  
 * @param string $action Action that caused the error
 * @param string $errorMessage Error message
 * @param string|null $stackTrace Optional stack trace
 * @return bool Success status of logging operation
 * 
 * @example
 * ```php
 * logEhrError('enc_123', 1, 'SUBMIT', 'Database connection failed');
 * logEhrError('enc_123', 1, 'VALIDATE', 'Missing required fields', $exception->getTraceAsString());
 * ```
 */
function logEhrError(
    string $encounterId, 
    $userId, 
    string $action, 
    string $errorMessage, 
    ?string $stackTrace = null
): bool {
    try {
        $logger = EHRLogger::getInstance();
        
        // Write to dedicated ehr_submissions log
        $details = ['error' => $errorMessage];
        if ($stackTrace) {
            $details['stack_trace'] = substr($stackTrace, 0, 1000); // Truncate long traces
        }
        
        $logEntry = buildEhrLogEntry($encounterId, $userId, $action, 'ERROR', $details);
        writeEhrSubmissionLog($logEntry);
        
        $context = [
            'encounter_id' => $encounterId,
            'channel' => EHRLogger::CHANNEL_ENCOUNTER,
        ];
        
        if ($stackTrace) {
            $context['stack_trace'] = $stackTrace;
        }
        
        return $logger->logError($action, $errorMessage, $context);
    } catch (\Exception $e) {
        error_log("EHR Error Logging failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Log a disclosure acknowledgment
 * 
 * @param string $encounterId Encounter identifier
 * @param int|string|null $userId User identifier
 * @param string $disclosureType Type of disclosure (e.g., 'general_consent', 'hipaa_acknowledgment')
 * @param string $status Status of acknowledgment ('SUCCESS', 'FAILED', 'PENDING')
 * @return bool Success status of logging operation
 * 
 * @example
 * ```php
 * logDisclosureAcknowledgment('enc_123', 1, 'hipaa_acknowledgment', 'SUCCESS');
 * logDisclosureAcknowledgment('enc_123', 1, 'work_related_auth', 'FAILED');
 * ```
 */
function logDisclosureAcknowledgment(
    string $encounterId, 
    $userId, 
    string $disclosureType, 
    string $status
): bool {
    try {
        $logger = EHRLogger::getInstance();
        
        $details = [
            'disclosure_type' => $disclosureType,
            'ip_address' => getClientIpAddress(),
        ];
        
        // Write to dedicated ehr_submissions log
        $logEntry = buildEhrLogEntry($encounterId, $userId, 'DISCLOSURE_ACK', $status, $details);
        writeEhrSubmissionLog($logEntry);
        
        $context = [
            'encounter_id' => $encounterId,
            'details' => $details,
            'result' => strtolower($status) === 'success' ? 'success' : 'failure',
        ];
        
        return $logger->logOperation('DISCLOSURE_ACK', $context, EHRLogger::CHANNEL_ENCOUNTER);
    } catch (\Exception $e) {
        error_log("EHR Disclosure Logging failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Log an encounter status transition
 * 
 * @param string $encounterId Encounter identifier
 * @param int|string|null $userId User identifier
 * @param string $fromStatus Previous status
 * @param string $toStatus New status
 * @param string $reason Optional reason for transition
 * @return bool Success status of logging operation
 * 
 * @example
 * ```php
 * logStatusTransition('enc_123', 1, 'draft', 'submitted', 'Ready for review');
 * logStatusTransition('enc_123', 1, 'submitted', 'completed', '');
 * ```
 */
function logStatusTransition(
    string $encounterId,
    $userId,
    string $fromStatus,
    string $toStatus,
    string $reason = ''
): bool {
    try {
        $logger = EHRLogger::getInstance();
        
        $details = [
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
        ];
        
        // Write to dedicated ehr_submissions log
        $logEntry = buildEhrLogEntry($encounterId, $userId, 'STATUS_TRANSITION', 'SUCCESS', $details);
        writeEhrSubmissionLog($logEntry);
        
        return $logger->logStatusTransition($encounterId, $fromStatus, $toStatus, $reason);
    } catch (\Exception $e) {
        error_log("EHR Status Transition Logging failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Log a validation failure
 * 
 * @param string $encounterId Encounter identifier
 * @param int|string|null $userId User identifier
 * @param array $validationErrors Array of validation errors
 * @param string $context Context where validation failed (e.g., 'submit', 'finalize')
 * @return bool Success status of logging operation
 * 
 * @example
 * ```php
 * logValidationFailure('enc_123', 1, ['patient_id' => 'Required', 'date' => 'Invalid format'], 'submit');
 * ```
 */
function logValidationFailure(
    string $encounterId,
    $userId,
    array $validationErrors,
    string $context = 'submit'
): bool {
    try {
        $logger = EHRLogger::getInstance();
        
        $details = [
            'validation_context' => $context,
            'error_count' => count($validationErrors),
            'error_fields' => array_keys($validationErrors),
        ];
        
        // Write to dedicated ehr_submissions log
        $logEntry = buildEhrLogEntry($encounterId, $userId, 'VALIDATION_FAILED', 'FAILED', $details);
        writeEhrSubmissionLog($logEntry);
        
        return $logger->logFinalization($encounterId, ['errors' => $validationErrors], false, [
            'validation_context' => $context,
        ]);
    } catch (\Exception $e) {
        error_log("EHR Validation Logging failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Build a formatted log entry string
 * 
 * @param string $encounterId Encounter identifier
 * @param int|string|null $userId User identifier
 * @param string $action Action being performed
 * @param string $status Status of the action
 * @param string|array $details Additional details
 * @return string Formatted log entry
 */
function buildEhrLogEntry(
    string $encounterId, 
    $userId, 
    string $action, 
    string $status, 
    $details
): string {
    $timestamp = date('Y-m-d H:i:s');
    $level = determineLogLevel($status);
    $userId = $userId ?? 'anonymous';
    $encounterId = $encounterId ?: 'unknown';
    
    // Convert details to string if array
    if (is_array($details)) {
        // Remove any potential PHI before logging
        $safeDetails = redactPhiFromArray($details);
        $detailsStr = json_encode($safeDetails, JSON_UNESCAPED_SLASHES);
    } else {
        $detailsStr = (string) $details;
    }
    
    // Add IP address for audit trail
    $ipAddress = getClientIpAddress();
    
    return sprintf(
        "[%s] [%s] [user_%s] [%s] [%s] [%s] [IP:%s] %s",
        $timestamp,
        $level,
        $userId,
        $encounterId,
        strtoupper($action),
        strtoupper($status),
        $ipAddress,
        $detailsStr
    );
}

/**
 * Write log entry to the ehr_submissions log file
 * 
 * @param string $logEntry The formatted log entry
 * @return bool Success status
 */
function writeEhrSubmissionLog(string $logEntry): bool {
    try {
        $logDir = dirname(__DIR__) . '/logs';
        
        // Ensure log directory exists
        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }
        
        $date = date('Y-m-d');
        $logFile = $logDir . '/ehr_submissions_' . $date . '.log';
        
        // Write with exclusive lock for thread safety
        $result = file_put_contents(
            $logFile, 
            $logEntry . PHP_EOL, 
            FILE_APPEND | LOCK_EX
        );
        
        return $result !== false;
    } catch (\Exception $e) {
        error_log("Failed to write EHR submission log: " . $e->getMessage());
        return false;
    }
}

/**
 * Determine log level based on status
 * 
 * @param string $status The status string
 * @return string Log level (INFO, WARNING, ERROR)
 */
function determineLogLevel(string $status): string {
    $status = strtoupper($status);
    
    switch ($status) {
        case 'ERROR':
        case 'FAILED':
            return 'ERROR';
        case 'WARNING':
        case 'PENDING':
            return 'WARNING';
        case 'SUCCESS':
        case 'INFO':
        default:
            return 'INFO';
    }
}

/**
 * Map action string to EHRLogger operation constant
 * 
 * @param string $action The action string
 * @return string EHRLogger operation constant
 */
function mapActionToOperation(string $action): string {
    $action = strtoupper($action);
    
    $mapping = [
        'CREATE' => EHRLogger::OP_CREATE,
        'READ' => EHRLogger::OP_READ,
        'VIEW' => EHRLogger::OP_READ,
        'UPDATE' => EHRLogger::OP_UPDATE,
        'EDIT' => EHRLogger::OP_UPDATE,
        'DELETE' => EHRLogger::OP_DELETE,
        'CANCEL' => EHRLogger::OP_DELETE,
        'FINALIZE' => EHRLogger::OP_FINALIZE,
        'SUBMIT' => EHRLogger::OP_FINALIZE,
        'SIGN' => EHRLogger::OP_SIGN,
        'AMEND' => EHRLogger::OP_AMEND,
    ];
    
    return $mapping[$action] ?? $action;
}

/**
 * Get client IP address for audit trail
 * 
 * @return string Client IP address
 */
function getClientIpAddress(): string {
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            // Handle comma-separated IPs (X-Forwarded-For can have multiple)
            if (strpos($ip, ',') !== false) {
                $ip = explode(',', $ip)[0];
            }
            return trim($ip);
        }
    }
    
    return '0.0.0.0';
}

/**
 * Redact PHI from array for logging (HIPAA compliance)
 * 
 * @param array $data Data to redact
 * @return array Data with PHI redacted
 */
function redactPhiFromArray(array $data): array {
    $phiFields = [
        'patient_name', 'first_name', 'last_name', 'full_name',
        'ssn', 'social_security', 'social_security_number',
        'dob', 'date_of_birth', 'birth_date',
        'address', 'street', 'city', 'zip', 'zipcode', 'postal_code',
        'phone', 'phone_number', 'mobile', 'cell', 'home_phone', 'work_phone',
        'email', 'email_address',
        'mrn', 'medical_record_number',
        'insurance_id', 'policy_number', 'group_number',
        'drivers_license', 'license_number',
        'employer_name', 'company_name'
    ];
    
    $redacted = [];
    
    foreach ($data as $key => $value) {
        $lowerKey = strtolower($key);
        
        // Redact PHI fields
        if (in_array($lowerKey, $phiFields)) {
            $redacted[$key] = '[REDACTED]';
            continue;
        }
        
        // Recursively process arrays
        if (is_array($value)) {
            $redacted[$key] = redactPhiFromArray($value);
            continue;
        }
        
        // Redact patterns in strings (SSN, phone, email)
        if (is_string($value)) {
            $redacted[$key] = redactPhiPatterns($value);
            continue;
        }
        
        $redacted[$key] = $value;
    }
    
    return $redacted;
}

/**
 * Redact PHI patterns from string values
 * 
 * @param string $value String to redact
 * @return string String with PHI patterns redacted
 */
function redactPhiPatterns(string $value): string {
    $patterns = [
        '/\b\d{3}-\d{2}-\d{4}\b/' => '[SSN-REDACTED]',  // SSN
        '/\b\d{3}-\d{3}-\d{4}\b/' => '[PHONE-REDACTED]', // Phone
        '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/' => '[EMAIL-REDACTED]', // Email
    ];
    
    foreach ($patterns as $pattern => $replacement) {
        $value = preg_replace($pattern, $replacement, $value);
    }
    
    return $value;
}

/**
 * Get EHRLogger instance for advanced logging operations
 * 
 * @return EHRLogger The singleton EHRLogger instance
 */
function getEhrLogger(): EHRLogger {
    return EHRLogger::getInstance();
}
