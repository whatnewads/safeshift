<?php

declare(strict_types=1);

namespace ViewModel;

use Model\Repositories\AdminRepository;
use Model\Repositories\CaseRepository;
use ViewModel\Core\ApiResponse;
use PDO;

/**
 * Admin ViewModel
 *
 * Coordinates between the View (API) and Model (Repository) layers
 * for Admin dashboard operations including compliance, training, and OSHA data.
 *
 * @package ViewModel
 */
class AdminViewModel
{
    private AdminRepository $adminRepository;
    private CaseRepository $caseRepository;
    private ?string $currentUserId = null;
    private ?string $currentClinicId = null;

    public function __construct(PDO $pdo)
    {
        $this->adminRepository = new AdminRepository($pdo);
        $this->caseRepository = new CaseRepository($pdo);
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
     * Get Admin Dashboard data
     * Returns comprehensive dashboard data including stats, cases, patient flow,
     * site performance, and provider performance
     */
    public function getAdminDashboard(): array
    {
        try {
            // Get admin-specific stats
            $adminStats = $this->adminRepository->getAdminStats();
            
            // Get case stats for operations overview
            $adminCaseStats = $this->adminRepository->getCaseStats($this->currentClinicId);
            $caseStats = $this->caseRepository->getCaseStats($this->currentClinicId);
            
            // Combine stats
            $stats = [
                // Admin-specific stats
                'complianceAlertsCount' => $adminStats['compliance_alerts'],
                'trainingDueCount' => $adminStats['training_due'],
                'expiringCredentialsCount' => $adminStats['expiring_credentials'],
                'regulatoryUpdates' => $adminStats['regulatory_updates'],
                // Case stats
                'pendingQA' => $adminCaseStats['pending_qa'],
                'openCases' => $adminCaseStats['open_cases'],
                'pendingOrders' => $adminCaseStats['pending_orders'],
                'encountersToday' => $adminCaseStats['encounters_today'],
                'followUpsDue' => $caseStats['follow_ups_due'],
                'highRisk' => $caseStats['high_risk'],
            ];

            // Get patient flow metrics
            $patientFlow = $this->adminRepository->getPatientFlowMetrics($this->currentClinicId);
            
            // Get recent cases
            $recentCases = $this->adminRepository->getRecentCases(10, $this->currentClinicId);
            
            // Get site performance
            $sitePerformance = $this->adminRepository->getSitePerformance(5);
            
            // Get provider performance
            $providerPerformance = $this->adminRepository->getProviderPerformance(5);
            
            // Get clearance stats
            $clearanceStats = $this->adminRepository->getClearanceStats($this->currentClinicId);

            return ApiResponse::success([
                'stats' => $stats,
                'patientFlow' => [
                    'walkIns' => $patientFlow['walk_ins'],
                    'scheduled' => $patientFlow['scheduled'],
                    'emergency' => $patientFlow['emergency'],
                    'totalToday' => $patientFlow['total_today'],
                ],
                'recentCases' => $recentCases,
                'sitePerformance' => $sitePerformance,
                'providerPerformance' => $providerPerformance,
                'clearanceStats' => [
                    'cleared' => $clearanceStats['cleared'],
                    'notCleared' => $clearanceStats['not_cleared'],
                    'pendingReview' => $clearanceStats['pending_review'],
                ],
            ]);
        } catch (\Exception $e) {
            error_log("AdminViewModel::getAdminDashboard error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve admin dashboard data');
        }
    }

    /**
     * Get case statistics only
     */
    public function getCaseStats(): array
    {
        try {
            $adminCaseStats = $this->adminRepository->getCaseStats($this->currentClinicId);
            $caseStats = $this->caseRepository->getCaseStats($this->currentClinicId);

            return ApiResponse::success([
                'stats' => [
                    'pendingQA' => $adminCaseStats['pending_qa'],
                    'openCases' => $adminCaseStats['open_cases'],
                    'pendingOrders' => $adminCaseStats['pending_orders'],
                    'encountersToday' => $adminCaseStats['encounters_today'],
                    'followUpsDue' => $caseStats['follow_ups_due'],
                    'highRisk' => $caseStats['high_risk'],
                ],
            ]);
        } catch (\Exception $e) {
            error_log("AdminViewModel::getCaseStats error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve case statistics');
        }
    }

    /**
     * Get recent cases
     */
    public function getRecentCases(int $limit = 10): array
    {
        try {
            $cases = $this->adminRepository->getRecentCases($limit, $this->currentClinicId);

            return ApiResponse::success([
                'cases' => $cases,
                'count' => count($cases),
            ]);
        } catch (\Exception $e) {
            error_log("AdminViewModel::getRecentCases error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve recent cases');
        }
    }

    /**
     * Get patient flow metrics
     */
    public function getPatientFlowMetrics(): array
    {
        try {
            $flow = $this->adminRepository->getPatientFlowMetrics($this->currentClinicId);

            return ApiResponse::success([
                'patientFlow' => [
                    'walkIns' => $flow['walk_ins'],
                    'scheduled' => $flow['scheduled'],
                    'emergency' => $flow['emergency'],
                    'totalToday' => $flow['total_today'],
                ],
            ]);
        } catch (\Exception $e) {
            error_log("AdminViewModel::getPatientFlowMetrics error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve patient flow metrics');
        }
    }

    /**
     * Get site/establishment performance metrics
     */
    public function getSitePerformance(int $limit = 10): array
    {
        try {
            $sites = $this->adminRepository->getSitePerformance($limit);

            return ApiResponse::success([
                'sites' => $sites,
                'count' => count($sites),
            ]);
        } catch (\Exception $e) {
            error_log("AdminViewModel::getSitePerformance error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve site performance data');
        }
    }

    /**
     * Get provider performance metrics
     */
    public function getProviderPerformance(int $limit = 10): array
    {
        try {
            $providers = $this->adminRepository->getProviderPerformance($limit);

            return ApiResponse::success([
                'providers' => $providers,
                'count' => count($providers),
            ]);
        } catch (\Exception $e) {
            error_log("AdminViewModel::getProviderPerformance error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve provider performance data');
        }
    }

    /**
     * Get staff list
     */
    public function getStaffList(int $page = 1, int $perPage = 50): array
    {
        try {
            $offset = ($page - 1) * $perPage;
            $staff = $this->adminRepository->getStaffList($perPage, $offset);
            $totalCount = $this->adminRepository->countStaff();

            return ApiResponse::success([
                'staff' => $staff,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $totalCount,
                    'total_pages' => (int) ceil($totalCount / $perPage),
                ],
            ]);
        } catch (\Exception $e) {
            error_log("AdminViewModel::getStaffList error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve staff list');
        }
    }

    /**
     * Get clearance statistics
     */
    public function getClearanceStats(): array
    {
        try {
            $stats = $this->adminRepository->getClearanceStats($this->currentClinicId);

            return ApiResponse::success([
                'clearanceStats' => [
                    'cleared' => $stats['cleared'],
                    'notCleared' => $stats['not_cleared'],
                    'pendingReview' => $stats['pending_review'],
                ],
            ]);
        } catch (\Exception $e) {
            error_log("AdminViewModel::getClearanceStats error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve clearance statistics');
        }
    }

    /**
     * Get compliance alerts
     */
    public function getComplianceAlerts(
        ?string $status = null,
        ?string $priority = null,
        int $page = 1,
        int $perPage = 20
    ): array {
        try {
            $offset = ($page - 1) * $perPage;
            
            $alerts = $this->adminRepository->getComplianceAlerts(
                $status,
                $priority,
                $perPage,
                $offset
            );
            
            $totalCount = $this->adminRepository->countComplianceAlerts($status);

            return ApiResponse::success([
                'alerts' => $alerts,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $totalCount,
                    'total_pages' => (int) ceil($totalCount / $perPage),
                ],
            ]);
        } catch (\Exception $e) {
            error_log("AdminViewModel::getComplianceAlerts error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve compliance alerts');
        }
    }

    /**
     * Acknowledge a compliance alert
     */
    public function acknowledgeComplianceAlert(string $alertId): array
    {
        if (!$this->currentUserId) {
            return ApiResponse::error('User not authenticated', 401);
        }

        try {
            $success = $this->adminRepository->acknowledgeComplianceAlert(
                $alertId,
                $this->currentUserId
            );

            if ($success) {
                return ApiResponse::success([
                    'message' => 'Alert acknowledged successfully',
                ]);
            }

            return ApiResponse::serverError('Failed to acknowledge alert');
        } catch (\Exception $e) {
            error_log("AdminViewModel::acknowledgeComplianceAlert error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to acknowledge alert');
        }
    }

    /**
     * Get training module summary
     */
    public function getTrainingModules(): array
    {
        try {
            $modules = $this->adminRepository->getTrainingModuleSummary();

            // Format for frontend
            $formattedModules = array_map(function($module) {
                return [
                    'id' => $module['requirement_id'],
                    'title' => $module['training_name'] ?? '',
                    'description' => $module['training_description'] ?? null,
                    'frequencyDays' => (int)($module['recurrence_interval'] ?? 365),
                    'isRequired' => (bool)($module['is_active'] ?? true),
                    'assignedTo' => (int)($module['assigned_count'] ?? 0),
                    'completed' => (int)($module['completed_count'] ?? 0),
                    'inProgress' => (int)($module['in_progress_count'] ?? 0),
                    'notStarted' => (int)($module['not_started_count'] ?? 0),
                    'expiringCount' => (int)($module['expiring_count'] ?? 0),
                ];
            }, $modules);

            return ApiResponse::success([
                'modules' => $formattedModules,
            ]);
        } catch (\Exception $e) {
            error_log("AdminViewModel::getTrainingModules error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve training modules');
        }
    }

    /**
     * Get expiring credentials
     */
    public function getExpiringCredentials(int $daysAhead = 60): array
    {
        try {
            $credentials = $this->adminRepository->getExpiringCredentials($daysAhead);

            // Format for frontend
            $formattedCredentials = array_map(function($cred) {
                return [
                    'id' => $cred['record_id'],
                    'userId' => $cred['user_id'],
                    'user' => $cred['username'] ?? 'Unknown',
                    'credential' => $cred['credential_name'],
                    'expiryDate' => $cred['expiration_date'],
                    'daysUntilExpiry' => (int)$cred['days_until_expiry'],
                    'status' => $cred['status'],
                ];
            }, $credentials);

            return ApiResponse::success([
                'credentials' => $formattedCredentials,
                'count' => count($formattedCredentials),
            ]);
        } catch (\Exception $e) {
            error_log("AdminViewModel::getExpiringCredentials error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve expiring credentials');
        }
    }

    /**
     * Get OSHA 300 Log (READ-ONLY)
     */
    public function getOsha300Log(?int $year = null): array
    {
        try {
            $year = $year ?? (int) date('Y');
            $entries = $this->adminRepository->getOsha300Log($year);
            $statistics = $this->adminRepository->getOshaStatistics($year);

            return ApiResponse::success([
                'entries' => $entries,
                'statistics' => [
                    'totalRecordableCases' => (int)$statistics['total_recordable_cases'],
                    'daysAwayCases' => (int)$statistics['days_away_cases'],
                    'jobTransferCases' => (int)$statistics['job_transfer_cases'],
                    'otherRecordableCases' => (int)$statistics['other_recordable_cases'],
                    'totalDaysAway' => (int)$statistics['total_days_away'],
                    'totalDaysRestricted' => (int)$statistics['total_days_restricted'],
                ],
                'year' => $year,
                'readonly' => true, // Indicate these are READ-ONLY per OSHA compliance
            ]);
        } catch (\Exception $e) {
            error_log("AdminViewModel::getOsha300Log error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve OSHA 300 Log');
        }
    }

    /**
     * Get OSHA 300A Summary (READ-ONLY)
     */
    public function getOsha300ASummary(?int $year = null): array
    {
        try {
            $year = $year ?? (int) date('Y');
            $summary = $this->adminRepository->getOsha300ASummary($year);

            return ApiResponse::success([
                'summary' => $summary,
                'year' => $year,
                'readonly' => true, // Indicate READ-ONLY per OSHA compliance
            ]);
        } catch (\Exception $e) {
            error_log("AdminViewModel::getOsha300ASummary error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve OSHA 300A Summary');
        }
    }
}
