<?php
/**
 * ReportsViewModel - Business logic layer for reports and analytics
 * 
 * Handles: Dashboard data, safety stats, compliance status, report generation
 * Security: Role-based filtering, cached results, export functionality
 * 
 * @package SafeShift\ViewModel\Reports
 */

declare(strict_types=1);

namespace ViewModel\Reports;

use ViewModel\Core\BaseViewModel;
use ViewModel\Core\ApiResponse;
use Core\Repositories\EncounterRepository;
use Core\Repositories\PatientRepository;
use Core\Services\AuditService;
use Exception;
use PDO;

/**
 * Reports ViewModel
 * 
 * Aggregates data from multiple repositories for reporting and analytics.
 * Provides caching for expensive queries and role-based data filtering.
 */
class ReportsViewModel extends BaseViewModel
{
    /** @var EncounterRepository */
    private EncounterRepository $encounterRepo;
    
    /** @var PatientRepository */
    private PatientRepository $patientRepo;
    
    /** @var array Simple in-memory cache */
    private static array $cache = [];
    
    /** Cache TTL in seconds */
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Constructor
     * 
     * @param EncounterRepository|null $encounterRepo
     * @param PatientRepository|null $patientRepo
     * @param AuditService|null $auditService
     * @param PDO|null $pdo
     */
    public function __construct(
        ?EncounterRepository $encounterRepo = null,
        ?PatientRepository $patientRepo = null,
        ?AuditService $auditService = null,
        ?PDO $pdo = null
    ) {
        parent::__construct($auditService, $pdo);
        
        $this->encounterRepo = $encounterRepo ?? new EncounterRepository($this->pdo);
        $this->patientRepo = $patientRepo ?? new PatientRepository($this->pdo);
    }

    /**
     * Get comprehensive dashboard data
     * 
     * @return array API response
     */
    public function getDashboardData(): array
    {
        try {
            $this->requireAuth();
            
            $cacheKey = 'dashboard_' . $this->getCurrentUserRole() . '_' . date('Y-m-d_H');
            
            if ($cached = $this->getFromCache($cacheKey)) {
                return ApiResponse::success($cached, 'Dashboard data retrieved (cached)');
            }
            
            $data = [
                'today_stats' => $this->getTodayStats(),
                'weekly_summary' => $this->getWeeklySummary(),
                'patient_flow' => $this->getPatientFlowStats(),
                'upcoming_appointments' => $this->getUpcomingAppointments(),
                'alerts' => $this->getSystemAlerts(),
                'generated_at' => gmdate('Y-m-d\TH:i:s\Z')
            ];
            
            // Add role-specific data
            $role = $this->getCurrentUserRole();
            
            if (in_array($role, ['tadmin', 'cadmin', 'Admin'])) {
                $data['system_health'] = $this->getSystemHealth();
                $data['compliance_summary'] = $this->getComplianceSummary();
            }
            
            if (in_array($role, ['pclinician', 'dclinician', '1clinician'])) {
                $data['my_patients'] = $this->getClinicianPatientStats();
                $data['pending_tasks'] = $this->getPendingTaskCount();
            }
            
            $this->setCache($cacheKey, $data);
            
            // Log access
            $this->audit('VIEW', 'dashboard_data', null);
            
            return ApiResponse::success($data, 'Dashboard data retrieved');
            
        } catch (Exception $e) {
            $this->logError('getDashboardData', $e);
            return $this->handleException($e, 'Failed to retrieve dashboard data');
        }
    }

    /**
     * Get safety statistics
     * 
     * @param array $filters Optional filters (date_range, employer_id, etc.)
     * @return array API response
     */
    public function getSafetyStats(array $filters = []): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('view_reports');
            
            $year = (int)($filters['year'] ?? date('Y'));
            $employerId = $filters['employer_id'] ?? null;
            
            // Get OSHA recordable counts
            $oshaStats = $this->getOshaStats($year, $employerId);
            
            // Get DOT testing stats
            $dotStats = $this->getDotTestingStats($year, $employerId);
            
            // Get work-related injury trends
            $injuryTrends = $this->getInjuryTrends($year, $employerId);
            
            $data = [
                'year' => $year,
                'employer_id' => $employerId,
                'osha' => $oshaStats,
                'dot' => $dotStats,
                'injury_trends' => $injuryTrends,
                'generated_at' => gmdate('Y-m-d\TH:i:s\Z')
            ];
            
            // Log access
            $this->audit('VIEW', 'safety_stats', null, ['year' => $year]);
            
            return ApiResponse::success($data, 'Safety statistics retrieved');
            
        } catch (Exception $e) {
            $this->logError('getSafetyStats', $e, ['filters' => $filters]);
            return $this->handleException($e, 'Failed to retrieve safety statistics');
        }
    }

    /**
     * Get compliance status
     * 
     * @return array API response
     */
    public function getComplianceStatus(): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('view_compliance');
            
            $cacheKey = 'compliance_status_' . date('Y-m-d');
            
            if ($cached = $this->getFromCache($cacheKey)) {
                return ApiResponse::success($cached, 'Compliance status retrieved (cached)');
            }
            
            $data = [
                'training_compliance' => $this->getTrainingCompliance(),
                'certification_status' => $this->getCertificationStatus(),
                'documentation_compliance' => $this->getDocumentationCompliance(),
                'regulatory_updates' => $this->getPendingRegulatoryUpdates(),
                'overall_score' => $this->calculateOverallComplianceScore(),
                'generated_at' => gmdate('Y-m-d\TH:i:s\Z')
            ];
            
            $this->setCache($cacheKey, $data);
            
            // Log access
            $this->audit('VIEW', 'compliance_status', null);
            
            return ApiResponse::success($data, 'Compliance status retrieved');
            
        } catch (Exception $e) {
            $this->logError('getComplianceStatus', $e);
            return $this->handleException($e, 'Failed to retrieve compliance status');
        }
    }

    /**
     * Generate report
     * 
     * @param string $type Report type
     * @param array $params Report parameters
     * @return array API response
     */
    public function generateReport(string $type, array $params = []): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('view_reports');
            
            $validTypes = [
                'patient_volume',
                'encounter_summary',
                'osha_300_log',
                'osha_300a_summary',
                'dot_testing',
                'provider_productivity',
                'compliance_summary',
                'audit_trail'
            ];
            
            if (!in_array($type, $validTypes)) {
                return ApiResponse::validationError(['type' => ['Invalid report type']]);
            }
            
            $report = match ($type) {
                'patient_volume' => $this->generatePatientVolumeReport($params),
                'encounter_summary' => $this->generateEncounterSummaryReport($params),
                'osha_300_log' => $this->generateOsha300Report($params),
                'osha_300a_summary' => $this->generateOsha300AReport($params),
                'dot_testing' => $this->generateDotTestingReport($params),
                'provider_productivity' => $this->generateProviderProductivityReport($params),
                'compliance_summary' => $this->generateComplianceSummaryReport($params),
                'audit_trail' => $this->generateAuditTrailReport($params),
                default => ['error' => 'Unknown report type']
            };
            
            // Log report generation
            $this->audit('GENERATE', 'report', $type, [
                'params' => array_keys($params)
            ]);
            
            return ApiResponse::success([
                'report_type' => $type,
                'parameters' => $params,
                'data' => $report,
                'generated_at' => gmdate('Y-m-d\TH:i:s\Z')
            ], 'Report generated successfully');
            
        } catch (Exception $e) {
            $this->logError('generateReport', $e, ['type' => $type]);
            return $this->handleException($e, 'Failed to generate report');
        }
    }

    /**
     * Export report to file
     * 
     * @param string $type Report type
     * @param string $format Export format (csv, pdf, xlsx)
     * @param array $params Report parameters
     * @return array API response
     */
    public function exportReport(string $type, string $format = 'csv', array $params = []): array
    {
        try {
            $this->requireAuth();
            $this->requirePermission('view_reports');
            
            $validFormats = ['csv', 'pdf', 'xlsx', 'json'];
            
            if (!in_array($format, $validFormats)) {
                return ApiResponse::validationError(['format' => ['Invalid export format']]);
            }
            
            // Generate report data
            $reportResult = $this->generateReport($type, $params);
            
            if (!$reportResult['success']) {
                return $reportResult;
            }
            
            $reportData = $reportResult['data']['data'];
            
            // Generate export file
            $exportResult = match ($format) {
                'csv' => $this->exportToCsv($type, $reportData),
                'json' => $this->exportToJson($type, $reportData),
                'pdf' => $this->exportToPdf($type, $reportData, $params),
                'xlsx' => $this->exportToXlsx($type, $reportData),
                default => ['error' => 'Unsupported format']
            };
            
            if (isset($exportResult['error'])) {
                return ApiResponse::serverError($exportResult['error']);
            }
            
            // Log export
            $this->audit('EXPORT', 'report', $type, [
                'format' => $format
            ]);
            
            return ApiResponse::success([
                'report_type' => $type,
                'format' => $format,
                'filename' => $exportResult['filename'],
                'content' => $exportResult['content'] ?? null,
                'download_url' => $exportResult['download_url'] ?? null,
                'generated_at' => gmdate('Y-m-d\TH:i:s\Z')
            ], 'Report exported successfully');
            
        } catch (Exception $e) {
            $this->logError('exportReport', $e, ['type' => $type, 'format' => $format]);
            return $this->handleException($e, 'Failed to export report');
        }
    }

    // ========== PRIVATE HELPER METHODS ==========

    /**
     * Get today's statistics
     * 
     * @return array
     */
    private function getTodayStats(): array
    {
        try {
            $todayPatients = $this->encounterRepo->countTodayPatients();
            $newPatients = $this->encounterRepo->countNewPatientsToday();
            $pendingReviews = $this->encounterRepo->countPendingReviews();
            
            // Get procedure counts
            $sql = "SELECT 
                        COUNT(CASE WHEN encounter_type = 'drug_test' THEN 1 END) as drug_tests,
                        COUNT(CASE WHEN encounter_type = 'physical' THEN 1 END) as physicals,
                        COUNT(CASE WHEN encounter_type = 'injury' THEN 1 END) as injuries,
                        COUNT(*) as total_encounters
                    FROM encounters
                    WHERE DATE(started_at) = CURDATE()
                    AND status IN ('completed', 'in-progress')";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $counts = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'total_patients_today' => $todayPatients,
                'new_patients_today' => $newPatients,
                'returning_patients_today' => $todayPatients - $newPatients,
                'drug_tests_today' => (int)($counts['drug_tests'] ?? 0),
                'physicals_today' => (int)($counts['physicals'] ?? 0),
                'injuries_today' => (int)($counts['injuries'] ?? 0),
                'total_encounters' => (int)($counts['total_encounters'] ?? 0),
                'pending_reviews' => $pendingReviews,
            ];
        } catch (Exception $e) {
            $this->logError('getTodayStats', $e);
            return [
                'total_patients_today' => 0,
                'new_patients_today' => 0,
                'returning_patients_today' => 0,
                'drug_tests_today' => 0,
                'physicals_today' => 0,
                'injuries_today' => 0,
                'total_encounters' => 0,
                'pending_reviews' => 0,
            ];
        }
    }

    /**
     * Get weekly summary
     * 
     * @return array
     */
    private function getWeeklySummary(): array
    {
        try {
            $sql = "SELECT 
                        DATE(started_at) as date,
                        COUNT(DISTINCT patient_id) as patient_count,
                        COUNT(*) as encounter_count
                    FROM encounters
                    WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    AND status IN ('completed', 'in-progress', 'pending_review')
                    GROUP BY DATE(started_at)
                    ORDER BY date ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logError('getWeeklySummary', $e);
            return [];
        }
    }

    /**
     * Get patient flow statistics
     * 
     * @return array
     */
    private function getPatientFlowStats(): array
    {
        try {
            $sql = "SELECT 
                        HOUR(started_at) as hour,
                        COUNT(*) as count
                    FROM encounters
                    WHERE DATE(started_at) = CURDATE()
                    GROUP BY HOUR(started_at)
                    ORDER BY hour ASC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $hourly = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Fill in missing hours
            $flow = [];
            for ($i = 0; $i < 24; $i++) {
                $flow[$i] = $hourly[$i] ?? 0;
            }
            
            return [
                'hourly_distribution' => $flow,
                'peak_hour' => array_search(max($flow), $flow),
                'current_hour_count' => $flow[(int)date('G')] ?? 0
            ];
        } catch (Exception $e) {
            $this->logError('getPatientFlowStats', $e);
            return ['hourly_distribution' => [], 'peak_hour' => null, 'current_hour_count' => 0];
        }
    }

    /**
     * Get upcoming appointments
     * 
     * @return array
     */
    private function getUpcomingAppointments(): array
    {
        try {
            return $this->encounterRepo->getUpcomingAppointments(10);
        } catch (Exception $e) {
            $this->logError('getUpcomingAppointments', $e);
            return [];
        }
    }

    /**
     * Get system alerts
     * 
     * @return array
     */
    private function getSystemAlerts(): array
    {
        $alerts = [];
        
        try {
            // Check for DOT tests approaching deadline
            $sql = "SELECT COUNT(*) as count FROM dot_tests 
                    WHERE status NOT IN ('completed', 'cancelled')
                    AND notification_deadline <= DATE_ADD(NOW(), INTERVAL 24 HOUR)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $dotDeadlines = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            
            if ($dotDeadlines > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'category' => 'dot',
                    'message' => "$dotDeadlines DOT test(s) approaching notification deadline",
                    'action_url' => '/dot/pending'
                ];
            }
            
            // Check for pending reviews
            $pendingReviews = $this->encounterRepo->countPendingReviews();
            if ($pendingReviews > 10) {
                $alerts[] = [
                    'type' => 'info',
                    'category' => 'workflow',
                    'message' => "$pendingReviews encounters pending review",
                    'action_url' => '/encounters/pending'
                ];
            }
            
            // Check for expiring certifications (if table exists)
            try {
                $sql = "SELECT COUNT(*) as count FROM certifications 
                        WHERE expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                        AND expiration_date >= CURDATE()";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute();
                $expiringCerts = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
                
                if ($expiringCerts > 0) {
                    $alerts[] = [
                        'type' => 'warning',
                        'category' => 'compliance',
                        'message' => "$expiringCerts certification(s) expiring within 30 days",
                        'action_url' => '/compliance/certifications'
                    ];
                }
            } catch (Exception $e) {
                // Table may not exist
            }
            
        } catch (Exception $e) {
            $this->logError('getSystemAlerts', $e);
        }
        
        return $alerts;
    }

    /**
     * Get system health metrics
     * 
     * @return array
     */
    private function getSystemHealth(): array
    {
        return [
            'database_status' => 'healthy',
            'active_sessions' => $this->getActiveSessionCount(),
            'last_backup' => $this->getLastBackupTime(),
            'disk_usage' => null, // Would require system calls
        ];
    }

    /**
     * Get active session count
     * 
     * @return int
     */
    private function getActiveSessionCount(): int
    {
        try {
            $sql = "SELECT COUNT(*) FROM sessions WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get last backup time
     * 
     * @return string|null
     */
    private function getLastBackupTime(): ?string
    {
        try {
            $sql = "SELECT MAX(created_at) FROM backups WHERE status = 'completed'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchColumn() ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get compliance summary
     * 
     * @return array
     */
    private function getComplianceSummary(): array
    {
        return [
            'training_rate' => 0,
            'certification_rate' => 0,
            'documentation_rate' => 0,
            'overall_rate' => 0
        ];
    }

    /**
     * Get clinician patient stats
     * 
     * @return array
     */
    private function getClinicianPatientStats(): array
    {
        try {
            $userId = $this->getCurrentUserId();
            
            $sql = "SELECT 
                        COUNT(DISTINCT patient_id) as total_patients,
                        COUNT(*) as total_encounters,
                        COUNT(CASE WHEN DATE(started_at) = CURDATE() THEN 1 END) as today_encounters
                    FROM encounters
                    WHERE provider_id = :user_id
                    AND started_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logError('getClinicianPatientStats', $e);
            return ['total_patients' => 0, 'total_encounters' => 0, 'today_encounters' => 0];
        }
    }

    /**
     * Get pending task count for current user
     * 
     * @return int
     */
    private function getPendingTaskCount(): int
    {
        try {
            $userId = $this->getCurrentUserId();
            
            $sql = "SELECT COUNT(*) FROM encounters 
                    WHERE provider_id = :user_id 
                    AND status IN ('draft', 'in-progress', 'pending_review')";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get OSHA stats
     * 
     * @param int $year
     * @param string|null $employerId
     * @return array
     */
    private function getOshaStats(int $year, ?string $employerId): array
    {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_recordable,
                        SUM(CASE WHEN case_classification = 'death' THEN 1 ELSE 0 END) as deaths,
                        SUM(CASE WHEN case_classification = 'days_away' THEN 1 ELSE 0 END) as days_away,
                        SUM(CASE WHEN case_classification = 'job_restriction' THEN 1 ELSE 0 END) as restricted,
                        SUM(days_away_from_work) as total_days_away,
                        SUM(days_restricted) as total_days_restricted
                    FROM osha_injuries
                    WHERE YEAR(injury_date) = :year
                    AND is_recordable = 1
                    AND deleted_at IS NULL";
            
            $params = ['year' => $year];
            
            if ($employerId) {
                $sql .= " AND employer_id = :employer_id";
                $params['employer_id'] = $employerId;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logError('getOshaStats', $e);
            return [];
        }
    }

    /**
     * Get DOT testing stats
     * 
     * @param int $year
     * @param string|null $employerId
     * @return array
     */
    private function getDotTestingStats(int $year, ?string $employerId): array
    {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_tests,
                        COUNT(CASE WHEN mro_final_result = 'negative' THEN 1 END) as negative,
                        COUNT(CASE WHEN mro_final_result = 'positive' THEN 1 END) as positive,
                        COUNT(CASE WHEN mro_final_result = 'refusal' THEN 1 END) as refusals,
                        COUNT(CASE WHEN test_type = 'pre_employment' THEN 1 END) as pre_employment,
                        COUNT(CASE WHEN test_type = 'random' THEN 1 END) as random,
                        COUNT(CASE WHEN test_type = 'post_accident' THEN 1 END) as post_accident
                    FROM dot_tests
                    WHERE YEAR(ordered_at) = :year
                    AND status = 'completed'";
            
            $params = ['year' => $year];
            
            if ($employerId) {
                $sql .= " AND employer_id = :employer_id";
                $params['employer_id'] = $employerId;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logError('getDotTestingStats', $e);
            return [];
        }
    }

    /**
     * Get injury trends
     * 
     * @param int $year
     * @param string|null $employerId
     * @return array
     */
    private function getInjuryTrends(int $year, ?string $employerId): array
    {
        try {
            $sql = "SELECT 
                        MONTH(injury_date) as month,
                        COUNT(*) as count
                    FROM osha_injuries
                    WHERE YEAR(injury_date) = :year
                    AND deleted_at IS NULL";
            
            $params = ['year' => $year];
            
            if ($employerId) {
                $sql .= " AND employer_id = :employer_id";
                $params['employer_id'] = $employerId;
            }
            
            $sql .= " GROUP BY MONTH(injury_date) ORDER BY month";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Fill all months
            $trends = [];
            for ($m = 1; $m <= 12; $m++) {
                $trends[$m] = $data[$m] ?? 0;
            }
            
            return $trends;
        } catch (Exception $e) {
            $this->logError('getInjuryTrends', $e);
            return [];
        }
    }

    /**
     * Get training compliance
     * 
     * @return array
     */
    private function getTrainingCompliance(): array
    {
        // Placeholder - would query training tables
        return [
            'completion_rate' => 0,
            'overdue_count' => 0,
            'upcoming_count' => 0
        ];
    }

    /**
     * Get certification status
     * 
     * @return array
     */
    private function getCertificationStatus(): array
    {
        // Placeholder - would query certification tables
        return [
            'valid_count' => 0,
            'expiring_30_days' => 0,
            'expired_count' => 0
        ];
    }

    /**
     * Get documentation compliance
     * 
     * @return array
     */
    private function getDocumentationCompliance(): array
    {
        // Placeholder - would analyze encounter documentation
        return [
            'completion_rate' => 0,
            'incomplete_count' => 0
        ];
    }

    /**
     * Get pending regulatory updates
     * 
     * @return array
     */
    private function getPendingRegulatoryUpdates(): array
    {
        // Placeholder - would query regulatory updates table
        return [];
    }

    /**
     * Calculate overall compliance score
     * 
     * @return int
     */
    private function calculateOverallComplianceScore(): int
    {
        // Placeholder - would calculate weighted average
        return 0;
    }

    // ========== REPORT GENERATION METHODS ==========

    private function generatePatientVolumeReport(array $params): array
    {
        $startDate = $params['start_date'] ?? date('Y-m-01');
        $endDate = $params['end_date'] ?? date('Y-m-d');
        
        $sql = "SELECT 
                    DATE(started_at) as date,
                    COUNT(DISTINCT patient_id) as unique_patients,
                    COUNT(*) as total_encounters,
                    COUNT(CASE WHEN encounter_type = 'injury' THEN 1 END) as injuries,
                    COUNT(CASE WHEN encounter_type = 'physical' THEN 1 END) as physicals,
                    COUNT(CASE WHEN encounter_type = 'drug_test' THEN 1 END) as drug_tests
                FROM encounters
                WHERE DATE(started_at) BETWEEN :start_date AND :end_date
                AND status != 'voided'
                GROUP BY DATE(started_at)
                ORDER BY date";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
        
        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'daily_data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    private function generateEncounterSummaryReport(array $params): array
    {
        $startDate = $params['start_date'] ?? date('Y-m-01');
        $endDate = $params['end_date'] ?? date('Y-m-d');
        
        $sql = "SELECT 
                    e.encounter_type,
                    e.status,
                    COUNT(*) as count,
                    AVG(TIMESTAMPDIFF(MINUTE, e.started_at, e.ended_at)) as avg_duration_minutes
                FROM encounters e
                WHERE DATE(e.started_at) BETWEEN :start_date AND :end_date
                AND e.status != 'voided'
                GROUP BY e.encounter_type, e.status";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
        
        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'summary' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    private function generateOsha300Report(array $params): array
    {
        $year = (int)($params['year'] ?? date('Y'));
        $establishmentId = $params['establishment_id'] ?? null;
        
        // Delegate to OshaViewModel
        $oshaViewModel = new \ViewModel\OSHA\OshaViewModel($this->auditService, $this->pdo);
        $result = $oshaViewModel->get300Log($year, $establishmentId);
        
        return $result['success'] ? $result['data'] : [];
    }

    private function generateOsha300AReport(array $params): array
    {
        $year = (int)($params['year'] ?? date('Y'));
        $establishmentId = $params['establishment_id'] ?? null;
        
        // Delegate to OshaViewModel
        $oshaViewModel = new \ViewModel\OSHA\OshaViewModel($this->auditService, $this->pdo);
        $result = $oshaViewModel->calculateRates($year, $establishmentId);
        
        return $result['success'] ? $result['data'] : [];
    }

    private function generateDotTestingReport(array $params): array
    {
        $year = (int)($params['year'] ?? date('Y'));
        
        return $this->getDotTestingStats($year, $params['employer_id'] ?? null);
    }

    private function generateProviderProductivityReport(array $params): array
    {
        $startDate = $params['start_date'] ?? date('Y-m-01');
        $endDate = $params['end_date'] ?? date('Y-m-d');
        
        $sql = "SELECT 
                    u.user_id as provider_id,
                    CONCAT(u.first_name, ' ', u.last_name) as provider_name,
                    COUNT(e.encounter_id) as encounter_count,
                    COUNT(DISTINCT e.patient_id) as unique_patients,
                    AVG(TIMESTAMPDIFF(MINUTE, e.started_at, e.ended_at)) as avg_duration
                FROM encounters e
                JOIN user u ON e.provider_id = u.user_id
                WHERE DATE(e.started_at) BETWEEN :start_date AND :end_date
                AND e.status = 'completed'
                GROUP BY e.provider_id
                ORDER BY encounter_count DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
        
        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'providers' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    private function generateComplianceSummaryReport(array $params): array
    {
        return $this->getComplianceSummary();
    }

    private function generateAuditTrailReport(array $params): array
    {
        $startDate = $params['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
        $endDate = $params['end_date'] ?? date('Y-m-d');
        
        $result = $this->auditService->searchLogs([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'action_type' => $params['action_type'] ?? null,
            'user_id' => $params['user_id'] ?? null
        ], 1000);
        
        return $result;
    }

    // ========== EXPORT METHODS ==========

    private function exportToCsv(string $type, array $data): array
    {
        $output = fopen('php://temp', 'r+');
        
        // Flatten data for CSV
        $rows = $this->flattenForExport($data);
        
        if (!empty($rows)) {
            // Headers
            fputcsv($output, array_keys($rows[0]));
            
            // Data
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return [
            'filename' => $type . '_' . date('Y-m-d_His') . '.csv',
            'content' => base64_encode($csv),
            'mime_type' => 'text/csv'
        ];
    }

    private function exportToJson(string $type, array $data): array
    {
        return [
            'filename' => $type . '_' . date('Y-m-d_His') . '.json',
            'content' => base64_encode(json_encode($data, JSON_PRETTY_PRINT)),
            'mime_type' => 'application/json'
        ];
    }

    private function exportToPdf(string $type, array $data, array $params): array
    {
        // Placeholder - would use TCPDF or similar
        return [
            'filename' => $type . '_' . date('Y-m-d_His') . '.pdf',
            'error' => 'PDF export not yet implemented'
        ];
    }

    private function exportToXlsx(string $type, array $data): array
    {
        // Placeholder - would use PhpSpreadsheet
        return [
            'filename' => $type . '_' . date('Y-m-d_His') . '.xlsx',
            'error' => 'Excel export not yet implemented'
        ];
    }

    private function flattenForExport(array $data): array
    {
        // Simple flattening for common report structures
        if (isset($data['daily_data'])) {
            return $data['daily_data'];
        }
        
        if (isset($data['summary'])) {
            return $data['summary'];
        }
        
        if (isset($data['entries'])) {
            return $data['entries'];
        }
        
        if (isset($data['providers'])) {
            return $data['providers'];
        }
        
        // If it's already a flat array
        if (!empty($data) && isset($data[0]) && is_array($data[0])) {
            return $data;
        }
        
        return [$data];
    }

    // ========== CACHE METHODS ==========

    private function getFromCache(string $key): ?array
    {
        if (!isset(self::$cache[$key])) {
            return null;
        }
        
        $cached = self::$cache[$key];
        
        if ($cached['expires_at'] < time()) {
            unset(self::$cache[$key]);
            return null;
        }
        
        return $cached['data'];
    }

    private function setCache(string $key, array $data): void
    {
        self::$cache[$key] = [
            'data' => $data,
            'expires_at' => time() + self::CACHE_TTL
        ];
    }
}
