<?php

declare(strict_types=1);

/**
 * Encounters API Endpoint
 *
 * Handles all clinical encounter-related API requests.
 * Routes:
 *   GET    /api/v1/encounters              - List encounters with filters
 *   GET    /api/v1/encounters/:id          - Get single encounter
 *   GET    /api/v1/encounters/patient/:id  - Get patient's encounters
 *   GET    /api/v1/encounters/today        - Get today's encounters
 *   GET    /api/v1/encounters/pending      - Get pending encounters for provider
 *   POST   /api/v1/encounters              - Create new encounter
 *   POST   /api/v1/encounters/:id/generate-narrative - Generate AI narrative for encounter
 *   PUT    /api/v1/encounters/:id          - Update encounter
 *   PUT    /api/v1/encounters/:id/vitals   - Record vitals
 *   PUT    /api/v1/encounters/:id/sign     - Sign/lock encounter
 *   PUT    /api/v1/encounters/:id/submit   - Submit for review
 *   PUT    /api/v1/encounters/:id/amend    - Amend signed encounter
 *   PUT    /api/v1/encounters/:id/finalize - Finalize encounter (triggers work-related notifications)
 *   DELETE /api/v1/encounters/:id          - Cancel encounter
 *
 * @package API\v1
 */

// Load interfaces first (required by entities)
require_once __DIR__ . '/../../model/Interfaces/EntityInterface.php';
require_once __DIR__ . '/../../model/Interfaces/RepositoryInterface.php';

require_once __DIR__ . '/../../model/Core/Database.php';
require_once __DIR__ . '/../../model/Entities/Encounter.php';
require_once __DIR__ . '/../../model/Entities/Patient.php';
require_once __DIR__ . '/../../model/Repositories/EncounterRepository.php';
require_once __DIR__ . '/../../model/Repositories/PatientRepository.php';
require_once __DIR__ . '/../../model/Validators/EncounterValidator.php';
require_once __DIR__ . '/../../ViewModel/EncounterViewModel.php';
require_once __DIR__ . '/../../ViewModel/Core/ApiResponse.php';

// Services for narrative generation
require_once __DIR__ . '/../../core/Services/BedrockService.php';
require_once __DIR__ . '/../../core/Services/NarrativePromptBuilder.php';

// Safe loading of EHRLogger - wrap in try-catch to prevent file load failure
$ehrLoggerPath = __DIR__ . '/../../core/Services/EHRLogger.php';
if (file_exists($ehrLoggerPath)) {
    try {
        require_once $ehrLoggerPath;
    } catch (\Throwable $e) {
        error_log("[Encounters] EHRLogger failed to load: " . $e->getMessage());
    }
} else {
    error_log("[Encounters] EHRLogger not found at: $ehrLoggerPath");
}

// Load field-level EHR logger for detailed operation tracking
$ehrFieldLoggerPath = __DIR__ . '/../../includes/ehr_field_logger.php';
if (file_exists($ehrFieldLoggerPath)) {
    require_once $ehrFieldLoggerPath;
}

use Model\Core\Database;
use Model\Validators\EncounterValidator;
use ViewModel\EncounterViewModel;
use ViewModel\Core\ApiResponse;
use Core\Services\EHRLogger;
use Core\Services\BedrockService;
use Core\Services\NarrativePromptBuilder;

/**
 * Check if user is authenticated
 *
 * @return bool
 */
function isEncounterUserAuthenticated(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        return false;
    }
    return isset($_SESSION['user']) && !empty($_SESSION['user']['user_id']);
}

/**
 * Get EHRLogger instance safely (returns null if unavailable)
 *
 * @return EHRLogger|null
 */
function getEhrLoggerSafe(): ?EHRLogger
{
    try {
        if (class_exists('Core\\Services\\EHRLogger')) {
            return EHRLogger::getInstance();
        }
    } catch (\Throwable $e) {
        error_log("[Encounters] EHRLogger unavailable: " . $e->getMessage());
    }
    return null;
}

/**
 * Route handler called by api/v1/index.php
 *
 * @param string $subPath The path after /api/v1/encounters/
 * @param string $method The HTTP method
 */
function handleEncountersRoute(string $subPath, string $method): void
{
    $logFile = __DIR__ . '/../../logs/encounters_debug.log';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - handleEncountersRoute START: subPath=$subPath, method=$method\n", FILE_APPEND);
    
    // Parse path segments from subPath
    $segments = array_filter(explode('/', $subPath));
    $segments = array_values($segments);
    
    // =========================================================================
    // AUTHENTICATION CHECK - Return 401 if user is not authenticated
    // =========================================================================
    if (!isEncounterUserAuthenticated()) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - AUTH FAILED: No valid session\n", FILE_APPEND);
        ApiResponse::send(ApiResponse::unauthorized('Authentication required. Please log in.'), 401);
        return;
    }
    
    try {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - About to get Database instance\n", FILE_APPEND);
        
        // Initialize database and view model
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Database OK, creating EncounterViewModel\n", FILE_APPEND);
        
        $viewModel = new EncounterViewModel($pdo);
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - EncounterViewModel created\n", FILE_APPEND);
        
        // Get user from session (session already started by bootstrap)
        // User data is stored in $_SESSION['user'] object (consistent with BaseViewModel)
        $userId = $_SESSION['user']['user_id'] ?? null;
        $clinicId = $_SESSION['user']['clinic_id'] ?? null;
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Session: userId=$userId, clinicId=$clinicId\n", FILE_APPEND);
        
        if ($userId) {
            $viewModel->setCurrentUser($userId);
        }
        if ($clinicId) {
            $viewModel->setCurrentClinic($clinicId);
        }
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - About to route request for method=$method\n", FILE_APPEND);
        
        // Route the request
        switch ($method) {
            case 'GET':
                handleEncounterGetRequest($viewModel, $segments);
                break;
                
            case 'POST':
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Calling handleEncounterPostRequest\n", FILE_APPEND);
                handleEncounterPostRequest($viewModel, $segments);
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - handleEncounterPostRequest completed\n", FILE_APPEND);
                break;
                
            case 'PUT':
                handleEncounterPutRequest($viewModel, $segments);
                break;
                
            case 'DELETE':
                handleEncounterDeleteRequest($viewModel, $segments);
                break;
                
            default:
                ApiResponse::send(ApiResponse::methodNotAllowed('Method not allowed'), 405);
        }
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - handleEncountersRoute completed successfully\n", FILE_APPEND);
        
    } catch (\Throwable $e) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - UNCAUGHT ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        file_put_contents($logFile, "  File: " . $e->getFile() . ":" . $e->getLine() . "\n", FILE_APPEND);
        file_put_contents($logFile, "  Trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);
        error_log("Encounters API error: " . $e->getMessage());
        ApiResponse::send(ApiResponse::serverError('Internal server error'), 500);
    }
}

/**
 * Handle GET requests
 */
function handleEncounterGetRequest(EncounterViewModel $viewModel, array $segments): void
{
    // Get EHRLogger safely - may be null if unavailable
    $ehrLogger = getEhrLoggerSafe();
    
    // GET /encounters - List all encounters
    if (empty($segments)) {
        $filters = [
            'patient_id' => $_GET['patient_id'] ?? $_GET['patientId'] ?? null,
            'provider_id' => $_GET['provider_id'] ?? $_GET['providerId'] ?? null,
            'clinic_id' => $_GET['clinic_id'] ?? $_GET['clinicId'] ?? null,
            'status' => $_GET['status'] ?? null,
            'encounter_type' => $_GET['encounter_type'] ?? $_GET['encounterType'] ?? null,
            'start_date' => $_GET['start_date'] ?? $_GET['startDate'] ?? null,
            'end_date' => $_GET['end_date'] ?? $_GET['endDate'] ?? null,
            'sort_by' => $_GET['sort_by'] ?? $_GET['sortBy'] ?? 'encounter_date',
            'sort_order' => $_GET['sort_order'] ?? $_GET['sortOrder'] ?? 'DESC',
        ];
        
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? $_GET['perPage'] ?? 50)));
        
        $response = $viewModel->getEncounters(array_filter($filters), $page, $perPage);
        
        // Log encounter list access (only if logger available)
        if ($ehrLogger !== null) {
            try {
                $ehrLogger->logOperation(EHRLogger::OP_READ, [
                    'details' => [
                        'action' => 'list_encounters',
                        'filters_applied' => array_keys(array_filter($filters)),
                        'page' => $page,
                        'per_page' => $perPage,
                        'results_count' => count($response['data']['encounters'] ?? []),
                    ],
                    'result' => ($response['status'] ?? 200) < 400 ? 'success' : 'failure',
                ], EHRLogger::CHANNEL_ENCOUNTER);
            } catch (\Throwable $e) {
                error_log("[Encounters] Logging failed: " . $e->getMessage());
            }
        }
        
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    $action = $segments[0];
    
    // GET /encounters/drafts - Get user's draft encounters (saved reports)
    if ($action === 'drafts') {
        $response = $viewModel->getDrafts();
        
        // Log draft access (only if logger available)
        if ($ehrLogger !== null) {
            try {
                $ehrLogger->logOperation(EHRLogger::OP_READ, [
                    'details' => [
                        'action' => 'list_draft_encounters',
                        'results_count' => count($response['data']['drafts'] ?? []),
                    ],
                    'result' => ($response['status'] ?? 200) < 400 ? 'success' : 'failure',
                ], EHRLogger::CHANNEL_ENCOUNTER);
            } catch (\Throwable $e) {
                error_log("[Encounters] Draft logging failed: " . $e->getMessage());
            }
        }
        
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // GET /encounters/today - Today's encounters
    // Note: clinic_id will be taken from session if not provided via query param
    // The EncounterViewModel.getTodaysEncounters() handles this and returns 400 if no clinic_id available
    if ($action === 'today') {
        $clinicId = $_GET['clinic_id'] ?? $_GET['clinicId'] ?? null;
        $response = $viewModel->getTodaysEncounters($clinicId);
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // GET /encounters/pending - Provider's pending encounters
    if ($action === 'pending') {
        $response = $viewModel->getMyPendingEncounters();
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // GET /encounters/patient/:patientId - Patient's encounters
    if ($action === 'patient' && isset($segments[1])) {
        $patientId = $segments[1];
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
        $response = $viewModel->getPatientEncounters($patientId, $limit);
        
        // Log PHI access for patient encounters (only if logger available)
        if ($ehrLogger !== null) {
            try {
                $ehrLogger->logPHIAccess(
                    $_SESSION['user']['user_id'] ?? 0,
                    $patientId,
                    'view_patient_encounters',
                    ['encounter_list']
                );
            } catch (\Throwable $e) {
                error_log("[Encounters] PHI access logging failed: " . $e->getMessage());
            }
        }
        
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // GET /encounters/:id/disclosures - Get encounter disclosures
    if (!empty($action) && isset($segments[1]) && $segments[1] === 'disclosures') {
        require_once __DIR__ . '/disclosures.php';
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        getEncounterDisclosures($pdo, $action);
        return;
    }
    
    // GET /encounters/:id - Single encounter
    if (!empty($action)) {
        $response = $viewModel->getEncounter($action);
        
        // Log encounter read access (only if logger available)
        if ($ehrLogger !== null) {
            try {
                $ehrLogger->logEncounterRead(
                    $action,
                    $response['data']['encounter']['patient_id'] ?? null
                );
            } catch (\Throwable $e) {
                error_log("[Encounters] Encounter read logging failed: " . $e->getMessage());
            }
        }
        
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    ApiResponse::send(ApiResponse::notFound('Invalid endpoint'), 404);
}

/**
 * Handle POST requests
 */
function handleEncounterPostRequest(EncounterViewModel $viewModel, array $segments): void
{
    $logFile = __DIR__ . '/../../logs/encounters_debug.log';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - handleEncounterPostRequest START, segments=" . json_encode($segments) . "\n", FILE_APPEND);
    
    // Safely get EHRLogger - may throw if SecureLogger fails
    try {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Getting EHRLogger instance...\n", FILE_APPEND);
        $ehrLogger = EHRLogger::getInstance();
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - EHRLogger obtained\n", FILE_APPEND);
    } catch (\Throwable $e) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - EHRLogger FAILED: " . $e->getMessage() . "\n", FILE_APPEND);
        $ehrLogger = null;
    }
    
    // POST /encounters - Create new encounter
    if (empty($segments)) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Empty segments, creating new encounter\n", FILE_APPEND);
        
        $data = getEncounterRequestBody();
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Request body: " . json_encode($data) . "\n", FILE_APPEND);
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Calling viewModel->createEncounter\n", FILE_APPEND);
        $response = $viewModel->createEncounter($data);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - createEncounter returned: " . json_encode($response) . "\n", FILE_APPEND);
        
        $success = ($response['status'] ?? 200) < 400 && isset($response['data']['encounter']);
        $encounterId = $response['data']['encounter']['encounter_id'] ?? uniqid('failed_', true);
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - success=$success, encounterId=$encounterId\n", FILE_APPEND);
        
        // Log encounter creation with EHRLogger (only if available)
        if ($ehrLogger !== null) {
            try {
                if ($success) {
                    $ehrLogger->logEncounterCreated(
                        $encounterId,
                        $data['patient_id'] ?? $data['patientId'] ?? null,
                        [
                            'encounter_type' => $data['encounter_type'] ?? $data['encounterType'] ?? null,
                        ]
                    );
                } else {
                    $ehrLogger->logError('CREATE_ENCOUNTER', $response['message'] ?? 'Failed to create encounter', [
                        'channel' => EHRLogger::CHANNEL_ENCOUNTER,
                    ]);
                }
            } catch (\Throwable $e) {
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - EHRLogger logging FAILED: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }
        
        // Log field-level details if function available
        if (function_exists('logEncounterCreationFields')) {
            $errors = $response['errors'] ?? ($response['data']['errors'] ?? []);
            logEncounterCreationFields($encounterId, $data, $success, $errors);
        }
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - About to send response\n", FILE_APPEND);
        ApiResponse::send($response, $response['status'] ?? 200);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Response sent, returning\n", FILE_APPEND);
        return;
    }
    
    $action = $segments[0];
    
    // POST /encounters/upload-document - Upload document/photo
    if ($action === 'upload-document') {
        require_once __DIR__ . '/encounters/upload-document.php';
        return;
    }
    
    // Check if first segment is an encounter ID
    $encounterId = $action;
    $subAction = $segments[1] ?? null;
    
    // POST /encounters/:id/vitals - Add vitals to encounter
    if (!empty($encounterId) && $subAction === 'vitals') {
        $data = getEncounterRequestBody();
        $response = $viewModel->addVitals($encounterId, $data);
        
        // Log vitals recording (only if logger available)
        if (($response['status'] ?? 200) < 400 && $ehrLogger !== null) {
            try {
                $ehrLogger->logVitalsRecorded($encounterId, $data);
            } catch (\Throwable $e) {
                error_log("[Encounters] Vitals logging failed: " . $e->getMessage());
            }
        }
        
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // POST /encounters/:id/assessments - Add assessment to encounter
    if (!empty($encounterId) && $subAction === 'assessments') {
        $data = getEncounterRequestBody();
        $response = $viewModel->addAssessment($encounterId, $data);
        
        // Log assessment addition (only if logger available)
        if (($response['status'] ?? 200) < 400 && $ehrLogger !== null) {
            try {
                $ehrLogger->logAssessmentAdded($encounterId, $data);
            } catch (\Throwable $e) {
                error_log("[Encounters] Assessment logging failed: " . $e->getMessage());
            }
        }
        
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // POST /encounters/:id/treatments - Add treatment to encounter
    if (!empty($encounterId) && $subAction === 'treatments') {
        $data = getEncounterRequestBody();
        $response = $viewModel->addTreatment($encounterId, $data);
        
        // Log treatment addition (only if logger available)
        if (($response['status'] ?? 200) < 400 && $ehrLogger !== null) {
            try {
                $ehrLogger->logTreatmentAdded($encounterId, $data);
            } catch (\Throwable $e) {
                error_log("[Encounters] Treatment logging failed: " . $e->getMessage());
            }
        }
        
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // POST /encounters/:id/signatures - Add signature to encounter
    if (!empty($encounterId) && $subAction === 'signatures') {
        $data = getEncounterRequestBody();
        $response = $viewModel->addSignature($encounterId, $data);
        
        // Log signature addition (only if logger available)
        if (($response['status'] ?? 200) < 400 && $ehrLogger !== null) {
            try {
                $ehrLogger->logSignatureAdded(
                    $encounterId,
                    $data['signature_type'] ?? $data['type'] ?? 'provider',
                    $_SESSION['user']['user_id'] ?? 0
                );
            } catch (\Throwable $e) {
                error_log("[Encounters] Signature logging failed: " . $e->getMessage());
            }
        }
        
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // POST /encounters/:id/disclosures - Record disclosure acknowledgment
    if (!empty($encounterId) && $subAction === 'disclosures') {
        require_once __DIR__ . '/disclosures.php';
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        $data = getEncounterRequestBody();
        
        // Check for batch operation
        if (isset($segments[2]) && $segments[2] === 'batch') {
            recordBatchDisclosureAcknowledgments($pdo, $encounterId, $data['disclosures'] ?? []);
        } else {
            recordDisclosureAcknowledgment($pdo, $encounterId, $data);
        }
        return;
    }
    
    // POST /encounters/:id/finalize - Finalize encounter (alternative to PUT)
    if (!empty($encounterId) && $subAction === 'finalize') {
        require_once __DIR__ . '/encounters/finalize.php';
        handleFinalizeEncounter($encounterId);
        return;
    }
    
    // POST /encounters/:id/generate-narrative - Generate AI narrative for encounter
    if (!empty($encounterId) && $subAction === 'generate-narrative') {
        handleGenerateNarrative($encounterId);
        return;
    }
    
    ApiResponse::send(ApiResponse::notFound('Invalid endpoint'), 404);
}

/**
 * Handle narrative generation for an encounter
 *
 * Uses BedrockService and NarrativePromptBuilder to generate an AI narrative.
 *
 * @param string $encounterId The encounter ID
 */
function handleGenerateNarrative(string $encounterId): void
{
    $logFile = __DIR__ . '/../../logs/encounters_debug.log';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - handleGenerateNarrative START: encounterId=$encounterId\n", FILE_APPEND);
    
    // Get EHRLogger safely
    $ehrLogger = getEhrLoggerSafe();
    
    try {
        // Validate encounter ID format (should be UUID or numeric)
        if (empty($encounterId) || (!preg_match('/^[a-f0-9\-]{36}$/i', $encounterId) && !is_numeric($encounterId))) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Invalid encounter ID format: $encounterId\n", FILE_APPEND);
            ApiResponse::send(ApiResponse::badRequest('Invalid encounter ID format'), 400);
            return;
        }
        
        // Initialize database
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        
        // Load encounter data
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Loading encounter data\n", FILE_APPEND);
        $encounterData = loadEncounterDataForNarrative($pdo, $encounterId);
        
        if ($encounterData === null) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Encounter not found: $encounterId\n", FILE_APPEND);
            ApiResponse::send(ApiResponse::notFound('Encounter not found'), 404);
            return;
        }
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Encounter data loaded: " . json_encode(array_keys($encounterData)) . "\n", FILE_APPEND);
        
        // Build prompt using NarrativePromptBuilder
        $promptBuilder = new NarrativePromptBuilder();
        
        // Validate encounter data has required fields
        if (!$promptBuilder->validateEncounterData($encounterData)) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Encounter data missing required fields\n", FILE_APPEND);
            ApiResponse::send(ApiResponse::badRequest(
                'Encounter is missing required data for narrative generation. ' .
                'Ensure encounter has patient information and chief complaint.'
            ), 400);
            return;
        }
        
        $prompt = $promptBuilder->buildPrompt($encounterData);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Prompt built, length=" . strlen($prompt) . "\n", FILE_APPEND);
        
        // Log data summary (no PHI)
        $dataSummary = $promptBuilder->getDataSummary($encounterData);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Data summary: " . json_encode($dataSummary) . "\n", FILE_APPEND);
        
        // Call BedrockService to generate narrative
        try {
            $bedrockService = new BedrockService();
        } catch (\RuntimeException $e) {
            // API key not configured
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - BedrockService initialization failed: " . $e->getMessage() . "\n", FILE_APPEND);
            ApiResponse::send([
                'success' => false,
                'error' => 'AI narrative service is not configured. Please contact administrator.',
                'status' => 503
            ], 503);
            return;
        }
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Calling BedrockService.generateNarrative()\n", FILE_APPEND);
        
        $narrative = $bedrockService->generateNarrative($prompt);
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Narrative generated, length=" . strlen($narrative) . "\n", FILE_APPEND);
        
        // Log successful generation (only if logger available)
        if ($ehrLogger !== null) {
            try {
                $ehrLogger->logOperation(EHRLogger::OP_READ, [
                    'details' => [
                        'action' => 'generate_narrative',
                        'encounter_id' => $encounterId,
                        'narrative_length' => strlen($narrative),
                        'data_summary' => $dataSummary,
                    ],
                    'result' => 'success',
                ], EHRLogger::CHANNEL_ENCOUNTER);
            } catch (\Throwable $e) {
                error_log("[Encounters] Narrative generation logging failed: " . $e->getMessage());
            }
        }
        
        // Return successful response
        ApiResponse::send([
            'success' => true,
            'narrative' => $narrative,
            'encounter_id' => $encounterId,
            'generated_at' => date('Y-m-d H:i:s'),
            'status' => 200
        ], 200);
        
    } catch (\InvalidArgumentException $e) {
        // Prompt building or validation error
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - InvalidArgumentException: " . $e->getMessage() . "\n", FILE_APPEND);
        ApiResponse::send([
            'success' => false,
            'error' => $e->getMessage(),
            'status' => 400
        ], 400);
        
    } catch (\RuntimeException $e) {
        // Bedrock API error
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - RuntimeException: " . $e->getMessage() . " (code=" . $e->getCode() . ")\n", FILE_APPEND);
        
        // Log error (only if logger available)
        if ($ehrLogger !== null) {
            try {
                $ehrLogger->logError('GENERATE_NARRATIVE', $e->getMessage(), [
                    'encounter_id' => $encounterId,
                    'error_code' => $e->getCode(),
                    'channel' => EHRLogger::CHANNEL_ENCOUNTER,
                ]);
            } catch (\Throwable $logE) {
                error_log("[Encounters] Error logging failed: " . $logE->getMessage());
            }
        }
        
        // Determine appropriate status code based on error
        $statusCode = 500;
        $errorMessage = 'Failed to generate narrative. Please try again.';
        
        $code = $e->getCode();
        if ($code === 401 || $code === 403) {
            $statusCode = 503;
            $errorMessage = 'AI service authentication failed. Please contact administrator.';
        } elseif ($code === 429) {
            $statusCode = 503;
            $errorMessage = 'AI service is currently busy. Please try again in a moment.';
        } elseif ($code >= 500 && $code < 600) {
            $statusCode = 503;
            $errorMessage = 'AI service is temporarily unavailable. Please try again later.';
        }
        
        ApiResponse::send([
            'success' => false,
            'error' => $errorMessage,
            'status' => $statusCode
        ], $statusCode);
        
    } catch (\Throwable $e) {
        // Unexpected error
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Unexpected error: " . $e->getMessage() . "\n", FILE_APPEND);
        file_put_contents($logFile, "  File: " . $e->getFile() . ":" . $e->getLine() . "\n", FILE_APPEND);
        error_log("Generate narrative error: " . $e->getMessage());
        
        ApiResponse::send([
            'success' => false,
            'error' => 'An unexpected error occurred while generating the narrative.',
            'status' => 500
        ], 500);
    }
}

/**
 * Load encounter data for narrative generation
 *
 * Assembles all necessary data from the database into the structure
 * expected by NarrativePromptBuilder.
 *
 * @param \PDO $pdo The database connection
 * @param string $encounterId The encounter ID
 * @return array|null The structured encounter data, or null if not found
 */
function loadEncounterDataForNarrative(\PDO $pdo, string $encounterId): ?array
{
    // Load encounter - using actual column names from the encounters table
    // Actual columns: encounter_id, patient_id, site_id, employer_name, encounter_type,
    // status, chief_complaint, onset_context, occurred_on, arrived_on, discharged_on,
    // disposition, npi_provider, created_at, created_by, modified_at, modified_by, deleted_at, deleted_by
    $sql = "SELECT
                e.encounter_id,
                e.patient_id,
                e.encounter_type,
                e.status,
                e.chief_complaint,
                e.onset_context,
                e.occurred_on,
                e.arrived_on,
                e.discharged_on,
                e.disposition,
                e.npi_provider,
                e.employer_name
            FROM encounters e
            WHERE e.encounter_id = :encounter_id
            AND e.deleted_at IS NULL";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['encounter_id' => $encounterId]);
    $encounter = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    if (!$encounter) {
        return null;
    }
    
    $patientId = $encounter['patient_id'];
    $npiProvider = $encounter['npi_provider'];
    
    // Load patient data
    $sql = "SELECT
                patient_id,
                legal_first_name,
                legal_last_name,
                dob,
                sex_assigned_at_birth
            FROM patients
            WHERE patient_id = :patient_id
            AND deleted_at IS NULL";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['patient_id' => $patientId]);
    $patient = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    // Calculate age
    $age = null;
    if ($patient && !empty($patient['dob'])) {
        try {
            $birthDate = new \DateTime($patient['dob']);
            $today = new \DateTime();
            $age = $birthDate->diff($today)->y;
        } catch (\Exception $e) {
            $age = null;
        }
    }
    
    // Load encounter observations (vitals) from encounter_observations table
    // Columns: obs_id, encounter_id, patient_id, label, posture, posture_other, value_num, value_text, unit, method, taken_at, notes
    $observations = [];
    try {
        $sql = "SELECT
                    label,
                    value_num,
                    value_text,
                    unit,
                    posture,
                    taken_at,
                    method
                FROM encounter_observations
                WHERE encounter_id = :encounter_id
                AND deleted_at IS NULL
                ORDER BY taken_at ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['encounter_id' => $encounterId]);
        $observations = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        // Table query failed
        error_log("[Narrative] encounter_observations query failed: " . $e->getMessage());
    }
    
    // Load medications administered from encounter_med_admin table
    // Columns: med_admin_id, encounter_id, patient_id, medication_name, dose, route, given_at, given_by, response, notes
    $medicationsAdministered = [];
    try {
        $sql = "SELECT
                    medication_name,
                    dose,
                    route,
                    given_at,
                    response,
                    notes
                FROM encounter_med_admin
                WHERE encounter_id = :encounter_id
                AND deleted_at IS NULL
                ORDER BY given_at ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['encounter_id' => $encounterId]);
        $medicationsAdministered = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        // Table query failed
        error_log("[Narrative] encounter_med_admin query failed: " . $e->getMessage());
    }
    
    // Load medical history from patient_conditions, patient_allergies, patient_medications tables
    $medicalHistory = [
        'conditions' => [],
        'current_medications' => [],
        'allergies' => [],
    ];
    
    // Load patient conditions
    try {
        $sql = "SELECT diagnosis, status FROM patient_conditions
                WHERE patient_id = :patient_id AND deleted_at IS NULL AND status = 'active'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['patient_id' => $patientId]);
        $conditions = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($conditions as $cond) {
            $medicalHistory['conditions'][] = $cond['diagnosis'];
        }
    } catch (\PDOException $e) {
        error_log("[Narrative] patient_conditions query failed: " . $e->getMessage());
    }
    
    // Load patient allergies
    try {
        $sql = "SELECT substance, reaction, severity FROM patient_allergies
                WHERE patient_id = :patient_id AND deleted_at IS NULL AND status = 'active'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['patient_id' => $patientId]);
        $allergies = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($allergies as $allergy) {
            $allergyText = $allergy['substance'];
            if (!empty($allergy['reaction'])) {
                $allergyText .= ' (' . $allergy['reaction'] . ')';
            }
            $medicalHistory['allergies'][] = $allergyText;
        }
    } catch (\PDOException $e) {
        error_log("[Narrative] patient_allergies query failed: " . $e->getMessage());
    }
    
    // Load patient medications (home medications)
    try {
        $sql = "SELECT med_name, dose, frequency FROM patient_medications
                WHERE patient_id = :patient_id AND deleted_at IS NULL AND active = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['patient_id' => $patientId]);
        $medications = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($medications as $med) {
            $medText = $med['med_name'];
            if (!empty($med['dose'])) {
                $medText .= ' ' . $med['dose'];
            }
            if (!empty($med['frequency'])) {
                $medText .= ' ' . $med['frequency'];
            }
            $medicalHistory['current_medications'][] = $medText;
        }
    } catch (\PDOException $e) {
        error_log("[Narrative] patient_medications query failed: " . $e->getMessage());
    }
    
    // Provider info - use NPI from encounter directly
    // The user table doesn't have npi/credentials columns, so we just use the NPI from encounter
    $provider = [
        'npi' => $npiProvider,
        'credentials' => null,
    ];
    
    // Build the structured data for NarrativePromptBuilder
    return [
        'encounter' => [
            'encounter_id' => $encounter['encounter_id'],
            'encounter_type' => $encounter['encounter_type'],
            'status' => $encounter['status'],
            'chief_complaint' => $encounter['chief_complaint'],
            'occurred_on' => $encounter['occurred_on'],
            'arrived_on' => $encounter['arrived_on'],
            'discharged_on' => $encounter['discharged_on'],
            'disposition' => $encounter['disposition'],
            'onset_context' => $encounter['onset_context'],
        ],
        'patient' => [
            'patient_id' => $patient['patient_id'] ?? $patientId,
            'name' => trim(($patient['legal_first_name'] ?? '') . ' ' . ($patient['legal_last_name'] ?? '')),
            'age' => $age,
            'sex' => $patient['sex_assigned_at_birth'] ?? null,
            'employer_name' => $encounter['employer_name'],
        ],
        'medical_history' => $medicalHistory,
        'observations' => $observations,
        'medications_administered' => $medicationsAdministered,
        'provider' => $provider,
    ];
}

/**
 * Convert vitals array from encounter JSON to observations format
 *
 * @param array $vitals The vitals array from encounter
 * @return array Formatted observations array
 */
function convertVitalsToObservations(array $vitals): array
{
    $observations = [];
    
    // Get timestamp if available
    $takenAt = $vitals['recorded_at'] ?? $vitals['taken_at'] ?? date('Y-m-d H:i:s');
    
    // Vital sign mapping
    $mapping = [
        'blood_pressure_systolic' => ['label' => 'BP Systolic', 'unit' => 'mmHg'],
        'bp_systolic' => ['label' => 'BP Systolic', 'unit' => 'mmHg'],
        'systolic' => ['label' => 'BP Systolic', 'unit' => 'mmHg'],
        'blood_pressure_diastolic' => ['label' => 'BP Diastolic', 'unit' => 'mmHg'],
        'bp_diastolic' => ['label' => 'BP Diastolic', 'unit' => 'mmHg'],
        'diastolic' => ['label' => 'BP Diastolic', 'unit' => 'mmHg'],
        'heart_rate' => ['label' => 'Pulse', 'unit' => 'bpm'],
        'pulse' => ['label' => 'Pulse', 'unit' => 'bpm'],
        'hr' => ['label' => 'Pulse', 'unit' => 'bpm'],
        'respiratory_rate' => ['label' => 'Resp Rate', 'unit' => 'breaths/min'],
        'resp_rate' => ['label' => 'Resp Rate', 'unit' => 'breaths/min'],
        'rr' => ['label' => 'Resp Rate', 'unit' => 'breaths/min'],
        'oxygen_saturation' => ['label' => 'SpO2', 'unit' => '%'],
        'spo2' => ['label' => 'SpO2', 'unit' => '%'],
        'o2_sat' => ['label' => 'SpO2', 'unit' => '%'],
        'temperature' => ['label' => 'Temp', 'unit' => '°F'],
        'temp' => ['label' => 'Temp', 'unit' => '°F'],
        'pain_level' => ['label' => 'Pain NRS', 'unit' => '/10'],
        'pain' => ['label' => 'Pain NRS', 'unit' => '/10'],
        'pain_nrs' => ['label' => 'Pain NRS', 'unit' => '/10'],
    ];
    
    foreach ($mapping as $key => $config) {
        if (isset($vitals[$key]) && $vitals[$key] !== '' && $vitals[$key] !== null) {
            $observations[] = [
                'label' => $config['label'],
                'value_num' => is_numeric($vitals[$key]) ? (float)$vitals[$key] : $vitals[$key],
                'unit' => $config['unit'],
                'taken_at' => $takenAt,
            ];
        }
    }
    
    return $observations;
}

/**
 * Handle PUT requests
 */
function handleEncounterPutRequest(EncounterViewModel $viewModel, array $segments): void
{
    // Get EHRLogger safely - may be null if unavailable
    $ehrLogger = getEhrLoggerSafe();
    
    if (empty($segments)) {
        ApiResponse::send(ApiResponse::badRequest('Encounter ID required'), 400);
        return;
    }
    
    $encounterId = $segments[0];
    $action = $segments[1] ?? null;
    $data = getEncounterRequestBody();
    
    // PUT /encounters/:id/vitals - Record vitals
    if ($action === 'vitals') {
        $response = $viewModel->recordVitals($encounterId, $data);
        
        $success = ($response['status'] ?? 200) < 400;
        
        // Log vitals update with EHRLogger (only if logger available)
        if ($success && $ehrLogger !== null) {
            try {
                $ehrLogger->logVitalsRecorded($encounterId, $data);
            } catch (\Throwable $e) {
                error_log("[Encounters] Vitals logging failed: " . $e->getMessage());
            }
        }
        
        // Log field-level vitals details if function available
        if (function_exists('logVitalsFields')) {
            $errors = $response['errors'] ?? [];
            logVitalsFields($encounterId, $data, $success, $errors);
        }
        
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // PUT /encounters/:id/sign - Sign/lock encounter
    if ($action === 'sign') {
        $response = $viewModel->signEncounter($encounterId);
        
        // Log encounter signing (only if logger available)
        if (($response['status'] ?? 200) < 400 && $ehrLogger !== null) {
            try {
                $ehrLogger->logEncounterSigned($encounterId, $_SESSION['user']['user_id'] ?? 0);
            } catch (\Throwable $e) {
                error_log("[Encounters] Signing logging failed: " . $e->getMessage());
            }
        }
        
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // PUT /encounters/:id/submit - Submit for review
    if ($action === 'submit') {
        // Validate encounter data before submission
        $validationErrors = EncounterValidator::validateForFinalization($data);
        if (!empty($validationErrors)) {
            // Log validation failure (only if logger available)
            if ($ehrLogger !== null) {
                try {
                    $ehrLogger->logOperation(EHRLogger::OP_UPDATE, [
                        'details' => [
                            'action' => 'submit_encounter_validation_failed',
                            'encounter_id' => $encounterId,
                            'validation_errors' => $validationErrors,
                        ],
                        'result' => 'failure',
                    ], EHRLogger::CHANNEL_ENCOUNTER);
                } catch (\Throwable $e) {
                    error_log("[Encounters] Validation failure logging failed: " . $e->getMessage());
                }
            }
            
            // Log field-level validation failures if function available
            if (function_exists('logEncounterSubmissionFields')) {
                // Convert validation errors to field results
                $fieldResults = [];
                foreach ($validationErrors as $field => $error) {
                    $fieldResults[$field] = is_string($error) ? $error : false;
                }
                logEncounterSubmissionFields($encounterId, $fieldResults, false);
            }
            
            ApiResponse::send(ApiResponse::validationError(
                $validationErrors,
                'Cannot submit: Please complete all required fields'
            ), 422);
            return;
        }
        
        $response = $viewModel->submitEncounter($encounterId);
        
        $success = ($response['status'] ?? 200) < 400;
        
        // Log status transition (only if logger available)
        if ($success && $ehrLogger !== null) {
            try {
                $ehrLogger->logStatusTransition($encounterId, 'in_progress', 'pending_review', 'submitted_for_review');
            } catch (\Throwable $e) {
                error_log("[Encounters] Status transition logging failed: " . $e->getMessage());
            }
        }
        
        // Log field-level submission result if function available
        if (function_exists('logEncounterSubmissionFields')) {
            // Build field results from validated data
            $fieldResults = [];
            foreach (array_keys($data) as $field) {
                $fieldResults[$field] = $success;
            }
            logEncounterSubmissionFields($encounterId, $fieldResults, $success);
        }
        
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // PUT /encounters/:id/amend - Amend signed encounter
    if ($action === 'amend') {
        $response = $viewModel->amendEncounter($encounterId, $data);
        
        // Log amendment (only if logger available)
        if (($response['status'] ?? 200) < 400 && $ehrLogger !== null) {
            try {
                $ehrLogger->logEncounterAmended(
                    $encounterId,
                    $data['reason'] ?? $data['amendment_reason'] ?? 'No reason provided',
                    $_SESSION['user']['user_id'] ?? 0
                );
            } catch (\Throwable $e) {
                error_log("[Encounters] Amendment logging failed: " . $e->getMessage());
            }
        }
        
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    // PUT /encounters/:id/finalize - Finalize encounter (triggers work-related notifications)
    if ($action === 'finalize') {
        require_once __DIR__ . '/encounters/finalize.php';
        handleFinalizeEncounter($encounterId);
        return;
    }
    
    // PUT /encounters/:id - Update encounter
    if ($action === null) {
        $response = $viewModel->updateEncounter($encounterId, $data);
        
        $success = ($response['status'] ?? 200) < 400;
        
        // Log encounter update with EHRLogger (only if logger available)
        if ($success && $ehrLogger !== null) {
            try {
                $ehrLogger->logEncounterUpdated($encounterId, array_keys($data));
            } catch (\Throwable $e) {
                error_log("[Encounters] Update logging failed: " . $e->getMessage());
            }
        }
        
        // Log field-level update details if function available
        if (function_exists('logEncounterUpdateFields')) {
            $errors = $response['errors'] ?? ($response['data']['errors'] ?? []);
            logEncounterUpdateFields($encounterId, $data, $success, $errors);
        }
        
        ApiResponse::send($response, $response['status'] ?? 200);
        return;
    }
    
    ApiResponse::send(ApiResponse::notFound('Invalid endpoint'), 404);
}

/**
 * Handle DELETE requests
 */
function handleEncounterDeleteRequest(EncounterViewModel $viewModel, array $segments): void
{
    // Get EHRLogger safely - may be null if unavailable
    $ehrLogger = getEhrLoggerSafe();
    
    if (empty($segments)) {
        ApiResponse::send(ApiResponse::badRequest('Encounter ID required'), 400);
        return;
    }
    
    $encounterId = $segments[0];
    $response = $viewModel->deleteEncounter($encounterId);
    
    // Log encounter deletion/cancellation (only if logger available)
    if (($response['status'] ?? 200) < 400 && $ehrLogger !== null) {
        try {
            $ehrLogger->logEncounterDeleted($encounterId, 'user_cancelled');
        } catch (\Throwable $e) {
            error_log("[Encounters] Delete logging failed: " . $e->getMessage());
        }
    }
    
    ApiResponse::send($response, $response['status'] ?? 200);
}

/**
 * Get JSON request body
 */
function getEncounterRequestBody(): array
{
    $json = file_get_contents('php://input');
    if (empty($json)) {
        return [];
    }
    
    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }
    
    return $data;
}
