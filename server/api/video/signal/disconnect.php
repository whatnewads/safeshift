<?php
/**
 * Disconnect API Endpoint
 * 
 * Removes a peer when a participant disconnects from the meeting.
 * 
 * POST /api/video/signal/disconnect.php
 * Body: { meeting_id: int, participant_id: int }
 * Returns: { success: true }
 * 
 * @package SafeShift\API\Video\Signal
 * @author SafeShift Development Team
 * @copyright 2025 SafeShift EHR
 */

declare(strict_types=1);

// CORS headers for API access
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use POST.',
    ]);
    exit;
}

// Load dependencies
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../ViewModel/VideoMeetingViewModel.php';

use ViewModel\VideoMeetingViewModel;

try {
    // Parse JSON body
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON body',
        ]);
        exit;
    }
    
    // Validate required fields
    $meetingId = isset($data['meeting_id']) ? (int) $data['meeting_id'] : 0;
    $participantId = isset($data['participant_id']) ? (int) $data['participant_id'] : 0;
    
    if ($meetingId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Missing or invalid meeting_id',
        ]);
        exit;
    }
    
    if ($participantId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Missing or invalid participant_id',
        ]);
        exit;
    }
    
    // Initialize ViewModel
    $viewModel = new VideoMeetingViewModel();
    
    // Disconnect the peer
    $success = $viewModel->disconnectPeer($meetingId, $participantId);
    
    if ($success) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Peer disconnected successfully',
            'data' => [
                'meeting_id' => $meetingId,
                'participant_id' => $participantId,
            ],
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to disconnect peer. Participant may not exist.',
        ]);
    }
    
} catch (Exception $e) {
    error_log('Disconnect Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
    ]);
}
