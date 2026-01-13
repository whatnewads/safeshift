<?php
/**
 * SecurityOfficerViewModel.php - ViewModel for Security Officer Dashboard
 * 
 * Coordinates between the View (API) and Model (Repository) layers
 * for security officer dashboard operations including security statistics,
 * audit events, failed login attempts, MFA status, active sessions,
 * security alerts, and user devices.
 * 
 * @package SafeShift\ViewModel
 */

declare(strict_types=1);

namespace ViewModel;

use Model\Repositories\SecurityOfficerRepository;
use ViewModel\Core\ApiResponse;
use ViewModel\Core\BaseViewModel;
use PDO;
use Exception;

/**
 * Security Officer ViewModel
 * 
 * Handles business logic for the security officer dashboard vertical slice.
 * Provides methods for security statistics, audit event analysis, failed login
 * monitoring, MFA compliance, active session management, and security alerts.
 */
class SecurityOfficerViewModel extends BaseViewModel
{
    /** @var SecurityOfficerRepository Repository for security officer data */
    private SecurityOfficerRepository $securityOfficerRepository;
    
    /** @var array Allowed roles for security officer access */
    private const ALLOWED_ROLES = ['SecurityOfficer', 'tadmin', 'cadmin', 'Admin'];

    /**
     * Constructor
     * 
     * @param PDO|null $pdo Database connection
     */
    public function __construct(?PDO $pdo = null)
    {
        parent::__construct(null, $pdo);
        
        if ($this->pdo) {
            $this->securityOfficerRepository = new SecurityOfficerRepository($this->pdo);
        }
    }

    /**
     * Validate security officer role access
     * 
     * @throws Exception If user doesn't have security officer access
     */
    public function validateSecurityOfficerAccess(): void
    {
        $this->requireAuth();
        
        $userRole = $this->getCurrentUserRole();
        
        if (!in_array($userRole, self::ALLOWED_ROLES)) {
            $this->audit('UNAUTHORIZED_ACCESS', 'security_officer_dashboard', null, [
                'attempted_role' => $userRole
            ]);
            throw new Exception('Access denied. Security Officer role required.', 403);
        }
    }

    /**
     * Get complete dashboard data
     * 
     * Combines security stats, audit events, failed logins, MFA status,
     * active sessions, and security alerts for the full security officer dashboard view.
     * 
     * @return array API response with dashboard data
     */
    public function getDashboardData(): array
    {
        try {
            $this->validateSecurityOfficerAccess();
            
            // Verify repository is initialized
            if (!isset($this->securityOfficerRepository)) {
                return ApiResponse::serverError('Database connection not available');
            }
            
            // Get security statistics
            $stats = $this->securityOfficerRepository->getSecurityStats();
            
            // Get audit events (limited for dashboard)
            $auditEvents = $this->securityOfficerRepository->getAuditEvents(10);
            
            // Get failed login attempts
            $failedLogins = $this->securityOfficerRepository->getFailedLoginAttempts(10);
            
            // Get MFA status
            $mfaStatus = $this->securityOfficerRepository->getMFAStatus();
            
            // Get active sessions
            $activeSessions = $this->securityOfficerRepository->getActiveSessions(10);
            
            // Get security alerts
            $securityAlerts = $this->securityOfficerRepository->getSecurityAlerts(10);
            
            // Log dashboard access for audit
            $this->audit('VIEW', 'security_officer_dashboard', null, [
                'stats' => $stats,
                'audit_events_count' => count($auditEvents),
                'failed_logins_count' => count($failedLogins),
                'active_sessions_count' => count($activeSessions),
                'alerts_count' => count($securityAlerts)
            ]);
            
            return ApiResponse::success([
                'stats' => $stats,
                'auditEvents' => $auditEvents,
                'failedLogins' => $failedLogins,
                'mfaStatus' => $mfaStatus,
                'activeSessions' => $activeSessions,
                'securityAlerts' => $securityAlerts
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'Failed to retrieve security officer dashboard data');
        }
    }

    /**
     * Get security statistics only
     * 
     * Returns just the security metrics without other data.
     * Useful for quick status updates or polling.
     * 
     * @return array API response with security stats
     */
    public function getSecurityStats(): array
    {
        try {
            $this->validateSecurityOfficerAccess();
            
            if (!isset($this->securityOfficerRepository)) {
                return ApiResponse::serverError('Database connection not available');
            }
            
            $stats = $this->securityOfficerRepository->getSecurityStats();
            
            return ApiResponse::success([
                'stats' => $stats
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'Failed to retrieve security statistics');
        }
    }

    /**
     * Get audit events
     * 
     * Returns security-related audit events for monitoring.
     * 
     * @param int $limit Maximum number of results
     * @return array API response with audit events
     */
    public function getAuditEvents(int $limit = 20): array
    {
        try {
            $this->validateSecurityOfficerAccess();
            
            if (!isset($this->securityOfficerRepository)) {
                return ApiResponse::serverError('Database connection not available');
            }
            
            // Validate limit
            $limit = min(max(1, $limit), 100);
            
            $auditEvents = $this->securityOfficerRepository->getAuditEvents($limit);
            
            // Log audit event viewing
            $this->audit('VIEW', 'audit_events', null, [
                'records_viewed' => count($auditEvents),
                'limit' => $limit
            ]);
            
            return ApiResponse::success([
                'auditEvents' => $auditEvents,
                'count' => count($auditEvents)
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'Failed to retrieve audit events');
        }
    }

    /**
     * Get failed login attempts
     * 
     * Returns recent failed login attempts for security monitoring.
     * 
     * @param int $limit Maximum number of results
     * @return array API response with failed login attempts
     */
    public function getFailedLoginAttempts(int $limit = 20): array
    {
        try {
            $this->validateSecurityOfficerAccess();
            
            if (!isset($this->securityOfficerRepository)) {
                return ApiResponse::serverError('Database connection not available');
            }
            
            // Validate limit
            $limit = min(max(1, $limit), 100);
            
            $failedLogins = $this->securityOfficerRepository->getFailedLoginAttempts($limit);
            
            // Log failed login viewing for audit
            $this->audit('VIEW', 'failed_logins', null, [
                'records_viewed' => count($failedLogins),
                'limit' => $limit
            ]);
            
            return ApiResponse::success([
                'failedLogins' => $failedLogins,
                'count' => count($failedLogins)
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'Failed to retrieve failed login attempts');
        }
    }

    /**
     * Get MFA status
     * 
     * Returns MFA enrollment and compliance status across all users.
     * 
     * @return array API response with MFA status
     */
    public function getMFAStatus(): array
    {
        try {
            $this->validateSecurityOfficerAccess();
            
            if (!isset($this->securityOfficerRepository)) {
                return ApiResponse::serverError('Database connection not available');
            }
            
            $mfaStatus = $this->securityOfficerRepository->getMFAStatus();
            
            // Log MFA status review for audit
            $this->audit('VIEW', 'mfa_status', null, [
                'enabled' => $mfaStatus['enabled'],
                'disabled' => $mfaStatus['disabled'],
                'pending' => $mfaStatus['pending'],
                'compliance_rate' => $mfaStatus['complianceRate']
            ]);
            
            return ApiResponse::success([
                'mfaStatus' => $mfaStatus
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'Failed to retrieve MFA status');
        }
    }

    /**
     * Get active sessions
     * 
     * Returns currently active user sessions.
     * 
     * @param int $limit Maximum number of results
     * @return array API response with active sessions
     */
    public function getActiveSessions(int $limit = 20): array
    {
        try {
            $this->validateSecurityOfficerAccess();
            
            if (!isset($this->securityOfficerRepository)) {
                return ApiResponse::serverError('Database connection not available');
            }
            
            // Validate limit
            $limit = min(max(1, $limit), 100);
            
            $activeSessions = $this->securityOfficerRepository->getActiveSessions($limit);
            
            // Log session viewing for audit
            $this->audit('VIEW', 'active_sessions', null, [
                'records_viewed' => count($activeSessions),
                'limit' => $limit
            ]);
            
            return ApiResponse::success([
                'activeSessions' => $activeSessions,
                'count' => count($activeSessions)
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'Failed to retrieve active sessions');
        }
    }

    /**
     * Get security alerts
     * 
     * Returns security alerts and anomalies.
     * 
     * @param int $limit Maximum number of results
     * @return array API response with security alerts
     */
    public function getSecurityAlerts(int $limit = 20): array
    {
        try {
            $this->validateSecurityOfficerAccess();
            
            if (!isset($this->securityOfficerRepository)) {
                return ApiResponse::serverError('Database connection not available');
            }
            
            // Validate limit
            $limit = min(max(1, $limit), 100);
            
            $alerts = $this->securityOfficerRepository->getSecurityAlerts($limit);
            
            // Log alert viewing for audit
            $this->audit('VIEW', 'security_alerts', null, [
                'records_viewed' => count($alerts),
                'limit' => $limit
            ]);
            
            return ApiResponse::success([
                'securityAlerts' => $alerts,
                'count' => count($alerts)
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'Failed to retrieve security alerts');
        }
    }

    /**
     * Get user devices
     * 
     * Returns registered user devices with security status.
     * 
     * @param int $limit Maximum number of results
     * @return array API response with user devices
     */
    public function getUserDevices(int $limit = 20): array
    {
        try {
            $this->validateSecurityOfficerAccess();
            
            if (!isset($this->securityOfficerRepository)) {
                return ApiResponse::serverError('Database connection not available');
            }
            
            // Validate limit
            $limit = min(max(1, $limit), 100);
            
            $devices = $this->securityOfficerRepository->getUserDevices($limit);
            
            // Log device viewing for audit
            $this->audit('VIEW', 'user_devices', null, [
                'records_viewed' => count($devices),
                'limit' => $limit
            ]);
            
            return ApiResponse::success([
                'userDevices' => $devices,
                'count' => count($devices)
            ]);
        } catch (Exception $e) {
            return $this->handleException($e, 'Failed to retrieve user devices');
        }
    }
}
