<?php
/**
 * Log Details API - Retrieve detailed information for a specific log entry
 */

require_once '../includes/bootstrap.php';
require_once '../includes/auth.php';

// Check authentication and authorization
if (!isAuthenticated() || !hasRole('tadmin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$logId = $_GET['id'] ?? '';

if (empty($logId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Log ID required']);
    exit;
}

try {
    $logDir = 'C:\\ehr_logs\\';
    $found = false;
    $logEntry = null;
    
    // Search through all log files (inefficient but necessary without a database)
    $files = glob($logDir . '*.log');
    
    foreach ($files as $file) {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            if (md5($line) === $logId) {
                $logEntry = json_decode($line, true);
                $found = true;
                break 2;
            }
        }
    }
    
    if ($found && $logEntry) {
        // Add the ID back
        $logEntry['id'] = $logId;
        
        echo json_encode([
            'success' => true,
            'log' => $logEntry
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Log entry not found'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to retrieve log details'
    ]);
}