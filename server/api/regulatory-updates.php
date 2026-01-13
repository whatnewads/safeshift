<?php
require_once '../includes/bootstrap.php';

use Core\Services\AuthService;
use Core\Services\RegulatoryUpdateService;

header('Content-Type: application/json');

try {
    // Check authentication
    $authService = new AuthService();
    $user = $authService->validateToken();
    
    // Only admins can access regulatory updates
    if (!$user || !in_array($user['role'], ['tadmin', 'cadmin'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized - Admin access required']);
        exit;
    }
    
    $regulatoryService = new RegulatoryUpdateService();
    $method = $_SERVER['REQUEST_METHOD'];
    $path = isset($_GET['path']) ? $_GET['path'] : '';
    
    switch ($method) {
        case 'GET':
            if ($path === 'list') {
                // Get list of regulatory updates
                $filters = [
                    'agency' => $_GET['agency'] ?? null,
                    'type' => $_GET['type'] ?? null,
                    'date_from' => $_GET['date_from'] ?? null
                ];
                
                $filters = array_filter($filters, function($value) {
                    return $value !== null;
                });
                
                $limit = (int)($_GET['limit'] ?? 50);
                $offset = (int)($_GET['offset'] ?? 0);
                
                $result = $regulatoryService->getRegulatoryUpdates($filters, $limit, $offset);
                
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
                
            } elseif ($path === 'download') {
                // Download document
                $updateId = $_GET['update_id'] ?? null;
                
                if (!$updateId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Update ID required']);
                    exit;
                }
                
                // Get document path
                $stmt = $regulatoryService->getDb()->prepare("
                    SELECT document_path, regulation_title 
                    FROM regulatory_updates 
                    WHERE update_id = ?
                ");
                $stmt->execute([$updateId]);
                $doc = $stmt->fetch();
                
                if (!$doc || !file_exists($doc['document_path'])) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Document not found']);
                    exit;
                }
                
                // Send file
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($doc['document_path']) . '"');
                header('Content-Length: ' . filesize($doc['document_path']));
                readfile($doc['document_path']);
                exit;
                
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Not found']);
            }
            break;
            
        case 'POST':
            if ($path === 'upload') {
                // Upload new regulatory document
                if (!isset($_FILES['document'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'No file uploaded']);
                    exit;
                }
                
                $metadata = [
                    'regulation_title' => $_POST['regulation_title'] ?? '',
                    'regulation_agency' => $_POST['regulation_agency'] ?? '',
                    'regulation_type' => $_POST['regulation_type'] ?? 'new_rule',
                    'effective_date' => $_POST['effective_date'] ?? null
                ];
                
                // Validate required fields
                if (empty($metadata['regulation_title']) || 
                    empty($metadata['regulation_agency']) || 
                    empty($metadata['effective_date'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing required fields']);
                    exit;
                }
                
                $result = $regulatoryService->uploadDocument(
                    $_FILES['document'],
                    $metadata,
                    $user['id']
                );
                
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
                
            } elseif ($path === 'generate-summary') {
                // Generate AI summary
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (!isset($data['update_id'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Update ID required']);
                    exit;
                }
                
                $summary = $regulatoryService->generateSummary($data['update_id']);
                
                echo json_encode([
                    'success' => true,
                    'data' => ['summary' => $summary]
                ]);
                
            } elseif ($path === 'generate-checklist') {
                // Generate implementation checklist
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (!isset($data['update_id'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Update ID required']);
                    exit;
                }
                
                $checklist = $regulatoryService->generateChecklist($data['update_id']);
                
                echo json_encode([
                    'success' => true,
                    'data' => ['checklist' => $checklist]
                ]);
                
            } elseif ($path === 'create-training') {
                // Create training module
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (!isset($data['update_id']) || 
                    !isset($data['assigned_roles']) || 
                    !isset($data['due_date'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing required fields']);
                    exit;
                }
                
                $trainingId = $regulatoryService->createTrainingModule(
                    $data['update_id'],
                    $data['assigned_roles'],
                    $data['due_date']
                );
                
                echo json_encode([
                    'success' => true,
                    'data' => ['training_id' => $trainingId]
                ]);
                
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Not found']);
            }
            break;
            
        case 'PUT':
            if ($path === 'update-checklist-item') {
                // Update checklist item status
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (!isset($data['update_id']) || 
                    !isset($data['item_index']) || 
                    !isset($data['updates'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing required fields']);
                    exit;
                }
                
                $result = $regulatoryService->updateChecklistItem(
                    $data['update_id'],
                    $data['item_index'],
                    $data['updates']
                );
                
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
                
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Not found']);
            }
            break;
            
        case 'DELETE':
            // Archive regulatory update
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['update_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Update ID required']);
                exit;
            }
            
            // Soft delete by marking as archived
            $stmt = $regulatoryService->getDb()->prepare("
                UPDATE regulatory_updates 
                SET status = 'archived' 
                WHERE update_id = ?
            ");
            $stmt->execute([$data['update_id']]);
            
            echo json_encode(['success' => true]);
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