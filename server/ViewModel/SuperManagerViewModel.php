<?php
/**
 * SuperManagerViewModel.php - ViewModel for SuperManager Dashboard
 * 
 * Coordinates between the View (API) and Model (Repository) layers
 * for SuperManager dashboard operations including multi-clinic oversight,
 * staff management, operational metrics, and approval actions.
 * 
 * The SuperManager role is associated with 'Manager' role type with elevated
 * permissions across multiple establishments/clinics.
 * 
 * @package    SafeShift\ViewModel
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace ViewModel;

use Model\Repositories\SuperManagerRepository;
use ViewModel\Core\ApiResponse;
use PDO;

/**
 * SuperManager ViewModel
 * 
 * Coordinates between the View (API) and Model (Repository) layers
 * for SuperManager dashboard operations.
 */
class SuperManagerViewModel
{
    private SuperManagerRepository $superManagerRepository;
    private ?string $currentUserId = null;
    private array $currentUserRoles = [];

    /**
     * Constructor
     * 
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo)
    {
        $this->superManagerRepository = new SuperManagerRepository($pdo);
    }

    /**
     * Set the current user context
     * 
     * @param string $userId User UUID
     * @return self
     */
    public function setCurrentUser(string $userId): self
    {
        $this->currentUserId = $userId;
        return $this;
    }

    /**
     * Set the current user's roles
     * 
     * @param array $roles Array of role slugs
     * @return self
     */
    public function setCurrentUserRoles(array $roles): self
    {
        $this->currentUserRoles = $roles;
        return $this;
    }

    /**
     * Validate that the current user has SuperManager access
     * 
     * SuperManager access requires one of: Manager, Admin, cadmin, tadmin
     * 
     * @return bool True if user has valid SuperManager access
     */
    public function validateSuperManagerAccess(): bool
    {
        if (empty($this->currentUserId)) {
            return false;
        }

        $validRoles = ['Manager', 'Admin', 'cadmin', 'tadmin', 'supermanager'];
        
        foreach ($this->currentUserRoles as $role) {
            if (in_array($role, $validRoles, true)) {
                return true;
            }
        }
        
        return false;
    }

    // ========================================================================
    // Dashboard Methods
    // ========================================================================

    /**
     * Get complete SuperManager Dashboard data
     * 
     * Returns comprehensive dashboard data including stats, clinic performance,
     * staff overview, pending approvals, and staff requiring attention.
     * 
     * @return array API response array
     */
    public function getDashboardData(): array
    {
        if (!$this->validateSuperManagerAccess()) {
            return ApiResponse::forbidden('SuperManager access required');
        }

        try {
            // Get overview statistics
            $stats = $this->superManagerRepository->getOverviewStats();
            
            // Get clinic performance
            $clinicPerformance = $this->superManagerRepository->getClinicPerformance(10);
            
            // Get staff requiring attention
            $expiringCredentials = $this->superManagerRepository->getExpiringCredentials(30, 10);
            $overdueTraining = $this->superManagerRepository->getTrainingOverdue(10);
            
            // Get pending approvals
            $pendingApprovals = $this->superManagerRepository->getPendingApprovals(10);
            
            // Get staff overview
            $staffOverview = $this->superManagerRepository->getStaffOverview(10);

            return ApiResponse::success([
                'stats' => [
                    'clinicsManaged' => $stats['clinicsManaged'],
                    'totalStaff' => $stats['totalStaff'],
                    'encountersToday' => $stats['encountersToday'],
                    'pendingApprovals' => $stats['pendingApprovals'],
                ],
                'clinicPerformance' => $clinicPerformance,
                'staffRequiringAttention' => [
                    'expiringCredentials' => $expiringCredentials,
                    'overdueTraining' => $overdueTraining,
                ],
                'pendingApprovals' => $pendingApprovals,
                'staffOverview' => $staffOverview,
            ]);
        } catch (\Exception $e) {
            error_log("SuperManagerViewModel::getDashboardData error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve SuperManager dashboard data');
        }
    }

    /**
     * Get overview statistics only
     * 
     * @return array API response array
     */
    public function getOverviewStats(): array
    {
        if (!$this->validateSuperManagerAccess()) {
            return ApiResponse::forbidden('SuperManager access required');
        }

        try {
            $stats = $this->superManagerRepository->getOverviewStats();

            return ApiResponse::success([
                'stats' => [
                    'clinicsManaged' => $stats['clinicsManaged'],
                    'totalStaff' => $stats['totalStaff'],
                    'encountersToday' => $stats['encountersToday'],
                    'pendingApprovals' => $stats['pendingApprovals'],
                ],
            ]);
        } catch (\Exception $e) {
            error_log("SuperManagerViewModel::getOverviewStats error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve overview statistics');
        }
    }

    // ========================================================================
    // Clinic Methods
    // ========================================================================

    /**
     * Get clinic performance metrics
     * 
     * @param int $limit Maximum number of clinics to return
     * @return array API response array
     */
    public function getClinicPerformance(int $limit = 10): array
    {
        if (!$this->validateSuperManagerAccess()) {
            return ApiResponse::forbidden('SuperManager access required');
        }

        try {
            $clinics = $this->superManagerRepository->getClinicPerformance($limit);

            return ApiResponse::success([
                'clinics' => $clinics,
                'count' => count($clinics),
            ]);
        } catch (\Exception $e) {
            error_log("SuperManagerViewModel::getClinicPerformance error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve clinic performance');
        }
    }

    /**
     * Get comparative metrics across all clinics
     * 
     * @return array API response array
     */
    public function getClinicComparison(): array
    {
        if (!$this->validateSuperManagerAccess()) {
            return ApiResponse::forbidden('SuperManager access required');
        }

        try {
            $comparison = $this->superManagerRepository->getClinicComparison();

            return ApiResponse::success([
                'comparison' => $comparison,
                'count' => count($comparison),
            ]);
        } catch (\Exception $e) {
            error_log("SuperManagerViewModel::getClinicComparison error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve clinic comparison');
        }
    }

    // ========================================================================
    // Staff Methods
    // ========================================================================

    /**
     * Get staff overview with roles and compliance status
     * 
     * @param int $limit Maximum number of staff to return
     * @return array API response array
     */
    public function getStaffOverview(int $limit = 20): array
    {
        if (!$this->validateSuperManagerAccess()) {
            return ApiResponse::forbidden('SuperManager access required');
        }

        try {
            $staff = $this->superManagerRepository->getStaffOverview($limit);

            return ApiResponse::success([
                'staff' => $staff,
                'count' => count($staff),
            ]);
        } catch (\Exception $e) {
            error_log("SuperManagerViewModel::getStaffOverview error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve staff overview');
        }
    }

    /**
     * Get expiring credentials
     * 
     * @param int $daysAhead Days to look ahead
     * @param int $limit Maximum number of results
     * @return array API response array
     */
    public function getExpiringCredentials(int $daysAhead = 30, int $limit = 10): array
    {
        if (!$this->validateSuperManagerAccess()) {
            return ApiResponse::forbidden('SuperManager access required');
        }

        try {
            $credentials = $this->superManagerRepository->getExpiringCredentials($daysAhead, $limit);

            // Format for frontend
            $formattedCredentials = array_map(function($cred) {
                return [
                    'id' => $cred['id'],
                    'staffId' => $cred['staffId'],
                    'staffName' => $cred['staffName'],
                    'credential' => $cred['credential'],
                    'expiresAt' => $cred['expiresAt'],
                    'daysUntilExpiry' => $cred['daysUntilExpiry'],
                    'clinic' => $cred['clinic'],
                ];
            }, $credentials);

            return ApiResponse::success([
                'credentials' => $formattedCredentials,
                'count' => count($formattedCredentials),
            ]);
        } catch (\Exception $e) {
            error_log("SuperManagerViewModel::getExpiringCredentials error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve expiring credentials');
        }
    }

    /**
     * Get staff with overdue training
     * 
     * @param int $limit Maximum number of results
     * @return array API response array
     */
    public function getTrainingOverdue(int $limit = 10): array
    {
        if (!$this->validateSuperManagerAccess()) {
            return ApiResponse::forbidden('SuperManager access required');
        }

        try {
            $overdue = $this->superManagerRepository->getTrainingOverdue($limit);

            // Format for frontend
            $formattedOverdue = array_map(function($item) {
                return [
                    'id' => $item['id'],
                    'staffId' => $item['staffId'],
                    'staffName' => $item['staffName'],
                    'training' => $item['training'],
                    'dueDate' => $item['dueDate'],
                    'daysOverdue' => $item['daysOverdue'],
                    'clinic' => $item['clinic'],
                ];
            }, $overdue);

            return ApiResponse::success([
                'overdue' => $formattedOverdue,
                'count' => count($formattedOverdue),
            ]);
        } catch (\Exception $e) {
            error_log("SuperManagerViewModel::getTrainingOverdue error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve overdue training');
        }
    }

    // ========================================================================
    // Approval Methods
    // ========================================================================

    /**
     * Get pending approvals
     * 
     * @param int $limit Maximum number of results
     * @return array API response array
     */
    public function getPendingApprovals(int $limit = 10): array
    {
        if (!$this->validateSuperManagerAccess()) {
            return ApiResponse::forbidden('SuperManager access required');
        }

        try {
            $approvals = $this->superManagerRepository->getPendingApprovals($limit);

            // Format for frontend
            $formattedApprovals = array_map(function($item) {
                return [
                    'id' => $item['id'],
                    'type' => $item['type'],
                    'staffName' => $item['staffName'],
                    'requestDate' => $item['requestDate'],
                    'details' => $item['details'],
                    'status' => $item['status'],
                ];
            }, $approvals);

            return ApiResponse::success([
                'approvals' => $formattedApprovals,
                'count' => count($formattedApprovals),
            ]);
        } catch (\Exception $e) {
            error_log("SuperManagerViewModel::getPendingApprovals error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve pending approvals');
        }
    }

    /**
     * Approve a pending request
     * 
     * @param string $requestId Request ID
     * @param string $type Request type ('time_off', 'schedule_change', 'chart_review')
     * @return array API response array
     */
    public function approvePending(string $requestId, string $type): array
    {
        if (!$this->validateSuperManagerAccess()) {
            return ApiResponse::forbidden('SuperManager access required');
        }

        if (empty($requestId)) {
            return ApiResponse::badRequest('Request ID is required');
        }

        if (empty($type)) {
            return ApiResponse::badRequest('Request type is required');
        }

        // Validate request type
        $validTypes = ['time_off', 'schedule_change', 'chart_review'];
        if (!in_array($type, $validTypes, true)) {
            return ApiResponse::validationError([
                'type' => ['Invalid request type. Must be one of: ' . implode(', ', $validTypes)]
            ]);
        }

        try {
            $success = $this->superManagerRepository->approvePending(
                $requestId,
                $type,
                $this->currentUserId
            );

            if ($success) {
                return ApiResponse::success([
                    'message' => 'Request approved successfully',
                    'requestId' => $requestId,
                    'type' => $type,
                ]);
            }

            return ApiResponse::error('Failed to approve request. Request may already be processed or not found.');
        } catch (\Exception $e) {
            error_log("SuperManagerViewModel::approvePending error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to approve request');
        }
    }

    /**
     * Deny a pending request
     * 
     * @param string $requestId Request ID
     * @param string $type Request type ('time_off', 'schedule_change', 'chart_review')
     * @param string $reason Denial reason
     * @return array API response array
     */
    public function denyPending(string $requestId, string $type, string $reason = ''): array
    {
        if (!$this->validateSuperManagerAccess()) {
            return ApiResponse::forbidden('SuperManager access required');
        }

        if (empty($requestId)) {
            return ApiResponse::badRequest('Request ID is required');
        }

        if (empty($type)) {
            return ApiResponse::badRequest('Request type is required');
        }

        // Validate request type
        $validTypes = ['time_off', 'schedule_change', 'chart_review'];
        if (!in_array($type, $validTypes, true)) {
            return ApiResponse::validationError([
                'type' => ['Invalid request type. Must be one of: ' . implode(', ', $validTypes)]
            ]);
        }

        try {
            $success = $this->superManagerRepository->denyPending(
                $requestId,
                $type,
                $this->currentUserId,
                $reason
            );

            if ($success) {
                return ApiResponse::success([
                    'message' => 'Request denied successfully',
                    'requestId' => $requestId,
                    'type' => $type,
                ]);
            }

            return ApiResponse::error('Failed to deny request. Request may already be processed or not found.');
        } catch (\Exception $e) {
            error_log("SuperManagerViewModel::denyPending error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to deny request');
        }
    }
}
