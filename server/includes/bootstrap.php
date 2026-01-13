<?php
/**
 * Bootstrap File - Infrastructure Initialization Only
 * Located at: /includes/bootstrap.php
 *
 * This file initializes core infrastructure components in the proper order.
 * It should NOT contain any business logic or application-specific code.
 * 
 * Loading Order:
 * 1. Error reporting configuration
 * 2. Configuration file loading
 * 3. Timezone settings
 * 4. Autoloader setup
 * 5. Error handler registration
 * 6. Database connection
 * 7. Session initialization
 * 8. Security headers
 * 9. Directory creation
 * 10. Backward compatibility includes
 * 11. Logger initialization
 */

// ========================================================================
// STEP 1: ERROR REPORTING CONFIGURATION
// Configure error reporting for security and debugging
// ========================================================================
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Never display errors to users in production
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// ========================================================================
// STEP 2: LOAD CONFIGURATION
// Load application configuration constants
// ========================================================================
require_once __DIR__ . '/config.php';

// Define CSRF token name if not already defined
if (!defined('CSRF_TOKEN_NAME')) {
    define('CSRF_TOKEN_NAME', 'csrf_token');
}

// ========================================================================
// STEP 3: TIMEZONE CONFIGURATION
// Set default timezone before any date/time operations
// ========================================================================
date_default_timezone_set('America/Denver');

// ========================================================================
// STEP 4: AUTOLOADER CONFIGURATION
// Register namespace autoloader for Core, App, and ViewModel namespaces
// ========================================================================
spl_autoload_register(function ($class) {
    // Define namespace to directory mappings
    $namespaces = [
        'Core\\'      => __DIR__ . '/../core/',
        'App\\'       => __DIR__ . '/../core/',
        'ViewModel\\' => __DIR__ . '/../ViewModel/',
        'Model\\'     => __DIR__ . '/../model/',
    ];
    
    // Try each namespace prefix
    foreach ($namespaces as $prefix => $base_dir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        
        // Get relative class path
        $relative_class = substr($class, $len);
        
        // Convert namespace separators to directory separators
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        
        // Load file if it exists
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Load Composer autoloader if available (for third-party packages like PHPMailer)
$composer_autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
} else {
    // Log warning but don't fail - not all functionality requires Composer packages
    error_log("Warning: Composer autoloader not found. Run 'composer install' to enable email functionality.");
}

// ========================================================================
// STEP 5: ERROR HANDLER REGISTRATION
// Initialize centralized error handling system
// ========================================================================
\Core\Infrastructure\ErrorHandling\ErrorHandler::register();

// ========================================================================
// STEP 6: DATABASE CONNECTION
// Initialize database connection using singleton pattern
// Database is optional during bootstrap - some functionality may work without it
// ========================================================================
$GLOBALS['db'] = null;
$GLOBALS['db_available'] = false;

try {
    // Validate database configuration
    if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
        throw new Exception('Database configuration is missing. Please check config.php');
    }
    
    // Use the new DatabaseConnection singleton
    $db = \Core\Infrastructure\Database\DatabaseConnection::getInstance();
    
    // Set global database variable for backward compatibility
    $GLOBALS['db'] = $db;
    $GLOBALS['db_available'] = true;
    
    // Log successful connection in development
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        error_log("[Bootstrap] Database connection established successfully");
    }
    
} catch (Exception $e) {
    // Log error securely
    error_log('[Bootstrap] Database connection failed: ' . $e->getMessage());
    
    // Store the error for later reference
    $GLOBALS['db_error'] = $e->getMessage();
    
    // In development, log detailed error but don't die - allow app to continue
    // with limited functionality (file-based logging, static pages, etc.)
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        error_log('[Bootstrap] WARNING: Application continuing without database. Some features will be unavailable.');
    }
    
    // Note: We no longer die() here - the application can boot without database
    // Individual features that require database should check $GLOBALS['db_available']
}

// ========================================================================
// STEP 7: SESSION INITIALIZATION
// Configure and start secure session handling
// ========================================================================
if (session_status() === PHP_SESSION_NONE) {
    // Ensure session directory exists with proper permissions
    $session_path = __DIR__ . '/../sessions';
    if (!is_dir($session_path)) {
        mkdir($session_path, 0700, true);
    }
    
    // Determine if we should use secure cookies
    // On localhost/development HTTP, secure cookies won't work
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $isLocalhost = in_array($host, ['localhost', '127.0.0.1', '::1'])
        || strpos($host, 'localhost:') === 0
        || strpos($host, '127.0.0.1:') === 0;
    
    // Use secure cookies only on HTTPS or if explicitly configured
    // On localhost HTTP, secure cookies would prevent session persistence
    $useSecureCookies = $isHttps || (defined('SESSION_SECURE') && SESSION_SECURE && !$isLocalhost);
    
    // Use Lax SameSite on localhost to allow cross-origin requests from React dev server
    $sameSite = $isLocalhost ? 'Lax' : 'Strict';
    
    // Configure session security settings
    ini_set('session.save_path', $session_path);
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $useSecureCookies ? '1' : '0');
    ini_set('session.cookie_samesite', $sameSite);
    ini_set('session.gc_maxlifetime', '3600'); // 1 hour
    ini_set('session.cookie_lifetime', '0'); // Session cookie
    
    // Set session cookie parameters
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $useSecureCookies,
        'httponly' => true,
        'samesite' => $sameSite
    ]);
    
    // Set session name BEFORE starting session
    // This ensures PHP uses the custom cookie name (SAFESHIFT_SESSION)
    // instead of the default (PHPSESSID)
    session_name(SESSION_NAME);
    
    // Start the session
    session_start();
    
    // Session regeneration security:
    // - Do NOT regenerate immediately on new sessions (this causes session mismatch on concurrent requests)
    // - Only regenerate established sessions periodically (every 5 minutes)
    // - Session ID should be regenerated after successful login (handled in Session::setUser())
    if (isset($_SESSION['last_regeneration'])) {
        // Existing session - check if periodic regeneration is needed
        if (time() - $_SESSION['last_regeneration'] > 300) { // Every 5 minutes
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    } else {
        // New session - just set the timestamp, don't regenerate
        // This prevents session ID mismatch when browser sends concurrent requests before cookie is set
        $_SESSION['last_regeneration'] = time();
    }
    
    // Initialize CSRF token if not exists
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
}

// ========================================================================
// STEP 8: SECURITY HEADERS
// Set security headers if not already sent
// ========================================================================
if (!headers_sent()) {
    // Basic security headers
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    
    // Content Security Policy (LOW-003: Enhanced CSP headers)
    // Note: 'unsafe-inline' for script-src is required for React development mode
    // In production, consider using nonces or hashes instead
    $csp = "default-src 'self'; " .
           "script-src 'self' 'unsafe-inline'; " .
           "style-src 'self' 'unsafe-inline'; " .
           "img-src 'self' data: blob:; " .
           "font-src 'self' data:; " .
           "connect-src 'self' ws: wss:; " .
           "media-src 'self' blob:; " .
           "frame-ancestors 'none'; " .
           "base-uri 'self'; " .
           "form-action 'self';";
    header("Content-Security-Policy: $csp");
}

// ========================================================================
// STEP 9: DIRECTORY CREATION
// Create required application directories with proper permissions
// ========================================================================
$required_dirs = [
    __DIR__ . '/../logs',
    __DIR__ . '/../sessions',
    __DIR__ . '/../uploads',
    __DIR__ . '/../cache',
    __DIR__ . '/../temp'
];

foreach ($required_dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        
        // Add .htaccess to protect sensitive directories
        if (in_array(basename($dir), ['logs', 'sessions', 'uploads', 'cache', 'temp'])) {
            file_put_contents($dir . '/.htaccess', "Deny from all\n");
        }
    }
}

// ========================================================================
// STEP 10: BACKWARD COMPATIBILITY INCLUDES
// Load legacy function files for backward compatibility
// These will be phased out as code is refactored to use new classes
// ========================================================================
$backward_compat_includes = [
    'error_handler.php',    // Provides legacy error handling functions
    'db.php',               // Legacy database functions (to be replaced)
    'auth.php',             // Authentication functions
    'auth_global.php',      // Global authentication helpers
    'validation.php',       // Input validation functions
    'sanitization.php'      // Input sanitization functions
];

foreach ($backward_compat_includes as $include) {
    $file = __DIR__ . '/' . $include;
    if (file_exists($file)) {
        require_once $file;
    } else {
        error_log("Warning: Backward compatibility file not found: " . $file);
    }
}

// Note: Duplicate foreach removed - this was a debugging artifact with syntax error

// ========================================================================
// STEP 11: LOGGER INITIALIZATION
// Initialize logging service for application-wide logging
// ========================================================================
try {
    // Create global logger instance
    $logger = new \Core\Services\LogService();
    
    // Store in globals for easy access
    $GLOBALS['logger'] = $logger;
    
    // Log successful bootstrap
    $logger->info('Bootstrap completed successfully', [
        'php_version' => PHP_VERSION,
        'timezone' => date_default_timezone_get(),
        'session_id' => session_id(),
        'environment' => defined('ENVIRONMENT') ? ENVIRONMENT : 'unknown'
    ], 'system');
    
} catch (Exception $e) {
    // Fall back to error_log if logger fails
    error_log("[Bootstrap] Failed to initialize logging service: " . $e->getMessage());
}

// ========================================================================
// HELPER FUNCTIONS
// Global helper functions for infrastructure access
// ========================================================================

if (!function_exists('logger')) {
    /**
     * Get the global logger instance
     *
     * @return \Core\Services\LogService|null
     */
    function logger() {
        return $GLOBALS['logger'] ?? null;
    }
}

// ========================================================================
// STEP 12: NAMESPACE FUNCTION WRAPPERS
// Load backward compatibility functions for \App\log namespace
// These wrap the new LogService to maintain compatibility with existing code
// ========================================================================
require_once __DIR__ . '/log_functions.php';

// Bootstrap initialization complete
?>