<?php

declare(strict_types=1);

/**
 * Security Officer API Endpoint
 *
 * Handles security officer dashboard and security monitoring API requests.
 * Routes:
 *   GET  /api/v1/security/dashboard      - Get full dashboard data
 *   GET  /api/v1/security/stats          - Get security statistics
 *   GET  /api/v1/security/audit          - Get audit events
 *   GET  /api/v1/security/failed-logins  - Get failed login attempts
 *   GET  /api/v1/security/mfa            - Get MFA status/compliance
 *   GET  /api/v1/security/sessions       - Get active sessions
 *   GET  /api/v1/security/alerts         - Get security alerts
 *   GET  /api/v1/security/devices        - Get user devices
 *
 * @package SafeShift\API\v1
 */

use ViewModel\SecurityOfficerViewModel;
use ViewModel\Core\ApiResponse;

// Require ViewModel files
require_once __DIR__ . '/../../ViewModel/Core/BaseViewModel.php';
require_once __DIR__ . '/../../ViewModel/Core/ApiResponse.php';
require_once __DIR__ . '/../../ViewModel/SecurityOfficerViewModel.php';
require_once __DIR__ . '/../../model/Repositories/SecurityOfficerRepository.php';

/**
 * Handle security officer route
 *
 * @param string $subPath The path after /security/
 * @param string $method HTTP method
 */
function handleSecurityRoute(string $subPath, string $method): void
{
    // Get PDO instance using the db helper function
    $pdo = \App\db\pdo();

    // Require authentication - check for user object with user_id
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['user_id'])) {
        ApiResponse::send(ApiResponse::unauthorized('Authentication required'), 401);
        return;
    }

    // Initialize ViewModel
    $viewModel = new SecurityOfficerViewModel($pdo);

    // Parse subpath for routing
    $segments = array_filter(explode('/', $subPath));
    $segments = array_values($segments);
    
    $action = $segments[0] ?? '';
    $subAction = $segments[1] ?? '';

    // Route based on method and path
    switch ($method) {
        case 'GET':
            handleGetSecurity($viewModel, $action, $subAction);
            break;
            
        default:
            ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
    }
}

/**
 * Handle GET requests for security officer
 * 
 * @param SecurityOfficerViewModel $viewModel
 * @param string $action First path segment
 * @param string $subAction Second path segment
 */
function handleGetSecurity(SecurityOfficerViewModel $viewModel, string $action, string $subAction): void
{
    // GET /security or /security/dashboard - Full dashboard data
    if (empty($action) || $action === 'dashboard') {
        $result = $viewModel->getDashboardData();
        sendSecurityResponse($result);
        return;
    }
    
    // GET /security/stats - Security statistics only
    if ($action === 'stats') {
        $result = $viewModel->getSecurityStats();
        sendSecurityResponse($result);
        return;
    }
    
    // GET /security/audit - Audit events
    if ($action === 'audit') {
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $result = $viewModel->getAuditEvents($limit);
        sendSecurityResponse($result);
        return;
    }
    
    // GET /security/failed-logins - Failed login attempts
    if ($action === 'failed-logins') {
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $result = $viewModel->getFailedLoginAttempts($limit);
        sendSecurityResponse($result);
        return;
    }
    
    // GET /security/mfa - MFA status/compliance
    if ($action === 'mfa') {
        $result = $viewModel->getMFAStatus();
        sendSecurityResponse($result);
        return;
    }
    
    // GET /security/sessions - Active sessions
    if ($action === 'sessions') {
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $result = $viewModel->getActiveSessions($limit);
        sendSecurityResponse($result);
        return;
    }
    
    // GET /security/alerts - Security alerts
    if ($action === 'alerts') {
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $result = $viewModel->getSecurityAlerts($limit);
        sendSecurityResponse($result);
        return;
    }
    
    // GET /security/devices - User devices
    if ($action === 'devices') {
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $result = $viewModel->getUserDevices($limit);
        sendSecurityResponse($result);
        return;
    }
    
    // Invalid endpoint
    ApiResponse::send(ApiResponse::notFound('Invalid security officer endpoint'), 404);
}

/**
 * Send API response with appropriate HTTP status code
 * 
 * @param array $result Result from ViewModel method
 */
function sendSecurityResponse(array $result): void
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
