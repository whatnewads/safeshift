<?php
/**
 * Register Peer API Endpoint
 * 
 * Registers a PeerJS peer ID with a meeting participant for WebRTC signaling.
 * 
 * POST /api/video/signal/register-peer.php
 * Body: { meeting_id: int, participant_id: int, peer_id: string }
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
    $peerId = isset($data['peer_id']) ? trim((string) $data['peer_id']) : '';
    
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
    
    if (empty($peerId) || strlen($peerId) > 64) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Missing or invalid peer_id (max 64 characters)',
        ]);
        exit;
    }
    
    // Initialize ViewModel
    $viewModel = new VideoMeetingViewModel();
    
    // Register the peer
    $success = $viewModel->registerPeer($meetingId, $participantId, $peerId);
    
    if ($success) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Peer registered successfully',
            'data' => [
                'meeting_id' => $meetingId,
                'participant_id' => $participantId,
                'peer_id' => $peerId,
            ],
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to register peer. Participant may not exist or meeting may have ended.',
        ]);
    }
    
} catch (Exception $e) {
    error_log('Register Peer Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
    ]);
}
