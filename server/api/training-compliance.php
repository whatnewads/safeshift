<?php
/**
 * Training Compliance API
 * Feature 2.2: Training Compliance Dashboard
 * 
 * Endpoints:
 * - GET /api/v1/training/dashboard - Get compliance stats
 * - GET /api/v1/training/staff/{user_id} - Get trainings for specific user
 * - POST /api/v1/training/records - Record training completion
 * - PUT /api/v1/training/records/{record_id} - Update training record
 * - POST /api/v1/training/reminders/bulk - Send bulk reminders
 * - GET /api/v1/training/export?format={csv|pdf} - Export compliance report
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../core/Services/TrainingComplianceService.php';

use Core\Services\TrainingComplianceService;

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

// Check if user has manager/admin role
$allowed_roles = ['tadmin', 'cadmin', 'pclinician'];
$is_manager = in_array($user['role'], $allowed_roles);

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));

try {
    $db = \App\db\pdo();
    $compliance_service = new TrainingComplianceService($db);
    
    switch ($method) {
        case 'GET':
            if (end($path_parts) === 'dashboard') {
                // Get compliance dashboard stats
                handleGetDashboard($compliance_service, $is_manager);
                
            } elseif (end($path_parts) === 'export') {
                // Export compliance report
                handleExportReport($compliance_service, $is_manager);
                
            } elseif (count($path_parts) >= 4 && $path_parts[count($path_parts) - 2] === 'staff') {
                // Get trainings for specific user
                $target_user_id = end($path_parts);
                handleGetUserTrainings($compliance_service, $user, $target_user_id, $is_manager);
                
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Invalid endpoint']);
            }
            break;
            
        case 'POST':
            if (end($path_parts) === 'records') {
                // Record training completion
                handleRecordTraining($compliance_service, $is_manager);
                
            } elseif (end($path_parts) === 'bulk' && $path_parts[count($path_parts) - 2] === 'reminders') {
                // Send bulk reminders
                handleSendReminders($compliance_service, $is_manager);
                
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Invalid endpoint']);
            }
            break;
            
        case 'PUT':
            // Update training record
            if (count($path_parts) >= 4 && $path_parts[count($path_parts) - 2] === 'records') {
                $record_id = end($path_parts);
                handleUpdateRecord($db, $record_id, $is_manager);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Invalid endpoint']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    \App\log\file_log('error', [
        'message' => 'Training compliance API error',
        'error' => $e->getMessage()
    ]);
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Handle GET /api/v1/training/dashboard
 */
function handleGetDashboard($compliance_service, $is_manager)
{
    if (!$is_manager) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    $filters = [];
    if (isset($_GET['department'])) {
        $filters['department'] = $_GET['department'];
    }
    if (isset($_GET['role'])) {
        $filters['role'] = $_GET['role'];
    }
    
    $stats = $compliance_service->getComplianceStats($filters);
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
}

/**
 * Handle GET /api/v1/training/staff/{user_id}
 */
function handleGetUserTrainings($compliance_service, $current_user, $target_user_id, $is_manager)
{
    // Users can view their own trainings, managers can view anyone's
    if (!$is_manager && $current_user['user_id'] !== $target_user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    $records = $compliance_service->getUserTrainingRecords($target_user_id);
    
    echo json_encode([
        'success' => true,
        'data' => $records
    ]);
}

/**
 * Handle POST /api/v1/training/records
 */
function handleRecordTraining($compliance_service, $is_manager)
{
    if (!$is_manager) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required_fields = ['user_id', 'requirement_id', 'completion_date', 'completed_by'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "$field is required"]);
            return;
        }
    }
    
    // Handle file upload if present
    if (!empty($_FILES['proof_document'])) {
        $upload_result = handleFileUpload($_FILES['proof_document']);
        if ($upload_result['success']) {
            $data['proof_document_path'] = $upload_result['path'];
        }
    }
    
    // Get recurrence interval from requirement
    global $db;
    $stmt = $db->prepare("SELECT recurrence_interval FROM training_requirements WHERE requirement_id = ?");
    $stmt->execute([$data['requirement_id']]);
    $requirement = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($requirement) {
        $data['recurrence_interval'] = $requirement['recurrence_interval'];
    }
    
    $record_id = $compliance_service->recordTrainingCompletion($data);
    
    if ($record_id) {
        echo json_encode([
            'success' => true,
            'record_id' => $record_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to record training']);
    }
}

/**
 * Handle PUT /api/v1/training/records/{record_id}
 */
function handleUpdateRecord($db, $record_id, $is_manager)
{
    if (!$is_manager) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $allowed_fields = ['completion_date', 'expiration_date', 'certification_number'];
    $update_fields = [];
    $params = [];
    
    foreach ($allowed_fields as $field) {
        if (isset($data[$field])) {
            $update_fields[] = "$field = :$field";
            $params[$field] = $data[$field];
        }
    }
    
    if (empty($update_fields)) {
        http_response_code(400);
        echo json_encode(['error' => 'No valid fields to update']);
        return;
    }
    
    $update_fields[] = "updated_at = NOW()";
    $params['record_id'] = $record_id;
    
    $stmt = $db->prepare("
        UPDATE staff_training_records 
        SET " . implode(', ', $update_fields) . "
        WHERE record_id = :record_id
    ");
    
    $result = $stmt->execute($params);
    
    if ($result && $stmt->rowCount() > 0) {
        // Log action
        \App\log\file_log('audit', [
            'action' => 'training_record_updated',
            'record_id' => $record_id,
            'updated_fields' => array_keys($data)
        ]);
        
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Record not found']);
    }
}

/**
 * Handle POST /api/v1/training/reminders/bulk
 */
function handleSendReminders($compliance_service, $is_manager)
{
    if (!$is_manager) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    $summary = $compliance_service->sendTrainingReminders();
    
    echo json_encode([
        'success' => true,
        'summary' => $summary
    ]);
}

/**
 * Handle GET /api/v1/training/export
 */
function handleExportReport($compliance_service, $is_manager)
{
    if (!$is_manager) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    $format = $_GET['format'] ?? 'csv';
    $filters = [];
    
    if (!in_array($format, ['csv', 'pdf'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid format. Use csv or pdf']);
        return;
    }
    
    $report = $compliance_service->generateComplianceReport($filters, $format);
    
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="training_compliance_' . date('Y-m-d') . '.csv"');
        echo $report;
    } elseif ($format === 'pdf') {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="training_compliance_' . date('Y-m-d') . '.pdf"');
        echo $report;
    }
}

/**
 * Handle file upload
 */
function handleFileUpload($file)
{
    $upload_dir = __DIR__ . '/../uploads/training_certificates/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Validate file type
    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }
    
    // Validate file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        return ['success' => false, 'error' => 'File too large'];
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('cert_') . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true,
            'path' => '/uploads/training_certificates/' . $filename
        ];
    }
    
    return ['success' => false, 'error' => 'Upload failed'];
}