<?php
/**
 * ErrorHandler.php - Centralized Error/Exception Handler for SafeShift EHR
 * 
 * Provides centralized error and exception handling with features:
 * - HIPAA-compliant logging (no PHI in logs)
 * - Safe error responses (no internal info leak)
 * - Development vs production mode handling
 * - JSON error responses for API requests
 * 
 * @package    SafeShift\Model\Core
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace Model\Core;

use Model\Config\AppConfig;

/**
 * Centralized error handler
 * 
 * Implements HIPAA-compliant error handling with different
 * behaviors for development and production environments.
 */
final class ErrorHandler
{
    /** @var AppConfig Configuration instance */
    private AppConfig $config;
    
    /** @var string Log directory path */
    private string $logPath;
    
    /** @var bool Whether handler is registered */
    private bool $registered = false;
    
    /** @var callable|null Previous error handler */
    private $previousErrorHandler = null;
    
    /** @var callable|null Previous exception handler */
    private $previousExceptionHandler = null;
    
    /** @var self|null Singleton instance */
    private static ?self $instance = null;

    /** @var array<string> Patterns indicating PHI data */
    private const PHI_PATTERNS = [
        '/\b\d{3}-\d{2}-\d{4}\b/',           // SSN pattern
        '/\b\d{9}\b/',                         // Unformatted SSN
        '/\b\d{2}\/\d{2}\/\d{4}\b/',          // DOB pattern MM/DD/YYYY
        '/\b\d{4}-\d{2}-\d{2}\b/',            // DOB pattern YYYY-MM-DD
        '/patient[_\s]?(?:name|id|uuid)/i',   // Patient identifiers
        '/\b[A-Z]{2}\d{6,10}\b/',             // Medical record numbers
    ];

    /** @var array<string> Keys that might contain sensitive data */
    private const SENSITIVE_KEYS = [
        'password', 'passwd', 'secret', 'token', 'api_key', 'apikey',
        'ssn', 'social_security', 'dob', 'date_of_birth', 'birthdate',
        'credit_card', 'card_number', 'cvv', 'pin',
        'patient_name', 'patient_id', 'mrn', 'medical_record',
    ];

    /**
     * Private constructor for singleton pattern
     */
    private function __construct()
    {
        $this->config = AppConfig::getInstance();
        $this->logPath = $this->getLogPath();
    }

    /**
     * Get singleton instance
     * 
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register error and exception handlers
     * 
     * @return self
     */
    public function register(): self
    {
        if ($this->registered) {
            return $this;
        }

        // Store previous handlers
        $this->previousErrorHandler = set_error_handler([$this, 'handleError']);
        $this->previousExceptionHandler = set_exception_handler([$this, 'handleException']);
        
        // Register shutdown handler for fatal errors
        register_shutdown_function([$this, 'handleShutdown']);

        $this->registered = true;
        return $this;
    }

    /**
     * Unregister handlers and restore previous ones
     * 
     * @return self
     */
    public function unregister(): self
    {
        if (!$this->registered) {
            return $this;
        }

        restore_error_handler();
        restore_exception_handler();

        $this->registered = false;
        return $this;
    }

    /**
     * Handle PHP errors
     * 
     * @param int $severity Error severity level
     * @param string $message Error message
     * @param string $file File where error occurred
     * @param int $line Line number
     * @return bool True to prevent default PHP error handler
     */
    public function handleError(
        int $severity,
        string $message,
        string $file,
        int $line
    ): bool {
        // Respect error_reporting setting
        if (!(error_reporting() & $severity)) {
            return false;
        }

        // Convert to ErrorException for consistent handling
        $exception = new \ErrorException(
            $message,
            0,
            $severity,
            $file,
            $line
        );

        $this->handleException($exception);
        
        return true;
    }

    /**
     * Handle uncaught exceptions
     * 
     * @param \Throwable $exception The exception
     */
    public function handleException(\Throwable $exception): void
    {
        // Log the error (sanitized)
        $this->logException($exception);

        // Send appropriate response
        if ($this->isApiRequest()) {
            $this->sendJsonResponse($exception);
        } else {
            $this->sendHtmlResponse($exception);
        }
    }

    /**
     * Handle fatal errors on shutdown
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $exception = new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );
            
            $this->handleException($exception);
        }
    }

    /**
     * Log exception with HIPAA compliance
     * 
     * @param \Throwable $exception The exception
     */
    private function logException(\Throwable $exception): void
    {
        $logEntry = $this->formatLogEntry($exception);
        
        // Write to error log file
        $logFile = $this->logPath . '/error_' . date('Y-m-d') . '.log';
        
        if (!file_exists($this->logPath)) {
            mkdir($this->logPath, 0750, true);
        }
        
        file_put_contents(
            $logFile,
            $logEntry . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );

        // Also log to PHP error log
        error_log($this->sanitizeMessage($exception->getMessage()));
    }

    /**
     * Format log entry
     * 
     * @param \Throwable $exception The exception
     * @return string Formatted log entry
     */
    private function formatLogEntry(\Throwable $exception): string
    {
        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $this->getErrorLevel($exception),
            'message' => $this->sanitizeMessage($exception->getMessage()),
            'code' => $exception->getCode(),
            'file' => $this->sanitizePath($exception->getFile()),
            'line' => $exception->getLine(),
            'request_uri' => $this->sanitizeUri($_SERVER['REQUEST_URI'] ?? ''),
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];

        // Include stack trace in debug mode (sanitized)
        if ($this->config->isDebug()) {
            $entry['trace'] = $this->sanitizeStackTrace($exception->getTrace());
        }

        return json_encode($entry, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get error level string
     * 
     * @param \Throwable $exception The exception
     * @return string Error level
     */
    private function getErrorLevel(\Throwable $exception): string
    {
        if ($exception instanceof \ErrorException) {
            return match ($exception->getSeverity()) {
                E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR => 'ERROR',
                E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING => 'WARNING',
                E_NOTICE, E_USER_NOTICE => 'NOTICE',
                E_DEPRECATED, E_USER_DEPRECATED => 'DEPRECATED',
                default => 'ERROR',
            };
        }

        return 'EXCEPTION';
    }

    /**
     * Sanitize message to remove PHI
     * 
     * @param string $message Original message
     * @return string Sanitized message
     */
    private function sanitizeMessage(string $message): string
    {
        // Remove potential PHI patterns
        foreach (self::PHI_PATTERNS as $pattern) {
            $message = preg_replace($pattern, '[REDACTED]', $message);
        }

        // Remove database credentials from error messages
        $message = preg_replace(
            '/using password: (YES|NO)/i',
            'using password: [REDACTED]',
            $message
        );

        $message = preg_replace(
            '/(host|user|password|database)[=:][\'""]?[^\s\'"]+[\'""]?/i',
            '$1=[REDACTED]',
            $message
        );

        return $message;
    }

    /**
     * Sanitize file path to remove sensitive paths
     * 
     * @param string $path Original path
     * @return string Sanitized path
     */
    private function sanitizePath(string $path): string
    {
        // Remove absolute path prefix, keep relative path
        $basePath = dirname(__DIR__, 3);
        return str_replace($basePath, '[ROOT]', $path);
    }

    /**
     * Sanitize URI to remove sensitive query parameters
     * 
     * @param string $uri Original URI
     * @return string Sanitized URI
     */
    private function sanitizeUri(string $uri): string
    {
        $parsed = parse_url($uri);
        
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $params);
            
            foreach (self::SENSITIVE_KEYS as $key) {
                if (isset($params[$key])) {
                    $params[$key] = '[REDACTED]';
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
     * Sanitize stack trace
     * 
     * @param array<int, array<string, mixed>> $trace Original trace
     * @return array<int, array<string, mixed>> Sanitized trace
     */
    private function sanitizeStackTrace(array $trace): array
    {
        $sanitized = [];
        
        foreach ($trace as $frame) {
            $sanitizedFrame = [
                'file' => isset($frame['file']) ? $this->sanitizePath($frame['file']) : '[internal]',
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? '',
                'class' => $frame['class'] ?? '',
            ];
            
            // Sanitize arguments (remove sensitive data)
            if (isset($frame['args'])) {
                $sanitizedFrame['args'] = $this->sanitizeArguments($frame['args']);
            }
            
            $sanitized[] = $sanitizedFrame;
        }
        
        return $sanitized;
    }

    /**
     * Sanitize function arguments
     * 
     * @param array<int, mixed> $args Original arguments
     * @return array<int, mixed> Sanitized arguments
     */
    private function sanitizeArguments(array $args): array
    {
        $sanitized = [];
        
        foreach ($args as $arg) {
            if (is_string($arg)) {
                $sanitized[] = $this->sanitizeMessage($arg);
            } elseif (is_array($arg)) {
                $sanitized[] = $this->sanitizeArrayData($arg);
            } elseif (is_object($arg)) {
                $sanitized[] = '[Object: ' . get_class($arg) . ']';
            } else {
                $sanitized[] = gettype($arg);
            }
        }
        
        return $sanitized;
    }

    /**
     * Sanitize array data
     * 
     * @param array<string, mixed> $data Original data
     * @return array<string, mixed> Sanitized data
     */
    private function sanitizeArrayData(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            $lowercaseKey = strtolower((string) $key);
            
            // Check if key contains sensitive identifier
            $isSensitive = false;
            foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
                if (str_contains($lowercaseKey, $sensitiveKey)) {
                    $isSensitive = true;
                    break;
                }
            }
            
            if ($isSensitive) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArrayData($value);
            } elseif (is_string($value)) {
                $sanitized[$key] = $this->sanitizeMessage($value);
            } elseif (is_object($value)) {
                $sanitized[$key] = '[Object]';
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Check if current request is API request
     * 
     * @return bool
     */
    private function isApiRequest(): bool
    {
        // Check Accept header
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        // Check Content-Type header
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            return true;
        }

        // Check if URI starts with /api/
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (preg_match('#^/api/#', $uri)) {
            return true;
        }

        // Check for XMLHttpRequest
        $xRequestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        if (strtolower($xRequestedWith) === 'xmlhttprequest') {
            return true;
        }

        return false;
    }

    /**
     * Send JSON error response
     * 
     * @param \Throwable $exception The exception
     */
    private function sendJsonResponse(\Throwable $exception): void
    {
        if (!headers_sent()) {
            http_response_code($this->getHttpStatusCode($exception));
            header('Content-Type: application/json; charset=utf-8');
        }

        $response = [
            'success' => false,
            'error' => [
                'message' => $this->getUserFriendlyMessage($exception),
                'code' => $this->getErrorCode($exception),
            ],
        ];

        // Include details in debug mode
        if ($this->config->isDebug()) {
            $response['error']['debug'] = [
                'exception' => get_class($exception),
                'file' => $this->sanitizePath($exception->getFile()),
                'line' => $exception->getLine(),
                'trace' => $this->sanitizeStackTrace($exception->getTrace()),
            ];
        }

        echo json_encode($response, JSON_PRETTY_PRINT);
    }

    /**
     * Send HTML error response
     * 
     * @param \Throwable $exception The exception
     */
    private function sendHtmlResponse(\Throwable $exception): void
    {
        if (!headers_sent()) {
            http_response_code($this->getHttpStatusCode($exception));
            header('Content-Type: text/html; charset=utf-8');
        }

        if ($this->config->isDebug()) {
            $this->renderDebugPage($exception);
        } else {
            $this->renderProductionPage($exception);
        }
    }

    /**
     * Render debug error page
     * 
     * @param \Throwable $exception The exception
     */
    private function renderDebugPage(\Throwable $exception): void
    {
        $message = htmlspecialchars($this->sanitizeMessage($exception->getMessage()));
        $file = htmlspecialchars($this->sanitizePath($exception->getFile()));
        $line = $exception->getLine();
        $trace = htmlspecialchars(print_r($this->sanitizeStackTrace($exception->getTrace()), true));
        
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - SafeShift EHR</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #c0392b; margin-top: 0; }
        .error-message { background: #fef2f2; border: 1px solid #fecaca; padding: 15px; border-radius: 4px; margin: 20px 0; }
        .location { background: #fefce8; border: 1px solid #fef08a; padding: 15px; border-radius: 4px; margin: 20px 0; }
        .trace { background: #f3f4f6; padding: 15px; border-radius: 4px; overflow-x: auto; font-family: monospace; font-size: 13px; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; padding: 10px; border-radius: 4px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚠️ Application Error</h1>
        <div class="error-message">
            <strong>Message:</strong> {$message}
        </div>
        <div class="location">
            <strong>Location:</strong> {$file}:{$line}
        </div>
        <h3>Stack Trace</h3>
        <pre class="trace">{$trace}</pre>
        <div class="warning">
            <strong>Note:</strong> This debug information is only shown in development mode. In production, a generic error page will be displayed.
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Render production error page
     * 
     * @param \Throwable $exception The exception
     */
    private function renderProductionPage(\Throwable $exception): void
    {
        $statusCode = $this->getHttpStatusCode($exception);
        
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - SafeShift EHR</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 0; background: #f5f5f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .container { text-align: center; padding: 40px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 500px; }
        h1 { color: #333; margin-top: 0; }
        p { color: #666; }
        .error-code { font-size: 72px; font-weight: bold; color: #e74c3c; margin: 0; }
        a { color: #3498db; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <p class="error-code">{$statusCode}</p>
        <h1>Something went wrong</h1>
        <p>We apologize for the inconvenience. Our team has been notified and is working to resolve the issue.</p>
        <p><a href="/">Return to Home</a></p>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Get HTTP status code for exception
     * 
     * @param \Throwable $exception The exception
     * @return int HTTP status code
     */
    private function getHttpStatusCode(\Throwable $exception): int
    {
        $code = $exception->getCode();
        
        // If exception has a valid HTTP status code
        if ($code >= 400 && $code < 600) {
            return $code;
        }

        // Default to 500 Internal Server Error
        return 500;
    }

    /**
     * Get error code for response
     * 
     * @param \Throwable $exception The exception
     * @return string Error code
     */
    private function getErrorCode(\Throwable $exception): string
    {
        $className = (new \ReflectionClass($exception))->getShortName();
        return strtoupper(preg_replace('/[A-Z]/', '_$0', lcfirst($className)));
    }

    /**
     * Get user-friendly error message
     * 
     * @param \Throwable $exception The exception
     * @return string User-friendly message
     */
    private function getUserFriendlyMessage(\Throwable $exception): string
    {
        if ($this->config->isDebug()) {
            return $this->sanitizeMessage($exception->getMessage());
        }

        // Return generic messages in production
        $code = $this->getHttpStatusCode($exception);
        
        return match (true) {
            $code >= 500 => 'An internal server error occurred. Please try again later.',
            $code === 404 => 'The requested resource was not found.',
            $code === 403 => 'You do not have permission to access this resource.',
            $code === 401 => 'Authentication is required to access this resource.',
            $code === 400 => 'The request was invalid. Please check your input.',
            default => 'An error occurred. Please try again later.',
        };
    }

    /**
     * Get log path
     * 
     * @return string Log directory path
     */
    private function getLogPath(): string
    {
        $basePath = dirname(__DIR__, 2);
        return $basePath . '/logs';
    }

    /**
     * Prevent cloning of singleton
     */
    private function __clone(): void
    {
    }

    /**
     * Prevent unserialization of singleton
     * 
     * @throws \RuntimeException
     */
    public function __wakeup(): void
    {
        throw new \RuntimeException('Cannot unserialize singleton');
    }
}
