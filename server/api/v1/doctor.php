<?php

declare(strict_types=1);

/**
 * Doctor (MRO) API Endpoint
 *
 * Handles Doctor/MRO dashboard and DOT test verification API requests.
 * The Doctor role is associated with 'pclinician' role type (provider clinician)
 * and serves as the MRO interface for DOT drug testing, result verification,
 * and order signing.
 *
 * Routes:
 *   GET  /api/v1/doctor/dashboard          - Get full dashboard data
 *   GET  /api/v1/doctor/stats              - Get doctor statistics only
 *   GET  /api/v1/doctor/verifications/pending  - Get pending DOT verifications
 *   GET  /api/v1/doctor/verifications/history  - Get verification history
 *   GET  /api/v1/doctor/verifications/:id      - Get test details for review
 *   POST /api/v1/doctor/verify/:testId         - Submit MRO verification
 *   GET  /api/v1/doctor/orders/pending         - Get pending orders
 *   POST /api/v1/doctor/sign/:orderId          - Sign an order
 *
 * @package SafeShift\API\v1
 */

use ViewModel\DoctorViewModel;
use ViewModel\Core\ApiResponse;

// Require ViewModel files
require_once __DIR__ . '/../../ViewModel/Core/ApiResponse.php';
require_once __DIR__ . '/../../ViewModel/DoctorViewModel.php';
require_once __DIR__ . '/../../model/Repositories/DoctorRepository.php';

/**
 * Handle doctor route
 *
 * @param string $subPath The path after /doctor/
 * @param string $method HTTP method
 */
function handleDoctorRoute(string $subPath, string $method): void
{
    // Get PDO instance using the db helper function
    $pdo = \App\db\pdo();

    // Require authentication - check for user object with user_id
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['user_id'])) {
        ApiResponse::send(ApiResponse::unauthorized('Authentication required'), 401);
        return;
    }

    // Initialize ViewModel and set user context
    $viewModel = new DoctorViewModel($pdo);
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
            handleGetDoctor($viewModel, $action, $subAction, $segments);
            break;
            
        case 'POST':
            handlePostDoctor($viewModel, $action, $subAction, $segments);
            break;
            
        default:
            ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
    }
}

/**
 * Handle GET requests for doctor
 * 
 * @param DoctorViewModel $viewModel
 * @param string $action First path segment
 * @param string $subAction Second path segment
 * @param array $segments All path segments
 */
function handleGetDoctor(DoctorViewModel $viewModel, string $action, string $subAction, array $segments): void
{
    // GET /doctor or /doctor/dashboard - Full dashboard data
    if (empty($action) || $action === 'dashboard') {
        $result = $viewModel->getDashboardData();
        sendDoctorResponse($result);
        return;
    }
    
    // GET /doctor/stats - Doctor statistics only
    if ($action === 'stats') {
        $result = $viewModel->getDoctorStats();
        sendDoctorResponse($result);
        return;
    }
    
    // GET /doctor/verifications/pending - Pending DOT verifications
    if ($action === 'verifications' && $subAction === 'pending') {
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $result = $viewModel->getPendingVerifications($limit);
        sendDoctorResponse($result);
        return;
    }
    
    // GET /doctor/verifications/history - Verification history
    if ($action === 'verifications' && $subAction === 'history') {
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $result = $viewModel->getVerificationHistory($limit);
        sendDoctorResponse($result);
        return;
    }
    
    // GET /doctor/verifications/:testId - Get test details for review
    if ($action === 'verifications' && !empty($subAction) && $subAction !== 'pending' && $subAction !== 'history') {
        $testId = $subAction;
        $result = $viewModel->getTestDetails($testId);
        sendDoctorResponse($result);
        return;
    }
    
    // GET /doctor/orders/pending - Pending orders
    if ($action === 'orders' && $subAction === 'pending') {
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $result = $viewModel->getPendingOrders($limit);
        sendDoctorResponse($result);
        return;
    }
    
    // Invalid endpoint
    ApiResponse::send(ApiResponse::notFound('Invalid doctor endpoint'), 404);
}

/**
 * Handle POST requests for doctor
 * 
 * @param DoctorViewModel $viewModel
 * @param string $action First path segment
 * @param string $subAction Second path segment
 * @param array $segments All path segments
 */
function handlePostDoctor(DoctorViewModel $viewModel, string $action, string $subAction, array $segments): void
{
    // Parse JSON body
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    
    // POST /doctor/verify/:testId - Submit MRO verification
    if ($action === 'verify' && !empty($subAction)) {
        $testId = $subAction;
        $result = $body['result'] ?? '';
        $comments = $body['comments'] ?? null;
        
        if (empty($result)) {
            ApiResponse::send(ApiResponse::badRequest('Verification result is required'), 400);
            return;
        }
        
        $response = $viewModel->verifyTest($testId, $result, $comments);
        sendDoctorResponse($response);
        return;
    }
    
    // POST /doctor/sign/:orderId - Sign an order
    if ($action === 'sign' && !empty($subAction)) {
        $orderId = $subAction;
        $response = $viewModel->signOrder($orderId);
        sendDoctorResponse($response);
        return;
    }
    
    // Invalid endpoint
    ApiResponse::send(ApiResponse::notFound('Invalid doctor endpoint'), 404);
}

/**
 * Send API response with appropriate HTTP status code
 * 
 * @param array $result Result from ViewModel method
 */
function sendDoctorResponse(array $result): void
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
