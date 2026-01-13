<?php
/**
 * Verify Log Integrity API - Check if log files have been tampered with
 */

require_once '../includes/bootstrap.php';
require_once '../includes/auth.php';
require_once '../includes/secure_logger.php';

// Check authentication and authorization
if (!isAuthenticated() || !hasRole('tadmin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$filename = $input['filename'] ?? '';

if (empty($filename)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Filename required']);
    exit;
}

// Sanitize filename to prevent directory traversal
$filename = basename($filename);

try {
    $logger = SecureLogger::getInstance();
    $isValid = $logger->verifyLogIntegrity($filename);
    
    // Log the integrity check
    logAudit('Log integrity check performed', [
        'filename' => $filename,
        'result' => $isValid ? 'valid' : 'invalid',
        'performed_by' => $_SESSION['user_id']
    ]);
    
    echo json_encode([
        'success' => true,
        'valid' => $isValid,
        'filename' => $filename,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to verify integrity'
    ]);
}