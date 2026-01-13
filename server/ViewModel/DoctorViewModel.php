<?php
/**
 * DoctorViewModel.php - ViewModel for Doctor (MRO) Dashboard
 * 
 * Coordinates between the View (API) and Model (Repository) layers
 * for Doctor/MRO dashboard operations including DOT test verifications,
 * pending orders, and verification history.
 * 
 * The Doctor role is associated with 'pclinician' role type (provider clinician)
 * and serves as the MRO interface for DOT drug testing, result verification,
 * and order signing.
 * 
 * @package    SafeShift\ViewModel
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace ViewModel;

use Model\Repositories\DoctorRepository;
use ViewModel\Core\ApiResponse;
use PDO;

/**
 * Doctor ViewModel
 * 
 * Coordinates between the View (API) and Model (Repository) layers
 * for Doctor/MRO dashboard operations.
 */
class DoctorViewModel
{
    private DoctorRepository $doctorRepository;
    private ?string $currentUserId = null;
    private array $currentUserRoles = [];

    /**
     * Constructor
     * 
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo)
    {
        $this->doctorRepository = new DoctorRepository($pdo);
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
     * Validate that the current user has doctor/MRO access
     * 
     * Doctor access requires one of: pclinician, Admin, doctor, mro
     * 
     * @return bool True if user has valid doctor access
     */
    public function validateDoctorAccess(): bool
    {
        if (empty($this->currentUserId)) {
            return false;
        }

        $validRoles = ['pclinician', 'Admin', 'doctor', 'mro', 'cadmin', 'tadmin'];
        
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
     * Get complete Doctor Dashboard data
     * 
     * Returns comprehensive dashboard data including stats, pending verifications,
     * pending orders, and verification history.
     * 
     * @return array API response array
     */
    public function getDashboardData(): array
    {
        if (!$this->validateDoctorAccess()) {
            return ApiResponse::forbidden('Doctor/MRO access required');
        }

        try {
            // Get doctor stats
            $stats = $this->doctorRepository->getDoctorStats($this->currentUserId);
            
            // Get tests reviewed this month for additional stat
            $testsThisMonth = $this->doctorRepository->getTestsReviewedThisMonth($this->currentUserId);
            
            // Get pending verifications
            $pendingVerifications = $this->doctorRepository->getPendingVerifications($this->currentUserId, 10);
            
            // Get pending orders
            $pendingOrders = $this->doctorRepository->getPendingOrders($this->currentUserId, 10);
            
            // Get verification history
            $verificationHistory = $this->doctorRepository->getVerificationHistory($this->currentUserId, 10);

            return ApiResponse::success([
                'stats' => [
                    'pendingVerifications' => $stats['pendingVerifications'],
                    'ordersToSign' => $stats['ordersToSign'],
                    'reviewedToday' => $stats['reviewedToday'],
                    'avgTurnaroundHours' => $stats['avgTurnaroundHours'],
                    'testsReviewedThisMonth' => $testsThisMonth,
                ],
                'pendingVerifications' => $pendingVerifications,
                'pendingOrders' => $pendingOrders,
                'verificationHistory' => $verificationHistory,
            ]);
        } catch (\Exception $e) {
            error_log("DoctorViewModel::getDashboardData error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve doctor dashboard data');
        }
    }

    /**
     * Get doctor statistics only
     * 
     * @return array API response array
     */
    public function getDoctorStats(): array
    {
        if (!$this->validateDoctorAccess()) {
            return ApiResponse::forbidden('Doctor/MRO access required');
        }

        try {
            $stats = $this->doctorRepository->getDoctorStats($this->currentUserId);
            $testsThisMonth = $this->doctorRepository->getTestsReviewedThisMonth($this->currentUserId);

            return ApiResponse::success([
                'stats' => [
                    'pendingVerifications' => $stats['pendingVerifications'],
                    'ordersToSign' => $stats['ordersToSign'],
                    'reviewedToday' => $stats['reviewedToday'],
                    'avgTurnaroundHours' => $stats['avgTurnaroundHours'],
                    'testsReviewedThisMonth' => $testsThisMonth,
                ],
            ]);
        } catch (\Exception $e) {
            error_log("DoctorViewModel::getDoctorStats error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve doctor statistics');
        }
    }

    // ========================================================================
    // Verification Methods
    // ========================================================================

    /**
     * Get pending DOT test verifications
     * 
     * @param int $limit Maximum number of results
     * @return array API response array
     */
    public function getPendingVerifications(int $limit = 20): array
    {
        if (!$this->validateDoctorAccess()) {
            return ApiResponse::forbidden('Doctor/MRO access required');
        }

        try {
            $verifications = $this->doctorRepository->getPendingVerifications($this->currentUserId, $limit);

            return ApiResponse::success([
                'verifications' => $verifications,
                'count' => count($verifications),
            ]);
        } catch (\Exception $e) {
            error_log("DoctorViewModel::getPendingVerifications error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve pending verifications');
        }
    }

    /**
     * Get verification history
     * 
     * @param int $limit Maximum number of results
     * @return array API response array
     */
    public function getVerificationHistory(int $limit = 20): array
    {
        if (!$this->validateDoctorAccess()) {
            return ApiResponse::forbidden('Doctor/MRO access required');
        }

        try {
            $history = $this->doctorRepository->getVerificationHistory($this->currentUserId, $limit);

            return ApiResponse::success([
                'history' => $history,
                'count' => count($history),
            ]);
        } catch (\Exception $e) {
            error_log("DoctorViewModel::getVerificationHistory error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve verification history');
        }
    }

    /**
     * Get detailed test information for review
     * 
     * @param string $testId Test UUID
     * @return array API response array
     */
    public function getTestDetails(string $testId): array
    {
        if (!$this->validateDoctorAccess()) {
            return ApiResponse::forbidden('Doctor/MRO access required');
        }

        if (empty($testId)) {
            return ApiResponse::badRequest('Test ID is required');
        }

        try {
            $details = $this->doctorRepository->getTestDetails($testId);

            if ($details === null) {
                return ApiResponse::notFound('Test not found');
            }

            return ApiResponse::success([
                'test' => $details,
            ]);
        } catch (\Exception $e) {
            error_log("DoctorViewModel::getTestDetails error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve test details');
        }
    }

    /**
     * Submit MRO verification for a DOT test
     * 
     * @param string $testId Test UUID
     * @param string $result Verification result (negative, positive, cancelled, etc.)
     * @param string|null $comments MRO comments
     * @return array API response array
     */
    public function verifyTest(string $testId, string $result, ?string $comments = null): array
    {
        if (!$this->validateDoctorAccess()) {
            return ApiResponse::forbidden('Doctor/MRO access required');
        }

        if (empty($testId)) {
            return ApiResponse::badRequest('Test ID is required');
        }

        if (empty($result)) {
            return ApiResponse::badRequest('Verification result is required');
        }

        // Validate result value
        $validResults = ['negative', 'positive', 'cancelled', 'invalid', 'dilute', 'substituted', 'adulterated', 'refused'];
        if (!in_array(strtolower($result), $validResults, true)) {
            return ApiResponse::validationError([
                'result' => ['Invalid verification result. Must be one of: ' . implode(', ', $validResults)]
            ]);
        }

        try {
            $success = $this->doctorRepository->verifyTest(
                $testId,
                $this->currentUserId,
                strtolower($result),
                $comments
            );

            if ($success) {
                return ApiResponse::success([
                    'message' => 'Test verified successfully',
                    'testId' => $testId,
                    'result' => strtolower($result),
                ]);
            }

            return ApiResponse::error('Failed to verify test. Test may already be verified or not found.');
        } catch (\Exception $e) {
            error_log("DoctorViewModel::verifyTest error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to submit verification');
        }
    }

    // ========================================================================
    // Order Methods
    // ========================================================================

    /**
     * Get pending orders requiring signature
     * 
     * @param int $limit Maximum number of results
     * @return array API response array
     */
    public function getPendingOrders(int $limit = 20): array
    {
        if (!$this->validateDoctorAccess()) {
            return ApiResponse::forbidden('Doctor/MRO access required');
        }

        try {
            $orders = $this->doctorRepository->getPendingOrders($this->currentUserId, $limit);

            return ApiResponse::success([
                'orders' => $orders,
                'count' => count($orders),
            ]);
        } catch (\Exception $e) {
            error_log("DoctorViewModel::getPendingOrders error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to retrieve pending orders');
        }
    }

    /**
     * Sign an order
     * 
     * @param string $orderId Order ID
     * @return array API response array
     */
    public function signOrder(string $orderId): array
    {
        if (!$this->validateDoctorAccess()) {
            return ApiResponse::forbidden('Doctor/MRO access required');
        }

        if (empty($orderId)) {
            return ApiResponse::badRequest('Order ID is required');
        }

        try {
            $success = $this->doctorRepository->signOrder($orderId, $this->currentUserId);

            if ($success) {
                return ApiResponse::success([
                    'message' => 'Order signed successfully',
                    'orderId' => $orderId,
                ]);
            }

            return ApiResponse::error('Failed to sign order. Order may already be signed or not found.');
        } catch (\Exception $e) {
            error_log("DoctorViewModel::signOrder error: " . $e->getMessage());
            return ApiResponse::serverError('Failed to sign order');
        }
    }
}
