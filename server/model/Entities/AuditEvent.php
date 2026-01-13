<?php
/**
 * AuditEvent.php - Audit Event Entity for SafeShift EHR
 * 
 * Represents an audit log event for HIPAA compliance tracking.
 * Records all significant system events with user, resource, and action details.
 * 
 * @package    SafeShift\Model\Entities
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace Model\Entities;

use Model\Interfaces\EntityInterface;
use DateTimeInterface;
use DateTimeImmutable;

/**
 * AuditEvent entity
 * 
 * Represents an audit log event in the SafeShift EHR system.
 * Provides HIPAA-compliant audit trail for PHI access and system events.
 * This entity is immutable after creation (audit logs cannot be modified).
 */
final class AuditEvent implements EntityInterface
{
    /** @var string|null Event unique identifier (UUID) */
    private ?string $eventId;
    
    /** @var string|null User ID who performed the action */
    private ?string $userId;
    
    /** @var string|null Username for display */
    private ?string $username;
    
    /** @var string|null User role at time of event */
    private ?string $userRole;
    
    /** @var string Action performed */
    private string $action;
    
    /** @var string Resource type being accessed */
    private string $resourceType;
    
    /** @var string|null Resource ID being accessed */
    private ?string $resourceId;
    
    /** @var string|null Additional event details (JSON) */
    private ?string $details;
    
    /** @var string|null Old values before change (JSON) */
    private ?string $oldValues;
    
    /** @var string|null New values after change (JSON) */
    private ?string $newValues;
    
    /** @var string|null Client IP address */
    private ?string $ipAddress;
    
    /** @var string|null User agent string */
    private ?string $userAgent;
    
    /** @var string|null Session ID */
    private ?string $sessionId;
    
    /** @var string|null Request method */
    private ?string $requestMethod;
    
    /** @var string|null Request URI */
    private ?string $requestUri;
    
    /** @var string Event severity level */
    private string $severity;
    
    /** @var string Event category */
    private string $category;
    
    /** @var string|null Clinic ID context */
    private ?string $clinicId;
    
    /** @var string|null Checksum for integrity verification */
    private ?string $checksum;
    
    /** @var DateTimeInterface Event timestamp */
    private DateTimeInterface $createdAt;

    /** Action constants */
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_LOGIN_FAILED = 'login_failed';
    public const ACTION_PASSWORD_CHANGE = 'password_change';
    public const ACTION_PASSWORD_RESET = 'password_reset';
    public const ACTION_CREATE = 'create';
    public const ACTION_READ = 'read';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';
    public const ACTION_EXPORT = 'export';
    public const ACTION_PRINT = 'print';
    public const ACTION_SEARCH = 'search';
    public const ACTION_LOCK = 'lock';
    public const ACTION_UNLOCK = 'unlock';
    public const ACTION_APPROVE = 'approve';
    public const ACTION_REJECT = 'reject';
    public const ACTION_AMEND = 'amend';
    public const ACTION_ACCESS_DENIED = 'access_denied';
    public const ACTION_PERMISSION_CHANGE = 'permission_change';

    /** Resource type constants */
    public const RESOURCE_USER = 'user';
    public const RESOURCE_PATIENT = 'patient';
    public const RESOURCE_ENCOUNTER = 'encounter';
    public const RESOURCE_DOT_TEST = 'dot_test';
    public const RESOURCE_OSHA_INJURY = 'osha_injury';
    public const RESOURCE_DOCUMENT = 'document';
    public const RESOURCE_REPORT = 'report';
    public const RESOURCE_SYSTEM = 'system';
    public const RESOURCE_CONFIG = 'config';
    public const RESOURCE_AUDIT_LOG = 'audit_log';

    /** Severity constants */
    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_CRITICAL = 'critical';

    /** Category constants */
    public const CATEGORY_AUTHENTICATION = 'authentication';
    public const CATEGORY_AUTHORIZATION = 'authorization';
    public const CATEGORY_DATA_ACCESS = 'data_access';
    public const CATEGORY_DATA_MODIFICATION = 'data_modification';
    public const CATEGORY_SYSTEM = 'system';
    public const CATEGORY_SECURITY = 'security';
    public const CATEGORY_COMPLIANCE = 'compliance';

    /**
     * Create a new AuditEvent instance
     * 
     * @param string $action Action performed
     * @param string $resourceType Resource type
     * @param string|null $resourceId Resource ID
     */
    public function __construct(
        string $action,
        string $resourceType,
        ?string $resourceId = null
    ) {
        $this->eventId = null;
        $this->userId = null;
        $this->username = null;
        $this->userRole = null;
        $this->action = $action;
        $this->resourceType = $resourceType;
        $this->resourceId = $resourceId;
        $this->details = null;
        $this->oldValues = null;
        $this->newValues = null;
        $this->ipAddress = $this->captureIpAddress();
        $this->userAgent = $this->captureUserAgent();
        $this->sessionId = session_id() ?: null;
        $this->requestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $this->requestUri = $this->sanitizeUri($_SERVER['REQUEST_URI'] ?? null);
        $this->severity = self::SEVERITY_INFO;
        $this->category = $this->determineCategory($action);
        $this->clinicId = null;
        $this->checksum = null;
        $this->createdAt = new DateTimeImmutable();
    }

    /**
     * Capture client IP address
     * 
     * @return string|null
     */
    private function captureIpAddress(): ?string
    {
        // Check for proxy headers (in order of reliability)
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
                // Handle comma-separated list of IPs
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return null;
    }

    /**
     * Capture user agent
     * 
     * @return string|null
     */
    private function captureUserAgent(): ?string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Limit length for storage
        if ($userAgent !== null && strlen($userAgent) > 500) {
            $userAgent = substr($userAgent, 0, 500);
        }
        
        return $userAgent;
    }

    /**
     * Sanitize request URI (remove sensitive query params)
     * 
     * @param string|null $uri
     * @return string|null
     */
    private function sanitizeUri(?string $uri): ?string
    {
        if ($uri === null) {
            return null;
        }
        
        // Remove sensitive query parameters
        $sensitiveParams = ['password', 'token', 'api_key', 'ssn'];
        
        $parsed = parse_url($uri);
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $params);
            
            foreach ($sensitiveParams as $param) {
                if (isset($params[$param])) {
                    $params[$param] = '[REDACTED]';
                }
            }
            
            $parsed['query'] = http_build_query($params);
        }
        
        $sanitized = $parsed['path'] ?? '/';
        if (!empty($parsed['query'])) {
            $sanitized .= '?' . $parsed['query'];
        }
        
        return $sanitized;
    }

    /**
     * Determine event category from action
     * 
     * @param string $action
     * @return string
     */
    private function determineCategory(string $action): string
    {
        return match ($action) {
            self::ACTION_LOGIN,
            self::ACTION_LOGOUT,
            self::ACTION_LOGIN_FAILED,
            self::ACTION_PASSWORD_CHANGE,
            self::ACTION_PASSWORD_RESET => self::CATEGORY_AUTHENTICATION,
            
            self::ACTION_ACCESS_DENIED,
            self::ACTION_PERMISSION_CHANGE => self::CATEGORY_AUTHORIZATION,
            
            self::ACTION_READ,
            self::ACTION_SEARCH,
            self::ACTION_EXPORT,
            self::ACTION_PRINT => self::CATEGORY_DATA_ACCESS,
            
            self::ACTION_CREATE,
            self::ACTION_UPDATE,
            self::ACTION_DELETE,
            self::ACTION_LOCK,
            self::ACTION_UNLOCK,
            self::ACTION_AMEND => self::CATEGORY_DATA_MODIFICATION,
            
            default => self::CATEGORY_SYSTEM,
        };
    }

    /**
     * Get the event ID
     * 
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->eventId;
    }

    /**
     * Set the event ID
     * 
     * @param string $eventId
     * @return self
     */
    public function setId(string $eventId): self
    {
        if ($this->eventId !== null) {
            throw new \RuntimeException('Cannot modify event ID once set');
        }
        $this->eventId = $eventId;
        return $this;
    }

    /**
     * Set user information
     * 
     * @param string $userId
     * @param string $username
     * @param string $role
     * @return self
     */
    public function setUser(string $userId, string $username, string $role): self
    {
        $this->userId = $userId;
        $this->username = $username;
        $this->userRole = $role;
        return $this;
    }

    /**
     * Get user ID
     * 
     * @return string|null
     */
    public function getUserId(): ?string
    {
        return $this->userId;
    }

    /**
     * Get username
     * 
     * @return string|null
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * Get action
     * 
     * @return string
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * Get resource type
     * 
     * @return string
     */
    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    /**
     * Get resource ID
     * 
     * @return string|null
     */
    public function getResourceId(): ?string
    {
        return $this->resourceId;
    }

    /**
     * Set event details (sanitized JSON)
     * 
     * @param array<string, mixed> $details
     * @return self
     */
    public function setDetails(array $details): self
    {
        // Remove any PHI from details
        $sanitized = $this->sanitizeDetails($details);
        $this->details = json_encode($sanitized);
        return $this;
    }

    /**
     * Get event details
     * 
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return $this->details ? json_decode($this->details, true) : [];
    }

    /**
     * Sanitize details to remove PHI
     * 
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    private function sanitizeDetails(array $details): array
    {
        $sensitiveKeys = [
            'ssn', 'social_security', 'password', 'dob', 'date_of_birth',
            'credit_card', 'card_number', 'cvv', 'address', 'phone',
        ];
        
        $sanitized = [];
        
        foreach ($details as $key => $value) {
            $lowercaseKey = strtolower($key);
            
            $isSensitive = false;
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (str_contains($lowercaseKey, $sensitiveKey)) {
                    $isSensitive = true;
                    break;
                }
            }
            
            if ($isSensitive) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeDetails($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Set old values (for update operations)
     * 
     * @param array<string, mixed> $values
     * @return self
     */
    public function setOldValues(array $values): self
    {
        $this->oldValues = json_encode($this->sanitizeDetails($values));
        return $this;
    }

    /**
     * Set new values (for create/update operations)
     * 
     * @param array<string, mixed> $values
     * @return self
     */
    public function setNewValues(array $values): self
    {
        $this->newValues = json_encode($this->sanitizeDetails($values));
        return $this;
    }

    /**
     * Set severity level
     * 
     * @param string $severity
     * @return self
     */
    public function setSeverity(string $severity): self
    {
        $validSeverities = [
            self::SEVERITY_INFO,
            self::SEVERITY_WARNING,
            self::SEVERITY_ERROR,
            self::SEVERITY_CRITICAL,
        ];
        
        if (in_array($severity, $validSeverities, true)) {
            $this->severity = $severity;
        }
        
        return $this;
    }

    /**
     * Get severity
     * 
     * @return string
     */
    public function getSeverity(): string
    {
        return $this->severity;
    }

    /**
     * Set category
     * 
     * @param string $category
     * @return self
     */
    public function setCategory(string $category): self
    {
        $this->category = $category;
        return $this;
    }

    /**
     * Get category
     * 
     * @return string
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * Set clinic ID
     * 
     * @param string $clinicId
     * @return self
     */
    public function setClinicId(string $clinicId): self
    {
        $this->clinicId = $clinicId;
        return $this;
    }

    /**
     * Get IP address
     * 
     * @return string|null
     */
    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    /**
     * Get session ID
     * 
     * @return string|null
     */
    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    /**
     * Generate checksum for integrity verification
     * 
     * @return self
     */
    public function generateChecksum(): self
    {
        $data = implode('|', [
            $this->eventId ?? '',
            $this->userId ?? '',
            $this->action,
            $this->resourceType,
            $this->resourceId ?? '',
            $this->createdAt->format('Y-m-d H:i:s'),
        ]);
        
        $this->checksum = hash('sha256', $data);
        return $this;
    }

    /**
     * Verify checksum integrity
     * 
     * @return bool
     */
    public function verifyChecksum(): bool
    {
        if ($this->checksum === null) {
            return false;
        }
        
        $data = implode('|', [
            $this->eventId ?? '',
            $this->userId ?? '',
            $this->action,
            $this->resourceType,
            $this->resourceId ?? '',
            $this->createdAt->format('Y-m-d H:i:s'),
        ]);
        
        return hash_equals($this->checksum, hash('sha256', $data));
    }

    /**
     * Get checksum
     * 
     * @return string|null
     */
    public function getChecksum(): ?string
    {
        return $this->checksum;
    }

    /**
     * Check if entity has been persisted
     * 
     * @return bool
     */
    public function isPersisted(): bool
    {
        return $this->eventId !== null;
    }

    /**
     * Get creation timestamp
     * 
     * @return DateTimeInterface|null
     */
    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Get update timestamp (always same as created for audit events)
     * 
     * @return DateTimeInterface|null
     */
    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * Validate entity data
     * 
     * @return array<string, string>
     */
    public function validate(): array
    {
        $errors = [];
        
        if (empty($this->action)) {
            $errors['action'] = 'Action is required';
        }
        
        if (empty($this->resourceType)) {
            $errors['resource_type'] = 'Resource type is required';
        }
        
        return $errors;
    }

    /**
     * Convert to array
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'user_id' => $this->userId,
            'username' => $this->username,
            'user_role' => $this->userRole,
            'action' => $this->action,
            'resource_type' => $this->resourceType,
            'resource_id' => $this->resourceId,
            'details' => $this->details,
            'old_values' => $this->oldValues,
            'new_values' => $this->newValues,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'session_id' => $this->sessionId,
            'request_method' => $this->requestMethod,
            'request_uri' => $this->requestUri,
            'severity' => $this->severity,
            'category' => $this->category,
            'clinic_id' => $this->clinicId,
            'checksum' => $this->checksum,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Convert to safe array (for external exposure)
     * 
     * @return array<string, mixed>
     */
    public function toSafeArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'user_id' => $this->userId,
            'username' => $this->username,
            'action' => $this->action,
            'resource_type' => $this->resourceType,
            'resource_id' => $this->resourceId,
            'severity' => $this->severity,
            'category' => $this->category,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Create from array data
     * 
     * @param array<string, mixed> $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        $event = new static(
            $data['action'],
            $data['resource_type'],
            $data['resource_id'] ?? null
        );
        
        if (isset($data['event_id'])) {
            $event->eventId = $data['event_id'];
        }
        
        $event->userId = $data['user_id'] ?? null;
        $event->username = $data['username'] ?? null;
        $event->userRole = $data['user_role'] ?? null;
        $event->details = $data['details'] ?? null;
        $event->oldValues = $data['old_values'] ?? null;
        $event->newValues = $data['new_values'] ?? null;
        $event->ipAddress = $data['ip_address'] ?? null;
        $event->userAgent = $data['user_agent'] ?? null;
        $event->sessionId = $data['session_id'] ?? null;
        $event->requestMethod = $data['request_method'] ?? null;
        $event->requestUri = $data['request_uri'] ?? null;
        $event->severity = $data['severity'] ?? self::SEVERITY_INFO;
        $event->category = $data['category'] ?? self::CATEGORY_SYSTEM;
        $event->clinicId = $data['clinic_id'] ?? null;
        $event->checksum = $data['checksum'] ?? null;
        
        if (isset($data['created_at'])) {
            $event->createdAt = new DateTimeImmutable($data['created_at']);
        }
        
        return $event;
    }

    /**
     * Create a login event
     * 
     * @param string $userId
     * @param string $username
     * @param bool $success
     * @return self
     */
    public static function createLoginEvent(string $userId, string $username, bool $success = true): self
    {
        $action = $success ? self::ACTION_LOGIN : self::ACTION_LOGIN_FAILED;
        $event = new self($action, self::RESOURCE_USER, $userId);
        $event->userId = $userId;
        $event->username = $username;
        $event->severity = $success ? self::SEVERITY_INFO : self::SEVERITY_WARNING;
        
        return $event;
    }

    /**
     * Create a PHI access event
     * 
     * @param string $userId
     * @param string $username
     * @param string $role
     * @param string $patientId
     * @param string $action
     * @return self
     */
    public static function createPhiAccessEvent(
        string $userId,
        string $username,
        string $role,
        string $patientId,
        string $action = self::ACTION_READ
    ): self {
        $event = new self($action, self::RESOURCE_PATIENT, $patientId);
        $event->setUser($userId, $username, $role);
        $event->setCategory(self::CATEGORY_DATA_ACCESS);
        
        return $event;
    }
}
