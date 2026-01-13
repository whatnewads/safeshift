<?php
/**
 * Create Meeting API Endpoint
 * 
 * POST /api/video/create-meeting
 * 
 * Creates a new video meeting. Requires authenticated clinician.
 * 
 * Response:
 *   - success: true, meeting_id, meeting_url, token, expires_at
 *   - 403 if user is not a clinician
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
    
    // Initialize database and view model
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $viewModel = new VideoMeetingViewModel($pdo);
    
    // Create meeting
    $response = $viewModel->createMeeting($userId);
    
    // Determine HTTP status code
    $statusCode = 200;
    if (!$response['success']) {
        if ($response['error_code'] === 'FORBIDDEN') {
            $statusCode = 403;
        } elseif ($response['error_code'] === 'VALIDATION_ERROR') {
            $statusCode = 422;
        } else {
            $statusCode = 500;
        }
    }
    
    http_response_code($statusCode);
    echo json_encode($response);
    
} catch (\Throwable $e) {
    error_log("Create meeting error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(ApiResponse::serverError('Failed to create meeting'));
}
