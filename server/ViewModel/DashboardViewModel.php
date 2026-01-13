<?php

declare(strict_types=1);

namespace ViewModel;

use Model\Repositories\CaseRepository;
use Model\Repositories\EncounterRepository;
use ViewModel\Core\ApiResponse;
use Core\Services\DashboardLogger;
use PDO;

// Ensure DashboardLogger is available
require_once __DIR__ . '/../core/Services/DashboardLogger.php';

/**
 * Dashboard ViewModel
 *
 * Coordinates between the View (API) and Model (Repository) layers
 * for dashboard-related operations across different roles.
 *
 * @package ViewModel
 */
class DashboardViewModel
{
    private CaseRepository $caseRepository;
    private EncounterRepository $encounterRepository;
    private ?string $currentUserId = null;
    private ?string $currentClinicId = null;
    private ?string $currentRole = null;
    private DashboardLogger $logger;

    public function __construct(PDO $pdo)
    {
        $this->caseRepository = new CaseRepository($pdo);
        $this->encounterRepository = new EncounterRepository($pdo);
        $this->logger = DashboardLogger::getInstance();
    }

    /**
     * Set the current user context
     */
    public function setCurrentUser(string $userId): self
    {
        $this->currentUserId = $userId;
        return $this;
    }

    /**
     * Set the current clinic context
     */
    public function setCurrentClinic(string $clinicId): self
    {
        $this->currentClinicId = $clinicId;
        return $this;
    }

    /**
     * Set the current role context
     */
    public function setCurrentRole(string $role): self
    {
        $this->currentRole = $role;
        return $this;
    }

    /**
     * Get Manager Dashboard data
     */
    public function getManagerDashboard(): array
    {
        $startTime = microtime(true);
        
        try {
            // Get case statistics
            $queryStart = microtime(true);
            $stats = $this->caseRepository->getCaseStats($this->currentClinicId);
            $this->logger->logQueryPerformance('getCaseStats', microtime(true) - $queryStart, 1);
            
            // Get recent cases for the table
            $queryStart = microtime(true);
            $cases = $this->caseRepository->getCases(
                $this->currentClinicId,
                null, // all statuses
                10,   // limit
                0,    // offset
                'created_at',
                'DESC'
            );
            $this->logger->logQueryPerformance('getCases', microtime(true) - $queryStart, count($cases));

            $this->logger->logDataAggregation('manager_dashboard_stats', [
                'data_points' => 4,
                'source_tables' => ['cases', 'case_stats'],
            ], microtime(true) - $startTime);

            return ApiResponse::success([
                'stats' => [
                    'openCases' => $stats['open_cases'],
                    'followUpsDue' => $stats['follow_ups_due'],
                    'highRisk' => $stats['high_risk'],
                    'closedThisMonth' => $stats['closed_this_month'],
                ],
                'cases' => $cases,
            ]);
        } catch (\Exception $e) {
            error_log("DashboardViewModel::getManagerDashboard error: " . $e->getMessage());
            $this->logger->logError('getManagerDashboard', $e->getMessage(), [
                'clinic_id' => $this->currentClinicId,
            ]);
            return ApiResponse::error('Failed to retrieve dashboard data', 500);
        }
    }

    /**
     * Get cases with filtering
     */
    public function getCases(
        ?string $status = null,
        int $page = 1,
        int $perPage = 20
    ): array {
        $startTime = microtime(true);
        
        try {
            $offset = ($page - 1) * $perPage;

            $queryStart = microtime(true);
            $cases = $this->caseRepository->getCases(
                $this->currentClinicId,
                $status,
                $perPage,
                $offset,
                'created_at',
                'DESC'
            );
            $this->logger->logQueryPerformance('getCases_filtered', microtime(true) - $queryStart, count($cases));

            $queryStart = microtime(true);
            $totalCount = $this->caseRepository->countCases($this->currentClinicId, $status);
            $this->logger->logQueryPerformance('countCases', microtime(true) - $queryStart, 1);

            $this->logger->logDataAggregation('cases_list', [
                'data_points' => count($cases),
                'source_tables' => ['cases'],
            ], microtime(true) - $startTime);

            return ApiResponse::success([
                'cases' => $cases,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $totalCount,
                    'total_pages' => (int) ceil($totalCount / $perPage),
                ],
            ]);
        } catch (\Exception $e) {
            error_log("DashboardViewModel::getCases error: " . $e->getMessage());
            $this->logger->logError('getCases', $e->getMessage(), [
                'status' => $status,
                'page' => $page,
            ]);
            return ApiResponse::error('Failed to retrieve cases', 500);
        }
    }

    /**
     * Get a single case by ID
     */
    public function getCase(string $caseId): array
    {
        $startTime = microtime(true);
        
        try {
            $case = $this->caseRepository->getCaseById($caseId);
            $this->logger->logQueryPerformance('getCaseById', microtime(true) - $startTime, $case ? 1 : 0);

            if (!$case) {
                $this->logger->logError('getCase', 'Case not found', ['case_id' => $caseId]);
                return ApiResponse::error('Case not found', 404);
            }

            return ApiResponse::success([
                'case' => $case,
            ]);
        } catch (\Exception $e) {
            error_log("DashboardViewModel::getCase error: " . $e->getMessage());
            $this->logger->logError('getCase', $e->getMessage(), ['case_id' => $caseId]);
            return ApiResponse::error('Failed to retrieve case', 500);
        }
    }

    /**
     * Add a flag to a case
     */
    public function addCaseFlag(string $caseId, array $data): array
    {
        $startTime = microtime(true);
        
        try {
            $flagType = $data['flag_type'] ?? $data['flagType'] ?? null;
            $reason = $data['reason'] ?? '';
            $severity = $data['severity'] ?? 'medium';

            if (!$flagType) {
                $this->logger->logError('addCaseFlag', 'Flag type is required', ['case_id' => $caseId]);
                return ApiResponse::error('Flag type is required', 422);
            }

            $validFlags = ['high_risk', 'follow_up_required', 'osha_recordable', 'critical', 'documentation_needed'];
            if (!in_array($flagType, $validFlags)) {
                $this->logger->logError('addCaseFlag', 'Invalid flag type', [
                    'case_id' => $caseId,
                    'flag_type' => $flagType,
                ]);
                return ApiResponse::error('Invalid flag type', 422);
            }

            $queryStart = microtime(true);
            $success = $this->caseRepository->addFlag(
                $caseId,
                $flagType,
                $reason,
                $severity,
                $this->currentUserId
            );
            $this->logger->logQueryPerformance('addFlag', microtime(true) - $queryStart, 1);

            if ($success) {
                $case = $this->caseRepository->getCaseById($caseId);
                $this->logger->logMetricCalculation('add_case_flag', [
                    'query_count' => 2,
                    'case_id' => $caseId,
                    'flag_type' => $flagType,
                ], microtime(true) - $startTime);
                
                return ApiResponse::success([
                    'case' => $case,
                    'message' => 'Flag added successfully',
                ]);
            }

            $this->logger->logError('addCaseFlag', 'Failed to add flag', ['case_id' => $caseId]);
            return ApiResponse::error('Failed to add flag', 500);
        } catch (\Exception $e) {
            error_log("DashboardViewModel::addCaseFlag error: " . $e->getMessage());
            $this->logger->logError('addCaseFlag', $e->getMessage(), ['case_id' => $caseId]);
            return ApiResponse::error('Failed to add flag', 500);
        }
    }

    /**
     * Resolve a flag
     */
    public function resolveFlag(string $flagId): array
    {
        $startTime = microtime(true);
        
        try {
            $success = $this->caseRepository->resolveFlag($flagId, $this->currentUserId);
            $this->logger->logQueryPerformance('resolveFlag', microtime(true) - $startTime, 1);

            if ($success) {
                $this->logger->logMetricCalculation('resolve_flag', [
                    'query_count' => 1,
                    'flag_id' => $flagId,
                ], microtime(true) - $startTime);
                
                return ApiResponse::success([
                    'message' => 'Flag resolved successfully',
                ]);
            }

            $this->logger->logError('resolveFlag', 'Failed to resolve flag', ['flag_id' => $flagId]);
            return ApiResponse::error('Failed to resolve flag', 500);
        } catch (\Exception $e) {
            error_log("DashboardViewModel::resolveFlag error: " . $e->getMessage());
            $this->logger->logError('resolveFlag', $e->getMessage(), ['flag_id' => $flagId]);
            return ApiResponse::error('Failed to resolve flag', 500);
        }
    }

    /**
     * Get dashboard statistics
     */
    public function getStats(): array
    {
        $startTime = microtime(true);
        
        try {
            $stats = $this->caseRepository->getCaseStats($this->currentClinicId);
            $this->logger->logQueryPerformance('getCaseStats', microtime(true) - $startTime, 4);

            $this->logger->logMetricCalculation('dashboard_stats', [
                'query_count' => 1,
                'row_count' => 4,
            ], microtime(true) - $startTime);

            return ApiResponse::success([
                'stats' => [
                    'openCases' => $stats['open_cases'],
                    'followUpsDue' => $stats['follow_ups_due'],
                    'highRisk' => $stats['high_risk'],
                    'closedThisMonth' => $stats['closed_this_month'],
                ],
            ]);
        } catch (\Exception $e) {
            error_log("DashboardViewModel::getStats error: " . $e->getMessage());
            $this->logger->logError('getStats', $e->getMessage(), [
                'clinic_id' => $this->currentClinicId,
            ]);
            return ApiResponse::error('Failed to retrieve statistics', 500);
        }
    }

    /**
     * Get Clinical Provider Dashboard data
     */
    public function getClinicalProviderDashboard(): array
    {
        $startTime = microtime(true);
        
        if (!$this->currentUserId) {
            $this->logger->logError('getClinicalProviderDashboard', 'User not authenticated', []);
            return ApiResponse::error('User not authenticated', 401);
        }

        try {
            // Get provider's pending encounters
            $queryStart = microtime(true);
            $pendingEncounters = $this->encounterRepository->findPendingForProvider(
                $this->currentUserId,
                10
            );
            $this->logger->logQueryPerformance('findPendingForProvider', microtime(true) - $queryStart, count($pendingEncounters));

            // Get today's encounters
            $todaysEncounters = [];
            if ($this->currentClinicId) {
                $queryStart = microtime(true);
                $todaysEncounters = $this->encounterRepository->findTodaysEncounters(
                    $this->currentClinicId,
                    20
                );
                $this->logger->logQueryPerformance('findTodaysEncounters', microtime(true) - $queryStart, count($todaysEncounters));
            }

            // Get stats
            $queryStart = microtime(true);
            $stats = $this->encounterRepository->getProviderStats($this->currentUserId);
            $this->logger->logQueryPerformance('getProviderStats', microtime(true) - $queryStart, count($stats));

            // Calculate summary stats
            $inProgress = 0;
            $pendingReview = 0;
            $completed = 0;
            foreach ($stats as $stat) {
                if ($stat['status'] === 'in_progress') {
                    $inProgress += (int)$stat['count'];
                } elseif ($stat['status'] === 'pending_review') {
                    $pendingReview += (int)$stat['count'];
                } elseif ($stat['status'] === 'complete') {
                    $completed += (int)$stat['count'];
                }
            }

            $encounterData = array_map(fn($e) => $e->toSafeArray(), $pendingEncounters);
            $todaysData = array_map(fn($e) => $e->toSafeArray(), $todaysEncounters);

            $this->logger->logDataAggregation('clinical_provider_dashboard', [
                'data_points' => count($encounterData) + count($todaysData) + 4,
                'source_tables' => ['encounters', 'provider_stats'],
            ], microtime(true) - $startTime);

            return ApiResponse::success([
                'stats' => [
                    'inProgress' => $inProgress,
                    'pendingReview' => $pendingReview,
                    'completedToday' => $completed,
                    'todaysTotal' => count($todaysEncounters),
                ],
                'pendingEncounters' => $encounterData,
                'todaysEncounters' => $todaysData,
            ]);
        } catch (\Exception $e) {
            error_log("DashboardViewModel::getClinicalProviderDashboard error: " . $e->getMessage());
            $this->logger->logError('getClinicalProviderDashboard', $e->getMessage(), [
                'user_id' => $this->currentUserId,
                'clinic_id' => $this->currentClinicId,
            ]);
            return ApiResponse::error('Failed to retrieve dashboard data', 500);
        }
    }

    /**
     * Get role-specific dashboard data
     */
    public function getDashboardByRole(): array
    {
        $this->logger->logMetricRequest('role_based', 'getDashboardByRole', (int)$this->currentUserId, [
            'role' => $this->currentRole,
        ]);
        
        switch ($this->currentRole) {
            case 'manager':
            case 'super_manager':
                return $this->getManagerDashboard();
            
            case 'clinical_provider':
            case 'doctor':
                return $this->getClinicalProviderDashboard();
            
            case 'technician':
                return $this->getTechnicianDashboard();
            
            case 'registration':
                return $this->getRegistrationDashboard();
            
            case 'admin':
                return $this->getAdminDashboard();
            
            default:
                $this->logger->logTodoHit(
                    'DashboardViewModel::getDashboardByRole',
                    'Generic dashboard returned for unhandled role',
                    ['role' => $this->currentRole]
                );
                return $this->getGenericDashboard();
        }
    }

    /**
     * Get Technician Dashboard data
     */
    public function getTechnicianDashboard(): array
    {
        $startTime = microtime(true);
        
        try {
            // Technicians see task queue - encounters needing vitals, samples, etc.
            $taskQueue = [];
            if ($this->currentClinicId) {
                $queryStart = microtime(true);
                $encounters = $this->encounterRepository->findTodaysEncounters(
                    $this->currentClinicId,
                    50
                );
                $this->logger->logQueryPerformance('findTodaysEncounters_technician', microtime(true) - $queryStart, count($encounters));
                
                foreach ($encounters as $encounter) {
                    $data = $encounter->toSafeArray();
                    $vitals = $encounter->getVitals();
                    
                    // Add to queue if vitals not recorded or incomplete
                    if (empty($vitals) || !isset($vitals['blood_pressure'])) {
                        $taskQueue[] = array_merge($data, [
                            'taskType' => 'vitals',
                            'taskDescription' => 'Record vitals',
                        ]);
                    }
                }
            }

            // TODO: Track completed tasks separately
            $this->logger->logTodoHit(
                'DashboardViewModel::getTechnicianDashboard',
                'completedToday metric needs separate tracking implementation',
                ['current_value' => 0]
            );

            $this->logger->logDataAggregation('technician_dashboard', [
                'data_points' => count($taskQueue),
                'source_tables' => ['encounters'],
            ], microtime(true) - $startTime);

            return ApiResponse::success([
                'stats' => [
                    'pendingTasks' => count($taskQueue),
                    'completedToday' => 0, // Would need separate tracking
                ],
                'taskQueue' => array_slice($taskQueue, 0, 20),
            ]);
        } catch (\Exception $e) {
            error_log("DashboardViewModel::getTechnicianDashboard error: " . $e->getMessage());
            $this->logger->logError('getTechnicianDashboard', $e->getMessage(), [
                'clinic_id' => $this->currentClinicId,
            ]);
            return ApiResponse::error('Failed to retrieve dashboard data', 500);
        }
    }

    /**
     * Get Registration Dashboard data
     */
    public function getRegistrationDashboard(): array
    {
        $startTime = microtime(true);
        
        try {
            // Registration sees check-ins and scheduled appointments
            $todaysEncounters = [];
            if ($this->currentClinicId) {
                $queryStart = microtime(true);
                $encounters = $this->encounterRepository->findTodaysEncounters(
                    $this->currentClinicId,
                    50
                );
                $this->logger->logQueryPerformance('findTodaysEncounters_registration', microtime(true) - $queryStart, count($encounters));
                $todaysEncounters = array_map(fn($e) => $e->toSafeArray(), $encounters);
            }

            $scheduled = array_filter($todaysEncounters, fn($e) => $e['status'] === 'scheduled');
            $checkedIn = array_filter($todaysEncounters, fn($e) => $e['status'] === 'checked_in');

            $this->logger->logDataAggregation('registration_dashboard', [
                'data_points' => count($todaysEncounters),
                'source_tables' => ['encounters'],
            ], microtime(true) - $startTime);

            return ApiResponse::success([
                'stats' => [
                    'scheduledToday' => count($scheduled),
                    'checkedIn' => count($checkedIn),
                    'total' => count($todaysEncounters),
                ],
                'appointments' => $todaysEncounters,
            ]);
        } catch (\Exception $e) {
            error_log("DashboardViewModel::getRegistrationDashboard error: " . $e->getMessage());
            $this->logger->logError('getRegistrationDashboard', $e->getMessage(), [
                'clinic_id' => $this->currentClinicId,
            ]);
            return ApiResponse::error('Failed to retrieve dashboard data', 500);
        }
    }

    /**
     * Get Admin Dashboard data
     */
    public function getAdminDashboard(): array
    {
        $startTime = microtime(true);
        
        try {
            // TODO: Implement actual compliance and training stats queries
            $this->logger->logTodoHit(
                'DashboardViewModel::getAdminDashboard',
                'Admin dashboard returns placeholder data - needs real compliance/training queries',
                ['stats' => ['complianceAlerts' => 0, 'trainingDue' => 0, 'regulatoryUpdates' => 0]]
            );

            $this->logger->logDataAggregation('admin_dashboard', [
                'data_points' => 3,
                'source_tables' => ['placeholder'],
            ], microtime(true) - $startTime);

            // Admin sees compliance and training stats
            return ApiResponse::success([
                'stats' => [
                    'complianceAlerts' => 0,
                    'trainingDue' => 0,
                    'regulatoryUpdates' => 0,
                ],
                'alerts' => [],
            ]);
        } catch (\Exception $e) {
            error_log("DashboardViewModel::getAdminDashboard error: " . $e->getMessage());
            $this->logger->logError('getAdminDashboard', $e->getMessage(), []);
            return ApiResponse::error('Failed to retrieve dashboard data', 500);
        }
    }

    /**
     * Get generic dashboard for unspecified roles
     */
    public function getGenericDashboard(): array
    {
        $this->logger->logDataAggregation('generic_dashboard', [
            'data_points' => 0,
            'source_tables' => [],
        ], 0);
        
        return ApiResponse::success([
            'stats' => [],
            'message' => 'Dashboard data loaded',
        ]);
    }
}
