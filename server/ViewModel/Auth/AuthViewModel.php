<?php
/**
 * Authentication ViewModel
 * 
 * Business logic layer for authentication operations.
 * Handles login, 2FA verification, logout, session management.
 * 
 * Security: Audit logging, session fixation protection, CSRF validation
 * 
 * @package SafeShift\ViewModel\Auth
 */

namespace ViewModel\Auth;

use Core\Services\AuthService;
use Core\Services\AuditService;
use App\Core\Session;
use App\Repositories\UserRepository;
use ViewModel\Core\ApiResponse;
use Model\Services\RoleService;
use Exception;

class AuthViewModel
{
    /** @var AuthService Authentication service */
    private AuthService $authService;
    
    /** @var AuditService Audit logging service */
    private AuditService $auditService;
    
    /** @var UserRepository User repository */
    private UserRepository $userRepo;
    
    /** @var string Log file path */
    private string $logPath;
    
    /**
     * Constructor with dependency injection
     * 
     * @param AuthService|null $authService
     * @param AuditService|null $auditService
     * @param UserRepository|null $userRepo
     * @param string|null $logPath
     */
    public function __construct(
        ?AuthService $authService = null,
        ?AuditService $auditService = null,
        ?UserRepository $userRepo = null,
        ?string $logPath = null
    ) {
        $this->authService = $authService ?? new AuthService();
        $this->auditService = $auditService ?? new AuditService();
        $this->userRepo = $userRepo ?? new UserRepository();
        $this->logPath = $logPath ?? (defined('LOG_PATH') ? LOG_PATH : dirname(__DIR__, 2) . '/logs/');
    }
    
    /**
     * Handle login request
     * 
     * @param array $credentials Array containing 'username' and 'password'
     * @return array JSON-compatible response array
     */
    public function login(array $credentials): array
    {
        try {
            // Validate input
            $errors = $this->validateLoginCredentials($credentials);
            if (!empty($errors)) {
                $this->logAuthEvent('login_validation_failed', [
                    'username' => $credentials['username'] ?? 'unknown',
                    'errors' => array_keys($errors)
                ]);
                return ApiResponse::validationError($errors);
            }
            
            $username = $this->sanitizeString($credentials['username']);
            $password = $credentials['password']; // Don't sanitize password
            
            // NOTE: Session regeneration moved to Session::setUser() after successful login
            // Regenerating here causes session loss during 2FA flow because:
            // 1. Session::regenerate(true) deletes old session file immediately
            // 2. If browser sends old session ID (race condition), PHP strict mode rejects it
            // 3. New session is created, losing pending 2FA state
            
            // Attempt login via AuthService
            $result = $this->authService->login($username, $password);
            
            if (!$result['success']) {
                $this->logAuthEvent('login_failed', [
                    'username' => $username,
                    'reason' => $result['message'] ?? 'Unknown'
                ]);
                return ApiResponse::error($result['message'] ?? 'Login failed');
            }
            
            // Check if we need 2FA
            $data = $result['data'] ?? [];
            
            if (isset($data['stage']) && $data['stage'] === 'otp') {
                $this->logAuthEvent('login_2fa_required', [
                    'username' => $username
                ]);
                
                // Return sanitized user info for 2FA stage
                $pending = Session::getPending2FA();
                $responseData = [
                    'stage' => 'otp',
                    'user' => [
                        'username' => $pending['username'] ?? $username,
                        'email' => $this->maskEmail($pending['email'] ?? '')
                    ],
                    'message' => $data['message'] ?? 'Verification code sent'
                ];
                
                // In development mode, include OTP in response for testing
                if (isset($data['_dev_otp'])) {
                    $responseData['_dev_otp'] = $data['_dev_otp'];
                    $responseData['_dev_note'] = $data['_dev_note'] ?? 'Development mode - OTP included for testing';
                }
                
                return ApiResponse::success($responseData, 'Please enter verification code');
            }
            
            // Login complete (no 2FA required)
            $this->logAuthEvent('login_success', [
                'username' => $username
            ]);
            
            $user = $this->getCurrentUser();
            return ApiResponse::success([
                'stage' => 'complete',
                'user' => $user,
                'dashboard_url' => $data['dashboard_url'] ?? '/dashboard'
            ], 'Login successful');
            
        } catch (Exception $e) {
            $this->logError('login', $e);
            return ApiResponse::serverError('An error occurred during login');
        }
    }
    
    /**
     * Verify 2FA code
     *
     * @param string $code OTP code
     * @return array JSON-compatible response array
     */
    public function verify2FA(string $code): array
    {
        // Log entry to session_debug.log
        $this->logVerify2FADebug('VM-ENTER', $code, null, null);
        
        try {
            // Validate OTP code format
            $code = trim($code);
            
            $this->logVerify2FADebug('VM-FORMAT-CHECK', $code, null, [
                'code_length' => strlen($code),
                'is_numeric' => ctype_digit($code),
                'matches_pattern' => preg_match('/^\d{6}$/', $code) ? 'YES' : 'NO'
            ]);
            
            if (empty($code) || !preg_match('/^\d{6}$/', $code)) {
                $this->logVerify2FADebug('VM-FORMAT-INVALID', $code, null, [
                    'reason' => 'Code is empty or does not match 6-digit pattern'
                ]);
                return ApiResponse::validationError([
                    'code' => ['Please enter a valid 6-digit code']
                ]);
            }
            
            // Check if we have pending 2FA
            $pending = Session::getPending2FA();
            
            $this->logVerify2FADebug('VM-PENDING-CHECK', $code, $pending, [
                'has_pending' => $pending ? 'YES' : 'NO',
                'has_user_id' => (!empty($pending['user_id'])) ? 'YES' : 'NO'
            ]);
            
            if (!$pending || empty($pending['user_id'])) {
                $this->logVerify2FADebug('VM-NO-PENDING', $code, $pending, [
                    'reason' => 'No pending_2fa or missing user_id'
                ]);
                $this->logAuthEvent('2fa_no_pending_session', []);
                return ApiResponse::error('Session expired. Please login again.');
            }
            
            $this->logVerify2FADebug('VM-CALLING-SERVICE', $code, $pending, [
                'user_id' => $pending['user_id']
            ]);
            
            // Verify OTP via AuthService
            $result = $this->authService->verify2FA($code);
            
            $this->logVerify2FADebug('VM-SERVICE-RESULT', $code, $pending, [
                'success' => $result['success'] ? 'YES' : 'NO',
                'message' => $result['message'] ?? '(none)',
                'has_data' => isset($result['data']) ? 'YES' : 'NO'
            ]);
            
            if (!$result['success']) {
                $this->logVerify2FADebug('VM-VERIFY-FAILED', $code, $pending, [
                    'reason' => $result['message'] ?? 'Invalid code'
                ]);
                $this->logAuthEvent('2fa_verification_failed', [
                    'user_id' => $pending['user_id'],
                    'reason' => $result['message'] ?? 'Invalid code'
                ]);
                return ApiResponse::error($result['message'] ?? 'Verification failed');
            }
            
            // 2FA successful
            $this->logVerify2FADebug('VM-VERIFY-SUCCESS', $code, $pending, [
                'user_id' => $pending['user_id']
            ]);
            
            $this->logAuthEvent('2fa_verification_success', [
                'user_id' => $pending['user_id']
            ]);
            
            $user = $this->getCurrentUser();
            $dashboardUrl = $result['data']['dashboard_url'] ?? '/dashboard';
            
            $this->logVerify2FADebug('VM-COMPLETE', $code, $pending, [
                'dashboard_url' => $dashboardUrl,
                'user_retrieved' => $user ? 'YES' : 'NO'
            ]);
            
            return ApiResponse::success([
                'stage' => 'complete',
                'user' => $user,
                'dashboard_url' => $dashboardUrl
            ], 'Verification successful');
            
        } catch (Exception $e) {
            $this->logVerify2FADebug('VM-EXCEPTION', $code, null, [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            $this->logError('verify2FA', $e);
            return ApiResponse::serverError('An error occurred during verification');
        }
    }
    
    /**
     * Log verify-2fa debug information to session_debug.log
     *
     * @param string $step Current step in verification
     * @param string $code The OTP code (will be masked)
     * @param array|null $pending Session pending_2fa data
     * @param array|null $extra Additional context
     */
    private function logVerify2FADebug(string $step, string $code, ?array $pending, ?array $extra): void
    {
        try {
            $debugFile = $this->logPath . 'session_debug.log';
            $timestamp = date('Y-m-d H:i:s.') . substr(microtime(), 2, 3);
            
            // Mask OTP code (show last 3 digits)
            $maskedCode = strlen($code) >= 3 ? '***' . substr($code, -3) : '***';
            
            $log = "[{$timestamp}] VERIFY-2FA-VIEWMODEL {$step}\n";
            $log .= "  Session ID: " . session_id() . "\n";
            $log .= "  Submitted OTP: {$maskedCode}\n";
            
            if ($pending) {
                $log .= "  pending_2fa:\n";
                $log .= "    user_id: " . ($pending['user_id'] ?? '(none)') . "\n";
                $log .= "    username: " . ($pending['username'] ?? '(none)') . "\n";
                $log .= "    email: " . ($pending['email'] ?? '(none)') . "\n";
            } else {
                $log .= "  pending_2fa: NULL\n";
            }
            
            if ($extra) {
                $log .= "  Extra:\n";
                foreach ($extra as $key => $value) {
                    $log .= "    {$key}: {$value}\n";
                }
            }
            
            $log .= "---\n";
            
            file_put_contents($debugFile, $log, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            // Don't let logging failure break authentication
            error_log('Failed to write verify2FA debug log: ' . $e->getMessage());
        }
    }
    
    /**
     * Resend OTP code
     *
     * @return array JSON-compatible response array
     */
    public function resendOtp(): array
    {
        try {
            // Check if we have pending 2FA
            $pending = Session::getPending2FA();
            if (!$pending || empty($pending['user_id'])) {
                return ApiResponse::error('Session expired. Please login again.');
            }
            
            // Request new OTP via AuthService
            $result = $this->authService->resendOtp();
            
            if (!$result['success']) {
                $this->logAuthEvent('otp_resend_failed', [
                    'user_id' => $pending['user_id'],
                    'reason' => $result['message'] ?? 'Unknown'
                ]);
                return ApiResponse::error($result['message'] ?? 'Failed to resend code');
            }
            
            $this->logAuthEvent('otp_resend_success', [
                'user_id' => $pending['user_id']
            ]);
            
            $responseData = [
                'message' => $result['data']['message'] ?? 'New verification code sent'
            ];
            
            // In development mode, include OTP in response for testing
            if (isset($result['data']['_dev_otp'])) {
                $responseData['_dev_otp'] = $result['data']['_dev_otp'];
                $responseData['_dev_note'] = $result['data']['_dev_note'] ?? 'Development mode - OTP included for testing';
            }
            
            return ApiResponse::success($responseData, 'Verification code resent');
            
        } catch (Exception $e) {
            $this->logError('resendOtp', $e);
            return ApiResponse::serverError('An error occurred while resending code');
        }
    }
    
    /**
     * Logout current user
     * 
     * @return array JSON-compatible response array
     */
    public function logout(): array
    {
        try {
            $user = Session::getUser();
            $userId = $user['user_id'] ?? 'unknown';
            $username = $user['username'] ?? 'unknown';
            
            // Log before destroying session
            $this->logAuthEvent('logout', [
                'user_id' => $userId,
                'username' => $username
            ]);
            
            // Perform logout via AuthService
            $this->authService->logout();
            
            return ApiResponse::success(null, 'Logged out successfully');
            
        } catch (Exception $e) {
            $this->logError('logout', $e);
            // Still return success even if logging failed
            return ApiResponse::success(null, 'Logged out');
        }
    }
    
    /**
     * Get current authenticated user
     *
     * Returns user data with role mappings for frontend consistency.
     * Includes both backend role and UI role for flexibility.
     *
     * @return array|null User data (sanitized) or null if not authenticated
     */
    public function getCurrentUser(): ?array
    {
        try {
            $user = Session::getUser();
            
            if (!$user || empty($user['user_id'])) {
                return null;
            }
            
            // Get additional user data from repository
            $fullUser = $this->userRepo->findById($user['user_id']);
            $primaryRole = $this->userRepo->getUserPrimaryRole($user['user_id']);
            $roles = $this->userRepo->getUserRoles($user['user_id']);
            
            // Determine backend role from primary role or first role
            $backendRole = $this->extractBackendRole($primaryRole, $roles);
            
            // Return sanitized user data with role mappings
            return $this->formatUserResponse([
                'user_id' => $user['user_id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'first_name' => $fullUser['first_name'] ?? null,
                'last_name' => $fullUser['last_name'] ?? null,
                'role' => $backendRole,
                'primary_role' => $primaryRole,
                'roles' => $roles,
                'clinic_id' => $fullUser['clinic_id'] ?? null,
                'clinic_name' => $fullUser['clinic_name'] ?? null,
                'two_factor_enabled' => $fullUser['two_factor_enabled'] ?? false,
                'last_login' => $fullUser['last_login'] ?? null,
                'logged_in_at' => $user['logged_in_at'] ?? null
            ]);
            
        } catch (Exception $e) {
            $this->logError('getCurrentUser', $e);
            return null;
        }
    }
    
    /**
     * Format user data for API response with role mappings
     *
     * Converts internal user data to frontend-compatible format with
     * both backend and UI role information for flexibility.
     *
     * @param array $user Raw user data
     * @return array Formatted user response
     */
    private function formatUserResponse(array $user): array
    {
        $backendRole = $user['role'] ?? 'unknown';
        
        return [
            'id' => $user['user_id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'firstName' => $user['first_name'],
            'lastName' => $user['last_name'],
            // Backend role (original database value)
            'role' => $backendRole,
            // UI role for frontend routing (mapped from backend role)
            'uiRole' => RoleService::toUiRole($backendRole),
            // Human-readable display name
            'displayRole' => RoleService::getDisplayName($backendRole),
            // Permissions array for frontend access control
            'permissions' => RoleService::getPermissions($backendRole),
            // Default dashboard route for this role
            'dashboardRoute' => RoleService::getDashboardRoute($backendRole),
            // Legacy primary_role object for backwards compatibility
            'primary_role' => $user['primary_role'] ? [
                'id' => $user['primary_role']['role_id'],
                'name' => $user['primary_role']['name'],
                'slug' => $user['primary_role']['slug']
            ] : null,
            // All assigned roles
            'roles' => array_map(function($role) {
                return [
                    'id' => $role['role_id'],
                    'name' => $role['name'],
                    'slug' => $role['slug']
                ];
            }, $user['roles'] ?? []),
            // Clinic information
            'clinicId' => $user['clinic_id'],
            'clinicName' => $user['clinic_name'],
            // Security settings
            'twoFactorEnabled' => (bool)($user['two_factor_enabled'] ?? false),
            // Timestamps
            'lastLogin' => $user['last_login'],
            'logged_in_at' => $user['logged_in_at']
        ];
    }
    
    /**
     * Extract backend role from primary role or roles array
     *
     * @param array|null $primaryRole Primary role data
     * @param array $roles All user roles
     * @return string Backend role string
     */
    private function extractBackendRole(?array $primaryRole, array $roles): string
    {
        // Try primary role slug first
        if ($primaryRole && !empty($primaryRole['slug'])) {
            return $primaryRole['slug'];
        }
        
        // Try primary role name
        if ($primaryRole && !empty($primaryRole['name'])) {
            return $primaryRole['name'];
        }
        
        // Try first role in roles array
        if (!empty($roles) && isset($roles[0])) {
            if (!empty($roles[0]['slug'])) {
                return $roles[0]['slug'];
            }
            if (!empty($roles[0]['name'])) {
                return $roles[0]['name'];
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Get CSRF token for the current session
     * 
     * @return string CSRF token
     */
    public function getCsrfToken(): string
    {
        return Session::getCsrfToken();
    }
    
    /**
     * Refresh session to extend timeout
     * 
     * @return array JSON-compatible response array
     */
    public function refreshSession(): array
    {
        try {
            if (!Session::isLoggedIn()) {
                return ApiResponse::unauthorized('Session expired. Please login again.');
            }
            
            // Update last activity time
            $user = Session::getUser();
            if ($user) {
                $user['logged_in_at'] = time();
                Session::set('user', $user);
            }
            
            // Regenerate session ID periodically for security
            Session::regenerate(false);
            
            $this->logAuthEvent('session_refreshed', [
                'user_id' => $user['user_id'] ?? 'unknown'
            ]);
            
            $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 1200;
            
            return ApiResponse::success([
                'expires_in' => $timeout,
                'expires_at' => gmdate('Y-m-d\TH:i:s\Z', time() + $timeout)
            ], 'Session refreshed');
            
        } catch (Exception $e) {
            $this->logError('refreshSession', $e);
            return ApiResponse::serverError('Failed to refresh session');
        }
    }
    
    /**
     * Get session status
     *
     * Detects:
     * 1. Pending 2FA (user has completed credentials but not OTP)
     * 2. Fully authenticated users
     * 3. Unauthenticated users
     *
     * @return array JSON-compatible response array
     */
    public function getSessionStatus(): array
    {
        try {
            // FIRST: Check if user is in the middle of 2FA flow
            // This takes priority because pending_2fa means credentials were valid
            $pending = Session::getPending2FA();
            
            if ($pending && !empty($pending['user_id'])) {
                // User has completed credentials but not OTP
                // Check if the pending 2FA hasn't expired
                if (isset($pending['expires']) && $pending['expires'] > time()) {
                    // Log for debugging
                    $this->logSessionStatusDebug('pending_2fa_found', $pending);
                    
                    return ApiResponse::success([
                        'valid' => true,  // Session is valid (credentials passed)
                        'authenticated' => false,  // But not fully authenticated
                        'stage' => 'otp',  // Tell React we're in OTP stage
                        'user' => [
                            'username' => $pending['username'] ?? null,
                            'email' => $this->maskEmail($pending['email'] ?? '')
                            // Don't expose user_id for security
                        ]
                    ]);
                } else {
                    // Pending 2FA has expired
                    $this->logSessionStatusDebug('pending_2fa_expired', $pending);
                    Session::clearPending2FA();
                }
            }
            
            // SECOND: Check if user is fully logged in
            $isLoggedIn = Session::isLoggedIn();
            
            if (!$isLoggedIn) {
                return ApiResponse::success([
                    'valid' => false,
                    'authenticated' => false,
                    'stage' => 'idle',
                    'user' => null
                ]);
            }
            
            // THIRD: User is fully authenticated
            $user = Session::getUser();
            $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 1200;
            $loggedInAt = $user['logged_in_at'] ?? time();
            $expiresAt = $loggedInAt + $timeout;
            $remainingSeconds = max(0, $expiresAt - time());
            
            // Check if session is about to expire (within 5 minutes)
            $isExpiring = $remainingSeconds < 300;
            
            return ApiResponse::success([
                'valid' => true,
                'authenticated' => true,
                'stage' => 'authenticated',
                'user' => $this->getCurrentUser(),
                'session' => [
                    'logged_in_at' => gmdate('Y-m-d\TH:i:s\Z', $loggedInAt),
                    'expires_at' => gmdate('Y-m-d\TH:i:s\Z', $expiresAt),
                    'remaining_seconds' => $remainingSeconds,
                    'is_expiring' => $isExpiring
                ]
            ]);
            
        } catch (Exception $e) {
            $this->logError('getSessionStatus', $e);
            return ApiResponse::serverError('Failed to get session status');
        }
    }
    
    /**
     * Log session status debug information
     *
     * @param string $event Event type
     * @param array|null $context Additional context
     */
    private function logSessionStatusDebug(string $event, ?array $context = null): void
    {
        try {
            $debugFile = $this->logPath . 'session_debug.log';
            $timestamp = date('Y-m-d H:i:s.') . substr(microtime(), 2, 3);
            
            $log = "[{$timestamp}] SESSION-STATUS {$event}\n";
            $log .= "  Session ID: " . session_id() . "\n";
            
            if ($context) {
                $log .= "  Context:\n";
                foreach ($context as $key => $value) {
                    if ($key !== 'otp_hash') {  // Don't log sensitive data
                        $log .= "    {$key}: " . (is_array($value) ? json_encode($value) : $value) . "\n";
                    }
                }
            }
            
            $log .= "---\n";
            
            file_put_contents($debugFile, $log, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            // Don't let logging failure break the response
            error_log('Failed to write session status debug log: ' . $e->getMessage());
        }
    }
    
    /**
     * Validate CSRF token
     * 
     * @param string $token CSRF token to validate
     * @return bool True if valid
     */
    public function validateCsrfToken(string $token): bool
    {
        return Session::validateCsrfToken($token);
    }
    
    /**
     * Check if user is authenticated
     * 
     * @return bool True if authenticated
     */
    public function isAuthenticated(): bool
    {
        return Session::isLoggedIn();
    }
    
    // ========== PRIVATE HELPER METHODS ==========
    
    /**
     * Validate login credentials
     * 
     * @param array $credentials
     * @return array Validation errors (empty if valid)
     */
    private function validateLoginCredentials(array $credentials): array
    {
        $errors = [];
        
        if (empty($credentials['username'])) {
            $errors['username'] = ['Username is required'];
        } elseif (strlen($credentials['username']) < 3) {
            $errors['username'] = ['Username must be at least 3 characters'];
        } elseif (strlen($credentials['username']) > 100) {
            $errors['username'] = ['Username is too long'];
        }
        
        if (empty($credentials['password'])) {
            $errors['password'] = ['Password is required'];
        } elseif (strlen($credentials['password']) < 1) {
            $errors['password'] = ['Password is required'];
        }
        
        return $errors;
    }
    
    /**
     * Sanitize string input
     * 
     * @param string $value
     * @return string
     */
    private function sanitizeString(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Mask email address for privacy
     * 
     * @param string $email
     * @return string Masked email (e.g., j***@example.com)
     */
    private function maskEmail(string $email): string
    {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '***@***.***';
        }
        
        $parts = explode('@', $email);
        $local = $parts[0];
        $domain = $parts[1] ?? '';
        
        // Show first character of local part
        $maskedLocal = strlen($local) > 1 
            ? substr($local, 0, 1) . str_repeat('*', min(5, strlen($local) - 1))
            : '*';
        
        return $maskedLocal . '@' . $domain;
    }
    
    /**
     * Log authentication event
     * 
     * @param string $action Event action
     * @param array $context Additional context
     */
    private function logAuthEvent(string $action, array $context = []): void
    {
        try {
            // Use AuditService for formal audit logging
            $this->auditService->audit(
                strtoupper('AUTH_' . $action),
                'User',
                $context['user_id'] ?? null,
                $context
            );
            
            // Also log to file for debugging
            $logEntry = [
                'timestamp' => date('Y-m-d H:i:s'),
                'action' => $action,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 200),
                'context' => $context
            ];
            
            $logFile = $this->logPath . 'auth_' . date('Y-m-d') . '.log';
            $logLine = date('Y-m-d H:i:s') . ' | AUTH | ' . json_encode($logEntry) . PHP_EOL;
            file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
            
        } catch (Exception $e) {
            error_log('Failed to log auth event: ' . $e->getMessage());
        }
    }
    
    /**
     * Log error
     * 
     * @param string $method Method where error occurred
     * @param Exception $e Exception
     * @param array $context Additional context
     */
    private function logError(string $method, Exception $e, array $context = []): void
    {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'class' => 'AuthViewModel',
            'method' => $method,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'context' => $context
        ];
        
        error_log('AuthViewModel Error: ' . json_encode($logEntry));
        
        try {
            $logFile = $this->logPath . 'error_' . date('Y-m-d') . '.log';
            $logLine = date('Y-m-d H:i:s') . ' | AUTH_ERROR | ' . json_encode($logEntry) . PHP_EOL;
            file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
        } catch (Exception $logException) {
            error_log('Failed to write to error log: ' . $logException->getMessage());
        }
    }
}
