<?php
/**
 * Log Statistics API - Provides dashboard statistics
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
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    $stats = [
        'todayCount' => 0,
        'errorCount' => 0,
        'activeUsers' => [],
        'oshaEvents' => 0
    ];
    
    // Process today's logs
    $categories = ['AUTH', 'ACCESS', 'FORM', 'ERROR', 'SYSTEM', 'OSHA', 'HIPAA'];
    
    foreach ($categories as $category) {
        // Today's file
        $todayFile = $logDir . strtolower($category) . '_' . $today . '.log';
        if (file_exists($todayFile)) {
            $lines = file($todayFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $log = json_decode($line, true);
                if ($log) {
                    $stats['todayCount']++;
                    
                    // Count OSHA events
                    if ($category === 'OSHA') {
                        $stats['oshaEvents']++;
                    }
                    
                    // Track active users
                    if (!empty($log['user_id'])) {
                        $stats['activeUsers'][$log['user_id']] = true;
                    }
                }
            }
        }
        
        // Yesterday's file for 24h error count
        $yesterdayFile = $logDir . strtolower($category) . '_' . $yesterday . '.log';
        if (file_exists($yesterdayFile)) {
            $lines = file($yesterdayFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $log = json_decode($line, true);
                if ($log && in_array($log['level'], ['ERROR', 'CRITICAL'])) {
                    $stats['errorCount']++;
                }
            }
        }
    }
    
    // Count unique active users
    $stats['activeUsers'] = count($stats['activeUsers']);
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to retrieve statistics'
    ]);
}