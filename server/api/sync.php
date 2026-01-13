<?php
/**
 * Sync API Endpoint
 * Handles offline sync, conflict resolution, and beacon requests
 * 
 * Feature 1.4: Offline Mode (Mobile Optimized)
 * 
 * Endpoints:
 * POST   /api/v1/sync/queue - Process sync queue items
 * POST   /api/v1/sync/resolve-conflict - Resolve sync conflicts
 * POST   /api/v1/sync/beacon - Quick sync for page unload
 * GET    /api/v1/sync/status - Get sync status
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Set headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// CORS handling
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origin = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
if ($origin === $allowed_origin) {
    header("Access-Control-Allow-Origin: $allowed_origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Sync-Request, X-Device-ID");
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Session validation
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['user_id'])) {
    \App\log\security_event('UNAUTHORIZED_API_ACCESS', [
        'api' => 'sync',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user']['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Parse action from URL
$request_uri = $_SERVER['REQUEST_URI'];
$path_parts = explode('/', trim(parse_url($request_uri, PHP_URL_PATH), '/'));
$action = $path_parts[3] ?? null; // sync/{action}

// CSRF validation for POST requests
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';
    
    // Skip CSRF for beacon requests (they can't send headers)
    if ($action !== 'beacon' && (!$csrf_token || $csrf_token !== ($_SESSION['csrf_token'] ?? ''))) {
        \App\log\security_event('CSRF_VALIDATION_FAILED', [
            'api' => 'sync',
            'user_id' => $user_id
        ]);
        http_response_code(403);
        echo json_encode(['error' => 'CSRF validation failed']);
        exit;
    }
}

try {
    $db = \App\db\pdo();
    
    switch ($action) {
        case 'queue':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $results = processSyncQueue($db, $input['items'] ?? [], $user_id);
            
            echo json_encode([
                'success' => true,
                'results' => $results
            ]);
            break;
            
        case 'resolve-conflict':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $success = resolveConflict($db, $input['conflict_id'], $input['resolution'], $user_id);
            
            if ($success) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Conflict resolved successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to resolve conflict']);
            }
            break;
            
        case 'beacon':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            
            // Beacon API sends data as plain text
            $data = file_get_contents('php://input');
            $items = json_decode($data, true) ?: [];
            
            // Quick sync without full validation
            $count = quickSync($db, $items, $user_id);
            
            // Beacon doesn't expect response body
            http_response_code(204);
            exit;
            
        case 'status':
            if ($method !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            
            $status = getSyncStatus($db, $user_id);
            echo json_encode($status);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Unknown sync endpoint']);
    }
    
} catch (Exception $e) {
    \App\log\file_log('error', [
        'api' => 'sync',
        'action' => $action,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'user_id' => $user_id
    ]);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage() // Remove in production
    ]);
}

/**
 * Process sync queue items
 */
function processSyncQueue($db, $items, $user_id) {
    $results = [];
    
    foreach ($items as $item) {
        try {
            $result = processSyncItem($db, $item, $user_id);
            $results[] = $result;
        } catch (Exception $e) {
            $results[] = [
                'id' => $item['id'] ?? null,
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    return $results;
}

/**
 * Process individual sync item
 */
function processSyncItem($db, $item, $user_id) {
    $resource_type = $item['request_type'] ?? 'unknown';
    $method = $item['method'] ?? 'POST';
    $data = json_decode($item['body'], true);
    
    if (!$data) {
        throw new Exception('Invalid sync data');
    }
    
    // Check for existing resource to detect conflicts
    $existing = null;
    $resource_id = null;
    
    switch ($resource_type) {
        case 'encounter':
            $resource_id = $data['encounter_id'] ?? null;
            if ($resource_id && $method !== 'POST') {
                $stmt = $db->prepare("SELECT * FROM encounters WHERE encounter_id = ?");
                $stmt->execute([$resource_id]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            break;
            
        case 'patient':
            $resource_id = $data['patient_id'] ?? null;
            if ($resource_id && $method !== 'POST') {
                $stmt = $db->prepare("SELECT * FROM patients WHERE patient_id = ?");
                $stmt->execute([$resource_id]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            break;
    }
    
    // Check for conflict (if resource was modified since offline edit)
    if ($existing && isset($data['local_updated_at'])) {
        $local_time = strtotime($data['local_updated_at']);
        $server_time = strtotime($existing['updated_at'] ?? $existing['created_at']);
        
        if ($server_time > $local_time) {
            // Conflict detected
            $conflict_id = createConflict($db, $resource_type, $resource_id, $data, $existing, $user_id);
            
            return [
                'id' => $item['id'] ?? null,
                'success' => false,
                'conflict' => true,
                'conflict_id' => $conflict_id,
                'server_version' => $existing,
                'client_version' => $data
            ];
        }
    }
    
    // No conflict, proceed with sync
    $success = false;
    
    switch ($resource_type) {
        case 'encounter':
            $success = syncEncounter($db, $method, $data, $user_id);
            break;
            
        case 'patient':
            $success = syncPatient($db, $method, $data, $user_id);
            break;
            
        default:
            throw new Exception("Unknown resource type: $resource_type");
    }
    
    return [
        'id' => $item['id'] ?? null,
        'success' => $success,
        'resource_id' => $resource_id
    ];
}

/**
 * Sync encounter data
 */
function syncEncounter($db, $method, $data, $user_id) {
    // Remove offline-specific fields
    unset($data['synced']);
    unset($data['local_updated_at']);
    unset($data['device_id']);
    unset($data['is_new']);
    
    if ($method === 'POST' || !isset($data['encounter_id'])) {
        // Create new encounter
        $data['encounter_id'] = $data['encounter_id'] ?? generateUUID();
        $data['created_by'] = $user_id;
        $data['created_at'] = date('Y-m-d H:i:s');
        
        $fields = array_keys($data);
        $placeholders = array_map(fn($f) => ":$f", $fields);
        
        $sql = "INSERT INTO encounters (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $db->prepare($sql);
        return $stmt->execute($data);
    } else {
        // Update existing encounter
        $data['updated_at'] = date('Y-m-d H:i:s');
        $encounter_id = $data['encounter_id'];
        unset($data['encounter_id']);
        
        $sets = array_map(fn($f) => "$f = :$f", array_keys($data));
        
        $sql = "UPDATE encounters SET " . implode(', ', $sets) . " 
                WHERE encounter_id = :encounter_id";
        
        $data['encounter_id'] = $encounter_id;
        $stmt = $db->prepare($sql);
        return $stmt->execute($data);
    }
}

/**
 * Sync patient data
 */
function syncPatient($db, $method, $data, $user_id) {
    // Similar to syncEncounter but for patients table
    unset($data['synced']);
    unset($data['local_updated_at']);
    unset($data['device_id']);
    unset($data['is_new']);
    
    if ($method === 'POST' || !isset($data['patient_id'])) {
        $data['patient_id'] = $data['patient_id'] ?? generateUUID();
        $data['created_at'] = date('Y-m-d H:i:s');
        
        $fields = array_keys($data);
        $placeholders = array_map(fn($f) => ":$f", $fields);
        
        $sql = "INSERT INTO patients (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $db->prepare($sql);
        return $stmt->execute($data);
    } else {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $patient_id = $data['patient_id'];
        unset($data['patient_id']);
        
        $sets = array_map(fn($f) => "$f = :$f", array_keys($data));
        
        $sql = "UPDATE patients SET " . implode(', ', $sets) . " 
                WHERE patient_id = :patient_id";
        
        $data['patient_id'] = $patient_id;
        $stmt = $db->prepare($sql);
        return $stmt->execute($data);
    }
}

/**
 * Create conflict record
 */
function createConflict($db, $resource_type, $resource_id, $local_data, $server_data, $user_id) {
    $sql = "INSERT INTO offline_conflicts 
            (conflict_id, resource_type, resource_id, local_version, server_version, 
             detected_by, detected_at, status) 
            VALUES (UUID(), :resource_type, :resource_id, :local_version, :server_version,
                    :user_id, NOW(), 'pending')";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'resource_type' => $resource_type,
        'resource_id' => $resource_id,
        'local_version' => json_encode($local_data),
        'server_version' => json_encode($server_data),
        'user_id' => $user_id
    ]);
    
    // Get the created conflict ID
    $stmt = $db->query("SELECT LAST_INSERT_ID()");
    return $stmt->fetchColumn();
}

/**
 * Resolve conflict
 */
function resolveConflict($db, $conflict_id, $resolution, $user_id) {
    // Get conflict details
    $stmt = $db->prepare("SELECT * FROM offline_conflicts WHERE conflict_id = ?");
    $stmt->execute([$conflict_id]);
    $conflict = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conflict) {
        return false;
    }
    
    $success = false;
    
    switch ($resolution) {
        case 'use_server':
            // No action needed, server version is already in place
            $success = true;
            break;
            
        case 'use_client':
            // Apply client version
            $local_data = json_decode($conflict['local_version'], true);
            $resource_type = $conflict['resource_type'];
            
            if ($resource_type === 'encounter') {
                $success = syncEncounter($db, 'PUT', $local_data, $user_id);
            } elseif ($resource_type === 'patient') {
                $success = syncPatient($db, 'PUT', $local_data, $user_id);
            }
            break;
            
        case 'merge':
            // TODO: Implement merge logic based on resource type
            break;
    }
    
    if ($success) {
        // Mark conflict as resolved
        $stmt = $db->prepare("UPDATE offline_conflicts 
                              SET status = 'resolved', 
                                  resolution = :resolution,
                                  resolved_by = :user_id,
                                  resolved_at = NOW()
                              WHERE conflict_id = :conflict_id");
        
        $stmt->execute([
            'resolution' => $resolution,
            'user_id' => $user_id,
            'conflict_id' => $conflict_id
        ]);
    }
    
    return $success;
}

/**
 * Quick sync for beacon API
 */
function quickSync($db, $items, $user_id) {
    $count = 0;
    
    foreach ($items as $item) {
        try {
            $result = processSyncItem($db, $item, $user_id);
            if ($result['success']) {
                $count++;
            }
        } catch (Exception $e) {
            // Log but continue
            \App\log\file_log('error', [
                'api' => 'sync_beacon',
                'error' => $e->getMessage()
            ]);
        }
    }
    
    return $count;
}

/**
 * Get sync status
 */
function getSyncStatus($db, $user_id) {
    // Count pending conflicts
    $stmt = $db->prepare("SELECT COUNT(*) FROM offline_conflicts 
                          WHERE status = 'pending' AND detected_by = ?");
    $stmt->execute([$user_id]);
    $pending_conflicts = $stmt->fetchColumn();
    
    // Get last sync time from audit log
    $stmt = $db->prepare("SELECT MAX(timestamp) FROM audit_log 
                          WHERE user_id = ? AND action_type LIKE 'sync_%'");
    $stmt->execute([$user_id]);
    $last_sync = $stmt->fetchColumn();
    
    return [
        'success' => true,
        'pending_conflicts' => (int)$pending_conflicts,
        'last_sync' => $last_sync,
        'user_id' => $user_id
    ];
}

/**
 * Generate UUID
 */
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}