<?php
/**
 * Cron job to calculate compliance KPIs
 * Run hourly: 0 * * * * /usr/bin/php /path/to/calculate-kpis.php
 */

// Change to the application root directory
chdir(dirname(__DIR__));

require_once 'includes/bootstrap.php';

use Core\Services\ComplianceService;
use Core\Services\AuditService;

// Set up error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/cron_errors.log');

// Prevent timeout
set_time_limit(300); // 5 minutes

try {
    echo "[" . date('Y-m-d H:i:s') . "] Starting KPI calculation...\n";
    
    // Initialize services
    $complianceService = new ComplianceService();
    $auditService = new AuditService();
    
    // Calculate all KPIs
    $result = $complianceService->calculateKPIs();
    
    if ($result) {
        echo "[" . date('Y-m-d H:i:s') . "] KPI calculation completed successfully.\n";
        
        // Log successful calculation
        $auditService->log('cron', 'compliance_kpis', 'system', 
            'Cron job: Successfully calculated all compliance KPIs');
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] KPI calculation failed.\n";
        
        // Log failure
        $auditService->log('cron_error', 'compliance_kpis', 'system', 
            'Cron job: Failed to calculate compliance KPIs');
    }
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    error_log("KPI calculation cron error: " . $e->getMessage());
    
    // Try to log the error
    try {
        $auditService = new AuditService();
        $auditService->log('cron_error', 'compliance_kpis', 'system', 
            'Cron job error: ' . $e->getMessage());
    } catch (Exception $auditError) {
        // If we can't even log the error, just write to error log
        error_log("Could not log audit error: " . $auditError->getMessage());
    }
    
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] KPI calculation cron job finished.\n";
exit(0);