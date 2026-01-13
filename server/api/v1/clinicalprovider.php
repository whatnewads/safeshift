<?php

declare(strict_types=1);

/**
 * Clinical Provider API Endpoint
 *
 * Handles clinical provider dashboard and encounter-related API requests.
 * Routes:
 *   GET  /api/v1/clinicalprovider/dashboard        - Get full dashboard data
 *   GET  /api/v1/clinicalprovider/stats            - Get provider statistics only
 *   GET  /api/v1/clinicalprovider/encounters/active  - Get active encounters
 *   GET  /api/v1/clinicalprovider/encounters/recent  - Get recent encounters
 *   GET  /api/v1/clinicalprovider/orders/pending   - Get pending orders
 *   GET  /api/v1/clinicalprovider/qa/pending       - Get pending QA reviews
 *
 * @package SafeShift\API\v1
 */

use ViewModel\ClinicalProviderViewModel;
use ViewModel\Core\ApiResponse;

// Require ViewModel files
require_once __DIR__ . '/../../ViewModel/Core/BaseViewModel.php';
require_once __DIR__ . '/../../ViewModel/Core/ApiResponse.php';
require_once __DIR__ . '/../../ViewModel/ClinicalProviderViewModel.php';
require_once __DIR__ . '/../../model/Repositories/ClinicalProviderRepository.php';

/**
 * Handle clinical provider route
 *
 * @param string $subPath The path after /clinicalprovider/
 * @param string $method HTTP method
 */
function handleClinicalProviderRoute(string $subPath, string $method): void
{
    // Get PDO instance using the db helper function
    $pdo = \App\db\pdo();

    // Require authentication - check for user object with user_id
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['user_id'])) {
        ApiResponse::send(ApiResponse::unauthorized('Authentication required'), 401);
        return;
    }

    // Initialize ViewModel
    $viewModel = new ClinicalProviderViewModel($pdo);

    // Parse subpath for routing
    $segments = array_filter(explode('/', $subPath));
    $segments = array_values($segments);
    
    $action = $segments[0] ?? '';
    $subAction = $segments[1] ?? '';

    // Route based on method and path
    switch ($method) {
        case 'GET':
            handleGetClinicalProvider($viewModel, $action, $subAction);
            break;
            
        default:
            ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
    }
}

/**
 * Handle GET requests for clinical provider
 * 
 * @param ClinicalProviderViewModel $viewModel
 * @param string $action First path segment
 * @param string $subAction Second path segment
 */
function handleGetClinicalProvider(ClinicalProviderViewModel $viewModel, string $action, string $subAction): void
{
    // GET /clinicalprovider or /clinicalprovider/dashboard - Full dashboard data
    if (empty($action) || $action === 'dashboard') {
        $result = $viewModel->getDashboardData();
        sendClinicalProviderResponse($result);
        return;
    }
    
    // GET /clinicalprovider/stats - Provider statistics only
    if ($action === 'stats') {
        $result = $viewModel->getStats();
        sendClinicalProviderResponse($result);
        return;
    }
    
    // GET /clinicalprovider/encounters/active - Active encounters
    if ($action === 'encounters' && $subAction === 'active') {
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
        $result = $viewModel->getActiveEncounters($limit);
        sendClinicalProviderResponse($result);
        return;
    }
    
    // GET /clinicalprovider/encounters/recent - Recent encounters
    if ($action === 'encounters' && $subAction === 'recent') {
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
        $result = $viewModel->getRecentEncounters($limit);
        sendClinicalProviderResponse($result);
        return;
    }
    
    // GET /clinicalprovider/orders/pending - Pending orders
    if ($action === 'orders' && $subAction === 'pending') {
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
        $result = $viewModel->getPendingOrders($limit);
        sendClinicalProviderResponse($result);
        return;
    }
    
    // GET /clinicalprovider/qa/pending - Pending QA reviews
    if ($action === 'qa' && $subAction === 'pending') {
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
        $result = $viewModel->getPendingQAReviews($limit);
        sendClinicalProviderResponse($result);
        return;
    }
    
    // Invalid endpoint
    ApiResponse::send(ApiResponse::notFound('Invalid clinical provider endpoint'), 404);
}

/**
 * Send API response with appropriate HTTP status code
 * 
 * @param array $result Result from ViewModel method
 */
function sendClinicalProviderResponse(array $result): void
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
