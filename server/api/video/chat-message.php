<?php
/**
 * Chat Message API Endpoint
 * 
 * POST /api/video/chat-message
 * 
 * Sends a chat message in a meeting.
 * 
 * Request Body:
 *   - meeting_id: int (required) - Meeting ID
 *   - participant_id: int (required) - Participant ID
 *   - message: string (required) - Message text (1-2000 chars)
 * 
 * Response:
 *   - success: true, message_id, sent_at
 *   - 400 if invalid data
 *   - 403 if participant not in meeting
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
    $meetingId = $data['meeting_id'] ?? $data['meetingId'] ?? null;
    $participantId = $data['participant_id'] ?? $data['participantId'] ?? null;
    $message = $data['message'] ?? '';
    
    $errors = [];
    
    if (empty($meetingId) || !is_numeric($meetingId)) {
        $errors['meeting_id'] = 'Valid meeting ID is required';
    }
    
    if (empty($participantId) || !is_numeric($participantId)) {
        $errors['participant_id'] = 'Valid participant ID is required';
    }
    
    if (empty(trim($message))) {
        $errors['message'] = 'Message cannot be empty';
    }
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(ApiResponse::validationError($errors));
        exit;
    }
    
    // Initialize database and view model
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $viewModel = new VideoMeetingViewModel($pdo);
    
    // Send message
    $response = $viewModel->sendChatMessage((int) $meetingId, (int) $participantId, $message);
    
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
    error_log("Chat message error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(ApiResponse::serverError('Failed to send message'));
}
