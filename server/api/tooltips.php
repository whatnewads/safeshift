<?php
/**
 * Tooltips API Endpoint
 * Handles tooltip retrieval and preferences
 * 
 * Feature 1.3: Tooltip-based Guided Interface
 * 
 * Endpoints:
 * GET    /api/v1/tooltips?page={page_identifier} - Get tooltips for a page
 * PUT    /api/v1/tooltips/preferences - Update user preferences
 * POST   /api/v1/tooltips/{tooltip_id}/dismiss - Dismiss a tooltip
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Set headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// CORS handling
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
if ($origin === $allowed_origin) {
    header("Access-Control-Allow-Origin: $allowed_origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, PUT, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token");
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Session validation
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['user_id'])) {
    \App\log\security_event('UNAUTHORIZED_API_ACCESS', [
        'api' => 'tooltips',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user']['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Parse URL for tooltip ID if present
$request_uri = $_SERVER['REQUEST_URI'];
$path_parts = explode('/', trim(parse_url($request_uri, PHP_URL_PATH), '/'));
$action = null;
$tooltip_id = null;

// Check if we have tooltips/{id}/action pattern
if (count($path_parts) >= 3 && $path_parts[1] === 'tooltips') {
    if ($path_parts[2] === 'preferences') {
        $action = 'preferences';
    } else if (count($path_parts) >= 4 && $path_parts[3] === 'dismiss') {
        $tooltip_id = $path_parts[2];
        $action = 'dismiss';
    }
}

// CSRF validation for non-GET requests
if ($method !== 'GET') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';
    
    if (!$csrf_token || $csrf_token !== ($_SESSION['csrf_token'] ?? '')) {
        \App\log\security_event('CSRF_VALIDATION_FAILED', [
            'api' => 'tooltips',
            'user_id' => $user_id
        ]);
        http_response_code(403);
        echo json_encode(['error' => 'CSRF validation failed']);
        exit;
    }
}

try {
    $db = \App\db\pdo();
    $tooltipService = new \Core\Services\TooltipService($db);
    
    switch ($method) {
        case 'GET':
            // Get tooltips for a page
            $page_identifier = $_GET['page'] ?? '';
            
            if (empty($page_identifier)) {
                http_response_code(400);
                echo json_encode(['error' => 'Page identifier required']);
                exit;
            }
            
            $tooltips = $tooltipService->getPageTooltips($page_identifier, $user_id);
            $preferences = $tooltipService->getUserPreferences($user_id);
            
            echo json_encode([
                'success' => true,
                'tooltips' => $tooltips,
                'preferences' => $preferences
            ]);
            break;
            
        case 'PUT':
            if ($action === 'preferences') {
                // Update user preferences
                $input = json_decode(file_get_contents('php://input'), true);
                
                $preferences = [
                    'tooltips_enabled' => $input['tooltips_enabled'] ?? true,
                    'dismissed_tooltips' => $input['dismissed_tooltips'] ?? []
                ];
                
                $success = $tooltipService->updateUserPreferences($user_id, $preferences);
                
                if ($success) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Preferences updated successfully'
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to update preferences']);
                }
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Unknown endpoint']);
            }
            break;
            
        case 'POST':
            if ($action === 'dismiss' && $tooltip_id) {
                // Dismiss a tooltip
                $success = $tooltipService->dismissTooltip($user_id, $tooltip_id);
                
                if ($success) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Tooltip dismissed'
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to dismiss tooltip']);
                }
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Unknown endpoint']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    \App\log\file_log('error', [
        'api' => 'tooltips',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'user_id' => $user_id
    ]);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage() // Remove in production
    ]);
}