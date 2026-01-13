<?php
/**
 * Logs API - Retrieve logs with filtering and pagination
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
    // Get filters
    $level = $_GET['level'] ?? '';
    $category = $_GET['category'] ?? '';
    $dateFrom = $_GET['dateFrom'] ?? date('Y-m-d', strtotime('-7 days'));
    $dateTo = $_GET['dateTo'] ?? date('Y-m-d');
    $search = $_GET['search'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = 50;
    
    // Windows-compatible log directory
    $logDir = 'C:\\ehr_logs\\';
    
    // Get all log files in date range
    $startDate = new DateTime($dateFrom);
    $endDate = new DateTime($dateTo);
    $interval = DateInterval::createFromDateString('1 day');
    $period = new DatePeriod($startDate, $interval, $endDate->add($interval));
    
    $allLogs = [];
    
    foreach ($period as $date) {
        // Check logs for each category or all if not specified
        $categories = $category ? [$category] : ['AUTH', 'ACCESS', 'FORM', 'ERROR', 'SYSTEM', 'OSHA', 'HIPAA'];
        
        foreach ($categories as $cat) {
            $filename = strtolower($cat) . '_' . $date->format('Y-m-d') . '.log';
            $filepath = $logDir . $filename;
            
            if (file_exists($filepath)) {
                $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                
                foreach ($lines as $line) {
                    $log = json_decode($line, true);
                    
                    if (!$log) continue;
                    
                    // Apply filters
                    if ($level && $log['level'] !== $level) continue;
                    if ($search && stripos(json_encode($log), $search) === false) continue;
                    
                    // Add ID for reference
                    $log['id'] = md5($line);
                    $allLogs[] = $log;
                }
            }
        }
    }
    
    // Sort by timestamp descending
    usort($allLogs, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    // Pagination
    $total = count($allLogs);
    $totalPages = ceil($total / $perPage);
    $offset = ($page - 1) * $perPage;
    $logs = array_slice($allLogs, $offset, $perPage);
    
    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'pagination' => [
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $total,
            'perPage' => $perPage
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to retrieve logs'
    ]);
}