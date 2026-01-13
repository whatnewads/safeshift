<?php
/**
 * Leave Meeting API Endpoint
 * 
 * POST /api/video/leave-meeting
 * 
 * Marks participant as having left the meeting.
 * 
 * Request Body:
 *   - participant_id: int (required) - Participant ID
 *   - meeting_id: int (required) - Meeting ID
 * 
 * Response:
 *   - success: true
 *   - 400 if invalid data
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
    $participantId = $data['participant_id'] ?? $data['participantId'] ?? null;
    $meetingId = $data['meeting_id'] ?? $data['meetingId'] ?? null;
    
    if (empty($participantId) || !is_numeric($participantId)) {
        http_response_code(400);
        echo json_encode(ApiResponse::validationError(['participant_id' => 'Valid participant ID is required']));
        exit;
    }
    
    if (empty($meetingId) || !is_numeric($meetingId)) {
        http_response_code(400);
        echo json_encode(ApiResponse::validationError(['meeting_id' => 'Valid meeting ID is required']));
        exit;
    }
    
    // Initialize database and view model
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $viewModel = new VideoMeetingViewModel($pdo);
    
    // Leave meeting
    $success = $viewModel->leaveMeeting((int) $participantId, (int) $meetingId);
    
    if ($success) {
        http_response_code(200);
        echo json_encode(ApiResponse::success([
            'participant_id' => (int) $participantId,
            'meeting_id' => (int) $meetingId,
            'left_at' => date('Y-m-d\TH:i:s\Z'),
        ], 'Left meeting successfully'));
    } else {
        http_response_code(400);
        echo json_encode(ApiResponse::badRequest('Failed to leave meeting'));
    }
    
} catch (\Throwable $e) {
    error_log("Leave meeting error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(ApiResponse::serverError('Failed to process leave request'));
}
