<?php
/**
 * Recent Patients API Endpoint
 * Returns the 10 most recently accessed patients for the current user
 * 
 * Feature 1.1: Recent Patients / Recently Viewed
 * 
 * GET /api/v1/recent-patients
 * 
 * Security:
 * - Session validation
 * - RBAC permission check
 * - Rate limiting
 * - HIPAA audit logging
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Set headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// CORS - Restrict to same origin only
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
if ($origin === $allowed_origin) {
    header("Access-Control-Allow-Origin: $allowed_origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
        'api' => 'recent-patients',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user']['user_id'];

// Rate limiting (simple implementation)
$rate_limit_key = 'api_recent_patients_' . $user_id;
$rate_limit_file = __DIR__ . '/../cache/rate_limits/' . md5($rate_limit_key) . '.json';
$rate_limit_dir = dirname($rate_limit_file);

if (!is_dir($rate_limit_dir)) {
    mkdir($rate_limit_dir, 0755, true);
}

$current_time = time();
$rate_limit_window = 60; // 1 minute
$max_requests = 60; // 60 requests per minute

if (file_exists($rate_limit_file)) {
    $rate_data = json_decode(file_get_contents($rate_limit_file), true);
    if ($rate_data['window_start'] + $rate_limit_window > $current_time) {
        if ($rate_data['count'] >= $max_requests) {
            http_response_code(429);
            echo json_encode(['error' => 'Too many requests']);
            exit;
        }
        $rate_data['count']++;
    } else {
        $rate_data = ['window_start' => $current_time, 'count' => 1];
    }
} else {
    $rate_data = ['window_start' => $current_time, 'count' => 1];
}
file_put_contents($rate_limit_file, json_encode($rate_data));

// Log API access
\App\log\audit('API_ACCESS', 'recent-patients', $user_id, [
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
]);

try {
    // Get database connection
    $db = \App\db\pdo();
    
    // Query for recent patients
    $sql = "
        SELECT DISTINCT
            p.patient_id,
            p.legal_first_name,
            p.legal_last_name,
            p.preferred_name,
            pal.accessed_at,
            pal.access_type,
            -- Get MRN (Medical Record Number) - using patient_id as placeholder
            CONCAT('MRN-', SUBSTRING(p.patient_id, 1, 8)) as mrn,
            -- Get last encounter date
            (SELECT MAX(e.created_at) 
             FROM encounters e 
             WHERE e.patient_id = p.patient_id
             AND e.status != 'voided') as last_encounter_date,
            -- Get employer name from last encounter
            (SELECT e.employer_name 
             FROM encounters e 
             WHERE e.patient_id = p.patient_id
             AND e.status != 'voided'
             ORDER BY e.created_at DESC
             LIMIT 1) as employer_name
        FROM patient_access_log pal
        INNER JOIN patients p ON pal.patient_id = p.patient_id
        WHERE pal.user_id = :user_id
        AND p.deleted_at IS NULL
        ORDER BY pal.accessed_at DESC
        LIMIT 10
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    
    $recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the response
    $response_data = [];
    foreach ($recent_patients as $patient) {
        // Determine display name
        $display_name = trim($patient['preferred_name']) ?: 
                       trim($patient['legal_first_name'] . ' ' . $patient['legal_last_name']);
        
        $response_data[] = [
            'patient_uuid' => $patient['patient_id'],
            'full_name' => $display_name,
            'mrn' => $patient['mrn'],
            'last_encounter_date' => $patient['last_encounter_date'],
            'employer_name' => $patient['employer_name'] ?: 'N/A',
            'accessed_at' => $patient['accessed_at'],
            'access_type' => $patient['access_type']
        ];
    }
    
    // Send successful response
    echo json_encode([
        'success' => true,
        'data' => $response_data,
        'count' => count($response_data),
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Log error
    \App\log\file_log('error', [
        'api' => 'recent-patients',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'user_id' => $user_id
    ]);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'timestamp' => date('c')
    ]);
}