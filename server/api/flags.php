<?php
/**
 * Flag Management API
 * Feature 2.1: High-Risk Call Flagging
 * 
 * Endpoints:
 * - GET /api/v1/flags - List flagged encounters
 * - POST /api/v1/flags - Manually create flag
 * - PUT /api/v1/flags/{flag_id}/assign - Assign flag to reviewer
 * - PUT /api/v1/flags/{flag_id}/resolve - Mark flag as resolved
 * - GET /api/v1/flags/stats - Dashboard stats
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/Services/FlagEngine.php';

use Core\Services\FlagEngine;

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Check authentication
$user = require __DIR__ . '/middleware/auth.php';
if (!$user) {
    exit;
}

// Check if user has manager/supervisor role
$allowed_roles = ['tadmin', 'cadmin', 'pclinician'];
if (!in_array($user['role'], $allowed_roles)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Manager role required.']);
    exit;
}

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));

try {
    $db = \App\db\pdo();
    $flag_engine = new FlagEngine($db);
    
    switch ($method) {
        case 'GET':
            if (end($path_parts) === 'stats') {
                // Get flag statistics
                handleGetStats($flag_engine);
            } else {
                // List flagged encounters
                handleListFlags($db);
            }
            break;
            
        case 'POST':
            // Manually create flag
            handleCreateFlag($db, $flag_engine, $user);
            break;
            
        case 'PUT':
            // Extract flag_id and action from path
            if (count($path_parts) >= 4) {
                $flag_id = $path_parts[count($path_parts) - 2];
                $action = end($path_parts);
                
                switch ($action) {
                    case 'assign':
                        handleAssignFlag($db, $flag_id, $user);
                        break;
                        
                    case 'resolve':
                        handleResolveFlag($db, $flag_id, $user);
                        break;
                        
                    default:
                        http_response_code(404);
                        echo json_encode(['error' => 'Invalid action']);
                }
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    \App\log\file_log('error', [
        'message' => 'Flag API error',
        'error' => $e->getMessage()
    ]);
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Handle GET /api/v1/flags - List flagged encounters
 */
function handleListFlags($db)
{
    $status = $_GET['status'] ?? null;
    $severity = $_GET['severity'] ?? null;
    $provider_id = $_GET['provider_id'] ?? null;
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;
    
    $conditions = [];
    $params = [];
    
    if ($status) {
        $conditions[] = "f.status = :status";
        $params['status'] = $status;
    }
    
    if ($severity) {
        $conditions[] = "f.severity = :severity";
        $params['severity'] = $severity;
    }
    
    if ($provider_id) {
        $conditions[] = "e.provider_id = :provider_id";
        $params['provider_id'] = $provider_id;
    }
    
    $where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
    
    // Get total count
    $count_stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM encounter_flags f
        JOIN encounters e ON f.encounter_id = e.encounter_id
        $where_clause
    ");
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetchColumn();
    
    // Get flagged encounters
    $query = "
        SELECT f.flag_id, f.encounter_id, f.flag_type, f.severity, 
               f.flag_reason, f.auto_flagged, f.status, f.due_date,
               f.created_at, f.assigned_to, f.resolved_at, f.resolution_notes,
               e.patient_id, e.created_at as encounter_date,
               e.chief_complaint,
               p.legal_first_name as first_name, p.legal_last_name as last_name, p.patient_id,
               a.username as assigned_to_name
        FROM encounter_flags f
        JOIN encounters e ON f.encounter_id = e.encounter_id
        JOIN patients p ON e.patient_id = p.patient_id
        LEFT JOIN user a ON f.assigned_to = a.user_id
        $where_clause
        ORDER BY 
            FIELD(f.severity, 'critical', 'high', 'medium', 'low'),
            f.created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $flags = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if overdue
    foreach ($flags as &$flag) {
        $flag['is_overdue'] = ($flag['status'] === 'pending' && 
                               strtotime($flag['due_date']) < time());
    }
    
    $response = [
        'success' => true,
        'data' => $flags,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total_count,
            'pages' => ceil($total_count / $limit)
        ]
    ];
    
    echo json_encode($response);
}

/**
 * Handle POST /api/v1/flags - Manually create flag
 */
function handleCreateFlag($db, $flag_engine, $user)
{
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['encounter_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'encounter_id is required']);
        return;
    }
    
    $flag_data = [
        'flag_type' => $data['flag_type'] ?? 'manual_review',
        'severity' => $data['severity'] ?? 'medium',
        'flag_reason' => $data['flag_reason'] ?? 'Manual review requested',
        'assigned_to' => $data['assigned_to'] ?? null
    ];
    
    $flag_id = $flag_engine->createManualFlag(
        $data['encounter_id'],
        $flag_data,
        $user['user_id']
    );
    
    if ($flag_id) {
        // Log action
        \App\log\file_log('audit', [
            'action' => 'manual_flag_created',
            'user_id' => $user['user_id'],
            'flag_id' => $flag_id,
            'encounter_id' => $data['encounter_id']
        ]);
        
        echo json_encode([
            'success' => true,
            'flag_id' => $flag_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create flag']);
    }
}

/**
 * Handle PUT /api/v1/flags/{flag_id}/assign - Assign flag to reviewer
 */
function handleAssignFlag($db, $flag_id, $user)
{
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['assigned_to'])) {
        http_response_code(400);
        echo json_encode(['error' => 'assigned_to is required']);
        return;
    }
    
    $stmt = $db->prepare("
        UPDATE encounter_flags 
        SET assigned_to = :assigned_to,
            status = 'under_review',
            updated_at = NOW()
        WHERE flag_id = :flag_id
        AND status = 'pending'
    ");
    
    $result = $stmt->execute([
        'assigned_to' => $data['assigned_to'],
        'flag_id' => $flag_id
    ]);
    
    if ($result && $stmt->rowCount() > 0) {
        // Log action
        \App\log\file_log('audit', [
            'action' => 'flag_assigned',
            'user_id' => $user['user_id'],
            'flag_id' => $flag_id,
            'assigned_to' => $data['assigned_to']
        ]);
        
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Flag not found or already assigned']);
    }
}

/**
 * Handle PUT /api/v1/flags/{flag_id}/resolve - Mark flag as resolved
 */
function handleResolveFlag($db, $flag_id, $user)
{
    $data = json_decode(file_get_contents('php://input'), true);
    
    $resolution_notes = $data['resolution_notes'] ?? '';
    $action_taken = $data['action_taken'] ?? 'resolved';
    
    $stmt = $db->prepare("
        UPDATE encounter_flags 
        SET status = :status,
            resolved_at = NOW(),
            resolved_by = :resolved_by,
            resolution_notes = :resolution_notes,
            updated_at = NOW()
        WHERE flag_id = :flag_id
        AND status IN ('pending', 'under_review')
    ");
    
    $result = $stmt->execute([
        'status' => ($action_taken === 'escalated' ? 'escalated' : 'resolved'),
        'resolved_by' => $user['user_id'],
        'resolution_notes' => $resolution_notes,
        'flag_id' => $flag_id
    ]);
    
    if ($result && $stmt->rowCount() > 0) {
        // Log action
        \App\log\file_log('audit', [
            'action' => 'flag_resolved',
            'user_id' => $user['user_id'],
            'flag_id' => $flag_id,
            'resolution' => $action_taken
        ]);
        
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Flag not found or already resolved']);
    }
}

/**
 * Handle GET /api/v1/flags/stats - Dashboard stats
 */
function handleGetStats($flag_engine)
{
    $stats = $flag_engine->getFlagStatistics();
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
}