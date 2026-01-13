<?php
/**
 * Logout Everywhere API Endpoint
 * 
 * POST /api/auth/logout-everywhere
 * 
 * Logs out ALL sessions for the current user INCLUDING the current session.
 * Used for security purposes when user suspects account compromise.
 * After this call, the user will be logged out and need to re-authenticate.
 * 
 * Response:
 * - success: Whether the operation was successful
 * - count: Number of sessions that were terminated
 * - message: Status message
 * 
 * @package SafeShift\API\Auth
 */

// Load bootstrap
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

use ViewModel\Core\ApiResponse;
use Model\Core\SessionManager;

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
$allowedOrigin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
                 . "://" . $_SERVER['HTTP_HOST'];

if ($origin === $allowedOrigin || in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-XSRF-Token');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
}

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
    exit;
}

// Check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || empty($_SESSION['user']['user_id'])) {
    ApiResponse::send(ApiResponse::unauthorized('Not authenticated'), 401);
    exit;
}

// Validate CSRF token
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_XSRF_TOKEN'] ?? '';
if (empty($csrfToken) || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
    ApiResponse::send(ApiResponse::error('Invalid security token'), 403);
    exit;
}

$userId = (int) $_SESSION['user']['user_id'];
$username = $_SESSION['user']['username'] ?? 'unknown';

try {
    $sessionManager = SessionManager::getInstance();
    
    // Destroy ALL sessions (pass null for exceptToken to include current)
    $result = $sessionManager->destroyAllUserSessions($userId, null);
    
    $count = $result['count'] ?? 0;
    
    // Log the action for audit (before destroying local session)
    error_log(sprintf(
        '[logout-everywhere] User %d (%s) logged out %d session(s) including current',
        $userId,
        $username,
        $count
    ));
    
    // Now destroy the PHP session as well
    $_SESSION = [];
    
    // Delete session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    
    // Destroy the session
    session_destroy();
    
    if ($result['success']) {
        ApiResponse::send(ApiResponse::success([
            'sessions_terminated' => $count,
            'logged_out' => true,
            'redirect_to' => '/login'
        ], "Logged out from all {$count} session(s)"), 200);
    } else {
        // Even if database operation failed, we still destroyed local session
        ApiResponse::send(ApiResponse::success([
            'sessions_terminated' => 0,
            'logged_out' => true,
            'redirect_to' => '/login',
            'warning' => $result['message'] ?? 'Some sessions may not have been terminated'
        ], 'Logged out locally'), 200);
    }
    
} catch (Exception $e) {
    error_log('[logout-everywhere] Error: ' . $e->getMessage());
    
    // Even on error, try to destroy local session
    try {
        $_SESSION = [];
        session_destroy();
    } catch (Exception $destroyError) {
        // Ignore
    }
    
    ApiResponse::send(ApiResponse::serverError('Failed to logout from all sessions'), 500);
}
