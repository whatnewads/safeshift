<?php
/**
 * DashboardstatsViewModel - Business logic layer for all dashboards
 *
 * Handles: admin/manager/clinician dashboards, audit logs, compliance, QA reviews
 * Security: Role-based access, CSRF validation, input sanitization, PHI audit logging
 *
 * @package SafeShift\ViewModel
 */

namespace ViewModel;

use ViewModel\Core\BaseViewModel;
use ViewModel\Core\ApiResponse;
use Core\Services\DashboardStatsService;
use Core\Services\AuditService;
use Core\Services\DashboardLogger;
use Core\Repositories\PatientRepository;
use Core\Repositories\EncounterRepository;
use Core\Repositories\UserRepository;
use Core\Repositories\AuditLogRepository;
use Exception;
use PDO;

// Ensure DashboardLogger is available
require_once __DIR__ . '/../core/Services/DashboardLogger.php';

class DashboardstatsViewModel extends BaseViewModel
{
    /** @var DashboardStatsService|null Dashboard stats service */
    private ?DashboardStatsService $statsService;
    
    /** @var PatientRepository Patient repository */
    private PatientRepository $patientRepo;
    
    /** @var EncounterRepository Encounter repository */
    private EncounterRepository $encounterRepo;
    
    /** @var UserRepository User repository */
    private UserRepository $userRepo;
    
    /** @var AuditLogRepository Audit log repository */
    private AuditLogRepository $auditLogRepo;
    
    /** @var DashboardLogger Dashboard logger for metrics tracking */
    private DashboardLogger $dashboardLogger;
    
    /** @var array Role-based dashboard access mapping */
    private const ROLE_DASHBOARD_MAP = [
        'tadmin' => '/dashboards/tadmin/',
        'cadmin' => '/dashboards/cadmin/',
        'Admin' => '/dashboards/dashboard_admin.php',
        'pclinician' => '/dashboards/pclinician/',
        'dclinician' => '/dashboards/dclinician/',
        '1clinician' => '/dashboards/1clinician/',
        'Manager' => '/dashboards/dashboard_manager.php',
        'QA' => '/dashboards/qa-review-mobile.php',
        'Employee' => '/dashboards/employee/',
        'Employer' => '/dashboards/employer/'
    ];
    
    /** @var array Dashboard permission requirements */
    private const DASHBOARD_PERMISSIONS = [
        'admin' => ['Admin', 'cadmin', 'tadmin'],
        'manager' => ['Manager', 'Admin', 'dclinician'],
        'clinician' => ['1clinician', 'dclinician', 'pclinician', 'Admin', 'Manager'],
        '1clinician' => ['1clinician'],
        'dclinician' => ['dclinician'],
        'pclinician' => ['pclinician'],
        'tadmin' => ['tadmin'],
        'audit_logs' => ['Admin', 'cadmin', 'tadmin'],
        'compliance' => ['Admin', 'Manager', 'pclinician', 'cadmin', 'tadmin'],
        'qa_review' => ['Manager', 'Admin', 'QA', 'dclinician'],
        'regulatory' => ['Admin', 'pclinician', 'cadmin']
    ];

    /**
     * Constructor with dependency injection
     *
     * @param DashboardStatsService|null $statsService
     * @param PatientRepository|null $patientRepo
     * @param EncounterRepository|null $encounterRepo
     * @param UserRepository|null $userRepo
     * @param AuditLogRepository|null $auditLogRepo
     * @param AuditService|null $auditService
     * @param PDO|null $pdo
     * @param string|null $logPath
     */
    public function __construct(
        ?DashboardStatsService $statsService = null,
        ?PatientRepository $patientRepo = null,
        ?EncounterRepository $encounterRepo = null,
        ?UserRepository $userRepo = null,
        ?AuditLogRepository $auditLogRepo = null,
        ?AuditService $auditService = null,
        ?PDO $pdo = null,
        ?string $logPath = null
    ) {
        parent::__construct($auditService, $pdo, $logPath);
        
        $this->statsService = $statsService;
        $this->patientRepo = $patientRepo ?? new PatientRepository($this->pdo);
        $this->encounterRepo = $encounterRepo ?? new EncounterRepository($this->pdo);
        $this->userRepo = $userRepo ?? new UserRepository($this->pdo);
        $this->auditLogRepo = $auditLogRepo ?? new AuditLogRepository($this->pdo);
        $this->dashboardLogger = DashboardLogger::getInstance();
    }

    // ========== AUTHENTICATION & AUTHORIZATION ==========

    /**
     * Validate the current user session
     */
    public function validateSession(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            return false;
        }
        if (!isset($_SESSION['user']) || !isset($_SESSION['user']['user_id'])) {
            return false;
        }
        if (isset($_SESSION['last_activity'])) {
            $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 1200;
            if ((time() - $_SESSION['last_activity']) > $timeout) {
                $this->logAuditEvent('session_timeout', ['user_id' => $_SESSION['user']['user_id'] ?? 'unknown']);
                return false;
            }
        }
        $_SESSION['last_activity'] = time();
        return true;
    }

    /**
     * Check if current user has permission for specific dashboard type
     */
    public function checkDashboardPermission(string $dashboardType): bool
    {
        if (!$this->validateSession()) {
            return false;
        }
        $dashboardType = $this->sanitizeString($dashboardType);
        $userRole = $this->getCurrentUserRole();
        if (!$userRole || !isset(self::DASHBOARD_PERMISSIONS[$dashboardType])) {
            return false;
        }
        $hasPermission = in_array($userRole, self::DASHBOARD_PERMISSIONS[$dashboardType]);
        if (!$hasPermission) {
            $this->logAuditEvent('dashboard_permission_denied', [
                'dashboard_type' => $dashboardType,
                'user_role' => $userRole,
                'user_id' => $_SESSION['user']['user_id'] ?? 'unknown'
            ]);
        }
        return $hasPermission;
    }

    /**
     * Get the appropriate dashboard URL for current user's role
     */
    public function getRoleBasedDashboard(): string
    {
        if (!$this->validateSession()) {
            return '/login';
        }
        $userRole = $this->getCurrentUserRole();
        if (!$userRole) {
            return '/login';
        }
        $dashboardUrl = self::ROLE_DASHBOARD_MAP[$userRole] ?? '/dashboard';
        $this->logAuditEvent('role_based_dashboard_redirect', [
            'user_role' => $userRole,
            'dashboard_url' => $dashboardUrl,
            'user_id' => $_SESSION['user']['user_id'] ?? 'unknown'
        ]);
        return $dashboardUrl;
    }

    /**
     * Validate a CSRF token
     */
    public function validateCSRFToken(string $token): bool
    {
        $token = $this->sanitizeString($token);
        $tokenName = defined('CSRF_TOKEN_NAME') ? CSRF_TOKEN_NAME : 'csrf_token';
        if (!isset($_SESSION[$tokenName])) {
            $this->logAuditEvent('csrf_validation_failed', ['reason' => 'no_session_token']);
            return false;
        }
        $isValid = hash_equals($_SESSION[$tokenName], $token);
        if (!$isValid) {
            $this->logAuditEvent('csrf_validation_failed', [
                'reason' => 'token_mismatch',
                'user_id' => $_SESSION['user']['user_id'] ?? 'unknown'
            ]);
        }
        return $isValid;
    }

    // ========== ADMIN DASHBOARD METHODS ==========

    /**
     * Get comprehensive admin dashboard data
     */
    public function getAdminDashboardData(): array
    {
        $startTime = microtime(true);
        $userId = $_SESSION['user']['user_id'] ?? 0;
        
        if (!$this->validateSession()) {
            $this->dashboardLogger->logError('getAdminDashboardData', 'Invalid session', []);
            return ['success' => false, 'errors' => ['session' => 'Invalid session']];
        }
        if (!$this->checkDashboardPermission('admin')) {
            $this->dashboardLogger->logError('getAdminDashboardData', 'Permission denied', ['user_id' => $userId]);
            return ['success' => false, 'errors' => ['permission' => 'Access denied']];
        }
        try {
            $this->dashboardLogger->logMetricRequest(DashboardLogger::DASH_ADMIN, 'full_dashboard', (int)$userId);
            $this->logAuditEvent('admin_dashboard_access', ['user_id' => $userId]);
            
            $result = [
                'success' => true,
                'data' => [
                    'system_stats' => $this->getSystemStats(),
                    'user_management' => $this->getUserManagementData(),
                    'audit_summary' => $this->getAuditSummary()
                ],
                'timestamp' => date('c')
            ];
            
            $this->dashboardLogger->logDashboardLoad(DashboardLogger::DASH_ADMIN, (int)$userId, [
                'system_stats', 'user_management', 'audit_summary'
            ]);
            
            return $result;
        } catch (Exception $e) {
            $this->logError('getAdminDashboardData', $e);
            $this->dashboardLogger->logError('getAdminDashboardData', $e->getMessage(), [
                'user_id' => $userId,
            ]);
            return ['success' => false, 'errors' => ['system' => 'Failed to load admin dashboard']];
        }
    }

    /**
     * Get system-wide statistics
     */
    public function getSystemStats(): array
    {
        try {
            // Get user statistics
            $totalUsers = $this->countUsers();
            $activeSessions = $this->countActiveSessions();
            $dailyLogins = $this->countDailyLogins();
            
            // Get encounter/patient statistics for today
            $todayStart = date('Y-m-d 00:00:00');
            $todayEnd = date('Y-m-d 23:59:59');
            
            $encountersToday = $this->encounterRepo->countByDateRange($todayStart, $todayEnd);
            
            return [
                'total_users' => $totalUsers,
                'active_sessions' => $activeSessions,
                'daily_logins' => $dailyLogins,
                'encounters_today' => $encountersToday,
                'system_uptime' => '99.9%',
                'database_size' => $this->getDatabaseSize(),
                'last_backup' => $this->getLastBackupDate(),
                'pending_tasks' => $this->countPendingTasks()
            ];
        } catch (Exception $e) {
            $this->logError('getSystemStats', $e);
            return [
                'total_users' => 0, 'active_sessions' => 0, 'daily_logins' => 0,
                'encounters_today' => 0, 'system_uptime' => 'N/A', 'database_size' => 'N/A',
                'last_backup' => 'N/A', 'pending_tasks' => 0
            ];
        }
    }

    /**
     * Get user management data for admin dashboard
     */
    public function getUserManagementData(): array
    {
        try {
            // Get user counts by status
            $activeUsers = $this->userRepo->countByStatus('active');
            $inactiveUsers = $this->userRepo->countByStatus('inactive');
            
            // Get users by role
            $usersByRole = $this->getUsersByRole();
            
            // Get recent registrations (last 7 days)
            $recentUsers = $this->userRepo->findRecentlyCreated(7);
            
            // Count locked accounts
            $lockedAccounts = $this->userRepo->countByStatus('locked');
            
            return [
                'total_active_users' => $activeUsers,
                'total_inactive_users' => $inactiveUsers,
                'users_by_role' => $usersByRole,
                'recent_registrations' => array_map(function($user) {
                    return [
                        'user_id' => $user['user_id'] ?? $user['id'] ?? null,
                        'username' => $user['username'] ?? '',
                        'email' => $user['email'] ?? '',
                        'role' => $user['primary_role'] ?? $user['role'] ?? '',
                        'created_at' => $user['created_at'] ?? ''
                    ];
                }, $recentUsers),
                'locked_accounts' => $lockedAccounts,
                'password_expiring_soon' => $this->countPasswordsExpiringSoon()
            ];
        } catch (Exception $e) {
            $this->logError('getUserManagementData', $e);
            return [
                'total_active_users' => 0, 'total_inactive_users' => 0, 'users_by_role' => [],
                'recent_registrations' => [], 'locked_accounts' => 0, 'password_expiring_soon' => 0
            ];
        }
    }

    /**
     * Get audit log summary for admin dashboard
     */
    public function getAuditSummary(): array
    {
        try {
            $todayStart = date('Y-m-d 00:00:00');
            $weekStart = date('Y-m-d 00:00:00', strtotime('-7 days'));
            $now = date('Y-m-d H:i:s');
            
            // Get audit event counts
            $eventsToday = $this->auditLogRepo->countByDateRange($todayStart, $now);
            $eventsWeek = $this->auditLogRepo->countByDateRange($weekStart, $now);
            
            // Get events by type
            $eventsByType = $this->auditLogRepo->countByType($todayStart, $now);
            
            // Get events by severity
            $eventsBySeverity = $this->auditLogRepo->countBySeverity($todayStart, $now);
            
            // Get recent critical events
            $criticalEvents = $this->auditLogRepo->findBySeverity('critical', 10);
            
            return [
                'total_events_today' => $eventsToday,
                'total_events_week' => $eventsWeek,
                'events_by_type' => $eventsByType,
                'events_by_severity' => $eventsBySeverity,
                'recent_critical_events' => array_map(function($event) {
                    return [
                        'id' => $event['id'] ?? $event['audit_id'] ?? null,
                        'action' => $event['action'] ?? $event['event_type'] ?? '',
                        'user_id' => $event['user_id'] ?? null,
                        'timestamp' => $event['timestamp'] ?? $event['created_at'] ?? '',
                        'details' => $event['details'] ?? $event['description'] ?? ''
                    ];
                }, $criticalEvents)
            ];
        } catch (Exception $e) {
            $this->logError('getAuditSummary', $e);
            return [
                'total_events_today' => 0, 'total_events_week' => 0, 'events_by_type' => [],
                'events_by_severity' => [], 'recent_critical_events' => []
            ];
        }
    }

    // ========== MANAGER DASHBOARD METHODS ==========

    /**
     * Get comprehensive manager dashboard data
     */
    public function getManagerDashboardData(): array
    {
        $startTime = microtime(true);
        $userId = $_SESSION['user']['user_id'] ?? 0;
        
        if (!$this->validateSession()) {
            $this->dashboardLogger->logError('getManagerDashboardData', 'Invalid session', []);
            return ['success' => false, 'errors' => ['session' => 'Invalid session']];
        }
        if (!$this->checkDashboardPermission('manager')) {
            $this->dashboardLogger->logError('getManagerDashboardData', 'Permission denied', ['user_id' => $userId]);
            return ['success' => false, 'errors' => ['permission' => 'Access denied']];
        }
        try {
            $this->dashboardLogger->logMetricRequest(DashboardLogger::DASH_MANAGER, 'full_dashboard', (int)$userId);
            $this->logAuditEvent('manager_dashboard_access', ['user_id' => $userId]);
            
            $result = [
                'success' => true,
                'data' => [
                    'team_performance' => $this->getTeamPerformanceStats(),
                    'compliance_overview' => $this->getComplianceOverview(),
                    'shift_coverage' => $this->getShiftCoverage()
                ],
                'timestamp' => date('c')
            ];
            
            $this->dashboardLogger->logDashboardLoad(DashboardLogger::DASH_MANAGER, (int)$userId, [
                'team_performance', 'compliance_overview', 'shift_coverage'
            ]);
            
            return $result;
        } catch (Exception $e) {
            $this->logError('getManagerDashboardData', $e);
            $this->dashboardLogger->logError('getManagerDashboardData', $e->getMessage(), [
                'user_id' => $userId,
            ]);
            return ['success' => false, 'errors' => ['system' => 'Failed to load manager dashboard']];
        }
    }

    /**
     * Get team performance statistics
     */
    public function getTeamPerformanceStats(): array
    {
        try {
            // Get clinician users
            $clinicianRoles = ['1clinician', 'dclinician', 'pclinician'];
            $totalStaff = 0;
            foreach ($clinicianRoles as $role) {
                $totalStaff += $this->userRepo->countByRole($role);
            }
            
            // Get encounter statistics
            $todayStart = date('Y-m-d 00:00:00');
            $todayEnd = date('Y-m-d 23:59:59');
            $weekStart = date('Y-m-d 00:00:00', strtotime('monday this week'));
            
            $encountersToday = $this->encounterRepo->countByDateRange($todayStart, $todayEnd);
            $encountersWeek = $this->encounterRepo->countByDateRange($weekStart, $todayEnd);
            
            // Get staff performance (top 10)
            $staffPerformance = $this->getStaffPerformanceMetrics();
            
            return [
                'total_staff' => $totalStaff,
                'average_response_time' => $this->calculateAverageResponseTime(),
                'encounters_completed_today' => $encountersToday,
                'encounters_completed_week' => $encountersWeek,
                'staff_performance' => $staffPerformance,
                'top_performers' => array_slice($staffPerformance, 0, 5)
            ];
        } catch (Exception $e) {
            $this->logError('getTeamPerformanceStats', $e);
            return [
                'total_staff' => 0, 'average_response_time' => 0, 'encounters_completed_today' => 0,
                'encounters_completed_week' => 0, 'staff_performance' => [], 'top_performers' => []
            ];
        }
    }

    /**
     * Get compliance overview for manager dashboard
     */
    public function getComplianceOverview(): array
    {
        try {
            // Calculate compliance metrics from encounter data
            $totalEncounters = $this->encounterRepo->countByDateRange(
                date('Y-m-d 00:00:00', strtotime('-30 days')),
                date('Y-m-d 23:59:59')
            );
            
            $signedEncounters = $this->encounterRepo->countByStatus('signed');
            $completedEncounters = $this->encounterRepo->countByStatus('completed');
            
            $documentationRate = $totalEncounters > 0
                ? round(($signedEncounters / $totalEncounters) * 100, 1)
                : 0;
            
            return [
                'overall_compliance_rate' => $documentationRate,
                'training_compliance' => $this->calculateTrainingCompliance(),
                'certification_compliance' => $this->calculateCertificationCompliance(),
                'documentation_compliance' => $documentationRate,
                'total_encounters' => $totalEncounters,
                'signed_encounters' => $signedEncounters,
                'completed_encounters' => $completedEncounters
            ];
        } catch (Exception $e) {
            $this->logError('getComplianceOverview', $e);
            return [
                'overall_compliance_rate' => 0, 'training_compliance' => 0,
                'certification_compliance' => 0, 'documentation_compliance' => 0
            ];
        }
    }

    /**
     * Get shift coverage data
     */
    public function getShiftCoverage(): array
    {
        try {
            $currentHour = (int)date('H');
            $currentShift = $this->determineCurrentShift($currentHour);
            $nextShift = $this->determineNextShift($currentShift);
            
            // Get active users for current shift
            $activeUsers = $this->countActiveSessions();
            
            return [
                'current_shift' => [
                    'name' => $currentShift['name'],
                    'start_time' => $currentShift['start'],
                    'end_time' => $currentShift['end'],
                    'staff_scheduled' => $currentShift['scheduled'] ?? 0,
                    'staff_present' => $activeUsers
                ],
                'next_shift' => [
                    'name' => $nextShift['name'],
                    'start_time' => $nextShift['start'],
                    'staff_scheduled' => $nextShift['scheduled'] ?? 0
                ],
                'weekly_coverage' => $this->getWeeklyCoverageStats()
            ];
        } catch (Exception $e) {
            $this->logError('getShiftCoverage', $e);
            return [
                'current_shift' => ['name' => '', 'staff_scheduled' => 0, 'staff_present' => 0],
                'next_shift' => ['name' => '', 'staff_scheduled' => 0], 'weekly_coverage' => []
            ];
        }
    }

    // ========== CLINICIAN DASHBOARD METHODS ==========

    /**
     * Get clinician dashboard data for a specific user
     */
    public function getClinicianDashboardData(int $userId): array
    {
        $startTime = microtime(true);
        
        if (!$this->validateSession()) {
            $this->dashboardLogger->logError('getClinicianDashboardData', 'Invalid session', []);
            return ['success' => false, 'errors' => ['session' => 'Invalid session']];
        }
        if (!$this->checkDashboardPermission('clinician')) {
            $this->dashboardLogger->logError('getClinicianDashboardData', 'Permission denied', ['user_id' => $userId]);
            return ['success' => false, 'errors' => ['permission' => 'Access denied']];
        }
        $userId = $this->sanitizeInteger($userId);
        try {
            $this->dashboardLogger->logMetricRequest(DashboardLogger::DASH_CLINICAL, 'full_dashboard', $userId);
            $this->logAuditEvent('clinician_dashboard_access', ['clinician_id' => $userId]);
            
            $result = [
                'success' => true,
                'data' => [
                    'patient_load' => $this->getPatientLoadStats($userId),
                    'encounter_stats' => $this->getEncounterStats($userId),
                    'kpi_metrics' => $this->getKPIMetrics($userId)
                ],
                'timestamp' => date('c')
            ];
            
            $this->dashboardLogger->logDashboardLoad(DashboardLogger::DASH_CLINICAL, $userId, [
                'patient_load', 'encounter_stats', 'kpi_metrics'
            ]);
            
            return $result;
        } catch (Exception $e) {
            $this->logError('getClinicianDashboardData', $e, ['user_id' => $userId]);
            $this->dashboardLogger->logError('getClinicianDashboardData', $e->getMessage(), [
                'user_id' => $userId,
            ]);
            return ['success' => false, 'errors' => ['system' => 'Failed to load clinician dashboard']];
        }
    }

    /**
     * Get patient load statistics for a clinician
     */
    public function getPatientLoadStats(int $userId): array
    {
        $userId = $this->sanitizeInteger($userId);
        
        try {
            $todayStart = date('Y-m-d 00:00:00');
            $todayEnd = date('Y-m-d 23:59:59');
            $weekStart = date('Y-m-d 00:00:00', strtotime('monday this week'));
            $monthStart = date('Y-m-01 00:00:00');
            
            // Get encounter counts for this clinician
            $patientsToday = $this->encounterRepo->countByProviderAndDateRange($userId, $todayStart, $todayEnd);
            $patientsWeek = $this->encounterRepo->countByProviderAndDateRange($userId, $weekStart, $todayEnd);
            $patientsMonth = $this->encounterRepo->countByProviderAndDateRange($userId, $monthStart, $todayEnd);
            
            // Get pending encounters in queue
            $currentQueue = $this->encounterRepo->countPendingByProvider($userId);
            
            // Calculate average
            $daysInMonth = (int)date('j');
            $avgPerDay = $daysInMonth > 0 ? round($patientsMonth / $daysInMonth, 1) : 0;
            
            // Determine trend
            $lastWeekStart = date('Y-m-d 00:00:00', strtotime('-14 days'));
            $lastWeekEnd = date('Y-m-d 23:59:59', strtotime('-7 days'));
            $lastWeekPatients = $this->encounterRepo->countByProviderAndDateRange($userId, $lastWeekStart, $lastWeekEnd);
            
            $trend = 'stable';
            if ($patientsWeek > $lastWeekPatients * 1.1) {
                $trend = 'increasing';
            } elseif ($patientsWeek < $lastWeekPatients * 0.9) {
                $trend = 'decreasing';
            }
            
            return [
                'patients_today' => $patientsToday,
                'patients_this_week' => $patientsWeek,
                'patients_this_month' => $patientsMonth,
                'current_queue' => $currentQueue,
                'average_per_day' => $avgPerDay,
                'trend' => $trend
            ];
        } catch (Exception $e) {
            $this->logError('getPatientLoadStats', $e, ['user_id' => $userId]);
            return [
                'patients_today' => 0, 'patients_this_week' => 0, 'patients_this_month' => 0,
                'current_queue' => 0, 'average_per_day' => 0, 'trend' => 'stable'
            ];
        }
    }

    /**
     * Get encounter statistics for a clinician
     */
    public function getEncounterStats(int $userId, string $dateRange = 'today'): array
    {
        $userId = $this->sanitizeInteger($userId);
        $dateRange = $this->sanitizeString($dateRange);
        $dates = $this->processDateRange($dateRange);
        
        try {
            $startDate = $dates['start'] . ' 00:00:00';
            $endDate = $dates['end'] . ' 23:59:59';
            
            // Get total encounters for this provider in date range
            $totalEncounters = $this->encounterRepo->countByProviderAndDateRange($userId, $startDate, $endDate);
            
            // Get encounters by status
            $completedEncounters = $this->encounterRepo->countByProviderStatusAndDateRange($userId, 'completed', $startDate, $endDate);
            $pendingEncounters = $this->encounterRepo->countByProviderStatusAndDateRange($userId, 'pending', $startDate, $endDate);
            
            // Get encounters by type
            $encountersByType = $this->encounterRepo->countByProviderAndType($userId, $startDate, $endDate);
            
            // Calculate average duration
            $avgDuration = $this->encounterRepo->getAverageDurationByProvider($userId, $startDate, $endDate);
            
            return [
                'total_encounters' => $totalEncounters,
                'completed_encounters' => $completedEncounters,
                'pending_encounters' => $pendingEncounters,
                'encounters_by_type' => $encountersByType,
                'average_duration' => round($avgDuration, 1),
                'date_range' => $dates
            ];
        } catch (Exception $e) {
            $this->logError('getEncounterStats', $e, ['user_id' => $userId, 'date_range' => $dateRange]);
            return [
                'total_encounters' => 0, 'completed_encounters' => 0, 'pending_encounters' => 0,
                'encounters_by_type' => [], 'average_duration' => 0, 'date_range' => $dates
            ];
        }
    }

    /**
     * Get KPI metrics for a clinician
     */
    public function getKPIMetrics(int $userId): array
    {
        $userId = $this->sanitizeInteger($userId);
        
        try {
            $monthStart = date('Y-m-01 00:00:00');
            $monthEnd = date('Y-m-d 23:59:59');
            
            // Get documentation completion rate
            $totalEncounters = $this->encounterRepo->countByProviderAndDateRange($userId, $monthStart, $monthEnd);
            $signedEncounters = $this->encounterRepo->countByProviderStatusAndDateRange($userId, 'signed', $monthStart, $monthEnd);
            $docCompletionRate = $totalEncounters > 0 ? round(($signedEncounters / $totalEncounters) * 100, 1) : 0;
            
            // Get last month's rate for trend
            $lastMonthStart = date('Y-m-01 00:00:00', strtotime('-1 month'));
            $lastMonthEnd = date('Y-m-t 23:59:59', strtotime('-1 month'));
            $lastMonthTotal = $this->encounterRepo->countByProviderAndDateRange($userId, $lastMonthStart, $lastMonthEnd);
            $lastMonthSigned = $this->encounterRepo->countByProviderStatusAndDateRange($userId, 'signed', $lastMonthStart, $lastMonthEnd);
            $lastMonthRate = $lastMonthTotal > 0 ? round(($lastMonthSigned / $lastMonthTotal) * 100, 1) : 0;
            
            $docTrend = $this->calculateTrend($docCompletionRate, $lastMonthRate);
            
            return [
                'response_time' => [
                    'current' => $this->calculateAverageResponseTimeForProvider($userId),
                    'target' => 15,
                    'trend' => 'stable'
                ],
                'documentation_completion' => [
                    'current' => $docCompletionRate,
                    'target' => 100,
                    'trend' => $docTrend
                ],
                'patient_satisfaction' => [
                    'current' => 0, // Would require survey data
                    'target' => 90,
                    'trend' => 'stable'
                ],
                'reports_on_time' => [
                    'current' => $docCompletionRate, // Using same metric for now
                    'target' => 100,
                    'trend' => $docTrend
                ]
            ];
        } catch (Exception $e) {
            $this->logError('getKPIMetrics', $e, ['user_id' => $userId]);
            return [
                'response_time' => ['current' => 0, 'target' => 0, 'trend' => 'stable'],
                'documentation_completion' => ['current' => 0, 'target' => 100, 'trend' => 'stable'],
                'patient_satisfaction' => ['current' => 0, 'target' => 90, 'trend' => 'stable'],
                'reports_on_time' => ['current' => 0, 'target' => 100, 'trend' => 'stable']
            ];
        }
    }

    // ========== AUDIT LOGS METHODS ==========

    /**
     * Get audit logs with filters
     */
    public function getAuditLogs(array $filters): array
    {
        $startTime = microtime(true);
        $userId = $_SESSION['user']['user_id'] ?? 0;
        
        if (!$this->validateSession()) {
            return ['success' => false, 'errors' => ['session' => 'Invalid session']];
        }
        if (!$this->checkDashboardPermission('audit_logs')) {
            return ['success' => false, 'errors' => ['permission' => 'Access denied']];
        }
        $validatedFilters = $this->validateAuditFilters($filters);
        if (isset($validatedFilters['errors']) && !empty($validatedFilters['errors'])) {
            return ['success' => false, 'errors' => $validatedFilters['errors']];
        }
        try {
            $this->dashboardLogger->logMetricRequest('audit_logs', 'list', (int)$userId, $validatedFilters);
            $this->logAuditEvent('audit_logs_accessed', ['filters' => $validatedFilters]);
            
            // TODO: Replace with actual repository calls
            $this->dashboardLogger->logTodoHit(
                'DashboardstatsViewModel::getAuditLogs',
                'Replace with actual repository calls for audit logs',
                ['filters' => $validatedFilters]
            );
            $logs = [];
            
            $this->dashboardLogger->logMetricCalculation('audit_logs_list', [
                'query_count' => 1,
                'row_count' => count($logs),
            ], microtime(true) - $startTime);
            
            return [
                'success' => true,
                'data' => $this->formatAuditLogsForDisplay($logs),
                'total' => count($logs),
                'filters' => $validatedFilters,
                'timestamp' => date('c')
            ];
        } catch (Exception $e) {
            $this->logError('getAuditLogs', $e, ['filters' => $filters]);
            $this->dashboardLogger->logError('getAuditLogs', $e->getMessage(), ['filters' => $filters]);
            return ['success' => false, 'errors' => ['system' => 'Failed to retrieve audit logs']];
        }
    }

    /**
     * Validate audit log filters
     */
    public function validateAuditFilters(array $filters): array
    {
        $validated = [];
        $errors = [];
        if (!empty($filters['start_date'])) {
            if ($this->isValidDate($filters['start_date'])) {
                $validated['start_date'] = $filters['start_date'];
            } else {
                $errors['start_date'] = 'Invalid start date format';
            }
        }
        if (!empty($filters['end_date'])) {
            if ($this->isValidDate($filters['end_date'])) {
                $validated['end_date'] = $filters['end_date'];
            } else {
                $errors['end_date'] = 'Invalid end date format';
            }
        }
        if (!empty($filters['user_id'])) {
            $validated['user_id'] = $this->sanitizeInteger($filters['user_id']);
        }
        if (!empty($filters['action_type'])) {
            $allowedActions = ['login', 'logout', 'view', 'create', 'update', 'delete', 'export'];
            $actionType = $this->sanitizeString($filters['action_type']);
            if (in_array($actionType, $allowedActions)) {
                $validated['action_type'] = $actionType;
            }
        }
        $validated['page'] = max(1, $this->sanitizeInteger($filters['page'] ?? 1));
        $validated['per_page'] = min(100, max(10, $this->sanitizeInteger($filters['per_page'] ?? 50)));
        if (!empty($errors)) {
            $validated['errors'] = $errors;
        }
        return $validated;
    }

    /**
     * Format audit logs for display
     */
    public function formatAuditLogsForDisplay(array $logs): array
    {
        $formatted = [];
        foreach ($logs as $log) {
            $formatted[] = [
                'id' => (int)($log['id'] ?? 0),
                'timestamp' => $this->formatDateTime($log['timestamp'] ?? null),
                'user_id' => (int)($log['user_id'] ?? 0),
                'username' => htmlspecialchars($log['username'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'),
                'action' => htmlspecialchars($log['action'] ?? '', ENT_QUOTES, 'UTF-8'),
                'details' => htmlspecialchars($log['details'] ?? '', ENT_QUOTES, 'UTF-8'),
                'ip_address' => htmlspecialchars($log['ip_address'] ?? '', ENT_QUOTES, 'UTF-8')
            ];
        }
        return $formatted;
    }

    // ========== COMPLIANCE MONITOR METHODS ==========

    /**
     * Get comprehensive compliance data
     */
    public function getComplianceData(): array
    {
        if (!$this->validateSession()) {
            return ['success' => false, 'errors' => ['session' => 'Invalid session']];
        }
        if (!$this->checkDashboardPermission('compliance')) {
            return ['success' => false, 'errors' => ['permission' => 'Access denied']];
        }
        try {
            $this->logAuditEvent('compliance_data_accessed', ['user_id' => $_SESSION['user']['user_id'] ?? 'unknown']);
            return [
                'success' => true,
                'data' => [
                    'training_status' => $this->getTrainingStatus(),
                    'certification_expiry' => $this->getCertificationExpiry(),
                    'alerts' => $this->getComplianceAlerts()
                ],
                'timestamp' => date('c')
            ];
        } catch (Exception $e) {
            $this->logError('getComplianceData', $e);
            return ['success' => false, 'errors' => ['system' => 'Failed to load compliance data']];
        }
    }

    /**
     * Get training status for a specific user or all users
     */
    public function getTrainingStatus(int $userId = null): array
    {
        try {
            // Query training records from database
            $query = "SELECT t.*, u.username, u.first_name, u.last_name
                      FROM training_records t
                      LEFT JOIN users u ON t.user_id = u.user_id
                      WHERE 1=1";
            $params = [];
            
            if ($userId !== null) {
                $query .= " AND t.user_id = :user_id";
                $params['user_id'] = $userId;
            }
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $required = [];
            $completed = [];
            $overdue = [];
            $upcoming = [];
            $now = time();
            
            foreach ($records as $record) {
                $dueDate = strtotime($record['due_date'] ?? '');
                $completedDate = $record['completed_at'] ?? null;
                
                if ($completedDate) {
                    $completed[] = $record;
                } elseif ($dueDate && $dueDate < $now) {
                    $overdue[] = $record;
                } elseif ($dueDate && $dueDate <= strtotime('+30 days')) {
                    $upcoming[] = $record;
                } else {
                    $required[] = $record;
                }
            }
            
            $totalRequired = count($required) + count($completed) + count($overdue) + count($upcoming);
            $complianceRate = $totalRequired > 0 ? round((count($completed) / $totalRequired) * 100, 1) : 0;
            
            // Find next due date
            $nextDue = null;
            $allDueDates = array_merge($required, $upcoming);
            usort($allDueDates, function($a, $b) {
                return strtotime($a['due_date'] ?? '') <=> strtotime($b['due_date'] ?? '');
            });
            if (!empty($allDueDates)) {
                $nextDue = $allDueDates[0]['due_date'] ?? null;
            }
            
            return [
                'required_trainings' => $required,
                'completed_trainings' => $completed,
                'overdue_trainings' => $overdue,
                'upcoming_trainings' => $upcoming,
                'compliance_rate' => $complianceRate,
                'next_due_date' => $nextDue
            ];
        } catch (Exception $e) {
            $this->logError('getTrainingStatus', $e, ['user_id' => $userId]);
            return [
                'required_trainings' => [], 'completed_trainings' => [], 'overdue_trainings' => [],
                'upcoming_trainings' => [], 'compliance_rate' => 0, 'next_due_date' => null
            ];
        }
    }

    /**
     * Get certification expiry information
     */
    public function getCertificationExpiry(): array
    {
        try {
            $query = "SELECT c.*, u.username, u.first_name, u.last_name
                      FROM certifications c
                      LEFT JOIN users u ON c.user_id = u.user_id
                      ORDER BY c.expiry_date ASC";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute();
            $certs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $expiringSoon = [];
            $expired = [];
            $valid = [];
            $byType = [];
            $now = time();
            $thirtyDaysFromNow = strtotime('+30 days');
            
            foreach ($certs as $cert) {
                $expiryDate = strtotime($cert['expiry_date'] ?? '');
                $certType = $cert['cert_type'] ?? 'Other';
                
                // Group by type
                if (!isset($byType[$certType])) {
                    $byType[$certType] = [];
                }
                $byType[$certType][] = $cert;
                
                // Categorize by expiry status
                if ($expiryDate && $expiryDate < $now) {
                    $expired[] = $cert;
                } elseif ($expiryDate && $expiryDate <= $thirtyDaysFromNow) {
                    $expiringSoon[] = $cert;
                } else {
                    $valid[] = $cert;
                }
            }
            
            return [
                'expiring_soon' => $expiringSoon,
                'expired' => $expired,
                'valid' => $valid,
                'by_type' => $byType,
                'summary' => [
                    'total_certifications' => count($certs),
                    'valid_count' => count($valid),
                    'expiring_30_days' => count($expiringSoon),
                    'expired_count' => count($expired)
                ]
            ];
        } catch (Exception $e) {
            $this->logError('getCertificationExpiry', $e);
            return [
                'expiring_soon' => [], 'expired' => [], 'valid' => [], 'by_type' => [],
                'summary' => ['total_certifications' => 0, 'valid_count' => 0, 'expiring_30_days' => 0]
            ];
        }
    }

    /**
     * Get compliance alerts
     */
    public function getComplianceAlerts(): array
    {
        try {
            $critical = [];
            $warning = [];
            $info = [];
            
            // Check for expired certifications (critical)
            $certExpiry = $this->getCertificationExpiry();
            foreach ($certExpiry['expired'] as $cert) {
                $critical[] = [
                    'type' => 'certification_expired',
                    'message' => ($cert['cert_type'] ?? 'Certification') . ' expired for ' . ($cert['username'] ?? 'Unknown'),
                    'user_id' => $cert['user_id'] ?? null,
                    'expiry_date' => $cert['expiry_date'] ?? null
                ];
            }
            
            // Check for overdue trainings (critical)
            $trainingStatus = $this->getTrainingStatus();
            foreach ($trainingStatus['overdue_trainings'] as $training) {
                $critical[] = [
                    'type' => 'training_overdue',
                    'message' => ($training['training_name'] ?? 'Training') . ' overdue',
                    'user_id' => $training['user_id'] ?? null,
                    'due_date' => $training['due_date'] ?? null
                ];
            }
            
            // Check for expiring soon certifications (warning)
            foreach ($certExpiry['expiring_soon'] as $cert) {
                $warning[] = [
                    'type' => 'certification_expiring',
                    'message' => ($cert['cert_type'] ?? 'Certification') . ' expiring soon for ' . ($cert['username'] ?? 'Unknown'),
                    'user_id' => $cert['user_id'] ?? null,
                    'expiry_date' => $cert['expiry_date'] ?? null
                ];
            }
            
            // Check for upcoming trainings (info)
            foreach ($trainingStatus['upcoming_trainings'] as $training) {
                $info[] = [
                    'type' => 'training_upcoming',
                    'message' => ($training['training_name'] ?? 'Training') . ' due soon',
                    'user_id' => $training['user_id'] ?? null,
                    'due_date' => $training['due_date'] ?? null
                ];
            }
            
            return [
                'critical' => $critical,
                'warning' => $warning,
                'info' => $info,
                'total_count' => count($critical) + count($warning) + count($info)
            ];
        } catch (Exception $e) {
            $this->logError('getComplianceAlerts', $e);
            return ['critical' => [], 'warning' => [], 'info' => [], 'total_count' => 0];
        }
    }

    // ========== QA REVIEW METHODS ==========

    /**
     * Get QA review data with filters
     */
    public function getQAReviewData(array $filters): array
    {
        $startTime = microtime(true);
        $userId = $_SESSION['user']['user_id'] ?? 0;
        
        if (!$this->validateSession()) {
            return ['success' => false, 'errors' => ['session' => 'Invalid session']];
        }
        if (!$this->checkDashboardPermission('qa_review')) {
            return ['success' => false, 'errors' => ['permission' => 'Access denied']];
        }
        try {
            $sanitizedFilters = $this->sanitizeQAFilters($filters);
            $this->dashboardLogger->logMetricRequest('qa_review', 'list', (int)$userId, $sanitizedFilters);
            $this->logAuditEvent('qa_review_data_accessed', ['filters' => $sanitizedFilters]);
            
            // TODO: Replace with actual repository calls
            $this->dashboardLogger->logTodoHit(
                'DashboardstatsViewModel::getQAReviewData',
                'Replace with actual repository calls for QA reviews',
                ['filters' => $sanitizedFilters]
            );
            
            $result = [
                'success' => true,
                'data' => [
                    'pending_reviews' => [], 'completed_reviews' => [],
                    'metrics' => $this->getQAMetrics(), 'filters' => $sanitizedFilters
                ],
                'timestamp' => date('c')
            ];
            
            $this->dashboardLogger->logMetricCalculation('qa_review_data', [
                'query_count' => 1,
            ], microtime(true) - $startTime);
            
            return $result;
        } catch (Exception $e) {
            $this->logError('getQAReviewData', $e, ['filters' => $filters]);
            $this->dashboardLogger->logError('getQAReviewData', $e->getMessage(), ['filters' => $filters]);
            return ['success' => false, 'errors' => ['system' => 'Failed to load QA review data']];
        }
    }

    /**
     * Get QA metrics
     */
    public function getQAMetrics(): array
    {
        try {
            $todayStart = date('Y-m-d 00:00:00');
            $todayEnd = date('Y-m-d 23:59:59');
            $weekStart = date('Y-m-d 00:00:00', strtotime('monday this week'));
            
            // Query QA reviews
            $stmt = $this->pdo->prepare(
                "SELECT * FROM qa_reviews WHERE created_at >= :start"
            );
            $stmt->execute(['start' => $weekStart]);
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $reviewsToday = 0;
            $reviewsWeek = count($reviews);
            $totalScore = 0;
            $passCount = 0;
            $reviewsByType = [];
            
            foreach ($reviews as $review) {
                $createdAt = strtotime($review['created_at'] ?? '');
                if ($createdAt >= strtotime($todayStart)) {
                    $reviewsToday++;
                }
                
                $score = (float)($review['score'] ?? 0);
                $totalScore += $score;
                
                if ($score >= 3) { // Pass threshold
                    $passCount++;
                }
                
                $type = $review['review_type'] ?? 'general';
                if (!isset($reviewsByType[$type])) {
                    $reviewsByType[$type] = 0;
                }
                $reviewsByType[$type]++;
            }
            
            $avgScore = $reviewsWeek > 0 ? round($totalScore / $reviewsWeek, 2) : 0;
            $passRate = $reviewsWeek > 0 ? round(($passCount / $reviewsWeek) * 100, 1) : 0;
            
            return [
                'total_reviews_today' => $reviewsToday,
                'total_reviews_week' => $reviewsWeek,
                'average_score' => $avgScore,
                'pass_rate' => $passRate,
                'reviews_by_type' => $reviewsByType,
                'trending_issues' => $this->getTrendingQAIssues()
            ];
        } catch (Exception $e) {
            $this->logError('getQAMetrics', $e);
            return [
                'total_reviews_today' => 0, 'total_reviews_week' => 0, 'average_score' => 0,
                'pass_rate' => 0, 'reviews_by_type' => [], 'trending_issues' => []
            ];
        }
    }

    /**
     * Submit a QA review
     */
    public function submitQAReview(array $data): array
    {
        if (!$this->validateSession()) {
            return ['success' => false, 'errors' => ['session' => 'Invalid session']];
        }
        if (isset($data['csrf_token']) && !$this->validateCSRFToken($data['csrf_token'])) {
            return ['success' => false, 'errors' => ['csrf' => 'Invalid security token']];
        }
        if (!$this->checkDashboardPermission('qa_review')) {
            return ['success' => false, 'errors' => ['permission' => 'Access denied']];
        }
        $validationErrors = $this->validateQAReviewData($data);
        if (!empty($validationErrors)) {
            return ['success' => false, 'errors' => $validationErrors];
        }
        try {
            $sanitizedData = $this->sanitizeQAReviewData($data);
            // TODO: Replace with actual repository calls
            $reviewId = random_int(1000, 9999);
            $this->logAuditEvent('qa_review_submitted', [
                'review_id' => $reviewId,
                'encounter_id' => $sanitizedData['encounter_id'] ?? null,
                'score' => $sanitizedData['score'] ?? null
            ]);
            return ['success' => true, 'review_id' => $reviewId, 'message' => 'QA review submitted successfully'];
        } catch (Exception $e) {
            $this->logError('submitQAReview', $e, ['encounter_id' => $data['encounter_id'] ?? null]);
            return ['success' => false, 'errors' => ['system' => 'Failed to submit QA review']];
        }
    }

    /**
     * Validate QA review data
     */
    public function validateQAReviewData(array $data): array
    {
        $errors = [];
        if (empty($data['encounter_id'])) {
            $errors['encounter_id'] = 'Encounter ID is required';
        } elseif (!is_numeric($data['encounter_id']) || $data['encounter_id'] < 1) {
            $errors['encounter_id'] = 'Invalid encounter ID';
        }
        if (!isset($data['score'])) {
            $errors['score'] = 'Score is required';
        } elseif (!is_numeric($data['score']) || $data['score'] < 1 || $data['score'] > 5) {
            $errors['score'] = 'Score must be between 1 and 5';
        }
        if (!empty($data['comments']) && strlen($data['comments']) > 2000) {
            $errors['comments'] = 'Comments exceed maximum length';
        }
        return $errors;
    }

    // ========== REGULATORY UPDATES METHODS ==========

    /**
     * Get regulatory updates
     */
    public function getRegulatoryUpdates(): array
    {
        $startTime = microtime(true);
        $userId = $_SESSION['user']['user_id'] ?? 0;
        
        if (!$this->validateSession()) {
            return ['success' => false, 'errors' => ['session' => 'Invalid session']];
        }
        if (!$this->checkDashboardPermission('regulatory')) {
            return ['success' => false, 'errors' => ['permission' => 'Access denied']];
        }
        try {
            $this->dashboardLogger->logMetricRequest('regulatory', 'updates', (int)$userId);
            $this->logAuditEvent('regulatory_updates_accessed', ['user_id' => $userId]);
            
            // TODO: Replace with actual repository calls
            $this->dashboardLogger->logTodoHit(
                'DashboardstatsViewModel::getRegulatoryUpdates',
                'Replace with actual repository calls for regulatory updates',
                []
            );
            
            $result = [
                'success' => true,
                'data' => ['updates' => [], 'categories' => $this->getRegulatoryCategories(), 'unacknowledged_count' => 0],
                'timestamp' => date('c')
            ];
            
            $this->dashboardLogger->logMetricCalculation('regulatory_updates', [
                'query_count' => 0,
            ], microtime(true) - $startTime);
            
            return $result;
        } catch (Exception $e) {
            $this->logError('getRegulatoryUpdates', $e);
            $this->dashboardLogger->logError('getRegulatoryUpdates', $e->getMessage(), []);
            return ['success' => false, 'errors' => ['system' => 'Failed to load regulatory updates']];
        }
    }

    /**
     * Get acknowledgement status for a user
     */
    public function getAcknowledgementStatus(int $userId): array
    {
        $userId = $this->sanitizeInteger($userId);
        
        // TODO: Replace with actual repository calls
        $this->dashboardLogger->logTodoHit(
            'DashboardstatsViewModel::getAcknowledgementStatus',
            'Replace with actual repository calls for acknowledgement status',
            ['user_id' => $userId]
        );
        
        return [
            'acknowledged' => [], 'pending' => [], 'overdue' => [],
            'total_required' => 0, 'total_acknowledged' => 0, 'compliance_rate' => 0
        ];
    }

    /**
     * Acknowledge a regulatory update
     */
    public function acknowledgeUpdate(int $updateId, int $userId): array
    {
        if (!$this->validateSession()) {
            return ['success' => false, 'errors' => ['session' => 'Invalid session']];
        }
        $updateId = $this->sanitizeInteger($updateId);
        $userId = $this->sanitizeInteger($userId);
        if ($updateId < 1 || $userId < 1) {
            return ['success' => false, 'errors' => ['validation' => 'Invalid update or user ID']];
        }
        try {
            // TODO: Replace with actual repository calls
            $this->logAuditEvent('regulatory_update_acknowledged', [
                'update_id' => $updateId, 'acknowledged_by' => $userId
            ]);
            return ['success' => true, 'message' => 'Update acknowledged successfully'];
        } catch (Exception $e) {
            $this->logError('acknowledgeUpdate', $e, ['update_id' => $updateId, 'user_id' => $userId]);
            return ['success' => false, 'errors' => ['system' => 'Failed to acknowledge update']];
        }
    }

    // ========== DATE RANGE & FILTERING METHODS ==========

    /**
     * Process a date range string into start and end dates
     */
    public function processDateRange(string $range): array
    {
        $range = $this->sanitizeString($range);
        $today = date('Y-m-d');
        switch ($range) {
            case 'today':
                return ['start' => $today, 'end' => $today];
            case 'week':
                return ['start' => date('Y-m-d', strtotime('monday this week')), 'end' => date('Y-m-d', strtotime('sunday this week'))];
            case 'month':
                return ['start' => date('Y-m-01'), 'end' => date('Y-m-t')];
            case 'quarter':
                $quarter = ceil(date('n') / 3);
                $startMonth = ($quarter - 1) * 3 + 1;
                return [
                    'start' => date('Y-' . str_pad($startMonth, 2, '0', STR_PAD_LEFT) . '-01'),
                    'end' => date('Y-m-t', strtotime(date('Y') . '-' . str_pad($startMonth + 2, 2, '0', STR_PAD_LEFT) . '-01'))
                ];
            case 'year':
                return ['start' => date('Y-01-01'), 'end' => date('Y-12-31')];
            default:
                return ['start' => $today, 'end' => $today];
        }
    }

    /**
     * Validate a date range
     */
    public function validateDateRange(string $startDate, string $endDate): bool
    {
        if (!$this->isValidDate($startDate) || !$this->isValidDate($endDate)) {
            return false;
        }
        $start = strtotime($startDate);
        $end = strtotime($endDate);
        if ($start === false || $end === false || $start > $end) {
            return false;
        }
        $maxRange = 365 * 24 * 60 * 60;
        return ($end - $start) <= $maxRange;
    }

    /**
     * Apply filters to a data array
     */
    public function applyFilters(array $data, array $filters): array
    {
        if (empty($filters)) {
            return $data;
        }
        return array_filter($data, function ($item) use ($filters) {
            foreach ($filters as $key => $value) {
                if ($value === null || $value === '') continue;
                if (!isset($item[$key])) return false;
                if (is_array($value)) {
                    if (!in_array($item[$key], $value)) return false;
                } else {
                    if ($item[$key] != $value) return false;
                }
            }
            return true;
        });
    }

    // ========== LEGACY COMPATIBILITY METHODS ==========

    /**
     * Get dashboard statistics (legacy compatibility)
     */
    public function getStats(array $input = []): array
    {
        $startTime = microtime(true);
        $userId = $_SESSION['user']['user_id'] ?? 0;
        
        try {
            $this->dashboardLogger->logMetricRequest('stats', 'today_stats', (int)$userId);
            
            if ($this->statsService) {
                $stats = $this->statsService->getTodayStats();
            } else {
                // TODO: statsService not available, using mock data
                $this->dashboardLogger->logTodoHit(
                    'DashboardstatsViewModel::getStats',
                    'statsService not available, returning mock data',
                    []
                );
                $stats = $this->getMockTodayStats();
            }
            
            $this->dashboardLogger->logMetricCalculation('today_stats', [
                'query_count' => 1,
                'row_count' => count($stats),
            ], microtime(true) - $startTime);
            
            return ['success' => true, 'data' => $this->formatStats($stats), 'timestamp' => date('c')];
        } catch (Exception $e) {
            $this->logError('getStats', $e);
            $this->dashboardLogger->logError('getStats', $e->getMessage(), []);
            return ['success' => false, 'error' => 'Failed to retrieve dashboard statistics', 'timestamp' => date('c')];
        }
    }

    /**
     * Get statistics for date range (legacy compatibility)
     */
    public function getStatsForDateRange(array $input): array
    {
        $startTime = microtime(true);
        $userId = $_SESSION['user']['user_id'] ?? 0;
        
        try {
            $errors = $this->validateDateRangeInput($input);
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }
            
            $this->dashboardLogger->logMetricRequest('stats', 'date_range_stats', (int)$userId, [
                'start_date' => $input['start_date'],
                'end_date' => $input['end_date'],
            ]);
            
            if ($this->statsService) {
                $stats = $this->statsService->getStatsForDateRange($input['start_date'], $input['end_date']);
            } else {
                $stats = [];
            }
            
            $this->dashboardLogger->logMetricCalculation('date_range_stats', [
                'query_count' => 1,
                'row_count' => count($stats),
            ], microtime(true) - $startTime);
            
            return [
                'success' => true, 'data' => $stats,
                'period' => ['start' => $input['start_date'], 'end' => $input['end_date']], 'timestamp' => date('c')
            ];
        } catch (Exception $e) {
            $this->logError('getStatsForDateRange', $e);
            $this->dashboardLogger->logError('getStatsForDateRange', $e->getMessage(), [
                'start_date' => $input['start_date'] ?? null,
                'end_date' => $input['end_date'] ?? null,
            ]);
            return ['success' => false, 'error' => 'Failed to retrieve statistics for date range', 'timestamp' => date('c')];
        }
    }

    /**
     * Get real-time patient flow statistics (legacy compatibility)
     */
    public function getPatientFlow(array $input = []): array
    {
        $startTime = microtime(true);
        $userId = $_SESSION['user']['user_id'] ?? 0;
        
        try {
            $this->dashboardLogger->logMetricRequest('stats', 'patient_flow', (int)$userId);
            
            $flow = $this->statsService ? $this->statsService->getPatientFlowStats() : [];
            
            $this->dashboardLogger->logMetricCalculation('patient_flow', [
                'query_count' => 1,
                'row_count' => count($flow),
            ], microtime(true) - $startTime);
            
            return ['success' => true, 'data' => $flow, 'timestamp' => date('c')];
        } catch (Exception $e) {
            $this->logError('getPatientFlow', $e);
            $this->dashboardLogger->logError('getPatientFlow', $e->getMessage(), []);
            return ['success' => false, 'error' => 'Failed to retrieve patient flow statistics', 'timestamp' => date('c')];
        }
    }

    // ========== PRIVATE HELPER METHODS ==========

    private function getCurrentUserRole(): ?string
    {
        return $_SESSION['user']['role'] ?? $_SESSION['user']['primary_role'] ?? null;
    }

    private function sanitizeString(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    private function sanitizeInteger($value): int
    {
        return (int)filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }

    private function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    private function formatDateTime(?string $datetime): string
    {
        if (empty($datetime)) return '';
        $timestamp = strtotime($datetime);
        return $timestamp === false ? '' : date('M j, Y g:i A', $timestamp);
    }

    private function validateDateRangeInput(array $input): array
    {
        $errors = [];
        if (empty($input['start_date'])) {
            $errors['start_date'] = 'Start date is required';
        } elseif (!$this->isValidDate($input['start_date'])) {
            $errors['start_date'] = 'Invalid start date format';
        }
        if (empty($input['end_date'])) {
            $errors['end_date'] = 'End date is required';
        } elseif (!$this->isValidDate($input['end_date'])) {
            $errors['end_date'] = 'Invalid end date format';
        }
        if (empty($errors) && strtotime($input['start_date']) > strtotime($input['end_date'])) {
            $errors['date_range'] = 'Start date must be before end date';
        }
        return $errors;
    }

    private function formatStats(array $stats): array
    {
        if (isset($stats['upcoming_appointments'])) {
            foreach ($stats['upcoming_appointments'] as &$apt) {
                $apt['appointment_id'] = $apt['appointment_id'] ?? null;
                $apt['patient_name'] = $apt['patient_name'] ?? 'Unknown';
                $apt['time'] = $apt['time'] ?? '';
                $apt['visit_reason'] = $apt['visit_reason'] ?? 'General Visit';
            }
        }
        return array_merge([
            'total_patients_today' => 0, 'new_patients_today' => 0, 'returning_patients_today' => 0,
            'procedures_completed' => 0, 'drug_tests_today' => 0, 'physicals_today' => 0,
            'pending_reviews' => 0, 'average_wait_time' => 0, 'appointments_today' => 0, 'upcoming_appointments' => []
        ], $stats);
    }

    private function getMockTodayStats(): array
    {
        return [
            'total_patients_today' => 0, 'new_patients_today' => 0, 'returning_patients_today' => 0,
            'procedures_completed' => 0, 'drug_tests_today' => 0, 'physicals_today' => 0,
            'pending_reviews' => 0, 'average_wait_time' => 0, 'appointments_today' => 0, 'upcoming_appointments' => []
        ];
    }

    private function sanitizeQAFilters(array $filters): array
    {
        $sanitized = [];
        if (!empty($filters['status'])) $sanitized['status'] = $this->sanitizeString($filters['status']);
        if (!empty($filters['reviewer_id'])) $sanitized['reviewer_id'] = $this->sanitizeInteger($filters['reviewer_id']);
        if (!empty($filters['date_from'])) $sanitized['date_from'] = $filters['date_from'];
        if (!empty($filters['date_to'])) $sanitized['date_to'] = $filters['date_to'];
        return $sanitized;
    }

    private function sanitizeQAReviewData(array $data): array
    {
        return [
            'encounter_id' => $this->sanitizeInteger($data['encounter_id'] ?? 0),
            'score' => $this->sanitizeInteger($data['score'] ?? 0),
            'comments' => trim($data['comments'] ?? ''),
            'review_type' => $this->sanitizeString($data['review_type'] ?? 'comprehensive'),
            'reviewer_id' => $_SESSION['user']['user_id'] ?? 0,
            'reviewed_at' => date('Y-m-d H:i:s')
        ];
    }

    private function getRegulatoryCategories(): array
    {
        return [
            'hipaa' => 'HIPAA', 'osha' => 'OSHA', 'dot' => 'DOT',
            'state' => 'State Regulations', 'federal' => 'Federal Regulations', 'clinical' => 'Clinical Guidelines'
        ];
    }
    
    // ========== DATABASE HELPER METHODS ==========
    
    /**
     * Count total users in the system
     */
    private function countUsers(): int
    {
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM users");
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Count active sessions
     */
    private function countActiveSessions(): int
    {
        try {
            $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 1200;
            $cutoff = date('Y-m-d H:i:s', time() - $timeout);
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(DISTINCT session_id) FROM audit_log WHERE created_at >= :cutoff"
            );
            $stmt->execute(['cutoff' => $cutoff]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Count daily login events
     */
    private function countDailyLogins(): int
    {
        try {
            $todayStart = date('Y-m-d 00:00:00');
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM audit_log WHERE event_type = 'login' AND created_at >= :start"
            );
            $stmt->execute(['start' => $todayStart]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Get database size (MySQL specific)
     */
    private function getDatabaseSize(): string
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()"
            );
            $size = $stmt->fetchColumn();
            return $size ? $size . ' MB' : 'N/A';
        } catch (Exception $e) {
            return 'N/A';
        }
    }
    
    /**
     * Get last backup date
     */
    private function getLastBackupDate(): string
    {
        try {
            // Check for backup log or recent backup file
            $backupPath = defined('BACKUP_PATH') ? BACKUP_PATH : __DIR__ . '/../database/backups/';
            if (is_dir($backupPath)) {
                $files = glob($backupPath . '*.sql');
                if (!empty($files)) {
                    $latestFile = max(array_map('filemtime', $files));
                    return date('Y-m-d H:i:s', $latestFile);
                }
            }
            return 'No backups found';
        } catch (Exception $e) {
            return 'Unknown';
        }
    }
    
    /**
     * Count pending tasks/encounters
     */
    private function countPendingTasks(): int
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT COUNT(*) FROM encounters WHERE status IN ('pending', 'in_progress', 'draft')"
            );
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Get user counts grouped by role
     */
    private function getUsersByRole(): array
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT primary_role, COUNT(*) as count FROM users GROUP BY primary_role"
            );
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[$row['primary_role'] ?? 'unknown'] = (int)$row['count'];
            }
            return $results;
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Count users with passwords expiring soon (within 30 days)
     */
    private function countPasswordsExpiringSoon(): int
    {
        try {
            $thirtyDaysFromNow = date('Y-m-d', strtotime('+30 days'));
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM users WHERE password_expires_at <= :expiry AND password_expires_at > NOW()"
            );
            $stmt->execute(['expiry' => $thirtyDaysFromNow]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Get staff performance metrics
     */
    private function getStaffPerformanceMetrics(): array
    {
        try {
            $monthStart = date('Y-m-01 00:00:00');
            $stmt = $this->pdo->prepare(
                "SELECT u.user_id, u.username, u.first_name, u.last_name,
                        COUNT(e.encounter_id) as encounter_count,
                        SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) as completed_count
                 FROM users u
                 LEFT JOIN encounters e ON u.user_id = e.provider_id AND e.created_at >= :start
                 WHERE u.primary_role IN ('1clinician', 'dclinician', 'pclinician')
                 GROUP BY u.user_id, u.username, u.first_name, u.last_name
                 ORDER BY encounter_count DESC
                 LIMIT 10"
            );
            $stmt->execute(['start' => $monthStart]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Calculate average response time (in minutes)
     */
    private function calculateAverageResponseTime(): float
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, started_at)) as avg_response
                 FROM encounters
                 WHERE started_at IS NOT NULL
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );
            return (float)($stmt->fetchColumn() ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Calculate average response time for a specific provider
     */
    private function calculateAverageResponseTimeForProvider(int $userId): float
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, started_at)) as avg_response
                 FROM encounters
                 WHERE provider_id = :user_id
                 AND started_at IS NOT NULL
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );
            $stmt->execute(['user_id' => $userId]);
            return (float)($stmt->fetchColumn() ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Calculate training compliance rate
     */
    private function calculateTrainingCompliance(): float
    {
        try {
            $trainingStatus = $this->getTrainingStatus();
            return $trainingStatus['compliance_rate'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Calculate certification compliance rate
     */
    private function calculateCertificationCompliance(): float
    {
        try {
            $certExpiry = $this->getCertificationExpiry();
            $total = $certExpiry['summary']['total_certifications'] ?? 0;
            $valid = $certExpiry['summary']['valid_count'] ?? 0;
            return $total > 0 ? round(($valid / $total) * 100, 1) : 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Determine current shift based on hour
     */
    private function determineCurrentShift(int $hour): array
    {
        // Standard 8-hour shifts
        if ($hour >= 7 && $hour < 15) {
            return ['name' => 'Day Shift', 'start' => '07:00', 'end' => '15:00', 'scheduled' => 0];
        } elseif ($hour >= 15 && $hour < 23) {
            return ['name' => 'Evening Shift', 'start' => '15:00', 'end' => '23:00', 'scheduled' => 0];
        } else {
            return ['name' => 'Night Shift', 'start' => '23:00', 'end' => '07:00', 'scheduled' => 0];
        }
    }
    
    /**
     * Determine next shift
     */
    private function determineNextShift(array $currentShift): array
    {
        switch ($currentShift['name']) {
            case 'Day Shift':
                return ['name' => 'Evening Shift', 'start' => '15:00', 'end' => '23:00', 'scheduled' => 0];
            case 'Evening Shift':
                return ['name' => 'Night Shift', 'start' => '23:00', 'end' => '07:00', 'scheduled' => 0];
            default:
                return ['name' => 'Day Shift', 'start' => '07:00', 'end' => '15:00', 'scheduled' => 0];
        }
    }
    
    /**
     * Get weekly coverage statistics
     */
    private function getWeeklyCoverageStats(): array
    {
        try {
            $weekStart = date('Y-m-d', strtotime('monday this week'));
            $coverage = [];
            
            for ($i = 0; $i < 7; $i++) {
                $date = date('Y-m-d', strtotime($weekStart . " +$i days"));
                $dayName = date('l', strtotime($date));
                
                // Count encounters for each day
                $stmt = $this->pdo->prepare(
                    "SELECT COUNT(DISTINCT provider_id) as providers, COUNT(*) as encounters
                     FROM encounters
                     WHERE DATE(created_at) = :date"
                );
                $stmt->execute(['date' => $date]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $coverage[] = [
                    'date' => $date,
                    'day' => $dayName,
                    'providers_active' => (int)($row['providers'] ?? 0),
                    'encounters' => (int)($row['encounters'] ?? 0)
                ];
            }
            
            return $coverage;
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Calculate trend based on current and previous values
     */
    private function calculateTrend(float $current, float $previous): string
    {
        if ($previous == 0) {
            return 'stable';
        }
        
        $percentChange = (($current - $previous) / $previous) * 100;
        
        if ($percentChange > 5) {
            return 'increasing';
        } elseif ($percentChange < -5) {
            return 'decreasing';
        }
        
        return 'stable';
    }
    
    /**
     * Get trending QA issues
     */
    private function getTrendingQAIssues(): array
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT issue_type, COUNT(*) as count
                 FROM qa_issues
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY issue_type
                 ORDER BY count DESC
                 LIMIT 5"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    private function logAuditEvent(string $action, array $context = []): void
    {
        try {
            $logEntry = [
                'timestamp' => date('Y-m-d H:i:s'),
                'action' => $this->sanitizeString($action),
                'user_id' => $_SESSION['user']['user_id'] ?? 'anonymous',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $this->sanitizeString($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'),
                'context' => $context
            ];
            $logFile = $this->logPath . 'audit_' . date('Y-m-d') . '.log';
            $logLine = date('Y-m-d H:i:s') . ' | DASHBOARD | ' . json_encode($logEntry) . PHP_EOL;
            file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            error_log('Failed to write audit log: ' . $e->getMessage());
        }
    }

    private function logError(string $method, Exception $e, array $context = []): void
    {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'class' => 'DashboardstatsViewModel',
            'method' => $method,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'user_id' => $_SESSION['user']['user_id'] ?? 'unknown',
            'context' => $context
        ];
        error_log('DashboardstatsViewModel Error: ' . json_encode($logEntry));
        try {
            $logFile = $this->logPath . 'error_' . date('Y-m-d') . '.log';
            $logLine = date('Y-m-d H:i:s') . ' | ERROR | ' . json_encode($logEntry) . PHP_EOL;
            file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
        } catch (Exception $logException) {
            error_log('Failed to write to error log: ' . $logException->getMessage());
        }
    }
}