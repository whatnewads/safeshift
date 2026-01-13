<?php
/**
 * PHPUnit Bootstrap File
 * 
 * Initializes the test environment for SafeShift EHR unit and integration tests.
 * 
 * @package SafeShift\Tests
 */

declare(strict_types=1);

// Define base paths
define('TEST_ROOT', __DIR__);
define('PROJECT_ROOT', dirname(__DIR__));

// ============================================================================
// AUTOLOADING
// ============================================================================

// Load Composer autoloader (required for PHPUnit and dependencies)
$composerAutoload = PROJECT_ROOT . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
} else {
    die("Composer autoloader not found. Run 'composer install' first.\n");
}

// Load application autoloader for SafeShift classes
$appAutoload = PROJECT_ROOT . '/includes/autoloader.php';
if (file_exists($appAutoload)) {
    require_once $appAutoload;
}

// Register Tests namespace autoloader
spl_autoload_register(function ($class) {
    // Only handle Tests namespace
    if (strpos($class, 'Tests\\') !== 0) {
        return;
    }
    
    // Convert namespace to path
    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
    $filePath = PROJECT_ROOT . DIRECTORY_SEPARATOR . str_replace('Tests' . DIRECTORY_SEPARATOR, 'tests' . DIRECTORY_SEPARATOR, $relativePath);
    
    if (file_exists($filePath)) {
        require_once $filePath;
    }
});

// ============================================================================
// ERROR HANDLING
// ============================================================================

// Set strict error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// Convert errors to exceptions in test environment
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// ============================================================================
// ENVIRONMENT CONFIGURATION
// ============================================================================

// Load test environment configuration
$envFile = TEST_ROOT . '/.env.testing';
if (file_exists($envFile)) {
    $dotenv = parse_ini_file($envFile);
    if ($dotenv !== false) {
        foreach ($dotenv as $key => $value) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Set default test environment variables
$defaultEnv = [
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'true',
    'DB_NAME' => 'safeshift_test',
    'DB_HOST' => 'localhost',
    'DB_USER' => 'root',
    'DB_PASS' => '',
    'SESSION_LIFETIME' => '3600',
    'AUDIT_SALT' => 'test_audit_salt_for_testing_only',
];

foreach ($defaultEnv as $key => $value) {
    if (!getenv($key)) {
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

// ============================================================================
// TEST HELPERS
// ============================================================================

/**
 * Create a mock user array for testing
 * 
 * @param string $role Backend role (e.g., 'pclinician', 'Admin')
 * @param array $overrides Optional field overrides
 * @return array User data array
 */
function createTestUser(string $role = 'pclinician', array $overrides = []): array
{
    $defaults = [
        'user_id' => 'test_user_' . uniqid(),
        'username' => 'testuser',
        'email' => 'testuser@example.com',
        'first_name' => 'Test',
        'last_name' => 'User',
        'role' => $role,
        'clinic_id' => 'clinic_1',
        'two_factor_enabled' => false,
        'last_login' => date('Y-m-d H:i:s'),
    ];
    
    return array_merge($defaults, $overrides);
}

/**
 * Create a mock patient array for testing
 * 
 * @param array $overrides Optional field overrides
 * @return array Patient data array
 */
function createTestPatient(array $overrides = []): array
{
    $defaults = [
        'patient_id' => 'patient_' . uniqid(),
        'first_name' => 'John',
        'last_name' => 'Doe',
        'dob' => '1980-01-15',
        'gender' => 'M',
        'clinic_id' => 'clinic_1',
        'created_at' => date('Y-m-d H:i:s'),
    ];
    
    return array_merge($defaults, $overrides);
}

/**
 * Create a mock encounter array for testing
 * 
 * @param array $overrides Optional field overrides
 * @return array Encounter data array
 */
function createTestEncounter(array $overrides = []): array
{
    $defaults = [
        'encounter_id' => 'enc_' . uniqid(),
        'patient_id' => 'patient_1',
        'provider_id' => 'provider_1',
        'encounter_type' => 'VISIT',
        'status' => 'in_progress',
        'clinic_id' => 'clinic_1',
        'chief_complaint' => 'Test complaint',
        'created_at' => date('Y-m-d H:i:s'),
        'created_by' => 'provider_1',
    ];
    
    return array_merge($defaults, $overrides);
}

/**
 * Assert that an array has all expected keys
 * 
 * @param array $expected Expected keys
 * @param array $actual Actual array
 * @param string $message Optional assertion message
 */
function assertArrayHasKeys(array $expected, array $actual, string $message = ''): void
{
    foreach ($expected as $key) {
        if (!array_key_exists($key, $actual)) {
            throw new PHPUnit\Framework\AssertionFailedError(
                $message ?: "Expected key '$key' not found in array"
            );
        }
    }
}

// ============================================================================
// SESSION MOCKING
// ============================================================================

// Start a mock session for tests that require it
if (session_status() === PHP_SESSION_NONE) {
    // Use array session handler for testing
    ini_set('session.use_cookies', '0');
    ini_set('session.use_only_cookies', '0');
    ini_set('session.cache_limiter', '');
    
    // Start session with test ID
    session_id('test_session_' . uniqid());
    @session_start();
}

// ============================================================================
// DATABASE SETUP (for integration tests)
// ============================================================================

/**
 * Get a test database connection
 * Only connects if needed for integration tests
 * 
 * @return PDO|null Database connection or null if not available
 */
function getTestDatabase(): ?PDO
{
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $host = getenv('DB_HOST') ?: 'localhost';
            $dbname = getenv('DB_NAME') ?: 'safeshift_test';
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_PASS') ?: '';
            
            $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            // Database not available - that's OK for unit tests
            return null;
        }
    }
    
    return $pdo;
}

/**
 * Clean up test data from database
 * Use with caution - only in test database
 * 
 * @param array $tables Tables to clean
 */
function cleanTestTables(array $tables): void
{
    $pdo = getTestDatabase();
    if ($pdo === null) {
        return;
    }
    
    $dbName = getenv('DB_NAME');
    if (strpos($dbName, 'test') === false) {
        throw new RuntimeException("Refusing to clean non-test database: $dbName");
    }
    
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $table) {
        $pdo->exec("TRUNCATE TABLE `$table`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

// ============================================================================
// OUTPUT
// ============================================================================

// Suppress output buffering issues in tests
if (ob_get_level() === 0) {
    ob_start();
}

echo "SafeShift EHR Test Environment Initialized\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Test Database: " . (getenv('DB_NAME') ?: 'safeshift_test') . "\n";
echo "-------------------------------------------\n";
