<?php
require_once '../includes/bootstrap.php';

use Core\Services\AuthService;
use Core\Services\ComplianceService;

header('Content-Type: application/json');

try {
    // Check authentication
    $authService = new AuthService();
    $user = $authService->validateToken();
    
    // Only managers and admins can access compliance monitoring
    if (!$user || !in_array($user['role'], ['tadmin', 'cadmin', 'pclinician'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $complianceService = new ComplianceService();
    $method = $_SERVER['REQUEST_METHOD'];
    $path = isset($_GET['path']) ? $_GET['path'] : '';
    
    switch ($method) {
        case 'GET':
            if ($path === 'dashboard') {
                // Get full compliance dashboard
                $dashboard = $complianceService->getComplianceDashboard();
                
                echo json_encode([
                    'success' => true,
                    'data' => $dashboard
                ]);
                
            } elseif ($path === 'kpi-history') {
                // Get history for specific KPI
                $kpiId = $_GET['kpi_id'] ?? null;
                $days = (int)($_GET['days'] ?? 30);
                
                if (!$kpiId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'KPI ID required']);
                    exit;
                }
                
                $history = $complianceService->getKPIHistory($kpiId, $days);
                
                echo json_encode([
                    'success' => true,
                    'data' => $history
                ]);
                
            } elseif ($path === 'category') {
                // Get KPIs by category
                $category = $_GET['category'] ?? null;
                
                if (!$category) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Category required']);
                    exit;
                }
                
                $kpis = $complianceService->getKPIsByCategory($category);
                
                echo json_encode([
                    'success' => true,
                    'data' => $kpis
                ]);
                
            } elseif ($path === 'alerts') {
                // Get recent alerts
                $limit = (int)($_GET['limit'] ?? 10);
                $alerts = $complianceService->getRecentAlerts($limit);
                
                echo json_encode([
                    'success' => true,
                    'data' => $alerts
                ]);
                
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
            
            if ($path === 'calculate') {
                // Manually trigger KPI calculation
                if (!in_array($user['role'], ['tadmin', 'cadmin'])) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Only admins can trigger calculations']);
                    exit;
                }
                
                $complianceService->calculateKPIs();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'KPIs calculated successfully'
                ]);
                
            } elseif ($path === 'calculate-single') {
                // Calculate single KPI
                if (!isset($data['kpi_id'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'KPI ID required']);
                    exit;
                }
                
                $result = $complianceService->calculateSingleKPI($data['kpi_id']);
                
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
                
            } elseif ($path === 'acknowledge-alert') {
                // Acknowledge an alert
                if (!isset($data['alert_id'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Alert ID required']);
                    exit;
                }
                
                $complianceService->acknowledgeAlert($data['alert_id'], $user['id']);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Alert acknowledged'
                ]);
                
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Not found']);
            }
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($path === 'update-thresholds') {
                // Update KPI thresholds
                if (!in_array($user['role'], ['tadmin', 'cadmin'])) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Only admins can update thresholds']);
                    exit;
                }
                
                if (!isset($data['kpi_id']) || !isset($data['warning']) || !isset($data['critical'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'KPI ID and thresholds required']);
                    exit;
                }
                
                $complianceService->updateKPIThresholds(
                    $data['kpi_id'],
                    $data['warning'],
                    $data['critical'],
                    $user['id']
                );
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Thresholds updated successfully'
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