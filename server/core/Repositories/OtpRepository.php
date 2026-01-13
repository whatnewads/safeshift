<?php
/**
 * OTP Repository
 * 
 * Handles all OTP/2FA-related database operations
 */

namespace App\Repositories;

use PDO;

class OtpRepository extends BaseRepository
{
    protected string $table = 'login_otp';
    protected string $primaryKey = 'otp_id';
    
    /**
     * Create new OTP record
     * 
     * @param string $userId
     * @param string $code
     * @param int $expiryMinutes
     * @return string|false OTP ID or false on failure
     */
    public function createOtp(string $userId, string $code, int $expiryMinutes = 10)
    {
        $otpId = $this->generateUuid();
        
        // Calculate expiry time in PHP since MySQL INTERVAL with prepared params is problematic
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryMinutes} minutes"));
        
        $sql = "INSERT INTO {$this->table} (
                    otp_id,
                    user_id,
                    code,
                    expires_at,
                    consumed,
                    created_at
                ) VALUES (
                    :otp_id,
                    :user_id,
                    :code,
                    :expires_at,
                    0,
                    NOW()
                )";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            'otp_id' => $otpId,
            'user_id' => $userId,
            'code' => $code,
            'expires_at' => $expiresAt
        ]);
        
        return $result ? $otpId : false;
    }
    
    /**
     * Find valid OTP for user
     *
     * @param string $userId
     * @param string $code
     * @return array|null
     */
    public function findValidOtp(string $userId, string $code): ?array
    {
        $this->logOtpDebug('FIND-VALID-START', $userId, $code, null);
        
        $sql = "SELECT otp_id, code, expires_at, consumed
                FROM {$this->table}
                WHERE user_id = :user_id
                AND consumed = 0
                AND expires_at > NOW()
                AND code = :code
                ORDER BY created_at DESC
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'code' => $code
        ]);
        
        $result = $stmt->fetch();
        
        $this->logOtpDebug('FIND-VALID-RESULT', $userId, $code, [
            'found' => $result ? 'YES' : 'NO',
            'otp_id' => $result['otp_id'] ?? '(none)',
            'expires_at' => $result['expires_at'] ?? '(none)'
        ]);
        
        return $result ?: null;
    }
    
    /**
     * Mark OTP as consumed
     *
     * @param string $otpId
     * @return bool
     */
    public function markAsConsumed(string $otpId): bool
    {
        // Note: consumed_at column doesn't exist in schema, only consumed flag
        $sql = "UPDATE {$this->table}
                SET consumed = 1
                WHERE otp_id = :otp_id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['otp_id' => $otpId]);
    }
    
    /**
     * Check OTP status
     *
     * @param string $userId
     * @param string $code
     * @return array Status information
     */
    public function checkOtpStatus(string $userId, string $code): array
    {
        $this->logOtpDebug('CHECK-STATUS-START', $userId, $code, null);
        
        $sql = "SELECT
                    consumed,
                    CASE WHEN expires_at > NOW() THEN 'valid' ELSE 'expired' END as status,
                    expires_at
                FROM {$this->table}
                WHERE user_id = :user_id
                AND code = :code
                ORDER BY created_at DESC
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'code' => $code
        ]);
        
        $result = $stmt->fetch();
        
        if (!$result) {
            $statusResult = [
                'exists' => false,
                'consumed' => false,
                'status' => 'not_found',
                'reason' => 'Invalid OTP code'
            ];
            
            $this->logOtpDebug('CHECK-STATUS-NOT-FOUND', $userId, $code, [
                'reason' => 'Code not found in database for this user'
            ]);
            
            return $statusResult;
        }
        
        $statusResult = [
            'exists' => true,
            'consumed' => (bool) $result['consumed'],
            'status' => $result['status'],
            'expires_at' => $result['expires_at'],
            'reason' => $result['consumed'] ? 'OTP already used' :
                       ($result['status'] === 'expired' ? 'OTP expired' : 'Valid')
        ];
        
        $this->logOtpDebug('CHECK-STATUS-RESULT', $userId, $code, [
            'exists' => 'YES',
            'consumed' => $result['consumed'] ? 'YES' : 'NO',
            'status' => $result['status'],
            'expires_at' => $result['expires_at'],
            'reason' => $statusResult['reason']
        ]);
        
        return $statusResult;
    }
    
    /**
     * Get recent OTPs for user (for debugging)
     *
     * @param string $userId
     * @param int $limit
     * @return array
     */
    public function getRecentOtps(string $userId, int $limit = 5): array
    {
        $sql = "SELECT
                    code,
                    expires_at,
                    consumed,
                    created_at,
                    CASE WHEN expires_at > NOW() THEN 'valid' ELSE 'expired' END as status
                FROM {$this->table}
                WHERE user_id = :user_id
                ORDER BY created_at DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Clean up expired OTPs
     *
     * @param int $hoursOld Clean OTPs older than this many hours
     * @return int Number of deleted records
     */
    public function cleanupExpiredOtps(int $hoursOld = 24): int
    {
        // Calculate cutoff time in PHP to avoid INTERVAL param issues
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$hoursOld} hours"));
        
        $sql = "DELETE FROM {$this->table}
                WHERE created_at < :cutoff_time
                OR (expires_at < NOW() AND consumed = 0)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cutoff_time' => $cutoffTime]);
        
        return $stmt->rowCount();
    }
    
    /**
     * Count active OTPs for user
     * 
     * @param string $userId
     * @return int
     */
    public function countActiveOtps(string $userId): int
    {
        $sql = "SELECT COUNT(*) as count
                FROM {$this->table}
                WHERE user_id = :user_id
                AND consumed = 0
                AND expires_at > NOW()";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        $result = $stmt->fetch();
        return (int) ($result['count'] ?? 0);
    }
    
    /**
     * Invalidate all active OTPs for user
     *
     * @param string $userId
     * @return bool
     */
    public function invalidateUserOtps(string $userId): bool
    {
        // Note: consumed_at column doesn't exist in schema, only consumed flag
        $sql = "UPDATE {$this->table}
                SET consumed = 1
                WHERE user_id = :user_id
                AND consumed = 0";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['user_id' => $userId]);
    }
    
    /**
     * Generate secure OTP code
     * 
     * @param int $length
     * @return string
     */
    public static function generateCode(int $length = 6): string
    {
        $max = pow(10, $length) - 1;
        return sprintf("%0{$length}d", random_int(0, $max));
    }
    
    /**
     * Get OTP statistics for user
     *
     * @param string $userId
     * @param int $days Number of days to look back
     * @return array
     */
    public function getOtpStats(string $userId, int $days = 30): array
    {
        // Calculate cutoff time in PHP to avoid INTERVAL param issues
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $sql = "SELECT
                    COUNT(*) as total_generated,
                    SUM(consumed) as total_consumed,
                    SUM(CASE WHEN consumed = 0 AND expires_at < NOW() THEN 1 ELSE 0 END) as total_expired,
                    MIN(created_at) as first_otp,
                    MAX(created_at) as last_otp
                FROM {$this->table}
                WHERE user_id = :user_id
                AND created_at >= :cutoff_time";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'cutoff_time' => $cutoffTime
        ]);
        
        return $stmt->fetch() ?: [
            'total_generated' => 0,
            'total_consumed' => 0,
            'total_expired' => 0,
            'first_otp' => null,
            'last_otp' => null
        ];
    }
    
    /**
     * Log OTP debug information to session_debug.log
     *
     * @param string $step Current step in OTP process
     * @param string $userId User ID
     * @param string $code The OTP code (will be masked)
     * @param array|null $extra Additional context
     */
    private function logOtpDebug(string $step, string $userId, string $code, ?array $extra): void
    {
        try {
            $logFile = dirname(__DIR__, 2) . '/logs/session_debug.log';
            $timestamp = date('Y-m-d H:i:s.') . substr(microtime(), 2, 3);
            
            // Mask OTP code (show last 3 digits)
            $maskedCode = strlen($code) >= 3 ? '***' . substr($code, -3) : '***';
            
            $log = "[{$timestamp}] OTP-REPOSITORY {$step}\n";
            $log .= "  Session ID: " . session_id() . "\n";
            $log .= "  User ID: {$userId}\n";
            $log .= "  Submitted OTP: {$maskedCode}\n";
            
            if ($extra) {
                $log .= "  Details:\n";
                foreach ($extra as $key => $value) {
                    $log .= "    {$key}: {$value}\n";
                }
            }
            
            $log .= "---\n";
            
            file_put_contents($logFile, $log, FILE_APPEND | LOCK_EX);
        } catch (\Exception $e) {
            // Don't let logging failure break OTP operations
            error_log('Failed to write OTP debug log: ' . $e->getMessage());
        }
    }
}