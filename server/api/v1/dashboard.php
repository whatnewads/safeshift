<?php

declare(strict_types=1);

/**
 * Dashboard API Endpoint
 *
 * Handles dashboard data requests for different roles.
 * Routes:
 *   GET  /api/v1/dashboard              - Get role-specific dashboard
 *   GET  /api/v1/dashboard/manager      - Get manager dashboard
 *   GET  /api/v1/dashboard/stats        - Get dashboard statistics
 *   GET  /api/v1/dashboard/cases        - Get cases list
 *   GET  /api/v1/dashboard/cases/:id    - Get single case
 *   POST /api/v1/dashboard/cases/:id/flags - Add flag to case
 *   PUT  /api/v1/dashboard/flags/:id/resolve - Resolve a flag
 *
 * @package API\v1
 */

require_once __DIR__ . '/../../model/Core/Database.php';
require_once __DIR__ . '/../../model/Entities/Encounter.php';
require_once __DIR__ . '/../../model/Repositories/CaseRepository.php';
require_once __DIR__ . '/../../model/Repositories/EncounterRepository.php';
require_once __DIR__ . '/../../ViewModel/DashboardViewModel.php';
require_once __DIR__ . '/../../ViewModel/Core/ApiResponse.php';
require_once __DIR__ . '/../../core/Services/DashboardLogger.php';

use Model\Core\Database;
use ViewModel\DashboardViewModel;
use ViewModel\Core\ApiResponse;
use Core\Services\DashboardLogger;

/**
 * Route handler called by api/v1/index.php
 *
 * @param string $subPath The path after /api/v1/dashboard/
 * @param string $method The HTTP method
 */
function handleDashboardRoute(string $subPath, string $method): void
{
    // Parse path segments from subPath
    $segments = array_filter(explode('/', $subPath));
    $segments = array_values($segments);
    
    // Initialize dashboard logger
    $logger = DashboardLogger::getInstance();
    $logger->startRequest();
    
    try {
        // Initialize database and view model
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        $viewModel = new DashboardViewModel($pdo);
        
        // Get user from session (session already started by bootstrap)
        // User data is stored in $_SESSION['user'] object (consistent with BaseViewModel)
        $userId = $_SESSION['user']['user_id'] ?? null;
        $clinicId = $_SESSION['user']['clinic_id'] ?? $_SESSION['clinic_id'] ?? null;
        $role = $_SESSION['user']['role'] ?? $_SESSION['user']['primary_role'] ?? null;
        
        if ($userId) {
            $viewModel->setCurrentUser($userId);
            // Log dashboard access
            $dashboardType = determineDashboardType($segments, $role);
            $logger->logDashboardAccess((int)$userId, $dashboardType, [
                'clinic_id' => $clinicId,
                'role' => $role,
                'method' => $method,
                'path' => $subPath,
            ]);
        }
        if ($clinicId) {
            $viewModel->setCurrentClinic($clinicId);
        }
        if ($role) {
            $viewModel->setCurrentRole($role);
        }
        
        // Route the request
        switch ($method) {
            case 'GET':
                handleDashboardGetRequest($viewModel, $segments, $logger, (int)$userId);
                break;
                
            case 'POST':
                handleDashboardPostRequest($viewModel, $segments, $logger, (int)$userId);
                break;
                
            case 'PUT':
                handleDashboardPutRequest($viewModel, $segments, $logger, (int)$userId);
                break;
                
            default:
                $logger->logError('INVALID_METHOD', 'Method not allowed: ' . $method, ['path' => $subPath]);
                ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        }
    } catch (\Exception $e) {
        error_log("Dashboard API error: " . $e->getMessage());
        $logger->logError('API_EXCEPTION', $e->getMessage(), [
            'path' => $subPath,
            'method' => $method,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
        ApiResponse::send(ApiResponse::serverError('Internal server error'), 500);
    }
}

/**
 * Determine dashboard type from segments and role
 */
function determineDashboardType(array $segments, ?string $role): string
{
    if (empty($segments)) {
        // Role-specific dashboard
        switch ($role) {
            case 'manager':
            case 'super_manager':
                return DashboardLogger::DASH_MANAGER;
            case 'clinical_provider':
            case 'doctor':
                return DashboardLogger::DASH_CLINICAL;
            case 'technician':
                return DashboardLogger::DASH_TECHNICIAN;
            case 'registration':
                return DashboardLogger::DASH_REGISTRATION;
            case 'admin':
                return DashboardLogger::DASH_ADMIN;
            default:
                return DashboardLogger::DASH_GENERIC;
        }
    }
    
    $action = $segments[0] ?? '';
    switch ($action) {
        case 'manager':
            return DashboardLogger::DASH_MANAGER;
        case 'clinical':
            return DashboardLogger::DASH_CLINICAL;
        case 'technician':
            return DashboardLogger::DASH_TECHNICIAN;
        case 'registration':
            return DashboardLogger::DASH_REGISTRATION;
        case 'admin':
            return DashboardLogger::DASH_ADMIN;
        default:
            return DashboardLogger::DASH_GENERIC;
    }
}

/**
 * Handle GET requests
 */
function handleDashboardGetRequest(DashboardViewModel $viewModel, array $segments, DashboardLogger $logger, int $userId): void
{
    $startTime = microtime(true);
    
    // GET /dashboard - Role-specific dashboard
    if (empty($segments)) {
        $logger->logMetricRequest('role_based', 'all', $userId);
        $response = $viewModel->getDashboardByRole();
        $logger->logDashboardLoad('role_based', $userId, ['getDashboardByRole']);
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    $action = $segments[0];
    
    // GET /dashboard/manager - Manager dashboard
    if ($action === 'manager') {
        $logger->logMetricRequest(DashboardLogger::DASH_MANAGER, 'full_dashboard', $userId);
        $response = $viewModel->getManagerDashboard();
        $logger->logDashboardLoad(DashboardLogger::DASH_MANAGER, $userId, ['stats', 'cases']);
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // GET /dashboard/stats - Statistics only
    if ($action === 'stats') {
        $logger->logMetricRequest('stats', 'case_stats', $userId);
        $response = $viewModel->getStats();
        $logger->logMetricCalculation('case_stats', ['query_count' => 1], microtime(true) - $startTime);
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // GET /dashboard/cases - Cases list
    if ($action === 'cases') {
        // Check if there's a case ID
        if (isset($segments[1]) && $segments[1] !== '') {
            $logger->logMetricRequest('cases', 'single_case', $userId, ['case_id' => $segments[1]]);
            $response = $viewModel->getCase($segments[1]);
            $logger->logMetricCalculation('single_case', ['query_count' => 1], microtime(true) - $startTime);
            ApiResponse::send($response, $response['status'] ?? 200);
            return;
        }
        
        $status = $_GET['status'] ?? null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? $_GET['perPage'] ?? 20)));
        
        $logger->logMetricRequest('cases', 'cases_list', $userId, [
            'status' => $status,
            'page' => $page,
            'per_page' => $perPage,
        ]);
        $response = $viewModel->getCases($status, $page, $perPage);
        $logger->logMetricCalculation('cases_list', [
            'query_count' => 2,
            'row_count' => count($response['data']['cases'] ?? []),
        ], microtime(true) - $startTime);
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // GET /dashboard/clinical - Clinical provider dashboard
    if ($action === 'clinical') {
        $logger->logMetricRequest(DashboardLogger::DASH_CLINICAL, 'full_dashboard', $userId);
        $response = $viewModel->getClinicalProviderDashboard();
        $logger->logDashboardLoad(DashboardLogger::DASH_CLINICAL, $userId, [
            'pending_encounters', 'todays_encounters', 'provider_stats'
        ]);
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // GET /dashboard/technician - Technician dashboard
    if ($action === 'technician') {
        $logger->logMetricRequest(DashboardLogger::DASH_TECHNICIAN, 'full_dashboard', $userId);
        $response = $viewModel->getTechnicianDashboard();
        $logger->logDashboardLoad(DashboardLogger::DASH_TECHNICIAN, $userId, ['task_queue', 'stats']);
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // GET /dashboard/registration - Registration dashboard
    if ($action === 'registration') {
        $logger->logMetricRequest(DashboardLogger::DASH_REGISTRATION, 'full_dashboard', $userId);
        $response = $viewModel->getRegistrationDashboard();
        $logger->logDashboardLoad(DashboardLogger::DASH_REGISTRATION, $userId, [
            'appointments', 'scheduled', 'checked_in'
        ]);
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // GET /dashboard/admin - Admin dashboard
    if ($action === 'admin') {
        $logger->logMetricRequest(DashboardLogger::DASH_ADMIN, 'full_dashboard', $userId);
        $response = $viewModel->getAdminDashboard();
        $logger->logDashboardLoad(DashboardLogger::DASH_ADMIN, $userId, [
            'compliance_alerts', 'training_due', 'regulatory_updates'
        ]);
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    $logger->logError('NOT_FOUND', 'Invalid endpoint: ' . implode('/', $segments), ['action' => $action]);
    ApiResponse::send(ApiResponse::notFound('Invalid endpoint'), 404);
}

/**
 * Handle POST requests
 */
function handleDashboardPostRequest(DashboardViewModel $viewModel, array $segments, DashboardLogger $logger, int $userId): void
{
    $startTime = microtime(true);
    
    // POST /dashboard/cases/:id/flags - Add flag to case
    if (isset($segments[0]) && $segments[0] === 'cases' && isset($segments[1]) && isset($segments[2]) && $segments[2] === 'flags') {
        $caseId = $segments[1];
        $data = getDashboardRequestBody();
        
        $logger->logMetricRequest('cases', 'add_flag', $userId, [
            'case_id' => $caseId,
            'flag_type' => $data['flag_type'] ?? $data['flagType'] ?? 'unknown',
        ]);
        
        $response = $viewModel->addCaseFlag($caseId, $data);
        
        $logger->logMetricCalculation('add_flag', [
            'query_count' => 2,
            'case_id' => $caseId,
        ], microtime(true) - $startTime);
        
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    $logger->logError('NOT_FOUND', 'Invalid POST endpoint: ' . implode('/', $segments), []);
    ApiResponse::send(ApiResponse::notFound('Invalid endpoint'), 404);
}

/**
 * Handle PUT requests
 */
function handleDashboardPutRequest(DashboardViewModel $viewModel, array $segments, DashboardLogger $logger, int $userId): void
{
    $startTime = microtime(true);
    
    // PUT /dashboard/flags/:id/resolve - Resolve a flag
    if (isset($segments[0]) && $segments[0] === 'flags' && isset($segments[1]) && isset($segments[2]) && $segments[2] === 'resolve') {
        $flagId = $segments[1];
        
        $logger->logMetricRequest('flags', 'resolve_flag', $userId, ['flag_id' => $flagId]);
        
        $response = $viewModel->resolveFlag($flagId);
        
        $logger->logMetricCalculation('resolve_flag', [
            'query_count' => 1,
            'flag_id' => $flagId,
        ], microtime(true) - $startTime);
        
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    $logger->logError('NOT_FOUND', 'Invalid PUT endpoint: ' . implode('/', $segments), []);
    ApiResponse::send(ApiResponse::notFound('Invalid endpoint'), 404);
}

/**
 * Get JSON request body
 */
function getDashboardRequestBody(): array
{
    $json = file_get_contents('php://input');
    if (empty($json)) {
        return [];
    }
    
    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }
    
    return $data;
}
