<?php

declare(strict_types=1);

/**
 * SuperManager API Endpoint
 *
 * Handles SuperManager dashboard and multi-clinic oversight API requests.
 * The SuperManager role is associated with 'Manager' role type with elevated
 * permissions across multiple establishments/clinics.
 *
 * Routes:
 *   GET  /api/v1/supermanager/dashboard           - Get full dashboard data
 *   GET  /api/v1/supermanager/stats               - Get overview statistics only
 *   GET  /api/v1/supermanager/clinics             - Get clinic performance
 *   GET  /api/v1/supermanager/clinics/comparison  - Get clinic comparison
 *   GET  /api/v1/supermanager/staff               - Get staff overview
 *   GET  /api/v1/supermanager/credentials/expiring - Get expiring credentials
 *   GET  /api/v1/supermanager/training/overdue    - Get overdue training
 *   GET  /api/v1/supermanager/approvals           - Get pending approvals
 *   POST /api/v1/supermanager/approve/:id         - Approve a pending request
 *   POST /api/v1/supermanager/deny/:id            - Deny a pending request
 *
 * @package SafeShift\API\v1
 */

use ViewModel\SuperManagerViewModel;
use ViewModel\Core\ApiResponse;

// Require ViewModel files
require_once __DIR__ . '/../../ViewModel/Core/ApiResponse.php';
require_once __DIR__ . '/../../ViewModel/SuperManagerViewModel.php';
require_once __DIR__ . '/../../model/Repositories/SuperManagerRepository.php';

/**
 * Handle supermanager route
 *
 * @param string $subPath The path after /supermanager/
 * @param string $method HTTP method
 */
function handleSuperManagerRoute(string $subPath, string $method): void
{
    // Get PDO instance using the db helper function
    $pdo = \App\db\pdo();

    // Require authentication - check for user object with user_id
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['user_id'])) {
        ApiResponse::send(ApiResponse::unauthorized('Authentication required'), 401);
        return;
    }

    // Initialize ViewModel and set user context
    $viewModel = new SuperManagerViewModel($pdo);
    $viewModel->setCurrentUser($_SESSION['user']['user_id']);
    
    // Set user roles from session
    $roles = [];
    if (isset($_SESSION['user']['roles']) && is_array($_SESSION['user']['roles'])) {
        $roles = $_SESSION['user']['roles'];
    } elseif (isset($_SESSION['roles']) && is_array($_SESSION['roles'])) {
        $roles = $_SESSION['roles'];
    }
    $viewModel->setCurrentUserRoles($roles);

    // Parse subpath for routing
    $segments = array_filter(explode('/', $subPath));
    $segments = array_values($segments);
    
    $action = $segments[0] ?? '';
    $subAction = $segments[1] ?? '';

    // Route based on method and path
    switch ($method) {
        case 'GET':
            handleGetSuperManager($viewModel, $action, $subAction, $segments);
            break;
            
        case 'POST':
            handlePostSuperManager($viewModel, $action, $subAction, $segments);
            break;
            
        default:
            ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
    }
}

/**
 * Handle GET requests for supermanager
 * 
 * @param SuperManagerViewModel $viewModel
 * @param string $action First path segment
 * @param string $subAction Second path segment
 * @param array $segments All path segments
 */
function handleGetSuperManager(SuperManagerViewModel $viewModel, string $action, string $subAction, array $segments): void
{
    // GET /supermanager or /supermanager/dashboard - Full dashboard data
    if (empty($action) || $action === 'dashboard') {
        $result = $viewModel->getDashboardData();
        sendSuperManagerResponse($result);
        return;
    }
    
    // GET /supermanager/stats - Overview statistics only
    if ($action === 'stats') {
        $result = $viewModel->getOverviewStats();
        sendSuperManagerResponse($result);
        return;
    }
    
    // GET /supermanager/clinics - Clinic performance
    if ($action === 'clinics' && empty($subAction)) {
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
        $result = $viewModel->getClinicPerformance($limit);
        sendSuperManagerResponse($result);
        return;
    }
    
    // GET /supermanager/clinics/comparison - Clinic comparison
    if ($action === 'clinics' && $subAction === 'comparison') {
        $result = $viewModel->getClinicComparison();
        sendSuperManagerResponse($result);
        return;
    }
    
    // GET /supermanager/staff - Staff overview
    if ($action === 'staff') {
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $result = $viewModel->getStaffOverview($limit);
        sendSuperManagerResponse($result);
        return;
    }
    
    // GET /supermanager/credentials/expiring - Expiring credentials
    if ($action === 'credentials' && $subAction === 'expiring') {
        $daysAhead = min(365, max(1, (int)($_GET['days'] ?? 30)));
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
        $result = $viewModel->getExpiringCredentials($daysAhead, $limit);
        sendSuperManagerResponse($result);
        return;
    }
    
    // GET /supermanager/training/overdue - Overdue training
    if ($action === 'training' && $subAction === 'overdue') {
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
        $result = $viewModel->getTrainingOverdue($limit);
        sendSuperManagerResponse($result);
        return;
    }
    
    // GET /supermanager/approvals - Pending approvals
    if ($action === 'approvals') {
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
        $result = $viewModel->getPendingApprovals($limit);
        sendSuperManagerResponse($result);
        return;
    }
    
    // Invalid endpoint
    ApiResponse::send(ApiResponse::notFound('Invalid supermanager endpoint'), 404);
}

/**
 * Handle POST requests for supermanager
 * 
 * @param SuperManagerViewModel $viewModel
 * @param string $action First path segment
 * @param string $subAction Second path segment
 * @param array $segments All path segments
 */
function handlePostSuperManager(SuperManagerViewModel $viewModel, string $action, string $subAction, array $segments): void
{
    // Parse JSON body
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    
    // POST /supermanager/approve/:id - Approve a pending request
    if ($action === 'approve' && !empty($subAction)) {
        $requestId = $subAction;
        $type = $body['type'] ?? '';
        
        if (empty($type)) {
            ApiResponse::send(ApiResponse::badRequest('Request type is required'), 400);
            return;
        }
        
        $response = $viewModel->approvePending($requestId, $type);
        sendSuperManagerResponse($response);
        return;
    }
    
    // POST /supermanager/deny/:id - Deny a pending request
    if ($action === 'deny' && !empty($subAction)) {
        $requestId = $subAction;
        $type = $body['type'] ?? '';
        $reason = $body['reason'] ?? '';
        
        if (empty($type)) {
            ApiResponse::send(ApiResponse::badRequest('Request type is required'), 400);
            return;
        }
        
        $response = $viewModel->denyPending($requestId, $type, $reason);
        sendSuperManagerResponse($response);
        return;
    }
    
    // Invalid endpoint
    ApiResponse::send(ApiResponse::notFound('Invalid supermanager endpoint'), 404);
}

/**
 * Send API response with appropriate HTTP status code
 * 
 * @param array $result Result from ViewModel method
 */
function sendSuperManagerResponse(array $result): void
{
    $statusCode = 200;
    
    if (!$result['success']) {
        // Determine status code based on error type
        if (isset($result['errors']['authentication'])) {
            $statusCode = 401;
        } elseif (isset($result['errors']['authorization'])) {
            $statusCode = 403;
        } elseif (isset($result['errors']['resource'])) {
            $statusCode = 404;
        } elseif (isset($result['errors'])) {
            // Check for validation errors
            $statusCode = 422;
        } else {
            $statusCode = 500;
        }
    }
    
    ApiResponse::send($result, $statusCode);
}
