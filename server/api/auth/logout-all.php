<?php
/**
 * Logout All Other Sessions API Endpoint
 * 
 * POST /api/auth/logout-all
 * 
 * Logs out all sessions for the current user EXCEPT the current session.
 * Used for "Sign out other devices" feature.
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
$currentSessionToken = $_SESSION['db_session_token'] ?? null;

try {
    $sessionManager = SessionManager::getInstance();
    
    // Destroy all sessions except current
    $result = $sessionManager->destroyAllUserSessions($userId, $currentSessionToken);
    
    if ($result['success']) {
        $count = $result['count'] ?? 0;
        
        // Log the action for audit
        error_log(sprintf(
            '[logout-all] User %d logged out %d other session(s)',
            $userId,
            $count
        ));
        
        ApiResponse::send(ApiResponse::success([
            'sessions_terminated' => $count,
            'current_session_active' => true
        ], $result['message'] ?? "Logged out {$count} other session(s)"), 200);
    } else {
        ApiResponse::send(ApiResponse::error($result['message'] ?? 'Failed to logout other sessions'), 500);
    }
    
} catch (Exception $e) {
    error_log('[logout-all] Error: ' . $e->getMessage());
    ApiResponse::send(ApiResponse::serverError('Failed to logout other sessions'), 500);
}
