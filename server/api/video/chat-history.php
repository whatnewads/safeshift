<?php
/**
 * Chat History API Endpoint
 * 
 * GET /api/video/chat-history?meeting_id=xxx
 * 
 * Gets chat message history for a meeting.
 * 
 * Query Parameters:
 *   - meeting_id: int (required) - Meeting ID
 * 
 * Response:
 *   - messages: array of message objects
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
    // Get meeting ID from query string
    $meetingId = $_GET['meeting_id'] ?? $_GET['meetingId'] ?? null;
    
    if (empty($meetingId) || !is_numeric($meetingId)) {
        http_response_code(400);
        echo json_encode(ApiResponse::badRequest('Valid meeting_id parameter is required'));
        exit;
    }
    
    // Initialize database and view model
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $viewModel = new VideoMeetingViewModel($pdo);
    
    // Get chat history
    $messages = $viewModel->getChatHistory((int) $meetingId);
    
    http_response_code(200);
    echo json_encode(ApiResponse::success([
        'messages' => $messages,
        'count' => count($messages),
        'meeting_id' => (int) $meetingId,
    ], 'Chat history retrieved successfully'));
    
} catch (\Throwable $e) {
    error_log("Get chat history error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(ApiResponse::serverError('Failed to get chat history'));
}
