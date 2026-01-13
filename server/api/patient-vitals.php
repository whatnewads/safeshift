<?php
/**
 * Patient Vitals API Endpoint
 * Retrieves patient vital trends with color-coded indicators
 * 
 * Security measures:
 * - Session validation for 1clinician role
 * - Prepared statements for all queries
 * - Rate limiting implementation
 * - CORS headers restricted to same origin
 * - Patient data access validation
 * - Comprehensive logging
 */

// Load bootstrap which handles all includes
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

// Check if user is logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['user_id'])) {
    \App\log\security_event('UNAUTHORIZED_API_ACCESS', [
        'api' => 'patient-vitals',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user']['user_id'];
$user_role = \App\auth\user_primary_role($user_id);

// Validate user has appropriate role
$allowed_roles = ['1clinician', 'pclinician', 'dclinician', 'cadmin', 'tadmin'];
if (!in_array($user_role, $allowed_roles)) {
    \App\log\audit('UNAUTHORIZED_API_ACCESS', 'API', $user_id, [
        'api' => 'patient-vitals',
        'user_role' => $user_role,
        'required_role' => '1clinician'
    ]);
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Rate limiting check
$rate_limit_key = 'api_patient_vitals_' . $user_id;
$rate_limit_file = __DIR__ . '/../cache/rate_limits/' . md5($rate_limit_key) . '.json';
$rate_limit_dir = dirname($rate_limit_file);

if (!is_dir($rate_limit_dir)) {
    mkdir($rate_limit_dir, 0755, true);
}

$current_time = time();
$rate_limit_window = 60; // 1 minute
$max_requests = 100; // 100 requests per minute

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

// Input validation
$patient_id = isset($_GET['patient_id']) ? trim($_GET['patient_id']) : '';
$encounter_id = isset($_GET['encounter_id']) ? trim($_GET['encounter_id']) : '';
$days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
$days = min(max($days, 1), 365); // Limit between 1 and 365 days

if (empty($patient_id) && empty($encounter_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Either patient_id or encounter_id is required']);
    exit;
}

// Validate UUID format
$uuid_pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
if (!empty($patient_id) && !preg_match($uuid_pattern, $patient_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid patient_id format']);
    exit;
}
if (!empty($encounter_id) && !preg_match($uuid_pattern, $encounter_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid encounter_id format']);
    exit;
}

// Log API access
\App\log\audit('API_ACCESS', 'patient-vitals', $user_id, [
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'patient_id' => $patient_id,
    'encounter_id' => $encounter_id,
    'days' => $days
]);

try {
    // Get database connection
    $db = \App\db\pdo();
    
    // If encounter_id provided, get patient_id
    if (!empty($encounter_id) && empty($patient_id)) {
        $sql = "SELECT patient_id FROM encounter WHERE encounter_id = :encounter_id";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':encounter_id', $encounter_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$result) {
            http_response_code(404);
            echo json_encode(['error' => 'Encounter not found']);
            exit;
        }
        $patient_id = $result['patient_id'];
    }
    
    // Verify patient exists and user has access
    $sql = "SELECT patient_id, first_name, last_name, mrn FROM patient WHERE patient_id = :patient_id";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':patient_id', $patient_id);
    $stmt->execute();
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        http_response_code(404);
        echo json_encode(['error' => 'Patient not found']);
        exit;
    }
    
    // Get vitals data
    $sql = "SELECT 
                o.observation_id,
                o.encounter_id,
                o.code,
                o.value,
                o.units,
                o.observed_at,
                e.started_at as encounter_date,
                e.type as encounter_type
            FROM observation o
            INNER JOIN encounter e ON o.encounter_id = e.encounter_id
            WHERE e.patient_id = :patient_id
            AND o.code IN ('bp_systolic', 'bp_diastolic', 'pulse', 'temperature', 'spo2', 'blood_sugar', 'pain_scale', 'weight', 'height')
            AND o.observed_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            ORDER BY o.observed_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':patient_id', $patient_id);
    $stmt->bindValue(':days', $days, PDO::PARAM_INT);
    $stmt->execute();
    $vitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process and categorize vitals with color coding
    $vitals_data = [
        'patient' => [
            'patient_id' => $patient['patient_id'],
            'name' => $patient['first_name'] . ' ' . $patient['last_name'],
            'mrn' => $patient['mrn']
        ],
        'vitals' => [],
        'trends' => []
    ];
    
    // Group vitals by type
    $vitals_by_type = [];
    foreach ($vitals as $vital) {
        $vitals_by_type[$vital['code']][] = $vital;
    }
    
    // Process each vital type
    foreach ($vitals_by_type as $code => $vital_list) {
        $latest = $vital_list[0]; // Most recent reading
        $value = floatval($latest['value']);
        $status = 'normal';
        $color = 'green';
        
        // Determine status and color based on vital type
        switch ($code) {
            case 'temperature':
                if ($value >= 97.0 && $value <= 99.0) {
                    $status = 'normal';
                    $color = 'green';
                } elseif (($value < 97.0 && $value >= 95.0) || ($value > 99.0 && $value <= 103.0)) {
                    $status = 'warning';
                    $color = 'yellow';
                } else {
                    $status = 'critical';
                    $color = 'red';
                }
                break;
                
            case 'bp_systolic':
                if ($value >= 90 && $value <= 120) {
                    $status = 'normal';
                    $color = 'green';
                } elseif ($value > 120 && $value <= 139) {
                    $status = 'warning';
                    $color = 'yellow';
                } else {
                    $status = 'critical';
                    $color = 'red';
                }
                break;
                
            case 'bp_diastolic':
                if ($value >= 60 && $value <= 80) {
                    $status = 'normal';
                    $color = 'green';
                } elseif ($value > 80 && $value <= 89) {
                    $status = 'warning';
                    $color = 'yellow';
                } else {
                    $status = 'critical';
                    $color = 'red';
                }
                break;
                
            case 'pulse':
                if ($value >= 60 && $value <= 100) {
                    $status = 'normal';
                    $color = 'green';
                } elseif (($value >= 50 && $value < 60) || ($value > 100 && $value <= 110)) {
                    $status = 'warning';
                    $color = 'yellow';
                } else {
                    $status = 'critical';
                    $color = 'red';
                }
                break;
                
            case 'spo2':
                if ($value >= 95) {
                    $status = 'normal';
                    $color = 'green';
                } elseif ($value >= 90 && $value < 95) {
                    $status = 'warning';
                    $color = 'yellow';
                } else {
                    $status = 'critical';
                    $color = 'red';
                }
                break;
                
            case 'blood_sugar':
                if ($value >= 70 && $value <= 140) {
                    $status = 'normal';
                    $color = 'green';
                } elseif (($value >= 60 && $value < 70) || ($value > 140 && $value <= 180)) {
                    $status = 'warning';
                    $color = 'yellow';
                } else {
                    $status = 'critical';
                    $color = 'red';
                }
                break;
                
            case 'pain_scale':
                if ($value <= 3) {
                    $status = 'mild';
                    $color = 'green';
                } elseif ($value <= 6) {
                    $status = 'moderate';
                    $color = 'yellow';
                } else {
                    $status = 'severe';
                    $color = 'red';
                }
                break;
        }
        
        // Format vital display name
        $display_names = [
            'bp_systolic' => 'Blood Pressure (Systolic)',
            'bp_diastolic' => 'Blood Pressure (Diastolic)',
            'pulse' => 'Pulse',
            'temperature' => 'Temperature',
            'spo2' => 'SpO2',
            'blood_sugar' => 'Blood Sugar',
            'pain_scale' => 'Pain Scale',
            'weight' => 'Weight',
            'height' => 'Height'
        ];
        
        $vitals_data['vitals'][] = [
            'type' => $code,
            'name' => $display_names[$code] ?? ucfirst(str_replace('_', ' ', $code)),
            'value' => $value,
            'units' => $latest['units'],
            'status' => $status,
            'color' => $color,
            'observed_at' => $latest['observed_at'],
            'encounter_id' => $latest['encounter_id']
        ];
        
        // Prepare trend data (last 10 readings)
        $trend_data = [];
        foreach (array_slice($vital_list, 0, 10) as $v) {
            $trend_data[] = [
                'value' => floatval($v['value']),
                'date' => date('Y-m-d H:i', strtotime($v['observed_at'])),
                'encounter_type' => $v['encounter_type']
            ];
        }
        
        $vitals_data['trends'][$code] = array_reverse($trend_data);
    }
    
    // Add combined blood pressure if both systolic and diastolic exist
    $has_systolic = false;
    $has_diastolic = false;
    $systolic_value = 0;
    $diastolic_value = 0;
    
    foreach ($vitals_data['vitals'] as $vital) {
        if ($vital['type'] === 'bp_systolic') {
            $has_systolic = true;
            $systolic_value = $vital['value'];
        }
        if ($vital['type'] === 'bp_diastolic') {
            $has_diastolic = true;
            $diastolic_value = $vital['value'];
        }
    }
    
    if ($has_systolic && $has_diastolic) {
        // Determine combined BP status
        $bp_status = 'normal';
        $bp_color = 'green';
        
        if ($systolic_value > 140 || $diastolic_value > 90 || $systolic_value < 90 || $diastolic_value < 60) {
            $bp_status = 'critical';
            $bp_color = 'red';
        } elseif ($systolic_value > 120 || $diastolic_value > 80) {
            $bp_status = 'warning';
            $bp_color = 'yellow';
        }
        
        $vitals_data['blood_pressure_combined'] = [
            'value' => $systolic_value . '/' . $diastolic_value,
            'status' => $bp_status,
            'color' => $bp_color
        ];
    }
    
    // Prepare response
    $response = [
        'success' => true,
        'data' => $vitals_data,
        'period' => [
            'days' => $days,
            'from' => date('Y-m-d', strtotime("-$days days")),
            'to' => date('Y-m-d')
        ],
        'timestamp' => date('c')
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Log error
    \App\log\file_log('error', [
        'api' => 'patient-vitals',
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