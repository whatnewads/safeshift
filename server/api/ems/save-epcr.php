<?php
/**
 * EMS ePCR Save API Endpoint
 *
 * This endpoint handles saving EMS ePCR form data to the database
 * with proper field mappings and data validation
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/epcr-functions.php';

use App\auth;
use App\log;
use App\db;
use App\EPCR;

// Validate authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require login
auth\require_login();
$user = auth\current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Validate user has appropriate role
$user_id = $user['user_id'];
$user_role = auth\user_primary_role($user_id);
$allowed_roles = ['1clinician', 'pclinician', 'cadmin', 'tadmin'];
if (!in_array($user_role, $allowed_roles)) {
    log\audit('UNAUTHORIZED_ACCESS', 'save-epcr', $user_id);
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !auth\validate_csrf_token($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Set JSON header
header('Content-Type: application/json');

// Validate blood pressure format if provided
if (!empty($_POST['blood_pressure']) && !preg_match('/^\d{2,3}\/\d{2,3}$/', $_POST['blood_pressure'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid blood pressure format. Please use format: 120/80'
    ]);
    exit;
}

// Get database connection
$pdo = db\pdo();

// Start transaction
$pdo->beginTransaction();

try {
    // Add debug logging
    EPCR\debugLog('Starting ePCR save', $_POST);
    
    // 1. Create or update patient record
    $patient_id = EPCR\upsertPatient($pdo, $_POST, $user_id);
    EPCR\debugLog('Patient upserted', ['patient_id' => $patient_id]);
    
    // 2. Create encounter record
    $encounter_id = EPCR\createEncounter($pdo, $patient_id, $_POST, $user_id);
    EPCR\debugLog('Encounter created', ['encounter_id' => $encounter_id]);
    
    // 3. Create encounter_response (EMS-specific)
    EPCR\createEncounterResponse($pdo, $encounter_id, $_POST, $user_id);
    EPCR\debugLog('EMS response created');
    
    // 4. Insert crew members (if any)
    $crewData = [];
    // Extract crew member data from form fields
    $crewCount = 0;
    while (isset($_POST['crew_user_id'][$crewCount]) || isset($_POST['crew_role'][$crewCount])) {
        if (!empty($_POST['crew_user_id'][$crewCount]) || !empty($_POST['crew_role'][$crewCount])) {
            $crewData[] = [
                'user_id' => $_POST['crew_user_id'][$crewCount] ?? null,
                'role' => $_POST['crew_role'][$crewCount] ?? null,
                'certification_level' => $_POST['crew_cert'][$crewCount] ?? null
            ];
        }
        $crewCount++;
    }
    
    if (!empty($crewData)) {
        EPCR\insertCrewMembers($pdo, $encounter_id, $crewData, $user_id);
        EPCR\debugLog('Crew members inserted', ['count' => count($crewData)]);
    }
    
    // 5. Insert vitals
    EPCR\insertVitals($pdo, $encounter_id, $patient_id, $_POST, $user_id);
    EPCR\debugLog('Vitals inserted');
    
    // 6. Insert assessments
    EPCR\insertAssessments($pdo, $encounter_id, $patient_id, $_POST, $user_id);
    EPCR\debugLog('Assessments inserted');
    
    // 7. Insert medications administered (if any)
    $medData = [];
    // Extract medication data from form fields
    $medCount = 0;
    while (isset($_POST['med_name'][$medCount])) {
        if (!empty($_POST['med_name'][$medCount])) {
            $medData[] = [
                'name' => $_POST['med_name'][$medCount],
                'dose' => $_POST['med_dose'][$medCount] ?? '',
                'route' => $_POST['med_route'][$medCount] ?? '',
                'time' => $_POST['med_time'][$medCount] ?? null,
                'response' => $_POST['med_response'][$medCount] ?? null
            ];
        }
        $medCount++;
    }
    
    if (!empty($medData)) {
        EPCR\insertMedications($pdo, $encounter_id, $patient_id, $medData, $user_id);
        EPCR\debugLog('Medications inserted', ['count' => count($medData)]);
    }
    
    // 8. Handle signatures
    if (!empty($_POST['provider_signature'])) {
        EPCR\saveSignature($pdo, $encounter_id, $patient_id, 'provider_signature',
                          $_POST['provider_signature'], $user_id);
        EPCR\debugLog('Provider signature saved');
    }
    
    if (!empty($_POST['patient_signature'])) {
        EPCR\saveSignature($pdo, $encounter_id, $patient_id, 'patient_consent',
                          $_POST['patient_signature'], $user_id);
        EPCR\debugLog('Patient signature saved');
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Log successful save
    log\audit('EMS_EPCR_SAVED', 'save-epcr', $user_id, [
        'encounter_id' => $encounter_id,
        'patient_id' => $patient_id
    ]);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'encounter_id' => $encounter_id,
        'patient_id' => $patient_id,
        'message' => 'ePCR saved successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    $pdo->rollBack();
    
    // Log error
    error_log('EMS ePCR Save Error: ' . $e->getMessage());
    EPCR\debugLog('Save error', ['error' => $e->getMessage()], 'error');
    log\audit('EMS_EPCR_SAVE_ERROR', 'save-epcr', $user_id, [
        'error' => $e->getMessage()
    ]);
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to save ePCR: ' . $e->getMessage()
    ]);
}

?>