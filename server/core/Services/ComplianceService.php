<?php
namespace Core\Services;

use Core\Database;
use Core\Services\BaseService;
use Core\Services\AuditService;
use Core\Services\EmailService;
use PDO;
use Exception;

class ComplianceService extends BaseService
{
    private $auditService;
    private $emailService;
    
    // Alert throttling - max 1 alert per KPI per hour
    private const ALERT_THROTTLE_MINUTES = 60;
    
    public function __construct()
    {
        parent::__construct();
        $this->auditService = new AuditService();
        $this->emailService = new EmailService();
    }
    
    /**
     * Get all active KPIs with current values
     */
    public function getComplianceDashboard()
    {
        try {
            // Get all active KPIs
            $stmt = $this->db->prepare("
                SELECT 
                    k.*,
                    v.value as current_value,
                    v.status as current_status,
                    v.calculated_at as last_calculated
                FROM compliance_kpis k
                LEFT JOIN (
                    SELECT kpi_id, value, status, calculated_at
                    FROM compliance_kpi_values
                    WHERE (kpi_id, calculated_at) IN (
                        SELECT kpi_id, MAX(calculated_at)
                        FROM compliance_kpi_values
                        GROUP BY kpi_id
                    )
                ) v ON k.kpi_id = v.kpi_id
                WHERE k.is_active = 1
                ORDER BY k.kpi_category, k.kpi_name
            ");
            
            $stmt->execute();
            $kpis = $stmt->fetchAll();
            
            // Group by category
            $dashboard = [];
            foreach ($kpis as $kpi) {
                $category = $kpi['kpi_category'] ?? 'other';
                if (!isset($dashboard[$category])) {
                    $dashboard[$category] = [];
                }
                $dashboard[$category][] = $kpi;
            }
            
            return $dashboard;
            
        } catch (Exception $e) {
            $this->logError("Failed to get compliance dashboard: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Calculate and update KPI values
     */
    public function calculateKPIs()
    {
        try {
            $this->db->beginTransaction();
            
            // Get all active KPIs
            $stmt = $this->db->prepare("
                SELECT * FROM compliance_kpis 
                WHERE is_active = 1
            ");
            $stmt->execute();
            $kpis = $stmt->fetchAll();
            
            foreach ($kpis as $kpi) {
                try {
                    // Calculate the KPI value
                    $value = $this->calculateKPIValue($kpi);
                    
                    // Determine status based on thresholds
                    $status = $this->determineStatus($value, $kpi);
                    
                    // Store the calculated value
                    $this->storeKPIValue($kpi['kpi_id'], $value, $status);
                    
                    // Check if alert is needed
                    if ($status !== 'compliant') {
                        $this->checkAndSendAlert($kpi, $value, $status);
                    }
                    
                } catch (Exception $e) {
                    $this->logError("Failed to calculate KPI {$kpi['kpi_name']}: " . $e->getMessage());
                    // Continue with other KPIs
                }
            }
            
            $this->db->commit();
            
            // Log successful calculation
            $this->auditService->log('calculate', 'compliance_kpis', 'system', 
                'Calculated all compliance KPIs');
                
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError("Failed to calculate KPIs: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Calculate individual KPI value
     */
    private function calculateKPIValue($kpi)
    {
        $calculationMethod = $kpi['calculation_method'];
        
        // Check if it's a SQL query
        if (stripos($calculationMethod, 'SELECT') === 0) {
            try {
                $stmt = $this->db->prepare($calculationMethod);
                $stmt->execute();
                $result = $stmt->fetch();
                
                // Get the first numeric value from the result
                foreach ($result as $value) {
                    if (is_numeric($value)) {
                        return floatval($value);
                    }
                }
                
                throw new Exception("No numeric value found in query result");
                
            } catch (Exception $e) {
                throw new Exception("Error executing KPI calculation: " . $e->getMessage());
            }
        } else {
            // Handle other calculation methods
            return $this->executeCalculationMethod($kpi);
        }
    }
    
    /**
     * Execute specific KPI calculations
     */
    private function executeCalculationMethod($kpi)
    {
        switch ($kpi['kpi_name']) {
            case 'OSHA_TRIR':
                return $this->calculateOshaTrir();
                
            case 'OSHA_DART':
                return $this->calculateOshaDart();
                
            case 'Chart_Completion_Rate':
                return $this->calculateChartCompletionRate();
                
            case 'DOT_Random_Test_Compliance':
                return $this->calculateDotRandomTestCompliance();
                
            case 'HIPAA_Training_Compliance':
                return $this->calculateHipaaTrainingCompliance();
                
            case 'Unsigned_Encounters':
                return $this->calculateUnsignedEncounters();
                
            case 'MRO_Turnaround_Time':
                return $this->calculateMroTurnaroundTime();
                
            default:
                throw new Exception("Unknown KPI calculation method: {$kpi['kpi_name']}");
        }
    }
    
    /**
     * Calculate OSHA Total Recordable Incident Rate
     * Formula: (Number of recordable cases × 200,000) / Total hours worked
     */
    private function calculateOshaTrir()
    {
        $year = date('Y');
        
        // Get recordable cases
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as recordable_cases
            FROM osha_cases
            WHERE recordable = 1
            AND YEAR(incident_date) = :year
        ");
        $stmt->execute(['year' => $year]);
        $recordableCases = $stmt->fetch()['recordable_cases'];
        
        // Get total hours worked
        $stmt = $this->db->prepare("
            SELECT SUM(hours_worked) as total_hours
            FROM employee_hours
            WHERE year = :year
        ");
        $stmt->execute(['year' => $year]);
        $totalHours = $stmt->fetch()['total_hours'] ?? 1; // Prevent division by zero
        
        // Calculate TRIR
        $trir = ($recordableCases * 200000) / $totalHours;
        
        return round($trir, 2);
    }
    
    /**
     * Calculate OSHA Days Away, Restricted, or Transferred rate
     */
    private function calculateOshaDart()
    {
        $year = date('Y');
        
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as dart_cases
            FROM osha_cases
            WHERE (days_away > 0 OR days_restricted > 0 OR days_transferred > 0)
            AND YEAR(incident_date) = :year
        ");
        $stmt->execute(['year' => $year]);
        $dartCases = $stmt->fetch()['dart_cases'];
        
        $stmt = $this->db->prepare("
            SELECT SUM(hours_worked) as total_hours
            FROM employee_hours
            WHERE year = :year
        ");
        $stmt->execute(['year' => $year]);
        $totalHours = $stmt->fetch()['total_hours'] ?? 1;
        
        $dart = ($dartCases * 200000) / $totalHours;
        
        return round($dart, 2);
    }
    
    /**
     * Calculate chart completion rate for last 30 days
     */
    private function calculateChartCompletionRate()
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_encounters,
                COUNT(CASE WHEN status = 'complete' AND signed_at IS NOT NULL THEN 1 END) as complete_encounters
            FROM encounters
            WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
        ");
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result['total_encounters'] == 0) {
            return 100.0; // No encounters means 100% complete
        }
        
        $rate = ($result['complete_encounters'] / $result['total_encounters']) * 100;
        
        return round($rate, 1);
    }
    
    /**
     * Calculate DOT random test completion rate
     */
    private function calculateDotRandomTestCompliance()
    {
        $year = date('Y');
        
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_tests,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_tests
            FROM dot_tests
            WHERE test_type = 'random'
            AND YEAR(scheduled_date) = :year
        ");
        $stmt->execute(['year' => $year]);
        $result = $stmt->fetch();
        
        if ($result['total_tests'] == 0) {
            return 100.0;
        }
        
        $rate = ($result['completed_tests'] / $result['total_tests']) * 100;
        
        return round($rate, 1);
    }
    
    /**
     * Calculate HIPAA training compliance percentage
     */
    private function calculateHipaaTrainingCompliance()
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT u.id) as total_staff,
                COUNT(DISTINCT CASE 
                    WHEN str.status = 'current' THEN u.id 
                END) as compliant_staff
            FROM user u
            JOIN userrole ur ON u.id = ur.user_id
            JOIN role r ON ur.role_slug = r.slug
            LEFT JOIN staff_training_records str ON u.id = str.user_id
            LEFT JOIN training_requirements tr ON str.requirement_id = tr.requirement_id
                AND tr.training_name = 'HIPAA Privacy Training'
            WHERE r.slug IN ('pclinician', 'dclinician', '1clinician', 'cadmin', 'tadmin')
            AND u.status = 'active'
        ");
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result['total_staff'] == 0) {
            return 100.0;
        }
        
        $rate = ($result['compliant_staff'] / $result['total_staff']) * 100;
        
        return round($rate, 1);
    }
    
    /**
     * Calculate number of unsigned encounters older than 24 hours
     */
    private function calculateUnsignedEncounters()
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as unsigned_count
            FROM encounters
            WHERE status != 'complete'
            AND signed_at IS NULL
            AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
        
        return intval($stmt->fetch()['unsigned_count']);
    }
    
    /**
     * Calculate average MRO turnaround time in hours
     */
    private function calculateMroTurnaroundTime()
    {
        $stmt = $this->db->prepare("
            SELECT AVG(TIMESTAMPDIFF(HOUR, specimen_collected_at, mro_verified_at)) as avg_hours
            FROM dot_tests
            WHERE mro_verified_at IS NOT NULL
            AND specimen_collected_at IS NOT NULL
            AND test_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
        ");
        $stmt->execute();
        $avgHours = $stmt->fetch()['avg_hours'];
        
        return $avgHours ? round($avgHours, 1) : 0;
    }
    
    /**
     * Determine KPI status based on value and thresholds
     */
    private function determineStatus($value, $kpi)
    {
        // For some KPIs, higher is better (compliance rates)
        // For others, lower is better (incident rates, turnaround times)
        $higherIsBetter = in_array($kpi['unit'], ['percentage', 'compliance_rate']);
        
        if ($higherIsBetter) {
            if ($value <= $kpi['threshold_critical']) {
                return 'critical';
            } elseif ($value <= $kpi['threshold_warning']) {
                return 'warning';
            }
        } else {
            if ($value >= $kpi['threshold_critical']) {
                return 'critical';
            } elseif ($value >= $kpi['threshold_warning']) {
                return 'warning';
            }
        }
        
        return 'compliant';
    }
    
    /**
     * Store calculated KPI value
     */
    private function storeKPIValue($kpiId, $value, $status)
    {
        $stmt = $this->db->prepare("
            INSERT INTO compliance_kpi_values (
                value_id, kpi_id, value, status, calculated_at
            ) VALUES (
                UUID(), :kpi_id, :value, :status, NOW()
            )
        ");
        
        $stmt->execute([
            'kpi_id' => $kpiId,
            'value' => $value,
            'status' => $status
        ]);
    }
    
    /**
     * Check if alert should be sent and send it
     */
    private function checkAndSendAlert($kpi, $value, $status)
    {
        // Check if we've already sent an alert recently
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as recent_alerts
            FROM compliance_alerts
            WHERE kpi_id = :kpi_id
            AND created_at > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
        ");
        $stmt->execute([
            'kpi_id' => $kpi['kpi_id'],
            'minutes' => self::ALERT_THROTTLE_MINUTES
        ]);
        
        if ($stmt->fetch()['recent_alerts'] > 0) {
            return; // Alert already sent recently
        }
        
        // Get alert recipients based on KPI category
        $recipients = $this->getAlertRecipients($kpi['kpi_category']);
        
        if (empty($recipients)) {
            return; // No one to alert
        }
        
        // Create alert message
        $alertMessage = $this->createAlertMessage($kpi, $value, $status);
        
        // Store alert in database
        $alertId = $this->storeAlert($kpi['kpi_id'], $alertMessage, $status, $recipients);
        
        // Send email alerts
        foreach ($recipients as $recipient) {
            $this->sendAlertEmail($recipient, $kpi, $value, $status, $alertMessage);
        }
        
        // Log the alert
        $this->auditService->log('alert', 'compliance_kpis', $kpi['kpi_id'], 
            "Compliance alert sent: {$kpi['kpi_name']} - Status: {$status}");
    }
    
    /**
     * Get alert recipients based on KPI category
     */
    private function getAlertRecipients($category)
    {
        $roleMap = [
            'hipaa' => ['tadmin', 'cadmin'],
            'osha' => ['tadmin', 'cadmin', 'pclinician'],
            'dot' => ['tadmin', 'cadmin'],
            'clinical' => ['cadmin', 'pclinician']
        ];
        
        $roles = $roleMap[$category] ?? ['tadmin'];
        
        $placeholders = str_repeat('?,', count($roles) - 1) . '?';
        
        $stmt = $this->db->prepare("
            SELECT DISTINCT u.id, u.email, u.first_name, u.last_name
            FROM user u
            JOIN userrole ur ON u.id = ur.user_id
            WHERE ur.role_slug IN ($placeholders)
            AND u.status = 'active'
            AND u.email IS NOT NULL
        ");
        $stmt->execute($roles);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Create alert message
     */
    private function createAlertMessage($kpi, $value, $status)
    {
        $statusText = ucfirst($status);
        $unit = $kpi['unit'] === 'percentage' ? '%' : ' ' . $kpi['unit'];
        
        return sprintf(
            "%s Alert: %s is %s at %s%s (Warning: %s, Critical: %s)",
            $statusText,
            $kpi['kpi_name'],
            $status,
            $value,
            $unit,
            $kpi['threshold_warning'],
            $kpi['threshold_critical']
        );
    }
    
    /**
     * Store alert in database
     */
    private function storeAlert($kpiId, $message, $severity, $recipients)
    {
        $stmt = $this->db->prepare("
            INSERT INTO compliance_alerts (
                alert_id, kpi_id, alert_message, severity, sent_to, created_at
            ) VALUES (
                UUID(), :kpi_id, :message, :severity, :sent_to, NOW()
            )
        ");
        
        $sentTo = array_map(function($r) { return $r['id']; }, $recipients);
        
        $stmt->execute([
            'kpi_id' => $kpiId,
            'message' => $message,
            'severity' => ($severity === 'critical' ? 'critical' : 'warning'),
            'sent_to' => json_encode($sentTo)
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Send alert email
     */
    private function sendAlertEmail($recipient, $kpi, $value, $status, $message)
    {
        $subject = "[SafeShift] Compliance Alert: {$kpi['kpi_name']} - {$status}";
        
        $body = $this->createAlertEmailBody($recipient, $kpi, $value, $status, $message);
        
        try {
            $this->emailService->send($recipient['email'], $subject, $body);
        } catch (Exception $e) {
            $this->logError("Failed to send alert email to {$recipient['email']}: " . $e->getMessage());
        }
    }
    
    /**
     * Create alert email body
     */
    private function createAlertEmailBody($recipient, $kpi, $value, $status, $message)
    {
        $statusColor = $status === 'critical' ? '#dc3545' : '#ffc107';
        $unit = $kpi['unit'] === 'percentage' ? '%' : ' ' . $kpi['unit'];
        
        return "
        <html>
        <body style='font-family: Arial, sans-serif; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto;'>
                <h2 style='color: {$statusColor};'>Compliance Alert: {$status}</h2>
                
                <p>Hello {$recipient['first_name']},</p>
                
                <p>A compliance KPI has exceeded its threshold and requires attention:</p>
                
                <div style='background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3 style='margin-top: 0;'>{$kpi['kpi_name']}</h3>
                    <p><strong>Current Value:</strong> {$value}{$unit}</p>
                    <p><strong>Warning Threshold:</strong> {$kpi['threshold_warning']}</p>
                    <p><strong>Critical Threshold:</strong> {$kpi['threshold_critical']}</p>
                    <p><strong>Category:</strong> " . strtoupper($kpi['kpi_category']) . "</p>
                </div>
                
                <p>{$message}</p>
                
                <p>Please log in to the SafeShift dashboard to review this alert and take appropriate action.</p>
                
                <p><a href='" . BASE_URL . "/dashboards/compliance-monitor.php' 
                      style='display: inline-block; padding: 10px 20px; background-color: #007bff; 
                             color: white; text-decoration: none; border-radius: 4px;'>
                    View Compliance Dashboard
                </a></p>
                
                <hr style='margin: 30px 0; border: 0; border-top: 1px solid #e0e0e0;'>
                
                <p style='font-size: 12px; color: #666;'>
                    This is an automated compliance alert from SafeShift EHR. 
                    Do not reply to this email.
                </p>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Get KPI history for trend analysis
     */
    public function getKPIHistory($kpiId, $days = 30)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    value,
                    status,
                    calculated_at
                FROM compliance_kpi_values
                WHERE kpi_id = :kpi_id
                AND calculated_at >= DATE_SUB(CURRENT_DATE, INTERVAL :days DAY)
                ORDER BY calculated_at ASC
            ");
            
            $stmt->execute([
                'kpi_id' => $kpiId,
                'days' => $days
            ]);
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            $this->logError("Failed to get KPI history: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get all KPIs for a specific category
     */
    public function getKPIsByCategory($category)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT k.*, 
                       v.value as current_value,
                       v.status as current_status,
                       v.calculated_at as last_calculated
                FROM compliance_kpis k
                LEFT JOIN (
                    SELECT kpi_id, value, status, calculated_at
                    FROM compliance_kpi_values v1
                    WHERE calculated_at = (
                        SELECT MAX(calculated_at) 
                        FROM compliance_kpi_values v2 
                        WHERE v2.kpi_id = v1.kpi_id
                    )
                ) v ON k.kpi_id = v.kpi_id
                WHERE k.kpi_category = :category
                AND k.is_active = 1
                ORDER BY k.kpi_name
            ");
            
            $stmt->execute(['category' => $category]);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            $this->logError("Failed to get KPIs by category: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update KPI thresholds
     */
    public function updateKPIThresholds($kpiId, $warningThreshold, $criticalThreshold, $updatedBy)
    {
        try {
            $this->db->beginTransaction();
            
            // Get current thresholds for audit trail
            $stmt = $this->db->prepare("
                SELECT threshold_warning, threshold_critical 
                FROM compliance_kpis 
                WHERE kpi_id = :kpi_id
            ");
            $stmt->execute(['kpi_id' => $kpiId]);
            $oldThresholds = $stmt->fetch();
            
            // Update thresholds
            $stmt = $this->db->prepare("
                UPDATE compliance_kpis
                SET threshold_warning = :warning,
                    threshold_critical = :critical,
                    updated_at = NOW()
                WHERE kpi_id = :kpi_id
            ");
            
            $stmt->execute([
                'kpi_id' => $kpiId,
                'warning' => $warningThreshold,
                'critical' => $criticalThreshold
            ]);
            
            // Log the change
            $this->auditService->log('update', 'compliance_kpis', $kpiId,
                sprintf(
                    "Updated thresholds - Warning: %s->%s, Critical: %s->%s",
                    $oldThresholds['threshold_warning'],
                    $warningThreshold,
                    $oldThresholds['threshold_critical'],
                    $criticalThreshold
                )
            );
            
            $this->db->commit();
            
            // Recalculate the KPI with new thresholds
            $this->calculateSingleKPI($kpiId);
            
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError("Failed to update KPI thresholds: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Calculate a single KPI
     */
    public function calculateSingleKPI($kpiId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM compliance_kpis 
                WHERE kpi_id = :kpi_id
            ");
            $stmt->execute(['kpi_id' => $kpiId]);
            $kpi = $stmt->fetch();
            
            if (!$kpi) {
                throw new Exception("KPI not found");
            }
            
            $value = $this->calculateKPIValue($kpi);
            $status = $this->determineStatus($value, $kpi);
            $this->storeKPIValue($kpiId, $value, $status);
            
            if ($status !== 'compliant') {
                $this->checkAndSendAlert($kpi, $value, $status);
            }
            
            return [
                'value' => $value,
                'status' => $status
            ];
            
        } catch (Exception $e) {
            $this->logError("Failed to calculate single KPI: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get recent alerts
     */
    public function getRecentAlerts($limit = 10)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    a.*,
                    k.kpi_name,
                    k.kpi_category
                FROM compliance_alerts a
                JOIN compliance_kpis k ON a.kpi_id = k.kpi_id
                ORDER BY a.created_at DESC
                LIMIT :limit
            ");
            
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            $this->logError("Failed to get recent alerts: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Acknowledge an alert
     */
    public function acknowledgeAlert($alertId, $userId)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE compliance_alerts
                SET acknowledged_by = :user_id,
                    acknowledged_at = NOW()
                WHERE alert_id = :alert_id
            ");
            
            $stmt->execute([
                'alert_id' => $alertId,
                'user_id' => $userId
            ]);
            
            // Log the acknowledgment
            $this->auditService->log('acknowledge', 'compliance_alerts', $alertId,
                'Acknowledged compliance alert');
            
            return true;
            
        } catch (Exception $e) {
            $this->logError("Failed to acknowledge alert: " . $e->getMessage());
            throw $e;
        }
    }
}