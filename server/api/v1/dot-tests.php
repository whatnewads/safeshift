<?php
/**
 * DOT Drug Testing API Endpoints
 * 
 * Handles all /api/v1/dot-tests/* routes for the SafeShift EHR React frontend.
 * 49 CFR Part 40 Compliant - Tracks notification windows, CCF forms, MRO workflow.
 * 
 * Endpoints:
 * - GET    /api/v1/dot-tests              - List tests with filters
 * - GET    /api/v1/dot-tests/{id}         - Get test details
 * - POST   /api/v1/dot-tests              - Initiate new test
 * - PUT    /api/v1/dot-tests/{id}/ccf     - Update CCF form data
 * - POST   /api/v1/dot-tests/{id}/results - Submit lab results
 * - POST   /api/v1/dot-tests/{id}/mro-verify - MRO verification
 * - GET    /api/v1/dot-tests/status/{status} - Filter by status
 * - GET    /api/v1/dot-tests/deadline     - Get tests approaching deadline
 * 
 * Compliance requirements:
 * - Track 2-day (48 hour) notification window
 * - CCF form data validation
 * - MRO workflow support
 * - Chain of custody documentation
 * 
 * @package SafeShift\API\v1
 */

// This file is included by the router, bootstrap should already be loaded
if (!defined('CSRF_TOKEN_NAME')) {
    require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
}

use ViewModel\DOT\DotTestingViewModel;
use ViewModel\Core\ApiResponse;

// ========================================================================
// INPUT HELPERS
// ========================================================================

/**
 * Get JSON input from request body
 */
function getDotTestJsonInput(): array
{
    static $input = null;
    
    if ($input === null) {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true) ?? [];
    }
    
    return $input;
}

/**
 * Validate CSRF token for POST/PUT/DELETE requests
 */
function validateDotTestCsrf(): bool
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] 
        ?? $_SERVER['HTTP_X_XSRF_TOKEN']
        ?? getDotTestJsonInput()['csrf_token'] 
        ?? '';
    
    if (empty($token)) {
        return false;
    }
    
    return \App\Core\Session::validateCsrfToken($token);
}

/**
 * Check if user is authenticated
 * User data is stored in $_SESSION['user'] object (consistent with BaseViewModel)
 */
function requireDotTestAuth(): bool
{
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['user_id'])) {
        ApiResponse::send(ApiResponse::unauthorized('Authentication required'), 401);
        return false;
    }
    return true;
}

// ========================================================================
// ROUTE HANDLING
// ========================================================================

/**
 * Handle DOT tests API routes
 * 
 * @param string $path The path after /dot-tests/ (e.g., '{id}', '{id}/ccf')
 * @param string $method HTTP method
 */
function handleDotTestsRoute(string $path, string $method): void
{
    // Require authentication for all DOT test endpoints
    if (!requireDotTestAuth()) {
        return;
    }
    
    $viewModel = new DotTestingViewModel();
    
    // Parse path segments
    $segments = array_filter(explode('/', $path));
    $segments = array_values($segments); // Re-index
    
    $id = $segments[0] ?? null;
    $subRoute = $segments[1] ?? null;
    
    try {
        // Special routes (before ID-based routing)
        if ($id === 'status' && isset($segments[1])) {
            handleDotTestsByStatus($viewModel, $segments[1], $method);
            return;
        }
        
        if ($id === 'deadline') {
            handleDotTestsApproachingDeadline($viewModel, $method);
            return;
        }
        
        // Route based on method and path
        switch ($method) {
            case 'GET':
                if ($id) {
                    handleGetDotTest($viewModel, $id, $method);
                } else {
                    handleListDotTests($viewModel, $method);
                }
                break;
                
            case 'POST':
                if (!validateDotTestCsrf()) {
                    ApiResponse::send(ApiResponse::error('Invalid security token'), 403);
                    return;
                }
                
                // Handle sub-routes for POST
                if ($id && $subRoute === 'results') {
                    handleSubmitLabResults($viewModel, $id, $method);
                } elseif ($id && $subRoute === 'mro-verify') {
                    handleMroVerify($viewModel, $id, $method);
                } elseif (!$id) {
                    handleInitiateDotTest($viewModel, $method);
                } else {
                    ApiResponse::send(ApiResponse::notFound('Endpoint not found'), 404);
                }
                break;
                
            case 'PUT':
                if (!validateDotTestCsrf()) {
                    ApiResponse::send(ApiResponse::error('Invalid security token'), 403);
                    return;
                }
                
                if ($id && $subRoute === 'ccf') {
                    handleUpdateCcf($viewModel, $id, $method);
                } else {
                    ApiResponse::send(ApiResponse::notFound('Endpoint not found'), 404);
                }
                break;
                
            default:
                ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        }
        
    } catch (Exception $e) {
        error_log('DOT Tests API error: ' . $e->getMessage());
        $isDev = defined('ENVIRONMENT') && ENVIRONMENT === 'development';
        ApiResponse::send(
            ApiResponse::serverError($isDev ? $e->getMessage() : 'An error occurred'),
            500
        );
    }
}

/**
 * GET /api/v1/dot-tests
 * List DOT tests with optional filters and pagination
 */
function handleListDotTests(DotTestingViewModel $vm, string $method): void
{
    if ($method !== 'GET') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    // Build filters from query params
    $filters = [
        'page' => (int)($_GET['page'] ?? 1),
        'per_page' => min(100, (int)($_GET['per_page'] ?? 20)),
        'status' => $_GET['status'] ?? null,
        'test_type' => $_GET['test_type'] ?? null,
        'employer_id' => $_GET['employer_id'] ?? null,
        'patient_id' => $_GET['patient_id'] ?? null,
        'date_from' => $_GET['date_from'] ?? null,
        'date_to' => $_GET['date_to'] ?? null,
    ];
    
    // Remove null values
    $filters = array_filter($filters, fn($v) => $v !== null);
    
    $result = $vm->index($filters);
    
    $statusCode = $result['success'] ? 200 : 400;
    ApiResponse::send($result, $statusCode);
}

/**
 * GET /api/v1/dot-tests/{id}
 * Get single DOT test by ID
 */
function handleGetDotTest(DotTestingViewModel $vm, string $id, string $method): void
{
    if ($method !== 'GET') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    $result = $vm->show($id);
    
    $statusCode = $result['success'] ? 200 : ($result['message'] === 'DOT test not found' ? 404 : 400);
    ApiResponse::send($result, $statusCode);
}

/**
 * POST /api/v1/dot-tests
 * Initiate new DOT test
 */
function handleInitiateDotTest(DotTestingViewModel $vm, string $method): void
{
    if ($method !== 'POST') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    $data = getDotTestJsonInput();
    
    if (empty($data)) {
        ApiResponse::send(ApiResponse::badRequest('Request body is required'), 400);
        return;
    }
    
    $result = $vm->initiate($data);
    
    $statusCode = $result['success'] ? 201 : (isset($result['errors']) ? 422 : 400);
    ApiResponse::send($result, $statusCode);
}

/**
 * PUT /api/v1/dot-tests/{id}/ccf
 * Update CCF (Custody and Control Form) data
 */
function handleUpdateCcf(DotTestingViewModel $vm, string $testId, string $method): void
{
    if ($method !== 'PUT') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    $data = getDotTestJsonInput();
    
    if (empty($data)) {
        ApiResponse::send(ApiResponse::badRequest('CCF data is required'), 400);
        return;
    }
    
    $result = $vm->updateCcf($testId, $data);
    
    $statusCode = $result['success'] ? 200 : 400;
    if ($result['message'] === 'DOT test not found') {
        $statusCode = 404;
    } elseif (strpos($result['message'] ?? '', 'cannot be updated') !== false) {
        $statusCode = 403;
    } elseif (isset($result['errors'])) {
        $statusCode = 422;
    }
    ApiResponse::send($result, $statusCode);
}

/**
 * POST /api/v1/dot-tests/{id}/results
 * Submit lab results for DOT test
 */
function handleSubmitLabResults(DotTestingViewModel $vm, string $testId, string $method): void
{
    if ($method !== 'POST') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    $data = getDotTestJsonInput();
    
    if (empty($data)) {
        ApiResponse::send(ApiResponse::badRequest('Lab results data is required'), 400);
        return;
    }
    
    $result = $vm->submitResults($testId, $data);
    
    $statusCode = $result['success'] ? 200 : 400;
    if ($result['message'] === 'DOT test not found') {
        $statusCode = 404;
    } elseif (isset($result['errors'])) {
        $statusCode = 422;
    }
    ApiResponse::send($result, $statusCode);
}

/**
 * POST /api/v1/dot-tests/{id}/mro-verify
 * MRO verification of test results
 */
function handleMroVerify(DotTestingViewModel $vm, string $testId, string $method): void
{
    if ($method !== 'POST') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    $data = getDotTestJsonInput();
    
    if (empty($data)) {
        ApiResponse::send(ApiResponse::badRequest('Verification data is required'), 400);
        return;
    }
    
    $result = $vm->mroVerify($testId, $data);
    
    $statusCode = $result['success'] ? 200 : 400;
    if ($result['message'] === 'DOT test not found') {
        $statusCode = 404;
    } elseif (strpos($result['message'] ?? '', 'not pending') !== false) {
        $statusCode = 400;
    } elseif (isset($result['errors'])) {
        $statusCode = 422;
    }
    ApiResponse::send($result, $statusCode);
}

/**
 * GET /api/v1/dot-tests/status/{status}
 * Get DOT tests filtered by status
 */
function handleDotTestsByStatus(DotTestingViewModel $vm, string $status, string $method): void
{
    if ($method !== 'GET') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    $result = $vm->getByStatus($status);
    
    $statusCode = $result['success'] ? 200 : (isset($result['errors']) ? 422 : 400);
    ApiResponse::send($result, $statusCode);
}

/**
 * GET /api/v1/dot-tests/deadline
 * Get DOT tests approaching notification deadline (within 24 hours)
 */
function handleDotTestsApproachingDeadline(DotTestingViewModel $vm, string $method): void
{
    if ($method !== 'GET') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    $result = $vm->getApproachingDeadline();
    
    $statusCode = $result['success'] ? 200 : 400;
    ApiResponse::send($result, $statusCode);
}

// ========================================================================
// MAIN EXECUTION (for direct access)
// ========================================================================

if (basename($_SERVER['SCRIPT_FILENAME']) === 'dot-tests.php') {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $basePath = '/api/v1/dot-tests';
    
    $path = parse_url($requestUri, PHP_URL_PATH);
    
    if (strpos($path, $basePath) === 0) {
        $dotTestsPath = substr($path, strlen($basePath));
        $dotTestsPath = trim($dotTestsPath, '/');
    } else {
        $dotTestsPath = '';
    }
    
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    header('Content-Type: application/json; charset=utf-8');
    
    // Handle CORS
    $allowedOrigins = [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:3000',
        'http://127.0.0.1:3000'
    ];
    
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, $allowedOrigins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-XSRF-Token');
        header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
    }
    
    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
    
    handleDotTestsRoute($dotTestsPath, $method);
}
