<?php
/**
 * EMS ePCR Validation API Endpoint
 *
 * This endpoint validates ePCR form data and returns validation errors
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON header
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/bootstrap.php';

use App\auth;
use App\log;

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
    log\audit('UNAUTHORIZED_ACCESS', 'validate-epcr', $user_id);
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

// Initialize validation errors array
$errors = [];

// Tab: Incident
if (empty($_POST['dispatch_time'])) {
    $errors['incident'][] = [
        'field' => 'dispatch-time',
        'message' => 'Dispatch Time is required',
        'severity' => 'error'
    ];
}

if (empty($_POST['unit_number'])) {
    $errors['incident'][] = [
        'field' => 'unit-number',
        'message' => 'Unit Number is required',
        'severity' => 'error'
    ];
}

if (empty($_POST['incident_address'])) {
    $errors['incident'][] = [
        'field' => 'incident-address',
        'message' => 'Incident Address is required',
        'severity' => 'error'
    ];
}

if (empty($_POST['chief_complaint']) && empty($_POST['complaint_details'])) {
    $errors['incident'][] = [
        'field' => 'chief-complaint',
        'message' => 'Chief Complaint is required - please select or describe',
        'severity' => 'error'
    ];
}

// Tab: Patient
if (empty($_POST['patient_name'])) {
    $errors['patient'][] = [
        'field' => 'patient-name',
        'message' => 'Patient Name is required',
        'severity' => 'error'
    ];
} else {
    // Validate name format (Last, First)
    if (strpos($_POST['patient_name'], ',') === false) {
        $errors['patient'][] = [
            'field' => 'patient-name',
            'message' => 'Patient Name must be in format: Last, First',
            'severity' => 'warning'
        ];
    }
}

if (empty($_POST['patient_dob'])) {
    $errors['patient'][] = [
        'field' => 'patient-dob',
        'message' => 'Date of Birth is required',
        'severity' => 'error'
    ];
}

if (empty($_POST['patient_gender'])) {
    $errors['patient'][] = [
        'field' => 'patient-gender',
        'message' => 'Gender is required',
        'severity' => 'error'
    ];
}

if (empty($_POST['patient_phone'])) {
    $errors['patient'][] = [
        'field' => 'patient-phone',
        'message' => 'Phone Number is required',
        'severity' => 'error'
    ];
}

// Tab: Assessment
$assessmentRequired = ['airway_status', 'breathing_status', 'circulation_status'];
foreach ($assessmentRequired as $field) {
    if (empty($_POST[$field])) {
        $errors['assessment'][] = [
            'field' => $field,
            'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required',
            'severity' => 'error'
        ];
    }
}

// Tab: Vitals
$hasVitals = !empty($_POST['blood_pressure']) || !empty($_POST['pulse_rate']) || 
             !empty($_POST['respiratory_rate']) || !empty($_POST['spo2']);

if (!$hasVitals) {
    $errors['vitals'][] = [
        'field' => 'vitals',
        'message' => 'At least one set of vital signs is required',
        'severity' => 'error'
    ];
}

// Validate blood pressure format if provided
if (!empty($_POST['blood_pressure'])) {
    if (!preg_match('/^\d{2,3}\/\d{2,3}$/', $_POST['blood_pressure'])) {
        $errors['vitals'][] = [
            'field' => 'blood_pressure',
            'message' => 'Blood Pressure must be in format: 120/80',
            'severity' => 'error'
        ];
    }
}

// Tab: Treatment
// No required fields, but validate medication administration if provided
if (isset($_POST['med_admin']) && is_array($_POST['med_admin'])) {
    foreach ($_POST['med_admin'] as $index => $med) {
        if (!empty($med['name']) && empty($med['dose'])) {
            $errors['treatment'][] = [
                'field' => 'med_admin_' . $index . '_dose',
                'message' => 'Medication dose is required',
                'severity' => 'error'
            ];
        }
        if (!empty($med['name']) && empty($med['route'])) {
            $errors['treatment'][] = [
                'field' => 'med_admin_' . $index . '_route',
                'message' => 'Medication route is required',
                'severity' => 'error'
            ];
        }
    }
}

// Tab: Narrative
if (empty($_POST['narrative']) && empty($_POST['additional_narrative'])) {
    $errors['narrative'][] = [
        'field' => 'narrative',
        'message' => 'Patient care narrative is required',
        'severity' => 'warning'
    ];
}

// Tab: Disposition
if (empty($_POST['transport_mode'])) {
    $errors['disposition'][] = [
        'field' => 'transport-mode',
        'message' => 'Transport Mode is required',
        'severity' => 'error'
    ];
}

if (!empty($_POST['transport_mode']) && $_POST['transport_mode'] !== 'Refused' && empty($_POST['transport_destination'])) {
    $errors['disposition'][] = [
        'field' => 'transport-destination',
        'message' => 'Destination Facility is required for transported patients',
        'severity' => 'error'
    ];
}

// Tab: Signatures
if (empty($_POST['provider_signature'])) {
    $errors['signatures'][] = [
        'field' => 'provider-signature',
        'message' => 'Provider Signature is required',
        'severity' => 'error'
    ];
}

// Count total errors and warnings
$errorCount = 0;
$warningCount = 0;
foreach ($errors as $tabErrors) {
    foreach ($tabErrors as $error) {
        if ($error['severity'] === 'error') {
            $errorCount++;
        } else {
            $warningCount++;
        }
    }
}

// Log validation
log\audit('EMS_EPCR_VALIDATED', 'validate-epcr', $user_id, [
    'errors' => $errorCount,
    'warnings' => $warningCount
]);

// Return validation results
echo json_encode([
    'success' => $errorCount === 0,
    'valid' => $errorCount === 0,
    'errors' => $errors,
    'summary' => [
        'errors' => $errorCount,
        'warnings' => $warningCount,
        'total' => $errorCount + $warningCount
    ]
]);
?>