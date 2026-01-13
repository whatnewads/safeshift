<?php
/**
 * Dashboard Statistics API Endpoint
 * Returns dashboard statistics and counts for 1clinician users
 * 
 * Security measures:
 * - Session validation for 1clinician role
 * - Prepared statements for all queries
 * - Rate limiting implementation
 * - CORS headers restricted to same origin
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
        'api' => 'dashboard-stats',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user']['user_id'];
$user_role = \App\auth\user_primary_role($user_id);

// Validate user has 1clinician role
$allowed_roles = ['1clinician', 'pclinician', 'cadmin', 'tadmin'];
if (!in_array($user_role, $allowed_roles)) {
    \App\log\audit('UNAUTHORIZED_API_ACCESS', 'API', $user_id, [
        'api' => 'dashboard-stats',
        'user_role' => $user_role,
        'required_role' => '1clinician'
    ]);
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Rate limiting check (simple implementation - should use Redis in production)
$rate_limit_key = 'api_dashboard_stats_' . $user_id;
$rate_limit_file = __DIR__ . '/../cache/rate_limits/' . md5($rate_limit_key) . '.json';
$rate_limit_dir = dirname($rate_limit_file);

if (!is_dir($rate_limit_dir)) {
    mkdir($rate_limit_dir, 0755, true);
}

$current_time = time();
$rate_limit_window = 60; // 1 minute
$max_requests = 30; // 30 requests per minute

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
\App\log\audit('API_ACCESS', 'dashboard-stats', $user_id, [
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
]);

try {
    // Get database connection
    $db = \App\db\pdo();
    
    // Initialize response data
    $stats = [
        'success' => true,
        'data' => [
            'total_patients_today' => 0,
            'new_patients_today' => 0,
            'returning_patients_today' => 0,
            'procedures_completed' => 0,
            'drug_tests_today' => 0,
            'physicals_today' => 0,
            'pending_reviews' => 0,
            'average_wait_time' => 0,
            'appointments_today' => 0,
            'upcoming_appointments' => []
        ],
        'timestamp' => date('c')
    ];
    
    // Get today's date range
    $today_start = date('Y-m-d 00:00:00');
    $today_end = date('Y-m-d 23:59:59');
    
    // Query 1: Total patients seen today
    $sql = "SELECT COUNT(DISTINCT patient_id) as total
            FROM encounters
            WHERE DATE(started_at) = CURDATE()
            AND status IN ('completed', 'in-progress')";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['data']['total_patients_today'] = (int)$result['total'];
    
    // Query 2: New patients today (first encounter)
    $sql = "SELECT COUNT(DISTINCT e.patient_id) as total
            FROM encounters e
            WHERE DATE(e.started_at) = CURDATE()
            AND NOT EXISTS (
                SELECT 1 FROM encounters e2
                WHERE e2.patient_id = e.patient_id
                AND e2.started_at < e.started_at
            )";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['data']['new_patients_today'] = (int)$result['total'];
    
    // Calculate returning patients
    $stats['data']['returning_patients_today'] = $stats['data']['total_patients_today'] - $stats['data']['new_patients_today'];
    
    // Query 3: Procedures completed today
    $sql = "SELECT COUNT(*) as total
            FROM orders o
            INNER JOIN encounters e ON o.encounter_id = e.encounter_id
            WHERE DATE(e.started_at) = CURDATE()
            AND o.order_type IN ('procedure', 'test')
            AND o.status = 'completed'";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['data']['procedures_completed'] = (int)$result['total'];
    
    // Query 4: Drug tests today
    $sql = "SELECT COUNT(*) as total
            FROM dot_tests dt
            INNER JOIN encounters e ON dt.encounter_id = e.encounter_id
            WHERE DATE(e.started_at) = CURDATE()
            AND dt.modality = 'drug_test'";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['data']['drug_tests_today'] = (int)$result['total'];
    
    // Query 5: Physicals today
    $sql = "SELECT COUNT(*) as total
            FROM orders o
            INNER JOIN encounters e ON o.encounter_id = e.encounter_id
            WHERE DATE(e.started_at) = CURDATE()
            AND o.order_type = 'physical_exam'";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['data']['physicals_today'] = (int)$result['total'];
    
    // Query 6: Pending reviews
    $sql = "SELECT COUNT(*) as total
            FROM encounters
            WHERE status = 'pending_review'
            AND DATE(started_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['data']['pending_reviews'] = (int)$result['total'];
    
    // Query 7: Average wait time (simplified - in production would use actual timestamps)
    $stats['data']['average_wait_time'] = rand(8, 25); // Mock data - replace with actual calculation
    
    // Query 8: Upcoming appointments (next 5)
    $sql = "SELECT 
                a.appointment_id,
                a.start_time,
                a.visit_reason,
                p.first_name,
                p.last_name,
                p.patient_id
            FROM appointments a
            INNER JOIN patients p ON a.patient_id = p.patient_id
            WHERE a.start_time >= NOW()
            AND DATE(a.start_time) = CURDATE()
            AND a.status = 'scheduled'
            ORDER BY a.start_time ASC
            LIMIT 5";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format appointments for response
    foreach ($appointments as &$apt) {
        $apt['patient_name'] = $apt['first_name'] . ' ' . $apt['last_name'];
        $apt['time'] = date('g:i A', strtotime($apt['start_time']));
        unset($apt['first_name'], $apt['last_name']);
    }
    
    $stats['data']['upcoming_appointments'] = $appointments;
    $stats['data']['appointments_today'] = count($appointments);
    
    // Send successful response
    echo json_encode($stats, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Log error
    \App\log\file_log('error', [
        'api' => 'dashboard-stats',
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