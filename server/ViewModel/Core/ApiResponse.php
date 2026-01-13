<?php
/**
 * Standardized API Response Class
 * 
 * Provides standardized JSON-compatible response formatting for API endpoints.
 * All methods return arrays that can be json_encode() for API responses.
 * 
 * Response Structure:
 * - success: true/false
 * - message: Human-readable message
 * - data: Response payload (on success)
 * - error_code: Machine-readable error code (on error)
 * - errors: Detailed error information (on error)
 * - timestamp: ISO 8601 timestamp
 * - request_id: Unique request identifier for tracing
 * 
 * @package SafeShift\ViewModel\Core
 */

namespace ViewModel\Core;

class ApiResponse
{
    /**
     * Create a successful response
     * 
     * @param mixed $data Response data
     * @param string $message Success message
     * @return array
     */
    public static function success($data = null, string $message = 'Success'): array
    {
        return [
            'success' => true,
            'status' => 200,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c'),
            'request_id' => self::getRequestId()
        ];
    }
    
    /**
     * Create an error response
     *
     * @param string $message Error message
     * @param array|int $errorsOrStatus Detailed error information OR HTTP status code for backwards compatibility
     * @param string|array|null $errorCodeOrErrors Machine-readable error code OR errors array when second param is status
     * @return array
     */
    public static function error(string $message, array|int $errorsOrStatus = [], string|array|null $errorCodeOrErrors = null): array
    {
        // Handle backwards compatibility: error('msg', 500) or error('msg', 422, $errors)
        if (is_int($errorsOrStatus)) {
            $statusCode = $errorsOrStatus;
            $errors = is_array($errorCodeOrErrors) ? $errorCodeOrErrors : [];
            $errorCode = is_string($errorCodeOrErrors) ? $errorCodeOrErrors : self::getDefaultErrorCode($statusCode);
            
            return [
                'success' => false,
                'message' => $message,
                'error_code' => $errorCode,
                'errors' => $errors,
                'status' => $statusCode,
                'timestamp' => date('c'),
                'request_id' => self::getRequestId()
            ];
        }
        
        // Standard call: error('msg', $errors, 'ERROR_CODE')
        return [
            'success' => false,
            'message' => $message,
            'error_code' => (is_string($errorCodeOrErrors) ? $errorCodeOrErrors : null) ?? 'BAD_REQUEST',
            'errors' => $errorsOrStatus,
            'timestamp' => date('c'),
            'request_id' => self::getRequestId()
        ];
    }
    
    /**
     * Create an unauthorized response (401)
     * 
     * @param string $message Error message
     * @return array
     */
    public static function unauthorized(string $message = 'Not authenticated'): array
    {
        return [
            'success' => false,
            'status' => 401,
            'message' => $message,
            'error' => $message,
            'error_code' => 'UNAUTHORIZED',
            'errors' => ['authentication' => [$message]],
            'timestamp' => date('c'),
            'request_id' => self::getRequestId()
        ];
    }
    
    /**
     * Create a forbidden response (403)
     * 
     * @param string $message Error message
     * @return array
     */
    public static function forbidden(string $message = 'Access denied'): array
    {
        return [
            'success' => false,
            'status' => 403,
            'message' => $message,
            'error' => $message,
            'error_code' => 'FORBIDDEN',
            'errors' => ['authorization' => [$message]],
            'timestamp' => date('c'),
            'request_id' => self::getRequestId()
        ];
    }
    
    /**
     * Create a not found response (404)
     * 
     * @param string $message Error message
     * @return array
     */
    public static function notFound(string $message = 'Resource not found'): array
    {
        return [
            'success' => false,
            'status' => 404,
            'message' => $message,
            'error' => $message,
            'error_code' => 'NOT_FOUND',
            'errors' => ['resource' => [$message]],
            'timestamp' => date('c'),
            'request_id' => self::getRequestId()
        ];
    }
    
    /**
     * Create a validation error response (422)
     * 
     * @param array $errors Validation errors keyed by field name
     * @param string $message Error message
     * @return array
     */
    public static function validationError(array $errors, string $message = 'Validation failed'): array
    {
        return [
            'success' => false,
            'status' => 422,
            'message' => $message,
            'error' => $message,
            'error_code' => 'VALIDATION_ERROR',
            'errors' => $errors,
            'timestamp' => date('c'),
            'request_id' => self::getRequestId()
        ];
    }
    
    /**
     * Create a server error response (500)
     * 
     * @param string $message Error message
     * @param \Exception|\Throwable|null $e Exception to log (not exposed to client)
     * @return array
     */
    public static function serverError(string $message = 'Internal server error', $e = null): array
    {
        // Log the actual error (don't expose to client)
        if ($e) {
            error_log(sprintf(
                'API Server Error: %s in %s:%d',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
        }
        
        return [
            'success' => false,
            'status' => 500,
            'message' => $message,
            'error' => $message,
            'error_code' => 'SERVER_ERROR',
            'errors' => ['server' => [$message]],
            'timestamp' => date('c'),
            'request_id' => self::getRequestId()
        ];
    }
    
    /**
     * Create a rate limit exceeded response (429)
     * 
     * @param string $message Error message
     * @param int $retryAfter Seconds until retry is allowed
     * @return array
     */
    public static function rateLimited(string $message = 'Too many requests', int $retryAfter = 60): array
    {
        return [
            'success' => false,
            'status' => 429,
            'message' => $message,
            'error' => $message,
            'error_code' => 'RATE_LIMITED',
            'errors' => ['rate_limit' => [$message]],
            'retry_after' => $retryAfter,
            'timestamp' => date('c'),
            'request_id' => self::getRequestId()
        ];
    }
    
    /**
     * Create a bad request response (400)
     * 
     * @param string $message Error message
     * @param array $errors Detailed error information
     * @return array
     */
    public static function badRequest(string $message = 'Bad request', array $errors = []): array
    {
        return [
            'success' => false,
            'status' => 400,
            'message' => $message,
            'error' => $message,
            'error_code' => 'BAD_REQUEST',
            'errors' => $errors,
            'timestamp' => date('c'),
            'request_id' => self::getRequestId()
        ];
    }
    
    /**
     * Create a conflict response (409)
     * 
     * @param string $message Error message
     * @param array $errors Detailed error information
     * @return array
     */
    public static function conflict(string $message = 'Resource conflict', array $errors = []): array
    {
        return [
            'success' => false,
            'status' => 409,
            'message' => $message,
            'error' => $message,
            'error_code' => 'CONFLICT',
            'errors' => $errors,
            'timestamp' => date('c'),
            'request_id' => self::getRequestId()
        ];
    }
    
    /**
     * Create a method not allowed response (405)
     * 
     * @param string $message Error message
     * @param array $allowedMethods List of allowed HTTP methods
     * @return array
     */
    public static function methodNotAllowed(string $message = 'Method not allowed', array $allowedMethods = []): array
    {
        return [
            'success' => false,
            'status' => 405,
            'message' => $message,
            'error' => $message,
            'error_code' => 'METHOD_NOT_ALLOWED',
            'errors' => ['method' => [$message]],
            'allowed_methods' => $allowedMethods,
            'timestamp' => date('c'),
            'request_id' => self::getRequestId()
        ];
    }
    
    /**
     * Create a service unavailable response (503)
     * 
     * @param string $message Error message
     * @param int|null $retryAfter Seconds until service may be available
     * @return array
     */
    public static function serviceUnavailable(string $message = 'Service temporarily unavailable', ?int $retryAfter = null): array
    {
        $response = [
            'success' => false,
            'status' => 503,
            'message' => $message,
            'error' => $message,
            'error_code' => 'SERVICE_UNAVAILABLE',
            'errors' => ['service' => [$message]],
            'timestamp' => date('c'),
            'request_id' => self::getRequestId()
        ];
        
        if ($retryAfter !== null) {
            $response['retry_after'] = $retryAfter;
        }
        
        return $response;
    }
    
    /**
     * Send JSON response with appropriate HTTP status code
     * 
     * @param array $response Response array
     * @param int $statusCode HTTP status code
     * @return void
     */
    public static function send(array $response, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        // Add request ID header for tracing
        $requestId = $response['request_id'] ?? self::getRequestId();
        header('X-Request-ID: ' . $requestId);
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Convenience method to send success response
     * 
     * @param mixed $data Response data
     * @param string $message Success message
     * @param int $statusCode HTTP status code (default 200)
     * @return void
     */
    public static function sendSuccess($data = null, string $message = '', int $statusCode = 200): void
    {
        self::send(self::success($data, $message), $statusCode);
    }
    
    /**
     * Convenience method to send error response
     * 
     * @param string $message Error message
     * @param array $errors Detailed errors
     * @param int $statusCode HTTP status code (default 400)
     * @return void
     */
    public static function sendError(string $message, array $errors = [], int $statusCode = 400): void
    {
        self::send(self::error($message, $errors), $statusCode);
    }
    
    /**
     * Get or generate request ID for tracing
     * 
     * @return string
     */
    private static function getRequestId(): string
    {
        if (!isset($_SERVER['HTTP_X_REQUEST_ID'])) {
            $_SERVER['HTTP_X_REQUEST_ID'] = bin2hex(random_bytes(8));
        }
        return $_SERVER['HTTP_X_REQUEST_ID'];
    }
    
    /**
     * Get default error code from HTTP status code
     * 
     * @param int $statusCode HTTP status code
     * @return string
     */
    public static function getDefaultErrorCode(int $statusCode): string
    {
        $codes = [
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            409 => 'CONFLICT',
            422 => 'VALIDATION_ERROR',
            429 => 'RATE_LIMITED',
            500 => 'SERVER_ERROR',
            503 => 'SERVICE_UNAVAILABLE'
        ];
        return $codes[$statusCode] ?? 'ERROR';
    }
}
