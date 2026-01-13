<?php
/**
 * SafeShift EHR Health Check
 * Returns JSON status for monitoring systems
 */

header('Content-Type: application/json');

$status = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'checks' => []
];

$allHealthy = true;

// Check database connection
try {
    require_once __DIR__ . '/../includes/db.php';
    $pdo = getDbConnection();
    $pdo->query('SELECT 1');
    $status['checks']['database'] = ['status' => 'ok'];
} catch (Exception $e) {
    $status['checks']['database'] = ['status' => 'error', 'message' => 'Connection failed'];
    $allHealthy = false;
}

// Check session directory
$sessionPath = session_save_path() ?: sys_get_temp_dir();
if (is_writable($sessionPath)) {
    $status['checks']['session'] = ['status' => 'ok'];
} else {
    $status['checks']['session'] = ['status' => 'error', 'message' => 'Session directory not writable'];
    $allHealthy = false;
}

// Check logs directory
$logsDir = __DIR__ . '/../logs';
if (is_writable($logsDir)) {
    $status['checks']['logs'] = ['status' => 'ok'];
} else {
    $status['checks']['logs'] = ['status' => 'error', 'message' => 'Logs directory not writable'];
    $allHealthy = false;
}

// Check disk space
$freeSpace = disk_free_space('/');
$totalSpace = disk_total_space('/');
$usedPercent = round((1 - $freeSpace / $totalSpace) * 100);
if ($usedPercent < 90) {
    $status['checks']['disk'] = ['status' => 'ok', 'used_percent' => $usedPercent];
} else {
    $status['checks']['disk'] = ['status' => 'warning', 'used_percent' => $usedPercent];
}

// Set overall status
if (!$allHealthy) {
    $status['status'] = 'unhealthy';
    http_response_code(503);
}

echo json_encode($status, JSON_PRETTY_PRINT);
