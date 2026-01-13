<?php
/**
 * End Meeting API Endpoint
 * 
 * POST /api/video/end-meeting
 * 
 * Ends a video meeting. Only the meeting creator can end it.
 * Requires authentication.
 * 
 * Request Body:
 *   - meeting_id: int (required) - Meeting ID to end
 * 
 * Response:
 *   - success: true
 *   - 403 if user is not meeting creator
 *   - 401 if not authenticated
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
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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
    // Check authentication
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['user_id'])) {
        http_response_code(401);
        echo json_encode(ApiResponse::unauthorized('Authentication required'));
        exit;
    }
    
    // User ID is a UUID string, not an integer
    $userId = (string) $_SESSION['user']['user_id'];
    
    // Parse JSON body
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(ApiResponse::badRequest('Invalid JSON in request body'));
        exit;
    }
    
    // Validate required fields
    $meetingId = $data['meeting_id'] ?? $data['meetingId'] ?? null;
    
    if (empty($meetingId) || !is_numeric($meetingId)) {
        http_response_code(400);
        echo json_encode(ApiResponse::validationError(['meeting_id' => 'Valid meeting ID is required']));
        exit;
    }
    
    // Initialize database and view model
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $viewModel = new VideoMeetingViewModel($pdo);
    
    // End meeting
    $success = $viewModel->endMeeting((int) $meetingId, $userId);
    
    if ($success) {
        http_response_code(200);
        echo json_encode(ApiResponse::success([
            'meeting_id' => (int) $meetingId,
            'ended_at' => date('Y-m-d\TH:i:s\Z'),
        ], 'Meeting ended successfully'));
    } else {
        http_response_code(403);
        echo json_encode(ApiResponse::forbidden('Unable to end meeting. You may not have permission.'));
    }
    
} catch (\Throwable $e) {
    error_log("End meeting error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(ApiResponse::serverError('Failed to end meeting'));
}
