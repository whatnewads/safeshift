<?php
/**
 * Log Files API - List available log files for integrity checking
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

try {
    $logDir = 'C:\\ehr_logs\\';
    $files = [];
    
    // Get all log files
    $logFiles = glob($logDir . '*.log');
    
    foreach ($logFiles as $file) {
        $files[] = [
            'name' => basename($file),
            'size' => filesize($file),
            'modified' => date('Y-m-d H:i:s', filemtime($file)),
            'category' => strtoupper(explode('_', basename($file))[0]),
            'date' => substr(basename($file, '.log'), -10) // Extract date from filename
        ];
    }
    
    // Sort by modified date descending
    usort($files, function($a, $b) {
        return strtotime($b['modified']) - strtotime($a['modified']);
    });
    
    echo json_encode([
        'success' => true,
        'files' => $files
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to retrieve log files'
    ]);
}