<?php

declare(strict_types=1);

/**
 * Patients API Endpoint
 *
 * Handles all patient-related API requests.
 * Routes:
 * - GET    /api/v1/patients                      - List patients with filters
 * - GET    /api/v1/patients/search?q=            - Quick search patients
 * - GET    /api/v1/patients/recent               - Get recent patients
 * - GET    /api/v1/patients/mrn/{mrn}            - Find by MRN
 * - GET    /api/v1/patients/{id}                 - Get single patient
 * - POST   /api/v1/patients                      - Create new patient
 * - PUT    /api/v1/patients/{id}                 - Update patient
 * - DELETE /api/v1/patients/{id}                 - Deactivate patient
 * - POST   /api/v1/patients/{id}/reactivate      - Reactivate patient
 * - GET    /api/v1/patients/{id}/encounters      - Get patient encounters
 *
 * @package SafeShift\API\v1
 */

use ViewModel\PatientViewModel;
use ViewModel\Core\ApiResponse;

/**
 * Handle patients route
 *
 * @param string $subPath The path after /patients/
 * @param string $method HTTP method
 */
function handlePatientsRoute(string $subPath, string $method): void
{
    // Get PDO instance using the db helper function (same pattern as working legacy endpoints)
    $pdo = \App\db\pdo();

    // Require authentication - check for user object with user_id (consistent with BaseViewModel)
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['user_id'])) {
        ApiResponse::send(ApiResponse::error('Authentication required'), 401);
        return;
    }

    $userId = $_SESSION['user']['user_id'];
    $clinicId = $_SESSION['user']['clinic_id'] ?? $_SESSION['clinic_id'] ?? null;
    
    // Initialize ViewModel
    $viewModel = new PatientViewModel($pdo);
    $viewModel->setCurrentUser($userId);
    if ($clinicId) {
        $viewModel->setCurrentClinic($clinicId);
    }

    // Parse subpath for routing
    $segments = array_filter(explode('/', $subPath));
    $segments = array_values($segments);
    
    $action = $segments[0] ?? '';
    $subAction = $segments[1] ?? '';

    // Route based on method and path
    switch ($method) {
        case 'GET':
            handleGetPatients($viewModel, $action, $subAction, $segments);
            break;
            
        case 'POST':
            handlePostPatients($viewModel, $action, $subAction);
            break;
            
        case 'PUT':
        case 'PATCH':
            handlePutPatients($viewModel, $action);
            break;
            
        case 'DELETE':
            handleDeletePatients($viewModel, $action);
            break;
            
        default:
            ApiResponse::send(ApiResponse::error('Method not allowed'), 405);
    }
}

/**
 * Handle GET requests for patients
 */
function handleGetPatients(PatientViewModel $viewModel, string $action, string $subAction, array $segments): void
{
    // GET /patients/search?q=
    if ($action === 'search') {
        $query = $_GET['q'] ?? $_GET['query'] ?? '';
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
        
        $result = $viewModel->searchPatients($query, $limit);
        ApiResponse::send($result, $result['success'] ? 200 : ($result['error']['code'] ?? 500));
        return;
    }
    
    // GET /patients/recent
    if ($action === 'recent') {
        $days = max(1, (int)($_GET['days'] ?? 7));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
        
        $result = $viewModel->getRecentPatients($days, $limit);
        ApiResponse::send($result, $result['success'] ? 200 : ($result['error']['code'] ?? 500));
        return;
    }

    // GET /patients/mrn/{mrn}
    if ($action === 'mrn' && !empty($subAction)) {
        $result = $viewModel->getPatientByMrn($subAction);
        ApiResponse::send($result, $result['success'] ? 200 : ($result['error']['code'] ?? 500));
        return;
    }
    
    // GET /patients/{id}/encounters
    if (isPatientUuid($action) && $subAction === 'encounters') {
        // This would typically call an EncounterViewModel
        // For now, return a placeholder
        ApiResponse::send(ApiResponse::error('Encounters endpoint not yet implemented'), 501);
        return;
    }
    
    // GET /patients/{id} - Get single patient
    if (!empty($action) && isPatientUuid($action)) {
        $result = $viewModel->getPatient($action);
        ApiResponse::send($result, $result['success'] ? 200 : ($result['error']['code'] ?? 500));
        return;
    }
    
    // GET /patients - List patients with filters
    $filters = [
        'search' => $_GET['search'] ?? $_GET['q'] ?? null,
        'employer_id' => $_GET['employer_id'] ?? $_GET['employerId'] ?? null,
        'active' => isset($_GET['active']) ? filter_var($_GET['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : true,
        'sort_by' => $_GET['sort_by'] ?? $_GET['sortBy'] ?? 'last_name',
        'sort_order' => $_GET['sort_order'] ?? $_GET['sortOrder'] ?? 'ASC',
    ];
    
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int)($_GET['per_page'] ?? $_GET['limit'] ?? 50)));
    
    $result = $viewModel->getPatients($filters, $page, $perPage);
    ApiResponse::send($result, $result['success'] ? 200 : ($result['error']['code'] ?? 500));
}

/**
 * Handle POST requests for patients
 */
function handlePostPatients(PatientViewModel $viewModel, string $action, string $subAction): void
{
    // POST /patients/{id}/reactivate
    if (isPatientUuid($action) && $subAction === 'reactivate') {
        $result = $viewModel->reactivatePatient($action);
        ApiResponse::send($result, $result['success'] ? 200 : ($result['error']['code'] ?? 500));
        return;
    }
    
    // POST /patients - Create new patient
    if (empty($action)) {
        $input = getPatientJsonInput();
        
        if (empty($input)) {
            ApiResponse::send(ApiResponse::error('Request body is required'), 400);
            return;
        }
        
        $result = $viewModel->createPatient($input);
        ApiResponse::send($result, $result['success'] ? 201 : ($result['error']['code'] ?? 500));
        return;
    }
    
    // Invalid POST route
    ApiResponse::send(ApiResponse::error('Invalid endpoint'), 404);
}

/**
 * Handle PUT/PATCH requests for patients
 */
function handlePutPatients(PatientViewModel $viewModel, string $action): void
{
    // PUT /patients/{id} - Update patient
    if (!empty($action) && isPatientUuid($action)) {
        $input = getPatientJsonInput();
        
        if (empty($input)) {
            ApiResponse::send(ApiResponse::error('Request body is required'), 400);
            return;
        }
        
        $result = $viewModel->updatePatient($action, $input);
        ApiResponse::send($result, $result['success'] ? 200 : ($result['error']['code'] ?? 500));
        return;
    }
    
    ApiResponse::send(ApiResponse::error('Patient ID is required'), 400);
}

/**
 * Handle DELETE requests for patients
 */
function handleDeletePatients(PatientViewModel $viewModel, string $action): void
{
    // DELETE /patients/{id} - Deactivate patient
    if (!empty($action) && isPatientUuid($action)) {
        $result = $viewModel->deletePatient($action);
        ApiResponse::send($result, $result['success'] ? 200 : ($result['error']['code'] ?? 500));
        return;
    }
    
    ApiResponse::send(ApiResponse::error('Patient ID is required'), 400);
}

/**
 * Check if string is a valid UUID
 */
function isPatientUuid(string $value): bool
{
    return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
}

/**
 * Get JSON input from request body
 */
function getPatientJsonInput(): array
{
    $input = file_get_contents('php://input');
    if (empty($input)) {
        return [];
    }
    
    $decoded = json_decode($input, true);
    return is_array($decoded) ? $decoded : [];
}
