<?php
/**
 * Audit Middleware
 * 
 * Provides automatic audit logging for API endpoints.
 * Can be applied to any API endpoint to automatically log requests
 * without modifying the endpoint code.
 * 
 * Features:
 * - Automatic request/response logging
 * - Timing measurement
 * - Error capture
 * - PHI access detection
 * - User context capture
 * 
 * @package SafeShift\Core\Middleware
 * @version 1.0.0
 */

declare(strict_types=1);

namespace Core\Middleware;

use Core\Services\AuditService;
use Core\Traits\Auditable;
use Exception;

/**
 * Class AuditMiddleware
 * 
 * Middleware for automatic audit logging of API requests.
 */
class AuditMiddleware
{
    use Auditable;
    
    /**
     * @var AuditService
     */
    private AuditService $auditService;
    
    /**
     * @var array Configuration options
     */
    private array $config;
    
    /**
     * @var float Request start time
     */
    private float $startTime;
    
    /**
     * @var array Resource type patterns for automatic detection
     */
    private const RESOURCE_PATTERNS = [
        'patient' => '/patients?/',
        'encounter' => '/encounters?/',
        'dot_test' => '/dot[-_]?tests?/',
        'osha' => '/osha/',
        'user' => '/users?/',
        'document' => '/documents?/',
        'report' => '/reports?/',
        'video' => '/video/',
        'auth' => '/auth/',
        'admin' => '/admin/',
    ];
    
    /**
     * @var array Operations that should always be logged
     */
    private const SENSITIVE_OPERATIONS = [
        'POST' => 'create',
        'PUT' => 'update',
        'PATCH' => 'update',
        'DELETE' => 'delete',
    ];
    
    /**
     * Constructor
     * 
     * @param array $config Configuration options
     */
    public function __construct(array $config = [])
    {
        $this->auditService = new AuditService();
        $this->config = array_merge([
            'log_reads' => true,           // Log GET requests
            'log_all_requests' => false,   // Log even non-sensitive routes
            'exclude_patterns' => [        // Paths to exclude from logging
                '/health',
                '/ping',
                '/status',
                '/favicon',
            ],
            'sensitive_paths' => [         // Paths that contain PHI
                '/patients',
                '/encounters',
                '/documents',
            ],
        ], $config);
        
        $this->startTime = microtime(true);
    }
    
    /**
     * Handle the request
     * 
     * This method wraps the actual request handler and logs the request/response.
     * 
     * @param callable $handler The request handler to wrap
     * @return mixed The handler's response
     */
    public function handle(callable $handler): mixed
    {
        $this->startTime = microtime(true);
        $response = null;
        $error = null;
        $success = true;
        
        try {
            // Execute the actual handler
            $response = $handler();
            
            // Check if response indicates failure
            if (is_array($response) && isset($response['success'])) {
                $success = (bool) $response['success'];
            }
            
        } catch (Exception $e) {
            $error = $e;
            $success = false;
            throw $e; // Re-throw to maintain normal error handling
            
        } finally {
            // Always log the request, even if it threw an exception
            $this->logRequest($response, $error, $success);
        }
        
        return $response;
    }
    
    /**
     * Process request before handler
     * 
     * Call this at the start of your API endpoint if not using the handle() wrapper.
     */
    public function before(): void
    {
        $this->startTime = microtime(true);
    }
    
    /**
     * Process request after handler
     * 
     * Call this at the end of your API endpoint if not using the handle() wrapper.
     * 
     * @param mixed $response The response from the handler
     * @param bool $success Whether the operation was successful
     * @param string|null $errorMessage Error message if operation failed
     */
    public function after(mixed $response = null, bool $success = true, ?string $errorMessage = null): void
    {
        $error = $errorMessage ? new Exception($errorMessage) : null;
        $this->logRequest($response, $error, $success);
    }
    
    /**
     * Log the API request
     * 
     * @param mixed $response The response data
     * @param Exception|null $error Any exception that occurred
     * @param bool $success Whether the operation succeeded
     */
    private function logRequest(mixed $response, ?Exception $error, bool $success): void
    {
        try {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            $path = parse_url($uri, PHP_URL_PATH) ?: '/';
            
            // Check if this path should be excluded
            if ($this->shouldExclude($path)) {
                return;
            }
            
            // Check if we should log this request type
            if ($method === 'GET' && !$this->config['log_reads'] && !$this->isSensitivePath($path)) {
                return;
            }
            
            // Determine action type from HTTP method
            $action = $this->getActionFromMethod($method);
            
            // Detect resource type from path
            $resourceType = $this->detectResourceType($path);
            
            // Extract resource ID from path if present
            $resourceId = $this->extractResourceId($path);
            
            // Detect patient ID if this is a patient-related request
            $patientId = $this->detectPatientId($path, $response);
            
            // Calculate duration
            $duration = microtime(true) - $this->startTime;
            
            // Build description
            $description = sprintf(
                '%s %s %s (%.3fs)',
                $method,
                $resourceType,
                $success ? 'completed' : 'failed',
                $duration
            );
            
            // Build metadata
            $metadata = [
                'request_method' => $method,
                'request_path' => $path,
                'duration_ms' => round($duration * 1000, 2),
                'success' => $success,
                'response_code' => http_response_code() ?: 200,
            ];
            
            if ($error) {
                $metadata['error_message'] = $error->getMessage();
                $metadata['error_code'] = $error->getCode();
            }
            
            // Add response summary (without sensitive data)
            if (is_array($response)) {
                $metadata['response_keys'] = array_keys($response);
                if (isset($response['data']) && is_array($response['data'])) {
                    $metadata['data_count'] = count($response['data']);
                }
            }
            
            // Log using the Auditable trait's internal method
            $this->logAuditEventInternal(
                action: $action,
                resourceType: $resourceType,
                resourceId: $resourceId ?? '',
                patientId: $patientId,
                success: $success,
                errorMessage: $error?->getMessage(),
                description: $description,
                metadata: $metadata
            );
            
        } catch (Exception $e) {
            // Don't let audit logging failures break the application
            error_log("[AuditMiddleware] Failed to log request: " . $e->getMessage());
        }
    }
    
    /**
     * Log an audit event (internal method to avoid naming conflict with trait)
     */
    private function logAuditEventInternal(
        string $action,
        string $resourceType,
        string $resourceId,
        ?string $patientId,
        bool $success,
        ?string $errorMessage,
        string $description,
        array $metadata
    ): void {
        $userContext = $this->getUserContext();
        
        // Merge user context with metadata
        $fullMetadata = array_merge($metadata, [
            'user_name' => $userContext['user_name'],
            'user_role' => $userContext['user_role'],
            'patient_id' => $patientId,
            'success' => $success,
            'error_message' => $errorMessage,
        ]);
        
        $this->auditService->audit(
            $action,
            $resourceType,
            $resourceId,
            $description,
            $fullMetadata
        );
    }
    
    /**
     * Check if path should be excluded from logging
     * 
     * @param string $path
     * @return bool
     */
    private function shouldExclude(string $path): bool
    {
        foreach ($this->config['exclude_patterns'] as $pattern) {
            if (str_contains($path, $pattern)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if path is sensitive (contains PHI)
     * 
     * @param string $path
     * @return bool
     */
    private function isSensitivePath(string $path): bool
    {
        foreach ($this->config['sensitive_paths'] as $pattern) {
            if (str_contains($path, $pattern)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get action type from HTTP method
     * 
     * @param string $method
     * @return string
     */
    private function getActionFromMethod(string $method): string
    {
        return match ($method) {
            'GET' => 'read',
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => strtolower($method),
        };
    }
    
    /**
     * Detect resource type from path
     * 
     * @param string $path
     * @return string
     */
    private function detectResourceType(string $path): string
    {
        foreach (self::RESOURCE_PATTERNS as $type => $pattern) {
            if (preg_match($pattern, $path)) {
                return $type;
            }
        }
        
        // Fall back to extracting from path
        $segments = array_filter(explode('/', $path));
        foreach ($segments as $segment) {
            // Skip version prefixes and 'api'
            if (preg_match('/^(api|v\d+)$/i', $segment)) {
                continue;
            }
            return strtolower($segment);
        }
        
        return 'api';
    }
    
    /**
     * Extract resource ID from path
     * 
     * @param string $path
     * @return string|null
     */
    private function extractResourceId(string $path): ?string
    {
        $segments = array_filter(explode('/', $path));
        $segments = array_values($segments);
        
        // Look for UUID patterns
        foreach ($segments as $segment) {
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $segment)) {
                return $segment;
            }
            // Also check for numeric IDs
            if (preg_match('/^\d+$/', $segment) && strlen($segment) > 0 && strlen($segment) < 20) {
                return $segment;
            }
        }
        
        return null;
    }
    
    /**
     * Detect patient ID from path or response
     * 
     * @param string $path
     * @param mixed $response
     * @return string|null
     */
    private function detectPatientId(string $path, mixed $response): ?string
    {
        // Check if this is a patient endpoint
        if (preg_match('/\/patients?\/([0-9a-f-]+)/i', $path, $matches)) {
            return $matches[1];
        }
        
        // Check query parameters
        $patientId = $_GET['patient_id'] ?? $_GET['patientId'] ?? null;
        if ($patientId) {
            return $patientId;
        }
        
        // Check response for patient_id
        if (is_array($response)) {
            if (isset($response['data']['patient_id'])) {
                return $response['data']['patient_id'];
            }
            if (isset($response['patient']['patient_id'])) {
                return $response['patient']['patient_id'];
            }
            if (isset($response['data']['patient']['patient_id'])) {
                return $response['data']['patient']['patient_id'];
            }
        }
        
        return null;
    }
    
    /**
     * Create middleware instance for a specific endpoint type
     * 
     * @param string $endpointType Type of endpoint (patient, encounter, etc.)
     * @return self
     */
    public static function forEndpoint(string $endpointType): self
    {
        $config = match ($endpointType) {
            'patient' => [
                'log_reads' => true,  // Always log patient access
                'sensitive_paths' => ['/patients'],
            ],
            'encounter' => [
                'log_reads' => true,
                'sensitive_paths' => ['/encounters'],
            ],
            'auth' => [
                'log_reads' => false,
                'log_all_requests' => true,
            ],
            'admin' => [
                'log_reads' => true,
                'log_all_requests' => true,
            ],
            default => [],
        };
        
        return new self($config);
    }
    
    /**
     * Static factory for wrapping a handler
     * 
     * Example usage:
     * ```php
     * return AuditMiddleware::wrap(function() {
     *     return handleRequest();
     * });
     * ```
     * 
     * @param callable $handler The request handler
     * @param array $config Optional configuration
     * @return mixed The handler's response
     */
    public static function wrap(callable $handler, array $config = []): mixed
    {
        $middleware = new self($config);
        return $middleware->handle($handler);
    }
    
    /**
     * Log a custom audit event through the middleware
     * 
     * Use this for custom logging that doesn't fit the automatic pattern.
     * 
     * @param string $action Action type (create, read, update, delete, etc.)
     * @param string $resourceType Resource type
     * @param string $resourceId Resource ID
     * @param string $description Description
     * @param array $metadata Additional metadata
     * @param string|null $patientId Patient ID for PHI tracking
     * @param bool $success Whether operation succeeded
     * @param string|null $errorMessage Error message if failed
     */
    public function logCustomEvent(
        string $action,
        string $resourceType,
        string $resourceId,
        string $description = '',
        array $metadata = [],
        ?string $patientId = null,
        bool $success = true,
        ?string $errorMessage = null
    ): void {
        $this->logAuditEventInternal(
            action: $action,
            resourceType: $resourceType,
            resourceId: $resourceId,
            patientId: $patientId,
            success: $success,
            errorMessage: $errorMessage,
            description: $description,
            metadata: $metadata
        );
    }
}
