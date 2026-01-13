<?php
/**
 * Get Peers API Endpoint
 * 
 * Retrieves all active peer IDs for a meeting for WebRTC signaling.
 * 
 * GET /api/video/signal/get-peers.php?meeting_id={id}
 * Returns: { peers: [ { participant_id, peer_id, display_name } ] }
 * 
 * @package SafeShift\API\Video\Signal
 * @author SafeShift Development Team
 * @copyright 2025 SafeShift EHR
 */

declare(strict_types=1);

// CORS headers for API access
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use GET.',
    ]);
    exit;
}

// Load dependencies
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../ViewModel/VideoMeetingViewModel.php';

use ViewModel\VideoMeetingViewModel;

try {
    // Get meeting_id from query string
    $meetingId = isset($_GET['meeting_id']) ? (int) $_GET['meeting_id'] : 0;
    
    if ($meetingId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Missing or invalid meeting_id',
        ]);
        exit;
    }
    
    // Initialize ViewModel
    $viewModel = new VideoMeetingViewModel();
    
    // Get active peers
    $peers = $viewModel->getActivePeers($meetingId);
    
    // Transform to API response format
    $peerList = array_map(function ($peer) {
        return [
            'participant_id' => $peer['participant_id'],
            'peer_id' => $peer['peer_id'],
            'display_name' => $peer['display_name'],
        ];
    }, $peers);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'meeting_id' => $meetingId,
        'peers' => $peerList,
        'count' => count($peerList),
    ]);
    
} catch (Exception $e) {
    error_log('Get Peers Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
    ]);
}
