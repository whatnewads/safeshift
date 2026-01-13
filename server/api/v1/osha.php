<?php
/**
 * OSHA Injury Reporting API Endpoints
 * 
 * Handles all /api/v1/osha/* routes for the SafeShift EHR React frontend.
 * 29 CFR 1904 Compliant - OSHA recordkeeping and reporting requirements.
 * 
 * Endpoints:
 * - GET    /api/v1/osha/injuries       - List recorded injuries
 * - GET    /api/v1/osha/injuries/{id}  - Get single injury record
 * - POST   /api/v1/osha/injuries       - Record new injury
 * - PUT    /api/v1/osha/injuries/{id}  - Update injury record
 * - DELETE /api/v1/osha/injuries/{id}  - Remove injury record (admin only)
 * - GET    /api/v1/osha/300-log        - Get OSHA 300 log data
 * - GET    /api/v1/osha/300a-log       - Get OSHA 300A summary
 * - GET    /api/v1/osha/rates          - Calculate TRIR/DART rates
 * - POST   /api/v1/osha/submit-ita     - Submit to ITA system
 * 
 * Query params for logs:
 * - ?year=2025&establishment_id=xxx
 * 
 * @package SafeShift\API\v1
 */

// This file is included by the router, bootstrap should already be loaded
if (!defined('CSRF_TOKEN_NAME')) {
    require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
}

use ViewModel\OSHA\OshaViewModel;
use ViewModel\Core\ApiResponse;

// ========================================================================
// INPUT HELPERS
// ========================================================================

/**
 * Get JSON input from request body
 */
function getOshaJsonInput(): array
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
function validateOshaCsrf(): bool
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] 
        ?? $_SERVER['HTTP_X_XSRF_TOKEN']
        ?? getOshaJsonInput()['csrf_token'] 
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
function requireOshaAuth(): bool
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
 * Handle OSHA API routes
 * 
 * @param string $path The path after /osha/ (e.g., 'injuries', '300-log')
 * @param string $method HTTP method
 */
function handleOshaRoute(string $path, string $method): void
{
    // Require authentication for all OSHA endpoints
    if (!requireOshaAuth()) {
        return;
    }
    
    $viewModel = new OshaViewModel();
    
    // Parse path segments
    $segments = array_filter(explode('/', $path));
    $segments = array_values($segments); // Re-index
    
    $resource = $segments[0] ?? null;
    $id = $segments[1] ?? null;
    
    try {
        // Route based on resource
        switch ($resource) {
            case 'injuries':
                handleInjuriesRoute($viewModel, $id, $method);
                break;
                
            case '300-log':
                handleGet300Log($viewModel, $method);
                break;
                
            case '300a-log':
                handleGet300ALog($viewModel, $method);
                break;
                
            case 'rates':
                handleGetRates($viewModel, $method);
                break;
                
            case 'submit-ita':
                handleSubmitIta($viewModel, $method);
                break;
                
            case '':
            case null:
                // Default to injuries list
                handleListInjuries($viewModel, $method);
                break;
                
            default:
                ApiResponse::send(ApiResponse::notFound('OSHA endpoint not found'), 404);
        }
        
    } catch (Exception $e) {
        error_log('OSHA API error: ' . $e->getMessage());
        $isDev = defined('ENVIRONMENT') && ENVIRONMENT === 'development';
        ApiResponse::send(
            ApiResponse::serverError($isDev ? $e->getMessage() : 'An error occurred'),
            500
        );
    }
}

/**
 * Route injuries sub-endpoints
 */
function handleInjuriesRoute(OshaViewModel $vm, ?string $id, string $method): void
{
    switch ($method) {
        case 'GET':
            if ($id) {
                handleGetInjury($vm, $id, $method);
            } else {
                handleListInjuries($vm, $method);
            }
            break;
            
        case 'POST':
            if (!validateOshaCsrf()) {
                ApiResponse::send(ApiResponse::error('Invalid security token'), 403);
                return;
            }
            handleRecordInjury($vm, $method);
            break;
            
        case 'PUT':
            if (!validateOshaCsrf()) {
                ApiResponse::send(ApiResponse::error('Invalid security token'), 403);
                return;
            }
            if (!$id) {
                ApiResponse::send(ApiResponse::badRequest('Injury ID required'), 400);
                return;
            }
            handleUpdateInjury($vm, $id, $method);
            break;
            
        case 'DELETE':
            if (!validateOshaCsrf()) {
                ApiResponse::send(ApiResponse::error('Invalid security token'), 403);
                return;
            }
            if (!$id) {
                ApiResponse::send(ApiResponse::badRequest('Injury ID required'), 400);
                return;
            }
            handleDeleteInjury($vm, $id, $method);
            break;
            
        default:
            ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
    }
}

/**
 * GET /api/v1/osha/injuries
 * List injuries with optional filters and pagination
 */
function handleListInjuries(OshaViewModel $vm, string $method): void
{
    if ($method !== 'GET') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    // Build filters from query params
    $filters = [
        'page' => (int)($_GET['page'] ?? 1),
        'per_page' => min(100, (int)($_GET['per_page'] ?? 20)),
        'year' => (int)($_GET['year'] ?? date('Y')),
        'establishment_id' => $_GET['establishment_id'] ?? null,
        'employer_id' => $_GET['employer_id'] ?? null,
        'category' => $_GET['category'] ?? null,
        'classification' => $_GET['classification'] ?? null,
        'recordable' => isset($_GET['recordable']) ? ($_GET['recordable'] === 'true' || $_GET['recordable'] === '1') : null,
    ];
    
    // Remove null values
    $filters = array_filter($filters, fn($v) => $v !== null);
    
    $result = $vm->getInjuries($filters);
    
    $statusCode = $result['success'] ? 200 : 400;
    ApiResponse::send($result, $statusCode);
}

/**
 * GET /api/v1/osha/injuries/{id}
 * Get single injury record
 */
function handleGetInjury(OshaViewModel $vm, string $id, string $method): void
{
    if ($method !== 'GET') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    // The ViewModel doesn't have a show() method for single injury,
    // so we use getInjuries with a filter that would match the ID
    // For now, return not implemented or implement via direct query
    ApiResponse::send(ApiResponse::error('Single injury lookup not yet implemented'), 501);
}

/**
 * POST /api/v1/osha/injuries
 * Record new injury
 */
function handleRecordInjury(OshaViewModel $vm, string $method): void
{
    if ($method !== 'POST') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    $data = getOshaJsonInput();
    
    if (empty($data)) {
        ApiResponse::send(ApiResponse::badRequest('Request body is required'), 400);
        return;
    }
    
    $result = $vm->recordInjury($data);
    
    $statusCode = $result['success'] ? 201 : (isset($result['errors']) ? 422 : 400);
    ApiResponse::send($result, $statusCode);
}

/**
 * PUT /api/v1/osha/injuries/{id}
 * Update injury record
 */
function handleUpdateInjury(OshaViewModel $vm, string $id, string $method): void
{
    if ($method !== 'PUT') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    $data = getOshaJsonInput();
    
    if (empty($data)) {
        ApiResponse::send(ApiResponse::badRequest('Request body is required'), 400);
        return;
    }
    
    $result = $vm->updateInjury($id, $data);
    
    $statusCode = $result['success'] ? 200 : 400;
    if ($result['message'] === 'Injury record not found') {
        $statusCode = 404;
    } elseif (isset($result['errors'])) {
        $statusCode = 422;
    }
    ApiResponse::send($result, $statusCode);
}

/**
 * DELETE /api/v1/osha/injuries/{id}
 * Delete injury record (admin only)
 */
function handleDeleteInjury(OshaViewModel $vm, string $id, string $method): void
{
    if ($method !== 'DELETE') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    $result = $vm->deleteInjury($id);
    
    $statusCode = $result['success'] ? 200 : 400;
    if ($result['message'] === 'Injury record not found') {
        $statusCode = 404;
    } elseif (strpos($result['message'] ?? '', 'administrators') !== false) {
        $statusCode = 403;
    }
    ApiResponse::send($result, $statusCode);
}

/**
 * GET /api/v1/osha/300-log
 * Get OSHA 300 Log data
 */
function handleGet300Log(OshaViewModel $vm, string $method): void
{
    if ($method !== 'GET') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    $year = (int)($_GET['year'] ?? date('Y'));
    $establishmentId = $_GET['establishment_id'] ?? null;
    
    $result = $vm->get300Log($year, $establishmentId);
    
    $statusCode = $result['success'] ? 200 : 400;
    ApiResponse::send($result, $statusCode);
}

/**
 * GET /api/v1/osha/300a-log
 * Get OSHA 300A Summary (calculates rates)
 */
function handleGet300ALog(OshaViewModel $vm, string $method): void
{
    if ($method !== 'GET') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    $year = (int)($_GET['year'] ?? date('Y'));
    $establishmentId = $_GET['establishment_id'] ?? null;
    
    // 300A uses the same rate calculation
    $result = $vm->calculateRates($year, $establishmentId);
    
    $statusCode = $result['success'] ? 200 : 400;
    ApiResponse::send($result, $statusCode);
}

/**
 * GET /api/v1/osha/rates
 * Calculate TRIR/DART rates
 */
function handleGetRates(OshaViewModel $vm, string $method): void
{
    if ($method !== 'GET') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    $year = (int)($_GET['year'] ?? date('Y'));
    $establishmentId = $_GET['establishment_id'] ?? null;
    
    $result = $vm->calculateRates($year, $establishmentId);
    
    $statusCode = $result['success'] ? 200 : 400;
    ApiResponse::send($result, $statusCode);
}

/**
 * POST /api/v1/osha/submit-ita
 * Submit data to OSHA ITA (Injury Tracking Application)
 */
function handleSubmitIta(OshaViewModel $vm, string $method): void
{
    if ($method !== 'POST') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    if (!validateOshaCsrf()) {
        ApiResponse::send(ApiResponse::error('Invalid security token'), 403);
        return;
    }
    
    $data = getOshaJsonInput();
    
    if (empty($data['year'])) {
        ApiResponse::send(ApiResponse::validationError([
            'year' => ['Year is required for ITA submission']
        ]), 422);
        return;
    }
    
    $result = $vm->submitToIta($data);
    
    $statusCode = $result['success'] ? 200 : 400;
    if (strpos($result['message'] ?? '', 'administrators') !== false) {
        $statusCode = 403;
    }
    ApiResponse::send($result, $statusCode);
}

// ========================================================================
// MAIN EXECUTION (for direct access)
// ========================================================================

if (basename($_SERVER['SCRIPT_FILENAME']) === 'osha.php') {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $basePath = '/api/v1/osha';
    
    $path = parse_url($requestUri, PHP_URL_PATH);
    
    if (strpos($path, $basePath) === 0) {
        $oshaPath = substr($path, strlen($basePath));
        $oshaPath = trim($oshaPath, '/');
    } else {
        $oshaPath = '';
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
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    }
    
    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
    
    handleOshaRoute($oshaPath, $method);
}
