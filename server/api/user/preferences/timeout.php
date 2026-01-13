<?php
/**
 * User Timeout Preferences API Endpoint
 * 
 * GET /api/user/preferences/timeout
 * Returns the current idle timeout setting for the user
 * 
 * PUT /api/user/preferences/timeout
 * Updates the user's idle timeout preference
 * 
 * Request Body (PUT):
 * - timeout: (required) Timeout in seconds (300-3600)
 * 
 * Response:
 * - timeout: Current/updated timeout value in seconds
 * - min_timeout: Minimum allowed timeout (300)
 * - max_timeout: Maximum allowed timeout (3600)
 * 
 * @package SafeShift\API\User\Preferences
 */

// Load bootstrap
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

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
    header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
}

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
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
$method = $_SERVER['REQUEST_METHOD'];

// Constants for timeout limits
const MIN_TIMEOUT = 300;  // 5 minutes
const MAX_TIMEOUT = 3600; // 1 hour
const DEFAULT_TIMEOUT = 1800; // 30 minutes

try {
    $sessionManager = SessionManager::getInstance();
    
    switch ($method) {
        case 'GET':
            handleGetTimeout($sessionManager, $userId);
            break;
            
        case 'PUT':
            handlePutTimeout($sessionManager, $userId);
            break;
            
        default:
            ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
    }
    
} catch (Exception $e) {
    error_log('[timeout-preferences] Error: ' . $e->getMessage());
    ApiResponse::send(ApiResponse::serverError('Failed to process timeout preference'), 500);
}

/**
 * Handle GET request - retrieve current timeout setting
 */
function handleGetTimeout(SessionManager $sessionManager, int $userId): void
{
    $timeout = $sessionManager->getUserIdleTimeout($userId);
    
    // Also get full preferences if available
    $prefsResult = $sessionManager->getUserPreferences($userId);
    
    ApiResponse::send(ApiResponse::success([
        'timeout' => $timeout,
        'timeout_minutes' => round($timeout / 60),
        'min_timeout' => MIN_TIMEOUT,
        'max_timeout' => MAX_TIMEOUT,
        'min_minutes' => round(MIN_TIMEOUT / 60),
        'max_minutes' => round(MAX_TIMEOUT / 60),
        'preferences' => $prefsResult['success'] ? $prefsResult['preferences'] : null
    ], 'Timeout preference retrieved'), 200);
}

/**
 * Handle PUT request - update timeout setting
 */
function handlePutTimeout(SessionManager $sessionManager, int $userId): void
{
    // Validate CSRF token for PUT requests
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_XSRF_TOKEN'] ?? '';
    if (empty($csrfToken) || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
        ApiResponse::send(ApiResponse::error('Invalid security token'), 403);
        return;
    }
    
    // Get JSON input
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? [];
    
    // Accept timeout in seconds or minutes
    $timeoutSeconds = null;
    
    if (isset($input['timeout'])) {
        $timeoutSeconds = (int) $input['timeout'];
    } elseif (isset($input['timeout_seconds'])) {
        $timeoutSeconds = (int) $input['timeout_seconds'];
    } elseif (isset($input['timeout_minutes'])) {
        $timeoutSeconds = (int) $input['timeout_minutes'] * 60;
    }
    
    if ($timeoutSeconds === null) {
        ApiResponse::send(ApiResponse::validationError([
            'timeout' => ['Timeout value is required (in seconds or minutes)']
        ]), 422);
        return;
    }
    
    // Validate range
    if ($timeoutSeconds < MIN_TIMEOUT) {
        ApiResponse::send(ApiResponse::validationError([
            'timeout' => ["Timeout must be at least " . MIN_TIMEOUT . " seconds (" . round(MIN_TIMEOUT/60) . " minutes)"]
        ]), 422);
        return;
    }
    
    if ($timeoutSeconds > MAX_TIMEOUT) {
        ApiResponse::send(ApiResponse::validationError([
            'timeout' => ["Timeout cannot exceed " . MAX_TIMEOUT . " seconds (" . round(MAX_TIMEOUT/60) . " minutes)"]
        ]), 422);
        return;
    }
    
    // Update the preference
    $result = $sessionManager->setUserIdleTimeout($userId, $timeoutSeconds);
    
    if ($result['success']) {
        $actualTimeout = $result['timeout'] ?? $timeoutSeconds;
        
        // Log the change for audit
        error_log(sprintf(
            '[timeout-preferences] User %d updated idle timeout to %d seconds (%d minutes)',
            $userId,
            $actualTimeout,
            round($actualTimeout / 60)
        ));
        
        ApiResponse::send(ApiResponse::success([
            'timeout' => $actualTimeout,
            'timeout_minutes' => round($actualTimeout / 60),
            'min_timeout' => MIN_TIMEOUT,
            'max_timeout' => MAX_TIMEOUT
        ], $result['message'] ?? 'Timeout preference updated'), 200);
    } else {
        ApiResponse::send(ApiResponse::error($result['message'] ?? 'Failed to update timeout preference'), 500);
    }
}
