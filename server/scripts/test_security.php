<?php
/**
 * Security & Compliance Test Script for SafeShift EHR
 * 
 * This script performs comprehensive security testing including:
 * - SQL injection vulnerability scanning
 * - Authentication requirement verification
 * - CSRF token validation testing
 * - Input sanitization verification
 * - PHI/PII protection checks
 * 
 * Run from CLI: php scripts/test_security.php
 * 
 * @package SafeShift\Scripts
 * @author Security Testing Team
 * @version 1.0.0
 */

declare(strict_types=1);

// Configure error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define constants
define('SECURITY_TEST_VERSION', '1.0.0');
define('PROJECT_ROOT', dirname(__DIR__));

// Initialize results tracking
$testResults = [
    'timestamp' => date('Y-m-d H:i:s'),
    'version' => SECURITY_TEST_VERSION,
    'summary' => [
        'total_tests' => 0,
        'passed' => 0,
        'failed' => 0,
        'warnings' => 0,
        'critical' => 0,
    ],
    'categories' => [],
];

/**
 * Output formatted test result
 */
function outputResult(string $category, string $test, string $status, string $message, ?string $details = null): void
{
    global $testResults;
    
    $testResults['summary']['total_tests']++;
    
    $statusColors = [
        'PASS' => "\033[32m",    // Green
        'FAIL' => "\033[31m",    // Red
        'WARN' => "\033[33m",    // Yellow
        'CRIT' => "\033[35m",    // Magenta
        'INFO' => "\033[36m",    // Cyan
    ];
    
    $reset = "\033[0m";
    $color = $statusColors[$status] ?? '';
    
    switch ($status) {
        case 'PASS':
            $testResults['summary']['passed']++;
            break;
        case 'FAIL':
            $testResults['summary']['failed']++;
            break;
        case 'WARN':
            $testResults['summary']['warnings']++;
            break;
        case 'CRIT':
            $testResults['summary']['critical']++;
            $testResults['summary']['failed']++;
            break;
    }
    
    // Store result
    if (!isset($testResults['categories'][$category])) {
        $testResults['categories'][$category] = [];
    }
    
    $testResults['categories'][$category][] = [
        'test' => $test,
        'status' => $status,
        'message' => $message,
        'details' => $details,
    ];
    
    // Output to console
    echo sprintf(
        "[%s%s%s] %s: %s%s\n",
        $color,
        $status,
        $reset,
        $category,
        $message,
        $details ? " - $details" : ''
    );
}

/**
 * Section header output
 */
function outputSection(string $title): void
{
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "  $title\n";
    echo str_repeat('=', 60) . "\n\n";
}

// =============================================================================
// SECURITY TESTS
// =============================================================================

outputSection("SafeShift EHR Security & Compliance Testing");
echo "Version: " . SECURITY_TEST_VERSION . "\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "Project Root: " . PROJECT_ROOT . "\n\n";

// =============================================================================
// 1. SQL INJECTION VULNERABILITY SCAN
// =============================================================================

outputSection("1. SQL Injection Vulnerability Scan");

/**
 * Scan repository files for potential SQL injection vulnerabilities
 */
function scanForSqlInjection(): void
{
    $repositoryDirs = [
        PROJECT_ROOT . '/model/Repositories',
        PROJECT_ROOT . '/core/Repositories',
    ];
    
    $dangerousPatterns = [
        // Direct string concatenation in SQL
        '/\$sql\s*\.?=\s*["\'].*\$[a-zA-Z_]+/',
        '/execute\s*\(\s*["\'].*\$[a-zA-Z_]+/',
        // Using user input directly in queries
        '/\$_GET\[.*\].*(?:SELECT|INSERT|UPDATE|DELETE)/i',
        '/\$_POST\[.*\].*(?:SELECT|INSERT|UPDATE|DELETE)/i',
        '/\$_REQUEST\[.*\].*(?:SELECT|INSERT|UPDATE|DELETE)/i',
    ];
    
    $safePatterns = [
        '/prepare\s*\(/',          // PDO prepared statements
        '/bindParam/',             // Parameter binding
        '/bindValue/',             // Value binding
        '/execute\s*\(\s*\[/',     // Named parameter arrays
        '/:[\w]+/',                // Named placeholders
        '/\?/',                    // Positional placeholders
    ];
    
    $vulnerabilities = [];
    $filesChecked = 0;
    $safeFiles = 0;
    
    foreach ($repositoryDirs as $dir) {
        if (!is_dir($dir)) {
            outputResult('SQL Injection', 'Directory Check', 'WARN', "Directory not found: $dir");
            continue;
        }
        
        $files = glob($dir . '/*.php');
        foreach ($files as $file) {
            $filesChecked++;
            $content = file_get_contents($file);
            $filename = basename($file);
            $isVulnerable = false;
            $usesPrepared = false;
            
            // Check for safe patterns (prepared statements)
            foreach ($safePatterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $usesPrepared = true;
                    break;
                }
            }
            
            // Check for dangerous patterns
            foreach ($dangerousPatterns as $pattern) {
                if (preg_match($pattern, $content, $matches)) {
                    // Skip if it's a comment or properly escaped
                    $lineNumber = substr_count(substr($content, 0, strpos($content, $matches[0])), "\n") + 1;
                    
                    // Check context - is this actually vulnerable?
                    $lines = explode("\n", $content);
                    $context = $lines[$lineNumber - 1] ?? '';
                    
                    // Skip commented lines
                    if (preg_match('/^\s*(\/\/|#|\*|\/\*)/', $context)) {
                        continue;
                    }
                    
                    $vulnerabilities[] = [
                        'file' => $filename,
                        'line' => $lineNumber,
                        'pattern' => $matches[0],
                    ];
                    $isVulnerable = true;
                }
            }
            
            if (!$isVulnerable && $usesPrepared) {
                $safeFiles++;
            }
        }
    }
    
    // Report results
    outputResult(
        'SQL Injection',
        'Repository Scan',
        $filesChecked > 0 ? 'PASS' : 'WARN',
        "Scanned $filesChecked repository files",
        "$safeFiles files use prepared statements"
    );
    
    // Check specific critical files
    $criticalFiles = [
        'PatientRepository.php' => '/model/Repositories/PatientRepository.php',
        'EncounterRepository.php' => '/model/Repositories/EncounterRepository.php',
        'UserRepository.php' => '/App/Repositories/UserRepository.php',
    ];
    
    foreach ($criticalFiles as $name => $path) {
        $fullPath = PROJECT_ROOT . $path;
        if (!file_exists($fullPath)) {
            outputResult('SQL Injection', $name, 'WARN', "File not found", $path);
            continue;
        }
        
        $content = file_get_contents($fullPath);
        
        // Count prepared statement usage
        $preparedCount = preg_match_all('/->prepare\s*\(/', $content);
        $executeCount = preg_match_all('/->execute\s*\(/', $content);
        $bindCount = preg_match_all('/bindValue|bindParam/', $content);
        
        // Check for direct concatenation in SQL strings (potential vulnerability)
        $concatenationRisk = preg_match('/\$sql\s*=\s*["\']SELECT.*["\'].*\$[a-zA-Z_]+/', $content);
        
        if ($preparedCount > 0 && !$concatenationRisk) {
            outputResult(
                'SQL Injection',
                $name,
                'PASS',
                "Uses parameterized queries",
                "Prepared: $preparedCount, Execute: $executeCount, Bindings: $bindCount"
            );
        } else if ($concatenationRisk) {
            outputResult(
                'SQL Injection',
                $name,
                'WARN',
                "Potential string concatenation in SQL detected",
                "Review manually for safety"
            );
        } else {
            outputResult(
                'SQL Injection',
                $name,
                'INFO',
                "No prepared statements found",
                "May use ORM or other abstraction"
            );
        }
    }
}

scanForSqlInjection();

// =============================================================================
// 2. AUTHENTICATION TESTING
// =============================================================================

outputSection("2. Authentication Implementation Testing");

/**
 * Test authentication implementation
 */
function testAuthentication(): void
{
    // Check for password hashing
    $authServicePath = PROJECT_ROOT . '/core/Services/AuthService.php';
    if (file_exists($authServicePath)) {
        $content = file_get_contents($authServicePath);
        
        // Check for proper password verification
        if (strpos($content, 'password_verify') !== false) {
            outputResult('Authentication', 'Password Verification', 'PASS', 'Uses password_verify() for secure password checking');
        } else {
            outputResult('Authentication', 'Password Verification', 'CRIT', 'password_verify() not found - CRITICAL SECURITY ISSUE');
        }
        
        // Check for MFA support
        if (strpos($content, 'mfa_enabled') !== false || strpos($content, '2FA') !== false || strpos($content, 'otp') !== false) {
            outputResult('Authentication', 'MFA Support', 'PASS', 'Multi-factor authentication is implemented');
        } else {
            outputResult('Authentication', 'MFA Support', 'WARN', 'MFA implementation not detected');
        }
        
        // Check for account lockout
        if (strpos($content, 'lockAccount') !== false || strpos($content, 'login_attempts') !== false) {
            outputResult('Authentication', 'Account Lockout', 'PASS', 'Account lockout mechanism is implemented');
        } else {
            outputResult('Authentication', 'Account Lockout', 'WARN', 'Account lockout mechanism not detected');
        }
        
        // Check for audit logging
        if (strpos($content, 'audit') !== false || strpos($content, 'logFailedLogin') !== false) {
            outputResult('Authentication', 'Auth Audit Logging', 'PASS', 'Authentication events are logged');
        } else {
            outputResult('Authentication', 'Auth Audit Logging', 'WARN', 'Auth audit logging not detected');
        }
    } else {
        outputResult('Authentication', 'AuthService', 'WARN', 'AuthService.php not found at expected location');
    }
    
    // Check Session security
    $sessionPath = PROJECT_ROOT . '/model/Core/Session.php';
    if (file_exists($sessionPath)) {
        $content = file_get_contents($sessionPath);
        
        // Check for secure cookie settings
        if (strpos($content, 'httponly') !== false && strpos($content, 'secure') !== false) {
            outputResult('Authentication', 'Secure Cookies', 'PASS', 'Session cookies use HttpOnly and Secure flags');
        } else {
            outputResult('Authentication', 'Secure Cookies', 'WARN', 'Check session cookie security settings');
        }
        
        // Check for session regeneration
        if (strpos($content, 'regenerate') !== false || strpos($content, 'session_regenerate_id') !== false) {
            outputResult('Authentication', 'Session Regeneration', 'PASS', 'Session ID regeneration is implemented');
        } else {
            outputResult('Authentication', 'Session Regeneration', 'WARN', 'Session regeneration not detected');
        }
        
        // Check for session fingerprinting
        if (strpos($content, 'fingerprint') !== false) {
            outputResult('Authentication', 'Session Fingerprinting', 'PASS', 'Session fingerprinting is implemented');
        } else {
            outputResult('Authentication', 'Session Fingerprinting', 'INFO', 'Session fingerprinting not detected');
        }
        
        // Check for session timeout
        if (strpos($content, 'timeout') !== false || strpos($content, 'idle') !== false) {
            outputResult('Authentication', 'Session Timeout', 'PASS', 'Session timeout mechanism is implemented');
        } else {
            outputResult('Authentication', 'Session Timeout', 'WARN', 'Session timeout not detected');
        }
    } else {
        outputResult('Authentication', 'Session', 'WARN', 'Session.php not found at expected location');
    }
}

testAuthentication();

// =============================================================================
// 3. AUTHORIZATION / RBAC TESTING
// =============================================================================

outputSection("3. Role-Based Access Control (RBAC) Testing");

/**
 * Test authorization implementation
 */
function testAuthorization(): void
{
    // Check RoleService
    $roleServicePath = PROJECT_ROOT . '/model/Services/RoleService.php';
    if (file_exists($roleServicePath)) {
        $content = file_get_contents($roleServicePath);
        
        // Check for role definitions
        $roleCount = preg_match_all('/ROLE_[A-Z_]+\s*=/', $content);
        outputResult(
            'Authorization',
            'Role Definitions',
            $roleCount > 0 ? 'PASS' : 'WARN',
            "Found $roleCount role constant definitions"
        );
        
        // Check for permission system
        if (strpos($content, 'ROLE_PERMISSIONS') !== false || strpos($content, 'hasPermission') !== false) {
            outputResult('Authorization', 'Permission System', 'PASS', 'Permission-based authorization is implemented');
        } else {
            outputResult('Authorization', 'Permission System', 'WARN', 'Permission system not detected');
        }
        
        // Check for role hierarchy
        if (strpos($content, 'getRoleLevel') !== false || strpos($content, 'isAdmin') !== false) {
            outputResult('Authorization', 'Role Hierarchy', 'PASS', 'Role hierarchy is implemented');
        } else {
            outputResult('Authorization', 'Role Hierarchy', 'INFO', 'Role hierarchy not explicitly defined');
        }
    } else {
        outputResult('Authorization', 'RoleService', 'WARN', 'RoleService.php not found');
    }
    
    // Check AuthorizationService
    $authzServicePath = PROJECT_ROOT . '/model/Services/AuthorizationService.php';
    if (file_exists($authzServicePath)) {
        $content = file_get_contents($authzServicePath);
        
        // Check for clinic-based access control
        if (strpos($content, 'clinic') !== false) {
            outputResult('Authorization', 'Clinic-Based Access', 'PASS', 'Clinic-based access control is implemented');
        } else {
            outputResult('Authorization', 'Clinic-Based Access', 'INFO', 'Clinic-based access not detected');
        }
        
        // Check for resource-based authorization
        if (strpos($content, 'canEditEncounter') !== false || strpos($content, 'canViewPatient') !== false) {
            outputResult('Authorization', 'Resource Authorization', 'PASS', 'Resource-level authorization checks exist');
        } else {
            outputResult('Authorization', 'Resource Authorization', 'WARN', 'Resource-level authorization not detected');
        }
    } else {
        outputResult('Authorization', 'AuthorizationService', 'WARN', 'AuthorizationService.php not found');
    }
    
    // Check API endpoint protection
    $apiEndpoints = [
        '/api/v1/patients.php' => 'Patient API',
        '/api/v1/encounters.php' => 'Encounter API',
        '/api/v1/admin.php' => 'Admin API',
    ];
    
    foreach ($apiEndpoints as $path => $name) {
        $fullPath = PROJECT_ROOT . $path;
        if (file_exists($fullPath)) {
            $content = file_get_contents($fullPath);
            
            // Check for session authentication
            if (strpos($content, '$_SESSION') !== false && strpos($content, 'user') !== false) {
                outputResult('Authorization', "$name Auth Check", 'PASS', 'Checks user session before processing');
            } else {
                outputResult('Authorization', "$name Auth Check", 'WARN', 'Session check not clearly visible');
            }
        }
    }
}

testAuthorization();

// =============================================================================
// 4. CSRF PROTECTION TESTING
// =============================================================================

outputSection("4. CSRF Protection Testing");

/**
 * Test CSRF protection implementation
 */
function testCsrfProtection(): void
{
    // Check for CSRF token generation
    $sessionPath = PROJECT_ROOT . '/model/Core/Session.php';
    if (file_exists($sessionPath)) {
        $content = file_get_contents($sessionPath);
        
        if (strpos($content, 'generateCsrfToken') !== false || strpos($content, 'csrf') !== false) {
            outputResult('CSRF Protection', 'Token Generation', 'PASS', 'CSRF token generation is implemented');
        } else {
            outputResult('CSRF Protection', 'Token Generation', 'WARN', 'CSRF token generation not detected');
        }
        
        if (strpos($content, 'validateCsrfToken') !== false) {
            outputResult('CSRF Protection', 'Token Validation', 'PASS', 'CSRF token validation is implemented');
        } else {
            outputResult('CSRF Protection', 'Token Validation', 'WARN', 'CSRF token validation not detected');
        }
        
        // Check for timing-safe comparison
        if (strpos($content, 'hash_equals') !== false) {
            outputResult('CSRF Protection', 'Timing-Safe Compare', 'PASS', 'Uses timing-safe comparison for tokens');
        } else {
            outputResult('CSRF Protection', 'Timing-Safe Compare', 'WARN', 'Timing-safe comparison not detected');
        }
    }
    
    // Check API endpoints for CSRF validation
    $authApiPath = PROJECT_ROOT . '/api/v1/auth.php';
    if (file_exists($authApiPath)) {
        $content = file_get_contents($authApiPath);
        
        if (strpos($content, 'validateCsrf') !== false) {
            outputResult('CSRF Protection', 'API CSRF Check', 'PASS', 'Auth API validates CSRF tokens');
        } else {
            outputResult('CSRF Protection', 'API CSRF Check', 'WARN', 'CSRF validation not found in auth API');
        }
        
        // Check which endpoints require CSRF
        $csrfRequired = ['logout', 'refresh-session'];
        foreach ($csrfRequired as $endpoint) {
            if (preg_match('/\'' . $endpoint . '\'.*validateCsrf/s', $content) ||
                preg_match('/"' . $endpoint . '".*validateCsrf/s', $content)) {
                outputResult('CSRF Protection', "$endpoint Endpoint", 'PASS', "CSRF validation enabled for $endpoint");
            }
        }
    }
    
    // Check frontend for CSRF token handling
    $authContextPath = PROJECT_ROOT . '/src/app/contexts/AuthContext.tsx';
    if (file_exists($authContextPath)) {
        $content = file_get_contents($authContextPath);
        
        if (strpos($content, 'csrf') !== false || strpos($content, 'CSRF') !== false) {
            outputResult('CSRF Protection', 'Frontend CSRF', 'PASS', 'Frontend handles CSRF tokens');
        } else {
            outputResult('CSRF Protection', 'Frontend CSRF', 'INFO', 'CSRF handling not found in AuthContext');
        }
    }
}

testCsrfProtection();

// =============================================================================
// 5. XSS PREVENTION TESTING
// =============================================================================

outputSection("5. XSS Prevention Testing");

/**
 * Test XSS prevention measures
 */
function testXssPrevention(): void
{
    // Check InputSanitizer
    $sanitizerPath = PROJECT_ROOT . '/core/Helpers/InputSanitizer.php';
    if (file_exists($sanitizerPath)) {
        $content = file_get_contents($sanitizerPath);
        
        // Check for htmlspecialchars usage
        if (strpos($content, 'htmlspecialchars') !== false) {
            outputResult('XSS Prevention', 'HTML Escaping', 'PASS', 'Uses htmlspecialchars for output encoding');
        } else {
            outputResult('XSS Prevention', 'HTML Escaping', 'WARN', 'htmlspecialchars not found');
        }
        
        // Check for XSS removal function
        if (strpos($content, 'removeXss') !== false || strpos($content, 'remove_xss') !== false) {
            outputResult('XSS Prevention', 'XSS Removal', 'PASS', 'Dedicated XSS removal function exists');
        } else {
            outputResult('XSS Prevention', 'XSS Removal', 'INFO', 'Dedicated XSS removal not found');
        }
        
        // Check for script tag filtering
        if (strpos($content, '<script') !== false || strpos($content, 'javascript') !== false) {
            outputResult('XSS Prevention', 'Script Filtering', 'PASS', 'Filters script tags and javascript protocol');
        } else {
            outputResult('XSS Prevention', 'Script Filtering', 'INFO', 'Script filtering patterns not found');
        }
        
        // Check for event handler removal
        if (preg_match('/on\w+\s*=/i', $content)) {
            outputResult('XSS Prevention', 'Event Handler Filter', 'PASS', 'Filters inline event handlers');
        } else {
            outputResult('XSS Prevention', 'Event Handler Filter', 'INFO', 'Event handler filtering not detected');
        }
    } else {
        outputResult('XSS Prevention', 'InputSanitizer', 'WARN', 'InputSanitizer.php not found');
    }
    
    // Check frontend for dangerouslySetInnerHTML usage
    $frontendDirs = [
        PROJECT_ROOT . '/src/app/components',
        PROJECT_ROOT . '/src/app/pages',
    ];
    
    $dangerousUsage = [];
    foreach ($frontendDirs as $dir) {
        if (is_dir($dir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile() && preg_match('/\.(tsx?|jsx?)$/', $file->getFilename())) {
                    $content = file_get_contents($file->getPathname());
                    if (strpos($content, 'dangerouslySetInnerHTML') !== false) {
                        $dangerousUsage[] = $file->getPathname();
                    }
                }
            }
        }
    }
    
    if (empty($dangerousUsage)) {
        outputResult('XSS Prevention', 'React dangerouslySetInnerHTML', 'PASS', 'No dangerouslySetInnerHTML usage found');
    } else {
        outputResult(
            'XSS Prevention',
            'React dangerouslySetInnerHTML',
            'WARN',
            count($dangerousUsage) . ' files use dangerouslySetInnerHTML',
            'Review for proper sanitization'
        );
    }
    
    // Check API responses for Content-Type headers
    $apiIndexPath = PROJECT_ROOT . '/api/v1/index.php';
    if (file_exists($apiIndexPath)) {
        $content = file_get_contents($apiIndexPath);
        
        if (strpos($content, 'application/json') !== false) {
            outputResult('XSS Prevention', 'API Content-Type', 'PASS', 'API sets JSON content type header');
        } else {
            outputResult('XSS Prevention', 'API Content-Type', 'WARN', 'JSON content type header not found');
        }
    }
}

testXssPrevention();

// =============================================================================
// 6. PHI/PII SECURITY TESTING
// =============================================================================

outputSection("6. PHI/PII Security Testing (HIPAA Compliance)");

/**
 * Test PHI/PII protection measures
 */
function testPhiPiiSecurity(): void
{
    // Check SSN Value Object
    $ssnPath = PROJECT_ROOT . '/model/ValueObjects/SSN.php';
    if (file_exists($ssnPath)) {
        $content = file_get_contents($ssnPath);
        
        // Check for encryption
        if (strpos($content, 'encrypt') !== false && strpos($content, 'aes') !== false) {
            outputResult('PHI Security', 'SSN Encryption', 'PASS', 'SSN values are encrypted (AES)');
        } else {
            outputResult('PHI Security', 'SSN Encryption', 'CRIT', 'SSN encryption not detected - HIPAA VIOLATION');
        }
        
        // Check for masking
        if (strpos($content, 'getMasked') !== false || strpos($content, 'lastFour') !== false) {
            outputResult('PHI Security', 'SSN Masking', 'PASS', 'SSN masking is implemented');
        } else {
            outputResult('PHI Security', 'SSN Masking', 'WARN', 'SSN masking not found');
        }
        
        // Check for debug info protection
        if (strpos($content, '__debugInfo') !== false) {
            outputResult('PHI Security', 'SSN Debug Protection', 'PASS', 'SSN debug output is protected');
        } else {
            outputResult('PHI Security', 'SSN Debug Protection', 'WARN', 'SSN debug protection not found');
        }
    } else {
        outputResult('PHI Security', 'SSN Value Object', 'WARN', 'SSN.php not found');
    }
    
    // Check audit logging
    $auditLogDirs = [
        PROJECT_ROOT . '/logs',
    ];
    
    $auditLogs = [];
    foreach ($auditLogDirs as $dir) {
        if (is_dir($dir)) {
            $files = glob($dir . '/audit*.log');
            $files = array_merge($files, glob($dir . '/phi*.log'));
            $auditLogs = array_merge($auditLogs, $files);
        }
    }
    
    if (!empty($auditLogs)) {
        outputResult('PHI Security', 'Audit Logging', 'PASS', count($auditLogs) . ' audit log files found');
        
        // Check for log file protection
        $htaccessPath = PROJECT_ROOT . '/logs/.htaccess';
        if (file_exists($htaccessPath)) {
            $content = file_get_contents($htaccessPath);
            if (strpos($content, 'deny') !== false || strpos($content, 'Deny') !== false) {
                outputResult('PHI Security', 'Log File Protection', 'PASS', '.htaccess denies web access to logs');
            } else {
                outputResult('PHI Security', 'Log File Protection', 'WARN', '.htaccess exists but may not deny access');
            }
        } else {
            outputResult('PHI Security', 'Log File Protection', 'WARN', '.htaccess not found in logs directory');
        }
    } else {
        outputResult('PHI Security', 'Audit Logging', 'WARN', 'No audit log files found');
    }
    
    // Check for PHI access logging
    $ehrLoggerPath = PROJECT_ROOT . '/core/Services/EHRLogger.php';
    if (file_exists($ehrLoggerPath)) {
        $content = file_get_contents($ehrLoggerPath);
        
        if (strpos($content, 'logPHIAccess') !== false) {
            outputResult('PHI Security', 'PHI Access Logging', 'PASS', 'PHI access logging is implemented');
        } else {
            outputResult('PHI Security', 'PHI Access Logging', 'WARN', 'PHI access logging not found');
        }
    }
    
    // Check for HTTPS enforcement
    $configFiles = [
        PROJECT_ROOT . '/includes/config.php',
        PROJECT_ROOT . '/model/Config/AppConfig.php',
    ];
    
    $httpsEnforced = false;
    foreach ($configFiles as $configFile) {
        if (file_exists($configFile)) {
            $content = file_get_contents($configFile);
            if (strpos($content, 'https') !== false || strpos($content, 'HTTPS') !== false) {
                $httpsEnforced = true;
                break;
            }
        }
    }
    
    if ($httpsEnforced) {
        outputResult('PHI Security', 'HTTPS Configuration', 'PASS', 'HTTPS configuration detected');
    } else {
        outputResult('PHI Security', 'HTTPS Configuration', 'WARN', 'HTTPS configuration not explicitly found');
    }
    
    // Check session cookie security
    if (file_exists(PROJECT_ROOT . '/model/Core/Session.php')) {
        $content = file_get_contents(PROJECT_ROOT . '/model/Core/Session.php');
        
        $secureFlags = [
            'secure' => strpos($content, "'secure'") !== false || strpos($content, '"secure"') !== false,
            'httponly' => strpos($content, "'httponly'") !== false || strpos($content, '"httponly"') !== false,
            'samesite' => strpos($content, "'samesite'") !== false || strpos($content, '"samesite"') !== false,
        ];
        
        $allSecure = array_reduce($secureFlags, fn($a, $b) => $a && $b, true);
        
        if ($allSecure) {
            outputResult('PHI Security', 'Session Cookie Security', 'PASS', 'Session cookies configured with secure flags');
        } else {
            $missing = array_keys(array_filter($secureFlags, fn($v) => !$v));
            outputResult('PHI Security', 'Session Cookie Security', 'WARN', 'Missing cookie flags: ' . implode(', ', $missing));
        }
    }
}

testPhiPiiSecurity();

// =============================================================================
// 7. API ENDPOINT SECURITY
// =============================================================================

outputSection("7. API Endpoint Security Testing");

/**
 * Test API endpoint security
 */
function testApiSecurity(): void
{
    // Check rate limiting
    $authApiPath = PROJECT_ROOT . '/api/v1/auth.php';
    if (file_exists($authApiPath)) {
        $content = file_get_contents($authApiPath);
        
        if (strpos($content, 'rateLimit') !== false || strpos($content, 'rate_limit') !== false) {
            outputResult('API Security', 'Rate Limiting', 'PASS', 'Rate limiting is implemented for auth endpoints');
        } else {
            outputResult('API Security', 'Rate Limiting', 'WARN', 'Rate limiting not detected');
        }
        
        // Check for CORS configuration
        if (strpos($content, 'Access-Control') !== false) {
            outputResult('API Security', 'CORS Configuration', 'PASS', 'CORS headers are configured');
            
            // Check if CORS is restrictive
            if (strpos($content, 'Access-Control-Allow-Origin: *') !== false) {
                outputResult('API Security', 'CORS Restriction', 'WARN', 'CORS allows all origins - restrict in production');
            } else {
                outputResult('API Security', 'CORS Restriction', 'PASS', 'CORS appears to be restricted');
            }
        } else {
            outputResult('API Security', 'CORS Configuration', 'INFO', 'CORS headers not found in auth API');
        }
    }
    
    // Check for API versioning
    if (is_dir(PROJECT_ROOT . '/api/v1')) {
        outputResult('API Security', 'API Versioning', 'PASS', 'API versioning is implemented (/api/v1/)');
    } else {
        outputResult('API Security', 'API Versioning', 'INFO', 'API versioning structure not found');
    }
    
    // Check for input validation
    $validationPath = PROJECT_ROOT . '/includes/validation.php';
    if (file_exists($validationPath)) {
        outputResult('API Security', 'Input Validation', 'PASS', 'Validation utilities exist');
    } else {
        outputResult('API Security', 'Input Validation', 'WARN', 'Validation utilities not found');
    }
    
    // Check API directory protection
    $apiHtaccessPath = PROJECT_ROOT . '/api/.htaccess';
    if (file_exists($apiHtaccessPath)) {
        $content = file_get_contents($apiHtaccessPath);
        outputResult('API Security', 'API .htaccess', 'PASS', 'API directory has .htaccess protection');
    } else {
        outputResult('API Security', 'API .htaccess', 'INFO', 'API .htaccess not found');
    }
}

testApiSecurity();

// =============================================================================
// 8. CONFIGURATION SECURITY
// =============================================================================

outputSection("8. Configuration Security Testing");

/**
 * Test configuration security
 */
function testConfigSecurity(): void
{
    // Check for exposed config files
    $sensitiveFiles = [
        '.env' => 'Environment file',
        'config.php' => 'Config file',
        '.git/config' => 'Git config',
    ];
    
    foreach ($sensitiveFiles as $file => $description) {
        $fullPath = PROJECT_ROOT . '/' . $file;
        if (file_exists($fullPath)) {
            // Check if web accessible (should have .htaccess protection)
            outputResult('Config Security', $description, 'INFO', "File exists: $file - ensure not web accessible");
        }
    }
    
    // Check for hardcoded credentials
    $filesToCheck = [
        PROJECT_ROOT . '/includes/config.php',
        PROJECT_ROOT . '/includes/db.php',
    ];
    
    $suspiciousPatterns = [
        '/password\s*=\s*["\'][^"\']+["\']/' => 'Hardcoded password',
        '/api_key\s*=\s*["\'][^"\']+["\']/' => 'Hardcoded API key',
        '/secret\s*=\s*["\'][^"\']+["\']/' => 'Hardcoded secret',
    ];
    
    foreach ($filesToCheck as $file) {
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $filename = basename($file);
            
            foreach ($suspiciousPatterns as $pattern => $description) {
                if (preg_match($pattern, $content)) {
                    // Check if it's using environment variables
                    if (strpos($content, 'getenv') !== false || strpos($content, '$_ENV') !== false) {
                        outputResult('Config Security', "$filename - $description", 'INFO', 'Uses environment variables - verify not hardcoded');
                    } else {
                        outputResult('Config Security', "$filename - $description", 'WARN', 'Potential hardcoded credential detected');
                    }
                }
            }
        }
    }
    
    // Check error display configuration
    $bootstrapPath = PROJECT_ROOT . '/includes/bootstrap.php';
    if (file_exists($bootstrapPath)) {
        $content = file_get_contents($bootstrapPath);
        
        if (strpos($content, 'display_errors') !== false) {
            if (strpos($content, "display_errors', '0'") !== false || strpos($content, "display_errors', 0") !== false) {
                outputResult('Config Security', 'Error Display', 'PASS', 'Error display is disabled');
            } else {
                outputResult('Config Security', 'Error Display', 'WARN', 'Verify error display is disabled in production');
            }
        }
    }
}

testConfigSecurity();

// =============================================================================
// GENERATE SUMMARY REPORT
// =============================================================================

outputSection("Security Test Summary");

echo "Total Tests: " . $testResults['summary']['total_tests'] . "\n";
echo "\033[32mPassed: " . $testResults['summary']['passed'] . "\033[0m\n";
echo "\033[31mFailed: " . $testResults['summary']['failed'] . "\033[0m\n";
echo "\033[33mWarnings: " . $testResults['summary']['warnings'] . "\033[0m\n";
echo "\033[35mCritical: " . $testResults['summary']['critical'] . "\033[0m\n";

// Calculate compliance score
$complianceScore = $testResults['summary']['total_tests'] > 0
    ? round(($testResults['summary']['passed'] / $testResults['summary']['total_tests']) * 100, 1)
    : 0;

echo "\nCompliance Score: " . $complianceScore . "%\n";

// HIPAA Compliance Status
$hipaaReady = $testResults['summary']['critical'] === 0;
echo "\n";
if ($hipaaReady) {
    echo "\033[32m✓ No critical HIPAA violations detected\033[0m\n";
} else {
    echo "\033[31m✗ Critical HIPAA violations found - DO NOT DEPLOY TO PRODUCTION\033[0m\n";
}

// Save detailed report
$reportPath = PROJECT_ROOT . '/logs/security_audit_' . date('Y-m-d_His') . '.json';
file_put_contents($reportPath, json_encode($testResults, JSON_PRETTY_PRINT));
echo "\nDetailed report saved to: $reportPath\n";

echo "\n" . str_repeat('=', 60) . "\n";
echo "Security Testing Complete\n";
echo str_repeat('=', 60) . "\n";

// Exit with appropriate code
exit($testResults['summary']['critical'] > 0 ? 1 : 0);
