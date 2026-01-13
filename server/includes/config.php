<?php
/**
 * SafeShift EHR Configuration File
 * Located at: root/includes/config.php
 *
 * Contains all system configuration constants
 * HIPAA-compliant settings for the EHR system
 */

// Prevent multiple inclusions
if (defined('CONFIG_LOADED')) {
    return;
}
define('CONFIG_LOADED', true);

/**
 * Load environment variables from .env file
 * @param string $path Path to .env file
 * @return array Parsed environment variables
 */
function loadEnvFile($path) {
    $env = [];
    if (file_exists($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            // Parse KEY=value
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                // Remove quotes if present
                if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                    (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }
                $env[$key] = $value;
                // Also set in $_ENV and putenv for compatibility
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
    return $env;
}

/**
 * Get environment variable with fallback
 * @param string $key Environment variable name
 * @param mixed $default Default value if not found
 * @return mixed
 */
function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        $value = $_ENV[$key] ?? $default;
    }
    // Handle boolean-like strings
    if (is_string($value)) {
        $lower = strtolower($value);
        if ($lower === 'true') return true;
        if ($lower === 'false') return false;
        if ($lower === 'null') return null;
    }
    return $value !== '' ? $value : $default;
}

// Load .env file from project root
$envPath = __DIR__ . '/../.env';
loadEnvFile($envPath);

// Environment Configuration
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', env('APP_ENV', 'development'));
}

// Database Configuration (load from .env with fallbacks)
if (!defined('DB_HOST')) {
    define('DB_HOST', env('DB_HOST', '127.0.0.1'));
    define('DB_PORT', env('DB_PORT', '3306'));
    define('DB_NAME', env('DB_NAME', 'safeshift_ehr_001_0'));
    define('DB_USER', env('DB_USER', 'safeshift_admin'));
    define('DB_PASS', env('DB_PASS', '+0ZX*DvSg.ta3l#S'));
    define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));
}

// Application Configuration
if (!defined('APP_NAME')) {
    define('APP_NAME', env('APP_NAME', 'SafeShift EHR'));
    define('APP_VERSION', '1.0.0');
    define('APP_URL', env('APP_URL', 'http://localhost:8000'));
    define('APP_EMAIL', '1stresponse@safeshift.ai');
}

// Security Configuration (load from .env with fallbacks)
if (!defined('ENCRYPTION_KEY')) {
    $envEncryptionKey = env('ENCRYPTION_KEY', '');
    define('ENCRYPTION_KEY', !empty($envEncryptionKey) ? $envEncryptionKey : bin2hex(random_bytes(32)));
    define('HASH_ALGO', 'sha256');
    define('BCRYPT_COST', 12);
}

// Session Secret from .env
if (!defined('SESSION_SECRET')) {
    $envSessionSecret = env('SESSION_SECRET', '');
    define('SESSION_SECRET', !empty($envSessionSecret) ? $envSessionSecret : bin2hex(random_bytes(32)));
}

// Session Configuration
if (!defined('SESSION_NAME')) {
    define('SESSION_NAME', 'SAFESHIFT_SESSION');
    define('SESSION_LIFETIME', 3600); // 1 hour in seconds
    define('SESSION_PATH', '/');
    define('SESSION_DOMAIN', 'localhost'); // Changed for local development
    define('SESSION_SECURE', false); // Changed to false for local HTTP
    define('SESSION_HTTPONLY', true); // No JavaScript access
    define('SESSION_SAMESITE', 'Strict');
}

// Email Configuration (SMTP) - Load from .env with fallbacks
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', env('SMTP_HOST', 'smtp.gmail.com'));
    define('SMTP_PORT', (int)env('SMTP_PORT', 587));
    define('SMTP_SECURE', env('SMTP_SECURE', 'tls')); // 'tls' or 'ssl'
    define('SMTP_USER', env('SMTP_USER', ''));
    define('SMTP_PASS', env('SMTP_PASS', ''));
    define('SMTP_FROM', env('SMTP_FROM', env('SMTP_USER', '')));
    define('SMTP_FROM_EMAIL', env('SMTP_FROM', env('SMTP_USER', '')));
    define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'SafeShift EHR'));
    define('SMTP_USERNAME', SMTP_USER); // Alias for compatibility
    define('SMTP_PASSWORD', SMTP_PASS); // Alias for compatibility
}

// File Upload Configuration
if (!defined('UPLOAD_MAX_SIZE')) {
    define('UPLOAD_MAX_SIZE', 10485760); // 10MB in bytes
    define('UPLOAD_PATH', __DIR__ . '/../uploads/');
    define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png']);
}

// Logging Configuration
if (!defined('LOG_LEVEL')) {
    define('LOG_LEVEL', 'ERROR'); // DEBUG, INFO, WARNING, ERROR, CRITICAL
    define('LOG_PATH', __DIR__ . '/../logs/');
    define('LOG_ROTATION_DAYS', 90);
}

// OSHA Configuration
if (!defined('OSHA_API_URL')) {
    define('OSHA_API_URL', 'https://www.osha.gov/injuryreporting/api/');
    define('OSHA_API_TOKEN', ''); // Add when available
}

// Timezone
if (!defined('TIMEZONE')) {
    define('TIMEZONE', 'America/Denver'); // Mountain Time
}

// Maintenance Mode
if (!defined('MAINTENANCE_MODE')) {
    define('MAINTENANCE_MODE', false);
    define('MAINTENANCE_MESSAGE', 'System is under maintenance. Please check back later.');
}

// Set timezone
date_default_timezone_set(TIMEZONE);

// Error Reporting
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', 0);
}

// HIPAA Compliance Settings
if (!defined('PHI_ACCESS_LOG')) {
    define('PHI_ACCESS_LOG', true);
    define('AUDIT_TRAIL_RETENTION_DAYS', 2557); // 7 years
    define('PASSWORD_EXPIRY_DAYS', 90);
    define('PASSWORD_MIN_LENGTH', 12);
    define('INACTIVE_LOGOUT_MINUTES', 20);
    define('MAX_FAILED_LOGIN_ATTEMPTS', 5);
    define('MAX_LOGIN_ATTEMPTS', 5); // Alias
    define('LOCKOUT_DURATION_MINUTES', 30);
    define('LOGIN_LOCKOUT_TIME', 1800); // 30 minutes in seconds
    define('SESSION_TIMEOUT', 1200); // 20 minutes in seconds
}

// MFA Configuration
if (!defined('MFA_REQUIRED_ROLES')) {
    define('MFA_REQUIRED_ROLES', ['Admin', 'Clinician', 'Employee', 'EmployerPortal']);
    define('MFA_CODE_LENGTH', 6);
    define('MFA_CODE_EXPIRY', 900); // 15 minutes in seconds
    define('MFA_CODE_EXPIRY_SECONDS', MFA_CODE_EXPIRY); // Alias
}

// CSRF Protection
if (!defined('CSRF_TOKEN_NAME')) {
    define('CSRF_TOKEN_NAME', 'csrf_token');
}

// API Rate Limiting
if (!defined('API_RATE_LIMIT_REQUESTS')) {
    define('API_RATE_LIMIT_REQUESTS', 100);
    define('API_RATE_LIMIT_WINDOW', 3600); // 1 hour
}

// Backup Configuration
if (!defined('BACKUP_PATH')) {
    define('BACKUP_PATH', __DIR__ . '/../backups/');
    define('BACKUP_ENCRYPTION', true);
    define('BACKUP_RETENTION_DAYS', 30);
}

// Business Rules
if (!defined('DEFAULT_EMPLOYER_ID')) {
    define('DEFAULT_EMPLOYER_ID', null);
    define('ALLOW_PATIENT_PORTAL', true);
    define('ALLOW_MOBILE_ACCESS', true);
    define('REQUIRE_EMPLOYER_APPROVAL', false);
}

// DOT Testing Configuration
if (!defined('DOT_MRO_REVIEW_REQUIRED')) {
    define('DOT_MRO_REVIEW_REQUIRED', true);
    define('DOT_RESULT_NOTIFICATION_DAYS', 2);
}

// OSHA Reporting
if (!defined('OSHA_REPORTING_ENABLED')) {
    define('OSHA_REPORTING_ENABLED', true);
    define('OSHA_AUTO_SUBMIT', false);
    define('OSHA_ESTABLISHMENT_ID', '');
}

// Feature Flags
if (!defined('FEATURE_TELEMEDICINE')) {
    define('FEATURE_TELEMEDICINE', false);
    define('FEATURE_E_PRESCRIBING', false);
    define('FEATURE_LAB_INTEGRATION', true);
    define('FEATURE_INSURANCE_BILLING', false);
}

// Third-party API Keys (add actual keys in production)
if (!defined('GOOGLE_MAPS_API_KEY')) {
    define('GOOGLE_MAPS_API_KEY', '');
    define('TWILIO_ACCOUNT_SID', '');
    define('TWILIO_AUTH_TOKEN', '');
    define('TWILIO_PHONE_NUMBER', '');
}

// Error Pages
if (!defined('ERROR_403_PAGE')) {
    define('ERROR_403_PAGE', '/errors/403.php');
    define('ERROR_404_PAGE', '/errors/404.php');
    define('ERROR_500_PAGE', '/errors/500.php');
}

// Performance Settings
if (!defined('ENABLE_CACHE')) {
    define('ENABLE_CACHE', true);
    define('CACHE_LIFETIME', 3600); // 1 hour
    define('COMPRESS_OUTPUT', true);
    define('MINIFY_ASSETS', ENVIRONMENT === 'production');
}

// Debug Settings
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', ENVIRONMENT === 'development');
    define('SHOW_SQL_ERRORS', DEBUG_MODE);
    define('LOG_SQL_QUERIES', DEBUG_MODE);
}

// Create required directories if they don't exist
$required_dirs = [
    LOG_PATH,
    UPLOAD_PATH,
    BACKUP_PATH,
    __DIR__ . '/../sessions',
    __DIR__ . '/../cache',
    __DIR__ . '/../temp'
];

foreach ($required_dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Set secure session parameters
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.name', SESSION_NAME);
    ini_set('session.cookie_lifetime', SESSION_LIFETIME);
    ini_set('session.cookie_path', SESSION_PATH);
    ini_set('session.cookie_domain', SESSION_DOMAIN);
    ini_set('session.cookie_secure', SESSION_SECURE);
    ini_set('session.cookie_httponly', SESSION_HTTPONLY);
    ini_set('session.cookie_samesite', SESSION_SAMESITE);
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    ini_set('session.save_path', __DIR__ . '/../sessions');
}