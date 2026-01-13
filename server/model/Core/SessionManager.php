<?php
/**
 * SessionManager.php - Database-backed Session Manager for SafeShift EHR
 * 
 * Provides database-backed session management with features:
 * - Multi-device session tracking
 * - Configurable idle timeout per user
 * - Secure session token generation and validation
 * - Session activity tracking for HIPAA compliance
 * - Logout from all devices functionality
 * 
 * This class works alongside the existing Session.php for PHP session handling,
 * adding database persistence layer for improved session management.
 * 
 * @package    SafeShift\Model\Core
 * @author     SafeShift Development Team
 * @copyright  2025 SafeShift EHR
 * @license    Proprietary
 */

declare(strict_types=1);

namespace Model\Core;

use PDO;
use PDOException;
use Exception;

/**
 * Database-backed session manager
 * 
 * Implements HIPAA-compliant session management with database persistence
 * for tracking active sessions across multiple devices.
 */
final class SessionManager
{
    /** @var Database Database instance */
    private Database $db;
    
    /** @var self|null Singleton instance */
    private static ?self $instance = null;
    
    /** Default idle timeout in seconds (30 minutes) */
    private const DEFAULT_IDLE_TIMEOUT = 1800;
    
    /** Minimum allowed idle timeout (5 minutes) */
    private const MIN_IDLE_TIMEOUT = 300;
    
    /** Maximum allowed idle timeout (1 hour) */
    private const MAX_IDLE_TIMEOUT = 3600;
    
    /** Hard session lifetime (1 hour) - sessions cannot exceed this regardless of activity */
    private const MAX_SESSION_LIFETIME = 3600;
    
    /** Session token length in bytes (will be 64 hex characters) */
    private const TOKEN_LENGTH = 32;

    /**
     * Private constructor for singleton pattern
     */
    private function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get singleton instance
     * 
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Create a new session record in the database
     * 
     * @param int $userId User ID
     * @param string|null $deviceInfo Device/browser info for display
     * @param string|null $ipAddress Client IP address
     * @param string|null $userAgent Full user agent string
     * @return array{success: bool, token?: string, session_id?: int, message?: string}
     */
    public function createSession(
        int $userId,
        ?string $deviceInfo = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        try {
            // Generate secure session token
            $rawToken = bin2hex(random_bytes(self::TOKEN_LENGTH));
            $hashedToken = $this->hashToken($rawToken);
            
            // Get user's idle timeout preference
            $idleTimeout = $this->getUserIdleTimeout($userId);
            
            // Calculate expiration time
            $expiresAt = date('Y-m-d H:i:s', time() + self::MAX_SESSION_LIFETIME);
            
            // Parse device info from user agent if not provided
            if ($deviceInfo === null && $userAgent !== null) {
                $deviceInfo = $this->parseDeviceInfo($userAgent);
            }
            
            // Insert session record
            $sql = "INSERT INTO user_sessions 
                    (user_id, session_token, device_info, ip_address, user_agent, expires_at) 
                    VALUES (:user_id, :token, :device_info, :ip_address, :user_agent, :expires_at)";
            
            $this->db->execute($sql, [
                ':user_id' => $userId,
                ':token' => $hashedToken,
                ':device_info' => $deviceInfo ? substr($deviceInfo, 0, 255) : null,
                ':ip_address' => $ipAddress ? substr($ipAddress, 0, 45) : null,
                ':user_agent' => $userAgent,
                ':expires_at' => $expiresAt
            ]);
            
            $sessionId = (int) $this->db->lastInsertId();
            
            // Log session creation
            $this->logSessionActivity($sessionId, $userId, 'login', $ipAddress, $userAgent);
            
            return [
                'success' => true,
                'token' => $rawToken, // Return raw token to be stored in cookie/session
                'session_id' => $sessionId,
                'expires_at' => $expiresAt,
                'idle_timeout' => $idleTimeout
            ];
            
        } catch (PDOException $e) {
            $this->logError('createSession', $e);
            return [
                'success' => false,
                'message' => 'Failed to create session'
            ];
        }
    }

    /**
     * Validate a session token and return session data if valid
     * 
     * @param string $sessionToken Raw session token
     * @return array{valid: bool, session?: array, user_id?: int, message?: string}
     */
    public function validateSession(string $sessionToken): array
    {
        try {
            $hashedToken = $this->hashToken($sessionToken);
            
            $sql = "SELECT s.*, 
                           TIMESTAMPDIFF(SECOND, s.last_activity, NOW()) as idle_seconds,
                           COALESCE(p.idle_timeout, :default_timeout) as user_idle_timeout
                    FROM user_sessions s
                    LEFT JOIN user_preferences p ON s.user_id = p.user_id
                    WHERE s.session_token = :token 
                    AND s.is_active = TRUE 
                    AND s.expires_at > NOW()";
            
            $session = $this->db->fetchOne($sql, [
                ':token' => $hashedToken,
                ':default_timeout' => self::DEFAULT_IDLE_TIMEOUT
            ]);
            
            if (!$session) {
                return [
                    'valid' => false,
                    'message' => 'Session not found or expired'
                ];
            }
            
            // Check if idle timeout exceeded
            $idleSeconds = (int) $session['idle_seconds'];
            $userIdleTimeout = (int) $session['user_idle_timeout'];
            
            if ($idleSeconds > $userIdleTimeout) {
                // Mark session as inactive due to timeout
                $this->deactivateSession($hashedToken, 'timeout');
                return [
                    'valid' => false,
                    'message' => 'Session timed out due to inactivity'
                ];
            }
            
            // Calculate remaining time
            $expiresAt = strtotime($session['expires_at']);
            $remainingHard = max(0, $expiresAt - time());
            $remainingIdle = max(0, $userIdleTimeout - $idleSeconds);
            $remainingTime = min($remainingHard, $remainingIdle);
            
            return [
                'valid' => true,
                'session' => [
                    'id' => (int) $session['id'],
                    'user_id' => (int) $session['user_id'],
                    'device_info' => $session['device_info'],
                    'ip_address' => $session['ip_address'],
                    'created_at' => $session['created_at'],
                    'last_activity' => $session['last_activity'],
                    'expires_at' => $session['expires_at'],
                    'idle_timeout' => $userIdleTimeout,
                    'idle_seconds' => $idleSeconds
                ],
                'user_id' => (int) $session['user_id'],
                'remaining_time' => $remainingTime
            ];
            
        } catch (PDOException $e) {
            $this->logError('validateSession', $e);
            return [
                'valid' => false,
                'message' => 'Session validation failed'
            ];
        }
    }

    /**
     * Update last activity timestamp for a session
     * 
     * @param string $sessionToken Raw session token
     * @return array{success: bool, remaining_time?: int, message?: string}
     */
    public function updateActivity(string $sessionToken): array
    {
        try {
            $hashedToken = $this->hashToken($sessionToken);
            
            // First validate the session is still active
            $validation = $this->validateSession($sessionToken);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message'] ?? 'Invalid session'
                ];
            }
            
            // Update last activity
            $sql = "UPDATE user_sessions 
                    SET last_activity = CURRENT_TIMESTAMP 
                    WHERE session_token = :token AND is_active = TRUE";
            
            $affected = $this->db->execute($sql, [':token' => $hashedToken]);
            
            if ($affected > 0) {
                // Recalculate remaining time after update
                $session = $validation['session'];
                $expiresAt = strtotime($session['expires_at']);
                $remainingHard = max(0, $expiresAt - time());
                $remainingIdle = $session['idle_timeout']; // Just reset, so full timeout available
                $remainingTime = min($remainingHard, $remainingIdle);
                
                return [
                    'success' => true,
                    'remaining_time' => $remainingTime,
                    'idle_timeout' => $session['idle_timeout'],
                    'expires_at' => gmdate('Y-m-d\TH:i:s\Z', $expiresAt)
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to update activity'
            ];
            
        } catch (PDOException $e) {
            $this->logError('updateActivity', $e);
            return [
                'success' => false,
                'message' => 'Activity update failed'
            ];
        }
    }

    /**
     * Destroy (deactivate) a specific session
     * 
     * @param string $sessionToken Raw session token
     * @return array{success: bool, message?: string}
     */
    public function destroySession(string $sessionToken): array
    {
        try {
            $hashedToken = $this->hashToken($sessionToken);
            return $this->deactivateSession($hashedToken, 'logout');
        } catch (Exception $e) {
            $this->logError('destroySession', $e);
            return [
                'success' => false,
                'message' => 'Failed to destroy session'
            ];
        }
    }

    /**
     * Destroy all sessions for a user, optionally except current
     * 
     * @param int $userId User ID
     * @param string|null $exceptToken Token to exclude (current session)
     * @return array{success: bool, count?: int, message?: string}
     */
    public function destroyAllUserSessions(int $userId, ?string $exceptToken = null): array
    {
        try {
            $params = [':user_id' => $userId];
            
            if ($exceptToken !== null) {
                $hashedExcept = $this->hashToken($exceptToken);
                $sql = "UPDATE user_sessions 
                        SET is_active = FALSE 
                        WHERE user_id = :user_id 
                        AND is_active = TRUE 
                        AND session_token != :except_token";
                $params[':except_token'] = $hashedExcept;
            } else {
                $sql = "UPDATE user_sessions 
                        SET is_active = FALSE 
                        WHERE user_id = :user_id AND is_active = TRUE";
            }
            
            $count = $this->db->execute($sql, $params);
            
            // Log the forced logout event
            $this->logSessionActivity(null, $userId, 'forced_logout', null, null, [
                'logout_type' => $exceptToken ? 'all_except_current' : 'all',
                'sessions_affected' => $count
            ]);
            
            return [
                'success' => true,
                'count' => $count,
                'message' => $count > 0 ? "Logged out {$count} session(s)" : 'No other sessions found'
            ];
            
        } catch (PDOException $e) {
            $this->logError('destroyAllUserSessions', $e);
            return [
                'success' => false,
                'message' => 'Failed to destroy sessions'
            ];
        }
    }

    /**
     * Get list of active sessions for a user
     * 
     * @param int $userId User ID
     * @return array{success: bool, sessions?: array, message?: string}
     */
    public function getActiveSessions(int $userId): array
    {
        try {
            $sql = "SELECT id, device_info, ip_address, created_at, last_activity, expires_at
                    FROM user_sessions 
                    WHERE user_id = :user_id 
                    AND is_active = TRUE 
                    AND expires_at > NOW()
                    ORDER BY last_activity DESC";
            
            $sessions = $this->db->fetchAll($sql, [':user_id' => $userId]);
            
            // Format sessions for response (don't expose tokens)
            $formatted = array_map(function ($session) {
                return [
                    'id' => (int) $session['id'],
                    'device_info' => $session['device_info'] ?? 'Unknown Device',
                    'ip_address' => $this->maskIpAddress($session['ip_address']),
                    'created_at' => gmdate('Y-m-d\TH:i:s\Z', strtotime($session['created_at'])),
                    'last_activity' => gmdate('Y-m-d\TH:i:s\Z', strtotime($session['last_activity'])),
                    'expires_at' => gmdate('Y-m-d\TH:i:s\Z', strtotime($session['expires_at']))
                ];
            }, $sessions);
            
            return [
                'success' => true,
                'sessions' => $formatted,
                'count' => count($formatted)
            ];
            
        } catch (PDOException $e) {
            $this->logError('getActiveSessions', $e);
            return [
                'success' => false,
                'message' => 'Failed to retrieve sessions'
            ];
        }
    }

    /**
     * Destroy a specific session by ID (must belong to user)
     * 
     * @param int $sessionId Session ID
     * @param int $userId User ID (for ownership verification)
     * @return array{success: bool, message?: string}
     */
    public function destroySessionById(int $sessionId, int $userId): array
    {
        try {
            // Verify ownership and deactivate
            $sql = "UPDATE user_sessions 
                    SET is_active = FALSE 
                    WHERE id = :session_id 
                    AND user_id = :user_id 
                    AND is_active = TRUE";
            
            $affected = $this->db->execute($sql, [
                ':session_id' => $sessionId,
                ':user_id' => $userId
            ]);
            
            if ($affected > 0) {
                $this->logSessionActivity($sessionId, $userId, 'forced_logout', null, null, [
                    'logout_type' => 'specific_session'
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Session terminated'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Session not found or already inactive'
            ];
            
        } catch (PDOException $e) {
            $this->logError('destroySessionById', $e);
            return [
                'success' => false,
                'message' => 'Failed to destroy session'
            ];
        }
    }

    /**
     * Get user's preferred idle timeout
     * 
     * @param int $userId User ID
     * @return int Idle timeout in seconds
     */
    public function getUserIdleTimeout(int $userId): int
    {
        try {
            $sql = "SELECT idle_timeout FROM user_preferences WHERE user_id = :user_id";
            $result = $this->db->fetchColumn($sql, [':user_id' => $userId]);
            
            if ($result !== false && $result !== null) {
                return $this->normalizeTimeout((int) $result);
            }
            
            return self::DEFAULT_IDLE_TIMEOUT;
            
        } catch (PDOException $e) {
            $this->logError('getUserIdleTimeout', $e);
            return self::DEFAULT_IDLE_TIMEOUT;
        }
    }

    /**
     * Set user's preferred idle timeout
     * 
     * @param int $userId User ID
     * @param int $timeoutSeconds Timeout in seconds (300-3600)
     * @return array{success: bool, timeout?: int, message?: string}
     */
    public function setUserIdleTimeout(int $userId, int $timeoutSeconds): array
    {
        try {
            // Validate timeout range
            $timeout = $this->normalizeTimeout($timeoutSeconds);
            
            // Upsert preference
            $sql = "INSERT INTO user_preferences (user_id, idle_timeout) 
                    VALUES (:user_id, :timeout) 
                    ON DUPLICATE KEY UPDATE idle_timeout = :timeout2, updated_at = CURRENT_TIMESTAMP";
            
            $this->db->execute($sql, [
                ':user_id' => $userId,
                ':timeout' => $timeout,
                ':timeout2' => $timeout
            ]);
            
            return [
                'success' => true,
                'timeout' => $timeout,
                'message' => 'Timeout preference updated'
            ];
            
        } catch (PDOException $e) {
            $this->logError('setUserIdleTimeout', $e);
            return [
                'success' => false,
                'message' => 'Failed to update timeout preference'
            ];
        }
    }

    /**
     * Get user preferences including timeout
     * 
     * @param int $userId User ID
     * @return array{success: bool, preferences?: array, message?: string}
     */
    public function getUserPreferences(int $userId): array
    {
        try {
            $sql = "SELECT idle_timeout, theme, notifications_enabled, updated_at 
                    FROM user_preferences 
                    WHERE user_id = :user_id";
            
            $result = $this->db->fetchOne($sql, [':user_id' => $userId]);
            
            if ($result) {
                return [
                    'success' => true,
                    'preferences' => [
                        'idle_timeout' => (int) $result['idle_timeout'],
                        'theme' => $result['theme'],
                        'notifications_enabled' => (bool) $result['notifications_enabled'],
                        'updated_at' => $result['updated_at']
                    ]
                ];
            }
            
            // Return defaults if no preferences exist
            return [
                'success' => true,
                'preferences' => [
                    'idle_timeout' => self::DEFAULT_IDLE_TIMEOUT,
                    'theme' => 'system',
                    'notifications_enabled' => true,
                    'updated_at' => null
                ]
            ];
            
        } catch (PDOException $e) {
            $this->logError('getUserPreferences', $e);
            return [
                'success' => false,
                'message' => 'Failed to retrieve preferences'
            ];
        }
    }

    /**
     * Get session statistics for a user (for admin/display purposes)
     * 
     * @param int $userId User ID
     * @return array
     */
    public function getSessionStats(int $userId): array
    {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_active,
                        MAX(last_activity) as last_activity,
                        MIN(created_at) as oldest_session
                    FROM user_sessions 
                    WHERE user_id = :user_id 
                    AND is_active = TRUE 
                    AND expires_at > NOW()";
            
            $stats = $this->db->fetchOne($sql, [':user_id' => $userId]);
            
            return [
                'success' => true,
                'stats' => [
                    'active_sessions' => (int) ($stats['total_active'] ?? 0),
                    'last_activity' => $stats['last_activity'] 
                        ? gmdate('Y-m-d\TH:i:s\Z', strtotime($stats['last_activity'])) 
                        : null,
                    'oldest_session' => $stats['oldest_session']
                        ? gmdate('Y-m-d\TH:i:s\Z', strtotime($stats['oldest_session']))
                        : null
                ]
            ];
            
        } catch (PDOException $e) {
            $this->logError('getSessionStats', $e);
            return [
                'success' => false,
                'stats' => ['active_sessions' => 0]
            ];
        }
    }

    // ========== PRIVATE HELPER METHODS ==========

    /**
     * Hash a session token for storage
     * 
     * @param string $token Raw token
     * @return string Hashed token
     */
    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Deactivate a session by hashed token
     * 
     * @param string $hashedToken Hashed session token
     * @param string $reason Reason for deactivation
     * @return array{success: bool, message?: string}
     */
    private function deactivateSession(string $hashedToken, string $reason): array
    {
        try {
            // Get session info before deactivating
            $sql = "SELECT id, user_id, ip_address, user_agent 
                    FROM user_sessions 
                    WHERE session_token = :token";
            $session = $this->db->fetchOne($sql, [':token' => $hashedToken]);
            
            // Deactivate
            $sql = "UPDATE user_sessions 
                    SET is_active = FALSE 
                    WHERE session_token = :token AND is_active = TRUE";
            $affected = $this->db->execute($sql, [':token' => $hashedToken]);
            
            if ($session && $affected > 0) {
                $eventType = $reason === 'timeout' ? 'timeout' : 'logout';
                $this->logSessionActivity(
                    (int) $session['id'],
                    (int) $session['user_id'],
                    $eventType,
                    $session['ip_address'],
                    $session['user_agent']
                );
            }
            
            return [
                'success' => $affected > 0,
                'message' => $affected > 0 ? 'Session deactivated' : 'Session not found'
            ];
            
        } catch (PDOException $e) {
            $this->logError('deactivateSession', $e);
            return [
                'success' => false,
                'message' => 'Failed to deactivate session'
            ];
        }
    }

    /**
     * Normalize timeout to valid range
     * 
     * @param int $timeout Requested timeout
     * @return int Normalized timeout
     */
    private function normalizeTimeout(int $timeout): int
    {
        return max(self::MIN_IDLE_TIMEOUT, min(self::MAX_IDLE_TIMEOUT, $timeout));
    }

    /**
     * Parse device info from user agent string
     * 
     * @param string $userAgent User agent string
     * @return string Simplified device info
     */
    private function parseDeviceInfo(string $userAgent): string
    {
        // Simple parsing - can be enhanced with a proper user agent parser library
        $deviceInfo = 'Unknown Device';
        
        // Detect browser
        if (preg_match('/Firefox\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Firefox ' . $matches[1];
        } elseif (preg_match('/Chrome\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Chrome ' . $matches[1];
        } elseif (preg_match('/Safari\/([0-9.]+)/', $userAgent) && !preg_match('/Chrome/', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/Edge\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Edge ' . $matches[1];
        } elseif (preg_match('/Edg\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Edge ' . $matches[1];
        } else {
            $browser = 'Unknown Browser';
        }
        
        // Detect OS
        if (preg_match('/Windows NT ([0-9.]+)/', $userAgent, $matches)) {
            $os = 'Windows';
            $version = $matches[1];
            if ($version >= '10.0') $os = 'Windows 10/11';
            elseif ($version >= '6.3') $os = 'Windows 8.1';
            elseif ($version >= '6.2') $os = 'Windows 8';
            elseif ($version >= '6.1') $os = 'Windows 7';
        } elseif (preg_match('/Mac OS X ([0-9._]+)/', $userAgent)) {
            $os = 'macOS';
        } elseif (preg_match('/Linux/', $userAgent)) {
            if (preg_match('/Android/', $userAgent)) {
                $os = 'Android';
            } else {
                $os = 'Linux';
            }
        } elseif (preg_match('/iPhone|iPad/', $userAgent)) {
            $os = 'iOS';
        } else {
            $os = 'Unknown OS';
        }
        
        return "{$browser} on {$os}";
    }

    /**
     * Mask IP address for display (privacy)
     * 
     * @param string|null $ipAddress IP address
     * @return string Masked IP
     */
    private function maskIpAddress(?string $ipAddress): string
    {
        if ($ipAddress === null) {
            return 'Unknown';
        }
        
        // For IPv4, mask last octet
        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.***', $ipAddress);
        }
        
        // For IPv6, mask last segment
        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return preg_replace('/:[^:]+$/', ':****', $ipAddress);
        }
        
        return $ipAddress;
    }

    /**
     * Log session activity to the database
     * 
     * @param int|null $sessionId Session ID (null if not available)
     * @param int $userId User ID
     * @param string $eventType Event type
     * @param string|null $ipAddress IP address
     * @param string|null $userAgent User agent
     * @param array|null $eventData Additional event data
     */
    private function logSessionActivity(
        ?int $sessionId,
        int $userId,
        string $eventType,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?array $eventData = null
    ): void {
        try {
            $sql = "INSERT INTO session_activity_log 
                    (session_id, user_id, event_type, ip_address, user_agent, event_data) 
                    VALUES (:session_id, :user_id, :event_type, :ip_address, :user_agent, :event_data)";
            
            $this->db->execute($sql, [
                ':session_id' => $sessionId,
                ':user_id' => $userId,
                ':event_type' => $eventType,
                ':ip_address' => $ipAddress ? substr($ipAddress, 0, 45) : null,
                ':user_agent' => $userAgent ? substr($userAgent, 0, 255) : null,
                ':event_data' => $eventData ? json_encode($eventData) : null
            ]);
        } catch (PDOException $e) {
            // Don't throw - logging failure shouldn't break main operation
            error_log('[SessionManager] Failed to log activity: ' . $e->getMessage());
        }
    }

    /**
     * Log an error (without exposing sensitive data)
     * 
     * @param string $method Method where error occurred
     * @param \Throwable $e Exception
     */
    private function logError(string $method, \Throwable $e): void
    {
        error_log(sprintf(
            '[SessionManager::%s] Error: %s | File: %s:%d',
            $method,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));
    }

    /**
     * Prevent cloning of singleton
     */
    private function __clone(): void
    {
    }

    /**
     * Prevent unserialization of singleton
     * 
     * @throws \RuntimeException
     */
    public function __wakeup(): void
    {
        throw new \RuntimeException('Cannot unserialize singleton');
    }
}
