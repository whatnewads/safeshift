<?php
/**
 * Logout Specific Session API Endpoint
 * 
 * POST /api/auth/logout-session
 * 
 * Logs out a specific session by ID. The session must belong to the current user.
 * Used for "Sign out" feature in the active sessions list.
 * 
 * Request Body:
 * - session_id: (required) The ID of the session to terminate
 * 
 * Response:
 * - success: Whether the logout was successful
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

// Get JSON input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? [];

// Validate session_id
if (empty($input['session_id'])) {
    ApiResponse::send(ApiResponse::validationError([
        'session_id' => ['Session ID is required']
    ]), 422);
    exit;
}

$sessionId = (int) $input['session_id'];

if ($sessionId <= 0) {
    ApiResponse::send(ApiResponse::validationError([
        'session_id' => ['Invalid session ID']
    ]), 422);
    exit;
}

try {
    $sessionManager = SessionManager::getInstance();
    $result = $sessionManager->destroySessionById($sessionId, $userId);
    
    if ($result['success']) {
        // Log the action for audit
        error_log(sprintf(
            '[logout-session] User %d logged out session %d',
            $userId,
            $sessionId
        ));
        
        ApiResponse::send(ApiResponse::success([
            'session_id' => $sessionId,
            'logged_out' => true
        ], $result['message'] ?? 'Session terminated'), 200);
    } else {
        ApiResponse::send(ApiResponse::error($result['message'] ?? 'Failed to terminate session'), 400);
    }
    
} catch (Exception $e) {
    error_log('[logout-session] Error: ' . $e->getMessage());
    ApiResponse::send(ApiResponse::serverError('Failed to terminate session'), 500);
}
