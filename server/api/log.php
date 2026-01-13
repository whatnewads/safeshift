<?php
/**
 * Logging API Endpoint
 * 
 * This endpoint receives log messages from application components
 * and forwards them to the secure logger service.
 * 
 * Only accepts POST requests with JSON payload
 */

require_once '../includes/bootstrap.php';
require_once '../includes/secure_logger.php';

// Set JSON response header
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!$input || !is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

// Required fields
$requiredFields = ['level', 'category', 'message'];
foreach ($requiredFields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

// Optional context
$context = $input['context'] ?? [];

// Additional security: verify API token if configured
if (defined('LOG_API_TOKEN') && LOG_API_TOKEN) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!$authHeader || $authHeader !== 'Bearer ' . LOG_API_TOKEN) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

try {
    // Get logger instance
    $logger = SecureLogger::getInstance();
    
    // Log the message
    $result = $logger->log(
        $input['level'],
        $input['category'],
        $input['message'],
        $context
    );
    
    if ($result) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Log entry created',
            'request_id' => uniqid('log_', true)
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to create log entry'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}