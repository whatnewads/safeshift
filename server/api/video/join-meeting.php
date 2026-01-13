<?php
/**
 * Join Meeting API Endpoint
 * 
 * POST /api/video/join-meeting
 * 
 * Joins a video meeting using a token.
 * Public endpoint - no authentication required.
 * 
 * Request Body:
 *   - token: string (required) - Meeting access token
 *   - display_name: string (required) - Participant display name (1-100 chars)
 * 
 * Response:
 *   - success: true, participant_id, meeting_id, session_data
 *   - 400 if token invalid or display_name missing
 * 
 * @package API\Video
 */

declare(strict_types=1);

// Set headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error_code' => 'METHOD_NOT_ALLOWED',
        'message' => 'Only POST method is allowed',
    ]);
    exit;
}

// Load dependencies
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../model/Interfaces/EntityInterface.php';
require_once __DIR__ . '/../../model/Core/Database.php';
require_once __DIR__ . '/../../model/Entities/VideoMeeting.php';
require_once __DIR__ . '/../../model/Entities/MeetingParticipant.php';
require_once __DIR__ . '/../../model/Entities/MeetingChatMessage.php';
require_once __DIR__ . '/../../model/Repositories/VideoMeetingRepository.php';
require_once __DIR__ . '/../../ViewModel/Core/BaseViewModel.php';
require_once __DIR__ . '/../../ViewModel/Core/ApiResponse.php';
require_once __DIR__ . '/../../ViewModel/VideoMeetingViewModel.php';

use Model\Core\Database;
use ViewModel\VideoMeetingViewModel;
use ViewModel\Core\ApiResponse;

try {
    // Parse JSON body
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(ApiResponse::badRequest('Invalid JSON in request body'));
        exit;
    }
    
    // Validate required fields
    $token = $data['token'] ?? '';
    $displayName = $data['display_name'] ?? $data['displayName'] ?? '';
    
    if (empty($token)) {
        http_response_code(400);
        echo json_encode(ApiResponse::validationError(['token' => 'Token is required']));
        exit;
    }
    
    if (empty($displayName)) {
        http_response_code(400);
        echo json_encode(ApiResponse::validationError(['display_name' => 'Display name is required']));
        exit;
    }
    
    // Validate display name length
    if (strlen($displayName) < 1 || strlen($displayName) > 100) {
        http_response_code(400);
        echo json_encode(ApiResponse::validationError([
            'display_name' => 'Display name must be between 1 and 100 characters',
        ]));
        exit;
    }
    
    // Get client IP address
    $ipAddress = getClientIp();
    
    // Initialize database and view model
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $viewModel = new VideoMeetingViewModel($pdo);
    
    // Join meeting
    $response = $viewModel->joinMeeting($token, $displayName, $ipAddress);
    
    // Determine HTTP status code
    $statusCode = 200;
    if (!$response['success']) {
        if ($response['error_code'] === 'FORBIDDEN') {
            $statusCode = 403;
        } elseif ($response['error_code'] === 'VALIDATION_ERROR') {
            $statusCode = 422;
        } elseif ($response['error_code'] === 'BAD_REQUEST') {
            $statusCode = 400;
        } else {
            $statusCode = 500;
        }
    }
    
    http_response_code($statusCode);
    echo json_encode($response);
    
} catch (\Throwable $e) {
    error_log("Join meeting error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(ApiResponse::serverError('Failed to join meeting'));
}

/**
 * Get client IP address, considering proxies
 */
function getClientIp(): string
{
    $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            
            // Handle comma-separated list (proxy chain)
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return 'unknown';
}
