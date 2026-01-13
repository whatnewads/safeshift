<?php
// ========================================================================
// IMMEDIATE LOGGING - FIRST THING BEFORE ANYTHING ELSE
// ========================================================================
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/api_debug.log';
$timestamp = date('Y-m-d H:i:s');
$logEntry = "[{$timestamp}] API/INDEX.PHP HIT\n";
$logEntry .= "  REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? '(not set)') . "\n";
$logEntry .= "  REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? '(not set)') . "\n";
$logEntry .= "  SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? '(not set)') . "\n";
$logEntry .= "  PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? '(not set)') . "\n";
$logEntry .= "  __FILE__: " . __FILE__ . "\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);

/**
 * Centralized API Router
 *
 * Routes all API requests to appropriate ViewModels
 * Handles authentication, CSRF validation, and error handling
 *
 * v1 API: Routes /api/v1/* to the new versioned API router
 * Legacy API: Routes other /api/* requests to legacy handlers
 */

// Load bootstrap which handles all includes
require_once __DIR__ . '/../includes/bootstrap.php';

// ========================================================================
// V1 API ROUTING - Route to new versioned API before legacy processing
// ========================================================================

$requestPath = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');

// Check if this is a v1 API request
if (strpos($requestPath, 'api/v1') === 0 || strpos($requestPath, 'api/v1/') === 0) {
    // Route to the v1 API router
    require_once __DIR__ . '/v1/index.php';
    exit;
}

// ========================================================================
// LEGACY API ROUTING - Everything below is for legacy /api/* endpoints
// ========================================================================

require_once __DIR__ . '/middleware/rate-limit.php';

// Set JSON headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// CORS handling for legacy API
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
                 . "://" . $_SERVER['HTTP_HOST'];

// Also allow React dev server origins for legacy endpoints
$allowedDevOrigins = [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'http://localhost:3000',
    'http://127.0.0.1:3000'
];

if ($origin === $allowedOrigin || in_array($origin, $allowedDevOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-XSRF-Token");
}

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Session validation
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'timestamp' => date('c')]);
    exit;
}

$userId = $_SESSION['user']['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$pathSegments = explode('/', $path);

// Remove 'api' from path segments
if ($pathSegments[0] === 'api') {
    array_shift($pathSegments);
}

$endpoint = $pathSegments[0] ?? '';

// Apply rate limiting
$rateLimiter = \Api\Middleware\RateLimit::forEndpoint($endpoint);
$rateResult = $rateLimiter->checkLimit('api_' . $endpoint . '_' . $userId);
$rateLimiter->applyHeaders($rateResult);

if (!$rateResult['allowed']) {
    http_response_code(429);
    echo json_encode([
        'error' => 'Too many requests',
        'retry_after' => $rateResult['retry_after'],
        'timestamp' => date('c')
    ]);
    exit;
}

// CSRF validation for state-changing methods
if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (!$csrfToken || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF validation failed', 'timestamp' => date('c')]);
        exit;
    }
}

// Sanitize input
$input = [];
switch ($method) {
    case 'GET':
        $input = filter_input_array(INPUT_GET, FILTER_SANITIZE_SPECIAL_CHARS) ?: [];
        break;
    case 'POST':
    case 'PUT':
    case 'DELETE':
    case 'PATCH':
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
        } else {
            $input = $_POST;
        }
        break;
}

// Add user ID to input for convenience
$input['user_id'] = $userId;

try {
    // Log API access
    \App\log\audit('API_ACCESS', $endpoint, $userId, [
        'method' => $method,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    // Get PDO instance
    $pdo = \App\db\pdo();
    
    // Route to appropriate ViewModel
    switch ($endpoint) {
        case 'dashboard-stats':
            // Create dependencies
            $encounterRepo = new \Core\Repositories\EncounterRepository($pdo);
            $orderRepo = null; // OrderRepository would need to be implemented
            $dotTestRepo = null; // DotTestRepository would need to be implemented
            $statsService = new \Core\Services\DashboardStatsService($encounterRepo, $orderRepo, $dotTestRepo);
            $viewModel = new \ViewModel\DashboardStatsViewModel($statsService);
            
            // Call appropriate method
            $response = $viewModel->getStats($input);
            echo json_encode($response);
            break;
            
        case 'recent-patients':
            // Create dependencies
            $patientAccessService = new \Core\Services\PatientAccessService($pdo);
            $viewModel = new \ViewModel\RecentPatientsViewModel($patientAccessService);
            
            // Call appropriate method
            $response = $viewModel->getRecentPatients($userId, $input);
            echo json_encode($response);
            break;
            
        case 'patient-vitals':
            // Create dependencies
            $observationRepo = new \Core\Repositories\ObservationRepository($pdo);
            $patientRepo = new \Core\Repositories\PatientRepository($pdo);
            $validator = new \Core\Validators\VitalRangeValidator();
            $vitalsService = new \Core\Services\PatientVitalsService($observationRepo, $patientRepo, $validator);
            $viewModel = new \ViewModel\PatientVitalsViewModel($vitalsService);
            
            // Call appropriate method based on sub-endpoint
            $subEndpoint = $pathSegments[1] ?? '';
            switch ($subEndpoint) {
                case 'latest':
                    $response = $viewModel->getLatestVitals($input);
                    break;
                case 'abnormal':
                    $response = $viewModel->getAbnormalVitals($input);
                    break;
                case 'trend':
                    $response = $viewModel->getVitalTrend($input);
                    break;
                default:
                    $response = $viewModel->getVitalsData($input);
            }
            echo json_encode($response);
            break;
            
        case 'notifications':
            // Create dependencies
            $notificationRepo = new \Core\Repositories\NotificationRepository($pdo);
            $notificationService = new \Core\Services\NotificationService($notificationRepo);
            $viewModel = new \ViewModel\NotificationsViewModel($notificationService);
            
            // Handle different methods and sub-endpoints
            $subEndpoint = $pathSegments[1] ?? '';
            if ($method === 'GET') {
                switch ($subEndpoint) {
                    case 'by-type':
                        $response = $viewModel->getByType($userId, $input);
                        break;
                    case 'has-unread':
                        $response = $viewModel->hasUnread($userId);
                        break;
                    default:
                        $response = $viewModel->getNotifications($userId, $input);
                }
            } elseif ($method === 'POST') {
                switch ($subEndpoint) {
                    case 'mark-read':
                        $response = $viewModel->markAsRead($userId, $input);
                        break;
                    case 'mark-all-read':
                        $response = $viewModel->markAllAsRead($userId);
                        break;
                    case 'create':
                        $response = $viewModel->createNotification($input);
                        break;
                    default:
                        http_response_code(404);
                        $response = ['error' => 'Invalid notification endpoint'];
                }
            }
            echo json_encode($response);
            break;
            
        case 'ems':
            // Handle EMS sub-endpoints
            $subEndpoint = $pathSegments[1] ?? '';
            
            // Create EPCR dependencies
            $epcrRepo = new \Core\Repositories\EPCRRepository($pdo);
            $patientRepo = new \Core\Repositories\PatientRepository($pdo);
            $encounterRepo = new \Core\Repositories\EncounterRepository($pdo);
            $observationRepo = new \Core\Repositories\ObservationRepository($pdo);
            $epcrValidator = new \Core\Validators\EPCRValidator();
            $epcrService = new \Core\Services\EPCRService(
                $epcrRepo, 
                $patientRepo, 
                $encounterRepo, 
                $observationRepo, 
                $epcrValidator, 
                $pdo
            );
            $viewModel = new \ViewModel\EPCRViewModel($epcrService);
            
            switch ($subEndpoint) {
                case 'save-epcr':
                    if ($method !== 'POST') {
                        http_response_code(405);
                        echo json_encode(['error' => 'Method not allowed']);
                        break;
                    }
                    $response = $viewModel->saveEPCR($input);
                    echo json_encode($response);
                    break;
                    
                case 'submit-epcr':
                    if ($method !== 'POST') {
                        http_response_code(405);
                        echo json_encode(['error' => 'Method not allowed']);
                        break;
                    }
                    $response = $viewModel->submitEPCR($input);
                    echo json_encode($response);
                    break;
                    
                case 'validate-epcr':
                    if ($method !== 'POST') {
                        http_response_code(405);
                        echo json_encode(['error' => 'Method not allowed']);
                        break;
                    }
                    $response = $viewModel->validateEPCR($input);
                    echo json_encode($response);
                    break;
                    
                case 'get-epcr':
                    if ($method !== 'GET') {
                        http_response_code(405);
                        echo json_encode(['error' => 'Method not allowed']);
                        break;
                    }
                    $response = $viewModel->getEPCR($input);
                    echo json_encode($response);
                    break;
                    
                case 'incomplete-epcrs':
                    if ($method !== 'GET') {
                        http_response_code(405);
                        echo json_encode(['error' => 'Method not allowed']);
                        break;
                    }
                    $response = $viewModel->getIncompleteEPCRs($input);
                    echo json_encode($response);
                    break;
                    
                case 'lock-epcr':
                    if ($method !== 'POST') {
                        http_response_code(405);
                        echo json_encode(['error' => 'Method not allowed']);
                        break;
                    }
                    $response = $viewModel->lockEPCR($input);
                    echo json_encode($response);
                    break;
                    
                default:
                    http_response_code(404);
                    echo json_encode(['error' => 'Invalid EMS endpoint']);
            }
            break;
            
        // Additional endpoints can be added here
        
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found', 'timestamp' => date('c')]);
    }
    
} catch (\Exception $e) {
    error_log("API Error: " . $e->getMessage());
    error_log($e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'timestamp' => date('c')
    ]);
}