<?php
/**
 * Two-Factor Authentication Service
 * 
 * Handles 2FA code generation, verification, and rate limiting
 * Uses Amazon SES for email delivery via EmailService
 */

namespace Core\Services;

class TwoFactorService extends BaseService
{
    private EmailService $emailService;
    
    // Rate limiting constants
    private const MAX_CODES_PER_HOUR = 5;
    private const CODE_EXPIRY_MINUTES = 10;
    private const MAX_VERIFICATION_ATTEMPTS = 5;
    
    public function __construct()
    {
        parent::__construct();
        $this->emailService = new EmailService();
    }
    
    /**
     * Request a new 2FA code for a user
     * 
     * @param string $userId User ID
     * @param string $email User email
     * @param string $username Username for personalization
     * @param string $purpose Purpose: login, password_reset, email_change, security
     * @return array Result with success status
     */
    public function requestCode(
        string $userId,
        string $email,
        string $username = '',
        string $purpose = 'login'
    ): array {
        try {
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->formatResponse(false, [], 'Invalid email address');
            }
            
            // Check rate limiting
            $rateLimitCheck = $this->checkRateLimit($userId, '2fa_request');
            if (!$rateLimitCheck['allowed']) {
                $this->log('2FA rate limit exceeded', [
                    'user_id' => $userId,
                    'count' => $rateLimitCheck['count']
                ]);
                return $this->formatResponse(false, [
                    'retry_after' => $rateLimitCheck['retry_after'] ?? 3600
                ], 'Too many verification requests. Please try again later.');
            }
            
            // Invalidate any existing codes for this user/purpose
            $this->invalidateExistingCodes($userId, $purpose);
            
            // Generate 6-digit code
            $code = $this->generateCode();
            $codeHash = password_hash($code, PASSWORD_DEFAULT);
            
            // Calculate expiration
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . self::CODE_EXPIRY_MINUTES . ' minutes'));
            
            // Store hashed code in database
            $this->storeCode($userId, $codeHash, $purpose, $expiresAt);
            
            // Update rate limit counter
            $this->incrementRateLimit($userId, '2fa_request');
            
            // Send email via EmailService (uses SES)
            $sendResult = $this->emailService->sendOtp($email, $code, $username);
            
            // Log the mail send
            $this->logMailSend($userId, $email, 'otp', $sendResult['success']);
            
            if (!$sendResult['success']) {
                $this->logError('Failed to send 2FA email', [
                    'user_id' => $userId,
                    'error' => $sendResult['errors'] ?? 'Unknown error'
                ]);
                return $this->formatResponse(false, [], 'Failed to send verification code. Please try again.');
            }
            
            $this->log('2FA code sent', [
                'user_id' => $userId,
                'purpose' => $purpose,
                'expires_at' => $expiresAt
            ]);
            
            return $this->formatResponse(true, [
                'expires_in' => self::CODE_EXPIRY_MINUTES * 60,
                'expires_at' => $expiresAt
            ], 'Verification code sent to your email');
            
        } catch (\Exception $e) {
            $this->logError('2FA request failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return $this->formatResponse(false, [], 'An error occurred. Please try again.');
        }
    }
    
    /**
     * Verify a 2FA code
     * 
     * @param string $userId User ID
     * @param string $code 6-digit code entered by user
     * @param string $purpose Purpose: login, password_reset, email_change, security
     * @return array Result with success status
     */
    public function verifyCode(string $userId, string $code, string $purpose = 'login'): array
    {
        try {
            // Get the latest unexpired code for this user/purpose
            $storedCode = $this->getActiveCode($userId, $purpose);
            
            if (!$storedCode) {
                return $this->formatResponse(false, [], 'No active verification code found. Please request a new one.');
            }
            
            // Check if max attempts exceeded
            if ($storedCode['attempts'] >= self::MAX_VERIFICATION_ATTEMPTS) {
                $this->invalidateCode($storedCode['id']);
                return $this->formatResponse(false, [], 'Too many failed attempts. Please request a new code.');
            }
            
            // Check expiration
            if (strtotime($storedCode['expires_at']) < time()) {
                $this->invalidateCode($storedCode['id']);
                return $this->formatResponse(false, [], 'Verification code has expired. Please request a new one.');
            }
            
            // Verify the code
            if (!password_verify($code, $storedCode['code_hash'])) {
                // Increment attempt counter
                $this->incrementAttempts($storedCode['id']);
                $remainingAttempts = self::MAX_VERIFICATION_ATTEMPTS - $storedCode['attempts'] - 1;
                
                $this->log('2FA verification failed', [
                    'user_id' => $userId,
                    'attempts' => $storedCode['attempts'] + 1
                ]);
                
                return $this->formatResponse(false, [
                    'remaining_attempts' => max(0, $remainingAttempts)
                ], 'Invalid verification code. ' . ($remainingAttempts > 0 ? "$remainingAttempts attempts remaining." : 'Please request a new code.'));
            }
            
            // Code is valid - mark as consumed
            $this->consumeCode($storedCode['id']);
            
            $this->log('2FA verification successful', [
                'user_id' => $userId,
                'purpose' => $purpose
            ]);
            
            return $this->formatResponse(true, [
                'verified_at' => date('Y-m-d H:i:s'),
                'purpose' => $purpose
            ], 'Verification successful');
            
        } catch (\Exception $e) {
            $this->logError('2FA verification error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return $this->formatResponse(false, [], 'An error occurred during verification.');
        }
    }
    
    /**
     * Generate a secure 6-digit code
     * 
     * @return string 6-digit code
     */
    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Store a 2FA code in the database
     */
    private function storeCode(string $userId, string $codeHash, string $purpose, string $expiresAt): void
    {
        $sql = "INSERT INTO two_factor_codes 
                (user_id, code_hash, purpose, expires_at, ip_address, user_agent, created_at)
                VALUES 
                (:user_id, :code_hash, :purpose, :expires_at, :ip_address, :user_agent, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'code_hash' => $codeHash,
            'purpose' => $purpose,
            'expires_at' => $expiresAt,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512)
        ]);
    }
    
    /**
     * Get the active code for a user/purpose
     */
    private function getActiveCode(string $userId, string $purpose): ?array
    {
        $sql = "SELECT id, code_hash, expires_at, attempts
                FROM two_factor_codes
                WHERE user_id = :user_id
                AND purpose = :purpose
                AND consumed_at IS NULL
                AND expires_at > NOW()
                ORDER BY created_at DESC
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'purpose' => $purpose
        ]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Invalidate existing codes for a user/purpose
     */
    private function invalidateExistingCodes(string $userId, string $purpose): void
    {
        $sql = "UPDATE two_factor_codes 
                SET consumed_at = NOW()
                WHERE user_id = :user_id
                AND purpose = :purpose
                AND consumed_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'purpose' => $purpose
        ]);
    }
    
    /**
     * Invalidate a specific code
     */
    private function invalidateCode(int $codeId): void
    {
        $sql = "UPDATE two_factor_codes SET consumed_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $codeId]);
    }
    
    /**
     * Mark a code as consumed (successfully verified)
     */
    private function consumeCode(int $codeId): void
    {
        $sql = "UPDATE two_factor_codes SET consumed_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $codeId]);
    }
    
    /**
     * Increment failed verification attempts
     */
    private function incrementAttempts(int $codeId): void
    {
        $sql = "UPDATE two_factor_codes SET attempts = attempts + 1 WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $codeId]);
    }
    
    /**
     * Check rate limiting for 2FA requests
     * 
     * @param string $userId
     * @param string $type
     * @return array ['allowed' => bool, 'count' => int, 'retry_after' => int]
     */
    private function checkRateLimit(string $userId, string $type): array
    {
        $sql = "SELECT count, window_start, last_sent_at
                FROM email_rate_limits
                WHERE user_id = :user_id
                AND email_type = :email_type";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'email_type' => $type
        ]);
        
        $record = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$record) {
            return ['allowed' => true, 'count' => 0];
        }
        
        $windowStart = strtotime($record['window_start']);
        $windowEnd = $windowStart + 3600; // 1 hour window
        
        // If window has expired, reset
        if (time() > $windowEnd) {
            return ['allowed' => true, 'count' => 0];
        }
        
        // Check if under limit
        if ($record['count'] < self::MAX_CODES_PER_HOUR) {
            return ['allowed' => true, 'count' => $record['count']];
        }
        
        // Rate limited
        return [
            'allowed' => false,
            'count' => $record['count'],
            'retry_after' => $windowEnd - time()
        ];
    }
    
    /**
     * Increment rate limit counter
     */
    private function incrementRateLimit(string $userId, string $type): void
    {
        $sql = "INSERT INTO email_rate_limits (user_id, email_type, count, window_start, last_sent_at)
                VALUES (:user_id, :email_type, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    count = IF(window_start <= DATE_SUB(NOW(), INTERVAL 1 HOUR), 1, count + 1),
                    window_start = IF(window_start <= DATE_SUB(NOW(), INTERVAL 1 HOUR), NOW(), window_start),
                    last_sent_at = NOW()";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'email_type' => $type
        ]);
    }
    
    /**
     * Log email send to mail_log table
     */
    private function logMailSend(string $userId, string $email, string $type, bool $success): void
    {
        try {
            $sql = "INSERT INTO mail_log 
                    (user_id, recipient_email, email_type, status, sent_at, created_at)
                    VALUES 
                    (:user_id, :recipient_email, :email_type, :status, :sent_at, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'recipient_email' => $this->maskEmail($email),
                'email_type' => $type,
                'status' => $success ? 'sent' : 'failed',
                'sent_at' => $success ? date('Y-m-d H:i:s') : null
            ]);
        } catch (\Exception $e) {
            // Don't fail the main operation if logging fails
            $this->logError('Failed to log mail send', ['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Mask email address for logging (HIPAA compliance)
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***@***.***';
        }
        
        $name = $parts[0];
        $domain = $parts[1];
        
        $maskedName = strlen($name) > 3
            ? substr($name, 0, 3) . '***'
            : $name[0] . '***';
        
        return $maskedName . '@' . $domain;
    }
    
    /**
     * Cleanup expired codes (call from cron)
     */
    public function cleanupExpiredCodes(): array
    {
        try {
            $sql = "DELETE FROM two_factor_codes 
                    WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            $deleted = $stmt->rowCount();
            
            $this->log('Cleaned up expired 2FA codes', ['deleted' => $deleted]);
            
            return $this->formatResponse(true, ['deleted' => $deleted]);
            
        } catch (\Exception $e) {
            $this->logError('Failed to cleanup 2FA codes', ['error' => $e->getMessage()]);
            return $this->formatResponse(false, [], 'Cleanup failed');
        }
    }
}
