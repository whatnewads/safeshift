<?php
require_once '../includes/bootstrap.php';

use Core\Services\AuthService;
use Core\Services\AuditService;

header('Content-Type: application/json');

try {
    // Check authentication
    $authService = new AuthService();
    $user = $authService->validateToken();
    
    // Only privacy officers and admins can access audit logs
    if (!$user || !in_array($user['role'], ['tadmin', 'cadmin'])) {
        // Log the access attempt
        $auditService = new AuditService();
        $auditService->logAccessDenied('audit_logs', 'all', 'Insufficient privileges');
        
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized - Audit log access requires admin privileges']);
        exit;
    }
    
    $auditService = new AuditService();
    $method = $_SERVER['REQUEST_METHOD'];
    $path = isset($_GET['path']) ? $_GET['path'] : '';
    
    // Log audit log access (meta-logging)
    $auditService->log('view', 'audit_logs', 'system', 'Accessed audit log system');
    
    switch ($method) {
        case 'GET':
            if ($path === 'search') {
                // Search audit logs
                $filters = [
                    'start_date' => $_GET['start_date'] ?? null,
                    'end_date' => $_GET['end_date'] ?? null,
                    'user_id' => $_GET['user_id'] ?? null,
                    'action_type' => $_GET['action_type'] ?? null,
                    'resource_type' => $_GET['resource_type'] ?? null,
                    'resource_id' => $_GET['resource_id'] ?? null,
                    'search' => $_GET['search'] ?? null
                ];
                
                // Remove null values
                $filters = array_filter($filters, function($value) {
                    return $value !== null;
                });
                
                $limit = (int)($_GET['limit'] ?? 100);
                $offset = (int)($_GET['offset'] ?? 0);
                
                $result = $auditService->searchLogs($filters, $limit, $offset);
                
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
                
            } elseif ($path === 'stats') {
                // Get audit statistics
                $dateRange = (int)($_GET['days'] ?? 30);
                $stats = $auditService->getStatistics($dateRange);
                
                echo json_encode([
                    'success' => true,
                    'data' => $stats
                ]);
                
            } elseif ($path === 'export') {
                // Export audit logs
                $filters = [
                    'start_date' => $_GET['start_date'] ?? null,
                    'end_date' => $_GET['end_date'] ?? null,
                    'user_id' => $_GET['user_id'] ?? null,
                    'action_type' => $_GET['action_type'] ?? null,
                    'resource_type' => $_GET['resource_type'] ?? null,
                    'resource_id' => $_GET['resource_id'] ?? null
                ];
                
                $filters = array_filter($filters, function($value) {
                    return $value !== null;
                });
                
                $format = $_GET['format'] ?? 'csv';
                
                // Log the export action
                $auditService->log('export', 'audit_logs', 'export', 
                    "Exported audit logs in {$format} format", $filters);
                
                $exportData = $auditService->exportLogs($filters, $format);
                
                // Set appropriate headers based on format
                switch ($format) {
                    case 'csv':
                        header('Content-Type: text/csv');
                        header('Content-Disposition: attachment; filename="audit_logs_' . date('Ymd_His') . '.csv"');
                        echo $exportData;
                        exit;
                        
                    case 'json':
                        header('Content-Type: application/json');
                        header('Content-Disposition: attachment; filename="audit_logs_' . date('Ymd_His') . '.json"');
                        echo $exportData;
                        exit;
                        
                    case 'pdf':
                        // For PDF, we'll return the HTML that needs to be converted
                        echo json_encode([
                            'success' => true,
                            'html' => $exportData,
                            'filename' => 'audit_logs_' . date('Ymd_His') . '.pdf'
                        ]);
                        break;
                        
                    default:
                        http_response_code(400);
                        echo json_encode(['error' => 'Invalid export format']);
                        exit;
                }
                
            } elseif ($path === 'action-types') {
                // Get available action types
                $stmt = $auditService->getDb()->prepare("
                    SELECT DISTINCT action_type 
                    FROM audit_logs 
                    ORDER BY action_type
                ");
                $stmt->execute();
                $actionTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                echo json_encode([
                    'success' => true,
                    'data' => $actionTypes
                ]);
                
            } elseif ($path === 'resource-types') {
                // Get available resource types
                $stmt = $auditService->getDb()->prepare("
                    SELECT DISTINCT resource_type 
                    FROM audit_logs 
                    ORDER BY resource_type
                ");
                $stmt->execute();
                $resourceTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                echo json_encode([
                    'success' => true,
                    'data' => $resourceTypes
                ]);
                
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Not found']);
            }
            break;
            
        case 'POST':
            if ($path === 'verify') {
                // Verify log integrity
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (!isset($data['log_id'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Log ID required']);
                    exit;
                }
                
                // Get the log entry
                $stmt = $auditService->getDb()->prepare("
                    SELECT * FROM audit_logs WHERE log_id = ?
                ");
                $stmt->execute([$data['log_id']]);
                $log = $stmt->fetch();
                
                if (!$log) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Log not found']);
                    exit;
                }
                
                $isValid = $auditService->verifyIntegrity($log);
                
                echo json_encode([
                    'success' => true,
                    'valid' => $isValid,
                    'log_id' => $data['log_id']
                ]);
                
            } elseif ($path === 'archive') {
                // Archive old logs
                $data = json_decode(file_get_contents('php://input'), true);
                $olderThanDays = $data['older_than_days'] ?? 730; // Default 2 years
                
                // Log the archive action
                $auditService->log('archive', 'audit_logs', 'archive', 
                    "Archived logs older than {$olderThanDays} days");
                
                $archivedCount = $auditService->archiveLogs($olderThanDays);
                
                echo json_encode([
                    'success' => true,
                    'archived_count' => $archivedCount,
                    'message' => "Archived {$archivedCount} audit log entries"
                ]);
                
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