<?php
/**
 * EMS ePCR Submit API Endpoint
 *
 * This endpoint handles final submission of EMS ePCR form data
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON header
header('Content-Type: application/json');

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
    log\audit('UNAUTHORIZED_ACCESS', 'submit-epcr', $user_id);
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

// Validate required fields
$errors = [];

// Required fields validation
$requiredFields = [
    'unit_number' => 'Unit Number',
    'dispatch_time' => 'Dispatch Time',
    'patient_name' => 'Patient Name',
    'patient_dob' => 'Date of Birth',
    'patient_gender' => 'Gender',
    'patient_phone' => 'Phone Number',
    'incident_address' => 'Incident Address'
];

foreach ($requiredFields as $field => $label) {
    if (empty($_POST[$field])) {
        $errors[] = "$label is required";
    }
}

// Check for chief complaint
if (empty($_POST['chief_complaint']) && empty($_POST['complaint_details'])) {
    $errors[] = "Chief Complaint is required";
}

// Check for at least one vital sign
$hasVitals = !empty($_POST['blood_pressure']) || !empty($_POST['pulse_rate']) || 
             !empty($_POST['respiratory_rate']) || !empty($_POST['spo2']);
if (!$hasVitals) {
    $errors[] = "At least one vital sign is required";
}

// Check for provider signature
if (empty($_POST['provider_signature'])) {
    $errors[] = "Provider Signature is required";
}

// Check transport mode
if (empty($_POST['transport_mode'])) {
    $errors[] = "Transport Mode is required";
}

// If errors, return them
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Please complete all required fields',
        'errors' => $errors
    ]);
    exit;
}

// If we get here, all required fields are present
// Get database connection
$pdo = db\pdo();

// Start transaction
$pdo->beginTransaction();

try {
    // Add debug logging
    EPCR\debugLog('Starting ePCR submission', $_POST);
    
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
    
    // 9. Update encounter status to completed
    $stmt = $pdo->prepare("
        UPDATE encounters 
        SET status = 'completed',
            discharged_on = NOW(),
            modified_at = NOW(),
            modified_by = :user_id
        WHERE encounter_id = :encounter_id
    ");
    
    $stmt->execute([
        'encounter_id' => $encounter_id,
        'user_id' => $user_id
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    // Log successful submission
    log\audit('EMS_EPCR_SUBMITTED', 'submit-epcr', $user_id, [
        'encounter_id' => $encounter_id,
        'patient_id' => $patient_id
    ]);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'encounter_id' => $encounter_id,
        'patient_id' => $patient_id,
        'message' => 'ePCR submitted successfully',
        'redirect' => '/dashboards/1clinician/'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    $pdo->rollBack();
    
    // Log error
    error_log('EMS ePCR Submit Error: ' . $e->getMessage());
    EPCR\debugLog('Submit error', ['error' => $e->getMessage()], 'error');
    log\audit('EMS_EPCR_SUBMIT_ERROR', 'submit-epcr', $user_id, [
        'error' => $e->getMessage()
    ]);
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to submit ePCR: ' . $e->getMessage()
    ]);
}

?>