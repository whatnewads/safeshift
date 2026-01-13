<?php
require_once '../includes/bootstrap.php';

use Core\Services\AuthService;
use Core\Services\QualityReviewService;

header('Content-Type: application/json');

try {
    // Check authentication
    $authService = new AuthService();
    $user = $authService->validateToken();
    
    if (!$user || !in_array($user['role'], ['tadmin', 'cadmin', 'pclinician'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $qrService = new QualityReviewService();
    $method = $_SERVER['REQUEST_METHOD'];
    $path = isset($_GET['path']) ? $_GET['path'] : '';
    
    switch ($method) {
        case 'GET':
            if ($path === 'queue') {
                // Get review queue
                $filters = [
                    'provider_id' => $_GET['provider_id'] ?? null,
                    'priority' => $_GET['priority'] ?? null,
                    'date_from' => $_GET['date_from'] ?? null,
                    'date_to' => $_GET['date_to'] ?? null
                ];
                $limit = (int)($_GET['limit'] ?? 20);
                $offset = (int)($_GET['offset'] ?? 0);
                
                $result = $qrService->getReviewQueue($user['id'], $filters, $limit, $offset);
                
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
                
            } elseif ($path === 'stats') {
                // Get QA statistics
                $dateRange = $_GET['range'] ?? '30';
                $result = $qrService->getQAStatistics($dateRange);
                
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
                
            } elseif ($path === 'download') {
                // Download for offline review
                $limit = (int)($_GET['limit'] ?? 50);
                $result = $qrService->downloadForOfflineReview($user['id'], $limit);
                
                echo json_encode($result);
                
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Not found']);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid request data']);
                exit;
            }
            
            if ($path === 'approve') {
                // Approve encounter
                $result = $qrService->approveEncounter(
                    $data['encounter_id'],
                    $user['id'],
                    $data['notes'] ?? ''
                );
                
                echo json_encode($result);
                
            } elseif ($path === 'reject') {
                // Reject encounter
                $result = $qrService->rejectEncounter(
                    $data['encounter_id'],
                    $user['id'],
                    $data['rejection_reasons'] ?? [],
                    $data['notes'] ?? ''
                );
                
                echo json_encode($result);
                
            } elseif ($path === 'flag') {
                // Flag encounter
                $result = $qrService->flagEncounter(
                    $data['encounter_id'],
                    $user['id'],
                    $data['flag_reason'],
                    $data['escalate_to'] ?? null
                );
                
                echo json_encode($result);
                
            } elseif ($path === 'bulk-approve') {
                // Bulk approve
                $result = $qrService->bulkApprove(
                    $data['encounter_ids'],
                    $user['id'],
                    $data['notes'] ?? ''
                );
                
                echo json_encode($result);
                
            } elseif ($path === 'sync') {
                // Sync offline decisions
                $result = $qrService->syncOfflineDecisions($data['decisions']);
                
                echo json_encode($result);
                
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Not found']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}