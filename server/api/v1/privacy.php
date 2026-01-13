<?php

declare(strict_types=1);

/**
 * Privacy Officer API Endpoint
 *
 * Handles privacy officer dashboard and HIPAA compliance API requests.
 * Routes:
 *   GET  /api/v1/privacy/dashboard         - Get full dashboard data
 *   GET  /api/v1/privacy/compliance/kpis   - Get compliance KPIs
 *   GET  /api/v1/privacy/access-logs       - Get PHI access audit logs
 *   GET  /api/v1/privacy/consents          - Get consent status overview
 *   GET  /api/v1/privacy/regulatory        - Get regulatory updates
 *   GET  /api/v1/privacy/breaches          - Get breach incidents
 *   GET  /api/v1/privacy/training          - Get training compliance
 *
 * @package SafeShift\API\v1
 */

use ViewModel\PrivacyOfficerViewModel;
use ViewModel\Core\ApiResponse;

// Require ViewModel files
require_once __DIR__ . '/../../ViewModel/Core/BaseViewModel.php';
require_once __DIR__ . '/../../ViewModel/Core/ApiResponse.php';
require_once __DIR__ . '/../../ViewModel/PrivacyOfficerViewModel.php';
require_once __DIR__ . '/../../model/Repositories/PrivacyOfficerRepository.php';

/**
 * Handle privacy officer route
 *
 * @param string $subPath The path after /privacy/
 * @param string $method HTTP method
 */
function handlePrivacyRoute(string $subPath, string $method): void
{
    // Get PDO instance using the db helper function
    $pdo = \App\db\pdo();

    // Require authentication - check for user object with user_id
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['user_id'])) {
        ApiResponse::send(ApiResponse::unauthorized('Authentication required'), 401);
        return;
    }

    // Initialize ViewModel
    $viewModel = new PrivacyOfficerViewModel($pdo);

    // Parse subpath for routing
    $segments = array_filter(explode('/', $subPath));
    $segments = array_values($segments);
    
    $action = $segments[0] ?? '';
    $subAction = $segments[1] ?? '';

    // Route based on method and path
    switch ($method) {
        case 'GET':
            handleGetPrivacy($viewModel, $action, $subAction);
            break;
            
        default:
            ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
    }
}

/**
 * Handle GET requests for privacy officer
 * 
 * @param PrivacyOfficerViewModel $viewModel
 * @param string $action First path segment
 * @param string $subAction Second path segment
 */
function handleGetPrivacy(PrivacyOfficerViewModel $viewModel, string $action, string $subAction): void
{
    // GET /privacy or /privacy/dashboard - Full dashboard data
    if (empty($action) || $action === 'dashboard') {
        $result = $viewModel->getDashboardData();
        sendPrivacyResponse($result);
        return;
    }
    
    // GET /privacy/compliance/kpis - Compliance KPIs only
    if ($action === 'compliance' && $subAction === 'kpis') {
        $result = $viewModel->getComplianceKPIs();
        sendPrivacyResponse($result);
        return;
    }
    
    // GET /privacy/access-logs - PHI access audit logs
    if ($action === 'access-logs') {
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $result = $viewModel->getPHIAccessLogs($limit);
        sendPrivacyResponse($result);
        return;
    }
    
    // GET /privacy/consents - Consent status overview
    if ($action === 'consents') {
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $result = $viewModel->getConsentStatus($limit);
        sendPrivacyResponse($result);
        return;
    }
    
    // GET /privacy/regulatory - Regulatory updates
    if ($action === 'regulatory') {
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
        $result = $viewModel->getRegulatoryUpdates($limit);
        sendPrivacyResponse($result);
        return;
    }
    
    // GET /privacy/breaches - Breach incidents
    if ($action === 'breaches') {
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
        $result = $viewModel->getBreachIncidents($limit);
        sendPrivacyResponse($result);
        return;
    }
    
    // GET /privacy/training - Training compliance
    if ($action === 'training') {
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $result = $viewModel->getTrainingCompliance($limit);
        sendPrivacyResponse($result);
        return;
    }
    
    // Invalid endpoint
    ApiResponse::send(ApiResponse::notFound('Invalid privacy officer endpoint'), 404);
}

/**
 * Send API response with appropriate HTTP status code
 * 
 * @param array $result Result from ViewModel method
 */
function sendPrivacyResponse(array $result): void
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
