<?php
/**
 * Chart Templates API Endpoint
 * Handles CRUD operations for chart templates
 * 
 * Feature 1.2: Smart Template Loader
 * 
 * Endpoints:
 * GET    /api/v1/templates - List templates
 * POST   /api/v1/templates - Create template
 * GET    /api/v1/templates/{id} - Get specific template
 * PUT    /api/v1/templates/{id} - Update template
 * DELETE /api/v1/templates/{id} - Archive template
 * POST   /api/v1/templates/{id}/duplicate - Duplicate template
 * POST   /api/v1/templates/{id}/load - Load template for encounter
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
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token");
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
        'api' => 'templates',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user']['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Parse template ID from URL if present
$request_uri = $_SERVER['REQUEST_URI'];
$path_parts = explode('/', trim(parse_url($request_uri, PHP_URL_PATH), '/'));
$template_id = null;
$action = null;

// Check for template ID and action in URL
if (count($path_parts) >= 3 && $path_parts[1] === 'templates') {
    $template_id = $path_parts[2] ?? null;
    $action = $path_parts[3] ?? null;
}

// CSRF validation for non-GET requests
if ($method !== 'GET') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';
    
    if (!$csrf_token || $csrf_token !== ($_SESSION['csrf_token'] ?? '')) {
        \App\log\security_event('CSRF_VALIDATION_FAILED', [
            'api' => 'templates',
            'user_id' => $user_id
        ]);
        http_response_code(403);
        echo json_encode(['error' => 'CSRF validation failed']);
        exit;
    }
}

try {
    $db = \App\db\pdo();
    $templateService = new \Core\Services\TemplateService($db);
    
    switch ($method) {
        case 'GET':
            if ($template_id) {
                // Get specific template
                $template = $templateService->getTemplate($template_id, $user_id);
                if ($template) {
                    echo json_encode([
                        'success' => true,
                        'data' => $template
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Template not found']);
                }
            } else {
                // List templates
                $filters = [
                    'encounter_type' => $_GET['encounter_type'] ?? null,
                    'search' => $_GET['search'] ?? null
                ];
                
                $templates = $templateService->getUserTemplates($user_id, $filters);
                echo json_encode([
                    'success' => true,
                    'data' => $templates,
                    'count' => count($templates)
                ]);
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if ($template_id) {
                if ($action === 'duplicate') {
                    // Duplicate template
                    if (empty($input['new_name'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'new_name is required']);
                        exit;
                    }
                    
                    $new_template_id = $templateService->duplicateTemplate(
                        $template_id, 
                        $user_id, 
                        $input['new_name']
                    );
                    
                    echo json_encode([
                        'success' => true,
                        'template_id' => $new_template_id,
                        'message' => 'Template duplicated successfully'
                    ]);
                    
                } elseif ($action === 'load') {
                    // Load template for encounter
                    $template_data = $templateService->loadTemplateForEncounter($template_id, $user_id);
                    echo json_encode([
                        'success' => true,
                        'data' => $template_data
                    ]);
                    
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Unknown action']);
                }
            } else {
                // Create new template
                if (empty($input['template_name']) || empty($input['template_data'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'template_name and template_data are required']);
                    exit;
                }
                
                $input['created_by'] = $user_id;
                $template_id = $templateService->createTemplate($input);
                
                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'template_id' => $template_id,
                    'message' => 'Template created successfully'
                ]);
            }
            break;
            
        case 'PUT':
            if (!$template_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Template ID required']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $success = $templateService->updateTemplate($template_id, $input, $user_id);
            
            if ($success) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Template updated successfully'
                ]);
            } else {
                http_response_code(403);
                echo json_encode(['error' => 'Failed to update template']);
            }
            break;
            
        case 'DELETE':
            if (!$template_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Template ID required']);
                exit;
            }
            
            $success = $templateService->archiveTemplate($template_id, $user_id);
            
            if ($success) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Template archived successfully'
                ]);
            } else {
                http_response_code(403);
                echo json_encode(['error' => 'Failed to archive template']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    \App\log\file_log('error', [
        'api' => 'templates',
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