<?php
/**
 * Active Sessions API Endpoint
 * 
 * GET /api/auth/active-sessions
 * 
 * Returns a list of all active sessions for the current user.
 * Sessions are sanitized - no tokens are exposed.
 * 
 * Response:
 * - sessions: Array of session objects with id, device_info, ip_address (masked), timestamps
 * - count: Total number of active sessions
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
    header('Access-Control-Allow-Methods: GET, OPTIONS');
}

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only accept GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

$userId = (int) $_SESSION['user']['user_id'];
$currentSessionToken = $_SESSION['db_session_token'] ?? null;

try {
    $sessionManager = SessionManager::getInstance();
    $result = $sessionManager->getActiveSessions($userId);
    
    if ($result['success']) {
        $sessions = $result['sessions'] ?? [];
        
        // Mark current session
        if ($currentSessionToken) {
            // We can't compare tokens directly since they're hashed,
            // but we can use the session_id from the current PHP session
            // to identify the current session in the list
            $currentPhpSessionId = session_id();
            
            // For now, just mark based on matching IP and recent activity
            // In a full implementation, you'd store the session ID mapping
            foreach ($sessions as &$session) {
                // Mark session as current if it matches current request characteristics
                $session['is_current'] = false;
                
                // Check if this session was created around the same time as current login
                if (isset($_SESSION['user']['logged_in_at'])) {
                    $loginTime = $_SESSION['user']['logged_in_at'];
                    $sessionCreated = strtotime($session['created_at']);
                    // If created within 5 seconds of login, likely current session
                    if (abs($loginTime - $sessionCreated) < 5) {
                        $session['is_current'] = true;
                    }
                }
            }
            unset($session); // Break reference
        }
        
        // Get session statistics
        $stats = $sessionManager->getSessionStats($userId);
        
        ApiResponse::send(ApiResponse::success([
            'sessions' => $sessions,
            'count' => count($sessions),
            'stats' => $stats['stats'] ?? null
        ], 'Active sessions retrieved'), 200);
    } else {
        ApiResponse::send(ApiResponse::error($result['message'] ?? 'Failed to retrieve sessions'), 500);
    }
    
} catch (Exception $e) {
    error_log('[active-sessions] Error: ' . $e->getMessage());
    ApiResponse::send(ApiResponse::serverError('Failed to retrieve sessions'), 500);
}
