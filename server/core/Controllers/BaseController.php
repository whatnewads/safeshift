<?php
/**
 * Base Controller Class
 * 
 * Provides common functionality for all controllers
 */

namespace App\Controllers;

use Core\Session;
use Core\ApiResponse;
use Core\Services\AuthService;
use Core\Services\AuditService;
use Core\Logger;

abstract class BaseController
{
    protected AuthService $authService;
    protected AuditService $auditService;
    protected Logger $logger;
    
    public function __construct()
    {
        $this->authService = new AuthService();
        $this->auditService = new AuditService();
        $this->logger = new Logger();
        
        // Initialize session
        Session::init();
    }
    
    /**
     * Get request method
     * 
     * @return string
     */
    protected function getRequestMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }
    
    /**
     * Check if request method matches
     * 
     * @param string|array $methods
     * @return bool
     */
    protected function isMethod($methods): bool
    {
        $currentMethod = $this->getRequestMethod();
        $methods = is_array($methods) ? $methods : [$methods];
        
        return in_array($currentMethod, array_map('strtoupper', $methods));
    }
    
    /**
     * Require specific HTTP method(s)
     * 
     * @param string|array $methods
     * @return void
     */
    protected function requireMethod($methods): void
    {
        if (!$this->isMethod($methods)) {
            $allowed = is_array($methods) ? $methods : [$methods];
            ApiResponse::methodNotAllowed($allowed);
        }
    }
    
    /**
     * Get request input
     * 
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    protected function input(?string $key = null, $default = null)
    {
        // Get input based on content type
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
        } elseif ($this->isMethod('POST')) {
            $input = $_POST;
        } else {
            $input = $_GET;
        }
        
        // Merge with GET parameters for all requests
        $input = array_merge($_GET, $input);
        
        if ($key === null) {
            return $input;
        }
        
        return $input[$key] ?? $default;
    }
    
    /**
     * Get all request inputs
     * 
     * @return array
     */
    protected function all(): array
    {
        return $this->input();
    }
    
    /**
     * Get only specified inputs
     * 
     * @param array $keys
     * @return array
     */
    protected function only(array $keys): array
    {
        $input = $this->all();
        return array_intersect_key($input, array_flip($keys));
    }
    
    /**
     * Get all except specified inputs
     * 
     * @param array $keys
     * @return array
     */
    protected function except(array $keys): array
    {
        $input = $this->all();
        return array_diff_key($input, array_flip($keys));
    }
    
    /**
     * Check if input exists
     * 
     * @param string $key
     * @return bool
     */
    protected function has(string $key): bool
    {
        $input = $this->all();
        return isset($input[$key]);
    }
    
    /**
     * Check if input is filled (not empty)
     * 
     * @param string $key
     * @return bool
     */
    protected function filled(string $key): bool
    {
        $value = $this->input($key);
        return $value !== null && $value !== '';
    }
    
    /**
     * Get uploaded file
     * 
     * @param string $key
     * @return array|null
     */
    protected function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }
    
    /**
     * Check if file was uploaded
     * 
     * @param string $key
     * @return bool
     */
    protected function hasFile(string $key): bool
    {
        $file = $this->file($key);
        return $file && $file['error'] === UPLOAD_ERR_OK;
    }
    
    /**
     * Get request header
     * 
     * @param string $header
     * @param mixed $default
     * @return mixed
     */
    protected function header(string $header, $default = null)
    {
        $header = 'HTTP_' . strtoupper(str_replace('-', '_', $header));
        return $_SERVER[$header] ?? $default;
    }
    
    /**
     * Get bearer token from Authorization header
     * 
     * @return string|null
     */
    protected function bearerToken(): ?string
    {
        $auth = $this->header('Authorization');
        if ($auth && preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    /**
     * Validate CSRF token
     * 
     * @return bool
     */
    protected function validateCsrf(): bool
    {
        // Skip CSRF for GET requests
        if ($this->isMethod('GET')) {
            return true;
        }
        
        $token = $this->input('csrf_token') ?? $this->header('X-CSRF-Token');
        return $this->authService->validateCsrfToken($token ?? '');
    }
    
    /**
     * Require CSRF token validation
     * 
     * @return void
     */
    protected function requireCsrf(): void
    {
        if (!$this->validateCsrf()) {
            ApiResponse::forbidden('Invalid CSRF token');
        }
    }
    
    /**
     * Require authentication
     * 
     * @return array User data
     */
    protected function requireAuth(): array
    {
        if (!$this->authService->isLoggedIn()) {
            ApiResponse::unauthorized('Authentication required');
        }
        
        $user = $this->authService->getCurrentUser();
        if (!$user) {
            ApiResponse::unauthorized('Invalid session');
        }
        
        return $user;
    }
    
    /**
     * Require specific role(s)
     * 
     * @param string|array $roles
     * @return array User data
     */
    protected function requireRole($roles): array
    {
        $user = $this->requireAuth();
        $roles = is_array($roles) ? $roles : [$roles];
        
        $hasRole = false;
        foreach ($roles as $role) {
            if ($this->authService->hasRole($role, $user['user_id'])) {
                $hasRole = true;
                break;
            }
        }
        
        if (!$hasRole) {
            ApiResponse::forbidden('Insufficient permissions');
        }
        
        return $user;
    }
    
    /**
     * Get client IP address
     * 
     * @return string
     */
    protected function getClientIp(): string
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Get user agent
     * 
     * @return string
     */
    protected function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }
    
    /**
     * Log controller action
     * 
     * @param string $action
     * @param array $data
     */
    protected function log(string $action, array $data = []): void
    {
        $this->logger->info('controller', [
            'controller' => static::class,
            'action' => $action,
            'method' => $this->getRequestMethod(),
            'ip' => $this->getClientIp(),
            'data' => $data
        ]);
    }
    
    /**
     * Log error
     * 
     * @param string $message
     * @param array $context
     */
    protected function logError(string $message, array $context = []): void
    {
        $this->logger->error('controller_error', [
            'controller' => static::class,
            'message' => $message,
            'method' => $this->getRequestMethod(),
            'ip' => $this->getClientIp(),
            'context' => $context
        ]);
    }
    
    /**
     * Sanitize string input
     * 
     * @param mixed $value
     * @return string
     */
    protected function sanitizeString($value): string
    {
        return htmlspecialchars(strip_tags(trim((string)$value)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Sanitize array of strings
     * 
     * @param array $values
     * @return array
     */
    protected function sanitizeArray(array $values): array
    {
        return array_map([$this, 'sanitizeString'], $values);
    }
    
    /**
     * Validate required fields
     * 
     * @param array $fields
     * @return array Validation errors
     */
    protected function validateRequired(array $fields): array
    {
        $errors = [];
        $input = $this->all();
        
        foreach ($fields as $field) {
            if (!isset($input[$field]) || trim($input[$field]) === '') {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate email
     * 
     * @param string $email
     * @return bool
     */
    protected function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate and get integer input
     * 
     * @param string $key
     * @param int $default
     * @param int|null $min
     * @param int|null $max
     * @return int
     */
    protected function intInput(string $key, int $default = 0, ?int $min = null, ?int $max = null): int
    {
        $value = (int) $this->input($key, $default);
        
        if ($min !== null && $value < $min) {
            $value = $min;
        }
        
        if ($max !== null && $value > $max) {
            $value = $max;
        }
        
        return $value;
    }
    
    /**
     * Get pagination parameters
     * 
     * @return array ['page' => int, 'per_page' => int, 'offset' => int]
     */
    protected function getPaginationParams(): array
    {
        $page = $this->intInput('page', 1, 1);
        $perPage = $this->intInput('per_page', 20, 1, 100);
        $offset = ($page - 1) * $perPage;
        
        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => $offset
        ];
    }
}