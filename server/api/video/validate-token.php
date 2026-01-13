<?php
/**
 * Validate Token API Endpoint
 * 
 * GET /api/video/validate-token?token=xxx
 * 
 * Validates a meeting token without joining.
 * Public endpoint - no authentication required.
 * 
 * Response:
 *   - { valid: true, meeting_id } if token is valid
 *   - { valid: false, error: "..." } if token is invalid/expired
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
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error_code' => 'METHOD_NOT_ALLOWED',
        'message' => 'Only GET method is allowed',
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
    // Get token from query string
    $token = $_GET['token'] ?? '';
    
    if (empty($token)) {
        http_response_code(400);
        echo json_encode(ApiResponse::badRequest('Token parameter is required'));
        exit;
    }
    
    // Initialize database and view model
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $viewModel = new VideoMeetingViewModel($pdo);
    
    // Validate token
    $response = $viewModel->validateToken($token);
    
    // Always return 200 for validation results
    http_response_code(200);
    echo json_encode($response);
    
} catch (\Throwable $e) {
    error_log("Validate token error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(ApiResponse::serverError('Failed to validate token'));
}
