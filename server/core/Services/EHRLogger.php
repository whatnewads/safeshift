<?php
/**
 * EHR Logger Service
 * 
 * Provides structured JSON logging specifically for EHR operations.
 * Designed for HIPAA compliance with PHI redaction and audit trails.
 * 
 * Log Channels:
 * - ehr: General EHR operations
 * - encounter: Encounter CRUD operations  
 * - vitals: Vital signs recording
 * - assessment: Patient assessments
 * - treatment: Treatments administered
 * - signature: Digital signatures
 * - finalization: Report finalization events
 * - phi_access: PHI access audit trail
 * 
 * @package Core\Services
 */

declare(strict_types=1);

namespace Core\Services;

// Ensure SecureLogger is available
require_once __DIR__ . '/../Infrastructure/Logging/SecureLogger.php';

use Core\Infrastructure\Logging\SecureLogger;
use Exception;

class EHRLogger
{
    private static ?EHRLogger $instance = null;
    private string $logPath;
    private SecureLogger $secureLogger;
    private float $requestStartTime;
    
    // Operation types for audit trail
    public const OP_CREATE = 'CREATE';
    public const OP_READ = 'READ';
    public const OP_UPDATE = 'UPDATE';
    public const OP_DELETE = 'DELETE';
    public const OP_FINALIZE = 'FINALIZE';
    public const OP_SIGN = 'SIGN';
    public const OP_AMEND = 'AMEND';
    
    // Log channels
    public const CHANNEL_EHR = 'ehr';
    public const CHANNEL_ENCOUNTER = 'encounter';
    public const CHANNEL_VITALS = 'vitals';
    public const CHANNEL_ASSESSMENT = 'assessment';
    public const CHANNEL_TREATMENT = 'treatment';
    public const CHANNEL_SIGNATURE = 'signature';
    public const CHANNEL_FINALIZATION = 'finalization';
    public const CHANNEL_PHI_ACCESS = 'phi_access';
    
    // PHI fields that must be redacted
    private const PHI_FIELDS = [
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
    
    // PHI patterns for regex matching
    private const PHI_PATTERNS = [
        '/\b\d{3}-\d{2}-\d{4}\b/' => '[SSN-REDACTED]',  // SSN
        '/\b\d{3}-\d{3}-\d{4}\b/' => '[PHONE-REDACTED]', // Phone
        '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/' => '[EMAIL-REDACTED]', // Email
        '/\b(?:0[1-9]|1[0-2])[-\/](?:0[1-9]|[12][0-9]|3[01])[-\/](?:19|20)\d{2}\b/' => '[DATE-REDACTED]', // MM/DD/YYYY
        '/\b(?:19|20)\d{2}[-\/](?:0[1-9]|1[0-2])[-\/](?:0[1-9]|[12][0-9]|3[01])\b/' => '[DATE-REDACTED]', // YYYY-MM-DD
    ];

    private function __construct()
    {
        $this->logPath = dirname(dirname(__DIR__)) . '/logs/';
        $this->secureLogger = SecureLogger::getInstance();
        $this->requestStartTime = microtime(true);
        
        // Ensure log directory exists
        if (!file_exists($this->logPath)) {
            mkdir($this->logPath, 0750, true);
        }
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Log an EHR operation with structured JSON format
     * 
     * @param string $operation Operation type (CREATE, READ, UPDATE, DELETE, FINALIZE, SIGN, AMEND)
     * @param array $context Context data including:
     *                       - encounter_id: Encounter identifier
     *                       - patient_id: Patient identifier (will be hashed)
     *                       - details: Operation-specific details
     *                       - result: success/failure
     * @param string $channel Log channel (ehr, encounter, vitals, etc.)
     * @return bool Success status
     */
    public function logOperation(string $operation, array $context, string $channel = self::CHANNEL_EHR): bool
    {
        $startTime = $context['start_time'] ?? $this->requestStartTime;
        $durationMs = (int)((microtime(true) - $startTime) * 1000);
        
        $logEntry = [
            'timestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
            'level' => 'INFO',
            'channel' => $channel,
            'operation' => $operation,
            'user_id' => $this->getCurrentUserId(),
            'user_role' => $this->getCurrentUserRole(),
            'encounter_id' => $context['encounter_id'] ?? null,
            'patient_id_hash' => isset($context['patient_id']) 
                ? $this->hashPatientId($context['patient_id']) 
                : null,
            'ip_address' => $this->getClientIP(),
            'user_agent' => $this->getUserAgent(),
            'request_id' => $this->getRequestId(),
            'details' => $this->redactPHI($context['details'] ?? []),
            'result' => $context['result'] ?? 'success',
            'duration_ms' => $durationMs,
        ];
        
        return $this->writeLog($channel, $logEntry);
    }

    /**
     * Log PHI access for HIPAA compliance
     * 
     * @param int $userId User accessing PHI
     * @param int|string $patientId Patient whose PHI was accessed
     * @param string $accessType Type of access (view, export, print, etc.)
     * @param array $fieldsAccessed List of PHI fields accessed
     * @return bool Success status
     */
    public function logPHIAccess(int $userId, $patientId, string $accessType, array $fieldsAccessed = []): bool
    {
        $logEntry = [
            'timestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
            'level' => 'AUDIT',
            'channel' => self::CHANNEL_PHI_ACCESS,
            'operation' => 'PHI_ACCESS',
            'user_id' => $userId,
            'user_role' => $this->getCurrentUserRole(),
            'patient_id_hash' => $this->hashPatientId($patientId),
            'access_type' => $accessType,
            'fields_accessed' => $fieldsAccessed,
            'fields_count' => count($fieldsAccessed),
            'ip_address' => $this->getClientIP(),
            'user_agent' => $this->getUserAgent(),
            'request_id' => $this->getRequestId(),
            'purpose' => $_SESSION['access_purpose'] ?? 'treatment',
            'result' => 'logged',
        ];
        
        return $this->writeLog(self::CHANNEL_PHI_ACCESS, $logEntry);
    }

    /**
     * Log encounter creation
     */
    public function logEncounterCreated(string $encounterId, $patientId, array $details = []): bool
    {
        return $this->logOperation(self::OP_CREATE, [
            'encounter_id' => $encounterId,
            'patient_id' => $patientId,
            'details' => array_merge($details, [
                'action' => 'encounter_created',
            ]),
            'result' => 'success',
        ], self::CHANNEL_ENCOUNTER);
    }

    /**
     * Log encounter read/access
     */
    public function logEncounterRead(string $encounterId, $patientId = null): bool
    {
        return $this->logOperation(self::OP_READ, [
            'encounter_id' => $encounterId,
            'patient_id' => $patientId,
            'details' => ['action' => 'encounter_read'],
            'result' => 'success',
        ], self::CHANNEL_ENCOUNTER);
    }

    /**
     * Log encounter update
     */
    public function logEncounterUpdated(string $encounterId, array $fieldsUpdated = []): bool
    {
        return $this->logOperation(self::OP_UPDATE, [
            'encounter_id' => $encounterId,
            'details' => [
                'action' => 'encounter_updated',
                'fields_updated' => $fieldsUpdated,
                'fields_count' => count($fieldsUpdated),
            ],
            'result' => 'success',
        ], self::CHANNEL_ENCOUNTER);
    }

    /**
     * Log encounter deletion/cancellation
     */
    public function logEncounterDeleted(string $encounterId, string $reason = ''): bool
    {
        return $this->logOperation(self::OP_DELETE, [
            'encounter_id' => $encounterId,
            'details' => [
                'action' => 'encounter_deleted',
                'reason' => $reason,
            ],
            'result' => 'success',
        ], self::CHANNEL_ENCOUNTER);
    }

    /**
     * Log vitals recording
     */
    public function logVitalsRecorded(string $encounterId, array $vitalsRecorded = []): bool
    {
        // Remove actual values, only log field names
        $vitalFields = array_keys($vitalsRecorded);
        
        return $this->logOperation(self::OP_UPDATE, [
            'encounter_id' => $encounterId,
            'details' => [
                'action' => 'vitals_recorded',
                'vitals_fields' => $vitalFields,
                'vitals_count' => count($vitalFields),
            ],
            'result' => 'success',
        ], self::CHANNEL_VITALS);
    }

    /**
     * Log assessment addition
     */
    public function logAssessmentAdded(string $encounterId, array $assessmentData = []): bool
    {
        return $this->logOperation(self::OP_UPDATE, [
            'encounter_id' => $encounterId,
            'details' => [
                'action' => 'assessment_added',
                'has_diagnosis' => !empty($assessmentData['diagnosis'] ?? $assessmentData['assessment']),
                'icd_codes_count' => count($assessmentData['icd_codes'] ?? []),
            ],
            'result' => 'success',
        ], self::CHANNEL_ASSESSMENT);
    }

    /**
     * Log treatment addition
     */
    public function logTreatmentAdded(string $encounterId, array $treatmentData = []): bool
    {
        return $this->logOperation(self::OP_UPDATE, [
            'encounter_id' => $encounterId,
            'details' => [
                'action' => 'treatment_added',
                'has_plan' => !empty($treatmentData['plan'] ?? $treatmentData['treatment_plan']),
                'cpt_codes_count' => count($treatmentData['cpt_codes'] ?? []),
                'medications_count' => count($treatmentData['medications'] ?? []),
                'procedures_count' => count($treatmentData['procedures'] ?? []),
            ],
            'result' => 'success',
        ], self::CHANNEL_TREATMENT);
    }

    /**
     * Log signature event
     */
    public function logSignatureAdded(string $encounterId, string $signatureType, int $signedBy): bool
    {
        return $this->logOperation(self::OP_SIGN, [
            'encounter_id' => $encounterId,
            'details' => [
                'action' => 'signature_added',
                'signature_type' => $signatureType,
                'signed_by' => $signedBy,
            ],
            'result' => 'success',
        ], self::CHANNEL_SIGNATURE);
    }

    /**
     * Log encounter signing/locking
     */
    public function logEncounterSigned(string $encounterId, int $signedBy): bool
    {
        return $this->logOperation(self::OP_SIGN, [
            'encounter_id' => $encounterId,
            'details' => [
                'action' => 'encounter_signed',
                'signed_by' => $signedBy,
                'locked' => true,
            ],
            'result' => 'success',
        ], self::CHANNEL_SIGNATURE);
    }

    /**
     * Log finalization with full details
     */
    public function logFinalization(
        string $encounterId, 
        array $validationResults, 
        bool $success,
        array $additionalDetails = []
    ): bool {
        $logEntry = [
            'timestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
            'level' => $success ? 'INFO' : 'WARNING',
            'channel' => self::CHANNEL_FINALIZATION,
            'operation' => self::OP_FINALIZE,
            'user_id' => $this->getCurrentUserId(),
            'user_role' => $this->getCurrentUserRole(),
            'encounter_id' => $encounterId,
            'ip_address' => $this->getClientIP(),
            'user_agent' => $this->getUserAgent(),
            'request_id' => $this->getRequestId(),
            'details' => [
                'action' => 'encounter_finalized',
                'validation_passed' => $success,
                'validation_errors' => $success ? [] : ($validationResults['errors'] ?? []),
                'validation_warnings' => $validationResults['warnings'] ?? [],
                'is_work_related' => $additionalDetails['is_work_related'] ?? false,
                'status_changed_from' => $additionalDetails['previous_status'] ?? null,
                'status_changed_to' => 'finalized',
            ],
            'result' => $success ? 'success' : 'failure',
            'duration_ms' => isset($additionalDetails['start_time']) 
                ? (int)((microtime(true) - $additionalDetails['start_time']) * 1000)
                : 0,
        ];
        
        return $this->writeLog(self::CHANNEL_FINALIZATION, $logEntry);
    }

    /**
     * Log email notification attempt
     */
    public function logEmailNotification(
        string $encounterId, 
        array $recipients, 
        bool $success,
        string $errorMessage = ''
    ): bool {
        // Hash recipient emails for privacy
        $hashedRecipients = array_map(function($email) {
            return hash('sha256', strtolower(trim($email)));
        }, $recipients);
        
        $logEntry = [
            'timestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
            'level' => $success ? 'INFO' : 'ERROR',
            'channel' => self::CHANNEL_FINALIZATION,
            'operation' => 'SEND_EMAIL',
            'user_id' => $this->getCurrentUserId(),
            'encounter_id' => $encounterId,
            'ip_address' => $this->getClientIP(),
            'request_id' => $this->getRequestId(),
            'details' => [
                'action' => 'email_notification',
                'recipient_count' => count($recipients),
                'recipient_hashes' => $hashedRecipients,
                'notification_type' => 'work_related_incident',
            ],
            'result' => $success ? 'success' : 'failure',
            'error_message' => $success ? null : $errorMessage,
        ];
        
        return $this->writeLog(self::CHANNEL_FINALIZATION, $logEntry);
    }

    /**
     * Log SMS reminder attempt
     */
    public function logSMSReminder(
        string $encounterId, 
        string $phoneNumber, 
        bool $success,
        string $errorMessage = ''
    ): bool {
        // Hash phone number for HIPAA compliance
        $phoneHash = hash('sha256', preg_replace('/[^0-9]/', '', $phoneNumber));
        
        $logEntry = [
            'timestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
            'level' => $success ? 'INFO' : 'ERROR',
            'channel' => self::CHANNEL_FINALIZATION,
            'operation' => 'SEND_SMS',
            'user_id' => $this->getCurrentUserId(),
            'encounter_id' => $encounterId,
            'ip_address' => $this->getClientIP(),
            'request_id' => $this->getRequestId(),
            'details' => [
                'action' => 'sms_reminder',
                'phone_hash' => $phoneHash,
                'reminder_type' => 'follow_up',
            ],
            'result' => $success ? 'success' : 'failure',
            'error_message' => $success ? null : $errorMessage,
        ];
        
        return $this->writeLog(self::CHANNEL_FINALIZATION, $logEntry);
    }

    /**
     * Log encounter status transition
     */
    public function logStatusTransition(
        string $encounterId, 
        string $fromStatus, 
        string $toStatus,
        string $reason = ''
    ): bool {
        return $this->logOperation(self::OP_UPDATE, [
            'encounter_id' => $encounterId,
            'details' => [
                'action' => 'status_transition',
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'transition_reason' => $reason,
            ],
            'result' => 'success',
        ], self::CHANNEL_ENCOUNTER);
    }

    /**
     * Log amendment to signed encounter
     */
    public function logEncounterAmended(string $encounterId, string $reason, int $amendedBy): bool
    {
        return $this->logOperation(self::OP_AMEND, [
            'encounter_id' => $encounterId,
            'details' => [
                'action' => 'encounter_amended',
                'amendment_reason' => $this->sanitizeString($reason),
                'amended_by' => $amendedBy,
            ],
            'result' => 'success',
        ], self::CHANNEL_ENCOUNTER);
    }

    /**
     * Log error during EHR operation
     */
    public function logError(string $operation, string $errorMessage, array $context = []): bool
    {
        $channel = $context['channel'] ?? self::CHANNEL_EHR;
        
        $logEntry = [
            'timestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
            'level' => 'ERROR',
            'channel' => $channel,
            'operation' => $operation,
            'user_id' => $this->getCurrentUserId(),
            'user_role' => $this->getCurrentUserRole(),
            'encounter_id' => $context['encounter_id'] ?? null,
            'ip_address' => $this->getClientIP(),
            'user_agent' => $this->getUserAgent(),
            'request_id' => $this->getRequestId(),
            'details' => [
                'error_message' => $this->sanitizeString($errorMessage),
                'context' => $this->redactPHI($context),
            ],
            'result' => 'failure',
        ];
        
        return $this->writeLog($channel, $logEntry);
    }

    /**
     * Redact PHI from data array
     */
    private function redactPHI(array $data): array
    {
        if (empty($data)) {
            return $data;
        }
        
        $redacted = [];
        
        foreach ($data as $key => $value) {
            // Cast key to string to handle numeric array indices
            $lowerKey = strtolower((string) $key);
            
            // Check if key is a PHI field
            if (in_array($lowerKey, self::PHI_FIELDS)) {
                $redacted[$key] = '[REDACTED]';
                continue;
            }
            
            // Recursively process arrays
            if (is_array($value)) {
                $redacted[$key] = $this->redactPHI($value);
                continue;
            }
            
            // Process strings for PHI patterns
            if (is_string($value)) {
                $redacted[$key] = $this->redactStringPHI($value);
                continue;
            }
            
            $redacted[$key] = $value;
        }
        
        return $redacted;
    }

    /**
     * Redact PHI patterns from string
     */
    private function redactStringPHI(string $value): string
    {
        foreach (self::PHI_PATTERNS as $pattern => $replacement) {
            $value = preg_replace($pattern, $replacement, $value);
        }
        return $value;
    }

    /**
     * Hash patient ID for logging (one-way, for correlation only)
     */
    private function hashPatientId($patientId): string
    {
        $salt = $_ENV['PHI_HASH_SALT'] ?? 'safeshift_ehr_phi_salt_2024';
        return hash('sha256', $patientId . $salt);
    }

    /**
     * Sanitize string for logging (remove control characters)
     */
    private function sanitizeString(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);
        return substr($value, 0, 2000); // Truncate long strings
    }

    /**
     * Write log entry to appropriate file
     */
    private function writeLog(string $channel, array $entry): bool
    {
        try {
            $date = date('Y-m-d');
            $filename = $this->logPath . $channel . '_' . $date . '.log';
            
            // Add hash for tamper detection
            $entry['hash'] = hash('sha256', json_encode($entry) . ($this->getLastHash($channel) ?? ''));
            
            $logLine = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
            
            $result = file_put_contents($filename, $logLine, FILE_APPEND | LOCK_EX);
            
            if ($result !== false) {
                $this->updateLastHash($channel, $entry['hash']);
            }
            
            return $result !== false;
        } catch (Exception $e) {
            error_log("EHRLogger write failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get last hash for a channel (for chain integrity)
     */
    private function getLastHash(string $channel): ?string
    {
        $hashFile = $this->logPath . '.ehr_hash_' . $channel;
        if (file_exists($hashFile)) {
            return file_get_contents($hashFile);
        }
        return null;
    }

    /**
     * Update last hash for a channel
     */
    private function updateLastHash(string $channel, string $hash): void
    {
        $hashFile = $this->logPath . '.ehr_hash_' . $channel;
        file_put_contents($hashFile, $hash, LOCK_EX);
    }

    /**
     * Get current user ID from session
     * Note: User IDs can be integers or UUID strings
     */
    private function getCurrentUserId(): int|string|null
    {
        return $_SESSION['user']['user_id'] ?? null;
    }

    /**
     * Get current user role from session
     */
    private function getCurrentUserRole(): ?string
    {
        return $_SESSION['user']['role'] ?? $_SESSION['user']['role_name'] ?? null;
    }

    /**
     * Get client IP address
     */
    private function getClientIP(): string
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                return trim($ip);
            }
        }
        
        return '0.0.0.0';
    }

    /**
     * Get user agent string
     */
    private function getUserAgent(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        return substr($ua, 0, 500); // Truncate long user agents
    }

    /**
     * Get or generate request ID for correlation
     */
    private function getRequestId(): string
    {
        if (!isset($_SERVER['X_REQUEST_ID'])) {
            $_SERVER['X_REQUEST_ID'] = 'ehr_' . uniqid('', true);
        }
        return $_SERVER['X_REQUEST_ID'];
    }

    /**
     * Verify log integrity for a specific channel and date
     */
    public function verifyLogIntegrity(string $channel, string $date): array
    {
        $filename = $this->logPath . $channel . '_' . $date . '.log';
        
        if (!file_exists($filename)) {
            return ['valid' => false, 'error' => 'Log file not found'];
        }
        
        $lines = file($filename, FILE_IGNORE_NEW_LINES);
        $previousHash = '';
        $lineNumber = 0;
        
        foreach ($lines as $line) {
            $lineNumber++;
            $entry = json_decode($line, true);
            
            if (!$entry || !isset($entry['hash'])) {
                return [
                    'valid' => false,
                    'error' => "Invalid entry at line $lineNumber",
                    'line' => $lineNumber
                ];
            }
            
            $storedHash = $entry['hash'];
            unset($entry['hash']);
            
            $calculatedHash = hash('sha256', json_encode($entry) . $previousHash);
            
            if ($calculatedHash !== $storedHash) {
                return [
                    'valid' => false,
                    'error' => "Hash mismatch at line $lineNumber - possible tampering",
                    'line' => $lineNumber
                ];
            }
            
            $previousHash = $storedHash;
        }
        
        return [
            'valid' => true,
            'entries_verified' => $lineNumber,
            'channel' => $channel,
            'date' => $date
        ];
    }

    /**
     * Get log statistics for a channel
     */
    public function getLogStatistics(string $channel, string $date = null): array
    {
        $date = $date ?? date('Y-m-d');
        $filename = $this->logPath . $channel . '_' . $date . '.log';
        
        if (!file_exists($filename)) {
            return ['error' => 'Log file not found'];
        }
        
        $stats = [
            'channel' => $channel,
            'date' => $date,
            'total_entries' => 0,
            'by_operation' => [],
            'by_result' => ['success' => 0, 'failure' => 0],
            'by_user' => [],
            'file_size_bytes' => filesize($filename),
        ];
        
        $handle = fopen($filename, 'r');
        while (($line = fgets($handle)) !== false) {
            $entry = json_decode($line, true);
            if (!$entry) continue;
            
            $stats['total_entries']++;
            
            // Count by operation
            $op = $entry['operation'] ?? 'unknown';
            $stats['by_operation'][$op] = ($stats['by_operation'][$op] ?? 0) + 1;
            
            // Count by result
            $result = $entry['result'] ?? 'unknown';
            if (isset($stats['by_result'][$result])) {
                $stats['by_result'][$result]++;
            }
            
            // Count by user
            $userId = $entry['user_id'] ?? 'anonymous';
            $stats['by_user'][$userId] = ($stats['by_user'][$userId] ?? 0) + 1;
        }
        fclose($handle);
        
        return $stats;
    }
}
