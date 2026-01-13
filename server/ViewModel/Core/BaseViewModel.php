<?php
/**
 * BaseViewModel - Abstract base class for all ViewModels
 * 
 * Provides common functionality for authentication, authorization,
 * input handling, validation, audit logging, and pagination.
 * 
 * @package SafeShift\ViewModel\Core
 */

declare(strict_types=1);

namespace ViewModel\Core;

use Core\Services\AuditService;
use App\Core\Session;
use Exception;
use PDO;

/**
 * Abstract base class for all ViewModels
 * 
 * All ViewModels should extend this class to inherit common functionality
 * for session validation, role checking, input handling, and audit logging.
 */
abstract class BaseViewModel
{
    /** @var AuditService Audit logging service */
    protected AuditService $auditService;
    
    /** @var PDO|null Database connection */
    protected ?PDO $pdo = null;
    
    /** @var array|null Current user data */
    protected ?array $currentUser = null;
    
    /** @var string Log file path */
    protected string $logPath;
    
    /** @var array Role-based permissions mapping */
    protected const ROLE_PERMISSIONS = [
        'tadmin' => ['*'], // Full access
        'cadmin' => [
            'view_patients', 'edit_patients', 'view_encounters', 'edit_encounters',
            'view_reports', 'view_audit_logs', 'view_compliance', 'manage_users'
        ],
        'Admin' => [
            'view_patients', 'edit_patients', 'view_encounters', 'edit_encounters',
            'view_reports', 'view_audit_logs', 'view_compliance'
        ],
        'pclinician' => [
            'view_patients', 'edit_patients', 'view_encounters', 'edit_encounters',
            'sign_encounters', 'view_reports', 'dot_mro_verify'
        ],
        'dclinician' => [
            'view_patients', 'edit_patients', 'view_encounters', 'edit_encounters',
            'sign_encounters', 'view_reports'
        ],
        '1clinician' => [
            'view_patients', 'view_encounters', 'edit_encounters', 'view_reports'
        ],
        'Manager' => [
            'view_patients', 'view_encounters', 'view_reports', 'view_compliance',
            'qa_review'
        ],
        'QA' => [
            'view_patients', 'view_encounters', 'qa_review'
        ],
        'Employee' => [
            'view_own_records'
        ],
        'Employer' => [
            'view_employee_records', 'view_compliance_reports'
        ]
    ];

    /**
     * Constructor
     * 
     * @param AuditService|null $auditService
     * @param PDO|null $pdo
     * @param string|null $logPath
     */
    public function __construct(
        ?AuditService $auditService = null,
        ?PDO $pdo = null,
        ?string $logPath = null
    ) {
        $this->auditService = $auditService ?? new AuditService();
        $this->pdo = $pdo ?? $this->getDefaultPdo();
        $this->logPath = $logPath ?? (defined('LOG_PATH') ? LOG_PATH : dirname(__DIR__, 2) . '/logs/');
        
        // Initialize current user from session
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->currentUser = $_SESSION['user'] ?? null;
        }
    }

    /**
     * Get default PDO connection
     * 
     * @return PDO|null
     */
    protected function getDefaultPdo(): ?PDO
    {
        try {
            if (class_exists('\\Core\\Database')) {
                return \Core\Database::getInstance()->getConnection();
            }
            
            // Fallback to global connection if available
            global $pdo;
            return $pdo ?? null;
        } catch (Exception $e) {
            $this->logError('getDefaultPdo', $e);
            return null;
        }
    }

    // ========== AUTHENTICATION & AUTHORIZATION ==========

    /**
     * Check if user is authenticated
     * 
     * @throws Exception If not authenticated
     */
    protected function requireAuth(): void
    {
        if (!$this->isAuthenticated()) {
            throw new Exception('Authentication required', 401);
        }
    }

    /**
     * Check if user is authenticated
     * 
     * @return bool
     */
    protected function isAuthenticated(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            return false;
        }
        
        if (class_exists('\\App\\Core\\Session')) {
            return Session::isLoggedIn();
        }
        
        // Fallback check
        return isset($_SESSION['user']) && !empty($_SESSION['user']['user_id']);
    }

    /**
     * Check if current user has a specific role
     * 
     * @param string $role Role to check
     * @throws Exception If user doesn't have required role
     */
    protected function requireRole(string $role): void
    {
        $this->requireAuth();
        
        $userRole = $this->getCurrentUserRole();
        
        // Admin roles have access to everything
        if (in_array($userRole, ['tadmin', 'cadmin', 'Admin'])) {
            return;
        }
        
        if ($userRole !== $role) {
            $this->auditService->logUnauthorizedAccess('role_required', $this->getCurrentUserId(), [
                'required_role' => $role,
                'actual_role' => $userRole
            ]);
            throw new Exception('Insufficient permissions', 403);
        }
    }

    /**
     * Check if current user has a specific permission
     * 
     * @param string $permission Permission to check
     * @return bool
     */
    protected function hasPermission(string $permission): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        $userRole = $this->getCurrentUserRole();
        
        if (!$userRole) {
            return false;
        }
        
        $permissions = self::ROLE_PERMISSIONS[$userRole] ?? [];
        
        // Full access for tadmin
        if (in_array('*', $permissions, true)) {
            return true;
        }
        
        return in_array($permission, $permissions, true);
    }

    /**
     * Require a specific permission
     * 
     * @param string $permission Permission required
     * @throws Exception If permission not granted
     */
    protected function requirePermission(string $permission): void
    {
        $this->requireAuth();
        
        if (!$this->hasPermission($permission)) {
            $this->auditService->logUnauthorizedAccess($permission, $this->getCurrentUserId(), [
                'permission' => $permission,
                'user_role' => $this->getCurrentUserRole()
            ]);
            throw new Exception('Permission denied: ' . $permission, 403);
        }
    }

    /**
     * Get current user ID
     *
     * @return string|null
     */
    protected function getCurrentUserId(): ?string
    {
        $userId = $this->currentUser['user_id'] ?? $_SESSION['user']['user_id'] ?? null;
        return $userId !== null ? (string)$userId : null;
    }

    /**
     * Get current user role
     * 
     * @return string|null
     */
    protected function getCurrentUserRole(): ?string
    {
        return $this->currentUser['role'] 
            ?? $this->currentUser['primary_role'] 
            ?? $_SESSION['user']['role'] 
            ?? $_SESSION['user']['primary_role'] 
            ?? null;
    }

    /**
     * Get current user data
     * 
     * @return array|null
     */
    protected function getCurrentUser(): ?array
    {
        return $this->currentUser ?? $_SESSION['user'] ?? null;
    }

    // ========== INPUT HANDLING ==========

    /**
     * Get JSON input from request body
     * 
     * @return array
     */
    protected function getInput(): array
    {
        $rawInput = file_get_contents('php://input');
        
        if (empty($rawInput)) {
            return [];
        }
        
        $decoded = json_decode($rawInput, true);
        
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Validate input against rules
     * 
     * @param array $data Input data
     * @param array $rules Validation rules
     * @return array Validated and sanitized data
     * @throws Exception If validation fails
     */
    protected function validateInput(array $data, array $rules): array
    {
        $validated = [];
        $errors = [];
        
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $ruleList = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);
            
            foreach ($ruleList as $rule) {
                [$ruleName, $ruleParam] = $this->parseRule($rule);
                
                $error = $this->applyRule($ruleName, $ruleParam, $field, $value);
                
                if ($error !== null) {
                    $errors[$field] = $error;
                    break;
                }
            }
            
            // Add to validated if no errors
            if (!isset($errors[$field]) && $value !== null) {
                $validated[$field] = $this->sanitizeValue($value);
            }
        }
        
        if (!empty($errors)) {
            throw new Exception(json_encode(['validation_errors' => $errors]), 422);
        }
        
        return $validated;
    }

    /**
     * Parse validation rule
     * 
     * @param string $rule Rule string
     * @return array [ruleName, ruleParam]
     */
    private function parseRule(string $rule): array
    {
        if (str_contains($rule, ':')) {
            return explode(':', $rule, 2);
        }
        
        return [$rule, null];
    }

    /**
     * Apply validation rule
     * 
     * @param string $rule Rule name
     * @param mixed $param Rule parameter
     * @param string $field Field name
     * @param mixed $value Field value
     * @return string|null Error message or null if valid
     */
    private function applyRule(string $rule, $param, string $field, $value): ?string
    {
        return match ($rule) {
            'required' => $value === null || $value === '' ? "$field is required" : null,
            'string' => !is_string($value) && $value !== null ? "$field must be a string" : null,
            'int', 'integer' => !is_numeric($value) && $value !== null ? "$field must be an integer" : null,
            'email' => $value && !filter_var($value, FILTER_VALIDATE_EMAIL) ? "$field must be a valid email" : null,
            'min' => $value && strlen((string)$value) < (int)$param ? "$field must be at least $param characters" : null,
            'max' => $value && strlen((string)$value) > (int)$param ? "$field cannot exceed $param characters" : null,
            'uuid' => $value && !$this->isValidUuid($value) ? "$field must be a valid UUID" : null,
            'date' => $value && !strtotime($value) ? "$field must be a valid date" : null,
            'in' => $value && !in_array($value, explode(',', $param)) ? "$field must be one of: $param" : null,
            default => null,
        };
    }

    /**
     * Sanitize value for storage
     * 
     * @param mixed $value Value to sanitize
     * @return mixed
     */
    protected function sanitizeValue($value)
    {
        if (is_string($value)) {
            return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
        }
        
        if (is_array($value)) {
            return array_map([$this, 'sanitizeValue'], $value);
        }
        
        return $value;
    }

    /**
     * Check if string is a valid UUID
     * 
     * @param string $uuid String to check
     * @return bool
     */
    protected function isValidUuid(string $uuid): bool
    {
        return (bool)preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $uuid
        );
    }

    // ========== AUDIT LOGGING ==========

    /**
     * Log an audit event
     * 
     * @param string $action Action performed
     * @param string $resource Resource type
     * @param string|null $resourceId Resource ID
     * @param array $metadata Additional metadata
     */
    protected function audit(string $action, string $resource, ?string $resourceId = null, array $metadata = []): void
    {
        try {
            $this->auditService->audit(
                $action,
                $resource,
                $resourceId,
                '',
                array_merge($metadata, [
                    'user_id' => $this->getCurrentUserId(),
                    'user_role' => $this->getCurrentUserRole()
                ])
            );
        } catch (Exception $e) {
            // Log error but don't fail the operation
            $this->logError('audit', $e, [
                'action' => $action,
                'resource' => $resource,
                'resource_id' => $resourceId
            ]);
        }
    }

    /**
     * Log PHI access (HIPAA requirement)
     * 
     * @param string $resourceType Resource type (patient, encounter, etc.)
     * @param string $resourceId Resource ID
     * @param string $accessType Type of access (view, edit, export)
     */
    protected function logPhiAccess(string $resourceType, string $resourceId, string $accessType = 'view'): void
    {
        $this->audit('PHI_ACCESS', $resourceType, $resourceId, [
            'access_type' => $accessType,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z')
        ]);
    }

    // ========== PAGINATION ==========

    /**
     * Paginate array data
     * 
     * @param array $data Data to paginate
     * @param int $page Current page (1-based)
     * @param int $perPage Items per page
     * @return array Paginated result
     */
    protected function paginate(array $data, int $page = 1, int $perPage = 20): array
    {
        $total = count($data);
        $totalPages = (int)ceil($total / $perPage);
        $page = max(1, min($page, $totalPages ?: 1));
        $offset = ($page - 1) * $perPage;
        
        $items = array_slice($data, $offset, $perPage);
        
        return [
            'items' => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_items' => $total,
                'total_pages' => $totalPages,
                'has_previous' => $page > 1,
                'has_next' => $page < $totalPages
            ]
        ];
    }

    /**
     * Get pagination parameters from input
     * 
     * @param array $input Input data
     * @return array [page, perPage]
     */
    protected function getPaginationParams(array $input): array
    {
        $page = max(1, (int)($input['page'] ?? 1));
        $perPage = min(100, max(1, (int)($input['per_page'] ?? $input['limit'] ?? 20)));
        
        return [$page, $perPage];
    }

    // ========== ERROR HANDLING ==========

    /**
     * Log error with context
     * 
     * @param string $method Method where error occurred
     * @param Exception $e The exception
     * @param array $context Additional context
     */
    protected function logError(string $method, Exception $e, array $context = []): void
    {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'class' => static::class,
            'method' => $method,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'user_id' => $this->getCurrentUserId() ?? 'anonymous',
            'context' => $context
        ];
        
        error_log(static::class . ' Error: ' . json_encode($logEntry));
        
        try {
            $logFile = $this->logPath . 'error_' . date('Y-m-d') . '.log';
            $logLine = date('Y-m-d H:i:s') . ' | ERROR | ' . json_encode($logEntry) . PHP_EOL;
            file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
        } catch (Exception $logException) {
            error_log('Failed to write to error log: ' . $logException->getMessage());
        }
    }

    /**
     * Handle exception and return appropriate API response
     * 
     * @param Exception $e Exception to handle
     * @param string $defaultMessage Default error message
     * @return array API response
     */
    protected function handleException(Exception $e, string $defaultMessage = 'An error occurred'): array
    {
        $code = $e->getCode();
        $message = $e->getMessage();
        
        // Check for validation errors
        if ($code === 422) {
            $decoded = json_decode($message, true);
            if (isset($decoded['validation_errors'])) {
                return ApiResponse::validationError($decoded['validation_errors']);
            }
        }
        
        return match ($code) {
            401 => ApiResponse::unauthorized($message ?: 'Not authenticated'),
            403 => ApiResponse::forbidden($message ?: 'Access denied'),
            404 => ApiResponse::notFound($message ?: 'Resource not found'),
            422 => ApiResponse::validationError(['error' => [$message]]),
            429 => ApiResponse::rateLimited($message ?: 'Too many requests'),
            default => ApiResponse::serverError($defaultMessage),
        };
    }

    // ========== UTILITY METHODS ==========

    /**
     * Generate UUID v4
     * 
     * @return string
     */
    protected function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Format date/time for display
     * 
     * @param string|null $datetime Date/time value
     * @param string $format Output format
     * @return string Formatted date/time or empty string
     */
    protected function formatDateTime(?string $datetime, string $format = 'M j, Y g:i A'): string
    {
        if (empty($datetime)) {
            return '';
        }
        
        $timestamp = strtotime($datetime);
        
        return $timestamp === false ? '' : date($format, $timestamp);
    }

    /**
     * Calculate age from date of birth
     * 
     * @param string|null $dob Date of birth
     * @return int|null Age in years
     */
    protected function calculateAge(?string $dob): ?int
    {
        if (empty($dob)) {
            return null;
        }
        
        $birthDate = strtotime($dob);
        
        if ($birthDate === false) {
            return null;
        }
        
        $today = time();
        $age = date('Y', $today) - date('Y', $birthDate);
        
        if (date('md', $today) < date('md', $birthDate)) {
            $age--;
        }
        
        return $age;
    }
}
