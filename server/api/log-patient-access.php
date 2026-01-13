<?php
/**
 * Log Patient Access API Endpoint
 * Records when a user views or edits a patient chart
 * 
 * Feature 1.1: Recent Patients / Recently Viewed
 * 
 * POST /api/v1/log-patient-access
 * 
 * Request body:
 * {
 *   "patient_id": "string",
 *   "access_type": "view" | "edit"
 * }
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Set headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Session validation
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['user_id'])) {
    \App\log\security_event('UNAUTHORIZED_API_ACCESS', [
        'api' => 'log-patient-access',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user']['user_id'];

// Get request body
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!$input || !isset($input['patient_id']) || !isset($input['access_type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields: patient_id, access_type']);
    exit;
}

$patient_id = $input['patient_id'];
$access_type = $input['access_type'];

// Validate access type
if (!in_array($access_type, ['view', 'edit'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid access_type. Must be "view" or "edit"']);
    exit;
}

// CSRF validation
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';
if (!$csrf_token || $csrf_token !== ($_SESSION['csrf_token'] ?? '')) {
    \App\log\security_event('CSRF_VALIDATION_FAILED', [
        'api' => 'log-patient-access',
        'user_id' => $user_id
    ]);
    http_response_code(403);
    echo json_encode(['error' => 'CSRF validation failed']);
    exit;
}

try {
    // Get database connection
    $db = \App\db\pdo();
    
    // Check if patient exists
    $stmt = $db->prepare("SELECT patient_id FROM patients WHERE patient_id = :patient_id AND deleted_at IS NULL");
    $stmt->execute(['patient_id' => $patient_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Patient not found']);
        exit;
    }
    
    // Create PatientAccessService instance
    $accessService = new \Core\Services\PatientAccessService($db);
    
    // Log the access
    $success = $accessService->logAccess($patient_id, $user_id, $access_type);
    
    if ($success) {
        // Also log to audit trail
        \App\log\audit('PATIENT_ACCESS', 'Patient Chart', $user_id, [
            'patient_id' => $patient_id,
            'access_type' => $access_type,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Access logged successfully',
            'timestamp' => date('c')
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to log access',
            'timestamp' => date('c')
        ]);
    }
    
} catch (Exception $e) {
    // Log error
    \App\log\file_log('error', [
        'api' => 'log-patient-access',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'user_id' => $user_id,
        'patient_id' => $patient_id
    ]);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'timestamp' => date('c')
    ]);
}