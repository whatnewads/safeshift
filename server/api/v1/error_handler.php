<?php
/**
 * Global API Error Handler
 * 
 * Include at the start of all API endpoints for consistent error handling.
 * This handler:
 * - Catches uncaught exceptions and converts them to JSON responses
 * - Converts PHP errors to exceptions
 * - Ensures all API responses are valid JSON
 * - Logs errors with context for debugging
 * - Sanitizes error messages before exposing to clients
 * 
 * Usage:
 * Include at the start of API endpoint files:
 * require_once __DIR__ . '/error_handler.php';
 * 
 * @package SafeShift\API\v1
 */

declare(strict_types=1);

// Ensure ApiResponse is available
require_once dirname(__DIR__, 2) . '/ViewModel/Core/ApiResponse.php';

use ViewModel\Core\ApiResponse;

/**
 * Custom exception handler for API endpoints
 * Catches all uncaught exceptions and returns consistent JSON response
 */
set_exception_handler(function (Throwable $e) {
    // Generate request ID for tracing
    $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? bin2hex(random_bytes(8));
    
    // Log the error with full context (not exposed to client)
    error_log(sprintf(
        "[API Error] %s: %s in %s:%d\nRequest ID: %s\nRequest URI: %s\nRequest Method: %s\nStack trace:\n%s",
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $requestId,
        $_SERVER['REQUEST_URI'] ?? 'unknown',
        $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        $e->getTraceAsString()
    ));
    
    // Determine appropriate status code
    $statusCode = 500;
    $errorCode = 'SERVER_ERROR';
    $message = 'An unexpected error occurred';
    
    // Check for specific exception types
    if ($e instanceof \PDOException) {
        $statusCode = 503;
        $errorCode = 'DATABASE_ERROR';
        $message = 'A database error occurred';
    } elseif ($e instanceof \InvalidArgumentException) {
        $statusCode = 400;
        $errorCode = 'BAD_REQUEST';
        $message = 'Invalid request parameters';
    } elseif ($e instanceof \DomainException) {
        $statusCode = 422;
        $errorCode = 'VALIDATION_ERROR';
        $message = 'Business rule violation';
    }
    
    // Don't expose internal errors to client
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Request-ID: ' . $requestId);
    
    echo json_encode([
        'success' => false,
        'message' => $message,
        'error_code' => $errorCode,
        'errors' => [],
        'timestamp' => date('c'),
        'request_id' => $requestId
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    exit(1);
});

/**
 * Convert PHP errors to ErrorException for consistent handling
 * This ensures warnings and notices don't silently fail
 */
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    // Don't throw for @ suppressed errors
    if (!(error_reporting() & $severity)) {
        return false;
    }
    
    // Convert to exception
    throw new ErrorException($message, 0, $severity, $file, $line);
});

/**
 * Register shutdown function to catch fatal errors
 */
register_shutdown_function(function () {
    $error = error_get_last();
    
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Generate request ID
        $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? bin2hex(random_bytes(8));
        
        // Log the fatal error
        error_log(sprintf(
            "[API Fatal Error] %s in %s:%d\nRequest ID: %s",
            $error['message'],
            $error['file'],
            $error['line'],
            $requestId
        ));
        
        // Clear any output that may have been generated
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Send error response
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Request-ID: ' . $requestId);
        
        echo json_encode([
            'success' => false,
            'message' => 'A critical error occurred',
            'error_code' => 'CRITICAL_ERROR',
            'errors' => [],
            'timestamp' => date('c'),
            'request_id' => $requestId
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
});

/**
 * Ensure JSON content type for all API responses
 * This can be called early to set the default content type
 */
function ensureJsonContentType(): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
}

/**
 * Validate that the request has valid JSON body
 * Returns decoded data or null on failure
 * 
 * @param bool $required Whether body is required
 * @return array|null Decoded JSON data
 */
function validateJsonBody(bool $required = false): ?array
{
    $rawInput = file_get_contents('php://input');
    
    if (empty($rawInput)) {
        if ($required) {
            ApiResponse::send(ApiResponse::badRequest('Request body is required'), 400);
        }
        return null;
    }
    
    $data = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        ApiResponse::send(ApiResponse::badRequest('Invalid JSON in request body'), 400);
    }
    
    return $data;
}

/**
 * Validate required fields in request data
 * 
 * @param array $data Request data
 * @param array $requiredFields List of required field names
 * @return array Validation errors (empty if valid)
 */
function validateRequiredFields(array $data, array $requiredFields): array
{
    $errors = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            $errors[$field] = [ucfirst(str_replace('_', ' ', $field)) . ' is required'];
        }
    }
    
    return $errors;
}

/**
 * Check if request method matches expected method(s)
 * 
 * @param string|array $allowedMethods Allowed HTTP method(s)
 * @return bool True if method is allowed
 */
function checkRequestMethod($allowedMethods): bool
{
    $allowedMethods = (array) $allowedMethods;
    $currentMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    if (!in_array($currentMethod, $allowedMethods)) {
        ApiResponse::send(
            ApiResponse::methodNotAllowed('Method not allowed', $allowedMethods),
            405
        );
        return false;
    }
    
    return true;
}

/**
 * Check if user is authenticated via session
 * 
 * @return int|null User ID if authenticated, null otherwise
 */
function requireAuthentication(): ?int
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['user_id'])) {
        ApiResponse::send(ApiResponse::unauthorized('Authentication required'), 401);
        return null;
    }
    
    return (int) $_SESSION['user']['user_id'];
}

/**
 * Handle CORS preflight and headers for API requests
 * 
 * @param array $additionalMethods Additional HTTP methods to allow
 * @return bool True if this is a preflight request (caller should exit)
 */
function handleCors(array $additionalMethods = []): bool
{
    $allowedOrigins = [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:3000',
        'http://127.0.0.1:3000'
    ];
    
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                     . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    
    if ($origin === $allowedOrigin || in_array($origin, $allowedOrigins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-XSRF-Token, Authorization, X-Request-ID');
        
        $methods = array_merge(['GET', 'POST', 'OPTIONS'], $additionalMethods);
        header('Access-Control-Allow-Methods: ' . implode(', ', array_unique($methods)));
    }
    
    // Handle preflight request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
    
    return false;
}

// Set JSON content type by default
ensureJsonContentType();
