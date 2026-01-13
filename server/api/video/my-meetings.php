<?php
/**
 * My Meetings API Endpoint
 * 
 * GET /api/video/my-meetings
 * 
 * Gets meetings created by the authenticated user.
 * Requires authentication.
 * 
 * Query Parameters:
 *   - active_only: bool (optional) - Only return active meetings (default: false)
 *   - limit: int (optional) - Max number of results (default: 50)
 * 
 * Response:
 *   - meetings: array of meeting objects
 *   - count: number of meetings
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
    
    // Get query parameters
    $activeOnly = filter_var($_GET['active_only'] ?? $_GET['activeOnly'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
    
    // Initialize database and view model
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $viewModel = new VideoMeetingViewModel($pdo);
    
    // Get user's meetings
    $meetings = $viewModel->getMyMeetings($userId, $activeOnly, $limit);
    
    http_response_code(200);
    echo json_encode(ApiResponse::success([
        'meetings' => $meetings,
        'count' => count($meetings),
        'filters' => [
            'active_only' => $activeOnly,
            'limit' => $limit,
        ],
    ], 'Meetings retrieved successfully'));
    
} catch (\Throwable $e) {
    error_log("Get my meetings error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(ApiResponse::serverError('Failed to get meetings'));
}
