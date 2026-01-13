<?php
/**
 * ClinicalProviderViewModel.php - ViewModel for Clinical Provider Dashboard
 * 
 * Coordinates between the View (API) and Model (Repository) layers
 * for clinical provider dashboard operations including encounters,
 * orders, and QA review workflows.
 * 
 * @package SafeShift\ViewModel
 */

declare(strict_types=1);

namespace ViewModel;

use Model\Repositories\ClinicalProviderRepository;
use ViewModel\Core\ApiResponse;
use ViewModel\Core\BaseViewModel;
use PDO;
use Exception;

/**
 * Clinical Provider ViewModel
 * 
 * Handles business logic for the clinical provider dashboard vertical slice.
 * Provides methods for dashboard data, active/recent encounters, pending orders,
 * and QA review functionality.
 */
class ClinicalProviderViewModel extends BaseViewModel
{
    /** @var ClinicalProviderRepository Repository for clinical provider data */
    private ClinicalProviderRepository $clinicalProviderRepository;

    /**
     * Constructor
     * 
     * @param PDO|null $pdo Database connection
     */
    public function __construct(?PDO $pdo = null)
    {
        parent::__construct(null, $pdo);
        
        if ($this->pdo) {
            $this->clinicalProviderRepository = new ClinicalProviderRepository($this->pdo);
        }
    }

    /**
     * Get complete dashboard data
     * 
     * Combines provider statistics with active encounters, recent encounters,
     * and pending orders for the full clinical provider dashboard view.
     * 
     * @return array API response with dashboard data
     */
    public function getDashboardData(): array
    {
        try {
            $this->requireAuth();
            
            // Verify repository is initialized
            if (!isset($this->clinicalProviderRepository)) {
                return ApiResponse::serverError('Database connection not available');
            }
            
            $providerId = $this->getCurrentUserId();
            
            if (!$providerId) {
                return ApiResponse::unauthorized('Provider ID not found in session');
            }
            
            // Get provider statistics
            $stats = $this->clinicalProviderRepository->getProviderStats($providerId);
            
            // Get active encounters
            $activeEncounters = $this->clinicalProviderRepository->getActiveEncounters($providerId, 10);
            
            // Get recent encounters
            $recentEncounters = $this->clinicalProviderRepository->getRecentEncounters($providerId, 5);
            
            // Get pending orders
            $pendingOrders = $this->clinicalProviderRepository->getPendingOrders($providerId, 10);
            
            // Log dashboard access for audit
            $this->audit('VIEW', 'clinicalprovider_dashboard', null, [
                'active_encounters_count' => count($activeEncounters),
                'recent_encounters_count' => count($recentEncounters),
                'pending_orders_count' => count($pendingOrders),
                'stats' => $stats
            ]);
            
            return ApiResponse::success([
                'stats' => $stats,
                'activeEncounters' => $activeEncounters,
                'recentEncounters' => $recentEncounters,
                'pendingOrders' => $pendingOrders
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'Failed to retrieve dashboard data');
        }
    }

    /**
     * Get provider statistics only
     * 
     * Returns just the statistics without encounter lists.
     * Useful for quick status updates or polling.
     * 
     * @return array API response with provider stats
     */
    public function getStats(): array
    {
        try {
            $this->requireAuth();
            
            if (!isset($this->clinicalProviderRepository)) {
                return ApiResponse::serverError('Database connection not available');
            }
            
            $providerId = $this->getCurrentUserId();
            
            if (!$providerId) {
                return ApiResponse::unauthorized('Provider ID not found in session');
            }
            
            $stats = $this->clinicalProviderRepository->getProviderStats($providerId);
            
            return ApiResponse::success([
                'stats' => $stats
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'Failed to retrieve provider statistics');
        }
    }

    /**
     * Get active encounters
     * 
     * Returns list of encounters currently in progress or awaiting provider,
     * with priority assignments based on wait time.
     * 
     * @param int $limit Maximum number of results
     * @return array API response with active encounters
     */
    public function getActiveEncounters(int $limit = 10): array
    {
        try {
            $this->requireAuth();
            
            if (!isset($this->clinicalProviderRepository)) {
                return ApiResponse::serverError('Database connection not available');
            }
            
            $providerId = $this->getCurrentUserId();
            
            if (!$providerId) {
                return ApiResponse::unauthorized('Provider ID not found in session');
            }
            
            // Validate limit
            $limit = min(max(1, $limit), 50);
            
            $activeEncounters = $this->clinicalProviderRepository->getActiveEncounters($providerId, $limit);
            
            return ApiResponse::success([
                'activeEncounters' => $activeEncounters,
                'count' => count($activeEncounters)
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'Failed to retrieve active encounters');
        }
    }

    /**
     * Get recent encounters
     * 
     * Returns recently completed encounters for follow-up purposes.
     * 
     * @param int $limit Maximum number of results
     * @return array API response with recent encounters
     */
    public function getRecentEncounters(int $limit = 10): array
    {
        try {
            $this->requireAuth();
            
            if (!isset($this->clinicalProviderRepository)) {
                return ApiResponse::serverError('Database connection not available');
            }
            
            $providerId = $this->getCurrentUserId();
            
            if (!$providerId) {
                return ApiResponse::unauthorized('Provider ID not found in session');
            }
            
            // Validate limit
            $limit = min(max(1, $limit), 50);
            
            $recentEncounters = $this->clinicalProviderRepository->getRecentEncounters($providerId, $limit);
            
            // Log PHI access for HIPAA compliance
            foreach ($recentEncounters as $encounter) {
                $this->logPhiAccess('encounter', $encounter['id'], 'view');
            }
            
            return ApiResponse::success([
                'recentEncounters' => $recentEncounters,
                'count' => count($recentEncounters)
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'Failed to retrieve recent encounters');
        }
    }

    /**
     * Get pending orders
     * 
     * Returns orders awaiting provider signature or review.
     * 
     * @param int $limit Maximum number of results
     * @return array API response with pending orders
     */
    public function getPendingOrders(int $limit = 10): array
    {
        try {
            $this->requireAuth();
            
            if (!isset($this->clinicalProviderRepository)) {
                return ApiResponse::serverError('Database connection not available');
            }
            
            $providerId = $this->getCurrentUserId();
            
            if (!$providerId) {
                return ApiResponse::unauthorized('Provider ID not found in session');
            }
            
            // Validate limit
            $limit = min(max(1, $limit), 50);
            
            $pendingOrders = $this->clinicalProviderRepository->getPendingOrders($providerId, $limit);
            
            return ApiResponse::success([
                'pendingOrders' => $pendingOrders,
                'count' => count($pendingOrders)
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'Failed to retrieve pending orders');
        }
    }

    /**
     * Get pending QA reviews
     * 
     * Returns QA review items assigned to or available for the provider.
     * 
     * @param int $limit Maximum number of results
     * @return array API response with pending QA reviews
     */
    public function getPendingQAReviews(int $limit = 10): array
    {
        try {
            $this->requireAuth();
            
            if (!isset($this->clinicalProviderRepository)) {
                return ApiResponse::serverError('Database connection not available');
            }
            
            $providerId = $this->getCurrentUserId();
            
            if (!$providerId) {
                return ApiResponse::unauthorized('Provider ID not found in session');
            }
            
            // Validate limit
            $limit = min(max(1, $limit), 50);
            
            $qaReviews = $this->clinicalProviderRepository->getPendingQAReviews($providerId, $limit);
            
            return ApiResponse::success([
                'qaReviews' => $qaReviews,
                'count' => count($qaReviews)
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'Failed to retrieve QA reviews');
        }
    }

    /**
     * Format encounter data for API response
     * 
     * Transforms raw encounter data from the database into the
     * format expected by the frontend API.
     * 
     * @param array $encounter Raw encounter data
     * @return array Formatted encounter data
     */
    public function formatEncounterForResponse(array $encounter): array
    {
        return [
            'id' => $encounter['id'] ?? $encounter['encounter_id'] ?? null,
            'patientName' => $encounter['patientName'] ?? $encounter['patient'] ?? '',
            'chiefComplaint' => $encounter['chiefComplaint'] ?? $encounter['chief_complaint'] ?? null,
            'status' => $encounter['status'] ?? 'unknown',
            'startTime' => $encounter['startTime'] ?? $encounter['arrived_on'] ?? null,
            'priority' => $encounter['priority'] ?? 'normal',
            'encounterType' => $encounter['encounterType'] ?? $encounter['encounter_type'] ?? 'clinic'
        ];
    }
}
