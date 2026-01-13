<?php

declare(strict_types=1);

/**
 * SuperAdmin API Endpoint
 *
 * Handles super admin operations including user management, system config,
 * security incidents, audit logs, and override requests.
 *
 * Routes:
 *   GET  /api/v1/superadmin              - Get super admin dashboard
 *   GET  /api/v1/superadmin/dashboard    - Get full dashboard data
 *   GET  /api/v1/superadmin/users        - Get all users
 *   GET  /api/v1/superadmin/users/:id    - Get user by ID
 *   POST /api/v1/superadmin/users        - Create user
 *   PUT  /api/v1/superadmin/users/:id/status - Update user status
 *   POST /api/v1/superadmin/users/:id/roles  - Assign role
 *   DELETE /api/v1/superadmin/users/:id/roles/:roleId - Remove role
 *   GET  /api/v1/superadmin/roles        - Get all roles
 *   GET  /api/v1/superadmin/clinics      - Get all clinics
 *   POST /api/v1/superadmin/clinics      - Create clinic
 *   GET  /api/v1/superadmin/audit        - Get audit logs
 *   GET  /api/v1/superadmin/audit/stats  - Get audit statistics
 *   GET  /api/v1/superadmin/incidents    - Get security incidents
 *   POST /api/v1/superadmin/incidents    - Create security incident
 *   PUT  /api/v1/superadmin/incidents/:id/resolve - Resolve incident
 *   GET  /api/v1/superadmin/overrides    - Get override requests
 *   PUT  /api/v1/superadmin/overrides/:id/approve - Approve override request
 *   PUT  /api/v1/superadmin/overrides/:id/deny    - Deny override request
 *
 * @package API\v1
 */

require_once __DIR__ . '/../../model/Core/Database.php';
require_once __DIR__ . '/../../model/Repositories/SuperAdminRepository.php';
require_once __DIR__ . '/../../ViewModel/SuperAdminViewModel.php';
require_once __DIR__ . '/../../ViewModel/Core/ApiResponse.php';

use Model\Core\Database;
use ViewModel\SuperAdminViewModel;
use ViewModel\Core\ApiResponse;

/**
 * Route handler called by api/v1/index.php
 * 
 * @param string $subPath The path after /api/v1/superadmin/
 * @param string $method The HTTP method
 */
function handleSuperAdminRoute(string $subPath, string $method): void
{
    // Parse path segments from subPath
    $segments = array_filter(explode('/', $subPath));
    $segments = array_values($segments);
    
    try {
        // Initialize database and view model
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        $viewModel = new SuperAdminViewModel($pdo);
        
        // Get user from session (user data stored in $_SESSION['user'] object)
        $userId = $_SESSION['user']['user_id'] ?? null;
        
        if ($userId) {
            $viewModel->setCurrentUser($userId);
        }
        
        // Route the request
        switch ($method) {
            case 'GET':
                handleSuperAdminGetRequest($viewModel, $segments);
                break;
                
            case 'POST':
                handleSuperAdminPostRequest($viewModel, $segments);
                break;
                
            case 'PUT':
                handleSuperAdminPutRequest($viewModel, $segments);
                break;
                
            case 'DELETE':
                handleSuperAdminDeleteRequest($viewModel, $segments);
                break;
                
            default:
                ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        }
    } catch (\Exception $e) {
        error_log("SuperAdmin API error: " . $e->getMessage());
        ApiResponse::send(ApiResponse::serverError('Internal server error'), 500);
    }
}

/**
 * Handle GET requests
 */
function handleSuperAdminGetRequest(SuperAdminViewModel $viewModel, array $segments): void
{
    // GET /superadmin - Dashboard stats only
    if (empty($segments)) {
        $response = $viewModel->getSuperAdminDashboard();
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    $action = $segments[0];
    
    // GET /superadmin/dashboard - Full dashboard data
    if ($action === 'dashboard') {
        $response = $viewModel->getFullDashboardData();
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // GET /superadmin/users
    if ($action === 'users') {
        if (isset($segments[1])) {
            // GET /superadmin/users/:id
            $userId = $segments[1];
            $response = $viewModel->getUser($userId);
            ApiResponse::send($response, $response['status'] ?? 200);
            return;
        }
        
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? $_GET['perPage'] ?? 50)));
        
        $response = $viewModel->getUsers($page, $perPage);
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // GET /superadmin/roles
    if ($action === 'roles') {
        $response = $viewModel->getRoles();
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // GET /superadmin/clinics
    if ($action === 'clinics') {
        $response = $viewModel->getClinics();
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // GET /superadmin/audit or /superadmin/audit/stats
    if ($action === 'audit') {
        if (isset($segments[1]) && $segments[1] === 'stats') {
            $date = $_GET['date'] ?? null;
            $response = $viewModel->getAuditStats($date);
            ApiResponse::send($response, $response['status'] ?? 200);
            return;
        }
        
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(500, max(1, (int)($_GET['per_page'] ?? $_GET['perPage'] ?? 100)));
        $filters = [
            'userId' => $_GET['userId'] ?? null,
            'action' => $_GET['action'] ?? null,
            'startDate' => $_GET['startDate'] ?? null,
            'endDate' => $_GET['endDate'] ?? null,
        ];
        
        $response = $viewModel->getAuditLogs($filters, $page, $perPage);
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // GET /superadmin/incidents
    if ($action === 'incidents') {
        $status = $_GET['status'] ?? null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? $_GET['perPage'] ?? 50)));
        
        $response = $viewModel->getSecurityIncidents($status, $page, $perPage);
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // GET /superadmin/overrides
    if ($action === 'overrides') {
        $status = $_GET['status'] ?? null;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? $_GET['perPage'] ?? 50)));
        
        $response = $viewModel->getOverrideRequests($status, $page, $perPage);
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    ApiResponse::send(ApiResponse::notFound('Invalid endpoint'), 404);
}

/**
 * Handle POST requests
 */
function handleSuperAdminPostRequest(SuperAdminViewModel $viewModel, array $segments): void
{
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    
    $action = $segments[0] ?? null;
    
    // POST /superadmin/users
    if ($action === 'users') {
        // POST /superadmin/users/:id/roles - Assign role
        if (isset($segments[1]) && isset($segments[2]) && $segments[2] === 'roles') {
            $userId = $segments[1];
            $roleId = $input['roleId'] ?? '';
            
            if (empty($roleId)) {
                ApiResponse::send(ApiResponse::badRequest('Role ID is required'), 400);
                return;
            }
            
            $response = $viewModel->assignRole($userId, $roleId);
            ApiResponse::send($response, $response['status'] ?? 200);
            return;
        }
        
        // POST /superadmin/users - Create user
        $response = $viewModel->createUser($input);
        ApiResponse::send($response, $response['status'] ?? 201);
        return;
    }
    
    // POST /superadmin/clinics
    if ($action === 'clinics') {
        $response = $viewModel->createClinic($input);
        ApiResponse::send($response, $response['status'] ?? 201);
        return;
    }
    
    // POST /superadmin/incidents
    if ($action === 'incidents') {
        $response = $viewModel->createSecurityIncident($input);
        ApiResponse::send($response, $response['status'] ?? 201);
        return;
    }
    
    ApiResponse::send(ApiResponse::notFound('Invalid endpoint'), 404);
}

/**
 * Handle PUT requests
 */
function handleSuperAdminPutRequest(SuperAdminViewModel $viewModel, array $segments): void
{
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    
    $action = $segments[0] ?? null;
    
    // PUT /superadmin/users/:id/status
    if ($action === 'users' && isset($segments[1]) && isset($segments[2]) && $segments[2] === 'status') {
        $userId = $segments[1];
        $isActive = (bool)($input['isActive'] ?? $input['is_active'] ?? false);
        
        $response = $viewModel->updateUserStatus($userId, $isActive);
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // PUT /superadmin/incidents/:id/resolve
    if ($action === 'incidents' && isset($segments[1]) && isset($segments[2]) && $segments[2] === 'resolve') {
        $incidentId = $segments[1];
        $resolutionNotes = $input['resolutionNotes'] ?? $input['resolution_notes'] ?? '';
        
        $response = $viewModel->resolveSecurityIncident($incidentId, $resolutionNotes);
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // PUT /superadmin/overrides/:id/approve
    if ($action === 'overrides' && isset($segments[1]) && isset($segments[2]) && $segments[2] === 'approve') {
        $requestId = $segments[1];
        $notes = $input['notes'] ?? $input['resolution_notes'] ?? '';
        
        $response = $viewModel->approveOverrideRequest($requestId, $notes);
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // PUT /superadmin/overrides/:id/deny
    if ($action === 'overrides' && isset($segments[1]) && isset($segments[2]) && $segments[2] === 'deny') {
        $requestId = $segments[1];
        $reason = $input['reason'] ?? $input['resolution_notes'] ?? '';
        
        $response = $viewModel->denyOverrideRequest($requestId, $reason);
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    ApiResponse::send(ApiResponse::notFound('Invalid endpoint'), 404);
}

/**
 * Handle DELETE requests
 */
function handleSuperAdminDeleteRequest(SuperAdminViewModel $viewModel, array $segments): void
{
    $action = $segments[0] ?? null;
    
    // DELETE /superadmin/users/:id/roles/:roleId
    if ($action === 'users' && isset($segments[1]) && isset($segments[2]) && $segments[2] === 'roles' && isset($segments[3])) {
        $userId = $segments[1];
        $roleId = $segments[3];
        
        $response = $viewModel->removeRole($userId, $roleId);
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    ApiResponse::send(ApiResponse::notFound('Invalid endpoint'), 404);
}
