<?php
/**
 * Reports and Analytics API Endpoints
 * 
 * Handles all /api/v1/reports/* routes for the SafeShift EHR React frontend.
 * Provides dashboard data, safety statistics, compliance status, and report generation.
 * 
 * Endpoints:
 * - GET /api/v1/reports/dashboard     - Dashboard summary data
 * - GET /api/v1/reports/safety        - Safety statistics
 * - GET /api/v1/reports/compliance    - Compliance status
 * - GET /api/v1/reports/{type}        - Generate specific report
 * - GET /api/v1/reports/export/{type} - Export report (csv/json/pdf)
 * 
 * Report types:
 * - patient-volume
 * - encounter-summary
 * - dot-summary
 * - osha-summary
 * - provider-productivity
 * - compliance-summary
 * - audit-trail
 * 
 * @package SafeShift\API\v1
 */

// This file is included by the router, bootstrap should already be loaded
if (!defined('CSRF_TOKEN_NAME')) {
    require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
}

use ViewModel\Reports\ReportsViewModel;
use ViewModel\Core\ApiResponse;

// ========================================================================
// INPUT HELPERS
// ========================================================================

/**
 * Get JSON input from request body
 */
function getReportsJsonInput(): array
{
    static $input = null;
    
    if ($input === null) {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true) ?? [];
    }
    
    return $input;
}

/**
 * Check if user is authenticated
 * User data is stored in $_SESSION['user'] object (consistent with BaseViewModel)
 */
function requireReportsAuth(): bool
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
 * Handle reports API routes
 * 
 * @param string $path The path after /reports/ (e.g., 'dashboard', 'safety')
 * @param string $method HTTP method
 */
function handleReportsRoute(string $path, string $method): void
{
    // Require authentication for all reports endpoints
    if (!requireReportsAuth()) {
        return;
    }
    
    // Reports are read-only, only GET methods allowed
    if ($method !== 'GET') {
        ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
        return;
    }
    
    $viewModel = new ReportsViewModel();
    
    // Parse path segments
    $segments = array_filter(explode('/', $path));
    $segments = array_values($segments); // Re-index
    
    $resource = $segments[0] ?? null;
    $subResource = $segments[1] ?? null;
    
    try {
        // Route based on resource
        switch ($resource) {
            case 'dashboard':
                handleDashboardReport($viewModel);
                break;
                
            case 'safety':
                handleSafetyReport($viewModel);
                break;
                
            case 'compliance':
                handleComplianceReport($viewModel);
                break;
                
            case 'export':
                if ($subResource) {
                    handleExportReport($viewModel, $subResource);
                } else {
                    ApiResponse::send(ApiResponse::badRequest('Report type required for export'), 400);
                }
                break;
                
            case 'patient-volume':
            case 'encounter-summary':
            case 'dot-summary':
            case 'osha-summary':
            case 'osha-300-log':
            case 'osha-300a-summary':
            case 'provider-productivity':
            case 'compliance-summary':
            case 'audit-trail':
                handleGenerateReport($viewModel, $resource);
                break;
                
            case '':
            case null:
                // Return list of available reports
                handleListReportTypes();
                break;
                
            default:
                ApiResponse::send(ApiResponse::notFound('Report type not found'), 404);
        }
        
    } catch (Exception $e) {
        error_log('Reports API error: ' . $e->getMessage());
        $isDev = defined('ENVIRONMENT') && ENVIRONMENT === 'development';
        ApiResponse::send(
            ApiResponse::serverError($isDev ? $e->getMessage() : 'An error occurred'),
            500
        );
    }
}

/**
 * GET /api/v1/reports
 * List available report types
 */
function handleListReportTypes(): void
{
    $reportTypes = [
        [
            'type' => 'patient-volume',
            'name' => 'Patient Volume Report',
            'description' => 'Daily patient counts and encounter volumes',
            'params' => ['start_date', 'end_date', 'employer_id']
        ],
        [
            'type' => 'encounter-summary',
            'name' => 'Encounter Summary Report',
            'description' => 'Summary of encounters by type and status',
            'params' => ['start_date', 'end_date', 'provider_id']
        ],
        [
            'type' => 'dot-summary',
            'name' => 'DOT Testing Summary',
            'description' => 'Drug testing statistics and outcomes',
            'params' => ['year', 'employer_id']
        ],
        [
            'type' => 'osha-summary',
            'name' => 'OSHA Summary Report',
            'description' => 'OSHA recordable injury summary',
            'params' => ['year', 'establishment_id']
        ],
        [
            'type' => 'osha-300-log',
            'name' => 'OSHA 300 Log',
            'description' => 'Log of Work-Related Injuries and Illnesses',
            'params' => ['year', 'establishment_id']
        ],
        [
            'type' => 'osha-300a-summary',
            'name' => 'OSHA 300A Summary',
            'description' => 'Summary of Work-Related Injuries and Illnesses',
            'params' => ['year', 'establishment_id']
        ],
        [
            'type' => 'provider-productivity',
            'name' => 'Provider Productivity Report',
            'description' => 'Provider encounter counts and metrics',
            'params' => ['start_date', 'end_date']
        ],
        [
            'type' => 'compliance-summary',
            'name' => 'Compliance Summary Report',
            'description' => 'Overall compliance status and metrics',
            'params' => []
        ],
        [
            'type' => 'audit-trail',
            'name' => 'Audit Trail Report',
            'description' => 'System audit events log',
            'params' => ['start_date', 'end_date', 'action_type', 'user_id']
        ],
    ];
    
    ApiResponse::send(ApiResponse::success([
        'report_types' => $reportTypes,
        'export_formats' => ['csv', 'json', 'pdf', 'xlsx']
    ], 'Available report types'), 200);
}

/**
 * GET /api/v1/reports/dashboard
 * Dashboard summary data
 */
function handleDashboardReport(ReportsViewModel $vm): void
{
    $result = $vm->getDashboardData();
    
    $statusCode = $result['success'] ? 200 : 400;
    ApiResponse::send($result, $statusCode);
}

/**
 * GET /api/v1/reports/safety
 * Safety statistics report
 */
function handleSafetyReport(ReportsViewModel $vm): void
{
    $filters = [
        'year' => (int)($_GET['year'] ?? date('Y')),
        'employer_id' => $_GET['employer_id'] ?? null,
        'establishment_id' => $_GET['establishment_id'] ?? null,
    ];
    
    // Remove null values
    $filters = array_filter($filters, fn($v) => $v !== null);
    
    $result = $vm->getSafetyStats($filters);
    
    $statusCode = $result['success'] ? 200 : 400;
    ApiResponse::send($result, $statusCode);
}

/**
 * GET /api/v1/reports/compliance
 * Compliance status report
 */
function handleComplianceReport(ReportsViewModel $vm): void
{
    $result = $vm->getComplianceStatus();
    
    $statusCode = $result['success'] ? 200 : 400;
    ApiResponse::send($result, $statusCode);
}

/**
 * GET /api/v1/reports/{type}
 * Generate specific report
 */
function handleGenerateReport(ReportsViewModel $vm, string $type): void
{
    // Map URL-friendly type to internal type
    $typeMap = [
        'patient-volume' => 'patient_volume',
        'encounter-summary' => 'encounter_summary',
        'dot-summary' => 'dot_testing',
        'osha-summary' => 'osha_300a_summary',
        'osha-300-log' => 'osha_300_log',
        'osha-300a-summary' => 'osha_300a_summary',
        'provider-productivity' => 'provider_productivity',
        'compliance-summary' => 'compliance_summary',
        'audit-trail' => 'audit_trail',
    ];
    
    $internalType = $typeMap[$type] ?? $type;
    
    // Build params from query string
    $params = [
        'start_date' => $_GET['start_date'] ?? null,
        'end_date' => $_GET['end_date'] ?? null,
        'year' => isset($_GET['year']) ? (int)$_GET['year'] : null,
        'employer_id' => $_GET['employer_id'] ?? null,
        'establishment_id' => $_GET['establishment_id'] ?? null,
        'provider_id' => $_GET['provider_id'] ?? null,
        'action_type' => $_GET['action_type'] ?? null,
        'user_id' => $_GET['user_id'] ?? null,
    ];
    
    // Remove null values
    $params = array_filter($params, fn($v) => $v !== null);
    
    $result = $vm->generateReport($internalType, $params);
    
    $statusCode = $result['success'] ? 200 : 400;
    if (isset($result['errors'])) {
        $statusCode = 422;
    }
    ApiResponse::send($result, $statusCode);
}

/**
 * GET /api/v1/reports/export/{type}
 * Export report to file format
 */
function handleExportReport(ReportsViewModel $vm, string $type): void
{
    // Map URL-friendly type to internal type
    $typeMap = [
        'patient-volume' => 'patient_volume',
        'encounter-summary' => 'encounter_summary',
        'dot-summary' => 'dot_testing',
        'osha-summary' => 'osha_300a_summary',
        'osha-300-log' => 'osha_300_log',
        'osha-300a-summary' => 'osha_300a_summary',
        'provider-productivity' => 'provider_productivity',
        'compliance-summary' => 'compliance_summary',
        'audit-trail' => 'audit_trail',
    ];
    
    $internalType = $typeMap[$type] ?? $type;
    
    // Get export format
    $format = $_GET['format'] ?? 'csv';
    
    // Validate format
    $validFormats = ['csv', 'json', 'pdf', 'xlsx'];
    if (!in_array($format, $validFormats)) {
        ApiResponse::send(ApiResponse::validationError([
            'format' => ['Invalid export format. Valid formats: ' . implode(', ', $validFormats)]
        ]), 422);
        return;
    }
    
    // Build params from query string
    $params = [
        'start_date' => $_GET['start_date'] ?? null,
        'end_date' => $_GET['end_date'] ?? null,
        'year' => isset($_GET['year']) ? (int)$_GET['year'] : null,
        'employer_id' => $_GET['employer_id'] ?? null,
        'establishment_id' => $_GET['establishment_id'] ?? null,
        'provider_id' => $_GET['provider_id'] ?? null,
    ];
    
    // Remove null values
    $params = array_filter($params, fn($v) => $v !== null);
    
    $result = $vm->exportReport($internalType, $format, $params);
    
    $statusCode = $result['success'] ? 200 : 400;
    if (isset($result['errors'])) {
        $statusCode = 422;
    }
    ApiResponse::send($result, $statusCode);
}

// ========================================================================
// MAIN EXECUTION (for direct access)
// ========================================================================

if (basename($_SERVER['SCRIPT_FILENAME']) === 'reports.php') {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $basePath = '/api/v1/reports';
    
    $path = parse_url($requestUri, PHP_URL_PATH);
    
    if (strpos($path, $basePath) === 0) {
        $reportsPath = substr($path, strlen($basePath));
        $reportsPath = trim($reportsPath, '/');
    } else {
        $reportsPath = '';
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
        header('Access-Control-Allow-Methods: GET, OPTIONS');
    }
    
    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
    
    handleReportsRoute($reportsPath, $method);
}
