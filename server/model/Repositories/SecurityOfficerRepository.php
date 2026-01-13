<?php
/**
 * SecurityOfficerRepository.php - Repository for Security Officer Dashboard Data
 * 
 * Provides data access methods for the security officer dashboard including
 * security statistics, audit events, failed login attempts, MFA status,
 * active sessions, security alerts, and user devices.
 * 
 * @package    SafeShift\Model\Repositories
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace Model\Repositories;

use PDO;
use PDOException;
use DateTime;

/**
 * Security Officer Repository
 * 
 * Handles database operations for the security officer dashboard vertical slice.
 * Provides security metrics, audit trail analysis, MFA compliance tracking,
 * failed login monitoring, and active session management.
 */
class SecurityOfficerRepository
{
    private PDO $pdo;
    
    /** @var string User table name */
    private string $userTable = 'user';
    
    /** @var string Audit log table name */
    private string $auditLogTable = 'audit_log';
    
    /** @var string Audit event table name */
    private string $auditEventTable = 'auditevent';
    
    /** @var string Login OTP table name */
    private string $loginOtpTable = 'login_otp';
    
    /** @var string User device table name */
    private string $userDeviceTable = 'user_device';
    
    /** @var string Compliance alerts table name */
    private string $complianceAlertsTable = 'compliance_alerts';
    
    /** @var string User role table name */
    private string $userRoleTable = 'userrole';
    
    /** @var string Role table name */
    private string $roleTable = 'role';

    /**
     * Constructor
     * 
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get security statistics for the dashboard
     * 
     * Returns key security metrics:
     * - failedLogins24h: failed login attempts in last 24 hours
     * - activeSessions: currently active user sessions (users logged in within 30 min)
     * - mfaCompliance: percentage of users with MFA enabled
     * - anomaliesDetected: flagged security events
     * - totalUsers: total active users
     * - usersWithMFA: users with MFA enabled
     * - lockedAccounts: accounts currently locked
     * 
     * @return array{failedLogins24h: int, activeSessions: int, mfaCompliance: float, anomaliesDetected: int, totalUsers: int, usersWithMFA: int, lockedAccounts: int}
     */
    public function getSecurityStats(): array
    {
        try {
            // Get failed logins in last 24 hours from audit events
            $failedLogins24h = $this->getFailedLoginCount24h();
            
            // Get active sessions (users who logged in within last 30 minutes)
            $activeSessions = $this->getActiveSessionCount();
            
            // Get MFA statistics
            $mfaStats = $this->getMFAStatistics();
            
            // Get anomalies detected (flagged audit events)
            $anomaliesDetected = $this->getAnomalyCount();
            
            // Get locked accounts count
            $lockedAccounts = $this->getLockedAccountsCount();
            
            return [
                'failedLogins24h' => $failedLogins24h,
                'activeSessions' => $activeSessions,
                'mfaCompliance' => $mfaStats['complianceRate'],
                'anomaliesDetected' => $anomaliesDetected,
                'totalUsers' => $mfaStats['totalUsers'],
                'usersWithMFA' => $mfaStats['usersWithMFA'],
                'lockedAccounts' => $lockedAccounts
            ];
        } catch (PDOException $e) {
            error_log('SecurityOfficerRepository::getSecurityStats error: ' . $e->getMessage());
            return [
                'failedLogins24h' => 0,
                'activeSessions' => 0,
                'mfaCompliance' => 0.0,
                'anomaliesDetected' => 0,
                'totalUsers' => 0,
                'usersWithMFA' => 0,
                'lockedAccounts' => 0
            ];
        }
    }

    /**
     * Get audit events for security monitoring
     * 
     * Returns recent security-related audit events from auditevent table.
     * 
     * @param int $limit Maximum number of results (default: 20)
     * @return array<int, array{
     *   id: string,
     *   eventType: string,
     *   userId: string|null,
     *   userName: string|null,
     *   ipAddress: string|null,
     *   userAgent: string|null,
     *   timestamp: string,
     *   details: string|null,
     *   flagged: bool,
     *   sessionId: string|null
     * }>
     */
    public function getAuditEvents(int $limit = 20): array
    {
        try {
            $sql = "SELECT 
                        ae.audit_id,
                        ae.user_id,
                        ae.subject_type,
                        ae.subject_id,
                        ae.action,
                        ae.occurred_at,
                        ae.source_ip,
                        ae.user_agent,
                        ae.session_id,
                        ae.details,
                        ae.flagged,
                        u.username
                    FROM {$this->auditEventTable} ae
                    LEFT JOIN {$this->userTable} u ON ae.user_id = u.user_id
                    ORDER BY ae.occurred_at DESC
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = $this->formatAuditEvent($row);
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log('SecurityOfficerRepository::getAuditEvents error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get failed login attempts
     * 
     * Returns recent failed login attempts from audit events.
     * 
     * @param int $limit Maximum number of results (default: 20)
     * @return array<int, array{
     *   id: string,
     *   userId: string|null,
     *   userName: string|null,
     *   ipAddress: string|null,
     *   userAgent: string|null,
     *   timestamp: string,
     *   attemptCount: int,
     *   details: string|null
     * }>
     */
    public function getFailedLoginAttempts(int $limit = 20): array
    {
        try {
            // Get failed login events from auditevent table
            $sql = "SELECT 
                        ae.audit_id,
                        ae.user_id,
                        ae.source_ip,
                        ae.user_agent,
                        ae.occurred_at,
                        ae.details,
                        u.username,
                        u.login_attempts
                    FROM {$this->auditEventTable} ae
                    LEFT JOIN {$this->userTable} u ON ae.user_id = u.user_id
                    WHERE ae.action IN ('login_failed', 'login_failure', 'authentication_failed')
                    ORDER BY ae.occurred_at DESC
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = $this->formatFailedLogin($row);
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log('SecurityOfficerRepository::getFailedLoginAttempts error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get MFA status across all users
     * 
     * Returns MFA enrollment and compliance statistics.
     * 
     * @return array{
     *   enabled: int,
     *   disabled: int,
     *   pending: int,
     *   complianceRate: float,
     *   users: array
     * }
     */
    public function getMFAStatus(): array
    {
        try {
            // Get MFA statistics
            $sql = "SELECT 
                        u.user_id,
                        u.username,
                        u.email,
                        u.mfa_enabled,
                        u.last_login,
                        u.is_active,
                        r.name as role_name,
                        r.slug as role_slug
                    FROM {$this->userTable} u
                    LEFT JOIN {$this->userRoleTable} ur ON u.user_id = ur.user_id
                    LEFT JOIN {$this->roleTable} r ON ur.role_id = r.role_id
                    WHERE u.is_active = 1
                    ORDER BY u.username";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            $enabled = 0;
            $disabled = 0;
            $pending = 0;
            $users = [];
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($row['mfa_enabled'] == 1) {
                    $enabled++;
                } else {
                    // If user has logged in but no MFA, count as pending
                    if (!empty($row['last_login'])) {
                        $pending++;
                    } else {
                        $disabled++;
                    }
                }
                
                $users[] = $this->formatMFAUser($row);
            }
            
            $total = $enabled + $disabled + $pending;
            $complianceRate = $total > 0 ? ($enabled / $total) * 100 : 0;
            
            return [
                'enabled' => $enabled,
                'disabled' => $disabled,
                'pending' => $pending,
                'complianceRate' => round($complianceRate, 1),
                'users' => $users
            ];
        } catch (PDOException $e) {
            error_log('SecurityOfficerRepository::getMFAStatus error: ' . $e->getMessage());
            return [
                'enabled' => 0,
                'disabled' => 0,
                'pending' => 0,
                'complianceRate' => 0.0,
                'users' => []
            ];
        }
    }

    /**
     * Get currently active user sessions
     * 
     * Returns users who have been active recently (last login within 30 minutes).
     * 
     * @param int $limit Maximum number of results (default: 20)
     * @return array<int, array{
     *   id: string,
     *   userId: string,
     *   userName: string,
     *   ipAddress: string|null,
     *   userAgent: string|null,
     *   lastActivity: string,
     *   role: string|null
     * }>
     */
    public function getActiveSessions(int $limit = 20): array
    {
        try {
            // Get users with recent login activity
            $sql = "SELECT 
                        u.user_id,
                        u.username,
                        u.email,
                        u.last_login,
                        u.ip_address,
                        r.name as role_name,
                        r.slug as role_slug
                    FROM {$this->userTable} u
                    LEFT JOIN {$this->userRoleTable} ur ON u.user_id = ur.user_id
                    LEFT JOIN {$this->roleTable} r ON ur.role_id = r.role_id
                    WHERE u.is_active = 1
                    AND u.last_login IS NOT NULL
                    AND u.last_login >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                    ORDER BY u.last_login DESC
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = $this->formatActiveSession($row);
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log('SecurityOfficerRepository::getActiveSessions error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get security alerts
     * 
     * Returns triggered security alerts and anomalies from flagged audit events
     * and compliance alerts.
     * 
     * @param int $limit Maximum number of results (default: 20)
     * @return array<int, array{
     *   id: string,
     *   alertType: string,
     *   severity: string,
     *   message: string,
     *   timestamp: string,
     *   acknowledged: bool,
     *   acknowledgedAt: string|null
     * }>
     */
    public function getSecurityAlerts(int $limit = 20): array
    {
        try {
            $alerts = [];
            
            // Get flagged audit events as security alerts
            $flaggedSql = "SELECT 
                            ae.audit_id,
                            ae.action,
                            ae.details,
                            ae.occurred_at,
                            ae.source_ip,
                            u.username
                          FROM {$this->auditEventTable} ae
                          LEFT JOIN {$this->userTable} u ON ae.user_id = u.user_id
                          WHERE ae.flagged = 1
                          ORDER BY ae.occurred_at DESC
                          LIMIT :limit";
            
            $stmt = $this->pdo->prepare($flaggedSql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $alerts[] = $this->formatSecurityAlertFromAudit($row);
            }
            
            // Get compliance alerts with security type
            $complianceSql = "SELECT 
                                ca.alert_id,
                                ca.alert_message,
                                ca.severity,
                                ca.created_at,
                                ca.acknowledged_at,
                                ca.acknowledged_by
                             FROM {$this->complianceAlertsTable} ca
                             WHERE ca.severity IN ('warning', 'critical')
                             ORDER BY ca.created_at DESC
                             LIMIT :limit";
            
            $stmt = $this->pdo->prepare($complianceSql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $alerts[] = $this->formatSecurityAlertFromCompliance($row);
            }
            
            // Sort by timestamp descending
            usort($alerts, function($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });
            
            return array_slice($alerts, 0, $limit);
        } catch (PDOException $e) {
            error_log('SecurityOfficerRepository::getSecurityAlerts error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get registered user devices
     * 
     * Returns user devices with their security status.
     * 
     * @param int $limit Maximum number of results (default: 20)
     * @return array<int, array{
     *   id: string,
     *   userId: string|null,
     *   userName: string|null,
     *   platform: string|null,
     *   status: string|null,
     *   lastSeen: string|null,
     *   encryptedAtRest: bool
     * }>
     */
    public function getUserDevices(int $limit = 20): array
    {
        try {
            $sql = "SELECT 
                        ud.device_id,
                        ud.user_id,
                        ud.platform,
                        ud.status,
                        ud.last_seen_at,
                        ud.encrypted_at_rest,
                        u.username
                    FROM {$this->userDeviceTable} ud
                    LEFT JOIN {$this->userTable} u ON ud.user_id = u.user_id
                    ORDER BY ud.last_seen_at DESC
                    LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = $this->formatUserDevice($row);
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log('SecurityOfficerRepository::getUserDevices error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get failed login count in last 24 hours
     * 
     * @return int Number of failed logins
     */
    private function getFailedLoginCount24h(): int
    {
        try {
            $sql = "SELECT COUNT(*) 
                    FROM {$this->auditEventTable}
                    WHERE action IN ('login_failed', 'login_failure', 'authentication_failed')
                    AND occurred_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('getFailedLoginCount24h error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get active session count
     * 
     * @return int Number of active sessions
     */
    private function getActiveSessionCount(): int
    {
        try {
            $sql = "SELECT COUNT(*) 
                    FROM {$this->userTable}
                    WHERE is_active = 1
                    AND last_login IS NOT NULL
                    AND last_login >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('getActiveSessionCount error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get MFA statistics
     * 
     * @return array{totalUsers: int, usersWithMFA: int, complianceRate: float}
     */
    private function getMFAStatistics(): array
    {
        try {
            $totalSql = "SELECT COUNT(*) FROM {$this->userTable} WHERE is_active = 1";
            $stmt = $this->pdo->prepare($totalSql);
            $stmt->execute();
            $totalUsers = (int) $stmt->fetchColumn();
            
            $mfaSql = "SELECT COUNT(*) FROM {$this->userTable} WHERE is_active = 1 AND mfa_enabled = 1";
            $stmt = $this->pdo->prepare($mfaSql);
            $stmt->execute();
            $usersWithMFA = (int) $stmt->fetchColumn();
            
            $complianceRate = $totalUsers > 0 ? ($usersWithMFA / $totalUsers) * 100 : 0;
            
            return [
                'totalUsers' => $totalUsers,
                'usersWithMFA' => $usersWithMFA,
                'complianceRate' => round($complianceRate, 1)
            ];
        } catch (PDOException $e) {
            error_log('getMFAStatistics error: ' . $e->getMessage());
            return [
                'totalUsers' => 0,
                'usersWithMFA' => 0,
                'complianceRate' => 0.0
            ];
        }
    }

    /**
     * Get anomaly count (flagged audit events)
     * 
     * @return int Number of anomalies
     */
    private function getAnomalyCount(): int
    {
        try {
            $sql = "SELECT COUNT(*) 
                    FROM {$this->auditEventTable}
                    WHERE flagged = 1
                    AND occurred_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('getAnomalyCount error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get locked accounts count
     * 
     * @return int Number of locked accounts
     */
    private function getLockedAccountsCount(): int
    {
        try {
            $sql = "SELECT COUNT(*) 
                    FROM {$this->userTable}
                    WHERE is_active = 1
                    AND (lockout_until IS NOT NULL AND lockout_until > NOW())
                    OR (account_locked_until IS NOT NULL AND account_locked_until > NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('getLockedAccountsCount error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Format audit event row for API response
     * 
     * @param array $row Database row
     * @return array Formatted audit event data
     */
    private function formatAuditEvent(array $row): array
    {
        return [
            'id' => $row['audit_id'],
            'eventType' => $row['action'] ?? 'unknown',
            'userId' => $row['user_id'],
            'userName' => $row['username'] ?? 'Unknown User',
            'ipAddress' => $this->maskIpAddress($row['source_ip']),
            'userAgent' => $row['user_agent'],
            'timestamp' => $this->formatIsoDateTime($row['occurred_at']),
            'details' => $this->parseJsonDetails($row['details']),
            'flagged' => (bool) ($row['flagged'] ?? false),
            'sessionId' => $row['session_id'] ? $this->maskSessionId($row['session_id']) : null,
            'subjectType' => $row['subject_type'],
            'subjectId' => $row['subject_id']
        ];
    }

    /**
     * Format failed login row for API response
     * 
     * @param array $row Database row
     * @return array Formatted failed login data
     */
    private function formatFailedLogin(array $row): array
    {
        return [
            'id' => $row['audit_id'],
            'userId' => $row['user_id'],
            'userName' => $row['username'] ?? 'Unknown User',
            'ipAddress' => $this->maskIpAddress($row['source_ip']),
            'userAgent' => $row['user_agent'],
            'timestamp' => $this->formatIsoDateTime($row['occurred_at']),
            'attemptCount' => (int) ($row['login_attempts'] ?? 1),
            'details' => $this->parseJsonDetails($row['details'])
        ];
    }

    /**
     * Format MFA user row for API response
     * 
     * @param array $row Database row
     * @return array Formatted MFA user data
     */
    private function formatMFAUser(array $row): array
    {
        $mfaStatus = 'disabled';
        if ($row['mfa_enabled'] == 1) {
            $mfaStatus = 'enabled';
        } elseif (!empty($row['last_login'])) {
            $mfaStatus = 'pending';
        }
        
        return [
            'userId' => $row['user_id'],
            'userName' => $row['username'],
            'email' => $row['email'],
            'mfaEnabled' => (bool) $row['mfa_enabled'],
            'mfaStatus' => $mfaStatus,
            'lastLogin' => $row['last_login'] ? $this->formatIsoDateTime($row['last_login']) : null,
            'role' => $row['role_name'] ?? 'Unknown Role'
        ];
    }

    /**
     * Format active session row for API response
     * 
     * @param array $row Database row
     * @return array Formatted active session data
     */
    private function formatActiveSession(array $row): array
    {
        return [
            'id' => $row['user_id'],
            'userId' => $row['user_id'],
            'userName' => $row['username'],
            'email' => $row['email'],
            'ipAddress' => $this->maskIpAddress($row['ip_address']),
            'lastActivity' => $this->formatIsoDateTime($row['last_login']),
            'role' => $row['role_name'] ?? 'Unknown Role'
        ];
    }

    /**
     * Format security alert from audit event
     * 
     * @param array $row Database row
     * @return array Formatted security alert data
     */
    private function formatSecurityAlertFromAudit(array $row): array
    {
        $details = $this->parseJsonDetails($row['details']);
        $message = $details ?? "Security event flagged: {$row['action']}";
        if ($row['username']) {
            $message .= " - User: {$row['username']}";
        }
        if ($row['source_ip']) {
            $message .= " - IP: " . $this->maskIpAddress($row['source_ip']);
        }
        
        return [
            'id' => $row['audit_id'],
            'alertType' => 'audit_flag',
            'severity' => 'warning',
            'message' => $message,
            'timestamp' => $this->formatIsoDateTime($row['occurred_at']),
            'acknowledged' => false,
            'acknowledgedAt' => null
        ];
    }

    /**
     * Format security alert from compliance alert
     * 
     * @param array $row Database row
     * @return array Formatted security alert data
     */
    private function formatSecurityAlertFromCompliance(array $row): array
    {
        return [
            'id' => $row['alert_id'],
            'alertType' => 'compliance',
            'severity' => $row['severity'] ?? 'warning',
            'message' => $row['alert_message'],
            'timestamp' => $this->formatIsoDateTime($row['created_at']),
            'acknowledged' => !empty($row['acknowledged_at']),
            'acknowledgedAt' => $row['acknowledged_at'] ? $this->formatIsoDateTime($row['acknowledged_at']) : null
        ];
    }

    /**
     * Format user device row for API response
     * 
     * @param array $row Database row
     * @return array Formatted user device data
     */
    private function formatUserDevice(array $row): array
    {
        return [
            'id' => $row['device_id'],
            'userId' => $row['user_id'],
            'userName' => $row['username'] ?? 'Unknown User',
            'platform' => $row['platform'] ?? 'Unknown',
            'status' => $row['status'] ?? 'unknown',
            'lastSeen' => $row['last_seen_at'] ? $this->formatIsoDateTime($row['last_seen_at']) : null,
            'encryptedAtRest' => (bool) ($row['encrypted_at_rest'] ?? true)
        ];
    }

    /**
     * Mask IP address for privacy (shows first 3 octets for IPv4)
     * 
     * @param string|null $ip IP address
     * @return string|null Masked IP address
     */
    private function maskIpAddress(?string $ip): ?string
    {
        if (empty($ip)) {
            return null;
        }
        
        // For IPv4, mask the last octet
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                $parts[3] = 'xxx';
                return implode('.', $parts);
            }
        }
        
        // For IPv6, just show first 4 groups
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            return implode(':', array_slice($parts, 0, 4)) . ':xxxx:xxxx:xxxx:xxxx';
        }
        
        return $ip;
    }

    /**
     * Mask session ID for security
     * 
     * @param string $sessionId Session ID
     * @return string Masked session ID
     */
    private function maskSessionId(string $sessionId): string
    {
        if (strlen($sessionId) <= 8) {
            return str_repeat('*', strlen($sessionId));
        }
        
        return substr($sessionId, 0, 4) . '...' . substr($sessionId, -4);
    }

    /**
     * Parse JSON details field
     * 
     * @param string|null $details JSON string
     * @return string|null Parsed details or original string
     */
    private function parseJsonDetails(?string $details): ?string
    {
        if (empty($details)) {
            return null;
        }
        
        $decoded = json_decode($details, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // Return a summary of the details
            if (isset($decoded['message'])) {
                return $decoded['message'];
            }
            if (isset($decoded['reason'])) {
                return $decoded['reason'];
            }
            if (isset($decoded['error'])) {
                return $decoded['error'];
            }
            return json_encode($decoded);
        }
        
        return $details;
    }

    /**
     * Format datetime as ISO 8601 string
     * 
     * @param string|null $datetime Datetime value
     * @return string ISO 8601 formatted string
     */
    private function formatIsoDateTime(?string $datetime): string
    {
        if (empty($datetime)) {
            return (new DateTime())->format('c');
        }
        
        try {
            $dt = new DateTime($datetime);
            return $dt->format('c');
        } catch (\Exception $e) {
            return (new DateTime())->format('c');
        }
    }
}
