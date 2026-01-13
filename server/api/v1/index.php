<?php
// ========================================================================
// IMMEDIATE LOGGING - FIRST THING BEFORE ANYTHING ELSE
// ========================================================================
$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/api_debug.log';
$timestamp = date('Y-m-d H:i:s');
$logEntry = "[{$timestamp}] API/V1/INDEX.PHP HIT\n";
$logEntry .= "  REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? '(not set)') . "\n";
$logEntry .= "  REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? '(not set)') . "\n";
$logEntry .= "  SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? '(not set)') . "\n";
$logEntry .= "  PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? '(not set)') . "\n";
$logEntry .= "  __FILE__: " . __FILE__ . "\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);

/**
 * API v1 Router Entry Point
 *
 * Main entry point for all /api/v1/* requests.
 * Routes requests to appropriate endpoint handlers.
 *
 * Routes:
 * - /api/v1/auth/* → auth.php - Authentication endpoints
 * - /api/v1/patients/* → patients.php - Patient management
 * - /api/v1/encounters/* → encounters.php - Encounter management
 * - /api/v1/dot-tests/* → dot-tests.php - DOT drug testing (49 CFR Part 40)
 * - /api/v1/osha/* → osha.php - OSHA recordkeeping (29 CFR 1904)
 * - /api/v1/reports/* → reports.php - Reports and analytics
 * - /api/v1/dashboard/* → dashboard.php - Dashboard data
 * - /api/v1/health → Health check endpoint
 *
 * @package SafeShift\API\v1
 */

// ========================================================================
// BOOTSTRAP INITIALIZATION
// ========================================================================

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

use ViewModel\Core\ApiResponse;

// ========================================================================
// SESSION COOKIE DIAGNOSTIC LOGGING
// ========================================================================

/**
 * Log session diagnostic information to help debug cookie issues
 */
function logSessionDiagnostics(): void
{
    $logFile = dirname(__DIR__, 2) . '/logs/session_debug.log';
    $timestamp = date('Y-m-d H:i:s.') . substr((string)microtime(true), -3);
    
    // Get the actual session cookie name (should be SAFESHIFT_SESSION)
    $sessionCookieName = session_name();
    
    // Get incoming cookie header
    $incomingCookies = $_SERVER['HTTP_COOKIE'] ?? '(none)';
    $sessionIdFromCookie = $_COOKIE[$sessionCookieName] ?? '(not set)';
    
    // Session info (session already started by bootstrap)
    $currentSessionId = session_id();
    $sessionStatus = session_status();
    $statusText = match($sessionStatus) {
        PHP_SESSION_DISABLED => 'DISABLED',
        PHP_SESSION_NONE => 'NONE',
        PHP_SESSION_ACTIVE => 'ACTIVE',
        default => 'UNKNOWN'
    };
    
    // Request info
    $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
    $uri = $_SERVER['REQUEST_URI'] ?? 'UNKNOWN';
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '(none)';
    
    // Is this a new session or existing?
    $isNewSession = empty($_COOKIE[$sessionCookieName]) || $_COOKIE[$sessionCookieName] !== $currentSessionId;
    $sessionType = $isNewSession ? 'NEW SESSION' : 'EXISTING SESSION';
    
    // Check what's in the session
    $sessionContents = array_keys($_SESSION ?? []);
    
    // Check pending_2fa (correct key name - OTP is stored in DATABASE, not session)
    $pending2fa = $_SESSION['pending_2fa'] ?? null;
    $hasPending2FA = $pending2fa ? 'YES' : 'NO';
    $pending2faUserId = $pending2fa['user_id'] ?? '(none)';
    $pending2faUsername = $pending2fa['username'] ?? '(none)';
    $pending2faExpires = isset($pending2fa['expires']) ? date('H:i:s', $pending2fa['expires']) : '(none)';
    
    $log = "[{$timestamp}] {$sessionType}\n";
    $log .= "  Request: {$method} {$uri}\n";
    $log .= "  Origin: {$origin}\n";
    $log .= "  Session Cookie Name: {$sessionCookieName}\n";
    $log .= "  Incoming Cookie Header: {$incomingCookies}\n";
    $log .= "  {$sessionCookieName} from \$_COOKIE: {$sessionIdFromCookie}\n";
    $log .= "  Current session_id(): {$currentSessionId}\n";
    $log .= "  Session Status: {$statusText}\n";
    $log .= "  Session Keys: " . implode(', ', $sessionContents) . "\n";
    $log .= "  Has pending_2fa: {$hasPending2FA} (user_id: {$pending2faUserId}, username: {$pending2faUsername}, expires: {$pending2faExpires})\n";
    $log .= "  Note: OTP is stored in DATABASE (otp_codes table), not in session\n";
    $log .= "---\n";
    
    file_put_contents($logFile, $log, FILE_APPEND | LOCK_EX);
}

// Run diagnostics early
logSessionDiagnostics();

// ========================================================================
// CORS HEADERS FOR REACT DEVELOPMENT SERVER
// ========================================================================

/**
 * Set CORS headers for allowed origins
 */
function setCorsHeaders(): void
{
    $allowedOrigins = [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:3000',
        'http://127.0.0.1:3000'
    ];
    
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    if (in_array($origin, $allowedOrigins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-XSRF-Token, Authorization');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Max-Age: 86400'); // Cache preflight for 24 hours
    }
}

// ========================================================================
// JSON CONTENT TYPE
// ========================================================================

header('Content-Type: application/json; charset=utf-8');

// Set CORS headers
setCorsHeaders();

// ========================================================================
// HANDLE PREFLIGHT (OPTIONS) REQUESTS
// ========================================================================

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ========================================================================
// ERROR HANDLING
// ========================================================================

// Set up exception handler for uncaught exceptions
set_exception_handler(function (Throwable $e) {
    // Log the error
    error_log('API Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    
    // Don't expose internal errors in production
    $isDev = defined('ENVIRONMENT') && ENVIRONMENT === 'development';
    $message = $isDev ? $e->getMessage() : 'Internal server error';
    
    ApiResponse::send(ApiResponse::serverError($message), 500);
});

// Set up error handler to convert errors to exceptions
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// ========================================================================
// REQUEST PARSING
// ========================================================================

/**
 * Parse the request URI and extract the API path
 * 
 * @return array [path, segments]
 */
function parseRequestUri(): array
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    
    // Remove query string
    $path = parse_url($requestUri, PHP_URL_PATH);
    
    // Remove /api/v1 prefix
    $basePath = '/api/v1';
    if (strpos($path, $basePath) === 0) {
        $path = substr($path, strlen($basePath));
    }
    
    // Normalize path
    $path = '/' . trim($path, '/');
    
    // Split into segments
    $segments = array_filter(explode('/', $path));
    $segments = array_values($segments); // Re-index
    
    return [$path, $segments];
}

/**
 * Get the HTTP method
 * 
 * @return string
 */
function getMethod(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

// ========================================================================
// ROUTING
// ========================================================================

/**
 * Route the request to the appropriate handler
 */
function routeRequest(): void
{
    [$path, $segments] = parseRequestUri();
    $method = getMethod();
    
    // Get the first segment (resource type)
    $resource = $segments[0] ?? '';
    
    // Get sub-path (everything after the resource)
    $subPath = '';
    if (count($segments) > 1) {
        $subPath = implode('/', array_slice($segments, 1));
    }
    
    // Route based on resource
    switch ($resource) {
        case 'auth':
            routeToAuth($subPath, $method);
            break;
            
        case 'patients':
            routeToPatients($subPath, $method);
            break;
            
        case 'encounters':
            routeToEncounters($subPath, $method);
            break;
            
        case 'dot-tests':
            routeToDotTests($subPath, $method);
            break;
            
        case 'osha':
            routeToOsha($subPath, $method);
            break;
            
        case 'reports':
            routeToReports($subPath, $method);
            break;
            
        case 'dashboard':
            routeToDashboard($subPath, $method);
            break;
            
        case 'notifications':
            routeToNotifications($subPath, $method);
            break;
            
        case 'admin':
            routeToAdmin($subPath, $method);
            break;
            
        case 'superadmin':
            routeToSuperAdmin($subPath, $method);
            break;
            
        case 'registration':
            routeToRegistration($subPath, $method);
            break;
            
        case 'clinicalprovider':
            routeToClinicalProvider($subPath, $method);
            break;
            
        case 'privacy':
            routeToPrivacy($subPath, $method);
            break;
            
        case 'security':
            routeToSecurity($subPath, $method);
            break;
            
        case 'doctor':
            routeToDoctor($subPath, $method);
            break;
            
        case 'supermanager':
            routeToSuperManager($subPath, $method);
            break;
            
        case 'clinics':
            routeToClinics($subPath, $method);
            break;
            
        case 'sms':
            routeToSms($subPath, $method);
            break;
            
        case 'disclosures':
            routeToDisclosures($subPath, $method);
            break;
            
        case 'video':
            routeToVideo($subPath, $method);
            break;
            
        case 'user':
            routeToUser($subPath, $method);
            break;
            
        case 'health':
            handleHealthCheck($method);
            break;
            
        case '':
            handleApiInfo($method);
            break;
            
        default:
            ApiResponse::send(ApiResponse::notFound("Resource '/{$resource}' not found"), 404);
    }
}

/**
 * Route to auth endpoints
 */
function routeToAuth(string $subPath, string $method): void
{
    require_once __DIR__ . '/auth.php';
    handleAuthRoute($subPath, $method);
}

/**
 * Route to patients endpoints (placeholder)
 */
function routeToPatients(string $subPath, string $method): void
{
    $patientsFile = __DIR__ . '/patients.php';
    
    if (file_exists($patientsFile)) {
        require_once $patientsFile;
        if (function_exists('handlePatientsRoute')) {
            handlePatientsRoute($subPath, $method);
            return;
        }
    }
    
    // Placeholder response
    ApiResponse::send(ApiResponse::error('Patients API endpoint not yet implemented', [
        'info' => 'This endpoint will be available in a future update'
    ]), 501);
}

/**
 * Route to encounters endpoints
 */
function routeToEncounters(string $subPath, string $method): void
{
    $logFile = dirname(__DIR__, 2) . '/logs/encounters_debug.log';
    $log = function($msg) use ($logFile) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - $msg\n", FILE_APPEND);
    };
    
    $log("========== routeToEncounters called: subPath=$subPath, method=$method ==========");
    
    $encountersFile = __DIR__ . '/encounters.php';
    $log("encounters.php path: $encountersFile");
    $log("File exists: " . (file_exists($encountersFile) ? 'YES' : 'NO'));
    
    if (file_exists($encountersFile)) {
        $log("About to require_once encounters.php");
        
        // Capture any errors during require
        set_error_handler(function($errno, $errstr, $errfile, $errline) use ($log) {
            $log("PHP Error during require: [$errno] $errstr in $errfile:$errline");
            return false;
        });
        
        try {
            require_once $encountersFile;
            $log("require_once completed successfully");
        } catch (\Throwable $e) {
            $log("EXCEPTION during require: " . $e->getMessage());
            $log("Exception file: " . $e->getFile() . ":" . $e->getLine());
            $log("Stack trace: " . $e->getTraceAsString());
        }
        
        restore_error_handler();
        
        $log("handleEncountersRoute exists: " . (function_exists('handleEncountersRoute') ? 'YES' : 'NO'));
        
        if (function_exists('handleEncountersRoute')) {
            $log("Calling handleEncountersRoute...");
            handleEncountersRoute($subPath, $method);
            $log("handleEncountersRoute returned");
            return;
        } else {
            $log("FAILED: handleEncountersRoute not defined after require!");
            
            // Check what functions ARE defined from encounters.php
            $definedFuncs = get_defined_functions()['user'];
            $encounterFuncs = array_filter($definedFuncs, fn($f) => stripos($f, 'encounter') !== false);
            $log("Encounter-related functions defined: " . implode(', ', $encounterFuncs));
        }
    } else {
        $log("FILE NOT FOUND: $encountersFile");
    }
    
    $log("Returning 501 not implemented response");
    ApiResponse::send(ApiResponse::error('Encounters API endpoint not yet implemented', [], 'ERROR', 501));
}

/**
 * Route to DOT tests endpoints
 */
function routeToDotTests(string $subPath, string $method): void
{
    $dotTestsFile = __DIR__ . '/dot-tests.php';
    
    if (file_exists($dotTestsFile)) {
        require_once $dotTestsFile;
        if (function_exists('handleDotTestsRoute')) {
            handleDotTestsRoute($subPath, $method);
            return;
        }
    }
    
    // Placeholder response
    ApiResponse::send(ApiResponse::error('DOT Tests API endpoint not yet implemented', [
        'info' => 'This endpoint will be available in a future update'
    ]), 501);
}

/**
 * Route to OSHA endpoints
 */
function routeToOsha(string $subPath, string $method): void
{
    $oshaFile = __DIR__ . '/osha.php';
    
    if (file_exists($oshaFile)) {
        require_once $oshaFile;
        if (function_exists('handleOshaRoute')) {
            handleOshaRoute($subPath, $method);
            return;
        }
    }
    
    // Placeholder response
    ApiResponse::send(ApiResponse::error('OSHA API endpoint not yet implemented', [
        'info' => 'This endpoint will be available in a future update'
    ]), 501);
}

/**
 * Route to Reports endpoints
 */
function routeToReports(string $subPath, string $method): void
{
    $reportsFile = __DIR__ . '/reports.php';
    
    if (file_exists($reportsFile)) {
        require_once $reportsFile;
        if (function_exists('handleReportsRoute')) {
            handleReportsRoute($subPath, $method);
            return;
        }
    }
    
    // Placeholder response
    ApiResponse::send(ApiResponse::error('Reports API endpoint not yet implemented', [
        'info' => 'This endpoint will be available in a future update'
    ]), 501);
}

/**
 * Route to Dashboard endpoints
 */
function routeToDashboard(string $subPath, string $method): void
{
    $dashboardFile = __DIR__ . '/dashboard.php';
    
    if (file_exists($dashboardFile)) {
        require_once $dashboardFile;
        if (function_exists('handleDashboardRoute')) {
            handleDashboardRoute($subPath, $method);
            return;
        }
    }
    
    // Placeholder response
    ApiResponse::send(ApiResponse::error('Dashboard API endpoint not yet implemented', [
        'info' => 'This endpoint will be available in a future update'
    ]), 501);
}

/**
 * Route to Notifications endpoints
 */
function routeToNotifications(string $subPath, string $method): void
{
    require_once __DIR__ . '/notifications.php';
    handleNotificationsRoute($subPath, $method);
}

/**
 * Route to Admin endpoints
 */
function routeToAdmin(string $subPath, string $method): void
{
    $adminFile = __DIR__ . '/admin.php';
    
    if (file_exists($adminFile)) {
        require_once $adminFile;
        if (function_exists('handleAdminRoute')) {
            handleAdminRoute($subPath, $method);
            return;
        }
    }
    
    // Placeholder response
    ApiResponse::send(ApiResponse::error('Admin API endpoint not yet implemented', [
        'info' => 'This endpoint will be available in a future update'
    ]), 501);
}

/**
 * Route to SuperAdmin endpoints
 */
function routeToSuperAdmin(string $subPath, string $method): void
{
    $superadminFile = __DIR__ . '/superadmin.php';
    
    if (file_exists($superadminFile)) {
        require_once $superadminFile;
        if (function_exists('handleSuperAdminRoute')) {
            handleSuperAdminRoute($subPath, $method);
            return;
        }
    }
    
    // Placeholder response
    ApiResponse::send(ApiResponse::error('SuperAdmin API endpoint not yet implemented', [
        'info' => 'This endpoint will be available in a future update'
    ]), 501);
}

/**
 * Route to Registration endpoints
 */
function routeToRegistration(string $subPath, string $method): void
{
    $registrationFile = __DIR__ . '/registration.php';
    
    if (file_exists($registrationFile)) {
        require_once $registrationFile;
        if (function_exists('handleRegistrationRoute')) {
            handleRegistrationRoute($subPath, $method);
            return;
        }
    }
    
    // Placeholder response
    ApiResponse::send(ApiResponse::error('Registration API endpoint not yet implemented', [
        'info' => 'This endpoint will be available in a future update'
    ]), 501);
}

/**
 * Route to Clinical Provider endpoints
 */
function routeToClinicalProvider(string $subPath, string $method): void
{
    $clinicalProviderFile = __DIR__ . '/clinicalprovider.php';
    
    if (file_exists($clinicalProviderFile)) {
        require_once $clinicalProviderFile;
        if (function_exists('handleClinicalProviderRoute')) {
            handleClinicalProviderRoute($subPath, $method);
            return;
        }
    }
    
    // Placeholder response
    ApiResponse::send(ApiResponse::error('Clinical Provider API endpoint not yet implemented', [
        'info' => 'This endpoint will be available in a future update'
    ]), 501);
}

/**
 * Route to Privacy Officer endpoints
 */
function routeToPrivacy(string $subPath, string $method): void
{
    $privacyFile = __DIR__ . '/privacy.php';
    
    if (file_exists($privacyFile)) {
        require_once $privacyFile;
        if (function_exists('handlePrivacyRoute')) {
            handlePrivacyRoute($subPath, $method);
            return;
        }
    }
    
    // Placeholder response
    ApiResponse::send(ApiResponse::error('Privacy Officer API endpoint not yet implemented', [
        'info' => 'This endpoint will be available in a future update'
    ]), 501);
}

/**
 * Route to Security Officer endpoints
 */
function routeToSecurity(string $subPath, string $method): void
{
    $securityFile = __DIR__ . '/security.php';
    
    if (file_exists($securityFile)) {
        require_once $securityFile;
        if (function_exists('handleSecurityRoute')) {
            handleSecurityRoute($subPath, $method);
            return;
        }
    }
    
    // Placeholder response
    ApiResponse::send(ApiResponse::error('Security Officer API endpoint not yet implemented', [
        'info' => 'This endpoint will be available in a future update'
    ]), 501);
}

/**
 * Route to Doctor (MRO) endpoints
 */
function routeToDoctor(string $subPath, string $method): void
{
    $doctorFile = __DIR__ . '/doctor.php';
    
    if (file_exists($doctorFile)) {
        require_once $doctorFile;
        if (function_exists('handleDoctorRoute')) {
            handleDoctorRoute($subPath, $method);
            return;
        }
    }
    
    // Placeholder response
    ApiResponse::send(ApiResponse::error('Doctor API endpoint not yet implemented', [
        'info' => 'This endpoint will be available in a future update'
    ]), 501);
}

/**
 * Route to SuperManager endpoints
 */
function routeToSuperManager(string $subPath, string $method): void
{
    $supermanagerFile = __DIR__ . '/supermanager.php';
    
    if (file_exists($supermanagerFile)) {
        require_once $supermanagerFile;
        if (function_exists('handleSuperManagerRoute')) {
            handleSuperManagerRoute($subPath, $method);
            return;
        }
    }
    
    // Placeholder response
    ApiResponse::send(ApiResponse::error('SuperManager API endpoint not yet implemented', [
        'info' => 'This endpoint will be available in a future update'
    ]), 501);
}

/**
 * Route to Clinics endpoints
 */
function routeToClinics(string $subPath, string $method): void
{
    $clinicsFile = __DIR__ . '/clinics.php';
    
    if (file_exists($clinicsFile)) {
        require_once $clinicsFile;
        if (function_exists('handleClinicsRoute')) {
            handleClinicsRoute($subPath, $method);
            return;
        }
    }
    
    // Placeholder response
    ApiResponse::send(ApiResponse::error('Clinics API endpoint not yet implemented', [
        'info' => 'This endpoint will be available in a future update'
    ]), 501);
}

/**
 * Route to SMS endpoints
 */
function routeToSms(string $subPath, string $method): void
{
    // Route based on subpath
    if ($subPath === 'send-reminder') {
        require_once __DIR__ . '/sms/send-reminder.php';
        return;
    }
    
    // Default SMS endpoint info
    if (empty($subPath) && $method === 'GET') {
        ApiResponse::send(ApiResponse::success([
            'endpoints' => [
                'POST /api/v1/sms/send-reminder' => 'Send appointment reminder SMS'
            ]
        ], 'SMS API endpoints'), 200);
        return;
    }
    
    ApiResponse::send(ApiResponse::notFound('SMS endpoint not found'), 404);
}

/**
 * Route to Disclosures endpoints
 */
function routeToDisclosures(string $subPath, string $method): void
{
    $disclosuresFile = __DIR__ . '/disclosures.php';
    
    if (file_exists($disclosuresFile)) {
        require_once $disclosuresFile;
        if (function_exists('handleDisclosuresRoute')) {
            handleDisclosuresRoute($subPath, $method);
            return;
        }
    }
    
    // Placeholder response
    ApiResponse::send(ApiResponse::error('Disclosures API endpoint not yet implemented', [
        'info' => 'This endpoint will be available in a future update'
    ]), 501);
}

/**
 * Route to Video Meeting endpoints
 * Proxies requests to the existing api/video/ handlers
 */
function routeToVideo(string $subPath, string $method): void
{
    // Map the subPath to the appropriate video endpoint file
    $videoDir = dirname(__DIR__) . '/video';
    
    // Parse the subPath to determine which file to use
    // e.g., 'my-meetings' -> 'my-meetings.php'
    // e.g., 'create' -> 'create-meeting.php'
    // e.g., 'meetings/123/participants' -> 'participants.php' with meeting_id=123
    
    $segments = array_filter(explode('/', $subPath));
    $segments = array_values($segments); // Re-index
    
    // Determine the endpoint file based on the first segment
    $endpoint = $segments[0] ?? '';
    
    // Map frontend endpoint names to backend file names
    $fileMap = [
        'create' => 'create-meeting.php',
        'validate-token' => 'validate-token.php',
        'join' => 'join-meeting.php',
        'leave' => 'leave-meeting.php',
        'end' => 'end-meeting.php',
        'my-meetings' => 'my-meetings.php',
        'chat' => 'chat-message.php',
        'meetings' => null, // Special handling for /meetings/{id}/...
    ];
    
    // Handle /video/meetings/{id}/... patterns
    if ($endpoint === 'meetings' && count($segments) >= 2) {
        $meetingId = $segments[1];
        $_GET['meeting_id'] = $meetingId;
        $_REQUEST['meeting_id'] = $meetingId;
        
        // Determine sub-endpoint (e.g., 'participants', 'chat', 'link')
        $subEndpoint = $segments[2] ?? '';
        
        switch ($subEndpoint) {
            case 'participants':
                $targetFile = $videoDir . '/participants.php';
                break;
            case 'chat':
                $targetFile = $videoDir . '/chat-history.php';
                break;
            case 'link':
                $targetFile = $videoDir . '/meeting-link.php';
                break;
            default:
                // Get single meeting - not implemented in existing backend
                ApiResponse::send(ApiResponse::notFound("Video endpoint '/meetings/{$meetingId}/{$subEndpoint}' not found"), 404);
                return;
        }
    } elseif (isset($fileMap[$endpoint]) && $fileMap[$endpoint] !== null) {
        $targetFile = $videoDir . '/' . $fileMap[$endpoint];
    } elseif ($endpoint === 'chat' && isset($segments[1]) && $segments[1] === 'send') {
        // Handle /video/chat/send
        $targetFile = $videoDir . '/chat-message.php';
    } else {
        // Check if endpoint file exists directly
        $possibleFile = $videoDir . '/' . $endpoint . '.php';
        if (file_exists($possibleFile)) {
            $targetFile = $possibleFile;
        } else {
            ApiResponse::send(ApiResponse::notFound("Video endpoint '/{$endpoint}' not found"), 404);
            return;
        }
    }
    
    if (!file_exists($targetFile)) {
        ApiResponse::send(ApiResponse::notFound("Video endpoint not found"), 404);
        return;
    }
    
    // Include and execute the video endpoint
    require_once $targetFile;
}

/**
 * Route to User endpoints (preferences, settings, etc.)
 */
function routeToUser(string $subPath, string $method): void
{
    // Parse the subPath to determine which endpoint to call
    // e.g., 'preferences/timeout' -> api/user/preferences/timeout.php
    
    $segments = array_filter(explode('/', $subPath));
    $segments = array_values($segments); // Re-index
    
    // Build the target file path based on segments
    $userDir = dirname(__DIR__) . '/user';
    
    // Handle preferences endpoints
    if (($segments[0] ?? '') === 'preferences') {
        $preference = $segments[1] ?? '';
        
        switch ($preference) {
            case 'timeout':
                $targetFile = $userDir . '/preferences/timeout.php';
                break;
            default:
                ApiResponse::send(ApiResponse::notFound("User preference endpoint '/{$preference}' not found"), 404);
                return;
        }
    } else {
        // Handle other user endpoints as they're added
        $endpoint = $segments[0] ?? '';
        
        if (empty($endpoint)) {
            // Return user endpoints info
            if ($method === 'GET') {
                ApiResponse::send(ApiResponse::success([
                    'endpoints' => [
                        'GET /api/v1/user/preferences/timeout' => 'Get user idle timeout preference',
                        'PUT /api/v1/user/preferences/timeout' => 'Update user idle timeout preference'
                    ]
                ], 'User API endpoints'), 200);
                return;
            }
        }
        
        ApiResponse::send(ApiResponse::notFound("User endpoint '/{$endpoint}' not found"), 404);
        return;
    }
    
    if (!file_exists($targetFile)) {
        ApiResponse::send(ApiResponse::notFound("User endpoint not found"), 404);
        return;
    }
    
    // Include and execute the user endpoint
    require_once $targetFile;
}

/**
 * Handle health check endpoint
 */
function handleHealthCheck(string $method): void
{
    if ($method !== 'GET') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    $dbAvailable = $GLOBALS['db_available'] ?? false;
    $status = $dbAvailable ? 'healthy' : 'degraded';
    $statusCode = $dbAvailable ? 200 : 503;
    
    ApiResponse::send(ApiResponse::success([
        'status' => $status,
        'version' => 'v1',
        'database' => $dbAvailable ? 'connected' : 'unavailable',
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z')
    ], 'API is ' . $status), $statusCode);
}

/**
 * Handle API info/root endpoint
 */
function handleApiInfo(string $method): void
{
    if ($method !== 'GET') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    ApiResponse::send(ApiResponse::success([
        'name' => 'SafeShift EHR API',
        'version' => 'v1',
        'endpoints' => [
            '/api/v1/auth' => 'Authentication endpoints (login, logout, 2FA, session)',
            '/api/v1/patients' => 'Patient management (CRUD, search, encounters)',
            '/api/v1/encounters' => 'Encounter management (CRUD, vitals, sign, amend)',
            '/api/v1/dot-tests' => 'DOT drug testing (49 CFR Part 40 compliant)',
            '/api/v1/osha' => 'OSHA recordkeeping (29 CFR 1904, 300/300A logs)',
            '/api/v1/reports' => 'Reports and analytics (safety, compliance, export)',
            '/api/v1/dashboard' => 'Dashboard data (role-specific stats, alerts)',
            '/api/v1/notifications' => 'Notifications management (inbox, preferences)',
            '/api/v1/admin' => 'Admin dashboard (compliance, training, OSHA)',
            '/api/v1/superadmin' => 'Super admin (users, clinics, audit, security)',
            '/api/v1/supermanager' => 'SuperManager dashboard (multi-clinic oversight, staff management, approvals)',
            '/api/v1/privacy' => 'Privacy officer dashboard (HIPAA compliance, PHI access, consents)',
            '/api/v1/security' => 'Security officer dashboard (audit events, MFA, failed logins, sessions)',
            '/api/v1/health' => 'Health check endpoint'
        ],
        'documentation' => '/docs/API.md'
    ], 'SafeShift EHR API v1'), 200);
}

// ========================================================================
// MAIN EXECUTION
// ========================================================================

try {
    routeRequest();
} catch (Throwable $e) {
    // This shouldn't be reached due to the exception handler above,
    // but provides an extra safety net
    error_log('Uncaught API error: ' . $e->getMessage());
    
    $isDev = defined('ENVIRONMENT') && ENVIRONMENT === 'development';
    ApiResponse::send(ApiResponse::serverError(
        $isDev ? $e->getMessage() : 'Internal server error'
    ), 500);
}
