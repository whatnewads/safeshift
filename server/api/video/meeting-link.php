<?php
/**
 * Meeting Link API Endpoint
 * 
 * GET /api/video/meeting-link?meeting_id=xxx
 * 
 * Gets the shareable meeting link for a meeting.
 * Requires authentication - only meeting creator can get the link.
 * 
 * Query Parameters:
 *   - meeting_id: int (required) - Meeting ID
 * 
 * Response:
 *   - meeting_url: string - Full URL to join the meeting
 *   - 403 if not meeting creator
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
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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
    // Check authentication
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['user_id'])) {
        http_response_code(401);
        echo json_encode(ApiResponse::unauthorized('Authentication required'));
        exit;
    }
    
    // User ID is a UUID string, not an integer
    $userId = (string) $_SESSION['user']['user_id'];
    
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
    
    // Get meeting link
    $meetingUrl = $viewModel->getMeetingLink((int) $meetingId, $userId);
    
    if ($meetingUrl === null) {
        http_response_code(403);
        echo json_encode(ApiResponse::forbidden('Meeting not found or you do not have permission'));
        exit;
    }
    
    http_response_code(200);
    echo json_encode(ApiResponse::success([
        'meeting_id' => (int) $meetingId,
        'meeting_url' => $meetingUrl,
    ], 'Meeting link retrieved successfully'));
    
} catch (\Throwable $e) {
    error_log("Get meeting link error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(ApiResponse::serverError('Failed to get meeting link'));
}
