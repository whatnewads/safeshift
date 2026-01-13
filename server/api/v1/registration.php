<?php

declare(strict_types=1);

/**
 * Registration API Endpoint
 *
 * Handles registration dashboard and patient check-in related API requests.
 * Routes:
 *   GET  /api/v1/registration/dashboard     - Get full dashboard data
 *   GET  /api/v1/registration/queue         - Get queue statistics only
 *   GET  /api/v1/registration/pending       - Get pending registrations list
 *   GET  /api/v1/registration/search?q=     - Search patients
 *   GET  /api/v1/registration/patient/{id}  - Get single patient by ID
 *
 * @package SafeShift\API\v1
 */

use ViewModel\RegistrationViewModel;
use ViewModel\Core\ApiResponse;

// Require ViewModel files
require_once __DIR__ . '/../../ViewModel/Core/BaseViewModel.php';
require_once __DIR__ . '/../../ViewModel/Core/ApiResponse.php';
require_once __DIR__ . '/../../ViewModel/RegistrationViewModel.php';
require_once __DIR__ . '/../../model/Repositories/RegistrationRepository.php';

/**
 * Handle registration route
 *
 * @param string $subPath The path after /registration/
 * @param string $method HTTP method
 */
function handleRegistrationRoute(string $subPath, string $method): void
{
    // Get PDO instance using the db helper function
    $pdo = \App\db\pdo();

    // Require authentication - check for user object with user_id
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['user_id'])) {
        ApiResponse::send(ApiResponse::unauthorized('Authentication required'), 401);
        return;
    }

    // Initialize ViewModel
    $viewModel = new RegistrationViewModel($pdo);

    // Parse subpath for routing
    $segments = array_filter(explode('/', $subPath));
    $segments = array_values($segments);
    
    $action = $segments[0] ?? '';
    $subAction = $segments[1] ?? '';

    // Route based on method and path
    switch ($method) {
        case 'GET':
            handleGetRegistration($viewModel, $action, $subAction);
            break;
            
        default:
            ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
    }
}

/**
 * Handle GET requests for registration
 * 
 * @param RegistrationViewModel $viewModel
 * @param string $action First path segment
 * @param string $subAction Second path segment
 */
function handleGetRegistration(RegistrationViewModel $viewModel, string $action, string $subAction): void
{
    // GET /registration or /registration/dashboard - Full dashboard data
    if (empty($action) || $action === 'dashboard') {
        $result = $viewModel->getDashboardData();
        sendRegistrationResponse($result);
        return;
    }
    
    // GET /registration/queue - Queue statistics only
    if ($action === 'queue') {
        $result = $viewModel->getQueueStats();
        sendRegistrationResponse($result);
        return;
    }
    
    // GET /registration/pending - Pending registrations list
    if ($action === 'pending') {
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $result = $viewModel->getPendingRegistrations($limit);
        sendRegistrationResponse($result);
        return;
    }
    
    // GET /registration/search?q= - Patient search
    if ($action === 'search') {
        $query = $_GET['q'] ?? $_GET['query'] ?? '';
        
        if (empty(trim($query))) {
            ApiResponse::send(ApiResponse::validationError([
                'q' => ['Search query is required']
            ]), 422);
            return;
        }
        
        $result = $viewModel->searchPatients($query);
        sendRegistrationResponse($result);
        return;
    }
    
    // GET /registration/patient/{id} - Get single patient
    if ($action === 'patient' && !empty($subAction)) {
        $result = $viewModel->getPatient($subAction);
        sendRegistrationResponse($result);
        return;
    }
    
    // Invalid endpoint
    ApiResponse::send(ApiResponse::notFound('Invalid registration endpoint'), 404);
}

/**
 * Send API response with appropriate HTTP status code
 * 
 * @param array $result Result from ViewModel method
 */
function sendRegistrationResponse(array $result): void
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
