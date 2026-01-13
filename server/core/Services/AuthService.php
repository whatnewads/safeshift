<?php
/**
 * Authentication Service
 * 
 * Handles authentication business logic
 */

namespace Core\Services;

use App\Repositories\UserRepository;
use App\Repositories\OtpRepository;
use App\Core\Session;
use Exception;

class AuthService extends BaseService
{
    private UserRepository $userRepo;
    private OtpRepository $otpRepo;
    private EmailService $emailService;
    private AuditService $auditService;
    
    public function __construct(
        ?UserRepository $userRepo = null,
        ?OtpRepository $otpRepo = null,
        ?EmailService $emailService = null,
        ?AuditService $auditService = null
    ) {
        parent::__construct();
        
        $this->userRepo = $userRepo ?? new UserRepository();
        $this->otpRepo = $otpRepo ?? new OtpRepository();
        $this->emailService = $emailService ?? new EmailService();
        $this->auditService = $auditService ?? new AuditService();
    }
    
    /**
     * Start login process
     * 
     * @param string $username
     * @param string $password
     * @return array
     */
    public function login(string $username, string $password): array
    {
        try {
            // Validate input
            if (empty($username) || empty($password)) {
                return $this->formatResponse(false, [], 'Username and password are required');
            }
            
            // Get user by username
            $user = $this->userRepo->findByUsername($username);
            
            if (!$user) {
                $this->auditService->logFailedLogin($username, 'User not found');
                return $this->formatResponse(false, [], 'Invalid username or password');
            }
            
            // Check if account is locked
            if ($this->userRepo->isLocked($user['user_id'])) {
                $this->auditService->logFailedLogin($username, 'Account locked', $user['user_id']);
                return $this->formatResponse(false, [], 'Account is locked. Please try again later.');
            }
            
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                $this->userRepo->incrementFailedAttempts($user['user_id']);
                
                // Check if we need to lock the account
                $loginAttempts = ($user['login_attempts'] ?? 0) + 1;
                if ($loginAttempts >= ($this->getConfig('MAX_FAILED_LOGIN_ATTEMPTS', 5))) {
                    $this->userRepo->lockAccount($user['user_id'], $this->getConfig('LOCKOUT_DURATION_MINUTES', 30));
                    $this->auditService->logSecurityEvent('ACCOUNT_LOCKED', ['user_id' => $user['user_id']]);
                }
                
                $this->auditService->logFailedLogin($username, 'Invalid password', $user['user_id']);
                return $this->formatResponse(false, [], 'Invalid username or password');
            }
            
            // Check if MFA is enabled
            if ($user['mfa_enabled']) {
                // Generate OTP
                $otpCode = OtpRepository::generateCode();
                $otpId = $this->otpRepo->createOtp($user['user_id'], $otpCode, 10);
                
                if (!$otpId) {
                    $this->logError('Failed to create OTP', ['user_id' => $user['user_id']]);
                    return $this->formatResponse(false, [], 'Failed to generate verification code');
                }
                
                // Store pending 2FA in session
                Session::setPending2FA([
                    'user_id' => $user['user_id'],
                    'username' => $user['username'],
                    'email' => $user['email']
                ]);
                
                // Send OTP email
                $emailResult = $this->emailService->sendOtp($user['email'], $otpCode, $user['username']);
                
                $message = $emailResult['success']
                    ? 'A verification code has been sent to your email.'
                    : 'A verification code has been generated.';
                
                // In development mode: log OTP to file and include in response
                $responseData = [
                    'stage' => 'otp',
                    'message' => $message
                ];
                
                if ($this->isDevelopment()) {
                    // Log OTP to file for easy retrieval
                    $this->logOtpToFile($user['username'], $user['email'], $otpCode);
                    
                    // Include OTP in response data (DEV ONLY - never in production!)
                    $responseData['_dev_otp'] = $otpCode;
                    $responseData['_dev_note'] = 'OTP also logged to logs/otp.log';
                    
                    // Also append to message if email failed
                    if (!$emailResult['success']) {
                        $message .= " [DEV] Your code is: $otpCode";
                        $responseData['message'] = $message;
                    }
                }
                
                // Store message in session for display
                Session::flash('otp_message', $message);
                
                $this->log('2FA initiated', ['user_id' => $user['user_id']]);
                
                return $this->formatResponse(true, $responseData);
            } else {
                // No MFA, complete login
                $this->completeLogin($user);
                
                return $this->formatResponse(true, [
                    'stage' => 'complete',
                    'dashboard_url' => $this->userRepo->getUserDashboardUrl($user['user_id'])
                ]);
            }
            
        } catch (Exception $e) {
            $this->logError('Login error', ['error' => $e->getMessage()]);
            return $this->formatResponse(false, [], 'An error occurred during login');
        }
    }
    
    /**
     * Complete login with OTP verification
     *
     * @param string $otpCode
     * @return array
     */
    public function verify2FA(string $otpCode): array
    {
        // Log to session_debug.log for detailed tracing
        $this->logVerifyDebug('ENTER', $otpCode, null, null);
        
        try {
            // Get pending 2FA data
            $pending2FA = Session::getPending2FA();
            
            $this->logVerifyDebug('SESSION-CHECK', $otpCode, $pending2FA, null);
            
            if (!$pending2FA || empty($pending2FA['user_id'])) {
                $this->logVerifyDebug('NO-PENDING-2FA', $otpCode, $pending2FA, [
                    'reason' => 'No pending_2fa in session or missing user_id'
                ]);
                return $this->formatResponse(false, [], 'Invalid session. Please login again.');
            }
            
            $userId = $pending2FA['user_id'];
            
            // Normalize OTP code
            $otpCode = trim($otpCode);
            
            $this->logVerifyDebug('NORMALIZED-CODE', $otpCode, $pending2FA, [
                'user_id' => $userId,
                'code_length' => strlen($otpCode)
            ]);
            
            // Get recent OTPs for debugging (always log to session_debug)
            $recentOtps = $this->otpRepo->getRecentOtps($userId, 5);
            $this->logVerifyDebug('DB-RECENT-OTPS', $otpCode, $pending2FA, [
                'user_id' => $userId,
                'recent_otps_count' => count($recentOtps),
                'recent_otps' => $recentOtps
            ]);
            
            // Debug logging in development (existing logger)
            if ($this->isDevelopment()) {
                $this->logger->debug('otp_verification', [
                    'user_id' => $userId,
                    'entered_code' => $otpCode,
                    'recent_otps' => $recentOtps
                ]);
            }
            
            // Find valid OTP
            $otp = $this->otpRepo->findValidOtp($userId, $otpCode);
            
            $this->logVerifyDebug('FIND-VALID-OTP', $otpCode, $pending2FA, [
                'user_id' => $userId,
                'otp_found' => $otp ? 'YES' : 'NO',
                'otp_data' => $otp
            ]);
            
            if (!$otp) {
                // Check why OTP failed
                $status = $this->otpRepo->checkOtpStatus($userId, $otpCode);
                
                $this->logVerifyDebug('OTP-INVALID', $otpCode, $pending2FA, [
                    'user_id' => $userId,
                    'status' => $status
                ]);
                
                $this->auditService->audit('LOGIN_2FA_FAILED', 'User', $userId, [
                    'reason' => $status['reason'],
                    'code_entered' => substr($otpCode, 0, 2) . '****'
                ]);
                
                return $this->formatResponse(false, [], $status['reason']);
            }
            
            // Mark OTP as consumed
            $this->otpRepo->markAsConsumed($otp['otp_id']);
            
            $this->logVerifyDebug('OTP-CONSUMED', $otpCode, $pending2FA, [
                'user_id' => $userId,
                'otp_id' => $otp['otp_id']
            ]);
            
            // Get full user data
            $user = $this->userRepo->findById($userId);
            
            if (!$user) {
                $this->logVerifyDebug('USER-NOT-FOUND', $otpCode, $pending2FA, [
                    'user_id' => $userId
                ]);
                return $this->formatResponse(false, [], 'User not found');
            }
            
            // Complete login
            $this->completeLogin($user);
            
            $this->logVerifyDebug('LOGIN-COMPLETE', $otpCode, $pending2FA, [
                'user_id' => $userId,
                'username' => $user['username']
            ]);
            
            $this->log('2FA completed', ['user_id' => $userId]);
            
            return $this->formatResponse(true, [
                'dashboard_url' => $this->userRepo->getUserDashboardUrl($userId)
            ]);
            
        } catch (Exception $e) {
            $this->logVerifyDebug('EXCEPTION', $otpCode, null, [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            $this->logError('2FA verification error', ['error' => $e->getMessage()]);
            return $this->formatResponse(false, [], 'Verification failed');
        }
    }
    
    /**
     * Log verify-2fa debug information to session_debug.log
     *
     * @param string $step Current step in verification
     * @param string $otpCode The OTP code (will be masked)
     * @param array|null $pending2FA Session pending_2fa data
     * @param array|null $extra Additional context
     */
    private function logVerifyDebug(string $step, string $otpCode, ?array $pending2FA, ?array $extra): void
    {
        try {
            $logFile = dirname(__DIR__, 2) . '/logs/session_debug.log';
            $timestamp = date('Y-m-d H:i:s.') . substr(microtime(), 2, 3);
            
            // Mask OTP code (show last 3 digits)
            $maskedOtp = strlen($otpCode) >= 3 ? '***' . substr($otpCode, -3) : '***';
            
            $log = "[{$timestamp}] VERIFY-2FA-SERVICE {$step}\n";
            $log .= "  Session ID: " . session_id() . "\n";
            $log .= "  Submitted OTP: {$maskedOtp}\n";
            
            if ($pending2FA) {
                $log .= "  pending_2fa:\n";
                $log .= "    user_id: " . ($pending2FA['user_id'] ?? '(none)') . "\n";
                $log .= "    username: " . ($pending2FA['username'] ?? '(none)') . "\n";
                $log .= "    email: " . ($pending2FA['email'] ?? '(none)') . "\n";
            } else {
                $log .= "  pending_2fa: NULL\n";
            }
            
            if ($extra) {
                $log .= "  Extra Data:\n";
                foreach ($extra as $key => $value) {
                    if (is_array($value)) {
                        $log .= "    {$key}: " . json_encode($value) . "\n";
                    } else {
                        $log .= "    {$key}: {$value}\n";
                    }
                }
            }
            
            $log .= "---\n";
            
            file_put_contents($logFile, $log, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            // Don't let logging failure break authentication
            error_log('Failed to write verify debug log: ' . $e->getMessage());
        }
    }
    
    /**
     * Complete the login process
     * 
     * @param array $user
     */
    private function completeLogin(array $user): void
    {
        // Set user session data
        Session::setUser($user);
        
        // Update last login time
        $this->userRepo->updateLastLogin($user['user_id']);
        
        // Log successful login
        $this->auditService->audit('LOGIN_SUCCESS', 'User', $user['user_id'], [
            'username' => $user['username'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }
    
    /**
     * Logout user
     * 
     * @return void
     */
    public function logout(): void
    {
        $user = Session::getUser();
        
        if ($user) {
            $this->auditService->audit('LOGOUT', 'User', $user['user_id'], [
                'username' => $user['username']
            ]);
        }
        
        // Destroy session
        Session::destroy();
    }
    
    /**
     * Get current user
     * 
     * @return array|null
     */
    public function getCurrentUser(): ?array
    {
        return Session::getUser();
    }
    
    /**
     * Check if user is logged in
     * 
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return Session::isLoggedIn();
    }
    
    /**
     * Require user to be logged in
     * 
     * @param string|null $redirectUrl
     * @return void
     */
    public function requireLogin(?string $redirectUrl = null): void
    {
        if (!$this->isLoggedIn()) {
            // Store current URL for redirect after login
            if ($redirectUrl === null) {
                $redirectUrl = $_SERVER['REQUEST_URI'] ?? '/';
            }
            Session::set('redirect_after_login', $redirectUrl);
            
            header('Location: /login');
            exit;
        }
        
        // Check session timeout
        if (!Session::checkTimeout($this->getConfig('SESSION_TIMEOUT', 1200))) {
            header('Location: /login?timeout=1');
            exit;
        }
    }
    
    /**
     * Check if user has role
     * 
     * @param string $roleName
     * @param string|null $userId
     * @return bool
     */
    public function userHasRole(string $roleName, ?string $userId = null): bool
    {
        if ($userId === null) {
            $user = $this->getCurrentUser();
            if (!$user) {
                return false;
            }
            $userId = $user['user_id'];
        }
        
        return $this->userRepo->hasRole($userId, $roleName);
    }
    
    /**
     * Get user's primary role
     * 
     * @param string|null $userId
     * @return array|null
     */
    public function getPrimaryRole(?string $userId = null): ?array
    {
        if ($userId === null) {
            $user = $this->getCurrentUser();
            if (!$user) {
                return null;
            }
            $userId = $user['user_id'];
        }
        
        return $this->userRepo->getUserPrimaryRole($userId);
    }
    
    /**
     * Resend OTP
     *
     * @return array
     */
    public function resendOtp(): array
    {
        try {
            $pending2FA = Session::getPending2FA();
            
            if (!$pending2FA || empty($pending2FA['user_id'])) {
                return $this->formatResponse(false, [], 'Invalid session. Please login again.');
            }
            
            // Check rate limiting - max 5 active OTPs
            $activeCount = $this->otpRepo->countActiveOtps($pending2FA['user_id']);
            if ($activeCount >= 5) {
                return $this->formatResponse(false, [], 'Too many verification codes requested. Please wait before requesting another.');
            }
            
            // Generate new OTP
            $otpCode = OtpRepository::generateCode();
            $otpId = $this->otpRepo->createOtp($pending2FA['user_id'], $otpCode, 10);
            
            if (!$otpId) {
                return $this->formatResponse(false, [], 'Failed to generate verification code');
            }
            
            // Send OTP email
            $emailResult = $this->emailService->sendOtp(
                $pending2FA['email'],
                $otpCode,
                $pending2FA['username']
            );
            
            $message = $emailResult['success']
                ? 'A new verification code has been sent to your email.'
                : 'A new verification code has been generated.';
            
            // In development mode: log OTP to file and include in response
            $responseData = ['message' => $message];
            
            if ($this->isDevelopment()) {
                // Log OTP to file for easy retrieval
                $this->logOtpToFile($pending2FA['username'], $pending2FA['email'], $otpCode);
                
                // Include OTP in response data (DEV ONLY - never in production!)
                $responseData['_dev_otp'] = $otpCode;
                $responseData['_dev_note'] = 'OTP also logged to logs/otp.log';
                
                // Also append to message if email failed
                if (!$emailResult['success']) {
                    $message .= " [DEV] Your code is: $otpCode";
                    $responseData['message'] = $message;
                }
            }
            
            $this->log('OTP resent', ['user_id' => $pending2FA['user_id']]);
            
            return $this->formatResponse(true, $responseData);
            
        } catch (Exception $e) {
            $this->logError('Resend OTP error', ['error' => $e->getMessage()]);
            return $this->formatResponse(false, [], 'Failed to resend verification code');
        }
    }
    
    /**
     * Validate CSRF token
     *
     * @param string $token
     * @return bool
     */
    public function validateCsrfToken(string $token): bool
    {
        return Session::validateCsrfToken($token);
    }
    
    /**
     * Get CSRF token
     *
     * @return string
     */
    public function getCsrfToken(): string
    {
        return Session::getCsrfToken();
    }
    
    /**
     * Log OTP to file for development mode
     *
     * Creates a dedicated otp.log file that developers can check
     * to retrieve OTP codes when email is not configured.
     *
     * @param string $username
     * @param string $email
     * @param string $otpCode
     * @return void
     */
    private function logOtpToFile(string $username, string $email, string $otpCode): void
    {
        try {
            $logPath = defined('LOG_PATH') ? LOG_PATH : dirname(__DIR__, 2) . '/logs/';
            $logFile = $logPath . 'otp.log';
            
            // Ensure logs directory exists
            if (!is_dir($logPath)) {
                mkdir($logPath, 0755, true);
            }
            
            $logEntry = sprintf(
                "[%s] OTP for %s (%s): %s (expires in 10 minutes) - session_id: %s\n",
                date('Y-m-d H:i:s'),
                $username,
                $email,
                $otpCode,
                session_id()
            );
            
            // Append to log file
            file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
            
            // Also log to system log for visibility
            $this->log('DEV: OTP generated', [
                'username' => $username,
                'otp_logged_to' => $logFile
            ]);
            
        } catch (Exception $e) {
            // Don't let logging failure break authentication
            error_log('Failed to log OTP to file: ' . $e->getMessage());
        }
    }
}