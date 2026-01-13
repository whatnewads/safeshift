<?php

namespace Core\Infrastructure\ErrorHandling;

use Core\Services\LogService;

/**
 * Error Handler for SafeShift
 * Provides comprehensive error logging and debugging
 */
class ErrorHandler
{
    private static $instance = null;
    private $logService;
    private $errorTypes = [
        E_ERROR => 'Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Strict Notice',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated'
    ];

    private function __construct()
    {
        $this->logService = new LogService();
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
     * Register all error handlers
     */
    public static function register(): void
    {
        $handler = self::getInstance();
        
        // Set error handlers
        set_error_handler([$handler, 'handleError']);
        set_exception_handler([$handler, 'handleException']);
        register_shutdown_function([$handler, 'handleShutdown']);
        
        // Create log directory if not exists
        $logDir = dirname(__DIR__, 3) . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Initialize log files
        $logFiles = [
            'php_errors_detailed.json',
            'exceptions.json',
            'fatal_errors.json'
        ];
        
        foreach ($logFiles as $file) {
            $path = $logDir . '/' . $file;
            if (!file_exists($path)) {
                file_put_contents($path, "[\n", LOCK_EX);
            }
        }
    }

    /**
     * Handle PHP errors
     */
    public function handleError($errno, $errstr, $errfile, $errline): bool
    {
        $errorType = $this->errorTypes[$errno] ?? 'Unknown Error';
        
        // Create error log entry
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => $errorType,
            'errno' => $errno,
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
        ];
        
        // Log to file using LogService
        $this->logService->error("PHP Error: $errorType - $errstr", $logEntry);
        
        // Also log to legacy file for backward compatibility
        $logFile = dirname(__DIR__, 3) . '/logs/php_errors_detailed.json';
        $logJson = json_encode($logEntry, JSON_PRETTY_PRINT) . ",\n";
        file_put_contents($logFile, $logJson, FILE_APPEND | LOCK_EX);
        
        // Also log to standard error log
        $logMessage = sprintf(
            "[%s] %s: %s in %s on line %d",
            date('Y-m-d H:i:s'),
            $errorType,
            $errstr,
            $errfile,
            $errline
        );
        error_log($logMessage);
        
        // Don't execute PHP internal error handler
        return true;
    }

    /**
     * Handle uncaught exceptions
     */
    public function handleException(\Throwable $exception): void
    {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'Exception',
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
            'trace' => $exception->getTraceAsString()
        ];
        
        // Log using LogService
        $this->logService->error("Exception: " . get_class($exception) . " - " . $exception->getMessage(), $logEntry);
        
        // Also log to legacy file for backward compatibility
        $logFile = dirname(__DIR__, 3) . '/logs/exceptions.json';
        $logJson = json_encode($logEntry, JSON_PRETTY_PRINT) . ",\n";
        file_put_contents($logFile, $logJson, FILE_APPEND | LOCK_EX);
        
        // Also log to standard error log
        error_log(sprintf(
            "[EXCEPTION] %s: %s in %s on line %d",
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        ));
        
        // Display error page
        if (!headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
        }
        
        // Check environment
        $environment = defined('ENVIRONMENT') ? ENVIRONMENT : 'development';
        
        // In production, show generic error
        if ($environment === 'production') {
            $errorFile = dirname(__DIR__, 3) . '/errors/500.php';
            if (file_exists($errorFile)) {
                include $errorFile;
            } else {
                echo "<h1>Internal Server Error</h1>";
                echo "<p>An error occurred while processing your request. Please try again later.</p>";
            }
        } else {
            // In development, show detailed error
            echo "<h1>Exception Occurred</h1>";
            echo "<p><strong>Type:</strong> " . get_class($exception) . "</p>";
            echo "<p><strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>";
            echo "<p><strong>File:</strong> " . htmlspecialchars($exception->getFile()) . "</p>";
            echo "<p><strong>Line:</strong> " . $exception->getLine() . "</p>";
            echo "<h2>Stack Trace:</h2>";
            echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
        }
        
        exit(1);
    }

    /**
     * Handle fatal errors on shutdown
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            // Log fatal error
            $logEntry = [
                'timestamp' => date('Y-m-d H:i:s'),
                'type' => 'Fatal Error',
                'errno' => $error['type'],
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'CLI',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'N/A'
            ];
            
            // Log using LogService
            $this->logService->critical("Fatal Error: " . $error['message'], $logEntry);
            
            // Also log to legacy file for backward compatibility
            $logFile = dirname(__DIR__, 3) . '/logs/fatal_errors.json';
            $logJson = json_encode($logEntry, JSON_PRETTY_PRINT) . ",\n";
            file_put_contents($logFile, $logJson, FILE_APPEND | LOCK_EX);
            
            // Also log to standard error log
            error_log(sprintf(
                "[FATAL] %s in %s on line %d",
                $error['message'],
                $error['file'],
                $error['line']
            ));
            
            // In production, show generic error page if possible
            $environment = defined('ENVIRONMENT') ? ENVIRONMENT : 'development';
            if ($environment === 'production' && !headers_sent()) {
                header('HTTP/1.1 500 Internal Server Error');
                $errorFile = dirname(__DIR__, 3) . '/errors/500.php';
                if (file_exists($errorFile)) {
                    include $errorFile;
                }
            }
        }
    }

    /**
     * Log application errors (replaces log_app_error function)
     */
    public function logApplicationError(string $type, string $message, array $context = []): void
    {
        $logEntry = array_merge([
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => $type,
            'message' => $message,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
            'session_id' => session_id() ?: 'none',
            'user_id' => $_SESSION['user']['user_id'] ?? null
        ], $context);
        
        // Log using LogService
        $this->logService->error("Application Error: $type - $message", $logEntry);
        
        // Also log to legacy file for backward compatibility
        $logFile = dirname(__DIR__, 3) . '/logs/app_errors_' . date('Y-m-d') . '.json';
        $logJson = json_encode($logEntry, JSON_PRETTY_PRINT) . ",\n";
        file_put_contents($logFile, $logJson, FILE_APPEND | LOCK_EX);
    }
}