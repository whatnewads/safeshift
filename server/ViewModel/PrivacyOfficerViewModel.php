<?php
/**
 * PrivacyOfficerViewModel.php - ViewModel for Privacy Officer Dashboard
 * 
 * Coordinates between the View (API) and Model (Repository) layers
 * for privacy officer dashboard operations including compliance KPIs,
 * PHI access logs, consent management, and regulatory updates.
 * 
 * @package SafeShift\ViewModel
 */

declare(strict_types=1);

namespace ViewModel;

use Model\Repositories\PrivacyOfficerRepository;
use ViewModel\Core\ApiResponse;
use ViewModel\Core\BaseViewModel;
use PDO;
use Exception;

/**
 * Privacy Officer ViewModel
 * 
 * Handles business logic for the privacy officer dashboard vertical slice.
 * Provides methods for compliance KPIs, PHI access audit logs, consent status,
 * regulatory updates, and training compliance tracking.
 */
class PrivacyOfficerViewModel extends BaseViewModel
{
    /** @var PrivacyOfficerRepository Repository for privacy officer data */
    private PrivacyOfficerRepository $privacyOfficerRepository;
    
    /** @var array Allowed roles for privacy officer access */
    private const ALLOWED_ROLES = ['PrivacyOfficer', 'tadmin', 'cadmin', 'Admin'];

    /**
     * Constructor
     * 
     * @param PDO|null $pdo Database connection
     */
    public function __construct(?PDO $pdo = null)
    {
        parent::__construct(null, $pdo);
        
        if ($this->pdo) {
            $this->privacyOfficerRepository = new PrivacyOfficerRepository($this->pdo);
        }
    }

    /**
     * Validate privacy officer role access
     * 
     * @throws Exception If user doesn't have privacy officer access
     */
    private function validatePrivacyOfficerAccess(): void
    {
        $this->requireAuth();
        
        $userRole = $this->getCurrentUserRole();
        
        if (!in_array($userRole, self::ALLOWED_ROLES)) {
            $this->audit('UNAUTHORIZED_ACCESS', 'privacy_officer_dashboard', null, [
                'attempted_role' => $userRole
            ]);
            throw new Exception('Access denied. Privacy Officer role required.', 403);
        }
    }

    /**
     * Get complete dashboard data
     * 
     * Combines compliance KPIs, PHI access logs, consent status,
     * and regulatory updates for the full privacy officer dashboard view.
     * 
     * @return array API response with dashboard data
     */
    public function getDashboardData(): array
    {
        try {
            $this->validatePrivacyOfficerAccess();
            
            // Verify repository is initialized
            if (!isset($this->privacyOfficerRepository)) {
                return ApiResponse::serverError('Database connection not available');
            }
            
            // Get compliance KPIs
            $complianceKPIs = $this->privacyOfficerRepository->getComplianceKPIs();
            
            // Get PHI access logs (limited for dashboard)
            $phiAccessLogs = $this->privacyOfficerRepository->getPHIAccessLogs(10);
            
            // Get consent status overview
            $consentStatus = $this->privacyOfficerRepository->getConsentStatus(10);
            
            // Get regulatory updates
            $regulatoryUpdates = $this->privacyOfficerRepository->getRegulatoryUpdates(5);
            
            // Get training compliance stats
            $trainingCompliance = $this->privacyOfficerRepository->getTrainingCompliance(10);
            
            // Log dashboard access for audit
            $this->audit('VIEW', 'privacy_officer_dashboard', null, [
                'phi_access_logs_count' => count($phiAccessLogs),
                'consent_status_count' => count($consentStatus),
                'regulatory_updates_count' => count($regulatoryUpdates),
                'compliance_kpis' => $complianceKPIs
            ]);
            
            return ApiResponse::success([
                'complianceKPIs' => $complianceKPIs,
                'phiAccessLogs' => $phiAccessLogs,
                'consentStatus' => $consentStatus,
                'regulatoryUpdates' => $regulatoryUpdates,
                'trainingCompliance' => $trainingCompliance
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'Failed to retrieve privacy officer dashboard data');
        }
    }

    /**
     * Get compliance KPIs only
     * 
     * Returns just the compliance metrics without other data.
     * Useful for quick status updates or polling.
     * 
     * @return array API response with compliance KPIs
     */
    public function getComplianceKPIs(): array
    {
        try {
            $this->validatePrivacyOfficerAccess();
            
            if (!isset($this->privacyOfficerRepository)) {
                return ApiResponse::serverError('Database connection not available');
            }
            
            $kpis = $this->privacyOfficerRepository->getComplianceKPIs();
            
            return ApiResponse::success([
                'complianceKPIs' => $kpis
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'Failed to retrieve compliance KPIs');
        }
    }

    /**
     * Get PHI access logs
     * 
     * Returns PHI access audit logs for compliance monitoring.
     * 
     * @param int $limit Maximum number of results
     * @return array API response with PHI access logs
     */
    public function getPHIAccessLogs(int $limit = 20): array
    {
        try {
            $this->validatePrivacyOfficerAccess();
            
            if (!isset($this->privacyOfficerRepository)) {
                return ApiResponse::serverError('Database connection not available');
            }
            
            // Validate limit
            $limit = min(max(1, $limit), 100);
            
            $accessLogs = $this->privacyOfficerRepository->getPHIAccessLogs($limit);
            
            // Log PHI access log viewing for compliance
            $this->audit('VIEW', 'phi_access_logs', null, [
                'records_viewed' => count($accessLogs),
                'limit' => $limit
            ]);
            
            return ApiResponse::success([
                'phiAccessLogs' => $accessLogs,
                'count' => count($accessLogs)
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'Failed to retrieve PHI access logs');
        }
    }

    /**
     * Get consent status overview
     * 
     * Returns patient consent records with their current status.
     * 
     * @param int $limit Maximum number of results
     * @return array API response with consent status
     */
    public function getConsentStatus(int $limit = 20): array
    {
        try {
            $this->validatePrivacyOfficerAccess();
            
            if (!isset($this->privacyOfficerRepository)) {
                return ApiResponse::serverError('Database connection not available');
            }
            
            // Validate limit
            $limit = min(max(1, $limit), 100);
            
            $consentStatus = $this->privacyOfficerRepository->getConsentStatus($limit);
            
            // Log consent review for audit - no PHI in response
            $this->audit('VIEW', 'consent_status', null, [
                'records_viewed' => count($consentStatus),
                'limit' => $limit
            ]);
            
            return ApiResponse::success([
                'consentStatus' => $consentStatus,
                'count' => count($consentStatus)
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'Failed to retrieve consent status');
        }
    }

    /**
     * Get regulatory updates
     * 
     * Returns pending HIPAA/regulatory updates that need attention.
     * 
     * @param int $limit Maximum number of results
     * @return array API response with regulatory updates
     */
    public function getRegulatoryUpdates(int $limit = 10): array
    {
        try {
            $this->validatePrivacyOfficerAccess();
            
            if (!isset($this->privacyOfficerRepository)) {
                return ApiResponse::serverError('Database connection not available');
            }
            
            // Validate limit
            $limit = min(max(1, $limit), 50);
            
            $updates = $this->privacyOfficerRepository->getRegulatoryUpdates($limit);
            
            return ApiResponse::success([
                'regulatoryUpdates' => $updates,
                'count' => count($updates)
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'Failed to retrieve regulatory updates');
        }
    }

    /**
     * Get breach incidents
     * 
     * Returns security breach incident records.
     * 
     * @param int $limit Maximum number of results
     * @return array API response with breach incidents
     */
    public function getBreachIncidents(int $limit = 10): array
    {
        try {
            $this->validatePrivacyOfficerAccess();
            
            if (!isset($this->privacyOfficerRepository)) {
                return ApiResponse::serverError('Database connection not available');
            }
            
            // Validate limit
            $limit = min(max(1, $limit), 50);
            
            $incidents = $this->privacyOfficerRepository->getBreachIncidents($limit);
            
            // Log breach incident review for audit
            $this->audit('VIEW', 'breach_incidents', null, [
                'records_viewed' => count($incidents),
                'limit' => $limit
            ]);
            
            return ApiResponse::success([
                'breachIncidents' => $incidents,
                'count' => count($incidents)
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'Failed to retrieve breach incidents');
        }
    }

    /**
     * Get training compliance data
     * 
     * Returns staff HIPAA training compliance status and records.
     * 
     * @param int $limit Maximum number of records
     * @return array API response with training compliance
     */
    public function getTrainingCompliance(int $limit = 20): array
    {
        try {
            $this->validatePrivacyOfficerAccess();
            
            if (!isset($this->privacyOfficerRepository)) {
                return ApiResponse::serverError('Database connection not available');
            }
            
            // Validate limit
            $limit = min(max(1, $limit), 100);
            
            $trainingData = $this->privacyOfficerRepository->getTrainingCompliance($limit);
            
            return ApiResponse::success([
                'trainingStats' => $trainingData['stats'],
                'trainingRecords' => $trainingData['records'],
                'count' => count($trainingData['records'])
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'Failed to retrieve training compliance data');
        }
    }
}
