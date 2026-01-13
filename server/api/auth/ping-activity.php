<?php
/**
 * Ping Activity API Endpoint
 * 
 * POST /api/auth/ping-activity
 * 
 * Updates the last_activity timestamp for the current session.
 * This endpoint is called periodically by the frontend to keep the session alive
 * and track user activity for HIPAA compliance.
 * 
 * Response:
 * - remaining_time: Seconds until session expires
 * - idle_timeout: User's configured idle timeout
 * - expires_at: ISO 8601 timestamp of hard expiration
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

$userId = (int) $_SESSION['user']['user_id'];

try {
    // Get session token from cookie or session
    $sessionToken = $_SESSION['db_session_token'] ?? null;
    
    if ($sessionToken) {
        // Use database-backed session manager
        $sessionManager = SessionManager::getInstance();
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
            // Return info based on PHP session
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
