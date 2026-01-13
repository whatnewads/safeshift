<?php

declare(strict_types=1);

/**
 * Admin API Endpoint
 *
 * Handles admin dashboard, compliance, training, and OSHA data requests.
 * Routes:
 *   GET  /api/v1/admin                - Get admin dashboard (full data)
 *   GET  /api/v1/admin/stats          - Get case statistics only
 *   GET  /api/v1/admin/cases          - Get recent cases
 *   GET  /api/v1/admin/patient-flow   - Get patient flow metrics
 *   GET  /api/v1/admin/sites          - Get site performance
 *   GET  /api/v1/admin/providers      - Get provider performance
 *   GET  /api/v1/admin/staff          - Get staff list
 *   GET  /api/v1/admin/clearance      - Get clearance statistics
 *   GET  /api/v1/admin/compliance     - Get compliance alerts
 *   PUT  /api/v1/admin/compliance/:id/acknowledge - Acknowledge alert
 *   GET  /api/v1/admin/training       - Get training modules
 *   GET  /api/v1/admin/credentials    - Get expiring credentials
 *   GET  /api/v1/admin/osha/300       - Get OSHA 300 Log (READ-ONLY)
 *   GET  /api/v1/admin/osha/300a      - Get OSHA 300A Summary (READ-ONLY)
 *
 * @package API\v1
 */

require_once __DIR__ . '/../../model/Core/Database.php';
require_once __DIR__ . '/../../model/Repositories/AdminRepository.php';
require_once __DIR__ . '/../../model/Repositories/CaseRepository.php';
require_once __DIR__ . '/../../ViewModel/AdminViewModel.php';
require_once __DIR__ . '/../../ViewModel/Core/ApiResponse.php';

use Model\Core\Database;
use ViewModel\AdminViewModel;
use ViewModel\Core\ApiResponse;

/**
 * Route handler called by api/v1/index.php
 * 
 * @param string $subPath The path after /api/v1/admin/
 * @param string $method The HTTP method
 */
function handleAdminRoute(string $subPath, string $method): void
{
    // Parse path segments from subPath
    $segments = array_filter(explode('/', $subPath));
    $segments = array_values($segments);
    
    try {
        // Initialize database and view model
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        $viewModel = new AdminViewModel($pdo);
        
        // Get user from session (session already started by bootstrap)
        // User data is stored in $_SESSION['user'] object (consistent with BaseViewModel)
        $userId = $_SESSION['user']['user_id'] ?? null;
        $clinicId = $_SESSION['user']['clinic_id'] ?? $_SESSION['clinic_id'] ?? null;
        
        if ($userId) {
            $viewModel->setCurrentUser($userId);
        }
        if ($clinicId) {
            $viewModel->setCurrentClinic($clinicId);
        }
        
        // Route the request
        switch ($method) {
            case 'GET':
                handleAdminGetRequest($viewModel, $segments);
                break;
                
            case 'PUT':
                handleAdminPutRequest($viewModel, $segments);
                break;
                
            default:
                ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        }
    } catch (\Exception $e) {
        error_log("Admin API error: " . $e->getMessage());
        ApiResponse::send(ApiResponse::serverError('Internal server error'), 500);
    }
}

/**
 * Handle GET requests
 */
function handleAdminGetRequest(AdminViewModel $viewModel, array $segments): void
{
    // GET /admin - Admin dashboard (full data)
    if (empty($segments)) {
        $response = $viewModel->getAdminDashboard();
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    $action = $segments[0];
    
    // GET /admin/stats - Case statistics only
    if ($action === 'stats') {
        $response = $viewModel->getCaseStats();
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // GET /admin/cases - Recent cases list
    if ($action === 'cases') {
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 10)));
        $response = $viewModel->getRecentCases($limit);
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // GET /admin/patient-flow - Patient flow metrics
    if ($action === 'patient-flow') {
        $response = $viewModel->getPatientFlowMetrics();
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // GET /admin/sites - Site performance metrics
    if ($action === 'sites') {
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
        $response = $viewModel->getSitePerformance($limit);
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // GET /admin/providers - Provider performance metrics
    if ($action === 'providers') {
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
        $response = $viewModel->getProviderPerformance($limit);
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // GET /admin/staff - Staff list
    if ($action === 'staff') {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? $_GET['perPage'] ?? 50)));
        $response = $viewModel->getStaffList($page, $perPage);
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // GET /admin/clearance - Clearance statistics
    if ($action === 'clearance') {
        $response = $viewModel->getClearanceStats();
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // GET /admin/compliance - Compliance alerts
    if ($action === 'compliance') {
        $status = $_GET['status'] ?? null;
        $priority = $_GET['priority'] ?? null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? $_GET['perPage'] ?? 20)));
        
        $response = $viewModel->getComplianceAlerts($status, $priority, $page, $perPage);
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // GET /admin/training - Training modules
    if ($action === 'training') {
        $response = $viewModel->getTrainingModules();
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // GET /admin/credentials - Expiring credentials
    if ($action === 'credentials') {
        $daysAhead = (int)($_GET['days'] ?? 60);
        $response = $viewModel->getExpiringCredentials($daysAhead);
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // GET /admin/osha/300 or /admin/osha/300a
    if ($action === 'osha') {
        $subAction = $segments[1] ?? null;
        $year = isset($_GET['year']) ? (int)$_GET['year'] : null;
        
        if ($subAction === '300') {
            $response = $viewModel->getOsha300Log($year);
            ApiResponse::send($response, $response['status'] ?? 200);
            return;
        }
        
        if ($subAction === '300a') {
            $response = $viewModel->getOsha300ASummary($year);
            ApiResponse::send($response, $response['status'] ?? 200);
            return;
        }
        
        ApiResponse::send(ApiResponse::notFound('Invalid OSHA endpoint'), 404);
        return;
    }
    
    ApiResponse::send(ApiResponse::notFound('Invalid endpoint'), 404);
}

/**
 * Handle PUT requests
 */
function handleAdminPutRequest(AdminViewModel $viewModel, array $segments): void
{
    // PUT /admin/compliance/:id/acknowledge
    if (isset($segments[0]) && $segments[0] === 'compliance' 
        && isset($segments[1]) && isset($segments[2]) && $segments[2] === 'acknowledge') {
        $alertId = $segments[1];
        $response = $viewModel->acknowledgeComplianceAlert($alertId);
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    ApiResponse::send(ApiResponse::notFound('Invalid endpoint'), 404);
}
