<?php
/**
 * Authentication API Endpoints
 * 
 * Handles all /api/v1/auth/* routes for the SafeShift EHR React frontend.
 * 
 * Endpoints:
 * - POST /api/v1/auth/login - Accept JSON { username, password }
 * - POST /api/v1/auth/verify-2fa - Accept JSON { code, trustDevice? }
 * - POST /api/v1/auth/resend-otp - Resend OTP
 * - POST /api/v1/auth/logout - Destroy session
 * - GET /api/v1/auth/current-user - Return logged in user
 * - GET /api/v1/auth/csrf-token - Return CSRF token
 * - POST /api/v1/auth/refresh-session - Extend session
 * - GET /api/v1/auth/session-status - Check session validity
 * 
 * @package SafeShift\API\v1
 */

// This file is included by the router, bootstrap should already be loaded
// but ensure it's available just in case it's accessed directly
if (!defined('CSRF_TOKEN_NAME')) {
    require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
}

use ViewModel\Auth\AuthViewModel;
use ViewModel\Core\ApiResponse;

// ========================================================================
// RATE LIMITING
// ========================================================================

/**
 * Simple rate limiter for authentication endpoints
 * Uses session-based tracking with IP fallback
 */
function checkRateLimit(string $action, int $maxAttempts = 5, int $windowSeconds = 300): bool
{
    $key = 'rate_limit_' . $action . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    
    // Use session for rate limiting
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'count' => 0,
            'window_start' => time()
        ];
    }
    
    $data = $_SESSION[$key];
    
    // Reset if window expired
    if (time() - $data['window_start'] > $windowSeconds) {
        $_SESSION[$key] = [
            'count' => 1,
            'window_start' => time()
        ];
        return true;
    }
    
    // Check limit
    if ($data['count'] >= $maxAttempts) {
        return false;
    }
    
    // Increment counter
    $_SESSION[$key]['count']++;
    return true;
}

/**
 * Get remaining time until rate limit resets
 */
function getRateLimitRetryAfter(string $action, int $windowSeconds = 300): int
{
    $key = 'rate_limit_' . $action . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    
    if (!isset($_SESSION[$key])) {
        return 0;
    }
    
    $windowStart = $_SESSION[$key]['window_start'] ?? time();
    $elapsed = time() - $windowStart;
    
    return max(0, $windowSeconds - $elapsed);
}

// ========================================================================
// CSRF VALIDATION
// ========================================================================

/**
 * Validate CSRF token for POST requests
 * Skipped for login and csrf-token endpoints
 */
function validateCsrf(): bool
{
    // Get token from header or body
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] 
        ?? $_SERVER['HTTP_X_XSRF_TOKEN']
        ?? getJsonInput()['csrf_token'] 
        ?? '';
    
    if (empty($token)) {
        return false;
    }
    
    return \App\Core\Session::validateCsrfToken($token);
}

// ========================================================================
// INPUT HELPERS
// ========================================================================

/**
 * Get JSON input from request body
 */
function getJsonInput(): array
{
    static $input = null;
    
    if ($input === null) {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true) ?? [];
    }
    
    return $input;
}

// ========================================================================
// ROUTE HANDLING
// ========================================================================

/**
 * Handle auth API routes
 * 
 * @param string $path The path after /auth/ (e.g., 'login', 'verify-2fa')
 * @param string $method HTTP method
 */
function handleAuthRoute(string $path, string $method): void
{
    // EARLY ROUTE LOGGING - Log ALL incoming auth requests
    $debugFile = dirname(__DIR__, 2) . '/logs/session_debug.log';
    file_put_contents($debugFile, "[" . date('Y-m-d H:i:s.') . substr(microtime(), 2, 3) . "] AUTH ROUTE ENTRY - path: '{$path}', method: {$method}\n", FILE_APPEND | LOCK_EX);
    
    $authViewModel = new AuthViewModel();
    
    // Route to appropriate handler
    switch ($path) {
        case 'login':
            handleLogin($authViewModel, $method);
            break;
            
        case 'verify-2fa':
            // Log that we matched the verify-2fa case
            file_put_contents($debugFile, "[" . date('Y-m-d H:i:s.') . substr(microtime(), 2, 3) . "] MATCHED verify-2fa case - calling handleVerify2FA\n", FILE_APPEND | LOCK_EX);
            handleVerify2FA($authViewModel, $method);
            break;
            
        case 'resend-otp':
            handleResendOtp($authViewModel, $method);
            break;
            
        case 'logout':
            handleLogout($authViewModel, $method);
            break;
            
        case 'current-user':
            handleCurrentUser($authViewModel, $method);
            break;
            
        case 'csrf-token':
            handleCsrfToken($authViewModel, $method);
            break;
            
        case 'refresh-session':
            handleRefreshSession($authViewModel, $method);
            break;
            
        case 'session-status':
            handleSessionStatus($authViewModel, $method);
            break;
            
        case 'ping-activity':
            handlePingActivity($authViewModel, $method);
            break;
            
        case 'active-sessions':
            handleActiveSessions($authViewModel, $method);
            break;
            
        case 'logout-session':
            handleLogoutSession($authViewModel, $method);
            break;
            
        case 'logout-all':
            handleLogoutAll($authViewModel, $method);
            break;
            
        case 'logout-everywhere':
            handleLogoutEverywhere($authViewModel, $method);
            break;
            
        default:
            ApiResponse::send(ApiResponse::notFound('Auth endpoint not found'), 404);
    }
}

/**
 * POST /api/v1/auth/login
 * Accept JSON { username, password }
 */
function handleLogin(AuthViewModel $vm, string $method): void
{
    if ($method !== 'POST') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    // Rate limiting for login attempts (5 attempts per 5 minutes)
    if (!checkRateLimit('login', 5, 300)) {
        $retryAfter = getRateLimitRetryAfter('login', 300);
        ApiResponse::send(ApiResponse::rateLimited('Too many login attempts. Please try again later.', $retryAfter), 429);
        return;
    }
    
    // No CSRF validation for login (user doesn't have session yet)
    
    $input = getJsonInput();
    
    // Validate required fields
    if (empty($input['username']) || empty($input['password'])) {
        ApiResponse::send(ApiResponse::validationError([
            'username' => empty($input['username']) ? ['Username is required'] : [],
            'password' => empty($input['password']) ? ['Password is required'] : []
        ]), 422);
        return;
    }
    
    $result = $vm->login([
        'username' => $input['username'],
        'password' => $input['password']
    ]);
    
    // POST-LOGIN DIAGNOSTIC: Log session state AFTER login completes
    logPostLoginDiagnostic($result);
    
    $statusCode = $result['success'] ? 200 : 401;
    ApiResponse::send($result, $statusCode);
}

/**
 * Log session state after login for debugging
 */
function logPostLoginDiagnostic(array $result): void
{
    $logFile = dirname(__DIR__, 2) . '/logs/session_debug.log';
    $timestamp = date('Y-m-d H:i:s.') . substr(microtime(), 2, 3);
    
    $pending2fa = $_SESSION['pending_2fa'] ?? null;
    
    $log = "[{$timestamp}] POST-LOGIN DIAGNOSTIC\n";
    $log .= "  Session ID: " . session_id() . "\n";
    $log .= "  Login Result: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    $log .= "  Stage: " . ($result['data']['stage'] ?? 'unknown') . "\n";
    
    if ($pending2fa) {
        $log .= "  pending_2fa FOUND:\n";
        $log .= "    user_id: " . ($pending2fa['user_id'] ?? '(none)') . "\n";
        $log .= "    username: " . ($pending2fa['username'] ?? '(none)') . "\n";
        $log .= "    expires: " . ($pending2fa['expires'] ?? '(none)') . "\n";
    } else {
        $log .= "  pending_2fa: NOT SET\n";
    }
    
    $log .= "  Session Keys: " . implode(', ', array_keys($_SESSION)) . "\n";
    $log .= "---\n";
    
    file_put_contents($logFile, $log, FILE_APPEND | LOCK_EX);
}

/**
 * POST /api/v1/auth/verify-2fa
 * Accept JSON { code, trustDevice? }
 */
function handleVerify2FA(AuthViewModel $vm, string $method): void
{
    // VERY EARLY LOGGING - First thing before ANY other code
    $debugFile = __DIR__ . '/../../logs/session_debug.log';
    file_put_contents($debugFile, "[" . date('Y-m-d H:i:s.') . substr(microtime(), 2, 3) . "] VERIFY-2FA ROUTE HIT - Method: {$method}\n", FILE_APPEND | LOCK_EX);
    
    if ($method !== 'POST') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    $input = getJsonInput();
    $otpCode = $input['code'] ?? '';
    
    // PRE-VERIFY DIAGNOSTIC: Log session state before verification with OTP code
    logVerify2FADiagnostic('PRE', null, $otpCode);
    
    // Rate limiting for 2FA verification (10 attempts per 10 minutes)
    if (!checkRateLimit('verify2fa', 10, 600)) {
        $retryAfter = getRateLimitRetryAfter('verify2fa', 600);
        logVerify2FADiagnostic('RATE-LIMITED', ['success' => false, 'message' => 'Rate limited'], $otpCode);
        ApiResponse::send(ApiResponse::rateLimited('Too many verification attempts. Please try again later.', $retryAfter), 429);
        return;
    }
    
    // No CSRF validation for 2FA (session not fully established)
    
    if (empty($otpCode)) {
        logVerify2FADiagnostic('VALIDATION-FAILED', ['success' => false, 'message' => 'Empty code'], $otpCode);
        ApiResponse::send(ApiResponse::validationError([
            'code' => ['Verification code is required']
        ]), 422);
        return;
    }
    
    $result = $vm->verify2FA($otpCode);
    
    // POST-VERIFY DIAGNOSTIC: Log session state after verification
    logVerify2FADiagnostic('POST', $result, $otpCode);
    
    $statusCode = $result['success'] ? 200 : 401;
    ApiResponse::send($result, $statusCode);
}

/**
 * Log session state for verify-2fa debugging
 */
function logVerify2FADiagnostic(string $phase, ?array $result = null, string $otpCode = ''): void
{
    $logFile = dirname(__DIR__, 2) . '/logs/session_debug.log';
    $timestamp = date('Y-m-d H:i:s.') . substr(microtime(), 2, 3);
    
    $pending2fa = $_SESSION['pending_2fa'] ?? null;
    
    // Mask OTP code for security (show last 3 digits)
    $maskedOtp = strlen($otpCode) >= 3 ? '***' . substr($otpCode, -3) : '***';
    
    $log = "[{$timestamp}] VERIFY-2FA {$phase}\n";
    $log .= "  Session ID: " . session_id() . "\n";
    $log .= "  Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'INACTIVE') . "\n";
    $log .= "  Submitted OTP: {$maskedOtp} (length: " . strlen($otpCode) . ")\n";
    
    if ($pending2fa) {
        $log .= "  pending_2fa FOUND:\n";
        $log .= "    user_id: " . ($pending2fa['user_id'] ?? '(none)') . "\n";
        $log .= "    username: " . ($pending2fa['username'] ?? '(none)') . "\n";
        $log .= "    email: " . ($pending2fa['email'] ?? '(none)') . "\n";
        $log .= "    expires: " . ($pending2fa['expires'] ?? '(none)') . "\n";
        if (isset($pending2fa['expires'])) {
            $expiresIn = $pending2fa['expires'] - time();
            $log .= "    expires_in: {$expiresIn} seconds\n";
            $log .= "    expired: " . ($pending2fa['expires'] < time() ? 'YES' : 'NO') . "\n";
        }
    } else {
        $log .= "  pending_2fa: NOT SET (THIS IS THE PROBLEM!)\n";
    }
    
    if ($result !== null) {
        $log .= "  Verify Result: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
        $log .= "  Message: " . ($result['message'] ?? 'none') . "\n";
        if (isset($result['errors'])) {
            $log .= "  Errors: " . json_encode($result['errors']) . "\n";
        }
    }
    
    $log .= "  Session Keys: " . implode(', ', array_keys($_SESSION)) . "\n";
    $log .= "  Request Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'unknown') . "\n";
    $log .= "  Cookie Header: " . (isset($_SERVER['HTTP_COOKIE']) ? 'present' : 'missing') . "\n";
    $log .= "---\n";
    
    file_put_contents($logFile, $log, FILE_APPEND | LOCK_EX);
}

/**
 * POST /api/v1/auth/resend-otp
 * Resend OTP code
 */
function handleResendOtp(AuthViewModel $vm, string $method): void
{
    if ($method !== 'POST') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    // Rate limiting for OTP resend (3 attempts per 5 minutes)
    if (!checkRateLimit('resend_otp', 3, 300)) {
        $retryAfter = getRateLimitRetryAfter('resend_otp', 300);
        ApiResponse::send(ApiResponse::rateLimited('Too many resend requests. Please wait before requesting another code.', $retryAfter), 429);
        return;
    }
    
    // No CSRF validation for OTP resend (session not fully established)
    
    $result = $vm->resendOtp();
    
    $statusCode = $result['success'] ? 200 : 400;
    ApiResponse::send($result, $statusCode);
}

/**
 * POST /api/v1/auth/logout
 * Destroy session
 */
function handleLogout(AuthViewModel $vm, string $method): void
{
    if ($method !== 'POST') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    // Validate CSRF for logout
    if (!validateCsrf()) {
        ApiResponse::send(ApiResponse::error('Invalid security token'), 403);
        return;
    }
    
    $result = $vm->logout();
    ApiResponse::send($result, 200);
}

/**
 * GET /api/v1/auth/current-user
 * Return logged in user data
 */
function handleCurrentUser(AuthViewModel $vm, string $method): void
{
    if ($method !== 'GET') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    $user = $vm->getCurrentUser();
    
    if (!$user) {
        ApiResponse::send(ApiResponse::unauthorized('Not authenticated'), 401);
        return;
    }
    
    ApiResponse::send(ApiResponse::success($user, 'User retrieved successfully'), 200);
}

/**
 * GET /api/v1/auth/csrf-token
 * Return CSRF token for the current session
 */
function handleCsrfToken(AuthViewModel $vm, string $method): void
{
    if ($method !== 'GET') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    $token = $vm->getCsrfToken();
    
    ApiResponse::send(ApiResponse::success([
        'token' => $token
    ], 'CSRF token retrieved'), 200);
}

/**
 * POST /api/v1/auth/refresh-session
 * Extend session timeout
 */
function handleRefreshSession(AuthViewModel $vm, string $method): void
{
    if ($method !== 'POST') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    // Validate CSRF for refresh
    if (!validateCsrf()) {
        ApiResponse::send(ApiResponse::error('Invalid security token'), 403);
        return;
    }
    
    if (!$vm->isAuthenticated()) {
        ApiResponse::send(ApiResponse::unauthorized('Not authenticated'), 401);
        return;
    }
    
    $result = $vm->refreshSession();
    
    $statusCode = $result['success'] ? 200 : 401;
    ApiResponse::send($result, $statusCode);
}

/**
 * GET /api/v1/auth/session-status
 * Check if session is valid
 */
function handleSessionStatus(AuthViewModel $vm, string $method): void
{
    if ($method !== 'GET') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    $result = $vm->getSessionStatus();
    ApiResponse::send($result, 200);
}

/**
 * POST /api/v1/auth/ping-activity
 * Update session activity timestamp
 */
function handlePingActivity(AuthViewModel $vm, string $method): void
{
    if ($method !== 'POST') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    // Check authentication
    if (!$vm->isAuthenticated()) {
        ApiResponse::send(ApiResponse::unauthorized('Not authenticated'), 401);
        return;
    }
    
    try {
        // Get session token from session
        $sessionToken = $_SESSION['db_session_token'] ?? null;
        
        if ($sessionToken) {
            // Use database-backed session manager
            $sessionManager = \Model\Core\SessionManager::getInstance();
            $result = $sessionManager->updateActivity($sessionToken);
            
            if ($result['success']) {
                ApiResponse::send(ApiResponse::success([
                    'remaining_time' => $result['remaining_time'] ?? 0,
                    'idle_timeout' => $result['idle_timeout'] ?? 1800,
                    'expires_at' => $result['expires_at'] ?? null,
                    'session_valid' => true
                ], 'Activity updated'), 200);
            } else {
                // Database session invalid but PHP session still active
                $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 1200;
                $lastActivity = $_SESSION['_last_activity'] ?? time();
                $remaining = max(0, $timeout - (time() - $lastActivity));
                
                ApiResponse::send(ApiResponse::success([
                    'remaining_time' => $remaining,
                    'idle_timeout' => $timeout,
                    'expires_at' => gmdate('Y-m-d\TH:i:s\Z', time() + $remaining),
                    'session_valid' => true,
                    'db_session' => false
                ], 'Activity updated (fallback)'), 200);
            }
        } else {
            // No database session token - use PHP session timing
            $_SESSION['_last_activity'] = time();
            
            $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 1200;
            
            ApiResponse::send(ApiResponse::success([
                'remaining_time' => $timeout,
                'idle_timeout' => $timeout,
                'expires_at' => gmdate('Y-m-d\TH:i:s\Z', time() + $timeout),
                'session_valid' => true,
                'db_session' => false
            ], 'Activity updated'), 200);
        }
        
    } catch (Exception $e) {
        error_log('[ping-activity] Error: ' . $e->getMessage());
        ApiResponse::send(ApiResponse::serverError('Failed to update activity'), 500);
    }
}

/**
 * GET /api/v1/auth/active-sessions
 * Get user's active sessions
 */
function handleActiveSessions(AuthViewModel $vm, string $method): void
{
    if ($method !== 'GET') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    if (!$vm->isAuthenticated()) {
        ApiResponse::send(ApiResponse::unauthorized('Not authenticated'), 401);
        return;
    }
    
    try {
        $userId = $_SESSION['user']['user_id'] ?? null;
        if (!$userId) {
            ApiResponse::send(ApiResponse::unauthorized('Not authenticated'), 401);
            return;
        }
        
        $sessionManager = \Model\Core\SessionManager::getInstance();
        $sessions = $sessionManager->getUserSessions((int)$userId);
        
        ApiResponse::send(ApiResponse::success($sessions, 'Active sessions retrieved'), 200);
    } catch (Exception $e) {
        error_log('[active-sessions] Error: ' . $e->getMessage());
        ApiResponse::send(ApiResponse::serverError('Failed to get sessions'), 500);
    }
}

/**
 * POST /api/v1/auth/logout-session
 * Log out a specific session
 */
function handleLogoutSession(AuthViewModel $vm, string $method): void
{
    if ($method !== 'POST') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    if (!$vm->isAuthenticated()) {
        ApiResponse::send(ApiResponse::unauthorized('Not authenticated'), 401);
        return;
    }
    
    $input = getJsonInput();
    $sessionId = $input['session_id'] ?? null;
    
    if (!$sessionId) {
        ApiResponse::send(ApiResponse::validationError(['session_id' => ['Session ID is required']]), 422);
        return;
    }
    
    try {
        $sessionManager = \Model\Core\SessionManager::getInstance();
        $result = $sessionManager->terminateSession((int)$sessionId);
        
        if ($result) {
            ApiResponse::send(ApiResponse::success(null, 'Session terminated'), 200);
        } else {
            ApiResponse::send(ApiResponse::error('Failed to terminate session'), 400);
        }
    } catch (Exception $e) {
        error_log('[logout-session] Error: ' . $e->getMessage());
        ApiResponse::send(ApiResponse::serverError('Failed to terminate session'), 500);
    }
}

/**
 * POST /api/v1/auth/logout-all
 * Log out all sessions except current
 */
function handleLogoutAll(AuthViewModel $vm, string $method): void
{
    if ($method !== 'POST') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    if (!$vm->isAuthenticated()) {
        ApiResponse::send(ApiResponse::unauthorized('Not authenticated'), 401);
        return;
    }
    
    try {
        $userId = $_SESSION['user']['user_id'] ?? null;
        $currentToken = $_SESSION['db_session_token'] ?? null;
        
        if (!$userId) {
            ApiResponse::send(ApiResponse::unauthorized('Not authenticated'), 401);
            return;
        }
        
        $sessionManager = \Model\Core\SessionManager::getInstance();
        $count = $sessionManager->terminateAllUserSessions((int)$userId, $currentToken);
        
        ApiResponse::send(ApiResponse::success(['count' => $count], 'Logged out other sessions'), 200);
    } catch (Exception $e) {
        error_log('[logout-all] Error: ' . $e->getMessage());
        ApiResponse::send(ApiResponse::serverError('Failed to logout sessions'), 500);
    }
}

/**
 * POST /api/v1/auth/logout-everywhere
 * Log out all sessions including current
 */
function handleLogoutEverywhere(AuthViewModel $vm, string $method): void
{
    if ($method !== 'POST') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    if (!$vm->isAuthenticated()) {
        ApiResponse::send(ApiResponse::unauthorized('Not authenticated'), 401);
        return;
    }
    
    try {
        $userId = $_SESSION['user']['user_id'] ?? null;
        
        if (!$userId) {
            ApiResponse::send(ApiResponse::unauthorized('Not authenticated'), 401);
            return;
        }
        
        $sessionManager = \Model\Core\SessionManager::getInstance();
        $count = $sessionManager->terminateAllUserSessions((int)$userId);
        
        // Also destroy the PHP session
        $vm->logout();
        
        ApiResponse::send(ApiResponse::success(['count' => $count], 'Logged out everywhere'), 200);
    } catch (Exception $e) {
        error_log('[logout-everywhere] Error: ' . $e->getMessage());
        ApiResponse::send(ApiResponse::serverError('Failed to logout sessions'), 500);
    }
}

// ========================================================================
// MAIN EXECUTION
// When this file is included by the router, handleAuthRoute will be called
// If accessed directly, extract path and handle
// ========================================================================

// Check if we're being included or accessed directly
if (basename($_SERVER['SCRIPT_FILENAME']) === 'auth.php') {
    // Direct access - extract path from REQUEST_URI
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $basePath = '/api/v1/auth/';
    
    // Remove query string
    $path = parse_url($requestUri, PHP_URL_PATH);
    
    // Extract auth endpoint path
    if (strpos($path, $basePath) === 0) {
        $authPath = substr($path, strlen($basePath));
        $authPath = trim($authPath, '/');
    } else {
        $authPath = '';
    }
    
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    // Set JSON content type
    header('Content-Type: application/json; charset=utf-8');
    
    // Handle CORS for React dev server
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
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-XSRF-Token');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    }
    
    // Handle preflight requests
    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
    
    handleAuthRoute($authPath, $method);
}
