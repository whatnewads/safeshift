<?php
/**
 * @deprecated This file is deprecated and will be removed in a future version.
 * @see \Core\Infrastructure\ErrorHandling\ErrorHandler
 * 
 * Legacy Error Handler for SafeShift - Maintained for backward compatibility
 * All functionality has been moved to Core\Infrastructure\ErrorHandling\ErrorHandler
 * 
 * This file now acts as a compatibility layer that wraps the new ErrorHandler class.
 * Please update your code to use the new ErrorHandler directly.
 */

namespace App\error;

/**
 * @deprecated Use \Core\Infrastructure\ErrorHandling\ErrorHandler::handleError() instead
 * 
 * Custom error handler - wrapper for backward compatibility
 */
function custom_error_handler($errno, $errstr, $errfile, $errline) {
    $handler = \Core\Infrastructure\ErrorHandling\ErrorHandler::getInstance();
    return $handler->handleError($errno, $errstr, $errfile, $errline);
}

/**
 * @deprecated Use \Core\Infrastructure\ErrorHandling\ErrorHandler::handleException() instead
 * 
 * Custom exception handler - wrapper for backward compatibility
 */
function custom_exception_handler($exception) {
    $handler = \Core\Infrastructure\ErrorHandling\ErrorHandler::getInstance();
    $handler->handleException($exception);
}

/**
 * @deprecated Use \Core\Infrastructure\ErrorHandling\ErrorHandler::handleShutdown() instead
 * 
 * Shutdown function to catch fatal errors - wrapper for backward compatibility
 */
function shutdown_handler() {
    $handler = \Core\Infrastructure\ErrorHandling\ErrorHandler::getInstance();
    $handler->handleShutdown();
}

/**
 * @deprecated Use \Core\Infrastructure\ErrorHandling\ErrorHandler::logApplicationError() instead
 * 
 * Log application errors - wrapper for backward compatibility
 */
function log_app_error($type, $message, $context = []) {
    $handler = \Core\Infrastructure\ErrorHandling\ErrorHandler::getInstance();
    $handler->logApplicationError($type, $message, $context);
}

/**
 * @deprecated Use \Core\Infrastructure\ErrorHandling\ErrorHandler::register() instead
 * 
 * Initialize error handlers - wrapper for backward compatibility
 * Note: This function no longer needs to be called as ErrorHandler::register() 
 * is called in bootstrap.php
 */
function init_error_handlers() {
    // Check if handlers are already registered to prevent double registration
    static $initialized = false;
    if ($initialized) {
        return;
    }
    
    // If ErrorHandler hasn't been registered yet (edge case), register it
    if (!class_exists('\Core\Infrastructure\ErrorHandling\ErrorHandler')) {
        // Try to load the autoloader if it exists
        $autoloaderPath = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($autoloaderPath)) {
            require_once $autoloaderPath;
        }
    }
    
    // Register handlers if class exists
    if (class_exists('\Core\Infrastructure\ErrorHandling\ErrorHandler')) {
        \Core\Infrastructure\ErrorHandling\ErrorHandler::register();
        $initialized = true;
    } else {
        // Fallback to manual registration if class not available
        trigger_error('ErrorHandler class not found. Please check your autoloader configuration.', E_USER_WARNING);
    }
}

// For complete backward compatibility, call init_error_handlers
// This will be a no-op if handlers are already registered in bootstrap.php
init_error_handlers();